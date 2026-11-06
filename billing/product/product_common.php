<?php
/**
 * Shared code for the Product Information menu: page shell, a generic add / edit / delete page for the setup lists
 * (category, classification, unit, warranty, transaction category, periodical warranty) and Sales / Purchase Price Fixing.
 * Tables: product/schema.sql (after sales, inventory and price_history schemas).
 */
require_once dirname(__DIR__) . '/inventory/inv_common.php';

const PM_MENU = [
    'category' => ['product_category.php', 'Product Category'], 'periodic' => ['periodical_warranty.php', 'Periodical Warranty'],
    'classification' => ['product_classification.php', 'Product Classification'], 'product' => ['product_info.php', 'Product Information'],
    'sales_price' => ['sales_price_fixing.php', 'Sales Price Fixing'], 'unit' => ['unit_info.php', 'Unit Information'], 'warranty' => ['warranty.php', 'Warranty'],
    'transaction' => ['transaction_category.php', 'Transaction Category'], 'purchase_price' => ['purchase_price_fixing.php', 'Purchase Price Fixing'],
];

function pm_ready(mysqli $db)
{
    return sales_tables_ready($db, ['sales_products', 'product_categories', 'product_classifications', 'product_units', 'product_warranties', 'product_periodic_warranties',
        'transaction_categories', 'product_price_fixings', 'product_price_fixing_lines', 'inv_price_history', 'inv_product_prices']) && inv_has_column($db, 'sales_products', 'purchase_price');
}

function pm_page_start($title, $active)
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . sales_h($title) . '</title>'
        . '<link rel="stylesheet" href="../sales/sales.css"><link rel="stylesheet" href="../inventory/inv.css"></head><body><div class="wrap"><nav class="acc-nav"><span><b>Product Information:</b>';
    foreach (PM_MENU as $key => [$href, $label]) {
        echo '<a href="' . $href . '"' . ($key === $active ? ' class="active"' : '') . '>' . sales_h($label) . '</a>';
    }
    echo '</span></nav><main class="panel"><fieldset class="box"><legend>' . sales_h($title) . '</legend>';
}

function pm_page_end($script = '')
{
    echo '</fieldset></main></div><script src="../sales/sales.js"></script>' . ($script !== '' ? '<script>' . $script . '</script>' : '') . '</body></html>';
}

/** Show the "not installed" notice and stop when the product tables are missing. */
function pm_require_ready(mysqli $db, $title, $active)
{
    if (pm_ready($db)) {
        return;
    }
    pm_page_start($title, $active);
    echo '<div class="errorbox"><strong>Product tables are not installed.</strong><br>Import <code>billing/price_history/schema.sql</code> and <code>billing/product/schema.sql</code>, then reload this page.</div>';
    pm_page_end();
    exit;
}

/** [value => label] lists for selects. */
function pm_options(mysqli $db, $kind)
{
    switch ($kind) {
        case 'category':
            return array_column(sales_rows($db, 'SELECT category_id, category_name FROM product_categories WHERE is_active=1 ORDER BY sort_order, category_name'), 'category_name', 'category_id');
        case 'group':
        case 'brand':
            $names = array_column(sales_rows($db, 'SELECT class_name FROM product_classifications WHERE class_type=? AND is_active=1 ORDER BY class_name', 's', [$kind]), 'class_name');
            return array_combine($names, $names) ?: [];
        case 'unit':
            $names = array_column(sales_rows($db, 'SELECT unit_name FROM product_units WHERE is_active=1 ORDER BY unit_name'), 'unit_name');
            return array_combine($names, $names) ?: [];
        case 'warranty':
            $names = array_column(sales_rows($db, 'SELECT warranty_name FROM product_warranties WHERE is_active=1 ORDER BY period_unit, period, warranty_name'), 'warranty_name');
            return array_combine($names, $names) ?: [];
        case 'warranty_id':
            return array_column(sales_rows($db, 'SELECT warranty_id, warranty_name FROM product_warranties WHERE is_active=1 ORDER BY period_unit, period, warranty_name'), 'warranty_name', 'warranty_id');
    }
    return [];
}

