<?php
// api/setup_audit.php
include_once 'config.php';

echo "<h1>Setting up Audit Trails...</h1>";

try {
    $sql = "CREATE TABLE IF NOT EXISTS `audit_logs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT,
      `username` VARCHAR(50),
      `action` VARCHAR(20),  -- e.g., CREATE, UPDATE, DELETE
      `target` VARCHAR(100), -- e.g., 'Device: SW-CORE-01'
      `details` TEXT,        -- e.g., 'Changed IP from 1.1 to 1.2'
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "<h3 style='color:green'>Success! Table 'audit_logs' created.</h3>";
    echo "You can now delete this file.";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error</h3>";
    echo $e->getMessage();
}
?>