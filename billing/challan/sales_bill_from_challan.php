<?php
/**
 * Sales Invoice/Bill From Challan: bill goods already delivered by a challan (no stock moves; the challan moved the stock).
 * Saving creates a normal sales invoice (sales_invoices), so the bill shows in invoice lists, returns, approvals, member
 * search and reports. Edit, Edit Price and Cancel keep that invoice in step until it is approved or returned;
 * Edit Details changes the customer details on both. Later payments are taken on the invoice (Cash Receipt).
 */
require_once dirname(__DIR__) . '/inventory/inv_doc_engine.php';
require_once dirname(__DIR__) . '/sales/sales_stock.php';

/** Reference of the payment the bill's Cash Amount becomes on its invoice. */
function chl_bill_cash_ref(array $doc)
{
    return 'Bill ' . $doc['doc_no'];
}

/** Why a saved bill can no longer change: its invoice is approved, void or has a sales return. */
function chl_bill_lock(mysqli $db, array $doc)
{
    if (empty($doc['sales_invoice_id'])) {
        return '';
    }
    $inv = sales_row($db, 'SELECT invoice_no, status, approval_status FROM sales_invoices WHERE invoice_id=?', 'i', [(int) $doc['sales_invoice_id']]);
    if (!$inv) {
        return '';
    }
    if (($inv['approval_status'] ?? '') === 'approved') {
        return 'Its invoice ' . $inv['invoice_no'] . ' is approved, so the bill can no longer change.';
    }
    if ($inv['status'] !== 'posted') {
        return 'Its invoice ' . $inv['invoice_no'] . ' is void.';
    }
    if (sales_row($db, "SELECT 1 FROM sales_returns WHERE invoice_id=? AND status='posted' LIMIT 1", 'i', [(int) $doc['sales_invoice_id']])) {
        return 'Its invoice ' . $inv['invoice_no'] . ' has a sales return. Void the return first.';
    }
    return '';
}

/** Create the bill's sales invoice, or rewrite it after an edit (keeping payments taken later on the invoice). */
function chl_bill_save_invoice(mysqli $db, $docId, array $doc, array $lines)
{
    if (!sales_tables_ready($db, ['sales_invoices', 'sales_invoice_items', 'sales_invoice_payments', 'sales_invoice_sequences', 'sales_invoice_schedules'])) {
        throw new RuntimeException('The sales invoice tables are missing. Import billing/sales/schema.sql.');
    }
    $v2 = sales_tables_ready($db, ['sales_members']);
    $customerId = (int) $doc['party_id'] > 0 && sales_row($db, 'SELECT 1 FROM sales_customers WHERE customer_id=?', 'i', [(int) $doc['party_id']]) ? (int) $doc['party_id'] : null;
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    $head = ['ref_no' => $doc['source_no'], 'sales_date' => $doc['doc_date'], 'bill_type' => $doc['bill_type'], 'customer_id' => $customerId, 'customer_group' => $doc['party_group'],
        'customer_name' => $doc['party_name'], 'mobile' => $doc['mobile'], 'attention' => $doc['attention'], 'address' => $doc['address'], 'delivery_point' => $doc['delivery_point'],
        'sales_person' => $doc['sales_person'], 'narration' => $doc['narration'] !== '' ? $doc['narration'] : 'Bill from challan ' . $doc['source_no'],
        'subtotal' => (float) $doc['subtotal'], 'extra_charges' => (float) $doc['extra_charges'], 'discount_amount' => (float) $doc['discount_amount'], 'vat_amount' => (float) $doc['vat_amount'],
        'net_amount' => (float) $doc['net_amount'], 'total_quantity' => (float) $doc['total_quantity']];
    if ($v2) {
        $head += ['branch_id' => (int) $doc['branch_id'] ?: null, 'additional_info' => $doc['additional_info'] ?? null];
    }
    $bind = fn(array $values) => implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), $values));
    $invoiceId = (int) ($doc['sales_invoice_id'] ?? 0);
    if ($invoiceId && sales_row($db, "SELECT 1 FROM sales_invoices WHERE invoice_id=? AND status='posted' FOR UPDATE", 'i', [$invoiceId])) {
        sales_exec($db, 'UPDATE sales_invoices SET ' . implode(', ', array_map(fn($c) => "$c=?", array_keys($head))) . ' WHERE invoice_id=?', $bind($head) . 'i', array_merge(array_values($head), [$invoiceId]))->close();
        sales_exec($db, 'DELETE FROM sales_invoice_items WHERE invoice_id=?', 'i', [$invoiceId])->close();
        sales_exec($db, 'DELETE FROM sales_invoice_payments WHERE invoice_id=? AND reference=?', 'is', [$invoiceId, chl_bill_cash_ref($doc)])->close();
        sales_exec($db, "DELETE FROM sales_invoice_schedules WHERE invoice_id=? AND note='Payment Date'", 'i', [$invoiceId])->close();
    } else {
        $head += ['invoice_no' => sales_next_number($db, 'SI', (int) substr($doc['doc_date'], 0, 4), 'sales_invoice_sequences'), 'created_by' => $userId];
        $invoiceId = sales_insert($db, 'INSERT INTO sales_invoices (' . implode(', ', array_keys($head)) . ') VALUES (' . implode(', ', array_fill(0, count($head), '?')) . ')', $bind($head), array_values($head));
    }
    $costs = $v2 ? sales_avg_costs($db, array_column($lines, 'product_id')) : [];
    foreach ($lines as $n => $l) {
        $row = ['invoice_id' => $invoiceId, 'product_id' => $l['product_id'] !== null ? (int) $l['product_id'] : null, 'product_code' => $l['product_code'], 'product_group' => $l['product_group'],
            'brand' => $l['brand'], 'model_caption' => $l['model_caption'], 'description' => $l['description'], 'product_narration' => $l['narration'], 'warranty' => $l['warranty'],
            'store_name' => $l['store_name'], 'unit_name' => $l['unit_name'], 'serial_imei' => (string) $l['serials'], 'quantity' => (float) $l['quantity'], 'unit_price' => (float) $l['unit_price'],
            'discount_amount' => (float) $l['discount_amount'], 'line_total' => (float) $l['line_total'], 'line_number' => $n + 1];
        if ($v2) {
            $row['unit_cost'] = round($costs[(int) $l['product_id']] ?? 0, 2);
        }
        sales_insert($db, 'INSERT INTO sales_invoice_items (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')', $bind($row), array_values($row));
    }
    if ((float) $doc['cash_received'] > 0) {
        sales_insert($db, "INSERT INTO sales_invoice_payments (invoice_id, payment_method, amount, reference, received_by) VALUES (?, 'cash', ?, ?, ?)", 'idsi', [$invoiceId, (float) $doc['cash_received'], chl_bill_cash_ref($doc), $userId]);
    }
    $paid = (float) sales_row($db, 'SELECT COALESCE(SUM(amount), 0) AS p FROM sales_invoice_payments WHERE invoice_id=?', 'i', [$invoiceId])['p'];
    if ($paid > (float) $doc['net_amount'] + 0.004) {
        throw new RuntimeException('Payments on the invoice (' . sales_money($paid) . ') are more than the new net amount ' . sales_money($doc['net_amount']) . '.');
    }
    $balance = round((float) $doc['net_amount'] - $paid, 2);
    sales_exec($db, 'UPDATE sales_invoices SET cash_received=?, balance_amount=? WHERE invoice_id=?', 'ddi', [$paid, $balance, $invoiceId])->close();
    if (!empty($doc['required_date']) && $balance > 0) {
        sales_insert($db, "INSERT INTO sales_invoice_schedules (invoice_id, due_date, amount, note) VALUES (?, ?, ?, 'Payment Date')", 'isd', [$invoiceId, $doc['required_date'], $balance]);
    }
    sales_exec($db, 'UPDATE inv_docs SET sales_invoice_id=? WHERE doc_id=?', 'ii', [$invoiceId, (int) $docId])->close();
}

