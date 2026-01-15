<?php
// gateway/api/zabbix_test.php
header("Content-Type: application/json");

// 1. Connect DB
require_once __DIR__ . '/../../infra-system-config.php';
$dbname = 'infra_gateway';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500); echo json_encode(["status"=>"error", "message"=>"Gateway DB Error"]); exit;
}

// 2. Authenticate session
session_start();
if (!isset($_SESSION['gw_user_id'])) {
    http_response_code(401); echo json_encode(["message" => "Unauthorized"]); exit;
}

// 3. Get Zabbix Settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM gateway_settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$zabbix_url = $settings['zabbix_url'] ?? '';
$zabbix_user = $settings['zabbix_user'] ?? '';
$zabbix_pass = $settings['zabbix_pass'] ?? '';

if (empty($zabbix_url) || empty($zabbix_user) || empty($zabbix_pass)) {
    http_response_code(500); echo json_encode(["status"=>"error", "message"=>"Zabbix API not configured in InfraGateway"]); exit;
}

// 4. Attempt to authenticate with Zabbix
try {
    $auth_request = [
        'jsonrpc' => '2.0',
        'method' => 'user.login',
        'params' => [
            'username' => $zabbix_user,
            'password' => $zabbix_pass,
        ],
        'id' => 1,
    ];

    $ch = curl_init($zabbix_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($auth_request));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json-rpc"]);
    error_log(json_encode($auth_request));
    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if (isset($decoded['result'])) {
        // Logout from Zabbix immediately after successful login test
        $logout_request = [
            'jsonrpc' => '2.0',
            'method' => 'user.logout',
            'params' => [],
            'id' => 2,
            'auth' => $decoded['result']
        ];
        $ch_logout = curl_init($zabbix_url);
        curl_setopt($ch_logout, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_logout, CURLOPT_POST, true);
        curl_setopt($ch_logout, CURLOPT_POSTFIELDS, json_encode($logout_request));
        curl_setopt($ch_logout, CURLOPT_HTTPHEADER, ["Content-Type: application/json-rpc"]);
        curl_exec($ch_logout);
        curl_close($ch_logout);

        echo json_encode(["status" => "success", "message" => "Zabbix API connection successful!"]);
    } else {
        throw new Exception("Zabbix authentication failed: " . ($decoded['error']['data'] ?? 'Unknown error'));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Zabbix Test Error: " . $e->getMessage() . " Request: " . json_encode($auth_request)]);
}
?>