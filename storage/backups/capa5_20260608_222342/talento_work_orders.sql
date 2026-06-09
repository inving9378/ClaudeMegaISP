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
-- Table structure for table `talento_work_orders`
--

DROP TABLE IF EXISTS `talento_work_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_work_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `type_id` bigint unsigned NOT NULL,
  `points` smallint unsigned NOT NULL,
  `is_billable` tinyint(1) NOT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `olt_onu_id` bigint unsigned DEFAULT NULL,
  `caja_id` bigint unsigned DEFAULT NULL,
  `modem_sn` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_item_id` bigint unsigned DEFAULT NULL,
  `inventory_movement_id` bigint unsigned DEFAULT NULL,
  `status` enum('pending','in_progress','completed','validated','cancelled','pending_activation','active','survey_pending','incidencia') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `accepted_by` bigint unsigned DEFAULT NULL,
  `activation_requested_at` timestamp NULL DEFAULT NULL,
  `activation_confirmed_at` timestamp NULL DEFAULT NULL,
  `activation_by` bigint unsigned DEFAULT NULL,
  `survey_completed` tinyint(1) NOT NULL DEFAULT '0',
  `validated_by` bigint unsigned DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `nota_tecnico` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_work_orders_type_id_foreign` (`type_id`),
  KEY `talento_work_orders_colaborador_id_status_index` (`colaborador_id`,`status`),
  KEY `talento_work_orders_scheduled_at_index` (`scheduled_at`),
  KEY `talento_work_orders_status_index` (`status`),
  CONSTRAINT `talento_work_orders_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_work_orders_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `talento_work_order_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `talento_work_orders`
--

LOCK TABLES `talento_work_orders` WRITE;
/*!40000 ALTER TABLE `talento_work_orders` DISABLE KEYS */;
INSERT INTO `talento_work_orders` VALUES (1,1,1,3,1,3986,4981,NULL,NULL,NULL,NULL,NULL,'incidencia','2026-06-05 15:00:00','2026-06-05 20:26:59',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'[PRUEBA] Fibra rota — reponer cableado. Cliente: Irving Jonathan Hernandez Carrillo.',NULL,3986,3986,'2026-06-05 19:48:22','2026-06-07 16:27:41',NULL),(2,1,4,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'completed','2026-06-07 16:29:00','2026-06-07 16:30:44','2026-06-07 16:31:31',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-07 16:29:44','2026-06-07 16:31:31',NULL),(3,1,1,9,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'in_progress','2026-06-07 16:31:00','2026-06-07 16:33:28',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-07 16:32:23','2026-06-07 16:33:28',NULL);
/*!40000 ALTER TABLE `talento_work_orders` ENABLE KEYS */;
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
