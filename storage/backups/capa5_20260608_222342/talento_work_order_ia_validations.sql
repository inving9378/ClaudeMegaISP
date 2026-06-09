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
-- Table structure for table `talento_work_order_ia_validations`
--

DROP TABLE IF EXISTS `talento_work_order_ia_validations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_work_order_ia_validations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `validation_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flags` json NOT NULL,
  `raw_response` json DEFAULT NULL,
  `overridden` tinyint(1) NOT NULL DEFAULT '0',
  `override_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overridden_by` bigint unsigned DEFAULT NULL,
  `overridden_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `twoiv_wo_idx` (`work_order_id`),
  KEY `idx_t_wo_ia_validations_tarea` (`tarea_id`),
  CONSTRAINT `fk_t_wo_ia_validations_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_wo_ia_validations_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `talento_work_order_ia_validations`
--

LOCK TABLES `talento_work_order_ia_validations` WRITE;
/*!40000 ALTER TABLE `talento_work_order_ia_validations` DISABLE KEYS */;
/*!40000 ALTER TABLE `talento_work_order_ia_validations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-08 22:23:47
