<?php
// pages/services/templates.php
session_start();
require __DIR__ . '/../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$success = '';
$error = '';

// Handle quick service creation from templates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_from_template'])) {
    $template = $_POST['template'];
    $start_date = $_POST['start_date'];
    $weeks = (int)$_POST['weeks'];
    
    if (empty($start_date) || $weeks < 1) {
        $error = 'Please select a start date and number of weeks.';
    } else {
        try {
            $templates = [
                'sunday_worship' => [
                    'name' => 'Sunday Morning Worship',
                    'description' => 'Weekly Sunday morning worship service',
                    'location' => 'Main Sanctuary',
                    'type' => 'Worship'
                ],
                'midweek_prayer' => [
                    'name' => 'Midweek Prayer Service',
                    'description' => 'Wednesday evening prayer and fellowship',
                    'location' => 'Prayer Room',
                    'type' => 'Prayer'
                ],
                'youth_fellowship' => [
                    'name' => 'Youth Fellowship',
                    'description' => 'Weekly youth ministry and fellowship time',
                    'location' => 'Youth Hall',
                    'type' => 'Fellowship'
                ],
                'bible_study' => [
                    'name' => 'Bible Study',
                    'description' => 'Weekly Bible study and discussion',
                    'location' => 'Fellowship Hall',
                    'type' => 'Teaching'
                ]
            ];
            
            if (isset($templates[$template])) {
                $tmpl = $templates[$template];
                $created_count = 0;
                $start_date_obj = new DateTime($start_date);
                
                $pdo->beginTransaction();
                
                for ($i = 0; $i < $weeks; $i++) {
                    $service_date = clone $start_date_obj;
                    $service_date->add(new DateInterval('P' . ($i * 7) . 'D'));
                    
                    $sql = "INSERT INTO services (name, description, date, location, type, status) VALUES (?, ?, ?, ?, ?, 'scheduled')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $tmpl['name'],
                        $tmpl['description'], 
                        $service_date->format('Y-m-d'),
                        $tmpl['location'],
                        $tmpl['type']
                    ]);
                    $created_count++;
                }
                
                $pdo->commit();
                $success = "Created {$created_count} {$tmpl['name']} services!";
            }
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'Error creating services: ' . $e->getMessage();
        }
    }
}

$page_title = "Service Templates - Bridge Ministries International";
include '../../includes/header.php';
?>
<!-- Additional CSS for services page -->
<link href="../../assets/css/services.css" rel="stylesheet">
<link href="../../assets/css/forms.css" rel="stylesheet">

<div class="services-container">
    <div class="container">
        <div class="services-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><i class="bi bi-collection"></i> Service Templates</h1>
                        <p>Quickly create recurring services from common templates</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="list.php" class="btn btn-outline-light">
                            <i class="bi bi-list"></i> View All Services
                        </a>
                    </div>
                </div>
            </div>
        </div>

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

        <div class="row">
            <!-- Sunday Worship -->
            <div class="col-lg-6 mb-4">
                <div class="template-card">
                    <div class="template-icon">
                        <i class="bi bi-sunrise"></i>
                    </div>
                    <h3>Sunday Morning Worship</h3>
                    <p>Weekly Sunday morning worship service in the Main Sanctuary</p>
                    
                    <form method="POST" class="template-form">
                        <input type="hidden" name="template" value="sunday_worship">
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Weeks</label>
                                <input type="number" name="weeks" class="form-control" min="1" max="52" value="12" required>
                            </div>
                        </div>
                        <button type="submit" name="create_from_template" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-circle"></i> Create Series
                        </button>
                    </form>
                </div>
            </div>

            <!-- Midweek Prayer -->
            <div class="col-lg-6 mb-4">
                <div class="template-card">
                    <div class="template-icon">
                        <i class="bi bi-moon-stars"></i>
                    </div>
                    <h3>Midweek Prayer Service</h3>
                    <p>Wednesday evening prayer and fellowship in the Prayer Room</p>
                    
                    <form method="POST" class="template-form">
                        <input type="hidden" name="template" value="midweek_prayer">
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Weeks</label>
                                <input type="number" name="weeks" class="form-control" min="1" max="52" value="8" required>
                            </div>
                        </div>
                        <button type="submit" name="create_from_template" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-circle"></i> Create Series
                        </button>
                    </form>
                </div>
            </div>

            <!-- Youth Fellowship -->
            <div class="col-lg-6 mb-4">
                <div class="template-card">
                    <div class="template-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3>Youth Fellowship</h3>
                    <p>Weekly youth ministry and fellowship time in the Youth Hall</p>
                    
                    <form method="POST" class="template-form">
                        <input type="hidden" name="template" value="youth_fellowship">
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Weeks</label>
                                <input type="number" name="weeks" class="form-control" min="1" max="52" value="10" required>
                            </div>
                        </div>
                        <button type="submit" name="create_from_template" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-circle"></i> Create Series
                        </button>
                    </form>
                </div>
            </div>

            <!-- Bible Study -->
            <div class="col-lg-6 mb-4">
                <div class="template-card">
                    <div class="template-icon">
                        <i class="bi bi-book"></i>
                    </div>
                    <h3>Bible Study</h3>
                    <p>Weekly Bible study and discussion in the Fellowship Hall</p>
                    
                    <form method="POST" class="template-form">
                        <input type="hidden" name="template" value="bible_study">
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Weeks</label>
                                <input type="number" name="weeks" class="form-control" min="1" max="52" value="6" required>
                            </div>
                        </div>
                        <button type="submit" name="create_from_template" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-circle"></i> Create Series
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <p class="text-muted">Need a custom service? <a href="add.php">Create a custom service</a> instead.</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../../includes/footer.php'; ?>
</html>