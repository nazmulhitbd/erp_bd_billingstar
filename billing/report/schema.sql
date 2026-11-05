-- Reports > Stock / Sales / Purchase / Transfer / Challan registers. MySQL 8.0+. Safe to run again.
-- Run after vsc/sales/schema.sql, sales_order/schema.sql, sal_bill_from_sal_order/schema.sql,
-- sales_retrun/schema.sql, vsc/inventory/schema.sql and vsc/sales/schema_v2.sql. Creates read-only views and a few indexes; no data changes.
-- Every view names its date column doc_date and uses the same product, party and store column names,
-- so the register filters work the same way on each of them.

-- Weighted average and last receipt cost per product, from purchases and Stock Amendment IN.
CREATE OR REPLACE VIEW rep_product_cost AS
SELECT m.product_id,
       ROUND(SUM(m.qty_in * m.unit_cost) / NULLIF(SUM(m.qty_in), 0), 4) AS avg_cost,
       CAST(SUBSTRING_INDEX(GROUP_CONCAT(m.unit_cost ORDER BY m.move_id DESC), ',', 1) AS DECIMAL(15,2)) AS last_cost
FROM inv_stock_moves m
WHERE m.qty_in > 0 AND m.doc_type IN ('purchase', 'stock_in')
GROUP BY m.product_id;

-- Every stock in / out with product, document and branch details.
CREATE OR REPLACE VIEW rep_stock_moves AS
SELECT m.move_id, m.move_date AS doc_date, m.product_id, p.product_code, p.product_group, p.brand, p.model_caption, p.description, p.unit_name,
       m.store_name, m.branch_id, COALESCE(b.branch_name, '') AS branch_name, m.qty_in, m.qty_out, m.unit_cost,
       m.doc_type, m.doc_id, m.doc_no, COALESCE(d.party_name, '') AS party_name, COALESCE(d.ref_no, '') AS ref_no,
       COALESCE(d.approval_status, 'approved') AS approval_status, COALESCE(c.avg_cost, 0) AS avg_cost
FROM inv_stock_moves m
JOIN sales_products p ON p.product_id = m.product_id
LEFT JOIN inv_docs d ON d.doc_id = m.doc_id
LEFT JOIN branches b ON b.branch_id = m.branch_id
LEFT JOIN rep_product_cost c ON c.product_id = m.product_id;

-- Inventory documents (purchase, requisition, purchase order, challan, transfer, amendments ...), headers and lines.
CREATE OR REPLACE VIEW rep_inv_docs AS
SELECT d.*, COALESCE(b.branch_name, '') AS branch_name
FROM inv_docs d
LEFT JOIN branches b ON b.branch_id = d.branch_id
WHERE d.status = 'active';

CREATE OR REPLACE VIEW rep_inv_lines AS
SELECT d.doc_id, d.doc_type, d.doc_no, d.doc_date, d.ref_no, d.branch_id, COALESCE(b.branch_name, '') AS branch_name,
       d.party_group, d.party_name, d.mobile, d.sales_person, d.bill_type, d.source_type, d.source_no, d.required_date,
       d.delivery_point, d.approval_status, d.sales_invoice_id, d.narration AS doc_narration,
       l.line_id, l.line_no, l.product_id, l.product_code, l.product_group, l.brand, l.model_caption, l.description, l.unit_name,
       l.store_name, l.store_to, l.quantity, l.free_quantity, l.unit_price, l.prev_price, l.discount_amount, l.line_total,
       COALESCE(l.serials, '') AS serials, l.narration AS line_narration, l.source_kind, l.source_line_id
FROM inv_doc_lines l
JOIN inv_docs d ON d.doc_id = l.doc_id
LEFT JOIN branches b ON b.branch_id = d.branch_id
WHERE d.status = 'active';

-- How much of each inventory line later documents have used (same rule as the document screens: quantity + free).
CREATE OR REPLACE VIEW rep_inv_line_usage AS
SELECT l.source_line_id AS line_id,
       SUM(CASE WHEN d.doc_type = 'purchase_order' THEN l.quantity + l.free_quantity ELSE 0 END) AS po_qty,
       SUM(CASE WHEN d.doc_type = 'purchase' THEN l.quantity + l.free_quantity ELSE 0 END) AS purchase_qty,
       SUM(CASE WHEN d.doc_type = 'challan_return' THEN l.quantity + l.free_quantity ELSE 0 END) AS return_qty,
       SUM(CASE WHEN d.doc_type = 'challan_bill' THEN l.quantity ELSE 0 END) AS bill_qty
