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
