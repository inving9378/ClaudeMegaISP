
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
DROP TABLE IF EXISTS `active_equipment_peripherals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `active_equipment_peripherals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `active_equipment_id` bigint unsigned NOT NULL,
  `model` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` bigint unsigned NOT NULL,
  `ports` int unsigned NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `active_equipment_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `active_equipment_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('Router','swtich','OLT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` bigint unsigned NOT NULL,
  `model` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cards` int NOT NULL,
  `ethernet_ports` int NOT NULL,
  `sfp_ports` int NOT NULL,
  `sfp_plus_ports` int NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `active_equipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `active_equipments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rack_id` bigint unsigned NOT NULL,
  `type_id` bigint unsigned NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`),
  KEY `activity_log_client_id_index` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1570394 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_integration_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_integration_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `integration_id` bigint unsigned NOT NULL,
  `company_id` int unsigned NOT NULL DEFAULT '1',
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `api_integration_logs_integration_id_index` (`integration_id`),
  KEY `api_integration_logs_company_id_index` (`company_id`),
  CONSTRAINT `api_integration_logs_integration_id_foreign` FOREIGN KEY (`integration_id`) REFERENCES `api_integrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_integration_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_integration_usage` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `integration_id` bigint unsigned NOT NULL,
  `company_id` int unsigned NOT NULL DEFAULT '1',
  `usage_date` date NOT NULL,
  `feature` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `call_count` int unsigned NOT NULL DEFAULT '0',
  `cost_usd` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_integration_usage_integration_id_usage_date_feature_unique` (`integration_id`,`usage_date`,`feature`),
  KEY `api_integration_usage_integration_id_index` (`integration_id`),
  KEY `api_integration_usage_company_id_index` (`company_id`),
  KEY `api_integration_usage_usage_date_index` (`usage_date`),
  CONSTRAINT `api_integration_usage_integration_id_foreign` FOREIGN KEY (`integration_id`) REFERENCES `api_integrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_integrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_integrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL DEFAULT '1',
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `encrypted_value` text COLLATE utf8mb4_unicode_ci,
  `key_preview` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `key_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_default_for_provider` tinyint(1) NOT NULL DEFAULT '1',
  `last_validation_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_validated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_integrations_company_id_slug_unique` (`company_id`,`slug`),
  KEY `api_integrations_company_id_index` (`company_id`),
  KEY `api_integrations_provider_index` (`provider`),
  KEY `api_integrations_slug_index` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_mobile_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_mobile_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_mobile_config_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_mobile_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_mobile_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `method` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` smallint unsigned NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `api_mobile_logs_created_at_index` (`created_at`),
  KEY `api_mobile_logs_status_created_at_index` (`status`,`created_at`),
  KEY `api_mobile_logs_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `app_layout_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_layout_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_datatable_color` tinyint(1) DEFAULT '0',
  `row_status_style` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'underline',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tabs_json` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `app_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `platform` enum('android','ios') COLLATE utf8mb4_unicode_ci NOT NULL,
  `version_name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version_code` int NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint NOT NULL DEFAULT '0',
  `changelog` text COLLATE utf8mb4_unicode_ci,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '0',
  `min_version_code` int DEFAULT NULL,
  `download_count` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_versions_platform_active_version_code_index` (`platform`,`active`,`version_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_plan_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_plan_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `prioridad` enum('urgente','alta','media','baja') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completado` tinyint(1) NOT NULL DEFAULT '0',
  `completado_at` timestamp NULL DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `orden` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auditoria_minero_cursores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria_minero_cursores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fuente` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ultimo_id` bigint unsigned NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auditoria_minero_cursores_fuente_unique` (`fuente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auditoria_senales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria_senales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fuente` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activity_log',
  `ocurrido_en` timestamp NOT NULL,
  `payload` json DEFAULT NULL,
  `dedupe_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revisado` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auditoria_senales_dedupe_key_unique` (`dedupe_key`),
  KEY `auditoria_senales_tipo_index` (`tipo`),
  KEY `auditoria_senales_fuente_index` (`fuente`),
  KEY `auditoria_senales_ocurrido_en_index` (`ocurrido_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `balances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `amount` double NOT NULL DEFAULT '0',
  `balanceable_id` bigint NOT NULL,
  `balanceable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `balances_balanceable_id_index` (`balanceable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4863 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint NOT NULL,
  `billing_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_zip_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_addresses_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `notification_window_hours` smallint unsigned NOT NULL DEFAULT '12',
  `send_prefactura_email` tinyint(1) NOT NULL DEFAULT '1',
  `send_ticket_email` tinyint(1) NOT NULL DEFAULT '0',
  `envio_pausado` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint NOT NULL,
  `type_billing_id` bigint NOT NULL,
  `payment_method_id` bigint DEFAULT NULL,
  `period` bigint unsigned DEFAULT NULL,
  `minimum_balance` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `send_financial_notification` tinyint(1) NOT NULL DEFAULT '0',
  `create_monthly_invoice` tinyint(1) NOT NULL DEFAULT '0',
  `billing_activated` tinyint(1) NOT NULL DEFAULT '0',
  `billing_date` int DEFAULT NULL COMMENT 'Dia de facturacion',
  `billing_expiration` int DEFAULT NULL COMMENT 'cantidad dias con servicio desde a partir del dia de facturacion',
  `grace_period` int DEFAULT NULL COMMENT 'Cantidad de dias en los que se mantendra realizando facturacion del servicio aunque no haya saldo disponible',
  `membership_percentage` int DEFAULT NULL,
  `create_invoice` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Create invoices (after Charge & Invoice)',
  `autopay_invoice` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Auto pay invoices from account balance',
  `seller` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_configurations_client_id_index` (`client_id`),
  KEY `billing_configurations_type_billing_id_index` (`type_billing_id`),
  KEY `billing_configurations_payment_method_id_index` (`payment_method_id`),
  KEY `billing_configurations_billing_date_index` (`billing_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4823 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `document_type` enum('prefactura','ticket','recibo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `pdf_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('en_espera','enviado','cancelado','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_espera',
  `window_hours` smallint unsigned NOT NULL DEFAULT '12',
  `notificar_despues_de` timestamp NULL DEFAULT NULL,
  `enviado_at` timestamp NULL DEFAULT NULL,
  `regenerated_at` timestamp NULL DEFAULT NULL,
  `error_log` text COLLATE utf8mb4_unicode_ci,
  `send_by_email` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_notifications_payment_id_foreign` (`payment_id`),
  KEY `billing_notifications_status_notificar_despues_de_index` (`status`,`notificar_despues_de`),
  KEY `billing_notifications_client_id_document_type_index` (`client_id`,`document_type`),
  KEY `billing_notifications_invoice_id_document_type_index` (`invoice_id`,`document_type`),
  CONSTRAINT `billing_notifications_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `billing_notifications_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `billing_notifications_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enable_reminders` tinyint(1) NOT NULL DEFAULT '1',
  `message_type` enum('email','sms') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'email',
  `send_time` time NOT NULL DEFAULT '10:00:00',
  `reminder_1_days` int DEFAULT NULL,
  `reminder_1_subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_1_email_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_1_sms_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_2_days` int DEFAULT NULL,
  `reminder_2_subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_2_email_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_2_sms_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_3_days` int DEFAULT NULL,
  `reminder_3_subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_3_email_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_3_sms_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `all_payment_methods` tinyint(1) NOT NULL DEFAULT '1',
  `payment_reminder_methods` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attach_paid_invoices` tinyint(1) NOT NULL DEFAULT '1',
  `cc_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `box_inputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `box_inputs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` int NOT NULL,
  `box_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `box_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `box_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('Empalme','Primer nivel','Segundo nivel') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` bigint unsigned NOT NULL,
  `inputs` int unsigned NOT NULL,
  `trays` int unsigned NOT NULL,
  `mergers_by_tray` int unsigned NOT NULL,
  `ports` int unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `box_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `box_zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` bigint unsigned NOT NULL,
  `client` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `box_zones_zone_id_foreign` (`zone_id`),
  CONSTRAINT `box_zones_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `boxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `boxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nomenclature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `box_type_id` bigint unsigned NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `point_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `brands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `brands_created_by_foreign` (`created_by`),
  KEY `brands_updated_by_foreign` (`updated_by`),
  CONSTRAINT `brands_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `brands_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `buffers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `buffers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `color_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `bundles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bundles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(20,2) DEFAULT NULL,
  `tax_include` tinyint(1) DEFAULT NULL,
  `tax` bigint DEFAULT NULL,
  `transaction_category` enum('Servicio','Descuento','Pago','Reembolso','Corrección') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_days` bigint DEFAULT NULL,
  `activation_fee` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_instalation_enable` tinyint(1) DEFAULT '0',
  `cost_instalation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `get_activation_fee_when` enum('En facturación del primer servicio','Al crear el servicio') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emit_invoice` tinyint(1) DEFAULT NULL,
  `contract_duration` bigint DEFAULT NULL,
  `automatic_renewal` tinyint(1) DEFAULT NULL,
  `auto_reactivate` tinyint(1) DEFAULT NULL,
  `cancellation_fee` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prior_cancellation_fee` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_period` bigint DEFAULT NULL,
  `discount_value` bigint DEFAULT NULL,
  `promotion_enable` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `init_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_value_fixed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('WAN','LAN') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active_equipment_id` bigint unsigned NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `change_plan_internet_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `change_plan_internet_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `internet_id` bigint NOT NULL,
  `tarifa_internet_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `change_plan_voz_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `change_plan_voz_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voz_id` bigint NOT NULL,
  `tarifa_voz_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `circuito_disparos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `circuito_disparos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `requested_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'boton',
  `item_id` bigint unsigned DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `circuito_disparos_requested_at_index` (`requested_at`),
  KEY `circuito_disparos_consumed_at_index` (`consumed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `circuito_ejecuciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `circuito_ejecuciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `duracion_seg` int DEFAULT NULL,
  `modo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aviso_previo',
  `modelo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pausado` tinyint(1) NOT NULL DEFAULT '0',
  `rc` int DEFAULT NULL,
  `items_tocados` json DEFAULT NULL,
  `n_propuestas` int NOT NULL DEFAULT '0',
  `n_decisiones` int NOT NULL DEFAULT '0',
  `ejecuto` tinyint(1) NOT NULL DEFAULT '0',
  `resumen` text COLLATE utf8mb4_unicode_ci,
  `log_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `circuito_ejecuciones_started_at_index` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `circuito_frontera_terminos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `circuito_frontera_terminos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `categoria` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `termino` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `palabra_completa` tinyint(1) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `circuito_frontera_terminos_categoria_termino_unique` (`categoria`,`termino`),
  KEY `circuito_frontera_terminos_categoria_index` (`categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `circuito_fronteras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `circuito_fronteras` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `categoria` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `efecto` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bandeja',
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `circuito_fronteras_categoria_unique` (`categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `circuito_motor_pulsos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `circuito_motor_pulsos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `motor` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inicio_at` datetime NOT NULL,
  `fin_at` datetime DEFAULT NULL,
  `ok` tinyint(1) NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci,
  `duracion_ms` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `circuito_motor_pulsos_motor_index` (`motor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `circuito_revisiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `circuito_revisiones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `roadmap_item_id` bigint unsigned NOT NULL,
  `veredicto` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `en_alcance` tinyint(1) NOT NULL DEFAULT '0',
  `categoria_escalada` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confianza` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo` text COLLATE utf8mb4_unicode_ci,
  `riesgos` text COLLATE utf8mb4_unicode_ci,
  `modelo` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decisor_ctx` text COLLATE utf8mb4_unicode_ci,
  `tokens_in` int unsigned DEFAULT NULL,
  `tokens_out` int unsigned DEFAULT NULL,
  `aplicado` tinyint(1) NOT NULL DEFAULT '0',
  `actor` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `circuito_revisiones_roadmap_item_id_index` (`roadmap_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_additional_information`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_additional_information` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint DEFAULT NULL,
  `connection_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'olt',
  `category` enum('Particular','Empresa') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modem_sn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gpon_ont` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `power_dbm` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `box_nomenclator` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `box_nomenclator_old` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_film` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_film` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_wifi` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reinstatement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installation_on_time` tinyint(1) DEFAULT NULL,
  `amount_technician_and_why` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'El tecnico le atendio con amabilidad y respeto si, no y porque',
  `doubt_signed_contract` tinyint(1) DEFAULT NULL,
  `technician_attencion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'El tecnico le atendio con amabilidad y respeto si, no y porque',
  `last_time_online` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_dedicated` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_additional_information_client_id_index` (`client_id`),
  KEY `idx_client_additional_information_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=4823 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_bundle_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_bundle_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mikrotik_sync_status` enum('pending','synced','failed') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estado de sincronización con Mikrotik. NULL = no rastreado (legacy). Item roadmap #86.',
  `mikrotik_sync_error` text COLLATE utf8mb4_unicode_ci,
  `mikrotik_synced_at` timestamp NULL DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `bundle_id` bigint unsigned NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` bigint NOT NULL,
  `pay_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` tinyint(1) NOT NULL DEFAULT '0',
  `discount_percent` int DEFAULT NULL COMMENT 'if discount is true',
  `start_date_discount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `end_date_discount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `discount_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `contract_start_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_end_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `automatic_renewal` tinyint(1) NOT NULL,
  `charged` tinyint(1) NOT NULL DEFAULT '0',
  `deployed` tinyint(1) NOT NULL DEFAULT '0',
  `instalation_cost_paid` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_bundle_services_bundle_id_foreign` (`bundle_id`),
  KEY `idx_client_bundle_services_client_id` (`client_id`),
  CONSTRAINT `client_bundle_services_bundle_id_foreign` FOREIGN KEY (`bundle_id`) REFERENCES `bundles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2893 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_contratable_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_contratable_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `contratable_service_id` bigint unsigned NOT NULL,
  `status` enum('active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `activated_at` timestamp NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `trial_invoices_remaining` smallint unsigned DEFAULT NULL,
  `activated_by` enum('client','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_service_unique` (`client_id`,`contratable_service_id`),
  KEY `client_contratable_subscriptions_contratable_service_id_foreign` (`contratable_service_id`),
  CONSTRAINT `client_contratable_subscriptions_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_contratable_subscriptions_contratable_service_id_foreign` FOREIGN KEY (`contratable_service_id`) REFERENCES `contratable_services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_custom_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_custom_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mikrotik_sync_status` enum('pending','synced','failed') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estado de sincronización con Mikrotik. NULL = no rastreado (legacy). Item roadmap #86.',
  `mikrotik_sync_error` text COLLATE utf8mb4_unicode_ci,
  `mikrotik_synced_at` timestamp NULL DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `custom_id` bigint unsigned DEFAULT NULL,
  `client_bundle_service_id` bigint unsigned DEFAULT NULL,
  `service_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint DEFAULT NULL,
  `unity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` bigint DEFAULT NULL,
  `pay_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finish_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` tinyint(1) NOT NULL DEFAULT '0',
  `discount_percent` int DEFAULT NULL COMMENT 'if discount is true',
  `start_date_discount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `end_date_discount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `discount_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `payment_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deferred_payment_in_month` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `charged` tinyint(1) NOT NULL DEFAULT '0',
  `deployed` tinyint(1) NOT NULL DEFAULT '0',
  `instalation_cost_paid` tinyint(1) DEFAULT '0',
  `router_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv4_assignment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_ipv4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv4_pool` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv6` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delegated_ipv6` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mac` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bandwidth` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_activation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_instalation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hidden` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `internet_id` bigint unsigned DEFAULT NULL,
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
DROP TABLE IF EXISTS `client_fiscal_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_fiscal_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `facturacion_fiscal` tinyint(1) NOT NULL DEFAULT '0',
  `razon_social` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rfc` varchar(13) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_postal_fiscal` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regimen_fiscal` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uso_cfdi` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo_fiscal` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `constancia_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL DEFAULT '0',
  `updated_by` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_fiscal_data_client_id_unique` (`client_id`),
  KEY `client_fiscal_data_rfc_index` (`rfc`),
  CONSTRAINT `client_fiscal_data_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_grace_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_grace_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2558 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_internet_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_internet_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mikrotik_sync_status` enum('pending','synced','failed') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estado de sincronización con Mikrotik. NULL = no rastreado (legacy). Item roadmap #86.',
  `mikrotik_sync_error` text COLLATE utf8mb4_unicode_ci,
  `mikrotik_synced_at` timestamp NULL DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `internet_id` bigint unsigned DEFAULT NULL,
  `client_bundle_service_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint DEFAULT NULL,
  `unity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` bigint DEFAULT NULL,
  `pay_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finish_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` tinyint(1) NOT NULL DEFAULT '0',
  `discount_percent` int DEFAULT NULL COMMENT 'if discount is true',
  `start_date_discount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `end_date_discount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `discount_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `estado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `router_id` bigint unsigned DEFAULT NULL,
  `client_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv4_assignment` enum('IP Estatica','Pool IP') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if ipv4_assignment is IP Estatica',
  `additional_ipv4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if ipv4_assignment is IP Estatica',
  `ipv4_pool` int DEFAULT NULL COMMENT 'if ipv4_assignment is Pool IP',
  `ipv6` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delegated_ipv6` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mac` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deferred_payment_in_month` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_activation` double(8,2) NOT NULL DEFAULT '0.00',
  `charged` tinyint(1) NOT NULL DEFAULT '0',
  `deployed` tinyint(1) NOT NULL DEFAULT '0',
  `instalation_cost_paid` tinyint(1) DEFAULT '0',
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
DROP TABLE IF EXISTS `client_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` bigint NOT NULL COMMENT 'numero generardo del pago año+mes+numero',
  `total` double(8,2) NOT NULL COMMENT 'total a pagar',
  `payment_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha de pago',
  `estado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pagar (del saldo de la cuenta)',
  `client_id` bigint unsigned NOT NULL,
  `last_update` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ultima actualiacion de la factura',
  `pay_up` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha que asigna la configuracion del fin de la facturacion',
  `use_of_transactions` int DEFAULT NULL COMMENT 'cantidad de transacciones que necesito para pagar la fafctura',
  `note` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `memo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Numero consecutivo de pago',
  `is_sent` tinyint(1) NOT NULL DEFAULT '0',
  `delete_transactions` tinyint(1) NOT NULL DEFAULT '0',
  `added_by` bigint unsigned NOT NULL COMMENT 'Quien generó la faactura',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_proforma` tinyint(1) NOT NULL DEFAULT '0',
  `document_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=123129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `add_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_logs_client_id_foreign` (`client_id`),
  CONSTRAINT `client_logs_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_main_information`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_main_information` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint NOT NULL,
  `user_id` bigint DEFAULT NULL COMMENT 'Usuario del sistema',
  `user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mikrotik User',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mikrotik User Password',
  `portal_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portal_registered_at` timestamp NULL DEFAULT NULL,
  `portal_last_login_at` timestamp NULL DEFAULT NULL,
  `estado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Nuevo' COMMENT 'Nuevo => Nuevo(Todavía no conectado),\n                Activo => Activado, Inactivo => Inactivo(No puede utilizar los servicios),\n                Bloqueado => Bloqueado\n            ',
  `type_of_billing_id` bigint DEFAULT NULL,
  `ift` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `father_last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nif_pasaport` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'RFC/CURP',
  `partner_id` bigint DEFAULT NULL,
  `street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `internal_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colony_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipality_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` bigint DEFAULT NULL,
  `geodata` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geo_data` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discharge_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha de alta',
  `activation_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha en la que se convirtio de crm a cliente',
  `activation_cost` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '299',
  `is_payment_activation_cost` tinyint(1) NOT NULL DEFAULT '0',
  `duration_contract_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distribute_commission` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `seller_id` bigint DEFAULT NULL,
  `medium_id` bigint DEFAULT NULL,
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
  KEY `idx_client_main_information_address` (`address`),
  KEY `idx_client_main_information_created_at` (`created_at`),
  KEY `idx_client_main_information_activation_date` (`activation_date`),
  KEY `client_main_information_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4832 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_payment_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_payment_metadata` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `previous_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `previous_fecha_pago` date DEFAULT NULL,
  `previous_fecha_corte` date DEFAULT NULL,
  `previous_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_fecha_fin_periodo_gracia` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `additional_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_payment_metadata_client_id_foreign` (`client_id`),
  KEY `client_payment_metadata_payment_id_client_id_index` (`payment_id`,`client_id`),
  CONSTRAINT `client_payment_metadata_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_payment_promises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_payment_promises` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_court_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_amount_is_pay` tinyint(1) NOT NULL DEFAULT '0',
  `second_court_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_amount_is_pay` tinyint(1) NOT NULL DEFAULT '0',
  `third_court_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `third_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `third_amount_is_pay` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_payment_references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_payment_references` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `reference` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `algo_version` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_payment_references_client_id_unique` (`client_id`),
  UNIQUE KEY `client_payment_references_reference_unique` (`reference`),
  CONSTRAINT `client_payment_references_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_payment_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_payment_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_in_time` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Campo que te dice si el pago se hizo en tiempo',
  `service_paymentable_id` bigint NOT NULL,
  `service_paymentable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_plan_promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_plan_promotions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned DEFAULT NULL,
  `before_download_profile` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_download_profile` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `before_upload_profile` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_upload_profile` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','canceled','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `end_at` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `client_plan_promotions_client_id_foreign` (`client_id`),
  CONSTRAINT `client_plan_promotions_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_recurring_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_recurring_cards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `openpay_customer_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `openpay_card_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `card_brand` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_last4` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_exp` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cardholder` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','paused','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `consent_at` timestamp NULL DEFAULT NULL,
  `consent_channel` enum('portal','app','admin','liga') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'portal',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_recurring_cards_client_id_index` (`client_id`),
  KEY `client_recurring_cards_client_id_status_index` (`client_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_referral_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_referral_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `referral_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referral_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_type` enum('single_reward','multilevel') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `threshold_amount_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_eligible` tinyint NOT NULL DEFAULT '0',
  `total_commissions_earned` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_rewards_earned` int NOT NULL DEFAULT '0',
  `total_referrals` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_referral_profiles_client_id_unique` (`client_id`),
  UNIQUE KEY `client_referral_profiles_referral_code_unique` (`referral_code`),
  KEY `idx_referral_code` (`referral_code`),
  KEY `idx_plan_eligible` (`plan_type`,`is_eligible`),
  KEY `idx_total_earned` (`total_commissions_earned`),
  CONSTRAINT `client_referral_profiles_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_serviceables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_serviceables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_invoice_id` bigint NOT NULL,
  `pay` tinyint(1) NOT NULL DEFAULT '0',
  `client_serviceable_id` bigint NOT NULL,
  `client_serviceable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `router_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5791 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_voz_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_voz_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `voz_id` bigint unsigned NOT NULL,
  `client_bundle_service_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint NOT NULL,
  `unity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` bigint NOT NULL,
  `pay_period` enum('Periodo 1','Periodo 2','Periodo 3','Periodo 4','Periodo 5') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `finish_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` tinyint(1) NOT NULL DEFAULT '0',
  `discount_percent` int DEFAULT NULL COMMENT 'if discount is true',
  `start_date_discount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `end_date_discount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `discount_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if discount is true',
  `estado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voise_device` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Se debe crear una tabla con los dispositivos de voz',
  `direction` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `charged` tinyint(1) NOT NULL DEFAULT '0',
  `deployed` tinyint(1) NOT NULL DEFAULT '0',
  `instalation_cost_paid` tinyint(1) DEFAULT '0',
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
DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `referred_by_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint DEFAULT NULL COMMENT 'System User',
  `fecha_corte` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha de finalizacion de los servicios del cliente',
  `fecha_pago` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha de pago de los servicios del cliente',
  `fecha_fin_periodo_gracia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_suspension` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active_promise_payment` tinyint(1) NOT NULL DEFAULT '0',
  `last_activity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ultima vez que tuvo actividad el usuario en el mikrotik',
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `migrated_from_splynt` tinyint(1) NOT NULL DEFAULT '0',
  `is_test_data` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Marca registros generados por simulación — nunca mostrar en producción real',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clients_created_by_foreign` (`created_by`),
  KEY `clients_updated_by_foreign` (`updated_by`),
  KEY `clients_id_index` (`id`),
  KEY `clients_fecha_corte_index` (`fecha_corte`),
  KEY `clients_deleted_at_index` (`deleted_at`),
  KEY `idx_referred_by_code` (`referred_by_code`),
  KEY `idx_is_test_data` (`is_test_data`),
  CONSTRAINT `clients_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `clients_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6693 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cobranza_campanas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobranza_campanas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('borrador','activa','pausada','completada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `hora_inicio` time NOT NULL DEFAULT '09:00:00',
  `hora_fin` time NOT NULL DEFAULT '20:00:00',
  `max_intentos` tinyint unsigned NOT NULL DEFAULT '3',
  `minutos_entre_intentos` smallint unsigned NOT NULL DEFAULT '180',
  `audio_mensaje` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dias_vencimiento` int unsigned NOT NULL DEFAULT '1',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cobranza_llamada_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobranza_llamada_eventos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `llamada_id` bigint unsigned NOT NULL,
  `evento` enum('Originate','ANSWER','BUSY','NOANSWER','FAILED','DTMF','TRANSFER','HANGUP') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `ocurrido_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cobranza_llamada_eventos_llamada_id_evento_index` (`llamada_id`,`evento`),
  CONSTRAINT `cobranza_llamada_eventos_llamada_id_foreign` FOREIGN KEY (`llamada_id`) REFERENCES `cobranza_llamadas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cobranza_llamadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobranza_llamadas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campana_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `intentos` tinyint unsigned NOT NULL DEFAULT '0',
  `estado` enum('pendiente','marcando','contestada','no_contesto','ocupado','fallida','pagada','excluida') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `ultimo_intento_at` timestamp NULL DEFAULT NULL,
  `proximo_intento_at` timestamp NULL DEFAULT NULL,
  `ami_channel` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ami_uniqueid` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto_vencido` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cobranza_llamadas_campana_id_estado_proximo_intento_at_index` (`campana_id`,`estado`,`proximo_intento_at`),
  KEY `cobranza_llamadas_client_id_estado_index` (`client_id`,`estado`),
  CONSTRAINT `cobranza_llamadas_campana_id_foreign` FOREIGN KEY (`campana_id`) REFERENCES `cobranza_campanas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cobranza_llamadas_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `colonies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `colonies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipality_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `colonies_id_index` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=148359 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `colors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `colors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `column_datatable_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `column_datatable_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `filter_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=823 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `command_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `command_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `command` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `process_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `frequency_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `execution_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `command_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `iva` int DEFAULT NULL,
  `account_balance` decimal(8,2) NOT NULL,
  `monthly_bonus` decimal(8,2) DEFAULT NULL,
  `monthly_bonus_sales_number` int DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Por pagar',
  `seller_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_sales` int NOT NULL,
  `number_prospects` int NOT NULL,
  `zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commissions_seller_id_foreign` (`seller_id`),
  CONSTRAINT `commissions_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commissions_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commissions_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bonus` int DEFAULT NULL,
  `commission_id` bigint unsigned NOT NULL,
  `bundle_id` bigint unsigned DEFAULT NULL,
  `prospect_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
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
DROP TABLE IF EXISTS `commissions_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commissions_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(8,2) DEFAULT NULL,
  `fixed_sales_commission` decimal(8,2) DEFAULT NULL,
  `number_of_prospects` int DEFAULT NULL,
  `period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_of_seller` bigint unsigned DEFAULT NULL,
  `fixed_salary` decimal(8,2) DEFAULT '0.00',
  `is_fixed_salary` tinyint(1) NOT NULL DEFAULT '0',
  `minimum_number_of_prospects` int DEFAULT '0',
  `minimum_sales_amount` int DEFAULT '0',
  `sales_commission` decimal(8,2) DEFAULT '0.00',
  `sales_commission_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_sales_commissions` json DEFAULT NULL,
  `selected_fields` json DEFAULT NULL,
  `monthly_bonus` json DEFAULT NULL,
  `distributors_commission` json DEFAULT NULL,
  `zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iva` int DEFAULT NULL,
  `minimum_sales` int DEFAULT NULL,
  `total_bonus` int DEFAULT NULL,
  `number_sales_required` int DEFAULT NULL,
  `conditions` json DEFAULT NULL,
  `commission_percentage_additional` int DEFAULT NULL,
  `fixed_sales_commission_additional` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `installation_cost` int DEFAULT NULL,
  `commission_percentage` int DEFAULT NULL,
  `total_comission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `number_sales_bonus_commission_required` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `penalty` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `fixed_sales_commission_distribuitors` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `fixed_sales_commission_distribuitors_percent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `conditions_comission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commissions_rules_type_of_seller_foreign` (`type_of_seller`),
  CONSTRAINT `commissions_rules_type_of_seller_foreign` FOREIGN KEY (`type_of_seller`) REFERENCES `seller_types` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commissions_rules_sellers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commissions_rules_sellers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commission_rule_id` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commissions_rules_sellers_commission_rule_id_foreign` (`commission_rule_id`),
  KEY `commissions_rules_sellers_seller_id_foreign` (`seller_id`),
  CONSTRAINT `commissions_rules_sellers_commission_rule_id_foreign` FOREIGN KEY (`commission_rule_id`) REFERENCES `commissions_rules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commissions_rules_sellers_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_information`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_information` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_representative` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_privacy_address` text COLLATE utf8mb4_unicode_ci,
  `company_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_external_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_internal_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_postal_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colony_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipality_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atention_client_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rfc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iva` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cominion_partner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_portal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `talento_app_logo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conciliation_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conciliation_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conciliation_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `config_finance_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `config_finance_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_config` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auto_send_notifications` tinyint(1) NOT NULL DEFAULT '0',
  `message_type` enum('email','sms') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'email',
  `email_template_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sms_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_bcc` text COLLATE utf8mb4_unicode_ci,
  `delay_hours` int unsigned NOT NULL DEFAULT '0',
  `notification_days` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notification_hours` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attach_receipt` tinyint(1) NOT NULL DEFAULT '0',
  `attach_invoice` tinyint(1) NOT NULL DEFAULT '0',
  `delay_days` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_finance_notifications_type_config_unique` (`type_config`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contratable_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contratable_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contratable_service_id` bigint unsigned NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rango_min` int unsigned NOT NULL,
  `rango_max` int unsigned DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contratable_packages_contratable_service_id_rango_min_index` (`contratable_service_id`,`rango_min`),
  CONSTRAINT `contratable_packages_contratable_service_id_foreign` FOREIGN KEY (`contratable_service_id`) REFERENCES `contratable_services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contratable_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contratable_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `metrica` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `meses_prueba` smallint unsigned NOT NULL DEFAULT '3',
  `aplica_iva` tinyint(1) NOT NULL DEFAULT '1',
  `iva_porcentaje` decimal(5,2) NOT NULL DEFAULT '16.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contratable_services_module_key_unique` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credential_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credential_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_lead_information`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_lead_information` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint unsigned NOT NULL,
  `score` bigint DEFAULT '0',
  `last_contacted` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instalation_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crm_techical_user_id` bigint unsigned DEFAULT NULL,
  `crm_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` bigint DEFAULT NULL COMMENT 'trabajador que recibió el cliente first time',
  `state_id` bigint unsigned DEFAULT NULL,
  `municipality_id` bigint unsigned DEFAULT NULL,
  `colony_id` bigint unsigned DEFAULT NULL,
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'en source se escribe un pequeno comentario',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crm_lead_information_instalation_date_unique` (`instalation_date`),
  KEY `crm_lead_information_crm_id_foreign` (`crm_id`),
  CONSTRAINT `crm_lead_information_crm_id_foreign` FOREIGN KEY (`crm_id`) REFERENCES `crms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_main_information`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_main_information` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint unsigned NOT NULL,
  `ift` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `father_last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_is_required` tinyint(1) NOT NULL DEFAULT '1',
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nif_pasaport` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'RFC/CURP',
  `partner_id` bigint DEFAULT NULL,
  `location_id` bigint DEFAULT NULL,
  `high_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'fecha de alta',
  `street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `internal_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colony_id` bigint unsigned DEFAULT NULL,
  `municipality_id` bigint unsigned DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geodata` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `nuevo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `azul` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `azul1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_main_information_crm_id_foreign` (`crm_id`),
  CONSTRAINT `crm_main_information_crm_id_foreign` FOREIGN KEY (`crm_id`) REFERENCES `crms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enable_same_name_or_rfc` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'cuando esta activo permite que en cliente o crm exista un usuario con los campos name, father_last_name y mother_last_name iguales',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crud_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crud_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint NOT NULL,
  `crud_package_id` bigint NOT NULL,
  `crud_package_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1789 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `update_description` tinyint(1) DEFAULT '0',
  `price` decimal(20,2) NOT NULL,
  `update_service` tinyint(1) DEFAULT '0',
  `partners` bigint DEFAULT NULL,
  `tax_include` tinyint(1) NOT NULL,
  `tax` bigint NOT NULL,
  `prepaid_period` enum('Mensual','Diario') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rates_to_change` bigint DEFAULT NULL,
  `transaction_category` enum('Servicio','Descuento','Pago','Reembolso','Corrección') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `available_in_self_registration` tinyint(1) DEFAULT '0',
  `bandwidth` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` enum('1','2','3','4','5','6') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_days` bigint DEFAULT NULL,
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
  `promotion_enable` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `init_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_value_fixed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cut_boxs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cut_boxs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `total_received` double NOT NULL DEFAULT '0',
  `total_extras` double NOT NULL DEFAULT '0',
  `total_technicals` double NOT NULL DEFAULT '0',
  `total_proveedores` double NOT NULL DEFAULT '0',
  `total_net` double NOT NULL DEFAULT '0',
  `end_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cut_boxs_user_id_foreign` (`user_id`),
  CONSTRAINT `cut_boxs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cut_extras_incomes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cut_extras_incomes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_method_id` bigint unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` double(8,2) NOT NULL,
  `comments` longtext COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `box_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cut_extras_incomes_payment_method_id_foreign` (`payment_method_id`),
  KEY `cut_extras_incomes_created_by_foreign` (`created_by`),
  KEY `cut_extras_incomes_box_id_foreign` (`box_id`),
  CONSTRAINT `cut_extras_incomes_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `cut_boxs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cut_extras_incomes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cut_extras_incomes_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `method_of_payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cut_fibers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cut_fibers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `type` enum('visible','no visible') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `power` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `meter` decimal(8,2) NOT NULL,
  `passive_equipment_id` bigint unsigned NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `cut_installations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cut_installations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_amount` double DEFAULT NULL,
  `installation_cost` double DEFAULT NULL,
  `warranty_cost` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `constance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activated` tinyint(1) NOT NULL DEFAULT '0',
  `box_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `technical_id` bigint unsigned DEFAULT NULL,
  `branch_id` bigint unsigned DEFAULT NULL,
  `comments` longtext COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cut_installations_box_id_foreign` (`box_id`),
  KEY `cut_installations_client_id_foreign` (`client_id`),
  KEY `cut_installations_created_by_foreign` (`created_by`),
  KEY `cut_installations_technical_id_foreign` (`technical_id`),
  KEY `cut_installations_branch_id_foreign` (`branch_id`),
  CONSTRAINT `cut_installations_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `cut_boxs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cut_installations_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `sucursals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cut_installations_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client_main_information` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cut_installations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cut_installations_technical_id_foreign` FOREIGN KEY (`technical_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cut_suppliers_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cut_suppliers_expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_method_id` bigint unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` double(8,2) NOT NULL,
  `comments` longtext COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `box_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cut_suppliers_expenses_payment_method_id_foreign` (`payment_method_id`),
  KEY `cut_suppliers_expenses_created_by_foreign` (`created_by`),
  KEY `cut_suppliers_expenses_box_id_foreign` (`box_id`),
  CONSTRAINT `cut_suppliers_expenses_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `cut_boxs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cut_suppliers_expenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cut_suppliers_expenses_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `method_of_payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cuts_observations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuts_observations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment` longtext COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `box_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cuts_observations_created_by_foreign` (`created_by`),
  KEY `cuts_observations_box_id_foreign` (`box_id`),
  CONSTRAINT `cuts_observations_box_id_foreign` FOREIGN KEY (`box_id`) REFERENCES `cut_boxs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cuts_observations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_internet_consumptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_internet_consumptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `bytes_in` bigint NOT NULL DEFAULT '0',
  `bytes_out` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_internet_consumptions_client_name_date_unique` (`client_name`,`date`),
  KEY `daily_internet_consumptions_client_name_index` (`client_name`),
  KEY `daily_internet_consumptions_date_index` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_ping_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_ping_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `avg_ms` decimal(8,2) DEFAULT NULL,
  `min_ms` decimal(8,2) DEFAULT NULL,
  `max_ms` decimal(8,2) DEFAULT NULL,
  `uptime_percent` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT '% del día que respondió',
  `total_checks` int unsigned NOT NULL DEFAULT '0',
  `down_checks` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_ping_statistics_client_id_date_unique` (`client_id`,`date`),
  KEY `daily_ping_statistics_client_id_date_index` (`client_id`,`date`),
  CONSTRAINT `daily_ping_statistics_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_plan_promotion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_plan_promotion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `upload` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `download` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration` int NOT NULL DEFAULT '0',
  `type_duration` enum('day','month') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `defined_by_user` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_plan_promotion_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_accesos_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_accesos_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `apartado_id` bigint unsigned DEFAULT NULL,
  `concepto_id` bigint unsigned DEFAULT NULL,
  `documento_id` bigint unsigned DEFAULT NULL,
  `accion` enum('ver','descargar','exportar','imprimir') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contexto` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_accesos_log_empresa_id_created_at_index` (`empresa_id`,`created_at`),
  KEY `dc_accesos_log_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `dc_accesos_log_apartado_id_index` (`apartado_id`),
  KEY `dc_accesos_log_concepto_id_index` (`concepto_id`),
  KEY `dc_accesos_log_documento_id_index` (`documento_id`),
  CONSTRAINT `dc_accesos_log_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_accesos_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_accionistas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_accionistas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `nombre_razon_social` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `porcentaje` decimal(5,2) NOT NULL,
  `num_acciones` int unsigned DEFAULT NULL,
  `tipo_serie` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_alta` date NOT NULL,
  `fecha_baja` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_accionistas_empresa_id_index` (`empresa_id`),
  CONSTRAINT `dc_accionistas_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_actas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_actas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `tipo` enum('asamblea_ordinaria','asamblea_extraordinaria','consejo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `folio` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resumen` text COLLATE utf8mb4_unicode_ci,
  `protocolizada` tinyint(1) NOT NULL DEFAULT '0',
  `documento_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_actas_documento_id_foreign` (`documento_id`),
  KEY `dc_actas_empresa_id_tipo_index` (`empresa_id`,`tipo`),
  CONSTRAINT `dc_actas_documento_id_foreign` FOREIGN KEY (`documento_id`) REFERENCES `dc_documentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_actas_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_activos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_activos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `categoria` enum('torre','antena','posteria','fibra','red_troncal','equipo_transmision','vehiculo','computo','herramienta','centro_distribucion','bodega','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `identificador` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'serie, placa o código de inventario',
  `ubicacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'referencia textual del sitio',
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `fecha_adquisicion` date DEFAULT NULL,
  `valor_adquisicion` decimal(12,2) DEFAULT NULL,
  `estado` enum('activo','baja','mantenimiento') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `responsable_user_id` bigint unsigned DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_activos_responsable_user_id_foreign` (`responsable_user_id`),
  KEY `dc_activos_empresa_id_categoria_index` (`empresa_id`,`categoria`),
  KEY `dc_activos_estado_index` (`estado`),
  CONSTRAINT `dc_activos_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_activos_responsable_user_id_foreign` FOREIGN KEY (`responsable_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_activos_digitales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_activos_digitales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `tipo` enum('sistema','plataforma','software_propio','servidor','base_datos','app_movil','sitio_web','panel','licencia','respaldo','dominio','correo_corporativo','red_social','plataforma_marketing') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `proveedor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titular` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titularidad_estado` enum('regular','titularidad_a_regularizar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'regular',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_alta` date DEFAULT NULL,
  `vigencia_fin` date DEFAULT NULL COMMENT 'vencimiento de dominio/licencia, si aplica',
  `costo_periodico` decimal(12,2) DEFAULT NULL,
  `periodicidad_costo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsable_user_id` bigint unsigned DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `revocado_at` timestamp NULL DEFAULT NULL,
  `revocado_por_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_activos_digitales_responsable_user_id_foreign` (`responsable_user_id`),
  KEY `dc_activos_digitales_empresa_id_tipo_index` (`empresa_id`,`tipo`),
  KEY `dc_activos_digitales_titularidad_estado_index` (`titularidad_estado`),
  KEY `dc_activos_digitales_revocado_por_user_id_foreign` (`revocado_por_user_id`),
  CONSTRAINT `dc_activos_digitales_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_activos_digitales_responsable_user_id_foreign` FOREIGN KEY (`responsable_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_activos_digitales_revocado_por_user_id_foreign` FOREIGN KEY (`revocado_por_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_apartados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_apartados` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `clave` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `icono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dc_apartados_empresa_id_clave_unique` (`empresa_id`,`clave`),
  KEY `dc_apartados_empresa_id_orden_index` (`empresa_id`,`orden`),
  CONSTRAINT `dc_apartados_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_capital_variaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_capital_variaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('aumento','disminucion') COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `capital_resultante` decimal(14,2) NOT NULL,
  `nota` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_capital_variaciones_empresa_id_index` (`empresa_id`),
  CONSTRAINT `dc_capital_variaciones_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_conceptos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_conceptos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `apartado_id` bigint unsigned NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_resolvedor` enum('sistema','documento','plantilla','grafica','inventario','pendiente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `config` json DEFAULT NULL,
  `plantilla_id` bigint unsigned DEFAULT NULL,
  `obligatorio` tinyint(1) NOT NULL DEFAULT '0',
  `rol_responsable` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `periodicidad_revision` enum('mensual','trimestral','semestral','anual','evento') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_legal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confidencialidad` enum('interna','restringida','critica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interna',
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dc_conceptos_empresa_id_slug_unique` (`empresa_id`,`slug`),
  KEY `dc_conceptos_apartado_id_orden_index` (`apartado_id`,`orden`),
  KEY `dc_conceptos_empresa_id_activo_obligatorio_index` (`empresa_id`,`activo`,`obligatorio`),
  CONSTRAINT `dc_conceptos_apartado_id_foreign` FOREIGN KEY (`apartado_id`) REFERENCES `dc_apartados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_conceptos_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_concesion_pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_concesion_pagos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `concesion_id` bigint unsigned NOT NULL,
  `concepto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `periodo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` decimal(12,2) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `comprobante_documento_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_concesion_pagos_concesion_id_foreign` (`concesion_id`),
  KEY `dc_concesion_pagos_comprobante_documento_id_foreign` (`comprobante_documento_id`),
  KEY `dc_concesion_pagos_empresa_id_concesion_id_index` (`empresa_id`,`concesion_id`),
  KEY `dc_concesion_pagos_fecha_vencimiento_index` (`fecha_vencimiento`),
  CONSTRAINT `dc_concesion_pagos_comprobante_documento_id_foreign` FOREIGN KEY (`comprobante_documento_id`) REFERENCES `dc_documentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_concesion_pagos_concesion_id_foreign` FOREIGN KEY (`concesion_id`) REFERENCES `dc_concesiones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_concesion_pagos_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_concesiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_concesiones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `tipo` enum('titulo_concesion','permiso','autorizacion','derecho_via','convenio_infraestructura','arrendamiento_sitio') COLLATE utf8mb4_unicode_ci NOT NULL,
  `autoridad` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `folio` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objeto` text COLLATE utf8mb4_unicode_ci,
  `fecha_otorgamiento` date DEFAULT NULL,
  `vigencia_fin` date NOT NULL,
  `obligaciones` text COLLATE utf8mb4_unicode_ci,
  `responsable_user_id` bigint unsigned NOT NULL,
  `estado_tramite` enum('vigente','en_renovacion','en_tramite','vencido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vigente',
  `documento_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_concesiones_responsable_user_id_foreign` (`responsable_user_id`),
  KEY `dc_concesiones_documento_id_foreign` (`documento_id`),
  KEY `dc_concesiones_empresa_id_estado_tramite_index` (`empresa_id`,`estado_tramite`),
  KEY `dc_concesiones_empresa_id_vigencia_fin_index` (`empresa_id`,`vigencia_fin`),
  KEY `dc_concesiones_tipo_index` (`tipo`),
  CONSTRAINT `dc_concesiones_documento_id_foreign` FOREIGN KEY (`documento_id`) REFERENCES `dc_documentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_concesiones_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_concesiones_responsable_user_id_foreign` FOREIGN KEY (`responsable_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_contratos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_contratos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `tipo` enum('cliente','proveedor','convenio_comercial','arrendamiento','servicios','mantenimiento','suministro','interconexion') COLLATE utf8mb4_unicode_ci NOT NULL,
  `contraparte` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `objeto` text COLLATE utf8mb4_unicode_ci,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `monto` decimal(14,2) DEFAULT NULL,
  `documento_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_contratos_documento_id_foreign` (`documento_id`),
  KEY `dc_contratos_empresa_id_tipo_index` (`empresa_id`,`tipo`),
  CONSTRAINT `dc_contratos_documento_id_foreign` FOREIGN KEY (`documento_id`) REFERENCES `dc_documentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_contratos_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_documento_versiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_documento_versiones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `documento_id` bigint unsigned NOT NULL,
  `version` int unsigned NOT NULL,
  `archivo_uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta_archivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo_nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bytes` bigint unsigned NOT NULL DEFAULT '0',
  `hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 del archivo de ESTA versión',
  `subido_por` bigint unsigned DEFAULT NULL,
  `nota_cambio` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dc_documento_versiones_documento_id_version_unique` (`documento_id`,`version`),
  KEY `dc_documento_versiones_empresa_id_foreign` (`empresa_id`),
  KEY `dc_documento_versiones_subido_por_foreign` (`subido_por`),
  KEY `dc_documento_versiones_archivo_uuid_index` (`archivo_uuid`),
  CONSTRAINT `dc_documento_versiones_documento_id_foreign` FOREIGN KEY (`documento_id`) REFERENCES `dc_documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_documento_versiones_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_documento_versiones_subido_por_foreign` FOREIGN KEY (`subido_por`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_documentos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `concepto_id` bigint unsigned NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta_archivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo_nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bytes` bigint unsigned NOT NULL DEFAULT '0',
  `hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 del archivo',
  `version_actual` int unsigned NOT NULL DEFAULT '1',
  `vigencia_inicio` date DEFAULT NULL,
  `vigencia_fin` date DEFAULT NULL,
  `folio` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contraparte` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confidencialidad` enum('interna','restringida','critica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interna',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `subido_por` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_documentos_concepto_id_foreign` (`concepto_id`),
  KEY `dc_documentos_subido_por_foreign` (`subido_por`),
  KEY `dc_documentos_empresa_id_concepto_id_index` (`empresa_id`,`concepto_id`),
  KEY `dc_documentos_vigencia_fin_index` (`vigencia_fin`),
  KEY `dc_documentos_archivo_uuid_index` (`archivo_uuid`),
  CONSTRAINT `dc_documentos_concepto_id_foreign` FOREIGN KEY (`concepto_id`) REFERENCES `dc_conceptos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_documentos_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_documentos_subido_por_foreign` FOREIGN KEY (`subido_por`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_empresas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `razon_social` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_comercial` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rfc` varchar(13) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regimen_fiscal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_constitucion` date DEFAULT NULL,
  `domicilio_fiscal` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_empresas_activo_index` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_entrega_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_entrega_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entrega_id` bigint unsigned NOT NULL,
  `apartado_clave` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `concepto_id` bigint unsigned DEFAULT NULL,
  `nivel_detalle` enum('agregado','detallado','integro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detallado',
  `archivo_incluido` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ruta relativa dentro del ZIP',
  `estado_resuelto` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'snapshot de ResultadoConcepto::estado al generar',
  `metricas` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_entrega_items_entrega_id_apartado_clave_index` (`entrega_id`,`apartado_clave`),
  KEY `dc_entrega_items_concepto_id_index` (`concepto_id`),
  CONSTRAINT `dc_entrega_items_concepto_id_foreign` FOREIGN KEY (`concepto_id`) REFERENCES `dc_conceptos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_entrega_items_entrega_id_foreign` FOREIGN KEY (`entrega_id`) REFERENCES `dc_entregas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_entregas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_entregas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `solicitud_id` bigint unsigned DEFAULT NULL,
  `generado_por_user_id` bigint unsigned DEFAULT NULL,
  `fecha_entrega` timestamp NULL DEFAULT NULL,
  `ruta_zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hash_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SHA-256 del ZIP ya cerrado',
  `ruta_acta_pdf` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indice` json DEFAULT NULL COMMENT 'resumen apartado/concepto/nivel/estado incluido en el paquete',
  `estado` enum('generando','generada','fallida') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generando',
  `error` text COLLATE utf8mb4_unicode_ci,
  `descargas_zip_count` int unsigned NOT NULL DEFAULT '0',
  `descargas_acta_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_entregas_generado_por_user_id_foreign` (`generado_por_user_id`),
  KEY `dc_entregas_empresa_id_estado_index` (`empresa_id`,`estado`),
  KEY `dc_entregas_solicitud_id_index` (`solicitud_id`),
  CONSTRAINT `dc_entregas_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_entregas_generado_por_user_id_foreign` FOREIGN KEY (`generado_por_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_entregas_solicitud_id_foreign` FOREIGN KEY (`solicitud_id`) REFERENCES `dc_solicitudes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_inventario_accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_inventario_accesos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `tipo` enum('cuenta_bancaria','linea_credito','cuenta_inversion','terminal_pv','usuario_sistema','firma_autorizada','token') COLLATE utf8mb4_unicode_ci NOT NULL,
  `institucion_o_sistema` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identificador_publico` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titular` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secreto_existe` tinyint(1) NOT NULL DEFAULT '1',
  `custodio_user_id` bigint unsigned DEFAULT NULL,
  `ubicacion_resguardo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_ultima_revision` date DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `revocado_at` timestamp NULL DEFAULT NULL,
  `revocado_por_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_inventario_accesos_custodio_user_id_foreign` (`custodio_user_id`),
  KEY `dc_inventario_accesos_empresa_id_tipo_index` (`empresa_id`,`tipo`),
  KEY `dc_inventario_accesos_revocado_por_user_id_foreign` (`revocado_por_user_id`),
  CONSTRAINT `dc_inventario_accesos_custodio_user_id_foreign` FOREIGN KEY (`custodio_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_inventario_accesos_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_inventario_accesos_revocado_por_user_id_foreign` FOREIGN KEY (`revocado_por_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_pendientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_pendientes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `concepto_id` bigint unsigned NOT NULL,
  `responsable_user_id` bigint unsigned DEFAULT NULL,
  `fecha_compromiso` date DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','entregado','no_aplica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `comentarios` text COLLATE utf8mb4_unicode_ci,
  `recordatorio_enviado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_pendientes_empresa_id_estado_index` (`empresa_id`,`estado`),
  KEY `dc_pendientes_responsable_user_id_estado_index` (`responsable_user_id`,`estado`),
  KEY `dc_pendientes_concepto_id_index` (`concepto_id`),
  CONSTRAINT `dc_pendientes_concepto_id_foreign` FOREIGN KEY (`concepto_id`) REFERENCES `dc_conceptos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_pendientes_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dc_pendientes_responsable_user_id_foreign` FOREIGN KEY (`responsable_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_poderes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_poderes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `apoderado` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_poder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alcance` text COLLATE utf8mb4_unicode_ci,
  `fecha_otorgamiento` date NOT NULL,
  `vigencia_fin` date DEFAULT NULL,
  `revocado` tinyint(1) NOT NULL DEFAULT '0',
  `documento_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_poderes_documento_id_foreign` (`documento_id`),
  KEY `dc_poderes_empresa_id_index` (`empresa_id`),
  CONSTRAINT `dc_poderes_documento_id_foreign` FOREIGN KEY (`documento_id`) REFERENCES `dc_documentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_poderes_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_proveedor_clasificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_proveedor_clasificaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `clasificacion` enum('telecomunicaciones','tecnologia','contratistas','programadores','desarrolladores','capacitadores','asesores','contadores','despachos','estrategicos') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dc_proveedor_clasificaciones_supplier_id_clasificacion_unique` (`supplier_id`,`clasificacion`),
  KEY `dc_proveedor_clasificaciones_clasificacion_index` (`clasificacion`),
  CONSTRAINT `dc_proveedor_clasificaciones_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_solicitudes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_solicitudes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` bigint unsigned NOT NULL,
  `solicitante` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caracter` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'accionista, socio, autoridad, etc. — texto libre',
  `fecha_recepcion` date NOT NULL,
  `plazo_dias` int unsigned DEFAULT NULL,
  `fecha_limite` date DEFAULT NULL,
  `apartados` json NOT NULL COMMENT 'claves I..XIV solicitadas',
  `estado` enum('recibida','en_preparacion','entregada','rechazada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'recibida',
  `documento_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'carta de solicitud escaneada, si la hay',
  `documento_nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento_mime` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento_bytes` bigint unsigned DEFAULT NULL,
  `creado_por_user_id` bigint unsigned DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_solicitudes_creado_por_user_id_foreign` (`creado_por_user_id`),
  KEY `dc_solicitudes_empresa_id_estado_index` (`empresa_id`,`estado`),
  KEY `dc_solicitudes_fecha_limite_index` (`fecha_limite`),
  CONSTRAINT `dc_solicitudes_creado_por_user_id_foreign` FOREIGN KEY (`creado_por_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_solicitudes_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `dc_empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deal_crms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deal_crms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint unsigned NOT NULL,
  `total` bigint NOT NULL,
  `created` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_close` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('algo','algo1','algo2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` bigint NOT NULL,
  `lead_id` bigint NOT NULL,
  `last_update_at` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_update_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connected_quote_id` bigint NOT NULL,
  `customers_deal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','won','lost','total') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deal_crms_crm_id_foreign` (`crm_id`),
  CONSTRAINT `deal_crms_crm_id_foreign` FOREIGN KEY (`crm_id`) REFERENCES `crms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `default_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `default_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4428 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `demo_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demo_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deployment_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deployment_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `release_id` bigint unsigned DEFAULT NULL,
  `triggered_by` bigint unsigned NOT NULL,
  `status` enum('pending','running','success','failed','rolled_back') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `steps` json DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `duration_seconds` int DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `rollback_to_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deployment_logs_release_id_foreign` (`release_id`),
  KEY `deployment_logs_triggered_by_foreign` (`triggered_by`),
  KEY `deployment_logs_status_created_at_index` (`status`,`created_at`),
  CONSTRAINT `deployment_logs_release_id_foreign` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deployment_logs_triggered_by_foreign` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `discount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discounts_seller_id_foreign` (`seller_id`),
  KEY `discounts_created_by_foreign` (`created_by`),
  CONSTRAINT `discounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discounts_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discounts_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discounts_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `discount_id` bigint unsigned NOT NULL,
  `rule_id` bigint unsigned NOT NULL,
  `sale_id` bigint unsigned NOT NULL,
  `discount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discounts_sales_discount_id_foreign` (`discount_id`),
  KEY `discounts_sales_rule_id_foreign` (`rule_id`),
  KEY `discounts_sales_sale_id_foreign` (`sale_id`),
  CONSTRAINT `discounts_sales_discount_id_foreign` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discounts_sales_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `history_general_configuration_rule` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discounts_sales_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `client_main_information` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `distribution_commission_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distribution_commission_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned NOT NULL,
  `duration` int NOT NULL,
  `initial` double(8,2) NOT NULL,
  `percent` double(8,2) NOT NULL,
  `iva` double(8,2) NOT NULL,
  `service` double(8,2) NOT NULL,
  `total_amount` double(8,2) NOT NULL,
  `total_amount_per_week` double(8,2) NOT NULL,
  `total_discount` double(8,2) NOT NULL,
  `discount_per_week` double(8,2) NOT NULL,
  `amount_per_week` double(8,2) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `distribution_commission_sales_sale_id_foreign` (`sale_id`),
  CONSTRAINT `distribution_commission_sales_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `client_main_information` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `distribution_commission_sales_amount`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distribution_commission_sales_amount` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `distribution_id` bigint unsigned NOT NULL,
  `month` int NOT NULL,
  `year` int NOT NULL,
  `amount` double(8,2) NOT NULL,
  `is_payment` tinyint(1) NOT NULL DEFAULT '0',
  `initial` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `distribution_commission_sales_amount_distribution_id_foreign` (`distribution_id`),
  CONSTRAINT `distribution_commission_sales_amount_distribution_id_foreign` FOREIGN KEY (`distribution_id`) REFERENCES `distribution_commission_sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `districts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `districts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `added_by_id` bigint DEFAULT NULL,
  `show` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1900 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_crms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_crms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `added_by_id` bigint DEFAULT NULL,
  `show` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_crms_crm_id_foreign` (`crm_id`),
  CONSTRAINT `document_crms_crm_id_foreign` FOREIGN KEY (`crm_id`) REFERENCES `crms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `html` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_type_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_type_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documentation_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentation_contents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `documentation_submenu_id` bigint unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documentation_contents_created_by_foreign` (`created_by`),
  KEY `documentation_contents_updated_by_foreign` (`updated_by`),
  KEY `documentation_contents_documentation_submenu_id_index` (`documentation_submenu_id`),
  CONSTRAINT `documentation_contents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documentation_contents_documentation_submenu_id_foreign` FOREIGN KEY (`documentation_submenu_id`) REFERENCES `documentation_submenus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documentation_contents_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documentation_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentation_menus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documentation_menus_title_unique` (`title`),
  KEY `documentation_menus_created_by_foreign` (`created_by`),
  KEY `documentation_menus_updated_by_foreign` (`updated_by`),
  KEY `documentation_menus_title_index` (`title`),
  CONSTRAINT `documentation_menus_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documentation_menus_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documentation_submenus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentation_submenus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `documentation_menu_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_title_per_menu` (`documentation_menu_id`,`title`),
  KEY `documentation_submenus_created_by_foreign` (`created_by`),
  KEY `documentation_submenus_updated_by_foreign` (`updated_by`),
  KEY `documentation_submenus_title_index` (`title`),
  KEY `documentation_submenus_documentation_menu_id_index` (`documentation_menu_id`),
  CONSTRAINT `documentation_submenus_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documentation_submenus_documentation_menu_id_foreign` FOREIGN KEY (`documentation_menu_id`) REFERENCES `documentation_menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documentation_submenus_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `duration_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `duration_contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mail_mailer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_host` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_port` int DEFAULT NULL,
  `mail_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_encryption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_from_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_from_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `limit_per_hour` int NOT NULL DEFAULT '0',
  `limit_per_minute` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_manual_chapters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_manual_chapters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` int unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_manual_chapters_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_manual_section_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_manual_section_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section_id` bigint unsigned NOT NULL,
  `version_number` int unsigned NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_manual_section_versions_section_id_version_number_unique` (`section_id`,`version_number`),
  CONSTRAINT `empresa_manual_section_versions_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `empresa_manual_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_manual_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_manual_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chapter_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` int unsigned NOT NULL DEFAULT '0',
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `published_version_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_manual_sections_chapter_id_slug_unique` (`chapter_id`,`slug`),
  KEY `empresa_manual_sections_published_version_id_foreign` (`published_version_id`),
  CONSTRAINT `empresa_manual_sections_chapter_id_foreign` FOREIGN KEY (`chapter_id`) REFERENCES `empresa_manual_chapters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `empresa_manual_sections_published_version_id_foreign` FOREIGN KEY (`published_version_id`) REFERENCES `empresa_manual_section_versions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `enrollment_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollment_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` enum('whatsapp','email') COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollment_links_token_unique` (`token`),
  KEY `enrollment_links_client_id_used_at_index` (`client_id`,`used_at`),
  KEY `enrollment_links_token_index` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `equipment_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipment_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `input_id` bigint unsigned NOT NULL,
  `output_id` bigint unsigned NOT NULL,
  `map_link_id` bigint unsigned DEFAULT NULL,
  `fiber_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `evaluaciones_empresariales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evaluaciones_empresariales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre_contacto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empresa` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_contacto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_contacto` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `puntaje_total` smallint unsigned NOT NULL DEFAULT '0',
  `categoria` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_recomendado` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `respuestas_json` json NOT NULL,
  `score_criticidad` smallint unsigned NOT NULL DEFAULT '0',
  `score_redundancia` smallint unsigned NOT NULL DEFAULT '0',
  `score_ancho_banda` smallint unsigned NOT NULL DEFAULT '0',
  `score_sla` smallint unsigned NOT NULL DEFAULT '0',
  `canal_origen` enum('whatsapp','email','directo','vendedor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'directo',
  `vendedor_id` bigint unsigned DEFAULT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `token_publico` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `evaluaciones_empresariales_token_publico_unique` (`token_publico`),
  KEY `evaluaciones_empresariales_categoria_index` (`categoria`),
  KEY `evaluaciones_empresariales_vendedor_id_index` (`vendedor_id`),
  KEY `evaluaciones_empresariales_lead_id_index` (`lead_id`),
  KEY `evaluaciones_empresariales_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fibers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fibers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` int NOT NULL,
  `color_id` bigint unsigned NOT NULL,
  `buffer_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `field_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `field_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint NOT NULL,
  `include` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'false if not include in ComponentFormDefault',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `placeholder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `options` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `search` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'if type select and you want get values by ajax request',
  `inputGroup` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inputGroupEnd` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `depend` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if type select-component-with-input',
  `inputs_depend` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `position` bigint DEFAULT NULL,
  `disabled` tinyint(1) DEFAULT NULL,
  `default_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partition` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rule` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `step` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_field` tinyint(1) NOT NULL DEFAULT '0',
  `class_col` enum('full','partial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `class_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
  `class_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'col-sm-12 col-md-9',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1379 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `field_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `field_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint NOT NULL,
  `preview` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Se pone en true cuando se sube un archivo y aun no se ha salvado.',
  `fileable_id` bigint NOT NULL,
  `fileable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2059 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `since` date NOT NULL,
  `until` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_assignments_vehicle_id_until_index` (`vehicle_id`,`until`),
  CONSTRAINT `fleet_assignments_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_device_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_device_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint unsigned NOT NULL,
  `device_id` bigint unsigned NOT NULL,
  `event_type` enum('ignition_on','ignition_off','sos','low_battery','harsh_brake','harsh_acceleration','over_speed','geofence_enter','geofence_exit','no_signal','back_online') COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_device_events_device_id_foreign` (`device_id`),
  KEY `fleet_device_events_vehicle_id_occurred_at_index` (`vehicle_id`,`occurred_at`),
  CONSTRAINT `fleet_device_events_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `fleet_devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fleet_device_events_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint unsigned DEFAULT NULL,
  `imei` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` enum('ruptela','concox','gt06_generic','mock','phone') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mock',
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sim_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sim_carrier` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','no_signal','unregistered','pending_first_connection') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `total_pings` int unsigned NOT NULL DEFAULT '0',
  `installed_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fleet_devices_imei_unique` (`imei`),
  KEY `fleet_devices_vehicle_id_foreign` (`vehicle_id`),
  KEY `fleet_devices_status_index` (`status`),
  CONSTRAINT `fleet_devices_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_document_alert_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_document_alert_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint unsigned NOT NULL,
  `threshold` smallint NOT NULL,
  `days_until` smallint NOT NULL,
  `channel` enum('email','whatsapp') COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('sent','failed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_doc_threshold_channel_recipient` (`document_id`,`threshold`,`channel`,`recipient`),
  KEY `idx_doc_alert_document_id` (`document_id`),
  CONSTRAINT `fleet_document_alert_log_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `fleet_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_document_ocr_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_document_ocr_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint unsigned DEFAULT NULL,
  `vehicle_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ok` tinyint(1) NOT NULL DEFAULT '0',
  `needs_review` tinyint(1) NOT NULL DEFAULT '1',
  `mime` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bytes` int unsigned DEFAULT NULL,
  `file_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fields` json DEFAULT NULL,
  `unreadable` json DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `provider` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_document_ocr_runs_document_id_index` (`document_id`),
  KEY `fleet_document_ocr_runs_vehicle_id_index` (`vehicle_id`),
  KEY `fleet_document_ocr_runs_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `fleet_document_ocr_runs_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `fleet_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint unsigned DEFAULT NULL,
  `driver_id` bigint unsigned DEFAULT NULL,
  `document_type` enum('circulation_card','insurance_policy','tenencia','verification','operator_license','special_permit','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `folio_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ocr_status` enum('no_ejecutado','ok','baja_confianza','fallido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no_ejecutado',
  `ocr_needs_review` tinyint(1) NOT NULL DEFAULT '0',
  `ocr_fields` json DEFAULT NULL,
  `ocr_ran_at` timestamp NULL DEFAULT NULL,
  `ocr_reviewed_at` timestamp NULL DEFAULT NULL,
  `ocr_reviewed_by` bigint unsigned DEFAULT NULL,
  `alert_30_days` tinyint(1) NOT NULL DEFAULT '1',
  `alert_7_days` tinyint(1) NOT NULL DEFAULT '1',
  `alert_1_day` tinyint(1) NOT NULL DEFAULT '1',
  `alert_same_day` tinyint(1) NOT NULL DEFAULT '0',
  `alert_channels` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_documents_vehicle_id_document_type_index` (`vehicle_id`,`document_type`),
  KEY `fleet_documents_expiration_date_index` (`expiration_date`),
  KEY `fleet_documents_driver_id_foreign` (`driver_id`),
  CONSTRAINT `fleet_documents_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fleet_documents_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_driver_push_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_driver_push_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` enum('android','ios') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'android',
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fleet_driver_push_tokens_token_unique` (`token`),
  KEY `fleet_driver_push_tokens_user_id_index` (`user_id`),
  CONSTRAINT `fleet_driver_push_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_fuel_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_fuel_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint unsigned NOT NULL,
  `refuel_date` date NOT NULL,
  `liters` decimal(7,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `km_at_refuel` int unsigned DEFAULT NULL,
  `octane` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `station_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_fuel_log_vehicle_id_refuel_date_index` (`vehicle_id`,`refuel_date`),
  CONSTRAINT `fleet_fuel_log_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_geofence_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_geofence_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint unsigned NOT NULL,
  `geofence_id` bigint unsigned NOT NULL,
  `event_type` enum('enter','exit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `position_id` bigint unsigned DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fleet_geofence_events_position_id_foreign` (`position_id`),
  KEY `fleet_geofence_events_vehicle_id_occurred_at_index` (`vehicle_id`,`occurred_at`),
  KEY `fleet_geofence_events_geofence_id_index` (`geofence_id`),
  CONSTRAINT `fleet_geofence_events_geofence_id_foreign` FOREIGN KEY (`geofence_id`) REFERENCES `fleet_geofences` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fleet_geofence_events_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `fleet_positions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fleet_geofence_events_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_geofence_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_geofence_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `vehicle_ids` json NOT NULL,
  `geofence_ids` json NOT NULL,
  `event_types` json NOT NULL,
  `time_from` time DEFAULT NULL,
  `time_to` time DEFAULT NULL,
  `days_of_week` json NOT NULL,
  `channels` json NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_geofence_rules_user_id_active_index` (`user_id`,`active`),
  KEY `fleet_geofence_rules_client_id_index` (`client_id`),
  CONSTRAINT `fleet_geofence_rules_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_geofence_vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_geofence_vehicles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `geofence_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fleet_geofence_vehicles_geofence_id_vehicle_id_unique` (`geofence_id`,`vehicle_id`),
  KEY `fleet_geofence_vehicles_vehicle_id_foreign` (`vehicle_id`),
  CONSTRAINT `fleet_geofence_vehicles_geofence_id_foreign` FOREIGN KEY (`geofence_id`) REFERENCES `fleet_geofences` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fleet_geofence_vehicles_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_geofences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_geofences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` enum('enter','exit','both') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `polygon` json NOT NULL,
  `color` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#3388ff',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_geofences_active_index` (`active`),
  KEY `fleet_geofences_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_maintenance_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_maintenance_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `maintenance_id` bigint unsigned NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_maintenance_files_maintenance_id_foreign` (`maintenance_id`),
  CONSTRAINT `fleet_maintenance_files_maintenance_id_foreign` FOREIGN KEY (`maintenance_id`) REFERENCES `fleet_maintenances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_maintenances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_maintenances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint unsigned NOT NULL,
  `type` enum('preventive','corrective','emergency','verification') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'preventive',
  `service_date` date NOT NULL,
  `service_km` int unsigned DEFAULT NULL,
  `works` json DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `provider_id` bigint unsigned DEFAULT NULL,
  `mechanic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `labor_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `parts_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `other_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_cost` decimal(10,2) GENERATED ALWAYS AS (((`labor_cost` + `parts_cost`) + `other_cost`)) STORED,
  `next_service_date` date DEFAULT NULL,
  `next_service_km` int unsigned DEFAULT NULL,
  `is_draft` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_maintenances_provider_id_foreign` (`provider_id`),
  KEY `fleet_maintenances_vehicle_id_service_date_index` (`vehicle_id`,`service_date`),
  CONSTRAINT `fleet_maintenances_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `fleet_providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fleet_maintenances_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_notification_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_notification_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `channel` enum('email','whatsapp','push','sms') COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('queued','sent','failed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fleet_notification_log_user_id_foreign` (`user_id`),
  KEY `fleet_notification_log_event_id_index` (`event_id`),
  KEY `fleet_notification_log_status_index` (`status`),
  CONSTRAINT `fleet_notification_log_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `fleet_geofence_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fleet_notification_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_notification_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned DEFAULT NULL,
  `geofence_id` bigint unsigned DEFAULT NULL,
  `event_types` json NOT NULL,
  `channels` json NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_notification_preferences_vehicle_id_geofence_id_index` (`vehicle_id`,`geofence_id`),
  KEY `fleet_notification_preferences_user_id_index` (`user_id`),
  CONSTRAINT `fleet_notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_photos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint unsigned NOT NULL,
  `photo_type` enum('front','side','rear','dashboard','general') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `taken_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_photos_vehicle_id_foreign` (`vehicle_id`),
  CONSTRAINT `fleet_photos_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_positions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint unsigned NOT NULL,
  `device_id` bigint unsigned NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `speed` decimal(5,2) NOT NULL DEFAULT '0.00',
  `heading` decimal(5,2) NOT NULL DEFAULT '0.00',
  `altitude` decimal(7,2) DEFAULT NULL,
  `satellites` tinyint unsigned DEFAULT NULL,
  `hdop` decimal(4,2) DEFAULT NULL,
  `ignition` tinyint(1) DEFAULT NULL,
  `battery` decimal(5,2) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL,
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_positions_vehicle_id_recorded_at_index` (`vehicle_id`,`recorded_at`),
  KEY `fleet_positions_device_id_index` (`device_id`),
  CONSTRAINT `fleet_positions_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `fleet_devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fleet_positions_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_providers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('workshop','dealer','parts','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'workshop',
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_providers_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_subscription_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_subscription_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `event_type` enum('created','trial_started','trial_reminder','trial_expiring','activated','billed','payment_failed','vehicles_changed','plan_changed','cancelled','expired','reactivated') COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_subscription_events_subscription_id_foreign` (`subscription_id`),
  KEY `fleet_subscription_events_client_id_occurred_at_index` (`client_id`,`occurred_at`),
  KEY `fleet_subscription_events_event_type_index` (`event_type`),
  KEY `fleet_subscription_events_client_id_index` (`client_id`),
  CONSTRAINT `fleet_subscription_events_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `fleet_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `plan` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gestion_plus',
  `status` enum('trial','active','past_due','cancelled','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trial',
  `trial_starts_at` timestamp NULL DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `trial_notified_days` smallint unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicles_count` int unsigned NOT NULL DEFAULT '0',
  `price_per_vehicle` decimal(8,2) NOT NULL DEFAULT '0.00',
  `monthly_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `next_billing_date` date DEFAULT NULL,
  `last_billed_at` date DEFAULT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT '1',
  `data_retention_until` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_subscriptions_status_index` (`status`),
  KEY `fleet_subscriptions_next_billing_date_index` (`next_billing_date`),
  KEY `fleet_subscriptions_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_vehicles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned DEFAULT NULL,
  `plates` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` smallint unsigned DEFAULT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vin` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motor_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_type` enum('car','pickup','truck','motorcycle','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'car',
  `fuel_type` enum('gasoline','diesel','electric','hybrid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gasoline',
  `tank_capacity_liters` decimal(6,1) DEFAULT NULL,
  `current_km` int unsigned NOT NULL DEFAULT '0',
  `status` enum('active','in_workshop','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `habitual_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `has_gps` tinyint(1) NOT NULL DEFAULT '0',
  `gps_brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_imei` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_sim` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_carrier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_vehicles_client_id_status_index` (`client_id`,`status`),
  KEY `fleet_vehicles_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `frequency_commands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frequency_commands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_time` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `frequency_estimated_dedicated_times`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frequency_estimated_dedicated_times` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `general_accounting_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `general_accounting_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `general_accounting_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `general_accounting_expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Gasto Manual',
  `created_by` bigint unsigned NOT NULL,
  `operation_date` datetime DEFAULT NULL,
  `transaction_id` bigint unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `operation_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `general_accounting_expenses_category_index` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `general_accounting_incomes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `general_accounting_incomes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Ingreso Manual',
  `created_by` bigint unsigned NOT NULL,
  `operation_date` datetime DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `transaction_id` bigint unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `operation_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `general_accounting_incomes_category_index` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `general_accounting_operations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `general_accounting_operations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `general_accounting_category_id` bigint unsigned NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `is_recurrent` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `operation_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `general_accounting_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `general_accounting_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `general_configuration_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `general_configuration_rule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `installation_cost` decimal(8,2) NOT NULL DEFAULT '0.00',
  `iva` decimal(8,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `general_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `general_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('Alta','Media','Baja') COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `row_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `general_notifications_user_id_foreign` (`user_id`),
  CONSTRAINT `general_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_general_configuration_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_general_configuration_rule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` bigint unsigned NOT NULL,
  `data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `history_general_configuration_rule_rule_id_foreign` (`rule_id`),
  CONSTRAINT `history_general_configuration_rule_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `general_configuration_rule` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_sellers_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_sellers_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint unsigned NOT NULL,
  `rule_id` bigint unsigned NOT NULL,
  `data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `history_sellers_rules_seller_id_foreign` (`seller_id`),
  KEY `history_sellers_rules_rule_id_foreign` (`rule_id`),
  CONSTRAINT `history_sellers_rules_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `commissions_rules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `history_sellers_rules_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_bot_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_bot_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `voice` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nova',
  `language` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'es-MX',
  `temperature` double(8,2) NOT NULL DEFAULT '0.70',
  `max_turns` int unsigned NOT NULL DEFAULT '12',
  `max_duration_seconds` int unsigned NOT NULL DEFAULT '480',
  `timeout_seconds` int unsigned NOT NULL DEFAULT '15',
  `grupo_timbrado_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `greeting_customer` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Hola [nombre], bienvenido a Meganet. ¿En qué puedo ayudarte hoy?',
  `greeting_lead` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Hola, bienvenido a Meganet Telecomunicaciones. Soy María, tu asistente virtual. ¿En qué puedo ayudarte?',
  `system_prompt` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_bot_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_bot_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `call_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversation_type` enum('customer_support','sales_lead') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sales_lead',
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `duration` int unsigned DEFAULT NULL,
  `transcript` json DEFAULT NULL,
  `problem_category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT '0',
  `escalated` tinyint(1) NOT NULL DEFAULT '0',
  `escalated_to` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transferred_at` datetime DEFAULT NULL,
  `transferred_extension` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lead_data` json DEFAULT NULL,
  `cost_usd` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `status` enum('completed','transferred','failed','timeout') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ia_bot_conversations_call_id_unique` (`call_id`),
  KEY `ia_bot_conversations_phone_number_index` (`phone_number`),
  KEY `ia_bot_conversations_customer_id_index` (`customer_id`),
  KEY `ia_bot_conversations_call_id_index` (`call_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_bot_knowledge_base`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_bot_knowledge_base` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `problem` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keywords` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solution_steps` json NOT NULL,
  `escalate_if_fails` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ia_bot_knowledge_base_category_index` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_bot_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_bot_leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interest_service` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interest_plan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to_user_id` bigint unsigned DEFAULT NULL,
  `status` enum('new','contacted','interested','converted','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `notes` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ia_bot_leads_conversation_id_foreign` (`conversation_id`),
  CONSTRAINT `ia_bot_leads_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `ia_bot_conversations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_chat_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_chat_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Chat IA',
  `messages` json DEFAULT NULL,
  `context` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_conversaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_conversaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ia_proyecto_id` bigint unsigned DEFAULT NULL,
  `ia_proveedor_id` bigint unsigned DEFAULT NULL,
  `modelo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ultimo_mensaje_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ia_conversaciones_user_id_index` (`user_id`),
  KEY `ia_conversaciones_ia_proyecto_id_index` (`ia_proyecto_id`),
  KEY `ia_conversaciones_ia_proveedor_id_index` (`ia_proveedor_id`),
  CONSTRAINT `ia_conversaciones_ia_proveedor_id_foreign` FOREIGN KEY (`ia_proveedor_id`) REFERENCES `ia_proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ia_conversaciones_ia_proyecto_id_foreign` FOREIGN KEY (`ia_proyecto_id`) REFERENCES `ia_proyectos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_memoria_proyecto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_memoria_proyecto` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('hecho','avance','decision','pendiente','error_resuelto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hecho',
  `contenido` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulo_relacionado` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relevancia` tinyint unsigned NOT NULL DEFAULT '5' COMMENT '1-10',
  `obsoleto` tinyint(1) NOT NULL DEFAULT '0',
  `ia_conversacion_id` bigint unsigned DEFAULT NULL COMMENT 'Conversación de la que se extrajo (NULL si es manual)',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_memoria_vigentes` (`obsoleto`,`relevancia`),
  KEY `ia_memoria_proyecto_tipo_index` (`tipo`),
  KEY `ia_memoria_proyecto_ia_conversacion_id_index` (`ia_conversacion_id`),
  CONSTRAINT `ia_memoria_proyecto_ia_conversacion_id_foreign` FOREIGN KEY (`ia_conversacion_id`) REFERENCES `ia_conversaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_mensajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_mensajes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ia_conversacion_id` bigint unsigned NOT NULL,
  `rol` enum('user','assistant','system') COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenido` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagenes` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `tokens_input` int unsigned DEFAULT NULL,
  `tokens_output` int unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ia_mensajes_ia_conversacion_id_index` (`ia_conversacion_id`),
  CONSTRAINT `ia_mensajes_ia_conversacion_id_foreign` FOREIGN KEY (`ia_conversacion_id`) REFERENCES `ia_conversaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_message_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_message_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ia_mensaje_id` bigint unsigned NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_mime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanio` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ia_message_files_ia_mensaje_id_index` (`ia_mensaje_id`),
  CONSTRAINT `ia_message_files_ia_mensaje_id_foreign` FOREIGN KEY (`ia_mensaje_id`) REFERENCES `ia_mensajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_notas_proyecto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_notas_proyecto` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenido` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `importante` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ia_notas_proyecto_importante_index` (`importante`),
  KEY `ia_notas_proyecto_categoria_index` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_prompts_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_prompts_usuario` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `titulo` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenido` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_publico` tinyint(1) NOT NULL DEFAULT '0',
  `usos` int unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ia_prompts_usuario_user_id_index` (`user_id`),
  KEY `ia_prompts_usuario_categoria_index` (`categoria`),
  KEY `ia_prompts_usuario_es_publico_index` (`es_publico`),
  CONSTRAINT `ia_prompts_usuario_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_proveedores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `driver` enum('claude','openai','gemini','openai_compatible','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key` text COLLATE utf8mb4_unicode_ci,
  `endpoint_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo_default` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `soporta_imagenes` tinyint(1) NOT NULL DEFAULT '0',
  `headers_personalizados` json DEFAULT NULL,
  `config_extra` json DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `estado` enum('conectado','error','sin_configurar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sin_configurar',
  `ultimo_error` text COLLATE utf8mb4_unicode_ci,
  `probado_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_proyectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_proyectos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_default` tinyint(1) NOT NULL DEFAULT '0',
  `user_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ia_proyectos_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_sesiones_trabajo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_sesiones_trabajo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resumen` text COLLATE utf8mb4_unicode_ci,
  `archivos_modificados` json DEFAULT NULL,
  `prompts_destacados` json DEFAULT NULL,
  `proveedor_ia_usado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inicio_sesion` timestamp NOT NULL,
  `fin_sesion` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ia_sesiones_trabajo_user_id_index` (`user_id`),
  KEY `ia_sesiones_trabajo_inicio_sesion_index` (`inicio_sesion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_tareas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_tareas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('pendiente','en_progreso','completada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `prioridad` enum('alta','media','baja') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `modulo_relacionado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completada_en` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ia_tareas_estado_index` (`estado`),
  KEY `ia_tareas_prioridad_index` (`prioridad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_uso_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_uso_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `ia_conversacion_id` bigint unsigned DEFAULT NULL,
  `ia_mensaje_id` bigint unsigned DEFAULT NULL,
  `ia_proveedor_id` bigint unsigned DEFAULT NULL,
  `proveedor` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokens_input` int unsigned NOT NULL DEFAULT '0',
  `tokens_output` int unsigned NOT NULL DEFAULT '0',
  `tokens_total` int unsigned NOT NULL DEFAULT '0',
  `costo_estimado` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `fecha` date NOT NULL,
  `origen` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ia',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ia_uso_tokens_ia_conversacion_id_foreign` (`ia_conversacion_id`),
  KEY `ia_uso_tokens_ia_mensaje_id_foreign` (`ia_mensaje_id`),
  KEY `ia_uso_tokens_ia_proveedor_id_foreign` (`ia_proveedor_id`),
  KEY `ia_uso_tokens_user_id_fecha_index` (`user_id`,`fecha`),
  KEY `ia_uso_tokens_proveedor_modelo_index` (`proveedor`,`modelo`),
  KEY `ia_uso_tokens_fecha_index` (`fecha`),
  CONSTRAINT `ia_uso_tokens_ia_conversacion_id_foreign` FOREIGN KEY (`ia_conversacion_id`) REFERENCES `ia_conversaciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ia_uso_tokens_ia_mensaje_id_foreign` FOREIGN KEY (`ia_mensaje_id`) REFERENCES `ia_mensajes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ia_uso_tokens_ia_proveedor_id_foreign` FOREIGN KEY (`ia_proveedor_id`) REFERENCES `ia_proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ia_uso_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_export_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_export_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('import','export') COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `format` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `modules_selected` json DEFAULT NULL,
  `fields_selected` json DEFAULT NULL,
  `ai_analysis` json DEFAULT NULL,
  `output_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `records_processed` int unsigned NOT NULL DEFAULT '0',
  `records_failed` int unsigned NOT NULL DEFAULT '0',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `admin_user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_export_logs_type_status_index` (`type`,`status`),
  KEY `import_export_logs_created_at_index` (`created_at`),
  KEY `import_export_logs_job_id_index` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `internet_consumptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `internet_consumptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bytes_in` bigint NOT NULL DEFAULT '0',
  `bytes_out` bigint NOT NULL DEFAULT '0',
  `rate_in_bps` bigint DEFAULT NULL,
  `rate_out_bps` bigint DEFAULT NULL,
  `uptime` int NOT NULL DEFAULT '0',
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mac_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nas_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_recorded` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `internet_consumptions_client_name_index` (`client_name`),
  KEY `internet_consumptions_session_id_index` (`session_id`),
  KEY `internet_consumptions_date_recorded_index` (`date_recorded`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `internets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `internets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `update_description` tinyint(1) DEFAULT '0',
  `price` decimal(20,2) NOT NULL,
  `update_service` tinyint(1) DEFAULT '0',
  `tax_include` tinyint(1) NOT NULL,
  `tax` bigint NOT NULL,
  `download_speed` bigint NOT NULL,
  `upload_speed` bigint NOT NULL,
  `guaranteed_speed_limit` bigint NOT NULL,
  `priority` enum('Baja','Normal','Alta') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aggregation` bigint NOT NULL,
  `burst` bigint NOT NULL,
  `burt_umbral` bigint DEFAULT NULL,
  `burt_time` bigint DEFAULT NULL,
  `rates_to_change` bigint DEFAULT NULL,
  `prepaid_period` enum('Mensual','Diario') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_category` enum('Servicio','Descuento','Pago','Reembolso','Corrección') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_days` bigint DEFAULT NULL,
  `available_when_register_by_social_network` tinyint(1) DEFAULT '0',
  `cost_activation` double(8,2) NOT NULL DEFAULT '0.00',
  `cost_instalation` double(8,2) NOT NULL DEFAULT '0.00',
  `cost_instalation_enable` tinyint(1) DEFAULT '0',
  `promotion_enable` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `init_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_value_fixed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_item_custom_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_item_custom_models` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inventory_item_type_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_item_custom_models_inventory_item_type_id_foreign` (`inventory_item_type_id`),
  CONSTRAINT `inventory_item_custom_models_inventory_item_type_id_foreign` FOREIGN KEY (`inventory_item_type_id`) REFERENCES `inventory_item_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_item_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_item_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_stock_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_item_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` int NOT NULL,
  `order` int NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_item_media_created_by_foreign` (`created_by`),
  CONSTRAINT `inventory_item_media_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_item_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_item_stocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `modelable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelable_id` bigint unsigned NOT NULL,
  `current_stock` int NOT NULL DEFAULT '0',
  `supplier_id` bigint unsigned DEFAULT NULL,
  `supplier_invoice_item_id` bigint unsigned DEFAULT NULL,
  `unit_cost` decimal(15,2) DEFAULT NULL,
  `condition` enum('new','used','damaged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_item_stocks_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `idx_modelable` (`modelable_type`,`modelable_id`),
  KEY `inventory_item_stocks_supplier_id_foreign` (`supplier_id`),
  KEY `inventory_item_stocks_supplier_invoice_item_id_foreign` (`supplier_invoice_item_id`),
  CONSTRAINT `inventory_item_stocks_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_item_stocks_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_item_stocks_supplier_invoice_item_id_foreign` FOREIGN KEY (`supplier_invoice_item_id`) REFERENCES `supplier_invoice_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_item_store_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_item_store_zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `store_zone_id` bigint unsigned NOT NULL,
  `inventory_store_id` bigint unsigned NOT NULL,
  `quantity` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_item_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_item_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `type` enum('tool','material') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tool',
  `categoria` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_item_types_created_by_foreign` (`created_by`),
  CONSTRAINT `inventory_item_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `initial_stock` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_by` bigint unsigned NOT NULL,
  `serial_number_enable` tinyint(1) NOT NULL DEFAULT '0',
  `serial_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_item_enable` tinyint(1) NOT NULL DEFAULT '0',
  `status_item` enum('new','used','repair','warranty','broken','good') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `high_limit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2',
  `middle_limit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inventory_item_type_id` bigint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `inventory_item_custom_model_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_items_created_by_foreign` (`created_by`),
  KEY `inventory_items_inventory_item_type_id_foreign` (`inventory_item_type_id`),
  KEY `inventory_items_inventory_item_custom_model_id_foreign` (`inventory_item_custom_model_id`),
  KEY `inventory_items_serial_number_index` (`serial_number`),
  KEY `inventory_items_serial_number_enable_index` (`serial_number_enable`),
  CONSTRAINT `inventory_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `inventory_items_inventory_item_custom_model_id_foreign` FOREIGN KEY (`inventory_item_custom_model_id`) REFERENCES `inventory_item_custom_models` (`id`),
  CONSTRAINT `inventory_items_inventory_item_type_id_foreign` FOREIGN KEY (`inventory_item_type_id`) REFERENCES `inventory_item_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `type` enum('Entrada','Salida') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `movementable_to_id` bigint unsigned NOT NULL,
  `movementable_to_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `movementable_from_id` bigint unsigned NOT NULL,
  `movementable_from_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','accepted','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `supplier_invoice_id` bigint unsigned DEFAULT NULL,
  `supplier_invoice_item_id` bigint unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_zone_id` bigint unsigned DEFAULT NULL,
  `is_initial` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `inventory_movements_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `inventory_movements_created_by_foreign` (`created_by`),
  KEY `idx_movement_to` (`movementable_to_type`,`movementable_to_id`),
  KEY `idx_movement_from` (`movementable_from_type`,`movementable_from_id`),
  KEY `inventory_movements_supplier_invoice_id_foreign` (`supplier_invoice_id`),
  KEY `inventory_movements_supplier_invoice_item_id_foreign` (`supplier_invoice_item_id`),
  CONSTRAINT `inventory_movements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `inventory_movements_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_movements_supplier_invoice_id_foreign` FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_supplier_invoice_item_id_foreign` FOREIGN KEY (`supplier_invoice_item_id`) REFERENCES `supplier_invoice_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_reservations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `movement_id` bigint unsigned NOT NULL,
  `modelable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelable_id` bigint unsigned NOT NULL,
  `quantity` int NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_reservations_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `inventory_reservations_movement_id_foreign` (`movement_id`),
  KEY `inventory_reservations_modelable_type_modelable_id_index` (`modelable_type`,`modelable_id`),
  CONSTRAINT `inventory_reservations_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_reservations_movement_id_foreign` FOREIGN KEY (`movement_id`) REFERENCES `inventory_movements` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_stores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_emails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `via` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `email_if_error` text COLLATE utf8mb4_unicode_ci,
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cc_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `html` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `modelable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelable_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `subtotal` decimal(10,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_foreign` (`invoice_id`),
  KEY `invoice_items_modelable_type_modelable_id_index` (`modelable_type`,`modelable_id`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `transaction_id` bigint unsigned DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `is_sent` tinyint(1) NOT NULL DEFAULT '0',
  `subtotal` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `pending_balance` decimal(10,2) NOT NULL,
  `status` enum('draft','issued','partially_paid','paid','overdue','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `type` enum('payment','proforma') COLLATE utf8mb4_unicode_ci NOT NULL,
  `needs_review` tinyint(1) NOT NULL DEFAULT '0',
  `review_reason` text COLLATE utf8mb4_unicode_ci,
  `period` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_number_unique` (`number`),
  KEY `invoices_client_id_foreign` (`client_id`),
  KEY `invoices_transaction_id_foreign` (`transaction_id`),
  KEY `invoices_payment_id_foreign` (`payment_id`),
  CONSTRAINT `invoices_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `invoices_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ipv6_bloques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ipv6_bloques` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prefijo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proveedor` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_contrato` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_transito` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_proveedor` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('planificado','activo','en_deprecacion','retirado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planificado',
  `activado_en` timestamp NULL DEFAULT NULL,
  `deprecado_en` timestamp NULL DEFAULT NULL,
  `retirado_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ipv6_bloques_estado_index` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ipv6_dual_stack_cutoff_dry_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ipv6_dual_stack_cutoff_dry_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `ipv4` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ipv6_prefix` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `router_version` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `driver` varchar(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comentario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comandos` json NOT NULL,
  `advertencias` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ipv6_dual_stack_cutoff_dry_runs_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ipv6_plan_segmentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ipv6_plan_segmentos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bloque_id` bigint unsigned NOT NULL,
  `router_id` bigint unsigned NOT NULL,
  `tipo` enum('infraestructura','zona_pppoe','dedicados','enlaces','reserva') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefijo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `longitud_delegacion` tinyint unsigned DEFAULT NULL,
  `vlan_id` int unsigned DEFAULT NULL,
  `interfaz_mikrotik` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `perfil_ppp` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv6_habilitado` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ipv6_plan_segmentos_bloque_id_foreign` (`bloque_id`),
  KEY `ipv6_plan_segmentos_router_id_foreign` (`router_id`),
  KEY `ipv6_plan_segmentos_tipo_index` (`tipo`),
  CONSTRAINT `ipv6_plan_segmentos_bloque_id_foreign` FOREIGN KEY (`bloque_id`) REFERENCES `ipv6_bloques` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ipv6_plan_segmentos_router_id_foreign` FOREIGN KEY (`router_id`) REFERENCES `ipv6_routers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ipv6_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ipv6_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `modo_entrante` enum('deny_default','allow_default') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'deny_default',
  `rate_in_mbps` int unsigned DEFAULT NULL,
  `rate_out_mbps` int unsigned DEFAULT NULL,
  `acl_profile` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `excepciones` json DEFAULT NULL,
  `estado` enum('borrador','activa','retirada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `retirada_en` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ipv6_policies_estado_index` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ipv6_policy_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ipv6_policy_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `ipv6_policy_id` bigint unsigned NOT NULL,
  `estado` enum('pendiente_piloto','activa','revocada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente_piloto',
  `activada_en` timestamp NULL DEFAULT NULL,
  `revocada_en` timestamp NULL DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ipv6_policy_assignments_ipv6_policy_id_foreign` (`ipv6_policy_id`),
  KEY `ipv6_policy_assignments_client_id_estado_index` (`client_id`,`estado`),
  CONSTRAINT `ipv6_policy_assignments_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ipv6_policy_assignments_ipv6_policy_id_foreign` FOREIGN KEY (`ipv6_policy_id`) REFERENCES `ipv6_policies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ipv6_renumbering_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ipv6_renumbering_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bloque_viejo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bloque_nuevo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('planificado','activo','en_deprecacion','retirado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planificado',
  `valid_lifetime_segundos` int unsigned DEFAULT NULL,
  `preferred_lifetime_segundos` int unsigned DEFAULT NULL,
  `deprecacion_inicia_at` timestamp NULL DEFAULT NULL,
  `retiro_programado_at` timestamp NULL DEFAULT NULL,
  `morosos_reconstruido_at` timestamp NULL DEFAULT NULL,
  `historico_preservado` tinyint(1) NOT NULL DEFAULT '1',
  `liberado_en` timestamp NULL DEFAULT NULL,
  `simple_queues_actualizado_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ipv6_renumbering_plans_estado_index` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ipv6_renumbering_transitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ipv6_renumbering_transitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` bigint unsigned NOT NULL,
  `quien_user_id` bigint unsigned DEFAULT NULL,
  `quien_nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuando` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `de_estado` enum('planificado','activo','en_deprecacion','retirado') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `a_estado` enum('planificado','activo','en_deprecacion','retirado') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `ipv6_renumbering_transitions_quien_user_id_foreign` (`quien_user_id`),
  KEY `ipv6_renumbering_transitions_plan_id_cuando_index` (`plan_id`,`cuando`),
  CONSTRAINT `ipv6_renumbering_transitions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `ipv6_renumbering_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ipv6_renumbering_transitions_quien_user_id_foreign` FOREIGN KEY (`quien_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ipv6_routers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ipv6_routers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `host_api` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `puerto_api` int unsigned DEFAULT NULL,
  `version_routeros` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `board_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_familia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version_manual` tinyint(1) NOT NULL DEFAULT '0',
  `ultimo_contacto` timestamp NULL DEFAULT NULL,
  `estado` enum('activo','inactivo','sin_contacto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sin_contacto',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ipv6_routers_estado_index` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jarvis_conversaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jarvis_conversaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sugerencia_clave` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `texto_sugerencia` text COLLATE utf8mb4_unicode_ci,
  `citas` json DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierta',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jarvis_conversaciones_sugerencia_clave_unique` (`sugerencia_clave`),
  KEY `jarvis_conversaciones_item_id_index` (`item_id`),
  KEY `jarvis_conversaciones_estado_index` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jarvis_mensajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jarvis_mensajes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jarvis_conversacion_id` bigint unsigned NOT NULL,
  `rol` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenido` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jarvis_mensajes_jarvis_conversacion_id_index` (`jarvis_conversacion_id`),
  CONSTRAINT `jarvis_mensajes_jarvis_conversacion_id_foreign` FOREIGN KEY (`jarvis_conversacion_id`) REFERENCES `jarvis_conversaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=327 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `list_template_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `list_template_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `list_template_verifications_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `list_template_verifications_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `list_template_verification_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `checks` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `locations_id_index` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL COMMENT 'system_user',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logable_id` bigint NOT NULL,
  `logable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=555 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manual_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `manual_pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section_slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` smallint unsigned NOT NULL DEFAULT '0',
  `related_module` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manual_pages_slug_unique` (`slug`),
  KEY `manual_pages_section_slug_index` (`section_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manual_screenshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `manual_screenshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section_slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_slug` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_placeholder` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `manual_screenshots_section_slug_index` (`section_slug`),
  KEY `manual_screenshots_page_slug_index` (`page_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manual_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `manual_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manual_sections_module_slug_version_unique` (`module_slug`,`version`),
  KEY `manual_sections_module_slug_index` (`module_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `map_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `position_x` smallint NOT NULL DEFAULT '20',
  `position_y` smallint NOT NULL DEFAULT '20',
  `orientation` enum('left','right') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'right',
  `layer_id` bigint unsigned DEFAULT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_devices_layer_id_foreign` (`layer_id`),
  KEY `map_devices_parent_id_foreign` (`parent_id`),
  CONSTRAINT `map_devices_layer_id_foreign` FOREIGN KEY (`layer_id`) REFERENCES `map_layers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `map_devices_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `map_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `map_devices_ports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_devices_ports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in',
  `orientation` enum('left','right') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `device_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `connected` tinyint(1) NOT NULL DEFAULT '0',
  `transfer` smallint DEFAULT NULL,
  `transfer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card` smallint DEFAULT NULL,
  `note` longtext COLLATE utf8mb4_unicode_ci,
  `zone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_devices_ports_device_id_foreign` (`device_id`),
  KEY `map_devices_ports_client_id_foreign` (`client_id`),
  CONSTRAINT `map_devices_ports_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client_main_information` (`id`) ON DELETE CASCADE,
  CONSTRAINT `map_devices_ports_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `map_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `map_devices_ports_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_devices_ports_connections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `from_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_id` bigint unsigned NOT NULL,
  `from_input` smallint NOT NULL DEFAULT '0',
  `to_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_id` bigint unsigned NOT NULL,
  `to_input` smallint NOT NULL DEFAULT '0',
  `from_element` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_element` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_route_id` bigint unsigned DEFAULT NULL,
  `to_route_id` bigint unsigned DEFAULT NULL,
  `connection_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'port-to-port',
  `type` enum('dotted','dashed','default') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width` smallint NOT NULL DEFAULT '4',
  `animate` enum('left','right','default') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `layer_id` bigint unsigned NOT NULL,
  `data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_devices_ports_connections_from_type_from_id_index` (`from_type`,`from_id`),
  KEY `map_devices_ports_connections_to_type_to_id_index` (`to_type`,`to_id`),
  KEY `map_devices_ports_connections_layer_id_foreign` (`layer_id`),
  KEY `map_devices_ports_connections_from_route_id_foreign` (`from_route_id`),
  KEY `map_devices_ports_connections_to_route_id_foreign` (`to_route_id`),
  CONSTRAINT `map_devices_ports_connections_from_route_id_foreign` FOREIGN KEY (`from_route_id`) REFERENCES `map_layers_routes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `map_devices_ports_connections_layer_id_foreign` FOREIGN KEY (`layer_id`) REFERENCES `map_layers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `map_devices_ports_connections_to_route_id_foreign` FOREIGN KEY (`to_route_id`) REFERENCES `map_layers_routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `map_fibers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_fibers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_buffer` smallint NOT NULL DEFAULT '1',
  `buffer` smallint NOT NULL,
  `number` smallint NOT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fiber_id` bigint unsigned NOT NULL,
  `zone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_fibers_fiber_id_foreign` (`fiber_id`),
  CONSTRAINT `map_fibers_fiber_id_foreign` FOREIGN KEY (`fiber_id`) REFERENCES `map_layers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `map_fibers_cut`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_fibers_cut` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fiber_id` bigint unsigned NOT NULL,
  `layer_id` bigint unsigned NOT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_input` smallint NOT NULL DEFAULT '0',
  `route_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_fibers_cut_fiber_id_foreign` (`fiber_id`),
  KEY `map_fibers_cut_layer_id_foreign` (`layer_id`),
  KEY `map_fibers_cut_route_id_foreign` (`route_id`),
  CONSTRAINT `map_fibers_cut_fiber_id_foreign` FOREIGN KEY (`fiber_id`) REFERENCES `map_fibers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `map_fibers_cut_layer_id_foreign` FOREIGN KEY (`layer_id`) REFERENCES `map_layers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `map_fibers_cut_route_id_foreign` FOREIGN KEY (`route_id`) REFERENCES `map_layers_routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `map_layers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_layers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned DEFAULT NULL,
  `classification` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'project',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dialog` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` int NOT NULL DEFAULT '4',
  `distance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `layerable_id` bigint unsigned DEFAULT NULL,
  `layerable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_box_id` bigint unsigned DEFAULT NULL,
  `coords` json NOT NULL,
  `data` json NOT NULL,
  `inputs` smallint NOT NULL DEFAULT '6',
  `level` int NOT NULL DEFAULT '1000000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_layers_service_box_id_foreign` (`service_box_id`),
  KEY `map_layers_project_id_foreign` (`project_id`),
  CONSTRAINT `map_layers_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `map_proyects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `map_layers_service_box_id_foreign` FOREIGN KEY (`service_box_id`) REFERENCES `map_layers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `map_layers_routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_layers_routes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `route_id` bigint unsigned NOT NULL,
  `layer_id` bigint unsigned NOT NULL,
  `position_x` smallint NOT NULL DEFAULT '20',
  `position_y` smallint NOT NULL DEFAULT '20',
  `direction` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'right',
  `input` smallint DEFAULT NULL,
  `calculate_distance` decimal(8,2) NOT NULL DEFAULT '0.00',
  `real_distance` decimal(8,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_layers_routes_route_id_foreign` (`route_id`),
  KEY `map_layers_routes_layer_id_foreign` (`layer_id`),
  CONSTRAINT `map_layers_routes_layer_id_foreign` FOREIGN KEY (`layer_id`) REFERENCES `map_layers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `map_layers_routes_route_id_foreign` FOREIGN KEY (`route_id`) REFERENCES `map_layers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `map_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `input_id` bigint unsigned NOT NULL,
  `input_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `output_id` bigint unsigned NOT NULL,
  `output_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_route_id` bigint unsigned NOT NULL,
  `tube_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `map_ports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_ports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_x` smallint NOT NULL DEFAULT '20',
  `position_y` smallint NOT NULL DEFAULT '20',
  `type` enum('in','out') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'out',
  `orientation` enum('left','right') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `client_id` bigint unsigned DEFAULT NULL,
  `connected` tinyint(1) NOT NULL DEFAULT '0',
  `transfer` smallint DEFAULT NULL,
  `transfer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card` smallint DEFAULT NULL,
  `note` longtext COLLATE utf8mb4_unicode_ci,
  `device_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_ports_client_id_foreign` (`client_id`),
  KEY `map_ports_device_type_device_id_index` (`device_type`,`device_id`),
  CONSTRAINT `map_ports_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client_main_information` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `map_proyects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_proyects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `classification` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'project',
  `level` int NOT NULL DEFAULT '1000000',
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_proyects_created_by_foreign` (`created_by`),
  KEY `map_proyects_updated_by_foreign` (`updated_by`),
  KEY `map_proyects_classification_index` (`classification`),
  KEY `map_proyects_parent_id_foreign` (`parent_id`),
  CONSTRAINT `map_proyects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `map_proyects_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `map_proyects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `map_proyects_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `map_routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `map_routes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fibers_amount` int DEFAULT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `map_routes_map_proyect_id_foreign` (`map_proyect_id`),
  KEY `map_routes_created_by_foreign` (`created_by`),
  KEY `map_routes_updated_by_foreign` (`updated_by`),
  CONSTRAINT `map_routes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `map_routes_map_proyect_id_foreign` FOREIGN KEY (`map_proyect_id`) REFERENCES `map_proyects` (`id`),
  CONSTRAINT `map_routes_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_ai_agent_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_ai_agent_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel_id` bigint unsigned NOT NULL,
  `system_prompt` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `persona` text COLLATE utf8mb4_unicode_ci,
  `knowledge_base` longtext COLLATE utf8mb4_unicode_ci,
  `max_turns_before_human` tinyint unsigned NOT NULL DEFAULT '5',
  `auto_assign_to_user_id` bigint unsigned DEFAULT NULL,
  `model` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'claude-opus-4-7',
  `temperature` decimal(3,2) NOT NULL DEFAULT '0.70',
  `max_tokens` smallint unsigned NOT NULL DEFAULT '1024',
  `tools_enabled` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_ai_agent_configs_channel_id_foreign` (`channel_id`),
  KEY `marketing_ai_agent_configs_company_id_channel_id_index` (`company_id`,`channel_id`),
  KEY `marketing_ai_agent_configs_company_id_index` (`company_id`),
  CONSTRAINT `marketing_ai_agent_configs_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `marketing_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `type` enum('image','video','audio','music','font','brand_logo','voiceover','broll') COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint unsigned NOT NULL DEFAULT '0',
  `duration_seconds` decimal(8,2) DEFAULT NULL,
  `width` smallint unsigned DEFAULT NULL,
  `height` smallint unsigned DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `license` enum('cc0','cc_by','owned','licensed','ai_generated','pexels_free') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_assets_company_id_type_category_index` (`company_id`,`type`,`category`),
  KEY `marketing_assets_company_id_index` (`company_id`),
  KEY `marketing_assets_source_external_id_index` (`source`,`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_attributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_attributions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `channel_id` bigint unsigned DEFAULT NULL,
  `touchpoint` enum('first_touch','mid_touch','last_touch','conversion') COLLATE utf8mb4_unicode_ci NOT NULL,
  `weight` decimal(4,3) NOT NULL DEFAULT '1.000',
  `occurred_at` timestamp NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_attributions_channel_id_foreign` (`channel_id`),
  KEY `marketing_attributions_lead_id_touchpoint_index` (`lead_id`,`touchpoint`),
  KEY `marketing_attributions_campaign_id_index` (`campaign_id`),
  KEY `marketing_attributions_occurred_at_index` (`occurred_at`),
  CONSTRAINT `marketing_attributions_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_attributions_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `marketing_channels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_attributions_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `marketing_leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('meta_ads','google_ads','organic','email_blast','sms_blast','voice_blast','mixed','manual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `external_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `budget` decimal(12,2) NOT NULL DEFAULT '0.00',
  `spent` decimal(12,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MXN',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('draft','active','paused','finished','canceled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `config` json DEFAULT NULL,
  `goals` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_campaigns_company_id_slug_unique` (`company_id`,`slug`),
  KEY `marketing_campaigns_source_id_foreign` (`source_id`),
  KEY `marketing_campaigns_company_id_index` (`company_id`),
  KEY `marketing_campaigns_external_id_index` (`external_id`),
  CONSTRAINT `marketing_campaigns_source_id_foreign` FOREIGN KEY (`source_id`) REFERENCES `marketing_lead_sources` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `config` json DEFAULT NULL,
  `external_instance_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_channels_company_id_code_unique` (`company_id`,`code`),
  KEY `marketing_channels_company_id_index` (`company_id`),
  KEY `marketing_channels_external_instance_id_index` (`external_instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_content_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_content_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('copy_post','copy_dm','copy_email','copy_sms','copy_voice_script','copy_video_script') COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'es_MX',
  `template_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `variables` json DEFAULT NULL,
  `use_ai` tinyint(1) NOT NULL DEFAULT '1',
  `ai_prompt_template` text COLLATE utf8mb4_unicode_ci,
  `tone` enum('profesional','amigable','urgente','divertido','formal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'amigable',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_content_templates_company_id_category_index` (`company_id`,`category`),
  KEY `marketing_content_templates_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `lead_id` bigint unsigned NOT NULL,
  `channel_id` bigint unsigned NOT NULL,
  `external_thread_id` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_user_id` bigint unsigned DEFAULT NULL,
  `ai_handled` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('open','ai_handling','human_review','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `last_inbound_at` timestamp NULL DEFAULT NULL,
  `last_outbound_at` timestamp NULL DEFAULT NULL,
  `unread_count` smallint unsigned NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_conversations_channel_id_foreign` (`channel_id`),
  KEY `marketing_conversations_company_id_status_index` (`company_id`,`status`),
  KEY `marketing_conversations_lead_id_index` (`lead_id`),
  KEY `marketing_conversations_company_id_index` (`company_id`),
  KEY `marketing_conversations_external_thread_id_index` (`external_thread_id`),
  KEY `marketing_conversations_assigned_user_id_index` (`assigned_user_id`),
  CONSTRAINT `marketing_conversations_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `marketing_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketing_conversations_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `marketing_leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_generated_content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_generated_content` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `type` enum('copy','image','video','audio_voiceover','ai_response') COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id` bigint unsigned DEFAULT NULL,
  `template_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_lead_id` bigint unsigned DEFAULT NULL,
  `source_campaign_id` bigint unsigned DEFAULT NULL,
  `source_plan_id` bigint unsigned DEFAULT NULL,
  `input_variables` json DEFAULT NULL,
  `output_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumbnail_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_text` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','generating','ready','failed','used','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `render_progress` tinyint unsigned NOT NULL DEFAULT '0',
  `render_stage` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `render_log` json DEFAULT NULL,
  `generation_engine` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generation_cost_usd` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `generation_metadata` json DEFAULT NULL,
  `failure_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_generated_content_company_id_status_index` (`company_id`,`status`),
  KEY `marketing_generated_content_source_lead_id_index` (`source_lead_id`),
  KEY `marketing_generated_content_source_campaign_id_index` (`source_campaign_id`),
  KEY `marketing_generated_content_company_id_index` (`company_id`),
  KEY `marketing_generated_content_template_id_index` (`template_id`),
  KEY `marketing_generated_content_source_plan_id_index` (`source_plan_id`),
  CONSTRAINT `marketing_generated_content_source_campaign_id_foreign` FOREIGN KEY (`source_campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_generated_content_source_lead_id_foreign` FOREIGN KEY (`source_lead_id`) REFERENCES `marketing_leads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_lead_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_lead_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `type` enum('created','message_sent','message_received','score_updated','stage_changed','assigned','note','call','meeting','conversion','lost','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `channel_id` bigint unsigned DEFAULT NULL,
  `happened_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_lead_activities_channel_id_foreign` (`channel_id`),
  KEY `marketing_lead_activities_lead_id_happened_at_index` (`lead_id`,`happened_at`),
  KEY `marketing_lead_activities_user_id_index` (`user_id`),
  KEY `marketing_lead_activities_happened_at_index` (`happened_at`),
  CONSTRAINT `marketing_lead_activities_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `marketing_channels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_lead_activities_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `marketing_leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_lead_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_lead_forms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fields_schema` json NOT NULL,
  `styling` json DEFAULT NULL,
  `thank_you_message` text COLLATE utf8mb4_unicode_ci,
  `thank_you_redirect_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assign_to_source_id` bigint unsigned DEFAULT NULL,
  `assign_to_user_id` bigint unsigned DEFAULT NULL,
  `assign_to_pipeline_id` bigint unsigned DEFAULT NULL,
  `auto_scoring` tinyint(1) NOT NULL DEFAULT '1',
  `rate_limit_per_minute` smallint unsigned NOT NULL DEFAULT '5',
  `rate_limit_per_hour` smallint unsigned NOT NULL DEFAULT '50',
  `recaptcha_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `recaptcha_site_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submissions_count` int unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_lead_forms_company_id_slug_unique` (`company_id`,`slug`),
  KEY `marketing_lead_forms_assign_to_source_id_foreign` (`assign_to_source_id`),
  KEY `marketing_lead_forms_assign_to_pipeline_id_foreign` (`assign_to_pipeline_id`),
  KEY `marketing_lead_forms_company_id_index` (`company_id`),
  KEY `marketing_lead_forms_slug_index` (`slug`),
  KEY `marketing_lead_forms_assign_to_user_id_index` (`assign_to_user_id`),
  CONSTRAINT `marketing_lead_forms_assign_to_pipeline_id_foreign` FOREIGN KEY (`assign_to_pipeline_id`) REFERENCES `marketing_pipelines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_lead_forms_assign_to_source_id_foreign` FOREIGN KEY (`assign_to_source_id`) REFERENCES `marketing_lead_sources` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_lead_pipeline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_lead_pipeline` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `pipeline_id` bigint unsigned NOT NULL,
  `stage_id` bigint unsigned NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `entered_stage_at` timestamp NOT NULL,
  `exited_stage_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_lead_pipeline_lead_id_pipeline_id_unique` (`lead_id`,`pipeline_id`),
  KEY `marketing_lead_pipeline_pipeline_id_foreign` (`pipeline_id`),
  KEY `marketing_lead_pipeline_stage_id_position_index` (`stage_id`,`position`),
  CONSTRAINT `marketing_lead_pipeline_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `marketing_leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketing_lead_pipeline_pipeline_id_foreign` FOREIGN KEY (`pipeline_id`) REFERENCES `marketing_pipelines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketing_lead_pipeline_stage_id_foreign` FOREIGN KEY (`stage_id`) REFERENCES `marketing_pipeline_stages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_lead_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_lead_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#888888',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_lead_sources_company_id_code_unique` (`company_id`,`code`),
  KEY `marketing_lead_sources_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `source_id` bigint unsigned DEFAULT NULL,
  `lead_form_id` bigint unsigned DEFAULT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `neighborhood` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `plan_interested_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `raw_payload` json DEFAULT NULL,
  `score` tinyint unsigned NOT NULL DEFAULT '0',
  `score_reason` text COLLATE utf8mb4_unicode_ci,
  `tags` json DEFAULT NULL,
  `status` enum('new','contacted','qualified','negotiating','won','lost','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `assigned_user_id` bigint unsigned DEFAULT NULL,
  `captured_at` timestamp NOT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `lost_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_leads_source_id_foreign` (`source_id`),
  KEY `marketing_leads_company_id_status_index` (`company_id`,`status`),
  KEY `marketing_leads_company_id_captured_at_index` (`company_id`,`captured_at`),
  KEY `marketing_leads_company_id_index` (`company_id`),
  KEY `marketing_leads_email_index` (`email`),
  KEY `marketing_leads_phone_index` (`phone`),
  KEY `marketing_leads_whatsapp_index` (`whatsapp`),
  KEY `marketing_leads_plan_interested_id_index` (`plan_interested_id`),
  KEY `marketing_leads_assigned_user_id_index` (`assigned_user_id`),
  KEY `marketing_leads_captured_at_index` (`captured_at`),
  KEY `marketing_leads_last_activity_at_index` (`last_activity_at`),
  KEY `marketing_leads_lead_form_id_foreign` (`lead_form_id`),
  CONSTRAINT `marketing_leads_lead_form_id_foreign` FOREIGN KEY (`lead_form_id`) REFERENCES `marketing_lead_forms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_leads_source_id_foreign` FOREIGN KEY (`source_id`) REFERENCES `marketing_lead_sources` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `direction` enum('inbound','outbound') COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `content_type` enum('text','image','video','audio','document','location','sticker','reaction','button_reply') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `media_paths` json DEFAULT NULL,
  `media_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_downloaded_at` timestamp NULL DEFAULT NULL,
  `sender` enum('lead','human_user','ai_agent','system','reconciliation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_user_id` bigint unsigned DEFAULT NULL,
  `external_message_id` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reply_to_message_id` bigint unsigned DEFAULT NULL,
  `sent_at` timestamp NOT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `ai_intent_detected` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_confidence` decimal(4,3) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_messages_conversation_id_sent_at_index` (`conversation_id`,`sent_at`),
  KEY `marketing_messages_sender_user_id_index` (`sender_user_id`),
  KEY `marketing_messages_external_message_id_index` (`external_message_id`),
  KEY `marketing_messages_sent_at_index` (`sent_at`),
  CONSTRAINT `marketing_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `marketing_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_multivariant_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_multivariant_campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `campaign_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','generating','ready','partially_failed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `input_data` json NOT NULL,
  `creative_briefs` json DEFAULT NULL,
  `variant_content_ids` json DEFAULT NULL,
  `total_cost_usd` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `variants_succeeded` int NOT NULL DEFAULT '0',
  `variants_failed` int NOT NULL DEFAULT '0',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_multivariant_campaigns_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_niches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_niches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivators` json NOT NULL,
  `pain_points` json NOT NULL,
  `objections` json NOT NULL,
  `vocabulary` json NOT NULL,
  `emotional_tone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `music_mood` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `broll_tags` json NOT NULL,
  `voice_style` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preferred_voice_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_niches_slug_unique` (`slug`),
  KEY `marketing_niches_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_pilot_campaign_sends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_pilot_campaign_sends` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pilot_campaign_id` bigint unsigned NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant` enum('a','b') COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','sent','failed','opened','clicked','converted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `error` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_pilot_campaign_sends_token_unique` (`token`),
  KEY `marketing_pilot_campaign_sends_pilot_campaign_id_variant_index` (`pilot_campaign_id`,`variant`),
  CONSTRAINT `marketing_pilot_campaign_sends_pilot_campaign_id_foreign` FOREIGN KEY (`pilot_campaign_id`) REFERENCES `marketing_pilot_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_pilot_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_pilot_campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','dry_run','ready_to_send','sending','sent','canceled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `variant_a_subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_a_body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_a_cta_label` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_a_cta_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_b_subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_b_body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_b_cta_label` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_b_cta_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_size` smallint unsigned NOT NULL DEFAULT '10',
  `batch_pause_seconds` smallint unsigned NOT NULL DEFAULT '5',
  `dry_run_report` json DEFAULT NULL,
  `dry_run_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_pilot_campaigns_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_pipeline_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_pipeline_stages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pipeline_id` bigint unsigned NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#888888',
  `order` smallint unsigned NOT NULL DEFAULT '0',
  `is_won` tinyint(1) NOT NULL DEFAULT '0',
  `is_lost` tinyint(1) NOT NULL DEFAULT '0',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_score` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_pipeline_stages_pipeline_id_order_index` (`pipeline_id`,`order`),
  CONSTRAINT `marketing_pipeline_stages_pipeline_id_foreign` FOREIGN KEY (`pipeline_id`) REFERENCES `marketing_pipelines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_pipelines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_pipelines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_pipelines_company_id_slug_unique` (`company_id`,`slug`),
  KEY `marketing_pipelines_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_publication_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_publication_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `platform` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_config` json DEFAULT NULL,
  `supported_aspect_ratios` json NOT NULL,
  `max_duration_seconds` int NOT NULL DEFAULT '60',
  `max_file_size_mb` int NOT NULL DEFAULT '100',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `credentials_ready` tinyint(1) NOT NULL DEFAULT '0',
  `credentials_status_message` text COLLATE utf8mb4_unicode_ci,
  `credentials_validated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_publication_channels_slug_unique` (`slug`),
  KEY `marketing_publication_channels_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_publication_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_publication_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `publication_id` bigint unsigned NOT NULL,
  `event` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `marketing_publication_logs_publication_id_index` (`publication_id`),
  CONSTRAINT `marketing_publication_logs_publication_id_foreign` FOREIGN KEY (`publication_id`) REFERENCES `marketing_publications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_publications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_publications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `campaign_id` bigint unsigned DEFAULT NULL,
  `channel_id` bigint unsigned NOT NULL,
  `pub_channel_id` bigint unsigned DEFAULT NULL COMMENT 'FK to marketing_publication_channels',
  `content_id` bigint unsigned DEFAULT NULL,
  `custom_text` text COLLATE utf8mb4_unicode_ci,
  `caption` text COLLATE utf8mb4_unicode_ci,
  `hashtags` json DEFAULT NULL,
  `platform_options` json DEFAULT NULL,
  `media_paths` json DEFAULT NULL,
  `scheduled_at` timestamp NOT NULL,
  `scheduled_for` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','queued','scheduled','publishing','published','failed','waiting_credentials','cancelled','pending','approved','rejected','sent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `external_post_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_post_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `engagement` json DEFAULT NULL,
  `metrics` json DEFAULT NULL,
  `metrics_updated_at` timestamp NULL DEFAULT NULL,
  `ab_variant_tag` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retry_count` int NOT NULL DEFAULT '0',
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_publications_campaign_id_foreign` (`campaign_id`),
  KEY `marketing_publications_channel_id_foreign` (`channel_id`),
  KEY `marketing_publications_content_id_foreign` (`content_id`),
  KEY `marketing_publications_company_id_status_scheduled_at_index` (`company_id`,`status`,`scheduled_at`),
  KEY `marketing_publications_company_id_index` (`company_id`),
  KEY `marketing_publications_scheduled_at_index` (`scheduled_at`),
  KEY `marketing_publications_created_by_user_id_index` (`created_by_user_id`),
  KEY `idx_pub_channel_status` (`pub_channel_id`,`status`),
  KEY `idx_pub_status_scheduled` (`status`,`scheduled_for`),
  CONSTRAINT `marketing_publications_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_publications_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `marketing_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketing_publications_content_id_foreign` FOREIGN KEY (`content_id`) REFERENCES `marketing_generated_content` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_settings_company_id_key_unique` (`company_id`,`key`),
  KEY `marketing_settings_company_id_index` (`company_id`),
  KEY `marketing_settings_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_video_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_video_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL DEFAULT '1',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generic',
  `aspect_ratios` json NOT NULL,
  `duration_seconds` smallint unsigned NOT NULL DEFAULT '15',
  `schema` json NOT NULL,
  `engine_version` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0',
  `default_variables` json DEFAULT NULL,
  `estimated_render_seconds` int NOT NULL DEFAULT '30',
  `requires_voiceover` tinyint(1) NOT NULL DEFAULT '1',
  `preview_thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketing_video_templates_company_id_slug_unique` (`company_id`,`slug`),
  KEY `marketing_video_templates_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medium_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medium_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `megafamilia_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `megafamilia_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `megafamilia_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `method_of_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `method_of_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `method_of_payments_id_index` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migration_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migration_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `input_tokens` int unsigned NOT NULL DEFAULT '0',
  `output_tokens` int unsigned NOT NULL DEFAULT '0',
  `cost_usd` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `status` enum('pending','running','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `migration_logs_module_slug_index` (`module_slug`),
  KEY `migration_logs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=831 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mikrotik_client_hostpot_radius`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_client_hostpot_radius` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `mikrotik_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mikrotik_client_hostpot_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_client_hostpot_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `mikrotik_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mikrotik_client_ppoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_client_ppoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `mikrotik_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4464 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mikrotik_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `router_id` bigint unsigned NOT NULL,
  `meganet_config_ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '192.168.1.1' COMMENT 'ip del sistema meganet',
  `custom_config_name_parent_router` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MgNetSQP',
  `custom_config_comment_parent_router` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Meganet',
  `custom_config_comment_sun_router` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MgNetSQS',
  `mikrotik_config_server_pppoe_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PPPoE_SERVER_VLAN_200',
  `mikrotik_config_server_pppoe_interface` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vlan200 Internet',
  `mikrotik_config_server_pppoe_mtu` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1500',
  `mikrotik_config_server_pppoe_mru` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1500',
  `mikrotik_config_server_pppoe_profile` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PPPOE_VLAN_200',
  `mikrotik_config_server_ppp_profile` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PPPOE_VLAN_200',
  `mikrotik_config_server_ppp_local_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '10.10.0.1' COMMENT 'Ip local de la interfaz',
  `mikrotik_config_server_ppp_remote_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'estatica',
  `mikrotik_config_server_ppp_bridge` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RED_LOCAL_LAN',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `meganet_config_ip_address_enable` tinyint(1) DEFAULT '0',
  `enforce_input_drop_rest` tinyint(1) NOT NULL DEFAULT '0',
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mikrotik_item_to_excecute_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_item_to_excecute_actions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `place` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `origin` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mikrotik_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('Alta','Media','Baja') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `router_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mikrotik_notifications_router_id_foreign` (`router_id`),
  CONSTRAINT `mikrotik_notifications_router_id_foreign` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mikrotik_tariff_main_tails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_tariff_main_tails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mikrotik_id` int NOT NULL,
  `tariff_id` int NOT NULL,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mikrotik_tariff_target_tails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_tariff_target_tails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mikrotik_tariff_main_tail_id` int NOT NULL,
  `mikrotik_id` int NOT NULL,
  `tariff_id` int NOT NULL,
  `client_internet_service_id` int NOT NULL,
  `client_custom_service_id` int DEFAULT NULL,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mikrotiks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotiks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `router_id` bigint unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `login_api` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_api` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `port_api` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shaper_active` tinyint(1) NOT NULL DEFAULT '0',
  `shaper` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shaping_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rule_wireless_access_list` tinyint(1) NOT NULL DEFAULT '0',
  `url_redirect` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'field depend on rule_wireless_access_list',
  `port_redirect` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '80',
  `ip_redirect` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'field depend on rule_wireless_access_list',
  `ips_with_comma_permited` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'field depend on rule_wireless_access_list',
  `rule_address_list_mobility_client` tinyint(1) NOT NULL DEFAULT '0',
  `bloking_rules` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plataform` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `board_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ros_version` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpu_load` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ipv6` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mikrotiks_router_id_foreign` (`router_id`),
  CONSTRAINT `mikrotiks_router_id_foreign` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modems` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `model` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` bigint unsigned NOT NULL,
  `serie` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `module_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_migrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ran_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_migrations_module_slug_migration_unique` (`module_slug`,`migration`),
  KEY `module_migrations_module_slug_index` (`module_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_registry` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `installed_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0.1.0',
  `type` enum('core','addon') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'addon',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `installed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_registry_slug_unique` (`slug`),
  KEY `module_registry_type_active_index` (`type`,`active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_sidebar_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_sidebar_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `show_in_sidebar` tinyint(1) NOT NULL DEFAULT '1',
  `sidebar_location` enum('direct','sub_item') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'direct',
  `sidebar_parent` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sidebar_section` enum('menu','modulos') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'modulos',
  `sidebar_position` int NOT NULL DEFAULT '99',
  `sidebar_icon` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sidebar_label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sidebar_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config_moved` tinyint(1) NOT NULL DEFAULT '0',
  `admin_section` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `configuracion_subsection` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_core` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_sidebar_config_module_key_unique` (`module_key`),
  KEY `msc_visible_section_pos` (`show_in_sidebar`,`sidebar_section`,`sidebar_position`),
  KEY `msc_parent` (`sidebar_parent`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '1',
  `main` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `municipalities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `municipalities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `municipalities_id_index` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2476 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `network_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `network_ips` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `network_id` int NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `used_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hostname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `host_category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ping` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `type_service` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `network_ips_client_id_index` (`client_id`),
  KEY `network_ips_network_id_index` (`network_id`),
  KEY `network_ips_ip_index` (`ip`)
) ENGINE=InnoDB AUTO_INCREMENT=26973 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `networks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `networks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `network` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bm` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rootnet` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `used` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `network_type` enum('RootNet','EndNet') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `network_category` enum('Dev','Coorporativa','Test','Produccion') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `router_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_of_use` enum('Estatico','Pool') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_usage_network` tinyint(1) DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `deployed` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `networks_id_index` (`id`),
  KEY `networks_title_index` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nomenclatures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nomenclatures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nomenclatures_name_unique` (`name`),
  KEY `nomenclatures_client_id_foreign` (`client_id`),
  KEY `nomenclatures_name_index` (`name`),
  CONSTRAINT `nomenclatures_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39938 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `observation_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `observation_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `observation` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_observation_tasks_task_id` (`task_id`)
) ENGINE=InnoDB AUTO_INCREMENT=667 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_billings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_billings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `end_subscription` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_cards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slot` int NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `real_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ports` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `software_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `olt_id` bigint unsigned NOT NULL,
  `info_updated` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_olt_slot_idx` (`olt_id`,`slot`),
  CONSTRAINT `olt_cards_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_interruption_pons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_interruption_pons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `board` int NOT NULL,
  `port` int NOT NULL,
  `cause` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `power_count` int NOT NULL DEFAULT '0',
  `total_onus` int NOT NULL DEFAULT '0',
  `los_count` int NOT NULL DEFAULT '0',
  `latest_status_change` timestamp NULL DEFAULT NULL,
  `pon_description` text COLLATE utf8mb4_unicode_ci,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `olt_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_board_port_idx` (`olt_id`,`board`,`port`),
  CONSTRAINT `olt_interruption_pons_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_odbs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_odbs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` int NOT NULL,
  `zone_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_onus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_onus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unique_external_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `board` int DEFAULT NULL,
  `port` int DEFAULT NULL,
  `administrative_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `service_id` bigint unsigned DEFAULT NULL COMMENT 'FK → client_internet_services.id (1 ONU = 1 servicio)',
  `onu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pon_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tr069` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tr069_profile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catv` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_template_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dns2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dns1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_gateway` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subnet_mask` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_service_port` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_vlan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_tag_transform_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_svlan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_cvlan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wan_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vlan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_dns2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_dns1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_default_gateway` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mgmt_ip_subnet_mask` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `odb_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signal_1310` double(8,2) DEFAULT NULL,
  `signal_1490` double(8,2) DEFAULT NULL,
  `voip_service` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_ports` json DEFAULT NULL,
  `ethernet_ports` json DEFAULT NULL,
  `wifi_ports` json DEFAULT NULL,
  `voip_ports` json DEFAULT NULL,
  `onu_type_id` bigint unsigned DEFAULT NULL,
  `onu_type_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` bigint unsigned DEFAULT NULL,
  `zone_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `olt_id` bigint unsigned NOT NULL,
  `olt_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authorization_date` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_status_change` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onus_sn_olt_id_unique` (`sn`,`olt_id`),
  UNIQUE KEY `olt_onus_unique_external_id_unique` (`unique_external_id`),
  KEY `olt_onus_onu_type_id_foreign` (`onu_type_id`),
  KEY `olt_onus_zone_id_foreign` (`zone_id`),
  KEY `olt_onus_olt_id_foreign` (`olt_id`),
  KEY `olt_onus_service_id_index` (`service_id`),
  CONSTRAINT `olt_onus_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `olt_onus_onu_type_id_foreign` FOREIGN KEY (`onu_type_id`) REFERENCES `olt_type_onus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `olt_onus_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `client_internet_services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `olt_onus_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `olt_zones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_pon_ports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_pon_ports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `board` int NOT NULL,
  `pon_port` int NOT NULL,
  `pon_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operational_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onus_count` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `online_onus_count` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `average_signal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_range` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_range` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tx_power` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `olt_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pon_port_idx` (`olt_id`,`board`,`pon_port`),
  CONSTRAINT `olt_pon_ports_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_smartolt_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_smartolt_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `api_domain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_token` text COLLATE utf8mb4_unicode_ci,
  `ttl` smallint unsigned NOT NULL DEFAULT '120',
  `hourly_budget` smallint unsigned NOT NULL DEFAULT '1000',
  `activa` tinyint(1) NOT NULL DEFAULT '0',
  `alertas_olt_activas` tinyint(1) NOT NULL DEFAULT '0',
  `alertas_rol_destino` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_sync_ok_at` timestamp NULL DEFAULT NULL,
  `last_sync_error_at` timestamp NULL DEFAULT NULL,
  `connected_since` timestamp NULL DEFAULT NULL,
  `last_error_message` text COLLATE utf8mb4_unicode_ci,
  `last_olt_count` int unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_speed_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_speed_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `speed` int NOT NULL,
  `direction` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_type_onus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_type_onus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pon_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capability` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ethernet_ports` int NOT NULL,
  `wifi_ports` int NOT NULL,
  `voip_ports` int NOT NULL,
  `catv` int NOT NULL,
  `allow_custom_profiles` int NOT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_type_onus_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_unconfigured_onus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_unconfigured_onus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `board` int NOT NULL,
  `port` int NOT NULL,
  `pon_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onu_type_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pon_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `olt_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_unconfigured_onus_sn_unique` (`sn`),
  KEY `olt_unconfigured_onus_olt_id_foreign` (`olt_id`),
  CONSTRAINT `olt_unconfigured_onus_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_uplink_ports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_uplink_ports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vlan_tag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `negotiation_auto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mtu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wavelength` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temperature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pvid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `olt_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_olt_name_idx` (`olt_id`,`name`),
  CONSTRAINT `olt_uplink_ports_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_vlans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_vlans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vlan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `olt_id` bigint unsigned NOT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `olt_vlans_olt_id_foreign` (`olt_id`),
  CONSTRAINT `olt_vlans_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olt_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_zones_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `olts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `olt_hardware_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snmp_port` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telnet_port` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `env_temp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uptime` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `driver` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'smartolt',
  `motor_modo` enum('smartolt','propio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'smartolt' COMMENT 'Motor de escritura activo para esta OLT (informativo en W2)',
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orphan_client_backfill_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orphan_client_backfill_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batch` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `login_user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orphan_client_backfill_log_batch_index` (`batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('js','css') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  `plan_id` bigint unsigned NOT NULL,
  `status` enum('active','suspended','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `licensed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `terms_accepted_at` timestamp NULL DEFAULT NULL,
  `terms_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_version_accepted` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_accounts_plan_id_foreign` (`plan_id`),
  KEY `parental_accounts_status_plan_id_index` (`status`,`plan_id`),
  KEY `parental_accounts_expires_at_index` (`expires_at`),
  KEY `parental_accounts_user_id_foreign` (`user_id`),
  KEY `parental_accounts_client_isp_id_foreign` (`client_isp_id`),
  CONSTRAINT `parental_accounts_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_accounts_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `parental_plans` (`id`),
  CONSTRAINT `parental_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `profile_id` bigint unsigned DEFAULT NULL,
  `device_id` bigint unsigned DEFAULT NULL,
  `type` enum('uninstall_attempt','geofence_exit','blocked_content','low_battery','device_offline') COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_alerts_device_id_foreign` (`device_id`),
  KEY `parental_alerts_account_id_read_at_index` (`account_id`,`read_at`),
  KEY `parental_alerts_profile_id_type_index` (`profile_id`,`type`),
  KEY `parental_alerts_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_alerts_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `parental_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_alerts_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_alerts_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `parental_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parental_alerts_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_app_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_app_blocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint unsigned NOT NULL,
  `package_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `app_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blocked` tinyint(1) NOT NULL DEFAULT '1',
  `schedule_start` time DEFAULT NULL,
  `schedule_end` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parental_app_blocks_profile_id_package_name_unique` (`profile_id`,`package_name`),
  KEY `parental_app_blocks_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_app_blocks_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_app_blocks_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version_number` int NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_draft` tinyint(1) NOT NULL DEFAULT '1',
  `require_reacceptance` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parental_consents_version_number_unique` (`version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` enum('android','ios') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'android',
  `os_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'offline',
  `battery_level` tinyint unsigned DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `fcm_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_token_expires_at` timestamp NULL DEFAULT NULL,
  `linked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parental_devices_link_token_unique` (`link_token`),
  KEY `parental_devices_account_id_status_index` (`account_id`,`status`),
  KEY `parental_devices_profile_id_status_index` (`profile_id`,`status`),
  KEY `parental_devices_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_devices_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `parental_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_devices_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_devices_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `profile_id` bigint unsigned DEFAULT NULL,
  `device_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_events_profile_id_foreign` (`profile_id`),
  KEY `parental_events_device_id_foreign` (`device_id`),
  KEY `parental_events_account_id_created_at_index` (`account_id`,`created_at`),
  KEY `parental_events_action_index` (`action`),
  KEY `parental_events_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_events_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `parental_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_events_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_events_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `parental_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parental_events_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_geofences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_geofences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home',
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `radius_meters` int unsigned NOT NULL DEFAULT '100',
  `coordinates` json DEFAULT NULL,
  `alert_on_enter` tinyint(1) NOT NULL DEFAULT '0',
  `alert_on_exit` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_geofences_profile_id_active_index` (`profile_id`,`active`),
  KEY `parental_geofences_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_geofences_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_geofences_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_licenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_licenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `plan_id` bigint unsigned NOT NULL,
  `status` enum('active','suspended','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `activated_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `suspended_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_licenses_account_id_foreign` (`account_id`),
  KEY `parental_licenses_plan_id_foreign` (`plan_id`),
  KEY `parental_licenses_status_plan_id_index` (`status`,`plan_id`),
  KEY `parental_licenses_expires_at_index` (`expires_at`),
  KEY `parental_licenses_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_licenses_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `parental_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_licenses_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_licenses_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `parental_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `device_id` bigint unsigned NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `accuracy` int unsigned DEFAULT NULL,
  `battery` tinyint unsigned DEFAULT NULL,
  `recorded_at` timestamp NOT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_locations_device_id_recorded_at_index` (`device_id`,`recorded_at`),
  KEY `parental_locations_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_locations_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_locations_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `parental_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_yearly` decimal(10,2) DEFAULT NULL,
  `period` enum('monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `max_children` int NOT NULL DEFAULT '0',
  `max_devices` int NOT NULL DEFAULT '0',
  `max_parents` int NOT NULL DEFAULT '1',
  `features` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parental_plans_slug_unique` (`slug`),
  KEY `parental_plans_active_slug_index` (`active`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `age` tinyint unsigned DEFAULT NULL,
  `school_level` enum('primaria','secundaria','preparatoria') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_type` enum('nino','preadolescente','adolescente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nino',
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `pin_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_profiles_account_id_active_index` (`account_id`,`active`),
  KEY `parental_profiles_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_profiles_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `parental_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_profiles_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint unsigned NOT NULL,
  `device_id` bigint unsigned DEFAULT NULL,
  `reward_id` bigint unsigned DEFAULT NULL,
  `type` enum('time_extra','app_unlock','web_unlock','redemption') COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `responded_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_requests_device_id_foreign` (`device_id`),
  KEY `parental_requests_profile_id_status_index` (`profile_id`,`status`),
  KEY `parental_requests_expires_at_index` (`expires_at`),
  KEY `parental_requests_client_isp_id_index` (`client_isp_id`),
  KEY `parental_requests_reward_id_foreign` (`reward_id`),
  CONSTRAINT `parental_requests_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_requests_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `parental_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parental_requests_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_requests_reward_id_foreign` FOREIGN KEY (`reward_id`) REFERENCES `parental_rewards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_rewards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint unsigned NOT NULL,
  `type` enum('time_extra','app_unlock','points','badge') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'points',
  `value` int NOT NULL DEFAULT '0',
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_task_id` bigint unsigned DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_rewards_source_task_id_foreign` (`source_task_id`),
  KEY `parental_rewards_profile_id_type_index` (`profile_id`,`type`),
  KEY `parental_rewards_expires_at_index` (`expires_at`),
  KEY `parental_rewards_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_rewards_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_rewards_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_rewards_source_task_id_foreign` FOREIGN KEY (`source_task_id`) REFERENCES `parental_tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint unsigned NOT NULL,
  `daily_limit_minutes` int unsigned NOT NULL DEFAULT '0',
  `weekend_limit_minutes` int unsigned NOT NULL DEFAULT '0',
  `bedtime_start` time DEFAULT NULL,
  `bedtime_end` time DEFAULT NULL,
  `school_start` time DEFAULT NULL,
  `school_end` time DEFAULT NULL,
  `internet_paused` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parental_rules_profile_id_unique` (`profile_id`),
  KEY `parental_rules_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_rules_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_rules_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `days` json DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `action` enum('block','allow') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'block',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_schedules_profile_id_active_index` (`profile_id`,`active`),
  KEY `parental_schedules_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_schedules_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_schedules_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_task_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_task_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `profile_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  `status` enum('pending','completed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_task_assignments_task_id_profile_id_index` (`task_id`,`profile_id`),
  KEY `parental_task_assignments_account_id_client_isp_id_index` (`account_id`,`client_isp_id`),
  KEY `parental_task_assignments_profile_id_index` (`profile_id`),
  CONSTRAINT `parental_task_assignments_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `parental_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_task_assignments_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_task_assignments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `parental_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `reward_type` enum('time_extra','app_unlock','points','badge') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'points',
  `reward_value` int NOT NULL DEFAULT '0',
  `reward_detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('baja','media','alta') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `points` int NOT NULL DEFAULT '0',
  `assignment_type` enum('cada_uno','solo_uno') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cada_uno',
  `status` enum('pending','completed','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `photo_proof` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parental_tasks_profile_id_status_index` (`profile_id`,`status`),
  KEY `parental_tasks_client_isp_id_index` (`client_isp_id`),
  KEY `parental_tasks_account_id_foreign` (`account_id`),
  CONSTRAINT `parental_tasks_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `parental_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_tasks_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_tasks_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parental_web_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parental_web_blocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint unsigned NOT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blocked` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_isp_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parental_web_blocks_profile_id_domain_unique` (`profile_id`,`domain`),
  KEY `parental_web_blocks_client_isp_id_index` (`client_isp_id`),
  CONSTRAINT `parental_web_blocks_client_isp_id_foreign` FOREIGN KEY (`client_isp_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parental_web_blocks_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `parental_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partner_module`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_module` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `partner_id` bigint NOT NULL,
  `partner_module_id` bigint NOT NULL,
  `partner_module_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=255 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partners_id_index` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `passive_equipment_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `passive_equipment_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('DFO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ports` int NOT NULL,
  `trays` int NOT NULL,
  `brand_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `passive_equipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `passive_equipments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rack_id` bigint unsigned NOT NULL,
  `type_id` bigint unsigned NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_by_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_by_rule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_date` date NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `payment_method_id` bigint unsigned NOT NULL,
  `amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `signature` longtext COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_by_rule_seller_id_foreign` (`seller_id`),
  KEY `payment_by_rule_payment_method_id_foreign` (`payment_method_id`),
  KEY `payment_by_rule_created_by_foreign` (`created_by`),
  CONSTRAINT `payment_by_rule_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `payment_by_rule_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `method_of_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_by_rule_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_by_rule_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_by_rule_commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `payment_id` bigint unsigned NOT NULL,
  `rule_id` bigint unsigned NOT NULL,
  `amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `sales` json DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_by_rule_commissions_payment_id_foreign` (`payment_id`),
  KEY `payment_by_rule_commissions_rule_id_foreign` (`rule_id`),
  KEY `type_index` (`type`),
  KEY `start_date_index` (`start_date`),
  KEY `end_date_index` (`end_date`),
  KEY `dates_index` (`start_date`,`end_date`),
  KEY `type_and_dates_index` (`type`,`start_date`,`end_date`),
  CONSTRAINT `payment_by_rule_commissions_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payment_by_rule` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_by_rule_commissions_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `history_sellers_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_clabes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_clabes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `clabe` char(18) COLLATE utf8mb4_unicode_ci NOT NULL,
  `openpay_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_provider_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_clabes_clabe_unique` (`clabe`),
  KEY `payment_clabes_payment_provider_id_foreign` (`payment_provider_id`),
  KEY `payment_clabes_client_id_is_active_index` (`client_id`,`is_active`),
  KEY `payment_clabes_openpay_id_index` (`openpay_id`),
  CONSTRAINT `payment_clabes_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_clabes_payment_provider_id_foreign` FOREIGN KEY (`payment_provider_id`) REFERENCES `payment_providers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_emails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `via` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `email_if_error` text COLLATE utf8mb4_unicode_ci,
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cc_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `html` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_instruments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_instruments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `payment_provider_id` bigint unsigned DEFAULT NULL,
  `instrument_type` enum('reference','clabe','card') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reference',
  `external_ref` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','suspended','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pay_instr_client_prov_type_uniq` (`client_id`,`payment_provider_id`,`instrument_type`),
  KEY `payment_instruments_payment_provider_id_foreign` (`payment_provider_id`),
  KEY `payment_instruments_client_id_status_index` (`client_id`,`status`),
  CONSTRAINT `payment_instruments_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_instruments_payment_provider_id_foreign` FOREIGN KEY (`payment_provider_id`) REFERENCES `payment_providers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_promises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_promises` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `court_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` double(8,2) NOT NULL DEFAULT '0.00',
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_providers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_routable` tinyint(1) NOT NULL DEFAULT '1',
  `config` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_providers_provider_is_active_index` (`provider`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_receipts_payment_id_type_index` (`payment_id`,`type`),
  CONSTRAINT `payment_receipts_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_webhooks_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_webhooks_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json NOT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_id` bigint unsigned DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_webhooks_log_payment_id_foreign` (`payment_id`),
  KEY `payment_webhooks_log_provider_external_id_index` (`provider`,`external_id`),
  KEY `payment_webhooks_log_status_created_at_index` (`status`,`created_at`),
  CONSTRAINT `payment_webhooks_log_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_id` bigint NOT NULL,
  `date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` double NOT NULL,
  `payment_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `receipt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Se genera con la fecha más un número consecutivo generado.',
  `send_receipt_after_payment` tinyint(1) DEFAULT NULL,
  `add_by` bigint NOT NULL,
  `paymentable_id` bigint NOT NULL,
  `paymentable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_first_payment` tinyint(1) NOT NULL DEFAULT '0',
  `enabled_payment_promise` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `first_court_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_amount` double DEFAULT NULL,
  `second_court_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_amount` double DEFAULT NULL,
  `third_court_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `third_amount` double DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_id_index` (`id`),
  KEY `idx_payments_paymentable_id` (`paymentable_id`),
  KEY `idx_payments_date` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=102048 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `prospect_id` bigint unsigned DEFAULT NULL,
  `bundle_id` bigint unsigned DEFAULT NULL,
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
DROP TABLE IF EXISTS `payments_sellers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments_sellers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `method_of_payment` bigint unsigned NOT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `commission_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'panel',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=499 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ping_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ping_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avg_ms` decimal(8,2) DEFAULT NULL,
  `min_ms` decimal(8,2) DEFAULT NULL,
  `max_ms` decimal(8,2) DEFAULT NULL,
  `jitter_ms` decimal(8,2) DEFAULT NULL,
  `packet_loss` tinyint NOT NULL DEFAULT '0' COMMENT 'Porcentaje 0-100',
  `status` enum('up','down','timeout') COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ping_statistics_client_id_recorded_at_index` (`client_id`,`recorded_at`),
  CONSTRAINT `ping_statistics_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plan_bundles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_bundles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bundle_id` bigint NOT NULL,
  `plan_bundle_id` bigint NOT NULL,
  `cant` bigint NOT NULL,
  `plan_bundle_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_bundles_bundle_plan_type_unique` (`bundle_id`,`plan_bundle_id`,`plan_bundle_type`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plan_custom_client`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_custom_client` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `custom_id` bigint NOT NULL,
  `tarifa_custom_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plan_type_billings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_type_billings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type_billing_id` bigint NOT NULL,
  `plan_billing_id` bigint NOT NULL,
  `plan_billing_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=206 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `point_accessories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `point_accessories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `point_id` bigint unsigned NOT NULL,
  `name` enum('raqueta','omega','loop','cambios de altura') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lenght` decimal(8,2) NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `points` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `map_proyect_id` bigint unsigned NOT NULL,
  `is_pole` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `pole_accessories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pole_accessories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pole_id` bigint unsigned NOT NULL,
  `name` enum('Hebilla','Fleje','Remates','Tensores','Brazos') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `observations` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `poles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `poles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `height` decimal(8,2) NOT NULL,
  `type` enum('Concreto','Madera','Metalicos','Luminarias','Semaforo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tension` enum('Alta','Baja','Media','Sin tención') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `portal_pago_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_pago_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clabe` char(18) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuenta` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarjeta` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banco` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titular` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `beneficiario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `instance_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `portal_pago_accounts_clabe_index` (`clabe`),
  KEY `portal_pago_accounts_activa_index` (`activa`),
  KEY `portal_pago_accounts_instance_id_index` (`instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_pago_payment_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_pago_payment_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `monto_esperado` decimal(12,2) NOT NULL,
  `referencia_unica` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('pendiente','reportado','validado','conciliado','expirado','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `expira_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `portal_pago_payment_links_token_unique` (`token`),
  UNIQUE KEY `portal_pago_payment_links_referencia_unica_unique` (`referencia_unica`),
  KEY `portal_pago_payment_links_account_id_foreign` (`account_id`),
  KEY `portal_pago_payment_links_estado_expira_at_index` (`estado`,`expira_at`),
  KEY `portal_pago_payment_links_document_id_index` (`document_id`),
  KEY `portal_pago_payment_links_client_id_index` (`client_id`),
  CONSTRAINT `portal_pago_payment_links_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `portal_pago_accounts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_pago_payment_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_pago_payment_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_link_id` bigint unsigned NOT NULL,
  `clave_rastreo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `banco_emisor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_operacion` date DEFAULT NULL,
  `monto_reportado` decimal(12,2) NOT NULL,
  `comprobante_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cep_validado` tinyint(1) NOT NULL DEFAULT '0',
  `cep_resultado` json DEFAULT NULL,
  `cep_xml_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('pendiente_validacion','validado','discrepancia','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente_validacion',
  `revisado_por` bigint unsigned DEFAULT NULL,
  `revisado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `portal_pago_payment_reports_payment_link_id_foreign` (`payment_link_id`),
  KEY `portal_pago_payment_reports_estado_index` (`estado`),
  KEY `portal_pago_payment_reports_clave_rastreo_index` (`clave_rastreo`),
  CONSTRAINT `portal_pago_payment_reports_payment_link_id_foreign` FOREIGN KEY (`payment_link_id`) REFERENCES `portal_pago_payment_links` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_pago_recurrences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_pago_recurrences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `dia_corte` tinyint unsigned NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `ultimo_link_enviado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `portal_pago_recurrences_account_id_foreign` (`account_id`),
  KEY `portal_pago_recurrences_activa_dia_corte_index` (`activa`,`dia_corte`),
  KEY `portal_pago_recurrences_client_id_index` (`client_id`),
  CONSTRAINT `portal_pago_recurrences_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `portal_pago_accounts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_payment_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_payment_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `order_id` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `openpay_charge_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_code` smallint unsigned DEFAULT NULL,
  `error_message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `portal_payment_attempts_order_id_unique` (`order_id`),
  KEY `portal_payment_attempts_client_id_invoice_id_index` (`client_id`,`invoice_id`),
  KEY `portal_payment_attempts_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_profile_change_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_profile_change_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `campo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_anterior` text COLLATE utf8mb4_unicode_ci,
  `valor_nuevo` text COLLATE utf8mb4_unicode_ci,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` int DEFAULT NULL,
  `type` enum('gibic C+','gibic C++','SFP','SFP+','ethernet','normal','entrada','fibra','jumper','fusión','continuo','box','splitter_out','splitter_in') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `portable_id` bigint unsigned NOT NULL,
  `portable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ports_created_by_foreign` (`created_by`),
  KEY `ports_updated_by_foreign` (`updated_by`),
  CONSTRAINT `ports_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `ports_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `positions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `point` point NOT NULL,
  `positionable_id` bigint unsigned NOT NULL,
  `positionable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `positions_created_by_foreign` (`created_by`),
  KEY `positions_updated_by_foreign` (`updated_by`),
  CONSTRAINT `positions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `positions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proforma_invoice_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proforma_invoice_emails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `via` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `email_if_error` text COLLATE utf8mb4_unicode_ci,
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cc_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `html` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `project_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_team` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `team_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_team` (`project_id`,`team_id`),
  KEY `idx_project_team_project` (`project_id`),
  KEY `idx_project_team_team` (`team_id`),
  CONSTRAINT `project_team_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_team_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `project_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_lead` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workflow` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `promotionable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `promotionable_id` bigint unsigned NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promotions_promotionable_type_promotionable_id_index` (`promotionable_type`,`promotionable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prospect_followups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prospect_followups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prospect_id` bigint unsigned NOT NULL,
  `embajador_id` bigint unsigned NOT NULL,
  `action` enum('call','whatsapp','visit','sms','email','note') COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `next_action_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prospect_followups_prospect_id_foreign` (`prospect_id`),
  KEY `prospect_followups_embajador_id_foreign` (`embajador_id`),
  CONSTRAINT `prospect_followups_embajador_id_foreign` FOREIGN KEY (`embajador_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prospect_followups_prospect_id_foreign` FOREIGN KEY (`prospect_id`) REFERENCES `referral_prospects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prospects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prospects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `colony` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipality` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_prospect` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_seller` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prospects_id_seller_foreign` (`id_seller`),
  CONSTRAINT `prospects_id_seller_foreign` FOREIGN KEY (`id_seller`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `push_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_tokens_token_unique` (`token`),
  KEY `push_tokens_user_id_index` (`user_id`),
  CONSTRAINT `push_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quote_crms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quote_crms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint unsigned NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime NOT NULL,
  `total` double(8,2) NOT NULL,
  `valid_till` datetime NOT NULL,
  `lead_id` bigint NOT NULL,
  `last_update` datetime NOT NULL,
  `date_of_decision` datetime NOT NULL,
  `invoice_id` bigint NOT NULL,
  `request_id` bigint NOT NULL,
  `is_sent` tinyint(1) NOT NULL,
  `note` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `memo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customers_quote` bigint NOT NULL,
  `connected_deal_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quote_crms_crm_id_foreign` (`crm_id`),
  CONSTRAINT `quote_crms_crm_id_foreign` FOREIGN KEY (`crm_id`) REFERENCES `crms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `racks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `racks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` bigint unsigned NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` bigint NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `radius_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `radius_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `framed_ip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nas_ip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  `stop_time` timestamp NULL DEFAULT NULL,
  `bytes_in` bigint unsigned NOT NULL DEFAULT '0',
  `bytes_out` bigint unsigned NOT NULL DEFAULT '0',
  `session_time` int NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `radius_sessions_session_id_unique` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ranges_of_sales_sectors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ranges_of_sales_sectors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sector` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `range` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_of_prospects` int NOT NULL,
  `number_of_sales` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ranges_of_sales_sectors_sector_range_unique` (`sector`,`range`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `receipt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `receiptable_id` bigint NOT NULL,
  `receiptable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=98779 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reconciliation_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliation_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `reason` enum('unmatched_reference','amount_mismatch','duplicate','manual_review') COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci,
  `amount` double DEFAULT NULL,
  `status` enum('open','resolved','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_note` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reconciliation_tickets_client_id_foreign` (`client_id`),
  KEY `reconciliation_tickets_payment_id_foreign` (`payment_id`),
  KEY `reconciliation_tickets_status_created_at_index` (`status`,`created_at`),
  CONSTRAINT `reconciliation_tickets_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reconciliation_tickets_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_charge_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_charge_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `order_id` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `openpay_charge_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_no` tinyint unsigned NOT NULL DEFAULT '1',
  `error_code` smallint unsigned DEFAULT NULL,
  `error_message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recurring_charge_attempts_order_id_unique` (`order_id`),
  KEY `recurring_charge_attempts_client_id_invoice_id_index` (`client_id`,`invoice_id`),
  KEY `recurring_charge_attempts_client_id_status_index` (`client_id`,`status`),
  KEY `recurring_charge_attempts_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_closures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_closures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ancestor_id` bigint unsigned NOT NULL,
  `descendant_id` bigint unsigned NOT NULL,
  `depth` tinyint unsigned NOT NULL COMMENT '0=self, 1=directo, hasta 5',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ancestor_descendant` (`ancestor_id`,`descendant_id`),
  KEY `idx_ancestor_depth` (`ancestor_id`,`depth`),
  KEY `idx_descendant_depth` (`descendant_id`,`depth`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_commission_tiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_commission_tiers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tier_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `speed_min_mbps` int NOT NULL,
  `speed_max_mbps` int NOT NULL,
  `level_1_pct` decimal(5,2) NOT NULL,
  `level_2_pct` decimal(5,2) NOT NULL,
  `level_3_pct` decimal(5,2) NOT NULL,
  `level_4_pct` decimal(5,2) NOT NULL,
  `level_5_pct` decimal(5,2) NOT NULL,
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `beneficiary_id` bigint unsigned NOT NULL,
  `referral_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `level` tinyint NOT NULL,
  `commission_pct` decimal(5,2) NOT NULL,
  `base_amount` decimal(10,2) NOT NULL,
  `commission_amount` decimal(10,2) NOT NULL,
  `period_month` tinyint NOT NULL,
  `period_year` smallint NOT NULL,
  `status` enum('pending','approved','applied','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `apply_after_at` timestamp NULL DEFAULT NULL COMMENT 'Comisión solo se aplica después de esta fecha (garantía 15 días anti-reversas)',
  `applied_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp exacto cuando pasó a applied — auditoría',
  `applied_invoice_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `period_yyyymm` int unsigned GENERATED ALWAYS AS (((`period_year` * 100) + `period_month`)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_invoice_beneficiary_level` (`invoice_id`,`beneficiary_id`,`level`),
  KEY `referral_commissions_referral_id_foreign` (`referral_id`),
  KEY `idx_beneficiary_period` (`beneficiary_id`,`period_year`,`period_month`),
  KEY `idx_apply_after_at` (`apply_after_at`),
  KEY `idx_status_apply_after` (`status`,`apply_after_at`),
  KEY `idx_beneficiary_status_period` (`beneficiary_id`,`status`,`period_year`,`period_month`),
  KEY `idx_period_yyyymm` (`period_yyyymm`),
  CONSTRAINT `referral_commissions_beneficiary_id_foreign` FOREIGN KEY (`beneficiary_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referral_commissions_referral_id_foreign` FOREIGN KEY (`referral_id`) REFERENCES `referrals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_job_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_job_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_class` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Short class name (no namespace)',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'success | failed',
  `duration_ms` int unsigned NOT NULL COMMENT 'Execution time in milliseconds',
  `records_processed` int unsigned NOT NULL DEFAULT '0',
  `context` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON: payload summary or error message',
  `ran_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_ran_at` (`job_class`,`ran_at`),
  KEY `idx_status_ran_at` (`status`,`ran_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_notification_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_sent` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` timestamp NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '1',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referral_notification_logs_client_id_index` (`client_id`),
  KEY `referral_notification_logs_event_type_index` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_notification_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_notification_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp',
  `body_template` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referral_notification_templates_event_type_unique` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_prospects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_prospects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `embajador_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` enum('shared_link','manual','contact_import','bulk_send') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `status` enum('new','contacted','interested','quoted','converted','lost') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `converted_client_id` bigint unsigned DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `last_contact_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referral_prospects_embajador_id_foreign` (`embajador_id`),
  KEY `referral_prospects_converted_client_id_foreign` (`converted_client_id`),
  CONSTRAINT `referral_prospects_converted_client_id_foreign` FOREIGN KEY (`converted_client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `referral_prospects_embajador_id_foreign` FOREIGN KEY (`embajador_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_rewards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `embajador_id` bigint unsigned NOT NULL,
  `referral_id` bigint unsigned NOT NULL,
  `type` enum('free_month','credit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free_month',
  `plan_value_snapshot` decimal(10,2) NOT NULL,
  `status` enum('pending','available','applied','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `available_at` timestamp NULL DEFAULT NULL,
  `applied_at` timestamp NULL DEFAULT NULL,
  `applied_invoice_id` bigint unsigned DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `warning_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referral_rewards_referral_id_foreign` (`referral_id`),
  KEY `idx_reward_status_expires` (`status`,`expires_at`),
  KEY `idx_reward_embajador_status` (`embajador_id`,`status`),
  CONSTRAINT `referral_rewards_embajador_id_foreign` FOREIGN KEY (`embajador_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referral_rewards_referral_id_foreign` FOREIGN KEY (`referral_id`) REFERENCES `referrals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_active` tinyint NOT NULL DEFAULT '1',
  `threshold_amount` decimal(10,2) NOT NULL DEFAULT '1500.00',
  `duration_months` tinyint NOT NULL DEFAULT '12',
  `max_levels` tinyint NOT NULL DEFAULT '5',
  `program_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Embajadores Meganet',
  `welcome_message` text COLLATE utf8mb4_unicode_ci,
  `share_template_default` text COLLATE utf8mb4_unicode_ci,
  `terms_conditions` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_share_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_share_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `embajador_id` bigint unsigned NOT NULL,
  `channel` enum('whatsapp','sms','email','link_copy','bulk_send') COLLATE utf8mb4_unicode_ci NOT NULL,
  `contacts_count` int NOT NULL DEFAULT '1',
  `message_template` text COLLATE utf8mb4_unicode_ci,
  `shared_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referral_share_logs_embajador_id_foreign` (`embajador_id`),
  CONSTRAINT `referral_share_logs_embajador_id_foreign` FOREIGN KEY (`embajador_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_stats_daily`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_stats_daily` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL COMMENT 'Fecha del snapshot (YYYY-MM-DD)',
  `total_embajadores` int unsigned NOT NULL DEFAULT '0',
  `active_embajadores` int unsigned NOT NULL DEFAULT '0',
  `new_referrals` int unsigned NOT NULL DEFAULT '0',
  `converted_referrals` int unsigned NOT NULL DEFAULT '0',
  `commissions_generated` decimal(12,2) NOT NULL DEFAULT '0.00',
  `commissions_applied` decimal(12,2) NOT NULL DEFAULT '0.00',
  `rewards_generated` int unsigned NOT NULL DEFAULT '0',
  `rewards_applied` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stat_date` (`stat_date`),
  KEY `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referrals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `embajador_id` bigint unsigned NOT NULL,
  `referred_client_id` bigint unsigned NOT NULL,
  `prospect_id` bigint unsigned DEFAULT NULL,
  `chain_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chain_depth` tinyint NOT NULL,
  `referred_threshold_amount_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `referred_threshold_covered_at` timestamp NULL DEFAULT NULL,
  `commission_window_start` timestamp NULL DEFAULT NULL,
  `commissions_paid_count` int NOT NULL DEFAULT '0',
  `status` enum('pending_threshold','active','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_threshold',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referrals_referred_client_id_unique` (`referred_client_id`),
  KEY `referrals_prospect_id_foreign` (`prospect_id`),
  KEY `idx_referral_embajador` (`embajador_id`),
  KEY `idx_referral_chain` (`chain_path`),
  KEY `idx_referral_status` (`status`),
  CONSTRAINT `referrals_embajador_id_foreign` FOREIGN KEY (`embajador_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_prospect_id_foreign` FOREIGN KEY (`prospect_id`) REFERENCES `referral_prospects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `referrals_referred_client_id_foreign` FOREIGN KEY (`referred_client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_descriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_descriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `release_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `release_descriptions_release_id_foreign` (`release_id`),
  CONSTRAINT `release_descriptions_release_id_foreign` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_reversibility_thresholds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_reversibility_thresholds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `criticidad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `umbral_filas` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `release_reversibility_thresholds_criticidad_unique` (`criticidad`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `release_id` bigint unsigned NOT NULL,
  `tabla` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `criticidad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_id_al_snapshot` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `release_snapshots_release_id_tabla_unique` (`release_id`,`tabla`),
  CONSTRAINT `release_snapshots_release_id_foreign` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `releases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `release_date` date NOT NULL,
  `commit_sha` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migracion_desde` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `migracion_hasta` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot_bd` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aplicada_en_dev_at` timestamp NULL DEFAULT NULL,
  `aplicada_en_prod_at` timestamp NULL DEFAULT NULL,
  `reversible` tinyint(1) DEFAULT NULL,
  `reversible_motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `releases_version_unique` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `via` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `email_if_error` text COLLATE utf8mb4_unicode_ci,
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cc_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `html` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reminders_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reminders_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint NOT NULL,
  `activate_reminders` tinyint(1) DEFAULT '0',
  `type_of_message` enum('Mail','SMS','Mail + SMS') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_1_days` int DEFAULT NULL,
  `reminder_2_days` int DEFAULT NULL,
  `reminder_3_days` int DEFAULT NULL,
  `reminder_payment_3` tinyint(1) DEFAULT NULL,
  `reminder_payment_amount` double DEFAULT '0',
  `reminder_payment_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reminders_configurations_client_id_index` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23310 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reported_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reported_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `receiver_account_id` bigint unsigned DEFAULT NULL,
  `method_of_payment_id` bigint unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `clave_rastreo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dedup_fingerprint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titular` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banco_origen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_oxxo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_autorizacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ultimos4_tarjeta` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tecnico_id` bigint unsigned DEFAULT NULL,
  `identified_by_user_id` bigint unsigned DEFAULT NULL,
  `confirmed_by_user_id` bigint unsigned DEFAULT NULL,
  `identification_session_id` bigint unsigned DEFAULT NULL,
  `comprobante_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conciliation_status` enum('pendiente_verificar','verificado','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente_verificar',
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `conciliation_note` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reported_payments_payment_id_foreign` (`payment_id`),
  KEY `reported_payments_receiver_account_id_foreign` (`receiver_account_id`),
  KEY `reported_payments_method_of_payment_id_foreign` (`method_of_payment_id`),
  KEY `reported_payments_verified_by_foreign` (`verified_by`),
  KEY `reported_payments_conciliation_status_index` (`conciliation_status`),
  KEY `reported_payments_client_id_index` (`client_id`),
  KEY `reported_payments_clave_rastreo_index` (`clave_rastreo`),
  KEY `reported_payments_tecnico_id_index` (`tecnico_id`),
  KEY `reported_payments_identification_session_id_index` (`identification_session_id`),
  KEY `reported_payments_dedup_fingerprint_index` (`dedup_fingerprint`),
  CONSTRAINT `reported_payments_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reported_payments_method_of_payment_id_foreign` FOREIGN KEY (`method_of_payment_id`) REFERENCES `method_of_payments` (`id`),
  CONSTRAINT `reported_payments_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reported_payments_receiver_account_id_foreign` FOREIGN KEY (`receiver_account_id`) REFERENCES `portal_pago_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reported_payments_tecnico_id_foreign` FOREIGN KEY (`tecnico_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reported_payments_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roadmap_item_memory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roadmap_item_memory` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `roadmap_item_id` bigint unsigned NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size_bytes` int NOT NULL DEFAULT '0',
  `content_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_count` int NOT NULL DEFAULT '0',
  `summary_last_session` text COLLATE utf8mb4_unicode_ci,
  `last_appended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roadmap_item_memory_roadmap_item_id_unique` (`roadmap_item_id`),
  KEY `roadmap_item_memory_last_appended_at_index` (`last_appended_at`),
  CONSTRAINT `roadmap_item_memory_roadmap_item_id_foreign` FOREIGN KEY (`roadmap_item_id`) REFERENCES `roadmap_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roadmap_item_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roadmap_item_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `roadmap_item_id` bigint unsigned NOT NULL,
  `autor` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nota',
  `resumen` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuerpo` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `rir_item_fecha_idx` (`roadmap_item_id`,`created_at`),
  CONSTRAINT `roadmap_item_reports_roadmap_item_id_foreign` FOREIGN KEY (`roadmap_item_id`) REFERENCES `roadmap_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roadmap_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roadmap_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','in_progress','done','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `priority` enum('alta','media','baja') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urgente` tinyint(1) NOT NULL DEFAULT '0',
  `urgente_at` timestamp NULL DEFAULT NULL,
  `urgente_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `en_desarrollo_humano` tinyint(1) NOT NULL DEFAULT '0',
  `worker_sid` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `estado_previo_claim` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reanudaciones_timeout` smallint unsigned NOT NULL DEFAULT '0',
  `veces_timeouteo` smallint unsigned NOT NULL DEFAULT '0',
  `trabajo_iniciado_at` timestamp NULL DEFAULT NULL,
  `eta_segundos` int DEFAULT NULL,
  `eta_metodo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eta_minutos` smallint unsigned DEFAULT NULL,
  `eta_asignada_at` timestamp NULL DEFAULT NULL,
  `reap_count` int unsigned NOT NULL DEFAULT '0',
  `consulta_supervisor` text COLLATE utf8mb4_unicode_ci,
  `consulta_supervisor_sid` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consulta_supervisor_at` timestamp NULL DEFAULT NULL,
  `consulta_opciones` json DEFAULT NULL,
  `consulta_respuesta` text COLLATE utf8mb4_unicode_ci,
  `consulta_resuelta_at` timestamp NULL DEFAULT NULL,
  `consulta_resuelta_por` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colision_pausada_por` bigint unsigned DEFAULT NULL,
  `colision_pausada_at` timestamp NULL DEFAULT NULL,
  `nivel_riesgo` enum('A','B','C') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nivel_riesgo_origen` enum('interno','externo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interno',
  `frontera_valvula` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frontera_valvula_at` timestamp NULL DEFAULT NULL,
  `automatizacion_override` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hereda',
  `estado_aprobacion` enum('pendiente_revision','aprobado_claude','requiere_irving','rechazado','en_progreso','completado','aprobado_irving','cancelado','aprobado_revisor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente_revision',
  `target_version` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `branch` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `merge_commit` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marcado_version` tinyint(1) NOT NULL DEFAULT '0',
  `revision_ui` tinyint(1) DEFAULT NULL,
  `ui_hint` text COLLATE utf8mb4_unicode_ci,
  `archivado_at` timestamp NULL DEFAULT NULL,
  `archivado_por` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prompt` text COLLATE utf8mb4_unicode_ci,
  `comentarios_claude` text COLLATE utf8mb4_unicode_ci,
  `reporte_tecnico` text COLLATE utf8mb4_unicode_ci,
  `reporte_coloquial` text COLLATE utf8mb4_unicode_ci,
  `opciones` json DEFAULT NULL,
  `opcion_elegida` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_resuelta` tinyint(1) NOT NULL DEFAULT '0',
  `decision_resumen` text COLLATE utf8mb4_unicode_ci,
  `decision_fuente` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_fecha` timestamp NULL DEFAULT NULL,
  `alcance_autorizado` json DEFAULT NULL,
  `fuera_de_alcance` json DEFAULT NULL,
  `siguiente_accion` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requiere_sesion_supervisada` tinyint(1) NOT NULL DEFAULT '0',
  `excluir_pool_automatico` tinyint(1) NOT NULL DEFAULT '0',
  `agendado_para` datetime DEFAULT NULL,
  `bloqueado_por_bucle` tinyint(1) NOT NULL DEFAULT '0',
  `motivo_bloqueo` text COLLATE utf8mb4_unicode_ci,
  `origen_bloqueo` enum('humano','clasificador') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bloqueo_expira_en` timestamp NULL DEFAULT NULL,
  `bloqueo_renovaciones` smallint unsigned NOT NULL DEFAULT '0',
  `escalaciones_fingerprint` json DEFAULT NULL,
  `esperando_merge_irving` tinyint(1) NOT NULL DEFAULT '0',
  `preguntas` json DEFAULT NULL,
  `huecos_spec` json DEFAULT NULL,
  `huecos_medidos_at` timestamp NULL DEFAULT NULL,
  `origen_item_id` bigint unsigned DEFAULT NULL,
  `reabre_item_id` int unsigned DEFAULT NULL,
  `auditor_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtasks` json DEFAULT NULL,
  `log` json DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `revisado_at` timestamp NULL DEFAULT NULL,
  `aprobado_por` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `enlace_revision` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validacion_funcional_requerida` tinyint(1) NOT NULL DEFAULT '0',
  `pendiente_validacion_irving` tinyint(1) NOT NULL DEFAULT '0',
  `validado_por_irving` tinyint(1) NOT NULL DEFAULT '0',
  `validado_at` timestamp NULL DEFAULT NULL,
  `validado_por` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comentario_validacion` text COLLATE utf8mb4_unicode_ci,
  `revision_tecnica` tinyint(1) NOT NULL DEFAULT '0',
  `validacion_brief` json DEFAULT NULL,
  `sin_ui` tinyint(1) NOT NULL DEFAULT '0',
  `sin_ui_motivo` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `roadmap_items_status_index` (`status`),
  KEY `roadmap_items_status_position_index` (`status`,`position`),
  KEY `roadmap_items_modulo_index` (`modulo`),
  KEY `roadmap_items_nivel_riesgo_index` (`nivel_riesgo`),
  KEY `roadmap_items_estado_aprobacion_index` (`estado_aprobacion`),
  KEY `roadmap_items_origen_item_id_index` (`origen_item_id`),
  KEY `roadmap_items_urgente_index` (`urgente`),
  KEY `roadmap_items_en_desarrollo_humano_index` (`en_desarrollo_humano`),
  KEY `roadmap_items_decision_resuelta_index` (`decision_resuelta`),
  KEY `roadmap_items_requiere_sesion_supervisada_index` (`requiere_sesion_supervisada`),
  KEY `roadmap_items_excluir_pool_automatico_index` (`excluir_pool_automatico`),
  KEY `roadmap_items_bloqueado_por_bucle_index` (`bloqueado_por_bucle`),
  KEY `roadmap_items_esperando_merge_irving_index` (`esperando_merge_irving`),
  KEY `roadmap_items_pendiente_validacion_irving_index` (`pendiente_validacion_irving`),
  KEY `ri_consulta_viva_idx` (`consulta_supervisor_at`,`consulta_resuelta_at`),
  KEY `roadmap_items_auditor_fingerprint_idx` (`auditor_fingerprint`),
  KEY `roadmap_items_origen_bloqueo_index` (`origen_bloqueo`),
  KEY `roadmap_items_bloqueo_expira_en_index` (`bloqueo_expira_en`),
  KEY `roadmap_items_automatizacion_override_index` (`automatizacion_override`),
  KEY `roadmap_items_frontera_valvula_index` (`frontera_valvula`),
  KEY `roadmap_items_reabre_item_id_index` (`reabre_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=332 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `routers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `routers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_of_nas` enum('Mikrotik','Cisco','Ubiquiti') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` bigint DEFAULT NULL,
  `physical_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nas_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secret_radius` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pool` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authorization_accounting` enum('PPP(Secrets)/API Acounting','Hostpot(Users)/API accounting','Hostopt(Radius)/Radius') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mikrotik_last_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mikrotik_status_changed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `routers_location_id_index` (`location_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `total` decimal(8,2) NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int NOT NULL,
  `medium_sale_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `seller_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seller_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seller_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seller_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sellers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sellers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status_id` bigint unsigned DEFAULT NULL,
  `type_id` bigint unsigned DEFAULT NULL,
  `balance` decimal(8,2) NOT NULL DEFAULT '0.00',
  `range` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Cobre',
  PRIMARY KEY (`id`),
  KEY `sellers_user_id_foreign` (`user_id`),
  KEY `sellers_status_id_foreign` (`status_id`),
  KEY `sellers_type_id_foreign` (`type_id`),
  CONSTRAINT `sellers_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `seller_status` (`id`),
  CONSTRAINT `sellers_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `seller_types` (`id`),
  CONSTRAINT `sellers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_in_address_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_in_address_lists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serviceable_id` int NOT NULL,
  `serviceable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deployed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12695 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `setting_debt_payment_client_customs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `setting_debt_payment_client_customs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `percent_discount` int NOT NULL,
  `apply_group_of_months` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `setting_debt_payment_client_recurrents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `setting_debt_payment_client_recurrents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `apply_discount` tinyint(1) NOT NULL,
  `percent_discount` int unsigned NOT NULL,
  `apply_group_of_days` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `setting_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `setting_imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `setting_table`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `setting_table` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `table_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `columns` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `setting_table_user_id_foreign` (`user_id`),
  CONSTRAINT `setting_table_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `setting_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `setting_tables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `table_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `columns` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `setting_tables_user_id_foreign` (`user_id`),
  CONSTRAINT `setting_tables_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `social_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_providers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `splitters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `splitters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` int NOT NULL,
  `outputs` int NOT NULL,
  `box_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `states` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `states_id_index` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `store_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sucursals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sucursals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sucursals_name_unique` (`name`),
  UNIQUE KEY `sucursals_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `supplier_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_invoice_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `purchase_type` enum('unit','bulk','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit',
  `quantity` decimal(15,2) NOT NULL,
  `store_price` decimal(15,2) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `bulk_quantity` int unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_invoice_items_supplier_invoice_id_foreign` (`supplier_invoice_id`),
  KEY `supplier_invoice_items_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `supplier_invoice_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `supplier_invoice_items_supplier_invoice_id_foreign` FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `supplier_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `supplier_vendor_id` bigint unsigned DEFAULT NULL,
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date NOT NULL,
  `total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','dispatched','received','cancelled','denied') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_invoices_supplier_id_foreign` (`supplier_id`),
  KEY `supplier_invoices_supplier_vendor_id_foreign` (`supplier_vendor_id`),
  KEY `supplier_invoices_created_by_foreign` (`created_by`),
  CONSTRAINT `supplier_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `supplier_invoices_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `supplier_invoices_supplier_vendor_id_foreign` FOREIGN KEY (`supplier_vendor_id`) REFERENCES `supplier_vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `supplier_product_price_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_product_price_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_product_price_id` bigint unsigned NOT NULL,
  `base_price` decimal(15,2) DEFAULT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `bulk_price` decimal(15,2) DEFAULT NULL,
  `bulk_min_quantity` int unsigned DEFAULT NULL,
  `source` enum('catalog_create','catalog_update','invoice') COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_invoice_id` bigint unsigned DEFAULT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sppr_price_created_at_index` (`supplier_product_price_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `supplier_product_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_product_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `inventory_item_stock_id` bigint unsigned DEFAULT NULL,
  `base_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `price` decimal(15,2) DEFAULT NULL,
  `bulk_price` decimal(15,2) DEFAULT NULL,
  `bulk_min_quantity` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_product_prices_supplier_id_inventory_item_id_unique` (`supplier_id`,`inventory_item_id`),
  KEY `supplier_product_prices_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `supplier_product_prices_inventory_item_stock_id_foreign` (`inventory_item_stock_id`),
  CONSTRAINT `supplier_product_prices_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `supplier_product_prices_inventory_item_stock_id_foreign` FOREIGN KEY (`inventory_item_stock_id`) REFERENCES `inventory_item_stocks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_product_prices_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `supplier_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_vendors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_vendors_supplier_id_foreign` (`supplier_id`),
  CONSTRAINT `supplier_vendors_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rfc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suppliers_created_by_foreign` (`created_by`),
  CONSTRAINT `suppliers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_map_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_map_credentials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `latitude` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `longitude` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zoom` int NOT NULL DEFAULT '16',
  `api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_id` bigint NOT NULL,
  `timeout` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_access` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_router_radius` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `send_name_in_mail` tinyint(1) NOT NULL,
  `cash_desk` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `repository_class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `column_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_column_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_position` tinyint(1) NOT NULL,
  `has_connection` tinyint(1) NOT NULL,
  `in_site` tinyint(1) NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tables_created_by_foreign` (`created_by`),
  KEY `tables_updated_by_foreign` (`updated_by`),
  CONSTRAINT `tables_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tables_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_activity_report_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_activity_report_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint unsigned NOT NULL,
  `colaborador_id` bigint unsigned NOT NULL,
  `quantity_share` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `points_earned` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tarp_report_col_unique` (`report_id`,`colaborador_id`),
  KEY `talento_activity_report_participants_colaborador_id_foreign` (`colaborador_id`),
  CONSTRAINT `talento_activity_report_participants_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_activity_report_participants_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `talento_project_activity_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_activity_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_activity_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unidad',
  `points_per_unit` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `money_per_unit` decimal(10,4) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `required_level_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_activity_types_required_level_id_foreign` (`required_level_id`),
  CONSTRAINT `talento_activity_types_required_level_id_foreign` FOREIGN KEY (`required_level_id`) REFERENCES `talento_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_app_releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_app_releases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version_name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version_code` int unsigned NOT NULL,
  `apk_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint DEFAULT NULL,
  `changelog` text COLLATE utf8mb4_unicode_ci,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `released_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_uuid` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colaborador_id` bigint unsigned NOT NULL,
  `check_in_at` timestamp NOT NULL,
  `check_in_lat` decimal(10,7) DEFAULT NULL,
  `check_in_lng` decimal(10,7) DEFAULT NULL,
  `check_in_site_id` bigint unsigned DEFAULT NULL,
  `check_in_work_order_id` bigint unsigned DEFAULT NULL,
  `check_in_project_id` bigint unsigned DEFAULT NULL,
  `check_in_within_geofence` tinyint(1) NOT NULL DEFAULT '0',
  `check_in_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `check_in_flag_reason` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_end_at` timestamp NULL DEFAULT NULL,
  `check_out_at` timestamp NULL DEFAULT NULL,
  `check_out_lat` decimal(10,7) DEFAULT NULL,
  `check_out_lng` decimal(10,7) DEFAULT NULL,
  `day_type` enum('worked','no_work_company','absent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'worked',
  `status` enum('open','closed','flagged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_attendances_check_in_site_id_foreign` (`check_in_site_id`),
  KEY `talento_attendances_colaborador_id_check_in_at_index` (`colaborador_id`,`check_in_at`),
  KEY `talento_attendances_status_index` (`status`),
  KEY `talento_attendances_check_in_flagged_index` (`check_in_flagged`),
  KEY `attendance_client_uuid_idx` (`client_uuid`),
  CONSTRAINT `talento_attendances_check_in_site_id_foreign` FOREIGN KEY (`check_in_site_id`) REFERENCES `talento_work_sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `talento_attendances_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_caja_baselines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_caja_baselines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `caja_ref` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `olt_onu_id` bigint unsigned DEFAULT NULL,
  `baseline_power_dbm` decimal(5,2) NOT NULL,
  `registered_by` bigint unsigned DEFAULT NULL,
  `registered_at` timestamp NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tcb_caja_time_idx` (`caja_ref`,`registered_at`),
  KEY `talento_caja_baselines_caja_ref_index` (`caja_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_caja_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_caja_inspections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `caja_ref` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `inspected_by` bigint unsigned NOT NULL,
  `photo_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `captured_lat` decimal(10,7) DEFAULT NULL,
  `captured_lng` decimal(10,7) DEFAULT NULL,
  `captured_in_app` tinyint(1) NOT NULL DEFAULT '0',
  `fusion_loss_measured` decimal(5,2) DEFAULT NULL,
  `power_measured` decimal(6,2) DEFAULT NULL,
  `aesthetic_score` tinyint unsigned DEFAULT NULL,
  `ia_flags` json DEFAULT NULL,
  `overall_result` enum('pass','fail','needs_rework') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supervisor_validated` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `talento_caja_inspections_project_id_foreign` (`project_id`),
  KEY `talento_caja_inspections_inspected_by_foreign` (`inspected_by`),
  KEY `tci_caja_time_idx` (`caja_ref`,`created_at`),
  KEY `talento_caja_inspections_caja_ref_index` (`caja_ref`),
  CONSTRAINT `talento_caja_inspections_inspected_by_foreign` FOREIGN KEY (`inspected_by`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_caja_inspections_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `talento_projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_certifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_certifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `exam_attempt_id` bigint unsigned DEFAULT NULL,
  `practical_evaluation_id` bigint unsigned DEFAULT NULL,
  `badge_label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `certified_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tc_col_course_unique` (`colaborador_id`,`course_id`),
  KEY `talento_certifications_course_id_foreign` (`course_id`),
  KEY `talento_certifications_exam_attempt_id_foreign` (`exam_attempt_id`),
  KEY `talento_certifications_practical_evaluation_id_foreign` (`practical_evaluation_id`),
  KEY `tc_col_status_idx` (`colaborador_id`,`status`),
  CONSTRAINT `talento_certifications_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_certifications_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `talento_courses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_certifications_exam_attempt_id_foreign` FOREIGN KEY (`exam_attempt_id`) REFERENCES `talento_exam_attempts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `talento_certifications_practical_evaluation_id_foreign` FOREIGN KEY (`practical_evaluation_id`) REFERENCES `talento_practical_evaluations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_colaboradores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_colaboradores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` enum('interno','externo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interno',
  `categoria_externo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supervisor_id` bigint unsigned DEFAULT NULL,
  `level_id` bigint unsigned DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `curp` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nss` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation_type` enum('indeterminada','determinada','obra') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation_end_date` date DEFAULT NULL,
  `pay_frequency` enum('semanal','quincenal','mensual') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `base_salary` decimal(10,2) DEFAULT NULL,
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `work_days` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `talento_colaboradores_user_id_unique` (`user_id`),
  KEY `talento_colaboradores_supervisor_id_foreign` (`supervisor_id`),
  KEY `talento_colaboradores_status_index` (`status`),
  KEY `talento_colaboradores_type_index` (`type`),
  KEY `talento_colaboradores_department_index` (`department`),
  KEY `talento_colaboradores_level_id_foreign` (`level_id`),
  CONSTRAINT `talento_colaboradores_level_id_foreign` FOREIGN KEY (`level_id`) REFERENCES `talento_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `talento_colaboradores_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `talento_colaboradores_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_compensation_rule_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_compensation_rule_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `rule_id` bigint unsigned NOT NULL,
  `data` json NOT NULL,
  `assigned_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `talento_compensation_rule_history_rule_id_foreign` (`rule_id`),
  KEY `tcrh_col_assigned_idx` (`colaborador_id`,`assigned_at`),
  CONSTRAINT `talento_compensation_rule_history_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_compensation_rule_history_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `talento_compensation_rules` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_compensation_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_compensation_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` enum('technician','seller','counter','all','accounting','support') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `base_salary` decimal(10,2) NOT NULL,
  `period` enum('weekly','biweekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weekly',
  `weekly_quota_units` smallint unsigned NOT NULL DEFAULT '0',
  `variable_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kpi_key` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formula_config` json DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `monthly_cutoff_day` tinyint unsigned DEFAULT NULL,
  `clawback_days` smallint unsigned DEFAULT NULL,
  `clawback_requires_collection` tinyint(1) DEFAULT NULL,
  `monthly_bonus` json DEFAULT NULL,
  `conditions` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_construction_standards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_construction_standards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('fusion_loss','power','raqueta','organization','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `ideal_value` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_image_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_course_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_course_materials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `type` enum('text','video','reference') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `video_url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_standard_id` bigint unsigned DEFAULT NULL,
  `reference_penalty_type_id` bigint unsigned DEFAULT NULL,
  `order` smallint unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `talento_course_materials_reference_standard_id_foreign` (`reference_standard_id`),
  KEY `talento_course_materials_reference_penalty_type_id_foreign` (`reference_penalty_type_id`),
  KEY `talento_course_materials_course_id_order_index` (`course_id`,`order`),
  CONSTRAINT `talento_course_materials_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `talento_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `talento_course_materials_reference_penalty_type_id_foreign` FOREIGN KEY (`reference_penalty_type_id`) REFERENCES `talento_penalty_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `talento_course_materials_reference_standard_id_foreign` FOREIGN KEY (`reference_standard_id`) REFERENCES `talento_construction_standards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_courses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `department` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_courses_active_order_index` (`active`,`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_credentials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `type` enum('driver_license','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'driver_license',
  `document_number` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` text COLLATE utf8mb4_unicode_ci,
  `issued_at` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `status` enum('valid','expiring','expired','missing') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'missing',
  `alert_weeks_before` tinyint unsigned NOT NULL DEFAULT '8',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tc_col_type_unique` (`colaborador_id`,`type`),
  KEY `tc_status_expires_idx` (`status`,`expires_at`),
  CONSTRAINT `talento_credentials_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_dbm_thresholds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_dbm_thresholds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dbm_min` decimal(6,2) DEFAULT NULL,
  `dbm_max` decimal(6,2) DEFAULT NULL,
  `categoria` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aplica_bono` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_device_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_device_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'android',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_token_unique` (`user_id`,`token`),
  KEY `talento_device_tokens_user_id_index` (`user_id`),
  CONSTRAINT `talento_device_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `device_key` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approval_required` tinyint(1) NOT NULL DEFAULT '0',
  `approved` tinyint(1) NOT NULL DEFAULT '1',
  `bound_at` timestamp NULL DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `talento_devices_device_key_unique` (`device_key`),
  KEY `talento_devices_user_id_revoked_at_index` (`user_id`,`revoked_at`),
  CONSTRAINT `talento_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_document_template_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_document_template_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned NOT NULL,
  `version_number` int unsigned NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tdtv_template_version_unique` (`template_id`,`version_number`),
  CONSTRAINT `talento_document_template_versions_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `talento_document_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_document_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_document_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `current_version_id` bigint unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_document_templates_current_version_id_foreign` (`current_version_id`),
  CONSTRAINT `talento_document_templates_current_version_id_foreign` FOREIGN KEY (`current_version_id`) REFERENCES `talento_document_template_versions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_escalafon_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_escalafon_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `weights` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_evidence_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_evidence_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permite_varias` tinyint(1) NOT NULL DEFAULT '0',
  `requiere_justificacion` tinyint(1) NOT NULL DEFAULT '0',
  `es_lectura_dbm` tinyint(1) NOT NULL DEFAULT '0',
  `es_firma` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_exam_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_exam_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exam_id` bigint unsigned NOT NULL,
  `colaborador_id` bigint unsigned NOT NULL,
  `score` tinyint unsigned NOT NULL DEFAULT '0',
  `passed` tinyint(1) NOT NULL DEFAULT '0',
  `answers` json NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `talento_exam_attempts_exam_id_foreign` (`exam_id`),
  KEY `tea_col_exam_time_idx` (`colaborador_id`,`exam_id`,`attempted_at`),
  CONSTRAINT `talento_exam_attempts_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_exam_attempts_exam_id_foreign` FOREIGN KEY (`exam_id`) REFERENCES `talento_exams` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_exam_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_exam_questions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exam_id` bigint unsigned NOT NULL,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('single','multiple','true_false') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `options` json NOT NULL,
  `correct_answer` json NOT NULL,
  `points` tinyint unsigned NOT NULL DEFAULT '1',
  `order` smallint unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `talento_exam_questions_exam_id_order_index` (`exam_id`,`order`),
  CONSTRAINT `talento_exam_questions_exam_id_foreign` FOREIGN KEY (`exam_id`) REFERENCES `talento_exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_exams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `passing_score` tinyint unsigned NOT NULL DEFAULT '70',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_exams_course_id_foreign` (`course_id`),
  CONSTRAINT `talento_exams_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `talento_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_funds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_funds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `purpose` enum('license','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'license',
  `target_amount` decimal(10,2) NOT NULL,
  `accumulated` decimal(10,2) NOT NULL DEFAULT '0.00',
  `weekly_deduction` decimal(10,2) NOT NULL,
  `status` enum('accumulating','ready','spent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'accumulating',
  `authorized` tinyint(1) NOT NULL DEFAULT '0',
  `authorized_at` timestamp NULL DEFAULT NULL,
  `authorized_by` bigint unsigned DEFAULT NULL,
  `credential_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_funds_credential_id_foreign` (`credential_id`),
  KEY `tf_col_status_idx` (`colaborador_id`,`status`),
  CONSTRAINT `talento_funds_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_funds_credential_id_foreign` FOREIGN KEY (`credential_id`) REFERENCES `talento_credentials` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `talento_ledger_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_ledger_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `type` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `concept` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tle_col_period_idx` (`colaborador_id`,`period_start`,`period_end`),
  KEY `talento_ledger_entries_concept_index` (`concept`),
  KEY `talento_ledger_entries_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  CONSTRAINT `talento_ledger_entries_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_level_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_level_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `level_id` bigint unsigned NOT NULL,
  `reason` enum('promotion','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'promotion',
  `certifications_snapshot` json NOT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `talento_level_assignments_level_id_foreign` (`level_id`),
  KEY `tla_col_time_idx` (`colaborador_id`,`assigned_at`),
  CONSTRAINT `talento_level_assignments_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `talento_level_assignments_level_id_foreign` FOREIGN KEY (`level_id`) REFERENCES `talento_levels` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_levels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rank` tinyint unsigned NOT NULL,
  `required_certifications` json NOT NULL,
  `base_salary` decimal(10,2) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `talento_levels_rank_unique` (`rank`),
  KEY `talento_levels_active_index` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_liquidations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_liquidations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_units` smallint unsigned NOT NULL DEFAULT '0',
  `base_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `overproduction_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `other_credits` decimal(10,2) NOT NULL DEFAULT '0.00',
  `other_debits` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gross_pay` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `talento_liq_colperiod_unique` (`colaborador_id`,`period_start`,`period_end`),
  KEY `talento_liquidations_status_index` (`status`),
  CONSTRAINT `talento_liquidations_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_loans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance` decimal(10,2) NOT NULL,
  `repayment_weekly` decimal(10,2) DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `authorized` tinyint(1) NOT NULL DEFAULT '0',
  `authorized_by` bigint unsigned DEFAULT NULL,
  `authorized_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tl_col_status_idx` (`colaborador_id`,`status`),
  CONSTRAINT `talento_loans_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_location_pings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_location_pings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `attendance_id` bigint unsigned NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `accuracy_m` decimal(7,2) DEFAULT NULL,
  `battery` tinyint unsigned DEFAULT NULL,
  `recorded_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tlp_att_time_idx` (`attendance_id`,`recorded_at`),
  KEY `tlp_col_time_idx` (`colaborador_id`,`recorded_at`),
  CONSTRAINT `talento_location_pings_attendance_id_foreign` FOREIGN KEY (`attendance_id`) REFERENCES `talento_attendances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `talento_location_pings_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_ot_type_evidence_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_ot_type_evidence_requirements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ot_type_id` bigint unsigned NOT NULL,
  `evidence_type_id` bigint unsigned NOT NULL,
  `condition` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tot_ev_req_unique` (`ot_type_id`,`evidence_type_id`),
  KEY `talento_ot_type_evidence_requirements_evidence_type_id_foreign` (`evidence_type_id`),
  CONSTRAINT `talento_ot_type_evidence_requirements_evidence_type_id_foreign` FOREIGN KEY (`evidence_type_id`) REFERENCES `talento_evidence_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `talento_ot_type_evidence_requirements_ot_type_id_foreign` FOREIGN KEY (`ot_type_id`) REFERENCES `talento_work_order_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_penalties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_penalties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `penalty_type_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `applied_by` bigint unsigned NOT NULL,
  `evidence_photo_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `captured_lat` decimal(10,7) DEFAULT NULL,
  `captured_lng` decimal(10,7) DEFAULT NULL,
  `captured_in_app` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('applied','appealed','overturned','upheld') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'applied',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `talento_penalties_penalty_type_id_foreign` (`penalty_type_id`),
  KEY `tp_col_status_idx` (`colaborador_id`,`status`),
  KEY `talento_penalties_applied_by_index` (`applied_by`),
  CONSTRAINT `talento_penalties_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_penalties_penalty_type_id_foreign` FOREIGN KEY (`penalty_type_id`) REFERENCES `talento_penalty_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_penalty_appeals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_penalty_appeals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `penalty_id` bigint unsigned NOT NULL,
  `appealed_by` bigint unsigned NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `decision` enum('overturned','upheld') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_penalty_appeals_penalty_id_index` (`penalty_id`),
  KEY `tpa_reviewer_decision_idx` (`reviewed_by`,`decision`),
  CONSTRAINT `talento_penalty_appeals_penalty_id_foreign` FOREIGN KEY (`penalty_id`) REFERENCES `talento_penalties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_penalty_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_penalty_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('safety','aesthetic','malpractice','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `penalty_kind` enum('event','status') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'event',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reference_image_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_penalty_types_category_active_index` (`category`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_portal_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_portal_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `theme` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'light',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `talento_portal_preferences_user_id_unique` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_practical_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_practical_evaluations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `colaborador_id` bigint unsigned NOT NULL,
  `evaluator_id` bigint unsigned NOT NULL,
  `evidence_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `captured_in_app` tinyint(1) NOT NULL DEFAULT '0',
  `captured_lat` decimal(10,7) DEFAULT NULL,
  `captured_lng` decimal(10,7) DEFAULT NULL,
  `result` enum('approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `evaluated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `talento_practical_evaluations_course_id_foreign` (`course_id`),
  KEY `talento_practical_evaluations_evaluator_id_foreign` (`evaluator_id`),
  KEY `tpe_col_course_idx` (`colaborador_id`,`course_id`),
  CONSTRAINT `talento_practical_evaluations_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_practical_evaluations_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `talento_courses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_practical_evaluations_evaluator_id_foreign` FOREIGN KEY (`evaluator_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_project_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_project_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `activity_type_id` bigint unsigned NOT NULL,
  `planned_quantity` decimal(12,4) NOT NULL,
  `location_notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tpa_proj_type_unique` (`project_id`,`activity_type_id`),
  KEY `talento_project_activities_activity_type_id_foreign` (`activity_type_id`),
  CONSTRAINT `talento_project_activities_activity_type_id_foreign` FOREIGN KEY (`activity_type_id`) REFERENCES `talento_activity_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_project_activities_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `talento_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_project_activity_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_project_activity_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_activity_id` bigint unsigned NOT NULL,
  `reported_by` bigint unsigned NOT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `report_date` date NOT NULL,
  `status` enum('pending','approved','rejected','capped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_quantity` decimal(12,4) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tpar_activity_status_idx` (`project_activity_id`,`status`),
  KEY `tpar_date_idx` (`report_date`),
  CONSTRAINT `talento_project_activity_reports_project_activity_id_foreign` FOREIGN KEY (`project_activity_id`) REFERENCES `talento_project_activities` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_project_deviations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_project_deviations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `colaborador_id` bigint unsigned NOT NULL,
  `detected_lat` decimal(10,7) NOT NULL,
  `detected_lng` decimal(10,7) NOT NULL,
  `deviation_m` decimal(8,1) NOT NULL,
  `sustained_minutes` smallint unsigned NOT NULL,
  `detected_at` timestamp NOT NULL,
  `supervisor_notified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `talento_project_deviations_colaborador_id_foreign` (`colaborador_id`),
  KEY `tpd_proj_time_idx` (`project_id`,`detected_at`),
  CONSTRAINT `talento_project_deviations_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `talento_project_deviations_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `talento_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('planning','active','paused','done') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planning',
  `lead_colaborador_id` bigint unsigned DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `bonus_amount` decimal(10,2) DEFAULT NULL,
  `bonus_scale` json DEFAULT NULL,
  `corridor_path` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_projects_lead_colaborador_id_foreign` (`lead_colaborador_id`),
  KEY `talento_projects_status_index` (`status`),
  CONSTRAINT `talento_projects_lead_colaborador_id_foreign` FOREIGN KEY (`lead_colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_responsibility_windows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_responsibility_windows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `caja_id` bigint unsigned DEFAULT NULL,
  `source_work_order_id` bigint unsigned NOT NULL,
  `starts_at` timestamp NOT NULL,
  `expires_at` timestamp NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_responsibility_windows_source_work_order_id_foreign` (`source_work_order_id`),
  KEY `trw_client_active_idx` (`client_id`,`active`,`expires_at`),
  KEY `trw_col_active_idx` (`colaborador_id`,`active`),
  KEY `idx_t_responsibility_windows_tarea` (`tarea_id`),
  CONSTRAINT `fk_t_responsibility_windows_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `talento_responsibility_windows_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `talento_responsibility_windows_source_work_order_id_foreign` FOREIGN KEY (`source_work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_roadmap_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_roadmap_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phase` tinyint unsigned NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` text COLLATE utf8mb4_unicode_ci,
  `status` enum('backlog','pending','in_progress','done') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'backlog',
  `target_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `talento_roadmap_items_phase_unique` (`phase`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_role_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_role_departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `talento_role_departments_role_name_unique` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_route_deviations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_route_deviations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `route_id` bigint unsigned NOT NULL,
  `colaborador_id` bigint unsigned NOT NULL,
  `detected_lat` decimal(10,7) NOT NULL,
  `detected_lng` decimal(10,7) NOT NULL,
  `deviation_m` decimal(8,1) NOT NULL,
  `sustained_minutes` smallint unsigned NOT NULL,
  `supervisor_notified` tinyint(1) NOT NULL DEFAULT '0',
  `detected_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `trd_route_time_idx` (`route_id`,`detected_at`),
  CONSTRAINT `talento_route_deviations_route_id_foreign` FOREIGN KEY (`route_id`) REFERENCES `talento_routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_route_stops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_route_stops` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `route_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `sequence` smallint unsigned NOT NULL,
  `eta` timestamp NULL DEFAULT NULL,
  `arrived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trs_route_seq_unique` (`route_id`,`sequence`),
  KEY `idx_t_route_stops_tarea` (`tarea_id`),
  KEY `fk_t_route_stops_wo` (`work_order_id`),
  CONSTRAINT `fk_t_route_stops_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_route_stops_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `talento_route_stops_route_id_foreign` FOREIGN KEY (`route_id`) REFERENCES `talento_routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_routes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `status` enum('draft','active','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tr_col_date_unique` (`colaborador_id`,`date`),
  CONSTRAINT `talento_routes_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_settlement_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_settlement_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `settlement_id` bigint unsigned NOT NULL,
  `stock_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `item_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `current_stock` decimal(10,3) NOT NULL,
  `disposition` enum('returned','damaged','missing') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'returned',
  `debit_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_settlement_items_settlement_id_index` (`settlement_id`),
  CONSTRAINT `talento_settlement_items_settlement_id_foreign` FOREIGN KEY (`settlement_id`) REFERENCES `talento_settlements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_settlements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint unsigned NOT NULL,
  `settlement_date` date NOT NULL,
  `status` enum('draft','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `gross_credits` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gross_debits` decimal(10,2) NOT NULL DEFAULT '0.00',
  `net_settlement` decimal(10,2) NOT NULL DEFAULT '0.00',
  `detail` json DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ts_col_status_idx` (`colaborador_id`,`status`),
  CONSTRAINT `talento_settlements_colaborador_id_foreign` FOREIGN KEY (`colaborador_id`) REFERENCES `talento_colaboradores` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_shift_extensions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_shift_extensions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attendance_id` bigint unsigned NOT NULL,
  `minutes` smallint unsigned NOT NULL,
  `reason_category` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_text` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `talento_shift_extensions_attendance_id_foreign` (`attendance_id`),
  CONSTRAINT `talento_shift_extensions_attendance_id_foreign` FOREIGN KEY (`attendance_id`) REFERENCES `talento_attendances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_warranty_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_warranty_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `responsibility_window_id` bigint unsigned DEFAULT NULL,
  `original_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overridden_by` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_t_warranty_overrides_tarea` (`tarea_id`),
  KEY `fk_t_warranty_overrides_wo` (`work_order_id`),
  CONSTRAINT `fk_t_warranty_overrides_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_warranty_overrides_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_work_order_activations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_work_order_activations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `activated_by` bigint unsigned DEFAULT NULL,
  `requested_at` timestamp NOT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `olt_dispatched` tinyint(1) NOT NULL DEFAULT '0',
  `olt_response` json DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_t_wo_activations_tarea` (`tarea_id`),
  KEY `fk_t_wo_activations_wo` (`work_order_id`),
  CONSTRAINT `fk_t_wo_activations_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_wo_activations_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_work_order_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_work_order_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `recorded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_t_wo_activities_tarea` (`tarea_id`),
  KEY `fk_t_wo_activities_wo` (`work_order_id`),
  CONSTRAINT `fk_t_wo_activities_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_wo_activities_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `talento_work_order_incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_work_order_incidents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_uuid` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `motivo` enum('cliente_ausente','sin_acceso','falta_material','olt_sin_senal','riesgo','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `server_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_work_order_incidents_created_by_foreign` (`created_by`),
  KEY `talento_work_order_incidents_work_order_id_index` (`work_order_id`),
  KEY `incident_client_uuid_idx` (`client_uuid`),
  KEY `idx_t_wo_incidents_tarea` (`tarea_id`),
  CONSTRAINT `fk_t_wo_incidents_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_wo_incidents_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `talento_work_order_incidents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_work_order_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_work_order_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_uuid` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `media_client_uuid_idx` (`client_uuid`),
  KEY `idx_t_wo_media_tarea` (`tarea_id`),
  CONSTRAINT `fk_t_wo_media_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_wo_media_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `talento_work_order_media_evidence_type_id_foreign` FOREIGN KEY (`evidence_type_id`) REFERENCES `talento_evidence_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_work_order_signatures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_work_order_signatures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `signer_type` enum('technician','client') COLLATE utf8mb4_unicode_ci NOT NULL,
  `signature_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `signed_lat` decimal(10,7) DEFAULT NULL,
  `signed_lng` decimal(10,7) DEFAULT NULL,
  `signed_at` timestamp NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tarea_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `twos_wo_signer_unique` (`work_order_id`,`signer_type`),
  KEY `idx_t_wo_signatures_tarea` (`tarea_id`),
  CONSTRAINT `fk_t_wo_signatures_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_wo_signatures_wo` FOREIGN KEY (`work_order_id`) REFERENCES `talento_work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_work_order_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_work_order_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points` smallint unsigned NOT NULL DEFAULT '0',
  `is_billable` tinyint(1) NOT NULL DEFAULT '1',
  `requires_validation` tinyint(1) NOT NULL DEFAULT '0',
  `inicia_garantia` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `required_level_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_work_order_types_required_level_id_foreign` (`required_level_id`),
  CONSTRAINT `talento_work_order_types_required_level_id_foreign` FOREIGN KEY (`required_level_id`) REFERENCES `talento_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talento_work_sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talento_work_sites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('bodega','oficina','predio','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'oficina',
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `radius_m` smallint unsigned NOT NULL DEFAULT '200',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `talento_work_sites_active_index` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_closures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_closures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `closed_by` bigint unsigned DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `gps_accuracy` decimal(8,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_closures_task_id_unique` (`task_id`),
  KEY `task_closures_closed_by_index` (`closed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('Alta','Media','Baja') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `base_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_id` bigint unsigned DEFAULT NULL,
  `created_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_notifications_task_id_foreign` (`task_id`),
  KEY `task_notifications_created_id_foreign` (`created_id`),
  CONSTRAINT `task_notifications_created_id_foreign` FOREIGN KEY (`created_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_notifications_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_user_task_id_foreign` (`task_id`),
  KEY `task_user_user_id_foreign` (`user_id`),
  CONSTRAINT `task_user_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2367 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `nota_tecnico` text COLLATE utf8mb4_unicode_ci,
  `olt_onu_id` bigint unsigned DEFAULT NULL,
  `caja_id` bigint unsigned DEFAULT NULL,
  `modem_sn` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` enum('interna','campo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interna',
  `points` smallint unsigned NOT NULL DEFAULT '0',
  `is_billable` tinyint(1) NOT NULL DEFAULT '0',
  `ticket_id` bigint unsigned DEFAULT NULL,
  `talento_type_id` bigint unsigned DEFAULT NULL,
  `client_main_information_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_service_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workflow` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geo_data` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notification` tinyint(1) NOT NULL DEFAULT '0',
  `scheduled` tinyint(1) NOT NULL DEFAULT '0',
  `start_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_to_task_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_from_task_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_verification` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dedicated_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT '0',
  `archived_at` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archived_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finish_at` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `finish_at_first_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_title_index` (`title`),
  KEY `tasks_address_index` (`address`),
  KEY `idx_tasks_assigned_to` (`assigned_to`),
  KEY `idx_tasks_client_main_information_id` (`client_main_information_id`),
  KEY `idx_tasks_project_id` (`project_id`),
  KEY `idx_tasks_partner_id` (`partner_id`),
  KEY `idx_tasks_priority` (`priority`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_archived` (`archived`),
  KEY `idx_tasks_location_id` (`location_id`),
  KEY `idx_tasks_archived_at` (`archived_at`),
  KEY `idx_tasks_finish_at` (`finish_at`),
  KEY `tasks_ticket_id_foreign` (`ticket_id`),
  KEY `tasks_talento_type_id_foreign` (`talento_type_id`),
  KEY `idx_tasks_tipo_status` (`tipo`,`status`),
  KEY `idx_tasks_liquidation` (`tipo`,`validated_at`,`is_billable`),
  CONSTRAINT `tasks_talento_type_id_foreign` FOREIGN KEY (`talento_type_id`) REFERENCES `talento_work_order_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=614 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `taxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_user_team_id_foreign` (`team_id`),
  KEY `team_user_user_id_foreign` (`user_id`),
  CONSTRAINT `team_user_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `team_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `template_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title_template` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_task` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_verification_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `assigned_to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `template_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `list` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_threads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint unsigned NOT NULL,
  `edited_by` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `ticket_thread_id` bigint unsigned DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hidden` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_lead` int DEFAULT NULL COMMENT 'Este es el client_id',
  `priority` int DEFAULT NULL,
  `estado` enum('Nuevo','Trabajo en curso','Resuelto','Esperando al cliente','Esperando al agente','Cerrado','Reciclado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nuevo',
  `group` enum('Cualquier','IT','Finanzas','Ventas') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('Pregunta','Incidente','Problema','Solicitud de función','Cliente potencial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `reporter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reporter_id` int DEFAULT NULL,
  `reporter_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `incoming_customer` int DEFAULT NULL,
  `hidden` tinyint(1) DEFAULT NULL,
  `task` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `star` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shareable` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duplicate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disable_association_closed_status` tinyint(1) DEFAULT NULL,
  `edited_by` int DEFAULT NULL,
  `date_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colony_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `torre_compuerta_cambios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torre_compuerta_cambios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `compuerta` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_antes` text COLLATE utf8mb4_unicode_ci,
  `valor_despues` text COLLATE utf8mb4_unicode_ci,
  `detalle` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_login` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `torre_compuerta_cambios_compuerta_index` (`compuerta`),
  KEY `torre_compuerta_cambios_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `torre_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torre_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nivel_automatizacion` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'estandar',
  `autopilot_max_nivel` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valvula_activa` tinyint(1) NOT NULL DEFAULT '1',
  `valvula_modo` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ablandar',
  `valvula_guarda_termino` tinyint(1) NOT NULL DEFAULT '1',
  `valvula_guarda_razon` tinyint(1) NOT NULL DEFAULT '0',
  `jarvis_icono` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auditor_activo` tinyint(1) NOT NULL DEFAULT '1',
  `auditor_max_por_corrida` tinyint unsigned NOT NULL DEFAULT '10',
  `auditor_cooldown_min` smallint unsigned NOT NULL DEFAULT '15',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `torre_frontera_dura_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torre_frontera_dura_eventos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `roadmap_item_id` bigint unsigned NOT NULL,
  `categoria` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `termino` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `veredicto` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon` text COLLATE utf8mb4_unicode_ci,
  `ocurrido_at` datetime NOT NULL,
  `origen` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'backfill_log',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `torre_fde_item_termino_ts_unique` (`roadmap_item_id`,`termino`,`ocurrido_at`),
  KEY `torre_frontera_dura_eventos_categoria_index` (`categoria`),
  KEY `torre_frontera_dura_eventos_ocurrido_at_index` (`ocurrido_at`),
  CONSTRAINT `torre_frontera_dura_eventos_roadmap_item_id_foreign` FOREIGN KEY (`roadmap_item_id`) REFERENCES `roadmap_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `torre_permiso_decisiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torre_permiso_decisiones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `permiso` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `concedido` tinyint(1) NOT NULL,
  `recomendacion` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contradice_recomendacion` tinyint(1) NOT NULL DEFAULT '0',
  `nota` text COLLATE utf8mb4_unicode_ci,
  `decidido_por` bigint unsigned DEFAULT NULL,
  `decidido_por_login` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decidido_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `torre_permiso_decisiones_permiso_rol_unique` (`permiso`,`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `debit` double DEFAULT NULL,
  `credit` double DEFAULT NULL,
  `account_balance` double NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cantidad` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint NOT NULL,
  `type` enum('debit','credit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` double NOT NULL,
  `iva` int NOT NULL,
  `total` double NOT NULL,
  `from_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `period` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `add_to_invoice` tinyint(1) NOT NULL,
  `company_balance` double NOT NULL,
  `movement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transactionable_id` bigint NOT NULL,
  `transactionable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_payment` tinyint(1) NOT NULL DEFAULT '0',
  `payment_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_from_old` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_to_old` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_payment_id_index` (`payment_id`),
  KEY `transactions_client_id_index` (`client_id`),
  KEY `transactions_date_index` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=217074 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions_sellers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions_sellers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `method_of_payment` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `previous_balance` decimal(8,2) NOT NULL,
  `new_balance` decimal(8,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_sellers_method_of_payment_foreign` (`method_of_payment`),
  KEY `transactions_sellers_seller_id_foreign` (`seller_id`),
  CONSTRAINT `transactions_sellers_method_of_payment_foreign` FOREIGN KEY (`method_of_payment`) REFERENCES `method_of_payments` (`id`),
  CONSTRAINT `transactions_sellers_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transceivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transceivers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('LS simple','LS dual','SC') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `card_id` bigint unsigned NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `trays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` int NOT NULL,
  `box_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `trenche_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trenche_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `width` decimal(8,2) NOT NULL,
  `lenght` decimal(8,2) NOT NULL,
  `depth` decimal(8,2) NOT NULL,
  `brand_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `trenches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trenches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trenche_type_id` bigint unsigned NOT NULL,
  `map_proyect_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `tube_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tube_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('No definido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `diameter` decimal(8,2) NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tube_types_created_by_foreign` (`created_by`),
  KEY `tube_types_updated_by_foreign` (`updated_by`),
  CONSTRAINT `tube_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tube_types_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tubes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tubes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tube_type_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
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
DROP TABLE IF EXISTS `type_billings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `type_billings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `type_billings_id_index` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_column_datatable_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_column_datatable_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `column_datatable_module_id` bigint unsigned NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=869 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_column_dt_expand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_column_dt_expand` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `module_id` bigint unsigned DEFAULT NULL,
  `column` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_column_dt_expand_user_id_foreign` (`user_id`),
  KEY `user_column_dt_expand_module_id_foreign` (`module_id`),
  CONSTRAINT `user_column_dt_expand_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_column_dt_expand_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_relationships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_relationships` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_user_id` bigint unsigned NOT NULL,
  `child_user_id` bigint unsigned NOT NULL,
  `type` enum('padre','tutor','apoderado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'padre',
  `status` enum('activa','inactiva') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activa',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_relationships_parent_user_id_child_user_id_unique` (`parent_user_id`,`child_user_id`),
  KEY `user_relationships_child_user_id_foreign` (`child_user_id`),
  KEY `user_relationships_status_index` (`status`),
  CONSTRAINT `user_relationships_child_user_id_foreign` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_relationships_parent_user_id_foreign` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `father_last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `offboarding_otros_items` json DEFAULT NULL,
  `client_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_municipality` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_postal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rfc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photography` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_seller` tinyint(1) NOT NULL DEFAULT '0',
  `color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colony` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `estado` enum('activo','bloqueado','inactivo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `last_visited_route` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sucursal_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_login_user_unique` (`login_user`),
  KEY `users_name_index` (`name`),
  KEY `users_email_index` (`email`),
  KEY `users_sucursal_id_foreign` (`sucursal_id`),
  CONSTRAINT `users_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3987 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users_avaiables_promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_avaiables_promotions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `promotion_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `users_avaiables_promotions_user_id_foreign` (`user_id`),
  KEY `users_avaiables_promotions_promotion_id_foreign` (`promotion_id`),
  CONSTRAINT `users_avaiables_promotions_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_avaiables_promotions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vigilante_discrepancias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vigilante_discrepancias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `programa` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_supervisor` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_real` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pids_supervisor` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pids_reales` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detectado_at` datetime NOT NULL,
  `resuelto_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vigilante_discrepancias_programa_index` (`programa`),
  KEY `vigilante_discrepancias_resuelto_at_index` (`resuelto_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `voip_configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voip_configuracion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Principal',
  `sip_host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sip_port` smallint unsigned NOT NULL DEFAULT '5060',
  `sip_username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sip_secret` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sip_fromuser` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sip_fromdomain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('activa','inactiva','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inactiva',
  `ultimo_registro_at` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `callerid_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Meganet Telecomunicaciones',
  `callerid_numero` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `voip_extensiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voip_extensiones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `tipo_dispositivo` enum('telefono_ip','softphone') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'softphone',
  `contexto` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'from-internal',
  `codecs` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ulaw,alaw',
  `transporte` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'transport-udp',
  `callerid` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `provisionado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voip_extensiones_numero_unique` (`numero`),
  KEY `voip_extensiones_user_id_foreign` (`user_id`),
  CONSTRAINT `voip_extensiones_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `voip_grupo_extension`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voip_grupo_extension` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `grupo_id` bigint unsigned NOT NULL,
  `extension_id` bigint unsigned NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `voip_grupo_extension_grupo_id_extension_id_unique` (`grupo_id`,`extension_id`),
  KEY `voip_grupo_extension_extension_id_foreign` (`extension_id`),
  CONSTRAINT `voip_grupo_extension_extension_id_foreign` FOREIGN KEY (`extension_id`) REFERENCES `voip_extensiones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `voip_grupo_extension_grupo_id_foreign` FOREIGN KEY (`grupo_id`) REFERENCES `voip_grupos_timbrado` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `voip_grupos_timbrado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voip_grupos_timbrado` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estrategia` enum('ringall','hunt','memoryhunt') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ringall',
  `ring_time` smallint unsigned NOT NULL DEFAULT '20',
  `destino_fallback` enum('buzon','colgar','repetir') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'colgar',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voip_grupos_timbrado_nombre_unique` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `voip_troncales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voip_troncales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proveedor` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` enum('registro','ip') COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` enum('entrante','saliente','ambas') COLLATE utf8mb4_unicode_ci NOT NULL,
  `host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `puerto` smallint unsigned NOT NULL DEFAULT '5060',
  `usuario` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secret` text COLLATE utf8mb4_unicode_ci,
  `contexto` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'from-trunk',
  `did` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grupo_entrante_id` bigint unsigned DEFAULT NULL,
  `codecs` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ulaw,alaw',
  `transporte` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'transport-udp',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `provisionado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voip_troncales_nombre_unique` (`nombre`),
  KEY `voip_troncales_grupo_entrante_id_foreign` (`grupo_entrante_id`),
  CONSTRAINT `voip_troncales_grupo_entrante_id_foreign` FOREIGN KEY (`grupo_entrante_id`) REFERENCES `voip_grupos_timbrado` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `voises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voises` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `update_description` tinyint(1) DEFAULT '0',
  `price` decimal(20,2) NOT NULL,
  `update_service` tinyint(1) DEFAULT '0',
  `type` enum('VoIP','Corregido','Móvil') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `partners` bigint DEFAULT NULL,
  `tax_include` tinyint(1) NOT NULL,
  `tax` bigint NOT NULL,
  `prepaid_period` enum('Mensual','Diario') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rates_to_change` bigint DEFAULT NULL,
  `transaction_category` enum('Servicio','Descuento','Pago','Reembolso','Corrección') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_category_for_calls` enum('Servicio','Descuento','Pago','Reembolso','Corrección') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_category_for_messages` enum('Servicio','Descuento','Pago','Reembolso','Corrección') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_category_for_data` enum('Servicio','Descuento','Pago','Reembolso','Corrección') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `available_in_self_registration` tinyint(1) DEFAULT NULL,
  `bandwidth` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` enum('1','2','3','4','5','6') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_days` bigint DEFAULT NULL,
  `cost_activation` double(8,2) NOT NULL DEFAULT '0.00',
  `cost_instalation` double(8,2) NOT NULL DEFAULT '0.00',
  `cost_instalation_enable` tinyint(1) DEFAULT '0',
  `promotion_enable` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `init_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_date_discount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_value_fixed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warroom_action_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warroom_action_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `meeting_id` bigint unsigned NOT NULL,
  `section_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `assignee_user_id` bigint unsigned DEFAULT NULL,
  `priority` enum('critico','alto','medio','oportunidad','estrategico') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medio',
  `priority_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `linked_task_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warroom_action_items_assignee_user_id_foreign` (`assignee_user_id`),
  KEY `warroom_action_items_meeting_id_section_key_index` (`meeting_id`,`section_key`),
  KEY `warroom_action_items_linked_task_id_index` (`linked_task_id`),
  CONSTRAINT `warroom_action_items_assignee_user_id_foreign` FOREIGN KEY (`assignee_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warroom_action_items_meeting_id_foreign` FOREIGN KEY (`meeting_id`) REFERENCES `warroom_meetings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warroom_insights_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warroom_insights_cache` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `view_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `insights` json NOT NULL,
  `source` enum('ai','rules') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rules',
  `generated_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warroom_insights_cache_view_key_period_unique` (`view_key`,`period`),
  KEY `warroom_insights_cache_generated_at_index` (`generated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warroom_kpi_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warroom_kpi_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kpis` json NOT NULL,
  `snapshot_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warroom_kpi_snapshots_period_unique` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warroom_meeting_attendees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warroom_meeting_attendees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `meeting_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `present` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warroom_meeting_attendees_meeting_id_user_id_unique` (`meeting_id`,`user_id`),
  KEY `warroom_meeting_attendees_user_id_foreign` (`user_id`),
  CONSTRAINT `warroom_meeting_attendees_meeting_id_foreign` FOREIGN KEY (`meeting_id`) REFERENCES `warroom_meetings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warroom_meeting_attendees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warroom_meeting_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warroom_meeting_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `meeting_id` bigint unsigned NOT NULL,
  `section_id` bigint unsigned DEFAULT NULL,
  `kind` enum('pregunta','respuesta','nota') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nota',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warroom_meeting_notes_section_id_foreign` (`section_id`),
  KEY `warroom_meeting_notes_created_by_foreign` (`created_by`),
  KEY `warroom_meeting_notes_meeting_id_created_at_index` (`meeting_id`,`created_at`),
  CONSTRAINT `warroom_meeting_notes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `warroom_meeting_notes_meeting_id_foreign` FOREIGN KEY (`meeting_id`) REFERENCES `warroom_meetings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warroom_meeting_notes_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `warroom_meeting_sections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warroom_meeting_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warroom_meeting_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `meeting_id` bigint unsigned NOT NULL,
  `section_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_position` int NOT NULL,
  `time_planned_seconds` int NOT NULL,
  `time_actual_seconds` int NOT NULL DEFAULT '0',
  `presenter_user_id` bigint unsigned DEFAULT NULL,
  `status` enum('pending','in_progress','completed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warroom_meeting_sections_meeting_id_section_key_unique` (`meeting_id`,`section_key`),
  KEY `warroom_meeting_sections_presenter_user_id_foreign` (`presenter_user_id`),
  CONSTRAINT `warroom_meeting_sections_meeting_id_foreign` FOREIGN KEY (`meeting_id`) REFERENCES `warroom_meetings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warroom_meeting_sections_presenter_user_id_foreign` FOREIGN KEY (`presenter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warroom_meetings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warroom_meetings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Junta operativa',
  `meeting_type` enum('ordinaria','acta') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ordinaria',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `duration_planned_minutes` int NOT NULL DEFAULT '45',
  `duration_actual_seconds` int DEFAULT NULL,
  `status` enum('draft','in_progress','paused','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `current_section_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_section_started_at` timestamp NULL DEFAULT NULL,
  `moderator_user_id` bigint unsigned NOT NULL,
  `settings` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warroom_meetings_moderator_user_id_foreign` (`moderator_user_id`),
  KEY `warroom_meetings_status_started_at_index` (`status`,`started_at`),
  CONSTRAINT `warroom_meetings_moderator_user_id_foreign` FOREIGN KEY (`moderator_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warroom_section_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warroom_section_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_position` int NOT NULL,
  `time_minutes` int NOT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_presenter_role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warroom_section_templates_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` bigint unsigned NOT NULL,
  `contact_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `crm_id` bigint unsigned DEFAULT NULL,
  `collected_data` json DEFAULT NULL,
  `seller_id` bigint unsigned DEFAULT NULL,
  `status` enum('open','closed','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `unread_count` int unsigned NOT NULL DEFAULT '0',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_conversations_instance_id_contact_number_unique` (`instance_id`,`contact_number`),
  KEY `whatsapp_conversations_client_id_index` (`client_id`),
  KEY `whatsapp_conversations_seller_id_index` (`seller_id`),
  KEY `whatsapp_conversations_last_message_at_index` (`last_message_at`),
  KEY `whatsapp_conversations_status_index` (`status`),
  KEY `whatsapp_conversations_crm_id_index` (`crm_id`),
  CONSTRAINT `whatsapp_conversations_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `whatsapp_conversations_crm_id_foreign` FOREIGN KEY (`crm_id`) REFERENCES `crms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `whatsapp_conversations_instance_id_foreign` FOREIGN KEY (`instance_id`) REFERENCES `whatsapp_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_functions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_functions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exclusive` tinyint(1) NOT NULL DEFAULT '1',
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `position` int NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_functions_slug_unique` (`slug`),
  UNIQUE KEY `whatsapp_functions_name_unique` (`name`),
  KEY `whatsapp_functions_active_index` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_identification_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_identification_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'marketing',
  `source_message_id` bigint unsigned DEFAULT NULL,
  `source_conversation_id` bigint unsigned DEFAULT NULL,
  `is_simulation` tinyint(1) NOT NULL DEFAULT '0',
  `extraction_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `message_id` bigint unsigned DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detecting',
  `method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certainty` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_client_id` bigint unsigned DEFAULT NULL,
  `resolved_multiple_services` tinyint(1) NOT NULL DEFAULT '0',
  `applied_at` timestamp NULL DEFAULT NULL,
  `applied_payment_id` bigint unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` bigint unsigned DEFAULT NULL,
  `reject_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `candidate_client_ids` json DEFAULT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `reminders_sent` tinyint unsigned NOT NULL DEFAULT '0',
  `escalated_to` bigint unsigned DEFAULT NULL,
  `escalation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whatsapp_identification_sessions_extraction_id_index` (`extraction_id`),
  KEY `whatsapp_identification_sessions_conversation_id_index` (`conversation_id`),
  KEY `whatsapp_identification_sessions_resolved_client_id_index` (`resolved_client_id`),
  KEY `whatsapp_identification_sessions_state_index` (`state`),
  KEY `whatsapp_identification_sessions_source_index` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_instance_functions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_instance_functions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` bigint unsigned NOT NULL,
  `function_id` bigint unsigned NOT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_instance_functions_instance_id_function_id_unique` (`instance_id`,`function_id`),
  KEY `whatsapp_instance_functions_function_id_foreign` (`function_id`),
  CONSTRAINT `whatsapp_instance_functions_function_id_foreign` FOREIGN KEY (`function_id`) REFERENCES `whatsapp_functions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `whatsapp_instance_functions_instance_id_foreign` FOREIGN KEY (`instance_id`) REFERENCES `whatsapp_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_instances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_instances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instance_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `webhook_secret` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_instance` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `phone_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disconnected',
  `qr_code` text COLLATE utf8mb4_unicode_ci,
  `qr_expires_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_instances_slug_unique` (`slug`),
  KEY `whatsapp_instances_slug_index` (`slug`),
  KEY `whatsapp_instances_active_index` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `instance_id` bigint unsigned NOT NULL,
  `direction` enum('in','out') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_type` enum('text','image','document','audio','video','location','sticker') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `body` text COLLATE utf8mb4_unicode_ci,
  `media_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_size` bigint unsigned DEFAULT NULL,
  `media_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_downloaded_at` timestamp NULL DEFAULT NULL,
  `evolution_message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quoted_message_id` bigint unsigned DEFAULT NULL,
  `status` enum('pending','sent','delivered','read','failed','received','skipped') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `context` json DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_messages_evolution_message_id_unique` (`evolution_message_id`),
  KEY `whatsapp_messages_instance_id_foreign` (`instance_id`),
  KEY `whatsapp_messages_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  KEY `whatsapp_messages_direction_index` (`direction`),
  KEY `whatsapp_messages_status_index` (`status`),
  CONSTRAINT `whatsapp_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `whatsapp_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `whatsapp_messages_instance_id_foreign` FOREIGN KEY (`instance_id`) REFERENCES `whatsapp_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_payment_extractions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_payment_extractions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'marketing',
  `source_message_id` bigint unsigned DEFAULT NULL,
  `source_conversation_id` bigint unsigned DEFAULT NULL,
  `message_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'spei_transfer',
  `source_mime` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `concepto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_pago` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ok` tinyint(1) NOT NULL DEFAULT '0',
  `fields` json DEFAULT NULL,
  `unreadable` json DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw` longtext COLLATE utf8mb4_unicode_ci,
  `extracted_by` bigint unsigned DEFAULT NULL,
  `extracted_at` timestamp NULL DEFAULT NULL,
  `discarded_at` timestamp NULL DEFAULT NULL,
  `discard_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whatsapp_payment_extractions_message_id_index` (`message_id`),
  KEY `whatsapp_payment_extractions_conversation_id_index` (`conversation_id`),
  KEY `whatsapp_payment_extractions_concepto_index` (`concepto`),
  KEY `whatsapp_payment_extractions_discarded_at_index` (`discarded_at`),
  KEY `whatsapp_payment_extractions_source_index` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `work_flows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_flows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `district_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zones_district_id_foreign` (`district_id`),
  CONSTRAINT `zones_district_id_foreign` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

