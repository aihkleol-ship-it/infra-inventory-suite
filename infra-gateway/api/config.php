<?php
// infra-gateway/api/config.php

// --- Database Settings ---
$db_host = 'localhost';
$db_name = 'infra_gateway';
$db_user = 'root';
$db_pass = ''; // Default empty password for Laragon

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    // Return a JSON error to ensure client-side code can parse it
    echo json_encode(["status" => "error", "message" => "Gateway database connection failed: " . $e->getMessage()]);
    exit;
}