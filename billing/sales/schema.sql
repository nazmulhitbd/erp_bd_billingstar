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
