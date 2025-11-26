-- =============================================================================
-- BRIDGE MINISTRIES INTERNATIONAL - ATTENDANCE SYSTEM
-- Complete Database Export for Hosting Deployment
-- Generated on: 2025-11-26 21:47:31
-- =============================================================================

-- Create database (if hosting allows)
CREATE DATABASE IF NOT EXISTS attendance_system;
USE attendance_system;

-- Table: attendance
CREATE TABLE `attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `member_id` int NOT NULL,
  `service_id` int NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late') DEFAULT 'present',
  `marked_by` int DEFAULT NULL,
  `method` enum('manual','qr','barcode','auto') DEFAULT 'manual',
  `session_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marked_by` (`marked_by`),
  KEY `session_id` (`session_id`),
  KEY `idx_attendance_date` (`date`),
  KEY `idx_attendance_member` (`member_id`),
  KEY `idx_attendance_service` (`service_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`),
  CONSTRAINT `attendance_ibfk_4` FOREIGN KEY (`session_id`) REFERENCES `service_sessions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: audit_logs
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: communication
CREATE TABLE `communication` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `member_id` int DEFAULT NULL,
  `message` text NOT NULL,
  `type` enum('email','sms','system') DEFAULT 'system',
  `sent_at` datetime DEFAULT NULL,
  `status` enum('sent','pending','failed') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `communication_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `communication_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: departments
CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `leader_member_id` int DEFAULT NULL,
  `meeting_day` enum('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') DEFAULT NULL,
  `meeting_time` time DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT '0.00',
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: departments
INSERT INTO `departments` (`id`, `name`, `description`, `leader_member_id`, `meeting_day`, `meeting_time`, `budget`, `status`) VALUES
('1', 'Choir', 'Handles worship music', NULL, NULL, NULL, '0.00', 'active'),
('2', 'Ushers', 'Manages seating and order', NULL, NULL, NULL, '0.00', 'active'),
('3', 'Youth', 'Youth ministry activities', NULL, NULL, NULL, '0.00', 'active');

-- Table: events
CREATE TABLE `events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `organizer_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `max_attendees` int DEFAULT NULL,
  `registration_required` enum('yes','no') DEFAULT 'no',
  `registration_deadline` date DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT '0.00',
  `status` enum('planning','open','closed','cancelled','completed') DEFAULT 'planning',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organizer_id` (`organizer_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: families
