<?php
// ============================================================
//  CyberGuard AI — API Proxy
//  এই ফাইলটি Anthropic API-তে request পাঠায়
//  API Key সুরক্ষিত থাকে .env ফাইলে
// ============================================================

// CORS Header — শুধু আপনার domain থেকে request allow করুন
// নিজের domain দিয়ে replace করুন, অথবা * রাখুন (less secure)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// OPTIONS preflight request handle করা
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// শুধু POST method allow
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// ---- .env ফাইল থেকে API Key লোড ----
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

// .env লোড করো (api.php এর পাশে থাকবে)
loadEnv(__DIR__ . '/.env');

$apiKey = getenv('ANTHROPIC_API_KEY');

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => '.env ফাইলে ANTHROPIC_API_KEY পাওয়া যায়নি।']);
    exit();
}

// ---- Request body পড়া ----
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit();
}

// ---- Anthropic API-তে Request পাঠানো ----
$payload = json_encode([
    'model'      => 'claude-sonnet-4-20250514',
    'max_tokens' => 1000,
    'system'     => $input['system'] ?? '',
    'messages'   => $input['messages']
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL Error: ' . $curlError]);
    exit();
}

http_response_code($httpCode);
echo $response;
