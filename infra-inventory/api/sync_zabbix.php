<?php
// api/sync_zabbix.php
// Simple endpoint to fetch Zabbix hosts via infra-gateway.
// NOTE: All real Zabbix configuration is stored and managed in infra-gateway.

include_once 'config.php';
include_once 'ZabbixHelper.php';

$method = $_SERVER['REQUEST_METHOD'];

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'editor'])) {
    http_response_code(403);
    echo json_encode(["message" => "Unauthorized"]);
    exit;
}

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit;
}

try {
    $hosts = zabbixFetchHosts();

    // For now we just return the raw host list to the frontend.
    // A future enhancement can map hosts into the local inventory table.
    echo json_encode([
        "success" => true,
        "count"   => count($hosts),
        "hosts"   => $hosts
    ]);
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

?>

