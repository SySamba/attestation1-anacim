<?php
// Script to create admin user with correct password hash
require_once 'config/database.php';

$username = 'admin';
$password = 'admin123';
$email = 'admin@anacim.sn';

// Generate password hash
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Delete existing admin user
    $delete_sql = "DELETE FROM admin_users WHERE username = ?";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute([$username]);
    
    // Insert new admin user
    $sql = "INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $password_hash, $email]);
    
    echo "Admin user created successfully!\n";
    echo "Username: $username\n";
    echo "Password: $password\n";
    echo "Password hash: $password_hash\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
