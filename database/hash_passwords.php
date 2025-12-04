<?php
/**
 * Password Hashing Script
 * Run this ONCE to convert existing plain text passwords to hashed passwords
 * IMPORTANT: Backup your database before running this script
 */

// Include database connection
require_once __DIR__ . '/../config/database.php';

echo "Password Hashing Script\n";
echo "======================\n";

try {
    // Get all users with plain text passwords
    $stmt = $pdo->query("SELECT id, username, password FROM users");
    $users = $stmt->fetchAll();
    
    $updated_count = 0;
    
    foreach ($users as $user) {
        // Check if password is already hashed (hashed passwords are 60 characters long)
        if (strlen($user['password']) !== 60) {
            // Hash the plain text password
            $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
            
            // Update the user's password
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->execute([$hashed_password, $user['id']]);
            
            echo "✅ Updated password for user: {$user['username']}\n";
            $updated_count++;
        } else {
            echo "⚠️  Password already hashed for user: {$user['username']}\n";
        }
    }
    
    echo "\n======================\n";
    echo "Summary: {$updated_count} passwords updated successfully!\n";
    echo "You can now delete this file for security.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>