FROM inv_doc_lines l
JOIN inv_docs d ON d.doc_id = l.doc_id
WHERE d.status = 'active' AND l.source_kind = 'inv' AND l.source_line_id IS NOT NULL
GROUP BY l.source_line_id;

-- Posted sales invoices. Branch is the invoice's own, or that of the challan bill that created it.
CREATE OR REPLACE VIEW rep_sales_invoices AS
SELECT v.invoice_id, v.invoice_no AS doc_no, v.sales_date AS doc_date, v.ref_no, v.bill_type, v.customer_id,
       v.customer_group AS party_group, v.customer_name AS party_name, v.mobile, v.sales_person,
       v.subtotal, v.extra_charges, v.discount_amount, v.vat_amount + v.ait_amount AS vat_amount, v.exchange_amount,
       v.net_amount, v.net_amount - v.balance_amount AS received_amount, v.balance_amount, v.total_quantity,
       COALESCE((SELECT SUM(i.discount_amount) FROM sales_invoice_items i WHERE i.invoice_id = v.invoice_id), 0) AS line_discount,
       COALESCE((SELECT b.branch_name FROM branches b WHERE b.branch_id = v.branch_id),
                (SELECT b.branch_name FROM inv_docs cb JOIN branches b ON b.branch_id = cb.branch_id
                 WHERE cb.sales_invoice_id = v.invoice_id ORDER BY cb.doc_id LIMIT 1), '') AS branch_name,
       v.approval_status
FROM sales_invoices v
WHERE v.status = 'posted';

