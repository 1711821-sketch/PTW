<?php
/**
 * Voice TTS API - OpenAI Text-to-Speech
 * Converts text to natural Danish speech using OpenAI TTS
 */

session_start();
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config/load_env.php';

// Release session lock early
session_write_close();

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

// Get text to speak
$input = json_decode(file_get_contents('php://input'), true);
$text = trim($input['text'] ?? '');

if (empty($text)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No text provided']);
    exit;
}

// Limit text length (TTS has a 4096 character limit)
if (strlen($text) > 500) {
    $text = substr($text, 0, 500);
}

// Get API key from environment
$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}

try {
    // Send to OpenAI TTS API
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.openai.com/v1/audio/speech',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'tts-1',
            'input' => $text,
            'voice' => 'nova',  // Natural female voice, good for Danish
            'response_format' => 'mp3',
            'speed' => 1.0,
        ]),
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError) {
        error_log("Voice TTS cURL error: " . $curlError);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Connection error']);
        exit;
    }

    if ($httpCode !== 200) {
        error_log("TTS API error (HTTP $httpCode): " . substr($response, 0, 500));
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'TTS service error']);
        exit;
    }

    // Return audio as base64
    $audioBase64 = base64_encode($response);

    echo json_encode([
        'success' => true,
        'audio' => $audioBase64,
        'format' => 'mp3',
    ]);

} catch (Exception $e) {
    error_log("Voice TTS error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
