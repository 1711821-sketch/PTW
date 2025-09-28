<?php
session_start();
require_once 'database.php';

// Ensure the user is logged in.  The session key 'user' is set in login.php.
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

try {
    $db = Database::getInstance();
    $timeEntry = new TimeEntry();
    
    // Load work orders from database
    $woData = $db->fetchAll("
        SELECT wo.*, 
               STRING_AGG(DISTINCT a.role || ':' || a.approved_date, '|') as approvals_data
        FROM work_orders wo 
        LEFT JOIN approvals a ON wo.id = a.work_order_id 
        GROUP BY wo.id
        ORDER BY wo.id
    ");
    
    // Convert approval data to match old JSON format
    foreach ($woData as &$wo) {
        $wo['approvals'] = [];
        if ($wo['approvals_data']) {
            $approvals = explode('|', $wo['approvals_data']);
            foreach ($approvals as $approval) {
                $parts = explode(':', $approval);
                if (count($parts) == 2) {
                    $wo['approvals'][$parts[0]] = $parts[1];
                }
            }
        }
        unset($wo['approvals_data']);
    }
    
} catch (Exception $e) {
    // Fallback to JSON if database fails
    if (file_exists("wo_data.json")) {
        $woData = json_decode(file_get_contents("wo_data.json"), true);
    } else {
        $woData = [];
    }
}

// --- STATUS TÆLLING ---
$counts = ["planning" => 0, "active" => 0, "completed" => 0];
foreach ($woData as $wo) {
    if (isset($wo["status"])) {
        $counts[$wo["status"]] = ($counts[$wo["status"]] ?? 0) + 1;
    }
}

// --- ENTREPRENØR STATISTIK ---
$entreprenorStats = [];
$today = new DateTime();
foreach ($woData as $wo) {
    if (!empty($wo["entreprenor_firma"]) && isset($wo["approvals"]["entreprenor"])) {
        $firma = $wo["entreprenor_firma"];
        $startDate = new DateTime($wo["approvals"]["entreprenor"]);

        if ($wo["status"] === "completed") {
            $endDate = !empty($wo["approvals"]["drift"]) ? new DateTime($wo["approvals"]["drift"]) : $today;
        } else {
            $endDate = $today;
        }

        $days = $endDate->diff($startDate)->days;

        if (!isset($entreprenorStats[$firma])) {
            $entreprenorStats[$firma] = ["wo_count" => 0, "days" => 0];
        }
        $entreprenorStats[$firma]["wo_count"] += 1;
        $entreprenorStats[$firma]["days"] += $days;
    }
}

// --- OPGAVEANSVARLIGE STATISTIK ---
$ansvarligStats = [];
foreach ($woData as $wo) {
    if (!empty($wo["jobansvarlig"]) && isset($wo["approvals"]["entreprenor"])) {
        $navn = $wo["jobansvarlig"];
        $startDate = new DateTime($wo["approvals"]["entreprenor"]);

        if ($wo["status"] === "completed") {
            $endDate = !empty($wo["approvals"]["drift"]) ? new DateTime($wo["approvals"]["drift"]) : $today;
        } else {
            $endDate = $today;
        }

        $days = $endDate->diff($startDate)->days;

        if (!isset($ansvarligStats[$navn])) {
            $ansvarligStats[$navn] = ["wo_count" => 0, "days" => 0];
        }
        $ansvarligStats[$navn]["wo_count"] += 1;
        $ansvarligStats[$navn]["days"] += $days;
    }
}

// --- TOP 3 LISTERS & HALL OF FAME ---
$sortedEntreprenorer = $entreprenorStats;
uasort($sortedEntreprenorer, fn($a, $b) => $b["wo_count"] <=> $a["wo_count"]);
$top3Entreprenorer = array_slice($sortedEntreprenorer, 0, 3, true);

$sortedAnsvarlige = $ansvarligStats;
uasort($sortedAnsvarlige, fn($a, $b) => $b["wo_count"] <=> $a["wo_count"]);
$top3Ansvarlige = array_slice($sortedAnsvarlige, 0, 3, true);

$topAnsvarlig = array_key_first($top3Ansvarlige);
$topAnsvarligData = reset($top3Ansvarlige);
$topEntreprenor = array_key_first($top3Entreprenorer);
$topEntreprenorData = reset($top3Entreprenorer);

// --- TIMELINE (AKKUMULERET) ---
$createdTimeline = [];
$completedTimeline = [];
foreach ($woData as $wo) {
    if (!empty($wo["oprettet_dato"])) {
        $date = $wo["oprettet_dato"];
        $createdTimeline[$date] = ($createdTimeline[$date] ?? 0) + 1;
    }
    if ($wo["status"] === "completed" && !empty($wo["approvals"]["entreprenor"])) {
        $date = $wo["approvals"]["entreprenor"];
        $completedTimeline[$date] = ($completedTimeline[$date] ?? 0) + 1;
    }
}
$allDates = array_unique(array_merge(array_keys($createdTimeline), array_keys($completedTimeline)));
sort($allDates);

$createdData = [];
$completedData = [];
$createdSum = 0;
$completedSum = 0;
foreach ($allDates as $date) {
    $createdSum += $createdTimeline[$date] ?? 0;
    $completedSum += $completedTimeline[$date] ?? 0;
    $createdData[] = $createdSum;
    $completedData[] = $completedSum;
}
$timelineLabels = $allDates;

// --- DATA TIL CHARTS ---
$statusLabels = ['Planlagt', 'Aktiv', 'Afsluttet'];
$statusData = [$counts['planning'], $counts['active'], $counts['completed']];
$entreprenorLabels = array_keys($entreprenorStats);
$entreprenorData = array_column($entreprenorStats, 'wo_count');
$ansvarligLabels = array_keys($ansvarligStats);
$ansvarligData = array_column($ansvarligStats, 'wo_count');

// --- TIDSREGISTRERING STATISTIK ---
$timeStats = [];
$workOrderHours = [];
try {
    // Get total hours per work order
    $hoursByWO = $db->fetchAll("
        SELECT wo.id, wo.work_order_no, wo.description, wo.entreprenor_firma,
               COALESCE(SUM(te.hours), 0) as total_hours,
               COUNT(DISTINCT te.user_id) as unique_users
        FROM work_orders wo
        LEFT JOIN time_entries te ON wo.id = te.work_order_id
        GROUP BY wo.id, wo.work_order_no, wo.description, wo.entreprenor_firma
        ORDER BY total_hours DESC
    ");
    
    foreach ($hoursByWO as $wo) {
        $workOrderHours[] = [
            'id' => $wo['id'],
            'work_order_no' => $wo['work_order_no'],
            'description' => $wo['description'],
            'entreprenor_firma' => $wo['entreprenor_firma'],
            'total_hours' => floatval($wo['total_hours']),
            'unique_users' => intval($wo['unique_users'])
        ];
    }
    
    // Get time stats by contractor
    $timeByContractor = $db->fetchAll("
        SELECT wo.entreprenor_firma,
               COALESCE(SUM(te.hours), 0) as total_hours,
               COUNT(DISTINCT CASE WHEN te.hours IS NOT NULL THEN te.work_order_id END) as work_orders_with_time,
               COUNT(DISTINCT te.user_id) as unique_workers
        FROM work_orders wo
        LEFT JOIN time_entries te ON wo.id = te.work_order_id
        WHERE wo.entreprenor_firma IS NOT NULL AND wo.entreprenor_firma != ''
        GROUP BY wo.entreprenor_firma
        ORDER BY total_hours DESC
    ");
    
    // Get total unique workers across all time entries
    $totalActiveWorkers = $db->fetch("
        SELECT COUNT(DISTINCT te.user_id) as total_workers
        FROM time_entries te
    ");
    $totalActiveWorkersCount = $totalActiveWorkers ? intval($totalActiveWorkers['total_workers']) : 0;
    
    foreach ($timeByContractor as $contractor) {
        $timeStats[$contractor['entreprenor_firma']] = [
            'total_hours' => floatval($contractor['total_hours']),
            'work_orders_with_time' => intval($contractor['work_orders_with_time']),
            'unique_workers' => intval($contractor['unique_workers']),
            'avg_hours_per_wo' => $contractor['work_orders_with_time'] > 0 ? 
                round(floatval($contractor['total_hours']) / intval($contractor['work_orders_with_time']), 1) : 0
        ];
    }
    
} catch (Exception $e) {
    // If database fails, use empty arrays
    $workOrderHours = [];
    $timeStats = [];
}

// Top work orders by hours
$topWorkOrdersByHours = array_slice($workOrderHours, 0, 5);

// Top contractors by hours
$sortedTimeStats = $timeStats;
uasort($sortedTimeStats, fn($a, $b) => $b['total_hours'] <=> $a['total_hours']);
$topContractorsByHours = array_slice($sortedTimeStats, 0, 3, true);

$medaljer = ["🥇", "🥈", "🥉"];
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navigation bar with hamburger menu -->
    <nav class="navbar">
        <button class="navbar-toggle" aria-label="Toggle navigation">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <div class="navbar-links">
            <a href="index.php">Forside</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_wo.php">Oversigt over arbejdstilladelser</a>
            <a href="view_sja.php">SJA Oversigt</a>
            <a href="info.php">Informationer</a>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span class="nav-user">
                Logget ind som <?php echo htmlspecialchars($_SESSION['user']); ?> (<?php echo htmlspecialchars($_SESSION['role'] ?? 'user'); ?>)
            </span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>

    <div class="container">
        <h1>📊 Dashboard</h1>

        <!-- Hall of Fame -->
        <section class="dashboard-section">
        <div class="hall-of-fame">
            <div class="hof-card ansvarlig">
                <h3>🏆 Top Opgaveansvarlig</h3>
                <p><strong><?php echo htmlspecialchars($topAnsvarlig); ?></strong></p>
                <p><?php echo $topAnsvarligData["wo_count"]; ?> arbejdstilladelser</p>
                <p>Gns.: <?php echo round($topAnsvarligData["days"] / $topAnsvarligData["wo_count"], 1); ?> dage pr. arbejdstilladelse</p>
            </div>
            <div class="hof-card entreprenor">
                <h3>🏆 Top Entreprenør</h3>
                <p><strong><?php echo htmlspecialchars($topEntreprenor); ?></strong></p>
                <p><?php echo $topEntreprenorData["wo_count"]; ?> arbejdstilladelser</p>
                <p>Gns.: <?php echo round($topEntreprenorData["days"] / $topEntreprenorData["wo_count"], 1); ?> dage pr. arbejdstilladelse</p>
            </div>
        </div>
        </section>

        <!-- Status bokse -->
        <section class="dashboard-section">
            <h2>📊 Oversigt over Arbejdstilladelser</h2>
        <div class="dashboard">
            <div class="card planlagt">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">📋</div>
                <div>Planlagte</div>
                <div style="font-size: 2rem; margin-top: 0.5rem;"><?php echo $counts["planning"]; ?></div>
            </div>
            <div class="card aktiv">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🔥</div>
                <div>Aktive</div>
                <div style="font-size: 2rem; margin-top: 0.5rem;"><?php echo $counts["active"]; ?></div>
            </div>
            <div class="card afsluttet">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">✅</div>
                <div>Afsluttede</div>
                <div style="font-size: 2rem; margin-top: 0.5rem;"><?php echo $counts["completed"]; ?></div>
            </div>
        </div>
        </section>

        <!-- Grafer -->
        <section class="dashboard-section">
            <h2>📈 Datavisualisering</h2>
        <div class="charts">
            <div class="chart-container"><h2>Status for arbejdstilladelser</h2><canvas id="statusChart"></canvas></div>
            <div class="chart-container"><h2>Arbejdstilladelser fordelt pr. entreprenør</h2><canvas id="entreprenorChart"></canvas></div>
            <div class="chart-container"><h2>Arbejdstilladelser fordelt pr. opgaveansvarlig</h2><canvas id="ansvarligChart"></canvas></div>
            <div class="chart-container" style="flex: 1 1 100%;"><h2>Udvikling over tid</h2><canvas id="timelineChart"></canvas></div>
        </div>
        </section>

        <!-- Tidsregistrering Oversigt -->
        <section class="dashboard-section">
            <h2>⏱️ Tidsregistrering Oversigt</h2>
            
            <?php if (!empty($topWorkOrdersByHours) && $topWorkOrdersByHours[0]['total_hours'] > 0): ?>
            <div class="time-overview">
                <div class="time-stats-cards">
                    <div class="time-card total-hours">
                        <div class="time-card-icon">⏰</div>
                        <div class="time-card-content">
                            <h3>Total Timer</h3>
                            <div class="time-value">
                                <?php echo array_sum(array_column($workOrderHours, 'total_hours')); ?>
                            </div>
                        </div>
                    </div>
                    <div class="time-card active-workers">
                        <div class="time-card-icon">👥</div>
                        <div class="time-card-content">
                            <h3>Aktive Arbejdere</h3>
                            <div class="time-value">
                                <?php echo $totalActiveWorkersCount; ?>
                            </div>
                        </div>
                    </div>
                    <div class="time-card projects-with-time">
                        <div class="time-card-icon">📊</div>
                        <div class="time-card-content">
                            <h3>Projekter med Timer</h3>
                            <div class="time-value">
                                <?php echo count(array_filter($workOrderHours, fn($wo) => $wo['total_hours'] > 0)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="top-work-orders-by-hours">
                    <h3>🏆 Top Arbejdstilladelser efter Timer</h3>
                    <div class="top-list">
                        <?php foreach ($topWorkOrdersByHours as $index => $wo): ?>
                            <?php if ($wo['total_hours'] > 0): ?>
                            <div class="top-item">
                                <span class="medal"><?php echo $medaljer[$index] ?? '🔸'; ?></span>
                                <div class="top-content">
                                    <div class="top-title"><?php echo htmlspecialchars($wo['work_order_no']); ?></div>
                                    <div class="top-subtitle"><?php echo htmlspecialchars($wo['entreprenor_firma']); ?></div>
                                    <div class="top-stats">
                                        <span class="hours"><?php echo $wo['total_hours']; ?> timer</span>
                                        <span class="workers"><?php echo $wo['unique_users']; ?> arbejdere</span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if (!empty($topContractorsByHours)): ?>
                <div class="top-contractors-by-hours">
                    <h3>🏢 Top Entreprenører efter Timer</h3>
                    <div class="top-list">
                        <?php $index = 0; foreach ($topContractorsByHours as $firma => $stats): ?>
                            <?php if ($stats['total_hours'] > 0): ?>
                            <div class="top-item">
                                <span class="medal"><?php echo $medaljer[$index] ?? '🔸'; ?></span>
                                <div class="top-content">
                                    <div class="top-title"><?php echo htmlspecialchars($firma); ?></div>
                                    <div class="top-stats">
                                        <span class="hours"><?php echo $stats['total_hours']; ?> timer</span>
                                        <span class="projects"><?php echo $stats['work_orders_with_time']; ?> projekter</span>
                                        <span class="avg"><?php echo $stats['avg_hours_per_wo']; ?> timer/projekt</span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php $index++; endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="no-time-data">
                <p>📋 Ingen tidsregistreringer endnu. Entreprenører kan registrere timer direkte på arbejdstilladelserne.</p>
            </div>
            <?php endif; ?>
        </section>

        <!-- Entreprenør statistik -->
        <section class="dashboard-section">
        <h2>Entreprenør statistik</h2>
        <h3>Top 3 Entreprenører</h3>
        <ol>
            <?php $i=0; foreach ($top3Entreprenorer as $firma => $data): ?>
                <li><?php echo $medaljer[$i]; ?> <?php echo htmlspecialchars($firma); ?> – <?php echo $data["wo_count"]; ?> arbejdstilladelser (gns. <?php echo round($data["days"] / $data["wo_count"], 1); ?> dage)</li>
            <?php $i++; endforeach; ?>
        </ol>
        <div class="table-wrapper">
            <table class="stats">
                <thead><tr><th>🏢 Entreprenørfirma</th><th>🔢 Antal arbejdstilladelser</th><th>📅 Samlet arbejdsdage</th><th>📊 Gns. pr. arbejdstilladelse (dage)</th></tr></thead>
                <tbody>
                <?php foreach ($entreprenorStats as $firma => $data): ?>
                    <tr><td><?php echo htmlspecialchars($firma); ?></td><td><?php echo $data["wo_count"]; ?></td><td><?php echo $data["days"]; ?></td><td><?php echo round($data["days"] / $data["wo_count"], 1); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        </section>

        <!-- Opgaveansvarlige statistik -->
        <section class="dashboard-section">
        <h2>Opgaveansvarlige statistik</h2>
        <h3>Top 3 Opgaveansvarlige</h3>
        <ol>
            <?php $i=0; foreach ($top3Ansvarlige as $navn => $data): ?>
                <li><?php echo $medaljer[$i]; ?> <?php echo htmlspecialchars($navn); ?> – <?php echo $data["wo_count"]; ?> arbejdstilladelser (gns. <?php echo round($data["days"] / $data["wo_count"], 1); ?> dage)</li>
            <?php $i++; endforeach; ?>
        </ol>
        <div class="table-wrapper">
            <table class="stats">
                <thead><tr><th>👤 Opgaveansvarlig</th><th>🔢 Antal arbejdstilladelser</th><th>📅 Samlet arbejdsdage</th><th>📊 Gns. pr. arbejdstilladelse (dage)</th></tr></thead>
                <tbody>
                <?php foreach ($ansvarligStats as $navn => $data): ?>
                    <tr><td><?php echo htmlspecialchars($navn); ?></td><td><?php echo $data["wo_count"]; ?></td><td><?php echo $data["days"]; ?></td><td><?php echo round($data["days"] / $data["wo_count"], 1); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </section>
    </div>

    <script>
        new Chart(document.getElementById('statusChart'), {
            type: 'bar',
            data: { labels: <?php echo json_encode($statusLabels); ?>,
                datasets: [{ data: <?php echo json_encode($statusData); ?>, backgroundColor: ['#3b82f6','#10b981','#6b7280'] }]},
            options: { responsive: true, plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('entreprenorChart'), {
            type: 'pie',
            data: { labels: <?php echo json_encode($entreprenorLabels); ?>,
                datasets: [{ data: <?php echo json_encode($entreprenorData); ?>, backgroundColor: ['#3b82f6','#10b981','#6b7280','#f59e0b','#06b6d4','#ef4444'] }]},
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        new Chart(document.getElementById('ansvarligChart'), {
            type: 'pie',
            data: { labels: <?php echo json_encode($ansvarligLabels); ?>,
                datasets: [{ data: <?php echo json_encode($ansvarligData); ?>, backgroundColor: ['#3b82f6','#10b981','#6b7280','#f59e0b','#06b6d4','#ef4444'] }]},
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        new Chart(document.getElementById('timelineChart'), {
            type: 'line',
            data: { labels: <?php echo json_encode($timelineLabels); ?>,
                datasets: [
                    { label: 'Akkumuleret oprettede arbejdstilladelser', data: <?php echo json_encode($createdData); ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', tension: 0.3, pointRadius: 4, fill: true, borderWidth: 3 },
                    { label: 'Akkumuleret afsluttede arbejdstilladelser', data: <?php echo json_encode($completedData); ?>, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', tension: 0.3, pointRadius: 4, fill: true, borderWidth: 3 }
                ]},
            options: { responsive: true, scales: { x: { title: { display: true, text: 'Dato' } }, y: { beginAtZero: true, title: { display: true, text: 'Akkumuleret antal arbejdstilladelser' } } }, plugins: { legend: { position: 'bottom' } } }
        });
    </script>
</body>
</html>
