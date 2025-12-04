<?php
/**
 * Session Attendance Utilities
 * Functions to calculate total attendance including members and visitors
 */

/**
 * Get comprehensive session attendance data
 * @param PDO $pdo Database connection
 * @param int $session_id Session ID
 * @return array Attendance statistics
 */
function getSessionAttendance($pdo, $session_id) {
    try {
        // Get session details
        $session_sql = "SELECT ss.*, s.name as service_name, s.id as service_id
                       FROM service_sessions ss
                       JOIN services s ON ss.service_id = s.id
                       WHERE ss.id = ?";
        $session_stmt = $pdo->prepare($session_sql);
        $session_stmt->execute([$session_id]);
        $session = $session_stmt->fetch();
        
        if (!$session) {
            return null;
        }
        
        // Get member attendance
        $member_sql = "SELECT COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) as present,
                             COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN a.member_id END) as absent,
                             (SELECT COUNT(*) FROM members WHERE status = 'active') as total_members
                      FROM attendance a
                      WHERE a.session_id = ?";
        $member_stmt = $pdo->prepare($member_sql);
        $member_stmt->execute([$session_id]);
        $member_stats = $member_stmt->fetch();
        
        // Get visitor attendance (visitors table already contains attendance data)
        $visitor_sql = "SELECT COUNT(DISTINCT v.id) as visitor_count
                       FROM visitors v 
                       WHERE v.service_id = ? AND v.date = ?";
        $visitor_stmt = $pdo->prepare($visitor_sql);
        $visitor_stmt->execute([$session['service_id'], $session['session_date']]);
        $visitor_stats = $visitor_stmt->fetch();
        
        // Calculate statistics
        $present_members = $member_stats['present'] ?? 0;
        $total_members = $member_stats['total_members'] ?? 0;
        $visitors = $visitor_stats['visitor_count'] ?? 0;
        
        return [
            'session' => $session,
            'members_present' => $present_members,
            'members_absent' => $member_stats['absent'] ?? 0,
            'total_members' => $total_members,
            'visitors' => $visitors,
            'total_attendance' => $present_members + $visitors,
            'member_attendance_rate' => $total_members > 0 ? round(($present_members / $total_members) * 100, 1) : 0
        ];
        
    } catch (Exception $e) {
        error_log("Error getting session attendance: " . $e->getMessage());
        return null;
    }
}

/**
 * Get attendance summary for multiple sessions
 * @param PDO $pdo Database connection
 * @param string $date Date (YYYY-MM-DD format)
 * @return array Array of session attendance data
 */
