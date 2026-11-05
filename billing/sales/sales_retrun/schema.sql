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
