<?php
/**
 * Voice Transcription API - Whisper Integration
 * Receives audio from browser, sends to OpenAI Whisper, returns text
 */

session_start();
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config/load_env.php';

// Release session lock early for better performance
$currentUser = $_SESSION['user'] ?? '';
$currentRole = $_SESSION['role'] ?? '';
session_write_close();

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

// Check for audio file
$audioFile = $_FILES['audio'] ?? null;
if (!$audioFile || $audioFile['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No audio file uploaded']);
    exit;
}

// Get API key from environment
$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}

// Validate file size (max 25MB for Whisper)
if ($audioFile['size'] > 25 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Audio file too large']);
    exit;
}

// Determine mime type
$mimeType = $audioFile['type'] ?: 'audio/webm';
$extension = 'webm';
if (strpos($mimeType, 'mp4') !== false || strpos($mimeType, 'm4a') !== false) {
    $extension = 'm4a';
} elseif (strpos($mimeType, 'mp3') !== false || strpos($mimeType, 'mpeg') !== false) {
    $extension = 'mp3';
} elseif (strpos($mimeType, 'wav') !== false) {
    $extension = 'wav';
}

try {
    // Send to OpenAI Whisper API
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile($audioFile['tmp_name'], $mimeType, 'audio.' . $extension),
            'model' => 'whisper-1',
            'language' => 'da',  // Danish
            'response_format' => 'json',
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError) {
        error_log("Voice transcribe cURL error: " . $curlError);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Connection error']);
        exit;
    }

    if ($httpCode !== 200) {
        error_log("Whisper API error (HTTP $httpCode): " . $response);
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Transcription service error']);
        exit;
    }

    $result = json_decode($response, true);

    if (!$result || !isset($result['text'])) {
        error_log("Invalid Whisper response: " . $response);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Invalid response from service']);
        exit;
    }

    // Return the transcribed text
    echo json_encode([
        'success' => true,
        'text' => trim($result['text']),
        'user' => $currentUser,
        'role' => $currentRole,
    ]);

} catch (Exception $e) {
    error_log("Voice transcribe error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
