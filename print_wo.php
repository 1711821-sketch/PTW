<?php
// Displays a single Work Order (WO) in a print friendly layout.  Requires
// authentication and an `id` query parameter referencing the WO entry.

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Use database instead of JSON files
require_once 'database.php';

$id = $_GET['id'] ?? '';
$entry = null;

try {
    $db = Database::getInstance();
    $entry = $db->fetch("SELECT * FROM work_orders WHERE id = ?", [$id]);
    
    if (!$entry) {
        echo '<p>Arbejdstilladelse ikke fundet.</p>';
        echo '<p><a href="view_wo.php">Tilbage til oversigt</a></p>';
        exit();
    }
    
    // Convert JSON strings back to arrays if needed
    if (is_string($entry['approvals'] ?? '')) {
        $entry['approvals'] = json_decode($entry['approvals'], true) ?? [];
    }
    if (is_string($entry['approval_history'] ?? '')) {
        $entry['approval_history'] = json_decode($entry['approval_history'], true) ?? [];
    }
    if (is_string($entry['completion_images'] ?? '[]')) {
        $entry['completion_images'] = json_decode($entry['completion_images'], true) ?? [];
    }
    
} catch (Exception $e) {
    error_log("Error loading work order for print: " . $e->getMessage());
    echo '<p>Fejl ved indlæsning af arbejdstilladelse.</p>';
    echo '<p><a href="view_wo.php">Tilbage til oversigt</a></p>';
    exit();
}

// Handle image upload for entrepreneurs
$upload_message = '';
$upload_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    $currentUser = $_SESSION['user'] ?? '';
    $currentRole = $_SESSION['role'] ?? '';
    $userFirma = $_SESSION['entreprenor_firma'] ?? '';
    
    // Security check: Only entrepreneurs can upload
    if ($currentRole !== 'entreprenor') {
        $upload_error = 'Kun entreprenører kan uploade billeder.';
    }
    // Security check: Entrepreneur can only upload to their own firm's work orders
    elseif ($entry['entreprenor_firma'] !== $userFirma) {
        $upload_error = 'Du kan kun uploade billeder til dit eget firmas arbejdstilladelser.';
        error_log("SECURITY VIOLATION: Entrepreneur $currentUser attempted to upload image to another firm's work order. User Firma: $userFirma, WO Firma: " . $entry['entreprenor_firma']);
    }
    // Check if file was uploaded
    elseif (!isset($_FILES['completion_image']) || $_FILES['completion_image']['error'] === UPLOAD_ERR_NO_FILE) {
        $upload_error = 'Ingen fil valgt.';
    }
    elseif ($_FILES['completion_image']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = 'Fejl ved upload af fil.';
    }
    else {
        $file = $_FILES['completion_image'];
        
        // Validate file type (only images) using MIME type detection
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Map MIME types to safe file extensions (NEVER use user-supplied extension)
        $mime_to_extension = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        
        if (!isset($mime_to_extension[$mime_type])) {
            $upload_error = 'Kun billedfiler (JPEG, PNG, GIF, WebP) er tilladt.';
        }
        // Validate file size (max 10MB)
        elseif ($file['size'] > 10485760) {
            $upload_error = 'Billedet må ikke være større end 10MB.';
        }
        else {
            // SECURITY: Use extension based on MIME type, NOT user-supplied filename
            $safe_extension = $mime_to_extension[$mime_type];
            $filename = 'wo_' . $entry['id'] . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $safe_extension;
            $upload_path = __DIR__ . '/uploads/work_order_images/' . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Add filename to completion_images array in database
                $current_images = $entry['completion_images'] ?? [];
                $current_images[] = $filename;
                
                try {
                    $db->execute("
                        UPDATE work_orders 
                        SET completion_images = ?, updated_at = NOW()
                        WHERE id = ?
                    ", [json_encode($current_images), $entry['id']]);
                    
                    $upload_message = 'Billedet er uploadet med succes!';
                    $entry['completion_images'] = $current_images;
                    
                    error_log("Image uploaded successfully - User: $currentUser, WO ID: " . $entry['id'] . ", Filename: $filename");
                } catch (Exception $e) {
                    $upload_error = 'Fejl ved gemning af billedet i databasen.';
                    error_log("Error saving image to database: " . $e->getMessage());
                    // Clean up uploaded file
                    @unlink($upload_path);
                }
            } else {
                $upload_error = 'Fejl ved gemning af fil.';
            }
        }
    }
}

