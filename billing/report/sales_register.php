<?php
/** Reports > Sales Register: sales invoices, returns, gross profit, sales person (SR) and sales order reports. */
require_once __DIR__ . '/report_common.php';

const SALES_USE = ['product', 'party_group', 'party', 'sr', 'approval'];
const SALES_HEAD_USE = ['party_group', 'party', 'sr', 'approval'];
const SALES_PRODUCT = 'product_id, product_code, product_group, brand, model_caption, description';

/** Invoice line totals per product, optionally per another column (customer, SR, branch). */
function sales_by_product(mysqli $db, array $f, $dim = '', $profit = false)
{
    $d = $dim !== '' ? $dim . ', ' : '';
    $rows = rep_query($db, 'rep_sales_lines', $f, [
        'select' => $d . SALES_PRODUCT . ', SUM(quantity) AS qty, SUM(free_quantity) AS free_qty, SUM(line_total+discount_amount) AS gross, '
            . 'SUM(discount_amount+invoice_discount) AS discount, SUM(net_amount) AS net, SUM(cost_amount) AS cost, SUM(net_amount-cost_amount) AS profit',
        'use' => SALES_USE, 'group' => $d . SALES_PRODUCT, 'order' => $d . 'product_group, brand, model_caption, description',
    ]);
    foreach ($rows as &$r) {
        $r['avg_price'] = (float) $r['qty'] > 0 ? (float) $r['net'] / (float) $r['qty'] : 0;
        $r['margin'] = (float) $r['net'] != 0 ? (float) $r['profit'] * 100 / (float) $r['net'] : 0;
    }
    unset($r);
    $cols = rep_product_cols(false) + ['qty' => ['Qty', 'qty'], 'free_qty' => ['Free', 'qty']];
    if ($profit) {
        $cols += ['net' => ['Net Sales', 'num'], 'cost' => ['Cost', 'num'], 'profit' => ['Gross Profit', 'num'], 'margin' => ['Margin', 'pct']];
        $sum = ['qty', 'free_qty', 'net', 'cost', 'profit'];
    } else {
        $cols += ['gross' => ['Gross Amount', 'num'], 'discount' => ['Discount', 'num'], 'net' => ['Net Amount', 'num'], 'avg_price' => ['Avg Price', 'num']];
        $sum = ['qty', 'free_qty', 'gross', 'discount', 'net'];
    }
    return ['cols' => $cols, 'rows' => $rows, 'group' => $dim !== '' ? $dim : null, 'sum' => $sum,
        'note' => $profit ? 'Cost is the weighted average purchase cost of each product. Sales returns are not deducted.' : ''];
}

/** Invoice lines one by one, grouped by product or by sales person. */
function sales_explicit(mysqli $db, array $f, $by)
{
    $rows = rep_query($db, 'rep_sales_lines', $f, ['use' => SALES_USE,
        'order' => $by === 'product' ? 'product_group, brand, model_caption, description, doc_date, doc_no' : 'sales_person, doc_date, doc_no']);
    foreach ($rows as &$r) {
        $r['group_key'] = $by === 'product' ? rep_product_key($r) : ($r['sales_person'] !== '' ? $r['sales_person'] : '(No sales person)');
        $r['product'] = rep_product_key($r);
    }
    unset($r);
    $cols = ['doc_date' => ['Date', 'date'], 'doc_no' => ['Invoice No'], 'party_name' => ['Customer']];
    if ($by !== 'product') {
        $cols['product'] = ['Product'];
    }
    $cols += ['store_name' => ['Store'], 'serial_imei' => ['Serial/IMEI'], 'quantity' => ['Qty', 'qty'], 'free_quantity' => ['Free', 'qty'], 'unit_price' => ['Unit Price', 'num'],
        'discount_amount' => ['Discount', 'num'], 'net_amount' => ['Net Amount', 'num']];
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'group_key', 'sum' => ['quantity', 'free_quantity', 'discount_amount', 'net_amount']];
}

