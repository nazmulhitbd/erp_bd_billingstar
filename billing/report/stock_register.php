<?php
/** Reports > Stock Register: current stock, date wise stock, product ledgers, serials and ageing from the inventory stock moves. */
require_once __DIR__ . '/report_common.php';

const STOCK_USE = ['product', 'store', 'branch', 'approval'];
const STOCK_PRODUCT_GROUP = 'product_id, product_code, product_group, brand, model_caption, description, unit_name';

/** Current stock as on Date To, optionally per store or branch, optionally with cost. */
function stock_current(mysqli $db, array $f, $by = '', $cost = false)
{
    $dim = $by !== '' ? $by . ', ' : '';
    $rows = rep_query($db, 'rep_stock_moves', $f, [
        'select' => $dim . STOCK_PRODUCT_GROUP . ', SUM(qty_in) AS qty_in, SUM(qty_out) AS qty_out, SUM(qty_in-qty_out) AS balance, '
            . 'MAX(avg_cost) AS avg_cost, ROUND(SUM(qty_in-qty_out)*MAX(avg_cost), 2) AS stock_value',
        'date' => 'asof', 'use' => STOCK_USE,
        'group' => $dim . STOCK_PRODUCT_GROUP, 'having' => 'ABS(balance)>0.0005',
        'order' => $dim . 'product_group, brand, model_caption, description',
    ]);
    $cols = rep_product_cols() + ['balance' => ['Quantity', 'qty']];
    $sum = ['balance'];
    if ($cost) {
        $cols += ['avg_cost' => ['Avg Cost', 'num'], 'stock_value' => ['Stock Value', 'num']];
        $sum[] = 'stock_value';
    }
    return ['cols' => $cols, 'rows' => $rows, 'group' => $by !== '' ? $by : null, 'sum' => $sum, 'date' => 'asof',
        'note' => $cost ? 'Cost is the weighted average of purchase and Stock Amendment IN prices.' : ''];
}

/** Current stock with one quantity column per branch or store. */
function stock_pivot(mysqli $db, array $f, $by)
{
    $raw = rep_query($db, 'rep_stock_moves', $f, [
        'select' => $by . ' AS dim, ' . STOCK_PRODUCT_GROUP . ', SUM(qty_in-qty_out) AS balance',
        'date' => 'asof', 'use' => STOCK_USE, 'group' => $by . ', ' . STOCK_PRODUCT_GROUP, 'having' => 'ABS(balance)>0.0005',
        'order' => 'product_group, brand, model_caption, description',
    ]);
    $dims = [];
    $rows = [];
    foreach ($raw as $r) {
        $dim = $r['dim'] !== '' ? $r['dim'] : '(No ' . ($by === 'branch_name' ? 'branch' : 'store') . ')';
        $dims[$dim] = $dims[$dim] ?? 'd' . count($dims);
        $id = (int) $r['product_id'];
        $rows[$id] = ($rows[$id] ?? $r + ['total' => 0]);
        $rows[$id][$dims[$dim]] = ($rows[$id][$dims[$dim]] ?? 0) + (float) $r['balance'];
        $rows[$id]['total'] += (float) $r['balance'];
    }
    ksort($dims);
    $cols = rep_product_cols();
    foreach ($dims as $name => $k) {
        $cols[$k] = [$name, 'qty'];
    }
    $cols['total'] = ['Total', 'qty'];
    return ['cols' => $cols, 'rows' => array_values($rows), 'sum' => array_merge(array_values($dims), ['total']), 'date' => 'asof'];
}

/** Pending sales order quantity per product (and store), keyed like the stock rows. */
function stock_pending_orders(mysqli $db, array $f, $byStore)
{
    $dim = $byStore ? 'store_name, ' : '';
    $rows = rep_query($db, 'rep_sales_order_lines', $f, [
        'select' => $dim . 'product_id, product_code, product_group, brand, model_caption, description, SUM(pending_qty) AS pending',
        'date' => 'asof', 'use' => $byStore ? ['product', 'store'] : ['product'],
        'where' => "status='open' AND pending_qty>0 AND product_id IS NOT NULL",
        'group' => $dim . 'product_id, product_code, product_group, brand, model_caption, description',
    ]);
    $out = [];
    foreach ($rows as $r) {
        $out[($byStore ? $r['store_name'] : '') . '|' . $r['product_id']] = $r;
    }
    return $out;
}

