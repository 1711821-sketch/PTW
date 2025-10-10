<?php
/**
 * Approval Workflow Widget
 * Displays a visual sequential approval process for PTW work orders
 * 
 * Shows: Opgaveansvarlig → Drift → Entreprenør
 * with color-coded status indicators and timestamps
 */

function renderApprovalWorkflowWidget($entry, $currentUserRole, $today, $compact = false) {
    $approvals = $entry['approvals'] ?? [];
    $approval_history = $entry['approval_history'] ?? [];
    
    // Get approval statuses for today
    $oaApproved = isset($approvals['opgaveansvarlig']) && $approvals['opgaveansvarlig'] === $today;
    $driftApproved = isset($approvals['drift']) && $approvals['drift'] === $today;
    $entApproved = isset($approvals['entreprenor']) && $approvals['entreprenor'] === $today;
    
    // Get timestamps from approval history
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
    $canApproveOA = ($currentUserRole === 'admin' || $currentUserRole === 'opgaveansvarlig') && !$oaApproved;
    $canApproveDrift = ($currentUserRole === 'admin' || $currentUserRole === 'drift') && !$driftApproved;
    $canApproveEnt = ($currentUserRole === 'admin' || $currentUserRole === 'entreprenor') && !$entApproved;
    
    // Determine step state (approved, current_user, pending)
    $oaState = $oaApproved ? 'approved' : ($canApproveOA ? 'current_user' : 'pending');
    $driftState = $driftApproved ? 'approved' : ($canApproveDrift ? 'current_user' : 'pending');
    $entState = $entApproved ? 'approved' : ($canApproveEnt ? 'current_user' : 'pending');
    
    $widgetId = 'approval-workflow-' . ($entry['id'] ?? uniqid());
    
    // Calculate approval count for status summary
    $approvalCount = 0;
    if ($oaApproved) $approvalCount++;
    if ($driftApproved) $approvalCount++;
    if ($entApproved) $approvalCount++;
    
    $statusText = "Godkendt {$approvalCount}/3";
    
    ?>
    <div class="card-approval-workflow">
        <div class="card-approval-header" onclick="toggleApprovalWorkflow(<?php echo $entry['id']; ?>)">
            <h4>✅ Godkendelsesproces</h4>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="approval-summary"><?php echo $statusText; ?></span>
                <span class="toggle-icon" id="approval-icon-<?php echo $entry['id']; ?>">▼</span>
            </div>
        </div>
        <div class="approval-workflow-section" id="approval-section-<?php echo $entry['id']; ?>">
            <div class="approval-workflow-widget <?php echo $compact ? 'compact' : ''; ?>" id="<?php echo $widgetId; ?>">
                <div class="workflow-steps">
            <!-- Step 1: Opgaveansvarlig -->
            <div class="workflow-step <?php echo $oaState; ?>">
                <div class="step-icon">
                    <?php if ($oaApproved): ?>
                        ✅
                    <?php elseif ($canApproveOA): ?>
                        👤
                    <?php else: ?>
                        ⏳
                    <?php endif; ?>
                </div>
                <div class="step-content">
                    <div class="step-title" data-short="OA">Opgaveansvarlig</div>
                    <?php if ($oaApproved && $oaTimestamp && !$compact): ?>
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
            <div class="workflow-arrow <?php echo $oaApproved ? 'active' : ''; ?>">
                →
            </div>
            
            <!-- Step 2: Drift -->
            <div class="workflow-step <?php echo $driftState; ?>">
                <div class="step-icon">
                    <?php if ($driftApproved): ?>
                        ✅
                    <?php elseif ($canApproveDrift): ?>
                        👤
                    <?php else: ?>
                        ⏳
                    <?php endif; ?>
                </div>
                <div class="step-content">
                    <div class="step-title" data-short="Drift">Drift</div>
                    <?php if ($driftApproved && $driftTimestamp && !$compact): ?>
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
            <div class="workflow-arrow <?php echo $driftApproved ? 'active' : ''; ?>">
                →
            </div>
            
            <!-- Step 3: Entreprenør -->
            <div class="workflow-step <?php echo $entState; ?>">
                <div class="step-icon">
                    <?php if ($entApproved): ?>
                        ✅
                    <?php elseif ($canApproveEnt): ?>
                        👤
                    <?php else: ?>
                        ⏳
                    <?php endif; ?>
                </div>
                <div class="step-content">
                    <div class="step-title" data-short="Ent">Entreprenør</div>
                    <?php if ($entApproved && $entTimestamp && !$compact): ?>
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
    
    <style>
        /* Accordion container for approval workflow */
        .card-approval-workflow {
            padding: 0;
            margin-bottom: 0.75rem;
        }

        .card-approval-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background: rgba(16, 185, 129, 0.03);
            border-radius: 6px;
            cursor: pointer;
            user-select: none;
        }
        
        .card-approval-header:hover {
            background: rgba(16, 185, 129, 0.06);
        }

        .card-approval-workflow h4 {
            margin: 0;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        
        .approval-summary {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .approval-workflow-section {
            display: none;
            padding: 0.5rem 0;
            margin-top: 0.5rem;
        }
        
        .approval-workflow-section.expanded {
            display: block;
        }
        
        .approval-workflow-widget {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            padding: 1.25rem;
            margin: 1rem 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .approval-workflow-widget.compact {
            padding: 0.75rem;
            margin: 0.5rem 0;
        }
        
        .workflow-steps {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding: 0.5rem 0;
        }
        
        .workflow-step {
            flex: 1;
            min-width: 140px;
            background: white;
            border-radius: 10px;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
        }
        
        /* State: Approved (Green) */
        .workflow-step.approved {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-color: #10b981;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }
        
        /* State: Current User Can Approve (Blue) */
        .workflow-step.current_user {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        /* State: Pending (Gray) */
        .workflow-step.pending {
            background: #f1f5f9;
            border-color: #cbd5e1;
            opacity: 0.7;
        }
        
        .step-icon {
            font-size: 2rem;
            line-height: 1;
        }
        
        .step-content {
            text-align: center;
            width: 100%;
        }
        
        .step-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }
        
        .step-timestamp {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .step-status {
            font-size: 0.75rem;
            color: #94a3b8;
            font-style: italic;
        }
        
        .step-approve-btn {
            margin-top: 0.5rem;
            padding: 0.4rem 0.8rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }
        
        .step-approve-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .step-approve-btn:active {
            transform: translateY(0);
        }
        
        .workflow-arrow {
            font-size: 1.5rem;
            color: #cbd5e1;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .workflow-arrow.active {
            color: #10b981;
            font-weight: bold;
        }
        
        /* Ultra-compact mobile responsive */
        @media (max-width: 768px) {
            .approval-workflow-widget {
                padding: 0.5rem;
                margin: 0.5rem 0;
                border-radius: 8px;
            }
            
            .approval-workflow-widget.compact {
                padding: 0.4rem;
                margin: 0.25rem 0;
            }
            
            .workflow-steps {
                gap: 2px;
                padding: 0.25rem 0;
            }
            
            .workflow-step {
                flex: 0 0 29%;
                min-width: 0;
                max-width: 29%;
                padding: 0.3rem 0.1rem;
                border-width: 1px;
                border-radius: 6px;
                gap: 0.2rem;
            }
            
            .step-icon {
                font-size: 1rem;
            }
            
            /* Use shortened names on mobile */
            .step-title {
                text-indent: -9999px;
                overflow: hidden;
                white-space: nowrap;
                line-height: 1.1;
                margin-bottom: 0;
                font-weight: 700;
                position: relative;
                height: 0.9rem;
            }
            
            .step-title::before {
                content: attr(data-short);
                font-size: 0.65rem;
                text-indent: 0;
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                white-space: nowrap;
            }
            
            /* Hide timestamps and status text on mobile to save space */
            .step-timestamp,
            .step-status {
                display: none;
            }
            
            .workflow-arrow {
                font-size: 0.7rem;
                margin: 0 -2px;
                flex-shrink: 0;
            }
            
            .step-approve-btn {
                font-size: 0;
                padding: 0.4rem 0.3rem;
                margin-top: 0.25rem;
                border-radius: 4px;
                min-height: 32px;
                font-weight: 700;
            }
            
            /* Use checkmark icon instead of text on mobile */
            .step-approve-btn::before {
                content: "✓";
                font-size: 1.1rem;
                line-height: 1;
            }
            
            /* Reduce pulse animation on mobile */
            .workflow-step.current_user {
                animation: pulse-mobile 2s ease-in-out infinite;
            }
            
            @keyframes pulse-mobile {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.03); }
            }
        }
        
        /* Compact mode for card views */
        .approval-workflow-widget.compact .workflow-step {
            min-width: 90px;
            padding: 0.5rem 0.3rem;
        }
        
        .approval-workflow-widget.compact .step-icon {
            font-size: 1.3rem;
        }
        
        .approval-workflow-widget.compact .step-title {
            font-size: 0.75rem;
        }
        
        .approval-workflow-widget.compact .workflow-arrow {
            font-size: 1rem;
        }
        
        @media print {
            .approval-workflow-widget {
                break-inside: avoid;
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .step-approve-btn {
                display: none;
            }
        }
    </style>
    <?php
}