/** Invoice headers, grouped by date, customer or branch. */
function sales_invoices(mysqli $db, array $f, $group)
{
    $rows = rep_query($db, 'rep_sales_invoices', $f, ['use' => SALES_HEAD_USE, 'order' => ($group === 'doc_date' ? '' : $group . ', ') . 'doc_date, doc_no']
            + rep_product_via($f, 'invoice_id', 'rep_sales_lines'));
    foreach ($rows as &$r) {
        $r['grp'] = $group === 'doc_date' ? inv_fmt_date($r['doc_date']) : ($r[$group] !== '' ? $r[$group] : '(None)');
    }
    unset($r);
    $cols = ['doc_date' => ['Date', 'date'], 'doc_no' => ['Invoice No'], 'party_name' => ['Customer'], 'mobile' => ['Mobile'], 'bill_type' => ['Bill Type'],
        'subtotal' => ['Sub Total', 'num'], 'discount_amount' => ['Discount', 'num'], 'vat_amount' => ['VAT & AIT', 'num'], 'extra_charges' => ['Extra', 'num'],
        'net_amount' => ['Net Payable', 'num'], 'received_amount' => ['Received', 'num'], 'balance_amount' => ['Due', 'num']];
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'grp', 'sum' => ['subtotal', 'discount_amount', 'vat_amount', 'extra_charges', 'net_amount', 'received_amount', 'balance_amount']];
}

/** Sold minus returned, per product, grouped by date or customer. */
function sales_net_product(mysqli $db, array $f, $group)
{
    $cols = SALES_PRODUCT . ', party_group, party_name, sales_person, approval_status, doc_date';
    $from = "(SELECT $cols, quantity+free_quantity AS sold_qty, net_amount AS sold_amt, 0 AS ret_qty, 0 AS ret_amt FROM rep_sales_lines
        UNION ALL SELECT $cols, 0, 0, quantity+free_quantity, line_total FROM rep_sales_return_lines) x";
    $rows = rep_query($db, $from, $f, [
        'select' => "$group, " . SALES_PRODUCT . ', SUM(sold_qty) AS sold_qty, SUM(sold_amt) AS sold_amt, SUM(ret_qty) AS ret_qty, SUM(ret_amt) AS ret_amt, '
            . 'SUM(sold_qty-ret_qty) AS net_qty, SUM(sold_amt-ret_amt) AS net_amt',
        'use' => SALES_USE, 'group' => "$group, " . SALES_PRODUCT, 'order' => "$group, product_group, brand, model_caption",
    ]);
    if ($group === 'doc_date') {
        foreach ($rows as &$r) {
            $r['doc_date'] = inv_fmt_date($r['doc_date']);
        }
        unset($r);
    }
    return ['cols' => rep_product_cols(false) + ['sold_qty' => ['Sold Qty', 'qty'], 'sold_amt' => ['Sales Amount', 'num'], 'ret_qty' => ['Return Qty', 'qty'],
        'ret_amt' => ['Return Amount', 'num'], 'net_qty' => ['Net Qty', 'qty'], 'net_amt' => ['Net Amount', 'num']],
        'rows' => $rows, 'group' => $group, 'sum' => ['sold_qty', 'sold_amt', 'ret_qty', 'ret_amt', 'net_qty', 'net_amt']];
}

/** Gross profit per invoice. */
function sales_profit_by_invoice(mysqli $db, array $f)
{
    $rows = rep_query($db, 'rep_sales_lines', $f, [
        'select' => 'doc_date, invoice_id, doc_no, party_name, SUM(net_amount) AS net, SUM(cost_amount) AS cost, SUM(net_amount-cost_amount) AS profit',
        'use' => SALES_USE, 'group' => 'doc_date, invoice_id, doc_no, party_name', 'order' => 'doc_date, doc_no',
    ]);
    foreach ($rows as &$r) {
        $r['margin'] = (float) $r['net'] != 0 ? (float) $r['profit'] * 100 / (float) $r['net'] : 0;
        $r['grp'] = inv_fmt_date($r['doc_date']);
    }
    unset($r);
    return ['cols' => ['doc_no' => ['Invoice No'], 'party_name' => ['Customer'], 'net' => ['Net Sales', 'num'], 'cost' => ['Cost', 'num'], 'profit' => ['Gross Profit', 'num'], 'margin' => ['Margin', 'pct']],
        'rows' => $rows, 'group' => 'grp', 'sum' => ['net', 'cost', 'profit'],
        'note' => 'Net sales are after line and invoice discounts, before VAT. Cost is the weighted average purchase cost.'];
}

/** Return headers grouped by date or customer. */
function sales_returns(mysqli $db, array $f, $group)
{
    $rows = rep_query($db, 'rep_sales_returns', $f, ['use' => SALES_HEAD_USE, 'order' => ($group === 'doc_date' ? '' : $group . ', ') . 'doc_date, doc_no']
            + rep_product_via($f, 'return_id', 'rep_sales_return_lines'));
    foreach ($rows as &$r) {
        $r['grp'] = $group === 'doc_date' ? inv_fmt_date($r['doc_date']) : $r[$group];
    }
    unset($r);
    return ['cols' => ['doc_date' => ['Date', 'date'], 'doc_no' => ['Return No'], 'invoice_no' => ['Invoice No'], 'party_name' => ['Customer'], 'reason' => ['Reason'],
        'total_quantity' => ['Qty', 'qty'], 'items_amount' => ['Items Value', 'num'], 'deduction_amount' => ['Deduction', 'num'], 'return_amount' => ['Return Amount', 'num'],
        'due_adjusted' => ['Due Adjusted', 'num'], 'refund_amount' => ['Refund', 'num']],
        'rows' => $rows, 'group' => 'grp', 'sum' => ['total_quantity', 'items_amount', 'deduction_amount', 'return_amount', 'due_adjusted', 'refund_amount']];
}

