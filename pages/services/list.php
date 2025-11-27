<?php
// pages/services/list.php
session_start();
require __DIR__ . '/../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$success = '';
$error = '';

// Handle service template CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_service'])) {
        $service_id = $_POST['service_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($service_id && $name) {
            try {
                // Check if name is unique (excluding current service)
                $check_sql = "SELECT COUNT(*) as count FROM services WHERE name = ? AND id != ?";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([$name, $service_id]);
                $check_result = $check_stmt->fetch();
                
                if ($check_result['count'] > 0) {
                    $error = 'Service name already exists. Please choose a different name.';
                } else {
                    $update_sql = "UPDATE services SET name = ?, description = ? WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([$name, $description, $service_id]);
                    $success = 'Service template updated successfully!';
                }
            } catch (PDOException $e) {
                $error = 'Error updating service: ' . $e->getMessage();
            }
        } else {
            $error = 'Service name is required.';
        }
    }
    
    if (isset($_POST['delete_service'])) {
        $service_id = $_POST['service_id'] ?? '';
        
        if ($service_id) {
            try {
                // Check if service has any sessions
                $check_sql = "SELECT COUNT(*) as session_count FROM service_sessions WHERE service_id = ?";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([$service_id]);
                $check_result = $check_stmt->fetch();
                
                if ($check_result['session_count'] > 0) {
                    $error = 'Cannot delete service with existing session history. Deactivate it instead.';
                } else {
                    $delete_sql = "DELETE FROM services WHERE id = ?";
                    $delete_stmt = $pdo->prepare($delete_sql);
                    $delete_stmt->execute([$service_id]);
                    $success = 'Service template deleted successfully!';
                }
            } catch (PDOException $e) {
                $error = 'Error deleting service: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $service_id = $_POST['service_id'] ?? '';
        $new_status = $_POST['new_status'] ?? '';
        
        if ($service_id && in_array($new_status, ['active', 'inactive'])) {
            try {
                $update_sql = "UPDATE services SET template_status = ? WHERE id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$new_status, $service_id]);
                $success = 'Service status updated successfully!';
            } catch (PDOException $e) {
                $error = 'Error updating service status: ' . $e->getMessage();
            }
        }
    }
}

// Get all service templates with session statistics
$services_sql = "SELECT s.*, 
                 COUNT(DISTINCT ss.id) as total_sessions,
                 COUNT(DISTINCT CASE WHEN ss.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ss.id END) as recent_sessions,
                 MAX(ss.session_date) as last_session_date,
                 COALESCE(s.created_at, NOW()) as created_at,
                 COALESCE(AVG(attendance_stats.attendance_rate), 0) as avg_attendance_rate
                 FROM services s
                 LEFT JOIN service_sessions ss ON s.id = ss.service_id
                 LEFT JOIN (
                     SELECT session_id, 
                            (COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / 
                             NULLIF(COUNT(*), 0)) as attendance_rate
                     FROM attendance 
                     GROUP BY session_id
                 ) attendance_stats ON ss.id = attendance_stats.session_id
                 GROUP BY s.id
                 ORDER BY s.name";
$services_stmt = $pdo->query($services_sql);
$services = $services_stmt->fetchAll();

// Get basic statistics for service template management
$stats_sql = "SELECT 
              COUNT(DISTINCT s.id) as total_services,
              COUNT(DISTINCT CASE WHEN s.template_status = 'active' THEN s.id END) as active_services,
              COUNT(DISTINCT CASE WHEN s.template_status = 'inactive' THEN s.id END) as inactive_services,
              COUNT(DISTINCT ss.id) as total_sessions_ever
              FROM services s
              LEFT JOIN service_sessions ss ON s.id = ss.service_id";
$stats_stmt = $pdo->query($stats_sql);
$stats = $stats_stmt->fetch();

$page_title = "Services Management - Bridge Ministries International";
include '../../includes/header.php';
?>

<!-- Using Bootstrap classes only -->

<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="bi bi-gear-fill"></i> Service Templates</h1>
                <p>Manage and organize your church service templates efficiently</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="sessions.php" class="btn btn-light me-2">
                    <i class="bi bi-calendar-day"></i> Sessions
                </a>
                <a href="add.php" class="btn btn-outline-light">
                    <i class="bi bi-plus-lg"></i> New Template
                </a>
            </div>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <div class="container">
        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card total-services">
                    <h3 class="stats-number"><?php echo $stats['total_services']; ?></h3>
                    <p class="stats-label">Total Templates</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card active-services">
                    <h3 class="stats-number"><?php echo $stats['active_services']; ?></h3>
                    <p class="stats-label">Active</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card inactive-services">
                    <h3 class="stats-number"><?php echo $stats['inactive_services']; ?></h3>
                    <p class="stats-label">Inactive</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card total-sessions">
                    <h3 class="stats-number"><?php echo $stats['total_sessions_ever']; ?></h3>
                    <p class="stats-label">Sessions</p>
                </div>
            </div>
        </div>

        <!-- Service Templates Section -->
        <div class="services-section">
            <h2 class="section-title">
                <i class="bi bi-collection"></i> Service Templates
            </h2>
            <p class="service-count"><?php echo count($services); ?> templates available</p>

            <?php if (!empty($services)): ?>
                <div class="row">
                    <?php foreach ($services as $index => $service): ?>
                        <?php
                        $status_color = $service['template_status'] === 'active' ? 'success' : 'warning';
                        $status_text = $service['template_status'] === 'active' ? 'Active' : 'Inactive';
                        $status_icon = $service['template_status'] === 'active' ? 'check-circle-fill' : 'pause-circle-fill';
                        
                        // Define unique color themes for each service card
                        $card_themes = ['blue', 'green', 'purple', 'amber', 'red', 'teal'];
                        $theme = $card_themes[$index % count($card_themes)];
                        ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="service-card theme-<?php echo $theme; ?>">
                                
                                <!-- Service Card Header -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <span class="status-badge <?php echo $service['template_status']; ?>">
                                            <i class="bi bi-<?php echo $status_icon; ?>"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                    <div class="dropdown">
                                        <button class="dropdown-toggle-custom" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><button class="dropdown-item" onclick="toggleEditMode(<?php echo $service['id']; ?>)">
                                                <i class="bi bi-pencil text-primary"></i> Quick Edit</button></li>
                                            <li><a class="dropdown-item" href="add.php?id=<?php echo $service['id']; ?>">
                                                <i class="bi bi-gear text-info"></i> Advanced Edit</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post" onsubmit="return confirmToggle('<?php echo $service['template_status']; ?>')">
                                                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $service['template_status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" name="toggle_status" class="dropdown-item">
                                                        <i class="bi bi-<?php echo $service['template_status'] === 'active' ? 'pause' : 'play'; ?>-circle text-warning"></i> 
                                                        <?php echo $service['template_status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post">
                                                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                    <button type="submit" name="delete_service" class="dropdown-item text-danger"
                                                            onclick="return confirm('Delete this template? This action cannot be undone.')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Service Info (View Mode) -->
                                <div id="view-mode-<?php echo $service['id']; ?>">
                                    <h5 class="service-title">
                                        <?php echo htmlspecialchars($service['name']); ?>
                                    </h5>
                                    <p class="service-description">
                                        <?php echo htmlspecialchars($service['description'] ?: 'No description provided'); ?>
                                    </p>
                                </div>
                                
                                <!-- Service Info (Edit Mode) -->
                                <div id="edit-mode-<?php echo $service['id']; ?>" style="display: none;">
                                    <form method="post" class="edit-form">
                                        <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Service Name</label>
                                            <input type="text" name="name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($service['name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($service['description']); ?></textarea>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="edit_service" class="btn btn-success btn-sm">
                                                <i class="bi bi-check"></i> Save
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm"
                                                    onclick="toggleEditMode(<?php echo $service['id']; ?>)">
                                                <i class="bi bi-x"></i> Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Service Statistics -->
                                <div class="service-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $service['total_sessions']; ?></div>
                                        <div class="stat-label">Sessions</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $service['recent_sessions']; ?></div>
                                        <div class="stat-label">Recent</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo round($service['avg_attendance_rate'] ?? 0); ?>%</div>
                                        <div class="stat-label">Attendance</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $service['last_session_date'] ? date('M j', strtotime($service['last_session_date'])) : 'Never'; ?></div>
                                        <div class="stat-label">Last</div>
                                    </div>
                                </div>
                                
                                <!-- Meta Information -->
                                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0;">
                                    <small style="color: #64748b; font-weight: 500; line-height: 1.5;">
                                        <i class="bi bi-calendar-plus" style="color: #3b82f6; margin-right: 0.5rem;"></i>
                                        Created <?php echo date('M j, Y', strtotime($service['created_at'])); ?>
                                        <?php if ($service['last_session_date']): ?>
                                            <br><i class="bi bi-clock-history" style="color: #3b82f6; margin-right: 0.5rem;"></i>
                                            Last used <?php echo date('M j, Y', strtotime($service['last_session_date'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 4rem; color: #64748b;">
                    <div style="background: #f8fafc; width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; border: 3px dashed #cbd5e1;">
                        <i class="bi bi-collection" style="font-size: 3rem; color: #94a3b8;"></i>
                    </div>
                    <h4 style="color: #1e293b; margin-bottom: 1.5rem; font-weight: 600; font-size: 1.5rem;">No Service Templates Yet</h4>
                    <p style="margin-bottom: 2.5rem; max-width: 450px; margin-left: auto; margin-right: auto; font-size: 1.1rem; line-height: 1.6;">Create your first service template to start managing church sessions efficiently and organize your worship services.</p>
                    <a href="add.php" class="btn btn-lg" style="background: #3b82f6; color: white; border: none; border-radius: 12px; font-weight: 600; padding: 1rem 2rem; box-shadow: 0 4px 12px rgba(59,130,246,0.25); transition: all 0.3s ease;">
                        <i class="bi bi-plus-lg"></i> Create First Template
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Classic professional interactions
document.addEventListener('DOMContentLoaded', function() {
    // Professional service card effects
    const serviceCards = document.querySelectorAll('.service-card');
    serviceCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.12)';
            this.style.borderColor = '#3b82f6';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 16px rgba(0,0,0,0.06)';
            this.style.borderColor = '#e2e8f0';
        });
    });
    
    // Statistics card effects
    const statCards = document.querySelectorAll('[style*="transition: all 0.3s ease"]');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Professional button effects
    const buttons = document.querySelectorAll('a[style*="background: #2563eb"], button[style*="background: #2563eb"]');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-1px)';
            this.style.boxShadow = '0 4px 12px rgba(37,99,235,0.25)';
            this.style.background = '#1d4ed8';
        });
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
            this.style.background = '#2563eb';
        });
    });
    
    // Professional input focus effects
    const inputs = document.querySelectorAll('input[type="text"], textarea');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.style.borderColor = '#2563eb';
            this.style.boxShadow = '0 0 0 3px rgba(37,99,235,0.1)';
        });
        input.addEventListener('blur', function() {
            this.style.borderColor = '#d1d5db';
            this.style.boxShadow = 'none';
        });
    });
});

// Enhanced editing functions
function toggleEditMode(serviceId) {
    const viewMode = document.getElementById('view-mode-' + serviceId);
    const editMode = document.getElementById('edit-mode-' + serviceId);
    
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
        const firstInput = editMode.querySelector('input[name="name"]');
        if (firstInput) {
            setTimeout(() => {
                firstInput.focus();
                firstInput.select();
            }, 100);
        }
    }
}

<?php include '../../includes/footer.php'; ?>

<script>
function confirmToggle(currentStatus) {
    const action = currentStatus === 'active' ? 'deactivate' : 'activate';
    return confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} this service template?\n\nThis will ${action} the template for future sessions.`);
}
</script>

</body>
</html>