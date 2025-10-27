<?php
// Time Overview Page - Admin Only
// Displays all registered hours across arbejdstilladelse (work orders)
// Grouped by arbejdstilladelse and user with filtering capabilities

session_start();
require_once 'auth_check.php';

// Admin-only access control
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: view_wo.php');
    exit();
}

require_once 'database.php';

$username = $_SESSION['user'];
$role = $_SESSION['role'] ?? 'user';

// Get filter parameters
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterWorkOrder = $_GET['work_order'] ?? '';
$filterUser = $_GET['user'] ?? '';

// Initialize all variables with default values
$timeData = [];
$totalStats = ['total_hours' => 0, 'total_work_orders' => 0, 'total_users' => 0, 'total_entries' => 0];
$topWorkOrders = [];
$topUsers = [];
$monthlyData = [];
$allWorkOrders = [];
$allUsers = [];
$error_message = null;

try {
    $db = Database::getInstance();
    
    // Build WHERE clause based on filters
    $whereClauses = [];
    $params = [];
    
    if ($filterDateFrom) {
        $whereClauses[] = "te.entry_date >= ?";
        $params[] = $filterDateFrom;
    }
    
    if ($filterDateTo) {
        $whereClauses[] = "te.entry_date <= ?";
        $params[] = $filterDateTo;
    }
    
    if ($filterWorkOrder) {
        $whereClauses[] = "wo.id = ?";
        $params[] = $filterWorkOrder;
    }
    
    if ($filterUser) {
        $whereClauses[] = "u.id = ?";
        $params[] = $filterUser;
    }
    
    $whereSQL = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";
    
    // Main query: Get time entries grouped by work order and user
    try {
        $timeData = $db->fetchAll("
            SELECT 
                wo.id as work_order_id,
                wo.work_order_no,
                wo.description as work_order_description,
                wo.p_description,
                u.id as user_id,
                u.username,
                u.entreprenor_firma,
                SUM(te.hours) as total_hours,
                COUNT(te.id) as entry_count,
                MIN(te.entry_date) as first_entry_date,
                MAX(te.entry_date) as last_entry_date
            FROM time_entries te
            JOIN work_orders wo ON te.work_order_id = wo.id
            JOIN users u ON te.user_id = u.id
            $whereSQL
            GROUP BY wo.id, wo.work_order_no, wo.description, wo.p_description, u.id, u.username, u.entreprenor_firma
            ORDER BY wo.work_order_no, u.username
        ", $params);
    } catch (Exception $e) {
        error_log("Error fetching time data: " . $e->getMessage());
    }
    
    // Statistics - Total hours
    try {
        $totalStats = $db->fetch("
            SELECT 
                COALESCE(SUM(te.hours), 0) as total_hours,
                COUNT(DISTINCT te.work_order_id) as total_work_orders,
                COUNT(DISTINCT te.user_id) as total_users,
                COUNT(te.id) as total_entries
            FROM time_entries te
            JOIN work_orders wo ON te.work_order_id = wo.id
            JOIN users u ON te.user_id = u.id
            $whereSQL
        ", $params);
        if (!$totalStats) {
            $totalStats = ['total_hours' => 0, 'total_work_orders' => 0, 'total_users' => 0, 'total_entries' => 0];
        }
    } catch (Exception $e) {
        error_log("Error fetching total stats: " . $e->getMessage());
    }
    
    // Top 5 work orders by hours
    try {
        $topWorkOrders = $db->fetchAll("
            SELECT 
                wo.work_order_no,
                wo.description,
                SUM(te.hours) as total_hours
            FROM time_entries te
            JOIN work_orders wo ON te.work_order_id = wo.id
            JOIN users u ON te.user_id = u.id
            $whereSQL
            GROUP BY wo.id, wo.work_order_no, wo.description
            ORDER BY total_hours DESC
            LIMIT 5
        ", $params);
    } catch (Exception $e) {
        error_log("Error fetching top work orders: " . $e->getMessage());
    }
    
    // Top 5 users by hours
    try {
        $topUsers = $db->fetchAll("
            SELECT 
                u.username,
                u.entreprenor_firma,
                SUM(te.hours) as total_hours,
                COUNT(DISTINCT te.work_order_id) as work_order_count
            FROM time_entries te
            JOIN work_orders wo ON te.work_order_id = wo.id
            JOIN users u ON te.user_id = u.id
            $whereSQL
            GROUP BY u.id, u.username, u.entreprenor_firma
            ORDER BY total_hours DESC
            LIMIT 5
        ", $params);
    } catch (Exception $e) {
        error_log("Error fetching top users: " . $e->getMessage());
    }
    
    // Hours per month (for chart) - PostgreSQL specific
    try {
        $monthlyData = $db->fetchAll("
            SELECT 
                TO_CHAR(te.entry_date, 'YYYY-MM') as month,
                SUM(te.hours) as total_hours
            FROM time_entries te
            JOIN work_orders wo ON te.work_order_id = wo.id
            JOIN users u ON te.user_id = u.id
            $whereSQL
            GROUP BY TO_CHAR(te.entry_date, 'YYYY-MM')
            ORDER BY month DESC
            LIMIT 12
        ", $params);
    } catch (Exception $e) {
        error_log("Error fetching monthly data: " . $e->getMessage());
        // Monthly chart is optional, continue without it
    }
    
    // Get all work orders for filter dropdown
    try {
        $allWorkOrders = $db->fetchAll("
            SELECT DISTINCT wo.id, wo.work_order_no, wo.description 
            FROM work_orders wo
            JOIN time_entries te ON wo.id = te.work_order_id
            ORDER BY wo.work_order_no
        ");
    } catch (Exception $e) {
        error_log("Error fetching work orders for filter: " . $e->getMessage());
    }
    
    // Get all users who have registered time
    try {
        $allUsers = $db->fetchAll("
            SELECT DISTINCT u.id, u.username, u.entreprenor_firma
            FROM users u
            JOIN time_entries te ON u.id = te.user_id
            ORDER BY u.username
        ");
    } catch (Exception $e) {
        error_log("Error fetching users for filter: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("Database connection error in time_overblik.php: " . $e->getMessage());
    $error_message = "Der opstod en fejl ved hentning af data. Prøv igen senere.";
}

// Prepare chart data
$monthLabels = array_reverse(array_column($monthlyData, 'month'));
$monthHours = array_reverse(array_column($monthlyData, 'total_hours'));
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Timeoverblik - PTW System</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--background-primary) 0%, var(--background-secondary) 100%);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-light);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0.5rem 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .filter-section {
            background: var(--background-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin: 2rem 0;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-field label {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .filter-field input,
        .filter-field select {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
        }
        
        .filter-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .top-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .top-card {
            background: var(--background-primary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
        }
        
        .top-card h3 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .top-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-light);
            transition: var(--transition);
        }
        
        .top-item:hover {
            background: var(--background-secondary);
        }
        
        .top-item:last-child {
            border-bottom: none;
        }
        
        .top-item-name {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .top-item-value {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .chart-container {
            background: var(--background-primary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            margin: 2rem 0;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
        }
        
        .time-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
        }
        
        .time-table th {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .time-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .time-table tbody tr:hover {
            background: var(--background-secondary);
        }
        
        .hours-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation.php'; ?>

    <div class="container">
        <h1>⏱️ Timeoverblik - Timeregistrering på tværs af PTW'er</h1>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Timer</div>
                <div class="stat-value"><?php echo number_format($totalStats['total_hours'], 1); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">PTW'er</div>
                <div class="stat-value"><?php echo $totalStats['total_work_orders']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Brugere</div>
                <div class="stat-value"><?php echo $totalStats['total_users']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Registreringer</div>
                <div class="stat-value"><?php echo $totalStats['total_entries']; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <h3>🔍 Filtrer data</h3>
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-field">
                        <label for="date_from">Dato fra:</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                    </div>
                    <div class="filter-field">
                        <label for="date_to">Dato til:</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                    </div>
                    <div class="filter-field">
                        <label for="work_order">PTW:</label>
                        <select id="work_order" name="work_order">
                            <option value="">Alle</option>
                            <?php foreach ($allWorkOrders as $wo): ?>
                                <option value="<?php echo $wo['id']; ?>" <?php echo $filterWorkOrder == $wo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($wo['work_order_no'] . ' - ' . $wo['description']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="user">Bruger:</label>
                        <select id="user" name="user">
                            <option value="">Alle</option>
                            <?php foreach ($allUsers as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $filterUser == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['username'] . ($u['entreprenor_firma'] ? ' (' . $u['entreprenor_firma'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Anvend filtre</button>
                    <a href="time_overblik.php" class="btn btn-secondary">Nulstil</a>
                </div>
            </form>
        </div>

        <!-- Monthly Chart -->
        <?php if (count($monthlyData) > 0): ?>
        <div class="chart-container">
            <h3>📊 Timer per måned</h3>
            <div class="chart-wrapper">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top Lists -->
        <div class="top-list">
            <div class="top-card">
                <h3>🏆 Top 5 PTW'er (Timer)</h3>
                <?php if (count($topWorkOrders) > 0): ?>
                    <?php foreach ($topWorkOrders as $index => $wo): ?>
                        <div class="top-item">
                            <div class="top-item-name">
                                <strong><?php echo ($index + 1); ?>.</strong> 
                                <?php echo htmlspecialchars($wo['work_order_no']); ?>
                                <div style="font-size: 0.85em; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars(substr($wo['description'], 0, 40) . (strlen($wo['description']) > 40 ? '...' : '')); ?>
                                </div>
                            </div>
                            <div class="top-item-value"><?php echo number_format($wo['total_hours'], 1); ?> t</div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-secondary); text-align: center;">Ingen data</p>
                <?php endif; ?>
            </div>
            
            <div class="top-card">
                <h3>👤 Top 5 Brugere (Timer)</h3>
                <?php if (count($topUsers) > 0): ?>
                    <?php foreach ($topUsers as $index => $user): ?>
                        <div class="top-item">
                            <div class="top-item-name">
                                <strong><?php echo ($index + 1); ?>.</strong> 
                                <?php echo htmlspecialchars($user['username']); ?>
                                <?php if ($user['entreprenor_firma']): ?>
                                    <div style="font-size: 0.85em; color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($user['entreprenor_firma']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="top-item-value"><?php echo number_format($user['total_hours'], 1); ?> t</div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-secondary); text-align: center;">Ingen data</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Data Table -->
        <h2>📋 Detaljeret Timeoverblik</h2>
        
        <?php if (count($timeData) > 0): ?>
            <div class="table-wrapper">
                <table class="time-table">
                    <thead>
                        <tr>
                            <th>A.T. Nummer</th>
                            <th>Beskrivelse</th>
                            <th>P-Beskrivelse</th>
                            <th>Bruger</th>
                            <th>Firma</th>
                            <th>Timer</th>
                            <th>Registreringer</th>
                            <th>Periode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeData as $entry): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($entry['work_order_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($entry['work_order_description']); ?></td>
                                <td><?php echo htmlspecialchars($entry['p_description'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($entry['username']); ?></td>
                                <td><?php echo htmlspecialchars($entry['entreprenor_firma'] ?? '-'); ?></td>
                                <td><span class="hours-badge"><?php echo number_format($entry['total_hours'], 1); ?></span></td>
                                <td><?php echo $entry['entry_count']; ?></td>
                                <td style="font-size: 0.85em; color: var(--text-secondary);">
                                    <?php echo date('d/m/Y', strtotime($entry['first_entry_date'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($entry['last_entry_date'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3>Ingen timeregistreringer fundet</h3>
                <p>Der er ingen registrerede timer der matcher de valgte filtre.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (count($monthlyData) > 0): ?>
    <script>
        // Monthly hours chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($monthLabels); ?>,
                datasets: [{
                    label: 'Timer',
                    data: <?php echo json_encode($monthHours); ?>,
                    backgroundColor: 'rgba(30, 64, 175, 0.8)',
                    borderColor: 'rgba(30, 64, 175, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toFixed(1) + ' timer';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' t';
                            }
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
