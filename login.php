<?php
// Login page for SJA system
// Loads user credentials from a JSON file and authenticates users.
// If no users file is present, a default admin account will be created.

session_start();

// Path to users JSON data
$users_file = __DIR__ . '/users.json';

// Ensure the users file exists with a default admin user
if (!file_exists($users_file)) {
    $default_users = [
        [
            'username'      => 'admin',
            // password_hash for 'Test1234!' generated previously via password_hash()
            'password_hash' => '$2y$12$846ZzhLP2DCMa6IoqxPOKOwHm0Zt706jb322fHkQlokCANnBmMTxK',
            'role'          => 'admin'
        ]
    ];
    file_put_contents($users_file, json_encode($default_users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Load existing users
$users = json_decode(file_get_contents($users_file), true);
if (!is_array($users)) {
    $users = [];
}

// Attempt login on POST
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $found    = false;
    foreach ($users as $u) {
        if ($u['username'] === $username && password_verify($password, $u['password_hash'])) {
            // Valid credentials: Check if the account is approved.
            // Existing users created before the introduction of the approval
            // system may not have an 'approved' key; treat them as approved.
            $isApproved = true;
            if (array_key_exists('approved', $u)) {
                $isApproved = (bool)$u['approved'];
            }
            if (!$isApproved) {
                // Deny login if the account is not yet approved by an admin
                $error = 'Din konto skal godkendes af en administrator, før du kan logge ind.';
                // Do not continue searching; break out of loop.
                $found    = true;
                break;
            }
            // Approved credentials: store username and role in session
            $_SESSION['user'] = $u['username'];
            $_SESSION['role'] = $u['role'];
            // When a user has the role "entreprenor", also store the contractor
            // company associated with their account in the session.  This value
            // will later be used to restrict which work orders they can view.
            if ($u['role'] === 'entreprenor') {
                // Some legacy user records may not include an entreprenor_firma key,
                // so we guard against undefined values.  If not set, the
                // entrepreneur will see no work orders, which is safer than
                // accidentally exposing data from other firms.
                $_SESSION['entreprenor_firma'] = $u['entreprenor_firma'] ?? null;
            }
            header('Location: index.php');
            exit();
        }
    }
    // If no matching credentials were found and no other error was set,
    // display the generic error message.  Without this check, the generic
    // message would overwrite the approval-related message set above.
    if (!$found && $error === '') {
        $error = 'Forkert brugernavn eller adgangskode';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Login - Arbejdstilladelsessystem</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
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
                <p class="login-subtitle">Arbejdstilladelsessystem</p>
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