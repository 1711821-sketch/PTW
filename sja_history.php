<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Load module configuration and check if SJA module is enabled
$modules = include __DIR__ . '/config/modules.php';
if (!$modules['sja']) {
    echo '<!DOCTYPE html><html lang="da"><head><meta charset="UTF-8"><title>SJA - Deaktiveret</title>';
    echo '<link rel="stylesheet" href="style.css"></head><body>';
    echo '<div class="container" style="padding:40px 20px; text-align:center;">';
    echo '<h2>Sikker Job Analyse (SJA) er midlertidigt deaktiveret</h2>';
    echo '<p style="color:#555; margin: 20px 0;">Denne funktion vil blive aktiveret igen, når PTW er fuldt implementeret.</p>';
    echo '<p><a href="view_wo.php" style="color:#1e40af;">← Gå til PTW-oversigt</a></p>';
    echo '</div></body></html>';
    exit;
}

require_once 'database.php';

$id = $_GET['id'] ?? '';
$entry = null;
$history = [];

try {
    $db = Database::getInstance();
    $dbEntry = $db->fetch("SELECT * FROM sja_entries WHERE id = ?", [$id]);
    
    if ($dbEntry) {
        $entry = $dbEntry;
        $history = json_decode($dbEntry['history'] ?? '[]', true);
        if (!is_array($history)) {
            $history = [];
        }
    }
} catch (Exception $e) {
    error_log("Error loading SJA history: " . $e->getMessage());
}

if (!$entry) {
    echo '<p>SJA ikke fundet.</p>';
    echo '<p><a href="view_sja.php">Tilbage til liste</a></p>';
    exit();
}

$opgave = json_decode($entry['basic_info'] ?? '{}', true)['opgave'] ?? 'SJA';
$current_version = $entry['version'] ?? 1;
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>SJA Versionshistorik - <?php echo htmlspecialchars($opgave); ?></title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
    <style>
        .version-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .version-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .version-badge {
            background: #4f46e5;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-weight: 600;
        }
        .current-badge {
            background: #059669;
        }
        .version-info {
            color: #64748b;
            font-size: 0.875rem;
        }
        .version-actions {
            margin-top: 0.75rem;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #4f46e5;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 0.5rem;
            font-size: 0.875rem;
        }
        .btn-secondary {
            background: #6b7280;
        }
        .compare-section {
            margin-top: 2rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <h1>📜 Versionshistorik for SJA</h1>
        <h2><?php echo htmlspecialchars($opgave); ?></h2>

        <div class="version-card">
            <div class="version-header">
                <span class="version-badge current-badge">Version <?php echo $current_version; ?> (Aktuel)</span>
                <div class="version-info">
                    Opdateret: <?php echo htmlspecialchars($entry['updated_at'] ?? $entry['created_at']); ?>
                    <br>Af: <?php echo htmlspecialchars($entry['modified_by'] ?? $entry['created_by'] ?? 'Ukendt'); ?>
                </div>
            </div>
            <div class="version-actions">
                <a href="print_sja.php?id=<?php echo urlencode($id); ?>" class="btn" target="_blank">👁️ Se nuværende version</a>
                <a href="create_sja.php?id=<?php echo urlencode($id); ?>" class="btn btn-secondary">✏️ Rediger</a>
            </div>
        </div>

        <?php if (!empty($history)): ?>
            <h3>📋 Tidligere versioner</h3>
            <?php 
            // Reverse to show newest first
            $reversed_history = array_reverse($history);
            foreach ($reversed_history as $idx => $hist): 
                $version_num = $hist['version'] ?? ($current_version - count($history) + $idx);
            ?>
                <div class="version-card">
                    <div class="version-header">
                        <span class="version-badge">Version <?php echo $version_num; ?></span>
                        <div class="version-info">
                            <?php echo htmlspecialchars($hist['timestamp'] ?? 'Ukendt tid'); ?>
                            <br>Af: <?php echo htmlspecialchars($hist['modified_by'] ?? 'Ukendt'); ?>
                        </div>
                    </div>
                    <div class="version-actions">
                        <a href="sja_view_version.php?id=<?php echo urlencode($id); ?>&version=<?php echo $version_num; ?>" class="btn" target="_blank">👁️ Se denne version</a>
                        <a href="sja_compare.php?id=<?php echo urlencode($id); ?>&v1=<?php echo $version_num; ?>&v2=<?php echo $current_version; ?>" class="btn btn-secondary">🔍 Sammenlign med nuværende</a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (count($history) >= 2): ?>
                <div class="compare-section">
                    <h3>🔄 Sammenlign to versioner</h3>
                    <form action="sja_compare.php" method="get">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                        <label>
                            Version 1:
                            <select name="v1">
                                <option value="<?php echo $current_version; ?>">Version <?php echo $current_version; ?> (Aktuel)</option>
                                <?php foreach (array_reverse($history) as $hist): 
                                    $v = $hist['version'] ?? 1;
                                ?>
                                    <option value="<?php echo $v; ?>">Version <?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Version 2:
                            <select name="v2">
                                <option value="<?php echo $current_version; ?>">Version <?php echo $current_version; ?> (Aktuel)</option>
                                <?php foreach (array_reverse($history) as $hist): 
                                    $v = $hist['version'] ?? 1;
                                ?>
                                    <option value="<?php echo $v; ?>">Version <?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit" class="btn">Sammenlign</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p>Ingen tidligere versioner findes. Dette er den første version af denne SJA.</p>
        <?php endif; ?>

        <p style="margin-top: 2rem;">
            <a href="view_sja.php">← Tilbage til SJA oversigt</a>
        </p>
    </div>
</body>
</html>
