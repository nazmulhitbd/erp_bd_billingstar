<?php
require_once __DIR__ . '/rma_report.php';

$reports = [
    'combined' => ['caption' => 'Combined Stock'],
    'combined_cost' => ['caption' => 'Combined Stock With Cost'],
    'date_wise' => ['caption' => 'Date Wise Stock'],
    'date_wise_cost' => ['caption' => 'Date Wise Stock With Cost'],
    'ledger' => ['caption' => 'Product Ledger'],
    'warehouse' => ['caption' => 'Warehouse Wise Stock'],
    'warehouse_ledger' => ['caption' => 'Warehouse Wise Product Ledger'],
    'date_wise_warehouse' => ['caption' => 'Date Wise Stock Warehouse Wise'],
    'serial' => ['caption' => 'Combined Stock With Serial'],
    'warehouse_serial' => ['caption' => 'Warehouse Wise Stock With Serial'],
];

/** WHERE clause for the product scope and category filters. */
function rma_stock_where(array &$params, &$types)
{
    $where = ['1=1'];
    $scope = (string) ($_GET['scope'] ?? 'all');
    $group = trim((string) ($_GET['product_group'] ?? ''));
    $brand = trim((string) ($_GET['brand'] ?? ''));
    $model = trim((string) ($_GET['model_no'] ?? ''));
    $desc = [];
    if (in_array($scope, ['group', 'group_brand'], true) && $group !== '') {
        $where[] = 'product_group=?';
        $params[] = $group;
        $types .= 's';
        $desc[] = 'Group: ' . $group;
    }
    if (in_array($scope, ['brand', 'group_brand'], true) && $brand !== '') {
        $where[] = 'brand=?';
        $params[] = $brand;
        $types .= 's';
        $desc[] = 'Brand: ' . $brand;
    }
    if ($scope === 'model' && $model !== '') {
        $where[] = 'model_no=?';
        $params[] = $model;
        $types .= 's';
        $desc[] = 'Model: ' . $model;
    }
    $category = (string) ($_GET['category'] ?? 'all');
    if (in_array($category, ['faulty', 'good'], true)) {
        $where[] = 'category=?';
        $params[] = $category;
        $types .= 's';
        $desc[] = 'Category: ' . ucfirst($category);
    }
    return [implode(' AND ', $where), $desc ? implode(', ', $desc) : 'All Products'];
}

/** Add a running balance per key to ledger rows (already ordered). */
function rma_running_balance(array $rows, array $keyCols)
{
    $balance = [];
    foreach ($rows as &$row) {
        $key = implode('|', array_map(function ($c) use ($row) { return $row[$c]; }, $keyCols));
        $balance[$key] = ($balance[$key] ?? 0) + (float) $row['qty_in'] - (float) $row['qty_out'];
        $row['balance'] = $balance[$key];
        $row['txn_raw'] = $row['txn_date'];
        $row['txn_date'] = date('d/m/Y', strtotime($row['txn_date']));
    }
    unset($row);
    return $rows;
}

