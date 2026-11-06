<?php
/**
 * Business dashboard shown in the main pane (Home), in both layouts (topmain.php and treemain.php). Read-only:
 * headline cards, sales against the previous period, sales by customer group, top sales persons, today's deliveries,
 * recent activity, sales by product group, receivables by age, quick actions, then the detailed lists. Each block
 * is skipped quietly when its module's tables are not installed. ?export=csv downloads the figures shown.
 */
require_once dirname(__DIR__) . '/sales/sales_common.php';
foreach (['/inventory/inv_common.php', '/accounts_info/acc_common.php'] as $lib) {
    if (is_file(dirname(__DIR__) . $lib)) {
        require_once dirname(__DIR__) . $lib;
    }
}

/* ---------- Period ---------- */
$periods = ['today' => 'Today', 'mtd' => 'Month to date', 'last30' => 'Last 30 days', 'qtd' => 'Quarter to date', 'ytd' => 'Year to date', 'custom' => 'Custom'];
$period = isset($periods[$_GET['period'] ?? '']) ? (string) $_GET['period'] : 'mtd';
$today = date('Y-m-d');
switch ($period) {
    case 'today':
        $from = $today;
        break;
    case 'last30':
        $from = date('Y-m-d', strtotime('-29 days'));
        break;
    case 'qtd':
        $from = date('Y') . '-' . str_pad((string) (intdiv((int) date('n') - 1, 3) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01';
        break;
    case 'ytd':
        $from = date('Y-01-01');
        break;
    case 'custom':
        $from = sales_valid_date($_GET['from'] ?? '', true) ?: date('Y-m-01');
        break;
    default:
        $from = date('Y-m-01');
}
$to = $period === 'custom' ? (sales_valid_date($_GET['to'] ?? '', true) ?: $today) : $today;
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
// The comparison window is the same number of days immediately before.
$days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
$prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
$prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

$has = fn(array $tables) => sales_tables_ready($conn, $tables);
$one = function ($sql, $types = '', $params = []) use ($conn) {
    $row = sales_row($conn, $sql, $types, $params);
    return $row ? (float) array_values($row)[0] : 0.0;
};
$salesReady = $has(['sales_invoices', 'sales_invoice_items', 'sales_invoice_payments']);
$invReady = function_exists('inv_ready') && inv_ready($conn);
$accReady = function_exists('acc_ready') && acc_ready($conn);
// A user limited to branches sees that branch's sales. Stock, cash and cheques have no branch here, so those blocks are left out.
$scope = perm_branch_sql('branch_id');
$scopeI = perm_branch_sql('i.branch_id');
$scopeReturns = $scope === '' ? '' : 'AND invoice_id IN (SELECT invoice_id FROM sales_invoices WHERE 1=1' . $scope . ')';
if ($scope !== '') {
    $invReady = $accReady = false;
}

/* ---------- Headline figures ---------- */
$k = [];
if ($salesReady) {
    $salesSql = "SELECT COALESCE(SUM(net_amount),0), COUNT(*) FROM sales_invoices WHERE 1=1{$scope} AND status='posted' AND sales_date BETWEEN ? AND ?";
    $cur = sales_row($conn, $salesSql, 'ss', [$from, $to]);
    $prev = sales_row($conn, $salesSql, 'ss', [$prevFrom, $prevTo]);
    $k['sales'] = (float) array_values($cur)[0];
    $k['sales_prev'] = (float) array_values($prev)[0];
    $k['invoices'] = (int) array_values($cur)[1];
    $collectSql = "SELECT COALESCE(SUM(p.amount),0) FROM sales_invoice_payments p JOIN sales_invoices i ON i.invoice_id=p.invoice_id WHERE 1=1{$scopeI} AND i.status='posted' AND p.paid_at >= ? AND p.paid_at < DATE_ADD(?, INTERVAL 1 DAY)";
    $k['collected'] = $one($collectSql, 'ss', [$from, $to]);
    $k['collected_prev'] = $one($collectSql, 'ss', [$prevFrom, $prevTo]);
    $k['receivable'] = $one("SELECT COALESCE(SUM(balance_amount),0) FROM sales_invoices WHERE 1=1{$scope} AND status='posted' AND balance_amount > 0");
    $k['receivable_count'] = (int) $one("SELECT COUNT(*) FROM sales_invoices WHERE 1=1{$scope} AND status='posted' AND balance_amount > 0");
    if ($has(['sales_returns'])) {
        $k['returns'] = $one("SELECT COALESCE(SUM(return_amount),0) FROM sales_returns WHERE status='posted' {$scopeReturns} AND return_date BETWEEN ? AND ?", 'ss', [$from, $to]);
    }
}
if ($has(['sales_orders'])) {
    $row = sales_row($conn, "SELECT COUNT(*) n, COALESCE(SUM(net_amount),0) v FROM sales_orders WHERE 1=1{$scope} AND status='open'");
    $k['open_orders'] = (int) $row['n'];
    $k['open_orders_value'] = (float) $row['v'];
}
if ($invReady) {
    $purchaseSql = "SELECT COALESCE(SUM(net_amount),0) FROM inv_docs WHERE doc_type='purchase' AND status='active' AND doc_date BETWEEN ? AND ?";
    $k['purchases'] = $one($purchaseSql, 'ss', [$from, $to]);
    $k['purchases_prev'] = $one($purchaseSql, 'ss', [$prevFrom, $prevTo]);
    // Stock on hand valued at the average cost of everything received.
    $stock = sales_rows(
        $conn,
        "SELECT m.product_id, SUM(m.qty_in - m.qty_out) AS qty, SUM(CASE WHEN m.qty_in > 0 AND m.doc_type IN ('purchase','stock_in') THEN m.qty_in * m.unit_cost ELSE 0 END) "
            . "/ NULLIF(SUM(CASE WHEN m.qty_in > 0 AND m.doc_type IN ('purchase','stock_in') THEN m.qty_in ELSE 0 END), 0) AS avg_cost FROM inv_stock_moves m GROUP BY m.product_id"
    );
    $k['stock_value'] = array_sum(array_map(fn($r) => max(0, (float) $r['qty']) * (float) $r['avg_cost'], $stock));
    $k['stock_items'] = count(array_filter($stock, fn($r) => (float) $r['qty'] > 0));
}
$approvals = [];
if ($invReady) {
    foreach (sales_rows($conn, "SELECT doc_type, COUNT(*) n FROM inv_docs WHERE status='active' AND approval_status='pending' AND doc_type<>'challan_bill' GROUP BY doc_type") as $r) {
        if (isset(INV_TYPES[$r['doc_type']]) && empty(INV_TYPES[$r['doc_type']]['linked'])) {
            $approvals[] = [INV_TYPES[$r['doc_type']]['title'] . ' awaiting approval', (int) $r['n'], '../approve/approve.php?doc=' . $r['doc_type']];
        }
    }
}
foreach (['sales_invoices' => ['Sales', "status='posted'", 'sales'], 'sales_orders' => ['Sales Order', "status<>'cancelled'", 'sales_order'], 'sales_returns' => ['Sales Return', "status='posted'", 'sales_return']] as $table => [$label, $where, $doc]) {
    if ($scope === '' && $has([$table]) && sales_row($conn, "SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='approval_status'", 's', [$table])) {
        $n = (int) $one("SELECT COUNT(*) FROM $table WHERE approval_status='pending' AND $where");
        if ($n > 0) {
            $approvals[] = [$label . ' awaiting approval', $n, '../approve/approve.php?doc=' . $doc];
        }
    }
}
if ($accReady) {
    $drafts = (int) $one("SELECT COUNT(*) FROM acc_vouchers WHERE status='draft'");
    if ($drafts > 0) {
        $approvals[] = ['Voucher' . ($drafts === 1 ? '' : 's') . ' waiting to be posted', $drafts, '../accounts_info/acc_posting.php?status=draft'];
    }
    $mov = acc_account_movements($conn, '1900-01-01', $today);
    $cash = 0.0;
    $payable = 0.0;
    foreach (acc_accounts($conn, false) as $a) {
        $m = $mov[(int) $a['account_id']] ?? ['open' => 0, 'dr' => 0, 'cr' => 0];
        $closing = $m['open'] + $m['dr'] - $m['cr'];
        if (in_array($a['account_type'], ['cash', 'bank'], true)) {
            $cash += $closing;
        }
        if ($a['account_type'] === 'payable') {
            $payable += -$closing;
        }
    }
    $k['cash_bank'] = $cash;
    $k['payable'] = $payable;
    $expenseSql = "SELECT COALESCE(SUM(l.debit - l.credit),0) FROM acc_voucher_lines l JOIN acc_accounts a ON a.account_id=l.account_id JOIN acc_groups g ON g.group_id=a.group_id WHERE l.is_posted=1 AND g.nature='expense' AND l.voucher_date BETWEEN ? AND ?";
    $k['expenses'] = $one($expenseSql, 'ss', [$from, $to]);
    $k['expenses_prev'] = $one($expenseSql, 'ss', [$prevFrom, $prevTo]);
}
$k['approvals'] = array_sum(array_column($approvals, 1));

/* ---------- Trend: 12 months of sales and collections ---------- */
$trend = [];
if ($salesReady) {
    $start = date('Y-m-01', strtotime(date('Y-m-01') . ' -11 months'));
    $months = [];
    for ($i = 0; $i < 12; $i++) {
        $m = date('Y-m', strtotime($start . " +$i months"));
        $months[$m] = ['label' => date('M y', strtotime($m . '-01')), 'sales' => 0.0, 'collected' => 0.0];
    }
    foreach (sales_rows($conn, "SELECT DATE_FORMAT(sales_date, '%Y-%m') m, SUM(net_amount) v FROM sales_invoices WHERE 1=1{$scope} AND status='posted' AND sales_date >= ? GROUP BY m", 's', [$start]) as $r) {
        if (isset($months[$r['m']])) {
            $months[$r['m']]['sales'] = round((float) $r['v'], 2);
        }
    }
    foreach (sales_rows($conn, "SELECT DATE_FORMAT(p.paid_at, '%Y-%m') m, SUM(p.amount) v FROM sales_invoice_payments p JOIN sales_invoices i ON i.invoice_id=p.invoice_id WHERE 1=1{$scopeI} AND i.status='posted' AND p.paid_at >= ? GROUP BY m", 's', [$start]) as $r) {
        if (isset($months[$r['m']])) {
            $months[$r['m']]['collected'] = round((float) $r['v'], 2);
        }
    }
    $trend = array_values($months);
}

/* ---------- Breakdowns and lists ---------- */
$aging = [];
$topCustomers = $topProducts = $recent = $dueInvoices = [];
if ($salesReady) {
    $aging = sales_rows(
        $conn,
        "SELECT b.bucket AS label, COALESCE(SUM(i.balance_amount),0) AS value, COUNT(i.invoice_id) AS n FROM (SELECT 1 k, '0-30 days' bucket UNION ALL SELECT 2, '31-60 days' UNION ALL SELECT 3, '61-90 days' UNION ALL SELECT 4, 'Over 90 days') b "
            . "LEFT JOIN sales_invoices i ON i.status='posted' AND i.balance_amount > 0 AND b.k = CASE WHEN DATEDIFF(CURDATE(), i.sales_date) <= 30 THEN 1 WHEN DATEDIFF(CURDATE(), i.sales_date) <= 60 THEN 2 WHEN DATEDIFF(CURDATE(), i.sales_date) <= 90 THEN 3 ELSE 4 END GROUP BY b.k, b.bucket ORDER BY b.k"
    );
    $topCustomers = sales_rows($conn, "SELECT customer_name AS name, MAX(mobile) AS mobile, COUNT(*) AS n, SUM(net_amount) AS value, SUM(balance_amount) AS due FROM sales_invoices WHERE 1=1{$scope} AND status='posted' AND sales_date BETWEEN ? AND ? GROUP BY customer_name ORDER BY value DESC LIMIT 6", 'ss', [$from, $to]);
    $topProducts = sales_rows($conn, "SELECT COALESCE(NULLIF(it.description, ''), it.model_caption) AS label, MAX(it.model_caption) AS model, SUM(it.quantity) AS qty, SUM(it.line_total) AS value FROM sales_invoice_items it JOIN sales_invoices i ON i.invoice_id=it.invoice_id WHERE i.status='posted' AND i.sales_date BETWEEN ? AND ? GROUP BY label ORDER BY value DESC LIMIT 6", 'ss', [$from, $to]);
    $recent = sales_rows($conn, "SELECT invoice_id, invoice_no, sales_date, customer_name, net_amount, balance_amount FROM sales_invoices WHERE 1=1{$scope} AND status='posted' ORDER BY invoice_id DESC LIMIT 7");
    $dueInvoices = sales_rows($conn, "SELECT invoice_id, invoice_no, sales_date, customer_name, mobile, balance_amount, DATEDIFF(CURDATE(), sales_date) AS age FROM sales_invoices WHERE 1=1{$scope} AND status='posted' AND balance_amount > 0 ORDER BY sales_date LIMIT 7");
}
$lowStock = [];
$lowLevel = $invReady ? (float) inv_setting($conn, 'low_stock_level', '2') : 2;
if ($invReady) {
    $lowStock = sales_rows($conn, "SELECT p.product_id, COALESCE(NULLIF(p.model_caption, ''), p.description) AS label, p.product_group, SUM(m.qty_in - m.qty_out) AS qty FROM inv_stock_moves m JOIN sales_products p ON p.product_id=m.product_id WHERE p.is_active=1 GROUP BY p.product_id, label, p.product_group HAVING qty <= ? ORDER BY qty, label LIMIT 8", 'd', [$lowLevel]);
}
$cheques = [];
if ($accReady) {
    $cheques = sales_rows($conn, "SELECT c.cheque_no, c.cheque_date, c.direction, c.party_name, c.amount FROM acc_cheques c WHERE c.status='pending' AND COALESCE(c.cheque_date, CURDATE()) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY c.cheque_date LIMIT 7");
}

/* ---------- Layout blocks: period trend, channels, sales persons, deliveries, groups, activity ---------- */
$daily = [];
$channels = $sellers = $deliveries = $groups = $activity = [];
$deliveriesToday = true;
$critical = 0;
$overdueCustomers = 0;
if ($salesReady) {
    $k['invoices_prev'] = (int) array_values($prev)[1];
    // Each day of this period beside the same day of the previous period (day 1 with day 1).
    $byDay = fn($a, $b) => array_column(sales_rows($conn, "SELECT sales_date d, SUM(net_amount) v FROM sales_invoices WHERE 1=1{$scope} AND status='posted' AND sales_date BETWEEN ? AND ? GROUP BY sales_date", 'ss', [$a, $b]), 'v', 'd');
    if ($days <= 62) {
        $cur = $byDay($from, $to);
        $old = $byDay($prevFrom, $prevTo);
        for ($i = 0; $i < $days; $i++) {
            $d = date('Y-m-d', strtotime("$from +$i days"));
            $p = date('Y-m-d', strtotime("$prevFrom +$i days"));
            $daily[] = ['label' => date('j M', strtotime($d)), 'sales' => round((float) ($cur[$d] ?? 0), 2), 'prev' => round((float) ($old[$p] ?? 0), 2)];
        }
    }
    $channelRows = sales_rows($conn, "SELECT COALESCE(NULLIF(TRIM(customer_group), ''), 'Walk-in') AS label, SUM(net_amount) AS value FROM sales_invoices WHERE 1=1{$scope} AND status='posted' AND sales_date BETWEEN ? AND ? GROUP BY label ORDER BY value DESC", 'ss', [$from, $to]);
    $channels = array_slice($channelRows, 0, 4);
    if (count($channelRows) > 4) {
        $channels[] = ['label' => 'Others', 'value' => array_sum(array_column(array_slice($channelRows, 4), 'value'))];
    }
    $hasSr = $has(['sales_representatives']) && sales_row($conn, "SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales_invoices' AND column_name='sr_id'");
    $sellers = $hasSr
        ? sales_rows($conn, "SELECT COALESCE(NULLIF(r.sr_name, ''), NULLIF(i.sales_person, ''), 'Not assigned') AS label, MAX(r.sr_code) AS code, SUM(i.net_amount) AS value FROM sales_invoices i LEFT JOIN sales_representatives r ON r.sr_id=i.sr_id WHERE 1=1{$scopeI} AND i.status='posted' AND i.sales_date BETWEEN ? AND ? GROUP BY label ORDER BY value DESC LIMIT 5", 'ss', [$from, $to])
        : sales_rows($conn, "SELECT COALESCE(NULLIF(sales_person, ''), 'Not assigned') AS label, '' AS code, SUM(net_amount) AS value FROM sales_invoices WHERE 1=1{$scope} AND status='posted' AND sales_date BETWEEN ? AND ? GROUP BY label ORDER BY value DESC LIMIT 5", 'ss', [$from, $to]);
    $groups = sales_rows($conn, "SELECT COALESCE(NULLIF(TRIM(it.product_group), ''), 'Other') AS label, SUM(it.line_total) AS value FROM sales_invoice_items it JOIN sales_invoices i ON i.invoice_id=it.invoice_id WHERE 1=1{$scopeI} AND i.status='posted' AND i.sales_date BETWEEN ? AND ? GROUP BY label ORDER BY value DESC LIMIT 6", 'ss', [$from, $to]);
    $overdueCustomers = (int) $one("SELECT COUNT(DISTINCT customer_name) FROM sales_invoices WHERE 1=1{$scope} AND status='posted' AND balance_amount > 0 AND sales_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    // Recent activity: the newest invoices, payments and orders, mixed by time.
    foreach (sales_rows($conn, "SELECT created_at t, invoice_no, customer_name, net_amount FROM sales_invoices WHERE 1=1{$scope} AND status='posted' ORDER BY invoice_id DESC LIMIT 5") as $r) {
        $activity[] = ['t' => $r['t'], 'kind' => 'invoice', 'title' => 'Invoice issued', 'text' => $r['invoice_no'] . ' to ' . $r['customer_name']];
    }
    foreach (sales_rows($conn, "SELECT p.paid_at t, p.amount, i.customer_name FROM sales_invoice_payments p JOIN sales_invoices i ON i.invoice_id=p.invoice_id WHERE 1=1{$scopeI} AND i.status='posted' ORDER BY p.payment_id DESC LIMIT 5") as $r) {
        $activity[] = ['t' => $r['t'], 'kind' => 'payment', 'title' => 'Payment received', 'text' => bs_currency_symbol() . bs_number((float) $r['amount'], 0) . ' from ' . $r['customer_name']];
    }
}
if ($has(['sales_orders'])) {
    $deliveries = sales_rows($conn, "SELECT order_id, order_no, customer_name, sales_person, delivery_point, delivery_date, net_amount, status FROM sales_orders WHERE 1=1{$scope} AND status<>'cancelled' AND delivery_date=CURDATE() ORDER BY status='converted', order_id LIMIT 6");
    if (!$deliveries) { // nothing due today: the next open ones this week
        $deliveriesToday = false;
        $deliveries = sales_rows($conn, "SELECT order_id, order_no, customer_name, sales_person, delivery_point, delivery_date, net_amount, status FROM sales_orders WHERE 1=1{$scope} AND status='open' AND delivery_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY delivery_date, order_id LIMIT 6");
    }
    foreach (sales_rows($conn, "SELECT created_at t, order_no, customer_name FROM sales_orders WHERE 1=1{$scope} AND status<>'cancelled' ORDER BY order_id DESC LIMIT 4") as $r) {
        $activity[] = ['t' => $r['t'], 'kind' => 'order', 'title' => 'New order', 'text' => $r['order_no'] . ' from ' . $r['customer_name']];
    }
}
if ($invReady) {
    $critical = (int) $one("SELECT COUNT(*) FROM (SELECT m.product_id FROM inv_stock_moves m JOIN sales_products p ON p.product_id=m.product_id WHERE p.is_active=1 GROUP BY m.product_id HAVING SUM(m.qty_in - m.qty_out) <= ?) x", 'd', [$lowLevel]);
    foreach (array_slice($lowStock, 0, 2) as $s) {
        $activity[] = ['t' => '', 'kind' => 'stock', 'title' => 'Low stock', 'text' => $s['label'] . ': ' . rtrim(rtrim(number_format((float) $s['qty'], 3), '0'), '.') . ' left'];
    }
}
usort($activity, fn($a, $b) => strcmp((string) $b['t'], (string) $a['t']));
$activity = array_slice($activity, 0, 6);

/* ---------- Formatting ---------- */
function dash_compact($v)
{
    $a = abs($v);
    $s = $a >= 10000000 ? round($a / 10000000, 1) . ' Cr' : ($a >= 100000 ? round($a / 100000, 1) . ' L' : ($a >= 1000 ? round($a / 1000, 1) . 'K' : number_format($a, 0)));
    return ($v < 0 ? '-' : '') . $s;
}
/** An amount in full, without decimals, in the country's currency and grouping (Settings > Document Numbering). */
function dash_tk($v)
{
    return bs_currency_symbol() . ' ' . bs_number(round((float) $v), 0);
}
/** Change against the previous period: arrow, percent and "vs previous period"; $upIsGood decides the colour. */
function dash_delta($cur, $prev, $upIsGood = true)
{
    if ($prev == 0 && $cur == 0) {
        return '<span class="delta flat">No change <span class="vs">vs last period</span></span>';
    }
    if ($prev == 0) {
        return '<span class="delta ' . ($upIsGood ? 'good' : 'bad') . '">New <span class="vs">this period</span></span>';
    }
    $pct = ($cur - $prev) / abs($prev) * 100;
    $good = $upIsGood ? $pct >= 0 : $pct <= 0;
    $icon = $pct >= 0 ? 'arrow-up-right' : 'arrow-down-right';
    return '<span class="delta ' . (abs($pct) < 0.05 ? 'flat' : ($good ? 'good' : 'bad')) . '"><i class="bi bi-' . $icon . '"></i>' . ($pct >= 0 ? '+' : '-') . number_format(abs($pct), 1) . '% <span class="vs">vs last period</span></span>';
}
function dash_ago($time)
{
    if ($time === '' || !($ts = strtotime($time))) {
        return 'Now';
    }
    $s = max(0, time() - $ts);
    return $s < 60 ? 'Just now' : ($s < 3600 ? intdiv($s, 60) . ' min ago' : ($s < 86400 ? intdiv($s, 3600) . ' h ago' : ($s < 172800 ? 'Yesterday' : date('d M', $ts))));
}
$periodLabel = date('d M Y', strtotime($from)) . ($from !== $to ? ' – ' . date('d M Y', strtotime($to)) : '');
$company = function_exists('acc_setting') && $accReady ? acc_setting($conn, 'company_name', '') : '';

/* ---------- Export: the figures on screen as a CSV file ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dashboard_' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Dashboard', $periodLabel]);
    fputcsv($out, []);
    fputcsv($out, ['Figure', 'This period', 'Previous period']);
    foreach (['Sales' => ['sales', 'sales_prev'], 'Collections' => ['collected', 'collected_prev'], 'Invoices issued' => ['invoices', 'invoices_prev'], 'Receivables due' => ['receivable', null], 'Open sales orders' => ['open_orders', null], 'Purchases' => ['purchases', 'purchases_prev'], 'Expenses' => ['expenses', 'expenses_prev'], 'Cash & bank' => ['cash_bank', null], 'Payables' => ['payable', null], 'Stock value' => ['stock_value', null]] as $label => [$a, $b]) {
        if (isset($k[$a])) {
            fputcsv($out, [$label, round((float) $k[$a], 2), $b !== null && isset($k[$b]) ? round((float) $k[$b], 2) : '']);
        }
    }
    fputcsv($out, ['Products at or below the low stock level', $critical, '']);
    foreach (['Sales by customer group' => $channels, 'Top sales persons' => $sellers, 'Sales by product group' => $groups, 'Receivables by age' => $aging] as $title => $rows) {
        if ($rows) {
            fputcsv($out, []);
            fputcsv($out, [$title, 'Amount']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['label'], round((float) $r['value'], 2)]);
            }
        }
    }
    fclose($out);
    exit;
}

$kpis = [];
if ($salesReady) {
    $kpis[] = ['Sales', 'currency-exchange', 'green', dash_tk($k['sales']), dash_delta($k['sales'], $k['sales_prev']), ''];
    $kpis[] = ['Collections', 'bank', 'blue', dash_tk($k['collected']), dash_delta($k['collected'], $k['collected_prev']), ''];
}
if (isset($k['open_orders'])) {
    $kpis[] = ['Pending orders', 'cart3', 'orange', number_format($k['open_orders']), '<span class="delta flat">' . dash_tk($k['open_orders_value']) . ' <span class="vs">to deliver</span></span>', '../sales/sales_order/sales_order.php?view=list&status=open'];
}
if ($salesReady) {
    $kpis[] = ['Invoices issued', 'receipt', 'purple', number_format($k['invoices']), dash_delta($k['invoices'], $k['invoices_prev']), '../sales/sales_invoice/sales_invoice.php?view=list'];
}
if ($invReady) {
    $kpis[] = ['Critical stock', 'exclamation-triangle', 'red', number_format($critical), '<span class="link-text">View products</span>', '#low-stock'];
}
if ($salesReady) {
    $kpis[] = ['Receivables', 'people', 'teal', dash_tk($k['receivable']), '<span class="delta warn">' . number_format($overdueCustomers) . ' customer' . ($overdueCustomers === 1 ? '' : 's') . ' over 30 days</span>', '../price_history/invoice_search.php?all=1'];
}
$more = [];
foreach ([['Purchases', 'purchases', 'purchases_prev', false, '../purchase/purchase.php?view=list'], ['Expenses', 'expenses', 'expenses_prev', false, ''], ['Cash & bank', 'cash_bank', null, true, '../accounts_info/acc_register.php'], ['Payables', 'payable', null, true, '../accounts_info/acc_register.php'], ['Stock value', 'stock_value', null, true, '']] as [$label, $key, $prevKey, $up, $href]) {
    if (isset($k[$key])) {
        $more[] = [$label, dash_tk($k[$key]), $prevKey !== null ? dash_delta($k[$key], $k[$prevKey], $up) : '', $href];
    }
}
$quick = [['Sales order', 'cart-plus', '../sales/sales_order/sales_order.php'], ['Receive money', 'cash-coin', '../accounts_info/acc_rp_voucher.php?type=credit'], ['New invoice', 'file-earmark-plus', '../sales/sales_invoice/sales_invoice.php'], ['POS sale', 'upc-scan', '../sales/pos_sales/pos_sales.php'], ['Purchase', 'bag-plus', '../purchase/purchase.php'], ['Challan', 'truck', '../challan/challan.php']];
$activityIcon = ['invoice' => ['file-earmark-text', 'blue'], 'payment' => ['currency-exchange', 'green'], 'order' => ['cart3', 'orange'], 'stock' => ['exclamation-triangle', 'red']];
$exportQuery = http_build_query(array_filter(['period' => $period, 'from' => $period === 'custom' ? $from : null, 'to' => $period === 'custom' ? $to : null, 'export' => 'csv']));
?>
<!doctype html>
<html lang="en" class="ui-common">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="dashboard.css?v=<?= (int) @filemtime(__DIR__ . '/dashboard.css') ?>">
</head>
<body>
<div class="dash">
  <header class="dash-head">
    <div>
      <h1>Dashboard</h1>
      <p class="muted">Overview of <?= $company !== '' ? sales_h($company) : 'your business' ?> · updated <?= date('h:i A') ?></p>
    </div>
    <div class="head-tools">
      <form method="get" class="period" id="period-form">
        <i class="bi bi-calendar3"></i>
        <select name="period" id="period" aria-label="Period">
          <?php foreach ($periods as $p => $label): ?><option value="<?= $p ?>"<?= $period === $p ? ' selected' : '' ?>><?= sales_h($label) ?></option><?php endforeach; ?>
        </select>
        <span class="range"<?= $period === 'custom' ? '' : ' hidden' ?>><input type="date" name="from" value="<?= sales_h($from) ?>" aria-label="From"><span>–</span><input type="date" name="to" value="<?= sales_h($to) ?>" aria-label="To"><button type="submit" class="btn-mini">Apply</button></span>
        <span class="range-text"<?= $period === 'custom' ? ' hidden' : '' ?>><?= sales_h($periodLabel) ?></span>
      </form>
      <a class="btn-outline" href="?<?= sales_h($exportQuery) ?>"><i class="bi bi-download"></i> Export</a>
    </div>
  </header>

  <?php if (!$salesReady): ?>
    <div class="notice">Sales tables are not installed, so sales figures are hidden. Import <code>billing/sales/schema.sql</code>.</div>
  <?php endif; ?>

  <?php if ($kpis): ?>
  <section class="kpis" aria-label="Headline figures">
    <?php foreach ($kpis as [$label, $icon, $tone, $value, $sub, $href]): $tag = $href !== '' ? 'a href="' . sales_h($href) . '"' : 'div'; ?>
    <<?= $tag ?> class="kpi">
      <div class="kpi-top"><span class="kpi-icon <?= $tone ?>"><i class="bi bi-<?= $icon ?>"></i></span><span class="kpi-label"><?= sales_h($label) ?></span></div>
      <div class="kpi-value"><?= sales_h($value) ?></div>
      <div class="kpi-sub"><?= $sub ?></div>
    </<?= $href !== '' ? 'a' : 'div' ?>>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if ($salesReady): ?>
  <div class="row r-2-1">
    <section class="card">
      <div class="card-head"><h2>Sales trend</h2>
        <div class="card-tools">
          <select id="trend-mode" aria-label="Chart period">
            <?php if ($daily): ?><option value="daily">Daily</option><?php endif; ?>
            <option value="monthly">Monthly</option>
          </select>
        </div>
      </div>
      <div class="legend" id="trend-legend"></div>
      <div id="trend" class="chart" role="img" aria-label="Sales over time"></div>
    </section>
    <section class="card">
      <div class="card-head"><h2>Sales by customer group</h2></div>
      <div class="donut-wrap"><div id="channels" class="donut" role="img" aria-label="Sales by customer group"></div><ul class="donut-legend" id="channels-legend"></ul></div>
      <?php if (!$channels): ?><p class="empty">No sales in this period.</p><?php endif; ?>
    </section>
  </div>

  <div class="row r-3">
    <section class="card">
      <div class="card-head"><h2>Top sales persons</h2><span class="muted small">by sales</span></div>
      <div id="sellers" class="hbars"></div>
      <?php if (!$sellers): ?><p class="empty">No sales in this period.</p><?php endif; ?>
    </section>
    <section class="card">
      <div class="card-head"><h2><i class="bi bi-truck head-icon"></i><?= $deliveriesToday ? 'Deliveries today' : 'Upcoming deliveries' ?></h2></div>
      <ul class="deliveries">
        <?php foreach ($deliveries as $i => $d): $done = $d['status'] === 'converted'; ?>
        <li>
          <span class="tag tone-<?= ['blue', 'blue', 'orange', 'purple', 'gray', 'teal'][$i % 6] ?>"><?= sales_h($d['order_no']) ?></span>
          <span class="who"><b><?= sales_h($d['customer_name']) ?></b><small><?= sales_h($d['sales_person'] !== '' ? $d['sales_person'] : ($d['delivery_point'] ?: '—')) ?></small></span>
          <span class="state <?= $done ? 'ok' : 'wait' ?>"><?= $done ? 'Billed' : 'Pending' ?></span>
          <span class="when"><?= $deliveriesToday ? sales_h(dash_compact((float) $d['net_amount'])) : sales_h(date('d M', strtotime($d['delivery_date']))) ?><small><?= $deliveriesToday ? 'Taka' : sales_h(dash_compact((float) $d['net_amount'])) ?></small></span>
        </li>
        <?php endforeach; ?>
        <?php if (!$deliveries): ?><li class="empty">No deliveries due this week.</li><?php endif; ?>
      </ul>
      <a class="card-link" href="../sales/sales_order/sales_order.php?view=list&amp;status=open">All open orders <i class="bi bi-arrow-right"></i></a>
    </section>
    <section class="card">
      <div class="card-head"><h2>Recent activity</h2></div>
      <ul class="activity">
        <?php foreach ($activity as $a): [$icon, $tone] = $activityIcon[$a['kind']]; ?>
        <li><span class="act-icon <?= $tone ?>"><i class="bi bi-<?= $icon ?>"></i></span><span class="act-text"><b><?= sales_h($a['title']) ?></b><small><?= sales_h($a['text']) ?></small></span><span class="act-time"><?= sales_h(dash_ago((string) $a['t'])) ?></span></li>
        <?php endforeach; ?>
        <?php if (!$activity): ?><li class="empty">Nothing yet.</li><?php endif; ?>
      </ul>
    </section>
  </div>

  <div class="row r-3">
    <section class="card">
      <div class="card-head"><h2>Sales by product group</h2><span class="muted small">this period</span></div>
      <div id="groups" class="hbars"></div>
      <?php if (!$groups): ?><p class="empty">No sales in this period.</p><?php endif; ?>
    </section>
    <section class="card">
      <div class="card-head"><h2>Receivables by age</h2></div>
      <div class="donut-wrap"><div id="aging" class="donut small" role="img" aria-label="Receivables by age"></div><ul class="donut-legend" id="aging-legend"></ul></div>
      <a class="card-link" href="../price_history/invoice_search.php?all=1">Unpaid invoices <i class="bi bi-arrow-right"></i></a>
    </section>
    <section class="card">
      <div class="card-head"><h2>Quick actions</h2></div>
      <nav class="quick" aria-label="Quick actions">
        <?php foreach ($quick as [$label, $icon, $href]): ?><a href="<?= sales_h($href) ?>"><i class="bi bi-<?= $icon ?>"></i><span><?= sales_h($label) ?></span></a><?php endforeach; ?>
      </nav>
    </section>
  </div>
  <?php endif; ?>

  <?php if ($more): ?>
  <section class="more" aria-label="More figures">
    <?php foreach ($more as [$label, $value, $sub, $href]): $tag = $href !== '' ? 'a href="' . sales_h($href) . '"' : 'div'; ?>
    <<?= $tag ?> class="mini"><span class="muted small"><?= sales_h($label) ?></span><b><?= sales_h($value) ?></b><?= $sub ?></<?= $href !== '' ? 'a' : 'div' ?>>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <div class="grid">
    <section class="card"><div class="card-head"><h2>Needs your attention</h2></div>
      <ul class="tasks">
        <?php foreach ($approvals as [$label, $n, $href]): ?><li><a href="<?= sales_h($href) ?>"><span class="badge-n"><?= (int) $n ?></span><?= sales_h($label) ?></a></li><?php endforeach; ?>
        <?php if ($lowStock): ?><li><a href="#low-stock"><span class="badge-n warn"><?= $critical ?></span>Product<?= $critical === 1 ? '' : 's' ?> at or below <?= sales_h(rtrim(rtrim(number_format($lowLevel, 3), '0'), '.')) ?> in stock</a></li><?php endif; ?>
        <?php if ($cheques): ?><li><a href="../accounts_info/acc_cheque_treatment.php"><span class="badge-n"><?= count($cheques) ?></span>Cheque<?= count($cheques) === 1 ? '' : 's' ?> due within 7 days</a></li><?php endif; ?>
        <?php if (!$approvals && !$lowStock && !$cheques): ?><li class="empty">Nothing waiting. All clear.</li><?php endif; ?>
      </ul>
    </section>

    <?php if ($salesReady): ?>
    <section class="card"><div class="card-head"><h2>Top customers</h2><span class="muted small">this period</span></div>
      <table class="t"><thead><tr><th>Customer</th><th class="num">Invoices</th><th class="num">Sales</th><th class="num">Due</th></tr></thead><tbody>
      <?php foreach ($topCustomers as $c): ?><tr><td><?= sales_h($c['name']) ?><div class="muted small"><?= sales_h($c['mobile']) ?></div></td><td class="num"><?= (int) $c['n'] ?></td><td class="num"><?= sales_money($c['value']) ?></td><td class="num<?= (float) $c['due'] > 0 ? ' due' : '' ?>"><?= sales_money($c['due']) ?></td></tr><?php endforeach; ?>
      <?php if (!$topCustomers): ?><tr><td colspan="4" class="empty">No sales in this period.</td></tr><?php endif; ?>
      </tbody></table></section>

    <section class="card"><div class="card-head"><h2>Oldest unpaid invoices</h2></div>
      <table class="t"><thead><tr><th>Invoice</th><th>Customer</th><th class="num">Age</th><th class="num">Due</th></tr></thead><tbody>
      <?php foreach ($dueInvoices as $r): ?><tr><td><a target="_blank" href="../sales/sales_invoice/sales_invoice.php?print=<?= (int) $r['invoice_id'] ?>"><?= sales_h($r['invoice_no']) ?></a></td><td><?= sales_h($r['customer_name']) ?><div class="muted small"><?= sales_h($r['mobile']) ?></div></td><td class="num"><?= (int) $r['age'] ?> d</td><td class="num due"><?= sales_money($r['balance_amount']) ?></td></tr><?php endforeach; ?>
      <?php if (!$dueInvoices): ?><tr><td colspan="4" class="empty">No unpaid invoices.</td></tr><?php endif; ?>
      </tbody></table></section>

    <section class="card"><div class="card-head"><h2>Recent invoices</h2><a class="link" href="../sales/sales_invoice/sales_invoice.php?view=list">All invoices</a></div>
      <table class="t"><thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="num">Amount</th></tr></thead><tbody>
      <?php foreach ($recent as $r): ?><tr><td><a target="_blank" href="../sales/sales_invoice/sales_invoice.php?print=<?= (int) $r['invoice_id'] ?>"><?= sales_h($r['invoice_no']) ?></a></td><td><?= date('d M', strtotime($r['sales_date'])) ?></td><td><?= sales_h($r['customer_name']) ?></td><td class="num"><?= sales_money($r['net_amount']) ?></td></tr><?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="4" class="empty">No invoices yet.</td></tr><?php endif; ?>
      </tbody></table></section>

    <section class="card"><div class="card-head"><h2>Top products</h2><span class="muted small">by sales, this period</span></div>
      <table class="t"><thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Sales</th></tr></thead><tbody>
      <?php foreach ($topProducts as $p): ?><tr><td><?= sales_h($p['label']) ?><?php if ($p['model'] !== '' && $p['model'] !== $p['label']): ?><div class="muted small"><?= sales_h($p['model']) ?></div><?php endif; ?></td><td class="num"><?= sales_h(rtrim(rtrim(number_format((float) $p['qty'], 3), '0'), '.')) ?></td><td class="num"><?= sales_money($p['value']) ?></td></tr><?php endforeach; ?>
      <?php if (!$topProducts): ?><tr><td colspan="3" class="empty">No sales in this period.</td></tr><?php endif; ?>
      </tbody></table></section>
    <?php endif; ?>

    <?php if ($invReady): ?>
    <section class="card" id="low-stock"><div class="card-head"><h2>Low stock</h2><a class="link" href="../amedment/stock_amendment_in.php">Add stock</a></div>
      <table class="t"><thead><tr><th>Product</th><th>Group</th><th class="num">On hand</th></tr></thead><tbody>
      <?php foreach ($lowStock as $s): ?><tr><td><?= sales_h($s['label']) ?></td><td><?= sales_h($s['product_group']) ?></td><td class="num<?= (float) $s['qty'] <= 0 ? ' due' : '' ?>"><?= sales_h(rtrim(rtrim(number_format((float) $s['qty'], 3), '0'), '.')) ?></td></tr><?php endforeach; ?>
      <?php if (!$lowStock): ?><tr><td colspan="3" class="empty">Every stocked product is above <?= sales_h(rtrim(rtrim(number_format($lowLevel, 3), '0'), '.')) ?>.</td></tr><?php endif; ?>
      </tbody></table></section>
    <?php endif; ?>

    <?php if ($accReady): ?>
    <section class="card"><div class="card-head"><h2>Cheques due this week</h2><a class="link" href="../accounts_info/acc_cheque_treatment.php">Cheque treatment</a></div>
      <table class="t"><thead><tr><th>Cheque</th><th>Date</th><th>Party</th><th class="num">Amount</th></tr></thead><tbody>
      <?php foreach ($cheques as $c): ?><tr><td><?= sales_h($c['cheque_no']) ?><div class="muted small"><?= $c['direction'] === 'received' ? 'To deposit' : 'To be paid' ?></div></td><td><?= $c['cheque_date'] ? date('d M', strtotime($c['cheque_date'])) : '' ?></td><td><?= sales_h($c['party_name']) ?></td><td class="num"><?= sales_money($c['amount']) ?></td></tr><?php endforeach; ?>
      <?php if (!$cheques): ?><tr><td colspan="4" class="empty">No cheques due in the next 7 days.</td></tr><?php endif; ?>
      </tbody></table></section>
    <?php endif; ?>
  </div>
  <p class="foot muted small"><?php $lakh = bs_country()['grouping'] === 'lakh'; ?>Amounts in <?= sales_h(bs_country()['currency']) ?> (<?= sales_h(bs_currency_symbol()) ?>). On charts K = thousand, <?= $lakh ? 'L = lakh, Cr = crore' : 'M = million, B = billion' ?>. "Previous period" is the <?= $days ?> day<?= $days > 1 ? 's' : '' ?> before <?= sales_h(date('d M Y', strtotime($from))) ?>.</p>
</div>
<script>window.DASH = <?= sales_js([
    'daily' => $daily,
    'trend' => $trend,
    'channels' => array_map(fn($r) => ['label' => $r['label'], 'value' => (float) $r['value']], $channels),
    'sellers' => array_map(fn($r) => ['label' => ($r['code'] !== '' && $r['code'] !== null ? $r['code'] . ' - ' : '') . $r['label'], 'value' => (float) $r['value']], $sellers),
    'groups' => array_map(fn($r) => ['label' => $r['label'], 'value' => (float) $r['value']], $groups),
    'aging' => array_map(fn($r) => ['label' => $r['label'], 'value' => (float) $r['value'], 'n' => (int) $r['n']], $aging),
]) ?>;</script>
<script>window.BS_MONEY = <?= json_encode(['symbol' => bs_currency_symbol(), 'lakh' => bs_country()['grouping'] === 'lakh'], JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="dashboard.js?v=<?= (int) @filemtime(__DIR__ . '/dashboard.js') ?>"></script>
</body>
</html>
<?php include "../../addon/conf/connection_close.php";  ?>
