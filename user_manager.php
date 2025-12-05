<?php
// User Management - Check existing users or create new admin
echo "<!DOCTYPE html><html><head><title>User Management</title></head><body>";
echo "<h1>User Management System</h1>";

try {
    require 'config/database.php';
    echo "<p style='color: green;'>✅ Database connected successfully!</p>";

    // Show existing users
    echo "<h3>Current Users in Database:</h3>";
    $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<p style='color: red;'>❌ No users found in database!</p>";
        echo "<p>You need to create an admin user first.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Role</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Create new admin user form
    if ($_POST && isset($_POST['create_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        if ($username && $password) {
            // Check if user already exists
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check_stmt->execute([$username]);
            
            if ($check_stmt->fetchColumn()) {
                echo "<p style='color: red;'>❌ User '$username' already exists!</p>";
            } else {
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                
                if ($insert_stmt->execute([$username, $hashed_password, $role])) {
                    echo "<p style='color: green;'>✅ User '$username' created successfully!</p>";
                    echo "<p><strong>Login with:</strong> Username: $username, Password: [the password you entered]</p>";
                } else {
                    echo "<p style='color: red;'>❌ Failed to create user!</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ Please fill in all fields!</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<h3>Create New User:</h3>
<form method="POST">
    <p>
        <label>Username: <input type="text" name="username" required></label>
    </p>
    <p>
        <label>Password: <input type="password" name="password" required></label>
    </p>
    <p>
        <label>Role: 
            <select name="role" required>
                <option value="admin">Admin</option>
                <option value="staff">Staff</option>
                <option value="member">Member</option>
            </select>
        </label>
    </p>
    <p>
        <button type="submit" name="create_user">Create User</button>
    </p>
</form>

<h3>Reset Password (if user exists):</h3>
<?php if ($_POST && isset($_POST['reset_password'])): ?>
<?php
    $username = trim($_POST['reset_username']);
    $new_password = $_POST['new_password'];
    
    if ($username && $new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
        
        if ($update_stmt->execute([$hashed_password, $username])) {
            if ($update_stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✅ Password updated for '$username'!</p>";
            } else {
                echo "<p style='color: red;'>❌ User '$username' not found!</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Failed to update password!</p>";
        }
    }
?>
<?php endif; ?>

<form method="POST">
    <p>
        <label>Username: <input type="text" name="reset_username" required></label>
    </p>
    <p>
        <label>New Password: <input type="password" name="new_password" required></label>
    </p>
    <p>
        <button type="submit" name="reset_password">Reset Password</button>
    </p>
</form>

<p><a href="login.php">Go to Login Page</a></p>
</body></html>