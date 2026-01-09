<?php
// gateway/api/admin.php
session_start();
header("Content-Type: application/json");

// Connect DB
try {
    $pdo = new PDO("mysql:host=localhost;dbname=infra_gateway", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(Exception $e) { 
    http_response_code(500); die(json_encode(["message"=>"DB Connection Failed"])); 
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// --- PUBLIC ENDPOINTS (Login) ---

if ($method === 'POST' && isset($input['action']) && $input['action'] === 'login') {
    $user = $input['username'] ?? '';
    $pass = $input['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM gateway_users WHERE username = ?");
    $stmt->execute([$user]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($u && password_verify($pass, $u['password_hash'])) {
        $_SESSION['gw_user_id'] = $u['id'];
        $_SESSION['gw_username'] = $u['username'];
        echo json_encode(["success" => true, "user" => ["username" => $u['username'], "id" => $u['id']]]);
    } else {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
    }
    exit;
}

if ($method === 'POST' && isset($input['action']) && $input['action'] === 'logout') {
    session_destroy();
    echo json_encode(["success" => true]);
    exit;
}

// --- PROTECTED ZONE ---
if (!isset($_SESSION['gw_user_id'])) {
    http_response_code(401); echo json_encode(["message" => "Unauthorized"]); exit;
}

// --- PRIVATE ENDPOINTS ---

if ($method === 'GET') {
    if (isset($_GET['check_auth'])) {
        echo json_encode(["authenticated" => true, "username" => $_SESSION['gw_username'], "id" => $_SESSION['gw_user_id']]);
        exit;
    }

    // Dashboard Data
    $clients = $pdo->query("SELECT * FROM gateway_clients ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $settings = $pdo->query("SELECT * FROM gateway_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $logs = $pdo->query("SELECT l.*, c.app_name FROM gateway_logs l LEFT JOIN gateway_clients c ON l.client_id = c.id ORDER BY l.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    
    // NEW: Fetch Admin Users (excluding password hash)
    $admins = $pdo->query("SELECT id, username, created_at FROM gateway_users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "clients" => $clients,
        "logs" => $logs,
        "admins" => $admins, // Return admin list
        "config" => [
            "host" => $settings['smtp_host'] ?? '',
            "port" => $settings['smtp_port'] ?? '',
            "user" => $settings['smtp_user'] ?? '',
            "pass" => !empty($settings['smtp_pass']) ? "******" : "",
            "encryption" => $settings['smtp_encryption'] ?? 'tls',
            "from_email" => $settings['smtp_from_email'] ?? '',
            "from_name" => $settings['smtp_from_name'] ?? '',
            // Zabbix integration (do not expose raw token)
            "zabbix_url" => $settings['zabbix_url'] ?? '',
            "zabbix_token_set" => !empty($settings['zabbix_token'] ?? ''),
        ]
    ]);
} 

elseif ($method === 'POST') {
    // Client Management
    if ($input['action'] === 'create_client') {
        $key = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO gateway_clients (app_name, api_key) VALUES (?, ?)");
        $stmt->execute([$input['name'], $key]);
        echo json_encode(["success"=>true]);
    }
    elseif ($input['action'] === 'revoke_client') {
        $pdo->prepare("UPDATE gateway_clients SET status = 'revoked' WHERE id = ?")->execute([$input['id']]);
        echo json_encode(["success"=>true]);
    }
    
    // SMTP Config
    elseif ($input['action'] === 'save_smtp') {
        $updates = [
            'smtp_host' => $input['host'], 'smtp_port' => $input['port'],
            'smtp_user' => $input['user'], 'smtp_encryption' => $input['encryption'],
            'smtp_from_email' => $input['from_email'], 'smtp_from_name' => $input['from_name']
        ];
        if (!empty($input['pass']) && $input['pass'] !== '******') {
            $updates['smtp_pass'] = $input['pass'];
        }
        $stmt = $pdo->prepare("REPLACE INTO gateway_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($updates as $k => $v) $stmt->execute([$k, $v]);
        echo json_encode(["success"=>true]);
    }

    // Zabbix Config
    elseif ($input['action'] === 'save_zabbix') {
        $updates = [
            'zabbix_url' => trim($input['url'] ?? ''),
        ];
        // 只有在使用者真的有輸入新 token 時才更新，避免覆蓋原值
        if (!empty($input['token']) && $input['token'] !== '******') {
            $updates['zabbix_token'] = $input['token'];
        }
        $stmt = $pdo->prepare("REPLACE INTO gateway_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($updates as $k => $v) $stmt->execute([$k, $v]);
        echo json_encode(["success"=>true]);
    }

    // NEW: User Management
    elseif ($input['action'] === 'create_admin') {
        if (empty($input['username']) || empty($input['password'])) {
            http_response_code(400); echo json_encode(["message" => "Missing fields"]); exit;
        }
        $hash = password_hash($input['password'], PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO gateway_users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$input['username'], $hash]);
            echo json_encode(["success"=>true]);
        } catch (Exception $e) {
            http_response_code(400); echo json_encode(["message" => "Username taken"]);
        }
    }
    elseif ($input['action'] === 'delete_admin') {
        if ($input['id'] == $_SESSION['gw_user_id']) {
            http_response_code(400); echo json_encode(["message" => "Cannot delete yourself"]); exit;
        }
        $pdo->prepare("DELETE FROM gateway_users WHERE id = ?")->execute([$input['id']]);
        echo json_encode(["success"=>true]);
    }
    elseif ($input['action'] === 'update_password') {
        if (empty($input['password'])) {
            http_response_code(400); echo json_encode(["message" => "Password required"]); exit;
        }
        $hash = password_hash($input['password'], PASSWORD_DEFAULT);
        $targetId = $input['id'] ?? $_SESSION['gw_user_id'];
        $pdo->prepare("UPDATE gateway_users SET password_hash = ? WHERE id = ?")->execute([$hash, $targetId]);
        echo json_encode(["success"=>true]);
    }
}
?>