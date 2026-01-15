<?php
/**
 * InfraInventory - Unified Database Installer & Migrator
 * This script handles both fresh installations and schema upgrades.
 * Features: Reads config.php for credentials, allows manual override.
 */

// --- 1. Auto-Detect Credentials from config.php ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'infra_inventory';

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    $configContent = file_get_contents($configPath);
    // Regex to find variables safely without executing the file (which might error if DB missing)
    if (preg_match('/\$host\s*=\s*[\'"](.*?)[\'"]/', $configContent, $m)) $db_host = $m[1];
    if (preg_match('/\$username\s*=\s*[\'"](.*?)[\'"]/', $configContent, $m)) $db_user = $m[1];
    if (preg_match('/\$password\s*=\s*[\'"](.*?)[\'"]/', $configContent, $m)) $db_pass = $m[1];
    // Note: DB name is usually hardcoded in logic below, but we can try to find it too
    if (preg_match('/\$db_name\s*=\s*[\'"](.*?)[\'"]/', $configContent, $m)) $db_name = $m[1];
}

// Override with POST data if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['host'] ?? $db_host;
    $db_user = $_POST['user'] ?? $db_user;
    $db_pass = $_POST['pass'] ?? $db_pass;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Install - InfraInventory</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; padding: 40px; background: #f8fafc; color: #1e293b; line-height: 1.5; }
        .card { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        h1 { margin-top: 0; color: #0f172a; font-size: 24px; text-align: center; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 12px; font-weight: bold; text-transform: uppercase; color: #64748b; margin-bottom: 5px; }
        input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-family: monospace; }
        input:focus { outline: none; border-color: #3b82f6; ring: 2px solid #3b82f6; }
        .btn { display: block; width: 100%; background: #2563eb; color: white; padding: 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; font-size: 16px; margin-top: 20px; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .log { background: #0f172a; color: #10b981; padding: 15px; border-radius: 6px; font-family: monospace; margin-top: 20px; white-space: pre-wrap; font-size: 12px; overflow-x: auto; }
        .error { color: #ef4444; background: #fee2e2; padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 14px; border: 1px solid #fecaca; }
        .success-box { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>

<div class="card">
    <h1>🚀 System Installer & Upgrader</h1>
    
    <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <p style="text-align:center; color:#64748b; font-size:14px; margin-bottom:25px;">
            Confirm your database credentials below to initialize the system.
        </p>
        <form method="POST">
            <div class="form-group">
                <label>Database Host</label>
                <input type="text" name="host" value="<?= htmlspecialchars($db_host) ?>" required>
            </div>
            <div class="form-group">
                <label>Database User</label>
                <input type="text" name="user" value="<?= htmlspecialchars($db_user) ?>" required>
            </div>
            <div class="form-group">
                <label>Database Password</label>
                <input type="password" name="pass" value="<?= htmlspecialchars($db_pass) ?>" placeholder="(Leave empty if no password)">
            </div>
            <div class="form-group">
                <label>Database Name (Will be Created)</label>
                <input type="text" value="<?= htmlspecialchars($db_name) ?>" disabled style="background:#f1f5f9; color:#94a3b8;">
            </div>
            <button type="submit" class="btn">Connect & Install / Upgrade</button>
        </form>
    <?php else: ?>
        
        <?php
        try {
            // 1. Connect to MySQL Server (No DB selected yet)
            $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo "<div class='log'>";
            echo "✓ Authenticated with MySQL server successfully.\n";

            // 2. Create Database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "✓ Database '$db_name' checked/created.\n";
            
            $pdo->exec("USE `$db_name`");
            echo "✓ Connected to database '$db_name'.\n\n";

            // 3. Check if this is a fresh install or an upgrade
            $stmt = $pdo->query("SHOW TABLES LIKE 'inventory'");
            $isFreshInstall = $stmt->rowCount() === 0;

            if ($isFreshInstall) {
                echo "→ Detected fresh installation. Setting up schema...\n";

                // --- CRITICAL FIX: Generate Hash Dynamically ---
                $pHash = password_hash('password123', PASSWORD_DEFAULT);

                // 4. Schema V4.1 (Full Schema)
                $sql = <<<SQL
                SET FOREIGN_KEY_CHECKS = 0;

                DROP TABLE IF EXISTS `system_settings`, `users`, `device_types`, `brands`, `models`, `inventory`, `audit_logs`;

                CREATE TABLE `system_settings` ( `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY, `setting_value` TEXT DEFAULT NULL ) ENGINE=InnoDB;
                CREATE TABLE `users` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `username` VARCHAR(50) NOT NULL UNIQUE, `password_hash` VARCHAR(255) NOT NULL, `role` VARCHAR(20) DEFAULT 'editor', `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB;
                CREATE TABLE `device_types` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(50) NOT NULL UNIQUE ) ENGINE=InnoDB;
                CREATE TABLE `brands` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(50) NOT NULL UNIQUE ) ENGINE=InnoDB;
                CREATE TABLE `models` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `brand_id` INT NOT NULL, `name` VARCHAR(100) NOT NULL, `eos_date` DATE DEFAULT NULL, FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE CASCADE ) ENGINE=InnoDB;
                CREATE TABLE `audit_logs` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT, `username` VARCHAR(50), `action` VARCHAR(20), `target` VARCHAR(100), `details` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB;

                CREATE TABLE `inventory` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `hostname` VARCHAR(100) NOT NULL,
                    `ip_address` VARCHAR(45) DEFAULT NULL,
                    `serial_number` VARCHAR(100) NOT NULL UNIQUE,
                    `asset_id` VARCHAR(50) DEFAULT NULL,
                    `firmware_version` VARCHAR(50) DEFAULT NULL,
                    `location` VARCHAR(255) DEFAULT NULL,
                    `sub_location` VARCHAR(255) DEFAULT NULL,
                    `rack` VARCHAR(255) DEFAULT NULL,
                    `rack_position` VARCHAR(255) DEFAULT NULL,
                    `status` ENUM('Active', 'Decommissioned', 'In-Stock') DEFAULT 'Active',
                    `notes` TEXT DEFAULT NULL,
                    `type_id` INT NOT NULL,
                    `brand_id` INT NOT NULL,
                    `model_id` INT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`type_id`) REFERENCES `device_types`(`id`),
                    FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`),
                    FOREIGN KEY (`model_id`) REFERENCES `models`(`id`)
                ) ENGINE=InnoDB;

                -- Seed Data
                INSERT INTO `users` (`username`, `password_hash`, `role`) VALUES 
                ('admin', '$pHash', 'admin'),
                ('viewer', '$pHash', 'viewer');

                INSERT INTO `device_types` (`name`) VALUES ('Server'), ('Switch'), ('Router'), ('Firewall'), ('Storage'), ('PDU'), ('Discovered');
                INSERT INTO `brands` (`name`) VALUES ('Cisco'), ('Dell'), ('HP'), ('Fortinet'), ('Synology'), ('Zabbix');
                
                INSERT INTO `models` (`brand_id`, `name`, `eos_date`) VALUES 
                (1, 'Catalyst 9300', '2030-01-01'), 
                (2, 'PowerEdge R740', '2027-12-31'), 
                (4, 'FortiGate 100F', '2028-11-30'),
                (6, 'Zabbix Host', NULL);

                INSERT INTO `inventory` (`hostname`, `ip_address`, `serial_number`, `type_id`, `brand_id`, `model_id`, `location`, `sub_location`, `rack`, `rack_position`, `status`) VALUES 
                ('SW-CORE-01', '192.168.1.1', 'FOC12345678', 2, 1, 1, 'HQ Server Room', 'Rack A1 - U40', 'A1', 'U40', 'Active'),
                ('SRV-APP-01', '192.168.1.10', 'DEL12345678', 1, 2, 2, 'HQ Server Room', 'Rack B2 - U10', 'B2', 'U10', 'Active');

                INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
                ('gateway_url', ''),
                ('gateway_key', '');

                SET FOREIGN_KEY_CHECKS = 1;
SQL;
                
                $pdo->exec($sql);
                echo "✓ All tables created successfully.\n";
                echo "✓ Default data seeded.\n";

                // --- VERIFICATION STEP ---
                echo "\n→ Verifying seeded data...\n";
                $typeCount = $pdo->query("SELECT COUNT(*) FROM device_types WHERE name = 'Discovered'")->fetchColumn();
                $brandCount = $pdo->query("SELECT COUNT(*) FROM brands WHERE name = 'Zabbix'")->fetchColumn();
                $modelCount = $pdo->query("SELECT COUNT(*) FROM models WHERE name = 'Zabbix Host'")->fetchColumn();

                if ($typeCount > 0 && $brandCount > 0 && $modelCount > 0) {
                    echo "✓ Verification successful! Default categories are present.\n";
                } else {
                    echo "✗ Verification failed! Default categories were not created correctly.\n";
                    echo "  - 'Discovered' in device_types: $typeCount\n";
                    echo "  - 'Zabbix' in brands: $brandCount\n";
                    echo "  - 'Zabbix Host' in models: $modelCount\n";
                }
            } else {
                echo "→ Detected existing installation. Checking for upgrades...\n";
                
                // --- MIGRATION LOGIC ---
                // Check for migration from v1.0 (add rack and rack_position)
                $invColumns = $pdo->query("SHOW COLUMNS FROM `inventory` LIKE 'rack'")->rowCount();
                if ($invColumns === 0) {
                    echo "  → Applying v1.0 migration (adding rack/position columns)...\n";
                    $pdo->exec("ALTER TABLE `inventory` 
                                ADD COLUMN `rack` VARCHAR(255) DEFAULT NULL AFTER `sub_location`,
                                ADD COLUMN `rack_position` VARCHAR(255) DEFAULT NULL AFTER `rack`;");
                    echo "  ✓ Migration v1.0 complete.\n";
                } else {
                    echo "  ✓ Schema is up to date.\n";
                }

                // Add future migration checks here in else-if blocks
            }

            echo "</div>";
            
            echo "<div class='success-box'>";
            echo "<h3 style='color:green; font-size:20px;'>✅ System Ready!</h3>";
            if ($isFreshInstall) {
                echo "<p style='color:#64748b;'>Installation complete. You can now log in with <b>admin / password123</b></p>";
            } else {
                echo "<p style='color:#64748b;'>Upgrade process complete. Your system is up to date.</p>";
            }
            echo "<a href='../index.html' class='btn' style='background:#10b981; margin-top:15px; text-decoration:none; display:inline-block; width:auto; padding:10px 30px;'>Go to Login</a>";
            echo "</div>";

        } catch (PDOException $e) {
            echo "<div class='error'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<p style='text-align:center; margin-top:20px;'><a href='setup_database.php' style='color:#2563eb;'>Try Again</a></p>";
        }
        ?>

    <?php endif; ?>
</div>

</body>
</html>