/** Opening, in, out and closing for the period; optional store split, cost and sales order basis. */
function stock_date_wise(mysqli $db, array $f, $byStore = false, $cost = false, $orders = false)
{
    $dim = $byStore ? 'store_name, ' : '';
    $rows = rep_query($db, 'rep_stock_moves', $f, [
        'select' => $dim . STOCK_PRODUCT_GROUP . ', SUM(CASE WHEN doc_date<? THEN qty_in-qty_out ELSE 0 END) AS opening, '
            . 'SUM(CASE WHEN doc_date>=? THEN qty_in ELSE 0 END) AS qty_in, SUM(CASE WHEN doc_date>=? THEN qty_out ELSE 0 END) AS qty_out, '
            . 'SUM(qty_in-qty_out) AS closing, MAX(avg_cost) AS avg_cost, ROUND(SUM(qty_in-qty_out)*MAX(avg_cost), 2) AS stock_value',
        'select_types' => 'sss', 'select_params' => [$f['from'], $f['from'], $f['from']],
        'date' => 'asof', 'use' => STOCK_USE, 'group' => $dim . STOCK_PRODUCT_GROUP,
        'having' => 'ABS(opening)>0.0005 OR qty_in>0 OR qty_out>0',
        'order' => $dim . 'product_group, brand, model_caption, description',
    ]);
    $cols = rep_product_cols() + ['opening' => ['Opening', 'qty'], 'qty_in' => ['Received', 'qty'], 'qty_out' => ['Issued', 'qty'], 'closing' => ['Closing', 'qty']];
    $sum = ['opening', 'qty_in', 'qty_out', 'closing'];
    if ($orders) {
        $pending = stock_pending_orders($db, $f, $byStore);
        foreach ($rows as &$r) {
            $k = ($byStore ? $r['store_name'] : '') . '|' . $r['product_id'];
            $r['pending'] = (float) ($pending[$k]['pending'] ?? 0);
            $r['available'] = (float) $r['closing'] - $r['pending'];
            unset($pending[$k]);
        }
        unset($r);
        // Ordered products with no stock movement still show, with a negative available quantity.
        foreach ($pending as $p) {
            $rows[] = $p + ['store_name' => $p['store_name'] ?? '', 'unit_name' => '', 'opening' => 0, 'qty_in' => 0, 'qty_out' => 0, 'closing' => 0,
                'avg_cost' => 0, 'stock_value' => 0, 'available' => -(float) $p['pending']];
        }
        $cols += ['pending' => ['Pending Order', 'qty'], 'available' => ['Available', 'qty']];
        array_push($sum, 'pending', 'available');
    }
    if ($cost) {
        $cols += ['avg_cost' => ['Avg Cost', 'num'], 'stock_value' => ['Closing Value', 'num']];
        $sum[] = 'stock_value';
    }
    if ($byStore) {
        usort($rows, fn($a, $b) => [$a['store_name'], $a['product_group'], $a['brand'], $a['model_caption']] <=> [$b['store_name'], $b['product_group'], $b['brand'], $b['model_caption']]);
    }
    return ['cols' => $cols, 'rows' => $rows, 'group' => $byStore ? 'store_name' : null, 'sum' => $sum];
}

