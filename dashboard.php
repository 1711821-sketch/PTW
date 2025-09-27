<?php
session_start();
// Ensure the user is logged in.  The session key 'user' is set in login.php.
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Indlæs data
$woData = json_decode(file_get_contents("wo_data.json"), true);

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
