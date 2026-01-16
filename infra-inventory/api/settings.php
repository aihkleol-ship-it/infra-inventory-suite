<?php
// api/settings.php (InfraInventory Client Version)
include_once 'config.php';
include_once 'logger.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $method !== 'GET') {
    http_response_code(403); echo json_encode(["message" => "Unauthorized"]); exit;
}

if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $all = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        echo json_encode([
            "alert_email" => $all['alert_email_recipient'] ?? "",
            "gateway" => [
                "url" => $all['gateway_url'] ?? "",
                "key" => $all['gateway_key'] ?? ""
            ],
            "zabbix" => [
                "url" => $all['zabbix_url'] ?? "",
                "user" => $all['zabbix_user'] ?? ""
            ]
        ]);
    } catch (Exception $e) { echo json_encode(["error" => "Settings load failed"]); }
}

elseif ($method === 'POST') {
    if (!isset($input['action'])) { http_response_code(400); echo json_encode(["message" => "Action required"]); exit; }

    try {
        // --- Save Gateway Config ---
        if ($input['action'] === 'save_gateway_config') {
            $data = $input['data'];
            
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            
            // Save Recipient
            $stmt->execute(['alert_email_recipient', $data['alert_email'], $data['alert_email']]);
            $stmt->closeCursor();
            
            // Save Gateway Details
            $stmt->execute(['gateway_url', $data['gateway_url'] ?? null, $data['gateway_url'] ?? null]);
            $stmt->closeCursor();
            $stmt->execute(['gateway_key', $data['gateway_key'] ?? null, $data['gateway_key'] ?? null]);
            $stmt->closeCursor();
            
            writeLog($pdo, 'CONFIG', 'Gateway Settings', 'Updated API connection details');
            echo json_encode(["success" => true, "message" => "Connection saved"]);
        }

        // --- Save Zabbix Config ---
        elseif ($input['action'] === 'save_zabbix_config') {
            $data = $input['data'];
            
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            
            $stmt->execute(['zabbix_url', $data['zabbix_url'] ?? null, $data['zabbix_url'] ?? null]);
            $stmt->closeCursor();
            $stmt->execute(['zabbix_user', $data['zabbix_user'] ?? null, $data['zabbix_user'] ?? null]);
            $stmt->closeCursor();

            if (!empty($data['zabbix_pass'])) {
                $stmt->execute(['zabbix_pass', $data['zabbix_pass'], $data['zabbix_pass']]);
                $stmt->closeCursor();
            }
            
            writeLog($pdo, 'CONFIG', 'Zabbix Settings', 'Updated Zabbix API connection details');
            echo json_encode(["success" => true, "message" => "Zabbix settings saved"]);
        }

        // --- Master Data (Brands/Models) - Kept Same ---
        elseif (in_array($input['action'], ['add_brand', 'edit_brand', 'delete_brand', 'add_model', 'edit_model', 'delete_model'])) {
            $type = strpos($input['action'], 'brand') !== false ? 'brand' : 'model';
            $table = $type . 's';
            
            if (strpos($input['action'], 'add_') === 0) {
                if ($type === 'brand') { $stmt = $pdo->prepare("INSERT INTO brands (name) VALUES (?)"); $stmt->execute([trim($input['name'])]); }
                else { $stmt = $pdo->prepare("INSERT INTO models (brand_id, name, rack_units, eos_date) VALUES (?, ?, ?, ?)"); $stmt->execute([$input['brand_id'], trim($input['name']), $input['rack_units'] ?: 1, $input['eos_date'] ?: null]); }
                writeLog($pdo, 'CREATE', ucfirst($type) . ": " . $input['name']);
            } 
            elseif (strpos($input['action'], 'edit_') === 0) {
                if ($type === 'brand') { $stmt = $pdo->prepare("UPDATE brands SET name = ? WHERE id = ?"); $stmt->execute([trim($input['name']), $input['id']]); }
                else { $stmt = $pdo->prepare("UPDATE models SET name = ?, brand_id = ?, rack_units = ?, eos_date = ? WHERE id = ?"); $stmt->execute([trim($input['name']), $input['brand_id'], $input['rack_units'] ?: 1, $input['eos_date'] ?: null, $input['id']]); }
                writeLog($pdo, 'UPDATE', ucfirst($type) . " ID " . $input['id']);
            }
            elseif (strpos($input['action'], 'delete_') === 0) {
                $col = $type . '_id';
                $check = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE $col = ?"); $check->execute([$input['id']]);
                if ($check->fetchColumn() > 0) throw new Exception("Cannot delete: In use.");
                $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?"); $stmt->execute([$input['id']]);
                writeLog($pdo, 'DELETE', ucfirst($type) . " ID " . $input['id']);
            }
            echo json_encode(["success" => true]);
        }
        else { throw new Exception("Invalid action"); }

    } catch (Exception $e) {
        http_response_code(500); echo json_encode(["message" => $e->getMessage()]);
    }
}
?>