/** Period movement split by transaction type. */
function stock_product_status(mysqli $db, array $f)
{
    $kinds = [
        'purchase' => ['Purchase', 'purchase', 'qty_in'], 'challan_return' => ['Challan Return', 'challan_return', 'qty_in'], 'stock_in' => ['Amendment IN', 'stock_in', 'qty_in'],
        'transfer_in' => ['Transfer In', 'stock_transfer', 'qty_in'], 'sales_return' => ['Sales Return', 'sales_return', 'qty_in'],
        'sales' => ['Sales', 'sales_invoice', 'qty_out'], 'challan' => ['Challan', 'challan', 'qty_out'], 'stock_out' => ['Amendment OUT', 'stock_out', 'qty_out'],
        'purchase_return' => ['Purchase Return', 'purchase_return', 'qty_out'], 'transfer_out' => ['Transfer Out', 'stock_transfer', 'qty_out'],
    ];
    $select = STOCK_PRODUCT_GROUP . ', SUM(CASE WHEN doc_date<? THEN qty_in-qty_out ELSE 0 END) AS opening';
    $params = [$f['from']];
    foreach ($kinds as $k => [, $type, $col]) {
        $select .= ", SUM(CASE WHEN doc_date>=? AND doc_type='$type' THEN $col ELSE 0 END) AS $k";
        $params[] = $f['from'];
    }
    $select .= ', SUM(qty_in-qty_out) AS closing';
    $rows = rep_query($db, 'rep_stock_moves', $f, [
        'select' => $select, 'select_types' => str_repeat('s', count($params)), 'select_params' => $params,
        'date' => 'asof', 'use' => STOCK_USE, 'group' => STOCK_PRODUCT_GROUP,
        'having' => 'ABS(opening)>0.0005 OR ABS(closing-opening)>0.0005 OR ' . implode('+', array_keys($kinds)) . '>0',
        'order' => 'product_group, brand, model_caption, description',
    ]);
    $cols = rep_product_cols(false) + ['opening' => ['Opening', 'qty']];
    foreach ($kinds as $k => [$label]) {
        $cols[$k] = [$label, 'qty'];
    }
    $cols['closing'] = ['Closing', 'qty'];
    return ['cols' => $cols, 'rows' => $rows, 'sum' => array_keys(array_slice($cols, 5))];
}

/** Product ledger with running balance; with $orders, pending sales orders are listed and reduce Available. */
function stock_ledger(mysqli $db, array $f, $byStore = false, $orders = false)
{
    $rows = rep_query($db, 'rep_stock_moves', $f, [
        'select' => 'doc_date, move_id AS seq, product_id, product_code, product_group, brand, model_caption, description, store_name, doc_no, doc_type, party_name, qty_in, qty_out, 0 AS qty_order',
        'date' => 'asof', 'use' => STOCK_USE,
    ]);
    foreach ($rows as &$r) {
        $r['txn_type'] = rep_doc_label($r['doc_type']);
    }
    unset($r);
    if ($orders) {
        $so = rep_query($db, 'rep_sales_order_lines', $f, [
            'select' => 'doc_date, item_id AS seq, product_id, product_code, product_group, brand, model_caption, description, store_name, doc_no, party_name, 0 AS qty_in, 0 AS qty_out, pending_qty AS qty_order',
            'date' => 'asof', 'use' => $byStore ? ['product', 'store'] : ['product'], 'where' => "status='open' AND pending_qty>0 AND product_id IS NOT NULL",
        ]);
        foreach ($so as $r) {
            $rows[] = $r + ['txn_type' => 'Sales Order (pending)', 'doc_type' => 'sales_order'];
        }
    }
    // Group by product id, labelled from the first row seen, so order lines join their product's ledger.
    $labels = [];
    foreach ($rows as $r) {
        $labels[(int) $r['product_id']] = $labels[(int) $r['product_id']] ?? rep_product_key($r);
    }
    $key = fn($r) => ($byStore ? $r['store_name'] . ' » ' : '') . $labels[(int) $r['product_id']];
    usort($rows, fn($a, $b) => [$key($a), $a['doc_date'], $a['doc_type'] === 'sales_order', (int) $a['seq']] <=> [$key($b), $b['doc_date'], $b['doc_type'] === 'sales_order', (int) $b['seq']]);
    $rows = rep_running_ledger($rows, $key, $f['from']);
    $cols = ['doc_date' => ['Date', 'date'], 'doc_no' => ['Doc No'], 'txn_type' => ['Transaction'], 'party_name' => ['Party'], 'store_name' => ['Store'],
        'qty_in' => ['In', 'qty'], 'qty_out' => ['Out', 'qty']];
    $sum = ['qty_in', 'qty_out'];
    if ($orders) {
        $cols['qty_order'] = ['Order', 'qty'];
        $sum[] = 'qty_order';
    }
    $cols['balance'] = ['Balance', 'qty'];
    if ($orders) {
        $cols['available'] = ['Available', 'qty'];
    }
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'group_key', 'sum' => $sum];
}

