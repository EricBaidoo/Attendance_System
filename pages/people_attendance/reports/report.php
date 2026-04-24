<?php
// pages/people_attendance/reports/report.php - Complete System Reports

// Handle includes gracefully
$base_dir = dirname(__DIR__, 2) . '/..';

// Try to include security - fall back to basic session handling if not available
if (file_exists($base_dir . '/includes/security.php')) {
    require_once $base_dir . '/includes/security.php';
} else {
    // Basic session handling if security.php doesn't exist
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../../../login');
        exit;
    }
    
    // Basic validation function
    function validateAndSanitize($data, $rules) {
        $result = ['data' => []];
        foreach ($rules as $key => $rule) {
            $result['data'][$key] = isset($data[$key]) ? htmlspecialchars(trim($data[$key])) : '';
        }
        return $result;
    }
}

// Include database connection
require_once $base_dir . '/config/database.php';

// Try to include error handler
if (file_exists($base_dir . '/includes/error_handler.php')) {
    require_once $base_dir . '/includes/error_handler.php';
} else {
    // Basic error logging function
    function logDatabaseError($message) {
        error_log("Database Error: " . $message);
    }
}

// Require login if function exists
if (function_exists('requireLogin')) {
    requireLogin('../../../login');
}

// Get filter parameters and validate
$validation_rules = [
    'start_date' => ['required' => false, 'max_length' => 10],
    'end_date' => ['required' => false, 'max_length' => 10],
    'department_filter' => ['required' => false, 'max_length' => 10],
    'service_filter' => ['required' => false, 'max_length' => 10],
    'report_type' => ['required' => false, 'max_length' => 20]
];

$validation_result = validateAndSanitize($_GET, $validation_rules);
$filters = $validation_result['data'];

// Set default date range
$start_date = $filters['start_date'] ?: date('Y-m-01'); // First day of current month
$end_date = $filters['end_date'] ?: date('Y-m-d'); // Today
$department_filter = $filters['department_filter'] ?: '';
$service_filter = $filters['service_filter'] ?: '';
$report_type = $filters['report_type'] ?: 'overview';

