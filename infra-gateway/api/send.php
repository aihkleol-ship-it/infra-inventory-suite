<?php
// gateway/api/send.php
header("Content-Type: application/json");
ini_set('display_errors', 0); // Prevent raw errors from breaking JSON output
error_reporting(0);

include_once 'config.php'; // Use centralized PDO connection
include_once 'Mailer.php';

// 2. Validate API Key
$headers = apache_request_headers();
$auth = $headers['Authorization'] ?? '';
$key = str_replace('Bearer ', '', $auth);

$stmt = $pdo->prepare("SELECT id, app_name FROM gateway_clients WHERE api_key = ? AND status = 'active'");
$stmt->execute([$key]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    http_response_code(403); echo json_encode(["status"=>"error", "message"=>"Invalid or Revoked API Key"]); exit;
}

// 3. Process Request
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['to']) || empty($input['body'])) {
    http_response_code(400); echo json_encode(["status"=>"error", "message"=>"Missing 'to' or 'body'"]); exit;
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
    http_response_code(500); echo json_encode(["status"=>"error", "message"=>$result['message']]);
}
?>