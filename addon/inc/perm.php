<?php
/**
 * Users, branches and form permissions for the admin shell. perm_guard() runs on every billing page (addon/conf/connection.php
 * and billing/core/admin_bootstrap.php call it) and decides from the signed-in session, never from a client value:
 * SuperAdmin passes, anyone else needs a row in admin_user_permissions for the form the page belongs to, and the
 * posted action, dates, amounts and prices are checked against that row. Tables: billing/user/schema.sql.
 */
require_once dirname(__DIR__, 2) . '/billing/menu_catalog.php';

// Business dates follow Dhaka time. PHP defaults to UTC here while MySQL uses the machine clock, so PHP was a day behind from midnight to 6 AM.
date_default_timezone_set('Asia/Dhaka');

/** Permission columns (can_<key>) and their captions in User Access Control. */
const PERM_ACTIONS = ['view' => 'View', 'add' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete', 'edit_price' => 'Edit Price', 'additional' => 'Additional', 'serial_list' => 'Serial List',
    'extra_charges' => 'Extra Charges', 'vat' => 'VAT', 'ait' => 'Ait', 'discount' => 'Discount', 'preview' => 'Preview', 'print' => 'Print', 'edit_after_app' => 'Edit After App',
    'edit_back_date' => 'Edit Back Date Data', 'advance_date' => 'Advance Date Data', 'show_cost' => 'Show Cost', 'show_price' => 'Show Price', 'print_before_app' => 'Print Before App', 'edit_after_print' => 'Edit After Print'];

/** Which columns apply to a kind of form; the others are greyed out on User Access Control. */
const PERM_PROFILES = [
    'page' => ['view'],
    'setup' => ['view', 'add', 'edit', 'delete'],
    'approve' => ['view', 'edit'],
    'report' => ['view', 'preview', 'print', 'show_cost', 'show_price'],
    'voucher' => ['view', 'add', 'edit', 'delete', 'preview', 'print', 'edit_after_app', 'edit_back_date', 'advance_date', 'print_before_app', 'edit_after_print'],
    'doc' => ['view', 'add', 'edit', 'delete', 'edit_price', 'additional', 'serial_list', 'extra_charges', 'vat', 'ait', 'discount', 'preview', 'print', 'edit_after_app', 'edit_back_date', 'advance_date', 'show_cost', 'show_price', 'print_before_app', 'edit_after_print'],
];

const PERM_LEVELS = ['SuperAdmin', 'Admin', 'Supervisor', 'Operator'];

/** Pages of the shell itself: open to every signed-in user. */
const PERM_ALWAYS = ['topmain.php', 'logout.php', 'go.php', 'user/switch_branch.php', 'user/no_access.php', 'user/notifications.php'];

/** Folders with no helper scripts: a page here must be a form of its own. The old user pages can change any password or role. */
const PERM_NO_HELPERS = ['user/'];

/** Roles that are not for assigning: Administrator is the SuperAdmin role; the other is left in older databases by a panel that no longer exists. */
const PERM_SYSTEM_ROLES = ['Administrator', 'Default Role for VSR'];

/** Documents whose rows carry an approval state and a branch: [script prefix => table, key, post field, state column, approved value]. */
const PERM_DOCS = [
    'sales/sales_invoice/' => ['sales_invoices', 'invoice_id', 'invoice_id', 'approval_status', 'approved'],
    'sales/sales_order/' => ['sales_orders', 'order_id', 'order_id', 'approval_status', 'approved'],
    'sales/sales_retrun/' => ['sales_returns', 'return_id', 'return_id', 'approval_status', 'approved'],
    'challan/' => ['inv_docs', 'doc_id', 'doc_id', 'approval_status', 'approved'],
    'purchase/' => ['inv_docs', 'doc_id', 'doc_id', 'approval_status', 'approved'],
    'amedment/' => ['inv_docs', 'doc_id', 'doc_id', 'approval_status', 'approved'],
    'transfer/' => ['inv_docs', 'doc_id', 'doc_id', 'approval_status', 'approved'],
    'accounts_info/acc_voucher.php' => ['acc_vouchers', 'voucher_id', 'voucher_id', 'status', 'posted'],
    'accounts_info/acc_rp_voucher.php' => ['acc_vouchers', 'voucher_id', 'voucher_id', 'status', 'posted'],
];

/** Posted date fields, for Edit Back Date Data and Advance Date Data. */
const PERM_DATE_FIELDS = ['sales_date', 'doc_date', 'order_date', 'return_date', 'voucher_date'];

/** Posted totals that need a right when they are not zero. */
const PERM_AMOUNT_FIELDS = ['discount_amount' => 'discount', 'vat_amount' => 'vat', 'ait_amount' => 'ait', 'extra_charges' => 'extra_charges'];

/** Query keys that open a document in the admin pages. */
const PERM_DOC_GET_KEYS = ['id', 'print', 'challan', 'receipt', 'barcode'];

/** What a limited user does not see for a right they lack; the server checks above are what enforce it. */
const PERM_HIDE = [
    'delete' => 'button[name="action"][value="delete"],button[name="action"][value="void"],button[name="action"][value="cancel"],button[name="action"][value="remove"]',
    'discount' => '.field:has([name="discount_amount"]),.field:has(#e_disc),[data-disc]',
    'vat' => '.field:has([name="vat_amount"])',
    'ait' => '.field:has([name="ait_amount"])',
    'extra_charges' => '.field:has([name="extra_charges"])',
    'additional' => '#additional-info,button[onclick*="additional-info"]',
    'serial_list' => '#serial-view',
    'show_cost' => '#show-cost',
    'show_price' => '#show-price',
    'print' => '[data-print],#preview-print,.bar button[onclick*="print"]',
    'preview' => '[data-report]:not([data-print])',
];

function perm_db()
{
    foreach (['conn', 'link', 'cp_db'] as $name) {
        if (($GLOBALS[$name] ?? null) instanceof mysqli) {
            return $GLOBALS[$name];
        }
    }
    return null;
}

/** Run a prepared statement; rows for SELECT, [] for anything else or on failure. The callers run in both mysqli error modes. */
function perm_q($sql, $types = '', array $params = [])
{
    $db = perm_db();
    if (!$db) {
        return [];
    }
    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $audit = audit_sql($db, $sql, $types, $params);
        $stmt->execute();
        audit_sql_end($audit, true, $stmt->insert_id);
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        error_log('perm: ' . $e->getMessage());
        return [];
    }
}