try {
    // Try to temporarily disable ONLY_FULL_GROUP_BY for compatibility
    try {
        $pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    } catch (Exception $sql_mode_error) {
        // If we can't change SQL mode, continue with strict-compliant queries
    }
    
    // Get system overview statistics
    $overview_sql = "SELECT 
        (SELECT COUNT(*) FROM member_roles WHERE status = 'active') as total_members,
        (SELECT COUNT(*) FROM visitor_roles) as total_visitors,
        (SELECT COUNT(*) FROM new_convert_roles WHERE DATE(date_converted) BETWEEN ? AND ?) as new_converts,
        (SELECT COUNT(*) FROM service_sessions WHERE session_date BETWEEN ? AND ?) as total_sessions,
        (SELECT COUNT(*) FROM departments WHERE status = 'active') as active_departments,
        (SELECT COUNT(*) FROM services WHERE template_status = 'active') as active_services";

    $overview_stmt = $pdo->prepare($overview_sql);
    // Parameters correspond to new_converts and total_sessions (two ranges)
    $overview_stmt->execute([$start_date, $end_date, $start_date, $end_date]);
    $overview_stats = $overview_stmt->fetch();

    // Get attendance statistics (member attendance table)
    $attendance_sql = "SELECT 
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_present,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absent,
        COUNT(DISTINCT a.session_id) as sessions_with_attendance,
        COUNT(DISTINCT a.member_id) as unique_attendees,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) as active_members_present
        FROM attendance a
        JOIN service_sessions ss ON a.session_id = ss.id
        JOIN member_roles m ON a.member_id = m.id
        WHERE ss.session_date BETWEEN ? AND ?
        AND m.status = 'active'";

    $attendance_params = [$start_date, $end_date];
    if ($service_filter) {
        $attendance_sql .= " AND ss.service_id = ?";
        $attendance_params[] = $service_filter;
    }
    if ($department_filter) {
        $attendance_sql .= " AND m.department_id = ?";
        $attendance_params[] = $department_filter;
    }

    $attendance_stmt = $pdo->prepare($attendance_sql);
    $attendance_stmt->execute($attendance_params);
    $attendance_stats = $attendance_stmt->fetch();

    // Total active members base for attendance rate denominator
    $active_members_sql = "SELECT COUNT(*) FROM member_roles WHERE status = 'active'";
    $active_members_params = [];
    if ($department_filter) {
        $active_members_sql .= " AND department_id = ?";
        $active_members_params[] = $department_filter;
    }
    $active_members_stmt = $pdo->prepare($active_members_sql);
    $active_members_stmt->execute($active_members_params);
    $total_active_members = (int)$active_members_stmt->fetchColumn();

    // Include visitor check-ins in total church attendance
    $visitor_attendance_sql = "SELECT COUNT(*) FROM visitor_roles WHERE date BETWEEN ? AND ?";
    if ($service_filter) {
        $visitor_attendance_sql .= " AND service_id = ?";
        $visitor_attendance_params = [$start_date, $end_date, $service_filter];
    } else {
        $visitor_attendance_params = [$start_date, $end_date];
    }

    $visitor_attendance_stmt = $pdo->prepare($visitor_attendance_sql);
    $visitor_attendance_stmt->execute($visitor_attendance_params);
    $visitor_attendance_count = (int)$visitor_attendance_stmt->fetchColumn();

    $member_attendance_count = (int)($attendance_stats['total_present'] ?? 0);
    $active_members_present = (int)($attendance_stats['active_members_present'] ?? 0);

    $attendance_stats['total_active_members'] = $total_active_members;
    $attendance_stats['active_members_present'] = $active_members_present;
    $attendance_stats['attendance_percentage'] = $total_active_members > 0
        ? round(($active_members_present / $total_active_members) * 100, 1)
        : 0;

    $attendance_stats['member_attendance'] = $member_attendance_count;
    $attendance_stats['visitor_attendance'] = $visitor_attendance_count;
    $attendance_stats['total_present'] = $member_attendance_count + $visitor_attendance_count;

    // Get member demographics
    $demographics_sql = "SELECT 
        COUNT(*) as total_members,
        COUNT(CASE WHEN gender = 'male' THEN 1 END) as male_count,
        COUNT(CASE WHEN gender = 'female' THEN 1 END) as female_count,
        COUNT(CASE WHEN baptized = 'yes' THEN 1 END) as baptized_count,
        COUNT(CASE WHEN congregation_group = 'Adult' THEN 1 END) as adult_count,
        COUNT(CASE WHEN congregation_group = 'Youth' THEN 1 END) as youth_count
        FROM member_roles WHERE status = 'active'";
    
    if ($department_filter) {
        $demographics_sql .= " AND department_id = ?";
        $demographics_stmt = $pdo->prepare($demographics_sql);
        $demographics_stmt->execute([$department_filter]);
    } else {
        $demographics_stmt = $pdo->query($demographics_sql);
    }
    $demographics = $demographics_stmt->fetch();

    // Get top services by attendance
    $top_services_sql = "SELECT 
        s.id as service_id,
        s.name as service_name,
        COUNT(DISTINCT ss.id) as session_count,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_attendance,
        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as avg_attendance_rate
        FROM services s
        LEFT JOIN service_sessions ss ON s.id = ss.service_id AND ss.session_date BETWEEN ? AND ?
        LEFT JOIN attendance a ON ss.id = a.session_id
        WHERE s.template_status = 'active'
        GROUP BY s.id, s.name
        ORDER BY total_attendance DESC, s.name
        LIMIT 5";
    
    $top_services_stmt = $pdo->prepare($top_services_sql);
    $top_services_stmt->execute([$start_date, $end_date]);
    $top_services = $top_services_stmt->fetchAll();

    // Get attendance trends (last 30 days) - SQL strict mode compliant
    $trends_sql = "SELECT 
        session_date as date,
        service_name,
        service_id,
        SUM(present_count) as present_count,
        SUM(total_marked) as total_marked
        FROM (
            SELECT 
                DATE(ss.session_date) as session_date,
                s.name as service_name,
                s.id as service_id,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                COUNT(a.id) as total_marked
            FROM service_sessions ss
            JOIN services s ON ss.service_id = s.id
            LEFT JOIN attendance a ON ss.id = a.session_id
            WHERE ss.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(ss.session_date), s.id, s.name
        ) as daily_attendance
        GROUP BY session_date, service_id, service_name
        ORDER BY session_date DESC, service_name";
    
    $trends_stmt = $pdo->query($trends_sql);
    $trends_data = $trends_stmt->fetchAll();

    // Get department performance analytics
    $dept_performance_sql = "SELECT 
        d.id as department_id,
        d.name as department_name,
        COUNT(DISTINCT m.id) as total_members,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN m.id END) as active_attendees,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_present,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absent,
        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as attendance_rate
        FROM departments d
        LEFT JOIN member_roles m ON d.id = m.department_id AND m.status = 'active'
        LEFT JOIN attendance a ON m.id = a.member_id
        LEFT JOIN service_sessions ss ON a.session_id = ss.id AND ss.session_date BETWEEN ? AND ?
        WHERE d.status = 'active'
        GROUP BY d.id, d.name
        ORDER BY attendance_rate DESC, total_members DESC";
    
    $dept_stmt = $pdo->prepare($dept_performance_sql);
    $dept_stmt->execute([$start_date, $end_date]);
    $department_performance = $dept_stmt->fetchAll();

    // Get individual member attendance tracking
    $member_tracking_sql = "SELECT 
        m.id as member_id,
        p.full_name as member_name,
        d.name as department_name,
        p.phone as phone,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as times_present,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as times_absent,
        COUNT(a.id) as total_sessions_marked,
        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as attendance_percentage,
        MAX(ss.session_date) as last_attendance,
        DATEDIFF(CURDATE(), MAX(ss.session_date)) as days_since_last_attendance
        FROM member_roles m
        JOIN people p ON p.id = m.person_id
        LEFT JOIN departments d ON m.department_id = d.id
        LEFT JOIN attendance a ON m.id = a.member_id
        LEFT JOIN service_sessions ss ON a.session_id = ss.id AND ss.session_date BETWEEN ? AND ?
        WHERE m.status = 'active'
        GROUP BY m.id, p.full_name, d.name, p.phone
        HAVING total_sessions_marked > 0
        ORDER BY attendance_percentage DESC, times_present DESC
        LIMIT 50";
    
    $member_stmt = $pdo->prepare($member_tracking_sql);
    $member_stmt->execute([$start_date, $end_date]);
    $member_tracking = $member_stmt->fetchAll();

    // Get frequently absent members for follow-up
    $absent_members_sql = "SELECT 
        m.id as member_id,
        p.full_name as member_name,
        d.name as department_name,
        p.phone as phone,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as times_absent,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as times_present,
        COUNT(a.id) as total_sessions_marked,
        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as attendance_percentage,
        MAX(ss.session_date) as last_attendance
        FROM member_roles m
        JOIN people p ON p.id = m.person_id
        LEFT JOIN departments d ON m.department_id = d.id
        LEFT JOIN attendance a ON m.id = a.member_id
        LEFT JOIN service_sessions ss ON a.session_id = ss.id AND ss.session_date BETWEEN ? AND ?
        WHERE m.status = 'active'
        GROUP BY m.id, p.full_name, d.name, p.phone
        HAVING total_sessions_marked > 0 AND attendance_percentage < 50
        ORDER BY attendance_percentage ASC, times_absent DESC
        LIMIT 20";
    
    $absent_stmt = $pdo->prepare($absent_members_sql);
    $absent_stmt->execute([$start_date, $end_date]);
    $absent_members = $absent_stmt->fetchAll();

    // Get departments for filter
    $departments_stmt = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
    $departments = $departments_stmt->fetchAll();

    // Get services for filter
    $services_stmt = $pdo->query("SELECT id, name FROM services WHERE template_status = 'active' ORDER BY name");
    $services = $services_stmt->fetchAll();

    // â”€â”€ Feature 1: Period-over-Period comparison â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $period_days      = max(1, (int)((strtotime($end_date) - strtotime($start_date)) / 86400) + 1);
    $prev_end_date    = date('Y-m-d', strtotime($start_date . ' -1 day'));
    $prev_start_date  = date('Y-m-d', strtotime($prev_end_date . ' -' . ($period_days - 1) . ' days'));

    // Previous period member attendance
    $prev_att_sql = "SELECT COUNT(CASE WHEN a.status = 'present' THEN 1 END) as prev_member_present,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) as prev_active_members_present
        FROM attendance a
        JOIN service_sessions ss ON a.session_id = ss.id
        JOIN member_roles m ON a.member_id = m.id
        WHERE ss.session_date BETWEEN ? AND ? AND m.status = 'active'";
    $prev_att_params = [$prev_start_date, $prev_end_date];
    if ($service_filter)    { $prev_att_sql .= " AND ss.service_id = ?"; $prev_att_params[] = $service_filter; }
    if ($department_filter) { $prev_att_sql .= " AND m.department_id = ?"; $prev_att_params[] = $department_filter; }
    $prev_att_stmt = $pdo->prepare($prev_att_sql);
    $prev_att_stmt->execute($prev_att_params);
    $prev_att_row = $prev_att_stmt->fetch();

    // Previous period visitor attendance
    $prev_visitor_sql = "SELECT COUNT(*) FROM visitor_roles WHERE date BETWEEN ? AND ?";
    $prev_visitor_params = [$prev_start_date, $prev_end_date];
    if ($service_filter) { $prev_visitor_sql .= " AND service_id = ?"; $prev_visitor_params[] = $service_filter; }
    $prev_visitor_stmt = $pdo->prepare($prev_visitor_sql);
    $prev_visitor_stmt->execute($prev_visitor_params);
    $prev_visitor_count = (int)$prev_visitor_stmt->fetchColumn();

    $prev_member_present = (int)($prev_att_row['prev_member_present'] ?? 0);
    $prev_total_present  = $prev_member_present + $prev_visitor_count;
    $prev_active_present = (int)($prev_att_row['prev_active_members_present'] ?? 0);
    $prev_att_rate       = $total_active_members > 0
        ? round(($prev_active_present / $total_active_members) * 100, 1) : 0;

    // Deltas
    $delta_total    = $attendance_stats['total_present'] - $prev_total_present;
    $delta_rate     = round($attendance_stats['attendance_percentage'] - $prev_att_rate, 1);
    $delta_visitors = $visitor_attendance_count - $prev_visitor_count;

    $comparison = [
        'prev_start'       => $prev_start_date,
        'prev_end'         => $prev_end_date,
        'prev_total'       => $prev_total_present,
        'prev_att_rate'    => $prev_att_rate,
        'prev_visitors'    => $prev_visitor_count,
        'delta_total'      => $delta_total,
        'delta_rate'       => $delta_rate,
        'delta_visitors'   => $delta_visitors,
    ];

    // â”€â”€ Feature 2: First-time vs Returning Visitors â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $visitor_type_sql = "SELECT
        COUNT(CASE WHEN first_time = 'yes' THEN 1 END) as first_time_count,
        COUNT(CASE WHEN first_time = 'no'  THEN 1 END) as returning_count,
        COUNT(*) as total_visitors_period
        FROM visitor_roles WHERE date BETWEEN ? AND ?";
    $visitor_type_params = [$start_date, $end_date];
    if ($service_filter) { $visitor_type_sql .= " AND service_id = ?"; $visitor_type_params[] = $service_filter; }
    $visitor_type_stmt = $pdo->prepare($visitor_type_sql);
    $visitor_type_stmt->execute($visitor_type_params);
    $visitor_type_stats = $visitor_type_stmt->fetch();

    // â”€â”€ Feature 3: Visitor â†’ Member Conversion â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Converts who were originally recorded as visitors (via phone match or direct convert)
    $conversion_sql = "SELECT COUNT(*) FROM new_convert_roles WHERE DATE(date_converted) BETWEEN ? AND ?";
    $conversion_params = [$start_date, $end_date];
    $conversion_stmt = $pdo->prepare($conversion_sql);
    $conversion_stmt->execute($conversion_params);
    $converts_in_period = (int)$conversion_stmt->fetchColumn();

    $visitors_in_period = (int)($visitor_type_stats['total_visitors_period'] ?? 0);
    $conversion_rate    = $visitors_in_period > 0
        ? round(($converts_in_period / $visitors_in_period) * 100, 1) : 0;

    // Previous period conversion for comparison
    $prev_conv_stmt = $pdo->prepare("SELECT COUNT(*) FROM new_convert_roles WHERE DATE(date_converted) BETWEEN ? AND ?");
    $prev_conv_stmt->execute([$prev_start_date, $prev_end_date]);
    $prev_converts = (int)$prev_conv_stmt->fetchColumn();
    $delta_converts = $converts_in_period - $prev_converts;

    // â”€â”€ Feature 4: Attendance Streaks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Subquery collects each member's present/absent sequence for the period
    $streak_inner = "SELECT
        a.member_id,
        ss.session_date as sdate,
        CASE WHEN a.status = 'present' THEN 1 ELSE 0 END as is_present
        FROM attendance a
        JOIN service_sessions ss ON a.session_id = ss.id
        JOIN member_roles m ON a.member_id = m.id
        WHERE ss.session_date BETWEEN ? AND ?
        AND m.status = 'active'";
    $streak_params = [$start_date, $end_date];
    if ($service_filter)    { $streak_inner .= " AND ss.service_id = ?";    $streak_params[] = $service_filter; }
    if ($department_filter) { $streak_inner .= " AND m.department_id = ?"; $streak_params[] = $department_filter; }

    $streak_sql = "SELECT
        m.id   as member_id,
        p.full_name as member_name,
        d.name as department_name,
        GROUP_CONCAT(sub.is_present ORDER BY sub.sdate ASC SEPARATOR '') as session_sequence
        FROM member_roles m
        JOIN people p ON p.id = m.person_id
        LEFT JOIN departments d ON m.department_id = d.id
        JOIN ($streak_inner) sub ON m.id = sub.member_id
        WHERE m.status = 'active'
        GROUP BY m.id, p.full_name, d.name
        HAVING session_sequence IS NOT NULL AND session_sequence != ''";

    $streak_stmt = $pdo->prepare($streak_sql);
    $streak_stmt->execute($streak_params);
    $streak_raw = $streak_stmt->fetchAll();

    // Compute longest current streak per member in PHP
    $streaks = [];
    foreach ($streak_raw as $row) {
        $seq = $row['session_sequence'];
        // max streak
        $max = 0; $cur = 0;
        for ($i = 0; $i < strlen($seq); $i++) {
            if ($seq[$i] === '1') { $cur++; $max = max($max, $cur); } else { $cur = 0; }
        }
        // current (trailing) streak
        $current = 0;
        for ($i = strlen($seq) - 1; $i >= 0; $i--) {
            if ($seq[$i] === '1') $current++; else break;
        }
        $streaks[] = [
            'member_name'     => $row['member_name'],
            'department_name' => $row['department_name'],
            'max_streak'      => $max,
            'current_streak'  => $current,
            'total_sessions'  => strlen($seq),
        ];
    }
    // Sort by current streak desc, then max streak desc
    usort($streaks, function($a, $b) {
        return $b['current_streak'] !== $a['current_streak']
            ? $b['current_streak'] - $a['current_streak']
            : $b['max_streak'] - $a['max_streak'];
    });
    $streaks = array_slice($streaks, 0, 20); // top 20

} catch (Exception $e) {
    // Log the error if error handler exists
    if (function_exists('logDatabaseError')) {
        logDatabaseError($e->getMessage());
    }
    
    $error_message = 'Database report could not be loaded right now.';
    
    // Set default values to prevent further errors
    $overview_stats = [
        'total_members' => 0,
        'total_visitors' => 0,
        'new_converts' => 0,
        'total_sessions' => 0,
        'active_departments' => 0,
        'active_services' => 0
    ];
    
    $attendance_stats = [
        'total_present' => 0,
        'total_absent' => 0,
        'sessions_with_attendance' => 0,
        'unique_attendees' => 0,
        'attendance_percentage' => 0,
        'active_members_present' => 0,
        'total_active_members' => 0,
        'member_attendance' => 0,
        'visitor_attendance' => 0
    ];
    
    $demographics = [
        'total_members' => 0,
        'male_count' => 0,
        'female_count' => 0,
        'baptized_count' => 0,
        'adult_count' => 0,
        'youth_count' => 0
    ];
    
    $top_services = [];
    $trends_data = [];
    $departments = [];
    $services = [];
    $department_performance = [];
    $member_tracking = [];
    $absent_members = [];
    // New feature fallbacks
    $period_days     = 1;
    $prev_start_date = date('Y-m-01');
    $prev_end_date   = date('Y-m-d');
    $comparison = ['prev_start'=>'','prev_end'=>'','prev_total'=>0,'prev_att_rate'=>0,'prev_visitors'=>0,'delta_total'=>0,'delta_rate'=>0,'delta_visitors'=>0];
    $visitor_type_stats = ['first_time_count'=>0,'returning_count'=>0,'total_visitors_period'=>0];
    $converts_in_period = 0; $visitors_in_period = 0; $conversion_rate = 0;
    $prev_converts = 0; $delta_converts = 0;
    $visitor_attendance_count = 0;
    $streaks = [];
}

