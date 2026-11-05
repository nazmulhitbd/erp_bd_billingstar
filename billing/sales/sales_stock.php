<?php
/**
 * Stock for sales. A sales invoice takes goods out of stock, and a sales return of such an invoice puts them back,
 * through a linked inv_docs row (doc_type sales_invoice / sales_return, same number as the sales document). That way
 * stock balance, serial history, transaction locks and the Stock Register treat sales like every other movement.
 * Turned off with inv_settings sales_stock_mode = off.
 */
require_once dirname(__DIR__) . '/inventory/inv_common.php';

function sales_stock_on(mysqli $db)
{
    return inv_ready($db) && sales_tables_ready($db, ['sales_members']) && inv_setting($db, 'sales_stock_mode', 'deduct') !== 'off';
}

/** Average purchase cost per product id, for the unit cost kept on invoice lines. */
function sales_avg_costs(mysqli $db, array $productIds)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
    if (!$ids || !inv_ready($db)) {
        return [];
    }
    $rows = sales_rows(
        $db,
        "SELECT product_id, SUM(qty_in*unit_cost)/NULLIF(SUM(qty_in),0) AS c FROM inv_stock_moves WHERE qty_in>0 AND doc_type IN ('purchase','stock_in') AND product_id IN ("
            . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY product_id',
        str_repeat('i', count($ids)),
        $ids
    );
    return array_map('floatval', array_column($rows, 'c', 'product_id'));
}

/**
 * Create the stock document for a sales document and post it. $head: doc_no, doc_date, branch_id, party fields, sales_invoice_id.
 * $lines: product_id, product_code, product_group, brand, model_caption, description, unit_name, store_name, quantity,
 * free_quantity, unit_price, serials (text or list). Returns the inv_docs id.
 */
function sales_stock_post(mysqli $db, $type, array $head, array $lines)
{
    $default = inv_setting($db, 'default_store', '');
    $branch = (int) ($head['branch_id'] ?? 0) ?: inv_current_branch($db);
    inv_assert_unlocked($db, $head['doc_date'], $branch);
    $docId = sales_insert(
        $db,
        "INSERT INTO inv_docs (doc_type, doc_no, doc_date, ref_no, branch_id, party_type, party_id, party_group, party_name, mobile, sales_invoice_id, net_amount, total_quantity, approval_status, created_by) "
            . "VALUES (?, ?, ?, ?, ?, 'customer', ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        'ssssiisssiddsi',
        [$type, $head['doc_no'], $head['doc_date'], (string) ($head['ref_no'] ?? ''), $branch, $head['party_id'] ?? null, (string) ($head['party_group'] ?? ''),
            (string) $head['party_name'], (string) ($head['mobile'] ?? ''), $head['sales_invoice_id'] ?? null, (float) ($head['net_amount'] ?? 0),
            (float) ($head['total_quantity'] ?? 0), (string) ($head['approval_status'] ?? 'pending'), (int) ($GLOBALS['user_id'] ?? 0)]
    );
    foreach (array_values($lines) as $n => $l) {
        $serials = inv_parse_serials($l['serials'] ?? '');
        sales_insert(
            $db,
            'INSERT INTO inv_doc_lines (doc_id, line_no, product_id, product_code, product_group, brand, model_caption, description, unit_name, store_name, quantity, free_quantity, unit_price, line_total, serials) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'iiisssssssdddds',
            [$docId, $n + 1, ((int) ($l['product_id'] ?? 0)) ?: null, (string) ($l['product_code'] ?? ''), (string) ($l['product_group'] ?? ''), (string) ($l['brand'] ?? ''),
                (string) ($l['model_caption'] ?? ''), (string) $l['description'], (string) ($l['unit_name'] ?? 'Pcs'), (string) (($l['store_name'] ?? '') ?: $default),
                (float) $l['quantity'], (float) ($l['free_quantity'] ?? 0), (float) ($l['unit_price'] ?? 0), round((float) $l['quantity'] * (float) ($l['unit_price'] ?? 0), 2), implode("\n", $serials)]
        );
    }
    $doc = sales_row($db, 'SELECT * FROM inv_docs WHERE doc_id=?', 'i', [$docId]);
    $saved = sales_rows($db, 'SELECT * FROM inv_doc_lines WHERE doc_id=? ORDER BY line_no', 'i', [$docId]);
    inv_apply_stock($db, $doc, $saved, INV_TYPES[$type]['stock'], INV_TYPES[$type]['title']);
    return $docId;
}