/** False until billing/user/schema.sql is imported; nothing is limited before then, so an upgrade cannot lock anyone out. */
function perm_ready()
{
    static $ready = null;
    if ($ready === null) {
        $ready = (bool) perm_q("SELECT 1 AS x FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='admin_user' AND column_name='access_level'");
    }
    return $ready;
}

/** The signed-in user, from the session id kept on the server. Null when there is no valid session. */
function perm_user($reload = false)
{
    static $user = false;
    if ($user === false || $reload) {
        $sesid = (string) ($_COOKIE['admin_sesid'] ?? '');
        $user = $sesid !== '' && perm_ready() ? (perm_q('SELECT * FROM admin_user WHERE sesid=? LIMIT 1', 's', [$sesid])[0] ?? null) : null;
    }
    return $user;
}

function perm_is_super()
{
    if (!perm_ready()) {
        return true;
    }
    $u = perm_user();
    return $u && $u['access_level'] === 'SuperAdmin' && $u['user_type'] === 'staff';
}

/* ---------------- Branches ---------------- */

/** The ids with every branch under them. */
function perm_with_sub_branches(array $ids)
{
    $children = [];
    foreach (perm_q('SELECT branch_id, parent_branch_id FROM branches WHERE parent_branch_id IS NOT NULL') as $b) {
        $children[(int) $b['parent_branch_id']][] = (int) $b['branch_id'];
    }
    $seen = [];
    while ($ids) {
        $id = (int) array_pop($ids);
        if (!isset($seen[$id])) {
            $seen[$id] = $id;
            array_push($ids, ...($children[$id] ?? []));
        }
    }
    return array_values($seen);
}

/** Branch ids a user may open, or null for all of them. */
function perm_user_branch_ids(array $u)
{
    if ((int) $u['all_branches'] === 1 || ($u['access_level'] === 'SuperAdmin' && $u['user_type'] === 'staff')) {
        return null;
    }
    return perm_with_sub_branches(array_map('intval', array_column(perm_q('SELECT branch_id FROM admin_user_branches WHERE user_id=?', 'i', [(int) $u['user_id']]), 'branch_id')));
}

function perm_branch_ids()
{
    static $ids = false;
    if ($ids === false) {
        $u = perm_user();
        $ids = perm_ready() && $u ? perm_user_branch_ids($u) : null;
    }
    return $ids;
}

function perm_branch_allowed($branchId)
{
    $ids = perm_branch_ids();
    return $ids === null || in_array((int) $branchId, $ids, true);
}

/** Branch a limited user is working in, with its sub-branches, or null when documents are not limited by branch. */
function perm_scope_ids()
{
    $ids = perm_branch_ids();
    return $ids === null ? null : array_values(array_intersect(perm_with_sub_branches([perm_current_branch()]), $ids));
}

/** ' AND col IN (...)' for list queries of a limited user, '' otherwise. */
function perm_branch_sql($column)
{
    $ids = perm_scope_ids();
    return $ids === null ? '' : ' AND ' . $column . ' IN (' . ($ids ? implode(',', array_map('intval', $ids)) : '0') . ')';
}

function perm_current_branch()
{
    $u = perm_user();
    $current = (int) ($u['current_branch_id'] ?? 0) ?: (int) ($u['default_branch_id'] ?? 0);
    $ids = perm_branch_ids();
    if ($ids === null) {
        return $current;
    }
    return in_array($current, $ids, true) ? $current : ($ids[0] ?? 0);
}

function perm_set_branch($branchId)
{
    $u = perm_user();
    if ($u && perm_branch_allowed($branchId)) {
        perm_q('UPDATE admin_user SET current_branch_id=? WHERE user_id=?', 'ii', [(int) $branchId, (int) $u['user_id']]);
        perm_user(true);
    }
}

/** The branch documents are saved under, out of the active branch ids. Used by inv_current_branch and acc_current_branch. */
function perm_pick_branch(array $ids)
{
    $allowed = perm_branch_ids();
    if ($allowed !== null) {
        $ids = array_values(array_intersect($ids, $allowed));
    }
    if (isset($_GET['set_branch']) && in_array((int) $_GET['set_branch'], $ids, true)) {
        $_SESSION['inv_branch_id'] = (int) $_GET['set_branch'];
        perm_set_branch((int) $_GET['set_branch']);
    }
    $current = perm_ready() && perm_user() ? perm_current_branch() : 0;
    $current = $current ?: (int) ($_SESSION['inv_branch_id'] ?? 0);
    return in_array($current, $ids, true) ? $current : ($ids[0] ?? 0);
}

/* ---------------- Rights ---------------- */

/** Every form as key => [category, sub, name, profile]. Registers count as one form per report. */
function perm_forms()
{
    static $forms = null;
    if ($forms !== null) {
        return $forms;
    }
    $forms = [];
    foreach (menu_catalog() as $cat) {
        if (isset($cat['url'])) {
            $forms[$cat['url']] = ['category' => $cat['label'], 'sub' => $cat['label'], 'name' => $cat['label'], 'profile' => $cat['profile']];
            continue;
        }
        foreach ($cat['groups'] as $g) {
            $profile = $g['profile'] ?? $cat['profile'];
            foreach ($g['items'] as $item) {
                if (!empty($g['registers'])) {
                    foreach (menu_register_reports($item[1]) as $key => $caption) {
                        $forms[$key] = ['category' => $cat['label'], 'sub' => $item[0], 'name' => $caption, 'profile' => $profile];
                    }
                } else {
                    $forms[$item[1]] = ['category' => $cat['label'], 'sub' => $g['title'], 'name' => $item[0], 'profile' => $profile];
                }
            }
        }
    }
    return $forms;
}

/** Saved rights of a user at a branch: form key => action => bool. A row for the branch replaces the all-branches row. */
function perm_saved_rights($userId, $branchId)
{
    $map = [];
    $rows = perm_q('SELECT * FROM admin_user_permissions WHERE user_id=? AND branch_id IN (0, ?) ORDER BY branch_id', 'ii', [(int) $userId, (int) $branchId]);
    foreach ($rows as $r) {
        $map[$r['form_key']] = [];
        foreach (PERM_ACTIONS as $action => $label) {
            $map[$r['form_key']][$action] = (int) $r['can_' . $action] === 1;
        }
    }
    return $map;
}

/** Rights of a role: form key => action => bool. */
function perm_role_rights($roleId)
{
    $map = [];
    foreach (perm_q('SELECT * FROM admin_role_permissions WHERE user_role_id=?', 'i', [(int) $roleId]) as $r) {
        foreach (PERM_ACTIONS as $action => $label) {
            $map[$r['form_key']][$action] = (int) $r['can_' . $action] === 1;
        }
    }
    return $map;
}