$page_title = "System Reports - " . getInstitutionName($pdo);
include '../../../includes/header.php';
?>
<link href="../../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../../assets/css/reports.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid py-4">
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         SECTION 1 â€” PAGE HEADER + FILTERS
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card report-card">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="h2 text-primary mb-1 fw-bold">
                                <i class="bi bi-graph-up"></i> System Reports &amp; Analytics
                            </h1>
                            <p class="text-muted mb-0">
                                Showing data for <strong><?php echo date('M j, Y', strtotime($start_date)); ?></strong>
                                to <strong><?php echo date('M j, Y', strtotime($end_date)); ?></strong>
                                <?php if ($department_filter || $service_filter): ?>
                                    &nbsp;Â·&nbsp;
                                    <?php if ($department_filter): ?><span class="badge bg-primary">Dept filtered</span><?php endif; ?>
                                    <?php if ($service_filter): ?><span class="badge bg-info ms-1">Service filtered</span><?php endif; ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                            <a href="identity_integrity" class="btn btn-outline-dark export-btn me-2" aria-label="Open identity integrity report">
                                <i class="bi bi-shield-check"></i> Identity Integrity
                            </a>
                            <a href="progression?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-outline-primary export-btn me-2" aria-label="Open progression and journey report">
                                <i class="bi bi-signpost-split"></i> Progression
                            </a>
                            <div class="btn-group me-2">
                                <button class="btn btn-outline-primary export-btn" aria-label="Export report as CSV" onclick="exportData('csv')">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> CSV
                                </button>
                                <button class="btn btn-outline-success export-btn" aria-label="Export report as Excel" onclick="exportData('excel')">
                                    <i class="bi bi-file-earmark-excel"></i> Excel
                                </button>
                                <button class="btn btn-outline-danger export-btn" aria-label="Export report as PDF" onclick="exportData('pdf')">
                                    <i class="bi bi-file-earmark-pdf"></i> PDF
                                </button>
                            </div>
                            <button class="btn btn-primary export-btn" aria-label="Print report" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card filter-card">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold mb-1 small"><i class="bi bi-calendar-range text-primary"></i> Start Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold mb-1 small"><i class="bi bi-calendar-check text-primary"></i> End Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-1 small"><i class="bi bi-building text-primary"></i> Department</label>
                            <select name="department_filter" class="form-select form-select-sm">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-1 small"><i class="bi bi-calendar-event text-primary"></i> Service</label>
                            <select name="service_filter" class="form-select form-select-sm">
                                <option value="">All Services</option>
                                <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>" <?php echo $service_filter == $service['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-funnel"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         SECTION 2 â€” OVERVIEW: 4 KEY STAT CARDS
         Global (unfiltered): Active Members, Total Visitors
         Filtered: Total Attendance, Attendance Rate
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="row g-3 mb-3">
        <!-- Active Members (global) -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100" style="background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo number_format($overview_stats['total_members']); ?></div>
                            <div class="stat-label">Active Members</div>
                            <div class="stat-breakdown">System-wide total</div>
                        </div>
                        <div class="text-white-50"><i class="bi bi-people fs-1"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Total Visitors (global) -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100" style="background: linear-gradient(135deg, #fd7e14 0%, #e55a0d 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo number_format($overview_stats['total_visitors']); ?></div>
                            <div class="stat-label">Total Visitors</div>
                            <div class="stat-breakdown">System-wide total</div>
                        </div>
                        <div class="text-white-50"><i class="bi bi-person-plus fs-1"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Total Attendance (filtered) -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo number_format($attendance_stats['total_present']); ?></div>
                            <div class="stat-label">Total Attendance</div>
                            <div class="stat-breakdown">
                                Members: <?php echo number_format($attendance_stats['member_attendance'] ?? 0); ?> &nbsp;|&nbsp;
                                Visitors: <?php echo number_format($attendance_stats['visitor_attendance'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="text-white-50"><i class="bi bi-check-circle fs-1"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Attendance Rate (filtered) -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100" style="background: linear-gradient(135deg, #6f42c1 0%, #59369c 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo $attendance_stats['attendance_percentage'] ?: '0'; ?>%</div>
                            <div class="stat-label">Attendance Rate</div>
                            <div class="stat-breakdown">
                                <?php echo number_format($attendance_stats['active_members_present'] ?? 0); ?> /
                                <?php echo number_format($attendance_stats['total_active_members'] ?? 0); ?> active members
                            </div>
                        </div>
                        <div class="text-white-50"><i class="bi bi-graph-up fs-1"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         SECTION 3 â€” PERIOD-OVER-PERIOD COMPARISON
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card report-card">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">
                                <i class="bi bi-arrow-left-right text-primary"></i> Period-over-Period Comparison
                            </h5>
                            <p class="text-muted mb-0 mt-1 small">
                                vs previous <?php echo $period_days; ?>-day period
                                (<?php echo date('M j', strtotime($prev_start_date)); ?> â€“ <?php echo date('M j, Y', strtotime($prev_end_date)); ?>)
                            </p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="row g-3">
                        <?php
                        $comparison_items = [
                            ['label'=>'Total Attendance',  'current'=>$attendance_stats['total_present'],         'previous'=>$comparison['prev_total'],     'delta'=>$comparison['delta_total'],    'suffix'=>'',  'icon'=>'bi-people-fill',      'color'=>'#198754'],
                            ['label'=>'Attendance Rate',   'current'=>$attendance_stats['attendance_percentage'], 'previous'=>$comparison['prev_att_rate'],  'delta'=>$comparison['delta_rate'],     'suffix'=>'%', 'icon'=>'bi-graph-up-arrow',   'color'=>'#6f42c1'],
                            ['label'=>'Visitor Check-ins', 'current'=>$attendance_stats['visitor_attendance'],    'previous'=>$comparison['prev_visitors'],  'delta'=>$comparison['delta_visitors'], 'suffix'=>'',  'icon'=>'bi-person-plus-fill', 'color'=>'#fd7e14'],
                            ['label'=>'New Converts',      'current'=>$converts_in_period,                       'previous'=>$prev_converts,                'delta'=>$delta_converts,               'suffix'=>'',  'icon'=>'bi-heart-fill',       'color'=>'#dc3545'],
                        ];
                        foreach ($comparison_items as $item):
                            $up = $item['delta'] > 0; $down = $item['delta'] < 0;
                        ?>
                        <div class="col-xl-3 col-md-6">
                            <div class="p-3 rounded-3 border h-100" style="border-left: 4px solid <?php echo $item['color']; ?> !important;">
                                <div class="small text-muted mb-1">
                                    <i class="bi <?php echo $item['icon']; ?>" style="color:<?php echo $item['color']; ?>"></i>
                                    <?php echo $item['label']; ?>
                                </div>
                                <div class="h4 fw-bold mb-0"><?php echo number_format($item['current']); ?><?php echo $item['suffix']; ?></div>
                                <div class="small text-muted">Prev: <?php echo number_format($item['previous']); ?><?php echo $item['suffix']; ?></div>
                                <div class="mt-1 fw-semibold small <?php echo $up ? 'text-success' : ($down ? 'text-danger' : 'text-muted'); ?>">
                                    <i class="bi <?php echo $up ? 'bi-arrow-up-circle-fill' : ($down ? 'bi-arrow-down-circle-fill' : 'bi-dash-circle'); ?>"></i>
                                    <?php echo ($up ? '+' : '') . number_format($item['delta']); ?><?php echo $item['suffix']; ?>
                                    <?php if ($item['previous'] > 0): ?>
                                        <span class="fw-normal">(<?php echo round(abs($item['delta'] / $item['previous']) * 100, 1); ?>%)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         SECTION 4 â€” TRENDS & DEMOGRAPHICS
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up text-primary"></i> Attendance Trends (Last 30 Days)
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="chart-container"><canvas id="attendanceChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pie-chart text-success"></i> Member Demographics
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="chart-container"><canvas id="demographicsChart"></canvas></div>
                    <div class="mt-3">
                        <div class="d-flex align-items-center mb-2">
                            <div style="width:70px;"><small class="text-muted">Male</small></div>
                            <div class="flex-grow-1">
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-primary" style="width:<?php echo $demographics['total_members'] > 0 ? ($demographics['male_count']/$demographics['total_members'])*100 : 0; ?>%"></div>
                                </div>
                            </div>
                            <small class="ms-2 fw-semibold"><?php echo $demographics['male_count']; ?></small>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div style="width:70px;"><small class="text-muted">Female</small></div>
                            <div class="flex-grow-1">
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-success" style="width:<?php echo $demographics['total_members'] > 0 ? ($demographics['female_count']/$demographics['total_members'])*100 : 0; ?>%"></div>
                                </div>
                            </div>
                            <small class="ms-2 fw-semibold"><?php echo $demographics['female_count']; ?></small>
                        </div>
                        <div class="d-flex align-items-center">
                            <div style="width:70px;"><small class="text-muted">Baptized</small></div>
                            <div class="flex-grow-1">
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-info" style="width:<?php echo $demographics['total_members'] > 0 ? ($demographics['baptized_count']/$demographics['total_members'])*100 : 0; ?>%"></div>
                                </div>
                            </div>
                            <small class="ms-2 fw-semibold"><?php echo round(($demographics['baptized_count']/max($demographics['total_members'],1))*100); ?>%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         SECTION 5 â€” SERVICE PERFORMANCE + QUICK STATS
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-star text-warning"></i> Top Performing Services
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($top_services)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted fs-1"></i>
                            <p class="text-muted mt-3">No service data for this period.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($top_services as $index => $service): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="badge bg-primary rounded-pill me-3"><?php echo $index + 1; ?></div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                <small class="text-muted"><?php echo $service['total_attendance']; ?> attendees Â· <?php echo $service['session_count']; ?> sessions</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-primary"><?php echo $service['avg_attendance_rate']; ?>%</div>
                                <small class="text-muted">avg rate</small>
                            </div>
                        </div>
                        <?php if ($index < count($top_services)-1): ?><hr class="my-2"><?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-speedometer2 text-info"></i> Quick Statistics
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-primary fw-bold"><?php echo $overview_stats['total_sessions']; ?></div>
                                <small class="text-muted">Sessions (Period)</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-success fw-bold"><?php echo $overview_stats['new_converts']; ?></div>
                                <small class="text-muted">New Converts</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-warning fw-bold"><?php echo $overview_stats['active_departments']; ?></div>
                                <small class="text-muted">Active Departments</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-info fw-bold"><?php echo $attendance_stats['unique_attendees']; ?></div>
                                <small class="text-muted">Unique Attendees</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-danger fw-bold"><?php echo $overview_stats['active_services'] ?? 'â€”'; ?></div>
                                <small class="text-muted">Active Services</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-secondary fw-bold"><?php echo $attendance_stats['sessions_with_attendance'] ?? 'â€”'; ?></div>
                                <small class="text-muted">Sessions w/ Records</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         SECTION 6 â€” VISITOR INSIGHTS
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pie-chart-fill text-warning"></i> First-time vs Returning Visitors
                    </h5>
                    <p class="text-muted mb-0 mt-1 small">Filtered period</p>
                </div>
                <div class="card-body p-4">
                    <?php $total_v = (int)($visitor_type_stats['total_visitors_period'] ?? 0); ?>
                    <?php if ($total_v === 0): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-person-x text-muted fs-1"></i>
                            <p class="text-muted mt-3">No visitor data for this period.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container mb-3"><canvas id="visitorTypeChart"></canvas></div>
                        <div class="row text-center g-2">
                            <div class="col-6">
                                <div class="p-2 rounded" style="background:#fff3cd;">
                                    <div class="h5 fw-bold text-warning mb-0"><?php echo number_format($visitor_type_stats['first_time_count']); ?></div>
                                    <small class="text-muted">First-time</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 rounded" style="background:#d1ecf1;">
                                    <div class="h5 fw-bold text-info mb-0"><?php echo number_format($visitor_type_stats['returning_count']); ?></div>
                                    <small class="text-muted">Returning</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-heart-pulse text-danger"></i> Visitor â†’ Member Conversion
                    </h5>
                    <p class="text-muted mb-0 mt-1 small">New converts recorded in the filtered period</p>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-4 text-center">
                            <div style="position:relative;width:130px;margin:auto;">
                                <canvas id="conversionGauge"></canvas>
                                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                                    <div class="h4 fw-bold mb-0 text-danger"><?php echo $conversion_rate; ?>%</div>
                                    <small class="text-muted">rate</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded text-center">
                                        <div class="h4 fw-bold text-danger mb-0"><?php echo number_format($converts_in_period); ?></div>
                                        <small class="text-muted">Converts this period</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded text-center">
                                        <div class="h4 fw-bold text-muted mb-0"><?php echo number_format($prev_converts); ?></div>
                                        <small class="text-muted">Converts prev. period</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded text-center">
                                        <div class="h4 fw-bold text-warning mb-0"><?php echo number_format($visitors_in_period); ?></div>
                                        <small class="text-muted">Visitors in period</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded text-center">
                                        <div class="h4 fw-bold <?php echo $delta_converts>0?'text-success':($delta_converts<0?'text-danger':'text-muted'); ?> mb-0">
                                            <?php echo ($delta_converts>0?'+':'').$delta_converts; ?>
                                        </div>
                                        <small class="text-muted">vs prev. period</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         SECTION 7 â€” MEMBER ANALYSIS (Dept Performance + Streaks)
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="row g-4 mb-4">
        <!-- Department Performance -->
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-building text-primary"></i> Department Performance
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($department_performance)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-building-x text-muted fs-1"></i>
                            <p class="text-muted mt-3">No department data for this period.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height:350px;overflow-y:auto;border:1px solid #dee2e6;border-radius:8px;">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Department</th>
                                        <th class="text-center">Members</th>
                                        <th class="text-center">Rate</th>
                                        <th class="text-center">Present</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($department_performance as $dept): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                                            <small class="text-muted"><?php echo $dept['active_attendees']; ?> active attendees</small>
                                        </td>
                                        <td class="text-center"><?php echo $dept['total_members']; ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo ($dept['attendance_rate']>=70)?'bg-success':(($dept['attendance_rate']>=50)?'bg-warning':'bg-danger'); ?>">
                                                <?php echo $dept['attendance_rate'] ?: '0'; ?>%
                                            </span>
                                        </td>
                                        <td class="text-center"><strong class="text-primary"><?php echo $dept['total_present']; ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Attendance Streaks -->
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-fire text-danger"></i> Attendance Streaks
                    </h5>
                    <p class="text-muted mb-0 mt-1 small">Longest consecutive runs in the filtered period</p>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($streaks)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted fs-1"></i>
                            <p class="text-muted mt-3">No streak data for this period.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height:350px;overflow-y:auto;border:1px solid #dee2e6;border-radius:8px;">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Member</th>
                                        <th>Dept</th>
                                        <th class="text-center">Current</th>
                                        <th class="text-center">Best</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($streaks as $i => $s):
                                        $cur   = $s['current_streak'];
                                        $flame = $cur >= 5 ? 'ðŸ”¥ ' : ($cur >= 3 ? 'âš¡ ' : '');
                                    ?>
                                    <tr>
                                        <td><span class="badge <?php echo $i===0?'bg-warning text-dark':($i<3?'bg-primary':'bg-secondary'); ?>">#<?php echo $i+1; ?></span></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($s['member_name']); ?></td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($s['department_name'] ?? 'â€”'); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $cur>=5?'danger':($cur>=3?'warning text-dark':'success'); ?>">
                                                <?php echo $flame.$cur; ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><span class="badge bg-primary"><?php echo $s['max_streak']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         SECTION 8 â€” FOLLOW-UP LIST
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card report-card">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle text-warning"></i> Members Needing Follow-up
                            </h5>
                            <p class="text-muted mb-0 mt-1 small">Active members with attendance below 50%</p>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" onclick="exportFollowUpList()">
                            <i class="bi bi-download"></i> Export List
                        </button>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($absent_members)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle text-success fs-1"></i>
                            <p class="text-muted mt-3">No members need immediate follow-up.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height:400px;overflow-y:auto;border:1px solid #dee2e6;border-radius:8px;">
                            <table class="table table-hover mb-0" id="followUpTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Member</th>
                                        <th>Department</th>
                                        <th>Phone</th>
                                        <th class="text-center">Rate</th>
                                        <th class="text-center">Missed</th>
                                        <th class="text-center">Last Seen</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($absent_members as $member): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($member['member_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['department_name'] ?? 'No Dept'); ?></td>
                                        <td>
                                            <?php if ($member['phone']): ?>
                                                <a href="tel:<?php echo $member['phone']; ?>" class="text-decoration-none">
                                                    <i class="bi bi-telephone"></i> <?php echo $member['phone']; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">â€”</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><span class="badge bg-danger"><?php echo $member['attendance_percentage']; ?>%</span></td>
                                        <td class="text-center"><strong class="text-danger"><?php echo $member['times_absent']; ?></strong></td>
                                        <td class="text-center"><small class="text-muted"><?php echo $member['last_attendance'] ? date('M j', strtotime($member['last_attendance'])) : 'Never'; ?></small></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($member['phone']): ?>
                                                <a href="tel:<?php echo $member['phone']; ?>" class="btn btn-outline-primary"><i class="bi bi-telephone"></i></a>
                                                <a href="sms:<?php echo $member['phone']; ?>" class="btn btn-outline-success"><i class="bi bi-chat-text"></i></a>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-info" onclick="markForFollowUp(<?php echo $member['member_id']; ?>)"><i class="bi bi-bookmark"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         SECTION 9 â€” INDIVIDUAL MEMBER TRACKING (Full Table)
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card report-card">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="card-title mb-0">
                                <i class="bi bi-people text-info"></i> Individual Member Tracking
                            </h5>
                            <p class="text-muted mb-0 mt-1 small">Detailed attendance for all active members in the filtered period</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-primary btn-sm" type="button" aria-label="Toggle top 10 attendance leaders" data-bs-toggle="collapse" data-bs-target="#top10Collapse">
                                <i class="bi bi-award"></i> Top 10
                            </button>
                            <button class="btn btn-outline-success btn-sm" aria-label="Export member tracking" onclick="exportMemberTracking()">
                                <i class="bi bi-download"></i> Export
                            </button>
                            <div class="input-group input-group-sm" style="width:220px;">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="memberSearch" placeholder="Search membersâ€¦" aria-label="Search members in tracking table">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($member_tracking)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted fs-1"></i>
                            <p class="text-muted mt-3">No member tracking data for this period.</p>
                        </div>
                    <?php else: ?>
                        <!-- Top 10 Collapse -->
                        <div class="collapse mb-3" id="top10Collapse">
                            <div class="card border">
                                <div class="card-header bg-light py-2">
                                    <strong><i class="bi bi-trophy text-warning"></i> Top 10 Attendance Leaders</strong>
                                </div>
                                <div class="card-body p-3" style="max-height:300px;overflow-y:auto;">
                                    <?php foreach (array_slice($member_tracking, 0, 10) as $index => $member): ?>
                                    <div class="d-flex align-items-center mb-2 py-2 <?php echo $index < 9 ? 'border-bottom' : ''; ?>">
                                        <div class="badge bg-<?php echo $index===0?'warning text-dark':($index<3?'primary':'secondary'); ?> rounded-pill me-3"><?php echo $index+1; ?></div>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold"><?php echo htmlspecialchars($member['member_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['department_name'] ?? 'No Dept'); ?> Â· <?php echo $member['times_present']; ?> sessions</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-success"><?php echo $member['attendance_percentage']; ?>%</div>
                                            <small class="text-muted"><?php echo $member['days_since_last_attendance'] ? $member['days_since_last_attendance'].'d ago' : 'Recent'; ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Showing <?php echo count($member_tracking); ?> members</small>
                        </div>

                        <div class="table-responsive" style="max-height:500px;overflow-y:auto;border:1px solid #dee2e6;border-radius:8px;">
                            <table class="table table-hover table-sm mb-0" id="memberTrackingTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Member</th>
                                        <th>Department</th>
                                        <th class="text-center">Rate</th>
                                        <th class="text-center">Present</th>
                                        <th class="text-center">Absent</th>
                                        <th class="text-center">Sessions</th>
                                        <th class="text-center">Last Seen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($member_tracking as $index => $member): ?>
                                    <tr class="member-row">
                                        <td><span class="badge <?php echo ($index<5)?'bg-success':(($index<15)?'bg-primary':'bg-secondary'); ?>">#<?php echo $index+1; ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($member['member_name']); ?></div>
                                            <?php if ($member['phone']): ?><small class="text-muted"><i class="bi bi-telephone"></i> <?php echo $member['phone']; ?></small><?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['department_name'] ?? 'No Dept'); ?></td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <div class="progress me-2" style="width:40px;height:5px;">
                                                    <div class="progress-bar <?php echo ($member['attendance_percentage']>=80)?'bg-success':(($member['attendance_percentage']>=60)?'bg-warning':'bg-danger'); ?>" style="width:<?php echo $member['attendance_percentage']; ?>%"></div>
                                                </div>
                                                <span class="fw-bold"><?php echo $member['attendance_percentage']; ?>%</span>
                                            </div>
                                        </td>
                                        <td class="text-center"><span class="badge bg-success"><?php echo $member['times_present']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger"><?php echo $member['times_absent']; ?></span></td>
                                        <td class="text-center"><?php echo $member['total_sessions_marked']; ?></td>
                                        <td class="text-center">
                                            <small class="text-muted">
                                                <?php if ($member['last_attendance']): ?>
                                                    <?php echo date('M j, Y', strtotime($member['last_attendance'])); ?><br>
                                                    <span class="text-muted">(<?php echo $member['days_since_last_attendance']; ?>d ago)</span>
                                                <?php else: ?>Never<?php endif; ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (count($member_tracking) >= 50): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle"></i> Showing top 50 members. Adjust filters to see a different subset.
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// Member search functionality
document.addEventListener('DOMContentLoaded', function() {
    const memberSearch = document.getElementById('memberSearch');
    if (memberSearch) {
        memberSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#memberTrackingTable .member-row');
            
            rows.forEach(row => {
                const memberName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const department = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                
                if (memberName.includes(searchTerm) || department.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});

// Export follow-up list
function exportFollowUpList() {
    const table = document.getElementById('followUpTable');
    if (!table) return;
    
    let csv = 'Member Name,Department,Phone,Attendance Rate,Sessions Missed,Last Attendance\n';
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const memberName = cells[0].textContent.trim();
        const department = cells[1].textContent.trim();
        const phone = cells[2].textContent.replace(/[^\d\s\+\-\(\)]/g, '').trim();
        const attendanceRate = cells[3].textContent.trim();
        const sessionsMissed = cells[4].textContent.trim();
        const lastAttendance = cells[5].textContent.trim();
        
        csv += `"${memberName}","${department}","${phone}","${attendanceRate}","${sessionsMissed}","${lastAttendance}"\n`;
    });
    
    // Create and download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'follow_up_list_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Mark member for follow-up
function markForFollowUp(memberId) {
    if (confirm('Mark this member for follow-up? This will add them to your follow-up list.')) {
        // In a real implementation, this would make an AJAX call to save the follow-up status
        console.log('Marking member ' + memberId + ' for follow-up');
        
        // Visual feedback
        const button = event.target.closest('button');
        button.innerHTML = '<i class="bi bi-bookmark-check"></i>';
        button.className = 'btn btn-success';
        button.disabled = true;
        
        // Show success message
        showToast('Member marked for follow-up!', 'success');
    }
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 3000);
}

// Enhanced export functionality
function exportMemberTracking() {
    const table = document.getElementById('memberTrackingTable');
    if (!table) return;
    
    let csv = 'Rank,Member Name,Department,Phone,Attendance Rate,Present,Absent,Total Sessions,Last Seen\n';
    
    const visibleRows = Array.from(table.querySelectorAll('tbody .member-row'))
        .filter(row => row.style.display !== 'none');
    
    visibleRows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        const rank = index + 1;
        const memberName = cells[1].querySelector('.fw-semibold').textContent.trim();
        const phone = cells[1].querySelector('small') ? 
            cells[1].querySelector('small').textContent.replace(/[^\d\s\+\-\(\)]/g, '').trim() : '';
        const department = cells[2].textContent.trim();
        const attendanceRate = cells[3].querySelector('span').textContent.trim();
        const present = cells[4].textContent.trim();
        const absent = cells[5].textContent.trim();
        const totalSessions = cells[6].textContent.trim();
        const lastSeen = cells[7].textContent.replace(/\n/g, ' ').trim();
        
        csv += `"${rank}","${memberName}","${department}","${phone}","${attendanceRate}","${present}","${absent}","${totalSessions}","${lastSeen}"\n`;
    });
    
    // Create and download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'member_tracking_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Chart.js Configuration
Chart.defaults.font.family = 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
Chart.defaults.color = '#6b7280';

// Prepare data from PHP
const trendsData = <?php echo json_encode($trends_data); ?>;
const demographics = <?php echo json_encode($demographics); ?>;

// Process trends data for chart
const last7Days = [];
const attendanceByDay = {};

// Get last 7 days
for (let i = 6; i >= 0; i--) {
    const date = new Date();
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    last7Days.push(dateStr);
    attendanceByDay[dateStr] = 0;
}

// Fill in actual attendance data
trendsData.forEach(item => {
    if (attendanceByDay.hasOwnProperty(item.date)) {
        attendanceByDay[item.date] += parseInt(item.present_count);
    }
});

const chartData = last7Days.map(date => attendanceByDay[date]);
const chartLabels = last7Days.map(date => {
    return new Date(date + 'T12:00:00').toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
});

// Attendance Trends Chart
const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
new Chart(attendanceCtx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Attendance',
            data: chartData,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#0d6efd',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                borderColor: '#0d6efd',
                borderWidth: 1,
                cornerRadius: 8,
                displayColors: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)',
                    drawBorder: false
                },
                ticks: {
                    font: {
                        size: 12
                    },
                    color: '#6b7280'
                }
            },
            x: {
                grid: {
                    display: false,
                    drawBorder: false
                },
                ticks: {
                    font: {
                        size: 12
                    },
                    color: '#6b7280'
                }
            }
        }
    }
});

