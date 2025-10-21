<?php
// Send push notifications to users
// This file is used internally by the system to send notifications

require_once 'vendor/autoload.php';
require_once 'database.php';
require_once 'push_config.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function sendPushNotification($userId, $title, $body, $url = '/view_wo.php', $icon = '/icon-192.png') {
    try {
        $db = Database::getInstance();
        
        // Get all subscriptions for the user
        $subscriptions = $db->fetchAll(
            "SELECT endpoint, p256dh_key, auth_token FROM push_subscriptions WHERE user_id = ?",
            [$userId]
        );
        
        if (empty($subscriptions)) {
            error_log("No push subscriptions found for user ID: $userId");
            return false;
        }
        
        // Initialize WebPush with VAPID keys
        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ]
        ];
        
        $webPush = new WebPush($auth);
        
        // Prepare notification payload
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'badge' => '/icon-192.png',
            'data' => [
                'url' => $url
            ]
        ]);
        
        // Queue notifications for all user's devices
        $sentCount = 0;
        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys' => [
                    'p256dh' => $sub['p256dh_key'],
                    'auth' => $sub['auth_token']
                ]
            ]);
            
            $webPush->queueNotification($subscription, $payload);
            $sentCount++;
        }
        
        // Send all queued notifications
        $results = $webPush->flush();
        
        // Process results and remove invalid subscriptions
        foreach ($results as $result) {
            if (!$result->isSuccess()) {
                $endpoint = $result->getEndpoint();
                error_log("Failed to send push to endpoint: $endpoint - " . $result->getReason());
                
                // If subscription is expired or invalid, remove it
                if ($result->isSubscriptionExpired()) {
                    $db->execute(
                        "DELETE FROM push_subscriptions WHERE endpoint = ?",
                        [$endpoint]
                    );
                    error_log("Removed expired subscription: $endpoint");
                }
            }
        }
        
        error_log("Sent push notification to user ID: $userId ($sentCount devices)");
        return true;
        
    } catch (Exception $e) {
        error_log("Error sending push notification: " . $e->getMessage());
        return false;
    }
}

// Send notification to all entrepreneurs of a specific company
function sendPushToEntrepreneurs($firma, $title, $body, $url = '/view_wo.php') {
    try {
        $db = Database::getInstance();
        
        // Get all entrepreneurs from the company
        $entrepreneurs = $db->fetchAll(
            "SELECT id FROM users WHERE role = 'entreprenor' AND entreprenor_firma = ?",
            [$firma]
        );
        
        if (empty($entrepreneurs)) {
            error_log("No entrepreneurs found for firma: $firma");
            return false;
        }
        
        $sentToCount = 0;
        foreach ($entrepreneurs as $entrepreneur) {
            if (sendPushNotification($entrepreneur['id'], $title, $body, $url)) {
                $sentToCount++;
            }
        }
        
        error_log("Sent push notifications to $sentToCount entrepreneurs from $firma");
        return $sentToCount > 0;
        
    } catch (Exception $e) {
        error_log("Error sending push to entrepreneurs: " . $e->getMessage());
        return false;
    }
}
