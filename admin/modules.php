<?php
/**
 * Module Management Admin Interface
 * 
 * This page allows administrators to enable/disable system modules.
 * Only accessible to users with admin role.
 */

session_start();
require_once __DIR__ . '/../auth_check.php';

// Admin-only access control
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../view_wo.php');
    exit();
}

// Load current module configuration
$configPath = __DIR__ . '/../config/modules.php';
$modules = include $configPath;

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update module status based on checkboxes
    $modules['ptw'] = isset($_POST['ptw']);
    $modules['sja'] = isset($_POST['sja']);
    $modules['tidsregistrering'] = isset($_POST['tidsregistrering']);
    
    // Generate PHP array code
    $configContent = "<?php\n";
    $configContent .= "/**\n";
    $configContent .= " * Module Configuration\n";
    $configContent .= " * \n";
    $configContent .= " * This file controls which modules are active in the EdinLink system.\n";
    $configContent .= " * Modules can be enabled/disabled without deleting code or data.\n";
    $configContent .= " * \n";
    $configContent .= " * Modules:\n";
    $configContent .= " * - ptw: Permit To Work (Arbejdstilladelse)\n";
    $configContent .= " * - sja: Safety Job Analysis (Sikker Job Analyse)\n";
    $configContent .= " * - tidsregistrering: Time Registration (Tidsregistrering)\n";
    $configContent .= " */\n\n";
    $configContent .= "return " . var_export($modules, true) . ";\n";
    
    // Write to file
    if (file_put_contents($configPath, $configContent)) {
        $message = 'Modulindstillingerne er blevet gemt.';
        $messageType = 'success';
        
        // Log the change
        error_log("Module configuration updated by " . $_SESSION['user'] . ": PTW=" . ($modules['ptw'] ? 'aktiv' : 'inaktiv') . 
                  ", SJA=" . ($modules['sja'] ? 'aktiv' : 'inaktiv') . 
                  ", Tidsregistrering=" . ($modules['tidsregistrering'] ? 'aktiv' : 'inaktiv'));
    } else {
        $message = 'Fejl: Kunne ikke gemme indstillinger. Kontroller filrettigheder.';
        $messageType = 'error';
        error_log("Failed to save module configuration: " . error_get_last()['message']);
    }
    
    // Reload configuration
    $modules = include $configPath;
}

$username = $_SESSION['user'];
$role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Modulstyring - Admin</title>
    <?php include __DIR__ . '/../pwa-head.php'; ?>
    <link rel="stylesheet" href="../style.css">
    <script src="../navigation.js"></script>
    <style>
        .module-admin-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .module-card {
            background: #f8f9fa;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: border-color 0.2s;
        }
        
        .module-card:hover {
            border-color: #cbd5e0;
        }
        
        .module-card.active {
            border-color: #4ade80;
            background: #f0fdf4;
        }
        
        .module-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .module-checkbox {
            width: 24px;
            height: 24px;
            margin-right: 1rem;
            cursor: pointer;
        }
        
        .module-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .module-description {
            color: #64748b;
            margin: 0.5rem 0 0 2.5rem;
            line-height: 1.5;
        }
        
        .module-status {
            display: inline-block;
            margin-left: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e2e8f0;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #1e40af;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1e3a8a;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .info-box {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 4px;
        }
        
        .info-box p {
            margin: 0;
            color: #1e3a8a;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navigation.php'; ?>
    
    <div class="container">
        <div class="module-admin-container">
            <h1>🔧 Modulstyring</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <p>
                    <strong>📌 Information:</strong> Her kan du aktivere eller deaktivere systemets moduler. 
                    Når et modul er deaktiveret, skjules det fra menuer og brugere får en besked, hvis de 
                    prøver at åbne det direkte. Ingen data slettes ved deaktivering.
                </p>
            </div>
            
            <form method="POST">
                <div class="module-card <?php echo $modules['ptw'] ? 'active' : ''; ?>">
                    <div class="module-header">
                        <input type="checkbox" 
                               class="module-checkbox" 
                               id="ptw" 
                               name="ptw" 
                               <?php echo $modules['ptw'] ? 'checked' : ''; ?>>
                        <label for="ptw" class="module-title">
                            PTW (Permit To Work)
                            <span class="module-status <?php echo $modules['ptw'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $modules['ptw'] ? '✓ Aktiv' : '✕ Inaktiv'; ?>
                            </span>
                        </label>
                    </div>
                    <div class="module-description">
                        Arbejdstilladelse-systemet inkluderer oprettelse, godkendelse og håndtering af PTW'er, 
                        kortoversigt og godkendelsesworkflow. Dette er kernemodullet i systemet.
                    </div>
                </div>
                
                <div class="module-card <?php echo $modules['sja'] ? 'active' : ''; ?>">
                    <div class="module-header">
                        <input type="checkbox" 
                               class="module-checkbox" 
                               id="sja" 
                               name="sja" 
                               <?php echo $modules['sja'] ? 'checked' : ''; ?>>
                        <label for="sja" class="module-title">
                            SJA (Sikker Job Analyse)
                            <span class="module-status <?php echo $modules['sja'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $modules['sja'] ? '✓ Aktiv' : '✕ Inaktiv'; ?>
                            </span>
                        </label>
                    </div>
                    <div class="module-description">
                        Sikkerhedsanalyse-system til risikovurdering, tilladelser, værnemidler og deltagerstyring. 
                        Inkluderer versionskontrol og sammenligning af SJA-versioner.
                    </div>
                </div>
                
                <div class="module-card <?php echo $modules['tidsregistrering'] ? 'active' : ''; ?>">
                    <div class="module-header">
                        <input type="checkbox" 
                               class="module-checkbox" 
                               id="tidsregistrering" 
                               name="tidsregistrering" 
                               <?php echo $modules['tidsregistrering'] ? 'checked' : ''; ?>>
                        <label for="tidsregistrering" class="module-title">
                            Tidsregistrering
                            <span class="module-status <?php echo $modules['tidsregistrering'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $modules['tidsregistrering'] ? '✓ Aktiv' : '✕ Inaktiv'; ?>
                            </span>
                        </label>
                    </div>
                    <div class="module-description">
                        Registrering og overblik over anvendte timer pr. arbejdstilladelse, bruger og entreprenør. 
                        Inkluderer statistik og rapportering.
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 Gem ændringer</button>
                    <a href="../admin.php" class="btn btn-secondary">← Tilbage til Admin</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
