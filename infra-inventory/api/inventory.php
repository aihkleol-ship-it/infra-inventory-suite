<?php
// api/inventory.php
include_once 'config.php';
include_once 'logger.php';

// --- Helper Functions ---

function json_response(int $statusCode, array $data, array $headers = []): void {
    foreach ($headers as $key => $value) {
        header("$key: $value");
    }
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function get_and_validate_inventory_data(array $input, bool $is_update = false): array {
    $errors = [];

    if (empty($input['hostname'])) { $errors[] = "Hostname is required."; }
    if (empty($input['serial_number'])) { $errors[] = "Serial number is required."; }
    if (!empty($input['ip_address']) && !filter_var($input['ip_address'], FILTER_VALIDATE_IP)) {
        $errors[] = "Invalid IP address format.";
    }
    
    foreach (['type_id', 'brand_id', 'model_id'] as $field) {
        if (isset($input[$field]) && !is_null($input[$field]) && !filter_var($input[$field], FILTER_VALIDATE_INT)) {
            $errors[] = "Invalid value for {$field}. Must be an integer.";
        }
    }

    if ($is_update) {
        if (empty($input['id']) || !filter_var($input['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            $errors[] = "A valid ID is required for an update.";
        }
    }
    
    if (!empty($errors)) {
        json_response(400, ["message" => "Validation failed", "errors" => $errors]);
    }

    return [
        ':hostname' => $input['hostname'], ':ip' => $input['ip_address'] ?? null, ':serial' => $input['serial_number'],
        ':asset' => $input['asset_id'] ?? null, ':fw' => $input['firmware_version'] ?? null, 
        ':loc' => $input['location'] ?? null, ':sub' => $input['sub_location'] ?? null,
        ':rack' => $input['rack'] ?? null, ':rack_pos' => $input['rack_position'] ?? null,
        ':status' => $input['status'] ?? 'Active', ':type' => $input['type_id'] ?? null, 
        ':brand' => $input['brand_id'] ?? null, ':model' => $input['model_id'] ?? null, ':notes' => $input['notes'] ?? null
    ];
}

// --- Main Logic ---

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (isset($_SESSION['role']) && $_SESSION['role'] === 'viewer' && $method !== 'GET') {
    json_response(403, ["message" => "Unauthorized: View Only Access"]);
}

try {
    switch ($method) {
        case 'GET':
            // --- Parameters ---
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            $search = $_GET['search'] ?? '';
            $sortBy = $_GET['sortBy'] ?? 'i.hostname';
            $sortOrder = isset($_GET['sortOrder']) && strtolower($_GET['sortOrder']) === 'desc' ? 'DESC' : 'ASC';
            
            $allowed_sort_columns = ['i.hostname', 'i.ip_address', 'i.serial_number', 'i.location', 'i.status', 'b.name', 'm.name', 'dt.name'];
            if (!in_array($sortBy, $allowed_sort_columns)) { $sortBy = 'i.hostname'; }

            // --- Query Building ---
            $base_sql = "FROM inventory i LEFT JOIN device_types dt ON i.type_id = dt.id LEFT JOIN brands b ON i.brand_id = b.id LEFT JOIN models m ON i.model_id = m.id";
            $where_sql = '';
            $params = [];

            if (!empty($search)) {
                $where_sql = " WHERE (i.hostname LIKE :search OR i.serial_number LIKE :search OR i.ip_address LIKE :search OR i.location LIKE :search OR i.notes LIKE :search)";
                $params[':search'] = "%$search%";
            }

            // --- Total Count ---
            $count_stmt = $pdo->prepare("SELECT COUNT(i.id) " . $base_sql . $where_sql);
            $count_stmt->execute($params);
            $total_count = $count_stmt->fetchColumn();

            // --- Data Fetch ---
            $limit_sql = '';
            if ($limit !== -1) {
                $limit_sql = " LIMIT :limit OFFSET :offset";
            }
            
            $data_sql = "SELECT i.id, i.hostname, i.ip_address, i.serial_number, i.asset_id, i.firmware_version, i.location, i.sub_location, i.rack, i.rack_position, i.status, i.notes, i.type_id, i.brand_id, i.model_id, dt.name as type, b.name as brand, m.name as model, m.eos_date " . $base_sql . $where_sql . " ORDER BY $sortBy $sortOrder" . $limit_sql;
            
            $stmt = $pdo->prepare($data_sql);
            
            if ($limit !== -1) {
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            }
            if (!empty($search)) {
                $stmt->bindParam(':search', $params[':search']);
            }
            $stmt->execute();
            
            json_response(200, $stmt->fetchAll(PDO::FETCH_ASSOC), ['X-Total-Count' => $total_count]);
            break;

        case 'POST':
            // ... (rest of the cases are unchanged)
            $data = get_and_validate_inventory_data($input);
            $sql = "INSERT INTO inventory (hostname, ip_address, serial_number, asset_id, firmware_version, location, sub_location, rack, rack_position, status, type_id, brand_id, model_id, notes) VALUES (:hostname, :ip, :serial, :asset, :fw, :loc, :sub, :rack, :rack_pos, :status, :type, :brand, :model, :notes)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            writeLog($pdo, 'CREATE', "Device: " . $input['hostname'], "Serial: " . $input['serial_number']);
            json_response(201, ["message" => "Device added successfully", "id" => $pdo->lastInsertId()]);
            break;

        case 'PUT':
            $data = get_and_validate_inventory_data($input, true);
            $sql = "UPDATE inventory SET hostname = :hostname, ip_address = :ip, serial_number = :serial, asset_id = :asset, firmware_version = :fw, location = :loc, sub_location = :sub, rack = :rack, rack_position = :rack_pos, status = :status, type_id = :type, brand_id = :brand, model_id = :model, notes = :notes, updated_at = NOW() WHERE id = :id";
            $data[':id'] = $input['id'];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            writeLog($pdo, 'UPDATE', "Device: " . ($input['hostname'] ?? 'N/A'), "Updated details for ID: " . $input['id']);
            json_response(200, ["message" => "Device updated successfully"]);
            break;

        case 'DELETE':
            if (empty($input['id']) || !filter_var($input['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                json_response(400, ["message" => "A valid ID is required for deletion."]);
            }
            $id = $input['id'];
            $check = $pdo->prepare("SELECT hostname, serial_number FROM inventory WHERE id = ?");
            $check->execute([$id]);
            $item = $check->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt->execute([$id]);
            if ($item) {
                writeLog($pdo, 'DELETE', "Device: " . $item['hostname'], "Serial: " . $item['serial_number']);
            }
            json_response(200, ["message" => "Device deleted successfully"]);
            break;

        default:
            json_response(405, ["message" => "Method not allowed"]);
            break;
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        json_response(409, ["message" => "Error: A record with this value (e.g., serial number) already exists."]);
    } else {
        json_response(500, ["message" => "Database Error: " . $e->getMessage()]);
    }
} catch (Exception $e) {
    json_response(500, ["error" => $e->getMessage()]);
}
?>