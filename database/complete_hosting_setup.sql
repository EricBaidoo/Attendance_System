-- =============================================================================
-- BRIDGE MINISTRIES INTERNATIONAL - ATTENDANCE SYSTEM DATABASE SETUP
-- =============================================================================
-- This script creates the complete database structure for the church management system
-- Run this script on your hosting database to set up everything you need
-- =============================================================================

-- Create the database (if your hosting allows)
CREATE DATABASE IF NOT EXISTS attendance_system;
USE attendance_system;

-- =============================================================================
-- 1. USERS TABLE - System authentication
-- =============================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    email VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================================================
-- 2. DEPARTMENTS TABLE - Church departments/ministries
-- =============================================================================
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- 3. MEMBERS TABLE - Church members
-- =============================================================================
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    dob DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    department_id INT NULL,
    date_joined DATE NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    membership_category ENUM('member', 'visitor', 'new_convert', 'worker') DEFAULT 'member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- =============================================================================
-- 4. VISITORS TABLE - Church visitors and tracking
-- =============================================================================
CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    gender ENUM('male', 'female', 'other') NULL,
    age_group ENUM('child', 'youth', 'adult', 'senior') NULL,
    visit_date DATE NOT NULL,
    service_id INT NULL,
    invitation_source ENUM('member', 'social_media', 'website', 'event', 'walk_in', 'other') NULL,
    invited_by VARCHAR(100) NULL,
    follow_up_status ENUM('pending', 'contacted', 'converted', 'not_interested') DEFAULT 'pending',
    follow_up_date DATE NULL,
    follow_up_notes TEXT NULL,
    is_first_time BOOLEAN DEFAULT TRUE,
    is_converted BOOLEAN DEFAULT FALSE,
    converted_to_member_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (converted_to_member_id) REFERENCES members(id) ON DELETE SET NULL
);

-- =============================================================================
-- 5. SERVICES TABLE - Church services and events
-- =============================================================================
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    date DATE NOT NULL,
    time TIME NULL,
    location VARCHAR(100) NULL,
    type VARCHAR(50) NULL,
    status ENUM('scheduled', 'open', 'closed') DEFAULT 'scheduled',
    opened_at DATETIME NULL,
    closed_at DATETIME NULL,
    opened_by INT NULL,
    closed_by INT NULL,
    expected_attendance INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =============================================================================
-- 6. ATTENDANCE TABLE - Track member attendance
-- =============================================================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    service_id INT NOT NULL,
    checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_in_by INT NULL,
    notes TEXT NULL,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_in_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (member_id, service_id)
);

-- =============================================================================
-- 7. VISITOR_CHECKINS TABLE - Track visitor attendance
-- =============================================================================
CREATE TABLE IF NOT EXISTS visitor_checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT NOT NULL,
    service_id INT NOT NULL,
    checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_in_by INT NULL,
    notes TEXT NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_in_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_visitor_checkin (visitor_id, service_id)
);

-- =============================================================================
-- 8. INSERT DEFAULT DATA
-- =============================================================================

-- Insert default admin user (change password after setup)
INSERT IGNORE INTO users (username, password, role, email) VALUES 
('admin', 'admin123', 'admin', 'admin@bridgeministries.org');

-- Insert default departments
INSERT IGNORE INTO departments (name, description) VALUES 
('General', 'General membership'),
('Youth', 'Youth ministry'),
('Children', 'Children ministry'),
('Choir', 'Music and worship'),
('Ushering', 'Ushering ministry'),
('Technical', 'Sound and technical');

-- Insert default service types for testing
INSERT IGNORE INTO services (name, description, date, time, location, type) VALUES 
('Sunday Morning Service', 'Main worship service', CURDATE(), '09:00:00', 'Main Auditorium', 'worship'),
('Wednesday Bible Study', 'Midweek Bible study', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '19:00:00', 'Fellowship Hall', 'study');

-- =============================================================================
-- 9. CREATE INDEXES FOR PERFORMANCE
-- =============================================================================

-- Members table indexes
CREATE INDEX IF NOT EXISTS idx_members_name ON members(name);
CREATE INDEX IF NOT EXISTS idx_members_status ON members(status);
CREATE INDEX IF NOT EXISTS idx_members_department ON members(department_id);

-- Visitors table indexes
CREATE INDEX IF NOT EXISTS idx_visitors_name ON visitors(name);
CREATE INDEX IF NOT EXISTS idx_visitors_date ON visitors(visit_date);
CREATE INDEX IF NOT EXISTS idx_visitors_status ON visitors(follow_up_status);

-- Services table indexes
CREATE INDEX IF NOT EXISTS idx_services_date ON services(date);
CREATE INDEX IF NOT EXISTS idx_services_status ON services(status);

-- Attendance table indexes
CREATE INDEX IF NOT EXISTS idx_attendance_service ON attendance(service_id);
CREATE INDEX IF NOT EXISTS idx_attendance_member ON attendance(member_id);

-- =============================================================================
-- SETUP COMPLETE!
-- =============================================================================
-- Your database is now ready for the Bridge Ministries International
-- Attendance System. Make sure to:
-- 1. Update the database connection in config/database.php
-- 2. Change the default admin password after first login
-- 3. Add your church departments and initial members
-- =============================================================================