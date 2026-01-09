<?php
// gateway/api/zabbix.php
// Lightweight Zabbix proxy – all Zabbix credentials are stored in infra-gateway (gateway_settings)

header("Content-Type: application/json");

// --- DB Connection (reuse same DB as other gateway APIs) ---
try {
    $pdo = new PDO("mysql:host=localhost;dbname=infra_gateway", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Gateway DB connection failed"]);
    exit;
}

// --- Client Authentication via API Key (for apps like infra-inventory) ---
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!$apiKey) {
    http_response_code(401);
    echo json_encode(["message" => "Missing X-API-Key"]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, app_name, status FROM gateway_clients WHERE api_key = ?");
$stmt->execute([$apiKey]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client || $client['status'] !== 'active') {
    http_response_code(401);
    echo json_encode(["message" => "Invalid or inactive API key"]);
    exit;
}

// --- Load Zabbix Settings from gateway_settings ---
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM gateway_settings");
$settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$zabbixUrl   = rtrim($settings['zabbix_url'] ?? '', '/');
$zabbixToken = $settings['zabbix_token'] ?? '';

if (empty($zabbixUrl) || empty($zabbixToken)) {
    http_response_code(500);
    echo json_encode(["message" => "Zabbix settings not configured in infra-gateway"]);
    exit;
}

// --- Helper: Call Zabbix JSON-RPC API ---
function callZabbix($url, $token, $method, $params = [])
{
    $payload = [
        "jsonrpc" => "2.0",
        "method"  => $method,
        "params"  => $params,
        "auth"    => $token,
        "id"      => 1
    ];

    $ch = curl_init($url . "/api_jsonrpc.php");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json-rpc'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        throw new Exception("Curl error: " . $err);
    }
    if ($code < 200 || $code >= 300) {
        throw new Exception("Zabbix HTTP error: " . $code);
    }

    $data = json_decode($response, true);
    if (!$data) {
        throw new Exception("Invalid JSON from Zabbix");
    }
    if (isset($data['error'])) {
        throw new Exception("Zabbix error: " . ($data['error']['data'] ?? $data['error']['message']));
    }

    return $data['result'] ?? null;
}

// --- Handle Request ---
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit;
}

$action = $input['action'] ?? 'get_hosts';

try {
    if ($action === 'get_hosts') {
        // Minimal host.get per Zabbix API docs
        $hosts = callZabbix($zabbixUrl, $zabbixToken, 'host.get', [
            "output" => ["hostid", "host", "name", "status"],
            "selectInterfaces" => ["ip", "dns"],
        ]);

        echo json_encode([
            "success" => true,
            "client"  => ["id" => $client['id'], "app_name" => $client['app_name']],
            "hosts"   => $hosts
        ]);
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Unknown action"]);
    }
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode(["message" => "Zabbix call failed", "error" => $e->getMessage()]);
}
?>

