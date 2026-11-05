<?php
/**
 * Shared code for the inventory-based pages (purchase, challan, price & history, amendment, transfer, approve).
 * Reuses the sales helpers for login, CSRF, prepared statements and flash messages.
 */
require_once dirname(__DIR__) . '/sales/sales_common.php';

/**
 * Document types stored in inv_docs. stock: in | out | transfer | none. party: customer | supplier | none.
 * linked: the stock side of a sales document, created by sales/sales_stock.php and approved with that document.
 */
const INV_TYPES = [
    'requisition' => ['code' => 'REQ', 'title' => 'Requisition', 'url' => 'purchase/requisition.php', 'party' => 'none', 'stock' => 'none'],
    'purchase_order' => ['code' => 'POR', 'title' => 'Purchase Order', 'url' => 'purchase/purchase_order.php', 'party' => 'supplier', 'stock' => 'none'],
    'purchase' => ['code' => 'PUR', 'title' => 'Purchase', 'url' => 'purchase/purchase.php', 'party' => 'supplier', 'stock' => 'in'],
    'purchase_return' => ['code' => 'PRT', 'title' => 'Purchase Return', 'url' => 'purchase/purchase_return.php', 'party' => 'supplier', 'stock' => 'out'],
    'sales_invoice' => ['code' => 'SIV', 'title' => 'Sales Invoice', 'url' => 'sales/sales_invoice/sales_invoice.php', 'party' => 'customer', 'stock' => 'out', 'linked' => true],
    'sales_return' => ['code' => 'SRT', 'title' => 'Sales Return', 'url' => 'sales/sales_retrun/sales_return.php', 'party' => 'customer', 'stock' => 'in', 'linked' => true],
    'challan' => ['code' => 'CHL', 'title' => 'Challan', 'url' => 'challan/challan.php', 'party' => 'customer', 'stock' => 'out'],
    'challan_return' => ['code' => 'CHR', 'title' => 'Challan Return', 'url' => 'challan/challan_return.php', 'party' => 'customer', 'stock' => 'in'],
    'challan_bill' => ['code' => 'SBC', 'title' => 'Sales Invoice/Bill From Challan', 'url' => 'challan/sales_bill_from_challan.php', 'party' => 'customer', 'stock' => 'none'],
    'quotation' => ['code' => 'PQI', 'title' => 'Price Quotation', 'url' => 'price_history/price_quotation.php', 'party' => 'customer', 'stock' => 'none'],
    'price_amendment' => ['code' => 'PPA', 'title' => 'Purchase Price Amendment', 'url' => 'amedment/purchase_price_amendment.php', 'party' => 'supplier', 'stock' => 'none'],
    'stock_in' => ['code' => 'SAI', 'title' => 'Stock Amendment IN', 'url' => 'amedment/stock_amendment_in.php', 'party' => 'none', 'stock' => 'in'],
    'stock_out' => ['code' => 'SAO', 'title' => 'Stock Amendment OUT', 'url' => 'amedment/stock_amendment_out.php', 'party' => 'none', 'stock' => 'out'],
    'stock_transfer' => ['code' => 'STT', 'title' => 'Stock Transfer Store to Store', 'url' => 'transfer/stock_transfer.php', 'party' => 'none', 'stock' => 'transfer'],
];

/** Fields of the Additional Info box, saved as JSON (inv_docs.additional_info, sales_invoices / sales_orders.additional_info). */
const INV_ADDITIONAL = ['delivery_date' => 'Delivery Date', 'vehicle_no' => 'Vehicle No', 'driver' => 'Driver Name / Mobile', 'reference' => 'Reference Person',
    'installation_address' => 'Installation Address', 'warranty_card' => 'Warranty Card No', 'remarks' => 'Remarks'];

/** Additional Info JSON from the posted add_* fields (or another array of them), or null when all are blank. */
function inv_additional_from_post(?array $src = null)
{
    $src = $src ?? $_POST;
    $out = [];
    foreach (INV_ADDITIONAL as $k => $label) {
        $v = sales_cut(trim((string) ($src['add_' . $k] ?? '')), 255);
        if ($v !== '') {
            $out[$k] = $v;
        }
    }
    return $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
}

