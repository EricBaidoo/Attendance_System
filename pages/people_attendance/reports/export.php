<?php
// pages/people_attendance/reports/export.php - Data Export Handler
require_once '../../../includes/security.php';
require_once '../../../config/database.php';
require_once '../../../includes/error_handler.php';
require_once '../../../includes/pdf_export.php';

// Require login and check permissions
requireLogin('../../../login');

// Validate export format
$allowed_formats = ['csv', 'excel', 'pdf'];
$format = isset($_GET['format']) && in_array($_GET['format'], $allowed_formats) ? $_GET['format'] : 'csv';

// Get and validate filter parameters
$validation_rules = [
    'start_date' => ['required' => false, 'max_length' => 10],
    'end_date' => ['required' => false, 'max_length' => 10],
    'department_filter' => ['required' => false, 'max_length' => 10],
    'service_filter' => ['required' => false, 'max_length' => 10]
];

$validation_result = validateAndSanitize($_GET, $validation_rules);
$filters = $validation_result['data'];

// Set default date range
$start_date = $filters['start_date'] ?: date('Y-m-01');
$end_date = $filters['end_date'] ?: date('Y-m-d');
$department_filter = $filters['department_filter'] ?: '';
$service_filter = $filters['service_filter'] ?: '';

try {
    // Comprehensive export query
    $export_sql = "SELECT 
        'Member Data' as report_section,
        p.full_name as full_name,
        p.email,
        p.phone as phone,
        d.name as department,
        mr.gender,
        mr.congregation_group,
        mr.baptized,
        mr.date_joined,
        '' as service_name,
        '' as session_date,
        '' as attendance_status,
        '' as visitor_name
    FROM member_roles mr
    JOIN people p ON p.id = mr.person_id
    LEFT JOIN departments d ON mr.department_id = d.id
    WHERE mr.status = 'active'
    " . ($department_filter ? "AND mr.department_id = " . intval($department_filter) : "") . "
    
    UNION ALL
    
    SELECT 
        'Attendance Data' as report_section,
        p.full_name as full_name,
        p.email,
        p.phone as phone,
        d.name as department,
        mr.gender,
        mr.congregation_group,
        mr.baptized,
        mr.date_joined,
        s.name as service_name,
        ss.session_date,
        a.status as attendance_status,
        '' as visitor_name
    FROM attendance a
    JOIN member_roles mr ON a.member_id = mr.id
    JOIN people p ON p.id = mr.person_id
    JOIN service_sessions ss ON a.session_id = ss.id
    JOIN services s ON ss.service_id = s.id
    LEFT JOIN departments d ON mr.department_id = d.id
    WHERE ss.session_date BETWEEN ? AND ?
    " . ($department_filter ? "AND mr.department_id = " . intval($department_filter) : "") . "
    " . ($service_filter ? "AND s.id = " . intval($service_filter) : "") . "
    
    UNION ALL
    
    SELECT 
        'Visitor Data' as report_section,
        p.full_name as full_name,
        p.email,
        p.phone,
        '' as department,
        p.gender,
        '' as congregation_group,
        '' as baptized,
        '' as date_joined,
        '' as service_name,
        '' as session_date,
        '' as attendance_status,
        p.full_name as visitor_name
    FROM visitor_roles vr
    JOIN people p ON p.id = vr.person_id
    WHERE DATE(vr.created_at) BETWEEN ? AND ?
    " . ($service_filter ? "AND vr.service_id = " . intval($service_filter) : "") . "
    
    ORDER BY report_section, full_name, session_date";
    
    $export_params = [$start_date, $end_date, $start_date, $end_date];
    $export_stmt = $pdo->prepare($export_sql);
    $export_stmt->execute($export_params);
    $export_data = $export_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute system totals for metadata (global and filtered)
    $totals = [];
    // Global active members
    $totals['total_members_global'] = (int)$pdo->query("SELECT COUNT(*) FROM member_roles WHERE status = 'active'")->fetchColumn();
    // Global visitors (all-time)
    $totals['total_visitors_global'] = (int)$pdo->query("SELECT COUNT(*) FROM visitor_roles")->fetchColumn();

    // Filtered member attendance (members present)
    $member_att_sql = "SELECT COUNT(CASE WHEN a.status = 'present' THEN 1 END) as member_present,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) as active_members_present
        FROM attendance a
        JOIN service_sessions ss ON a.session_id = ss.id
        JOIN member_roles mr ON a.member_id = mr.id
        WHERE ss.session_date BETWEEN ? AND ? AND mr.status = 'active'";

    $member_att_params = [$start_date, $end_date];
    if ($service_filter) {
        $member_att_sql .= " AND ss.service_id = ?";
        $member_att_params[] = $service_filter;
    }
    if ($department_filter) {
        $member_att_sql .= " AND mr.department_id = ?";
        $member_att_params[] = $department_filter;
    }

    $member_att_stmt = $pdo->prepare($member_att_sql);
    $member_att_stmt->execute($member_att_params);
    $member_att_row = $member_att_stmt->fetch(PDO::FETCH_ASSOC);
    $totals['member_attendance_filtered'] = (int)($member_att_row['member_present'] ?? 0);
    $totals['active_members_present_filtered'] = (int)($member_att_row['active_members_present'] ?? 0);

    // Total active members for denominator (respect department filter)
    $active_sql = "SELECT COUNT(*) FROM member_roles WHERE status = 'active'";
    $active_params = [];
    if ($department_filter) {
        $active_sql .= " AND department_id = ?";
        $active_params[] = $department_filter;
    }
    $active_stmt = $pdo->prepare($active_sql);
    $active_stmt->execute($active_params);
    $totals['total_active_members_filtered'] = (int)$active_stmt->fetchColumn();

    // Filtered visitor attendance
    $visitor_att_sql = "SELECT COUNT(*) FROM visitor_roles WHERE DATE(created_at) BETWEEN ? AND ?";
    $visitor_att_params = [$start_date, $end_date];
    if ($service_filter) {
        $visitor_att_sql .= " AND service_id = ?";
        $visitor_att_params[] = $service_filter;
    }
    $visitor_att_stmt = $pdo->prepare($visitor_att_sql);
    $visitor_att_stmt->execute($visitor_att_params);
    $totals['visitor_attendance_filtered'] = (int)$visitor_att_stmt->fetchColumn();

    $totals['total_present_filtered'] = $totals['member_attendance_filtered'] + $totals['visitor_attendance_filtered'];
    $totals['attendance_rate_filtered'] = $totals['total_active_members_filtered'] > 0
        ? round(($totals['active_members_present_filtered'] / $totals['total_active_members_filtered']) * 100, 1)
        : 0;

    // Build metadata rows to include at the top of exports
    $meta = [
        ['Report Section' => 'System Totals', 'Metric' => 'Active Members (Total)', 'Value' => $totals['total_members_global']],
        ['Report Section' => 'System Totals', 'Metric' => 'Visitors (Total)', 'Value' => $totals['total_visitors_global']],
        ['Report Section' => 'Filtered Totals', 'Metric' => 'Member Attendance (Filtered)', 'Value' => $totals['member_attendance_filtered']],
        ['Report Section' => 'Filtered Totals', 'Metric' => 'Visitor Attendance (Filtered)', 'Value' => $totals['visitor_attendance_filtered']],
        ['Report Section' => 'Filtered Totals', 'Metric' => 'Total Present (Filtered)', 'Value' => $totals['total_present_filtered']],
        ['Report Section' => 'Filtered Totals', 'Metric' => 'Active Members Present', 'Value' => $totals['active_members_present_filtered']],
        ['Report Section' => 'Filtered Totals', 'Metric' => 'Total Active Members', 'Value' => $totals['total_active_members_filtered']],
        ['Report Section' => 'Filtered Totals', 'Metric' => 'Attendance Rate (%)', 'Value' => $totals['attendance_rate_filtered']]
    ];

    // Generate filename
    $filename = 'system_report_' . $start_date . '_to_' . $end_date;

    switch ($format) {
        case 'csv':
            exportToCSV($meta, $export_data, $filename);
            break;
        case 'excel':
            exportToExcel($meta, $export_data, $filename);
            break;
        case 'pdf':
            exportToPDF($meta, $export_data, $filename, $filters);
            break;
    }

} catch (Exception $e) {
    logDatabaseError($e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Export failed. Please try again later.';
    exit;
}

function exportToCSV($meta, $data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');

    $output = fopen('php://output', 'w');

    // Add BOM for proper Excel UTF-8 handling
    fwrite($output, "\xEF\xBB\xBF");

    // Write metadata block
    if (!empty($meta)) {
        fputcsv($output, ['Report Section', 'Metric', 'Value']);
        foreach ($meta as $m) {
            fputcsv($output, [$m['Report Section'], $m['Metric'], $m['Value']]);
        }
        // Blank line between meta and detailed data
        fputcsv($output, []);
    }

    // Add detailed data headers and rows
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));

        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit;
}

