<?php
/**
 * Voice AI Assistant API - Sikkerjob Knowledge Base
 * Answers questions about the PTW system using OpenAI GPT
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

// Get question
$input = json_decode(file_get_contents('php://input'), true);
$question = trim($input['question'] ?? '');

if (empty($question)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No question provided']);
    exit;
}

// Get API key from environment
$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}

// System prompt with Sikkerjob knowledge
$systemPrompt = <<<PROMPT
Du er en hjælpsom assistent for Sikkerjob PTW-systemet (Permit To Work / Arbejdstilladelse).

## Om Sikkerjob
Sikkerjob er et digitalt system til håndtering af arbejdstilladelser (PTW) på byggepladser og industrianlæg. Systemet sikrer at farligt arbejde kun udføres efter korrekt godkendelse.

## Brugerroller
- **Admin**: Fuld adgang til alt. Kan oprette brugere, se alle PTW'er, godkende som alle roller.
- **Drift**: Driftspersonale der godkender at arbejdet kan udføres sikkert på lokationen.
- **Opgaveansvarlig**: Den person der er ansvarlig for selve arbejdsopgaven.
- **Entreprenør**: Ekstern entreprenør der udfører arbejdet. Kan kun se egne PTW'er (filtreret på firma).
- **Bruger**: Basis adgang til at se PTW'er.

## PTW Status
- **Planlagt** (planning): PTW er oprettet men ikke startet endnu.
- **Aktiv** (active): Arbejdet er i gang. Kræver godkendelser.
- **Afsluttet** (completed): Arbejdet er færdigt og afsluttet.

## Godkendelsesflow
En aktiv PTW kræver godkendelse fra tre roller:
1. **Drift** - bekræfter at lokationen er klar
2. **Opgaveansvarlig** - bekræfter arbejdsplanen
3. **Entreprenør** - bekræfter at de har forstået sikkerhedskravene

Godkendelser vises med grønne flueben. Manglende godkendelser vises med grå ikoner.

## Sider i systemet
- **PTW-oversigt** (view_wo.php): Liste over alle PTW'er med filtrering og søgning
- **Opret ny PTW** (create_wo.php): Formular til at oprette ny arbejdstilladelse
- **Kort** (map_wo.php): Geografisk visning af PTW'er på et kort
- **Dashboard** (dashboard.php): Statistik og overblik over PTW'er
- **Admin** (admin.php): Brugeradministration (kun admin)

## Vigtige felter i en PTW
- Arbejdsordrenummer
- Lokation/adresse
- Arbejdsbeskrivelse
- Risici og sikkerhedsforanstaltninger
- Start- og slutdato
- Ansvarlige personer

## Stemmekommandoer
Brugere kan styre systemet med stemmen:
- "Godkend [nummer]" - godkender en PTW
- "Vis aktive/planlagte/afsluttede" - filtrerer listen
- "Søg efter [tekst]" - søger i PTW'er
- "Dashboard", "Kort", "Opret ny" - navigation

Svar venligst på dansk, kort og præcist. Fokuser på praktisk hjælp til brugeren.
PROMPT;

try {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $question]
            ],
            'max_tokens' => 300,
            'temperature' => 0.7,
        ]),
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError) {
        error_log("Voice AI cURL error: " . $curlError);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Connection error']);
        exit;
    }

    if ($httpCode !== 200) {
        error_log("Voice AI API error (HTTP $httpCode): " . substr($response, 0, 500));
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'AI service error']);
        exit;
    }

    $result = json_decode($response, true);
    $answer = $result['choices'][0]['message']['content'] ?? '';

    if (empty($answer)) {
        echo json_encode(['success' => false, 'error' => 'No answer generated']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'answer' => $answer,
    ]);

} catch (Exception $e) {
    error_log("Voice AI error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
