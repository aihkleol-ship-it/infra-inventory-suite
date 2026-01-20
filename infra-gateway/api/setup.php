<?php
// gateway/api/setup.php
require_once __DIR__ . '/../../infra-system-config.php';
$dbname = 'infra_gateway';

/**
 * Generates a random, secure password.
 * @param int $length
 * @return string
 */
function generate_random_password(int $length = 16): string {
    return bin2hex(random_bytes($length / 2));
}

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Table creations...
    $pdo->exec("CREATE TABLE IF NOT EXISTS `gateway_clients` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `app_name` VARCHAR(100) NOT NULL,
        `api_key` VARCHAR(64) NOT NULL UNIQUE,
        `status` ENUM('active', 'revoked') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `gateway_settings` (
        `setting_key` VARCHAR(50) PRIMARY KEY,
        `setting_value` TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `gateway_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `client_id` INT,
        `recipient` VARCHAR(150),
        `subject` VARCHAR(255),
        `status` VARCHAR(20),
        `error_msg` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `gateway_users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Seed Settings
    $defaults = [
        'smtp_host' => 'smtp.gmail.com', 'smtp_port' => '587', 'smtp_user' => '', 'smtp_pass' => '',
        'smtp_encryption' => 'tls', 'smtp_from_email' => 'gateway@local', 'smtp_from_name' => 'Gateway',
        'zabbix_url' => '', 'zabbix_user' => '', 'zabbix_pass' => '',
        'zabbix_token' => '', 'zabbix_token_expires' => ''
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO gateway_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);

    // Seed Default Admin with a random password
    $check = $pdo->query("SELECT COUNT(*) FROM gateway_users")->fetchColumn();
    if ($check == 0) {
        $new_password = generate_random_password();
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO gateway_users (username, password_hash) VALUES (?, ?)")->execute(['admin', $hash]);
        
        echo "<h1>Gateway Admin User Created</h1>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> <code style='background:#f1f1f1;padding:4px;'>" . htmlspecialchars($new_password) . "</code></p>";
        echo "<p style='color:red;'>Please save this password securely. It will not be shown again.</p><hr>";
    }

    echo "Gateway Database Initialized/Updated Successfully.";

} catch (PDOException $e) { die("Setup Error: " . $e->getMessage()); }
?>