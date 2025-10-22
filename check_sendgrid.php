<?php
/**
 * SendGrid Configuration Checker
 * Shows current SendGrid settings to debug email issues
 */

require_once 'email_helper.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='da'>
<head>
    <meta charset='UTF-8'>
    <title>SendGrid Status</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 50px auto; padding: 20px; }
        .box { padding: 20px; margin: 15px 0; border-radius: 8px; border: 2px solid #ccc; }
        .success { background: #d4edda; border-color: #28a745; }
        .error { background: #f8d7da; border-color: #dc3545; }
        .info { background: #d1ecf1; border-color: #17a2b8; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        h2 { margin-top: 0; }
    </style>
</head>
<body>
    <h1>🔍 SendGrid Konfiguration</h1>";

// Check if credentials can be fetched
$credentials = get_sendgrid_credentials();

if (!$credentials) {
    echo "<div class='box error'>
        <h2>❌ Ingen SendGrid forbindelse</h2>
        <p>Kunne ikke hente SendGrid credentials fra Replit connector.</p>
        <p><strong>Mulige årsager:</strong></p>
        <ul>
            <li>SendGrid connector er ikke sat op korrekt</li>
            <li>REPL_IDENTITY eller WEB_REPL_RENEWAL tokens mangler</li>
            <li>REPLIT_CONNECTORS_HOSTNAME er ikke sat</li>
        </ul>
    </div>";
    
    // Debug info
    echo "<div class='box info'>
        <h2>🔧 Debug Info</h2>
        <p><strong>REPLIT_CONNECTORS_HOSTNAME:</strong> " . (getenv('REPLIT_CONNECTORS_HOSTNAME') ?: '❌ Ikke sat') . "</p>
        <p><strong>REPL_IDENTITY:</strong> " . (getenv('REPL_IDENTITY') ? '✅ Sat' : '❌ Ikke sat') . "</p>
        <p><strong>WEB_REPL_RENEWAL:</strong> " . (getenv('WEB_REPL_RENEWAL') ? '✅ Sat' : '❌ Ikke sat') . "</p>
    </div>";
} else {
    echo "<div class='box success'>
        <h2>✅ SendGrid Forbindelse OK</h2>
        <p>Credentials hentet succesfuldt fra Replit connector!</p>
    </div>";
    
    echo "<div class='box info'>
        <h2>📧 Email Konfiguration</h2>
        <p><strong>Afsender (From) Email:</strong> <code>" . htmlspecialchars($credentials['from_email']) . "</code></p>
        <p><strong>API Key:</strong> <code>" . substr($credentials['api_key'], 0, 20) . "..." . substr($credentials['api_key'], -10) . "</code></p>
    </div>";
    
    echo "<div class='box info'>
        <h2>⚠️ Vigtig Information</h2>
        <p>For at emails kan sendes, skal <strong>" . htmlspecialchars($credentials['from_email']) . "</strong> være verificeret i SendGrid.</p>
        <p><strong>Sådan verificerer du:</strong></p>
        <ol>
            <li>Log ind på <a href='https://sendgrid.com/' target='_blank'>SendGrid Dashboard</a></li>
            <li>Gå til <strong>Settings → Sender Authentication</strong></li>
            <li>Klik <strong>Verify a Single Sender</strong></li>
            <li>Indtast emailen: <code>" . htmlspecialchars($credentials['from_email']) . "</code></li>
            <li>Tjek din email og klik på verificerings-linket</li>
        </ol>
    </div>";
    
    // Test sending
    echo "<div class='box info'>
        <h2>🧪 Test Email Sending</h2>
        <p>Vil du prøve at sende en test email nu?</p>
        <form method='post' action='check_sendgrid.php'>
            <button type='submit' name='send_test' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;'>
                📤 Send Test Email
            </button>
        </form>
    </div>";
}

// Handle test email send
if (isset($_POST['send_test']) && $credentials) {
    echo "<div class='box info'>
        <h2>📤 Sender test email...</h2>";
    
    $result = send_new_user_notification('Test Bruger', 'entreprenor', 'Test Firma A/S');
    
    if ($result) {
        echo "<p class='success'>✅ Email sendt succesfuldt!</p>
              <p>Tjek <code>1711821@gmail.com</code> (inklusiv spam folder)</p>";
    } else {
        echo "<p class='error'>❌ Email kunne ikke sendes.</p>
              <p>Check PHP error logs for detaljer om fejlen.</p>";
    }
    
    echo "</div>";
}

echo "<p><a href='register.php'>← Tilbage til registrering</a></p>
</body>
</html>";
?>
