-- Purchase module: Requisition, Purchase Order, Purchase and Purchase Return. MySQL 8.0+. Safe to run again.
-- Run after vsc/sales/schema.sql and vsc/inventory/schema.sql.
--
-- The documents themselves live in inv_docs / inv_doc_lines (doc_type requisition, purchase_order, purchase,
-- purchase_return) so stock, serials, approvals and the registers keep working from one place. This file adds
-- what only purchases need: order date, payments to the supplier, payment schedule and attached files.

SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='inv_docs' AND column_name='order_date') = 0,
    'ALTER TABLE inv_docs ADD COLUMN order_date DATE NULL AFTER required_date', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='inv_docs' AND index_name='ix_inv_docs_party') = 0,
    'ALTER TABLE inv_docs ADD KEY ix_inv_docs_party (doc_type, party_name)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Money paid to the supplier against a purchase. is_initial = the Cash Amount entered on the purchase itself.
CREATE TABLE IF NOT EXISTS purchase_payments (
    payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doc_id BIGINT UNSIGNED NOT NULL,
    paid_on DATE NOT NULL,
    payment_method ENUM('cash','bank','mobile','cheque','other') NOT NULL DEFAULT 'cash',
    amount DECIMAL(15,2) NOT NULL,
    reference VARCHAR(120) NOT NULL DEFAULT '',
    note VARCHAR(255) NOT NULL DEFAULT '',
    is_initial TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    KEY ix_purchase_payments_doc (doc_id),
    KEY ix_purchase_payments_date (paid_on),
    CONSTRAINT fk_purchase_payment_doc FOREIGN KEY (doc_id) REFERENCES inv_docs (doc_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT ck_purchase_payment_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- When the remaining amount is due to the supplier.
CREATE TABLE IF NOT EXISTS purchase_schedules (
    schedule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doc_id BIGINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    note VARCHAR(190) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (schedule_id),
    KEY ix_purchase_schedules_doc (doc_id),
    KEY ix_purchase_schedules_due (due_date),
    CONSTRAINT fk_purchase_schedule_doc FOREIGN KEY (doc_id) REFERENCES inv_docs (doc_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT ck_purchase_schedule_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supplier invoice scans, delivery notes and other files. Stored in vsc/purchase/uploads under a random name.
CREATE TABLE IF NOT EXISTS purchase_attachments (
    attachment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doc_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attachment_id),
    UNIQUE KEY uq_purchase_attachments_stored (stored_name),
    KEY ix_purchase_attachments_doc (doc_id),
    CONSTRAINT fk_purchase_attachment_doc FOREIGN KEY (doc_id) REFERENCES inv_docs (doc_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What is still owed on each active purchase: net amount less approved-or-pending returns and payments.
CREATE OR REPLACE VIEW purchase_due AS
SELECT d.doc_id, d.doc_no, d.doc_date, d.party_id, d.party_group, d.party_name, d.bill_type, d.net_amount,
       COALESCE(r.amount, 0) AS return_amount,
       COALESCE(p.amount, 0) AS paid_amount,
       d.net_amount - COALESCE(r.amount, 0) - COALESCE(p.amount, 0) AS due_amount
FROM inv_docs d
LEFT JOIN (SELECT source_doc_id, SUM(net_amount) AS amount FROM inv_docs WHERE doc_type='purchase_return' AND status='active' GROUP BY source_doc_id) r ON r.source_doc_id = d.doc_id
LEFT JOIN (SELECT doc_id, SUM(amount) AS amount FROM purchase_payments GROUP BY doc_id) p ON p.doc_id = d.doc_id
WHERE d.doc_type = 'purchase' AND d.status = 'active';
