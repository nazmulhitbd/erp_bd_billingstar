-- Price & History menu: Price Quotation, Serial History, Sales Price MRP View, Sales Price View and
-- Invoice Search by Mobile No. MySQL 8.0+. Safe to run again.
-- Run after vsc/sales/schema.sql, the sales_order and sales_retrun schemas, vsc/inventory/schema.sql and vsc/sales/schema_v2.sql.
-- Quotations are inv_docs rows (doc_type quotation, numbers like DESPQI2026000001) and price levels live in
-- inv_product_prices (inventory/schema.sql), so this file only adds the price change log and two views.

-- Every change made in Sales Price View / Sales Price MRP View: which price, old and new value, who and when.
CREATE TABLE IF NOT EXISTS inv_price_history (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    price_field VARCHAR(30) NOT NULL COMMENT 'default_unit_price, retail_a..c, dealer_a..c, mrp, lower_price, higher_price',
    old_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    new_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    changed_by BIGINT UNSIGNED NULL,
    changed_by_name VARCHAR(120) NOT NULL DEFAULT '',
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY ix_inv_price_history_product (product_id, changed_at),
    CONSTRAINT fk_inv_price_history_product FOREIGN KEY (product_id) REFERENCES sales_products (product_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price list per product: sales price, price levels, MRP band, costs and stock on hand.
CREATE OR REPLACE VIEW product_price_list AS
SELECT p.product_id, p.product_code, p.product_group, p.brand, p.model_caption, p.description, p.unit_name, p.is_active,
       p.default_unit_price AS sales_price,
       COALESCE(pp.retail_a, 0) AS retail_a, COALESCE(pp.retail_b, 0) AS retail_b, COALESCE(pp.retail_c, 0) AS retail_c,
       COALESCE(pp.dealer_a, 0) AS dealer_a, COALESCE(pp.dealer_b, 0) AS dealer_b, COALESCE(pp.dealer_c, 0) AS dealer_c,
       COALESCE(pp.mrp, 0) AS mrp, COALESCE(pp.lower_price, 0) AS lower_price, COALESCE(pp.higher_price, 0) AS higher_price, pp.updated_at AS price_updated_at,
       COALESCE(c.avg_cost, 0) AS avg_cost,
       COALESCE((SELECT m.unit_cost FROM inv_stock_moves m WHERE m.product_id = p.product_id AND m.qty_in > 0 AND m.doc_type = 'purchase' ORDER BY m.move_id DESC LIMIT 1), 0) AS last_cost,
       COALESCE((SELECT SUM(m.qty_in - m.qty_out) FROM inv_stock_moves m WHERE m.product_id = p.product_id), 0) AS stock_qty
FROM sales_products p
LEFT JOIN inv_product_prices pp ON pp.product_id = p.product_id
LEFT JOIN (SELECT product_id, SUM(qty_in * unit_cost) / NULLIF(SUM(qty_in), 0) AS avg_cost FROM inv_stock_moves
           WHERE qty_in > 0 AND doc_type IN ('purchase', 'stock_in') GROUP BY product_id) c ON c.product_id = p.product_id;

-- Every sales document of a customer by mobile number. mobile_key is the last 10 digits, so 01711..., +8801711...
-- and 1711... all match.
CREATE OR REPLACE VIEW sales_mobile_history AS
SELECT 'invoice' AS doc_kind, v.invoice_id AS doc_id, v.invoice_no AS doc_no, v.sales_date AS doc_date, v.ref_no,
       v.customer_id, v.customer_name AS party_name, v.customer_group AS party_group, v.mobile,
       RIGHT(REGEXP_REPLACE(v.mobile, '[^0-9]', ''), 10) AS mobile_key,
       v.net_amount AS amount, v.cash_received AS paid, v.balance_amount AS due, v.total_quantity, v.status, v.bill_type
FROM sales_invoices v
UNION ALL
SELECT 'return', r.return_id, r.return_no, r.return_date, v.invoice_no, r.customer_id, r.customer_name, v.customer_group, r.mobile,
       RIGHT(REGEXP_REPLACE(r.mobile, '[^0-9]', ''), 10), r.return_amount, r.refund_amount, 0.00, r.total_quantity, r.status, ''
FROM sales_returns r JOIN sales_invoices v ON v.invoice_id = r.invoice_id
UNION ALL
SELECT 'order', o.order_id, o.order_no, o.order_date, o.ref_no, o.customer_id, o.customer_name, o.customer_group, o.mobile,
       RIGHT(REGEXP_REPLACE(o.mobile, '[^0-9]', ''), 10), o.net_amount, o.advance_received, o.balance_amount, o.total_quantity, o.status, o.bill_type
FROM sales_orders o
UNION ALL
SELECT d.doc_type, d.doc_id, d.doc_no, d.doc_date, d.ref_no, d.party_id, d.party_name, d.party_group, d.mobile,
       RIGHT(REGEXP_REPLACE(d.mobile, '[^0-9]', ''), 10), d.net_amount, d.cash_received, 0.00, d.total_quantity, d.status, d.bill_type
FROM inv_docs d
WHERE d.doc_type IN ('quotation', 'challan', 'challan_return');