/** What a user can do at a branch: their role, with their own rows replacing it form by form. */
function perm_effective_rights($userId, $roleId, $branchId)
{
    return perm_saved_rights($userId, $branchId) + perm_role_rights($roleId);
}

function perm_rights()
{
    static $rights = null;
    if ($rights === null) {
        $u = perm_user();
        $rights = $u ? perm_effective_rights((int) $u['user_id'], (int) $u['user_role_id'], perm_current_branch()) : [];
    }
    return $rights;
}

/** Roles that can be given to a user, as user_role_id => role_name. ERP User is the "no role" entry. */
function perm_assignable_roles()
{
    $roles = [];
    foreach (perm_q('SELECT user_role_id, role_name FROM admin_role WHERE id_reseller IS NULL ORDER BY role_name') as $r) {
        if (!in_array($r['role_name'], PERM_SYSTEM_ROLES, true)) {
            $roles[(int) $r['user_role_id']] = $r['role_name'];
        }
    }
    return $roles;
}

/** True when the signed-in user holds every right of a role, so giving it to someone hands out nothing extra. */
function perm_role_within_own($roleId)
{
    foreach (perm_role_rights($roleId) as $key => $actions) {
        foreach ($actions as $action => $on) {
            if ($on && !perm_can($key, $action)) {
                return false;
            }
        }
    }
    return true;
}

function perm_can($formKey, $action)
{
    return perm_is_super() || !empty(perm_rights()[$formKey][$action]);
}

/** Forms of a script, narrowed by the query string when a variant matches (trader_info.php?cat=3, approve.php?doc=sales). */
function perm_forms_for($script)
{
    $all = [];
    $best = [];
    $bestSize = -1;
    foreach (array_keys(perm_forms()) as $key) {
        [$path, $query] = array_pad(explode('?', $key, 2), 2, '');
        if ($path !== $script) {
            continue;
        }
        $all[] = $key;
        parse_str($query, $want);
        foreach ($want as $k => $v) {
            if ((string) ($_GET[$k] ?? '') !== (string) $v) {
                continue 2;
            }
        }
        if (count($want) > $bestSize) {
            $bestSize = count($want);
            $best = [$key];
        }
    }
    return $best ?: $all;
}

/** The form the current page was matched to, for checks inside shared code. */
function perm_can_here($action)
{
    if (perm_is_super()) {
        return true;
    }
    foreach ($GLOBALS['perm_page_forms'] ?? [] as $key) {
        if (perm_can($key, $action)) {
            return true;
        }
    }
    return false;
}

/** Drop the reports of a register the user may not open. */
function perm_filter_reports($register, array $reports)
{
    if (perm_is_super()) {
        return $reports;
    }
    return array_filter($reports, fn($key) => perm_can($register . '?report=' . $key, 'view'), ARRAY_FILTER_USE_KEY);
}

/** Report columns the user may not see: cost and margin need Show Cost, sales prices need Show Price. */
function perm_filter_report_cols(array $cols)
{
    if (perm_is_super()) {
        return $cols;
    }
    $cost = perm_can_here('show_cost');
    $price = perm_can_here('show_price');
    return array_filter($cols, function ($key) use ($cost, $price) {
        return ($cost || !preg_match('/cost|stock_value|profit|margin|purchase_price/', $key)) && ($price || preg_match('/cost|purchase_price/', $key) || !preg_match('/price|rate|mrp/', $key));
    }, ARRAY_FILTER_USE_KEY);
}

/** A report's branch filter: a limited user only ever gets their own branch. */
function perm_report_branch($branchId)
{
    $ids = perm_scope_ids();
    return $ids === null || in_array((int) $branchId, $ids, true) ? (int) $branchId : perm_current_branch();
}

/* ---------------- Page guard ---------------- */

function perm_deny($message)
{
    audit_security('permission_denied', 'Refused: ' . mb_substr((string) $message, 0, 200));
    http_response_code(403);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $text = 'You do not have permission: ' . $message;
    if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== '') {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'error' => $text]));
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>No permission</title><style>body{font-family:system-ui,sans-serif;background:#f5f6fa;margin:0;padding:40px 16px;color:#333}'
        . '.box{max-width:520px;margin:auto;background:#fff;border-radius:10px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,.08)}h1{font-size:20px;margin:0 0 8px}p{margin:0 0 12px}</style></head>'
        . '<body><div class="box"><h1>No permission</h1><p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p><p>Ask your administrator to change your rights in User Access Control.</p>'
        . '<p><a href="javascript:history.back()">Go back</a></p></div></body></html>';
    exit;
}

/** Whether a login may be used now. Returns '' or the reason. */
function perm_login_block(array $u)
{
    if ((int) $u['is_active'] !== 1) {
        return 'This user is not active. Contact your administrator.';
    }
    if ((int) $u['full_time'] !== 1 && $u['start_time'] && $u['end_time']) {
        $now = date('H:i:s');
        $in = $u['start_time'] <= $u['end_time'] ? ($now >= $u['start_time'] && $now <= $u['end_time']) : ($now >= $u['start_time'] || $now <= $u['end_time']);
        if (!$in) {
            return 'You can sign in between ' . date('h:i A', strtotime($u['start_time'])) . ' and ' . date('h:i A', strtotime($u['end_time'])) . '.';
        }
    }
    return '';
}

/** The page's document from PERM_DOCS, or null. */
function perm_doc_ref($script)
{
    foreach (PERM_DOCS as $prefix => $doc) {
        if (str_starts_with($script, $prefix)) {
            return $doc;
        }
    }
    return null;
}

function perm_doc_row(array $doc, $id)
{
    return $id > 0 ? (perm_q('SELECT * FROM ' . $doc[0] . ' WHERE ' . $doc[1] . '=?', 'i', [$id])[0] ?? null) : null;
}

/** Stop when the document belongs to a branch outside the user's. */
function perm_check_doc_branch(array $row)
{
    $ids = perm_scope_ids();
    if ($ids !== null && !empty($row['branch_id']) && !in_array((int) $row['branch_id'], $ids, true)) {
        perm_deny('this document belongs to another branch.');
    }
}

/** Prices a user without Edit Price may charge for a product: the list price and the fixed price levels. */
function perm_list_prices($productId)
{
    $prices = [];
    $rows = array_merge(perm_q('SELECT default_unit_price FROM sales_products WHERE product_id=?', 'i', [$productId]),
        perm_q('SELECT retail_a, retail_b, retail_c, dealer_a, dealer_b, dealer_c, mrp, lower_price, higher_price FROM inv_product_prices WHERE product_id=?', 'i', [$productId]));
    foreach ($rows as $r) {
        foreach ($r as $v) {
            $prices[] = round((float) $v, 2);
        }
    }
    return $prices;
}

