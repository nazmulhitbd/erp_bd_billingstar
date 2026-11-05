<?php
/** Reports > Purchase Register: purchases, returns, requisitions, purchase orders, pending lists and supplier reports. */
require_once __DIR__ . '/report_common.php';

const PUR_USE = ['product', 'store', 'branch', 'party_group', 'party', 'approval'];
const PUR_HEAD_USE = ['branch', 'party_group', 'party', 'approval'];
const PUR_PRODUCT = 'product_id, product_code, product_group, brand, model_caption, description';

/** Document headers of one type, grouped by date or supplier. */
function pur_docs(mysqli $db, array $f, $type, $group)
{
    $rows = rep_query($db, 'rep_inv_docs', $f, rep_and(['use' => PUR_HEAD_USE, 'where' => 'doc_type=?', 'where_types' => 's', 'where_params' => [$type],
        'order' => ($group === 'doc_date' ? '' : $group . ', ') . 'doc_date, doc_no'], rep_product_via($f, 'doc_id', 'rep_inv_lines')));
    foreach ($rows as &$r) {
        $r['grp'] = $group === 'doc_date' ? inv_fmt_date($r['doc_date']) : $r[$group];
    }
    unset($r);
    return ['cols' => ['doc_date' => ['Date', 'date'], 'doc_no' => ['Doc No'], 'ref_no' => ['Ref No'], 'party_name' => ['Supplier'], 'bill_type' => ['Bill Type'], 'source_no' => ['PO No'],
        'total_quantity' => ['Qty', 'qty'], 'subtotal' => ['Sub Total', 'num'], 'discount_amount' => ['Discount', 'num'], 'vat_amount' => ['VAT', 'num'], 'extra_charges' => ['Extra', 'num'],
        'net_amount' => ['Net Amount', 'num']],
        'rows' => $rows, 'group' => 'grp', 'sum' => ['total_quantity', 'subtotal', 'discount_amount', 'vat_amount', 'extra_charges', 'net_amount'],
        'note' => $type === 'purchase_return' ? 'Purchase returns appear here once the Purchase Return screen is in use.' : ''];
}

/** Document lines of one type, one by one, grouped by product, supplier or date. */
function pur_lines(mysqli $db, array $f, $type, $group, $price = true)
{
    $order = ['product' => 'product_group, brand, model_caption, description, doc_date, doc_no', 'party_name' => 'party_name, doc_date, doc_no', 'doc_date' => 'doc_date, doc_no, line_no'][$group];
    $rows = rep_query($db, 'rep_inv_lines', $f, ['use' => PUR_USE, 'where' => 'doc_type=?', 'where_types' => 's', 'where_params' => [$type], 'order' => $order]);
    foreach ($rows as &$r) {
        $r['product'] = rep_product_key($r);
        $r['grp'] = $group === 'product' ? $r['product'] : ($group === 'doc_date' ? inv_fmt_date($r['doc_date']) : $r['party_name']);
    }
    unset($r);
    $cols = ['doc_date' => ['Date', 'date'], 'doc_no' => ['Doc No']];
    if ($type !== 'requisition') {
        $cols['party_name'] = ['Supplier'];
    } else {
        $cols += ['sales_person' => ['Requisition By'], 'required_date' => ['Required Date', 'date']];
    }
    if ($group !== 'product') {
        $cols['product'] = ['Product'];
    }
    if ($type === 'purchase') {
        $cols['store_name'] = ['Store'];
    }
    $cols += ['quantity' => ['Qty', 'qty'], 'free_quantity' => ['Free', 'qty']];
    $sum = ['quantity', 'free_quantity'];
    if ($price) {
        $cols += ['unit_price' => ['Unit Price', 'num'], 'discount_amount' => ['Discount', 'num'], 'line_total' => ['Amount', 'num']];
        array_push($sum, 'discount_amount', 'line_total');
    }
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'grp', 'sum' => $sum,
        'note' => $type === 'purchase_return' ? 'Purchase returns appear here once the Purchase Return screen is in use.' : ''];
}

