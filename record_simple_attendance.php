<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$member_id = mysqli_real_escape_string($conn, $_POST['member_id']);
$cell_center_id = mysqli_real_escape_string($conn, $_POST['cell_center_id']);
$service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
$location_lat = mysqli_real_escape_string($conn, $_POST['location_lat']);
$location_lng = mysqli_real_escape_string($conn, $_POST['location_lng']);
$location_accuracy = mysqli_real_escape_string($conn, $_POST['location_accuracy']);

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

mysqli_begin_transaction($conn);

try {
    // 1. Check if already checked in today at this center
    $check_query = "SELECT id FROM cell_attendance 
                    WHERE cell_center_id = '$cell_center_id' 
                    AND member_id = '$member_id' 
                    AND attendance_date = '$today'";

    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        throw new Exception('Already checked in at this center today');
    }

    // 2. Record in cell_attendance (SIMPLE - no meeting ID needed)
    $cell_query = "INSERT INTO cell_attendance 
                   (cell_center_id, member_id, service_id, attendance_date, checked_in_at, 
                    location_lat, location_lng, location_accuracy, checkin_method) 
                   VALUES 
                   ('$cell_center_id', '$member_id', '$service_id', '$today', '$now',
                    '$location_lat', '$location_lng', '$location_accuracy', 'geolocation')";

    if (!mysqli_query($conn, $cell_query)) {
        throw new Exception('Failed to record attendance');
    }

    // 3. Record in main attendance
    $main_query = "INSERT INTO attendance 
                   (member_id, service_id, date, status, method, 
                    location_lat, location_lng, location_accuracy, checkin_time) 
                   VALUES 
                   ('$member_id', '$service_id', '$today', 'present', 'auto',
                    '$location_lat', '$location_lng', '$location_accuracy', '$now')";

    if (!mysqli_query($conn, $main_query)) {
        throw new Exception('Failed to record main attendance');
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Check-in successful']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($conn);
