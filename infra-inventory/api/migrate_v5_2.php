<?php
// api/migrate_v5_2.php
include_once 'config.php';

echo "<h1>Migrating Database to V5.2 (Email Microservice)</h1>";

try {
    // Generate a random strong API key for the service
    $apiKey = bin2hex(random_bytes(32)); // 64 characters

    // Insert API Key setting
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('service_api_key', ?)");
    $stmt->execute([$apiKey]);
    
    echo "<li>Checked/Inserted key: <strong>service_api_key</strong></li>";
    echo "<h3 style='color:green'>Success: Email Microservice ready.</h3>";
    echo "<p>Your API Key is generated. View it in the Settings page.</p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>