/** Pending lines: requisition not yet ordered or purchased, or purchase order not yet purchased. */
function pur_pending(mysqli $db, array $f, $kind, $group)
{
    $sources = [
        'req_po' => "(SELECT l.*, COALESCE(u.po_qty, 0) AS done_qty FROM rep_inv_lines l LEFT JOIN rep_inv_line_usage u ON u.line_id=l.line_id WHERE l.doc_type='requisition') x",
        'req_purchase' => "(SELECT l.*, COALESCE(pp.qty, 0) AS done_qty FROM rep_inv_lines l LEFT JOIN (SELECT p.source_line_id AS line_id, SUM(u.purchase_qty) AS qty FROM rep_inv_lines p
            JOIN rep_inv_line_usage u ON u.line_id=p.line_id WHERE p.doc_type='purchase_order' AND p.source_kind='inv' GROUP BY p.source_line_id) pp ON pp.line_id=l.line_id WHERE l.doc_type='requisition') x",
        'po_purchase' => "(SELECT l.*, COALESCE(u.purchase_qty, 0) AS done_qty FROM rep_inv_lines l LEFT JOIN rep_inv_line_usage u ON u.line_id=l.line_id WHERE l.doc_type='purchase_order') x",
    ];
    $rows = rep_query($db, $sources[$kind], $f, ['select' => '*, quantity+free_quantity-done_qty AS pending_qty', 'use' => PUR_USE, 'having' => 'pending_qty>0.0005',
        'order' => $group === 'product' ? 'product_group, brand, model_caption, description, doc_date, doc_no' : 'doc_date, doc_no, line_no']);
    foreach ($rows as &$r) {
        $r['product'] = rep_product_key($r);
        $r['grp'] = $group === 'product' ? $r['product'] : inv_fmt_date($r['doc_date']);
        $r['total_qty'] = (float) $r['quantity'] + (float) $r['free_quantity'];
        $r['pending_value'] = (float) $r['pending_qty'] * (float) $r['unit_price'];
    }
    unset($r);
    $cols = ['doc_date' => ['Date', 'date'], 'doc_no' => ['Doc No']];
    $cols += $kind === 'po_purchase' ? ['party_name' => ['Supplier']] : ['sales_person' => ['Requisition By'], 'required_date' => ['Required Date', 'date']];
    if ($group !== 'product') {
        $cols['product'] = ['Product'];
    }
    $doneLabel = $kind === 'req_po' ? 'Ordered' : 'Purchased';
    $cols += ['total_qty' => ['Qty', 'qty'], 'done_qty' => [$doneLabel, 'qty'], 'pending_qty' => ['Pending', 'qty']];
    $sum = ['total_qty', 'done_qty', 'pending_qty'];
    if ($kind === 'po_purchase') {
        $cols += ['unit_price' => ['Unit Price', 'num'], 'pending_value' => ['Pending Value', 'num']];
        $sum[] = 'pending_value';
    }
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'grp', 'sum' => $sum];
}

