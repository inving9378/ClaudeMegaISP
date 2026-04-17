-- mysqldump-php https://github.com/ifsnop/mysqldump-php
--
-- Host: 127.0.0.1	Database: mtest
-- ------------------------------------------------------
-- Server version 	5.7.35
-- Date: Mon, 16 Dec 2024 16:42:43 -0600

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40101 SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `active_equipments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `active_equipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rack_id` bigint(20) unsigned NOT NULL,
  `type_id` bigint(20) unsigned NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `active_equipments_rack_id_foreign` (`rack_id`),
  KEY `active_equipments_type_id_foreign` (`type_id`),
  KEY `active_equipments_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `active_equipments_created_by_foreign` (`created_by`),
  KEY `active_equipments_updated_by_foreign` (`updated_by`),
  CONSTRAINT `active_equipments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `active_equipments_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `active_equipments_rack_id_foreign` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`),
  CONSTRAINT `active_equipments_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `active_equipment_types` (`id`),
  CONSTRAINT `active_equipments_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `active_equipments`
--

LOCK TABLES `active_equipments` WRITE;
/*!40000 ALTER TABLE `active_equipments` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `active_equipments` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `active_equipments` with 0 row(s)
--

--
-- Table structure for table `active_equipment_peripherals`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `active_equipment_peripherals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `active_equipment_id` bigint(20) unsigned NOT NULL,
  `model` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` bigint(20) unsigned NOT NULL,
  `ports` int(10) unsigned NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `active_equipment_peripherals_active_equipment_id_foreign` (`active_equipment_id`),
  KEY `active_equipment_peripherals_brand_id_foreign` (`brand_id`),
  KEY `active_equipment_peripherals_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `active_equipment_peripherals_created_by_foreign` (`created_by`),
  KEY `active_equipment_peripherals_updated_by_foreign` (`updated_by`),
  CONSTRAINT `active_equipment_peripherals_active_equipment_id_foreign` FOREIGN KEY (`active_equipment_id`) REFERENCES `active_equipments` (`id`),
  CONSTRAINT `active_equipment_peripherals_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `active_equipment_peripherals_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `active_equipment_peripherals_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `active_equipment_peripherals_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `active_equipment_peripherals`
--

LOCK TABLES `active_equipment_peripherals` WRITE;
/*!40000 ALTER TABLE `active_equipment_peripherals` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `active_equipment_peripherals` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `active_equipment_peripherals` with 0 row(s)
--

--
-- Table structure for table `active_equipment_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `active_equipment_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('Router','swtich','OLT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` bigint(20) unsigned NOT NULL,
  `model` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cards` int(11) NOT NULL,
  `ethernet_ports` int(11) NOT NULL,
  `sfp_ports` int(11) NOT NULL,
  `sfp_plus_ports` int(11) NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `active_equipment_types_brand_id_foreign` (`brand_id`),
  KEY `active_equipment_types_created_by_foreign` (`created_by`),
  KEY `active_equipment_types_updated_by_foreign` (`updated_by`),
  CONSTRAINT `active_equipment_types_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `active_equipment_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `active_equipment_types_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `active_equipment_types`
--

LOCK TABLES `active_equipment_types` WRITE;
/*!40000 ALTER TABLE `active_equipment_types` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `active_equipment_types` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `active_equipment_types` with 0 row(s)
--

--
-- Table structure for table `box_inputs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `box_inputs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` int(11) NOT NULL,
  `box_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box_inputs_box_id_foreign` (`box_id`),
  KEY `box_inputs_created_by_foreign` (`created_by`),
  KEY `box_inputs_updated_by_foreign` (`updated_by`),
  CONSTRAINT `box_inputs_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `boxes` (`id`),
  CONSTRAINT `box_inputs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `box_inputs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `box_inputs`
--

LOCK TABLES `box_inputs` WRITE;
/*!40000 ALTER TABLE `box_inputs` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `box_inputs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `box_inputs` with 0 row(s)
--

--
-- Table structure for table `box_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `box_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('Empalme','Primer nivel','Segundo nivel') COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` bigint(20) unsigned NOT NULL,
  `inputs` int(10) unsigned NOT NULL,
  `trays` int(10) unsigned NOT NULL,
  `mergers_by_tray` int(10) unsigned NOT NULL,
  `ports` int(10) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box_types_brand_id_foreign` (`brand_id`),
  KEY `box_types_created_by_foreign` (`created_by`),
  KEY `box_types_updated_by_foreign` (`updated_by`),
  CONSTRAINT `box_types_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `box_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `box_types_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `box_types`
--

LOCK TABLES `box_types` WRITE;
/*!40000 ALTER TABLE `box_types` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `box_types` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `box_types` with 0 row(s)
--

--
-- Table structure for table `brands`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `brands_created_by_foreign` (`created_by`),
  KEY `brands_updated_by_foreign` (`updated_by`),
  CONSTRAINT `brands_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `brands_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `brands` with 0 row(s)
--

--
-- Table structure for table `buffers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buffers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `color_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `buffers_color_id_unique` (`color_id`),
  KEY `buffers_created_by_foreign` (`created_by`),
  KEY `buffers_updated_by_foreign` (`updated_by`),
  CONSTRAINT `buffers_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`),
  CONSTRAINT `buffers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `buffers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buffers`
--

LOCK TABLES `buffers` WRITE;
/*!40000 ALTER TABLE `buffers` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `buffers` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `buffers` with 0 row(s)
--

--
-- Table structure for table `bundles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bundles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` bigint(20) DEFAULT NULL,
  `tax_include` tinyint(1) DEFAULT NULL,
  `tax` bigint(20) DEFAULT NULL,
  `transaction_category` enum('Servicio','Descuento','Pago','Reembolso','Corrección') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_days` bigint(20) DEFAULT NULL,
  `activation_fee` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `get_activation_fee_when` enum('En facturación del primer servicio','Al crear el servicio') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emit_invoice` tinyint(1) DEFAULT NULL,
  `contract_duration` bigint(20) DEFAULT NULL,
  `automatic_renewal` tinyint(1) DEFAULT NULL,
  `auto_reactivate` tinyint(1) DEFAULT NULL,
  `cancellation_fee` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prior_cancellation_fee` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_period` bigint(20) DEFAULT NULL,
  `discount_value` bigint(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bundles`
--

LOCK TABLES `bundles` WRITE;
/*!40000 ALTER TABLE `bundles` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `bundles` VALUES (2,'ESTUDIANTE+','ESTUDIANTE+',520,1,16,'Servicio',NULL,'0.00','En facturación del primer servicio',0,NULL,1,1,'0','0.00',NULL,NULL,NULL,'2024-11-15 12:50:47'),(3,'BASICO','BASICO',420,1,16,'Servicio',NULL,'0.00 ','Al crear el servicio',1,NULL,1,1,'299','299.00 ',NULL,NULL,NULL,NULL),(7,'20MB+TEL+EMPLEADOS','20MB+TEL+EMPLEADOS',270,1,16,'Servicio',NULL,'0.00 ','En facturación del primer servicio',0,NULL,0,0,'0','0.00 ',NULL,NULL,NULL,NULL),(15,'GAMER+','GAMER+',669,1,16,'Servicio',NULL,'0.00 ','Al crear el servicio',0,NULL,0,0,'299','299.00 ',NULL,NULL,NULL,NULL),(16,'PLUS+','PLUS+',520,1,16,'Servicio',NULL,'0.00','Al crear el servicio',1,NULL,1,1,'299','299.00',NULL,NULL,NULL,'2024-11-06 14:53:57'),(20,'NEGOCIO BASICO','NEGOCIOBASICO',470,1,16,'Servicio',NULL,'0.00 ','En facturación del primer servicio',0,18,1,1,'299','299.00 ',NULL,NULL,NULL,NULL),(26,'CLASICO+TEL_REGALO','349',349,1,16,'Servicio',NULL,'0.00 ','En facturación del primer servicio',0,NULL,0,0,'299','299.00 ',NULL,NULL,NULL,NULL),(28,'50+tel empleados','50+tel-empleados',330,1,16,'Servicio',NULL,'0.00 ','En facturación del primer servicio',0,NULL,1,1,'0','0.00 ',NULL,NULL,NULL,NULL),(29,'50 mb Empleados','50_mb_empleados',300,1,16,'Servicio',NULL,'0.00','En facturación del primer servicio',0,NULL,0,0,'0','0.00',NULL,NULL,NULL,'2024-10-09 15:41:17'),(30,'Internet Plus50+','Internet50mb+telefoniaGratis',349,1,16,'Servicio',NULL,'299','En facturación del primer servicio',0,6,1,1,'299','299.00',NULL,NULL,NULL,'2024-11-29 13:54:37'),(31,'Internet Gamer 100+','100mbinternetytelefoniaGratis',449,1,16,'Servicio',NULL,'0.00 ','En facturación del primer servicio',0,6,1,1,'299','0.00 ',NULL,NULL,NULL,NULL),(32,'Internet 150+','Internet150mb+telefoniaGratis',509,1,16,'Servicio',NULL,'0.00','En facturación del primer servicio',0,NULL,0,0,'299','0.00',NULL,NULL,NULL,'2024-10-07 10:04:18'),(33,'Internet 200+','Internet200mb+TelefoniaGratis',549,1,16,'Servicio',NULL,'0.00','En facturación del primer servicio',0,6,1,1,'299','0.00',NULL,NULL,NULL,'2024-11-08 15:05:13'),(34,'100mb Promo 70','100mbPromo70',449,1,16,'Servicio',NULL,'0.00 ','En facturación del primer servicio',0,12,1,1,'0','0.00 ',NULL,NULL,NULL,NULL),(35,'500mb+tel+movienet','500mb+tel+movienet',709,1,16,'Servicio',NULL,'0.00 ','En facturación del primer servicio',0,NULL,1,1,'0','0.00 ',NULL,NULL,NULL,NULL),(36,'250MB+','250MB+',599,0,NULL,NULL,NULL,'299',NULL,0,18,1,1,'1200','1200',NULL,NULL,'2024-10-08 15:11:06','2024-11-11 11:36:16'),(37,'Administracion paquete',NULL,NULL,0,NULL,'Servicio',NULL,NULL,NULL,0,NULL,0,0,NULL,NULL,NULL,NULL,'2024-10-23 16:33:21','2024-10-23 16:33:21'),(38,'PORTABILIDAD 100','PORTABILIDAD 100',449,1,16,'Servicio',NULL,'299',NULL,0,12,1,1,'299','1200',6,22,'2024-12-03 11:48:27','2024-12-03 11:48:27');
/*!40000 ALTER TABLE `bundles` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `bundles` with 18 row(s)
--

--
-- Table structure for table `cards`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('WAN','LAN') COLLATE utf8mb4_unicode_ci NOT NULL,
  `active_equipment_id` bigint(20) unsigned NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cards_active_equipment_id_foreign` (`active_equipment_id`),
  KEY `cards_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `cards_created_by_foreign` (`created_by`),
  KEY `cards_updated_by_foreign` (`updated_by`),
  CONSTRAINT `cards_active_equipment_id_foreign` FOREIGN KEY (`active_equipment_id`) REFERENCES `active_equipments` (`id`),
  CONSTRAINT `cards_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `cards_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `cards_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cards`
--

LOCK TABLES `cards` WRITE;
/*!40000 ALTER TABLE `cards` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `cards` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `cards` with 0 row(s)
--

--
-- Table structure for table `colors`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `colors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `colors_name_unique` (`name`),
  UNIQUE KEY `colors_code_unique` (`code`),
  KEY `colors_created_by_foreign` (`created_by`),
  KEY `colors_updated_by_foreign` (`updated_by`),
  CONSTRAINT `colors_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `colors_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `colors`
--

LOCK TABLES `colors` WRITE;
/*!40000 ALTER TABLE `colors` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `colors` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `colors` with 0 row(s)
--

--
-- Table structure for table `column_datatable_modules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `column_datatable_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filter_name` text COLLATE utf8mb4_unicode_ci,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `class` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `column_datatable_modules`
--

LOCK TABLES `column_datatable_modules` WRITE;
/*!40000 ALTER TABLE `column_datatable_modules` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `column_datatable_modules` VALUES (1,1,'title',NULL,'Título','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(2,1,'service_name',NULL,'Nombre del Servicio','1',NULL,'1',NULL,'2024-05-09 21:43:11'),(3,1,'price',NULL,'Precio','1',NULL,'2',NULL,'2024-05-09 21:43:11'),(4,1,'download_speed',NULL,'Velocidad de Descarga (kbps)','1',NULL,'3',NULL,'2024-05-09 21:43:11'),(5,1,'upload_speed',NULL,'Velocidad de Subida (kbps)','1',NULL,'4',NULL,'2024-05-09 21:43:11'),(6,1,'update_service',NULL,'Actualización de la descripción existente de los servicios','1',NULL,'5',NULL,'2024-05-09 21:43:11'),(7,1,'tax_include',NULL,'Iva Incluido','1',NULL,'6',NULL,'2024-05-09 21:43:11'),(8,1,'tax',NULL,'Iva','1',NULL,'7',NULL,'2024-05-09 21:43:11'),(9,1,'guaranteed_speed_limit',NULL,'Lím. Velocidad Garantizada','1',NULL,'8',NULL,'2024-05-09 21:43:11'),(10,1,'priority',NULL,'Prioridad','1',NULL,'9',NULL,'2024-05-09 21:43:11'),(11,1,'aggregation',NULL,'Agregación','1',NULL,'10',NULL,'2024-05-09 21:43:11'),(12,1,'burst',NULL,'Límite de Burst','1',NULL,'11',NULL,'2024-05-09 21:43:11'),(13,1,'burt_umbral',NULL,'Umbral de Burst','1',NULL,'12',NULL,'2024-05-09 21:43:11'),(14,1,'burt_time',NULL,'Tiempo de Burst','1',NULL,'13',NULL,'2024-05-09 21:43:11'),(15,1,'rates_to_change',NULL,'Tarifas que se Pueden Elegir en Portal del Cliente','1',NULL,'14',NULL,'2024-05-09 21:43:11'),(16,1,'prepaid_period',NULL,'Períodos de Pago','1',NULL,'15',NULL,'2024-05-09 21:43:12'),(17,1,'transaction_category',NULL,'Categoría de la Transacción','1',NULL,'16',NULL,'2024-05-09 21:43:12'),(18,1,'available_when_register_by_social_network',NULL,'Disponible cuando se registra en la red social','1',NULL,'17',NULL,'2024-05-09 21:43:12'),(19,1,'cost_activation',NULL,'Costo de Activación','1',NULL,'18',NULL,'2024-05-09 21:43:12'),(20,1,'cost_instalation',NULL,'Costo de Instalación','1',NULL,'19',NULL,'2024-05-09 21:43:12'),(21,1,'action',NULL,'Acciones','1',NULL,'9999',NULL,'2024-11-12 11:26:27'),(22,2,'title',NULL,'titulo','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(23,2,'price',NULL,'precio','1',NULL,'1',NULL,'2024-05-09 21:43:12'),(24,2,'service_name',NULL,'servicio','1',NULL,'2',NULL,'2024-05-09 21:43:12'),(25,2,'type',NULL,'type','1',NULL,'3',NULL,'2024-05-09 21:43:12'),(26,2,'partners',NULL,'Socios','1',NULL,'4',NULL,'2024-05-09 21:43:12'),(27,2,'tax_include',NULL,'IVA Incluido','1',NULL,'5',NULL,'2024-05-09 21:43:12'),(28,2,'tax',NULL,'IVA','1',NULL,'6',NULL,'2024-05-09 21:43:12'),(29,2,'prepaid_period',NULL,'Períodos de Pago','1',NULL,'7',NULL,'2024-05-09 21:43:12'),(30,2,'rates_to_change',NULL,'Tarifas que se Pueden Elegir en Portal del Cliente','1',NULL,'8',NULL,'2024-05-09 21:43:12'),(31,2,'transaction_category',NULL,'Categoría de la Transacción','1',NULL,'9',NULL,'2024-05-09 21:43:12'),(32,2,'transaction_category_for_calls',NULL,'Categoria de Transacción de Llamadas','1',NULL,'10',NULL,'2024-05-09 21:43:12'),(33,2,'transaction_category_for_messages',NULL,'Categoria de Transacción de Mensajes','1',NULL,'11',NULL,'2024-05-09 21:43:12'),(34,2,'transaction_category_for_data',NULL,'Categoria de Transacción de Datos','1',NULL,'12',NULL,'2024-05-09 21:43:12'),(35,2,'available_in_self_registration',NULL,'Disponible el Auto Registro','1',NULL,'13',NULL,'2024-05-09 21:43:12'),(36,2,'bandwidth',NULL,'Ancho de Banda','1',NULL,'14',NULL,'2024-05-09 21:43:12'),(37,2,'priority',NULL,'Prioridad','1',NULL,'15',NULL,'2024-05-09 21:43:12'),(38,2,'cost_activation',NULL,'Costo de Activacion','1',NULL,'16',NULL,'2024-05-09 21:43:12'),(39,2,'cost_instalation',NULL,'Costo de Instalacion','1',NULL,'17',NULL,'2024-05-09 21:43:12'),(40,2,'action',NULL,'Acciones','1',NULL,'9999',NULL,'2024-11-12 11:26:27'),(41,3,'title',NULL,'Título','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(42,3,'price',NULL,'Precio','1',NULL,'1',NULL,'2024-05-09 21:43:12'),(43,3,'service_name',NULL,'Servicio','1',NULL,'2',NULL,'2024-05-09 21:43:12'),(44,3,'update_description',NULL,'Actualización de la descripción existente de los servicios','1',NULL,'3',NULL,'2024-05-09 21:43:12'),(45,3,'partners',NULL,'Socios','1',NULL,'4',NULL,'2024-05-09 21:43:12'),(46,3,'tax_include',NULL,'IVA Incluido','1',NULL,'5',NULL,'2024-05-09 21:43:12'),(47,3,'tax',NULL,'IVA','1',NULL,'6',NULL,'2024-05-09 21:43:12'),(48,3,'prepaid_period',NULL,'Períodos de Pago','1',NULL,'7',NULL,'2024-05-09 21:43:12'),(49,3,'rates_to_change',NULL,'Tarifas que se Pueden Elegir en Portal del Cliente','1',NULL,'8',NULL,'2024-05-09 21:43:12'),(50,3,'transaction_category',NULL,'Categoría de la Transacción','1',NULL,'9',NULL,'2024-05-09 21:43:12'),(51,3,'available_in_self_registration',NULL,'Disponible el Auto Registro','1',NULL,'10',NULL,'2024-05-09 21:43:12'),(52,3,'bandwidth',NULL,'Ancho de Banda','1',NULL,'11',NULL,'2024-05-09 21:43:12'),(53,3,'priority',NULL,'Prioridad','1',NULL,'12',NULL,'2024-05-09 21:43:12'),(54,3,'cost_activation',NULL,'Costo de Activacion','1',NULL,'13',NULL,'2024-05-09 21:43:12'),(55,3,'cost_instalation',NULL,'Costo de Instalacion','1',NULL,'14',NULL,'2024-05-09 21:43:12'),(56,3,'action',NULL,'Acciones','1',NULL,'9999',NULL,'2024-11-12 11:26:27'),(57,4,'title',NULL,'Título','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(58,4,'price',NULL,'Precio','1',NULL,'1',NULL,'2024-05-09 21:43:12'),(59,4,'service_description',NULL,'Descripción','1',NULL,'2',NULL,'2024-05-09 21:43:12'),(60,4,'tax_include',NULL,'IVA Incluido','1',NULL,'3',NULL,'2024-05-09 21:43:12'),(61,4,'tax',NULL,'IVA','1',NULL,'4',NULL,'2024-05-09 21:43:12'),(62,4,'transaction_category',NULL,'Categoría de la Transacción','1',NULL,'5',NULL,'2024-05-09 21:43:12'),(63,4,'activation_fee',NULL,'Costo de Activación','1',NULL,'6',NULL,'2024-05-09 21:43:12'),(64,4,'get_activation_fee_when',NULL,'Aplica activación','1',NULL,'7',NULL,'2024-05-09 21:43:12'),(65,4,'emit_invoice',NULL,'Emitir Factura','1',NULL,'8',NULL,'2024-05-09 21:43:12'),(66,4,'contract_duration',NULL,'Duración del Contrato','1',NULL,'9',NULL,'2024-05-09 21:43:12'),(67,4,'automatic_renewal',NULL,'Renovación Automática','1',NULL,'10',NULL,'2024-05-09 21:43:12'),(68,4,'auto_reactivate',NULL,'Reactivar automáticamente','1',NULL,'11',NULL,'2024-05-09 21:43:12'),(69,4,'cancellation_fee',NULL,'Cancelación previa','1',NULL,'12',NULL,'2024-05-09 21:43:12'),(70,4,'prior_cancellation_fee',NULL,'Tarifa de Cancelación Previa','1',NULL,'13',NULL,'2024-05-09 21:43:12'),(71,4,'discount_period',NULL,'Período de Descuento','1',NULL,'14',NULL,'2024-05-09 21:43:12'),(72,4,'discount_value',NULL,'Porciento de Descuento','1',NULL,'15',NULL,'2024-05-09 21:43:12'),(74,8,'crm_status',NULL,'Estado','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(75,8,'name',NULL,'Nombre','1',NULL,'1',NULL,'2024-05-09 21:43:12'),(76,8,'father_last_name',NULL,'Apellido Paterno','1',NULL,'2',NULL,'2024-05-09 21:43:12'),(77,8,'mother_last_name',NULL,'Apellido Materno','1',NULL,'3',NULL,'2024-05-09 21:43:12'),(78,8,'phone',NULL,'Teléfono','1',NULL,'4',NULL,'2024-05-09 21:43:12'),(79,8,'action',NULL,'Acciones','1',NULL,'5',NULL,'2024-05-09 21:43:12'),(80,11,'title',NULL,'Título','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(81,11,'description',NULL,'Descripción','1',NULL,'1',NULL,'2024-05-09 21:43:12'),(82,11,'show',NULL,'Mostrar al Usuario','1',NULL,'2',NULL,'2024-05-09 21:43:12'),(83,11,'action',NULL,'Acciones','1',NULL,'3',NULL,'2024-05-09 21:43:12'),(84,12,'id','clients.id','ID','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(87,12,'name','client_main_information.name','Nombre','1',NULL,'1',NULL,'2024-06-13 16:10:40'),(88,12,'estado','client_main_information.estado','Estado','1',NULL,'4',NULL,'2024-06-13 16:10:40'),(89,12,'amount',NULL,'Saldo de la cuenta','1',NULL,'8',NULL,'2024-06-13 16:10:40'),(90,12,'nif_pasaport',NULL,'RFC/CURP','1',NULL,'9',NULL,'2024-06-13 16:10:40'),(91,12,'father_last_name','client_main_information.father_last_name','Apellido_Paterno','1',NULL,'2',NULL,'2024-06-13 16:10:40'),(92,12,'mother_last_name','client_main_information.mother_last_name','Apellido_Materno','1',NULL,'3',NULL,'2024-06-13 16:10:40'),(93,12,'phone','client_main_information.phone','Teléfono','1',NULL,'12',NULL,'2024-06-13 16:10:40'),(94,12,'phone2','client_main_information.phone2','Teléfono 2','1',NULL,'13',NULL,'2024-06-13 16:10:40'),(95,12,'type_of_billing_id','client_main_information.type_of_billing_id','Tipo de facturación','1',NULL,'14',NULL,'2024-06-13 16:10:40'),(96,12,'email','client_main_information.email','Correo','1',NULL,'15',NULL,'2024-06-13 16:10:40'),(97,12,'street','client_main_information.street','Calle','1',NULL,'16',NULL,'2024-06-13 16:10:40'),(98,12,'zip','client_main_information.zip','Código Zip','1',NULL,'17',NULL,'2024-06-13 16:10:40'),(99,12,'external_number','client_main_information.external_number','Número Exterior','1',NULL,'18',NULL,'2024-06-13 16:10:40'),(100,12,'internal_number','client_main_information.internal_number','Número Interior','1',NULL,'19',NULL,'2024-06-13 16:10:40'),(101,12,'category',NULL,'Categoría','1',NULL,'20',NULL,'2024-06-13 16:10:40'),(102,12,'modem_sn',NULL,'Modem Serie','1',NULL,'21',NULL,'2024-06-13 16:10:40'),(103,12,'gpon_ont',NULL,'GPON ONT','1',NULL,'22',NULL,'2024-06-13 16:10:40'),(104,12,'power_dbm',NULL,'Potencia dBm','1',NULL,'23',NULL,'2024-06-13 16:10:40'),(105,12,'original_password',NULL,'Contraseña Original','1',NULL,'24',NULL,'2024-06-13 16:10:40'),(107,12,'box_nomenclator',NULL,'Nomenclatura Caja','1',NULL,'26',NULL,'2024-06-13 16:10:40'),(108,12,'user_film',NULL,'Usuario Peliculas','1',NULL,'27',NULL,'2024-06-13 16:10:40'),(109,12,'password_film',NULL,'Contraseña Peliculas','1',NULL,'28',NULL,'2024-06-13 16:10:40'),(110,12,'password_wifi',NULL,'Contraseña Wifi','1',NULL,'29',NULL,'2024-06-13 16:10:40'),(111,12,'reinstatement',NULL,'Reinstalacion','1',NULL,'30',NULL,'2024-06-13 16:10:40'),(112,12,'social_id',NULL,'Social ID','1',NULL,'31',NULL,'2024-06-13 16:10:40'),(113,12,'comment',NULL,'Comentario','1',NULL,'32',NULL,'2024-06-13 16:10:40'),(114,12,'installation_on_time',NULL,'El técnico instalo en tiempo y forma','1',NULL,'33',NULL,'2024-06-13 16:10:40'),(115,12,'amount_technician_and_why',NULL,'Cual fue el monto que cobro el técnico y por qué?','1',NULL,'34',NULL,'2024-06-13 16:10:40'),(116,12,'doubt_signed_contract',NULL,'Tiene dudas acerca del contrato que esta firmando','1',NULL,'35',NULL,'2024-06-13 16:10:40'),(117,12,'technician_attencion',NULL,'El técnico le atendio con amabilidad y respeto si, no y por qué?','1',NULL,'36',NULL,'2024-06-13 16:10:40'),(118,12,'last_time_online',NULL,'Última vez online','1',NULL,'37',NULL,'2024-06-13 16:10:40'),(119,12,'billing_activated',NULL,'Facturación habilitada','1',NULL,'38',NULL,'2024-06-13 16:10:40'),(120,12,'type_billing_id',NULL,'Tipo de facturación','1',NULL,'39',NULL,'2024-06-13 16:10:40'),(121,12,'period',NULL,'Periodo','1',NULL,'40',NULL,'2024-06-13 16:10:40'),(123,12,'billing_expiration',NULL,'Vencimiento de la facturación','1',NULL,'42',NULL,'2024-06-13 16:10:41'),(124,12,'grace_period',NULL,'Periodo de desactivación','1',NULL,'43',NULL,'2024-06-13 16:10:41'),(125,12,'autopay_invoice',NULL,'Pago automático de facturas del saldo de la cuenta','1',NULL,'44',NULL,'2024-06-13 16:10:41'),(126,12,'send_financial_notification',NULL,'Enviar notificaciones de finanzas','1',NULL,'45',NULL,'2024-06-13 16:10:41'),(127,12,'partner_id',NULL,'Socios','1',NULL,'46',NULL,'2024-06-13 16:10:41'),(128,12,'state_id',NULL,'Estado','1',NULL,'47',NULL,'2024-06-13 16:10:41'),(129,12,'municipality_id',NULL,'Municipio','1',NULL,'48',NULL,'2024-06-13 16:10:41'),(130,12,'colony_id',NULL,'Colonia','1',NULL,'49',NULL,'2024-06-13 16:10:41'),(131,12,'internet_fees','networks.title','Tarifa de Internet','1',NULL,'50',NULL,'2024-06-13 16:10:41'),(132,12,'voz_fees',NULL,'Tarifa de Voz','1',NULL,'51',NULL,'2024-06-13 16:10:41'),(133,12,'custom_fees',NULL,'Tarifa de Personalizados','1',NULL,'52',NULL,'2024-06-13 16:10:41'),(134,12,'bundle_fees',NULL,'Tarifa de Paquetes','1',NULL,'53',NULL,'2024-06-13 16:10:41'),(135,12,'price',NULL,'Precio','1',NULL,'54',NULL,'2024-06-13 16:10:41'),(139,12,'ip_ranges','network_ips.ip','Rangos de Ip','1',NULL,'58',NULL,'2024-06-13 16:10:41'),(140,12,'location_id',NULL,'Ubicacion','1',NULL,'59',NULL,'2024-06-13 16:10:41'),(141,12,'router',NULL,'Router','1',NULL,'60',NULL,'2024-06-13 16:10:41'),(142,12,'redes_adicionales',NULL,'Redes Adicionales','1',NULL,'61',NULL,'2024-06-13 16:10:41'),(143,12,'ipv6',NULL,'IPv6','1',NULL,'62',NULL,'2024-06-13 16:10:41'),(144,12,'ipv6_delegada',NULL,'IPv6 Delegada','1',NULL,'63',NULL,'2024-06-13 16:10:41'),(145,12,'mac',NULL,'MAC','1',NULL,'64',NULL,'2024-06-13 16:10:41'),(146,12,'payment_method_id',NULL,'Método de Pago','1',NULL,'65',NULL,'2024-06-13 16:10:41'),(147,12,'activate_reminders',NULL,'Activar recordatorios','1',NULL,'66',NULL,'2024-06-13 16:10:41'),(148,12,'type_of_message',NULL,'Tipo de mensaje','1',NULL,'67',NULL,'2024-06-13 16:10:41'),(149,12,'reminder_1_days',NULL,'Recordatorio #1 día','1',NULL,'68',NULL,'2024-06-13 16:10:41'),(150,12,'reminder_2_days',NULL,'Recordatorio #2 día','1',NULL,'69',NULL,'2024-06-13 16:10:41'),(151,12,'reminder_3_days',NULL,'Recordatorio #3 día','1',NULL,'70',NULL,'2024-06-13 16:10:41'),(152,12,'reminder_payment_3',NULL,'Recordatorio de pago # 3','1',NULL,'71',NULL,'2024-06-13 16:10:41'),(153,12,'reminder_payment_amount',NULL,'Cantidad del pago de recordatorio','1',NULL,'72',NULL,'2024-06-13 16:10:41'),(154,12,'reminder_payment_comment',NULL,'Comentario del pago de recordatorio','1',NULL,'73',NULL,'2024-06-13 16:10:41'),(155,12,'billing_name',NULL,'Nombre de Facturación','1',NULL,'74',NULL,'2024-06-13 16:10:41'),(156,12,'billing_street',NULL,'Calle de Facturación','1',NULL,'75',NULL,'2024-06-13 16:10:41'),(157,12,'billing_zip_code',NULL,'Código Postal de la facturación','1',NULL,'76',NULL,'2024-06-13 16:10:41'),(158,12,'billing_city',NULL,'Ciudad de Facturación','1',NULL,'77',NULL,'2024-06-13 16:10:41'),(168,12,'created_at',NULL,'Última actualización','1',NULL,'87',NULL,'2024-06-13 16:10:41'),(169,12,'updated_at',NULL,'Creado','1',NULL,'88',NULL,'2024-06-13 16:10:41'),(170,12,'action',NULL,'Acciones','1',NULL,'999',NULL,'2024-09-22 16:35:55'),(171,15,'title',NULL,'Título','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(172,15,'description',NULL,'Descripción','1',NULL,'1',NULL,'2024-05-09 21:43:13'),(173,15,'show',NULL,'Mostrar al Usuario','1',NULL,'2',NULL,'2024-05-09 21:43:13'),(174,15,'action',NULL,'Acciones','1',NULL,'3',NULL,'2024-05-09 21:43:13'),(175,16,'id',NULL,'id','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(176,16,'estado',NULL,'Estado','1',NULL,'1',NULL,'2024-05-09 21:43:13'),(177,16,'description',NULL,'description','1',NULL,'2',NULL,'2024-05-09 21:43:14'),(178,16,'price',NULL,'Tarifa','1',NULL,'3',NULL,'2024-05-09 21:43:14'),(179,16,'contract_start_date',NULL,'Fecha Inicio','1',NULL,'4',NULL,'2024-05-09 21:43:14'),(180,16,'contract_end_date',NULL,'Fecha Finalización','1',NULL,'5',NULL,'2024-05-09 21:43:14'),(181,16,'action',NULL,'Acciones','1',NULL,'6',NULL,'2024-05-09 21:43:14'),(202,20,'id',NULL,'id','1',NULL,NULL,NULL,NULL),(203,20,'estado',NULL,'Estado','1',NULL,NULL,NULL,NULL),(204,20,'description',NULL,'description','1',NULL,NULL,NULL,NULL),(205,20,'price',NULL,'Tarifa','1',NULL,NULL,NULL,NULL),(206,20,'contract_start_date',NULL,'Fecha Inicio','1',NULL,NULL,NULL,NULL),(207,20,'contract_end_date',NULL,'Fecha Finalización','1',NULL,NULL,NULL,NULL),(208,20,'action',NULL,'Acciones','1',NULL,NULL,NULL,NULL),(209,25,'date',NULL,'Fecha de Pago','1',NULL,'1',NULL,'2024-06-11 12:44:54'),(210,25,'payment_method_id',NULL,'Forma de Pago','1',NULL,'2',NULL,'2024-06-11 12:44:54'),(211,25,'amount',NULL,'Cantidad','1',NULL,'3',NULL,'2024-06-11 12:44:54'),(212,25,'payment_period',NULL,'Periodo','1',NULL,'4',NULL,'2024-06-11 12:44:54'),(213,25,'comment',NULL,'Comentario','1',NULL,'5',NULL,'2024-06-11 12:44:54'),(214,25,'receipt',NULL,'Recibo','1',NULL,'6',NULL,'2024-06-11 12:44:54'),(215,25,'send_receipt_after_payment',NULL,'Enviar recivo despues del pago','1',NULL,'7',NULL,'2024-06-11 12:44:54'),(216,25,'add_by',NULL,'Agregado por','1',NULL,'8',NULL,'2024-06-11 12:44:54'),(217,25,'paymentable_id',NULL,'paymentable_id','1',NULL,'9',NULL,'2024-06-11 12:44:54'),(218,25,'paymentable_type',NULL,'paymentable_type','1',NULL,'10',NULL,'2024-06-11 12:44:54'),(219,25,'action',NULL,'Acciones','1',NULL,'11',NULL,'2024-06-11 12:44:54'),(220,26,'date',NULL,'Fecha','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(221,26,'debit',NULL,'+ Debito','1',NULL,'1',NULL,'2024-05-09 21:43:14'),(222,26,'credit',NULL,'Credito','1',NULL,'2',NULL,'2024-05-09 21:43:14'),(223,26,'account_balance',NULL,'Account Balance','1',NULL,'3',NULL,'2024-05-09 21:43:14'),(224,26,'type',NULL,'Tipo','1',NULL,'4',NULL,'2024-05-09 21:43:14'),(225,26,'description',NULL,'Descripcion','1',NULL,'5',NULL,'2024-05-09 21:43:14'),(226,26,'cantidad',NULL,'Cantidad','1',NULL,'6',NULL,'2024-05-09 21:43:14'),(227,26,'price',NULL,'Precio','1',NULL,'7',NULL,'2024-05-09 21:43:14'),(228,26,'category',NULL,'Categoria','1',NULL,'8',NULL,'2024-05-09 21:43:14'),(229,26,'client_id',NULL,'client_id','1',NULL,'9',NULL,'2024-05-09 21:43:14'),(230,26,'iva',NULL,'Iva','1',NULL,'10',NULL,'2024-05-09 21:43:14'),(231,26,'total',NULL,'Total','1',NULL,'11',NULL,'2024-05-09 21:43:14'),(232,26,'from_date',NULL,'Desde fecha','1',NULL,'12',NULL,'2024-05-09 21:43:14'),(233,26,'to_date',NULL,'Hasta Fecha','1',NULL,'13',NULL,'2024-05-09 21:43:14'),(234,26,'comment',NULL,'Comentario','1',NULL,'14',NULL,'2024-05-09 21:43:14'),(235,26,'period',NULL,'Periodo','1',NULL,'15',NULL,'2024-05-09 21:43:14'),(236,26,'add_to_invoice',NULL,'Agragar factura','1',NULL,'16',NULL,'2024-05-09 21:43:14'),(237,26,'movement',NULL,'Movimiento','1',NULL,'17',NULL,'2024-05-09 21:43:14'),(238,26,'service_name',NULL,'Servicio','1',NULL,'18',NULL,'2024-05-09 21:43:14'),(239,26,'invoice',NULL,'Factura','1',NULL,'19',NULL,'2024-05-09 21:43:14'),(240,26,'transactionable_id',NULL,'transactionable_id','1',NULL,'20',NULL,'2024-05-09 21:43:14'),(241,26,'transactionable_type',NULL,'transactionable_type','1',NULL,'21',NULL,'2024-05-09 21:43:14'),(242,26,'is_payment',NULL,'Pagada','1',NULL,'22',NULL,'2024-05-09 21:43:14'),(243,26,'payment_id',NULL,'payment_id','1',NULL,'23',NULL,'2024-05-09 21:43:14'),(244,26,'action',NULL,'Acciones','1',NULL,'24',NULL,'2024-05-09 21:43:14'),(245,28,'title',NULL,'Título','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(246,28,'type_of_nas',NULL,'Tipo de NAS','1',NULL,'1',NULL,'2024-05-09 21:43:14'),(247,28,'vendor_model',NULL,'Vendedor/Modelo','1',NULL,'2',NULL,'2024-05-09 21:43:14'),(248,28,'ip_host',NULL,'IP del Host','1',NULL,'3',NULL,'2024-05-09 21:43:14'),(249,28,'physical_address',NULL,'Dirección Físcia','1',NULL,'4',NULL,'2024-05-09 21:43:14'),(250,28,'status',NULL,'Estado','1',NULL,'5',NULL,'2024-05-09 21:43:14'),(251,28,'action',NULL,'Acciones','1',NULL,'6',NULL,'2024-05-09 21:43:14'),(252,32,'network',NULL,'Red','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(253,32,'bm',NULL,'BM','1',NULL,'1',NULL,'2024-05-09 21:43:14'),(254,32,'rootnet',NULL,'RootNet','1',NULL,'2',NULL,'2024-05-09 21:43:14'),(255,32,'used',NULL,'Usado','1',NULL,'3',NULL,'2024-05-09 21:43:14'),(256,32,'title',NULL,'Título','1',NULL,'4',NULL,'2024-05-09 21:43:14'),(257,32,'location_id',NULL,'Ubicación','1',NULL,'5',NULL,'2024-05-09 21:43:14'),(258,32,'network_type',NULL,'Tipo de Red','1',NULL,'6',NULL,'2024-05-09 21:43:14'),(259,32,'network_category',NULL,'Categoría de Red','1',NULL,'7',NULL,'2024-05-09 21:43:15'),(260,32,'action',NULL,'Acciones','1',NULL,'8',NULL,'2024-05-09 21:43:15'),(261,34,'id',NULL,'ID','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(262,34,'ip',NULL,'IP','1',NULL,'1',NULL,'2024-05-09 21:43:15'),(263,34,'used',NULL,'Utilizado','1',NULL,'2',NULL,'2024-05-09 21:43:15'),(264,34,'client_name',NULL,'Utilizado por','1',NULL,'3',NULL,'2024-05-09 21:43:15'),(265,34,'title',NULL,'Título','1',NULL,'4',NULL,'2024-05-09 21:43:15'),(266,34,'hostname',NULL,'HostName','1',NULL,'5',NULL,'2024-05-09 21:43:15'),(267,34,'location_id',NULL,'Ubicación','1',NULL,'6',NULL,'2024-05-09 21:43:15'),(268,34,'host_category',NULL,'Categoría Host','1',NULL,'7',NULL,'2024-05-09 21:43:15'),(269,34,'ping',NULL,'Ping','1',NULL,'8',NULL,'2024-05-09 21:43:15'),(270,34,'action',NULL,'Acciones','1',NULL,'9',NULL,'2024-05-09 21:43:15'),(271,37,'name',NULL,'Nombre del Socio','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(272,37,'action',NULL,'Acciones','1',NULL,'1',NULL,'2024-05-09 21:43:15'),(273,38,'name',NULL,'Ubicacion','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(274,38,'action',NULL,'Acciones','1',NULL,'1',NULL,'2024-05-09 21:43:15'),(283,42,'name',NULL,'Nombre del Socio','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(284,42,'action',NULL,'Acciones','1',NULL,'1',NULL,'2024-05-09 21:43:15'),(285,45,'id',NULL,'ID','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(286,45,'topic',NULL,'Tema','1',NULL,'1',NULL,'2024-05-09 21:43:15'),(287,45,'customer_lead',NULL,'Customer/Lead','1',NULL,'2',NULL,'2024-05-09 21:43:15'),(288,45,'priority',NULL,'Prioridad','1',NULL,'3',NULL,'2024-05-09 21:43:15'),(289,45,'estado',NULL,'Estado','1',NULL,'4',NULL,'2024-05-09 21:43:15'),(290,45,'group',NULL,'Grupo','1',NULL,'5',NULL,'2024-05-09 21:43:15'),(291,45,'type',NULL,'Tipo','1',NULL,'6',NULL,'2024-05-09 21:43:15'),(292,45,'assigned_to',NULL,'Asignado a','1',NULL,'7',NULL,'2024-05-09 21:43:15'),(293,45,'date_time',NULL,'Fecha y Hora','1',NULL,'8',NULL,'2024-05-09 21:43:15'),(294,45,'phone',NULL,'Teléfono','1',NULL,'9',NULL,'2024-05-09 21:43:15'),(295,45,'action',NULL,'Acciones','1',NULL,'10',NULL,'2024-05-09 21:43:15'),(296,47,'type',NULL,'Nombre','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(297,47,'active',NULL,'Activo','1',NULL,'1',NULL,'2024-05-09 21:43:15'),(298,47,'action',NULL,'Acciones','1',NULL,'2',NULL,'2024-05-09 21:43:15'),(299,48,'number',NULL,'Número','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(300,48,'created_at',NULL,'Fecha','1',NULL,'1',NULL,'2024-05-09 21:43:15'),(301,48,'total',NULL,'Total','1',NULL,'2',NULL,'2024-05-09 21:43:15'),(302,48,'payment_date',NULL,'Fecha de Pago','1',NULL,'3',NULL,'2024-05-09 21:43:15'),(303,48,'estado',NULL,'Estado','1',NULL,'4',NULL,'2024-05-09 21:43:15'),(304,48,'last_update',NULL,'Última Actualización','1',NULL,'5',NULL,'2024-05-09 21:43:15'),(305,48,'pay_up',NULL,'Pagar','1',NULL,'6',NULL,'2024-05-09 21:43:15'),(306,48,'use_of_transactions',NULL,'Uso de transaccion','1',NULL,'7',NULL,'2024-05-09 21:43:15'),(307,48,'note',NULL,'Nota','1',NULL,'8',NULL,'2024-05-09 21:43:15'),(308,48,'memo',NULL,'memo','1',NULL,'9',NULL,'2024-05-09 21:43:15'),(309,48,'payment',NULL,'Pagado','1',NULL,'10',NULL,'2024-05-09 21:43:15'),(310,48,'is_sent',NULL,'Enviado','1',NULL,'11',NULL,'2024-05-09 21:43:15'),(311,48,'delete_transactions',NULL,'Transaccion Eliminada','1',NULL,'12',NULL,'2024-05-09 21:43:15'),(312,48,'added_by',NULL,'Agregado por','1',NULL,'13',NULL,'2024-05-09 21:43:15'),(313,48,'type',NULL,'Tipo','1',NULL,'14',NULL,'2024-05-09 21:43:15'),(314,48,'action',NULL,'Acciones','1',NULL,'15',NULL,'2024-05-09 21:43:15'),(315,49,'name',NULL,'Nombre','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(316,49,'action',NULL,'Acciones','1',NULL,'1',NULL,'2024-05-09 21:43:15'),(317,50,'date',NULL,'Fecha','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(318,50,'debit',NULL,'+ Debito','1',NULL,'1',NULL,'2024-05-09 21:43:15'),(319,50,'credit',NULL,'Credito','1',NULL,'2',NULL,'2024-05-09 21:43:15'),(320,50,'account_balance',NULL,'Account Balance','1',NULL,'3',NULL,'2024-05-09 21:43:15'),(321,50,'type',NULL,'Tipo','1',NULL,'4',NULL,'2024-05-09 21:43:15'),(322,50,'description',NULL,'Descripcion','1',NULL,'5',NULL,'2024-05-09 21:43:15'),(323,50,'cantidad',NULL,'Cantidad','1',NULL,'6',NULL,'2024-05-09 21:43:15'),(324,50,'price',NULL,'Precio','1',NULL,'7',NULL,'2024-05-09 21:43:15'),(325,50,'action',NULL,'Acciones','1',NULL,'8',NULL,'2024-05-09 21:43:15'),(326,51,'number',NULL,'Número','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(327,51,'created_at',NULL,'Fecha','1',NULL,'1',NULL,'2024-05-09 21:43:15'),(328,51,'total',NULL,'Total','1',NULL,'2',NULL,'2024-05-09 21:43:15'),(329,51,'payment_date',NULL,'Fecha de Pago','1',NULL,'3',NULL,'2024-05-09 21:43:15'),(330,51,'estado',NULL,'Estado','1',NULL,'4',NULL,'2024-05-09 21:43:15'),(331,51,'last_update',NULL,'Última Actualización','1',NULL,'5',NULL,'2024-05-09 21:43:15'),(332,51,'pay_up',NULL,'Pagar','1',NULL,'6',NULL,'2024-05-09 21:43:15'),(333,51,'action',NULL,'Acciones','1',NULL,'7',NULL,'2024-05-09 21:43:15'),(334,52,'payment_method_id',NULL,'Forma de Pago','1',NULL,'1',NULL,'2024-06-11 12:44:54'),(335,52,'paymentable_id',NULL,'Nombre del Cliente','1',NULL,'2',NULL,'2024-06-11 12:44:54'),(336,52,'date',NULL,'Fecha de Pago','1',NULL,'3',NULL,'2024-06-11 12:44:54'),(337,52,'amount',NULL,'Suma','1',NULL,'4',NULL,'2024-06-11 12:44:54'),(338,52,'payment_period',NULL,'Periodo','1',NULL,'5',NULL,'2024-06-11 12:44:54'),(339,52,'comment',NULL,'Comentario','1',NULL,'6',NULL,'2024-06-11 12:44:54'),(340,52,'action',NULL,'Acciones','1',NULL,'8',NULL,'2024-06-11 12:44:54'),(341,54,'percent_discount',NULL,'Porciento','1',NULL,'1',NULL,'2024-12-16 13:55:41'),(342,54,'apply_group_of_months',NULL,'Meses','1',NULL,'1',NULL,'2024-05-09 21:43:16'),(343,54,'action',NULL,'Acciones','1',NULL,'2',NULL,'2024-05-09 21:43:16'),(346,55,'name',NULL,'Nombre','1',NULL,'1','2024-02-09 17:45:23','2024-12-16 13:55:41'),(347,55,'url',NULL,'Url','1',NULL,'1','2024-02-09 17:45:23','2024-05-09 21:43:16'),(348,55,'type',NULL,'Tipo','1',NULL,'2','2024-02-09 17:45:23','2024-05-09 21:43:16'),(354,57,'module_id',NULL,'Nombre del Modulo','1',NULL,'1','2024-02-15 15:01:21','2024-12-16 13:55:41'),(355,57,'name',NULL,'Nombre del campo','1',NULL,'1','2024-02-15 15:01:21','2024-05-09 21:43:16'),(356,57,'label',NULL,'Título del campo','1',NULL,'2','2024-02-15 15:01:21','2024-05-09 21:43:16'),(357,57,'type_field',NULL,'Tipo de Campo','1',NULL,'3','2024-02-15 15:01:21','2024-05-09 21:43:16'),(358,57,'position',NULL,'Posición','1',NULL,'4','2024-02-15 15:01:21','2024-05-09 21:43:16'),(359,57,'include',NULL,'Mostrar','1',NULL,'5','2024-02-15 15:01:21','2024-05-09 21:43:16'),(360,57,'action',NULL,'Acciones','1',NULL,'6','2024-02-15 15:01:21','2024-05-09 21:43:16'),(362,12,'password',NULL,'Contraseña','1',NULL,'90','2024-03-15 06:58:06','2024-06-13 16:10:41'),(363,58,'module_id',NULL,'Modulo','1',NULL,'1','2024-03-21 12:08:44','2024-12-16 13:55:41'),(364,58,'file',NULL,'Archivo','1',NULL,'1','2024-03-21 12:08:44','2024-05-09 21:43:16'),(365,58,'created_by',NULL,'Importado por','1',NULL,'2','2024-03-21 12:08:44','2024-05-09 21:43:16'),(366,58,'action',NULL,'Acciones','1',NULL,'3','2024-03-21 12:08:44','2024-05-09 21:43:16'),(367,4,'associated_clients',NULL,'Clientes asociados','1',NULL,'16','2024-04-06 23:26:40','2024-05-09 21:43:12'),(368,4,'action',NULL,'Acciones','1',NULL,'17','2024-04-06 23:26:40','2024-05-09 21:43:12'),(369,18,'id',NULL,'Id','1',NULL,'1','2024-04-15 13:28:40','2024-12-16 13:55:41'),(370,18,'service_name',NULL,'Nombre del Servicio','1',NULL,'1','2024-04-15 13:28:40','2024-05-09 21:43:14'),(371,18,'estado',NULL,'Estado','1',NULL,'2','2024-04-15 13:28:40','2024-05-09 21:43:14'),(372,18,'voz_id',NULL,'Tarifa','1',NULL,'3','2024-04-15 13:28:40','2024-05-09 21:43:14'),(373,18,'phone',NULL,'Teléfono','1',NULL,'4','2024-04-15 13:28:40','2024-05-09 21:43:14'),(374,18,'direction',NULL,'Dirección','1',NULL,'5','2024-04-15 13:28:40','2024-05-09 21:43:14'),(375,18,'start_date',NULL,'Fecha de Inicio','1',NULL,'6','2024-04-15 13:28:40','2024-05-09 21:43:14'),(376,18,'finish_date',NULL,'Fecha de Finalización','1',NULL,'7','2024-04-15 13:28:40','2024-05-09 21:43:14'),(377,18,'action',NULL,'Acciones','1',NULL,'8','2024-04-15 13:28:40','2024-05-09 21:43:14'),(378,19,'id',NULL,'Id','1',NULL,'1','2024-04-15 13:28:40','2024-12-16 13:55:41'),(379,19,'service_name',NULL,'Nombre del Servicio','1',NULL,'1','2024-04-15 13:28:40','2024-05-09 21:43:14'),(380,19,'estado',NULL,'Estado','1',NULL,'2','2024-04-15 13:28:40','2024-05-09 21:43:14'),(381,19,'ip',NULL,'Ip','1',NULL,'3','2024-04-15 13:28:40','2024-05-09 21:43:14'),(382,19,'user',NULL,'Usuario','1',NULL,'4','2024-04-15 13:28:40','2024-05-09 21:43:14'),(383,19,'password',NULL,'Contraseña','1',NULL,'5','2024-04-15 13:28:40','2024-05-09 21:43:14'),(384,19,'start_date',NULL,'Fecha de Inicio','1',NULL,'6','2024-04-15 13:28:40','2024-05-09 21:43:14'),(385,19,'finish_date',NULL,'Fecha de Finalización','1',NULL,'7','2024-04-15 13:28:40','2024-05-09 21:43:14'),(386,19,'action',NULL,'Acciones','1',NULL,'8','2024-04-15 13:28:40','2024-05-09 21:43:14'),(387,17,'id',NULL,'Id','1',NULL,'1','2024-04-15 13:28:40','2024-12-16 13:55:41'),(388,17,'service_name',NULL,'Nombre del Servicio','1',NULL,'1','2024-04-15 13:28:40','2024-05-09 21:43:14'),(389,17,'estado',NULL,'Estado','1',NULL,'2','2024-04-15 13:28:40','2024-05-09 21:43:14'),(390,17,'internet_id',NULL,'Tarifa','1',NULL,'3','2024-04-15 13:28:40','2024-05-09 21:43:14'),(391,17,'client_name',NULL,'Usuario secret','1',NULL,'4','2024-04-15 13:28:40','2024-10-29 15:51:26'),(392,17,'password',NULL,'Contraseña','1',NULL,'5','2024-04-15 13:28:40','2024-05-09 21:43:14'),(393,17,'ip',NULL,'Ip','1',NULL,'6','2024-04-15 13:28:40','2024-05-09 21:43:14'),(394,17,'start_date',NULL,'Fecha de Inicio','1',NULL,'7','2024-04-15 13:28:40','2024-05-09 21:43:14'),(395,17,'finish_date',NULL,'Fecha de Finalización','1',NULL,'8','2024-04-15 13:28:40','2024-05-09 21:43:14'),(396,17,'action',NULL,'Acciones','1',NULL,'9','2024-04-15 13:28:40','2024-05-09 21:43:14'),(397,39,'id',NULL,'Id','1',NULL,'1','2024-04-16 15:32:34','2024-12-16 13:55:41'),(398,39,'name',NULL,'Nombre','1',NULL,'1','2024-04-16 15:32:34','2024-05-09 21:43:15'),(399,39,'action',NULL,'Acciones','1',NULL,'2','2024-04-16 15:32:34','2024-05-09 21:43:15'),(400,40,'id',NULL,'Id','1',NULL,'1','2024-04-16 15:32:34','2024-12-16 13:55:41'),(401,40,'name',NULL,'Nombre','1',NULL,'1','2024-04-16 15:32:34','2024-05-09 21:43:15'),(402,40,'state_id',NULL,'Estado','1',NULL,'2','2024-04-16 15:32:34','2024-05-09 21:43:15'),(403,40,'action',NULL,'Acciones','1',NULL,'3','2024-04-16 15:32:34','2024-05-09 21:43:15'),(404,41,'id',NULL,'Id','1',NULL,'1','2024-04-16 15:32:34','2024-12-16 13:55:41'),(405,41,'name',NULL,'Nombre','1',NULL,'1','2024-04-16 15:32:34','2024-05-09 21:43:15'),(406,41,'municipality_id',NULL,'Municipio','1',NULL,'2','2024-04-16 15:32:34','2024-05-09 21:43:15'),(407,41,'action',NULL,'Acciones','1',NULL,'3','2024-04-16 15:32:35','2024-05-09 21:43:15'),(408,52,'add_by',NULL,'Agregado por','1',NULL,'7','2024-06-08 15:54:25','2024-06-11 12:44:54'),(409,52,'id',NULL,'Id','1',NULL,'1','2024-06-11 12:44:54','2024-12-16 13:55:41'),(410,25,'id',NULL,'Id','1',NULL,'1','2024-06-11 12:44:54','2024-12-16 13:55:41'),(411,12,'fecha_corte','clients.fecha_corte','Fecha de Corte','1',NULL,'91','2024-06-13 11:06:19','2024-06-13 11:06:19'),(413,12,'billing_date','billing_configurations.billing_date','Día de facturación','1',NULL,'92','2024-06-20 14:33:32','2024-06-26 16:57:03'),(414,12,'last_payment',NULL,'Fecha de Ultimo Pago','1',NULL,'93','2024-06-20 14:33:32','2024-06-20 14:33:32'),(415,60,'log_name',NULL,'Nombre','1',NULL,'1','2024-07-15 18:13:30','2024-07-15 18:13:30'),(416,60,'description',NULL,'Descripcion','1',NULL,'2','2024-07-15 18:13:30','2024-07-15 18:13:30'),(417,60,'subject_type',NULL,'Subject Type','1',NULL,'3','2024-07-15 18:13:30','2024-07-15 18:13:30'),(418,60,'event',NULL,'Evento','1',NULL,'4','2024-07-15 18:13:30','2024-07-15 18:13:30'),(419,60,'causer_type',NULL,'Modelo','1',NULL,'5','2024-07-15 18:13:30','2024-07-17 14:55:58'),(420,60,'properties',NULL,'Propiedades','1',NULL,'6','2024-07-15 18:13:30','2024-07-15 18:13:30'),(422,60,'id',NULL,'Id','1',NULL,'1','2024-07-17 14:55:57','2024-12-16 13:55:41'),(423,60,'created_at',NULL,'Fecha de Creación','1',NULL,'8','2024-07-17 14:55:58','2024-07-17 14:55:58'),(424,60,'causer_id',NULL,'Creado Por','1',NULL,'7','2024-07-17 14:55:58','2024-07-17 14:55:58'),(425,61,'name',NULL,'Nombre','1',NULL,'1','2024-07-25 09:21:43','2024-07-25 09:21:43'),(426,61,'html',NULL,'Html','1',NULL,'2','2024-07-25 09:21:43','2024-07-25 09:21:43'),(427,61,'action',NULL,'Acciones','1',NULL,'999','2024-07-25 09:21:43','2024-07-29 12:36:39'),(428,61,'type',NULL,'Tipo','1',NULL,'3','2024-07-29 12:36:39','2024-07-29 12:36:39'),(429,63,'name',NULL,'Tipo de Platilla','1',NULL,'1','2024-07-29 12:36:39','2024-07-29 12:36:39'),(430,63,'action',NULL,'Acciones','1',NULL,'999','2024-07-29 12:36:40','2024-07-29 12:36:40'),(431,64,'title',NULL,'Título','1',NULL,'1','2024-08-04 09:43:36','2024-08-04 09:43:36'),(432,64,'task_status_new',NULL,'Nuevo','1',NULL,'2','2024-08-04 09:43:36','2024-08-04 09:43:36'),(433,64,'task_status_in_progress',NULL,'En Progreso','1',NULL,'3','2024-08-04 09:43:36','2024-08-04 09:43:36'),(434,64,'task_status_in_completed',NULL,'Completado','1',NULL,'4','2024-08-04 09:43:36','2024-08-04 09:43:36'),(435,64,'action',NULL,'Acciones','1',NULL,'999','2024-08-04 09:43:36','2024-08-04 09:43:36'),(436,65,'id',NULL,'ID','1',NULL,'1','2024-08-09 16:43:14','2024-08-09 16:43:14'),(437,65,'title',NULL,'Título','1',NULL,'2','2024-08-09 16:43:14','2024-08-09 16:43:14'),(438,65,'workflow',NULL,'Flujo de Trabajo','1',NULL,'3','2024-08-09 16:43:14','2024-08-09 16:43:14'),(439,65,'project_id',NULL,'Proyecto','1',NULL,'4','2024-08-09 16:43:14','2024-08-09 16:43:14'),(440,65,'action',NULL,'Acciones','1',NULL,'999','2024-08-09 16:43:14','2024-08-09 16:43:14'),(441,12,'seller_id','client_main_information.seller_id','Vendedor','1',NULL,'25','2024-08-09 16:43:15','2024-08-09 16:43:15'),(442,68,'id',NULL,'ID','1',NULL,'1','2024-08-20 20:47:41','2024-08-20 20:47:41'),(443,68,'name',NULL,'Nombre','1',NULL,'2','2024-08-20 20:47:41','2024-08-20 20:47:41'),(444,68,'action',NULL,'Acciones','1',NULL,'999','2024-08-20 20:47:41','2024-08-20 20:47:41'),(445,65,'status',NULL,'Estado','1',NULL,'5','2024-08-26 10:06:20','2024-08-26 10:06:20'),(446,65,'created_at',NULL,'Fecha de Creacion','1',NULL,'11','2024-08-26 10:06:20','2024-09-21 07:21:44'),(447,69,'id',NULL,'ID','1',NULL,'1','2024-09-02 10:03:18','2024-09-02 10:03:18'),(448,69,'name',NULL,'Nombre','1',NULL,'2','2024-09-02 10:03:18','2024-09-02 10:03:18'),(449,69,'action',NULL,'Acciones','1',NULL,'999','2024-09-02 10:03:18','2024-09-02 10:03:18'),(450,70,'id',NULL,'ID','1',NULL,'1','2024-09-16 11:25:00','2024-09-16 11:25:00'),(451,70,'title_template',NULL,'Título de la Plantilla','1',NULL,'2','2024-09-16 11:25:00','2024-09-16 11:25:00'),(452,70,'action',NULL,'Acciones','1',NULL,'999','2024-09-16 11:25:00','2024-09-16 11:25:00'),(458,65,'start_time',NULL,'Fecha de Inicio','1',NULL,'6','2024-09-21 07:21:44','2024-09-21 07:21:44'),(459,65,'assigned_to',NULL,'Asignado a','1',NULL,'7','2024-09-21 07:21:44','2024-09-21 07:21:44'),(460,65,'priority',NULL,'Prioridad','1',NULL,'8','2024-09-21 07:21:44','2024-09-21 07:21:44'),(461,65,'estimated_time',NULL,'Tiempo estimado','1',NULL,'9','2024-09-21 07:21:44','2024-09-21 07:21:44'),(462,65,'dedicated_time',NULL,'Tiempo Dedicado','1',NULL,'10','2024-09-21 07:21:44','2024-09-21 07:21:44'),(463,71,'id',NULL,'ID','1',NULL,'1','2024-09-22 16:35:53','2024-09-22 16:35:53'),(464,71,'name',NULL,'Nombre','1',NULL,'2','2024-09-22 16:35:53','2024-09-22 16:35:53'),(465,71,'action',NULL,'Acciones','1',NULL,'999','2024-09-22 16:35:53','2024-09-22 16:35:53'),(466,72,'id',NULL,'ID','1',NULL,'1','2024-09-22 16:35:53','2024-09-22 16:35:53'),(467,72,'name',NULL,'Nombre','1',NULL,'2','2024-09-22 16:35:53','2024-09-22 16:35:53'),(468,72,'action',NULL,'Acciones','1',NULL,'999','2024-09-22 16:35:53','2024-09-22 16:35:53'),(469,73,'id',NULL,'ID','1',NULL,'1','2024-09-22 16:35:54','2024-09-22 16:35:54'),(470,73,'name',NULL,'Nombre','1',NULL,'2','2024-09-22 16:35:54','2024-09-22 16:35:54'),(471,73,'action',NULL,'Acciones','1',NULL,'999','2024-09-22 16:35:54','2024-09-22 16:35:54'),(472,74,'id',NULL,'ID','1',NULL,'1','2024-09-22 16:35:54','2024-09-22 16:35:54'),(473,74,'name',NULL,'Nombre','1',NULL,'2','2024-09-22 16:35:54','2024-09-22 16:35:54'),(474,74,'client_id',NULL,'Cliente','1',NULL,'3','2024-09-22 16:35:54','2024-09-22 16:35:54'),(475,74,'action',NULL,'Acciones','1',NULL,'999','2024-09-22 16:35:54','2024-09-22 16:35:54'),(476,12,'nomenclature_name','nomenclatures.name','Nomenclatura','1',NULL,'94','2024-09-22 16:35:55','2024-09-22 16:35:55'),(477,76,'id',NULL,'ID','1',NULL,'1','2024-09-22 16:35:55','2024-09-22 16:35:55'),(478,76,'name',NULL,'Nombre','1',NULL,'2','2024-09-22 16:35:55','2024-09-22 16:35:55'),(479,76,'action',NULL,'Acciones','1',NULL,'999','2024-09-22 16:35:55','2024-09-22 16:35:55'),(481,12,'service_user_name','client_internet_services.user','Usuario secret','1',NULL,'93','2024-09-27 16:07:59','2024-10-29 15:51:26'),(482,77,'id',NULL,'ID','1',NULL,'1','2024-09-28 01:13:29','2024-09-28 01:13:29'),(483,77,'serviceable_id',NULL,'Sercicio de Internet Id','1',NULL,'2','2024-09-28 01:13:29','2024-09-28 01:13:29'),(484,77,'client_id',NULL,'Cliente','1',NULL,'3','2024-09-28 01:13:29','2024-09-28 01:13:29'),(485,77,'action',NULL,'Acciones','1',NULL,'999','2024-09-28 01:13:29','2024-09-28 01:13:29'),(486,77,'ip',NULL,'IP','1',NULL,'4','2024-09-29 16:10:31','2024-09-29 16:10:31'),(488,65,'description',NULL,'Descripcion','1',NULL,'3','2024-09-29 16:26:06','2024-09-29 16:26:06'),(489,65,'address',NULL,'Dirección','1',NULL,'12','2024-09-30 20:16:10','2024-09-30 20:16:10'),(490,12,'address','client_main_information.address','Dirección completa','1',NULL,'95','2024-11-11 11:39:34','2024-11-11 11:39:34'),(491,1,'associated_clients',NULL,'Clientes asociados','1',NULL,'20','2024-11-12 11:26:27','2024-11-12 11:26:27'),(492,3,'associated_clients',NULL,'Clientes asociados','1',NULL,'20','2024-11-12 11:26:27','2024-11-12 11:26:27'),(493,2,'associated_clients',NULL,'Clientes asociados','1',NULL,'20','2024-11-12 11:26:27','2024-11-12 11:26:27'),(494,76,'users',NULL,'Integrantes','1',NULL,'3','2024-12-09 08:14:29','2024-12-09 08:14:29'),(495,12,'fecha_pago','clients.fecha_pago','Proximo Pago','1',NULL,'95','2024-12-14 04:31:09','2024-12-14 04:31:09'),(496,12,'fecha_fin_periodo_gracia','clients.fecha_fin_periodo_gracia','Fecha Fin de Periodo de Gracia','1',NULL,'96','2024-12-14 04:31:09','2024-12-14 04:31:09'),(497,1,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(498,2,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(499,3,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(500,4,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(501,5,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(502,6,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(503,7,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(504,8,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(505,9,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(506,10,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(507,11,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(508,13,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(509,14,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(510,15,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(511,21,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(512,22,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(513,23,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(514,24,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(515,26,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(516,27,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(517,28,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(518,29,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(519,30,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(520,31,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(521,32,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(522,33,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(523,35,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(524,36,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(525,37,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(526,38,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(527,42,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(528,43,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(529,44,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(530,46,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(531,47,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(532,48,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(533,49,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(534,50,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(535,51,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(536,53,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(537,54,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(538,55,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(539,56,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(540,57,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(541,58,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(542,61,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(543,62,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(544,63,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(545,64,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(546,66,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(547,67,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(548,75,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(549,78,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41'),(550,79,'id',NULL,'ID','1',NULL,'0','2024-12-16 13:55:41','2024-12-16 13:55:41');
/*!40000 ALTER TABLE `column_datatable_modules` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `column_datatable_modules` with 488 row(s)
--

--
-- Table structure for table `command_configs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `command_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `command` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `process_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `frequency_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `execution_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `command_description` longtext COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `command_configs`
--

LOCK TABLES `command_configs` WRITE;
/*!40000 ALTER TABLE `command_configs` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `command_configs` VALUES (14,'Suspender Servicio','suspends_services_early_in_the_day:process','1','10:00','Suspende los servicios que el tiempo de expiracion es hoy o ya paso y no tienen periodo de gracia a la hora que este programada',1,NULL,'2024-06-19 15:39:34'),(15,'Cobrar Servicios','billing_service_command:process','1','00:03','Cobra los servicios que No están pagados, tienen dinero en la cuenta y el dia de facturación es hoy o ya pasó, los activa y crea una factura',1,NULL,'2024-06-19 15:40:26'),(27,'Slavar Base De Datos','backup_db:process','1','00:05','Backup diaria de la base de datos',1,'2024-06-19 17:29:57','2024-06-19 17:29:57'),(29,'Actualizar Fechas Pasadas','update_task_old_command:process','1','07:25','Actualiza la Fecha de las Tareas que ha pasado la fecha de ejecución',1,'2024-09-30 20:16:10','2024-12-09 08:11:59'),(30,'Chequear Conexion Con el Mikrotik','check_conection_mikrotik:process','6',NULL,'Chequea la conexion con el mikrotik y envia notificacion a los administradores',1,'2024-10-10 15:41:12','2024-10-10 15:41:12');
/*!40000 ALTER TABLE `command_configs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `command_configs` with 5 row(s)
--

--
-- Table structure for table `company_information`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_information` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_street` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_external_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_internal_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_postal_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colony_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipality_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atention_client_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rfc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iva` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cominion_partner` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_portal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company_information`
--

LOCK TABLES `company_information` WRITE;
/*!40000 ALTER TABLE `company_information` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `company_information` VALUES (1,'Meganet Telecomunicaciones',NULL,NULL,NULL,'55796','México','70539','15','763','soporte@meganett.com.mx','5542106277','MTE1709083F3','16%','SANTANDER','014180655063756953',NULL,NULL,'1',NULL,NULL,NULL,NULL,NULL,'2024-11-23 18:58:55');
/*!40000 ALTER TABLE `company_information` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `company_information` with 1 row(s)
--

--
-- Table structure for table `crud_packages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crud_packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint(20) NOT NULL,
  `crud_package_id` bigint(20) NOT NULL,
  `crud_package_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crud_packages`
--

LOCK TABLES `crud_packages` WRITE;
/*!40000 ALTER TABLE `crud_packages` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `crud_packages` VALUES (1,1,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(2,2,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(3,3,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(4,7,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(5,8,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(6,9,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(7,10,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(8,11,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(9,12,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(10,13,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(11,14,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(12,15,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(13,16,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(14,17,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(15,18,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(16,19,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(17,4,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(18,5,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(19,21,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(20,22,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(21,23,1,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(22,1,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(23,2,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(24,3,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(25,7,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(26,8,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(27,9,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(28,10,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(29,11,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(30,12,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(31,13,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(32,14,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(33,15,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(34,16,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(35,17,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(36,18,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(37,19,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(38,4,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(39,5,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(40,21,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(41,22,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(42,23,2,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(43,1,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(44,2,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(45,3,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(46,7,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(47,8,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(48,9,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(49,10,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(50,11,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(51,12,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(52,13,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(53,14,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(54,15,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(55,16,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(56,17,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(57,18,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(58,19,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(59,4,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(60,5,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(61,21,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(62,22,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(63,23,3,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(64,1,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(65,2,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(66,3,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(67,7,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(68,8,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(69,9,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(70,10,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(71,11,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(72,12,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(73,13,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(74,14,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(75,15,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(76,16,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(77,17,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(78,18,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(79,19,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(80,4,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(81,5,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(82,21,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(83,22,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(84,23,4,'App\\Models\\Module','2024-02-09 17:43:46','2024-02-09 17:43:46'),(85,1,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(86,2,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(87,3,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(88,7,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(89,8,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(90,9,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(91,10,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(92,11,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(93,12,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(94,13,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(95,14,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(96,15,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(97,16,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(98,17,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(99,18,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(100,19,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(101,4,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(102,5,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(103,21,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(104,22,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(105,23,8,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(106,1,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(107,2,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(108,3,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(109,7,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(110,8,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(111,9,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(112,10,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(113,11,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(114,12,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(115,13,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(116,14,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(117,15,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(118,16,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(119,17,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(120,18,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(121,19,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(122,4,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(123,5,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(124,21,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(125,22,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(126,23,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(127,20,12,'App\\Models\\Module','2024-02-09 17:43:47','2024-02-09 17:43:47'),(128,1,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(129,2,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(130,3,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(131,7,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(132,8,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(133,9,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(134,10,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(135,11,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(136,12,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(137,13,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(138,14,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(139,15,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(140,16,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(141,17,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(142,18,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(143,19,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(144,4,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(145,5,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(146,21,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(147,22,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(148,23,28,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(149,1,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(150,2,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(151,3,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(152,7,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(153,8,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(154,9,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(155,10,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(156,11,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(157,12,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(158,13,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(159,14,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(160,15,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(161,16,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(162,17,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(163,18,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(164,19,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(165,4,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(166,5,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(167,21,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(168,22,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(169,23,29,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(170,1,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(171,2,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(172,3,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(173,7,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(174,8,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(175,9,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(176,10,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(177,11,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(178,12,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(179,13,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(180,14,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(181,15,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(182,16,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(183,17,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(184,18,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(185,19,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(186,4,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(187,5,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(188,21,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(189,22,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(190,23,30,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(191,1,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(192,2,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(193,3,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(194,7,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(195,8,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(196,9,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(197,10,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(198,11,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(199,12,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(200,13,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(201,14,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(202,15,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(203,16,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(204,17,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(205,18,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(206,19,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(207,4,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(208,5,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(209,21,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(210,22,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(211,23,32,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(212,1,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(213,2,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(214,3,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(215,7,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(216,8,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(217,9,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(218,10,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(219,11,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(220,12,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(221,13,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(222,14,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(223,15,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(224,16,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(225,17,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(226,18,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(227,19,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(228,4,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(229,5,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(230,21,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(231,22,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(232,23,33,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(233,1,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(234,2,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(235,3,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(236,7,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(237,8,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(238,9,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(239,10,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(240,11,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(241,12,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(242,13,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(243,14,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(244,15,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(245,16,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(246,17,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(247,18,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(248,19,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(249,4,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(250,5,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(251,21,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(252,22,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(253,23,34,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(254,1,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(255,2,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(256,3,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(257,7,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(258,8,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(259,9,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(260,10,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(261,11,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(262,12,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(263,13,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(264,14,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(265,15,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(266,16,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(267,17,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(268,18,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(269,19,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(270,4,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(271,5,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(272,21,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(273,22,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(274,23,35,'App\\Models\\Module','2024-02-09 17:43:49','2024-02-09 17:43:49'),(275,1,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(276,2,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(277,3,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(278,7,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(279,8,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(280,9,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(281,10,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(282,11,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(283,12,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(284,13,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(285,14,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(286,15,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(287,16,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(288,17,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(289,18,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(290,19,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(291,4,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(292,5,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(293,21,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(294,22,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(295,23,36,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(296,1,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(297,2,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(298,3,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(299,7,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(300,8,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(301,9,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(302,10,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(303,11,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(304,12,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(305,13,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(306,14,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(307,15,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(308,16,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(309,17,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(310,18,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(311,19,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(312,4,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(313,5,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(314,21,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(315,22,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(316,23,37,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(317,1,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(318,2,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(319,3,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(320,7,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(321,8,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(322,9,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(323,10,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(324,11,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(325,12,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(326,13,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(327,14,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(328,15,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(329,16,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(330,17,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(331,18,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(332,19,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(333,4,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(334,5,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(335,21,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(336,22,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(337,23,38,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(338,1,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(339,2,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(340,3,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(341,7,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(342,8,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(343,9,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(344,10,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(345,11,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(346,12,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(347,13,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(348,14,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(349,15,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(350,16,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(351,17,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(352,18,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(353,19,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(354,4,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(355,5,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(356,21,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(357,22,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(358,23,39,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(359,1,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(360,2,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(361,3,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(362,7,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(363,8,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(364,9,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(365,10,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(366,11,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(367,12,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(368,13,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(369,14,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(370,15,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(371,16,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(372,17,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(373,18,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(374,19,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(375,4,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(376,5,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(377,21,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(378,22,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(379,23,40,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(380,1,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(381,2,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(382,3,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(383,7,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(384,8,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(385,9,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(386,10,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(387,11,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(388,12,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(389,13,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(390,14,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(391,15,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(392,16,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(393,17,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(394,18,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(395,19,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(396,4,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(397,5,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(398,21,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(399,22,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(400,23,41,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(401,1,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(402,2,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(403,3,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(404,7,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(405,8,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(406,9,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(407,10,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(408,11,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(409,12,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(410,13,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(411,14,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(412,15,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(413,16,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(414,17,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(415,18,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(416,19,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(417,4,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(418,5,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(419,21,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(420,22,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(421,23,42,'App\\Models\\Module','2024-02-09 17:43:50','2024-02-09 17:43:50'),(422,1,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(423,2,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(424,3,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(425,7,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(426,8,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(427,9,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(428,10,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(429,11,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(430,12,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(431,13,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(432,14,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(433,15,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(434,16,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(435,17,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(436,18,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(437,19,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(438,4,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(439,5,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(440,21,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(441,22,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(442,23,43,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(443,1,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(444,2,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(445,3,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(446,7,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(447,8,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(448,9,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(449,10,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(450,11,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(451,12,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(452,13,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(453,14,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(454,15,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(455,16,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(456,17,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(457,18,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(458,19,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(459,4,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(460,5,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(461,21,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(462,22,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(463,23,45,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(464,1,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(465,2,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(466,3,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(467,7,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(468,8,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(469,9,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(470,10,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(471,11,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(472,12,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(473,13,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(474,14,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(475,15,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(476,16,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(477,17,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(478,18,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(479,19,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(480,4,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(481,5,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(482,21,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(483,22,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(484,23,46,'App\\Models\\Module','2024-02-09 17:43:51','2024-02-09 17:43:51'),(485,1,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(486,2,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(487,3,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(488,7,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(489,8,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(490,9,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(491,10,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(492,11,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(493,12,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(494,13,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(495,14,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(496,15,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(497,16,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(498,17,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(499,18,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(500,19,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(501,4,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(502,5,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(503,21,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(504,22,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(505,23,47,'App\\Models\\Module','2024-02-09 17:43:57','2024-02-09 17:43:57'),(506,1,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(507,2,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(508,3,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(509,7,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(510,8,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(511,9,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(512,10,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(513,11,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(514,12,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(515,13,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(516,14,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(517,15,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(518,16,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(519,17,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(520,18,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(521,19,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(522,4,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(523,5,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(524,21,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(525,22,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(526,23,48,'App\\Models\\Module','2024-02-09 17:43:58','2024-02-09 17:43:58'),(527,1,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(528,2,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(529,3,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(530,7,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(531,8,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(532,9,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(533,10,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(534,11,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(535,12,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(536,13,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(537,14,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(538,15,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(539,16,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(540,17,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(541,18,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(542,19,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(543,4,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(544,5,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(545,21,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(546,22,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(547,23,49,'App\\Models\\Module','2024-02-09 17:43:59','2024-02-09 17:43:59'),(548,1,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(549,2,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(550,3,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(551,7,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(552,8,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(553,9,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(554,10,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(555,11,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(556,12,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(557,13,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(558,14,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(559,15,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(560,16,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(561,17,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(562,18,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(563,19,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(564,4,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(565,5,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(566,21,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(567,22,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(568,23,50,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(569,1,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(570,2,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(571,3,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(572,7,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(573,8,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(574,9,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(575,10,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(576,11,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(577,12,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(578,13,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(579,14,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(580,15,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(581,16,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(582,17,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(583,18,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(584,19,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(585,4,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(586,5,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(587,21,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(588,22,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(589,23,51,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(590,1,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(591,2,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(592,3,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(593,7,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(594,8,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(595,9,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(596,10,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(597,11,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(598,12,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(599,13,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(600,14,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(601,15,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(602,16,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(603,17,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(604,18,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(605,19,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(606,4,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(607,5,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(608,21,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(609,22,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(610,23,52,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(611,1,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(612,2,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(613,3,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(614,7,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(615,8,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(616,9,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(617,10,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(618,11,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(619,12,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(620,13,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(621,14,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(622,15,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(623,16,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(624,17,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(625,18,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(626,19,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(627,4,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(628,5,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(629,21,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(630,22,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(631,23,53,'App\\Models\\Module','2024-02-09 17:44:00','2024-02-09 17:44:00'),(632,1,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(633,2,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(634,3,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(635,7,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(636,8,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(637,9,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(638,10,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(639,11,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(640,12,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(641,13,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(642,14,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(643,15,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(644,16,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(645,17,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(646,18,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(647,19,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(648,4,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(649,5,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(650,21,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(651,22,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(652,23,54,'App\\Models\\Module','2024-02-09 17:44:01','2024-02-09 17:44:01'),(653,24,12,'App\\Models\\Module','2024-02-09 17:44:02','2024-02-09 17:44:02'),(654,25,12,'App\\Models\\Module','2024-02-09 17:44:02','2024-02-09 17:44:02'),(655,1,55,'App\\Models\\Module','2024-02-09 17:45:23','2024-02-09 17:45:23'),(656,2,55,'App\\Models\\Module','2024-02-09 17:45:23','2024-02-09 17:45:23'),(657,3,55,'App\\Models\\Module','2024-02-09 17:45:23','2024-02-09 17:45:23'),(658,7,55,'App\\Models\\Module','2024-02-09 17:45:23','2024-02-09 17:45:23'),(659,8,55,'App\\Models\\Module','2024-02-09 17:45:23','2024-02-09 17:45:23'),(660,9,55,'App\\Models\\Module','2024-02-09 17:45:23','2024-02-09 17:45:23'),(661,10,55,'App\\Models\\Module','2024-02-09 17:45:23','2024-02-09 17:45:23'),(662,11,55,'App\\Models\\Module','2024-02-09 17:45:23','2024-02-09 17:45:23'),(663,12,55,'App\\Models\\Module','2024-02-09 17:45:23','2024-02-09 17:45:23'),(664,13,55,'App\\Models\\Module','2024-02-09 17:45:23','2024-02-09 17:45:23'),(665,14,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(666,15,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(667,16,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(668,17,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(669,18,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(670,19,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(671,4,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(672,5,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(673,21,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(674,22,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(675,23,55,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(676,26,8,'App\\Models\\Module','2024-02-09 17:45:24','2024-02-09 17:45:24'),(677,26,12,'App\\Models\\Module','2024-02-09 17:45:26','2024-02-09 17:45:26'),(678,1,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(679,2,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(680,3,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(681,7,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(682,8,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(683,9,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(684,10,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(685,11,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(686,12,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(687,13,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(688,14,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(689,15,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(690,16,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(691,17,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(692,18,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(693,19,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(694,4,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(695,5,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(696,21,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(697,22,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(698,23,57,'App\\Models\\Module','2024-02-21 06:26:20','2024-02-21 06:26:20'),(699,1,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(700,2,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(701,3,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(702,7,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(703,8,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(704,9,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(705,10,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(706,11,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(707,12,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(708,13,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(709,14,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(710,15,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(711,16,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(712,17,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(713,18,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(714,19,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(715,4,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(716,5,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(717,21,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(718,22,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(719,23,58,'App\\Models\\Module','2024-03-26 12:26:52','2024-03-26 12:26:52'),(720,1,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(721,2,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(722,3,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(723,7,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(724,8,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(725,9,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(726,10,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(727,11,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(728,12,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(729,13,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(730,14,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(731,15,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(732,16,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(733,17,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(734,18,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(735,19,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(736,4,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(737,5,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(738,21,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(739,22,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(740,23,59,'App\\Models\\Module','2024-07-12 15:53:08','2024-07-12 15:53:08'),(741,1,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(742,2,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(743,3,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(744,7,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(745,8,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(746,9,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(747,10,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(748,11,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(749,12,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(750,13,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(751,14,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(752,15,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(753,16,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(754,17,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(755,18,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(756,19,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(757,4,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(758,5,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(759,21,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(760,22,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(761,23,64,'App\\Models\\Module','2024-08-04 09:43:36','2024-08-04 09:43:36'),(762,1,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(763,2,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(764,3,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(765,7,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(766,8,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(767,9,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(768,10,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(769,11,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(770,12,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(771,13,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(772,14,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(773,15,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(774,16,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(775,17,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(776,18,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(777,19,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(778,4,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(779,5,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(780,21,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(781,22,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(782,23,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(783,26,65,'App\\Models\\Module','2024-08-09 16:43:14','2024-08-09 16:43:14'),(784,1,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(785,2,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(786,3,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(787,7,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(788,8,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(789,9,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(790,10,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(791,11,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(792,12,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(793,13,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(794,14,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(795,15,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(796,16,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(797,17,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(798,18,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(799,19,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(800,4,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(801,5,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(802,21,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(803,22,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(804,23,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(805,26,66,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(806,1,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(807,2,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(808,3,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(809,7,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(810,8,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(811,9,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(812,10,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(813,11,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(814,12,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(815,13,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(816,14,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(817,15,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(818,16,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(819,17,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(820,18,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(821,19,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(822,4,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(823,5,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(824,21,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(825,22,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(826,23,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(827,26,67,'App\\Models\\Module','2024-08-09 16:43:15','2024-08-09 16:43:15'),(828,1,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(829,2,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(830,3,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(831,7,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(832,8,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(833,9,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(834,10,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(835,11,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(836,12,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(837,13,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(838,14,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(839,15,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(840,16,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(841,17,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(842,18,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(843,19,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(844,4,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(845,5,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(846,21,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(847,22,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(848,23,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(849,26,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(850,20,68,'App\\Models\\Module','2024-08-20 20:47:41','2024-08-20 20:47:41'),(851,1,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(852,2,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(853,3,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(854,7,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(855,8,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(856,9,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(857,10,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(858,11,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(859,12,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(860,13,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(861,14,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(862,15,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(863,16,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(864,17,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(865,18,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(866,19,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(867,4,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(868,5,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(869,21,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(870,22,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(871,23,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(872,20,69,'App\\Models\\Module','2024-09-02 10:03:18','2024-09-02 10:03:18'),(873,1,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(874,2,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(875,20,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(876,3,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(877,7,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(878,8,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(879,9,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(880,10,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(881,11,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(882,12,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(883,13,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(884,14,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(885,15,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(886,16,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(887,17,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(888,18,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(889,19,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(890,4,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(891,5,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(892,21,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(893,22,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(894,23,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(895,26,70,'App\\Models\\Module','2024-09-16 11:25:00','2024-09-16 11:25:00'),(896,1,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(897,2,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(898,3,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(899,7,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(900,8,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(901,9,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(902,10,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(903,11,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(904,12,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(905,13,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(906,14,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(907,15,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(908,16,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(909,17,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(910,18,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(911,19,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(912,4,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(913,5,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(914,21,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(915,22,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(916,23,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(917,26,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(918,20,71,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(919,1,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(920,2,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(921,3,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(922,7,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(923,8,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(924,9,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(925,10,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(926,11,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(927,12,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(928,13,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(929,14,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(930,15,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(931,16,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(932,17,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(933,18,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(934,19,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(935,4,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(936,5,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(937,21,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(938,22,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(939,23,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(940,26,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(941,20,72,'App\\Models\\Module','2024-09-22 16:35:53','2024-09-22 16:35:53'),(942,1,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(943,2,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(944,3,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(945,7,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(946,8,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(947,9,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(948,10,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(949,11,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(950,12,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(951,13,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(952,14,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(953,15,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(954,16,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(955,17,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(956,18,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(957,19,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(958,4,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(959,5,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(960,21,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(961,22,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(962,23,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(963,26,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(964,20,73,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(965,1,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(966,2,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(967,3,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(968,7,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(969,8,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(970,9,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(971,10,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(972,11,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(973,12,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(974,13,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(975,14,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(976,15,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(977,16,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(978,17,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(979,18,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(980,19,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(981,4,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(982,5,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(983,21,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(984,22,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(985,23,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(986,26,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(987,20,74,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(988,1,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(989,2,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(990,3,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(991,7,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(992,8,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(993,9,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(994,10,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(995,11,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(996,12,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(997,13,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(998,14,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(999,15,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1000,16,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1001,17,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1002,18,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1003,19,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1004,4,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1005,5,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1006,21,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1007,22,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1008,23,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1009,26,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1010,20,75,'App\\Models\\Module','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1011,1,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1012,2,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1013,3,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1014,7,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1015,8,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1016,9,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1017,10,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1018,11,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1019,12,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1020,13,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1021,14,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1022,15,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1023,16,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1024,17,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1025,18,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1026,19,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1027,4,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1028,5,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1029,21,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1030,22,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1031,23,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1032,20,76,'App\\Models\\Module','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1033,1,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1034,2,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1035,3,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1036,7,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1037,8,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1038,9,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1039,10,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1040,11,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1041,12,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1042,13,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1043,14,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1044,15,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1045,16,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1046,17,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1047,18,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1048,19,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1049,4,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1050,5,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1051,21,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1052,22,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1053,23,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1054,20,77,'App\\Models\\Module','2024-09-28 01:13:29','2024-09-28 01:13:29'),(1055,1,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1056,2,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1057,3,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1058,7,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1059,8,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1060,9,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1061,10,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1062,11,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1063,12,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1064,13,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1065,14,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1066,15,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1067,16,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1068,17,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1069,18,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1070,19,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1071,4,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1072,5,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1073,21,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1074,22,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1075,23,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1076,26,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1077,20,79,'App\\Models\\Module','2024-11-23 14:09:54','2024-11-23 14:09:54');
/*!40000 ALTER TABLE `crud_packages` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `crud_packages` with 1077 row(s)
--

--
-- Table structure for table `customs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `update_description` tinyint(1) DEFAULT '0',
  `price` bigint(20) NOT NULL,
  `update_service` tinyint(1) DEFAULT '0',
  `partners` bigint(20) DEFAULT NULL,
  `tax_include` tinyint(1) NOT NULL,
  `tax` bigint(20) NOT NULL,
  `prepaid_period` enum('Mensual','Diario') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rates_to_change` bigint(20) DEFAULT NULL,
  `transaction_category` enum('Servicio','Descuento','Pago','Reembolso','Corrección') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `available_in_self_registration` tinyint(1) DEFAULT '0',
  `bandwidth` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` enum('1','2','3','4','5','6') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_days` bigint(20) DEFAULT NULL,
  `cost_activation` double(8,2) NOT NULL DEFAULT '0.00',
  `cost_instalation` double(8,2) NOT NULL DEFAULT '0.00',
  `cost_instalation_enable` tinyint(1) NOT NULL DEFAULT '0',
  `cost_activation_enable` tinyint(1) NOT NULL DEFAULT '0',
  `bandwidth_enable` tinyint(1) NOT NULL DEFAULT '0',
  `password_enable` tinyint(1) NOT NULL DEFAULT '0',
  `user_enable` tinyint(1) NOT NULL DEFAULT '0',
  `serial_number_enable` tinyint(1) NOT NULL DEFAULT '0',
  `router_id_enable` tinyint(1) NOT NULL DEFAULT '0',
  `mac_enable` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customs`
--

LOCK TABLES `customs` WRITE;
/*!40000 ALTER TABLE `customs` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `customs` VALUES (1,'Kit Instalacion','Kit Instalacion',NULL,2500,NULL,NULL,0,0,'Mensual',NULL,'Servicio',NULL,NULL,'1',NULL,0.00,0.00,0,0,0,0,0,0,0,0,'2024-05-29 04:46:43','2024-05-29 04:46:43'),(2,'Gpon_TV','TV',NULL,199,NULL,NULL,1,16,'Mensual',NULL,'Servicio',NULL,'20000','1',NULL,0.00,0.00,0,0,1,0,0,0,0,0,'2024-05-29 04:46:43','2024-05-29 04:46:43'),(3,'MovieNet','MovieNet',NULL,125,NULL,NULL,0,0,'Mensual',NULL,'Servicio',NULL,'5','1',NULL,0.00,0.00,0,0,1,0,0,0,0,0,'2024-05-29 04:46:43','2024-05-29 04:46:43'),(4,'modem','Modem',NULL,99,NULL,NULL,1,16,'Mensual',NULL,'Servicio',NULL,NULL,'1',NULL,0.00,0.00,0,0,0,0,0,0,0,0,'2024-05-29 04:46:43','2024-05-29 04:46:43');
/*!40000 ALTER TABLE `customs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `customs` with 4 row(s)
--

--
-- Table structure for table `cut_fibers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cut_fibers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `type` enum('visible','no visible') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `power` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meter` decimal(8,2) NOT NULL,
  `passive_equipment_id` bigint(20) unsigned NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cut_fibers_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `cut_fibers_created_by_foreign` (`created_by`),
  KEY `cut_fibers_updated_by_foreign` (`updated_by`),
  KEY `cut_fibers_passive_equipment_id_foreign` (`passive_equipment_id`),
  CONSTRAINT `cut_fibers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `cut_fibers_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `cut_fibers_passive_equipment_id_foreign` FOREIGN KEY (`passive_equipment_id`) REFERENCES `passive_equipments` (`id`),
  CONSTRAINT `cut_fibers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cut_fibers`
--

LOCK TABLES `cut_fibers` WRITE;
/*!40000 ALTER TABLE `cut_fibers` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `cut_fibers` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `cut_fibers` with 0 row(s)
--

--
-- Table structure for table `deal_crms`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deal_crms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint(20) NOT NULL,
  `total` bigint(20) NOT NULL,
  `created` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_close` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('algo','algo1','algo2') COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` bigint(20) NOT NULL,
  `lead_id` bigint(20) NOT NULL,
  `last_update_at` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_update_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connected_quote_id` bigint(20) NOT NULL,
  `customers_deal` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','won','lost','total') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deal_crms`
--

LOCK TABLES `deal_crms` WRITE;
/*!40000 ALTER TABLE `deal_crms` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `deal_crms` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `deal_crms` with 0 row(s)
--

--
-- Table structure for table `districts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `districts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `districts`
--

LOCK TABLES `districts` WRITE;
/*!40000 ALTER TABLE `districts` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `districts` VALUES (2,'1','2024-09-23 13:09:40','2024-09-23 13:09:40');
/*!40000 ALTER TABLE `districts` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `districts` with 1 row(s)
--

--
-- Table structure for table `equipment_links`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipment_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `input_id` bigint(20) unsigned NOT NULL,
  `output_id` bigint(20) unsigned NOT NULL,
  `map_link_id` bigint(20) unsigned DEFAULT NULL,
  `fiber_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipment_links_input_id_foreign` (`input_id`),
  KEY `equipment_links_output_id_foreign` (`output_id`),
  KEY `equipment_links_map_link_id_foreign` (`map_link_id`),
  KEY `equipment_links_fiber_id_foreign` (`fiber_id`),
  KEY `equipment_links_created_by_foreign` (`created_by`),
  KEY `equipment_links_updated_by_foreign` (`updated_by`),
  CONSTRAINT `equipment_links_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `equipment_links_fiber_id_foreign` FOREIGN KEY (`fiber_id`) REFERENCES `fibers` (`id`),
  CONSTRAINT `equipment_links_input_id_foreign` FOREIGN KEY (`input_id`) REFERENCES `ports` (`id`),
  CONSTRAINT `equipment_links_map_link_id_foreign` FOREIGN KEY (`map_link_id`) REFERENCES `map_links` (`id`),
  CONSTRAINT `equipment_links_output_id_foreign` FOREIGN KEY (`output_id`) REFERENCES `ports` (`id`),
  CONSTRAINT `equipment_links_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment_links`
--

LOCK TABLES `equipment_links` WRITE;
/*!40000 ALTER TABLE `equipment_links` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `equipment_links` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `equipment_links` with 0 row(s)
--

--
-- Table structure for table `failed_jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `failed_jobs` with 0 row(s)
--

--
-- Table structure for table `fibers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fibers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` int(11) NOT NULL,
  `color_id` bigint(20) unsigned NOT NULL,
  `buffer_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fibers_color_id_foreign` (`color_id`),
  KEY `fibers_buffer_id_foreign` (`buffer_id`),
  KEY `fibers_created_by_foreign` (`created_by`),
  KEY `fibers_updated_by_foreign` (`updated_by`),
  CONSTRAINT `fibers_buffer_id_foreign` FOREIGN KEY (`buffer_id`) REFERENCES `buffers` (`id`),
  CONSTRAINT `fibers_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`),
  CONSTRAINT `fibers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fibers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fibers`
--

LOCK TABLES `fibers` WRITE;
/*!40000 ALTER TABLE `fibers` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `fibers` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `fibers` with 0 row(s)
--

--
-- Table structure for table `field_modules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `field_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint(20) NOT NULL,
  `include` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'false if not include in ComponentFormDefault',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `placeholder` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `options` text COLLATE utf8mb4_unicode_ci,
  `search` text COLLATE utf8mb4_unicode_ci COMMENT 'if type select and you want get values by ajax request',
  `inputGroup` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inputGroupEnd` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `depend` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if type select-component-with-input',
  `inputs_depend` text COLLATE utf8mb4_unicode_ci,
  `position` bigint(20) DEFAULT NULL,
  `disabled` tinyint(1) DEFAULT NULL,
  `default_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rule` text COLLATE utf8mb4_unicode_ci,
  `step` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_field` tinyint(1) NOT NULL DEFAULT '0',
  `class_col` enum('full','partial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `class_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
  `class_field` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'col-sm-12 col-md-9',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `field_modules`
--

LOCK TABLES `field_modules` WRITE;
/*!40000 ALTER TABLE `field_modules` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `field_modules` VALUES (1,1,1,'title','1','Título','','título',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(2,1,1,'service_name','1','Nombre del Servicio','','nombre del servicio',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(3,1,1,'update_description','27','Actualizar Descripcion','Actualización de la descripción existente de los servicios','actualizar descripcion','false','[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(4,1,1,'price','15','Precio','','precio',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(5,1,1,'update_service','27','Actualizar Servicio','Actualización de precios existentes de los servicios','actualizar servicio','false','[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(6,1,1,'partners','25','Socios','','socios','[]',NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(7,1,1,'tax_include','16','IVA Incluido','','iva incluido','false','[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(8,1,1,'tax','22','IVA','','Seleccione IVA',NULL,'{\"0\":\"0% (IVA 0%)\",\"16\":\"16% (IVA 16%)\"}',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(9,1,1,'download_speed','11','Velocidad de bajada (kbps)','','velocidad de bajada',NULL,'[]',NULL,'kbps','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(10,1,1,'upload_speed','11','Velocidad de Subida','','velocidad de subida',NULL,'[]',NULL,'kbps','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(11,1,1,'guaranteed_speed_limit','12','Lím. Velocidad Garantizada al','','limite de velocidad garantizada',NULL,'[]',NULL,'%','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(12,1,1,'priority','22','Prioridad','','Seleccione la prioridad',NULL,'{\"Baja\":\"Baja\",\"Normal\":\"Normal\",\"Alta\":\"Alta\"}',NULL,'','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(13,1,1,'aggregation','13','Agregación','','agregacion',NULL,'[]',NULL,'1:','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(14,1,1,'burst','14','Límite de Burst','','burst',NULL,'{\"Ninguno\":\"Ninguno\",\"Relativo\":\"Relativo\",\"Fijo\":\"Fijo\"}',NULL,'+','%',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(15,1,1,'burt_umbral','11','Umbral de Burst','','umbral de burst',NULL,'[]',NULL,'%','',NULL,NULL,16,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(16,1,1,'burt_time','11','Tiempo de Burst','','tiempo de burst',NULL,'[]',NULL,'seg','',NULL,NULL,17,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(17,1,1,'rates_to_change','26','Tarifas que se Pueden Elegir en Portal del Cliente','','','[]',NULL,'{\"model\":\"App\\\\Models\\\\Internet\",\"id\":\"id\",\"text\":\"title\"}','','',NULL,NULL,18,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(18,1,1,'types_of_billing','25','Tipos de Facturación','','facturacion','[]',NULL,'{\"model\":\"App\\\\Models\\\\TypeBilling\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,19,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(19,1,1,'prepaid_period','19','Períodos de Pago','','Seleccione Períodos de Pago...',NULL,'{\"Mensual\":\"Mensual\",\"Diario\":\"Grupo de D\\u00edas\"}',NULL,'','','option','{\"amount_days\":{\"field\":\"amount_days\",\"label\":\"# de Dias\",\"placeholder\":\"# de dias\",\"type\":\"input-number\",\"value\":null,\"depend\":\"Diario\",\"position\":1}}',20,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(20,1,0,'amount_days','30','','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(21,1,1,'transaction_category','22','Categoría de la Transacción','','categoria de la transaccion',NULL,'{\"Servicio\":\"Servicio\",\"Descuento\":\"Descuento\",\"Pago\":\"Pago\",\"Reembolso\":\"Reembolso\",\"Correcci\\u00f3n\":\"Correcci\\u00f3n\"}',NULL,'','',NULL,NULL,21,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(22,1,1,'available_when_register_by_social_network','16','Disponible cuando se registra en la red social','','disponible','false','[]',NULL,'','',NULL,NULL,22,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(23,1,1,'cost_activation','15','Costo de Activacion','','',NULL,'[]',NULL,'','',NULL,NULL,23,0,'\"0\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(24,1,1,'cost_instalation','15','Costo de Instalacion','','',NULL,'[]',NULL,'','',NULL,NULL,24,0,'\"0\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(25,2,1,'title','1','Título','','título',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(26,2,1,'service_name','1','Nombre del Servicio','','nombre del servicio',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(27,2,1,'update_description','27','Actualizar Descripcion','Actualización de la descripción existente de los servicios','actualizar descripcion','false','[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(28,2,1,'price','15','Precio','','precio',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(29,2,1,'update_service','27','Actualizar Servicio','Actualización de precios existentes de los servicios','actualizar servicio','false','[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(30,2,1,'type','22','Tipo','','Seleccione el tipo',NULL,'{\"VoIP\":\"Telefon\\u00eda IP\",\"Corregido\":\"Corregido\",\"M\\u00f3vil\":\"Telefon\\u00eda M\\u00f3vil\"}',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(31,2,1,'partners','25','Socios','','socios','[]',NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(32,2,1,'tax_include','16','IVA Incluido','','iva incluido','false','[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(33,2,1,'tax','22','IVA','','Seleccione IVA',NULL,'{\"0\":\"0% (IVA 0%)\",\"16\":\"16% (IVA 16%)\"}',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(34,2,1,'rates_to_change','26','Tarifas que se Pueden Elegir en Portal del Cliente','','Seleccione las tarifas ...','[]',NULL,'{\"model\":\"App\\\\Models\\\\Voise\",\"id\":\"id\",\"text\":\"title\"}','','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(35,2,1,'types_of_billing','25','Tipos de Facturación','','Seleccione tipo de facturación','[]',NULL,'{\"model\":\"App\\\\Models\\\\TypeBilling\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(36,2,1,'prepaid_period','19','Períodos de Pago','','Seleccione Períodos de Pago...',NULL,'{\"Mensual\":\"Mensual\",\"Diario\":\"Grupo de D\\u00edas\"}',NULL,'','','option','{\"amount_days\":{\"field\":\"amount_days\",\"label\":\"# de Dias\",\"placeholder\":\"# de dias\",\"type\":\"input-number\",\"value\":null,\"depend\":\"Diario\",\"position\":1}}',12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(37,2,0,'amount_days','30','','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(38,2,1,'transaction_category','22','Categoría de la Transacción','','Seleccione la categoría',NULL,'{\"Servicio\":\"Servicio\",\"Descuento\":\"Descuento\",\"Pago\":\"Pago\",\"Reembolso\":\"Reembolso\",\"Correcci\\u00f3n\":\"Correcci\\u00f3n\"}',NULL,'','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(39,2,1,'transaction_category_for_calls','22','Categoria de Transacción de Llamadas','','Seleccione la categoría',NULL,'{\"Servicio\":\"Servicio\",\"Descuento\":\"Descuento\",\"Pago\":\"Pago\",\"Reembolso\":\"Reembolso\",\"Correcci\\u00f3n\":\"Correcci\\u00f3n\"}',NULL,'','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(40,2,1,'transaction_category_for_messages','22','Categoria de Transacción de Mensajes','','Seleccione la categoría',NULL,'{\"Servicio\":\"Servicio\",\"Descuento\":\"Descuento\",\"Pago\":\"Pago\",\"Reembolso\":\"Reembolso\",\"Correcci\\u00f3n\":\"Correcci\\u00f3n\"}',NULL,'','',NULL,NULL,16,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:15'),(41,2,1,'transaction_category_for_data','22','Categoria de Transacción de Datos','','Seleccione la categoría',NULL,'{\"Servicio\":\"Servicio\",\"Descuento\":\"Descuento\",\"Pago\":\"Pago\",\"Reembolso\":\"Reembolso\",\"Correcci\\u00f3n\":\"Correcci\\u00f3n\"}',NULL,'','',NULL,NULL,17,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(42,2,1,'available_in_self_registration','16','Disponible el Auto Registro','','','false','[]',NULL,'','',NULL,NULL,18,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(43,2,1,'bandwidth','15','Ancho de Banda','','Ancho de Banda',NULL,'[]',NULL,'','',NULL,NULL,19,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(44,2,1,'priority','22','Prioridad','','Seleccione la prioridad',NULL,'{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\",\"6\":\"6\"}',NULL,'','',NULL,NULL,20,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(45,2,1,'cost_activation','15','Costo de Activacion','','',NULL,'[]',NULL,'','',NULL,NULL,21,0,'\"0\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(46,2,1,'cost_instalation','15','Costo de Instalacion','','',NULL,'[]',NULL,'','',NULL,NULL,22,0,'\"0\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(47,3,1,'title','1','Título','','título',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(48,3,1,'service_name','1','Nombre del Servicio','','nombre del servicio',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(49,3,1,'update_description','27','Actualizar Descripcion','Actualización de la descripción existente de los servicios','actualizar descripcion','false','[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(50,3,1,'price','15','Precio','','precio',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(51,3,1,'update_service','27','Actualizar Servicio','Actualización de precios existentes de los servicios','actualizar servicio','false','[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(52,3,1,'partners','22','Socios','','socios',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(53,3,1,'tax_include','16','IVA Incluido','','iva incluido','false','[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(54,3,1,'tax','22','IVA','','Seleccione IVA',NULL,'{\"0\":\"0% (IVA 0%)\",\"16\":\"16% (IVA 16%)\"}',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(55,3,1,'rates_to_change','26','Tarifas que se Pueden Elegir en Portal del Cliente','','Seleccione las tarifas ...','[]',NULL,'{\"model\":\"App\\\\Models\\\\Custom\",\"id\":\"id\",\"text\":\"title\"}','','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(56,3,1,'types_of_billing','25','Tipos de Facturación','','facturacion','[]',NULL,'{\"model\":\"App\\\\Models\\\\TypeBilling\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(57,3,1,'prepaid_period','19','Períodos de Pago','','Seleccione Períodos de Pago...',NULL,'{\"Mensual\":\"Mensual\",\"Diario\":\"Grupo de D\\u00edas\"}',NULL,'','','option','{\"amount_days\":{\"field\":\"amount_days\",\"label\":\"# de Dias\",\"placeholder\":\"# de dias\",\"type\":\"input-number\",\"value\":null,\"depend\":\"Diario\",\"position\":1}}',12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(58,3,0,'amount_days','30','','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(59,3,1,'transaction_category','22','Categoría de la Transacción','','Seleccione la categoría',NULL,'{\"Servicio\":\"Servicio\",\"Descuento\":\"Descuento\",\"Pago\":\"Pago\",\"Reembolso\":\"Reembolso\",\"Correcci\\u00f3n\":\"Correcci\\u00f3n\"}',NULL,'','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(60,3,1,'Available_in_self_registration','16','Disponible el Auto Registro','','','false','[]',NULL,'','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(61,3,1,'Bandwidth','15','Ancho de Banda','','Ancho de Banda',NULL,'[]',NULL,'','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(62,3,1,'priority','22','Prioridad','','Seleccione la prioridad',NULL,'{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\",\"6\":\"6\"}',NULL,'','',NULL,NULL,17,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(63,3,1,'cost_activation','15','Costo de Activacion','','',NULL,'[]',NULL,'','',NULL,NULL,18,0,'\"0\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(64,3,1,'cost_instalation','15','Costo de Instalacion','','',NULL,'[]',NULL,'','',NULL,NULL,20,0,'\"0\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(65,4,1,'title','1','Título','','título',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:22'),(66,4,1,'service_description','1','Descripción del Servicio','','Descripción',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:22'),(67,4,1,'price','15','Precio','','precio',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:22'),(68,4,1,'tax_include','16','IVA Incluido','','iva incluido','false','[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:22'),(69,4,1,'tax','22','IVA','','Seleccione IVA',NULL,'{\"0\":\"0% (IVA 0%)\",\"16\":\"16% (IVA 16%)\"}',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:22'),(70,4,1,'partners','25','Socios','','socios','[]',NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(71,4,1,'types_of_billing','25','Tipos de Facturación','','facturacion','[]',NULL,'{\"model\":\"App\\\\Models\\\\TypeBilling\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(72,4,1,'transaction_category','22','Categoría de la Transacción','','categoria de la transaccion',NULL,'{\"Servicio\":\"Servicio\",\"Descuento\":\"Descuento\",\"Pago\":\"Pago\",\"Reembolso\":\"Reembolso\",\"Correcci\\u00f3n\":\"Correcci\\u00f3n\"}',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(73,4,1,'activation_fee','15','Costo de Activación','','0.000',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(74,4,1,'get_activation_fee_when','22','Aplica activación','','Aplica activación',NULL,'{\"En facturaci\\u00f3n del primer servicio\":\"En facturaci\\u00f3n del primer servicio\",\"Al crear el servicio\":\"Al crear el servicio\"}',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(75,4,1,'emit_invoice','16','Emitir Factura','','Emitir Factura','false','[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(76,4,1,'contract_duration','15','Duración del Contrato','','0.000',NULL,'[]',NULL,'','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(77,4,1,'automatic_renewal','16','Renovación Automática','','','false','[]',NULL,'','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(78,4,1,'auto_reactivate','16','Reactivar automáticamente ','','','false','[]',NULL,'','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(79,4,1,'cancellation_fee','15','Cancelación previa','','0.000',NULL,'[]',NULL,'','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(80,4,1,'prior_cancellation_fee','15','Tarifa de Cancelación Previa','','0.000',NULL,'[]',NULL,'','',NULL,NULL,16,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(81,4,1,'discount_period','15','Período de Descuento','','0.000',NULL,'[]',NULL,'','',NULL,NULL,17,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(82,4,1,'discount_value','15','Porciento de Descuento','','0.0',NULL,'[]',NULL,'','',NULL,NULL,18,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(83,5,1,'title','1','Título','','título',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(84,5,1,'service_description','1','Descripción del Servicio','','Descripción',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(85,5,1,'price','15','Precio','','precio',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(86,5,1,'tax_include','16','IVA Incluido','','iva incluido','false','[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(87,5,1,'tax','22','IVA','','Seleccione IVA',NULL,'{\"0\":\"0% (IVA 0%)\",\"16\":\"16% (IVA 16%)\"}',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(88,5,1,'partners','25','Socios','','socios','[]',NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(89,5,1,'types_of_billing','25','Tipos de Facturación','','facturacion','[]',NULL,'{\"model\":\"App\\\\Models\\\\TypeBilling\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(90,5,1,'transaction_category','22','Categoría de la Transacción','','categoria de la transaccion',NULL,'{\"Servicio\":\"Servicio\",\"Descuento\":\"Descuento\",\"Pago\":\"Pago\",\"Reembolso\":\"Reembolso\",\"Correcci\\u00f3n\":\"Correcci\\u00f3n\"}',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(91,6,1,'activation_fee','15','Costo de Activación','','0.000',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(92,6,1,'get_activation_fee_when','22','Aplica activación','','Aplica activación',NULL,'{\"En facturaci\\u00f3n del primer servicio\":\"En facturaci\\u00f3n del primer servicio\",\"Al crear el servicio\":\"Al crear el servicio\"}',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(93,6,1,'emit_invoice','16','Emitir Factura','','Emitir Factura','false','[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(94,6,1,'contract_duration','15','Duración del Contrato','','0.000',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(95,6,1,'automatic_renewal','16','Renovación Automática','','','false','[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(96,6,1,'auto_reactivate','16','Reactivar automáticamente ','','','false','[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(97,6,1,'cancellation_fee','15','Cancelación previa','','0.000',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(98,6,1,'prior_cancellation_fee','15','Tarifa de Cancelación Previa','','0.000',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(99,6,1,'discount_period','15','Período de Descuento','','0.000',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(100,6,1,'discount_value','15','Porciento de Descuento','','0.0',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(101,7,1,'planes_internet','28','Internet','','Agregar Plan',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Internet\",\"id\":\"id\",\"text\":\"title\"}','','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(102,7,1,'planes_voz','28','Voz','','Agregar Plan',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Voise\",\"id\":\"id\",\"text\":\"title\"}','','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(103,7,1,'planes_custom','28','Custom','','Agregar Plan',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Custom\",\"id\":\"id\",\"text\":\"title\"}','','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(104,8,1,'name','1','Nombre','','nombre',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 15:53:17'),(105,8,1,'father_last_name','1','Apellido Paterno','','Apellido Paterno',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 15:54:54'),(106,8,1,'mother_last_name','1','Apellido Materno','','Apellido Materno',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 15:55:21'),(107,8,1,'email','1','Correo','','correo',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 15:54:47'),(108,8,1,'email_is_required','16','Correo es requerido','','','false','[]',NULL,'','',NULL,NULL,4,0,'true','',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 15:55:21'),(109,8,1,'phone','1','Telefono','','telefono',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 15:54:46'),(110,8,1,'phone2','1','Telefono2','','telefono2',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 15:56:13'),(111,8,0,'nif_pasaport','1','RFC/CURP','','',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 15:56:11'),(112,8,1,'partner_id','22','Socios','','Seleccionar el socio',NULL,'[]','{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 15:56:13'),(113,8,1,'location_id','22','Ubicación','','Seleccionar ubicacion',NULL,'[]','{\"model\":\"App\\\\Models\\\\Location\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 16:05:15'),(114,8,1,'street','1','Calle','','calle',NULL,'[]',NULL,'','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 16:07:28'),(115,8,1,'external_number','1','Numero Exterior','','Mz',NULL,'[]',NULL,'','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 16:11:44'),(116,8,1,'internal_number','1','Numero Interior','','Lt',NULL,'[]',NULL,'','',NULL,NULL,17,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-06-01 09:55:02'),(117,8,1,'owner_id','22','Vendedor','','Seleccionar el vendedor',NULL,'[]','{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\", \"scope\": \"sellerRole\"}','','',NULL,NULL,14,0,'\"user_authenticated\"','',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-08-26 16:41:27'),(118,8,1,'crm_status','22','CRM Status','','Seleccionar el Status',NULL,'{\"Nuevo\":\"Nuevo\",\"Contactado\":\"Contactado\",\"Interesado\":\"Interesado\",\"Cotizaci\\u00f3n\":\"Cotizaci\\u00f3n\",\"Ganado\":\"Ganado\",\"Perdido\":\"Perdido\"}',NULL,'','',NULL,NULL,2,0,'\"Nuevo\"','',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 15:54:54'),(119,8,1,'enable_same_name_or_rfc','16','Permitir Usuario Duplicado','','','false','[]',NULL,'','',NULL,NULL,16,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 16:15:05'),(120,9,1,'ift','1','IFT','','ift',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(121,9,1,'name','1','Nombre','','nombre',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(122,9,1,'father_last_name','1','Apellido Paterno','','Apellido Paterno',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(123,9,1,'mother_last_name','1','Apellido Materno','','Apellido Materno',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(124,9,1,'email','1','Correo','','correo',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-10-08 15:20:05'),(125,9,1,'phone','1','Telefono','','telefono',NULL,'[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(126,9,1,'phone2','1','Telefono 2','','telefono 2',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(127,9,1,'nif_pasaport','1','RFC/CURP','','',NULL,'[]',NULL,'','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-11-19 09:30:03'),(128,9,1,'partner_id','22','Socios','','Seleccionar el Socio',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(129,9,1,'location_id','22','Ubicación','','Seleccionar ubicación',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Location\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(130,9,1,'high_date','1','Fecha de alta','','fecha-de-alta',NULL,'[]',NULL,'','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(131,9,1,'colony_id','24','App\\Models\\CrmMainInformation','','Seleccionar Colonia',NULL,'[]',NULL,'','',NULL,NULL,16,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:23'),(135,9,0,'state_id','30','','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(136,9,0,'municipality_id','30','','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(137,9,1,'zip','1','Codigo Postal','','codigo-postal',NULL,'[]',NULL,'','',NULL,NULL,17,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(138,10,1,'score','2','Score','','score',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(139,10,1,'last_contacted','2','Last Contacted','','last-contacted',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(140,10,1,'instalation_date','20','Dia de instalacion','','Agregue fecha de instalacion',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-11-19 09:31:06'),(141,10,1,'crm_techical_user_id','22','Tecnico','','Seleccionar el técnico encargado',NULL,NULL,'{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\", \"scope\": \"technicalRole\"}','','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-08-26 16:41:27'),(142,10,1,'crm_status','22','Estado del CRM','','Seleccionar el estado',NULL,'{\"Nuevo\":\"Nuevo\",\"Contactado\":\"Contactado\",\"Interesado\":\"Interesado\",\"Cotizaci\\u00f3n\":\"Cotizaci\\u00f3n\",\"Instalacion\":\"Instalacion\",\"Perdido\":\"Perdido\"}',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(143,10,1,'owner_id','22','Propietario','','Seleccionar el propietario',NULL,'[]','{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\", \"scope\": \"sellerRole\"}','','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-08-26 16:41:27'),(144,10,1,'source','1','Comentario','','comentario',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(145,11,1,'title','1','Titulo','','titulo',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(146,11,1,'description','1','Descripcion','','descripcion',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(147,11,1,'show','16','Visible','','visible','false','[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(148,11,1,'file','6','','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(230,15,1,'title','1','Titulo','','titulo',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(231,15,1,'description','1','Descripcion','','descripcion',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(232,15,1,'show','16','Visible','','visible','false','[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(233,15,1,'file','6','','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(294,18,0,'voz_id','22','Tarifas','','Seleccionar las tarifas ...',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Voise\",\"id\":\"id\",\"text\":\"title\"}','','',NULL,NULL,5,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(295,18,1,'description','1','Descripción','','',NULL,'[]',NULL,'','',NULL,NULL,6,1,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(296,18,1,'amount','15','Cantidad','','',NULL,'[]',NULL,'','',NULL,NULL,7,0,'1','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(297,18,1,'unity','15','Unidad','','',NULL,'[]',NULL,'','',NULL,NULL,8,0,'1','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(298,18,1,'price','15','Precio','','',NULL,'[]',NULL,'','',NULL,NULL,9,1,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(299,18,1,'pay_period','22','Período de Pago','','Seleccionar Periodo de Pago',NULL,'{\"Periodo 1\":\"Periodo 1\",\"Periodo 2\":\"Periodo 2\",\"Periodo 3\":\"Periodo 3\",\"Periodo 4\":\"Periodo 4\",\"Periodo 5\":\"Periodo 5\"}',NULL,'','',NULL,NULL,10,0,'\"Periodo 1\"','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(300,18,1,'finish_date','20','Fecha en que termina','','01/01/2021',NULL,'[]',NULL,'','',NULL,NULL,12,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(301,18,1,'start_date','20','Fecha de Inicio','','01/01/2021',NULL,'[]',NULL,'','',NULL,NULL,11,0,'\"now\"','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(302,18,1,'discount','18','Descuento','','','false','[]',NULL,'','','1','{\"discount_percent\":{\"field\":\"discount_percent\",\"label\":\"Porciento de descuento\",\"placeholder\":\"descuento\",\"type\":\"input-group-text\",\"inputGroup\":\"%\",\"value\":null,\"position\":1},\"start_date_discount\":{\"field\":\"start_date_discount\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Fecha de Inicio de Descuento\",\"placeholder\":\"01\\/01\\/2021\",\"position\":2},\"end_date_discount\":{\"field\":\"end_date_discount\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Fecha de Finalizaci\\u00f3n de Descuento\",\"placeholder\":\"01\\/01\\/2021\",\"position\":3},\"discount_message\":{\"field\":\"discount_message\",\"type\":\"input-string\",\"value\":null,\"label\":\"Mensaje de descuento\",\"placeholder\":\"entre su mensaje\",\"position\":4}}',13,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(303,18,0,'discount_percent','30','','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(304,18,0,'start_date_discount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(305,18,0,'end_date_discount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(306,18,0,'discount_message','30','','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(307,18,1,'estado','22','Estado','','Seleccionar el Estado',NULL,'{\"Activado\":\"Activado\",\"Desactivado\":\"Desactivado\"}',NULL,'','',NULL,NULL,14,0,'\"Activado\"','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(308,18,1,'phone','1','Teléfono','','telefono',NULL,'[]',NULL,'','',NULL,NULL,15,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(309,18,1,'password','8','Contraseña','','',NULL,'[]',NULL,'','',NULL,NULL,16,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(310,18,1,'voise_device','22','Dispositivo de Voz','','Seleccionar dispositivo de voz',NULL,'{\"Ninguno\":\"Ninguno\",\"VoIP\":\"VoIP\"}',NULL,'','',NULL,NULL,17,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(311,18,1,'direction','22','Dirección','','Seleccionar direccion',NULL,'{\"Salientes\":\"Salientes\",\"Entrantes\":\"Entrantes\"}',NULL,'','',NULL,NULL,18,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(312,19,0,'custom_id','22','Tarifas','','Seleccionar las tarifas ...',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Custom\",\"id\":\"id\",\"text\":\"title\"}','','',NULL,NULL,9,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(313,19,1,'description','1','Descripción','','',NULL,'[]',NULL,'','',NULL,NULL,10,1,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(314,19,1,'amount','15','Cantidad','','',NULL,'[]',NULL,'','',NULL,NULL,11,0,'1','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(315,19,1,'unity','15','Unidad','','',NULL,'[]',NULL,'','',NULL,NULL,12,0,'1','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(316,19,1,'price','15','Precio','','',NULL,'[]',NULL,'','',NULL,NULL,13,1,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(317,19,1,'pay_period','22','Período de Pago','','Seleccionar el Período de Pago',NULL,'{\"Periodo 1\":\"Periodo 1\",\"Periodo 2\":\"Periodo 2\",\"Periodo 3\":\"Periodo 3\",\"Periodo 4\":\"Periodo 4\",\"Periodo 5\":\"Periodo 5\"}',NULL,'','',NULL,NULL,14,0,'\"Periodo 1\"','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(318,19,1,'start_date','20','Fecha de Inicio','','01/01/2021',NULL,'[]',NULL,'','',NULL,NULL,15,0,'\"now\"','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(319,19,1,'discount','18','Descuento','','','false','[]',NULL,'','','1','{\"discount_percent\":{\"field\":\"discount_percent\",\"label\":\"Porciento de descuento\",\"placeholder\":\"descuento\",\"type\":\"input-group-text\",\"inputGroup\":\"%\",\"value\":null,\"position\":1},\"start_date_discount\":{\"field\":\"start_date_discount\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Fecha de Inicio de Descuento\",\"placeholder\":\"01\\/01\\/2021\",\"position\":2},\"end_date_discount\":{\"field\":\"end_date_discount\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Fecha de Finalizaci\\u00f3n de Descuento\",\"placeholder\":\"01\\/01\\/2021\",\"position\":3},\"discount_message\":{\"field\":\"discount_message\",\"type\":\"input-string\",\"value\":null,\"label\":\"Mensaje de descuento\",\"placeholder\":\"entre su mensaje\",\"position\":4}}',16,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(320,19,0,'discount_percent','30','','','',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(321,19,0,'start_date_discount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(322,19,0,'end_date_discount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(323,19,0,'discount_message','30','','','',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(324,19,1,'estado','22','Estado','','Seleccionar el Estado',NULL,'{\"Activado\":\"Activado\",\"Desactivado\":\"Desactivado\",\"Pendiente\":\"Pendiente\"}',NULL,'','',NULL,NULL,17,0,'\"Pendiente\"','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(325,19,1,'payment_type','19','Tipo de pago','','Seleccione el tipo de pago..','false','{\"Pago recurrente\":\"Pago recurrente\",\"Pago unico\":\"Pago unico\",\"Pago diferido\":\"Pago diferido\",\"Garantia\":\"Garantia\"}',NULL,'','','option','{\"deferred_payment_in_month\":{\"field\":\"deferred_payment_in_month\",\"label\":\"Meses\",\"placeholder\":\"Seleccione en cuantos meses\",\"type\":\"select-component\",\"value\":null,\"depend\":\"Pago diferido\",\"options\":{\"1mes\":\"1mes\",\"3meses\":\"3meses\",\"6meses\":\"6meses\",\"9meses\":\"9meses\",\"12meses\":\"12meses\"},\"position\":1}}',18,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(326,19,0,'deferred_payment_in_month','30','','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(359,21,1,'payment_method_id','22','Metodo de pago','','metodo-de-pago',NULL,NULL,'{\"model\":\"App\\\\Models\\\\MethodOfPayment\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(360,21,1,'minimum_balance','15','Balance minimo','','balance-minimo',NULL,'[]',NULL,'','',NULL,NULL,2,0,'\"0\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(361,21,1,'create_monthly_invoice','16','Crear facturas (mensual)','','crear-facturas-mensual','false','[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(362,21,1,'send_financial_notification','16','Enviar notificaciones de finanzas','','enviar-notificaciones-de-finanzas','false','[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(363,22,1,'billing_activated','16','Activar Facturacion','','activar-facturacion','false','[]',NULL,'','',NULL,NULL,1,0,'\"1\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(364,22,1,'period','15','Periodo','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,'1','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(365,22,1,'payment_method_id','22','Metodo de pago','','metodo-de-pago',NULL,NULL,'{\"model\":\"App\\\\Models\\\\MethodOfPayment\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(366,22,1,'billing_date','15','Dia de facturación','','dia-de-facturacion',NULL,'[]',NULL,'','',NULL,NULL,4,0,'{\"request\":\"\\/get-default-billing-date-for-client\"}','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(367,22,1,'billing_expiration','15','Bloqueo de servicio','','dias de servicio sin pagar',NULL,'{\"min\":1,\"max\":31}',NULL,'','',NULL,NULL,5,0,'{\"request\":\"\\/get-default-billing-date-for-client\"}','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(368,22,1,'grace_period','15','Grace periodo','','grace periodo',NULL,'[]',NULL,'','',NULL,NULL,6,0,'\"90\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(369,22,1,'minimum_balance','15','Balance minimo','','balance-minimo',NULL,'[]',NULL,'','',NULL,NULL,7,0,'\"0\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:24'),(370,22,1,'membership_percentage','15','Porcentaje de socio','','porcentaje-de-socio',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(371,22,1,'create_invoice','16','Crear factura (despues de hacer el descuento)','','create-invoices-after-charge-invoice-','false','[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(372,22,1,'autopay_invoice','16','Auto pagar factura desde la cuenta','','auto-pay-invoices-from-account-balance','false','[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(373,22,1,'send_financial_notification','16','Enviar notificaciones de finanzas','','enviar-notificaciones-de-finanzas','false','[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(374,23,1,'billing_name','1','Nombre de Facturación','','Nombre de Facturación',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(375,23,1,'billing_street','1','Calle de Facturación','','Calle de Facturación',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(376,23,1,'billing_zip_code','1','Código Postal de la facturación','','Código Postal de la facturación',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(377,23,1,'billing_city','1','Ciudad de Facturación','','Ciudad de Facturación',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(378,24,1,'activate_reminders','16','Activar recordatorios','','Activar recordatorios','false','[]',NULL,'','',NULL,NULL,1,0,'true','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(379,24,1,'type_of_message','22','Tipo de mensaje','','Seleccione el tipo de mensaje',NULL,'{\"Mail\":\"Mail\",\"SMS\":\"SMS\",\"Mail + SMS\":\"Mail + SMS\"}',NULL,'','',NULL,NULL,2,0,'\"Mail + SMS\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(380,24,1,'reminder_1_days','15','Recordatorio #1 día','','Recordatorio #1 día',NULL,'[]',NULL,'','',NULL,NULL,3,0,'5','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(381,24,1,'reminder_2_days','15','Recordatorio #2 día','','Recordatorio #2 día',NULL,'[]',NULL,'','',NULL,NULL,4,0,'3','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(382,24,1,'reminder_3_days','15','Recordatorio #3 día','','Recordatorio #3 día',NULL,'[]',NULL,'','',NULL,NULL,5,0,'1','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(383,24,1,'reminder_payment_amount','15','Cantidad del pago de recordatorio','','0.00',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(384,24,1,'reminder_payment_comment','1','Comentario del pago de recordatorio','','Comentario del pago de recordatorio',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(400,26,1,'type','22','Tipo','','Seleccione el tipo',NULL,'{\"debit\":\"+ Debit\",\"credit\":\"- Credit\"}',NULL,'','',NULL,NULL,4,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(401,26,1,'description','1','Descripcion','','',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(402,26,1,'cantidad','1','Cantidad','','','\"1\"','[]',NULL,'','',NULL,NULL,6,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(403,26,1,'input-price-transaction','32','','','','{\"price\":{\"type\":\"input-string\",\"value\":null,\"label\":\"Precio\",\"placeholder\":\"\",\"position\":4,\"partition\":\"price\"},\"iva\":{\"type\":\"input-string\",\"value\":null,\"label\":\"Iva %\",\"placeholder\":\"\",\"position\":5,\"partition\":\"price\"},\"withiva\":{\"type\":\"input-string\",\"value\":null,\"label\":\"Con Iva\",\"placeholder\":\"\",\"position\":5,\"partition\":\"price\"},\"total\":{\"type\":\"input-string\",\"value\":null,\"label\":\"Total\",\"placeholder\":\"\",\"position\":5,\"partition\":\"price\"}}','[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(404,26,0,'price','30','','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(405,26,0,'iva','30','','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(406,26,0,'total','30','','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(407,26,1,'period','31','Periodo','','periodo',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(408,26,1,'category','22','Categoria','','Seleccione la categoria',NULL,'{\"Servicio\":\"Servicio\",\"Descuento\":\"Descuento\",\"Pago\":\"Pago\",\"Reembolso\":\"Reembolso\",\"Correccion\":\"Correccion\"}',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(409,26,1,'date','20','Fecha','','',NULL,'[]',NULL,'','',NULL,NULL,10,1,'\"2024-02-09 23:43:22\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(410,26,1,'comment','1','Comentario','','comentario',NULL,'[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(411,26,1,'add_to_invoice','16','Add to Invoice','','add-to-invoice','false','[]',NULL,'','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(412,27,1,'title','1','Título','','título',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(413,27,1,'type_of_nas','22','Tipo de Nas','','Seleccionar tipo de Nas','\"Mikrotik\"','{\"Mikrotik\":\"Mikrotik\",\"Cisco\":\"Cisco\",\"Ubiquiti\":\"Ubiquiti\"}',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(414,27,1,'vendor_model','1','Vendedor/Modelo','','Vendedor/Modelo',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(415,27,1,'partners','25','Socios','','socios','[]',NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,4,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(416,27,1,'location_id','22','Ubicación','','Seleccionar la ubicación',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Location\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(417,27,1,'physical_address','1','Dirección física','','Dirección física',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(418,27,1,'ip_host','1','IP/Host','','0.0.0.0',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(419,27,1,'nas_ip','1','NAS IP','','0.0.0.0',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(420,27,1,'authorization_accounting','22','Autorización / Contabilidad ','','Ninguno/Ninguno','\"PPP(Secrets)\\/API Acounting\"','{\"PPP(Secrets)\\/API Acounting\":\"PPP(Secrets)\\/API Acounting\",\"Hostpot(Users)\\/API accounting\":\"Hostpot(Users)\\/API accounting\",\"Hostopt(Radius)\\/Radius\":\"Hostopt(Radius)\\/Radius\"}',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(421,28,1,'title','1','Título','','título',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(422,28,1,'type_of_nas','22','Tipo de Nas','','Seleccione tipo de Nas','\"Mikrotik\"','{\"Mikrotik\":\"Mikrotik\",\"Cisco\":\"Cisco\",\"Ubiquiti\":\"Ubiquiti\"}',NULL,'','',NULL,NULL,2,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(423,28,1,'vendor_model','1','Vendedor/Modelo','','Vendedor/Modelo',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(424,28,1,'partners','25','Socios','','socios','[]',NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,4,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(425,28,1,'location_id','22','Ubicación','','Seleccione la ubicación',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Location\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,5,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(426,28,1,'physical_address','1','Dirección física','','Dirección física',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(427,28,1,'ip_host','1','IP/Host','','0.0.0.0',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(428,28,1,'authorization_accounting','22','Autorización / Contabilidad ','','Ninguno/Ninguno','\"PPP(Secrets)\\/API Acounting\"','{\"PPP(Secrets)\\/API Acounting\":\"PPP(Secrets)\\/API Acounting\",\"Hostpot(Users)\\/API accounting\":\"Hostpot(Users)\\/API accounting\",\"Hostopt(Radius)\\/Radius\":\"Hostopt(Radius)\\/Radius\"}',NULL,'','',NULL,NULL,8,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(429,28,1,'secret_radius','1','Radius  Secreto','','',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(430,28,1,'nas_ip','1','NAS IP','','0.0.0.0',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(431,28,1,'pool','1','Pool','','0.0.0.0/8',NULL,'[]',NULL,'','',NULL,NULL,11,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:25'),(432,29,1,'active','16','Habilitar API','','','false','[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(433,29,1,'login_api','1','Usuario (API)','','',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(434,29,1,'password_api','9','Contraseña (API)','','contraseña',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(435,29,1,'port_api','1','Puerto (API)','','8730',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(436,29,1,'shaper_active','16','Habilitar Shaper','','','false','[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(437,29,1,'shaper','22','Shaper','','Seleccionar Shaper','\"Este enrutador\"','{\"Este enrutador\":\"Este enrutador\"}',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(438,29,1,'shaping_type','22','Tipo de shaping','','Seleccionar el tipo de shaping','\"Simple queue(Con \\u00e1rbol de cola)\"','{\"Simple queue(Con \\u00e1rbol de cola)\":\"Simple queue(Con \\u00e1rbol de cola)\"}',NULL,'','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(439,29,1,'rule_wireless_access_list','18','Lista de accesos a Morosos','','','false','[]',NULL,'','','1','{\"url_redirect\":{\"field\":\"url_redirect\",\"type\":\"input-string\",\"value\":null,\"label\":\"Url de Redirecci\\u00f3n\",\"placeholder\":\"http:\\/\\/ejemplo.com\",\"position\":1},\"ip_redirect\":{\"field\":\"ip_redirect\",\"type\":\"input-string\",\"value\":null,\"label\":\"IP de redirecci\\u00f3n\",\"placeholder\":\"0.0.0.0\",\"position\":1},\"ips_with_comma_permited\":{\"field\":\"ips_with_comma_permited\",\"type\":\"input-string\",\"value\":null,\"label\":\"IPs\",\"placeholder\":\"IPs de sitios permitidos separados por coma,sin espacios\",\"position\":1}}',13,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(440,29,0,'url_redirect','30','','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(441,29,0,'ip_redirect','30','','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(442,29,0,'ips_with_comma_permited','30','','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(443,29,0,'port_redirect','30','','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(444,29,1,'rule_address_list_mobility_client','16','Movilidad de Clientes de la Address-List','','','false','[]',NULL,'','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(445,29,1,'bloking_rules','16','Reglas de Bloqueo','','','false','[]',NULL,'','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(446,31,1,'title','1','Título','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(447,31,1,'network','4','Red','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(448,31,1,'bm','4','BM','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(449,31,1,'allow_usage_network','16','Permitir el Uso del network y el Broadcast','','','false','[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(450,31,1,'comment','1','Comentario','','',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(451,31,1,'location_id','22','Ubicación','','Seleccionar la ubicación',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Location\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(452,31,1,'network_category','22','Categoría de Red','','Seleccionar la categoría de red',NULL,'{\"Dev\":\"Dev\",\"Coorporativa\":\"Coorporativa\",\"Test\":\"Test\",\"Producci\\u00f3n\":\"Producci\\u00f3n\"}',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(453,31,1,'network_type','4','Categoría de Red','','',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(454,31,1,'type_of_use','22','Tipo de Uso','','Seleccionar tipo de uso',NULL,'{\"Estatico\":\"Estatico\",\"Pool\":\"Pool\"}',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(455,32,1,'network','1','Red','','0.0.0.0',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(456,32,1,'bm','22','BM','','Seleccionar máscara de Red',NULL,'{\"8\":\"8 (255.0.0.0 - 16777214 hosts, 16777216 IP)\",\"9\":\"9 (255.128.0.0 - 8388606 hosts, 8388608 IP)\",\"10\":\"10 (255.192.0.0 - 4194302 hosts, 4194304 IP)\",\"11\":\"11 (255.224.0.0 - 2097150 hosts, 2097152 IP)\",\"12\":\"12 (255.240.0.0 - 1048574 hosts, 1048576 IP)\",\"13\":\"13 (255.248.0.0 - 524286 hosts, 524288 IP)\",\"14\":\"14 (255.252.0.0 - 262142 hosts, 262144 IP)\",\"15\":\"15 (255.254.0.0 - 131070 hosts, 131072 IP)\",\"16\":\"16 (255.255.0.0 - 65534 hosts, 65536 IP)\",\"17\":\"17 (255.255.128.0 - 32766 hosts, 32768 IP)\",\"18\":\"18 (255.255.192.0 - 16382 hosts, 16384 IP)\",\"19\":\"19 (255.255.224.0 - 8190 hosts, 8192 IP)\",\"20\":\"20 (255.255.240.0 - 4094 hosts, 4096 IP)\",\"21\":\"21 (255.255.248.0 - 2046 hosts, 2048 IP)\",\"22\":\"22 (255.255.252.0 - 1022 hosts, 1024 IP)\",\"23\":\"23 (255.255.254.0 - 510 hosts, 512 IP)\",\"24\":\"24 (255.255.255.0 - 254 hosts, 256 IP)\",\"25\":\"25 (255.255.255.128 - 126 hosts, 128 IP)\",\"26\":\"26 (255.255.255.192 - 62 hosts, 64 IP)\",\"27\":\"27 (255.255.255.224 - 30 hosts, 32 IP)\",\"28\":\"28 (255.255.255.240 - 14 hosts, 16 IP)\",\"29\":\"29 (255.255.255.248 - 6 hosts, 8 IP)\",\"30\":\"30 (255.255.255.252 - 2 hosts, 4 IP)\",\"31\":\"31 (255.255.255.254 - 0 hosts, 2 IP)\",\"32\":\"32 (255.255.255.255 - 0 hosts, 1 IP)\"}',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(457,32,1,'allow_usage_network','16','Permitir el Uso del network y el Broadcast','','','false','[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(458,32,1,'title','1','Título','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(459,32,1,'comment','1','Comentario','','',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(460,32,1,'location_id','22','Ubicación','','Seleccionar la ubicación',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Location\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(461,32,1,'network_category','22','Categoría de Red','','Seleccionar la categoría de red',NULL,'{\"Dev\":\"Dev\",\"Coorporativa\":\"Coorporativa\",\"Test\":\"Test\",\"Producci\\u00f3n\":\"Producci\\u00f3n\"}',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(462,32,1,'network_type','22','Tipo de Red','','Seleccionar tipo de red',NULL,'{\"RootNet\":\"RootNet\",\"EndNet\":\"EndNet\"}',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(463,32,1,'type_of_use','22','Tipo de uso','','Seleccionar tipo de uso',NULL,'{\"Estatico\":\"Estatico\",\"Pool\":\"Pool\"}',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(464,33,1,'network_calculator','1','Red','','0.0.0.0',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(465,33,1,'bm_calculator','22','BM','','Mascara de Red',NULL,'{\"8\":\"8 (255.0.0.0 - 16777214 hosts, 16777216 IP)\",\"9\":\"9 (255.128.0.0 - 8388606 hosts, 8388608 IP)\",\"10\":\"10 (255.192.0.0 - 4194302 hosts, 4194304 IP)\",\"11\":\"11 (255.224.0.0 - 2097150 hosts, 2097152 IP)\",\"12\":\"12 (255.240.0.0 - 1048574 hosts, 1048576 IP)\",\"13\":\"13 (255.248.0.0 - 524286 hosts, 524288 IP)\",\"14\":\"14 (255.252.0.0 - 262142 hosts, 262144 IP)\",\"15\":\"15 (255.254.0.0 - 131070 hosts, 131072 IP)\",\"16\":\"16 (255.255.0.0 - 65534 hosts, 65536 IP)\",\"17\":\"17 (255.255.128.0 - 32766 hosts, 32768 IP)\",\"18\":\"18 (255.255.192.0 - 16382 hosts, 16384 IP)\",\"19\":\"19 (255.255.224.0 - 8190 hosts, 8192 IP)\",\"20\":\"20 (255.255.240.0 - 4094 hosts, 4096 IP)\",\"21\":\"21 (255.255.248.0 - 2046 hosts, 2048 IP)\",\"22\":\"22 (255.255.252.0 - 1022 hosts, 1024 IP)\",\"23\":\"23 (255.255.254.0 - 510 hosts, 512 IP)\",\"24\":\"24 (255.255.255.0 - 254 hosts, 256 IP)\",\"25\":\"25 (255.255.255.128 - 126 hosts, 128 IP)\",\"26\":\"26 (255.255.255.192 - 62 hosts, 64 IP)\",\"27\":\"27 (255.255.255.224 - 30 hosts, 32 IP)\",\"28\":\"28 (255.255.255.240 - 14 hosts, 16 IP)\",\"29\":\"29 (255.255.255.248 - 6 hosts, 8 IP)\",\"30\":\"30 (255.255.255.252 - 2 hosts, 4 IP)\",\"31\":\"31 (255.255.255.254 - 0 hosts, 2 IP)\",\"32\":\"32 (255.255.255.255 - 0 hosts, 1 IP)\"}',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(466,34,1,'ip','1','IP','','0.0.0.0',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(467,34,1,'used','1','Usado','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(468,34,1,'used_by','1','Usado Por','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(469,34,1,'title','1','Título','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(470,34,1,'hostname','1','Título','','',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(471,34,1,'location_id','1','Ubicación','','',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(472,34,1,'host_category','1','Categoría de Host','','',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(473,34,1,'comment','1','Categoría de Host','','',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(474,34,1,'client_id','1','Cliente','','',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(475,35,1,'apply_discount','16','Aplicar descuento','','aplicar-descuento','false','[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(476,35,1,'percent_discount','15','Porciento de Descuento','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(477,35,1,'apply_group_of_days','15','Aplicable en grupo de dias','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(478,36,1,'name','1','Nombre','','Nombre',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(479,36,1,'father_last_name','1','Apellido paterno','','Apellido paterno',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(480,36,1,'mother_last_name','1','Apellido materno','','Apellido materno',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(481,36,1,'phone','1','Teléfono','','Teléfono',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(482,36,1,'email','1','Correo','','email@dominio.com',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(483,36,1,'location','1','Ubicación','','Ubicación',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(484,36,1,'login_user','1','Usuario','','Usuario',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(485,36,1,'password','1','Contraseña','','Contraseña',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(486,37,1,'name','1','Nombre del socio','','título',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(487,38,1,'name','1','Ubicacion','','ubicacion',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(488,39,1,'name','1','Nombre','','Nombre',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(489,40,1,'name','1','Nombre','','Nombre',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(490,40,1,'state_id','23','Estado','','Seleccionar el Estado',NULL,NULL,'{\"model\":\"App\\\\Models\\\\State\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(491,41,1,'name','1','Nombre','','Nombre',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(492,41,1,'municipality_id','23','Municipio','','Seleccionar Municipio',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Municipality\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(493,42,1,'name','1','Nombre','','nombre',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:26'),(494,43,1,'dashboard_system_status','16','Ver Estado del Sistema','','','false','[]',NULL,'','',NULL,NULL,58,0,NULL,'dashboard',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(495,43,1,'dashboard_clientes','16','Clientes','','','false','[]',NULL,'','',NULL,NULL,59,0,NULL,'dashboard',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(496,43,1,'dashboard_payroll','16','Finanza','','','false','[]',NULL,'','',NULL,NULL,61,0,NULL,'dashboard',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(497,43,1,'dashboard_enrutador','16','Enrutadores','','','false','[]',NULL,'','',NULL,NULL,63,0,NULL,'dashboard',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(498,43,1,'plan_internet','18','Internet','','','false','[]',NULL,'','','1','{\"plan_view_internet\":{\"field\":\"plan_view_internet\",\"label\":\"Ver Listado de Planes de Internet\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"plan_add_internet\":{\"field\":\"plan_add_internet\",\"label\":\"Agregar Plan de Internet\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"plan_edit_internet\":{\"field\":\"plan_edit_internet\",\"label\":\"Editar Plan de Internet\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"plan_delete_internet\":{\"field\":\"plan_delete_internet\",\"label\":\"Eliminar Plan de Internet\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":4}}',66,0,NULL,'plan',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(499,43,0,'plan_view_internet','30','','','',NULL,'[]',NULL,'','',NULL,NULL,54,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(500,43,0,'plan_add_internet','30','','','',NULL,'[]',NULL,'','',NULL,NULL,52,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(501,43,0,'plan_edit_internet','30','','','',NULL,'[]',NULL,'','',NULL,NULL,48,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(502,43,0,'plan_delete_internet','30','','','',NULL,'[]',NULL,'','',NULL,NULL,47,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(503,43,1,'plan_voz','18','Voz','','','false','[]',NULL,'','','1','{\"plan_view_voz\":{\"field\":\"plan_view_voz\",\"label\":\"Ver Listado de Planes de Voz\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"plan_add_voz\":{\"field\":\"plan_add_voz\",\"label\":\"Agregar Plan de Voz\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"plan_edit_voz\":{\"field\":\"plan_edit_voz\",\"label\":\"Editar Plan de Voz\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"plan_delete_voz\":{\"field\":\"plan_delete_voz\",\"label\":\"Eliminar Plan de Voz\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":4}}',71,0,NULL,'plan',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(504,43,0,'plan_view_voz','30','','','',NULL,'[]',NULL,'','',NULL,NULL,45,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(505,43,0,'plan_add_voz','30','','','',NULL,'[]',NULL,'','',NULL,NULL,43,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(506,43,0,'plan_edit_voz','30','','','',NULL,'[]',NULL,'','',NULL,NULL,40,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(507,43,0,'plan_delete_voz','30','','','',NULL,'[]',NULL,'','',NULL,NULL,38,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(508,43,1,'plan_custom','18','Custom','','','false','[]',NULL,'','','1','{\"plan_view_custom\":{\"field\":\"plan_view_custom\",\"label\":\"Ver Listado de Planes Customs\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"plan_add_custom\":{\"field\":\"plan_add_custom\",\"label\":\"Agregar Plan Custom\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"plan_edit_custom\":{\"field\":\"plan_edit_custom\",\"label\":\"Editar Plan Custom\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"plan_delete_custom\":{\"field\":\"plan_delete_custom\",\"label\":\"Eliminar Plan Custom\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":4}}',74,0,NULL,'plan',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(509,43,0,'plan_view_custom','30','','','',NULL,'[]',NULL,'','',NULL,NULL,35,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(510,43,0,'plan_add_custom','30','','','',NULL,'[]',NULL,'','',NULL,NULL,36,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(511,43,0,'plan_edit_custom','30','','','',NULL,'[]',NULL,'','',NULL,NULL,37,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(512,43,0,'plan_delete_custom','30','','','',NULL,'[]',NULL,'','',NULL,NULL,39,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(513,43,1,'plan_paquetes','18','Paquete','','','false','[]',NULL,'','','1','{\"plan_view_paquetes\":{\"field\":\"plan_view_paquetes\",\"label\":\"Ver Listado de Planes de Paquetes\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"plan_add_paquetes\":{\"field\":\"plan_add_paquetes\",\"label\":\"Agregar Plan de Paquete\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"plan_edit_paquetes\":{\"field\":\"plan_edit_paquetes\",\"label\":\"Editar Plan de Paquete\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"plan_delete_paquetes\":{\"field\":\"plan_delete_paquetes\",\"label\":\"Eliminar Plan de Paquete\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":4}}',73,0,NULL,'plan',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(514,43,0,'plan_view_paquetes','30','','','',NULL,'[]',NULL,'','',NULL,NULL,41,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(515,43,0,'plan_add_paquetes','30','','','',NULL,'[]',NULL,'','',NULL,NULL,42,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(516,43,0,'plan_edit_paquetes','30','','','',NULL,'[]',NULL,'','',NULL,NULL,44,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(517,43,0,'plan_delete_paquetes','30','','','',NULL,'[]',NULL,'','',NULL,NULL,46,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(518,43,1,'crm_dashboard','16','Ver Dashboard del crm','','','false','[]',NULL,'','',NULL,NULL,56,0,NULL,'crm',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(519,43,1,'crm_crm','18','Crm','','','false','[]',NULL,'','','1','{\"crm_view_crm\":{\"field\":\"crm_view_crm\",\"label\":\"Ver Listado de Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"crm_add_crm\":{\"field\":\"crm_add_crm\",\"label\":\"Agregar Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"crm_edit_crm\":{\"field\":\"crm_edit_crm\",\"label\":\"Editar Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"crm_delete_crm\":{\"field\":\"crm_delete_crm\",\"label\":\"Eliminar Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":6}}',72,0,NULL,'crm',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(520,43,0,'crm_view_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,49,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(521,43,0,'crm_add_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,50,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(522,43,0,'crm_edit_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,51,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(523,43,0,'crm_delete_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,53,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(524,43,1,'crm_information','18','Informacion del Crm','','','false','[]',NULL,'','','1','{\"crm_information_view_tab_crm\":{\"field\":\"crm_information_view_tab_crm\",\"label\":\"Ver Pesta\\u00f1a de Informacion del Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"crm_information_geolocation_crm\":{\"field\":\"crm_information_geolocation_crm\",\"label\":\"Ver Geo Localizacion del Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1}}',75,0,NULL,'crm',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(525,43,0,'crm_information_view_tab_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,55,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(526,43,0,'crm_information_geolocation_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,34,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(527,43,1,'crm_document','18','Documentos del Crm','','','false','[]',NULL,'','','1','{\"crm_document_view_tab_crm\":{\"field\":\"crm_document_view_tab_crm\",\"label\":\"Ver Pesta\\u00f1a de Documentos del Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"crm_document_view_crm\":{\"field\":\"crm_document_view_crm\",\"label\":\"Ver Listado de Documentos del Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"crm_document_add_crm\":{\"field\":\"crm_document_add_crm\",\"label\":\"Agregar Documento al Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"crm_document_edit_crm\":{\"field\":\"crm_document_edit_crm\",\"label\":\"Editar Documento Subido al Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"crm_document_delete_crm\":{\"field\":\"crm_document_delete_crm\",\"label\":\"Eliminar Documento Subido al Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":6}}',76,0,NULL,'crm',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(528,43,0,'crm_document_view_tab_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,17,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(529,43,0,'crm_document_view_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,16,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(530,43,0,'crm_document_add_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(531,43,0,'crm_document_edit_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(532,43,0,'crm_document_delete_crm','30','','','',NULL,'[]',NULL,'','',NULL,NULL,18,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(533,43,1,'client_dashboard','16','Ver Dashboard del Cliente','','','false','[]',NULL,'','',NULL,NULL,57,0,NULL,'client',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(534,43,1,'client_client','18','Cliente','','','false','[]',NULL,'','','1','{\"client_view_client\":{\"field\":\"client_view_client\",\"label\":\"Ver Listado de Clientes\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"client_add_client\":{\"field\":\"client_add_client\",\"label\":\"Agregar Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"client_edit_client\":{\"field\":\"client_edit_client\",\"label\":\"Editar Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"client_delete_client\":{\"field\":\"client_delete_client\",\"label\":\"Eliminar Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":4}}',60,0,NULL,'client',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(535,43,0,'client_view_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(536,43,0,'client_add_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(537,43,0,'client_edit_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(538,43,0,'client_delete_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(539,43,1,'client_information','18','Informacion del Cliente','','','false','[]',NULL,'','','1','{\"client_information_view_tab_client\":{\"field\":\"client_information_view_tab_client\",\"label\":\"Ver Pesta\\u00f1a de Informacion del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"client_information_geolocation_client\":{\"field\":\"client_information_geolocation_client\",\"label\":\"Ver Geo Localizacion del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1}}',62,0,NULL,'client',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(540,43,0,'client_information_view_tab_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(541,43,0,'client_information_geolocation_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(542,43,1,'client_service','18','Servicio del Cliente','','','false','[]',NULL,'','','1','{\"client_service_view_tab_client\":{\"field\":\"client_service_view_tab_client\",\"label\":\"Ver Pesta\\u00f1a de Servicio del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1}}',64,0,NULL,'client',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(543,43,0,'client_service_view_tab_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(544,43,1,'client_service_internet','18','Servicio de Internet del Cliente','','','false','[]',NULL,'','','1','{\"client_service_internet_view_client\":{\"field\":\"client_service_internet_view_client\",\"label\":\"Ver Listado de Servicio de Internet del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"client_service_internet_add_client\":{\"field\":\"client_service_internet_add_client\",\"label\":\"Agregar Servicio de Internet al Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"client_service_internet_edit_client\":{\"field\":\"client_service_internet_edit_client\",\"label\":\"Editar Servicio de Internet del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"client_service_internet_delete_client\":{\"field\":\"client_service_internet_delete_client\",\"label\":\"Eliminar Servicio de Internet del Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":6}}',69,0,NULL,'client',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(545,43,0,'client_service_internet_view_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(546,43,0,'client_service_internet_add_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(547,43,0,'client_service_internet_edit_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(548,43,0,'client_service_internet_delete_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(549,43,1,'client_service_voz','18','Servicio de Voz del Cliente','','','false','[]',NULL,'','','1','{\"client_service_voz_view_client\":{\"field\":\"client_service_voz_view_client\",\"label\":\"Ver Listado de Servicio de Voz del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"client_service_voz_add_client\":{\"field\":\"client_service_voz_add_client\",\"label\":\"Agregar Servicio de Voz al Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"client_service_voz_edit_client\":{\"field\":\"client_service_voz_edit_client\",\"label\":\"Editar Servicio de Voz del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"client_service_voz_delete_client\":{\"field\":\"client_service_voz_delete_client\",\"label\":\"Eliminar Servicio de Voz del Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":7}}',70,0,NULL,'client',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(550,43,0,'client_service_voz_view_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(551,43,0,'client_service_voz_add_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(552,43,0,'client_service_voz_edit_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,19,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(553,43,0,'client_service_voz_delete_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,20,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(554,43,1,'client_service_bundle','18','Servicio de Paquetes del Cliente','','','false','[]',NULL,'','','1','{\"client_service_bundle_view_client\":{\"field\":\"client_service_bundle_view_client\",\"label\":\"Ver Listado de Servicio de Paquete del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"client_service_bundle_add_client\":{\"field\":\"client_service_bundle_add_client\",\"label\":\"Agregar Servicio de Paquete al Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"client_service_bundle_edit_client\":{\"field\":\"client_service_bundle_edit_client\",\"label\":\"Editar Servicio de Paquete del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"client_service_bundle_delete_client\":{\"field\":\"client_service_bundle_delete_client\",\"label\":\"Eliminar Servicio de Paquete del Crm\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":6}}',65,0,NULL,'client',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(555,43,0,'client_service_bundle_view_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,21,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(556,43,0,'client_service_bundle_add_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,22,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(557,43,0,'client_service_bundle_edit_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,23,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(558,43,0,'client_service_bundle_delete_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,24,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(559,43,1,'client_payroll','18','Facturacion del Cliente','','','false','[]',NULL,'','','1','{\"client_payroll_view_tab_client\":{\"field\":\"client_payroll_view_tab_client\",\"label\":\"Ver Pesta\\u00f1a de Facturacion del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1}}',77,0,NULL,'client',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(560,43,0,'client_payroll_view_tab_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,25,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(561,43,1,'client_payroll_payment','18','Pago del Cliente','','','false','[]',NULL,'','','1','{\"client_payroll_payment_view_client\":{\"field\":\"client_payroll_payment_view_client\",\"label\":\"Ver Listado de Pagos del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"client_payroll_payment_add_client\":{\"field\":\"client_payroll_payment_add_client\",\"label\":\"Agregar Pago al Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"client_payroll_payment_edit_client\":{\"field\":\"client_payroll_payment_edit_client\",\"label\":\"Editar Pago del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"client_payroll_payment_delete_client\":{\"field\":\"client_payroll_payment_delete_client\",\"label\":\"Eliminar Pago del Cliente\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":6}}',68,0,NULL,'client',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(562,43,0,'client_payroll_payment_view_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,26,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(563,43,0,'client_payroll_payment_add_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,27,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(564,43,0,'client_payroll_payment_edit_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,28,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(565,43,0,'client_payroll_payment_delete_client','30','','','',NULL,'[]',NULL,'','',NULL,NULL,29,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(566,43,1,'router_router','18','Router','','','false','[]',NULL,'','','1','{\"router_view_router\":{\"field\":\"router_view_router\",\"label\":\"Ver Listado de Routers\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":1},\"router_add_router\":{\"field\":\"router_add_router\",\"label\":\"Agregar Router\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":2},\"router_edit_router\":{\"field\":\"router_edit_router\",\"label\":\"Editar router\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":3},\"router_delete_router\":{\"field\":\"router_delete_router\",\"label\":\"Eliminar router\",\"placeholder\":\"\",\"type\":\"input-checkbox\",\"value\":false,\"position\":4}}',67,0,NULL,'router',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(567,43,0,'router_view_router','30','','','',NULL,'[]',NULL,'','',NULL,NULL,30,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(568,43,0,'router_add_router','30','','','',NULL,'[]',NULL,'','',NULL,NULL,31,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(569,43,0,'router_edit_router','30','','','',NULL,'[]',NULL,'','',NULL,NULL,32,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(570,43,0,'router_delete_router','30','','','',NULL,'[]',NULL,'','',NULL,NULL,33,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:20'),(571,44,1,'hidden','16','Ocultar Cliente','','Escondido','false','[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(572,44,1,'customer_lead','22','Cliente potencial','','Seleccionar cliente potencial',NULL,NULL,'{\"model\":\"App\\\\Models\\\\ClientMainInformation\",\"id\":\"client_id\",\"text\":\"name\"}','','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(573,44,1,'assigned_to','22','Asignado a:','','Seleccionar trabajador',NULL,NULL,'{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\", \"scope\": \"notClientRole\"}','','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-08-26 16:41:27'),(574,44,1,'topic','1','Tema','','Tema',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(575,44,1,'priority','22','Prioridad','','Seleccionar Prioridad','\"Baja\"','{\"1\":\"Urgente\",\"2\":\"Alta\",\"3\":\"Normal\",\"4\":\"Baja\"}',NULL,'','',NULL,NULL,5,0,'\"3\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(576,44,1,'estado','22','Estado','','Seleccionar Estado','\"Baja\"','{\"Nuevo\":\"Nuevo\",\"Trabajo en curso\":\"Trabajo en curso\",\"Resuelto\":\"Resuelto\",\"Esperando al cliente\":\"Esperando al cliente\",\"Esperando al agente\":\"Esperando al agente\",\"Cerrado\":\"Cerrado\",\"Reciclado\":\"Reciclado\"}',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(577,44,1,'group','22','Grupo','','Seleccione el grupo','\"Cualquier\"','{\"Cualquier\":\"Cualquier\",\"IT\":\"IT\",\"Finanzas\":\"Finanzas\",\"Ventas\":\"Ventas\"}',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(578,44,1,'type','22','Tipo','','Seleccione el tipo','\"Pregunta\"','{\"Pregunta\":\"Pregunta\",\"Incidente\":\"Incidente\",\"Problema\":\"Problema\",\"Solicitud de funcion\":\"Solicitud de funcion\",\"Cliente potencial\":\"Cliente potencial\"}',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(579,44,1,'date_time','20','Fecha y Hora','','01/01/2021',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(580,44,1,'phone','1','Teléfono','','000-000-0000',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(581,44,1,'phone2','1','Teléfono 2','','000-000-0000',NULL,'[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(582,44,1,'colony_id','22','Colonia','','Seleccionar Colonia',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Colony\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(583,45,1,'customer_lead','23','Cliente','','Seleccionar cliente potencial',NULL,NULL,'{\"model\":\"App\\\\Models\\\\ClientMainInformation\",\"id\":\"client_id\",\"text\":\"client_name_with_fathers_names\"}','','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(584,45,1,'hidden','16','Escondido','','Escondido','false','[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:28'),(585,45,1,'assigned_to','22','Asignado a:','','Seleccionar trabajador',NULL,NULL,'{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\", \"scope\": \"notClientRole\"}','','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-08-26 16:41:27'),(586,45,1,'topic','1','Tema','','Tema',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(587,45,1,'priority','22','Prioridad','','Seleccionar Prioridad','\"3\"','{\"1\":\"Urgente\",\"2\":\"Alta\",\"3\":\"Normal\",\"4\":\"Baja\"}',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(588,45,1,'group','22','Grupo','','Seleccione el grupo','\"Cualquier\"','{\"Cualquier\":\"Cualquier\",\"IT\":\"IT\",\"Finanzas\":\"Finanzas\",\"Ventas\":\"Ventas\"}',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(589,45,1,'type','22','Tipo','','Seleccione el tipo','\"Pregunta\"','{\"Pregunta\":\"Pregunta\",\"Incidente\":\"Incidente\",\"Problema\":\"Problema\",\"Solicitud de funcion\":\"Solicitud de funcion\",\"Cliente potencial\":\"Cliente potencial\"}',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(590,45,1,'message','5','Mensaje','','Texto',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(591,45,1,'date_time','20','Fecha y Hora','','',NULL,'[]',NULL,'','',NULL,NULL,9,0,'\"now\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(592,45,1,'phone','1','Teléfono','','000-000-0000',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(593,45,1,'phone2','1','Teléfono 2','','000-000-0000',NULL,'[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(594,45,1,'colony_id','23','Colonia','','Seleccionar Colonia',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Colony\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(595,45,1,'attachments','7','Adjuntar','','Adjuntar',NULL,'[]',NULL,'','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:21'),(596,46,1,'message','5','Mensaje','','Texto',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(597,46,1,'attachments','7','Adjuntar','','Dirección',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(598,30,1,'meganet_config_ip_address','1','IP/Host Meganet','','IP/Host Meganet',NULL,NULL,NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(599,30,1,'custom_config_name_parent_router','1','Queue Nombre Hilo Padre','','Queue Nombre Hilo Padre',NULL,NULL,NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(600,30,1,'custom_config_comment_parent_router','1','Queue comentario Hilo Padre','','Queue comentario Hilo Padre',NULL,NULL,NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(601,30,1,'custom_config_comment_sun_router','1','Queue comentario Hilo Hijo','','Queue comentario Hilo Hijo',NULL,NULL,NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(602,30,1,'mikrotik_config_server_pppoe_name','1','Nombre del Servidor Ppoe','','Nombre del Servidor Ppoe',NULL,NULL,NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(603,30,1,'mikrotik_config_server_pppoe_interface','1','Nombre Interface para Servidor Ppoe','','Nombre Interface para Servidor Ppoe',NULL,NULL,NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(604,30,1,'mikrotik_config_server_pppoe_mtu','1','Servidor Ppoe MTU','','Servidor Ppoe MTU',NULL,NULL,NULL,'','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(605,30,1,'mikrotik_config_server_pppoe_mru','1','Servidor Ppoe MRU','','Servidor Ppoe MRU',NULL,NULL,NULL,'','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(606,30,1,'mikrotik_config_server_pppoe_profile','1','Servidor Ppoe Nombre del Perfil','','Servidor Ppoe Nombre del Perfil',NULL,NULL,NULL,'','',NULL,NULL,17,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(607,30,1,'mikrotik_config_server_ppp_profile','1','Nombre Interface para Servidor Ppp','','Nombre Interface para Servidor Pppp',NULL,NULL,NULL,'','',NULL,NULL,19,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(608,30,1,'mikrotik_config_server_ppp_local_address','1','Direccion local servidor Ppp','','Direccion local servidor Ppp',NULL,NULL,NULL,'','',NULL,NULL,21,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(609,30,1,'mikrotik_config_server_ppp_remote_address','1','Direccion remota servidor Ppp','','Direccion remota servidor Ppp',NULL,NULL,NULL,'','',NULL,NULL,23,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(610,30,1,'mikrotik_config_server_ppp_bridge','1','Nombre de la Interfaz puente','','Nombre de la Interfaz puente',NULL,NULL,NULL,'','',NULL,NULL,25,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:29'),(611,29,0,'port_redirect','30','','','puerto',NULL,NULL,NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:43:56','2024-04-08 14:51:20'),(612,17,0,'internet_id','22','Tarifas','','Seleccionar las tarifas ...',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Internet\",\"id\":\"id\",\"text\":\"title\"}','','',NULL,NULL,10,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(613,17,1,'description','1','Descripción','','',NULL,'[]',NULL,'','',NULL,NULL,11,1,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(614,17,1,'amount','15','Cantidad','','',NULL,'[]',NULL,'','',NULL,NULL,12,0,'1','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(615,17,1,'unity','15','Unidad','','',NULL,'[]',NULL,'','',NULL,NULL,13,0,'1','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(616,17,1,'price','15','Precio','','',NULL,'[]',NULL,'','',NULL,NULL,15,1,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(617,17,1,'pay_period','22','Período de Pago','','Seleccionar Período de Pago',NULL,'{\"Periodo 1\":\"Periodo 1\",\"Periodo 2\":\"Periodo 2\",\"Periodo 3\":\"Periodo 3\",\"Periodo 4\":\"Periodo 4\",\"Periodo 5\":\"Periodo 5\"}',NULL,'','',NULL,NULL,16,0,'\"Periodo 1\"','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(618,17,1,'start_date','20','Fecha de Inicio','','01/01/2021',NULL,'[]',NULL,'','',NULL,NULL,18,0,'\"now\"','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(619,17,1,'finish_date','20','Fecha en que termina','','01/01/2021',NULL,'[]',NULL,'','',NULL,NULL,17,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(620,17,1,'discount','18','Descuento','','','false','[]',NULL,'','','1','{\"discount_percent\":{\"field\":\"discount_percent\",\"label\":\"Porciento de descuento\",\"placeholder\":\"descuento\",\"type\":\"input-group-text\",\"inputGroup\":\"%\",\"value\":null,\"position\":1},\"start_date_discount\":{\"field\":\"start_date_discount\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Fecha de Inicio de Descuento\",\"placeholder\":\"01\\/01\\/2021\",\"position\":2},\"end_date_discount\":{\"field\":\"end_date_discount\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Fecha de Finalizaci\\u00f3n de Descuento\",\"placeholder\":\"01\\/01\\/2021\",\"position\":3},\"discount_message\":{\"field\":\"discount_message\",\"type\":\"input-string\",\"value\":null,\"label\":\"Mensaje de descuento\",\"placeholder\":\"entre su mensaje\",\"position\":4}}',19,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(621,17,0,'discount_percent','30','','','',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(622,17,0,'start_date_discount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(623,17,0,'end_date_discount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(624,17,0,'discount_message','30','','','',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(625,17,1,'estado','22','Estado','','Seleccionar el Estado',NULL,'{\"Activado\":\"Activado\",\"Desactivado\":\"Desactivado\",\"Pendiente\":\"Pendiente\"}',NULL,'','',NULL,NULL,20,0,'\"Pendiente\"','init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(627,17,1,'client_name','1','Usuario','','Nombre del usuario',NULL,'[]',NULL,'','',NULL,NULL,21,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-05-03 12:20:56'),(628,17,1,'password','8','Contraseña','','',NULL,'[]',NULL,'','',NULL,NULL,22,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(633,17,1,'ipv6','1','Ipv6','','',NULL,'[]',NULL,'','',NULL,NULL,23,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(634,17,1,'delegated_ipv6','1','Ipv6 Delegada','','',NULL,'[]',NULL,'','',NULL,NULL,24,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(635,17,1,'mac','1','Mac(s,)','','',NULL,'[]',NULL,'','',NULL,NULL,25,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(636,17,1,'portid','1','Port ID','','',NULL,'[]',NULL,'','',NULL,NULL,26,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(637,17,1,'payment_type','19','Tipo de pago','','Seleccione el tipo de pago..','false','{\"Pago al crear el servicio\":\"Pago al crear el servicio\",\"Pago al finalizar el servicio\":\"Pago al finalizar el servicio\",\"Pago diferido\":\"Pago diferido\"}',NULL,'','','option','{\"deferred_payment_in_month\":{\"field\":\"deferred_payment_in_month\",\"label\":\"Meses\",\"placeholder\":\"Seleccione en cuantos meses\",\"type\":\"select-component\",\"value\":null,\"depend\":\"Pago diferido\",\"options\":{\"1mes\":\"1mes\",\"3meses\":\"3meses\",\"6meses\":\"6meses\",\"9meses\":\"9meses\",\"12meses\":\"12meses\"},\"position\":1}}',27,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(638,17,0,'deferred_payment_in_month','30','','','',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(639,17,1,'cost_activation','15','Costo de activacion','','',NULL,'[]',NULL,'','',NULL,NULL,28,1,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(640,16,0,'bundle_id','22','Tarifas','','Seleccionar las tarifas ...',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Bundle\",\"id\":\"id\",\"text\":\"title\"}','','',NULL,NULL,17,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(641,16,1,'bundle_description','1','Descripción','','',NULL,'[]',NULL,'','',NULL,NULL,19,0,NULL,'bundle_service_option',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(642,16,1,'bundle_price','15','Precio','','',NULL,'[]',NULL,'','',NULL,NULL,24,1,NULL,'bundle_service_option',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-11-12 11:26:27'),(643,16,1,'bundle_estado','22','Estado','','Seleccionar estado del paquete',NULL,'{\"Activado\":\"Activado\",\"Desactivado\":\"Desactivado\",\"Pendiente\":\"Pendiente\"}',NULL,'','',NULL,NULL,29,0,'\"Pendiente\"','bundle_service_option',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(644,16,1,'bundle_pay_period','22','Período de Pago','','Seleccionar periodo de pago',NULL,'{\"Periodo 1\":\"Periodo 1\",\"Periodo 2\":\"Periodo 2\",\"Periodo 3\":\"Periodo 3\",\"Periodo 4\":\"Periodo 4\",\"Periodo 5\":\"Periodo 5\"}',NULL,'','',NULL,NULL,31,0,'\"Periodo 1\"','bundle_service_option',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(645,16,1,'bundle_discount','18','Descuento','','','false','[]',NULL,'','','1','{\"bundle_discount_percent\":{\"field\":\"bundle_discount_percent\",\"label\":\"Porciento de descuento\",\"placeholder\":\"descuento\",\"type\":\"input-group-text\",\"inputGroup\":\"%\",\"value\":null,\"position\":1},\"bundle_start_date_discount\":{\"field\":\"bundle_start_date_discount\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Fecha de Inicio de Descuento\",\"placeholder\":\"01\\/01\\/2021\",\"position\":2},\"bundle_end_date_discount\":{\"field\":\"bundle_end_date_discount\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Fecha de Finalizaci\\u00f3n de Descuento\",\"placeholder\":\"01\\/01\\/2021\",\"position\":3},\"bundle_discount_message\":{\"field\":\"bundle_discount_message\",\"type\":\"input-string\",\"value\":null,\"label\":\"Mensaje de descuento\",\"placeholder\":\"entre su mensaje\",\"position\":4}}',36,0,NULL,'bundle_service_option',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(646,16,0,'bundle_discount_percent','30','','','',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(647,16,0,'bundle_start_date_discount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(648,16,0,'bundle_end_date_discount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(649,16,0,'bundle_discount_message','30','','','',NULL,'[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(650,16,1,'bundle_contract_start_date','20','Inicio de Contrato','','01/01/2021',NULL,'[]',NULL,'','',NULL,NULL,16,0,'\"now\"','contract_information',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(651,16,1,'bundle_automatic_renewal','16','Renovacion Automatica','','','true','[]',NULL,'','',NULL,NULL,21,0,NULL,'contract_information',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(653,16,1,'plan_internet_client_name','1','Usuario','','Nombre del usuario',NULL,'[]',NULL,'','',NULL,NULL,15,0,NULL,'internet_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(654,16,1,'plan_internet_password','8','Contraseña','','',NULL,'[]',NULL,'','',NULL,NULL,22,0,NULL,'internet_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(659,16,1,'plan_internet_ipv6','1','Ipv6','','',NULL,'[]',NULL,'','',NULL,NULL,32,0,NULL,'internet_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(660,16,1,'plan_internet_delegated_ipv6','1','Ipv6 Delegada','','',NULL,'[]',NULL,'','',NULL,NULL,33,0,NULL,'internet_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(661,16,1,'plan_internet_mac','1','Mac(s,)','','',NULL,'[]',NULL,'','',NULL,NULL,34,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(662,16,1,'plan_internet_portid','1','Port ID','','',NULL,'[]',NULL,'','',NULL,NULL,35,0,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(663,16,1,'plan_voz_phone','1','Teléfono','','telefono',NULL,'[]',NULL,'','',NULL,NULL,13,0,NULL,'voz_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(664,16,1,'plan_voz_password','8','Contraseña','','',NULL,'[]',NULL,'','',NULL,NULL,20,0,NULL,'voz_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(665,16,1,'plan_voz_voise_device','22','Dispositivo de Voz','','Seleccionar dispositivo de voz',NULL,'{\"Ninguno\":\"Ninguno\",\"VoIP\":\"VoIP\"}',NULL,'','',NULL,NULL,23,0,NULL,'voz_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(666,16,1,'plan_voz_direction','22','Dirección','','Seleccionar dirección',NULL,'{\"Salientes\":\"Salientes\",\"Entrantes\":\"Entrantes\"}',NULL,'','',NULL,NULL,28,0,NULL,'voz_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(667,16,1,'plan_custom_hidden','3','-','','-',NULL,'[]',NULL,'','',NULL,NULL,14,0,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(668,16,1,'plan_custom_service_name','2','Servicio','','',NULL,'[]',NULL,'','',NULL,NULL,18,0,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(669,16,1,'plan_custom_price','2','Precio','','',NULL,'[]',NULL,'','',NULL,NULL,26,0,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(670,16,1,'plan_custom_user','1','Usuario','','usuario',NULL,'[]',NULL,'','',NULL,NULL,27,0,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(671,16,1,'plan_custom_password','1','Contraseña','','contraseña',NULL,'[]',NULL,'','',NULL,NULL,30,0,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(689,47,1,'type','1','Nombre','','Nombre',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:30'),(690,47,1,'active','16','Activo','','Activo','false','[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:30'),(691,25,1,'payment_method_id','22','Metodo de pago','','Seleccione el metodo de pago...',NULL,NULL,'{\"model\":\"App\\\\Models\\\\MethodOfPayment\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,7,0,'1','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(692,25,1,'amount','1','Cantidad','','cantidad',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(693,25,1,'receipt','1','# de Recibo','','',NULL,'[]',NULL,'','',NULL,NULL,9,1,'{\"request\":\"\\/cliente\\/get-receipt-for-client\"}','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(694,25,1,'payment_period','1','Periodo de pago','','periodo-de-pago',NULL,'[]',NULL,'','',NULL,NULL,10,0,'{\"request\":\"\\/get-payment-period\"}','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(695,25,1,'date_payment','2','Fecha de Pago','','fecha',NULL,'[]',NULL,'','',NULL,NULL,11,0,'{\"request\":\"\\/get-default-value\\/now-show\"}','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(696,25,1,'comment','1','Comentario','','comentario',NULL,'[]',NULL,'','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(697,25,1,'file','6','Ticket','','',NULL,'[]',NULL,'','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(698,25,1,'send_receipt_after_payment','16','Enviar Recibo después del Pago','','','true','[]',NULL,'','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(699,25,1,'enabled_payment_promise','18','Promesa de Pago','','','false','[]',NULL,'','','1','{\"first_court_date\":{\"field\":\"first_court_date\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Primer corte\",\"placeholder\":\"Fecha hasta\",\"default_value\":\"now\",\"position\":1},\"first_amount\":{\"field\":\"first_amount\",\"type\":\"input-number\",\"value\":null,\"label\":\"Primer monto\",\"placeholder\":\"0.0\",\"position\":2},\"second_court_date\":{\"field\":\"second_court_date\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Segundo corte\",\"placeholder\":\"Fecha hasta\",\"default_value\":\"now\",\"position\":3},\"second_amount\":{\"field\":\"second_amount\",\"type\":\"input-number\",\"value\":null,\"label\":\"Segundo monto\",\"placeholder\":\"0.0\",\"position\":4},\"third_court_date\":{\"field\":\"third_court_date\",\"type\":\"date-time-local\",\"value\":null,\"label\":\"Tercer corte\",\"placeholder\":\"Fecha hasta\",\"default_value\":\"now\",\"position\":5},\"third_amount\":{\"field\":\"third_amount\",\"type\":\"input-number\",\"value\":null,\"label\":\"Tercer monto\",\"placeholder\":\"0.0\",\"position\":6}}',15,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(700,25,0,'first_court_date','30','','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(701,25,0,'first_amount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(702,25,0,'second_court_date','30','','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(703,25,0,'second_amount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(704,25,0,'third_court_date','30','','','',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(705,25,0,'third_amount','30','','','',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:19'),(711,49,1,'name','1','Nombre','','Nombre',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:30'),(752,13,1,'user','10','Usuario WEB','','usuario',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-10-18 08:06:33'),(753,13,1,'password','8','Contraseña WEB','','contraseña',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-10-18 08:06:33'),(754,13,1,'estado','22','Estado','','Seleccionar el Estado',NULL,'{\"Nuevo\":\"Nuevo(Todav\\u00eda no conectado)\",\"Activo\":\"Activado\",\"Inactivo\":\"Inactivo(No puede utilizar los servicios)\",\"Bloqueado\":\"Bloqueado\"}',NULL,'','',NULL,NULL,7,0,'\"Nuevo\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(755,13,1,'type_of_billing_id','22','Tipo de facturacion','','Seleccionar Tipo de Facturación',NULL,NULL,'{\"model\":\"App\\\\Models\\\\TypeBilling\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,8,0,'1','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(756,13,1,'ift','22','Ift','','Seleccionar Ift',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Ift\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,9,0,'1','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(757,13,1,'name','1','Nombre','','nombre',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(758,13,1,'father_last_name','1','Apellido Paterno','','Apellido Parterno',NULL,'[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(759,13,1,'mother_last_name','1','Apellido Materno','','Apellido Materno',NULL,'[]',NULL,'','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(760,13,1,'email','1','Correo','','correo',NULL,'[]',NULL,'','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(761,13,1,'phone','1','Telefono','','telefono',NULL,'[]',NULL,'','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(762,13,1,'nif_pasaport','1','RFC/CURP','','',NULL,'[]',NULL,'','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-05-03 11:57:00'),(763,13,1,'phone2','1','Teléfono 2','','Teléfono',NULL,'[]',NULL,'','',NULL,NULL,16,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(764,13,1,'partner_id','22','Socios','','Seleccionar socios',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,17,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(765,13,1,'location_id','22','Ubicación','','Seleccionar la Ubicación',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Location\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,18,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(766,13,1,'street','1','Calle','','Calle',NULL,'[]',NULL,'','',NULL,NULL,20,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(767,13,1,'external_number','1','Número externo','','Mz',NULL,'[]',NULL,'','',NULL,NULL,21,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(768,13,1,'internal_number','1','Número interno','','Lt',NULL,'[]',NULL,'','',NULL,NULL,22,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(769,13,1,'colony_id','24','App\\Models\\ClientMainInformation','','Seleccionar Colonia',NULL,'[]',NULL,'','',NULL,NULL,23,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(770,13,0,'state_id','30','','','',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(771,13,0,'municipality_id','30','','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(772,13,1,'zip','1','Código Zip','','Código Zip',NULL,'[]',NULL,'','',NULL,NULL,19,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(773,13,0,'geo_data','1','Datos geográficos','','Datos geográficos',NULL,'[]',NULL,'','',NULL,NULL,24,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(774,13,1,'discharge_date','20','Fecha de alta','','',NULL,'[]',NULL,'','',NULL,NULL,25,1,'\"now\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(775,13,1,'activation_date','20','Fecha de Activación','','',NULL,'[]',NULL,'','',NULL,NULL,26,1,'\"now\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(776,53,1,'apply_group_of_months','22','Porciento descuento por Meses de débito','','Seleccione meses de débito.',NULL,NULL,'{\"model\":\"App\\\\Models\\\\SettingDebtPaymentClientCustom\",\"id\":\"percent_discount\",\"text\":\"apply_group_of_months\"}','','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(777,53,1,'payment_method_id','22','Metodo de pago','','Seleccione el metodo de pago...',NULL,NULL,'{\"model\":\"App\\\\Models\\\\MethodOfPayment\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,2,0,'1','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(778,54,1,'percent_discount','15','Porciento de Descuento','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(779,54,1,'apply_group_of_months','15','Meses','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(795,12,1,'user','10','Usuario','','usuario',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(796,12,1,'password','8','Contraseña','','Contraseña',NULL,'[]',NULL,'','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(797,12,1,'type_of_billing_id','22','Tipo de facturación','','Seleccionar Tipos de Facturación',NULL,NULL,'{\"model\":\"App\\\\Models\\\\TypeBilling\",\"id\":\"id\",\"text\":\"type\"}','','',NULL,NULL,5,0,'1','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(798,12,1,'ift','22','Ift','','Seleccionar Ift',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Ift\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,6,0,'1','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(799,12,1,'name','1','Nombre','','Nombre',NULL,'[]',NULL,'','',NULL,NULL,7,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(800,12,1,'father_last_name','1','Apellido Paterno','','Apellido Parterno',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(801,12,1,'mother_last_name','1','Apellido Materno','','Apellido Materno',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(802,12,1,'email','1','Correo','','correo',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(803,12,1,'phone','1','Teléfono','','Teléfono',NULL,'[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(804,12,1,'category','22','Categoría','','Seleccionar la Categoría',NULL,'{\"Particular\":\"Particular\",\"Empresa\":\"Empresa\"}',NULL,'','',NULL,NULL,12,0,'\"Particular\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(805,12,1,'activation_date','20','Fecha de Activación','','',NULL,'[]',NULL,'','',NULL,NULL,13,0,'\"now\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(806,12,1,'nif_pasaport','1','NIF / Pasaporte','','',NULL,'[]',NULL,'','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-05-03 11:59:31'),(807,12,1,'partner_id','22','Socios','','Seleccionar el Socio',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(808,12,1,'location_id','22','Ubicación','','Seleccionar ubicación',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Location\",\"id\":\"id\",\"text\":\"name\"}','','',NULL,NULL,16,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(809,12,1,'discharge_date','20','Fecha de alta','','',NULL,'[]',NULL,'','',NULL,NULL,17,1,'\"now\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(810,12,1,'street','1','Calle','','Calle',NULL,'[]',NULL,'','',NULL,NULL,18,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(811,12,1,'external_number','1','Número exterior','','Mz',NULL,'[]',NULL,'','',NULL,NULL,19,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(812,12,1,'internal_number','1','Número interior','','Lt',NULL,'[]',NULL,'','',NULL,NULL,20,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(813,12,1,'colony_id','24','Colonia','','Seleccionar Colonia',NULL,'[]',NULL,'','',NULL,NULL,21,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(814,12,0,'state_id','30','','','',NULL,'[]',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(815,12,0,'municipality_id','30','','','',NULL,'[]',NULL,'','',NULL,NULL,1,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(816,12,1,'zip','1','Código Zip','','Código Zip',NULL,'[]',NULL,'','',NULL,NULL,22,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(817,12,1,'geo_data','1','Datos geográficos','','Datos geográficos',NULL,'[]',NULL,'','',NULL,NULL,23,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(818,12,1,'modem_sn','1','S/N (SERIE MODEM)','','',NULL,'[]',NULL,'','',NULL,NULL,24,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(819,12,1,'phone2','1','Teléfono 2','','Teléfono',NULL,'[]',NULL,'','',NULL,NULL,25,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(820,12,1,'gpon_ont','11','GPON ONT','','',NULL,'[]',NULL,'Mirar / Fijar','',NULL,NULL,26,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(821,12,1,'power_dbm','15','Potencia en dbm','','Potencia en dbm',NULL,'[]',NULL,'','',NULL,NULL,27,0,NULL,'',NULL,'.01',0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(822,12,1,'original_password','1','Contraseña Original','','Contraseña Original',NULL,'[]',NULL,'','',NULL,NULL,28,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(823,12,1,'box_nomenclator','1','Nomenclatura de Caja','','Nomenclatura de Caja',NULL,'[]',NULL,'','',NULL,NULL,29,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(824,12,1,'user_films','1','Usuario Peliculas','','Usuario',NULL,'[]',NULL,'','',NULL,NULL,30,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(825,12,1,'password_film','1','Contraseña Peliculas','','Contraseña',NULL,'[]',NULL,'','',NULL,NULL,31,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(826,12,1,'rfc','1','RFC','','RFC',NULL,'[]',NULL,'','',NULL,NULL,32,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:31'),(827,12,1,'reinstatement','1','Reinstalación','','Reinstalación',NULL,'[]',NULL,'','',NULL,NULL,33,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(828,12,1,'social_id','1','Social ID','','Social ID',NULL,'[]',NULL,'','',NULL,NULL,34,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(829,12,1,'coverage_notes','1','Coverage Notes','','coverage-notes',NULL,'[]',NULL,'','',NULL,NULL,35,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(830,12,1,'comment','1','Comentario','','comentario',NULL,'[]',NULL,'','',NULL,NULL,36,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(831,12,0,'installation_on_time','22','El tecnico instalo en tiempo','','Seleccionar si fue Instalado',NULL,'[]',NULL,'','',NULL,NULL,37,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(832,12,0,'amount_technician_and_why','1','Cual fue el monto que cobro el tecnico y porque','','explique',NULL,'[]',NULL,'','',NULL,NULL,38,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(833,12,0,'doubt_signed_contract','22','Tiene dudas acerca del contrato que esta firmando','','Seleccionar si Tiene Dudas',NULL,'[]',NULL,'','',NULL,NULL,39,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(834,12,0,'technician_attencion','1','El tecnico le atendio con amabilidad y respeto si, no y porque','','El tecnico le atendio con amabilidad y respeto si, no y porque',NULL,'[]',NULL,'','',NULL,NULL,40,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(835,14,1,'connection_type','22','Tipo de Conexión','','Seleccionar tipo',NULL,'{\"olt\":\"olt\",\"wifi\":\"wifi\"}',NULL,'','',NULL,NULL,1,0,'\"olt\"','',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:32'),(836,14,1,'category','22','Categoría','','Seleccionar Categoría',NULL,'{\"Particular\":\"Particular\",\"Empresa\":\"Empresa\"}',NULL,'','',NULL,NULL,2,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-02-15 15:01:32'),(837,14,1,'modem_sn','1','S/N (SERIE MODEM)','','',NULL,'[]',NULL,'','',NULL,NULL,3,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(838,14,1,'gpon_ont','11','GPON ONT','','',NULL,'[]',NULL,'Mirar / Fijar','',NULL,NULL,4,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(839,14,1,'power_dbm','15','Potencia en dbm','','Potencia en dbm',NULL,'[]',NULL,'','',NULL,NULL,5,0,NULL,'',NULL,'.01',0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(840,14,1,'original_password','1','Contraseña Original','','Contraseña Original',NULL,'[]',NULL,'','',NULL,NULL,6,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(842,14,1,'user_film','1','Usuario Peliculas','','Usuario',NULL,'[]',NULL,'','',NULL,NULL,8,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(843,14,1,'password_film','1','Contraseña Peliculas','','Contraseña',NULL,'[]',NULL,'','',NULL,NULL,9,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(844,14,1,'password_wifi','1','Contraseña de Wifi','','Contraseña',NULL,'[]',NULL,'','',NULL,NULL,10,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(845,14,1,'reinstatement','1','Reinstalación','','Reinstalación',NULL,'[]',NULL,'','',NULL,NULL,11,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:17'),(846,14,1,'social_id','1','Social ID','','Social ID',NULL,'[]',NULL,'','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(847,14,1,'comment','1','Comentario','','comentario',NULL,'[]',NULL,'','',NULL,NULL,13,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(848,14,1,'installation_on_time','22','El técnico instalo en tiempo','','Elija una opción',NULL,'{\"1\":\"Si\",\"0\":\"No\"}',NULL,'','',NULL,NULL,14,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(849,14,1,'amount_technician_and_why','1','Cual fue el monto que cobro el técnico y porque','','explique',NULL,'[]',NULL,'','',NULL,NULL,15,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(850,14,1,'doubt_signed_contract','22','Tiene dudas acerca del contrato que esta firmando','','Elija una opción',NULL,'{\"1\":\"Si\",\"0\":\"No\"}',NULL,'','',NULL,NULL,16,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(851,14,1,'technician_attencion','1','El técnico le atendio con amabilidad y respeto si, no y porque','','El técnico le atendió con amabilidad y respeto si, no y porque',NULL,'[]',NULL,'','',NULL,NULL,17,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:18'),(869,30,1,'meganet_config_ip_address_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:19','2024-02-15 15:01:32'),(870,30,1,'custom_config_name_parent_router_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:19','2024-02-15 15:01:32'),(871,30,1,'custom_config_comment_parent_router_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,6,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:19','2024-02-15 15:01:32'),(872,30,1,'custom_config_comment_sun_router_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,8,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:19','2024-02-15 15:01:32'),(873,30,1,'mikrotik_config_server_pppoe_name_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,10,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:20','2024-02-15 15:01:32'),(874,30,1,'mikrotik_config_server_pppoe_interface_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,12,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:20','2024-02-15 15:01:32'),(875,30,1,'mikrotik_config_server_pppoe_mtu_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,14,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:20','2024-02-15 15:01:32'),(876,30,1,'mikrotik_config_server_pppoe_mru_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,16,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:20','2024-02-15 15:01:32'),(877,30,1,'mikrotik_config_server_pppoe_profile_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,18,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:20','2024-02-15 15:01:32'),(878,30,1,'mikrotik_config_server_ppp_profile_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,20,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:20','2024-02-15 15:01:32'),(879,30,1,'mikrotik_config_server_ppp_local_address_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,22,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:20','2024-02-15 15:01:32'),(880,30,1,'mikrotik_config_server_ppp_remote_address_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,24,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:20','2024-02-15 15:01:32'),(881,30,1,'mikrotik_config_server_ppp_bridge_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,26,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:20','2024-02-15 15:01:32'),(882,8,1,'zip','1','Codigo Postal','','codigo-postal',NULL,'[]',NULL,'','',NULL,NULL,19,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 16:17:59'),(883,9,1,'street','1','Calle','','calle',NULL,'[]',NULL,'','',NULL,NULL,18,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(884,9,1,'external_number','1','Numero Exterior','','Mz',NULL,'[]',NULL,'','',NULL,NULL,19,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(885,9,1,'internal_number','1','Numero Interior','','Lt',NULL,'[]',NULL,'','',NULL,NULL,20,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 14:51:16'),(886,3,1,'bandwidth_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,16,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:21','2024-04-08 14:51:16'),(887,3,1,'cost_activation_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,19,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:21','2024-04-08 14:51:16'),(888,3,1,'cost_instalation_enable','16',NULL,NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,21,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:21','2024-04-08 14:51:16'),(889,3,1,'mac_enable','16','Mac',NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,22,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:16'),(890,3,1,'router_id_enable','16','Ip',NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,23,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:16'),(891,3,1,'serial_number_enable','16','Numero de serie',NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,24,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:16'),(892,3,1,'user_enable','16','Usuario',NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,25,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:16'),(893,3,1,'password_enable','16','Contraseña',NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,26,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:16'),(895,16,0,'plan_custom_ipv4','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:18'),(896,16,0,'plan_custom_additional_ipv4','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:18'),(897,16,0,'plan_custom_ipv4_pool','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:18'),(898,16,1,'plan_custom_mac','1','Mac(s)',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,38,NULL,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:18'),(899,16,1,'plan_custom_serial_number','1','Número de Serie',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,39,NULL,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:18'),(900,16,1,'plan_custom_serial_number','1','Número de Serie',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,40,NULL,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:18'),(901,16,1,'plan_custom_bandwidth','15','Ancho de Banda',NULL,'Ancho de Banda',NULL,NULL,NULL,NULL,NULL,NULL,NULL,41,NULL,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:18'),(902,16,1,'plan_custom_cost_activation','15','Costo de activación',NULL,'Costo de activación',NULL,NULL,NULL,NULL,NULL,NULL,NULL,42,NULL,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:18'),(903,16,1,'plan_custom_cost_instalation','15','Costo de instalación',NULL,'Costo de instalación',NULL,NULL,NULL,NULL,NULL,NULL,NULL,43,NULL,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:22','2024-04-08 14:51:18'),(904,9,0,'geodata','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:24','2024-04-08 14:51:16'),(905,32,1,'router_id','22','Router',NULL,'Seleccionar el router',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Router\",\"id\":\"id\",\"text\":\"title\"}',NULL,NULL,NULL,NULL,7,0,NULL,'0',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:24','2024-04-08 14:51:20'),(906,31,1,'router_id','22','Router',NULL,'Seleccionar el router',NULL,NULL,'{\"model\":\"App\\\\Models\\\\Router\",\"id\":\"id\",\"text\":\"title\"}',NULL,NULL,NULL,NULL,7,0,NULL,'0',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:24','2024-04-08 14:51:20'),(907,17,1,'router_id','29',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'{\"ipv4_assignment\":{\"field\":\"ipv4_assignment\",\"type\":\"input-depend\"},\"ipv4\":{\"field\":\"ipv4\",\"type\":\"input-depend\"},\"additional_ipv4\":{\"field\":\"additional_ipv4\",\"type\":\"input-depend\"},\"ipv4_pool\":{\"field\":\"ipv4_pool\",\"type\":\"input-depend\"}}',14,NULL,NULL,'other',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:25','2024-04-08 14:51:18'),(908,17,0,'ipv4_assignment','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:25','2024-04-08 14:51:18'),(909,17,0,'ipv4','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:25','2024-04-08 14:51:18'),(910,17,0,'additional_ipv4','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:25','2024-04-08 14:51:18'),(911,17,0,'ipv4_pool','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:25','2024-04-08 14:51:18'),(912,16,1,'plan_internet_router_id','29',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'{\"plan_internet_ipv4_assignment\":{\"field\":\"plan_internet_ipv4_assignment\",\"type\":\"input-depend\"},\"plan_internet_ipv4\":{\"field\":\"plan_internet_ipv4\",\"type\":\"input-depend\"},\"plan_internet_additional_ipv4\":{\"field\":\"plan_internet_additional_ipv4\",\"type\":\"input-depend\"},\"plan_internet_ipv4_pool\":{\"field\":\"plan_internet_ipv4_pool\",\"type\":\"input-depend\"}}',25,0,NULL,'internet_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:25','2024-04-08 14:51:18'),(913,16,0,'plan_internet_ipv4_assignment','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,12,NULL,NULL,'internet_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:25','2024-04-08 14:51:18'),(914,16,0,'plan_internet_ipv4','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,7,NULL,NULL,'internet_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:25','2024-04-08 14:51:18'),(915,16,0,'plan_internet_additional_ipv4','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,6,NULL,NULL,'internet_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:25','2024-04-08 14:51:18'),(916,16,0,'plan_internet_ipv4_pool','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,NULL,NULL,'internet_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:25','2024-04-08 14:51:18'),(917,13,0,'geodata','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:26','2024-04-08 14:51:17'),(918,13,0,'address','1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:26','2024-04-08 14:51:17'),(919,9,0,'address','1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:26','2024-04-08 14:51:16'),(920,56,1,'command','1','Comando',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:27','2024-02-15 15:01:33'),(921,56,1,'frequency_id','22','Frecuencia',NULL,NULL,NULL,NULL,'{\"model\":\"App\\\\Models\\\\FrequencyCommand\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:27','2024-04-08 14:51:21'),(922,56,1,'execution_time','21','Hora de ejecución',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:27','2024-04-08 14:51:21'),(923,56,1,'command_description','5','Descripción',NULL,NULL,NULL,'{\"rows\":5}',NULL,NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:27','2024-04-08 14:51:21'),(924,56,1,'status','16','Estado',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:27','2024-04-08 14:51:21'),(925,8,0,'state_id','30','','','',NULL,'[]',NULL,'','',NULL,NULL,12,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 16:07:08'),(926,8,0,'municipality_id','30','','','',NULL,'[]',NULL,'','',NULL,NULL,20,0,NULL,'',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 16:08:52'),(927,8,1,'colony_id','24','App\\Models\\CrmMainInformation','','Seleccionar Colonia',NULL,'[]',NULL,'','',NULL,NULL,18,0,NULL,'',NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9',NULL,'2024-04-08 16:17:59'),(928,19,1,'user','1','Usuario',NULL,'usuario',NULL,NULL,NULL,NULL,NULL,'depend-checkbox',NULL,19,NULL,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:29','2024-04-08 14:51:19'),(929,19,1,'password','1','Contraseña','','contraseña',NULL,NULL,NULL,NULL,NULL,'depend-checkbox',NULL,20,NULL,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:29','2024-04-08 14:51:19'),(930,19,1,'mac','1','Mac(s)',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'depend-checkbox',NULL,21,NULL,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:29','2024-04-08 14:51:19'),(931,19,1,'serial_number','1','Número de Serie',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'depend-checkbox',NULL,22,NULL,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:29','2024-04-08 14:51:19'),(932,19,1,'bandwidth','15','Ancho de Banda',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'depend-checkbox',NULL,23,NULL,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:29','2024-04-08 14:51:19'),(933,19,1,'ip','19','Método de Asignación de IP','','Ninguno (enrutador asignará IP)','false','{\"IP Estatica\":\"IP Estatica\",\"Pool IP\":\"Pool IP\"}',NULL,NULL,NULL,'option','{\"ipv4\":{\"field\":\"ipv4\",\"label\":\"Dirección IPv4\",\"placeholder\":\"Selecione dirección Ipv4\",\"type\":\"select-2-component\",\"value\":null,\"depend\":\"IP Estatica\",\"options\":null,\"search\":{\"model\":\"App\\\\Models\\\\NetworkIp\",\"id\":\"id\",\"text\":\"ip\",\"filter\":[{\"field_relation\":\"network\",\"field\":\"type_of_use\",\"value\":\"Estatico\"},{\"field\":\"used\",\"value\":0},{\"or_where\":\"used_by\",\"value\":\"client_id\"}]},\"position\":1},\"additional_ipv4\":{\"field\":\"additional_ipv4\",\"label\":\"Red adicional IPv4\",\"placeholder\":\"Introducir Red\",\"type\":\"input-string\",\"value\":null,\"depend\":\"IP Estatica\",\"position\":2},\"ipv4_pool\":{\"field\":\"ipv4_pool\",\"label\":\"Ipv4 Pools\",\"placeholder\":\"Selecione Pool de Ipv4\",\"type\":\"select-component\",\"value\":null,\"depend\":\"Pool IP\",\"options\":null,\"search\":{\"model\":\"App\\\\Models\\\\Network\",\"id\":\"id\",\"text\":\"network\",\"filter\":[{\"field\":\"type_of_use\",\"value\":\"Pool\"}]},\"position\":0}}',24,0,NULL,'init',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:29','2024-04-08 14:51:19'),(934,19,0,'ipv4','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:29','2024-04-08 14:51:19'),(935,19,0,'additional_ipv4','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:29','2024-04-08 14:51:19'),(936,19,0,'ipv4_pool','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-09 17:45:29','2024-04-08 14:51:19'),(937,57,1,'label','1','Titulo del campo',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-15 15:01:21','2024-04-08 14:51:21'),(939,57,1,'type','22','Tipo de Campo',NULL,NULL,NULL,NULL,'{\"model\":\"App\\\\Models\\\\FieldType\",\"id\":\"id\",\"text\":\"name\",\"scope\":\"getFieldTypes\"}',NULL,NULL,NULL,NULL,7,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-15 15:01:21','2024-04-08 14:51:21'),(940,57,1,'include','16','Mostrar',NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,9,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-15 15:01:21','2024-04-08 14:51:21'),(941,57,1,'required','16','Requerido',NULL,NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,10,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-15 15:01:21','2024-04-08 14:51:21'),(942,16,1,'plan_custom_router_id','29',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'{\"plan_custom_ipv4_assignment\":{\"field\":\"plan_custom_ipv4_assignment\",\"type\":\"input-depend\"},\"plan_custom_ipv4\":{\"field\":\"plan_custom_ipv4\",\"type\":\"input-depend\"},\"plan_custom_additional_ipv4\":{\"field\":\"plan_custom_additional_ipv4\",\"type\":\"input-depend\"},\"plan_custom_ipv4_pool\":{\"field\":\"plan_custom_ipv4_pool\",\"type\":\"input-depend\"}}',37,NULL,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-16 05:07:03','2024-04-08 14:51:18'),(943,16,0,'plan_custom_ipv4_assignment','30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,NULL,'custom_service',NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-16 05:07:03','2024-04-08 14:51:18'),(944,57,0,'module_id','3',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-17 22:56:05','2024-04-08 14:51:21'),(945,57,1,'name','1','Nombre del Campo',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-17 22:56:05','2024-04-08 14:51:21'),(946,57,0,'options','3',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-23 13:39:27','2024-04-08 14:51:21'),(947,57,0,'additional_field','3',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-25 21:13:39','2024-04-08 14:51:21'),(950,57,1,'class_col','22','Seleccione clase',NULL,'Seleccione clase',NULL,'{\"full\":\"Completo\",\"partial\":\"Parcial\"}',NULL,NULL,NULL,NULL,NULL,8,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-02-29 05:29:07','2024-04-08 14:51:21'),(956,58,1,'module_id','3','Módulo',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-03-21 12:08:44','2024-03-21 12:08:44'),(957,58,1,'file','6','Archivo a importar',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-03-21 12:08:44','2024-03-21 12:08:44'),(958,13,1,'seller_id','22','Vendedor',NULL,'Selecciona Vendedor',NULL,NULL,'{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\", \"scope\": \"sellerRole\"}',NULL,NULL,NULL,NULL,27,NULL,'user_authenticated',NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-04-01 18:01:13','2024-08-26 16:41:27'),(959,13,1,'medium_id','22','Medio de venta',NULL,'Selecciona el medio de venta',NULL,NULL,'{\"model\":\"App\\\\Models\\\\MediumOfSale\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,28,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-04-01 18:01:13','2024-04-08 14:51:17'),(960,13,0,'phone3','1','Telefono 3',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,29,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-05-14 16:12:03','2024-05-14 16:12:03'),(965,61,1,'name','1','Nombre de Plantilla',NULL,'Nombre de Plantilla','',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-07-25 09:21:43','2024-07-25 09:21:43'),(966,61,1,'template','22','Selecciona Plantilla',NULL,'Selecciona Plantilla','',NULL,'{\"model\":\"App\\\\Models\\\\DocumentTemplate\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-07-25 09:21:43','2024-07-25 09:21:43'),(967,61,1,'html','3',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,999,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-07-25 09:21:43','2024-07-25 09:21:43'),(968,61,1,'type','22','Tipo de Documento',NULL,'Tipo de Documento','',NULL,'{\"model\":\"App\\\\Models\\\\DocumentTypeTemplate\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-07-29 12:36:39','2024-07-29 12:36:39'),(971,63,1,'name','1','Tipo de Plantilla',NULL,'Tipo de Plantilla','',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-07-29 12:36:39','2024-07-29 12:36:39'),(972,62,1,'type','33','Tipo de Plantilla',NULL,'Tipo de Plantilla','',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-07-29 12:36:40','2024-07-29 12:36:40'),(973,62,1,'template','30','Plantilla',NULL,'Plantilla','',NULL,NULL,NULL,NULL,NULL,NULL,999,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-07-29 12:36:40','2024-07-29 12:36:40'),(974,62,1,'html','3',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,999,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-07-29 12:36:40','2024-07-29 12:36:40'),(975,64,1,'title','1','Título',NULL,'Título',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-08-04 09:43:36','2024-08-04 09:43:36'),(976,64,1,'description','5','Descripción',NULL,'Descripción',NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-08-04 09:43:36','2024-08-04 09:43:36'),(977,64,1,'type','22','Tipo',NULL,'Seleccione Tipo','',NULL,'{\"model\":\"App\\\\Models\\\\ProjectType\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-08-04 09:43:36','2024-08-04 09:43:36'),(978,64,1,'partners','25','Socios',NULL,'Socios','',NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-08-04 09:43:36','2024-08-04 09:43:36'),(979,64,1,'project_lead','22','Project Lead',NULL,'Seleccione','',NULL,'{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\",\"scope\":\"leadProject\"}',NULL,NULL,NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-08-04 09:43:36','2024-08-04 09:43:36'),(980,64,1,'category','22','Categoría',NULL,'Default','',NULL,NULL,NULL,NULL,NULL,NULL,6,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-08-04 09:43:36','2024-08-04 09:43:36'),(981,64,1,'workflow','22','Flujo de Trabajo',NULL,'Flujo de Trabajo','',NULL,'{\"model\":\"App\\\\Models\\\\WorkFlow\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,7,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-08-04 09:43:36','2024-08-04 09:43:36'),(1008,65,1,'template_id','22','Selecciona Plantilla',NULL,'Selecciona Plantilla','',NULL,'{\"model\":\"App\\\\Models\\\\TemplateTask\",\"id\":\"id\",\"text\":\"title_template\"}',NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-12','col-12','2024-08-10 14:51:52','2024-09-17 12:52:23'),(1009,65,1,'title','1','Título',NULL,'Título',NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-12','col-12','2024-08-10 14:51:52','2024-08-10 14:51:52'),(1010,65,1,'description','5','Descripción',NULL,'Descripción',NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-12','col-12','2024-08-10 14:51:52','2024-08-10 14:51:52'),(1011,65,1,'project_id','22','Proyecto',NULL,'Selecciona Proyecto','',NULL,'{\"model\":\"App\\\\Models\\\\Project\",\"id\":\"id\",\"text\":\"title\"}',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'partial','col-12','col-12','2024-08-10 14:51:52','2024-08-20 20:47:40'),(1012,65,1,'assigned_to','41','Asignado a',NULL,'','',NULL,'{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\",\"scope\":\"notClientRole\"}',NULL,NULL,NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,0,'partial','col-12','col-12','2024-08-10 14:51:52','2024-09-22 16:35:56'),(1013,65,1,'priority','22','Prioridad',NULL,'','','{\"Alta\":\"Alta\",\"Media\":\"Media\",\"Baja\":\"Baja\"}',NULL,NULL,NULL,NULL,NULL,6,NULL,NULL,NULL,NULL,NULL,0,'partial','col-12','col-12','2024-08-10 14:51:52','2024-08-10 14:51:52'),(1014,65,1,'status','22','Estado',NULL,'','','{\"ToDo\":\"Por Hacer\",\"InProgress\":\"En Progreso\",\"Done\":\"Terminado\",\"PostponedByClient\":\"Pospuesto por el Cliente\"}',NULL,NULL,NULL,NULL,NULL,7,NULL,NULL,NULL,NULL,NULL,0,'partial','col-12','col-12','2024-08-10 14:51:52','2024-11-18 11:08:58'),(1015,65,1,'client_main_information_id','34','Cliente',NULL,'Por Favor introduzca 2 o mas caracteres',NULL,NULL,'{\"model\":\"App\\\\Models\\\\ClientMainInformation\",\"id\":\"id\",\"text\":\"name\",\"append\":\"client_name_with_fathers_names\"}',NULL,NULL,NULL,NULL,8,NULL,NULL,NULL,NULL,NULL,0,'full','col-12','col-12','2024-08-10 14:51:52','2024-08-10 14:51:52'),(1016,65,1,'partner_id','22','Socio',NULL,'','',NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,9,NULL,NULL,NULL,NULL,NULL,0,'partial','col-12','col-12','2024-08-10 14:51:52','2024-08-10 14:51:52'),(1017,65,1,'location_id','22','Ubicación',NULL,'','',NULL,'{\"model\":\"App\\\\Models\\\\Location\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,10,NULL,NULL,NULL,NULL,NULL,0,'partial','col-12','col-12','2024-08-10 14:51:52','2024-08-10 14:51:52'),(1018,65,1,'address','1','Dirección',NULL,'Dirección',NULL,NULL,NULL,NULL,NULL,NULL,NULL,11,NULL,NULL,NULL,NULL,NULL,0,'full','col-12','col-12','2024-08-10 14:51:52','2024-08-10 14:51:52'),(1019,65,1,'geo_data','38','Datos geográficos',NULL,'Datos geográficos',NULL,NULL,NULL,NULL,NULL,NULL,NULL,12,NULL,NULL,NULL,NULL,NULL,0,'full','col-12','col-12','2024-08-10 14:51:52','2024-08-10 14:51:52'),(1020,65,1,'template_verification','39','Plantilla de la lista de Verificación',NULL,'','',NULL,'{\"model\":\"App\\\\Models\\\\ListTemplateVerification\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,13,NULL,NULL,NULL,NULL,NULL,0,'full','col-form-label pr-2','col-sm-12','2024-08-10 14:51:52','2024-09-02 10:03:18'),(1021,65,1,'start_time','36','Fecha de Inicio',NULL,'','',NULL,NULL,NULL,NULL,NULL,NULL,14,NULL,NULL,NULL,NULL,NULL,0,'partial','col-form-label pr-2','col-sm-12','2024-08-10 14:51:52','2024-09-30 20:16:10'),(1022,65,1,'end_time','36','Fecha Fin',NULL,'','',NULL,NULL,NULL,NULL,NULL,NULL,15,NULL,NULL,NULL,NULL,NULL,0,'partial','col-form-label pr-2','col-sm-12','2024-08-10 14:51:52','2024-09-30 20:16:10'),(1025,68,1,'name','1','Nombre',NULL,'Nombre del Flujo de Trabajo',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-08-20 20:47:41','2024-08-20 20:47:41'),(1040,66,1,'project_id','22','Proyecto',NULL,'Selecciona Proyecto','',NULL,'{\"model\":\"App\\\\Models\\\\Project\",\"id\":\"id\",\"text\":\"title\"}',NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-02 10:03:17','2024-09-02 10:03:17'),(1041,66,1,'priority','22','Prioridad',NULL,'','','{\"Alta\":\"Alta\",\"Media\":\"Media\",\"Baja\":\"Baja\"}',NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-02 10:03:17','2024-09-02 10:03:17'),(1042,66,1,'status','22','Estado',NULL,'','','{\"ToDo\":\"Por Hacer\",\"InProgress\":\"En Progreso\",\"Done\":\"Terminado\",\"PostponedByClient\":\"Pospuesto por el Cliente\"}',NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-02 10:03:17','2024-11-18 11:08:58'),(1043,66,1,'partner_id','22','Socio',NULL,'','',NULL,'{\"model\":\"App\\\\Models\\\\Partner\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'partial','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-02 10:03:17','2024-09-02 10:03:17'),(1044,66,1,'client_main_information_id','34','Cliente',NULL,'Por Favor introduzca 2 o mas caracteres',NULL,NULL,'{\"model\":\"App\\\\Models\\\\ClientMainInformation\",\"id\":\"id\",\"text\":\"name\",\"append\":\"client_name_with_fathers_names\"}',NULL,NULL,NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,0,'full','col-form-label pr-2','col-sm-12','2024-09-02 10:03:17','2024-09-02 10:03:17'),(1045,66,1,'client_service_id','3','Servicio de Internet',NULL,'Por Favor introduzca 2 o mas caracteres',NULL,NULL,NULL,NULL,NULL,NULL,NULL,99999,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1046,66,1,'description','5','Descripción',NULL,'Descripción',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,NULL,NULL,NULL,NULL,NULL,0,'full','col-12','col-12','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1047,66,1,'file','6','Archivo Adjunto',NULL,'Archivo Adjunto',NULL,NULL,NULL,NULL,NULL,NULL,NULL,9,NULL,NULL,NULL,NULL,NULL,0,'full','col-12','col-12','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1048,66,1,'location_id','22','Ubicación',NULL,'','',NULL,'{\"model\":\"App\\\\Models\\\\Location\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,11,NULL,NULL,NULL,NULL,NULL,0,'full','col-12','col-12','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1049,66,1,'address','1','Dirección',NULL,'Dirección',NULL,NULL,NULL,NULL,NULL,NULL,NULL,12,NULL,NULL,NULL,NULL,NULL,0,'full','col-12','col-12','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1050,67,1,'assigned_to','41','Asignado a',NULL,'','',NULL,'{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\",\"scope\":\"notClientRole\"}',NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-form-label pr-2','col-sm-12','2024-09-02 10:03:18','2024-09-26 12:59:30'),(1051,67,1,'start_time','36','Fecha de Inicio',NULL,'','',NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-form-label pr-2','col-sm-12','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1052,67,1,'end_time','36','Fecha de Terminación',NULL,'','',NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-form-label pr-2','col-sm-12','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1053,67,1,'time_to_task_location','3','Tiempo hasta la Tarea',NULL,'0h 0m','',NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'full','col-form-label pr-2','col-sm-12','2024-09-02 10:03:18','2024-09-30 20:16:10'),(1054,67,1,'time_from_task_location','3','Tiempo desde la Tarea',NULL,'0h 0m','',NULL,NULL,NULL,NULL,NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,0,'full','col-form-label pr-2','col-sm-12','2024-09-02 10:03:18','2024-09-30 20:16:10'),(1055,69,1,'name','1','Nombre',NULL,'Nombre',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1056,69,1,'checks','3','Nombre',NULL,'Nombre',NULL,NULL,NULL,NULL,NULL,NULL,NULL,999,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1057,65,1,'checks','3','Nombre',NULL,'Nombre',NULL,NULL,NULL,NULL,NULL,NULL,NULL,999,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1058,67,1,'template_verification','3','Nombre',NULL,'Nombre',NULL,NULL,NULL,NULL,NULL,NULL,NULL,999,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1059,67,1,'checks','3','Nombre',NULL,'Nombre',NULL,NULL,NULL,NULL,NULL,NULL,NULL,999,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-02 10:03:18','2024-09-02 10:03:18'),(1062,70,1,'title_template','1','Título de la Plantilla',NULL,'Título de la Plantilla',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-16 11:25:00','2024-09-16 11:25:00'),(1063,70,1,'title_task','1','Título de la Tarea',NULL,'Título de la Tarea',NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-16 11:25:00','2024-09-16 11:25:00'),(1064,70,1,'project_id','22','Proyecto',NULL,'Selecciona Proyecto','',NULL,'{\"model\":\"App\\\\Models\\\\Project\",\"id\":\"id\",\"text\":\"title\"}',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-16 11:25:00','2024-09-16 11:25:00'),(1065,70,1,'template_verification_id','22','Lista de Verificación',NULL,'Selecciona Lista','',NULL,'{\"model\":\"App\\\\Models\\\\TemplateVerification\",\"id\":\"id\",\"text\":\"name\"}',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-16 11:25:00','2024-09-16 11:25:00'),(1066,70,1,'priority','22','Prioridad',NULL,'','','{\"Alta\":\"Alta\",\"Media\":\"Media\",\"Baja\":\"Baja\"}',NULL,NULL,NULL,NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-16 11:25:00','2024-09-16 11:25:00'),(1067,70,1,'description','5','Descripción',NULL,'Descripción',NULL,NULL,NULL,NULL,NULL,NULL,NULL,6,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-16 11:25:00','2024-09-16 11:25:00'),(1068,70,1,'assigned_to','35','Asignado a',NULL,'','',NULL,'{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\",\"scope\":\"notClientRole\"}',NULL,NULL,NULL,NULL,7,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-16 11:25:00','2024-09-16 11:25:00'),(1074,65,1,'estimated_time','22','Tiempo estimado',NULL,'Tiempo estimado en hrs',NULL,NULL,'{\"model\":\"App\\\\Models\\\\FrequencyEstimatedDedicatedTime\",\"id\":\"id\",\"text\":\"value\"}',NULL,NULL,NULL,NULL,25,NULL,'3',NULL,NULL,NULL,0,'partial','col-12','col-12','2024-09-21 07:21:43','2024-11-08 09:39:21'),(1075,65,1,'dedicated_time','22','Tiempo dedicado',NULL,'Tiempo Dedicado',NULL,NULL,'{\"model\":\"App\\\\Models\\\\FrequencyEstimatedDedicatedTime\",\"id\":\"id\",\"text\":\"value\"}',NULL,NULL,NULL,NULL,26,NULL,'1',NULL,NULL,NULL,0,'partial','col-12','col-12','2024-09-21 07:21:43','2024-11-08 09:39:21'),(1076,66,1,'dedicated_time','22','Tiempo Dedicado',NULL,'Tiempo Dedicado',NULL,NULL,'{\"model\":\"App\\\\Models\\\\FrequencyEstimatedDedicatedTime\",\"id\":\"id\",\"text\":\"value\"}',NULL,NULL,NULL,NULL,16,NULL,'1',NULL,NULL,NULL,0,'full','col-12','col-12','2024-09-21 07:21:43','2024-11-11 11:39:34'),(1077,71,1,'name','1','Nombre',NULL,'Distrito',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:53','2024-09-22 16:35:53'),(1078,72,1,'name','1','Nombre',NULL,'Zona',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:53','2024-09-22 16:35:53'),(1079,73,1,'name','1','Nombre',NULL,'Zona',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1080,74,1,'district','1','Distrito',NULL,'Ej. 1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1081,74,1,'zone','1','Zona',NULL,'Ej. 1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1082,74,1,'box_zone','1','Caja',NULL,'Ej. 1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1083,74,1,'client','1','Cliente',NULL,'Ej. 1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1085,75,1,'nomenclature_id','23','Nomenclatura',NULL,'Selecciona Nomenclatura','',NULL,'{\"model\":\"App\\\\Models\\\\Nomenclature\",\"id\":\"id\",\"text\":\"name\",\"scope\":\"notUsed\"}',NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1086,75,1,'client_id','3','Cliente',NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,999,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:54','2024-09-22 16:35:54'),(1087,76,1,'name','1','Nombre',NULL,'Equipo',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1088,76,1,'users','25','Integrantes',NULL,'','',NULL,'{\"model\":\"App\\\\Models\\\\User\",\"id\":\"id\",\"text\":\"name\",\"scope\":\"notClientRole\"}',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-22 16:35:55','2024-09-22 16:35:55'),(1089,14,1,'box_nomenclator','43','Nomenclatura',NULL,'Selecciona Nomenclatura','',NULL,'{\"model\":\"App\\\\Models\\\\Nomenclature\",\"id\":\"id\",\"text\":\"name\",\"scope\":\"notUsed\"}',NULL,NULL,NULL,NULL,7,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-25 11:17:44','2024-10-28 06:15:42'),(1090,76,1,'color','40','Color',NULL,'Color',NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-09-26 11:57:35','2024-09-26 11:57:35'),(1093,66,1,'title','1','Título',NULL,'Título',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,0,'full','col-form-label pr-2','col-sm-12','2024-10-29 15:51:26','2024-12-08 09:48:15'),(1094,66,1,'geo_data','38','Datos Geograficos',NULL,'19.700586990172585,-99.07096803188318',NULL,NULL,NULL,NULL,NULL,NULL,NULL,14,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-02 14:08:46','2024-11-02 14:08:46'),(1095,66,1,'estimated_time','22','Tiempo estimado',NULL,'Tiempo estimado',NULL,NULL,'{\"model\":\"App\\\\Models\\\\FrequencyEstimatedDedicatedTime\",\"id\":\"id\",\"text\":\"value\"}',NULL,NULL,NULL,NULL,15,NULL,'3',NULL,NULL,NULL,0,'full','col-12','col-12','2024-11-11 11:39:34','2024-11-11 11:39:34'),(1096,79,1,'company_name','1','Npmbre de la Empresa',NULL,'Nombre de la Empresa',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1097,79,1,'company_postal_code','1','Código Postal',NULL,'Código Postal',NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1098,79,1,'country','1','País',NULL,'País',NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1099,79,1,'colony_id','24','App\\Models\\CompanyInformation',NULL,'Seleccionar Colonia',NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1100,79,0,'state_id','30','Estado',NULL,'Seleccionar Estado',NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1101,79,0,'municipality_id','30','Municipio',NULL,'Seleccionar Municipio',NULL,NULL,NULL,NULL,NULL,NULL,NULL,7,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1102,79,1,'email','1','Correo Electrónico',NULL,'Correo Electrónico',NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1103,79,1,'atention_client_phone','1','Teléfono Atención a Clientes',NULL,'Teléfono Atención a Clientes',NULL,NULL,NULL,NULL,NULL,NULL,NULL,9,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1104,79,1,'rfc','1','RFC de la Empresa',NULL,'RFC de la Empresa',NULL,NULL,NULL,NULL,NULL,NULL,NULL,10,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1105,79,1,'iva','1','IVA',NULL,'IVA',NULL,NULL,NULL,NULL,NULL,NULL,NULL,11,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1106,79,1,'bank_name','1','Nombre del Banco',NULL,'Nombre del Banco',NULL,NULL,NULL,NULL,NULL,NULL,NULL,12,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1107,79,1,'bank_account','1','Cuenta Bancaria',NULL,'Cuenta Bancaria',NULL,NULL,NULL,NULL,NULL,NULL,NULL,13,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1108,79,1,'cominion_partner','1','Comisión a Socios',NULL,'Comisión a Socios',NULL,NULL,NULL,NULL,NULL,NULL,NULL,14,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-23 14:09:54','2024-11-23 14:09:54'),(1109,79,1,'logo','44','Logo',NULL,'Logo',NULL,NULL,NULL,NULL,NULL,NULL,NULL,800,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-25 11:23:25','2024-11-25 11:46:59'),(1110,79,1,'url_portal','1','Enlace a Portal',NULL,'Enlace a Portal',NULL,NULL,NULL,NULL,NULL,NULL,NULL,15,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-11-26 11:52:56','2024-11-26 11:52:56'),(1111,79,1,'company_street','1','Calle',NULL,'Calle',NULL,NULL,NULL,NULL,NULL,NULL,NULL,16,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-12-09 08:14:29','2024-12-09 08:14:29'),(1112,79,1,'company_external_number','1','Numero Exterior',NULL,'Numero Exterior',NULL,NULL,NULL,NULL,NULL,NULL,NULL,17,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-12-09 08:14:29','2024-12-09 08:14:29'),(1113,79,1,'company_internal_number','1','Numero Interior',NULL,'Numero Interior',NULL,NULL,NULL,NULL,NULL,NULL,NULL,18,NULL,NULL,NULL,NULL,NULL,0,'full','col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center','col-sm-12 col-md-9','2024-12-09 08:14:29','2024-12-09 08:14:29');
/*!40000 ALTER TABLE `field_modules` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `field_modules` with 750 row(s)
--

--
-- Table structure for table `field_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `field_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `field_types`
--

LOCK TABLES `field_types` WRITE;
/*!40000 ALTER TABLE `field_types` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `field_types` VALUES (1,'input-string',NULL,NULL),(2,'input-string-information',NULL,NULL),(3,'input-hidden',NULL,NULL),(4,'text',NULL,NULL),(5,'input-text-area',NULL,NULL),(6,'input-file',NULL,NULL),(7,'input-multiple-file',NULL,NULL),(8,'input-password',NULL,NULL),(9,'input-password-in-modal',NULL,NULL),(10,'input-group-generate-user',NULL,NULL),(11,'input-group-text',NULL,NULL),(12,'input-group-number',NULL,NULL),(13,'input-group-text-init',NULL,NULL),(14,'input-group-multiple',NULL,NULL),(15,'input-number',NULL,NULL),(16,'input-checkbox',NULL,NULL),(17,'input-checkbox-left-order',NULL,NULL),(18,'input-checkbox-with-inputs',NULL,NULL),(19,'select-component-with-group-inputs',NULL,NULL),(20,'date-time-local',NULL,NULL),(21,'input-time',NULL,NULL),(22,'select-component',NULL,NULL),(23,'select-2-component',NULL,NULL),(24,'select-2-estado-municipio-colonia-component',NULL,NULL),(25,'select-component-with-checkbox',NULL,NULL),(26,'select-component-with-checkbox-without-id',NULL,NULL),(27,'input-checkbox-after-withou-validation-error',NULL,NULL),(28,'select-single-add-items',NULL,NULL),(29,'router-network',NULL,NULL),(30,'depend-field',NULL,NULL),(31,'data-range-picker',NULL,NULL),(32,'input-price-transaction',NULL,NULL),(33,'select-2-type-template','2024-07-29 12:36:40','2024-07-29 12:36:40'),(34,'select_component_with_search_client','2024-08-09 16:43:14','2024-08-09 16:43:14'),(35,'select_component_with_search','2024-08-09 16:43:14','2024-08-09 16:43:14'),(36,'input_vue_datepicker_single','2024-08-10 14:51:52','2024-08-10 14:51:52'),(37,'input_vue_hour_picker','2024-08-10 14:51:52','2024-08-10 14:51:52'),(38,'input_modal_google_map','2024-08-10 14:51:52','2024-08-10 14:51:52'),(39,'select_template_list_verification','2024-08-10 14:51:52','2024-08-10 14:51:52'),(40,'color-picker','2024-09-16 11:25:00','2024-09-16 11:25:00'),(41,'select-group-team','2024-09-22 16:35:55','2024-09-22 16:35:55'),(42,'input-editor','2024-09-27 02:16:41','2024-09-27 02:16:41'),(43,'select-long-component','2024-10-28 06:15:42','2024-10-28 06:15:42'),(44,'input-file-imagen','2024-11-25 11:46:59','2024-11-25 11:46:59');
/*!40000 ALTER TABLE `field_types` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `field_types` with 44 row(s)
--

--
-- Table structure for table `frequency_commands`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frequency_commands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_time` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `frequency_commands`
--

LOCK TABLES `frequency_commands` WRITE;
/*!40000 ALTER TABLE `frequency_commands` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `frequency_commands` VALUES (1,'dailyAt',1,NULL,'2024-02-09 17:45:29'),(2,'everyMinute',0,NULL,'2024-02-09 17:45:29'),(3,'everyFiveMinutes',0,NULL,NULL),(4,'everyTenMinutes',0,NULL,NULL),(5,'everyFifteenMinutes',0,NULL,NULL),(6,'everyThirtyMinutes',0,NULL,NULL),(7,'hourly',0,NULL,NULL),(13,'everyFourHours',0,'2024-09-16 11:25:00','2024-09-16 11:25:00');
/*!40000 ALTER TABLE `frequency_commands` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `frequency_commands` with 8 row(s)
--

--
-- Table structure for table `frequency_estimated_dedicated_times`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frequency_estimated_dedicated_times` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `frequency_estimated_dedicated_times`
--

LOCK TABLES `frequency_estimated_dedicated_times` WRITE;
/*!40000 ALTER TABLE `frequency_estimated_dedicated_times` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `frequency_estimated_dedicated_times` VALUES (1,'00:00',NULL,NULL),(2,'00:30',NULL,NULL),(3,'01:00',NULL,NULL),(4,'01:30',NULL,NULL),(5,'02:00',NULL,NULL),(6,'02:30',NULL,NULL),(7,'03:00',NULL,NULL),(8,'03:30',NULL,NULL),(9,'04:00',NULL,NULL),(10,'04:30',NULL,NULL),(11,'05:00',NULL,NULL),(12,'05:30',NULL,NULL),(13,'06:00',NULL,NULL),(14,'06:30',NULL,NULL),(15,'07:00',NULL,NULL),(16,'07:30',NULL,NULL),(17,'08:00',NULL,NULL);
/*!40000 ALTER TABLE `frequency_estimated_dedicated_times` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `frequency_estimated_dedicated_times` with 17 row(s)
--

--
-- Table structure for table `ifts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ifts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ifts`
--

LOCK TABLES `ifts` WRITE;
/*!40000 ALTER TABLE `ifts` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `ifts` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `ifts` with 0 row(s)
--

--
-- Table structure for table `internets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `update_description` tinyint(1) DEFAULT '0',
  `price` bigint(20) NOT NULL,
  `update_service` tinyint(1) DEFAULT '0',
  `tax_include` tinyint(1) NOT NULL,
  `tax` bigint(20) NOT NULL,
  `download_speed` bigint(20) NOT NULL,
  `upload_speed` bigint(20) NOT NULL,
  `guaranteed_speed_limit` bigint(20) NOT NULL,
  `priority` enum('Baja','Normal','Alta') COLLATE utf8mb4_unicode_ci NOT NULL,
  `aggregation` bigint(20) NOT NULL,
  `burst` bigint(20) NOT NULL,
  `burt_umbral` bigint(20) DEFAULT NULL,
  `burt_time` bigint(20) DEFAULT NULL,
  `rates_to_change` bigint(20) DEFAULT NULL,
  `prepaid_period` enum('Mensual','Diario') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_category` enum('Servicio','Descuento','Pago','Reembolso','Corrección') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_days` bigint(20) DEFAULT NULL,
  `available_when_register_by_social_network` tinyint(1) DEFAULT '0',
  `cost_activation` double(8,2) NOT NULL DEFAULT '0.00',
  `cost_instalation` double(8,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internets`
--

LOCK TABLES `internets` WRITE;
/*!40000 ALTER TABLE `internets` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `internets` VALUES (1,'Basico_50MB','Basico_50MB',0,349,0,1,16,0,0,11,'Normal',0,100,100,800,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:17','2024-11-06 14:10:29'),(7,'administracion','administracion',NULL,0,NULL,0,16,0,0,5,'Normal',0,200,50,30,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:17','2024-05-29 04:45:17'),(11,'Meganet_recurrente 20','Meganet_recurrente 20',NULL,349,NULL,1,16,0,0,5,'Normal',10,200,200,60,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:17','2024-05-29 04:45:17'),(18,'250mb','250mb',0,599,0,1,16,250000,250000,10,'Normal',20,200,200,30,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:17','2024-10-08 19:00:17'),(24,'Meganet_40Mb','Meganet_40Mb',NULL,549,NULL,1,16,40000,8000,10,'Normal',20,800,800,100,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:17','2024-05-29 04:45:17'),(25,'EMPLEADOS','EMPLEADOS',NULL,200,NULL,1,16,15000,10000,10,'Alta',15,100,800,800,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:17','2024-05-29 04:45:17'),(29,'50 MB + TEL EMPLEADOS','50 MB + TEL EMPLEADOS',0,270,0,1,16,20000,5000,10,'Normal',1,0,0,0,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:17','2024-11-06 14:09:59'),(31,'EXPRESS ','EXPRESS ',NULL,769,NULL,1,16,102400,10240,10,'Normal',20,800,100,60,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:18','2024-05-29 04:45:18'),(32,'GAMER 100 ','GAMER 100 ',NULL,599,NULL,1,16,102400,10240,10,'Normal',20,100,100,60,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:18','2024-05-29 04:45:18'),(33,'EMPRENDE 300','EMPRENDE 300',NULL,1199,NULL,1,16,307200,30720,10,'Normal',100,100,100,60,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:18','2024-05-29 04:45:18'),(34,'EXPERTO 500','EXPERTO 500',NULL,1509,NULL,1,16,512000,51200,10,'Normal',100,100,100,60,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:18','2024-05-29 04:45:18'),(35,'CRACK','CRACK',NULL,2099,NULL,1,16,1024000,102400,10,'Normal',128,0,0,0,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:18','2024-05-29 04:45:18'),(36,'100 Mb 449','100 Mb 449',0,449,0,1,16,0,0,10,'Normal',50,100,100,60,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:18','2024-11-06 14:09:39'),(37,'Internet 150','Internet 150',NULL,699,NULL,1,16,10000,10000,10,'Normal',1,0,0,0,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:18','2024-05-29 04:45:18'),(38,'internet 200','internet 200',NULL,599,NULL,1,16,20000,20000,10,'Normal',1,0,0,0,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:18','2024-05-29 04:45:18'),(39,'Club_Meganet','Club_Meganet',NULL,30,NULL,1,16,10000,10000,10,'Normal',1,0,0,0,NULL,'Mensual','Servicio',NULL,0,0.00,0.00,'2024-05-29 04:45:18','2024-05-29 04:45:18'),(40,'50 Mb 349','50 Mb 349',0,349,0,1,16,500000,50000,50000,'Normal',8,100,100,100,NULL,'Mensual','Servicio',NULL,0,299.00,299.00,'2024-11-06 14:01:25','2024-11-06 14:01:25');
/*!40000 ALTER TABLE `internets` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `internets` with 17 row(s)
--

--
-- Table structure for table `jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `jobs` with 0 row(s)
--

--
-- Table structure for table `list_template_verifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `list_template_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checks` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `list_template_verifications`
--

LOCK TABLES `list_template_verifications` WRITE;
/*!40000 ALTER TABLE `list_template_verifications` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `list_template_verifications` VALUES (1,'Instalacion','[\"checar nombre y direccion completos\",\"validar identificacion\",\"validar comprobante de domicilio\",\"verificar que sea el titular el qe recibe o un adulto\",\"revisar cobertura\",\"comprobar calidad de servicio\"]','2024-09-07 12:30:18','2024-09-07 12:31:35'),(2,'Garantia Pon Rojo','[\"Verificar Modem (Encendido)\",\"Verificar potencia desde el modem\",\"Reconectar fibra\",\"Reponchar\",\"Reemplazo de fibra\",\"Reemplazo de eliminador\",\"Reemplazo de modem\"]','2024-09-18 10:32:40','2024-09-18 10:39:16'),(3,'Cambio de Domicilio','[\"Desconectar de la caja actual el servicio\",\"Retirar fibra de la posteria\",\"Validar el nuevo domicilio\",\"Comprobar el comprobante de domicilio\",\"Instalar y revisar potencia\",\"Validar servicio de calidad\",\"Enviar documentacion del nuevo domicilio\"]','2024-09-18 10:41:20','2024-09-18 10:41:20'),(4,'Garantia Potencia Alta','[\"Reconectar en ambos lados\",\"Limpiar en ambos lados conector y terminal\",\"Reponchar casa\",\"Reponchar Poste\",\"Revisar fibra\"]','2024-09-18 10:44:10','2024-09-18 10:44:10'),(5,'Servicio de Antena','[\"Revisar la conexion de los cables\",\"Validar que este encendida\",\"Validar que se encuentre firme y apretada\",\"Validar conectividad entre antena y modem\"]','2024-09-18 10:46:57','2024-09-18 10:46:57'),(6,'CORRECCION DESARROLLO','[\"REVISAR CUAL ES EL CONFLICTO\",\"VALIDAR EL CONFLICTO\",\"EVALUAR TIEMPO DE RESOLUCION\",\"SOLUCIONAR EL CONFLICTO\",\"VALIDAR QUE LA SOLUCION ESTE AL |100%\",\"ARCHIVAR TAREA\"]','2024-09-18 14:34:30','2024-09-18 14:34:30'),(7,'IMPLEMENTACION EN DESARROLLO','[\"REVISAR CUAL SERA LA IMPLEMENTACION\",\"CALCULAR EL TIEMPO\",\"REALIZAR LA IMPLEMENTACION\",\"VALIDAR EL FUNCIONAMIENTO\",\"INFORMAR QUE ESTA TERMINADA LA IMPLEMENTACION\",\"IRVING REVISA Y RETROALIMENTA\"]','2024-09-18 14:36:40','2024-09-18 14:36:40'),(8,'MATERIALES','[\"ENTREGADO\",\"PENDIENTE\"]','2024-11-26 18:03:12','2024-11-26 18:03:12'),(9,'DISEÑO','[]','2024-11-27 09:04:45','2024-11-27 09:04:45');
/*!40000 ALTER TABLE `list_template_verifications` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `list_template_verifications` with 9 row(s)
--

--
-- Table structure for table `list_template_verifications_tasks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `list_template_verifications_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `list_template_verification_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `list_template_verifications_tasks`
--

LOCK TABLES `list_template_verifications_tasks` WRITE;
/*!40000 ALTER TABLE `list_template_verifications_tasks` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `list_template_verifications_tasks` VALUES (1,'2','1','[\"checar nombre y direccion completos\",\"validar identificacion\",\"validar comprobante de domicilio\",\"verificar que sea el titular el qe recibe o un adulto\",\"revisar cobertura\",\"comprobar calidad de servicio\"]','2024-09-07 12:33:33','2024-09-07 12:33:33'),(2,'3','1','[\"checar nombre y direccion completos\",\"validar identificacion\"]','2024-09-16 11:37:07','2024-09-16 11:37:07'),(3,'4','2','[\"Verificar Modem (Encendido)\",\"Verificar potencia desde el modem\",\"Reconectar fibra\"]','2024-09-18 12:16:36','2024-09-18 12:16:36'),(4,'5','2','[\"Verificar Modem (Encendido)\",\"Verificar potencia desde el modem\",\"Reconectar fibra\"]','2024-09-18 12:16:37','2024-09-18 12:16:37'),(5,'6','2','[\"Verificar Modem (Encendido)\",\"Verificar potencia desde el modem\",\"Reconectar fibra\"]','2024-09-18 12:16:51','2024-09-18 12:16:51');
/*!40000 ALTER TABLE `list_template_verifications_tasks` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `list_template_verifications_tasks` with 5 row(s)
--

--
-- Table structure for table `locations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `locations_id_index` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `locations`
--

LOCK TABLES `locations` WRITE;
/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `locations` VALUES (1,'ZONA 1','2024-03-14 13:21:58','2024-03-14 13:21:58'),(2,'ZONA 2','2024-03-14 13:22:04','2024-03-14 13:22:04'),(3,'ZONA 3','2024-03-14 13:22:11','2024-03-14 13:22:11'),(4,'ZONA 4','2024-03-14 13:22:16','2024-03-14 13:22:16'),(5,'ZONA 5','2024-03-14 13:22:21','2024-03-14 13:22:21'),(6,'ZONA 6','2024-03-14 13:22:27','2024-03-14 13:22:27'),(7,'ZONA 7','2024-03-14 13:22:33','2024-03-14 13:22:33'),(8,'ZONA 8','2024-03-14 13:22:38','2024-03-14 13:22:38'),(9,'ZONA 9','2024-03-14 13:22:44','2024-03-14 13:22:44'),(10,'ZONA 10','2024-03-14 13:22:55','2024-03-14 13:22:55'),(11,'ZONA 11','2024-03-14 13:23:04','2024-03-14 13:23:04'),(12,'ZONA 12','2024-03-14 13:23:09','2024-03-14 13:23:09'),(13,'ZONA 13','2024-03-14 13:23:14','2024-03-14 13:23:14'),(14,'ZONA 14','2024-03-14 13:23:21','2024-03-14 13:23:21'),(15,'ZONA 15','2024-03-14 13:23:27','2024-03-14 13:23:27'),(16,'ZONA 16','2024-03-14 13:23:34','2024-03-14 13:23:34'),(17,'ZONA 17','2024-03-14 13:23:39','2024-03-14 13:23:39'),(18,'ZONA 18','2024-03-14 13:23:45','2024-03-14 13:23:45'),(19,'ZONA 19','2024-03-14 13:23:50','2024-03-14 13:23:50'),(20,'ZONA 20','2024-03-14 13:23:56','2024-03-14 13:23:56'),(21,'ZONA 21','2024-03-14 13:24:02','2024-03-14 13:24:02'),(22,'ZONA 22','2024-03-14 13:24:07','2024-03-14 13:24:07'),(23,'ZONA 23','2024-03-14 13:24:13','2024-03-14 13:24:13'),(24,'ZONA 24','2024-03-14 13:24:18','2024-03-14 13:24:18'),(25,'ZONA 25','2024-03-14 13:24:34','2024-03-14 13:24:34'),(26,'ZONA 26','2024-03-14 13:24:41','2024-03-14 13:24:41'),(27,'ZONA 27','2024-03-14 13:24:47','2024-03-14 13:24:47'),(28,'ZONA 28','2024-03-14 13:24:53','2024-03-14 13:24:53'),(29,'ZONA 29','2024-03-14 13:25:00','2024-03-14 13:25:00'),(30,'ZONA 30','2024-03-14 13:25:06','2024-03-14 13:25:06'),(31,'ZONA 31','2024-03-14 13:25:12','2024-03-14 13:25:12'),(32,'ZONA 32','2024-03-14 13:25:18','2024-03-14 13:25:18'),(33,'ZONA 33','2024-03-14 13:25:24','2024-03-14 13:25:24'),(34,'ZONA 34','2024-03-14 13:25:33','2024-03-14 13:25:33'),(35,'ZONA 35','2024-03-14 13:25:38','2024-03-14 13:25:38'),(36,'ZONA 36','2024-03-14 13:25:44','2024-03-14 13:25:44'),(37,'ZONA 37','2024-03-14 13:26:30','2024-03-14 13:26:30'),(38,'ZONA 38','2024-03-14 13:26:36','2024-03-14 13:26:36'),(39,'ZONA 39','2024-03-14 13:26:41','2024-03-14 13:26:41'),(40,'ZONA 40','2024-03-14 13:26:46','2024-03-14 13:26:46'),(41,'ZONA 41','2024-03-14 13:26:52','2024-03-14 13:26:52'),(42,'ZONA 42','2024-03-14 13:26:57','2024-03-14 13:26:57'),(43,'ZONA 43','2024-03-14 13:27:03','2024-03-14 13:27:03'),(44,'ZONA 44','2024-03-14 13:27:08','2024-03-14 13:27:08'),(45,'ZONA 45','2024-03-14 13:27:12','2024-03-14 13:27:12'),(46,'ZONA 46','2024-03-14 13:27:17','2024-03-14 13:27:17'),(47,'ZONA 47','2024-03-14 13:27:21','2024-03-14 13:27:21'),(48,'ZONA 48','2024-03-14 13:27:26','2024-03-14 13:27:26'),(49,'ZONA 49','2024-03-14 13:27:31','2024-03-14 13:27:31'),(50,'ZONA 50','2024-03-14 13:27:36','2024-03-14 13:27:36'),(51,'ZONA 51','2024-03-14 13:27:41','2024-03-14 13:27:41'),(52,'ZONA 52','2024-03-14 13:27:47','2024-03-14 13:27:47'),(53,'ZONA 53','2024-03-14 13:27:53','2024-03-14 13:27:53'),(54,'ZONA 54','2024-03-14 13:27:58','2024-03-14 13:27:58'),(55,'ZONA 55','2024-03-14 13:28:04','2024-03-14 13:28:04'),(56,'ZONA 56','2024-03-14 13:28:11','2024-03-14 13:28:11'),(57,'ZONA 57','2024-03-14 13:28:16','2024-03-14 13:28:16'),(58,'ZONA 58','2024-03-14 13:28:21','2024-03-14 13:28:21'),(59,'ZONA 59','2024-03-14 13:28:27','2024-03-14 13:28:27'),(60,'ZONA 60','2024-03-14 13:28:35','2024-03-14 13:28:35'),(61,'ZONA 61','2024-03-14 13:28:51','2024-03-14 13:28:51'),(62,'ZONA 62','2024-03-14 13:28:58','2024-03-14 13:28:58'),(63,'ZONA 63','2024-03-14 13:29:05','2024-03-14 13:29:05'),(64,'ZONA 64','2024-03-14 13:29:11','2024-03-14 13:29:11'),(65,'ZONA 65','2024-03-14 13:29:18','2024-03-14 13:29:18'),(66,'ZONA 66','2024-03-14 13:29:23','2024-03-14 13:29:23'),(67,'ZONA 67','2024-03-14 13:29:29','2024-03-14 13:29:29'),(68,'ZONA 68','2024-03-14 13:29:35','2024-03-14 13:29:35'),(69,'ZONA 69','2024-03-14 13:29:45','2024-03-14 13:29:45'),(70,'ZONA 70','2024-03-14 13:29:54','2024-03-14 13:29:54'),(71,'ZONA 71','2024-03-14 13:30:00','2024-03-14 13:30:00'),(72,'ZONA 72','2024-03-14 13:30:07','2024-03-14 13:30:07'),(73,'ZONA 73','2024-03-14 13:30:14','2024-03-14 13:30:14'),(74,'ZONA 74','2024-03-14 13:30:22','2024-03-14 13:30:22'),(75,'ZONA 75','2024-03-14 13:30:34','2024-03-14 13:30:34'),(76,'ZONA 76','2024-03-14 13:30:44','2024-03-14 13:30:44'),(77,'ZONA 77','2024-03-14 13:30:59','2024-03-14 13:30:59'),(78,'ZONA 78','2024-03-14 13:31:25','2024-03-14 13:31:25'),(79,'ZONA 79','2024-03-14 13:31:33','2024-03-14 13:31:33'),(80,'ZONA 80','2024-03-14 13:31:42','2024-03-14 13:31:42'),(81,'ZONA 81','2024-03-14 13:31:51','2024-03-14 13:31:51'),(82,'ZONA 82','2024-03-14 13:31:58','2024-03-14 13:31:58'),(83,'ZONA 83','2024-03-14 13:32:04','2024-03-14 13:32:04'),(84,'ZONA 84','2024-03-14 13:32:14','2024-03-14 13:32:14'),(85,'ZONA 85','2024-03-14 13:32:21','2024-03-14 13:32:21'),(86,'ZONA 86','2024-03-14 13:32:28','2024-03-14 13:32:28'),(87,'ZONA 87','2024-03-14 13:32:36','2024-03-14 13:32:36'),(88,'ZONA 88','2024-03-14 13:32:41','2024-03-14 13:32:41'),(89,'ZONA 89','2024-03-14 13:32:47','2024-03-14 13:32:47'),(90,'ZONA 90','2024-03-14 13:32:52','2024-03-14 13:32:52'),(91,'ZONA 91','2024-03-14 13:32:59','2024-03-14 13:32:59'),(92,'ZONA 92','2024-03-14 13:33:08','2024-03-14 13:33:08'),(93,'ZONA 93','2024-03-14 13:33:15','2024-03-14 13:33:15'),(94,'ZONA 94','2024-03-14 13:33:22','2024-03-14 13:33:22'),(95,'ZONA 95','2024-03-14 13:33:28','2024-03-14 13:33:28'),(96,'ZONA 96','2024-03-14 13:33:34','2024-03-14 13:33:34'),(97,'ZONA 97','2024-03-14 13:33:40','2024-03-14 13:33:40'),(98,'ZONA 98','2024-03-14 13:33:46','2024-03-14 13:33:46'),(99,'ZONA 99','2024-03-14 13:33:53','2024-03-14 13:33:53'),(100,'ZONA 100','2024-03-14 13:34:00','2024-03-14 13:34:00'),(101,'ZONA 101','2024-03-14 13:34:07','2024-03-14 13:34:07'),(102,'ZONA 102','2024-03-14 13:34:14','2024-03-14 13:34:14'),(103,'ZONA 103','2024-03-14 13:34:20','2024-03-14 13:34:20'),(104,'ZONA 104','2024-03-14 13:34:26','2024-03-14 13:34:26'),(105,'ZONA 105','2024-03-14 13:34:32','2024-03-14 13:34:32'),(106,'ZONA 106','2024-03-14 13:34:38','2024-03-14 13:34:38'),(107,'ZONA 107','2024-03-14 13:34:45','2024-03-14 13:34:45'),(108,'ZONA 108','2024-03-14 13:34:51','2024-03-14 13:34:51'),(109,'ZONA 109','2024-03-14 13:35:01','2024-03-14 13:35:01'),(110,'ZONA 110','2024-03-14 13:35:09','2024-03-14 13:35:09'),(111,'ZONA 111','2024-03-14 13:35:18','2024-03-14 13:35:18'),(112,'ZONA 112','2024-03-14 13:35:24','2024-03-14 13:35:24');
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `locations` with 112 row(s)
--

--
-- Table structure for table `map_links`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `map_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `input_id` bigint(20) unsigned NOT NULL,
  `input_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `output_id` bigint(20) unsigned NOT NULL,
  `output_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_route_id` bigint(20) unsigned NOT NULL,
  `tube_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_links_map_route_id_foreign` (`map_route_id`),
  KEY `map_links_tube_id_foreign` (`tube_id`),
  KEY `map_links_created_by_foreign` (`created_by`),
  KEY `map_links_updated_by_foreign` (`updated_by`),
  CONSTRAINT `map_links_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `map_links_map_route_id_foreign` FOREIGN KEY (`map_route_id`) REFERENCES `map_routes` (`id`),
  CONSTRAINT `map_links_tube_id_foreign` FOREIGN KEY (`tube_id`) REFERENCES `tubes` (`id`),
  CONSTRAINT `map_links_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `map_links`
--

LOCK TABLES `map_links` WRITE;
/*!40000 ALTER TABLE `map_links` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `map_links` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `map_links` with 0 row(s)
--

--
-- Table structure for table `map_proyects`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `map_proyects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_proyects_created_by_foreign` (`created_by`),
  KEY `map_proyects_updated_by_foreign` (`updated_by`),
  CONSTRAINT `map_proyects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `map_proyects_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `map_proyects`
--

LOCK TABLES `map_proyects` WRITE;
/*!40000 ALTER TABLE `map_proyects` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `map_proyects` VALUES (1,'PRUEBA 1',1,1,'2024-05-14 15:05:06','2024-05-14 15:05:06'),(3,'PRUEBA 2',1,1,'2024-08-13 08:47:03','2024-08-13 08:47:03'),(4,'PRUEBA 2',1,1,'2024-08-13 08:47:03','2024-08-13 08:47:03');
/*!40000 ALTER TABLE `map_proyects` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `map_proyects` with 3 row(s)
--

--
-- Table structure for table `map_routes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `map_routes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_id` bigint(20) unsigned NOT NULL,
  `fibers_amount` int(11) NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_routes_color_id_foreign` (`color_id`),
  KEY `map_routes_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `map_routes_created_by_foreign` (`created_by`),
  KEY `map_routes_updated_by_foreign` (`updated_by`),
  CONSTRAINT `map_routes_color_id_foreign` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`),
  CONSTRAINT `map_routes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `map_routes_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `map_routes_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `map_routes`
--

LOCK TABLES `map_routes` WRITE;
/*!40000 ALTER TABLE `map_routes` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `map_routes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `map_routes` with 0 row(s)
--

--
-- Table structure for table `medium_sales`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medium_sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medium_sales`
--

LOCK TABLES `medium_sales` WRITE;
/*!40000 ALTER TABLE `medium_sales` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `medium_sales` VALUES (1,'Facebook',NULL,NULL),(2,'WhatsApp',NULL,NULL),(3,'Instagram',NULL,NULL),(4,'Cambaceo',NULL,'2024-06-19 10:32:59'),(5,'Volanteo',NULL,NULL),(6,'Telefonico','2024-05-31 19:59:44','2024-05-31 19:59:44'),(7,'Oficina','2024-05-31 19:59:53','2024-05-31 19:59:53'),(8,'Stand','2024-05-31 20:00:01','2024-05-31 20:00:01'),(9,'Videos en Youtube','2024-08-08 17:39:25','2024-08-08 17:39:25'),(10,'Whats o Llamada por video de Youtube','2024-08-08 17:40:13','2024-08-08 17:40:13'),(11,'Recomendacion','2024-08-08 17:41:38','2024-08-08 17:41:38'),(12,'Solo pregunto sin conocernos','2024-08-08 17:42:36','2024-08-08 17:42:36');
/*!40000 ALTER TABLE `medium_sales` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `medium_sales` with 12 row(s)
--

--
-- Table structure for table `method_of_payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `method_of_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `method_of_payments_id_index` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `method_of_payments`
--

LOCK TABLES `method_of_payments` WRITE;
/*!40000 ALTER TABLE `method_of_payments` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `method_of_payments` VALUES (1,'Efectivo en Caja',1,NULL,NULL),(2,'Transferencia Bancaria',1,NULL,NULL),(3,'Tarjeta de Credito o debito en Oficina',1,NULL,NULL),(4,'PayPal',1,NULL,NULL),(5,'Oxxo',1,NULL,NULL),(6,'TARJETAS PREPAGO',1,NULL,NULL),(7,'PAGO A TECNICO',1,NULL,NULL);
/*!40000 ALTER TABLE `method_of_payments` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `method_of_payments` with 7 row(s)
--

--
-- Table structure for table `mikrotiks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mikrotiks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `router_id` bigint(20) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `login_api` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_api` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `port_api` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shaper_active` tinyint(1) NOT NULL DEFAULT '0',
  `shaper` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shaping_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rule_wireless_access_list` tinyint(1) NOT NULL DEFAULT '0',
  `url_redirect` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'field depend on rule_wireless_access_list',
  `port_redirect` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '80',
  `ip_redirect` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'field depend on rule_wireless_access_list',
  `ips_with_comma_permited` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'field depend on rule_wireless_access_list',
  `rule_address_list_mobility_client` tinyint(1) NOT NULL DEFAULT '0',
  `bloking_rules` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plataform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `board_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ros_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpu_load` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv6` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mikrotiks_router_id_foreign` (`router_id`),
  CONSTRAINT `mikrotiks_router_id_foreign` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mikrotiks`
--

LOCK TABLES `mikrotiks` WRITE;
/*!40000 ALTER TABLE `mikrotiks` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `mikrotiks` VALUES (2,2,1,'MegaISP','megaisp','8730',1,'Este enrutador','Simple queue(Con árbol de cola)',0,NULL,NULL,NULL,NULL,1,0,'1','MikroTik','CCR2216-1G-12XS-2XQ','7.12.1 (stable)','25','-','3h26m4s','2024-02-10 10:54:41','2024-12-12 14:33:47'),(3,3,1,'user1','admin1','8787',0,'Este enrutador','Simple queue(Con árbol de cola)',0,NULL,NULL,NULL,NULL,1,1,'1','MikroTik','CCR1036-12G-4S','6.49.12 (stable)','0','-','1w1d1m50s','2024-02-10 12:17:33','2024-07-09 16:35:13');
/*!40000 ALTER TABLE `mikrotiks` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `mikrotiks` with 2 row(s)
--

--
-- Table structure for table `mikrotik_configs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mikrotik_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `router_id` bigint(20) unsigned NOT NULL,
  `meganet_config_ip_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '192.168.1.1' COMMENT 'ip del sistema meganet',
  `custom_config_name_parent_router` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MgNetSQP',
  `custom_config_comment_parent_router` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Meganet',
  `custom_config_comment_sun_router` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MgNetSQS',
  `mikrotik_config_server_pppoe_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PPPoE_SERVER_VLAN_200',
  `mikrotik_config_server_pppoe_interface` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vlan200 Internet',
  `mikrotik_config_server_pppoe_mtu` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1500',
  `mikrotik_config_server_pppoe_mru` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1500',
  `mikrotik_config_server_pppoe_profile` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PPPOE_VLAN_200',
  `mikrotik_config_server_ppp_profile` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PPPOE_VLAN_200',
  `mikrotik_config_server_ppp_local_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '10.10.0.1' COMMENT 'Ip local de la interfaz',
  `mikrotik_config_server_ppp_remote_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'estatica',
  `mikrotik_config_server_ppp_bridge` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RED_LOCAL_LAN',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `meganet_config_ip_address_enable` tinyint(1) DEFAULT '0',
  `custom_config_name_parent_router_enable` tinyint(1) DEFAULT '0',
  `custom_config_comment_parent_router_enable` tinyint(1) DEFAULT '0',
  `custom_config_comment_sun_router_enable` tinyint(1) DEFAULT '0',
  `mikrotik_config_server_pppoe_name_enable` tinyint(1) DEFAULT '0',
  `mikrotik_config_server_pppoe_interface_enable` tinyint(1) DEFAULT '0',
  `mikrotik_config_server_pppoe_mtu_enable` tinyint(1) DEFAULT '0',
  `mikrotik_config_server_pppoe_mru_enable` tinyint(1) DEFAULT '0',
  `mikrotik_config_server_pppoe_profile_enable` tinyint(1) DEFAULT '0',
  `mikrotik_config_server_ppp_profile_enable` tinyint(1) DEFAULT '0',
  `mikrotik_config_server_ppp_local_address_enable` tinyint(1) DEFAULT '0',
  `mikrotik_config_server_ppp_remote_address_enable` tinyint(1) DEFAULT '0',
  `mikrotik_config_server_ppp_bridge_enable` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mikrotik_configs`
--

LOCK TABLES `mikrotik_configs` WRITE;
/*!40000 ALTER TABLE `mikrotik_configs` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `mikrotik_configs` VALUES (2,2,'192.168.1.1','MgNetSQP','Meganet','MgNetSQS','PPPoE_SERVER_VLAN_200','vlan200 Internet','1500','1500','PPPOE_VLAN_200','PPPOE_VLAN_200','10.10.0.1','estatica','RED_LOCAL_LAN','2024-02-10 10:54:41','2024-02-10 10:54:41',0,0,0,0,0,0,0,0,0,0,0,0,0),(3,3,'192.168.1.1','MgNetSQP','Meganet','MgNetSQS','PPPoE_SERVER_VLAN_200','vlan200 Internet','1500','1500','PPPOE_VLAN_200','PPPOE_VLAN_200','10.10.0.1','estatica','RED_LOCAL_LAN','2024-02-10 12:17:33','2024-02-10 12:17:33',0,0,0,0,0,0,0,0,0,0,0,0,0);
/*!40000 ALTER TABLE `mikrotik_configs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `mikrotik_configs` with 2 row(s)
--

--
-- Table structure for table `mikrotik_item_to_excecute_actions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mikrotik_item_to_excecute_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `place` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flag` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origin` longtext COLLATE utf8mb4_unicode_ci,
  `value` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mikrotik_item_to_excecute_actions`
--

LOCK TABLES `mikrotik_item_to_excecute_actions` WRITE;
/*!40000 ALTER TABLE `mikrotik_item_to_excecute_actions` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `mikrotik_item_to_excecute_actions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `mikrotik_item_to_excecute_actions` with 0 row(s)
--

--
-- Table structure for table `mikrotik_notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mikrotik_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('Alta','Media','Baja') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `router_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mikrotik_notifications_router_id_foreign` (`router_id`),
  CONSTRAINT `mikrotik_notifications_router_id_foreign` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mikrotik_notifications`
--

LOCK TABLES `mikrotik_notifications` WRITE;
/*!40000 ALTER TABLE `mikrotik_notifications` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `mikrotik_notifications` VALUES (1,'Se ha perdido la conexion con el Mikrotik','Alta','/red/router/mikrotik/read-notification/',NULL,2,'2024-12-12 11:01:00','2024-12-12 11:01:00');
/*!40000 ALTER TABLE `mikrotik_notifications` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `mikrotik_notifications` with 1 row(s)
--

--
-- Table structure for table `mikrotik_tariff_main_tails`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mikrotik_tariff_main_tails` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mikrotik_id` int(11) NOT NULL,
  `tariff_id` int(11) NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mikrotik_tariff_main_tails`
--

LOCK TABLES `mikrotik_tariff_main_tails` WRITE;
/*!40000 ALTER TABLE `mikrotik_tariff_main_tails` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `mikrotik_tariff_main_tails` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `mikrotik_tariff_main_tails` with 0 row(s)
--

--
-- Table structure for table `mikrotik_tariff_target_tails`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mikrotik_tariff_target_tails` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mikrotik_tariff_main_tail_id` int(11) NOT NULL,
  `mikrotik_id` int(11) NOT NULL,
  `tariff_id` int(11) NOT NULL,
  `client_internet_service_id` int(11) NOT NULL,
  `client_custom_service_id` int(11) DEFAULT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mikrotik_tariff_target_tails`
--

LOCK TABLES `mikrotik_tariff_target_tails` WRITE;
/*!40000 ALTER TABLE `mikrotik_tariff_target_tails` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `mikrotik_tariff_target_tails` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `mikrotik_tariff_target_tails` with 0 row(s)
--

--
-- Table structure for table `modems`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modems` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `model` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` bigint(20) unsigned NOT NULL,
  `serie` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modems_client_id_foreign` (`client_id`),
  KEY `modems_brand_id_foreign` (`brand_id`),
  KEY `modems_created_by_foreign` (`created_by`),
  KEY `modems_updated_by_foreign` (`updated_by`),
  CONSTRAINT `modems_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `modems_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `modems_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `modems_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modems`
--

LOCK TABLES `modems` WRITE;
/*!40000 ALTER TABLE `modems` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `modems` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `modems` with 0 row(s)
--

--
-- Table structure for table `modules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '1',
  `main` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `group` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modules`
--

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `modules` VALUES (1,'Internet',1,NULL,NULL,'2024-02-09 17:43:46','Package','2024-03-01 11:36:02'),(2,'Voise',1,NULL,NULL,'2024-02-09 17:43:46','Package','2024-03-01 11:36:02'),(3,'Custom',1,NULL,NULL,'2024-02-09 17:43:46','Package','2024-03-01 11:36:02'),(4,'Bundle',1,NULL,NULL,'2024-02-09 17:43:46','Package','2024-03-01 11:36:02'),(5,'BundleLeft',0,'App\\Models\\Bundle',NULL,'2024-02-09 17:43:46','Package','2024-03-01 11:36:02'),(6,'BundleRight',0,'App\\Models\\Bundle',NULL,'2024-02-09 17:43:46','Package','2024-03-01 11:36:02'),(7,'BundleBottom',0,'App\\Models\\Bundle',NULL,'2024-02-09 17:43:46','Package','2024-03-01 11:36:02'),(8,'Crm',1,NULL,NULL,'2024-02-09 17:43:47','Crms','2024-03-01 11:36:02'),(9,'CrmMainInformation',1,NULL,NULL,'2024-02-09 17:43:47','Crms','2024-03-01 11:36:02'),(10,'CrmLeadInformation',1,NULL,NULL,'2024-02-09 17:43:47','Crms','2024-03-01 11:36:02'),(11,'DocumentCrm',1,NULL,NULL,'2024-02-09 17:43:47','Crms','2024-03-01 11:36:02'),(12,'Client',1,NULL,NULL,'2024-02-09 17:43:47','Clients','2024-03-01 11:36:02'),(13,'ClientMainInformation',1,NULL,NULL,'2024-02-09 17:43:47','Clients','2024-03-01 11:36:02'),(14,'ClientAdditionalInformation',1,NULL,NULL,'2024-02-09 17:43:47','Clients','2024-03-01 11:36:02'),(15,'DocumentClient',1,NULL,NULL,'2024-02-09 17:43:47','Clients','2024-03-01 11:36:02'),(16,'ClientBundleService',1,NULL,NULL,'2024-02-09 17:43:47','Services','2024-03-01 11:36:02'),(17,'ClientInternetService',1,NULL,NULL,'2024-02-09 17:43:47','Services','2024-03-01 11:36:02'),(18,'ClientVozService',1,NULL,NULL,'2024-02-09 17:43:48','Services','2024-03-01 11:36:02'),(19,'ClientCustomService',1,NULL,NULL,'2024-02-09 17:43:48','Services','2024-03-01 11:36:02'),(21,'ClientBillingConfigurationCustom',1,NULL,NULL,'2024-02-09 17:43:48','Billing','2024-03-01 11:36:02'),(22,'ClientBillingConfigurationRecurrent',1,NULL,NULL,'2024-02-09 17:43:48','Billing','2024-03-01 11:36:02'),(23,'ClientBillingAddress',1,NULL,NULL,'2024-02-09 17:43:48','Billing','2024-03-01 11:36:02'),(24,'ClientRemindersConfiguration',1,NULL,NULL,'2024-02-09 17:43:48','Billing','2024-03-01 11:36:02'),(25,'ClientPayment',0,'App\\Models\\Payment',NULL,'2024-02-09 17:43:48','Billing','2024-03-01 11:36:02'),(26,'ClientTransaction',0,'App\\Models\\Transaction',NULL,'2024-02-09 17:43:48','Billing','2024-03-01 11:36:02'),(27,'RouterAdd',0,'App\\Models\\Router',NULL,'2024-02-09 17:43:48','Net','2024-03-01 11:36:02'),(28,'Router',1,NULL,NULL,'2024-02-09 17:43:49','Net','2024-03-01 11:36:02'),(29,'Mikrotik',1,NULL,NULL,'2024-02-09 17:43:49','Net','2024-03-01 11:36:02'),(30,'MikrotikConfig',1,NULL,NULL,'2024-02-09 17:43:49','Net','2024-03-01 11:36:02'),(31,'NetworkEdit',0,'App\\Models\\Network',NULL,'2024-02-09 17:43:49','Net','2024-03-01 11:36:02'),(32,'Network',1,NULL,NULL,'2024-02-09 17:43:49','Net','2024-03-01 11:36:02'),(33,'Ipv4Calculator',1,NULL,NULL,'2024-02-09 17:43:49','Net','2024-03-01 11:36:02'),(34,'NetworkIp',1,NULL,NULL,'2024-02-09 17:43:49','Net','2024-03-01 11:36:02'),(35,'SettingDebtPaymentClientRecurrent',1,NULL,NULL,'2024-02-09 17:43:49','Configuration','2024-03-01 11:36:02'),(36,'Perfil',0,'App\\Models\\User',NULL,'2024-02-09 17:43:50','Administration','2024-03-01 11:36:02'),(37,'Partner',1,NULL,NULL,'2024-02-09 17:43:50','Administration','2024-03-01 11:36:02'),(38,'Location',1,NULL,NULL,'2024-02-09 17:43:50','Administration','2024-03-01 11:36:02'),(39,'State',1,NULL,NULL,'2024-02-09 17:43:50','Administration','2024-03-01 11:36:02'),(40,'Municipality',1,NULL,NULL,'2024-02-09 17:43:50','Administration','2024-03-01 11:36:02'),(41,'Colony',1,NULL,NULL,'2024-02-09 17:43:50','Administration','2024-03-01 11:36:02'),(42,'Role',0,'Spatie\\Permission\\Models\\Role',NULL,'2024-02-09 17:43:50','Administration','2024-03-01 11:36:02'),(43,'Permission',1,NULL,NULL,'2024-02-09 17:43:51','Administration','2024-03-01 11:36:02'),(44,'TicketDetails',0,'App\\Models\\Ticket',NULL,'2024-02-09 17:43:51','Ticket','2024-03-01 11:36:02'),(45,'Ticket',1,NULL,NULL,'2024-02-09 17:43:51','Ticket','2024-03-01 11:36:02'),(46,'TicketThread',1,NULL,NULL,'2024-02-09 17:43:51','Ticket','2024-03-01 11:36:02'),(47,'MethodOfPayment',1,NULL,NULL,'2024-02-09 17:43:57','Administration','2024-03-01 11:36:02'),(48,'ClientInvoice',1,NULL,NULL,'2024-02-09 17:43:58','Billing','2024-06-01 09:25:02'),(49,'Ift',1,NULL,NULL,'2024-02-09 17:43:59','Administration','2024-03-01 11:36:02'),(50,'FinanceTransaction',1,NULL,NULL,'2024-02-09 17:44:00',NULL,'2024-02-09 17:44:00'),(51,'FinanceInvoice',1,NULL,NULL,'2024-02-09 17:44:00',NULL,'2024-02-09 17:44:00'),(52,'FinancePayment',1,NULL,NULL,'2024-02-09 17:44:00',NULL,'2024-02-09 17:44:00'),(53,'ClientDebitRectificationAgreement',1,NULL,NULL,'2024-02-09 17:44:00','Configuration','2024-03-01 11:36:02'),(54,'SettingDebtPaymentClientCustom',1,NULL,NULL,'2024-02-09 17:44:01','Configuration','2024-03-01 11:36:02'),(55,'Package',1,NULL,NULL,'2024-02-09 17:45:23',NULL,'2024-02-09 17:45:23'),(56,'CommandConfig',1,NULL,'','2024-02-09 17:45:27','Configuration','2024-03-01 11:36:03'),(57,'FieldModule',1,NULL,NULL,'2024-02-10 20:50:44','Configuration','2024-03-01 11:36:03'),(58,'SettingToolsImport',1,NULL,'','2024-03-21 12:08:44','Configuration','2024-03-21 12:08:44'),(60,'ActivityLog',1,NULL,'Modulo encargado de guardar historico','2024-07-15 18:13:30','Administration','2024-07-15 18:13:30'),(61,'DocumentTemplate',1,NULL,'Plantillas','2024-07-25 09:21:43',NULL,'2024-07-25 09:21:43'),(62,'DocumentTemplateClient',1,NULL,'Plantillas de Clientes','2024-07-29 12:36:39',NULL,'2024-07-29 12:36:39'),(63,'DocumentTypeTemplate',1,NULL,'Tipo de plantillas','2024-07-29 12:36:39',NULL,'2024-07-29 12:36:39'),(64,'Project',1,NULL,'Scheduling Project','2024-08-04 09:43:35',NULL,'2024-08-04 09:43:35'),(65,'Task',1,NULL,'Scheduling Task','2024-08-09 16:43:14',NULL,'2024-08-09 16:43:14'),(66,'TaskLeft',0,'App\\Models\\Task','Scheduling Task','2024-08-09 16:43:14',NULL,'2024-08-30 12:45:01'),(67,'TaskRight',0,'App\\Models\\Task','Scheduling Task','2024-08-09 16:43:15',NULL,'2024-08-30 12:45:01'),(68,'WorkFlow',1,NULL,NULL,'2024-08-20 20:47:40','Administration','2024-08-20 20:47:40'),(69,'ListTemplateVerification',1,NULL,NULL,'2024-09-02 10:03:18','Configuration','2024-09-02 10:03:18'),(70,'TemplateTask',1,NULL,NULL,'2024-09-16 11:25:00','Configuration','2024-09-16 11:25:00'),(71,'District',1,NULL,NULL,'2024-09-22 16:35:53','Administration','2024-09-22 16:35:53'),(72,'Zone',1,NULL,NULL,'2024-09-22 16:35:53','Administration','2024-09-22 16:35:53'),(73,'BoxZone',1,NULL,NULL,'2024-09-22 16:35:54','Administration','2024-09-22 16:35:54'),(74,'Nomenclature',1,NULL,NULL,'2024-09-22 16:35:54','Administration','2024-09-22 16:35:54'),(75,'ClientNomenclature',0,'App\\Models\\Nomenclature',NULL,'2024-09-22 16:35:54',NULL,'2024-09-22 16:35:54'),(76,'Team',1,NULL,NULL,'2024-09-22 16:35:55','Administration','2024-09-22 16:35:55'),(77,'ServiceInAddressList',1,NULL,NULL,'2024-09-28 01:13:29','Administration','2024-09-28 01:13:29'),(78,'FiltersTaskCalendar',1,NULL,NULL,'2024-11-12 22:38:04',NULL,'2024-11-12 22:38:04'),(79,'CompanyInformation',1,NULL,NULL,'2024-11-23 14:09:54',NULL,'2024-11-23 14:09:54');
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `modules` with 77 row(s)
--

--
-- Table structure for table `packages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('js','css') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packages`
--

LOCK TABLES `packages` WRITE;
/*!40000 ALTER TABLE `packages` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `packages` VALUES (1,'bootstrap-multiselect','/plugins/bootstrap-multiselect/css/bootstrap-multiselect.min.css','css',NULL,NULL),(2,'bootstrap-multiselect','/plugins/bootstrap-multiselect/js/bootstrap-multiselect.min.js','js',NULL,NULL),(3,'toaster','/plugins/toastr/build/toastr.min.css','css',NULL,NULL),(4,'select2','/plugins/select2/css/select2.min.css','css',NULL,NULL),(5,'select2','/plugins/select2/js/select2.min.js','js',NULL,NULL),(6,'ckeditor','/assets/libs/ckeditor/ckeditor.min.js','js',NULL,NULL),(7,'datatables.net-bs4','/assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css','css',NULL,NULL),(8,'datatables.net-buttons-bs4','/assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css','css',NULL,NULL),(9,'datatables.net-responsive-bs4','/assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css','css',NULL,NULL),(10,'datatables.net','/assets/libs/datatables.net/js/jquery.dataTables.min.js','js',NULL,NULL),(11,'datatables.net-bs4','/assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js','js',NULL,NULL),(12,'datatables.net-buttons','/assets/libs/datatables.net-buttons/js/dataTables.buttons.min.js','js',NULL,NULL),(13,'datatables.net-buttons-bs4','/assets/libs/datatables.net-buttons-bs4/js/buttons.bootstrap4.min.js','js',NULL,NULL),(14,'jszip','/assets/libs/jszip/jszip.min.js','js',NULL,NULL),(15,'pdfmake','/assets/libs/pdfmake/build/pdfmake.min.js','js',NULL,NULL),(16,'pdfmake','/assets/libs/pdfmake/build/vfs_fonts.js','js',NULL,NULL),(17,'datatables.net-buttons','/assets/libs/datatables.net-buttons/js/buttons.html5.min.js','js',NULL,NULL),(18,'datatables.net-buttons','/assets/libs/datatables.net-buttons/js/buttons.print.min.js','js',NULL,NULL),(19,'datatables.net-buttons','/assets/libs/datatables.net-buttons/js/buttons.colVis.min.js','js',NULL,NULL),(20,'apexcharts','/assets/libs/apexcharts/apexcharts.min.js','js',NULL,NULL),(21,'choice','assets/libs/choices.js/public/assets/styles/choices.min.css','css',NULL,NULL),(22,'choice','/assets/libs/choices.js/public/assets/scripts/choices.min.js','js',NULL,NULL),(23,'ckeditor','/assets/libs/@ckeditor/ckeditor5-build-classic/build/ckeditor.js','js',NULL,NULL),(24,'date_range_picket','/plugins/date-range-picker/date_range_picker.min.js','js',NULL,NULL),(25,'date_range_picket','/plugins/date-range-picker/date_range_picker.css','css',NULL,NULL),(26,'google_map','https://maps.googleapis.com/maps/api/js?key=AIzaSyCE7DdEderJ4A7bw6e29NKIlMcmjsVi7u4&v=beta&libraries=marker&callback=initMap','js','2024-02-09 17:45:24','2024-02-09 17:45:24');
/*!40000 ALTER TABLE `packages` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `packages` with 26 row(s)
--

--
-- Table structure for table `partners`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partners_id_index` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `partners`
--

LOCK TABLES `partners` WRITE;
/*!40000 ALTER TABLE `partners` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `partners` VALUES (1,'Oficina ','2024-05-26 22:27:43','2024-05-26 22:27:43'),(2,'PASEOS','2024-05-26 22:27:43','2024-05-26 22:27:43'),(3,'SANTA ANA','2024-05-26 22:27:43','2024-05-26 22:27:43');
/*!40000 ALTER TABLE `partners` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `partners` with 3 row(s)
--

--
-- Table structure for table `partner_module`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_module` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `partner_id` bigint(20) NOT NULL,
  `partner_module_id` bigint(20) NOT NULL,
  `partner_module_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `partner_module`
--

LOCK TABLES `partner_module` WRITE;
/*!40000 ALTER TABLE `partner_module` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `partner_module` VALUES (1,1,1,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(2,2,1,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(3,1,2,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(4,2,2,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(5,1,1,'App\\Models\\Voise','2024-02-09 18:11:58','2024-02-09 18:11:58'),(6,1,3,'App\\Models\\Internet','2024-02-10 10:35:30','2024-02-10 10:35:30'),(7,1,4,'App\\Models\\Internet','2024-02-10 10:36:45','2024-02-10 10:36:45'),(8,1,5,'App\\Models\\Internet','2024-02-10 10:38:00','2024-02-10 10:38:00'),(9,1,6,'App\\Models\\Internet','2024-02-10 10:39:03','2024-02-10 10:39:03'),(10,1,1,'App\\Models\\Bundle','2024-02-10 10:45:57','2024-02-10 10:45:57'),(11,1,2,'App\\Models\\Bundle','2024-02-10 10:47:26','2024-02-10 10:47:26'),(12,1,2,'App\\Models\\Router','2024-02-10 10:50:18','2024-02-10 10:50:18'),(13,2,2,'App\\Models\\Router','2024-02-10 10:50:18','2024-02-10 10:50:18'),(14,1,3,'App\\Models\\Router','2024-02-10 12:16:32','2024-02-10 12:16:32'),(15,2,3,'App\\Models\\Router','2024-02-10 12:16:32','2024-02-10 12:16:32'),(16,1,3,'App\\Models\\Bundle','2024-02-12 08:36:52','2024-02-12 08:36:52'),(17,2,3,'App\\Models\\Bundle','2024-02-12 08:36:52','2024-02-12 08:36:52'),(18,1,5,'App\\Models\\Bundle','2024-02-16 07:20:16','2024-02-16 07:20:16'),(19,2,5,'App\\Models\\Bundle','2024-02-16 07:20:16','2024-02-16 07:20:16'),(20,1,6,'App\\Models\\Bundle','2024-02-18 05:04:07','2024-02-18 05:04:07'),(21,2,6,'App\\Models\\Bundle','2024-02-18 05:04:07','2024-02-18 05:04:07'),(22,1,7,'App\\Models\\Bundle','2024-03-20 17:51:44','2024-03-20 17:51:44'),(23,2,7,'App\\Models\\Bundle','2024-03-20 17:51:44','2024-03-20 17:51:44'),(24,1,8,'App\\Models\\Bundle','2024-03-20 17:53:15','2024-03-20 17:53:15'),(25,2,8,'App\\Models\\Bundle','2024-03-20 17:53:15','2024-03-20 17:53:15'),(26,1,9,'App\\Models\\Bundle','2024-03-20 17:55:48','2024-03-20 17:55:48'),(27,2,9,'App\\Models\\Bundle','2024-03-20 17:55:48','2024-03-20 17:55:48'),(28,1,10,'App\\Models\\Bundle','2024-03-20 17:58:30','2024-03-20 17:58:30'),(29,2,10,'App\\Models\\Bundle','2024-03-20 17:58:30','2024-03-20 17:58:30'),(30,1,26,'App\\Models\\Internet','2024-05-19 01:46:49','2024-05-19 01:46:49'),(31,2,26,'App\\Models\\Internet','2024-05-19 01:46:49','2024-05-19 01:46:49'),(32,2,1,'App\\Models\\Internet','2024-05-19 12:37:57','2024-05-19 12:37:57'),(33,2,2,'App\\Models\\Internet','2024-05-19 12:37:57','2024-05-19 12:37:57'),(34,2,3,'App\\Models\\Internet','2024-05-19 12:37:57','2024-05-19 12:37:57'),(35,2,4,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(36,2,5,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(37,2,6,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(38,2,7,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(39,2,8,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(40,2,9,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(41,2,10,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(42,2,11,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(43,2,12,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(44,2,13,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(45,2,14,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(46,2,15,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(47,2,16,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(48,2,17,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(49,2,18,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(50,2,19,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(51,2,20,'App\\Models\\Internet','2024-05-19 12:37:58','2024-05-19 12:37:58'),(52,2,21,'App\\Models\\Internet','2024-05-19 12:37:59','2024-05-19 12:37:59'),(53,2,2,'App\\Models\\Voise','2024-05-19 14:58:53','2024-05-19 14:58:53'),(54,3,2,'App\\Models\\Voise','2024-05-19 14:58:53','2024-05-19 14:58:53'),(55,2,3,'App\\Models\\Voise','2024-05-19 14:59:42','2024-05-19 14:59:42'),(56,3,3,'App\\Models\\Voise','2024-05-19 14:59:42','2024-05-19 14:59:42'),(57,2,4,'App\\Models\\Voise','2024-05-19 14:59:42','2024-05-19 14:59:42'),(58,3,4,'App\\Models\\Voise','2024-05-19 14:59:42','2024-05-19 14:59:42'),(59,2,5,'App\\Models\\Voise','2024-05-19 14:59:42','2024-05-19 14:59:42'),(60,3,5,'App\\Models\\Voise','2024-05-19 14:59:42','2024-05-19 14:59:42'),(61,1,1,'App\\Models\\Internet','2024-05-23 16:11:21','2024-05-23 16:11:21'),(62,1,2,'App\\Models\\Internet','2024-05-23 16:11:21','2024-05-23 16:11:21'),(63,1,3,'App\\Models\\Internet','2024-05-23 16:11:21','2024-05-23 16:11:21'),(64,1,4,'App\\Models\\Internet','2024-05-23 16:11:21','2024-05-23 16:11:21'),(65,1,5,'App\\Models\\Internet','2024-05-23 16:11:21','2024-05-23 16:11:21'),(66,1,6,'App\\Models\\Internet','2024-05-23 16:11:21','2024-05-23 16:11:21'),(67,1,7,'App\\Models\\Internet','2024-05-23 16:11:21','2024-05-23 16:11:21'),(68,1,8,'App\\Models\\Internet','2024-05-23 16:11:21','2024-05-23 16:11:21'),(69,1,9,'App\\Models\\Internet','2024-05-23 16:11:21','2024-05-23 16:11:21'),(70,1,10,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(71,1,11,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(72,1,12,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(73,1,13,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(74,1,14,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(75,1,15,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(76,1,16,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(77,1,17,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(78,1,18,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(79,1,19,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(80,1,20,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(81,1,21,'App\\Models\\Internet','2024-05-23 16:11:22','2024-05-23 16:11:22'),(82,1,1,'App\\Models\\Voise','2024-05-23 16:11:38','2024-05-23 16:11:38'),(83,1,2,'App\\Models\\Voise','2024-05-23 16:11:38','2024-05-23 16:11:38'),(84,1,3,'App\\Models\\Voise','2024-05-23 16:11:38','2024-05-23 16:11:38'),(85,1,1,'App\\Models\\Internet','2024-05-24 01:15:53','2024-05-24 01:15:53'),(86,1,5,'App\\Models\\Internet','2024-05-24 01:15:53','2024-05-24 01:15:53'),(87,1,7,'App\\Models\\Internet','2024-05-24 01:15:53','2024-05-24 01:15:53'),(88,1,11,'App\\Models\\Internet','2024-05-24 01:15:53','2024-05-24 01:15:53'),(89,1,13,'App\\Models\\Internet','2024-05-24 01:15:53','2024-05-24 01:15:53'),(90,1,18,'App\\Models\\Internet','2024-05-24 01:15:53','2024-05-24 01:15:53'),(91,1,20,'App\\Models\\Internet','2024-05-24 01:15:53','2024-05-24 01:15:53'),(92,1,21,'App\\Models\\Internet','2024-05-24 01:15:53','2024-05-24 01:15:53'),(93,1,24,'App\\Models\\Internet','2024-05-24 01:15:53','2024-05-24 01:15:53'),(94,1,25,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(95,1,29,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(96,1,30,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(97,1,31,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(98,1,32,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(99,1,33,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(100,1,34,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(101,1,35,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(102,1,36,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(103,1,37,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(104,1,38,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(105,1,39,'App\\Models\\Internet','2024-05-24 01:15:54','2024-05-24 01:15:54'),(106,1,1,'App\\Models\\Internet','2024-05-27 00:15:36','2024-05-27 00:15:36'),(107,1,2,'App\\Models\\Internet','2024-05-27 00:15:36','2024-05-27 00:15:36'),(108,1,3,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(109,1,4,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(110,1,5,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(111,1,6,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(112,1,7,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(113,1,8,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(114,1,9,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(115,1,10,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(116,1,11,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(117,1,12,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(118,1,13,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(119,1,14,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(120,1,15,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(121,1,16,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(122,1,17,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(123,1,18,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(124,1,19,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(125,1,20,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(126,1,21,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(127,1,22,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(128,1,23,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(129,1,24,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(130,1,25,'App\\Models\\Internet','2024-05-27 00:15:37','2024-05-27 00:15:37'),(131,1,1,'App\\Models\\Voise','2024-05-27 00:15:55','2024-05-27 00:15:55'),(132,1,2,'App\\Models\\Voise','2024-05-27 00:15:55','2024-05-27 00:15:55'),(133,1,3,'App\\Models\\Voise','2024-05-27 00:15:55','2024-05-27 00:15:55'),(134,1,1,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(135,1,5,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(136,1,7,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(137,1,11,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(138,1,13,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(139,1,18,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(140,1,20,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(141,1,21,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(142,1,24,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(143,1,25,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(144,1,29,'App\\Models\\Internet','2024-05-29 04:45:17','2024-05-29 04:45:17'),(145,1,30,'App\\Models\\Internet','2024-05-29 04:45:18','2024-05-29 04:45:18'),(146,1,31,'App\\Models\\Internet','2024-05-29 04:45:18','2024-05-29 04:45:18'),(147,1,32,'App\\Models\\Internet','2024-05-29 04:45:18','2024-05-29 04:45:18'),(148,1,33,'App\\Models\\Internet','2024-05-29 04:45:18','2024-05-29 04:45:18'),(149,1,34,'App\\Models\\Internet','2024-05-29 04:45:18','2024-05-29 04:45:18'),(150,1,35,'App\\Models\\Internet','2024-05-29 04:45:18','2024-05-29 04:45:18'),(151,1,36,'App\\Models\\Internet','2024-05-29 04:45:18','2024-05-29 04:45:18'),(152,1,37,'App\\Models\\Internet','2024-05-29 04:45:18','2024-05-29 04:45:18'),(153,1,38,'App\\Models\\Internet','2024-05-29 04:45:18','2024-05-29 04:45:18'),(154,1,39,'App\\Models\\Internet','2024-05-29 04:45:18','2024-05-29 04:45:18'),(155,1,1,'App\\Models\\Voise','2024-05-29 04:45:53','2024-05-29 04:45:53'),(156,1,2,'App\\Models\\Voise','2024-05-29 04:45:53','2024-05-29 04:45:53'),(157,1,3,'App\\Models\\Voise','2024-05-29 04:45:53','2024-05-29 04:45:53'),(158,1,1,'App\\Models\\Custom','2024-05-29 04:46:43','2024-05-29 04:46:43'),(159,1,1,'App\\Models\\Custom','2024-05-29 04:46:43','2024-05-29 04:46:43'),(160,1,1,'App\\Models\\Custom','2024-05-29 04:46:43','2024-05-29 04:46:43'),(161,1,1,'App\\Models\\Custom','2024-05-29 04:46:43','2024-05-29 04:46:43'),(162,1,2,'App\\Models\\Bundle','2024-05-29 04:47:06','2024-05-29 04:47:06'),(163,1,3,'App\\Models\\Bundle','2024-05-29 04:47:06','2024-05-29 04:47:06'),(164,1,5,'App\\Models\\Bundle','2024-05-29 04:47:06','2024-05-29 04:47:06'),(165,1,7,'App\\Models\\Bundle','2024-05-29 04:47:06','2024-05-29 04:47:06'),(166,1,15,'App\\Models\\Bundle','2024-05-29 04:47:06','2024-05-29 04:47:06'),(167,1,16,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(168,1,17,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(169,1,18,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(170,1,19,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(171,1,20,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(172,1,21,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(173,1,22,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(174,1,23,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(175,1,24,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(176,1,25,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(177,1,26,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(178,1,27,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(179,1,28,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(180,1,29,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(181,1,30,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(182,1,31,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(183,1,32,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(184,1,33,'App\\Models\\Bundle','2024-05-29 04:47:09','2024-05-29 04:47:09'),(185,1,34,'App\\Models\\Bundle','2024-05-29 04:47:09','2024-05-29 04:47:09'),(186,1,35,'App\\Models\\Bundle','2024-05-29 04:47:09','2024-05-29 04:47:09'),(187,1,2,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(188,1,3,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(189,1,5,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(190,1,7,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(191,1,15,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(192,1,16,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(193,1,17,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(194,1,18,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(195,1,19,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(196,1,20,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(197,1,21,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(198,1,22,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(199,1,23,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(200,1,24,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(201,1,25,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(202,1,26,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(203,1,27,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(204,1,28,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(205,1,29,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(206,1,30,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(207,1,31,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(208,1,32,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(209,1,33,'App\\Models\\Bundle','2024-06-05 05:48:28','2024-06-05 05:48:28'),(210,1,34,'App\\Models\\Bundle','2024-06-05 05:48:28','2024-06-05 05:48:28'),(211,1,35,'App\\Models\\Bundle','2024-06-05 05:48:28','2024-06-05 05:48:28'),(212,1,2,'App\\Models\\Bundle','2024-06-05 10:18:01','2024-06-05 10:18:01'),(213,1,3,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(214,1,5,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(215,1,7,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(216,1,15,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(217,1,16,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(218,1,17,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(219,1,18,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(220,1,19,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(221,1,20,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(222,1,21,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(223,1,22,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(224,1,23,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(225,1,24,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(226,1,25,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(227,1,26,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(228,1,27,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(229,1,28,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(230,1,29,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(231,1,30,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(232,1,31,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(233,1,32,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(234,1,33,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(235,1,34,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(236,1,35,'App\\Models\\Bundle','2024-06-05 10:18:05','2024-06-05 10:18:05'),(237,1,1,'App\\Models\\Project','2024-08-04 09:52:42','2024-08-04 09:52:42'),(238,2,1,'App\\Models\\Project','2024-08-04 09:52:42','2024-08-04 09:52:42'),(239,1,2,'App\\Models\\Project','2024-08-06 12:27:37','2024-08-06 12:27:37'),(240,2,2,'App\\Models\\Project','2024-08-06 12:27:37','2024-08-06 12:27:37'),(241,3,2,'App\\Models\\Project','2024-08-06 12:27:37','2024-08-06 12:27:37'),(242,1,3,'App\\Models\\Project','2024-08-08 14:19:40','2024-08-08 14:19:40'),(243,2,3,'App\\Models\\Project','2024-08-08 14:19:40','2024-08-08 14:19:40'),(244,3,3,'App\\Models\\Project','2024-08-08 14:19:40','2024-08-08 14:19:40'),(245,1,4,'App\\Models\\Project','2024-08-08 14:20:23','2024-08-08 14:20:23'),(246,2,4,'App\\Models\\Project','2024-08-08 14:20:23','2024-08-08 14:20:23'),(247,3,4,'App\\Models\\Project','2024-08-08 14:20:23','2024-08-08 14:20:23'),(248,1,37,'App\\Models\\Bundle','2024-10-23 16:33:22','2024-10-23 16:33:22'),(249,2,37,'App\\Models\\Bundle','2024-10-23 16:33:22','2024-10-23 16:33:22'),(250,3,37,'App\\Models\\Bundle','2024-10-23 16:33:22','2024-10-23 16:33:22'),(251,1,40,'App\\Models\\Internet','2024-11-06 14:01:25','2024-11-06 14:01:25'),(252,2,40,'App\\Models\\Internet','2024-11-06 14:01:25','2024-11-06 14:01:25'),(253,3,40,'App\\Models\\Internet','2024-11-06 14:01:25','2024-11-06 14:01:25'),(254,1,36,'App\\Models\\Bundle','2024-11-11 11:36:16','2024-11-11 11:36:16');
/*!40000 ALTER TABLE `partner_module` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `partner_module` with 254 row(s)
--

--
-- Table structure for table `passive_equipments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `passive_equipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rack_id` bigint(20) unsigned NOT NULL,
  `type_id` bigint(20) unsigned NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `passive_equipments_rack_id_foreign` (`rack_id`),
  KEY `passive_equipments_type_id_foreign` (`type_id`),
  KEY `passive_equipments_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `passive_equipments_created_by_foreign` (`created_by`),
  KEY `passive_equipments_updated_by_foreign` (`updated_by`),
  CONSTRAINT `passive_equipments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `passive_equipments_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `passive_equipments_rack_id_foreign` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`),
  CONSTRAINT `passive_equipments_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `passive_equipment_types` (`id`),
  CONSTRAINT `passive_equipments_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `passive_equipments`
--

LOCK TABLES `passive_equipments` WRITE;
/*!40000 ALTER TABLE `passive_equipments` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `passive_equipments` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `passive_equipments` with 0 row(s)
--

--
-- Table structure for table `passive_equipment_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `passive_equipment_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('DFO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ports` int(11) NOT NULL,
  `trays` int(11) NOT NULL,
  `brand_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `passive_equipment_types_brand_id_foreign` (`brand_id`),
  KEY `passive_equipment_types_created_by_foreign` (`created_by`),
  KEY `passive_equipment_types_updated_by_foreign` (`updated_by`),
  CONSTRAINT `passive_equipment_types_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `passive_equipment_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `passive_equipment_types_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `passive_equipment_types`
--

LOCK TABLES `passive_equipment_types` WRITE;
/*!40000 ALTER TABLE `passive_equipment_types` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `passive_equipment_types` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `passive_equipment_types` with 0 row(s)
--

--
-- Table structure for table `password_resets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `password_resets` with 0 row(s)
--

--
-- Table structure for table `payment_accounts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_accounts`
--

LOCK TABLES `payment_accounts` WRITE;
/*!40000 ALTER TABLE `payment_accounts` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `payment_accounts` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `payment_accounts` with 0 row(s)
--

--
-- Table structure for table `payment_promises`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_promises` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) unsigned NOT NULL,
  `court_date` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` double(8,2) NOT NULL DEFAULT '0.00',
  `action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_promises`
--

LOCK TABLES `payment_promises` WRITE;
/*!40000 ALTER TABLE `payment_promises` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `payment_promises` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `payment_promises` with 0 row(s)
--

--
-- Table structure for table `permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `permissions` VALUES (1,'plan_view_internet','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(2,'plan_add_internet','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(3,'plan_edit_internet','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(4,'plan_delete_internet','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(5,'plan_export_internet','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(6,'plan_view_voz','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(7,'plan_add_voz','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(8,'plan_edit_voz','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(9,'plan_delete_voz','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(10,'plan_export_voz','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(11,'plan_view_custom','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(12,'plan_add_custom','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(13,'plan_edit_custom','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(14,'plan_delete_custom','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(15,'plan_export_custom','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(16,'plan_view_package','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(17,'plan_add_package','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(18,'plan_edit_package','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(19,'plan_delete_package','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(20,'plan_export_package','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(21,'crm_view_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(22,'crm_add_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(23,'crm_edit_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(24,'crm_delete_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(25,'crm_export_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(26,'crm_information_view_tab_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(27,'crm_document_view_tab_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(28,'client_view_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(29,'client_add_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(30,'client_edit_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(31,'client_delete_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(32,'client_export_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(33,'client_view_dashboard','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(34,'client_information_view_tab_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(35,'client_service_view_tab_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(36,'client_payroll_view_tab_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(37,'seller_view_seller','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(38,'seller_add_seller','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(39,'seller_view_panel','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(40,'seller_view_dashboard','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(41,'panel_view_prospects','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(42,'panel_view_sales','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(43,'panel_view_stadistics','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(44,'panel_view_billing','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(45,'billing_payment_view_clients','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(46,'billing_view_detail_payments','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(47,'billing_payment_sellers','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(48,'billing_view_transactions','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(49,'seller_add_payment','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(50,'seller_edit_payment','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(51,'seller_delete_payment','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(52,'seller_download_payment','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(53,'ticket_view_open','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(54,'ticket_add_open','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(55,'ticket_edit_open','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(56,'ticket_delete_open','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(57,'ticket_export_open','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(58,'ticket_view_close','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(59,'ticket_add_close','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(60,'ticket_edit_close','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(61,'ticket_delete_close','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(62,'ticket_export_close','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(63,'ticket_view_recycling','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(64,'ticket_add_recycling','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(65,'ticket_edit_recycling','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(66,'ticket_delete_recycling','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(67,'ticket_export_recycling','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(68,'ticket_view_dashboard','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(69,'finance_view_transactions','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(70,'finance_edit_transactions','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(71,'finance_delete_transactions','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(72,'finance_export_transactions','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(73,'finance_view_billing','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(74,'finance_edit_billing','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(75,'finance_delete_billing','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(76,'finance_export_billing','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(77,'finance_view_payments','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(78,'finance_edit_payments','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(79,'finance_delete_payments','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(80,'finance_export_payments','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(81,'maps_view_maps','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(82,'router_view_router','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(83,'router_add_router','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(84,'router_edit_router','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(85,'router_delete_router','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(86,'router_export_router','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(87,'ipv4_view_ipv4','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(88,'ipv4_add_ipv4','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(89,'ipv4_edit_ipv4','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(90,'ipv4_delete_ipv4','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(91,'ipv4_export_ipv4','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(92,'admin_view_module','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(93,'admin_view_meganet','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(94,'admin_view_registers','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(95,'admin_view_information','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(96,'admin_view_reports','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(97,'config_view_module','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(98,'config_view_system','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(99,'config_view_main','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(100,'config_view_finance','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(101,'config_view_network_management','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(102,'config_view_helpdesk','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(103,'config_view_scheduling','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(104,'config_view_potencial_customer','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(105,'config_view_inventory','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(106,'config_view_integrations','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(107,'config_view_voice','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(108,'config_view_tools','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(109,'config_view_sales','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(110,'config_view_google_maps','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(111,'user_view_user','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(112,'user_add_user','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(113,'user_edit_user','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(114,'user_delete_user','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(115,'role_view_role','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(116,'role_add_role','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(117,'role_edit_role','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(118,'role_delete_role','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(119,'role_permission_role','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(120,'partners_view_partners','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(121,'partners_add_partners','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(122,'partners_edit_partners','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(123,'partners_delete_partners','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(124,'partners_export_partners','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(125,'location_view_location','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(126,'location_add_location','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(127,'location_edit_location','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(128,'location_delete_location','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(129,'location_export_location','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(130,'apikey_view_apikey','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(131,'state_view_state','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(132,'state_add_state','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(133,'state_edit_state','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(134,'state_delete_state','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(135,'state_export_state','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(136,'municipality_view_municipality','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(137,'municipality_add_municipality','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(138,'municipality_edit_municipality','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(139,'municipality_delete_municipality','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(140,'municipality_export_municipality','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(141,'colony_view_colony','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(142,'colony_add_colony','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(143,'colony_edit_colony','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(144,'colony_delete_colony','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(145,'colony_export_colony','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(146,'method_payment_view_method_payment','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(147,'method_payment_add_method_payment','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(148,'method_payment_edit_method_payment','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(149,'method_payment_delete_method_payment','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(150,'method_payment_export_method_payment','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(151,'ift_view_ift','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(152,'ift_add_ift','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(153,'ift_edit_ift','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(154,'ift_delete_ift','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(155,'ift_export_ift','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(156,'package_view_package','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(157,'additional_fields_view_additional_fields','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(158,'additional_fields_add_additional_fields','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(159,'additional_fields_edit_additional_fields','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(160,'additional_fields_delete_additional_fields','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(161,'additional_fields_export_additional_fields','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(162,'labels_view_labels','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(163,'translation_view_translation','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(164,'admin_view_files','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(165,'templates_view_templates','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(166,'company_view_information','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(167,'dashboard_system_status','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(168,'dashboard_clientes','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(169,'dashboard_payroll','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(170,'crm_information','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(171,'crm_information_geolocation_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(172,'crm_document','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(173,'crm_document_view_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(174,'crm_document_add_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(175,'crm_document_edit_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(176,'crm_document_delete_crm','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(177,'client_information_geolocation_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(178,'client_service','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(179,'client_service_internet','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(180,'client_service_internet_view_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(181,'client_service_internet_add_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(182,'client_service_internet_edit_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(183,'client_service_internet_delete_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(184,'client_service_voz','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(185,'client_service_voz_view_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(186,'client_service_voz_add_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(187,'client_service_voz_edit_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(188,'client_service_voz_delete_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(189,'client_service_bundle','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(190,'client_service_bundle_view_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(191,'client_service_bundle_add_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(192,'client_service_bundle_edit_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(193,'client_service_bundle_delete_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(194,'client_payroll','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(195,'client_payroll_payment','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(196,'client_payroll_payment_view_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(197,'client_payroll_payment_add_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(198,'client_payroll_payment_edit_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(199,'client_payroll_payment_delete_client','web','2024-06-21 22:41:15','2024-06-21 22:41:15'),(200,'crm_view_of_convert_crm_to_client','web','2024-08-27 18:44:24','2024-08-27 18:44:24'),(201,'seller_view_prospects','web','2024-09-11 11:21:06','2024-09-11 11:21:06'),(202,'seller_view_statics','web','2024-09-11 11:21:06','2024-09-11 11:21:06'),(203,'seller_view_sales','web','2024-09-11 11:21:07','2024-09-11 11:21:07'),(204,'seller_view_billing','web','2024-09-11 11:21:07','2024-09-11 11:21:07'),(205,'seller_follow_payment_client','web','2024-09-11 11:21:07','2024-09-11 11:21:07'),(206,'seller_view_all_payments_for_seller','web','2024-09-11 11:21:07','2024-09-11 11:21:07'),(207,'seller_view_all_transactions_for_seller','web','2024-09-11 11:21:08','2024-09-11 11:21:08'),(208,'crm_convert_crm','web','2024-09-11 11:21:08','2024-09-11 11:21:08'),(209,'client_document_view_client','web','2024-09-11 21:47:56','2024-09-11 21:47:56'),(210,'client_document_add_client','web','2024-09-11 21:47:56','2024-09-11 21:47:56'),(211,'client_document_edit_client','web','2024-09-11 21:47:57','2024-09-11 21:47:57'),(212,'client_document_delete_client','web','2024-09-11 21:47:57','2024-09-11 21:47:57'),(213,'client_document_view_tab_client','web','2024-09-11 21:47:57','2024-09-11 21:47:57'),(214,'client_billing_view_tab_client','web','2024-09-11 21:47:57','2024-09-11 21:47:57'),(215,'client_billing_transaction_client','web','2024-09-11 21:47:58','2024-09-11 21:47:58'),(216,'client_billing_transaction_add','web','2024-09-11 21:47:58','2024-09-11 21:47:58'),(217,'client_billing_transaction_edit','web','2024-09-11 21:47:58','2024-09-11 21:47:58'),(218,'client_billing_transaction_delete','web','2024-09-11 21:47:58','2024-09-11 21:47:58'),(219,'client_billing_invoice_client','web','2024-09-11 21:47:59','2024-09-11 21:47:59'),(220,'client_billing_invoice_add','web','2024-09-11 21:47:59','2024-09-11 21:47:59'),(221,'client_billing_invoice_edit','web','2024-09-11 21:47:59','2024-09-11 21:47:59'),(222,'client_billing_invoice_delete','web','2024-09-11 21:47:59','2024-09-11 21:47:59'),(223,'scheduling_view_scheduling','web','2024-09-11 21:48:00','2024-09-11 21:48:00'),(224,'scheduling_project_view_project','web','2024-09-11 21:48:00','2024-09-11 21:48:00'),(225,'scheduling_project_create','web','2024-09-11 21:48:00','2024-09-11 21:48:00'),(226,'scheduling_project_update','web','2024-09-11 21:48:00','2024-09-11 21:48:00'),(227,'scheduling_project_delete','web','2024-09-11 21:48:01','2024-09-11 21:48:01'),(228,'scheduling_task_view_task','web','2024-09-11 21:48:01','2024-09-11 21:48:01'),(229,'scheduling_task_create','web','2024-09-11 21:48:01','2024-09-11 21:48:01'),(230,'scheduling_task_update','web','2024-09-11 21:48:01','2024-09-11 21:48:01'),(231,'scheduling_task_delete','web','2024-09-11 21:48:02','2024-09-11 21:48:02'),(232,'scheduling_view_calendar','web','2024-09-11 21:48:02','2024-09-11 21:48:02'),(233,'admin_view_scripts','web','2024-09-13 10:31:37','2024-09-13 10:31:37'),(234,'templatetask_view_templatetask','web','2024-09-16 11:25:00','2024-09-16 11:25:00'),(235,'templatetask_add_templatetask','web','2024-09-16 11:25:01','2024-09-16 11:25:01'),(236,'templatetask_edit_templatetask','web','2024-09-16 11:25:01','2024-09-16 11:25:01'),(237,'templatetask_delete_templatetask','web','2024-09-16 11:25:01','2024-09-16 11:25:01'),(238,'templatetask_export_templatetask','web','2024-09-16 11:25:01','2024-09-16 11:25:01'),(239,'task_view_task','web','2024-09-26 11:57:35','2024-09-26 11:57:35'),(240,'task_add_task','web','2024-09-26 11:57:35','2024-09-26 11:57:35'),(241,'task_edit_task','web','2024-09-26 11:57:35','2024-09-26 11:57:35'),(242,'task_delete_task','web','2024-09-26 11:57:35','2024-09-26 11:57:35'),(243,'task_export_task','web','2024-09-26 11:57:35','2024-09-26 11:57:35'),(244,'task_view_full_task','web','2024-09-26 11:57:35','2024-09-26 11:57:35'),(245,'task_view_archived_task','web','2024-09-29 16:26:06','2024-09-29 16:26:06'),(246,'task_filter_project','web','2024-11-04 08:09:58','2024-11-04 08:09:58'),(247,'task_filter_status','web','2024-11-04 08:09:58','2024-11-04 08:09:58'),(248,'task_filter_partner','web','2024-11-04 08:09:58','2024-11-04 08:09:58'),(249,'task_filter_assigned_to','web','2024-11-04 08:09:58','2024-11-04 08:09:58'),(250,'task_filter_filter','web','2024-11-04 08:09:58','2024-11-04 08:09:58'),(251,'calendar_filter_project','web','2024-11-04 08:09:58','2024-11-04 08:09:58'),(252,'calendar_filter_status','web','2024-11-04 08:09:58','2024-11-04 08:09:58'),(253,'calendar_filter_partner','web','2024-11-04 08:09:58','2024-11-04 08:09:58'),(254,'calendar_filter_assigned_to','web','2024-11-04 08:09:58','2024-11-04 08:09:58'),(255,'calendar_filter_filter','web','2024-11-04 08:09:58','2024-11-04 08:09:58'),(256,'user_permission_user','web','2024-11-11 11:39:34','2024-11-11 11:39:34'),(257,'client_service_custom','web','2024-11-12 11:26:27','2024-11-12 11:26:27'),(258,'client_service_custom_view_client','web','2024-11-12 11:26:27','2024-11-12 11:26:27'),(259,'client_service_custom_add_client','web','2024-11-12 11:26:27','2024-11-12 11:26:27'),(260,'client_service_custom_edit_client','web','2024-11-12 11:26:27','2024-11-12 11:26:27'),(261,'client_service_custom_delete_client','web','2024-11-12 11:26:27','2024-11-12 11:26:27'),(262,'company_information_view_company_information','web','2024-11-23 14:09:55','2024-11-23 14:09:55'),(263,'company_information_add_company_information','web','2024-11-23 14:09:55','2024-11-23 14:09:55'),(264,'company_information_edit_company_information','web','2024-11-23 14:09:55','2024-11-23 14:09:55'),(265,'company_information_delete_company_information','web','2024-11-23 14:09:55','2024-11-23 14:09:55'),(266,'task_archive_task','web','2024-11-26 11:52:56','2024-11-26 11:52:56'),(267,'dashboard_view_dashboard','web','2024-12-02 09:05:15','2024-12-02 09:05:15'),(268,'dashboard_view_card_client_inline','web','2024-12-02 09:05:15','2024-12-02 09:05:15'),(269,'dashboard_view_card_client_new','web','2024-12-02 09:05:15','2024-12-02 09:05:15'),(270,'dashboard_view_card_tickets_open_new','web','2024-12-02 09:05:15','2024-12-02 09:05:15'),(271,'dashboard_view_card_device_not_responding','web','2024-12-02 09:05:15','2024-12-02 09:05:15'),(272,'dashboard_view_block_client','web','2024-12-02 09:05:16','2024-12-02 09:05:16'),(273,'dashboard_view_block_ticket','web','2024-12-02 09:05:16','2024-12-02 09:05:16'),(274,'dashboard_view_block_finance','web','2024-12-02 09:05:16','2024-12-02 09:05:16'),(275,'dashboard_view_info_invoice_transaction','web','2024-12-02 09:05:16','2024-12-02 09:05:16'),(276,'dashboard_view_block_server_status','web','2024-12-03 11:08:10','2024-12-03 11:08:10'),(277,'client_edit_fecha_corte','web','2024-12-09 14:19:41','2024-12-09 14:19:41'),(278,'client_edit_fecha_pago','web','2024-12-09 14:19:41','2024-12-09 14:19:41'),(279,'client_edit_balance','web','2024-12-09 14:19:41','2024-12-09 14:19:41'),(280,'client_edit_id','web','2024-12-09 14:19:41','2024-12-09 14:19:41');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `permissions` with 280 row(s)
--

--
-- Table structure for table `plan_bundles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan_bundles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bundle_id` bigint(20) NOT NULL,
  `plan_bundle_id` bigint(20) NOT NULL,
  `cant` bigint(20) NOT NULL,
  `plan_bundle_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plan_bundles`
--

LOCK TABLES `plan_bundles` WRITE;
/*!40000 ALTER TABLE `plan_bundles` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `plan_bundles` VALUES (4,3,11,1,'App\\Models\\Internet',NULL,NULL),(5,3,3,1,'App\\Models\\Custom',NULL,NULL),(6,3,2,1,'App\\Models\\Voise',NULL,NULL),(10,7,29,1,'App\\Models\\Internet',NULL,NULL),(11,7,1,1,'App\\Models\\Voise',NULL,NULL),(12,15,32,1,'App\\Models\\Internet',NULL,NULL),(13,15,3,1,'App\\Models\\Custom',NULL,NULL),(14,15,1,1,'App\\Models\\Voise',NULL,NULL),(18,17,33,1,'App\\Models\\Internet',NULL,NULL),(19,17,3,1,'App\\Models\\Custom',NULL,NULL),(20,17,1,1,'App\\Models\\Voise',NULL,NULL),(21,18,34,1,'App\\Models\\Internet',NULL,NULL),(22,18,3,1,'App\\Models\\Custom',NULL,NULL),(23,18,1,1,'App\\Models\\Voise',NULL,NULL),(24,19,35,1,'App\\Models\\Internet',NULL,NULL),(25,19,3,1,'App\\Models\\Custom',NULL,NULL),(26,19,1,1,'App\\Models\\Voise',NULL,NULL),(27,20,1,1,'App\\Models\\Internet',NULL,NULL),(28,20,2,1,'App\\Models\\Voise',NULL,NULL),(29,21,36,1,'App\\Models\\Internet',NULL,NULL),(30,21,2,1,'App\\Models\\Voise',NULL,NULL),(31,22,32,1,'App\\Models\\Internet',NULL,NULL),(32,22,3,1,'App\\Models\\Custom',NULL,NULL),(33,22,2,1,'App\\Models\\Voise',NULL,NULL),(34,23,33,1,'App\\Models\\Internet',NULL,NULL),(35,23,3,1,'App\\Models\\Custom',NULL,NULL),(36,23,2,1,'App\\Models\\Voise',NULL,NULL),(37,24,34,1,'App\\Models\\Internet',NULL,NULL),(38,24,3,1,'App\\Models\\Custom',NULL,NULL),(39,24,2,1,'App\\Models\\Voise',NULL,NULL),(40,25,35,1,'App\\Models\\Internet',NULL,NULL),(41,25,3,1,'App\\Models\\Custom',NULL,NULL),(42,25,2,1,'App\\Models\\Voise',NULL,NULL),(43,26,1,1,'App\\Models\\Internet',NULL,NULL),(44,26,1,1,'App\\Models\\Voise',NULL,NULL),(45,27,21,1,'App\\Models\\Internet',NULL,NULL),(46,27,1,1,'App\\Models\\Voise',NULL,NULL),(47,28,21,1,'App\\Models\\Internet',NULL,NULL),(48,28,3,1,'App\\Models\\Custom',NULL,NULL),(49,28,1,1,'App\\Models\\Voise',NULL,NULL),(53,31,32,1,'App\\Models\\Internet',NULL,NULL),(54,31,1,1,'App\\Models\\Voise',NULL,NULL),(59,34,32,1,'App\\Models\\Internet',NULL,NULL),(60,34,1,1,'App\\Models\\Voise',NULL,NULL),(61,35,34,1,'App\\Models\\Internet',NULL,NULL),(62,35,3,1,'App\\Models\\Custom',NULL,NULL),(63,35,1,1,'App\\Models\\Voise',NULL,NULL),(66,32,37,1,'App\\Models\\Internet',NULL,NULL),(67,32,1,1,'App\\Models\\Voise',NULL,NULL),(70,29,36,1,'App\\Models\\Internet',NULL,NULL),(74,37,32,1,'App\\Models\\Internet',NULL,NULL),(75,37,1,1,'App\\Models\\Voise',NULL,NULL),(76,16,1,1,'App\\Models\\Internet',NULL,NULL),(77,16,1,1,'App\\Models\\Voise',NULL,NULL),(78,33,38,1,'App\\Models\\Internet',NULL,NULL),(79,33,3,1,'App\\Models\\Voise',NULL,NULL),(80,5,36,1,'App\\Models\\Internet',NULL,NULL),(81,5,2,1,'App\\Models\\Voise',NULL,NULL),(82,5,3,1,'App\\Models\\Custom',NULL,NULL),(83,36,18,1,'App\\Models\\Internet',NULL,NULL),(84,36,2,1,'App\\Models\\Voise',NULL,NULL),(87,2,40,1,'App\\Models\\Internet',NULL,NULL),(88,2,2,1,'App\\Models\\Voise',NULL,NULL),(89,2,3,1,'App\\Models\\Custom',NULL,NULL),(90,30,40,1,'App\\Models\\Internet',NULL,NULL),(91,30,1,1,'App\\Models\\Voise',NULL,NULL),(92,38,32,1,'App\\Models\\Internet',NULL,NULL),(93,38,3,1,'App\\Models\\Voise',NULL,NULL);
/*!40000 ALTER TABLE `plan_bundles` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `plan_bundles` with 68 row(s)
--

--
-- Table structure for table `plan_custom_client`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan_custom_client` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `custom_id` bigint(20) NOT NULL,
  `tarifa_custom_id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plan_custom_client`
--

LOCK TABLES `plan_custom_client` WRITE;
/*!40000 ALTER TABLE `plan_custom_client` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `plan_custom_client` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `plan_custom_client` with 0 row(s)
--

--
-- Table structure for table `plan_type_billings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan_type_billings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type_billing_id` bigint(20) NOT NULL,
  `plan_billing_id` bigint(20) NOT NULL,
  `plan_billing_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plan_type_billings`
--

LOCK TABLES `plan_type_billings` WRITE;
/*!40000 ALTER TABLE `plan_type_billings` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `plan_type_billings` VALUES (1,1,1,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(2,2,1,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(3,3,1,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(4,1,2,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(7,1,3,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(8,2,3,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(9,3,3,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(10,1,4,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(11,2,4,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(12,3,4,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(13,1,5,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(14,2,5,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(15,3,5,'App\\Models\\Internet','2024-02-09 17:43:52','2024-02-09 17:43:52'),(16,1,6,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(17,2,6,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(18,3,6,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(19,1,7,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(20,2,7,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(21,3,7,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(22,1,8,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(23,2,8,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(24,3,8,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(25,1,9,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(26,2,9,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(27,3,9,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(28,1,10,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(29,2,10,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(30,3,10,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(31,1,11,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(32,2,11,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(33,3,11,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(34,1,12,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(35,2,12,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(36,3,12,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(37,1,13,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(38,2,13,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(39,3,13,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(40,1,14,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(41,2,14,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(42,3,14,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(43,1,15,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(44,2,15,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(45,3,15,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(46,1,16,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(47,2,16,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(48,3,16,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(49,1,17,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(50,2,17,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(51,3,17,'App\\Models\\Internet','2024-02-09 17:43:53','2024-02-09 17:43:53'),(52,1,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(53,2,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(54,3,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(55,1,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(56,2,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(57,3,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(58,1,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(59,2,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(60,3,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(61,1,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(62,2,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(63,3,1,'App\\Models\\Voise','2024-02-09 17:43:53','2024-02-09 17:43:53'),(64,1,1,'App\\Models\\Voise','2024-02-09 17:43:54','2024-02-09 17:43:54'),(65,2,1,'App\\Models\\Voise','2024-02-09 17:43:54','2024-02-09 17:43:54'),(66,3,1,'App\\Models\\Voise','2024-02-09 17:43:54','2024-02-09 17:43:54'),(67,1,1,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(68,2,1,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(69,3,1,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(70,1,2,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(71,2,2,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(72,3,2,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(73,1,3,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(74,2,3,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(75,3,3,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(76,1,4,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(77,2,4,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(78,3,4,'App\\Models\\Custom','2024-02-09 17:43:54','2024-02-09 17:43:54'),(79,1,1,'App\\Models\\Bundle','2024-02-09 17:43:54','2024-02-09 17:43:54'),(80,2,1,'App\\Models\\Bundle','2024-02-09 17:43:54','2024-02-09 17:43:54'),(81,3,1,'App\\Models\\Bundle','2024-02-09 17:43:54','2024-02-09 17:43:54'),(82,3,1,'App\\Models\\Internet','2024-02-09 18:06:43','2024-02-09 18:06:43'),(84,1,2,'App\\Models\\Internet','2024-02-09 18:07:58','2024-02-09 18:07:58'),(85,1,1,'App\\Models\\Voise','2024-02-09 18:11:58','2024-02-09 18:11:58'),(86,1,3,'App\\Models\\Internet','2024-02-10 10:35:30','2024-02-10 10:35:30'),(87,1,4,'App\\Models\\Internet','2024-02-10 10:36:45','2024-02-10 10:36:45'),(88,1,5,'App\\Models\\Internet','2024-02-10 10:38:00','2024-02-10 10:38:00'),(89,1,6,'App\\Models\\Internet','2024-02-10 10:39:03','2024-02-10 10:39:03'),(90,1,1,'App\\Models\\Custom','2024-02-10 10:40:15','2024-02-10 10:40:15'),(91,1,2,'App\\Models\\Custom','2024-02-10 10:41:14','2024-02-10 10:41:14'),(92,1,3,'App\\Models\\Custom','2024-02-10 10:42:13','2024-02-10 10:42:13'),(93,1,4,'App\\Models\\Custom','2024-02-10 10:43:18','2024-02-10 10:43:18'),(94,1,1,'App\\Models\\Bundle','2024-02-10 10:45:57','2024-02-10 10:45:57'),(95,1,2,'App\\Models\\Bundle','2024-02-10 10:47:26','2024-02-10 10:47:26'),(96,1,3,'App\\Models\\Bundle','2024-02-12 08:36:52','2024-02-12 08:36:52'),(97,1,5,'App\\Models\\Custom','2024-02-16 07:19:37','2024-02-16 07:19:37'),(98,2,5,'App\\Models\\Custom','2024-02-16 07:19:37','2024-02-16 07:19:37'),(99,3,5,'App\\Models\\Custom','2024-02-16 07:19:37','2024-02-16 07:19:37'),(100,1,5,'App\\Models\\Bundle','2024-02-16 07:20:16','2024-02-16 07:20:16'),(101,2,5,'App\\Models\\Bundle','2024-02-16 07:20:16','2024-02-16 07:20:16'),(102,3,5,'App\\Models\\Bundle','2024-02-16 07:20:16','2024-02-16 07:20:16'),(103,1,6,'App\\Models\\Custom','2024-02-18 05:03:29','2024-02-18 05:03:29'),(104,2,6,'App\\Models\\Custom','2024-02-18 05:03:29','2024-02-18 05:03:29'),(105,3,6,'App\\Models\\Custom','2024-02-18 05:03:29','2024-02-18 05:03:29'),(106,1,6,'App\\Models\\Bundle','2024-02-18 05:04:07','2024-02-18 05:04:07'),(109,1,7,'App\\Models\\Bundle','2024-03-20 17:51:44','2024-03-20 17:51:44'),(110,1,8,'App\\Models\\Bundle','2024-03-20 17:53:15','2024-03-20 17:53:15'),(111,1,9,'App\\Models\\Bundle','2024-03-20 17:55:48','2024-03-20 17:55:48'),(112,1,10,'App\\Models\\Bundle','2024-03-20 17:58:30','2024-03-20 17:58:30'),(113,1,1,'App\\Models\\Voise','2024-05-29 04:45:53','2024-05-29 04:45:53'),(114,2,1,'App\\Models\\Voise','2024-05-29 04:45:53','2024-05-29 04:45:53'),(115,3,1,'App\\Models\\Voise','2024-05-29 04:45:53','2024-05-29 04:45:53'),(116,1,2,'App\\Models\\Voise','2024-05-29 04:45:53','2024-05-29 04:45:53'),(117,1,3,'App\\Models\\Voise','2024-05-29 04:45:53','2024-05-29 04:45:53'),(118,1,1,'App\\Models\\Custom','2024-05-29 04:46:43','2024-05-29 04:46:43'),(119,1,1,'App\\Models\\Custom','2024-05-29 04:46:43','2024-05-29 04:46:43'),(120,1,1,'App\\Models\\Custom','2024-05-29 04:46:43','2024-05-29 04:46:43'),(121,1,1,'App\\Models\\Custom','2024-05-29 04:46:43','2024-05-29 04:46:43'),(122,1,2,'App\\Models\\Bundle','2024-05-29 04:47:06','2024-05-29 04:47:06'),(123,1,3,'App\\Models\\Bundle','2024-05-29 04:47:06','2024-05-29 04:47:06'),(124,1,5,'App\\Models\\Bundle','2024-05-29 04:47:06','2024-05-29 04:47:06'),(125,2,7,'App\\Models\\Bundle','2024-05-29 04:47:06','2024-05-29 04:47:06'),(126,1,15,'App\\Models\\Bundle','2024-05-29 04:47:06','2024-05-29 04:47:06'),(127,1,16,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(128,1,17,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(129,1,18,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(130,1,19,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(131,1,20,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(132,1,21,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(133,1,22,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(134,1,23,'App\\Models\\Bundle','2024-05-29 04:47:07','2024-05-29 04:47:07'),(135,1,24,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(136,1,25,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(137,1,26,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(138,1,27,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(139,2,28,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(140,2,29,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(141,1,30,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(142,1,31,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(143,1,32,'App\\Models\\Bundle','2024-05-29 04:47:08','2024-05-29 04:47:08'),(144,1,33,'App\\Models\\Bundle','2024-05-29 04:47:09','2024-05-29 04:47:09'),(145,1,34,'App\\Models\\Bundle','2024-05-29 04:47:09','2024-05-29 04:47:09'),(146,1,35,'App\\Models\\Bundle','2024-05-29 04:47:09','2024-05-29 04:47:09'),(147,1,2,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(148,1,3,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(149,1,5,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(150,2,7,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(151,1,15,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(152,1,16,'App\\Models\\Bundle','2024-06-05 05:48:25','2024-06-05 05:48:25'),(153,1,17,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(154,1,18,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(155,1,19,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(156,1,20,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(157,1,21,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(158,1,22,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(159,1,23,'App\\Models\\Bundle','2024-06-05 05:48:26','2024-06-05 05:48:26'),(160,1,24,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(161,1,25,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(162,1,26,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(163,1,27,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(164,2,28,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(165,2,29,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(166,1,30,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(167,1,31,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(168,1,32,'App\\Models\\Bundle','2024-06-05 05:48:27','2024-06-05 05:48:27'),(169,1,33,'App\\Models\\Bundle','2024-06-05 05:48:28','2024-06-05 05:48:28'),(170,1,34,'App\\Models\\Bundle','2024-06-05 05:48:28','2024-06-05 05:48:28'),(171,1,35,'App\\Models\\Bundle','2024-06-05 05:48:28','2024-06-05 05:48:28'),(172,1,2,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(173,1,3,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(174,1,5,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(175,2,7,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(176,1,15,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(177,1,16,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(178,1,17,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(179,1,18,'App\\Models\\Bundle','2024-06-05 10:18:02','2024-06-05 10:18:02'),(180,1,19,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(181,1,20,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(182,1,21,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(183,1,22,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(184,1,23,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(185,1,24,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(186,1,25,'App\\Models\\Bundle','2024-06-05 10:18:03','2024-06-05 10:18:03'),(187,1,26,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(188,1,27,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(189,2,28,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(190,2,29,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(191,1,30,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(192,1,31,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(193,1,32,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(194,1,33,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(195,1,34,'App\\Models\\Bundle','2024-06-05 10:18:04','2024-06-05 10:18:04'),(196,1,35,'App\\Models\\Bundle','2024-06-05 10:18:05','2024-06-05 10:18:05'),(197,1,18,'App\\Models\\Internet','2024-10-08 19:00:17','2024-10-08 19:00:17'),(198,2,40,'App\\Models\\Internet','2024-11-06 14:01:25','2024-11-06 14:01:25'),(199,3,40,'App\\Models\\Internet','2024-11-06 14:01:25','2024-11-06 14:01:25'),(200,2,36,'App\\Models\\Internet','2024-11-06 14:09:39','2024-11-06 14:09:39'),(201,3,36,'App\\Models\\Internet','2024-11-06 14:09:39','2024-11-06 14:09:39'),(202,2,29,'App\\Models\\Internet','2024-11-06 14:09:59','2024-11-06 14:09:59'),(203,3,29,'App\\Models\\Internet','2024-11-06 14:09:59','2024-11-06 14:09:59'),(204,1,36,'App\\Models\\Bundle','2024-11-11 11:36:16','2024-11-11 11:36:16'),(205,1,38,'App\\Models\\Bundle','2024-12-03 11:48:27','2024-12-03 11:48:27');
/*!40000 ALTER TABLE `plan_type_billings` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `plan_type_billings` with 200 row(s)
--

--
-- Table structure for table `points`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `points` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `is_pole` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `points_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `points_created_by_foreign` (`created_by`),
  KEY `points_updated_by_foreign` (`updated_by`),
  CONSTRAINT `points_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `points_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `points_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `points`
--

LOCK TABLES `points` WRITE;
/*!40000 ALTER TABLE `points` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `points` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `points` with 0 row(s)
--

--
-- Table structure for table `point_accessories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `point_accessories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `point_id` bigint(20) unsigned NOT NULL,
  `name` enum('raqueta','omega','loop','cambios de altura') COLLATE utf8mb4_unicode_ci NOT NULL,
  `lenght` decimal(8,2) NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `point_accessories_point_id_foreign` (`point_id`),
  KEY `point_accessories_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `point_accessories_created_by_foreign` (`created_by`),
  KEY `point_accessories_updated_by_foreign` (`updated_by`),
  CONSTRAINT `point_accessories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `point_accessories_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `point_accessories_point_id_foreign` FOREIGN KEY (`point_id`) REFERENCES `points` (`id`),
  CONSTRAINT `point_accessories_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `point_accessories`
--

LOCK TABLES `point_accessories` WRITE;
/*!40000 ALTER TABLE `point_accessories` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `point_accessories` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `point_accessories` with 0 row(s)
--

--
-- Table structure for table `poles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `height` decimal(8,2) NOT NULL,
  `type` enum('Concreto','Madera','Metalicos','Luminarias','Semaforo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tension` enum('Alta','Baja','Media','Sin tención') COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `poles_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `poles_created_by_foreign` (`created_by`),
  KEY `poles_updated_by_foreign` (`updated_by`),
  CONSTRAINT `poles_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `poles_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `poles_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `poles`
--

LOCK TABLES `poles` WRITE;
/*!40000 ALTER TABLE `poles` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `poles` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `poles` with 0 row(s)
--

--
-- Table structure for table `pole_accessories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pole_accessories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pole_id` bigint(20) unsigned NOT NULL,
  `name` enum('Hebilla','Fleje','Remates','Tensores','Brazos') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `observations` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pole_accessories_pole_id_foreign` (`pole_id`),
  KEY `pole_accessories_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `pole_accessories_created_by_foreign` (`created_by`),
  KEY `pole_accessories_updated_by_foreign` (`updated_by`),
  CONSTRAINT `pole_accessories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `pole_accessories_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `pole_accessories_pole_id_foreign` FOREIGN KEY (`pole_id`) REFERENCES `poles` (`id`),
  CONSTRAINT `pole_accessories_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pole_accessories`
--

LOCK TABLES `pole_accessories` WRITE;
/*!40000 ALTER TABLE `pole_accessories` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `pole_accessories` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `pole_accessories` with 0 row(s)
--

--
-- Table structure for table `ports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` int(11) DEFAULT NULL,
  `type` enum('gibic C+','gibic C++','SFP','SFP+','ethernet','normal','entrada','fibra','jumper','fusión','continuo','box','splitter_out','splitter_in') COLLATE utf8mb4_unicode_ci NOT NULL,
  `portable_id` bigint(20) unsigned NOT NULL,
  `portable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ports_created_by_foreign` (`created_by`),
  KEY `ports_updated_by_foreign` (`updated_by`),
  CONSTRAINT `ports_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `ports_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ports`
--

LOCK TABLES `ports` WRITE;
/*!40000 ALTER TABLE `ports` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `ports` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `ports` with 0 row(s)
--

--
-- Table structure for table `positions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `positions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `point` point NOT NULL,
  `positionable_id` bigint(20) unsigned NOT NULL,
  `positionable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `positions_created_by_foreign` (`created_by`),
  KEY `positions_updated_by_foreign` (`updated_by`),
  CONSTRAINT `positions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `positions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `positions`
--

LOCK TABLES `positions` WRITE;
/*!40000 ALTER TABLE `positions` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `positions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `positions` with 0 row(s)
--

--
-- Table structure for table `projects`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_lead` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workflow` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `projects` VALUES (2,'INSTALACIONES','REALIZAR INSTALACION EN LOS DOMICILIOS DE LOS CLIENTES',NULL,'1',NULL,NULL,NULL,'2024-08-06 12:27:37','2024-08-06 12:27:37'),(3,'ORDEN DE SERVICIO','REALIZAR ACTIVIDADES PARA LA ATENCION A LOS CLIENTES',NULL,'1',NULL,NULL,NULL,'2024-08-08 14:19:40','2024-08-08 14:19:40'),(4,'CAMBIO DE DOMICILIO','REALIZAR LA INSTALACION EN UN NUEVO DOMICILIO Y ACTIVAR',NULL,'1',NULL,NULL,NULL,'2024-08-08 14:20:23','2024-08-08 14:20:23'),(5,'REPARACION','REPARAR SERVICIO YA SEA POR ROTURA DE FIBRA O DESCOMPOSTURA DEL EQUIPO',NULL,'1',NULL,NULL,NULL,'2024-08-08 14:20:58','2024-08-08 14:20:58'),(6,'TENDIDO','REALIZAR LA INSTALACION DE RED NUEVA EN LOS DIFERENTES BARRIOS O MUNICIPIOS CON COBERTURA',NULL,'1',NULL,NULL,NULL,'2024-08-08 14:21:31','2024-08-08 14:21:31'),(7,'DEPUERACION','ELIMINAR CABLES REVENTADOS Y / O CLIENTES QUE YA NO ESTAN ACTIVOS PARA MEJORAR LA IMAGEN URBANA',NULL,'1',NULL,NULL,NULL,'2024-08-08 14:22:30','2024-08-08 14:22:30'),(8,'Software de Gestion',NULL,NULL,'1',NULL,NULL,NULL,'2024-08-27 14:42:41','2024-09-19 10:09:12'),(9,'Planta Externa Garantias','Realizar Garantia de:\nCorte de servicio\nDaño a Caja\nCables tirados',NULL,'1',NULL,NULL,NULL,'2024-09-19 10:08:56','2024-09-19 10:08:56'),(10,'Habilitación de caja Zona:       Caja','Nomenclatura: D1 Zona.         CAJA:\nCoordenadas:\nBUFFER:\nHILO:\nTIPO DE CAJA:\nSPLITTER:\nPotencia:',NULL,'1',NULL,NULL,NULL,'2024-11-01 15:03:43','2024-11-01 15:03:43');
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `projects` with 9 row(s)
--

--
-- Table structure for table `project_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_types`
--

LOCK TABLES `project_types` WRITE;
/*!40000 ALTER TABLE `project_types` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `project_types` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `project_types` with 0 row(s)
--

--
-- Table structure for table `prospects`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prospects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `colony` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipality` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cp` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_prospect` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_seller` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prospects_id_seller_foreign` (`id_seller`),
  CONSTRAINT `prospects_id_seller_foreign` FOREIGN KEY (`id_seller`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prospects`
--

LOCK TABLES `prospects` WRITE;
/*!40000 ALTER TABLE `prospects` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `prospects` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `prospects` with 0 row(s)
--

--
-- Table structure for table `quote_crms`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quote_crms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint(20) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime NOT NULL,
  `total` double(8,2) NOT NULL,
  `valid_till` datetime NOT NULL,
  `lead_id` bigint(20) NOT NULL,
  `last_update` datetime NOT NULL,
  `date_of_decision` datetime NOT NULL,
  `invoice_id` bigint(20) NOT NULL,
  `request_id` bigint(20) NOT NULL,
  `is_sent` tinyint(1) NOT NULL,
  `note` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `memo` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `customers_quote` bigint(20) NOT NULL,
  `connected_deal_id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quote_crms`
--

LOCK TABLES `quote_crms` WRITE;
/*!40000 ALTER TABLE `quote_crms` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `quote_crms` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `quote_crms` with 0 row(s)
--

--
-- Table structure for table `racks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `racks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` bigint(20) unsigned NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` bigint(20) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `racks_site_id_foreign` (`site_id`),
  KEY `racks_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `racks_created_by_foreign` (`created_by`),
  KEY `racks_updated_by_foreign` (`updated_by`),
  CONSTRAINT `racks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `racks_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `racks_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  CONSTRAINT `racks_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `racks`
--

LOCK TABLES `racks` WRITE;
/*!40000 ALTER TABLE `racks` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `racks` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `racks` with 0 row(s)
--

--
-- Table structure for table `ranges_of_sales_sectors`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ranges_of_sales_sectors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sector` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `range` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_of_prospects` int(11) NOT NULL,
  `number_of_sales` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ranges_of_sales_sectors_sector_range_unique` (`sector`,`range`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ranges_of_sales_sectors`
--

LOCK TABLES `ranges_of_sales_sectors` WRITE;
/*!40000 ALTER TABLE `ranges_of_sales_sectors` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `ranges_of_sales_sectors` VALUES (1,'A','Cobre',0,0,NULL,NULL),(2,'A','Bronce',0,0,NULL,NULL),(3,'A','Plata',0,0,NULL,NULL),(4,'A','Oro',0,0,NULL,NULL),(5,'A','Diamante',0,0,NULL,NULL),(6,'B','Cobre',0,0,NULL,NULL),(7,'B','Bronce',0,0,NULL,NULL),(8,'B','Plata',0,0,NULL,NULL),(9,'B','Oro',0,0,NULL,NULL),(10,'B','Diamante',0,0,NULL,NULL),(11,'C','Cobre',0,0,NULL,NULL),(12,'C','Bronce',0,0,NULL,NULL),(13,'C','Plata',0,0,NULL,NULL),(14,'C','Oro',0,0,NULL,NULL),(15,'C','Diamante',0,0,NULL,NULL);
/*!40000 ALTER TABLE `ranges_of_sales_sectors` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `ranges_of_sales_sectors` with 15 row(s)
--

--
-- Table structure for table `roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `roles` VALUES (1,'super-administrator','web','2024-02-09 17:43:52','2024-02-09 17:43:52'),(3,'Super Administrador','web','2024-02-10 12:43:17','2024-02-10 12:43:17'),(4,'Administrador','web','2024-02-10 12:43:26','2024-02-10 12:43:26'),(5,'Mostrador','web','2024-02-10 12:43:34','2024-02-10 12:43:34'),(6,'Vendedor','web','2024-02-10 12:43:50','2024-02-10 12:43:50'),(7,'TECNICO','web','2024-02-10 12:43:59','2024-02-10 12:43:59'),(8,'Almacen','web','2024-02-10 12:44:14','2024-02-10 12:44:14'),(9,'client','web','2024-03-15 06:58:06','2024-03-15 06:58:06'),(10,'DESARROLLADOR','web','2024-09-18 13:28:25','2024-09-18 13:28:25'),(11,'Socio','web','2024-09-27 11:24:38','2024-09-27 11:32:03');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `roles` with 10 row(s)
--

--
-- Table structure for table `role_has_permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `role_has_permissions` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1),(14,1),(15,1),(16,1),(17,1),(18,1),(19,1),(20,1),(21,1),(22,1),(23,1),(24,1),(25,1),(26,1),(27,1),(28,1),(29,1),(30,1),(31,1),(32,1),(33,1),(34,1),(35,1),(36,1),(37,1),(38,1),(39,1),(40,1),(41,1),(42,1),(43,1),(44,1),(45,1),(46,1),(47,1),(48,1),(49,1),(50,1),(51,1),(52,1),(53,1),(54,1),(55,1),(56,1),(57,1),(58,1),(59,1),(60,1),(61,1),(62,1),(63,1),(64,1),(65,1),(66,1),(67,1),(68,1),(69,1),(70,1),(71,1),(72,1),(73,1),(74,1),(75,1),(76,1),(77,1),(78,1),(79,1),(80,1),(81,1),(82,1),(83,1),(84,1),(85,1),(86,1),(87,1),(88,1),(89,1),(90,1),(91,1),(92,1),(93,1),(94,1),(95,1),(96,1),(97,1),(98,1),(99,1),(100,1),(101,1),(102,1),(103,1),(104,1),(105,1),(106,1),(107,1),(108,1),(109,1),(110,1),(111,1),(112,1),(113,1),(114,1),(115,1),(116,1),(117,1),(118,1),(119,1),(120,1),(121,1),(122,1),(123,1),(124,1),(125,1),(126,1),(127,1),(128,1),(129,1),(130,1),(131,1),(132,1),(133,1),(134,1),(135,1),(136,1),(137,1),(138,1),(139,1),(140,1),(141,1),(142,1),(143,1),(144,1),(145,1),(146,1),(147,1),(148,1),(149,1),(150,1),(151,1),(152,1),(153,1),(154,1),(155,1),(156,1),(157,1),(158,1),(159,1),(160,1),(161,1),(162,1),(163,1),(164,1),(165,1),(166,1),(167,1),(168,1),(169,1),(170,1),(171,1),(172,1),(173,1),(174,1),(175,1),(176,1),(177,1),(178,1),(179,1),(180,1),(181,1),(182,1),(183,1),(184,1),(185,1),(186,1),(187,1),(188,1),(189,1),(190,1),(191,1),(192,1),(193,1),(194,1),(195,1),(196,1),(197,1),(198,1),(199,1),(200,1),(201,1),(202,1),(203,1),(204,1),(205,1),(206,1),(207,1),(208,1),(209,1),(210,1),(211,1),(212,1),(213,1),(214,1),(215,1),(216,1),(217,1),(218,1),(219,1),(220,1),(221,1),(222,1),(223,1),(224,1),(225,1),(226,1),(227,1),(228,1),(229,1),(230,1),(231,1),(232,1),(233,1),(234,1),(235,1),(236,1),(237,1),(238,1),(239,1),(240,1),(241,1),(242,1),(243,1),(244,1),(245,1),(246,1),(247,1),(248,1),(249,1),(250,1),(251,1),(252,1),(253,1),(254,1),(255,1),(256,1),(257,1),(258,1),(259,1),(260,1),(261,1),(262,1),(263,1),(264,1),(265,1),(266,1),(267,1),(268,1),(269,1),(270,1),(271,1),(272,1),(273,1),(274,1),(275,1),(276,1),(277,1),(278,1),(279,1),(280,1),(1,3),(2,3),(3,3),(4,3),(5,3),(6,3),(7,3),(8,3),(9,3),(10,3),(11,3),(12,3),(13,3),(14,3),(15,3),(16,3),(17,3),(18,3),(19,3),(20,3),(21,3),(22,3),(23,3),(24,3),(25,3),(26,3),(27,3),(28,3),(29,3),(30,3),(31,3),(32,3),(33,3),(34,3),(35,3),(36,3),(37,3),(38,3),(39,3),(40,3),(41,3),(42,3),(43,3),(44,3),(45,3),(46,3),(47,3),(48,3),(49,3),(50,3),(51,3),(52,3),(53,3),(54,3),(55,3),(56,3),(57,3),(58,3),(59,3),(60,3),(61,3),(62,3),(63,3),(64,3),(65,3),(66,3),(67,3),(68,3),(69,3),(70,3),(71,3),(72,3),(73,3),(74,3),(75,3),(76,3),(77,3),(78,3),(79,3),(80,3),(81,3),(82,3),(83,3),(84,3),(85,3),(86,3),(87,3),(88,3),(89,3),(90,3),(91,3),(92,3),(93,3),(94,3),(95,3),(96,3),(97,3),(98,3),(99,3),(100,3),(101,3),(102,3),(103,3),(104,3),(105,3),(106,3),(107,3),(108,3),(109,3),(110,3),(111,3),(112,3),(113,3),(114,3),(115,3),(116,3),(117,3),(118,3),(119,3),(120,3),(121,3),(122,3),(123,3),(124,3),(125,3),(126,3),(127,3),(128,3),(129,3),(130,3),(131,3),(132,3),(133,3),(134,3),(135,3),(136,3),(137,3),(138,3),(139,3),(140,3),(141,3),(142,3),(143,3),(144,3),(145,3),(146,3),(147,3),(148,3),(149,3),(150,3),(151,3),(152,3),(153,3),(154,3),(155,3),(156,3),(157,3),(158,3),(159,3),(160,3),(161,3),(162,3),(163,3),(164,3),(165,3),(166,3),(167,3),(168,3),(169,3),(170,3),(171,3),(172,3),(173,3),(174,3),(175,3),(176,3),(177,3),(178,3),(179,3),(180,3),(181,3),(182,3),(183,3),(184,3),(185,3),(186,3),(187,3),(188,3),(189,3),(190,3),(191,3),(192,3),(193,3),(194,3),(195,3),(196,3),(197,3),(198,3),(199,3),(245,3),(1,4),(2,4),(3,4),(4,4),(5,4),(6,4),(7,4),(8,4),(9,4),(10,4),(11,4),(12,4),(13,4),(14,4),(15,4),(16,4),(17,4),(18,4),(19,4),(20,4),(21,4),(22,4),(23,4),(24,4),(25,4),(28,4),(29,4),(30,4),(31,4),(32,4),(33,4),(37,4),(38,4),(39,4),(40,4),(69,4),(70,4),(71,4),(72,4),(73,4),(74,4),(75,4),(76,4),(77,4),(78,4),(79,4),(80,4),(81,4),(82,4),(83,4),(84,4),(85,4),(86,4),(87,4),(88,4),(89,4),(90,4),(91,4),(97,4),(98,4),(99,4),(100,4),(101,4),(102,4),(103,4),(104,4),(105,4),(106,4),(107,4),(108,4),(109,4),(110,4),(1,5),(6,5),(11,5),(16,5),(21,5),(22,5),(23,5),(26,5),(27,5),(28,5),(29,5),(30,5),(33,5),(34,5),(35,5),(36,5),(37,5),(39,5),(40,5),(53,5),(54,5),(55,5),(57,5),(58,5),(59,5),(60,5),(62,5),(63,5),(64,5),(65,5),(67,5),(68,5),(77,5),(81,5),(170,5),(171,5),(172,5),(173,5),(174,5),(175,5),(176,5),(177,5),(179,5),(180,5),(181,5),(182,5),(184,5),(185,5),(186,5),(187,5),(189,5),(190,5),(191,5),(192,5),(196,5),(197,5),(198,5),(201,5),(203,5),(206,5),(208,5),(209,5),(210,5),(211,5),(213,5),(214,5),(232,5),(239,5),(240,5),(241,5),(245,5),(246,5),(247,5),(249,5),(250,5),(251,5),(252,5),(253,5),(254,5),(255,5),(1,6),(6,6),(11,6),(16,6),(21,6),(22,6),(23,6),(26,6),(27,6),(28,6),(29,6),(30,6),(33,6),(34,6),(35,6),(36,6),(37,6),(39,6),(40,6),(53,6),(54,6),(55,6),(57,6),(58,6),(59,6),(60,6),(62,6),(63,6),(64,6),(65,6),(67,6),(68,6),(77,6),(81,6),(156,6),(170,6),(171,6),(172,6),(173,6),(174,6),(175,6),(176,6),(177,6),(179,6),(180,6),(181,6),(182,6),(184,6),(185,6),(186,6),(187,6),(189,6),(190,6),(191,6),(192,6),(196,6),(197,6),(198,6),(201,6),(202,6),(203,6),(204,6),(205,6),(206,6),(207,6),(208,6),(209,6),(210,6),(211,6),(213,6),(214,6),(232,6),(239,6),(240,6),(241,6),(245,6),(246,6),(247,6),(249,6),(252,6),(254,6),(262,6),(263,6),(264,6),(265,6),(1,7),(6,7),(11,7),(16,7),(21,7),(22,7),(23,7),(26,7),(27,7),(33,7),(37,7),(39,7),(40,7),(53,7),(54,7),(55,7),(58,7),(59,7),(60,7),(63,7),(68,7),(81,7),(156,7),(170,7),(171,7),(172,7),(173,7),(174,7),(175,7),(176,7),(177,7),(201,7),(202,7),(203,7),(204,7),(205,7),(206,7),(207,7),(208,7),(209,7),(210,7),(211,7),(213,7),(232,7),(239,7),(240,7),(241,7),(247,7),(249,7),(252,7),(254,7),(21,8),(22,8),(23,8),(26,8),(27,8),(37,8),(38,8),(39,8),(40,8),(156,8),(170,8),(171,8),(172,8),(173,8),(174,8),(175,8),(201,8),(202,8),(203,8),(204,8),(205,8),(206,8),(207,8),(232,8),(239,8),(240,8),(241,8),(247,8),(249,8),(252,8),(254,8),(156,9),(202,9),(1,10),(2,10),(3,10),(4,10),(5,10),(6,10),(7,10),(8,10),(9,10),(10,10),(11,10),(12,10),(13,10),(14,10),(15,10),(16,10),(17,10),(18,10),(19,10),(20,10),(21,10),(22,10),(23,10),(24,10),(25,10),(26,10),(27,10),(28,10),(29,10),(30,10),(31,10),(32,10),(33,10),(34,10),(35,10),(36,10),(37,10),(38,10),(39,10),(40,10),(53,10),(54,10),(55,10),(56,10),(57,10),(58,10),(59,10),(60,10),(61,10),(62,10),(63,10),(64,10),(65,10),(66,10),(67,10),(68,10),(69,10),(70,10),(71,10),(72,10),(73,10),(74,10),(75,10),(76,10),(77,10),(78,10),(79,10),(80,10),(81,10),(82,10),(83,10),(84,10),(85,10),(86,10),(87,10),(88,10),(89,10),(90,10),(91,10),(92,10),(93,10),(94,10),(95,10),(96,10),(97,10),(98,10),(99,10),(100,10),(101,10),(102,10),(103,10),(104,10),(105,10),(106,10),(107,10),(108,10),(109,10),(110,10),(131,10),(132,10),(133,10),(134,10),(135,10),(136,10),(137,10),(138,10),(139,10),(140,10),(141,10),(142,10),(143,10),(144,10),(145,10),(146,10),(147,10),(148,10),(149,10),(150,10),(151,10),(152,10),(153,10),(154,10),(155,10),(156,10),(170,10),(171,10),(172,10),(173,10),(174,10),(175,10),(176,10),(177,10),(179,10),(180,10),(181,10),(182,10),(184,10),(185,10),(186,10),(187,10),(188,10),(189,10),(190,10),(191,10),(192,10),(193,10),(196,10),(197,10),(198,10),(199,10),(201,10),(202,10),(203,10),(204,10),(205,10),(206,10),(207,10),(208,10),(209,10),(210,10),(211,10),(212,10),(213,10),(214,10),(215,10),(216,10),(217,10),(218,10),(219,10),(220,10),(221,10),(222,10),(224,10),(225,10),(226,10),(227,10),(232,10),(239,10),(240,10),(241,10),(242,10),(244,10),(245,10),(246,10),(247,10),(248,10),(249,10),(250,10),(251,10),(252,10),(253,10),(254,10),(255,10),(262,10),(263,10),(264,10),(265,10),(267,10),(268,10),(269,10),(270,10),(271,10),(272,10),(273,10),(274,10),(275,10),(276,10),(277,10),(278,10),(279,10),(280,10),(1,11),(6,11),(11,11),(16,11),(21,11),(22,11),(23,11),(26,11),(27,11),(28,11),(33,11),(34,11),(35,11),(36,11),(37,11),(39,11),(40,11),(53,11),(54,11),(55,11),(58,11),(59,11),(60,11),(63,11),(64,11),(65,11),(68,11),(156,11),(170,11),(171,11),(172,11),(173,11),(174,11),(177,11),(179,11),(180,11),(184,11),(185,11),(189,11),(190,11),(201,11),(202,11),(203,11),(204,11),(205,11),(206,11),(207,11),(209,11),(213,11),(232,11),(239,11),(240,11),(241,11),(245,11),(247,11),(249,11),(252,11),(254,11),(262,11),(263,11),(264,11),(265,11);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `role_has_permissions` with 1074 row(s)
--

--
-- Table structure for table `routers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `routers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_of_nas` enum('Mikrotik','Cisco','Ubiquiti') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` bigint(20) DEFAULT NULL,
  `physical_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_host` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nas_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secret_radius` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pool` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authorization_accounting` enum('PPP(Secrets)/API Acounting','Hostpot(Users)/API accounting','Hostopt(Radius)/Radius') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `routers_location_id_index` (`location_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `routers`
--

LOCK TABLES `routers` WRITE;
/*!40000 ALTER TABLE `routers` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `routers` VALUES (2,'ADMIN2','Mikrotik','1036',NULL,'AV LA PURISIMA MZ 3 LT 54 CASA A','149.14.76.98',NULL,NULL,NULL,'PPP(Secrets)/API Acounting','API OK','2024-02-10 10:50:18','2024-10-07 11:38:00'),(3,'Club Meganet','Mikrotik','1036',NULL,'Santa Ines','149.14.76.98',NULL,NULL,NULL,'Hostpot(Users)/API accounting','API (con errores)','2024-02-10 12:16:32','2024-08-13 08:48:00');
/*!40000 ALTER TABLE `routers` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `routers` with 2 row(s)
--

--
-- Table structure for table `setting_imports`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setting_imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `setting_imports`
--

LOCK TABLES `setting_imports` WRITE;
/*!40000 ALTER TABLE `setting_imports` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `setting_imports` VALUES (1,'1','settingImport/1/document_import/6657073e93ce8_example-internets-ready.xlsx','1','2024-05-29 04:45:18','2024-05-29 04:45:18'),(2,'2','settingImport/1/document_import/66570761ef2d4_example-voises-ready.xlsx','1','2024-05-29 04:45:53','2024-05-29 04:45:53'),(3,'3','settingImport/1/document_import/66570793962af_example-customs.xlsx','1','2024-05-29 04:46:43','2024-05-29 04:46:43'),(4,'4','settingImport/1/document_import/665707ad51207_example-bundles.xlsx','1','2024-05-29 04:47:09','2024-05-29 04:47:09'),(5,'4','settingImport/1/document_import/6660508c5d40e_example-bundles.xlsx','1','2024-06-05 05:48:28','2024-06-05 05:48:28'),(6,'4','settingImport/1/document_import/66608fbd1cbdc_example-bundles.xlsx','1','2024-06-05 10:18:05','2024-06-05 10:18:05');
/*!40000 ALTER TABLE `setting_imports` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `setting_imports` with 6 row(s)
--

--
-- Table structure for table `setting_table`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setting_table` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `table_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `columns` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `setting_table_user_id_foreign` (`user_id`),
  CONSTRAINT `setting_table_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `setting_table`
--

LOCK TABLES `setting_table` WRITE;
/*!40000 ALTER TABLE `setting_table` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `setting_table` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `setting_table` with 0 row(s)
--

--
-- Table structure for table `setting_tables`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setting_tables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `table_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `columns` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `setting_tables_user_id_foreign` (`user_id`),
  CONSTRAINT `setting_tables_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `setting_tables`
--

LOCK TABLES `setting_tables` WRITE;
/*!40000 ALTER TABLE `setting_tables` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `setting_tables` VALUES (1,1,'vendedores','\"[{\\\"name\\\":\\\"seller_id\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"type\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"father_last_name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"mother_last_name\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"address\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"city_municipality\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"state_country\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"phone\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"rfc\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"status_seller\\\",\\\"visible\\\":true}]\"','2024-06-26 18:34:18','2024-06-26 18:35:11'),(3,1,'prospectos','\"[{\\\"name\\\":\\\"id\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"father_last_name\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"mother_last_name\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"phone\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"email\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"phone\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"street\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"zip\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"crm_status\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"last_contacted\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"high_date\\\",\\\"visible\\\":true}]\"','2024-06-26 18:35:33','2024-06-26 18:53:39'),(4,1,'ventas-vendedores','\"[{\\\"name\\\":\\\"id\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"client_name_with_fathers_names\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"user\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"phone\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"email\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"address\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"zip\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"estado\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"discharge_date\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"activation_date\\\",\\\"visible\\\":true}]\"','2024-06-26 18:36:00','2024-06-26 18:36:00'),(5,1,'administradores','\"[{\\\"name\\\":\\\"id\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"father_last_name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"mother_last_name\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"email\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"login_user\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"address\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"city_municipality\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"colony\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"state_country\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"code_postal\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"rfc\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"rol_name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"action\\\",\\\"visible\\\":true}]\"','2024-06-26 18:50:18','2024-10-23 09:18:45'),(6,3,'administradores','\"[{\\\"name\\\":\\\"id\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"father_last_name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"mother_last_name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"email\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"login_user\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"address\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"city_municipality\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"colony\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"state_country\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"code_postal\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"rfc\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"rol_name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"action\\\",\\\"visible\\\":false}]\"','2024-08-07 19:14:30','2024-08-07 19:14:30'),(7,1,'reglas-comisiones','\"[{\\\"name\\\":\\\"id\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"name\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"zone\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"amount\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"number_of_prospects\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"minimum_sales\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"fixed_sales_commission\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"commission_percentage\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"period\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"commission_percentage_additional\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"fixed_sales_commission_additional\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"total_bonus\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"number_sales_required\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"installation_cost\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"sellers_count\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"actions\\\",\\\"visible\\\":true}]\"','2024-08-15 15:22:26','2024-09-02 12:38:56'),(8,4,'ventas-vendedores','\"[{\\\"name\\\":\\\"No\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"client_id\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"client_name_with_fathers_names\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"user\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"phone\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"email\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"address\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"zip\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"estado\\\",\\\"visible\\\":true},{\\\"name\\\":\\\"discharge_date\\\",\\\"visible\\\":false},{\\\"name\\\":\\\"activation_date\\\",\\\"visible\\\":true}]\"','2024-08-27 11:33:28','2024-08-27 11:33:28');
/*!40000 ALTER TABLE `setting_tables` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `setting_tables` with 7 row(s)
--

--
-- Table structure for table `sites`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sites_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `sites_created_by_foreign` (`created_by`),
  KEY `sites_updated_by_foreign` (`updated_by`),
  CONSTRAINT `sites_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `sites_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `sites_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sites`
--

LOCK TABLES `sites` WRITE;
/*!40000 ALTER TABLE `sites` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `sites` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `sites` with 0 row(s)
--

--
-- Table structure for table `social_providers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_providers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `provider_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_providers`
--

LOCK TABLES `social_providers` WRITE;
/*!40000 ALTER TABLE `social_providers` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `social_providers` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `social_providers` with 0 row(s)
--

--
-- Table structure for table `splitters`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `splitters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` int(11) NOT NULL,
  `outputs` int(11) NOT NULL,
  `box_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `splitters_box_id_foreign` (`box_id`),
  KEY `splitters_created_by_foreign` (`created_by`),
  KEY `splitters_updated_by_foreign` (`updated_by`),
  CONSTRAINT `splitters_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `boxes` (`id`),
  CONSTRAINT `splitters_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `splitters_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `splitters`
--

LOCK TABLES `splitters` WRITE;
/*!40000 ALTER TABLE `splitters` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `splitters` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `splitters` with 0 row(s)
--

--
-- Table structure for table `system_map_credentials`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_map_credentials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `api_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_map_credentials`
--

LOCK TABLES `system_map_credentials` WRITE;
/*!40000 ALTER TABLE `system_map_credentials` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `system_map_credentials` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `system_map_credentials` with 0 row(s)
--

--
-- Table structure for table `system_users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_id` bigint(20) NOT NULL,
  `timeout` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_access` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_router_radius` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `send_name_in_mail` tinyint(1) NOT NULL,
  `cash_desk` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_users`
--

LOCK TABLES `system_users` WRITE;
/*!40000 ALTER TABLE `system_users` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `system_users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `system_users` with 0 row(s)
--

--
-- Table structure for table `tables`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_class` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `repository_class` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `column_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_column_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_position` tinyint(1) NOT NULL,
  `has_connection` tinyint(1) NOT NULL,
  `in_site` tinyint(1) NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tables_created_by_foreign` (`created_by`),
  KEY `tables_updated_by_foreign` (`updated_by`),
  CONSTRAINT `tables_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tables_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tables`
--

LOCK TABLES `tables` WRITE;
/*!40000 ALTER TABLE `tables` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `tables` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `tables` with 0 row(s)
--

--
-- Table structure for table `taxes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `taxes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tax` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `taxes`
--

LOCK TABLES `taxes` WRITE;
/*!40000 ALTER TABLE `taxes` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `taxes` VALUES (1,'80%','2024-02-09 17:43:45','2024-02-09 17:43:45'),(2,'80%','2024-02-09 17:43:46','2024-02-09 17:43:46'),(3,'80%','2024-02-09 17:43:46','2024-02-09 17:43:46');
/*!40000 ALTER TABLE `taxes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `taxes` with 3 row(s)
--

--
-- Table structure for table `ticket_threads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_threads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `edited_by` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `ticket_thread_id` bigint(20) unsigned DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `hidden` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_threads`
--

LOCK TABLES `ticket_threads` WRITE;
/*!40000 ALTER TABLE `ticket_threads` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `ticket_threads` VALUES (1,1,1,3,NULL,'el camion de la basura rompio el cable',0,'2024-04-03 09:39:13','2024-04-03 09:39:13'),(2,2,1,6,NULL,'FIBRA ROTA',0,'2024-04-06 12:50:34','2024-04-06 12:50:34'),(3,3,1,2,NULL,NULL,0,'2024-04-06 12:55:26','2024-04-06 12:55:26'),(4,4,1,1,NULL,'PASO UN CAMION Y ROMPIO LA FIBRA',0,'2024-04-12 09:53:15','2024-04-12 09:53:15'),(5,5,6,3405,NULL,NULL,0,'2024-06-14 10:59:56','2024-06-14 10:59:56'),(6,6,1,4948,NULL,'CAMBIO DE DOMICILIO, CALLE 7 DE MARZO S/N ENTRE JUAN RAMIREZ Y ALLENDE SAN MARTIN JALTENCO, PUERTA CAFE PEQUEÑA, CASA CASA 2 PISOS TEJEBAN, OBRA NEGRA, DESDE JUAN RAMIREZ 2 CASAS HACIA ALLENDE LADO IZQUIERDO',0,'2024-06-18 08:40:24','2024-06-18 08:40:24'),(7,7,1,6360,NULL,NULL,0,'2024-06-24 08:35:45','2024-06-24 08:35:45'),(8,8,4,965,NULL,'REPORTA QUE EN SU MODEM HAY UN FOCO EN ROJO\r\n- SE LE PIDIO QUE DESCONECTARA Y CONECTARA EL CABLE DE FIBRA PERO NO SE CORRIGIO EL PROBLEMA',0,'2024-07-15 12:46:29','2024-07-15 12:46:29'),(9,9,1,5019,NULL,'USUARIO ENVIO MENSAJE POR WHATSAPP PARA INFORMAR QUE NO CONTABA CON SERVICIO\r\n- SE REINICIO EL SECTOR 2 YA QUE NO PODIA INGRESAR A LA ANTENA DEL CLIENTE\r\n- AL INGRESAR A LA ANTENA SE REALIZO LA ACTUALIZACIÓN QUE SOLICITABA EL SISTEMA DE ANTENA\r\n-SE LE PIDIO AL CLIENTE QUE VERIFICARA QUE TA TUVIERA CONEXIÓN Y EFECTIVAMENTE LO VALIDO',0,'2024-08-19 20:41:51','2024-08-19 20:41:51'),(10,10,1,5864,NULL,'LLAMA PORQUE SU SERVICIO ESTABA INTERMITENTE Y LA LINEA TELEFONICA IGUAL\r\n- SE HIZO UNA RESINCRONIZACIÓN DEL MODEM, YA QUE NO SONABA EL TELEFONO PERO NO SE ESCUCHABA\r\n- QUEDO HABILITADO Y SE LE PIDIO AL SR ALBERTO QUE VERIFICARA EL SERVICIO',0,'2024-08-21 11:31:30','2024-08-21 11:31:30'),(11,11,1,661,NULL,'LA USUARIA COMENTA QUE SU SERVICIO A ESTADO FALLANDO\r\n-se hizo un reinicio al modem y se le pidio que checara el servicio\r\n-se le explico que el fin de semana hubo un incidente en esa zona t quedo resuelto por los tecnicos de planta externa',0,'2024-08-21 14:10:03','2024-08-21 14:10:03'),(12,12,1,5739,NULL,'SE MANDA A TECNICO DE PLANTA INTERNA',0,'2024-08-22 16:37:14','2024-08-22 16:37:14'),(13,13,4,6506,NULL,NULL,0,'2024-08-26 14:44:27','2024-08-26 14:44:27'),(14,14,4,1286,NULL,'DICE QUE NO TIENE SERVICIO',0,'2024-08-26 14:46:59','2024-08-26 14:46:59'),(15,15,1,1902,NULL,NULL,0,'2024-09-10 14:18:56','2024-09-10 14:18:56'),(16,16,4,993,NULL,'checar el switch de la antena ya que no tiene conexion el radio, ir antes de las 10:30 ya que salen a trabajar',0,'2024-09-19 13:19:18','2024-09-19 13:19:18'),(17,17,4,993,NULL,'checar el switch de la antena ya que no tiene conexion el radio, ir antes de las 10:30 ya que salen a trabajar',0,'2024-09-19 13:20:02','2024-09-19 13:20:02'),(18,18,4,6020,NULL,'PON ROJO LLEVA DOS DÍAS ASI',0,'2024-09-23 17:00:19','2024-09-23 17:00:19'),(19,19,4,1759,NULL,'NO CONTABA CON SERVICIO, SE CHECO EN ADDRESS LIST Y SE MARCO EN GRIS, SE LE PASO EL REPORTE AL INGENIERO CORRESPONDIENTE.\r\nLA USUARIA PIDE QUE SE LE CONDONEN LAS 2 HORAS QUE SE QUEDO SIN SERVICIO, SIN EMBARGO SE LE EXPLICO QUE CON FORME A LA LEY DEBEN DE TRANSCURRIR 72 HORAS CONTINUAS A PARTIR DEL MOMENTO DE SU REPORTE. \r\nDURANTE LA LLAMADA ESTUVO LOCALIZANDO A SU HIJA, PARA CHECAR SI TENIAN INTERNET PORQUE ELLA SE ENCUENTRA EN EL DOMICLIO, NOS PUSO EN ESPERA EN LO QUE LA LOCALIZABA. AMENAZO CON QUE SI NO TENIA SERVICIO CUANDO LLEGARA AL DOMICILIO O SI SU HIJA LA DECIA QUE NO TIENE SERVICIO ACUDIRA A NUESTRA SUCURSAL A DECIR DE COSAS, \"ME TENDRAN QUE ESTAR AGUANTANDO\" CITO TEXTUALMENTE. \r\nCOMENTA QUE DESDE LAS 2:19pm NO TIENEN SERVICIO.',0,'2024-09-27 15:20:44','2024-09-27 15:20:44'),(20,20,4,2847,NULL,'CHECAR LINEA TELEFONICA',0,'2024-10-04 11:06:31','2024-10-04 11:06:31'),(21,21,3,1759,NULL,'CLIENTE COMENTA NO CUENTA CON SERVICIO SE VERIFICA Y ESTA EN ADDRESS LIST',0,'2024-10-05 12:03:05','2024-10-05 12:03:05'),(22,22,1,1759,NULL,'CLIENTE REPORTA NO CONTAR CON SERVICIO SE VERIICA Y SE REINCICIA EQUIPO',0,'2024-10-05 12:05:02','2024-10-05 12:05:02'),(23,23,3,5281,NULL,'EL SERVICIO SE ENCUENTRA ACTIVO SIN ADEUDO Y CADA DIA SE VA AL ADDRESS LIST SE HABIA PUESTO EN GRIS Y YA NO SE HABIA TENIDO PROBLEMA, SE HICIERON LOS CAMBIOS EN MILROTIK Y VOLVIO A PRESENTAR ESTE DETALLE',0,'2024-10-08 13:40:21','2024-10-08 13:40:21'),(24,24,1,1789,NULL,'no tuvo servicio el dia domingo su pago se vence hasta el dia 23 de octubre por lo tanto no cuenta con ningun atraso, estaba en address list y se puso en gris para evitar suspencion',0,'2024-10-21 11:36:53','2024-10-21 11:36:53'),(25,25,3,3489,NULL,'CLIENTE NO CUENTA CON SERVICIO Y TIENE EL PON EN ROJO',0,'2024-10-22 09:14:24','2024-10-22 09:14:24'),(26,26,3,6477,NULL,'CLIENTE COMENTA QUE EL SERVICIO HA SIDO DEFICIENTE DESDE QUE LO CONTRATO YA QUE CONSTANTEMENTE SE PIERDE LA SEÑAL Y YA NO PUEDE REALIZAR SUS ACTIVIDADES, SE LE INDICA QUE CUALQUIER TIPO DE ANOMALIA SE TIENE QUE REPORTAR PARA QUE HAYA UN ANTECEDENTE DE ESTA SITUACION Y PODER BRINDARLE UNA SOLUCION, SE LE COMENTA QUE SE REVISARA SU SERVICIO Y SE HARA UNA LIMPIEZA DE EQUIPO ASI COMO UN REINICIO PARA VER SI CON ESTO SE RESTABLECE CON NORMALIDAD EL SERVICIO CUALQUIER SITUACION PUES ES NECESARIO VOLVER A NOTIFICAR. INDICA QUE SE ESPERARA A QUE SE VENZA EL PAGO DE SU SERVICIO PARA VER SI YA LE FUNCIONA Y VOLVER A REALIZAR PAGO O YA NO, DE IGUAL FORMA SE LE INDICA LA VIGENCIA DE SU CONTRATO QUE ES DE UN PLAZO DE 12 MESES.',0,'2024-10-28 14:59:36','2024-10-28 14:59:36'),(27,27,4,3610,NULL,'COMENTA QUE SU SERVICIO ESTUVO LENTO TODO EL MES Y QUE SOLO LE LLEGAN 25 MEGAS\r\nSE LE ECPLICO COMO DEBE DE REALIZAR SU TEST DE VELOCIDAD Y QUE CHECARA NUEVAMENTE SU TEST\r\nSE LE DIO NUMERO DE REPORTE 241183610',0,'2024-11-08 17:38:53','2024-11-08 17:38:53'),(28,28,4,6512,NULL,'LLEVA 4 DÍAS SIN SERVICIO, AYER LO REPORTO FUERON LOS TECNICOS A VERIFICAR EN LA CAJA Y NO HAY NINGUNA ANOMALIA, NO HABIA NADIE DENTRO DEL DOMICILIO QUE LOS PUEDA RESIBIR\r\nEL DÍA DE HOY VINO EL USUARIO PARA QUE SE AGENDE LA VISITA EL DÍA SABADO QUE EL SE ENCONTRARA EN EL DOMICILIO',0,'2024-11-20 19:48:53','2024-11-20 19:48:53'),(29,29,4,3995,NULL,'REPORTA QUE EL SERVICIO A ESTADO INTERMITENTE\r\nYA SE CAMBIO LA CONRASEÑA DE WIFI\r\nSE HIZO CAMBIO DE IP\r\nAL PARECER EL ERROR ESTA EN SU PANTALLA, SE LE PIDIO AL USUARIO QUE VERIFIQUE SUS EQUIPOS Y S9I LOS PUEDE RESETEAR MUCHO MEJOR\r\nSE CHECO POTENCIA Y ESTA TODO BIEN',0,'2024-11-23 18:46:32','2024-11-23 18:46:32'),(30,30,3,5238,NULL,'cliente no cuenta con servicio tiene el foco de pon  en rojo',0,'2024-11-25 13:30:43','2024-11-25 13:30:43'),(31,31,3,6571,NULL,'NO CUENTA CON SERVICIO SE VERIFICA Y MARCA SIN POTENCIA SE ENVIA VISITA TECNICA',0,'2024-11-28 18:52:05','2024-11-28 18:52:05');
/*!40000 ALTER TABLE `ticket_threads` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `ticket_threads` with 31 row(s)
--

--
-- Table structure for table `transactions_sellers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions_sellers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `method_of_payment` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `previous_balance` decimal(8,2) NOT NULL,
  `new_balance` decimal(8,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_sellers_method_of_payment_foreign` (`method_of_payment`),
  KEY `transactions_sellers_seller_id_foreign` (`seller_id`),
  CONSTRAINT `transactions_sellers_method_of_payment_foreign` FOREIGN KEY (`method_of_payment`) REFERENCES `method_of_payments` (`id`),
  CONSTRAINT `transactions_sellers_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions_sellers`
--

LOCK TABLES `transactions_sellers` WRITE;
/*!40000 ALTER TABLE `transactions_sellers` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `transactions_sellers` VALUES (1,'2024-09-07 00:00:00',1,4,'2024-09-07 14:33:53','2024-09-07 14:33:53',31080.00,-1932.00),(2,'2024-09-07 00:00:00',1,4,'2024-09-07 14:34:21','2024-09-07 14:34:21',-1932.00,0.00);
/*!40000 ALTER TABLE `transactions_sellers` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `transactions_sellers` with 2 row(s)
--

--
-- Table structure for table `transceivers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transceivers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('LS simple','LS dual','SC') COLLATE utf8mb4_unicode_ci NOT NULL,
  `card_id` bigint(20) unsigned NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transceivers_card_id_foreign` (`card_id`),
  KEY `transceivers_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `transceivers_created_by_foreign` (`created_by`),
  KEY `transceivers_updated_by_foreign` (`updated_by`),
  CONSTRAINT `transceivers_card_id_foreign` FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`),
  CONSTRAINT `transceivers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `transceivers_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `transceivers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transceivers`
--

LOCK TABLES `transceivers` WRITE;
/*!40000 ALTER TABLE `transceivers` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `transceivers` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `transceivers` with 0 row(s)
--

--
-- Table structure for table `trays`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trays` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` int(11) NOT NULL,
  `box_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trays_box_id_foreign` (`box_id`),
  KEY `trays_created_by_foreign` (`created_by`),
  KEY `trays_updated_by_foreign` (`updated_by`),
  CONSTRAINT `trays_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `boxes` (`id`),
  CONSTRAINT `trays_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `trays_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trays`
--

LOCK TABLES `trays` WRITE;
/*!40000 ALTER TABLE `trays` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `trays` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `trays` with 0 row(s)
--

--
-- Table structure for table `trenches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trenches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trenche_type_id` bigint(20) unsigned NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trenches_trenche_type_id_foreign` (`trenche_type_id`),
  KEY `trenches_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `trenches_created_by_foreign` (`created_by`),
  KEY `trenches_updated_by_foreign` (`updated_by`),
  CONSTRAINT `trenches_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `trenches_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `trenches_trenche_type_id_foreign` FOREIGN KEY (`trenche_type_id`) REFERENCES `trenche_types` (`id`),
  CONSTRAINT `trenches_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trenches`
--

LOCK TABLES `trenches` WRITE;
/*!40000 ALTER TABLE `trenches` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `trenches` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `trenches` with 0 row(s)
--

--
-- Table structure for table `trenche_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trenche_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `width` decimal(8,2) NOT NULL,
  `lenght` decimal(8,2) NOT NULL,
  `depth` decimal(8,2) NOT NULL,
  `brand_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trenche_types_brand_id_foreign` (`brand_id`),
  KEY `trenche_types_created_by_foreign` (`created_by`),
  KEY `trenche_types_updated_by_foreign` (`updated_by`),
  CONSTRAINT `trenche_types_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `trenche_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `trenche_types_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trenche_types`
--

LOCK TABLES `trenche_types` WRITE;
/*!40000 ALTER TABLE `trenche_types` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `trenche_types` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `trenche_types` with 0 row(s)
--

--
-- Table structure for table `tubes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tubes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tube_type_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tubes_tube_type_id_foreign` (`tube_type_id`),
  KEY `tubes_created_by_foreign` (`created_by`),
  KEY `tubes_updated_by_foreign` (`updated_by`),
  CONSTRAINT `tubes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tubes_tube_type_id_foreign` FOREIGN KEY (`tube_type_id`) REFERENCES `tube_types` (`id`),
  CONSTRAINT `tubes_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tubes`
--

LOCK TABLES `tubes` WRITE;
/*!40000 ALTER TABLE `tubes` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `tubes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `tubes` with 0 row(s)
--

--
-- Table structure for table `tube_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tube_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('No definido') COLLATE utf8mb4_unicode_ci NOT NULL,
  `diameter` decimal(8,2) NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tube_types_created_by_foreign` (`created_by`),
  KEY `tube_types_updated_by_foreign` (`updated_by`),
  CONSTRAINT `tube_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tube_types_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tube_types`
--

LOCK TABLES `tube_types` WRITE;
/*!40000 ALTER TABLE `tube_types` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `tube_types` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `tube_types` with 0 row(s)
--

--
-- Table structure for table `type_billings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `type_billings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `type_billings_id_index` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `type_billings`
--

LOCK TABLES `type_billings` WRITE;
/*!40000 ALTER TABLE `type_billings` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `type_billings` VALUES (1,'Pagos Recurrentes','2024-02-09 17:43:45','2024-02-09 17:43:45'),(2,'Prepagos (Diarios)','2024-02-09 17:43:45','2024-02-09 17:43:45'),(3,'Prepagos (Personalizados)','2024-02-09 17:43:45','2024-02-09 17:43:45');
/*!40000 ALTER TABLE `type_billings` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `type_billings` with 3 row(s)
--

--
-- Table structure for table `user_column_dt_expand`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_column_dt_expand` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `module_id` bigint(20) unsigned DEFAULT NULL,
  `column` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_column_dt_expand_user_id_foreign` (`user_id`),
  KEY `user_column_dt_expand_module_id_foreign` (`module_id`),
  CONSTRAINT `user_column_dt_expand_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_column_dt_expand_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_column_dt_expand`
--

LOCK TABLES `user_column_dt_expand` WRITE;
/*!40000 ALTER TABLE `user_column_dt_expand` DISABLE KEYS */;
SET autocommit=0;
/*!40000 ALTER TABLE `user_column_dt_expand` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `user_column_dt_expand` with 0 row(s)
--

--
-- Table structure for table `voises`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voises` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `update_description` tinyint(1) DEFAULT '0',
  `price` bigint(20) NOT NULL,
  `update_service` tinyint(1) DEFAULT '0',
  `type` enum('VoIP','Corregido','Móvil') COLLATE utf8mb4_unicode_ci NOT NULL,
  `partners` bigint(20) DEFAULT NULL,
  `tax_include` tinyint(1) NOT NULL,
  `tax` bigint(20) NOT NULL,
  `prepaid_period` enum('Mensual','Diario') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rates_to_change` bigint(20) DEFAULT NULL,
  `transaction_category` enum('Servicio','Descuento','Pago','Reembolso','Corrección') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_category_for_calls` enum('Servicio','Descuento','Pago','Reembolso','Corrección') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_category_for_messages` enum('Servicio','Descuento','Pago','Reembolso','Corrección') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_category_for_data` enum('Servicio','Descuento','Pago','Reembolso','Corrección') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `available_in_self_registration` tinyint(1) DEFAULT NULL,
  `bandwidth` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` enum('1','2','3','4','5','6') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_days` bigint(20) DEFAULT NULL,
  `cost_activation` double(8,2) NOT NULL DEFAULT '0.00',
  `cost_instalation` double(8,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voises`
--

LOCK TABLES `voises` WRITE;
/*!40000 ALTER TABLE `voises` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `voises` VALUES (1,'VoIP','160',NULL,160,NULL,'VoIP',NULL,1,0,'Mensual',NULL,'Servicio','Servicio','Servicio','Servicio',1,'2000','1',NULL,0.00,0.00,'2024-05-29 04:45:53','2024-05-29 04:45:53'),(2,'Telefonia_en_paquete','Telefonia_en_paquete',NULL,70,NULL,'VoIP',NULL,1,16,'Mensual',NULL,'Servicio','Servicio','Servicio','Servicio',0,'0','1',NULL,0.00,0.00,'2024-05-29 04:45:53','2024-05-29 04:45:53'),(3,'voz_paquete','Voz_Paquete',NULL,70,NULL,'VoIP',NULL,1,16,'Mensual',NULL,'Servicio','Servicio','Servicio','Servicio',0,'2','1',NULL,0.00,0.00,'2024-05-29 04:45:53','2024-05-29 04:45:53');
/*!40000 ALTER TABLE `voises` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `voises` with 3 row(s)
--

--
-- Table structure for table `zones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `district_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zones_district_id_foreign` (`district_id`),
  CONSTRAINT `zones_district_id_foreign` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zones`
--

LOCK TABLES `zones` WRITE;
/*!40000 ALTER TABLE `zones` DISABLE KEYS */;
SET autocommit=0;
INSERT INTO `zones` VALUES (1,'54',2,'2024-12-10 20:23:55','2024-12-10 20:23:55');
/*!40000 ALTER TABLE `zones` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;

-- Dumped table `zones` with 1 row(s)
--

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET AUTOCOMMIT=@OLD_AUTOCOMMIT */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on: Mon, 16 Dec 2024 16:42:43 -0600


-- mysqldump-php https://github.com/ifsnop/mysqldump-php
--
-- Host: 127.0.0.1	Database: mtest
-- ------------------------------------------------------
-- Server version 	5.7.35
-- Date: Mon, 16 Dec 2024 16:42:43 -0600

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40101 SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint(20) unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`),
  KEY `activity_log_client_id_index` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1570393 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `app_layout_configurations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_layout_configurations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_datatable_color` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tabs_json` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `balances`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `amount` double NOT NULL DEFAULT '0',
  `balanceable_id` bigint(20) NOT NULL,
  `balanceable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `balances_balanceable_id_index` (`balanceable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4863 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_addresses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) NOT NULL,
  `billing_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_street` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_zip_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_addresses_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `billing_configurations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_configurations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) NOT NULL,
  `type_billing_id` bigint(20) NOT NULL,
  `payment_method_id` bigint(20) DEFAULT NULL,
  `period` bigint(20) unsigned DEFAULT NULL,
  `minimum_balance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `send_financial_notification` tinyint(1) NOT NULL DEFAULT '0',
  `create_monthly_invoice` tinyint(1) NOT NULL DEFAULT '0',
  `billing_activated` tinyint(1) NOT NULL DEFAULT '0',
  `billing_date` int(11) DEFAULT NULL COMMENT 'Dia de facturacion',
  `billing_expiration` int(11) DEFAULT NULL COMMENT 'cantidad dias con servicio desde a partir del dia de facturacion',
  `grace_period` int(11) DEFAULT NULL COMMENT 'Cantidad de dias en los que se mantendra realizando facturacion del servicio aunque no haya saldo disponible',
  `membership_percentage` int(11) DEFAULT NULL,
  `create_invoice` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Create invoices (after Charge & Invoice)',
  `autopay_invoice` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Auto pay invoices from account balance',
  `seller` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_configurations_client_id_index` (`client_id`),
  KEY `billing_configurations_type_billing_id_index` (`type_billing_id`),
  KEY `billing_configurations_payment_method_id_index` (`payment_method_id`),
  KEY `billing_configurations_billing_date_index` (`billing_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4823 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `boxes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `boxes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nomenclature` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `box_type_id` bigint(20) unsigned NOT NULL,
  `map_proyect_id` bigint(20) unsigned NOT NULL,
  `point_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `boxes_box_type_id_foreign` (`box_type_id`),
  KEY `boxes_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `boxes_point_id_foreign` (`point_id`),
  KEY `boxes_created_by_foreign` (`created_by`),
  KEY `boxes_updated_by_foreign` (`updated_by`),
  CONSTRAINT `boxes_box_type_id_foreign` FOREIGN KEY (`box_type_id`) REFERENCES `box_types` (`id`),
  CONSTRAINT `boxes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `boxes_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `boxes_point_id_foreign` FOREIGN KEY (`point_id`) REFERENCES `points` (`id`),
  CONSTRAINT `boxes_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `box_zones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `box_zones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` bigint(20) unsigned NOT NULL,
  `client` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box_zones_zone_id_foreign` (`zone_id`),
  CONSTRAINT `box_zones_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `change_plan_internet_clients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `change_plan_internet_clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internet_id` bigint(20) NOT NULL,
  `tarifa_internet_id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `change_plan_voz_clients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `change_plan_voz_clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `voz_id` bigint(20) NOT NULL,
  `tarifa_voz_id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL COMMENT 'System User',
  `fecha_corte` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha de finalizacion de los servicios del cliente',
  `fecha_pago` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha de pago de los servicios del cliente',
  `fecha_fin_periodo_gracia` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_suspension` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active_promise_payment` tinyint(1) NOT NULL DEFAULT '0',
  `last_activity` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ultima vez que tuvo actividad el usuario en el mikrotik',
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned NOT NULL,
  `migrated_from_splynt` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clients_created_by_foreign` (`created_by`),
  KEY `clients_updated_by_foreign` (`updated_by`),
  KEY `clients_id_index` (`id`),
  KEY `clients_fecha_corte_index` (`fecha_corte`),
  CONSTRAINT `clients_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `clients_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6693 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_additional_information`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_additional_information` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) DEFAULT NULL,
  `connection_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'olt',
  `category` enum('Particular','Empresa') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modem_sn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gpon_ont` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `power_dbm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `box_nomenclator` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_film` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_film` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_wifi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reinstatement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installation_on_time` tinyint(1) DEFAULT NULL,
  `amount_technician_and_why` text COLLATE utf8mb4_unicode_ci COMMENT 'El tecnico le atendio con amabilidad y respeto si, no y porque',
  `doubt_signed_contract` tinyint(1) DEFAULT NULL,
  `technician_attencion` text COLLATE utf8mb4_unicode_ci COMMENT 'El tecnico le atendio con amabilidad y respeto si, no y porque',
  `last_time_online` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_additional_information_client_id_index` (`client_id`),
  KEY `idx_client_additional_information_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=4823 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_bundle_services`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_bundle_services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `bundle_id` bigint(20) unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` bigint(20) NOT NULL,
  `pay_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` tinyint(1) NOT NULL DEFAULT '0',
  `discount_percent` int(11) DEFAULT NULL COMMENT 'if discount is true',
  `start_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `end_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `discount_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `contract_start_date` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_end_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `automatic_renewal` tinyint(1) NOT NULL,
  `charged` tinyint(1) NOT NULL DEFAULT '0',
  `deployed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_bundle_services_bundle_id_foreign` (`bundle_id`),
  KEY `idx_client_bundle_services_client_id` (`client_id`),
  CONSTRAINT `client_bundle_services_bundle_id_foreign` FOREIGN KEY (`bundle_id`) REFERENCES `bundles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2893 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_custom_services`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_custom_services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `custom_id` bigint(20) unsigned DEFAULT NULL,
  `client_bundle_service_id` bigint(20) unsigned DEFAULT NULL,
  `service_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint(20) DEFAULT NULL,
  `unity` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` bigint(20) DEFAULT NULL,
  `pay_period` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finish_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` tinyint(1) NOT NULL DEFAULT '0',
  `discount_percent` int(11) DEFAULT NULL COMMENT 'if discount is true',
  `start_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `end_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `discount_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deferred_payment_in_month` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `charged` tinyint(1) NOT NULL DEFAULT '0',
  `deployed` tinyint(1) NOT NULL DEFAULT '0',
  `router_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv4_assignment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_ipv4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv4_pool` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv6` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delegated_ipv6` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mac` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bandwidth` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_activation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_instalation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hidden` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `internet_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_custom_services_custom_id_foreign` (`custom_id`),
  KEY `client_custom_services_client_bundle_service_id_foreign` (`client_bundle_service_id`),
  KEY `client_custom_services_internet_id_foreign` (`internet_id`),
  KEY `idx_client_custom_services_client_id` (`client_id`),
  CONSTRAINT `client_custom_services_client_bundle_service_id_foreign` FOREIGN KEY (`client_bundle_service_id`) REFERENCES `client_bundle_services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_custom_services_custom_id_foreign` FOREIGN KEY (`custom_id`) REFERENCES `customs` (`id`),
  CONSTRAINT `client_custom_services_internet_id_foreign` FOREIGN KEY (`internet_id`) REFERENCES `internets` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1596 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_grace_periods`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_grace_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2558 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_internet_services`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_internet_services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `internet_id` bigint(20) unsigned DEFAULT NULL,
  `client_bundle_service_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint(20) DEFAULT NULL,
  `unity` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` bigint(20) DEFAULT NULL,
  `pay_period` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finish_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` tinyint(1) NOT NULL DEFAULT '0',
  `discount_percent` int(11) DEFAULT NULL COMMENT 'if discount is true',
  `start_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `end_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `discount_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `estado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `router_id` bigint(20) unsigned DEFAULT NULL,
  `client_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv4_assignment` enum('IP Estatica','Pool IP') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if ipv4_assignment is IP Estatica',
  `additional_ipv4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if ipv4_assignment is IP Estatica',
  `ipv4_pool` int(11) DEFAULT NULL COMMENT 'if ipv4_assignment is Pool IP',
  `ipv6` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delegated_ipv6` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mac` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deferred_payment_in_month` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_activation` double(8,2) NOT NULL DEFAULT '0.00',
  `charged` tinyint(1) NOT NULL DEFAULT '0',
  `deployed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_internet_services_internet_id_foreign` (`internet_id`),
  KEY `client_internet_services_client_bundle_service_id_foreign` (`client_bundle_service_id`),
  KEY `client_internet_services_router_id_foreign` (`router_id`),
  KEY `client_internet_services_client_id_index` (`client_id`),
  KEY `client_internet_services_user_index` (`user`),
  CONSTRAINT `client_internet_services_client_bundle_service_id_foreign` FOREIGN KEY (`client_bundle_service_id`) REFERENCES `client_bundle_services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_internet_services_internet_id_foreign` FOREIGN KEY (`internet_id`) REFERENCES `internets` (`id`),
  CONSTRAINT `client_internet_services_router_id_foreign` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9227 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_invoices`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` bigint(20) NOT NULL COMMENT 'numero generardo del pago año+mes+numero',
  `total` double(8,2) NOT NULL COMMENT 'total a pagar',
  `payment_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha de pago',
  `estado` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pagar (del saldo de la cuenta)',
  `client_id` bigint(20) unsigned NOT NULL,
  `last_update` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ultima actualiacion de la factura',
  `pay_up` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha que asigna la configuracion del fin de la facturacion',
  `use_of_transactions` int(11) DEFAULT NULL COMMENT 'cantidad de transacciones que necesito para pagar la fafctura',
  `note` longtext COLLATE utf8mb4_unicode_ci,
  `memo` longtext COLLATE utf8mb4_unicode_ci,
  `payment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Numero consecutivo de pago',
  `is_sent` tinyint(1) NOT NULL DEFAULT '0',
  `delete_transactions` tinyint(1) NOT NULL DEFAULT '0',
  `added_by` bigint(20) unsigned NOT NULL COMMENT 'Quien generó la faactura',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_proforma` tinyint(1) NOT NULL DEFAULT '0',
  `document_date` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=123129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `add_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_logs_client_id_foreign` (`client_id`),
  CONSTRAINT `client_logs_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_main_information`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_main_information` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL COMMENT 'Usuario del sistema',
  `user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mikrotik User',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mikrotik User Password',
  `estado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Nuevo' COMMENT 'Nuevo => Nuevo(Todavía no conectado),\n                Activo => Activado, Inactivo => Inactivo(No puede utilizar los servicios),\n                Bloqueado => Bloqueado\n            ',
  `type_of_billing_id` bigint(20) DEFAULT NULL,
  `ift` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `father_last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nif_pasaport` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'RFC/CURP',
  `partner_id` bigint(20) DEFAULT NULL,
  `street` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `internal_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colony_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipality_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` bigint(20) unsigned DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` bigint(20) DEFAULT NULL,
  `geodata` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geo_data` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discharge_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha de alta',
  `activation_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha en la que se convirtio de crm a cliente',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `seller_id` bigint(20) DEFAULT NULL,
  `medium_id` bigint(20) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_main_information_client_id_index` (`client_id`),
  KEY `client_main_information_location_id_index` (`location_id`),
  KEY `client_main_information_partner_id_index` (`partner_id`),
  KEY `client_main_information_state_id_index` (`state_id`),
  KEY `client_main_information_municipality_id_index` (`municipality_id`),
  KEY `client_main_information_colony_id_index` (`colony_id`),
  KEY `client_main_information_name_index` (`name`),
  KEY `client_main_information_estado_index` (`estado`),
  KEY `client_main_information_father_last_name_index` (`father_last_name`),
  KEY `client_main_information_mother_last_name_index` (`mother_last_name`),
  KEY `client_main_information_phone_index` (`phone`),
  KEY `client_main_information_phone2_index` (`phone2`),
  KEY `client_main_information_type_of_billing_id_index` (`type_of_billing_id`),
  KEY `client_main_information_email_index` (`email`),
  KEY `client_main_information_street_index` (`street`),
  KEY `client_main_information_zip_index` (`zip`),
  KEY `client_main_information_external_number_index` (`external_number`),
  KEY `client_main_information_internal_number_index` (`internal_number`),
  KEY `client_main_information_seller_id_index` (`seller_id`),
  KEY `idx_client_main_information_address` (`address`)
) ENGINE=InnoDB AUTO_INCREMENT=4832 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_payment_promises`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_payment_promises` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_court_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_amount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_amount_is_pay` tinyint(1) NOT NULL DEFAULT '0',
  `second_court_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_amount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_amount_is_pay` tinyint(1) NOT NULL DEFAULT '0',
  `third_court_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `third_amount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `third_amount_is_pay` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_payment_services`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_payment_services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_in_time` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Campo que te dice si el pago se hizo en tiempo',
  `service_paymentable_id` bigint(20) NOT NULL,
  `service_paymentable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_serviceables`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_serviceables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_invoice_id` bigint(20) NOT NULL,
  `pay` tinyint(1) NOT NULL DEFAULT '0',
  `client_serviceable_id` bigint(20) NOT NULL,
  `client_serviceable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `router_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5791 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `client_voz_services`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_voz_services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `voz_id` bigint(20) unsigned NOT NULL,
  `client_bundle_service_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint(20) NOT NULL,
  `unity` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` bigint(20) NOT NULL,
  `pay_period` enum('Periodo 1','Periodo 2','Periodo 3','Periodo 4','Periodo 5') COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `finish_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` tinyint(1) NOT NULL DEFAULT '0',
  `discount_percent` int(11) DEFAULT NULL COMMENT 'if discount is true',
  `start_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `end_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `discount_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `estado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voise_device` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Se debe crear una tabla con los dispositivos de voz',
  `direction` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `charged` tinyint(1) NOT NULL DEFAULT '0',
  `deployed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_voz_services_voz_id_foreign` (`voz_id`),
  KEY `client_voz_services_client_bundle_service_id_foreign` (`client_bundle_service_id`),
  KEY `idx_client_voz_services_client_id` (`client_id`),
  CONSTRAINT `client_voz_services_client_bundle_service_id_foreign` FOREIGN KEY (`client_bundle_service_id`) REFERENCES `client_bundle_services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_voz_services_voz_id_foreign` FOREIGN KEY (`voz_id`) REFERENCES `voises` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2729 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `colonies`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `colonies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipality_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `data` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `colonies_id_index` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=148359 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `commissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `iva` int(11) DEFAULT NULL,
  `account_balance` decimal(8,2) NOT NULL,
  `monthly_bonus` decimal(8,2) DEFAULT NULL,
  `monthly_bonus_sales_number` int(11) DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Por pagar',
  `seller_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `period` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_sales` int(11) NOT NULL,
  `number_prospects` int(11) NOT NULL,
  `zone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commissions_seller_id_foreign` (`seller_id`),
  CONSTRAINT `commissions_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `commissions_details`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commissions_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bonus` int(11) DEFAULT NULL,
  `commission_id` bigint(20) unsigned NOT NULL,
  `bundle_id` bigint(20) unsigned DEFAULT NULL,
  `prospect_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commissions_details_bundle_id_foreign` (`bundle_id`),
  KEY `commissions_details_client_id_foreign` (`client_id`),
  KEY `commissions_details_commission_id_foreign` (`commission_id`),
  KEY `commissions_details_prospect_id_foreign` (`prospect_id`),
  CONSTRAINT `commissions_details_bundle_id_foreign` FOREIGN KEY (`bundle_id`) REFERENCES `client_bundle_services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commissions_details_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client_main_information` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commissions_details_commission_id_foreign` FOREIGN KEY (`commission_id`) REFERENCES `commissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commissions_details_prospect_id_foreign` FOREIGN KEY (`prospect_id`) REFERENCES `crms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=586350 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `commissions_rules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commissions_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(8,2) DEFAULT NULL,
  `fixed_sales_commission` decimal(8,2) DEFAULT NULL,
  `number_of_prospects` int(11) DEFAULT NULL,
  `period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iva` int(11) DEFAULT NULL,
  `minimum_sales` int(11) DEFAULT NULL,
  `total_bonus` int(11) DEFAULT NULL,
  `number_sales_required` int(11) DEFAULT NULL,
  `conditions` json DEFAULT NULL,
  `commission_percentage_additional` int(11) DEFAULT NULL,
  `fixed_sales_commission_additional` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `installation_cost` int(11) DEFAULT NULL,
  `commission_percentage` int(11) DEFAULT NULL,
  `total_comission` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `number_sales_bonus_commission_required` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `penalty` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `fixed_sales_commission_distribuitors` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `fixed_sales_commission_distribuitors_percent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `conditions_comission` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `commissions_rules_sellers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commissions_rules_sellers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `commission_rule_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commissions_rules_sellers_commission_rule_id_foreign` (`commission_rule_id`),
  KEY `commissions_rules_sellers_seller_id_foreign` (`seller_id`),
  CONSTRAINT `commissions_rules_sellers_commission_rule_id_foreign` FOREIGN KEY (`commission_rule_id`) REFERENCES `commissions_rules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commissions_rules_sellers_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `credential_images`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credential_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crms`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `enable_same_name_or_rfc` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'cuando esta activo permite que en cliente o crm exista un usuario con los campos name, father_last_name y mother_last_name iguales',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_lead_information`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_lead_information` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint(20) unsigned NOT NULL,
  `score` bigint(20) DEFAULT '0',
  `last_contacted` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instalation_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crm_techical_user_id` bigint(20) unsigned DEFAULT NULL,
  `crm_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` bigint(20) DEFAULT NULL COMMENT 'trabajador que recibió el cliente first time',
  `state_id` bigint(20) unsigned DEFAULT NULL,
  `municipality_id` bigint(20) unsigned DEFAULT NULL,
  `colony_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'en source se escribe un pequeno comentario',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crm_lead_information_instalation_date_unique` (`instalation_date`),
  KEY `crm_lead_information_crm_id_foreign` (`crm_id`),
  CONSTRAINT `crm_lead_information_crm_id_foreign` FOREIGN KEY (`crm_id`) REFERENCES `crms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `crm_main_information`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_main_information` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint(20) unsigned NOT NULL,
  `ift` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `father_last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_is_required` tinyint(1) NOT NULL DEFAULT '1',
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nif_pasaport` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'RFC/CURP',
  `partner_id` bigint(20) DEFAULT NULL,
  `location_id` bigint(20) DEFAULT NULL,
  `high_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha de alta',
  `street` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `internal_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colony_id` bigint(20) unsigned DEFAULT NULL,
  `municipality_id` bigint(20) unsigned DEFAULT NULL,
  `state_id` bigint(20) unsigned DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geodata` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `nuevo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `azul` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `azul1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_main_information_crm_id_foreign` (`crm_id`),
  CONSTRAINT `crm_main_information_crm_id_foreign` FOREIGN KEY (`crm_id`) REFERENCES `crms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `default_values`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `default_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4428 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_clients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `added_by_id` bigint(20) DEFAULT NULL,
  `show` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1900 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_crms`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_crms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint(20) NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `added_by_id` bigint(20) DEFAULT NULL,
  `show` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `html` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document_type_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_type_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint(20) NOT NULL,
  `preview` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Se pone en true cuando se sube un archivo y aun no se ha salvado.',
  `fileable_id` bigint(20) NOT NULL,
  `fileable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2059 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `log_activities`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL COMMENT 'system_user',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `logable_id` bigint(20) NOT NULL,
  `logable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=555 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mikrotik_client_hostpot_radius`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mikrotik_client_hostpot_radius` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `mikrotik_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mikrotik_client_hostpot_users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mikrotik_client_hostpot_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `mikrotik_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mikrotik_client_ppoes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mikrotik_client_ppoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `mikrotik_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4464 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `model_has_permissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `model_has_roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `municipalities`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `municipalities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `municipalities_id_index` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2476 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `networks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `networks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `network` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rootnet` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `used` int(11) DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `network_type` enum('RootNet','EndNet') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `network_category` enum('Dev','Coorporativa','Test','Produccion') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `router_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_of_use` enum('Estatico','Pool') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_usage_network` tinyint(1) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `deployed` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `networks_id_index` (`id`),
  KEY `networks_title_index` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `network_ips`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `network_ips` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `network_id` int(11) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `used_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hostname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `host_category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ping` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `type_service` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `network_ips_client_id_index` (`client_id`),
  KEY `network_ips_network_id_index` (`network_id`),
  KEY `network_ips_ip_index` (`ip`)
) ENGINE=InnoDB AUTO_INCREMENT=26973 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nomenclatures`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nomenclatures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nomenclatures_name_unique` (`name`),
  KEY `nomenclatures_client_id_foreign` (`client_id`),
  KEY `nomenclatures_name_index` (`name`),
  CONSTRAINT `nomenclatures_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39938 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `observation_tasks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `observation_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `observation` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=667 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_id` bigint(20) NOT NULL,
  `date` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` double NOT NULL,
  `payment_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `receipt` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Se genera con la fecha más un número consecutivo generado.',
  `send_receipt_after_payment` tinyint(1) DEFAULT NULL,
  `add_by` bigint(20) NOT NULL,
  `paymentable_id` bigint(20) NOT NULL,
  `paymentable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled_payment_promise` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `first_court_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_amount` double DEFAULT NULL,
  `second_court_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_amount` double DEFAULT NULL,
  `third_court_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `third_amount` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_id_index` (`id`),
  KEY `idx_payments_paymentable_id` (`paymentable_id`),
  KEY `idx_payments_date` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=102048 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments_details`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `prospect_id` bigint(20) unsigned DEFAULT NULL,
  `bundle_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_details_payment_id_foreign` (`payment_id`),
  KEY `payments_details_client_id_foreign` (`client_id`),
  KEY `payments_details_bundle_id_foreign` (`bundle_id`),
  KEY `payments_details_prospect_id_foreign` (`prospect_id`),
  CONSTRAINT `payments_details_bundle_id_foreign` FOREIGN KEY (`bundle_id`) REFERENCES `client_bundle_services` (`id`),
  CONSTRAINT `payments_details_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client_main_information` (`id`),
  CONSTRAINT `payments_details_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments_sellers` (`id`),
  CONSTRAINT `payments_details_prospect_id_foreign` FOREIGN KEY (`prospect_id`) REFERENCES `crms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments_sellers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments_sellers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `amount` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method_of_payment` bigint(20) unsigned NOT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `commission_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_sellers_method_of_payment_foreign` (`method_of_payment`),
  KEY `payments_sellers_created_by_foreign` (`created_by`),
  KEY `payments_sellers_seller_id_foreign` (`seller_id`),
  KEY `payments_sellers_commission_id_foreign` (`commission_id`),
  CONSTRAINT `payments_sellers_commission_id_foreign` FOREIGN KEY (`commission_id`) REFERENCES `commissions` (`id`),
  CONSTRAINT `payments_sellers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `payments_sellers_method_of_payment_foreign` FOREIGN KEY (`method_of_payment`) REFERENCES `method_of_payments` (`id`),
  CONSTRAINT `payments_sellers_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `receipts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receipt` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receiptable_id` bigint(20) NOT NULL,
  `receiptable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=98779 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reminders_configurations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reminders_configurations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) NOT NULL,
  `activate_reminders` tinyint(1) DEFAULT '0',
  `type_of_message` enum('Mail','SMS','Mail + SMS') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_1_days` int(11) DEFAULT NULL,
  `reminder_2_days` int(11) DEFAULT NULL,
  `reminder_3_days` int(11) DEFAULT NULL,
  `reminder_payment_3` tinyint(1) DEFAULT NULL,
  `reminder_payment_amount` double DEFAULT '0',
  `reminder_payment_comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reminders_configurations_client_id_index` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23310 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sales`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `total` decimal(8,2) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int(11) NOT NULL,
  `medium_sale_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_medium_sale_id_foreign` (`medium_sale_id`),
  KEY `sales_client_id_foreign` (`client_id`),
  KEY `sales_seller_id_foreign` (`seller_id`),
  CONSTRAINT `sales_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_medium_sale_id_foreign` FOREIGN KEY (`medium_sale_id`) REFERENCES `medium_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sellers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sellers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status_id` bigint(20) unsigned DEFAULT NULL,
  `type_id` bigint(20) unsigned DEFAULT NULL,
  `balance` decimal(8,2) NOT NULL DEFAULT '0.00',
  `range` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Cobre',
  PRIMARY KEY (`id`),
  KEY `sellers_user_id_foreign` (`user_id`),
  KEY `sellers_status_id_foreign` (`status_id`),
  KEY `sellers_type_id_foreign` (`type_id`),
  CONSTRAINT `sellers_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `seller_status` (`id`),
  CONSTRAINT `sellers_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `seller_types` (`id`),
  CONSTRAINT `sellers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `seller_status`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seller_status` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `seller_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seller_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_in_address_lists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_in_address_lists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `serviceable_id` int(11) NOT NULL,
  `serviceable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deployed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12695 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `setting_debt_payment_client_customs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setting_debt_payment_client_customs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `percent_discount` int(11) NOT NULL,
  `apply_group_of_months` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `setting_debt_payment_client_recurrents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setting_debt_payment_client_recurrents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `apply_discount` tinyint(1) NOT NULL,
  `percent_discount` int(10) unsigned NOT NULL,
  `apply_group_of_days` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `states`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `states` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `states_id_index` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tasks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_main_information_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_service_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workflow` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geo_data` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notification` tinyint(1) NOT NULL DEFAULT '0',
  `scheduled` tinyint(1) NOT NULL DEFAULT '0',
  `start_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_to_task_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_from_task_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_verification` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dedicated_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT '0',
  `archived_at` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archived_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finish_at` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_title_index` (`title`),
  KEY `tasks_address_index` (`address`)
) ENGINE=InnoDB AUTO_INCREMENT=614 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task_notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('Alta','Media','Baja') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `base_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_id` bigint(20) unsigned DEFAULT NULL,
  `created_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_notifications_task_id_foreign` (`task_id`),
  KEY `task_notifications_created_id_foreign` (`created_id`),
  CONSTRAINT `task_notifications_created_id_foreign` FOREIGN KEY (`created_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_notifications_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task_user`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_user_task_id_foreign` (`task_id`),
  KEY `task_user_user_id_foreign` (`user_id`),
  CONSTRAINT `task_user_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2367 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teams`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `team_user`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_user_team_id_foreign` (`team_id`),
  KEY `team_user_user_id_foreign` (`user_id`),
  CONSTRAINT `team_user_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `team_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `template_tasks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `template_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_task` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_verification_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `assigned_to` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `template_verifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `template_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `list` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tickets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `topic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_lead` int(11) DEFAULT NULL COMMENT 'Este es el client_id',
  `priority` int(11) DEFAULT NULL,
  `estado` enum('Nuevo','Trabajo en curso','Resuelto','Esperando al cliente','Esperando al agente','Cerrado','Reciclado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nuevo',
  `group` enum('Cualquier','IT','Finanzas','Ventas') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('Pregunta','Incidente','Problema','Solicitud de función','Cliente potencial') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `reporter` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reporter_id` int(11) DEFAULT NULL,
  `reporter_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `incoming_customer` int(11) DEFAULT NULL,
  `hidden` tinyint(1) DEFAULT NULL,
  `task` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `star` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shareable` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duplicate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disable_association_closed_status` tinyint(1) DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  `date_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colony_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `date` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `debit` double DEFAULT NULL,
  `credit` double DEFAULT NULL,
  `account_balance` double NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `cantidad` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint(20) NOT NULL,
  `type` enum('debit','credit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` double NOT NULL,
  `iva` int(11) NOT NULL,
  `total` double NOT NULL,
  `from_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `period` text COLLATE utf8mb4_unicode_ci,
  `add_to_invoice` tinyint(1) NOT NULL,
  `company_balance` double NOT NULL,
  `movement` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transactionable_id` bigint(20) NOT NULL,
  `transactionable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_payment` tinyint(1) NOT NULL DEFAULT '0',
  `payment_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_from_old` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_to_old` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_payment_id_index` (`payment_id`),
  KEY `transactions_client_id_index` (`client_id`),
  KEY `transactions_date_index` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=217074 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transaction_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `father_last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_municipality` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_postal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rfc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photography` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_seller` tinyint(1) NOT NULL DEFAULT '0',
  `color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colony` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_login_user_unique` (`login_user`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_name_index` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3986 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_column_datatable_modules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_column_datatable_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `column_datatable_module_id` bigint(20) unsigned NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=869 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `work_flows`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_flows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET AUTOCOMMIT=@OLD_AUTOCOMMIT */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on: Mon, 16 Dec 2024 16:42:43 -0600
