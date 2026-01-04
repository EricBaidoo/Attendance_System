<?php
session_start();

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

// Database connection
try {
    require '../../config/database.php';
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get member ID
$member_id = $_POST['id'] ?? $_GET['id'] ?? 0;
$return_url = $_POST['return'] ?? $_GET['return'] ?? 'list';

if (!$member_id) {
    $_SESSION['error'] = 'Invalid member ID';
    header('Location: ' . ($return_url === 'list' ? 'list.php' : 'view.php?id=' . $member_id));
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get member details before deletion (for logging/audit)
    $member_stmt = $pdo->prepare("SELECT name, id FROM members WHERE id = ?");
    $member_stmt->execute([$member_id]);
    $member = $member_stmt->fetch();

    if (!$member) {
        throw new Exception("Member not found");
    }

    // Delete related records first (delete only from tables that exist)
    // Delete attendance records
    try {
        $pdo->prepare("DELETE FROM attendance WHERE member_id = ?")->execute([$member_id]);
    } catch (Exception $e) { /* attendance table might not exist */ }
    
    // Delete visitor records
    try {
        $pdo->prepare("DELETE FROM visitors WHERE member_id = ?")->execute([$member_id]);
    } catch (Exception $e) { /* visitors table might not exist */ }
    
    // Delete related records from other tables if they exist
    try {
        $pdo->prepare("DELETE FROM member_skills WHERE member_id = ?")->execute([$member_id]);
    } catch (Exception $e) { /* member_skills table might not exist */ }
    
    try {
        $pdo->prepare("DELETE FROM follow_ups WHERE member_id = ?")->execute([$member_id]);
    } catch (Exception $e) { /* follow_ups table might not exist */ }
    
    try {
        $pdo->prepare("DELETE FROM member_positions WHERE member_id = ?")->execute([$member_id]);
    } catch (Exception $e) { /* member_positions table might not exist */ }
    
    try {
        $pdo->prepare("DELETE FROM events WHERE organizer_id = ?")->execute([$member_id]);
    } catch (Exception $e) { /* events table might not exist */ }
    
    try {
        $pdo->prepare("DELETE FROM families WHERE head_of_family = ?")->execute([$member_id]);
    } catch (Exception $e) { /* families table might not exist */ }

    // Finally delete the member
    $delete_stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
    $delete_stmt->execute([$member_id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = 'Member "' . htmlspecialchars($member['name']) . '" has been successfully deleted.';
    header('Location: list.php');
    exit;

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    $_SESSION['error'] = 'Error deleting member: ' . $e->getMessage();
    header('Location: ' . ($return_url === 'list' ? 'list.php' : 'view.php?id=' . $member_id));
    exit;
}
?>
