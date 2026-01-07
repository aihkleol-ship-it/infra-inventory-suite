<?php
include_once 'config.php';
header('Content-Type: text/plain');

try {
    $pdo->exec("ALTER TABLE `inventory` ADD COLUMN `rack` VARCHAR(255) DEFAULT NULL AFTER `sub_location`;");
    echo "SUCCESS: 'rack' column added.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) { // More generic check
        echo "INFO: 'rack' column already exists.\n";
    } else {
        echo "ERROR adding 'rack' column: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec("ALTER TABLE `inventory` ADD COLUMN `rack_position` VARCHAR(255) DEFAULT NULL AFTER `rack`;");
    echo "SUCCESS: 'rack_position' column added.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) { // More generic check
        echo "INFO: 'rack_position' column already exists.\n";
    } else {
        echo "ERROR adding 'rack_position' column: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration script finished.";
?>
