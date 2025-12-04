<?php
// pages/services/list.php - Manage service templates
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
                // First check if there are any sessions for this service
                $check_sessions_sql = "SELECT COUNT(*) as session_count FROM service_sessions WHERE service_id = ?";
                $check_sessions_stmt = $pdo->prepare($check_sessions_sql);
                $check_sessions_stmt->execute([$service_id]);
                $session_count = $check_sessions_stmt->fetch()['session_count'];
                
                if ($session_count > 0) {
                    // Don't delete, just deactivate
                    $deactivate_sql = "UPDATE services SET template_status = 'inactive' WHERE id = ?";
                    $deactivate_stmt = $pdo->prepare($deactivate_sql);
                    $deactivate_stmt->execute([$service_id]);
                    $success = 'Service template has been deactivated (has existing sessions).';
                } else {
                    // Safe to delete
                    $delete_sql = "DELETE FROM services WHERE id = ?";
                    $delete_stmt = $pdo->prepare($delete_sql);
                    $delete_stmt->execute([$service_id]);
                    $success = 'Service template deleted successfully!';
                }
            } catch (PDOException $e) {
                $error = 'Error deleting service: ' . $e->getMessage();
            }
        } else {
            $error = 'Service ID is required.';
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $service_id = $_POST['service_id'] ?? '';
        $new_status = $_POST['new_status'] ?? '';
        
        if ($service_id && in_array($new_status, ['active', 'inactive'])) {
            try {
                $update_status_sql = "UPDATE services SET template_status = ? WHERE id = ?";
                $update_status_stmt = $pdo->prepare($update_status_sql);
                $update_status_stmt->execute([$new_status, $service_id]);
                $success = 'Service status updated successfully!';
            } catch (PDOException $e) {
                $error = 'Error updating service status: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid service status.';
        }
    }
}

// Get service templates with statistics
$services_sql = "SELECT s.*,
                  COUNT(DISTINCT ss.id) as total_sessions,
                  COUNT(DISTINCT CASE WHEN ss.session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN ss.id END) as recent_sessions,
                  AVG(attendance_rates.attendance_rate) as avg_attendance_rate,
                  MAX(ss.session_date) as last_session_date
                  FROM services s
                  LEFT JOIN service_sessions ss ON s.id = ss.service_id
                  LEFT JOIN (
                      SELECT session_id, 
                             (COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / COUNT(*)) as attendance_rate
                      FROM attendance 
                      GROUP BY session_id
                  ) attendance_rates ON ss.id = attendance_rates.session_id
                  WHERE s.template_status IN ('active', 'inactive')
                  GROUP BY s.id
                  ORDER BY s.name";
$services_stmt = $pdo->query($services_sql);
$services = $services_stmt->fetchAll();

// Get overall statistics
$stats_sql = "SELECT 
              COUNT(CASE WHEN template_status = 'active' THEN 1 END) as active_services,
              COUNT(CASE WHEN template_status = 'inactive' THEN 1 END) as inactive_services,
              COUNT(*) as total_services,
              (SELECT COUNT(*) FROM service_sessions WHERE session_date = CURDATE()) as sessions_today
              FROM services 
              WHERE template_status IN ('active', 'inactive')";
$stats_stmt = $pdo->query($stats_sql);
$stats = $stats_stmt->fetch();

$page_title = "Service Templates - Bridge Ministries International";
include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">

