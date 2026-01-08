<?php
// api/test_gateway.php
include_once 'config.php';
include_once 'logger.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

header('Content-Type: application/json');

try {
    // 1. Get Settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $to = $settings['alert_email_recipient'] ?? '';
    $gatewayUrl = $settings['gateway_url'] ?? '';
    $gatewayKey = $settings['gateway_key'] ?? '';

    if (empty($to) || empty($gatewayUrl) || empty($gatewayKey)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Gateway or Recipient not configured."]);
        exit;
    }

    // 2. Compose Test Email
    $subject = "[InfraInventory] Test Connection";
    $body = "
    <div style='font-family: sans-serif; color: #333;'>
        <h2 style='color: #16a34a;'>✅ Connection Successful!</h2>
        <p>This is a test message from your InfraInventory instance.</p>
        <p>If you received this, your connection to the InfraGateway is working correctly.</p>
        <hr style='border:none; border-top:1px solid #e5e7eb; margin:20px 0;'>
        <p style='color: #6b7280; font-size: 12px;'>Sent via InfraGateway</p>
    </div>";

    // 3. Send via Gateway (cURL)
    $ch = curl_init($gatewayUrl);
    $payload = json_encode(["to" => $to, "subject" => $subject, "body" => $body]);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $gatewayKey"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception("Gateway Connection Error: " . curl_error($ch));
    }
    curl_close($ch);

    $respData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && ($respData['status'] ?? '') === 'success') {
        writeLog($pdo, 'CONFIG', 'Gateway Test', "Test email sent to $to");
        echo json_encode(["status" => "success", "message" => "Test email sent successfully via Gateway!"]);
    } else {
        throw new Exception("Gateway Error ($httpCode): " . ($respData['message'] ?? $response));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Service Error: " . $e->getMessage()]);
}
?>