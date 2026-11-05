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