function getDailyAttendanceSummary($pdo, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    try {
        $sessions_sql = "SELECT id FROM service_sessions WHERE session_date = ? ORDER BY opened_at DESC";
        $sessions_stmt = $pdo->prepare($sessions_sql);
        $sessions_stmt->execute([$date]);
        $sessions = $sessions_stmt->fetchAll();
        
        $summary = [];
        foreach ($sessions as $session) {
            $attendance = getSessionAttendance($pdo, $session['id']);
            if ($attendance) {
                $summary[] = $attendance;
            }
        }
        
        return $summary;
        
    } catch (Exception $e) {
        error_log("Error getting daily attendance summary: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total attendance across all sessions for a specific date
 * @param PDO $pdo Database connection  
 * @param string $date Date (YYYY-MM-DD format)
 * @return array Summary statistics
 */
function getTotalDailyAttendance($pdo, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $summary = getDailyAttendanceSummary($pdo, $date);
    
    $totals = [
        'total_sessions' => count($summary),
        'total_members_present' => 0,
        'total_visitors' => 0,
        'total_attendance' => 0,
        'average_attendance_per_session' => 0
    ];
    
    foreach ($summary as $session) {
        $totals['total_members_present'] += $session['members_present'];
        $totals['total_visitors'] += $session['visitors'];
        $totals['total_attendance'] += $session['total_attendance'];
    }
    
    $totals['average_attendance_per_session'] = $totals['total_sessions'] > 0 
        ? round($totals['total_attendance'] / $totals['total_sessions'], 1)
        : 0;
    
    return $totals;
}

/**
 * Get complete list of attendees for a specific service session
 * @param PDO $pdo Database connection
 * @param int $session_id Session ID
 * @return array List of all attendees (members and visitors)
 */
function getSessionAttendeeList($pdo, $session_id) {
    try {
        // Get session details first
        $session_sql = "SELECT ss.*, s.name as service_name, s.id as service_id
                       FROM service_sessions ss
                       JOIN services s ON ss.service_id = s.id
                       WHERE ss.id = ?";
        $session_stmt = $pdo->prepare($session_sql);
        $session_stmt->execute([$session_id]);
        $session = $session_stmt->fetch();
        
        if (!$session) {
            return null;
        }
        
        $attendees = [];
        
        // Get member attendees
        $member_sql = "SELECT 
                          'MEMBER' as attendee_type,
                          m.id,
                          m.name,
                          m.phone as phone,
                          m.email,
                          m.department_id,
                          m.congregation_group,
                          a.status,
                          a.method,
                          a.date as attendance_date
                       FROM attendance a 
                       JOIN members m ON a.member_id = m.id 
                       WHERE a.session_id = ? 
                       ORDER BY m.name";
        
        $member_stmt = $pdo->prepare($member_sql);
        $member_stmt->execute([$session_id]);
        $members = $member_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get visitor attendees
        $visitor_sql = "SELECT 
                           'VISITOR' as attendee_type,
                           v.id,
                           v.name,
                           v.phone,
                           v.email,
                           v.gender,
                           v.age_group,
                           'present' as status,
                           'visitor_checkin' as method,
                           v.date as attendance_date
                        FROM visitors v 
                        JOIN service_sessions ss ON v.service_id = ss.service_id AND v.date = ss.session_date 
                        WHERE ss.id = ?
                        ORDER BY v.name";
        
        $visitor_stmt = $pdo->prepare($visitor_sql);
        $visitor_stmt->execute([$session_id]);
        $visitors = $visitor_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine and return results
        $attendees = array_merge($members, $visitors);
        
        return [
            'session' => $session,
            'attendees' => $attendees,
            'summary' => [
                'total_members' => count($members),
                'total_visitors' => count($visitors),
                'total_attendees' => count($attendees),
                'members_present' => count(array_filter($members, function($m) { return $m['status'] === 'present'; })),
                'members_absent' => count(array_filter($members, function($m) { return $m['status'] === 'absent'; })),
                'members_late' => count(array_filter($members, function($m) { return $m['status'] === 'late'; }))
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error getting session attendee list: " . $e->getMessage());
        return null;
    }
}

/**
 * Get attendee list for a service across all sessions on a specific date
 * @param PDO $pdo Database connection
 * @param int $service_id Service ID
 * @param string $date Date (YYYY-MM-DD format)
 * @return array Combined attendee data for all sessions of the service on that date
 */
function getServiceAttendeeList($pdo, $service_id, $date) {
    try {
        // Get all sessions for this service on the specified date
        $sessions_sql = "SELECT id FROM service_sessions WHERE service_id = ? AND session_date = ? ORDER BY opened_at";
        $sessions_stmt = $pdo->prepare($sessions_sql);
        $sessions_stmt->execute([$service_id, $date]);
        $sessions = $sessions_stmt->fetchAll();
        
        $all_attendees = [];
        $all_summaries = [];
        
        foreach ($sessions as $session) {
            $session_data = getSessionAttendeeList($pdo, $session['id']);
            if ($session_data) {
                $all_attendees = array_merge($all_attendees, $session_data['attendees']);
                $all_summaries[] = $session_data['summary'];
            }
        }
        
        // Remove duplicate attendees (same person attending multiple sessions)
        $unique_attendees = [];
        $seen = [];
        
        foreach ($all_attendees as $attendee) {
            $key = $attendee['attendee_type'] . '_' . $attendee['id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique_attendees[] = $attendee;
            }
        }
        
        // Calculate combined summary
        $combined_summary = [
            'total_sessions' => count($sessions),
            'unique_members' => count(array_filter($unique_attendees, function($a) { return $a['attendee_type'] === 'MEMBER'; })),
            'unique_visitors' => count(array_filter($unique_attendees, function($a) { return $a['attendee_type'] === 'VISITOR'; })),
            'total_unique_attendees' => count($unique_attendees)
        ];
        
        return [
            'service_id' => $service_id,
            'date' => $date,
            'attendees' => $unique_attendees,
            'summary' => $combined_summary,
            'session_summaries' => $all_summaries
        ];
        
    } catch (Exception $e) {
        error_log("Error getting service attendee list: " . $e->getMessage());
        return null;
    }
}
?>