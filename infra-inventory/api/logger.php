<?php
// api/logger.php

function writeLog($pdo, $action, $target, $details = '') {
    // 1. Identify User
    $userId = $_SESSION['user_id'] ?? 0;
    $username = 'System';
    
    if ($userId) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) $username = $user['username'];
    }

    // 2. Record Log
    try {
        $sql = "INSERT INTO audit_logs (user_id, username, action, target, details) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $username, $action, $target, $details]);
    } catch (Exception $e) {
        // Silently fail logging to not break the main app
        error_log("Audit Log Error: " . $e->getMessage());
    }
}
?>