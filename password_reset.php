<?php
/**
 * Password Reset Script
 * Use this to reset passwords to known values
 */

echo "<!DOCTYPE html><html><head><title>Password Reset</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
    .credentials { background: #f0f8ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style></head><body>";

echo "<h1>🔑 Password Reset Tool</h1>";

try {
    require 'config/database.php';
    echo "<p class='success'>✅ Database connected successfully!</p>";

    if ($_POST && isset($_POST['reset_all_passwords'])) {
        echo "<div class='section'>";
        echo "<h2>🔄 Resetting All Passwords</h2>";
        
        // Reset admin password to 'admin123'
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $admin_updated = $stmt->execute([$admin_password]);
        
        // Reset staff password to 'staff123' 
        $staff_password = password_hash('staff123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'staff'");
        $staff_updated = $stmt->execute([$staff_password]);
        
        if ($admin_updated) {
            echo "<p class='success'>✅ Admin password reset to: <strong>admin123</strong></p>";
        }
        
        if ($staff_updated) {
            echo "<p class='success'>✅ Staff password reset to: <strong>staff123</strong></p>";
        }
        
        echo "<div class='credentials'>";
        echo "<h3>🔐 Your Login Credentials:</h3>";
        echo "<p><strong>Admin Login:</strong><br>";
        echo "Username: <code>admin</code><br>";
        echo "Password: <code>admin123</code></p>";
        echo "<p><strong>Staff Login:</strong><br>";
        echo "Username: <code>staff</code><br>";  
        echo "Password: <code>staff123</code></p>";
        echo "</div>";
        
        echo "<p class='info'>💡 You can now login with these credentials!</p>";
        echo "<p><a href='login.php'>→ Go to Login Page</a></p>";
        echo "</div>";
        
    } else if ($_POST && isset($_POST['custom_reset'])) {
        $username = trim($_POST['username']);
        $new_password = $_POST['new_password'];
        
        if ($username && $new_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
            
            if ($stmt->execute([$hashed_password, $username])) {
                if ($stmt->rowCount() > 0) {
                    echo "<p class='success'>✅ Password updated for user '$username'!</p>";
                    echo "<div class='credentials'>";
                    echo "<p><strong>Login Credentials:</strong><br>";
                    echo "Username: <code>$username</code><br>";
                    echo "Password: <code>$new_password</code></p>";
                    echo "</div>";
                } else {
                    echo "<p class='error'>❌ User '$username' not found!</p>";
                }
            } else {
                echo "<p class='error'>❌ Failed to update password!</p>";
            }
        }
    }

    // Show current users
    echo "<div class='section'>";
    echo "<h2>👥 Current Users</h2>";
    $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    if (!empty($users)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Role</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<div class="section">
<h2>🔧 Reset Options</h2>

<form method="POST" style="margin: 15px 0;">
    <h3>Quick Reset (Recommended)</h3>
    <p>This will reset passwords to default values:</p>
    <ul>
        <li><strong>admin</strong> → password: <code>admin123</code></li>
        <li><strong>staff</strong> → password: <code>staff123</code></li>
    </ul>
    <button type="submit" name="reset_all_passwords" style="background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
        🔄 Reset All Passwords
    </button>
</form>

<form method="POST" style="margin: 15px 0;">
    <h3>Custom Password Reset</h3>
    <p>
        <label>Username: <input type="text" name="username" required style="margin: 5px;"></label>
    </p>
    <p>
        <label>New Password: <input type="password" name="new_password" required style="margin: 5px;"></label>
    </p>
    <button type="submit" name="custom_reset" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
        🔑 Reset Password
    </button>
</form>
</div>

<div class="section">
<p class="info">💡 <strong>Next Steps:</strong></p>
<ol>
    <li>Use the "Quick Reset" button to set known passwords</li>
    <li>Go to the <a href="login.php">Login Page</a></li>
    <li>Login with the credentials shown above</li>
    <li>Once logged in, you can change passwords from the admin panel</li>
</ol>
</div>

</body></html>