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

    // 2. Get default IDs for new devices
    $defaultTypeId = $pdo->query("SELECT id FROM device_types WHERE name = 'Discovered'")->fetchColumn();
    $defaultBrandId = $pdo->query("SELECT id FROM brands WHERE name = 'Zabbix'")->fetchColumn();
    $defaultModelId = $pdo->query("SELECT id FROM models WHERE name = 'Zabbix Host' AND brand_id = $defaultBrandId")->fetchColumn();

    if (!$defaultTypeId || !$defaultBrandId || !$defaultModelId) {
        throw new Exception("Default categories for Zabbix import not found. Please run the setup script.");
    }

    // 3. Get all hosts from Zabbix with serial number
    echo "Fetching hosts from Zabbix...\n";
    $zabbixHosts = callZabbixApi('host.get', [
        'output' => ['host', 'name'],
        'selectInterfaces' => ['ip'],
        'selectInventory' => ['serialno_a'],
        'filter' => ['serialno_a' => ''] // Zabbix filter for not empty
    ]);
    
    echo "Found " . count($zabbixHosts) . " hosts with serial numbers in Zabbix.\n";
    $updatedCount = 0;
    $createdCount = 0;
    $skippedCount = 0;

    // 4. Iterate over Zabbix hosts and update local inventory
    foreach ($zabbixHosts as $host) {
        if (empty($host['inventory']['serialno_a'])) {
            $skippedCount++;
            continue;
        }

        $serial = trim($host['inventory']['serialno_a']);
        $zabbixHostname = $host['name'];
        $zabbixIp = $host['interfaces'][0]['ip'] ?? '';

        // Find local device by serial number (case-insensitive and trimmed)
        $stmt = $pdo->prepare("SELECT id, hostname, ip_address FROM inventory WHERE TRIM(LOWER(serial_number)) = ?");
        $stmt->execute([strtolower($serial)]);
        $localDevice = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($localDevice) {
            // Device found, update it
            if ($localDevice['hostname'] !== $zabbixHostname || $localDevice['ip_address'] !== $zabbixIp) {
                $updateStmt = $pdo->prepare(
                    "UPDATE inventory SET hostname = ?, ip_address = ? WHERE id = ?"
                );
                $updateStmt->execute([$zabbixHostname, $zabbixIp, $localDevice['id']]);

                $logMsg = "Updated device #{$localDevice['id']} from Zabbix (SN: $serial, Host: $zabbixHostname, IP: $zabbixIp).";
                echo $logMsg . "\n";
                writeLog($pdo, 'ZABBIX_SYNC', 'Device Updated', $logMsg);
                $updatedCount++;
            } else {
                $logMsg = "Skipped device (SN: $serial) as it is already up-to-date.";
                echo $logMsg . "\n";
                writeLog($pdo, 'ZABBIX_SYNC', 'Device Skipped', $logMsg);
                $skippedCount++;
            }
        } else {
            // Device not found, check for duplicates by IP or hostname before creating
            $stmt = $pdo->prepare("SELECT id FROM inventory WHERE hostname = ? OR ip_address = ?");
            $stmt->execute([$zabbixHostname, $zabbixIp]);
            if ($stmt->fetch()) {
                $logMsg = "Skipped creating device (SN: $serial). Duplicate hostname '$zabbixHostname' or IP '$zabbixIp' already exists.";
                echo $logMsg . "\n";
                writeLog($pdo, 'ZABBIX_SYNC', 'Skipped Duplicate', $logMsg);
                $skippedCount++;
                continue;
            }

            // Create new device
            $insertStmt = $pdo->prepare(
                "INSERT INTO inventory (hostname, ip_address, serial_number, type_id, brand_id, model_id, status, location) 
                 VALUES (?, ?, ?, ?, ?, ?, 'Active', 'Discovered by Zabbix')"
            );
            $insertStmt->execute([$zabbixHostname, $zabbixIp, $serial, $defaultTypeId, $defaultBrandId, $defaultModelId]);
            $newId = $pdo->lastInsertId();

            $logMsg = "Created new device #{$newId} from Zabbix (SN: $serial, Host: $zabbixHostname, IP: $zabbixIp).";
            echo $logMsg . "\n";
            writeLog($pdo, 'ZABBIX_SYNC', 'Device Created', $logMsg);
            $createdCount++;
        }
    }

    $summary = "Sync complete. Updated: $updatedCount, Created: $createdCount, Skipped/Unchanged: $skippedCount.";
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
