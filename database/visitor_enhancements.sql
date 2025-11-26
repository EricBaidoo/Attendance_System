-- Visitor Management Enhancement
-- Date: November 23, 2025

USE attendance_system;

-- Enhance visitors table with additional fields
ALTER TABLE visitors 
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
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER notes,
ADD FOREIGN KEY (invited_by) REFERENCES members(id) ON DELETE SET NULL;

-- Create visitor follow-up tracking table
CREATE TABLE visitor_followups (
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
);

-- Create visitor attendance tracking (separate from members)
CREATE TABLE visitor_attendance (
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
);

-- Insert sample visitor data
INSERT INTO visitors (name, phone, email, address, gender, age_group, how_heard, first_time, invited_by, service_id, date) VALUES
('Jennifer Parker', '08098765432', 'jennifer@example.com', '123 Visitor St', 'female', 'adult', 'Friend invitation', 'yes', 1, 1, '2025-11-17'),
('Michael Thompson', '08087654321', 'michael@example.com', '456 Guest Ave', 'male', 'adult', 'Social media', 'yes', NULL, 1, '2025-11-17'),
('Sarah Wilson', '08076543210', 'sarah@example.com', '789 Newcomer Rd', 'female', 'youth', 'Family member', 'no', 2, 2, '2025-11-20'),
('David Chen', '08065432109', 'david@example.com', '321 Explorer Dr', 'male', 'adult', 'Website', 'yes', NULL, 1, '2025-11-23'),
('Emily Davis', '08054321098', 'emily@example.com', '654 Seeker Ln', 'female', 'adult', 'Drove by church', 'yes', 3, 1, '2025-11-23');

-- Insert visitor attendance records
INSERT INTO visitor_attendance (visitor_id, service_id, visit_date, visit_number) VALUES
(1, 1, '2025-11-17', 1),
(2, 1, '2025-11-17', 1),
(3, 2, '2025-11-20', 1),
(3, 1, '2025-11-24', 2),
(4, 1, '2025-11-23', 1),
(5, 1, '2025-11-23', 1);

-- Insert follow-up tasks for visitors
INSERT INTO visitor_followups (visitor_id, follow_up_date, follow_up_type, assigned_to, status, notes) VALUES
(1, '2025-11-25', 'call', 1, 'scheduled', 'Welcome call to Jennifer, invited by John Doe'),
(2, '2025-11-26', 'email', 1, 'scheduled', 'Send welcome email with church information'),
(3, '2025-11-27', 'visit', 1, 'scheduled', 'Home visit - second time visitor, seems interested'),
(4, '2025-11-28', 'call', 1, 'scheduled', 'Phone call to David, found us through website'),
(5, '2025-11-29', 'email', 1, 'scheduled', 'Follow up with Emily, first time visitor');

-- Create view for comprehensive visitor information
CREATE VIEW visitor_details AS
SELECT 
    v.id,
    v.name,
    v.phone,
    v.email,
    v.address,
    v.gender,
    v.age_group,
    v.how_heard,
    v.first_time,
    inviter.name AS invited_by_name,
    s.name AS service_name,
    v.date AS visit_date,
    v.follow_up_needed,
    v.follow_up_date,
    v.follow_up_completed,
    v.became_member,
    v.notes,
    COUNT(va.id) AS total_visits,
    MAX(va.visit_date) AS last_visit,
    (SELECT COUNT(*) FROM visitor_followups vf WHERE vf.visitor_id = v.id AND vf.status = 'scheduled') AS pending_followups
FROM visitors v
LEFT JOIN members inviter ON v.invited_by = inviter.id
LEFT JOIN services s ON v.service_id = s.id
LEFT JOIN visitor_attendance va ON v.id = va.visitor_id
GROUP BY v.id;

-- Create view for visitor statistics
CREATE VIEW visitor_stats AS
SELECT 
    COUNT(*) AS total_visitors,
    COUNT(CASE WHEN first_time = 'yes' THEN 1 END) AS first_time_visitors,
    COUNT(CASE WHEN first_time = 'no' THEN 1 END) AS return_visitors,
    COUNT(CASE WHEN follow_up_needed = 'yes' AND follow_up_completed = 'no' THEN 1 END) AS pending_followups,
    COUNT(CASE WHEN became_member = 'yes' THEN 1 END) AS became_members,
    COUNT(CASE WHEN gender = 'male' THEN 1 END) AS male_visitors,
    COUNT(CASE WHEN gender = 'female' THEN 1 END) AS female_visitors,
    COUNT(CASE WHEN age_group = 'child' THEN 1 END) AS child_visitors,
    COUNT(CASE WHEN age_group = 'youth' THEN 1 END) AS youth_visitors,
    COUNT(CASE WHEN age_group = 'adult' THEN 1 END) AS adult_visitors,
    COUNT(CASE WHEN age_group = 'senior' THEN 1 END) AS senior_visitors,
    COUNT(CASE WHEN date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS) THEN 1 END) AS recent_visitors
FROM visitors;