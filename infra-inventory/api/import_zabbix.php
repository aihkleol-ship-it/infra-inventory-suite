<?php
// api/import_zabbix.php
// Placeholder for future Zabbix → inventory import logic.
// Intentionally minimal for now to avoid modifying inventory data unintentionally.

include_once 'config.php';
include_once 'ZabbixHelper.php';

$method = $_SERVER['REQUEST_METHOD'];

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin'])) {
    http_response_code(403);
    echo json_encode(["message" => "Unauthorized"]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit;
}

try {
    // At this stage, just return hosts for client-side preview before implementing real import.
    $hosts = zabbixFetchHosts();
    echo json_encode([
        "success" => true,
        "message" => "Zabbix hosts fetched. Implement import mapping logic as a next step.",
        "count"   => count($hosts),
        "hosts"   => $hosts
    ]);
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

?>

