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
