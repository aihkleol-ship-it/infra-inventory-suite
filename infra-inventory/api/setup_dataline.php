<?php
header('Content-Type: text/plain; charset=utf-8');

require_once 'config.php'; // Use the existing config for credentials

try {
    // 1. Connect to the database using credentials from config.php
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✓ Connected to database '$db_name' successfully.\n\n";

    // 2. Define the SQL statement for creating the 'dataline' table
    $sql = "
    CREATE TABLE IF NOT EXISTS `dataline` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `site` VARCHAR(255) DEFAULT NULL,
        `usage_type` VARCHAR(100) DEFAULT NULL,
        `bandwidth` VARCHAR(50) DEFAULT NULL,
        `end_device` VARCHAR(255) DEFAULT NULL,
        `end_device_mgmt_ip` VARCHAR(255) DEFAULT NULL,
        `wan_ip` VARCHAR(255) DEFAULT NULL,
        `wan_ip_count` VARCHAR(50) DEFAULT NULL,
        `gateway` VARCHAR(255) DEFAULT NULL,
        `service_provider` VARCHAR(255) DEFAULT NULL,
        `circuit_id` VARCHAR(255) DEFAULT NULL,
        `circuit_description` TEXT DEFAULT NULL,
        `installation_address` TEXT DEFAULT NULL,
        `account_number` VARCHAR(255) DEFAULT NULL,
        `account_name` VARCHAR(255) DEFAULT NULL,
        `monthly_charge_hkd` VARCHAR(100) DEFAULT NULL,
        `contract_end_date` DATE DEFAULT NULL,
        `contract_status` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ";

    // 3. Execute the SQL statement
    $pdo->exec($sql);

    echo "✓ 'dataline' table has been created successfully (if it didn't exist).\n";
    echo "→ You can now proceed to the next step.\n";

} catch (PDOException $e) {
    // http_response_code(500);
    die("✗ Database Error: " . $e->getMessage() . "\n");
}

