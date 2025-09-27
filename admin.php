<?php
// Admin panel: list all users and allow admin to delete users (except self and other admins).
// Also provides interface for managing information messages.
// Only accessible to users with role "admin".

session_start();
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

$users_file = __DIR__ . '/users.json';
$info_file = __DIR__ . '/info_data.json';
$users = [];
$messages = [];

// Load users
if (file_exists($users_file)) {
    $users = json_decode(file_get_contents($users_file), true);
    if (!is_array($users)) {
        $users = [];
    }
}

// Load messages
if (file_exists($info_file)) {
    $messages = json_decode(file_get_contents($info_file), true);
    if (!is_array($messages)) {
        $messages = [];
    }
}

$message = '';
$info_message = '';

// Function to generate unique ID
function generateMessageId($messages) {
    $maxId = 0;
    foreach ($messages as $msg) {
        $id = intval($msg['id']);
        if ($id > $maxId) {
            $maxId = $id;
        }
    }
    return str_pad($maxId + 1, 3, '0', STR_PAD_LEFT);
}

// Function to validate message input
function validateMessageInput($title, $content, $type) {
    $errors = [];
    
    if (empty(trim($title))) {
        $errors[] = 'Titel er påkrævet';
    } elseif (strlen(trim($title)) > 200) {
        $errors[] = 'Titel må ikke være længere end 200 tegn';
    }
    
    if (empty(trim($content))) {
        $errors[] = 'Indhold er påkrævet';
    } elseif (strlen(trim($content)) > 2000) {
        $errors[] = 'Indhold må ikke være længere end 2000 tegn';
    }
    
    $validTypes = ['important', 'normal', 'info'];
    if (!in_array($type, $validTypes)) {
        $errors[] = 'Ugyldig meddelelsestype';
    }
    
    return $errors;
}

