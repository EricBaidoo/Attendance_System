<?php
require_once '../../../includes/security.php';
require_once '../../../includes/people_sync.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    if (isset($_GET['return'])) {
        header('Location: list?error=' . urlencode('Unauthorized'));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_role = getUserRole();
if (!canAccessModule('people_attendance', $user_role)) {
    http_response_code(403);
    if (isset($_GET['return'])) {
        header('Location: list?error=' . urlencode('Insufficient permissions'));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Secure POST request check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    if (isset($_POST['return'])) {
        header('Location: list?error=' . urlencode('Method Not Allowed'));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$csrf_token = false;
$is_form_submission = isset($_POST['action']);

if ($is_form_submission) {
    // Form submission
    $csrf_token = $_POST['csrf_token'] ?? '';
    $member_id = $_POST['id'] ?? null;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle') {
        require '../../../config/database.php';
        $check_stmt = $pdo->prepare("SELECT status FROM member_roles WHERE id = ?");
        $check_stmt->execute([$member_id]);
        $current = $check_stmt->fetch();
        if ($current) {
            $new_status = $current['status'] === 'active' ? 'inactive' : 'active';
        }
    }
} else {
    // JSON API request
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $csrf_token = $input['csrf_token'] ?? '';
        $member_id = $input['member_id'] ?? null;
        $new_status = $input['status'] ?? null;
    }
}

if (!validateCSRFToken($csrf_token)) {
    http_response_code(403);
    if ($is_form_submission && isset($_POST['return'])) {
         header('Location: list?error=' . urlencode('Invalid CSRF token'));
         exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate status
if (!in_array($new_status, ['active', 'inactive'])) {
    if ($is_get_request && isset($_GET['return'])) {
        header('Location: list?error=' . urlencode('Invalid status value'));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

try {
    if (!isset($pdo)) {
        require '../../../config/database.php';
    }
    
    // Check if member exists
    $check_stmt = $pdo->prepare("SELECT mr.id, p.full_name AS name, p.email, p.phone FROM member_roles mr JOIN people p ON mr.person_id = p.id WHERE mr.id = ?");
    $check_stmt->execute([$member_id]);
    $member = $check_stmt->fetch();
    
    if (!$member) {
        if ($is_get_request && isset($_GET['return'])) {
            header('Location: list?error=' . urlencode('Member not found'));
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit;
    }
    
    // Update member status
    $update_stmt = $pdo->prepare("UPDATE member_roles SET status = ? WHERE id = ?");
    $result = $update_stmt->execute([$new_status, $member_id]);
    
    if ($result) {
        peopleSyncRecord(
            $pdo,
            'member_roles',
            (int)$member_id,
            (string)($member['name'] ?? ''),
            (string)($member['email'] ?? ''),
            (string)($member['phone'] ?? ''),
            'member'
        );

        if ($is_get_request && isset($_GET['return'])) {
            $status_text = $new_status === 'active' ? 'activated' : 'deactivated';
            header('Location: list?success=' . urlencode('Member "' . $member['name'] . '" ' . $status_text . ' successfully'));
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Status updated successfully',
            'member_name' => $member['name'],
            'new_status' => $new_status
        ]);
    } else {
        if ($is_get_request && isset($_GET['return'])) {
            header('Location: list?error=' . urlencode('Failed to update status'));
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
    
} catch (Exception $e) {
    if ($is_get_request && isset($_GET['return'])) {
        header('Location: list?error=' . urlencode(reportDatabaseException($e)));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => reportDatabaseException($e)]);
}
?>
