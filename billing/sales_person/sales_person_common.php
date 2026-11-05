<?php
/**
 * Shared code for Sales Person Management (billing/sales_person, formerly the VoIP Common Clients pages): SR profile,
 * territory, monthly target and commission. SRs are sales_representatives rows; sales are credited to an SR by
 * sales_invoices.sr_id, or for older invoices by the Sales Person name. Tables: trader/schema.sql, sales_person/schema.sql.
 */
require_once dirname(__DIR__) . '/trader/trader_common.php';
require_once dirname(__DIR__) . '/inventory/inv_common.php'; // inv_fmt_date, inv_print_head, INV_PRINT_CSS

/** Permission flags in admin_role.sales_person_mgmt: view, add, edit, delete, commission payment, targets. */
const SP_PERMS = ['view' => 0, 'add' => 1, 'edit' => 2, 'delete' => 3, 'commission' => 4, 'target' => 5];
const SP_MENU = ['sr' => ['sr_list.php', 'Sales Person (SR)'], 'territory' => ['territory.php', 'Territory'], 'target' => ['sr_target.php', 'Target'], 'commission' => ['sr_commission.php', 'Commission'],
    'sr_portal_accounts' => ['sr_portal_accounts.php', 'SR Portal Accounts'], 'sr_collections' => ['sr_collections.php', 'SR Collections']];
const SP_BASIS = ['none' => 'No commission', 'net_sales' => 'Net sales', 'collection' => 'Collection', 'gross_profit' => 'Gross profit'];
const SP_METHODS = ['cash' => 'Cash', 'bank' => 'Bank', 'mobile' => 'Mobile Banking', 'other' => 'Other'];

function sp_ready(mysqli $db)
{
    return trader_schema_ready($db) && sales_tables_ready($db, ['sr_territories', 'sr_targets', 'sr_commission_payments', 'sales_invoices', 'sales_invoice_payments', 'sales_returns'])
        && (bool) sales_row($db, "SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales_invoices' AND column_name='sr_id'");
}

function sp_can($db, $what)
{
    return mgmt_permissions($db, 'sales_person_mgmt', SP_PERMS)[$what] ?? false;
}

function sp_page_start(mysqli $db, $title, $active)
{
    mgmt_require(sp_can($db, 'view'), 'viewing sales persons');
    mgmt_page_start('Sales Person Management', SP_MENU, $title, $active);
    if (!sp_ready($db)) {
        echo '<div class="errorbox"><strong>Sales Person Management is not installed.</strong><br>Import <code>billing/trader/schema.sql</code> and <code>billing/sales_person/schema.sql</code> (after the sales schemas), then reload this page.</div>';
        mgmt_page_end();
        exit;
    }
}

const SR_PORTAL_URL = '../../sr_portal/login.php';

function sp_portal_ready(mysqli $db)
{
    return sp_ready($db) && sales_tables_ready($db, ['sr_portal_accounts', 'sr_portal_login_log', 'sr_collections', 'sr_collection_allocations']);
}

/** Page start for the SR portal pages: Sales Person Management checks plus the portal install notice. */
function sp_portal_page_start(mysqli $db, $title, $active)
{
    sp_page_start($db, $title, $active);
    if (!sp_portal_ready($db)) {
        echo '<div class="errorbox"><strong>The SR Portal is not installed.</strong><br>Import <code>sr_portal/schema.sql</code> (after <code>billing/sales_person/schema.sql</code>), then reload this page.</div>';
        mgmt_page_end();
        exit;
    }
}

/** First day of the month from ?month=YYYY-MM (default this month). */
function sp_month($value)
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value) ? $value . '-01' : date('Y-m-01');
}

/** SQL condition that an invoice v belongs to SR s. */
const SP_MATCH = "(v.sr_id = s.sr_id OR (v.sr_id IS NULL AND v.sales_person <> '' AND v.sales_person = s.sr_name))";

/**
 * Figures per SR for a month: [sr_id => invoices, sales, returns, net_sales, collection, gross_profit].
 * Sales and gross profit count invoices dated in the month; returns and collection count what happened in the month
 * on that SR's invoices.
 */