function inv_ready(mysqli $db)
{
    return sales_tables_ready($db, ['sales_products', 'sales_customers', 'sales_stores', 'branches', 'inv_settings', 'inv_sequences', 'inv_docs', 'inv_doc_lines', 'inv_stock_moves', 'inv_serials', 'inv_serial_moves', 'inv_product_prices', 'inv_transaction_locks']);
}

function inv_setting(mysqli $db, $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (sales_rows($db, 'SELECT setting_key, setting_value FROM inv_settings') as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

/** Company header for prints: the Accounts Configuration when installed, else a plain default. */
function inv_company(mysqli $db)
{
    $out = ['name' => 'Company', 'address' => '', 'phone' => '', 'mobile' => '', 'email' => '', 'website' => '', 'logo' => '', 'bin' => ''];
    if (sales_tables_ready($db, ['acc_settings'])) {
        foreach (sales_rows($db, "SELECT setting_key, setting_value FROM acc_settings WHERE setting_key IN ('company_name','company_address','company_phone','company_mobile','company_email','company_website','company_logo','company_bin')") as $r) {
            $out[substr($r['setting_key'], 8)] = $r['setting_value'];
        }
    }
    return $out;
}

/* ---------------- Branches and transaction locks ---------------- */

function inv_branches(mysqli $db, $activeOnly = true)
{
    return sales_rows($db, 'SELECT * FROM branches' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY branch_id');
}

/** The branch new documents are saved under: the one picked at sign-in, in the header or with the selector at the top of each page. */
function inv_current_branch(mysqli $db)
{
    $branches = inv_branches($db);
    $current = perm_pick_branch(array_map('intval', array_column($branches, 'branch_id')));
    $_SESSION['inv_branch_id'] = $current;
    return $current;
}

function inv_branch_name(mysqli $db, $branchId)
{
    static $names = null;
    if ($names === null) {
        $names = array_column(inv_branches($db, false), 'branch_name', 'branch_id');
    }
    return $names[(int) $branchId] ?? '';
}

/** Throws when the date is on or before an active lock for the branch (or for all branches). */
function inv_assert_unlocked(mysqli $db, $date, $branchId)
{
    if (!sales_tables_ready($db, ['inv_transaction_locks'])) {
        return;
    }
    $lock = sales_row(
        $db,
        'SELECT MAX(lock_date) AS d FROM inv_transaction_locks WHERE is_active=1 AND (branch_id IS NULL OR branch_id=?) AND lock_date >= ?',
        'is',
        [(int) $branchId, $date]
    );
    if (!empty($lock['d'])) {
        throw new RuntimeException('Transactions on or before ' . date('d/m/Y', strtotime($lock['d'])) . ' are locked for this branch. Use a later date or remove the lock in Transaction Lock.');
    }
}

/** Reserve the next document number inside a transaction, e.g. DESPUR2024000001. */
function inv_next_no(mysqli $db, $code, $date)
{
    $year = (int) substr($date, 0, 4);
    sales_exec($db, 'INSERT INTO inv_sequences (doc_code, seq_year, last_number) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE last_number = last_number + 1', 'si', [$code, $year])->close();
    $row = sales_row($db, 'SELECT last_number FROM inv_sequences WHERE doc_code=? AND seq_year=? FOR UPDATE', 'si', [$code, $year]);
    return inv_setting($db, 'doc_prefix', 'DES') . $code . $year . str_pad((string) $row['last_number'], 6, '0', STR_PAD_LEFT);
}

function inv_peek_no(mysqli $db, $code)
{
    $row = sales_row($db, 'SELECT last_number + 1 AS n FROM inv_sequences WHERE doc_code=? AND seq_year=?', 'si', [$code, (int) date('Y')]);
    return inv_setting($db, 'doc_prefix', 'DES') . $code . date('Y') . str_pad((string) ($row['n'] ?? 1), 6, '0', STR_PAD_LEFT);
}

/* ---------------- Products, stores and stock ---------------- */

/** True when a table has the column (cached per request). */
function inv_has_column(mysqli $db, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        $cache[$key] = (bool) sales_row($db, 'SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?', 'ss', [$table, $column]);
    }
    return $cache[$key];
}

/**
 * Warranty offered today per product from Periodical Warranty rules: [product_id => warranty name].
 * The most specific active rule wins: product, then product group, then brand. Empty when the product module is not installed.
 */
function inv_periodic_warranties(mysqli $db, $date = null)
{
    if (!sales_tables_ready($db, ['product_periodic_warranties', 'product_warranties'])) {
        return [];
    }
    $rows = sales_rows(
        $db,
        "SELECT p.product_id, w.warranty_name, CASE r.scope WHEN 'product' THEN 1 WHEN 'group' THEN 2 ELSE 3 END AS rank_no FROM product_periodic_warranties r "
            . 'JOIN product_warranties w ON w.warranty_id=r.warranty_id '
            . "JOIN sales_products p ON (r.scope='product' AND p.product_id=r.product_id) OR (r.scope='group' AND p.product_group=r.scope_value) OR (r.scope='brand' AND p.brand=r.scope_value) "
            . 'WHERE r.is_active=1 AND ? BETWEEN r.from_date AND r.to_date ORDER BY p.product_id, rank_no, r.pw_id DESC',
        's',
        [$date ?: date('Y-m-d')]
    );
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['product_id']] = $out[(int) $r['product_id']] ?? $r['warranty_name'];
    }
    return $out;
}

