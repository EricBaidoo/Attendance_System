<?php
// pages/services/add.php
session_start();
require __DIR__ . '/../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    
    // Validation
    if (empty($name)) {
        $error = 'Service name is required.';
    } elseif (empty($type)) {
        $error = 'Service type is required.';
    } else {
        try {
            // Create service template
            $sql = "INSERT INTO services (name, description, status, template_status, created_at) VALUES (?, ?, 'scheduled', 'active', NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $type]); // Use type as description for now
            $success = 'Service template created successfully! You can now start sessions for this service.';
            
            // Clear form data
            $name = $type = '';
        } catch (PDOException $e) {
            $error = 'Error adding service: ' . $e->getMessage();
        }
    }
}

$page_title = "Add New Service - Bridge Ministries International";
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/services-add.css">


<div class="add-service-container">
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-calendar-plus"></i> Add New Service Template</h1>
                    <p>Create a reusable service template for attendance tracking</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="list.php" class="btn btn-outline-light">
                        <i class="bi bi-list"></i> View All Services
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <div class="main-card">
                    <div class="card-header">
                        <h2><i class="bi bi-form"></i> Service Template Details</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <!-- Service Template Details -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bi bi-info-circle"></i> Template Information
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="name" class="form-label">Service Template Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($name ?? ''); ?>" 
                                               placeholder="e.g., Morning Worship, Evening Service, Bible Study" required>
                                        <div class="invalid-feedback">Please provide a service name.</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="type" class="form-label">Service Type *</label>
                                        <select class="form-select" id="type" name="type" required>
                                            <option value="">Choose type...</option>
                                            <option value="Worship" <?php echo (isset($type) && $type === 'Worship') ? 'selected' : ''; ?>>🎵 Worship</option>
                                            <option value="Teaching" <?php echo (isset($type) && $type === 'Teaching') ? 'selected' : ''; ?>>📖 Teaching</option>
                                            <option value="Fellowship" <?php echo (isset($type) && $type === 'Fellowship') ? 'selected' : ''; ?>>🤝 Fellowship</option>
                                            <option value="Prayer" <?php echo (isset($type) && $type === 'Prayer') ? 'selected' : ''; ?>>🙏 Prayer</option>
                                            <option value="Special Event" <?php echo (isset($type) && $type === 'Special Event') ? 'selected' : ''; ?>>🎉 Special Event</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a service type.</div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-lightbulb"></i> <strong>Service Templates</strong> are reusable patterns. Once created, you can start attendance sessions for any date using this template.
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-plus-square"></i> Create Service Template
                                </button>
                                <a href="list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Back to Services
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Enhanced form validation with better UX
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Focus on first invalid field
                    const firstInvalid = form.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else {
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating Service...';
                    submitBtn.disabled = true;
                    
                    // Re-enable after 3 seconds (in case form doesn't submit)
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 3000);
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Auto-populate service name based on type
function updateServiceSuggestion() {
    const type = document.getElementById('type').value;
    const nameField = document.getElementById('name');
    
    if (type && !nameField.value) {
        const today = new Date();
        const dayName = today.toLocaleDateString('en-US', { weekday: 'long' });
        const suggestions = {
            'Worship': `${dayName} Worship Service`,
            'Teaching': `${dayName} Bible Study`,
            'Fellowship': `${dayName} Fellowship`,
            'Prayer': `${dayName} Prayer Meeting`,
            'Special Event': `Special Event`
        };
        
        if (suggestions[type]) {
            nameField.placeholder = `Suggestion: ${suggestions[type]}`;
        }
    }
}

<?php include '../../includes/footer.php'; ?>

<script>
// Add event listener for auto-suggestion
document.getElementById('type').addEventListener('change', updateServiceSuggestion);
</script>
</body>
</html>