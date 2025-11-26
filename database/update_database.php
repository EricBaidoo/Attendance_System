<?php
// Database update script for Church Attendance System
// Execute this script to apply database enhancements

require_once '../config/database.php';

echo "=== Church Attendance System Database Updates ===\n";
echo "Starting database modification process...\n\n";

try {
    // Start transaction
    $pdo->beginTransaction();

    echo "1. Adding new columns to members table...\n";
    
    // Check if columns already exist before adding
    $result = $pdo->query("SHOW COLUMNS FROM members LIKE 'location'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE members 
                    ADD COLUMN location VARCHAR(150) AFTER address,
                    ADD COLUMN occupation VARCHAR(100) AFTER phone,
                    ADD COLUMN emergency_contact VARCHAR(100) AFTER email,
                    ADD COLUMN emergency_phone VARCHAR(20) AFTER emergency_contact,
                    ADD COLUMN baptized ENUM('yes', 'no') DEFAULT 'no' AFTER date_joined,
                    ADD COLUMN baptism_date DATE NULL AFTER baptized,
                    ADD COLUMN member_type ENUM('full', 'associate', 'visitor', 'inactive') DEFAULT 'full' AFTER status,
                    ADD COLUMN profile_photo VARCHAR(255) NULL AFTER member_type,
                    ADD COLUMN notes TEXT NULL AFTER profile_photo");
        echo "   ✓ New member columns added successfully\n";
    } else {
        echo "   ℹ Member columns already exist, skipping...\n";
    }

    echo "2. Creating database indexes for performance...\n";
    
    // Add indexes with error handling
    $indexes = [
        "CREATE INDEX idx_members_department ON members(department_id)",
        "CREATE INDEX idx_members_status ON members(status)",
        "CREATE INDEX idx_members_date_joined ON members(date_joined)",
        "CREATE INDEX idx_attendance_date ON attendance(date)",
        "CREATE INDEX idx_attendance_member ON attendance(member_id)",
        "CREATE INDEX idx_attendance_service ON attendance(service_id)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $pdo->exec($index);
            echo "   ✓ Index created\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                throw $e;
            }
            echo "   ℹ Index already exists, skipping...\n";
        }
    }

    echo "3. Updating departments table...\n";
    
    $result = $pdo->query("SHOW COLUMNS FROM departments LIKE 'leader_member_id'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE departments 
                    ADD COLUMN leader_member_id INT NULL AFTER description,
                    ADD COLUMN meeting_day ENUM('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NULL AFTER leader_member_id,
                    ADD COLUMN meeting_time TIME NULL AFTER meeting_day,
                    ADD COLUMN budget DECIMAL(10,2) NULL DEFAULT 0.00 AFTER meeting_time,
                    ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER budget");
        echo "   ✓ Department columns added successfully\n";
    } else {
        echo "   ℹ Department columns already exist, skipping...\n";
    }

    echo "4. Creating families table...\n";
    
    $result = $pdo->query("SHOW TABLES LIKE 'families'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE families (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        family_name VARCHAR(100) NOT NULL,
                        head_of_family INT,
                        address VARCHAR(255),
                        phone VARCHAR(20),
                        email VARCHAR(100),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (head_of_family) REFERENCES members(id)
                    )");
        echo "   ✓ Families table created successfully\n";
    } else {
        echo "   ℹ Families table already exists, skipping...\n";
    }

    echo "5. Creating member_skills table...\n";
    
    $result = $pdo->query("SHOW TABLES LIKE 'member_skills'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE member_skills (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        member_id INT NOT NULL,
                        skill_name VARCHAR(100) NOT NULL,
                        skill_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
                        available ENUM('yes', 'no') DEFAULT 'yes',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
                        UNIQUE KEY unique_member_skill (member_id, skill_name)
                    )");
        echo "   ✓ Member skills table created successfully\n";
    } else {
        echo "   ℹ Member skills table already exists, skipping...\n";
    }

    echo "6. Creating follow_ups table...\n";
    
    $result = $pdo->query("SHOW TABLES LIKE 'follow_ups'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE follow_ups (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        member_id INT NOT NULL,
                        assigned_to INT NOT NULL,
                        title VARCHAR(150) NOT NULL,
                        description TEXT,
                        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
                        due_date DATE,
                        completed_at DATETIME NULL,
                        notes TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
                        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE
                    )");
        echo "   ✓ Follow-ups table created successfully\n";
    } else {
        echo "   ℹ Follow-ups table already exists, skipping...\n";
    }

    echo "7. Creating member_positions table...\n";
    
    $result = $pdo->query("SHOW TABLES LIKE 'member_positions'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE member_positions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        member_id INT NOT NULL,
                        position_name VARCHAR(100) NOT NULL,
                        department_id INT,
                        start_date DATE NOT NULL,
                        end_date DATE NULL,
                        status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
                        description TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
                        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
                    )");
        echo "   ✓ Member positions table created successfully\n";
    } else {
        echo "   ℹ Member positions table already exists, skipping...\n";
    }

    echo "8. Creating events table...\n";
    
    $result = $pdo->query("SHOW TABLES LIKE 'events'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE events (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(150) NOT NULL,
                        description TEXT,
                        event_date DATE NOT NULL,
                        start_time TIME NOT NULL,
                        end_time TIME NULL,
                        location VARCHAR(150),
                        organizer_id INT,
                        department_id INT,
                        max_attendees INT NULL,
                        registration_required ENUM('yes', 'no') DEFAULT 'no',
                        registration_deadline DATE NULL,
                        cost DECIMAL(10,2) DEFAULT 0.00,
                        status ENUM('planning', 'open', 'closed', 'cancelled', 'completed') DEFAULT 'planning',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (organizer_id) REFERENCES members(id) ON DELETE SET NULL,
                        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
                    )");
        echo "   ✓ Events table created successfully\n";
    } else {
        echo "   ℹ Events table already exists, skipping...\n";
    }

    echo "9. Creating system_settings table...\n";
    
    $result = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE system_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        setting_key VARCHAR(100) NOT NULL UNIQUE,
                        setting_value TEXT,
                        description TEXT,
                        category ENUM('general', 'attendance', 'notifications', 'security', 'display') DEFAULT 'general',
                        updated_by INT,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
                    )");
        echo "   ✓ System settings table created successfully\n";
        
        // Insert default settings
        $pdo->exec("INSERT INTO system_settings (setting_key, setting_value, description, category) VALUES
                    ('church_name', 'Bridge Ministries International', 'Official church name', 'general'),
                    ('church_address', '', 'Church physical address', 'general'),
                    ('church_phone', '', 'Church contact phone number', 'general'),
                    ('church_email', '', 'Church contact email', 'general'),
                    ('attendance_auto_mark', 'no', 'Automatically mark attendance for services', 'attendance'),
                    ('notification_email', 'yes', 'Enable email notifications', 'notifications'),
                    ('session_timeout', '3600', 'Session timeout in seconds', 'security'),
                    ('members_per_page', '25', 'Number of members to display per page', 'display')");
        echo "   ✓ Default system settings inserted\n";
    } else {
        echo "   ℹ System settings table already exists, skipping...\n";
    }

    echo "10. Adding sample enhanced data...\n";
    
    // Check if enhanced data already exists
    $result = $pdo->query("SELECT COUNT(*) as count FROM members WHERE location IS NOT NULL");
    $row = $result->fetch();
    
    if ($row['count'] == 0) {
        // Add sample members with enhanced data
        $pdo->exec("INSERT INTO members (name, dob, gender, address, location, phone, occupation, email, emergency_contact, emergency_phone, department_id, date_joined, baptized, member_type, status) VALUES
                    ('Mary Johnson', '1992-03-15', 'female', '321 Pine St', 'Cityville', '08045678901', 'Teacher', 'mary@example.com', 'James Johnson', '08056789012', 1, '2023-04-10', 'yes', 'full', 'active'),
                    ('Peter Williams', '1988-07-08', 'male', '654 Cedar Ave', 'Townsburg', '08067890123', 'Engineer', 'peter@example.com', 'Sarah Williams', '08078901234', 2, '2023-05-15', 'yes', 'full', 'active'),
                    ('Grace Brown', '1995-12-20', 'female', '987 Maple Dr', 'Villageton', '08089012345', 'Nurse', 'grace@example.com', 'Michael Brown', '08090123456', 3, '2023-06-20', 'no', 'associate', 'active')");
        echo "   ✓ Enhanced member data added\n";
        
        // Add sample families
        $pdo->exec("INSERT INTO families (family_name, head_of_family, address, phone, email) VALUES
                    ('Johnson Family', 4, '321 Pine St', '08045678901', 'johnson.family@example.com'),
                    ('Williams Family', 5, '654 Cedar Ave', '08067890123', 'williams.family@example.com')");
        echo "   ✓ Sample families added\n";
        
        // Add sample skills
        $pdo->exec("INSERT INTO member_skills (member_id, skill_name, skill_level) VALUES
                    (1, 'Music - Piano', 'advanced'),
                    (1, 'Public Speaking', 'intermediate'),
                    (2, 'Organization', 'expert'),
                    (4, 'Teaching', 'expert'),
                    (5, 'Technical Support', 'advanced')");
        echo "   ✓ Sample member skills added\n";
    } else {
        echo "   ℹ Enhanced data already exists, skipping...\n";
    }

    // Commit all changes
    $pdo->commit();
    
    echo "\n=== Database Update Completed Successfully! ===\n";
    echo "✓ All tables updated and enhanced\n";
    echo "✓ New features and functionality added\n";
    echo "✓ Sample data inserted\n";
    echo "✓ Database is ready for enhanced church management\n\n";
    
    // Display final table count
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    echo "Total tables in database: " . count($tables) . "\n";
    echo "Tables: " . implode(", ", $tables) . "\n";

} catch (PDOException $e) {
    // Rollback on error
    $pdo->rollback();
    echo "\n❌ Error during database update: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back.\n";
} catch (Exception $e) {
    $pdo->rollback();
    echo "\n❌ Unexpected error: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back.\n";
}
?>