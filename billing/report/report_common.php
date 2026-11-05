<?php
/**
 * Shared code for Reports > Stock / Sales / Purchase / Transfer / Challan Register.
 * A register page lists its reports. Print Preview and Print open the same file with ?report=key and the filter
 * values, which renders that report as a printable page. Data comes from the rep_* views in report/schema.sql.
 */
require_once dirname(__DIR__) . '/inventory/inv_common.php';

const REP_VIEWS = ['rep_product_cost', 'rep_stock_moves', 'rep_inv_docs', 'rep_inv_lines', 'rep_inv_line_usage', 'rep_sales_invoices', 'rep_sales_lines',
    'rep_sales_returns', 'rep_sales_return_lines', 'rep_sales_order_lines', 'rep_sales_ledger'];

/** Filter column names in the views, per filter group. */
const REP_FILTER_COLS = ['store' => 'store_name', 'branch' => 'branch_id', 'party_group' => 'party_group', 'party' => 'party_name', 'sr' => 'sales_person', 'approval' => 'approval_status'];

function rep_ready(mysqli $db)
{
    return inv_ready($db) && sales_tables_ready($db, REP_VIEWS);
}

/* ---------------- Filters ---------------- */

/** Filter values from the register form. Dates fall back to all dates when blank or invalid. */
function rep_filters()
{
    $g = fn($k) => sales_cut(trim((string) ($_GET[$k] ?? '')), 190);
    $all = !empty($_GET['all_date']);
    $from = $all ? '' : (string) sales_valid_date($g('from'), true);
    $to = $all ? '' : (string) sales_valid_date($g('to'), true);
    $scope = in_array($g('scope'), ['group', 'brand', 'group_brand', 'model'], true) ? $g('scope') : 'all';
    return [
        'from' => $from ?: '1000-01-01',
        'to' => $to ?: '9999-12-31',
        'has_from' => $from !== '',
        'has_to' => $to !== '',
        'scope' => $scope,
        'product_group' => in_array($scope, ['group', 'group_brand', 'model'], true) ? $g('product_group') : '',
        'brand' => in_array($scope, ['brand', 'group_brand', 'model'], true) ? $g('brand') : '',
        'model' => $scope === 'model' ? $g('model') : '',
        'description' => $scope === 'model' ? $g('description') : '',
        'store' => $g('store'),
        'branch' => perm_report_branch((int) ($_GET['branch'] ?? 0)),
        'party_group' => $g('party_group'),
        'party' => $g('party'),
        'sr' => $g('sr'),
        'approval' => in_array($g('approval'), ['approved', 'pending'], true) ? $g('approval') : '',
    ];
}

/**
 * WHERE conditions for the filters a report supports. $use lists filter groups: product, store, branch, party_group,
 * party, sr, approval. $prefix is the table alias ('' for none). Returns [conditions[], types, params].
 */
function rep_conditions(array $f, array $use, $prefix = '')
{
    $p = $prefix !== '' ? $prefix . '.' : '';
    $where = [];
    $types = '';
    $params = [];
    $add = function ($sql, $value, $type = 's') use (&$where, &$types, &$params) {
        $where[] = $sql;
        $types .= $type;
        $params[] = $value;
    };
    if (in_array('product', $use, true)) {
        if ($f['product_group'] !== '') {
            $add($p . 'product_group=?', $f['product_group']);
        }
        if ($f['brand'] !== '') {
            $add($p . 'brand=?', $f['brand']);
        }
        if ($f['model'] !== '') {
            $add($p . 'model_caption LIKE ?', '%' . $f['model'] . '%');
        }
        if ($f['description'] !== '') {
            $add($p . 'description LIKE ?', '%' . $f['description'] . '%');
        }
    }
    foreach (REP_FILTER_COLS as $key => $col) {
        if (!in_array($key, $use, true) || $f[$key] === '' || $f[$key] === 0) {
            continue;
        }
        if ($key === 'party') {
            $add($p . $col . ' LIKE ?', '%' . $f[$key] . '%');
        } else {
            $add($p . $col . '=?', $f[$key], $key === 'branch' ? 'i' : 's');
        }
    }
    return [$where, $types, $params];
}

/**
 * Run a report query over a view or sub-query.
 * $o: select, select_types/select_params (placeholders inside select), date ('range' | 'asof' | null), use (filter groups),
 * where (extra SQL) with where_types/where_params, group, having, order.
 */
