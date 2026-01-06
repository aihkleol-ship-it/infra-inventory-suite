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

$rowNum = 0; $success = 0; $errors = [];

try {
    $pdo->beginTransaction();
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $rowNum++;
        if ($rowNum === 1) {
            $data[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data[0]);
            if (strtolower($data[0]) === 'hostname') continue;
        }
        if (empty($data[0]) && empty($data[2])) continue;

        // V3.3 CSV Structure: 
        // 0:Host, 1:IP, 2:Serial, 3:Type, 4:Brand, 5:Model, 6:Location, 7:SubLocation, 8:Status
        
        $hostname = $data[0] ?? null;
        $serial   = $data[2] ?? null;
        if (!$hostname || !$serial) { $errors[] = "Row $rowNum: Hostname/Serial required."; continue; }

        $typeId = getId($pdo, 'device_types', 'name', !empty($data[3]) ? $data[3] : 'Server');
        $brandId = getId($pdo, 'brands', 'name', !empty($data[4]) ? $data[4] : 'Generic');
        $modelId = getId($pdo, 'models', 'name', !empty($data[5]) ? $data[5] : 'Generic Model', ['brand_id' => $brandId]);

        try {
            $stmt = $pdo->prepare("INSERT INTO inventory (hostname, ip_address, serial_number, type_id, brand_id, model_id, location, sub_location, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $hostname, $data[1] ?? null, $serial, $typeId, $brandId, $modelId, 
                $data[6] ?? null, 
                $data[7] ?? null, // Sub Location
                $data[8] ?? 'Active'
            ]);
            $success++;
        } catch (PDOException $e) { $errors[] = $e->getCode() == 23000 ? "Row $rowNum: Duplicate Serial." : "Row $rowNum Error: " . $e->getMessage(); }
    }
    $pdo->commit(); fclose($handle);
    echo json_encode(["success" => true, "imported" => $success, "errors" => $errors, "message" => "Imported $success items." . (count($errors)>0?" (".count($errors)." errors)":"")]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500); echo json_encode(["message" => "Import Error: " . $e->getMessage()]);
}
?>