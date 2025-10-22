<?php
// EMERGENCY ADMIN CREATION SCRIPT
// Visit this page ONCE to create an admin user in production database
// DELETE THIS FILE AFTER USE for security!

require_once 'database.php';

$success = false;
$message = '';

// Check if admin already exists
$db = Database::getInstance();
$existing = $db->fetch("SELECT username FROM users WHERE username = ?", ['testadmin']);

if ($existing) {
    $message = '❌ Admin bruger "testadmin" findes allerede!';
} else {
    try {
        // Create admin user
        $password_hash = password_hash('Admin2025!', PASSWORD_BCRYPT);
        
        $db->execute(
            "INSERT INTO users (username, password_hash, role, approved) VALUES (?, ?, ?, ?)",
            ['testadmin', $password_hash, 'admin', true]
        );
        
        $success = true;
        $message = '✅ Admin bruger oprettet!<br><br>
                    <strong>Brugernavn:</strong> testadmin<br>
                    <strong>Password:</strong> Admin2025!<br><br>
                    ⚠️ <strong>VIGTIGT:</strong> Slet denne fil (create_emergency_admin.php) NU for sikkerhed!<br><br>
                    <a href="login.php">Gå til login</a>';
    } catch (Exception $e) {
        $message = '❌ Fejl: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Opret Emergency Admin</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success {
            color: #10b981;
        }
        .error {
            color: #ef4444;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #1e40af;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
        a:hover {
            background: #1e3a8a;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1><?php echo $success ? '✅' : '⚠️'; ?> Emergency Admin</h1>
        <p class="<?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </p>
    </div>
</body>
</html>
