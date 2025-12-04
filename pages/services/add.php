<?php
// pages/services/add.php - Add new service template
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
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? '';
    
    // Validation
    if (empty($name)) {
        $error = 'Service name is required.';
    } elseif (empty($type)) {
        $error = 'Service type is required.';
    } else {
        try {
            // Check if service name already exists
            $check_sql = "SELECT COUNT(*) as count FROM services WHERE name = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$name]);
            $check_result = $check_stmt->fetch();
            
            if ($check_result['count'] > 0) {
                $error = 'A service with this name already exists. Please choose a different name.';
            } else {
                // Create service template
                $sql = "INSERT INTO services (name, description, status, template_status, created_at) VALUES (?, ?, 'scheduled', 'active', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $description ?: $type]);
                $success = 'Service template created successfully! You can now start sessions for this service.';
                
                // Clear form data
                $name = $description = $type = '';
            }
        } catch (PDOException $e) {
            $error = 'Error adding service: ' . $e->getMessage();
        }
    }
}

// Get existing services count for statistics
$stats_sql = "SELECT COUNT(*) as total_services FROM services WHERE template_status = 'active'";
$stats_stmt = $pdo->query($stats_sql);
$total_services = $stats_stmt->fetch()['total_services'];

$page_title = "Add New Service - Bridge Ministries International";
include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">

<!-- Professional Add Service Form -->
<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="text-primary mb-2 fw-bold">
                        <i class="bi bi-plus-circle"></i> Add New Service Template
                    </h1>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="text-muted">Create a new service template for your church</span>
                        <span class="badge bg-light text-dark"><?php echo $total_services; ?> Existing Templates</span>
                    </div>
                </div>
                <div class="col-lg-4 text-end">
                    <a href="list.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-arrow-left"></i> Back to Services
                    </a>
                    <a href="sessions.php" class="btn btn-primary">
                        <i class="bi bi-calendar-day"></i> Sessions
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <!-- Service Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="template-icon mx-auto mb-3">
                            <i class="bi bi-gear-wide-connected"></i>
                        </div>
                        <h3 class="text-primary fw-bold mb-2">Create Service Template</h3>
                        <p class="text-muted">Set up a new service type for your church ministry</p>
                    </div>

                    <form method="post" class="service-form">
                        <!-- Service Name -->
                        <div class="mb-4">
                            <label for="name" class="form-label fw-semibold text-dark">
                                <i class="bi bi-tag me-2 text-primary"></i>Service Name *
                            </label>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="name" 
                                   name="name" 
                                   value="<?php echo htmlspecialchars($name ?? ''); ?>" 
                                   placeholder="Enter service name (e.g., Sunday Morning Service)"
                                   required>
                            <div class="form-text">This will be the display name for your service template</div>
                        </div>

                        <!-- Service Type -->
                        <div class="mb-4">
                            <label for="type" class="form-label fw-semibold text-dark">
                                <i class="bi bi-collection me-2 text-primary"></i>Service Type *
                            </label>
                            <select class="form-select form-select-lg" id="type" name="type" required>
                                <option value="">Choose service type...</option>
                                <option value="Sunday Morning Service" <?php echo (($type ?? '') === 'Sunday Morning Service') ? 'selected' : ''; ?>>Sunday Morning Service</option>
                                <option value="Sunday Evening Service" <?php echo (($type ?? '') === 'Sunday Evening Service') ? 'selected' : ''; ?>>Sunday Evening Service</option>
                                <option value="Wednesday Bible Study" <?php echo (($type ?? '') === 'Wednesday Bible Study') ? 'selected' : ''; ?>>Wednesday Bible Study</option>
                                <option value="Prayer Meeting" <?php echo (($type ?? '') === 'Prayer Meeting') ? 'selected' : ''; ?>>Prayer Meeting</option>
                                <option value="Youth Service" <?php echo (($type ?? '') === 'Youth Service') ? 'selected' : ''; ?>>Youth Service</option>
                                <option value="Children's Service" <?php echo (($type ?? '') === 'Children\'s Service') ? 'selected' : ''; ?>>Children's Service</option>
                                <option value="Special Event" <?php echo (($type ?? '') === 'Special Event') ? 'selected' : ''; ?>>Special Event</option>
                                <option value="Conference" <?php echo (($type ?? '') === 'Conference') ? 'selected' : ''; ?>>Conference</option>
                                <option value="Revival Meeting" <?php echo (($type ?? '') === 'Revival Meeting') ? 'selected' : ''; ?>>Revival Meeting</option>
                                <option value="Communion Service" <?php echo (($type ?? '') === 'Communion Service') ? 'selected' : ''; ?>>Communion Service</option>
                                <option value="Baptism Service" <?php echo (($type ?? '') === 'Baptism Service') ? 'selected' : ''; ?>>Baptism Service</option>
                                <option value="Other" <?php echo (($type ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <div class="form-text">Select the type of service this template represents</div>
                        </div>

                        <!-- Service Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold text-dark">
                                <i class="bi bi-textarea-t me-2 text-primary"></i>Description
                            </label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="4"
                                      placeholder="Enter a detailed description of this service (optional)"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                            <div class="form-text">Provide additional details about this service template</div>
                        </div>

                        <!-- Form Actions -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="list.php" class="btn btn-outline-secondary btn-lg w-100">
                                    <i class="bi bi-x-circle me-2"></i>Cancel
                                </a>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="bi bi-plus-circle me-2"></i>Create Service Template
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body p-4">
                    <h5 class="text-primary fw-bold mb-3">
                        <i class="bi bi-lightning"></i> Quick Actions
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="list.php" class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center py-3">
                                <div class="text-center">
                                    <i class="bi bi-list-ul fs-4 d-block mb-2"></i>
                                    <span>View All Services</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="sessions.php" class="btn btn-outline-success w-100 d-flex align-items-center justify-content-center py-3">
                                <div class="text-center">
                                    <i class="bi bi-play-circle fs-4 d-block mb-2"></i>
                                    <span>Start Session</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});

