<?php
// api/migrate_v5_1.php
include_once 'config.php';

echo "<h1>Migrating Database to V5.1 (SMTP Module) - Fix</h1>";

try {
    // 1. [Fix] Ensure system_settings table exists (Previously in V5.0)
    // 這是新增的修復：確保資料表一定存在
    $sql = "CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_key` VARCHAR(50) PRIMARY KEY,
        `setting_value` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "<li>Checked/Created table: <strong>system_settings</strong></li>";

    // 2. Insert default SMTP settings
    // encryption options: 'tls', 'ssl', or '' (none)
    $defaults = [
        'alert_email_recipient' => '', // V5.0 setting
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_user' => '',
        'smtp_pass' => '', 
        'smtp_encryption' => 'tls',
        'smtp_from_email' => 'admin@example.com',
        'smtp_from_name' => 'InfraInventory'
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    
    foreach ($defaults as $key => $val) {
        $stmt->execute([$key, $val]);
        echo "<li>Checked/Inserted key: <strong>$key</strong></li>";
    }
    
    echo "<h3 style='color:green'>Success: SMTP settings initialized.</h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>