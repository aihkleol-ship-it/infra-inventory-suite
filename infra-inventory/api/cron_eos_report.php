<?php
// api/cron_eos_report.php (InfraInventory Client Version)
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    include_once 'config.php'; 
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') die("Unauthorized access.");
} else {
    include_once __DIR__ . '/config.php';
}
include_once __DIR__ . '/logger.php';

header('Content-Type: application/json');

try {
    // 1. Get Settings (Recipient + Gateway Config)
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $to = $settings['alert_email_recipient'] ?? '';
    $gatewayUrl = $settings['gateway_url'] ?? '';
    $gatewayKey = $settings['gateway_key'] ?? '';

    if (empty($to) || empty($gatewayUrl) || empty($gatewayKey)) {
        echo json_encode(["status" => "skipped", "message" => "Gateway or Recipient not configured."]);
        exit;
    }

    // 2. Find Risks
    $sql = "SELECT 
                i.hostname, i.ip_address, i.status,
                b.name as brand, m.name as model, m.eos_date,
                DATEDIFF(m.eos_date, NOW()) as days_left
            FROM inventory i
            JOIN models m ON i.model_id = m.id
            JOIN brands b ON i.brand_id = b.id
            WHERE i.status != 'Decommissioned' AND m.eos_date IS NOT NULL
            AND (m.eos_date < NOW() OR DATEDIFF(m.eos_date, NOW()) <= 548)
            ORDER BY m.eos_date ASC";

    $risks = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    if (count($risks) === 0) {
        echo json_encode(["status" => "success", "message" => "No risks found."]);
        exit;
    }

    // 3. Compose HTML Body
    $subject = "[InfraInventory] Risk Report: " . count($risks) . " Devices";
    
    $body = "
    <div style='font-family: sans-serif; color: #333;'>
        <h2 style='color: #d32f2f;'>🛡️ End-of-Support Risk Report</h2>
        <p>The following <strong>" . count($risks) . "</strong> devices require attention.</p>
        <table style='width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 14px;'>
            <tr style='background: #f5f5f5;'>
                <th style='padding: 8px; border: 1px solid #ddd;'>Device</th>
                <th style='padding: 8px; border: 1px solid #ddd;'>Model</th>
                <th style='padding: 8px; border: 1px solid #ddd;'>EOS Date</th>
            </tr>";

    foreach ($risks as $item) {
        $color = ($item['days_left'] < 0) ? '#d32f2f' : '#f57c00';
        $status = ($item['days_left'] < 0) ? "EXPIRED" : $item['days_left']." days left";
        $body .= "
            <tr>
                <td style='padding: 8px; border: 1px solid #ddd;'><strong>{$item['hostname']}</strong><br><span style='color:#666'>{$item['ip_address']}</span></td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$item['brand']} {$item['model']}</td>
                <td style='padding: 8px; border: 1px solid #ddd; color: {$color}; font-weight: bold;'>
                    {$item['eos_date']}<br><small>{$status}</small>
                </td>
            </tr>";
    }
    $body .= "</table><p style='color: #888; font-size: 12px; margin-top: 20px;'>Sent via InfraGateway</p></div>";

    // 4. Send via Gateway (cURL)
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
        if (!$is_cli) writeLog($pdo, 'EMAIL', 'Gateway', "EOS Report sent to $to");
        echo json_encode(["status" => "success", "message" => "Gateway accepted request"]);
    } else {
        throw new Exception("Gateway Error ($httpCode): " . ($respData['message'] ?? $response));
    }

} catch (Exception $e) {
    if (!$is_cli) http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>