/** Checks on what a save posts: dates, charge fields and line prices. */
function perm_check_posted(callable $has)
{
    $today = date('Y-m-d');
    foreach (PERM_DATE_FIELDS as $field) {
        $date = trim((string) ($_POST[$field] ?? ''));
        if ($date !== '' && $date < $today && !$has('edit_back_date')) {
            perm_deny('Edit Back Date Data (the date must be today or later).');
        }
        if ($date !== '' && $date > $today && !$has('advance_date')) {
            perm_deny('Advance Date Data (the date cannot be after today).');
        }
    }
    foreach (PERM_AMOUNT_FIELDS as $field => $action) {
        if ((float) ($_POST[$field] ?? 0) > 0 && !$has($action)) {
            perm_deny(PERM_ACTIONS[$action] . ' (leave it at zero).');
        }
    }
    foreach (['items_json', 'lines_json'] as $field) {
        foreach ((array) json_decode((string) ($_POST[$field] ?? '[]'), true) as $line) {
            if (!is_array($line)) {
                continue;
            }
            if ((float) ($line['discount_amount'] ?? 0) > 0 && !$has('discount')) {
                perm_deny('Discount (leave the line discount at zero).');
            }
            $product = (int) ($line['product_id'] ?? 0);
            if ($field === 'items_json' && $product > 0 && empty($line['source_line_id']) && !$has('edit_price')
                    && !in_array(round((float) ($line['unit_price'] ?? 0), 2), perm_list_prices($product), true)) {
                        perm_deny('Edit Price (use the fixed price of the product).');
                    }
        }
    }
}

/* ---------------- Stores (User Wise Store Fixing, billing/user/user_store_fixing.php) ---------------- */

/** Forms a store rule can name: form key => name. Stock documents and the RMA service screens. */
function perm_store_modules()
{
    $modules = [];
    foreach (perm_forms() as $key => $form) {
        if ($form['profile'] === 'doc' || ($form['category'] === 'Service' && !in_array(basename($key), ['problem.php', 'delivery_store_fixing.php'], true))) {
            $modules[$key] = $form['name'];
        }
    }
    return $modules;
}

/** A branch and every branch above it: a rule on a branch also covers its sub-branches. */
function perm_branch_chain($branchId)
{
    $parents = array_column(perm_q('SELECT branch_id, parent_branch_id FROM branches WHERE parent_branch_id IS NOT NULL'), 'parent_branch_id', 'branch_id');
    $chain = [];
    for ($id = (int) $branchId; $id > 0 && !in_array($id, $chain, true); $id = (int) ($parents[$id] ?? 0)) {
        $chain[] = $id;
    }
    return $chain;
}

/** What rules leave open at these branches and forms: null for every store, else the store names (none = no store). No rules = no limit. */
function perm_store_limit(array $rules, array $branches, array $forms)
{
    if (!$rules) {
        return null;
    }
    $stores = [];
    foreach ($rules as $rule) {
        $branch = (int) $rule['branch_id'];
        if (($branch !== 0 && !in_array($branch, $branches, true)) || ($rule['form_key'] !== '' && !in_array($rule['form_key'], $forms, true))) {
            continue;
        }
        if ($rule['store_name'] === '') {
            return null;
        }
        $stores[] = $rule['store_name'];
    }
    return array_values(array_unique($stores));
}

/** Stores the signed-in user may use on this page: null for every store. Only pages in perm_store_modules() are limited. */
function perm_allowed_stores()
{
    $forms = array_values(array_intersect($GLOBALS['perm_page_forms'] ?? [], array_keys(perm_store_modules())));
    $u = perm_user();
    if (!$forms || !$u || perm_is_super()) {
        return null;
    }
    $rules = perm_q('SELECT branch_id, form_key, store_name FROM admin_user_stores WHERE user_id=?', 'i', [(int) $u['user_id']]);
    return perm_store_limit($rules, perm_branch_chain(perm_current_branch()), $forms);
}

/** Users a fixing page can set for a branch: staff who hold it or may open every branch. SuperAdmin rights are fixed; nobody below that sets their own. */
function perm_fixing_users($branchId)
{
    return perm_q("SELECT u.user_id, u.user_name, u.full_name FROM admin_user u WHERE u.is_active=1 AND u.user_type<>'customer' AND u.access_level<>'SuperAdmin' AND u.user_id<>?
        AND (u.all_branches=1 OR u.default_branch_id=? OR EXISTS (SELECT 1 FROM admin_user_branches b WHERE b.user_id=u.user_id AND b.branch_id=?)) ORDER BY u.user_name",
        'iii', [perm_is_super() ? 0 : (int) perm_user()['user_id'], (int) $branchId, (int) $branchId]);
}

/** Cut a list of store names down to what the user may use. */
function perm_filter_stores(array $stores)
{
    $allowed = perm_allowed_stores();
    return $allowed === null ? $stores : array_values(array_intersect($stores, $allowed));
}

/** Stop a posted document that uses a store the user does not hold. A transfer's Store To stays open: stock may go anywhere. */
function perm_check_stores()
{
    $allowed = perm_allowed_stores();
    if ($allowed === null) {
        return;
    }
    $used = [];
    foreach (['store_name', 'store'] as $field) {
        $store = trim((string) ($_POST[$field] ?? ''));
        if ($store !== '') {
            $used[] = $store;
        }
    }
    foreach (['items_json', 'lines_json'] as $field) {
        foreach ((array) json_decode((string) ($_POST[$field] ?? '[]'), true) as $line) {
            if (is_array($line) && array_key_exists('store_name', $line)) {
                $used[] = trim((string) $line['store_name']);
            }
        }
    }
    foreach (array_unique($used) as $store) {
        if (!in_array($store, $allowed, true)) {
            perm_deny($store === '' ? 'a line without a store. Pick one of your stores.' : 'store "' . $store . '" on this page.');
        }
    }
}

/* ---------------- Ledgers (User Wise Ledger Fixing, billing/user/user_ledger_fixing.php) ---------------- */

/** Pages that pick a ledger. Automatic postings and report totals are left alone, so their figures never change with the user. */
const PERM_LEDGER_PAGES = ['accounts_info/acc_voucher.php', 'accounts_info/acc_rp_voucher.php', 'accounts_info/acc_register.php', 'accounts_info/acc_report.php'];