/** Purchase totals per product, optionally per supplier or store; with $net, purchase returns are deducted. */
function pur_by_product(mysqli $db, array $f, $dim = '', $net = false, $byAmount = false)
{
    $d = $dim !== '' ? $dim . ', ' : '';
    $rows = rep_query($db, 'rep_inv_lines', $f, [
        'select' => $d . PUR_PRODUCT . ", SUM(CASE WHEN doc_type='purchase' THEN quantity+free_quantity ELSE 0 END) AS qty, "
            . "SUM(CASE WHEN doc_type='purchase' THEN line_total ELSE 0 END) AS amount, "
            . "SUM(CASE WHEN doc_type='purchase_return' THEN quantity+free_quantity ELSE 0 END) AS ret_qty, "
            . "SUM(CASE WHEN doc_type='purchase_return' THEN line_total ELSE 0 END) AS ret_amount",
        'use' => PUR_USE, 'where' => "doc_type IN ('purchase','purchase_return')", 'group' => $d . PUR_PRODUCT,
        'order' => $byAmount ? 'amount DESC' : $d . 'product_group, brand, model_caption, description',
    ]);
    foreach ($rows as &$r) {
        $r['net_qty'] = (float) $r['qty'] - (float) $r['ret_qty'];
        $r['net_amount'] = (float) $r['amount'] - (float) $r['ret_amount'];
        $r['avg_price'] = (float) $r['qty'] > 0 ? (float) $r['amount'] / (float) $r['qty'] : 0;
    }
    unset($r);
    $cols = rep_product_cols(false);
    if ($net) {
        $cols += ['qty' => ['Purchase Qty', 'qty'], 'amount' => ['Purchase Amount', 'num'], 'ret_qty' => ['Return Qty', 'qty'], 'ret_amount' => ['Return Amount', 'num'],
            'net_qty' => ['Net Qty', 'qty'], 'net_amount' => ['Net Amount', 'num']];
        $sum = ['qty', 'amount', 'ret_qty', 'ret_amount', 'net_qty', 'net_amount'];
    } else {
        $cols += ['qty' => ['Qty', 'qty'], 'avg_price' => ['Avg Price', 'num'], 'amount' => ['Amount', 'num']];
        $sum = ['qty', 'amount'];
    }
    return ['cols' => $cols, 'rows' => $rows, 'group' => $dim !== '' ? $dim : null, 'sum' => $sum];
}

/** Net purchase per product per date. */
function pur_net_by_date(mysqli $db, array $f)
{
    $r = pur_by_product($db, $f, 'doc_date', true);
    foreach ($r['rows'] as &$row) {
        $row['doc_date'] = inv_fmt_date($row['doc_date']);
    }
    unset($row);
    return $r;
}

/** Supplier statement: purchases (credit), cash paid on purchase and returns (debit), running payable balance. */
function pur_statement(mysqli $db, array $f)
{
    // Payments: the purchase payment table when the purchase module is installed, else the Cash Amount on each purchase.
    $paid = sales_tables_ready($db, ['purchase_payments'])
        ? "SELECT p.paid_on, d.doc_id, 2, d.doc_no, CONCAT('Paid (', p.payment_method, ')'), d.party_group, d.party_name, d.branch_id, d.approval_status, p.amount, 0.00 FROM purchase_payments p JOIN rep_inv_docs d ON d.doc_id=p.doc_id"
        : "SELECT doc_date, doc_id, 2, doc_no, 'Cash Paid', party_group, party_name, branch_id, approval_status, cash_received, 0.00 FROM rep_inv_docs WHERE doc_type='purchase' AND cash_received>0";
    $from = "(SELECT doc_date, doc_id, 1 AS sort_order, doc_no, 'Purchase' AS txn_type, party_group, party_name, branch_id, approval_status, 0.00 AS debit, net_amount AS credit FROM rep_inv_docs WHERE doc_type='purchase'
        UNION ALL $paid
        UNION ALL SELECT doc_date, doc_id, 3, doc_no, 'Purchase Return', party_group, party_name, branch_id, approval_status, net_amount, 0.00 FROM rep_inv_docs WHERE doc_type='purchase_return') x";
    $rows = rep_query($db, $from, $f, ['date' => 'asof', 'use' => PUR_HEAD_USE, 'order' => 'party_name, doc_date, doc_id, sort_order']);
    $out = [];
    $bal = [];
    $opened = [];
    foreach ($rows as $r) {
        $k = $r['party_name'];
        $bal[$k] = $bal[$k] ?? 0.0;
        if ($r['doc_date'] < $f['from']) {
            $bal[$k] += (float) $r['credit'] - (float) $r['debit'];
            continue;
        }
        if (!isset($opened[$k])) {
            $opened[$k] = true;
            $out[] = ['group_key' => $k, 'txn_type' => 'Opening Balance', 'balance' => $bal[$k], 'opening' => true];
        }
        $bal[$k] += (float) $r['credit'] - (float) $r['debit'];
        $out[] = $r + ['group_key' => $k, 'balance' => $bal[$k]];
    }
    return ['cols' => ['doc_date' => ['Date', 'date'], 'doc_no' => ['Doc No'], 'txn_type' => ['Transaction'], 'debit' => ['Debit', 'num'], 'credit' => ['Credit', 'num'], 'balance' => ['Payable', 'num']],
        'rows' => $out, 'group' => 'group_key', 'sum' => ['debit', 'credit'], 'note' => 'Payments recorded on purchases are included. Payment vouchers posted only in Accounts are in the Accounts Register (Accounts Ledger).'];
}

