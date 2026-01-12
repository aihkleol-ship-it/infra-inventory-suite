<?php
// infra-inventory/api/cron_zabbix_sync.php

// This script is intended to be run as a cron job.
// It synchronizes device information from Zabbix to the local inventory.

include_once 'config.php';
include_once 'logger.php';

echo "Zabbix Sync Started: " . date('Y-m-d H:i:s') . "\n";

try {
    // 1. Get Gateway settings from infra-inventory's settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $gatewayUrl = $settings['gateway_url'] ?? '';
    $gatewayKey = $settings['gateway_key'] ?? '';

    if (empty($gatewayUrl) || empty($gatewayKey)) {
        throw new Exception("InfraGateway not configured in inventory settings.");
    }
    
    $zabbixProxyUrl = rtrim($gatewayUrl, '/') . '/../infra-gateway/api/zabbix_proxy.php';

    // Helper function to make requests to the proxy
    function callZabbixApi($method, $params) {
        global $zabbixProxyUrl, $gatewayKey;

        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => time(),
        ];

        $ch = curl_init($zabbixProxyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json-rpc",
            "Authorization: Bearer $gatewayKey"
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("Zabbix API request failed: " . curl_error($ch));
        }
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (isset($decoded['error'])) {
            throw new Exception("Zabbix API Error: " . ($decoded['error']['message'] ?? '') . ' ' . ($decoded['error']['data'] ?? ''));
        }
        return $decoded['result'];
    }

    // 2. Get all hosts from Zabbix with serial number
    echo "Fetching hosts from Zabbix...\n";
    $zabbixHosts = callZabbixApi('host.get', [
        'output' => ['host', 'name'],
        'selectInterfaces' => ['ip'],
        'selectInventory' => ['serialno_a'],
        'filter' => ['serialno_a' => ''] // Zabbix filter for not empty
    ]);
    
    echo "Found " . count($zabbixHosts) . " hosts with serial numbers in Zabbix.\n";
    $updatedCount = 0;
    $skippedCount = 0;

    // 3. Iterate over Zabbix hosts and update local inventory
    foreach ($zabbixHosts as $host) {
        if (empty($host['inventory']['serialno_a'])) {
            $skippedCount++;
            continue;
        }

        $serial = $host['inventory']['serialno_a'];
        $zabbixHostname = $host['name'];
        $zabbixIp = $host['interfaces'][0]['ip'] ?? '';

        // Find local device by serial number
        $stmt = $pdo->prepare("SELECT id, hostname, ip_address FROM inventory WHERE serial_number = ?");
        $stmt->execute([$serial]);
        $localDevice = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($localDevice) {
            // Device found, check if update is needed
            if ($localDevice['hostname'] !== $zabbixHostname || $localDevice['ip_address'] !== $zabbixIp) {
                $updateStmt = $pdo->prepare("UPDATE inventory SET hostname = ?, ip_address = ? WHERE id = ?");
                $updateStmt->execute([$zabbixHostname, $zabbixIp, $localDevice['id']]);
                
                $logMsg = "Updated device #{$localDevice['id']} (SN: $serial). Hostname: '{$localDevice['hostname']}' -> '$zabbixHostname'. IP: '{$localDevice['ip_address']}' -> '$zabbixIp'.";
                echo $logMsg . "\n";
                writeLog($pdo, 'ZABBIX_SYNC', 'Device Updated', $logMsg);
                $updatedCount++;
            } else {
                $skippedCount++;
            }
        } else {
            // Device not found in local inventory
            $skippedCount++;
        }
    }

    $summary = "Sync complete. Updated: $updatedCount devices. Skipped/Unchanged: $skippedCount devices.";
    echo $summary . "\n";
    writeLog($pdo, 'ZABBIX_SYNC', 'Sync Finished', $summary);


} catch (Exception $e) {
    // Log errors to a file or database
    $errorMsg = "Error: " . $e->getMessage();
    echo $errorMsg . "\n";
    writeLog($pdo, 'ZABBIX_SYNC', 'Error', $errorMsg);
}

echo "Zabbix Sync Finished: " . date('Y-m-d H:i:s') . "\n";
?>
