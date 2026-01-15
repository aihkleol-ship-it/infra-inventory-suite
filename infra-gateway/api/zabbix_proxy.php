<?php
// gateway/api/zabbix_proxy.php
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

// 2. Validate API Key from Bearer Token
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';
$key = str_replace('Bearer ', '', $auth);

$stmt = $pdo->prepare("SELECT id, app_name FROM gateway_clients WHERE api_key = ? AND status = 'active'");
$stmt->execute([$key]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    http_response_code(403); echo json_encode(["status"=>"error", "message"=>"Invalid or Revoked API Key"]); exit;
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

// 4. Get Zabbix API request from POST body
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input) || empty($input['method'])) {
    http_response_code(400); echo json_encode(["status"=>"error", "message"=>"Invalid Zabbix request body"]); exit;
}

// 5. Zabbix Authentication and Token Caching
function getZabbixAuthToken($pdo, $settings) {
    $token_ttl = 3600; // 1 hour

    $zabbix_token = $settings['zabbix_token'] ?? null;
    $zabbix_token_expires = $settings['zabbix_token_expires'] ?? null;

    if ($zabbix_token && $zabbix_token_expires && time() < $zabbix_token_expires) {
        return $zabbix_token;
    }

    // Token is invalid or expired, get a new one
    $auth_request = [
        'jsonrpc' => '2.0',
        'method' => 'user.login',
        'params' => [
            'username' => $settings['zabbix_user'],
            'password' => $settings['zabbix_pass'],
        ],
        'id' => 1,
    ];

    error_log("Zabbix URL for authentication: " . $settings['zabbix_url']); // ADDED LOGGING
    $ch = curl_init($settings['zabbix_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($auth_request));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json-rpc"]);
    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if (isset($decoded['result'])) {
        $token = $decoded['result'];
        $expires = time() + $token_ttl;

        // Save new token to database
        $stmt = $pdo->prepare("REPLACE INTO gateway_settings (setting_key, setting_value) VALUES (?, ?), (?, ?)");
        $stmt->execute(['zabbix_token', $token, 'zabbix_token_expires', $expires]);

        return $token;
    } else {
        throw new Exception("Zabbix authentication failed: " . ($decoded['error']['data'] ?? 'Unknown error'));
    }
}

// 6. Make the call to Zabbix API
try {
    // If method is not user.login, add auth token to request
    if ($input['method'] !== 'user.login') {
        $input['auth'] = getZabbixAuthToken($pdo, $settings);
    }

    error_log("Zabbix URL for API call: " . $zabbix_url); // ADDED LOGGING
    $ch = curl_init($zabbix_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json-rpc"]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        throw new Exception("Zabbix API Connection Error: " . curl_error($ch));
    }
    curl_close($ch);

    // 7. Return Zabbix API response
    http_response_code($httpCode);
    echo $response;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Zabbix Proxy Error: " . $e->getMessage()]);
}
?>