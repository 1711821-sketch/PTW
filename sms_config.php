<?php
// SMS Configuration for Work Permit Notifications
// This file contains the configuration and functions for sending SMS notifications
// when new work permits are created in the system

require_once __DIR__ . '/vendor/autoload.php';
use Twilio\Rest\Client;

class SMSNotifier {
    private $client;
    private $fromNumber;
    private $config;
    
    public function __construct() {
        $this->loadConfig();
        $this->initializeTwilio();
    }
    
    private function loadConfig() {
        // Load SMS configuration
        // These will be set via environment variables for security
        $this->config = [
            'twilio_sid' => $_ENV['TWILIO_ACCOUNT_SID'] ?? '',
            'twilio_token' => $_ENV['TWILIO_AUTH_TOKEN'] ?? '',
            'twilio_phone' => $_ENV['TWILIO_PHONE_NUMBER'] ?? '',
            'notification_recipients' => [
                '+4524664369'
            ],
            'notify_roles' => [
                'admin',
                'opgaveansvarlig',
                'drift'
            ],
            'message_template' => "besked fra Interterminals workflow Der er oprettet ny PTW. åbne workfol på http://edinlink.com"
        ];
    }
    
    private function initializeTwilio() {
        if (!empty($this->config['twilio_sid']) && !empty($this->config['twilio_token'])) {
            try {
                $this->client = new Client($this->config['twilio_sid'], $this->config['twilio_token']);
                $this->fromNumber = $this->config['twilio_phone'];
            } catch (Exception $e) {
                error_log("Twilio initialization error: " . $e->getMessage());
                $this->client = null;
            }
        }
    }
    
    public function sendWorkPermitNotification($workPermitData) {
        if (!$this->client) {
            error_log("SMS notification skipped - Twilio not configured");
            return false;
        }
        
        if (empty($this->config['notification_recipients'])) {
            error_log("SMS notification skipped - No recipients configured");
            return false;
        }
        
        $message = $this->formatMessage($workPermitData);
        $success = true;
        
        foreach ($this->config['notification_recipients'] as $phoneNumber) {
            try {
                $this->client->messages->create(
                    $phoneNumber,
                    [
                        'from' => $this->fromNumber,
                        'body' => $message
                    ]
                );
                error_log("SMS sent successfully to: " . $phoneNumber);
            } catch (Exception $e) {
                error_log("SMS send error to {$phoneNumber}: " . $e->getMessage());
                $success = false;
            }
        }
        
        return $success;
    }
    
    private function formatMessage($data) {
        $template = $this->config['message_template'];
        
        // Replace placeholders with actual data
        $replacements = [
            '{work_order_no}' => $data['work_order_no'] ?? 'N/A',
            '{description}' => substr($data['description'] ?? '', 0, 100) . (strlen($data['description'] ?? '') > 100 ? '...' : ''),
            '{jobansvarlig}' => $data['jobansvarlig'] ?? 'N/A',
            '{status}' => $this->translateStatus($data['status'] ?? 'planning'),
            '{entreprenor_firma}' => $data['entreprenor_firma'] ?? 'N/A'
        ];
        
        foreach ($replacements as $placeholder => $value) {
            $template = str_replace($placeholder, $value, $template);
        }
        
        return $template;
    }
    
    private function translateStatus($status) {
        $translations = [
            'planning' => 'Planlagt',
            'active' => 'Aktiv',
            'completed' => 'Afsluttet'
        ];
        
        return $translations[$status] ?? $status;
    }
    
    public function addRecipient($phoneNumber) {
        if (!in_array($phoneNumber, $this->config['notification_recipients'])) {
            $this->config['notification_recipients'][] = $phoneNumber;
            return $this->saveConfig();
        }
        return true;
    }
    
    public function removeRecipient($phoneNumber) {
        $key = array_search($phoneNumber, $this->config['notification_recipients']);
        if ($key !== false) {
            unset($this->config['notification_recipients'][$key]);
            $this->config['notification_recipients'] = array_values($this->config['notification_recipients']);
            return $this->saveConfig();
        }
        return true;
    }
    
    public function getRecipients() {
        return $this->config['notification_recipients'];
    }
    
    public function testSMS($phoneNumber, $testMessage = null) {
        if (!$this->client) {
            return ['success' => false, 'message' => 'Twilio not configured'];
        }
        
        $message = $testMessage ?? "Test besked fra PTW system. SMS notifikationer fungerer korrekt.";
        
        try {
            $result = $this->client->messages->create(
                $phoneNumber,
                [
                    'from' => $this->fromNumber,
                    'body' => $message
                ]
            );
            return ['success' => true, 'message_sid' => $result->sid];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function saveConfig() {
        // For now, this would need to be saved to a configuration file or database
        // This is a placeholder for future configuration persistence
        return true;
    }
}

// Convenience function to send notification
function sendWorkPermitSMSNotification($workPermitData) {
    try {
        $smsNotifier = new SMSNotifier();
        return $smsNotifier->sendWorkPermitNotification($workPermitData);
    } catch (Exception $e) {
        error_log("SMS notification error: " . $e->getMessage());
        return false;
    }
}