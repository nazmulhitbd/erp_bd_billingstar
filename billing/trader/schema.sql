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
