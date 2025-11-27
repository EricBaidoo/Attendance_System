<?php
// login.php
session_start();
require 'config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && $user['password'] === $password) { // For demo, plain text. Use password_hash in production.
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Staff Login - Bridge Ministries International</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <!-- Header Section -->
            <div class="login-header">
                <div class="brand-logo">
                    <img src="assets/css/image/bmi logo.png" alt="BMI Logo">
                </div>
                <div class="brand-info">
                    <h1 class="brand-title">Bridge Ministries International</h1>
                    <p class="brand-subtitle">Attendance Management System</p>
                </div>
            </div>
            
            <!-- Login Card -->
            <div class="login-card">
                <div class="login-form-header">
                    <h2 class="form-title">Welcome Back</h2>
                 
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="login-form" novalidate>
                    <div class="input-group">
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   required 
                                   autocomplete="username">
                            <label for="username">Username</label>
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   autocomplete="current-password">
                            <label for="password">Password</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In to Dashboard
                    </button>
                </form>
            </div>
        </div>
    </div>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus username field
            document.getElementById('username').focus();
            
            // Add loading state to login button
            const loginForm = document.querySelector('.login-form');
            const loginBtn = document.querySelector('.btn-login');
            const originalBtnText = loginBtn.innerHTML;
            
            loginForm.addEventListener('submit', function() {
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing in...';
                loginBtn.disabled = true;
            });
            
            // Enhanced input interactions
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.closest('.input-wrapper').classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.closest('.input-wrapper').classList.remove('focused');
                    }
                });
                
                // Check if input has value on load
                if (input.value) {
                    input.closest('.input-wrapper').classList.add('focused');
                }
            });
        });
    </script>
</body>
</html>