function rep_query(mysqli $db, $from, array $f, array $o = [])
{
    [$where, $types, $params] = rep_conditions($f, $o['use'] ?? []);
    $date = array_key_exists('date', $o) ? $o['date'] : 'range';
    if ($date === 'range') {
        array_unshift($where, 'doc_date BETWEEN ? AND ?');
        $types = 'ss' . $types;
        array_unshift($params, $f['from'], $f['to']);
    } elseif ($date === 'asof') {
        array_unshift($where, 'doc_date<=?');
        $types = 's' . $types;
        array_unshift($params, $f['to']);
    }
    if (!empty($o['where'])) {
        $where[] = $o['where'];
        $types .= $o['where_types'] ?? '';
        $params = array_merge($params, $o['where_params'] ?? []);
    }
    $sql = 'SELECT ' . ($o['select'] ?? '*') . ' FROM ' . $from . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . (!empty($o['group']) ? ' GROUP BY ' . $o['group'] : '')
        . (!empty($o['having']) ? ' HAVING ' . $o['having'] : '')
        . (!empty($o['order']) ? ' ORDER BY ' . $o['order'] : '');
    return sales_rows($db, $sql, ($o['select_types'] ?? '') . $types, array_merge($o['select_params'] ?? [], $params));
}

/**
 * Product filter for header-level reports: "$idCol IN (lines of a matching product)". Returns rep_query options to merge,
 * or [] when no product filter is set.
 */
function rep_product_via(array $f, $idCol, $linesView)
{
    [$where, $types, $params] = rep_conditions($f, ['product']);
    if (!$where) {
        return [];
    }
    return ['where' => "$idCol IN (SELECT $idCol FROM $linesView WHERE " . implode(' AND ', $where) . ')', 'where_types' => $types, 'where_params' => $params];
}

/** Add the extra where (and its params) of $more to the rep_query options $o. */
function rep_and(array $o, array $more)
{
    if (empty($more['where'])) {
        return $o;
    }
    $o['where'] = !empty($o['where']) ? '(' . $o['where'] . ') AND ' . $more['where'] : $more['where'];
    $o['where_types'] = ($o['where_types'] ?? '') . ($more['where_types'] ?? '');
    $o['where_params'] = array_merge($o['where_params'] ?? [], $more['where_params'] ?? []);
    return $o;
}

/** Subtitle describing the active filters. */
function rep_filter_label(mysqli $db, array $f, $dateMode = 'range')
{
    $parts = [];
    foreach (['product_group' => 'Group', 'brand' => 'Brand', 'model' => 'Model', 'description' => 'Description', 'store' => 'Store', 'party_group' => 'Party Group', 'party' => 'Party', 'sr' => 'Sales Person'] as $k => $label) {
        if ($f[$k] !== '') {
            $parts[] = $label . ': ' . $f[$k];
        }
    }
    if ($f['branch']) {
        $parts[] = 'Branch: ' . inv_branch_name($db, $f['branch']);
    }
    if ($f['approval'] !== '') {
        $parts[] = ucfirst($f['approval']) . ' only';
    }
    if ($dateMode === 'asof') {
        $parts[] = $f['has_to'] ? 'As on ' . inv_fmt_date($f['to']) : 'As on today';
    } elseif ($dateMode === 'now') {
        $parts[] = 'Current position';
    } elseif ($f['has_from'] || $f['has_to']) {
        $parts[] = 'Date: ' . ($f['has_from'] ? inv_fmt_date($f['from']) : '…') . ' to ' . ($f['has_to'] ? inv_fmt_date($f['to']) : '…');
    } else {
        $parts[] = 'All Date';
    }
    return implode(' · ', $parts);
}

/* ---------------- Shared calculations ---------------- */

/** Product columns shown at the start of most product reports. */
function rep_product_cols($withUnit = true)
{
    $cols = ['product_code' => ['Code'], 'product_group' => ['Group'], 'brand' => ['Brand'], 'model_caption' => ['Model No/Caption'], 'description' => ['Description']];
    if ($withUnit) {
        $cols['unit_name'] = ['Unit'];
    }
    return $cols;
}

