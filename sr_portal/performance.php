<?php
/** Target & Commission: the last 12 months of net sales, collection, target achievement and commission earned, paid and due. */
require_once __DIR__ . '/sr_common.php';
$a = sr_require_login();
$months = [];
for ($i = 0; $i < 12; $i++) {
    $m = date('Y-m-01', strtotime(date('Y-m-01') . " -$i months"));
    $months[$m] = sr_month_figures($a, $m);
}
$cur = reset($months);
$payments = cp_rows('SELECT period_month, paid_on, amount, payment_method, reference FROM sr_commission_payments WHERE sr_id=? ORDER BY paid_on DESC LIMIT 24', 'i', [(int) $a['sr_id']]);

sr_page_start('Target & Commission', 'performance');
$basis = $a['commission_basis'] === 'none' ? 'You are not on commission.' : 'Commission: ' . (float) $a['commission_rate'] . '% of ' . str_replace('_', ' ', $a['commission_basis'])
    . ((float) $a['target_bonus_rate'] ? ', plus a ' . (float) $a['target_bonus_rate'] . '% bonus on net sales in a month you reach your sales target' : '') . '.';
echo '<p class="muted">' . cp_h($basis) . ' Figures count posted invoices credited to you, less returns. Collections count once the office has entered or verified them.</p>';
echo '<h2>' . date('F Y') . '</h2><div class="tiles">'
    . '<div class="tile' . ($cur['target_reached'] ? ' ok' : '') . '"><span>Sales vs target</span><b>' . cp_money($cur['net_sales']) . ' / ' . ($cur['sales_target'] ? cp_money($cur['sales_target']) : '—') . '</b></div>'
    . '<div class="tile"><span>To reach target</span><b>' . ($cur['sales_target'] ? cp_money(max(0, $cur['sales_target'] - $cur['net_sales'])) : '—') . '</b></div>'
    . '<div class="tile"><span>Collection vs target</span><b>' . cp_money($cur['collection']) . ' / ' . ($cur['collection_target'] ? cp_money($cur['collection_target']) : '—') . '</b></div>'
    . '<div class="tile ok"><span>Commission so far</span><b>' . cp_money($cur['total']) . '</b></div></div>';
echo '<div class="scroll"><table><thead><tr><th>Month</th><th class="num">Invoices</th><th class="num">Net sales</th><th class="num">Target</th><th class="num">Achieved</th><th class="num">Collection</th><th class="num">Coll. target</th>'
    . '<th class="num">Commission</th><th class="num">Bonus</th><th class="num">Paid</th><th class="num">Due</th></tr></thead><tbody>';
$tot = array_fill_keys(['net_sales', 'collection', 'commission', 'bonus', 'paid', 'due'], 0.0);
foreach ($months as $m => $f) {
    foreach ($tot as $k => $v) {
        $tot[$k] += $f[$k];
    }
    echo '<tr><td><a href="sales.php?month=' . substr($m, 0, 7) . '">' . date('M Y', strtotime($m)) . '</a></td><td class="num">' . $f['invoices'] . '</td><td class="num">' . cp_money($f['net_sales']) . '</td><td class="num">' . ($f['sales_target'] ? cp_money($f['sales_target']) : '') . '</td>'
        . '<td class="num">' . ($f['sales_target'] ? '<span class="badge ' . ($f['target_reached'] ? 'ok' : '') . '">' . sr_pct($f['net_sales'], $f['sales_target']) . '</span>' : '') . '</td><td class="num">' . cp_money($f['collection']) . '</td>'
        . '<td class="num">' . ($f['collection_target'] ? cp_money($f['collection_target']) : '') . '</td><td class="num">' . cp_money($f['commission']) . '</td><td class="num">' . ($f['bonus'] ? cp_money($f['bonus']) : '') . '</td>'
        . '<td class="num">' . cp_money($f['paid']) . '</td><td class="num">' . ($f['due'] > 0.004 ? '<b>' . cp_money($f['due']) . '</b>' : cp_money($f['due'])) . '</td></tr>';
}
echo '<tr><td><b>12 months</b></td><td></td><td class="num"><b>' . cp_money($tot['net_sales']) . '</b></td><td></td><td></td><td class="num"><b>' . cp_money($tot['collection']) . '</b></td><td></td>'
    . '<td class="num"><b>' . cp_money($tot['commission']) . '</b></td><td class="num"><b>' . cp_money($tot['bonus']) . '</b></td><td class="num"><b>' . cp_money($tot['paid']) . '</b></td><td class="num"><b>' . cp_money($tot['due']) . '</b></td></tr></tbody></table></div>';
echo '<h2>Commission payments</h2><div class="scroll"><table><thead><tr><th>Paid on</th><th>For month</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead><tbody>';
echo $payments ? '' : '<tr><td colspan="5" class="empty">No commission paid yet.</td></tr>';
foreach ($payments as $p) {
    echo '<tr><td>' . cp_date($p['paid_on']) . '</td><td>' . date('M Y', strtotime($p['period_month'])) . '</td><td>' . cp_h(ucfirst((string) $p['payment_method'])) . '</td><td>' . cp_h($p['reference']) . '</td><td class="num">' . cp_money($p['amount']) . '</td></tr>';
}
echo '</tbody></table></div>';
sr_page_end();
