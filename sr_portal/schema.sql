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