/** Text used as a group heading for a product row. */
function rep_product_key(array $r)
{
    $name = trim(($r['model_caption'] ?? '') !== '' ? $r['model_caption'] : ($r['description'] ?? ''));
    return trim(($r['product_code'] ?? '') . ' ' . $name . (($r['brand'] ?? '') !== '' ? ' (' . $r['brand'] . ')' : ''));
}

/**
 * Ledger rows with an opening balance row and a running balance per key. $rows must be ordered by key then date and
 * include rows before From (used only for the opening). Rows need doc_date, qty_in, qty_out and optional qty_order.
 */
function rep_running_ledger(array $rows, callable $key, $from)
{
    $out = [];
    $state = [];
    foreach ($rows as $r) {
        $k = $key($r);
        if (!isset($state[$k])) {
            $state[$k] = ['bal' => 0.0, 'order' => 0.0, 'opened' => false, 'tpl' => $r];
        }
        $s = &$state[$k];
        $in = (float) ($r['qty_in'] ?? 0);
        $outQty = (float) ($r['qty_out'] ?? 0);
        $order = (float) ($r['qty_order'] ?? 0);
        if ($r['doc_date'] < $from) {
            $s['bal'] += $in - $outQty;
            $s['order'] += $order;
            unset($s);
            continue;
        }
        if (!$s['opened']) {
            $s['opened'] = true;
            $out[] = ['group_key' => $k, 'doc_date' => '', 'doc_no' => '', 'txn_type' => 'Opening Balance', 'party_name' => '', 'store_name' => '',
                'qty_in' => null, 'qty_out' => null, 'qty_order' => null, 'balance' => $s['bal'], 'available' => $s['bal'] - $s['order'], 'opening' => true];
        }
        $s['bal'] += $in - $outQty;
        $s['order'] += $order;
        $r['group_key'] = $k;
        $r['balance'] = $s['bal'];
        $r['available'] = $s['bal'] - $s['order'];
        $out[] = $r;
        unset($s);
    }
    // Products with only an opening balance still show it.
    foreach ($state as $k => $s) {
        if (!$s['opened'] && abs($s['bal']) > 0.0005) {
            $out[] = ['group_key' => $k, 'doc_date' => '', 'doc_no' => '', 'txn_type' => 'Opening Balance', 'party_name' => '', 'store_name' => '',
                'qty_in' => null, 'qty_out' => null, 'qty_order' => null, 'balance' => $s['bal'], 'available' => $s['bal'] - $s['order'], 'opening' => true];
        }
    }
    usort($out, fn($a, $b) => strcmp($a['group_key'], $b['group_key']));
    return $out;
}

/**
 * Inventory document lines (rep_inv_lines, or a sub-query with the same columns) one by one.
 * $group: 'product', 'date' or a column name; rows get 'grp', 'product', 'total_qty' and 'doc_label'.
 * $o: extra rep_query options (where, where_types, where_params, select, having).
 */
function rep_doc_lines(mysqli $db, array $f, array $docTypes, $group, array $use, array $o = [], $from = 'rep_inv_lines')
{
    $order = ['product' => 'product_group, brand, model_caption, description, doc_date, doc_no, line_no', 'date' => 'doc_date, doc_no, line_no'][$group]
        ?? $group . ', doc_date, doc_no, line_no';
    $base = ['use' => $use, 'order' => $order, 'where' => 'doc_type IN (' . implode(',', array_fill(0, count($docTypes), '?')) . ')',
        'where_types' => str_repeat('s', count($docTypes)), 'where_params' => $docTypes];
    $rows = rep_query($db, $from, $f, rep_and(array_diff_key($o, ['where' => 1, 'where_types' => 1, 'where_params' => 1]) + $base, $o));
    foreach ($rows as &$r) {
        $r['product'] = rep_product_key($r);
        $r['total_qty'] = (float) $r['quantity'] + (float) $r['free_quantity'];
        $r['doc_label'] = rep_doc_label($r['doc_type']);
        if ($group === 'product') {
            $r['grp'] = $r['product'];
        } elseif ($group === 'date') {
            $r['grp'] = inv_fmt_date($r['doc_date']);
        } else {
            $r['grp'] = $r[$group] !== '' ? $r[$group] : '(None)';
        }
    }
    unset($r);
    return $rows;
}

/** Labels for document types in stock and ledger reports. */
function rep_doc_label($type)
{
    if (isset(INV_TYPES[$type])) {
        return INV_TYPES[$type]['title'];
    }
    return ucwords(str_replace('_', ' ', (string) $type));
}

