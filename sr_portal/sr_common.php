<?php
/**
 * SR / Staff Portal bootstrap: SR sign-in, layout and data rules on top of addon/inc/portal_base.php.
 * An SR sees only the customers assigned to them (Trader wise SR Fixing / Customer Profile) and the sales credited to
 * them; an account with "see all customers" (supervisor, office staff) sees every customer.
 * Tables: sr_portal/schema.sql, plus the sales, customer and sales person schemas.
 */
const PORTAL_SESSION_NAME = 'SRSESSID';
require_once dirname(__DIR__) . '/addon/inc/portal_base.php';

const SR_AUTH = ['accounts' => 'sr_portal_accounts', 'log' => 'sr_portal_login_log', 'session' => 'sr_account',
    'mobile_sql' => "SELECT a.* FROM sr_portal_accounts a JOIN sales_representatives s ON s.sr_id=a.sr_id WHERE RIGHT(REGEXP_REPLACE(s.mobile, '[^0-9]', ''), 10)=? LIMIT 1"];
const SR_METHODS = ['cash' => 'Cash', 'bank' => 'Bank / Cheque', 'mobile' => 'Mobile Banking', 'other' => 'Other'];
/** Invoice v belongs to SR s (same rule as Sales Person Management). */
const SR_MATCH = "(v.sr_id = ? OR (v.sr_id IS NULL AND v.sales_person <> '' AND v.sales_person = ?))";

function sr_ready()
{
    static $ready = null;
    if ($ready === null) {
        $ready = true;
        foreach (['sr_portal_accounts', 'sr_portal_login_log', 'sr_collections', 'sr_collection_allocations', 'sales_representatives', 'sr_targets', 'sr_commission_payments', 'customer_accounts', 'customer_ledger_entries', 'sales_orders'] as $t) {
            $ready = $ready && cp_table_exists($t);
        }
    }
    return $ready;
}

/** The signed-in account with its SR (sr_name, sr_code, rates ...), or null. Ends idle or disabled sessions. */
function sr_account()
{
    $id = (int) ($_SESSION['sr_account'] ?? 0);
    if ($id <= 0 || !sr_ready()) {
        return null;
    }
    if (time() - (int) ($_SESSION['cp_seen'] ?? 0) > CP_IDLE_MINUTES * 60) {
        cp_sign_out();
        cp_flash('err', 'You were signed out after ' . CP_IDLE_MINUTES . ' minutes without activity.');
        return null;
    }
    $row = cp_row("SELECT a.account_id, a.login, a.must_change_password, a.can_book_orders, a.can_collect, a.see_all_customers, s.*, COALESCE(t.territory_name, '') AS territory_name
        FROM sr_portal_accounts a JOIN sales_representatives s ON s.sr_id=a.sr_id LEFT JOIN sr_territories t ON t.territory_id=s.territory_id
        WHERE a.account_id=? AND a.status='active' AND s.is_active=1", 'i', [$id]);
    if (!$row) {
        cp_sign_out();
        return null;
    }
    $_SESSION['cp_seen'] = time();
    return $row;
}

function sr_require_login($passwordPage = false)
{
    if (!sr_ready()) {
        sr_page_start('Portal not installed', '', true);
        echo '<div class="alert err">The SR portal is not installed yet. Office: import <code>sr_portal/schema.sql</code> after the customer and sales person schemas.</div>';
        sr_page_end();
        exit;
    }
    $a = sr_account();
    if (!$a) {
        cp_redirect('login.php');
    }
    licence_portal_guard('sr_portal');
    if (!$passwordPage && (int) $a['must_change_password'] === 1) {
        cp_redirect('profile.php', 'err', 'Please choose your own password before continuing.');
    }
    $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!empty($a['staff_only']) && !in_array($page, MENU_STAFF_PAGES, true) && !str_starts_with($page, 'hr_')) {
        cp_redirect('hr.php'); // sales pages are for people who sell
    }
    return $a;
}

/* ---------------- What the SR may see ---------------- */

/** SQL condition (on customer_accounts c) for the customers this account may see, with its params. */
function sr_customer_scope(array $a)
{
    if ((int) $a['see_all_customers']) {
        return ['1=1', '', []];
    }
    return ['c.trader_id IN (SELECT trader_id FROM trader_sr_assignments WHERE sr_id=? AND effective_to IS NULL)', 'i', [(int) $a['sr_id']]];
}

