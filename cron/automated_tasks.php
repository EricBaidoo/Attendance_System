<?php
/**
 * Bridge Ministries - Central Automation Hub (v2 - Dynamic Templates)
 * ==============================================================
 * This script handles all automated daily tasks:
 * 1. Member Birthday Greetings
 * 2. Visitor Welcome Follow-ups
 * 3. New Member Official Welcome
 * 4. Welfare Follow-up
 * 
 * All messages are now fetched dynamically from System Settings.
 * Templates support the [FIRST_NAME] placeholder.
 * 
 * Best practice: Run this script via Cron Job once daily (e.g., at 07:00 AM).
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/communication_utils.php';
require_once __DIR__ . '/../includes/settings_utils.php';

echo "============================================\n";
echo "BRIDGE MINISTRIES AUTOMATION HUB STARTING\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "============================================\n";

try {
    // 0. Fetch Message Templates
    $tpl_birthday = getSystemSetting($pdo, 'sms_template_birthday', 'Dear [FIRST_NAME], happy birthday! God bless you.');
    $tpl_visitor  = getSystemSetting($pdo, 'sms_template_visitor_welcome', 'Dear [FIRST_NAME], thanks for visiting Bridge Ministries.');
    $tpl_member   = getSystemSetting($pdo, 'sms_template_member_welcome', 'Welcome to the Family, [FIRST_NAME]!');
    $tpl_welfare  = getSystemSetting($pdo, 'sms_template_welfare_followup', 'Dear [FIRST_NAME], we missed you. Praying for you.');

    /**
     * TASK 1: BIRTHDAY GREETINGS
     * Scan for members celebrating their birthday today.
     */
    echo "\n[TASK] Processing Birthdays...\n";
    $bday_stmt = $pdo->prepare("
        SELECT m.id, p.full_name, p.phone, p.dob 
        FROM member_roles m
        JOIN people p ON p.id = m.person_id
        WHERE m.status = 'active'
        AND DATE_FORMAT(p.dob, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
        AND p.phone IS NOT NULL AND p.phone <> ''
        AND m.id NOT IN (
            SELECT recipient FROM communication_logs 
            WHERE message_preview LIKE '%birthday%' 
            AND DATE(created_at) = CURDATE()
        )
    ");
    $bday_stmt->execute();
    $celebrants = $bday_stmt->fetchAll();

    foreach ($celebrants as $person) {
        $first_name = explode(' ', $person['full_name'])[0];
        $msg = str_replace('[FIRST_NAME]', $first_name, $tpl_birthday);
        
        $log_id = queueDirectSms($pdo, $person['phone'], $msg, $person['full_name'], 'sms');
        if ($log_id) {
            echo " - Birthday queued for {$person['full_name']}\n";
        }
    }

    /**
     * TASK 2: VISITOR FOLLOW-UP
     * Scan visitors added in the last 48 hours who haven't received a follow-up yet.
     */
    echo "\n[TASK] Processing Visitor Follow-ups...\n";
    $visitor_stmt = $pdo->prepare("
        SELECT vr.id, p.full_name, p.phone
        FROM visitor_roles vr
        JOIN people p ON p.id = vr.person_id
        WHERE vr.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND p.phone IS NOT NULL AND p.phone <> ''
        AND vr.id NOT IN (
            SELECT recipient FROM communication_logs 
            WHERE (message_preview LIKE '%visiting%' OR message_preview LIKE '%honored%')
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        )
        LIMIT 20
    ");
    $visitor_stmt->execute();
    $visitors = $visitor_stmt->fetchAll();

    foreach ($visitors as $v) {
        $first_name = explode(' ', $v['full_name'])[0];
        $msg = str_replace('[FIRST_NAME]', $first_name, $tpl_visitor);
        
        $log_id = queueDirectSms($pdo, $v['phone'], $msg, $v['full_name'], 'sms');
        if ($log_id) {
            echo " - Visitor welcome queued for {$v['full_name']}\n";
        }
    }

    /**
     * TASK 3: NEW MEMBER WELCOME
     * Scan those promoted to members in the last 72 hours for a welcome message.
     */
    echo "\n[TASK] Processing New Member Welcomes...\n";
    $member_stmt = $pdo->prepare("
        SELECT m.id, p.full_name, p.phone
        FROM member_roles m
        JOIN people p ON p.id = m.person_id
        WHERE m.status = 'active'
        AND m.created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
        AND p.phone IS NOT NULL AND p.phone <> ''
        AND m.id NOT IN (
            SELECT recipient FROM communication_logs 
            WHERE message_preview LIKE '%Welcome to the Family%'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        )
        LIMIT 20
    ");
    $member_stmt->execute();
    $new_members = $member_stmt->fetchAll();

    foreach ($new_members as $nm) {
        $first_name = explode(' ', $nm['full_name'])[0];
        $msg = str_replace('[FIRST_NAME]', $first_name, $tpl_member);
        
        $log_id = queueDirectSms($pdo, $nm['phone'], $msg, $nm['full_name'], 'sms');
        if ($log_id) {
            echo " - Official welcome queued for {$nm['full_name']}\n";
        }
    }

    /**
     * TASK 4: WELFARE FOLLOW-UP (MISSING IN ACTION)
     * Scan members who haven't attended ANY service in the last 21 days.
     */
    echo "\n[TASK] Processing Welfare Follow-ups...\n";
    $welfare_stmt = $pdo->prepare("
        SELECT m.id, p.full_name, p.phone, MAX(ss.session_date) as last_seen
        FROM member_roles m
        JOIN people p ON p.id = m.person_id
        LEFT JOIN attendance a ON a.member_id = m.id
        LEFT JOIN service_sessions ss ON a.session_id = ss.id
        WHERE m.status = 'active'
        AND p.phone IS NOT NULL AND p.phone <> ''
        AND m.id NOT IN (
            SELECT recipient FROM communication_logs 
            WHERE message_preview LIKE '%missed you%' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        )
        GROUP BY m.id, p.full_name, p.phone
        HAVING last_seen < DATE_SUB(NOW(), INTERVAL 21 DAY)
        OR last_seen IS NULL
        LIMIT 20
    ");
    $welfare_stmt->execute();
    $welfare_list = $welfare_stmt->fetchAll();

    foreach ($welfare_list as $missing) {
        $first_name = explode(' ', $missing['full_name'])[0];
        $msg = str_replace('[FIRST_NAME]', $first_name, $tpl_welfare);
        
        $log_id = queueDirectSms($pdo, $missing['phone'], $msg, $missing['full_name'], 'sms');
        if ($log_id) {
            echo " - Welfare message queued for {$missing['full_name']}\n";
        }
    }

    echo "\n============================================\n";
    echo "AUTOMATION COMPLETE: " . date('Y-m-d H:i:s') . "\n";
    echo "============================================\n";

} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    error_log("Cron Error: " . $e->getMessage());
}