/** What rules leave open at these branches: null for every ledger, else ids by scope. No rules = no limit. */
function perm_ledger_scopes(array $rules, array $branches)
{
    if (!$rules) {
        return null;
    }
    $open = ['ledger' => [], 'account_group' => [], 'trader_group' => []];
    foreach ($rules as $rule) {
        $branch = (int) $rule['branch_id'];
        if ($branch !== 0 && !in_array($branch, $branches, true)) {
            continue;
        }
        if ($rule['scope'] === 'all') {
            return null;
        }
        $open[$rule['scope']][] = (int) $rule['ref_id'];
    }
    return $open;
}

/** Whether a line may use this account and party. A rule on an account opens its parties; a Trader Group opens only its own parties. */
function perm_ledger_ok($limit, $accountId, $subId = 0)
{
    return $limit === null || isset($limit['accounts'][(int) $accountId]) || (($limit['parties'][(int) $subId] ?? 0) === (int) $accountId && (int) $subId > 0);
}

/** The signed-in user's ledgers at the working branch: null for every ledger, else ['accounts' => id => true, 'parties' => sub ledger id => account id]. */
function perm_ledger_limit()
{
    static $cache = [];
    $u = perm_user();
    if (!$u || perm_is_super()) {
        return null;
    }
    $branch = perm_current_branch();
    if (array_key_exists($branch, $cache)) {
        return $cache[$branch];
    }
    $rules = perm_q('SELECT branch_id, scope, ref_id FROM admin_user_ledgers WHERE user_id=?', 'i', [(int) $u['user_id']]);
    $open = perm_ledger_scopes($rules, perm_branch_chain($branch));
    if ($open === null) {
        return $cache[$branch] = null;
    }
    $list = fn(array $ids) => implode(',', array_map('intval', $ids));
    $accounts = array_map('intval', $open['ledger']);
    if ($open['account_group']) {
        // A group includes the groups under it.
        $children = [];
        foreach (perm_q('SELECT group_id, parent_id FROM acc_groups WHERE parent_id IS NOT NULL') as $g) {
            $children[(int) $g['parent_id']][] = (int) $g['group_id'];
        }
        $todo = $open['account_group'];
        $groups = [];
        while ($todo) {
            $id = (int) array_pop($todo);
            if (!isset($groups[$id])) {
                $groups[$id] = $id;
                array_push($todo, ...($children[$id] ?? []));
            }
        }
        $accounts = array_merge($accounts, array_map('intval', array_column(perm_q('SELECT account_id FROM acc_accounts WHERE group_id IN (' . $list($groups) . ')'), 'account_id')));
    }
    $parties = [];
    if ($open['trader_group']) {
        foreach (perm_q('SELECT s.sub_ledger_id, s.account_id FROM acc_sub_ledgers s JOIN traders t ON t.trader_id=s.trader_id WHERE t.group_id IN (' . $list($open['trader_group']) . ')') as $s) {
            $parties[(int) $s['sub_ledger_id']] = (int) $s['account_id'];
        }
    }
    return $cache[$branch] = ['accounts' => array_fill_keys($accounts, true), 'parties' => $parties];
}

/** Keep the rows (with account_id and, for a party, sub_ledger_id) the user may pick. */
function perm_filter_ledgers(array $rows)
{
    $limit = perm_ledger_limit();
    return $limit === null ? $rows : array_values(array_filter($rows, fn($r) => perm_ledger_ok($limit, $r['account_id'], $r['sub_ledger_id'] ?? 0)));
}

/** Stop a voucher line, or a ledger report or party lookup, for a ledger the user does not hold. */
function perm_check_ledgers()
{
    $limit = perm_ledger_limit();
    if ($limit === null) {
        return;
    }
    $used = [];
    if ((int) ($_POST['under_account'] ?? 0) > 0) {
        $used[] = [(int) $_POST['under_account'], 0];
    }
    foreach (['lines_json', 'entries_json'] as $field) {
        foreach ((array) json_decode((string) ($_POST[$field] ?? '[]'), true) as $line) {
            if (is_array($line) && (int) ($line['account_id'] ?? 0) > 0) {
                $used[] = [(int) $line['account_id'], (int) ($line['sub_ledger_id'] ?? 0)];
            }
        }
    }
    [$kind, $id] = array_pad(explode(':', (string) ($_GET['party'] ?? $_GET['balance'] ?? '')), 2, '0');
    $subId = $kind === 's' ? (int) $id : (int) ($_GET['invoices'] ?? 0);
    if ($subId > 0) {
        $used[] = [(int) (perm_q('SELECT account_id FROM acc_sub_ledgers WHERE sub_ledger_id=?', 'i', [$subId])[0]['account_id'] ?? 0), $subId];
    } elseif ($kind === 'a' && (int) $id > 0) {
        $used[] = [(int) $id, 0];
    }
    foreach ($used as [$accountId, $sub]) {
        if (!perm_ledger_ok($limit, $accountId, $sub)) {
            $name = perm_q('SELECT account_name FROM acc_accounts WHERE account_id=?', 'i', [$accountId])[0]['account_name'] ?? '';
            perm_deny('ledger "' . $name . '". It is not in your ledger list.');
        }
    }
}

/** The right a posted action needs. */
function perm_post_action(array $profile)
{
    $action = strtolower((string) ($_POST['action'] ?? ''));
    if (preg_match('/^(delete|void|cancel|remove|deactivate|detach|unassign)/', $action)) {
        $need = 'delete';
    } elseif ($action === 'edit_price') {
        $need = 'edit_price';
    } elseif (preg_match('/^(edit|update|toggle|disable|enable|activate|approve|reject|post|unpost|reverse|verify|settle|move|rights|reset|unlock|honoured|dishonoured|settings)/', $action)) {
        $need = 'edit';
    } elseif (in_array($action, ['save', 'add', 'create', 'sr_add'], true)) {
        $id = 0;
        foreach (['id', 'invoice_id', 'order_id', 'doc_id', 'return_id', 'voucher_id'] as $field) {
            $id = $id ?: (int) ($_POST[$field] ?? 0);
        }
        $need = $id > 0 ? 'edit' : 'add';
    } else {
        $need = '';
    }
    return $need === '' || !in_array($need, $profile, true) ? '' : $need;
}