/** For products purchased in the period: purchased, issued and returned since, and stock now. */
function pur_movement(mysqli $db, array $f)
{
    $rows = rep_query($db, 'rep_stock_moves', $f, [
        'select' => PUR_PRODUCT . ", SUM(CASE WHEN doc_date>=? AND doc_type='purchase' THEN qty_in ELSE 0 END) AS purchased, "
            . "SUM(CASE WHEN doc_date>=? AND doc_type IN ('sales_invoice','challan','stock_out','purchase_return') THEN qty_out ELSE 0 END) AS issued, "
            . "SUM(CASE WHEN doc_date>=? AND doc_type IN ('challan_return','sales_return') THEN qty_in ELSE 0 END) AS returned, SUM(qty_in-qty_out) AS balance",
        'select_types' => 'sss', 'select_params' => [$f['from'], $f['from'], $f['from']],
        'date' => 'asof', 'use' => ['product', 'store', 'branch'], 'group' => PUR_PRODUCT, 'having' => 'purchased>0',
        'order' => 'product_group, brand, model_caption, description',
    ]);
    return ['cols' => rep_product_cols(false) + ['purchased' => ['Purchased', 'qty'], 'issued' => ['Issued', 'qty'], 'returned' => ['Returned In', 'qty'], 'balance' => ['Current Stock', 'qty']],
        'rows' => $rows, 'sum' => ['purchased', 'issued', 'returned', 'balance']];
}

/** Purchase price amendments per supplier and product: old price, new price and the value difference. */
function pur_amendments(mysqli $db, array $f)
{
    $rows = rep_query($db, 'rep_inv_lines', $f, ['select' => '*, (unit_price-prev_price)*quantity AS difference', 'use' => PUR_USE,
        'where' => "doc_type='price_amendment'", 'order' => 'party_name, product_group, brand, model_caption, doc_date']);
    foreach ($rows as &$r) {
        $r['grp'] = $r['party_name'];
        $r['product'] = rep_product_key($r);
    }
    unset($r);
    return ['cols' => ['doc_date' => ['Date', 'date'], 'doc_no' => ['Amendment No'], 'source_no' => ['Purchase No'], 'product' => ['Product'], 'quantity' => ['Qty', 'qty'],
        'prev_price' => ['Old Price', 'num'], 'unit_price' => ['New Price', 'num'], 'difference' => ['Difference', 'num']],
        'rows' => $rows, 'group' => 'grp', 'sum' => ['quantity', 'difference']];
}