// Demographics Chart
const demographicsCtx = document.getElementById('demographicsChart').getContext('2d');
new Chart(demographicsCtx, {
    type: 'doughnut',
    data: {
        labels: ['Male', 'Female'],
        datasets: [{
            data: [demographics.male_count, demographics.female_count],
            backgroundColor: ['#0d6efd', '#198754'],
            borderWidth: 0,
            cutout: '65%'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.raw / total) * 100).toFixed(1);
                        return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Export functionality
function exportData(format) {
    const startDate = document.querySelector('input[name="start_date"]').value;
    const endDate = document.querySelector('input[name="end_date"]').value;
    const department = document.querySelector('select[name="department_filter"]').value;
    const service = document.querySelector('select[name="service_filter"]').value;
    
    // Create download URL
    let url = 'export?format=' + format;
    url += '&start_date=' + encodeURIComponent(startDate);
    url += '&end_date=' + encodeURIComponent(endDate);
    if (department) url += '&department_filter=' + encodeURIComponent(department);
    if (service) url += '&service_filter=' + encodeURIComponent(service);
    
    // Trigger download
    window.location.href = url;
}

// Auto-refresh functionality
let refreshInterval;

function startAutoRefresh() {
    refreshInterval = setInterval(() => {
        // In a real implementation, this would use AJAX to update data
        console.log('Auto-refreshing report data...');
    }, 300000); // 5 minutes
}

// Initialize auto-refresh
startAutoRefresh();

// â”€â”€ Visitor Type Chart â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const visitorTypeCtx = document.getElementById('visitorTypeChart');
if (visitorTypeCtx) {
    new Chart(visitorTypeCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['First-time', 'Returning'],
            datasets: [{
                data: [
                    <?php echo (int)($visitor_type_stats['first_time_count'] ?? 0); ?>,
                    <?php echo (int)($visitor_type_stats['returning_count'] ?? 0); ?>
                ],
                backgroundColor: ['#ffc107', '#0dcaf0'],
                borderWidth: 0,
                cutout: '65%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                            return ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
}

// â”€â”€ Conversion Gauge (doughnut used as semicircle gauge) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const convCtx = document.getElementById('conversionGauge');
if (convCtx) {
    const rate = <?php echo (float)$conversion_rate; ?>;
    new Chart(convCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [rate, 100 - rate],
                backgroundColor: ['#dc3545', '#e9ecef'],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false }, tooltip: { enabled: false } }
        }
    });
}

// Print optimization
window.addEventListener('beforeprint', function() {
    // Hide interactive elements when printing
    document.querySelectorAll('.btn, .dropdown, .form-control').forEach(el => {
        el.style.display = 'none';
    });
});

window.addEventListener('afterprint', function() {
    // Restore interactive elements after printing
    document.querySelectorAll('.btn, .dropdown, .form-control').forEach(el => {
        el.style.display = '';
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
