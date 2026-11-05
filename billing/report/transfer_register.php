<?php
/** Reports > Transfer Register: store to store transfers and stock amendments IN / OUT. */
require_once __DIR__ . '/report_common.php';

const TRF_USE = ['product', 'store', 'branch', 'approval'];

/** Stock transfer lines grouped by date, product, From store or To store. */
function trf_lines(mysqli $db, array $f, $group, $serials = false)
{
    $o = [];
    // The Store filter matches either side of a transfer.
    $use = TRF_USE;
    if ($f['store'] !== '') {
        $use = array_diff($use, ['store']);
        $o = ['where' => '(store_name=? OR store_to=?)', 'where_types' => 'ss', 'where_params' => [$f['store'], $f['store']]];
    }
    $rows = rep_doc_lines($db, $f, ['stock_transfer'], $group, $use, $o);
    $cols = ['doc_date' => ['Date', 'date'], 'doc_no' => ['Transfer No']];
    if ($group !== 'product') {
        $cols['product'] = ['Product'];
    }
    $cols += ['store_name' => ['From Store'], 'store_to' => ['To Store']];
    if ($serials) {
        $cols['serials'] = ['Serial / IMEI'];
    }
    $cols += ['total_qty' => ['Qty', 'qty'], 'doc_narration' => ['Narration']];
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'grp', 'sum' => ['total_qty']];
}

/** Quantity moved per From → To store pair and product. */
function trf_routes(mysqli $db, array $f)
{
    $rows = rep_query($db, 'rep_inv_lines', $f, [
        'select' => 'store_name, store_to, product_id, product_code, product_group, brand, model_caption, description, unit_name, COUNT(DISTINCT doc_id) AS docs, SUM(quantity+free_quantity) AS total_qty',
        'use' => TRF_USE, 'where' => "doc_type='stock_transfer'",
        'group' => 'store_name, store_to, product_id, product_code, product_group, brand, model_caption, description, unit_name',
        'order' => 'store_name, store_to, product_group, brand, model_caption',
    ]);
    foreach ($rows as &$r) {
        $r['route'] = $r['store_name'] . ' → ' . $r['store_to'];
    }
    unset($r);
    return ['cols' => rep_product_cols() + ['docs' => ['Transfers', 'int'], 'total_qty' => ['Qty', 'qty']], 'rows' => $rows, 'group' => 'route', 'sum' => ['docs', 'total_qty']];
}

/** Stock Amendment IN or OUT lines grouped by date or product. */
function trf_amendments(mysqli $db, array $f, $type, $group)
{
    $rows = rep_doc_lines($db, $f, [$type], $group, TRF_USE);
    $cols = ['doc_date' => ['Date', 'date'], 'doc_no' => ['Amendment No']];
    if ($group !== 'product') {
        $cols['product'] = ['Product'];
    }
    $cols += ['store_name' => ['Store'], 'serials' => ['Serial / IMEI'], 'total_qty' => ['Qty', 'qty'], 'unit_price' => ['Unit Cost', 'num'], 'line_total' => ['Value', 'num'],
        'doc_narration' => ['Reason / Narration']];
    return ['cols' => $cols, 'rows' => $rows, 'group' => 'grp', 'sum' => ['total_qty', 'line_total']];
}

/** Transfers and amendments still waiting for approval. */
function trf_pending(mysqli $db, array $f)
{
    $rows = rep_doc_lines($db, $f, ['stock_transfer', 'stock_in', 'stock_out'], 'doc_type', array_diff(TRF_USE, ['approval']), ['where' => "approval_status='pending'"]);
    foreach ($rows as &$r) {
        $r['grp'] = $r['doc_label'];
    }
    unset($r);
    return ['cols' => ['doc_date' => ['Date', 'date'], 'doc_no' => ['Doc No'], 'product' => ['Product'], 'store_name' => ['Store'], 'store_to' => ['To Store'], 'total_qty' => ['Qty', 'qty']],
        'rows' => $rows, 'group' => 'grp', 'sum' => ['total_qty']];
}

$reports = [
    'date' => ['caption' => 'Date Wise Stock Transfer', 'run' => fn($db, $f) => trf_lines($db, $f, 'date')],
    'product' => ['caption' => 'Product Wise Stock Transfer', 'run' => fn($db, $f) => trf_lines($db, $f, 'product')],
    'store_out' => ['caption' => 'Store Wise Transfer Out', 'run' => fn($db, $f) => trf_lines($db, $f, 'store_name')],
    'store_in' => ['caption' => 'Store Wise Transfer In', 'run' => fn($db, $f) => trf_lines($db, $f, 'store_to')],
    'serial' => ['caption' => 'Stock Transfer With Serial', 'run' => fn($db, $f) => trf_lines($db, $f, 'date', true)],
    'route' => ['caption' => 'Store to Store Transfer Summary', 'run' => fn($db, $f) => trf_routes($db, $f)],
    'date_in' => ['caption' => 'Date Wise Stock Amendment IN', 'run' => fn($db, $f) => trf_amendments($db, $f, 'stock_in', 'date')],
    'product_in' => ['caption' => 'Product Wise Stock Amendment IN', 'run' => fn($db, $f) => trf_amendments($db, $f, 'stock_in', 'product')],
    'date_out' => ['caption' => 'Date Wise Stock Amendment OUT', 'run' => fn($db, $f) => trf_amendments($db, $f, 'stock_out', 'date')],
    'product_out' => ['caption' => 'Product Wise Stock Amendment OUT', 'run' => fn($db, $f) => trf_amendments($db, $f, 'stock_out', 'product')],
    'pending' => ['caption' => 'Pending Approval (Transfer and Amendment)', 'run' => fn($db, $f) => trf_pending($db, $f)],
];

rep_register($conn, 'Transfer Register', $reports, ['date', 'product', 'store', 'branch', 'approval']);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