/**
 * Products with last purchase cost (or the fixed Purchase Price when nothing was bought yet), price levels and the
 * warranty to offer (Periodical Warranty when a rule applies today), for the type-ahead on document pages.
 */
function inv_products(mysqli $db)
{
    $fixed = inv_has_column($db, 'sales_products', 'purchase_price') ? 'NULLIF(p.purchase_price, 0)' : 'NULL';
    $rows = sales_rows(
        $db,
        'SELECT p.product_id, p.product_code, p.product_group, p.brand, p.model_caption, p.description, p.unit_name, p.warranty, p.default_unit_price, '
            . 'COALESCE(pp.mrp, 0) AS mrp, COALESCE(pp.retail_a, 0) AS retail_a, COALESCE(pp.dealer_a, 0) AS dealer_a, '
            . 'COALESCE((SELECT m.unit_cost FROM inv_stock_moves m WHERE m.product_id=p.product_id AND m.qty_in > 0 AND m.doc_type=\'purchase\' ORDER BY m.move_id DESC LIMIT 1), ' . $fixed . ') AS last_cost '
            . 'FROM sales_products p LEFT JOIN inv_product_prices pp ON pp.product_id=p.product_id WHERE p.is_active=1 ORDER BY p.model_caption, p.description LIMIT 5000'
    );
    $periodic = inv_periodic_warranties($db);
    if ($periodic) {
        foreach ($rows as &$r) {
            $r['warranty'] = $periodic[(int) $r['product_id']] ?? $r['warranty'];
        }
        unset($r);
    }
    return $rows;
}

/** Active stores the user may use on this page; $all skips that limit, for a transfer's Store To. */
function inv_stores(mysqli $db, $all = false)
{
    $stores = array_column(sales_rows($db, 'SELECT store_name FROM sales_stores WHERE is_active=1 ORDER BY store_name'), 'store_name');
    return $all ? $stores : perm_filter_stores($stores);
}

/** Quantity on hand for a product in a store (all stores when $store is ''), optionally ignoring one document. */
function inv_available(mysqli $db, $productId, $store = '', $excludeDocId = 0)
{
    $sql = 'SELECT COALESCE(SUM(qty_in - qty_out), 0) AS q FROM inv_stock_moves WHERE product_id=?';
    $types = 'i';
    $params = [(int) $productId];
    if ($store !== '') {
        $sql .= ' AND store_name=?';
        $types .= 's';
        $params[] = $store;
    }
    if ($excludeDocId > 0) {
        $sql .= ' AND doc_id<>?';
        $types .= 'i';
        $params[] = (int) $excludeDocId;
    }
    return round((float) sales_row($db, $sql, $types, $params)['q'], 3);
}

/** Normalise a serial list: one per line or comma separated, trimmed, without blanks or repeats. */
function inv_parse_serials($value)
{
    $items = is_array($value) ? $value : preg_split('/[\r\n,]+/', (string) $value);
    $out = [];
    foreach ($items as $s) {
        $s = sales_cut(trim((string) $s), 120);
        if ($s !== '' && !in_array($s, $out, true)) {
            $out[] = $s;
        }
    }
    return $out;
}