function sales_returns_by_product(mysqli $db, array $f)
{
    $rows = rep_query($db, 'rep_sales_return_lines', $f, ['use' => SALES_USE, 'order' => 'product_group, brand, model_caption, description, doc_date, doc_no']);
    foreach ($rows as &$r) {
        $r['group_key'] = rep_product_key($r);
        $r['item_condition'] = ucfirst($r['item_condition']);
    }
    unset($r);
    return ['cols' => ['doc_date' => ['Date', 'date'], 'doc_no' => ['Return No'], 'invoice_no' => ['Invoice No'], 'party_name' => ['Customer'], 'store_name' => ['Store'],
        'item_condition' => ['Condition'], 'quantity' => ['Qty', 'qty'], 'free_quantity' => ['Free', 'qty'], 'unit_value' => ['Unit Value', 'num'], 'line_total' => ['Amount', 'num']],
        'rows' => $rows, 'group' => 'group_key', 'sum' => ['quantity', 'free_quantity', 'line_total']];
}

/** One row per sales person: invoices, sales, returns, net and profit. */
function sales_by_sr(mysqli $db, array $f, $mode)
{
    $sales = rep_query($db, 'rep_sales_invoices', $f, [
        'select' => 'sales_person, COUNT(*) AS invoices, SUM(net_amount) AS net_amount, SUM(received_amount) AS received_amount, SUM(balance_amount) AS balance_amount',
        'use' => SALES_HEAD_USE, 'group' => 'sales_person', 'order' => 'sales_person',
    ] + rep_product_via($f, 'invoice_id', 'rep_sales_lines'));
    $rows = [];
    foreach ($sales as $r) {
        $rows[$r['sales_person']] = $r + ['returns' => 0, 'cost' => 0, 'profit' => 0, 'line_net' => 0];
    }
    $blank = ['invoices' => 0, 'net_amount' => 0, 'received_amount' => 0, 'balance_amount' => 0, 'returns' => 0, 'cost' => 0, 'profit' => 0, 'line_net' => 0];
    if ($mode === 'net') {
        $ret = rep_query($db, 'rep_sales_returns', $f, ['select' => 'sales_person, SUM(return_amount) AS returns', 'use' => SALES_HEAD_USE, 'group' => 'sales_person']
                + rep_product_via($f, 'return_id', 'rep_sales_return_lines'));
        foreach ($ret as $r) {
            $rows[$r['sales_person']] = ($rows[$r['sales_person']] ?? ['sales_person' => $r['sales_person']] + $blank);
            $rows[$r['sales_person']]['returns'] = $r['returns'];
        }
    }
    if ($mode === 'profit') {
        $lines = rep_query($db, 'rep_sales_lines', $f, ['select' => 'sales_person, SUM(net_amount) AS line_net, SUM(cost_amount) AS cost', 'use' => SALES_USE, 'group' => 'sales_person']);
        foreach ($lines as $r) {
            $rows[$r['sales_person']] = ($rows[$r['sales_person']] ?? ['sales_person' => $r['sales_person']] + $blank);
            $rows[$r['sales_person']]['line_net'] = $r['line_net'];
            $rows[$r['sales_person']]['cost'] = $r['cost'];
        }
    }
    ksort($rows);
    foreach ($rows as &$r) {
        $r['sales_person'] = $r['sales_person'] !== '' ? $r['sales_person'] : '(No sales person)';
        $r['net_sales'] = (float) $r['net_amount'] - (float) $r['returns'];
        $r['profit'] = (float) $r['line_net'] - (float) $r['cost'];
        $r['margin'] = (float) $r['line_net'] != 0 ? $r['profit'] * 100 / (float) $r['line_net'] : 0;
    }
    unset($r);
    $cols = ['sales_person' => ['Sales Person (SR)'], 'invoices' => ['Invoices', 'int']];
    if ($mode === 'net') {
        $cols += ['net_amount' => ['Sales', 'num'], 'returns' => ['Returns', 'num'], 'net_sales' => ['Net Sales', 'num']];
        $sum = ['invoices', 'net_amount', 'returns', 'net_sales'];
    } elseif ($mode === 'profit') {
        $cols += ['line_net' => ['Net Sales', 'num'], 'cost' => ['Cost', 'num'], 'profit' => ['Gross Profit', 'num'], 'margin' => ['Margin', 'pct']];
        $sum = ['invoices', 'line_net', 'cost', 'profit'];
    } else {
        $cols += ['net_amount' => ['Net Sales', 'num'], 'received_amount' => ['Received', 'num'], 'balance_amount' => ['Due', 'num']];
        $sum = ['invoices', 'net_amount', 'received_amount', 'balance_amount'];
    }
    return ['cols' => $cols, 'rows' => array_values($rows), 'sum' => $sum];
}

