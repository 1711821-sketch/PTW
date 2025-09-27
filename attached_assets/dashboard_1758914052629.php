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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .hall-of-fame { display: flex; gap: 2rem; margin: 2rem 0; justify-content: center; }
        .hof-card { flex: 1; max-width: 300px; background: #fff; border-radius: 8px; padding: 1.5rem; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .hof-card h3 { margin-bottom: 1rem; color: #0a3f6a; }
        .hof-card.ansvarlig { border-top: 6px solid #0070C0; }
        .hof-card.entreprenor { border-top: 6px solid #28a745; }
        .dashboard { display: flex; gap: 1rem; margin: 1rem 0; }
        .card { flex: 1; padding: 2rem; border-radius: 8px; color: #fff; text-align: center; font-size: 1.2rem; font-weight: bold; box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
        .planlagt { background-color: #0070C0; }
        .aktiv { background-color: #28a745; }
        .afsluttet { background-color: #6c757d; }
        .charts { display: flex; flex-wrap: wrap; gap: 2rem; margin-top: 2rem; }
        .chart-container { flex: 1; min-width: 300px; }
        table.stats { width: 100%; margin-top: 2rem; border-collapse: collapse; }
        table.stats th, table.stats td { padding: 0.6rem; border: 1px solid #ccc; text-align: left; }
        table.stats th { background-color: #f0f0f0; }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="index.php">Forside</a>
        <a href="dashboard.php">Dashboard</a>
        <div class="nav-user">
            <?php echo htmlspecialchars($_SESSION['user']); ?>
            <a href="logout.php" class="logout-link">Log ud</a>
        </div>
    </div>

    <div class="container">
        <h1>Dashboard</h1>

        <!-- Hall of Fame -->
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

        <!-- Status bokse -->
        <div class="dashboard">
            <div class="card planlagt">Planlagte: <?php echo $counts["planning"]; ?></div>
            <div class="card aktiv">Aktive: <?php echo $counts["active"]; ?></div>
            <div class="card afsluttet">Afsluttede: <?php echo $counts["completed"]; ?></div>
        </div>

        <!-- Grafer -->
        <div class="charts">
            <div class="chart-container"><h2>Status for arbejdstilladelser</h2><canvas id="statusChart"></canvas></div>
            <div class="chart-container"><h2>Arbejdstilladelser fordelt pr. entreprenør</h2><canvas id="entreprenorChart"></canvas></div>
            <div class="chart-container"><h2>Arbejdstilladelser fordelt pr. opgaveansvarlig</h2><canvas id="ansvarligChart"></canvas></div>
            <div class="chart-container" style="flex: 1 1 100%;"><h2>Udvikling over tid</h2><canvas id="timelineChart"></canvas></div>
        </div>

        <!-- Entreprenør statistik -->
        <h2>Entreprenør statistik</h2>
        <h3>Top 3 Entreprenører</h3>
        <ol>
            <?php $i=0; foreach ($top3Entreprenorer as $firma => $data): ?>
                <li><?php echo $medaljer[$i]; ?> <?php echo htmlspecialchars($firma); ?> – <?php echo $data["wo_count"]; ?> arbejdstilladelser (gns. <?php echo round($data["days"] / $data["wo_count"], 1); ?> dage)</li>
            <?php $i++; endforeach; ?>
        </ol>
        <table class="stats">
            <thead><tr><th>Entreprenørfirma</th><th>Antal arbejdstilladelser</th><th>Samlet arbejdsdage</th><th>Gns. pr. arbejdstilladelse (dage)</th></tr></thead>
            <tbody>
            <?php foreach ($entreprenorStats as $firma => $data): ?>
                <tr><td><?php echo htmlspecialchars($firma); ?></td><td><?php echo $data["wo_count"]; ?></td><td><?php echo $data["days"]; ?></td><td><?php echo round($data["days"] / $data["wo_count"], 1); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Opgaveansvarlige statistik -->
        <h2>Opgaveansvarlige statistik</h2>
        <h3>Top 3 Opgaveansvarlige</h3>
        <ol>
            <?php $i=0; foreach ($top3Ansvarlige as $navn => $data): ?>
                <li><?php echo $medaljer[$i]; ?> <?php echo htmlspecialchars($navn); ?> – <?php echo $data["wo_count"]; ?> arbejdstilladelser (gns. <?php echo round($data["days"] / $data["wo_count"], 1); ?> dage)</li>
            <?php $i++; endforeach; ?>
        </ol>
        <table class="stats">
            <thead><tr><th>Opgaveansvarlig</th><th>Antal arbejdstilladelser</th><th>Samlet arbejdsdage</th><th>Gns. pr. arbejdstilladelse (dage)</th></tr></thead>
            <tbody>
            <?php foreach ($ansvarligStats as $navn => $data): ?>
                <tr><td><?php echo htmlspecialchars($navn); ?></td><td><?php echo $data["wo_count"]; ?></td><td><?php echo $data["days"]; ?></td><td><?php echo round($data["days"] / $data["wo_count"], 1); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        new Chart(document.getElementById('statusChart'), {
            type: 'bar',
            data: { labels: <?php echo json_encode($statusLabels); ?>,
                datasets: [{ data: <?php echo json_encode($statusData); ?>, backgroundColor: ['#0070C0','#28a745','#6c757d'] }]},
            options: { responsive: true, plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('entreprenorChart'), {
            type: 'pie',
            data: { labels: <?php echo json_encode($entreprenorLabels); ?>,
                datasets: [{ data: <?php echo json_encode($entreprenorData); ?>, backgroundColor: ['#0070C0','#28a745','#6c757d','#ffc107','#17a2b8','#dc3545'] }]},
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        new Chart(document.getElementById('ansvarligChart'), {
            type: 'pie',
            data: { labels: <?php echo json_encode($ansvarligLabels); ?>,
                datasets: [{ data: <?php echo json_encode($ansvarligData); ?>, backgroundColor: ['#0070C0','#28a745','#6c757d','#ffc107','#17a2b8','#dc3545'] }]},
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        new Chart(document.getElementById('timelineChart'), {
            type: 'line',
            data: { labels: <?php echo json_encode($timelineLabels); ?>,
                datasets: [
                    { label: 'Akkumuleret oprettede arbejdstilladelser', data: <?php echo json_encode($createdData); ?>, borderColor: '#0070C0', backgroundColor: '#0070C0', tension: 0.2, pointRadius: 3, fill: false },
                    { label: 'Akkumuleret afsluttede arbejdstilladelser', data: <?php echo json_encode($completedData); ?>, borderColor: '#28a745', backgroundColor: '#28a745', tension: 0.2, pointRadius: 3, fill: false }
                ]},
            options: { responsive: true, scales: { x: { title: { display: true, text: 'Dato' } }, y: { beginAtZero: true, title: { display: true, text: 'Akkumuleret antal arbejdstilladelser' } } }, plugins: { legend: { position: 'bottom' } } }
        });
    </script>
</body>
</html>