/** Recompute a serial's state from its remaining moves; delete it when no move is left. */
function inv_rebuild_serial(mysqli $db, $serialId)
{
    $last = sales_row($db, 'SELECT direction, store_name FROM inv_serial_moves WHERE serial_id=? ORDER BY serial_move_id DESC LIMIT 1', 'i', [(int) $serialId]);
    if (!$last) {
        sales_exec($db, 'DELETE FROM inv_serials WHERE serial_id=?', 'i', [(int) $serialId])->close();
        return;
    }
    sales_exec($db, 'UPDATE inv_serials SET in_stock=?, store_name=? WHERE serial_id=?', 'isi', [$last['direction'] === 'in' ? 1 : 0, $last['store_name'], (int) $serialId])->close();
}

/**
 * Undo a document's stock and serial effects. Refuses when one of its serials was moved later by another
 * document, because undoing would then break that later history.
 */
function inv_reverse_stock(mysqli $db, $docId)
{
    $docId = (int) $docId;
    $later = sales_row(
        $db,
        'SELECT s.serial_no, o.doc_no FROM inv_serial_moves m JOIN inv_serial_moves o ON o.serial_id=m.serial_id AND o.serial_move_id > m.serial_move_id AND o.doc_id<>m.doc_id '
            . 'JOIN inv_serials s ON s.serial_id=m.serial_id WHERE m.doc_id=? LIMIT 1',
        'i',
        [$docId]
    );
    if ($later) {
        throw new RuntimeException('Serial ' . $later['serial_no'] . ' was used later in ' . $later['doc_no'] . ', so this document can no longer be changed.');
    }
    $serialIds = array_map('intval', array_column(sales_rows($db, 'SELECT DISTINCT serial_id FROM inv_serial_moves WHERE doc_id=?', 'i', [$docId]), 'serial_id'));
    $keys = sales_rows($db, 'SELECT DISTINCT product_id, store_name FROM inv_stock_moves WHERE doc_id=?', 'i', [$docId]);
    sales_exec($db, 'DELETE FROM inv_serial_moves WHERE doc_id=?', 'i', [$docId])->close();
    sales_exec($db, 'DELETE FROM inv_stock_moves WHERE doc_id=?', 'i', [$docId])->close();
    foreach ($serialIds as $sid) {
        inv_rebuild_serial($db, $sid);
    }
    return $keys;
}

/**
 * Apply stock and serial moves for a saved document. $mode is in, out or transfer. $lines are rows from
 * inv_doc_lines (with line_id). $label is the transaction name shown in Serial History.
 * $touched lists [product_id, store_name] pairs whose balance must not go negative afterwards.
 */