/** Undo and delete a stock document made by sales_stock_post (edit or void of the sales document). */
function sales_stock_remove(mysqli $db, $stockDocId)
{
    if (!$stockDocId) {
        return;
    }
    $touched = inv_reverse_stock($db, (int) $stockDocId);
    sales_exec($db, 'DELETE FROM inv_docs WHERE doc_id=?', 'i', [(int) $stockDocId])->close();
    foreach ($touched as $t) {
        if (inv_available($db, (int) $t['product_id'], (string) $t['store_name']) < -0.0005) {
            throw new RuntimeException('This would make stock negative in ' . $t['store_name'] . ' because some of it was already issued again.');
        }
    }
}

/** Stock out for a saved invoice (no-op when sales stock is off). Stores the link on the invoice. */
function sales_stock_post_invoice(mysqli $db, $invoiceId)
{
    if (!sales_stock_on($db)) {
        return null;
    }
    $inv = sales_row($db, 'SELECT * FROM sales_invoices WHERE invoice_id=?', 'i', [(int) $invoiceId]);
    $items = sales_rows($db, 'SELECT * FROM sales_invoice_items WHERE invoice_id=? ORDER BY line_number', 'i', [(int) $invoiceId]);
    foreach ($items as &$it) {
        if (!(int) $it['product_id']) {
            throw new RuntimeException('"' . $it['description'] . '" is not a saved product. Pick it from the product list so stock can be tracked.');
        }
        $it['serials'] = $it['serial_imei'];
    }
    unset($it);
    $docId = sales_stock_post($db, 'sales_invoice', [
        'doc_no' => $inv['invoice_no'], 'doc_date' => $inv['sales_date'], 'ref_no' => $inv['ref_no'], 'branch_id' => $inv['branch_id'] ?? null, 'party_id' => $inv['customer_id'],
        'party_group' => $inv['customer_group'], 'party_name' => $inv['customer_name'], 'mobile' => $inv['mobile'], 'sales_invoice_id' => (int) $invoiceId,
        'net_amount' => $inv['net_amount'], 'total_quantity' => $inv['total_quantity'], 'approval_status' => $inv['approval_status'] ?? 'pending',
    ], $items);
    sales_exec($db, 'UPDATE sales_invoices SET stock_doc_id=? WHERE invoice_id=?', 'ii', [$docId, (int) $invoiceId])->close();
    return $docId;
}

/**
 * Stock back in for a saved sales return, when its invoice took stock out. Serials must be ones sold on that invoice.
 */
function sales_stock_post_return(mysqli $db, $returnId)
{
    $ret = sales_row($db, 'SELECT r.*, v.stock_doc_id AS invoice_stock_doc, v.customer_group, v.branch_id FROM sales_returns r JOIN sales_invoices v ON v.invoice_id=r.invoice_id WHERE r.return_id=?', 'i', [(int) $returnId]);
    if (!$ret || !$ret['invoice_stock_doc'] || !inv_ready($db)) {
        return null;
    }
    $sold = [];
    foreach (sales_rows($db, 'SELECT product_id, serials FROM inv_doc_lines WHERE doc_id=?', 'i', [(int) $ret['invoice_stock_doc']]) as $l) {
        $sold[(int) $l['product_id']] = array_merge($sold[(int) $l['product_id']] ?? [], inv_parse_serials($l['serials']));
    }
    $items = sales_rows($db, 'SELECT ri.*, ii.product_group, ii.brand, ii.model_caption FROM sales_return_items ri JOIN sales_invoice_items ii ON ii.item_id=ri.invoice_item_id WHERE ri.return_id=?', 'i', [(int) $returnId]);
    foreach ($items as &$it) {
        $it['serials'] = inv_parse_serials($it['serial_imei']);
        $foreign = array_diff($it['serials'], $sold[(int) $it['product_id']] ?? []);
        if ($foreign) {
            throw new RuntimeException('Serial ' . reset($foreign) . ' was not sold on invoice for "' . $it['description'] . '".');
        }
        $it['unit_price'] = $it['unit_value'];
    }
    unset($it);
    $docId = sales_stock_post($db, 'sales_return', [
        'doc_no' => $ret['return_no'], 'doc_date' => $ret['return_date'], 'branch_id' => $ret['branch_id'], 'party_id' => $ret['customer_id'], 'party_group' => $ret['customer_group'],
        'party_name' => $ret['customer_name'], 'mobile' => $ret['mobile'], 'sales_invoice_id' => (int) $ret['invoice_id'], 'net_amount' => $ret['return_amount'],
        'total_quantity' => $ret['total_quantity'], 'approval_status' => $ret['approval_status'] ?? 'pending',
    ], $items);
    sales_exec($db, 'UPDATE sales_returns SET stock_doc_id=? WHERE return_id=?', 'ii', [$docId, (int) $returnId])->close();
    return $docId;
}