// Handle message operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_message'])) {
        // Create new message
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['type'] ?? '';
        
        $validation_errors = validateMessageInput($title, $content, $type);
        
        if (empty($validation_errors)) {
            $new_message = [
                'id' => generateMessageId($messages),
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'created_date' => date('Y-m-d'),
                'author' => $_SESSION['user']
            ];
            
            $messages[] = $new_message;
            file_put_contents($info_file, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $info_message = 'Meddelelse blev oprettet succesfuldt.';
        } else {
            $info_message = 'Fejl: ' . implode(', ', $validation_errors);
        }
    } elseif (isset($_POST['update_message'])) {
        // Update existing message
        $id = $_POST['message_id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['type'] ?? '';
        
        $validation_errors = validateMessageInput($title, $content, $type);
        
        if (empty($validation_errors)) {
            foreach ($messages as &$msg) {
                if ($msg['id'] === $id) {
                    $msg['title'] = $title;
                    $msg['content'] = $content;
                    $msg['type'] = $type;
                    break;
                }
            }
            
            file_put_contents($info_file, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $info_message = 'Meddelelse blev opdateret succesfuldt.';
        } else {
            $info_message = 'Fejl: ' . implode(', ', $validation_errors);
        }
    } elseif (isset($_POST['delete_message'])) {
        // Delete message
        $id = $_POST['message_id'] ?? '';
        $messages = array_filter($messages, function($msg) use ($id) {
            return $msg['id'] !== $id;
        });
        
        file_put_contents($info_file, json_encode(array_values($messages), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $info_message = 'Meddelelse blev slettet succesfuldt.';
    }
}

// Get message for editing
$editing_message = null;
if (isset($_GET['edit_message'])) {
    $edit_id = $_GET['edit_message'];
    foreach ($messages as $msg) {
        if ($msg['id'] === $edit_id) {
            $editing_message = $msg;
            break;
        }
    }
}

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
    <title>Administration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
    <style>
        /* Additional styles for admin information management */
        .admin-section {
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .admin-section:last-of-type {
            border-bottom: none;
        }
        
        .admin-section h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .admin-section h3 {
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .message-form {
            background: var(--background-primary);
            padding: 2rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
        }
        
        /* Alert styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            color: var(--secondary-dark);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-success::before {
            content: '✅';
            font-size: 1.2rem;
        }
        
        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            color: var(--danger-dark);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .alert-error::before {
            content: '⚠️';
            font-size: 1.2rem;
        }
        
        /* Status badges for message types */
        .status-important {
            color: #ffffff !important;
            background: linear-gradient(135deg, var(--danger-color) 0%, var(--danger-dark) 100%) !important;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: var(--shadow-sm);
            display: inline-flex;
            align-items: center;
        }
        
        .status-normal {
            color: #ffffff !important;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%) !important;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: var(--shadow-sm);
            display: inline-flex;
            align-items: center;
        }
        
        .status-info {
            color: #ffffff !important;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%) !important;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: var(--shadow-sm);
            display: inline-flex;
            align-items: center;
        }
        
        /* Form improvements */
        .form-group label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--background-primary);
            color: var(--text-primary);
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Table enhancements */
        .table-wrapper table td {
            vertical-align: middle;
        }
        
        .table-wrapper table td form {
            margin: 0;
            padding: 0;
            background: none;
            border: none;
            box-shadow: none;
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-actions .button {
                margin: 0.25rem 0;
            }
        }
    </style>
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
            <a href="info.php">Informationer</a>
            <a href="admin.php">Admin</a>
            <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user']); ?> (admin)</span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>

    <div class="container">
        <h1>⚙️ Administration</h1>
        
        <!-- User Management Section -->
        <div class="admin-section">
            <h2>👥 Brugeradministration</h2>
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
        </div>

        <!-- Information Management Section -->
        <div class="admin-section" style="margin-top: 3rem;">
            <h2>📢 Administrer Informationer</h2>
            <?php if ($info_message): ?>
                <div class="alert <?php echo strpos($info_message, 'Fejl:') === 0 ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($info_message); ?>
                </div>
            <?php endif; ?>

            <!-- Message Form -->
            <form method="POST" class="message-form">
                <h3><?php echo $editing_message ? '✏️ Rediger Meddelelse' : '➕ Opret Ny Meddelelse'; ?></h3>
                
                <?php if ($editing_message): ?>
                    <input type="hidden" name="message_id" value="<?php echo htmlspecialchars($editing_message['id']); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">Titel:</label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           maxlength="200" 
                           required 
                           value="<?php echo htmlspecialchars($editing_message['title'] ?? ''); ?>"
                           placeholder="Indtast meddelelsens titel">
                </div>
                
                <div class="form-group">
                    <label for="content">Indhold:</label>
                    <textarea id="content" 
                              name="content" 
                              maxlength="2000" 
                              required 
                              rows="6"
                              placeholder="Indtast meddelelsens indhold"><?php echo htmlspecialchars($editing_message['content'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="type">Type:</label>
                    <select id="type" name="type" required>
                        <option value="">Vælg meddelelsestype</option>
                        <option value="important" <?php echo ($editing_message['type'] ?? '') === 'important' ? 'selected' : ''; ?>>
                            ⚠️ Vigtigt
                        </option>
                        <option value="normal" <?php echo ($editing_message['type'] ?? '') === 'normal' ? 'selected' : ''; ?>>
                            📢 Normal
                        </option>
                        <option value="info" <?php echo ($editing_message['type'] ?? '') === 'info' ? 'selected' : ''; ?>>
                            ℹ️ Information
                        </option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <?php if ($editing_message): ?>
                        <button type="submit" name="update_message" class="button">✏️ Opdater Meddelelse</button>
                        <a href="admin.php" class="button button-secondary">❌ Annuller</a>
                    <?php else: ?>
                        <button type="submit" name="create_message" class="button">➕ Opret Meddelelse</button>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Messages Table -->
            <div style="margin-top: 2rem;">
                <h3>📋 Eksisterende Meddelelser</h3>
                <?php if (count($messages) > 0): ?>
                    <div class="table-wrapper">
                        <table>
                            <tr>
                                <th>ID</th>
                                <th>Titel</th>
                                <th>Type</th>
                                <th>Forfatter</th>
                                <th>Oprettet</th>
                                <th>Handling</th>
                            </tr>
                            <?php 
                            // Sort messages by date (newest first)
                            usort($messages, function($a, $b) {
                                return strtotime($b['created_date']) - strtotime($a['created_date']);
                            });
                            
                            foreach ($messages as $msg): 
                                $typeInfo = [
                                    'important' => ['label' => 'Vigtigt', 'class' => 'status-important'],
                                    'normal' => ['label' => 'Normal', 'class' => 'status-normal'],
                                    'info' => ['label' => 'Information', 'class' => 'status-info']
                                ];
                                $currentType = $typeInfo[$msg['type']] ?? ['label' => 'Ukendt', 'class' => 'status-normal'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($msg['id']); ?></td>
                                    <td title="<?php echo htmlspecialchars($msg['content']); ?>">
                                        <strong><?php echo htmlspecialchars(substr($msg['title'], 0, 50) . (strlen($msg['title']) > 50 ? '...' : '')); ?></strong>
                                    </td>
                                    <td>
                                        <span class="<?php echo $currentType['class']; ?>">
                                            <?php echo $currentType['label']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($msg['author']); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($msg['created_date'])); ?></td>
                                    <td>
                                        <a href="?edit_message=<?php echo urlencode($msg['id']); ?>" 
                                           class="button button-secondary button-sm">✏️ Rediger</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Er du sikker på, at du vil slette denne meddelelse?');">
                                            <input type="hidden" name="message_id" value="<?php echo htmlspecialchars($msg['id']); ?>">
                                            <button type="submit" name="delete_message" class="button button-danger button-sm">🗑️ Slet</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-messages" style="text-align: center; padding: 2rem; background: var(--background-secondary); border-radius: var(--radius-lg); border: 2px dashed var(--border-color);">
                        <p style="color: var(--text-secondary); margin: 0;">
                            📭 Ingen meddelelser endnu. Opret den første meddelelse ovenfor.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-light);">
            <a href="index.php" class="button button-secondary">🏠 Tilbage til forsiden</a>
            <a href="register.php" class="button" style="margin-left: 1rem;">👤 Opret ny bruger</a>
            <a href="info.php" class="button" style="margin-left: 1rem;">📢 Se informationer</a>
        </div>
    </div>
</body>
</html>