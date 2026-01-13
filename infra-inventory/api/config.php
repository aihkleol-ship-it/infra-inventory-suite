<?php
// api/config.php

if (php_sapi_name() !== 'cli') {
    // 1. Start Session securely (Must be first)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 2. CORS Headers (Allow React to talk to PHP)
    header("Access-Control-Allow-Origin: *"); // For production, change * to your specific domain
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// 3. Database Connection
require_once __DIR__ . '/../../infra-system-config.php';
$db_name = 'infra_inventory';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(["error" => "Connection error: " . $e->getMessage()]);
    exit();
}
?>