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
-- Table structure for table `talento_health_bonus_log`
--

DROP TABLE IF EXISTS `talento_health_bonus_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_health_bonus_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `colaborador_id` bigint unsigned NOT NULL,
  `caja_ref` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `baseline_power_dbm` decimal(5,2) DEFAULT NULL,
  `client_power_dbm` decimal(5,2) DEFAULT NULL,
  `loss_db` decimal(5,2) DEFAULT NULL,
  `max_loss_db` decimal(4,2) NOT NULL,
  `bonus_awarded` tinyint(1) NOT NULL DEFAULT '0',
  `bonus_amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `power_source` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `skip_reason` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `thbl_col_time_idx` (`colaborador_id`,`checked_at`),
  KEY `idx_t_health_bonus_log_tarea` (`tarea_id`),
  KEY `fk_t_health_bonus_log_wo` (`work_order_id`),
  CONSTRAINT `fk_t_health_bonus_log_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_health_bonus_log_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `talento_health_bonus_log`
--

LOCK TABLES `talento_health_bonus_log` WRITE;
/*!40000 ALTER TABLE `talento_health_bonus_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `talento_health_bonus_log` ENABLE KEYS */;
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
