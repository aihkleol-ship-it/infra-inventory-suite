<?php
// api/inventory.php
include_once 'config.php';
include_once 'logger.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if (isset($_SESSION['role']) && $_SESSION['role'] === 'viewer' && $method !== 'GET') {
    http_response_code(403); echo json_encode(["message" => "Unauthorized: View Only Access"]); exit;
}

if ($method === 'GET') {
    // Added sub_location to SELECT
    $sql = "SELECT 
                i.id, i.hostname, i.ip_address, i.serial_number, 
                i.asset_id, i.firmware_version, i.location, i.sub_location, i.rack, i.rack_position, i.status, i.notes,
                i.type_id, i.brand_id, i.model_id, 
                dt.name as type, b.name as brand, m.name as model, m.eos_date
            FROM inventory i
            LEFT JOIN device_types dt ON i.type_id = dt.id
            LEFT JOIN brands b ON i.brand_id = b.id
            LEFT JOIN models m ON i.model_id = m.id
            ORDER BY i.hostname ASC";
    try {
        $stmt = $pdo->prepare($sql); $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) { http_response_code(500); echo json_encode(["error" => $e->getMessage()]); }
}

elseif ($method === 'POST') {
    if (!isset($input['hostname'], $input['serial_number'])) { http_response_code(400); echo json_encode(["message" => "Missing data"]); exit; }

    $sql = "INSERT INTO inventory 
            (hostname, ip_address, serial_number, asset_id, firmware_version, location, sub_location, rack, rack_position, status, type_id, brand_id, model_id, notes)
            VALUES (:hostname, :ip, :serial, :asset, :fw, :loc, :sub, :rack, :rack_pos, :status, :type, :brand, :model, :notes)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':hostname' => $input['hostname'], ':ip' => $input['ip_address'] ?? null, ':serial' => $input['serial_number'],
            ':asset' => $input['asset_id'] ?? null, ':fw' => $input['firmware_version'] ?? null, 
            ':loc' => $input['location'] ?? null, ':sub' => $input['sub_location'] ?? null,
            ':rack' => $input['rack'] ?? null, ':rack_pos' => $input['rack_position'] ?? null,
            ':status' => $input['status'] ?? 'Active', ':type' => $input['type_id'], ':brand' => $input['brand_id'],
            ':model' => $input['model_id'], ':notes' => $input['notes'] ?? null
        ]);
        writeLog($pdo, 'CREATE', "Device: " . $input['hostname'], "Serial: " . $input['serial_number']);
        echo json_encode(["message" => "Device added successfully", "id" => $pdo->lastInsertId()]);
    } catch (PDOException $e) { http_response_code(500); echo json_encode(["message" => $e->getCode() == 23000 ? "Error: Serial Number must be unique." : "Database Error: " . $e->getMessage()]); }
}

elseif ($method === 'PUT') {
    if (!isset($input['id'])) { http_response_code(400); echo json_encode(["message" => "ID required"]); exit; }

    $sql = "UPDATE inventory SET 
            hostname = :hostname, ip_address = :ip, serial_number = :serial, asset_id = :asset, 
            firmware_version = :fw, location = :loc, sub_location = :sub, rack = :rack, rack_position = :rack_pos, status = :status, 
            type_id = :type, brand_id = :brand, model_id = :model, notes = :notes,
            updated_at = NOW()
            WHERE id = :id";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $input['id'],
            ':hostname' => $input['hostname'], ':ip' => $input['ip_address'] ?? null, ':serial' => $input['serial_number'],
            ':asset' => $input['asset_id'] ?? null, ':fw' => $input['firmware_version'] ?? null, 
            ':loc' => $input['location'] ?? null, ':sub' => $input['sub_location'] ?? null,
            ':rack' => $input['rack'] ?? null, ':rack_pos' => $input['rack_position'] ?? null,
            ':status' => $input['status'] ?? 'Active', ':type' => $input['type_id'], ':brand' => $input['brand_id'],
            ':model' => $input['model_id'], ':notes' => $input['notes'] ?? null
        ]);
        writeLog($pdo, 'UPDATE', "Device: " . $input['hostname'], "Updated details via dashboard");
        echo json_encode(["message" => "Device updated successfully"]);
    } catch (PDOException $e) { http_response_code(500); echo json_encode(["message" => "Database Error: " . $e->getMessage()]); }
}

elseif ($method === 'DELETE') {
    if (!isset($input['id'])) { http_response_code(400); echo json_encode(["message" => "ID required"]); exit; }
    try {
        $check = $pdo->prepare("SELECT hostname, serial_number FROM inventory WHERE id = ?"); $check->execute([$input['id']]); $item = $check->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?"); $stmt->execute([$input['id']]);
        if ($item) writeLog($pdo, 'DELETE', "Device: " . $item['hostname'], "Serial: " . $item['serial_number']);
        echo json_encode(["message" => "Device deleted successfully"]);
    } catch (Exception $e) { http_response_code(500); echo json_encode(["message" => "Database Error: " . $e->getMessage()]); }
}
else { http_response_code(405); echo json_encode(["message" => "Method not allowed"]); }
?>