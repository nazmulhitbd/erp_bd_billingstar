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
