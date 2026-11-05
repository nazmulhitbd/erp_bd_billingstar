-- Challan, Challan Return and Sales Invoice/Bill From Challan. MySQL 8.0+. Safe to run again.
-- Run after vsc/inventory/schema.sql (and vsc/sales/schema_v2.sql for the bill's invoice fields).
-- The documents are inv_docs rows (doc_type challan, challan_return, challan_bill); this file adds the Additional Info
-- column and a status view, and needs no tables of its own.

SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='inv_docs' AND column_name='additional_info') = 0,
    'ALTER TABLE inv_docs ADD COLUMN additional_info TEXT NULL AFTER narration', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- Every active challan line with what came back, what was billed and what is still to bill.
-- Returns use up free quantity first, so pending = LEAST(qty, qty + free - returned) - billed (same rule as the bill screen).
CREATE OR REPLACE VIEW challan_line_status AS
SELECT d.doc_id, d.doc_no, d.doc_date, d.party_name, d.mobile, d.source_no AS order_no, d.branch_id,
       l.line_id, l.line_no, l.product_id, l.product_code, l.model_caption, l.description, l.store_name,
       l.quantity, l.free_quantity, l.quantity + l.free_quantity AS delivered_qty,
       COALESCE(r.qty, 0) AS returned_qty, COALESCE(b.qty, 0) AS billed_qty,
       GREATEST(LEAST(l.quantity, l.quantity + l.free_quantity - COALESCE(r.qty, 0)) - COALESCE(b.qty, 0), 0) AS pending_qty
FROM inv_doc_lines l
JOIN inv_docs d ON d.doc_id = l.doc_id AND d.doc_type = 'challan' AND d.status = 'active'
LEFT JOIN (SELECT c.source_line_id, SUM(c.quantity + c.free_quantity) AS qty FROM inv_doc_lines c JOIN inv_docs x ON x.doc_id = c.doc_id
           WHERE x.doc_type = 'challan_return' AND x.status = 'active' AND c.source_kind = 'inv' GROUP BY c.source_line_id) r ON r.source_line_id = l.line_id
LEFT JOIN (SELECT c.source_line_id, SUM(c.quantity) AS qty FROM inv_doc_lines c JOIN inv_docs x ON x.doc_id = c.doc_id
           WHERE x.doc_type = 'challan_bill' AND x.status = 'active' AND c.source_kind = 'inv' GROUP BY c.source_line_id) b ON b.source_line_id = l.line_id;
