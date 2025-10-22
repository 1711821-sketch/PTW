<?php
/**
 * Email Helper - SendGrid Integration via Replit Connector
 * Sends notification emails using SendGrid API
 */

/**
 * Get SendGrid credentials from Replit Connector
 * @return array|null Array with 'api_key' and 'from_email' or null on failure
 */
function get_sendgrid_credentials() {
    $hostname = getenv('REPLIT_CONNECTORS_HOSTNAME');
    $xReplitToken = null;
    
    if (getenv('REPL_IDENTITY')) {
        $xReplitToken = 'repl ' . getenv('REPL_IDENTITY');
    } elseif (getenv('WEB_REPL_RENEWAL')) {
        $xReplitToken = 'depl ' . getenv('WEB_REPL_RENEWAL');
    }
    
    if (!$xReplitToken) {
        error_log('SendGrid: X_REPLIT_TOKEN not found for repl/depl');
        return null;
    }
    
    $url = 'https://' . $hostname . '/api/v2/connection?include_secrets=true&connector_names=sendgrid';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'X_REPLIT_TOKEN: ' . $xReplitToken
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("SendGrid: Failed to fetch credentials (HTTP $httpCode)");
        return null;
    }
    
    $data = json_decode($response, true);
    $connection = $data['items'][0] ?? null;
    
    if (!$connection || empty($connection['settings']['api_key']) || empty($connection['settings']['from_email'])) {
        error_log('SendGrid: Invalid connection settings');
        return null;
    }
    
    return [
        'api_key' => $connection['settings']['api_key'],
        'from_email' => $connection['settings']['from_email']
    ];
}

/**
 * Send email via SendGrid API
 * @param string $to_email Recipient email address
 * @param string $to_name Recipient name
 * @param string $subject Email subject
 * @param string $html_content HTML email content
 * @param string $text_content Plain text email content (fallback)
 * @return bool True on success, false on failure
 */
function send_email_sendgrid($to_email, $to_name, $subject, $html_content, $text_content = '') {
    $credentials = get_sendgrid_credentials();
    
    if (!$credentials) {
        error_log('SendGrid: Failed to get credentials');
        return false;
    }
    
    $from_email = $credentials['from_email'];
    $api_key = $credentials['api_key'];
    
    // If no plain text provided, strip HTML tags
    if (empty($text_content)) {
        $text_content = strip_tags($html_content);
    }
    
    // Build SendGrid API payload
    $payload = [
        'personalizations' => [
            [
                'to' => [
                    [
                        'email' => $to_email,
                        'name' => $to_name
                    ]
                ],
                'subject' => $subject
            ]
        ],
        'from' => [
            'email' => $from_email,
            'name' => 'PTW System - SikkerJob'
        ],
        'content' => [
            [
                'type' => 'text/plain',
                'value' => $text_content
            ],
            [
                'type' => 'text/html',
                'value' => $html_content
            ]
        ]
    ];
    
    // Send request to SendGrid API
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 202) {
        error_log("SendGrid: Email sent successfully to $to_email");
        return true;
    } else {
        error_log("SendGrid: Failed to send email (HTTP $httpCode): $response");
        return false;
    }
}

/**
 * Send new user registration notification to admin
 * @param string $username New user's username
 * @param string $role New user's role
 * @param string|null $firma Entreprenør firma (if applicable)
 * @return bool True on success, false on failure
 */
function send_new_user_notification($username, $role, $firma = null) {
    $admin_email = 'edin.zubcevic@interterminals.dk';
    $admin_name = 'Administrator';
    
    $subject = '🔔 Ny bruger anmodning - PTW System';
    
    // Map role to Danish display name
    $role_names = [
        'admin' => 'Administrator',
        'opgaveansvarlig' => 'Opgaveansvarlig',
        'drift' => 'Drift',
        'entreprenor' => 'Entreprenør'
    ];
    $role_display = $role_names[$role] ?? $role;
    
    // Build HTML email content
    $html_content = '
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Inter, Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .info-box { background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-row { margin: 10px 0; }
        .label { font-weight: 600; color: #1e40af; }
        .value { color: #334155; }
        .cta-button { display: inline-block; background: #1e40af; color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 20px; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Ny bruger anmodning</h1>
        </div>
        <div class="content">
            <p>Hej Administrator,</p>
            <p>En ny bruger har anmodet om adgang til PTW systemet:</p>
            
            <div class="info-box">
                <div class="info-row">
                    <span class="label">👤 Brugernavn:</span>
                    <span class="value">' . htmlspecialchars($username) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">🔑 Rolle:</span>
                    <span class="value">' . htmlspecialchars($role_display) . '</span>
                </div>';
    
    if ($firma) {
        $html_content .= '
                <div class="info-row">
                    <span class="label">🏢 Firma:</span>
                    <span class="value">' . htmlspecialchars($firma) . '</span>
                </div>';
    }
    
    $html_content .= '
            </div>
            
            <p>Log ind på admin-panelet for at godkende eller afvise denne anmodning.</p>
            
            <a href="https://sikkerjob.replit.app/admin.php" class="cta-button">
                Gå til Admin Panel
            </a>
        </div>
        <div class="footer">
            <p>PTW System - SikkerJob<br>
            Dette er en automatisk notifikation</p>
        </div>
    </div>
</body>
</html>';
    
    // Plain text version
    $text_content = "Ny bruger anmodning - PTW System\n\n";
    $text_content .= "Brugernavn: $username\n";
    $text_content .= "Rolle: $role_display\n";
    if ($firma) {
        $text_content .= "Firma: $firma\n";
    }
    $text_content .= "\nLog ind på https://sikkerjob.replit.app/admin.php for at godkende eller afvise denne anmodning.";
    
    return send_email_sendgrid($admin_email, $admin_name, $subject, $html_content, $text_content);
}
?>
