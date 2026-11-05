-- Product Information menu: Product Category, Periodical Warranty, Product Classification, Product Information,
-- Sales Price Fixing, Unit Information, Warranty, Transaction Category, Purchase Price Fixing. MySQL 8.0+. Safe to run again.
-- Run after vsc/sales/schema.sql, vsc/inventory/schema.sql and vsc/price_history/schema.sql.
-- Products stay in sales_products (every screen reads it); this file adds the setup lists, a few product columns,
-- the price fixing log, and fills the lists from the product data already saved.

CREATE TABLE IF NOT EXISTS product_categories (
    category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_product_categories_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product groups and brands (the names products carry in product_group / brand). A group can belong to a category.
CREATE TABLE IF NOT EXISTS product_classifications (
    class_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_type ENUM('group','brand') NOT NULL,
    class_name VARCHAR(140) NOT NULL,
    category_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id),
    UNIQUE KEY uq_product_classifications (class_type, class_name),
    CONSTRAINT fk_product_class_category FOREIGN KEY (category_id) REFERENCES product_categories (category_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_units (
    unit_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_name VARCHAR(40) NOT NULL,
    short_name VARCHAR(20) NOT NULL DEFAULT '',
    allow_decimal TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (unit_id),
    UNIQUE KEY uq_product_units_name (unit_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warranty terms. period / period_unit let screens work out the end date (Serial History uses it).
CREATE TABLE IF NOT EXISTS product_warranties (
    warranty_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    warranty_name VARCHAR(100) NOT NULL,
    period INT UNSIGNED NOT NULL DEFAULT 0,
    period_unit ENUM('none','day','month','year') NOT NULL DEFAULT 'none',
    terms VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (warranty_id),
    UNIQUE KEY uq_product_warranties_name (warranty_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warranty that applies for a date range (e.g. a brand campaign): to one product, a product group or a brand.
-- The most specific active rule for today wins (product, then group, then brand) and is offered when selling.
CREATE TABLE IF NOT EXISTS product_periodic_warranties (
    pw_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope ENUM('product','group','brand') NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    scope_value VARCHAR(140) NOT NULL DEFAULT '',
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    warranty_id INT UNSIGNED NOT NULL,
    note VARCHAR(190) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pw_id),
    KEY ix_product_periodic_dates (is_active, from_date, to_date),
    CONSTRAINT fk_periodic_product FOREIGN KEY (product_id) REFERENCES sales_products (product_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_periodic_warranty FOREIGN KEY (warranty_id) REFERENCES product_warranties (warranty_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_periodic_dates CHECK (to_date >= from_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Names for kinds of transactions per module (Cash Sales, Credit Purchase, ...).
CREATE TABLE IF NOT EXISTS transaction_categories (
    tc_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    module ENUM('sales','purchase','inventory','accounts','rma') NOT NULL,
    category_name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tc_id),
    UNIQUE KEY uq_transaction_categories (module, category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product columns used by Product Information. Each ALTER runs only when its column is missing.
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_products' AND column_name='category_id') = 0,
    'ALTER TABLE sales_products ADD COLUMN category_id INT UNSIGNED NULL AFTER product_code, ADD KEY ix_sales_products_category (category_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_products' AND column_name='purchase_price') = 0,
    'ALTER TABLE sales_products ADD COLUMN purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT ''Purchase Price Fixing; default cost on purchase screens'' AFTER default_unit_price, ADD COLUMN reorder_level DECIMAL(15,3) NOT NULL DEFAULT 0.000 AFTER purchase_price, ADD COLUMN vat_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER reorder_level, ADD COLUMN serial_required TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Stock documents must give a serial per unit'' AFTER vat_percent', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Sales / Purchase Price Fixing: one row per run, one line per product price changed.
CREATE TABLE IF NOT EXISTS product_price_fixings (
    fixing_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fixing_no VARCHAR(40) NOT NULL,
    fixing_date DATE NOT NULL,
    price_kind ENUM('sales','purchase') NOT NULL,
    price_field VARCHAR(30) NOT NULL,
    scope VARCHAR(20) NOT NULL DEFAULT 'all',
    scope_text VARCHAR(255) NOT NULL DEFAULT '',
    method ENUM('manual','percent_cost','percent_change','amount_change') NOT NULL DEFAULT 'manual',
    method_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(255) NOT NULL DEFAULT '',
    product_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_by_name VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (fixing_id),
    UNIQUE KEY uq_product_price_fixings_no (fixing_no),
    KEY ix_product_price_fixings_kind (price_kind, fixing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_price_fixing_lines (
    line_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fixing_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    old_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    new_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (line_id),
    KEY ix_price_fixing_lines_fixing (fixing_id),
    KEY ix_price_fixing_lines_product (product_id),
    CONSTRAINT fk_price_fixing_line_fixing FOREIGN KEY (fixing_id) REFERENCES product_price_fixings (fixing_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_price_fixing_line_product FOREIGN KEY (product_id) REFERENCES sales_products (product_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starting data: common units, warranty terms and transaction categories, plus the groups, brands, units and
-- warranties already used on products.
INSERT IGNORE INTO product_units (unit_name, short_name, allow_decimal) VALUES ('Pcs', 'pc', 0), ('Box', 'box', 0), ('Set', 'set', 0), ('Pair', 'pr', 0), ('Kg', 'kg', 1), ('Meter', 'm', 1), ('Feet', 'ft', 1), ('Litre', 'l', 1);
INSERT IGNORE INTO product_warranties (warranty_name, period, period_unit) VALUES ('No Warranty', 0, 'none'), ('7 Days', 7, 'day'), ('1 Month', 1, 'month'), ('3 Months', 3, 'month'),
    ('6 Months', 6, 'month'), ('1 Year', 1, 'year'), ('2 Years', 2, 'year'), ('3 Years', 3, 'year'), ('5 Years', 5, 'year');
INSERT IGNORE INTO transaction_categories (module, category_name, is_default) VALUES ('sales', 'Cash Sales', 1), ('sales', 'Credit Sales', 0), ('sales', 'POS Sales', 0), ('sales', 'Corporate Sales', 0),
    ('purchase', 'Credit Purchase', 1), ('purchase', 'Cash Purchase', 0), ('purchase', 'Import Purchase', 0), ('inventory', 'Stock Transfer', 1), ('inventory', 'Stock Amendment', 0), ('rma', 'Service', 1);
INSERT IGNORE INTO product_classifications (class_type, class_name) SELECT DISTINCT 'group', product_group FROM sales_products WHERE product_group <> '';
INSERT IGNORE INTO product_classifications (class_type, class_name) SELECT DISTINCT 'brand', brand FROM sales_products WHERE brand <> '';
INSERT IGNORE INTO product_units (unit_name) SELECT DISTINCT unit_name FROM sales_products WHERE unit_name <> '';
INSERT IGNORE INTO product_warranties (warranty_name) SELECT DISTINCT warranty FROM sales_products WHERE warranty <> '';
