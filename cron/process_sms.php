<?php
/**
 * Background SMS Worker
 * Processes queued SMS messages without browser interaction.
 * Run this via Cron (Linux) or Task Scheduler (Windows).
 */

// CLI setup
if (php_sapi_name() !== 'cli' && !isset($_GET['force_run'])) {
    die('This script must be run from the command line.');
}

require_once __DIR__ . '/../includes/settings_utils.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/communication_utils.php';

// Prevent concurrent runs
$lock_file = __DIR__ . '/sms_worker.lock';
if (file_exists($lock_file) && (time() - filemtime($lock_file)) < 300) {
    // If lock is older than 5 mins, assume it crashed and continue
    echo "Worker already running.\n";
    exit;
}
file_put_contents($lock_file, getmypid());

try {
    $sms_config = require __DIR__ . '/../config/sms_config.php';
    
    // 1. Process Individual Queued SMS (e.g. Tithe Alerts, Single Messages)
    $direct_res = processDirectSmsQueue($pdo, $sms_config, 30);
    if ($direct_res['processed'] > 0) {
        echo "Processed {$direct_res['processed']} individual transaction alerts.\n";
    }

    // 2. Find active campaigns that still have queued items
    // Priority: 'sending' status first, then 'scheduled'
    $stmt = $pdo->prepare("
        SELECT id FROM communication_campaigns 
        WHERE channel = 'sms' 
        AND status IN ('sending', 'scheduled')
        ORDER BY FIELD(status, 'sending', 'scheduled'), id ASC 
        LIMIT 5
    ");
    $stmt->execute();
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($campaigns)) {
        echo "No active SMS campaigns found.\n";
    }

    foreach ($campaigns as $camp) {
        $campaign_id = (int)$camp['id'];
        echo "Processing Campaign #{$campaign_id}...\n";
        
        $result = processSmsBatchJob($pdo, $sms_config, $campaign_id);
        
        if ($result['ok']) {
            echo "Processed batch. Delivered: {$result['delivered']}, Failed: {$result['failed']}, Progress: {$result['percent']}%\n";
            if ($result['done']) {
                echo "Campaign #{$campaign_id} completed.\n";
            }
        } else {
            echo "Error processing #{$campaign_id}: " . ($result['error'] ?? 'Unknown error') . "\n";
        }
    }

} catch (Exception $e) {
    echo "Worker Fatal Error: " . $e->getMessage() . "\n";
} finally {
    if (file_exists($lock_file)) {
        unlink($lock_file);
    }
    file_put_contents(__DIR__ . '/last_run.txt', time());
}