function inv_apply_stock(mysqli $db, array $doc, array $lines, $mode, $label, array $touched = [])
{
    $docId = (int) $doc['doc_id'];
    $need = [];
    foreach ($lines as $l) {
        $pid = (int) ($l['product_id'] ?? 0);
        $qty = round((float) $l['quantity'] + (float) $l['free_quantity'], 3);
        if ($qty <= 0) {
            continue;
        }
        if ($pid <= 0) {
            throw new RuntimeException('"' . $l['description'] . '" is not a saved product. Pick it from the product list so stock can be tracked.');
        }
        $store = (string) $l['store_name'];
        if ($store === '') {
            throw new RuntimeException('Pick a store for "' . $l['description'] . '".');
        }
        $serials = inv_parse_serials($l['serials'] ?? '');
        if (!$serials && inv_has_column($db, 'sales_products', 'serial_required')
                && (int) (sales_row($db, 'SELECT serial_required FROM sales_products WHERE product_id=?', 'i', [$pid])['serial_required'] ?? 0) === 1) {
                    throw new RuntimeException('"' . $l['description'] . '" needs a serial / IMEI for every unit (Serial Required in Product Information).');
                }
        if ($serials && count($serials) !== (int) round($qty)) {
            throw new RuntimeException('"' . $l['description'] . '" has ' . count($serials) . ' serial(s) for a quantity of ' . sales_qty_fmt($qty) . '. They must match.');
        }
        $moves = [];
        if ($mode === 'in') {
            $moves[] = [$store, $qty, 0.0];
        } elseif ($mode === 'out') {
            $moves[] = [$store, 0.0, $qty];
            $need[$pid . '|' . $store] = ($need[$pid . '|' . $store] ?? 0) + $qty;
        } else {
            $to = (string) ($l['store_to'] ?? '');
            if ($to === '' || $to === $store) {
                throw new RuntimeException('Pick a different "Store To" for "' . $l['description'] . '".');
            }
            $moves[] = [$store, 0.0, $qty];
            $moves[] = [$to, $qty, 0.0];
            $need[$pid . '|' . $store] = ($need[$pid . '|' . $store] ?? 0) + $qty;
            $touched[] = ['product_id' => $pid, 'store_name' => $to];
        }
        foreach ($moves as [$moveStore, $in, $out]) {
            sales_insert(
                $db,
                'INSERT INTO inv_stock_moves (product_id, store_name, branch_id, move_date, qty_in, qty_out, unit_cost, doc_type, doc_id, doc_no, line_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'isisdddsisi',
                [$pid, $moveStore, $doc['branch_id'], $doc['doc_date'], $in, $out, (float) $l['unit_price'], $doc['doc_type'], $docId, $doc['doc_no'], (int) $l['line_id']]
            );
        }
        foreach ($serials as $sn) {
            $s = sales_row($db, 'SELECT * FROM inv_serials WHERE product_id=? AND serial_no=? FOR UPDATE', 'is', [$pid, $sn]);
            if ($mode === 'in') {
                if ($s && (int) $s['in_stock']) {
                    throw new RuntimeException('Serial ' . $sn . ' is already in stock (' . $s['store_name'] . ').');
                }
                $sid = $s ? (int) $s['serial_id'] : sales_insert($db, 'INSERT INTO inv_serials (product_id, serial_no, store_name, in_stock) VALUES (?, ?, ?, 0)', 'iss', [$pid, $sn, $store]);
                $steps = [['in', $store]];
            } else {
                if (!$s || !(int) $s['in_stock']) {
                    throw new RuntimeException('Serial ' . $sn . ' of "' . $l['description'] . '" is not in stock.');
                }
                if ($s['store_name'] !== $store) {
                    throw new RuntimeException('Serial ' . $sn . ' is in ' . $s['store_name'] . ', not ' . $store . '.');
                }
                $sid = (int) $s['serial_id'];
                $steps = $mode === 'out' ? [['out', $store]] : [['out', $store], ['in', (string) $l['store_to']]];
            }
            foreach ($steps as [$dir, $st]) {
                sales_insert(
                    $db,
                    'INSERT INTO inv_serial_moves (serial_id, direction, move_date, doc_type, doc_id, doc_no, ref_no, transaction_label, party_name, mobile, store_name, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    'isss' . 'i' . 'ssssss' . 'i',
                    [$sid, $dir, $doc['doc_date'], $doc['doc_type'], $docId, $doc['doc_no'], $doc['ref_no'], $label, $doc['party_name'], $doc['mobile'], $st, $doc['branch_id']]
                );
            }
            inv_rebuild_serial($db, $sid);
        }
    }
    foreach ($need as $key => $qty) {
        [$pid, $store] = explode('|', $key, 2);
        $have = inv_available($db, (int) $pid, $store, $docId);
        if ($qty > $have + 0.0005) {
            $name = sales_row($db, 'SELECT COALESCE(NULLIF(model_caption, \'\'), description) AS n FROM sales_products WHERE product_id=?', 'i', [(int) $pid])['n'] ?? ('#' . $pid);
            throw new RuntimeException('Only ' . sales_qty_fmt($have) . ' of "' . $name . '" is available in ' . $store . '.');
        }
    }
    foreach ($touched as $t) {
        if (inv_available($db, (int) $t['product_id'], (string) $t['store_name']) < -0.0005) {
            throw new RuntimeException('This change would make stock negative in ' . $t['store_name'] . ' because some of it was already issued. Reverse the later documents first.');
        }
    }
}

/* ---------------- Product scope filter (Group / Brand / Group and Brand / Model Wise) ---------------- */


/** Scope filter values from the request: scope, group, brand, model. */
function inv_scope()
{
    $g = fn($k) => sales_cut(trim((string) ($_REQUEST[$k] ?? '')), 190);
    $scope = in_array($g('scope'), ['all', 'group', 'brand', 'group_brand', 'model'], true) ? $g('scope') : 'group';
    return ['scope' => $scope, 'group' => in_array($scope, ['group', 'group_brand'], true) ? $g('group') : '', 'brand' => in_array($scope, ['brand', 'group_brand'], true) ? $g('brand') : '',
        'model' => $scope === 'model' ? $g('model') : ''];
}

