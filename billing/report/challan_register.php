<?php
/** Reports > Challan Register: challans, challan returns, challans still to bill and sales bills made from challans. */
require_once __DIR__ . '/report_common.php';

const CHL_USE = ['product', 'store', 'branch', 'party_group', 'party', 'approval'];

/** Challan lines with what was returned and billed; pending = what can still be billed (returns use free quantity first). */
const CHL_STATUS_FROM = "(SELECT l.*, COALESCE(u.return_qty, 0) AS ret_qty, COALESCE(u.bill_qty, 0) AS bill_qty,
    GREATEST(LEAST(l.quantity, l.quantity + l.free_quantity - COALESCE(u.return_qty, 0)) - COALESCE(u.bill_qty, 0), 0) AS pending_qty
    FROM rep_inv_lines l LEFT JOIN rep_inv_line_usage u ON u.line_id=l.line_id WHERE l.doc_type='challan') x";

/** Challan or Challan Return lines grouped by date, customer or product. */
function chl_lines(mysqli $db, array $f, $type, $group, $serials = false)
{
    $rows = rep_doc_lines($db, $f, [$type], $group, CHL_USE);
    $cols = ['doc_date' => ['Date', 'date'], 'doc_no' => [$type === 'challan' ? 'Challan No' : 'Return No']];
    if ($type === 'challan_return') {
        $cols['source_no'] = ['Challan No'];
    }
    if ($group !== 'party_name') {
        $cols['party_name'] = ['Customer'];
    }
    if ($group !== 'product') {
        $cols['product'] = ['Product'];
    }
    $cols['store_name'] = ['Store'];
    if ($serials) {
        $cols['serials'] = ['Serial / IMEI'];
    }
    $cols += ['quantity' => ['Qty', 'qty'], 'free_quantity' => ['Free', 'qty'], 'total_qty' => ['Total', 'qty']];
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'grp', 'sum' => ['quantity', 'free_quantity', 'total_qty']];
}

/** Challan lines with delivered, returned, billed and pending quantity; $pendingOnly keeps lines still to bill. */
function chl_status(mysqli $db, array $f, $group, $pendingOnly)
{
    $rows = rep_doc_lines($db, $f, ['challan'], $group, CHL_USE, $pendingOnly ? ['where' => 'pending_qty>0.0005'] : [], CHL_STATUS_FROM);
    $cols = ['doc_date' => ['Date', 'date'], 'doc_no' => ['Challan No']];
    if ($group !== 'party_name') {
        $cols['party_name'] = ['Customer'];
    }
    if ($group !== 'product') {
        $cols['product'] = ['Product'];
    }
    $cols += ['total_qty' => ['Delivered', 'qty'], 'ret_qty' => ['Returned', 'qty'], 'bill_qty' => ['Billed', 'qty'], 'pending_qty' => ['Pending Bill', 'qty']];
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'grp', 'sum' => ['total_qty', 'ret_qty', 'bill_qty', 'pending_qty']];
}

/** Sales bills made from challans, with the sales invoice each one created. */
function chl_bills(mysqli $db, array $f)
{
    $from = "(SELECT d.*, COALESCE(v.invoice_no, '') AS invoice_no FROM rep_inv_docs d LEFT JOIN sales_invoices v ON v.invoice_id=d.sales_invoice_id WHERE d.doc_type='challan_bill') x";
    $rows = rep_query($db, $from, $f, rep_and(['use' => ['branch', 'party_group', 'party', 'approval'], 'order' => 'doc_date, doc_no'], rep_product_via($f, 'doc_id', 'rep_inv_lines')));
    foreach ($rows as &$r) {
        $r['grp'] = inv_fmt_date($r['doc_date']);
        $r['due'] = (float) $r['net_amount'] - (float) $r['cash_received'];
    }
    unset($r);
    return ['cols' => ['doc_no' => ['Bill No'], 'source_no' => ['Challan No'], 'invoice_no' => ['Invoice No'], 'party_name' => ['Customer'], 'bill_type' => ['Bill Type'],
        'total_quantity' => ['Qty', 'qty'], 'subtotal' => ['Sub Total', 'num'], 'discount_amount' => ['Discount', 'num'], 'vat_amount' => ['VAT', 'num'], 'net_amount' => ['Net Amount', 'num'],
        'cash_received' => ['Cash Received', 'num'], 'due' => ['Due', 'num']],
        'rows' => $rows, 'group' => 'grp', 'sum' => ['total_quantity', 'subtotal', 'discount_amount', 'vat_amount', 'net_amount', 'cash_received', 'due']];
}

/** Challan lines made from sales orders, grouped by order number. */
function chl_by_order(mysqli $db, array $f)
{
    $rows = rep_doc_lines($db, $f, ['challan'], 'source_no', CHL_USE, ['where' => "source_kind='sales_order'"]);
    return ['cols' => ['doc_date' => ['Date', 'date'], 'doc_no' => ['Challan No'], 'party_name' => ['Customer'], 'product' => ['Product'], 'store_name' => ['Store'],
        'quantity' => ['Qty', 'qty'], 'free_quantity' => ['Free', 'qty'], 'total_qty' => ['Total', 'qty']],
        'rows' => $rows, 'group' => 'grp', 'sum' => ['quantity', 'free_quantity', 'total_qty']];
}

$reports = [
    'date' => ['caption' => 'Date Wise Challan', 'run' => fn($db, $f) => chl_lines($db, $f, 'challan', 'date')],
    'customer' => ['caption' => 'Customer Wise Challan', 'run' => fn($db, $f) => chl_lines($db, $f, 'challan', 'party_name')],
    'product' => ['caption' => 'Product Wise Challan', 'run' => fn($db, $f) => chl_lines($db, $f, 'challan', 'product')],
    'serial' => ['caption' => 'Challan With Serial', 'run' => fn($db, $f) => chl_lines($db, $f, 'challan', 'date', true)],
    'date_return' => ['caption' => 'Date Wise Challan Return', 'run' => fn($db, $f) => chl_lines($db, $f, 'challan_return', 'date')],
    'customer_return' => ['caption' => 'Customer Wise Challan Return', 'run' => fn($db, $f) => chl_lines($db, $f, 'challan_return', 'party_name')],
    'product_return' => ['caption' => 'Product Wise Challan Return', 'run' => fn($db, $f) => chl_lines($db, $f, 'challan_return', 'product')],
    'date_pending' => ['caption' => 'Date Wise Challan Pending for Bill', 'run' => fn($db, $f) => chl_status($db, $f, 'date', true)],
    'customer_pending' => ['caption' => 'Customer Wise Challan Pending for Bill', 'run' => fn($db, $f) => chl_status($db, $f, 'party_name', true)],
    'product_pending' => ['caption' => 'Product Wise Challan Pending for Bill', 'run' => fn($db, $f) => chl_status($db, $f, 'product', true)],
    'status' => ['caption' => 'Challan Wise Delivery Status', 'run' => fn($db, $f) => chl_status($db, $f, 'date', false)],
    'bill' => ['caption' => 'Date Wise Sales Bill From Challan', 'run' => fn($db, $f) => chl_bills($db, $f)],
    'order' => ['caption' => 'Sales Order Wise Challan', 'run' => fn($db, $f) => chl_by_order($db, $f)],
];

rep_register($conn, 'Challan Register', $reports, ['date', 'product', 'store', 'branch', 'party', 'approval'],
    ['party' => 'Customer', 'party_table' => 'rep_inv_docs', 'party_where' => "doc_type IN ('challan','challan_return','challan_bill')"]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
