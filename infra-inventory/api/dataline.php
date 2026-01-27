<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // For now, we will just fetch all records.
    // We can add pagination, searching, and filtering later.
    $stmt = $pdo->query("SELECT * FROM dataline ORDER BY id DESC");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