/** WHERE for a scope over sales_products (alias p). Returns [sql, types, params, label]. */
function inv_scope_where(array $s)
{
    $w = ['p.is_active=1'];
    $t = '';
    $p = [];
    $label = [];
    foreach (['group' => 'p.product_group=?', 'brand' => 'p.brand=?'] as $k => $sql) {
        if ($s[$k] !== '') {
            $w[] = $sql;
            $t .= 's';
            $p[] = $s[$k];
            $label[] = ucfirst($k) . ': ' . $s[$k];
        }
    }
    if ($s['model'] !== '') {
        $w[] = '(p.model_caption LIKE ? OR p.product_code LIKE ? OR p.description LIKE ?)';
        $t .= 'sss';
        array_push($p, '%' . $s['model'] . '%', '%' . $s['model'] . '%', '%' . $s['model'] . '%');
        $label[] = 'Model: ' . $s['model'];
    }
    return [implode(' AND ', $w), $t, $p, $label ? implode(', ', $label) : 'All products'];
}

/** Scope radios with the matching name boxes (shown by scope), like the desktop screens. $groups / $brands feed the type-ahead. */
function inv_scope_html(array $s, array $groups, array $brands, $withAll = false)
{
    $html = '<div class="radios">';
    $choices = ($withAll ? ['all' => 'All'] : []) + ['group' => 'Group Wise', 'brand' => 'Brand Wise', 'group_brand' => 'Group and Brand Wise', 'model' => 'Model Wise'];
    foreach ($choices as $k => $label) {
        $html .= '<label><input type="radio" name="scope" value="' . $k . '"' . ($s['scope'] === $k ? ' checked' : '') . ' onchange="invScope(this.form)"> ' . $label . '</label>';
    }
    $dl = fn($id, array $list) => '<datalist id="' . $id . '">' . implode('', array_map(fn($v) => '<option value="' . sales_h($v) . '">', $list)) . '</datalist>';
    $html .= '</div><div class="radios">'
        . '<label data-scope="group group_brand">Group Name <input name="group" list="dl-group" value="' . sales_h($s['group']) . '" style="width:240px"></label>'
        . '<label data-scope="brand group_brand">Brand Name <input name="brand" list="dl-brand" value="' . sales_h($s['brand']) . '" style="width:240px"></label>'
        . '<label data-scope="model">Model No <input name="model" value="' . sales_h($s['model']) . '" style="width:240px"></label></div>'
        . $dl('dl-group', $groups) . $dl('dl-brand', $brands);
    return $html;
}

/** Script for inv_scope_html: show only the boxes of the chosen scope. */
const INV_SCOPE_JS = 'function invScope(f){const s=(f.querySelector("input[name=scope]:checked")||{}).value;f.querySelectorAll("[data-scope]").forEach(el=>{const on=el.dataset.scope.split(" ").includes(s);el.hidden=!on;el.querySelectorAll("input").forEach(i=>i.disabled=!on)})}'
    . 'document.querySelectorAll("form").forEach(f=>{if(f.querySelector("input[name=scope]"))invScope(f)});';

/* ---------------- Layout ---------------- */

function inv_menu()
{
    return [
        'Purchase' => ['requisition' => ['../purchase/requisition.php', 'Requisition'], 'purchase_order' => ['../purchase/purchase_order.php', 'Purchase Order'], 'purchase' => ['../purchase/purchase.php', 'Purchase'], 'purchase_return' => ['../purchase/purchase_return.php', 'Purchase Return']],
        'Challan' => ['challan' => ['../challan/challan.php', 'Challan'], 'challan_return' => ['../challan/challan_return.php', 'Challan Return'], 'challan_bill' => ['../challan/sales_bill_from_challan.php', 'Sales Bill From Challan']],
        'Price & History' => ['quotation' => ['../price_history/price_quotation.php', 'Price Quotation'], 'serial_history' => ['../price_history/serial_history.php', 'Serial History'], 'mrp_view' => ['../price_history/sales_price_mrp_view.php', 'Sales Price MRP View'], 'price_view' => ['../price_history/sales_price_view.php', 'Sales Price View'], 'invoice_search' => ['../price_history/invoice_search.php', 'Invoice Search by Mobile No.']],
        'Amendment' => ['price_amendment' => ['../amedment/purchase_price_amendment.php', 'Purchase Price Amendment'], 'stock_in' => ['../amedment/stock_amendment_in.php', 'Stock Amendment IN'], 'stock_out' => ['../amedment/stock_amendment_out.php', 'Stock Amendment OUT'], 'lock' => ['../amedment/transaction_lock.php', 'Transaction Lock']],
        'Transfer' => ['stock_transfer' => ['../transfer/stock_transfer.php', 'Stock Transfer']],
        'Approve' => ['approve' => ['../approve/approve.php', 'Approvals']],
    ];
}

