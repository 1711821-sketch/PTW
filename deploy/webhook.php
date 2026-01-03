<?php
/**
 * GitHub Webhook Handler for Auto-Deployment
 *
 * Setup:
 * 1. Go to your GitHub repo -> Settings -> Webhooks -> Add webhook
 * 2. Payload URL: https://ptw.interterminals.app/deploy/webhook.php
 * 3. Content type: application/json
 * 4. Secret: (set in WEBHOOK_SECRET below)
 * 5. Events: Just the push event
 */

// Configuration
define('WEBHOOK_SECRET', 'your-webhook-secret-here'); // Change this!
define('DEPLOY_BRANCH', 'main');
define('WEB_ROOT', '/var/www/ptw');
define('LOG_FILE', '/var/log/ptw-deploy.log');

// Logging function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// Verify GitHub signature
function verifySignature($payload, $signature) {
    if (empty($signature)) {
        return false;
    }

    $expected = 'sha256=' . hash_hmac('sha256', $payload, WEBHOOK_SECRET);
    return hash_equals($expected, $signature);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

// Get payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

// Verify signature
if (!verifySignature($payload, $signature)) {
    logMessage('ERROR: Invalid signature');
    http_response_code(401);
    die('Invalid signature');
}

// Parse payload
$data = json_decode($payload, true);
if (!$data) {
    logMessage('ERROR: Invalid JSON payload');
    http_response_code(400);
    die('Invalid payload');
}

// Check if this is a push to the deploy branch
$ref = $data['ref'] ?? '';
$branch = str_replace('refs/heads/', '', $ref);

if ($branch !== DEPLOY_BRANCH) {
    logMessage("INFO: Ignoring push to branch: $branch");
    echo "Ignored (not deploy branch)";
    exit;
}

logMessage("INFO: Starting deployment from branch: $branch");

// Run deployment
$output = [];
$returnCode = 0;

// Pull latest changes
chdir(WEB_ROOT);
exec('git pull origin ' . DEPLOY_BRANCH . ' 2>&1', $output, $returnCode);

if ($returnCode !== 0) {
    logMessage("ERROR: Git pull failed: " . implode("\n", $output));
    http_response_code(500);
    die('Deployment failed');
}

// Set permissions
exec('chown -R www-data:www-data ' . WEB_ROOT . ' 2>&1', $output, $returnCode);

// Clear any PHP opcache
if (function_exists('opcache_reset')) {
    opcache_reset();
}

logMessage("SUCCESS: Deployment completed\n" . implode("\n", $output));

// Return success
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Deployment successful',
    'branch' => $branch,
    'timestamp' => date('c')
]);