/* ---------------- Printable report ---------------- */

/**
 * Print a report page. $cols: [key => [label, kind]] with kind '', 'num', 'qty', 'date', 'pct' or 'int'.
 * $group: row key for group headings and sub totals. $sum: keys to total.
 */
function rep_render(mysqli $db, $title, $subtitle, array $cols, array $rows, $group = null, array $sum = [], $note = '')
{
    $cols = perm_filter_report_cols($cols);
    $sum = array_values(array_intersect($sum, array_keys($cols)));
    $fmt = function ($kind, $v) {
        if ($v === null || $v === '') {
            return '';
        }
        switch ($kind) {
            case 'num':
                return sales_money($v);
            case 'qty':
                return rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
            case 'int':
                return number_format((float) $v);
            case 'pct':
                return number_format((float) $v, 2) . '%';
            case 'date':
                return sales_h(inv_fmt_date($v));
        }
        return nl2br(sales_h($v));
    };
    $isNum = fn($kind) => in_array($kind, ['num', 'qty', 'int', 'pct'], true);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . sales_h($title) . '</title><style>' . INV_PRINT_CSS
        . '.sub{text-align:center;color:#444;margin:-6px 0 8px}tr.group td{background:#e9eef5;font-weight:bold}tr.subtotal td{background:#f7f7f7;font-weight:bold}'
        . 'tr.grand td{background:#e2e2e2;font-weight:bold}tr.opening td{font-style:italic;color:#333}.empty{text-align:center;color:#777;padding:18px}.note{color:#555;font-size:11px}</style></head><body>';
    echo '<div class="bar"><button onclick="window.print()">Print</button></div>';
    echo inv_print_head($db, $title);
    echo '<div class="sub">' . sales_h($subtitle) . '</div>';
    echo '<table><thead><tr><th>SL</th>';
    foreach ($cols as $c) {
        echo '<th' . ($isNum($c[1] ?? '') ? ' class="num"' : '') . '>' . sales_h($c[0]) . '</th>';
    }
    echo '</tr></thead><tbody>';
    $cols = ['_sl' => ['']] + $cols;
    $totalRow = function ($label, array $totals, $class) use ($cols, $fmt) {
        $html = '<tr class="' . $class . '">';
        $first = true;
        foreach ($cols as $key => $c) {
            $html .= array_key_exists($key, $totals) ? '<td class="num">' . $fmt($c[1] ?? '', $totals[$key]) . '</td>' : '<td>' . ($first ? sales_h($label) : '') . '</td>';
            $first = false;
        }
        return $html . '</tr>';
    };

    if (!$rows) {
        echo '<tr><td colspan="' . count($cols) . '" class="empty">No data for the selected filters.</td></tr>';
    }
    $grand = array_fill_keys($sum, 0.0);
    $sub = $grand;
    $current = null;
    $sl = 0;
    foreach ($rows as $row) {
        if ($group !== null && (string) $row[$group] !== $current) {
            if ($current !== null && $sum) {
                echo $totalRow('Sub Total', $sub, 'subtotal');
            }
            $current = (string) $row[$group];
            $sub = array_fill_keys($sum, 0.0);
            $sl = 0;
            echo '<tr class="group"><td colspan="' . count($cols) . '">' . sales_h($current !== '' ? $current : '(blank)') . '</td></tr>';
        }
        $opening = !empty($row['opening']);
        echo '<tr' . ($opening ? ' class="opening"' : '') . '><td>' . ($opening ? '' : ++$sl) . '</td>';
        foreach ($cols as $key => $c) {
            if ($key !== '_sl') {
                echo '<td' . ($isNum($c[1] ?? '') ? ' class="num"' : '') . '>' . $fmt($c[1] ?? '', $row[$key] ?? '') . '</td>';
            }
        }
        echo '</tr>';
        if (!$opening) {
            foreach ($sum as $k) {
                $grand[$k] += (float) ($row[$k] ?? 0);
                $sub[$k] += (float) ($row[$k] ?? 0);
            }
        }
    }
    if ($rows && $group !== null && $sum) {
        echo $totalRow('Sub Total', $sub, 'subtotal');
    }
    if ($rows && $sum) {
        echo $totalRow('Grand Total', $grand, 'grand');
    }
    echo '</tbody></table>';
    if ($note !== '') {
        echo '<p class="note">' . sales_h($note) . '</p>';
    }
    echo '<p class="note">Printed ' . sales_h(date('d/m/Y h:i A')) . ' by ' . sales_h($GLOBALS['user_name'] ?? '') . '</p>';
    if (!empty($_GET['auto'])) {
        echo '<script>window.addEventListener("load",function(){window.print()})</script>';
    }
    echo '</body></html>';
}

