<?php
// api/service_email.php
// This is a standalone Microservice Endpoint.
// It does NOT rely on user session ($_SESSION), but on API Key Authentication.

include_once 'config.php';
include_once 'Mailer.php';
include_once 'logger.php'; // Optional: if you want to log service usage

header('Content-Type: application/json');

// 1. Verify Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Use POST."]);
    exit;
}

// 2. Authenticate via API Key (Bearer Token)
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = null;

if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

if (!$token) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. API Key required."]);
    exit;
}

try {
    // Check Token against Database
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'service_api_key'");
    $stmt->execute();
    $validKey = $stmt->fetchColumn();

    if ($token !== $validKey) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Forbidden. Invalid API Key."]);
        exit;
    }

    // 3. Parse Input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $to = $input['to'] ?? null;
    $subject = $input['subject'] ?? '(No Subject)';
    $body = $input['body'] ?? '';

    if (!$to || !$body) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing 'to' or 'body' fields."]);
        exit;
    }

    // 4. Send Email using Mailer Module
    $mailer = new Mailer($pdo);
    $result = $mailer->send($to, $subject, $body);

    if ($result['success']) {
        // Log the API usage (User ID 0 for System/API)
        // We manually insert audit log here since there is no session user
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target, details) VALUES (0, 'Microservice', 'API_EMAIL', ?, ?)");
        $stmt->execute(['Recipient: ' . $to, 'Subject: ' . $subject]);

        echo json_encode(["status" => "success", "message" => "Email queued/sent successfully."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $result['message']]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Service Error: " . $e->getMessage()]);
}
?>