-- Posted invoice lines with the invoice discount shared by line value and the cost of what was sold
-- (cost kept on the line when sold, else today's average purchase cost).
CREATE OR REPLACE VIEW rep_sales_lines AS
SELECT v.invoice_id, v.doc_no, v.doc_date, v.bill_type, v.party_group, v.party_name, v.mobile, v.sales_person, v.branch_name, v.approval_status,
       i.item_id, i.product_id, i.product_code, i.product_group, i.brand, i.model_caption, i.description, i.store_name, i.unit_name, i.serial_imei,
       i.quantity, i.free_quantity, i.unit_price, i.discount_amount, i.line_total,
       ROUND(CASE WHEN v.subtotal > 0 THEN v.discount_amount * i.line_total / v.subtotal ELSE 0 END, 2) AS invoice_discount,
       ROUND(i.line_total - CASE WHEN v.subtotal > 0 THEN v.discount_amount * i.line_total / v.subtotal ELSE 0 END, 2) AS net_amount,
       ROUND((i.quantity + i.free_quantity) * COALESCE(NULLIF(i.unit_cost, 0), c.avg_cost, 0), 2) AS cost_amount
FROM sales_invoice_items i
JOIN rep_sales_invoices v ON v.invoice_id = i.invoice_id
LEFT JOIN rep_product_cost c ON c.product_id = i.product_id;

-- Posted sales returns, headers and lines (product details come from the invoice line returned).
CREATE OR REPLACE VIEW rep_sales_returns AS
SELECT r.return_id, r.return_no AS doc_no, r.return_date AS doc_date, r.invoice_id, v.invoice_no,
       v.customer_group AS party_group, r.customer_name AS party_name, r.mobile, v.sales_person, r.reason,
       r.items_amount, r.deduction_amount, r.return_amount, r.due_adjusted, r.refund_amount, r.total_quantity, r.approval_status
FROM sales_returns r
JOIN sales_invoices v ON v.invoice_id = r.invoice_id
WHERE r.status = 'posted';

CREATE OR REPLACE VIEW rep_sales_return_lines AS
SELECT r.return_id, r.doc_no, r.doc_date, r.invoice_id, r.invoice_no, r.party_group, r.party_name, r.mobile, r.sales_person, r.approval_status,
       ri.return_item_id, ri.product_id, COALESCE(ii.product_code, ri.product_code) AS product_code,
       COALESCE(ii.product_group, '') AS product_group, COALESCE(ii.brand, '') AS brand, COALESCE(ii.model_caption, '') AS model_caption,
       ri.description, ri.store_name, ri.item_condition, ri.quantity, ri.free_quantity, ri.unit_value, ri.line_total,
       ROUND((ri.quantity + ri.free_quantity) * COALESCE(c.avg_cost, 0), 2) AS cost_amount
FROM sales_return_items ri
JOIN rep_sales_returns r ON r.return_id = ri.return_id
LEFT JOIN sales_invoice_items ii ON ii.item_id = ri.invoice_item_id
LEFT JOIN rep_product_cost c ON c.product_id = ri.product_id;

-- Sales order lines with what is billed (posted invoices) or delivered by challan, and what is still pending.
CREATE OR REPLACE VIEW rep_sales_order_lines AS
SELECT o.order_id, o.order_no AS doc_no, o.order_date AS doc_date, o.delivery_date, o.customer_group AS party_group, o.customer_name AS party_name,
       o.mobile, o.sales_person, o.status, o.approval_status, o.net_amount AS order_amount, o.advance_received,
       i.item_id, i.product_id, i.product_code, i.product_group, i.brand, i.model_caption, i.description, i.store_name,
       i.quantity, i.unit_price, i.discount_amount, i.line_total,
       COALESCE(bq.qty, 0) AS billed_qty, COALESCE(cq.qty, 0) AS challan_qty,
       GREATEST(i.quantity - COALESCE(bq.qty, 0) - COALESCE(cq.qty, 0), 0) AS pending_qty
FROM sales_order_items i
JOIN sales_orders o ON o.order_id = i.order_id
LEFT JOIN (SELECT bi.order_item_id, SUM(bi.quantity) AS qty FROM sales_order_bill_items bi
           JOIN sales_order_bills b ON b.bill_id = bi.bill_id
           JOIN sales_invoices v ON v.invoice_id = b.invoice_id AND v.status = 'posted'
           GROUP BY bi.order_item_id) bq ON bq.order_item_id = i.item_id
LEFT JOIN (SELECT l.source_line_id, SUM(l.quantity + l.free_quantity) AS qty FROM inv_doc_lines l
           JOIN inv_docs d ON d.doc_id = l.doc_id AND d.doc_type = 'challan' AND d.status = 'active'
           WHERE l.source_kind = 'sales_order'
           GROUP BY l.source_line_id) cq ON cq.source_line_id = i.item_id
WHERE o.status <> 'cancelled';

-- Customer statement rows: sale (debit), payment (credit), return (credit) and refund (debit).
CREATE OR REPLACE VIEW rep_sales_ledger AS
SELECT v.doc_date, 1 AS sort_order, v.invoice_id AS ref_id, v.doc_no, 'Sales' AS txn_type, v.party_group, v.party_name, v.mobile, v.sales_person,
       v.net_amount AS debit, 0.00 AS credit
FROM rep_sales_invoices v
UNION ALL
SELECT DATE(p.paid_at), 2, p.payment_id, v.doc_no, CONCAT('Received (', p.payment_method, ')'), v.party_group, v.party_name, v.mobile, v.sales_person,
       0.00, p.amount
FROM sales_invoice_payments p
JOIN rep_sales_invoices v ON v.invoice_id = p.invoice_id
UNION ALL
SELECT r.doc_date, 3, r.return_id, r.doc_no, 'Sales Return', r.party_group, r.party_name, r.mobile, r.sales_person, 0.00, r.return_amount
FROM rep_sales_returns r
UNION ALL
SELECT r.doc_date, 4, r.return_id, r.doc_no, 'Refund', r.party_group, r.party_name, r.mobile, r.sales_person, r.refund_amount, 0.00
FROM rep_sales_returns r
WHERE r.refund_amount > 0;

-- Indexes the registers filter on. Added only when missing.
SET @db := DATABASE();
SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='inv_stock_moves' AND index_name='ix_inv_moves_date') = 0,
    'ALTER TABLE inv_stock_moves ADD KEY ix_inv_moves_date (move_date, product_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='sales_invoice_items' AND index_name='ix_sales_items_model') = 0,
    'ALTER TABLE sales_invoice_items ADD KEY ix_sales_items_model (product_group, brand, model_caption)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='inv_docs' AND index_name='ix_inv_docs_invoice') = 0,
    'ALTER TABLE inv_docs ADD KEY ix_inv_docs_invoice (sales_invoice_id)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
SET @q := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='sales_returns' AND index_name='ix_sales_returns_date') = 0,
    'ALTER TABLE sales_returns ADD KEY ix_sales_returns_date (return_date)', 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