// Form validation and enhancement
document.getElementById('type').addEventListener('change', function() {
    const nameField = document.getElementById('name');
    const descriptionField = document.getElementById('description');
    
    if (this.value && !nameField.value) {
        nameField.value = this.value;
    }
    
    // Auto-populate description based on service type
    if (this.value && !descriptionField.value) {
        const descriptions = {
            'Sunday Morning Service': 'Main Sunday worship service for the congregation',
            'Sunday Evening Service': 'Evening worship and fellowship service',
            'Wednesday Bible Study': 'Midweek Bible study and prayer meeting',
            'Prayer Meeting': 'Dedicated time for corporate prayer',
            'Youth Service': 'Service designed for young people and teenagers',
            'Children\'s Service': 'Sunday school and children\'s ministry',
            'Special Event': 'Special church events and celebrations',
            'Conference': 'Church conferences and seminars',
            'Revival Meeting': 'Revival and evangelistic meetings',
            'Communion Service': 'Holy Communion service',
            'Baptism Service': 'Water baptism service'
        };
        
        if (descriptions[this.value]) {
            descriptionField.value = descriptions[this.value];
        }
    }
});

// Add floating label effect
document.querySelectorAll('.form-control, .form-select').forEach(function(element) {
    element.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    
    element.addEventListener('blur', function() {
        if (!this.value) {
            this.parentElement.classList.remove('focused');
        }
    });
    
    // Check if field has value on load
    if (element.value) {
        element.parentElement.classList.add('focused');
    }
});
</script>

<style>
.service-form .form-control:focus,
.service-form .form-select:focus {
    border-color: #000032;
    box-shadow: 0 0 0 0.2rem rgba(0, 0, 50, 0.25);
}

.focused .form-label {
    color: #000032 !important;
}

.btn-primary {
    background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #1a1a5e 0%, #000032 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 50, 0.3);
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
}

.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}
</style>

<?php include '../../includes/footer.php'; ?>