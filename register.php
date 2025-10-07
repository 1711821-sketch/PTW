<?php
// Start PHP session for potential future use
session_start();

// Database connection for user storage and contractor list
require_once 'database.php';

// Extract unique contractor firm names from the database.  Contractors are
// specified by the "entreprenor_firma" column in work_orders table.
$contractors = [];
try {
    $db = Database::getInstance();
    $result = $db->fetchAll(
        "SELECT DISTINCT entreprenor_firma FROM work_orders 
         WHERE entreprenor_firma IS NOT NULL AND entreprenor_firma != '' 
         ORDER BY entreprenor_firma ASC"
    );
    foreach ($result as $row) {
        $contractors[] = $row['entreprenor_firma'];
    }
} catch (Exception $e) {
    // If database access fails, contractors array remains empty
    error_log('Database error in register.php: ' . $e->getMessage());
}

// Define which roles are allowed.  The role names used here should match
// those in other parts of the application.  "entreprenor" (without
// diacritics) is used internally even though the display label contains
// the Danish character "ø".
$allowed_roles = ['admin', 'opgaveansvarlig', 'drift', 'entreprenor'];

// Initialize error message variable
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim input values to avoid leading/trailing spaces
    $username          = trim($_POST['username'] ?? '');
    $password          = $_POST['password'] ?? '';
    $role              = $_POST['role'] ?? '';
    $selected_contractor = $_POST['contractor'] ?? '';

    // Validate inputs
    if ($username === '' || $password === '') {
        $error = 'Brugernavn og adgangskode er påkrævet.';
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error = 'Ugyldig rolle valgt.';
    } else {
        // Check for duplicate username in PostgreSQL database
        try {
            $db = Database::getInstance();
            $existing_user = $db->fetch("SELECT username FROM users WHERE LOWER(username) = LOWER(?)", [$username]);
            if ($existing_user) {
                $error = 'Brugernavnet eksisterer allerede.';
            }
        } catch (Exception $e) {
            error_log('Database error checking duplicate username: ' . $e->getMessage());
            $error = 'Der opstod en fejl. Prøv igen senere.';
        }
    }

    // If no errors so far, register the user in PostgreSQL database
    if ($error === '') {
        try {
            $db = Database::getInstance();
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Prepare SQL based on role (entreprenor needs firma column)
            if ($role === 'entreprenor') {
                $db->execute(
                    "INSERT INTO users (username, password_hash, role, approved, entreprenor_firma) 
                     VALUES (?, ?, ?, false, ?)",
                    [$username, $password_hash, $role, $selected_contractor]
                );
            } else {
                $db->execute(
                    "INSERT INTO users (username, password_hash, role, approved) 
                     VALUES (?, ?, ?, false)",
                    [$username, $password_hash, $role]
                );
            }
            
            // Redirect to login page after successful registration.
            // The user will remain unable to log in until an administrator approves the account.
            header('Location: login.php');
            exit;
        } catch (Exception $e) {
            error_log('Database error during user registration: ' . $e->getMessage());
            $error = 'Der opstod en fejl under registrering. Prøv igen senere.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opret ny bruger</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Arbejdstilladelse">
    <link rel="apple-touch-icon" href="attached_assets/apple-touch-icon.png">
    <meta name="theme-color" content="#1e40af">

    <!-- Service Worker Registration -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js')
                .then(reg => console.log('Service Worker registreret'))
                .catch(err => console.error('Service Worker fejl:', err));
        });
    }
    </script>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            background: #fff;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            margin-top: 0;
            font-size: 1.5em;
            text-align: center;
        }
        label {
            display: block;
            margin-bottom: 10px;
        }
        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007BFF;
            border: none;
            color: white;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Opret ny bruger</h1>
        <?php if ($error !== ''): ?>
            <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form method="post">
            <label>
                Brugernavn (Din navn og efternavn)
                <input type="text" name="username" required>
            </label>
            <label>
                Adgangskode
                <input type="password" name="password" required>
            </label>
            <label>
                Vælg rolle
                <select name="role" id="roleSelect" onchange="toggleContractorField(this.value)" required>
                    <option value="">-- Vælg rolle --</option>
                    <option value="admin">Administrator</option>
                    <option value="opgaveansvarlig">Opgaveansvarlig</option>
                    <option value="drift">Drift</option>
                    <option value="entreprenor">Entreprenør</option>
                </select>
            </label>
            <div id="contractorDiv" style="display: none;">
                <label>
                    Entreprenør firma
                    <select name="contractor">
                        <?php foreach ($contractors as $firma): ?>
                            <option value="<?php echo htmlspecialchars($firma, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($firma, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <button type="submit" class="button button-primary button-lg" style="width: 100%; margin-top: 1rem;">👤 Registrer bruger</button>
        </form>
        <p style="text-align: center;"><a href="login.php">Tilbage til login</a></p>
    </div>
    <script>
    // Show or hide the contractor dropdown depending on the selected role
    function toggleContractorField(role) {
        var div = document.getElementById('contractorDiv');
        if (role === 'entreprenor') {
            div.style.display = 'block';
        } else {
            div.style.display = 'none';
        }
    }
    </script>
</body>
</html>