/** Stock on hand split into age buckets, assuming the oldest stock was issued first. */
function stock_ageing(mysqli $db, array $f)
{
    $balances = stock_current($db, $f)['rows'];
    $ins = rep_query($db, 'rep_stock_moves', $f, [
        'select' => 'product_id, doc_date, qty_in', 'date' => 'asof', 'use' => STOCK_USE,
        'where' => "qty_in>0 AND doc_type<>'stock_transfer'", 'order' => 'product_id, doc_date DESC, move_id DESC',
    ]);
    $receipts = [];
    foreach ($ins as $r) {
        $receipts[(int) $r['product_id']][] = $r;
    }
    $asOf = new DateTime($f['has_to'] ? $f['to'] : 'today');
    $buckets = ['a30' => [0, 30, '0-30 Days'], 'a60' => [31, 60, '31-60 Days'], 'a90' => [61, 90, '61-90 Days'], 'a180' => [91, 180, '91-180 Days'], 'a999' => [181, PHP_INT_MAX, 'Over 180 Days']];
    foreach ($balances as &$row) {
        $left = (float) $row['balance'];
        foreach ($buckets as $k => $b) {
            $row[$k] = 0.0;
        }
        foreach ($receipts[(int) $row['product_id']] ?? [] as $rc) {
            if ($left <= 0.0005) {
                break;
            }
            $take = min($left, (float) $rc['qty_in']);
            $days = (int) (new DateTime($rc['doc_date']))->diff($asOf)->format('%a');
            foreach ($buckets as $k => [$lo, $hi]) {
                if ($days >= $lo && $days <= $hi) {
                    $row[$k] += $take;
                }
            }
            $left -= $take;
        }
        // Stock with no receipt on record (opening data) counts as the oldest.
        $row['a999'] += max(0, $left);
    }
    unset($row);
    $cols = rep_product_cols() + ['balance' => ['Quantity', 'qty']];
    foreach ($buckets as $k => $b) {
        $cols[$k] = [$b[2], 'qty'];
    }
    return ['cols' => $cols, 'rows' => $balances, 'sum' => array_merge(['balance'], array_keys($buckets)), 'date' => 'asof'];
}

/** Serials now in stock with receive date, age and cost. */
function stock_serials(mysqli $db, array $f, $mode)
{
    $from = "(SELECT s.serial_id, s.serial_no, s.store_name, p.product_id, p.product_code, p.product_group, p.brand, p.model_caption, p.description, p.unit_name,
        (SELECT MIN(m.move_date) FROM inv_serial_moves m WHERE m.serial_id=s.serial_id AND m.direction='in') AS received_date,
        (SELECT MAX(m.move_date) FROM inv_serial_moves m WHERE m.serial_id=s.serial_id AND m.direction='in') AS last_in_date,
        (SELECT sm.unit_cost FROM inv_serial_moves m JOIN inv_stock_moves sm ON sm.doc_id=m.doc_id AND sm.product_id=s.product_id AND sm.qty_in>0
            WHERE m.serial_id=s.serial_id AND m.direction='in' AND m.doc_type IN ('purchase','stock_in') ORDER BY m.serial_move_id DESC LIMIT 1) AS unit_cost
        FROM inv_serials s JOIN sales_products p ON p.product_id=s.product_id WHERE s.in_stock=1) x";
    $rows = rep_query($db, $from, $f, ['date' => null, 'use' => ['product', 'store'], 'order' => 'product_group, brand, model_caption, store_name, serial_no']);
    $today = new DateTime('today');
    foreach ($rows as &$r) {
        $r['group_key'] = rep_product_key($r);
        $r['qty'] = 1;
        $r['age'] = $r['received_date'] ? (int) (new DateTime($r['received_date']))->diff($today)->format('%a') : null;
    }
    unset($r);
    $cols = ['serial_no' => ['Serial / IMEI'], 'store_name' => ['Store'], 'received_date' => ['Received', 'date']];
    $sum = ['qty'];
    if ($mode === 'ageing') {
        $cols += ['last_in_date' => ['Last In', 'date'], 'age' => ['Age (Days)', 'int']];
    } elseif ($mode === 'cost') {
        $cols['unit_cost'] = ['Cost', 'num'];
        $sum[] = 'unit_cost';
    }
    $cols['qty'] = ['Qty', 'qty'];
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'group_key', 'sum' => $sum, 'date' => 'now'];
}

