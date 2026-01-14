<?php
// api/trigger_zabbix_sync.php
include_once 'config.php';

if (php_sapi_name() !== 'cli' && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin')) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

header('Content-Type: application/json');

// Execute the cron script in the background
// Note: This requires 'exec' to be enabled in php.ini and proper permissions.
$command = 'php ' . __DIR__ . '/cron_zabbix_sync.php';

// Redirect output to a log file instead of /dev/null for better debugging
$logFile = __DIR__ . '/../../infra-inventory-zabbix-sync.log';
exec("$command > $logFile 2>&1 &");

echo json_encode(["status" => "success", "message" => "Zabbix synchronization started in the background."]);
?>
