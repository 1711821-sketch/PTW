<?php
/**
 * Test email functionality
 * Visit this page to test SendGrid integration
 */

require_once 'email_helper.php';

echo "<!DOCTYPE html>
<html lang='da'>
<head>
    <meta charset='UTF-8'>
    <title>Test Email - SendGrid</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .result { padding: 15px; margin: 10px 0; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    </style>
</head>
<body>
    <h1>🧪 Test SendGrid Email Integration</h1>";

// Test 1: Check if we can get credentials
echo "<div class='result info'><strong>Test 1:</strong> Henter SendGrid credentials...</div>";
$credentials = get_sendgrid_credentials();

if ($credentials) {
    echo "<div class='result success'>✅ SendGrid credentials hentet! From email: " . htmlspecialchars($credentials['from_email']) . "</div>";
} else {
    echo "<div class='result error'>❌ Kunne ikke hente SendGrid credentials. Check logs for detaljer.</div>";
    echo "</body></html>";
    exit;
}

// Test 2: Send test email
echo "<div class='result info'><strong>Test 2:</strong> Sender test email til admin...</div>";

$result = send_new_user_notification('Test Bruger', 'entreprenor', 'Test Firma A/S');

if ($result) {
    echo "<div class='result success'>✅ Email sendt succesfuldt til edin.zubcevic@interterminals.dk!</div>";
    echo "<p><strong>Note:</strong> Check email indbakken (inklusiv spam folder) for at bekræfte modtagelse.</p>";
} else {
    echo "<div class='result error'>❌ Email kunne ikke sendes. Check error logs for detaljer.</div>";
}

echo "<p><a href='register.php'>Tilbage til registrering</a></p>";
echo "</body></html>";
?>