function inv_page_start(mysqli $db, $title, $active = '')
{
    $branch = inv_ready($db) ? inv_current_branch($db) : 0;
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . sales_h($title) . '</title><link rel="stylesheet" href="../sales/sales.css"><link rel="stylesheet" href="../inventory/inv.css"><link rel="stylesheet" href="../doc_layout.css?v=' . (int) @filemtime(dirname(__DIR__) . '/doc_layout.css') . '"></head><body><div class="wrap"><nav class="acc-nav">';
    foreach (inv_menu() as $section => $links) {
        echo '<span><b>' . sales_h($section) . ':</b>';
        foreach ($links as $key => [$href, $label]) {
            echo '<a href="' . sales_h($href) . '"' . ($key === $active ? ' class="active"' : '') . '>' . sales_h($label) . '</a>';
        }
        echo '</span>';
    }
    if ($branch) {
        $query = $_GET;
        unset($query['set_branch']);
        echo '<form method="get" class="branch-pick">';
        foreach ($query as $k => $v) {
            if (is_scalar($v)) {
                echo '<input type="hidden" name="' . sales_h($k) . '" value="' . sales_h($v) . '">';
            }
        }
        echo '<label>Branch <select name="set_branch" onchange="this.form.submit()">';
        foreach (inv_branches($db) as $b) {
            echo '<option value="' . (int) $b['branch_id'] . '"' . ((int) $b['branch_id'] === $branch ? ' selected' : '') . '>' . sales_h($b['branch_name']) . '</option>';
        }
        echo '</select></label></form>';
    }
    echo '</nav><main class="panel">';
}

function inv_page_end($script = '')
{
    echo '</main></div><script src="../sales/sales.js"></script>';
    if ($script !== '') {
        echo '<script>' . $script . '</script>';
    }
    echo '</body></html>';
}

function inv_missing_html()
{
    return '<div class="errorbox"><strong>Inventory tables are not installed yet.</strong><br>Import <code>billing/sales/schema.sql</code>, then <code>billing/inventory/schema.sql</code>, and reload this page.</div>';
}

function inv_require_ready(mysqli $db, $title, $active)
{
    if (inv_ready($db)) {
        return;
    }
    inv_page_start($db, $title, $active);
    echo inv_missing_html();
    inv_page_end();
    exit;
}

function inv_fmt_date($date)
{
    if (!$date) {
        return '';
    }
    $t = strtotime(substr((string) $date, 0, 10));
    return $t ? date('d/m/Y', $t) : (string) $date;
}

/** Company header block used at the top of every print. */
function inv_print_head(mysqli $db, $title)
{
    $c = inv_company($db);
    return '<div class="ph"><div><b class="co">' . sales_h($c['name']) . '</b><br>' . sales_h($c['address']) . '</div><div class="contact">Phone : ' . sales_h($c['phone']) . '<br>Email : ' . sales_h($c['email']) . '</div></div><h2 class="pt">' . sales_h($title) . '</h2>';
}

const INV_PRINT_CSS = 'body{font:12px Arial,Helvetica,sans-serif;color:#111;margin:22px}.ph{display:flex;justify-content:space-between}.co{font-size:19px}.contact{font-weight:bold}.pt{text-align:center;margin:12px 0}'
    . 'table{width:100%;border-collapse:collapse;margin:8px 0}th,td{border:1px solid #bbb;padding:4px 6px;text-align:left;vertical-align:top}th{background:#f2f2f2}.num{text-align:right;white-space:nowrap}'
    . '.meta{display:grid;grid-template-columns:1fr 1fr;gap:3px 24px;margin:8px 0}.totals{width:320px;margin-left:auto}.sign{display:flex;justify-content:space-between;margin-top:60px}.sign div{border-top:1px solid #000;width:22%;text-align:center;padding-top:4px}'
    . '.bar{margin-bottom:10px}.muted{color:#777}.serials{font-size:11px;color:#333}@media print{.bar{display:none}body{margin:8mm}}';
