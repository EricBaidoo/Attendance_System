<?php
require_once '../../../includes/security.php';
require_once '../../../includes/people_sync.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    if (isset($_GET['return'])) {
        header('Location: list.php?error=' . urlencode('Unauthorized'));
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
        header('Location: list.php?error=' . urlencode('Insufficient permissions'));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Handle both GET (from button links) and POST (from AJAX)
$is_get_request = isset($_GET['id']) && isset($_GET['action']);
$member_id = null;
$new_status = null;

if ($is_get_request) {
    // GET request with action=toggle
    $member_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'toggle') {
        // Need to fetch current status to toggle it
        require '../../../config/database.php';
        $check_stmt = $pdo->prepare("SELECT status FROM member_roles WHERE id = ?");
        $check_stmt->execute([$member_id]);
        $current = $check_stmt->fetch();
        
        if (!$current) {
            if (isset($_GET['return'])) {
                header('Location: list.php?error=' . urlencode('Member not found'));
                exit;
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Member not found']);
            exit;
        }
        
        $new_status = $current['status'] === 'active' ? 'inactive' : 'active';
    }
} else {
    // JSON POST request
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['member_id']) || !isset($input['status'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    $member_id = $input['member_id'];
    $new_status = $input['status'];
}

// Validate status
if (!in_array($new_status, ['active', 'inactive'])) {
    if ($is_get_request && isset($_GET['return'])) {
        header('Location: list.php?error=' . urlencode('Invalid status value'));
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
            header('Location: list.php?error=' . urlencode('Member not found'));
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
            header('Location: list.php?success=' . urlencode('Member "' . $member['name'] . '" ' . $status_text . ' successfully'));
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
            header('Location: list.php?error=' . urlencode('Failed to update status'));
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
    
} catch (Exception $e) {
    if ($is_get_request && isset($_GET['return'])) {
        header('Location: list.php?error=' . urlencode('Database error: ' . $e->getMessage()));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>