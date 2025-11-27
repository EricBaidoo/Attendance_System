-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: localhost    Database: attendance_system
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `visitors`
--

DROP TABLE IF EXISTS `visitors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visitors`
--

LOCK TABLES `visitors` WRITE;
/*!40000 ALTER TABLE `visitors` DISABLE KEYS */;
INSERT INTO `visitors` VALUES (9,'Jennifer Parker','08098765432','jennifer@example.com','123 Visitor St','female','adult','Friend invitation','yes',1,'yes','2025-11-26','yes','no','\nConverted to New Convert on 2025-11-26 05:05:05','2025-11-23 13:47:38',5,'2025-11-17',NULL,'2025-11-26','converted_to_convert'),(10,'Michael Thompson','08087654321','michael@example.com','456 Guest Ave','male','adult','Social media','yes',NULL,'yes',NULL,'no','no','\nConverted to New Convert on 2025-11-26 05:02:42','2025-11-23 13:47:38',5,'2025-11-17',NULL,'2025-11-26','converted_to_convert'),(11,'Sarah Wilson','08076543210','sarah@example.com','789 Newcomer Rd','female','youth','Family member','no',2,'yes','2025-11-26','yes','no','\nConverted to New Convert on 2025-11-26 05:02:27','2025-11-23 13:47:38',3,'2025-11-20',NULL,'2025-11-26','converted_to_convert');
/*!40000 ALTER TABLE `visitors` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-27  4:15:25