/** Cancelling the bill voids its invoice (the lock check has already refused approved or returned invoices). */
function chl_bill_cancel(mysqli $db, array $doc)
{
    if (!empty($doc['sales_invoice_id'])) {
        sales_exec($db, "UPDATE sales_invoices SET status='void' WHERE invoice_id=?", 'i', [(int) $doc['sales_invoice_id']])->close();
    }
}

/** Edit Details: copy the customer details to the invoice too. */
function chl_bill_details(mysqli $db, array $doc)
{
    if (empty($doc['sales_invoice_id'])) {
        return;
    }
    $cols = ['customer_group' => $doc['party_group'], 'customer_name' => $doc['party_name'], 'mobile' => $doc['mobile'], 'attention' => $doc['attention'], 'address' => $doc['address'],
        'sales_person' => $doc['sales_person'], 'narration' => $doc['narration']];
    if (sales_tables_ready($db, ['sales_members'])) {
        $cols['additional_info'] = $doc['additional_info'] ?? null;
    }
    sales_exec($db, 'UPDATE sales_invoices SET ' . implode(', ', array_map(fn($c) => "$c=?", array_keys($cols))) . ' WHERE invoice_id=?', str_repeat('s', count($cols)) . 'i', array_merge(array_values($cols), [(int) $doc['sales_invoice_id']]))->close();
}

/** Cash Receipt: later payments are taken on the bill's invoice. */
function chl_bill_receipt($doc)
{
    if ($doc && !empty($doc['sales_invoice_id'])) {
        return '<a class="btn" href="../sales/sales_invoice/sales_invoice.php?id=' . (int) $doc['sales_invoice_id'] . '" title="Take a later payment on this bill\'s invoice">Cash Receipt</a>';
    }
    return '<button type="button" class="btn" disabled title="The Cash Amount is received when the bill is saved">Cash Receipt</button>';
}

inv_doc_page($conn, [
    'type' => 'challan_bill',
    'header' => [['mobile', 'Mobile No', 'mobile'], ['bill_type', 'Bill Type', 'select'], ['party_group', 'Customer Group', 'text'], ['party_name', 'Customer Name', 'party'],
        ['attention', 'Attention', 'text'], ['sales_person', 'Sales Person', 'text'], ['address', 'Address', 'text'], ['narration', 'Narration', 'text'], ['source_order', 'Sales Order', 'sourceinfo']],
    'header_options' => ['bill_type' => ['Credit', 'Cash']],
    'source' => ['type' => 'challan', 'label' => 'Challan Wise', 'field' => 'Challan Invoice', 'mode' => 'bill'],
    'source_required' => true,
    'lines' => ['store', 'narration', 'discount', 'serials', 'source_col'],
    'totals' => ['extra', 'discount', 'vat'],
    'cash' => true,
    'payment_date' => true,
    'cash_extra' => 'chl_bill_receipt',
    'additional' => true,
    'locked' => 'chl_bill_lock',
    'after_save' => 'chl_bill_save_invoice',
    'on_cancel' => 'chl_bill_cancel',
    'after_details' => 'chl_bill_details',
    'buttons' => ['add_all', 'edit_price_inline', 'edit_details', 'serial_view', 'import'],
    'search_label' => 'Invoice NO',
    'last_info' => 'serial',
    'print_title' => 'Sales Invoice/Bill From Challan',
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
