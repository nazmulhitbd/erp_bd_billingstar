<?php
/** My Customers: the customers this SR may see, with due; ?id= shows contact, credit, unpaid invoices and recent ledger. */
require_once __DIR__ . '/sr_common.php';
$a = sr_require_login();

if (isset($_GET['id'])) {
    $c = sr_customer($a, (int) $_GET['id']);
    if (!$c) {
        cp_redirect('customers.php', 'err', 'Customer not found.');
    }
    $cid = (int) $c['customer_id'];
    $unpaid = cp_rows("SELECT invoice_id, invoice_no, sales_date, net_amount, cash_received, balance_amount FROM sales_invoices WHERE customer_id=? AND status='posted' AND balance_amount>0.004 ORDER BY sales_date, invoice_id", 'i', [$cid]);
    $ledger = cp_rows('SELECT * FROM (SELECT * FROM customer_ledger_entries WHERE customer_id=? ORDER BY entry_date DESC, sort_order DESC, ref_id DESC LIMIT 15) x ORDER BY entry_date, sort_order, ref_id', 'i', [$cid]);
    $pending = cp_rows("SELECT collection_no, collected_on, amount, payment_method FROM sr_collections WHERE customer_id=? AND status='pending' ORDER BY collection_id", 'i', [$cid]);
    sr_page_start($c['customer_name'], 'customers');
    if ((int) $c['block_sales']) {
        echo '<div class="alert err">Sales to this customer are blocked by the office.</div>';
    }
    $limit = (float) $c['credit_limit'];
    echo '<div class="card meta"><div><b>Code:</b> ' . cp_h($c['trader_code'] ?: $c['customer_code']) . '</div><div><b>Mobile:</b> ' . cp_h($c['mobile']) . '</div><div><b>Contact:</b> ' . cp_h($c['attention']) . '</div>'
        . '<div><b>Address:</b> ' . cp_h($c['address']) . '</div><div><b>Category:</b> ' . cp_h($c['category_name']) . '</div><div><b>Price level:</b> ' . cp_h(ucfirst($c['price_level'])) . '</div>'
        . '<div><b>Credit:</b> ' . ((int) $c['allow_credit'] ? ($limit > 0 ? cp_money($limit) . ' limit' : 'Allowed') . ((int) $c['credit_days'] ? ', ' . (int) $c['credit_days'] . ' days' : '') : 'Cash only') . '</div>'
        . '<div><b>Last invoice:</b> ' . ($c['last_invoice_date'] ? cp_date($c['last_invoice_date']) : '—') . '</div></div>';
    echo '<div class="tiles"><div class="tile bad"><span>Balance owing</span><b>' . cp_money($c['balance']) . '</b></div><div class="tile"><span>Total sales</span><b>' . cp_money($c['total_sales']) . '</b></div>'
        . ($limit > 0 ? '<div class="tile"><span>Credit left</span><b>' . cp_money(max(0, $limit - (float) $c['balance'])) . '</b></div>' : '') . '</div>';
    echo '<p>' . ((int) $a['can_book_orders'] && !(int) $c['block_sales'] ? '<a class="btn" href="orders.php?new=1&amp;customer=' . $cid . '">Book order</a> ' : '')
        . ((int) $a['can_collect'] ? '<a class="btn light" href="collections.php?customer=' . $cid . '">Record collection</a>' : '') . '</p>';
    if ($pending) {
        echo '<div class="alert info">Collections waiting for the office to verify: ';
        echo implode(', ', array_map(fn($p) => cp_h($p['collection_no'] . ' · ' . cp_money($p['amount'])), $pending)) . '. The balance above does not include them yet.</div>';
    }
    echo '<h2>Unpaid invoices</h2><div class="scroll"><table><thead><tr><th>Date</th><th>Invoice No</th><th>Age</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Due</th></tr></thead><tbody>';
    echo $unpaid ? '' : '<tr><td colspan="6" class="empty">No unpaid invoices.</td></tr>';
    foreach ($unpaid as $r) {
        $age = (int) (new DateTime($r['sales_date']))->diff(new DateTime('today'))->format('%a');
        echo '<tr><td>' . cp_date($r['sales_date']) . '</td><td><a href="sales.php?id=' . (int) $r['invoice_id'] . '">' . cp_h($r['invoice_no']) . '</a></td><td><span class="badge' . ((int) $c['credit_days'] && $age > (int) $c['credit_days'] ? ' bad' : '') . '">' . $age . ' days</span></td>'
            . '<td class="num">' . cp_money($r['net_amount']) . '</td><td class="num">' . cp_money($r['cash_received']) . '</td><td class="num">' . cp_money($r['balance_amount']) . '</td></tr>';
    }
    echo '</tbody></table></div><h2>Recent ledger</h2><div class="scroll"><table><thead><tr><th>Date</th><th>Particulars</th><th>Document</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>';
    echo $ledger ? '' : '<tr><td colspan="5" class="empty">No entries.</td></tr>';
    foreach ($ledger as $r) {
        echo '<tr><td>' . cp_date($r['entry_date']) . '</td><td>' . cp_h($r['entry_type']) . '</td><td>' . cp_h($r['doc_no']) . '</td><td class="num">' . ((float) $r['debit'] ? cp_money($r['debit']) : '') . '</td><td class="num">' . ((float) $r['credit'] ? cp_money($r['credit']) : '') . '</td></tr>';
    }
    echo '</tbody></table></div><p><a class="btn light" href="customers.php">All customers</a></p>';
    sr_page_end();
    exit;
}

$q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 60));
$due = !empty($_GET['due']);
$rows = array_filter(sr_customers($a, $q), fn($c) => !$due || (float) $c['balance'] > 0.004);
sr_page_start((int) $a['see_all_customers'] ? 'Customers' : 'My Customers', 'customers');
echo '<form method="get" class="card row"><div><label for="q">Name, mobile or code</label><input id="q" name="q" value="' . cp_h($q) . '"></div>'
    . '<div class="auto"><label><input type="checkbox" name="due" value="1"' . ($due ? ' checked' : '') . '> Owing only</label></div><div class="auto"><button class="btn">Search</button></div></form>';
echo '<div class="scroll"><table><thead><tr><th>Customer</th><th>Code</th><th>Mobile</th><th>Category</th><th class="num">Balance</th><th>Last invoice</th></tr></thead><tbody>';
echo $rows ? '' : '<tr><td colspan="6" class="empty">' . ((int) $a['see_all_customers'] ? 'No customers found.' : 'No customers found. The office assigns customers to you in Trader wise SR Fixing.') . '</td></tr>';
foreach ($rows as $c) {
    echo '<tr><td><a href="customers.php?id=' . (int) $c['customer_id'] . '">' . cp_h($c['customer_name']) . '</a>' . ((int) $c['block_sales'] ? ' <span class="badge bad">Blocked</span>' : '') . '</td><td>' . cp_h($c['trader_code'] ?: $c['customer_code']) . '</td>'
        . '<td>' . cp_h($c['mobile']) . '</td><td>' . cp_h($c['category_name']) . '</td><td class="num">' . ((float) $c['balance'] > 0.004 ? '<span class="badge bad">' . cp_money($c['balance']) . '</span>' : cp_money($c['balance'])) . '</td>'
        . '<td>' . ($c['last_invoice_date'] ? cp_date($c['last_invoice_date']) : '') . '</td></tr>';
}
echo '</tbody></table></div>';
sr_page_end();