<!-- Professional Service Templates Dashboard -->
<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="text-primary mb-2 fw-bold">
                        <i class="bi bi-gear-wide-connected"></i> Service Templates
                    </h1>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="text-muted">Manage your church service templates and configurations</span>
                        <span class="badge bg-light text-dark"><?php echo $stats['total_services']; ?> Templates</span>
                    </div>
                </div>
                <div class="col-lg-4 text-end">
                    <a href="sessions.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-calendar-day"></i> Today's Sessions
                    </a>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Service
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

    <!-- Statistics Cards -->
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 members-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Active Services</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['active_services']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-check-circle"></i> Ready to use
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-gear-wide-connected text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 visitors-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Inactive</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['inactive_services']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-pause-circle"></i> Disabled
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-pause-fill text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 converts-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Total Services</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['total_services']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-collection"></i> Templates
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-stack text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 departments-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Today's Sessions</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['sessions_today']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-calendar-day"></i> Active now
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-broadcast text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Templates List -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h3 class="text-primary mb-4 fw-bold">
                <i class="bi bi-list-ul"></i> Service Templates
            </h3>

            <?php if (empty($services)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-plus-circle text-muted empty-state-icon"></i>
                    <h4 class="text-muted mt-3 mb-2">No Service Templates</h4>
                    <p class="text-muted mb-4">Create your first service template to get started with session management.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus"></i> Add First Service
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($services as $service): ?>
                        <?php
                        $status_color = $service['template_status'] === 'active' ? 'success' : 'secondary';
                        $status_icon = $service['template_status'] === 'active' ? 'check-circle-fill' : 'pause-circle-fill';
                        $last_session = $service['last_session_date'] ? date('M j, Y', strtotime($service['last_session_date'])) : 'Never';
                        ?>
                        <div class="col-lg-6 col-md-12">
                            <div class="card border-0 shadow-sm h-100 service-template-card">
                                <div class="card-body p-4">
                                    <!-- Service Header -->
                                    <div class="d-flex align-items-start justify-content-between mb-3">
                                        <div class="service-icon me-3">
                                            <i class="bi bi-<?php echo $status_icon; ?> text-<?php echo $status_color; ?> fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <!-- Service Info (View Mode) -->
                                            <div id="view-mode-<?php echo $service['id']; ?>">
                                                <h5 class="text-primary fw-bold mb-2"><?php echo htmlspecialchars($service['name']); ?></h5>
                                                <p class="text-muted mb-3"><?php echo htmlspecialchars($service['description'] ?: 'No description provided'); ?></p>
                                                <div class="d-flex align-items-center gap-2 mb-3">
                                                    <span class="badge bg-<?php echo $status_color; ?>">
                                                        <?php echo ucfirst($service['template_status']); ?>
                                                    </span>
                                                    <small class="text-muted">Last used: <?php echo $last_session; ?></small>
                                                </div>
                                            </div>
                                            
                                            <!-- Service Info (Edit Mode) -->
                                            <div id="edit-mode-<?php echo $service['id']; ?>" class="d-none">
                                                <form method="post" class="edit-form">
                                                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Service Name</label>
                                                        <input type="text" name="name" class="form-control" 
                                                               value="<?php echo htmlspecialchars($service['name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Description</label>
                                                        <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($service['description']); ?></textarea>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button type="submit" name="edit_service" class="btn btn-success btn-sm">
                                                            <i class="bi bi-check"></i> Save Changes
                                                        </button>
                                                        <button type="button" class="btn btn-secondary btn-sm"
                                                                onclick="toggleEditMode(<?php echo $service['id']; ?>)">
                                                            <i class="bi bi-x"></i> Cancel
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <!-- Action Dropdown -->
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <button class="dropdown-item" onclick="toggleEditMode(<?php echo $service['id']; ?>)">
                                                        <i class="bi bi-pencil me-2"></i> Edit
                                                    </button>
                                                </li>
                                                <li>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo $service['template_status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                        <button type="submit" name="toggle_status" class="dropdown-item">
                                                            <i class="bi bi-<?php echo $service['template_status'] === 'active' ? 'pause' : 'play'; ?> me-2"></i>
                                                            <?php echo $service['template_status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this service template?')">
                                                        <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                        <button type="submit" name="delete_service" class="dropdown-item text-danger">
                                                            <i class="bi bi-trash me-2"></i> Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Service Statistics -->
                                    <div class="row g-3 mb-3">
                                        <div class="col-4">
                                            <div class="text-center p-3 bg-light rounded-3">
                                                <div class="fw-bold text-primary fs-5"><?php echo $service['total_sessions']; ?></div>
                                                <small class="text-muted">Total Sessions</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-center p-3 bg-light rounded-3">
                                                <div class="fw-bold text-success fs-5"><?php echo $service['recent_sessions']; ?></div>
                                                <small class="text-muted">Recent (30d)</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-center p-3 bg-light rounded-3">
                                                <div class="fw-bold text-info fs-5"><?php echo round($service['avg_attendance_rate'] ?? 0); ?>%</div>
                                                <small class="text-muted">Avg Attendance</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <?php if ($service['template_status'] === 'active'): ?>
                                        <div class="d-flex gap-2">
                                            <a href="sessions.php" class="btn btn-primary btn-sm flex-fill">
                                                <i class="bi bi-play-fill"></i> Start Session
                                            </a>
                                            <a href="history.php?service_id=<?php echo $service['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm flex-fill">
                                                <i class="bi bi-clock-history"></i> View History
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning border-0 mb-0 py-2">
                                            <i class="bi bi-pause-circle"></i> <small>Service is currently inactive</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleEditMode(serviceId) {
    const viewMode = document.getElementById('view-mode-' + serviceId);
    const editMode = document.getElementById('edit-mode-' + serviceId);
    
    if (viewMode.classList.contains('d-none')) {
        // Currently in edit mode, switch to view mode
        viewMode.classList.remove('d-none');
        editMode.classList.add('d-none');
    } else {
        // Currently in view mode, switch to edit mode
        viewMode.classList.add('d-none');
        editMode.classList.remove('d-none');
    }
}

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
</script>

<?php include '../../includes/footer.php'; ?>