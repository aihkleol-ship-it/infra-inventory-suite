<?php
// api/backup.php
include_once 'config.php';
include_once 'logger.php';

// 1. Security: Method Check & JSON Response
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit;
}

// 2. Security: Authorization Check (Admins Only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Unauthorized: Admin privileges required"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

if (empty($password)) {
    http_response_code(400); 
    echo json_encode(["message" => "Encryption password required"]); 
    exit;
}

try {
    // 3. Start Read-Only Transaction
    // We use raw SQL to specify 'READ ONLY', which optimizes the snapshot 
    // and ensures no accidental writes occur during backup.
    $pdo->exec("START TRANSACTION READ ONLY");

    $data = [];
    $data['inventory'] = $pdo->query("SELECT * FROM inventory")->fetchAll(PDO::FETCH_ASSOC);
    $data['brands']    = $pdo->query("SELECT * FROM brands")->fetchAll(PDO::FETCH_ASSOC);
    $data['models']    = $pdo->query("SELECT * FROM models")->fetchAll(PDO::FETCH_ASSOC);
    $data['types']     = $pdo->query("SELECT * FROM device_types")->fetchAll(PDO::FETCH_ASSOC);
    $data['users']     = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);

    // Commit immediately after reading to release the database snapshot
    $pdo->exec("COMMIT");

    // 4. Convert to JSON with strict error handling
    // JSON_THROW_ON_ERROR ensures we catch encoding issues (e.g. malformed UTF-8)
    $jsonData = json_encode($data, JSON_THROW_ON_ERROR);

    // 5. Encrypt (AES-256-CBC)
    $method = "aes-256-cbc";
    $ivLength = openssl_cipher_iv_length($method);
    
    // Security Check: Ensure the OS provided strong entropy
    $iv = openssl_random_pseudo_bytes($ivLength, $isStrong);
    if (!$isStrong) {
        throw new Exception("Host system failed to generate a secure Initialization Vector.");
    }
    
    $encrypted = openssl_encrypt($jsonData, $method, $password, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        throw new Exception("Encryption failed.");
    }
    
    // 6. Prepare Final Payload (IV + Ciphertext in Base64)
    $finalPayload = base64_encode($iv . $encrypted);

    // 7. Log & Respond
    // Only log success after everything else has worked
    writeLog($pdo, 'BACKUP', 'System Database', 'Full encrypted backup created.');

    echo json_encode([
        "success" => true,
        "filename" => "infra_backup_" . date('Y-m-d') . ".dat",
        "content" => $finalPayload
    ]);

} catch (Exception $e) {
    // Attempt rollback if the error occurred during the DB read phase.
    // We suppress errors here because if the error happened during Encryption,
    // the transaction is already committed, and ROLLBACK would throw a warning.
    try { $pdo->exec("ROLLBACK"); } catch (Exception $x) {}
    
    http_response_code(500);
    echo json_encode(["message" => "Backup failed: " . $e->getMessage()]);
}
?>