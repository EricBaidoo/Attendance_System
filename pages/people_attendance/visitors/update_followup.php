<?php
require_once '../../../includes/security.php';
require_once '../../../includes/people_sync.php';
header('Content-Type: application/json');

// Authentication check
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_role = getUserRole();
if (!canAccessModule('people_attendance', $user_role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['visitor_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$visitor_id = $input['visitor_id'];
$action = $input['action'];

if ($action !== 'complete') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    require '../../../config/database.php';
    
    // Check if visitor exists
    $check_stmt = $pdo->prepare("SELECT id, name, email, phone, person_id FROM visitor_roles WHERE id = ?");
    $check_stmt->execute([$visitor_id]);
    $visitor = $check_stmt->fetch();
    
    if (!$visitor) {
        echo json_encode(['success' => false, 'message' => 'Visitor not found']);
        exit;
    }
    
    // Update follow-up status
    $update_stmt = $pdo->prepare("UPDATE visitor_roles SET follow_up_completed = 'yes', follow_up_date = NOW() WHERE id = ?");
    $result = $update_stmt->execute([$visitor_id]);
    
    if ($result) {
        $pid = !empty($visitor['person_id']) ? (int)$visitor['person_id'] : null;
        if (!$pid) {
            $pid = peopleFindOrCreate(
                $pdo,
                (string)($visitor['name'] ?? ''),
                (string)($visitor['email'] ?? ''),
                (string)($visitor['phone'] ?? ''),
                'visitor'
            );
        }

        if ($pid) {
            peopleRelinkRecord($pdo, 'visitors', (int)$visitor_id, $pid);
            peopleEnsureStage($pdo, $pid, 'visitor');
            peopleAddLifecycleEvent($pdo, $pid, 'visitor_followup_completed', 'visitor_roles', (int)$visitor_id, 'Follow-up completed via update_followup endpoint');
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Follow-up marked as completed',
            'visitor_name' => $visitor['name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update follow-up status']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>