-- Bill (sales invoice) from sales order, MySQL 8.0+.
-- Run after vsc/sales/schema.sql and vsc/sales/sales_order/schema.sql.
-- Links each invoice made from an order back to the order and to the order lines it billed,
-- so partial billing is possible and the remaining quantity per order line is always known.

CREATE TABLE IF NOT EXISTS sales_order_bills (
    bill_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    advance_applied DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (bill_id),
    UNIQUE KEY uq_sales_order_bills_invoice (invoice_id),
    KEY ix_sales_order_bills_order (order_id),
    CONSTRAINT fk_sales_order_bill_order
        FOREIGN KEY (order_id) REFERENCES sales_orders (order_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sales_order_bill_invoice
        FOREIGN KEY (invoice_id) REFERENCES sales_invoices (invoice_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_order_bill_items (
    bill_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bill_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    invoice_item_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    PRIMARY KEY (bill_item_id),
    KEY ix_sales_order_bill_items_bill (bill_id),
    KEY ix_sales_order_bill_items_order_item (order_item_id),
    KEY ix_sales_order_bill_items_invoice_item (invoice_item_id),
    CONSTRAINT fk_sales_order_bill_item_bill
        FOREIGN KEY (bill_id) REFERENCES sales_order_bills (bill_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sales_order_bill_item_order_item
        FOREIGN KEY (order_item_id) REFERENCES sales_order_items (item_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sales_order_bill_item_invoice_item
        FOREIGN KEY (invoice_item_id) REFERENCES sales_invoice_items (item_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remaining (not yet billed) quantity per order line.
CREATE OR REPLACE VIEW sales_order_item_balance AS
SELECT
    oi.item_id AS order_item_id,
    oi.order_id,
    oi.quantity AS ordered_quantity,
    COALESCE(SUM(bi.quantity), 0) AS billed_quantity,
    oi.quantity - COALESCE(SUM(bi.quantity), 0) AS remaining_quantity
FROM sales_order_items oi
LEFT JOIN sales_order_bill_items bi ON bi.order_item_id = oi.item_id
GROUP BY oi.item_id, oi.order_id, oi.quantity;