/* ---------------- Register page ---------------- */

/**
 * Show a register: run ?report=key when given, otherwise the filter form and report list.
 * Each report: ['caption' => ..., 'run' => fn(mysqli, array $f) => ['cols', 'rows', 'group', 'sum', 'date', 'note']].
 * $form lists the filter blocks: date, product, store, branch, party (with $labels['party']), sr, approval.
 */
function rep_register(mysqli $db, $title, array $reports, array $form, array $labels = [])
{
    if (!rep_ready($db)) {
        rep_page_start($title);
        echo '<div class="errorbox"><strong>Report views are not installed yet.</strong><br>Import the sales and inventory SQL files (see <code>billing/inventory/README.md</code>), then <code>billing/report/schema.sql</code>, and reload this page.</div>';
        rep_page_end();
        exit;
    }
    $reports = perm_filter_reports('report/' . basename((string) $_SERVER['SCRIPT_NAME']), $reports);
    $keys = array_keys($reports);
    $key = $_GET['report'] ?? null;
    if ($key !== null) {
        if (!isset($reports[$key])) {
            http_response_code(404);
            exit('Unknown report.');
        }
        $f = rep_filters();
        $r = $reports[$key]['run']($db, $f) + ['group' => null, 'sum' => [], 'date' => 'range', 'note' => ''];
        rep_render($db, $reports[$key]['caption'], rep_filter_label($db, $f, $r['date']), $r['cols'], $r['rows'], $r['group'], $r['sum'], $r['note']);
        exit;
    }

    rep_page_start($title);
    rep_filter_form($db, $form, $labels);
    echo '<div class="toolbar" style="margin:10px 0"><button type="button" class="btn" id="open-ticked">Open ticked reports in new tabs</button></div>';
    echo '<section id="preview" class="section" hidden><div class="toolbar" style="align-items:center"><b id="preview-title"></b><span id="preview-status" class="muted"></span><span style="flex:1"></span>'
        . '<button type="button" class="btn primary" id="preview-print">Print</button><a class="btn" id="preview-tab" target="_blank" rel="noopener">Open in new tab</a><button type="button" class="btn" id="preview-close">Close preview</button></div>'
        . '<iframe id="preview-frame" title="Report preview"></iframe></section>';
    echo '<div class="table-scroll rep-list"><table><thead><tr><th>SL</th><th>Caption</th><th></th><th>Report SL</th><th>Preview</th><th>Print</th></tr></thead><tbody>';
    foreach ($keys as $i => $k) {
        $n = $i + 1;
        $cap = sales_h($reports[$k]['caption']);
        echo '<tr><td>' . $n . '</td><td>' . $cap . '</td><td class="center"><input class="check" type="checkbox" data-tick="' . sales_h($k) . '"></td><td>' . $n . '</td>'
            . '<td><button type="button" class="btn small" data-report="' . sales_h($k) . '" data-caption="' . $n . '. ' . $cap . '">Print Preview</button></td>'
            . '<td><button type="button" class="btn small" data-report="' . sales_h($k) . '" data-caption="' . $n . '. ' . $cap . '" data-print="1">Print</button></td></tr>';
    }
    echo '</tbody></table></div>';
    rep_page_end();
}

function rep_page_start($title)
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . sales_h($title) . '</title><link rel="stylesheet" href="../sales/sales.css"><link rel="stylesheet" href="../inventory/inv.css"><link rel="stylesheet" href="report.css"></head>';
    echo '<body><div class="wrap"><main class="panel"><fieldset class="box"><legend>' . sales_h($title) . '</legend>';
}

function rep_page_end()
{
    echo '</fieldset></main></div><script src="report.js"></script></body></html>';
}