if (isset($_GET['report']) && isset($reports[$_GET['report']]) && rma_schema_ready()) {
    $key = $_GET['report'];
    [$from, $to, $dateLabel] = rma_report_dates();
    $params = [];
    $types = '';
    [$where, $scopeLabel] = rma_stock_where($params, $types);
    $subtitle = $scopeLabel . ' · ' . $dateLabel;
    $title = 'RMA ' . $reports[$key]['caption'];
    $product = ['product_group' => ['Group'], 'brand' => ['Brand'], 'model_no' => ['Model No'], 'category' => ['Category']];
    $avgCost = 'SUM(qty_in*unit_cost)/NULLIF(SUM(qty_in),0)';

    switch ($key) {
        case 'combined':
        case 'combined_cost':
        case 'warehouse':
            $store = $key === 'warehouse' ? 'store_name, ' : '';
            $rows = rma_rows(
                "SELECT {$store}product_group, brand, model_no, category, SUM(qty_in) AS qty_in, SUM(qty_out) AS qty_out, SUM(qty_in-qty_out) AS balance, "
                    . "COALESCE($avgCost,0) AS avg_cost, SUM(qty_in-qty_out)*COALESCE($avgCost,0) AS stock_value "
                    . "FROM rma_stock_ledger WHERE $where AND txn_date<=? GROUP BY {$store}product_group, brand, model_no, category "
                    . "ORDER BY {$store}product_group, brand, model_no, category",
                $types . 's', array_merge($params, [$to])
            );
            $cols = $product + ['qty_in' => ['Total In', 'qty'], 'qty_out' => ['Total Out', 'qty'], 'balance' => ['Balance', 'qty']];
            $sum = ['qty_in', 'qty_out', 'balance'];
            if ($key === 'combined_cost') {
                $cols += ['avg_cost' => ['Avg Cost', 'num'], 'stock_value' => ['Stock Value', 'num']];
                $sum[] = 'stock_value';
            }
            rma_report_render($title, $subtitle, $cols, $rows, $key === 'warehouse' ? 'store_name' : null, $sum);
            break;

        case 'date_wise':
        case 'date_wise_cost':
        case 'date_wise_warehouse':
            $store = $key === 'date_wise_warehouse' ? 'store_name, ' : '';
            $rows = rma_rows(
                "SELECT {$store}product_group, brand, model_no, category, "
                    . 'SUM(CASE WHEN txn_date<? THEN qty_in-qty_out ELSE 0 END) AS opening, '
                    . 'SUM(CASE WHEN txn_date>=? THEN qty_in ELSE 0 END) AS qty_in, '
                    . 'SUM(CASE WHEN txn_date>=? THEN qty_out ELSE 0 END) AS qty_out, '
                    . "SUM(qty_in-qty_out) AS closing, COALESCE($avgCost,0) AS avg_cost, SUM(qty_in-qty_out)*COALESCE($avgCost,0) AS stock_value "
                    . "FROM rma_stock_ledger WHERE $where AND txn_date<=? GROUP BY {$store}product_group, brand, model_no, category "
                    . "ORDER BY {$store}product_group, brand, model_no, category",
                'sss' . $types . 's', array_merge([$from, $from, $from], $params, [$to])
            );
            $cols = $product + ['opening' => ['Opening', 'qty'], 'qty_in' => ['In', 'qty'], 'qty_out' => ['Out', 'qty'], 'closing' => ['Closing', 'qty']];
            $sum = ['opening', 'qty_in', 'qty_out', 'closing'];
            if ($key === 'date_wise_cost') {
                $cols += ['avg_cost' => ['Avg Cost', 'num'], 'stock_value' => ['Closing Value', 'num']];
                $sum[] = 'stock_value';
            }
            rma_report_render($title, $subtitle, $cols, $rows, $key === 'date_wise_warehouse' ? 'store_name' : null, $sum);
            break;

        case 'ledger':
        case 'warehouse_ledger':
            $byStore = $key === 'warehouse_ledger';
            $order = $byStore ? 'store_name, model_no, category' : 'model_no, category';
            $rows = rma_rows(
                "SELECT ledger_id, txn_date, store_name, product_group, brand, model_no, category, serial_no, doc_type, doc_no, party_name, qty_in, qty_out "
                    . "FROM rma_stock_ledger WHERE $where AND txn_date<=? ORDER BY $order, txn_date, ledger_id",
                $types . 's', array_merge($params, [$to])
            );
            $rows = rma_running_balance($rows, $byStore ? ['store_name', 'model_no', 'category'] : ['model_no', 'category']);
            // Hide rows before the From date once the running balance has been worked out.
            $rows = array_values(array_filter($rows, function ($r) use ($from) {
                return $r['txn_raw'] >= $from;
            }));
            foreach ($rows as &$r) {
                $r['group_key'] = ($byStore ? $r['store_name'] . ' » ' : '') . $r['model_no'] . ' (' . $r['category'] . ')';
            }
            unset($r);
            $cols = ['txn_date' => ['Date'], 'doc_no' => ['Doc No'], 'doc_type' => ['Type'], 'party_name' => ['Party'], 'store_name' => ['Store'], 'serial_no' => ['Serial'],
                'qty_in' => ['In', 'qty'], 'qty_out' => ['Out', 'qty'], 'balance' => ['Balance', 'qty']];
            rma_report_render($title, $subtitle, $cols, $rows, 'group_key', ['qty_in', 'qty_out']);
            break;

        case 'serial':
        case 'warehouse_serial':
            $store = $key === 'warehouse_serial' ? 'store_name, ' : '';
            $rows = rma_rows(
                "SELECT {$store}product_group, brand, model_no, category, serial_no, SUM(qty_in-qty_out) AS balance "
                    . "FROM rma_stock_ledger WHERE $where AND txn_date<=? GROUP BY {$store}product_group, brand, model_no, category, serial_no "
                    . "HAVING balance>0 ORDER BY {$store}model_no, category, serial_no",
                $types . 's', array_merge($params, [$to])
            );
            $cols = $product + ['serial_no' => ['Serial No'], 'balance' => ['Qty', 'qty']];
            rma_report_render($title, $subtitle, $cols, $rows, $key === 'warehouse_serial' ? 'store_name' : 'model_no', ['balance']);
            break;
    }
    exit;
}

