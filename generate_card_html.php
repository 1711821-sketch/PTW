<?php
// This file generates HTML for a single work order card
// Expected variables in scope: $updatedWorkOrder, $role, $today, $modules, $csrf_token, $db

// Use $updatedWorkOrder as $entry for consistency with main code
$entry = $updatedWorkOrder;

// Prepare variables (same as main card generation)
$status = $entry['status'] ?? 'planning';
if ($status === 'planning') {
    $statusLabel = 'Planlagt';
    $statusClass = 'status-planlagt';
} elseif ($status === 'active') {
    $statusLabel = 'Aktiv';
    $statusClass = 'status-aktiv';
} else {
    $statusLabel = 'Afsluttet';
    $statusClass = 'status-afsluttet';
}

$approvals = is_string($entry['approvals'] ?? '') ? (json_decode($entry['approvals'], true) ?? []) : ($entry['approvals'] ?? []);
$oaApproved = (isset($approvals['opgaveansvarlig']) && $approvals['opgaveansvarlig'] === $today);
$driftApproved = (isset($approvals['drift']) && $approvals['drift'] === $today);
$entApproved = (isset($approvals['entreprenor']) && $approvals['entreprenor'] === $today);

$status_dag = $entry['status_dag'] ?? '';
$workStatusIcon = '';
$workStatusText = '';
if ($status_dag === 'aktiv_dag') {
    $workStatusIcon = '🔨';
    $workStatusText = 'Arbejder';
} elseif ($status_dag === 'pause_dag') {
    $workStatusIcon = '⏹️';
    $workStatusText = 'Stoppet';
}

$firma = $entry['entreprenor_firma'] ?? '';
$kontakt = $entry['entreprenor_kontakt'] ?? '';

// Calculate approval count
$approvalCount = 0;
if ($oaApproved) $approvalCount++;
if ($driftApproved) $approvalCount++;
if ($entApproved) $approvalCount++;

// Get approval history
$approval_history_raw = $entry['approval_history'] ?? '[]';
$approval_history = is_array($approval_history_raw) ? $approval_history_raw : (json_decode($approval_history_raw, true) ?? []);
$oaTimestamp = '';
$driftTimestamp = '';
$entTimestamp = '';

