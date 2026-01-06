<?php
// api/migrate_v3.php
include_once 'config.php';

echo "<h1>Migrating Database to V3.3</h1>";

try {
    // Add sub_location column if it doesn't exist
    $sql = "ALTER TABLE inventory ADD COLUMN sub_location VARCHAR(255) DEFAULT NULL AFTER location";
    $pdo->exec($sql);
    echo "<h3 style='color:green'>Success: 'sub_location' column added to inventory table.</h3>";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') { // Column already exists
        echo "<h3 style='color:orange'>Notice: Column 'sub_location' already exists. No changes made.</h3>";
    } else {
        echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
    }
}
?>