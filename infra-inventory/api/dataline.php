<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM dataline ORDER BY id DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        $sql = "INSERT INTO dataline (site, usage_type, bandwidth, end_device, end_device_mgmt_ip, wan_ip, wan_ip_count, gateway, service_provider, circuit_id, circuit_description, installation_address, account_number, account_name, monthly_charge_hkd, contract_end_date, contract_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['site'], $data['usage_type'], $data['bandwidth'], $data['end_device'], $data['end_device_mgmt_ip'], $data['wan_ip'], $data['wan_ip_count'], $data['gateway'], $data['service_provider'], $data['circuit_id'], $data['circuit_description'], $data['installation_address'], $data['account_number'], $data['account_name'], $data['monthly_charge_hkd'], $data['contract_end_date'], $data['contract_status']
        ]);

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'];

        $sql = "UPDATE dataline SET site = ?, usage_type = ?, bandwidth = ?, end_device = ?, end_device_mgmt_ip = ?, wan_ip = ?, wan_ip_count = ?, gateway = ?, service_provider = ?, circuit_id = ?, circuit_description = ?, installation_address = ?, account_number = ?, account_name = ?, monthly_charge_hkd = ?, contract_end_date = ?, contract_status = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['site'], $data['usage_type'], $data['bandwidth'], $data['end_device'], $data['end_device_mgmt_ip'], $data['wan_ip'], $data['wan_ip_count'], $data['gateway'], $data['service_provider'], $data['circuit_id'], $data['circuit_description'], $data['installation_address'], $data['account_number'], $data['account_name'], $data['monthly_charge_hkd'], $data['contract_end_date'], $data['contract_status'], $id
        ]);

        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'];

        $sql = "DELETE FROM dataline WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
