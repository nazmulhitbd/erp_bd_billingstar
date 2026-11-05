<?php
/**
 * Business dashboard shown in the main pane (Home). Read-only summaries of sales, collections,
 * receivables, purchases, stock, cash & bank, approvals and cheques. Each block is skipped
 * quietly when its module's tables are not installed.
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

/* ---------- Formatting ---------- */
function dash_compact($v)
{
    $a = abs($v);
    $s = $a >= 10000000 ? round($a / 10000000, 1) . ' Cr' : ($a >= 100000 ? round($a / 100000, 1) . ' L' : ($a >= 1000 ? round($a / 1000, 1) . 'K' : number_format($a, 0)));
    return ($v < 0 ? '-' : '') . $s;
}
/** Signed change vs the previous period; $upIsGood decides the colour. */
function dash_delta($cur, $prev, $upIsGood = true)
{
    if ($prev == 0 && $cur == 0) {
        return '<span class="delta flat">No change</span>';
    }
    if ($prev == 0) {
        return '<span class="delta ' . ($upIsGood ? 'good' : 'bad') . '">New this period</span>';
    }
    $pct = ($cur - $prev) / abs($prev) * 100;
    $good = $upIsGood ? $pct >= 0 : $pct <= 0;
    $arrow = $pct >= 0 ? '&#9650;' : '&#9660;';
    return '<span class="delta ' . (abs($pct) < 0.05 ? 'flat' : ($good ? 'good' : 'bad')) . '">' . $arrow . ' ' . number_format(abs($pct), 1) . '% <span class="vs">vs previous ' . $GLOBALS['days'] . ' day' . ($GLOBALS['days'] > 1 ? 's' : '') . '</span></span>';
}
$tile = function ($label, $value, $sub = '', $href = '', $note = '') {
    $tag = $href ? 'a href="' . sales_h($href) . '"' : 'div';
    $close = $href ? 'a' : 'div';
    return '<' . $tag . ' class="tile"><div class="tile-label">' . sales_h($label) . '</div><div class="tile-value">' . $value . '</div>' . ($sub !== '' ? '<div class="tile-sub">' . $sub . '</div>' : '') . ($note !== '' ? '<div class="tile-note">' . sales_h($note) . '</div>' : '') . '</' . $close . '>';
};
$tk = fn($v) => '<span class="cur">&#2547;</span>' . sales_h(dash_compact($v));
$periodLabel = $period === 'custom' ? date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to)) : $periods[$period] . ' · ' . date('d M', strtotime($from)) . ($from !== $to ? ' – ' . date('d M Y', strtotime($to)) : ' ' . date('Y', strtotime($to)));
$company = function_exists('acc_setting') && $accReady ? acc_setting($conn, 'company_name', '') : '';
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard</title>
<link rel="stylesheet" href="dashboard.css">
</head>
<body>
<div class="dash">
  <header class="dash-head">
    <div>
      <h1><?= sales_h($greeting) ?><?= $company !== '' ? ', ' . sales_h($company) : '' ?></h1>
      <p class="muted"><?= sales_h($periodLabel) ?> · updated <?= date('h:i A') ?></p>
    </div>
    <form method="get" class="period">
      <?php foreach (['today', 'mtd', 'last30', 'qtd', 'ytd'] as $p): ?><a class="chip<?= $period === $p ? ' on' : '' ?>" href="?period=<?= $p ?>"><?= sales_h($periods[$p]) ?></a><?php endforeach; ?>
      <span class="custom"><input type="hidden" name="period" value="custom"><input type="date" name="from" value="<?= sales_h($from) ?>" aria-label="From"><input type="date" name="to" value="<?= sales_h($to) ?>" aria-label="To"><button class="chip<?= $period === 'custom' ? ' on' : '' ?>">Apply</button></span>
    </form>
  </header>

  <nav class="quick" aria-label="Quick actions">
    <?php foreach ([['New Invoice', '../sales/sales_invoice/sales_invoice.php'], ['POS Sale', '../sales/pos_sales/pos_sales.php'], ['Sales Order', '../sales/sales_order/sales_order.php'], ['Challan', '../challan/challan.php'], ['Purchase', '../purchase/purchase.php'], ['Receive Money', '../accounts_info/acc_rp_voucher.php?type=credit'], ['Make Payment', '../accounts_info/acc_rp_voucher.php?type=debit'], ['Stock Transfer', '../transfer/stock_transfer.php']] as [$label, $href]): ?>
      <a href="<?= sales_h($href) ?>"><span class="plus">+</span><?= sales_h($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$salesReady): ?>
    <div class="notice">Sales tables are not installed, so sales figures are hidden. Import <code>billing/sales/schema.sql</code>.</div>
  <?php endif; ?>

  <section class="kpis">
    <?php if ($salesReady): ?>
    <div class="hero tile">
      <div class="tile-label">Sales</div>
      <div class="hero-value"><?= $tk($k['sales']) ?></div>
      <div class="tile-sub"><?= dash_delta($k['sales'], $k['sales_prev']) ?></div>
      <div class="tile-note"><?= number_format($k['invoices']) ?> invoice<?= $k['invoices'] === 1 ? '' : 's' ?><?= $k['invoices'] ? ' · average ৳' . sales_h(dash_compact($k['sales'] / $k['invoices'])) : '' ?><?= isset($k['returns']) && $k['returns'] > 0 ? ' · returns ৳' . sales_h(dash_compact($k['returns'])) : '' ?></div>
    </div>
    <?= $tile('Collections', $tk($k['collected']), dash_delta($k['collected'], $k['collected_prev']), '', $k['sales'] > 0 ? round($k['collected'] / $k['sales'] * 100) . '% of sales' : '') ?>
    <?= $tile('Receivables due', $tk($k['receivable']), '', '../price_history/invoice_search.php?all=1', number_format($k['receivable_count']) . ' unpaid invoice' . ($k['receivable_count'] === 1 ? '' : 's')) ?>
    <?php endif; ?>
    <?php if (isset($k['purchases'])): ?><?= $tile('Purchases', $tk($k['purchases']), dash_delta($k['purchases'], $k['purchases_prev'], false), '../purchase/purchase.php?view=list') ?><?php endif; ?>
    <?php if (isset($k['cash_bank'])): ?><?= $tile('Cash & bank', $tk($k['cash_bank']), '', '../accounts_info/acc_register.php', 'Balance today') ?><?php endif; ?>
    <?php if (isset($k['payable'])): ?><?= $tile('Payables', $tk($k['payable']), '', '../accounts_info/acc_register.php', 'Owed to suppliers') ?><?php endif; ?>
    <?php if (isset($k['expenses'])): ?><?= $tile('Expenses', $tk($k['expenses']), dash_delta($k['expenses'], $k['expenses_prev'], false)) ?><?php endif; ?>
    <?php if (isset($k['stock_value'])): ?><?= $tile('Stock value', $tk($k['stock_value']), '', '', number_format($k['stock_items']) . ' product' . ($k['stock_items'] === 1 ? '' : 's') . ' in stock') ?><?php endif; ?>
    <?php if (isset($k['open_orders'])): ?><?= $tile('Open sales orders', number_format($k['open_orders']), '', '../sales/sales_order/sales_order.php?view=list&status=open', '৳' . dash_compact($k['open_orders_value']) . ' to deliver') ?><?php endif; ?>
    <?= $tile('Waiting for approval', number_format($k['approvals']), '', '', $k['approvals'] ? 'Across ' . count($approvals) . ' kind' . (count($approvals) === 1 ? '' : 's') . ' of document' : 'All clear') ?>
  </section>

  <?php if ($trend): ?>
  <section class="card wide">
    <div class="card-head"><div><h2>Sales and collections, last 12 months</h2><span class="muted small"><?= date('M Y') ?> is month to date</span></div>
      <div class="legend"><span><i class="key s1"></i>Sales</span><span><i class="key s2"></i>Collections</span><button type="button" class="link" id="toggle-table" aria-expanded="false">Show table</button></div></div>
    <div id="trend" class="chart" role="img" aria-label="Monthly sales and collections for the last 12 months"></div>
    <div id="trend-table" hidden></div>
  </section>
  <?php endif; ?>

  <div class="grid">
    <?php if ($aging): ?>
    <section class="card"><div class="card-head"><h2>Receivables by age</h2><a class="link" href="../price_history/invoice_search.php?all=1">View invoices</a></div><div id="aging" class="bars"></div></section>
    <?php endif; ?>
    <?php if ($salesReady): ?>
    <section class="card"><div class="card-head"><h2>Top products</h2><span class="muted small">by sales, this period</span></div><div id="products" class="bars"><?php if (!$topProducts): ?><p class="empty">No sales in this period.</p><?php endif; ?></div></section>
    <?php endif; ?>

    <section class="card"><div class="card-head"><h2>Needs your attention</h2></div>
      <ul class="tasks">
        <?php foreach ($approvals as [$label, $n, $href]): ?><li><a href="<?= sales_h($href) ?>"><span class="badge-n"><?= (int) $n ?></span><?= sales_h($label) ?></a></li><?php endforeach; ?>
        <?php if ($lowStock): ?><li><span class="badge-n warn"><?= count($lowStock) ?></span>Product<?= count($lowStock) === 1 ? '' : 's' ?> at or below <?= sales_h(rtrim(rtrim(number_format($lowLevel, 3), '0'), '.')) ?> in stock</li><?php endif; ?>
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
    <?php endif; ?>

    <?php if ($invReady): ?>
    <section class="card"><div class="card-head"><h2>Low stock</h2><a class="link" href="../amedment/stock_amendment_in.php">Add stock</a></div>
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
  <p class="foot muted small">Amounts in Taka (৳). K = thousand, L = lakh, Cr = crore. Changes compare with the <?= $days ?> day<?= $days > 1 ? 's' : '' ?> before <?= sales_h(date('d M Y', strtotime($from))) ?>.</p>
</div>
<script>window.DASH = <?= sales_js(['trend' => $trend, 'aging' => array_map(fn($r) => ['label' => $r['label'], 'value' => (float) $r['value'], 'n' => (int) $r['n']], $aging), 'products' => array_map(fn($r) => ['label' => $r['label'], 'model' => (string) $r['model'], 'value' => (float) $r['value'], 'qty' => (float) $r['qty']], $topProducts)]) ?>;</script>
<script src="dashboard.js"></script>
</body>
</html>
<?php include "../../addon/conf/connection_close.php";  ?>