/** The customer row when this account may see it, else null. */
function sr_customer(array $a, $customerId)
{
    [$w, $t, $p] = sr_customer_scope($a);
    return cp_row("SELECT c.* FROM customer_accounts c WHERE c.customer_id=? AND $w", 'i' . $t, array_merge([(int) $customerId], $p));
}

/** Customers this account may see (for lists and pickers). */
function sr_customers(array $a, $q = '', $limit = 500)
{
    [$w, $t, $p] = sr_customer_scope($a);
    $sql = "SELECT c.* FROM customer_accounts c WHERE $w AND c.is_active=1";
    if ($q !== '') {
        $sql .= ' AND (c.customer_name LIKE ? OR c.mobile LIKE ? OR c.trader_code LIKE ?)';
        $t .= 'sss';
        array_push($p, "%$q%", "%$q%", "%$q%");
    }
    return cp_rows($sql . ' ORDER BY c.customer_name LIMIT ' . (int) $limit, $t, $p);
}

/* ---------------- Month figures and commission (same rules as Sales Person Management) ---------------- */

/** Net sales, returns, collection and gross profit credited to the SR in a month (first day given). */
function sr_month_figures(array $a, $month)
{
    $from = $month;
    $to = date('Y-m-t', strtotime($month));
    $args = [(int) $a['sr_id'], $a['sr_name'], $from, $to];
    $one = fn($sql) => (float) (cp_row($sql, 'isss', $args)['v'] ?? 0);
    $sales = $one('SELECT COALESCE(SUM(v.net_amount), 0) AS v FROM sales_invoices v WHERE ' . SR_MATCH . " AND v.status='posted' AND v.sales_date BETWEEN ? AND ?");
    $invoices = (int) $one('SELECT COUNT(*) AS v FROM sales_invoices v WHERE ' . SR_MATCH . " AND v.status='posted' AND v.sales_date BETWEEN ? AND ?");
    $returns = $one('SELECT COALESCE(SUM(r.return_amount), 0) AS v FROM sales_invoices v JOIN sales_returns r ON r.invoice_id=v.invoice_id AND r.status=\'posted\' WHERE ' . SR_MATCH . " AND v.status='posted' AND r.return_date BETWEEN ? AND ?");
    $collection = $one('SELECT COALESCE(SUM(p.amount), 0) AS v FROM sales_invoices v JOIN sales_invoice_payments p ON p.invoice_id=v.invoice_id WHERE ' . SR_MATCH . " AND v.status='posted' AND DATE(p.paid_at) BETWEEN ? AND ?");
    $gp = $one('SELECT COALESCE(SUM(i.line_total * (CASE WHEN v.subtotal > 0 THEN 1 - v.discount_amount / v.subtotal ELSE 1 END) - (i.quantity + i.free_quantity) * i.unit_cost), 0) AS v '
            . 'FROM sales_invoices v JOIN sales_invoice_items i ON i.invoice_id=v.invoice_id WHERE ' . SR_MATCH . " AND v.status='posted' AND v.sales_date BETWEEN ? AND ?");
    $target = cp_row('SELECT sales_target, collection_target FROM sr_targets WHERE sr_id=? AND target_month=?', 'is', [(int) $a['sr_id'], $month]) ?? ['sales_target' => 0, 'collection_target' => 0];
    $net = round($sales - $returns, 2);
    $base = match ($a['commission_basis']) { 'net_sales' => $net, 'collection' => $collection, 'gross_profit' => $gp, default => 0.0 };
    $reached = (float) $target['sales_target'] > 0 && $net >= (float) $target['sales_target'];
    $commission = round(max(0.0, $base) * (float) $a['commission_rate'] / 100, 2);
    $bonus = $reached ? round($net * (float) $a['target_bonus_rate'] / 100, 2) : 0.0;
    $paid = (float) cp_row('SELECT COALESCE(SUM(amount), 0) AS v FROM sr_commission_payments WHERE sr_id=? AND period_month=?', 'is', [(int) $a['sr_id'], $month])['v'];
    return ['invoices' => $invoices, 'sales' => $sales, 'returns' => $returns, 'net_sales' => $net, 'collection' => $collection, 'gross_profit' => $gp,
        'sales_target' => (float) $target['sales_target'], 'collection_target' => (float) $target['collection_target'], 'target_reached' => $reached,
        'commission' => $commission, 'bonus' => $bonus, 'total' => $commission + $bonus, 'paid' => $paid, 'due' => round($commission + $bonus - $paid, 2)];
}