// Determine status label
$statusVal = $entry['status'] ?? 'planning';
// Determine the current user role for navigation bar (if available)
$role = $_SESSION['role'] ?? '';
if ($statusVal === 'planning') {
    $statusLabel = 'Planlagt';
} elseif ($statusVal === 'active') {
    $statusLabel = 'Aktiv';
} else {
    $statusLabel = 'Afsluttet';
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Vis/Print arbejdstilladelse</title>
    <?php include 'pwa-head.php'; ?>
    <!-- Import global stylesheet for consistent typography and navigation -->
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 1rem; color: #222; }
        h1 { margin-top: 0; font-size: 1.6em; text-align: center; }
        h2 { margin-top: 1.2rem; font-size: 1.3em; border-bottom: 2px solid #0070C0; padding-bottom: 0.2rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { border: 1px solid #ccc; padding: 0.4rem; text-align: left; vertical-align: top; }
        th { background-color: #f5f5f5; width: 25%; }
        
        /* Action buttons styling */
        .action-buttons { 
            display: flex; 
            gap: 0.5rem; 
            margin-bottom: 1rem; 
            flex-wrap: wrap;
        }
        .action-btn { 
            padding: 0.5rem 1rem; 
            color: #fff; 
            text-decoration: none; 
            border-radius: 4px; 
            display: inline-block;
            transition: background-color 0.2s;
        }
        .btn-primary { background-color: #007bff; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary { background-color: #6c757d; }
        .btn-secondary:hover { background-color: #545b62; }
        
        /* Image gallery styling */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .image-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .image-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .image-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }
        
        @media print {
            .action-buttons, .navbar, .upload-section { display: none; }
            body { margin: 0; }
            h1 { font-size: 1.4em; }
            h2 { font-size: 1.2em; border-color: #000; }
            .image-gallery {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Top navigation bar -->
    <nav class="navbar">
        <a href="view_wo.php">Oversigt over arbejdstilladelser</a>
        <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
            <a href="create_wo.php">Opret ny arbejdstilladelse</a>
        <?php endif; ?>
        <a href="map_wo.php">Kort</a>
        <!-- Include Dashboard link for consistency with other pages -->
        <a href="dashboard.php">Dashboard</a>
        <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user'] ?? ''); ?> (<?php echo htmlspecialchars($role); ?>)</span>
        <a class="logout-link" href="logout.php">Log ud</a>
    </nav>
    <div class="container">
    <!-- Action buttons for better navigation -->
    <div class="action-buttons">
        <a href="view_wo.php" class="action-btn btn-secondary">← Tilbage til oversigt</a>
        <a href="#" class="action-btn btn-primary" onclick="window.print();return false;">🖨️ Print</a>
    </div>
    <h1>Arbejdstilladelse</h1>
    <h2>Basisinformation</h2>
    <table>
        <tr><th>Arbejdstilladelse Nr.</th><td><?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></td></tr>
        <tr><th>Beskrivelse</th><td><?php echo nl2br(htmlspecialchars($entry['description'] ?? '')); ?></td></tr>
        <tr><th>P-nr beskrivelse</th><td><?php echo nl2br(htmlspecialchars($entry['p_description'] ?? '')); ?></td></tr>
        <tr><th>Jobansvarlig</th><td><?php echo htmlspecialchars($entry['jobansvarlig'] ?? ''); ?></td></tr>
        <tr><th>Telefon</th><td><?php echo htmlspecialchars($entry['telefon'] ?? ''); ?></td></tr>
        <tr><th>Arbejdstilladelse oprettet af</th><td><?php echo htmlspecialchars($entry['oprettet_af'] ?? ''); ?></td></tr>
        <tr><th>Arbejdstilladelse oprettet dato</th><td><?php echo htmlspecialchars($entry['oprettet_dato'] ?? ''); ?></td></tr>
        <tr><th>Entreprenør firma</th><td><?php echo htmlspecialchars($entry['entreprenor_firma'] ?? ''); ?></td></tr>
        <tr><th>Entreprenør kontakt</th><td><?php echo htmlspecialchars($entry['entreprenor_kontakt'] ?? ''); ?></td></tr>
        <tr><th>Komponent nr.</th><td><?php echo nl2br(htmlspecialchars($entry['components'] ?? '')); ?></td></tr>
        <tr><th>Status</th><td><?php echo $statusLabel; ?></td></tr>
        <tr><th>Lokation (lat,lng)</th><td><?php
            $lat = $entry['latitude'] ?? '';
            $lng = $entry['longitude'] ?? '';
            echo ($lat && $lng) ? htmlspecialchars($lat . ', ' . $lng) : '—';
        ?></td></tr>
        <tr><th>Bemærkninger</th><td><?php echo nl2br(htmlspecialchars($entry['notes'] ?? '')); ?></td></tr>
        <?php if (!empty($entry['pdf_file'])): ?>
        <tr><th>Original PDF</th><td><a href="uploads/<?php echo urlencode($entry['pdf_file']); ?>" target="_blank">Download</a></td></tr>
        <?php endif; ?>
    </table>
    <?php if (!empty($entry['approval_history']) && is_array($entry['approval_history'])): ?>
        <h2>Godkendelseshistorik</h2>
        <table>
            <tr><th>Tidspunkt</th><th>Bruger</th><th>Rolle</th></tr>
            <?php foreach ($entry['approval_history'] as $hist): ?>
                <tr>
                    <td><?php echo htmlspecialchars($hist['timestamp'] ?? ''); ?></td>
                    <td><?php 
                        $userDisplay = htmlspecialchars($hist['user'] ?? '');
                        // If this is an entrepreneur approval and company name is available, show it
                        if (($hist['role'] ?? '') === 'entreprenor' && !empty($hist['company'])) {
                            $userDisplay .= ' (' . htmlspecialchars($hist['company']) . ')';
                        }
                        echo $userDisplay;
                    ?></td>
                    <td><?php echo htmlspecialchars($hist['role'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <h2>Godkendelseshistorik</h2>
        <p>Ingen godkendelser endnu.</p>
    <?php endif; ?>

    <!-- Section for displaying SJA entries linked to this WO -->
    <h2>Tilknyttede SJA'er</h2>
    <?php
        // Load SJA entries from database that reference this work order
        $attached_sja = [];
        try {
            // Check if sja_entries table exists and get related SJAs
            $attached_sja = $db->fetchAll("
                SELECT * FROM sja_entries 
                WHERE work_order_id = ? 
                ORDER BY created_at DESC
            ", [$entry['id']]);
        } catch (Exception $e) {
            // SJA table might not exist yet, that's okay
            error_log("SJA table not available: " . $e->getMessage());
        }
    ?>
    <?php if (!empty($attached_sja)): ?>
        <table>
            <tr><th>SJA ID</th><th>Opgave</th><th>Oprettet</th><th>Handlinger</th></tr>
            <?php foreach ($attached_sja as $sja): 
                $basic_info = json_decode($sja['basic_info'] ?? '{}', true);
                $opgave = $basic_info['opgave'] ?? 'SJA';
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($sja['id']); ?></td>
                    <td><?php echo htmlspecialchars($opgave); ?></td>
                    <td><?php echo htmlspecialchars($sja['created_at'] ?? ''); ?></td>
                    <td><a href="print_sja.php?id=<?php echo urlencode($sja['id']); ?>">Se SJA</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Ingen tilknyttede SJA'er fundet for denne arbejdstilladelse.</p>
    <?php endif; ?>
    <div class="action-buttons" style="margin-top: 2rem;">
        <a href="create_sja.php?wo_id=<?php echo urlencode($entry['id']); ?>" class="action-btn btn-primary">Opret ny SJA til denne WO</a>
    </div>

    <!-- Section for displaying time consumption for this work order -->
    <h2>Timeforbrug</h2>
    <?php
        // Load time entries from database that reference this work order
        $time_consumption = [];
        try {
            // Get time entries grouped by user with total hours
            $time_consumption = $db->fetchAll("
                SELECT 
                    u.username as brugernavn,
                    SUM(te.hours) as total_timer
                FROM time_entries te
                JOIN users u ON te.user_id = u.id
                WHERE te.work_order_id = ?
                GROUP BY u.username, u.id
                ORDER BY u.username
            ", [$entry['id']]);
        } catch (Exception $e) {
            // Time entries table might not exist yet or other error
            error_log("Error loading time entries: " . $e->getMessage());
        }
    ?>
    <?php if (!empty($time_consumption)): ?>
        <table>
            <tr><th>Brugernavn</th><th>Samlet timeforbrug</th></tr>
            <?php 
            $total_all_hours = 0;
            foreach ($time_consumption as $consumption): 
                $total_all_hours += floatval($consumption['total_timer'] ?? 0);
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($consumption['brugernavn'] ?? ''); ?></td>
                    <td><?php echo number_format(floatval($consumption['total_timer'] ?? 0), 2, ',', '.') . ' timer'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($time_consumption) > 1): ?>
                <tr style="border-top: 2px solid #333; font-weight: bold;">
                    <td><strong>Total</strong></td>
                    <td><strong><?php echo number_format($total_all_hours, 2, ',', '.') . ' timer'; ?></strong></td>
                </tr>
            <?php endif; ?>
        </table>
    <?php else: ?>
        <p>Ingen timer registreret for denne arbejdstilladelse endnu.</p>
    <?php endif; ?>
    
    <!-- Image upload section for entrepreneurs -->
    <?php 
    $currentUser = $_SESSION['user'] ?? '';
    $currentRole = $_SESSION['role'] ?? '';
    $userFirma = $_SESSION['entreprenor_firma'] ?? '';
    $canUpload = ($currentRole === 'entreprenor' && $entry['entreprenor_firma'] === $userFirma);
    ?>
    
    <h2>Dokumentationsbilleder</h2>
    
    <?php if ($canUpload): ?>
        <div class="upload-section" style="margin-bottom: 1.5rem; padding: 1rem; background-color: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
            <h3 style="margin-top: 0; font-size: 1.1em;">Upload dokumentationsbilleder</h3>
            <p style="margin-bottom: 1rem; color: #666;">Upload billeder for at dokumentere afsluttet arbejde. Tilladt: JPEG, PNG, GIF, WebP (maks. 10MB)</p>
            
            <?php if ($upload_message): ?>
                <div style="padding: 0.75rem; margin-bottom: 1rem; background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 4px;">
                    ✅ <?php echo htmlspecialchars($upload_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($upload_error): ?>
                <div style="padding: 0.75rem; margin-bottom: 1rem; background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px;">
                    ❌ <?php echo htmlspecialchars($upload_error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <input type="file" name="completion_image" accept="image/*" required 
                       style="padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; min-width: 200px;">
                <button type="submit" name="upload_image" value="1" 
                        style="padding: 0.6rem 1.5rem; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    📤 Upload billede
                </button>
            </form>
        </div>
    <?php endif; ?>
    
    <!-- Display uploaded images -->
    <?php if (!empty($entry['completion_images']) && is_array($entry['completion_images'])): ?>
        <div class="image-gallery" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
            <?php foreach ($entry['completion_images'] as $image_filename): ?>
                <div class="image-card" style="border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <a href="uploads/work_order_images/<?php echo urlencode($image_filename); ?>" target="_blank">
                        <img src="uploads/work_order_images/<?php echo urlencode($image_filename); ?>" 
                             alt="Dokumentationsbillede" 
                             style="width: 100%; height: 200px; object-fit: cover; display: block;">
                    </a>
                    <div style="padding: 0.5rem; background-color: #f8f9fa; font-size: 0.85em; color: #666; text-align: center;">
                        Klik for at se i fuld størrelse
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color: #666; font-style: italic;">Ingen dokumentationsbilleder uploadet endnu.</p>
    <?php endif; ?>
    
    </div><!-- /.container -->
</body>
</html>