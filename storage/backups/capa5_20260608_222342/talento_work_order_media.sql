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
-- Table structure for table `talento_work_order_media`
--

DROP TABLE IF EXISTS `talento_work_order_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_work_order_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `evidence_type_id` bigint unsigned DEFAULT NULL,
  `type` enum('presentation','completion','ine_front','ine_back','proof_address','modem_sn','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `captured_lat` decimal(10,7) DEFAULT NULL,
  `captured_lng` decimal(10,7) DEFAULT NULL,
  `captured_at` timestamp NULL DEFAULT NULL,
  `server_captured_at` timestamp NULL DEFAULT NULL,
  `captured_in_app` tinyint(1) NOT NULL DEFAULT '1',
  `watermark_applied` tinyint(1) NOT NULL DEFAULT '0',
  `location_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `is_mock_location` tinyint(1) NOT NULL DEFAULT '0',
  `location_distance_m` decimal(8,1) DEFAULT NULL,
  `potencia_dbm` decimal(6,2) DEFAULT NULL COMMENT 'Potencia de señal medida en campo (dBm). Negativo = pérdida.',
  `justificacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_accuracy_m` decimal(8,1) DEFAULT NULL COMMENT 'Precisión del fix GPS reportada por la app (metros).',
  `source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web' COMMENT 'Origen del upload: web | mobile | field_app.',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `twom_wo_type_idx` (`work_order_id`,`type`),
  KEY `talento_work_order_media_evidence_type_id_foreign` (`evidence_type_id`),
  KEY `idx_t_wo_media_tarea` (`tarea_id`),
  CONSTRAINT `fk_t_wo_media_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_wo_media_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `talento_work_order_media_evidence_type_id_foreign` FOREIGN KEY (`evidence_type_id`) REFERENCES `talento_evidence_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `talento_work_order_media`
--

LOCK TABLES `talento_work_order_media` WRITE;
/*!40000 ALTER TABLE `talento_work_order_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `talento_work_order_media` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-08 22:23:44