/** Stop unless the user may do this on the current page. Pages call it through addon/conf/connection.php or admin_bootstrap.php. */
function perm_guard($requireLogin = false)
{
    $file = str_replace('\\', '/', (string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
    $base = str_replace('\\', '/', dirname(__DIR__, 2)) . '/billing/';
    if (!str_starts_with($file, $base) || !perm_ready()) {
        return;
    }
    $script = substr($file, strlen($base));
    $u = perm_user();
    if (!$u) {
        if ($requireLogin) {
            header('Location: ' . str_repeat('../', substr_count($script, '/') + 1) . 'index.php');
            exit;
        }
        return;
    }
    // Older pages read these cookies directly; make them say what the session says.
    $_COOKIE['user_id_admin'] = (string) $u['user_id'];
    $_COOKIE['ID_my_site_admin'] = (string) $u['user_name'];
    perm_page_links_start('billing/' . $script);

    if (($block = perm_login_block($u)) !== '') {
        perm_q("UPDATE admin_user SET sesid='', lastaccess=0 WHERE user_id=?", 'i', [(int) $u['user_id']]);
        setcookie('admin_sesid', '', 0, '/');
        exit('<script>alert(' . json_encode($block) . ');top.location.href=' . json_encode(str_repeat('../', substr_count($script, '/') + 1) . 'index.php') . ';</script>');
    }
    licence_guard($script);
    if (perm_is_super() || in_array($script, PERM_ALWAYS, true)) {
        return;
    }
    if ($u['user_type'] === 'customer') {
        perm_deny('this page is for staff.');
    }

    $forms = perm_forms_for($script);
    $GLOBALS['perm_page_forms'] = $forms;
    if (!$forms) {
        // A helper script is allowed only next to a form the user holds; folders with no form stay closed.
        $dir = dirname($script) . '/';
        foreach (array_keys(perm_rights()) as $key) {
            if (str_starts_with($key, $dir) && !in_array($dir, PERM_NO_HELPERS, true) && perm_can($key, 'view')) {
                return;
            }
        }
        perm_deny('this page is not part of your menu.');
    }
    $has = function ($action) use ($forms) {
        foreach ($forms as $key) {
            if (perm_can($key, $action)) {
                return true;
            }
        }
        return false;
    };
    if (!$has('view')) {
        perm_deny('View ' . perm_forms()[$forms[0]]['name'] . '.');
    }
    $profile = PERM_PROFILES[perm_forms()[$forms[0]]['profile']];
    $doc = perm_doc_ref($script);

    if ($doc) {
        foreach (PERM_DOC_GET_KEYS as $key) {
            if (($id = (int) ($_GET[$key] ?? 0)) > 0 && ($row = perm_doc_row($doc, $id))) {
                perm_check_doc_branch($row);
            }
        }
    }

    if (in_array($script, PERM_LEDGER_PAGES, true)) {
        perm_check_ledgers();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $need = perm_post_action($profile);
        if ($need !== '' && !$has($need)) {
            perm_deny(PERM_ACTIONS[$need] . ' ' . perm_forms()[$forms[0]]['name'] . '.');
        }
        if ($need === '' && array_intersect(['add', 'edit'], $profile) && !$has('add') && !$has('edit')) {
            perm_deny('Add or Edit ' . perm_forms()[$forms[0]]['name'] . '.');
        }
        if ($doc && in_array('edit_back_date', $profile, true)) {
            perm_check_posted($has);
        }
        perm_check_stores();
        $row = $doc ? perm_doc_row($doc, (int) ($_POST[$doc[2]] ?? 0)) : null;
        if ($row) {
            perm_check_doc_branch($row);
            if ((string) ($row[$doc[3]] ?? '') === $doc[4] && !$has('edit_after_app')) {
                perm_deny('Edit After App (this document is approved).');
            }
            if (!$has('edit_after_print') && perm_q('SELECT 1 AS x FROM admin_doc_prints WHERE doc_table=? AND doc_id=?', 'si', [$doc[0], (int) $row[$doc[1]]])) {
                perm_deny('Edit After Print (this document has been printed).');
            }
        }
    } elseif (array_intersect(array_keys($_GET), ['print', 'challan', 'receipt']) && in_array('print', $profile, true)) {
        if (!$has('print') && !$has('preview')) {
            perm_deny('Preview or Print ' . perm_forms()[$forms[0]]['name'] . '.');
        }
        $id = (int) ($_GET['print'] ?? $_GET['challan'] ?? $_GET['receipt']);
        $row = $doc ? perm_doc_row($doc, $id) : null;
        if ($row) {
            if ((string) ($row[$doc[3]] ?? '') !== $doc[4] && !$has('print_before_app')) {
                perm_deny('Print Before App (this document is not approved yet).');
            }
            perm_q('INSERT IGNORE INTO admin_doc_prints (doc_table, doc_id, printed_by) VALUES (?, ?, ?)', 'sii', [$doc[0], $id, (int) $u['user_id']]);
        }
    }

    $hide = [];
    foreach (PERM_HIDE as $action => $selector) {
        if (in_array($action, $profile, true) && !$has($action)) {
            $hide[] = $selector;
        }
    }
    if (in_array('edit_price', $profile, true) && !$has('edit_price')) {
        $hide[] = 'button[value="edit_price"],#edit-price,[data-price]';
    }
    if ($hide) {
        $css = '<style>' . implode(',', $hide) . '{display:none!important}' . (in_array('edit_price', $profile, true) && !$has('edit_price') ? '#e_price{pointer-events:none;background:#eee}' : '') . '</style>';
        ob_start(fn($html) => str_contains($html, '</head>') ? str_replace('</head>', $css . '</head>', $html) : $html);
    }
}

/* ---------------- Menu links ---------------- */

/** Token for a menu page, so the menu does not print the page's path. Links only; rights are still checked on the page. */
function perm_link($url)
{
    return 'go.php?k=' . substr(hash_hmac('sha256', $url, 'menu-link' . ($GLOBALS['dbpass'] ?? '')), 0, 40);
}

/** The page behind a token from perm_link(), or '' when it is not a menu page. */
function perm_link_target($token)
{
    $urls = ['user/no_access.php', 'user/registration.php'];
    foreach (menu_catalog() as $cat) {
        if (isset($cat['url'])) {
            $urls[] = $cat['url'];
        }
        foreach ($cat['groups'] ?? [] as $g) {
            $urls = array_merge($urls, array_column($g['items'], 1));
        }
    }
    foreach (['customer', 'employee'] as $type) {
        $portal = menu_portal_pages($type);
        foreach ($portal['pages'] as $page) {
            $urls[] = $portal['dir'] . $page[2];
        }
    }
    foreach ($urls as $url) {
        if (hash_equals(perm_link($url), 'go.php?k=' . $token)) {
            return $url;
        }
    }
    return '';
}

/* ---------------- Links inside pages ---------------- */

/** Key for page link tokens. It comes from the login session, so a copied token stops working at sign-out. */
function perm_page_key()
{
    $u = perm_user();
    return $u && (string) $u['sesid'] !== '' ? hash('sha256', 'page-link|' . $u['sesid'], true) : '';
}

/** Encrypted token for a project-relative page such as billing/sales/sales_order/sales_order.php?id=4. */
function perm_page_token($target, $key)
{
    $iv = random_bytes(12);
    $tag = '';
    $data = openssl_encrypt($target, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return rtrim(strtr(base64_encode($iv . $tag . $data), '+/', '-_'), '=');
}

/** The page inside a token, or '' when it is not one of this session's tokens. */
function perm_page_target($token)
{
    $key = perm_page_key();
    $raw = base64_decode(strtr((string) $token, '-_', '+/'), true);
    if ($key === '' || $raw === false || strlen($raw) < 29) {
        return '';
    }
    $target = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $target === false ? '' : $target;
}

/** Project-relative page for a relative link on a page in $dir, or '' when the link stays as it is. */
function perm_page_link_target($url, $dir)
{
    $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
    if (!preg_match('/^[A-Za-z0-9_.\/-]+\.php(\?[^\s"\'<>`]*)?(#.*)?$/', $url) || $url[0] === '/' || preg_match('/(^|\/)logout\.php/', $url) || preg_match('/[{}$]/', $url)) {
        return '';
    }
    [$path, $rest] = array_pad(explode('?', $url, 2), 2, null);
    $parts = $dir === '' ? [] : explode('/', $dir);
    foreach (explode('/', $path) as $part) {
        if ($part === '..') {
            if (!$parts) {
                return '';
            }
            array_pop($parts);
        } elseif ($part !== '.' && $part !== '') {
            $parts[] = $part;
        }
    }
    return implode('/', $parts) . ($rest !== null ? '?' . $rest : '');
}

/** Replace page paths in <a href> and in onclick location changes. Script blocks are left alone: they build URLs at run time. */
function perm_hide_page_links($html, $script, $key)
{
    $dir = dirname($script);
    $dir = $dir === '.' ? '' : $dir;
    $go = str_repeat('../', substr_count($script, '/')) . 'billing/go.php?t=';
    $swap = function ($url) use ($dir, $go, $key) {
        $target = perm_page_link_target($url, $dir);
        return $target === '' ? null : $go . perm_page_token($target, $key);
    };
    $parts = preg_split('~(<script\b.*?</script>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $html;
    }
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            continue;
        }
        $done = preg_replace_callback('~(<a\b[^>]*?\bhref\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+))~i', function ($m) use ($swap) {
            $url = $m[4] ?? $m[3] ?? $m[2]; // trailing groups that did not match are left out of $m
            $new = $swap($url);
            return $new === null ? $m[0] : $m[1] . '"' . $new . '"';
        }, $part);
        $done = $done === null ? $part : preg_replace_callback('~(\bon[cC]lick\s*=\s*"[^"]*?\b(?:window\.)?location(?:\.href)?\s*=\s*\')([^\'"]+)(\'\s*(?:;[^"]*)?")~', function ($m) use ($swap) {
            $new = $swap($m[2]);
            return $new === null ? $m[0] : $m[1] . $new . $m[3];
        }, $done);
        $parts[$i] = $done ?? $part;
    }
    return implode('', $parts);
}

/** Filter this page's HTML through perm_hide_page_links. Downloads and JSON pass through untouched. */
function perm_page_links_start($script)
{
    static $started = false;
    $key = perm_page_key();
    if ($started || $key === '' || $script === 'billing/topmain.php' || $script === 'billing/go.php' || $script === 'billing/user/dump_mysql.php') {
        return;
    }
    $started = true;
    ob_start(function ($html) use ($script, $key) {
        foreach (headers_list() as $header) {
            if (preg_match('/^content-disposition:|^content-type:\s*+(?!text\/html)/i', $header)) {
                return $html;
            }
        }
        return stripos($html, '<a') === false && stripos($html, 'onclick') === false ? $html : perm_hide_page_links($html, $script, $key);
    });
}

/* ---------------- Sign-in ---------------- */

/** Password check for admin_user: bcrypt for new users, md5 for the older rows. */
function perm_password_ok($password, $stored)
{
    return str_starts_with((string) $stored, '$') ? password_verify((string) $password, (string) $stored) : hash_equals((string) $stored, md5((string) $password));
}

function perm_portal_table($type)
{
    return $type === 'customer' ? ['portal_accounts', 'customer_id'] : ['sr_portal_accounts', 'sr_id'];
}

/** The portal account row of a customer or employee user. */
function perm_portal_account(array $u)
{
    [$table, $col] = perm_portal_table($u['user_type']);
    return perm_q("SELECT * FROM $table WHERE $col=?", 'i', [(int) $u['linked_id']])[0] ?? null;
}

/** Password check for customer / employee users: the portal account holds the password. Returns '' or the reason. */
function perm_portal_password($type, array $acc, $password)
{
    [$table] = perm_portal_table($type);
    $id = (int) $acc['account_id'];
    if ($acc['locked_until'] && strtotime($acc['locked_until']) > time()) {
        return 'This login is locked after too many wrong passwords. Try again later.';
    }
    if (!password_verify((string) $password, (string) $acc['password_hash'])) {
        perm_q("UPDATE $table SET failed_attempts=failed_attempts+1, locked_until=IF(failed_attempts>=5, NOW() + INTERVAL 15 MINUTE, NULL) WHERE account_id=?", 'i', [$id]);
        return 'Incorrect user name or password.';
    }
    if ($acc['status'] !== 'active') {
        return 'This account is disabled. Please contact the office.';
    }
    perm_q("UPDATE $table SET failed_attempts=0, locked_until=NULL, last_login_at=NOW(), last_login_ip=? WHERE account_id=?", 'si', [substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), $id]);
    return '';
}

