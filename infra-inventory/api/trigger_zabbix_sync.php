<?php
// api/trigger_zabbix_sync.php
include_once 'config.php';
include_once 'logger.php'; // Include logger

set_time_limit(300); // 5 minutes

if (php_sapi_name() !== 'cli' && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin')) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

header('Content-Type: application/json');

// Execute the cron script
$command = '/usr/bin/php ' . __DIR__ . '/cron_zabbix_sync.php';
writeLog($pdo, 'ZABBIX_SYNC_TRIGGER', 'Command', $command);

// Execute the command and capture the output
$output = [];
$return_var = 0;
exec($command, $output, $return_var);

$logOutput = implode("\n", $output);
writeLog($pdo, 'ZABBIX_SYNC_TRIGGER', 'Output', $logOutput);
writeLog($pdo, 'ZABBIX_SYNC_TRIGGER', 'Return Code', (string)$return_var);

if ($return_var === 0) {
    echo json_encode(["status" => "success", "message" => "Zabbix synchronization finished.", "output" => $logOutput]);
} else {
    echo json_encode(["status" => "error", "message" => "Zabbix synchronization failed.", "output" => $logOutput]);
}
?>
