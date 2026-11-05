<?php
/**
 * Shared code for Customer Management (billing/customer, formerly the VoIP IVR Clients pages): customer profile, credit
 * limit and ledger. A customer is a sales_customers row (what every sales screen uses); the full profile is a
 * customer-type trader synced to it, and credit rules are trader_flags. Tables: trader/schema.sql, customer/schema.sql.
 */
require_once dirname(__DIR__) . '/trader/trader_common.php';
require_once dirname(__DIR__) . '/inventory/inv_common.php'; // inv_fmt_date, inv_print_head, INV_PRINT_CSS

/** Permission flags in admin_role.customer_mgmt: view, add, edit, delete, credit limit, ledger. */
const CM_PERMS = ['view' => 0, 'add' => 1, 'edit' => 2, 'delete' => 3, 'credit' => 4, 'ledger' => 5];
const CM_MENU = ['list' => ['customer_list.php', 'Customers'], 'profile' => ['customer_profile.php', 'Customer Profile'],
    'credit' => ['customer_credit.php', 'Credit Limit'], 'ledger' => ['customer_ledger.php', 'Customer Ledger'],
    'portal_accounts' => ['portal_accounts.php', 'Portal Accounts'], 'portal_requests' => ['portal_requests.php', 'Portal Requests'], 'portal_notices' => ['../user/company_settings.php?tab=notices', 'Notices & Settings']];

function cm_ready(mysqli $db)
{
    return trader_schema_ready($db) && sales_tables_ready($db, ['customer_accounts', 'customer_ledger_entries', 'sales_invoices', 'sales_returns']);
}

function cm_can($db, $what)
{
    return mgmt_permissions($db, 'customer_mgmt', CM_PERMS)[$what] ?? false;
}

/** Page start for Customer Management, with the install notice and the View permission check. */
function cm_page_start(mysqli $db, $title, $active)
{
    mgmt_require(cm_can($db, 'view'), 'viewing customers');
    mgmt_page_start('Customer Management', CM_MENU, $title, $active);
    if (!cm_ready($db)) {
        echo '<div class="errorbox"><strong>Customer Management is not installed.</strong><br>Import <code>billing/trader/schema.sql</code> and <code>billing/customer/schema.sql</code> (after the sales schemas), then reload this page.</div>';
        mgmt_page_end();
        exit;
    }
}

/** Customer-type trader categories: [id => name]. */
function cm_categories(mysqli $db)
{
    $out = [];
    foreach (trader_categories($db) as $id => $c) {
        if ($c['kind'] === 'customer') {
            $out[$id] = $c['category_name'];
        }
    }
    return $out;
}

function cm_customer(mysqli $db, $customerId)
{
    return sales_row($db, 'SELECT * FROM customer_accounts WHERE customer_id=?', 'i', [(int) $customerId]);
}

/** Days since the oldest unpaid invoice, or null. */
function cm_overdue_days(array $c)
{
    return empty($c['oldest_due_date']) ? null : (int) (new DateTime($c['oldest_due_date']))->diff(new DateTime('today'))->format('%a');
}

/** Short status text for a customer row: Blocked, Over limit, Overdue, No credit, OK. */
function cm_status(array $c)
{
    if (!(int) $c['is_active']) {
        return ['Inactive', 'cancelled'];
    }
    if ((int) $c['block_sales']) {
        return ['Blocked', 'cancelled'];
    }
    if ((int) $c['has_credit_rules'] && (int) $c['allow_credit'] && (float) $c['credit_limit'] > 0 && (float) $c['balance'] > (float) $c['credit_limit'] + 0.004) {
        return ['Over limit', 'cancelled'];
    }
    $age = cm_overdue_days($c);
    if ((int) $c['credit_days'] > 0 && $age !== null && $age > (int) $c['credit_days']) {
        return ['Overdue ' . $age . 'd', 'open'];
    }
    if ((int) $c['has_credit_rules'] && !(int) $c['allow_credit']) {
        return ['Cash only', 'open'];
    }
    return ['OK', 'converted'];
}

/** Make a trader's current SR $srId (closing the previous assignment), or none when $srId is 0. */
function cm_assign_sr(mysqli $db, $traderId, $srId)
{
    $current = sales_row($db, 'SELECT assignment_id, sr_id FROM trader_sr_assignments WHERE trader_id=? AND effective_to IS NULL ORDER BY assignment_id DESC LIMIT 1', 'i', [(int) $traderId]);
    if ($current && (int) $current['sr_id'] === (int) $srId) {
        return;
    }
    if ($current) {
        sales_exec($db, 'UPDATE trader_sr_assignments SET effective_to=CURDATE() WHERE trader_id=? AND effective_to IS NULL', 'i', [(int) $traderId])->close();
    }
    if ($srId > 0) {
        sales_insert($db, 'INSERT INTO trader_sr_assignments (trader_id, sr_id, effective_from, assigned_by) VALUES (?, ?, CURDATE(), ?)', 'iii', [(int) $traderId, (int) $srId, (int) ($GLOBALS['user_id'] ?? 0)]);
    }
}

/**
 * Ledger of a customer between two dates: [opening, rows (with running balance), closing, totals].
 * Opening = profile opening balance + everything before $from.
 */
function cm_ledger(mysqli $db, array $c, $from, $to)
{
    $before = sales_row($db, 'SELECT COALESCE(SUM(debit - credit), 0) AS b FROM customer_ledger_entries WHERE customer_id=? AND entry_date<?', 'is', [(int) $c['customer_id'], $from]);
    $opening = round((float) $c['opening_balance'] + (float) $before['b'], 2);
    $rows = sales_rows($db, 'SELECT * FROM customer_ledger_entries WHERE customer_id=? AND entry_date BETWEEN ? AND ? ORDER BY entry_date, sort_order, ref_id', 'iss', [(int) $c['customer_id'], $from, $to]);
    $bal = $opening;
    $dr = $cr = 0.0;
    foreach ($rows as &$r) {
        $bal += (float) $r['debit'] - (float) $r['credit'];
        $dr += (float) $r['debit'];
        $cr += (float) $r['credit'];
        $r['balance'] = round($bal, 2);
    }
    unset($r);
    return [$opening, $rows, round($bal, 2), $dr, $cr];
}

/** Unpaid invoice balances of a customer by age: [label => amount]. */
function cm_aging(mysqli $db, $customerId)
{
    $buckets = ['0-30 days' => 0.0, '31-60 days' => 0.0, '61-90 days' => 0.0, '91-180 days' => 0.0, 'Over 180 days' => 0.0];
    foreach (sales_rows($db, "SELECT sales_date, balance_amount FROM sales_invoices WHERE customer_id=? AND status='posted' AND balance_amount>0.004", 'i', [(int) $customerId]) as $r) {
        $age = (int) (new DateTime($r['sales_date']))->diff(new DateTime('today'))->format('%a');
        $key = $age <= 30 ? '0-30 days' : ($age <= 60 ? '31-60 days' : ($age <= 90 ? '61-90 days' : ($age <= 180 ? '91-180 days' : 'Over 180 days')));
        $buckets[$key] += (float) $r['balance_amount'];
    }
    return $buckets;
}
