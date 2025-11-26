-- Database Updates for Church Attendance System
-- Date: November 23, 2025

USE attendance_system;

-- Add new columns to members table for better organization
ALTER TABLE members 
ADD COLUMN location VARCHAR(150) AFTER address,
ADD COLUMN occupation VARCHAR(100) AFTER phone,
ADD COLUMN emergency_contact VARCHAR(100) AFTER email,
ADD COLUMN emergency_phone VARCHAR(20) AFTER emergency_contact,
ADD COLUMN baptized ENUM('yes', 'no') DEFAULT 'no' AFTER date_joined,
ADD COLUMN baptism_date DATE NULL AFTER baptized,
ADD COLUMN member_type ENUM('full', 'associate', 'visitor', 'inactive') DEFAULT 'full' AFTER status,
ADD COLUMN profile_photo VARCHAR(255) NULL AFTER member_type,
ADD COLUMN notes TEXT NULL AFTER profile_photo;

-- Add indexes for better performance
CREATE INDEX idx_members_department ON members(department_id);
CREATE INDEX idx_members_status ON members(status);
CREATE INDEX idx_members_date_joined ON members(date_joined);
CREATE INDEX idx_attendance_date ON attendance(date);
CREATE INDEX idx_attendance_member ON attendance(member_id);
CREATE INDEX idx_attendance_service ON attendance(service_id);

-- Update departments table to include more information
ALTER TABLE departments 
ADD COLUMN leader_member_id INT NULL AFTER description,
ADD COLUMN meeting_day ENUM('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NULL AFTER leader_member_id,
ADD COLUMN meeting_time TIME NULL AFTER meeting_day,
ADD COLUMN budget DECIMAL(10,2) NULL DEFAULT 0.00 AFTER meeting_time,
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER budget,
ADD FOREIGN KEY (leader_member_id) REFERENCES members(id) ON DELETE SET NULL;

-- Create a new table for member families/households
CREATE TABLE families (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_name VARCHAR(100) NOT NULL,
    head_of_family INT,
    address VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (head_of_family) REFERENCES members(id)
);

-- Add family_id to members table
ALTER TABLE members ADD COLUMN family_id INT NULL AFTER department_id,
ADD FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE SET NULL;

-- Create table for member skills/talents
CREATE TABLE member_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    skill_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
    available ENUM('yes', 'no') DEFAULT 'yes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_skill (member_id, skill_name)
);

-- Create table for follow-up actions
CREATE TABLE follow_ups (
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
);

-- Create table for member positions/roles
CREATE TABLE member_positions (
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
);

-- Update services table with more fields
ALTER TABLE services 
ADD COLUMN expected_attendance INT DEFAULT 0 AFTER type,
ADD COLUMN actual_attendance INT DEFAULT 0 AFTER expected_attendance,
ADD COLUMN service_leader INT NULL AFTER actual_attendance,
ADD COLUMN notes TEXT NULL AFTER service_leader,
ADD FOREIGN KEY (service_leader) REFERENCES members(id) ON DELETE SET NULL;

-- Create table for service planning
CREATE TABLE service_planning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    activity VARCHAR(100) NOT NULL,
    assigned_member INT,
    assigned_department INT,
    duration_minutes INT DEFAULT 0,
    notes TEXT,
    order_sequence INT DEFAULT 1,
    status ENUM('planned', 'confirmed', 'completed', 'cancelled') DEFAULT 'planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_member) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_department) REFERENCES departments(id) ON DELETE SET NULL
);

-- Create table for church events
CREATE TABLE events (
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
);

-- Create table for event registrations
CREATE TABLE event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    member_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'no_show', 'cancelled') DEFAULT 'registered',
    notes TEXT,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_member (event_id, member_id)
);

-- Create table for donations/offerings
CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT,
    service_id INT,
    amount DECIMAL(10,2) NOT NULL,
    donation_type ENUM('tithe', 'offering', 'special_offering', 'pledge', 'other') NOT NULL,
    method ENUM('cash', 'check', 'card', 'transfer', 'online') DEFAULT 'cash',
    reference_number VARCHAR(50),
    date DATE NOT NULL,
    notes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Update users table with more fields
ALTER TABLE users 
ADD COLUMN full_name VARCHAR(100) AFTER username,
ADD COLUMN department_id INT NULL AFTER phone,
ADD COLUMN last_login TIMESTAMP NULL AFTER department_id,
ADD COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active' AFTER last_login,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status,
ADD FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- Create table for system settings
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    category ENUM('general', 'attendance', 'notifications', 'security', 'display') DEFAULT 'general',
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description, category) VALUES
('church_name', 'Bridge Ministries International', 'Official church name', 'general'),
('church_address', '', 'Church physical address', 'general'),
('church_phone', '', 'Church contact phone number', 'general'),
('church_email', '', 'Church contact email', 'general'),
('attendance_auto_mark', 'no', 'Automatically mark attendance for services', 'attendance'),
('notification_email', 'yes', 'Enable email notifications', 'notifications'),
('notification_sms', 'no', 'Enable SMS notifications', 'notifications'),
('session_timeout', '3600', 'Session timeout in seconds', 'security'),
('members_per_page', '25', 'Number of members to display per page', 'display'),
('date_format', 'Y-m-d', 'Default date format', 'display');