/** Sign-in checks for an admin_user row. Returns '' or the message; on success the picked branch becomes the working branch. */
function perm_signin_error($u, $password, $branchId)
{
    if (!$u) {
        return '';
    }
    if ($u['user_type'] === 'staff') {
        if (!perm_password_ok($password, $u['user_password'])) {
            return 'Incorrect user name or password.';
        }
        if (!str_starts_with((string) $u['user_password'], '$') || password_needs_rehash((string) $u['user_password'], PASSWORD_DEFAULT)) {
            perm_q('UPDATE admin_user SET user_password=? WHERE user_id=?', 'si', [password_hash((string) $password, PASSWORD_DEFAULT), (int) $u['user_id']]); // old MD5 passwords move to a real hash at sign-in
        }
    } else {
        $acc = perm_portal_account($u);
        $error = $acc ? perm_portal_password($u['user_type'], $acc, $password) : 'This login has no portal account.';
        if ($error !== '') {
            return $error;
        }
    }
    if (($block = perm_login_block($u)) !== '') {
        return $block;
    }
    licence_tick();
    if (licence_status()['locked'] && !($u['access_level'] === 'SuperAdmin' && $u['user_type'] === 'staff')) { // the SuperAdmin signs in to see why
        return licence_status()['message'];
    }
    if ($u['user_type'] !== 'customer') {
        $ids = perm_user_branch_ids($u);
        if ($ids !== null && !$ids) {
            return 'No branch is assigned to this user. Contact your administrator.';
        }
        if ($ids !== null && $branchId > 0 && !in_array($branchId, $ids, true)) {
            return 'You have no access to this branch.';
        }
        $pick = $branchId ?: (int) $u['default_branch_id'];
        perm_q('UPDATE admin_user SET current_branch_id=? WHERE user_id=?', 'ii', [$pick ?: null, (int) $u['user_id']]);
    }
    return '';
}

