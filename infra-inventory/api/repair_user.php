<?php
// api/repair_user.php
include_once 'config.php';

// Fix: config.php sets the content type to JSON. 
// We must override it to HTML so you can see the message below.
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>Repairing Admin User...</h1>";

try {
    // 1. Delete the old admin user if it exists (to prevent duplicates)
    $stmt = $pdo->prepare("DELETE FROM users WHERE username = 'admin'");
    $stmt->execute();
    echo "Old 'admin' user removed.<br>";

    // 2. Create a FRESH hash using your server's specific algorithm
    $password = 'password123';
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    
    // 3. Insert the new user
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
    $stmt->execute(['admin', $newHash, 'admin']);

    echo "<h3 style='color:green'>Success!</h3>";
    echo "User: <b>admin</b><br>";
    echo "Pass: <b>password123</b><br>";
    echo "<br>The password hash has been regenerated for your specific PHP version.<br>";
    echo "Please <a href='../index.html'>Go Back to Login</a> and try again.";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Database Error</h3>";
    echo $e->getMessage();
}
?>