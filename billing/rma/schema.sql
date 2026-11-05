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
