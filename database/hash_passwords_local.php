<?php
/**
 * Simple Password Hashing Script for Local Development
 */

// Local database connection
$host = 'localhost';
$db = 'attendance_system';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Password Hashing Script\n";
    echo "======================\n";

    // Get all users with their current passwords
    $stmt = $pdo->query("SELECT id, username, password FROM users");
    $users = $stmt->fetchAll();

    $updated_count = 0;

    foreach ($users as $user) {
        // Check if password is already hashed (hashed passwords are 60 characters long)
        if (strlen($user['password']) !== 60) {
            echo "Current password for {$user['username']}: '{$user['password']}'\n";
            
            // Hash the plain text password
            $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
            
            // Update the user's password
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->execute([$hashed_password, $user['id']]);
            
            echo "✅ Updated password for user: {$user['username']}\n";
            echo "   New hash: " . substr($hashed_password, 0, 20) . "...\n\n";
            $updated_count++;
        } else {
            echo "⚠️  Password already hashed for user: {$user['username']}\n";
        }
    }

    echo "======================\n";
    echo "Summary: {$updated_count} passwords updated successfully!\n";
    echo "You can now log in with your original passwords.\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>