function sp_month_stats(mysqli $db, $month)
{
    $from = $month;
    $to = date('Y-m-t', strtotime($month));
    $out = [];
    $add = function (array $rows, $key) use (&$out) {
        foreach ($rows as $r) {
            $out[(int) $r['sr_id']][$key] = (float) $r['v'];
        }
    };
    $add(sales_rows($db, 'SELECT s.sr_id, COUNT(*) AS v FROM sales_representatives s JOIN sales_invoices v ON ' . SP_MATCH . " WHERE v.status='posted' AND v.sales_date BETWEEN ? AND ? GROUP BY s.sr_id", 'ss', [$from, $to]), 'invoices');
    $add(sales_rows($db, 'SELECT s.sr_id, SUM(v.net_amount) AS v FROM sales_representatives s JOIN sales_invoices v ON ' . SP_MATCH . " WHERE v.status='posted' AND v.sales_date BETWEEN ? AND ? GROUP BY s.sr_id", 'ss', [$from, $to]), 'sales');
    $add(sales_rows($db, 'SELECT s.sr_id, SUM(r.return_amount) AS v FROM sales_representatives s JOIN sales_invoices v ON ' . SP_MATCH . " JOIN sales_returns r ON r.invoice_id=v.invoice_id AND r.status='posted' WHERE v.status='posted' AND r.return_date BETWEEN ? AND ? GROUP BY s.sr_id", 'ss', [$from, $to]), 'returns');
    $add(sales_rows($db, 'SELECT s.sr_id, SUM(p.amount) AS v FROM sales_representatives s JOIN sales_invoices v ON ' . SP_MATCH . " JOIN sales_invoice_payments p ON p.invoice_id=v.invoice_id WHERE v.status='posted' AND DATE(p.paid_at) BETWEEN ? AND ? GROUP BY s.sr_id", 'ss', [$from, $to]), 'collection');
    $cost = sales_row($db, "SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales_invoice_items' AND column_name='unit_cost'") ? '(i.quantity + i.free_quantity) * i.unit_cost' : '0';
    $add(sales_rows($db, 'SELECT s.sr_id, SUM(i.line_total * (CASE WHEN v.subtotal > 0 THEN 1 - v.discount_amount / v.subtotal ELSE 1 END) - ' . $cost . ') AS v '
            . 'FROM sales_representatives s JOIN sales_invoices v ON ' . SP_MATCH . " JOIN sales_invoice_items i ON i.invoice_id=v.invoice_id WHERE v.status='posted' AND v.sales_date BETWEEN ? AND ? GROUP BY s.sr_id", 'ss', [$from, $to]), 'gross_profit');
    foreach ($out as &$o) {
        $o += ['invoices' => 0, 'sales' => 0.0, 'returns' => 0.0, 'collection' => 0.0, 'gross_profit' => 0.0];
        $o['net_sales'] = round($o['sales'] - $o['returns'], 2);
    }
    unset($o);
    return $out;
}

/**
 * Commission sheet for a month, one row per SR with activity, a target or a payment (and every active SR):
 * basis amount x rate, plus the target bonus (bonus rate x net sales) when net sales reach the sales target,
 * less commission already paid for the month.
 */
function sp_commission_rows(mysqli $db, $month)
{
    $stats = sp_month_stats($db, $month);
    $targets = array_column(sales_rows($db, 'SELECT * FROM sr_targets WHERE target_month=?', 's', [$month]), null, 'sr_id');
    $paid = array_column(sales_rows($db, 'SELECT sr_id, SUM(amount) AS paid FROM sr_commission_payments WHERE period_month=? GROUP BY sr_id', 's', [$month]), 'paid', 'sr_id');
    $rows = [];
    foreach (sales_rows($db, 'SELECT s.*, COALESCE(t.territory_name, \'\') AS territory_name FROM sales_representatives s LEFT JOIN sr_territories t ON t.territory_id=s.territory_id ORDER BY s.sr_name') as $s) {
        if (!empty($s['staff_only'])) {
            continue;
        }
        $id = (int) $s['sr_id'];
        $st = $stats[$id] ?? ['invoices' => 0, 'sales' => 0.0, 'returns' => 0.0, 'net_sales' => 0.0, 'collection' => 0.0, 'gross_profit' => 0.0];
        if (!(int) $s['is_active'] && !isset($stats[$id]) && !isset($targets[$id]) && !isset($paid[$id])) {
            continue;
        }
        $basis = $s['commission_basis'];
        $base = match ($basis) { 'net_sales' => $st['net_sales'], 'collection' => $st['collection'], 'gross_profit' => $st['gross_profit'], default => 0.0 };
        $target = (float) ($targets[$id]['sales_target'] ?? 0);
        $reached = $target > 0 && $st['net_sales'] >= $target;
        $commission = round(max(0.0, $base) * (float) $s['commission_rate'] / 100, 2);
        $bonus = $reached ? round($st['net_sales'] * (float) $s['target_bonus_rate'] / 100, 2) : 0.0;
        $rows[] = $s + $st + ['basis_amount' => $base, 'sales_target' => $target, 'collection_target' => (float) ($targets[$id]['collection_target'] ?? 0), 'target_reached' => $reached,
            'commission' => $commission, 'bonus' => $bonus, 'total' => $commission + $bonus, 'paid' => (float) ($paid[$id] ?? 0), 'due' => round($commission + $bonus - (float) ($paid[$id] ?? 0), 2)];
    }
    return $rows;
}

/** Achievement percent, or null without a target. */
function sp_pct($done, $target)
{
    return (float) $target > 0 ? round((float) $done * 100 / (float) $target, 1) : null;
}
