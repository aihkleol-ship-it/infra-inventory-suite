<?php
// api/import.php
include_once 'config.php';
ini_set('display_errors', 0); error_reporting(E_ALL);

if (isset($_SESSION['role']) && $_SESSION['role'] === 'viewer') { http_response_code(403); echo json_encode(["message" => "Unauthorized"]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(["message" => "Method not allowed"]); exit; }
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo json_encode(["message" => "Upload failed"]); exit; }

$handle = fopen($_FILES['file']['tmp_name'], "r");
if ($handle === FALSE) { http_response_code(500); echo json_encode(["message" => "Cannot read file"]); exit; }

function getId($pdo, $table, $field, $value, $extra = []) {
    if (empty($value)) return null;
    $value = trim($value);
    $sql = "SELECT id FROM $table WHERE $field = :val"; $params = [':val' => $value];
    if (!empty($extra)) { foreach($extra as $k => $v) { $sql .= " AND $k = :$k"; $params[":$k"] = $v; } }
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row['id'];
    $colNames = [$field]; $placeholders = [':val'];
    if (!empty($extra)) { foreach($extra as $k => $v) { $colNames[] = $k; $placeholders[] = ":$k"; } }
    $insSql = "INSERT INTO $table (" . implode(', ', $colNames) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($insSql); $stmt->execute($params);
    return $pdo->lastInsertId();
}

$rowNum = 0; $success_add = 0; $success_update = 0; $errors = [];

try {
    $pdo->beginTransaction();
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $rowNum++;
        if ($rowNum === 1) {
            $data[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data[0]);
            if (strtolower($data[0]) === 'hostname') continue;
        }
        if (empty($data[0]) && empty($data[2])) continue;

        // New V6.1 CSV Structure (matches export):
        // 0:Hostname, 1:IP, 2:Serial, 3:Type, 4:Brand, 5:Model, 6:EOS, 7:Location, 8:Sub Location, 9:Rack, 10:Rack Position, 11:Status, 12:Asset ID, 13:Firmware, 14:Notes
        
        $hostname       = $data[0] ?? null;
        $ip_address     = $data[1] ?? null;
        $serial         = $data[2] ?? null;
        if (!$hostname || !$serial) { $errors[] = "Row $rowNum: Hostname/Serial required."; continue; }

        $typeId         = getId($pdo, 'device_types', 'name', !empty($data[3]) ? $data[3] : 'Server');
        $brandId        = getId($pdo, 'brands', 'name', !empty($data[4]) ? $data[4] : 'Generic');
        $modelId        = getId($pdo, 'models', 'name', !empty($data[5]) ? $data[5] : 'Generic Model', ['brand_id' => $brandId]);
        
        $eos_date       = !empty($data[6]) ? date('Y-m-d', strtotime($data[6])) : null;
        $location       = $data[7] ?? null;
        $sub_location   = $data[8] ?? null;
        $rack           = $data[9] ?? null;
        $rack_position  = $data[10] ?? null;
        $status         = $data[11] ?? 'Active';
        $asset_id       = $data[12] ?? null;
        $firmware       = $data[13] ?? null;
        $notes          = $data[14] ?? null;

        // Check if serial number exists
        $stmt = $pdo->prepare("SELECT id FROM inventory WHERE serial_number = ?");
        $stmt->execute([$serial]);
        $existingId = $stmt->fetchColumn();

        try {
            if ($existingId) {
                // Update existing record
                $updateStmt = $pdo->prepare(
                    "UPDATE inventory SET hostname = ?, ip_address = ?, type_id = ?, brand_id = ?, model_id = ?, 
                     location = ?, sub_location = ?, rack = ?, rack_position = ?, status = ?, asset_id = ?, 
                     firmware_version = ?, notes = ? WHERE id = ?"
                );
                $updateStmt->execute([
                    $hostname, $ip_address, $typeId, $brandId, $modelId, $location, $sub_location,
                    $rack, $rack_position, $status, $asset_id, $firmware, $notes, $existingId
                ]);
                // Also update EOS date on model
                if ($eos_date && $modelId) {
                    $pdo->prepare("UPDATE models SET eos_date = ? WHERE id = ?")->execute([$eos_date, $modelId]);
                }
                $success_update++;
            } else {
                // Insert new record
                $insertStmt = $pdo->prepare(
                    "INSERT INTO inventory (hostname, ip_address, serial_number, type_id, brand_id, model_id, 
                     location, sub_location, rack, rack_position, status, asset_id, firmware_version, notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $insertStmt->execute([
                    $hostname, $ip_address, $serial, $typeId, $brandId, $modelId, $location, $sub_location,
                    $rack, $rack_position, $status, $asset_id, $firmware, $notes
                ]);
                $newId = $pdo->lastInsertId();
                // Also update EOS date on model
                if ($eos_date && $modelId) {
                    $pdo->prepare("UPDATE models SET eos_date = ? WHERE id = ?")->execute([$eos_date, $modelId]);
                }
                $success_add++;
            }
        } catch (PDOException $e) {
            $errors[] = "Row $rowNum (Serial: $serial): " . $e->getMessage();
        }
    }
    $pdo->commit(); fclose($handle);
    $message = "Import complete. Added: $success_add, Updated: $success_update." . (count($errors) > 0 ? " Errors: " . count($errors) : "");
    echo json_encode(["success" => true, "imported" => $success_add + $success_update, "errors" => $errors, "message" => $message]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500); echo json_encode(["message" => "Import Error: " . $e->getMessage()]);
}
?>