/* ---------------- Generic setup list page ---------------- */

/**
 * Add / edit / delete page for a setup table.
 * $cfg: title, active, table, pk, order, fields [name => [label, type (text|number|check|select|date|textarea), opts]]
 *   opts: max, required, options ([value => label] or callable($db)), default, step
 * fixed: [column, [value => tab label]] splits the list into tabs (e.g. Group / Brand)
 * unique: [columns...] that must not repeat; usage: fn($db, $row) reason it cannot be deleted ('' = can);
 * validate: fn($db, array &$data, ?array $old); after_save: fn($db, ?array $old, array $new); list: extra [key => [label, fn($row)]].
 * search: columns the search box looks in (adds the box; the list then shows at most 500 rows); list_fields: fields shown in the list (default all).
 */
function pm_master_page(mysqli $db, array $cfg)
{
    $self = basename($_SERVER['SCRIPT_NAME']);
    pm_require_ready($db, $cfg['title'], $cfg['active']);
    [$fixedCol, $tabs] = $cfg['fixed'] ?? [null, []];
    $tab = $fixedCol ? (array_key_exists($_REQUEST['tab'] ?? '', $tabs) ? (string) $_REQUEST['tab'] : array_key_first($tabs)) : '';
    $base = $self . ($fixedCol ? '?tab=' . urlencode($tab) : '');
    $join = $fixedCol ? '&' : '?';
    $options = function ($f) use ($db) {
        $o = $f[2]['options'] ?? [];
        return is_callable($o) ? $o($db) : $o;
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        sales_require_csrf($base);
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $db->begin_transaction();
            $old = $id ? sales_row($db, "SELECT * FROM {$cfg['table']} WHERE {$cfg['pk']}=? FOR UPDATE", 'i', [$id]) : null;
            if ($id && !$old) {
                throw new RuntimeException('Record not found.');
            }
            if (($_POST['action'] ?? '') === 'delete') {
                $why = isset($cfg['usage']) ? ($cfg['usage'])($db, $old) : '';
                if ($why !== '') {
                    throw new RuntimeException($why . ' Make it inactive instead.');
                }
                sales_exec($db, "DELETE FROM {$cfg['table']} WHERE {$cfg['pk']}=?", 'i', [$id])->close();
                $db->commit();
                sales_flash_redirect($base, 'success', 'Deleted.');
            }
            $data = [];
            foreach ($cfg['fields'] as $name => $f) {
                [$label, $type] = $f;
                $o = $f[2] ?? [];
                $raw = $_POST[$name] ?? '';
                if ($type === 'check') {
                    $data[$name] = !empty($raw) ? 1 : 0;
                } elseif ($type === 'number') {
                    if ($raw !== '' && !is_numeric($raw)) {
                        throw new RuntimeException($label . ' must be a number.');
                    }
                    $data[$name] = $raw === '' ? 0 : (isset($o['step']) ? round((float) $raw, 2) : (int) $raw);
                } elseif ($type === 'date') {
                    $d = sales_valid_date($raw, empty($o['required']));
                    if ($d === null) {
                        throw new RuntimeException('Enter a valid ' . strtolower($label) . '.');
                    }
                    $data[$name] = $d ?: null;
                } elseif ($type === 'select') {
                    $opts = $options($f);
                    $data[$name] = $raw === '' ? null : (array_key_exists($raw, $opts) ? $raw : null);
                    if ($raw !== '' && $data[$name] === null) {
                        throw new RuntimeException('Pick a valid ' . strtolower($label) . '.');
                    }
                } else {
                    $data[$name] = sales_cut(trim((string) $raw), $o['max'] ?? 190);
                }
                if (!empty($o['required']) && ($data[$name] === '' || $data[$name] === null)) {
                    throw new RuntimeException('Enter ' . strtolower($label) . '.');
                }
            }
            if ($fixedCol) {
                $data[$fixedCol] = $tab;
            }
            if (isset($cfg['validate'])) {
                ($cfg['validate'])($db, $data, $old);
            }
            if (!$old && $cfg['table'] === 'sales_products' && ($limit = licence_limit_error('products')) !== '') { // package limit
                throw new RuntimeException($limit);
            }
            if (!empty($cfg['unique'])) {
                $where = implode(' AND ', array_map(fn($c) => "$c=?", $cfg['unique']));
                $vals = array_map(fn($c) => $data[$c], $cfg['unique']);
                if (sales_row($db, "SELECT 1 FROM {$cfg['table']} WHERE $where AND {$cfg['pk']}<>?", str_repeat('s', count($vals)) . 'i', array_merge($vals, [$id]))) {
                    throw new RuntimeException('"' . implode(' / ', $vals) . '" already exists.');
                }
            }
            $types = implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), $data));
            if ($old) {
                sales_exec($db, "UPDATE {$cfg['table']} SET " . implode(', ', array_map(fn($c) => "$c=?", array_keys($data))) . " WHERE {$cfg['pk']}=?", $types . 'i', array_merge(array_values($data), [$id]))->close();
            } else {
                $id = sales_insert($db, "INSERT INTO {$cfg['table']} (" . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')', $types, array_values($data));
            }
            if (isset($cfg['after_save'])) {
                ($cfg['after_save'])($db, $old, sales_row($db, "SELECT * FROM {$cfg['table']} WHERE {$cfg['pk']}=?", 'i', [$id]));
            }
            $db->commit();
            sales_flash_redirect($base, 'success', $old ? 'Saved.' : 'Added.');
        } catch (RuntimeException $e) {
            try { $db->rollback(); } catch (Throwable $ignored) {}
            sales_flash_redirect($base . ($id ? $join . 'edit=' . $id : ''), 'error', $e->getMessage());
        }
    }

    $flash = sales_take_flash();
    $edit = isset($_GET['edit']) ? sales_row($db, "SELECT * FROM {$cfg['table']} WHERE {$cfg['pk']}=?", 'i', [(int) $_GET['edit']]) : null;
    $q = sales_cut(trim((string) ($_GET['q'] ?? '')), 120);
    $where = $fixedCol ? ["$fixedCol=?"] : [];
    $wTypes = $fixedCol ? 's' : '';
    $wParams = $fixedCol ? [$tab] : [];
    if (!empty($cfg['search']) && $q !== '') {
        $where[] = '(' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $cfg['search'])) . ')';
        $wTypes .= str_repeat('s', count($cfg['search']));
        $wParams = array_merge($wParams, array_fill(0, count($cfg['search']), '%' . $q . '%'));
    }
    $rows = sales_list_rows($db, "SELECT * FROM {$cfg['table']}" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $cfg['order'], $wTypes, $wParams);
    $listFields = array_intersect_key($cfg['fields'], array_flip($cfg['list_fields'] ?? array_keys($cfg['fields'])));
    pm_page_start($cfg['title'], $cfg['active']);
    echo sales_flash_html($flash);
    if ($fixedCol) {
        echo '<nav class="sales-nav">';
        foreach ($tabs as $k => $label) {
            echo '<a href="' . $self . '?tab=' . urlencode($k) . '"' . ($k === $tab ? ' class="active"' : '') . '>' . sales_h($label) . '</a>';
        }
        echo '</nav>';
    }
    echo '<form method="post" class="section product-form" style="margin-top:0">' . sales_csrf_field() . '<input type="hidden" name="id" value="' . (int) ($edit[$cfg['pk']] ?? 0) . '">'
        . ($fixedCol ? '<input type="hidden" name="tab" value="' . sales_h($tab) . '">' : '') . '<div class="hgrid">';
    foreach ($cfg['fields'] as $name => $f) {
        [$label, $type] = $f;
        $o = $f[2] ?? [];
        $v = $edit[$name] ?? ($o['default'] ?? '');
        $span = $type === 'textarea' ? ' s6' : ($type === 'check' ? ' s2' : '');
        echo '<div class="field' . $span . '"><label for="f_' . $name . '">' . sales_h($label) . (!empty($o['required']) ? ' *' : '') . '</label>';
        if ($type === 'check') {
            echo '<label><input class="check" type="checkbox" id="f_' . $name . '" name="' . $name . '" value="1"' . ((int) ($edit ? $v : ($o['default'] ?? 1)) ? ' checked' : '') . '> Yes</label>';
        } elseif ($type === 'select') {
            echo '<select id="f_' . $name . '" name="' . $name . '"><option value="">' . sales_h($o['placeholder'] ?? '-') . '</option>';
            foreach ($options($f) as $k => $label2) {
                echo '<option value="' . sales_h($k) . '"' . ((string) $k === (string) $v ? ' selected' : '') . '>' . sales_h($label2) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'textarea') {
            echo '<input id="f_' . $name . '" name="' . $name . '" maxlength="' . (int) ($o['max'] ?? 255) . '" value="' . sales_h($v) . '">';
        } else {
            echo '<input id="f_' . $name . '" name="' . $name . '" type="' . ($type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text')) . '"'
                . ($type === 'number' ? ' min="0" step="' . ($o['step'] ?? '1') . '"' : ' maxlength="' . (int) ($o['max'] ?? 190) . '"') . ' value="' . sales_h($v) . '">';
        }
        echo '</div>';
    }
    echo '<div class="field s2"><label>&nbsp;</label><div class="inline"><button class="btn primary">' . ($edit ? 'Save' : 'Add') . '</button>' . ($edit ? '<a class="btn" href="' . $base . '">New</a>' : '') . '</div></div></div></form>';

    if (!empty($cfg['search'])) {
        echo '<form method="get" class="list-search">' . ($fixedCol ? '<input type="hidden" name="tab" value="' . sales_h($tab) . '">' : '') . '<input class="input" name="q" value="' . sales_h($q) . '" placeholder="Search" style="max-width:320px"><button class="btn">Search</button>'
            . '</form>';
    }
    echo '<div class="table-scroll" style="max-height:600px"><table><thead><tr><th>SL</th>';
    foreach ($listFields as $name => $f) {
        echo '<th>' . sales_h($f[0]) . '</th>';
    }
    foreach ($cfg['list'] ?? [] as [$label]) {
        echo '<th>' . sales_h($label) . '</th>';
    }
    echo '<th>Edit</th><th>Delete</th></tr></thead><tbody>';
    echo $rows ? '' : '<tr><td colspan="' . (3 + count($listFields) + count($cfg['list'] ?? [])) . '" class="empty">Nothing yet.</td></tr>';
    foreach ($rows as $i => $r) {
        echo '<tr><td>' . ($i + 1) . '</td>';
        foreach ($listFields as $name => $f) {
            $v = $r[$name];
            if ($f[1] === 'check') {
                $v = (int) $v ? 'Yes' : 'No';
            } elseif ($f[1] === 'select') {
                $v = $options($f)[$v] ?? $v;
            } elseif ($f[1] === 'date') {
                $v = inv_fmt_date($v);
            }
            echo '<td>' . sales_h($v) . '</td>';
        }
        foreach ($cfg['list'] ?? [] as [, $fn]) {
            echo '<td>' . sales_h($fn($r)) . '</td>';
        }
        echo '<td><a class="btn small" href="' . $base . $join . 'edit=' . (int) $r[$cfg['pk']] . '">Edit</a></td><td><form method="post" onsubmit="return confirm(\'Delete this record?\')">' . sales_csrf_field()
            . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $r[$cfg['pk']] . '">' . ($fixedCol ? '<input type="hidden" name="tab" value="' . sales_h($tab) . '">' : '')
            . '<button class="btn small danger">Delete</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
    pm_page_end();
}

/* ---------------- Sales / Purchase Price Fixing ---------------- */

/**
 * Price fixing page. $kind sales: fields Sales Price, MRP, Retail A-C, Dealer A-C; purchase: Purchase Price.
 * Pick products by scope, pick a method (enter each price, % over average cost, % change, amount change), preview the
 * new prices, then Apply. Each run is saved with its lines and every change goes into inv_price_history.
 */
function pm_price_fixing_page(mysqli $db, $kind)
{
    $self = basename($_SERVER['SCRIPT_NAME']);
    $title = $kind === 'sales' ? 'Sales Price Fixing' : 'Purchase Price Fixing';
    $active = $kind === 'sales' ? 'sales_price' : 'purchase_price';
    pm_require_ready($db, $title, $active);
    $fields = $kind === 'sales'
        ? ['default_unit_price' => 'Sales Price', 'mrp' => 'MRP', 'retail_a' => 'Retail A', 'retail_b' => 'Retail B', 'retail_c' => 'Retail C', 'dealer_a' => 'Dealer A', 'dealer_b' => 'Dealer B', 'dealer_c' => 'Dealer C']
        : ['purchase_price' => 'Purchase Price'];
    $methods = ['manual' => 'Enter each price', 'percent_cost' => '% over average cost', 'percent_change' => '% up / down from current', 'amount_change' => 'Amount up / down from current'];
    $field = array_key_exists($_REQUEST['field'] ?? '', $fields) ? (string) $_REQUEST['field'] : array_key_first($fields);
    $method = array_key_exists($_REQUEST['method'] ?? '', $methods) ? (string) $_REQUEST['method'] : 'manual';
    $value = is_numeric($_REQUEST['value'] ?? '') ? round((float) $_REQUEST['value'], 2) : 0.0;
    $round = in_array($_REQUEST['round'] ?? '', ['1', '5', '10', '50', '100'], true) ? (int) $_REQUEST['round'] : 0;
    $s = inv_scope();
    [$where, $types, $params, $scopeText] = inv_scope_where($s);
    $current = $field === 'default_unit_price' || $field === 'purchase_price' ? "p.$field" : "COALESCE(pp.$field, 0)";
    $loadRows = fn() => sales_rows(
        $db,
        "SELECT p.product_id, p.product_code, p.product_group, p.brand, p.model_caption, p.description, $current AS current_price, "
            . "COALESCE((SELECT SUM(m.qty_in*m.unit_cost)/NULLIF(SUM(m.qty_in),0) FROM inv_stock_moves m WHERE m.product_id=p.product_id AND m.qty_in>0 AND m.doc_type IN ('purchase','stock_in')), p.purchase_price, 0) AS avg_cost "
            . "FROM sales_products p LEFT JOIN inv_product_prices pp ON pp.product_id=p.product_id WHERE $where ORDER BY p.product_group, p.brand, p.model_caption LIMIT 2000",
        $types,
        $params
    );
    $calc = function (array $r) use ($method, $value, $round) {
        $cur = (float) $r['current_price'];
        $new = match ($method) {
            'percent_cost' => (float) $r['avg_cost'] * (1 + $value / 100),
            'percent_change' => $cur * (1 + $value / 100),
            'amount_change' => $cur + $value,
            default => $cur,
        };
        if ($round > 0 && $method !== 'manual') {
            $new = round($new / $round) * $round;
        }
        return max(0.0, round($new, 2));
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
        sales_require_csrf($self);
        try {
            $manual = is_array($_POST['new'] ?? null) ? $_POST['new'] : [];
            $user = (int) ($GLOBALS['user_id'] ?? 0);
            $userName = sales_cut((string) ($GLOBALS['user_name'] ?? ''), 120);
            $db->begin_transaction();
            $changes = [];
            foreach ($loadRows() as $r) {
                $pid = (int) $r['product_id'];
                if ($method === 'manual') {
                    if (!isset($manual[$pid]) || trim((string) $manual[$pid]) === '') {
                        continue;
                    }
                    if (!is_numeric($manual[$pid]) || (float) $manual[$pid] < 0) {
                        throw new RuntimeException('Prices must be numbers of 0 or more (' . ($r['model_caption'] ?: $r['description']) . ').');
                    }
                    $new = round((float) $manual[$pid], 2);
                } else {
                    $new = $calc($r);
                }
                if (abs($new - (float) $r['current_price']) > 0.004) {
                    $changes[] = [$pid, (float) $r['current_price'], $new];
                }
            }
            if (!$changes) {
                throw new RuntimeException('No price would change.');
            }
            $no = docno_next($db, $kind === 'sales' ? 'PFS' : 'PFP'); // Settings > Document Numbering
            $fid = sales_insert($db, 'INSERT INTO product_price_fixings (fixing_no, fixing_date, price_kind, price_field, scope, scope_text, method, method_value, note, product_count, created_by, created_by_name) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'ssssssdsiis', [$no, $kind, $field, $s['scope'], $scopeText, $method, $value, sales_post_string('note', 255), count($changes), $user, $userName]);
            foreach ($changes as [$pid, $old, $new]) {
                if ($field === 'default_unit_price' || $field === 'purchase_price') {
                    sales_exec($db, "UPDATE sales_products SET $field=? WHERE product_id=?", 'di', [$new, $pid])->close();
                } else {
                    sales_exec($db, "INSERT INTO inv_product_prices (product_id, $field, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $field=VALUES($field), updated_by=VALUES(updated_by)", 'idi', [$pid, $new, $user])->close();
                }
                sales_insert($db, 'INSERT INTO product_price_fixing_lines (fixing_id, product_id, old_value, new_value) VALUES (?, ?, ?, ?)', 'iidd', [$fid, $pid, $old, $new]);
                sales_insert($db, 'INSERT INTO inv_price_history (product_id, price_field, old_value, new_value, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?)', 'isddis', [$pid, $field, $old, $new, $user, $userName]);
            }
            $db->commit();
            sales_flash_redirect($self . '?run=' . $fid, 'success', $no . ': ' . count($changes) . ' ' . $fields[$field] . (str_ends_with($fields[$field], 'Price') ? '' : ' price') . '(s) changed.');
        } catch (RuntimeException $e) {
            try { $db->rollback(); } catch (Throwable $ignored) {}
            sales_flash_redirect($self, 'error', $e->getMessage());
        }
    }

    $flash = sales_take_flash();
    pm_page_start($title, $active);
    echo sales_flash_html($flash);
    $show = isset($_REQUEST['preview']) || isset($_POST['action']);
    echo '<form method="post" id="pf">' . sales_csrf_field() . inv_scope_html($s, pm_options($db, 'group'), pm_options($db, 'brand'), true)
        . '<div class="radios"><label>Price <select name="field" class="input">' . implode('', array_map(fn($k, $l) => '<option value="' . $k . '"' . ($k === $field ? ' selected' : '') . '>' . $l . '</option>', array_keys($fields), $fields)) . '</select></label>'
        . '<label>Method <select name="method" class="input">' . implode('', array_map(fn($k, $l) => '<option value="' . $k . '"' . ($k === $method ? ' selected' : '') . '>' . $l . '</option>', array_keys($methods), $methods)) . '</select></label>'
        . '<label>Value <input name="value" type="number" step="0.01" value="' . sales_h($value) . '" style="width:110px" title="Percent or amount; use a minus for down"></label>'
        . '<label>Round to <select name="round" class="input"><option value="0">No rounding</option>' . implode('', array_map(fn($r) => '<option value="' . $r . '"' . ($round === $r ? ' selected' : '') . '>' . $r . '</option>', [1, 5, 10, 50, 100])) . '</select></label>'
        . '<label>Note <input name="note" maxlength="255" style="width:220px"></label>'
        . '<button class="btn" name="preview" value="1">Preview</button></div>';
    if ($show) {
        $rows = $loadRows();
        echo '<p class="muted">' . count($rows) . ' product(s) · ' . sales_h($scopeText) . ($method === 'manual' ? ' · Type the new prices; blank or unchanged boxes are skipped.' : '') . '</p>';
        echo '<div class="table-scroll" style="max-height:560px"><table><thead><tr><th>SL</th><th>Code</th><th>Group</th><th>Brand</th><th>Model No</th><th>Description</th><th class="num">Avg Cost</th><th class="num">Current ' . sales_h($fields[$field]) . '</th><th class="num">New ' . sales_h($fields[$field]) . '</th><th class="num">Change</th></tr></thead><tbody>';
        echo $rows ? '' : '<tr><td colspan="10" class="empty">No products in this scope.</td></tr>';
        foreach ($rows as $i => $r) {
            $new = $calc($r);
            $diff = $new - (float) $r['current_price'];
            echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h($r['product_code']) . '</td><td>' . sales_h($r['product_group']) . '</td><td>' . sales_h($r['brand']) . '</td><td>' . sales_h($r['model_caption']) . '</td><td>' . sales_h($r['description']) . '</td>'
                . '<td class="num">' . sales_money($r['avg_cost']) . '</td><td class="num">' . sales_money($r['current_price']) . '</td><td class="num">'
                . ($method === 'manual' ? '<input type="number" min="0" step="0.01" name="new[' . (int) $r['product_id'] . ']" value="" placeholder="' . sales_h(number_format((float) $r['current_price'], 2, '.', '')) . '" style="width:110px;text-align:right">' : sales_money($new))
                . '</td><td class="num">' . ($method === 'manual' ? '' : ($diff >= 0 ? '+' : '') . sales_money($diff)) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        if ($rows) {
            echo '<div class="toolbar" style="margin-top:8px"><button class="btn primary" name="action" value="apply" onclick="return confirm(\'Apply these prices?\')">Apply Prices</button></div>';
        }
    }
    echo '</form>';
    $runs = sales_rows($db, 'SELECT * FROM product_price_fixings WHERE price_kind=? ORDER BY fixing_id DESC LIMIT 20', 's', [$kind]);
    $run = isset($_GET['run']) ? sales_row($db, 'SELECT * FROM product_price_fixings WHERE fixing_id=? AND price_kind=?', 'is', [(int) $_GET['run'], $kind]) : null;
    if ($run) {
        $lines = sales_rows($db, 'SELECT l.*, p.product_code, p.model_caption, p.description FROM product_price_fixing_lines l JOIN sales_products p ON p.product_id=l.product_id WHERE l.fixing_id=? ORDER BY l.line_id', 'i', [(int) $run['fixing_id']]);
        echo '<section class="section"><div class="section-title">' . sales_h($run['fixing_no']) . ' · ' . sales_h($fields[$run['price_field']] ?? $run['price_field']) . ' · ' . sales_h($run['scope_text']) . ' · ' . sales_h($methods[$run['method']] ?? $run['method']) . '</div><table><thead><tr><th>Code</th><th>Model No</th><th>Description</th><th class="num">Old</th><th class="num">New</th></tr></thead><tbody>';
        foreach ($lines as $l) {
            echo '<tr><td>' . sales_h($l['product_code']) . '</td><td>' . sales_h($l['model_caption']) . '</td><td>' . sales_h($l['description']) . '</td><td class="num">' . sales_money($l['old_value']) . '</td><td class="num">' . sales_money($l['new_value']) . '</td></tr>';
        }
        echo '</tbody></table></section>';
    }
    echo '<section class="section"><div class="section-title">Recent price fixings</div><table><thead><tr><th>No</th><th>Date</th><th>Price</th><th>Scope</th><th>Method</th><th class="num">Value</th><th class="num">Products</th><th>By</th><th></th></tr></thead><tbody>';
    echo $runs ? '' : '<tr><td colspan="9" class="empty">None yet.</td></tr>';
    foreach ($runs as $r) {
        echo '<tr><td>' . sales_h($r['fixing_no']) . '</td><td>' . inv_fmt_date($r['fixing_date']) . '</td><td>' . sales_h($fields[$r['price_field']] ?? $r['price_field']) . '</td><td>' . sales_h($r['scope_text']) . '</td><td>' . sales_h($methods[$r['method']] ?? $r['method']) . '</td>'
            . '<td class="num">' . sales_money($r['method_value']) . '</td><td class="num">' . (int) $r['product_count'] . '</td><td>' . sales_h($r['created_by_name']) . '</td><td><a class="btn small" href="' . $self . '?run=' . (int) $r['fixing_id'] . '">Lines</a></td></tr>';
    }
    echo '</tbody></table></section>';
    pm_page_end(INV_SCOPE_JS);
}
