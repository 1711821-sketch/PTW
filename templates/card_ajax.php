<?php
/**
 * Card template for AJAX infinite scroll
 * This file is included from view_wo.php to generate card HTML for AJAX responses
 *
 * Expected variables:
 * - $entry: array with work order data
 * - $status, $statusLabel, $statusClass
 * - $approvals, $oaApproved, $driftApproved, $entApproved
 * - $status_dag, $firma, $kontakt
 * - $today, $role, $csrf_token
 */
?>
<div class="work-permit-card" data-status="<?php echo htmlspecialchars($status); ?>" data-status-dag="<?php echo htmlspecialchars($status_dag); ?>">
    <!-- Card Header -->
    <div class="card-header">
        <div class="card-title-section">
            <div class="card-title-text">
                <h3>PTW <?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></h3>
                <span class="card-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
            </div>
            <?php if (!empty($entry['description'])): ?>
                <p class="card-header-description" id="desc-<?php echo $entry['id']; ?>">
                    <?php echo htmlspecialchars($entry['description']); ?>
                </p>
                <?php if (strlen($entry['description']) > 100): ?>
                    <span class="description-toggle" id="desc-toggle-<?php echo $entry['id']; ?>" onclick="toggleDescription(<?php echo $entry['id']; ?>)">Vis mere</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <hr class="card-divider">

    <!-- Card Content -->
    <div class="card-content">
        <!-- Details Grid -->
        <div class="card-details">
            <div class="detail-item">
                <span class="detail-label">Firma:</span>
                <span class="detail-value"><?php echo htmlspecialchars($firma ?: 'Ikke angivet'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Kontakt:</span>
                <span class="detail-value"><?php echo htmlspecialchars($kontakt ?: 'Ikke angivet'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Oprettet:</span>
                <span class="detail-value"><?php echo isset($entry['created_at']) ? date('d/m/Y', strtotime($entry['created_at'])) : 'Ukendt'; ?></span>
            </div>
            <?php if (!empty($entry['jobansvarlig'])): ?>
            <div class="detail-item">
                <span class="detail-label">Jobansvarlig:</span>
                <span class="detail-value"><?php echo htmlspecialchars($entry['jobansvarlig']); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Approvals Section -->
        <div class="card-approvals">
            <div class="card-approvals-header" onclick="toggleApprovals(<?php echo $entry['id']; ?>)">
                <h4>Godkendelser</h4>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="approvals-summary">
                        <?php
                        $approvedCount = ($oaApproved ? 1 : 0) + ($driftApproved ? 1 : 0) + ($entApproved ? 1 : 0);
                        echo $approvedCount . '/3 godkendt';
                        ?>
                    </span>
                    <span class="toggle-icon" id="approvals-icon-<?php echo $entry['id']; ?>">▼</span>
                </div>
            </div>
            <div class="approval-grid" id="approval-grid-<?php echo $entry['id']; ?>">
                <div class="approval-item">
                    <span class="approval-label">Opgaveansvarlig:</span>
                    <?php if ($oaApproved): ?>
                        <span class="approval-status approved">✓ Godkendt</span>
                    <?php else: ?>
                        <span class="approval-status pending">Afventer</span>
                    <?php endif; ?>
                </div>
                <div class="approval-item">
                    <span class="approval-label">Drift:</span>
                    <?php if ($driftApproved): ?>
                        <span class="approval-status approved">✓ Godkendt</span>
                    <?php else: ?>
                        <span class="approval-status pending">Afventer</span>
                    <?php endif; ?>
                </div>
                <div class="approval-item">
                    <span class="approval-label">Entreprenør:</span>
                    <?php if ($entApproved): ?>
                        <span class="approval-status approved">✓ Godkendt</span>
                    <?php else: ?>
                        <span class="approval-status pending">Afventer</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Actions -->
    <div class="card-actions">
        <a class="button button-secondary button-sm" href="print_wo.php?id=<?php echo urlencode($entry['id']); ?>">Vis</a>
        <?php if ($role !== 'entreprenor'): ?>
            <a class="button button-sm" href="create_wo.php?id=<?php echo urlencode($entry['id']); ?>">Rediger</a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
            <a class="button button-danger button-sm" href="view_wo.php?delete_id=<?php echo urlencode($entry['id']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne PTW?');">Slet</a>
        <?php endif; ?>
    </div>
</div>
