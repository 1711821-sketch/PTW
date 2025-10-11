<?php
session_start();
require_once 'database.php';

// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['user'];
$role = $_SESSION['role'] ?? 'user';
$isEntrepreneur = (strtolower($role) === 'entreprenor');

try {
    $db = Database::getInstance();
    
    // === ENTREPRENEUR DASHBOARD - Different view for contractors ===
    if ($isEntrepreneur) {
        $firma = $_SESSION['entreprenor_firma'] ?? '';
        
        $entrepreneurPTWs = $db->fetchAll("
            SELECT 
                wo.*,
                COALESCE(SUM(te.hours), 0) as total_hours
            FROM work_orders wo
            LEFT JOIN time_entries te ON wo.id = te.work_order_id
            WHERE wo.entreprenor_firma = ?
            GROUP BY wo.id
            ORDER BY 
                CASE 
                    WHEN wo.status = 'active' THEN 1
                    WHEN wo.status = 'planning' THEN 2
                    WHEN wo.status = 'completed' THEN 3
                    ELSE 4
                END,
                wo.created_at DESC
        ", [$firma]);
        
        $planningPTWs = array_filter($entrepreneurPTWs, fn($ptw) => $ptw['status'] === 'planning');
        $activePTWs = array_filter($entrepreneurPTWs, fn($ptw) => $ptw['status'] === 'active');
        $completedPTWs = array_filter($entrepreneurPTWs, fn($ptw) => $ptw['status'] === 'completed');
    }
    
    // === CORE STATISTICS QUERIES (for non-entrepreneurs) ===
    
    // 1. Work Order Statistics
    $totalWorkOrders = $db->fetch("SELECT COUNT(*) as count FROM work_orders")['count'];
    $activeWorkOrders = $db->fetch("SELECT COUNT(*) as count FROM work_orders WHERE status = 'active'")['count'];
    $completedWorkOrders = $db->fetch("SELECT COUNT(*) as count FROM work_orders WHERE status = 'completed'")['count'];
    
    // 2. Work Orders by Status
    $statusData = $db->fetchAll("
        SELECT status, COUNT(*) as count 
        FROM work_orders 
        GROUP BY status 
        ORDER BY count DESC
    ");
    
    // 3. Recent Work Orders (last 30 days)
    $recentWorkOrders = $db->fetchAll("
        SELECT work_order_no, description, status, created_at, entreprenor_firma
        FROM work_orders 
        WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    // 4. Work Orders by Entrepreneur
    $entrepreneurStats = $db->fetchAll("
        SELECT 
            entreprenor_firma as firma,
            COUNT(*) as total_work_orders,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_orders,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders
        FROM work_orders 
        WHERE entreprenor_firma IS NOT NULL AND entreprenor_firma != ''
        GROUP BY entreprenor_firma 
        ORDER BY total_work_orders DESC
    ");
    
    // 5. Time Entry Statistics
    $totalHours = $db->fetch("SELECT COALESCE(SUM(hours), 0) as total FROM time_entries")['total'];
    $totalEntriesThisMonth = $db->fetch("
        SELECT COUNT(*) as count 
        FROM time_entries 
        WHERE entry_date >= DATE_TRUNC('month', CURRENT_DATE)
    ")['count'];
    
    // 6. Top Time Contributors
    $topContributors = $db->fetchAll("
        SELECT 
            u.username,
            u.entreprenor_firma,
            SUM(te.hours) as total_hours,
            COUNT(te.id) as total_entries
        FROM time_entries te 
        JOIN users u ON te.user_id = u.id 
        GROUP BY u.id, u.username, u.entreprenor_firma
        ORDER BY total_hours DESC 
        LIMIT 5
    ");
    
    // 7. User Statistics
    $totalUsers = $db->fetch("SELECT COUNT(*) as count FROM users")['count'];
    $activeUsersThisMonth = $db->fetch("
        SELECT COUNT(DISTINCT te.user_id) as count 
        FROM time_entries te 
        WHERE te.entry_date >= DATE_TRUNC('month', CURRENT_DATE)
    ")['count'];
    
    // 8. SJA Statistics (from PostgreSQL database)
    $sjaStats = $db->fetch("
        SELECT 
            COUNT(DISTINCT wo.id) as total_work_orders,
            COUNT(DISTINCT sja.work_order_id) as work_orders_with_sja,
            COUNT(sja.id) as total_sjas
        FROM work_orders wo
        LEFT JOIN sja_entries sja ON wo.id = sja.work_order_id
    ");
    
    $totalSJAs = $sjaStats ? $sjaStats['total_sjas'] : 0;
    $workOrdersWithSJA = $sjaStats ? $sjaStats['work_orders_with_sja'] : 0;
    
    // Ensure SJA compliance rate cannot exceed 100% by using distinct work order count
    $sjaComplianceRate = $totalWorkOrders > 0 ? round(($workOrdersWithSJA / $totalWorkOrders) * 100, 1) : 0;
    $sjaComplianceRate = min($sjaComplianceRate, 100); // Cap at 100%
    
    // 9. Safety Statistics - Days since last accident
    $accidentData = $db->fetch("SELECT *, CURRENT_DATE - last_accident_date as days_since_accident FROM accident_counter LIMIT 1");
    $daysSinceAccident = $accidentData ? $accidentData['days_since_accident'] : 0;
    
} catch (Exception $e) {
    // Fallback values if database query fails
    if ($isEntrepreneur) {
        $entrepreneurPTWs = [];
        $planningPTWs = [];
        $activePTWs = [];
        $completedPTWs = [];
    }
    $totalWorkOrders = 0;
    $activeWorkOrders = 0;
    $completedWorkOrders = 0;
    $statusData = [];
    $recentWorkOrders = [];
    $entrepreneurStats = [];
    $totalHours = 0;
    $totalEntriesThisMonth = 0;
    $topContributors = [];
    $totalUsers = 0;
    $activeUsersThisMonth = 0;
    $totalSJAs = 0;
    $workOrdersWithSJA = 0;
    $sjaComplianceRate = 0;
    $daysSinceAccident = 0;
    $error_message = "Database fejl: " . $e->getMessage();
}

// Prepare data for JavaScript charts
$statusLabels = array_column($statusData, 'status');
$statusCounts = array_column($statusData, 'count');
$entrepreneurLabels = array_column($entrepreneurStats, 'firma');
$entrepreneurCounts = array_column($entrepreneurStats, 'total_work_orders');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - PTW System</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Modern Dashboard Specific Styles */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .kpi-card {
            background: linear-gradient(135deg, var(--background-primary) 0%, var(--background-secondary) 100%);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }
        
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-color);
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .kpi-card.success::before { background: var(--success-color); }
        .kpi-card.warning::before { background: var(--warning-color); }
        .kpi-card.danger::before { background: var(--danger-color); }
        .kpi-card.info::before { background: var(--primary-light); }
        
        .kpi-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }
        
        .kpi-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0.5rem 0;
            line-height: 1;
        }
        
        .kpi-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .kpi-subtitle {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }
        
        .chart-card {
            background: var(--background-primary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
        }
        
        .chart-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .recent-activity {
            background: var(--background-primary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-light);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 0.25rem 0;
        }
        
        .activity-details {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .safety-highlight {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            text-align: center;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin: 2rem 0;
            box-shadow: var(--shadow-lg);
        }
        
        .safety-days {
            font-size: 3rem;
            font-weight: 900;
            margin: 0.5rem 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .progress-ring {
            width: 120px;
            height: 120px;
            margin: 0 auto 1rem;
        }
        
        .progress-ring svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }
        
        .progress-ring circle {
            fill: transparent;
            stroke-width: 8;
        }
        
        .progress-ring .background {
            stroke: var(--border-color);
        }
        
        .progress-ring .progress {
            stroke: var(--success-color);
            stroke-linecap: round;
            transition: stroke-dashoffset 1s ease;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-light);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Entrepreneur Dashboard Specific Styles */
        .entrepreneur-dashboard {
            padding: 1rem 0;
        }
        
        .ptw-section {
            margin-bottom: 2rem;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-light);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .section-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .section-badge.planning {
            background: var(--warning-color);
        }
        
        .section-badge.active {
            background: var(--success-color);
        }
        
        .section-badge.completed {
            background: var(--text-light);
        }
        
        .ptw-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .ptw-card {
            background: var(--background-primary);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .ptw-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }
        
        .ptw-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .ptw-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }
        
        .ptw-hours {
            background: var(--primary-light);
            color: white;
            padding: 0.4rem 0.75rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .ptw-description {
            color: var(--text-primary);
            margin: 0.5rem 0;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        
        .ptw-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .ptw-meta-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .ptw-meta-icon {
            opacity: 0.7;
        }
        
        .empty-section {
            text-align: center;
            padding: 2rem 1rem;
            background: var(--background-secondary);
            border-radius: var(--radius-lg);
            color: var(--text-light);
        }
        
        .empty-section-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .kpi-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 0.75rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .kpi-number {
                font-size: 2rem;
            }
            
            .safety-days {
                font-size: 2rem;
            }
            
            .ptw-card {
                padding: 1rem;
            }
            
            .ptw-card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .ptw-hours {
                align-self: flex-end;
            }
            
            .ptw-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .section-header {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <button class="navbar-toggle" aria-label="Toggle navigation">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <div class="navbar-links">
            <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                <a href="create_wo.php">Opret PTW</a>
            <?php endif; ?>
            <a href="view_wo.php">PTW-oversigt</a>
            <a href="map_wo.php">Kort</a>
            <a href="dashboard.php" class="active">Dashboard</a>
            <?php if ($role === 'admin'): ?>
                <a href="time_overblik.php">Timeoverblik</a>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span class="nav-user">Logget ind som <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($isEntrepreneur): ?>
            <h1>📋 Mine PTW'er</h1>
            <p>Oversigt over alle PTW'er for <?= htmlspecialchars($firma ?? 'dit firma') ?></p>
        <?php else: ?>
            <h1>📊 System Dashboard</h1>
            <p>Overblik over PTW'er, sikkerhed og aktivitet</p>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <strong>Advarsel:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($isEntrepreneur): ?>
            <!-- ENTREPRENEUR DASHBOARD -->
            <div class="entrepreneur-dashboard">
                
                <!-- Active PTWs -->
                <div class="ptw-section">
                    <div class="section-header">
                        <h2 class="section-title">🟢 Aktive PTW'er</h2>
                        <span class="section-badge active"><?= count($activePTWs) ?></span>
                    </div>
                    <?php if (!empty($activePTWs)): ?>
                        <div class="ptw-list">
                            <?php foreach ($activePTWs as $ptw): ?>
                                <a href="view_wo.php?id=<?= $ptw['id'] ?>" class="ptw-card">
                                    <div class="ptw-card-header">
                                        <h3 class="ptw-number">PTW <?= htmlspecialchars($ptw['work_order_no']) ?></h3>
                                        <div class="ptw-hours">⏱️ <?= number_format($ptw['total_hours'], 1) ?> timer</div>
                                    </div>
                                    <p class="ptw-description"><?= htmlspecialchars($ptw['description'] ?? 'Ingen beskrivelse') ?></p>
                                    <div class="ptw-meta">
                                        <div class="ptw-meta-item">
                                            <span class="ptw-meta-icon">👷</span>
                                            <span><?= htmlspecialchars($ptw['jobansvarlig'] ?? 'Ikke tildelt') ?></span>
                                        </div>
                                        <div class="ptw-meta-item">
                                            <span class="ptw-meta-icon">📅</span>
                                            <span><?= date('d/m/Y', strtotime($ptw['created_at'])) ?></span>
                                        </div>
                                        <?php if (!empty($ptw['components'])): ?>
                                            <div class="ptw-meta-item">
                                                <span class="ptw-meta-icon">🔧</span>
                                                <span><?= htmlspecialchars($ptw['components']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-section">
                            <div class="empty-section-icon">📋</div>
                            <p>Ingen aktive PTW'er i øjeblikket</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Planning PTWs -->
                <div class="ptw-section">
                    <div class="section-header">
                        <h2 class="section-title">📝 PTW'er i Planlægning</h2>
                        <span class="section-badge planning"><?= count($planningPTWs) ?></span>
                    </div>
                    <?php if (!empty($planningPTWs)): ?>
                        <div class="ptw-list">
                            <?php foreach ($planningPTWs as $ptw): ?>
                                <a href="view_wo.php?id=<?= $ptw['id'] ?>" class="ptw-card">
                                    <div class="ptw-card-header">
                                        <h3 class="ptw-number">PTW <?= htmlspecialchars($ptw['work_order_no']) ?></h3>
                                        <div class="ptw-hours">⏱️ <?= number_format($ptw['total_hours'], 1) ?> timer</div>
                                    </div>
                                    <p class="ptw-description"><?= htmlspecialchars($ptw['description'] ?? 'Ingen beskrivelse') ?></p>
                                    <div class="ptw-meta">
                                        <div class="ptw-meta-item">
                                            <span class="ptw-meta-icon">👷</span>
                                            <span><?= htmlspecialchars($ptw['jobansvarlig'] ?? 'Ikke tildelt') ?></span>
                                        </div>
                                        <div class="ptw-meta-item">
                                            <span class="ptw-meta-icon">📅</span>
                                            <span><?= date('d/m/Y', strtotime($ptw['created_at'])) ?></span>
                                        </div>
                                        <?php if (!empty($ptw['components'])): ?>
                                            <div class="ptw-meta-item">
                                                <span class="ptw-meta-icon">🔧</span>
                                                <span><?= htmlspecialchars($ptw['components']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-section">
                            <div class="empty-section-icon">📝</div>
                            <p>Ingen PTW'er i planlægning</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Completed PTWs -->
                <div class="ptw-section">
                    <div class="section-header">
                        <h2 class="section-title">✅ Afsluttede PTW'er</h2>
                        <span class="section-badge completed"><?= count($completedPTWs) ?></span>
                    </div>
                    <?php if (!empty($completedPTWs)): ?>
                        <div class="ptw-list">
                            <?php foreach ($completedPTWs as $ptw): ?>
                                <a href="view_wo.php?id=<?= $ptw['id'] ?>" class="ptw-card">
                                    <div class="ptw-card-header">
                                        <h3 class="ptw-number">PTW <?= htmlspecialchars($ptw['work_order_no']) ?></h3>
                                        <div class="ptw-hours">⏱️ <?= number_format($ptw['total_hours'], 1) ?> timer</div>
                                    </div>
                                    <p class="ptw-description"><?= htmlspecialchars($ptw['description'] ?? 'Ingen beskrivelse') ?></p>
                                    <div class="ptw-meta">
                                        <div class="ptw-meta-item">
                                            <span class="ptw-meta-icon">👷</span>
                                            <span><?= htmlspecialchars($ptw['jobansvarlig'] ?? 'Ikke tildelt') ?></span>
                                        </div>
                                        <div class="ptw-meta-item">
                                            <span class="ptw-meta-icon">📅</span>
                                            <span><?= date('d/m/Y', strtotime($ptw['created_at'])) ?></span>
                                        </div>
                                        <?php if (!empty($ptw['components'])): ?>
                                            <div class="ptw-meta-item">
                                                <span class="ptw-meta-icon">🔧</span>
                                                <span><?= htmlspecialchars($ptw['components']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-section">
                            <div class="empty-section-icon">✅</div>
                            <p>Ingen afsluttede PTW'er endnu</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        <?php else: ?>
            <!-- STANDARD DASHBOARD FOR OTHER ROLES -->

        <!-- Safety Highlight -->
        <div class="safety-highlight">
            <h2 style="margin: 0; font-size: 1.5rem;">🛡️ SIKKERHEDSREKORD</h2>
            <div class="safety-days"><?= $daysSinceAccident ?></div>
            <div style="font-size: 1.1rem; font-weight: 600;">DAGE SIDEN SIDSTE UHELD</div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card info">
                <div class="kpi-icon">📋</div>
                <div class="kpi-number"><?= $totalWorkOrders ?></div>
                <div class="kpi-label">Total PTW'er</div>
                <div class="kpi-subtitle">Alle registrerede</div>
            </div>
            
            <div class="kpi-card success">
                <div class="kpi-icon">🟢</div>
                <div class="kpi-number"><?= $activeWorkOrders ?></div>
                <div class="kpi-label">Aktive PTW'er</div>
                <div class="kpi-subtitle">I gang nu</div>
            </div>
            
            <div class="kpi-card warning">
                <div class="kpi-icon">✅</div>
                <div class="kpi-number"><?= $completedWorkOrders ?></div>
                <div class="kpi-label">Fuldførte</div>
                <div class="kpi-subtitle">Afsluttede opgaver</div>
            </div>
            
            <div class="kpi-card <?= $sjaComplianceRate >= 80 ? 'success' : ($sjaComplianceRate >= 50 ? 'warning' : 'danger') ?>">
                <div class="kpi-icon">🛡️</div>
                <div class="kpi-number"><?= $sjaComplianceRate ?>%</div>
                <div class="kpi-label">SJA Compliance</div>
                <div class="kpi-subtitle"><?= $workOrdersWithSJA ?> af <?= $totalWorkOrders ?> har SJA</div>
            </div>
            
            <div class="kpi-card info">
                <div class="kpi-icon">⏱️</div>
                <div class="kpi-number"><?= number_format($totalHours, 1) ?></div>
                <div class="kpi-label">Total Timer</div>
                <div class="kpi-subtitle">Registreret tid</div>
            </div>
            
            <div class="kpi-card success">
                <div class="kpi-icon">👥</div>
                <div class="kpi-number"><?= $activeUsersThisMonth ?></div>
                <div class="kpi-label">Aktive Brugere</div>
                <div class="kpi-subtitle">Denne måned</div>
            </div>
        </div>

        <!-- Charts and Analytics -->
        <div class="dashboard-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">📊 Status Distribution</h3>
                </div>
                <?php if (!empty($statusData)): ?>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📊</div>
                        <p>Ingen statusdata tilgængelig endnu</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">🏢 Entreprenører</h3>
                </div>
                <?php if (!empty($entrepreneurStats)): ?>
                    <div class="chart-container">
                        <canvas id="entrepreneurChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🏢</div>
                        <p>Ingen entreprenørdata endnu</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity and Top Contributors -->
        <div class="dashboard-grid">
            <div class="recent-activity">
                <div class="chart-header">
                    <h3 class="chart-title">🕒 Seneste PTW'er</h3>
                </div>
                <?php if (!empty($recentWorkOrders)): ?>
                    <?php foreach ($recentWorkOrders as $wo): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: var(--primary-light); color: white;">
                                📋
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?= htmlspecialchars($wo['work_order_no'] ?? 'Ingen WO#') ?>
                                </div>
                                <div class="activity-details">
                                    <?= htmlspecialchars(substr($wo['description'] ?? 'Ingen beskrivelse', 0, 60)) ?><?= strlen($wo['description'] ?? '') > 60 ? '...' : '' ?>
                                    <br>
                                    <strong><?= htmlspecialchars($wo['entreprenor_firma'] ?? 'Ingen firma') ?></strong> • 
                                    <?= date('d/m/Y', strtotime($wo['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📋</div>
                        <p>Ingen seneste aktivitet</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="recent-activity">
                <div class="chart-header">
                    <h3 class="chart-title">🏆 Top Timeregistrering</h3>
                </div>
                <?php if (!empty($topContributors)): ?>
                    <?php foreach ($topContributors as $index => $contributor): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: var(--success-color); color: white;">
                                #<?= $index + 1 ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?= htmlspecialchars($contributor['username']) ?>
                                </div>
                                <div class="activity-details">
                                    <?= number_format($contributor['total_hours'], 1) ?> timer • 
                                    <?= $contributor['total_entries'] ?> registreringer
                                    <br>
                                    <strong><?= htmlspecialchars($contributor['entreprenor_firma'] ?? 'Intern') ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">⏱️</div>
                        <p>Ingen timeregistreringer endnu</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Chart.js Configuration and Data
        const chartColors = {
            primary: '#1e40af',
            success: '#22c55e',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6',
            secondary: '#10b981'
        };

        // Status Distribution Pie Chart
        <?php if (!empty($statusData)): ?>
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_map('ucfirst', $statusLabels)) ?>,
                    datasets: [{
                        data: <?= json_encode($statusCounts) ?>,
                        backgroundColor: [
                            chartColors.success,
                            chartColors.warning,
                            chartColors.danger,
                            chartColors.info
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>

        // Entrepreneur Bar Chart
        <?php if (!empty($entrepreneurStats)): ?>
        const entrepreneurCtx = document.getElementById('entrepreneurChart');
        if (entrepreneurCtx) {
            new Chart(entrepreneurCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_map(function($label) { 
                        return strlen($label) > 15 ? substr($label, 0, 15) . '...' : $label; 
                    }, $entrepreneurLabels)) ?>,
                    datasets: [{
                        label: 'PTW\'er',
                        data: <?= json_encode($entrepreneurCounts) ?>,
                        backgroundColor: chartColors.primary,
                        borderColor: chartColors.primary,
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>

        // Add smooth entrance animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.kpi-card, .chart-card, .recent-activity');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>