-- Insert additional demo data for enhanced functionality
INSERT INTO families (family_name, head_of_family, address, phone, email) VALUES
('Doe Family', 1, '123 Main St', '08012345678', 'doe.family@example.com'),
('Smith Family', 2, '456 Elm St', '08023456789', 'smith.family@example.com');

-- Update existing members with family relationships
UPDATE members SET family_id = 1 WHERE id = 1;
UPDATE members SET family_id = 2 WHERE id = 2;

-- Insert member skills
INSERT INTO member_skills (member_id, skill_name, skill_level) VALUES
(1, 'Music - Piano', 'advanced'),
(1, 'Public Speaking', 'intermediate'),
(2, 'Organization', 'expert'),
(2, 'Event Planning', 'advanced'),
(3, 'Youth Ministry', 'intermediate'),
(3, 'Sports Coordination', 'advanced');

-- Insert member positions
INSERT INTO member_positions (member_id, position_name, department_id, start_date, status) VALUES
(1, 'Choir Leader', 1, '2023-01-10', 'active'),
(2, 'Head Usher', 2, '2023-02-15', 'active'),
(3, 'Youth Coordinator', 3, '2023-03-20', 'active');

-- Update departments with additional info
UPDATE departments SET 
    leader_member_id = 1, 
    meeting_day = 'Wednesday', 
    meeting_time = '18:00:00',
    status = 'active'
WHERE id = 1;

UPDATE departments SET 
    leader_member_id = 2, 
    meeting_day = 'Sunday', 
    meeting_time = '08:00:00',
    status = 'active'
WHERE id = 2;

UPDATE departments SET 
    leader_member_id = 3, 
    meeting_day = 'Saturday', 
    meeting_time = '15:00:00',
    status = 'active'
WHERE id = 3;

-- Add more demo members with enhanced data
INSERT INTO members (name, dob, gender, address, location, phone, occupation, email, emergency_contact, emergency_phone, department_id, date_joined, baptized, member_type, status) VALUES
('Mary Johnson', '1992-03-15', 'female', '321 Pine St', 'Cityville', '08045678901', 'Teacher', 'mary@example.com', 'James Johnson', '08056789012', 1, '2023-04-10', 'yes', 'full', 'active'),
('Peter Williams', '1988-07-08', 'male', '654 Cedar Ave', 'Townsburg', '08067890123', 'Engineer', 'peter@example.com', 'Sarah Williams', '08078901234', 2, '2023-05-15', 'yes', 'full', 'active'),
('Grace Brown', '1995-12-20', 'female', '987 Maple Dr', 'Villageton', '08089012345', 'Nurse', 'grace@example.com', 'Michael Brown', '08090123456', 3, '2023-06-20', 'no', 'associate', 'active');

-- Insert some follow-up tasks
INSERT INTO follow_ups (member_id, assigned_to, title, description, priority, due_date) VALUES
(4, 1, 'New Member Orientation', 'Schedule orientation session for Mary Johnson', 'high', '2025-11-30'),
(5, 1, 'Department Integration', 'Help Peter Williams integrate into Ushers department', 'medium', '2025-12-05'),
(6, 1, 'Baptism Counseling', 'Provide baptism counseling for Grace Brown', 'high', '2025-12-10');

-- Insert upcoming events
INSERT INTO events (name, description, event_date, start_time, end_time, location, organizer_id, department_id, registration_required, max_attendees) VALUES
('Christmas Carol Service', 'Annual Christmas celebration with carols and special performances', '2025-12-25', '18:00:00', '20:00:00', 'Main Auditorium', 1, 1, 'yes', 200),
('Youth Camp 2025', 'Weekend retreat for youth members', '2025-12-28', '09:00:00', '17:00:00', 'Camp Grounds', 3, 3, 'yes', 50),
('New Year Prayer Service', 'Special prayer service to welcome the new year', '2025-12-31', '23:00:00', '01:00:00', 'Main Auditorium', NULL, NULL, 'no', NULL);

-- Create view for comprehensive member information
CREATE VIEW member_details AS
SELECT 
    m.id,
    m.name,
    m.dob,
    m.gender,
    m.address,
    m.location,
    m.phone,
    m.occupation,
    m.email,
    m.emergency_contact,
    m.emergency_phone,
    d.name AS department_name,
    f.family_name,
    m.date_joined,
    m.baptized,
    m.baptism_date,
    m.member_type,
    m.status,
    m.profile_photo,
    m.notes,
    COUNT(DISTINCT a.id) AS attendance_count,
    MAX(a.date) AS last_attendance
FROM members m
LEFT JOIN departments d ON m.department_id = d.id
LEFT JOIN families f ON m.family_id = f.id
LEFT JOIN attendance a ON m.id = a.member_id
GROUP BY m.id;

-- Create view for department statistics
CREATE VIEW department_stats AS
SELECT 
    d.id,
    d.name,
    d.description,
    d.status,
    leader.name AS leader_name,
    COUNT(m.id) AS total_members,
    COUNT(CASE WHEN m.status = 'active' THEN 1 END) AS active_members,
    COUNT(CASE WHEN m.gender = 'male' THEN 1 END) AS male_members,
    COUNT(CASE WHEN m.gender = 'female' THEN 1 END) AS female_members,
    d.budget,
    d.meeting_day,
    d.meeting_time
FROM departments d
LEFT JOIN members leader ON d.leader_member_id = leader.id
LEFT JOIN members m ON d.id = m.department_id
GROUP BY d.id;