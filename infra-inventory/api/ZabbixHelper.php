<?php
// api/ZabbixHelper.php
// Helper for infra-inventory to talk to infra-gateway, which in turn talks to Zabbix.

// --- Gateway Config (all real Zabbix credentials live in infra-gateway) ---
// Base URL to your infra-gateway installation (adjust if needed)
if (!defined('GATEWAY_BASE_URL')) {
    define('GATEWAY_BASE_URL', 'http://localhost/infra-gateway');
}

// API key for this app (create a client in infra-gateway admin panel and paste its api_key here)
if (!defined('GATEWAY_API_KEY')) {
    define('GATEWAY_API_KEY', 'REPLACE_WITH_GATEWAY_API_KEY');
}

/**
 * Low-level helper to call infra-gateway APIs.
 */
function gatewayPostJson(string $path, array $payload)
{
    $url = rtrim(GATEWAY_BASE_URL, '/') . '/' . ltrim($path, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . GATEWAY_API_KEY
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        throw new Exception("Gateway HTTP error: " . $err);
    }
    if ($code < 200 || $code >= 300) {
        throw new Exception("Gateway returned HTTP " . $code . " – body: " . $response);
    }

    $data = json_decode($response, true);
    if (!$data) {
        throw new Exception("Invalid JSON from gateway");
    }

    return $data;
}

/**
 * High-level helper: fetch hosts from Zabbix via infra-gateway.
 *
 * @return array List of hosts as returned by infra-gateway/zabbix proxy
 * @throws Exception on error
 */
function zabbixFetchHosts(): array
{
    $result = gatewayPostJson('api/zabbix.php', [
        'action' => 'get_hosts'
    ]);

    if (!isset($result['success']) || !$result['success']) {
        $msg = $result['message'] ?? 'Unknown gateway error';
        throw new Exception("Gateway Zabbix error: " . $msg);
    }

    return $result['hosts'] ?? [];
}

?>

