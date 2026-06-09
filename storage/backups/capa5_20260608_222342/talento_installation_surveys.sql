mysqldump: [Warning] Using a password on the command line interface can be insecure.
-- MySQL dump 10.13  Distrib 8.4.6, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: megaisp
-- ------------------------------------------------------
-- Server version	8.4.6

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `talento_installation_surveys`
--

DROP TABLE IF EXISTS `talento_installation_surveys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_installation_surveys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `rating_overall` tinyint unsigned DEFAULT NULL,
  `rating_technician` tinyint unsigned DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `google_review_offered` tinyint(1) NOT NULL DEFAULT '0',
  `google_review_opened` tinyint(1) NOT NULL DEFAULT '0',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `auto_closed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tis_client_idx` (`client_id`),
  KEY `idx_t_installation_surveys_tarea` (`tarea_id`),
  KEY `fk_t_installation_surveys_wo` (`work_order_id`),
  CONSTRAINT `fk_t_installation_surveys_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_installation_surveys_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `talento_installation_surveys`
--

LOCK TABLES `talento_installation_surveys` WRITE;
/*!40000 ALTER TABLE `talento_installation_surveys` DISABLE KEYS */;
/*!40000 ALTER TABLE `talento_installation_surveys` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-08 22:23:48
