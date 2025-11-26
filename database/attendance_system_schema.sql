-- Create the database
CREATE DATABASE IF NOT EXISTS attendance_system;
USE attendance_system;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20)
);

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    dob DATE,
    gender ENUM('male', 'female', 'other'),
    address VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(100),
    department_id INT,
    date_joined DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    location VARCHAR(100),
    type VARCHAR(50),
    status ENUM('scheduled', 'open', 'closed') DEFAULT 'scheduled',
    opened_at DATETIME NULL,
    closed_at DATETIME NULL,
    opened_by INT NULL,
    closed_by INT NULL,
    FOREIGN KEY (opened_by) REFERENCES users(id),
    FOREIGN KEY (closed_by) REFERENCES users(id)
);

CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    service_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    marked_by INT,
    method ENUM('manual', 'qr', 'barcode') DEFAULT 'manual',
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (marked_by) REFERENCES users(id)
);

CREATE TABLE visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    service_id INT NOT NULL,
    date DATE NOT NULL,
    member_id INT,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (member_id) REFERENCES members(id)
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT NOT NULL,
    type ENUM('email', 'sms', 'system') DEFAULT 'system',
    sent_at DATETIME,
    status ENUM('sent', 'pending', 'failed') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE communication (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    member_id INT,
    message TEXT NOT NULL,
    type ENUM('email', 'sms', 'system') DEFAULT 'system',
    sent_at DATETIME,
    status ENUM('sent', 'pending', 'failed') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (member_id) REFERENCES members(id)
);

-- Demo data for departments
INSERT INTO departments (name, description) VALUES
    ('Choir', 'Handles worship music'),
    ('Ushers', 'Manages seating and order'),
    ('Youth', 'Youth ministry activities');

-- Demo data for members
INSERT INTO members (name, dob, gender, address, phone, email, department_id, date_joined, status) VALUES
    ('John Doe', '1990-05-12', 'male', '123 Main St', '08012345678', 'john@example.com', 1, '2023-01-10', 'active'),
    ('Jane Smith', '1985-08-22', 'female', '456 Elm St', '08023456789', 'jane@example.com', 2, '2023-02-15', 'active'),
    ('Samuel Lee', '2000-11-03', 'male', '789 Oak St', '08034567890', 'samuel@example.com', 3, '2023-03-20', 'active');

-- Demo data for services
INSERT INTO services (name, description, date, location, type, status) VALUES
    ('Sunday Service', 'Weekly worship service', '2025-11-24', 'Main Auditorium', 'Worship', 'scheduled'),
    ('Bible Study', 'Midweek bible study', '2025-11-26', 'Room 2', 'Teaching', 'scheduled'),
    ('Youth Fellowship', 'Monthly youth gathering', '2025-11-30', 'Youth Hall', 'Fellowship', 'scheduled');

-- Demo data for admin user
INSERT INTO users (username, password, role, email, phone) VALUES
    ('admin', 'admin', 'admin', 'admin@bridgeministries.org', '08011111111');
