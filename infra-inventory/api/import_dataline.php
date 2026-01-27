<?php
// api/import_dataline.php
include_once 'config.php';
ini_set('display_errors', 0); error_reporting(E_ALL);

if (isset($_SESSION['role']) && $_SESSION['role'] === 'viewer') { http_response_code(403); echo json_encode(["message" => "Unauthorized"]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(["message" => "Method not allowed"]); exit; }
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo json_encode(["message" => "Upload failed"]); exit; }

$handle = fopen($_FILES['file']['tmp_name'], "r");
if ($handle === FALSE) { http_response_code(500); echo json_encode(["message" => "Cannot read file"]); exit; }

// The getId function is not needed for dataline import.

$rowNum = 0; $success_add = 0; $success_update = 0; $errors = [];

try {
    $pdo->beginTransaction();
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $rowNum++;
        if ($rowNum === 1) {
            // Skip header row
            continue;
        }
        
        // CSV Structure for dataline:
        // 0:site, 1:usage_type, 2:bandwidth, 3:end_device, 4:end_device_mgmt_ip, 5:wan_ip, 6:wan_ip_count, 7:gateway, 8:service_provider, 9:circuit_id, 10:circuit_description, 11:installation_address, 12:account_number, 13:account_name, 14:monthly_charge_hkd, 15:contract_end_date, 16:contract_status
        
        $circuit_id = $data[9] ?? null;
        if (!$circuit_id) { $errors[] = "Row $rowNum: Circuit ID is required."; continue; }

        $site = $data[0] ?? null;
        $usage_type = $data[1] ?? null;
        $bandwidth = $data[2] ?? null;
        $end_device = $data[3] ?? null;
        $end_device_mgmt_ip = $data[4] ?? null;
        $wan_ip = $data[5] ?? null;
        $wan_ip_count = $data[6] ?? null;
        $gateway = $data[7] ?? null;
        $service_provider = $data[8] ?? null;
        $circuit_description = $data[10] ?? null;
        $installation_address = $data[11] ?? null;
        $account_number = $data[12] ?? null;
        $account_name = $data[13] ?? null;
        $monthly_charge_hkd = $data[14] ?? null;
        $contract_end_date = !empty($data[15]) ? date('Y-m-d', strtotime($data[15])) : null;
        $contract_status = $data[16] ?? null;


        // Check if circuit_id exists
        $stmt = $pdo->prepare("SELECT id FROM dataline WHERE circuit_id = ?");
        $stmt->execute([$circuit_id]);
        $existingId = $stmt->fetchColumn();

        try {
            if ($existingId) {
                // Update existing record
                $updateStmt = $pdo->prepare(
                    "UPDATE dataline SET site = ?, usage_type = ?, bandwidth = ?, end_device = ?, end_device_mgmt_ip = ?, wan_ip = ?, wan_ip_count = ?, gateway = ?, service_provider = ?, circuit_description = ?, installation_address = ?, account_number = ?, account_name = ?, monthly_charge_hkd = ?, contract_end_date = ?, contract_status = ? WHERE id = ?"
                );
                $updateStmt->execute([
                    $site, $usage_type, $bandwidth, $end_device, $end_device_mgmt_ip, $wan_ip, $wan_ip_count, $gateway, $service_provider, $circuit_description, $installation_address, $account_number, $account_name, $monthly_charge_hkd, $contract_end_date, $contract_status, $existingId
                ]);
                $success_update++;
            } else {
                // Insert new record
                $insertStmt = $pdo->prepare(
                    "INSERT INTO dataline (site, usage_type, bandwidth, end_device, end_device_mgmt_ip, wan_ip, wan_ip_count, gateway, service_provider, circuit_id, circuit_description, installation_address, account_number, account_name, monthly_charge_hkd, contract_end_date, contract_status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $insertStmt->execute([
                    $site, $usage_type, $bandwidth, $end_device, $end_device_mgmt_ip, $wan_ip, $wan_ip_count, $gateway, $service_provider, $circuit_id, $circuit_description, $installation_address, $account_number, $account_name, $monthly_charge_hkd, $contract_end_date, $contract_status
                ]);
                $success_add++;
            }
        } catch (PDOException $e) {
            $errors[] = "Row $rowNum (Circuit ID: $circuit_id): " . $e->getMessage();
        }
    }
    $pdo->commit(); fclose($handle);
    $message = "Dataline import complete. Added: $success_add, Updated: $success_update." . (count($errors) > 0 ? " Errors: " . count($errors) : "");
    echo json_encode(["success" => true, "imported" => $success_add + $success_update, "errors" => $errors, "message" => $message]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500); echo json_encode(["message" => "Import Error: " . $e->getMessage()]);
}
?>