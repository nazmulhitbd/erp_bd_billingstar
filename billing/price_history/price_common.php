<?php
/**
 * Shared code for Sales Price View and Sales Price MRP View: filters, the editable price grid, saving changed prices
 * (inv_product_prices, and sales_products.default_unit_price for the Sales Price) with every change logged in
 * inv_price_history, and the per-product change history. View and table: price_history/schema.sql.
 */
require_once dirname(__DIR__) . '/inventory/inv_common.php';

const PH_LABELS = ['default_unit_price' => 'Sales Price', 'retail_a' => 'Retail A', 'retail_b' => 'Retail B', 'retail_c' => 'Retail C', 'dealer_a' => 'Dealer A',
    'dealer_b' => 'Dealer B', 'dealer_c' => 'Dealer C', 'mrp' => 'MRP', 'lower_price' => 'Lower Price', 'higher_price' => 'Higher Price'];
/** Column of product_price_list that holds each editable price. */
const PH_LIST_COL = ['default_unit_price' => 'sales_price'];

function ph_ready(mysqli $db)
{
    return sales_tables_ready($db, ['product_price_list', 'inv_price_history', 'inv_product_prices']);
}

/** Products of the chosen scope (Group / Brand / Group and Brand / Model Wise) with their prices, costs and stock. */
function ph_rows(mysqli $db, array $scope)
{
    [$where, $types, $params] = inv_scope_where($scope);
    $rows = sales_rows($db, "SELECT * FROM product_price_list p WHERE $where ORDER BY p.product_group, p.brand, p.model_caption, p.description LIMIT 3000", $types, $params);
    foreach ($rows as &$r) {
        // Margin of the sales price over average cost; blank when either is unknown.
        $r['margin'] = (float) $r['sales_price'] > 0 && (float) $r['avg_cost'] > 0 ? ((float) $r['sales_price'] - (float) $r['avg_cost']) * 100 / (float) $r['sales_price'] : null;
    }
    unset($r);
    return $rows;
}

/**
 * Save posted prices[product_id][field] for the allowed fields. Only changed values are written and logged.
 * Returns the number of changed prices.
 */
