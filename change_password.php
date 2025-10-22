<?php
// Change Password page - Forces users to change password on first login
// Only accessible when user is logged in and has must_change_password flag set

session_start();

// CSRF Token Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once 'database.php';
$db = Database::getInstance();

// Check if user actually needs to change password
$user = $db->fetch("SELECT must_change_password FROM users WHERE username = ?", [$_SESSION['user']]);
if (!$user || !isset($user['must_change_password']) || (bool)$user['must_change_password'] === false) {
    // User doesn't need to change password, redirect to main page
    header('Location: view_wo.php');
    exit();
}

$csrf_token = generateCSRFToken();
$error = '';
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ugyldig forespørgsel. Prøv igen.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($new_password)) {
            $error = 'Nyt password er påkrævet';
        } elseif (strlen($new_password) < 4) {
            $error = 'Password skal være mindst 4 tegn';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords matcher ikke';
        } else {
            try {
                // Verify current password
                $user_data = $db->fetch("SELECT password_hash FROM users WHERE username = ?", [$_SESSION['user']]);
                
                if ($user_data && password_verify($current_password, $user_data['password_hash'])) {
                    // Update password and remove must_change_password flag
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $db->execute(
                        "UPDATE users SET password_hash = ?, must_change_password = false WHERE username = ?",
                        [$new_hash, $_SESSION['user']]
                    );
                    
                    $success = 'Password ændret succesfuldt! Du omdirigeres til systemet...';
                    // Redirect after 2 seconds
                    header("Refresh: 2; url=view_wo.php");
                } else {
                    $error = 'Nuværende password er forkert';
                }
            } catch (Exception $e) {
                error_log('Database error changing password: ' . $e->getMessage());
                $error = 'Der opstod en fejl. Prøv igen senere.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Skift Password - PTW System</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <style>
        .password-container {
            max-width: 500px;
            margin: 4rem auto;
            padding: 0;
        }
        
        .password-card {
            background: var(--background-primary);
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }
        
        .password-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        }
        
        .password-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .password-header h1 {
            margin: 0 0 1rem 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .password-header .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .info-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .info-box strong {
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-dark);
            padding: 0.875rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--secondary-color);
            color: var(--secondary-dark);
            padding: 0.875rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--background-primary);
            color: var(--text-primary);
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }
        
        .button {
            width: 100%;
            padding: 0.875rem 1.25rem;
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        .button:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .button:active {
            transform: translateY(0);
        }
        
        .password-requirements {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="password-container">
        <div class="password-card">
            <div class="password-header">
                <div class="icon">🔐</div>
                <h1>Skift Password</h1>
            </div>
            
            <div class="info-box">
                <strong>⚠️ Påkrævet handling</strong>
                Du skal skifte dit midlertidige password for at fortsætte. Vælg et nyt, sikkert password.
            </div>
            
            <?php if ($error): ?>
                <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-group">
                        <label for="current_password">Nuværende Password:</label>
                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Nyt Password:</label>
                        <input type="password" id="new_password" name="new_password" required minlength="4" autocomplete="new-password">
                        <div class="password-requirements">Minimum 4 tegn</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Bekræft Nyt Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="4" autocomplete="new-password">
                    </div>
                    
                    <button type="submit" class="button">🔒 Skift Password</button>
                </form>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin-top: 1.5rem;">
            <p style="color: var(--text-secondary); font-size: 0.9rem;">
                Logget ind som <strong><?php echo htmlspecialchars($_SESSION['user']); ?></strong> |
                <a href="logout.php" style="color: var(--primary-color); text-decoration: none;">Log ud</a>
            </p>
        </div>
    </div>
</body>
</html>
