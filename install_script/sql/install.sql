-- Billing Star: everything a new installation runs, in order. Made by build_sql.php: change the module schema, then rebuild.
-- SECTION: Database structure
-- Billing Star: database structure for a new installation, with only the setup rows the application needs.
-- No customer, user, payment or other business data. Loaded by install_script/index.php before the module schemas.
-- Built from a mysqldump: data rows, definers and auto-increment counters are removed.
SET FOREIGN_KEY_CHECKS=0;
-- MySQL dump 10.13  Distrib 8.4.13, for macos15.8 (x86_64)
--
-- Host: localhost    Database: voipswitch
-- ------------------------------------------------------
-- Server version	8.4.13

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
-- Table structure for table `acc_accounts`
--

DROP TABLE IF EXISTS `acc_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_accounts` (
  `account_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned NOT NULL,
  `account_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` enum('general','cash','bank','receivable','payable','loan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `has_sub_ledger` tinyint(1) NOT NULL DEFAULT '0',
  `bank_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bank_account_no` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `opening_side` enum('Dr','Cr') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Dr',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uq_acc_accounts_code` (`account_code`),
  UNIQUE KEY `uq_acc_accounts_name` (`account_name`),
  KEY `ix_acc_accounts_group` (`group_id`),
  CONSTRAINT `fk_acc_account_group` FOREIGN KEY (`group_id`) REFERENCES `acc_groups` (`group_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_accounts`
--

--
-- Table structure for table `acc_cc_interest_runs`
--

DROP TABLE IF EXISTS `acc_cc_interest_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_cc_interest_runs` (
  `run_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `day_basis` smallint unsigned NOT NULL DEFAULT '365',
  `interest_amount` decimal(15,2) NOT NULL,
  `voucher_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`run_id`),
  KEY `ix_acc_cc_runs_account` (`account_id`,`period_from`,`period_to`),
  KEY `fk_acc_cc_run_voucher` (`voucher_id`),
  CONSTRAINT `fk_acc_cc_run_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_acc_cc_run_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `acc_vouchers` (`voucher_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_cc_interest_runs`
--

--
-- Table structure for table `acc_cc_loan_rates`
--

DROP TABLE IF EXISTS `acc_cc_loan_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_cc_loan_rates` (
  `rate_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `annual_rate` decimal(6,3) NOT NULL,
  `effective_from` date NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`rate_id`),
  UNIQUE KEY `uq_acc_cc_rate` (`account_id`,`effective_from`),
  CONSTRAINT `fk_acc_cc_rate_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_cc_loan_rates`
--

--
-- Table structure for table `acc_cheque_books`
--

DROP TABLE IF EXISTS `acc_cheque_books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_cheque_books` (
  `book_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `book_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `leaf_prefix` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `leaf_from` bigint unsigned NOT NULL,
  `leaf_to` bigint unsigned NOT NULL,
  `leaf_digits` tinyint unsigned NOT NULL DEFAULT '0',
  `issue_date` date NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `approval_status` enum('pending','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`book_id`),
  UNIQUE KEY `uq_acc_cheque_books` (`account_id`,`book_no`),
  CONSTRAINT `fk_acc_cheque_book_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_cheque_books`
--

--
-- Table structure for table `acc_cheque_events`
--

DROP TABLE IF EXISTS `acc_cheque_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_cheque_events` (
  `event_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cheque_id` bigint unsigned NOT NULL,
  `event_date` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  KEY `ix_acc_cheque_events` (`cheque_id`),
  CONSTRAINT `fk_acc_cheque_event` FOREIGN KEY (`cheque_id`) REFERENCES `acc_cheques` (`cheque_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_cheque_events`
--

--
-- Table structure for table `acc_cheque_leaves`
--

DROP TABLE IF EXISTS `acc_cheque_leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_cheque_leaves` (
  `leaf_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `book_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `leaf_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('unused','used','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unused',
  `voucher_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`leaf_id`),
  UNIQUE KEY `uq_acc_cheque_leaf` (`account_id`,`leaf_no`),
  KEY `ix_acc_cheque_leaves_book` (`book_id`),
  CONSTRAINT `fk_acc_cheque_leaf_book` FOREIGN KEY (`book_id`) REFERENCES `acc_cheque_books` (`book_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_cheque_leaves`
--

--
-- Table structure for table `acc_cheques`
--

DROP TABLE IF EXISTS `acc_cheques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_cheques` (
  `cheque_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` enum('received','issued') COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_id` bigint unsigned NOT NULL,
  `line_id` bigint unsigned DEFAULT NULL,
  `bank_account_id` bigint unsigned NOT NULL,
  `party_account_id` bigint unsigned NOT NULL,
  `party_sub_ledger_id` bigint unsigned DEFAULT NULL,
  `party_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cheque_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cheque_date` date DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('pending','honoured','dishonoured','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `status_date` date DEFAULT NULL,
  `reversal_voucher_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cheque_id`),
  KEY `ix_acc_cheques_no` (`cheque_no`),
  KEY `ix_acc_cheques_status` (`status`,`cheque_date`),
  KEY `ix_acc_cheques_voucher` (`voucher_id`),
  CONSTRAINT `fk_acc_cheque_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `acc_vouchers` (`voucher_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_cheques`
--

--
-- Table structure for table `acc_groups`
--

DROP TABLE IF EXISTS `acc_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_groups` (
  `group_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `group_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `group_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nature` enum('asset','liability','equity','income','expense') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `uq_acc_groups_name` (`parent_id`,`group_name`),
  KEY `ix_acc_groups_parent` (`parent_id`),
  CONSTRAINT `fk_acc_group_parent` FOREIGN KEY (`parent_id`) REFERENCES `acc_groups` (`group_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_groups`
--

--
-- Table structure for table `acc_money_receipt_categories`
--

DROP TABLE IF EXISTS `acc_money_receipt_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_money_receipt_categories` (
  `category_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint unsigned DEFAULT NULL COMMENT 'Default account credited',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_acc_mrc_name` (`category_name`),
  KEY `fk_acc_mrc_account` (`account_id`),
  CONSTRAINT `fk_acc_mrc_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_money_receipt_categories`
--

--
-- Table structure for table `acc_overdue_permissions`
--

DROP TABLE IF EXISTS `acc_overdue_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_overdue_permissions` (
  `permission_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sub_ledger_id` bigint unsigned NOT NULL,
  `permitted_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `approved_by` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permission_id`),
  KEY `ix_acc_odp_sub_ledger` (`sub_ledger_id`,`valid_to`),
  CONSTRAINT `fk_acc_odp_sub_ledger` FOREIGN KEY (`sub_ledger_id`) REFERENCES `acc_sub_ledgers` (`sub_ledger_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_overdue_permissions`
--

--
-- Table structure for table `acc_sequences`
--

DROP TABLE IF EXISTS `acc_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_sequences` (
  `doc_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seq_year` smallint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`doc_code`,`seq_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_sequences`
--

--
-- Table structure for table `acc_settings`
--

DROP TABLE IF EXISTS `acc_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_settings` (
  `setting_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_settings`
--

--
-- Table structure for table `acc_sub_ledger_groups`
--

DROP TABLE IF EXISTS `acc_sub_ledger_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_sub_ledger_groups` (
  `slg_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `group_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`slg_id`),
  UNIQUE KEY `uq_acc_slg_name` (`account_id`,`group_name`),
  CONSTRAINT `fk_acc_slg_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_sub_ledger_groups`
--

--
-- Table structure for table `acc_sub_ledgers`
--

DROP TABLE IF EXISTS `acc_sub_ledgers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_sub_ledgers` (
  `sub_ledger_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slg_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `sub_ledger_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_ledger_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `opening_side` enum('Dr','Cr') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Dr',
  `trader_id` bigint unsigned DEFAULT NULL COMMENT 'Link to traders.trader_id when imported',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sub_ledger_id`),
  UNIQUE KEY `uq_acc_sub_ledgers_code` (`sub_ledger_code`),
  UNIQUE KEY `uq_acc_sub_ledgers_trader` (`trader_id`),
  KEY `ix_acc_sub_ledgers_account` (`account_id`),
  KEY `ix_acc_sub_ledgers_name` (`sub_ledger_name`),
  KEY `fk_acc_sl_group` (`slg_id`),
  CONSTRAINT `fk_acc_sl_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_acc_sl_group` FOREIGN KEY (`slg_id`) REFERENCES `acc_sub_ledger_groups` (`slg_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_sub_ledgers`
--

--
-- Table structure for table `acc_voucher_attachments`
--

DROP TABLE IF EXISTS `acc_voucher_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_voucher_attachments` (
  `attachment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voucher_id` bigint unsigned NOT NULL,
  `original_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/octet-stream',
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attachment_id`),
  UNIQUE KEY `uq_acc_voucher_attachments_stored` (`stored_name`),
  KEY `ix_acc_voucher_attachments_voucher` (`voucher_id`),
  CONSTRAINT `fk_acc_voucher_attachment` FOREIGN KEY (`voucher_id`) REFERENCES `acc_vouchers` (`voucher_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_voucher_attachments`
--

--
-- Table structure for table `acc_voucher_lines`
--

DROP TABLE IF EXISTS `acc_voucher_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_voucher_lines` (
  `line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voucher_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `sub_ledger_id` bigint unsigned DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `line_narration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `line_no` smallint unsigned NOT NULL DEFAULT '1',
  `is_posted` tinyint(1) NOT NULL DEFAULT '0',
  `voucher_date` date DEFAULT NULL,
  `basis_date` date DEFAULT NULL,
  `branch_id` int unsigned DEFAULT NULL,
  `transaction_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cheque_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cheque_date` date DEFAULT NULL,
  `invoice_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`line_id`),
  KEY `ix_acc_lines_voucher` (`voucher_id`),
  KEY `ix_acc_lines_account` (`account_id`),
  KEY `ix_acc_lines_sub_ledger` (`sub_ledger_id`),
  KEY `ix_acc_l_post_acct` (`is_posted`,`account_id`,`voucher_date`,`debit`,`credit`),
  KEY `ix_acc_l_post_sub` (`is_posted`,`sub_ledger_id`,`voucher_date`,`debit`,`credit`),
  KEY `ix_acc_l_post_acct_b` (`is_posted`,`account_id`,`basis_date`,`debit`,`credit`),
  KEY `ix_acc_l_post_sub_b` (`is_posted`,`sub_ledger_id`,`basis_date`,`debit`,`credit`),
  CONSTRAINT `fk_acc_line_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_acc_line_sub_ledger` FOREIGN KEY (`sub_ledger_id`) REFERENCES `acc_sub_ledgers` (`sub_ledger_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_acc_line_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `acc_vouchers` (`voucher_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_voucher_lines`
--

--
-- Table structure for table `acc_vouchers`
--

DROP TABLE IF EXISTS `acc_vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_vouchers` (
  `voucher_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voucher_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_type` enum('debit','credit','journal','contra') COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_date` date NOT NULL,
  `ref_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_person` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `transaction_mode` enum('Cash','Cheque','Bank Transfer','Mobile Banking','Adjustment') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cash',
  `cheque_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cheque_date` date DEFAULT NULL,
  `receipt_category_id` bigint unsigned DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','posted','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `created_by` bigint unsigned DEFAULT NULL,
  `posted_by` bigint unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `branch_id` int unsigned DEFAULT NULL,
  `to_branch_id` int unsigned DEFAULT NULL,
  `is_inter_branch` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`voucher_id`),
  UNIQUE KEY `uq_acc_vouchers_no` (`voucher_no`),
  KEY `ix_acc_vouchers_date` (`voucher_date`),
  KEY `ix_acc_vouchers_status` (`status`,`voucher_type`),
  KEY `fk_acc_voucher_mrc` (`receipt_category_id`),
  KEY `ix_acc_vouchers_status_date` (`status`,`voucher_date`),
  CONSTRAINT `fk_acc_voucher_mrc` FOREIGN KEY (`receipt_category_id`) REFERENCES `acc_money_receipt_categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_vouchers`
--

--
-- Table structure for table `admin_doc_prints`
--

DROP TABLE IF EXISTS `admin_doc_prints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_doc_prints` (
  `doc_table` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `printed_by` int DEFAULT NULL,
  `printed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_table`,`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_doc_prints`
--

--
-- Table structure for table `admin_role`
--

DROP TABLE IF EXISTS `admin_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_role` (
  `user_role_id` int NOT NULL AUTO_INCREMENT,
  `user_config` varchar(20) DEFAULT NULL COMMENT 'adduser-roteuser-visitortracking-createconfig-databasebackup',
  `user` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `role_name` varchar(50) DEFAULT NULL,
  `description` varchar(150) DEFAULT NULL,
  `addby` varchar(50) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `id_reseller` int DEFAULT NULL,
  `reseller_level` int DEFAULT NULL,
  `customer_mgmt` varchar(30) NOT NULL DEFAULT '1,1,1,1,1,1',
  `sales_person_mgmt` varchar(30) NOT NULL DEFAULT '1,1,1,1,1,1',
  PRIMARY KEY (`user_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_role`
--

--
-- Table structure for table `admin_role_permissions`
--

DROP TABLE IF EXISTS `admin_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_role_permissions` (
  `user_role_id` int NOT NULL,
  `form_key` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT '0',
  `can_add` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit` tinyint(1) NOT NULL DEFAULT '0',
  `can_delete` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit_price` tinyint(1) NOT NULL DEFAULT '0',
  `can_additional` tinyint(1) NOT NULL DEFAULT '0',
  `can_serial_list` tinyint(1) NOT NULL DEFAULT '0',
  `can_extra_charges` tinyint(1) NOT NULL DEFAULT '0',
  `can_vat` tinyint(1) NOT NULL DEFAULT '0',
  `can_ait` tinyint(1) NOT NULL DEFAULT '0',
  `can_discount` tinyint(1) NOT NULL DEFAULT '0',
  `can_preview` tinyint(1) NOT NULL DEFAULT '0',
  `can_print` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit_after_app` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit_back_date` tinyint(1) NOT NULL DEFAULT '0',
  `can_advance_date` tinyint(1) NOT NULL DEFAULT '0',
  `can_show_cost` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Actual (purchase) price and cost columns',
  `can_show_price` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Sales price columns in reports',
  `can_print_before_app` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit_after_print` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_role_id`,`form_key`),
  CONSTRAINT `fk_role_perm_role` FOREIGN KEY (`user_role_id`) REFERENCES `admin_role` (`user_role_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_role_permissions`
--

--
-- Table structure for table `admin_user`
--

DROP TABLE IF EXISTS `admin_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `user_password` varchar(100) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `full_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `level` int DEFAULT '0',
  `sesid` varchar(255) DEFAULT NULL,
  `lastaccess` int DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `mobile` varchar(30) NOT NULL DEFAULT '',
  `show_user` smallint DEFAULT '0',
  `user_role_id` int DEFAULT '0',
  `access_level` enum('SuperAdmin','Admin','Supervisor','Operator') NOT NULL DEFAULT 'Operator',
  `user_type` enum('staff','employee','customer') NOT NULL DEFAULT 'staff',
  `linked_id` bigint unsigned DEFAULT NULL COMMENT 'employee: sales_representatives.sr_id, customer: sales_customers.customer_id',
  `department` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_of_birth` date DEFAULT NULL,
  `full_time` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0: sign-in only between start_time and end_time',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `all_branches` tinyint(1) NOT NULL DEFAULT '0',
  `default_branch_id` int unsigned DEFAULT NULL,
  `current_branch_id` int unsigned DEFAULT NULL COMMENT 'Branch picked at sign-in or in the header',
  PRIMARY KEY (`user_id`),
  KEY `ix_admin_user_link` (`user_type`,`linked_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user`
--

--
-- Table structure for table `admin_user_branches`
--

DROP TABLE IF EXISTS `admin_user_branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_branches` (
  `user_id` int NOT NULL,
  `branch_id` int unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`branch_id`),
  KEY `fk_user_branch_branch` (`branch_id`),
  CONSTRAINT `fk_user_branch_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_branch_user` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_branches`
--

--
-- Table structure for table `admin_user_permissions`
--

DROP TABLE IF EXISTS `admin_user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_permissions` (
  `user_id` int NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '0',
  `form_key` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT '0',
  `can_add` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit` tinyint(1) NOT NULL DEFAULT '0',
  `can_delete` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit_price` tinyint(1) NOT NULL DEFAULT '0',
  `can_additional` tinyint(1) NOT NULL DEFAULT '0',
  `can_serial_list` tinyint(1) NOT NULL DEFAULT '0',
  `can_extra_charges` tinyint(1) NOT NULL DEFAULT '0',
  `can_vat` tinyint(1) NOT NULL DEFAULT '0',
  `can_ait` tinyint(1) NOT NULL DEFAULT '0',
  `can_discount` tinyint(1) NOT NULL DEFAULT '0',
  `can_preview` tinyint(1) NOT NULL DEFAULT '0',
  `can_print` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit_after_app` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit_back_date` tinyint(1) NOT NULL DEFAULT '0',
  `can_advance_date` tinyint(1) NOT NULL DEFAULT '0',
  `can_show_cost` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Actual (purchase) price and cost columns',
  `can_show_price` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Sales price columns in reports',
  `can_print_before_app` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit_after_print` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`branch_id`,`form_key`),
  CONSTRAINT `fk_user_perm_user` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_permissions`
--

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `branch_id` int unsigned NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_branch_id` int unsigned DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`branch_id`),
  UNIQUE KEY `uq_branches_name` (`branch_name`),
  KEY `ix_branches_parent` (`parent_branch_id`),
  CONSTRAINT `fk_branch_parent` FOREIGN KEY (`parent_branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

--
-- Temporary view structure for view `challan_line_status`
--

DROP TABLE IF EXISTS `challan_line_status`;
/*!50001 DROP VIEW IF EXISTS `challan_line_status`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `challan_line_status` AS SELECT 
 1 AS `doc_id`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `party_name`,
 1 AS `mobile`,
 1 AS `order_no`,
 1 AS `branch_id`,
 1 AS `line_id`,
 1 AS `line_no`,
 1 AS `product_id`,
 1 AS `product_code`,
 1 AS `model_caption`,
 1 AS `description`,
 1 AS `store_name`,
 1 AS `quantity`,
 1 AS `free_quantity`,
 1 AS `delivered_qty`,
 1 AS `returned_qty`,
 1 AS `billed_qty`,
 1 AS `pending_qty`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `client_theme`
--

DROP TABLE IF EXISTS `client_theme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_theme` (
  `client_theme_id` int NOT NULL AUTO_INCREMENT,
  `service_code` int DEFAULT NULL,
  `index_title` varchar(50) DEFAULT NULL,
  `index_heading` varchar(50) DEFAULT NULL,
  `main_title` varchar(50) DEFAULT NULL,
  `session_idle_time` int DEFAULT NULL,
  `default_record_no` int DEFAULT NULL,
  `brand_name` varchar(50) DEFAULT NULL,
  `slogan` varchar(50) DEFAULT NULL,
  `menu_style` varchar(20) DEFAULT NULL,
  `mobile_view_vsc` varchar(20) DEFAULT NULL,
  `default_theme` varchar(20) DEFAULT NULL,
  `index_bg_color` varchar(20) DEFAULT NULL,
  `menu_bg_color` varchar(20) DEFAULT NULL,
  `footer_left_bg_color` varchar(20) DEFAULT NULL,
  `footer_right_bg_color` varchar(20) DEFAULT NULL,
  `body_bgcolor_color` varchar(20) DEFAULT NULL,
  `mailto` varchar(40) DEFAULT NULL,
  `mailcc` varchar(40) DEFAULT NULL,
  `mailbcc` varchar(40) DEFAULT NULL,
  `mailreport` int DEFAULT NULL,
  `module` varchar(20) NOT NULL DEFAULT '0,0,0,0,0,0,0,0,0,0',
  `customize1` varchar(20) DEFAULT NULL,
  `customize2` varchar(20) DEFAULT NULL,
  `customize3` varchar(20) DEFAULT NULL,
  `customize4` varchar(20) DEFAULT NULL,
  `customize5` varchar(20) DEFAULT NULL,
  `customize6` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`client_theme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_theme`
--

--
-- Temporary view structure for view `customer_accounts`
--

DROP TABLE IF EXISTS `customer_accounts`;
/*!50001 DROP VIEW IF EXISTS `customer_accounts`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `customer_accounts` AS SELECT 
 1 AS `customer_id`,
 1 AS `customer_code`,
 1 AS `customer_group`,
 1 AS `customer_name`,
 1 AS `mobile`,
 1 AS `attention`,
 1 AS `address`,
 1 AS `bin`,
 1 AS `nid`,
 1 AS `trader_id`,
 1 AS `trader_code`,
 1 AS `category_id`,
 1 AS `category_name`,
 1 AS `is_active`,
 1 AS `opening_balance`,
 1 AS `has_credit_rules`,
 1 AS `allow_credit`,
 1 AS `credit_limit`,
 1 AS `credit_days`,
 1 AS `block_sales`,
 1 AS `price_level`,
 1 AS `invoice_due`,
 1 AS `balance`,
 1 AS `total_sales`,
 1 AS `invoice_count`,
 1 AS `last_invoice_date`,
 1 AS `oldest_due_date`,
 1 AS `sr_name`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `customer_ledger_entries`
--

DROP TABLE IF EXISTS `customer_ledger_entries`;
/*!50001 DROP VIEW IF EXISTS `customer_ledger_entries`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `customer_ledger_entries` AS SELECT 
 1 AS `customer_id`,
 1 AS `entry_date`,
 1 AS `sort_order`,
 1 AS `ref_id`,
 1 AS `doc_no`,
 1 AS `entry_type`,
 1 AS `ref_no`,
 1 AS `debit`,
 1 AS `credit`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `inv_doc_lines`
--

DROP TABLE IF EXISTS `inv_doc_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_doc_lines` (
  `line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_id` bigint unsigned NOT NULL,
  `line_no` smallint unsigned NOT NULL DEFAULT '1',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_caption` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `warranty` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_to` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `free_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `prev_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `narration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serials` text COLLATE utf8mb4_unicode_ci COMMENT 'One serial / IMEI per line',
  `source_kind` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'inv = inv_doc_lines, sales_order = sales_order_items',
  `source_line_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`line_id`),
  KEY `ix_inv_lines_doc` (`doc_id`),
  KEY `ix_inv_lines_source` (`source_kind`,`source_line_id`),
  KEY `ix_inv_lines_product` (`product_id`),
  CONSTRAINT `fk_inv_line_doc` FOREIGN KEY (`doc_id`) REFERENCES `inv_docs` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_doc_lines`
--

--
-- Table structure for table `inv_docs`
--

DROP TABLE IF EXISTS `inv_docs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_docs` (
  `doc_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_date` date NOT NULL,
  `ref_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `branch_id` int unsigned DEFAULT NULL,
  `party_type` enum('none','customer','supplier') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `party_id` bigint unsigned DEFAULT NULL,
  `party_group` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `party_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attention` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_person` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `delivery_point` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `additional_info` text COLLATE utf8mb4_unicode_ci,
  `bill_type` enum('Cash','Credit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Credit',
  `source_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `source_doc_id` bigint unsigned DEFAULT NULL,
  `source_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `payment_term` text COLLATE utf8mb4_unicode_ci,
  `required_date` date DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `extra_charges` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `vat_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `cash_received` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `sales_invoice_id` bigint unsigned DEFAULT NULL COMMENT 'Invoice created by Sales Bill From Challan',
  `status` enum('active','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `approval_status` enum('pending','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`),
  UNIQUE KEY `uq_inv_docs_no` (`doc_no`),
  KEY `ix_inv_docs_type_date` (`doc_type`,`doc_date`),
  KEY `ix_inv_docs_approval` (`doc_type`,`approval_status`),
  KEY `ix_inv_docs_mobile` (`mobile`),
  KEY `ix_inv_docs_source` (`source_doc_id`),
  KEY `ix_inv_docs_invoice` (`sales_invoice_id`),
  KEY `ix_inv_docs_party` (`doc_type`,`party_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_docs`
--

--
-- Table structure for table `inv_price_history`
--

DROP TABLE IF EXISTS `inv_price_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_price_history` (
  `history_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `price_field` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'default_unit_price, retail_a..c, dealer_a..c, mrp, lower_price, higher_price',
  `old_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `new_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `changed_by` bigint unsigned DEFAULT NULL,
  `changed_by_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `ix_inv_price_history_product` (`product_id`,`changed_at`),
  CONSTRAINT `fk_inv_price_history_product` FOREIGN KEY (`product_id`) REFERENCES `sales_products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_price_history`
--

--
-- Table structure for table `inv_product_prices`
--

DROP TABLE IF EXISTS `inv_product_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_product_prices` (
  `product_id` bigint unsigned NOT NULL,
  `retail_a` decimal(15,2) NOT NULL DEFAULT '0.00',
  `retail_b` decimal(15,2) NOT NULL DEFAULT '0.00',
  `retail_c` decimal(15,2) NOT NULL DEFAULT '0.00',
  `dealer_a` decimal(15,2) NOT NULL DEFAULT '0.00',
  `dealer_b` decimal(15,2) NOT NULL DEFAULT '0.00',
  `dealer_c` decimal(15,2) NOT NULL DEFAULT '0.00',
  `mrp` decimal(15,2) NOT NULL DEFAULT '0.00',
  `lower_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `higher_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_product_prices`
--

--
-- Table structure for table `inv_sequences`
--

DROP TABLE IF EXISTS `inv_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_sequences` (
  `doc_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seq_year` smallint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`doc_code`,`seq_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_sequences`
--

--
-- Table structure for table `inv_serial_moves`
--

DROP TABLE IF EXISTS `inv_serial_moves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_serial_moves` (
  `serial_move_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serial_id` bigint unsigned NOT NULL,
  `direction` enum('in','out') COLLATE utf8mb4_unicode_ci NOT NULL,
  `move_date` date NOT NULL,
  `doc_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `doc_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `transaction_label` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `party_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `branch_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`serial_move_id`),
  KEY `ix_inv_serial_moves_serial` (`serial_id`,`serial_move_id`),
  KEY `ix_inv_serial_moves_doc` (`doc_id`),
  CONSTRAINT `fk_inv_serial_move_serial` FOREIGN KEY (`serial_id`) REFERENCES `inv_serials` (`serial_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_serial_moves`
--

--
-- Table structure for table `inv_serials`
--

DROP TABLE IF EXISTS `inv_serials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_serials` (
  `serial_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `serial_no` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `in_stock` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`serial_id`),
  UNIQUE KEY `uq_inv_serials` (`product_id`,`serial_no`),
  KEY `ix_inv_serials_no` (`serial_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_serials`
--

--
-- Table structure for table `inv_settings`
--

DROP TABLE IF EXISTS `inv_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_settings` (
  `setting_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_settings`
--

--
-- Table structure for table `inv_stock_moves`
--

DROP TABLE IF EXISTS `inv_stock_moves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_moves` (
  `move_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int unsigned DEFAULT NULL,
  `move_date` date NOT NULL,
  `qty_in` decimal(15,3) NOT NULL DEFAULT '0.000',
  `qty_out` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `doc_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `doc_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `line_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`move_id`),
  KEY `ix_inv_moves_stock` (`product_id`,`store_name`,`qty_in`,`qty_out`),
  KEY `ix_inv_moves_doc` (`doc_id`),
  KEY `ix_inv_moves_line` (`line_id`),
  KEY `ix_inv_moves_date` (`move_date`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_stock_moves`
--

--
-- Table structure for table `inv_transaction_locks`
--

DROP TABLE IF EXISTS `inv_transaction_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_transaction_locks` (
  `lock_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int unsigned DEFAULT NULL,
  `lock_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`lock_id`),
  KEY `ix_inv_locks` (`is_active`,`branch_id`,`lock_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_transaction_locks`
--

--
-- Table structure for table `login_log`
--

DROP TABLE IF EXISTS `login_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_log` (
  `logid` int NOT NULL AUTO_INCREMENT,
  `login_id` varchar(20) DEFAULT NULL,
  `logout_time` datetime DEFAULT NULL,
  `log_date` date DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ip_address` varchar(20) DEFAULT NULL,
  `user_type` int DEFAULT '0',
  PRIMARY KEY (`logid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_log`
--

--
-- Table structure for table `portal_accounts`
--

DROP TABLE IF EXISTS `portal_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_accounts` (
  `account_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `login` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usually the mobile number',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','disabled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '1',
  `failed_attempts` smallint unsigned NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_via` enum('staff','signup') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uq_portal_accounts_customer` (`customer_id`),
  UNIQUE KEY `uq_portal_accounts_login` (`login`),
  CONSTRAINT `fk_portal_account_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_accounts`
--

--
-- Table structure for table `portal_login_log`
--

DROP TABLE IF EXISTS `portal_login_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_login_log` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned DEFAULT NULL,
  `login` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `reason` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `ix_portal_login_log_account` (`account_id`,`created_at`),
  KEY `ix_portal_login_log_ip` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_login_log`
--

--
-- Table structure for table `portal_notices`
--

DROP TABLE IF EXISTS `portal_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_notices` (
  `notice_id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notice_id`),
  KEY `ix_portal_notices_dates` (`is_active`,`starts_on`,`ends_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_notices`
--

--
-- Table structure for table `portal_request_messages`
--

DROP TABLE IF EXISTS `portal_request_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_request_messages` (
  `message_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint unsigned NOT NULL,
  `sender` enum('customer','staff') COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_user_id` bigint unsigned DEFAULT NULL,
  `staff_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `ix_portal_request_messages_request` (`request_id`,`message_id`),
  CONSTRAINT `fk_portal_message_request` FOREIGN KEY (`request_id`) REFERENCES `portal_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_request_messages`
--

--
-- Table structure for table `portal_requests`
--

DROP TABLE IF EXISTS `portal_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_requests` (
  `request_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `account_id` bigint unsigned DEFAULT NULL,
  `request_type` enum('quotation','support','payment','account','password','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `subject` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'For password help sent before signing in',
  `status` enum('open','answered','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `last_reply_by` enum('customer','staff') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uq_portal_requests_no` (`request_no`),
  KEY `ix_portal_requests_customer` (`customer_id`,`created_at`),
  KEY `ix_portal_requests_status` (`status`,`updated_at`),
  CONSTRAINT `fk_portal_request_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_requests`
--

--
-- Table structure for table `portal_settings`
--

DROP TABLE IF EXISTS `portal_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_settings` (
  `setting_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_settings`
--

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `category_id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_product_categories_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

--
-- Table structure for table `product_classifications`
--

DROP TABLE IF EXISTS `product_classifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_classifications` (
  `class_id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_type` enum('group','brand') COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_id`),
  UNIQUE KEY `uq_product_classifications` (`class_type`,`class_name`),
  KEY `fk_product_class_category` (`category_id`),
  CONSTRAINT `fk_product_class_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_classifications`
--

--
-- Table structure for table `product_periodic_warranties`
--

DROP TABLE IF EXISTS `product_periodic_warranties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_periodic_warranties` (
  `pw_id` int unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('product','group','brand') COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `scope_value` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `warranty_id` int unsigned NOT NULL,
  `note` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pw_id`),
  KEY `ix_product_periodic_dates` (`is_active`,`from_date`,`to_date`),
  KEY `fk_periodic_product` (`product_id`),
  KEY `fk_periodic_warranty` (`warranty_id`),
  CONSTRAINT `fk_periodic_product` FOREIGN KEY (`product_id`) REFERENCES `sales_products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_periodic_warranty` FOREIGN KEY (`warranty_id`) REFERENCES `product_warranties` (`warranty_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_periodic_dates` CHECK ((`to_date` >= `from_date`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_periodic_warranties`
--

--
-- Table structure for table `product_price_fixing_lines`
--

DROP TABLE IF EXISTS `product_price_fixing_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_price_fixing_lines` (
  `line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fixing_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `old_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `new_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`line_id`),
  KEY `ix_price_fixing_lines_fixing` (`fixing_id`),
  KEY `ix_price_fixing_lines_product` (`product_id`),
  CONSTRAINT `fk_price_fixing_line_fixing` FOREIGN KEY (`fixing_id`) REFERENCES `product_price_fixings` (`fixing_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_price_fixing_line_product` FOREIGN KEY (`product_id`) REFERENCES `sales_products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_price_fixing_lines`
--

--
-- Table structure for table `product_price_fixings`
--

DROP TABLE IF EXISTS `product_price_fixings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_price_fixings` (
  `fixing_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fixing_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fixing_date` date NOT NULL,
  `price_kind` enum('sales','purchase') COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_field` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `scope_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `method` enum('manual','percent_cost','percent_change','amount_change') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `method_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_count` int unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`fixing_id`),
  UNIQUE KEY `uq_product_price_fixings_no` (`fixing_no`),
  KEY `ix_product_price_fixings_kind` (`price_kind`,`fixing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_price_fixings`
--

--
-- Temporary view structure for view `product_price_list`
--

DROP TABLE IF EXISTS `product_price_list`;
/*!50001 DROP VIEW IF EXISTS `product_price_list`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `product_price_list` AS SELECT 
 1 AS `product_id`,
 1 AS `product_code`,
 1 AS `product_group`,
 1 AS `brand`,
 1 AS `model_caption`,
 1 AS `description`,
 1 AS `unit_name`,
 1 AS `is_active`,
 1 AS `sales_price`,
 1 AS `retail_a`,
 1 AS `retail_b`,
 1 AS `retail_c`,
 1 AS `dealer_a`,
 1 AS `dealer_b`,
 1 AS `dealer_c`,
 1 AS `mrp`,
 1 AS `lower_price`,
 1 AS `higher_price`,
 1 AS `price_updated_at`,
 1 AS `avg_cost`,
 1 AS `last_cost`,
 1 AS `stock_qty`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `product_units`
--

DROP TABLE IF EXISTS `product_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_units` (
  `unit_id` int unsigned NOT NULL AUTO_INCREMENT,
  `unit_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `allow_decimal` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`),
  UNIQUE KEY `uq_product_units_name` (`unit_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_units`
--

--
-- Table structure for table `product_warranties`
--

DROP TABLE IF EXISTS `product_warranties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_warranties` (
  `warranty_id` int unsigned NOT NULL AUTO_INCREMENT,
  `warranty_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` int unsigned NOT NULL DEFAULT '0',
  `period_unit` enum('none','day','month','year') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `terms` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`warranty_id`),
  UNIQUE KEY `uq_product_warranties_name` (`warranty_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_warranties`
--

--
-- Table structure for table `purchase_attachments`
--

DROP TABLE IF EXISTS `purchase_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_attachments` (
  `attachment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_id` bigint unsigned NOT NULL,
  `original_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attachment_id`),
  UNIQUE KEY `uq_purchase_attachments_stored` (`stored_name`),
  KEY `ix_purchase_attachments_doc` (`doc_id`),
  CONSTRAINT `fk_purchase_attachment_doc` FOREIGN KEY (`doc_id`) REFERENCES `inv_docs` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_attachments`
--

--
-- Temporary view structure for view `purchase_due`
--

DROP TABLE IF EXISTS `purchase_due`;
/*!50001 DROP VIEW IF EXISTS `purchase_due`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `purchase_due` AS SELECT 
 1 AS `doc_id`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `party_id`,
 1 AS `party_group`,
 1 AS `party_name`,
 1 AS `bill_type`,
 1 AS `net_amount`,
 1 AS `return_amount`,
 1 AS `paid_amount`,
 1 AS `due_amount`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `purchase_payments`
--

DROP TABLE IF EXISTS `purchase_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_payments` (
  `payment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_id` bigint unsigned NOT NULL,
  `paid_on` date NOT NULL,
  `payment_method` enum('cash','bank','mobile','cheque','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `amount` decimal(15,2) NOT NULL,
  `reference` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_initial` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `ix_purchase_payments_doc` (`doc_id`),
  KEY `ix_purchase_payments_date` (`paid_on`),
  CONSTRAINT `fk_purchase_payment_doc` FOREIGN KEY (`doc_id`) REFERENCES `inv_docs` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_purchase_payment_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_payments`
--

--
-- Table structure for table `purchase_schedules`
--

DROP TABLE IF EXISTS `purchase_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_schedules` (
  `schedule_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_id` bigint unsigned NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `note` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `ix_purchase_schedules_doc` (`doc_id`),
  KEY `ix_purchase_schedules_due` (`due_date`),
  CONSTRAINT `fk_purchase_schedule_doc` FOREIGN KEY (`doc_id`) REFERENCES `inv_docs` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_purchase_schedule_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_schedules`
--

--
-- Temporary view structure for view `rep_inv_docs`
--

DROP TABLE IF EXISTS `rep_inv_docs`;
/*!50001 DROP VIEW IF EXISTS `rep_inv_docs`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_inv_docs` AS SELECT 
 1 AS `doc_id`,
 1 AS `doc_type`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `ref_no`,
 1 AS `branch_id`,
 1 AS `party_type`,
 1 AS `party_id`,
 1 AS `party_group`,
 1 AS `party_name`,
 1 AS `mobile`,
 1 AS `address`,
 1 AS `attention`,
 1 AS `sales_person`,
 1 AS `delivery_point`,
 1 AS `narration`,
 1 AS `bill_type`,
 1 AS `source_type`,
 1 AS `source_doc_id`,
 1 AS `source_no`,
 1 AS `payment_term`,
 1 AS `required_date`,
 1 AS `order_date`,
 1 AS `subtotal`,
 1 AS `extra_charges`,
 1 AS `discount_amount`,
 1 AS `vat_amount`,
 1 AS `net_amount`,
 1 AS `cash_received`,
 1 AS `total_quantity`,
 1 AS `sales_invoice_id`,
 1 AS `status`,
 1 AS `approval_status`,
 1 AS `approved_by`,
 1 AS `approved_at`,
 1 AS `created_by`,
 1 AS `created_at`,
 1 AS `updated_at`,
 1 AS `branch_name`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `rep_inv_line_usage`
--

DROP TABLE IF EXISTS `rep_inv_line_usage`;
/*!50001 DROP VIEW IF EXISTS `rep_inv_line_usage`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_inv_line_usage` AS SELECT 
 1 AS `line_id`,
 1 AS `po_qty`,
 1 AS `purchase_qty`,
 1 AS `return_qty`,
 1 AS `bill_qty`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `rep_inv_lines`
--

DROP TABLE IF EXISTS `rep_inv_lines`;
/*!50001 DROP VIEW IF EXISTS `rep_inv_lines`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_inv_lines` AS SELECT 
 1 AS `doc_id`,
 1 AS `doc_type`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `ref_no`,
 1 AS `branch_id`,
 1 AS `branch_name`,
 1 AS `party_group`,
 1 AS `party_name`,
 1 AS `mobile`,
 1 AS `sales_person`,
 1 AS `bill_type`,
 1 AS `source_type`,
 1 AS `source_no`,
 1 AS `required_date`,
 1 AS `delivery_point`,
 1 AS `approval_status`,
 1 AS `sales_invoice_id`,
 1 AS `doc_narration`,
 1 AS `line_id`,
 1 AS `line_no`,
 1 AS `product_id`,
 1 AS `product_code`,
 1 AS `product_group`,
 1 AS `brand`,
 1 AS `model_caption`,
 1 AS `description`,
 1 AS `unit_name`,
 1 AS `store_name`,
 1 AS `store_to`,
 1 AS `quantity`,
 1 AS `free_quantity`,
 1 AS `unit_price`,
 1 AS `prev_price`,
 1 AS `discount_amount`,
 1 AS `line_total`,
 1 AS `serials`,
 1 AS `line_narration`,
 1 AS `source_kind`,
 1 AS `source_line_id`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `rep_product_cost`
--

DROP TABLE IF EXISTS `rep_product_cost`;
/*!50001 DROP VIEW IF EXISTS `rep_product_cost`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_product_cost` AS SELECT 
 1 AS `product_id`,
 1 AS `avg_cost`,
 1 AS `last_cost`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `rep_sales_invoices`
--

DROP TABLE IF EXISTS `rep_sales_invoices`;
/*!50001 DROP VIEW IF EXISTS `rep_sales_invoices`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_sales_invoices` AS SELECT 
 1 AS `invoice_id`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `ref_no`,
 1 AS `bill_type`,
 1 AS `customer_id`,
 1 AS `party_group`,
 1 AS `party_name`,
 1 AS `mobile`,
 1 AS `sales_person`,
 1 AS `subtotal`,
 1 AS `extra_charges`,
 1 AS `discount_amount`,
 1 AS `vat_amount`,
 1 AS `exchange_amount`,
 1 AS `net_amount`,
 1 AS `received_amount`,
 1 AS `balance_amount`,
 1 AS `total_quantity`,
 1 AS `line_discount`,
 1 AS `branch_name`,
 1 AS `approval_status`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `rep_sales_ledger`
--

DROP TABLE IF EXISTS `rep_sales_ledger`;
/*!50001 DROP VIEW IF EXISTS `rep_sales_ledger`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_sales_ledger` AS SELECT 
 1 AS `doc_date`,
 1 AS `sort_order`,
 1 AS `ref_id`,
 1 AS `doc_no`,
 1 AS `txn_type`,
 1 AS `party_group`,
 1 AS `party_name`,
 1 AS `mobile`,
 1 AS `sales_person`,
 1 AS `debit`,
 1 AS `credit`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `rep_sales_lines`
--

DROP TABLE IF EXISTS `rep_sales_lines`;
/*!50001 DROP VIEW IF EXISTS `rep_sales_lines`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_sales_lines` AS SELECT 
 1 AS `invoice_id`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `bill_type`,
 1 AS `party_group`,
 1 AS `party_name`,
 1 AS `mobile`,
 1 AS `sales_person`,
 1 AS `branch_name`,
 1 AS `approval_status`,
 1 AS `item_id`,
 1 AS `product_id`,
 1 AS `product_code`,
 1 AS `product_group`,
 1 AS `brand`,
 1 AS `model_caption`,
 1 AS `description`,
 1 AS `store_name`,
 1 AS `unit_name`,
 1 AS `serial_imei`,
 1 AS `quantity`,
 1 AS `free_quantity`,
 1 AS `unit_price`,
 1 AS `discount_amount`,
 1 AS `line_total`,
 1 AS `invoice_discount`,
 1 AS `net_amount`,
 1 AS `cost_amount`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `rep_sales_order_lines`
--

DROP TABLE IF EXISTS `rep_sales_order_lines`;
/*!50001 DROP VIEW IF EXISTS `rep_sales_order_lines`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_sales_order_lines` AS SELECT 
 1 AS `order_id`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `delivery_date`,
 1 AS `party_group`,
 1 AS `party_name`,
 1 AS `mobile`,
 1 AS `sales_person`,
 1 AS `status`,
 1 AS `approval_status`,
 1 AS `order_amount`,
 1 AS `advance_received`,
 1 AS `item_id`,
 1 AS `product_id`,
 1 AS `product_code`,
 1 AS `product_group`,
 1 AS `brand`,
 1 AS `model_caption`,
 1 AS `description`,
 1 AS `store_name`,
 1 AS `quantity`,
 1 AS `unit_price`,
 1 AS `discount_amount`,
 1 AS `line_total`,
 1 AS `billed_qty`,
 1 AS `challan_qty`,
 1 AS `pending_qty`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `rep_sales_return_lines`
--

DROP TABLE IF EXISTS `rep_sales_return_lines`;
/*!50001 DROP VIEW IF EXISTS `rep_sales_return_lines`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_sales_return_lines` AS SELECT 
 1 AS `return_id`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `invoice_id`,
 1 AS `invoice_no`,
 1 AS `party_group`,
 1 AS `party_name`,
 1 AS `mobile`,
 1 AS `sales_person`,
 1 AS `approval_status`,
 1 AS `return_item_id`,
 1 AS `product_id`,
 1 AS `product_code`,
 1 AS `product_group`,
 1 AS `brand`,
 1 AS `model_caption`,
 1 AS `description`,
 1 AS `store_name`,
 1 AS `item_condition`,
 1 AS `quantity`,
 1 AS `free_quantity`,
 1 AS `unit_value`,
 1 AS `line_total`,
 1 AS `cost_amount`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `rep_sales_returns`
--

DROP TABLE IF EXISTS `rep_sales_returns`;
/*!50001 DROP VIEW IF EXISTS `rep_sales_returns`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_sales_returns` AS SELECT 
 1 AS `return_id`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `invoice_id`,
 1 AS `invoice_no`,
 1 AS `party_group`,
 1 AS `party_name`,
 1 AS `mobile`,
 1 AS `sales_person`,
 1 AS `reason`,
 1 AS `items_amount`,
 1 AS `deduction_amount`,
 1 AS `return_amount`,
 1 AS `due_adjusted`,
 1 AS `refund_amount`,
 1 AS `total_quantity`,
 1 AS `approval_status`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `rep_stock_moves`
--

DROP TABLE IF EXISTS `rep_stock_moves`;
/*!50001 DROP VIEW IF EXISTS `rep_stock_moves`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `rep_stock_moves` AS SELECT 
 1 AS `move_id`,
 1 AS `doc_date`,
 1 AS `product_id`,
 1 AS `product_code`,
 1 AS `product_group`,
 1 AS `brand`,
 1 AS `model_caption`,
 1 AS `description`,
 1 AS `unit_name`,
 1 AS `store_name`,
 1 AS `branch_id`,
 1 AS `branch_name`,
 1 AS `qty_in`,
 1 AS `qty_out`,
 1 AS `unit_cost`,
 1 AS `doc_type`,
 1 AS `doc_id`,
 1 AS `doc_no`,
 1 AS `party_name`,
 1 AS `ref_no`,
 1 AS `approval_status`,
 1 AS `avg_cost`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `rma_complain_items`
--

DROP TABLE IF EXISTS `rma_complain_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_complain_items` (
  `item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `complain_id` bigint unsigned NOT NULL,
  `line_number` smallint unsigned NOT NULL DEFAULT '1',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sequence_type` enum('not_available','auto_generated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_available',
  `under_warranty` tinyint(1) NOT NULL DEFAULT '1',
  `sales_invoice_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `sales_date` date DEFAULT NULL,
  `sold_serial` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `delivery_date` date DEFAULT NULL,
  `problem_id` int unsigned DEFAULT NULL,
  `problem_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `engineer_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `no_of_time` int unsigned NOT NULL DEFAULT '0',
  `pre_job_ref_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `received_serial` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `amount_charges` decimal(15,2) NOT NULL DEFAULT '0.00',
  `need_diagnosis` tinyint(1) NOT NULL DEFAULT '0',
  `line_status` enum('received','diagnosed','sent_supplier','replaced_in','delivered','settled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  KEY `ix_rma_ci_complain` (`complain_id`),
  KEY `ix_rma_ci_serial` (`received_serial`),
  KEY `ix_rma_ci_status` (`line_status`),
  KEY `fk_rma_ci_product` (`product_id`),
  KEY `fk_rma_ci_problem` (`problem_id`),
  CONSTRAINT `fk_rma_ci_complain` FOREIGN KEY (`complain_id`) REFERENCES `rma_complains` (`complain_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rma_ci_problem` FOREIGN KEY (`problem_id`) REFERENCES `rma_problems` (`problem_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_rma_ci_product` FOREIGN KEY (`product_id`) REFERENCES `sales_products` (`product_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_complain_items`
--

--
-- Table structure for table `rma_complains`
--

DROP TABLE IF EXISTS `rma_complains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_complains` (
  `complain_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `complain_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `complain_date` date NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_group` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text COLLATE utf8mb4_unicode_ci,
  `attention` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('draft','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`complain_id`),
  UNIQUE KEY `uq_rma_complains_no` (`complain_no`),
  KEY `ix_rma_complains_date` (`complain_date`),
  KEY `ix_rma_complains_mobile` (`mobile`),
  KEY `ix_rma_complains_status` (`status`),
  KEY `fk_rma_complain_customer` (`customer_id`),
  CONSTRAINT `fk_rma_complain_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_complains`
--

--
-- Table structure for table `rma_deliveries`
--

DROP TABLE IF EXISTS `rma_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_deliveries` (
  `delivery_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `delivery_date` date NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_group` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text COLLATE utf8mb4_unicode_ci,
  `total_receivable` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_payable` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_settled` tinyint(1) NOT NULL DEFAULT '0',
  `settled_at` datetime DEFAULT NULL,
  `status` enum('draft','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`delivery_id`),
  UNIQUE KEY `uq_rma_dl_no` (`delivery_no`),
  KEY `ix_rma_dl_date` (`delivery_date`),
  KEY `ix_rma_dl_status` (`status`),
  KEY `fk_rma_dl_customer` (`customer_id`),
  CONSTRAINT `fk_rma_dl_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_deliveries`
--

--
-- Table structure for table `rma_delivery_items`
--

DROP TABLE IF EXISTS `rma_delivery_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_delivery_items` (
  `item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_id` bigint unsigned NOT NULL,
  `line_number` smallint unsigned NOT NULL DEFAULT '1',
  `complain_item_id` bigint unsigned DEFAULT NULL,
  `complain_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `received_product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `received_model_no` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `received_serial` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `received_qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `adjustment_type` enum('without','receivable','payable','product_receivable','product_payable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'without',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_no` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `delivery_qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `receivable` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payable` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`item_id`),
  KEY `ix_rma_dli_dl` (`delivery_id`),
  KEY `ix_rma_dli_complain` (`complain_item_id`),
  CONSTRAINT `fk_rma_dli_complain` FOREIGN KEY (`complain_item_id`) REFERENCES `rma_complain_items` (`item_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_rma_dli_dl` FOREIGN KEY (`delivery_id`) REFERENCES `rma_deliveries` (`delivery_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_delivery_items`
--

--
-- Table structure for table `rma_diagnoses`
--

DROP TABLE IF EXISTS `rma_diagnoses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_diagnoses` (
  `diagnosis_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `diagnosis_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `diagnosis_date` date NOT NULL,
  `complain_item_id` bigint unsigned NOT NULL,
  `engineer_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `findings` text COLLATE utf8mb4_unicode_ci,
  `result` enum('repairable','replace','no_fault','beyond_repair') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'repairable',
  `estimated_charge` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`diagnosis_id`),
  UNIQUE KEY `uq_rma_diagnoses_no` (`diagnosis_no`),
  KEY `ix_rma_diag_item` (`complain_item_id`),
  CONSTRAINT `fk_rma_diag_item` FOREIGN KEY (`complain_item_id`) REFERENCES `rma_complain_items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_diagnoses`
--

--
-- Table structure for table `rma_problems`
--

DROP TABLE IF EXISTS `rma_problems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_problems` (
  `problem_id` int unsigned NOT NULL AUTO_INCREMENT,
  `problem_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`problem_id`),
  UNIQUE KEY `uq_rma_problems_name` (`problem_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_problems`
--

--
-- Table structure for table `rma_replace_in_items`
--

DROP TABLE IF EXISTS `rma_replace_in_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_replace_in_items` (
  `item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `replace_in_id` bigint unsigned NOT NULL,
  `line_number` smallint unsigned NOT NULL DEFAULT '1',
  `replace_out_item_id` bigint unsigned DEFAULT NULL,
  `out_job_ref_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_model_no` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_serial` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `adjustment_type` enum('without','receivable','payable','product_receivable','product_payable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'without',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `serial_no` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `receivable` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payable` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`item_id`),
  KEY `ix_rma_rii_ri` (`replace_in_id`),
  KEY `ix_rma_rii_roi` (`replace_out_item_id`),
  CONSTRAINT `fk_rma_rii_ri` FOREIGN KEY (`replace_in_id`) REFERENCES `rma_replace_ins` (`replace_in_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rma_rii_roi` FOREIGN KEY (`replace_out_item_id`) REFERENCES `rma_replace_out_items` (`item_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_replace_in_items`
--

--
-- Table structure for table `rma_replace_ins`
--

DROP TABLE IF EXISTS `rma_replace_ins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_replace_ins` (
  `replace_in_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `replace_in_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `replace_date` date NOT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `supplier_group` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `supplier_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text COLLATE utf8mb4_unicode_ci,
  `narration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `total_receivable` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_payable` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`replace_in_id`),
  UNIQUE KEY `uq_rma_ri_no` (`replace_in_no`),
  KEY `ix_rma_ri_date` (`replace_date`),
  KEY `ix_rma_ri_status` (`status`),
  KEY `fk_rma_ri_supplier` (`supplier_id`),
  CONSTRAINT `fk_rma_ri_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `rma_suppliers` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_replace_ins`
--

--
-- Table structure for table `rma_replace_out_items`
--

DROP TABLE IF EXISTS `rma_replace_out_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_replace_out_items` (
  `item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `replace_out_id` bigint unsigned NOT NULL,
  `line_number` smallint unsigned NOT NULL DEFAULT '1',
  `complain_item_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sequence_type` enum('not_available','available') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_available',
  `under_warranty` tinyint(1) NOT NULL DEFAULT '1',
  `serial_common` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_from` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_to` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `no_of_time` int unsigned NOT NULL DEFAULT '0',
  `pre_job_ref_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `problem_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `purchase_inv_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `purchase_date` date DEFAULT NULL,
  `purchase_serial` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_serial` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `line_status` enum('sent','received','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  PRIMARY KEY (`item_id`),
  KEY `ix_rma_roi_ro` (`replace_out_id`),
  KEY `ix_rma_roi_complain` (`complain_item_id`),
  KEY `ix_rma_roi_serial` (`out_serial`),
  CONSTRAINT `fk_rma_roi_complain` FOREIGN KEY (`complain_item_id`) REFERENCES `rma_complain_items` (`item_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_rma_roi_ro` FOREIGN KEY (`replace_out_id`) REFERENCES `rma_replace_outs` (`replace_out_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_replace_out_items`
--

--
-- Table structure for table `rma_replace_outs`
--

DROP TABLE IF EXISTS `rma_replace_outs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_replace_outs` (
  `replace_out_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `replace_out_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `replace_date` date NOT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `supplier_group` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `supplier_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text COLLATE utf8mb4_unicode_ci,
  `attention` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('draft','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`replace_out_id`),
  UNIQUE KEY `uq_rma_ro_no` (`replace_out_no`),
  KEY `ix_rma_ro_date` (`replace_date`),
  KEY `ix_rma_ro_status` (`status`),
  KEY `fk_rma_ro_supplier` (`supplier_id`),
  CONSTRAINT `fk_rma_ro_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `rma_suppliers` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_replace_outs`
--

--
-- Table structure for table `rma_sequences`
--

DROP TABLE IF EXISTS `rma_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_sequences` (
  `doc_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seq_year` smallint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`doc_type`,`seq_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_sequences`
--

--
-- Table structure for table `rma_service_bill_items`
--

DROP TABLE IF EXISTS `rma_service_bill_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_service_bill_items` (
  `item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` bigint unsigned NOT NULL,
  `line_number` smallint unsigned NOT NULL DEFAULT '1',
  `complain_item_id` bigint unsigned DEFAULT NULL,
  `complain_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_no` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `service_charge` decimal(15,2) NOT NULL DEFAULT '0.00',
  `parts_charge` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`item_id`),
  KEY `ix_rma_sbi_bill` (`bill_id`),
  KEY `fk_rma_sbi_complain` (`complain_item_id`),
  CONSTRAINT `fk_rma_sbi_bill` FOREIGN KEY (`bill_id`) REFERENCES `rma_service_bills` (`bill_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rma_sbi_complain` FOREIGN KEY (`complain_item_id`) REFERENCES `rma_complain_items` (`item_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_service_bill_items`
--

--
-- Table structure for table `rma_service_bills`
--

DROP TABLE IF EXISTS `rma_service_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_service_bills` (
  `bill_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bill_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bill_date` date NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_group` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text COLLATE utf8mb4_unicode_ci,
  `narration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`bill_id`),
  UNIQUE KEY `uq_rma_sb_no` (`bill_no`),
  KEY `ix_rma_sb_date` (`bill_date`),
  KEY `ix_rma_sb_status` (`status`),
  KEY `fk_rma_sb_customer` (`customer_id`),
  CONSTRAINT `fk_rma_sb_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_service_bills`
--

--
-- Table structure for table `rma_stock_ledger`
--

DROP TABLE IF EXISTS `rma_stock_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_stock_ledger` (
  `ledger_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `txn_date` date NOT NULL,
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category` enum('faulty','good') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'faulty',
  `serial_no` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qty_in` decimal(15,3) NOT NULL DEFAULT '0.000',
  `qty_out` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `doc_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `doc_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `party_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ledger_id`),
  KEY `ix_rma_sl_date` (`txn_date`),
  KEY `ix_rma_sl_store` (`store_name`),
  KEY `ix_rma_sl_model` (`model_no`),
  KEY `ix_rma_sl_group_brand` (`product_group`,`brand`),
  KEY `ix_rma_sl_doc` (`doc_type`,`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_stock_ledger`
--

--
-- Table structure for table `rma_store_settings`
--

DROP TABLE IF EXISTS `rma_store_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_store_settings` (
  `purpose` enum('complain_receive','replace_out','replace_in','delivery','utilize') COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_store_settings`
--

--
-- Table structure for table `rma_suppliers`
--

DROP TABLE IF EXISTS `rma_suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_suppliers` (
  `supplier_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_group` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `supplier_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attention` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`),
  UNIQUE KEY `uq_rma_suppliers_name` (`supplier_name`),
  KEY `ix_rma_suppliers_mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_suppliers`
--

--
-- Table structure for table `rma_utilize_items`
--

DROP TABLE IF EXISTS `rma_utilize_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_utilize_items` (
  `item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `utilize_id` bigint unsigned NOT NULL,
  `line_number` smallint unsigned NOT NULL DEFAULT '1',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_no` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`item_id`),
  KEY `ix_rma_ui_utilize` (`utilize_id`),
  CONSTRAINT `fk_rma_ui_utilize` FOREIGN KEY (`utilize_id`) REFERENCES `rma_utilizes` (`utilize_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_utilize_items`
--

--
-- Table structure for table `rma_utilizes`
--

DROP TABLE IF EXISTS `rma_utilizes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_utilizes` (
  `utilize_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `utilize_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `utilize_date` date NOT NULL,
  `complain_item_id` bigint unsigned DEFAULT NULL,
  `job_ref_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `engineer_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`utilize_id`),
  UNIQUE KEY `uq_rma_utilizes_no` (`utilize_no`),
  KEY `ix_rma_util_item` (`complain_item_id`),
  CONSTRAINT `fk_rma_util_item` FOREIGN KEY (`complain_item_id`) REFERENCES `rma_complain_items` (`item_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_utilizes`
--

--
-- Table structure for table `sales_customers`
--

DROP TABLE IF EXISTS `sales_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_customers` (
  `customer_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_group` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attention` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text COLLATE utf8mb4_unicode_ci,
  `bin` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nid` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `branch_id` int unsigned DEFAULT NULL COMMENT 'Owning store branch (NULL = Head Office)',
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uq_sales_customers_code` (`customer_code`),
  KEY `ix_sales_customers_name` (`customer_name`),
  KEY `ix_sales_customers_mobile` (`mobile`),
  KEY `ix_sales_customers_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_customers`
--

--
-- Table structure for table `sales_doc_attachments`
--

DROP TABLE IF EXISTS `sales_doc_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_doc_attachments` (
  `attachment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_kind` enum('invoice','order') COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `original_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attachment_id`),
  UNIQUE KEY `uq_sales_doc_attachments_stored` (`stored_name`),
  KEY `ix_sales_doc_attachments_doc` (`doc_kind`,`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_doc_attachments`
--

--
-- Table structure for table `sales_doc_sequences`
--

DROP TABLE IF EXISTS `sales_doc_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_doc_sequences` (
  `doc_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence_year` smallint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`doc_type`,`sequence_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_doc_sequences`
--

--
-- Table structure for table `sales_invoice_exchanges`
--

DROP TABLE IF EXISTS `sales_invoice_exchanges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoice_exchanges` (
  `exchange_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_no` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `quantity` decimal(15,3) NOT NULL DEFAULT '1.000',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`exchange_id`),
  KEY `ix_sales_invoice_exchanges_invoice` (`invoice_id`),
  CONSTRAINT `fk_sales_exchange_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_exchanges`
--

--
-- Table structure for table `sales_invoice_items`
--

DROP TABLE IF EXISTS `sales_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoice_items` (
  `item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_caption` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_narration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `warranty` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `sequence_type` enum('not_available','available','bulk') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_available',
  `serial_clear` tinyint(1) NOT NULL DEFAULT '0',
  `serial_storage` tinyint(1) NOT NULL DEFAULT '0',
  `serial_common` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_from` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_to` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_imei` text COLLATE utf8mb4_unicode_ci,
  `quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `free_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Average cost when sold',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `line_number` smallint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  KEY `ix_sales_items_invoice` (`invoice_id`),
  KEY `ix_sales_items_product` (`product_id`),
  KEY `ix_sales_items_model` (`product_group`,`brand`,`model_caption`),
  CONSTRAINT `fk_sales_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_item_product` FOREIGN KEY (`product_id`) REFERENCES `sales_products` (`product_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_items`
--

--
-- Table structure for table `sales_invoice_payments`
--

DROP TABLE IF EXISTS `sales_invoice_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoice_payments` (
  `payment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `paid_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_method` enum('cash','bank','mobile','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `amount` decimal(15,2) NOT NULL,
  `reference` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `received_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `ix_sales_payments_invoice` (`invoice_id`),
  CONSTRAINT `fk_sales_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_payments`
--

--
-- Table structure for table `sales_invoice_schedules`
--

DROP TABLE IF EXISTS `sales_invoice_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoice_schedules` (
  `schedule_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `note` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `ix_sales_schedules_invoice` (`invoice_id`),
  CONSTRAINT `fk_sales_schedule_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_schedules`
--

--
-- Table structure for table `sales_invoice_sequences`
--

DROP TABLE IF EXISTS `sales_invoice_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoice_sequences` (
  `sequence_year` smallint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sequence_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_sequences`
--

--
-- Table structure for table `sales_invoices`
--

DROP TABLE IF EXISTS `sales_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoices` (
  `invoice_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int unsigned DEFAULT NULL,
  `ref_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_date` date NOT NULL,
  `bill_type` enum('Cash','Credit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cash',
  `customer_id` bigint unsigned DEFAULT NULL,
  `member_id` bigint unsigned DEFAULT NULL,
  `customer_group` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attention` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text COLLATE utf8mb4_unicode_ci,
  `delivery_point` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `create_installation` tinyint(1) NOT NULL DEFAULT '0',
  `bin` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nid` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_person` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sr_id` bigint unsigned DEFAULT NULL,
  `narration` text COLLATE utf8mb4_unicode_ci,
  `additional_info` text COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `extra_charges` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `vat_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `ait_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `exchange_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `point_adjust` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Taka value of redeemed points',
  `points_redeemed` decimal(12,2) NOT NULL DEFAULT '0.00',
  `points_earned` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `cash_received` decimal(15,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `status` enum('posted','void') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'posted',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approval_status` enum('pending','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `stock_doc_id` bigint unsigned DEFAULT NULL COMMENT 'inv_docs row (doc_type sales_invoice) holding the stock out',
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `uq_sales_invoices_no` (`invoice_no`),
  KEY `ix_sales_invoices_date` (`sales_date`),
  KEY `ix_sales_invoices_customer` (`customer_id`),
  KEY `ix_sales_invoices_mobile` (`mobile`),
  KEY `ix_sales_invoices_approval` (`approval_status`,`sales_date`),
  KEY `ix_sales_invoices_stock` (`stock_doc_id`),
  KEY `ix_sales_invoices_sr` (`sr_id`,`sales_date`),
  CONSTRAINT `fk_sales_invoice_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoices`
--

--
-- Table structure for table `sales_member_points`
--

DROP TABLE IF EXISTS `sales_member_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_member_points` (
  `entry_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `entry_date` date NOT NULL,
  `kind` enum('earn','redeem','adjust') COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` decimal(12,2) NOT NULL,
  `note` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`entry_id`),
  KEY `ix_sales_member_points_member` (`member_id`),
  KEY `ix_sales_member_points_invoice` (`invoice_id`),
  CONSTRAINT `fk_sales_member_point_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_member_point_member` FOREIGN KEY (`member_id`) REFERENCES `sales_members` (`member_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_member_points`
--

--
-- Table structure for table `sales_members`
--

DROP TABLE IF EXISTS `sales_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_members` (
  `member_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `member_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `opening_points` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `uq_sales_members_no` (`member_no`),
  KEY `ix_sales_members_mobile` (`mobile`),
  KEY `fk_sales_member_customer` (`customer_id`),
  CONSTRAINT `fk_sales_member_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_members`
--

--
-- Temporary view structure for view `sales_mobile_history`
--

DROP TABLE IF EXISTS `sales_mobile_history`;
/*!50001 DROP VIEW IF EXISTS `sales_mobile_history`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `sales_mobile_history` AS SELECT 
 1 AS `doc_kind`,
 1 AS `doc_id`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `ref_no`,
 1 AS `customer_id`,
 1 AS `party_name`,
 1 AS `party_group`,
 1 AS `mobile`,
 1 AS `mobile_key`,
 1 AS `amount`,
 1 AS `paid`,
 1 AS `due`,
 1 AS `total_quantity`,
 1 AS `status`,
 1 AS `bill_type`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `sales_order_adjustment_lines`
--

DROP TABLE IF EXISTS `sales_order_adjustment_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_adjustment_lines` (
  `line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_id` bigint unsigned NOT NULL,
  `order_item_id` bigint unsigned DEFAULT NULL,
  `action` enum('added','changed','removed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `old_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `new_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `old_unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `new_unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `old_discount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `new_discount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `old_line_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `new_line_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`line_id`),
  KEY `ix_sales_order_adjustment_lines_adj` (`adjustment_id`),
  CONSTRAINT `fk_sales_order_adjustment_line_adj` FOREIGN KEY (`adjustment_id`) REFERENCES `sales_order_adjustments` (`adjustment_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_adjustment_lines`
--

--
-- Table structure for table `sales_order_adjustments`
--

DROP TABLE IF EXISTS `sales_order_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_adjustments` (
  `adjustment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `adjustment_date` date NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `new_subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `old_net_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `new_net_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `old_advance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `new_advance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `old_delivery_date` date DEFAULT NULL,
  `new_delivery_date` date DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`adjustment_id`),
  UNIQUE KEY `uq_sales_order_adjustments_no` (`adjustment_no`),
  KEY `ix_sales_order_adjustments_order` (`order_id`),
  KEY `ix_sales_order_adjustments_date` (`adjustment_date`),
  CONSTRAINT `fk_sales_order_adjustment_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_adjustments`
--

--
-- Table structure for table `sales_order_bill_items`
--

DROP TABLE IF EXISTS `sales_order_bill_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_bill_items` (
  `bill_item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` bigint unsigned NOT NULL,
  `order_item_id` bigint unsigned NOT NULL,
  `invoice_item_id` bigint unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  PRIMARY KEY (`bill_item_id`),
  KEY `ix_sales_order_bill_items_bill` (`bill_id`),
  KEY `ix_sales_order_bill_items_order_item` (`order_item_id`),
  KEY `ix_sales_order_bill_items_invoice_item` (`invoice_item_id`),
  CONSTRAINT `fk_sales_order_bill_item_bill` FOREIGN KEY (`bill_id`) REFERENCES `sales_order_bills` (`bill_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_order_bill_item_invoice_item` FOREIGN KEY (`invoice_item_id`) REFERENCES `sales_invoice_items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_order_bill_item_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `sales_order_items` (`item_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_bill_items`
--

--
-- Table structure for table `sales_order_bills`
--

DROP TABLE IF EXISTS `sales_order_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_bills` (
  `bill_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `advance_applied` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`bill_id`),
  UNIQUE KEY `uq_sales_order_bills_invoice` (`invoice_id`),
  KEY `ix_sales_order_bills_order` (`order_id`),
  CONSTRAINT `fk_sales_order_bill_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_order_bill_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`order_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_bills`
--

--
-- Temporary view structure for view `sales_order_item_balance`
--

DROP TABLE IF EXISTS `sales_order_item_balance`;
/*!50001 DROP VIEW IF EXISTS `sales_order_item_balance`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `sales_order_item_balance` AS SELECT 
 1 AS `order_item_id`,
 1 AS `order_id`,
 1 AS `ordered_quantity`,
 1 AS `billed_quantity`,
 1 AS `remaining_quantity`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `sales_order_items`
--

DROP TABLE IF EXISTS `sales_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_items` (
  `item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_caption` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `warranty` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `line_number` smallint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  KEY `ix_sales_order_items_order` (`order_id`),
  KEY `ix_sales_order_items_product` (`product_id`),
  CONSTRAINT `fk_sales_order_item_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_order_item_product` FOREIGN KEY (`product_id`) REFERENCES `sales_products` (`product_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_items`
--

--
-- Table structure for table `sales_order_schedules`
--

DROP TABLE IF EXISTS `sales_order_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_schedules` (
  `schedule_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `payment_day` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `ix_sales_order_schedules_order` (`order_id`),
  CONSTRAINT `fk_sales_order_schedule_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_schedules`
--

--
-- Table structure for table `sales_order_sequences`
--

DROP TABLE IF EXISTS `sales_order_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_sequences` (
  `sequence_year` smallint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sequence_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_sequences`
--

--
-- Table structure for table `sales_orders`
--

DROP TABLE IF EXISTS `sales_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_orders` (
  `order_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int unsigned DEFAULT NULL,
  `ref_no` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `order_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `bill_type` enum('Cash','Credit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Credit',
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_group` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attention` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` text COLLATE utf8mb4_unicode_ci,
  `additional_info` text COLLATE utf8mb4_unicode_ci,
  `address` text COLLATE utf8mb4_unicode_ci,
  `bin` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nid` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_person` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sr_id` bigint unsigned DEFAULT NULL,
  `delivery_point` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `create_installation` tinyint(1) NOT NULL DEFAULT '0',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `extra_charges` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `vat_ait_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `advance_received` decimal(15,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `status` enum('open','converted','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approval_status` enum('pending','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `portal_account_id` bigint unsigned DEFAULT NULL COMMENT 'sr_portal_accounts row that booked it',
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uq_sales_orders_no` (`order_no`),
  KEY `ix_sales_orders_ref` (`ref_no`),
  KEY `ix_sales_orders_date` (`order_date`),
  KEY `ix_sales_orders_delivery_date` (`delivery_date`),
  KEY `ix_sales_orders_customer` (`customer_id`),
  KEY `ix_sales_orders_mobile` (`mobile`),
  KEY `ix_sales_orders_sr` (`sr_id`),
  KEY `ix_sales_orders_portal` (`portal_account_id`),
  CONSTRAINT `fk_sales_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_orders`
--

--
-- Table structure for table `sales_products`
--

DROP TABLE IF EXISTS `sales_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_products` (
  `product_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `product_group` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_caption` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `warranty` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `default_unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `purchase_price` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Purchase Price Fixing; default cost on purchase screens',
  `reorder_level` decimal(15,3) NOT NULL DEFAULT '0.000',
  `vat_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `serial_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Stock documents must give a serial per unit',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `uq_sales_products_code` (`product_code`),
  KEY `ix_sales_products_name` (`description`),
  KEY `ix_sales_products_model` (`model_caption`),
  KEY `ix_sales_products_group` (`product_group`),
  KEY `ix_sales_products_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_products`
--

--
-- Table structure for table `sales_representatives`
--

DROP TABLE IF EXISTS `sales_representatives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_representatives` (
  `sr_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sr_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sr_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `designation` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `territory_id` int unsigned DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `commission_basis` enum('none','net_sales','collection','gross_profit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'net_sales',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Percent of the basis',
  `target_bonus_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Extra percent of net sales when the month target is reached',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `branch_id` int unsigned DEFAULT NULL COMMENT 'Owning store branch (NULL = Head Office)',
  PRIMARY KEY (`sr_id`),
  UNIQUE KEY `uq_sales_representatives_code` (`sr_code`),
  KEY `ix_sales_representatives_name` (`sr_name`),
  KEY `ix_sales_representatives_territory` (`territory_id`),
  KEY `ix_sales_representatives_branch` (`branch_id`),
  CONSTRAINT `fk_sr_territory` FOREIGN KEY (`territory_id`) REFERENCES `sr_territories` (`territory_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_representatives`
--

--
-- Table structure for table `sales_return_items`
--

DROP TABLE IF EXISTS `sales_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_return_items` (
  `return_item_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `return_id` bigint unsigned NOT NULL,
  `invoice_item_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `serial_imei` text COLLATE utf8mb4_unicode_ci,
  `item_condition` enum('good','damaged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'good',
  `quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `free_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_value` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `line_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`return_item_id`),
  KEY `ix_sales_return_items_return` (`return_id`),
  KEY `ix_sales_return_items_invoice_item` (`invoice_item_id`),
  CONSTRAINT `fk_sales_return_item_invoice_item` FOREIGN KEY (`invoice_item_id`) REFERENCES `sales_invoice_items` (`item_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_return_item_return` FOREIGN KEY (`return_id`) REFERENCES `sales_returns` (`return_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_return_items`
--

--
-- Table structure for table `sales_returns`
--

DROP TABLE IF EXISTS `sales_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_returns` (
  `return_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `return_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `return_date` date NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `items_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `deduction_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `return_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `due_adjusted` decimal(15,2) NOT NULL DEFAULT '0.00',
  `refund_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `refund_method` enum('cash','bank','mobile','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `refund_reference` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `total_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `status` enum('posted','void') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'posted',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approval_status` enum('pending','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `stock_doc_id` bigint unsigned DEFAULT NULL COMMENT 'inv_docs row (doc_type sales_return) holding the stock in',
  PRIMARY KEY (`return_id`),
  UNIQUE KEY `uq_sales_returns_no` (`return_no`),
  KEY `ix_sales_returns_invoice` (`invoice_id`),
  KEY `ix_sales_returns_date` (`return_date`),
  KEY `ix_sales_returns_mobile` (`mobile`),
  KEY `fk_sales_return_customer` (`customer_id`),
  CONSTRAINT `fk_sales_return_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_return_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_returns`
--

--
-- Table structure for table `sales_stores`
--

DROP TABLE IF EXISTS `sales_stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_stores` (
  `store_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`store_id`),
  UNIQUE KEY `uq_sales_stores_name` (`store_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_stores`
--

--
-- Table structure for table `sr_collection_allocations`
--

DROP TABLE IF EXISTS `sr_collection_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_collection_allocations` (
  `allocation_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `collection_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `payment_id` bigint unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  PRIMARY KEY (`allocation_id`),
  KEY `ix_sr_collection_alloc_collection` (`collection_id`),
  KEY `ix_sr_collection_alloc_invoice` (`invoice_id`),
  CONSTRAINT `fk_sr_collection_alloc_collection` FOREIGN KEY (`collection_id`) REFERENCES `sr_collections` (`collection_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_collection_allocations`
--

--
-- Table structure for table `sr_collections`
--

DROP TABLE IF EXISTS `sr_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_collections` (
  `collection_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `collection_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sr_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `collected_on` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank','mobile','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `reference` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Cheque, transaction or receipt number',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('pending','verified','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `staff_note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`collection_id`),
  UNIQUE KEY `uq_sr_collections_no` (`collection_no`),
  KEY `ix_sr_collections_sr` (`sr_id`,`collected_on`),
  KEY `ix_sr_collections_status` (`status`,`created_at`),
  KEY `fk_sr_collection_customer` (`customer_id`),
  CONSTRAINT `fk_sr_collection_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_collection_sr` FOREIGN KEY (`sr_id`) REFERENCES `sales_representatives` (`sr_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_sr_collection_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_collections`
--

--
-- Table structure for table `sr_commission_payments`
--

DROP TABLE IF EXISTS `sr_commission_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_commission_payments` (
  `payment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sr_id` bigint unsigned NOT NULL,
  `period_month` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid_on` date NOT NULL,
  `payment_method` enum('cash','bank','mobile','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `reference` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `note` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `ix_sr_commission_payments` (`sr_id`,`period_month`),
  CONSTRAINT `fk_sr_commission_payment_sr` FOREIGN KEY (`sr_id`) REFERENCES `sales_representatives` (`sr_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_sr_commission_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_commission_payments`
--

--
-- Table structure for table `sr_portal_accounts`
--

DROP TABLE IF EXISTS `sr_portal_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_portal_accounts` (
  `account_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sr_id` bigint unsigned NOT NULL,
  `login` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','disabled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '1',
  `can_book_orders` tinyint(1) NOT NULL DEFAULT '1',
  `can_collect` tinyint(1) NOT NULL DEFAULT '1',
  `see_all_customers` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Supervisor / office staff: every customer, not only the SR''s own',
  `failed_attempts` smallint unsigned NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uq_sr_portal_accounts_sr` (`sr_id`),
  UNIQUE KEY `uq_sr_portal_accounts_login` (`login`),
  CONSTRAINT `fk_sr_portal_account_sr` FOREIGN KEY (`sr_id`) REFERENCES `sales_representatives` (`sr_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_portal_accounts`
--

--
-- Table structure for table `sr_portal_login_log`
--

DROP TABLE IF EXISTS `sr_portal_login_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_portal_login_log` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned DEFAULT NULL,
  `login` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `reason` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `ix_sr_portal_login_log_account` (`account_id`,`created_at`),
  KEY `ix_sr_portal_login_log_ip` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_portal_login_log`
--

--
-- Table structure for table `sr_targets`
--

DROP TABLE IF EXISTS `sr_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_targets` (
  `target_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sr_id` bigint unsigned NOT NULL,
  `target_month` date NOT NULL,
  `sales_target` decimal(15,2) NOT NULL DEFAULT '0.00',
  `collection_target` decimal(15,2) NOT NULL DEFAULT '0.00',
  `note` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`target_id`),
  UNIQUE KEY `uq_sr_targets` (`sr_id`,`target_month`),
  CONSTRAINT `fk_sr_target_sr` FOREIGN KEY (`sr_id`) REFERENCES `sales_representatives` (`sr_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_sr_target_month` CHECK ((dayofmonth(`target_month`) = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_targets`
--

--
-- Table structure for table `sr_territories`
--

DROP TABLE IF EXISTS `sr_territories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_territories` (
  `territory_id` int unsigned NOT NULL AUTO_INCREMENT,
  `territory_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `territory_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `parent_id` int unsigned DEFAULT NULL COMMENT 'Wider territory this one is part of',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`territory_id`),
  UNIQUE KEY `uq_sr_territories_name` (`territory_name`),
  KEY `fk_sr_territory_parent` (`parent_id`),
  CONSTRAINT `fk_sr_territory_parent` FOREIGN KEY (`parent_id`) REFERENCES `sr_territories` (`territory_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_territories`
--

--
-- Table structure for table `store_profiles`
--

DROP TABLE IF EXISTS `store_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_profiles` (
  `store_id` int unsigned NOT NULL AUTO_INCREMENT,
  `reseller_level` tinyint unsigned NOT NULL COMMENT '3 = Head Office, 2 = store',
  `reseller_id` int NOT NULL,
  `store_type` enum('head_office','warehouse','retail_shop') COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int unsigned NOT NULL,
  `stock_store` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sales_stores.store_name this store sells from',
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`store_id`),
  UNIQUE KEY `uq_store_profiles_reseller` (`reseller_level`,`reseller_id`),
  UNIQUE KEY `uq_store_profiles_code` (`store_code`),
  UNIQUE KEY `uq_store_profiles_branch` (`branch_id`),
  CONSTRAINT `fk_store_profile_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `store_profiles`
--

--
-- Table structure for table `trader_attachments`
--

DROP TABLE IF EXISTS `trader_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trader_attachments` (
  `attachment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trader_id` bigint unsigned NOT NULL,
  `original_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/octet-stream',
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attachment_id`),
  UNIQUE KEY `uq_trader_attachments_stored` (`stored_name`),
  KEY `ix_trader_attachments_trader` (`trader_id`),
  CONSTRAINT `fk_trader_attachment_trader` FOREIGN KEY (`trader_id`) REFERENCES `traders` (`trader_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trader_attachments`
--

--
-- Table structure for table `trader_categories`
--

DROP TABLE IF EXISTS `trader_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trader_categories` (
  `category_id` tinyint unsigned NOT NULL,
  `category_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` enum('customer','supplier') COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_prefix` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_trader_categories_name` (`category_name`),
  UNIQUE KEY `uq_trader_categories_prefix` (`code_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trader_categories`
--

--
-- Table structure for table `trader_conversions`
--

DROP TABLE IF EXISTS `trader_conversions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trader_conversions` (
  `conversion_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trader_id` bigint unsigned NOT NULL,
  `old_category_id` tinyint unsigned NOT NULL,
  `new_category_id` tinyint unsigned NOT NULL,
  `old_group_id` bigint unsigned DEFAULT NULL,
  `new_group_id` bigint unsigned DEFAULT NULL,
  `old_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `new_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `converted_by` bigint unsigned DEFAULT NULL,
  `converted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`conversion_id`),
  KEY `ix_trader_conversions_trader` (`trader_id`),
  CONSTRAINT `fk_trader_conversion_trader` FOREIGN KEY (`trader_id`) REFERENCES `traders` (`trader_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trader_conversions`
--

--
-- Table structure for table `trader_flags`
--

DROP TABLE IF EXISTS `trader_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trader_flags` (
  `trader_id` bigint unsigned NOT NULL,
  `allow_credit` tinyint(1) NOT NULL DEFAULT '0',
  `credit_limit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit_days` smallint unsigned NOT NULL DEFAULT '0',
  `price_level` enum('retail','wholesale','dealer','special') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'retail',
  `special_discount_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `vat_exempt` tinyint(1) NOT NULL DEFAULT '0',
  `block_sales` tinyint(1) NOT NULL DEFAULT '0',
  `block_purchase` tinyint(1) NOT NULL DEFAULT '0',
  `flag_note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`trader_id`),
  CONSTRAINT `fk_trader_flags_trader` FOREIGN KEY (`trader_id`) REFERENCES `traders` (`trader_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trader_flags`
--

--
-- Table structure for table `trader_groups`
--

DROP TABLE IF EXISTS `trader_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trader_groups` (
  `group_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` tinyint unsigned NOT NULL,
  `group_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `uq_trader_groups_name` (`category_id`,`group_name`),
  CONSTRAINT `fk_trader_group_category` FOREIGN KEY (`category_id`) REFERENCES `trader_categories` (`category_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trader_groups`
--

--
-- Table structure for table `trader_sr_assignments`
--

DROP TABLE IF EXISTS `trader_sr_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trader_sr_assignments` (
  `assignment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trader_id` bigint unsigned NOT NULL,
  `sr_id` bigint unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`assignment_id`),
  KEY `ix_trader_sr_trader` (`trader_id`,`effective_to`),
  KEY `ix_trader_sr_sr` (`sr_id`),
  CONSTRAINT `fk_trader_sr_sr` FOREIGN KEY (`sr_id`) REFERENCES `sales_representatives` (`sr_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_trader_sr_trader` FOREIGN KEY (`trader_id`) REFERENCES `traders` (`trader_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trader_sr_assignments`
--

--
-- Table structure for table `traders`
--

DROP TABLE IF EXISTS `traders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `traders` (
  `trader_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trader_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` tinyint unsigned NOT NULL,
  `group_id` bigint unsigned DEFAULT NULL,
  `trader_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `referrer_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `secondary_mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `phone` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fax` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `web` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contact_person` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `owner_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `security_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bg_cheque_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bin` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nid` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sales_customer_id` bigint unsigned DEFAULT NULL COMMENT 'Copy in sales_customers for customer-type traders',
  `rma_supplier_id` bigint unsigned DEFAULT NULL COMMENT 'Copy in rma_suppliers for supplier-type traders',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`trader_id`),
  UNIQUE KEY `uq_traders_code` (`trader_code`),
  KEY `ix_traders_category` (`category_id`),
  KEY `ix_traders_group` (`group_id`),
  KEY `ix_traders_name` (`trader_name`),
  KEY `ix_traders_mobile` (`mobile`),
  CONSTRAINT `fk_trader_category` FOREIGN KEY (`category_id`) REFERENCES `trader_categories` (`category_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_trader_group` FOREIGN KEY (`group_id`) REFERENCES `trader_groups` (`group_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `traders`
--

--
-- Table structure for table `transaction_categories`
--

DROP TABLE IF EXISTS `transaction_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_categories` (
  `tc_id` int unsigned NOT NULL AUTO_INCREMENT,
  `module` enum('sales','purchase','inventory','accounts','rma') COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tc_id`),
  UNIQUE KEY `uq_transaction_categories` (`module`,`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_categories`
--

--
-- Final view structure for view `challan_line_status`
--

/*!50001 DROP VIEW IF EXISTS `challan_line_status`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `challan_line_status` AS select `d`.`doc_id` AS `doc_id`,`d`.`doc_no` AS `doc_no`,`d`.`doc_date` AS `doc_date`,`d`.`party_name` AS `party_name`,`d`.`mobile` AS `mobile`,`d`.`source_no` AS `order_no`,`d`.`branch_id` AS `branch_id`,`l`.`line_id` AS `line_id`,`l`.`line_no` AS `line_no`,`l`.`product_id` AS `product_id`,`l`.`product_code` AS `product_code`,`l`.`model_caption` AS `model_caption`,`l`.`description` AS `description`,`l`.`store_name` AS `store_name`,`l`.`quantity` AS `quantity`,`l`.`free_quantity` AS `free_quantity`,(`l`.`quantity` + `l`.`free_quantity`) AS `delivered_qty`,coalesce(`r`.`qty`,0) AS `returned_qty`,coalesce(`b`.`qty`,0) AS `billed_qty`,greatest((least(`l`.`quantity`,((`l`.`quantity` + `l`.`free_quantity`) - coalesce(`r`.`qty`,0))) - coalesce(`b`.`qty`,0)),0) AS `pending_qty` from (((`inv_doc_lines` `l` join `inv_docs` `d` on(((`d`.`doc_id` = `l`.`doc_id`) and (`d`.`doc_type` = 'challan') and (`d`.`status` = 'active')))) left join (select `c`.`source_line_id` AS `source_line_id`,sum((`c`.`quantity` + `c`.`free_quantity`)) AS `qty` from (`inv_doc_lines` `c` join `inv_docs` `x` on((`x`.`doc_id` = `c`.`doc_id`))) where ((`x`.`doc_type` = 'challan_return') and (`x`.`status` = 'active') and (`c`.`source_kind` = 'inv')) group by `c`.`source_line_id`) `r` on((`r`.`source_line_id` = `l`.`line_id`))) left join (select `c`.`source_line_id` AS `source_line_id`,sum(`c`.`quantity`) AS `qty` from (`inv_doc_lines` `c` join `inv_docs` `x` on((`x`.`doc_id` = `c`.`doc_id`))) where ((`x`.`doc_type` = 'challan_bill') and (`x`.`status` = 'active') and (`c`.`source_kind` = 'inv')) group by `c`.`source_line_id`) `b` on((`b`.`source_line_id` = `l`.`line_id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `customer_accounts`
--

/*!50001 DROP VIEW IF EXISTS `customer_accounts`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `customer_accounts` AS select `c`.`customer_id` AS `customer_id`,`c`.`customer_code` AS `customer_code`,`c`.`customer_group` AS `customer_group`,`c`.`customer_name` AS `customer_name`,`c`.`mobile` AS `mobile`,`c`.`attention` AS `attention`,`c`.`address` AS `address`,`c`.`bin` AS `bin`,`c`.`nid` AS `nid`,`t`.`trader_id` AS `trader_id`,`t`.`trader_code` AS `trader_code`,`t`.`category_id` AS `category_id`,coalesce(`tc`.`category_name`,'') AS `category_name`,coalesce(`t`.`is_active`,1) AS `is_active`,coalesce(`t`.`opening_balance`,0) AS `opening_balance`,(`f`.`trader_id` is not null) AS `has_credit_rules`,coalesce(`f`.`allow_credit`,0) AS `allow_credit`,coalesce(`f`.`credit_limit`,0) AS `credit_limit`,coalesce(`f`.`credit_days`,0) AS `credit_days`,coalesce(`f`.`block_sales`,0) AS `block_sales`,coalesce(`f`.`price_level`,'retail') AS `price_level`,coalesce(`i`.`invoice_due`,0) AS `invoice_due`,(coalesce(`t`.`opening_balance`,0) + coalesce(`i`.`invoice_due`,0)) AS `balance`,coalesce(`i`.`total_sales`,0) AS `total_sales`,coalesce(`i`.`invoice_count`,0) AS `invoice_count`,`i`.`last_invoice_date` AS `last_invoice_date`,`i`.`oldest_due_date` AS `oldest_due_date`,(select `s`.`sr_name` from (`trader_sr_assignments` `a` join `sales_representatives` `s` on((`s`.`sr_id` = `a`.`sr_id`))) where ((`a`.`trader_id` = `t`.`trader_id`) and (`a`.`effective_to` is null)) order by `a`.`assignment_id` desc limit 1) AS `sr_name` from ((((`sales_customers` `c` left join `traders` `t` on((`t`.`sales_customer_id` = `c`.`customer_id`))) left join `trader_categories` `tc` on((`tc`.`category_id` = `t`.`category_id`))) left join `trader_flags` `f` on((`f`.`trader_id` = `t`.`trader_id`))) left join (select `sales_invoices`.`customer_id` AS `customer_id`,sum(`sales_invoices`.`balance_amount`) AS `invoice_due`,sum(`sales_invoices`.`net_amount`) AS `total_sales`,count(0) AS `invoice_count`,max(`sales_invoices`.`sales_date`) AS `last_invoice_date`,min((case when (`sales_invoices`.`balance_amount` > 0.004) then `sales_invoices`.`sales_date` end)) AS `oldest_due_date` from `sales_invoices` where ((`sales_invoices`.`status` = 'posted') and (`sales_invoices`.`customer_id` is not null)) group by `sales_invoices`.`customer_id`) `i` on((`i`.`customer_id` = `c`.`customer_id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `customer_ledger_entries`
--

/*!50001 DROP VIEW IF EXISTS `customer_ledger_entries`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `customer_ledger_entries` AS select `v`.`customer_id` AS `customer_id`,`v`.`sales_date` AS `entry_date`,1 AS `sort_order`,`v`.`invoice_id` AS `ref_id`,`v`.`invoice_no` AS `doc_no`,'Sales Invoice' AS `entry_type`,`v`.`ref_no` AS `ref_no`,`v`.`net_amount` AS `debit`,0.00 AS `credit` from `sales_invoices` `v` where ((`v`.`status` = 'posted') and (`v`.`customer_id` is not null)) union all select `v`.`customer_id` AS `customer_id`,cast(`p`.`paid_at` as date) AS `DATE(p.paid_at)`,2 AS `2`,`p`.`payment_id` AS `payment_id`,`v`.`invoice_no` AS `invoice_no`,concat('Receipt (',`p`.`payment_method`,')') AS `CONCAT('Receipt (', p.payment_method, ')')`,`p`.`reference` AS `reference`,0.00 AS `0.00`,`p`.`amount` AS `amount` from (`sales_invoice_payments` `p` join `sales_invoices` `v` on(((`v`.`invoice_id` = `p`.`invoice_id`) and (`v`.`status` = 'posted')))) where (`v`.`customer_id` is not null) union all select `v`.`customer_id` AS `customer_id`,`r`.`return_date` AS `return_date`,3 AS `3`,`r`.`return_id` AS `return_id`,`r`.`return_no` AS `return_no`,'Sales Return' AS `Sales Return`,`v`.`invoice_no` AS `invoice_no`,0.00 AS `0.00`,`r`.`return_amount` AS `return_amount` from (`sales_returns` `r` join `sales_invoices` `v` on(((`v`.`invoice_id` = `r`.`invoice_id`) and (`v`.`status` = 'posted')))) where ((`r`.`status` = 'posted') and (`v`.`customer_id` is not null)) union all select `v`.`customer_id` AS `customer_id`,`r`.`return_date` AS `return_date`,4 AS `4`,`r`.`return_id` AS `return_id`,`r`.`return_no` AS `return_no`,'Refund' AS `Refund`,`v`.`invoice_no` AS `invoice_no`,`r`.`refund_amount` AS `refund_amount`,0.00 AS `0.00` from (`sales_returns` `r` join `sales_invoices` `v` on(((`v`.`invoice_id` = `r`.`invoice_id`) and (`v`.`status` = 'posted')))) where ((`r`.`status` = 'posted') and (`r`.`refund_amount` > 0) and (`v`.`customer_id` is not null)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `product_price_list`
--

/*!50001 DROP VIEW IF EXISTS `product_price_list`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `product_price_list` AS select `p`.`product_id` AS `product_id`,`p`.`product_code` AS `product_code`,`p`.`product_group` AS `product_group`,`p`.`brand` AS `brand`,`p`.`model_caption` AS `model_caption`,`p`.`description` AS `description`,`p`.`unit_name` AS `unit_name`,`p`.`is_active` AS `is_active`,`p`.`default_unit_price` AS `sales_price`,coalesce(`pp`.`retail_a`,0) AS `retail_a`,coalesce(`pp`.`retail_b`,0) AS `retail_b`,coalesce(`pp`.`retail_c`,0) AS `retail_c`,coalesce(`pp`.`dealer_a`,0) AS `dealer_a`,coalesce(`pp`.`dealer_b`,0) AS `dealer_b`,coalesce(`pp`.`dealer_c`,0) AS `dealer_c`,coalesce(`pp`.`mrp`,0) AS `mrp`,coalesce(`pp`.`lower_price`,0) AS `lower_price`,coalesce(`pp`.`higher_price`,0) AS `higher_price`,`pp`.`updated_at` AS `price_updated_at`,coalesce(`c`.`avg_cost`,0) AS `avg_cost`,coalesce((select `m`.`unit_cost` from `inv_stock_moves` `m` where ((`m`.`product_id` = `p`.`product_id`) and (`m`.`qty_in` > 0) and (`m`.`doc_type` = 'purchase')) order by `m`.`move_id` desc limit 1),0) AS `last_cost`,coalesce((select sum((`m`.`qty_in` - `m`.`qty_out`)) from `inv_stock_moves` `m` where (`m`.`product_id` = `p`.`product_id`)),0) AS `stock_qty` from ((`sales_products` `p` left join `inv_product_prices` `pp` on((`pp`.`product_id` = `p`.`product_id`))) left join (select `inv_stock_moves`.`product_id` AS `product_id`,(sum((`inv_stock_moves`.`qty_in` * `inv_stock_moves`.`unit_cost`)) / nullif(sum(`inv_stock_moves`.`qty_in`),0)) AS `avg_cost` from `inv_stock_moves` where ((`inv_stock_moves`.`qty_in` > 0) and (`inv_stock_moves`.`doc_type` in ('purchase','stock_in'))) group by `inv_stock_moves`.`product_id`) `c` on((`c`.`product_id` = `p`.`product_id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `purchase_due`
--

/*!50001 DROP VIEW IF EXISTS `purchase_due`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `purchase_due` AS select `d`.`doc_id` AS `doc_id`,`d`.`doc_no` AS `doc_no`,`d`.`doc_date` AS `doc_date`,`d`.`party_id` AS `party_id`,`d`.`party_group` AS `party_group`,`d`.`party_name` AS `party_name`,`d`.`bill_type` AS `bill_type`,`d`.`net_amount` AS `net_amount`,coalesce(`r`.`amount`,0) AS `return_amount`,coalesce(`p`.`amount`,0) AS `paid_amount`,((`d`.`net_amount` - coalesce(`r`.`amount`,0)) - coalesce(`p`.`amount`,0)) AS `due_amount` from ((`inv_docs` `d` left join (select `inv_docs`.`source_doc_id` AS `source_doc_id`,sum(`inv_docs`.`net_amount`) AS `amount` from `inv_docs` where ((`inv_docs`.`doc_type` = 'purchase_return') and (`inv_docs`.`status` = 'active')) group by `inv_docs`.`source_doc_id`) `r` on((`r`.`source_doc_id` = `d`.`doc_id`))) left join (select `purchase_payments`.`doc_id` AS `doc_id`,sum(`purchase_payments`.`amount`) AS `amount` from `purchase_payments` group by `purchase_payments`.`doc_id`) `p` on((`p`.`doc_id` = `d`.`doc_id`))) where ((`d`.`doc_type` = 'purchase') and (`d`.`status` = 'active')) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_inv_docs`
--

/*!50001 DROP VIEW IF EXISTS `rep_inv_docs`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_inv_docs` AS select `d`.`doc_id` AS `doc_id`,`d`.`doc_type` AS `doc_type`,`d`.`doc_no` AS `doc_no`,`d`.`doc_date` AS `doc_date`,`d`.`ref_no` AS `ref_no`,`d`.`branch_id` AS `branch_id`,`d`.`party_type` AS `party_type`,`d`.`party_id` AS `party_id`,`d`.`party_group` AS `party_group`,`d`.`party_name` AS `party_name`,`d`.`mobile` AS `mobile`,`d`.`address` AS `address`,`d`.`attention` AS `attention`,`d`.`sales_person` AS `sales_person`,`d`.`delivery_point` AS `delivery_point`,`d`.`narration` AS `narration`,`d`.`bill_type` AS `bill_type`,`d`.`source_type` AS `source_type`,`d`.`source_doc_id` AS `source_doc_id`,`d`.`source_no` AS `source_no`,`d`.`payment_term` AS `payment_term`,`d`.`required_date` AS `required_date`,`d`.`order_date` AS `order_date`,`d`.`subtotal` AS `subtotal`,`d`.`extra_charges` AS `extra_charges`,`d`.`discount_amount` AS `discount_amount`,`d`.`vat_amount` AS `vat_amount`,`d`.`net_amount` AS `net_amount`,`d`.`cash_received` AS `cash_received`,`d`.`total_quantity` AS `total_quantity`,`d`.`sales_invoice_id` AS `sales_invoice_id`,`d`.`status` AS `status`,`d`.`approval_status` AS `approval_status`,`d`.`approved_by` AS `approved_by`,`d`.`approved_at` AS `approved_at`,`d`.`created_by` AS `created_by`,`d`.`created_at` AS `created_at`,`d`.`updated_at` AS `updated_at`,coalesce(`b`.`branch_name`,'') AS `branch_name` from (`inv_docs` `d` left join `branches` `b` on((`b`.`branch_id` = `d`.`branch_id`))) where (`d`.`status` = 'active') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_inv_line_usage`
--

/*!50001 DROP VIEW IF EXISTS `rep_inv_line_usage`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_inv_line_usage` AS select `l`.`source_line_id` AS `line_id`,sum((case when (`d`.`doc_type` = 'purchase_order') then (`l`.`quantity` + `l`.`free_quantity`) else 0 end)) AS `po_qty`,sum((case when (`d`.`doc_type` = 'purchase') then (`l`.`quantity` + `l`.`free_quantity`) else 0 end)) AS `purchase_qty`,sum((case when (`d`.`doc_type` = 'challan_return') then (`l`.`quantity` + `l`.`free_quantity`) else 0 end)) AS `return_qty`,sum((case when (`d`.`doc_type` = 'challan_bill') then `l`.`quantity` else 0 end)) AS `bill_qty` from (`inv_doc_lines` `l` join `inv_docs` `d` on((`d`.`doc_id` = `l`.`doc_id`))) where ((`d`.`status` = 'active') and (`l`.`source_kind` = 'inv') and (`l`.`source_line_id` is not null)) group by `l`.`source_line_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_inv_lines`
--

/*!50001 DROP VIEW IF EXISTS `rep_inv_lines`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_inv_lines` AS select `d`.`doc_id` AS `doc_id`,`d`.`doc_type` AS `doc_type`,`d`.`doc_no` AS `doc_no`,`d`.`doc_date` AS `doc_date`,`d`.`ref_no` AS `ref_no`,`d`.`branch_id` AS `branch_id`,coalesce(`b`.`branch_name`,'') AS `branch_name`,`d`.`party_group` AS `party_group`,`d`.`party_name` AS `party_name`,`d`.`mobile` AS `mobile`,`d`.`sales_person` AS `sales_person`,`d`.`bill_type` AS `bill_type`,`d`.`source_type` AS `source_type`,`d`.`source_no` AS `source_no`,`d`.`required_date` AS `required_date`,`d`.`delivery_point` AS `delivery_point`,`d`.`approval_status` AS `approval_status`,`d`.`sales_invoice_id` AS `sales_invoice_id`,`d`.`narration` AS `doc_narration`,`l`.`line_id` AS `line_id`,`l`.`line_no` AS `line_no`,`l`.`product_id` AS `product_id`,`l`.`product_code` AS `product_code`,`l`.`product_group` AS `product_group`,`l`.`brand` AS `brand`,`l`.`model_caption` AS `model_caption`,`l`.`description` AS `description`,`l`.`unit_name` AS `unit_name`,`l`.`store_name` AS `store_name`,`l`.`store_to` AS `store_to`,`l`.`quantity` AS `quantity`,`l`.`free_quantity` AS `free_quantity`,`l`.`unit_price` AS `unit_price`,`l`.`prev_price` AS `prev_price`,`l`.`discount_amount` AS `discount_amount`,`l`.`line_total` AS `line_total`,coalesce(`l`.`serials`,'') AS `serials`,`l`.`narration` AS `line_narration`,`l`.`source_kind` AS `source_kind`,`l`.`source_line_id` AS `source_line_id` from ((`inv_doc_lines` `l` join `inv_docs` `d` on((`d`.`doc_id` = `l`.`doc_id`))) left join `branches` `b` on((`b`.`branch_id` = `d`.`branch_id`))) where (`d`.`status` = 'active') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_product_cost`
--

/*!50001 DROP VIEW IF EXISTS `rep_product_cost`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_product_cost` AS select `m`.`product_id` AS `product_id`,round((sum((`m`.`qty_in` * `m`.`unit_cost`)) / nullif(sum(`m`.`qty_in`),0)),4) AS `avg_cost`,cast(substring_index(group_concat(`m`.`unit_cost` order by `m`.`move_id` DESC separator ','),',',1) as decimal(15,2)) AS `last_cost` from `inv_stock_moves` `m` where ((`m`.`qty_in` > 0) and (`m`.`doc_type` in ('purchase','stock_in'))) group by `m`.`product_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_sales_invoices`
--

/*!50001 DROP VIEW IF EXISTS `rep_sales_invoices`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_sales_invoices` AS select `v`.`invoice_id` AS `invoice_id`,`v`.`invoice_no` AS `doc_no`,`v`.`sales_date` AS `doc_date`,`v`.`ref_no` AS `ref_no`,`v`.`bill_type` AS `bill_type`,`v`.`customer_id` AS `customer_id`,`v`.`customer_group` AS `party_group`,`v`.`customer_name` AS `party_name`,`v`.`mobile` AS `mobile`,`v`.`sales_person` AS `sales_person`,`v`.`subtotal` AS `subtotal`,`v`.`extra_charges` AS `extra_charges`,`v`.`discount_amount` AS `discount_amount`,(`v`.`vat_amount` + `v`.`ait_amount`) AS `vat_amount`,`v`.`exchange_amount` AS `exchange_amount`,`v`.`net_amount` AS `net_amount`,(`v`.`net_amount` - `v`.`balance_amount`) AS `received_amount`,`v`.`balance_amount` AS `balance_amount`,`v`.`total_quantity` AS `total_quantity`,coalesce((select sum(`i`.`discount_amount`) from `sales_invoice_items` `i` where (`i`.`invoice_id` = `v`.`invoice_id`)),0) AS `line_discount`,coalesce((select `b`.`branch_name` from `branches` `b` where (`b`.`branch_id` = `v`.`branch_id`)),(select `b`.`branch_name` from (`inv_docs` `cb` join `branches` `b` on((`b`.`branch_id` = `cb`.`branch_id`))) where (`cb`.`sales_invoice_id` = `v`.`invoice_id`) order by `cb`.`doc_id` limit 1),'') AS `branch_name`,`v`.`approval_status` AS `approval_status` from `sales_invoices` `v` where (`v`.`status` = 'posted') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_sales_ledger`
--

/*!50001 DROP VIEW IF EXISTS `rep_sales_ledger`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_sales_ledger` AS select `v`.`doc_date` AS `doc_date`,1 AS `sort_order`,`v`.`invoice_id` AS `ref_id`,`v`.`doc_no` AS `doc_no`,'Sales' AS `txn_type`,`v`.`party_group` AS `party_group`,`v`.`party_name` AS `party_name`,`v`.`mobile` AS `mobile`,`v`.`sales_person` AS `sales_person`,`v`.`net_amount` AS `debit`,0.00 AS `credit` from `rep_sales_invoices` `v` union all select cast(`p`.`paid_at` as date) AS `DATE(p.paid_at)`,2 AS `2`,`p`.`payment_id` AS `payment_id`,`v`.`doc_no` AS `doc_no`,concat('Received (',`p`.`payment_method`,')') AS `CONCAT('Received (', p.payment_method, ')')`,`v`.`party_group` AS `party_group`,`v`.`party_name` AS `party_name`,`v`.`mobile` AS `mobile`,`v`.`sales_person` AS `sales_person`,0.00 AS `0.00`,`p`.`amount` AS `amount` from (`sales_invoice_payments` `p` join `rep_sales_invoices` `v` on((`v`.`invoice_id` = `p`.`invoice_id`))) union all select `r`.`doc_date` AS `doc_date`,3 AS `3`,`r`.`return_id` AS `return_id`,`r`.`doc_no` AS `doc_no`,'Sales Return' AS `Sales Return`,`r`.`party_group` AS `party_group`,`r`.`party_name` AS `party_name`,`r`.`mobile` AS `mobile`,`r`.`sales_person` AS `sales_person`,0.00 AS `0.00`,`r`.`return_amount` AS `return_amount` from `rep_sales_returns` `r` union all select `r`.`doc_date` AS `doc_date`,4 AS `4`,`r`.`return_id` AS `return_id`,`r`.`doc_no` AS `doc_no`,'Refund' AS `Refund`,`r`.`party_group` AS `party_group`,`r`.`party_name` AS `party_name`,`r`.`mobile` AS `mobile`,`r`.`sales_person` AS `sales_person`,`r`.`refund_amount` AS `refund_amount`,0.00 AS `0.00` from `rep_sales_returns` `r` where (`r`.`refund_amount` > 0) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_sales_lines`
--

/*!50001 DROP VIEW IF EXISTS `rep_sales_lines`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_sales_lines` AS select `v`.`invoice_id` AS `invoice_id`,`v`.`doc_no` AS `doc_no`,`v`.`doc_date` AS `doc_date`,`v`.`bill_type` AS `bill_type`,`v`.`party_group` AS `party_group`,`v`.`party_name` AS `party_name`,`v`.`mobile` AS `mobile`,`v`.`sales_person` AS `sales_person`,`v`.`branch_name` AS `branch_name`,`v`.`approval_status` AS `approval_status`,`i`.`item_id` AS `item_id`,`i`.`product_id` AS `product_id`,`i`.`product_code` AS `product_code`,`i`.`product_group` AS `product_group`,`i`.`brand` AS `brand`,`i`.`model_caption` AS `model_caption`,`i`.`description` AS `description`,`i`.`store_name` AS `store_name`,`i`.`unit_name` AS `unit_name`,`i`.`serial_imei` AS `serial_imei`,`i`.`quantity` AS `quantity`,`i`.`free_quantity` AS `free_quantity`,`i`.`unit_price` AS `unit_price`,`i`.`discount_amount` AS `discount_amount`,`i`.`line_total` AS `line_total`,round((case when (`v`.`subtotal` > 0) then ((`v`.`discount_amount` * `i`.`line_total`) / `v`.`subtotal`) else 0 end),2) AS `invoice_discount`,round((`i`.`line_total` - (case when (`v`.`subtotal` > 0) then ((`v`.`discount_amount` * `i`.`line_total`) / `v`.`subtotal`) else 0 end)),2) AS `net_amount`,round(((`i`.`quantity` + `i`.`free_quantity`) * coalesce(nullif(`i`.`unit_cost`,0),`c`.`avg_cost`,0)),2) AS `cost_amount` from ((`sales_invoice_items` `i` join `rep_sales_invoices` `v` on((`v`.`invoice_id` = `i`.`invoice_id`))) left join `rep_product_cost` `c` on((`c`.`product_id` = `i`.`product_id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_sales_order_lines`
--

/*!50001 DROP VIEW IF EXISTS `rep_sales_order_lines`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_sales_order_lines` AS select `o`.`order_id` AS `order_id`,`o`.`order_no` AS `doc_no`,`o`.`order_date` AS `doc_date`,`o`.`delivery_date` AS `delivery_date`,`o`.`customer_group` AS `party_group`,`o`.`customer_name` AS `party_name`,`o`.`mobile` AS `mobile`,`o`.`sales_person` AS `sales_person`,`o`.`status` AS `status`,`o`.`approval_status` AS `approval_status`,`o`.`net_amount` AS `order_amount`,`o`.`advance_received` AS `advance_received`,`i`.`item_id` AS `item_id`,`i`.`product_id` AS `product_id`,`i`.`product_code` AS `product_code`,`i`.`product_group` AS `product_group`,`i`.`brand` AS `brand`,`i`.`model_caption` AS `model_caption`,`i`.`description` AS `description`,`i`.`store_name` AS `store_name`,`i`.`quantity` AS `quantity`,`i`.`unit_price` AS `unit_price`,`i`.`discount_amount` AS `discount_amount`,`i`.`line_total` AS `line_total`,coalesce(`bq`.`qty`,0) AS `billed_qty`,coalesce(`cq`.`qty`,0) AS `challan_qty`,greatest(((`i`.`quantity` - coalesce(`bq`.`qty`,0)) - coalesce(`cq`.`qty`,0)),0) AS `pending_qty` from (((`sales_order_items` `i` join `sales_orders` `o` on((`o`.`order_id` = `i`.`order_id`))) left join (select `bi`.`order_item_id` AS `order_item_id`,sum(`bi`.`quantity`) AS `qty` from ((`sales_order_bill_items` `bi` join `sales_order_bills` `b` on((`b`.`bill_id` = `bi`.`bill_id`))) join `sales_invoices` `v` on(((`v`.`invoice_id` = `b`.`invoice_id`) and (`v`.`status` = 'posted')))) group by `bi`.`order_item_id`) `bq` on((`bq`.`order_item_id` = `i`.`item_id`))) left join (select `l`.`source_line_id` AS `source_line_id`,sum((`l`.`quantity` + `l`.`free_quantity`)) AS `qty` from (`inv_doc_lines` `l` join `inv_docs` `d` on(((`d`.`doc_id` = `l`.`doc_id`) and (`d`.`doc_type` = 'challan') and (`d`.`status` = 'active')))) where (`l`.`source_kind` = 'sales_order') group by `l`.`source_line_id`) `cq` on((`cq`.`source_line_id` = `i`.`item_id`))) where (`o`.`status` <> 'cancelled') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_sales_return_lines`
--

/*!50001 DROP VIEW IF EXISTS `rep_sales_return_lines`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_sales_return_lines` AS select `r`.`return_id` AS `return_id`,`r`.`doc_no` AS `doc_no`,`r`.`doc_date` AS `doc_date`,`r`.`invoice_id` AS `invoice_id`,`r`.`invoice_no` AS `invoice_no`,`r`.`party_group` AS `party_group`,`r`.`party_name` AS `party_name`,`r`.`mobile` AS `mobile`,`r`.`sales_person` AS `sales_person`,`r`.`approval_status` AS `approval_status`,`ri`.`return_item_id` AS `return_item_id`,`ri`.`product_id` AS `product_id`,coalesce(`ii`.`product_code`,`ri`.`product_code`) AS `product_code`,coalesce(`ii`.`product_group`,'') AS `product_group`,coalesce(`ii`.`brand`,'') AS `brand`,coalesce(`ii`.`model_caption`,'') AS `model_caption`,`ri`.`description` AS `description`,`ri`.`store_name` AS `store_name`,`ri`.`item_condition` AS `item_condition`,`ri`.`quantity` AS `quantity`,`ri`.`free_quantity` AS `free_quantity`,`ri`.`unit_value` AS `unit_value`,`ri`.`line_total` AS `line_total`,round(((`ri`.`quantity` + `ri`.`free_quantity`) * coalesce(`c`.`avg_cost`,0)),2) AS `cost_amount` from (((`sales_return_items` `ri` join `rep_sales_returns` `r` on((`r`.`return_id` = `ri`.`return_id`))) left join `sales_invoice_items` `ii` on((`ii`.`item_id` = `ri`.`invoice_item_id`))) left join `rep_product_cost` `c` on((`c`.`product_id` = `ri`.`product_id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_sales_returns`
--

/*!50001 DROP VIEW IF EXISTS `rep_sales_returns`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_sales_returns` AS select `r`.`return_id` AS `return_id`,`r`.`return_no` AS `doc_no`,`r`.`return_date` AS `doc_date`,`r`.`invoice_id` AS `invoice_id`,`v`.`invoice_no` AS `invoice_no`,`v`.`customer_group` AS `party_group`,`r`.`customer_name` AS `party_name`,`r`.`mobile` AS `mobile`,`v`.`sales_person` AS `sales_person`,`r`.`reason` AS `reason`,`r`.`items_amount` AS `items_amount`,`r`.`deduction_amount` AS `deduction_amount`,`r`.`return_amount` AS `return_amount`,`r`.`due_adjusted` AS `due_adjusted`,`r`.`refund_amount` AS `refund_amount`,`r`.`total_quantity` AS `total_quantity`,`r`.`approval_status` AS `approval_status` from (`sales_returns` `r` join `sales_invoices` `v` on((`v`.`invoice_id` = `r`.`invoice_id`))) where (`r`.`status` = 'posted') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `rep_stock_moves`
--

/*!50001 DROP VIEW IF EXISTS `rep_stock_moves`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `rep_stock_moves` AS select `m`.`move_id` AS `move_id`,`m`.`move_date` AS `doc_date`,`m`.`product_id` AS `product_id`,`p`.`product_code` AS `product_code`,`p`.`product_group` AS `product_group`,`p`.`brand` AS `brand`,`p`.`model_caption` AS `model_caption`,`p`.`description` AS `description`,`p`.`unit_name` AS `unit_name`,`m`.`store_name` AS `store_name`,`m`.`branch_id` AS `branch_id`,coalesce(`b`.`branch_name`,'') AS `branch_name`,`m`.`qty_in` AS `qty_in`,`m`.`qty_out` AS `qty_out`,`m`.`unit_cost` AS `unit_cost`,`m`.`doc_type` AS `doc_type`,`m`.`doc_id` AS `doc_id`,`m`.`doc_no` AS `doc_no`,coalesce(`d`.`party_name`,'') AS `party_name`,coalesce(`d`.`ref_no`,'') AS `ref_no`,coalesce(`d`.`approval_status`,'approved') AS `approval_status`,coalesce(`c`.`avg_cost`,0) AS `avg_cost` from ((((`inv_stock_moves` `m` join `sales_products` `p` on((`p`.`product_id` = `m`.`product_id`))) left join `inv_docs` `d` on((`d`.`doc_id` = `m`.`doc_id`))) left join `branches` `b` on((`b`.`branch_id` = `m`.`branch_id`))) left join `rep_product_cost` `c` on((`c`.`product_id` = `m`.`product_id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `sales_mobile_history`
--

/*!50001 DROP VIEW IF EXISTS `sales_mobile_history`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `sales_mobile_history` AS select 'invoice' AS `doc_kind`,`v`.`invoice_id` AS `doc_id`,`v`.`invoice_no` AS `doc_no`,`v`.`sales_date` AS `doc_date`,`v`.`ref_no` AS `ref_no`,`v`.`customer_id` AS `customer_id`,`v`.`customer_name` AS `party_name`,`v`.`customer_group` AS `party_group`,`v`.`mobile` AS `mobile`,right(regexp_replace(`v`.`mobile`,'[^0-9]',''),10) AS `mobile_key`,`v`.`net_amount` AS `amount`,`v`.`cash_received` AS `paid`,`v`.`balance_amount` AS `due`,`v`.`total_quantity` AS `total_quantity`,`v`.`status` AS `status`,`v`.`bill_type` AS `bill_type` from `sales_invoices` `v` union all select 'return' AS `return`,`r`.`return_id` AS `return_id`,`r`.`return_no` AS `return_no`,`r`.`return_date` AS `return_date`,`v`.`invoice_no` AS `invoice_no`,`r`.`customer_id` AS `customer_id`,`r`.`customer_name` AS `customer_name`,`v`.`customer_group` AS `customer_group`,`r`.`mobile` AS `mobile`,right(regexp_replace(`r`.`mobile`,'[^0-9]',''),10) AS `RIGHT(REGEXP_REPLACE(r.mobile, '[^0-9]', ''), 10)`,`r`.`return_amount` AS `return_amount`,`r`.`refund_amount` AS `refund_amount`,0.00 AS `0.00`,`r`.`total_quantity` AS `total_quantity`,`r`.`status` AS `status`,'' AS `Name_exp_32` from (`sales_returns` `r` join `sales_invoices` `v` on((`v`.`invoice_id` = `r`.`invoice_id`))) union all select 'order' AS `order`,`o`.`order_id` AS `order_id`,`o`.`order_no` AS `order_no`,`o`.`order_date` AS `order_date`,`o`.`ref_no` AS `ref_no`,`o`.`customer_id` AS `customer_id`,`o`.`customer_name` AS `customer_name`,`o`.`customer_group` AS `customer_group`,`o`.`mobile` AS `mobile`,right(regexp_replace(`o`.`mobile`,'[^0-9]',''),10) AS `RIGHT(REGEXP_REPLACE(o.mobile, '[^0-9]', ''), 10)`,`o`.`net_amount` AS `net_amount`,`o`.`advance_received` AS `advance_received`,`o`.`balance_amount` AS `balance_amount`,`o`.`total_quantity` AS `total_quantity`,`o`.`status` AS `status`,`o`.`bill_type` AS `bill_type` from `sales_orders` `o` union all select `d`.`doc_type` AS `doc_type`,`d`.`doc_id` AS `doc_id`,`d`.`doc_no` AS `doc_no`,`d`.`doc_date` AS `doc_date`,`d`.`ref_no` AS `ref_no`,`d`.`party_id` AS `party_id`,`d`.`party_name` AS `party_name`,`d`.`party_group` AS `party_group`,`d`.`mobile` AS `mobile`,right(regexp_replace(`d`.`mobile`,'[^0-9]',''),10) AS `RIGHT(REGEXP_REPLACE(d.mobile, '[^0-9]', ''), 10)`,`d`.`net_amount` AS `net_amount`,`d`.`cash_received` AS `cash_received`,0.00 AS `0.00`,`d`.`total_quantity` AS `total_quantity`,`d`.`status` AS `status`,`d`.`bill_type` AS `bill_type` from `inv_docs` `d` where (`d`.`doc_type` in ('quotation','challan','challan_return')) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `sales_order_item_balance`
--

/*!50001 DROP VIEW IF EXISTS `sales_order_item_balance`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50001 VIEW `sales_order_item_balance` AS select `oi`.`item_id` AS `order_item_id`,`oi`.`order_id` AS `order_id`,`oi`.`quantity` AS `ordered_quantity`,coalesce(sum(`bi`.`quantity`),0) AS `billed_quantity`,(`oi`.`quantity` - coalesce(sum(`bi`.`quantity`),0)) AS `remaining_quantity` from (`sales_order_items` `oi` left join `sales_order_bill_items` `bi` on((`bi`.`order_item_id` = `oi`.`item_id`))) group by `oi`.`item_id`,`oi`.`order_id`,`oi`.`quantity` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-31  0:58:20

-- Setup rows
INSERT INTO `admin_role` (`user_role_id`, `user_config`, `user`, `role_name`, `description`, `addby`, `role`, `id_reseller`, `reseller_level`, `customer_mgmt`, `sales_person_mgmt`) VALUES (1,'1,1,1,1','1,0,1,1','Administrator','Administrator','Administrator','1,1,1,1',NULL,NULL,'1,1,1,1,1,1','1,1,1,1,1,1'),(3,'1,1,1,1','1,1,1,1','ERP User','Rights come from User Access Control','schema','1,1,1,1',NULL,NULL,'1,1,1,1,1,1','1,1,1,1,1,1');
INSERT INTO `client_theme` (`client_theme_id`, `service_code`, `index_title`, `index_heading`, `main_title`, `session_idle_time`, `default_record_no`, `brand_name`, `slogan`, `menu_style`, `mobile_view_vsc`, `default_theme`, `index_bg_color`, `menu_bg_color`, `footer_left_bg_color`, `footer_right_bg_color`, `body_bgcolor_color`, `mailto`, `mailcc`, `mailbcc`, `mailreport`, `module`, `customize1`, `customize2`, `customize3`, `customize4`, `customize5`, `customize6`) VALUES (1,123,'Billing Star','Billing Star','Billing Star',10,25,'Billing Star','You shop in one roof','','treemain','treemain','','','','','','','','',0,'1,1,1,1,0,1,1,1,1,1','#FFFFFF',NULL,'Petacore Tech','www.petacoretech.com','www.petacoretech.com','Petacore Tech');
INSERT INTO `transaction_categories` VALUES (1,'sales','Cash Sales','',1,1,'2026-10-30 05:57:51'),(2,'sales','Credit Sales','',0,1,'2026-10-30 05:57:51'),(3,'sales','POS Sales','',0,1,'2026-10-30 05:57:51'),(4,'sales','Corporate Sales','',0,1,'2026-10-30 05:57:51'),(5,'purchase','Credit Purchase','',1,1,'2026-10-30 05:57:51'),(6,'purchase','Cash Purchase','',0,1,'2026-10-30 05:57:51'),(7,'purchase','Import Purchase','',0,1,'2026-10-30 05:57:51'),(8,'inventory','Stock Transfer','',1,1,'2026-10-30 05:57:51'),(9,'inventory','Stock Amendment','',0,1,'2026-10-30 05:57:51'),(10,'rma','Service','',1,1,'2026-10-30 05:57:51');
SET FOREIGN_KEY_CHECKS=1;
-- SECTION: Sales
-- From billing/sales/schema.sql
-- BusBook sales invoice module (MySQL 8.0+)
-- Run this against the BusBook database before opening sales_invoice/sales_invoice.php.

CREATE TABLE IF NOT EXISTS sales_customers (
    customer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_code VARCHAR(40) NULL,
    customer_group VARCHAR(120) NOT NULL DEFAULT '',
    customer_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    attention VARCHAR(190) NOT NULL DEFAULT '',
    address TEXT NULL,
    bin VARCHAR(60) NOT NULL DEFAULT '',
    nid VARCHAR(60) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id),
    UNIQUE KEY uq_sales_customers_code (customer_code),
    KEY ix_sales_customers_name (customer_name),
    KEY ix_sales_customers_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_stores (
    store_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    store_name VARCHAR(140) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (store_id),
    UNIQUE KEY uq_sales_stores_name (store_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_products (
    product_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_code VARCHAR(60) NULL,
    product_group VARCHAR(140) NOT NULL DEFAULT '',
    brand VARCHAR(140) NOT NULL DEFAULT '',
    model_caption VARCHAR(190) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL,
    unit_name VARCHAR(40) NOT NULL DEFAULT 'Pcs',
    warranty VARCHAR(100) NOT NULL DEFAULT '',
    default_unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_sales_products_code (product_code),
    KEY ix_sales_products_name (description),
    KEY ix_sales_products_model (model_caption),
    KEY ix_sales_products_group (product_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_invoice_sequences (
    sequence_year SMALLINT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (sequence_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_invoices (
    invoice_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_no VARCHAR(40) NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    sales_date DATE NOT NULL,
    bill_type ENUM('Cash','Credit') NOT NULL DEFAULT 'Cash',
    customer_id BIGINT UNSIGNED NULL,
    customer_group VARCHAR(120) NOT NULL DEFAULT '',
    customer_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    attention VARCHAR(190) NOT NULL DEFAULT '',
    address TEXT NULL,
    delivery_point VARCHAR(255) NOT NULL DEFAULT '',
    create_installation TINYINT(1) NOT NULL DEFAULT 0,
    bin VARCHAR(60) NOT NULL DEFAULT '',
    nid VARCHAR(60) NOT NULL DEFAULT '',
    sales_person VARCHAR(140) NOT NULL DEFAULT '',
    narration TEXT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    extra_charges DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    ait_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    exchange_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    cash_received DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    balance_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    status ENUM('posted','void') NOT NULL DEFAULT 'posted',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (invoice_id),
    UNIQUE KEY uq_sales_invoices_no (invoice_no),
    KEY ix_sales_invoices_date (sales_date),
    KEY ix_sales_invoices_customer (customer_id),
    KEY ix_sales_invoices_mobile (mobile),
    CONSTRAINT fk_sales_invoice_customer
        FOREIGN KEY (customer_id) REFERENCES sales_customers (customer_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_invoice_items (
    item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_code VARCHAR(60) NOT NULL DEFAULT '',
    product_group VARCHAR(140) NOT NULL DEFAULT '',
    brand VARCHAR(140) NOT NULL DEFAULT '',
    model_caption VARCHAR(190) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL,
    product_narration VARCHAR(255) NOT NULL DEFAULT '',
    warranty VARCHAR(100) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    unit_name VARCHAR(40) NOT NULL DEFAULT 'Pcs',
    sequence_type ENUM('not_available','available','bulk') NOT NULL DEFAULT 'not_available',
    serial_clear TINYINT(1) NOT NULL DEFAULT 0,
    serial_storage TINYINT(1) NOT NULL DEFAULT 0,
    serial_common VARCHAR(120) NOT NULL DEFAULT '',
    serial_from VARCHAR(120) NOT NULL DEFAULT '',
    serial_to VARCHAR(120) NOT NULL DEFAULT '',
    serial_imei VARCHAR(255) NOT NULL DEFAULT '',
    quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    free_quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    KEY ix_sales_items_invoice (invoice_id),
    KEY ix_sales_items_product (product_id),
    CONSTRAINT fk_sales_item_invoice
        FOREIGN KEY (invoice_id) REFERENCES sales_invoices (invoice_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sales_item_product
        FOREIGN KEY (product_id) REFERENCES sales_products (product_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_invoice_payments (
    payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    payment_method ENUM('cash','bank','mobile','other') NOT NULL DEFAULT 'cash',
    amount DECIMAL(15,2) NOT NULL,
    reference VARCHAR(120) NOT NULL DEFAULT '',
    received_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    KEY ix_sales_payments_invoice (invoice_id),
    CONSTRAINT fk_sales_payment_invoice
        FOREIGN KEY (invoice_id) REFERENCES sales_invoices (invoice_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_invoice_schedules (
    schedule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    note VARCHAR(190) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (schedule_id),
    KEY ix_sales_schedules_invoice (invoice_id),
    CONSTRAINT fk_sales_schedule_invoice
        FOREIGN KEY (invoice_id) REFERENCES sales_invoices (invoice_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- SECTION: Sales orders
-- From billing/sales/sales_order/schema.sql
-- Sales order tables for MySQL 8.0+.
-- Run after vsc/sales/schema.sql so the customer and product master tables exist.

CREATE TABLE IF NOT EXISTS sales_order_sequences (
    sequence_year SMALLINT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (sequence_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_orders (
    order_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_no VARCHAR(40) NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    order_date DATE NOT NULL,
    delivery_date DATE NULL,
    bill_type ENUM('Cash','Credit') NOT NULL DEFAULT 'Credit',
    customer_id BIGINT UNSIGNED NULL,
    customer_group VARCHAR(120) NOT NULL DEFAULT '',
    customer_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    attention VARCHAR(190) NOT NULL DEFAULT '',
    narration TEXT NULL,
    address TEXT NULL,
    bin VARCHAR(60) NOT NULL DEFAULT '',
    nid VARCHAR(60) NOT NULL DEFAULT '',
    sales_person VARCHAR(140) NOT NULL DEFAULT '',
    delivery_point VARCHAR(255) NOT NULL DEFAULT '',
    create_installation TINYINT(1) NOT NULL DEFAULT 0,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    extra_charges DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    vat_ait_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    advance_received DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    balance_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    status ENUM('open','converted','cancelled') NOT NULL DEFAULT 'open',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (order_id),
    UNIQUE KEY uq_sales_orders_no (order_no),
    KEY ix_sales_orders_ref (ref_no),
    KEY ix_sales_orders_date (order_date),
    KEY ix_sales_orders_delivery_date (delivery_date),
    KEY ix_sales_orders_customer (customer_id),
    KEY ix_sales_orders_mobile (mobile),
    CONSTRAINT fk_sales_order_customer
        FOREIGN KEY (customer_id) REFERENCES sales_customers (customer_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_order_items (
    item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_code VARCHAR(60) NOT NULL DEFAULT '',
    product_group VARCHAR(140) NOT NULL DEFAULT '',
    brand VARCHAR(140) NOT NULL DEFAULT '',
    model_caption VARCHAR(190) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL,
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    unit_name VARCHAR(40) NOT NULL DEFAULT 'Pcs',
    warranty VARCHAR(100) NOT NULL DEFAULT '',
    quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    KEY ix_sales_order_items_order (order_id),
    KEY ix_sales_order_items_product (product_id),
    CONSTRAINT fk_sales_order_item_order
        FOREIGN KEY (order_id) REFERENCES sales_orders (order_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sales_order_item_product
        FOREIGN KEY (product_id) REFERENCES sales_products (product_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_order_schedules (
    schedule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    payment_day VARCHAR(40) NOT NULL DEFAULT '',
    payment_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (schedule_id),
    KEY ix_sales_order_schedules_order (order_id),
    CONSTRAINT fk_sales_order_schedule_order
        FOREIGN KEY (order_id) REFERENCES sales_orders (order_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- SECTION: Accounts
-- From billing/accounts_info/schema.sql
-- Accounts Information module, MySQL 8.0+. Safe to run more than once.
-- Chart of accounts, sub ledgers, vouchers, settings, CC loan interest and over-due permissions.

CREATE TABLE IF NOT EXISTS acc_settings (
    setting_key VARCHAR(60) NOT NULL,
    setting_value VARCHAR(255) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_sequences (
    doc_code VARCHAR(10) NOT NULL,
    seq_year SMALLINT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (doc_code, seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Accounts Group Distribution": the group tree of the chart of accounts.
CREATE TABLE IF NOT EXISTS acc_groups (
    group_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED NULL,
    group_code VARCHAR(20) NOT NULL DEFAULT '',
    group_name VARCHAR(120) NOT NULL,
    nature ENUM('asset','liability','equity','income','expense') NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id),
    UNIQUE KEY uq_acc_groups_name (parent_id, group_name),
    KEY ix_acc_groups_parent (parent_id),
    CONSTRAINT fk_acc_group_parent FOREIGN KEY (parent_id) REFERENCES acc_groups (group_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "General Accounts": ledger accounts. Control accounts (has_sub_ledger=1) carry party sub ledgers.
CREATE TABLE IF NOT EXISTS acc_accounts (
    account_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    account_code VARCHAR(20) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_type ENUM('general','cash','bank','receivable','payable','loan') NOT NULL DEFAULT 'general',
    has_sub_ledger TINYINT(1) NOT NULL DEFAULT 0,
    bank_name VARCHAR(120) NOT NULL DEFAULT '',
    bank_account_no VARCHAR(60) NOT NULL DEFAULT '',
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    opening_side ENUM('Dr','Cr') NOT NULL DEFAULT 'Dr',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id),
    UNIQUE KEY uq_acc_accounts_code (account_code),
    UNIQUE KEY uq_acc_accounts_name (account_name),
    KEY ix_acc_accounts_group (group_id),
    CONSTRAINT fk_acc_account_group FOREIGN KEY (group_id) REFERENCES acc_groups (group_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Accounts Sub Ledger Group": area or branch groups of parties under a control account.
CREATE TABLE IF NOT EXISTS acc_sub_ledger_groups (
    slg_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    group_name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (slg_id),
    UNIQUE KEY uq_acc_slg_name (account_id, group_name),
    CONSTRAINT fk_acc_slg_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Accounts Sub Ledger": one row per party (customer, supplier, employee ...).
CREATE TABLE IF NOT EXISTS acc_sub_ledgers (
    sub_ledger_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slg_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    sub_ledger_code VARCHAR(30) NOT NULL,
    sub_ledger_name VARCHAR(190) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    opening_side ENUM('Dr','Cr') NOT NULL DEFAULT 'Dr',
    trader_id BIGINT UNSIGNED NULL COMMENT 'Link to traders.trader_id when imported',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sub_ledger_id),
    UNIQUE KEY uq_acc_sub_ledgers_code (sub_ledger_code),
    UNIQUE KEY uq_acc_sub_ledgers_trader (trader_id),
    KEY ix_acc_sub_ledgers_account (account_id),
    KEY ix_acc_sub_ledgers_name (sub_ledger_name),
    CONSTRAINT fk_acc_sl_group FOREIGN KEY (slg_id) REFERENCES acc_sub_ledger_groups (slg_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_acc_sl_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "MoneyReceiptCategory": receipt types used on credit (receipt) vouchers.
CREATE TABLE IF NOT EXISTS acc_money_receipt_categories (
    category_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(120) NOT NULL,
    account_id BIGINT UNSIGNED NULL COMMENT 'Default account credited',
    note VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_acc_mrc_name (category_name),
    CONSTRAINT fk_acc_mrc_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vouchers. Only status='posted' vouchers count in the reports.
CREATE TABLE IF NOT EXISTS acc_vouchers (
    voucher_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_no VARCHAR(40) NOT NULL,
    voucher_type ENUM('debit','credit','journal','contra') NOT NULL,
    voucher_date DATE NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    narration VARCHAR(500) NOT NULL DEFAULT '',
    sales_person VARCHAR(140) NOT NULL DEFAULT '',
    transaction_mode ENUM('Cash','Cheque','Bank Transfer','Mobile Banking','Adjustment') NOT NULL DEFAULT 'Cash',
    cheque_no VARCHAR(40) NOT NULL DEFAULT '',
    cheque_date DATE NULL,
    receipt_category_id BIGINT UNSIGNED NULL,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
    source VARCHAR(30) NOT NULL DEFAULT 'manual',
    branch_id INT UNSIGNED NULL,
    to_branch_id INT UNSIGNED NULL,
    is_inter_branch TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    posted_by BIGINT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (voucher_id),
    UNIQUE KEY uq_acc_vouchers_no (voucher_no),
    KEY ix_acc_vouchers_date (voucher_date),
    KEY ix_acc_vouchers_status (status, voucher_type),
    KEY ix_acc_vouchers_status_date (status, voucher_date),
    CONSTRAINT fk_acc_voucher_mrc FOREIGN KEY (receipt_category_id) REFERENCES acc_money_receipt_categories (category_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_voucher_lines (
    line_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    sub_ledger_id BIGINT UNSIGNED NULL,
    debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_narration VARCHAR(255) NOT NULL DEFAULT '',
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    -- Copies of the voucher's status and dates, kept in step by acc_sync_lines() in acc_common.php,
    -- so report totals read one covering index instead of joining every line to its voucher.
    is_posted TINYINT(1) NOT NULL DEFAULT 0,
    voucher_date DATE NULL,
    basis_date DATE NULL COMMENT 'Line cheque date, else voucher cheque date, else voucher date',
    branch_id INT UNSIGNED NULL,
    transaction_mode VARCHAR(20) NOT NULL DEFAULT '',
    cheque_no VARCHAR(40) NOT NULL DEFAULT '',
    cheque_date DATE NULL,
    invoice_no VARCHAR(40) NOT NULL DEFAULT '',
    PRIMARY KEY (line_id),
    KEY ix_acc_lines_voucher (voucher_id),
    KEY ix_acc_lines_account (account_id),
    KEY ix_acc_lines_sub_ledger (sub_ledger_id),
    KEY ix_acc_l_post_acct (is_posted, account_id, voucher_date, debit, credit),
    KEY ix_acc_l_post_sub (is_posted, sub_ledger_id, voucher_date, debit, credit),
    KEY ix_acc_l_post_acct_b (is_posted, account_id, basis_date, debit, credit),
    KEY ix_acc_l_post_sub_b (is_posted, sub_ledger_id, basis_date, debit, credit),
    CONSTRAINT fk_acc_line_voucher FOREIGN KEY (voucher_id) REFERENCES acc_vouchers (voucher_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_acc_line_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_acc_line_sub_ledger FOREIGN KEY (sub_ledger_id) REFERENCES acc_sub_ledgers (sub_ledger_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Document Attach" on the voucher screen.
CREATE TABLE IF NOT EXISTS acc_voucher_attachments (
    attachment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attachment_id),
    UNIQUE KEY uq_acc_voucher_attachments_stored (stored_name),
    KEY ix_acc_voucher_attachments_voucher (voucher_id),
    CONSTRAINT fk_acc_voucher_attachment FOREIGN KEY (voucher_id) REFERENCES acc_vouchers (voucher_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "CC Loan Interest": annual rate history per cash-credit loan account.
CREATE TABLE IF NOT EXISTS acc_cc_loan_rates (
    rate_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    annual_rate DECIMAL(6,3) NOT NULL,
    effective_from DATE NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (rate_id),
    UNIQUE KEY uq_acc_cc_rate (account_id, effective_from),
    CONSTRAINT fk_acc_cc_rate_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each posted interest calculation, so the same days are never charged twice.
CREATE TABLE IF NOT EXISTS acc_cc_interest_runs (
    run_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    day_basis SMALLINT UNSIGNED NOT NULL DEFAULT 365,
    interest_amount DECIMAL(15,2) NOT NULL,
    voucher_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (run_id),
    KEY ix_acc_cc_runs_account (account_id, period_from, period_to),
    CONSTRAINT fk_acc_cc_run_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_acc_cc_run_voucher FOREIGN KEY (voucher_id) REFERENCES acc_vouchers (voucher_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Over Due Permission": lets a party stay over its due or credit limit until a date.
CREATE TABLE IF NOT EXISTS acc_overdue_permissions (
    permission_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sub_ledger_id BIGINT UNSIGNED NOT NULL,
    permitted_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    valid_from DATE NOT NULL,
    valid_to DATE NOT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    approved_by BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (permission_id),
    KEY ix_acc_odp_sub_ledger (sub_ledger_id, valid_to),
    CONSTRAINT fk_acc_odp_sub_ledger FOREIGN KEY (sub_ledger_id) REFERENCES acc_sub_ledgers (sub_ledger_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Branches, shared with vsc/inventory. Inter branch vouchers move amounts between two branches.
CREATE TABLE IF NOT EXISTS branches (
    branch_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (branch_id),
    UNIQUE KEY uq_branches_name (branch_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO branches (branch_id, branch_name) VALUES (1, 'Head Office');

-- "Cheque Book": leaves of a bank account's cheque book. A book must be approved before its leaves are used.
CREATE TABLE IF NOT EXISTS acc_cheque_books (
    book_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    book_no VARCHAR(40) NOT NULL,
    leaf_prefix VARCHAR(20) NOT NULL DEFAULT '',
    leaf_from BIGINT UNSIGNED NOT NULL,
    leaf_to BIGINT UNSIGNED NOT NULL,
    leaf_digits TINYINT UNSIGNED NOT NULL DEFAULT 0,
    issue_date DATE NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    approval_status ENUM('pending','approved') NOT NULL DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (book_id),
    UNIQUE KEY uq_acc_cheque_books (account_id, book_no),
    CONSTRAINT fk_acc_cheque_book_account FOREIGN KEY (account_id) REFERENCES acc_accounts (account_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_cheque_leaves (
    leaf_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    leaf_no VARCHAR(40) NOT NULL,
    status ENUM('unused','used','cancelled') NOT NULL DEFAULT 'unused',
    voucher_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (leaf_id),
    UNIQUE KEY uq_acc_cheque_leaf (account_id, leaf_no),
    KEY ix_acc_cheque_leaves_book (book_id),
    CONSTRAINT fk_acc_cheque_leaf_book FOREIGN KEY (book_id) REFERENCES acc_cheque_books (book_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cheque register: every cheque received or issued on a voucher, and what happened to it ("Cheque Treatment").
CREATE TABLE IF NOT EXISTS acc_cheques (
    cheque_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    direction ENUM('received','issued') NOT NULL,
    voucher_id BIGINT UNSIGNED NOT NULL,
    line_id BIGINT UNSIGNED NULL,
    bank_account_id BIGINT UNSIGNED NOT NULL,
    party_account_id BIGINT UNSIGNED NOT NULL,
    party_sub_ledger_id BIGINT UNSIGNED NULL,
    party_name VARCHAR(190) NOT NULL DEFAULT '',
    cheque_no VARCHAR(40) NOT NULL,
    cheque_date DATE NULL,
    amount DECIMAL(15,2) NOT NULL,
    status ENUM('pending','honoured','dishonoured','cancelled') NOT NULL DEFAULT 'pending',
    status_date DATE NULL,
    reversal_voucher_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cheque_id),
    KEY ix_acc_cheques_no (cheque_no),
    KEY ix_acc_cheques_status (status, cheque_date),
    KEY ix_acc_cheques_voucher (voucher_id),
    CONSTRAINT fk_acc_cheque_voucher FOREIGN KEY (voucher_id) REFERENCES acc_vouchers (voucher_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_cheque_events (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cheque_id BIGINT UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY ix_acc_cheque_events (cheque_id),
    CONSTRAINT fk_acc_cheque_event FOREIGN KEY (cheque_id) REFERENCES acc_cheques (cheque_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Upgrade an existing install (safe to run again) ----------
-- Adds the report columns and indexes to databases created before they existed, then fills them.
SET @db := DATABASE();

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='acc_voucher_lines' AND column_name='is_posted') = 0,
    'ALTER TABLE acc_voucher_lines ADD COLUMN is_posted TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN voucher_date DATE NULL, ADD COLUMN basis_date DATE NULL',
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='acc_voucher_lines' AND index_name='ix_acc_l_post_acct') = 0,
    'ALTER TABLE acc_voucher_lines ADD KEY ix_acc_l_post_acct (is_posted, account_id, voucher_date, debit, credit), ADD KEY ix_acc_l_post_sub (is_posted, sub_ledger_id, voucher_date, debit, credit), ADD KEY ix_acc_l_post_acct_b (is_posted, account_id, basis_date, debit, credit), ADD KEY ix_acc_l_post_sub_b (is_posted, sub_ledger_id, basis_date, debit, credit)',
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='acc_vouchers' AND index_name='ix_acc_vouchers_status_date') = 0,
    'ALTER TABLE acc_vouchers ADD KEY ix_acc_vouchers_status_date (status, voucher_date)',
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='acc_voucher_lines' AND column_name='branch_id') = 0,
    "ALTER TABLE acc_voucher_lines ADD COLUMN branch_id INT UNSIGNED NULL, ADD COLUMN transaction_mode VARCHAR(20) NOT NULL DEFAULT '', ADD COLUMN cheque_no VARCHAR(40) NOT NULL DEFAULT '', ADD COLUMN cheque_date DATE NULL, ADD COLUMN invoice_no VARCHAR(40) NOT NULL DEFAULT ''",
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='acc_vouchers' AND column_name='is_inter_branch') = 0,
    'ALTER TABLE acc_vouchers ADD COLUMN branch_id INT UNSIGNED NULL, ADD COLUMN to_branch_id INT UNSIGNED NULL, ADD COLUMN is_inter_branch TINYINT(1) NOT NULL DEFAULT 0',
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Fill or repair the copied columns for every line.
UPDATE acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id = l.voucher_id
SET l.is_posted = (v.status = 'posted'), l.voucher_date = v.voucher_date, l.basis_date = COALESCE(l.cheque_date, v.cheque_date, v.voucher_date)
WHERE l.is_posted <> (v.status = 'posted') OR NOT (l.voucher_date <=> v.voucher_date) OR NOT (l.basis_date <=> COALESCE(l.cheque_date, v.cheque_date, v.voucher_date));

-- ---------- Starter chart of accounts (skipped when the ids already exist) ----------
INSERT IGNORE INTO acc_groups (group_id, parent_id, group_code, group_name, nature, sort_order) VALUES
    (1, NULL, '1', 'Assets', 'asset', 1),
    (7, NULL, '2', 'Liabilities', 'liability', 2),
    (11, NULL, '3', 'Equity', 'equity', 3),
    (12, NULL, '4', 'Income', 'income', 4),
    (15, NULL, '5', 'Expenses', 'expense', 5);
INSERT IGNORE INTO acc_groups (group_id, parent_id, group_code, group_name, nature, sort_order) VALUES
    (2, 1, '11', 'Current Assets', 'asset', 1),
    (6, 1, '12', 'Fixed Assets', 'asset', 2),
    (8, 7, '21', 'Current Liabilities', 'liability', 1),
    (10, 7, '22', 'Loans', 'liability', 2),
    (13, 12, '41', 'Sales Income', 'income', 1),
    (14, 12, '42', 'General Income', 'income', 2),
    (16, 15, '51', 'Cost of Sales', 'expense', 1),
    (17, 15, '52', 'General Expenses', 'expense', 2),
    (18, 15, '53', 'Finance Cost', 'expense', 3);
INSERT IGNORE INTO acc_groups (group_id, parent_id, group_code, group_name, nature, sort_order) VALUES
    (3, 2, '111', 'Cash & Bank', 'asset', 1),
    (4, 2, '112', 'Accounts Receivable', 'asset', 2),
    (5, 2, '113', 'Inventory', 'asset', 3),
    (9, 8, '211', 'Accounts Payable', 'liability', 1);

INSERT IGNORE INTO acc_accounts (account_id, group_id, account_code, account_name, account_type, has_sub_ledger) VALUES
    (1, 3, '1001', 'Cash In Hand', 'cash', 0),
    (2, 3, '1002', 'Main Bank Account', 'bank', 0),
    (3, 4, '1101', 'Sundry Debtors', 'receivable', 1),
    (4, 5, '1201', 'Inventory', 'general', 0),
    (5, 9, '2001', 'Sundry Creditors', 'payable', 1),
    (6, 10, '2101', 'CC Loan', 'loan', 0),
    (7, 11, '3001', 'Capital', 'general', 0),
    (8, 13, '4001', 'Sales', 'general', 0),
    (9, 14, '4101', 'Other Income', 'general', 0),
    (10, 16, '5001', 'Purchase', 'general', 0),
    (11, 17, '5101', 'Office Expenses', 'general', 0),
    (12, 17, '5102', 'Salary', 'general', 0),
    (13, 18, '5201', 'CC Loan Interest', 'general', 0);

INSERT IGNORE INTO acc_sub_ledger_groups (slg_id, account_id, group_name) VALUES
    (1, 3, 'General Customers'),
    (2, 5, 'General Suppliers');

INSERT IGNORE INTO acc_money_receipt_categories (category_id, category_name, account_id) VALUES
    (1, 'Customer Collection', 3),
    (2, 'Other Income', 9);

INSERT IGNORE INTO acc_settings (setting_key, setting_value) VALUES
    ('company_name', 'DEMO ERP'),
    ('company_address', 'DHAKA-1205'),
    ('company_phone', ''),
    ('company_email', ''),
    ('voucher_prefix', 'DES'),
    ('cash_account', '1'),
    ('receivable_account', '3'),
    ('payable_account', '5'),
    ('cc_interest_account', '13'),
    ('cc_day_basis', '365'),
    ('require_posting', '1');

-- BIN printed on POS receipts (Company & Login Settings), and two-step sign-in per group (Settings > User Config):
-- off, sms, email or both, read by addon/inc/two_step.php. Off until someone turns it on.
INSERT IGNORE INTO acc_settings (setting_key, setting_value) VALUES
    ('company_bin', ''),
    ('tfa_staff', 'off'),
    ('tfa_employee', 'off'),
    ('tfa_customer', 'off'),
    ('tfa_minutes', '5');
-- SECTION: Inventory
-- From billing/inventory/schema.sql
-- Inventory, purchase, challan, price, amendment, transfer and approval documents. MySQL 8.0+.
-- Run after vsc/sales/schema.sql (and sales_order/schema.sql if you use sales orders). Safe to run again.

CREATE TABLE IF NOT EXISTS branches (
    branch_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (branch_id),
    UNIQUE KEY uq_branches_name (branch_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO branches (branch_id, branch_name) VALUES (1, 'Head Office');

CREATE TABLE IF NOT EXISTS inv_settings (
    setting_key VARCHAR(60) NOT NULL,
    setting_value VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO inv_settings (setting_key, setting_value) VALUES ('doc_prefix', 'DES'), ('default_store', 'Main Store');
INSERT IGNORE INTO sales_stores (store_name) VALUES ('Main Store');

CREATE TABLE IF NOT EXISTS inv_sequences (
    doc_code VARCHAR(10) NOT NULL,
    seq_year SMALLINT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (doc_code, seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One header table for every document type in this module (doc_type tells them apart).
CREATE TABLE IF NOT EXISTS inv_docs (
    doc_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doc_type VARCHAR(24) NOT NULL,
    doc_no VARCHAR(40) NOT NULL,
    doc_date DATE NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    branch_id INT UNSIGNED NULL,
    party_type ENUM('none','customer','supplier') NOT NULL DEFAULT 'none',
    party_id BIGINT UNSIGNED NULL,
    party_group VARCHAR(120) NOT NULL DEFAULT '',
    party_name VARCHAR(190) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    attention VARCHAR(190) NOT NULL DEFAULT '',
    sales_person VARCHAR(140) NOT NULL DEFAULT '',
    delivery_point VARCHAR(190) NOT NULL DEFAULT '',
    narration VARCHAR(500) NOT NULL DEFAULT '',
    bill_type ENUM('Cash','Credit') NOT NULL DEFAULT 'Credit',
    source_type VARCHAR(24) NOT NULL DEFAULT '',
    source_doc_id BIGINT UNSIGNED NULL,
    source_no VARCHAR(40) NOT NULL DEFAULT '',
    payment_term TEXT NULL,
    required_date DATE NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    extra_charges DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    cash_received DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    sales_invoice_id BIGINT UNSIGNED NULL COMMENT 'Invoice created by Sales Bill From Challan',
    status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    approval_status ENUM('pending','approved') NOT NULL DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (doc_id),
    UNIQUE KEY uq_inv_docs_no (doc_no),
    KEY ix_inv_docs_type_date (doc_type, doc_date),
    KEY ix_inv_docs_approval (doc_type, approval_status),
    KEY ix_inv_docs_mobile (mobile),
    KEY ix_inv_docs_source (source_doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inv_doc_lines (
    line_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doc_id BIGINT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NULL,
    product_code VARCHAR(60) NOT NULL DEFAULT '',
    product_group VARCHAR(140) NOT NULL DEFAULT '',
    brand VARCHAR(140) NOT NULL DEFAULT '',
    model_caption VARCHAR(190) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    unit_name VARCHAR(40) NOT NULL DEFAULT 'Pcs',
    warranty VARCHAR(100) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    store_to VARCHAR(140) NOT NULL DEFAULT '',
    quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    free_quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    prev_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    narration VARCHAR(255) NOT NULL DEFAULT '',
    serials TEXT NULL COMMENT 'One serial / IMEI per line',
    source_kind VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'inv = inv_doc_lines, sales_order = sales_order_items',
    source_line_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (line_id),
    KEY ix_inv_lines_doc (doc_id),
    KEY ix_inv_lines_source (source_kind, source_line_id),
    KEY ix_inv_lines_product (product_id),
    CONSTRAINT fk_inv_line_doc FOREIGN KEY (doc_id) REFERENCES inv_docs (doc_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock ledger: every stock in / out per product and store.
CREATE TABLE IF NOT EXISTS inv_stock_moves (
    move_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    store_name VARCHAR(140) NOT NULL,
    branch_id INT UNSIGNED NULL,
    move_date DATE NOT NULL,
    qty_in DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    qty_out DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    doc_type VARCHAR(24) NOT NULL,
    doc_id BIGINT UNSIGNED NOT NULL,
    doc_no VARCHAR(40) NOT NULL,
    line_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (move_id),
    KEY ix_inv_moves_stock (product_id, store_name, qty_in, qty_out),
    KEY ix_inv_moves_doc (doc_id),
    KEY ix_inv_moves_line (line_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inv_serials (
    serial_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    serial_no VARCHAR(120) NOT NULL,
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    in_stock TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (serial_id),
    UNIQUE KEY uq_inv_serials (product_id, serial_no),
    KEY ix_inv_serials_no (serial_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inv_serial_moves (
    serial_move_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    serial_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('in','out') NOT NULL,
    move_date DATE NOT NULL,
    doc_type VARCHAR(24) NOT NULL,
    doc_id BIGINT UNSIGNED NOT NULL,
    doc_no VARCHAR(40) NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    transaction_label VARCHAR(60) NOT NULL DEFAULT '',
    party_name VARCHAR(190) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    branch_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (serial_move_id),
    KEY ix_inv_serial_moves_serial (serial_id, serial_move_id),
    KEY ix_inv_serial_moves_doc (doc_id),
    CONSTRAINT fk_inv_serial_move_serial FOREIGN KEY (serial_id) REFERENCES inv_serials (serial_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price levels per product (Sales Price View / Sales Price MRP View).
CREATE TABLE IF NOT EXISTS inv_product_prices (
    product_id BIGINT UNSIGNED NOT NULL,
    retail_a DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    retail_b DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    retail_c DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    dealer_a DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    dealer_b DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    dealer_c DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    mrp DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    lower_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    higher_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transaction Lock: no document of the branch (NULL = all branches) may be dated on or before lock_date.
CREATE TABLE IF NOT EXISTS inv_transaction_locks (
    lock_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id INT UNSIGNED NULL,
    lock_date DATE NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (lock_id),
    KEY ix_inv_locks (is_active, branch_id, lock_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Approval columns on the existing sales documents (Approve > Sales). Added only when missing.
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='sales_invoices') = 1
    AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='approval_status') = 0,
    "ALTER TABLE sales_invoices ADD COLUMN approval_status ENUM('pending','approved') NOT NULL DEFAULT 'pending', ADD COLUMN approved_by BIGINT UNSIGNED NULL, ADD COLUMN approved_at DATETIME NULL, ADD KEY ix_sales_invoices_approval (approval_status, sales_date)",
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='sales_orders') = 1
    AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_orders' AND column_name='approval_status') = 0,
    "ALTER TABLE sales_orders ADD COLUMN approval_status ENUM('pending','approved') NOT NULL DEFAULT 'pending', ADD COLUMN approved_by BIGINT UNSIGNED NULL, ADD COLUMN approved_at DATETIME NULL",
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='sales_returns') = 1
    AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_returns' AND column_name='approval_status') = 0,
    "ALTER TABLE sales_returns ADD COLUMN approval_status ENUM('pending','approved') NOT NULL DEFAULT 'pending', ADD COLUMN approved_by BIGINT UNSIGNED NULL, ADD COLUMN approved_at DATETIME NULL",
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
-- SECTION: Purchase
-- From billing/purchase/schema.sql
-- Purchase module: Requisition, Purchase Order, Purchase and Purchase Return. MySQL 8.0+. Safe to run again.
-- Run after vsc/sales/schema.sql and vsc/inventory/schema.sql.
--
-- The documents themselves live in inv_docs / inv_doc_lines (doc_type requisition, purchase_order, purchase,
-- purchase_return) so stock, serials, approvals and the registers keep working from one place. This file adds
-- what only purchases need: order date, payments to the supplier, payment schedule and attached files.

SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='inv_docs' AND column_name='order_date') = 0,
    'ALTER TABLE inv_docs ADD COLUMN order_date DATE NULL AFTER required_date', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='inv_docs' AND index_name='ix_inv_docs_party') = 0,
    'ALTER TABLE inv_docs ADD KEY ix_inv_docs_party (doc_type, party_name)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Money paid to the supplier against a purchase. is_initial = the Cash Amount entered on the purchase itself.
CREATE TABLE IF NOT EXISTS purchase_payments (
    payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doc_id BIGINT UNSIGNED NOT NULL,
    paid_on DATE NOT NULL,
    payment_method ENUM('cash','bank','mobile','cheque','other') NOT NULL DEFAULT 'cash',
    amount DECIMAL(15,2) NOT NULL,
    reference VARCHAR(120) NOT NULL DEFAULT '',
    note VARCHAR(255) NOT NULL DEFAULT '',
    is_initial TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    KEY ix_purchase_payments_doc (doc_id),
    KEY ix_purchase_payments_date (paid_on),
    CONSTRAINT fk_purchase_payment_doc FOREIGN KEY (doc_id) REFERENCES inv_docs (doc_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT ck_purchase_payment_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- When the remaining amount is due to the supplier.
CREATE TABLE IF NOT EXISTS purchase_schedules (
    schedule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doc_id BIGINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    note VARCHAR(190) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (schedule_id),
    KEY ix_purchase_schedules_doc (doc_id),
    KEY ix_purchase_schedules_due (due_date),
    CONSTRAINT fk_purchase_schedule_doc FOREIGN KEY (doc_id) REFERENCES inv_docs (doc_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT ck_purchase_schedule_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supplier invoice scans, delivery notes and other files. Stored in vsc/purchase/uploads under a random name.
CREATE TABLE IF NOT EXISTS purchase_attachments (
    attachment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doc_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attachment_id),
    UNIQUE KEY uq_purchase_attachments_stored (stored_name),
    KEY ix_purchase_attachments_doc (doc_id),
    CONSTRAINT fk_purchase_attachment_doc FOREIGN KEY (doc_id) REFERENCES inv_docs (doc_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What is still owed on each active purchase: net amount less approved-or-pending returns and payments.
CREATE OR REPLACE VIEW purchase_due AS
SELECT d.doc_id, d.doc_no, d.doc_date, d.party_id, d.party_group, d.party_name, d.bill_type, d.net_amount,
       COALESCE(r.amount, 0) AS return_amount,
       COALESCE(p.amount, 0) AS paid_amount,
       d.net_amount - COALESCE(r.amount, 0) - COALESCE(p.amount, 0) AS due_amount
FROM inv_docs d
LEFT JOIN (SELECT source_doc_id, SUM(net_amount) AS amount FROM inv_docs WHERE doc_type='purchase_return' AND status='active' GROUP BY source_doc_id) r ON r.source_doc_id = d.doc_id
LEFT JOIN (SELECT doc_id, SUM(amount) AS amount FROM purchase_payments GROUP BY doc_id) p ON p.doc_id = d.doc_id
WHERE d.doc_type = 'purchase' AND d.status = 'active';
-- SECTION: Sales documents
-- From billing/sales/schema_v2.sql
-- Sales module v2: Sales Invoice/Bill and Sales Order screens, stock-tracked sales, member points,
-- product exchange, attachments and additional info. MySQL 8.0+. Safe to run again.
-- Run after vsc/sales/schema.sql, sales_order/schema.sql, sal_bill_from_sal_order/schema.sql,
-- sales_order_adjustment/schema.sql, sales_retrun/schema.sql and vsc/inventory/schema.sql.
-- Then run vsc/report/schema.sql again so the report views pick up the invoice branch.

SET @db := DATABASE();

-- Each ALTER runs only when its column is missing.
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='branch_id') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN branch_id INT UNSIGNED NULL AFTER invoice_no', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='member_id') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN member_id BIGINT UNSIGNED NULL AFTER customer_id', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='point_adjust') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN point_adjust DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT ''Taka value of redeemed points'' AFTER exchange_amount, ADD COLUMN points_redeemed DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER point_adjust, ADD COLUMN points_earned DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER points_redeemed', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='additional_info') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN additional_info TEXT NULL AFTER narration', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='stock_doc_id') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN stock_doc_id BIGINT UNSIGNED NULL COMMENT ''inv_docs row (doc_type sales_invoice) holding the stock out'', ADD KEY ix_sales_invoices_stock (stock_doc_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoice_items' AND column_name='unit_cost') = 0,
    'ALTER TABLE sales_invoice_items ADD COLUMN unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT ''Average cost when sold'' AFTER unit_price', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Serial lists (one serial per line) can be longer than 255 characters.
SET @q := IF((SELECT data_type FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoice_items' AND column_name='serial_imei') = 'varchar',
    'ALTER TABLE sales_invoice_items MODIFY serial_imei TEXT NULL', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT data_type FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_return_items' AND column_name='serial_imei') = 'varchar',
    'ALTER TABLE sales_return_items MODIFY serial_imei TEXT NULL', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_orders' AND column_name='branch_id') = 0,
    'ALTER TABLE sales_orders ADD COLUMN branch_id INT UNSIGNED NULL AFTER order_no, ADD COLUMN additional_info TEXT NULL AFTER narration', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_returns' AND column_name='stock_doc_id') = 0,
    'ALTER TABLE sales_returns ADD COLUMN stock_doc_id BIGINT UNSIGNED NULL COMMENT ''inv_docs row (doc_type sales_return) holding the stock in''', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Settings. sales_stock_mode: deduct = invoices, POS and bills from orders take goods out of stock; off = sales never touch stock.
INSERT IGNORE INTO inv_settings (setting_key, setting_value) VALUES
    ('sales_stock_mode', 'deduct'),
    ('member_points_per_100', '1'),
    ('member_point_value', '1');

-- POS receipt (Settings > User Config): heading line, default VAT % and whether it is in the price, footer text.
INSERT IGNORE INTO inv_settings (setting_key, setting_value) VALUES
    ('pos_receipt_heading', 'MUSHAK-6.3'),
    ('pos_vat_percent', '0'),
    ('pos_vat_mode', 'inclusive'),
    ('pos_receipt_footer', '');

-- Loyalty members. Points are earned on invoices (member_points_per_100 per 100 Tk of net) and redeemed with Point Adjust.
CREATE TABLE IF NOT EXISTS sales_members (
    member_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_no VARCHAR(40) NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    member_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    opening_points DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (member_id),
    UNIQUE KEY uq_sales_members_no (member_no),
    KEY ix_sales_members_mobile (mobile),
    CONSTRAINT fk_sales_member_customer FOREIGN KEY (customer_id) REFERENCES sales_customers (customer_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Point ledger: earn (+), redeem (-), adjust (+/-). Balance = opening_points + SUM(points).
CREATE TABLE IF NOT EXISTS sales_member_points (
    entry_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    entry_date DATE NOT NULL,
    kind ENUM('earn','redeem','adjust') NOT NULL,
    points DECIMAL(12,2) NOT NULL,
    note VARCHAR(190) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (entry_id),
    KEY ix_sales_member_points_member (member_id),
    KEY ix_sales_member_points_invoice (invoice_id),
    CONSTRAINT fk_sales_member_point_member FOREIGN KEY (member_id) REFERENCES sales_members (member_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sales_member_point_invoice FOREIGN KEY (invoice_id) REFERENCES sales_invoices (invoice_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items the customer hands back in exchange; their value is the invoice's Product Exchange amount.
CREATE TABLE IF NOT EXISTS sales_invoice_exchanges (
    exchange_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    serial_no VARCHAR(120) NOT NULL DEFAULT '',
    quantity DECIMAL(15,3) NOT NULL DEFAULT 1.000,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (exchange_id),
    KEY ix_sales_invoice_exchanges_invoice (invoice_id),
    CONSTRAINT fk_sales_exchange_invoice FOREIGN KEY (invoice_id) REFERENCES sales_invoices (invoice_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Files attached to invoices and orders. Stored in vsc/sales/uploads under a random name.
CREATE TABLE IF NOT EXISTS sales_doc_attachments (
    attachment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doc_kind ENUM('invoice','order') NOT NULL,
    doc_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attachment_id),
    UNIQUE KEY uq_sales_doc_attachments_stored (stored_name),
    KEY ix_sales_doc_attachments_doc (doc_kind, doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- SECTION: Sales returns
-- From billing/sales/sales_retrun/schema.sql
-- Sales return, MySQL 8.0+.
-- Run after vsc/sales/schema.sql. Returns are always made against a saved sales invoice.
-- The return value can reduce the invoice's due balance, be refunded, or both.

CREATE TABLE IF NOT EXISTS sales_doc_sequences (
    doc_type VARCHAR(10) NOT NULL,
    sequence_year SMALLINT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (doc_type, sequence_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_returns (
    return_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    return_no VARCHAR(40) NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    return_date DATE NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    customer_name VARCHAR(190) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    reason VARCHAR(255) NOT NULL DEFAULT '',
    items_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    deduction_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    return_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    due_adjusted DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    refund_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    refund_method ENUM('cash','bank','mobile','other') NOT NULL DEFAULT 'cash',
    refund_reference VARCHAR(120) NOT NULL DEFAULT '',
    total_quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    status ENUM('posted','void') NOT NULL DEFAULT 'posted',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (return_id),
    UNIQUE KEY uq_sales_returns_no (return_no),
    KEY ix_sales_returns_invoice (invoice_id),
    KEY ix_sales_returns_date (return_date),
    KEY ix_sales_returns_mobile (mobile),
    CONSTRAINT fk_sales_return_invoice
        FOREIGN KEY (invoice_id) REFERENCES sales_invoices (invoice_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sales_return_customer
        FOREIGN KEY (customer_id) REFERENCES sales_customers (customer_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_return_items (
    return_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    return_id BIGINT UNSIGNED NOT NULL,
    invoice_item_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_code VARCHAR(60) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    unit_name VARCHAR(40) NOT NULL DEFAULT 'Pcs',
    serial_imei VARCHAR(255) NOT NULL DEFAULT '',
    item_condition ENUM('good','damaged') NOT NULL DEFAULT 'good',
    quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    free_quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    unit_value DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (return_item_id),
    KEY ix_sales_return_items_return (return_id),
    KEY ix_sales_return_items_invoice_item (invoice_item_id),
    CONSTRAINT fk_sales_return_item_return
        FOREIGN KEY (return_id) REFERENCES sales_returns (return_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sales_return_item_invoice_item
        FOREIGN KEY (invoice_item_id) REFERENCES sales_invoice_items (item_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- SECTION: Bills from orders
-- From billing/sales/sal_bill_from_sal_order/schema.sql
-- Bill (sales invoice) from sales order, MySQL 8.0+.
-- Run after vsc/sales/schema.sql and vsc/sales/sales_order/schema.sql.
-- Links each invoice made from an order back to the order and to the order lines it billed,
-- so partial billing is possible and the remaining quantity per order line is always known.

CREATE TABLE IF NOT EXISTS sales_order_bills (
    bill_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    advance_applied DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (bill_id),
    UNIQUE KEY uq_sales_order_bills_invoice (invoice_id),
    KEY ix_sales_order_bills_order (order_id),
    CONSTRAINT fk_sales_order_bill_order
        FOREIGN KEY (order_id) REFERENCES sales_orders (order_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sales_order_bill_invoice
        FOREIGN KEY (invoice_id) REFERENCES sales_invoices (invoice_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_order_bill_items (
    bill_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bill_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    invoice_item_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    PRIMARY KEY (bill_item_id),
    KEY ix_sales_order_bill_items_bill (bill_id),
    KEY ix_sales_order_bill_items_order_item (order_item_id),
    KEY ix_sales_order_bill_items_invoice_item (invoice_item_id),
    CONSTRAINT fk_sales_order_bill_item_bill
        FOREIGN KEY (bill_id) REFERENCES sales_order_bills (bill_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sales_order_bill_item_order_item
        FOREIGN KEY (order_item_id) REFERENCES sales_order_items (item_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sales_order_bill_item_invoice_item
        FOREIGN KEY (invoice_item_id) REFERENCES sales_invoice_items (item_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remaining (not yet billed) quantity per order line.
CREATE OR REPLACE VIEW sales_order_item_balance AS
SELECT
    oi.item_id AS order_item_id,
    oi.order_id,
    oi.quantity AS ordered_quantity,
    COALESCE(SUM(bi.quantity), 0) AS billed_quantity,
    oi.quantity - COALESCE(SUM(bi.quantity), 0) AS remaining_quantity
FROM sales_order_items oi
LEFT JOIN sales_order_bill_items bi ON bi.order_item_id = oi.item_id
GROUP BY oi.item_id, oi.order_id, oi.quantity;
-- SECTION: Order adjustments
-- From billing/sales/sales_order_adjustment/schema.sql
-- Sales order adjustment, MySQL 8.0+.
-- Run after vsc/sales/schema.sql and vsc/sales/sales_order/schema.sql.
-- Every adjustment keeps an audit row with old and new values per changed order line.

CREATE TABLE IF NOT EXISTS sales_doc_sequences (
    doc_type VARCHAR(10) NOT NULL,
    sequence_year SMALLINT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (doc_type, sequence_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_order_adjustments (
    adjustment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    adjustment_no VARCHAR(40) NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    adjustment_date DATE NOT NULL,
    reason VARCHAR(255) NOT NULL,
    old_subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    new_subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    old_net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    new_net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    old_advance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    new_advance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    old_delivery_date DATE NULL,
    new_delivery_date DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (adjustment_id),
    UNIQUE KEY uq_sales_order_adjustments_no (adjustment_no),
    KEY ix_sales_order_adjustments_order (order_id),
    KEY ix_sales_order_adjustments_date (adjustment_date),
    CONSTRAINT fk_sales_order_adjustment_order
        FOREIGN KEY (order_id) REFERENCES sales_orders (order_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_order_adjustment_lines (
    line_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    adjustment_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NULL,
    action ENUM('added','changed','removed') NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    old_quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    new_quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    old_unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    new_unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    old_discount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    new_discount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    old_line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    new_line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (line_id),
    KEY ix_sales_order_adjustment_lines_adj (adjustment_id),
    CONSTRAINT fk_sales_order_adjustment_line_adj
        FOREIGN KEY (adjustment_id) REFERENCES sales_order_adjustments (adjustment_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- SECTION: Challan
-- From billing/challan/schema.sql
-- Challan, Challan Return and Sales Invoice/Bill From Challan. MySQL 8.0+. Safe to run again.
-- Run after vsc/inventory/schema.sql (and vsc/sales/schema_v2.sql for the bill's invoice fields).
-- The documents are inv_docs rows (doc_type challan, challan_return, challan_bill); this file adds the Additional Info
-- column and a status view, and needs no tables of its own.

SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='inv_docs' AND column_name='additional_info') = 0,
    'ALTER TABLE inv_docs ADD COLUMN additional_info TEXT NULL AFTER narration', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Every active challan line with what came back, what was billed and what is still to bill.
-- Returns use up free quantity first, so pending = LEAST(qty, qty + free - returned) - billed (same rule as the bill screen).
CREATE OR REPLACE VIEW challan_line_status AS
SELECT d.doc_id, d.doc_no, d.doc_date, d.party_name, d.mobile, d.source_no AS order_no, d.branch_id,
       l.line_id, l.line_no, l.product_id, l.product_code, l.model_caption, l.description, l.store_name,
       l.quantity, l.free_quantity, l.quantity + l.free_quantity AS delivered_qty,
       COALESCE(r.qty, 0) AS returned_qty, COALESCE(b.qty, 0) AS billed_qty,
       GREATEST(LEAST(l.quantity, l.quantity + l.free_quantity - COALESCE(r.qty, 0)) - COALESCE(b.qty, 0), 0) AS pending_qty
FROM inv_doc_lines l
JOIN inv_docs d ON d.doc_id = l.doc_id AND d.doc_type = 'challan' AND d.status = 'active'
LEFT JOIN (SELECT c.source_line_id, SUM(c.quantity + c.free_quantity) AS qty FROM inv_doc_lines c JOIN inv_docs x ON x.doc_id = c.doc_id
           WHERE x.doc_type = 'challan_return' AND x.status = 'active' AND c.source_kind = 'inv' GROUP BY c.source_line_id) r ON r.source_line_id = l.line_id
LEFT JOIN (SELECT c.source_line_id, SUM(c.quantity) AS qty FROM inv_doc_lines c JOIN inv_docs x ON x.doc_id = c.doc_id
           WHERE x.doc_type = 'challan_bill' AND x.status = 'active' AND c.source_kind = 'inv' GROUP BY c.source_line_id) b ON b.source_line_id = l.line_id;
-- SECTION: Price history
-- From billing/price_history/schema.sql
-- Price & History menu: Price Quotation, Serial History, Sales Price MRP View, Sales Price View and
-- Invoice Search by Mobile No. MySQL 8.0+. Safe to run again.
-- Run after vsc/sales/schema.sql, the sales_order and sales_retrun schemas, vsc/inventory/schema.sql and vsc/sales/schema_v2.sql.
-- Quotations are inv_docs rows (doc_type quotation, numbers like DESPQI2026000001) and price levels live in
-- inv_product_prices (inventory/schema.sql), so this file only adds the price change log and two views.

-- Every change made in Sales Price View / Sales Price MRP View: which price, old and new value, who and when.
CREATE TABLE IF NOT EXISTS inv_price_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    price_field VARCHAR(30) NOT NULL COMMENT 'default_unit_price, retail_a..c, dealer_a..c, mrp, lower_price, higher_price',
    old_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    new_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    changed_by BIGINT UNSIGNED NULL,
    changed_by_name VARCHAR(120) NOT NULL DEFAULT '',
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY ix_inv_price_history_product (product_id, changed_at),
    CONSTRAINT fk_inv_price_history_product FOREIGN KEY (product_id) REFERENCES sales_products (product_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price list per product: sales price, price levels, MRP band, costs and stock on hand.
CREATE OR REPLACE VIEW product_price_list AS
SELECT p.product_id, p.product_code, p.product_group, p.brand, p.model_caption, p.description, p.unit_name, p.is_active,
       p.default_unit_price AS sales_price,
       COALESCE(pp.retail_a, 0) AS retail_a, COALESCE(pp.retail_b, 0) AS retail_b, COALESCE(pp.retail_c, 0) AS retail_c,
       COALESCE(pp.dealer_a, 0) AS dealer_a, COALESCE(pp.dealer_b, 0) AS dealer_b, COALESCE(pp.dealer_c, 0) AS dealer_c,
       COALESCE(pp.mrp, 0) AS mrp, COALESCE(pp.lower_price, 0) AS lower_price, COALESCE(pp.higher_price, 0) AS higher_price, pp.updated_at AS price_updated_at,
       COALESCE(c.avg_cost, 0) AS avg_cost,
       COALESCE((SELECT m.unit_cost FROM inv_stock_moves m WHERE m.product_id = p.product_id AND m.qty_in > 0 AND m.doc_type = 'purchase' ORDER BY m.move_id DESC LIMIT 1), 0) AS last_cost,
       COALESCE((SELECT SUM(m.qty_in - m.qty_out) FROM inv_stock_moves m WHERE m.product_id = p.product_id), 0) AS stock_qty
FROM sales_products p
LEFT JOIN inv_product_prices pp ON pp.product_id = p.product_id
LEFT JOIN (SELECT product_id, SUM(qty_in * unit_cost) / NULLIF(SUM(qty_in), 0) AS avg_cost FROM inv_stock_moves
           WHERE qty_in > 0 AND doc_type IN ('purchase', 'stock_in') GROUP BY product_id) c ON c.product_id = p.product_id;

-- Every sales document of a customer by mobile number. mobile_key is the last 10 digits, so 01711..., +8801711...
-- and 1711... all match.
CREATE OR REPLACE VIEW sales_mobile_history AS
SELECT 'invoice' AS doc_kind, v.invoice_id AS doc_id, v.invoice_no AS doc_no, v.sales_date AS doc_date, v.ref_no,
       v.customer_id, v.customer_name AS party_name, v.customer_group AS party_group, v.mobile,
       RIGHT(REGEXP_REPLACE(v.mobile, '[^0-9]', ''), 10) AS mobile_key,
       v.net_amount AS amount, v.cash_received AS paid, v.balance_amount AS due, v.total_quantity, v.status, v.bill_type
FROM sales_invoices v
UNION ALL
SELECT 'return', r.return_id, r.return_no, r.return_date, v.invoice_no, r.customer_id, r.customer_name, v.customer_group, r.mobile,
       RIGHT(REGEXP_REPLACE(r.mobile, '[^0-9]', ''), 10), r.return_amount, r.refund_amount, 0.00, r.total_quantity, r.status, ''
FROM sales_returns r JOIN sales_invoices v ON v.invoice_id = r.invoice_id
UNION ALL
SELECT 'order', o.order_id, o.order_no, o.order_date, o.ref_no, o.customer_id, o.customer_name, o.customer_group, o.mobile,
       RIGHT(REGEXP_REPLACE(o.mobile, '[^0-9]', ''), 10), o.net_amount, o.advance_received, o.balance_amount, o.total_quantity, o.status, o.bill_type
FROM sales_orders o
UNION ALL
SELECT d.doc_type, d.doc_id, d.doc_no, d.doc_date, d.ref_no, d.party_id, d.party_name, d.party_group, d.mobile,
       RIGHT(REGEXP_REPLACE(d.mobile, '[^0-9]', ''), 10), d.net_amount, d.cash_received, 0.00, d.total_quantity, d.status, d.bill_type
FROM inv_docs d
WHERE d.doc_type IN ('quotation', 'challan', 'challan_return');
-- SECTION: Products
-- From billing/product/schema.sql
-- Product Information menu: Product Category, Periodical Warranty, Product Classification, Product Information,
-- Sales Price Fixing, Unit Information, Warranty, Transaction Category, Purchase Price Fixing. MySQL 8.0+. Safe to run again.
-- Run after vsc/sales/schema.sql, vsc/inventory/schema.sql and vsc/price_history/schema.sql.
-- Products stay in sales_products (every screen reads it); this file adds the setup lists, a few product columns,
-- the price fixing log, and fills the lists from the product data already saved.

CREATE TABLE IF NOT EXISTS product_categories (
    category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_product_categories_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product groups and brands (the names products carry in product_group / brand). A group can belong to a category.
CREATE TABLE IF NOT EXISTS product_classifications (
    class_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_type ENUM('group','brand') NOT NULL,
    class_name VARCHAR(140) NOT NULL,
    category_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id),
    UNIQUE KEY uq_product_classifications (class_type, class_name),
    CONSTRAINT fk_product_class_category FOREIGN KEY (category_id) REFERENCES product_categories (category_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_units (
    unit_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_name VARCHAR(40) NOT NULL,
    short_name VARCHAR(20) NOT NULL DEFAULT '',
    allow_decimal TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (unit_id),
    UNIQUE KEY uq_product_units_name (unit_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warranty terms. period / period_unit let screens work out the end date (Serial History uses it).
CREATE TABLE IF NOT EXISTS product_warranties (
    warranty_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    warranty_name VARCHAR(100) NOT NULL,
    period INT UNSIGNED NOT NULL DEFAULT 0,
    period_unit ENUM('none','day','month','year') NOT NULL DEFAULT 'none',
    terms VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (warranty_id),
    UNIQUE KEY uq_product_warranties_name (warranty_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warranty that applies for a date range (e.g. a brand campaign): to one product, a product group or a brand.
-- The most specific active rule for today wins (product, then group, then brand) and is offered when selling.
CREATE TABLE IF NOT EXISTS product_periodic_warranties (
    pw_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope ENUM('product','group','brand') NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    scope_value VARCHAR(140) NOT NULL DEFAULT '',
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    warranty_id INT UNSIGNED NOT NULL,
    note VARCHAR(190) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pw_id),
    KEY ix_product_periodic_dates (is_active, from_date, to_date),
    CONSTRAINT fk_periodic_product FOREIGN KEY (product_id) REFERENCES sales_products (product_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_periodic_warranty FOREIGN KEY (warranty_id) REFERENCES product_warranties (warranty_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_periodic_dates CHECK (to_date >= from_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Names for kinds of transactions per module (Cash Sales, Credit Purchase, ...).
CREATE TABLE IF NOT EXISTS transaction_categories (
    tc_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    module ENUM('sales','purchase','inventory','accounts','rma') NOT NULL,
    category_name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tc_id),
    UNIQUE KEY uq_transaction_categories (module, category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product columns used by Product Information. Each ALTER runs only when its column is missing.
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_products' AND column_name='category_id') = 0,
    'ALTER TABLE sales_products ADD COLUMN category_id INT UNSIGNED NULL AFTER product_code, ADD KEY ix_sales_products_category (category_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_products' AND column_name='purchase_price') = 0,
    'ALTER TABLE sales_products ADD COLUMN purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT ''Purchase Price Fixing; default cost on purchase screens'' AFTER default_unit_price, ADD COLUMN reorder_level DECIMAL(15,3) NOT NULL DEFAULT 0.000 AFTER purchase_price, ADD COLUMN vat_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER reorder_level, ADD COLUMN serial_required TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Stock documents must give a serial per unit'' AFTER vat_percent', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Sales / Purchase Price Fixing: one row per run, one line per product price changed.
CREATE TABLE IF NOT EXISTS product_price_fixings (
    fixing_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fixing_no VARCHAR(40) NOT NULL,
    fixing_date DATE NOT NULL,
    price_kind ENUM('sales','purchase') NOT NULL,
    price_field VARCHAR(30) NOT NULL,
    scope VARCHAR(20) NOT NULL DEFAULT 'all',
    scope_text VARCHAR(255) NOT NULL DEFAULT '',
    method ENUM('manual','percent_cost','percent_change','amount_change') NOT NULL DEFAULT 'manual',
    method_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(255) NOT NULL DEFAULT '',
    product_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_by_name VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (fixing_id),
    UNIQUE KEY uq_product_price_fixings_no (fixing_no),
    KEY ix_product_price_fixings_kind (price_kind, fixing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_price_fixing_lines (
    line_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fixing_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    old_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    new_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (line_id),
    KEY ix_price_fixing_lines_fixing (fixing_id),
    KEY ix_price_fixing_lines_product (product_id),
    CONSTRAINT fk_price_fixing_line_fixing FOREIGN KEY (fixing_id) REFERENCES product_price_fixings (fixing_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_price_fixing_line_product FOREIGN KEY (product_id) REFERENCES sales_products (product_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starting data: common units, warranty terms and transaction categories, plus the groups, brands, units and
-- warranties already used on products.
INSERT IGNORE INTO product_units (unit_name, short_name, allow_decimal) VALUES ('Pcs', 'pc', 0), ('Box', 'box', 0), ('Set', 'set', 0), ('Pair', 'pr', 0), ('Kg', 'kg', 1), ('Meter', 'm', 1), ('Feet', 'ft', 1), ('Litre', 'l', 1);
INSERT IGNORE INTO product_warranties (warranty_name, period, period_unit) VALUES ('No Warranty', 0, 'none'), ('7 Days', 7, 'day'), ('1 Month', 1, 'month'), ('3 Months', 3, 'month'),
    ('6 Months', 6, 'month'), ('1 Year', 1, 'year'), ('2 Years', 2, 'year'), ('3 Years', 3, 'year'), ('5 Years', 5, 'year');
INSERT IGNORE INTO transaction_categories (module, category_name, is_default) VALUES ('sales', 'Cash Sales', 1), ('sales', 'Credit Sales', 0), ('sales', 'POS Sales', 0), ('sales', 'Corporate Sales', 0),
    ('purchase', 'Credit Purchase', 1), ('purchase', 'Cash Purchase', 0), ('purchase', 'Import Purchase', 0), ('inventory', 'Stock Transfer', 1), ('inventory', 'Stock Amendment', 0), ('rma', 'Service', 1);
INSERT IGNORE INTO product_classifications (class_type, class_name) SELECT DISTINCT 'group', product_group FROM sales_products WHERE product_group <> '';
INSERT IGNORE INTO product_classifications (class_type, class_name) SELECT DISTINCT 'brand', brand FROM sales_products WHERE brand <> '';
INSERT IGNORE INTO product_units (unit_name) SELECT DISTINCT unit_name FROM sales_products WHERE unit_name <> '';
INSERT IGNORE INTO product_warranties (warranty_name) SELECT DISTINCT warranty FROM sales_products WHERE warranty <> '';
-- SECTION: Traders
-- From billing/trader/schema.sql
-- Trader Info module (customers, suppliers and other trading parties), MySQL 8.0+.
-- Safe to run more than once. Run vsc/sales/schema.sql first if you want customer-type
-- traders copied into sales_customers for the sales pages (optional).

CREATE TABLE IF NOT EXISTS trader_categories (
    category_id TINYINT UNSIGNED NOT NULL,
    category_name VARCHAR(60) NOT NULL,
    kind ENUM('customer','supplier') NOT NULL,
    code_prefix VARCHAR(6) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_trader_categories_name (category_name),
    UNIQUE KEY uq_trader_categories_prefix (code_prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO trader_categories (category_id, category_name, kind, code_prefix, sort_order) VALUES
    (1, 'Supplier', 'supplier', 'SUP', 1),
    (2, 'Dealer', 'customer', 'DLR', 2),
    (3, 'Credit Customer', 'customer', 'CRC', 3),
    (4, 'Cash Customer', 'customer', 'CSC', 4),
    (5, 'Cash Supplier', 'supplier', 'CSS', 5),
    (6, 'Retailer', 'customer', 'RTL', 6),
    (7, 'Importer', 'supplier', 'IMP', 7),
    (8, 'C&F', 'supplier', 'CNF', 8),
    (9, 'Indenter', 'supplier', 'IND', 9);

CREATE TABLE IF NOT EXISTS trader_groups (
    group_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id TINYINT UNSIGNED NOT NULL,
    group_name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id),
    UNIQUE KEY uq_trader_groups_name (category_id, group_name),
    CONSTRAINT fk_trader_group_category
        FOREIGN KEY (category_id) REFERENCES trader_categories (category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS traders (
    trader_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trader_code VARCHAR(20) NULL,
    category_id TINYINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED NULL,
    trader_name VARCHAR(190) NOT NULL,
    document VARCHAR(255) NOT NULL DEFAULT '',
    referrer_name VARCHAR(190) NOT NULL DEFAULT '',
    address TEXT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    secondary_mobile VARCHAR(40) NOT NULL DEFAULT '',
    phone VARCHAR(40) NOT NULL DEFAULT '',
    fax VARCHAR(40) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL DEFAULT '',
    web VARCHAR(190) NOT NULL DEFAULT '',
    contact_person VARCHAR(190) NOT NULL DEFAULT '',
    owner_name VARCHAR(190) NOT NULL DEFAULT '',
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    security_type VARCHAR(80) NOT NULL DEFAULT '',
    bg_cheque_no VARCHAR(80) NOT NULL DEFAULT '',
    bin VARCHAR(60) NOT NULL DEFAULT '',
    nid VARCHAR(60) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sales_customer_id BIGINT UNSIGNED NULL COMMENT 'Copy in sales_customers for customer-type traders',
    rma_supplier_id BIGINT UNSIGNED NULL COMMENT 'Copy in rma_suppliers for supplier-type traders',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (trader_id),
    UNIQUE KEY uq_traders_code (trader_code),
    KEY ix_traders_category (category_id),
    KEY ix_traders_group (group_id),
    KEY ix_traders_name (trader_name),
    KEY ix_traders_mobile (mobile),
    CONSTRAINT fk_trader_category
        FOREIGN KEY (category_id) REFERENCES trader_categories (category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_trader_group
        FOREIGN KEY (group_id) REFERENCES trader_groups (group_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trader_attachments (
    attachment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trader_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attachment_id),
    UNIQUE KEY uq_trader_attachments_stored (stored_name),
    KEY ix_trader_attachments_trader (trader_id),
    CONSTRAINT fk_trader_attachment_trader
        FOREIGN KEY (trader_id) REFERENCES traders (trader_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales representatives (SR) and which SR looks after which trader ("Trader wise SR fixing").
CREATE TABLE IF NOT EXISTS sales_representatives (
    sr_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sr_code VARCHAR(30) NULL,
    sr_name VARCHAR(140) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    designation VARCHAR(80) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sr_id),
    UNIQUE KEY uq_sales_representatives_code (sr_code),
    KEY ix_sales_representatives_name (sr_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One open row (effective_to IS NULL) per trader is the current SR; closed rows are history.
CREATE TABLE IF NOT EXISTS trader_sr_assignments (
    assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trader_id BIGINT UNSIGNED NOT NULL,
    sr_id BIGINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    assigned_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assignment_id),
    KEY ix_trader_sr_trader (trader_id, effective_to),
    KEY ix_trader_sr_sr (sr_id),
    CONSTRAINT fk_trader_sr_trader
        FOREIGN KEY (trader_id) REFERENCES traders (trader_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_trader_sr_sr
        FOREIGN KEY (sr_id) REFERENCES sales_representatives (sr_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trading rules per trader ("Trading wise trader flag fixing").
CREATE TABLE IF NOT EXISTS trader_flags (
    trader_id BIGINT UNSIGNED NOT NULL,
    allow_credit TINYINT(1) NOT NULL DEFAULT 0,
    credit_limit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    credit_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    price_level ENUM('retail','wholesale','dealer','special') NOT NULL DEFAULT 'retail',
    special_discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    vat_exempt TINYINT(1) NOT NULL DEFAULT 0,
    block_sales TINYINT(1) NOT NULL DEFAULT 0,
    block_purchase TINYINT(1) NOT NULL DEFAULT 0,
    flag_note VARCHAR(255) NOT NULL DEFAULT '',
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (trader_id),
    CONSTRAINT fk_trader_flags_trader
        FOREIGN KEY (trader_id) REFERENCES traders (trader_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit of category changes ("Trader Convert"), e.g. Cash Customer -> Credit Customer.
CREATE TABLE IF NOT EXISTS trader_conversions (
    conversion_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trader_id BIGINT UNSIGNED NOT NULL,
    old_category_id TINYINT UNSIGNED NOT NULL,
    new_category_id TINYINT UNSIGNED NOT NULL,
    old_group_id BIGINT UNSIGNED NULL,
    new_group_id BIGINT UNSIGNED NULL,
    old_code VARCHAR(20) NOT NULL DEFAULT '',
    new_code VARCHAR(20) NOT NULL DEFAULT '',
    reason VARCHAR(255) NOT NULL DEFAULT '',
    converted_by BIGINT UNSIGNED NULL,
    converted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (conversion_id),
    KEY ix_trader_conversions_trader (trader_id),
    CONSTRAINT fk_trader_conversion_trader
        FOREIGN KEY (trader_id) REFERENCES traders (trader_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- SECTION: Customers
-- From billing/customer/schema.sql
-- Customer Management: customer profile, credit limit, ledger.
-- MySQL 8.0+. Safe to run again. Run after vsc/sales/schema.sql, vsc/sales/sales_retrun/schema.sql, vsc/trader/schema.sql
-- and vsc/sales/schema_v2.sql.
--
-- Customers are the sales_customers rows every sales screen uses. Their full profile is a customer-type trader
-- (traders, synced to sales_customers), credit rules are trader_flags. This file adds the role permission column and
-- two read-only views.

SET @db := DATABASE();

-- Role permission for this menu: View, Add, Edit, Delete, Credit Limit, Ledger (same 6-flag format as the other client rows).
-- Added once. A database from before this module starts it from the role's old ivr_client rights.
SET @need := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='admin_role' AND column_name='customer_mgmt') = 0;
SET @q := IF(@need, "ALTER TABLE admin_role ADD COLUMN customer_mgmt VARCHAR(30) NOT NULL DEFAULT '1,1,1,1,1,1'", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
-- Older roles keep 5 flags; the 6th (Ledger) then follows the role's View flag.
SET @old := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='admin_role' AND column_name='ivr_client') > 0;
SET @q := IF(@need AND @old, "UPDATE admin_role SET customer_mgmt=IF(ivr_client REGEXP '^[01](,[01]){4}$', CONCAT(ivr_client, ',', LEFT(ivr_client, 1)), ivr_client) WHERE ivr_client REGEXP '^[01](,[01]){4,5}$'", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- One row per customer: profile link, credit rules and what is owed now
-- (opening balance + the due of every posted invoice, which already counts receipts and returns).
CREATE OR REPLACE VIEW customer_accounts AS
SELECT c.customer_id, c.customer_code, c.customer_group, c.customer_name, c.mobile, c.attention, c.address, c.bin, c.nid,
       t.trader_id, t.trader_code, t.category_id, COALESCE(tc.category_name, '') AS category_name, COALESCE(t.is_active, 1) AS is_active,
       COALESCE(t.opening_balance, 0) AS opening_balance,
       f.trader_id IS NOT NULL AS has_credit_rules,
       COALESCE(f.allow_credit, 0) AS allow_credit, COALESCE(f.credit_limit, 0) AS credit_limit, COALESCE(f.credit_days, 0) AS credit_days,
       COALESCE(f.block_sales, 0) AS block_sales, COALESCE(f.price_level, 'retail') AS price_level,
       COALESCE(i.invoice_due, 0) AS invoice_due,
       COALESCE(t.opening_balance, 0) + COALESCE(i.invoice_due, 0) AS balance,
       COALESCE(i.total_sales, 0) AS total_sales, COALESCE(i.invoice_count, 0) AS invoice_count, i.last_invoice_date, i.oldest_due_date,
       (SELECT s.sr_name FROM trader_sr_assignments a JOIN sales_representatives s ON s.sr_id = a.sr_id
        WHERE a.trader_id = t.trader_id AND a.effective_to IS NULL ORDER BY a.assignment_id DESC LIMIT 1) AS sr_name
FROM sales_customers c
LEFT JOIN traders t ON t.sales_customer_id = c.customer_id
LEFT JOIN trader_categories tc ON tc.category_id = t.category_id
LEFT JOIN trader_flags f ON f.trader_id = t.trader_id
LEFT JOIN (SELECT customer_id, SUM(balance_amount) AS invoice_due, SUM(net_amount) AS total_sales, COUNT(*) AS invoice_count,
                  MAX(sales_date) AS last_invoice_date, MIN(CASE WHEN balance_amount > 0.004 THEN sales_date END) AS oldest_due_date
           FROM sales_invoices WHERE status = 'posted' AND customer_id IS NOT NULL GROUP BY customer_id) i ON i.customer_id = c.customer_id;

-- Ledger lines per customer: sale (debit), receipt (credit), sales return (credit) and refund (debit).
-- Their sum plus the opening balance equals customer_accounts.balance.
CREATE OR REPLACE VIEW customer_ledger_entries AS
SELECT v.customer_id, v.sales_date AS entry_date, 1 AS sort_order, v.invoice_id AS ref_id, v.invoice_no AS doc_no, 'Sales Invoice' AS entry_type,
       v.ref_no, v.net_amount AS debit, 0.00 AS credit
FROM sales_invoices v
WHERE v.status = 'posted' AND v.customer_id IS NOT NULL
UNION ALL
SELECT v.customer_id, DATE(p.paid_at), 2, p.payment_id, v.invoice_no, CONCAT('Receipt (', p.payment_method, ')'), p.reference, 0.00, p.amount
FROM sales_invoice_payments p
JOIN sales_invoices v ON v.invoice_id = p.invoice_id AND v.status = 'posted'
WHERE v.customer_id IS NOT NULL
UNION ALL
SELECT v.customer_id, r.return_date, 3, r.return_id, r.return_no, 'Sales Return', v.invoice_no, 0.00, r.return_amount
FROM sales_returns r
JOIN sales_invoices v ON v.invoice_id = r.invoice_id AND v.status = 'posted'
WHERE r.status = 'posted' AND v.customer_id IS NOT NULL
UNION ALL
SELECT v.customer_id, r.return_date, 4, r.return_id, r.return_no, 'Refund', v.invoice_no, r.refund_amount, 0.00
FROM sales_returns r
JOIN sales_invoices v ON v.invoice_id = r.invoice_id AND v.status = 'posted'
WHERE r.status = 'posted' AND r.refund_amount > 0 AND v.customer_id IS NOT NULL;
-- SECTION: Sales team
-- From billing/sales_person/schema.sql
-- Sales Person Management: SR, territory, target, commission.
-- MySQL 8.0+. Safe to run again. Run after vsc/sales/schema.sql, vsc/sales/sales_order/schema.sql, vsc/trader/schema.sql
-- and vsc/sales/schema_v2.sql.
--
-- SRs are the existing sales_representatives rows (also used by Trader wise SR Fixing). This file adds their
-- territory and commission settings, monthly targets, commission payments, and an sr_id link on invoices and orders.

SET @db := DATABASE();

-- Role permission for this menu: View, Add, Edit, Delete, Commission Payment, Targets. Added once; a database from
-- before this module starts it from the role's old common_client rights.
SET @need := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='admin_role' AND column_name='sales_person_mgmt') = 0;
SET @q := IF(@need, "ALTER TABLE admin_role ADD COLUMN sales_person_mgmt VARCHAR(30) NOT NULL DEFAULT '1,1,1,1,1,1'", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
-- Older roles keep 5 flags; the 6th (Targets) then follows the role's View flag.
SET @old := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='admin_role' AND column_name='common_client') > 0;
SET @q := IF(@need AND @old, "UPDATE admin_role SET sales_person_mgmt=IF(common_client REGEXP '^[01](,[01]){4}$', CONCAT(common_client, ',', LEFT(common_client, 1)), common_client) WHERE common_client REGEXP '^[01](,[01]){4,5}$'", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS sr_territories (
    territory_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    territory_code VARCHAR(20) NOT NULL DEFAULT '',
    territory_name VARCHAR(120) NOT NULL,
    region VARCHAR(120) NOT NULL DEFAULT '',
    parent_id INT UNSIGNED NULL COMMENT 'Wider territory this one is part of',
    description VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (territory_id),
    UNIQUE KEY uq_sr_territories_name (territory_name),
    CONSTRAINT fk_sr_territory_parent FOREIGN KEY (parent_id) REFERENCES sr_territories (territory_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SR profile and commission settings. Each ALTER runs only when its column is missing.
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_representatives' AND column_name='territory_id') = 0,
    "ALTER TABLE sales_representatives ADD COLUMN territory_id INT UNSIGNED NULL AFTER designation, ADD COLUMN email VARCHAR(190) NOT NULL DEFAULT '' AFTER mobile, ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT '' AFTER email, ADD COLUMN joining_date DATE NULL AFTER territory_id, ADD COLUMN commission_basis ENUM('none','net_sales','collection','gross_profit') NOT NULL DEFAULT 'net_sales' AFTER joining_date, ADD COLUMN commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percent of the basis' AFTER commission_basis, ADD COLUMN target_bonus_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Extra percent of net sales when the month target is reached' AFTER commission_rate, ADD KEY ix_sales_representatives_territory (territory_id), ADD CONSTRAINT fk_sr_territory FOREIGN KEY (territory_id) REFERENCES sr_territories (territory_id) ON UPDATE CASCADE ON DELETE SET NULL",
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Monthly targets (target_month is the first day of the month).
CREATE TABLE IF NOT EXISTS sr_targets (
    target_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sr_id BIGINT UNSIGNED NOT NULL,
    target_month DATE NOT NULL,
    sales_target DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    collection_target DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(190) NOT NULL DEFAULT '',
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (target_id),
    UNIQUE KEY uq_sr_targets (sr_id, target_month),
    CONSTRAINT fk_sr_target_sr FOREIGN KEY (sr_id) REFERENCES sales_representatives (sr_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT ck_sr_target_month CHECK (DAY(target_month) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commission paid to an SR for a month.
CREATE TABLE IF NOT EXISTS sr_commission_payments (
    payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sr_id BIGINT UNSIGNED NOT NULL,
    period_month DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_on DATE NOT NULL,
    payment_method ENUM('cash','bank','mobile','other') NOT NULL DEFAULT 'cash',
    reference VARCHAR(120) NOT NULL DEFAULT '',
    note VARCHAR(190) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    KEY ix_sr_commission_payments (sr_id, period_month),
    CONSTRAINT fk_sr_commission_payment_sr FOREIGN KEY (sr_id) REFERENCES sales_representatives (sr_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_sr_commission_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which SR made an invoice or order. Set when saved (sales person name = SR name); old rows are matched by name below.
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='sr_id') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN sr_id BIGINT UNSIGNED NULL AFTER sales_person, ADD KEY ix_sales_invoices_sr (sr_id, sales_date)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_orders' AND column_name='sr_id') = 0,
    'ALTER TABLE sales_orders ADD COLUMN sr_id BIGINT UNSIGNED NULL AFTER sales_person, ADD KEY ix_sales_orders_sr (sr_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
UPDATE sales_invoices v JOIN sales_representatives s ON s.sr_name = v.sales_person SET v.sr_id = s.sr_id WHERE v.sr_id IS NULL AND v.sales_person <> '';
UPDATE sales_orders o JOIN sales_representatives s ON s.sr_name = o.sales_person SET o.sr_id = s.sr_id WHERE o.sr_id IS NULL AND o.sales_person <> '';
-- SECTION: Customer portal
-- From customer_portal/schema.sql
-- Customer Portal (billing_star/customer_portal, formerly the public booking site in billing_star/pinless).
-- MySQL 8.0+. Safe to run again. Run after the sales, trader, inventory and vsc/customer/schema.sql files
-- (the portal shows data from customer_accounts and customer_ledger_entries).
--
-- Customers sign in with their own portal account and see only their own invoices, statement, orders,
-- payments, warranty and service tickets, and send requests to the shop. Staff manage accounts, requests and
-- notices in VSC > Customer Management. The bus/car booking tables the old pages used are not touched.

-- One login per customer (sales_customers). Passwords are stored with PHP password_hash().
CREATE TABLE IF NOT EXISTS portal_accounts (
    account_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    login VARCHAR(60) NOT NULL COMMENT 'Usually the mobile number',
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NOT NULL DEFAULT '',
    created_via ENUM('staff','signup') NOT NULL DEFAULT 'staff',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id),
    UNIQUE KEY uq_portal_accounts_customer (customer_id),
    UNIQUE KEY uq_portal_accounts_login (login),
    CONSTRAINT fk_portal_account_customer FOREIGN KEY (customer_id) REFERENCES sales_customers (customer_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every sign-in attempt, for the lockout rules and for staff to review.
CREATE TABLE IF NOT EXISTS portal_login_log (
    log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NULL,
    login VARCHAR(60) NOT NULL DEFAULT '',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    success TINYINT(1) NOT NULL DEFAULT 0,
    reason VARCHAR(60) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY ix_portal_login_log_account (account_id, created_at),
    KEY ix_portal_login_log_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Requests from customers (quotation, support, payment, account) and the conversation with staff.
CREATE TABLE IF NOT EXISTS portal_requests (
    request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_no VARCHAR(30) NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    account_id BIGINT UNSIGNED NULL,
    request_type ENUM('quotation','support','payment','account','password','other') NOT NULL DEFAULT 'other',
    subject VARCHAR(190) NOT NULL,
    contact VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'For password help sent before signing in',
    status ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
    last_reply_by ENUM('customer','staff') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    UNIQUE KEY uq_portal_requests_no (request_no),
    KEY ix_portal_requests_customer (customer_id, created_at),
    KEY ix_portal_requests_status (status, updated_at),
    CONSTRAINT fk_portal_request_customer FOREIGN KEY (customer_id) REFERENCES sales_customers (customer_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_request_messages (
    message_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    sender ENUM('customer','staff') NOT NULL,
    staff_user_id BIGINT UNSIGNED NULL,
    staff_name VARCHAR(120) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id),
    KEY ix_portal_request_messages_request (request_id, message_id),
    CONSTRAINT fk_portal_message_request FOREIGN KEY (request_id) REFERENCES portal_requests (request_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notices shown on the portal dashboard between two dates.
CREATE TABLE IF NOT EXISTS portal_notices (
    notice_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notice_id),
    KEY ix_portal_notices_dates (is_active, starts_on, ends_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_settings (
    setting_key VARCHAR(60) NOT NULL,
    setting_value VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- allow_signup: customers may create their own account with their mobile number and one of their invoice numbers.
INSERT IGNORE INTO portal_settings (setting_key, setting_value) VALUES
    ('portal_title', 'Customer Portal'), ('allow_signup', '1'), ('contact_phone', ''), ('contact_email', ''), ('welcome_text', 'View your invoices, statement, orders and warranty, and send us requests.');
-- SECTION: SR portal
-- From sr_portal/schema.sql
-- SR / Staff Portal (sr_portal).
-- MySQL 8.0+. Safe to run again. Run after the sales, trader, inventory, billing/customer/schema.sql and
-- billing/sales_person/schema.sql files.
--
-- Sales representatives (sales_representatives, managed in Sales Person Management) sign in to see their customers, sales, targets and commission, book orders and record collections.
-- Booked orders arrive in the office as pending Sales Orders; collections wait until staff verify them, and only
-- then become payments on the customer's invoices.

-- One portal login per SR, with what the SR may do.
CREATE TABLE IF NOT EXISTS sr_portal_accounts (
    account_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sr_id BIGINT UNSIGNED NOT NULL,
    login VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    can_book_orders TINYINT(1) NOT NULL DEFAULT 1,
    can_collect TINYINT(1) NOT NULL DEFAULT 1,
    see_all_customers TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Supervisor / office staff: every customer, not only the SR''s own',
    failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id),
    UNIQUE KEY uq_sr_portal_accounts_sr (sr_id),
    UNIQUE KEY uq_sr_portal_accounts_login (login),
    CONSTRAINT fk_sr_portal_account_sr FOREIGN KEY (sr_id) REFERENCES sales_representatives (sr_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sr_portal_login_log (
    log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NULL,
    login VARCHAR(60) NOT NULL DEFAULT '',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    success TINYINT(1) NOT NULL DEFAULT 0,
    reason VARCHAR(60) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY ix_sr_portal_login_log_account (account_id, created_at),
    KEY ix_sr_portal_login_log_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Money an SR says was collected from a customer. Becomes invoice payments only when staff verify it.
CREATE TABLE IF NOT EXISTS sr_collections (
    collection_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    collection_no VARCHAR(30) NOT NULL,
    sr_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    collected_on DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method ENUM('cash','bank','mobile','other') NOT NULL DEFAULT 'cash',
    reference VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'Cheque, transaction or receipt number',
    note VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    verified_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    staff_note VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (collection_id),
    UNIQUE KEY uq_sr_collections_no (collection_no),
    KEY ix_sr_collections_sr (sr_id, collected_on),
    KEY ix_sr_collections_status (status, created_at),
    CONSTRAINT fk_sr_collection_sr FOREIGN KEY (sr_id) REFERENCES sales_representatives (sr_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sr_collection_customer FOREIGN KEY (customer_id) REFERENCES sales_customers (customer_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_sr_collection_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which invoices a verified collection paid (oldest due first), and the payment row it created on each.
CREATE TABLE IF NOT EXISTS sr_collection_allocations (
    allocation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    collection_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    PRIMARY KEY (allocation_id),
    KEY ix_sr_collection_alloc_collection (collection_id),
    KEY ix_sr_collection_alloc_invoice (invoice_id),
    CONSTRAINT fk_sr_collection_alloc_collection FOREIGN KEY (collection_id) REFERENCES sr_collections (collection_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders booked from the portal (NULL = entered in the office).
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_orders' AND column_name='portal_account_id') = 0,
    'ALTER TABLE sales_orders ADD COLUMN portal_account_id BIGINT UNSIGNED NULL COMMENT ''sr_portal_accounts row that booked it'', ADD KEY ix_sales_orders_portal (portal_account_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
-- SECTION: Service (RMA)
-- From billing/rma/schema.sql
-- BusBook RMA (warranty / service) module (MySQL 8.0+)
-- Run AFTER vsc/sales/schema.sql (customers, products and stores are shared masters).
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS rma_sequences (
    doc_type VARCHAR(10) NOT NULL,
    seq_year SMALLINT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (doc_type, seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rma_problems (
    problem_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    problem_name VARCHAR(190) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (problem_id),
    UNIQUE KEY uq_rma_problems_name (problem_name)
) ENGINE=InnoDB AUTO_INCREMENT=807 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rma_suppliers (
    supplier_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_group VARCHAR(120) NOT NULL DEFAULT '',
    supplier_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    attention VARCHAR(190) NOT NULL DEFAULT '',
    address TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (supplier_id),
    UNIQUE KEY uq_rma_suppliers_name (supplier_name),
    KEY ix_rma_suppliers_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "RMA Delivery Store Fixing": default store for each RMA operation.
CREATE TABLE IF NOT EXISTS rma_store_settings (
    purpose ENUM('complain_receive','replace_out','replace_in','delivery','utilize') NOT NULL,
    store_name VARCHAR(140) NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Complain Receive
CREATE TABLE IF NOT EXISTS rma_complains (
    complain_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    complain_no VARCHAR(40) NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    complain_date DATE NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    customer_group VARCHAR(120) NOT NULL DEFAULT '',
    customer_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    address TEXT NULL,
    attention VARCHAR(190) NOT NULL DEFAULT '',
    status ENUM('draft','approved') NOT NULL DEFAULT 'draft',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_by_name VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (complain_id),
    UNIQUE KEY uq_rma_complains_no (complain_no),
    KEY ix_rma_complains_date (complain_date),
    KEY ix_rma_complains_mobile (mobile),
    KEY ix_rma_complains_status (status),
    CONSTRAINT fk_rma_complain_customer FOREIGN KEY (customer_id)
        REFERENCES sales_customers (customer_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rma_complain_items (
    item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    complain_id BIGINT UNSIGNED NOT NULL,
    line_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NULL,
    product_group VARCHAR(140) NOT NULL DEFAULT '',
    brand VARCHAR(140) NOT NULL DEFAULT '',
    model_no VARCHAR(190) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    sequence_type ENUM('not_available','auto_generated') NOT NULL DEFAULT 'not_available',
    under_warranty TINYINT(1) NOT NULL DEFAULT 1,
    sales_invoice_no VARCHAR(40) NOT NULL DEFAULT '',
    sales_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    sales_date DATE NULL,
    sold_serial VARCHAR(120) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    delivery_date DATE NULL,
    problem_id INT UNSIGNED NULL,
    problem_name VARCHAR(190) NOT NULL DEFAULT '',
    engineer_name VARCHAR(140) NOT NULL DEFAULT '',
    no_of_time INT UNSIGNED NOT NULL DEFAULT 0,
    pre_job_ref_no VARCHAR(40) NOT NULL DEFAULT '',
    narration VARCHAR(255) NOT NULL DEFAULT '',
    qty DECIMAL(15,3) NOT NULL DEFAULT 1.000,
    received_serial VARCHAR(120) NOT NULL DEFAULT '',
    amount_charges DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    need_diagnosis TINYINT(1) NOT NULL DEFAULT 0,
    line_status ENUM('received','diagnosed','sent_supplier','replaced_in','delivered','settled') NOT NULL DEFAULT 'received',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    KEY ix_rma_ci_complain (complain_id),
    KEY ix_rma_ci_serial (received_serial),
    KEY ix_rma_ci_status (line_status),
    CONSTRAINT fk_rma_ci_complain FOREIGN KEY (complain_id)
        REFERENCES rma_complains (complain_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rma_ci_product FOREIGN KEY (product_id)
        REFERENCES sales_products (product_id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_rma_ci_problem FOREIGN KEY (problem_id)
        REFERENCES rma_problems (problem_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Diagnosis
CREATE TABLE IF NOT EXISTS rma_diagnoses (
    diagnosis_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    diagnosis_no VARCHAR(40) NOT NULL,
    diagnosis_date DATE NOT NULL,
    complain_item_id BIGINT UNSIGNED NOT NULL,
    engineer_name VARCHAR(140) NOT NULL DEFAULT '',
    findings TEXT NULL,
    result ENUM('repairable','replace','no_fault','beyond_repair') NOT NULL DEFAULT 'repairable',
    estimated_charge DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_by BIGINT UNSIGNED NULL,
    created_by_name VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (diagnosis_id),
    UNIQUE KEY uq_rma_diagnoses_no (diagnosis_no),
    KEY ix_rma_diag_item (complain_item_id),
    CONSTRAINT fk_rma_diag_item FOREIGN KEY (complain_item_id)
        REFERENCES rma_complain_items (item_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Item Utilize
CREATE TABLE IF NOT EXISTS rma_utilizes (
    utilize_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilize_no VARCHAR(40) NOT NULL,
    utilize_date DATE NOT NULL,
    complain_item_id BIGINT UNSIGNED NULL,
    job_ref_no VARCHAR(40) NOT NULL DEFAULT '',
    engineer_name VARCHAR(140) NOT NULL DEFAULT '',
    narration VARCHAR(255) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_by_name VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (utilize_id),
    UNIQUE KEY uq_rma_utilizes_no (utilize_no),
    KEY ix_rma_util_item (complain_item_id),
    CONSTRAINT fk_rma_util_item FOREIGN KEY (complain_item_id)
        REFERENCES rma_complain_items (item_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rma_utilize_items (
    item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilize_id BIGINT UNSIGNED NOT NULL,
    line_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NULL,
    product_group VARCHAR(140) NOT NULL DEFAULT '',
    brand VARCHAR(140) NOT NULL DEFAULT '',
    model_no VARCHAR(190) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    serial_no VARCHAR(120) NOT NULL DEFAULT '',
    qty DECIMAL(15,3) NOT NULL DEFAULT 1.000,
    unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (item_id),
    KEY ix_rma_ui_utilize (utilize_id),
    CONSTRAINT fk_rma_ui_utilize FOREIGN KEY (utilize_id)
        REFERENCES rma_utilizes (utilize_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Replace Out (to supplier)
CREATE TABLE IF NOT EXISTS rma_replace_outs (
    replace_out_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    replace_out_no VARCHAR(40) NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    replace_date DATE NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,
    supplier_group VARCHAR(120) NOT NULL DEFAULT '',
    supplier_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    address TEXT NULL,
    attention VARCHAR(190) NOT NULL DEFAULT '',
    narration VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('draft','approved') NOT NULL DEFAULT 'draft',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_by_name VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (replace_out_id),
    UNIQUE KEY uq_rma_ro_no (replace_out_no),
    KEY ix_rma_ro_date (replace_date),
    KEY ix_rma_ro_status (status),
    CONSTRAINT fk_rma_ro_supplier FOREIGN KEY (supplier_id)
        REFERENCES rma_suppliers (supplier_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rma_replace_out_items (
    item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    replace_out_id BIGINT UNSIGNED NOT NULL,
    line_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    complain_item_id BIGINT UNSIGNED NULL,
    product_id BIGINT UNSIGNED NULL,
    product_group VARCHAR(140) NOT NULL DEFAULT '',
    brand VARCHAR(140) NOT NULL DEFAULT '',
    model_no VARCHAR(190) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    sequence_type ENUM('not_available','available') NOT NULL DEFAULT 'not_available',
    under_warranty TINYINT(1) NOT NULL DEFAULT 1,
    serial_common VARCHAR(120) NOT NULL DEFAULT '',
    serial_from VARCHAR(120) NOT NULL DEFAULT '',
    serial_to VARCHAR(120) NOT NULL DEFAULT '',
    no_of_time INT UNSIGNED NOT NULL DEFAULT 0,
    pre_job_ref_no VARCHAR(40) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    problem_name VARCHAR(190) NOT NULL DEFAULT '',
    purchase_inv_no VARCHAR(40) NOT NULL DEFAULT '',
    purchase_date DATE NULL,
    purchase_serial VARCHAR(120) NOT NULL DEFAULT '',
    out_serial VARCHAR(120) NOT NULL DEFAULT '',
    qty DECIMAL(15,3) NOT NULL DEFAULT 1.000,
    line_status ENUM('sent','received','closed') NOT NULL DEFAULT 'sent',
    PRIMARY KEY (item_id),
    KEY ix_rma_roi_ro (replace_out_id),
    KEY ix_rma_roi_complain (complain_item_id),
    KEY ix_rma_roi_serial (out_serial),
    CONSTRAINT fk_rma_roi_ro FOREIGN KEY (replace_out_id)
        REFERENCES rma_replace_outs (replace_out_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rma_roi_complain FOREIGN KEY (complain_item_id)
        REFERENCES rma_complain_items (item_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Replace In (from supplier)
CREATE TABLE IF NOT EXISTS rma_replace_ins (
    replace_in_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    replace_in_no VARCHAR(40) NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    replace_date DATE NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,
    supplier_group VARCHAR(120) NOT NULL DEFAULT '',
    supplier_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    address TEXT NULL,
    narration VARCHAR(255) NOT NULL DEFAULT '',
    total_receivable DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_payable DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','approved') NOT NULL DEFAULT 'draft',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_by_name VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (replace_in_id),
    UNIQUE KEY uq_rma_ri_no (replace_in_no),
    KEY ix_rma_ri_date (replace_date),
    KEY ix_rma_ri_status (status),
    CONSTRAINT fk_rma_ri_supplier FOREIGN KEY (supplier_id)
        REFERENCES rma_suppliers (supplier_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rma_replace_in_items (
    item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    replace_in_id BIGINT UNSIGNED NOT NULL,
    line_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    replace_out_item_id BIGINT UNSIGNED NULL,
    out_job_ref_no VARCHAR(40) NOT NULL DEFAULT '',
    out_product_group VARCHAR(140) NOT NULL DEFAULT '',
    out_model_no VARCHAR(190) NOT NULL DEFAULT '',
    out_serial VARCHAR(120) NOT NULL DEFAULT '',
    out_qty DECIMAL(15,3) NOT NULL DEFAULT 1.000,
    adjustment_type ENUM('without','receivable','payable','product_receivable','product_payable') NOT NULL DEFAULT 'without',
    product_id BIGINT UNSIGNED NULL,
    product_group VARCHAR(140) NOT NULL DEFAULT '',
    brand VARCHAR(140) NOT NULL DEFAULT '',
    model_no VARCHAR(190) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    serial_no VARCHAR(120) NOT NULL DEFAULT '',
    qty DECIMAL(15,3) NOT NULL DEFAULT 1.000,
    receivable DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    payable DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (item_id),
    KEY ix_rma_rii_ri (replace_in_id),
    KEY ix_rma_rii_roi (replace_out_item_id),
    CONSTRAINT fk_rma_rii_ri FOREIGN KEY (replace_in_id)
        REFERENCES rma_replace_ins (replace_in_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rma_rii_roi FOREIGN KEY (replace_out_item_id)
        REFERENCES rma_replace_out_items (item_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Replacement Delivery (to customer)
CREATE TABLE IF NOT EXISTS rma_deliveries (
    delivery_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    delivery_no VARCHAR(40) NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    delivery_date DATE NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    customer_group VARCHAR(120) NOT NULL DEFAULT '',
    customer_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    address TEXT NULL,
    total_receivable DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_payable DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_settled TINYINT(1) NOT NULL DEFAULT 0,
    settled_at DATETIME NULL,
    status ENUM('draft','approved') NOT NULL DEFAULT 'draft',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_by_name VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (delivery_id),
    UNIQUE KEY uq_rma_dl_no (delivery_no),
    KEY ix_rma_dl_date (delivery_date),
    KEY ix_rma_dl_status (status),
    CONSTRAINT fk_rma_dl_customer FOREIGN KEY (customer_id)
        REFERENCES sales_customers (customer_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rma_delivery_items (
    item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    delivery_id BIGINT UNSIGNED NOT NULL,
    line_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    complain_item_id BIGINT UNSIGNED NULL,
    complain_no VARCHAR(40) NOT NULL DEFAULT '',
    received_product_group VARCHAR(140) NOT NULL DEFAULT '',
    received_model_no VARCHAR(190) NOT NULL DEFAULT '',
    received_serial VARCHAR(120) NOT NULL DEFAULT '',
    received_qty DECIMAL(15,3) NOT NULL DEFAULT 1.000,
    adjustment_type ENUM('without','receivable','payable','product_receivable','product_payable') NOT NULL DEFAULT 'without',
    product_id BIGINT UNSIGNED NULL,
    product_group VARCHAR(140) NOT NULL DEFAULT '',
    brand VARCHAR(140) NOT NULL DEFAULT '',
    model_no VARCHAR(190) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    narration VARCHAR(255) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    serial_no VARCHAR(120) NOT NULL DEFAULT '',
    delivery_qty DECIMAL(15,3) NOT NULL DEFAULT 1.000,
    receivable DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    payable DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (item_id),
    KEY ix_rma_dli_dl (delivery_id),
    KEY ix_rma_dli_complain (complain_item_id),
    CONSTRAINT fk_rma_dli_dl FOREIGN KEY (delivery_id)
        REFERENCES rma_deliveries (delivery_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rma_dli_complain FOREIGN KEY (complain_item_id)
        REFERENCES rma_complain_items (item_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Service Bill
CREATE TABLE IF NOT EXISTS rma_service_bills (
    bill_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bill_no VARCHAR(40) NOT NULL,
    ref_no VARCHAR(80) NOT NULL DEFAULT '',
    bill_date DATE NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    customer_group VARCHAR(120) NOT NULL DEFAULT '',
    customer_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    address TEXT NULL,
    narration VARCHAR(255) NOT NULL DEFAULT '',
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    balance_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','approved') NOT NULL DEFAULT 'draft',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_by_name VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bill_id),
    UNIQUE KEY uq_rma_sb_no (bill_no),
    KEY ix_rma_sb_date (bill_date),
    KEY ix_rma_sb_status (status),
    CONSTRAINT fk_rma_sb_customer FOREIGN KEY (customer_id)
        REFERENCES sales_customers (customer_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rma_service_bill_items (
    item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bill_id BIGINT UNSIGNED NOT NULL,
    line_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    complain_item_id BIGINT UNSIGNED NULL,
    complain_no VARCHAR(40) NOT NULL DEFAULT '',
    model_no VARCHAR(190) NOT NULL DEFAULT '',
    serial_no VARCHAR(120) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    service_charge DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    parts_charge DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (item_id),
    KEY ix_rma_sbi_bill (bill_id),
    CONSTRAINT fk_rma_sbi_bill FOREIGN KEY (bill_id)
        REFERENCES rma_service_bills (bill_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rma_sbi_complain FOREIGN KEY (complain_item_id)
        REFERENCES rma_complain_items (item_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Stock ledger
CREATE TABLE IF NOT EXISTS rma_stock_ledger (
    ledger_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    txn_date DATE NOT NULL,
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    product_id BIGINT UNSIGNED NULL,
    product_group VARCHAR(140) NOT NULL DEFAULT '',
    brand VARCHAR(140) NOT NULL DEFAULT '',
    model_no VARCHAR(190) NOT NULL DEFAULT '',
    category ENUM('faulty','good') NOT NULL DEFAULT 'faulty',
    serial_no VARCHAR(120) NOT NULL DEFAULT '',
    qty_in DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    qty_out DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    doc_type VARCHAR(20) NOT NULL,
    doc_id BIGINT UNSIGNED NOT NULL,
    doc_no VARCHAR(40) NOT NULL DEFAULT '',
    party_name VARCHAR(190) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ledger_id),
    KEY ix_rma_sl_date (txn_date),
    KEY ix_rma_sl_store (store_name),
    KEY ix_rma_sl_model (model_no),
    KEY ix_rma_sl_group_brand (product_group, brand),
    KEY ix_rma_sl_doc (doc_type, doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Seed data
INSERT IGNORE INTO rma_problems (problem_name) VALUES ('DISPLAY'), ('No power'), ('No Detected'), ('Hang Problem');
INSERT IGNORE INTO sales_stores (store_name) VALUES ('RMA STORE');
INSERT IGNORE INTO rma_store_settings (purpose, store_name) VALUES
    ('complain_receive','RMA STORE'), ('replace_out','RMA STORE'), ('replace_in','RMA STORE'),
    ('delivery','RMA STORE'), ('utilize','RMA STORE');

-- Who approved, saved or deleted a service document. Written by bus_admin_audit() in billing/core/admin_bootstrap.php.
CREATE TABLE IF NOT EXISTS bus_admin_audit (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT DEFAULT NULL,
    action_code VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    details_json TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_id),
    KEY idx_bus_audit_entity (entity_type, entity_id, created_at),
    KEY idx_bus_audit_actor (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- SECTION: Reports
-- From billing/report/schema.sql
-- Reports > Stock / Sales / Purchase / Transfer / Challan registers. MySQL 8.0+. Safe to run again.
-- Run after vsc/sales/schema.sql, sales_order/schema.sql, sal_bill_from_sal_order/schema.sql,
-- sales_retrun/schema.sql, vsc/inventory/schema.sql and vsc/sales/schema_v2.sql. Creates read-only views and a few indexes; no data changes.
-- Every view names its date column doc_date and uses the same product, party and store column names,
-- so the register filters work the same way on each of them.

-- Weighted average and last receipt cost per product, from purchases and Stock Amendment IN.
CREATE OR REPLACE VIEW rep_product_cost AS
SELECT m.product_id,
       ROUND(SUM(m.qty_in * m.unit_cost) / NULLIF(SUM(m.qty_in), 0), 4) AS avg_cost,
       CAST(SUBSTRING_INDEX(GROUP_CONCAT(m.unit_cost ORDER BY m.move_id DESC), ',', 1) AS DECIMAL(15,2)) AS last_cost
FROM inv_stock_moves m
WHERE m.qty_in > 0 AND m.doc_type IN ('purchase', 'stock_in')
GROUP BY m.product_id;

-- Every stock in / out with product, document and branch details.
CREATE OR REPLACE VIEW rep_stock_moves AS
SELECT m.move_id, m.move_date AS doc_date, m.product_id, p.product_code, p.product_group, p.brand, p.model_caption, p.description, p.unit_name,
       m.store_name, m.branch_id, COALESCE(b.branch_name, '') AS branch_name, m.qty_in, m.qty_out, m.unit_cost,
       m.doc_type, m.doc_id, m.doc_no, COALESCE(d.party_name, '') AS party_name, COALESCE(d.ref_no, '') AS ref_no,
       COALESCE(d.approval_status, 'approved') AS approval_status, COALESCE(c.avg_cost, 0) AS avg_cost
FROM inv_stock_moves m
JOIN sales_products p ON p.product_id = m.product_id
LEFT JOIN inv_docs d ON d.doc_id = m.doc_id
LEFT JOIN branches b ON b.branch_id = m.branch_id
LEFT JOIN rep_product_cost c ON c.product_id = m.product_id;

-- Inventory documents (purchase, requisition, purchase order, challan, transfer, amendments ...), headers and lines.
CREATE OR REPLACE VIEW rep_inv_docs AS
SELECT d.*, COALESCE(b.branch_name, '') AS branch_name
FROM inv_docs d
LEFT JOIN branches b ON b.branch_id = d.branch_id
WHERE d.status = 'active';

CREATE OR REPLACE VIEW rep_inv_lines AS
SELECT d.doc_id, d.doc_type, d.doc_no, d.doc_date, d.ref_no, d.branch_id, COALESCE(b.branch_name, '') AS branch_name,
       d.party_group, d.party_name, d.mobile, d.sales_person, d.bill_type, d.source_type, d.source_no, d.required_date,
       d.delivery_point, d.approval_status, d.sales_invoice_id, d.narration AS doc_narration,
       l.line_id, l.line_no, l.product_id, l.product_code, l.product_group, l.brand, l.model_caption, l.description, l.unit_name,
       l.store_name, l.store_to, l.quantity, l.free_quantity, l.unit_price, l.prev_price, l.discount_amount, l.line_total,
       COALESCE(l.serials, '') AS serials, l.narration AS line_narration, l.source_kind, l.source_line_id
FROM inv_doc_lines l
JOIN inv_docs d ON d.doc_id = l.doc_id
LEFT JOIN branches b ON b.branch_id = d.branch_id
WHERE d.status = 'active';

-- How much of each inventory line later documents have used (same rule as the document screens: quantity + free).
CREATE OR REPLACE VIEW rep_inv_line_usage AS
SELECT l.source_line_id AS line_id,
       SUM(CASE WHEN d.doc_type = 'purchase_order' THEN l.quantity + l.free_quantity ELSE 0 END) AS po_qty,
       SUM(CASE WHEN d.doc_type = 'purchase' THEN l.quantity + l.free_quantity ELSE 0 END) AS purchase_qty,
       SUM(CASE WHEN d.doc_type = 'challan_return' THEN l.quantity + l.free_quantity ELSE 0 END) AS return_qty,
       SUM(CASE WHEN d.doc_type = 'challan_bill' THEN l.quantity ELSE 0 END) AS bill_qty
FROM inv_doc_lines l
JOIN inv_docs d ON d.doc_id = l.doc_id
WHERE d.status = 'active' AND l.source_kind = 'inv' AND l.source_line_id IS NOT NULL
GROUP BY l.source_line_id;

-- Posted sales invoices. Branch is the invoice's own, or that of the challan bill that created it.
CREATE OR REPLACE VIEW rep_sales_invoices AS
SELECT v.invoice_id, v.invoice_no AS doc_no, v.sales_date AS doc_date, v.ref_no, v.bill_type, v.customer_id,
       v.customer_group AS party_group, v.customer_name AS party_name, v.mobile, v.sales_person,
       v.subtotal, v.extra_charges, v.discount_amount, v.vat_amount + v.ait_amount AS vat_amount, v.exchange_amount,
       v.net_amount, v.net_amount - v.balance_amount AS received_amount, v.balance_amount, v.total_quantity,
       COALESCE((SELECT SUM(i.discount_amount) FROM sales_invoice_items i WHERE i.invoice_id = v.invoice_id), 0) AS line_discount,
       COALESCE((SELECT b.branch_name FROM branches b WHERE b.branch_id = v.branch_id),
                (SELECT b.branch_name FROM inv_docs cb JOIN branches b ON b.branch_id = cb.branch_id
                 WHERE cb.sales_invoice_id = v.invoice_id ORDER BY cb.doc_id LIMIT 1), '') AS branch_name,
       v.approval_status
FROM sales_invoices v
WHERE v.status = 'posted';

-- Posted invoice lines with the invoice discount shared by line value and the cost of what was sold
-- (cost kept on the line when sold, else today's average purchase cost).
CREATE OR REPLACE VIEW rep_sales_lines AS
SELECT v.invoice_id, v.doc_no, v.doc_date, v.bill_type, v.party_group, v.party_name, v.mobile, v.sales_person, v.branch_name, v.approval_status,
       i.item_id, i.product_id, i.product_code, i.product_group, i.brand, i.model_caption, i.description, i.store_name, i.unit_name, i.serial_imei,
       i.quantity, i.free_quantity, i.unit_price, i.discount_amount, i.line_total,
       ROUND(CASE WHEN v.subtotal > 0 THEN v.discount_amount * i.line_total / v.subtotal ELSE 0 END, 2) AS invoice_discount,
       ROUND(i.line_total - CASE WHEN v.subtotal > 0 THEN v.discount_amount * i.line_total / v.subtotal ELSE 0 END, 2) AS net_amount,
       ROUND((i.quantity + i.free_quantity) * COALESCE(NULLIF(i.unit_cost, 0), c.avg_cost, 0), 2) AS cost_amount
FROM sales_invoice_items i
JOIN rep_sales_invoices v ON v.invoice_id = i.invoice_id
LEFT JOIN rep_product_cost c ON c.product_id = i.product_id;

-- Posted sales returns, headers and lines (product details come from the invoice line returned).
CREATE OR REPLACE VIEW rep_sales_returns AS
SELECT r.return_id, r.return_no AS doc_no, r.return_date AS doc_date, r.invoice_id, v.invoice_no,
       v.customer_group AS party_group, r.customer_name AS party_name, r.mobile, v.sales_person, r.reason,
       r.items_amount, r.deduction_amount, r.return_amount, r.due_adjusted, r.refund_amount, r.total_quantity, r.approval_status
FROM sales_returns r
JOIN sales_invoices v ON v.invoice_id = r.invoice_id
WHERE r.status = 'posted';

CREATE OR REPLACE VIEW rep_sales_return_lines AS
SELECT r.return_id, r.doc_no, r.doc_date, r.invoice_id, r.invoice_no, r.party_group, r.party_name, r.mobile, r.sales_person, r.approval_status,
       ri.return_item_id, ri.product_id, COALESCE(ii.product_code, ri.product_code) AS product_code,
       COALESCE(ii.product_group, '') AS product_group, COALESCE(ii.brand, '') AS brand, COALESCE(ii.model_caption, '') AS model_caption,
       ri.description, ri.store_name, ri.item_condition, ri.quantity, ri.free_quantity, ri.unit_value, ri.line_total,
       ROUND((ri.quantity + ri.free_quantity) * COALESCE(c.avg_cost, 0), 2) AS cost_amount
FROM sales_return_items ri
JOIN rep_sales_returns r ON r.return_id = ri.return_id
LEFT JOIN sales_invoice_items ii ON ii.item_id = ri.invoice_item_id
LEFT JOIN rep_product_cost c ON c.product_id = ri.product_id;

-- Sales order lines with what is billed (posted invoices) or delivered by challan, and what is still pending.
CREATE OR REPLACE VIEW rep_sales_order_lines AS
SELECT o.order_id, o.order_no AS doc_no, o.order_date AS doc_date, o.delivery_date, o.customer_group AS party_group, o.customer_name AS party_name,
       o.mobile, o.sales_person, o.status, o.approval_status, o.net_amount AS order_amount, o.advance_received,
       i.item_id, i.product_id, i.product_code, i.product_group, i.brand, i.model_caption, i.description, i.store_name,
       i.quantity, i.unit_price, i.discount_amount, i.line_total,
       COALESCE(bq.qty, 0) AS billed_qty, COALESCE(cq.qty, 0) AS challan_qty,
       GREATEST(i.quantity - COALESCE(bq.qty, 0) - COALESCE(cq.qty, 0), 0) AS pending_qty
FROM sales_order_items i
JOIN sales_orders o ON o.order_id = i.order_id
LEFT JOIN (SELECT bi.order_item_id, SUM(bi.quantity) AS qty FROM sales_order_bill_items bi
           JOIN sales_order_bills b ON b.bill_id = bi.bill_id
           JOIN sales_invoices v ON v.invoice_id = b.invoice_id AND v.status = 'posted'
           GROUP BY bi.order_item_id) bq ON bq.order_item_id = i.item_id
LEFT JOIN (SELECT l.source_line_id, SUM(l.quantity + l.free_quantity) AS qty FROM inv_doc_lines l
           JOIN inv_docs d ON d.doc_id = l.doc_id AND d.doc_type = 'challan' AND d.status = 'active'
           WHERE l.source_kind = 'sales_order'
           GROUP BY l.source_line_id) cq ON cq.source_line_id = i.item_id
WHERE o.status <> 'cancelled';

-- Customer statement rows: sale (debit), payment (credit), return (credit) and refund (debit).
CREATE OR REPLACE VIEW rep_sales_ledger AS
SELECT v.doc_date, 1 AS sort_order, v.invoice_id AS ref_id, v.doc_no, 'Sales' AS txn_type, v.party_group, v.party_name, v.mobile, v.sales_person,
       v.net_amount AS debit, 0.00 AS credit
FROM rep_sales_invoices v
UNION ALL
SELECT DATE(p.paid_at), 2, p.payment_id, v.doc_no, CONCAT('Received (', p.payment_method, ')'), v.party_group, v.party_name, v.mobile, v.sales_person,
       0.00, p.amount
FROM sales_invoice_payments p
JOIN rep_sales_invoices v ON v.invoice_id = p.invoice_id
UNION ALL
SELECT r.doc_date, 3, r.return_id, r.doc_no, 'Sales Return', r.party_group, r.party_name, r.mobile, r.sales_person, 0.00, r.return_amount
FROM rep_sales_returns r
UNION ALL
SELECT r.doc_date, 4, r.return_id, r.doc_no, 'Refund', r.party_group, r.party_name, r.mobile, r.sales_person, r.refund_amount, 0.00
FROM rep_sales_returns r
WHERE r.refund_amount > 0;

-- Indexes the registers filter on. Added only when missing.
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='inv_stock_moves' AND index_name='ix_inv_moves_date') = 0,
    'ALTER TABLE inv_stock_moves ADD KEY ix_inv_moves_date (move_date, product_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='sales_invoice_items' AND index_name='ix_sales_items_model') = 0,
    'ALTER TABLE sales_invoice_items ADD KEY ix_sales_items_model (product_group, brand, model_caption)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='inv_docs' AND index_name='ix_inv_docs_invoice') = 0,
    'ALTER TABLE inv_docs ADD KEY ix_inv_docs_invoice (sales_invoice_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='sales_returns' AND index_name='ix_sales_returns_date') = 0,
    'ALTER TABLE sales_returns ADD KEY ix_sales_returns_date (return_date)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
-- SECTION: Users and permissions
-- From billing/user/schema.sql
-- Users, branches and form permissions (Settings > Users & Roles). MySQL 8.0+. Safe to run again.
-- Run after billing/inventory/schema.sql (branches).
--
-- admin_user keeps being the only login table for the admin shell (vsc/topmain.php). It gains an access level, a user
-- type and the Registration fields. SuperAdmin passes every check; everyone else is limited to the rows in
-- admin_user_permissions, which addon/inc/perm.php enforces on each page.
-- user_type customer / employee links a login to sales_customers.customer_id / sales_representatives.sr_id: those users
-- get the same shell around their own customer_portal / sr_portal pages.

SET @db := DATABASE();

-- Users that exist before this file runs were full administrators, so they stay SuperAdmin with every branch.
SET @first_run := (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema=@db AND table_name='admin_user' AND column_name='access_level');

SET @q := IF(@first_run, "ALTER TABLE admin_user
    ADD COLUMN full_name VARCHAR(120) CHARACTER SET utf8mb4 NOT NULL DEFAULT '' AFTER user_name,
    ADD COLUMN mobile VARCHAR(30) NOT NULL DEFAULT '' AFTER email,
    ADD COLUMN access_level ENUM('SuperAdmin','Admin','Supervisor','Operator') NOT NULL DEFAULT 'Operator',
    ADD COLUMN user_type ENUM('staff','employee','customer') NOT NULL DEFAULT 'staff',
    ADD COLUMN linked_id BIGINT UNSIGNED NULL COMMENT 'employee: sales_representatives.sr_id, customer: sales_customers.customer_id',
    ADD COLUMN department VARCHAR(80) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
    ADD COLUMN date_of_birth DATE NULL,
    ADD COLUMN full_time TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0: sign-in only between start_time and end_time',
    ADD COLUMN start_time TIME NULL,
    ADD COLUMN end_time TIME NULL,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN all_branches TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN default_branch_id INT UNSIGNED NULL,
    ADD COLUMN current_branch_id INT UNSIGNED NULL COMMENT 'Branch picked at sign-in or in the header',
    ADD KEY ix_admin_user_link (user_type, linked_id)", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF(@first_run, "UPDATE admin_user SET access_level='SuperAdmin', all_branches=1", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Sub-branches: a branch may sit under another; access to a branch includes the branches under it.
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='branches' AND column_name='parent_branch_id') = 0,
    'ALTER TABLE branches ADD COLUMN parent_branch_id INT UNSIGNED NULL AFTER branch_name, ADD KEY ix_branches_parent (parent_branch_id), ADD CONSTRAINT fk_branch_parent FOREIGN KEY (parent_branch_id) REFERENCES branches (branch_id) ON UPDATE CASCADE ON DELETE SET NULL',
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Branches a user may work in (ignored when admin_user.all_branches = 1).
CREATE TABLE IF NOT EXISTS admin_user_branches (
    user_id INT NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, branch_id),
    CONSTRAINT fk_user_branch_user FOREIGN KEY (user_id) REFERENCES admin_user (user_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_user_branch_branch FOREIGN KEY (branch_id) REFERENCES branches (branch_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per user, form and branch. branch_id 0 = all branches; a row for a branch replaces the all-branches row there.
-- form_key is the menu URL of the form in vsc/menu_catalog.php, e.g. sales/sales_invoice/sales_invoice.php.
CREATE TABLE IF NOT EXISTS admin_user_permissions (
    user_id INT NOT NULL,
    branch_id INT UNSIGNED NOT NULL DEFAULT 0,
    form_key VARCHAR(160) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_add TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_price TINYINT(1) NOT NULL DEFAULT 0,
    can_additional TINYINT(1) NOT NULL DEFAULT 0,
    can_serial_list TINYINT(1) NOT NULL DEFAULT 0,
    can_extra_charges TINYINT(1) NOT NULL DEFAULT 0,
    can_vat TINYINT(1) NOT NULL DEFAULT 0,
    can_ait TINYINT(1) NOT NULL DEFAULT 0,
    can_discount TINYINT(1) NOT NULL DEFAULT 0,
    can_preview TINYINT(1) NOT NULL DEFAULT 0,
    can_print TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_after_app TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_back_date TINYINT(1) NOT NULL DEFAULT 0,
    can_advance_date TINYINT(1) NOT NULL DEFAULT 0,
    can_show_cost TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Actual (purchase) price and cost columns',
    can_show_price TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Sales price columns in reports',
    can_print_before_app TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_after_print TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, branch_id, form_key),
    CONSTRAINT fk_user_perm_user FOREIGN KEY (user_id) REFERENCES admin_user (user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rights of a role, in every branch the user can open. A user's own row in admin_user_permissions replaces the role's
-- row for that form, so one person can be given more or less than their role.
CREATE TABLE IF NOT EXISTS admin_role_permissions (
    user_role_id INT NOT NULL,
    form_key VARCHAR(160) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_add TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_price TINYINT(1) NOT NULL DEFAULT 0,
    can_additional TINYINT(1) NOT NULL DEFAULT 0,
    can_serial_list TINYINT(1) NOT NULL DEFAULT 0,
    can_extra_charges TINYINT(1) NOT NULL DEFAULT 0,
    can_vat TINYINT(1) NOT NULL DEFAULT 0,
    can_ait TINYINT(1) NOT NULL DEFAULT 0,
    can_discount TINYINT(1) NOT NULL DEFAULT 0,
    can_preview TINYINT(1) NOT NULL DEFAULT 0,
    can_print TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_after_app TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_back_date TINYINT(1) NOT NULL DEFAULT 0,
    can_advance_date TINYINT(1) NOT NULL DEFAULT 0,
    can_show_cost TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Actual (purchase) price and cost columns',
    can_show_price TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Sales price columns in reports',
    can_print_before_app TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_after_print TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_role_id, form_key),
    CONSTRAINT fk_role_perm_role FOREIGN KEY (user_role_id) REFERENCES admin_role (user_role_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- First print of a document, for Edit After Print.
CREATE TABLE IF NOT EXISTS admin_doc_prints (
    doc_table VARCHAR(40) NOT NULL,
    doc_id BIGINT UNSIGNED NOT NULL,
    printed_by INT NULL,
    printed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (doc_table, doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- admin_role is still joined by the older pages. New users share this role: it opens those pages' own checks, so
-- admin_user_permissions alone decides what they can reach.
INSERT INTO admin_role (role_name, description, addby, user_config, user, role)
SELECT 'ERP User', 'Rights come from User Access Control', 'schema', '1,1,1,1', '1,1,1,1', '1,1,1,1' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admin_role WHERE role_name='ERP User');


-- User Wise Store Fixing (vsc/user/user_store_fixing.php): which stores a user may work with. One row per rule.
-- branch_id 0 = all branches, form_key '' = all modules, store_name '' = all stores. A user with no rows is not limited;
-- once a user has a row, only what the rows leave open can be used. SuperAdmin is never limited.
CREATE TABLE IF NOT EXISTS admin_user_stores (
    rule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    branch_id INT UNSIGNED NOT NULL DEFAULT 0,
    form_key VARCHAR(160) NOT NULL DEFAULT '',
    store_name VARCHAR(140) NOT NULL DEFAULT '',
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rule_id),
    UNIQUE KEY uq_user_store_rule (user_id, branch_id, form_key, store_name),
    CONSTRAINT fk_user_store_user FOREIGN KEY (user_id) REFERENCES admin_user (user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Wise Ledger Fixing (vsc/user/user_ledger_fixing.php): which ledgers a user may work with. One row per rule.
-- branch_id 0 = all branches. scope 'all' opens every ledger (ref_id 0); 'trader_group' opens the parties of a
-- trader_groups row; 'account_group' opens every acc_accounts row under an acc_groups row and its sub groups; 'ledger'
-- opens one acc_accounts row with its parties. A user with no rows is not limited; SuperAdmin is never limited.
CREATE TABLE IF NOT EXISTS admin_user_ledgers (
    rule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    branch_id INT UNSIGNED NOT NULL DEFAULT 0,
    scope ENUM('all','trader_group','account_group','ledger') NOT NULL,
    ref_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rule_id),
    UNIQUE KEY uq_user_ledger_rule (user_id, branch_id, scope, ref_id),
    CONSTRAINT fk_user_ledger_user FOREIGN KEY (user_id) REFERENCES admin_user (user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications (addon/inc/notify.php). Office ones go to everyone who may view form_key; staff ones to one sr_id, or to all
-- staff when sr_id is NULL. link is from the project root. Who has read what is kept per reader in app_notification_reads.
CREATE TABLE IF NOT EXISTS app_notifications (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    audience ENUM('admin','staff') NOT NULL,
    form_key VARCHAR(160) NOT NULL DEFAULT '',
    sr_id BIGINT UNSIGNED NULL,
    title VARCHAR(140) NOT NULL,
    body VARCHAR(500) NOT NULL DEFAULT '',
    link VARCHAR(190) NOT NULL DEFAULT '',
    sent_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY ix_app_notifications_admin (audience, notification_id),
    KEY ix_app_notifications_staff (audience, sr_id, notification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_notification_reads (
    notification_id BIGINT UNSIGNED NOT NULL,
    reader_type ENUM('user','sr') NOT NULL,
    reader_id BIGINT UNSIGNED NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id, reader_type, reader_id),
    CONSTRAINT fk_app_notification_read FOREIGN KEY (notification_id) REFERENCES app_notifications (notification_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications to one office user (user_id) and to customers (customer_id; audience 'customer' with NULL customer_id is everyone).
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='app_notifications' AND column_name='customer_id') = 0,
    "ALTER TABLE app_notifications MODIFY audience ENUM('admin','staff','customer') NOT NULL, ADD COLUMN user_id INT NULL AFTER sr_id, ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER user_id, ADD KEY ix_app_notifications_customer (audience, customer_id, notification_id)", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
ALTER TABLE app_notification_reads MODIFY reader_type ENUM('user','sr','customer') NOT NULL;

-- One Users, Roles & Permissions screen. The old View Billing User form is gone, so rights on it move to the Users tab.
-- Rows are read through a derived table: ON DUPLICATE KEY cannot otherwise tell the source row from the existing one.
INSERT INTO admin_user_permissions (user_id, branch_id, form_key, can_view, can_add, can_edit, can_delete)
    SELECT * FROM (SELECT user_id, branch_id, 'user/registration.php' AS form_key, can_view, can_add, can_edit, can_delete FROM admin_user_permissions WHERE form_key='user/view_users.php') AS old_right
    ON DUPLICATE KEY UPDATE can_view=GREATEST(admin_user_permissions.can_view, old_right.can_view), can_add=GREATEST(admin_user_permissions.can_add, old_right.can_add),
        can_edit=GREATEST(admin_user_permissions.can_edit, old_right.can_edit), can_delete=GREATEST(admin_user_permissions.can_delete, old_right.can_delete);
DELETE FROM admin_user_permissions WHERE form_key='user/view_users.php';
INSERT INTO admin_role_permissions (user_role_id, form_key, can_view, can_add, can_edit, can_delete)
    SELECT * FROM (SELECT user_role_id, 'user/registration.php' AS form_key, can_view, can_add, can_edit, can_delete FROM admin_role_permissions WHERE form_key='user/view_users.php') AS old_right
    ON DUPLICATE KEY UPDATE can_view=GREATEST(admin_role_permissions.can_view, old_right.can_view), can_add=GREATEST(admin_role_permissions.can_add, old_right.can_add),
        can_edit=GREATEST(admin_role_permissions.can_edit, old_right.can_edit), can_delete=GREATEST(admin_role_permissions.can_delete, old_right.can_delete);
DELETE FROM admin_role_permissions WHERE form_key='user/view_users.php';

-- Service licence from Billing CRM, see addon/inc/licence.php. No row means this install is not connected.
CREATE TABLE IF NOT EXISTS licence_state (
  id TINYINT NOT NULL PRIMARY KEY,
  crm_url VARCHAR(255) NOT NULL,
  service_code INT NOT NULL,
  service_key CHAR(64) NOT NULL,
  public_key TEXT NOT NULL,
  payload MEDIUMTEXT NOT NULL,
  signature TEXT NOT NULL,
  checked_at INT NOT NULL DEFAULT 0,
  attempted_at INT NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- SECTION: HR
-- From billing/hr/schema.sql
-- HR: onboarding, employee profiles, attendance, leave and salary for every SR, staff member and employee.
-- MySQL 8.0+. Safe to run again. Run after vsc/trader/schema.sql (sales_representatives), vsc/inventory/schema.sql
-- (branches), vsc/sales_person/schema.sql and sr_portal/schema.sql.
--
-- hr_employees is the one list of people. sr_id links a person to sales_representatives: that row gives them an SR / Staff
-- Portal login. Someone who does not sell gets a row with staff_only=1, which keeps them out of the Sales Person lists.

SET @db := DATABASE();

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_representatives' AND column_name='staff_only') = 0,
    "ALTER TABLE sales_representatives ADD COLUMN staff_only TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1: office staff with a portal login, not a sales person'", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS hr_settings (
    setting_key VARCHAR(40) NOT NULL,
    setting_value VARCHAR(190) NOT NULL DEFAULT '',
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- weekly_off: PHP weekday numbers, 0 Sunday to 6 Saturday, comma separated. late_after: check-in later than this is Late.
INSERT IGNORE INTO hr_settings (setting_key, setting_value) VALUES ('weekly_off', '5'), ('late_after', '09:30');

CREATE TABLE IF NOT EXISTS hr_departments (
    department_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (department_id),
    UNIQUE KEY uq_hr_departments_name (department_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_designations (
    designation_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    designation_name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (designation_id),
    UNIQUE KEY uq_hr_designations_name (designation_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_holidays (
    holiday_date DATE NOT NULL,
    holiday_name VARCHAR(120) NOT NULL,
    PRIMARY KEY (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_employees (
    employee_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_code VARCHAR(20) NOT NULL,
    full_name VARCHAR(140) NOT NULL,
    father_name VARCHAR(140) NOT NULL DEFAULT '',
    mother_name VARCHAR(140) NOT NULL DEFAULT '',
    date_of_birth DATE NULL,
    gender ENUM('','male','female','other') NOT NULL DEFAULT '',
    blood_group VARCHAR(5) NOT NULL DEFAULT '',
    nid VARCHAR(40) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL DEFAULT '',
    present_address VARCHAR(255) NOT NULL DEFAULT '',
    permanent_address VARCHAR(255) NOT NULL DEFAULT '',
    emergency_name VARCHAR(140) NOT NULL DEFAULT '',
    emergency_mobile VARCHAR(40) NOT NULL DEFAULT '',
    department_id INT UNSIGNED NULL,
    designation_id INT UNSIGNED NULL,
    branch_id INT UNSIGNED NULL,
    reports_to BIGINT UNSIGNED NULL,
    employment_type ENUM('permanent','probation','contract','intern') NOT NULL DEFAULT 'permanent',
    join_date DATE NOT NULL,
    confirm_date DATE NULL,
    exit_date DATE NULL,
    status ENUM('onboarding','active','resigned','terminated') NOT NULL DEFAULT 'onboarding',
    bank_name VARCHAR(120) NOT NULL DEFAULT '',
    bank_account VARCHAR(60) NOT NULL DEFAULT '',
    sr_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id),
    UNIQUE KEY uq_hr_employees_code (employee_code),
    UNIQUE KEY uq_hr_employees_sr (sr_id),
    KEY ix_hr_employees_name (full_name),
    KEY ix_hr_employees_status (status),
    CONSTRAINT fk_hr_emp_department FOREIGN KEY (department_id) REFERENCES hr_departments (department_id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_hr_emp_designation FOREIGN KEY (designation_id) REFERENCES hr_designations (designation_id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_hr_emp_manager FOREIGN KEY (reports_to) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_hr_emp_sr FOREIGN KEY (sr_id) REFERENCES sales_representatives (sr_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Onboarding checklist of a person.
CREATE TABLE IF NOT EXISTS hr_onboarding_tasks (
    employee_id BIGINT UNSIGNED NOT NULL,
    task_key VARCHAR(30) NOT NULL,
    done_by BIGINT UNSIGNED NULL,
    done_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id, task_key),
    CONSTRAINT fk_hr_task_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Salary of a person from a date. The latest row on or before a month's last day is that month's salary, so raises keep history.
CREATE TABLE IF NOT EXISTS hr_salaries (
    salary_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    basic DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    house_rent DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    medical DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    conveyance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    other_allowance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    provident_fund DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    other_deduction DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(190) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (salary_id),
    UNIQUE KEY uq_hr_salaries (employee_id, effective_from),
    CONSTRAINT fk_hr_salary_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_leave_types (
    leave_type_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    leave_name VARCHAR(80) NOT NULL,
    default_days DECIMAL(6,1) NOT NULL DEFAULT 0.0 COMMENT 'Days per year, suggested on Leave Assign',
    is_paid TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0: no balance needed, and the days are taken off the salary',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (leave_type_id),
    UNIQUE KEY uq_hr_leave_types_name (leave_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO hr_leave_types (leave_name, default_days, is_paid) VALUES ('Casual Leave', 10, 1), ('Sick Leave', 14, 1), ('Earned Leave', 15, 1), ('Unpaid Leave', 0, 0);

-- Days of a leave type given to a person for a year. Days used come from the approved applications.
CREATE TABLE IF NOT EXISTS hr_leave_balances (
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    leave_year SMALLINT UNSIGNED NOT NULL,
    allocated DECIMAL(6,1) NOT NULL DEFAULT 0.0,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id, leave_type_id, leave_year),
    CONSTRAINT fk_hr_bal_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_hr_bal_type FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types (leave_type_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_leave_applications (
    application_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    half_day TINYINT(1) NOT NULL DEFAULT 0,
    days DECIMAL(6,1) NOT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    applied_via ENUM('portal','office') NOT NULL DEFAULT 'portal',
    decided_by BIGINT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decision_note VARCHAR(190) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (application_id),
    KEY ix_hr_leave_emp (employee_id, from_date),
    KEY ix_hr_leave_status (status, from_date),
    CONSTRAINT fk_hr_leave_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_hr_leave_type FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types (leave_type_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per person per day. application_id marks a day written by an approved leave; only cancelling the leave changes it.
CREATE TABLE IF NOT EXISTS hr_attendance (
    attendance_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present','late','half_day','absent','leave','half_leave') NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    unpaid_days DECIMAL(3,1) NOT NULL DEFAULT 0.0 COMMENT 'Part of the day taken off the salary',
    application_id BIGINT UNSIGNED NULL,
    source ENUM('office','portal','leave') NOT NULL DEFAULT 'office',
    note VARCHAR(190) NOT NULL DEFAULT '',
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (attendance_id),
    UNIQUE KEY uq_hr_attendance (employee_id, attendance_date),
    KEY ix_hr_attendance_date (attendance_date),
    KEY ix_hr_attendance_leave (application_id),
    CONSTRAINT fk_hr_att_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_hr_att_leave FOREIGN KEY (application_id) REFERENCES hr_leave_applications (application_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One payslip per person per month (period_month is the first day). Draft is refreshed by Generate; a person sees Approved and Paid only.
CREATE TABLE IF NOT EXISTS hr_payslips (
    payslip_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    period_month DATE NOT NULL,
    basic DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    house_rent DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    medical DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    conveyance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    other_allowance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    bonus DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    gross DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Monthly pay before bonus',
    tax DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    provident_fund DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    other_deduction DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    advance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    month_days TINYINT UNSIGNED NOT NULL DEFAULT 30,
    absent_days DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    unpaid_leave_days DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    not_employed_days TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Before joining or after leaving',
    absence_deduction DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    net_pay DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft',
    paid_on DATE NULL,
    payment_method ENUM('cash','bank','mobile','other') NOT NULL DEFAULT 'bank',
    reference VARCHAR(120) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (payslip_id),
    UNIQUE KEY uq_hr_payslips (employee_id, period_month),
    KEY ix_hr_payslips_month (period_month, status),
    CONSTRAINT fk_hr_payslip_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Full employee record: more profile fields, then one table per kind of record (see HR_RECORDS in addon/inc/hr.php).
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='hr_employees' AND column_name='marital_status') = 0,
    "ALTER TABLE hr_employees
        ADD COLUMN marital_status ENUM('','single','married','other') NOT NULL DEFAULT '' AFTER blood_group,
        ADD COLUMN spouse_name VARCHAR(140) NOT NULL DEFAULT '' AFTER marital_status,
        ADD COLUMN religion VARCHAR(40) NOT NULL DEFAULT '' AFTER spouse_name,
        ADD COLUMN nationality VARCHAR(60) NOT NULL DEFAULT '' AFTER religion,
        ADD COLUMN passport_no VARCHAR(40) NOT NULL DEFAULT '' AFTER nid,
        ADD COLUMN driving_license VARCHAR(40) NOT NULL DEFAULT '' AFTER passport_no,
        ADD COLUMN tin VARCHAR(40) NOT NULL DEFAULT '' AFTER driving_license,
        ADD COLUMN skills VARCHAR(255) NOT NULL DEFAULT '' AFTER emergency_mobile,
        ADD COLUMN languages VARCHAR(190) NOT NULL DEFAULT '' AFTER skills", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS hr_education (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    level VARCHAR(20) NOT NULL,
    degree VARCHAR(120) NOT NULL,
    institution VARCHAR(190) NOT NULL,
    board_university VARCHAR(140) NOT NULL DEFAULT '',
    major VARCHAR(120) NOT NULL DEFAULT '',
    passing_year SMALLINT UNSIGNED NULL,
    result VARCHAR(40) NOT NULL DEFAULT '',
    PRIMARY KEY (record_id),
    KEY ix_hr_education_emp (employee_id),
    CONSTRAINT fk_hr_education_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobs before this one. A blank to_date means they were still working there when they applied.
CREATE TABLE IF NOT EXISTS hr_experience (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    company VARCHAR(190) NOT NULL,
    job_title VARCHAR(120) NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NULL,
    last_salary DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    responsibilities VARCHAR(500) NOT NULL DEFAULT '',
    leaving_reason VARCHAR(190) NOT NULL DEFAULT '',
    contact VARCHAR(140) NOT NULL DEFAULT '',
    PRIMARY KEY (record_id),
    KEY ix_hr_experience_emp (employee_id),
    CONSTRAINT fk_hr_experience_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_references (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    reference_name VARCHAR(140) NOT NULL,
    relation VARCHAR(80) NOT NULL DEFAULT '',
    organization VARCHAR(190) NOT NULL DEFAULT '',
    designation VARCHAR(120) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'The office has spoken to this person',
    PRIMARY KEY (record_id),
    KEY ix_hr_references_emp (employee_id),
    CONSTRAINT fk_hr_references_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_training (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    institute VARCHAR(190) NOT NULL DEFAULT '',
    from_date DATE NULL,
    to_date DATE NULL,
    certificate_no VARCHAR(80) NOT NULL DEFAULT '',
    PRIMARY KEY (record_id),
    KEY ix_hr_training_emp (employee_id),
    CONSTRAINT fk_hr_training_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Family members and nominees. A nominee's share of what is payable to them, in percent; a person's shares cannot pass 100.
CREATE TABLE IF NOT EXISTS hr_family (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    member_name VARCHAR(140) NOT NULL,
    relation VARCHAR(40) NOT NULL,
    date_of_birth DATE NULL,
    occupation VARCHAR(120) NOT NULL DEFAULT '',
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    nominee_share DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (record_id),
    KEY ix_hr_family_emp (employee_id),
    CONSTRAINT fk_hr_family_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scanned papers. Files are in vsc/hr/uploads under a random name and are only served after login.
CREATE TABLE IF NOT EXISTS hr_documents (
    document_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL DEFAULT '',
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (document_id),
    KEY ix_hr_documents_emp (employee_id),
    CONSTRAINT fk_hr_documents_emp FOREIGN KEY (employee_id) REFERENCES hr_employees (employee_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Salary tax and disbursement. A person's photo is their latest hr_documents row of type Photo, so it needs no table.
-- Bands are worked from the lowest sort_order up: band_amount of yearly income at rate percent; band_amount 0 means all the rest.
CREATE TABLE IF NOT EXISTS hr_tax_slabs (
    slab_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    band_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (slab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='hr_salaries' AND column_name='tax_mode') = 0,
    "ALTER TABLE hr_salaries ADD COLUMN tax_mode ENUM('manual','slab') NOT NULL DEFAULT 'manual' AFTER other_allowance", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- pay_account_id: the cash or bank account paid from. voucher_id: the journal in Accounts, cleared if the payment is undone.
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='hr_payslips' AND column_name='voucher_id') = 0,
    "ALTER TABLE hr_payslips ADD COLUMN pay_account_id BIGINT UNSIGNED NULL AFTER reference, ADD COLUMN voucher_id BIGINT UNSIGNED NULL AFTER pay_account_id, ADD KEY ix_hr_payslips_voucher (voucher_id)", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
-- SECTION: Audit
-- From billing/audit/schema.sql
-- Audit trail: logins, data changes, activity and attacks, for every kind of user. Safe to run again.
-- Written by addon/inc/audit.php. The pages only read these tables, apart from Audit Settings and the blocked list.
CREATE TABLE IF NOT EXISTS audit_log (
  audit_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  category VARCHAR(12) NOT NULL,
  action VARCHAR(40) NOT NULL,
  severity VARCHAR(8) NOT NULL DEFAULT 'info',
  status VARCHAR(8) NOT NULL DEFAULT 'ok',
  actor_type VARCHAR(12) NOT NULL DEFAULT 'anonymous',
  actor_id INT NOT NULL DEFAULT 0,
  actor_name VARCHAR(120) NOT NULL DEFAULT '',
  branch_id INT NOT NULL DEFAULT 0,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  country VARCHAR(3) NOT NULL DEFAULT '',
  browser VARCHAR(30) NOT NULL DEFAULT '',
  os VARCHAR(30) NOT NULL DEFAULT '',
  device VARCHAR(10) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  method VARCHAR(8) NOT NULL DEFAULT '',
  page VARCHAR(190) NOT NULL DEFAULT '',
  target_table VARCHAR(64) NOT NULL DEFAULT '',
  target_id VARCHAR(40) NOT NULL DEFAULT '',
  summary VARCHAR(255) NOT NULL DEFAULT '',
  detail MEDIUMTEXT NULL,
  session_ref CHAR(12) NOT NULL DEFAULT '',
  request_id CHAR(16) NOT NULL DEFAULT '',
  KEY idx_created (created_at),
  KEY idx_category (category, created_at),
  KEY idx_severity (severity, created_at),
  KEY idx_ip (ip, created_at),
  KEY idx_actor (actor_type, actor_id, created_at),
  KEY idx_target (target_table, target_id),
  KEY idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Country of each address, kept so an address is looked up only once.
CREATE TABLE IF NOT EXISTS audit_ip_country (
  ip VARCHAR(45) PRIMARY KEY,
  country VARCHAR(3) NOT NULL DEFAULT '',
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Addresses refused before any page runs. An empty blocked_until means until someone unblocks it.
CREATE TABLE IF NOT EXISTS audit_blocked_ip (
  ip VARCHAR(45) PRIMARY KEY,
  reason VARCHAR(190) NOT NULL DEFAULT '',
  blocked_until DATETIME NULL,
  created_by VARCHAR(120) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_settings (
  setting_key VARCHAR(40) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Counters behind the sign-in limits and automatic blocking.
CREATE TABLE IF NOT EXISTS security_throttle (
  bucket CHAR(64) PRIMARY KEY,
  hits INT NOT NULL DEFAULT 0,
  window_end DATETIME NOT NULL
) ENGINE=InnoDB;
