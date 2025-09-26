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
    <title>Login til SJA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
        .container { max-width: 400px; margin: 80px auto; padding: 20px; background: #fff;
                     border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; font-size: 1.4em; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin: 8px 0;
                                                         border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #0070C0; color: #fff; padding: 10px 15px;
                 border: none; border-radius: 4px; cursor: pointer; width: 100%; }
        .error { color: red; margin-top: 10px; }
        .register-link { margin-top: 1rem; display: block; text-align: center; }
        a { color: #0070C0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Login</h1>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <label for="username">Brugernavn</label>
            <input type="text" id="username" name="username" required>
            <label for="password">Adgangskode</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Log ind</button>
        </form>
        <a class="register-link" href="register.php">Opret ny bruger</a>
    </div>
</body>
</html>