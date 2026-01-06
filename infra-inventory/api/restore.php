<?php
// api/restore.php
include_once 'config.php';
include_once 'logger.php';

// 1. Prevent PHP warnings (like "padding invalid") from breaking the JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    http_response_code(403); 
    echo json_encode(["message" => "Unauthorized"]); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    http_response_code(405); 
    echo json_encode(["message" => "Method not allowed"]); 
    exit; 
}

if (!isset($_FILES['backup_file']) || empty($_POST['password'])) { 
    http_response_code(400); 
    echo json_encode(["message" => "Required fields missing"]); 
    exit; 
}

try {
    if ($_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed with error code: " . $_FILES['backup_file']['error']);
    }

    $fileContent = file_get_contents($_FILES['backup_file']['tmp_name']);
    if ($fileContent === false) {
        throw new Exception("Unable to read uploaded file.");
    }

    $password = $_POST['password'];
    $method = "aes-256-cbc"; 
    $ivLength = openssl_cipher_iv_length($method);
    
    if (strlen($fileContent) < $ivLength) {
        throw new Exception("Invalid file format (File too short)");
    }
    
    $iv = substr($fileContent, 0, $ivLength); 
    $ciphertext = substr($fileContent, $ivLength);
    
    // 2. Decrypt
    // If password is wrong, this returns false. 
    // We suppress warnings just in case, relying on the false check.
    $json = @openssl_decrypt($ciphertext, $method, $password, OPENSSL_RAW_DATA, $iv);
    
    if ($json === false) {
        // Force a specific exception message for the user
        throw new Exception("Incorrect password or corrupt backup file.");
    }
    
    $data = json_decode($json, true);
    if (!$data) {
        throw new Exception("Decryption successful, but data is invalid JSON.");
    }

    $pdo->beginTransaction();
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Clear Tables
    $tables = ['inventory', 'models', 'brands', 'device_types', 'users'];
    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM $table");
    }
    
    // Restore Users
    if (!empty($data['users'])) {
        $stmt = $pdo->prepare("INSERT INTO users (id, username, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)");
        foreach ($data['users'] as $row) $stmt->execute([$row['id'], $row['username'], $row['password_hash'], $row['role'], $row['created_at']]);
    }
    // Restore Types
    if (!empty($data['types'])) {
        $stmt = $pdo->prepare("INSERT INTO device_types (id, name) VALUES (?, ?)");
        foreach ($data['types'] as $row) $stmt->execute([$row['id'], $row['name']]);
    }
    // Restore Brands
    if (!empty($data['brands'])) {
        $stmt = $pdo->prepare("INSERT INTO brands (id, name) VALUES (?, ?)");
        foreach ($data['brands'] as $row) $stmt->execute([$row['id'], $row['name']]);
    }
    // Restore Models
    if (!empty($data['models'])) {
        $stmt = $pdo->prepare("INSERT INTO models (id, brand_id, name, eos_date) VALUES (?, ?, ?, ?)");
        foreach ($data['models'] as $row) $stmt->execute([$row['id'], $row['brand_id'], $row['name'], $row['eos_date']]);
    }
    // Restore Inventory
    if (!empty($data['inventory'])) {
        $stmt = $pdo->prepare("INSERT INTO inventory (id, hostname, ip_address, serial_number, asset_id, firmware_version, location, sub_location, status, type_id, brand_id, model_id, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['inventory'] as $row) {
            $stmt->execute([
                $row['id'], $row['hostname'], $row['ip_address'], $row['serial_number'], 
                $row['asset_id'], $row['firmware_version'], $row['location'], 
                $row['sub_location'] ?? null, 
                $row['status'],
                $row['type_id'], $row['brand_id'], $row['model_id'], $row['notes'],
                $row['created_at'], $row['updated_at']
            ]);
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    $pdo->commit();
    
    writeLog($pdo, 'RESTORE', 'System Database', 'Restored from backup');
    echo json_encode(["success" => true, "message" => "Database successfully restored."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // 3. Ensure we send a 500 error code so the frontend .catch() block triggers
    http_response_code(500); 
    echo json_encode(["message" => "Restore failed: " . $e->getMessage()]);
}
?>