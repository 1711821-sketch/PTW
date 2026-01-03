<?php
/**
 * Email Reminder Cron Job
 * Sends reminder emails for pending approvals older than 24 hours
 *
 * Run this script daily via cron or Windows Task Scheduler:
 * Linux: 0 8 * * * php /path/to/send_reminders.php
 * Windows: schtasks /create /sc daily /tn "PTW Reminders" /tr "php c:\xampp\htdocs\Sikkerjob\cron\send_reminders.php" /st 08:00
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    http_response_code(403);
    exit('Access denied. This script must be run from CLI.');
}

// Set timezone
date_default_timezone_set('Europe/Copenhagen');

// Load dependencies
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../email_helper.php';
require_once __DIR__ . '/../config/load_env.php';

// Configuration
$REMINDER_THRESHOLD_HOURS = 24; // Send reminder after this many hours
$DRY_RUN = false; // Set to true for testing without sending emails
$LOG_FILE = __DIR__ . '/reminder_log.txt';

/**
 * Log message to file and console
 */
function log_message($message) {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message\n";
    file_put_contents($LOG_FILE, $line, FILE_APPEND);
    if (php_sapi_name() === 'cli') {
        echo $line;
    }
}

/**
 * Get users who need reminders
 */
function get_pending_reminders($db, $threshold_hours) {
    $threshold_time = date('Y-m-d H:i:s', strtotime("-{$threshold_hours} hours"));

    // Get PTWs with pending approvals that haven't been reminded recently
    $sql = "
        SELECT
            w.id,
            w.work_order_no,
            w.description,
            w.jobansvarlig,
            w.entreprenor_firma,
            w.entreprenor_kontakt,
            w.approvals,
            w.created_at,
            w.status
        FROM work_orders w
        WHERE w.status IN ('planning', 'active')
        AND w.created_at < ?
        ORDER BY w.created_at ASC
    ";

    return $db->fetchAll($sql, [$threshold_time]);
}

/**
 * Get user email by role or username
 */
function get_user_email($db, $username) {
    $user = $db->fetch("SELECT email, username FROM users WHERE username = ?", [$username]);
    return $user ? $user['email'] : null;
}

/**
 * Get all users with a specific role
 */
function get_users_by_role($db, $role) {
    return $db->fetchAll("SELECT id, username, email FROM users WHERE role = ? AND approved = TRUE", [$role]);
}

/**
 * Send reminder email for pending approval
 */
