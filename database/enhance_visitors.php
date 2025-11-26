<?php
// Visitor Enhancement Script
require_once '../config/database.php';

echo "=== Visitor Management Enhancement ===\n";
echo "Starting visitor system upgrades...\n\n";

try {
    $pdo->beginTransaction();

    echo "1. Enhancing visitors table...\n";
    
    // Check if enhancements already exist
    $result = $pdo->query("SHOW COLUMNS FROM visitors LIKE 'address'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE visitors 
                    ADD COLUMN address VARCHAR(255) AFTER email,
                    ADD COLUMN gender ENUM('male', 'female', 'other') AFTER address,
                    ADD COLUMN age_group ENUM('child', 'youth', 'adult', 'senior') AFTER gender,
                    ADD COLUMN how_heard VARCHAR(150) AFTER age_group,
                    ADD COLUMN first_time ENUM('yes', 'no') DEFAULT 'yes' AFTER how_heard,
                    ADD COLUMN invited_by INT NULL AFTER first_time,
                    ADD COLUMN follow_up_needed ENUM('yes', 'no') DEFAULT 'yes' AFTER invited_by,
                    ADD COLUMN follow_up_date DATE NULL AFTER follow_up_needed,
                    ADD COLUMN follow_up_completed ENUM('yes', 'no') DEFAULT 'no' AFTER follow_up_date,
                    ADD COLUMN became_member ENUM('yes', 'no') DEFAULT 'no' AFTER follow_up_completed,
                    ADD COLUMN notes TEXT AFTER became_member,
                    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER notes");
        echo "   ✓ Visitor table enhanced successfully\n";
    } else {
        echo "   ℹ Visitor enhancements already exist, skipping...\n";
    }

    echo "2. Creating visitor follow-up tracking table...\n";
    
    $result = $pdo->query("SHOW TABLES LIKE 'visitor_followups'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE visitor_followups (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        visitor_id INT NOT NULL,
                        follow_up_date DATE NOT NULL,
                        follow_up_type ENUM('call', 'visit', 'email', 'text') NOT NULL,
                        assigned_to INT NOT NULL,
                        status ENUM('scheduled', 'completed', 'cancelled', 'no_response') DEFAULT 'scheduled',
                        notes TEXT,
                        completed_at DATETIME NULL,
                        next_follow_up DATE NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
                        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE
                    )");
        echo "   ✓ Visitor follow-ups table created\n";
    } else {
        echo "   ℹ Visitor follow-ups table already exists, skipping...\n";
    }

    echo "3. Creating visitor attendance tracking...\n";
    
    $result = $pdo->query("SHOW TABLES LIKE 'visitor_attendance'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE visitor_attendance (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        visitor_id INT NOT NULL,
                        service_id INT NOT NULL,
                        visit_date DATE NOT NULL,
                        visit_number INT DEFAULT 1,
                        brought_guests INT DEFAULT 0,
                        notes TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
                        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
                    )");
        echo "   ✓ Visitor attendance table created\n";
    } else {
        echo "   ℹ Visitor attendance table already exists, skipping...\n";
    }

    echo "4. Adding sample visitor data...\n";
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM visitors");
    $row = $result->fetch();
    
    if ($row['count'] == 0) {
        $pdo->exec("INSERT INTO visitors (name, phone, email, address, gender, age_group, how_heard, first_time, invited_by, service_id, date) VALUES
                    ('Jennifer Parker', '08098765432', 'jennifer@example.com', '123 Visitor St', 'female', 'adult', 'Friend invitation', 'yes', 1, 1, '2025-11-17'),
                    ('Michael Thompson', '08087654321', 'michael@example.com', '456 Guest Ave', 'male', 'adult', 'Social media', 'yes', NULL, 1, '2025-11-17'),
                    ('Sarah Wilson', '08076543210', 'sarah@example.com', '789 Newcomer Rd', 'female', 'youth', 'Family member', 'no', 2, 2, '2025-11-20'),
                    ('David Chen', '08065432109', 'david@example.com', '321 Explorer Dr', 'male', 'adult', 'Website', 'yes', NULL, 1, '2025-11-23'),
                    ('Emily Davis', '08054321098', 'emily@example.com', '654 Seeker Ln', 'female', 'adult', 'Drove by church', 'yes', 3, 1, '2025-11-23')");
        echo "   ✓ Sample visitor data added\n";
        
        $pdo->exec("INSERT INTO visitor_attendance (visitor_id, service_id, visit_date, visit_number) VALUES
                    (1, 1, '2025-11-17', 1),
                    (2, 1, '2025-11-17', 1),
                    (3, 2, '2025-11-20', 1),
                    (3, 1, '2025-11-24', 2),
                    (4, 1, '2025-11-23', 1),
                    (5, 1, '2025-11-23', 1)");
        echo "   ✓ Visitor attendance records added\n";
        
        $pdo->exec("INSERT INTO visitor_followups (visitor_id, follow_up_date, follow_up_type, assigned_to, status, notes) VALUES
                    (1, '2025-11-25', 'call', 1, 'scheduled', 'Welcome call to Jennifer, invited by John Doe'),
                    (2, '2025-11-26', 'email', 1, 'scheduled', 'Send welcome email with church information'),
                    (3, '2025-11-27', 'visit', 1, 'scheduled', 'Home visit - second time visitor, seems interested'),
                    (4, '2025-11-28', 'call', 1, 'scheduled', 'Phone call to David, found us through website'),
                    (5, '2025-11-29', 'email', 1, 'scheduled', 'Follow up with Emily, first time visitor')");
        echo "   ✓ Follow-up tasks created\n";
    } else {
        echo "   ℹ Visitor data already exists, skipping...\n";
    }

    $pdo->commit();
    
    echo "\n=== Visitor Management Enhancement Completed! ===\n";
    echo "✓ Visitor tracking enhanced with detailed information\n";
    echo "✓ Follow-up system created\n";
    echo "✓ Visitor attendance tracking added\n";
    echo "✓ Sample visitor data populated\n\n";
    
    // Show visitor statistics
    $result = $pdo->query("SELECT COUNT(*) AS total_visitors,
                           COUNT(CASE WHEN first_time = 'yes' THEN 1 END) AS first_time,
                           COUNT(CASE WHEN follow_up_needed = 'yes' THEN 1 END) AS need_followup
                           FROM visitors");
    $stats = $result->fetch();
    echo "Visitor Statistics:\n";
    echo "- Total Visitors: {$stats['total_visitors']}\n";
    echo "- First Time Visitors: {$stats['first_time']}\n";
    echo "- Need Follow-up: {$stats['need_followup']}\n";

} catch (PDOException $e) {
    $pdo->rollback();
    echo "\n❌ Error during enhancement: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    $pdo->rollback();
    echo "\n❌ Unexpected error: " . $e->getMessage() . "\n";
}
?>