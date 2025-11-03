<?php
// Displays a single Work Order (WO) in a print friendly layout.  Requires
// authentication and an `id` query parameter referencing the WO entry.

session_start();
require_once 'auth_check.php';

// Set Danish timezone
date_default_timezone_set('Europe/Copenhagen');

// Use database instead of JSON files
require_once 'database.php';

// Include approval workflow widget
require_once 'approval_workflow_widget.php';

$id = $_GET['id'] ?? '';
$entry = null;

try {
    $db = Database::getInstance();
    $entry = $db->fetch("SELECT * FROM work_orders WHERE id = ?", [$id]);
    
    if (!$entry) {
        echo '<p>PTW ikke fundet.</p>';
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
    echo '<p>Fejl ved indlæsning af PTW.</p>';
    echo '<p><a href="view_wo.php">Tilbage til oversigt</a></p>';
    exit();
}

// Handle image upload for entrepreneurs
$upload_message = '';
$upload_error = '';
$delete_message = '';
$delete_error = '';

// Handle image deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $currentUser = $_SESSION['user'] ?? '';
    $currentRole = $_SESSION['role'] ?? '';
    $userFirma = $_SESSION['entreprenor_firma'] ?? '';
    $image_to_delete = $_POST['image_filename'] ?? '';
    
    // Security check: Only entrepreneurs can delete
    if ($currentRole !== 'entreprenor') {
        $delete_error = 'Kun entreprenører kan slette billeder.';
    }
    // Security check: Entrepreneur can only delete from their own firm's work orders
    elseif ($entry['entreprenor_firma'] !== $userFirma) {
        $delete_error = 'Du kan kun slette billeder fra dit eget firmas PTW\'er.';
        error_log("SECURITY VIOLATION: Entrepreneur $currentUser attempted to delete image from another firm's work order. User Firma: $userFirma, WO Firma: " . $entry['entreprenor_firma']);
    }
    elseif (empty($image_to_delete)) {
        $delete_error = 'Intet billednavn angivet.';
    }
    else {
        // Verify the image exists in the database
        $current_images = $entry['completion_images'] ?? [];
        $image_index = array_search($image_to_delete, $current_images);
        
        if ($image_index === false) {
            $delete_error = 'Billedet blev ikke fundet i databasen.';
        }
        else {
            // Remove from array
            array_splice($current_images, $image_index, 1);
            
            try {
                // Update database
                $db->execute("
                    UPDATE work_orders 
                    SET completion_images = ?, updated_at = NOW()
                    WHERE id = ?
                ", [json_encode($current_images), $entry['id']]);
                
                // Delete physical file
                $file_path = __DIR__ . '/uploads/work_order_images/' . $image_to_delete;
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
                
                $delete_message = 'Billedet er slettet med succes!';
                $entry['completion_images'] = $current_images;
                
                error_log("Image deleted successfully - User: $currentUser, WO ID: " . $entry['id'] . ", Filename: $image_to_delete");
            } catch (Exception $e) {
                $delete_error = 'Fejl ved sletning af billedet fra databasen.';
                error_log("Error deleting image from database: " . $e->getMessage());
            }
        }
    }
}

// Handle image upload
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
        $upload_error = 'Du kan kun uploade billeder til dit eget firmas PTW\'er.';
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
        // Extended format support for modern smartphones
        // NOTE: Only formats that browsers can render natively are allowed
        // NOTE: SVG excluded due to XSS security concerns
        // NOTE: HEIC/HEIF/TIFF/BMP excluded as they cannot be displayed in standard browsers
        $mime_to_extension = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif'
        ];
        
        if (!isset($mime_to_extension[$mime_type])) {
            $upload_error = 'Kun billedfiler der kan vises i browsere er tilladt (JPEG, PNG, GIF, WebP, AVIF). iPhone-brugere: Indstil kameraet til at gemme som JPEG i Indstillinger → Kamera → Formater → Mest kompatibel.';
        }
        // Validate file size (max 50MB for high-resolution smartphone images)
        elseif ($file['size'] > 52428800) {
            $upload_error = 'Billedet må ikke være større end 50MB.';
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
    <title>Vis/Print PTW</title>
    <?php include 'pwa-head.php'; ?>
    <!-- Import global stylesheet for consistent typography and navigation -->
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 1rem; color: #222; }
        h1 { margin-top: 0; font-size: 1.6em; text-align: center; }
        h2 { margin-top: 1.2rem; font-size: 1.3em; border-bottom: 2px solid #0070C0; padding-bottom: 0.2rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { border: 1px solid #ccc; padding: 0.4rem; text-align: left; vertical-align: top; word-wrap: break-word; }
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
        
        /* Mobile responsive styles - tablets and up */
        @media screen and (max-width: 768px) {
            body { margin: 0.5rem; font-size: 14px; }
            h1 { font-size: 1.3em; }
            h2 { font-size: 1.1em; margin-top: 1rem; }
            
            /* Make tables responsive */
            table { font-size: 0.9em; }
            th, td { padding: 0.3rem; font-size: 0.95em; }
            
            /* Action buttons full width on mobile */
            .action-buttons { gap: 0.3rem; }
            .action-btn { 
                flex: 1 1 100%;
                text-align: center;
                padding: 0.6rem 0.8rem;
                font-size: 0.9em;
            }
            
            /* Image gallery - single column on mobile */
            .image-gallery {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }
            
            /* Upload section adjustments */
            .upload-section { padding: 0.8rem; }
            .upload-section h3 { font-size: 1em; }
            .upload-section form { flex-direction: column; }
            .upload-section input[type="file"],
            .upload-section button { width: 100%; min-width: auto; }
        }
        
        /* Stack table cells vertically on very small screens */
        @media screen and (max-width: 480px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { margin-bottom: 1rem; border: 1px solid #ccc; padding: 0.5rem; }
            td { 
                border: none;
                padding: 0.6rem;
                text-align: left;
            }
            td:before { 
                display: block;
                font-weight: bold;
                content: attr(data-label);
                margin-bottom: 0.4rem;
                word-wrap: break-word;
                overflow-wrap: break-word;
                color: #333;
            }
            th { width: 100%; }
        }
        
        /* Collapsible sections styling */
        .card-collapsible-section {
            padding: 0;
            margin-bottom: 1.5rem;
        }

        .card-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background: rgba(59, 130, 246, 0.05);
            border-radius: 8px;
            cursor: pointer;
            user-select: none;
            transition: background 0.2s ease;
        }
        
        .card-section-header:hover {
            background: rgba(59, 130, 246, 0.1);
        }

        .card-section-header h2 {
            margin: 0;
            font-size: 1.1rem;
            border-bottom: none;
            padding: 0;
        }
        
        .collapsible-section-content {
            display: none;
            margin-top: 0.75rem;
            animation: slideDown 0.3s ease-out;
        }
        
        .collapsible-section-content.expanded {
            display: block;
        }
        
        /* Collapsible approval history styling */
        .card-approval-history {
            padding: 0;
            margin-bottom: 1.5rem;
        }

        .card-history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background: rgba(99, 102, 241, 0.05);
            border-radius: 8px;
            cursor: pointer;
            user-select: none;
            transition: background 0.2s ease;
        }
        
        .card-history-header:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        .card-history-header h2 {
            margin: 0;
            font-size: 1.1rem;
            border-bottom: none;
            padding: 0;
        }
        
        .history-count {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 600;
        }
        
        .toggle-icon {
            font-size: 1rem;
            transition: transform 0.3s ease;
            display: inline-block;
        }
        
        .toggle-icon.expanded {
            transform: rotate(180deg);
        }
        
        .approval-history-section {
            display: none;
            margin-top: 0.75rem;
            animation: slideDown 0.3s ease-out;
        }
        
        .approval-history-section.expanded {
            display: block;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 1000px;
            }
        }
        
        /* Approval summary styling */
        .approval-summary {
            font-size: 0.9rem;
            color: #666;
            font-weight: 600;
        }
        
        @media print {
            .action-buttons, .navbar, .upload-section { display: none; }
            body { margin: 0; }
            h1 { font-size: 1.4em; }
            h2 { font-size: 1.2em; border-color: #000; }
            .image-gallery {
                grid-template-columns: repeat(2, 1fr);
            }
            
            /* Hide delete buttons when printing */
            .image-card form[method="POST"] {
                display: none !important;
            }
            
            /* Show all collapsible sections when printing */
            .collapsible-section-content,
            .approval-history-section {
                display: block !important;
            }
            .toggle-icon {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation.php'; ?>
    <div class="container">
    <!-- Action buttons for better navigation -->
    <div class="action-buttons">
        <a href="view_wo.php" class="action-btn btn-secondary">← Tilbage til oversigt</a>
        <a href="#" class="action-btn btn-primary" onclick="window.print();return false;">🖨️ Print</a>
    </div>
    <h1>PTW</h1>
    
    <div class="card-collapsible-section">
        <div class="card-section-header" onclick="toggleSection(<?php echo $entry['id']; ?>, 'basis')">
            <h2>📋 Basisinformation</h2>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="toggle-icon" id="basis-icon-<?php echo $entry['id']; ?>">▼</span>
            </div>
        </div>
        <div class="collapsible-section-content" id="basis-section-<?php echo $entry['id']; ?>">
            <table>
                <tr><th>PTW Nr.</th><td data-label="PTW Nr."><?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></td></tr>
                <tr><th>Beskrivelse</th><td data-label="Beskrivelse"><?php echo nl2br(htmlspecialchars($entry['description'] ?? '')); ?></td></tr>
                <tr><th>Indkøbsordre nummer</th><td data-label="Indkøbsordre nummer"><?php echo htmlspecialchars($entry['p_number'] ?? ''); ?></td></tr>
                <tr><th>Indkøbsordre beskrivelse</th><td data-label="Indkøbsordre beskrivelse"><?php echo nl2br(htmlspecialchars($entry['p_description'] ?? '')); ?></td></tr>
                <tr><th>Jobansvarlig</th><td data-label="Jobansvarlig"><?php echo htmlspecialchars($entry['jobansvarlig'] ?? ''); ?></td></tr>
                <tr><th>Telefon</th><td data-label="Telefon"><?php echo htmlspecialchars($entry['telefon'] ?? ''); ?></td></tr>
                <tr><th>PTW oprettet af</th><td data-label="PTW oprettet af"><?php echo htmlspecialchars($entry['oprettet_af'] ?? ''); ?></td></tr>
                <tr><th>PTW oprettet dato</th><td data-label="PTW oprettet dato"><?php echo htmlspecialchars($entry['oprettet_dato'] ?? ''); ?></td></tr>
                <tr><th>Entreprenør firma</th><td data-label="Entreprenør firma"><?php echo htmlspecialchars($entry['entreprenor_firma'] ?? ''); ?></td></tr>
                <tr><th>Entreprenør kontakt</th><td data-label="Entreprenør kontakt"><?php echo htmlspecialchars($entry['entreprenor_kontakt'] ?? ''); ?></td></tr>
                <tr><th>Komponent nr.</th><td data-label="Komponent nr."><?php echo nl2br(htmlspecialchars($entry['components'] ?? '')); ?></td></tr>
                <tr><th>Status</th><td data-label="Status"><?php echo $statusLabel; ?></td></tr>
                <tr><th>Lokation (lat,lng)</th><td data-label="Lokation (lat,lng)"><?php
                    $lat = $entry['latitude'] ?? '';
                    $lng = $entry['longitude'] ?? '';
                    echo ($lat && $lng) ? htmlspecialchars($lat . ', ' . $lng) : '—';
                ?></td></tr>
                <tr><th>Bemærkninger</th><td data-label="Bemærkninger"><?php echo nl2br(htmlspecialchars($entry['notes'] ?? '')); ?></td></tr>
                <?php if (!empty($entry['pdf_file'])): ?>
                <tr><th>Original PDF</th><td data-label="Original PDF"><a href="uploads/<?php echo urlencode($entry['pdf_file']); ?>" target="_blank">Download</a></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <!-- Section 2: Godkendelsesproces (using standard collapsible structure) -->
    <?php 
    $today = date('d-m-Y');
    $approvals = $entry['approvals'] ?? [];
    // Get approval statuses for today (PTW requires daily reapproval for safety)
    $oaApproved = isset($approvals['opgaveansvarlig']) && $approvals['opgaveansvarlig'] === $today;
    $driftApproved = isset($approvals['drift']) && $approvals['drift'] === $today;
    $entApproved = isset($approvals['entreprenor']) && $approvals['entreprenor'] === $today;
    
    $approvalCount = 0;
    if ($oaApproved) $approvalCount++;
    if ($driftApproved) $approvalCount++;
    if ($entApproved) $approvalCount++;
    $statusText = "Godkendt {$approvalCount}/3";
    ?>
    <div class="card-collapsible-section">
        <div class="card-section-header" onclick="toggleSection(<?php echo $entry['id']; ?>, 'approval')">
            <h2>✅ Godkendelsesproces</h2>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="approval-summary"><?php echo $statusText; ?></span>
                <span class="toggle-icon" id="approval-icon-<?php echo $entry['id']; ?>">▼</span>
            </div>
        </div>
        <div class="collapsible-section-content" id="approval-section-<?php echo $entry['id']; ?>">
            <?php 
            // Render approval workflow widget content (without its own wrapper)
            $approval_history = $entry['approval_history'] ?? [];
            
            // Get timestamps from approval history
            $oaTimestamp = '';
            $driftTimestamp = '';
            $entTimestamp = '';
            
            if (is_array($approval_history)) {
                // Get timestamps for today's approvals only (matching original widget logic)
                foreach ($approval_history as $hist) {
                    if (($hist['role'] ?? '') === 'opgaveansvarlig' && strpos($hist['timestamp'] ?? '', $today) === 0) {
                        $oaTimestamp = $hist['timestamp'] ?? '';
                    }
                    if (($hist['role'] ?? '') === 'drift' && strpos($hist['timestamp'] ?? '', $today) === 0) {
                        $driftTimestamp = $hist['timestamp'] ?? '';
                    }
                    if (($hist['role'] ?? '') === 'entreprenor' && strpos($hist['timestamp'] ?? '', $today) === 0) {
                        $entTimestamp = $hist['timestamp'] ?? '';
                    }
                }
            }
            
            // Determine user's ability to approve (can only approve once, and only if not already approved)
            $canApproveOA = ($role === 'admin' || $role === 'opgaveansvarlig') && !$oaApproved;
            $canApproveDrift = ($role === 'admin' || $role === 'drift') && !$driftApproved;
            $canApproveEnt = ($role === 'admin' || $role === 'entreprenor') && !$entApproved;
            
            // Determine step state
            $oaState = $oaApproved ? 'approved' : ($canApproveOA ? 'current_user' : 'pending');
            $driftState = $driftApproved ? 'approved' : ($canApproveDrift ? 'current_user' : 'pending');
            $entState = $entApproved ? 'approved' : ($canApproveEnt ? 'current_user' : 'pending');
            ?>
            <div class="approval-workflow-widget">
                <div class="workflow-steps">
                    <!-- Step 1: Opgaveansvarlig -->
                    <div class="workflow-step <?php echo $oaState; ?>">
                        <div class="step-icon">
                            <?php if ($oaApproved): ?>✅
                            <?php elseif ($canApproveOA): ?>👤
                            <?php else: ?>⏳<?php endif; ?>
                        </div>
                        <div class="step-content">
                            <div class="step-title">Opgaveansvarlig</div>
                            <?php if ($oaApproved && $oaTimestamp): ?>
                                <div class="step-timestamp"><?php echo htmlspecialchars($oaTimestamp); ?></div>
                            <?php elseif (!$oaApproved): ?>
                                <div class="step-status">Afventer</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($canApproveOA): ?>
                            <button class="step-approve-btn ajax-approve-btn" 
                                    data-id="<?php echo $entry['id']; ?>" 
                                    data-role="opgaveansvarlig">
                                Godkend
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Arrow 1 -->
                    <div class="workflow-arrow <?php echo $oaApproved ? 'active' : ''; ?>">→</div>
                    
                    <!-- Step 2: Drift -->
                    <div class="workflow-step <?php echo $driftState; ?>">
                        <div class="step-icon">
                            <?php if ($driftApproved): ?>✅
                            <?php elseif ($canApproveDrift): ?>👤
                            <?php else: ?>⏳<?php endif; ?>
                        </div>
                        <div class="step-content">
                            <div class="step-title">Drift</div>
                            <?php if ($driftApproved && $driftTimestamp): ?>
                                <div class="step-timestamp"><?php echo htmlspecialchars($driftTimestamp); ?></div>
                            <?php elseif (!$driftApproved): ?>
                                <div class="step-status">Afventer</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($canApproveDrift): ?>
                            <button class="step-approve-btn ajax-approve-btn" 
                                    data-id="<?php echo $entry['id']; ?>" 
                                    data-role="drift">
                                Godkend
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Arrow 2 -->
                    <div class="workflow-arrow <?php echo $driftApproved ? 'active' : ''; ?>">→</div>
                    
                    <!-- Step 3: Entreprenør -->
                    <div class="workflow-step <?php echo $entState; ?>">
                        <div class="step-icon">
                            <?php if ($entApproved): ?>✅
                            <?php elseif ($canApproveEnt): ?>👤
                            <?php else: ?>⏳<?php endif; ?>
                        </div>
                        <div class="step-content">
                            <div class="step-title">Entreprenør</div>
                            <?php if ($entApproved && $entTimestamp): ?>
                                <div class="step-timestamp"><?php echo htmlspecialchars($entTimestamp); ?></div>
                            <?php elseif (!$entApproved): ?>
                                <div class="step-status">Afventer</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($canApproveEnt): ?>
                            <button class="step-approve-btn ajax-approve-btn" 
                                    data-id="<?php echo $entry['id']; ?>" 
                                    data-role="entreprenor">
                                Godkend
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($entry['approval_history']) && is_array($entry['approval_history'])): ?>
    <div class="card-collapsible-section">
        <div class="card-section-header" onclick="toggleSection(<?php echo $entry['id']; ?>, 'history')">
            <h2>📜 Godkendelseshistorik</h2>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="history-count"><?php echo count($entry['approval_history']); ?> godkendelser</span>
                <span class="toggle-icon" id="history-icon-<?php echo $entry['id']; ?>">▼</span>
            </div>
        </div>
        <div class="collapsible-section-content" id="history-section-<?php echo $entry['id']; ?>">
            <table>
                <tr><th>Tidspunkt</th><th>Bruger</th><th>Rolle</th></tr>
                <?php foreach ($entry['approval_history'] as $hist): ?>
                    <tr>
                        <td data-label="Tidspunkt"><?php echo htmlspecialchars($hist['timestamp'] ?? ''); ?></td>
                        <td data-label="Bruger"><?php 
                            $userDisplay = htmlspecialchars($hist['user'] ?? '');
                            // If this is an entrepreneur approval and company name is available, show it
                            if (($hist['role'] ?? '') === 'entreprenor' && !empty($hist['company'])) {
                                $userDisplay .= ' (' . htmlspecialchars($hist['company']) . ')';
                            }
                            echo $userDisplay;
                        ?></td>
                        <td data-label="Rolle"><?php echo htmlspecialchars($hist['role'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Section for displaying SJA entries linked to this WO -->
    <?php if ($modules['sja']): ?>
    <div class="card-collapsible-section">
        <div class="card-section-header" onclick="toggleSection(<?php echo $entry['id']; ?>, 'sja')">
            <h2>📝 Tilknyttede SJA'er</h2>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="toggle-icon" id="sja-icon-<?php echo $entry['id']; ?>">▼</span>
            </div>
        </div>
        <div class="collapsible-section-content" id="sja-section-<?php echo $entry['id']; ?>">
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
                    <td data-label="SJA ID"><?php echo htmlspecialchars($sja['id']); ?></td>
                    <td data-label="Opgave"><?php echo htmlspecialchars($opgave); ?></td>
                    <td data-label="Oprettet"><?php echo htmlspecialchars($sja['created_at'] ?? ''); ?></td>
                    <td data-label="Handlinger"><a href="print_sja.php?id=<?php echo urlencode($sja['id']); ?>">Se SJA</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Ingen tilknyttede SJA'er fundet for denne PTW.</p>
    <?php endif; ?>
    <div class="action-buttons" style="margin-top: 2rem;">
        <a href="create_sja.php?wo_id=<?php echo urlencode($entry['id']); ?>" class="action-btn btn-primary">Opret ny SJA til denne PTW</a>
        <a href="view_sja.php" class="action-btn btn-secondary">Se SJA Oversigt</a>
    </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Section for displaying time consumption for this work order -->
    <?php if ($modules['tidsregistrering']): ?>
    <div class="card-collapsible-section">
        <div class="card-section-header" onclick="toggleSection(<?php echo $entry['id']; ?>, 'time')">
            <h2>⏱️ Timeforbrug</h2>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="toggle-icon" id="time-icon-<?php echo $entry['id']; ?>">▼</span>
            </div>
        </div>
        <div class="collapsible-section-content" id="time-section-<?php echo $entry['id']; ?>">
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
                    <td data-label="Brugernavn"><?php echo htmlspecialchars($consumption['brugernavn'] ?? ''); ?></td>
                    <td data-label="Samlet timeforbrug"><?php echo number_format(floatval($consumption['total_timer'] ?? 0), 2, ',', '.') . ' timer'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($time_consumption) > 1): ?>
                <tr style="border-top: 2px solid #333; font-weight: bold;">
                    <td data-label="Brugernavn"><strong>Total</strong></td>
                    <td data-label="Samlet timeforbrug"><strong><?php echo number_format($total_all_hours, 2, ',', '.') . ' timer'; ?></strong></td>
                </tr>
            <?php endif; ?>
        </table>
    <?php else: ?>
        <p>Ingen timer registreret for denne PTW endnu.</p>
    <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Image upload section for entrepreneurs -->
    <?php 
    $currentUser = $_SESSION['user'] ?? '';
    $currentRole = $_SESSION['role'] ?? '';
    $userFirma = $_SESSION['entreprenor_firma'] ?? '';
    $canUpload = ($currentRole === 'entreprenor' && $entry['entreprenor_firma'] === $userFirma);
    ?>
    
    <div class="card-collapsible-section">
        <div class="card-section-header" onclick="toggleSection(<?php echo $entry['id']; ?>, 'images')">
            <h2>📸 Dokumentationsbilleder</h2>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="toggle-icon" id="images-icon-<?php echo $entry['id']; ?>">▼</span>
            </div>
        </div>
        <div class="collapsible-section-content" id="images-section-<?php echo $entry['id']; ?>">
    
    <?php if ($canUpload): ?>
        <div class="upload-section" style="margin-bottom: 1.5rem; padding: 1rem; background-color: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
            <h3 style="margin-top: 0; font-size: 1.1em;">Upload dokumentationsbilleder</h3>
            <p style="margin-bottom: 1rem; color: #666;">Upload billeder for at dokumentere afsluttet arbejde. Tilladt: JPEG, PNG, GIF, WebP, AVIF (maks. 50MB). <strong>iPhone-brugere:</strong> Indstil kameraet til at gemme som JPEG i Indstillinger → Kamera → Formater → Mest kompatibel.</p>
            
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
            
            <?php if ($delete_message): ?>
                <div style="padding: 0.75rem; margin-bottom: 1rem; background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 4px;">
                    ✅ <?php echo htmlspecialchars($delete_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($delete_error): ?>
                <div style="padding: 0.75rem; margin-bottom: 1rem; background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px;">
                    ❌ <?php echo htmlspecialchars($delete_error); ?>
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
                             loading="lazy"
                             style="width: 100%; height: 200px; object-fit: cover; display: block;">
                    </a>
                    <div style="padding: 0.5rem; background-color: #f8f9fa; font-size: 0.85em; color: #666;">
                        <div style="text-align: center; margin-bottom: 0.5rem;">
                            Klik for at se i fuld størrelse
                        </div>
                        <?php if ($canUpload): ?>
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Er du sikker på, at du vil slette dette billede? Denne handling kan ikke fortrydes.');">
                                <input type="hidden" name="image_filename" value="<?php echo htmlspecialchars($image_filename); ?>">
                                <button type="submit" name="delete_image" value="1" 
                                        style="width: 100%; padding: 0.4rem; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; font-weight: 500;">
                                    🗑️ Slet billede
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color: #666; font-style: italic;">Ingen dokumentationsbilleder uploadet endnu.</p>
    <?php endif; ?>
        </div>
    </div>
    
    </div><!-- /.container -->
    
    <script>
        // Generic toggle function for all collapsible sections
        function toggleSection(woId, sectionName) {
            const section = document.getElementById(`${sectionName}-section-${woId}`);
            const icon = document.getElementById(`${sectionName}-icon-${woId}`);
            
            // Ensure elements exist before trying to access their properties
            if (!section || !icon) {
                console.error(`Toggle section elements not found: ${sectionName}-section-${woId} or ${sectionName}-icon-${woId}`);
                return;
            }
            
            if (section.classList.contains('expanded')) {
                section.classList.remove('expanded');
                icon.classList.remove('expanded');
            } else {
                section.classList.add('expanded');
                icon.classList.add('expanded');
            }
        }
    </script>
</body>
</html>