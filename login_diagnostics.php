<?php
/**
 * Login Diagnostics and Fix Script
 * This script helps diagnose and fix username/password issues
 */

echo "<!DOCTYPE html><html><head><title>Login Diagnostics</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
</style></head><body>";

echo "<h1>🔍 Login System Diagnostics</h1>";

try {
    require 'config/database.php';
    echo "<p class='success'>✅ Database connection: SUCCESS</p>";

    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        echo "<p class='error'>❌ Users table does not exist!</p>";
        echo "<p>You need to create the users table first.</p>";
        exit;
    }
    echo "<p class='success'>✅ Users table: EXISTS</p>";

    // Get all users
    $stmt = $pdo->query("SELECT id, username, password, role FROM users ORDER BY id");
    $users = $stmt->fetchAll();

    echo "<div class='section'>";
    echo "<h2>👥 Current Users</h2>";
    
    if (empty($users)) {
        echo "<p class='error'>❌ NO USERS FOUND!</p>";
        echo "<p class='warning'>⚠️  You need to create at least one admin user to access the system.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Password Status</th><th>Password Length</th></tr>";
        
        foreach ($users as $user) {
            $password_status = strlen($user['password']) == 60 ? "✅ Hashed" : "❌ Plain Text";
            $password_class = strlen($user['password']) == 60 ? "success" : "error";
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td class='$password_class'>$password_status</td>";
            echo "<td>" . strlen($user['password']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Check for unhashed passwords
        $unhashed_users = array_filter($users, function($user) {
            return strlen($user['password']) != 60;
        });

        if (!empty($unhashed_users)) {
            echo "<p class='warning'>⚠️  Some users have unhashed passwords. This will prevent login!</p>";
            echo "<p class='info'>ℹ️  Current passwords for unhashed users:</p>";
            echo "<ul>";
            foreach ($unhashed_users as $user) {
                echo "<li><strong>{$user['username']}</strong>: '{$user['password']}'</li>";
            }
            echo "</ul>";
        }
    }
    echo "</div>";

    // Quick fix section
    echo "<div class='section'>";
    echo "<h2>🔧 Quick Fixes</h2>";

    if ($_POST && isset($_POST['create_admin'])) {
        $username = trim($_POST['admin_username']) ?: 'admin';
        $password = trim($_POST['admin_password']) ?: 'admin123';
        
        // Check if admin already exists
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->execute([$username]);
        
        if ($check_stmt->fetchColumn()) {
            echo "<p class='error'>❌ User '$username' already exists!</p>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
            
            if ($insert_stmt->execute([$username, $hashed_password])) {
                echo "<p class='success'>✅ Admin user '$username' created successfully!</p>";
                echo "<p class='info'>📝 Login credentials: Username: <strong>$username</strong>, Password: <strong>$password</strong></p>";
            } else {
                echo "<p class='error'>❌ Failed to create admin user!</p>";
            }
        }
    }

    if ($_POST && isset($_POST['hash_passwords'])) {
        $updated_count = 0;
        foreach ($users as $user) {
            if (strlen($user['password']) != 60) {
                $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->execute([$hashed_password, $user['id']]);
                $updated_count++;
                echo "<p class='success'>✅ Hashed password for user: {$user['username']}</p>";
            }
        }
        
        if ($updated_count == 0) {
            echo "<p class='info'>ℹ️  All passwords are already properly hashed.</p>";
        } else {
            echo "<p class='success'>✅ Updated $updated_count passwords successfully!</p>";
        }
    }

    if ($_POST && isset($_POST['test_login'])) {
        $test_username = trim($_POST['test_username']);
        $test_password = $_POST['test_password'];
        
        if ($test_username && $test_password) {
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$test_username]);
            $user = $stmt->fetch();
            
            if ($user) {
                if (password_verify($test_password, $user['password'])) {
                    echo "<p class='success'>✅ LOGIN TEST SUCCESS for user '$test_username'</p>";
                } else {
                    echo "<p class='error'>❌ LOGIN TEST FAILED - Wrong password for user '$test_username'</p>";
                    echo "<p class='info'>ℹ️  User exists but password doesn't match</p>";
                }
            } else {
                echo "<p class='error'>❌ LOGIN TEST FAILED - User '$test_username' not found</p>";
            }
        }
    }

    echo "<form method='POST' style='margin: 10px 0;'>";
    echo "<h3>Create Admin User</h3>";
    echo "<p><label>Username: <input type='text' name='admin_username' placeholder='admin' /></label></p>";
    echo "<p><label>Password: <input type='text' name='admin_password' placeholder='admin123' /></label></p>";
    echo "<p><button type='submit' name='create_admin'>Create Admin User</button></p>";
    echo "</form>";

    if (!empty($users)) {
        echo "<form method='POST' style='margin: 10px 0;'>";
        echo "<h3>Hash All Plain Text Passwords</h3>";
        echo "<p><button type='submit' name='hash_passwords'>Hash All Passwords</button></p>";
        echo "</form>";

        echo "<form method='POST' style='margin: 10px 0;'>";
        echo "<h3>Test Login</h3>";
        echo "<p><label>Username: <input type='text' name='test_username' required /></label></p>";
        echo "<p><label>Password: <input type='password' name='test_password' required /></label></p>";
        echo "<p><button type='submit' name='test_login'>Test Login</button></p>";
        echo "</form>";
    }

    echo "</div>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    if (strpos($e->getMessage(), 'Connection refused') !== false || strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<div class='section'>";
        echo "<h3>🔧 Database Connection Issues</h3>";
        echo "<p class='warning'>Your database connection is failing. Check:</p>";
        echo "<ul>";
        echo "<li>XAMPP MySQL service is running</li>";
        echo "<li>Database credentials in config/database.php are correct</li>";
        echo "<li>Database 'attendance_system' exists</li>";
        echo "<li>MySQL root password is 'root' (as configured)</li>";
        echo "</ul>";
        echo "</div>";
    }
}

echo "</body></html>";
?>