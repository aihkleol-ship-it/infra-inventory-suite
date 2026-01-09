<?php
// gateway/api/send.php
header("Content-Type: application/json");
include_once 'Mailer.php';

// 1. Connect DB
try {
    $pdo = new PDO("mysql:host=localhost;dbname=infra_gateway", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error", "message"=>"Gateway DB Error"]);
    exit;
}

// 2. Validate API Key (support multiple server APIs, not only Apache)
function gw_get_auth_header(): string {
    // Preferred: getallheaders (Apache/FastCGI)
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) return $headers['Authorization'];
        // Some servers normalize header names differently
        if (isset($headers['authorization'])) return $headers['authorization'];
    }
    // Fallback: $_SERVER
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) return $_SERVER['HTTP_AUTHORIZATION'];
    if (isset($_SERVER['Authorization'])) return $_SERVER['Authorization'];
    return '';
}

$auth = gw_get_auth_header();
$key = str_replace('Bearer ', '', $auth);

if ($key === '') {
    http_response_code(401);
    echo json_encode(["status"=>"error", "message"=>"Missing Authorization header"]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, app_name FROM gateway_clients WHERE api_key = ? AND status = 'active'");
$stmt->execute([$key]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    http_response_code(403);
    echo json_encode(["status"=>"error", "message"=>"Invalid or Revoked API Key"]);
    exit;
}

// 3. Process Request
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(["status"=>"error", "message"=>"Invalid JSON payload"]);
    exit;
}

if (empty($input['to']) || empty($input['body'])) {
    http_response_code(400);
    echo json_encode(["status"=>"error", "message"=>"Missing 'to' or 'body'"]);
    exit;
}

// 4. Send
$mailer = new Mailer($pdo);
$result = $mailer->send($input['to'], $input['subject'] ?? 'No Subject', $input['body']);

// 5. Log
$logStmt = $pdo->prepare("INSERT INTO gateway_logs (client_id, recipient, subject, status, error_msg) VALUES (?, ?, ?, ?, ?)");
$logStmt->execute([$client['id'], $input['to'], $input['subject'] ?? '', $result['success'] ? 'success' : 'error', $result['message']]);

if ($result['success']) {
    echo json_encode(["status"=>"success", "message"=>"Queued via Gateway"]);
} else {
    http_response_code(500);
    echo json_encode(["status"=>"error", "message"=>$result['message']]);
}
?>