function exportToExcel($meta, $data, $filename) {
    // For Excel export, we'll produce a simple HTML table Excel can open
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: no-cache, must-revalidate');

    echo "\xEF\xBB\xBF"; // BOM
    echo "<html><body>";

    // Metadata table
    if (!empty($meta)) {
        echo "<h3>System Totals</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Report Section</th><th>Metric</th><th>Value</th></tr>";
        foreach ($meta as $m) {
            echo "<tr><td>" . htmlspecialchars($m['Report Section']) . "</td><td>" . htmlspecialchars($m['Metric']) . "</td><td>" . htmlspecialchars($m['Value']) . "</td></tr>";
        }
        echo "</table><br/>";
    }

    // Detailed data
    if (!empty($data)) {
        echo "<h3>Detailed Records</h3>";
        echo "<table border='1'>";
        echo "<tr>";
        foreach (array_keys($data[0]) as $header) {
            echo "<th>" . htmlspecialchars($header) . "</th>";
        }
        echo "</tr>";

        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . htmlspecialchars($cell) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "</body></html>";
    exit;
}

function exportToPDF($meta, $data, $filename, $filters) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>System Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 10px; }
            .header { text-align: center; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .filter-info { margin-bottom: 15px; padding: 10px; background-color: #f9f9f9; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . htmlspecialchars(getInstitutionName($pdo)) . ' - System Report</h1>
            <p>Generated on ' . date('F j, Y \a\t g:i A') . '</p>
        </div>
        
        <div class="filter-info">
            <strong>Report Period:</strong> ' . date('F j, Y', strtotime($filters['start_date'] ?: date('Y-m-01'))) . ' to ' . date('F j, Y', strtotime($filters['end_date'] ?: date('Y-m-d'))) . '<br>
            <strong>Filters Applied:</strong> ' . 
            ($filters['department_filter'] ? 'Department Filter, ' : '') .
            ($filters['service_filter'] ? 'Service Filter, ' : '') .
            'None' . '
        </div>';
    // Add metadata section
    if (!empty($meta)) {
        $html .= '<h3>System Totals</h3>';
        $html .= '<table>';
        $html .= '<tr><th>Report Section</th><th>Metric</th><th>Value</th></tr>';
        foreach ($meta as $m) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($m['Report Section']) . '</td>';
            $html .= '<td>' . htmlspecialchars($m['Metric']) . '</td>';
            $html .= '<td>' . htmlspecialchars($m['Value']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table><br/>';
    }
    
    if (!empty($data)) {
        $html .= '<table>';
        
        // Headers
        $html .= '<tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr>';
        
        // Data (limit to first 100 rows for PDF)
        $limited_data = array_slice($data, 0, 100);
        foreach ($limited_data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        if (count($data) > 100) {
            $html .= '<p><em>Note: Only first 100 records shown. Use CSV export for complete data.</em></p>';
        }
    }
    
    $html .= '</body></html>';

    exportHtmlAsPdf($html, $filename . '.pdf', ['paper' => 'A4', 'orientation' => 'landscape']);
}
?>