$reports = [
    'current' => ['caption' => 'Current Stock (Combined)', 'run' => fn($db, $f) => stock_current($db, $f)],
    'current_cost' => ['caption' => 'Current Stock (Combined) with Cost', 'run' => fn($db, $f) => stock_current($db, $f, '', true)],
    'current_wh' => ['caption' => 'Current Stock ( Warehouse Wise)', 'run' => fn($db, $f) => stock_current($db, $f, 'store_name')],
    'current_wh_cost' => ['caption' => 'Current Stock ( Warehouse Wise) with Cost', 'run' => fn($db, $f) => stock_current($db, $f, 'store_name', true)],
    'current_serial' => ['caption' => 'Current Stock With Serial', 'run' => fn($db, $f) => stock_serials($db, $f, 'list')],
    'branch_summary' => ['caption' => 'Combined Current Stock (All BR Summary)', 'run' => fn($db, $f) => stock_pivot($db, $f, 'branch_name')],
    'ledger' => ['caption' => 'Product Ledger(Combined)', 'run' => fn($db, $f) => stock_ledger($db, $f)],
    'current_branch' => ['caption' => 'Current Stock (With Branch)', 'run' => fn($db, $f) => stock_current($db, $f, 'branch_name')],
    'ageing' => ['caption' => 'Current Stock With Ageing', 'run' => fn($db, $f) => stock_ageing($db, $f)],
    'serial_ageing' => ['caption' => 'Current Stock With Serial Ageing', 'run' => fn($db, $f) => stock_serials($db, $f, 'ageing')],
    'date_cost' => ['caption' => 'Date Wise Stock with Cost', 'run' => fn($db, $f) => stock_date_wise($db, $f, false, true)],
    'product_status' => ['caption' => 'Date Wise Product Status', 'run' => fn($db, $f) => stock_product_status($db, $f)],
    'serial_cost' => ['caption' => 'Current Stock With Serial Cost', 'run' => fn($db, $f) => stock_serials($db, $f, 'cost')],
    'ledger_order' => ['caption' => 'Product Ledger With Order(Combined)', 'run' => fn($db, $f) => stock_ledger($db, $f, false, true)],
    'date' => ['caption' => 'Date Wise Stock', 'run' => fn($db, $f) => stock_date_wise($db, $f)],
    'date_wh_cost' => ['caption' => 'Date Wise Stock Warehouse Wise With Cost', 'run' => fn($db, $f) => stock_date_wise($db, $f, true, true)],
    'all_store' => ['caption' => 'Current Stock All Store', 'run' => fn($db, $f) => stock_pivot($db, $f, 'store_name')],
    'date_so' => ['caption' => 'Date Wise Stock Sales Order Basis', 'run' => fn($db, $f) => stock_date_wise($db, $f, false, false, true)],
    'ledger_wh' => ['caption' => 'Product Ledger(Warehouse)', 'run' => fn($db, $f) => stock_ledger($db, $f, true)],
    'ledger_order_wh' => ['caption' => 'Product Ledger With Order(Warehouse)', 'run' => fn($db, $f) => stock_ledger($db, $f, true, true)],
    'date_so_wh_cost' => ['caption' => 'Date Wise Stock Sales Order Basis Warehouse Wise With Cost', 'run' => fn($db, $f) => stock_date_wise($db, $f, true, true, true)],
    'date_wh' => ['caption' => 'Date Wise Stock Warehouse Wise', 'run' => fn($db, $f) => stock_date_wise($db, $f, true)],
    'date_so_wh' => ['caption' => 'Date Wise Stock Sales Order Basis Warehouse Wise', 'run' => fn($db, $f) => stock_date_wise($db, $f, true, false, true)],
];

rep_register($conn, 'Stock Register', $reports, ['date', 'product', 'store', 'branch', 'approval']);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
