<?php
// Admin panel: list all users and allow admin to delete users (except self and other admins).
// Only accessible to users with role "admin".

session_start();
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

$users_file = __DIR__ . '/users.json';
$users = [];
if (file_exists($users_file)) {
    $users = json_decode(file_get_contents($users_file), true);
    if (!is_array($users)) {
        $users = [];
    }
}

$message = '';

// Handle approval via GET parameter.  When the admin clicks on a
// ?approve=username link, the specified user's 'approved' flag is set
// to true and the updated users list is saved back to users.json.
if (isset($_GET['approve'])) {
    $approve_user = $_GET['approve'];
    foreach ($users as &$u) {
        if ($u['username'] === $approve_user) {
            // Set approved to true.  If the key does not exist it will be added.
            $u['approved'] = true;
            $message = 'Bruger "' . htmlspecialchars($approve_user) . '" er nu godkendt.';
            break;
        }
    }
    // Persist the modified users list.
    file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Handle deletion via GET parameter
if (isset($_GET['delete'])) {
    $delete_user = $_GET['delete'];
    $updated = [];
    foreach ($users as $u) {
        // Do not delete admin accounts
        if ($u['username'] === $delete_user && $u['role'] !== 'admin') {
            // Skip this user to delete
            continue;
        }
        $updated[] = $u;
    }
    // Check if deletion happened
    if (count($updated) < count($users)) {
        file_put_contents($users_file, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $users = $updated;
        $message = 'Bruger "' . htmlspecialchars($delete_user) . '" er blevet slettet.';
    } else {
        $message = 'Kunne ikke slette bruger. Administratorer kan ikke slettes.';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Brugeradministration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        h1 { margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 0.4rem; text-align: left; }
        th { background-color: #f2f2f2; }
        .msg { margin-bottom: 1rem; color: green; }
        .btn-delete { color: #fff; background-color: #d9534f; padding: 0.3rem 0.6rem; border-radius: 3px; text-decoration: none; }
        a { color: #0070C0; }
    </style>
</head>
<body>
    <h1>Administrer brugere</h1>
    <?php if ($message): ?>
        <div class="msg"><?php echo $message; ?></div>
    <?php endif; ?>
    <table>
        <tr>
            <th>Brugernavn</th>
            <th>Rolle</th>
            <th>Status</th>
            <th>Handling</th>
        </tr>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo htmlspecialchars($u['username']); ?></td>
                <td><?php echo htmlspecialchars($u['role']); ?></td>
                <td>
                    <?php
                        $approved = true;
                        if (array_key_exists('approved', $u)) {
                            $approved = (bool)$u['approved'];
                        }
                        echo $approved ? '<span style="color:green;">Godkendt</span>' : '<span style="color:red;">Afventer</span>';
                    ?>
                </td>
                <td>
                    <?php if (!$approved): ?>
                        <a href="?approve=<?php echo urlencode($u['username']); ?>">Godkend</a>
                    <?php endif; ?>
                    <?php if ($u['role'] !== 'admin'): ?>
                        <?php if (!$approved) echo ' | '; ?>
                        <a class="btn-delete" href="?delete=<?php echo urlencode($u['username']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne bruger?');">Slet</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p><a href="index.php">Tilbage til forsiden</a></p>
</body>
</html>