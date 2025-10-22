<?php
// SMS Administration Page
// This page allows administrators to configure SMS notification settings
// and test SMS functionality

session_start();
require_once 'auth_check.php';

// Only allow admin users to access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'sms_config.php';

$message = '';
$messageType = '';

// Handle form submissions
if ($_POST) {
    $smsNotifier = new SMSNotifier();
    
    if (isset($_POST['add_recipient'])) {
        $phoneNumber = trim($_POST['phone_number']);
        if (!empty($phoneNumber)) {
            if (preg_match('/^\+45\d{8}$/', $phoneNumber)) {
                $smsNotifier->addRecipient($phoneNumber);
                $message = "Modtager tilføjet: $phoneNumber";
                $messageType = 'success';
            } else {
                $message = "Ugyldigt telefonnummer. Kun danske numre accepteres. Brug format: +45 efterfulgt af 8 cifre (eks: +4512345678)";
                $messageType = 'error';
            }
        }
    }
    
    if (isset($_POST['remove_recipient'])) {
        $phoneNumber = trim($_POST['remove_phone']);
        $smsNotifier->removeRecipient($phoneNumber);
        $message = "Modtager fjernet: $phoneNumber";
        $messageType = 'success';
    }
    
    if (isset($_POST['test_sms'])) {
        $testPhone = trim($_POST['test_phone_number']);
        if (!empty($testPhone)) {
            $result = $smsNotifier->testSMS($testPhone);
            if ($result['success']) {
                $message = "Test SMS sendt med succes til $testPhone";
                $messageType = 'success';
            } else {
                $message = "Test SMS fejlede: " . $result['message'];
                $messageType = 'error';
            }
        }
    }
}

$smsNotifier = new SMSNotifier();
$recipients = $smsNotifier->getRecipients();
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>SMS Notifikationer - Administration</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-section {
            background: white;
            padding: 2rem;
            margin: 1rem 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .recipients-list {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        
        .recipient-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .recipient-item:last-child {
            border-bottom: none;
        }
        
        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-remove:hover {
            background: #c82333;
        }
        
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .config-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-mobile">
            <button class="navbar-toggle" aria-label="Toggle navigation">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
            <span class="nav-user nav-user-mobile"><?php echo htmlspecialchars($_SESSION['user']); ?></span>
        </div>
        <div class="navbar-links">
            <a href="view_wo.php">PTW-oversigt</a>
            <a href="admin.php">Admin</a>
            <a href="sms_admin.php">SMS Notifikationer</a>
            <span class="nav-user nav-user-desktop">Logget ind som <?php echo htmlspecialchars($_SESSION['user']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>

    <div class="container">
        <h1>SMS Notifikationer - Administration</h1>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="config-info">
            <h3>📱 Konfiguration</h3>
            <p>SMS notifikationer sendes automatisk når nye PTWr oprettes.</p>
            <p><strong>Krav:</strong> Twilio API nøgler skal være konfigureret som miljøvariabler:</p>
            <ul>
                <li>TWILIO_ACCOUNT_SID</li>
                <li>TWILIO_AUTH_TOKEN</li>
                <li>TWILIO_PHONE_NUMBER</li>
            </ul>
        </div>
        
        <!-- Add Recipient Section -->
        <div class="form-section">
            <h2>Tilføj SMS Modtager</h2>
            <form method="post">
                <div class="form-group">
                    <label for="phone_number">Dansk telefonnummer:</label>
                    <input type="text" id="phone_number" name="phone_number" 
                           placeholder="+4524664369" required
                           pattern="^\+45\d{8}$"
                           title="Kun danske telefonnumre accepteres. Format: +45 efterfulgt af 8 cifre">
                </div>
                <button type="submit" name="add_recipient" class="button-lg">Tilføj Modtager</button>
            </form>
        </div>
        
        <!-- Current Recipients Section -->
        <div class="form-section">
            <h2>Nuværende SMS Modtagere</h2>
            <?php if (empty($recipients)): ?>
                <p>Ingen SMS modtagere konfigureret.</p>
            <?php else: ?>
                <div class="recipients-list">
                    <?php foreach ($recipients as $recipient): ?>
                        <div class="recipient-item">
                            <span><?php echo htmlspecialchars($recipient); ?></span>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="remove_phone" value="<?php echo htmlspecialchars($recipient); ?>">
                                <button type="submit" name="remove_recipient" class="btn-remove" 
                                        onclick="return confirm('Er du sikker på at du vil fjerne denne modtager?')">
                                    Fjern
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Test SMS Section -->
        <div class="form-section">
            <h2>Test SMS</h2>
            <form method="post">
                <div class="form-group">
                    <label for="test_phone_number">Test telefonnummer:</label>
                    <input type="text" id="test_phone_number" name="test_phone_number" 
                           placeholder="+4524664369" required
                           pattern="^\+45\d{8}$">
                </div>
                <button type="submit" name="test_sms" class="button-lg">Send Test SMS</button>
            </form>
        </div>
        
        <!-- Configuration Status -->
        <div class="form-section">
            <h2>Konfigurationsstatus</h2>
            <table class="table">
                <tr>
                    <td>TWILIO_ACCOUNT_SID</td>
                    <td><?php echo !empty($_ENV['TWILIO_ACCOUNT_SID']) ? '✅ Konfigureret' : '❌ Mangler'; ?></td>
                </tr>
                <tr>
                    <td>TWILIO_AUTH_TOKEN</td>
                    <td><?php echo !empty($_ENV['TWILIO_AUTH_TOKEN']) ? '✅ Konfigureret' : '❌ Mangler'; ?></td>
                </tr>
                <tr>
                    <td>TWILIO_PHONE_NUMBER</td>
                    <td><?php echo !empty($_ENV['TWILIO_PHONE_NUMBER']) ? '✅ Konfigureret' : '❌ Mangler'; ?></td>
                </tr>
                <tr>
                    <td>SMS Modtagere</td>
                    <td><?php echo count($recipients) . ' konfigureret'; ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>