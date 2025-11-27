<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'member';
if (!in_array($user_role, ['admin', 'staff'])) {
    header('Location: ../../index.php');
    exit;
}

// Helper function to convert empty strings to null for database
function emptyToNull($value) {
    return (!empty($value)) ? $value : null;
}

// Get visitor ID
$visitor_id = $_GET['id'] ?? null;
if (!$visitor_id) {
    header('Location: list.php');
    exit;
}

$message = '';
$error = '';

try {
    require '../../config/database.php';
    
    // Get visitor information
    $visitor_stmt = $pdo->prepare("SELECT v.*, s.name as service_name FROM visitors v 
                                   LEFT JOIN services s ON v.service_id = s.id 
                                   WHERE v.id = ?");
    $visitor_stmt->execute([$visitor_id]);
    $visitor = $visitor_stmt->fetch();
    
    if (!$visitor) {
        header('Location: list.php?error=' . urlencode('Visitor not found'));
        exit;
    }
    
    // Get departments for dropdown
    $departments_stmt = $pdo->query("SELECT * FROM departments ORDER BY name");
    $departments = $departments_stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submission - Convert to New Convert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_to_new_convert'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department_id = emptyToNull($_POST['department_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($name)) {
        $error = "Name is required.";
    } else {
        try {
            // Check if convert already exists (by phone or email)
            $existing_check = null;
            if (!empty($phone) || !empty($email)) {
                $check_sql = "SELECT id FROM new_converts WHERE";
                $check_params = [];
                $conditions = [];
                
                if (!empty($phone)) {
                    $conditions[] = "phone = ?";
                    $check_params[] = $phone;
                }
                if (!empty($email)) {
                    $conditions[] = "email = ?";
                    $check_params[] = $email;
                }
                
                $check_sql .= " " . implode(' OR ', $conditions);
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute($check_params);
                $existing_check = $check_stmt->fetch();
            }
            
            if ($existing_check) {
                $error = "A new convert with this phone number or email already exists.";
            } else {
                $pdo->beginTransaction();
                
                // Create new convert record
                $convert_sql = "INSERT INTO new_converts (name, phone, email, department_id, notes, 
                               date_converted, created_at, visitor_id) 
                               VALUES (?, ?, ?, ?, ?, CURDATE(), NOW(), ?)";
                $convert_stmt = $pdo->prepare($convert_sql);
                $convert_result = $convert_stmt->execute([
                    $name, $phone, $email, $department_id, $notes, $visitor_id
                ]);
                
                if (!$convert_result) {
                    $pdo->rollBack();
                    $error = "Failed to create new convert record.";
                } else {
                    // Update visitor status to converted
                    $update_visitor_sql = "UPDATE visitors SET status = 'converted_to_convert', 
                                          notes = CONCAT(COALESCE(notes, ''), '\nConverted to New Convert on ', NOW()),
                                          converted_date = CURDATE()
                                          WHERE id = ?";
                    $update_result = $pdo->prepare($update_visitor_sql)->execute([$visitor_id]);
                    
                    if (!$update_result) {
                        $pdo->rollBack();
                        $error = "Failed to update visitor status.";
                    } else {
                        $pdo->commit();
                        $message = "Visitor successfully converted to New Convert!";
                        
                        // Redirect to new converts page after successful conversion
                        header('Location: new_converts.php?message=' . urlencode($message));
                        exit;
                    }
                }
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle form submission - Convert to Full Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_to_member'])) {
    $convert_id = $_POST['convert_id'] ?? null;
    $congregation_group = $_POST['congregation_group'] ?? 'adult';
    $baptized = $_POST['baptized'] ?? 'no';
    $member_notes = trim($_POST['member_notes'] ?? '');
    
    if (!$convert_id) {
        $error = "Invalid convert ID.";
    } else {
        try {
            // Get new convert information
            $convert_stmt = $pdo->prepare("SELECT * FROM new_converts WHERE id = ?");
            $convert_stmt->execute([$convert_id]);
            $convert = $convert_stmt->fetch();
            
            if (!$convert) {
                $error = "New convert not found.";
            } else {
                // Check if member already exists
                $existing_member = null;
                if (!empty($convert['phone']) || !empty($convert['email'])) {
                    $check_sql = "SELECT id FROM members WHERE";
                    $check_params = [];
                    $conditions = [];
                    
                    if (!empty($convert['phone'])) {
                        $conditions[] = "phone = ?";
                        $check_params[] = $convert['phone'];
                    }
                    if (!empty($convert['email'])) {
                        $conditions[] = "email = ?";
                        $check_params[] = $convert['email'];
                    }
                    
                    $check_sql .= " " . implode(' OR ', $conditions);
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute($check_params);
                    $existing_member = $check_stmt->fetch();
                }
                
                if ($existing_member) {
                    $error = "A member with this phone number or email already exists.";
                } else {
                    $pdo->beginTransaction();
                    
                    // Create member record
                    $member_sql = "INSERT INTO members (name, phone, email, department_id, congregation_group, 
                                  baptized, status, date_joined, notes, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, 'active', CURDATE(), ?, NOW())";
                    $member_stmt = $pdo->prepare($member_sql);
                    $member_result = $member_stmt->execute([
                        $convert['name'],
                        $convert['phone'],
                        $convert['email'],
                        emptyToNull($convert['department_id']),
                        $congregation_group,
                        $baptized,
                        $member_notes
                    ]);
                    
                    if (!$member_result) {
                        $pdo->rollBack();
                        $error = "Failed to create member record.";
                    } else {
                        // Update new convert status
                        $update_convert_sql = "UPDATE new_converts SET status = 'converted_to_member',
                                             member_conversion_date = CURDATE(),
                                             notes = CONCAT(COALESCE(notes, ''), '\nConverted to Full Member on ', NOW())
                                             WHERE id = ?";
                        $update_result = $pdo->prepare($update_convert_sql)->execute([$convert_id]);
                        
                        if (!$update_result) {
                            $pdo->rollBack();
                            $error = "Failed to update new convert status.";
                        } else {
                            $pdo->commit();
                            $message = "New Convert successfully converted to Full Member!";
                            
                            // Redirect to members list
                            header('Location: ../members/list.php?message=' . urlencode($message));
                            exit;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get existing new converts for this visitor
try {
    $converts_stmt = $pdo->prepare("SELECT nc.*, d.name as department_name 
                                   FROM new_converts nc 
                                   LEFT JOIN departments d ON nc.department_id = d.id 
                                   WHERE nc.visitor_id = ? 
                                   ORDER BY nc.date_converted DESC");
    $converts_stmt->execute([$visitor_id]);
    $existing_converts = $converts_stmt->fetchAll();
} catch (Exception $e) {
    $existing_converts = [];
}

// Page configuration
$page_title = "Convert Visitor to New Convert";
$page_header = true;
$page_icon = "bi bi-person-plus-fill";
$page_heading = "Convert Visitor to New Convert";
$page_description = "Convert visitor to new convert status or promote to full membership";

include '../../includes/header.php';
?>

<!-- Using Bootstrap classes only -->

<style>
    .conversion-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 0.9375rem;
        box-shadow: 0 0.9375rem 2.1875rem rgba(0, 0, 0, 0.1);
    }
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
    }
    .btn-convert {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        border: none;
        color: white;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-convert:hover {
        transform: translateY(-0.125rem);
        box-shadow: 0 0.625rem 1.875rem rgba(72, 187, 120, 0.3);
        color: white;
    }
    .btn-member {
        background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        border: none;
        color: white;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-member:hover {
        transform: translateY(-0.125rem);
        box-shadow: 0 0.625rem 1.875rem rgba(237, 137, 54, 0.3);
        color: white;
    }
</style>

<div class="row">
    <div class="col-lg-8">
        <!-- Visitor Information -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="conversion-card">
                <div class="card-body text-center py-4">
                    <div class="mb-3">
                        <i class="bi bi-person-circle" style="font-size: 4rem; opacity: 0.9;"></i>
                    </div>
                    <h3 class="mb-2"><?= htmlspecialchars($visitor['name']) ?></h3>
                    <p class="mb-1">
                        <i class="bi bi-calendar-event me-2"></i>
                        Visited on <?= date('F d, Y', strtotime($visitor['date'])) ?>
                    </p>
                    <?php if ($visitor['service_name']): ?>
                    <p class="mb-1">
                        <i class="bi bi-building me-2"></i>
                        Service: <?= htmlspecialchars($visitor['service_name']) ?>
                    </p>
                    <?php endif; ?>
                    <p class="mb-0">
                        <i class="bi bi-star me-2"></i>
                        <?= $visitor['first_time'] == 'yes' ? 'First Time Visitor' : 'Return Visitor' ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Convert to New Convert Form -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-success bg-opacity-10 border-bottom-0">
                <h5 class="mb-0 text-success">
                    <i class="bi bi-person-plus-fill me-2"></i>Step 1: Convert to New Convert
                </h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">
                                <i class="bi bi-person me-2"></i>Full Name *
                            </label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($visitor['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label fw-semibold">
                                <i class="bi bi-building me-2"></i>Department
                            </label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">Select Department (Optional)</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold">
                                <i class="bi bi-telephone me-2"></i>Phone Number
                            </label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($visitor['phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">
                                <i class="bi bi-envelope me-2"></i>Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($visitor['email']) ?>">
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label fw-semibold">
                                <i class="bi bi-journal-text me-2"></i>Notes
                            </label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Any additional notes about this new convert..."></textarea>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to List
                        </a>
                        <button type="submit" name="convert_to_new_convert" class="btn btn-convert">
                            <i class="bi bi-person-plus-fill me-2"></i>Convert to New Convert
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Existing New Converts -->
        <?php if (!empty($existing_converts)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning bg-opacity-10 border-bottom-0">
                <h6 class="mb-0 text-warning">
                    <i class="bi bi-people-fill me-2"></i>Existing New Converts
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($existing_converts as $convert): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1"><?= htmlspecialchars($convert['name']) ?></h6>
                            <small class="text-muted">
                                Converted on <?= date('M d, Y', strtotime($convert['date_converted'])) ?>
                            </small>
                        </div>
                        <?php if ($convert['status'] == 'active'): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-primary">Member</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($convert['status'] == 'active'): ?>
                    <!-- Convert to Member Form -->
                    <form method="POST" action="" class="mt-3">
                        <input type="hidden" name="convert_id" value="<?= $convert['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Congregation Group</label>
                            <select class="form-select form-select-sm" name="congregation_group" required>
                                <option value="adult">Adult</option>
                                <option value="youth">Youth</option>
                                <option value="children">Children</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Baptized Status</label>
                            <select class="form-select form-select-sm" name="baptized">
                                <option value="no">Not Baptized</option>
                                <option value="yes">Baptized</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Additional Notes</label>
                            <textarea class="form-control form-control-sm" name="member_notes" rows="2" 
                                      placeholder="Member notes..."></textarea>
                        </div>
                        
                        <button type="submit" name="convert_to_member" class="btn btn-member btn-sm w-100">
                            <i class="bi bi-arrow-up-circle me-2"></i>Convert to Full Member
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Conversion Process Info -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-info bg-opacity-10 border-bottom-0">
                <h6 class="mb-0 text-info">
                    <i class="bi bi-info-circle-fill me-2"></i>Conversion Process
                </h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary rounded-circle p-2 me-3">
                        <i class="bi bi-1-circle-fill text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Visitor</h6>
                        <small class="text-muted">Initial visit recorded</small>
                    </div>
                </div>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success rounded-circle p-2 me-3">
                        <i class="bi bi-2-circle-fill text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">New Convert</h6>
                        <small class="text-muted">Expressed interest in faith</small>
                    </div>
                </div>
                
                <div class="d-flex align-items-center">
                    <div class="bg-warning rounded-circle p-2 me-3">
                        <i class="bi bi-3-circle-fill text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Full Member</h6>
                        <small class="text-muted">Complete church membership</small>
                    </div>
                </div>
                
                <hr class="my-3">
                
                <div class="alert alert-light mb-0">
                    <small class="text-muted">
                        <i class="bi bi-lightbulb me-1"></i>
                        The conversion process allows for proper discipleship and integration into church community.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-fill phone and email if visitor has them
    const visitorPhone = "<?= addslashes($visitor['phone']) ?>";
    const visitorEmail = "<?= addslashes($visitor['email']) ?>";
    
    if (visitorPhone) {
        document.getElementById('phone').value = visitorPhone;
    }
    if (visitorEmail) {
        document.getElementById('email').value = visitorEmail;
    }
});
</script>