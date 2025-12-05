<?php
/**
 * HOSTING PASSWORD RESET SCRIPT
 * Use this ONLY on your hosting server to reset admin password
 * Delete this file after use for security!
 */

echo "<!DOCTYPE html><html><head><title>Hosting Password Reset</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .warning { color: orange; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .credentials { background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #007cba; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #f8f9fa; }
    button { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
    button:hover { background: #005a8b; }
    .danger-btn { background: #dc3545; }
    .danger-btn:hover { background: #c82333; }
    input[type='text'], input[type='password'] { padding: 8px; margin: 5px; border: 1px solid #ddd; border-radius: 4px; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔐 Hosting Server Password Reset</h1>";

// Detect environment
$is_hosting = !(
    (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) ||
    !isset($_SERVER['HTTP_HOST'])
);

if ($is_hosting) {
    echo "<div class='info'>🌐 <strong>HOSTING ENVIRONMENT DETECTED</strong><br>";
    echo "Server: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "</div>";
} else {
    echo "<div class='warning'>⚠️ <strong>LOCAL ENVIRONMENT DETECTED</strong><br>";
    echo "This script is designed for hosting servers. Use password_reset.php for local development.</div>";
}

try {
    require 'config/database.php';
    echo "<div class='success'>✅ Database connected successfully!</div>";

    if ($_POST && isset($_POST['reset_admin_hosting'])) {
        echo "<div class='info'>";
        echo "<h2>🔄 Resetting Admin Password for Hosting</h2>";
        
        // Set admin password to the same as local: 'admin'
        $admin_password = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $admin_updated = $stmt->execute([$admin_password]);
        
        if ($admin_updated && $stmt->rowCount() > 0) {
            echo "<div class='success'>✅ Admin password reset successfully!</div>";
            echo "<div class='credentials'>";
            echo "<h3>🔐 Your Hosting Login Credentials:</h3>";
            echo "<p><strong>Username:</strong> <code>admin</code><br>";
            echo "<strong>Password:</strong> <code>admin</code></p>";
            echo "<p><em>This now matches your local development credentials!</em></p>";
            echo "</div>";
            
            echo "<div class='info'>💡 <strong>Next Steps:</strong>";
            echo "<ol>";
            echo "<li>Try logging in with: admin / admin</li>";
            echo "<li>If successful, change your password from the admin panel</li>";
            echo "<li><strong>DELETE THIS FILE</strong> for security!</li>";
            echo "</ol></div>";
        } else {
            echo "<div class='error'>❌ Failed to update admin password or admin user not found!</div>";
        }
        echo "</div>";
        
    } else if ($_POST && isset($_POST['create_admin_hosting'])) {
        echo "<div class='info'>";
        echo "<h2>➕ Creating Admin User for Hosting</h2>";
        
        // Check if admin exists
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn()) {
            echo "<div class='error'>❌ Admin user already exists! Use the reset password option instead.</div>";
        } else {
            // Create admin user with password 'admin'
            $admin_password = password_hash('admin', PASSWORD_DEFAULT);
            $insert_stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')");
            
            if ($insert_stmt->execute([$admin_password])) {
                echo "<div class='success'>✅ Admin user created successfully!</div>";
                echo "<div class='credentials'>";
                echo "<h3>🔐 Your New Admin Login:</h3>";
                echo "<p><strong>Username:</strong> <code>admin</code><br>";
                echo "<strong>Password:</strong> <code>admin</code></p>";
                echo "</div>";
            } else {
                echo "<div class='error'>❌ Failed to create admin user!</div>";
            }
        }
        echo "</div>";
    }

    // Show current users
    echo "<h2>👥 Current Users in Hosting Database</h2>";
    $stmt = $pdo->query("SELECT id, username, role, 
                         CASE 
                            WHEN LENGTH(password) = 60 THEN 'Hashed (Secure)' 
                            ELSE CONCAT('Plain Text: ', password) 
                         END as password_status
                         FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    if (!empty($users)) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Password Status</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['password_status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ No users found in database! You need to create an admin user.</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<div class='warning'>";
        echo "<h3>🔧 Database Connection Issues</h3>";
        echo "<p>Your hosting database credentials might be incorrect. Check:</p>";
        echo "<ul>";
        echo "<li>Database name: <code>u145148023_attendance</code></li>";
        echo "<li>Username: <code>u145148023_Bmi_admin</code></li>";
        echo "<li>Password is correct in config/database.php</li>";
        echo "<li>Database server is running</li>";
        echo "</ul>";
        echo "</div>";
    }
}
?>

<h2>🔧 Reset Options</h2>

<form method="POST" style="margin: 15px 0;">
    <h3>🔄 Reset Admin Password</h3>
    <p>Reset admin password to match your local development environment:</p>
    <div class='credentials'>
        <p><strong>This will set:</strong><br>
        Username: <code>admin</code><br>
        Password: <code>admin</code></p>
    </div>
    <button type="submit" name="reset_admin_hosting">
        🔑 Reset Admin Password to 'admin'
    </button>
</form>

<form method="POST" style="margin: 15px 0;">
    <h3>➕ Create Admin User</h3>
    <p>Use this only if no admin user exists:</p>
    <button type="submit" name="create_admin_hosting" class="danger-btn">
        👤 Create Admin User
    </button>
</form>

<div class="warning">
    <h3>⚠️ Security Notice</h3>
    <p><strong>IMPORTANT:</strong> After successfully logging in, please:</p>
    <ol>
        <li>Change your password to something secure</li>
        <li>Delete this file from your hosting server</li>
        <li>Never leave password reset scripts on production servers</li>
    </ol>
</div>

<?php if ($is_hosting): ?>
<div class="info">
    <h3>📍 You are currently on your hosting server</h3>
    <p>After fixing the password, access your login page at:<br>
    <a href="login.php" target="_blank">🔗 Login to Attendance System</a></p>
</div>
<?php endif; ?>

</div>
</body></html>