function perm_role_id()
{
    return (int) (perm_q("SELECT user_role_id FROM admin_role WHERE role_name='ERP User' LIMIT 1")[0]['user_role_id'] ?? 0);
}

/** The admin_user row for a portal account, added the first time so every portal login gets the shell. */
function perm_shell_user($type, array $acc)
{
    [, $col] = perm_portal_table($type);
    $linked = (int) $acc[$col];
    $u = perm_q('SELECT * FROM admin_user WHERE user_type=? AND linked_id=? LIMIT 1', 'si', [$type, $linked])[0] ?? null;
    if (!$u) {
        $name = (string) $acc['login'];
        if (perm_q('SELECT 1 AS x FROM admin_user WHERE user_name=?', 's', [$name])) {
            $name = ($type === 'customer' ? 'cust_' : 'emp_') . $name;
        }
        perm_q("INSERT INTO admin_user (user_name, user_password, user_role_id, access_level, user_type, linked_id, is_active) VALUES (?, '!', ?, 'Operator', ?, ?, 1)", 'sisi', [$name, perm_role_id(), $type, $linked]);
        $u = perm_q('SELECT * FROM admin_user WHERE user_type=? AND linked_id=? LIMIT 1', 'si', [$type, $linked])[0] ?? null;
    }
    return $u;
}

/** Start the shell session of a user (same cookies as an administrator sign-in). */
function perm_start_session(array $u)
{
    $sesid = bin2hex(random_bytes(16));
    $id = (int) $u['user_id'];
    perm_q('UPDATE admin_user SET sesid=?, lastaccess=? WHERE user_id=?', 'sii', [$sesid, time(), $id]);
    perm_q("INSERT INTO login_log (login_id, log_date, login_time, ip_address, user_type) VALUES (?, CURDATE(), NOW(), ?, '0')", 'ss', [(string) $u['user_name'], (string) ($_SERVER['REMOTE_ADDR'] ?? '')]);
    $log = (int) (perm_q('SELECT MAX(logid) AS id FROM login_log WHERE login_id=?', 's', [(string) $u['user_name']])[0]['id'] ?? 0);
    setcookie('admin_sesid', $sesid, 0, '/');
    setcookie('ID_my_site_admin', (string) $u['user_name'], 0, '/');
    setcookie('user_id_admin', (string) $id, 0, '/');
    setcookie('logid', (string) $log, 0, '/');
    if (!isset($GLOBALS['audit_actor'])) { // a portal's own sign-in has already recorded this
        audit_login(AUDIT_ACTORS[$u['user_type']] ?? false ? $u['user_type'] : 'staff', $id, (string) $u['user_name'], true);
    }
}

/** Sign-in-page lookup for a portal login that has no admin_user row yet. Returns [user row or null, message]. */
function perm_portal_login($login, $password)
{
    foreach (['customer', 'employee'] as $type) {
        [$table] = perm_portal_table($type);
        $acc = perm_q("SELECT * FROM $table WHERE login=?", 's', [(string) $login])[0] ?? null;
        if ($acc) {
            $error = perm_portal_password($type, $acc, $password);
            return $error === '' ? [perm_shell_user($type, $acc), ''] : [null, $error];
        }
    }
    return [null, 'Incorrect user name or password.'];
}

/** After a portal's own login page: start the shell and return true, or false to stay on the plain portal. */
function perm_portal_open_shell($type, $accountId)
{
    [$table] = perm_portal_table($type);
    $acc = perm_ready() ? (perm_q("SELECT * FROM $table WHERE account_id=?", 'i', [(int) $accountId])[0] ?? null) : null;
    $u = $acc ? perm_shell_user($type, $acc) : null;
    if (!$u || perm_login_block($u) !== '') {
        return false;
    }
    perm_start_session($u);
    return true;
}

/**
 * Inside the shell the portals run in the frame: a valid shell session of the matching customer / employee signs the
 * portal session in, and framing by this site is allowed. Portal pages without a shell session keep their own login.
 */
function perm_portal_bridge($sessionKey, $idleMinutes)
{
    $type = $sessionKey === 'cp_account' ? 'customer' : 'employee';
    $u = perm_ready() ? perm_user() : null;
    $valid = $u && $u['user_type'] === $type && (int) $u['lastaccess'] + $idleMinutes * 60 >= time() && perm_login_block($u) === '';
    $acc = $valid ? perm_portal_account($u) : null;
    if (!$acc) {
        if (!empty($_SESSION['shell_embedded'])) {
            $_SESSION = [];
        }
        return;
    }
    $_SESSION[$sessionKey] = (int) $acc['account_id'];
    $_SESSION['cp_seen'] = time();
    $_SESSION['shell_embedded'] = 1;
    perm_q('UPDATE admin_user SET lastaccess=? WHERE user_id=?', 'ii', [time(), (int) $u['user_id']]);
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'none'");
    $root = str_replace('\\', '/', dirname(__DIR__, 2)) . '/';
    perm_page_links_start(substr(str_replace('\\', '/', (string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))), strlen($root)));
}

require_once __DIR__ . '/notify.php'; // every page that can raise a notification has this file loaded
require_once __DIR__ . '/licence.php';