function ph_save(mysqli $db, array $fields)
{
    $posted = is_array($_POST['prices'] ?? null) ? $_POST['prices'] : [];
    $user = (int) ($GLOBALS['user_id'] ?? 0);
    $userName = sales_cut((string) ($GLOBALS['user_name'] ?? ''), 120);
    $changed = 0;
    $db->begin_transaction();
    try {
        foreach ($posted as $pid => $values) {
            $pid = (int) $pid;
            if ($pid <= 0 || !is_array($values)) {
                continue;
            }
            $cur = sales_row($db, 'SELECT * FROM product_price_list WHERE product_id=?', 'i', [$pid]);
            if (!$cur) {
                continue;
            }
            $new = [];
            foreach ($fields as $field) {
                if (!array_key_exists($field, $values) || trim((string) $values[$field]) === '') {
                    continue;
                }
                if (!is_numeric($values[$field]) || (float) $values[$field] < 0) {
                    throw new RuntimeException('Prices must be numbers of 0 or more (' . ($cur['model_caption'] ?: $cur['description']) . ', ' . PH_LABELS[$field] . ').');
                }
                $value = round((float) $values[$field], 2);
                $old = round((float) $cur[PH_LIST_COL[$field] ?? $field], 2);
                if (abs($value - $old) > 0.004) {
                    $new[$field] = [$old, $value];
                }
            }
            $lower = $new['lower_price'][1] ?? (float) $cur['lower_price'];
            $higher = $new['higher_price'][1] ?? (float) $cur['higher_price'];
            if ($lower > 0 && $higher > 0 && $lower > $higher) {
                throw new RuntimeException('Lower Price is above Higher Price for ' . ($cur['model_caption'] ?: $cur['description']) . '.');
            }
            if (!$new) {
                continue;
            }
            if (isset($new['default_unit_price'])) {
                sales_exec($db, 'UPDATE sales_products SET default_unit_price=? WHERE product_id=?', 'di', [$new['default_unit_price'][1], $pid])->close();
            }
            $levels = array_diff_key($new, ['default_unit_price' => 1]);
            if ($levels) {
                $cols = array_keys($levels);
                $vals = array_map(fn($v) => $v[1], array_values($levels));
                sales_exec(
                    $db,
                    'INSERT INTO inv_product_prices (product_id, ' . implode(', ', $cols) . ', updated_by) VALUES (?, ' . implode(', ', array_fill(0, count($cols), '?')) . ', ?) '
                        . 'ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c=VALUES($c)", $cols)) . ', updated_by=VALUES(updated_by)',
                'i' . str_repeat('d', count($cols)) . 'i',
                array_merge([$pid], $vals, [$user])
            )->close();
        }
        foreach ($new as $field => [$old, $value]) {
            sales_insert($db, 'INSERT INTO inv_price_history (product_id, price_field, old_value, new_value, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?)', 'isddis', [$pid, $field, $old, $value, $user, $userName]);
            $changed++;
        }
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}
return $changed;
}

/** Change log of one product (?history=ID). */
function ph_history_html(mysqli $db, $productId)
{
    $p = sales_row($db, 'SELECT product_code, model_caption, description FROM sales_products WHERE product_id=?', 'i', [(int) $productId]);
    $rows = sales_list_rows($db, 'SELECT * FROM inv_price_history WHERE product_id=? ORDER BY changed_at DESC, history_id DESC', 'i', [(int) $productId]);
    $html = '<section class="section" id="history"><div class="section-title">Price history: ' . sales_h(trim(($p['product_code'] ?? '') . ' ' . ($p['model_caption'] ?? '') . ' ' . ($p['description'] ?? ''))) . '</div>'
        . '<table><thead><tr><th>Date</th><th>Price</th><th class="num">Old</th><th class="num">New</th><th class="num">Change</th><th>Changed By</th></tr></thead><tbody>';
    $html .= $rows ? '' : '<tr><td colspan="6" class="empty">No price changes recorded.</td></tr>';
    foreach ($rows as $r) {
        $diff = (float) $r['new_value'] - (float) $r['old_value'];
        $html .= '<tr><td>' . sales_h(date('d/m/Y h:i A', strtotime($r['changed_at']))) . '</td><td>' . sales_h(PH_LABELS[$r['price_field']] ?? $r['price_field']) . '</td><td class="num">' . sales_money($r['old_value']) . '</td>'
            . '<td class="num">' . sales_money($r['new_value']) . '</td><td class="num">' . ($diff >= 0 ? '+' : '') . sales_money($diff) . '</td><td>' . sales_h($r['changed_by_name']) . '</td></tr>';
    }
    return $html . '</tbody></table></section>';
}

/**
 * Whole page for a price view, laid out like the desktop screen: scope radios with Group / Brand / Model boxes and
 * Preview (printable list), then a grid with PRODUCT columns and a second heading ($groupLabel) over the editable prices.
 * $info: read-only product columns [key => [label, kind]]; $fields: editable prices.
 */
function ph_page(mysqli $db, $title, $active, $groupLabel, array $info, array $fields, $note)
{
    $self = basename($_SERVER['SCRIPT_NAME']);
    inv_require_ready($db, $title, $active);
    $scope = inv_scope();
    $query = array_filter(['scope' => $scope['scope'], 'group' => $scope['group'], 'brand' => $scope['brand'], 'model' => $scope['model']], 'strlen');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ph_ready($db)) {
        $back = $self . '?' . http_build_query($query);
        sales_require_csrf($back);
        try {
            $n = ph_save($db, $fields);
            sales_flash_redirect($back, 'success', $n ? $n . ' price(s) changed.' : 'No price was changed.');
        } catch (RuntimeException $e) {
            sales_flash_redirect($back, 'error', $e->getMessage());
        }
    }
    $ready = ph_ready($db);
    $fmt = fn($kind, $v) => $kind === 'qty' ? sales_qty_fmt($v) : ($kind === 'pct' ? ($v === null ? '' : number_format((float) $v, 1) . '%') : ($kind === 'text' ? sales_h($v) : sales_money($v)));
    $shown = isset($_GET['scope']);
    $rows = $ready && $shown ? ph_rows($db, $scope) : [];

    if ($ready && isset($_GET['preview'])) {
        // Printable price list of the chosen scope.
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . sales_h($title) . '</title><style>' . INV_PRINT_CSS . '</style></head><body>';
        echo '<div class="bar"><button onclick="window.print()">Print</button></div>' . inv_print_head($db, $title) . '<p style="text-align:center">' . sales_h(inv_scope_where($scope)[3]) . ' · ' . sales_h(date('d/m/Y')) . '</p>';
        echo '<table><thead><tr><th colspan="' . (2 + count($info)) . '" style="text-align:center">PRODUCT</th><th colspan="' . count($fields) . '" style="text-align:center">' . sales_h($groupLabel) . '</th></tr><tr><th>SL</th><th>Model No</th>';
        foreach ($info as [$label, $kind]) {
            echo '<th' . ($kind === 'text' ? '' : ' class="num"') . '>' . sales_h($label) . '</th>';
        }
        foreach ($fields as $field) {
            echo '<th class="num">' . sales_h(PH_LABELS[$field]) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $i => $r) {
            echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h(trim($r['product_code'] . ' ' . $r['model_caption'])) . '</td>';
            foreach ($info as $key => [$label, $kind]) {
                echo '<td' . ($kind === 'text' ? '' : ' class="num"') . '>' . $fmt($kind, $r[$key] ?? null) . '</td>';
            }
            foreach ($fields as $field) {
                echo '<td class="num">' . sales_money($r[PH_LIST_COL[$field] ?? $field]) . '</td>';
            }
            echo '</tr>';
        }
        echo ($rows ? '' : '<tr><td colspan="' . (2 + count($info) + count($fields)) . '" class="muted">No products.</td></tr>') . '</tbody></table></body></html>';
        return;
    }

    $flash = sales_take_flash();
    inv_page_start($db, $title, $active);
    echo sales_flash_html($flash) . '<fieldset class="box"><legend>' . sales_h($title) . '</legend>';
    if (!$ready) {
        echo '<div class="errorbox"><strong>Price tables are not installed.</strong><br>Import <code>billing/price_history/schema.sql</code> and reload this page.</div></fieldset>';
        inv_page_end();
        return;
    }
    $names = fn($col) => array_column(sales_rows($db, "SELECT DISTINCT $col AS v FROM sales_products WHERE $col<>'' ORDER BY 1"), 'v');
    echo '<form method="get" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px"><div>' . inv_scope_html($scope, $names('product_group'), $names('brand'))
        . '</div><div class="toolbar"><button class="btn primary">Show</button><button class="btn" name="preview" value="1" formtarget="_blank">Preview</button></div></form>';
    if (isset($_GET['history'])) {
        echo ph_history_html($db, (int) $_GET['history']);
    }
    echo '<form method="post">' . sales_csrf_field();
    foreach ($query as $k => $v) {
        echo '<input type="hidden" name="' . sales_h($k) . '" value="' . sales_h($v) . '">';
    }
    echo '<div class="table-scroll" style="max-height:620px"><table class="price-grid"><thead><tr><th colspan="' . (2 + count($info)) . '" style="text-align:center">PRODUCT</th><th colspan="' . (count($fields) + 1) . '" style="text-align:center">' . sales_h($groupLabel) . '</th></tr><tr><th>SL</th><th>Model No</th>';
    foreach ($info as [$label, $kind]) {
        echo '<th' . ($kind === 'text' ? '' : ' class="num"') . '>' . sales_h($label) . '</th>';
    }
    foreach ($fields as $field) {
        echo '<th class="num">' . sales_h(PH_LABELS[$field]) . '</th>';
    }
    echo '<th></th></tr></thead><tbody>';
    if (!$shown) {
        echo '<tr><td colspan="' . (3 + count($info) + count($fields)) . '" class="empty">Pick Group, Brand, Group and Brand or Model Wise and click Show. Leave the name blank for every product.</td></tr>';
    } elseif (!$rows) {
        echo '<tr><td colspan="' . (3 + count($info) + count($fields)) . '" class="empty">No products match.</td></tr>';
    }
    foreach ($rows as $i => $r) {
        $pid = (int) $r['product_id'];
        echo '<tr><td>' . ($i + 1) . '</td><td title="' . sales_h(trim($r['product_group'] . ' / ' . $r['brand'], ' /')) . '">' . sales_h(trim($r['product_code'] . ' ' . $r['model_caption'])) . '</td>';
        foreach ($info as $key => [$label, $kind]) {
            echo '<td' . ($kind === 'text' ? '' : ' class="num"') . '>' . $fmt($kind, $r[$key] ?? null) . '</td>';
        }
        foreach ($fields as $field) {
            echo '<td><input type="number" min="0" step="0.01" name="prices[' . $pid . '][' . $field . ']" value="' . sales_h(number_format((float) $r[PH_LIST_COL[$field] ?? $field], 2, '.', '')) . '" style="width:100px;height:26px;text-align:right"></td>';
        }
        echo '<td><a class="btn small" href="' . $self . '?' . sales_h(http_build_query($query + ['history' => $pid])) . '#history">History</a></td></tr>';
    }
    echo '</tbody></table></div>';
    if ($rows) {
        echo '<div class="toolbar" style="margin-top:8px;align-items:center"><button class="btn primary">Save Changes</button><span class="muted">' . count($rows) . ' product(s). ' . sales_h($note) . '</span></div>';
    }
    echo '</form></fieldset>';
    // Only changed inputs are sent, so a long list saves quickly and stays under PHP's max_input_vars.
    inv_page_end(INV_SCOPE_JS . 'document.querySelectorAll(".price-grid input").forEach(i=>{i.dataset.orig=i.value});'
            . 'document.querySelectorAll("form[method=post]").forEach(f=>f.addEventListener("submit",()=>{f.querySelectorAll(".price-grid input").forEach(i=>{if(i.value===i.dataset.orig)i.disabled=true})}));');
}
