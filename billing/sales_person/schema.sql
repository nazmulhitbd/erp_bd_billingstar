-- Sales Person Management: SR, territory, target, commission.
-- MySQL 8.0+. Safe to run again. Run after vsc/sales/schema.sql, vsc/sales/sales_order/schema.sql, vsc/trader/schema.sql
-- and vsc/sales/schema_v2.sql.
--
-- SRs are the existing sales_representatives rows (also used by Trader wise SR Fixing). This file adds their
-- territory and commission settings, monthly targets, commission payments, and an sr_id link on invoices and orders.

SET @db := DATABASE();

-- Role permission for this menu: View, Add, Edit, Delete, Commission Payment, Targets. Added once; a database from
-- before this module starts it from the role's old common_client rights.
SET @need := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='admin_role' AND column_name='sales_person_mgmt') = 0;
SET @q := IF(@need, "ALTER TABLE admin_role ADD COLUMN sales_person_mgmt VARCHAR(30) NOT NULL DEFAULT '1,1,1,1,1,1'", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
-- Older roles keep 5 flags; the 6th (Targets) then follows the role's View flag.
SET @old := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='admin_role' AND column_name='common_client') > 0;
SET @q := IF(@need AND @old, "UPDATE admin_role SET sales_person_mgmt=IF(common_client REGEXP '^[01](,[01]){4}$', CONCAT(common_client, ',', LEFT(common_client, 1)), common_client) WHERE common_client REGEXP '^[01](,[01]){4,5}$'", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS sr_territories (
    territory_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    territory_code VARCHAR(20) NOT NULL DEFAULT '',
    territory_name VARCHAR(120) NOT NULL,
    region VARCHAR(120) NOT NULL DEFAULT '',
    parent_id INT UNSIGNED NULL COMMENT 'Wider territory this one is part of',
    description VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (territory_id),
    UNIQUE KEY uq_sr_territories_name (territory_name),
    CONSTRAINT fk_sr_territory_parent FOREIGN KEY (parent_id) REFERENCES sr_territories (territory_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SR profile and commission settings. Each ALTER runs only when its column is missing.
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_representatives' AND column_name='territory_id') = 0,
    "ALTER TABLE sales_representatives ADD COLUMN territory_id INT UNSIGNED NULL AFTER designation, ADD COLUMN email VARCHAR(190) NOT NULL DEFAULT '' AFTER mobile, ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT '' AFTER email, ADD COLUMN joining_date DATE NULL AFTER territory_id, ADD COLUMN commission_basis ENUM('none','net_sales','collection','gross_profit') NOT NULL DEFAULT 'net_sales' AFTER joining_date, ADD COLUMN commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percent of the basis' AFTER commission_basis, ADD COLUMN target_bonus_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Extra percent of net sales when the month target is reached' AFTER commission_rate, ADD KEY ix_sales_representatives_territory (territory_id), ADD CONSTRAINT fk_sr_territory FOREIGN KEY (territory_id) REFERENCES sr_territories (territory_id) ON UPDATE CASCADE ON DELETE SET NULL",
    'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Monthly targets (target_month is the first day of the month).
CREATE TABLE IF NOT EXISTS sr_targets (
    target_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sr_id BIGINT UNSIGNED NOT NULL,
    target_month DATE NOT NULL,
    sales_target DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    collection_target DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(190) NOT NULL DEFAULT '',
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (target_id),
    UNIQUE KEY uq_sr_targets (sr_id, target_month),
    CONSTRAINT fk_sr_target_sr FOREIGN KEY (sr_id) REFERENCES sales_representatives (sr_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT ck_sr_target_month CHECK (DAY(target_month) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commission paid to an SR for a month.
CREATE TABLE IF NOT EXISTS sr_commission_payments (
    payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sr_id BIGINT UNSIGNED NOT NULL,
    period_month DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_on DATE NOT NULL,
    payment_method ENUM('cash','bank','mobile','other') NOT NULL DEFAULT 'cash',
    reference VARCHAR(120) NOT NULL DEFAULT '',
    note VARCHAR(190) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    KEY ix_sr_commission_payments (sr_id, period_month),
    CONSTRAINT fk_sr_commission_payment_sr FOREIGN KEY (sr_id) REFERENCES sales_representatives (sr_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_sr_commission_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which SR made an invoice or order. Set when saved (sales person name = SR name); old rows are matched by name below.
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_invoices' AND column_name='sr_id') = 0,
    'ALTER TABLE sales_invoices ADD COLUMN sr_id BIGINT UNSIGNED NULL AFTER sales_person, ADD KEY ix_sales_invoices_sr (sr_id, sales_date)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='sales_orders' AND column_name='sr_id') = 0,
    'ALTER TABLE sales_orders ADD COLUMN sr_id BIGINT UNSIGNED NULL AFTER sales_person, ADD KEY ix_sales_orders_sr (sr_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
UPDATE sales_invoices v JOIN sales_representatives s ON s.sr_name = v.sales_person SET v.sr_id = s.sr_id WHERE v.sr_id IS NULL AND v.sales_person <> '';
UPDATE sales_orders o JOIN sales_representatives s ON s.sr_name = o.sales_person SET o.sr_id = s.sr_id WHERE o.sr_id IS NULL AND o.sales_person <> '';
