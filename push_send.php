<?php
require_once 'vendor/autoload.php';
require_once 'database.php';
require_once 'push_config.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function sendPushNotification($userId, $title, $body, $url = null) {
    try {
        $db = Database::getInstance();
        
        // Get all push subscriptions for this user
        $subscriptions = $db->fetchAll("
            SELECT endpoint, p256dh_key, auth_token 
            FROM push_subscriptions 
            WHERE user_id = ?
        ", [$userId]);
        
        if (empty($subscriptions)) {
            error_log("No push subscriptions found for user ID: $userId");
            return false;
        }
        
        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ]
        ];
        
        $webPush = new WebPush($auth);
        
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/icon-192.png',
            'url' => $url ?? '/view_wo.php'
        ]);
        
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys' => [
                    'p256dh' => $sub['p256dh_key'],
                    'auth' => $sub['auth_token']
                ]
            ]);
            
            $result = $webPush->sendOneNotification($subscription, $payload);
            
            if ($result->isSuccess()) {
                $successCount++;
            } else {
                $failureCount++;
                error_log("Push notification failed: " . $result->getReason());
                
                // If subscription is expired or invalid, remove it
                if ($result->isSubscriptionExpired()) {
                    $db->execute("DELETE FROM push_subscriptions WHERE endpoint = ?", [$sub['endpoint']]);
                    error_log("Removed expired subscription: " . $sub['endpoint']);
                }
            }
        }
        
        error_log("Push notifications sent to user $userId: $successCount success, $failureCount failed");
        return $successCount > 0;
        
    } catch (Exception $e) {
        error_log("Error sending push notification: " . $e->getMessage());
        return false;
    }
}

function sendPushToEntrepreneurFirma($firma, $title, $body, $url = null) {
    try {
        $db = Database::getInstance();
        
        // Get all entrepreneur users from this firma
        $users = $db->fetchAll("
            SELECT DISTINCT u.id 
            FROM users u
            WHERE u.role = 'entreprenor' 
            AND u.entreprenor_firma = ?
        ", [$firma]);
        
        if (empty($users)) {
            error_log("No entrepreneur users found for firma: $firma");
            return false;
        }
        
        $sentCount = 0;
        foreach ($users as $user) {
            if (sendPushNotification($user['id'], $title, $body, $url)) {
                $sentCount++;
            }
        }
        
        return $sentCount > 0;
        
    } catch (Exception $e) {
        error_log("Error sending push to firma: " . $e->getMessage());
        return false;
    }
}
