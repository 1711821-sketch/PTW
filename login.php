<?php
// Login page for SJA system
// Loads user credentials from PostgreSQL database and authenticates users.

session_start();
require_once 'database.php';

// Initialize database connection
$db = Database::getInstance();

// Attempt login on POST
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    try {
        // Look up user in PostgreSQL database
        $user = $db->fetch("SELECT * FROM users WHERE username = ?", [$username]);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Valid credentials: Check if the account is approved
            $isApproved = (bool)$user['approved'];
            
            if (!$isApproved) {
                // Deny login if the account is not yet approved by an admin
                $error = 'Din konto skal godkendes af en administrator, før du kan logge ind.';
            } else {
                // Approved credentials: store username and role in session
                $_SESSION['user'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // When a user has the role "entreprenor", also store the contractor
                // company associated with their account in the session.
                if ($user['role'] === 'entreprenor') {
                    $_SESSION['entreprenor_firma'] = $user['entreprenor_firma'] ?? null;
                }
                header('Location: index.php');
                exit();
            }
        } else {
            $error = 'Forkert brugernavn eller adgangskode';
        }
    } catch (Exception $e) {
        error_log("Database error during login: " . $e->getMessage());
        $error = 'Der opstod en fejl under login. Prøv igen senere.';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Login - Arbejdstilladelsessystem</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Work Order og Safety Job Analysis system til sikker arbejdsstyring">
    <meta name="theme-color" content="#1e40af">
    <link rel="manifest" href="/manifest.json">
    
    <!-- Apple iOS Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Arbejdstilladelse">
    <link rel="apple-touch-icon" href="/attached_assets/apple-touch-icon.png">
    
    <!-- MS Tiles -->
    <meta name="msapplication-TileColor" content="#1e40af">
    
    <link rel="stylesheet" href="style.css">
    
    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js')
                    .then(reg => console.log('Service Worker registreret'))
                    .catch(err => console.log('Service Worker fejl:', err));
            });
        }
    </script>
    <style>
        /* Login-specific styling */
        .login-container {
            max-width: 400px;
            margin: 4rem auto;
            padding: 0;
            background: transparent;
            border-radius: 0;
            box-shadow: none;
        }
        
        .login-card {
            background: var(--background-primary);
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0;
        }
        
        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-dark);
            padding: 0.875rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .login-form {
            margin-bottom: 1.5rem;
        }
        
        .login-form button {
            width: 100%;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 0.95rem;
            margin-top: 0.5rem;
        }
        
        .register-link {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
        }
        
        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .register-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>🔐 Login</h1>
                <p class="login-subtitle">Arbejdsflow for Interterminals</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="post" action="" class="login-form">
                <div class="form-group">
                    <label for="username">Brugernavn (Din navn og efternavn)</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Adgangskode</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Log ind</button>
            </form>
            
            <div class="register-link">
                <a href="register.php">Opret ny bruger</a>
            </div>
        </div>
    </div>
</body>
</html>