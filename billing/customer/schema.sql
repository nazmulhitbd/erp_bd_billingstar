-- Customer Management: customer profile, credit limit, ledger.
-- MySQL 8.0+. Safe to run again. Run after vsc/sales/schema.sql, vsc/sales/sales_retrun/schema.sql, vsc/trader/schema.sql
-- and vsc/sales/schema_v2.sql.
--
-- Customers are the sales_customers rows every sales screen uses. Their full profile is a customer-type trader
-- (traders, synced to sales_customers), credit rules are trader_flags. This file adds the role permission column and
-- two read-only views.

SET @db := DATABASE();

-- Role permission for this menu: View, Add, Edit, Delete, Credit Limit, Ledger (same 6-flag format as the other client rows).
-- Added once. A database from before this module starts it from the role's old ivr_client rights.
SET @need := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='admin_role' AND column_name='customer_mgmt') = 0;
SET @q := IF(@need, "ALTER TABLE admin_role ADD COLUMN customer_mgmt VARCHAR(30) NOT NULL DEFAULT '1,1,1,1,1,1'", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
-- Older roles keep 5 flags; the 6th (Ledger) then follows the role's View flag.
SET @old := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='admin_role' AND column_name='ivr_client') > 0;
SET @q := IF(@need AND @old, "UPDATE admin_role SET customer_mgmt=IF(ivr_client REGEXP '^[01](,[01]){4}$', CONCAT(ivr_client, ',', LEFT(ivr_client, 1)), ivr_client) WHERE ivr_client REGEXP '^[01](,[01]){4,5}$'", 'DO 0');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- One row per customer: profile link, credit rules and what is owed now
-- (opening balance + the due of every posted invoice, which already counts receipts and returns).
CREATE OR REPLACE VIEW customer_accounts AS
SELECT c.customer_id, c.customer_code, c.customer_group, c.customer_name, c.mobile, c.attention, c.address, c.bin, c.nid,
       t.trader_id, t.trader_code, t.category_id, COALESCE(tc.category_name, '') AS category_name, COALESCE(t.is_active, 1) AS is_active,
       COALESCE(t.opening_balance, 0) AS opening_balance,
       f.trader_id IS NOT NULL AS has_credit_rules,
       COALESCE(f.allow_credit, 0) AS allow_credit, COALESCE(f.credit_limit, 0) AS credit_limit, COALESCE(f.credit_days, 0) AS credit_days,
       COALESCE(f.block_sales, 0) AS block_sales, COALESCE(f.price_level, 'retail') AS price_level,
       COALESCE(i.invoice_due, 0) AS invoice_due,
       COALESCE(t.opening_balance, 0) + COALESCE(i.invoice_due, 0) AS balance,
       COALESCE(i.total_sales, 0) AS total_sales, COALESCE(i.invoice_count, 0) AS invoice_count, i.last_invoice_date, i.oldest_due_date,
       (SELECT s.sr_name FROM trader_sr_assignments a JOIN sales_representatives s ON s.sr_id = a.sr_id
        WHERE a.trader_id = t.trader_id AND a.effective_to IS NULL ORDER BY a.assignment_id DESC LIMIT 1) AS sr_name
FROM sales_customers c
LEFT JOIN traders t ON t.sales_customer_id = c.customer_id
LEFT JOIN trader_categories tc ON tc.category_id = t.category_id
LEFT JOIN trader_flags f ON f.trader_id = t.trader_id
LEFT JOIN (SELECT customer_id, SUM(balance_amount) AS invoice_due, SUM(net_amount) AS total_sales, COUNT(*) AS invoice_count,
                  MAX(sales_date) AS last_invoice_date, MIN(CASE WHEN balance_amount > 0.004 THEN sales_date END) AS oldest_due_date
           FROM sales_invoices WHERE status = 'posted' AND customer_id IS NOT NULL GROUP BY customer_id) i ON i.customer_id = c.customer_id;

-- Ledger lines per customer: sale (debit), receipt (credit), sales return (credit) and refund (debit).
-- Their sum plus the opening balance equals customer_accounts.balance.
CREATE OR REPLACE VIEW customer_ledger_entries AS
SELECT v.customer_id, v.sales_date AS entry_date, 1 AS sort_order, v.invoice_id AS ref_id, v.invoice_no AS doc_no, 'Sales Invoice' AS entry_type,
       v.ref_no, v.net_amount AS debit, 0.00 AS credit
FROM sales_invoices v
WHERE v.status = 'posted' AND v.customer_id IS NOT NULL
UNION ALL
SELECT v.customer_id, DATE(p.paid_at), 2, p.payment_id, v.invoice_no, CONCAT('Receipt (', p.payment_method, ')'), p.reference, 0.00, p.amount
FROM sales_invoice_payments p
JOIN sales_invoices v ON v.invoice_id = p.invoice_id AND v.status = 'posted'
WHERE v.customer_id IS NOT NULL
UNION ALL
SELECT v.customer_id, r.return_date, 3, r.return_id, r.return_no, 'Sales Return', v.invoice_no, 0.00, r.return_amount
FROM sales_returns r
JOIN sales_invoices v ON v.invoice_id = r.invoice_id AND v.status = 'posted'
WHERE r.status = 'posted' AND v.customer_id IS NOT NULL
UNION ALL
SELECT v.customer_id, r.return_date, 4, r.return_id, r.return_no, 'Refund', v.invoice_no, r.refund_amount, 0.00
FROM sales_returns r
JOIN sales_invoices v ON v.invoice_id = r.invoice_id AND v.status = 'posted'
WHERE r.status = 'posted' AND r.refund_amount > 0 AND v.customer_id IS NOT NULL;