function sr_pct($done, $target)
{
    return (float) $target > 0 ? round((float) $done * 100 / (float) $target) . '%' : '—';
}

/** First day of the month from ?month=YYYY-MM (default this month). */
function sr_month($value)
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value) ? $value . '-01' : date('Y-m-01');
}

/* ---------------- Layout ---------------- */

const SR_MENU = ['index' => ['index.php', 'Dashboard'], 'customers' => ['customers.php', 'My Customers'], 'sales' => ['sales.php', 'My Sales'], 'orders' => ['orders.php', 'Book Order'],
    'collections' => ['collections.php', 'Collections'], 'performance' => ['performance.php', 'Target & Commission'], 'stock' => ['stock.php', 'Stock & Price'], 'hr' => ['hr.php', 'My HR'], 'profile' => ['profile.php', 'Profile']];

function sr_company()
{
    $c = ['name' => 'SR Portal', 'phone' => '', 'address' => '', 'email' => ''];
    if (cp_table_exists('acc_settings')) {
        foreach (cp_rows("SELECT setting_key, setting_value FROM acc_settings WHERE setting_key IN ('company_name','company_address','company_phone','company_email')") as $r) {
            if ($r['setting_value'] !== '') {
                $c[substr($r['setting_key'], 8)] = $r['setting_value'];
            }
        }
    }
    return $c;
}

function sr_page_start($title, $active, $public = false)
{
    $c = sr_company();
    $a = $public ? null : sr_account();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="copyright" content="&copy; Petacore Technology. PetaBill is a trademark of Petacore Technology."><title>' . cp_h($title . ' · SR Portal') . '</title>'
        . '<link rel="stylesheet" href="../addon/portal/portal.css"></head><body>';
    if (empty($_SESSION['shell_embedded'])) {
        echo '<header class="top"><div class="brand"><b>' . cp_h($c['name']) . '</b><span>SR / Staff Portal</span></div>';
        if ($a) {
            $unread = notify_unread(['type' => 'sr', 'id' => (int) $a['sr_id']]);
            echo '<div class="who"><a class="bell" href="notifications.php" aria-label="Notifications' . ($unread ? ', ' . $unread . ' unread' : '') . '">&#128276;' . ($unread ? '<span>' . $unread . '</span>' : '') . '</a><span>' . cp_h($a['sr_name']) . ($a['territory_name'] ? ' · ' . cp_h($a['territory_name']) : '') . '</span><a class="btn light" href="logout.php">Sign out</a></div>';
        }
        echo '</header>';
        if ($a) {
            echo '<nav class="menu">';
            foreach (SR_MENU as $key => [$href, $label]) {
                if (($key === 'orders' && !(int) $a['can_book_orders']) || ($key === 'collections' && !(int) $a['can_collect']) || (!empty($a['staff_only']) && !in_array($href, MENU_STAFF_PAGES, true))) {
                    continue;
                }
                echo '<a href="' . $href . '"' . ($key === $active ? ' class="active"' : '') . '>' . cp_h($label) . '</a>';
            }
            echo '</nav>';
        }
    }
    echo '<main class="page' . ($public ? ' narrow' : '') . '"><h1>' . cp_h($title) . '</h1>' . cp_take_flash();
}

function sr_page_end()
{
    echo '</main>';
    if (empty($_SESSION['shell_embedded'])) {
        echo '<footer class="foot">' . cp_h(sr_company()['name']) . ' · SR / Staff Portal<br><small class="powered">Powered by PetaBill &middot; &copy; ' . date('Y') . ' Petacore Technology</small></footer>';
    }
    echo '<script src="../addon/portal/portal.js"></script><script src="../addon/portal/list-pager.js"></script></body></html>';
}

