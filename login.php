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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">

</head>
<body>
    <div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center py-5">
        <div class="row g-0 shadow-lg rounded-4 overflow-hidden" style="max-width: 900px; width: 100%;">
            <!-- Left Panel - Branding -->
            <div class="col-lg-6 d-flex align-items-center justify-content-center bg-primary-section">
                <div class="text-center text-white p-5 m-4">
                    <img src="assets/css/image/bmi logo.png" alt="BMI Logo" class="brand-logo mb-5">
                    <h1 class="display-5 fw-bold mb-4">Bridge Ministries International</h1>
                    <p class="lead mb-0">Attendance Management System</p>
                </div>
            </div>
            
            <!-- Right Panel - Login Form -->
            <div class="col-lg-6 d-flex align-items-center justify-content-center bg-light-section">
                <div class="w-100 max-width-form p-5 m-4">
                    <div class="text-center mb-5">
                        <h2 class="h3 fw-bold text-primary mb-3">Welcome Back</h2>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 rounded-3 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="login-form">
                        <div class="form-floating mb-4">
                            <input type="text" 
                                   class="form-control form-control-lg rounded-3" 
                                   id="username" 
                                   name="username" 
                                   placeholder="Username"
                                   required 
                                   autocomplete="username">
                            <label for="username">
                                <i class="bi bi-person me-2"></i>Username
                            </label>
                        </div>

                        <div class="form-floating mb-5">
                            <input type="password" 
                                   class="form-control form-control-lg rounded-3" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Password"
                                   required 
                                   autocomplete="current-password">
                            <label for="password">
                                <i class="bi bi-lock me-2"></i>Password
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-3 mb-3">
                            Sign In
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus username field
            document.getElementById('username').focus();
            
            // Add form validation
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please fill in both username and password.');
                }
            });
        });
    </script>
</body>
</html>