/** Customer statement: opening balance, then sales, receipts, returns and refunds with a running balance. */
function sales_statement(mysqli $db, array $f)
{
    $rows = rep_query($db, 'rep_sales_ledger', $f, ['date' => 'asof', 'use' => ['party_group', 'party', 'sr'], 'order' => 'party_name, doc_date, sort_order, ref_id']);
    $out = [];
    $bal = [];
    $opened = [];
    foreach ($rows as $r) {
        $k = $r['party_name'] . ($r['mobile'] !== '' ? ' (' . $r['mobile'] . ')' : '');
        $bal[$k] = $bal[$k] ?? 0.0;
        if ($r['doc_date'] < $f['from']) {
            $bal[$k] += (float) $r['debit'] - (float) $r['credit'];
            continue;
        }
        if (!isset($opened[$k])) {
            $opened[$k] = true;
            $out[] = ['group_key' => $k, 'txn_type' => 'Opening Balance', 'balance' => $bal[$k], 'opening' => true];
        }
        $bal[$k] += (float) $r['debit'] - (float) $r['credit'];
        $out[] = $r + ['group_key' => $k, 'balance' => $bal[$k]];
    }
    return ['cols' => ['doc_date' => ['Date', 'date'], 'doc_no' => ['Doc No'], 'txn_type' => ['Transaction'], 'debit' => ['Debit', 'num'], 'credit' => ['Credit', 'num'], 'balance' => ['Balance', 'num']],
        'rows' => $out, 'group' => 'group_key', 'sum' => ['debit', 'credit'],
        'note' => 'Sales and refunds are debits; payments received and returns are credits. Accounts vouchers are in the Accounts Register.'];
}

/** Invoices with any discount: line discounts plus invoice discount. */
function sales_rebate(mysqli $db, array $f)
{
    $rows = rep_query($db, 'rep_sales_invoices', $f, ['select' => '*, line_discount + discount_amount AS rebate', 'use' => SALES_HEAD_USE,
        'having' => 'rebate>0', 'order' => 'doc_date, doc_no'] + rep_product_via($f, 'invoice_id', 'rep_sales_lines'));
    foreach ($rows as &$r) {
        $r['grp'] = inv_fmt_date($r['doc_date']);
    }
    unset($r);
    return ['cols' => ['doc_no' => ['Invoice No'], 'party_name' => ['Customer'], 'sales_person' => ['Sales Person'], 'subtotal' => ['Sub Total', 'num'],
        'line_discount' => ['Line Discount', 'num'], 'discount_amount' => ['Invoice Discount', 'num'], 'rebate' => ['Total Rebate', 'num'], 'net_amount' => ['Net Payable', 'num']],
        'rows' => $rows, 'group' => 'grp', 'sum' => ['subtotal', 'line_discount', 'discount_amount', 'rebate', 'net_amount']];
}

