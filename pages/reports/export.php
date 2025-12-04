<?php
// pages/reports/export.php - Data Export Handler
require_once '../../includes/security.php';
require_once '../../config/database.php';
require_once '../../includes/error_handler.php';

// Require login and check permissions
requireLogin();

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
        m.name as full_name,
        m.email,
        m.phone as phone,
        d.name as department,
        m.gender,
        m.congregation_group,
        m.baptized,
        m.date_joined,
        '' as service_name,
        '' as session_date,
        '' as attendance_status,
        '' as visitor_name
    FROM members m
    LEFT JOIN departments d ON m.department_id = d.id
    WHERE m.status = 'active'
    " . ($department_filter ? "AND m.department_id = " . intval($department_filter) : "") . "
    
    UNION ALL
    
    SELECT 
        'Attendance Data' as report_section,
        m.name as full_name,
        m.email,
        m.phone as phone,
        d.name as department,
        m.gender,
        m.congregation_group,
        m.baptized,
        m.date_joined,
        s.name as service_name,
        ss.session_date,
        a.status as attendance_status,
        '' as visitor_name
    FROM attendance a
    JOIN members m ON a.member_id = m.id
    JOIN service_sessions ss ON a.session_id = ss.id
    JOIN services s ON ss.service_id = s.id
    LEFT JOIN departments d ON m.department_id = d.id
    WHERE ss.session_date BETWEEN ? AND ?
    " . ($department_filter ? "AND m.department_id = " . intval($department_filter) : "") . "
    " . ($service_filter ? "AND s.id = " . intval($service_filter) : "") . "
    
    UNION ALL
    
    SELECT 
        'Visitor Data' as report_section,
        v.name as full_name,
        v.email,
        v.phone,
        '' as department,
        v.gender,
        '' as congregation_group,
        '' as baptized,
        '' as date_joined,
        '' as service_name,
        '' as session_date,
        '' as attendance_status,
        '' as visitor_name
    FROM visitors v
    WHERE DATE(v.created_at) BETWEEN ? AND ?
    
    ORDER BY report_section, full_name, session_date";
    
    $export_params = [$start_date, $end_date, $start_date, $end_date];
    $export_stmt = $pdo->prepare($export_sql);
    $export_stmt->execute($export_params);
    $export_data = $export_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate filename
    $filename = 'system_report_' . $start_date . '_to_' . $end_date;

    switch ($format) {
        case 'csv':
            exportToCSV($export_data, $filename);
            break;
        case 'excel':
            exportToExcel($export_data, $filename);
            break;
        case 'pdf':
            exportToPDF($export_data, $filename, $filters);
            break;
    }

} catch (Exception $e) {
    logDatabaseError($e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Export failed. Please try again later.';
    exit;
}

function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper Excel UTF-8 handling
    fwrite($output, "\xEF\xBB\xBF");
    
    // Add headers
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportToExcel($data, $filename) {
    // For Excel export, we'll use CSV format with Excel-specific headers
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo "\xEF\xBB\xBF"; // BOM
    echo "<table border='1'>";
    
    if (!empty($data)) {
        // Headers
        echo "<tr>";
        foreach (array_keys($data[0]) as $header) {
            echo "<th>" . htmlspecialchars($header) . "</th>";
        }
        echo "</tr>";
        
        // Data
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . htmlspecialchars($cell) . "</td>";
            }
            echo "</tr>";
        }
    }
    
    echo "</table>";
    exit;
}

function exportToPDF($data, $filename, $filters) {
    // Simple HTML to PDF conversion
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    
    // For a basic implementation, we'll create an HTML version
    // In production, use a proper PDF library like TCPDF or DOMPDF
    
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
            <h1>Bridge Ministries International - System Report</h1>
            <p>Generated on ' . date('F j, Y \a\t g:i A') . '</p>
        </div>
        
        <div class="filter-info">
            <strong>Report Period:</strong> ' . date('F j, Y', strtotime($filters['start_date'] ?: date('Y-m-01'))) . ' to ' . date('F j, Y', strtotime($filters['end_date'] ?: date('Y-m-d'))) . '<br>
            <strong>Filters Applied:</strong> ' . 
            ($filters['department_filter'] ? 'Department Filter, ' : '') .
            ($filters['service_filter'] ? 'Service Filter, ' : '') .
            'None' . '
        </div>';
    
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
    
    // For basic PDF output, we'll use HTML headers
    // In production, replace this with proper PDF generation
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $filename . '.html"');
    
    echo $html;
    exit;
}
?>