CREATE TABLE `families` (
  `id` int NOT NULL AUTO_INCREMENT,
  `family_name` varchar(100) NOT NULL,
  `head_of_family` int DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `head_of_family` (`head_of_family`),
  CONSTRAINT `families_ibfk_1` FOREIGN KEY (`head_of_family`) REFERENCES `members` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: families
INSERT INTO `families` (`id`, `family_name`, `head_of_family`, `address`, `phone`, `email`, `created_at`) VALUES
('1', 'Johnson Family', '4', '321 Pine St', '08045678901', 'johnson.family@example.com', '2025-11-23 13:23:35'),
('2', 'Williams Family', '5', '654 Cedar Ave', '08067890123', 'williams.family@example.com', '2025-11-23 13:23:35');

-- Table: follow_ups
CREATE TABLE `follow_ups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `member_id` int NOT NULL,
  `assigned_to` int NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `follow_ups_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `follow_ups_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: member_positions
CREATE TABLE `member_positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `member_id` int NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `department_id` int DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive','completed') DEFAULT 'active',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `member_positions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_positions_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: member_positions
INSERT INTO `member_positions` (`id`, `member_id`, `position_name`, `department_id`, `start_date`, `end_date`, `status`, `description`, `created_at`) VALUES
('1', '1', 'Pastor', '1', '2020-01-01', NULL, 'active', NULL, '2025-11-23 13:57:18'),
('2', '2', 'Deacon', '2', '2021-06-01', NULL, 'active', NULL, '2025-11-23 13:57:18');

-- Table: member_skills
CREATE TABLE `member_skills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `member_id` int NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `skill_level` enum('beginner','intermediate','advanced','expert') DEFAULT 'beginner',
  `available` enum('yes','no') DEFAULT 'yes',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member_skill` (`member_id`,`skill_name`),
  CONSTRAINT `member_skills_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: member_skills
INSERT INTO `member_skills` (`id`, `member_id`, `skill_name`, `skill_level`, `available`, `created_at`) VALUES
('1', '1', 'Music - Piano', 'advanced', 'yes', '2025-11-23 13:23:35'),
('2', '1', 'Public Speaking', 'intermediate', 'yes', '2025-11-23 13:23:35'),
('3', '2', 'Organization', 'expert', 'yes', '2025-11-23 13:23:35'),
('4', '4', 'Teaching', 'expert', 'yes', '2025-11-23 13:23:35'),
('5', '5', 'Technical Support', 'advanced', 'yes', '2025-11-23 13:23:35');

-- Table: members
CREATE TABLE `members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `date_joined` date DEFAULT NULL,
  `baptized` enum('yes','no') DEFAULT 'no',
  `status` enum('active','inactive') DEFAULT 'active',
  `congregation_group` enum('Adult','Youth','Teen','Children') DEFAULT 'Adult',
  PRIMARY KEY (`id`),
  KEY `idx_members_department` (`department_id`),
  KEY `idx_members_status` (`status`),
  KEY `idx_members_date_joined` (`date_joined`),
  CONSTRAINT `members_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=214 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: members
INSERT INTO `members` (`id`, `name`, `dob`, `gender`, `location`, `phone`, `occupation`, `email`, `department_id`, `date_joined`, `baptized`, `status`, `congregation_group`) VALUES
('1', 'Test User', NULL, NULL, NULL, '123456789', NULL, NULL, NULL, NULL, 'no', 'active', 'Adult'),
('2', 'Lord William', NULL, NULL, NULL, '240279748', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('3', 'Frederica Afful', NULL, NULL, NULL, '272432100', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('4', 'Michael Abia Sackey', NULL, NULL, NULL, '243493595', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('5', 'Sara Ntow', NULL, NULL, NULL, '262440603', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('6', 'Martey Naomi', NULL, NULL, NULL, '243202612', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('7', 'John Richard', NULL, NULL, NULL, '239102664', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('8', 'Edem Affram', NULL, NULL, NULL, '244680038', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('9', 'Vivian Andokow', NULL, NULL, NULL, '279639585', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('10', 'Bennita Sobeli', NULL, NULL, NULL, '201859341', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('11', 'Vera Selorm Tasiame', NULL, NULL, NULL, '506946794', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('12', 'Benedicta Banahene', NULL, NULL, NULL, '506794254', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('13', 'Wisdom Dodzi Melio', NULL, NULL, NULL, '241563715', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('14', 'Agbottah Mawuli', NULL, NULL, NULL, '249352407', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('15', 'Asafo Adjei Michael', NULL, NULL, NULL, '509601099', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('16', 'Obikyenbi Norah Mawulawoe', NULL, NULL, NULL, '545398015', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('17', 'Nandy Amelley', NULL, NULL, NULL, '240645308', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('18', 'Issaka Salomey', NULL, NULL, NULL, '240036642', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('19', 'Eric Baidoo', NULL, NULL, NULL, '243838490', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('20', 'Princelove Okyere', NULL, NULL, NULL, '506703468', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('21', 'Derrick Kwasi Frimpong', NULL, NULL, NULL, '208026225', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('22', 'Henry Shadeko', NULL, NULL, NULL, '547218101', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('23', 'Nana Kwesi AduBoahen', NULL, NULL, NULL, '552001917', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('24', 'Bernice Kumah', NULL, NULL, NULL, '542329937', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('25', 'Gloria Agyekum', NULL, NULL, NULL, '206462476', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('26', 'Samuel Nunoo', NULL, NULL, NULL, '545333330', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('27', 'Elizabeth Esenam', NULL, NULL, NULL, '545560957', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('28', 'Doris Inkoom', NULL, NULL, NULL, '553829520', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('29', 'Sedor Wisdom', NULL, NULL, NULL, '599610238', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('30', 'John Richard', NULL, NULL, NULL, '504843020', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('31', 'Akpandja Abrampa', NULL, NULL, NULL, '544979407', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('32', 'Joshua Adusu', NULL, NULL, NULL, '546109539', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('33', 'LP Rukaya', NULL, NULL, NULL, '249317707', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('34', 'Lady Wendy', NULL, NULL, NULL, '244684663', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('35', 'KINGSLEY SAKYI BUDU', NULL, NULL, NULL, '270231629', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('36', 'Aryee Isaac', NULL, NULL, NULL, '598034778', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('37', 'Naa Akushika Allotey', NULL, NULL, NULL, '208060082', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('38', 'Stephen Tawiah', NULL, NULL, NULL, '545577055', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('39', 'Pastor Andrews', NULL, NULL, NULL, '244984221', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('40', 'Rev David Ampah korsah', NULL, NULL, NULL, '244697701', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('41', 'Abigail Darko', NULL, NULL, NULL, '244950338', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('42', 'Adepa Priscilla', NULL, NULL, NULL, '249390618', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('43', 'Blessing Issifu', NULL, NULL, NULL, '543273329', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('44', 'Michael kissi Nyarko', NULL, NULL, NULL, '579093844', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('45', 'Jessica Xedudzi', NULL, NULL, NULL, '596444979', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('46', 'Gifty Cudjoe', NULL, NULL, NULL, '249239248', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('47', 'Korley David', NULL, NULL, NULL, '249255978', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('48', 'Adjei-Adomako George', NULL, NULL, NULL, '276115331', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('49', 'Apedo Joshua', NULL, NULL, NULL, '543075773', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('50', 'Kojo Agyapong Ofori', NULL, NULL, NULL, '543397273', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('51', 'Abigail Adetona', NULL, NULL, NULL, '554666470', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('52', 'Anna Ami Ahiabli', NULL, NULL, NULL, '277312634', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('53', 'Dora Kweinorkie Quaynor', NULL, NULL, NULL, '508092809', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('54', 'Abigail Quayson', NULL, NULL, NULL, '530711950', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('55', 'Affumang Asare Samuel', NULL, NULL, NULL, '545454771', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('56', 'Dorcas Howard', NULL, NULL, NULL, '242443726', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('57', 'Adetona Naomi', NULL, NULL, NULL, '201281158', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('58', 'Maame Yaa', NULL, NULL, NULL, '244636105', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('59', 'Asamoah Joyce', NULL, NULL, NULL, '257111151', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('60', 'Alberta Anderson', NULL, NULL, NULL, '257631383', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('61', 'Dorcas Afful', NULL, NULL, NULL, '533271187', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('62', 'Agyemang Richard', NULL, NULL, NULL, '552709364', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('63', 'Fedora Bondzie', NULL, NULL, NULL, '271055300', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('64', 'Kelvin Adjei Adjetey', NULL, NULL, NULL, '209476738', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('65', 'Abraham Annan', NULL, NULL, NULL, '208551221', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('66', 'Adekunde Adetona', NULL, NULL, NULL, '243324143', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('67', 'FRED ADOTEI', NULL, NULL, NULL, '244207512', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('68', 'Georgina D Addae', NULL, NULL, NULL, '246635578', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('69', 'Barbara Brenya', NULL, NULL, NULL, '249488484', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('70', 'Anesi Akuye Addy', NULL, NULL, NULL, '260913077', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('71', 'Juliana odame Amponsah', NULL, NULL, NULL, '501798214', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('72', 'Dennis Ofori McClaude', NULL, NULL, NULL, '543421055', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('73', 'Elizabeth Adwoa Baah', NULL, NULL, NULL, '591683032', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('74', 'Gifty Nyan', NULL, NULL, NULL, '246153117', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('75', 'Anita Yenney', NULL, NULL, NULL, '553016482', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('76', 'Sophia Martey', NULL, NULL, NULL, '279516953', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('77', 'Hayford Phyllis', NULL, NULL, NULL, '502785549', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('78', 'Kesewaa Ansong', NULL, NULL, NULL, '541899844', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('79', 'Amartey Comfort', NULL, NULL, NULL, '556127516', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('80', 'Prince Ibeh', NULL, NULL, NULL, '559446328', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('81', 'Gifty Attopley', NULL, NULL, NULL, '243840134', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('82', 'Anna Sefah Antwi', NULL, NULL, NULL, '546121618', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('83', 'Joseline Ofori', NULL, NULL, NULL, '242533227', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('84', 'Pastor Anthony Okrah', NULL, NULL, NULL, '208453336', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('85', 'Kelvin Essiamah', NULL, NULL, NULL, '260528838', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('86', 'Josephine Adepa Akpor', NULL, NULL, NULL, '574629027', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('87', 'Apedo James', NULL, NULL, NULL, '550135442', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('88', 'Maria Arthur', NULL, NULL, NULL, '240405539', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('89', 'Asansu Elvis', NULL, NULL, NULL, '245138182', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('90', 'Theophilus Nii Tetteh', NULL, NULL, NULL, '277102804', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('91', 'Edmund fifi Acquah', NULL, NULL, NULL, '579237429', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('92', 'Bernice Shadeko', NULL, NULL, NULL, '547230765', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('93', 'Asamoah Clement', NULL, NULL, NULL, '205776242', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('94', 'Gladys Amoah', NULL, NULL, NULL, '243754784', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('95', 'Sarah Appau', NULL, NULL, NULL, '248048329', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('96', 'DAFFLINE SUETOR OKRAH', NULL, NULL, NULL, '501238916', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('97', 'Amina Mohammed', NULL, NULL, NULL, '557083910', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('98', 'Patrick Sakyi', NULL, NULL, NULL, '558420787', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('99', 'Nii Lante Lamptey', NULL, NULL, NULL, '573012350', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('100', 'Osei Guggisberg', NULL, NULL, NULL, '209081000', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('101', 'Dzadu Blessing', NULL, NULL, NULL, '257030897', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('102', 'Stella Asante', NULL, NULL, NULL, '540169499', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('103', 'Ranford Eduah', NULL, NULL, NULL, '554701503', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('104', 'Delali Anakpa', NULL, NULL, NULL, '275550096', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('105', 'Nii Adarku', NULL, NULL, NULL, '543941467', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('106', 'Agyepong Jonas Yaw', NULL, NULL, NULL, '547788363', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('107', 'Afi Dziedzoave', NULL, NULL, NULL, '548510383', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('108', 'Mavis Apedo', NULL, NULL, NULL, '548524397', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('109', 'Mavis Adjanor', NULL, NULL, NULL, '557484530', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('110', 'Emmanuel Philip Lutterodt', NULL, NULL, NULL, '547349680', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('111', 'Bernice Quartey', NULL, NULL, NULL, '242780840', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('112', 'Anakpah Elorm', NULL, NULL, NULL, '205700131', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('113', 'Queen Sennedy Lamptey', NULL, NULL, NULL, '546835267', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('114', 'Francis Tsibu', NULL, NULL, NULL, '547678961', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('115', 'Lily Akosah', NULL, NULL, NULL, '559448961', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('116', 'Ebenezer Teye Gyadu Narh', NULL, NULL, NULL, '597698353', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('117', 'Sally Annan', NULL, NULL, NULL, '201583963', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('118', 'Ernest Denkyira', NULL, NULL, NULL, '201769134', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('119', 'Tetteh Dugbanorki Sarah', NULL, NULL, NULL, '546497979', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('120', 'Mintah Bertha', NULL, NULL, NULL, '594006176', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('121', 'Adeleke Janet', NULL, NULL, NULL, '543700286', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('122', 'Rashida Ashilley', NULL, NULL, NULL, '262034258', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('123', 'Imade Frank', NULL, NULL, NULL, '541188578', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('124', 'Ernest', NULL, NULL, NULL, '551730038', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('125', 'Eric Kwesi Darku', NULL, NULL, NULL, '598198139', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('126', 'kutina thaddaeus Orlando', NULL, NULL, NULL, '209471290', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('127', 'Philemon Nyarko', NULL, NULL, NULL, '248729823', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('128', 'Esthelle Appaou', NULL, NULL, NULL, '544142444', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('129', 'Shadrach Boateng', NULL, NULL, NULL, '277077079', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('130', 'Doris Ofori', NULL, NULL, NULL, '247451055', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('131', 'Anna Quayson', NULL, NULL, NULL, '543730502', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('132', 'Peter Quansah', NULL, NULL, NULL, '555582787', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('133', 'Christine Agyepong Mprah', NULL, NULL, NULL, '208931015', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('134', 'Benedicta Tetteh', NULL, NULL, NULL, '257121743', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('135', 'SARAH APAU', NULL, NULL, NULL, '248431080', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('136', 'Dodzi Wisdom Melio', NULL, NULL, NULL, '277766411', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('137', 'Grace Larry', NULL, NULL, NULL, '202554181', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('138', 'Patience Panford', NULL, NULL, NULL, '246739240', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('139', 'Alice Quayson', NULL, NULL, NULL, '540750766', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('140', 'Dorothy Kankam', NULL, NULL, NULL, '243537778', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('141', 'Jennifer Mantebea', NULL, NULL, NULL, '597468101', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('142', 'Irene Attakorah', NULL, NULL, NULL, '244054520', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('143', 'Elipklim Anakpah', NULL, NULL, NULL, '267186347', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('144', 'Auntie Caro', NULL, NULL, NULL, '278561167', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('145', 'Rebecca acheampong', NULL, NULL, NULL, '549406979', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('146', 'Mercy', NULL, NULL, NULL, '532404342', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('147', 'Abigail Kasin-kana', NULL, NULL, NULL, '543034252', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('148', 'Michael Acheampong', NULL, NULL, NULL, '247758710', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('149', 'Abraham Kwame Bunna', NULL, NULL, NULL, '509531523', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('150', 'Adjei Godfred', NULL, NULL, NULL, '204080363', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('151', 'Irene Nuertey', NULL, NULL, NULL, '243776687', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('152', 'Afful Joshua Kankam', NULL, NULL, NULL, '271365847', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('153', 'Isaac Opoku', NULL, NULL, NULL, '544880003', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('154', 'Jeremy Moes', NULL, NULL, NULL, '548925042', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('155', 'Bernard Cobbinah', NULL, NULL, NULL, '264315588', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('156', 'Josephine Appoh', NULL, NULL, NULL, '277291126', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('157', 'Ernest Boakye', NULL, NULL, NULL, '546447569', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('158', 'Rebecca Siabi Acheampong', NULL, NULL, NULL, '5494006979', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('159', 'Pearl Adomako', NULL, NULL, NULL, '248828864', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('160', 'Chris Sherbo Avadzinu', NULL, NULL, NULL, '266381817', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('161', 'Bright Arthur', NULL, NULL, NULL, '593156650', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('162', 'Nathaniel Tweneboah', NULL, NULL, NULL, '585088301', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('163', 'Princess Djangmah', NULL, NULL, NULL, '557109246', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('164', 'Micheal kissi Nyarko', NULL, NULL, NULL, '599554074', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('165', 'Somoye Esther', NULL, NULL, NULL, '531242315', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('166', 'Priscilla Dogbe', NULL, NULL, NULL, '244075903', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('167', 'Vida Masopeh Akuffo', NULL, NULL, NULL, '244794037', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('168', 'Edna Asante', NULL, NULL, NULL, '244979731', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('169', 'Adomako Oforiwah Ewurama Beatrice', NULL, NULL, NULL, '248768713', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('170', 'Andy Awuah Orchere', NULL, NULL, NULL, '533900488', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('171', 'Okai Francis', NULL, NULL, NULL, '532020605', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('172', 'Emmanuel Ahenkan', NULL, NULL, NULL, '540615550', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('173', 'Daniel Sampong', NULL, NULL, NULL, '500975965', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('174', 'Perfect Ameho', NULL, NULL, NULL, '257001096', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('175', 'Diana Gafah', NULL, NULL, NULL, '556037876', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('176', 'naa Karley Tetteh', NULL, NULL, NULL, '546785720', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('177', 'David KONADU', NULL, NULL, NULL, '544158380', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('178', 'Victoria Adotei', NULL, NULL, NULL, '244616010', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('179', 'Morris Kwesi Mensah Baffoe', NULL, NULL, NULL, '576505965', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('180', 'Faustina Kwapong', NULL, NULL, NULL, '242567031', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('181', 'Cornelius', NULL, NULL, NULL, '244150546', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('182', 'Eric Agyekum', NULL, NULL, NULL, '558290082', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('183', 'Isaac Davis', NULL, NULL, NULL, '206788409', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('184', 'Anita Hayford', NULL, NULL, NULL, '240264251', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('185', 'Michael Adjei Owusu', NULL, NULL, NULL, '249326625', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('186', 'LUCKY SENNEDY', NULL, NULL, NULL, '501182601', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('187', 'Bright Iyke', NULL, NULL, NULL, '530912354', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('188', 'Kingsley K. Addai', NULL, NULL, NULL, '559965633', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('189', 'Jasmine Fafanyo Awagah', NULL, NULL, NULL, '209019196', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('190', 'Daniella Agbeko', NULL, NULL, NULL, '533851269', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('191', 'Tetteh Edith', NULL, NULL, NULL, '256974107', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('192', 'Djanie Oleria', NULL, NULL, NULL, '559659938', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('193', 'Samuel Dentteh', NULL, NULL, NULL, '578555650', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('194', 'Jeffery Moes', NULL, NULL, NULL, '549095370', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('195', 'Akua Mother', NULL, NULL, NULL, '557469671', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('196', 'Anfree Bartuah', NULL, NULL, NULL, '557398940', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('197', 'Rose Hayford', NULL, NULL, NULL, '203856022', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('198', 'Sartey Abigail', NULL, NULL, NULL, '543152556', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('199', 'Emmanuel Tei Lamptey', NULL, NULL, NULL, '543233198', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('200', 'Ruth Dodoo', NULL, NULL, NULL, '550706808', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('201', 'Afiba Awube Anim', NULL, NULL, NULL, '242626121', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('202', 'Ashong Martin', NULL, NULL, NULL, '551207333', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('203', 'Eunice Adjei', NULL, NULL, NULL, '244630271', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('204', 'AIMEE MORGAN', NULL, NULL, NULL, '558205380', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('205', 'Kobby Owusu', NULL, NULL, NULL, '533997186', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('206', 'VIDA NANA AMA BENSON', NULL, NULL, NULL, '243879232', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('207', 'EVANGELINE QUAYE', NULL, NULL, NULL, '278000396', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('208', 'Iyke Kings', NULL, NULL, NULL, '531876589', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('209', 'Francis Sarpong', NULL, NULL, NULL, '208241176', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('210', 'Micheal Acheampong', NULL, NULL, NULL, '247158710', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('211', 'Eric Nii Attoh', NULL, NULL, NULL, '500508530', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('212', 'Diana Gafah', NULL, NULL, NULL, '506037876', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult'),
('213', 'Afful Dorcas', NULL, NULL, NULL, '553272287', NULL, NULL, NULL, '2025-11-26', 'no', 'active', 'Adult');

-- Table: new_converts
CREATE TABLE `new_converts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `visitor_id` int DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `date_converted` date NOT NULL,
  `member_conversion_date` date DEFAULT NULL,
  `status` enum('active','converted_to_member','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `baptized` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visitor_id` (`visitor_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date_converted` (`date_converted`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `new_converts_ibfk_1` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `new_converts_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table: new_converts
INSERT INTO `new_converts` (`id`, `visitor_id`, `name`, `phone`, `email`, `department_id`, `date_converted`, `member_conversion_date`, `status`, `baptized`, `notes`, `created_at`, `updated_at`) VALUES
('1', '11', 'Sarah Wilson', '08076543210', 'sarah@example.com', NULL, '2025-11-26', NULL, 'active', 'no', '', '2025-11-26 05:02:27', '2025-11-26 05:02:27'),
('2', '10', 'Michael Thompson', '08087654321', 'michael@example.com', NULL, '2025-11-26', NULL, 'active', 'no', '', '2025-11-26 05:02:42', '2025-11-26 05:02:42'),
('3', '9', 'Jennifer Parker', '08098765432', 'jennifer@example.com', NULL, '2025-11-26', NULL, 'active', 'no', '', '2025-11-26 05:05:05', '2025-11-26 05:05:05');

-- Table: notifications
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `message` text NOT NULL,
  `type` enum('email','sms','system') DEFAULT 'system',
  `sent_at` datetime DEFAULT NULL,
  `status` enum('sent','pending','failed') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: service_sessions
CREATE TABLE `service_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `session_date` date NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` timestamp NULL DEFAULT NULL,
  `opened_by` int DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_service_date` (`service_id`,`session_date`),
  KEY `opened_by` (`opened_by`),
  KEY `closed_by` (`closed_by`),
  CONSTRAINT `service_sessions_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `service_sessions_ibfk_2` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`),
  CONSTRAINT `service_sessions_ibfk_3` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: service_sessions
INSERT INTO `service_sessions` (`id`, `service_id`, `session_date`, `location`, `opened_at`, `closed_at`, `opened_by`, `closed_by`, `status`) VALUES
('1', '5', '2025-11-22', 'Main Sanctuary', '2025-11-22 08:15:08', '2025-11-22 10:05:42', '1', '1', 'closed'),
('2', '9', '2025-11-22', NULL, '2025-11-22 16:17:43', '2025-11-22 16:21:34', '1', '1', 'closed'),
('3', '11', '2025-11-22', NULL, '2025-11-22 16:27:32', '2025-11-22 16:43:56', '1', '1', 'closed'),
('4', '14', '2025-11-26', NULL, '2025-11-26 06:18:12', NULL, '1', NULL, 'open');

-- Table: services
CREATE TABLE `services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `status` enum('scheduled','open','closed') DEFAULT 'scheduled',
  `opened_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `opened_by` int DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `template_status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_service_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: services
INSERT INTO `services` (`id`, `name`, `description`, `status`, `opened_at`, `closed_at`, `opened_by`, `closed_by`, `template_status`, `created_at`) VALUES
('3', 'Youth Fellowship', 'Monthly youth gathering', 'scheduled', NULL, NULL, NULL, NULL, 'active', '2025-11-22 08:41:47'),
('5', 'CELEBRATION SERVICE', '', 'open', '2025-11-21 23:36:36', NULL, '1', NULL, 'active', '2025-11-22 08:41:47'),
('9', 'CAN GOD', 'Powerful service focused on faith and divine possibilities', 'scheduled', NULL, NULL, NULL, NULL, 'active', '2025-11-22 08:41:47'),
('10', 'MIRACLE SERVICE', 'Healing and restoration service with prayer for miracles', 'scheduled', NULL, NULL, NULL, NULL, 'active', '2025-11-22 08:41:47'),
('11', 'ELITE SERVICE', 'Leadership development and excellence in ministry', 'scheduled', NULL, NULL, NULL, NULL, 'active', '2025-11-22 08:41:47'),
('12', 'SEMPA FIDELIS', 'Faithful service dedicated to spiritual commitment', 'scheduled', NULL, NULL, NULL, NULL, 'active', '2025-11-22 08:41:47'),
('13', 'JOINT SERVICE', 'Unity service bringing together different congregations', 'scheduled', NULL, NULL, NULL, NULL, 'active', '2025-11-22 08:41:47'),
('14', 'CELL', 'Teaching SFSAFSLDF', 'scheduled', NULL, NULL, NULL, NULL, 'active', '2025-11-22 10:01:10');

-- Table: system_settings
CREATE TABLE `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` text,
  `category` enum('general','attendance','notifications','security','display') DEFAULT 'general',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: system_settings
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `category`, `updated_by`, `updated_at`) VALUES
('1', 'church_name', 'Bridge Ministries International', 'Official church name', 'general', NULL, '2025-11-23 13:23:35'),
('2', 'church_address', '', 'Church physical address', 'general', NULL, '2025-11-23 13:23:35'),
('3', 'church_phone', '', 'Church contact phone number', 'general', NULL, '2025-11-23 13:23:35'),
('4', 'church_email', '', 'Church contact email', 'general', NULL, '2025-11-23 13:23:35'),
('5', 'attendance_auto_mark', 'no', 'Automatically mark attendance for services', 'attendance', NULL, '2025-11-23 13:23:35'),
('6', 'notification_email', 'yes', 'Enable email notifications', 'notifications', NULL, '2025-11-23 13:23:35'),
('7', 'session_timeout', '3600', 'Session timeout in seconds', 'security', NULL, '2025-11-23 13:23:35'),
('8', 'members_per_page', '25', 'Number of members to display per page', 'display', NULL, '2025-11-23 13:23:35');

-- Table: users
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: users
INSERT INTO `users` (`id`, `username`, `password`, `role`, `email`, `phone`) VALUES
('1', 'admin', 'admin', 'admin', 'admin@bridgeministries.org', '08011111111'),
('5', 'staff', 'password123', 'staff', NULL, NULL);

-- Table: visitor_attendance
CREATE TABLE `visitor_attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `visitor_id` int NOT NULL,
  `service_id` int NOT NULL,
  `visit_date` date NOT NULL,
  `visit_number` int DEFAULT '1',
  `brought_guests` int DEFAULT '0',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `visitor_id` (`visitor_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `visitor_attendance_ibfk_1` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `visitor_attendance_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: visitor_followups
CREATE TABLE `visitor_followups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `visitor_id` int NOT NULL,
  `follow_up_date` date NOT NULL,
  `follow_up_type` enum('call','visit','email','text') NOT NULL,
  `assigned_to` int NOT NULL,
  `status` enum('scheduled','completed','cancelled','no_response') DEFAULT 'scheduled',
  `notes` text,
  `completed_at` datetime DEFAULT NULL,
  `next_follow_up` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `visitor_id` (`visitor_id`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `visitor_followups_ibfk_1` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `visitor_followups_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: visitors
CREATE TABLE `visitors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `age_group` enum('child','youth','adult','senior') DEFAULT NULL,
  `how_heard` varchar(150) DEFAULT NULL,
  `first_time` enum('yes','no') DEFAULT 'yes',
  `invited_by` int DEFAULT NULL,
  `follow_up_needed` enum('yes','no') DEFAULT 'yes',
  `follow_up_date` date DEFAULT NULL,
  `follow_up_completed` enum('yes','no') DEFAULT 'no',
  `became_member` enum('yes','no') DEFAULT 'no',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `service_id` int NOT NULL,
  `date` date NOT NULL,
  `member_id` int DEFAULT NULL,
  `converted_date` date DEFAULT NULL,
  `status` enum('pending','contacted','follow_up_needed','converted','converted_to_convert','inactive') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `visitors_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `visitors_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: visitors
INSERT INTO `visitors` (`id`, `name`, `phone`, `email`, `address`, `gender`, `age_group`, `how_heard`, `first_time`, `invited_by`, `follow_up_needed`, `follow_up_date`, `follow_up_completed`, `became_member`, `notes`, `created_at`, `service_id`, `date`, `member_id`, `converted_date`, `status`) VALUES
('9', 'Jennifer Parker', '08098765432', 'jennifer@example.com', '123 Visitor St', 'female', 'adult', 'Friend invitation', 'yes', '1', 'yes', '2025-11-26', 'yes', 'no', '\nConverted to New Convert on 2025-11-26 05:05:05', '2025-11-23 13:47:38', '5', '2025-11-17', NULL, '2025-11-26', 'converted_to_convert'),
('10', 'Michael Thompson', '08087654321', 'michael@example.com', '456 Guest Ave', 'male', 'adult', 'Social media', 'yes', NULL, 'yes', NULL, 'no', 'no', '\nConverted to New Convert on 2025-11-26 05:02:42', '2025-11-23 13:47:38', '5', '2025-11-17', NULL, '2025-11-26', 'converted_to_convert'),
('11', 'Sarah Wilson', '08076543210', 'sarah@example.com', '789 Newcomer Rd', 'female', 'youth', 'Family member', 'no', '2', 'yes', '2025-11-26', 'yes', 'no', '\nConverted to New Convert on 2025-11-26 05:02:27', '2025-11-23 13:47:38', '3', '2025-11-20', NULL, '2025-11-26', 'converted_to_convert');

-- =============================================================================
-- DEPLOYMENT INSTRUCTIONS:
-- 1. Create database in hosting control panel
-- 2. Import this file using phpMyAdmin or MySQL import tool
-- 3. Update config/database.php with hosting database credentials
-- 4. Upload all project files to hosting
-- 5. Test login: admin / admin123 (change password!)
-- =============================================================================