$reports = [
    'date' => ['caption' => 'Date wise Purchase', 'run' => fn($db, $f) => pur_docs($db, $f, 'purchase', 'doc_date')],
    'supplier' => ['caption' => 'Supplier wise Purchase', 'run' => fn($db, $f) => pur_docs($db, $f, 'purchase', 'party_name')],
    'supplier_return' => ['caption' => 'Supplier wise Purchase Return', 'run' => fn($db, $f) => pur_docs($db, $f, 'purchase_return', 'party_name')],
    'date_return' => ['caption' => 'Date wise Purchase Return', 'run' => fn($db, $f) => pur_docs($db, $f, 'purchase_return', 'doc_date')],
    'product' => ['caption' => 'Product wise Purchase', 'run' => fn($db, $f) => pur_lines($db, $f, 'purchase', 'product')],
    'product_return' => ['caption' => 'Product wise Purchase Return', 'run' => fn($db, $f) => pur_lines($db, $f, 'purchase_return', 'product')],
    'date_req_pending_po' => ['caption' => 'Date Wise Purchase Requisition Pending for Purchase Order', 'run' => fn($db, $f) => pur_pending($db, $f, 'req_po', 'date')],
    'date_req_pending_pur' => ['caption' => 'Date Wise Purchase Requisition Pending for Purchase', 'run' => fn($db, $f) => pur_pending($db, $f, 'req_purchase', 'date')],
    'date_po_pending' => ['caption' => 'Date Wise Purchase Order Pending for Purchase', 'run' => fn($db, $f) => pur_pending($db, $f, 'po_purchase', 'date')],
    'date_req' => ['caption' => 'Date Wise Purchase Requisition', 'run' => fn($db, $f) => pur_lines($db, $f, 'requisition', 'doc_date', false)],
    'date_po' => ['caption' => 'Date Wise Purchase Order', 'run' => fn($db, $f) => pur_lines($db, $f, 'purchase_order', 'doc_date')],
    'product_req_pending_po' => ['caption' => 'Product Wise Purchase Requisition Pending for Purchase Order', 'run' => fn($db, $f) => pur_pending($db, $f, 'req_po', 'product')],
    'product_req_pending_pur' => ['caption' => 'Product Wise Purchase Requisition Pending for Purchase', 'run' => fn($db, $f) => pur_pending($db, $f, 'req_purchase', 'product')],
    'product_po_pending' => ['caption' => 'Product Wise Purchase Order Pending for Purchase', 'run' => fn($db, $f) => pur_pending($db, $f, 'po_purchase', 'product')],
    'product_req' => ['caption' => 'Product Wise Purchase Requisition', 'run' => fn($db, $f) => pur_lines($db, $f, 'requisition', 'product', false)],
    'product_po' => ['caption' => 'Product Wise Purchase Order', 'run' => fn($db, $f) => pur_lines($db, $f, 'purchase_order', 'product')],
    'product_amount' => ['caption' => 'Product wise Purchase By Amount', 'run' => fn($db, $f) => pur_by_product($db, $f, '', false, true)],
    'supplier_no_price' => ['caption' => 'Supplier wise Purchase Without Price', 'run' => fn($db, $f) => pur_lines($db, $f, 'purchase', 'party_name', false)],
    'product_no_price' => ['caption' => 'Product wise Purchase Without Price', 'run' => fn($db, $f) => pur_lines($db, $f, 'purchase', 'product', false)],
    'store_net' => ['caption' => 'Store Wise Net Product Purchase', 'run' => fn($db, $f) => pur_by_product($db, $f, 'store_name', true)],
    'date_net' => ['caption' => 'Date Wise Net Product Purchase', 'run' => fn($db, $f) => pur_net_by_date($db, $f)],
    'statement' => ['caption' => 'Supplier Wise Statement', 'run' => fn($db, $f) => pur_statement($db, $f)],
    'supplier_product' => ['caption' => 'Supplier and Product Wise Purchase', 'run' => fn($db, $f) => pur_by_product($db, $f, 'party_name')],
    'movement' => ['caption' => 'Purchased Product Movement', 'run' => fn($db, $f) => pur_movement($db, $f)],
    'amendment' => ['caption' => 'Supplier and Product wise Purchase Amendment Manual', 'run' => fn($db, $f) => pur_amendments($db, $f)],
];

rep_register($conn, 'Purchase Register', $reports, ['date', 'product', 'store', 'branch', 'party', 'approval'],
    ['party' => 'Supplier', 'party_table' => 'rep_inv_docs', 'party_where' => "doc_type IN ('purchase','purchase_order','price_amendment')"]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
