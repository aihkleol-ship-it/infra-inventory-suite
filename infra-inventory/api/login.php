<?php
// api/login.php
include_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $input['username'] ?? '';
    $pass = $input['password'] ?? '';

    // Fetch user from DB
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify Password
    if ($userData && password_verify($pass, $userData['password_hash'])) {
        // Success: Save to Session
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['role'] = $userData['role'];
        
        echo json_encode([
            "success" => true, 
            "message" => "Login successful",
            "user" => [
                "username" => $userData['username'],
                "role" => $userData['role']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
    }
}
?>