/** Sales order lines; $pending limits to open orders with quantity still to bill or deliver. */
function sales_orders(mysqli $db, array $f, $group, $pending)
{
    $rows = rep_query($db, 'rep_sales_order_lines', $f, ['use' => SALES_USE, 'where' => $pending ? "status='open' AND pending_qty>0" : '',
        'order' => $group === 'product' ? 'product_group, brand, model_caption, description, doc_date, doc_no' : 'doc_date, doc_no, item_id']);
    foreach ($rows as &$r) {
        $r['grp'] = $group === 'product' ? rep_product_key($r) : inv_fmt_date($r['doc_date']);
        $r['product'] = rep_product_key($r);
        $r['pending_value'] = (float) $r['pending_qty'] * (float) $r['unit_price'];
        $r['status'] = ucfirst($r['status']);
    }
    unset($r);
    $cols = ['doc_date' => ['Date', 'date'], 'doc_no' => ['Order No'], 'party_name' => ['Customer'], 'delivery_date' => ['Delivery Date', 'date']];
    if ($group !== 'product') {
        $cols['product'] = ['Product'];
    }
    if ($pending) {
        $cols += ['quantity' => ['Order Qty', 'qty'], 'billed_qty' => ['Billed', 'qty'], 'challan_qty' => ['Challan', 'qty'], 'pending_qty' => ['Pending', 'qty'],
            'unit_price' => ['Unit Price', 'num'], 'pending_value' => ['Pending Value', 'num']];
        $sum = ['quantity', 'billed_qty', 'challan_qty', 'pending_qty', 'pending_value'];
    } else {
        $cols += ['status' => ['Status'], 'quantity' => ['Qty', 'qty'], 'unit_price' => ['Unit Price', 'num'], 'discount_amount' => ['Discount', 'num'], 'line_total' => ['Amount', 'num'],
            'pending_qty' => ['Pending', 'qty']];
        $sum = ['quantity', 'discount_amount', 'line_total', 'pending_qty'];
    }
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'grp', 'sum' => $sum];
}

$reports = [
    'product' => ['caption' => 'Product wise Sales', 'run' => fn($db, $f) => sales_by_product($db, $f)],
    'date' => ['caption' => 'Date Wise Sales', 'run' => fn($db, $f) => sales_invoices($db, $f, 'doc_date')],
    'customer' => ['caption' => 'Customer wise Sales', 'run' => fn($db, $f) => sales_invoices($db, $f, 'party_name')],
    'date_net_product' => ['caption' => 'Date wise Net Product Sales', 'run' => fn($db, $f) => sales_net_product($db, $f, 'doc_date')],
    'customer_net_product' => ['caption' => 'Customer wise Net Product Sales', 'run' => fn($db, $f) => sales_net_product($db, $f, 'party_name')],
    'date_profit' => ['caption' => 'Date Wise Gross Profit', 'run' => fn($db, $f) => sales_profit_by_invoice($db, $f)],
    'product_profit' => ['caption' => 'Product wise Gross Profit', 'run' => fn($db, $f) => sales_by_product($db, $f, '', true)],
    'date_return' => ['caption' => 'Date wise Sales Return', 'run' => fn($db, $f) => sales_returns($db, $f, 'doc_date')],
    'product_return' => ['caption' => 'Product wise Sales Return', 'run' => fn($db, $f) => sales_returns_by_product($db, $f)],
    'customer_return' => ['caption' => 'Customer wise Sales Return', 'run' => fn($db, $f) => sales_returns($db, $f, 'party_name')],
    'sr' => ['caption' => 'SR wise Sales', 'run' => fn($db, $f) => sales_by_sr($db, $f, 'sales')],
    'sr_net' => ['caption' => 'SR wise Net Sales', 'run' => fn($db, $f) => sales_by_sr($db, $f, 'net')],
    'sr_items' => ['caption' => 'SR wise Item Sales Explicit', 'run' => fn($db, $f) => sales_explicit($db, $f, 'sr')],
    'sr_profit' => ['caption' => 'SR wise Gross Profit', 'run' => fn($db, $f) => sales_by_sr($db, $f, 'profit')],
    'product_explicit' => ['caption' => 'Explicit Product wise Sales', 'run' => fn($db, $f) => sales_explicit($db, $f, 'product')],
    'statement' => ['caption' => 'Customer wise Statement', 'run' => fn($db, $f) => sales_statement($db, $f)],
    'branch' => ['caption' => 'Branch wise Sales', 'run' => fn($db, $f) => sales_invoices($db, $f, 'branch_name')],
    'all_sr_items' => ['caption' => 'All SR wise Item Sales', 'run' => fn($db, $f) => sales_by_product($db, $f, 'sales_person')],
    'rebate' => ['caption' => 'Date Wise Rebate', 'run' => fn($db, $f) => sales_rebate($db, $f)],
    'product_branch' => ['caption' => 'Product wise Sales with Branch', 'run' => fn($db, $f) => sales_by_product($db, $f, 'branch_name')],
    'order_pending' => ['caption' => 'Date Wise Pending Sales Order', 'run' => fn($db, $f) => sales_orders($db, $f, 'date', true)],
    'order' => ['caption' => 'Date Wise Sales Order', 'run' => fn($db, $f) => sales_orders($db, $f, 'date', false)],
    'order_pending_product' => ['caption' => 'Product Wise Pending Sales Order', 'run' => fn($db, $f) => sales_orders($db, $f, 'product', true)],
];

rep_register($conn, 'Sales Register', $reports, ['date', 'product', 'party', 'sr', 'approval'], ['party' => 'Customer']);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
