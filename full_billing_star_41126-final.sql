-- MySQL dump 10.13  Distrib 8.4.13, for macos15.8 (x86_64)
--
-- Host: localhost    Database: billing_star
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
  `account_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` enum('general','cash','bank','receivable','payable','loan') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `has_sub_ledger` tinyint(1) NOT NULL DEFAULT '0',
  `bank_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bank_account_no` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `opening_side` enum('Dr','Cr') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Dr',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uq_acc_accounts_code` (`account_code`),
  UNIQUE KEY `uq_acc_accounts_name` (`account_name`),
  KEY `ix_acc_accounts_group` (`group_id`),
  CONSTRAINT `fk_acc_account_group` FOREIGN KEY (`group_id`) REFERENCES `acc_groups` (`group_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_accounts`
--

LOCK TABLES `acc_accounts` WRITE;
/*!40000 ALTER TABLE `acc_accounts` DISABLE KEYS */;
INSERT INTO `acc_accounts` VALUES (1,3,'1001','Cash In Hand','cash',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(2,3,'1002','Main Bank Account','bank',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(3,4,'1101','Sundry Debtors','receivable',1,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(4,5,'1201','Inventory','general',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(5,9,'2001','Sundry Creditors','payable',1,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(6,10,'2101','CC Loan','loan',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(7,11,'3001','Capital','general',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(8,13,'4001','Sales','general',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(9,14,'4101','Other Income','general',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(10,16,'5001','Purchase','general',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(11,17,'5101','Office Expenses','general',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(12,17,'5102','Salary','general',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(13,18,'5201','CC Loan Interest','general',0,'','',0.00,'Dr',1,'2026-10-29 16:23:46'),(15,2,'123','Cash in leg','cash',0,'brac bank','12345678',5000.00,'Dr',1,'2026-10-29 16:34:38');
/*!40000 ALTER TABLE `acc_accounts` ENABLE KEYS */;
UNLOCK TABLES;

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

LOCK TABLES `acc_cc_interest_runs` WRITE;
/*!40000 ALTER TABLE `acc_cc_interest_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `acc_cc_interest_runs` ENABLE KEYS */;
UNLOCK TABLES;

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
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`rate_id`),
  UNIQUE KEY `uq_acc_cc_rate` (`account_id`,`effective_from`),
  CONSTRAINT `fk_acc_cc_rate_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_cc_loan_rates`
--

LOCK TABLES `acc_cc_loan_rates` WRITE;
/*!40000 ALTER TABLE `acc_cc_loan_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `acc_cc_loan_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `acc_cheque_books`
--

DROP TABLE IF EXISTS `acc_cheque_books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_cheque_books` (
  `book_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `book_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `leaf_prefix` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `leaf_from` bigint unsigned NOT NULL,
  `leaf_to` bigint unsigned NOT NULL,
  `leaf_digits` tinyint unsigned NOT NULL DEFAULT '0',
  `issue_date` date NOT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `approval_status` enum('pending','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`book_id`),
  UNIQUE KEY `uq_acc_cheque_books` (`account_id`,`book_no`),
  CONSTRAINT `fk_acc_cheque_book_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_cheque_books`
--

LOCK TABLES `acc_cheque_books` WRITE;
/*!40000 ALTER TABLE `acc_cheque_books` DISABLE KEYS */;
INSERT INTO `acc_cheque_books` VALUES (1,2,'CB-1001','A',100001,100025,6,'2026-08-01','Main bank book','approved',NULL,NULL,10,'2026-10-30 18:59:23');
/*!40000 ALTER TABLE `acc_cheque_books` ENABLE KEYS */;
UNLOCK TABLES;

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
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `acc_cheque_events` WRITE;
/*!40000 ALTER TABLE `acc_cheque_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `acc_cheque_events` ENABLE KEYS */;
UNLOCK TABLES;

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
  `leaf_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('unused','used','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unused',
  `voucher_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`leaf_id`),
  UNIQUE KEY `uq_acc_cheque_leaf` (`account_id`,`leaf_no`),
  KEY `ix_acc_cheque_leaves_book` (`book_id`),
  CONSTRAINT `fk_acc_cheque_leaf_book` FOREIGN KEY (`book_id`) REFERENCES `acc_cheque_books` (`book_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_cheque_leaves`
--

LOCK TABLES `acc_cheque_leaves` WRITE;
/*!40000 ALTER TABLE `acc_cheque_leaves` DISABLE KEYS */;
INSERT INTO `acc_cheque_leaves` VALUES (1,1,2,'A100001','used',22),(2,1,2,'A100002','unused',NULL),(3,1,2,'A100003','unused',NULL),(4,1,2,'A100004','unused',NULL),(5,1,2,'A100005','unused',NULL),(6,1,2,'A100006','unused',NULL),(7,1,2,'A100007','unused',NULL),(8,1,2,'A100008','unused',NULL),(9,1,2,'A100009','unused',NULL),(10,1,2,'A100010','unused',NULL),(11,1,2,'A100011','unused',NULL),(12,1,2,'A100012','unused',NULL),(13,1,2,'A100013','unused',NULL),(14,1,2,'A100014','unused',NULL),(15,1,2,'A100015','unused',NULL),(16,1,2,'A100016','unused',NULL),(17,1,2,'A100017','unused',NULL),(18,1,2,'A100018','unused',NULL),(19,1,2,'A100019','unused',NULL),(20,1,2,'A100020','unused',NULL),(21,1,2,'A100021','unused',NULL),(22,1,2,'A100022','unused',NULL),(23,1,2,'A100023','unused',NULL),(24,1,2,'A100024','unused',NULL),(25,1,2,'A100025','unused',NULL);
/*!40000 ALTER TABLE `acc_cheque_leaves` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `acc_cheques`
--

DROP TABLE IF EXISTS `acc_cheques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_cheques` (
  `cheque_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` enum('received','issued') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_id` bigint unsigned NOT NULL,
  `line_id` bigint unsigned DEFAULT NULL,
  `bank_account_id` bigint unsigned NOT NULL,
  `party_account_id` bigint unsigned NOT NULL,
  `party_sub_ledger_id` bigint unsigned DEFAULT NULL,
  `party_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cheque_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cheque_date` date DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('pending','honoured','dishonoured','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `status_date` date DEFAULT NULL,
  `reversal_voucher_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cheque_id`),
  KEY `ix_acc_cheques_no` (`cheque_no`),
  KEY `ix_acc_cheques_status` (`status`,`cheque_date`),
  KEY `ix_acc_cheques_voucher` (`voucher_id`),
  CONSTRAINT `fk_acc_cheque_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `acc_vouchers` (`voucher_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_cheques`
--

LOCK TABLES `acc_cheques` WRITE;
/*!40000 ALTER TABLE `acc_cheques` DISABLE KEYS */;
INSERT INTO `acc_cheques` VALUES (1,'issued',22,43,2,5,1,'Dhaka Network House','A100001','2026-09-12',120000.00,'pending',NULL,NULL,'2026-10-30 19:05:27');
/*!40000 ALTER TABLE `acc_cheques` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `acc_groups`
--

DROP TABLE IF EXISTS `acc_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_groups` (
  `group_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `group_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `group_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nature` enum('asset','liability','equity','income','expense') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `uq_acc_groups_name` (`parent_id`,`group_name`),
  KEY `ix_acc_groups_parent` (`parent_id`),
  CONSTRAINT `fk_acc_group_parent` FOREIGN KEY (`parent_id`) REFERENCES `acc_groups` (`group_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_groups`
--

LOCK TABLES `acc_groups` WRITE;
/*!40000 ALTER TABLE `acc_groups` DISABLE KEYS */;
INSERT INTO `acc_groups` VALUES (1,NULL,'1','Assets','asset',1,1,'2026-10-29 16:23:46'),(2,1,'11','Current Assets','asset',1,1,'2026-10-29 16:23:46'),(3,2,'111','Cash & Bank','asset',1,1,'2026-10-29 16:23:46'),(4,2,'112','Accounts Receivable','asset',2,1,'2026-10-29 16:23:46'),(5,2,'113','Inventory','asset',3,1,'2026-10-29 16:23:46'),(6,1,'12','Fixed Assets','asset',2,1,'2026-10-29 16:23:46'),(7,NULL,'2','Liabilities','liability',2,1,'2026-10-29 16:23:46'),(8,7,'21','Current Liabilities','liability',1,1,'2026-10-29 16:23:46'),(9,8,'211','Accounts Payable','liability',1,1,'2026-10-29 16:23:46'),(10,7,'22','Loans','liability',2,1,'2026-10-29 16:23:46'),(11,NULL,'3','Equity','equity',3,1,'2026-10-29 16:23:46'),(12,NULL,'4','Income','income',4,1,'2026-10-29 16:23:46'),(13,12,'41','Sales Income','income',1,1,'2026-10-29 16:23:46'),(14,12,'42','General Income','income',2,1,'2026-10-29 16:23:46'),(15,NULL,'5','Expenses','expense',5,1,'2026-10-29 16:23:46'),(16,15,'51','Cost of Sales','expense',1,1,'2026-10-29 16:23:46'),(17,15,'52','General Expenses','expense',2,1,'2026-10-29 16:23:46'),(18,15,'53','Finance Cost','expense',3,1,'2026-10-29 16:23:46');
/*!40000 ALTER TABLE `acc_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `acc_money_receipt_categories`
--

DROP TABLE IF EXISTS `acc_money_receipt_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_money_receipt_categories` (
  `category_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` bigint unsigned DEFAULT NULL COMMENT 'Default account credited',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_acc_mrc_name` (`category_name`),
  KEY `fk_acc_mrc_account` (`account_id`),
  CONSTRAINT `fk_acc_mrc_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_money_receipt_categories`
--

LOCK TABLES `acc_money_receipt_categories` WRITE;
/*!40000 ALTER TABLE `acc_money_receipt_categories` DISABLE KEYS */;
INSERT INTO `acc_money_receipt_categories` VALUES (1,'Customer Collection',3,'',1),(2,'Other Income',9,'',1),(3,'Service Income',9,'',1);
/*!40000 ALTER TABLE `acc_money_receipt_categories` ENABLE KEYS */;
UNLOCK TABLES;

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
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `acc_overdue_permissions` WRITE;
/*!40000 ALTER TABLE `acc_overdue_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `acc_overdue_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `acc_sequences`
--

DROP TABLE IF EXISTS `acc_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_sequences` (
  `doc_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seq_year` smallint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`doc_code`,`seq_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_sequences`
--

LOCK TABLES `acc_sequences` WRITE;
/*!40000 ALTER TABLE `acc_sequences` DISABLE KEYS */;
INSERT INTO `acc_sequences` VALUES ('CON',2026,3),('CRV',2026,3),('DRV',2026,9),('JUR',2026,3);
/*!40000 ALTER TABLE `acc_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `acc_settings`
--

DROP TABLE IF EXISTS `acc_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_settings` (
  `setting_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_settings`
--

LOCK TABLES `acc_settings` WRITE;
/*!40000 ALTER TABLE `acc_settings` DISABLE KEYS */;
INSERT INTO `acc_settings` VALUES ('cash_account','1','2026-10-30 17:57:46'),('cc_day_basis','365','2026-10-30 17:57:46'),('cc_interest_account','13','2026-10-30 17:57:46'),('company_address','300 GK Palace, Rayer Bazar, DHanmondi','2026-10-30 18:05:48'),('company_email','nazmul@gmail.com','2026-10-30 18:04:52'),('company_hours','Sun to Friday 9:00AM to 8:00PM','2026-10-30 18:08:58'),('company_logo','addon/uploads/brand/logo_c17e03cd327e.jpg','2026-10-30 18:05:48'),('company_mobile','01615911873','2026-10-30 18:05:48'),('company_name','Billing Star Limited.','2026-10-30 18:06:21'),('company_phone','01711264147','2026-10-30 18:05:48'),('company_website','https://www.billingstar.com','2026-10-30 18:05:48'),('licence_host','localhost','2026-11-05 16:00:17'),('login_color','#bcc2bf','2026-10-31 08:33:13'),('login_show_notices','0','2026-10-30 18:04:52'),('login_show_portals','1','2026-10-30 18:04:52'),('login_title','Welcome to MyShop','2026-10-31 08:32:55'),('login_welcome','All kind of mobile computer shops available at .','2026-10-31 08:32:55'),('payable_account','5','2026-10-30 17:57:46'),('receivable_account','3','2026-10-30 17:57:46'),('require_posting','0','2026-10-30 17:57:46'),('voucher_prefix','DES','2026-10-30 17:57:46');
/*!40000 ALTER TABLE `acc_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `acc_sub_ledger_groups`
--

DROP TABLE IF EXISTS `acc_sub_ledger_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_sub_ledger_groups` (
  `slg_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `group_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`slg_id`),
  UNIQUE KEY `uq_acc_slg_name` (`account_id`,`group_name`),
  CONSTRAINT `fk_acc_slg_account` FOREIGN KEY (`account_id`) REFERENCES `acc_accounts` (`account_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_sub_ledger_groups`
--

LOCK TABLES `acc_sub_ledger_groups` WRITE;
/*!40000 ALTER TABLE `acc_sub_ledger_groups` DISABLE KEYS */;
INSERT INTO `acc_sub_ledger_groups` VALUES (1,3,'General Customers',1,'2026-10-29 16:23:46'),(2,5,'General Suppliers',1,'2026-10-29 16:23:46');
/*!40000 ALTER TABLE `acc_sub_ledger_groups` ENABLE KEYS */;
UNLOCK TABLES;

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
  `sub_ledger_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_ledger_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `opening_side` enum('Dr','Cr') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Dr',
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_sub_ledgers`
--

LOCK TABLES `acc_sub_ledgers` WRITE;
/*!40000 ALTER TABLE `acc_sub_ledgers` DISABLE KEYS */;
INSERT INTO `acc_sub_ledgers` VALUES (1,2,5,'SUP-001','Dhaka Network House','Motijheel, Dhaka','01711100101',0.00,'Cr',NULL,1,'2026-10-30 19:04:58'),(2,2,5,'SUP-002','IDB Tech Traders','IDB Bhaban, Agargaon','01711100102',0.00,'Cr',NULL,1,'2026-10-30 19:04:58'),(3,2,5,'SUP-003','Global Power Imports','Tejgaon, Dhaka','01711100103',0.00,'Cr',NULL,1,'2026-10-30 19:04:58'),(4,2,5,'SUP-004','Star Computer Distribution','Elephant Road, Dhaka','01711100104',0.00,'Cr',NULL,1,'2026-10-30 19:04:59'),(5,2,5,'SUP-005','PrintLine Bangladesh','Nawabpur, Dhaka','01711100105',0.00,'Cr',NULL,1,'2026-10-30 19:04:59'),(6,1,3,'CUS-001','Rahman Enterprise','Mirpur 10, Dhaka','01811200201',0.00,'Dr',NULL,1,'2026-10-30 19:04:59'),(7,1,3,'CUS-002','Karim Traders','Uttara Sector 7, Dhaka','01811200202',0.00,'Dr',NULL,1,'2026-10-30 19:04:59'),(8,1,3,'CUS-003','Bengal IT Solutions','Banani, Dhaka','01811200203',0.00,'Dr',NULL,1,'2026-10-30 19:04:59'),(9,1,3,'CUS-004','Sonar Bangla School','Gazipur Sadar','01811200204',0.00,'Dr',NULL,1,'2026-10-30 19:04:59'),(10,1,3,'CUS-005','City Pharma Ltd','Dhanmondi, Dhaka','01811200205',0.00,'Dr',NULL,1,'2026-10-30 19:04:59'),(11,1,3,'CUS-006','Hasan Computer Point','Comilla Town','01811200206',0.00,'Dr',NULL,1,'2026-10-30 19:04:59'),(12,1,3,'CUS-007','Amin & Sons','Chattogram','01811200207',0.00,'Dr',NULL,1,'2026-10-30 19:05:00'),(13,1,3,'CUS-008','Green Valley Hospital','Sylhet','01811200208',0.00,'Dr',NULL,1,'2026-10-30 19:05:00'),(14,1,3,'CUS-009','Noor Fashion House','Narayanganj','01811200209',0.00,'Dr',NULL,1,'2026-10-30 19:05:00'),(15,1,3,'CUS-010','Delta Garments Ltd','Savar, Dhaka','01811200210',0.00,'Dr',NULL,1,'2026-10-30 19:05:00');
/*!40000 ALTER TABLE `acc_sub_ledgers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `acc_voucher_attachments`
--

DROP TABLE IF EXISTS `acc_voucher_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_voucher_attachments` (
  `attachment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voucher_id` bigint unsigned NOT NULL,
  `original_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/octet-stream',
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

LOCK TABLES `acc_voucher_attachments` WRITE;
/*!40000 ALTER TABLE `acc_voucher_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `acc_voucher_attachments` ENABLE KEYS */;
UNLOCK TABLES;

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
  `line_narration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `line_no` smallint unsigned NOT NULL DEFAULT '1',
  `is_posted` tinyint(1) NOT NULL DEFAULT '0',
  `voucher_date` date DEFAULT NULL,
  `basis_date` date DEFAULT NULL,
  `branch_id` int unsigned DEFAULT NULL,
  `transaction_mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cheque_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cheque_date` date DEFAULT NULL,
  `invoice_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_voucher_lines`
--

LOCK TABLES `acc_voucher_lines` WRITE;
/*!40000 ALTER TABLE `acc_voucher_lines` DISABLE KEYS */;
INSERT INTO `acc_voucher_lines` VALUES (13,7,2,NULL,2500000.00,0.00,'Capital introduced',1,1,'2026-08-01','2026-08-01',1,'','',NULL,''),(14,7,7,NULL,0.00,2500000.00,'Capital introduced',2,1,'2026-08-01','2026-08-01',1,'','',NULL,''),(15,8,11,NULL,8500.00,0.00,'Stationery',1,1,'2026-09-30','2026-09-30',1,'','',NULL,''),(16,8,1,NULL,0.00,8500.00,'Stationery',2,1,'2026-09-30','2026-09-30',1,'','',NULL,''),(17,9,2,NULL,150000.00,0.00,'Bank deposit',1,1,'2026-08-31','2026-08-31',1,'','',NULL,''),(18,9,1,NULL,0.00,150000.00,'Bank deposit',2,1,'2026-08-31','2026-08-31',1,'','',NULL,''),(19,10,2,NULL,200000.00,0.00,'Bank deposit',1,1,'2026-09-30','2026-09-30',1,'','',NULL,''),(20,10,1,NULL,0.00,200000.00,'Bank deposit',2,1,'2026-09-30','2026-09-30',1,'','',NULL,''),(21,11,2,NULL,175000.00,0.00,'Bank deposit',1,1,'2026-10-28','2026-10-28',1,'','',NULL,''),(22,11,1,NULL,0.00,175000.00,'Bank deposit',2,1,'2026-10-28','2026-10-28',1,'','',NULL,''),(23,12,12,NULL,185000.00,0.00,'',1,1,'2026-08-31','2026-08-31',1,'Cash','',NULL,''),(24,12,1,NULL,0.00,185000.00,'',2,1,'2026-08-31','2026-08-31',1,'Cash','',NULL,''),(25,13,11,NULL,60000.00,0.00,'',1,1,'2026-08-31','2026-08-31',1,'Cash','',NULL,''),(26,13,1,NULL,0.00,60000.00,'',2,1,'2026-08-31','2026-08-31',1,'Cash','',NULL,''),(27,14,12,NULL,185000.00,0.00,'',1,1,'2026-09-30','2026-09-30',1,'Cash','',NULL,''),(28,14,1,NULL,0.00,185000.00,'',2,1,'2026-09-30','2026-09-30',1,'Cash','',NULL,''),(29,15,11,NULL,60000.00,0.00,'',1,1,'2026-09-30','2026-09-30',1,'Cash','',NULL,''),(30,15,1,NULL,0.00,60000.00,'',2,1,'2026-09-30','2026-09-30',1,'Cash','',NULL,''),(31,16,12,NULL,185000.00,0.00,'',1,1,'2026-10-30','2026-10-30',1,'Cash','',NULL,''),(32,16,1,NULL,0.00,185000.00,'',2,1,'2026-10-30','2026-10-30',1,'Cash','',NULL,''),(33,17,11,NULL,60000.00,0.00,'',1,1,'2026-10-30','2026-10-30',1,'Cash','',NULL,''),(34,17,1,NULL,0.00,60000.00,'',2,1,'2026-10-30','2026-10-30',1,'Cash','',NULL,''),(35,18,1,NULL,18000.00,0.00,'',1,1,'2026-09-05','2026-09-05',1,'Cash','',NULL,''),(36,18,9,NULL,0.00,18000.00,'',2,1,'2026-09-05','2026-09-05',1,'Cash','',NULL,''),(37,19,1,NULL,24000.00,0.00,'',1,1,'2026-10-11','2026-10-11',1,'Cash','',NULL,''),(38,19,9,NULL,0.00,24000.00,'',2,1,'2026-10-11','2026-10-11',1,'Cash','',NULL,''),(41,21,5,2,95000.00,0.00,'',1,1,'2026-10-29','2026-10-29',1,'','',NULL,''),(42,21,2,NULL,0.00,95000.00,'',2,1,'2026-10-29','2026-10-29',1,'','',NULL,''),(43,22,5,1,120000.00,0.00,'',1,1,'2026-09-12','2026-09-12',1,'Cheque','A100001','2026-09-12',''),(44,22,2,NULL,0.00,120000.00,'',2,1,'2026-09-12','2026-09-12',1,'Cheque','A100001','2026-09-12',''),(45,23,1,NULL,10000.00,0.00,'SAMPLE collection from customer',1,1,'2026-11-02','2026-11-02',1,'Cash','',NULL,''),(46,23,3,6,0.00,10000.00,'SAMPLE collection from customer',2,1,'2026-11-02','2026-11-02',1,'Cash','',NULL,''),(47,24,11,NULL,2500.00,0.00,'SAMPLE office expense',1,1,'2026-11-02','2026-11-02',1,'Cash','',NULL,''),(48,24,1,NULL,0.00,2500.00,'SAMPLE office expense',2,1,'2026-11-02','2026-11-02',1,'Cash','',NULL,''),(49,25,11,NULL,1200.00,0.00,'SAMPLE owner paid office expense',1,1,'2026-11-02','2026-11-02',1,'','',NULL,''),(50,25,7,NULL,0.00,1200.00,'SAMPLE owner paid office expense',2,1,'2026-11-02','2026-11-02',1,'','',NULL,'');
/*!40000 ALTER TABLE `acc_voucher_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `acc_vouchers`
--

DROP TABLE IF EXISTS `acc_vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acc_vouchers` (
  `voucher_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voucher_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_type` enum('debit','credit','journal','contra') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_date` date NOT NULL,
  `ref_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_person` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `transaction_mode` enum('Cash','Cheque','Bank Transfer','Mobile Banking','Adjustment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cash',
  `cheque_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cheque_date` date DEFAULT NULL,
  `receipt_category_id` bigint unsigned DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','posted','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `source` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acc_vouchers`
--

LOCK TABLES `acc_vouchers` WRITE;
/*!40000 ALTER TABLE `acc_vouchers` DISABLE KEYS */;
INSERT INTO `acc_vouchers` VALUES (7,'DESJUR2026000001','journal','2026-08-01','','Owner capital into bank','','Adjustment','',NULL,NULL,2500000.00,'posted','manual',10,10,'2026-10-31 01:02:11','2026-10-30 19:02:11','2026-10-30 19:04:00',1,NULL,0),(8,'DESJUR2026000002','journal','2026-09-30','','Office expense accrual','','Cash','',NULL,NULL,8500.00,'posted','manual',10,10,'2026-10-31 01:02:11','2026-10-30 19:02:11','2026-10-30 19:04:00',1,NULL,0),(9,'DESCON2026000001','contra','2026-08-31','','Cash deposit to bank','','Cash','',NULL,NULL,150000.00,'posted','manual',10,10,'2026-10-31 01:02:12','2026-10-30 19:02:12','2026-10-30 19:04:00',1,NULL,0),(10,'DESCON2026000002','contra','2026-09-30','','Cash deposit to bank','','Cash','',NULL,NULL,200000.00,'posted','manual',10,10,'2026-10-31 01:02:12','2026-10-30 19:02:12','2026-10-30 19:04:00',1,NULL,0),(11,'DESCON2026000003','contra','2026-10-28','','Cash deposit to bank','','Cash','',NULL,NULL,175000.00,'posted','manual',10,10,'2026-10-31 01:02:12','2026-10-30 19:02:12','2026-10-30 19:04:00',1,NULL,0),(12,'DESDRV2026000001','debit','2026-08-31','','August salary','','Cash','',NULL,NULL,185000.00,'posted','manual',10,10,'2026-10-31 01:02:12','2026-10-30 19:02:12','2026-10-30 19:04:00',1,NULL,0),(13,'DESDRV2026000002','debit','2026-08-31','','August office rent','','Cash','',NULL,NULL,60000.00,'posted','manual',10,10,'2026-10-31 01:02:13','2026-10-30 19:02:13','2026-10-30 19:04:00',1,NULL,0),(14,'DESDRV2026000003','debit','2026-09-30','','September salary','','Cash','',NULL,NULL,185000.00,'posted','manual',10,10,'2026-10-31 01:02:13','2026-10-30 19:02:13','2026-10-30 19:04:00',1,NULL,0),(15,'DESDRV2026000004','debit','2026-09-30','','September office rent','','Cash','',NULL,NULL,60000.00,'posted','manual',10,10,'2026-10-31 01:02:13','2026-10-30 19:02:13','2026-10-30 19:04:00',1,NULL,0),(16,'DESDRV2026000005','debit','2026-10-30','','October salary','','Cash','',NULL,NULL,185000.00,'posted','manual',10,10,'2026-10-31 01:02:14','2026-10-30 19:02:14','2026-10-30 19:04:00',1,NULL,0),(17,'DESDRV2026000006','debit','2026-10-30','','October office rent','','Cash','',NULL,NULL,60000.00,'posted','manual',10,10,'2026-10-31 01:02:14','2026-10-30 19:02:14','2026-10-30 19:04:00',1,NULL,0),(18,'DESCRV2026000001','credit','2026-09-05','','Service income','','Cash','',NULL,NULL,18000.00,'posted','manual',10,10,'2026-10-31 01:02:15','2026-10-30 19:02:15','2026-10-30 19:04:00',1,NULL,0),(19,'DESCRV2026000002','credit','2026-10-11','','Installation income','','Cash','',NULL,NULL,24000.00,'posted','manual',10,10,'2026-10-31 01:02:15','2026-10-30 19:02:15','2026-10-30 19:04:00',1,NULL,0),(21,'DESDRV2026000007','debit','2026-10-29','','Supplier payment by bank','','Cash','',NULL,NULL,95000.00,'posted','manual',10,10,'2026-10-31 01:05:00','2026-10-30 19:05:00','2026-10-30 19:05:27',1,NULL,0),(22,'DESDRV2026000008','debit','2026-09-12','','Supplier payment by cheque','','Cheque','',NULL,NULL,120000.00,'posted','manual',10,10,'2026-10-31 01:05:27','2026-10-30 19:05:27','2026-10-30 19:05:39',1,NULL,0),(23,'DESCRV2026000003','credit','2026-11-02','SMP-RV-1','SAMPLE collection from customer','','Cash','',NULL,NULL,10000.00,'posted','manual',63,63,'2026-11-02 22:05:58','2026-11-02 16:05:57','2026-11-02 16:05:58',1,NULL,0),(24,'DESDRV2026000009','debit','2026-11-02','SMP-PV-1','SAMPLE office expense','','Cash','',NULL,NULL,2500.00,'posted','manual',63,63,'2026-11-02 22:06:01','2026-11-02 16:06:01','2026-11-02 16:06:01',1,NULL,0),(25,'DESJUR2026000003','journal','2026-11-02','SMP-JV-1','SAMPLE owner paid office expense','','Adjustment','',NULL,NULL,1200.00,'posted','manual',63,63,'2026-11-02 22:07:17','2026-11-02 16:07:17','2026-11-02 16:07:17',1,NULL,0);
/*!40000 ALTER TABLE `acc_vouchers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_doc_prints`
--

DROP TABLE IF EXISTS `admin_doc_prints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_doc_prints` (
  `doc_table` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `printed_by` int DEFAULT NULL,
  `printed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_table`,`doc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_doc_prints`
--

LOCK TABLES `admin_doc_prints` WRITE;
/*!40000 ALTER TABLE `admin_doc_prints` DISABLE KEYS */;
INSERT INTO `admin_doc_prints` VALUES ('sales_invoices',123,48,'2026-10-30 19:30:40');
/*!40000 ALTER TABLE `admin_doc_prints` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_role`
--

DROP TABLE IF EXISTS `admin_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_role` (
  `user_role_id` int NOT NULL AUTO_INCREMENT,
  `user_config` varchar(20) DEFAULT NULL COMMENT 'adduser-roteuser-visitortracking-createconfig-databasebackup',
  `gw_client` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `pc2phone_client` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `callback_client` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `ivr_client` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `common_client` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `callshop_client` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `reseller1` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `reseller2` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `reseller3` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `reseller4` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `reseller_report` int DEFAULT '0',
  `npa_number` int DEFAULT '0',
  `reseller_payment` int DEFAULT '0',
  `gateway` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `gkregistrar` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `gk_client` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `dial_plan` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `tariff_to_dni` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `tariff_to_ani` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `recharge_pin` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `account_generation` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `monitoring_report` int DEFAULT '0',
  `client_payment` int DEFAULT '0',
  `active_call` varchar(20) DEFAULT NULL COMMENT 'view_delete-summary',
  `tariff` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete-export-import',
  `tariff_destination` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete-export-import',
  `user` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `role_name` varchar(50) DEFAULT NULL,
  `description` varchar(150) DEFAULT NULL,
  `addby` varchar(50) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL COMMENT 'view-edit-add-delete',
  `call_failcall` int DEFAULT '0',
  `id_reseller` int DEFAULT NULL,
  `reseller_level` int DEFAULT NULL,
  `customer_mgmt` varchar(30) NOT NULL DEFAULT '1,1,1,1,1,1',
  `sales_person_mgmt` varchar(30) NOT NULL DEFAULT '1,1,1,1,1,1',
  PRIMARY KEY (`user_role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_role`
--

LOCK TABLES `admin_role` WRITE;
/*!40000 ALTER TABLE `admin_role` DISABLE KEYS */;
INSERT INTO `admin_role` VALUES (1,'1,1,1,1','1,1,1,1,1','1,1,1,1,1','1,1,1,1,1','1,1,1,1,1','1,1,1,1,1','1,1,1,1,1','1,1,1,1,1','1,1,1,1,1','1,1,1,1,1','1,1,1,1,1',1,1,1,'1,1,1,1','1,1,1,1','1,1,1,1,1','1,1,1,1','1,1,1,1','1,1,1,1','1,1,0,1','1,1,0,1',1,1,'1,0,0,1','1,1,1,1','1,1,1,1','1,0,1,1','Administrator','Administrator','Administrator','1,1,1,1',1,NULL,NULL,'1,1,1,1,1,1','1,1,1,1,1,1'),(2,'0,0,0,0','1,1,1,0,1','1,1,1,0,1','1,1,1,0,1','1,1,1,0,1','1,1,1,0,1','1,1,1,0,1','1,1,1,0,1','1,1,1,0,1','1,1,1,0,1','1,1,1,0,1',1,0,1,'0,0,0,0','0,0,0,0','1,1,1,0,1','0,0,0,0','1,1,1,1','1,1,1,1','0,0,0,0','1,1,0,1',1,1,'1,0,0,1','1,1,1,1','1,1,1,1','0,0,0,0','Default Role for VSR','for all reseller','pm','1,1,1,1',0,NULL,NULL,'1,1,1,0,1,1','1,1,1,0,1,1'),(3,'1,1,1,1','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,0,'0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,'0,0','0,0,0,0,0,0','0,0,0,0,0,0','1,1,1,1','ERP User','Rights come from User Access Control','schema','1,1,1,1',0,NULL,NULL,'1,1,1,1,1,1','1,1,1,1,1,1'),(6,'1,1,1,1','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,0,'0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,'0,0','0,0,0,0,0,0','0,0,0,0,0,0','1,1,1,1','Sales','Sales price','Administrator','1,1,1,1',0,NULL,NULL,'1,1,1,1,1,1','1,1,1,1,1,1'),(9,'1,1,1,1','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,0,'0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,'0,0','0,0,0,0,0,0','0,0,0,0,0,0','1,1,1,1','Sales Officer','Sales, orders and customers','dummy_admin','1,1,1,1',0,NULL,NULL,'1,1,1,1,1,1','1,1,1,1,1,1'),(10,'1,1,1,1','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,0,'0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,'0,0','0,0,0,0,0,0','0,0,0,0,0,0','1,1,1,1','Store Keeper','Purchase, stock and transfers','dummy_admin','1,1,1,1',0,NULL,NULL,'1,1,1,1,1,1','1,1,1,1,1,1'),(11,'1,1,1,1','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,0,'0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,'0,0','0,0,0,0,0,0','0,0,0,0,0,0','1,1,1,1','Accountant','Vouchers, cheques and accounts reports','dummy_admin','1,1,1,1',0,NULL,NULL,'1,1,1,1,1,1','1,1,1,1,1,1'),(12,'1,1,1,1','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,0,'0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0','0,0,0,0',0,0,'0,0','0,0,0,0,0,0','0,0,0,0,0,0','1,1,1,1','Branch Manager','Runs a branch with approvals','dummy_admin','1,1,1,1',0,NULL,NULL,'1,1,1,1,1,1','1,1,1,1,1,1');
/*!40000 ALTER TABLE `admin_role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_role_permissions`
--

DROP TABLE IF EXISTS `admin_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_role_permissions` (
  `user_role_id` int NOT NULL,
  `form_key` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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

LOCK TABLES `admin_role_permissions` WRITE;
/*!40000 ALTER TABLE `admin_role_permissions` DISABLE KEYS */;
INSERT INTO `admin_role_permissions` VALUES (6,'price_history/invoice_search.php',1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 17:18:39'),(6,'price_history/price_quotation.php',1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 17:18:39'),(6,'price_history/sales_price_mrp_view.php',1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 17:18:39'),(6,'price_history/sales_price_view.php',1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 17:18:39'),(6,'price_history/serial_history.php',1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 17:18:39'),(6,'sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php',1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 17:18:39'),(6,'sales/sales_order_adjustment/sales_order_adjustment.php',1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 17:18:39'),(6,'sales/sales_order/sales_order.php',1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 17:18:39'),(9,'accounts_info/acc_report.php?r=1',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=10',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=11',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=12',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=13',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=14',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=15',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=16',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=17',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=18',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=19',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=2',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=20',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=21',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=22',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=3',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=4',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=5',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=6',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=7',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=8',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'accounts_info/acc_report.php?r=9',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'challan/challan_return.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'challan/challan.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'challan/sales_bill_from_challan.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'customer/customer_credit.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'customer/customer_ledger.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'customer/customer_list.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'customer/customer_profile.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'customer/portal_accounts.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'customer/portal_requests.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'dashboard/dashboard.php',1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'price_history/invoice_search.php',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'price_history/price_quotation.php',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'price_history/sales_price_mrp_view.php',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'price_history/sales_price_view.php',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'price_history/serial_history.php',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=bill',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=customer_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=customer_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=date_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=product_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/challan_register.php?report=status',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=amendment',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=date_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=date_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=date_po_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=date_req',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=date_req_pending_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=date_req_pending_pur',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=movement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=product_amount',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=product_no_price',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=product_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=product_po_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=product_req',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=product_req_pending_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=product_req_pending_pur',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=statement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=store_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=supplier_no_price',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=supplier_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/purchase_register.php?report=supplier_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=all_sr_items',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=customer_net_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=customer_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=date_net_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=date_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=order_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=order_pending_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=product_branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=product_explicit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=product_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=rebate',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=sr',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=sr_items',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=sr_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=sr_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/sales_register.php?report=statement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=ageing',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=all_store',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=branch_summary',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=current',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=current_branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=current_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=current_serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=current_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=current_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=date_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=date_so',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=date_so_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=date_so_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=date_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=date_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=ledger_order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=ledger_order_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=ledger_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=product_status',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=serial_ageing',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/stock_register.php?report=serial_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=date_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=date_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=product_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=product_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=route',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=store_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'report/transfer_register.php?report=store_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=complain_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=complain_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=complain_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=delivery_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=delivery_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=diagnosis',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=in_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=in_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=out_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=out_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=out_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=pending_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=pending_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=receivable_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=receivable_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=service_bill',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/service_register.php?report=utilize',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/stock_register.php?report=combined',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/stock_register.php?report=combined_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/stock_register.php?report=date_wise',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/stock_register.php?report=date_wise_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/stock_register.php?report=date_wise_warehouse',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/stock_register.php?report=ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/stock_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/stock_register.php?report=warehouse',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/stock_register.php?report=warehouse_ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'rma/stock_register.php?report=warehouse_serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'sales/pos_sales/pos_sales.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'sales/sales_invoice/sales_invoice.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'sales/sales_order_adjustment/sales_order_adjustment.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'sales/sales_order/sales_order.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'sales/sales_retrun/sales_return.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'trader/trader_convert.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'trader/trader_flag.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'trader/trader_group.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'trader/trader_info.php?cat=1',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'trader/trader_info.php?cat=3',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'trader/trader_info.php?cat=5',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(9,'trader/trader_search.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=1',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=10',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=11',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=12',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=13',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=14',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=15',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=16',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=17',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=18',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=19',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=2',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=20',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=21',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=22',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=3',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=4',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=5',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=6',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=7',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=8',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'accounts_info/acc_report.php?r=9',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'amedment/purchase_price_amendment.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'amedment/stock_amendment_in.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'amedment/stock_amendment_out.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'amedment/transaction_lock.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'product/periodical_warranty.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'product/product_category.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'product/product_classification.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'product/product_info.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'product/purchase_price_fixing.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'product/sales_price_fixing.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'product/transaction_category.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'product/unit_info.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'product/warranty.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'purchase/purchase_order.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'purchase/purchase_return.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'purchase/purchase.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'purchase/requisition.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=bill',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=customer_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=customer_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=date_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=product_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/challan_register.php?report=status',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=amendment',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=date_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=date_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=date_po_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=date_req',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=date_req_pending_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=date_req_pending_pur',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=movement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=product_amount',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=product_no_price',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=product_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=product_po_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=product_req',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=product_req_pending_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=product_req_pending_pur',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=statement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=store_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=supplier_no_price',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=supplier_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/purchase_register.php?report=supplier_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=all_sr_items',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=customer_net_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=customer_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=date_net_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=date_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=order_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=order_pending_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=product_branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=product_explicit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=product_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=rebate',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=sr',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=sr_items',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=sr_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=sr_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/sales_register.php?report=statement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=ageing',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=all_store',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=branch_summary',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=current',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=current_branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=current_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=current_serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=current_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=current_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=date_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=date_so',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=date_so_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=date_so_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=date_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=date_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=ledger_order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=ledger_order_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=ledger_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=product_status',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=serial_ageing',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/stock_register.php?report=serial_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=date_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=date_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=product_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=product_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=route',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=store_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'report/transfer_register.php?report=store_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=complain_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=complain_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=complain_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=delivery_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=delivery_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=diagnosis',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=in_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=in_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=out_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=out_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=out_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=pending_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=pending_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=receivable_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=receivable_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=service_bill',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/service_register.php?report=utilize',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/stock_register.php?report=combined',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/stock_register.php?report=combined_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/stock_register.php?report=date_wise',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/stock_register.php?report=date_wise_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/stock_register.php?report=date_wise_warehouse',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/stock_register.php?report=ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/stock_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/stock_register.php?report=warehouse',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/stock_register.php?report=warehouse_ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'rma/stock_register.php?report=warehouse_serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(10,'transfer/stock_transfer.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_account.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_cc_interest.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_cheque_book.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_cheque_history.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_cheque_treatment.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_group.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_overdue.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_posting.php',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_posting.php?status=draft&vtype=contra',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_posting.php?status=draft&vtype=credit&ib=0',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_posting.php?status=draft&vtype=credit&ib=1',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_posting.php?status=draft&vtype=debit&ib=0',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_posting.php?status=draft&vtype=debit&ib=1',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_posting.php?status=draft&vtype=journal&ib=0',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_posting.php?status=draft&vtype=journal&ib=1',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_receipt_category.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=1',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=10',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=11',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=12',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=13',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=14',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=15',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=16',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=17',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=18',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=19',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=2',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=20',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=21',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=22',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=3',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=4',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=5',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=6',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=7',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=8',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_report.php?r=9',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_rp_voucher.php?type=credit',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_rp_voucher.php?type=credit&ib=1',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_rp_voucher.php?type=debit',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_rp_voucher.php?type=debit&ib=1',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_sub_ledger_group.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_sub_ledger.php',1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_voucher.php?type=contra',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_voucher.php?type=journal',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'accounts_info/acc_voucher.php?type=journal&ib=1',1,1,1,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=challan',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=challan_return',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=price_amendment',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=purchase',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=purchase_order',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=purchase_return',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=requisition',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=sales',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=sales_order',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=sales_return',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=stock_in',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=stock_out',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'approve/approve.php?doc=stock_transfer',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=bill',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=customer_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=customer_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=date_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=product_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/challan_register.php?report=status',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=amendment',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=date_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=date_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=date_po_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=date_req',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=date_req_pending_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=date_req_pending_pur',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=movement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=product_amount',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=product_no_price',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=product_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=product_po_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=product_req',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=product_req_pending_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=product_req_pending_pur',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=statement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=store_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=supplier_no_price',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=supplier_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/purchase_register.php?report=supplier_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=all_sr_items',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=customer_net_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=customer_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=date_net_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=date_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=order_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=order_pending_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=product_branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=product_explicit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=product_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=rebate',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=sr',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=sr_items',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=sr_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=sr_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/sales_register.php?report=statement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=ageing',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=all_store',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=branch_summary',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=current',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=current_branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=current_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=current_serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=current_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=current_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=date_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=date_so',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=date_so_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=date_so_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=date_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=date_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=ledger_order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=ledger_order_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=ledger_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=product_status',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=serial_ageing',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/stock_register.php?report=serial_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=date_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=date_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=product_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=product_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=route',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=store_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'report/transfer_register.php?report=store_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/approve.php?type=complain',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/approve.php?type=delivery',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/approve.php?type=replace_in',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/approve.php?type=replace_out',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/approve.php?type=service_bill',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=complain_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=complain_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=complain_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=delivery_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=delivery_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=diagnosis',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=in_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=in_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=out_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=out_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=out_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=pending_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=pending_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=receivable_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=receivable_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=service_bill',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/service_register.php?report=utilize',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/stock_register.php?report=combined',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/stock_register.php?report=combined_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/stock_register.php?report=date_wise',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/stock_register.php?report=date_wise_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/stock_register.php?report=date_wise_warehouse',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/stock_register.php?report=ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/stock_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/stock_register.php?report=warehouse',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/stock_register.php?report=warehouse_ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(11,'rma/stock_register.php?report=warehouse_serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_posting.php?status=draft&vtype=contra',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_posting.php?status=draft&vtype=credit&ib=0',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_posting.php?status=draft&vtype=credit&ib=1',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_posting.php?status=draft&vtype=debit&ib=0',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_posting.php?status=draft&vtype=debit&ib=1',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_posting.php?status=draft&vtype=journal&ib=0',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_posting.php?status=draft&vtype=journal&ib=1',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=1',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=10',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=11',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=12',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=13',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=14',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=15',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=16',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=17',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=18',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=19',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=2',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=20',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=21',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=22',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=3',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=4',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=5',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=6',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=7',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=8',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'accounts_info/acc_report.php?r=9',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'amedment/purchase_price_amendment.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'amedment/stock_amendment_in.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'amedment/stock_amendment_out.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'amedment/transaction_lock.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=challan',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=challan_return',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=price_amendment',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=purchase',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=purchase_order',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=purchase_return',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=requisition',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=sales',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=sales_order',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=sales_return',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=stock_in',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=stock_out',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'approve/approve.php?doc=stock_transfer',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'challan/challan_return.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'challan/challan.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'challan/sales_bill_from_challan.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'customer/customer_credit.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'customer/customer_ledger.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'customer/customer_list.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'customer/customer_profile.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'customer/portal_accounts.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'customer/portal_requests.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'dashboard/dashboard.php',1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'price_history/invoice_search.php',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'price_history/price_quotation.php',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'price_history/sales_price_mrp_view.php',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'price_history/sales_price_view.php',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'price_history/serial_history.php',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'purchase/purchase_order.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'purchase/purchase_return.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'purchase/purchase.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'purchase/requisition.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=bill',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=customer_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=customer_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=date_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=product_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/challan_register.php?report=status',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=amendment',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=date_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=date_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=date_po_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=date_req',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=date_req_pending_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=date_req_pending_pur',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=movement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=product_amount',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=product_no_price',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=product_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=product_po_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=product_req',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=product_req_pending_po',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=product_req_pending_pur',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=statement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=store_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=supplier_no_price',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=supplier_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/purchase_register.php?report=supplier_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=all_sr_items',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=customer_net_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=customer_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=date_net_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=date_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=date_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=order_pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=order_pending_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=product_branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=product_explicit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=product_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=product_return',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=rebate',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=sr',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=sr_items',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=sr_net',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=sr_profit',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/sales_register.php?report=statement',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=ageing',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=all_store',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=branch_summary',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=current',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=current_branch',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=current_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=current_serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=current_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=current_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=date_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=date_so',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=date_so_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=date_so_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=date_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=date_wh_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=ledger_order',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=ledger_order_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=ledger_wh',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=product_status',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=serial_ageing',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/stock_register.php?report=serial_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=date_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=date_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=pending',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=product_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=product_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=route',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=store_in',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'report/transfer_register.php?report=store_out',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/approve.php?type=complain',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'rma/approve.php?type=delivery',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'rma/approve.php?type=replace_in',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'rma/approve.php?type=replace_out',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'rma/approve.php?type=service_bill',1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'rma/complain_receive.php',1,1,1,1,0,0,0,0,0,0,0,1,1,0,1,0,0,0,1,0,10,'2026-10-30 19:04:00'),(12,'rma/delivery_store_fixing.php',1,1,1,1,0,0,0,0,0,0,0,1,1,0,1,0,0,0,1,0,10,'2026-10-30 19:04:00'),(12,'rma/diagnosis.php',1,1,1,1,0,0,0,0,0,0,0,1,1,0,1,0,0,0,1,0,10,'2026-10-30 19:04:00'),(12,'rma/item_utilize.php',1,1,1,1,0,0,0,0,0,0,0,1,1,0,1,0,0,0,1,0,10,'2026-10-30 19:04:00'),(12,'rma/problem.php',1,1,1,1,0,0,0,0,0,0,0,1,1,0,1,0,0,0,1,0,10,'2026-10-30 19:04:00'),(12,'rma/replace_delivery.php',1,1,1,1,0,0,0,0,0,0,0,1,1,0,1,0,0,0,1,0,10,'2026-10-30 19:04:00'),(12,'rma/replace_in.php',1,1,1,1,0,0,0,0,0,0,0,1,1,0,1,0,0,0,1,0,10,'2026-10-30 19:04:00'),(12,'rma/replace_out.php',1,1,1,1,0,0,0,0,0,0,0,1,1,0,1,0,0,0,1,0,10,'2026-10-30 19:04:00'),(12,'rma/service_bill.php',1,1,1,1,0,0,0,0,0,0,0,1,1,0,1,0,0,0,1,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=complain_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=complain_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=complain_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=delivery_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=delivery_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=diagnosis',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=in_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=in_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=out_date',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=out_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=out_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=pending_customer',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=pending_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=receivable_product',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=receivable_supplier',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=service_bill',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/service_register.php?report=utilize',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/stock_register.php?report=combined',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/stock_register.php?report=combined_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/stock_register.php?report=date_wise',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/stock_register.php?report=date_wise_cost',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/stock_register.php?report=date_wise_warehouse',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/stock_register.php?report=ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/stock_register.php?report=serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/stock_register.php?report=warehouse',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/stock_register.php?report=warehouse_ledger',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'rma/stock_register.php?report=warehouse_serial',1,0,0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,1,0,0,10,'2026-10-30 19:04:00'),(12,'sales_person/sr_collections.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'sales_person/sr_commission.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'sales_person/sr_list.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'sales_person/sr_portal_accounts.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'sales_person/sr_target.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'sales_person/territory.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'sales/pos_sales/pos_sales.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'sales/sales_invoice/sales_invoice.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'sales/sales_order_adjustment/sales_order_adjustment.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'sales/sales_order/sales_order.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'sales/sales_retrun/sales_return.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00'),(12,'trader/trader_convert.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'trader/trader_flag.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'trader/trader_group.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'trader/trader_info.php?cat=1',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'trader/trader_info.php?cat=3',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'trader/trader_info.php?cat=5',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'trader/trader_search.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'trader/trader_sr.php',1,1,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,10,'2026-10-30 19:04:00'),(12,'transfer/stock_transfer.php',1,1,1,1,1,0,0,1,1,0,1,1,1,0,1,0,1,1,1,0,10,'2026-10-30 19:04:00');
/*!40000 ALTER TABLE `admin_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

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
  `full_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
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
  `department` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
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
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user`
--

LOCK TABLES `admin_user` WRITE;
/*!40000 ALTER TABLE `admin_user` DISABLE KEYS */;
INSERT INTO `admin_user` VALUES (2,'6fb42da0e32e07b61c9f0251fe627a9c','administrator1','',0,NULL,NULL,'support@moonnetworks.net','',0,1,'SuperAdmin','staff',NULL,'',NULL,1,NULL,NULL,1,1,NULL,NULL),(10,'$2y$12$z/zGboZrNB2yL42GNS5EgexRKevOHHxx7FqskNKOvMjdX/zDS2dHK','Administrator','',0,'06f7b89ada0b1345df0ab3859380e177',1793895156,'','',1,1,'SuperAdmin','staff',NULL,'',NULL,1,NULL,NULL,1,1,NULL,NULL),(16,'$2y$12$KMSiMWfZ.d713ae.IH.SVOi8pKEjhEzZvcoK2SAXQwhkY22aQTO9q','nazmulb','Nazmul Bhuiyan',0,'',0,'nazmulitbd@gmail.com','01815911873',1,6,'Supervisor','staff',NULL,'Sales','1987-10-29',1,'10:03:00','20:03:00',1,1,1,1),(48,'$2y$12$.LF8zGArWl8EH9VKNECGbu1vvWk4.TAMWgUWcnVTN9Ozvq10wS45W','demo_sales','Rafiq Islam',0,'',0,'demo_sales@example.com','',1,9,'Operator','staff',NULL,'Sales',NULL,1,NULL,NULL,1,0,1,1),(49,'$2y$12$8T.3IplGdFTHgc1G.HZ81OiigLUnLhCHG.6BhkrvlNzJFiJErZvvi','demo_store','Shamim Reza',0,'0e0d52d5467edb440212689658497807',1793387036,'demo_store@example.com','',1,10,'Operator','staff',NULL,'Store',NULL,1,NULL,NULL,1,0,1,1),(50,'$2y$12$h0ed.SP7Rec1PHBWEjJhHeGbtMsk765En11UMxv5fT9AMP/5qFHhK','demo_accounts','Sharmin Sultana',0,'468074cc2ae4eb6e8b20aeb0267f1886',1793387037,'demo_accounts@example.com','',1,11,'Supervisor','staff',NULL,'Accountant',NULL,1,NULL,NULL,1,0,1,1),(51,'$2y$12$yTjw14QkGbJD.uYKHfH5WuZJQYT/D88t4/fegi4PC/gBcnu5cs8gy','demo_manager','Imran Hossain',0,'3182767e4ad068831f999cfd12842b38',1793387037,'demo_manager@example.com','',1,12,'Admin','staff',NULL,'Branch',NULL,1,NULL,NULL,1,0,1,1),(52,'$2y$12$.K5.9/dLYnUqZ./UrlkGaO6.QIo5/d7QNetGZJkvvAXH7ihzHzzaC','demo_ctg','Jewel Barua',0,'22c98b5b5907ce2a92839e668a84eab1',1793387102,'demo_ctg@example.com','',1,9,'Operator','staff',NULL,'Sales',NULL,1,NULL,NULL,1,0,15,15),(53,'!','demo_customer','Rahman Enterprise',0,'03888523c976724fb881ec3bb6088d6b',1793387038,'','',1,3,'Operator','customer',83,'',NULL,1,NULL,NULL,1,0,NULL,NULL),(54,'!','demo_customer2','Bengal IT Solutions',0,'ea704203d4172d8bf3f25917381126ee',1793387039,'','',1,3,'Operator','customer',85,'',NULL,1,NULL,NULL,1,0,NULL,NULL),(55,'!','demo_sr','Tanvir Hasan',0,'fc99077bfeea97848bfc8adb13b52c94',1793387039,'','',1,3,'Operator','employee',19,'',NULL,1,NULL,NULL,1,0,NULL,NULL),(56,'!','demo_sr2','Nusrat Jahan',0,'23e8b7e2e43fbb453a56f681e1e62725',1793387039,'','',1,3,'Operator','employee',20,'',NULL,1,NULL,NULL,1,0,NULL,NULL),(61,'!','EMP00004','',0,'c99c4bae018082b5867c34d09259a7a5',1793431879,NULL,'',0,3,'Operator','employee',21,'',NULL,1,NULL,NULL,1,0,NULL,NULL),(64,'$2y$12$1rK8emBflmrSDGiisD53m.bsetSct4o61brhFopL1usW37H9orrZa','sample_cashier','SAMPLE Cashier',0,NULL,NULL,'sample.cashier@example.com','01711000701',1,11,'Operator','staff',NULL,'Sales',NULL,1,NULL,NULL,0,0,11,NULL);
/*!40000 ALTER TABLE `admin_user` ENABLE KEYS */;
UNLOCK TABLES;

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

LOCK TABLES `admin_user_branches` WRITE;
/*!40000 ALTER TABLE `admin_user_branches` DISABLE KEYS */;
INSERT INTO `admin_user_branches` VALUES (48,1),(49,1),(50,1),(51,1),(55,1),(56,1),(64,11),(52,15);
/*!40000 ALTER TABLE `admin_user_branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_ledgers`
--

DROP TABLE IF EXISTS `admin_user_ledgers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_ledgers` (
  `rule_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '0',
  `scope` enum('all','trader_group','account_group','ledger') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_id` bigint unsigned NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rule_id`),
  UNIQUE KEY `uq_user_ledger_rule` (`user_id`,`branch_id`,`scope`,`ref_id`),
  CONSTRAINT `fk_user_ledger_user` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_ledgers`
--

LOCK TABLES `admin_user_ledgers` WRITE;
/*!40000 ALTER TABLE `admin_user_ledgers` DISABLE KEYS */;
INSERT INTO `admin_user_ledgers` VALUES (1,48,1,'all',0,10,'2026-10-31 06:13:16');
/*!40000 ALTER TABLE `admin_user_ledgers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_permissions`
--

DROP TABLE IF EXISTS `admin_user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_permissions` (
  `user_id` int NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '0',
  `form_key` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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

LOCK TABLES `admin_user_permissions` WRITE;
/*!40000 ALTER TABLE `admin_user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_stores`
--

DROP TABLE IF EXISTS `admin_user_stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_stores` (
  `rule_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '0',
  `form_key` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rule_id`),
  UNIQUE KEY `uq_user_store_rule` (`user_id`,`branch_id`,`form_key`,`store_name`),
  CONSTRAINT `fk_user_store_user` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_stores`
--

LOCK TABLES `admin_user_stores` WRITE;
/*!40000 ALTER TABLE `admin_user_stores` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_notification_reads`
--

DROP TABLE IF EXISTS `app_notification_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_notification_reads` (
  `notification_id` bigint unsigned NOT NULL,
  `reader_type` enum('user','sr','customer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reader_id` bigint unsigned NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`,`reader_type`,`reader_id`),
  CONSTRAINT `fk_app_notification_read` FOREIGN KEY (`notification_id`) REFERENCES `app_notifications` (`notification_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_notification_reads`
--

LOCK TABLES `app_notification_reads` WRITE;
/*!40000 ALTER TABLE `app_notification_reads` DISABLE KEYS */;
INSERT INTO `app_notification_reads` VALUES (5,'user',10,'2026-11-05 15:58:28'),(6,'user',10,'2026-11-05 15:58:26'),(7,'user',10,'2026-11-05 15:58:23'),(8,'user',10,'2026-11-05 15:58:21'),(9,'user',10,'2026-11-05 15:58:19'),(10,'user',10,'2026-11-05 15:58:16'),(11,'user',10,'2026-11-05 16:02:14'),(15,'user',10,'2026-11-05 16:02:12');
/*!40000 ALTER TABLE `app_notification_reads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_notifications`
--

DROP TABLE IF EXISTS `app_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_notifications` (
  `notification_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `audience` enum('admin','staff','customer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `form_key` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sr_id` bigint unsigned DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `title` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `link` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sent_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `ix_app_notifications_admin` (`audience`,`notification_id`),
  KEY `ix_app_notifications_staff` (`audience`,`sr_id`,`notification_id`),
  KEY `ix_app_notifications_customer` (`audience`,`customer_id`,`notification_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_notifications`
--

LOCK TABLES `app_notifications` WRITE;
/*!40000 ALTER TABLE `app_notifications` DISABLE KEYS */;
INSERT INTO `app_notifications` VALUES (1,'admin','approve/approve.php?doc=purchase_order',NULL,NULL,NULL,'Approval needed: Purchase Order DESPOR2026000006','SAMPLE Tech Distributors Ltd','billing/approve/approve.php?doc=purchase_order',NULL,'2026-11-02 16:00:42'),(2,'admin','approve/approve.php?doc=purchase',NULL,NULL,NULL,'Approval needed: Purchase DESPUR2026000011','SAMPLE Tech Distributors Ltd','billing/approve/approve.php?doc=purchase',NULL,'2026-11-02 16:00:45'),(3,'admin','approve/approve.php?doc=stock_in',NULL,NULL,NULL,'Approval needed: Stock Amendment IN DESSAI2026000003','','billing/approve/approve.php?doc=stock_in',NULL,'2026-11-02 16:00:48'),(4,'admin','approve/approve.php?doc=stock_transfer',NULL,NULL,NULL,'Approval needed: Stock Transfer Store to Store DESSTT2026000005','','billing/approve/approve.php?doc=stock_transfer',NULL,'2026-11-02 16:00:50'),(5,'admin','approve/approve.php?doc=sales',NULL,NULL,NULL,'Approval needed: Sales Invoice SI2026000076','SAMPLE Retail Customer','billing/approve/approve.php?doc=sales',NULL,'2026-11-02 16:00:53'),(6,'admin','approve/approve.php?doc=sales_order',NULL,NULL,NULL,'Approval needed: Sales Order SO2026000009','SAMPLE Corporate Buyer Ltd','billing/approve/approve.php?doc=sales_order',NULL,'2026-11-02 16:00:59'),(7,'admin','approve/approve.php?doc=challan',NULL,NULL,NULL,'Approval needed: Challan DESCHL2026000006','SAMPLE Corporate Buyer Ltd','billing/approve/approve.php?doc=challan',NULL,'2026-11-02 16:01:01'),(8,'admin','approve/approve.php?doc=sales',NULL,NULL,NULL,'Approval needed: Sales Invoice SI2026000078','SAMPLE Retail Customer','billing/approve/approve.php?doc=sales',NULL,'2026-11-02 16:01:43'),(9,'admin','approve/approve.php?doc=sales_return',NULL,NULL,NULL,'Approval needed: Sales Return SR2026000006','','billing/approve/approve.php?doc=sales_return',NULL,'2026-11-02 16:05:51'),(10,'admin','rma/approve.php?type=complain',NULL,NULL,NULL,'Approval needed: Complain Received DESCOM2026000006','SAMPLE Retail Customer','billing/rma/approve.php?type=complain',NULL,'2026-11-02 16:06:26'),(11,'admin','',NULL,10,NULL,'Purchase approved: DESPUR2026000010','PrintLine Bangladesh','',NULL,'2026-11-02 16:07:23'),(12,'admin','',NULL,63,NULL,'Sales approved: SI2026000076','SAMPLE Retail Customer','',NULL,'2026-11-05 15:59:33'),(13,'customer','',NULL,NULL,94,'Sales ready: SI2026000076','Amount 32,000.00','customer_portal/invoices.php',NULL,'2026-11-05 15:59:33'),(14,'staff','',23,NULL,NULL,'Sales approved: SI2026000076','SAMPLE Retail Customer','',NULL,'2026-11-05 15:59:33'),(15,'admin','',NULL,10,NULL,'Sales approved: SI2026000060','Walk-in Customer','',NULL,'2026-11-05 15:59:36');
/*!40000 ALTER TABLE `app_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_blocked_ip`
--

DROP TABLE IF EXISTS `audit_blocked_ip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_blocked_ip` (
  `ip` varchar(45) NOT NULL,
  `reason` varchar(190) NOT NULL DEFAULT '',
  `blocked_until` datetime DEFAULT NULL,
  `created_by` varchar(120) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip`),
  KEY `idx_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_blocked_ip`
--

LOCK TABLES `audit_blocked_ip` WRITE;
/*!40000 ALTER TABLE `audit_blocked_ip` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_blocked_ip` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_ip_country`
--

DROP TABLE IF EXISTS `audit_ip_country`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_ip_country` (
  `ip` varchar(45) NOT NULL,
  `country` varchar(3) NOT NULL DEFAULT '',
  `checked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_ip_country`
--

LOCK TABLES `audit_ip_country` WRITE;
/*!40000 ALTER TABLE `audit_ip_country` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_ip_country` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `audit_id` bigint NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `category` varchar(12) NOT NULL,
  `action` varchar(40) NOT NULL,
  `severity` varchar(8) NOT NULL DEFAULT 'info',
  `status` varchar(8) NOT NULL DEFAULT 'ok',
  `actor_type` varchar(12) NOT NULL DEFAULT 'anonymous',
  `actor_id` int NOT NULL DEFAULT '0',
  `actor_name` varchar(120) NOT NULL DEFAULT '',
  `branch_id` int NOT NULL DEFAULT '0',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `country` varchar(3) NOT NULL DEFAULT '',
  `browser` varchar(30) NOT NULL DEFAULT '',
  `os` varchar(30) NOT NULL DEFAULT '',
  `device` varchar(10) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `method` varchar(8) NOT NULL DEFAULT '',
  `page` varchar(190) NOT NULL DEFAULT '',
  `target_table` varchar(64) NOT NULL DEFAULT '',
  `target_id` varchar(40) NOT NULL DEFAULT '',
  `summary` varchar(255) NOT NULL DEFAULT '',
  `detail` mediumtext,
  `session_ref` char(12) NOT NULL DEFAULT '',
  `request_id` char(16) NOT NULL DEFAULT '',
  PRIMARY KEY (`audit_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_category` (`category`,`created_at`),
  KEY `idx_severity` (`severity`,`created_at`),
  KEY `idx_ip` (`ip`,`created_at`),
  KEY `idx_actor` (`actor_type`,`actor_id`,`created_at`),
  KEY `idx_target` (`target_table`,`target_id`),
  KEY `idx_request` (`request_id`)
) ENGINE=InnoDB AUTO_INCREMENT=258 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,'2026-11-01 19:50:47','login','logout','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','GET','/billing_star/billing/logout.php','','','Signed out',NULL,'d0aaeb3ad033','b4a6f1870b7f83b7'),(2,'2026-11-01 19:50:52','login','login_ok','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: Administrator',NULL,'','fb4be6650c485894'),(3,'2026-11-02 20:59:34','login','login_failed','warn','failed','anonymous',0,'admin@example.com',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Sign-in failed: admin@example.com',NULL,'','8a692865dd8c88db'),(4,'2026-11-02 20:59:47','login','login_ok','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: Administrator',NULL,'','359d687152acace2'),(5,'2026-11-02 21:06:15','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_sequences','','Added to sales_invoice_sequences','{\"added\":{\"sequence_year\":2026,\"last_number\":\"1\"}}','50d6f0de83c4','0848240c8ae831f1'),(6,'2026-11-02 21:06:15','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','192','Added to sales_invoices #192','{\"added\":{\"invoice_no\":\"SI2026000075\",\"ref_no\":\"POS\",\"sales_date\":\"2026-11-02\",\"bill_type\":\"Cash\",\"customer_id\":null,\"customer_name\":\"Walk-in Customer\",\"mobile\":\"\",\"sales_person\":\"Administrator\",\"narration\":\"POS sale. Tendered 42,130.00, change 0.00.\",\"subtotal\":38400,\"discount_amount\":100,\"vat_amount\":3830,\"net_amount\":42130,\"cash_received\":42130,\"balance_amount\":\"0\",\"total_quantity\":3,\"created_by\":10}}','50d6f0de83c4','0848240c8ae831f1'),(7,'2026-11-02 21:06:15','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_items','245','Added to sales_invoice_items #245','{\"added\":{\"invoice_id\":192,\"product_id\":75,\"product_code\":\"ACC-2003\",\"product_group\":\"Monitor\",\"brand\":\"Dell\",\"model_caption\":\"E2223HN\",\"description\":\"22 inch LED monitor\",\"warranty\":\"1 Year\",\"unit_name\":\"Pcs\",\"serial_imei\":\"\",\"quantity\":3,\"unit_price\":12800,\"discount_amount\":0,\"line_total\":38400,\"line_number\":1}}','50d6f0de83c4','0848240c8ae831f1'),(8,'2026-11-02 21:06:15','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_payments','179','Added to sales_invoice_payments #179','{\"added\":{\"invoice_id\":192,\"payment_method\":\"cash\",\"amount\":42130,\"reference\":\"cash\",\"received_by\":10}}','50d6f0de83c4','0848240c8ae831f1'),(9,'2026-11-02 21:06:15','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','192','Updated sales_invoices #192: branch_id','{\"changes\":{\"branch_id\":[null,1]},\"rows\":1}','50d6f0de83c4','0848240c8ae831f1'),(10,'2026-11-02 21:06:15','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_docs','408','Added to inv_docs #408','{\"added\":{\"doc_type\":\"sales_invoice\",\"doc_no\":\"SI2026000075\",\"doc_date\":\"2026-11-02\",\"ref_no\":\"POS\",\"branch_id\":1,\"party_type\":\"customer\",\"party_id\":null,\"party_group\":\"\",\"party_name\":\"Walk-in Customer\",\"mobile\":\"\",\"sales_invoice_id\":192,\"net_amount\":42130,\"total_quantity\":3,\"approval_status\":\"pending\",\"created_by\":10}}','50d6f0de83c4','0848240c8ae831f1'),(11,'2026-11-02 21:06:15','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_doc_lines','584','Added to inv_doc_lines #584','{\"added\":{\"doc_id\":408,\"line_no\":1,\"product_id\":75,\"product_code\":\"ACC-2003\",\"product_group\":\"Monitor\",\"brand\":\"Dell\",\"model_caption\":\"E2223HN\",\"description\":\"22 inch LED monitor\",\"unit_name\":\"Pcs\",\"store_name\":\"Main Store\",\"quantity\":3,\"free_quantity\":0,\"unit_price\":12800,\"line_total\":38400,\"serials\":\"\"}}','50d6f0de83c4','0848240c8ae831f1'),(12,'2026-11-02 21:06:15','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_stock_moves','470','Added to inv_stock_moves #470','{\"added\":{\"product_id\":75,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":3,\"unit_cost\":12800,\"doc_type\":\"sales_invoice\",\"doc_id\":408,\"doc_no\":\"SI2026000075\",\"line_id\":584}}','50d6f0de83c4','0848240c8ae831f1'),(13,'2026-11-02 21:06:15','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','192','Updated sales_invoices #192: stock_doc_id','{\"changes\":{\"stock_doc_id\":[null,408]},\"rows\":1}','50d6f0de83c4','0848240c8ae831f1'),(14,'2026-11-02 21:06:15','activity','form_post','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','','','Form sent: pos_sales.php','{\"fields\":{\"csrf_token\":\"***\",\"cart_json\":\"[{\\\"product_id\\\":75,\\\"product_code\\\":\\\"ACC-2003\\\",\\\"product_group\\\":\\\"Monitor\\\",\\\"brand\\\":\\\"Dell\\\",\\\"model_caption\\\":\\\"E2223HN\\\",\\\"description\\\":\\\"22 inch LED monitor\\\",\\\"warranty\\\":\\\"1 Year\\\",\\\"unit_name\\\":\\\"Pcs\\\",\\\"quantity\\\":3,\\\"u\",\"customer_name\":\"Walk-in Customer\",\"customer_id\":\"\",\"mobile\":\"\",\"discount_amount\":\"100\",\"vat_percent\":\"10\",\"payment_method\":\"cash\",\"payment_reference\":\"cash\",\"tendered\":\"42130.00\"},\"files\":[]}','50d6f0de83c4','0848240c8ae831f1'),(15,'2026-11-02 21:33:58','login','login_ok','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: Administrator',NULL,'50d6f0de83c4','71fe5857b3581ac1'),(16,'2026-11-02 21:56:39','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'','1c7153aa27b44b3f'),(17,'2026-11-02 21:57:07','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'','a75117e918a82f3d'),(18,'2026-11-02 21:57:32','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'','249184b098ab8af3'),(19,'2026-11-02 21:58:59','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'','337dc73b65844d1b'),(20,'2026-11-02 21:59:02','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/unit_info.php','product_units','10','Added to product_units #10','{\"added\":{\"unit_name\":\"SAMPLE Box\",\"short_name\":\"Bx\",\"allow_decimal\":0,\"is_active\":1}}','2e881e0f3ba8','e5c4f409913068c3'),(21,'2026-11-02 21:59:02','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/unit_info.php','','','Form sent: unit_info.php','{\"fields\":{\"csrf_token\":\"***\",\"id\":\"0\",\"unit_name\":\"SAMPLE Box\",\"short_name\":\"Bx\",\"is_active\":\"1\"},\"files\":[]}','2e881e0f3ba8','e5c4f409913068c3'),(22,'2026-11-02 21:59:05','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/product_category.php','product_categories','7','Added to product_categories #7','{\"added\":{\"category_name\":\"SAMPLE Electronics\",\"description\":\"Sample category for the walkthrough\",\"sort_order\":90,\"is_active\":1}}','2e881e0f3ba8','395f422cf5b15fdf'),(23,'2026-11-02 21:59:05','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/product_category.php','','','Form sent: product_category.php','{\"fields\":{\"csrf_token\":\"***\",\"id\":\"0\",\"category_name\":\"SAMPLE Electronics\",\"description\":\"Sample category for the walkthrough\",\"sort_order\":\"90\",\"is_active\":\"1\"},\"files\":[]}','2e881e0f3ba8','395f422cf5b15fdf'),(24,'2026-11-02 21:59:08','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/product_classification.php','product_classifications','24','Added to product_classifications #24','{\"added\":{\"class_name\":\"SAMPLE Smartphone\",\"category_id\":\"7\",\"is_active\":1,\"class_type\":\"group\"}}','2e881e0f3ba8','6e51059f7a5b2022'),(25,'2026-11-02 21:59:08','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/product_classification.php','','','Form sent: product_classification.php','{\"fields\":{\"csrf_token\":\"***\",\"id\":\"0\",\"tab\":\"group\",\"class_name\":\"SAMPLE Smartphone\",\"category_id\":\"7\",\"is_active\":\"1\"},\"files\":[]}','2e881e0f3ba8','6e51059f7a5b2022'),(26,'2026-11-02 21:59:10','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/warranty.php','product_warranties','12','Added to product_warranties #12','{\"added\":{\"warranty_name\":\"SAMPLE 1 Year\",\"period\":1,\"period_unit\":\"year\",\"terms\":\"Service warranty, parts and labour\",\"is_active\":1}}','2e881e0f3ba8','809acd6e0bb1ae03'),(27,'2026-11-02 21:59:10','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/warranty.php','','','Form sent: warranty.php','{\"fields\":{\"csrf_token\":\"***\",\"id\":\"0\",\"warranty_name\":\"SAMPLE 1 Year\",\"period\":\"1\",\"period_unit\":\"year\",\"terms\":\"Service warranty, parts and labour\",\"is_active\":\"1\"},\"files\":[]}','2e881e0f3ba8','809acd6e0bb1ae03'),(28,'2026-11-02 21:59:13','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/product_info.php','sales_products','86','Added to sales_products #86','{\"added\":{\"product_code\":\"SMP-001\",\"category_id\":\"7\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"SAMPLE 1 Year\",\"default_unit_price\":25000,\"purchase_price\":21000,\"reorder_level\":5,\"vat_percent\":5,\"serial_required\":0,\"is_active\":1}}','2e881e0f3ba8','35d8ed0c2eae5b75'),(29,'2026-11-02 21:59:13','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/product_info.php','','','Form sent: product_info.php','{\"fields\":{\"csrf_token\":\"***\",\"id\":\"0\",\"product_code\":\"SMP-001\",\"category_id\":\"7\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"SAMPLE 1 Year\",\"default_unit_price\":\"25000\",\"purchase_price\":\"21000\",\"reorder_level\":\"5\",\"vat_percent\":\"5\",\"is_active\":\"1\"},\"files\":[]}','2e881e0f3ba8','35d8ed0c2eae5b75'),(30,'2026-11-02 21:59:16','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/product_info.php','sales_products','87','Added to sales_products #87','{\"added\":{\"product_code\":\"SMP-002\",\"category_id\":\"7\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"SAMPLE 1 Year\",\"default_unit_price\":3500,\"purchase_price\":2600,\"reorder_level\":5,\"vat_percent\":5,\"serial_required\":0,\"is_active\":1}}','2e881e0f3ba8','a69ce7ce952ef305'),(31,'2026-11-02 21:59:16','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/product/product_info.php','','','Form sent: product_info.php','{\"fields\":{\"csrf_token\":\"***\",\"id\":\"0\",\"product_code\":\"SMP-002\",\"category_id\":\"7\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"SAMPLE 1 Year\",\"default_unit_price\":\"3500\",\"purchase_price\":\"2600\",\"reorder_level\":\"5\",\"vat_percent\":\"5\",\"is_active\":\"1\"},\"files\":[]}','2e881e0f3ba8','a69ce7ce952ef305'),(32,'2026-11-02 21:59:19','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales_person/territory.php','sr_territories','10','Added to sr_territories #10','{\"added\":{\"territory_code\":\"SMP-DHK\",\"territory_name\":\"SAMPLE Dhaka North\",\"region\":\"Dhaka\",\"parent_id\":null,\"description\":\"Sample territory\",\"is_active\":1}}','2e881e0f3ba8','17ea199b147b59cc'),(33,'2026-11-02 21:59:19','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales_person/territory.php','','','Form sent: territory.php','{\"fields\":{\"csrf_token\":\"***\",\"territory_id\":\"0\",\"territory_code\":\"SMP-DHK\",\"territory_name\":\"SAMPLE Dhaka North\",\"region\":\"Dhaka\",\"parent_id\":\"0\",\"is_active\":\"1\",\"description\":\"Sample territory\"},\"files\":[]}','2e881e0f3ba8','17ea199b147b59cc'),(34,'2026-11-02 21:59:21','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales_person/sr_list.php','sales_representatives','23','Added to sales_representatives #23','{\"added\":{\"sr_code\":\"SMP-SR1\",\"sr_name\":\"SAMPLE Rahim Uddin\",\"mobile\":\"01711000101\",\"email\":\"sample.sr@example.com\",\"address\":\"Banani, Dhaka\",\"designation\":\"Sales Executive\",\"territory_id\":10,\"joining_date\":\"2026-11-01\",\"commission_basis\":\"none\",\"commission_rate\":2,\"target_bonus_rate\":1,\"is_active\":1}}','2e881e0f3ba8','c9c290e887cfbe70'),(35,'2026-11-02 21:59:21','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales_person/sr_list.php','','','Form sent: sr_list.php','{\"fields\":{\"csrf_token\":\"***\",\"sr_id\":\"0\",\"sr_code\":\"SMP-SR1\",\"sr_name\":\"SAMPLE Rahim Uddin\",\"mobile\":\"01711000101\",\"email\":\"sample.sr@example.com\",\"designation\":\"Sales Executive\",\"territory_id\":\"10\",\"joining_date\":\"2026-11-01\",\"address\":\"Banani, Dhaka\",\"commission_basis\":\"none\",\"commission_rate\":\"2\",\"target_bonus_rate\":\"1\",\"is_active\":\"1\"},\"files\":[]}','2e881e0f3ba8','c9c290e887cfbe70'),(36,'2026-11-02 21:59:24','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_info.php','traders','28','Added to traders #28','{\"added\":{\"category_id\":1,\"group_id\":null,\"trader_name\":\"SAMPLE Tech Distributors Ltd\",\"document\":\"\",\"referrer_name\":\"\",\"address\":\"Motijheel, Dhaka\",\"mobile\":\"01811000201\",\"secondary_mobile\":\"\",\"phone\":\"\",\"fax\":\"\",\"email\":\"sample.supplier@example.com\",\"web\":\"\",\"contact_person\":\"Karim\",\"owner_name\":\"Abdul Karim\",\"opening_balance\":0,\"security_type\":\"\",\"bg_cheque_no\":\"\",\"bin\":\"000111222-0101\",\"nid\":\"\",\"is_active\":1,\"created_by\":63}}','2e881e0f3ba8','40f7e654e7211f89'),(37,'2026-11-02 21:59:24','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_info.php','traders','','Update on traders','{\"statement\":\"UPDATE traders t JOIN trader_categories c ON c.category_id=t.category_id SET t.trader_code=CONCAT(c.code_prefix, LPAD(t.trader_id, 6, \'0\')) WHERE t.trader_id=?\"}','2e881e0f3ba8','40f7e654e7211f89'),(38,'2026-11-02 21:59:24','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_info.php','rma_suppliers','10','Added to rma_suppliers #10','{\"added\":{\"supplier_group\":\"\",\"supplier_name\":\"SAMPLE Tech Distributors Ltd\",\"mobile\":\"01811000201\",\"attention\":\"Karim\",\"address\":\"Motijheel, Dhaka\",\"is_active\":1}}','2e881e0f3ba8','40f7e654e7211f89'),(39,'2026-11-02 21:59:24','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_info.php','traders','28','Updated traders #28: rma_supplier_id','{\"changes\":{\"rma_supplier_id\":[null,10]},\"rows\":1}','2e881e0f3ba8','40f7e654e7211f89'),(40,'2026-11-02 21:59:24','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_info.php','','','Form sent: trader_info.php (add)','{\"fields\":{\"csrf_token\":\"***\",\"cat\":\"1\",\"trader_id\":\"0\",\"group_name\":\"\",\"trader_name\":\"SAMPLE Tech Distributors Ltd\",\"document\":\"\",\"referrer_name\":\"\",\"address\":\"Motijheel, Dhaka\",\"mobile\":\"01811000201\",\"secondary_mobile\":\"\",\"phone\":\"\",\"fax\":\"\",\"email\":\"sample.supplier@example.com\",\"web\":\"\",\"contact_person\":\"Karim\",\"owner_name\":\"Abdul Karim\",\"opening_balance\":\"0\",\"security_type\":\"\",\"bg_cheque_no\":\"\",\"bin\":\"000111222-0101\",\"nid\":\"\",\"is_active\":\"1\",\"action\":\"add\"},\"files\":[]}','2e881e0f3ba8','40f7e654e7211f89'),(41,'2026-11-02 21:59:27','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_info.php','traders','29','Added to traders #29','{\"added\":{\"category_id\":3,\"group_id\":null,\"trader_name\":\"SAMPLE Corporate Buyer Ltd\",\"document\":\"\",\"referrer_name\":\"\",\"address\":\"Gulshan 1, Dhaka\",\"mobile\":\"01911000301\",\"secondary_mobile\":\"\",\"phone\":\"\",\"fax\":\"\",\"email\":\"sample.buyer@example.com\",\"web\":\"\",\"contact_person\":\"Nasrin\",\"owner_name\":\"\",\"opening_balance\":0,\"security_type\":\"\",\"bg_cheque_no\":\"\",\"bin\":\"\",\"nid\":\"\",\"is_active\":1,\"created_by\":63}}','2e881e0f3ba8','b9e98bb73403b970'),(42,'2026-11-02 21:59:27','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_info.php','traders','','Update on traders','{\"statement\":\"UPDATE traders t JOIN trader_categories c ON c.category_id=t.category_id SET t.trader_code=CONCAT(c.code_prefix, LPAD(t.trader_id, 6, \'0\')) WHERE t.trader_id=?\"}','2e881e0f3ba8','b9e98bb73403b970'),(43,'2026-11-02 21:59:27','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_info.php','sales_customers','93','Added to sales_customers #93','{\"added\":{\"customer_code\":\"CRC000029\",\"customer_group\":\"\",\"customer_name\":\"SAMPLE Corporate Buyer Ltd\",\"mobile\":\"01911000301\",\"attention\":\"Nasrin\",\"address\":\"Gulshan 1, Dhaka\",\"bin\":\"\",\"nid\":\"\"}}','2e881e0f3ba8','b9e98bb73403b970'),(44,'2026-11-02 21:59:27','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_info.php','traders','29','Updated traders #29: sales_customer_id','{\"changes\":{\"sales_customer_id\":[null,93]},\"rows\":1}','2e881e0f3ba8','b9e98bb73403b970'),(45,'2026-11-02 21:59:27','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_info.php','','','Form sent: trader_info.php (add)','{\"fields\":{\"csrf_token\":\"***\",\"cat\":\"3\",\"trader_id\":\"0\",\"group_name\":\"\",\"trader_name\":\"SAMPLE Corporate Buyer Ltd\",\"document\":\"\",\"referrer_name\":\"\",\"address\":\"Gulshan 1, Dhaka\",\"mobile\":\"01911000301\",\"secondary_mobile\":\"\",\"phone\":\"\",\"fax\":\"\",\"email\":\"sample.buyer@example.com\",\"web\":\"\",\"contact_person\":\"Nasrin\",\"owner_name\":\"\",\"opening_balance\":\"0\",\"security_type\":\"\",\"bg_cheque_no\":\"\",\"bin\":\"\",\"nid\":\"\",\"is_active\":\"1\",\"action\":\"add\"},\"files\":[]}','2e881e0f3ba8','b9e98bb73403b970'),(46,'2026-11-02 21:59:29','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/customer/customer_profile.php','traders','30','Added to traders #30','{\"added\":{\"category_id\":2,\"group_id\":null,\"trader_name\":\"SAMPLE Retail Customer\",\"mobile\":\"01611000401\",\"secondary_mobile\":\"\",\"phone\":\"\",\"email\":\"sample.retail@example.com\",\"contact_person\":\"\",\"owner_name\":\"\",\"address\":\"Mirpur 10, Dhaka\",\"bin\":\"\",\"nid\":\"\",\"opening_balance\":0,\"referrer_name\":\"\",\"is_active\":1,\"sales_customer_id\":null,\"created_by\":63}}','2e881e0f3ba8','406a01f08d6f6a3c'),(47,'2026-11-02 21:59:29','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/customer/customer_profile.php','traders','','Update on traders','{\"statement\":\"UPDATE traders t JOIN trader_categories c ON c.category_id=t.category_id SET t.trader_code=CONCAT(c.code_prefix, LPAD(t.trader_id, 6, \'0\')) WHERE t.trader_id=?\"}','2e881e0f3ba8','406a01f08d6f6a3c'),(48,'2026-11-02 21:59:29','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/customer/customer_profile.php','sales_customers','94','Added to sales_customers #94','{\"added\":{\"customer_code\":\"DLR000030\",\"customer_group\":\"\",\"customer_name\":\"SAMPLE Retail Customer\",\"mobile\":\"01611000401\",\"attention\":\"\",\"address\":\"Mirpur 10, Dhaka\",\"bin\":\"\",\"nid\":\"\"}}','2e881e0f3ba8','406a01f08d6f6a3c'),(49,'2026-11-02 21:59:29','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/customer/customer_profile.php','traders','30','Updated traders #30: sales_customer_id','{\"changes\":{\"sales_customer_id\":[null,94]},\"rows\":1}','2e881e0f3ba8','406a01f08d6f6a3c'),(50,'2026-11-02 21:59:29','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/customer/customer_profile.php','trader_sr_assignments','19','Added to trader_sr_assignments #19','{\"added\":{\"trader_id\":30,\"sr_id\":23,\"effective_from\":{\"expression\":\"CURDATE()\"},\"assigned_by\":63}}','2e881e0f3ba8','406a01f08d6f6a3c'),(51,'2026-11-02 21:59:29','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/customer/customer_profile.php','trader_flags','','Added to trader_flags','{\"added\":{\"trader_id\":30,\"allow_credit\":1,\"credit_limit\":50000,\"credit_days\":30,\"price_level\":\"retail\",\"special_discount_pct\":0,\"block_sales\":0,\"flag_note\":\"\",\"updated_by\":63}}','2e881e0f3ba8','406a01f08d6f6a3c'),(52,'2026-11-02 21:59:30','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/customer/customer_profile.php','','','Form sent: customer_profile.php','{\"fields\":{\"csrf_token\":\"***\",\"id\":\"0\",\"category_id\":\"2\",\"group_name\":\"\",\"trader_name\":\"SAMPLE Retail Customer\",\"mobile\":\"01611000401\",\"secondary_mobile\":\"\",\"phone\":\"\",\"email\":\"sample.retail@example.com\",\"contact_person\":\"\",\"owner_name\":\"\",\"address\":\"Mirpur 10, Dhaka\",\"bin\":\"\",\"nid\":\"\",\"referrer_name\":\"\",\"opening_balance\":\"0\",\"sr_id\":\"23\",\"is_active\":\"1\",\"credit_form\":\"1\",\"allow_credit\":\"1\",\"credit_limit\":\"50000\",\"credit_days\":\"30\",\"price_level\":\"retail\",\"special_discount_pct\":\"0\",\"flag_note\":\"\"},\"files\":[]}','2e881e0f3ba8','406a01f08d6f6a3c'),(53,'2026-11-02 21:59:44','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'2e881e0f3ba8','138104fa70ac4296'),(54,'2026-11-02 22:00:39','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'2e881e0f3ba8','2d46713eeb7cea18'),(55,'2026-11-02 22:00:42','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase_order.php','inv_sequences','','Added to inv_sequences','{\"added\":{\"doc_code\":\"POR\",\"seq_year\":2026,\"last_number\":\"1\"}}','31b238f12f99','955a341801c2b2be'),(56,'2026-11-02 22:00:42','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase_order.php','inv_docs','409','Added to inv_docs #409','{\"added\":{\"ref_no\":\"SMP-PO-1\",\"party_group\":\"\",\"party_name\":\"SAMPLE Tech Distributors Ltd\",\"mobile\":\"01811000201\",\"address\":\"Motijheel, Dhaka\",\"attention\":\"\",\"sales_person\":\"\",\"delivery_point\":\"\",\"narration\":\"SAMPLE purchase order\",\"payment_term\":\"\",\"doc_date\":\"2026-11-02\",\"party_id\":28,\"bill_type\":\"Credit\",\"required_date\":\"2026-11-10\",\"source_type\":\"\",\"source_doc_id\":null,\"source_no\":\"\",\"subtotal\":262000,\"extra_charges\":0,\"discount_amount\":0,\"vat_amount\":0,\"net_amount\":262000,\"cash_received\":0,\"total_quantity\":30,\"doc_type\":\"purchase_order\",\"doc_no\":\"DESPOR2026000006\",\"branch_id\":1,\"party_type\":\"supplier\",\"created_by\":63}}','31b238f12f99','955a341801c2b2be'),(57,'2026-11-02 22:00:42','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase_order.php','inv_doc_lines','585','Added to inv_doc_lines #585','{\"added\":{\"doc_id\":409,\"line_no\":1,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"\",\"store_name\":\"\",\"store_to\":\"\",\"quantity\":10,\"free_quantity\":0,\"unit_price\":21000,\"prev_price\":0,\"discount_amount\":0,\"line_total\":210000,\"narration\":\"\",\"serials\":\"\",\"source_kind\":\"\",\"source_line_id\":null}}','31b238f12f99','955a341801c2b2be'),(58,'2026-11-02 22:00:42','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase_order.php','inv_doc_lines','586','Added to inv_doc_lines #586','{\"added\":{\"doc_id\":409,\"line_no\":2,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"\",\"store_name\":\"\",\"store_to\":\"\",\"quantity\":20,\"free_quantity\":0,\"unit_price\":2600,\"prev_price\":0,\"discount_amount\":0,\"line_total\":52000,\"narration\":\"\",\"serials\":\"\",\"source_kind\":\"\",\"source_line_id\":null}}','31b238f12f99','955a341801c2b2be'),(59,'2026-11-02 22:00:42','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase_order.php','','','Form sent: purchase_order.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"doc_id\":\"0\",\"lines_json\":\"[{\\\"product_id\\\":86,\\\"product_code\\\":\\\"SMP-001\\\",\\\"product_group\\\":\\\"SAMPLE Smartphone\\\",\\\"brand\\\":\\\"APC\\\",\\\"model_caption\\\":\\\"SAMPLE X10\\\",\\\"description\\\":\\\"SAMPLE X10 Smartphone 8/128GB\\\",\\\"unit_name\\\":\\\"SAMPLE Box\\\",\\\"warran\",\"party_id\":\"28\",\"ref_no\":\"SMP-PO-1\",\"doc_date\":\"2026-11-02\",\"mobile\":\"01811000201\",\"bill_type\":\"Credit\",\"party_group\":\"\",\"party_name\":\"SAMPLE Tech Distributors Ltd\",\"address\":\"Motijheel, Dhaka\",\"required_date\":\"2026-11-10\",\"narration\":\"SAMPLE purchase order\",\"source_no\":\"\",\"payment_term\":\"\",\"action\":\"save\",\"extra_charges\":\"0\",\"discount_amount\":\"0\",\"vat_amount\":\"0\"},\"files\":[]}','31b238f12f99','955a341801c2b2be'),(60,'2026-11-02 22:00:45','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase.php','inv_sequences','','Added to inv_sequences','{\"added\":{\"doc_code\":\"PUR\",\"seq_year\":2026,\"last_number\":\"1\"}}','31b238f12f99','25532c899dae69fd'),(61,'2026-11-02 22:00:45','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase.php','inv_docs','410','Added to inv_docs #410','{\"added\":{\"ref_no\":\"SMP-PUR-1\",\"party_group\":\"\",\"party_name\":\"SAMPLE Tech Distributors Ltd\",\"mobile\":\"01811000201\",\"address\":\"Motijheel, Dhaka\",\"attention\":\"\",\"sales_person\":\"\",\"delivery_point\":\"\",\"narration\":\"SAMPLE purchase, stock in\",\"payment_term\":\"\",\"doc_date\":\"2026-11-02\",\"party_id\":28,\"bill_type\":\"Credit\",\"required_date\":null,\"source_type\":\"\",\"source_doc_id\":null,\"source_no\":\"\",\"subtotal\":262000,\"extra_charges\":0,\"discount_amount\":0,\"vat_amount\":0,\"net_amount\":262000,\"cash_received\":0,\"total_quantity\":30,\"order_date\":null,\"doc_type\":\"purchase\",\"doc_no\":\"DESPUR2026000011\",\"branch_id\":1,\"party_type\":\"supplier\",\"created_by\":63}}','31b238f12f99','25532c899dae69fd'),(62,'2026-11-02 22:00:45','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase.php','inv_doc_lines','587','Added to inv_doc_lines #587','{\"added\":{\"doc_id\":410,\"line_no\":1,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"\",\"store_name\":\"Main Store\",\"store_to\":\"\",\"quantity\":10,\"free_quantity\":0,\"unit_price\":21000,\"prev_price\":0,\"discount_amount\":0,\"line_total\":210000,\"narration\":\"\",\"serials\":\"\",\"source_kind\":\"\",\"source_line_id\":null}}','31b238f12f99','25532c899dae69fd'),(63,'2026-11-02 22:00:45','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase.php','inv_doc_lines','588','Added to inv_doc_lines #588','{\"added\":{\"doc_id\":410,\"line_no\":2,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"\",\"store_name\":\"Main Store\",\"store_to\":\"\",\"quantity\":20,\"free_quantity\":0,\"unit_price\":2600,\"prev_price\":0,\"discount_amount\":0,\"line_total\":52000,\"narration\":\"\",\"serials\":\"\",\"source_kind\":\"\",\"source_line_id\":null}}','31b238f12f99','25532c899dae69fd'),(64,'2026-11-02 22:00:45','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase.php','inv_stock_moves','471','Added to inv_stock_moves #471','{\"added\":{\"product_id\":86,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":10,\"qty_out\":0,\"unit_cost\":21000,\"doc_type\":\"purchase\",\"doc_id\":410,\"doc_no\":\"DESPUR2026000011\",\"line_id\":587}}','31b238f12f99','25532c899dae69fd'),(65,'2026-11-02 22:00:45','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase.php','inv_stock_moves','472','Added to inv_stock_moves #472','{\"added\":{\"product_id\":87,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":20,\"qty_out\":0,\"unit_cost\":2600,\"doc_type\":\"purchase\",\"doc_id\":410,\"doc_no\":\"DESPUR2026000011\",\"line_id\":588}}','31b238f12f99','25532c899dae69fd'),(66,'2026-11-02 22:00:45','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/purchase/purchase.php','','','Form sent: purchase.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"doc_id\":\"0\",\"lines_json\":\"[{\\\"product_id\\\":86,\\\"product_code\\\":\\\"SMP-001\\\",\\\"product_group\\\":\\\"SAMPLE Smartphone\\\",\\\"brand\\\":\\\"APC\\\",\\\"model_caption\\\":\\\"SAMPLE X10\\\",\\\"description\\\":\\\"SAMPLE X10 Smartphone 8/128GB\\\",\\\"unit_name\\\":\\\"SAMPLE Box\\\",\\\"warran\",\"party_id\":\"28\",\"ref_no\":\"SMP-PUR-1\",\"doc_date\":\"2026-11-02\",\"mobile\":\"01811000201\",\"bill_type\":\"Credit\",\"party_group\":\"\",\"party_name\":\"SAMPLE Tech Distributors Ltd\",\"address\":\"Motijheel, Dhaka\",\"order_date\":\"\",\"narration\":\"SAMPLE purchase, stock in\",\"source_no\":\"\",\"sq\":\"none\",\"action\":\"save\",\"cash_received\":\"0\",\"extra_charges\":\"0\",\"discount_amount\":\"0\",\"vat_amount\":\"0\"},\"files\":[]}','31b238f12f99','25532c899dae69fd'),(67,'2026-11-02 22:00:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/amedment/stock_amendment_in.php','inv_sequences','','Added to inv_sequences','{\"added\":{\"doc_code\":\"SAI\",\"seq_year\":2026,\"last_number\":\"1\"}}','31b238f12f99','971f81925623a7d6'),(68,'2026-11-02 22:00:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/amedment/stock_amendment_in.php','inv_docs','411','Added to inv_docs #411','{\"added\":{\"ref_no\":\"SMP-AMD-1\",\"party_group\":\"\",\"party_name\":\"\",\"mobile\":\"\",\"address\":\"\",\"attention\":\"\",\"sales_person\":\"\",\"delivery_point\":\"\",\"narration\":\"SAMPLE opening stock\",\"payment_term\":\"\",\"doc_date\":\"2026-11-02\",\"party_id\":null,\"bill_type\":\"Credit\",\"required_date\":null,\"source_type\":\"\",\"source_doc_id\":null,\"source_no\":\"\",\"subtotal\":13000,\"extra_charges\":0,\"discount_amount\":0,\"vat_amount\":0,\"net_amount\":13000,\"cash_received\":0,\"total_quantity\":5,\"doc_type\":\"stock_in\",\"doc_no\":\"DESSAI2026000003\",\"branch_id\":1,\"party_type\":\"none\",\"created_by\":63}}','31b238f12f99','971f81925623a7d6'),(69,'2026-11-02 22:00:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/amedment/stock_amendment_in.php','inv_doc_lines','589','Added to inv_doc_lines #589','{\"added\":{\"doc_id\":411,\"line_no\":1,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"\",\"store_name\":\"Main Store\",\"store_to\":\"\",\"quantity\":5,\"free_quantity\":0,\"unit_price\":2600,\"prev_price\":0,\"discount_amount\":0,\"line_total\":13000,\"narration\":\"\",\"serials\":\"\",\"source_kind\":\"\",\"source_line_id\":null}}','31b238f12f99','971f81925623a7d6'),(70,'2026-11-02 22:00:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/amedment/stock_amendment_in.php','inv_stock_moves','473','Added to inv_stock_moves #473','{\"added\":{\"product_id\":87,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":5,\"qty_out\":0,\"unit_cost\":2600,\"doc_type\":\"stock_in\",\"doc_id\":411,\"doc_no\":\"DESSAI2026000003\",\"line_id\":589}}','31b238f12f99','971f81925623a7d6'),(71,'2026-11-02 22:00:48','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/amedment/stock_amendment_in.php','','','Form sent: stock_amendment_in.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"doc_id\":\"0\",\"lines_json\":\"[{\\\"product_id\\\":87,\\\"product_code\\\":\\\"SMP-002\\\",\\\"product_group\\\":\\\"SAMPLE Smartphone\\\",\\\"brand\\\":\\\"APC\\\",\\\"model_caption\\\":\\\"SAMPLE Buds\\\",\\\"description\\\":\\\"SAMPLE Buds Wireless Earphone\\\",\\\"unit_name\\\":\\\"SAMPLE Box\\\",\\\"warra\",\"party_id\":\"\",\"ref_no\":\"SMP-AMD-1\",\"doc_date\":\"2026-11-02\",\"party_group\":\"\",\"party_name\":\"\",\"narration\":\"SAMPLE opening stock\",\"sq\":\"none\",\"action\":\"save\"},\"files\":[]}','31b238f12f99','971f81925623a7d6'),(72,'2026-11-02 22:00:50','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/transfer/stock_transfer.php','inv_sequences','','Added to inv_sequences','{\"added\":{\"doc_code\":\"STT\",\"seq_year\":2026,\"last_number\":\"1\"}}','31b238f12f99','a8a41b11049a65ac'),(73,'2026-11-02 22:00:50','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/transfer/stock_transfer.php','inv_docs','412','Added to inv_docs #412','{\"added\":{\"ref_no\":\"SMP-TRF-1\",\"party_group\":\"\",\"party_name\":\"\",\"mobile\":\"\",\"address\":\"\",\"attention\":\"\",\"sales_person\":\"\",\"delivery_point\":\"\",\"narration\":\"SAMPLE transfer\",\"payment_term\":\"\",\"doc_date\":\"2026-11-02\",\"party_id\":null,\"bill_type\":\"Credit\",\"required_date\":null,\"source_type\":\"\",\"source_doc_id\":null,\"source_no\":\"\",\"subtotal\":0,\"extra_charges\":0,\"discount_amount\":0,\"vat_amount\":0,\"net_amount\":0,\"cash_received\":0,\"total_quantity\":2,\"doc_type\":\"stock_transfer\",\"doc_no\":\"DESSTT2026000005\",\"branch_id\":1,\"party_type\":\"none\",\"created_by\":63}}','31b238f12f99','a8a41b11049a65ac'),(74,'2026-11-02 22:00:50','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/transfer/stock_transfer.php','inv_doc_lines','590','Added to inv_doc_lines #590','{\"added\":{\"doc_id\":412,\"line_no\":1,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"\",\"store_name\":\"Main Store\",\"store_to\":\"RMA STORE\",\"quantity\":2,\"free_quantity\":0,\"unit_price\":0,\"prev_price\":0,\"discount_amount\":0,\"line_total\":0,\"narration\":\"\",\"serials\":\"\",\"source_kind\":\"\",\"source_line_id\":null}}','31b238f12f99','a8a41b11049a65ac'),(75,'2026-11-02 22:00:50','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/transfer/stock_transfer.php','inv_stock_moves','474','Added to inv_stock_moves #474','{\"added\":{\"product_id\":87,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":2,\"unit_cost\":0,\"doc_type\":\"stock_transfer\",\"doc_id\":412,\"doc_no\":\"DESSTT2026000005\",\"line_id\":590}}','31b238f12f99','a8a41b11049a65ac'),(76,'2026-11-02 22:00:50','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/transfer/stock_transfer.php','inv_stock_moves','475','Added to inv_stock_moves #475','{\"added\":{\"product_id\":87,\"store_name\":\"RMA STORE\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":2,\"qty_out\":0,\"unit_cost\":0,\"doc_type\":\"stock_transfer\",\"doc_id\":412,\"doc_no\":\"DESSTT2026000005\",\"line_id\":590}}','31b238f12f99','a8a41b11049a65ac'),(77,'2026-11-02 22:00:50','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/transfer/stock_transfer.php','','','Form sent: stock_transfer.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"doc_id\":\"0\",\"lines_json\":\"[{\\\"product_id\\\":87,\\\"product_code\\\":\\\"SMP-002\\\",\\\"product_group\\\":\\\"SAMPLE Smartphone\\\",\\\"brand\\\":\\\"APC\\\",\\\"model_caption\\\":\\\"SAMPLE Buds\\\",\\\"description\\\":\\\"SAMPLE Buds Wireless Earphone\\\",\\\"unit_name\\\":\\\"SAMPLE Box\\\",\\\"warra\",\"party_id\":\"\",\"ref_no\":\"SMP-TRF-1\",\"doc_date\":\"2026-11-02\",\"narration\":\"SAMPLE transfer\",\"sq\":\"none\",\"action\":\"save\"},\"files\":[]}','31b238f12f99','a8a41b11049a65ac'),(78,'2026-11-02 22:00:53','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoice_sequences','','Added to sales_invoice_sequences','{\"added\":{\"sequence_year\":2026,\"last_number\":\"1\"}}','31b238f12f99','5bc3c29efb4ef654'),(79,'2026-11-02 22:00:53','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoices','193','Added to sales_invoices #193','{\"added\":{\"ref_no\":\"SMP-INV-1\",\"customer_group\":\"\",\"customer_name\":\"SAMPLE Retail Customer\",\"mobile\":\"01611000401\",\"attention\":\"\",\"address\":\"Mirpur 10, Dhaka\",\"delivery_point\":\"\",\"sales_person\":\"SAMPLE Rahim Uddin\",\"narration\":\"SAMPLE sales invoice\",\"bin\":\"\",\"nid\":\"\",\"create_installation\":0,\"additional_info\":null,\"sales_date\":\"2026-11-02\",\"bill_type\":\"Cash\",\"customer_id\":94,\"member_id\":null,\"subtotal\":32000,\"extra_charges\":0,\"discount_amount\":0,\"vat_amount\":0,\"ait_amount\":0,\"exchange_amount\":0,\"point_adjust\":0,\"points_redeemed\":0,\"points_earned\":0,\"net_amount\":32000,\"total_quantity\":3,\"invoice_no\":\"SI2026000076\",\"branch_id\":1,\"created_by\":63}}','31b238f12f99','5bc3c29efb4ef654'),(80,'2026-11-02 22:00:53','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoice_items','246','Added to sales_invoice_items #246','{\"added\":{\"invoice_id\":193,\"line_number\":1,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"product_narration\":\"\",\"warranty\":\"SAMPLE 1 Year\",\"store_name\":\"Main Store\",\"unit_name\":\"SAMPLE Box\",\"quantity\":1,\"free_quantity\":0,\"unit_price\":25000,\"discount_amount\":0,\"line_total\":25000,\"serial_imei\":\"\",\"sequence_type\":\"not_available\",\"unit_cost\":21000}}','31b238f12f99','5bc3c29efb4ef654'),(81,'2026-11-02 22:00:53','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoice_items','247','Added to sales_invoice_items #247','{\"added\":{\"invoice_id\":193,\"line_number\":2,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"product_narration\":\"\",\"warranty\":\"SAMPLE 1 Year\",\"store_name\":\"Main Store\",\"unit_name\":\"SAMPLE Box\",\"quantity\":2,\"free_quantity\":0,\"unit_price\":3500,\"discount_amount\":0,\"line_total\":7000,\"serial_imei\":\"\",\"sequence_type\":\"not_available\",\"unit_cost\":2600}}','31b238f12f99','5bc3c29efb4ef654'),(82,'2026-11-02 22:00:53','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoice_payments','180','Added to sales_invoice_payments #180','{\"added\":{\"invoice_id\":193,\"payment_method\":\"cash\",\"amount\":32000,\"reference\":\"Cash at sale\",\"received_by\":63}}','31b238f12f99','5bc3c29efb4ef654'),(83,'2026-11-02 22:00:53','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoices','','Update on sales_invoices','{\"statement\":\"UPDATE sales_invoices v SET cash_received=(SELECT COALESCE(SUM(amount), 0) FROM sales_invoice_payments p WHERE p.invoice_id=v.invoice_id), balance_amount=GREATEST(0, net_amount - cash_received - (SELECT COALESCE(SUM(due_adjusted), 0) FROM sales_returns r WHERE r.invoice_id=v.invoice_id AND r.status=\"}','31b238f12f99','5bc3c29efb4ef654'),(84,'2026-11-02 22:00:53','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoices','','Update on sales_invoices','{\"statement\":\"UPDATE sales_invoices t LEFT JOIN sales_representatives s ON s.sr_name=t.sales_person AND t.sales_person<>\'\' SET t.sr_id=s.sr_id WHERE t.invoice_id=?\"}','31b238f12f99','5bc3c29efb4ef654'),(85,'2026-11-02 22:00:53','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','inv_docs','413','Added to inv_docs #413','{\"added\":{\"doc_type\":\"sales_invoice\",\"doc_no\":\"SI2026000076\",\"doc_date\":\"2026-11-02\",\"ref_no\":\"SMP-INV-1\",\"branch_id\":1,\"party_type\":\"customer\",\"party_id\":94,\"party_group\":\"\",\"party_name\":\"SAMPLE Retail Customer\",\"mobile\":\"01611000401\",\"sales_invoice_id\":193,\"net_amount\":32000,\"total_quantity\":3,\"approval_status\":\"pending\",\"created_by\":63}}','31b238f12f99','5bc3c29efb4ef654'),(86,'2026-11-02 22:00:53','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','inv_doc_lines','591','Added to inv_doc_lines #591','{\"added\":{\"doc_id\":413,\"line_no\":1,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"unit_name\":\"SAMPLE Box\",\"store_name\":\"Main Store\",\"quantity\":1,\"free_quantity\":0,\"unit_price\":25000,\"line_total\":25000,\"serials\":\"\"}}','31b238f12f99','5bc3c29efb4ef654'),(87,'2026-11-02 22:00:53','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','inv_doc_lines','592','Added to inv_doc_lines #592','{\"added\":{\"doc_id\":413,\"line_no\":2,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"store_name\":\"Main Store\",\"quantity\":2,\"free_quantity\":0,\"unit_price\":3500,\"line_total\":7000,\"serials\":\"\"}}','31b238f12f99','5bc3c29efb4ef654'),(88,'2026-11-02 22:00:53','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','inv_stock_moves','476','Added to inv_stock_moves #476','{\"added\":{\"product_id\":86,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":1,\"unit_cost\":25000,\"doc_type\":\"sales_invoice\",\"doc_id\":413,\"doc_no\":\"SI2026000076\",\"line_id\":591}}','31b238f12f99','5bc3c29efb4ef654'),(89,'2026-11-02 22:00:53','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','inv_stock_moves','477','Added to inv_stock_moves #477','{\"added\":{\"product_id\":87,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":2,\"unit_cost\":3500,\"doc_type\":\"sales_invoice\",\"doc_id\":413,\"doc_no\":\"SI2026000076\",\"line_id\":592}}','31b238f12f99','5bc3c29efb4ef654'),(90,'2026-11-02 22:00:53','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoices','193','Updated sales_invoices #193: stock_doc_id','{\"changes\":{\"stock_doc_id\":[null,413]},\"rows\":1}','31b238f12f99','5bc3c29efb4ef654'),(91,'2026-11-02 22:00:53','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','','','Form sent: sales_invoice.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"action\":\"save\",\"invoice_id\":\"0\",\"customer_id\":\"94\",\"items_json\":\"[{\\\"item_id\\\":0,\\\"product_id\\\":86,\\\"product_code\\\":\\\"SMP-001\\\",\\\"product_group\\\":\\\"SAMPLE Smartphone\\\",\\\"brand\\\":\\\"APC\\\",\\\"model_caption\\\":\\\"SAMPLE X10\\\",\\\"description\\\":\\\"SAMPLE X10 Smartphone 8/128GB\\\",\\\"product_narration\\\":\",\"schedules_json\":\"[]\",\"exchanges_json\":\"[]\",\"ref_no\":\"SMP-INV-1\",\"sales_date\":\"2026-11-02\",\"mobile\":\"01611000401\",\"bill_type\":\"Cash\",\"customer_group\":\"\",\"customer_name\":\"SAMPLE Retail Customer\",\"attention\":\"\",\"sales_person\":\"SAMPLE Rahim Uddin\",\"address\":\"Mirpur 10, Dhaka\",\"delivery_point\":\"\",\"narration\":\"SAMPLE sales invoice\",\"bin\":\"\",\"nid\":\"\",\"sq\":\"none\",\"add_delivery_date\":\"\",\"add_vehicle_no\":\"\",\"add_driver\":\"\",\"add_reference\":\"\",\"add_installation_address\":\"\",\"add_warranty_card\":\"\",\"add_remarks\":\"\",\"cash_received\":\"32000\",\"payment_method\":\"cash\",\"member_no\":\"\",\"point_adjust\":\"0\",\"extra_charges\":\"0\",\"discount_amount\":\"0\",\"vat_amount\":\"0\"},\"files\":[]}','31b238f12f99','5bc3c29efb4ef654'),(92,'2026-11-02 22:00:56','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_sequences','','Added to sales_invoice_sequences','{\"added\":{\"sequence_year\":2026,\"last_number\":\"1\"}}','31b238f12f99','b25cca0f568850bc'),(93,'2026-11-02 22:00:56','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','194','Added to sales_invoices #194','{\"added\":{\"invoice_no\":\"SI2026000077\",\"ref_no\":\"POS\",\"sales_date\":\"2026-11-02\",\"bill_type\":\"Cash\",\"customer_id\":null,\"customer_name\":\"SAMPLE Walk-in\",\"mobile\":\"01511000501\",\"address\":\"\",\"sales_person\":\"sample_admin\",\"narration\":\"POS sale. Tendered 30,000.00, change 1,500.00.\",\"subtotal\":28500,\"discount_amount\":0,\"vat_amount\":1357.14,\"net_amount\":28500,\"cash_received\":28500,\"balance_amount\":\"0\",\"total_quantity\":2,\"created_by\":63}}','31b238f12f99','b25cca0f568850bc'),(94,'2026-11-02 22:00:56','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_items','248','Added to sales_invoice_items #248','{\"added\":{\"invoice_id\":194,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"warranty\":\"SAMPLE 1 Year\",\"unit_name\":\"SAMPLE Box\",\"serial_imei\":\"\",\"quantity\":1,\"unit_price\":3500,\"discount_amount\":0,\"line_total\":3500,\"line_number\":1}}','31b238f12f99','b25cca0f568850bc'),(95,'2026-11-02 22:00:56','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_items','249','Added to sales_invoice_items #249','{\"added\":{\"invoice_id\":194,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"warranty\":\"SAMPLE 1 Year\",\"unit_name\":\"SAMPLE Box\",\"serial_imei\":\"\",\"quantity\":1,\"unit_price\":25000,\"discount_amount\":0,\"line_total\":25000,\"line_number\":2}}','31b238f12f99','b25cca0f568850bc'),(96,'2026-11-02 22:00:56','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_payments','181','Added to sales_invoice_payments #181','{\"added\":{\"invoice_id\":194,\"payment_method\":\"cash\",\"amount\":28500,\"reference\":\"\",\"received_by\":63}}','31b238f12f99','b25cca0f568850bc'),(97,'2026-11-02 22:00:56','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','194','Updated sales_invoices #194: branch_id','{\"changes\":{\"branch_id\":[null,1]},\"rows\":1}','31b238f12f99','b25cca0f568850bc'),(98,'2026-11-02 22:00:56','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_docs','414','Added to inv_docs #414','{\"added\":{\"doc_type\":\"sales_invoice\",\"doc_no\":\"SI2026000077\",\"doc_date\":\"2026-11-02\",\"ref_no\":\"POS\",\"branch_id\":1,\"party_type\":\"customer\",\"party_id\":null,\"party_group\":\"\",\"party_name\":\"SAMPLE Walk-in\",\"mobile\":\"01511000501\",\"sales_invoice_id\":194,\"net_amount\":28500,\"total_quantity\":2,\"approval_status\":\"pending\",\"created_by\":63}}','31b238f12f99','b25cca0f568850bc'),(99,'2026-11-02 22:00:56','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_doc_lines','593','Added to inv_doc_lines #593','{\"added\":{\"doc_id\":414,\"line_no\":1,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"store_name\":\"Main Store\",\"quantity\":1,\"free_quantity\":0,\"unit_price\":3500,\"line_total\":3500,\"serials\":\"\"}}','31b238f12f99','b25cca0f568850bc'),(100,'2026-11-02 22:00:56','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_doc_lines','594','Added to inv_doc_lines #594','{\"added\":{\"doc_id\":414,\"line_no\":2,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"unit_name\":\"SAMPLE Box\",\"store_name\":\"Main Store\",\"quantity\":1,\"free_quantity\":0,\"unit_price\":25000,\"line_total\":25000,\"serials\":\"\"}}','31b238f12f99','b25cca0f568850bc'),(101,'2026-11-02 22:00:56','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_stock_moves','478','Added to inv_stock_moves #478','{\"added\":{\"product_id\":87,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":1,\"unit_cost\":3500,\"doc_type\":\"sales_invoice\",\"doc_id\":414,\"doc_no\":\"SI2026000077\",\"line_id\":593}}','31b238f12f99','b25cca0f568850bc'),(102,'2026-11-02 22:00:56','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_stock_moves','479','Added to inv_stock_moves #479','{\"added\":{\"product_id\":86,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":1,\"unit_cost\":25000,\"doc_type\":\"sales_invoice\",\"doc_id\":414,\"doc_no\":\"SI2026000077\",\"line_id\":594}}','31b238f12f99','b25cca0f568850bc'),(103,'2026-11-02 22:00:56','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','194','Updated sales_invoices #194: stock_doc_id','{\"changes\":{\"stock_doc_id\":[null,414]},\"rows\":1}','31b238f12f99','b25cca0f568850bc'),(104,'2026-11-02 22:00:56','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','','','Form sent: pos_sales.php','{\"fields\":{\"csrf_token\":\"***\",\"cart_json\":\"[{\\\"product_id\\\":87,\\\"product_code\\\":\\\"SMP-002\\\",\\\"product_group\\\":\\\"SAMPLE Smartphone\\\",\\\"brand\\\":\\\"APC\\\",\\\"model_caption\\\":\\\"SAMPLE Buds\\\",\\\"description\\\":\\\"SAMPLE Buds Wireless Earphone\\\",\\\"warranty\\\":\\\"SAMPLE 1 Year\\\",\\\"uni\",\"customer_name\":\"SAMPLE Walk-in\",\"customer_id\":\"\",\"mobile\":\"01511000501\",\"discount_amount\":\"0\",\"vat_percent\":\"5\",\"payment_method\":\"cash\",\"payment_reference\":\"\",\"tendered\":\"30000\"},\"files\":[]}','31b238f12f99','b25cca0f568850bc'),(105,'2026-11-02 22:00:59','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_order/sales_order.php','sales_order_sequences','','Added to sales_order_sequences','{\"added\":{\"sequence_year\":2026,\"last_number\":\"1\"}}','31b238f12f99','41c6cb0681f41e2e'),(106,'2026-11-02 22:00:59','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_order/sales_order.php','sales_orders','67','Added to sales_orders #67','{\"added\":{\"ref_no\":\"SMP-SO-1\",\"customer_group\":\"\",\"customer_name\":\"SAMPLE Corporate Buyer Ltd\",\"mobile\":\"01911000301\",\"attention\":\"Nasrin\",\"narration\":\"SAMPLE sales order\",\"address\":\"Gulshan 1, Dhaka\",\"bin\":\"\",\"nid\":\"\",\"sales_person\":\"SAMPLE Rahim Uddin\",\"delivery_point\":\"\",\"create_installation\":0,\"additional_info\":null,\"order_date\":\"2026-11-02\",\"delivery_date\":\"2026-11-15\",\"bill_type\":\"Credit\",\"customer_id\":93,\"subtotal\":90500,\"extra_charges\":0,\"discount_amount\":0,\"vat_ait_amount\":0,\"net_amount\":90500,\"advance_received\":0,\"balance_amount\":90500,\"total_quantity\":8,\"order_no\":\"SO2026000009\",\"branch_id\":1,\"created_by\":63}}','31b238f12f99','41c6cb0681f41e2e'),(107,'2026-11-02 22:00:59','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_order/sales_order.php','sales_order_items','75','Added to sales_order_items #75','{\"added\":{\"order_id\":67,\"line_number\":1,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"warranty\":\"SAMPLE 1 Year\",\"store_name\":\"Main Store\",\"unit_name\":\"SAMPLE Box\",\"quantity\":3,\"unit_price\":24500,\"discount_amount\":0,\"line_total\":73500}}','31b238f12f99','41c6cb0681f41e2e'),(108,'2026-11-02 22:00:59','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_order/sales_order.php','sales_order_items','76','Added to sales_order_items #76','{\"added\":{\"order_id\":67,\"line_number\":2,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"warranty\":\"SAMPLE 1 Year\",\"store_name\":\"Main Store\",\"unit_name\":\"SAMPLE Box\",\"quantity\":5,\"unit_price\":3400,\"discount_amount\":0,\"line_total\":17000}}','31b238f12f99','41c6cb0681f41e2e'),(109,'2026-11-02 22:00:59','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_order/sales_order.php','sales_orders','','Update on sales_orders','{\"statement\":\"UPDATE sales_orders t LEFT JOIN sales_representatives s ON s.sr_name=t.sales_person AND t.sales_person<>\'\' SET t.sr_id=s.sr_id WHERE t.order_id=?\"}','31b238f12f99','41c6cb0681f41e2e'),(110,'2026-11-02 22:00:59','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_order/sales_order.php','','','Form sent: sales_order.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"action\":\"save\",\"order_id\":\"0\",\"customer_id\":\"93\",\"items_json\":\"[{\\\"item_id\\\":0,\\\"product_id\\\":86,\\\"product_code\\\":\\\"SMP-001\\\",\\\"product_group\\\":\\\"SAMPLE Smartphone\\\",\\\"brand\\\":\\\"APC\\\",\\\"model_caption\\\":\\\"SAMPLE X10\\\",\\\"description\\\":\\\"SAMPLE X10 Smartphone 8/128GB\\\",\\\"product_narration\\\":\",\"schedules_json\":\"[]\",\"ref_no\":\"SMP-SO-1\",\"order_date\":\"2026-11-02\",\"mobile\":\"01911000301\",\"bill_type\":\"Credit\",\"delivery_date\":\"2026-11-15\",\"attention\":\"Nasrin\",\"narration\":\"SAMPLE sales order\",\"customer_group\":\"\",\"customer_name\":\"SAMPLE Corporate Buyer Ltd\",\"address\":\"Gulshan 1, Dhaka\",\"bin\":\"\",\"nid\":\"\",\"sales_person\":\"SAMPLE Rahim Uddin\",\"delivery_point\":\"\",\"add_delivery_date\":\"\",\"add_vehicle_no\":\"\",\"add_driver\":\"\",\"add_reference\":\"\",\"add_installation_address\":\"\",\"add_warranty_card\":\"\",\"add_remarks\":\"\",\"extra_charges\":\"0\",\"discount_amount\":\"0\",\"vat_amount\":\"0\",\"advance_received\":\"0\"},\"files\":[]}','31b238f12f99','41c6cb0681f41e2e'),(111,'2026-11-02 22:01:01','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/challan/challan.php','inv_sequences','','Added to inv_sequences','{\"added\":{\"doc_code\":\"CHL\",\"seq_year\":2026,\"last_number\":\"1\"}}','31b238f12f99','217a03a810cd2549'),(112,'2026-11-02 22:01:01','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/challan/challan.php','inv_docs','415','Added to inv_docs #415','{\"added\":{\"ref_no\":\"SMP-CH-1\",\"party_group\":\"\",\"party_name\":\"SAMPLE Corporate Buyer Ltd\",\"mobile\":\"01911000301\",\"address\":\"Gulshan 1, Dhaka\",\"attention\":\"Nasrin\",\"sales_person\":\"SAMPLE Rahim Uddin\",\"delivery_point\":\"Gulshan 1 office\",\"narration\":\"SAMPLE challan\",\"payment_term\":\"\",\"doc_date\":\"2026-11-02\",\"party_id\":93,\"bill_type\":\"Credit\",\"required_date\":null,\"source_type\":\"\",\"source_doc_id\":null,\"source_no\":\"\",\"subtotal\":0,\"extra_charges\":0,\"discount_amount\":0,\"vat_amount\":0,\"net_amount\":0,\"cash_received\":0,\"total_quantity\":2,\"additional_info\":null,\"doc_type\":\"challan\",\"doc_no\":\"DESCHL2026000006\",\"branch_id\":1,\"party_type\":\"customer\",\"created_by\":63}}','31b238f12f99','217a03a810cd2549'),(113,'2026-11-02 22:01:01','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/challan/challan.php','inv_doc_lines','595','Added to inv_doc_lines #595','{\"added\":{\"doc_id\":415,\"line_no\":1,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"warranty\":\"SAMPLE 1 Year\",\"store_name\":\"Main Store\",\"store_to\":\"\",\"quantity\":2,\"free_quantity\":0,\"unit_price\":0,\"prev_price\":0,\"discount_amount\":0,\"line_total\":0,\"narration\":\"\",\"serials\":\"\",\"source_kind\":\"\",\"source_line_id\":null}}','31b238f12f99','217a03a810cd2549'),(114,'2026-11-02 22:01:01','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/challan/challan.php','inv_stock_moves','480','Added to inv_stock_moves #480','{\"added\":{\"product_id\":87,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":2,\"unit_cost\":0,\"doc_type\":\"challan\",\"doc_id\":415,\"doc_no\":\"DESCHL2026000006\",\"line_id\":595}}','31b238f12f99','217a03a810cd2549'),(115,'2026-11-02 22:01:01','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/challan/challan.php','','','Form sent: challan.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"doc_id\":\"0\",\"lines_json\":\"[{\\\"product_id\\\":87,\\\"product_code\\\":\\\"SMP-002\\\",\\\"product_group\\\":\\\"SAMPLE Smartphone\\\",\\\"brand\\\":\\\"APC\\\",\\\"model_caption\\\":\\\"SAMPLE Buds\\\",\\\"description\\\":\\\"SAMPLE Buds Wireless Earphone\\\",\\\"unit_name\\\":\\\"SAMPLE Box\\\",\\\"warra\",\"party_id\":\"93\",\"ref_no\":\"SMP-CH-1\",\"doc_date\":\"2026-11-02\",\"mobile\":\"01911000301\",\"party_group\":\"\",\"party_name\":\"SAMPLE Corporate Buyer Ltd\",\"address\":\"Gulshan 1, Dhaka\",\"attention\":\"Nasrin\",\"sales_person\":\"SAMPLE Rahim Uddin\",\"narration\":\"SAMPLE challan\",\"delivery_point\":\"Gulshan 1 office\",\"source_no\":\"\",\"sq\":\"none\",\"action\":\"save\",\"add_delivery_date\":\"\",\"add_vehicle_no\":\"\",\"add_driver\":\"\",\"add_reference\":\"\",\"add_installation_address\":\"\",\"add_warranty_card\":\"\",\"add_remarks\":\"\"},\"files\":[]}','31b238f12f99','217a03a810cd2549'),(116,'2026-11-02 22:01:30','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'2e881e0f3ba8','088bd98ee60c4784'),(117,'2026-11-02 22:01:43','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoice_sequences','','Added to sales_invoice_sequences','{\"added\":{\"sequence_year\":2026,\"last_number\":\"1\"}}','8f191f2d72dd','d02375e89595da0c'),(118,'2026-11-02 22:01:43','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoices','195','Added to sales_invoices #195','{\"added\":{\"ref_no\":\"SMP-INV-1\",\"customer_group\":\"\",\"customer_name\":\"SAMPLE Retail Customer\",\"mobile\":\"01611000401\",\"attention\":\"\",\"address\":\"Mirpur 10, Dhaka\",\"delivery_point\":\"\",\"sales_person\":\"SAMPLE Rahim Uddin\",\"narration\":\"SAMPLE sales invoice\",\"bin\":\"\",\"nid\":\"\",\"create_installation\":0,\"additional_info\":null,\"sales_date\":\"2026-11-02\",\"bill_type\":\"Cash\",\"customer_id\":94,\"member_id\":null,\"subtotal\":32000,\"extra_charges\":0,\"discount_amount\":0,\"vat_amount\":0,\"ait_amount\":0,\"exchange_amount\":0,\"point_adjust\":0,\"points_redeemed\":0,\"points_earned\":0,\"net_amount\":32000,\"total_quantity\":3,\"invoice_no\":\"SI2026000078\",\"branch_id\":1,\"created_by\":63}}','8f191f2d72dd','d02375e89595da0c'),(119,'2026-11-02 22:01:43','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoice_items','250','Added to sales_invoice_items #250','{\"added\":{\"invoice_id\":195,\"line_number\":1,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"product_narration\":\"\",\"warranty\":\"SAMPLE 1 Year\",\"store_name\":\"Main Store\",\"unit_name\":\"SAMPLE Box\",\"quantity\":1,\"free_quantity\":0,\"unit_price\":25000,\"discount_amount\":0,\"line_total\":25000,\"serial_imei\":\"\",\"sequence_type\":\"not_available\",\"unit_cost\":21000}}','8f191f2d72dd','d02375e89595da0c'),(120,'2026-11-02 22:01:43','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoice_items','251','Added to sales_invoice_items #251','{\"added\":{\"invoice_id\":195,\"line_number\":2,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"product_narration\":\"\",\"warranty\":\"SAMPLE 1 Year\",\"store_name\":\"Main Store\",\"unit_name\":\"SAMPLE Box\",\"quantity\":2,\"free_quantity\":0,\"unit_price\":3500,\"discount_amount\":0,\"line_total\":7000,\"serial_imei\":\"\",\"sequence_type\":\"not_available\",\"unit_cost\":2600}}','8f191f2d72dd','d02375e89595da0c'),(121,'2026-11-02 22:01:43','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoice_payments','182','Added to sales_invoice_payments #182','{\"added\":{\"invoice_id\":195,\"payment_method\":\"cash\",\"amount\":32000,\"reference\":\"Cash at sale\",\"received_by\":63}}','8f191f2d72dd','d02375e89595da0c'),(122,'2026-11-02 22:01:43','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoices','','Update on sales_invoices','{\"statement\":\"UPDATE sales_invoices v SET cash_received=(SELECT COALESCE(SUM(amount), 0) FROM sales_invoice_payments p WHERE p.invoice_id=v.invoice_id), balance_amount=GREATEST(0, net_amount - cash_received - (SELECT COALESCE(SUM(due_adjusted), 0) FROM sales_returns r WHERE r.invoice_id=v.invoice_id AND r.status=\"}','8f191f2d72dd','d02375e89595da0c'),(123,'2026-11-02 22:01:43','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoices','','Update on sales_invoices','{\"statement\":\"UPDATE sales_invoices t LEFT JOIN sales_representatives s ON s.sr_name=t.sales_person AND t.sales_person<>\'\' SET t.sr_id=s.sr_id WHERE t.invoice_id=?\"}','8f191f2d72dd','d02375e89595da0c'),(124,'2026-11-02 22:01:43','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','inv_docs','416','Added to inv_docs #416','{\"added\":{\"doc_type\":\"sales_invoice\",\"doc_no\":\"SI2026000078\",\"doc_date\":\"2026-11-02\",\"ref_no\":\"SMP-INV-1\",\"branch_id\":1,\"party_type\":\"customer\",\"party_id\":94,\"party_group\":\"\",\"party_name\":\"SAMPLE Retail Customer\",\"mobile\":\"01611000401\",\"sales_invoice_id\":195,\"net_amount\":32000,\"total_quantity\":3,\"approval_status\":\"pending\",\"created_by\":63}}','8f191f2d72dd','d02375e89595da0c'),(125,'2026-11-02 22:01:43','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','inv_doc_lines','596','Added to inv_doc_lines #596','{\"added\":{\"doc_id\":416,\"line_no\":1,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"unit_name\":\"SAMPLE Box\",\"store_name\":\"Main Store\",\"quantity\":1,\"free_quantity\":0,\"unit_price\":25000,\"line_total\":25000,\"serials\":\"\"}}','8f191f2d72dd','d02375e89595da0c'),(126,'2026-11-02 22:01:43','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','inv_doc_lines','597','Added to inv_doc_lines #597','{\"added\":{\"doc_id\":416,\"line_no\":2,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"store_name\":\"Main Store\",\"quantity\":2,\"free_quantity\":0,\"unit_price\":3500,\"line_total\":7000,\"serials\":\"\"}}','8f191f2d72dd','d02375e89595da0c'),(127,'2026-11-02 22:01:43','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','inv_stock_moves','481','Added to inv_stock_moves #481','{\"added\":{\"product_id\":86,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":1,\"unit_cost\":25000,\"doc_type\":\"sales_invoice\",\"doc_id\":416,\"doc_no\":\"SI2026000078\",\"line_id\":596}}','8f191f2d72dd','d02375e89595da0c'),(128,'2026-11-02 22:01:43','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','inv_stock_moves','482','Added to inv_stock_moves #482','{\"added\":{\"product_id\":87,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":2,\"unit_cost\":3500,\"doc_type\":\"sales_invoice\",\"doc_id\":416,\"doc_no\":\"SI2026000078\",\"line_id\":597}}','8f191f2d72dd','d02375e89595da0c'),(129,'2026-11-02 22:01:43','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','sales_invoices','195','Updated sales_invoices #195: stock_doc_id','{\"changes\":{\"stock_doc_id\":[null,416]},\"rows\":1}','8f191f2d72dd','d02375e89595da0c'),(130,'2026-11-02 22:01:43','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_invoice/sales_invoice.php','','','Form sent: sales_invoice.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"action\":\"save\",\"invoice_id\":\"0\",\"customer_id\":\"94\",\"items_json\":\"[{\\\"item_id\\\":0,\\\"product_id\\\":86,\\\"product_code\\\":\\\"SMP-001\\\",\\\"product_group\\\":\\\"SAMPLE Smartphone\\\",\\\"brand\\\":\\\"APC\\\",\\\"model_caption\\\":\\\"SAMPLE X10\\\",\\\"description\\\":\\\"SAMPLE X10 Smartphone 8/128GB\\\",\\\"product_narration\\\":\",\"schedules_json\":\"[]\",\"exchanges_json\":\"[]\",\"ref_no\":\"SMP-INV-1\",\"sales_date\":\"2026-11-02\",\"mobile\":\"01611000401\",\"bill_type\":\"Cash\",\"customer_group\":\"\",\"customer_name\":\"SAMPLE Retail Customer\",\"attention\":\"\",\"sales_person\":\"SAMPLE Rahim Uddin\",\"address\":\"Mirpur 10, Dhaka\",\"delivery_point\":\"\",\"narration\":\"SAMPLE sales invoice\",\"bin\":\"\",\"nid\":\"\",\"sq\":\"none\",\"add_delivery_date\":\"\",\"add_vehicle_no\":\"\",\"add_driver\":\"\",\"add_reference\":\"\",\"add_installation_address\":\"\",\"add_warranty_card\":\"\",\"add_remarks\":\"\",\"cash_received\":\"32000\",\"payment_method\":\"cash\",\"member_no\":\"\",\"point_adjust\":\"0\",\"extra_charges\":\"0\",\"discount_amount\":\"0\",\"vat_amount\":\"0\"},\"files\":[]}','8f191f2d72dd','d02375e89595da0c'),(131,'2026-11-02 22:02:11','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'2e881e0f3ba8','bc8776e448ce001a'),(132,'2026-11-02 22:02:31','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'2e881e0f3ba8','d3f8fb088ab582e2'),(133,'2026-11-02 22:02:57','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'2e881e0f3ba8','55acaaac7ef1d822'),(134,'2026-11-02 22:03:35','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'2e881e0f3ba8','d9803a870b144fa5'),(135,'2026-11-02 22:04:20','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'2e881e0f3ba8','4c07763d2dbe47cc'),(136,'2026-11-02 22:05:45','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'2e881e0f3ba8','251dab2271c3e50d'),(137,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','sales_invoice_sequences','','Added to sales_invoice_sequences','{\"added\":{\"sequence_year\":2026,\"last_number\":\"1\"}}','d61ba4f827bd','73333097c2f74bc8'),(138,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','sales_invoices','196','Added to sales_invoices #196','{\"added\":{\"invoice_no\":\"SI2026000079\",\"ref_no\":\"SMP-SOB-1\",\"sales_date\":\"2026-11-02\",\"bill_type\":\"Credit\",\"customer_id\":93,\"customer_group\":\"\",\"customer_name\":\"SAMPLE Corporate Buyer Ltd\",\"mobile\":\"01911000301\",\"attention\":\"Nasrin\",\"address\":\"Gulshan 1, Dhaka\",\"delivery_point\":\"\",\"create_installation\":0,\"bin\":\"\",\"nid\":\"\",\"sales_person\":\"SAMPLE Rahim Uddin\",\"narration\":\"SAMPLE bill from order SO2026000009\",\"subtotal\":83700,\"extra_charges\":0,\"discount_amount\":0,\"vat_amount\":0,\"ait_amount\":0,\"exchange_amount\":0,\"net_amount\":83700,\"cash_received\":50000,\"balance_amount\":33700,\"total_quantity\":6,\"created_by\":63}}','d61ba4f827bd','73333097c2f74bc8'),(139,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','sales_order_bills','9','Added to sales_order_bills #9','{\"added\":{\"order_id\":67,\"invoice_id\":196,\"advance_applied\":0,\"created_by\":63}}','d61ba4f827bd','73333097c2f74bc8'),(140,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','sales_invoice_items','252','Added to sales_invoice_items #252','{\"added\":{\"invoice_id\":196,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"product_narration\":\"\",\"warranty\":\"SAMPLE 1 Year\",\"store_name\":\"Main Store\",\"unit_name\":\"SAMPLE Box\",\"serial_imei\":\"\",\"quantity\":3,\"free_quantity\":\"0\",\"unit_price\":24500,\"discount_amount\":0,\"line_total\":73500,\"line_number\":1}}','d61ba4f827bd','73333097c2f74bc8'),(141,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','sales_order_bill_items','12','Added to sales_order_bill_items #12','{\"added\":{\"bill_id\":9,\"order_item_id\":75,\"invoice_item_id\":252,\"quantity\":3}}','d61ba4f827bd','73333097c2f74bc8'),(142,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','sales_invoice_items','253','Added to sales_invoice_items #253','{\"added\":{\"invoice_id\":196,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"product_narration\":\"\",\"warranty\":\"SAMPLE 1 Year\",\"store_name\":\"Main Store\",\"unit_name\":\"SAMPLE Box\",\"serial_imei\":\"\",\"quantity\":3,\"free_quantity\":\"0\",\"unit_price\":3400,\"discount_amount\":0,\"line_total\":10200,\"line_number\":2}}','d61ba4f827bd','73333097c2f74bc8'),(143,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','sales_order_bill_items','13','Added to sales_order_bill_items #13','{\"added\":{\"bill_id\":9,\"order_item_id\":76,\"invoice_item_id\":253,\"quantity\":3}}','d61ba4f827bd','73333097c2f74bc8'),(144,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','sales_invoice_payments','183','Added to sales_invoice_payments #183','{\"added\":{\"invoice_id\":196,\"payment_method\":\"cash\",\"amount\":50000,\"reference\":\"\",\"received_by\":63}}','d61ba4f827bd','73333097c2f74bc8'),(145,'2026-11-02 22:05:48','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','sales_invoices','196','Updated sales_invoices #196: branch_id','{\"changes\":{\"branch_id\":[null,1]},\"rows\":1}','d61ba4f827bd','73333097c2f74bc8'),(146,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','inv_docs','417','Added to inv_docs #417','{\"added\":{\"doc_type\":\"sales_invoice\",\"doc_no\":\"SI2026000079\",\"doc_date\":\"2026-11-02\",\"ref_no\":\"SMP-SOB-1\",\"branch_id\":1,\"party_type\":\"customer\",\"party_id\":93,\"party_group\":\"\",\"party_name\":\"SAMPLE Corporate Buyer Ltd\",\"mobile\":\"01911000301\",\"sales_invoice_id\":196,\"net_amount\":83700,\"total_quantity\":6,\"approval_status\":\"pending\",\"created_by\":63}}','d61ba4f827bd','73333097c2f74bc8'),(147,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','inv_doc_lines','598','Added to inv_doc_lines #598','{\"added\":{\"doc_id\":417,\"line_no\":1,\"product_id\":86,\"product_code\":\"SMP-001\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE X10\",\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"unit_name\":\"SAMPLE Box\",\"store_name\":\"Main Store\",\"quantity\":3,\"free_quantity\":0,\"unit_price\":24500,\"line_total\":73500,\"serials\":\"\"}}','d61ba4f827bd','73333097c2f74bc8'),(148,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','inv_doc_lines','599','Added to inv_doc_lines #599','{\"added\":{\"doc_id\":417,\"line_no\":2,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"store_name\":\"Main Store\",\"quantity\":3,\"free_quantity\":0,\"unit_price\":3400,\"line_total\":10200,\"serials\":\"\"}}','d61ba4f827bd','73333097c2f74bc8'),(149,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','inv_stock_moves','483','Added to inv_stock_moves #483','{\"added\":{\"product_id\":86,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":3,\"unit_cost\":24500,\"doc_type\":\"sales_invoice\",\"doc_id\":417,\"doc_no\":\"SI2026000079\",\"line_id\":598}}','d61ba4f827bd','73333097c2f74bc8'),(150,'2026-11-02 22:05:48','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','inv_stock_moves','484','Added to inv_stock_moves #484','{\"added\":{\"product_id\":87,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":0,\"qty_out\":3,\"unit_cost\":3400,\"doc_type\":\"sales_invoice\",\"doc_id\":417,\"doc_no\":\"SI2026000079\",\"line_id\":599}}','d61ba4f827bd','73333097c2f74bc8'),(151,'2026-11-02 22:05:48','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','sales_invoices','196','Updated sales_invoices #196: stock_doc_id','{\"changes\":{\"stock_doc_id\":[null,417]},\"rows\":1}','d61ba4f827bd','73333097c2f74bc8'),(152,'2026-11-02 22:05:48','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sal_bill_from_sal_order/sal_bill_from_sal_order.php','','','Form sent: sal_bill_from_sal_order.php','{\"fields\":{\"csrf_token\":\"***\",\"order_id\":\"67\",\"sales_date\":\"2026-11-02\",\"bill_type\":\"Credit\",\"ref_no\":\"SMP-SOB-1\",\"narration\":\"SAMPLE bill from order SO2026000009\",\"line\":\"(2 items)\",\"advance_apply\":\"0\",\"cash_now\":\"50000\",\"payment_method\":\"cash\",\"payment_reference\":\"\",\"extra_charges\":\"0.00\",\"discount_amount\":\"0.00\",\"vat_amount\":\"0.00\",\"ait_amount\":\"0\"},\"files\":[]}','d61ba4f827bd','73333097c2f74bc8'),(153,'2026-11-02 22:05:51','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_retrun/sales_return.php','sales_doc_sequences','','Added to sales_doc_sequences','{\"added\":{\"doc_type\":\"SR\",\"sequence_year\":2026,\"last_number\":\"1\"}}','d61ba4f827bd','519186f3b744aadc'),(154,'2026-11-02 22:05:51','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_retrun/sales_return.php','sales_returns','24','Added to sales_returns #24','{\"added\":{\"return_no\":\"SR2026000006\",\"invoice_id\":195,\"return_date\":\"2026-11-02\",\"customer_id\":94,\"customer_name\":\"SAMPLE Retail Customer\",\"mobile\":\"01611000401\",\"reason\":\"SAMPLE return: customer changed mind\",\"items_amount\":3500,\"deduction_amount\":0,\"return_amount\":3500,\"due_adjusted\":0,\"refund_amount\":3500,\"refund_method\":\"cash\",\"refund_reference\":\"\",\"total_quantity\":1,\"created_by\":63}}','d61ba4f827bd','519186f3b744aadc'),(155,'2026-11-02 22:05:51','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_retrun/sales_return.php','sales_return_items','24','Added to sales_return_items #24','{\"added\":{\"return_id\":24,\"invoice_item_id\":251,\"product_id\":87,\"product_code\":\"SMP-002\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"store_name\":\"Main Store\",\"unit_name\":\"SAMPLE Box\",\"serial_imei\":\"\",\"item_condition\":\"good\",\"quantity\":1,\"free_quantity\":0,\"unit_value\":3500,\"line_total\":3500}}','d61ba4f827bd','519186f3b744aadc'),(156,'2026-11-02 22:05:51','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_retrun/sales_return.php','inv_docs','418','Added to inv_docs #418','{\"added\":{\"doc_type\":\"sales_return\",\"doc_no\":\"SR2026000006\",\"doc_date\":\"2026-11-02\",\"ref_no\":\"\",\"branch_id\":1,\"party_type\":\"customer\",\"party_id\":94,\"party_group\":\"\",\"party_name\":\"SAMPLE Retail Customer\",\"mobile\":\"01611000401\",\"sales_invoice_id\":195,\"net_amount\":3500,\"total_quantity\":1,\"approval_status\":\"pending\",\"created_by\":63}}','d61ba4f827bd','519186f3b744aadc'),(157,'2026-11-02 22:05:51','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_retrun/sales_return.php','inv_doc_lines','600','Added to inv_doc_lines #600','{\"added\":{\"doc_id\":418,\"line_no\":1,\"product_id\":87,\"product_code\":\"SMP-002\",\"product_group\":\"SAMPLE Smartphone\",\"brand\":\"APC\",\"model_caption\":\"SAMPLE Buds\",\"description\":\"SAMPLE Buds Wireless Earphone\",\"unit_name\":\"SAMPLE Box\",\"store_name\":\"Main Store\",\"quantity\":1,\"free_quantity\":0,\"unit_price\":3500,\"line_total\":3500,\"serials\":\"\"}}','d61ba4f827bd','519186f3b744aadc'),(158,'2026-11-02 22:05:51','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_retrun/sales_return.php','inv_stock_moves','485','Added to inv_stock_moves #485','{\"added\":{\"product_id\":87,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-02\",\"qty_in\":1,\"qty_out\":0,\"unit_cost\":3500,\"doc_type\":\"sales_return\",\"doc_id\":418,\"doc_no\":\"SR2026000006\",\"line_id\":600}}','d61ba4f827bd','519186f3b744aadc'),(159,'2026-11-02 22:05:51','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_retrun/sales_return.php','sales_returns','24','Updated sales_returns #24: stock_doc_id','{\"changes\":{\"stock_doc_id\":[null,418]},\"rows\":1}','d61ba4f827bd','519186f3b744aadc'),(160,'2026-11-02 22:05:51','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/sales_retrun/sales_return.php','','','Form sent: sales_return.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"action\":\"save\",\"invoice_id\":\"195\",\"return_date\":\"2026-11-02\",\"reason\":\"SAMPLE return: customer changed mind\",\"line\":\"(2 items)\",\"refund_method\":\"cash\",\"refund_reference\":\"\",\"deduction_amount\":\"0\",\"due_adjusted\":\"0.00\"},\"files\":[]}','d61ba4f827bd','519186f3b744aadc'),(161,'2026-11-02 22:05:57','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_sequences','','Added to acc_sequences','{\"added\":{\"doc_code\":\"CRV\",\"seq_year\":2026,\"last_number\":\"1\"}}','d61ba4f827bd','799c3fd12bd32f5d'),(162,'2026-11-02 22:05:57','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_vouchers','23','Added to acc_vouchers #23','{\"added\":{\"voucher_date\":\"2026-11-02\",\"ref_no\":\"SMP-RV-1\",\"narration\":\"SAMPLE collection from customer\",\"sales_person\":\"\",\"transaction_mode\":\"Cash\",\"total_amount\":10000,\"branch_id\":1,\"to_branch_id\":null,\"is_inter_branch\":0,\"voucher_no\":\"DESCRV2026000003\",\"voucher_type\":\"credit\",\"created_by\":63}}','d61ba4f827bd','799c3fd12bd32f5d'),(163,'2026-11-02 22:05:57','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_voucher_lines','45','Added to acc_voucher_lines #45','{\"added\":{\"voucher_id\":23,\"account_id\":1,\"sub_ledger_id\":null,\"debit\":10000,\"credit\":0,\"line_narration\":\"SAMPLE collection from customer\",\"line_no\":1,\"branch_id\":1,\"transaction_mode\":\"Cash\",\"cheque_no\":\"\",\"cheque_date\":null,\"invoice_no\":\"\"}}','d61ba4f827bd','799c3fd12bd32f5d'),(164,'2026-11-02 22:05:57','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_voucher_lines','46','Added to acc_voucher_lines #46','{\"added\":{\"voucher_id\":23,\"account_id\":3,\"sub_ledger_id\":6,\"debit\":0,\"credit\":10000,\"line_narration\":\"SAMPLE collection from customer\",\"line_no\":2,\"branch_id\":1,\"transaction_mode\":\"Cash\",\"cheque_no\":\"\",\"cheque_date\":null,\"invoice_no\":\"\"}}','d61ba4f827bd','799c3fd12bd32f5d'),(165,'2026-11-02 22:05:57','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_voucher_lines','','Update on acc_voucher_lines','{\"statement\":\"UPDATE acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id=l.voucher_id SET l.is_posted=(v.status=\'posted\'), l.voucher_date=v.voucher_date, l.basis_date=COALESCE(l.cheque_date, v.cheque_date, v.voucher_date) WHERE l.voucher_id=?\"}','d61ba4f827bd','799c3fd12bd32f5d'),(166,'2026-11-02 22:05:58','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_vouchers','23','Updated acc_vouchers #23: status, posted_by, posted_at','{\"changes\":{\"status\":[\"draft\",\"posted\"],\"posted_by\":[null,63],\"posted_at\":[null,{\"expression\":\"NOW()\"}]},\"rows\":1}','d61ba4f827bd','799c3fd12bd32f5d'),(167,'2026-11-02 22:05:58','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_voucher_lines','','Update on acc_voucher_lines','{\"statement\":\"UPDATE acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id=l.voucher_id SET l.is_posted=(v.status=\'posted\'), l.voucher_date=v.voucher_date, l.basis_date=COALESCE(l.cheque_date, v.cheque_date, v.voucher_date) WHERE l.voucher_id=?\"}','d61ba4f827bd','799c3fd12bd32f5d'),(168,'2026-11-02 22:05:58','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','','','Form sent: acc_rp_voucher.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"type\":\"credit\",\"ib\":\"\",\"voucher_id\":\"0\",\"entries_json\":\"[{\\\"account_id\\\":3,\\\"sub_ledger_id\\\":6,\\\"amount\\\":10000,\\\"transaction_mode\\\":\\\"Cash\\\",\\\"cheque_no\\\":\\\"\\\",\\\"cheque_date\\\":\\\"\\\",\\\"invoice_no\\\":\\\"\\\",\\\"line_narration\\\":\\\"SAMPLE collection from customer\\\"}]\",\"ref_no\":\"SMP-RV-1\",\"voucher_date\":\"2026-11-02\",\"under_account\":\"1\",\"sales_person\":\"\",\"narration\":\"SAMPLE collection from customer\",\"action\":\"save\"},\"files\":[]}','d61ba4f827bd','799c3fd12bd32f5d'),(169,'2026-11-02 22:06:01','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_sequences','','Added to acc_sequences','{\"added\":{\"doc_code\":\"DRV\",\"seq_year\":2026,\"last_number\":\"1\"}}','d61ba4f827bd','59af3b0e2188d8b2'),(170,'2026-11-02 22:06:01','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_vouchers','24','Added to acc_vouchers #24','{\"added\":{\"voucher_date\":\"2026-11-02\",\"ref_no\":\"SMP-PV-1\",\"narration\":\"SAMPLE office expense\",\"sales_person\":\"\",\"transaction_mode\":\"Cash\",\"total_amount\":2500,\"branch_id\":1,\"to_branch_id\":null,\"is_inter_branch\":0,\"voucher_no\":\"DESDRV2026000009\",\"voucher_type\":\"debit\",\"created_by\":63}}','d61ba4f827bd','59af3b0e2188d8b2'),(171,'2026-11-02 22:06:01','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_voucher_lines','47','Added to acc_voucher_lines #47','{\"added\":{\"voucher_id\":24,\"account_id\":11,\"sub_ledger_id\":null,\"debit\":2500,\"credit\":0,\"line_narration\":\"SAMPLE office expense\",\"line_no\":1,\"branch_id\":1,\"transaction_mode\":\"Cash\",\"cheque_no\":\"\",\"cheque_date\":null,\"invoice_no\":\"\"}}','d61ba4f827bd','59af3b0e2188d8b2'),(172,'2026-11-02 22:06:01','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_voucher_lines','48','Added to acc_voucher_lines #48','{\"added\":{\"voucher_id\":24,\"account_id\":1,\"sub_ledger_id\":null,\"debit\":0,\"credit\":2500,\"line_narration\":\"SAMPLE office expense\",\"line_no\":2,\"branch_id\":1,\"transaction_mode\":\"Cash\",\"cheque_no\":\"\",\"cheque_date\":null,\"invoice_no\":\"\"}}','d61ba4f827bd','59af3b0e2188d8b2'),(173,'2026-11-02 22:06:01','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_voucher_lines','','Update on acc_voucher_lines','{\"statement\":\"UPDATE acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id=l.voucher_id SET l.is_posted=(v.status=\'posted\'), l.voucher_date=v.voucher_date, l.basis_date=COALESCE(l.cheque_date, v.cheque_date, v.voucher_date) WHERE l.voucher_id=?\"}','d61ba4f827bd','59af3b0e2188d8b2'),(174,'2026-11-02 22:06:01','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_vouchers','24','Updated acc_vouchers #24: status, posted_by, posted_at','{\"changes\":{\"status\":[\"draft\",\"posted\"],\"posted_by\":[null,63],\"posted_at\":[null,{\"expression\":\"NOW()\"}]},\"rows\":1}','d61ba4f827bd','59af3b0e2188d8b2'),(175,'2026-11-02 22:06:01','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','acc_voucher_lines','','Update on acc_voucher_lines','{\"statement\":\"UPDATE acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id=l.voucher_id SET l.is_posted=(v.status=\'posted\'), l.voucher_date=v.voucher_date, l.basis_date=COALESCE(l.cheque_date, v.cheque_date, v.voucher_date) WHERE l.voucher_id=?\"}','d61ba4f827bd','59af3b0e2188d8b2'),(176,'2026-11-02 22:06:01','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_rp_voucher.php','','','Form sent: acc_rp_voucher.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"type\":\"debit\",\"ib\":\"\",\"voucher_id\":\"0\",\"entries_json\":\"[{\\\"account_id\\\":11,\\\"sub_ledger_id\\\":0,\\\"amount\\\":2500,\\\"transaction_mode\\\":\\\"Cash\\\",\\\"cheque_no\\\":\\\"\\\",\\\"cheque_date\\\":\\\"\\\",\\\"invoice_no\\\":\\\"\\\",\\\"line_narration\\\":\\\"SAMPLE office expense\\\"}]\",\"ref_no\":\"SMP-PV-1\",\"voucher_date\":\"2026-11-02\",\"under_account\":\"1\",\"sales_person\":\"\",\"narration\":\"SAMPLE office expense\",\"action\":\"save\"},\"files\":[]}','d61ba4f827bd','59af3b0e2188d8b2'),(177,'2026-11-02 22:06:09','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/hr_setup.php','hr_departments','2','Added to hr_departments #2','{\"added\":{\"department_name\":\"SAMPLE Sales Department\",\"is_active\":1}}','d61ba4f827bd','1a0eb426f2a31dca'),(178,'2026-11-02 22:06:09','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/hr_setup.php','','','Form sent: hr_setup.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"tab\":\"department\",\"id\":\"0\",\"name\":\"SAMPLE Sales Department\",\"is_active\":\"on\",\"action\":\"save\"},\"files\":[]}','d61ba4f827bd','1a0eb426f2a31dca'),(179,'2026-11-02 22:06:12','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/hr_setup.php','hr_designations','3','Added to hr_designations #3','{\"added\":{\"designation_name\":\"SAMPLE Sales Officer\",\"is_active\":1}}','d61ba4f827bd','1659ec4c8d748cf4'),(180,'2026-11-02 22:06:12','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/hr_setup.php','','','Form sent: hr_setup.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"tab\":\"designation\",\"id\":\"0\",\"name\":\"SAMPLE Sales Officer\",\"is_active\":\"on\",\"action\":\"save\"},\"files\":[]}','d61ba4f827bd','1659ec4c8d748cf4'),(181,'2026-11-02 22:06:16','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/employees.php','hr_employees','6','Added to hr_employees #6','{\"added\":{\"full_name\":\"SAMPLE Fatema Akter\",\"father_name\":\"Abdul Hamid\",\"mother_name\":\"\",\"spouse_name\":\"\",\"blood_group\":\"\",\"religion\":\"\",\"nationality\":\"\",\"nid\":\"1990000000001\",\"passport_no\":\"***\",\"driving_license\":\"\",\"tin\":\"\",\"mobile\":\"01711000601\",\"email\":\"sample.employee@example.com\",\"present_address\":\"Dhanmondi, Dhaka\",\"permanent_address\":\"\",\"emergency_name\":\"Hamid\",\"emergency_mobile\":\"01711000602\",\"skills\":\"\",\"languages\":\"\",\"bank_name\":\"Sample Bank\",\"bank_account\":\"0011223344\",\"employee_code\":\"SMP-EMP-1\",\"date_of_birth\":\"1996-04-15\",\"gender\":\"female\",\"department_id\":2,\"designation_id\":3,\"branch_id\":11,\"reports_to\":null,\"employment_type\":\"permanent\",\"join_date\":\"2026-11-01\",\"confirm_date\":null,\"exit_date\":null,\"status\":\"onboarding\",\"sr_id\":null,\"marital_status\":\"\",\"created_by\":63}}','d61ba4f827bd','f7a735ae7346e89d'),(182,'2026-11-02 22:06:16','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/employees.php','','','Form sent: employees.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"id\":\"0\",\"employee_code\":\"SMP-EMP-1\",\"full_name\":\"SAMPLE Fatema Akter\",\"department_id\":\"2\",\"designation_id\":\"3\",\"branch_id\":\"11\",\"reports_to\":\"0\",\"employment_type\":\"permanent\",\"status\":\"onboarding\",\"join_date\":\"2026-11-01\",\"confirm_date\":\"\",\"exit_date\":\"\",\"sr_id\":\"0\",\"mobile\":\"01711000601\",\"email\":\"sample.employee@example.com\",\"date_of_birth\":\"1996-04-15\",\"gender\":\"female\",\"father_name\":\"Abdul Hamid\",\"mother_name\":\"\",\"marital_status\":\"\",\"spouse_name\":\"\",\"religion\":\"\",\"nationality\":\"\",\"blood_group\":\"\",\"nid\":\"1990000000001\",\"passport_no\":\"***\",\"driving_license\":\"\",\"tin\":\"\",\"present_address\":\"Dhanmondi, Dhaka\",\"permanent_address\":\"\",\"emergency_name\":\"Hamid\",\"emergency_mobile\":\"01711000602\",\"skills\":\"\",\"languages\":\"\",\"bank_name\":\"Sample Bank\",\"bank_account\":\"0011223344\",\"action\":\"save\"},\"files\":[]}','d61ba4f827bd','f7a735ae7346e89d'),(183,'2026-11-02 22:06:19','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/attendance.php','hr_attendance','3','Added to hr_attendance #3','{\"added\":{\"employee_id\":5,\"attendance_date\":\"2026-11-02\",\"status\":\"present\",\"check_in\":null,\"check_out\":null,\"note\":\"\",\"source\":\"office\",\"updated_by\":63}}','d61ba4f827bd','fea02e76dccaf132'),(184,'2026-11-02 22:06:19','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/attendance.php','hr_attendance','4','Added to hr_attendance #4','{\"added\":{\"employee_id\":4,\"attendance_date\":\"2026-11-02\",\"status\":\"present\",\"check_in\":null,\"check_out\":null,\"note\":\"\",\"source\":\"office\",\"updated_by\":63}}','d61ba4f827bd','fea02e76dccaf132'),(185,'2026-11-02 22:06:19','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/attendance.php','hr_attendance','5','Added to hr_attendance #5','{\"added\":{\"employee_id\":3,\"attendance_date\":\"2026-11-02\",\"status\":\"present\",\"check_in\":null,\"check_out\":null,\"note\":\"\",\"source\":\"office\",\"updated_by\":63}}','d61ba4f827bd','fea02e76dccaf132'),(186,'2026-11-02 22:06:19','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/attendance.php','hr_attendance','6','Added to hr_attendance #6','{\"added\":{\"employee_id\":1,\"attendance_date\":\"2026-11-02\",\"status\":\"present\",\"check_in\":null,\"check_out\":null,\"note\":\"\",\"source\":\"office\",\"updated_by\":63}}','d61ba4f827bd','fea02e76dccaf132'),(187,'2026-11-02 22:06:19','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/attendance.php','hr_attendance','7','Added to hr_attendance #7','{\"added\":{\"employee_id\":2,\"attendance_date\":\"2026-11-02\",\"status\":\"present\",\"check_in\":null,\"check_out\":null,\"note\":\"\",\"source\":\"office\",\"updated_by\":63}}','d61ba4f827bd','fea02e76dccaf132'),(188,'2026-11-02 22:06:19','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/hr/attendance.php','','','Form sent: attendance.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"date\":\"2026-11-02\",\"department\":\"0\",\"action\":\"save\",\"a\":\"(5 items)\"},\"files\":[]}','d61ba4f827bd','fea02e76dccaf132'),(189,'2026-11-02 22:06:23','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/rma/problem.php','rma_problems','813','Added to rma_problems #813','{\"added\":{\"problem_name\":\"SAMPLE No power\",\"is_active\":1,\"created_by\":63}}','d61ba4f827bd','b266134d337277d3'),(190,'2026-11-02 22:06:23','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/rma/problem.php','','','Form sent: problem.php (add)','{\"fields\":{\"csrf_token\":\"***\",\"action\":\"add\",\"problem_id\":\"\",\"problem_name\":\"SAMPLE No power\",\"is_active\":\"1\"},\"files\":[]}','d61ba4f827bd','b266134d337277d3'),(191,'2026-11-02 22:06:26','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/rma/complain_receive.php','rma_sequences','6','Added to rma_sequences #6','{\"added\":{\"doc_type\":\"COM\",\"seq_year\":2026,\"last_number\":\"1\"}}','d61ba4f827bd','70247e727aa929ef'),(192,'2026-11-02 22:06:26','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/rma/complain_receive.php','rma_complains','9','Added to rma_complains #9','{\"added\":{\"complain_no\":\"DESCOM2026000006\",\"ref_no\":\"SMP-RMA-1\",\"complain_date\":\"2026-11-02\",\"mobile\":\"01611000401\",\"customer_group\":\"\",\"customer_name\":\"SAMPLE Retail Customer\",\"customer_id\":null,\"address\":\"Mirpur 10, Dhaka\",\"attention\":\"\",\"created_by\":63,\"created_by_name\":\"sample_admin\"}}','d61ba4f827bd','70247e727aa929ef'),(193,'2026-11-02 22:06:26','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/rma/complain_receive.php','rma_complain_items','9','Added to rma_complain_items #9','{\"added\":{\"complain_id\":9,\"line_number\":1,\"product_group\":\"\",\"brand\":\"\",\"model_no\":\"SAMPLE X10\",\"product_id\":null,\"description\":\"SAMPLE X10 Smartphone 8/128GB\",\"sequence_type\":\"not_available\",\"under_warranty\":\"1\",\"sales_invoice_no\":\"SI2026000078\",\"sales_value\":0,\"sales_date\":null,\"sold_serial\":\"\",\"store_name\":\"RMA STORE\",\"delivery_date\":null,\"problem_name\":\"SAMPLE No power\",\"engineer_name\":\"SAMPLE Engineer\",\"no_of_time\":0,\"pre_job_ref_no\":\"\",\"narration\":\"Does not switch on\",\"qty\":1,\"received_serial\":\"SMPX10-0001\",\"amount_charges\":0,\"need_diagnosis\":\"1\"}}','d61ba4f827bd','70247e727aa929ef'),(194,'2026-11-02 22:06:26','change','insert','info','failed','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/rma/complain_receive.php','bus_admin_audit','','Added to bus_admin_audit','{\"added\":{\"actor_user_id\":\"63\",\"action_code\":\"rma_save\",\"entity_type\":\"rma_complains\",\"entity_id\":\"9\",\"details_json\":\"{\\\"doc_no\\\":\\\"DESCOM2026000006\\\"}\",\"ip_address\":\"::1\"}}','d61ba4f827bd','70247e727aa929ef'),(195,'2026-11-02 22:06:26','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/rma/complain_receive.php','','','Form sent: complain_receive.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"action\":\"save\",\"doc_id\":\"0\",\"items_json\":\"[{\\\"product_group\\\":\\\"\\\",\\\"brand\\\":\\\"\\\",\\\"model_no\\\":\\\"SAMPLE X10\\\",\\\"product_id\\\":\\\"\\\",\\\"description\\\":\\\"SAMPLE X10 Smartphone 8/128GB\\\",\\\"sequence_type\\\":\\\"not_available\\\",\\\"under_warranty\\\":\\\"1\\\",\\\"sales_invoice_no\\\":\\\"SI2026000\",\"h\":\"(9 items)\",\"i_sequence_type\":\"not_available\"},\"files\":[]}','d61ba4f827bd','70247e727aa929ef'),(196,'2026-11-02 22:06:52','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'d61ba4f827bd','a522e77076a49928'),(197,'2026-11-02 22:07:14','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'d61ba4f827bd','ab140dfbaea41702'),(198,'2026-11-02 22:07:17','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_voucher.php','acc_sequences','','Added to acc_sequences','{\"added\":{\"doc_code\":\"JUR\",\"seq_year\":2026,\"last_number\":\"1\"}}','e6ab4fd4070c','09ec9e96d142b49e'),(199,'2026-11-02 22:07:17','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_voucher.php','acc_vouchers','25','Added to acc_vouchers #25','{\"added\":{\"voucher_date\":\"2026-11-02\",\"ref_no\":\"SMP-JV-1\",\"narration\":\"SAMPLE owner paid office expense\",\"sales_person\":\"\",\"transaction_mode\":\"Adjustment\",\"cheque_no\":\"\",\"cheque_date\":null,\"receipt_category_id\":null,\"total_amount\":1200,\"branch_id\":1,\"is_inter_branch\":0,\"voucher_no\":\"DESJUR2026000003\",\"voucher_type\":\"journal\",\"created_by\":63}}','e6ab4fd4070c','09ec9e96d142b49e'),(200,'2026-11-02 22:07:17','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_voucher.php','acc_voucher_lines','49','Added to acc_voucher_lines #49','{\"added\":{\"voucher_id\":25,\"account_id\":11,\"sub_ledger_id\":null,\"debit\":1200,\"credit\":0,\"line_narration\":\"SAMPLE owner paid office expense\",\"line_no\":1,\"branch_id\":1,\"transaction_mode\":\"\",\"cheque_no\":\"\",\"cheque_date\":null,\"invoice_no\":\"\"}}','e6ab4fd4070c','09ec9e96d142b49e'),(201,'2026-11-02 22:07:17','change','insert','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_voucher.php','acc_voucher_lines','50','Added to acc_voucher_lines #50','{\"added\":{\"voucher_id\":25,\"account_id\":7,\"sub_ledger_id\":null,\"debit\":0,\"credit\":1200,\"line_narration\":\"SAMPLE owner paid office expense\",\"line_no\":2,\"branch_id\":1,\"transaction_mode\":\"\",\"cheque_no\":\"\",\"cheque_date\":null,\"invoice_no\":\"\"}}','e6ab4fd4070c','09ec9e96d142b49e'),(202,'2026-11-02 22:07:17','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_voucher.php','acc_voucher_lines','','Update on acc_voucher_lines','{\"statement\":\"UPDATE acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id=l.voucher_id SET l.is_posted=(v.status=\'posted\'), l.voucher_date=v.voucher_date, l.basis_date=COALESCE(l.cheque_date, v.cheque_date, v.voucher_date) WHERE l.voucher_id=?\"}','e6ab4fd4070c','09ec9e96d142b49e'),(203,'2026-11-02 22:07:17','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_voucher.php','acc_vouchers','25','Updated acc_vouchers #25: status, posted_by, posted_at','{\"changes\":{\"status\":[\"draft\",\"posted\"],\"posted_by\":[null,63],\"posted_at\":[null,{\"expression\":\"NOW()\"}]},\"rows\":1}','e6ab4fd4070c','09ec9e96d142b49e'),(204,'2026-11-02 22:07:17','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_voucher.php','acc_voucher_lines','','Update on acc_voucher_lines','{\"statement\":\"UPDATE acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id=l.voucher_id SET l.is_posted=(v.status=\'posted\'), l.voucher_date=v.voucher_date, l.basis_date=COALESCE(l.cheque_date, v.cheque_date, v.voucher_date) WHERE l.voucher_id=?\"}','e6ab4fd4070c','09ec9e96d142b49e'),(205,'2026-11-02 22:07:17','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/accounts_info/acc_voucher.php','','','Form sent: acc_voucher.php (save)','{\"fields\":{\"csrf_token\":\"***\",\"type\":\"journal\",\"voucher_id\":\"0\",\"lines_json\":\"[{\\\"account_id\\\":11,\\\"sub_ledger_id\\\":0,\\\"debit\\\":1200,\\\"credit\\\":0,\\\"line_narration\\\":\\\"SAMPLE owner paid office expense\\\",\\\"branch_id\\\":0,\\\"transaction_mode\\\":\\\"\\\",\\\"cheque_no\\\":\\\"\\\",\\\"cheque_date\\\":\\\"\\\",\\\"invoice_no\\\":\\\"\\\"},{\\\"a\",\"ref_no\":\"SMP-JV-1\",\"voucher_date\":\"2026-11-02\",\"sales_person\":\"\",\"narration\":\"SAMPLE owner paid office expense\",\"transaction_mode\":\"Adjustment\",\"cheque_no\":\"\",\"cheque_date\":\"\",\"action\":\"save\"},\"files\":[]}','e6ab4fd4070c','09ec9e96d142b49e'),(206,'2026-11-02 22:07:23','change','update','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/approve/approve.php','inv_docs','311','Updated inv_docs #311: approval_status, approved_by, approved_at','{\"changes\":{\"approval_status\":[\"pending\",\"approved\"],\"approved_by\":[null,63],\"approved_at\":[null,{\"expression\":\"NOW()\"}]},\"rows\":1}','e6ab4fd4070c','44f7a94cd26b871a'),(207,'2026-11-02 22:07:23','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/approve/approve.php','','','Form sent: approve.php','{\"fields\":{\"csrf_token\":\"***\",\"ids\":\"(1 items)\"},\"files\":[]}','e6ab4fd4070c','44f7a94cd26b871a'),(208,'2026-11-02 22:07:37','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'d61ba4f827bd','f4af57a66f8cd2f6'),(209,'2026-11-02 22:08:13','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'d61ba4f827bd','02dbf571626636a1'),(210,'2026-11-02 22:08:21','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/user/registration.php','','','Form sent: registration.php (add)','{\"fields\":{\"csrf_token\":\"***\",\"user_id\":\"0\",\"full_name\":\"SAMPLE Cashier\",\"user_name\":\"sample_cashier\",\"password\":\"***\",\"confirm_password\":\"***\",\"email\":\"sample.cashier@example.com\",\"mobile\":\"01711000701\",\"user_type\":\"staff\",\"sr_id\":\"0\",\"customer_id\":\"0\",\"default_branch_id\":\"11\",\"access_level\":\"Operator\",\"user_role_id\":\"11\",\"department\":\"Sales\",\"date_of_birth\":\"\",\"start_time\":\"\",\"full_time\":\"on\",\"end_time\":\"\",\"action\":\"add\"},\"files\":[]}','6af5b99d3301','1e2ae32aece4b462'),(211,'2026-11-02 22:09:09','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'d61ba4f827bd','79338a745b442da4'),(212,'2026-11-02 22:09:12','change','insert','warn','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/user/registration.php','admin_user','64','Added to admin_user #64','{\"added\":{\"user_name\":\"sample_cashier\",\"full_name\":\"SAMPLE Cashier\",\"user_password\":\"***\",\"email\":\"sample.cashier@example.com\",\"mobile\":\"01711000701\",\"access_level\":\"Operator\",\"user_type\":\"staff\",\"linked_id\":null,\"department\":\"Sales\",\"date_of_birth\":null,\"full_time\":1,\"start_time\":null,\"end_time\":null,\"is_active\":0,\"all_branches\":0,\"default_branch_id\":11,\"user_role_id\":11,\"level\":\"0\",\"show_user\":\"1\"}}','144761a1169e','28a0bf345ead783b'),(213,'2026-11-02 22:09:12','change','insert','warn','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/user/registration.php','admin_user_branches','','Added to admin_user_branches','{\"added\":{\"user_id\":64,\"branch_id\":11}}','144761a1169e','28a0bf345ead783b'),(214,'2026-11-02 22:09:12','activity','form_post','info','ok','staff',63,'sample_admin',1,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/user/registration.php','','','Form sent: registration.php (add)','{\"fields\":{\"csrf_token\":\"***\",\"user_id\":\"0\",\"full_name\":\"SAMPLE Cashier\",\"user_name\":\"sample_cashier\",\"password\":\"***\",\"confirm_password\":\"***\",\"email\":\"sample.cashier@example.com\",\"mobile\":\"01711000701\",\"user_type\":\"staff\",\"sr_id\":\"0\",\"customer_id\":\"0\",\"default_branch_id\":\"11\",\"branches\":\"(1 items)\",\"access_level\":\"Operator\",\"user_role_id\":\"11\",\"department\":\"Sales\",\"date_of_birth\":\"\",\"start_time\":\"\",\"full_time\":\"on\",\"end_time\":\"\",\"action\":\"add\"},\"files\":[]}','144761a1169e','28a0bf345ead783b'),(215,'2026-11-02 22:09:47','login','login_ok','info','ok','staff',63,'sample_admin',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: sample_admin',NULL,'d61ba4f827bd','fd9084657981bffa'),(216,'2026-11-05 21:40:09','login','login_ok','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: Administrator',NULL,'','8c4fc237fb5317df'),(217,'2026-11-05 21:52:22','login','login_ok','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/index.php','','','Signed in: Administrator',NULL,'402c07cb5635','6bf9f79550ebc582'),(218,'2026-11-05 21:52:45','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_sequences','','Added to sales_invoice_sequences','{\"added\":{\"sequence_year\":2026,\"last_number\":\"1\"}}','5a54625ac8ce','f9d20bed0b75ef64'),(219,'2026-11-05 21:52:45','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','197','Added to sales_invoices #197','{\"added\":{\"invoice_no\":\"SI2026000080\",\"ref_no\":\"POS\",\"sales_date\":\"2026-11-05\",\"bill_type\":\"Cash\",\"customer_id\":null,\"customer_name\":\"Walk-in Customer\",\"mobile\":\"\",\"address\":\"\",\"sales_person\":\"Administrator\",\"narration\":\"POS sale. Tendered 11,800.00, change 0.00.\",\"subtotal\":11800,\"discount_amount\":0,\"vat_amount\":0,\"net_amount\":11800,\"cash_received\":11800,\"balance_amount\":\"0\",\"total_quantity\":1,\"created_by\":10}}','5a54625ac8ce','f9d20bed0b75ef64'),(220,'2026-11-05 21:52:45','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_items','254','Added to sales_invoice_items #254','{\"added\":{\"invoice_id\":197,\"product_id\":80,\"product_code\":\"PWR-3002\",\"product_group\":\"UPS\",\"brand\":\"APC\",\"model_caption\":\"BX1100\",\"description\":\"1100VA UPS\",\"warranty\":\"1 Year\",\"unit_name\":\"Pcs\",\"serial_imei\":\"\",\"quantity\":1,\"unit_price\":11800,\"discount_amount\":0,\"line_total\":11800,\"line_number\":1}}','5a54625ac8ce','f9d20bed0b75ef64'),(221,'2026-11-05 21:52:45','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_payments','184','Added to sales_invoice_payments #184','{\"added\":{\"invoice_id\":197,\"payment_method\":\"cash\",\"amount\":11800,\"reference\":\"\",\"received_by\":10}}','5a54625ac8ce','f9d20bed0b75ef64'),(222,'2026-11-05 21:52:45','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','197','Updated sales_invoices #197: branch_id','{\"changes\":{\"branch_id\":[null,1]},\"rows\":1}','5a54625ac8ce','f9d20bed0b75ef64'),(223,'2026-11-05 21:52:45','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_docs','419','Added to inv_docs #419','{\"added\":{\"doc_type\":\"sales_invoice\",\"doc_no\":\"SI2026000080\",\"doc_date\":\"2026-11-05\",\"ref_no\":\"POS\",\"branch_id\":1,\"party_type\":\"customer\",\"party_id\":null,\"party_group\":\"\",\"party_name\":\"Walk-in Customer\",\"mobile\":\"\",\"sales_invoice_id\":197,\"net_amount\":11800,\"total_quantity\":1,\"approval_status\":\"pending\",\"created_by\":10}}','5a54625ac8ce','f9d20bed0b75ef64'),(224,'2026-11-05 21:52:45','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_doc_lines','601','Added to inv_doc_lines #601','{\"added\":{\"doc_id\":419,\"line_no\":1,\"product_id\":80,\"product_code\":\"PWR-3002\",\"product_group\":\"UPS\",\"brand\":\"APC\",\"model_caption\":\"BX1100\",\"description\":\"1100VA UPS\",\"unit_name\":\"Pcs\",\"store_name\":\"Main Store\",\"quantity\":1,\"free_quantity\":0,\"unit_price\":11800,\"line_total\":11800,\"serials\":\"\"}}','5a54625ac8ce','f9d20bed0b75ef64'),(225,'2026-11-05 21:52:45','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_stock_moves','486','Added to inv_stock_moves #486','{\"added\":{\"product_id\":80,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-05\",\"qty_in\":0,\"qty_out\":1,\"unit_cost\":11800,\"doc_type\":\"sales_invoice\",\"doc_id\":419,\"doc_no\":\"SI2026000080\",\"line_id\":601}}','5a54625ac8ce','f9d20bed0b75ef64'),(226,'2026-11-05 21:52:45','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','197','Updated sales_invoices #197: stock_doc_id','{\"changes\":{\"stock_doc_id\":[null,419]},\"rows\":1}','5a54625ac8ce','f9d20bed0b75ef64'),(227,'2026-11-05 21:52:45','activity','form_post','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','','','Form sent: pos_sales.php','{\"fields\":{\"csrf_token\":\"***\",\"cart_json\":\"[{\\\"product_id\\\":80,\\\"product_code\\\":\\\"PWR-3002\\\",\\\"product_group\\\":\\\"UPS\\\",\\\"brand\\\":\\\"APC\\\",\\\"model_caption\\\":\\\"BX1100\\\",\\\"description\\\":\\\"1100VA UPS\\\",\\\"warranty\\\":\\\"1 Year\\\",\\\"unit_name\\\":\\\"Pcs\\\",\\\"quantity\\\":1,\\\"unit_price\\\":1180\",\"customer_name\":\"Walk-in Customer\",\"customer_id\":\"\",\"mobile\":\"\",\"discount_amount\":\"0\",\"vat_percent\":\"0\",\"payment_method\":\"cash\",\"payment_reference\":\"\",\"tendered\":\"11800.00\"},\"files\":[]}','5a54625ac8ce','f9d20bed0b75ef64'),(228,'2026-11-05 21:53:43','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_sequences','','Added to sales_invoice_sequences','{\"added\":{\"sequence_year\":2026,\"last_number\":\"1\"}}','5a54625ac8ce','d1c9c0b4071d7c28'),(229,'2026-11-05 21:53:43','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','198','Added to sales_invoices #198','{\"added\":{\"invoice_no\":\"SI2026000081\",\"ref_no\":\"POS\",\"sales_date\":\"2026-11-05\",\"bill_type\":\"Cash\",\"customer_id\":null,\"customer_name\":\"Walk-in Customer\",\"mobile\":\"017123123213\",\"address\":\"\",\"sales_person\":\"Administrator\",\"narration\":\"POS sale. Tendered 16,890.00, change 0.00.\",\"subtotal\":16900,\"discount_amount\":10,\"vat_amount\":331.18,\"net_amount\":16890,\"cash_received\":16890,\"balance_amount\":\"0\",\"total_quantity\":1,\"created_by\":10}}','5a54625ac8ce','d1c9c0b4071d7c28'),(230,'2026-11-05 21:53:43','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_items','255','Added to sales_invoice_items #255','{\"added\":{\"invoice_id\":198,\"product_id\":76,\"product_code\":\"ACC-2004\",\"product_group\":\"Monitor\",\"brand\":\"Samsung\",\"model_caption\":\"LS24C\",\"description\":\"24 inch IPS monitor\",\"warranty\":\"1 Year\",\"unit_name\":\"Pcs\",\"serial_imei\":\"\",\"quantity\":1,\"unit_price\":16900,\"discount_amount\":0,\"line_total\":16900,\"line_number\":1}}','5a54625ac8ce','d1c9c0b4071d7c28'),(231,'2026-11-05 21:53:43','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_payments','185','Added to sales_invoice_payments #185','{\"added\":{\"invoice_id\":198,\"payment_method\":\"cash\",\"amount\":16890,\"reference\":\"erweqrw\",\"received_by\":10}}','5a54625ac8ce','d1c9c0b4071d7c28'),(232,'2026-11-05 21:53:43','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','198','Updated sales_invoices #198: branch_id','{\"changes\":{\"branch_id\":[null,1]},\"rows\":1}','5a54625ac8ce','d1c9c0b4071d7c28'),(233,'2026-11-05 21:53:43','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_docs','420','Added to inv_docs #420','{\"added\":{\"doc_type\":\"sales_invoice\",\"doc_no\":\"SI2026000081\",\"doc_date\":\"2026-11-05\",\"ref_no\":\"POS\",\"branch_id\":1,\"party_type\":\"customer\",\"party_id\":null,\"party_group\":\"\",\"party_name\":\"Walk-in Customer\",\"mobile\":\"017123123213\",\"sales_invoice_id\":198,\"net_amount\":16890,\"total_quantity\":1,\"approval_status\":\"pending\",\"created_by\":10}}','5a54625ac8ce','d1c9c0b4071d7c28'),(234,'2026-11-05 21:53:43','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_doc_lines','602','Added to inv_doc_lines #602','{\"added\":{\"doc_id\":420,\"line_no\":1,\"product_id\":76,\"product_code\":\"ACC-2004\",\"product_group\":\"Monitor\",\"brand\":\"Samsung\",\"model_caption\":\"LS24C\",\"description\":\"24 inch IPS monitor\",\"unit_name\":\"Pcs\",\"store_name\":\"Main Store\",\"quantity\":1,\"free_quantity\":0,\"unit_price\":16900,\"line_total\":16900,\"serials\":\"\"}}','5a54625ac8ce','d1c9c0b4071d7c28'),(235,'2026-11-05 21:53:43','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_stock_moves','487','Added to inv_stock_moves #487','{\"added\":{\"product_id\":76,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-05\",\"qty_in\":0,\"qty_out\":1,\"unit_cost\":16900,\"doc_type\":\"sales_invoice\",\"doc_id\":420,\"doc_no\":\"SI2026000081\",\"line_id\":602}}','5a54625ac8ce','d1c9c0b4071d7c28'),(236,'2026-11-05 21:53:43','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','198','Updated sales_invoices #198: stock_doc_id','{\"changes\":{\"stock_doc_id\":[null,420]},\"rows\":1}','5a54625ac8ce','d1c9c0b4071d7c28'),(237,'2026-11-05 21:53:43','activity','form_post','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','','','Form sent: pos_sales.php','{\"fields\":{\"csrf_token\":\"***\",\"cart_json\":\"[{\\\"product_id\\\":76,\\\"product_code\\\":\\\"ACC-2004\\\",\\\"product_group\\\":\\\"Monitor\\\",\\\"brand\\\":\\\"Samsung\\\",\\\"model_caption\\\":\\\"LS24C\\\",\\\"description\\\":\\\"24 inch IPS monitor\\\",\\\"warranty\\\":\\\"1 Year\\\",\\\"unit_name\\\":\\\"Pcs\\\",\\\"quantity\\\":1,\\\"\",\"customer_name\":\"Walk-in Customer\",\"customer_id\":\"\",\"mobile\":\"017123123213\",\"discount_amount\":\"10\",\"vat_percent\":\"2\",\"payment_method\":\"cash\",\"payment_reference\":\"erweqrw\",\"tendered\":\"16890.00\"},\"files\":[]}','5a54625ac8ce','d1c9c0b4071d7c28'),(238,'2026-11-05 21:54:53','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_sequences','','Added to sales_invoice_sequences','{\"added\":{\"sequence_year\":2026,\"last_number\":\"1\"}}','5a54625ac8ce','3c40646655f92760'),(239,'2026-11-05 21:54:53','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','199','Added to sales_invoices #199','{\"added\":{\"invoice_no\":\"SI2026000082\",\"ref_no\":\"POS\",\"sales_date\":\"2026-11-05\",\"bill_type\":\"Cash\",\"customer_id\":null,\"customer_name\":\"Walk-in Customer\",\"mobile\":\"\",\"address\":\"\",\"sales_person\":\"Administrator\",\"narration\":\"POS sale. Tendered 28,490.00, change 0.00.\",\"subtotal\":28490,\"discount_amount\":0,\"vat_amount\":0,\"net_amount\":28490,\"cash_received\":28490,\"balance_amount\":\"0\",\"total_quantity\":1,\"created_by\":10}}','5a54625ac8ce','3c40646655f92760'),(240,'2026-11-05 21:54:53','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_items','256','Added to sales_invoice_items #256','{\"added\":{\"invoice_id\":199,\"product_id\":68,\"product_code\":\"NET-1002\",\"product_group\":\"Router\",\"brand\":\"Cisco\",\"model_caption\":\"RV340\",\"description\":\"Business VPN router\",\"warranty\":\"1 Year\",\"unit_name\":\"Pcs\",\"serial_imei\":\"\",\"quantity\":1,\"unit_price\":28500,\"discount_amount\":10,\"line_total\":28490,\"line_number\":1}}','5a54625ac8ce','3c40646655f92760'),(241,'2026-11-05 21:54:53','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoice_payments','186','Added to sales_invoice_payments #186','{\"added\":{\"invoice_id\":199,\"payment_method\":\"cash\",\"amount\":28490,\"reference\":\"\",\"received_by\":10}}','5a54625ac8ce','3c40646655f92760'),(242,'2026-11-05 21:54:53','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','199','Updated sales_invoices #199: branch_id','{\"changes\":{\"branch_id\":[null,1]},\"rows\":1}','5a54625ac8ce','3c40646655f92760'),(243,'2026-11-05 21:54:53','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_docs','421','Added to inv_docs #421','{\"added\":{\"doc_type\":\"sales_invoice\",\"doc_no\":\"SI2026000082\",\"doc_date\":\"2026-11-05\",\"ref_no\":\"POS\",\"branch_id\":1,\"party_type\":\"customer\",\"party_id\":null,\"party_group\":\"\",\"party_name\":\"Walk-in Customer\",\"mobile\":\"\",\"sales_invoice_id\":199,\"net_amount\":28490,\"total_quantity\":1,\"approval_status\":\"pending\",\"created_by\":10}}','5a54625ac8ce','3c40646655f92760'),(244,'2026-11-05 21:54:53','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_doc_lines','603','Added to inv_doc_lines #603','{\"added\":{\"doc_id\":421,\"line_no\":1,\"product_id\":68,\"product_code\":\"NET-1002\",\"product_group\":\"Router\",\"brand\":\"Cisco\",\"model_caption\":\"RV340\",\"description\":\"Business VPN router\",\"unit_name\":\"Pcs\",\"store_name\":\"Main Store\",\"quantity\":1,\"free_quantity\":0,\"unit_price\":28500,\"line_total\":28500,\"serials\":\"\"}}','5a54625ac8ce','3c40646655f92760'),(245,'2026-11-05 21:54:53','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','inv_stock_moves','488','Added to inv_stock_moves #488','{\"added\":{\"product_id\":68,\"store_name\":\"Main Store\",\"branch_id\":1,\"move_date\":\"2026-11-05\",\"qty_in\":0,\"qty_out\":1,\"unit_cost\":28500,\"doc_type\":\"sales_invoice\",\"doc_id\":421,\"doc_no\":\"SI2026000082\",\"line_id\":603}}','5a54625ac8ce','3c40646655f92760'),(246,'2026-11-05 21:54:53','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','sales_invoices','199','Updated sales_invoices #199: stock_doc_id','{\"changes\":{\"stock_doc_id\":[null,421]},\"rows\":1}','5a54625ac8ce','3c40646655f92760'),(247,'2026-11-05 21:54:53','activity','form_post','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/sales/pos_sales/pos_sales.php','','','Form sent: pos_sales.php','{\"fields\":{\"csrf_token\":\"***\",\"cart_json\":\"[{\\\"product_id\\\":68,\\\"product_code\\\":\\\"NET-1002\\\",\\\"product_group\\\":\\\"Router\\\",\\\"brand\\\":\\\"Cisco\\\",\\\"model_caption\\\":\\\"RV340\\\",\\\"description\\\":\\\"Business VPN router\\\",\\\"warranty\\\":\\\"1 Year\\\",\\\"unit_name\\\":\\\"Pcs\\\",\\\"quantity\\\":1,\\\"uni\",\"customer_name\":\"Walk-in Customer\",\"customer_id\":\"\",\"mobile\":\"\",\"discount_amount\":\"0\",\"vat_percent\":\"0\",\"payment_method\":\"cash\",\"payment_reference\":\"\",\"tendered\":\"28490.00\"},\"files\":[]}','5a54625ac8ce','3c40646655f92760'),(248,'2026-11-05 21:59:33','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/approve/approve.php','sales_invoices','193','Updated sales_invoices #193: approval_status, approved_by, approved_at','{\"changes\":{\"approval_status\":[\"pending\",\"approved\"],\"approved_by\":[null,10],\"approved_at\":[null,{\"expression\":\"NOW()\"}]},\"rows\":1}','5a54625ac8ce','643a2bd88a1c1471'),(249,'2026-11-05 21:59:33','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/approve/approve.php','inv_docs','','Update on inv_docs','{\"statement\":\"UPDATE inv_docs d JOIN sales_invoices s ON s.stock_doc_id=d.doc_id SET d.approval_status=\'approved\', d.approved_by=?, d.approved_at=NOW() WHERE d.doc_type=? AND s.invoice_id IN (?) AND s.approval_status=\'approved\'\"}','5a54625ac8ce','643a2bd88a1c1471'),(250,'2026-11-05 21:59:33','activity','form_post','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/approve/approve.php','','','Form sent: approve.php','{\"fields\":{\"csrf_token\":\"***\",\"ids\":\"(1 items)\"},\"files\":[]}','5a54625ac8ce','643a2bd88a1c1471'),(251,'2026-11-05 21:59:36','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/approve/approve.php','sales_invoices','177','Updated sales_invoices #177: approval_status, approved_by, approved_at','{\"changes\":{\"approval_status\":[\"pending\",\"approved\"],\"approved_by\":[null,10],\"approved_at\":[null,{\"expression\":\"NOW()\"}]},\"rows\":1}','5a54625ac8ce','f00fe516724a1e05'),(252,'2026-11-05 21:59:36','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/approve/approve.php','inv_docs','','Update on inv_docs','{\"statement\":\"UPDATE inv_docs d JOIN sales_invoices s ON s.stock_doc_id=d.doc_id SET d.approval_status=\'approved\', d.approved_by=?, d.approved_at=NOW() WHERE d.doc_type=? AND s.invoice_id IN (?) AND s.approval_status=\'approved\'\"}','5a54625ac8ce','f00fe516724a1e05'),(253,'2026-11-05 21:59:36','activity','form_post','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/approve/approve.php','','','Form sent: approve.php','{\"fields\":{\"csrf_token\":\"***\",\"ids\":\"(1 items)\"},\"files\":[]}','5a54625ac8ce','f00fe516724a1e05'),(254,'2026-11-05 22:00:17','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','GET','/billing_star/billing/user/licence.php','acc_settings','','Added to acc_settings','{\"added\":{\"setting_key\":\"licence_host\",\"setting_value\":\"localhost\"}}','5a54625ac8ce','ad4402ac93e9f53d'),(255,'2026-11-05 22:12:21','change','update','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_sr.php','trader_sr_assignments','14','Updated trader_sr_assignments #14: effective_to','{\"changes\":{\"effective_to\":[null,\"2026-11-05\"]},\"rows\":1}','5a54625ac8ce','da2c37338715fa92'),(256,'2026-11-05 22:12:21','change','insert','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_sr.php','trader_sr_assignments','20','Added to trader_sr_assignments #20','{\"added\":{\"trader_id\":23,\"sr_id\":22,\"effective_from\":\"2026-11-05\",\"assigned_by\":10}}','5a54625ac8ce','da2c37338715fa92'),(257,'2026-11-05 22:12:21','activity','form_post','info','ok','staff',10,'Administrator',0,'::1','LAN','Chrome','macOS','Desktop','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','POST','/billing_star/billing/trader/trader_sr.php','','','Form sent: trader_sr.php (assign)','{\"fields\":{\"csrf_token\":\"***\",\"cat\":\"0\",\"group\":\"0\",\"q\":\"\",\"unassigned\":\"\",\"assign_sr_id\":\"22\",\"effective_from\":\"2026-11-05\",\"action\":\"assign\",\"trader_ids\":\"(1 items)\"},\"files\":[]}','5a54625ac8ce','da2c37338715fa92');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_settings`
--

DROP TABLE IF EXISTS `audit_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_settings` (
  `setting_key` varchar(40) NOT NULL,
  `setting_value` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_settings`
--

LOCK TABLES `audit_settings` WRITE;
/*!40000 ALTER TABLE `audit_settings` DISABLE KEYS */;
INSERT INTO `audit_settings` VALUES ('purged_licence_state','1');
/*!40000 ALTER TABLE `audit_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `branch_id` int unsigned NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_branch_id` int unsigned DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`branch_id`),
  UNIQUE KEY `uq_branches_name` (`branch_name`),
  KEY `ix_branches_parent` (`parent_branch_id`),
  CONSTRAINT `fk_branch_parent` FOREIGN KEY (`parent_branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Head Office',NULL,'',1),(11,'ABC Tele',NULL,'MIrour',1),(15,'Chattogram Branch',NULL,'Chattogram Branch office',1),(16,'Sylhet Branch',NULL,'Sylhet Branch office',1),(17,'Mirpur Sub Branch',1,'Mirpur Sub Branch office',1);
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

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
  `enable_recognize_h323_id` varchar(20) DEFAULT NULL,
  `menu_style` varchar(20) DEFAULT NULL,
  `vsr_style` varchar(20) DEFAULT NULL,
  `mobile_view_vsc` varchar(20) DEFAULT NULL,
  `mobile_view_vsr` varchar(20) DEFAULT NULL,
  `default_theme` varchar(20) DEFAULT NULL,
  `default_call_limit` int DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_theme`
--

LOCK TABLES `client_theme` WRITE;
/*!40000 ALTER TABLE `client_theme` DISABLE KEYS */;
INSERT INTO `client_theme` VALUES (1,123,'Billing Star','Billing Star','Billing Star',10,25,'Billing Star','You shop in one roof','disable','','','treemain','treemain','treemain',1,'','','','','','','','',0,'1,1,1,1,0,1,1,1,1,1','#FFFFFF',NULL,'Petacore Tech','www.petacoretech.com','www.petacoretech.com','Petacore Tech');
/*!40000 ALTER TABLE `client_theme` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Table structure for table `hosted_reseller`
--

DROP TABLE IF EXISTS `hosted_reseller`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hosted_reseller` (
  `hosted_id` int NOT NULL AUTO_INCREMENT,
  `reseller_id` int DEFAULT NULL,
  `level` int DEFAULT NULL,
  `gateway` int DEFAULT NULL,
  `gkregistrar` int DEFAULT NULL,
  `dialing_plan` int DEFAULT NULL,
  `gateway_call_limit` int DEFAULT NULL,
  PRIMARY KEY (`hosted_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hosted_reseller`
--

LOCK TABLES `hosted_reseller` WRITE;
/*!40000 ALTER TABLE `hosted_reseller` DISABLE KEYS */;
/*!40000 ALTER TABLE `hosted_reseller` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_attendance`
--

DROP TABLE IF EXISTS `hr_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_attendance` (
  `attendance_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','late','half_day','absent','leave','half_leave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `unpaid_days` decimal(3,1) NOT NULL DEFAULT '0.0' COMMENT 'Part of the day taken off the salary',
  `application_id` bigint unsigned DEFAULT NULL,
  `source` enum('office','portal','leave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'office',
  `note` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `uq_hr_attendance` (`employee_id`,`attendance_date`),
  KEY `ix_hr_attendance_date` (`attendance_date`),
  KEY `ix_hr_attendance_leave` (`application_id`),
  CONSTRAINT `fk_hr_att_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_att_leave` FOREIGN KEY (`application_id`) REFERENCES `hr_leave_applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_attendance`
--

LOCK TABLES `hr_attendance` WRITE;
/*!40000 ALTER TABLE `hr_attendance` DISABLE KEYS */;
INSERT INTO `hr_attendance` VALUES (2,4,'2026-10-31','late','13:13:18','13:13:22',0.0,NULL,'portal','',NULL,'2026-10-31 07:13:22'),(3,5,'2026-11-02','present',NULL,NULL,0.0,NULL,'office','',63,'2026-11-02 16:06:19'),(4,4,'2026-11-02','present',NULL,NULL,0.0,NULL,'office','',63,'2026-11-02 16:06:19'),(5,3,'2026-11-02','present',NULL,NULL,0.0,NULL,'office','',63,'2026-11-02 16:06:19'),(6,1,'2026-11-02','present',NULL,NULL,0.0,NULL,'office','',63,'2026-11-02 16:06:19'),(7,2,'2026-11-02','present',NULL,NULL,0.0,NULL,'office','',63,'2026-11-02 16:06:19');
/*!40000 ALTER TABLE `hr_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_departments`
--

DROP TABLE IF EXISTS `hr_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_departments` (
  `department_id` int unsigned NOT NULL AUTO_INCREMENT,
  `department_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `uq_hr_departments_name` (`department_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_departments`
--

LOCK TABLES `hr_departments` WRITE;
/*!40000 ALTER TABLE `hr_departments` DISABLE KEYS */;
INSERT INTO `hr_departments` VALUES (1,'Sales',1),(2,'SAMPLE Sales Department',1);
/*!40000 ALTER TABLE `hr_departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_designations`
--

DROP TABLE IF EXISTS `hr_designations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_designations` (
  `designation_id` int unsigned NOT NULL AUTO_INCREMENT,
  `designation_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`designation_id`),
  UNIQUE KEY `uq_hr_designations_name` (`designation_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_designations`
--

LOCK TABLES `hr_designations` WRITE;
/*!40000 ALTER TABLE `hr_designations` DISABLE KEYS */;
INSERT INTO `hr_designations` VALUES (1,'Sr Sales',1),(2,'Sales Executive',1),(3,'SAMPLE Sales Officer',1);
/*!40000 ALTER TABLE `hr_designations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_documents`
--

DROP TABLE IF EXISTS `hr_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_documents` (
  `document_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `document_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `original_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` int unsigned NOT NULL DEFAULT '0',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  KEY `ix_hr_documents_emp` (`employee_id`),
  CONSTRAINT `fk_hr_documents_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_documents`
--

LOCK TABLES `hr_documents` WRITE;
/*!40000 ALTER TABLE `hr_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_education`
--

DROP TABLE IF EXISTS `hr_education`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_education` (
  `record_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `degree` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `institution` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `board_university` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `major` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `passing_year` smallint unsigned DEFAULT NULL,
  `result` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`record_id`),
  KEY `ix_hr_education_emp` (`employee_id`),
  CONSTRAINT `fk_hr_education_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_education`
--

LOCK TABLES `hr_education` WRITE;
/*!40000 ALTER TABLE `hr_education` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_education` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employees`
--

DROP TABLE IF EXISTS `hr_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_employees` (
  `employee_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `father_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mother_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('','male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `blood_group` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `marital_status` enum('','single','married','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `spouse_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `religion` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nationality` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nid` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `passport_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `driving_license` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tin` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `present_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `permanent_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `emergency_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `emergency_mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `skills` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `languages` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `department_id` int unsigned DEFAULT NULL,
  `designation_id` int unsigned DEFAULT NULL,
  `branch_id` int unsigned DEFAULT NULL,
  `reports_to` bigint unsigned DEFAULT NULL,
  `employment_type` enum('permanent','probation','contract','intern') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'permanent',
  `join_date` date NOT NULL,
  `confirm_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `status` enum('onboarding','active','resigned','terminated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'onboarding',
  `bank_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bank_account` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sr_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `uq_hr_employees_code` (`employee_code`),
  UNIQUE KEY `uq_hr_employees_sr` (`sr_id`),
  KEY `ix_hr_employees_name` (`full_name`),
  KEY `ix_hr_employees_status` (`status`),
  KEY `fk_hr_emp_department` (`department_id`),
  KEY `fk_hr_emp_designation` (`designation_id`),
  KEY `fk_hr_emp_manager` (`reports_to`),
  CONSTRAINT `fk_hr_emp_department` FOREIGN KEY (`department_id`) REFERENCES `hr_departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_emp_designation` FOREIGN KEY (`designation_id`) REFERENCES `hr_designations` (`designation_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_emp_manager` FOREIGN KEY (`reports_to`) REFERENCES `hr_employees` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_emp_sr` FOREIGN KEY (`sr_id`) REFERENCES `sales_representatives` (`sr_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employees`
--

LOCK TABLES `hr_employees` WRITE;
/*!40000 ALTER TABLE `hr_employees` DISABLE KEYS */;
INSERT INTO `hr_employees` VALUES (1,'EMP00001','Sadman Bhuian','','',NULL,'','','','','','','','','','','01815911873','sadman@test.com','Dhanamoni dhaka','','','','','',NULL,1,NULL,NULL,'permanent','2026-10-30',NULL,NULL,'active','','',10,10,'2026-10-31 07:01:56','2026-10-31 07:01:56'),(2,'EMP00002','Tanvir Hasan','','',NULL,'','','','','','','','','','','01911300301','tanvir@example.com','Dhaka','','','','','',NULL,2,NULL,NULL,'permanent','2026-01-15',NULL,NULL,'active','','',19,10,'2026-10-31 07:01:56','2026-10-31 07:01:56'),(3,'EMP00003','Nusrat Jahan','','',NULL,'','','','','','','','','','','01911300302','nusrat@example.com','Dhaka','','','','','',NULL,2,NULL,NULL,'permanent','2026-01-15',NULL,NULL,'active','','',20,10,'2026-10-31 07:01:56','2026-10-31 07:01:56'),(4,'EMP00004','Mahmud Alam','','',NULL,'','','','','','','','','','','01911300303','mahmud@example.com','Dhaka','','','','','',NULL,2,NULL,NULL,'permanent','2026-01-15',NULL,NULL,'active','','',21,10,'2026-10-31 07:01:56','2026-10-31 07:01:56'),(5,'EMP00005','Farhana Akter','','',NULL,'','','','','','','','','','','01911300304','farhana@example.com','Dhaka','','','','','',NULL,2,NULL,NULL,'permanent','2026-01-15',NULL,NULL,'active','','',22,10,'2026-10-31 07:01:56','2026-10-31 07:01:56'),(6,'SMP-EMP-1','SAMPLE Fatema Akter','Abdul Hamid','','1996-04-15','female','','','','','','1990000000001','','','','01711000601','sample.employee@example.com','Dhanmondi, Dhaka','','Hamid','01711000602','','',2,3,11,NULL,'permanent','2026-11-01',NULL,NULL,'onboarding','Sample Bank','0011223344',NULL,63,'2026-11-02 16:06:16','2026-11-02 16:06:16');
/*!40000 ALTER TABLE `hr_employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_experience`
--

DROP TABLE IF EXISTS `hr_experience`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_experience` (
  `record_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `company` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_title` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date DEFAULT NULL,
  `last_salary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `responsibilities` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `leaving_reason` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contact` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`record_id`),
  KEY `ix_hr_experience_emp` (`employee_id`),
  CONSTRAINT `fk_hr_experience_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_experience`
--

LOCK TABLES `hr_experience` WRITE;
/*!40000 ALTER TABLE `hr_experience` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_experience` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_family`
--

DROP TABLE IF EXISTS `hr_family`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_family` (
  `record_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `member_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `relation` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `occupation` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nominee_share` decimal(5,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`record_id`),
  KEY `ix_hr_family_emp` (`employee_id`),
  CONSTRAINT `fk_hr_family_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_family`
--

LOCK TABLES `hr_family` WRITE;
/*!40000 ALTER TABLE `hr_family` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_family` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_holidays`
--

DROP TABLE IF EXISTS `hr_holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_holidays` (
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_holidays`
--

LOCK TABLES `hr_holidays` WRITE;
/*!40000 ALTER TABLE `hr_holidays` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_leave_applications`
--

DROP TABLE IF EXISTS `hr_leave_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_leave_applications` (
  `application_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `leave_type_id` int unsigned NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `half_day` tinyint(1) NOT NULL DEFAULT '0',
  `days` decimal(6,1) NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('pending','approved','rejected','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `applied_via` enum('portal','office') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'portal',
  `decided_by` bigint unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_note` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`application_id`),
  KEY `ix_hr_leave_emp` (`employee_id`,`from_date`),
  KEY `ix_hr_leave_status` (`status`,`from_date`),
  KEY `fk_hr_leave_type` (`leave_type_id`),
  CONSTRAINT `fk_hr_leave_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `hr_leave_types` (`leave_type_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_leave_applications`
--

LOCK TABLES `hr_leave_applications` WRITE;
/*!40000 ALTER TABLE `hr_leave_applications` DISABLE KEYS */;
INSERT INTO `hr_leave_applications` VALUES (1,4,1,'2026-11-04','2026-11-04',0,1.0,'sick','cancelled','portal',10,'2026-10-31 13:21:06','','2026-10-31 07:10:50'),(2,4,1,'2026-11-12','2026-11-12',0,1.0,'sfsfds','pending','portal',NULL,NULL,'','2026-10-31 07:17:22'),(3,4,1,'2026-11-11','2026-11-11',0,1.0,'fsdfsdf','pending','portal',NULL,NULL,'','2026-10-31 07:29:01');
/*!40000 ALTER TABLE `hr_leave_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_leave_balances`
--

DROP TABLE IF EXISTS `hr_leave_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_leave_balances` (
  `employee_id` bigint unsigned NOT NULL,
  `leave_type_id` int unsigned NOT NULL,
  `leave_year` smallint unsigned NOT NULL,
  `allocated` decimal(6,1) NOT NULL DEFAULT '0.0',
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`,`leave_type_id`,`leave_year`),
  KEY `fk_hr_bal_type` (`leave_type_id`),
  CONSTRAINT `fk_hr_bal_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_bal_type` FOREIGN KEY (`leave_type_id`) REFERENCES `hr_leave_types` (`leave_type_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_leave_balances`
--

LOCK TABLES `hr_leave_balances` WRITE;
/*!40000 ALTER TABLE `hr_leave_balances` DISABLE KEYS */;
INSERT INTO `hr_leave_balances` VALUES (1,1,2026,0.0,10,'2026-10-31 07:10:12'),(2,1,2026,0.0,10,'2026-10-31 07:10:12'),(3,1,2026,0.0,10,'2026-10-31 07:10:12'),(4,1,2026,10.0,10,'2026-10-31 07:10:12'),(5,1,2026,0.0,10,'2026-10-31 07:10:12');
/*!40000 ALTER TABLE `hr_leave_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_leave_types`
--

DROP TABLE IF EXISTS `hr_leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_leave_types` (
  `leave_type_id` int unsigned NOT NULL AUTO_INCREMENT,
  `leave_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_days` decimal(6,1) NOT NULL DEFAULT '0.0' COMMENT 'Days per year, suggested on Leave Assign',
  `is_paid` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0: no balance needed, and the days are taken off the salary',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`leave_type_id`),
  UNIQUE KEY `uq_hr_leave_types_name` (`leave_name`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_leave_types`
--

LOCK TABLES `hr_leave_types` WRITE;
/*!40000 ALTER TABLE `hr_leave_types` DISABLE KEYS */;
INSERT INTO `hr_leave_types` VALUES (1,'Casual Leave',10.0,1,1),(2,'Sick Leave',14.0,1,1),(3,'Earned Leave',15.0,1,1),(4,'Unpaid Leave',0.0,0,1);
/*!40000 ALTER TABLE `hr_leave_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_onboarding_tasks`
--

DROP TABLE IF EXISTS `hr_onboarding_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_onboarding_tasks` (
  `employee_id` bigint unsigned NOT NULL,
  `task_key` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `done_by` bigint unsigned DEFAULT NULL,
  `done_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`,`task_key`),
  CONSTRAINT `fk_hr_task_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_onboarding_tasks`
--

LOCK TABLES `hr_onboarding_tasks` WRITE;
/*!40000 ALTER TABLE `hr_onboarding_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_onboarding_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_payslips`
--

DROP TABLE IF EXISTS `hr_payslips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_payslips` (
  `payslip_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `period_month` date NOT NULL,
  `basic` decimal(15,2) NOT NULL DEFAULT '0.00',
  `house_rent` decimal(15,2) NOT NULL DEFAULT '0.00',
  `medical` decimal(15,2) NOT NULL DEFAULT '0.00',
  `conveyance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `other_allowance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `bonus` decimal(15,2) NOT NULL DEFAULT '0.00',
  `gross` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Monthly pay before bonus',
  `tax` decimal(15,2) NOT NULL DEFAULT '0.00',
  `provident_fund` decimal(15,2) NOT NULL DEFAULT '0.00',
  `other_deduction` decimal(15,2) NOT NULL DEFAULT '0.00',
  `advance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `month_days` tinyint unsigned NOT NULL DEFAULT '30',
  `absent_days` decimal(5,1) NOT NULL DEFAULT '0.0',
  `unpaid_leave_days` decimal(5,1) NOT NULL DEFAULT '0.0',
  `not_employed_days` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Before joining or after leaving',
  `absence_deduction` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_pay` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','approved','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `paid_on` date DEFAULT NULL,
  `payment_method` enum('cash','bank','mobile','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bank',
  `reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pay_account_id` bigint unsigned DEFAULT NULL,
  `voucher_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payslip_id`),
  UNIQUE KEY `uq_hr_payslips` (`employee_id`,`period_month`),
  KEY `ix_hr_payslips_month` (`period_month`,`status`),
  KEY `ix_hr_payslips_voucher` (`voucher_id`),
  CONSTRAINT `fk_hr_payslip_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_payslips`
--

LOCK TABLES `hr_payslips` WRITE;
/*!40000 ALTER TABLE `hr_payslips` DISABLE KEYS */;
INSERT INTO `hr_payslips` VALUES (1,4,'2026-10-01',4000.00,2000.00,1000.00,1000.00,2000.00,0.00,10000.00,1200.00,0.00,0.00,0.00,31,0.0,0.0,0,0.00,8800.00,'draft',NULL,'bank','',NULL,NULL,10,'2026-10-31 09:32:51','2026-10-31 09:32:51');
/*!40000 ALTER TABLE `hr_payslips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_references`
--

DROP TABLE IF EXISTS `hr_references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_references` (
  `record_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `reference_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `relation` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `organization` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `designation` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `verified` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'The office has spoken to this person',
  PRIMARY KEY (`record_id`),
  KEY `ix_hr_references_emp` (`employee_id`),
  CONSTRAINT `fk_hr_references_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_references`
--

LOCK TABLES `hr_references` WRITE;
/*!40000 ALTER TABLE `hr_references` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_references` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_salaries`
--

DROP TABLE IF EXISTS `hr_salaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_salaries` (
  `salary_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `basic` decimal(15,2) NOT NULL DEFAULT '0.00',
  `house_rent` decimal(15,2) NOT NULL DEFAULT '0.00',
  `medical` decimal(15,2) NOT NULL DEFAULT '0.00',
  `conveyance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `other_allowance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tax_mode` enum('manual','slab') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `tax` decimal(15,2) NOT NULL DEFAULT '0.00',
  `provident_fund` decimal(15,2) NOT NULL DEFAULT '0.00',
  `other_deduction` decimal(15,2) NOT NULL DEFAULT '0.00',
  `note` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`salary_id`),
  UNIQUE KEY `uq_hr_salaries` (`employee_id`,`effective_from`),
  CONSTRAINT `fk_hr_salary_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_salaries`
--

LOCK TABLES `hr_salaries` WRITE;
/*!40000 ALTER TABLE `hr_salaries` DISABLE KEYS */;
INSERT INTO `hr_salaries` VALUES (1,4,'2026-10-01',4000.00,2000.00,1000.00,1000.00,2000.00,'manual',1200.00,0.00,0.00,'',10,'2026-10-31 07:07:32');
/*!40000 ALTER TABLE `hr_salaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_settings`
--

DROP TABLE IF EXISTS `hr_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_settings` (
  `setting_key` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_settings`
--

LOCK TABLES `hr_settings` WRITE;
/*!40000 ALTER TABLE `hr_settings` DISABLE KEYS */;
INSERT INTO `hr_settings` VALUES ('late_after','09:30'),('weekly_off','5');
/*!40000 ALTER TABLE `hr_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_tax_slabs`
--

DROP TABLE IF EXISTS `hr_tax_slabs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_tax_slabs` (
  `slab_id` int unsigned NOT NULL AUTO_INCREMENT,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `band_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`slab_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_tax_slabs`
--

LOCK TABLES `hr_tax_slabs` WRITE;
/*!40000 ALTER TABLE `hr_tax_slabs` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_tax_slabs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_training`
--

DROP TABLE IF EXISTS `hr_training`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hr_training` (
  `record_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `title` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `institute` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `from_date` date DEFAULT NULL,
  `to_date` date DEFAULT NULL,
  `certificate_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`record_id`),
  KEY `ix_hr_training_emp` (`employee_id`),
  CONSTRAINT `fk_hr_training_emp` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_training`
--

LOCK TABLES `hr_training` WRITE;
/*!40000 ALTER TABLE `hr_training` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_training` ENABLE KEYS */;
UNLOCK TABLES;

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
  `product_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_caption` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `warranty` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_to` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `free_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `prev_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `narration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serials` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'One serial / IMEI per line',
  `source_kind` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'inv = inv_doc_lines, sales_order = sales_order_items',
  `source_line_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`line_id`),
  KEY `ix_inv_lines_doc` (`doc_id`),
  KEY `ix_inv_lines_source` (`source_kind`,`source_line_id`),
  KEY `ix_inv_lines_product` (`product_id`),
  CONSTRAINT `fk_inv_line_doc` FOREIGN KEY (`doc_id`) REFERENCES `inv_docs` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=604 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_doc_lines`
--

LOCK TABLES `inv_doc_lines` WRITE;
/*!40000 ALTER TABLE `inv_doc_lines` DISABLE KEYS */;
INSERT INTO `inv_doc_lines` VALUES (313,294,1,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','1 Year','Main Store','',26.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(314,294,2,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','1 Year','Main Store','',30.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(315,294,3,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','1 Year','Main Store','',13.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(316,295,1,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','1 Year','Main Store','',28.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(317,295,2,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','1 Year','Main Store','',27.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(318,295,3,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','1 Year','Main Store','',25.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(319,296,1,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','Pcs','1 Year','Main Store','',10.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(320,296,2,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','1 Year','Main Store','',29.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(321,296,3,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','1 Year','Main Store','',12.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(322,297,1,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','1 Year','','',20.000,0.000,13200.00,0.00,0.00,264000.00,'','','',NULL),(323,297,2,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','1 Year','','',46.000,0.000,13900.00,0.00,0.00,639400.00,'','','',NULL),(324,297,3,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','Pcs','1 Year','','',48.000,0.000,3500.00,0.00,0.00,168000.00,'','','',NULL),(325,297,4,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','1 Year','','',41.000,0.000,3000.00,0.00,0.00,123000.00,'','','',NULL),(326,298,1,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','1 Year','','',28.000,0.000,14300.00,0.00,0.00,400400.00,'','','',NULL),(327,298,2,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','1 Year','','',31.000,0.000,5400.00,0.00,0.00,167400.00,'','','',NULL),(328,298,3,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','1 Year','','',49.000,0.000,600.00,0.00,0.00,29400.00,'','','',NULL),(329,298,4,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','Pcs','1 Year','','',31.000,0.000,5100.00,0.00,0.00,158100.00,'','','',NULL),(330,299,1,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','Pcs','1 Year','','',22.000,0.000,5100.00,0.00,0.00,112200.00,'','','',NULL),(331,299,2,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','1 Year','','',37.000,0.000,12900.00,0.00,0.00,477300.00,'','','',NULL),(332,299,3,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','1 Year','','',37.000,0.000,1600.00,0.00,0.00,59200.00,'','','',NULL),(333,299,4,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','1 Year','','',29.000,0.000,5400.00,0.00,0.00,156600.00,'','','',NULL),(334,300,1,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','1 Year','','',20.000,0.000,13900.00,0.00,0.00,278000.00,'','','',NULL),(335,300,2,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','1 Year','','',47.000,0.000,3000.00,0.00,0.00,141000.00,'','','',NULL),(336,300,3,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year','','',42.000,0.000,6200.00,0.00,0.00,260400.00,'','','',NULL),(337,300,4,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','Pcs','1 Year','','',31.000,0.000,3500.00,0.00,0.00,108500.00,'','','',NULL),(338,301,1,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year','','',32.000,0.000,9100.00,0.00,0.00,291200.00,'','','',NULL),(339,301,2,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','1 Year','','',48.000,0.000,3000.00,0.00,0.00,144000.00,'','','',NULL),(340,301,3,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','1 Year','','',38.000,0.000,9500.00,0.00,0.00,361000.00,'','','',NULL),(341,301,4,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','1 Year','','',37.000,0.000,1600.00,0.00,0.00,59200.00,'','','',NULL),(342,302,1,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','1 Year','Main Store','',71.000,0.000,2500.00,0.00,0.00,177500.00,'','','',NULL),(343,302,2,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','1 Year','Main Store','',47.000,0.000,23000.00,0.00,0.00,1081000.00,'','','',NULL),(344,302,3,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','1 Year','Main Store','',67.000,0.000,1600.00,0.00,0.00,107200.00,'','','',NULL),(345,302,4,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','1 Year','Main Store','',72.000,0.000,13200.00,0.00,0.00,950400.00,'','','',NULL),(346,302,5,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year','Main Store','',88.000,0.000,6200.00,0.00,0.00,545600.00,'','','',NULL),(347,302,6,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','1 Year','Main Store','',78.000,0.000,7600.00,0.00,0.00,592800.00,'','','',NULL),(348,302,7,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','1 Year','Main Store','',71.000,0.000,600.00,0.00,0.00,42600.00,'','','',NULL),(349,302,8,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','1 Year','Main Store','',65.000,0.000,820.00,0.00,0.00,53300.00,'','','',NULL),(350,302,9,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','1 Year','Main Store','',73.000,0.000,10400.00,0.00,0.00,759200.00,'','','',NULL),(351,302,10,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','1 Year','Main Store','',56.000,0.000,13900.00,0.00,0.00,778400.00,'','','',NULL),(352,302,11,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','1 Year','Main Store','',66.000,0.000,5400.00,0.00,0.00,356400.00,'','','',NULL),(353,302,12,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year','Main Store','',76.000,0.000,9100.00,0.00,0.00,691600.00,'','','',NULL),(354,302,13,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','Pcs','1 Year','Main Store','',70.000,0.000,5100.00,0.00,0.00,357000.00,'','','',NULL),(355,302,14,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','1 Year','Main Store','',72.000,0.000,9500.00,0.00,0.00,684000.00,'','','',NULL),(356,302,15,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','1 Year','Main Store','',73.000,0.000,3000.00,0.00,0.00,219000.00,'','','',NULL),(357,302,16,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','1 Year','Main Store','',41.000,0.000,14300.00,0.00,0.00,586300.00,'','','',NULL),(358,302,17,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','1 Year','Main Store','',76.000,0.000,12900.00,0.00,0.00,980400.00,'','','',NULL),(359,302,18,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','1 Year','Main Store','',54.000,0.000,3900.00,0.00,0.00,210600.00,'','','',NULL),(360,302,19,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','Pcs','1 Year','Main Store','',89.000,0.000,3500.00,0.00,0.00,311500.00,'','','',NULL),(361,303,1,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year','Main Store','',28.000,0.000,6200.00,0.00,0.00,173600.00,'','','',NULL),(362,303,2,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','1 Year','Main Store','',30.000,0.000,23000.00,0.00,0.00,690000.00,'','','',NULL),(363,303,3,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','1 Year','Main Store','',19.000,0.000,12900.00,0.00,0.00,245100.00,'','','',NULL),(364,303,4,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','1 Year','Main Store','',30.000,0.000,13200.00,0.00,0.00,396000.00,'','','',NULL),(365,303,5,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year','Main Store','',22.000,0.000,9100.00,0.00,0.00,200200.00,'','','',NULL),(366,303,6,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','1 Year','Main Store','',18.000,0.000,13900.00,0.00,0.00,250200.00,'','','',NULL),(367,304,1,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','1 Year','Main Store','',32.000,0.000,3000.00,0.00,0.00,96000.00,'','','',NULL),(368,304,2,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','1 Year','Main Store','',40.000,0.000,14300.00,0.00,0.00,572000.00,'','','',NULL),(369,304,3,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','1 Year','Main Store','',35.000,0.000,10400.00,0.00,0.00,364000.00,'','','',NULL),(370,304,4,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','Pcs','1 Year','Main Store','',31.000,0.000,5100.00,0.00,0.00,158100.00,'','','',NULL),(371,304,5,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','1 Year','Main Store','',37.000,0.000,5400.00,0.00,0.00,199800.00,'','','',NULL),(372,304,6,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year','Main Store','',30.000,0.000,6200.00,0.00,0.00,186000.00,'','','',NULL),(373,305,1,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','Pcs','1 Year','Main Store','',15.000,0.000,5100.00,0.00,0.00,76500.00,'','','',NULL),(374,305,2,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','1 Year','Main Store','',16.000,0.000,9500.00,0.00,0.00,152000.00,'','','',NULL),(375,305,3,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','1 Year','Main Store','',19.000,0.000,600.00,0.00,0.00,11400.00,'','','',NULL),(376,305,4,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','1 Year','Main Store','',35.000,0.000,13900.00,0.00,0.00,486500.00,'','','',NULL),(377,305,5,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','1 Year','Main Store','',34.000,0.000,3900.00,0.00,0.00,132600.00,'','','',NULL),(378,305,6,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year','Main Store','',29.000,0.000,6200.00,0.00,0.00,179800.00,'','','',NULL),(379,306,1,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','1 Year','Main Store','',30.000,0.000,12900.00,0.00,0.00,387000.00,'','','',NULL),(380,306,2,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','1 Year','Main Store','',29.000,0.000,14300.00,0.00,0.00,414700.00,'','','',NULL),(381,306,3,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year','Main Store','',31.000,0.000,9100.00,0.00,0.00,282100.00,'','','',NULL),(382,306,4,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','1 Year','Main Store','',39.000,0.000,600.00,0.00,0.00,23400.00,'','','',NULL),(383,306,5,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year','Main Store','',30.000,0.000,6200.00,0.00,0.00,186000.00,'','','',NULL),(384,306,6,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','Pcs','1 Year','Main Store','',40.000,0.000,3500.00,0.00,0.00,140000.00,'','','',NULL),(385,307,1,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','1 Year','Main Store','',40.000,0.000,9500.00,0.00,0.00,380000.00,'','','',NULL),(386,307,2,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','1 Year','Main Store','',18.000,0.000,3900.00,0.00,0.00,70200.00,'','','',NULL),(387,307,3,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','1 Year','Main Store','',29.000,0.000,23000.00,0.00,0.00,667000.00,'','','',NULL),(388,307,4,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','1 Year','Main Store','',37.000,0.000,13900.00,0.00,0.00,514300.00,'','','',NULL),(389,307,5,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','1 Year','Main Store','',37.000,0.000,7600.00,0.00,0.00,281200.00,'','','',NULL),(390,307,6,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','1 Year','Main Store','',40.000,0.000,820.00,0.00,0.00,32800.00,'','','',NULL),(391,308,1,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','1 Year','Main Store','',38.000,0.000,12900.00,0.00,0.00,490200.00,'','','',NULL),(392,308,2,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','1 Year','Main Store','',30.000,0.000,10400.00,0.00,0.00,312000.00,'','','',NULL),(393,308,3,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','1 Year','Main Store','',17.000,0.000,3000.00,0.00,0.00,51000.00,'','','',NULL),(394,308,4,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','1 Year','Main Store','',35.000,0.000,7600.00,0.00,0.00,266000.00,'','','',NULL),(395,308,5,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','1 Year','Main Store','',23.000,0.000,600.00,0.00,0.00,13800.00,'','','',NULL),(396,308,6,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','1 Year','Main Store','',30.000,0.000,9500.00,0.00,0.00,285000.00,'','','',NULL),(397,309,1,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year','Main Store','',15.000,0.000,9100.00,0.00,0.00,136500.00,'','','',NULL),(398,309,2,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','1 Year','Main Store','',17.000,0.000,13200.00,0.00,0.00,224400.00,'','','',NULL),(399,309,3,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','1 Year','Main Store','',15.000,0.000,600.00,0.00,0.00,9000.00,'','','',NULL),(400,309,4,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','1 Year','Main Store','',29.000,0.000,9500.00,0.00,0.00,275500.00,'','','',NULL),(401,309,5,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','1 Year','Main Store','',33.000,0.000,1600.00,0.00,0.00,52800.00,'','','',NULL),(402,309,6,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','1 Year','Main Store','',32.000,0.000,820.00,0.00,0.00,26240.00,'','','',NULL),(403,310,1,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','1 Year','Main Store','',39.000,0.000,1600.00,0.00,0.00,62400.00,'','','',NULL),(404,310,2,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','1 Year','Main Store','',32.000,0.000,600.00,0.00,0.00,19200.00,'','','',NULL),(405,310,3,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','1 Year','Main Store','',23.000,0.000,820.00,0.00,0.00,18860.00,'','','',NULL),(406,310,4,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','1 Year','Main Store','',36.000,0.000,13200.00,0.00,0.00,475200.00,'','','',NULL),(407,310,5,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','1 Year','Main Store','',39.000,0.000,13900.00,0.00,0.00,542100.00,'','','',NULL),(408,310,6,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','1 Year','Main Store','',35.000,0.000,2500.00,0.00,0.00,87500.00,'','','',NULL),(409,311,1,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year','Main Store','',33.000,0.000,9100.00,0.00,0.00,300300.00,'','','',NULL),(410,311,2,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year','Main Store','',39.000,0.000,6200.00,0.00,0.00,241800.00,'','','',NULL),(411,311,3,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','1 Year','Main Store','',30.000,0.000,2500.00,0.00,0.00,75000.00,'','','',NULL),(412,311,4,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','1 Year','Main Store','',37.000,0.000,5400.00,0.00,0.00,199800.00,'','','',NULL),(413,311,5,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','1 Year','Main Store','',38.000,0.000,14300.00,0.00,0.00,543400.00,'','','',NULL),(414,311,6,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','1 Year','Main Store','',19.000,0.000,13900.00,0.00,0.00,264100.00,'','','',NULL),(415,312,1,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','','Main Store','',2.000,0.000,3900.00,0.00,0.00,7800.00,'','','',NULL),(416,313,1,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','','Main Store','',2.000,0.000,15800.00,0.00,0.00,31600.00,'','','',NULL),(417,314,1,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',4.000,0.000,12800.00,0.00,0.00,51200.00,'','','',NULL),(418,314,2,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',1.000,0.000,11800.00,0.00,0.00,11800.00,'','','',NULL),(419,315,1,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',4.000,0.000,12800.00,0.00,0.00,51200.00,'','','',NULL),(420,316,1,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','','Main Store','',3.000,0.000,3900.00,0.00,0.00,11700.00,'','','',NULL),(421,316,2,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','','Main Store','',4.000,0.000,9500.00,0.00,0.00,38000.00,'','','',NULL),(422,317,1,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',2.000,0.000,16500.00,0.00,0.00,33000.00,'','','',NULL),(423,317,2,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','','Main Store','',1.000,0.000,850.00,0.00,0.00,850.00,'','','',NULL),(424,318,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',1.000,0.000,5200.00,0.00,0.00,5200.00,'','','',NULL),(425,318,2,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','','Main Store','',3.000,0.000,9500.00,0.00,0.00,28500.00,'','','',NULL),(426,319,1,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','','Main Store','',2.000,0.000,850.00,0.00,0.00,1700.00,'','','',NULL),(427,319,2,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',3.000,0.000,16500.00,0.00,0.00,49500.00,'','','',NULL),(428,319,3,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',3.000,0.000,12800.00,0.00,0.00,38400.00,'','','',NULL),(429,320,1,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',2.000,0.000,11800.00,0.00,0.00,23600.00,'','','',NULL),(430,321,1,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','','Main Store','',3.000,0.000,9500.00,0.00,0.00,28500.00,'','','',NULL),(431,321,2,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',3.000,0.000,16500.00,0.00,0.00,49500.00,'','','',NULL),(432,321,3,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','','Main Store','',1.000,0.000,15800.00,0.00,0.00,15800.00,'','','',NULL),(433,322,1,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',1.000,0.000,12800.00,0.00,0.00,12800.00,'','','',NULL),(434,322,2,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','','Main Store','',4.000,0.000,2100.00,0.00,0.00,8400.00,'','','',NULL),(435,323,1,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',2.000,0.000,16500.00,0.00,0.00,33000.00,'','','',NULL),(436,324,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',4.000,0.000,5200.00,0.00,0.00,20800.00,'','','',NULL),(437,324,2,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','','Main Store','',1.000,0.000,11500.00,0.00,0.00,11500.00,'','','',NULL),(438,324,3,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','','Main Store','',4.000,0.000,6800.00,0.00,0.00,27200.00,'','','',NULL),(439,325,1,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',3.000,0.000,11800.00,0.00,0.00,35400.00,'','','',NULL),(440,325,2,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',3.000,0.000,16500.00,0.00,0.00,49500.00,'','','',NULL),(441,326,1,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',1.000,0.000,11800.00,0.00,0.00,11800.00,'','','',NULL),(442,327,1,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',3.000,0.000,16500.00,0.00,0.00,49500.00,'','','',NULL),(443,327,2,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','','Main Store','',1.000,0.000,16900.00,0.00,0.00,16900.00,'','','',NULL),(444,327,3,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',3.000,0.000,11800.00,0.00,0.00,35400.00,'','','',NULL),(445,328,1,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',1.000,0.000,16500.00,0.00,0.00,16500.00,'','','',NULL),(446,329,1,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','','Main Store','',2.000,0.000,16900.00,0.00,0.00,33800.00,'','','',NULL),(447,329,2,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','','Main Store','',3.000,0.000,3200.00,0.00,0.00,9600.00,'','','',NULL),(448,329,3,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',4.000,0.000,11800.00,0.00,0.00,47200.00,'','','',NULL),(449,330,1,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','','Main Store','',1.000,0.000,1150.00,0.00,0.00,1150.00,'','','',NULL),(450,330,2,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','','Main Store','',4.000,0.000,7800.00,0.00,0.00,31200.00,'','','',NULL),(451,330,3,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','','Main Store','',3.000,0.000,9500.00,0.00,0.00,28500.00,'','','',NULL),(452,331,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',4.000,0.000,5200.00,0.00,0.00,20800.00,'','','',NULL),(453,331,2,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','','Main Store','',3.000,0.000,17500.00,0.00,0.00,52500.00,'','','',NULL),(454,332,1,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','','Main Store','',1.000,0.000,9500.00,0.00,0.00,9500.00,'','','',NULL),(455,333,1,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','','Main Store','',2.000,0.000,6800.00,0.00,0.00,13600.00,'','','',NULL),(456,333,2,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','','Main Store','',4.000,0.000,28500.00,0.00,0.00,114000.00,'','','',NULL),(457,333,3,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','','Main Store','',1.000,0.000,3200.00,0.00,0.00,3200.00,'','','',NULL),(458,334,1,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','Pcs','','Main Store','',3.000,0.000,6400.00,0.00,0.00,19200.00,'','','',NULL),(459,334,2,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','','Main Store','',3.000,0.000,11500.00,0.00,0.00,34500.00,'','','',NULL),(460,334,3,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','','Main Store','',2.000,0.000,1150.00,0.00,0.00,2300.00,'','','',NULL),(461,335,1,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','','Main Store','',2.000,0.000,17500.00,0.00,0.00,35000.00,'','','',NULL),(462,335,2,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','','Main Store','',4.000,0.000,16900.00,0.00,0.00,67600.00,'','','',NULL),(463,335,3,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','','Main Store','',2.000,0.000,850.00,0.00,0.00,1700.00,'','','',NULL),(464,336,1,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',1.000,0.000,11800.00,0.00,0.00,11800.00,'','','',NULL),(465,336,2,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','','Main Store','',3.000,0.000,28500.00,0.00,0.00,85500.00,'','','',NULL),(466,336,3,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','','Main Store','',3.000,0.000,9500.00,0.00,0.00,28500.00,'','','',NULL),(467,337,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',2.000,0.000,5200.00,0.00,0.00,10400.00,'','','',NULL),(468,338,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',2.000,0.000,5200.00,0.00,0.00,10400.00,'','','',NULL),(469,338,2,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','','Main Store','',1.000,0.000,15800.00,0.00,0.00,15800.00,'','','',NULL),(470,339,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',3.000,0.000,5200.00,0.00,0.00,15600.00,'','','',NULL),(471,339,2,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',1.000,0.000,12800.00,0.00,0.00,12800.00,'','','',NULL),(472,340,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',3.000,0.000,5200.00,0.00,0.00,15600.00,'','','',NULL),(473,340,2,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','','Main Store','',2.000,0.000,16900.00,0.00,0.00,33800.00,'','','',NULL),(474,341,1,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','','Main Store','',2.000,0.000,17500.00,0.00,0.00,35000.00,'','','',NULL),(475,341,2,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',1.000,0.000,11800.00,0.00,0.00,11800.00,'','','',NULL),(476,341,3,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','','Main Store','',2.000,0.000,11500.00,0.00,0.00,23000.00,'','','',NULL),(477,342,1,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',1.000,0.000,16500.00,0.00,0.00,16500.00,'','','',NULL),(478,343,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',2.000,0.000,5200.00,0.00,0.00,10400.00,'','','',NULL),(479,344,1,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',4.000,0.000,16500.00,0.00,0.00,66000.00,'','','',NULL),(480,344,2,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','','Main Store','',3.000,0.000,2100.00,0.00,0.00,6300.00,'','','',NULL),(481,345,1,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','','Main Store','',2.000,0.000,17500.00,0.00,0.00,35000.00,'','','',NULL),(482,345,2,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',3.000,0.000,16500.00,0.00,0.00,49500.00,'','','',NULL),(483,346,1,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','','Main Store','',1.000,0.000,2100.00,0.00,0.00,2100.00,'','','',NULL),(484,347,1,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','','Main Store','',4.000,0.000,1150.00,0.00,0.00,4600.00,'','','',NULL),(485,347,2,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','','Main Store','',3.000,0.000,9500.00,0.00,0.00,28500.00,'','','',NULL),(486,347,3,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','','Main Store','',3.000,0.000,6800.00,0.00,0.00,20400.00,'','','',NULL),(487,348,1,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','','Main Store','',2.000,0.000,3900.00,0.00,0.00,7800.00,'','','',NULL),(488,349,1,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','Pcs','','Main Store','',2.000,0.000,4600.00,0.00,0.00,9200.00,'','','',NULL),(489,349,2,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','Pcs','','Main Store','',2.000,0.000,6400.00,0.00,0.00,12800.00,'','','',NULL),(490,350,1,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',1.000,0.000,12800.00,0.00,0.00,12800.00,'','','',NULL),(491,350,2,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','','Main Store','',3.000,0.000,6800.00,0.00,0.00,20400.00,'','','',NULL),(492,351,1,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','','Main Store','',3.000,0.000,7800.00,0.00,0.00,23400.00,'','','',NULL),(493,351,2,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',2.000,0.000,5200.00,0.00,0.00,10400.00,'','','',NULL),(494,352,1,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',3.000,0.000,12800.00,0.00,0.00,38400.00,'','','',NULL),(495,353,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',3.000,0.000,5200.00,0.00,0.00,15600.00,'','','',NULL),(496,354,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',4.000,0.000,5200.00,0.00,0.00,20800.00,'','','',NULL),(497,354,2,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','','Main Store','',3.000,0.000,15800.00,0.00,0.00,47400.00,'','','',NULL),(498,354,3,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','','Main Store','',3.000,0.000,850.00,0.00,0.00,2550.00,'','','',NULL),(499,355,1,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',3.000,0.000,12800.00,0.00,0.00,38400.00,'','','',NULL),(500,356,1,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','','Main Store','',3.000,0.000,17500.00,0.00,0.00,52500.00,'','','',NULL),(501,356,2,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','Pcs','','Main Store','',1.000,0.000,4600.00,0.00,0.00,4600.00,'','','',NULL),(502,357,1,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',2.000,0.000,12800.00,0.00,0.00,25600.00,'','','',NULL),(503,357,2,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','','Main Store','',4.000,0.000,6800.00,0.00,0.00,27200.00,'','','',NULL),(504,357,3,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','','Main Store','',3.000,0.000,3200.00,0.00,0.00,9600.00,'','','',NULL),(505,358,1,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','','Main Store','',3.000,0.000,3200.00,0.00,0.00,9600.00,'','','',NULL),(506,358,2,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',2.000,0.000,16500.00,0.00,0.00,33000.00,'','','',NULL),(507,358,3,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','','Main Store','',4.000,0.000,7800.00,0.00,0.00,31200.00,'','','',NULL),(508,359,1,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','','Main Store','',2.000,0.000,16900.00,0.00,0.00,33800.00,'','','',NULL),(509,359,2,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','','Main Store','',1.000,0.000,2100.00,0.00,0.00,2100.00,'','','',NULL),(510,359,3,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',1.000,0.000,11800.00,0.00,0.00,11800.00,'','','',NULL),(511,360,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',2.000,0.000,5200.00,0.00,0.00,10400.00,'','','',NULL),(512,361,1,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','','Main Store','',4.000,0.000,11500.00,0.00,0.00,46000.00,'','','',NULL),(513,361,2,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','','Main Store','',3.000,0.000,6800.00,0.00,0.00,20400.00,'','','',NULL),(514,362,1,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','','Main Store','',2.000,0.000,17500.00,0.00,0.00,35000.00,'','','',NULL),(515,363,1,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','','Main Store','',3.000,0.000,3900.00,0.00,0.00,11700.00,'','','',NULL),(516,363,2,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','','Main Store','',3.000,0.000,850.00,0.00,0.00,2550.00,'','','',NULL),(517,364,1,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',1.000,0.000,11800.00,0.00,0.00,11800.00,'','','',NULL),(518,365,1,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','','Main Store','',3.000,0.000,15800.00,0.00,0.00,47400.00,'','','',NULL),(519,366,1,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','','Main Store','',2.000,0.000,9500.00,0.00,0.00,19000.00,'','','',NULL),(520,366,2,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','','Main Store','',3.000,0.000,2100.00,0.00,0.00,6300.00,'','','',NULL),(521,367,1,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','','Main Store','',4.000,0.000,3200.00,0.00,0.00,12800.00,'','','',NULL),(522,368,1,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','','Main Store','',3.000,0.000,7800.00,0.00,0.00,23400.00,'','','',NULL),(523,368,2,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','','Main Store','',4.000,0.000,15800.00,0.00,0.00,63200.00,'','','',NULL),(524,368,3,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','','Main Store','',2.000,0.000,850.00,0.00,0.00,1700.00,'','','',NULL),(525,369,1,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','','Main Store','',2.000,0.000,28500.00,0.00,0.00,57000.00,'','','',NULL),(526,370,1,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','','Main Store','',1.000,0.000,9500.00,0.00,0.00,9500.00,'','','',NULL),(527,371,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',2.000,0.000,5200.00,0.00,0.00,10400.00,'','','',NULL),(528,372,1,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Pcs','','Main Store','',1.000,0.000,3900.00,0.00,0.00,3900.00,'','','',NULL),(529,373,1,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','','Main Store','',1.000,0.000,28500.00,0.00,0.00,28500.00,'','','',NULL),(530,374,1,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','','Main Store','',2.000,0.000,7800.00,0.00,0.00,15600.00,'','','',NULL),(531,375,1,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','','Main Store','',1.000,0.000,17500.00,0.00,0.00,17500.00,'','','',NULL),(532,376,1,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','','Main Store','',2.000,0.000,1150.00,0.00,0.00,2300.00,'','','',NULL),(533,377,1,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','','Main Store','',2.000,0.000,2100.00,0.00,0.00,4200.00,'','','',NULL),(534,378,1,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',1.000,0.000,11800.00,0.00,0.00,11800.00,'','','',NULL),(535,379,1,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','','Main Store','',2.000,0.000,15800.00,0.00,0.00,31600.00,'','','',NULL),(536,379,2,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','','Main Store','',1.000,0.000,28500.00,0.00,0.00,28500.00,'','','',NULL),(537,380,1,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','','Main Store','',3.000,0.000,16500.00,0.00,0.00,49500.00,'','','',NULL),(538,380,2,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','','Main Store','',3.000,0.000,11500.00,0.00,0.00,34500.00,'','','',NULL),(539,381,1,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','','Main Store','',1.000,0.000,6800.00,0.00,0.00,6800.00,'','','',NULL),(540,381,2,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','','Main Store','',3.000,0.000,3200.00,0.00,0.00,9600.00,'','','',NULL),(541,382,1,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','','Main Store','',1.000,0.000,7800.00,0.00,0.00,7800.00,'','','',NULL),(542,383,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','','Main Store','',1.000,0.000,5200.00,0.00,0.00,5200.00,'','','',NULL),(543,384,1,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',1.000,0.000,11800.00,0.00,0.00,11800.00,'','','',NULL),(544,385,1,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','','Main Store','',1.000,0.000,17500.00,0.00,0.00,17500.00,'','','',NULL),(545,386,1,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',1.000,0.000,12800.00,0.00,0.00,12800.00,'','','',NULL),(546,387,1,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','1 Year','Main Store','',1.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(547,387,2,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year','Main Store','',1.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(548,388,1,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','1 Year','Main Store','',1.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(549,388,2,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','1 Year','Main Store','',2.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(550,389,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','1 Year','Main Store','',2.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(551,389,2,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year','Main Store','',2.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(552,390,1,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','Pcs','1 Year','Main Store','',2.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(553,390,2,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','1 Year','Main Store','',2.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(554,391,1,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year','Main Store','',1.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(555,391,2,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','1 Year','Main Store','',1.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(556,392,1,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','1 Year','','',4.000,0.000,5200.00,0.00,0.00,20800.00,'','','',NULL),(557,392,2,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Pcs','1 Year','','',4.000,0.000,1150.00,0.00,0.00,4600.00,'','','',NULL),(558,392,3,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year','','',5.000,0.000,11500.00,0.00,0.00,57500.00,'','','',NULL),(559,393,1,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','1 Year','','',3.000,0.000,28500.00,0.00,0.00,85500.00,'','','',NULL),(560,393,2,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','1 Year','','',1.000,0.000,5200.00,0.00,0.00,5200.00,'','','',NULL),(561,393,3,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','1 Year','','',4.000,0.000,6800.00,0.00,0.00,27200.00,'','','',NULL),(562,394,1,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year','','',5.000,0.000,11500.00,0.00,0.00,57500.00,'','','',NULL),(563,394,2,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','1 Year','','',1.000,0.000,5200.00,0.00,0.00,5200.00,'','','',NULL),(564,394,3,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','1 Year','','',3.000,0.000,15800.00,0.00,0.00,47400.00,'','','',NULL),(565,395,1,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','1 Year','','',1.000,0.000,28500.00,0.00,0.00,28500.00,'','','',NULL),(566,395,2,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','1 Year','','',5.000,0.000,2100.00,0.00,0.00,10500.00,'','','',NULL),(567,395,3,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','Pcs','1 Year','','',4.000,0.000,4600.00,0.00,0.00,18400.00,'','','',NULL),(568,396,1,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','Pcs','1 Year','Main Store','site office',5.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(569,396,2,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','1 Year','Main Store','site office',5.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(570,397,1,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','1 Year','Main Store','site office',2.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(571,397,2,84,'PRN-4003','Toner','HP','CF150A','Black toner','Pcs','1 Year','Main Store','site office',3.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(572,398,1,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','1 Year','Main Store','RMA STORE',2.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(573,398,2,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year','Main Store','RMA STORE',2.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(574,399,1,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','Pcs','1 Year','Main Store','site office',3.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(575,399,2,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','1 Year','Main Store','site office',4.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(576,400,1,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','1 Year','Main Store','',2.000,0.000,2500.00,0.00,0.00,5000.00,'','','inv',342),(577,401,1,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year','Main Store','',2.000,0.000,6200.00,0.00,0.00,12400.00,'','','inv',361),(578,402,1,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',2.000,0.000,10400.00,0.00,0.00,20800.00,'','','',NULL),(579,403,1,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','','Main Store','',1.000,0.000,600.00,0.00,0.00,600.00,'','','',NULL),(580,404,1,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Pcs','','Main Store','',1.000,0.000,600.00,0.00,0.00,600.00,'','','',NULL),(581,405,1,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','','Main Store','',2.000,0.000,13900.00,0.00,0.00,27800.00,'','','',NULL),(582,406,1,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','','Main Store','',1.000,0.000,9500.00,0.00,0.00,9500.00,'','','',NULL),(583,407,1,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','','Main Store','',1.000,0.000,6800.00,0.00,0.00,6800.00,'','','',NULL),(584,408,1,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','','Main Store','',3.000,0.000,12800.00,0.00,0.00,38400.00,'','','',NULL),(585,409,1,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','SAMPLE Box','','','',10.000,0.000,21000.00,0.00,0.00,210000.00,'','','',NULL),(586,409,2,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','','','',20.000,0.000,2600.00,0.00,0.00,52000.00,'','','',NULL),(587,410,1,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','SAMPLE Box','','Main Store','',10.000,0.000,21000.00,0.00,0.00,210000.00,'','','',NULL),(588,410,2,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','','Main Store','',20.000,0.000,2600.00,0.00,0.00,52000.00,'','','',NULL),(589,411,1,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','','Main Store','',5.000,0.000,2600.00,0.00,0.00,13000.00,'','','',NULL),(590,412,1,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','','Main Store','RMA STORE',2.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(591,413,1,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','SAMPLE Box','','Main Store','',1.000,0.000,25000.00,0.00,0.00,25000.00,'','','',NULL),(592,413,2,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','','Main Store','',2.000,0.000,3500.00,0.00,0.00,7000.00,'','','',NULL),(593,414,1,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','','Main Store','',1.000,0.000,3500.00,0.00,0.00,3500.00,'','','',NULL),(594,414,2,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','SAMPLE Box','','Main Store','',1.000,0.000,25000.00,0.00,0.00,25000.00,'','','',NULL),(595,415,1,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','SAMPLE 1 Year','Main Store','',2.000,0.000,0.00,0.00,0.00,0.00,'','','',NULL),(596,416,1,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','SAMPLE Box','','Main Store','',1.000,0.000,25000.00,0.00,0.00,25000.00,'','','',NULL),(597,416,2,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','','Main Store','',2.000,0.000,3500.00,0.00,0.00,7000.00,'','','',NULL),(598,417,1,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','SAMPLE Box','','Main Store','',3.000,0.000,24500.00,0.00,0.00,73500.00,'','','',NULL),(599,417,2,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','','Main Store','',3.000,0.000,3400.00,0.00,0.00,10200.00,'','','',NULL),(600,418,1,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','','Main Store','',1.000,0.000,3500.00,0.00,0.00,3500.00,'','','',NULL),(601,419,1,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Pcs','','Main Store','',1.000,0.000,11800.00,0.00,0.00,11800.00,'','','',NULL),(602,420,1,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','','Main Store','',1.000,0.000,16900.00,0.00,0.00,16900.00,'','','',NULL),(603,421,1,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Pcs','','Main Store','',1.000,0.000,28500.00,0.00,0.00,28500.00,'','','',NULL);
/*!40000 ALTER TABLE `inv_doc_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inv_docs`
--

DROP TABLE IF EXISTS `inv_docs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_docs` (
  `doc_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_type` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_date` date NOT NULL,
  `ref_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `branch_id` int unsigned DEFAULT NULL,
  `party_type` enum('none','customer','supplier') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `party_id` bigint unsigned DEFAULT NULL,
  `party_group` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `party_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attention` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_person` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `delivery_point` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `additional_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bill_type` enum('Cash','Credit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Credit',
  `source_type` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `source_doc_id` bigint unsigned DEFAULT NULL,
  `source_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `payment_term` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `status` enum('active','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `approval_status` enum('pending','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
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
) ENGINE=InnoDB AUTO_INCREMENT=422 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_docs`
--

LOCK TABLES `inv_docs` WRITE;
/*!40000 ALTER TABLE `inv_docs` DISABLE KEYS */;
INSERT INTO `inv_docs` VALUES (294,'requisition','DESREQ2026000001','2026-07-21','',1,'none',NULL,'','','','','','','','Monthly stock request',NULL,'Credit','',NULL,'','','2026-08-10',NULL,0.00,0.00,0.00,0.00,0.00,0.00,69.000,NULL,'active','approved',10,'2026-10-31 01:01:13',10,'2026-10-30 19:00:25','2026-10-30 19:04:00'),(295,'requisition','DESREQ2026000002','2026-07-22','',1,'none',NULL,'','','','','','','','Monthly stock request',NULL,'Credit','',NULL,'','','2026-08-10',NULL,0.00,0.00,0.00,0.00,0.00,0.00,80.000,NULL,'active','approved',10,'2026-10-31 01:01:13',10,'2026-10-30 19:00:25','2026-10-30 19:04:00'),(296,'requisition','DESREQ2026000003','2026-07-23','',1,'none',NULL,'','','','','','','','Monthly stock request',NULL,'Credit','',NULL,'','','2026-08-10',NULL,0.00,0.00,0.00,0.00,0.00,0.00,51.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:00:26','2026-10-30 19:04:00'),(297,'purchase_order','DESPOR2026000001','2026-07-24','',1,'supplier',13,'','Dhaka Network House','01711100101','Motijheel, Dhaka','','','','Purchase order',NULL,'Credit','',NULL,'','30 days',NULL,NULL,1194400.00,0.00,0.00,0.00,1194400.00,0.00,155.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:00:26','2026-10-30 19:04:00'),(298,'purchase_order','DESPOR2026000002','2026-07-25','',1,'supplier',14,'','IDB Tech Traders','01711100102','IDB Bhaban, Agargaon','','','','Purchase order',NULL,'Credit','',NULL,'','30 days',NULL,NULL,755300.00,0.00,0.00,0.00,755300.00,0.00,139.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:00:26','2026-10-30 19:04:00'),(299,'purchase_order','DESPOR2026000003','2026-07-26','',1,'supplier',15,'','Global Power Imports','01711100103','Tejgaon, Dhaka','','','','Purchase order',NULL,'Credit','',NULL,'','30 days',NULL,NULL,805300.00,0.00,0.00,0.00,805300.00,0.00,125.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:00:27','2026-10-30 19:04:00'),(300,'purchase_order','DESPOR2026000004','2026-07-27','',1,'supplier',16,'','Star Computer Distribution','01711100104','Elephant Road, Dhaka','','','','Purchase order',NULL,'Credit','',NULL,'','30 days',NULL,NULL,787900.00,0.00,0.00,0.00,787900.00,0.00,140.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:00:27','2026-10-30 19:04:00'),(301,'purchase_order','DESPOR2026000005','2026-07-28','',1,'supplier',17,'','PrintLine Bangladesh','01711100105','Nawabpur, Dhaka','','','','Purchase order',NULL,'Credit','',NULL,'','30 days',NULL,NULL,855400.00,0.00,0.00,0.00,855400.00,0.00,155.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:00:27','2026-10-30 19:04:00'),(302,'purchase','DESPUR2026000001','2026-08-01','SUP-INV-1000',1,'supplier',13,'','Dhaka Network House','01711100101','Motijheel, Dhaka','','','','Stock purchase',NULL,'Cash','',NULL,'','',NULL,NULL,9484800.00,0.00,0.00,0.00,9484800.00,9484800.00,1305.000,NULL,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:28','2026-10-30 19:04:00'),(303,'purchase','DESPUR2026000002','2026-08-03','SUP-INV-1001',1,'supplier',14,'','IDB Tech Traders','01711100102','IDB Bhaban, Agargaon','','','','Stock purchase',NULL,'Credit','',NULL,'','',NULL,NULL,1955100.00,0.00,0.00,0.00,1955100.00,0.00,147.000,NULL,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:28','2026-10-30 19:04:00'),(304,'purchase','DESPUR2026000003','2026-08-05','SUP-INV-1002',1,'supplier',15,'','Global Power Imports','01711100103','Tejgaon, Dhaka','','','','Stock purchase',NULL,'Cash','',NULL,'','',NULL,NULL,1575900.00,0.00,0.00,0.00,1575900.00,1575900.00,205.000,NULL,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:28','2026-10-30 19:04:00'),(305,'purchase','DESPUR2026000004','2026-08-08','SUP-INV-1003',1,'supplier',16,'','Star Computer Distribution','01711100104','Elephant Road, Dhaka','','','','Stock purchase',NULL,'Credit','',NULL,'','',NULL,NULL,1038800.00,0.00,0.00,0.00,1038800.00,0.00,148.000,NULL,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:29','2026-10-30 19:04:00'),(306,'purchase','DESPUR2026000005','2026-08-12','SUP-INV-1004',1,'supplier',17,'','PrintLine Bangladesh','01711100105','Nawabpur, Dhaka','','','','Stock purchase',NULL,'Cash','',NULL,'','',NULL,NULL,1433200.00,0.00,0.00,0.00,1433200.00,1433200.00,199.000,NULL,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:29','2026-10-30 19:04:00'),(307,'purchase','DESPUR2026000006','2026-09-02','SUP-INV-1005',1,'supplier',13,'','Dhaka Network House','01711100101','Motijheel, Dhaka','','','','Stock purchase',NULL,'Credit','',NULL,'','',NULL,NULL,1945500.00,0.00,0.00,0.00,1945500.00,0.00,201.000,NULL,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:29','2026-10-30 19:04:00'),(308,'purchase','DESPUR2026000007','2026-09-10','SUP-INV-1006',1,'supplier',14,'','IDB Tech Traders','01711100102','IDB Bhaban, Agargaon','','','','Stock purchase',NULL,'Cash','',NULL,'','',NULL,NULL,1418000.00,0.00,0.00,0.00,1418000.00,1418000.00,173.000,NULL,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:30','2026-10-30 19:04:00'),(309,'purchase','DESPUR2026000008','2026-09-20','SUP-INV-1007',1,'supplier',15,'','Global Power Imports','01711100103','Tejgaon, Dhaka','','','','Stock purchase',NULL,'Credit','',NULL,'','',NULL,NULL,724440.00,0.00,0.00,0.00,724440.00,0.00,141.000,NULL,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:30','2026-10-30 19:04:00'),(310,'purchase','DESPUR2026000009','2026-10-01','SUP-INV-1008',1,'supplier',16,'','Star Computer Distribution','01711100104','Elephant Road, Dhaka','','','','Stock purchase',NULL,'Cash','',NULL,'','',NULL,NULL,1205260.00,0.00,0.00,0.00,1205260.00,1205260.00,204.000,NULL,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:30','2026-10-30 19:04:00'),(311,'purchase','DESPUR2026000010','2026-10-08','SUP-INV-1009',1,'supplier',17,'','PrintLine Bangladesh','01711100105','Nawabpur, Dhaka','','','','Stock purchase',NULL,'Credit','',NULL,'','',NULL,NULL,1624400.00,0.00,0.00,0.00,1624400.00,0.00,196.000,NULL,'active','approved',63,'2026-11-02 22:07:23',10,'2026-10-30 19:00:31','2026-11-02 16:07:23'),(312,'sales_invoice','SI2026000003','2026-08-04','PO-5000',1,'customer',91,'','Noor Fashion House','01811200209','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,7780.00,0.00,2.000,120,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:31','2026-10-30 19:04:00'),(313,'sales_invoice','SI2026000004','2026-08-06','PO-5001',1,'customer',83,'','Rahman Enterprise','01811200201','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,31600.00,0.00,2.000,121,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:32','2026-10-30 19:04:00'),(314,'sales_invoice','SI2026000005','2026-08-08','PO-5002',1,'customer',85,'','Bengal IT Solutions','01811200203','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,59850.00,0.00,5.000,122,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:32','2026-10-30 19:04:00'),(315,'sales_invoice','SI2026000006','2026-08-10','PO-5003',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,51072.00,0.00,4.000,123,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:32','2026-10-30 19:04:00'),(316,'sales_invoice','SI2026000007','2026-08-11','PO-5004',15,'customer',89,'','Amin & Sons','01811200207','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,47215.00,0.00,7.000,124,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:33','2026-10-30 19:05:01'),(317,'sales_invoice','SI2026000008','2026-08-13','PO-5005',1,'customer',91,'','Noor Fashion House','01811200209','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,33850.00,0.00,3.000,125,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:33','2026-10-30 19:04:00'),(318,'sales_invoice','SI2026000009','2026-08-15','PO-5006',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,35385.00,0.00,4.000,126,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:33','2026-10-30 19:04:00'),(319,'sales_invoice','SI2026000010','2026-08-17','PO-5007',1,'customer',92,'','Delta Garments Ltd','01811200210','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,89600.00,0.00,8.000,127,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:34','2026-10-30 19:04:00'),(320,'sales_invoice','SI2026000011','2026-08-18','PO-5008',1,'customer',87,'','City Pharma Ltd','01811200205','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,23600.00,0.00,2.000,128,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:34','2026-10-30 19:04:00'),(321,'sales_invoice','SI2026000012','2026-08-19','PO-5009',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,96520.00,0.00,7.000,129,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:34','2026-10-30 19:04:00'),(322,'sales_invoice','SI2026000013','2026-08-20','PO-5010',1,'customer',85,'','Bengal IT Solutions','01811200203','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,20776.00,0.00,5.000,130,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:35','2026-10-30 19:04:00'),(323,'sales_invoice','SI2026000014','2026-08-22','PO-5011',1,'customer',83,'','Rahman Enterprise','01811200201','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,33000.00,0.00,2.000,131,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:35','2026-10-30 19:04:00'),(324,'sales_invoice','SI2026000015','2026-08-23','PO-5012',1,'customer',84,'','Karim Traders','01811200202','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,61226.00,0.00,9.000,132,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:36','2026-10-30 19:04:00'),(325,'sales_invoice','SI2026000016','2026-08-24','PO-5013',1,'customer',92,'','Delta Garments Ltd','01811200210','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,80655.00,0.00,6.000,133,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:36','2026-10-30 19:04:00'),(326,'sales_invoice','SI2026000017','2026-08-26','PO-5014',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,11564.00,0.00,1.000,134,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:36','2026-10-30 19:04:00'),(327,'sales_invoice','SI2026000018','2026-08-27','PO-5015',1,'customer',87,'','City Pharma Ltd','01811200205','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,106890.00,0.00,7.000,135,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:37','2026-10-30 19:04:00'),(328,'sales_invoice','SI2026000019','2026-08-29','PO-5016',1,'customer',84,'','Karim Traders','01811200202','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,16500.00,0.00,1.000,136,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:37','2026-10-30 19:04:00'),(329,'sales_invoice','SI2026000020','2026-08-30','PO-5017',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,86070.00,0.00,9.000,137,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:37','2026-10-30 19:04:00'),(330,'sales_invoice','SI2026000021','2026-08-31','PO-5018',15,'customer',89,'','Amin & Sons','01811200207','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,63892.00,0.00,8.000,138,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:38','2026-10-30 19:05:01'),(331,'sales_invoice','SI2026000022','2026-09-01','PO-5019',1,'customer',83,'','Rahman Enterprise','01811200201','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,71834.00,0.00,7.000,139,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:38','2026-10-30 19:04:00'),(332,'sales_invoice','SI2026000023','2026-09-03','PO-5020',1,'customer',92,'','Delta Garments Ltd','01811200210','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,9310.00,0.00,1.000,140,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:38','2026-10-30 19:04:00'),(333,'sales_invoice','SI2026000024','2026-09-05','PO-5021',1,'customer',91,'','Noor Fashion House','01811200209','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,137340.00,0.00,7.000,141,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:39','2026-10-30 19:04:00'),(334,'sales_invoice','SI2026000025','2026-09-06','PO-5022',1,'customer',85,'','Bengal IT Solutions','01811200203','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,54880.00,0.00,8.000,142,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:39','2026-10-30 19:04:00'),(335,'sales_invoice','SI2026000026','2026-09-07','PO-5023',16,'customer',90,'','Green Valley Hospital','01811200208','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,99085.00,0.00,8.000,143,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:40','2026-10-30 19:05:01'),(336,'sales_invoice','SI2026000027','2026-09-08','PO-5024',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,129448.00,0.00,7.000,144,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:40','2026-10-30 19:04:00'),(337,'sales_invoice','SI2026000028','2026-09-09','PO-5025',1,'customer',84,'','Karim Traders','01811200202','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,10192.00,0.00,2.000,145,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:40','2026-10-30 19:04:00'),(338,'sales_invoice','SI2026000029','2026-09-10','PO-5026',15,'customer',89,'','Amin & Sons','01811200207','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,26200.00,0.00,3.000,146,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:41','2026-10-30 19:05:01'),(339,'sales_invoice','SI2026000030','2026-09-13','PO-5027',15,'customer',89,'','Amin & Sons','01811200207','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,29820.00,0.00,4.000,147,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:41','2026-10-30 19:05:01'),(340,'sales_invoice','SI2026000031','2026-09-14','PO-5028',1,'customer',87,'','City Pharma Ltd','01811200205','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,46930.00,0.00,5.000,148,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:41','2026-10-30 19:04:00'),(341,'sales_invoice','SI2026000032','2026-09-15','PO-5029',1,'customer',83,'','Rahman Enterprise','01811200201','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,68404.00,0.00,5.000,149,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:42','2026-10-30 19:04:00'),(342,'sales_invoice','SI2026000033','2026-09-16','PO-5030',1,'customer',85,'','Bengal IT Solutions','01811200203','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,16978.00,0.00,1.000,150,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:42','2026-10-30 19:04:00'),(343,'sales_invoice','SI2026000034','2026-09-19','PO-5031',1,'customer',87,'','City Pharma Ltd','01811200205','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,10400.00,0.00,2.000,151,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:42','2026-10-30 19:04:00'),(344,'sales_invoice','SI2026000035','2026-09-20','PO-5032',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,70854.00,0.00,7.000,152,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:43','2026-10-30 19:04:00'),(345,'sales_invoice','SI2026000036','2026-09-22','PO-5033',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,84289.00,0.00,5.000,153,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:43','2026-10-30 19:04:00'),(346,'sales_invoice','SI2026000037','2026-09-23','PO-5034',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,1995.00,0.00,1.000,154,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:43','2026-10-30 19:04:00'),(347,'sales_invoice','SI2026000038','2026-09-24','PO-5035',1,'customer',87,'','City Pharma Ltd','01811200205','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,50825.00,0.00,10.000,155,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:44','2026-10-30 19:04:00'),(348,'sales_invoice','SI2026000039','2026-09-26','PO-5036',1,'customer',85,'','Bengal IT Solutions','01811200203','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,8026.00,0.00,2.000,156,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:44','2026-10-30 19:04:00'),(349,'sales_invoice','SI2026000040','2026-09-27','PO-5037',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,20900.00,0.00,4.000,157,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:45','2026-10-30 19:04:00'),(350,'sales_invoice','SI2026000041','2026-09-28','PO-5038',1,'customer',85,'','Bengal IT Solutions','01811200203','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,32536.00,0.00,4.000,158,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:45','2026-10-30 19:04:00'),(351,'sales_invoice','SI2026000042','2026-09-30','PO-5039',16,'customer',90,'','Green Valley Hospital','01811200208','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,34780.00,0.00,5.000,159,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:45','2026-10-30 19:05:01'),(352,'sales_invoice','SI2026000043','2026-10-03','PO-5040',1,'customer',91,'','Noor Fashion House','01811200209','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,36480.00,0.00,3.000,160,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:46','2026-10-30 19:04:00'),(353,'sales_invoice','SI2026000044','2026-10-04','PO-5041',15,'customer',89,'','Amin & Sons','01811200207','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,14820.00,0.00,3.000,161,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:46','2026-10-30 19:05:01'),(354,'sales_invoice','SI2026000045','2026-10-06','PO-5042',16,'customer',90,'','Green Valley Hospital','01811200208','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,74288.00,0.00,10.000,162,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:46','2026-10-30 19:05:01'),(355,'sales_invoice','SI2026000046','2026-10-08','PO-5043',1,'customer',85,'','Bengal IT Solutions','01811200203','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,38400.00,0.00,3.000,163,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:47','2026-10-30 19:04:00'),(356,'sales_invoice','SI2026000047','2026-10-10','PO-5044',1,'customer',92,'','Delta Garments Ltd','01811200210','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,57100.00,0.00,4.000,164,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:47','2026-10-30 19:04:00'),(357,'sales_invoice','SI2026000048','2026-10-12','PO-5045',1,'customer',84,'','Karim Traders','01811200202','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,64210.00,0.00,9.000,165,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:47','2026-10-30 19:04:00'),(358,'sales_invoice','SI2026000049','2026-10-13','PO-5046',1,'customer',86,'','Sonar Bangla School','01811200204','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,73800.00,0.00,9.000,166,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:48','2026-10-30 19:04:00'),(359,'sales_invoice','SI2026000050','2026-10-14','PO-5047',1,'customer',85,'','Bengal IT Solutions','01811200203','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,46746.00,0.00,4.000,167,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:48','2026-10-30 19:04:00'),(360,'sales_invoice','SI2026000051','2026-10-17','PO-5048',1,'customer',83,'','Rahman Enterprise','01811200201','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,10374.00,0.00,2.000,168,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:48','2026-10-30 19:04:00'),(361,'sales_invoice','SI2026000052','2026-10-19','PO-5049',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,63080.00,0.00,7.000,169,'active','approved',10,'2026-10-31 01:01:11',10,'2026-10-30 19:00:49','2026-10-30 19:04:00'),(362,'sales_invoice','SI2026000053','2026-10-21','PO-5050',1,'customer',87,'','City Pharma Ltd','01811200205','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,35000.00,0.00,2.000,170,'active','pending',NULL,NULL,10,'2026-10-30 19:00:49','2026-10-30 19:04:00'),(363,'sales_invoice','SI2026000054','2026-10-22','PO-5051',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,14215.00,0.00,6.000,171,'active','pending',NULL,NULL,10,'2026-10-30 19:00:50','2026-10-30 19:04:00'),(364,'sales_invoice','SI2026000055','2026-10-24','PO-5052',1,'customer',92,'','Delta Garments Ltd','01811200210','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,11564.00,0.00,1.000,172,'active','pending',NULL,NULL,10,'2026-10-30 19:00:50','2026-10-30 19:04:00'),(365,'sales_invoice','SI2026000056','2026-10-25','PO-5053',1,'customer',92,'','Delta Garments Ltd','01811200210','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,45030.00,0.00,3.000,173,'active','pending',NULL,NULL,10,'2026-10-30 19:00:50','2026-10-30 19:04:00'),(366,'sales_invoice','SI2026000057','2026-10-26','PO-5054',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,25237.00,0.00,5.000,174,'active','pending',NULL,NULL,10,'2026-10-30 19:00:51','2026-10-30 19:04:00'),(367,'sales_invoice','SI2026000058','2026-10-27','PO-5055',1,'customer',92,'','Delta Garments Ltd','01811200210','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,12800.00,0.00,4.000,175,'active','pending',NULL,NULL,10,'2026-10-30 19:00:51','2026-10-30 19:04:00'),(368,'sales_invoice','SI2026000059','2026-10-29','PO-5056',1,'customer',91,'','Noor Fashion House','01811200209','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,86534.00,0.00,9.000,176,'active','pending',NULL,NULL,10,'2026-10-30 19:00:51','2026-10-30 19:04:00'),(369,'sales_invoice','SI2026000060','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,57000.00,0.00,2.000,177,'active','approved',10,'2026-11-05 21:59:36',10,'2026-10-30 19:00:57','2026-11-05 15:59:36'),(370,'sales_invoice','SI2026000061','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,9500.00,0.00,1.000,178,'active','pending',NULL,NULL,10,'2026-10-30 19:00:57','2026-10-30 19:04:00'),(371,'sales_invoice','SI2026000062','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,10400.00,0.00,2.000,179,'active','pending',NULL,NULL,10,'2026-10-30 19:00:57','2026-10-30 19:04:00'),(372,'sales_invoice','SI2026000063','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,3900.00,0.00,1.000,180,'active','pending',NULL,NULL,10,'2026-10-30 19:00:57','2026-10-30 19:04:00'),(373,'sales_invoice','SI2026000064','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,28500.00,0.00,1.000,181,'active','pending',NULL,NULL,10,'2026-10-30 19:00:58','2026-10-30 19:04:00'),(374,'sales_invoice','SI2026000065','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,15600.00,0.00,2.000,182,'active','pending',NULL,NULL,10,'2026-10-30 19:00:58','2026-10-30 19:04:00'),(375,'sales_invoice','SI2026000066','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,17500.00,0.00,1.000,183,'active','pending',NULL,NULL,10,'2026-10-30 19:00:58','2026-10-30 19:04:00'),(376,'sales_invoice','SI2026000067','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,2300.00,0.00,2.000,184,'active','pending',NULL,NULL,10,'2026-10-30 19:00:59','2026-10-30 19:04:00'),(377,'sales_invoice','SI2026000068','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,4200.00,0.00,2.000,185,'active','pending',NULL,NULL,10,'2026-10-30 19:00:59','2026-10-30 19:04:00'),(378,'sales_invoice','SI2026000069','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,11800.00,0.00,1.000,186,'active','approved',10,'2026-10-31 11:15:06',10,'2026-10-30 19:00:59','2026-10-31 05:15:06'),(379,'sales_invoice','SI2026000070','2026-10-22','SO2026000001',1,'customer',85,'','Bengal IT Solutions','01811200203','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,60100.00,0.00,3.000,187,'active','pending',NULL,NULL,10,'2026-10-30 19:01:02','2026-10-30 19:04:00'),(380,'sales_invoice','SI2026000071','2026-10-22','SO2026000002',1,'customer',83,'','Rahman Enterprise','01811200201','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,84000.00,0.00,6.000,188,'active','pending',NULL,NULL,10,'2026-10-30 19:01:03','2026-10-30 19:04:00'),(381,'sales_invoice','SI2026000072','2026-10-22','SO2026000003',1,'customer',87,'','City Pharma Ltd','01811200205','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,16400.00,0.00,4.000,189,'active','pending',NULL,NULL,10,'2026-10-30 19:01:03','2026-10-30 19:04:00'),(382,'sales_return','SR2026000001','2026-10-25','',1,'customer',90,'','Green Valley Hospital','01811200208','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,7800.00,0.00,1.000,159,'active','pending',NULL,NULL,10,'2026-10-30 19:01:03','2026-10-30 19:04:00'),(383,'sales_return','SR2026000002','2026-10-25','',1,'customer',84,'','Karim Traders','01811200202','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,5200.00,0.00,1.000,132,'active','pending',NULL,NULL,10,'2026-10-30 19:01:03','2026-10-30 19:04:00'),(384,'sales_return','SR2026000003','2026-10-25','',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,11800.00,0.00,1.000,144,'active','pending',NULL,NULL,10,'2026-10-30 19:01:04','2026-10-30 19:04:00'),(385,'sales_return','SR2026000004','2026-10-25','',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,17500.00,0.00,1.000,153,'active','pending',NULL,NULL,10,'2026-10-30 19:01:04','2026-10-30 19:04:00'),(386,'sales_return','SR2026000005','2026-10-25','',1,'customer',88,'','Hasan Computer Point','01811200206','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,12800.00,0.00,1.000,123,'active','pending',NULL,NULL,10,'2026-10-30 19:01:04','2026-10-30 19:04:00'),(387,'challan','DESCHL2026000001','2026-10-12','',1,'customer',84,'','Karim Traders','01811200202','Uttara Sector 7, Dhaka','','Tanvir Hasan','Uttara Sector 7, Dhaka','',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,2.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:01:05','2026-10-30 19:04:00'),(388,'challan','DESCHL2026000002','2026-10-13','',1,'customer',85,'','Bengal IT Solutions','01811200203','Banani, Dhaka','','Nusrat Jahan','Banani, Dhaka','',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,3.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:01:05','2026-10-30 19:04:00'),(389,'challan','DESCHL2026000003','2026-10-14','',1,'customer',83,'','Rahman Enterprise','01811200201','Mirpur 10, Dhaka','','Mahmud Alam','Mirpur 10, Dhaka','',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,4.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:01:05','2026-10-30 19:04:00'),(390,'challan','DESCHL2026000004','2026-10-15','',1,'customer',88,'','Hasan Computer Point','01811200206','Comilla Town','','Farhana Akter','Comilla Town','',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,4.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:01:06','2026-10-30 19:04:00'),(391,'challan','DESCHL2026000005','2026-10-16','',1,'customer',87,'','City Pharma Ltd','01811200205','Dhanmondi, Dhaka','','Tanvir Hasan','Dhanmondi, Dhaka','',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,2.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:01:06','2026-10-30 19:04:00'),(392,'quotation','DESPQI2026000001','2026-10-18','',1,'customer',85,'','Bengal IT Solutions','01811200203','Banani, Dhaka','','','','',NULL,'Credit','',NULL,'','Valid for 15 days',NULL,NULL,82900.00,0.00,0.00,0.00,82900.00,0.00,13.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:01:06','2026-10-30 19:04:00'),(393,'quotation','DESPQI2026000002','2026-10-19','',1,'customer',92,'','Delta Garments Ltd','01811200210','Savar, Dhaka','','','','',NULL,'Credit','',NULL,'','Valid for 15 days',NULL,NULL,117900.00,0.00,0.00,0.00,117900.00,0.00,8.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:01:06','2026-10-30 19:04:00'),(394,'quotation','DESPQI2026000003','2026-10-20','',1,'customer',90,'','Green Valley Hospital','01811200208','Sylhet','','','','',NULL,'Credit','',NULL,'','Valid for 15 days',NULL,NULL,110100.00,0.00,0.00,0.00,110100.00,0.00,9.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:01:07','2026-10-30 19:04:00'),(395,'quotation','DESPQI2026000004','2026-10-21','',1,'customer',87,'','City Pharma Ltd','01811200205','Dhanmondi, Dhaka','','','','',NULL,'Credit','',NULL,'','Valid for 15 days',NULL,NULL,57400.00,0.00,0.00,0.00,57400.00,0.00,10.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:01:07','2026-10-30 19:04:00'),(396,'stock_transfer','DESSTT2026000001','2026-09-05','',1,'none',NULL,'','','','','','','','Store transfer',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,10.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:01:07','2026-10-30 19:04:00'),(397,'stock_transfer','DESSTT2026000002','2026-09-11','',1,'none',NULL,'','','','','','','','Store transfer',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,5.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:01:08','2026-10-30 19:04:00'),(398,'stock_transfer','DESSTT2026000003','2026-09-17','',1,'none',NULL,'','','','','','','','Store transfer',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,4.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:01:08','2026-10-30 19:04:00'),(399,'stock_transfer','DESSTT2026000004','2026-09-23','',1,'none',NULL,'','','','','','','','Store transfer',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,7.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:01:08','2026-10-30 19:04:00'),(400,'purchase_return','DESPRT2026000001','2026-09-15','',1,'supplier',13,'','Dhaka Network House','','','','','','',NULL,'Credit','purchase',302,'DESPUR2026000001','',NULL,NULL,5000.00,0.00,0.00,0.00,5000.00,0.00,2.000,NULL,'active','approved',10,'2026-10-31 01:01:12',10,'2026-10-30 19:01:10','2026-10-30 19:04:00'),(401,'purchase_return','DESPRT2026000002','2026-09-15','',1,'supplier',14,'','IDB Tech Traders','','','','','','',NULL,'Credit','purchase',303,'DESPUR2026000002','',NULL,NULL,12400.00,0.00,0.00,0.00,12400.00,0.00,2.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:01:10','2026-10-30 19:04:00'),(402,'stock_in','DESSAI2026000001','2026-10-02','',1,'none',NULL,'','','','','','','','Stock count adjustment',NULL,'Credit','',NULL,'','',NULL,NULL,20800.00,0.00,0.00,0.00,20800.00,0.00,2.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:02:08','2026-10-30 19:04:00'),(403,'stock_in','DESSAI2026000002','2026-10-03','',1,'none',NULL,'','','','','','','','Stock count adjustment',NULL,'Credit','',NULL,'','',NULL,NULL,600.00,0.00,0.00,0.00,600.00,0.00,1.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:02:09','2026-10-30 19:04:00'),(404,'stock_out','DESSAO2026000001','2026-10-02','',1,'none',NULL,'','','','','','','','Stock count adjustment',NULL,'Credit','',NULL,'','',NULL,NULL,600.00,0.00,0.00,0.00,600.00,0.00,1.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:02:09','2026-10-30 19:04:00'),(405,'stock_out','DESSAO2026000002','2026-10-03','',1,'none',NULL,'','','','','','','','Stock count adjustment',NULL,'Credit','',NULL,'','',NULL,NULL,27800.00,0.00,0.00,0.00,27800.00,0.00,2.000,NULL,'active','pending',NULL,NULL,10,'2026-10-30 19:02:09','2026-10-30 19:04:00'),(406,'sales_invoice','SI2026000073','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','01121323232','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,9500.00,0.00,1.000,190,'active','pending',NULL,NULL,10,'2026-10-31 05:08:34','2026-10-31 05:08:34'),(407,'sales_invoice','SI2026000074','2026-10-31','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,6790.00,0.00,1.000,191,'active','pending',NULL,NULL,10,'2026-10-31 06:18:27','2026-10-31 06:18:27'),(408,'sales_invoice','SI2026000075','2026-11-02','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,42130.00,0.00,3.000,192,'active','pending',NULL,NULL,10,'2026-11-02 15:06:15','2026-11-02 15:06:15'),(409,'purchase_order','DESPOR2026000006','2026-11-02','SMP-PO-1',1,'supplier',28,'','SAMPLE Tech Distributors Ltd','01811000201','Motijheel, Dhaka','','','','SAMPLE purchase order',NULL,'Credit','',NULL,'','','2026-11-10',NULL,262000.00,0.00,0.00,0.00,262000.00,0.00,30.000,NULL,'active','pending',NULL,NULL,63,'2026-11-02 16:00:42','2026-11-02 16:00:42'),(410,'purchase','DESPUR2026000011','2026-11-02','SMP-PUR-1',1,'supplier',28,'','SAMPLE Tech Distributors Ltd','01811000201','Motijheel, Dhaka','','','','SAMPLE purchase, stock in',NULL,'Credit','',NULL,'','',NULL,NULL,262000.00,0.00,0.00,0.00,262000.00,0.00,30.000,NULL,'active','pending',NULL,NULL,63,'2026-11-02 16:00:45','2026-11-02 16:00:45'),(411,'stock_in','DESSAI2026000003','2026-11-02','SMP-AMD-1',1,'none',NULL,'','','','','','','','SAMPLE opening stock',NULL,'Credit','',NULL,'','',NULL,NULL,13000.00,0.00,0.00,0.00,13000.00,0.00,5.000,NULL,'active','pending',NULL,NULL,63,'2026-11-02 16:00:48','2026-11-02 16:00:48'),(412,'stock_transfer','DESSTT2026000005','2026-11-02','SMP-TRF-1',1,'none',NULL,'','','','','','','','SAMPLE transfer',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,2.000,NULL,'active','pending',NULL,NULL,63,'2026-11-02 16:00:50','2026-11-02 16:00:50'),(413,'sales_invoice','SI2026000076','2026-11-02','SMP-INV-1',1,'customer',94,'','SAMPLE Retail Customer','01611000401','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,32000.00,0.00,3.000,193,'active','approved',10,'2026-11-05 21:59:33',63,'2026-11-02 16:00:53','2026-11-05 15:59:33'),(414,'sales_invoice','SI2026000077','2026-11-02','POS',1,'customer',NULL,'','SAMPLE Walk-in','01511000501','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,28500.00,0.00,2.000,194,'active','pending',NULL,NULL,63,'2026-11-02 16:00:56','2026-11-02 16:00:56'),(415,'challan','DESCHL2026000006','2026-11-02','SMP-CH-1',1,'customer',93,'','SAMPLE Corporate Buyer Ltd','01911000301','Gulshan 1, Dhaka','Nasrin','SAMPLE Rahim Uddin','Gulshan 1 office','SAMPLE challan',NULL,'Credit','',NULL,'','',NULL,NULL,0.00,0.00,0.00,0.00,0.00,0.00,2.000,NULL,'active','pending',NULL,NULL,63,'2026-11-02 16:01:01','2026-11-02 16:01:01'),(416,'sales_invoice','SI2026000078','2026-11-02','SMP-INV-1',1,'customer',94,'','SAMPLE Retail Customer','01611000401','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,32000.00,0.00,3.000,195,'active','pending',NULL,NULL,63,'2026-11-02 16:01:43','2026-11-02 16:01:43'),(417,'sales_invoice','SI2026000079','2026-11-02','SMP-SOB-1',1,'customer',93,'','SAMPLE Corporate Buyer Ltd','01911000301','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,83700.00,0.00,6.000,196,'active','pending',NULL,NULL,63,'2026-11-02 16:05:48','2026-11-02 16:05:48'),(418,'sales_return','SR2026000006','2026-11-02','',1,'customer',94,'','SAMPLE Retail Customer','01611000401','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,3500.00,0.00,1.000,195,'active','pending',NULL,NULL,63,'2026-11-02 16:05:51','2026-11-02 16:05:51'),(419,'sales_invoice','SI2026000080','2026-11-05','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,11800.00,0.00,1.000,197,'active','pending',NULL,NULL,10,'2026-11-05 15:52:45','2026-11-05 15:52:45'),(420,'sales_invoice','SI2026000081','2026-11-05','POS',1,'customer',NULL,'','Walk-in Customer','017123123213','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,16890.00,0.00,1.000,198,'active','pending',NULL,NULL,10,'2026-11-05 15:53:43','2026-11-05 15:53:43'),(421,'sales_invoice','SI2026000082','2026-11-05','POS',1,'customer',NULL,'','Walk-in Customer','','','','','','',NULL,'Credit','',NULL,'',NULL,NULL,NULL,0.00,0.00,0.00,0.00,28490.00,0.00,1.000,199,'active','pending',NULL,NULL,10,'2026-11-05 15:54:53','2026-11-05 15:54:53');
/*!40000 ALTER TABLE `inv_docs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inv_price_history`
--

DROP TABLE IF EXISTS `inv_price_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_price_history` (
  `history_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `price_field` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'default_unit_price, retail_a..c, dealer_a..c, mrp, lower_price, higher_price',
  `old_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `new_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `changed_by` bigint unsigned DEFAULT NULL,
  `changed_by_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `ix_inv_price_history_product` (`product_id`,`changed_at`),
  CONSTRAINT `fk_inv_price_history_product` FOREIGN KEY (`product_id`) REFERENCES `sales_products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_price_history`
--

LOCK TABLES `inv_price_history` WRITE;
/*!40000 ALTER TABLE `inv_price_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `inv_price_history` ENABLE KEYS */;
UNLOCK TABLES;

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

LOCK TABLES `inv_product_prices` WRITE;
/*!40000 ALTER TABLE `inv_product_prices` DISABLE KEYS */;
INSERT INTO `inv_product_prices` VALUES (67,3200.00,3136.00,3072.00,2976.00,2912.00,2880.00,3456.00,2880.00,3520.00,NULL,'2026-10-30 19:02:09'),(68,28500.00,27930.00,27360.00,26505.00,25935.00,25650.00,30780.00,25650.00,31350.00,NULL,'2026-10-30 19:02:09'),(69,2100.00,2058.00,2016.00,1953.00,1911.00,1890.00,2268.00,1890.00,2310.00,NULL,'2026-10-30 19:02:10'),(70,16500.00,16170.00,15840.00,15345.00,15015.00,14850.00,17820.00,14850.00,18150.00,NULL,'2026-10-30 19:02:10'),(71,7800.00,7644.00,7488.00,7254.00,7098.00,7020.00,8424.00,7020.00,8580.00,NULL,'2026-10-30 19:02:10'),(72,9500.00,9310.00,9120.00,8835.00,8645.00,8550.00,10260.00,8550.00,10450.00,NULL,'2026-10-30 19:02:10'),(73,850.00,833.00,816.00,790.00,774.00,765.00,918.00,765.00,935.00,NULL,'2026-10-30 19:02:10'),(74,1150.00,1127.00,1104.00,1070.00,1046.00,1035.00,1242.00,1035.00,1265.00,NULL,'2026-10-30 19:02:10'),(75,12800.00,12544.00,12288.00,11904.00,11648.00,11520.00,13824.00,11520.00,14080.00,NULL,'2026-10-30 19:02:10'),(76,16900.00,16562.00,16224.00,15717.00,15379.00,15210.00,18252.00,15210.00,18590.00,NULL,'2026-10-30 19:02:10'),(77,6800.00,6664.00,6528.00,6324.00,6188.00,6120.00,7344.00,6120.00,7480.00,NULL,'2026-10-30 19:02:10'),(78,11500.00,11270.00,11040.00,10695.00,10465.00,10350.00,12420.00,10350.00,12650.00,NULL,'2026-10-30 19:02:10'),(79,6400.00,6272.00,6144.00,5952.00,5824.00,5760.00,6912.00,5760.00,7040.00,NULL,'2026-10-30 19:02:10'),(80,11800.00,11564.00,11328.00,10974.00,10738.00,10620.00,12744.00,10620.00,12980.00,NULL,'2026-10-30 19:02:10'),(81,3900.00,3822.00,3744.00,3627.00,3549.00,3510.00,4212.00,3510.00,4290.00,NULL,'2026-10-30 19:02:10'),(82,17500.00,17150.00,16800.00,16275.00,15925.00,15750.00,18900.00,15750.00,19250.00,NULL,'2026-10-30 19:02:10'),(83,15800.00,15484.00,15168.00,14694.00,14378.00,14220.00,17064.00,14220.00,17380.00,NULL,'2026-10-30 19:02:10'),(84,5200.00,5096.00,4992.00,4836.00,4732.00,4680.00,5616.00,4680.00,5720.00,NULL,'2026-10-30 19:02:11'),(85,4600.00,4508.00,4416.00,4278.00,4186.00,4140.00,4968.00,4140.00,5060.00,NULL,'2026-10-30 19:02:11');
/*!40000 ALTER TABLE `inv_product_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inv_sequences`
--

DROP TABLE IF EXISTS `inv_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_sequences` (
  `doc_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seq_year` smallint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`doc_code`,`seq_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_sequences`
--

LOCK TABLES `inv_sequences` WRITE;
/*!40000 ALTER TABLE `inv_sequences` DISABLE KEYS */;
INSERT INTO `inv_sequences` VALUES ('CHL',2026,6),('POR',2026,6),('PQI',2026,4),('PRT',2026,2),('PUR',2026,11),('REQ',2026,3),('SAI',2026,3),('SAO',2026,2),('STT',2026,5);
/*!40000 ALTER TABLE `inv_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inv_serial_moves`
--

DROP TABLE IF EXISTS `inv_serial_moves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_serial_moves` (
  `serial_move_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serial_id` bigint unsigned NOT NULL,
  `direction` enum('in','out') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `move_date` date NOT NULL,
  `doc_type` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `doc_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `transaction_label` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `party_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `branch_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`serial_move_id`),
  KEY `ix_inv_serial_moves_serial` (`serial_id`,`serial_move_id`),
  KEY `ix_inv_serial_moves_doc` (`doc_id`),
  CONSTRAINT `fk_inv_serial_move_serial` FOREIGN KEY (`serial_id`) REFERENCES `inv_serials` (`serial_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=356 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_serial_moves`
--

LOCK TABLES `inv_serial_moves` WRITE;
/*!40000 ALTER TABLE `inv_serial_moves` DISABLE KEYS */;
/*!40000 ALTER TABLE `inv_serial_moves` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inv_serials`
--

DROP TABLE IF EXISTS `inv_serials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_serials` (
  `serial_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `serial_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `in_stock` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`serial_id`),
  UNIQUE KEY `uq_inv_serials` (`product_id`,`serial_no`),
  KEY `ix_inv_serials_no` (`serial_no`)
) ENGINE=InnoDB AUTO_INCREMENT=245 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_serials`
--

LOCK TABLES `inv_serials` WRITE;
/*!40000 ALTER TABLE `inv_serials` DISABLE KEYS */;
/*!40000 ALTER TABLE `inv_serials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inv_settings`
--

DROP TABLE IF EXISTS `inv_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_settings` (
  `setting_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_settings`
--

LOCK TABLES `inv_settings` WRITE;
/*!40000 ALTER TABLE `inv_settings` DISABLE KEYS */;
INSERT INTO `inv_settings` VALUES ('default_store','Main Store'),('doc_prefix','DES'),('member_point_value','1'),('member_points_per_100','1'),('sales_stock_mode','deduct');
/*!40000 ALTER TABLE `inv_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inv_stock_moves`
--

DROP TABLE IF EXISTS `inv_stock_moves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_moves` (
  `move_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int unsigned DEFAULT NULL,
  `move_date` date NOT NULL,
  `qty_in` decimal(15,3) NOT NULL DEFAULT '0.000',
  `qty_out` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `doc_type` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `doc_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `line_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`move_id`),
  KEY `ix_inv_moves_stock` (`product_id`,`store_name`,`qty_in`,`qty_out`),
  KEY `ix_inv_moves_doc` (`doc_id`),
  KEY `ix_inv_moves_line` (`line_id`),
  KEY `ix_inv_moves_date` (`move_date`,`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=489 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_stock_moves`
--

LOCK TABLES `inv_stock_moves` WRITE;
/*!40000 ALTER TABLE `inv_stock_moves` DISABLE KEYS */;
INSERT INTO `inv_stock_moves` VALUES (232,67,'Main Store',1,'2026-08-01',71.000,0.000,2500.00,'purchase',302,'DESPUR2026000001',342,'2026-10-30 19:00:28'),(233,68,'Main Store',1,'2026-08-01',47.000,0.000,23000.00,'purchase',302,'DESPUR2026000001',343,'2026-10-30 19:00:28'),(234,69,'Main Store',1,'2026-08-01',67.000,0.000,1600.00,'purchase',302,'DESPUR2026000001',344,'2026-10-30 19:00:28'),(235,70,'Main Store',1,'2026-08-01',72.000,0.000,13200.00,'purchase',302,'DESPUR2026000001',345,'2026-10-30 19:00:28'),(236,71,'Main Store',1,'2026-08-01',88.000,0.000,6200.00,'purchase',302,'DESPUR2026000001',346,'2026-10-30 19:00:28'),(237,72,'Main Store',1,'2026-08-01',78.000,0.000,7600.00,'purchase',302,'DESPUR2026000001',347,'2026-10-30 19:00:28'),(238,73,'Main Store',1,'2026-08-01',71.000,0.000,600.00,'purchase',302,'DESPUR2026000001',348,'2026-10-30 19:00:28'),(239,74,'Main Store',1,'2026-08-01',65.000,0.000,820.00,'purchase',302,'DESPUR2026000001',349,'2026-10-30 19:00:28'),(240,75,'Main Store',1,'2026-08-01',73.000,0.000,10400.00,'purchase',302,'DESPUR2026000001',350,'2026-10-30 19:00:28'),(241,76,'Main Store',1,'2026-08-01',56.000,0.000,13900.00,'purchase',302,'DESPUR2026000001',351,'2026-10-30 19:00:28'),(242,77,'Main Store',1,'2026-08-01',66.000,0.000,5400.00,'purchase',302,'DESPUR2026000001',352,'2026-10-30 19:00:28'),(243,78,'Main Store',1,'2026-08-01',76.000,0.000,9100.00,'purchase',302,'DESPUR2026000001',353,'2026-10-30 19:00:28'),(244,79,'Main Store',1,'2026-08-01',70.000,0.000,5100.00,'purchase',302,'DESPUR2026000001',354,'2026-10-30 19:00:28'),(245,80,'Main Store',1,'2026-08-01',72.000,0.000,9500.00,'purchase',302,'DESPUR2026000001',355,'2026-10-30 19:00:28'),(246,81,'Main Store',1,'2026-08-01',73.000,0.000,3000.00,'purchase',302,'DESPUR2026000001',356,'2026-10-30 19:00:28'),(247,82,'Main Store',1,'2026-08-01',41.000,0.000,14300.00,'purchase',302,'DESPUR2026000001',357,'2026-10-30 19:00:28'),(248,83,'Main Store',1,'2026-08-01',76.000,0.000,12900.00,'purchase',302,'DESPUR2026000001',358,'2026-10-30 19:00:28'),(249,84,'Main Store',1,'2026-08-01',54.000,0.000,3900.00,'purchase',302,'DESPUR2026000001',359,'2026-10-30 19:00:28'),(250,85,'Main Store',1,'2026-08-01',89.000,0.000,3500.00,'purchase',302,'DESPUR2026000001',360,'2026-10-30 19:00:28'),(251,71,'Main Store',1,'2026-08-03',28.000,0.000,6200.00,'purchase',303,'DESPUR2026000002',361,'2026-10-30 19:00:28'),(252,68,'Main Store',1,'2026-08-03',30.000,0.000,23000.00,'purchase',303,'DESPUR2026000002',362,'2026-10-30 19:00:28'),(253,83,'Main Store',1,'2026-08-03',19.000,0.000,12900.00,'purchase',303,'DESPUR2026000002',363,'2026-10-30 19:00:28'),(254,70,'Main Store',1,'2026-08-03',30.000,0.000,13200.00,'purchase',303,'DESPUR2026000002',364,'2026-10-30 19:00:28'),(255,78,'Main Store',1,'2026-08-03',22.000,0.000,9100.00,'purchase',303,'DESPUR2026000002',365,'2026-10-30 19:00:28'),(256,76,'Main Store',1,'2026-08-03',18.000,0.000,13900.00,'purchase',303,'DESPUR2026000002',366,'2026-10-30 19:00:28'),(257,81,'Main Store',1,'2026-08-05',32.000,0.000,3000.00,'purchase',304,'DESPUR2026000003',367,'2026-10-30 19:00:29'),(258,82,'Main Store',1,'2026-08-05',40.000,0.000,14300.00,'purchase',304,'DESPUR2026000003',368,'2026-10-30 19:00:29'),(259,75,'Main Store',1,'2026-08-05',35.000,0.000,10400.00,'purchase',304,'DESPUR2026000003',369,'2026-10-30 19:00:29'),(260,79,'Main Store',1,'2026-08-05',31.000,0.000,5100.00,'purchase',304,'DESPUR2026000003',370,'2026-10-30 19:00:29'),(261,77,'Main Store',1,'2026-08-05',37.000,0.000,5400.00,'purchase',304,'DESPUR2026000003',371,'2026-10-30 19:00:29'),(262,71,'Main Store',1,'2026-08-05',30.000,0.000,6200.00,'purchase',304,'DESPUR2026000003',372,'2026-10-30 19:00:29'),(263,79,'Main Store',1,'2026-08-08',15.000,0.000,5100.00,'purchase',305,'DESPUR2026000004',373,'2026-10-30 19:00:29'),(264,80,'Main Store',1,'2026-08-08',16.000,0.000,9500.00,'purchase',305,'DESPUR2026000004',374,'2026-10-30 19:00:29'),(265,73,'Main Store',1,'2026-08-08',19.000,0.000,600.00,'purchase',305,'DESPUR2026000004',375,'2026-10-30 19:00:29'),(266,76,'Main Store',1,'2026-08-08',35.000,0.000,13900.00,'purchase',305,'DESPUR2026000004',376,'2026-10-30 19:00:29'),(267,84,'Main Store',1,'2026-08-08',34.000,0.000,3900.00,'purchase',305,'DESPUR2026000004',377,'2026-10-30 19:00:29'),(268,71,'Main Store',1,'2026-08-08',29.000,0.000,6200.00,'purchase',305,'DESPUR2026000004',378,'2026-10-30 19:00:29'),(269,83,'Main Store',1,'2026-08-12',30.000,0.000,12900.00,'purchase',306,'DESPUR2026000005',379,'2026-10-30 19:00:29'),(270,82,'Main Store',1,'2026-08-12',29.000,0.000,14300.00,'purchase',306,'DESPUR2026000005',380,'2026-10-30 19:00:29'),(271,78,'Main Store',1,'2026-08-12',31.000,0.000,9100.00,'purchase',306,'DESPUR2026000005',381,'2026-10-30 19:00:29'),(272,73,'Main Store',1,'2026-08-12',39.000,0.000,600.00,'purchase',306,'DESPUR2026000005',382,'2026-10-30 19:00:29'),(273,71,'Main Store',1,'2026-08-12',30.000,0.000,6200.00,'purchase',306,'DESPUR2026000005',383,'2026-10-30 19:00:29'),(274,85,'Main Store',1,'2026-08-12',40.000,0.000,3500.00,'purchase',306,'DESPUR2026000005',384,'2026-10-30 19:00:29'),(275,80,'Main Store',1,'2026-09-02',40.000,0.000,9500.00,'purchase',307,'DESPUR2026000006',385,'2026-10-30 19:00:29'),(276,84,'Main Store',1,'2026-09-02',18.000,0.000,3900.00,'purchase',307,'DESPUR2026000006',386,'2026-10-30 19:00:29'),(277,68,'Main Store',1,'2026-09-02',29.000,0.000,23000.00,'purchase',307,'DESPUR2026000006',387,'2026-10-30 19:00:29'),(278,76,'Main Store',1,'2026-09-02',37.000,0.000,13900.00,'purchase',307,'DESPUR2026000006',388,'2026-10-30 19:00:29'),(279,72,'Main Store',1,'2026-09-02',37.000,0.000,7600.00,'purchase',307,'DESPUR2026000006',389,'2026-10-30 19:00:29'),(280,74,'Main Store',1,'2026-09-02',40.000,0.000,820.00,'purchase',307,'DESPUR2026000006',390,'2026-10-30 19:00:29'),(281,83,'Main Store',1,'2026-09-10',38.000,0.000,12900.00,'purchase',308,'DESPUR2026000007',391,'2026-10-30 19:00:30'),(282,75,'Main Store',1,'2026-09-10',30.000,0.000,10400.00,'purchase',308,'DESPUR2026000007',392,'2026-10-30 19:00:30'),(283,81,'Main Store',1,'2026-09-10',17.000,0.000,3000.00,'purchase',308,'DESPUR2026000007',393,'2026-10-30 19:00:30'),(284,72,'Main Store',1,'2026-09-10',35.000,0.000,7600.00,'purchase',308,'DESPUR2026000007',394,'2026-10-30 19:00:30'),(285,73,'Main Store',1,'2026-09-10',23.000,0.000,600.00,'purchase',308,'DESPUR2026000007',395,'2026-10-30 19:00:30'),(286,80,'Main Store',1,'2026-09-10',30.000,0.000,9500.00,'purchase',308,'DESPUR2026000007',396,'2026-10-30 19:00:30'),(287,78,'Main Store',1,'2026-09-20',15.000,0.000,9100.00,'purchase',309,'DESPUR2026000008',397,'2026-10-30 19:00:30'),(288,70,'Main Store',1,'2026-09-20',17.000,0.000,13200.00,'purchase',309,'DESPUR2026000008',398,'2026-10-30 19:00:30'),(289,73,'Main Store',1,'2026-09-20',15.000,0.000,600.00,'purchase',309,'DESPUR2026000008',399,'2026-10-30 19:00:30'),(290,80,'Main Store',1,'2026-09-20',29.000,0.000,9500.00,'purchase',309,'DESPUR2026000008',400,'2026-10-30 19:00:30'),(291,69,'Main Store',1,'2026-09-20',33.000,0.000,1600.00,'purchase',309,'DESPUR2026000008',401,'2026-10-30 19:00:30'),(292,74,'Main Store',1,'2026-09-20',32.000,0.000,820.00,'purchase',309,'DESPUR2026000008',402,'2026-10-30 19:00:30'),(293,69,'Main Store',1,'2026-10-01',39.000,0.000,1600.00,'purchase',310,'DESPUR2026000009',403,'2026-10-30 19:00:31'),(294,73,'Main Store',1,'2026-10-01',32.000,0.000,600.00,'purchase',310,'DESPUR2026000009',404,'2026-10-30 19:00:31'),(295,74,'Main Store',1,'2026-10-01',23.000,0.000,820.00,'purchase',310,'DESPUR2026000009',405,'2026-10-30 19:00:31'),(296,70,'Main Store',1,'2026-10-01',36.000,0.000,13200.00,'purchase',310,'DESPUR2026000009',406,'2026-10-30 19:00:31'),(297,76,'Main Store',1,'2026-10-01',39.000,0.000,13900.00,'purchase',310,'DESPUR2026000009',407,'2026-10-30 19:00:31'),(298,67,'Main Store',1,'2026-10-01',35.000,0.000,2500.00,'purchase',310,'DESPUR2026000009',408,'2026-10-30 19:00:31'),(299,78,'Main Store',1,'2026-10-08',33.000,0.000,9100.00,'purchase',311,'DESPUR2026000010',409,'2026-10-30 19:00:31'),(300,71,'Main Store',1,'2026-10-08',39.000,0.000,6200.00,'purchase',311,'DESPUR2026000010',410,'2026-10-30 19:00:31'),(301,67,'Main Store',1,'2026-10-08',30.000,0.000,2500.00,'purchase',311,'DESPUR2026000010',411,'2026-10-30 19:00:31'),(302,77,'Main Store',1,'2026-10-08',37.000,0.000,5400.00,'purchase',311,'DESPUR2026000010',412,'2026-10-30 19:00:31'),(303,82,'Main Store',1,'2026-10-08',38.000,0.000,14300.00,'purchase',311,'DESPUR2026000010',413,'2026-10-30 19:00:31'),(304,76,'Main Store',1,'2026-10-08',19.000,0.000,13900.00,'purchase',311,'DESPUR2026000010',414,'2026-10-30 19:00:31'),(305,81,'Main Store',1,'2026-08-04',0.000,2.000,3900.00,'sales_invoice',312,'SI2026000003',415,'2026-10-30 19:00:31'),(306,83,'Main Store',1,'2026-08-06',0.000,2.000,15800.00,'sales_invoice',313,'SI2026000004',416,'2026-10-30 19:00:32'),(307,75,'Main Store',1,'2026-08-08',0.000,4.000,12800.00,'sales_invoice',314,'SI2026000005',417,'2026-10-30 19:00:32'),(308,80,'Main Store',1,'2026-08-08',0.000,1.000,11800.00,'sales_invoice',314,'SI2026000005',418,'2026-10-30 19:00:32'),(309,75,'Main Store',1,'2026-08-10',0.000,4.000,12800.00,'sales_invoice',315,'SI2026000006',419,'2026-10-30 19:00:32'),(310,81,'Main Store',15,'2026-08-11',0.000,3.000,3900.00,'sales_invoice',316,'SI2026000007',420,'2026-10-30 19:00:33'),(311,72,'Main Store',15,'2026-08-11',0.000,4.000,9500.00,'sales_invoice',316,'SI2026000007',421,'2026-10-30 19:00:33'),(312,70,'Main Store',1,'2026-08-13',0.000,2.000,16500.00,'sales_invoice',317,'SI2026000008',422,'2026-10-30 19:00:33'),(313,73,'Main Store',1,'2026-08-13',0.000,1.000,850.00,'sales_invoice',317,'SI2026000008',423,'2026-10-30 19:00:33'),(314,84,'Main Store',1,'2026-08-15',0.000,1.000,5200.00,'sales_invoice',318,'SI2026000009',424,'2026-10-30 19:00:33'),(315,72,'Main Store',1,'2026-08-15',0.000,3.000,9500.00,'sales_invoice',318,'SI2026000009',425,'2026-10-30 19:00:33'),(316,73,'Main Store',1,'2026-08-17',0.000,2.000,850.00,'sales_invoice',319,'SI2026000010',426,'2026-10-30 19:00:34'),(317,70,'Main Store',1,'2026-08-17',0.000,3.000,16500.00,'sales_invoice',319,'SI2026000010',427,'2026-10-30 19:00:34'),(318,75,'Main Store',1,'2026-08-17',0.000,3.000,12800.00,'sales_invoice',319,'SI2026000010',428,'2026-10-30 19:00:34'),(319,80,'Main Store',1,'2026-08-18',0.000,2.000,11800.00,'sales_invoice',320,'SI2026000011',429,'2026-10-30 19:00:34'),(320,72,'Main Store',1,'2026-08-19',0.000,3.000,9500.00,'sales_invoice',321,'SI2026000012',430,'2026-10-30 19:00:34'),(321,70,'Main Store',1,'2026-08-19',0.000,3.000,16500.00,'sales_invoice',321,'SI2026000012',431,'2026-10-30 19:00:34'),(322,83,'Main Store',1,'2026-08-19',0.000,1.000,15800.00,'sales_invoice',321,'SI2026000012',432,'2026-10-30 19:00:34'),(323,75,'Main Store',1,'2026-08-20',0.000,1.000,12800.00,'sales_invoice',322,'SI2026000013',433,'2026-10-30 19:00:35'),(324,69,'Main Store',1,'2026-08-20',0.000,4.000,2100.00,'sales_invoice',322,'SI2026000013',434,'2026-10-30 19:00:35'),(325,70,'Main Store',1,'2026-08-22',0.000,2.000,16500.00,'sales_invoice',323,'SI2026000014',435,'2026-10-30 19:00:35'),(326,84,'Main Store',1,'2026-08-23',0.000,4.000,5200.00,'sales_invoice',324,'SI2026000015',436,'2026-10-30 19:00:36'),(327,78,'Main Store',1,'2026-08-23',0.000,1.000,11500.00,'sales_invoice',324,'SI2026000015',437,'2026-10-30 19:00:36'),(328,77,'Main Store',1,'2026-08-23',0.000,4.000,6800.00,'sales_invoice',324,'SI2026000015',438,'2026-10-30 19:00:36'),(329,80,'Main Store',1,'2026-08-24',0.000,3.000,11800.00,'sales_invoice',325,'SI2026000016',439,'2026-10-30 19:00:36'),(330,70,'Main Store',1,'2026-08-24',0.000,3.000,16500.00,'sales_invoice',325,'SI2026000016',440,'2026-10-30 19:00:36'),(331,80,'Main Store',1,'2026-08-26',0.000,1.000,11800.00,'sales_invoice',326,'SI2026000017',441,'2026-10-30 19:00:36'),(332,70,'Main Store',1,'2026-08-27',0.000,3.000,16500.00,'sales_invoice',327,'SI2026000018',442,'2026-10-30 19:00:37'),(333,76,'Main Store',1,'2026-08-27',0.000,1.000,16900.00,'sales_invoice',327,'SI2026000018',443,'2026-10-30 19:00:37'),(334,80,'Main Store',1,'2026-08-27',0.000,3.000,11800.00,'sales_invoice',327,'SI2026000018',444,'2026-10-30 19:00:37'),(335,70,'Main Store',1,'2026-08-29',0.000,1.000,16500.00,'sales_invoice',328,'SI2026000019',445,'2026-10-30 19:00:37'),(336,76,'Main Store',1,'2026-08-30',0.000,2.000,16900.00,'sales_invoice',329,'SI2026000020',446,'2026-10-30 19:00:37'),(337,67,'Main Store',1,'2026-08-30',0.000,3.000,3200.00,'sales_invoice',329,'SI2026000020',447,'2026-10-30 19:00:37'),(338,80,'Main Store',1,'2026-08-30',0.000,4.000,11800.00,'sales_invoice',329,'SI2026000020',448,'2026-10-30 19:00:37'),(339,74,'Main Store',15,'2026-08-31',0.000,1.000,1150.00,'sales_invoice',330,'SI2026000021',449,'2026-10-30 19:00:38'),(340,71,'Main Store',15,'2026-08-31',0.000,4.000,7800.00,'sales_invoice',330,'SI2026000021',450,'2026-10-30 19:00:38'),(341,72,'Main Store',15,'2026-08-31',0.000,3.000,9500.00,'sales_invoice',330,'SI2026000021',451,'2026-10-30 19:00:38'),(342,84,'Main Store',1,'2026-09-01',0.000,4.000,5200.00,'sales_invoice',331,'SI2026000022',452,'2026-10-30 19:00:38'),(343,82,'Main Store',1,'2026-09-01',0.000,3.000,17500.00,'sales_invoice',331,'SI2026000022',453,'2026-10-30 19:00:38'),(344,72,'Main Store',1,'2026-09-03',0.000,1.000,9500.00,'sales_invoice',332,'SI2026000023',454,'2026-10-30 19:00:38'),(345,77,'Main Store',1,'2026-09-05',0.000,2.000,6800.00,'sales_invoice',333,'SI2026000024',455,'2026-10-30 19:00:39'),(346,68,'Main Store',1,'2026-09-05',0.000,4.000,28500.00,'sales_invoice',333,'SI2026000024',456,'2026-10-30 19:00:39'),(347,67,'Main Store',1,'2026-09-05',0.000,1.000,3200.00,'sales_invoice',333,'SI2026000024',457,'2026-10-30 19:00:39'),(348,79,'Main Store',1,'2026-09-06',0.000,3.000,6400.00,'sales_invoice',334,'SI2026000025',458,'2026-10-30 19:00:39'),(349,78,'Main Store',1,'2026-09-06',0.000,3.000,11500.00,'sales_invoice',334,'SI2026000025',459,'2026-10-30 19:00:39'),(350,74,'Main Store',1,'2026-09-06',0.000,2.000,1150.00,'sales_invoice',334,'SI2026000025',460,'2026-10-30 19:00:39'),(351,82,'Main Store',16,'2026-09-07',0.000,2.000,17500.00,'sales_invoice',335,'SI2026000026',461,'2026-10-30 19:00:40'),(352,76,'Main Store',16,'2026-09-07',0.000,4.000,16900.00,'sales_invoice',335,'SI2026000026',462,'2026-10-30 19:00:40'),(353,73,'Main Store',16,'2026-09-07',0.000,2.000,850.00,'sales_invoice',335,'SI2026000026',463,'2026-10-30 19:00:40'),(354,80,'Main Store',1,'2026-09-08',0.000,1.000,11800.00,'sales_invoice',336,'SI2026000027',464,'2026-10-30 19:00:40'),(355,68,'Main Store',1,'2026-09-08',0.000,3.000,28500.00,'sales_invoice',336,'SI2026000027',465,'2026-10-30 19:00:40'),(356,72,'Main Store',1,'2026-09-08',0.000,3.000,9500.00,'sales_invoice',336,'SI2026000027',466,'2026-10-30 19:00:40'),(357,84,'Main Store',1,'2026-09-09',0.000,2.000,5200.00,'sales_invoice',337,'SI2026000028',467,'2026-10-30 19:00:40'),(358,84,'Main Store',15,'2026-09-10',0.000,2.000,5200.00,'sales_invoice',338,'SI2026000029',468,'2026-10-30 19:00:41'),(359,83,'Main Store',15,'2026-09-10',0.000,1.000,15800.00,'sales_invoice',338,'SI2026000029',469,'2026-10-30 19:00:41'),(360,84,'Main Store',15,'2026-09-13',0.000,3.000,5200.00,'sales_invoice',339,'SI2026000030',470,'2026-10-30 19:00:41'),(361,75,'Main Store',15,'2026-09-13',0.000,1.000,12800.00,'sales_invoice',339,'SI2026000030',471,'2026-10-30 19:00:41'),(362,84,'Main Store',1,'2026-09-14',0.000,3.000,5200.00,'sales_invoice',340,'SI2026000031',472,'2026-10-30 19:00:41'),(363,76,'Main Store',1,'2026-09-14',0.000,2.000,16900.00,'sales_invoice',340,'SI2026000031',473,'2026-10-30 19:00:41'),(364,82,'Main Store',1,'2026-09-15',0.000,2.000,17500.00,'sales_invoice',341,'SI2026000032',474,'2026-10-30 19:00:42'),(365,80,'Main Store',1,'2026-09-15',0.000,1.000,11800.00,'sales_invoice',341,'SI2026000032',475,'2026-10-30 19:00:42'),(366,78,'Main Store',1,'2026-09-15',0.000,2.000,11500.00,'sales_invoice',341,'SI2026000032',476,'2026-10-30 19:00:42'),(367,70,'Main Store',1,'2026-09-16',0.000,1.000,16500.00,'sales_invoice',342,'SI2026000033',477,'2026-10-30 19:00:42'),(368,84,'Main Store',1,'2026-09-19',0.000,2.000,5200.00,'sales_invoice',343,'SI2026000034',478,'2026-10-30 19:00:42'),(369,70,'Main Store',1,'2026-09-20',0.000,4.000,16500.00,'sales_invoice',344,'SI2026000035',479,'2026-10-30 19:00:43'),(370,69,'Main Store',1,'2026-09-20',0.000,3.000,2100.00,'sales_invoice',344,'SI2026000035',480,'2026-10-30 19:00:43'),(371,82,'Main Store',1,'2026-09-22',0.000,2.000,17500.00,'sales_invoice',345,'SI2026000036',481,'2026-10-30 19:00:43'),(372,70,'Main Store',1,'2026-09-22',0.000,3.000,16500.00,'sales_invoice',345,'SI2026000036',482,'2026-10-30 19:00:43'),(373,69,'Main Store',1,'2026-09-23',0.000,1.000,2100.00,'sales_invoice',346,'SI2026000037',483,'2026-10-30 19:00:43'),(374,74,'Main Store',1,'2026-09-24',0.000,4.000,1150.00,'sales_invoice',347,'SI2026000038',484,'2026-10-30 19:00:44'),(375,72,'Main Store',1,'2026-09-24',0.000,3.000,9500.00,'sales_invoice',347,'SI2026000038',485,'2026-10-30 19:00:44'),(376,77,'Main Store',1,'2026-09-24',0.000,3.000,6800.00,'sales_invoice',347,'SI2026000038',486,'2026-10-30 19:00:44'),(377,81,'Main Store',1,'2026-09-26',0.000,2.000,3900.00,'sales_invoice',348,'SI2026000039',487,'2026-10-30 19:00:44'),(378,85,'Main Store',1,'2026-09-27',0.000,2.000,4600.00,'sales_invoice',349,'SI2026000040',488,'2026-10-30 19:00:45'),(379,79,'Main Store',1,'2026-09-27',0.000,2.000,6400.00,'sales_invoice',349,'SI2026000040',489,'2026-10-30 19:00:45'),(380,75,'Main Store',1,'2026-09-28',0.000,1.000,12800.00,'sales_invoice',350,'SI2026000041',490,'2026-10-30 19:00:45'),(381,77,'Main Store',1,'2026-09-28',0.000,3.000,6800.00,'sales_invoice',350,'SI2026000041',491,'2026-10-30 19:00:45'),(382,71,'Main Store',16,'2026-09-30',0.000,3.000,7800.00,'sales_invoice',351,'SI2026000042',492,'2026-10-30 19:00:45'),(383,84,'Main Store',16,'2026-09-30',0.000,2.000,5200.00,'sales_invoice',351,'SI2026000042',493,'2026-10-30 19:00:45'),(384,75,'Main Store',1,'2026-10-03',0.000,3.000,12800.00,'sales_invoice',352,'SI2026000043',494,'2026-10-30 19:00:46'),(385,84,'Main Store',15,'2026-10-04',0.000,3.000,5200.00,'sales_invoice',353,'SI2026000044',495,'2026-10-30 19:00:46'),(386,84,'Main Store',16,'2026-10-06',0.000,4.000,5200.00,'sales_invoice',354,'SI2026000045',496,'2026-10-30 19:00:46'),(387,83,'Main Store',16,'2026-10-06',0.000,3.000,15800.00,'sales_invoice',354,'SI2026000045',497,'2026-10-30 19:00:46'),(388,73,'Main Store',16,'2026-10-06',0.000,3.000,850.00,'sales_invoice',354,'SI2026000045',498,'2026-10-30 19:00:46'),(389,75,'Main Store',1,'2026-10-08',0.000,3.000,12800.00,'sales_invoice',355,'SI2026000046',499,'2026-10-30 19:00:47'),(390,82,'Main Store',1,'2026-10-10',0.000,3.000,17500.00,'sales_invoice',356,'SI2026000047',500,'2026-10-30 19:00:47'),(391,85,'Main Store',1,'2026-10-10',0.000,1.000,4600.00,'sales_invoice',356,'SI2026000047',501,'2026-10-30 19:00:47'),(392,75,'Main Store',1,'2026-10-12',0.000,2.000,12800.00,'sales_invoice',357,'SI2026000048',502,'2026-10-30 19:00:47'),(393,77,'Main Store',1,'2026-10-12',0.000,4.000,6800.00,'sales_invoice',357,'SI2026000048',503,'2026-10-30 19:00:47'),(394,67,'Main Store',1,'2026-10-12',0.000,3.000,3200.00,'sales_invoice',357,'SI2026000048',504,'2026-10-30 19:00:47'),(395,67,'Main Store',1,'2026-10-13',0.000,3.000,3200.00,'sales_invoice',358,'SI2026000049',505,'2026-10-30 19:00:48'),(396,70,'Main Store',1,'2026-10-13',0.000,2.000,16500.00,'sales_invoice',358,'SI2026000049',506,'2026-10-30 19:00:48'),(397,71,'Main Store',1,'2026-10-13',0.000,4.000,7800.00,'sales_invoice',358,'SI2026000049',507,'2026-10-30 19:00:48'),(398,76,'Main Store',1,'2026-10-14',0.000,2.000,16900.00,'sales_invoice',359,'SI2026000050',508,'2026-10-30 19:00:48'),(399,69,'Main Store',1,'2026-10-14',0.000,1.000,2100.00,'sales_invoice',359,'SI2026000050',509,'2026-10-30 19:00:48'),(400,80,'Main Store',1,'2026-10-14',0.000,1.000,11800.00,'sales_invoice',359,'SI2026000050',510,'2026-10-30 19:00:48'),(401,84,'Main Store',1,'2026-10-17',0.000,2.000,5200.00,'sales_invoice',360,'SI2026000051',511,'2026-10-30 19:00:49'),(402,78,'Main Store',1,'2026-10-19',0.000,4.000,11500.00,'sales_invoice',361,'SI2026000052',512,'2026-10-30 19:00:49'),(403,77,'Main Store',1,'2026-10-19',0.000,3.000,6800.00,'sales_invoice',361,'SI2026000052',513,'2026-10-30 19:00:49'),(404,82,'Main Store',1,'2026-10-21',0.000,2.000,17500.00,'sales_invoice',362,'SI2026000053',514,'2026-10-30 19:00:49'),(405,81,'Main Store',1,'2026-10-22',0.000,3.000,3900.00,'sales_invoice',363,'SI2026000054',515,'2026-10-30 19:00:50'),(406,73,'Main Store',1,'2026-10-22',0.000,3.000,850.00,'sales_invoice',363,'SI2026000054',516,'2026-10-30 19:00:50'),(407,80,'Main Store',1,'2026-10-24',0.000,1.000,11800.00,'sales_invoice',364,'SI2026000055',517,'2026-10-30 19:00:50'),(408,83,'Main Store',1,'2026-10-25',0.000,3.000,15800.00,'sales_invoice',365,'SI2026000056',518,'2026-10-30 19:00:50'),(409,72,'Main Store',1,'2026-10-26',0.000,2.000,9500.00,'sales_invoice',366,'SI2026000057',519,'2026-10-30 19:00:51'),(410,69,'Main Store',1,'2026-10-26',0.000,3.000,2100.00,'sales_invoice',366,'SI2026000057',520,'2026-10-30 19:00:51'),(411,67,'Main Store',1,'2026-10-27',0.000,4.000,3200.00,'sales_invoice',367,'SI2026000058',521,'2026-10-30 19:00:51'),(412,71,'Main Store',1,'2026-10-29',0.000,3.000,7800.00,'sales_invoice',368,'SI2026000059',522,'2026-10-30 19:00:51'),(413,83,'Main Store',1,'2026-10-29',0.000,4.000,15800.00,'sales_invoice',368,'SI2026000059',523,'2026-10-30 19:00:51'),(414,73,'Main Store',1,'2026-10-29',0.000,2.000,850.00,'sales_invoice',368,'SI2026000059',524,'2026-10-30 19:00:51'),(415,68,'Main Store',1,'2026-10-31',0.000,2.000,28500.00,'sales_invoice',369,'SI2026000060',525,'2026-10-30 19:00:57'),(416,72,'Main Store',1,'2026-10-31',0.000,1.000,9500.00,'sales_invoice',370,'SI2026000061',526,'2026-10-30 19:00:57'),(417,84,'Main Store',1,'2026-10-31',0.000,2.000,5200.00,'sales_invoice',371,'SI2026000062',527,'2026-10-30 19:00:57'),(418,81,'Main Store',1,'2026-10-31',0.000,1.000,3900.00,'sales_invoice',372,'SI2026000063',528,'2026-10-30 19:00:57'),(419,68,'Main Store',1,'2026-10-31',0.000,1.000,28500.00,'sales_invoice',373,'SI2026000064',529,'2026-10-30 19:00:58'),(420,71,'Main Store',1,'2026-10-31',0.000,2.000,7800.00,'sales_invoice',374,'SI2026000065',530,'2026-10-30 19:00:58'),(421,82,'Main Store',1,'2026-10-31',0.000,1.000,17500.00,'sales_invoice',375,'SI2026000066',531,'2026-10-30 19:00:58'),(422,74,'Main Store',1,'2026-10-31',0.000,2.000,1150.00,'sales_invoice',376,'SI2026000067',532,'2026-10-30 19:00:59'),(423,69,'Main Store',1,'2026-10-31',0.000,2.000,2100.00,'sales_invoice',377,'SI2026000068',533,'2026-10-30 19:00:59'),(424,80,'Main Store',1,'2026-10-31',0.000,1.000,11800.00,'sales_invoice',378,'SI2026000069',534,'2026-10-30 19:00:59'),(425,83,'Main Store',1,'2026-10-22',0.000,2.000,15800.00,'sales_invoice',379,'SI2026000070',535,'2026-10-30 19:01:02'),(426,68,'Main Store',1,'2026-10-22',0.000,1.000,28500.00,'sales_invoice',379,'SI2026000070',536,'2026-10-30 19:01:02'),(427,70,'Main Store',1,'2026-10-22',0.000,3.000,16500.00,'sales_invoice',380,'SI2026000071',537,'2026-10-30 19:01:03'),(428,78,'Main Store',1,'2026-10-22',0.000,3.000,11500.00,'sales_invoice',380,'SI2026000071',538,'2026-10-30 19:01:03'),(429,77,'Main Store',1,'2026-10-22',0.000,1.000,6800.00,'sales_invoice',381,'SI2026000072',539,'2026-10-30 19:01:03'),(430,67,'Main Store',1,'2026-10-22',0.000,3.000,3200.00,'sales_invoice',381,'SI2026000072',540,'2026-10-30 19:01:03'),(431,71,'Main Store',1,'2026-10-25',1.000,0.000,7800.00,'sales_return',382,'SR2026000001',541,'2026-10-30 19:01:03'),(432,84,'Main Store',1,'2026-10-25',1.000,0.000,5200.00,'sales_return',383,'SR2026000002',542,'2026-10-30 19:01:03'),(433,80,'Main Store',1,'2026-10-25',1.000,0.000,11800.00,'sales_return',384,'SR2026000003',543,'2026-10-30 19:01:04'),(434,82,'Main Store',1,'2026-10-25',1.000,0.000,17500.00,'sales_return',385,'SR2026000004',544,'2026-10-30 19:01:04'),(435,75,'Main Store',1,'2026-10-25',1.000,0.000,12800.00,'sales_return',386,'SR2026000005',545,'2026-10-30 19:01:04'),(436,69,'Main Store',1,'2026-10-12',0.000,1.000,0.00,'challan',387,'DESCHL2026000001',546,'2026-10-30 19:01:05'),(437,78,'Main Store',1,'2026-10-12',0.000,1.000,0.00,'challan',387,'DESCHL2026000001',547,'2026-10-30 19:01:05'),(438,73,'Main Store',1,'2026-10-13',0.000,1.000,0.00,'challan',388,'DESCHL2026000002',548,'2026-10-30 19:01:05'),(439,80,'Main Store',1,'2026-10-13',0.000,2.000,0.00,'challan',388,'DESCHL2026000002',549,'2026-10-30 19:01:05'),(440,84,'Main Store',1,'2026-10-14',0.000,2.000,0.00,'challan',389,'DESCHL2026000003',550,'2026-10-30 19:01:05'),(441,71,'Main Store',1,'2026-10-14',0.000,2.000,0.00,'challan',389,'DESCHL2026000003',551,'2026-10-30 19:01:05'),(442,79,'Main Store',1,'2026-10-15',0.000,2.000,0.00,'challan',390,'DESCHL2026000004',552,'2026-10-30 19:01:06'),(443,67,'Main Store',1,'2026-10-15',0.000,2.000,0.00,'challan',390,'DESCHL2026000004',553,'2026-10-30 19:01:06'),(444,78,'Main Store',1,'2026-10-16',0.000,1.000,0.00,'challan',391,'DESCHL2026000005',554,'2026-10-30 19:01:06'),(445,82,'Main Store',1,'2026-10-16',0.000,1.000,0.00,'challan',391,'DESCHL2026000005',555,'2026-10-30 19:01:06'),(446,85,'Main Store',1,'2026-09-05',0.000,5.000,0.00,'stock_transfer',396,'DESSTT2026000001',568,'2026-10-30 19:01:07'),(447,85,'site office',1,'2026-09-05',5.000,0.000,0.00,'stock_transfer',396,'DESSTT2026000001',568,'2026-10-30 19:01:07'),(448,70,'Main Store',1,'2026-09-05',0.000,5.000,0.00,'stock_transfer',396,'DESSTT2026000001',569,'2026-10-30 19:01:07'),(449,70,'site office',1,'2026-09-05',5.000,0.000,0.00,'stock_transfer',396,'DESSTT2026000001',569,'2026-10-30 19:01:07'),(450,82,'Main Store',1,'2026-09-11',0.000,2.000,0.00,'stock_transfer',397,'DESSTT2026000002',570,'2026-10-30 19:01:08'),(451,82,'site office',1,'2026-09-11',2.000,0.000,0.00,'stock_transfer',397,'DESSTT2026000002',570,'2026-10-30 19:01:08'),(452,84,'Main Store',1,'2026-09-11',0.000,3.000,0.00,'stock_transfer',397,'DESSTT2026000002',571,'2026-10-30 19:01:08'),(453,84,'site office',1,'2026-09-11',3.000,0.000,0.00,'stock_transfer',397,'DESSTT2026000002',571,'2026-10-30 19:01:08'),(454,77,'Main Store',1,'2026-09-17',0.000,2.000,0.00,'stock_transfer',398,'DESSTT2026000003',572,'2026-10-30 19:01:08'),(455,77,'RMA STORE',1,'2026-09-17',2.000,0.000,0.00,'stock_transfer',398,'DESSTT2026000003',572,'2026-10-30 19:01:08'),(456,71,'Main Store',1,'2026-09-17',0.000,2.000,0.00,'stock_transfer',398,'DESSTT2026000003',573,'2026-10-30 19:01:08'),(457,71,'RMA STORE',1,'2026-09-17',2.000,0.000,0.00,'stock_transfer',398,'DESSTT2026000003',573,'2026-10-30 19:01:08'),(458,79,'Main Store',1,'2026-09-23',0.000,3.000,0.00,'stock_transfer',399,'DESSTT2026000004',574,'2026-10-30 19:01:08'),(459,79,'site office',1,'2026-09-23',3.000,0.000,0.00,'stock_transfer',399,'DESSTT2026000004',574,'2026-10-30 19:01:08'),(460,69,'Main Store',1,'2026-09-23',0.000,4.000,0.00,'stock_transfer',399,'DESSTT2026000004',575,'2026-10-30 19:01:08'),(461,69,'site office',1,'2026-09-23',4.000,0.000,0.00,'stock_transfer',399,'DESSTT2026000004',575,'2026-10-30 19:01:08'),(462,67,'Main Store',1,'2026-09-15',0.000,2.000,2500.00,'purchase_return',400,'DESPRT2026000001',576,'2026-10-30 19:01:10'),(463,71,'Main Store',1,'2026-09-15',0.000,2.000,6200.00,'purchase_return',401,'DESPRT2026000002',577,'2026-10-30 19:01:11'),(464,75,'Main Store',1,'2026-10-02',2.000,0.000,10400.00,'stock_in',402,'DESSAI2026000001',578,'2026-10-30 19:02:08'),(465,73,'Main Store',1,'2026-10-03',1.000,0.000,600.00,'stock_in',403,'DESSAI2026000002',579,'2026-10-30 19:02:09'),(466,73,'Main Store',1,'2026-10-02',0.000,1.000,600.00,'stock_out',404,'DESSAO2026000001',580,'2026-10-30 19:02:09'),(467,76,'Main Store',1,'2026-10-03',0.000,2.000,13900.00,'stock_out',405,'DESSAO2026000002',581,'2026-10-30 19:02:09'),(468,72,'Main Store',1,'2026-10-31',0.000,1.000,9500.00,'sales_invoice',406,'SI2026000073',582,'2026-10-31 05:08:34'),(469,77,'Main Store',1,'2026-10-31',0.000,1.000,6800.00,'sales_invoice',407,'SI2026000074',583,'2026-10-31 06:18:27'),(470,75,'Main Store',1,'2026-11-02',0.000,3.000,12800.00,'sales_invoice',408,'SI2026000075',584,'2026-11-02 15:06:15'),(471,86,'Main Store',1,'2026-11-02',10.000,0.000,21000.00,'purchase',410,'DESPUR2026000011',587,'2026-11-02 16:00:45'),(472,87,'Main Store',1,'2026-11-02',20.000,0.000,2600.00,'purchase',410,'DESPUR2026000011',588,'2026-11-02 16:00:45'),(473,87,'Main Store',1,'2026-11-02',5.000,0.000,2600.00,'stock_in',411,'DESSAI2026000003',589,'2026-11-02 16:00:48'),(474,87,'Main Store',1,'2026-11-02',0.000,2.000,0.00,'stock_transfer',412,'DESSTT2026000005',590,'2026-11-02 16:00:50'),(475,87,'RMA STORE',1,'2026-11-02',2.000,0.000,0.00,'stock_transfer',412,'DESSTT2026000005',590,'2026-11-02 16:00:50'),(476,86,'Main Store',1,'2026-11-02',0.000,1.000,25000.00,'sales_invoice',413,'SI2026000076',591,'2026-11-02 16:00:53'),(477,87,'Main Store',1,'2026-11-02',0.000,2.000,3500.00,'sales_invoice',413,'SI2026000076',592,'2026-11-02 16:00:53'),(478,87,'Main Store',1,'2026-11-02',0.000,1.000,3500.00,'sales_invoice',414,'SI2026000077',593,'2026-11-02 16:00:56'),(479,86,'Main Store',1,'2026-11-02',0.000,1.000,25000.00,'sales_invoice',414,'SI2026000077',594,'2026-11-02 16:00:56'),(480,87,'Main Store',1,'2026-11-02',0.000,2.000,0.00,'challan',415,'DESCHL2026000006',595,'2026-11-02 16:01:01'),(481,86,'Main Store',1,'2026-11-02',0.000,1.000,25000.00,'sales_invoice',416,'SI2026000078',596,'2026-11-02 16:01:43'),(482,87,'Main Store',1,'2026-11-02',0.000,2.000,3500.00,'sales_invoice',416,'SI2026000078',597,'2026-11-02 16:01:43'),(483,86,'Main Store',1,'2026-11-02',0.000,3.000,24500.00,'sales_invoice',417,'SI2026000079',598,'2026-11-02 16:05:48'),(484,87,'Main Store',1,'2026-11-02',0.000,3.000,3400.00,'sales_invoice',417,'SI2026000079',599,'2026-11-02 16:05:48'),(485,87,'Main Store',1,'2026-11-02',1.000,0.000,3500.00,'sales_return',418,'SR2026000006',600,'2026-11-02 16:05:51'),(486,80,'Main Store',1,'2026-11-05',0.000,1.000,11800.00,'sales_invoice',419,'SI2026000080',601,'2026-11-05 15:52:45'),(487,76,'Main Store',1,'2026-11-05',0.000,1.000,16900.00,'sales_invoice',420,'SI2026000081',602,'2026-11-05 15:53:43'),(488,68,'Main Store',1,'2026-11-05',0.000,1.000,28500.00,'sales_invoice',421,'SI2026000082',603,'2026-11-05 15:54:53');
/*!40000 ALTER TABLE `inv_stock_moves` ENABLE KEYS */;
UNLOCK TABLES;

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

LOCK TABLES `inv_transaction_locks` WRITE;
/*!40000 ALTER TABLE `inv_transaction_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `inv_transaction_locks` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=241 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_log`
--

LOCK TABLES `login_log` WRITE;
/*!40000 ALTER TABLE `login_log` DISABLE KEYS */;
INSERT INTO `login_log` VALUES (1,'administrator',NULL,'2025-10-11','2025-10-11 12:14:53','192.168.2.239',0),(2,'administrator',NULL,'2025-10-11','2025-10-11 12:16:21','192.168.2.239',0),(3,'administrator','2025-10-11 20:25:03','2025-10-11','2025-10-11 12:33:46','192.168.2.239',0),(4,'administrator',NULL,'2025-10-11','2025-10-11 15:01:17','192.168.2.239',0),(5,'administrator',NULL,'2025-10-11','2025-10-11 15:50:06','192.168.2.239',0),(6,'administrator',NULL,'2025-10-11','2025-10-11 16:10:05','192.168.2.239',0),(7,'administrator',NULL,'2025-10-11','2025-10-11 16:35:54','192.168.2.239',0),(8,'administrator',NULL,'2025-10-11','2025-10-11 17:38:41','192.168.2.239',0),(9,'administrator',NULL,'2025-10-12','2025-10-12 05:16:26','192.168.2.239',0),(10,'administrator',NULL,'2025-10-12','2025-10-12 10:18:27','192.168.2.239',0),(11,'administrator',NULL,'2025-10-12','2025-10-12 10:57:12','192.168.2.239',0),(12,'administrator',NULL,'2025-10-12','2025-10-12 11:39:09','192.168.2.239',0),(13,'administrator',NULL,'2025-10-13','2025-10-13 16:10:36','192.168.2.239',0),(14,'administrator',NULL,'2025-10-13','2025-10-13 17:06:37','192.168.2.239',0),(15,'administrator',NULL,'2025-10-13','2025-10-13 18:08:01','192.168.2.239',0),(16,'administrator',NULL,'2025-10-17','2025-10-17 13:56:35','192.168.2.239',0),(17,'administrator',NULL,'2025-10-17','2025-10-17 14:49:30','192.168.2.239',0),(18,'administrator',NULL,'2025-10-17','2025-10-17 15:23:22','192.168.2.239',0),(19,'administrator',NULL,'2025-10-18','2025-10-18 05:28:17','192.168.2.239',0),(20,'administrator',NULL,'2025-10-18','2025-10-18 08:18:03','192.168.2.239',0),(21,'administrator',NULL,'2025-10-18','2025-10-18 09:36:12','192.168.2.239',0),(22,'administrator','2025-10-18 18:00:21','2025-10-18','2025-10-18 10:38:22','192.168.2.239',0),(23,'administrator','2025-10-18 22:19:12','2025-10-18','2025-10-18 16:19:09','192.168.2.239',0),(24,'administrator',NULL,'2025-10-18','2025-10-18 16:19:15','192.168.2.239',0),(25,'administrator',NULL,'2025-10-18','2025-10-18 17:40:30','192.168.2.239',0),(26,'Administrator',NULL,'2025-10-18','2025-10-18 18:28:23','127.0.0.1',0),(27,'Administrator',NULL,'2025-10-19','2025-10-19 06:08:55','127.0.0.1',0),(28,'Administrator','2025-10-19 12:58:49','2025-10-19','2025-10-19 06:58:32','127.0.0.1',0),(29,'administrator',NULL,'2025-10-23','2025-10-23 15:06:14','192.168.2.239',0),(30,'administrator','2025-10-23 22:01:45','2025-10-23','2025-10-23 16:01:12','192.168.2.239',0),(31,'administrator','2025-10-23 22:06:56','2025-10-23','2025-10-23 16:04:21','192.168.2.239',0),(32,'administrator','2025-10-23 22:07:18','2025-10-23','2025-10-23 16:07:10','192.168.2.239',0),(33,'administrator',NULL,'2025-10-23','2025-10-23 16:37:29','192.168.2.239',0),(34,'administrator',NULL,'2025-10-23','2025-10-23 16:58:05','192.168.2.239',0),(35,'administrator','2025-10-26 21:46:01','2025-10-26','2025-10-26 15:44:24','192.168.2.239',0),(36,'administrator',NULL,'2025-11-01','2025-11-01 08:35:59','192.168.2.239',0),(37,'administrator',NULL,'2025-11-01','2025-11-01 18:47:52','192.168.2.239',0),(38,'administrator',NULL,'2025-11-02','2025-11-02 04:29:52','192.168.2.239',0),(39,'Administrator','2025-11-02 16:21:57','2025-11-02','2025-11-02 10:20:43','127.0.0.1',0),(40,'administrator',NULL,'2025-11-02','2025-11-02 10:22:08','192.168.2.239',0),(41,'administrator',NULL,'2025-11-02','2025-11-02 10:47:33','192.168.2.239',0),(42,'Administrator',NULL,'2025-11-10','2025-11-10 14:21:55','127.0.0.1',0),(43,'Administrator','2025-11-10 23:13:17','2025-11-10','2025-11-10 16:59:52','127.0.0.1',0),(44,'Administrator',NULL,'2025-11-10','2025-11-10 17:13:46','127.0.0.1',0),(45,'administrator',NULL,'2025-11-12','2025-11-12 09:10:27','192.168.2.239',0),(46,'administrator',NULL,'2025-11-12','2025-11-12 09:27:12','192.168.2.239',0),(47,'administrator','2025-11-12 16:58:24','2025-11-12','2025-11-12 10:58:22','192.168.2.239',0),(48,'administrator',NULL,'2025-11-12','2025-11-12 11:35:53','192.168.2.239',0),(49,'administrator','2025-11-12 18:33:13','2025-11-12','2025-11-12 12:33:10','192.168.2.239',0),(50,'administrator',NULL,'2025-11-12','2025-11-12 16:02:24','192.168.2.239',0),(51,'administrator',NULL,'2025-11-13','2025-11-13 04:28:54','192.168.2.239',0),(52,'administrator',NULL,'2025-11-13','2025-11-13 10:57:05','192.168.2.239',0),(53,'administrator',NULL,'2025-11-13','2025-11-13 12:13:09','192.168.2.239',0),(54,'administrator',NULL,'2025-11-13','2025-11-13 16:16:25','192.168.2.239',0),(55,'administrator',NULL,'2025-11-13','2025-11-13 16:42:10','192.168.2.239',0),(56,'administrator',NULL,'2025-11-14','2025-11-14 04:34:49','192.168.2.239',0),(57,'administrator','2025-11-14 10:40:05','2025-11-14','2025-11-14 04:35:37','192.168.2.239',0),(58,'administrator',NULL,'2025-11-14','2025-11-14 06:36:44','192.168.2.239',0),(59,'administrator',NULL,'2025-11-14','2025-11-14 06:53:09','192.168.2.239',0),(60,'administrator',NULL,'2025-11-14','2025-11-14 10:36:06','192.168.2.239',0),(61,'administrator',NULL,'2025-11-14','2025-11-14 11:00:40','192.168.2.239',0),(62,'administrator',NULL,'2025-11-14','2025-11-14 11:17:37','192.168.2.239',0),(63,'administrator',NULL,'2025-11-14','2025-11-14 11:21:28','192.168.2.239',0),(64,'administrator',NULL,'2025-11-14','2025-11-14 15:32:55','192.168.2.239',0),(65,'administrator',NULL,'2025-11-14','2025-11-14 17:14:10','192.168.2.239',0),(66,'administrator',NULL,'2025-11-15','2025-11-15 05:30:53','192.168.2.239',0),(67,'administrator',NULL,'2025-11-15','2025-11-15 06:12:09','192.168.2.239',0),(68,'administrator',NULL,'2025-11-15','2025-11-15 07:46:38','192.168.2.239',0),(69,'administrator',NULL,'2025-11-15','2025-11-15 13:37:17','192.168.2.239',0),(70,'administrator',NULL,'2025-11-16','2025-11-16 10:20:19','192.168.2.239',0),(71,'Administrator',NULL,'2026-10-27','2026-10-27 13:48:08','192.168.2.213',0),(72,'Administrator','2026-10-27 20:20:19','2026-10-27','2026-10-27 14:19:54','192.168.2.213',0),(73,'Administrator','2026-10-27 20:51:56','2026-10-27','2026-10-27 14:20:28','192.168.2.213',0),(74,'Administrator','2026-10-27 21:21:31','2026-10-27','2026-10-27 14:52:01','192.168.2.213',0),(75,'Administrator',NULL,'2026-10-28','2026-10-28 15:11:09','192.168.2.213',0),(76,'Administrator',NULL,'2026-10-28','2026-10-28 15:56:39','192.168.2.213',0),(77,'Administrator',NULL,'2026-10-28','2026-10-28 16:34:23','192.168.2.213',0),(78,'Administrator',NULL,'2026-10-29','2026-10-29 14:08:32','192.168.2.213',0),(79,'Administrator',NULL,'2026-10-29','2026-10-29 15:21:35','192.168.2.213',0),(80,'Administrator',NULL,'2026-10-29','2026-10-29 15:48:47','192.168.2.213',0),(81,'Administrator',NULL,'2026-10-29','2026-10-29 16:19:29','192.168.2.213',0),(82,'Administrator',NULL,'2026-10-29','2026-10-29 16:55:04','192.168.2.213',0),(83,'Administrator','2026-10-29 23:52:46','2026-10-29','2026-10-29 17:31:51','192.168.2.213',0),(84,'Administrator','2026-10-30 00:06:29','2026-10-29','2026-10-29 17:52:52','192.168.2.213',0),(85,'Administrator',NULL,'2026-10-29','2026-10-29 18:06:43','192.168.2.213',0),(86,'Administrator ',NULL,'2026-10-29','2026-10-29 18:23:42','192.168.2.233',0),(87,'Administrator ','2026-10-30 00:30:48','2026-10-29','2026-10-29 18:29:22','192.168.2.213',0),(88,'Administrator ',NULL,'2026-10-29','2026-10-29 18:37:38','192.168.2.213',0),(89,'Administrator ',NULL,'2026-10-30','2026-10-30 03:54:12','192.168.2.213',0),(90,'Administrator ','2026-10-30 12:53:58','2026-10-30','2026-10-30 06:04:55','192.168.2.213',0),(91,'Administrator ',NULL,'2026-10-30','2026-10-30 07:16:11','192.168.2.213',0),(92,'Administrator ','2026-10-30 15:53:02','2026-10-30','2026-10-30 09:32:20','192.168.2.213',0),(93,'Administrator ',NULL,'2026-10-30','2026-10-30 09:53:05','192.168.2.213',0),(94,'Administrator ','2026-10-30 17:10:42','2026-10-30','2026-10-30 10:20:28','192.168.2.213',0),(98,'Administrator ',NULL,'2026-10-30','2026-10-30 15:54:56','192.168.2.213',0),(109,'Administrator ','2026-10-30 23:01:30','2026-10-30','2026-10-30 16:54:28','192.168.2.213',0),(110,'nazmulb','2026-10-30 23:05:06','2026-10-30','2026-10-30 17:01:50','192.168.2.213',0),(114,'Administrator ','2026-10-30 23:06:44','2026-10-30','2026-10-30 17:05:08','192.168.2.213',0),(115,'Administrator ','2026-10-30 23:16:36','2026-10-30','2026-10-30 17:06:54','192.168.2.213',0),(119,'nazmulb','2026-10-30 23:17:06','2026-10-30','2026-10-30 17:16:48','192.168.2.213',0),(120,'Administrator ','2026-10-30 23:18:52','2026-10-30','2026-10-30 17:17:14','192.168.2.213',0),(121,'nazmulb','2026-10-30 23:19:30','2026-10-30','2026-10-30 17:18:59','192.168.2.213',0),(122,'Administrator ',NULL,'2026-10-30','2026-10-30 17:19:33','192.168.2.213',0),(130,'Administrator ','2026-10-30 23:50:18','2026-10-30','2026-10-30 17:44:50','192.168.2.213',0),(134,'Administrator ','2026-10-30 23:58:54','2026-10-30','2026-10-30 17:58:14','192.168.2.213',0),(135,'Administrator ','2026-10-30 23:59:47','2026-10-30','2026-10-30 17:59:21','192.168.2.213',0),(136,'Administrator ','2026-10-31 00:10:15','2026-10-30','2026-10-30 17:59:53','192.168.2.213',0),(138,'Administrator ','2026-10-31 00:10:35','2026-10-30','2026-10-30 18:10:25','192.168.2.213',0),(139,'Administrator ','2026-10-31 00:13:07','2026-10-30','2026-10-30 18:11:12','192.168.2.213',0),(140,'Administrator ','2026-10-31 00:24:57','2026-10-30','2026-10-30 18:13:17','192.168.2.213',0),(144,'Administrator ','2026-10-31 00:34:34','2026-10-30','2026-10-30 18:25:16','192.168.2.213',0),(157,'Administrator ',NULL,'2026-10-30','2026-10-30 18:34:39','192.168.2.213',0),(158,'Administrator ','2026-10-31 00:59:58','2026-10-31','2026-10-30 18:53:24','192.168.2.213',0),(165,'demo_customer',NULL,'2026-10-31','2026-10-30 19:03:06','127.0.0.1',0),(166,'demo_sr',NULL,'2026-10-31','2026-10-30 19:03:06','127.0.0.1',0),(167,'demo_sr',NULL,'2026-10-31','2026-10-30 19:03:36','127.0.0.1',0),(169,'demo_sales',NULL,'2026-10-31','2026-10-30 19:03:56','127.0.0.1',0),(170,'demo_store',NULL,'2026-10-31','2026-10-30 19:03:56','127.0.0.1',0),(171,'demo_accounts',NULL,'2026-10-31','2026-10-30 19:03:57','127.0.0.1',0),(172,'demo_manager',NULL,'2026-10-31','2026-10-30 19:03:57','127.0.0.1',0),(173,'demo_ctg',NULL,'2026-10-31','2026-10-30 19:03:58','127.0.0.1',0),(174,'demo_customer',NULL,'2026-10-31','2026-10-30 19:03:58','127.0.0.1',0),(175,'demo_customer2',NULL,'2026-10-31','2026-10-30 19:03:59','127.0.0.1',0),(176,'demo_sr',NULL,'2026-10-31','2026-10-30 19:03:59','127.0.0.1',0),(177,'demo_sr2',NULL,'2026-10-31','2026-10-30 19:03:59','127.0.0.1',0),(178,'demo_ctg',NULL,'2026-10-31','2026-10-30 19:04:00','127.0.0.1',0),(181,'demo_ctg',NULL,'2026-10-31','2026-10-30 19:05:02','127.0.0.1',0),(183,'Administrator ','2026-10-31 01:29:18','2026-10-31','2026-10-30 19:07:39','192.168.2.213',0),(185,'demo_sales',NULL,'2026-10-31','2026-10-30 19:19:32','127.0.0.1',0),(186,'demo_sales','2026-10-31 01:31:44','2026-10-31','2026-10-30 19:29:34','192.168.2.213',0),(187,'Administrator ','2026-10-31 11:05:17','2026-10-31','2026-10-31 05:05:13','192.168.2.213',0),(188,'Administrator ','2026-10-31 11:11:05','2026-10-31','2026-10-31 05:06:15','192.168.2.213',0),(189,'Administrator ','2026-10-31 11:24:35','2026-10-31','2026-10-31 05:11:09','192.168.2.213',0),(190,'Administrator ',NULL,'2026-10-31','2026-10-31 05:31:53','192.168.2.213',0),(191,'Administrator ','2026-10-31 11:50:00','2026-10-31','2026-10-31 05:47:21','192.168.2.233',0),(192,'Administrator ','2026-10-31 11:56:30','2026-10-31','2026-10-31 05:53:21','192.168.2.213',0),(193,'Administrator ','2026-10-31 12:08:21','2026-10-31','2026-10-31 06:00:46','192.168.2.213',0),(194,'Administrator ','2026-10-31 12:13:45','2026-10-31','2026-10-31 06:09:09','192.168.2.213',0),(195,'Administrator ','2026-10-31 12:40:14','2026-10-31','2026-10-31 06:16:01','192.168.2.213',0),(196,'Administrator ','2026-10-31 13:01:11','2026-10-31','2026-10-31 06:40:44','192.168.2.213',0),(197,'Administrator ',NULL,'2026-10-31','2026-10-31 07:01:16','192.168.2.213',0),(198,'Administrator ','2026-10-31 13:51:04','2026-10-31','2026-10-31 07:01:23','192.168.2.213',0),(199,'EMP00004','2026-10-31 13:30:53','2026-10-31','2026-10-31 07:04:31','192.168.2.213',0),(200,'EMP00004',NULL,'2026-10-31','2026-10-31 07:31:16','192.168.2.213',0),(201,'Administrator ','2026-10-31 13:57:06','2026-10-31','2026-10-31 07:55:47','192.168.2.213',0),(202,'Administrator ','2026-10-31 14:04:35','2026-10-31','2026-10-31 08:01:57','192.168.2.213',0),(203,'Administrator ',NULL,'2026-10-31','2026-10-31 08:10:01','192.168.2.213',0),(206,'Administrator ','2026-10-31 14:25:54','2026-10-31','2026-10-31 08:22:40','192.168.2.213',0),(207,'Administrator ','2026-10-31 14:34:40','2026-10-31','2026-10-31 08:26:05','192.168.2.213',0),(208,'Administrator ',NULL,'2026-10-31','2026-10-31 08:49:27','192.168.2.213',0),(209,'Administrator ','2026-10-31 15:37:49','2026-10-31','2026-10-31 09:26:44','192.168.2.213',0),(210,'Administrator ',NULL,'2026-11-01','2026-11-01 05:24:56','::1',0),(211,'Administrator','2026-11-01 13:33:42','2026-11-01','2026-11-01 07:30:05','::1',0),(212,'Administrator','2026-11-01 13:42:09','2026-11-01','2026-11-01 07:33:53','::1',0),(213,'Administrator','2026-11-01 13:48:25','2026-11-01','2026-11-01 07:44:49','::1',0),(214,'Administrator',NULL,'2026-11-01','2026-11-01 07:50:22','::1',0),(215,'Administrator',NULL,'2026-11-01','2026-11-01 12:36:53','::1',0),(216,'Administrator','2026-11-01 19:50:47','2026-11-01','2026-11-01 13:41:25','::1',0),(217,'Administrator',NULL,'2026-11-01','2026-11-01 13:50:52','::1',0),(218,'Administrator',NULL,'2026-11-02','2026-11-02 14:59:47','::1',0),(219,'Administrator',NULL,'2026-11-02','2026-11-02 15:33:58','::1',0),(220,'sample_admin',NULL,'2026-11-02','2026-11-02 15:56:39','::1',0),(221,'sample_admin',NULL,'2026-11-02','2026-11-02 15:57:07','::1',0),(222,'sample_admin',NULL,'2026-11-02','2026-11-02 15:57:32','::1',0),(223,'sample_admin',NULL,'2026-11-02','2026-11-02 15:58:59','::1',0),(224,'sample_admin',NULL,'2026-11-02','2026-11-02 15:59:44','::1',0),(225,'sample_admin',NULL,'2026-11-02','2026-11-02 16:00:39','::1',0),(226,'sample_admin',NULL,'2026-11-02','2026-11-02 16:01:30','::1',0),(227,'sample_admin',NULL,'2026-11-02','2026-11-02 16:02:11','::1',0),(228,'sample_admin',NULL,'2026-11-02','2026-11-02 16:02:30','::1',0),(229,'sample_admin',NULL,'2026-11-02','2026-11-02 16:02:57','::1',0),(230,'sample_admin',NULL,'2026-11-02','2026-11-02 16:03:35','::1',0),(231,'sample_admin',NULL,'2026-11-02','2026-11-02 16:04:20','::1',0),(232,'sample_admin',NULL,'2026-11-02','2026-11-02 16:05:45','::1',0),(233,'sample_admin',NULL,'2026-11-02','2026-11-02 16:06:52','::1',0),(234,'sample_admin',NULL,'2026-11-02','2026-11-02 16:07:14','::1',0),(235,'sample_admin',NULL,'2026-11-02','2026-11-02 16:07:37','::1',0),(236,'sample_admin',NULL,'2026-11-02','2026-11-02 16:08:13','::1',0),(237,'sample_admin',NULL,'2026-11-02','2026-11-02 16:09:09','::1',0),(238,'sample_admin',NULL,'2026-11-02','2026-11-02 16:09:47','::1',0),(239,'Administrator',NULL,'2026-11-05','2026-11-05 15:40:09','::1',0),(240,'Administrator',NULL,'2026-11-05','2026-11-05 15:52:22','::1',0);
/*!40000 ALTER TABLE `login_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paypalmail`
--

DROP TABLE IF EXISTS `paypalmail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paypalmail` (
  `paypalmail_id` int NOT NULL AUTO_INCREMENT,
  `payacc` varchar(40) DEFAULT NULL,
  `paycur` varchar(40) DEFAULT NULL,
  `payserver` varchar(40) DEFAULT NULL,
  `mail1` varchar(40) DEFAULT NULL,
  `mail2` varchar(40) DEFAULT NULL,
  `mail3` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`paypalmail_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paypalmail`
--

LOCK TABLES `paypalmail` WRITE;
/*!40000 ALTER TABLE `paypalmail` DISABLE KEYS */;
INSERT INTO `paypalmail` VALUES (1,'Your Paypal Account','USD','This Web Address','sales.isip','sales@isipbilling.com','');
/*!40000 ALTER TABLE `paypalmail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_accounts`
--

DROP TABLE IF EXISTS `portal_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_accounts` (
  `account_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `login` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usually the mobile number',
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','disabled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '1',
  `failed_attempts` smallint unsigned NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_via` enum('staff','signup') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uq_portal_accounts_customer` (`customer_id`),
  UNIQUE KEY `uq_portal_accounts_login` (`login`),
  CONSTRAINT `fk_portal_account_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_accounts`
--

LOCK TABLES `portal_accounts` WRITE;
/*!40000 ALTER TABLE `portal_accounts` DISABLE KEYS */;
INSERT INTO `portal_accounts` VALUES (1,55,'nazmulitbd','$2y$12$bWkpQXdyX3eb5wXCYKvuX.q4XMBpdaK643OqOWgKWhLt0UMvvAwoe','active',0,0,NULL,'2026-10-30 15:42:07','192.168.2.213','staff',10,'2026-10-30 06:51:20','2026-10-30 09:42:07'),(13,83,'demo_customer','$2y$12$rb2lcaa44pwZkCtyV2WyZem.gNd5OQfoq.pzLWCtly/NKY/QH7B.a','active',0,0,NULL,'2026-10-31 01:03:58','127.0.0.1','staff',10,'2026-10-30 19:02:59','2026-10-30 19:04:00'),(14,85,'demo_customer2','$2y$12$nWHU.fOQUzjAO0YamqH1D.gjY50hNyzYOrqVLlYCYENH1ZEmp6Hj2','active',0,0,NULL,'2026-10-31 01:03:59','127.0.0.1','staff',10,'2026-10-30 19:03:00','2026-10-30 19:04:00');
/*!40000 ALTER TABLE `portal_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_login_log`
--

DROP TABLE IF EXISTS `portal_login_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_login_log` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned DEFAULT NULL,
  `login` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `reason` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `ix_portal_login_log_account` (`account_id`,`created_at`),
  KEY `ix_portal_login_log_ip` (`ip_address`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_login_log`
--

LOCK TABLES `portal_login_log` WRITE;
/*!40000 ALTER TABLE `portal_login_log` DISABLE KEYS */;
INSERT INTO `portal_login_log` VALUES (1,NULL,'0178653523','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36',0,'signup_mismatch','2026-10-30 06:41:26'),(2,NULL,'1615911873','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36',0,'signup_mismatch','2026-10-30 06:49:20'),(3,1,'nazmulitbd','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36',1,'ok','2026-10-30 06:51:39'),(4,1,'nazmulitbd','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36',1,'ok','2026-10-30 06:54:30'),(5,1,'01711264140','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36',1,'ok','2026-10-30 07:16:55'),(48,1,'01711264140','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36',1,'ok','2026-10-30 09:42:07'),(49,8,'t_cust','127.0.0.1','curl/8.23.0',1,'ok','2026-10-30 16:51:47');
/*!40000 ALTER TABLE `portal_login_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_notices`
--

DROP TABLE IF EXISTS `portal_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_notices` (
  `notice_id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notice_id`),
  KEY `ix_portal_notices_dates` (`is_active`,`starts_on`,`ends_on`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_notices`
--

LOCK TABLES `portal_notices` WRITE;
/*!40000 ALTER TABLE `portal_notices` DISABLE KEYS */;
INSERT INTO `portal_notices` VALUES (5,'Test notice','Hi all price are fall down','2026-10-30','2026-10-30',1,10,'2026-10-30 18:07:04'),(7,'Test Notice','The mobile price is down','2026-10-30','2026-11-02',1,10,'2026-10-30 18:10:07'),(8,'Eid holiday schedule','Our offices stay closed for 3 days. Service requests will be answered after the holiday.','2026-10-20',NULL,1,10,'2026-10-30 19:02:16'),(9,'New UPS stock arrived','APC 650VA and 1100VA are back in stock at all branches.','2026-10-20','2026-12-31',1,10,'2026-10-30 19:02:16');
/*!40000 ALTER TABLE `portal_notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_request_messages`
--

DROP TABLE IF EXISTS `portal_request_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_request_messages` (
  `message_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint unsigned NOT NULL,
  `sender` enum('customer','staff') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_user_id` bigint unsigned DEFAULT NULL,
  `staff_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `ix_portal_request_messages_request` (`request_id`,`message_id`),
  CONSTRAINT `fk_portal_message_request` FOREIGN KEY (`request_id`) REFERENCES `portal_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_request_messages`
--

LOCK TABLES `portal_request_messages` WRITE;
/*!40000 ALTER TABLE `portal_request_messages` DISABLE KEYS */;
INSERT INTO `portal_request_messages` VALUES (1,1,'customer',NULL,'','Please reset my portal password.','2026-10-30 06:43:41'),(12,10,'customer',NULL,'','Please send the statement for October.','2026-10-30 19:03:06'),(13,11,'customer',NULL,'','When will our last order be delivered?','2026-10-30 19:03:06');
/*!40000 ALTER TABLE `portal_request_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_requests`
--

DROP TABLE IF EXISTS `portal_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_requests` (
  `request_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `account_id` bigint unsigned DEFAULT NULL,
  `request_type` enum('quotation','support','payment','account','password','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `subject` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'For password help sent before signing in',
  `status` enum('open','answered','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `last_reply_by` enum('customer','staff') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uq_portal_requests_no` (`request_no`),
  KEY `ix_portal_requests_customer` (`customer_id`,`created_at`),
  KEY `ix_portal_requests_status` (`status`,`updated_at`),
  CONSTRAINT `fk_portal_request_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_requests`
--

LOCK TABLES `portal_requests` WRITE;
/*!40000 ALTER TABLE `portal_requests` DISABLE KEYS */;
INSERT INTO `portal_requests` VALUES (1,'PR20261030001',NULL,NULL,'password','Password reset request','0178653523','open','customer','2026-10-30 06:43:41','2026-10-30 06:43:41'),(10,'PR20261031001',83,13,'quotation','Need updated statement','','open','customer','2026-10-30 19:03:06','2026-10-30 19:03:06'),(11,'PR20261031002',83,13,'quotation','Delivery date for order','','open','customer','2026-10-30 19:03:06','2026-10-30 19:03:06');
/*!40000 ALTER TABLE `portal_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_settings`
--

DROP TABLE IF EXISTS `portal_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_settings` (
  `setting_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_settings`
--

LOCK TABLES `portal_settings` WRITE;
/*!40000 ALTER TABLE `portal_settings` DISABLE KEYS */;
INSERT INTO `portal_settings` VALUES ('allow_signup','1'),('contact_email',''),('contact_phone',''),('portal_title','Customer Portal'),('welcome_text','View your invoices, statement, orders and warranty, and send us requests.');
/*!40000 ALTER TABLE `portal_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `category_id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_product_categories_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT INTO `product_categories` VALUES (2,'Laptop','Dell',101,1,NULL,'2026-10-30 06:10:34'),(3,'Networking','Networking items',1,1,NULL,'2026-10-30 18:58:31'),(4,'Computer Accessories','Computer Accessories items',2,1,NULL,'2026-10-30 18:58:32'),(5,'Power & UPS','Power & UPS items',3,1,NULL,'2026-10-30 18:58:32'),(6,'Printers','Printers items',4,1,NULL,'2026-10-30 18:58:32'),(7,'SAMPLE Electronics','Sample category for the walkthrough',90,1,NULL,'2026-11-02 15:59:05');
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_classifications`
--

DROP TABLE IF EXISTS `product_classifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_classifications` (
  `class_id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_type` enum('group','brand') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_id`),
  UNIQUE KEY `uq_product_classifications` (`class_type`,`class_name`),
  KEY `fk_product_class_category` (`category_id`),
  CONSTRAINT `fk_product_class_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_classifications`
--

LOCK TABLES `product_classifications` WRITE;
/*!40000 ALTER TABLE `product_classifications` DISABLE KEYS */;
INSERT INTO `product_classifications` VALUES (3,'group','Dell 4567',2,1,NULL,'2026-10-30 06:11:20'),(4,'group','Router',3,1,NULL,'2026-10-30 18:59:10'),(5,'group','Network Switch',3,1,NULL,'2026-10-30 18:59:10'),(6,'group','Access Point',3,1,NULL,'2026-10-30 18:59:10'),(7,'group','LAN Cable',3,1,NULL,'2026-10-30 18:59:10'),(8,'group','Keyboard',4,1,NULL,'2026-10-30 18:59:11'),(9,'group','Mouse',4,1,NULL,'2026-10-30 18:59:11'),(10,'group','Monitor',4,1,NULL,'2026-10-30 18:59:11'),(11,'group','SSD Drive',4,1,NULL,'2026-10-30 18:59:11'),(12,'group','UPS',5,1,NULL,'2026-10-30 18:59:11'),(13,'group','Battery',5,1,NULL,'2026-10-30 18:59:12'),(14,'group','Laser Printer',6,1,NULL,'2026-10-30 18:59:12'),(15,'group','Toner',6,1,NULL,'2026-10-30 18:59:12'),(16,'brand','TP-Link',NULL,1,NULL,'2026-10-30 18:59:12'),(17,'brand','Cisco',NULL,1,NULL,'2026-10-30 18:59:13'),(18,'brand','Logitech',NULL,1,NULL,'2026-10-30 18:59:13'),(19,'brand','Dell',NULL,1,NULL,'2026-10-30 18:59:13'),(20,'brand','HP',NULL,1,NULL,'2026-10-30 18:59:13'),(21,'brand','Samsung',NULL,1,NULL,'2026-10-30 18:59:13'),(22,'brand','APC',NULL,1,NULL,'2026-10-30 18:59:13'),(23,'brand','Canon',NULL,1,NULL,'2026-10-30 18:59:14'),(24,'group','SAMPLE Smartphone',7,1,NULL,'2026-11-02 15:59:08');
/*!40000 ALTER TABLE `product_classifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_periodic_warranties`
--

DROP TABLE IF EXISTS `product_periodic_warranties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_periodic_warranties` (
  `pw_id` int unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('product','group','brand') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `scope_value` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `warranty_id` int unsigned NOT NULL,
  `note` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_periodic_warranties`
--

LOCK TABLES `product_periodic_warranties` WRITE;
/*!40000 ALTER TABLE `product_periodic_warranties` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_periodic_warranties` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_price_fixing_lines`
--

LOCK TABLES `product_price_fixing_lines` WRITE;
/*!40000 ALTER TABLE `product_price_fixing_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_price_fixing_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_price_fixings`
--

DROP TABLE IF EXISTS `product_price_fixings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_price_fixings` (
  `fixing_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fixing_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fixing_date` date NOT NULL,
  `price_kind` enum('sales','purchase') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_field` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `scope_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `method` enum('manual','percent_cost','percent_change','amount_change') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `method_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_count` int unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`fixing_id`),
  UNIQUE KEY `uq_product_price_fixings_no` (`fixing_no`),
  KEY `ix_product_price_fixings_kind` (`price_kind`,`fixing_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_price_fixings`
--

LOCK TABLES `product_price_fixings` WRITE;
/*!40000 ALTER TABLE `product_price_fixings` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_price_fixings` ENABLE KEYS */;
UNLOCK TABLES;

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
  `unit_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `allow_decimal` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`),
  UNIQUE KEY `uq_product_units_name` (`unit_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_units`
--

LOCK TABLES `product_units` WRITE;
/*!40000 ALTER TABLE `product_units` DISABLE KEYS */;
INSERT INTO `product_units` VALUES (1,'Pcs','pc',0,1,'2026-10-30 05:57:51'),(2,'Box','box',0,1,'2026-10-30 05:57:51'),(3,'Set','set',0,1,'2026-10-30 05:57:51'),(4,'Pair','pr',0,1,'2026-10-30 05:57:51'),(5,'Kg','kg',1,1,'2026-10-30 05:57:51'),(6,'Meter','m',1,1,'2026-10-30 05:57:51'),(7,'Feet','ft',1,1,'2026-10-30 05:57:51'),(8,'Litre','l',1,1,'2026-10-30 05:57:51'),(10,'SAMPLE Box','Bx',0,1,'2026-11-02 15:59:02');
/*!40000 ALTER TABLE `product_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_warranties`
--

DROP TABLE IF EXISTS `product_warranties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_warranties` (
  `warranty_id` int unsigned NOT NULL AUTO_INCREMENT,
  `warranty_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` int unsigned NOT NULL DEFAULT '0',
  `period_unit` enum('none','day','month','year') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `terms` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`warranty_id`),
  UNIQUE KEY `uq_product_warranties_name` (`warranty_name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_warranties`
--

LOCK TABLES `product_warranties` WRITE;
/*!40000 ALTER TABLE `product_warranties` DISABLE KEYS */;
INSERT INTO `product_warranties` VALUES (1,'No Warranty',0,'none','',1,'2026-10-30 05:57:51'),(2,'7 Days',7,'day','',1,'2026-10-30 05:57:51'),(3,'1 Month',2,'month','',1,'2026-10-30 05:57:51'),(4,'3 Months',3,'month','',1,'2026-10-30 05:57:51'),(5,'6 Months',6,'month','',1,'2026-10-30 05:57:51'),(6,'1 Year',1,'year','',1,'2026-10-30 05:57:51'),(7,'2 Years',2,'year','',1,'2026-10-30 05:57:51'),(8,'3 Years',3,'year','',1,'2026-10-30 05:57:51'),(9,'5 Years',5,'year','',1,'2026-10-30 05:57:51'),(12,'SAMPLE 1 Year',1,'year','Service warranty, parts and labour',1,'2026-11-02 15:59:10');
/*!40000 ALTER TABLE `product_warranties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_attachments`
--

DROP TABLE IF EXISTS `purchase_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_attachments` (
  `attachment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_id` bigint unsigned NOT NULL,
  `original_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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

LOCK TABLES `purchase_attachments` WRITE;
/*!40000 ALTER TABLE `purchase_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_attachments` ENABLE KEYS */;
UNLOCK TABLES;

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
  `payment_method` enum('cash','bank','mobile','cheque','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `amount` decimal(15,2) NOT NULL,
  `reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_initial` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `ix_purchase_payments_doc` (`doc_id`),
  KEY `ix_purchase_payments_date` (`paid_on`),
  CONSTRAINT `fk_purchase_payment_doc` FOREIGN KEY (`doc_id`) REFERENCES `inv_docs` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_purchase_payment_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_payments`
--

LOCK TABLES `purchase_payments` WRITE;
/*!40000 ALTER TABLE `purchase_payments` DISABLE KEYS */;
INSERT INTO `purchase_payments` VALUES (15,302,'2026-08-01','cash',9484800.00,'Cash Amount on purchase','',1,10,'2026-10-30 19:00:28'),(16,304,'2026-08-05','cash',1575900.00,'Cash Amount on purchase','',1,10,'2026-10-30 19:00:29'),(17,306,'2026-08-12','cash',1433200.00,'Cash Amount on purchase','',1,10,'2026-10-30 19:00:29'),(18,308,'2026-09-10','cash',1418000.00,'Cash Amount on purchase','',1,10,'2026-10-30 19:00:30'),(19,310,'2026-10-01','cash',1205260.00,'Cash Amount on purchase','',1,10,'2026-10-30 19:00:31');
/*!40000 ALTER TABLE `purchase_payments` ENABLE KEYS */;
UNLOCK TABLES;

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
  `note` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `ix_purchase_schedules_doc` (`doc_id`),
  KEY `ix_purchase_schedules_due` (`due_date`),
  CONSTRAINT `fk_purchase_schedule_doc` FOREIGN KEY (`doc_id`) REFERENCES `inv_docs` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_purchase_schedule_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_schedules`
--

LOCK TABLES `purchase_schedules` WRITE;
/*!40000 ALTER TABLE `purchase_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_schedules` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Table structure for table `reseller_checkoption`
--

DROP TABLE IF EXISTS `reseller_checkoption`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reseller_checkoption` (
  `reseller_checkoption_id` int NOT NULL AUTO_INCREMENT,
  `id` int DEFAULT NULL,
  `level` int DEFAULT '0',
  `sesid` varchar(255) DEFAULT NULL,
  `lastaccess` int DEFAULT NULL,
  `user_role_id` int DEFAULT '2',
  `user_theme` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`reseller_checkoption_id`),
  KEY `ix_reseller_checkoption_session` (`id`,`level`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reseller_checkoption`
--

LOCK TABLES `reseller_checkoption` WRITE;
/*!40000 ALTER TABLE `reseller_checkoption` DISABLE KEYS */;
INSERT INTO `reseller_checkoption` VALUES (1,1,3,'',0,2,NULL),(2,1,2,'',0,2,NULL),(3,1,1,'',0,2,NULL),(4,1596,1,'',0,2,NULL),(5,229,3,'',0,2,NULL),(6,1512,1,'c380a9efa52062b3fafad836ba9a7b81',1375571275,2,NULL),(7,624,2,'',0,2,NULL),(8,2945,1,'',0,2,NULL),(9,2946,1,'',0,2,NULL),(10,2723,1,'c878b6e06be478b8c360a097dec2897d',1761236632,2,NULL),(11,1786,1,'4a3a3d8d22911a046a14143b373f7eac',1761237766,2,NULL),(12,1195,1,'b2abf83198beb8102bb1d8f4551c551b',1761237841,2,NULL),(13,1167,2,'3ed875b0bae4b75da523b04c2b93a308',1761238476,2,NULL),(14,2626,1,'63e692fcc17b7e54695f3a7b6592753b',1762940139,2,NULL),(15,2947,1,'9e98e3bb2acae1b703269f1dfc5a8ca3',1763114878,2,NULL),(16,2948,1,'dd3f18e440bd2dd15c74c3fc2e8ae4b5',1762949419,2,NULL),(17,2949,1,'5b1823e2b84f0a1eb4b9f1390ee0ab82',1763309376,2,NULL),(18,1249,2,'9b2684192c2bd23f8b52081766ba058d',1763008244,2,NULL),(19,1250,2,'7bc92980ccf1013b79528f0bf2503748',1763032333,2,NULL),(40,3,3,'16df28da55f1d5cc4e4e9e7c4fb4db9a',1793378089,2,NULL),(41,2952,1,'',0,2,NULL);
/*!40000 ALTER TABLE `reseller_checkoption` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resellers1`
--

DROP TABLE IF EXISTS `resellers1`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resellers1` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idReseller` int DEFAULT NULL,
  `login` char(20) NOT NULL,
  `password` char(40) NOT NULL,
  `type` int NOT NULL,
  `id_tariff` int NOT NULL,
  `callsLimit` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `clientsLimit` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `tech_prefix` text NOT NULL,
  `identifier` char(20) NOT NULL DEFAULT '',
  `Fullname` char(200) NOT NULL DEFAULT '',
  `Address` char(200) NOT NULL DEFAULT '',
  `City` char(50) NOT NULL DEFAULT '',
  `ZipCode` char(20) NOT NULL DEFAULT '',
  `Country` char(50) NOT NULL DEFAULT '',
  `Phone` char(50) NOT NULL DEFAULT '',
  `Email` char(200) NOT NULL DEFAULT '',
  `MaxClients` int NOT NULL DEFAULT '0',
  `template_id` int NOT NULL DEFAULT '-1',
  `TaxID` varchar(50) NOT NULL DEFAULT '',
  `storageLimit` int NOT NULL DEFAULT '0',
  `type2` int NOT NULL DEFAULT '0',
  `language` char(2) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UQ_Resellers1Login` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=2953 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resellers1`
--

LOCK TABLES `resellers1` WRITE;
/*!40000 ALTER TABLE `resellers1` DISABLE KEYS */;
INSERT INTO `resellers1` VALUES (2947,-1,'babau','babau',509,1,0.0000,0.0000,';;;','12323','Nazmul','Vazankara','chouddgram','3583','Bangladesh','01615911873','nazmulitbd@gmail.com',0,-1,'',0,0,''),(2948,-1,'kabu','kabu',1,1,0.0000,0.0000,';;;','12323','','','','','','','',0,-1,'',0,0,''),(2949,-1,'padua','padua',509,1,4976980.0000,100.0000,';;;','padua14gra','Sarwar Alom','Padua Laxmipur','Chouddagram','3452','Bangladesh','01711264134','sarass@test.com',0,-1,'',0,0,''),(2952,1255,'stor123','4147966706e77960519c',1,-1,0.0000,0.0000,'','','Test Sotore','','','','','','',0,-1,'',0,0,'');
/*!40000 ALTER TABLE `resellers1` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resellers2`
--

DROP TABLE IF EXISTS `resellers2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resellers2` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idReseller` int DEFAULT NULL,
  `login` char(20) NOT NULL,
  `password` char(40) NOT NULL,
  `type` int NOT NULL,
  `id_tariff` int NOT NULL,
  `callsLimit` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `clientsLimit` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `tech_prefix` text NOT NULL,
  `identifier` char(20) NOT NULL DEFAULT '',
  `Fullname` char(200) NOT NULL DEFAULT '',
  `Address` char(200) NOT NULL DEFAULT '',
  `City` char(50) NOT NULL DEFAULT '',
  `ZipCode` char(20) NOT NULL DEFAULT '',
  `Country` char(50) NOT NULL DEFAULT '',
  `Phone` char(50) NOT NULL DEFAULT '',
  `Email` char(200) NOT NULL DEFAULT '',
  `template_id` int NOT NULL DEFAULT '-1',
  `TaxID` varchar(50) NOT NULL DEFAULT '',
  `type2` int NOT NULL DEFAULT '0',
  `language` char(2) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UQ_Resellers2Login` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=1256 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resellers2`
--

LOCK TABLES `resellers2` WRITE;
/*!40000 ALTER TABLE `resellers2` DISABLE KEYS */;
INSERT INTO `resellers2` VALUES (1250,-1,'nazmul','nazmul',509,1,0.0000,0.0000,';;;','slkdfjs','Nazmul','Chouddagram','Cumilla','3583','Bangladesh','01711264147','nazmulitbd@gmail.com',-1,'',0,''),(1255,3,'nazmulitbd','0bc06874dccc46e5e454',1,-1,0.0000,0.0000,'','','rohim','','','','','','',-1,'',0,'');
/*!40000 ALTER TABLE `resellers2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resellers3`
--

DROP TABLE IF EXISTS `resellers3`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resellers3` (
  `id` int NOT NULL AUTO_INCREMENT,
  `login` char(20) NOT NULL,
  `password` char(40) NOT NULL,
  `type` int NOT NULL,
  `id_tariff` int NOT NULL,
  `callsLimit` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `clientsLimit` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `tech_prefix` text NOT NULL,
  `identifier` char(20) NOT NULL DEFAULT '',
  `Fullname` char(200) NOT NULL DEFAULT '',
  `Address` char(200) NOT NULL DEFAULT '',
  `City` char(50) NOT NULL DEFAULT '',
  `ZipCode` char(20) NOT NULL DEFAULT '',
  `Country` char(50) NOT NULL DEFAULT '',
  `Phone` char(50) NOT NULL DEFAULT '',
  `Email` char(200) NOT NULL DEFAULT '',
  `TaxID` varchar(50) NOT NULL DEFAULT '',
  `type2` int NOT NULL DEFAULT '0',
  `language` char(2) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UQ_Resellers3Login` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resellers3`
--

LOCK TABLES `resellers3` WRITE;
/*!40000 ALTER TABLE `resellers3` DISABLE KEYS */;
INSERT INTO `resellers3` VALUES (3,'BH_CIty','8c74ab12029c9df93193',1,-1,0.0000,0.0000,'','','Head Office','','','','','','','',0,'');
/*!40000 ALTER TABLE `resellers3` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resellers4`
--

DROP TABLE IF EXISTS `resellers4`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resellers4` (
  `id` int NOT NULL AUTO_INCREMENT,
  `login` char(20) NOT NULL,
  `password` char(20) NOT NULL,
  `type` int NOT NULL,
  `id_tariff` int NOT NULL,
  `callsLimit` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `clientsLimit` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `tech_prefix` char(255) NOT NULL DEFAULT '',
  `identifier` char(10) NOT NULL DEFAULT '',
  `Fullname` char(200) NOT NULL DEFAULT '',
  `Address` char(200) NOT NULL DEFAULT '',
  `City` char(50) NOT NULL DEFAULT '',
  `ZipCode` char(20) NOT NULL DEFAULT '',
  `Country` char(50) NOT NULL DEFAULT '',
  `Phone` char(50) NOT NULL DEFAULT '',
  `Email` char(200) NOT NULL DEFAULT '',
  `TaxID` varchar(50) NOT NULL DEFAULT '',
  `type2` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resellers4`
--

LOCK TABLES `resellers4` WRITE;
/*!40000 ALTER TABLE `resellers4` DISABLE KEYS */;
INSERT INTO `resellers4` VALUES (1,'hasan','123',13,2,0.0000,1.0000,';;;','asd','','','','','','','','',1);
/*!40000 ALTER TABLE `resellers4` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resellers4_child`
--

DROP TABLE IF EXISTS `resellers4_child`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resellers4_child` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reseller4_id` int DEFAULT NULL,
  `child_id` int DEFAULT NULL,
  `callsLimit` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `type` int DEFAULT NULL,
  `security` int DEFAULT NULL,
  `locked` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resellers4_child`
--

LOCK TABLES `resellers4_child` WRITE;
/*!40000 ALTER TABLE `resellers4_child` DISABLE KEYS */;
INSERT INTO `resellers4_child` VALUES (1,1,1,0.0000,NULL,NULL,1),(2,-1,325,0.0000,NULL,NULL,1);
/*!40000 ALTER TABLE `resellers4_child` ENABLE KEYS */;
UNLOCK TABLES;

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
  `product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sequence_type` enum('not_available','auto_generated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_available',
  `under_warranty` tinyint(1) NOT NULL DEFAULT '1',
  `sales_invoice_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `sales_date` date DEFAULT NULL,
  `sold_serial` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `delivery_date` date DEFAULT NULL,
  `problem_id` int unsigned DEFAULT NULL,
  `problem_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `engineer_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `no_of_time` int unsigned NOT NULL DEFAULT '0',
  `pre_job_ref_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `received_serial` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `amount_charges` decimal(15,2) NOT NULL DEFAULT '0.00',
  `need_diagnosis` tinyint(1) NOT NULL DEFAULT '0',
  `line_status` enum('received','diagnosed','sent_supplier','replaced_in','delivered','settled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_complain_items`
--

LOCK TABLES `rma_complain_items` WRITE;
/*!40000 ALTER TABLE `rma_complain_items` DISABLE KEYS */;
INSERT INTO `rma_complain_items` VALUES (4,4,1,73,'Keyboard','Logitech','K120','USB keyboard','not_available',0,'',0.00,NULL,'','RMA STORE',NULL,NULL,'No power','Service Desk',0,'','',1.000,'',0.00,1,'diagnosed','2026-10-30 19:03:03'),(5,5,1,76,'Monitor','Samsung','LS24C','24 inch IPS monitor','not_available',1,'',0.00,NULL,'','RMA STORE',NULL,NULL,'Overheating','Service Desk',0,'','',1.000,'',0.00,1,'diagnosed','2026-10-30 19:03:03'),(6,6,1,75,'Monitor','Dell','E2223HN','22 inch LED monitor','not_available',0,'',0.00,NULL,'','RMA STORE',NULL,NULL,'Overheating','Service Desk',0,'','',1.000,'',0.00,1,'diagnosed','2026-10-30 19:03:03'),(7,7,1,80,'UPS','APC','BX1100','1100VA UPS','not_available',1,'',0.00,NULL,'','RMA STORE',NULL,NULL,'No power','Service Desk',0,'','',1.000,'',0.00,1,'received','2026-10-30 19:03:04'),(8,8,1,71,'Access Point','TP-Link','EAP225','Ceiling access point','not_available',0,'',0.00,NULL,'','RMA STORE',NULL,NULL,'Overheating','Service Desk',0,'','',1.000,'',0.00,1,'received','2026-10-30 19:03:04'),(9,9,1,NULL,'','','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','not_available',1,'SI2026000078',0.00,NULL,'','RMA STORE',NULL,NULL,'SAMPLE No power','SAMPLE Engineer',0,'','Does not switch on',1.000,'SMPX10-0001',0.00,1,'received','2026-11-02 16:06:26');
/*!40000 ALTER TABLE `rma_complain_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_complains`
--

DROP TABLE IF EXISTS `rma_complains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_complains` (
  `complain_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `complain_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `complain_date` date NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_group` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attention` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('draft','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`complain_id`),
  UNIQUE KEY `uq_rma_complains_no` (`complain_no`),
  KEY `ix_rma_complains_date` (`complain_date`),
  KEY `ix_rma_complains_mobile` (`mobile`),
  KEY `ix_rma_complains_status` (`status`),
  KEY `fk_rma_complain_customer` (`customer_id`),
  CONSTRAINT `fk_rma_complain_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_complains`
--

LOCK TABLES `rma_complains` WRITE;
/*!40000 ALTER TABLE `rma_complains` DISABLE KEYS */;
INSERT INTO `rma_complains` VALUES (4,'DESCOM2026000001','','2026-10-14',83,'','Rahman Enterprise','01811200201','','','draft',NULL,NULL,10,'dummy_admin','2026-10-30 19:03:03','2026-10-30 19:04:00'),(5,'DESCOM2026000002','','2026-10-15',84,'','Karim Traders','01811200202','','','draft',NULL,NULL,10,'dummy_admin','2026-10-30 19:03:03','2026-10-30 19:04:00'),(6,'DESCOM2026000003','','2026-10-16',85,'','Bengal IT Solutions','01811200203','','','draft',NULL,NULL,10,'dummy_admin','2026-10-30 19:03:03','2026-10-30 19:04:00'),(7,'DESCOM2026000004','','2026-10-17',86,'','Sonar Bangla School','01811200204','','','draft',NULL,NULL,10,'dummy_admin','2026-10-30 19:03:04','2026-10-30 19:04:00'),(8,'DESCOM2026000005','','2026-10-18',87,'','City Pharma Ltd','01811200205','','','draft',NULL,NULL,10,'dummy_admin','2026-10-30 19:03:04','2026-10-30 19:04:00'),(9,'DESCOM2026000006','SMP-RMA-1','2026-11-02',NULL,'','SAMPLE Retail Customer','01611000401','Mirpur 10, Dhaka','','draft',NULL,NULL,63,'sample_admin','2026-11-02 16:06:26','2026-11-02 16:06:26');
/*!40000 ALTER TABLE `rma_complains` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_deliveries`
--

DROP TABLE IF EXISTS `rma_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_deliveries` (
  `delivery_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `delivery_date` date NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_group` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `total_receivable` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_payable` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_settled` tinyint(1) NOT NULL DEFAULT '0',
  `settled_at` datetime DEFAULT NULL,
  `status` enum('draft','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `rma_deliveries` WRITE;
/*!40000 ALTER TABLE `rma_deliveries` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_deliveries` ENABLE KEYS */;
UNLOCK TABLES;

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
  `complain_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `received_product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `received_model_no` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `received_serial` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `received_qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `adjustment_type` enum('without','receivable','payable','product_receivable','product_payable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'without',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `rma_delivery_items` WRITE;
/*!40000 ALTER TABLE `rma_delivery_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_delivery_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_diagnoses`
--

DROP TABLE IF EXISTS `rma_diagnoses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_diagnoses` (
  `diagnosis_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `diagnosis_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `diagnosis_date` date NOT NULL,
  `complain_item_id` bigint unsigned NOT NULL,
  `engineer_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `findings` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `result` enum('repairable','replace','no_fault','beyond_repair') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'repairable',
  `estimated_charge` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`diagnosis_id`),
  UNIQUE KEY `uq_rma_diagnoses_no` (`diagnosis_no`),
  KEY `ix_rma_diag_item` (`complain_item_id`),
  CONSTRAINT `fk_rma_diag_item` FOREIGN KEY (`complain_item_id`) REFERENCES `rma_complain_items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_diagnoses`
--

LOCK TABLES `rma_diagnoses` WRITE;
/*!40000 ALTER TABLE `rma_diagnoses` DISABLE KEYS */;
INSERT INTO `rma_diagnoses` VALUES (1,'DESDIA2026000001','2026-10-22',4,'Service Desk','Power board needs replacing','repairable',1500.00,10,'dummy_admin','2026-10-30 19:03:05'),(2,'DESDIA2026000002','2026-10-22',5,'Service Desk','Power board needs replacing','repairable',1500.00,10,'dummy_admin','2026-10-30 19:03:05'),(3,'DESDIA2026000003','2026-10-22',6,'Service Desk','Power board needs replacing','repairable',1500.00,10,'dummy_admin','2026-10-30 19:03:05');
/*!40000 ALTER TABLE `rma_diagnoses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_problems`
--

DROP TABLE IF EXISTS `rma_problems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_problems` (
  `problem_id` int unsigned NOT NULL AUTO_INCREMENT,
  `problem_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`problem_id`),
  UNIQUE KEY `uq_rma_problems_name` (`problem_name`)
) ENGINE=InnoDB AUTO_INCREMENT=814 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_problems`
--

LOCK TABLES `rma_problems` WRITE;
/*!40000 ALTER TABLE `rma_problems` DISABLE KEYS */;
INSERT INTO `rma_problems` VALUES (807,'DISPLAY',1,NULL,'2026-10-28 16:06:35','2026-10-28 16:06:35'),(808,'No power',1,NULL,'2026-10-28 16:06:35','2026-10-28 16:36:32'),(809,'No Detected',1,NULL,'2026-10-28 16:06:35','2026-10-28 16:06:35'),(810,'Hang Problem',1,NULL,'2026-10-28 16:06:35','2026-10-28 16:06:35'),(811,'RAM Burn',1,10,'2026-10-28 16:36:59','2026-10-28 16:36:59'),(813,'SAMPLE No power',1,63,'2026-11-02 16:06:23','2026-11-02 16:06:23');
/*!40000 ALTER TABLE `rma_problems` ENABLE KEYS */;
UNLOCK TABLES;

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
  `out_job_ref_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_model_no` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_serial` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `adjustment_type` enum('without','receivable','payable','product_receivable','product_payable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'without',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `serial_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `rma_replace_in_items` WRITE;
/*!40000 ALTER TABLE `rma_replace_in_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_replace_in_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_replace_ins`
--

DROP TABLE IF EXISTS `rma_replace_ins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_replace_ins` (
  `replace_in_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `replace_in_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `replace_date` date NOT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `supplier_group` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `supplier_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `narration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `total_receivable` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_payable` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `rma_replace_ins` WRITE;
/*!40000 ALTER TABLE `rma_replace_ins` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_replace_ins` ENABLE KEYS */;
UNLOCK TABLES;

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
  `product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sequence_type` enum('not_available','available') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_available',
  `under_warranty` tinyint(1) NOT NULL DEFAULT '1',
  `serial_common` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_from` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_to` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `no_of_time` int unsigned NOT NULL DEFAULT '0',
  `pre_job_ref_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `problem_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `purchase_inv_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `purchase_date` date DEFAULT NULL,
  `purchase_serial` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_serial` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qty` decimal(15,3) NOT NULL DEFAULT '1.000',
  `line_status` enum('sent','received','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
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

LOCK TABLES `rma_replace_out_items` WRITE;
/*!40000 ALTER TABLE `rma_replace_out_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_replace_out_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_replace_outs`
--

DROP TABLE IF EXISTS `rma_replace_outs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_replace_outs` (
  `replace_out_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `replace_out_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `replace_date` date NOT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `supplier_group` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `supplier_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attention` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('draft','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `rma_replace_outs` WRITE;
/*!40000 ALTER TABLE `rma_replace_outs` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_replace_outs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_sequences`
--

DROP TABLE IF EXISTS `rma_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_sequences` (
  `doc_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seq_year` smallint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`doc_type`,`seq_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_sequences`
--

LOCK TABLES `rma_sequences` WRITE;
/*!40000 ALTER TABLE `rma_sequences` DISABLE KEYS */;
INSERT INTO `rma_sequences` VALUES ('COM',2026,6),('DIA',2026,3);
/*!40000 ALTER TABLE `rma_sequences` ENABLE KEYS */;
UNLOCK TABLES;

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
  `complain_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `rma_service_bill_items` WRITE;
/*!40000 ALTER TABLE `rma_service_bill_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_service_bill_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_service_bills`
--

DROP TABLE IF EXISTS `rma_service_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_service_bills` (
  `bill_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bill_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bill_date` date NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_group` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `narration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `rma_service_bills` WRITE;
/*!40000 ALTER TABLE `rma_service_bills` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_service_bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_stock_ledger`
--

DROP TABLE IF EXISTS `rma_stock_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_stock_ledger` (
  `ledger_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `txn_date` date NOT NULL,
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category` enum('faulty','good') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'faulty',
  `serial_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qty_in` decimal(15,3) NOT NULL DEFAULT '0.000',
  `qty_out` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `doc_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `doc_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `party_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `rma_stock_ledger` WRITE;
/*!40000 ALTER TABLE `rma_stock_ledger` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_stock_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_store_settings`
--

DROP TABLE IF EXISTS `rma_store_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_store_settings` (
  `purpose` enum('complain_receive','replace_out','replace_in','delivery','utilize') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_store_settings`
--

LOCK TABLES `rma_store_settings` WRITE;
/*!40000 ALTER TABLE `rma_store_settings` DISABLE KEYS */;
INSERT INTO `rma_store_settings` VALUES ('complain_receive','RMA STORE',10,'2026-10-28 16:07:34'),('replace_out','RMA STORE',10,'2026-10-28 16:07:34'),('replace_in','RMA STORE',10,'2026-10-28 16:07:34'),('delivery','RMA STORE',10,'2026-10-28 16:07:34'),('utilize','RMA STORE',10,'2026-10-28 16:07:34');
/*!40000 ALTER TABLE `rma_store_settings` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_suppliers`
--

LOCK TABLES `rma_suppliers` WRITE;
/*!40000 ALTER TABLE `rma_suppliers` DISABLE KEYS */;
INSERT INTO `rma_suppliers` VALUES (5,'Importer','Dhaka Network House','01711100101','Manager','Motijheel, Dhaka',1,'2026-10-30 18:59:18'),(6,'Local Supplier','IDB Tech Traders','01711100102','Manager','IDB Bhaban, Agargaon',1,'2026-10-30 18:59:18'),(7,'Importer','Global Power Imports','01711100103','Manager','Tejgaon, Dhaka',1,'2026-10-30 18:59:18'),(8,'Local Supplier','Star Computer Distribution','01711100104','Manager','Elephant Road, Dhaka',1,'2026-10-30 18:59:18'),(9,'Local Supplier','PrintLine Bangladesh','01711100105','Manager','Nawabpur, Dhaka',1,'2026-10-30 18:59:19'),(10,'','SAMPLE Tech Distributors Ltd','01811000201','Karim','Motijheel, Dhaka',1,'2026-11-02 15:59:24');
/*!40000 ALTER TABLE `rma_suppliers` ENABLE KEYS */;
UNLOCK TABLES;

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
  `product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_no` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `rma_utilize_items` WRITE;
/*!40000 ALTER TABLE `rma_utilize_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_utilize_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_utilizes`
--

DROP TABLE IF EXISTS `rma_utilizes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rma_utilizes` (
  `utilize_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `utilize_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `utilize_date` date NOT NULL,
  `complain_item_id` bigint unsigned DEFAULT NULL,
  `job_ref_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `engineer_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `rma_utilizes` WRITE;
/*!40000 ALTER TABLE `rma_utilizes` DISABLE KEYS */;
/*!40000 ALTER TABLE `rma_utilizes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_customers`
--

DROP TABLE IF EXISTS `sales_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_customers` (
  `customer_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_group` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attention` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bin` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nid` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `branch_id` int unsigned DEFAULT NULL COMMENT 'Owning store branch (NULL = Head Office)',
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uq_sales_customers_code` (`customer_code`),
  KEY `ix_sales_customers_name` (`customer_name`),
  KEY `ix_sales_customers_mobile` (`mobile`),
  KEY `ix_sales_customers_branch` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_customers`
--

LOCK TABLES `sales_customers` WRITE;
/*!40000 ALTER TABLE `sales_customers` DISABLE KEYS */;
INSERT INTO `sales_customers` VALUES (51,'CRC000005','test','nazmul','0178653523','sfkdslf','asfasdf','24234','35353','2026-10-30 06:24:18','2026-10-30 06:24:18',NULL),(55,'CRC000006','test','Nazmul Bhuiyan','01711264140','Sadman','dhanmondi','32423423423','23423432432','2026-10-30 06:50:37','2026-10-30 06:50:37',NULL),(83,'CRC000018','Corporate','Rahman Enterprise','01811200201','Accounts','Mirpur 10, Dhaka','','','2026-10-30 18:59:19','2026-10-30 18:59:19',NULL),(84,'CRC000019','Retail','Karim Traders','01811200202','Accounts','Uttara Sector 7, Dhaka','','','2026-10-30 18:59:19','2026-10-30 18:59:19',NULL),(85,'CRC000020','Corporate','Bengal IT Solutions','01811200203','Accounts','Banani, Dhaka','','','2026-10-30 18:59:19','2026-10-30 18:59:19',NULL),(86,'CRC000021','Corporate','Sonar Bangla School','01811200204','Accounts','Gazipur Sadar','','','2026-10-30 18:59:19','2026-10-30 18:59:19',NULL),(87,'CRC000022','Corporate','City Pharma Ltd','01811200205','Accounts','Dhanmondi, Dhaka','','','2026-10-30 18:59:20','2026-10-30 18:59:20',NULL),(88,'CRC000023','Retail','Hasan Computer Point','01811200206','Accounts','Comilla Town','','','2026-10-30 18:59:20','2026-10-30 18:59:20',NULL),(89,'CRC000024','Retail','Amin & Sons','01811200207','Accounts','Chattogram','','','2026-10-30 18:59:20','2026-10-30 18:59:20',NULL),(90,'CRC000025','Corporate','Green Valley Hospital','01811200208','Accounts','Sylhet','','','2026-10-30 18:59:20','2026-10-30 18:59:20',NULL),(91,'CRC000026','Retail','Noor Fashion House','01811200209','Accounts','Narayanganj','','','2026-10-30 18:59:20','2026-10-30 18:59:20',NULL),(92,'CRC000027','Corporate','Delta Garments Ltd','01811200210','Accounts','Savar, Dhaka','','','2026-10-30 18:59:20','2026-10-30 18:59:20',NULL),(93,'CRC000029','','SAMPLE Corporate Buyer Ltd','01911000301','Nasrin','Gulshan 1, Dhaka','','','2026-11-02 15:59:27','2026-11-02 15:59:27',NULL),(94,'DLR000030','','SAMPLE Retail Customer','01611000401','','Mirpur 10, Dhaka','','','2026-11-02 15:59:29','2026-11-02 15:59:29',NULL);
/*!40000 ALTER TABLE `sales_customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_doc_attachments`
--

DROP TABLE IF EXISTS `sales_doc_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_doc_attachments` (
  `attachment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_kind` enum('invoice','order') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_id` bigint unsigned NOT NULL,
  `original_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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

LOCK TABLES `sales_doc_attachments` WRITE;
/*!40000 ALTER TABLE `sales_doc_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_doc_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_doc_sequences`
--

DROP TABLE IF EXISTS `sales_doc_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_doc_sequences` (
  `doc_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence_year` smallint unsigned NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`doc_type`,`sequence_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_doc_sequences`
--

LOCK TABLES `sales_doc_sequences` WRITE;
/*!40000 ALTER TABLE `sales_doc_sequences` DISABLE KEYS */;
INSERT INTO `sales_doc_sequences` VALUES ('SR',2026,6);
/*!40000 ALTER TABLE `sales_doc_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_invoice_exchanges`
--

DROP TABLE IF EXISTS `sales_invoice_exchanges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoice_exchanges` (
  `exchange_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `quantity` decimal(15,3) NOT NULL DEFAULT '1.000',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`exchange_id`),
  KEY `ix_sales_invoice_exchanges_invoice` (`invoice_id`),
  CONSTRAINT `fk_sales_exchange_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_exchanges`
--

LOCK TABLES `sales_invoice_exchanges` WRITE;
/*!40000 ALTER TABLE `sales_invoice_exchanges` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_invoice_exchanges` ENABLE KEYS */;
UNLOCK TABLES;

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
  `product_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_caption` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_narration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `warranty` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `sequence_type` enum('not_available','available','bulk') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_available',
  `serial_clear` tinyint(1) NOT NULL DEFAULT '0',
  `serial_storage` tinyint(1) NOT NULL DEFAULT '0',
  `serial_common` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_from` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_to` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `serial_imei` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB AUTO_INCREMENT=257 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_items`
--

LOCK TABLES `sales_invoice_items` WRITE;
/*!40000 ALTER TABLE `sales_invoice_items` DISABLE KEYS */;
INSERT INTO `sales_invoice_items` VALUES (1,1,NULL,'','','','','sfdasdfasdf','asfddsaf','2','','Pcs','not_available',0,0,'wer','123213','32423','234324',1.000,0.000,0.00,0.00,0.00,0.00,1,'2026-10-27 14:46:21'),(2,2,NULL,'','Retail','A4tech','01615911873','Keyboard Mouse combo','Wireless combo keyboard mouse','1','Elephant Road','Pcs','not_available',0,0,'abcd324','123','434','ASDS675G6654D',1.000,1.000,2000.00,0.00,50.00,1950.00,1,'2026-10-27 15:07:04'),(117,120,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,3900.00,3000.00,0.00,7800.00,1,'2026-10-30 19:00:31'),(118,121,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,15800.00,12900.00,0.00,31600.00,1,'2026-10-30 19:00:32'),(119,122,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,12800.00,10400.00,0.00,51200.00,1,'2026-10-30 19:00:32'),(120,122,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,11800.00,9500.00,0.00,11800.00,2,'2026-10-30 19:00:32'),(121,123,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,12800.00,10400.00,0.00,51200.00,1,'2026-10-30 19:00:32'),(122,124,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,3900.00,3000.00,0.00,11700.00,1,'2026-10-30 19:00:33'),(123,124,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,9500.00,7600.00,0.00,38000.00,2,'2026-10-30 19:00:33'),(124,125,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,16500.00,13200.00,0.00,33000.00,1,'2026-10-30 19:00:33'),(125,125,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,850.00,600.00,0.00,850.00,2,'2026-10-30 19:00:33'),(126,126,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,5200.00,3900.00,0.00,5200.00,1,'2026-10-30 19:00:33'),(127,126,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,9500.00,7600.00,0.00,28500.00,2,'2026-10-30 19:00:33'),(128,127,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,850.00,600.00,0.00,1700.00,1,'2026-10-30 19:00:34'),(129,127,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,16500.00,13200.00,0.00,49500.00,2,'2026-10-30 19:00:34'),(130,127,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,12800.00,10400.00,0.00,38400.00,3,'2026-10-30 19:00:34'),(131,128,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,11800.00,9500.00,0.00,23600.00,1,'2026-10-30 19:00:34'),(132,129,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,9500.00,7600.00,0.00,28500.00,1,'2026-10-30 19:00:34'),(133,129,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,16500.00,13200.00,0.00,49500.00,2,'2026-10-30 19:00:34'),(134,129,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,15800.00,12900.00,0.00,15800.00,3,'2026-10-30 19:00:34'),(135,130,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,12800.00,10400.00,0.00,12800.00,1,'2026-10-30 19:00:35'),(136,130,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,2100.00,1600.00,0.00,8400.00,2,'2026-10-30 19:00:35'),(137,131,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,16500.00,13200.00,0.00,33000.00,1,'2026-10-30 19:00:35'),(138,132,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,5200.00,3900.00,0.00,20800.00,1,'2026-10-30 19:00:36'),(139,132,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,11500.00,9100.00,0.00,11500.00,2,'2026-10-30 19:00:36'),(140,132,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,6800.00,5400.00,0.00,27200.00,3,'2026-10-30 19:00:36'),(141,133,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,11800.00,9500.00,0.00,35400.00,1,'2026-10-30 19:00:36'),(142,133,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,16500.00,13200.00,0.00,49500.00,2,'2026-10-30 19:00:36'),(143,134,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,11800.00,9500.00,0.00,11800.00,1,'2026-10-30 19:00:36'),(144,135,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,16500.00,13200.00,0.00,49500.00,1,'2026-10-30 19:00:37'),(145,135,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,16900.00,13900.00,0.00,16900.00,2,'2026-10-30 19:00:37'),(146,135,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,11800.00,9500.00,0.00,35400.00,3,'2026-10-30 19:00:37'),(147,136,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,16500.00,13200.00,0.00,16500.00,1,'2026-10-30 19:00:37'),(148,137,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,16900.00,13900.00,0.00,33800.00,1,'2026-10-30 19:00:37'),(149,137,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,3200.00,2500.00,0.00,9600.00,2,'2026-10-30 19:00:37'),(150,137,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,11800.00,9500.00,0.00,47200.00,3,'2026-10-30 19:00:37'),(151,138,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,1150.00,820.00,0.00,1150.00,1,'2026-10-30 19:00:38'),(152,138,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,7800.00,6200.00,0.00,31200.00,2,'2026-10-30 19:00:38'),(153,138,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,9500.00,7600.00,0.00,28500.00,3,'2026-10-30 19:00:38'),(154,139,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,5200.00,3900.00,0.00,20800.00,1,'2026-10-30 19:00:38'),(155,139,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,17500.00,14300.00,0.00,52500.00,2,'2026-10-30 19:00:38'),(156,140,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,9500.00,7600.00,0.00,9500.00,1,'2026-10-30 19:00:38'),(157,141,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,6800.00,5400.00,0.00,13600.00,1,'2026-10-30 19:00:39'),(158,141,68,'NET-1002','Router','Cisco','RV340','Business VPN router','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,28500.00,23000.00,0.00,114000.00,2,'2026-10-30 19:00:39'),(159,141,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,3200.00,2500.00,0.00,3200.00,3,'2026-10-30 19:00:39'),(160,142,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,6400.00,5100.00,0.00,19200.00,1,'2026-10-30 19:00:39'),(161,142,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,11500.00,9100.00,0.00,34500.00,2,'2026-10-30 19:00:39'),(162,142,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,1150.00,820.00,0.00,2300.00,3,'2026-10-30 19:00:39'),(163,143,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,17500.00,14300.00,0.00,35000.00,1,'2026-10-30 19:00:39'),(164,143,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,16900.00,13900.00,0.00,67600.00,2,'2026-10-30 19:00:39'),(165,143,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,850.00,600.00,0.00,1700.00,3,'2026-10-30 19:00:39'),(166,144,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,11800.00,9500.00,0.00,11800.00,1,'2026-10-30 19:00:40'),(167,144,68,'NET-1002','Router','Cisco','RV340','Business VPN router','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,28500.00,23000.00,0.00,85500.00,2,'2026-10-30 19:00:40'),(168,144,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,9500.00,7600.00,0.00,28500.00,3,'2026-10-30 19:00:40'),(169,145,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,5200.00,3900.00,0.00,10400.00,1,'2026-10-30 19:00:40'),(170,146,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,5200.00,3900.00,0.00,10400.00,1,'2026-10-30 19:00:41'),(171,146,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,15800.00,12900.00,0.00,15800.00,2,'2026-10-30 19:00:41'),(172,147,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,5200.00,3900.00,0.00,15600.00,1,'2026-10-30 19:00:41'),(173,147,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,12800.00,10400.00,0.00,12800.00,2,'2026-10-30 19:00:41'),(174,148,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,5200.00,3900.00,0.00,15600.00,1,'2026-10-30 19:00:41'),(175,148,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,16900.00,13900.00,0.00,33800.00,2,'2026-10-30 19:00:41'),(176,149,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,17500.00,14300.00,0.00,35000.00,1,'2026-10-30 19:00:42'),(177,149,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,11800.00,9500.00,0.00,11800.00,2,'2026-10-30 19:00:42'),(178,149,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,11500.00,9100.00,0.00,23000.00,3,'2026-10-30 19:00:42'),(179,150,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,16500.00,13200.00,0.00,16500.00,1,'2026-10-30 19:00:42'),(180,151,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,5200.00,3900.00,0.00,10400.00,1,'2026-10-30 19:00:42'),(181,152,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,16500.00,13200.00,0.00,66000.00,1,'2026-10-30 19:00:43'),(182,152,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,2100.00,1600.00,0.00,6300.00,2,'2026-10-30 19:00:43'),(183,153,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,17500.00,14300.00,0.00,35000.00,1,'2026-10-30 19:00:43'),(184,153,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,16500.00,13200.00,0.00,49500.00,2,'2026-10-30 19:00:43'),(185,154,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,2100.00,1600.00,0.00,2100.00,1,'2026-10-30 19:00:43'),(186,155,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,1150.00,820.00,0.00,4600.00,1,'2026-10-30 19:00:44'),(187,155,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,9500.00,7600.00,0.00,28500.00,2,'2026-10-30 19:00:44'),(188,155,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,6800.00,5400.00,0.00,20400.00,3,'2026-10-30 19:00:44'),(189,156,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,3900.00,3000.00,0.00,7800.00,1,'2026-10-30 19:00:44'),(190,157,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,4600.00,3500.00,0.00,9200.00,1,'2026-10-30 19:00:45'),(191,157,79,'PWR-3001','UPS','APC','BX650','650VA line interactive UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,6400.00,5100.00,0.00,12800.00,2,'2026-10-30 19:00:45'),(192,158,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,12800.00,10400.00,0.00,12800.00,1,'2026-10-30 19:00:45'),(193,158,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,6800.00,5400.00,0.00,20400.00,2,'2026-10-30 19:00:45'),(194,159,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,7800.00,6200.00,0.00,23400.00,1,'2026-10-30 19:00:45'),(195,159,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,5200.00,3900.00,0.00,10400.00,2,'2026-10-30 19:00:45'),(196,160,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,12800.00,10400.00,0.00,38400.00,1,'2026-10-30 19:00:46'),(197,161,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,5200.00,3900.00,0.00,15600.00,1,'2026-10-30 19:00:46'),(198,162,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,5200.00,3900.00,0.00,20800.00,1,'2026-10-30 19:00:46'),(199,162,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,15800.00,12900.00,0.00,47400.00,2,'2026-10-30 19:00:46'),(200,162,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,850.00,600.00,0.00,2550.00,3,'2026-10-30 19:00:46'),(201,163,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,12800.00,10400.00,0.00,38400.00,1,'2026-10-30 19:00:47'),(202,164,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,17500.00,14300.00,0.00,52500.00,1,'2026-10-30 19:00:47'),(203,164,85,'PRN-4004','Toner','Canon','CRG 325','Canon black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,4600.00,3500.00,0.00,4600.00,2,'2026-10-30 19:00:47'),(204,165,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,12800.00,10400.00,0.00,25600.00,1,'2026-10-30 19:00:47'),(205,165,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,6800.00,5400.00,0.00,27200.00,2,'2026-10-30 19:00:47'),(206,165,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,3200.00,2500.00,0.00,9600.00,3,'2026-10-30 19:00:47'),(207,166,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,3200.00,2500.00,0.00,9600.00,1,'2026-10-30 19:00:48'),(208,166,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,16500.00,13200.00,0.00,33000.00,2,'2026-10-30 19:00:48'),(209,166,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,7800.00,6200.00,0.00,31200.00,3,'2026-10-30 19:00:48'),(210,167,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,16900.00,13900.00,0.00,33800.00,1,'2026-10-30 19:00:48'),(211,167,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,2100.00,1600.00,0.00,2100.00,2,'2026-10-30 19:00:48'),(212,167,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,11800.00,9500.00,0.00,11800.00,3,'2026-10-30 19:00:48'),(213,168,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,5200.00,3900.00,0.00,10400.00,1,'2026-10-30 19:00:48'),(214,169,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,11500.00,9100.00,0.00,46000.00,1,'2026-10-30 19:00:49'),(215,169,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,6800.00,5400.00,0.00,20400.00,2,'2026-10-30 19:00:49'),(216,170,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,17500.00,14300.00,0.00,35000.00,1,'2026-10-30 19:00:49'),(217,171,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,3900.00,3000.00,0.00,11700.00,1,'2026-10-30 19:00:50'),(218,171,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,850.00,600.00,0.00,2550.00,2,'2026-10-30 19:00:50'),(219,172,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,11800.00,9500.00,0.00,11800.00,1,'2026-10-30 19:00:50'),(220,173,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,15800.00,12900.00,0.00,47400.00,1,'2026-10-30 19:00:50'),(221,174,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,9500.00,7600.00,0.00,19000.00,1,'2026-10-30 19:00:51'),(222,174,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,2100.00,1600.00,0.00,6300.00,2,'2026-10-30 19:00:51'),(223,175,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,3200.00,2500.00,0.00,12800.00,1,'2026-10-30 19:00:51'),(224,176,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,7800.00,6200.00,0.00,23400.00,1,'2026-10-30 19:00:51'),(225,176,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',4.000,0.000,15800.00,12900.00,0.00,63200.00,2,'2026-10-30 19:00:51'),(226,176,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,850.00,600.00,0.00,1700.00,3,'2026-10-30 19:00:51'),(227,177,68,'NET-1002','Router','Cisco','RV340','Business VPN router','','1 Year','','Pcs','not_available',0,0,'','','','',2.000,0.000,28500.00,0.00,0.00,57000.00,1,'2026-10-30 19:00:57'),(228,178,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','','1 Year','','Pcs','not_available',0,0,'','','','',1.000,0.000,9500.00,0.00,0.00,9500.00,1,'2026-10-30 19:00:57'),(229,179,84,'PRN-4003','Toner','HP','CF150A','Black toner','','1 Year','','Pcs','not_available',0,0,'','','','',2.000,0.000,5200.00,0.00,0.00,10400.00,1,'2026-10-30 19:00:57'),(230,180,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','','1 Year','','Pcs','not_available',0,0,'','','','',1.000,0.000,3900.00,0.00,0.00,3900.00,1,'2026-10-30 19:00:57'),(231,181,68,'NET-1002','Router','Cisco','RV340','Business VPN router','','1 Year','','Pcs','not_available',0,0,'','','','',1.000,0.000,28500.00,0.00,0.00,28500.00,1,'2026-10-30 19:00:58'),(232,182,71,'NET-1005','Access Point','TP-Link','EAP225','Ceiling access point','','1 Year','','Pcs','not_available',0,0,'','','','',2.000,0.000,7800.00,0.00,0.00,15600.00,1,'2026-10-30 19:00:58'),(233,183,82,'PRN-4001','Laser Printer','HP','LaserJet M111w','Mono laser printer','','1 Year','','Pcs','not_available',0,0,'','','','',1.000,0.000,17500.00,0.00,0.00,17500.00,1,'2026-10-30 19:00:58'),(234,184,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','','1 Year','','Pcs','not_available',0,0,'','','','',2.000,0.000,1150.00,0.00,0.00,2300.00,1,'2026-10-30 19:00:59'),(235,185,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','','1 Year','','Pcs','not_available',0,0,'','','','',2.000,0.000,2100.00,0.00,0.00,4200.00,1,'2026-10-30 19:00:59'),(236,186,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','','Pcs','not_available',0,0,'','','','',1.000,0.000,11800.00,0.00,0.00,11800.00,1,'2026-10-30 19:00:59'),(237,187,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',2.000,0.000,15800.00,0.00,0.00,31600.00,1,'2026-10-30 19:01:02'),(238,187,68,'NET-1002','Router','Cisco','RV340','Business VPN router','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,28500.00,0.00,0.00,28500.00,2,'2026-10-30 19:01:02'),(239,188,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,16500.00,0.00,0.00,49500.00,1,'2026-10-30 19:01:03'),(240,188,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,11500.00,0.00,0.00,34500.00,2,'2026-10-30 19:01:03'),(241,189,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',1.000,0.000,6800.00,0.00,0.00,6800.00,1,'2026-10-30 19:01:03'),(242,189,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','','1 Year','Main Store','Pcs','not_available',0,0,'','','','',3.000,0.000,3200.00,0.00,0.00,9600.00,2,'2026-10-30 19:01:03'),(243,190,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','','1 Year','','Pcs','not_available',0,0,'','','','',1.000,0.000,9500.00,0.00,0.00,9500.00,1,'2026-10-31 05:08:34'),(244,191,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','','1 Year','','Pcs','not_available',0,0,'','','','',1.000,0.000,6800.00,0.00,10.00,6790.00,1,'2026-10-31 06:18:27'),(245,192,75,'ACC-2003','Monitor','Dell','E2223HN','22 inch LED monitor','','1 Year','','Pcs','not_available',0,0,'','','','',3.000,0.000,12800.00,0.00,0.00,38400.00,1,'2026-11-02 15:06:15'),(246,193,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','','SAMPLE 1 Year','Main Store','SAMPLE Box','not_available',0,0,'','','','',1.000,0.000,25000.00,21000.00,0.00,25000.00,1,'2026-11-02 16:00:53'),(247,193,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','','SAMPLE 1 Year','Main Store','SAMPLE Box','not_available',0,0,'','','','',2.000,0.000,3500.00,2600.00,0.00,7000.00,2,'2026-11-02 16:00:53'),(248,194,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','','SAMPLE 1 Year','','SAMPLE Box','not_available',0,0,'','','','',1.000,0.000,3500.00,0.00,0.00,3500.00,1,'2026-11-02 16:00:56'),(249,194,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','','SAMPLE 1 Year','','SAMPLE Box','not_available',0,0,'','','','',1.000,0.000,25000.00,0.00,0.00,25000.00,2,'2026-11-02 16:00:56'),(250,195,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','','SAMPLE 1 Year','Main Store','SAMPLE Box','not_available',0,0,'','','','',1.000,0.000,25000.00,21000.00,0.00,25000.00,1,'2026-11-02 16:01:43'),(251,195,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','','SAMPLE 1 Year','Main Store','SAMPLE Box','not_available',0,0,'','','','',2.000,0.000,3500.00,2600.00,0.00,7000.00,2,'2026-11-02 16:01:43'),(252,196,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','','SAMPLE 1 Year','Main Store','SAMPLE Box','not_available',0,0,'','','','',3.000,0.000,24500.00,0.00,0.00,73500.00,1,'2026-11-02 16:05:48'),(253,196,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','','SAMPLE 1 Year','Main Store','SAMPLE Box','not_available',0,0,'','','','',3.000,0.000,3400.00,0.00,0.00,10200.00,2,'2026-11-02 16:05:48'),(254,197,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','','1 Year','','Pcs','not_available',0,0,'','','','',1.000,0.000,11800.00,0.00,0.00,11800.00,1,'2026-11-05 15:52:45'),(255,198,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','','1 Year','','Pcs','not_available',0,0,'','','','',1.000,0.000,16900.00,0.00,0.00,16900.00,1,'2026-11-05 15:53:43'),(256,199,68,'NET-1002','Router','Cisco','RV340','Business VPN router','','1 Year','','Pcs','not_available',0,0,'','','','',1.000,0.000,28500.00,0.00,10.00,28490.00,1,'2026-11-05 15:54:53');
/*!40000 ALTER TABLE `sales_invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

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
  `payment_method` enum('cash','bank','mobile','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `amount` decimal(15,2) NOT NULL,
  `reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `received_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `ix_sales_payments_invoice` (`invoice_id`),
  CONSTRAINT `fk_sales_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=187 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_payments`
--

LOCK TABLES `sales_invoice_payments` WRITE;
/*!40000 ALTER TABLE `sales_invoice_payments` DISABLE KEYS */;
INSERT INTO `sales_invoice_payments` VALUES (1,1,'2026-10-27 20:46:21','cash',1000.00,'',10,'2026-10-27 14:46:21'),(2,2,'2026-10-27 21:07:04','cash',2000.00,'cash',10,'2026-10-27 15:07:04'),(118,121,'2026-10-31 01:00:32','cash',31600.00,'Cash at sale',47,'2026-10-30 19:00:32'),(119,122,'2026-10-31 01:00:32','cash',59850.00,'Cash at sale',47,'2026-10-30 19:00:32'),(120,124,'2026-10-31 01:00:33','cash',47215.00,'Cash at sale',47,'2026-10-30 19:00:33'),(121,126,'2026-10-31 01:00:33','cash',35385.00,'Cash at sale',47,'2026-10-30 19:00:33'),(122,127,'2026-10-31 01:00:34','cash',89600.00,'Cash at sale',47,'2026-10-30 19:00:34'),(123,130,'2026-10-31 01:00:35','cash',20776.00,'Cash at sale',47,'2026-10-30 19:00:35'),(124,131,'2026-10-31 01:00:35','cash',33000.00,'Cash at sale',47,'2026-10-30 19:00:35'),(125,133,'2026-10-31 01:00:36','cash',80655.00,'Cash at sale',47,'2026-10-30 19:00:36'),(126,136,'2026-10-31 01:00:37','cash',16500.00,'Cash at sale',47,'2026-10-30 19:00:37'),(127,137,'2026-10-31 01:00:37','cash',86070.00,'Cash at sale',47,'2026-10-30 19:00:37'),(128,138,'2026-10-31 01:00:38','cash',63892.00,'Cash at sale',47,'2026-10-30 19:00:38'),(129,139,'2026-10-31 01:00:38','cash',71834.00,'Cash at sale',47,'2026-10-30 19:00:38'),(130,140,'2026-10-31 01:00:38','cash',9310.00,'Cash at sale',47,'2026-10-30 19:00:38'),(131,142,'2026-10-31 01:00:39','cash',54880.00,'Cash at sale',47,'2026-10-30 19:00:39'),(132,144,'2026-10-31 01:00:40','cash',129448.00,'Cash at sale',47,'2026-10-30 19:00:40'),(133,146,'2026-10-31 01:00:41','cash',26200.00,'Cash at sale',47,'2026-10-30 19:00:41'),(134,147,'2026-10-31 01:00:41','cash',29820.00,'Cash at sale',47,'2026-10-30 19:00:41'),(135,148,'2026-10-31 01:00:41','cash',46930.00,'Cash at sale',47,'2026-10-30 19:00:41'),(136,149,'2026-10-31 01:00:42','cash',68404.00,'Cash at sale',47,'2026-10-30 19:00:42'),(137,150,'2026-10-31 01:00:42','cash',16978.00,'Cash at sale',47,'2026-10-30 19:00:42'),(138,151,'2026-10-31 01:00:42','cash',10400.00,'Cash at sale',47,'2026-10-30 19:00:42'),(139,155,'2026-10-31 01:00:44','cash',50825.00,'Cash at sale',47,'2026-10-30 19:00:44'),(140,161,'2026-10-31 01:00:46','cash',14820.00,'Cash at sale',47,'2026-10-30 19:00:46'),(141,163,'2026-10-31 01:00:47','cash',38400.00,'Cash at sale',47,'2026-10-30 19:00:47'),(142,165,'2026-10-31 01:00:47','cash',64210.00,'Cash at sale',47,'2026-10-30 19:00:47'),(143,170,'2026-10-31 01:00:49','cash',35000.00,'Cash at sale',47,'2026-10-30 19:00:49'),(144,171,'2026-10-31 01:00:50','cash',14215.00,'Cash at sale',47,'2026-10-30 19:00:50'),(145,173,'2026-10-31 01:00:50','cash',45030.00,'Cash at sale',47,'2026-10-30 19:00:50'),(146,175,'2026-10-31 01:00:51','cash',12800.00,'Cash at sale',47,'2026-10-30 19:00:51'),(147,176,'2026-10-31 01:00:51','cash',86534.00,'Cash at sale',47,'2026-10-30 19:00:51'),(148,120,'2026-10-31 01:00:52','bank',7780.00,'',47,'2026-10-30 19:00:52'),(149,123,'2026-10-31 01:00:52','cash',25536.00,'',47,'2026-10-30 19:00:52'),(150,125,'2026-10-31 01:00:52','bank',33850.00,'',47,'2026-10-30 19:00:52'),(151,128,'2026-10-31 01:00:53','bank',11800.00,'',47,'2026-10-30 19:00:53'),(152,129,'2026-10-31 01:00:53','bank',96520.00,'',47,'2026-10-30 19:00:53'),(153,132,'2026-10-31 01:00:53','bank',30613.00,'',47,'2026-10-30 19:00:53'),(154,134,'2026-10-31 01:00:53','cash',11564.00,'',47,'2026-10-30 19:00:53'),(155,135,'2026-10-31 01:00:54','cash',53445.00,'',47,'2026-10-30 19:00:54'),(156,141,'2026-10-31 01:00:54','bank',137340.00,'',47,'2026-10-30 19:00:54'),(157,143,'2026-10-31 01:00:54','bank',49542.00,'',47,'2026-10-30 19:00:54'),(158,145,'2026-10-31 01:00:54','bank',10192.00,'',47,'2026-10-30 19:00:54'),(159,152,'2026-10-31 01:00:55','cash',35427.00,'',47,'2026-10-30 19:00:55'),(160,153,'2026-10-31 01:00:55','bank',84289.00,'',47,'2026-10-30 19:00:55'),(161,154,'2026-10-31 01:00:55','cash',998.00,'',47,'2026-10-30 19:00:55'),(162,156,'2026-10-31 01:00:56','cash',8026.00,'',47,'2026-10-30 19:00:56'),(163,157,'2026-10-31 01:00:56','bank',10450.00,'',47,'2026-10-30 19:00:56'),(164,158,'2026-10-31 01:00:56','bank',32536.00,'',47,'2026-10-30 19:00:56'),(165,159,'2026-10-31 01:00:56','cash',17390.00,'',47,'2026-10-30 19:00:56'),(166,177,'2026-10-31 01:00:57','cash',57000.00,'',47,'2026-10-30 19:00:57'),(167,178,'2026-10-31 01:00:57','cash',9500.00,'',47,'2026-10-30 19:00:57'),(168,179,'2026-10-31 01:00:57','cash',10400.00,'',47,'2026-10-30 19:00:57'),(169,180,'2026-10-31 01:00:57','cash',3900.00,'',47,'2026-10-30 19:00:57'),(170,181,'2026-10-31 01:00:58','cash',28500.00,'',47,'2026-10-30 19:00:58'),(171,182,'2026-10-31 01:00:58','cash',15600.00,'',47,'2026-10-30 19:00:58'),(172,183,'2026-10-31 01:00:58','cash',17500.00,'',47,'2026-10-30 19:00:58'),(173,184,'2026-10-31 01:00:59','cash',2300.00,'',47,'2026-10-30 19:00:59'),(174,185,'2026-10-31 01:00:59','cash',4200.00,'',47,'2026-10-30 19:00:59'),(175,186,'2026-10-31 01:00:59','cash',11800.00,'',47,'2026-10-30 19:00:59'),(176,190,'2026-10-31 11:08:34','cash',9500.00,'',10,'2026-10-31 05:08:34'),(177,168,'2026-10-29 11:44:40','cash',5000.00,'SR collection CL20261031003 / MR-1002',10,'2026-10-31 05:44:40'),(178,191,'2026-10-31 12:18:27','cash',6790.00,'',10,'2026-10-31 06:18:27'),(179,192,'2026-11-02 21:06:15','cash',42130.00,'cash',10,'2026-11-02 15:06:15'),(180,193,'2026-11-02 22:00:53','cash',32000.00,'Cash at sale',63,'2026-11-02 16:00:53'),(181,194,'2026-11-02 22:00:56','cash',28500.00,'',63,'2026-11-02 16:00:56'),(182,195,'2026-11-02 22:01:43','cash',32000.00,'Cash at sale',63,'2026-11-02 16:01:43'),(183,196,'2026-11-02 22:05:48','cash',50000.00,'',63,'2026-11-02 16:05:48'),(184,197,'2026-11-05 21:52:45','cash',11800.00,'',10,'2026-11-05 15:52:45'),(185,198,'2026-11-05 21:53:43','cash',16890.00,'erweqrw',10,'2026-11-05 15:53:43'),(186,199,'2026-11-05 21:54:53','cash',28490.00,'',10,'2026-11-05 15:54:53');
/*!40000 ALTER TABLE `sales_invoice_payments` ENABLE KEYS */;
UNLOCK TABLES;

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
  `note` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `ix_sales_schedules_invoice` (`invoice_id`),
  CONSTRAINT `fk_sales_schedule_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_schedules`
--

LOCK TABLES `sales_invoice_schedules` WRITE;
/*!40000 ALTER TABLE `sales_invoice_schedules` DISABLE KEYS */;
INSERT INTO `sales_invoice_schedules` VALUES (1,2,'2026-10-27',1900.00,'due 100','2026-10-27 15:07:04');
/*!40000 ALTER TABLE `sales_invoice_schedules` ENABLE KEYS */;
UNLOCK TABLES;

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

LOCK TABLES `sales_invoice_sequences` WRITE;
/*!40000 ALTER TABLE `sales_invoice_sequences` DISABLE KEYS */;
INSERT INTO `sales_invoice_sequences` VALUES (2026,82);
/*!40000 ALTER TABLE `sales_invoice_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_invoices`
--

DROP TABLE IF EXISTS `sales_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoices` (
  `invoice_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int unsigned DEFAULT NULL,
  `ref_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_date` date NOT NULL,
  `bill_type` enum('Cash','Credit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cash',
  `customer_id` bigint unsigned DEFAULT NULL,
  `member_id` bigint unsigned DEFAULT NULL,
  `customer_group` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attention` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `delivery_point` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `create_installation` tinyint(1) NOT NULL DEFAULT '0',
  `bin` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nid` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_person` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sr_id` bigint unsigned DEFAULT NULL,
  `narration` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `additional_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `status` enum('posted','void') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'posted',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approval_status` enum('pending','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
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
) ENGINE=InnoDB AUTO_INCREMENT=200 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoices`
--

LOCK TABLES `sales_invoices` WRITE;
/*!40000 ALTER TABLE `sales_invoices` DISABLE KEYS */;
INSERT INTO `sales_invoices` VALUES (1,'SI2026000001',NULL,'123','2026-10-27','Cash',NULL,NULL,'','Nazmul Bhuiyan','01711264147','asfasdf','feni','',0,'','','Administrator',NULL,'choudda',NULL,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,1000.00,0.00,1.000,'posted',10,'2026-10-27 14:46:21','2026-10-29 18:16:47','approved',10,'2026-10-30 00:16:47',NULL),(2,'SI2026000002',NULL,'124','2026-10-27','Cash',NULL,NULL,'Retail','Naifa Binte Nazmul','01615911873','Bay Mouse Keyboard Combo','Rayer Bazar','Mohammadpur',0,'12344645','3335455663','Administrator',NULL,'New customer bay keybaord mouse',NULL,1950.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,1950.00,2000.00,0.00,2.000,'posted',10,'2026-10-27 15:07:04','2026-10-27 15:07:04','pending',NULL,NULL,NULL),(120,'SI2026000003',1,'PO-5000','2026-08-04','Credit',91,NULL,'','Noor Fashion House','01811200209','','Narayanganj','',0,'','','Tanvir Hasan',19,'',NULL,7800.00,0.00,390.00,370.00,0.00,0.00,0.00,0.00,0.00,7780.00,7780.00,0.00,2.000,'posted',10,'2026-10-30 19:00:31','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',312),(121,'SI2026000004',1,'PO-5001','2026-08-06','Cash',83,NULL,'','Rahman Enterprise','01811200201','','Mirpur 10, Dhaka','',0,'','','Nusrat Jahan',20,'',NULL,31600.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,31600.00,31600.00,0.00,2.000,'posted',10,'2026-10-30 19:00:32','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',313),(122,'SI2026000005',1,'PO-5002','2026-08-08','Cash',85,NULL,'','Bengal IT Solutions','01811200203','','Banani, Dhaka','',0,'','','Mahmud Alam',21,'',NULL,63000.00,0.00,3150.00,0.00,0.00,0.00,0.00,0.00,0.00,59850.00,59850.00,0.00,5.000,'posted',10,'2026-10-30 19:00:32','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',314),(123,'SI2026000006',1,'PO-5003','2026-08-10','Credit',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Farhana Akter',22,'',NULL,51200.00,0.00,2560.00,2432.00,0.00,0.00,0.00,0.00,0.00,51072.00,25536.00,25536.00,4.000,'posted',10,'2026-10-30 19:00:32','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',315),(124,'SI2026000007',15,'PO-5004','2026-08-11','Cash',89,NULL,'','Amin & Sons','01811200207','','Chattogram','',0,'','','Tanvir Hasan',19,'',NULL,49700.00,0.00,2485.00,0.00,0.00,0.00,0.00,0.00,0.00,47215.00,47215.00,0.00,7.000,'posted',10,'2026-10-30 19:00:33','2026-10-30 19:05:01','approved',10,'2026-10-31 01:01:11',316),(125,'SI2026000008',1,'PO-5005','2026-08-13','Credit',91,NULL,'','Noor Fashion House','01811200209','','Narayanganj','',0,'','','Nusrat Jahan',20,'',NULL,33850.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,33850.00,33850.00,0.00,3.000,'posted',10,'2026-10-30 19:00:33','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',317),(126,'SI2026000009',1,'PO-5006','2026-08-15','Cash',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Mahmud Alam',21,'',NULL,33700.00,0.00,0.00,1685.00,0.00,0.00,0.00,0.00,0.00,35385.00,35385.00,0.00,4.000,'posted',10,'2026-10-30 19:00:33','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',318),(127,'SI2026000010',1,'PO-5007','2026-08-17','Cash',92,NULL,'','Delta Garments Ltd','01811200210','','Savar, Dhaka','',0,'','','Farhana Akter',22,'',NULL,89600.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,89600.00,89600.00,0.00,8.000,'posted',10,'2026-10-30 19:00:34','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',319),(128,'SI2026000011',1,'PO-5008','2026-08-18','Credit',87,NULL,'','City Pharma Ltd','01811200205','','Dhanmondi, Dhaka','',0,'','','Tanvir Hasan',19,'',NULL,23600.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,23600.00,11800.00,11800.00,2.000,'posted',10,'2026-10-30 19:00:34','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',320),(129,'SI2026000012',1,'PO-5009','2026-08-19','Credit',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Nusrat Jahan',20,'',NULL,93800.00,0.00,1876.00,4596.00,0.00,0.00,0.00,0.00,0.00,96520.00,96520.00,0.00,7.000,'posted',10,'2026-10-30 19:00:34','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',321),(130,'SI2026000013',1,'PO-5010','2026-08-20','Cash',85,NULL,'','Bengal IT Solutions','01811200203','','Banani, Dhaka','',0,'','','Mahmud Alam',21,'',NULL,21200.00,0.00,424.00,0.00,0.00,0.00,0.00,0.00,0.00,20776.00,20776.00,0.00,5.000,'posted',10,'2026-10-30 19:00:35','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',322),(131,'SI2026000014',1,'PO-5011','2026-08-22','Cash',83,NULL,'','Rahman Enterprise','01811200201','','Mirpur 10, Dhaka','',0,'','','Farhana Akter',22,'',NULL,33000.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,33000.00,33000.00,0.00,2.000,'posted',10,'2026-10-30 19:00:35','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',323),(132,'SI2026000015',1,'PO-5012','2026-08-23','Credit',84,NULL,'','Karim Traders','01811200202','','Uttara Sector 7, Dhaka','',0,'','','Tanvir Hasan',19,'',NULL,59500.00,0.00,1190.00,2916.00,0.00,0.00,0.00,0.00,0.00,61226.00,30613.00,30613.00,9.000,'posted',10,'2026-10-30 19:00:36','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',324),(133,'SI2026000016',1,'PO-5013','2026-08-24','Cash',92,NULL,'','Delta Garments Ltd','01811200210','','Savar, Dhaka','',0,'','','Nusrat Jahan',20,'',NULL,84900.00,0.00,4245.00,0.00,0.00,0.00,0.00,0.00,0.00,80655.00,80655.00,0.00,6.000,'posted',10,'2026-10-30 19:00:36','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',325),(134,'SI2026000017',1,'PO-5014','2026-08-26','Credit',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Mahmud Alam',21,'',NULL,11800.00,0.00,236.00,0.00,0.00,0.00,0.00,0.00,0.00,11564.00,11564.00,0.00,1.000,'posted',10,'2026-10-30 19:00:36','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',326),(135,'SI2026000018',1,'PO-5015','2026-08-27','Credit',87,NULL,'','City Pharma Ltd','01811200205','','Dhanmondi, Dhaka','',0,'','','Farhana Akter',22,'',NULL,101800.00,0.00,0.00,5090.00,0.00,0.00,0.00,0.00,0.00,106890.00,53445.00,53445.00,7.000,'posted',10,'2026-10-30 19:00:37','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',327),(136,'SI2026000019',1,'PO-5016','2026-08-29','Cash',84,NULL,'','Karim Traders','01811200202','','Uttara Sector 7, Dhaka','',0,'','','Tanvir Hasan',19,'',NULL,16500.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,16500.00,16500.00,0.00,1.000,'posted',10,'2026-10-30 19:00:37','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',328),(137,'SI2026000020',1,'PO-5017','2026-08-30','Cash',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Nusrat Jahan',20,'',NULL,90600.00,0.00,4530.00,0.00,0.00,0.00,0.00,0.00,0.00,86070.00,86070.00,0.00,9.000,'posted',10,'2026-10-30 19:00:37','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',329),(138,'SI2026000021',15,'PO-5018','2026-08-31','Cash',89,NULL,'','Amin & Sons','01811200207','','Chattogram','',0,'','','Mahmud Alam',21,'',NULL,60850.00,0.00,0.00,3042.00,0.00,0.00,0.00,0.00,0.00,63892.00,63892.00,0.00,8.000,'posted',10,'2026-10-30 19:00:38','2026-10-30 19:05:01','approved',10,'2026-10-31 01:01:11',330),(139,'SI2026000022',1,'PO-5019','2026-09-01','Cash',83,NULL,'','Rahman Enterprise','01811200201','','Mirpur 10, Dhaka','',0,'','','Farhana Akter',22,'',NULL,73300.00,0.00,1466.00,0.00,0.00,0.00,0.00,0.00,0.00,71834.00,71834.00,0.00,7.000,'posted',10,'2026-10-30 19:00:38','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',331),(140,'SI2026000023',1,'PO-5020','2026-09-03','Cash',92,NULL,'','Delta Garments Ltd','01811200210','','Savar, Dhaka','',0,'','','Tanvir Hasan',19,'',NULL,9500.00,0.00,190.00,0.00,0.00,0.00,0.00,0.00,0.00,9310.00,9310.00,0.00,1.000,'posted',10,'2026-10-30 19:00:38','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',332),(141,'SI2026000024',1,'PO-5021','2026-09-05','Credit',91,NULL,'','Noor Fashion House','01811200209','','Narayanganj','',0,'','','Nusrat Jahan',20,'',NULL,130800.00,0.00,0.00,6540.00,0.00,0.00,0.00,0.00,0.00,137340.00,137340.00,0.00,7.000,'posted',10,'2026-10-30 19:00:39','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',333),(142,'SI2026000025',1,'PO-5022','2026-09-06','Cash',85,NULL,'','Bengal IT Solutions','01811200203','','Banani, Dhaka','',0,'','','Mahmud Alam',21,'',NULL,56000.00,0.00,1120.00,0.00,0.00,0.00,0.00,0.00,0.00,54880.00,54880.00,0.00,8.000,'posted',10,'2026-10-30 19:00:39','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',334),(143,'SI2026000026',16,'PO-5023','2026-09-07','Credit',90,NULL,'','Green Valley Hospital','01811200208','','Sylhet','',0,'','','Farhana Akter',22,'',NULL,104300.00,0.00,5215.00,0.00,0.00,0.00,0.00,0.00,0.00,99085.00,49542.00,49543.00,8.000,'posted',10,'2026-10-30 19:00:39','2026-10-30 19:05:01','approved',10,'2026-10-31 01:01:11',335),(144,'SI2026000027',1,'PO-5024','2026-09-08','Cash',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Tanvir Hasan',19,'',NULL,125800.00,0.00,2516.00,6164.00,0.00,0.00,0.00,0.00,0.00,129448.00,129448.00,0.00,7.000,'posted',10,'2026-10-30 19:00:40','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',336),(145,'SI2026000028',1,'PO-5025','2026-09-09','Credit',84,NULL,'','Karim Traders','01811200202','','Uttara Sector 7, Dhaka','',0,'','','Nusrat Jahan',20,'',NULL,10400.00,0.00,208.00,0.00,0.00,0.00,0.00,0.00,0.00,10192.00,10192.00,0.00,2.000,'posted',10,'2026-10-30 19:00:40','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',337),(146,'SI2026000029',15,'PO-5026','2026-09-10','Cash',89,NULL,'','Amin & Sons','01811200207','','Chattogram','',0,'','','Mahmud Alam',21,'',NULL,26200.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,26200.00,26200.00,0.00,3.000,'posted',10,'2026-10-30 19:00:41','2026-10-30 19:05:01','approved',10,'2026-10-31 01:01:11',338),(147,'SI2026000030',15,'PO-5027','2026-09-13','Cash',89,NULL,'','Amin & Sons','01811200207','','Chattogram','',0,'','','Farhana Akter',22,'',NULL,28400.00,0.00,0.00,1420.00,0.00,0.00,0.00,0.00,0.00,29820.00,29820.00,0.00,4.000,'posted',10,'2026-10-30 19:00:41','2026-10-30 19:05:01','approved',10,'2026-10-31 01:01:11',339),(148,'SI2026000031',1,'PO-5028','2026-09-14','Cash',87,NULL,'','City Pharma Ltd','01811200205','','Dhanmondi, Dhaka','',0,'','','Tanvir Hasan',19,'',NULL,49400.00,0.00,2470.00,0.00,0.00,0.00,0.00,0.00,0.00,46930.00,46930.00,0.00,5.000,'posted',10,'2026-10-30 19:00:41','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',340),(149,'SI2026000032',1,'PO-5029','2026-09-15','Cash',83,NULL,'','Rahman Enterprise','01811200201','','Mirpur 10, Dhaka','',0,'','','Nusrat Jahan',20,'',NULL,69800.00,0.00,1396.00,0.00,0.00,0.00,0.00,0.00,0.00,68404.00,68404.00,0.00,5.000,'posted',10,'2026-10-30 19:00:42','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',341),(150,'SI2026000033',1,'PO-5030','2026-09-16','Cash',85,NULL,'','Bengal IT Solutions','01811200203','','Banani, Dhaka','',0,'','','Mahmud Alam',21,'',NULL,16500.00,0.00,330.00,808.00,0.00,0.00,0.00,0.00,0.00,16978.00,16978.00,0.00,1.000,'posted',10,'2026-10-30 19:00:42','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',342),(151,'SI2026000034',1,'PO-5031','2026-09-19','Cash',87,NULL,'','City Pharma Ltd','01811200205','','Dhanmondi, Dhaka','',0,'','','Farhana Akter',22,'',NULL,10400.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,10400.00,10400.00,0.00,2.000,'posted',10,'2026-10-30 19:00:42','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',343),(152,'SI2026000035',1,'PO-5032','2026-09-20','Credit',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Tanvir Hasan',19,'',NULL,72300.00,0.00,1446.00,0.00,0.00,0.00,0.00,0.00,0.00,70854.00,35427.00,35427.00,7.000,'posted',10,'2026-10-30 19:00:43','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',344),(153,'SI2026000036',1,'PO-5033','2026-09-22','Credit',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Nusrat Jahan',20,'',NULL,84500.00,0.00,4225.00,4014.00,0.00,0.00,0.00,0.00,0.00,84289.00,84289.00,0.00,5.000,'posted',10,'2026-10-30 19:00:43','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',345),(154,'SI2026000037',1,'PO-5034','2026-09-23','Credit',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Mahmud Alam',21,'',NULL,2100.00,0.00,105.00,0.00,0.00,0.00,0.00,0.00,0.00,1995.00,998.00,997.00,1.000,'posted',10,'2026-10-30 19:00:43','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',346),(155,'SI2026000038',1,'PO-5035','2026-09-24','Cash',87,NULL,'','City Pharma Ltd','01811200205','','Dhanmondi, Dhaka','',0,'','','Farhana Akter',22,'',NULL,53500.00,0.00,2675.00,0.00,0.00,0.00,0.00,0.00,0.00,50825.00,50825.00,0.00,10.000,'posted',10,'2026-10-30 19:00:44','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',347),(156,'SI2026000039',1,'PO-5036','2026-09-26','Credit',85,NULL,'','Bengal IT Solutions','01811200203','','Banani, Dhaka','',0,'','','Tanvir Hasan',19,'',NULL,7800.00,0.00,156.00,382.00,0.00,0.00,0.00,0.00,0.00,8026.00,8026.00,0.00,2.000,'posted',10,'2026-10-30 19:00:44','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',348),(157,'SI2026000040',1,'PO-5037','2026-09-27','Credit',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Nusrat Jahan',20,'',NULL,22000.00,0.00,1100.00,0.00,0.00,0.00,0.00,0.00,0.00,20900.00,10450.00,10450.00,4.000,'posted',10,'2026-10-30 19:00:45','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',349),(158,'SI2026000041',1,'PO-5038','2026-09-28','Credit',85,NULL,'','Bengal IT Solutions','01811200203','','Banani, Dhaka','',0,'','','Mahmud Alam',21,'',NULL,33200.00,0.00,664.00,0.00,0.00,0.00,0.00,0.00,0.00,32536.00,32536.00,0.00,4.000,'posted',10,'2026-10-30 19:00:45','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',350),(159,'SI2026000042',16,'PO-5039','2026-09-30','Credit',90,NULL,'','Green Valley Hospital','01811200208','','Sylhet','',0,'','','Farhana Akter',22,'',NULL,33800.00,0.00,676.00,1656.00,0.00,0.00,0.00,0.00,0.00,34780.00,17390.00,17390.00,5.000,'posted',10,'2026-10-30 19:00:45','2026-10-30 19:05:01','approved',10,'2026-10-31 01:01:11',351),(160,'SI2026000043',1,'PO-5040','2026-10-03','Credit',91,NULL,'','Noor Fashion House','01811200209','','Narayanganj','',0,'','','Tanvir Hasan',19,'',NULL,38400.00,0.00,1920.00,0.00,0.00,0.00,0.00,0.00,0.00,36480.00,0.00,36480.00,3.000,'posted',10,'2026-10-30 19:00:46','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',352),(161,'SI2026000044',15,'PO-5041','2026-10-04','Cash',89,NULL,'','Amin & Sons','01811200207','','Chattogram','',0,'','','Nusrat Jahan',20,'',NULL,15600.00,0.00,780.00,0.00,0.00,0.00,0.00,0.00,0.00,14820.00,14820.00,0.00,3.000,'posted',10,'2026-10-30 19:00:46','2026-10-30 19:05:01','approved',10,'2026-10-31 01:01:11',353),(162,'SI2026000045',16,'PO-5042','2026-10-06','Credit',90,NULL,'','Green Valley Hospital','01811200208','','Sylhet','',0,'','','Mahmud Alam',21,'',NULL,70750.00,0.00,0.00,3538.00,0.00,0.00,0.00,0.00,0.00,74288.00,0.00,74288.00,10.000,'posted',10,'2026-10-30 19:00:46','2026-10-30 19:05:01','approved',10,'2026-10-31 01:01:11',354),(163,'SI2026000046',1,'PO-5043','2026-10-08','Cash',85,NULL,'','Bengal IT Solutions','01811200203','','Banani, Dhaka','',0,'','','Farhana Akter',22,'',NULL,38400.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,38400.00,38400.00,0.00,3.000,'posted',10,'2026-10-30 19:00:47','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',355),(164,'SI2026000047',1,'PO-5044','2026-10-10','Credit',92,NULL,'','Delta Garments Ltd','01811200210','','Savar, Dhaka','',0,'','','Tanvir Hasan',19,'',NULL,57100.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,57100.00,0.00,57100.00,4.000,'posted',10,'2026-10-30 19:00:47','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',356),(165,'SI2026000048',1,'PO-5045','2026-10-12','Cash',84,NULL,'','Karim Traders','01811200202','','Uttara Sector 7, Dhaka','',0,'','','Nusrat Jahan',20,'',NULL,62400.00,0.00,1248.00,3058.00,0.00,0.00,0.00,0.00,0.00,64210.00,64210.00,0.00,9.000,'posted',10,'2026-10-30 19:00:47','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',357),(166,'SI2026000049',1,'PO-5046','2026-10-13','Credit',86,NULL,'','Sonar Bangla School','01811200204','','Gazipur Sadar','',0,'','','Mahmud Alam',21,'',NULL,73800.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,73800.00,0.00,73800.00,9.000,'posted',10,'2026-10-30 19:00:48','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',358),(167,'SI2026000050',1,'PO-5047','2026-10-14','Credit',85,NULL,'','Bengal IT Solutions','01811200203','','Banani, Dhaka','',0,'','','Farhana Akter',22,'',NULL,47700.00,0.00,954.00,0.00,0.00,0.00,0.00,0.00,0.00,46746.00,0.00,46746.00,4.000,'posted',10,'2026-10-30 19:00:48','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',359),(168,'SI2026000051',1,'PO-5048','2026-10-17','Credit',83,NULL,'','Rahman Enterprise','01811200201','','Mirpur 10, Dhaka','',0,'','','Tanvir Hasan',19,'',NULL,10400.00,0.00,520.00,494.00,0.00,0.00,0.00,0.00,0.00,10374.00,5000.00,5374.00,2.000,'posted',10,'2026-10-30 19:00:48','2026-10-31 05:44:40','approved',10,'2026-10-31 01:01:11',360),(169,'SI2026000052',1,'PO-5049','2026-10-19','Credit',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Nusrat Jahan',20,'',NULL,66400.00,0.00,3320.00,0.00,0.00,0.00,0.00,0.00,0.00,63080.00,0.00,63080.00,7.000,'posted',10,'2026-10-30 19:00:49','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',361),(170,'SI2026000053',1,'PO-5050','2026-10-21','Cash',87,NULL,'','City Pharma Ltd','01811200205','','Dhanmondi, Dhaka','',0,'','','Mahmud Alam',21,'',NULL,35000.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,35000.00,35000.00,0.00,2.000,'posted',10,'2026-10-30 19:00:49','2026-10-30 19:04:00','pending',NULL,NULL,362),(171,'SI2026000054',1,'PO-5051','2026-10-22','Cash',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Farhana Akter',22,'',NULL,14250.00,0.00,712.00,677.00,0.00,0.00,0.00,0.00,0.00,14215.00,14215.00,0.00,6.000,'posted',10,'2026-10-30 19:00:50','2026-10-30 19:04:00','pending',NULL,NULL,363),(172,'SI2026000055',1,'PO-5052','2026-10-24','Credit',92,NULL,'','Delta Garments Ltd','01811200210','','Savar, Dhaka','',0,'','','Tanvir Hasan',19,'',NULL,11800.00,0.00,236.00,0.00,0.00,0.00,0.00,0.00,0.00,11564.00,0.00,11564.00,1.000,'posted',10,'2026-10-30 19:00:50','2026-10-30 19:04:00','pending',NULL,NULL,364),(173,'SI2026000056',1,'PO-5053','2026-10-25','Cash',92,NULL,'','Delta Garments Ltd','01811200210','','Savar, Dhaka','',0,'','','Nusrat Jahan',20,'',NULL,47400.00,0.00,2370.00,0.00,0.00,0.00,0.00,0.00,0.00,45030.00,45030.00,0.00,3.000,'posted',10,'2026-10-30 19:00:50','2026-10-30 19:04:00','pending',NULL,NULL,365),(174,'SI2026000057',1,'PO-5054','2026-10-26','Credit',88,NULL,'','Hasan Computer Point','01811200206','','Comilla Town','',0,'','','Mahmud Alam',21,'',NULL,25300.00,0.00,1265.00,1202.00,0.00,0.00,0.00,0.00,0.00,25237.00,0.00,25237.00,5.000,'posted',10,'2026-10-30 19:00:51','2026-10-30 19:04:00','pending',NULL,NULL,366),(175,'SI2026000058',1,'PO-5055','2026-10-27','Cash',92,NULL,'','Delta Garments Ltd','01811200210','','Savar, Dhaka','',0,'','','Farhana Akter',22,'',NULL,12800.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,12800.00,12800.00,0.00,4.000,'posted',10,'2026-10-30 19:00:51','2026-10-30 19:04:00','pending',NULL,NULL,367),(176,'SI2026000059',1,'PO-5056','2026-10-29','Cash',91,NULL,'','Noor Fashion House','01811200209','','Narayanganj','',0,'','','Tanvir Hasan',19,'',NULL,88300.00,0.00,1766.00,0.00,0.00,0.00,0.00,0.00,0.00,86534.00,86534.00,0.00,9.000,'posted',10,'2026-10-30 19:00:51','2026-10-30 19:04:00','pending',NULL,NULL,368),(177,'SI2026000060',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','dummy_admin',NULL,'POS sale. Tendered 57,100.00, change 100.00.',NULL,57000.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,57000.00,57000.00,0.00,2.000,'posted',10,'2026-10-30 19:00:57','2026-11-05 15:59:36','approved',10,'2026-11-05 21:59:36',369),(178,'SI2026000061',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','dummy_admin',NULL,'POS sale. Tendered 9,600.00, change 100.00.',NULL,9500.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,9500.00,9500.00,0.00,1.000,'posted',10,'2026-10-30 19:00:57','2026-10-30 19:04:00','pending',NULL,NULL,370),(179,'SI2026000062',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','dummy_admin',NULL,'POS sale. Tendered 10,400.00, change 0.00.',NULL,10400.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,10400.00,10400.00,0.00,2.000,'posted',10,'2026-10-30 19:00:57','2026-10-30 19:04:00','pending',NULL,NULL,371),(180,'SI2026000063',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','dummy_admin',NULL,'POS sale. Tendered 3,900.00, change 0.00.',NULL,3900.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,3900.00,3900.00,0.00,1.000,'posted',10,'2026-10-30 19:00:57','2026-10-30 19:04:00','pending',NULL,NULL,372),(181,'SI2026000064',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','dummy_admin',NULL,'POS sale. Tendered 29,000.00, change 500.00.',NULL,28500.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,28500.00,28500.00,0.00,1.000,'posted',10,'2026-10-30 19:00:58','2026-10-30 19:04:00','pending',NULL,NULL,373),(182,'SI2026000065',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','dummy_admin',NULL,'POS sale. Tendered 16,100.00, change 500.00.',NULL,15600.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,15600.00,15600.00,0.00,2.000,'posted',10,'2026-10-30 19:00:58','2026-10-30 19:04:00','pending',NULL,NULL,374),(183,'SI2026000066',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','dummy_admin',NULL,'POS sale. Tendered 17,600.00, change 100.00.',NULL,17500.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,17500.00,17500.00,0.00,1.000,'posted',10,'2026-10-30 19:00:58','2026-10-30 19:04:00','pending',NULL,NULL,375),(184,'SI2026000067',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','dummy_admin',NULL,'POS sale. Tendered 2,800.00, change 500.00.',NULL,2300.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,2300.00,2300.00,0.00,2.000,'posted',10,'2026-10-30 19:00:59','2026-10-30 19:04:00','pending',NULL,NULL,376),(185,'SI2026000068',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','dummy_admin',NULL,'POS sale. Tendered 4,700.00, change 500.00.',NULL,4200.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,4200.00,4200.00,0.00,2.000,'posted',10,'2026-10-30 19:00:59','2026-10-30 19:04:00','pending',NULL,NULL,377),(186,'SI2026000069',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','dummy_admin',NULL,'POS sale. Tendered 11,900.00, change 100.00.',NULL,11800.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,11800.00,11800.00,0.00,1.000,'posted',10,'2026-10-30 19:00:59','2026-10-31 05:15:06','approved',10,'2026-10-31 11:15:06',378),(187,'SI2026000070',1,'SO2026000001','2026-10-22','Credit',85,NULL,'','Bengal IT Solutions','01811200203','','Banani, Dhaka','',0,'','','Tanvir Hasan',NULL,'Bill from sales order SO2026000001',NULL,60100.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,60100.00,0.00,60100.00,3.000,'posted',10,'2026-10-30 19:01:02','2026-10-30 19:04:00','pending',NULL,NULL,379),(188,'SI2026000071',1,'SO2026000002','2026-10-22','Credit',83,NULL,'','Rahman Enterprise','01811200201','','Mirpur 10, Dhaka','',0,'','','Nusrat Jahan',NULL,'Bill from sales order SO2026000002',NULL,84000.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,84000.00,0.00,84000.00,6.000,'posted',10,'2026-10-30 19:01:03','2026-10-30 19:04:00','pending',NULL,NULL,380),(189,'SI2026000072',1,'SO2026000003','2026-10-22','Credit',87,NULL,'','City Pharma Ltd','01811200205','','Dhanmondi, Dhaka','',0,'','','Mahmud Alam',NULL,'Bill from sales order SO2026000003',NULL,16400.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,16400.00,0.00,16400.00,4.000,'posted',10,'2026-10-30 19:01:03','2026-10-30 19:04:00','pending',NULL,NULL,381),(190,'SI2026000073',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','01121323232','',NULL,'',0,'','','Administrator',NULL,'POS sale. Tendered 9,500.00, change 0.00.',NULL,9500.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,9500.00,9500.00,0.00,1.000,'posted',10,'2026-10-31 05:08:34','2026-10-31 05:08:34','pending',NULL,NULL,406),(191,'SI2026000074',1,'POS','2026-10-31','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','Administrator',NULL,'POS sale. Tendered 6,790.00, change 0.00.',NULL,6790.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,6790.00,6790.00,0.00,1.000,'posted',10,'2026-10-31 06:18:27','2026-10-31 06:18:27','pending',NULL,NULL,407),(192,'SI2026000075',1,'POS','2026-11-02','Cash',NULL,NULL,'','Walk-in Customer','','',NULL,'',0,'','','Administrator',NULL,'POS sale. Tendered 42,130.00, change 0.00.',NULL,38400.00,0.00,100.00,3830.00,0.00,0.00,0.00,0.00,0.00,42130.00,42130.00,0.00,3.000,'posted',10,'2026-11-02 15:06:15','2026-11-02 15:06:15','pending',NULL,NULL,408),(193,'SI2026000076',1,'SMP-INV-1','2026-11-02','Cash',94,NULL,'','SAMPLE Retail Customer','01611000401','','Mirpur 10, Dhaka','',0,'','','SAMPLE Rahim Uddin',23,'SAMPLE sales invoice',NULL,32000.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,32000.00,32000.00,0.00,3.000,'posted',63,'2026-11-02 16:00:53','2026-11-05 15:59:33','approved',10,'2026-11-05 21:59:33',413),(194,'SI2026000077',1,'POS','2026-11-02','Cash',NULL,NULL,'','SAMPLE Walk-in','01511000501','','','',0,'','','sample_admin',NULL,'POS sale. Tendered 30,000.00, change 1,500.00.',NULL,28500.00,0.00,0.00,1357.14,0.00,0.00,0.00,0.00,0.00,28500.00,28500.00,0.00,2.000,'posted',63,'2026-11-02 16:00:56','2026-11-02 16:00:56','pending',NULL,NULL,414),(195,'SI2026000078',1,'SMP-INV-1','2026-11-02','Cash',94,NULL,'','SAMPLE Retail Customer','01611000401','','Mirpur 10, Dhaka','',0,'','','SAMPLE Rahim Uddin',23,'SAMPLE sales invoice',NULL,32000.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,32000.00,32000.00,0.00,3.000,'posted',63,'2026-11-02 16:01:43','2026-11-02 16:01:43','pending',NULL,NULL,416),(196,'SI2026000079',1,'SMP-SOB-1','2026-11-02','Credit',93,NULL,'','SAMPLE Corporate Buyer Ltd','01911000301','Nasrin','Gulshan 1, Dhaka','',0,'','','SAMPLE Rahim Uddin',NULL,'SAMPLE bill from order SO2026000009',NULL,83700.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,83700.00,50000.00,33700.00,6.000,'posted',63,'2026-11-02 16:05:48','2026-11-02 16:05:48','pending',NULL,NULL,417),(197,'SI2026000080',1,'POS','2026-11-05','Cash',NULL,NULL,'','Walk-in Customer','','','','',0,'','','Administrator',NULL,'POS sale. Tendered 11,800.00, change 0.00.',NULL,11800.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,11800.00,11800.00,0.00,1.000,'posted',10,'2026-11-05 15:52:45','2026-11-05 15:52:45','pending',NULL,NULL,419),(198,'SI2026000081',1,'POS','2026-11-05','Cash',NULL,NULL,'','Walk-in Customer','017123123213','','','',0,'','','Administrator',NULL,'POS sale. Tendered 16,890.00, change 0.00.',NULL,16900.00,0.00,10.00,331.18,0.00,0.00,0.00,0.00,0.00,16890.00,16890.00,0.00,1.000,'posted',10,'2026-11-05 15:53:43','2026-11-05 15:53:43','pending',NULL,NULL,420),(199,'SI2026000082',1,'POS','2026-11-05','Cash',NULL,NULL,'','Walk-in Customer','','','','',0,'','','Administrator',NULL,'POS sale. Tendered 28,490.00, change 0.00.',NULL,28490.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,28490.00,28490.00,0.00,1.000,'posted',10,'2026-11-05 15:54:53','2026-11-05 15:54:53','pending',NULL,NULL,421);
/*!40000 ALTER TABLE `sales_invoices` ENABLE KEYS */;
UNLOCK TABLES;

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
  `kind` enum('earn','redeem','adjust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` decimal(12,2) NOT NULL,
  `note` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`entry_id`),
  KEY `ix_sales_member_points_member` (`member_id`),
  KEY `ix_sales_member_points_invoice` (`invoice_id`),
  CONSTRAINT `fk_sales_member_point_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_member_point_member` FOREIGN KEY (`member_id`) REFERENCES `sales_members` (`member_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_member_points`
--

LOCK TABLES `sales_member_points` WRITE;
/*!40000 ALTER TABLE `sales_member_points` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_member_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_members`
--

DROP TABLE IF EXISTS `sales_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_members` (
  `member_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `member_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `opening_points` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `uq_sales_members_no` (`member_no`),
  KEY `ix_sales_members_mobile` (`mobile`),
  KEY `fk_sales_member_customer` (`customer_id`),
  CONSTRAINT `fk_sales_member_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_members`
--

LOCK TABLES `sales_members` WRITE;
/*!40000 ALTER TABLE `sales_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_members` ENABLE KEYS */;
UNLOCK TABLES;

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
  `action` enum('added','changed','removed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `sales_order_adjustment_lines` WRITE;
/*!40000 ALTER TABLE `sales_order_adjustment_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_order_adjustment_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_order_adjustments`
--

DROP TABLE IF EXISTS `sales_order_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_adjustments` (
  `adjustment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `adjustment_date` date NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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

LOCK TABLES `sales_order_adjustments` WRITE;
/*!40000 ALTER TABLE `sales_order_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_order_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_bill_items`
--

LOCK TABLES `sales_order_bill_items` WRITE;
/*!40000 ALTER TABLE `sales_order_bill_items` DISABLE KEYS */;
INSERT INTO `sales_order_bill_items` VALUES (6,6,59,237,2.000),(7,6,60,238,1.000),(8,7,61,239,3.000),(9,7,62,240,3.000),(10,8,63,241,1.000),(11,8,64,242,3.000),(12,9,75,252,3.000),(13,9,76,253,3.000);
/*!40000 ALTER TABLE `sales_order_bill_items` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_bills`
--

LOCK TABLES `sales_order_bills` WRITE;
/*!40000 ALTER TABLE `sales_order_bills` DISABLE KEYS */;
INSERT INTO `sales_order_bills` VALUES (6,59,187,0.00,10,'2026-10-30 19:01:02'),(7,60,188,0.00,10,'2026-10-30 19:01:03'),(8,61,189,0.00,10,'2026-10-30 19:01:03'),(9,67,196,0.00,63,'2026-11-02 16:05:48');
/*!40000 ALTER TABLE `sales_order_bills` ENABLE KEYS */;
UNLOCK TABLES;

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
  `product_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_caption` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `warranty` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_items`
--

LOCK TABLES `sales_order_items` WRITE;
/*!40000 ALTER TABLE `sales_order_items` DISABLE KEYS */;
INSERT INTO `sales_order_items` VALUES (59,59,83,'PRN-4002','Laser Printer','Canon','LBP6030','Compact laser printer','Main Store','Pcs','1 Year',2.000,15800.00,0.00,31600.00,1,'2026-10-30 19:01:00'),(60,59,68,'NET-1002','Router','Cisco','RV340','Business VPN router','Main Store','Pcs','1 Year',1.000,28500.00,0.00,28500.00,2,'2026-10-30 19:01:00'),(61,60,70,'NET-1004','Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Main Store','Pcs','1 Year',3.000,16500.00,0.00,49500.00,1,'2026-10-30 19:01:00'),(62,60,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Main Store','Pcs','1 Year',3.000,11500.00,0.00,34500.00,2,'2026-10-30 19:01:00'),(63,61,77,'ACC-2005','SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Main Store','Pcs','1 Year',1.000,6800.00,0.00,6800.00,1,'2026-10-30 19:01:00'),(64,61,67,'NET-1001','Router','TP-Link','Archer C6','Dual band gigabit router','Main Store','Pcs','1 Year',3.000,3200.00,0.00,9600.00,2,'2026-10-30 19:01:00'),(65,62,69,'NET-1003','Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Main Store','Pcs','1 Year',3.000,2100.00,0.00,6300.00,1,'2026-10-30 19:01:01'),(66,62,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Main Store','Pcs','1 Year',2.000,11800.00,0.00,23600.00,2,'2026-10-30 19:01:01'),(67,63,72,'NET-1006','LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Main Store','Pcs','1 Year',1.000,9500.00,0.00,9500.00,1,'2026-10-30 19:01:01'),(68,63,74,'ACC-2002','Mouse','Logitech','M185','Wireless mouse','Main Store','Pcs','1 Year',3.000,1150.00,0.00,3450.00,2,'2026-10-30 19:01:01'),(69,64,73,'ACC-2001','Keyboard','Logitech','K120','USB keyboard','Main Store','Pcs','1 Year',1.000,850.00,0.00,850.00,1,'2026-10-30 19:01:01'),(70,64,78,'ACC-2006','SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Main Store','Pcs','1 Year',3.000,11500.00,0.00,34500.00,2,'2026-10-30 19:01:01'),(71,65,80,'PWR-3002','UPS','APC','BX1100','1100VA UPS','Main Store','Pcs','1 Year',2.000,11800.00,0.00,23600.00,1,'2026-10-30 19:01:02'),(72,65,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Main Store','Pcs','1 Year',3.000,3900.00,0.00,11700.00,2,'2026-10-30 19:01:02'),(73,66,76,'ACC-2004','Monitor','Samsung','LS24C','24 inch IPS monitor','Main Store','Pcs','1 Year',3.000,16900.00,0.00,50700.00,1,'2026-10-30 19:01:02'),(74,66,81,'PWR-3003','Battery','APC','RBC17','Replacement UPS battery','Main Store','Pcs','1 Year',3.000,3900.00,0.00,11700.00,2,'2026-10-30 19:01:02'),(75,67,86,'SMP-001','SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','Main Store','SAMPLE Box','SAMPLE 1 Year',3.000,24500.00,0.00,73500.00,1,'2026-11-02 16:00:59'),(76,67,87,'SMP-002','SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','Main Store','SAMPLE Box','SAMPLE 1 Year',5.000,3400.00,0.00,17000.00,2,'2026-11-02 16:00:59');
/*!40000 ALTER TABLE `sales_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_order_schedules`
--

DROP TABLE IF EXISTS `sales_order_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_schedules` (
  `schedule_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `payment_day` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `ix_sales_order_schedules_order` (`order_id`),
  CONSTRAINT `fk_sales_order_schedule_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_order_schedules`
--

LOCK TABLES `sales_order_schedules` WRITE;
/*!40000 ALTER TABLE `sales_order_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_order_schedules` ENABLE KEYS */;
UNLOCK TABLES;

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

LOCK TABLES `sales_order_sequences` WRITE;
/*!40000 ALTER TABLE `sales_order_sequences` DISABLE KEYS */;
INSERT INTO `sales_order_sequences` VALUES (2026,9);
/*!40000 ALTER TABLE `sales_order_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_orders`
--

DROP TABLE IF EXISTS `sales_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_orders` (
  `order_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int unsigned DEFAULT NULL,
  `ref_no` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `order_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `bill_type` enum('Cash','Credit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Credit',
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_group` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attention` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `narration` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `additional_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bin` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nid` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sales_person` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sr_id` bigint unsigned DEFAULT NULL,
  `delivery_point` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `create_installation` tinyint(1) NOT NULL DEFAULT '0',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `extra_charges` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `vat_ait_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `advance_received` decimal(15,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `status` enum('open','converted','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approval_status` enum('pending','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
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
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_orders`
--

LOCK TABLES `sales_orders` WRITE;
/*!40000 ALTER TABLE `sales_orders` DISABLE KEYS */;
INSERT INTO `sales_orders` VALUES (59,'SO2026000001',1,'','2026-10-10','2026-11-05','Credit',85,'','Bengal IT Solutions','01811200203','','',NULL,'Banani, Dhaka','','','Tanvir Hasan',19,'',0,60100.00,0.00,0.00,0.00,60100.00,0.00,60100.00,3.000,'converted',10,'2026-10-30 19:01:00','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',NULL),(60,'SO2026000002',1,'','2026-10-11','2026-11-06','Credit',83,'','Rahman Enterprise','01811200201','','',NULL,'Mirpur 10, Dhaka','','','Nusrat Jahan',20,'',0,84000.00,0.00,0.00,0.00,84000.00,0.00,84000.00,6.000,'converted',10,'2026-10-30 19:01:00','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',NULL),(61,'SO2026000003',1,'','2026-10-12','2026-11-07','Credit',87,'','City Pharma Ltd','01811200205','','',NULL,'Dhanmondi, Dhaka','','','Mahmud Alam',21,'',0,16400.00,0.00,0.00,0.00,16400.00,0.00,16400.00,4.000,'converted',10,'2026-10-30 19:01:00','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',NULL),(62,'SO2026000004',1,'','2026-10-13','2026-11-08','Credit',86,'','Sonar Bangla School','01811200204','','',NULL,'Gazipur Sadar','','','Farhana Akter',22,'',0,29900.00,0.00,0.00,0.00,29900.00,0.00,29900.00,5.000,'open',10,'2026-10-30 19:01:01','2026-10-30 19:04:00','approved',10,'2026-10-31 01:01:11',NULL),(63,'SO2026000005',1,'','2026-10-14','2026-11-09','Credit',86,'','Sonar Bangla School','01811200204','','',NULL,'Gazipur Sadar','','','Tanvir Hasan',19,'',0,12950.00,0.00,0.00,0.00,12950.00,0.00,12950.00,4.000,'open',10,'2026-10-30 19:01:01','2026-10-30 19:04:00','pending',NULL,NULL,NULL),(64,'SO2026000006',1,'','2026-10-15','2026-11-10','Credit',90,'','Green Valley Hospital','01811200208','','',NULL,'Sylhet','','','Nusrat Jahan',20,'',0,35350.00,0.00,0.00,0.00,35350.00,0.00,35350.00,4.000,'open',10,'2026-10-30 19:01:01','2026-10-30 19:04:00','pending',NULL,NULL,NULL),(65,'SO2026000007',1,'','2026-10-16','2026-11-11','Credit',89,'','Amin & Sons','01811200207','','',NULL,'Chattogram','','','Mahmud Alam',21,'',0,35300.00,0.00,0.00,0.00,35300.00,0.00,35300.00,5.000,'open',10,'2026-10-30 19:01:02','2026-10-30 19:04:00','pending',NULL,NULL,NULL),(66,'SO2026000008',1,'','2026-10-17','2026-11-12','Credit',86,'','Sonar Bangla School','01811200204','','',NULL,'Gazipur Sadar','','','Farhana Akter',22,'',0,62400.00,0.00,0.00,0.00,62400.00,0.00,62400.00,6.000,'open',10,'2026-10-30 19:01:02','2026-10-30 19:04:00','pending',NULL,NULL,NULL),(67,'SO2026000009',1,'SMP-SO-1','2026-11-02','2026-11-15','Credit',93,'','SAMPLE Corporate Buyer Ltd','01911000301','Nasrin','SAMPLE sales order',NULL,'Gulshan 1, Dhaka','','','SAMPLE Rahim Uddin',23,'',0,90500.00,0.00,0.00,0.00,90500.00,0.00,90500.00,8.000,'open',63,'2026-11-02 16:00:59','2026-11-02 16:00:59','pending',NULL,NULL,NULL);
/*!40000 ALTER TABLE `sales_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_products`
--

DROP TABLE IF EXISTS `sales_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_products` (
  `product_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `product_group` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brand` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `model_caption` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `warranty` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_products`
--

LOCK TABLES `sales_products` WRITE;
/*!40000 ALTER TABLE `sales_products` DISABLE KEYS */;
INSERT INTO `sales_products` VALUES (67,'NET-1001',3,'Router','TP-Link','Archer C6','Dual band gigabit router','Pcs','1 Year',3200.00,2500.00,5.000,5.00,0,1,'2026-10-30 18:59:14','2026-10-30 18:59:14'),(68,'NET-1002',3,'Router','Cisco','RV340','Business VPN router','Pcs','1 Year',28500.00,23000.00,5.000,5.00,0,1,'2026-10-30 18:59:14','2026-10-30 18:59:14'),(69,'NET-1003',3,'Network Switch','TP-Link','TL-SG1008D','8 port gigabit switch','Pcs','1 Year',2100.00,1600.00,5.000,5.00,0,1,'2026-10-30 18:59:14','2026-10-30 18:59:14'),(70,'NET-1004',3,'Network Switch','Cisco','CBS110-24','24 port unmanaged switch','Pcs','1 Year',16500.00,13200.00,5.000,5.00,0,1,'2026-10-30 18:59:14','2026-10-30 18:59:14'),(71,'NET-1005',3,'Access Point','TP-Link','EAP225','Ceiling access point','Pcs','1 Year',7800.00,6200.00,5.000,5.00,0,1,'2026-10-30 18:59:15','2026-10-30 18:59:15'),(72,'NET-1006',3,'LAN Cable','TP-Link','CAT6-305','CAT6 cable box 305m','Pcs','1 Year',9500.00,7600.00,5.000,5.00,0,1,'2026-10-30 18:59:15','2026-10-30 18:59:15'),(73,'ACC-2001',4,'Keyboard','Logitech','K120','USB keyboard','Pcs','1 Year',850.00,600.00,5.000,5.00,0,1,'2026-10-30 18:59:15','2026-10-30 18:59:15'),(74,'ACC-2002',4,'Mouse','Logitech','M185','Wireless mouse','Pcs','1 Year',1150.00,820.00,5.000,5.00,0,1,'2026-10-30 18:59:15','2026-10-30 18:59:15'),(75,'ACC-2003',4,'Monitor','Dell','E2223HN','22 inch LED monitor','Pcs','1 Year',12800.00,10400.00,5.000,5.00,0,1,'2026-10-30 18:59:15','2026-10-30 18:59:15'),(76,'ACC-2004',4,'Monitor','Samsung','LS24C','24 inch IPS monitor','Pcs','1 Year',16900.00,13900.00,5.000,5.00,0,1,'2026-10-30 18:59:15','2026-10-30 18:59:15'),(77,'ACC-2005',4,'SSD Drive','Samsung','870 EVO 500GB','500GB SATA SSD','Pcs','1 Year',6800.00,5400.00,5.000,5.00,0,1,'2026-10-30 18:59:16','2026-10-30 18:59:16'),(78,'ACC-2006',4,'SSD Drive','Dell','NVMe 1TB','1TB NVMe SSD','Pcs','1 Year',11500.00,9100.00,5.000,5.00,0,1,'2026-10-30 18:59:16','2026-10-30 18:59:16'),(79,'PWR-3001',5,'UPS','APC','BX650','650VA line interactive UPS','Pcs','1 Year',6400.00,5100.00,5.000,5.00,0,1,'2026-10-30 18:59:16','2026-10-30 18:59:16'),(80,'PWR-3002',5,'UPS','APC','BX1100','1100VA UPS','Pcs','1 Year',11800.00,9500.00,5.000,5.00,0,1,'2026-10-30 18:59:16','2026-10-30 18:59:16'),(81,'PWR-3003',5,'Battery','APC','RBC17','Replacement UPS battery','Pcs','1 Year',3900.00,3000.00,5.000,5.00,0,1,'2026-10-30 18:59:16','2026-10-30 18:59:16'),(82,'PRN-4001',6,'Laser Printer','HP','LaserJet M111w','Mono laser printer','Pcs','1 Year',17500.00,14300.00,5.000,5.00,0,1,'2026-10-30 18:59:16','2026-10-30 18:59:16'),(83,'PRN-4002',6,'Laser Printer','Canon','LBP6030','Compact laser printer','Pcs','1 Year',15800.00,12900.00,5.000,5.00,0,1,'2026-10-30 18:59:17','2026-10-30 18:59:17'),(84,'PRN-4003',6,'Toner','HP','CF150A','Black toner','Pcs','1 Year',5200.00,3900.00,5.000,5.00,0,1,'2026-10-30 18:59:17','2026-10-30 18:59:17'),(85,'PRN-4004',6,'Toner','Canon','CRG 325','Canon black toner','Pcs','1 Year',4600.00,3500.00,5.000,5.00,0,1,'2026-10-30 18:59:17','2026-10-30 18:59:17'),(86,'SMP-001',7,'SAMPLE Smartphone','APC','SAMPLE X10','SAMPLE X10 Smartphone 8/128GB','SAMPLE Box','SAMPLE 1 Year',25000.00,21000.00,5.000,5.00,0,1,'2026-11-02 15:59:13','2026-11-02 15:59:13'),(87,'SMP-002',7,'SAMPLE Smartphone','APC','SAMPLE Buds','SAMPLE Buds Wireless Earphone','SAMPLE Box','SAMPLE 1 Year',3500.00,2600.00,5.000,5.00,0,1,'2026-11-02 15:59:16','2026-11-02 15:59:16');
/*!40000 ALTER TABLE `sales_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_representatives`
--

DROP TABLE IF EXISTS `sales_representatives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_representatives` (
  `sr_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sr_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sr_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `designation` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `territory_id` int unsigned DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `commission_basis` enum('none','net_sales','collection','gross_profit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'net_sales',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Percent of the basis',
  `target_bonus_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Extra percent of net sales when the month target is reached',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `branch_id` int unsigned DEFAULT NULL COMMENT 'Owning store branch (NULL = Head Office)',
  `staff_only` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1: office staff with a portal login, not a sales person',
  PRIMARY KEY (`sr_id`),
  UNIQUE KEY `uq_sales_representatives_code` (`sr_code`),
  KEY `ix_sales_representatives_name` (`sr_name`),
  KEY `ix_sales_representatives_territory` (`territory_id`),
  KEY `ix_sales_representatives_branch` (`branch_id`),
  CONSTRAINT `fk_sr_territory` FOREIGN KEY (`territory_id`) REFERENCES `sr_territories` (`territory_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_representatives`
--

LOCK TABLES `sales_representatives` WRITE;
/*!40000 ALTER TABLE `sales_representatives` DISABLE KEYS */;
INSERT INTO `sales_representatives` VALUES (10,'102','Sadman Bhuian','01815911873','sadman@test.com','Dhanamoni dhaka','Sr Sales',NULL,'2026-10-30','net_sales',1.00,2.00,1,'2026-10-30 09:54:08',NULL,0),(19,'SR-201','Tanvir Hasan','01911300301','tanvir@example.com','Dhaka','Sales Executive',6,'2026-01-15','net_sales',2.00,1.00,1,'2026-10-30 18:59:22',NULL,0),(20,'SR-202','Nusrat Jahan','01911300302','nusrat@example.com','Dhaka','Sales Executive',7,'2026-01-15','net_sales',2.00,1.00,1,'2026-10-30 18:59:22',NULL,0),(21,'SR-203','Mahmud Alam','01911300303','mahmud@example.com','Dhaka','Sales Executive',8,'2026-01-15','net_sales',2.00,1.00,1,'2026-10-30 18:59:22',NULL,0),(22,'SR-204','Farhana Akter','01911300304','farhana@example.com','Dhaka','Sales Executive',9,'2026-01-15','net_sales',2.00,1.00,1,'2026-10-30 18:59:22',NULL,0),(23,'SMP-SR1','SAMPLE Rahim Uddin','01711000101','sample.sr@example.com','Banani, Dhaka','Sales Executive',10,'2026-11-01','none',2.00,1.00,1,'2026-11-02 15:59:21',NULL,0);
/*!40000 ALTER TABLE `sales_representatives` ENABLE KEYS */;
UNLOCK TABLES;

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
  `product_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `serial_imei` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `item_condition` enum('good','damaged') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'good',
  `quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `free_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_value` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `line_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`return_item_id`),
  KEY `ix_sales_return_items_return` (`return_id`),
  KEY `ix_sales_return_items_invoice_item` (`invoice_item_id`),
  CONSTRAINT `fk_sales_return_item_invoice_item` FOREIGN KEY (`invoice_item_id`) REFERENCES `sales_invoice_items` (`item_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_return_item_return` FOREIGN KEY (`return_id`) REFERENCES `sales_returns` (`return_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_return_items`
--

LOCK TABLES `sales_return_items` WRITE;
/*!40000 ALTER TABLE `sales_return_items` DISABLE KEYS */;
INSERT INTO `sales_return_items` VALUES (19,19,194,71,'NET-1005','Ceiling access point','Main Store','Pcs','','good',1.000,0.000,7800.0000,7800.00),(20,20,138,84,'PRN-4003','Black toner','Main Store','Pcs','','good',1.000,0.000,5200.0000,5200.00),(21,21,166,80,'PWR-3002','1100VA UPS','Main Store','Pcs','','good',1.000,0.000,11800.0000,11800.00),(22,22,183,82,'PRN-4001','Mono laser printer','Main Store','Pcs','','damaged',1.000,0.000,17500.0000,17500.00),(23,23,121,75,'ACC-2003','22 inch LED monitor','Main Store','Pcs','','good',1.000,0.000,12800.0000,12800.00),(24,24,251,87,'SMP-002','SAMPLE Buds Wireless Earphone','Main Store','SAMPLE Box','','good',1.000,0.000,3500.0000,3500.00);
/*!40000 ALTER TABLE `sales_return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_returns`
--

DROP TABLE IF EXISTS `sales_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_returns` (
  `return_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `return_no` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `return_date` date NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mobile` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `items_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `deduction_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `return_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `due_adjusted` decimal(15,2) NOT NULL DEFAULT '0.00',
  `refund_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `refund_method` enum('cash','bank','mobile','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `refund_reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `total_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `status` enum('posted','void') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'posted',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approval_status` enum('pending','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_returns`
--

LOCK TABLES `sales_returns` WRITE;
/*!40000 ALTER TABLE `sales_returns` DISABLE KEYS */;
INSERT INTO `sales_returns` VALUES (19,'SR2026000001',159,'2026-10-25',90,'Green Valley Hospital','01811200208','',7800.00,0.00,7800.00,0.00,7800.00,'cash','',1.000,'posted',10,'2026-10-30 19:01:03','pending',NULL,NULL,382),(20,'SR2026000002',132,'2026-10-25',84,'Karim Traders','01811200202','',5200.00,0.00,5200.00,0.00,5200.00,'cash','',1.000,'posted',10,'2026-10-30 19:01:03','pending',NULL,NULL,383),(21,'SR2026000003',144,'2026-10-25',88,'Hasan Computer Point','01811200206','',11800.00,0.00,11800.00,0.00,11800.00,'cash','',1.000,'posted',10,'2026-10-30 19:01:04','pending',NULL,NULL,384),(22,'SR2026000004',153,'2026-10-25',88,'Hasan Computer Point','01811200206','',17500.00,0.00,17500.00,0.00,17500.00,'cash','',1.000,'posted',10,'2026-10-30 19:01:04','pending',NULL,NULL,385),(23,'SR2026000005',123,'2026-10-25',88,'Hasan Computer Point','01811200206','',12800.00,0.00,12800.00,0.00,12800.00,'cash','',1.000,'posted',10,'2026-10-30 19:01:04','pending',NULL,NULL,386),(24,'SR2026000006',195,'2026-11-02',94,'SAMPLE Retail Customer','01611000401','SAMPLE return: customer changed mind',3500.00,0.00,3500.00,0.00,3500.00,'cash','',1.000,'posted',63,'2026-11-02 16:05:51','pending',NULL,NULL,418);
/*!40000 ALTER TABLE `sales_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_stores`
--

DROP TABLE IF EXISTS `sales_stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_stores` (
  `store_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`store_id`),
  UNIQUE KEY `uq_sales_stores_name` (`store_name`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_stores`
--

LOCK TABLES `sales_stores` WRITE;
/*!40000 ALTER TABLE `sales_stores` DISABLE KEYS */;
INSERT INTO `sales_stores` VALUES (1,'RMA STORE',1,'2026-10-28 16:06:35'),(2,'Main Store',1,'2026-10-29 17:35:11'),(89,'site office',1,'2026-10-30 10:28:34');
/*!40000 ALTER TABLE `sales_stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_throttle`
--

DROP TABLE IF EXISTS `security_throttle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `security_throttle` (
  `bucket` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hits` int NOT NULL DEFAULT '0',
  `window_end` datetime NOT NULL,
  PRIMARY KEY (`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_throttle`
--

LOCK TABLES `security_throttle` WRITE;
/*!40000 ALTER TABLE `security_throttle` DISABLE KEYS */;
INSERT INTO `security_throttle` VALUES ('01bdbdb57b80a88acda0d10966e322edaf734e56d81ccc7301db359800cd613f',1,'2026-11-02 21:14:34'),('4d44b5db05eb0a83e865ffc74518af8f109b8e202994407617dccbd1b71656bf',1,'2026-11-02 21:14:34'),('a720891d672a1bb8da06d565ab984847f9a8b92c137c769f6b698fe345b798ad',1,'2026-11-02 21:59:34');
/*!40000 ALTER TABLE `security_throttle` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_collection_allocations`
--

LOCK TABLES `sr_collection_allocations` WRITE;
/*!40000 ALTER TABLE `sr_collection_allocations` DISABLE KEYS */;
INSERT INTO `sr_collection_allocations` VALUES (5,7,168,177,5000.00);
/*!40000 ALTER TABLE `sr_collection_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sr_collections`
--

DROP TABLE IF EXISTS `sr_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_collections` (
  `collection_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `collection_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sr_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `collected_on` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank','mobile','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Cheque, transaction or receipt number',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('pending','verified','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `staff_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`collection_id`),
  UNIQUE KEY `uq_sr_collections_no` (`collection_no`),
  KEY `ix_sr_collections_sr` (`sr_id`,`collected_on`),
  KEY `ix_sr_collections_status` (`status`,`created_at`),
  KEY `fk_sr_collection_customer` (`customer_id`),
  CONSTRAINT `fk_sr_collection_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customers` (`customer_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_collection_sr` FOREIGN KEY (`sr_id`) REFERENCES `sales_representatives` (`sr_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_sr_collection_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_collections`
--

LOCK TABLES `sr_collections` WRITE;
/*!40000 ALTER TABLE `sr_collections` DISABLE KEYS */;
INSERT INTO `sr_collections` VALUES (5,'CL20261031001',19,7,87,'2026-10-29',5000.00,'cash','MR-1000','Collected at customer office','pending',0.00,NULL,NULL,'','2026-10-30 19:03:36'),(6,'CL20261031002',19,7,91,'2026-10-29',5000.00,'cash','MR-1001','Collected at customer office','pending',0.00,NULL,NULL,'','2026-10-30 19:03:36'),(7,'CL20261031003',19,7,83,'2026-10-29',5000.00,'cash','MR-1002','Collected at customer office','verified',5000.00,10,'2026-10-31 11:44:40','','2026-10-30 19:03:36');
/*!40000 ALTER TABLE `sr_collections` ENABLE KEYS */;
UNLOCK TABLES;

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
  `payment_method` enum('cash','bank','mobile','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `note` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `ix_sr_commission_payments` (`sr_id`,`period_month`),
  CONSTRAINT `fk_sr_commission_payment_sr` FOREIGN KEY (`sr_id`) REFERENCES `sales_representatives` (`sr_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_sr_commission_amount` CHECK ((`amount` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_commission_payments`
--

LOCK TABLES `sr_commission_payments` WRITE;
/*!40000 ALTER TABLE `sr_commission_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `sr_commission_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sr_portal_accounts`
--

DROP TABLE IF EXISTS `sr_portal_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_portal_accounts` (
  `account_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sr_id` bigint unsigned NOT NULL,
  `login` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','disabled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '1',
  `can_book_orders` tinyint(1) NOT NULL DEFAULT '1',
  `can_collect` tinyint(1) NOT NULL DEFAULT '1',
  `see_all_customers` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Supervisor / office staff: every customer, not only the SR''s own',
  `failed_attempts` smallint unsigned NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uq_sr_portal_accounts_sr` (`sr_id`),
  UNIQUE KEY `uq_sr_portal_accounts_login` (`login`),
  CONSTRAINT `fk_sr_portal_account_sr` FOREIGN KEY (`sr_id`) REFERENCES `sales_representatives` (`sr_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_portal_accounts`
--

LOCK TABLES `sr_portal_accounts` WRITE;
/*!40000 ALTER TABLE `sr_portal_accounts` DISABLE KEYS */;
INSERT INTO `sr_portal_accounts` VALUES (7,19,'demo_sr','$2y$12$kGsIvqtwrp83b77AAeHx1.Nfs06AriUJO2QdK3mHOLqb5fxuTJtSW','active',0,1,1,0,0,NULL,'2026-10-31 01:03:59','127.0.0.1',10,'2026-10-30 19:03:01','2026-10-30 19:04:00'),(8,20,'demo_sr2','$2y$12$3/xF21xCUu1NP9ImOd4/LuNo7apTrNXHdtY9eCmhX1lgtVg99LdYO','active',0,1,1,0,0,NULL,'2026-10-31 01:03:59','127.0.0.1',10,'2026-10-30 19:03:02','2026-10-30 19:04:00'),(9,21,'EMP00004','$2y$12$kM/qPYZ6xJJ4k6laOb.LG.vhhvryd/Cr5hPv3x8XKKXkdrIL8um5i','active',0,1,1,0,0,NULL,'2026-10-31 13:31:16','192.168.2.213',10,'2026-10-31 07:03:52','2026-10-31 07:31:16');
/*!40000 ALTER TABLE `sr_portal_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sr_portal_login_log`
--

DROP TABLE IF EXISTS `sr_portal_login_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_portal_login_log` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned DEFAULT NULL,
  `login` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `reason` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `ix_sr_portal_login_log_account` (`account_id`,`created_at`),
  KEY `ix_sr_portal_login_log_ip` (`ip_address`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_portal_login_log`
--

LOCK TABLES `sr_portal_login_log` WRITE;
/*!40000 ALTER TABLE `sr_portal_login_log` DISABLE KEYS */;
INSERT INTO `sr_portal_login_log` VALUES (21,3,'01815911873','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36',1,'ok','2026-10-30 09:55:34'),(22,NULL,'nazmulitbd','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36',0,'unknown_login','2026-10-31 06:40:25'),(23,NULL,'nazmulb','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36',0,'unknown_login','2026-10-31 06:40:34'),(24,NULL,'nazmulitbd','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15',0,'unknown_login','2026-10-31 06:54:40'),(25,NULL,'nazmulitbd','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15',0,'unknown_login','2026-10-31 06:54:56'),(26,9,'EMP00004','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15',0,'bad_password','2026-10-31 07:04:05'),(27,9,'EMP00004','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15',1,'ok','2026-10-31 07:04:31'),(28,9,'EMP00004','192.168.2.213','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15',1,'ok','2026-10-31 07:31:16');
/*!40000 ALTER TABLE `sr_portal_login_log` ENABLE KEYS */;
UNLOCK TABLES;

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
  `note` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`target_id`),
  UNIQUE KEY `uq_sr_targets` (`sr_id`,`target_month`),
  CONSTRAINT `fk_sr_target_sr` FOREIGN KEY (`sr_id`) REFERENCES `sales_representatives` (`sr_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_sr_target_month` CHECK ((dayofmonth(`target_month`) = 1))
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_targets`
--

LOCK TABLES `sr_targets` WRITE;
/*!40000 ALTER TABLE `sr_targets` DISABLE KEYS */;
INSERT INTO `sr_targets` VALUES (5,10,'2026-10-01',500000.00,200000.00,'October target',10,'2026-10-30 19:04:00'),(7,19,'2026-10-01',300000.00,200000.00,'October target',10,'2026-10-30 19:04:00'),(8,20,'2026-10-01',400000.00,200000.00,'October target',10,'2026-10-30 19:04:00'),(9,21,'2026-10-01',400000.00,200000.00,'October target',10,'2026-10-30 19:04:00'),(10,22,'2026-10-01',300000.00,250000.00,'October target',10,'2026-10-30 19:04:00');
/*!40000 ALTER TABLE `sr_targets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sr_territories`
--

DROP TABLE IF EXISTS `sr_territories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sr_territories` (
  `territory_id` int unsigned NOT NULL AUTO_INCREMENT,
  `territory_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `territory_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `parent_id` int unsigned DEFAULT NULL COMMENT 'Wider territory this one is part of',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`territory_id`),
  UNIQUE KEY `uq_sr_territories_name` (`territory_name`),
  KEY `fk_sr_territory_parent` (`parent_id`),
  CONSTRAINT `fk_sr_territory_parent` FOREIGN KEY (`parent_id`) REFERENCES `sr_territories` (`territory_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sr_territories`
--

LOCK TABLES `sr_territories` WRITE;
/*!40000 ALTER TABLE `sr_territories` DISABLE KEYS */;
INSERT INTO `sr_territories` VALUES (5,'101','Bhoundhora CIty','Dhaka',NULL,'Mobile Slaes',1,'2026-10-30 09:54:40'),(6,'T-DHK-N','Dhaka North','Dhaka',NULL,'',1,'2026-10-30 18:59:21'),(7,'T-DHK-S','Dhaka South','Dhaka',NULL,'',1,'2026-10-30 18:59:21'),(8,'T-CTG','Chattogram Zone','Chattogram',NULL,'',1,'2026-10-30 18:59:21'),(9,'T-SYL','Sylhet Zone','Sylhet',NULL,'',1,'2026-10-30 18:59:21'),(10,'SMP-DHK','SAMPLE Dhaka North','Dhaka',NULL,'Sample territory',1,'2026-11-02 15:59:19');
/*!40000 ALTER TABLE `sr_territories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `store_accounts`
--

DROP TABLE IF EXISTS `store_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_accounts` (
  `account_id` int unsigned NOT NULL AUTO_INCREMENT,
  `reseller_level` tinyint unsigned NOT NULL,
  `reseller_id` int NOT NULL,
  `store_id` int unsigned NOT NULL,
  `role` enum('head_office','back_office','front_office') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '1',
  `rights` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Comma list of right keys, see STORE_RIGHTS in store_common.php',
  `failed_attempts` smallint unsigned NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uq_store_accounts_reseller` (`reseller_level`,`reseller_id`),
  KEY `ix_store_accounts_store` (`store_id`),
  CONSTRAINT `fk_store_account_store` FOREIGN KEY (`store_id`) REFERENCES `store_profiles` (`store_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `store_accounts`
--

LOCK TABLES `store_accounts` WRITE;
/*!40000 ALTER TABLE `store_accounts` DISABLE KEYS */;
INSERT INTO `store_accounts` VALUES (9,3,3,9,'head_office','Nazmul H Bhuiyan','$2y$12$5YNBHUwJ9Q0h/X9Lnx6LHOUKJOtw35KKI1p5pSKqsG58JiQvPJ8Ju',0,'',0,NULL,'2026-10-30 22:09:43','192.168.2.213','2026-10-30 10:26:19'),(10,2,1255,10,'back_office','rohim','$2y$12$OhnyowJ6jZPzS4ZFy9XhPuXse6EfJdoPgL1fceqJmozW.oYe/FWY6',1,'customers_view,customers_edit,staff_view,staff_edit,users,sales,purchase,stock,reports',3,NULL,NULL,'','2026-10-30 10:28:35'),(11,1,2952,10,'front_office','Test Sotore','$2y$12$mWjH9IbbuGjomf1d8Y9LNuFnEXRsTA94I/Ii38WvB5JLgxIeFtDY2',0,'customers_view,customers_edit,staff_view,sales',0,NULL,'2026-10-30 16:30:13','192.168.2.213','2026-10-30 10:29:46');
/*!40000 ALTER TABLE `store_accounts` ENABLE KEYS */;
UNLOCK TABLES;

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
  `store_type` enum('head_office','warehouse','retail_shop') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int unsigned NOT NULL,
  `stock_store` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sales_stores.store_name this store sells from',
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`store_id`),
  UNIQUE KEY `uq_store_profiles_reseller` (`reseller_level`,`reseller_id`),
  UNIQUE KEY `uq_store_profiles_code` (`store_code`),
  UNIQUE KEY `uq_store_profiles_branch` (`branch_id`),
  CONSTRAINT `fk_store_profile_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `store_profiles`
--

LOCK TABLES `store_profiles` WRITE;
/*!40000 ALTER TABLE `store_profiles` DISABLE KEYS */;
INSERT INTO `store_profiles` VALUES (9,3,3,'head_office','HO','Head Office',1,'Main Store','01712264147','Dhanmondi',1,'2026-10-30 10:26:18'),(10,2,1255,'retail_shop','GU-01','ABC Tele',11,'site office','01712264456','MIrour',1,'2026-10-30 10:28:34');
/*!40000 ALTER TABLE `store_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trader_attachments`
--

DROP TABLE IF EXISTS `trader_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trader_attachments` (
  `attachment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trader_id` bigint unsigned NOT NULL,
  `original_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/octet-stream',
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

LOCK TABLES `trader_attachments` WRITE;
/*!40000 ALTER TABLE `trader_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `trader_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trader_categories`
--

DROP TABLE IF EXISTS `trader_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trader_categories` (
  `category_id` tinyint unsigned NOT NULL,
  `category_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` enum('customer','supplier') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_prefix` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_trader_categories_name` (`category_name`),
  UNIQUE KEY `uq_trader_categories_prefix` (`code_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trader_categories`
--

LOCK TABLES `trader_categories` WRITE;
/*!40000 ALTER TABLE `trader_categories` DISABLE KEYS */;
INSERT INTO `trader_categories` VALUES (1,'Supplier','supplier','SUP',1),(2,'Dealer','customer','DLR',2),(3,'Credit Customer','customer','CRC',3),(4,'Cash Customer','customer','CSC',4),(5,'Cash Supplier','supplier','CSS',5),(6,'Retailer','customer','RTL',6),(7,'Importer','supplier','IMP',7),(8,'C&F','supplier','CNF',8),(9,'Indenter','supplier','IND',9);
/*!40000 ALTER TABLE `trader_categories` ENABLE KEYS */;
UNLOCK TABLES;

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
  `old_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `new_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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

LOCK TABLES `trader_conversions` WRITE;
/*!40000 ALTER TABLE `trader_conversions` DISABLE KEYS */;
/*!40000 ALTER TABLE `trader_conversions` ENABLE KEYS */;
UNLOCK TABLES;

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
  `price_level` enum('retail','wholesale','dealer','special') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'retail',
  `special_discount_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `vat_exempt` tinyint(1) NOT NULL DEFAULT '0',
  `block_sales` tinyint(1) NOT NULL DEFAULT '0',
  `block_purchase` tinyint(1) NOT NULL DEFAULT '0',
  `flag_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`trader_id`),
  CONSTRAINT `fk_trader_flags_trader` FOREIGN KEY (`trader_id`) REFERENCES `traders` (`trader_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trader_flags`
--

LOCK TABLES `trader_flags` WRITE;
/*!40000 ALTER TABLE `trader_flags` DISABLE KEYS */;
INSERT INTO `trader_flags` VALUES (5,1,100.00,1,'retail',2.00,0,0,0,'Good Cusstomer',10,'2026-10-30 09:49:19'),(6,0,0.00,0,'retail',0.00,1,0,0,'',10,'2026-10-30 09:49:19'),(30,1,50000.00,30,'retail',0.00,0,0,0,'',63,'2026-11-02 15:59:29');
/*!40000 ALTER TABLE `trader_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trader_groups`
--

DROP TABLE IF EXISTS `trader_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trader_groups` (
  `group_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` tinyint unsigned NOT NULL,
  `group_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `uq_trader_groups_name` (`category_id`,`group_name`),
  CONSTRAINT `fk_trader_group_category` FOREIGN KEY (`category_id`) REFERENCES `trader_categories` (`category_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trader_groups`
--

LOCK TABLES `trader_groups` WRITE;
/*!40000 ALTER TABLE `trader_groups` DISABLE KEYS */;
INSERT INTO `trader_groups` VALUES (1,1,'HNDM',1,10,'2026-10-29 15:53:27','2026-10-29 15:53:27'),(4,3,'test',1,10,'2026-10-30 06:24:18','2026-10-30 06:24:18'),(5,3,'Corporate',1,10,'2026-10-30 18:59:17','2026-10-30 19:04:00'),(6,3,'Retail',1,10,'2026-10-30 18:59:18','2026-10-30 19:04:00'),(7,1,'Local Supplier',1,10,'2026-10-30 18:59:18','2026-10-30 19:04:00'),(8,1,'Importer',1,10,'2026-10-30 18:59:18','2026-10-30 19:04:00');
/*!40000 ALTER TABLE `trader_groups` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trader_sr_assignments`
--

LOCK TABLES `trader_sr_assignments` WRITE;
/*!40000 ALTER TABLE `trader_sr_assignments` DISABLE KEYS */;
INSERT INTO `trader_sr_assignments` VALUES (9,18,19,'2026-08-01',NULL,NULL,'2026-10-30 19:03:02'),(10,19,20,'2026-08-01',NULL,NULL,'2026-10-30 19:03:02'),(11,20,21,'2026-08-01',NULL,NULL,'2026-10-30 19:03:02'),(12,21,22,'2026-08-01',NULL,NULL,'2026-10-30 19:03:02'),(13,22,19,'2026-08-01',NULL,NULL,'2026-10-30 19:03:02'),(14,23,20,'2026-08-01','2026-11-05',NULL,'2026-10-30 19:03:02'),(15,24,21,'2026-08-01',NULL,NULL,'2026-10-30 19:03:02'),(16,25,22,'2026-08-01',NULL,NULL,'2026-10-30 19:03:02'),(17,26,19,'2026-08-01',NULL,NULL,'2026-10-30 19:03:02'),(18,27,20,'2026-08-01',NULL,NULL,'2026-10-30 19:03:03'),(19,30,23,'2026-11-02',NULL,63,'2026-11-02 15:59:29'),(20,23,22,'2026-11-05',NULL,10,'2026-11-05 16:12:21');
/*!40000 ALTER TABLE `trader_sr_assignments` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `traders`
--

LOCK TABLES `traders` WRITE;
/*!40000 ALTER TABLE `traders` DISABLE KEYS */;
INSERT INTO `traders` VALUES (5,'CRC000005',3,4,'nazmul','','dgsfdf','asfasdf','0178653523','3424234','24234','','nazmulitbd@gmail.com','','sfkdslf','asfasf',0.00,'','','24234','35353',1,51,NULL,10,'2026-10-30 06:24:18','2026-10-30 06:24:18'),(6,'CRC000006',3,4,'Nazmul Bhuiyan','','Mishu','dhanmondi','01711264140','01711264140','','','nazmulitbd@gmail.com','','Sadman','Naifa',0.00,'','','32423423423','23423432432',1,55,NULL,10,'2026-10-30 06:50:37','2026-10-30 06:50:37'),(13,'SUP000013',1,8,'Dhaka Network House','','','Motijheel, Dhaka','01711100101','','','','dhaka@example.com','','Manager','',0.00,'','','','',1,NULL,5,10,'2026-10-30 18:59:18','2026-10-30 19:04:00'),(14,'SUP000014',1,7,'IDB Tech Traders','','','IDB Bhaban, Agargaon','01711100102','','','','idb@example.com','','Manager','',0.00,'','','','',1,NULL,6,10,'2026-10-30 18:59:18','2026-10-30 19:04:00'),(15,'SUP000015',1,8,'Global Power Imports','','','Tejgaon, Dhaka','01711100103','','','','global@example.com','','Manager','',0.00,'','','','',1,NULL,7,10,'2026-10-30 18:59:18','2026-10-30 19:04:00'),(16,'SUP000016',1,7,'Star Computer Distribution','','','Elephant Road, Dhaka','01711100104','','','','star@example.com','','Manager','',0.00,'','','','',1,NULL,8,10,'2026-10-30 18:59:18','2026-10-30 19:04:00'),(17,'SUP000017',1,7,'PrintLine Bangladesh','','','Nawabpur, Dhaka','01711100105','','','','printline@example.com','','Manager','',0.00,'','','','',1,NULL,9,10,'2026-10-30 18:59:19','2026-10-30 19:04:00'),(18,'CRC000018',3,5,'Rahman Enterprise','','','Mirpur 10, Dhaka','01811200201','','','','rahman@example.com','','Accounts','',0.00,'','','','',1,83,NULL,10,'2026-10-30 18:59:19','2026-10-30 19:04:00'),(19,'CRC000019',3,6,'Karim Traders','','','Uttara Sector 7, Dhaka','01811200202','','','','karim@example.com','','Accounts','',0.00,'','','','',1,84,NULL,10,'2026-10-30 18:59:19','2026-10-30 19:04:00'),(20,'CRC000020',3,5,'Bengal IT Solutions','','','Banani, Dhaka','01811200203','','','','bengal@example.com','','Accounts','',0.00,'','','','',1,85,NULL,10,'2026-10-30 18:59:19','2026-10-30 19:04:00'),(21,'CRC000021',3,5,'Sonar Bangla School','','','Gazipur Sadar','01811200204','','','','sonar@example.com','','Accounts','',0.00,'','','','',1,86,NULL,10,'2026-10-30 18:59:19','2026-10-30 19:04:00'),(22,'CRC000022',3,5,'City Pharma Ltd','','','Dhanmondi, Dhaka','01811200205','','','','city@example.com','','Accounts','',0.00,'','','','',1,87,NULL,10,'2026-10-30 18:59:20','2026-10-30 19:04:00'),(23,'CRC000023',3,6,'Hasan Computer Point','','','Comilla Town','01811200206','','','','hasan@example.com','','Accounts','',0.00,'','','','',1,88,NULL,10,'2026-10-30 18:59:20','2026-10-30 19:04:00'),(24,'CRC000024',3,6,'Amin & Sons','','','Chattogram','01811200207','','','','amin@example.com','','Accounts','',0.00,'','','','',1,89,NULL,10,'2026-10-30 18:59:20','2026-10-30 19:04:00'),(25,'CRC000025',3,5,'Green Valley Hospital','','','Sylhet','01811200208','','','','green@example.com','','Accounts','',0.00,'','','','',1,90,NULL,10,'2026-10-30 18:59:20','2026-10-30 19:04:00'),(26,'CRC000026',3,6,'Noor Fashion House','','','Narayanganj','01811200209','','','','noor@example.com','','Accounts','',0.00,'','','','',1,91,NULL,10,'2026-10-30 18:59:20','2026-10-30 19:04:00'),(27,'CRC000027',3,5,'Delta Garments Ltd','','','Savar, Dhaka','01811200210','','','','delta@example.com','','Accounts','',0.00,'','','','',1,92,NULL,10,'2026-10-30 18:59:20','2026-10-30 19:04:00'),(28,'SUP000028',1,NULL,'SAMPLE Tech Distributors Ltd','','','Motijheel, Dhaka','01811000201','','','','sample.supplier@example.com','','Karim','Abdul Karim',0.00,'','','000111222-0101','',1,NULL,10,63,'2026-11-02 15:59:24','2026-11-02 15:59:24'),(29,'CRC000029',3,NULL,'SAMPLE Corporate Buyer Ltd','','','Gulshan 1, Dhaka','01911000301','','','','sample.buyer@example.com','','Nasrin','',0.00,'','','','',1,93,NULL,63,'2026-11-02 15:59:27','2026-11-02 15:59:27'),(30,'DLR000030',2,NULL,'SAMPLE Retail Customer','','','Mirpur 10, Dhaka','01611000401','','','','sample.retail@example.com','','','',0.00,'','','','',1,94,NULL,63,'2026-11-02 15:59:29','2026-11-02 15:59:29');
/*!40000 ALTER TABLE `traders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction_categories`
--

DROP TABLE IF EXISTS `transaction_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_categories` (
  `tc_id` int unsigned NOT NULL AUTO_INCREMENT,
  `module` enum('sales','purchase','inventory','accounts','rma') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tc_id`),
  UNIQUE KEY `uq_transaction_categories` (`module`,`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_categories`
--

LOCK TABLES `transaction_categories` WRITE;
/*!40000 ALTER TABLE `transaction_categories` DISABLE KEYS */;
INSERT INTO `transaction_categories` VALUES (1,'sales','Cash Sales','',1,1,'2026-10-30 05:57:51'),(2,'sales','Credit Sales','',0,1,'2026-10-30 05:57:51'),(3,'sales','POS Sales','',0,1,'2026-10-30 05:57:51'),(4,'sales','Corporate Sales','',0,1,'2026-10-30 05:57:51'),(5,'purchase','Credit Purchase','',1,1,'2026-10-30 05:57:51'),(6,'purchase','Cash Purchase','',0,1,'2026-10-30 05:57:51'),(7,'purchase','Import Purchase','',0,1,'2026-10-30 05:57:51'),(8,'inventory','Stock Transfer','',1,1,'2026-10-30 05:57:51'),(9,'inventory','Stock Amendment','',0,1,'2026-10-30 05:57:51'),(10,'rma','Service','',1,1,'2026-10-30 05:57:51');
/*!40000 ALTER TABLE `transaction_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'billing_star'
--

--
-- Dumping routines for database 'billing_star'
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
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
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `sales_mobile_history` AS select 'invoice' AS `doc_kind`,`v`.`invoice_id` AS `doc_id`,`v`.`invoice_no` AS `doc_no`,`v`.`sales_date` AS `doc_date`,`v`.`ref_no` AS `ref_no`,`v`.`customer_id` AS `customer_id`,`v`.`customer_name` AS `party_name`,`v`.`customer_group` AS `party_group`,`v`.`mobile` AS `mobile`,right(regexp_replace(`v`.`mobile`,'[^0-9]',''),10) AS `mobile_key`,`v`.`net_amount` AS `amount`,`v`.`cash_received` AS `paid`,`v`.`balance_amount` AS `due`,`v`.`total_quantity` AS `total_quantity`,`v`.`status` AS `status`,`v`.`bill_type` AS `bill_type` from `sales_invoices` `v` union all select 'return' AS `return`,`r`.`return_id` AS `return_id`,`r`.`return_no` AS `return_no`,`r`.`return_date` AS `return_date`,`v`.`invoice_no` AS `invoice_no`,`r`.`customer_id` AS `customer_id`,`r`.`customer_name` AS `customer_name`,`v`.`customer_group` AS `customer_group`,`r`.`mobile` AS `mobile`,right(regexp_replace(`r`.`mobile`,'[^0-9]',''),10) AS `RIGHT(REGEXP_REPLACE(r.mobile, '[^0-9]', ''), 10)`,`r`.`return_amount` AS `return_amount`,`r`.`refund_amount` AS `refund_amount`,0.00 AS `0.00`,`r`.`total_quantity` AS `total_quantity`,`r`.`status` AS `status`,'' AS `Name_exp_32` from (`sales_returns` `r` join `sales_invoices` `v` on((`v`.`invoice_id` = `r`.`invoice_id`))) union all select 'order' AS `order`,`o`.`order_id` AS `order_id`,`o`.`order_no` AS `order_no`,`o`.`order_date` AS `order_date`,`o`.`ref_no` AS `ref_no`,`o`.`customer_id` AS `customer_id`,`o`.`customer_name` AS `customer_name`,`o`.`customer_group` AS `customer_group`,`o`.`mobile` AS `mobile`,right(regexp_replace(`o`.`mobile`,'[^0-9]',''),10) AS `RIGHT(REGEXP_REPLACE(o.mobile, '[^0-9]', ''), 10)`,`o`.`net_amount` AS `net_amount`,`o`.`advance_received` AS `advance_received`,`o`.`balance_amount` AS `balance_amount`,`o`.`total_quantity` AS `total_quantity`,`o`.`status` AS `status`,`o`.`bill_type` AS `bill_type` from `sales_orders` `o` union all select `d`.`doc_type` AS `doc_type`,`d`.`doc_id` AS `doc_id`,`d`.`doc_no` AS `doc_no`,`d`.`doc_date` AS `doc_date`,`d`.`ref_no` AS `ref_no`,`d`.`party_id` AS `party_id`,`d`.`party_name` AS `party_name`,`d`.`party_group` AS `party_group`,`d`.`mobile` AS `mobile`,right(regexp_replace(`d`.`mobile`,'[^0-9]',''),10) AS `RIGHT(REGEXP_REPLACE(d.mobile, '[^0-9]', ''), 10)`,`d`.`net_amount` AS `net_amount`,`d`.`cash_received` AS `cash_received`,0.00 AS `0.00`,`d`.`total_quantity` AS `total_quantity`,`d`.`status` AS `status`,`d`.`bill_type` AS `bill_type` from `inv_docs` `d` where (`d`.`doc_type` in ('quotation','challan','challan_return')) */;
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

-- Dump completed on 2026-11-05 22:22:05