if (is_array($approval_history)) {
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

// Determine user's ability to approve
$canApproveOA = ($status === 'active') && ($role === 'admin' || $role === 'opgaveansvarlig') && !$oaApproved;
$canApproveDrift = ($status === 'active') && ($role === 'admin' || $role === 'drift') && !$driftApproved;
$canApproveEnt = ($status === 'active') && ($role === 'admin' || $role === 'entreprenor') && !$entApproved;
?>
<div class="work-permit-card" data-status="<?php echo htmlspecialchars($status); ?>" data-status-dag="<?php echo htmlspecialchars($status_dag); ?>" data-wo-id="<?php echo $entry['id']; ?>">
    <!-- Card Header with Status Badge -->
    <div class="card-header-modern">
        <div class="card-title-row">
            <h3 class="card-main-title">PTW <?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></h3>
            <span class="card-status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
            <?php if ($workStatusIcon): ?>
                <span id="card-work-status-icon-<?php echo $entry['id']; ?>" title="<?php echo htmlspecialchars($workStatusText); ?>" class="work-status-badge"><?php echo $workStatusIcon; ?></span>
            <?php else: ?>
                <span id="card-work-status-icon-<?php echo $entry['id']; ?>"></span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card-content-modern">
        <!-- Section 1: Basisinformation (Collapsible) -->
        <div class="card-section">
            <div class="section-header" onclick="toggleSection('basic-<?php echo $entry['id']; ?>')">
                <h4>📋 Basisinformation</h4>
                <span class="toggle-icon" id="toggle-basic-<?php echo $entry['id']; ?>">►</span>
            </div>
            <div class="section-content" id="basic-<?php echo $entry['id']; ?>">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">PTW Nr.</span>
                        <span class="info-value"><?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Indkøbsordre Nr.</span>
                        <span class="info-value"><?php echo htmlspecialchars($entry['p_number'] ?? ''); ?></span>
                    </div>
                    <div class="info-item full-width">
                        <span class="info-label">Beskrivelse</span>
                        <span class="info-value"><?php echo htmlspecialchars($entry['description'] ?? ''); ?></span>
                    </div>
                    <?php if (!empty($entry['p_description'])): ?>
                    <div class="info-item full-width">
                        <span class="info-label">Indkøbsordre beskrivelse</span>
                        <span class="info-value"><?php echo htmlspecialchars($entry['p_description']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">👤 Jobansvarlig</span>
                        <span class="info-value"><?php echo htmlspecialchars($entry['jobansvarlig'] ?? ''); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🏢 Entreprenør</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($firma); ?>
                            <?php if ($kontakt): ?>
                                <br><small style="color: #666;"><?php echo htmlspecialchars($kontakt); ?></small>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section 2: Godkendelsesproces (Collapsible) -->
        <div class="card-section">
            <div class="section-header" onclick="toggleSection('approval-<?php echo $entry['id']; ?>')">
                <h4>✅ Godkendelsesproces</h4>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="approval-count" id="approval-count-<?php echo $entry['id']; ?>">Godkendt <?php echo $approvalCount; ?>/3</span>
                    <span class="toggle-icon" id="toggle-approval-<?php echo $entry['id']; ?>">▼</span>
                </div>
            </div>
            <div class="section-content" id="approval-<?php echo $entry['id']; ?>">
                <div class="approval-list">
                    <!-- Step 1: Opgaveansvarlig -->
                    <div class="approval-step <?php echo $oaApproved ? 'approved' : 'pending'; ?>">
                        <div class="approval-icon">
                            <?php echo $oaApproved ? '<span class="check-icon">✓</span>' : '<span class="pending-icon">○</span>'; ?>
                        </div>
                        <div class="approval-info">
                            <div class="approval-name">Opgaveansvarlig</div>
                            <?php if ($oaApproved && $oaTimestamp): ?>
                                <div class="approval-date"><?php echo htmlspecialchars($oaTimestamp); ?></div>
                            <?php else: ?>
                                <div class="approval-status-text">Afventer godkendelse</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($canApproveOA): ?>
                            <button class="approval-btn ajax-approve-btn" 
                                    data-id="<?php echo $entry['id']; ?>" 
                                    data-role="opgaveansvarlig">
                                Godkend
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Step 2: Drift -->
                    <div class="approval-step <?php echo $driftApproved ? 'approved' : 'pending'; ?>">
                        <div class="approval-icon">
                            <?php echo $driftApproved ? '<span class="check-icon">✓</span>' : '<span class="pending-icon">○</span>'; ?>
                        </div>
                        <div class="approval-info">
                            <div class="approval-name">Drift</div>
                            <?php if ($driftApproved && $driftTimestamp): ?>
                                <div class="approval-date"><?php echo htmlspecialchars($driftTimestamp); ?></div>
                            <?php else: ?>
                                <div class="approval-status-text">Afventer godkendelse</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($canApproveDrift): ?>
                            <button class="approval-btn ajax-approve-btn" 
                                    data-id="<?php echo $entry['id']; ?>" 
                                    data-role="drift">
                                Godkend
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Step 3: Entreprenør -->
                    <div class="approval-step <?php echo $entApproved ? 'approved' : 'pending'; ?>">
                        <div class="approval-icon">
                            <?php echo $entApproved ? '<span class="check-icon">✓</span>' : '<span class="pending-icon">○</span>'; ?>
                        </div>
                        <div class="approval-info">
                            <div class="approval-name">Entreprenør</div>
                            <?php if ($entApproved && $entTimestamp): ?>
                                <div class="approval-date"><?php echo htmlspecialchars($entTimestamp); ?></div>
                            <?php else: ?>
                                <div class="approval-status-text">Afventer godkendelse</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($canApproveEnt): ?>
                            <button class="approval-btn ajax-approve-btn" 
                                    data-id="<?php echo $entry['id']; ?>" 
                                    data-role="entreprenor">
                                Godkend
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section 3: Tidsregistrering (Collapsible) -->
        <?php if ($modules['tidsregistrering'] && in_array($role, ['entreprenor', 'admin', 'opgaveansvarlig', 'drift'])): ?>
        <div class="card-section">
            <div class="section-header" onclick="toggleTimeTracking(<?php echo $entry['id']; ?>)">
                <h4>⏱️ Tidsregistrering</h4>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="time-summary" id="time-summary-<?php echo $entry['id']; ?>"></span>
                    <span class="toggle-icon" id="time-icon-<?php echo $entry['id']; ?>">▼</span>
                </div>
            </div>
            <div class="section-content time-entry-section" id="time-section-<?php echo $entry['id']; ?>">
                
                <?php if (in_array($role, ['admin', 'entreprenor'])): ?>
                <!-- Time entry form - only for admin and entreprenor -->
                <div class="time-entry-form">
                    <div class="time-input-group">
                        <label for="time-date-<?php echo $entry['id']; ?>">📅 Dato:</label>
                        <input type="date" 
                               id="time-date-<?php echo $entry['id']; ?>" 
                               class="time-date-input" 
                               value="<?php echo date('Y-m-d'); ?>"
                               max="<?php echo date('Y-m-d'); ?>"
                               onchange="loadTimeEntry(<?php echo $entry['id']; ?>, this.value)">
                    </div>
                    <div class="time-input-group">
                        <label for="time-hours-<?php echo $entry['id']; ?>">🕐 Timer:</label>
                        <input type="number" 
                               id="time-hours-<?php echo $entry['id']; ?>" 
                               class="time-hours-input" 
                               min="0" 
                               max="24" 
                               step="0.25" 
                               placeholder="0.00">
                    </div>
                    <div class="time-input-group full-width">
                        <label for="time-desc-<?php echo $entry['id']; ?>">📝 Beskrivelse (valgfri):</label>
                        <input type="text" 
                               id="time-desc-<?php echo $entry['id']; ?>" 
                               class="time-desc-input" 
                               placeholder="Beskrivelse af arbejdet...">
                    </div>
                    <div class="time-actions">
                        <button type="button" 
                                class="button button-success button-sm save-time-btn" 
                                onclick="saveTimeEntry(<?php echo $entry['id']; ?>)">
                            💾 Gem timer
                        </button>
                        <button type="button" 
                                class="button button-secondary button-sm show-all-times-btn" 
                                onclick="toggleTimeHistory(<?php echo $entry['id']; ?>)">
                            📊 Vis alle timer
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <!-- View-only for opgaveansvarlig and drift -->
                <div class="time-view-only">
                    <p class="time-view-notice">📋 Du kan se tidshistorik og totale timer, men ikke indtaste nye timer.</p>
                    <div class="time-actions">
                        <button type="button" 
                                class="button button-primary button-sm show-all-times-btn" 
                                onclick="toggleTimeHistory(<?php echo $entry['id']; ?>)">
                            📊 Vis tidshistorik og totale timer
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Time history section - available for all authorized roles -->
                <div class="time-history" id="time-history-<?php echo $entry['id']; ?>" style="display: none;">
                    <h5>📈 Tidshistorik</h5>
                    <div class="time-history-content">
                        <div class="loading">Indlæser...</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Section 4: Dokumentationsbilleder (Collapsible) -->
        <div class="card-section">
            <div class="section-header" onclick="toggleSection('images-<?php echo $entry['id']; ?>')">
                <h4>📷 Dokumentationsbilleder</h4>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="images-count">
                        <?php 
                        $completionImages = $entry['completion_images'] ?? [];
                        if (is_string($completionImages)) {
                            $completionImages = json_decode($completionImages, true) ?? [];
                        }
                        $imageCount = count($completionImages);
                        echo $imageCount > 0 ? $imageCount . ' billede' . ($imageCount > 1 ? 'r' : '') : 'Ingen billeder';
                        ?>
                    </span>
                    <span class="toggle-icon" id="toggle-images-<?php echo $entry['id']; ?>">▼</span>
                </div>
            </div>
            <div class="section-content" id="images-<?php echo $entry['id']; ?>">
                <?php if ($role === 'entreprenor' && $status === 'active'): ?>
                    <!-- Upload form for entrepreneurs -->
                    <div class="image-upload-section">
                        <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.75rem;">
                            📤 Upload dokumentationsbilleder til denne PTW
                        </p>
                        <form method="POST" enctype="multipart/form-data" class="upload-form-card">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="wo_id" value="<?php echo $entry['id']; ?>">
                            <div class="upload-input-group">
                                <input type="file" 
                                       name="completion_image" 
                                       id="upload-file-<?php echo $entry['id']; ?>"
                                       accept="image/*" 
                                       required 
                                       class="file-input">
                                <label for="upload-file-<?php echo $entry['id']; ?>" class="file-label">
                                    📁 Vælg billede
                                </label>
                            </div>
                            <button type="submit" name="upload_image" value="1" class="button button-success button-sm">
                                📤 Upload billede
                            </button>
                        </form>
                        <p style="color: #999; font-size: 0.75rem; margin-top: 0.5rem;">
                            💡 Accepterede formater: JPEG, PNG, GIF, WebP, AVIF (max 50MB)
                        </p>
                    </div>
                <?php else: ?>
                    <!-- View-only message for non-entrepreneurs -->
                    <p class="upload-restricted-notice">
                        <?php if ($status !== 'active'): ?>
                            ℹ️ Upload af billeder er kun muligt for aktive PTW'er.
                        <?php else: ?>
                            ℹ️ Kun entreprenører kan uploade dokumentationsbilleder.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Card Actions (Bottom Buttons) -->
    <div class="card-actions">
        <a class="button button-secondary button-sm handlinger-btn" href="print_wo.php?id=<?php echo urlencode($entry['id']); ?>">Vis</a>
        <?php if ($role !== 'entreprenor'): ?>
            <a class="button button-sm handlinger-btn" href="create_wo.php?id=<?php echo urlencode($entry['id']); ?>">Rediger</a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
            <a class="button button-danger button-sm handlinger-btn" href="view_wo.php?delete_id=<?php echo urlencode($entry['id']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne PTW??');">Slet</a>
        <?php endif; ?>
        <?php if ($role === 'entreprenor' && $status === 'active' && $status_dag === 'aktiv_dag'): ?>
            <button id="card-work-btn-<?php echo $entry['id']; ?>" class="button button-warning button-sm" onclick="updateWorkStatus(<?php echo $entry['id']; ?>, 'stopped')">⏹️ Stop arbejde for i dag</button>
        <?php endif; ?>
    </div>
</div>
