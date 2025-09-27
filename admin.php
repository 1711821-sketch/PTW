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
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
</head>
<body>
    <!-- Navigation bar with hamburger menu -->
    <nav class="navbar">
        <button class="navbar-toggle" aria-label="Toggle navigation">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <div class="navbar-links">
            <a href="index.php">Forside</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_wo.php">WO Oversigt</a>
            <a href="view_sja.php">SJA Oversigt</a>
            <a href="admin.php">Admin</a>
            <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user']); ?> (admin)</span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>

    <div class="container">
        <h1>⚙️ Brugeradministration</h1>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="table-wrapper">
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
                        echo $approved ? '<span class="status-aktiv">Godkendt</span>' : '<span class="status-planlagt">Afventer</span>';
                    ?>
                </td>
                <td>
                    <?php if (!$approved): ?>
                        <a class="button button-success button-sm" href="?approve=<?php echo urlencode($u['username']); ?>">✅ Godkend</a>
                    <?php endif; ?>
                    <?php if ($u['role'] !== 'admin'): ?>
                        <?php if (!$approved) echo ' | '; ?>
                        <a class="button button-danger button-sm" href="?delete=<?php echo urlencode($u['username']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne bruger?');">🗑️ Slet</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
        </div>
        
        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-light);">
            <a href="index.php" class="button button-secondary">← Tilbage til forsiden</a>
            <a href="register.php" class="button" style="margin-left: 1rem;">👤 Opret ny bruger</a>
        </div>
    </div>
</body>
</html>