/** Next number like CL20261030001 / for a table and column. */
function sr_next_no($prefix, $table, $col)
{
    return docno_next(docno_db(), $prefix); // Settings > Document Numbering (CL: collections); never a duplicate
}

/* ---------------- Products for orders and the stock page (no cost prices) ---------------- */

/** Product rows with the price for a price level, the floor (lower price) and stock in all stores and the default store. */
function sr_products($q = '', array $ids = [], $level = 'retail', $limit = 200)
{
    $store = (string) (cp_row("SELECT setting_value FROM inv_settings WHERE setting_key='default_store'")['setting_value'] ?? 'Main Store');
    $sql = 'SELECT p.product_id, p.product_code, p.product_group, p.brand, p.model_caption, p.description, p.unit_name, p.warranty, p.default_unit_price,
            COALESCE(pp.dealer_a, 0) AS dealer_a, COALESCE(pp.mrp, 0) AS mrp, COALESCE(pp.lower_price, 0) AS lower_price,
            COALESCE((SELECT SUM(m.qty_in - m.qty_out) FROM inv_stock_moves m WHERE m.product_id=p.product_id), 0) AS stock_all,
            COALESCE((SELECT SUM(m.qty_in - m.qty_out) FROM inv_stock_moves m WHERE m.product_id=p.product_id AND m.store_name=?), 0) AS stock_store
        FROM sales_products p LEFT JOIN inv_product_prices pp ON pp.product_id=p.product_id WHERE p.is_active=1';
    $t = 's';
    $p = [$store];
    if ($ids) {
        $sql .= ' AND p.product_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $t .= str_repeat('i', count($ids));
        array_push($p, ...array_map('intval', $ids));
    }
    if ($q !== '') {
        $sql .= ' AND (p.product_code LIKE ? OR p.model_caption LIKE ? OR p.description LIKE ? OR p.brand LIKE ? OR p.product_group LIKE ?)';
        $t .= 'sssss';
        array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
    }
    $rows = cp_rows($sql . ' ORDER BY p.product_group, p.brand, p.model_caption LIMIT ' . (int) $limit, $t, $p);
    foreach ($rows as &$r) {
        $r['price'] = $level === 'dealer' && (float) $r['dealer_a'] > 0 ? (float) $r['dealer_a'] : (float) $r['default_unit_price'];
        $r['store'] = $store;
        $r['label'] = trim($r['product_code'] . ' — ' . trim($r['model_caption'] . ' ' . $r['description']));
    }
    return $rows;
}

/* ---------------- My HR (addon/inc/hr.php) ---------------- */

/** The signed-in person's HR record, with department and designation names, or null. */
function sr_hr_employee(array $a)
{
    if (!cp_table_exists('hr_employees') || !cp_table_exists('hr_payslips')) {
        return null;
    }
    return cp_row("SELECT e.*, COALESCE(d.department_name, '') AS department_name, COALESCE(g.designation_name, '') AS designation_name, COALESCE(m.full_name, '') AS manager_name FROM hr_employees e
        LEFT JOIN hr_departments d ON d.department_id=e.department_id LEFT JOIN hr_designations g ON g.designation_id=e.designation_id LEFT JOIN hr_employees m ON m.employee_id=e.reports_to WHERE e.sr_id=?", 'i', [(int) $a['sr_id']]);
}

/** Start a My HR page: the shared tabs, or a notice when the office has not added this person to HR. Returns the employee. */
function sr_hr_page_start(array $a, $title, $active)
{
    sr_page_start($title, 'hr');
    $e = sr_hr_employee($a);
    if (!$e) {
        echo '<div class="alert info">You are not in HR yet. Please ask the office to add you.</div>';
        sr_page_end();
        exit;
    }
    echo '<nav class="tabs">';
    foreach (['hr' => ['hr.php', 'Profile'], 'attendance' => ['hr_attendance.php', 'Attendance'], 'leave' => ['hr_leave.php', 'Leave'], 'salary' => ['hr_salary.php', 'Salary'], 'records' => ['hr_records.php', 'My Records']] as $key => [$href, $label]) {
        echo '<a class="btn' . ($key === $active ? '' : ' light') . ' small" href="' . $href . '">' . $label . '</a>';
    }
    echo '</nav>';
    return $e;
}
