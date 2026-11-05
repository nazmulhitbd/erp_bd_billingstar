<?php
/** Dashboard: this month against target, commission, customers with due, pending collections and orders. */
require_once __DIR__ . '/sr_common.php';
$a = sr_require_login();
$month = date('Y-m-01');
$f = sr_month_figures($a, $month);
[$w, $t, $p] = sr_customer_scope($a);
$cust = cp_row("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN c.balance>0.004 THEN 1 ELSE 0 END), 0) AS with_due, COALESCE(SUM(GREATEST(c.balance, 0)), 0) AS due FROM customer_accounts c WHERE $w", $t, $p);
$overdue = cp_rows("SELECT c.customer_id, c.customer_name, c.mobile, c.balance, c.oldest_due_date, c.credit_days FROM customer_accounts c WHERE $w AND c.balance>0.004 ORDER BY c.oldest_due_date LIMIT 8", $t, $p);
$pendingCol = cp_row("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS s FROM sr_collections WHERE sr_id=? AND status='pending'", 'i', [(int) $a['sr_id']]);
$pendingOrd = (int) cp_row("SELECT COUNT(*) AS n FROM sales_orders WHERE sr_id=? AND status='open' AND approval_status='pending'", 'i', [(int) $a['sr_id']])['n'];

sr_page_start('Hello, ' . $a['sr_name'], 'index');
echo '<h2>' . date('F Y') . '</h2><div class="tiles">'
    . '<div class="tile"><span>Net sales</span><b>' . cp_money($f['net_sales']) . '</b></div>'
    . '<div class="tile' . ($f['target_reached'] ? ' ok' : '') . '"><span>Sales target · achieved</span><b>' . ($f['sales_target'] ? cp_money($f['sales_target']) . ' · ' . sr_pct($f['net_sales'], $f['sales_target']) : '—') . '</b></div>'
    . '<div class="tile"><span>Collected</span><b>' . cp_money($f['collection']) . '</b></div>'
    . '<div class="tile"><span>Collection target · achieved</span><b>' . ($f['collection_target'] ? cp_money($f['collection_target']) . ' · ' . sr_pct($f['collection'], $f['collection_target']) : '—') . '</b></div>'
    . '<div class="tile ok"><span>Commission earned</span><b>' . cp_money($f['total']) . '</b></div><div class="tile"><span>Commission due</span><b>' . cp_money($f['due']) . '</b></div></div>';
echo '<div class="tiles"><div class="tile"><span>' . ((int) $a['see_all_customers'] ? 'Customers' : 'My customers') . '</span><b>' . (int) $cust['n'] . '</b></div>'
    . '<div class="tile bad"><span>Owing · customers</span><b>' . cp_money($cust['due']) . ' · ' . (int) $cust['with_due'] . '</b></div>'
    . '<div class="tile"><span>Collections waiting for the office</span><b>' . (int) $pendingCol['n'] . ' · ' . cp_money($pendingCol['s']) . '</b></div>'
    . '<div class="tile"><span>Orders waiting for approval</span><b>' . $pendingOrd . '</b></div></div>';
echo '<h2>Customers to collect from</h2><div class="scroll"><table><thead><tr><th>Customer</th><th>Mobile</th><th class="num">Due</th><th>Oldest due</th><th></th></tr></thead><tbody>';
echo $overdue ? '' : '<tr><td colspan="5" class="empty">Nobody owes money. Well done.</td></tr>';
foreach ($overdue as $c) {
    $age = $c['oldest_due_date'] ? (int) (new DateTime($c['oldest_due_date']))->diff(new DateTime('today'))->format('%a') : 0;
    $late = (int) $c['credit_days'] > 0 && $age > (int) $c['credit_days'];
    echo '<tr><td><a href="customers.php?id=' . (int) $c['customer_id'] . '">' . cp_h($c['customer_name']) . '</a></td><td>' . cp_h($c['mobile']) . '</td><td class="num">' . cp_money($c['balance']) . '</td>'
        . '<td>' . ($c['oldest_due_date'] ? cp_date($c['oldest_due_date']) . ' <span class="badge' . ($late ? ' bad' : '') . '">' . $age . ' days</span>' : '') . '</td>'
        . '<td>' . ((int) $a['can_collect'] ? '<a class="btn small light" href="collections.php?customer=' . (int) $c['customer_id'] . '">Collect</a>' : '') . '</td></tr>';
}
echo '</tbody></table></div>';
sr_page_end();