/** Distinct non-blank values of a column, for the type-ahead lists. */
function rep_distinct(mysqli $db, $table, $col, $where = '1=1')
{
    return array_column(sales_rows($db, "SELECT DISTINCT $col AS v FROM $table WHERE $col<>'' AND $where ORDER BY 1 LIMIT 3000"), 'v');
}

function rep_datalist($id, array $values)
{
    echo '<datalist id="' . $id . '">';
    foreach ($values as $v) {
        echo '<option value="' . sales_h($v) . '">';
    }
    echo '</datalist>';
}

function rep_filter_form(mysqli $db, array $form, array $labels)
{
    $today = date('Y-m-d');
    echo '<form id="rep-filters" onsubmit="return false">';
    if (in_array('date', $form, true)) {
        echo '<div class="rep-row"><label><input class="check" type="checkbox" name="all_date" value="1" id="all-date"> All Date</label>'
            . '<label>Date From <input type="date" name="from" class="rep-date" value="' . $today . '"></label>'
            . '<label>Date To <input type="date" name="to" class="rep-date" value="' . $today . '"></label>'
            . '<span class="muted">Current stock reports use Date To as the "as on" date.</span></div>';
    }
    if (in_array('product', $form, true)) {
        echo '<div class="rep-row radios">';
        foreach (['all' => 'All', 'group' => 'Group Wise', 'brand' => 'Brand Wise', 'group_brand' => 'Group and Brand Wise', 'model' => 'Model Wise'] as $v => $label) {
            echo '<label><input type="radio" name="scope" value="' . $v . '"' . ($v === 'all' ? ' checked' : '') . '> ' . $label . '</label>';
        }
        echo '</div><div class="rep-grid">'
            . '<label data-scope="group group_brand model" hidden>Product Group <input type="text" name="product_group" list="dl-group" autocomplete="off"></label>'
            . '<label data-scope="brand group_brand model" hidden>Product Brand <input type="text" name="brand" list="dl-brand" autocomplete="off"></label>'
            . '<label data-scope="model" hidden>Model No/Caption <input type="text" name="model" list="dl-model" autocomplete="off"></label>'
            . '<label data-scope="model" hidden>Description <input type="text" name="description" autocomplete="off"></label></div>';
        rep_datalist('dl-group', rep_distinct($db, 'sales_products', 'product_group'));
        rep_datalist('dl-brand', rep_distinct($db, 'sales_products', 'brand'));
        rep_datalist('dl-model', rep_distinct($db, 'sales_products', 'model_caption'));
    }
    echo '<div class="rep-grid">';
    if (in_array('store', $form, true)) {
        echo '<label>Store <select name="store"><option value="">All</option>';
        foreach (inv_stores($db) as $s) {
            echo '<option>' . sales_h($s) . '</option>';
        }
        echo '</select></label>';
    }
    if (in_array('branch', $form, true)) {
        echo '<label>Branch <select name="branch"><option value="0">All</option>';
        foreach (array_filter(inv_branches($db, false), fn($b) => perm_branch_allowed($b['branch_id'])) as $b) {
            echo '<option value="' . (int) $b['branch_id'] . '">' . sales_h($b['branch_name']) . '</option>';
        }
        echo '</select></label>';
    }
    if (in_array('party', $form, true)) {
        $party = $labels['party'] ?? 'Customer';
        $table = $labels['party_table'] ?? 'rep_sales_invoices';
        echo '<label>' . sales_h($party) . ' Group <input type="text" name="party_group" list="dl-party-group" autocomplete="off"></label>';
        echo '<label>' . sales_h($party) . ' Name <input type="text" name="party" list="dl-party" autocomplete="off"></label>';
        rep_datalist('dl-party-group', rep_distinct($db, $table, 'party_group', $labels['party_where'] ?? '1=1'));
        rep_datalist('dl-party', rep_distinct($db, $table, 'party_name', $labels['party_where'] ?? '1=1'));
    }
    if (in_array('sr', $form, true)) {
        echo '<label>Sales Person (SR) <input type="text" name="sr" list="dl-sr" autocomplete="off"></label>';
        rep_datalist('dl-sr', rep_distinct($db, 'rep_sales_invoices', 'sales_person'));
    }
    if (in_array('approval', $form, true)) {
        echo '<label>Approval <select name="approval"><option value="">All</option><option value="approved">Approved</option><option value="pending">Pending</option></select></label>';
    }
    echo '</div></form>';
}
