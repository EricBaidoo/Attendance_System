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

// Get member ID
$member_id = $_GET['id'] ?? null;
if (!$member_id) {
    header('Location: list.php');
    exit;
}

// Database connection
try {
    require '../../config/database.php';
    
    // Get member details
    $stmt = $pdo->prepare("SELECT m.*, d.name AS department_name 
                          FROM members m 
                          LEFT JOIN departments d ON m.department_id = d.id 
                          WHERE m.id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        header('Location: list.php?error=Member not found');
        exit;
    }
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Page configuration
$page_title = "View Member - {$member['name']}";
$page_header = true;
$page_icon = "bi bi-person";
$page_heading = "Member Details";
$page_description = "View member information and details";
$page_actions = '<a href="edit.php?id=' . $member['id'] . '" class="btn btn-warning"><i class="bi bi-pencil"></i> Edit Member</a>
                <a href="list.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>';

include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../assets/css/members.css?v=<?php echo time(); ?>" rel="stylesheet">

<style>
.profile-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    color: white;
    border-radius: 16px 16px 0 0;
}

.info-card {
    border: none;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border-radius: 16px;
    overflow: hidden;
    transition: transform 0.2s ease;
}

.info-card:hover {
    transform: translateY(-2px);
}

.info-label {
    font-size: 0.85rem;
    color: #6b7280;
    font-weight: 600;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0;
}

.section-header {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
}

.action-buttons {
    gap: 0.75rem;
}

.action-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.member-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    font-weight: bold;
    margin-right: 1.5rem;
    border: 4px solid rgba(255,255,255,0.2);
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.875rem;
}

.badge-active {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.badge-inactive {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}
</style>

<!-- Enhanced Member Profile -->
<div class="container-fluid py-4">
    <!-- Profile Header -->
    <div class="card border-0 shadow-lg mb-4 info-card">
        <div class="profile-header p-4">
            <div class="d-flex align-items-center">
                <div class="member-avatar">
                    <?php echo strtoupper(substr($member['name'], 0, 2)); ?>
                </div>
                <div class="flex-grow-1">
                    <h2 class="mb-2 fw-bold"><?php echo htmlspecialchars($member['name']); ?></h2>
                    <p class="mb-3 opacity-90"><?php echo htmlspecialchars($member['department_name'] ?? 'No Department Assigned'); ?></p>
                    <div class="d-flex align-items-center gap-3">
                        <span class="status-badge <?php echo $member['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                            <i class="bi <?php echo $member['status'] === 'active' ? 'bi-check-circle' : 'bi-pause-circle'; ?> me-1"></i>
                            <?php echo ucfirst($member['status']); ?>
                        </span>
                        <?php if ($member['baptized'] === 'yes'): ?>
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-droplet me-1"></i>Baptized
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="card-footer bg-white border-0 p-4">
            <div class="d-flex action-buttons flex-wrap">
                <a href="edit.php?id=<?php echo $member['id']; ?>" class="btn btn-primary action-btn">
                    <i class="bi bi-pencil me-2"></i>Edit Member
                </a>
                <a href="list.php" class="btn btn-outline-secondary action-btn">
                    <i class="bi bi-arrow-left me-2"></i>Back to List
                </a>
                <a href="../../pages/attendance/view.php?member_id=<?php echo $member['id']; ?>" class="btn btn-outline-info action-btn">
                    <i class="bi bi-calendar-check me-2"></i>View Attendance
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Personal Information -->
        <div class="col-lg-6">
            <div class="card info-card">
                <div class="section-header">
                    <h5 class="mb-0 text-primary fw-bold">
                        <i class="bi bi-person-circle me-2"></i>Personal Information
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($member['name']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Gender</div>
                            <div class="info-value">
                                <i class="bi <?php echo strtolower($member['gender'] ?? '') === 'male' ? 'bi-person' : 'bi-person-dress'; ?> me-2 text-primary"></i>
                                <?php echo ucfirst($member['gender'] ?? 'Not specified'); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value">
                                <i class="bi bi-calendar3 me-2 text-primary"></i>
                                <?php echo $member['dob'] ? date('F d, Y', strtotime($member['dob'])) : 'Not provided'; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-label">Location</div>
                            <div class="info-value">
                                <i class="bi bi-geo-alt me-2 text-primary"></i>
                                <?php echo htmlspecialchars($member['location'] ?? 'Not provided'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="col-lg-6">
            <div class="card info-card">
                <div class="section-header">
                    <h5 class="mb-0 text-primary fw-bold">
                        <i class="bi bi-telephone me-2"></i>Contact Information
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="info-label">Email Address</div>
                            <div class="info-value">
                                <?php if ($member['email']): ?>
                                    <i class="bi bi-envelope me-2 text-primary"></i>
                                    <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($member['email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Primary Phone</div>
                            <div class="info-value">
                                <?php if ($member['phone']): ?>
                                    <i class="bi bi-telephone me-2 text-success"></i>
                                    <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($member['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Alternative Phone</div>
                            <div class="info-value">
                                <?php if ($member['phone2']): ?>
                                    <i class="bi bi-telephone me-2 text-info"></i>
                                    <a href="tel:<?php echo htmlspecialchars($member['phone2']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($member['phone2']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Church Information -->
        <div class="col-lg-6 mt-4">
            <div class="card info-card">
                <div class="section-header">
                    <h5 class="mb-0 text-primary fw-bold">
                        <i class="bi bi-building me-2"></i>Church Information
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-label">Department</div>
                            <div class="info-value">
                                <i class="bi bi-diagram-3 me-2 text-primary"></i>
                                <?php echo htmlspecialchars($member['department_name'] ?? 'Not assigned'); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Congregation Group</div>
                            <div class="info-value">
                                <i class="bi bi-people me-2 text-primary"></i>
                                <?php echo htmlspecialchars($member['congregation_group'] ?? 'Adult'); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Baptism Status</div>
                            <div class="info-value">
                                <?php if ($member['baptized'] === 'yes'): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-droplet me-1"></i>Baptized
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-circle me-1"></i>Not Baptized
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Date Joined</div>
                            <div class="info-value">
                                <i class="bi bi-calendar-plus me-2 text-primary"></i>
                                <?php echo $member['date_joined'] ? date('F d, Y', strtotime($member['date_joined'])) : 'Not recorded'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="col-lg-6 mt-4">
            <div class="card info-card">
                <div class="section-header">
                    <h5 class="mb-0 text-primary fw-bold">
                        <i class="bi bi-info-circle me-2"></i>Additional Information
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="info-label">Member ID</div>
                            <div class="info-value">
                                <span class="badge bg-light text-dark">#<?php echo str_pad($member['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-label">Last Updated</div>
                            <div class="info-value">
                                <i class="bi bi-clock me-2 text-primary"></i>
                                <?php echo isset($member['updated_at']) && $member['updated_at'] ? date('F d, Y \a\t g:i A', strtotime($member['updated_at'])) : 'Not available'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>