$ready = rma_schema_ready();
$groups = $ready ? array_column(rma_rows("SELECT DISTINCT product_group FROM rma_stock_ledger WHERE product_group<>'' ORDER BY 1"), 'product_group') : [];
$brands = $ready ? array_column(rma_rows("SELECT DISTINCT brand FROM rma_stock_ledger WHERE brand<>'' ORDER BY 1"), 'brand') : [];
$models = $ready ? array_column(rma_rows("SELECT DISTINCT model_no FROM rma_stock_ledger WHERE model_no<>'' ORDER BY 1"), 'model_no') : [];

rma_page_header('RMA Stock Register');
?>
<form id="reg-form" onsubmit="return false">
<div id="reg-filters" hidden>
  <div class="rma-filter">
    <label><input type="radio" name="scope" value="all" checked> All</label>
    <label><input type="radio" name="scope" value="group"> Group Wise</label>
    <label><input type="radio" name="scope" value="brand"> Brand Wise</label>
    <label><input type="radio" name="scope" value="group_brand"> Group and Brand Wise</label>
    <label><input type="radio" name="scope" value="model"> Model Wise</label>
  </div>
  <div class="rma-filter">
    <label data-scope-for="group group_brand" hidden>Group <input type="text" name="product_group" list="dl-groups"></label>
    <label data-scope-for="brand group_brand" hidden>Brand <input type="text" name="brand" list="dl-brands"></label>
    <label data-scope-for="model" hidden>Model <input type="text" name="model_no" list="dl-models"></label>
  </div>
  <div class="rma-filter">
    <label>Category <select name="category"><option value="all">All</option><option value="faulty">Faulty (received from customer)</option><option value="good">Good (replacement stock)</option></select></label>
    <label>All Date <input type="checkbox" id="all-date" name="all_date" value="1" checked></label>
    <label>Date From <input type="date" class="date-range" name="from" value="<?= date('Y-m-01') ?>"></label>
    <label>Date To <input type="date" class="date-range" name="to" value="<?= date('Y-m-d') ?>"></label>
  </div>
</div>
</form>
<datalist id="dl-groups"><?php foreach ($groups as $v): ?><option value="<?= rma_h($v) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-brands"><?php foreach ($brands as $v): ?><option value="<?= rma_h($v) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-models"><?php foreach ($models as $v): ?><option value="<?= rma_h($v) ?>"><?php endforeach; ?></datalist>
<?php
rma_report_picker($reports);
rma_page_footer('RMA.initRegister();');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
