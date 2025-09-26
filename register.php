<?php
// Start PHP session for potential future use
session_start();

// Define file paths.  Users will be stored in users.json and
// the list of available contractors will be extracted from wo_data.json.
$users_file = __DIR__ . '/users.json';
$wo_file    = __DIR__ . '/wo_data.json';

// Ensure users.json exists.  If it doesn't, create an empty array.
if (!file_exists($users_file)) {
    file_put_contents($users_file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Load existing users
$users = json_decode(file_get_contents($users_file), true);
if (!is_array($users)) {
    $users = [];
}

// Extract unique contractor firm names from WO data.  Contractors are
// specified by the key "entreprenor_firma" in wo_data.json.  If there are
// no contractors in the file, the array will remain empty.
$contractors = [];
if (file_exists($wo_file)) {
    $wo_data = json_decode(file_get_contents($wo_file), true);
    if (is_array($wo_data)) {
        foreach ($wo_data as $wo) {
            if (isset($wo['entreprenor_firma']) && $wo['entreprenor_firma'] !== '') {
                $contractors[] = $wo['entreprenor_firma'];
            }
        }
        $contractors = array_values(array_unique($contractors));
    }
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
        // Check for duplicate username
        foreach ($users as $existing) {
            if (isset($existing['username']) && strtolower($existing['username']) === strtolower($username)) {
                $error = 'Brugernavnet eksisterer allerede.';
                break;
            }
        }
    }

    // If no errors so far, register the user
    if ($error === '') {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        // Create the new user entry.  Users are not approved by default and must be
        // approved by an administrator via admin.php before they can log in.
        $new_user = [
            'username'      => $username,
            'password_hash' => $password_hash,
            'role'          => $role,
            'approved'      => false
        ];
        // Attach contractor firm to the user only if role is entreprenor
        if ($role === 'entreprenor') {
            $new_user['entreprenor_firma'] = $selected_contractor;
        }
        $users[] = $new_user;
        file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        // Redirect to login page after successful registration.  The user will
        // remain unable to log in until an administrator approves the account.
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Opret ny bruger</title>
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
                Brugernavn
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