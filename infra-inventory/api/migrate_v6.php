<?php
// api/migrate_v6.php
include_once 'config.php';

echo "<h1>Migrating InfraInventory to V6.0 (Gateway Client)</h1>";

try {
    // Insert default Gateway settings
    $defaults = [
        'gateway_url' => 'http://localhost/infra-gateway/api/send.php',
        'gateway_key' => '', // User must paste the key from Gateway Admin UI
        'alert_email_recipient' => '' // Kept from previous versions
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    
    foreach ($defaults as $key => $val) {
        $stmt->execute([$key, $val]);
        echo "<li>Initialized setting: <strong>$key</strong></li>";
    }
    
    // Optional: Clean up old SMTP settings to avoid confusion
    // $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'smtp_%'");
    // echo "<li>Cleanup: Old SMTP settings hidden.</li>";
    
    echo "<h3 style='color:green'>Success: Ready to connect to InfraGateway.</h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>