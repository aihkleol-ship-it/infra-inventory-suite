<?php
// api/users.php
include_once 'config.php';
include_once 'logger.php';

// Security: Only logged-in Admins can manage users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Unauthorized: Admins only"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// --- GET: List Users ---
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// --- POST: Add User ---
elseif ($method === 'POST') {
    if (empty($input['username']) || empty($input['password'])) {
        http_response_code(400); echo json_encode(["message" => "Username and Password required"]); exit;
    }

    try {
        $hash = password_hash($input['password'], PASSWORD_DEFAULT);
        $role = $input['role'] ?? 'editor';
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute([trim($input['username']), $hash, $role]);
        
        writeLog($pdo, 'USER_ADD', "Added user: " . $input['username']);
        echo json_encode(["success" => true, "message" => "User added"]);
    } catch (PDOException $e) {
        http_response_code(500); echo json_encode(["message" => "Error: User likely exists."]);
    }
}

// --- PUT: Update User (Edit / Reset Password) ---
elseif ($method === 'PUT') {
    if (empty($input['id'])) { http_response_code(400); echo json_encode(["message" => "ID required"]); exit; }
    
    // Safety: Prevent changing your own ROLE (to avoid locking yourself out)
    // You can still change your own password or username.
    if ($input['id'] == $_SESSION['user_id'] && isset($input['role']) && $input['role'] !== $_SESSION['role']) {
        http_response_code(400); echo json_encode(["message" => "Safety: You cannot change your own role."]); exit;
    }

    $fields = [];
    $params = [];

    // 1. Update Username
    if (!empty($input['username'])) {
        $fields[] = "username = ?";
        $params[] = trim($input['username']);
    }

    // 2. Update Role
    if (!empty($input['role'])) {
        $fields[] = "role = ?";
        $params[] = $input['role'];
    }

    // 3. Reset Password (only if provided)
    if (!empty($input['password'])) {
        $fields[] = "password_hash = ?";
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }

    if (empty($fields)) {
        echo json_encode(["success" => true, "message" => "No changes detected"]);
        exit;
    }

    $params[] = $input['id']; // WHERE clause ID

    try {
        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        writeLog($pdo, 'USER_UPDATE', "Updated User ID: " . $input['id']);
        echo json_encode(["success" => true, "message" => "User updated successfully"]);
    } catch (PDOException $e) {
        http_response_code(500); echo json_encode(["message" => "Update failed. Username may be taken."]);
    }
}

// --- DELETE: Remove User ---
elseif ($method === 'DELETE') {
    if (empty($input['id'])) { http_response_code(400); echo json_encode(["message" => "ID required"]); exit; }
    
    if ($input['id'] == $_SESSION['user_id']) {
        http_response_code(400); echo json_encode(["message" => "Cannot delete your own account"]); exit;
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$input['id']]);
    writeLog($pdo, 'USER_DEL', "Deleted user ID: " . $input['id']);
    echo json_encode(["success" => true, "message" => "User deleted"]);
}
?>