function send_reminder_email($to_email, $to_name, $ptw, $role_needed) {
    global $DRY_RUN;

    $subject = "Husk godkendelse - PTW #{$ptw['work_order_no']}";

    $role_display = [
        'opgaveansvarlig' => 'Opgaveansvarlig',
        'drift' => 'Drift',
        'entreprenor' => 'Entreprenør'
    ][$role_needed] ?? $role_needed;

    $html_content = '
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Inter, Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .info-box { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-row { margin: 10px 0; }
        .label { font-weight: 600; color: #92400e; }
        .value { color: #334155; }
        .cta-button { display: inline-block; background: #1e40af; color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 20px; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
        .warning { color: #92400e; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Husk at godkende PTW</h1>
        </div>
        <div class="content">
            <p>Hej ' . htmlspecialchars($to_name) . ',</p>
            <p class="warning">Der er en PTW der afventer din godkendelse som ' . htmlspecialchars($role_display) . ':</p>

            <div class="info-box">
                <div class="info-row">
                    <span class="label">PTW Nr:</span>
                    <span class="value">' . htmlspecialchars($ptw['work_order_no']) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Beskrivelse:</span>
                    <span class="value">' . htmlspecialchars($ptw['description']) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Oprettet:</span>
                    <span class="value">' . date('d-m-Y H:i', strtotime($ptw['created_at'])) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Jobansvarlig:</span>
                    <span class="value">' . htmlspecialchars($ptw['jobansvarlig']) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Entreprenør:</span>
                    <span class="value">' . htmlspecialchars($ptw['entreprenor_firma']) . '</span>
                </div>
            </div>

            <p>Klik på knappen herunder for at se og godkende PTW\'en:</p>

            <a href="https://sikkerjob.replit.app/view_wo.php?id=' . $ptw['id'] . '" class="cta-button">
                Se PTW og godkend
            </a>
        </div>
        <div class="footer">
            <p>PTW System - SikkerJob<br>
            Dette er en automatisk påmindelse</p>
        </div>
    </div>
</body>
</html>';

    $text_content = "Husk at godkende PTW\n\n";
    $text_content .= "PTW Nr: {$ptw['work_order_no']}\n";
    $text_content .= "Beskrivelse: {$ptw['description']}\n";
    $text_content .= "Oprettet: " . date('d-m-Y H:i', strtotime($ptw['created_at'])) . "\n\n";
    $text_content .= "Se PTW: https://sikkerjob.replit.app/view_wo.php?id={$ptw['id']}";

    if ($DRY_RUN) {
        log_message("[DRY RUN] Would send email to: $to_email for PTW #{$ptw['work_order_no']}");
        return true;
    }

    return send_email_sendgrid($to_email, $to_name, $subject, $html_content, $text_content);
}

/**
 * Create in-app notification for pending approval
 */
function create_reminder_notification($db, $user_id, $ptw, $role_needed) {
    $role_display = [
        'opgaveansvarlig' => 'Opgaveansvarlig',
        'drift' => 'Drift',
        'entreprenor' => 'Entreprenør'
    ][$role_needed] ?? $role_needed;

    $title = "Husk godkendelse: PTW #{$ptw['work_order_no']}";
    $message = "Denne PTW afventer din godkendelse som {$role_display}";
    $link = "view_wo.php?id={$ptw['id']}";

    try {
        $db->execute("
            INSERT INTO notifications (user_id, type, title, message, link)
            VALUES (?, 'reminder', ?, ?, ?)
        ", [$user_id, $title, $message, $link]);
        return true;
    } catch (Exception $e) {
        log_message("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

// Main execution
log_message("=== Starting PTW Reminder Job ===");
log_message("Threshold: {$REMINDER_THRESHOLD_HOURS} hours");
log_message("Dry run: " . ($DRY_RUN ? 'YES' : 'NO'));

try {
    $db = Database::getInstance();
    $today = date('Y-m-d');

    // Get all PTWs with pending approvals
    $pending_ptws = get_pending_reminders($db, $REMINDER_THRESHOLD_HOURS);
    log_message("Found " . count($pending_ptws) . " PTWs to check");

    $emails_sent = 0;
    $notifications_created = 0;

    foreach ($pending_ptws as $ptw) {
        $approvals = json_decode($ptw['approvals'] ?? '{}', true) ?: [];
        $missing_roles = [];

        // Check which approvals are missing for today
        foreach (['opgaveansvarlig', 'drift', 'entreprenor'] as $role) {
            $approved_date = $approvals[$role] ?? null;
            if ($approved_date !== $today) {
                $missing_roles[] = $role;
            }
        }

        if (empty($missing_roles)) {
            continue;
        }

        log_message("PTW #{$ptw['work_order_no']}: Missing approvals from " . implode(', ', $missing_roles));

        // Send reminders for each missing role
        foreach ($missing_roles as $role) {
            $users = [];

            if ($role === 'entreprenor') {
                // Send to entreprenør contact if available
                if (!empty($ptw['entreprenor_kontakt'])) {
                    $user = $db->fetch("SELECT id, username, email FROM users WHERE username = ?", [$ptw['entreprenor_kontakt']]);
                    if ($user && $user['email']) {
                        $users[] = $user;
                    }
                }
            } elseif ($role === 'opgaveansvarlig') {
                // Send to the jobansvarlig for this PTW
                if (!empty($ptw['jobansvarlig'])) {
                    $user = $db->fetch("SELECT id, username, email FROM users WHERE username = ?", [$ptw['jobansvarlig']]);
                    if ($user && $user['email']) {
                        $users[] = $user;
                    }
                }
            } else {
                // For drift, get all drift users
                $users = get_users_by_role($db, $role);
            }

            foreach ($users as $user) {
                if (empty($user['email'])) {
                    log_message("  - No email for user: {$user['username']}");
                    continue;
                }

                // Send email reminder
                if (send_reminder_email($user['email'], $user['username'], $ptw, $role)) {
                    $emails_sent++;
                    log_message("  - Email sent to {$user['username']} ({$user['email']})");
                } else {
                    log_message("  - Failed to send email to {$user['username']}");
                }

                // Create in-app notification
                if (create_reminder_notification($db, $user['id'], $ptw, $role)) {
                    $notifications_created++;
                }
            }
        }
    }

    log_message("=== Job Complete ===");
    log_message("Emails sent: $emails_sent");
    log_message("Notifications created: $notifications_created");

} catch (Exception $e) {
    log_message("ERROR: " . $e->getMessage());
    exit(1);
}

exit(0);
