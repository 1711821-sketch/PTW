<?php
require_once 'database.php';

echo "Starting data migration from JSON to PostgreSQL...\n";

try {
    $db = Database::getInstance();
    
    // Migrate users
    echo "Migrating users...\n";
    if (file_exists('users.json')) {
        $users = json_decode(file_get_contents('users.json'), true);
        if (is_array($users)) {
            foreach ($users as $user) {
                // Check if user already exists
                $existing = $db->fetch("SELECT id FROM users WHERE username = ?", [$user['username']]);
                if (!$existing) {
                    $db->query(
                        "INSERT INTO users (username, password_hash, role, approved, entreprenor_firma) VALUES (?, ?, ?, ?, ?)",
                        [
                            $user['username'],
                            $user['password_hash'],
                            $user['role'],
                            $user['approved'] ?? true,
                            $user['entreprenor_firma'] ?? null
                        ]
                    );
                    echo "- Migrated user: {$user['username']}\n";
                } else {
                    echo "- User already exists: {$user['username']}\n";
                }
            }
        }
    }
    
    // Migrate work orders
    echo "Migrating work orders...\n";
    if (file_exists('wo_data.json')) {
        $workOrders = json_decode(file_get_contents('wo_data.json'), true);
        if (is_array($workOrders)) {
            foreach ($workOrders as $wo) {
                // Check if work order already exists
                $existing = $db->fetch("SELECT id FROM work_orders WHERE id = ?", [$wo['id']]);
                if (!$existing) {
                    $db->query(
                        "INSERT INTO work_orders (id, work_order_no, p_number, mps_nr, description, p_description, jobansvarlig, telefon, oprettet_af, oprettet_dato, components, entreprenor_firma, entreprenor_kontakt, status, latitude, longitude, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $wo['id'],
                            $wo['work_order_no'] ?? null,
                            $wo['p_number'] ?? null,
                            $wo['mps_nr'] ?? null,
                            $wo['description'] ?? null,
                            $wo['p_description'] ?? null,
                            $wo['jobansvarlig'] ?? null,
                            $wo['telefon'] ?? null,
                            $wo['oprettet_af'] ?? null,
                            $wo['oprettet_dato'] ?? null,
                            $wo['components'] ?? null,
                            $wo['entreprenor_firma'] ?? null,
                            $wo['entreprenor_kontakt'] ?? null,
                            $wo['status'] ?? 'active',
                            isset($wo['latitude']) ? floatval($wo['latitude']) : null,
                            isset($wo['longitude']) ? floatval($wo['longitude']) : null,
                            $wo['notes'] ?? null
                        ]
                    );
                    echo "- Migrated work order: {$wo['id']}\n";
                    
                    // Migrate approvals
                    if (isset($wo['approvals']) && is_array($wo['approvals'])) {
                        foreach ($wo['approvals'] as $role => $date) {
                            if ($date) {
                                $approvedBy = null;
                                // Try to find who approved it from approval_history
                                if (isset($wo['approval_history']) && is_array($wo['approval_history'])) {
                                    foreach ($wo['approval_history'] as $history) {
                                        if ($history['role'] === $role && isset($history['user'])) {
                                            $approvedBy = $history['user'];
                                            break;
                                        }
                                    }
                                }
                                
                                $db->query(
                                    "INSERT INTO approvals (work_order_id, role, approved_date, approved_by) VALUES (?, ?, ?, ?) ON CONFLICT (work_order_id, role) DO NOTHING",
                                    [
                                        $wo['id'],
                                        $role,
                                        $date,
                                        $approvedBy
                                    ]
                                );
                                echo "  - Migrated approval: {$role} on {$date}\n";
                            }
                        }
                    }
                } else {
                    echo "- Work order already exists: {$wo['id']}\n";
                }
            }
        }
    }
    
    // Update sequence for auto-generated IDs
    echo "Updating sequences...\n";
    $maxWoId = $db->fetch("SELECT MAX(id) as max_id FROM work_orders");
    if ($maxWoId && $maxWoId['max_id']) {
        $nextId = intval($maxWoId['max_id']) + 1;
        $db->query("SELECT setval('work_orders_id_seq', ?)", [$nextId]);
        echo "- Updated work_orders sequence to start at {$nextId}\n";
    }
    
    echo "Data migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>