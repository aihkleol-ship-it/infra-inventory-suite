<?php
// api/migrate_v7_zabbix.php
// Placeholder migration script related to future Zabbix integration changes.
// Currently does not perform schema changes, just a safe no-op endpoint.

include_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Unauthorized"]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit;
}

// No-op migration placeholder – keep for future DB changes related to Zabbix.
echo json_encode([
    "success" => true,
    "message" => "Zabbix v7 migration placeholder executed – no DB changes applied."
]);

?>

