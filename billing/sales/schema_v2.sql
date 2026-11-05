-- Sales module v2: Sales Invoice/Bill and Sales Order screens, stock-tracked sales, member points,
-- product exchange, attachments and additional info. MySQL 8.0+. Safe to run again.
-- Run after vsc/sales/schema.sql, sales_order/schema.sql, sal_bill_from_sal_order/schema.sql,
-- sales_order_adjustment/schema.sql, sales_retrun/schema.sql and vsc/inventory/schema.sql.
-- Then run vsc/report/schema.sql again so the report views pick up the invoice branch.

SET @db := DATABASE();

-- Each ALTER runs only when its column is missing.
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='branch_id') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN branch_id INT UNSIGNED NULL AFTER invoice_no', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='member_id') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN member_id BIGINT UNSIGNED NULL AFTER customer_id', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='point_adjust') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN point_adjust DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT ''Taka value of redeemed points'' AFTER exchange_amount, ADD COLUMN points_redeemed DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER point_adjust, ADD COLUMN points_earned DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER points_redeemed', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='additional_info') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN additional_info TEXT NULL AFTER narration', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='stock_doc_id') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN stock_doc_id BIGINT UNSIGNED NULL COMMENT ''inv_docs row (doc_type sales_invoice) holding the stock out'', ADD KEY ix_sales_invoices_stock (stock_doc_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoice_items' AND column_name='unit_cost') = 0,
    'ALTER TABLE sales_invoice_items ADD COLUMN unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT ''Average cost when sold'' AFTER unit_price', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Serial lists (one serial per line) can be longer than 255 characters.
SET @q := IF((SELECT data_type FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoice_items' AND column_name='serial_imei') = 'varchar',
    'ALTER TABLE sales_invoice_items MODIFY serial_imei TEXT NULL', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT data_type FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_return_items' AND column_name='serial_imei') = 'varchar',
    'ALTER TABLE sales_return_items MODIFY serial_imei TEXT NULL', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_orders' AND column_name='branch_id') = 0,
    'ALTER TABLE sales_orders ADD COLUMN branch_id INT UNSIGNED NULL AFTER order_no, ADD COLUMN additional_info TEXT NULL AFTER narration', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_returns' AND column_name='stock_doc_id') = 0,
    'ALTER TABLE sales_returns ADD COLUMN stock_doc_id BIGINT UNSIGNED NULL COMMENT ''inv_docs row (doc_type sales_return) holding the stock in''', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Settings. sales_stock_mode: deduct = invoices, POS and bills from orders take goods out of stock; off = sales never touch stock.
INSERT IGNORE INTO inv_settings (setting_key, setting_value) VALUES
    ('sales_stock_mode', 'deduct'),
    ('member_points_per_100', '1'),
    ('member_point_value', '1');

-- POS receipt (Settings > User Config): heading line, default VAT % and whether it is in the price, footer text.
INSERT IGNORE INTO inv_settings (setting_key, setting_value) VALUES
    ('pos_receipt_heading', 'MUSHAK-6.3'),
    ('pos_vat_percent', '0'),
    ('pos_vat_mode', 'inclusive'),
    ('pos_receipt_footer', '');

-- Loyalty members. Points are earned on invoices (member_points_per_100 per 100 Tk of net) and redeemed with Point Adjust.
CREATE TABLE IF NOT EXISTS sales_members (
    member_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_no VARCHAR(40) NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    member_name VARCHAR(190) NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    opening_points DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (member_id),
    UNIQUE KEY uq_sales_members_no (member_no),
    KEY ix_sales_members_mobile (mobile),
    CONSTRAINT fk_sales_member_customer FOREIGN KEY (customer_id) REFERENCES sales_customers (customer_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Point ledger: earn (+), redeem (-), adjust (+/-). Balance = opening_points + SUM(points).
CREATE TABLE IF NOT EXISTS sales_member_points (
    entry_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    entry_date DATE NOT NULL,
    kind ENUM('earn','redeem','adjust') NOT NULL,
    points DECIMAL(12,2) NOT NULL,
    note VARCHAR(190) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (entry_id),
    KEY ix_sales_member_points_member (member_id),
    KEY ix_sales_member_points_invoice (invoice_id),
    CONSTRAINT fk_sales_member_point_member FOREIGN KEY (member_id) REFERENCES sales_members (member_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sales_member_point_invoice FOREIGN KEY (invoice_id) REFERENCES sales_invoices (invoice_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items the customer hands back in exchange; their value is the invoice's Product Exchange amount.
CREATE TABLE IF NOT EXISTS sales_invoice_exchanges (
    exchange_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    serial_no VARCHAR(120) NOT NULL DEFAULT '',
    quantity DECIMAL(15,3) NOT NULL DEFAULT 1.000,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (exchange_id),
    KEY ix_sales_invoice_exchanges_invoice (invoice_id),
    CONSTRAINT fk_sales_exchange_invoice FOREIGN KEY (invoice_id) REFERENCES sales_invoices (invoice_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Files attached to invoices and orders. Stored in vsc/sales/uploads under a random name.
CREATE TABLE IF NOT EXISTS sales_doc_attachments (
    attachment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doc_kind ENUM('invoice','order') NOT NULL,
    doc_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attachment_id),
    UNIQUE KEY uq_sales_doc_attachments_stored (stored_name),
    KEY ix_sales_doc_attachments_doc (doc_kind, doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
