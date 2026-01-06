<?php
// gateway/api/setup.php
$host = 'localhost'; $user = 'root'; $pass = ''; $dbname = 'infra_gateway';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // 1. Clients Table (API Keys)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `gateway_clients` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `app_name` VARCHAR(100) NOT NULL,
        `api_key` VARCHAR(64) NOT NULL UNIQUE,
        `status` ENUM('active', 'revoked') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Settings Table (SMTP Config)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `gateway_settings` (
        `setting_key` VARCHAR(50) PRIMARY KEY,
        `setting_value` TEXT
    )");

    // 3. Logs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `gateway_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `client_id` INT,
        `recipient` VARCHAR(150),
        `subject` VARCHAR(255),
        `status` VARCHAR(20),
        `error_msg` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. NEW: Users Table (Admin Access)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `gateway_users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Seed Settings
    $defaults = [
        'smtp_host' => 'smtp.gmail.com', 'smtp_port' => '587', 'smtp_user' => '', 'smtp_pass' => '',
        'smtp_encryption' => 'tls', 'smtp_from_email' => 'gateway@local', 'smtp_from_name' => 'Gateway'
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO gateway_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);

    // Seed Default Admin (admin / secret123)
    // Only insert if table is empty to prevent overwriting custom passwords
    $check = $pdo->query("SELECT COUNT(*) FROM gateway_users")->fetchColumn();
    if ($check == 0) {
        $hash = password_hash('secret123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO gateway_users (username, password_hash) VALUES (?, ?)")->execute(['admin', $hash]);
        echo "Default Admin Created: admin / secret123<br>";
    }

    echo "Gateway Database Initialized/Updated Successfully.";

} catch (PDOException $e) { die("Setup Error: " . $e->getMessage()); }
?>