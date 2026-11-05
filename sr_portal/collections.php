<?php
/**
 * Collections: money the SR collected from a customer. It waits as "pending" until the office verifies it
 * (Sales Person Management › SR Collections), which then pays the customer's oldest due invoices.
 * An SR may withdraw a collection while it is still pending.
 */
require_once __DIR__ . '/sr_common.php';
$a = sr_require_login();
if (!(int) $a['can_collect']) {
    cp_redirect('index.php', 'err', 'Your account may not record collections. Ask the office.');
}
const SR_BACKDATE_DAYS = 7;

/** Invoice due of a customer less what is already waiting for verification. */
function sr_collectable(array $c)
{
    $pending = (float) cp_row("SELECT COALESCE(SUM(amount), 0) AS s FROM sr_collections WHERE customer_id=? AND status='pending'", 'i', [(int) $c['customer_id']])['s'];
    return round(max(0, (float) $c['invoice_due'] - $pending), 2);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cp_check_csrf('collections.php');
    if (($_POST['action'] ?? '') === 'withdraw') {
        $n = cp_exec("DELETE FROM sr_collections WHERE collection_id=? AND sr_id=? AND status='pending'", 'ii', [(int) ($_POST['collection_id'] ?? 0), (int) $a['sr_id']]);
        $gone = $n->affected_rows;
        $n->close();
        cp_redirect('collections.php', $gone ? 'ok' : 'err', $gone ? 'Collection withdrawn.' : 'Only a collection still waiting for the office can be withdrawn.');
    }
    $cid = (int) ($_POST['customer_id'] ?? 0);
    $back = 'collections.php?customer=' . $cid;
    $c = sr_customer($a, $cid);
    if (!$c) {
        cp_redirect('collections.php', 'err', 'Choose one of your customers.');
    }
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    $date = (string) ($_POST['collected_on'] ?? '');
    $method = array_key_exists($_POST['payment_method'] ?? '', SR_METHODS) ? (string) $_POST['payment_method'] : 'cash';
    $reference = cp_post('reference', 120);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date) || $date > date('Y-m-d') || $date < date('Y-m-d', strtotime('-' . SR_BACKDATE_DAYS . ' days'))) {
        cp_redirect($back, 'err', 'The collection date must be today or within the last ' . SR_BACKDATE_DAYS . ' days.');
    }
    $max = sr_collectable($c);
    if ($amount <= 0 || $amount > $max + 0.004) {
        cp_redirect($back, 'err', $max > 0 ? 'Enter an amount up to ' . cp_money($max) . ', the invoice due not already collected.' : $c['customer_name'] . ' has no invoice due to collect.');
    }
    if ($method !== 'cash' && $reference === '') {
        cp_redirect($back, 'err', 'Enter the cheque or transaction number.');
    }
    $no = sr_next_no('CL', 'sr_collections', 'collection_no');
    cp_insert('INSERT INTO sr_collections (collection_no, sr_id, account_id, customer_id, collected_on, amount, payment_method, reference, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'siiisdsss', [$no, (int) $a['sr_id'], (int) $a['account_id'], $cid, $date, $amount, $method, $reference, cp_post('note', 255)]);
    notify_admin('sales_person/sr_collections.php', 'Collection to verify: ' . $no, $a['sr_name'] . ' collected ' . cp_money($amount) . ' from ' . $c['customer_name'] . '.');
    cp_redirect('collections.php', 'ok', $no . ': ' . cp_money($amount) . ' from ' . $c['customer_name'] . ' recorded. The office will verify it.');
}

$month = sr_month($_GET['month'] ?? '');
$rows = cp_rows('SELECT k.*, c.customer_name FROM sr_collections k JOIN sales_customers c ON c.customer_id=k.customer_id WHERE k.sr_id=? AND k.collected_on BETWEEN ? AND ? ORDER BY k.collection_id DESC',
    'iss', [(int) $a['sr_id'], $month, date('Y-m-t', strtotime($month))]);
$c = isset($_GET['customer']) ? sr_customer($a, (int) $_GET['customer']) : null;
$customers = array_filter(sr_customers($a), fn($x) => (float) $x['invoice_due'] > 0.004);

sr_page_start('Collections', 'collections');
echo '<form method="post" class="card grid">' . cp_csrf_field() . '<h2>Record a collection</h2><div class="row"><div><label for="customer_id">Customer</label><select id="customer_id" name="customer_id" required><option value="">Choose…</option>';
foreach ($customers as $x) {
    echo '<option value="' . (int) $x['customer_id'] . '"' . ($c && (int) $c['customer_id'] === (int) $x['customer_id'] ? ' selected' : '') . '>' . cp_h($x['customer_name'] . ' · due ' . cp_money($x['invoice_due'])) . '</option>';
}
echo '</select></div><div><label for="amount">Amount' . ($c ? ' (up to ' . cp_money(sr_collectable($c)) . ')' : '') . '</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" required></div>'
    . '<div><label for="collected_on">Date</label><input id="collected_on" name="collected_on" type="date" required value="' . date('Y-m-d') . '" max="' . date('Y-m-d') . '" min="' . date('Y-m-d', strtotime('-' . SR_BACKDATE_DAYS . ' days')) . '"></div></div>'
    . '<div class="row"><div><label for="payment_method">Method</label><select id="payment_method" name="payment_method">';
foreach (SR_METHODS as $k => $l) {
    echo '<option value="' . $k . '">' . cp_h($l) . '</option>';
}
echo '</select></div><div><label for="reference">Cheque / transaction / receipt no</label><input id="reference" name="reference" maxlength="120"></div><div><label for="note">Note</label><input id="note" name="note" maxlength="255"></div></div>'
    . '<div><button class="btn">Record collection</button></div></form>';
if (!$customers) {
    echo '<div class="alert info">None of your customers has an invoice due.</div>';
}

$sum = fn($s) => array_sum(array_map(fn($r) => $r['status'] === $s ? (float) ($s === 'verified' ? $r['verified_amount'] : $r['amount']) : 0, $rows));
echo '<form method="get" class="card row"><div><label for="month">Month</label><input id="month" type="month" name="month" value="' . substr($month, 0, 7) . '"></div><div class="auto"><button class="btn">Show</button></div></form>';
echo '<div class="tiles"><div class="tile"><span>Waiting for office</span><b>' . cp_money($sum('pending')) . '</b></div><div class="tile ok"><span>Verified</span><b>' . cp_money($sum('verified')) . '</b></div><div class="tile bad"><span>Rejected</span><b>' . cp_money($sum('rejected')) . '</b></div></div>';
echo '<div class="scroll"><table><thead><tr><th>No</th><th>Date</th><th>Customer</th><th>Method</th><th class="num">Amount</th><th>Status</th><th>Office note</th><th></th></tr></thead><tbody>';
echo $rows ? '' : '<tr><td colspan="8" class="empty">No collections this month.</td></tr>';
foreach ($rows as $r) {
    $badge = ['pending' => ['Waiting', ''], 'verified' => ['Verified', 'ok'], 'rejected' => ['Rejected', 'bad']][$r['status']];
    $amount = cp_money($r['amount']) . ($r['status'] === 'verified' && abs((float) $r['verified_amount'] - (float) $r['amount']) > 0.004 ? '<br><span class="muted">verified ' . cp_money($r['verified_amount']) . '</span>' : '');
    echo '<tr><td>' . cp_h($r['collection_no']) . '</td><td>' . cp_date($r['collected_on']) . '</td><td>' . cp_h($r['customer_name']) . '</td><td>' . cp_h(SR_METHODS[$r['payment_method']] . ($r['reference'] !== '' ? ' · ' . $r['reference'] : '')) . '</td>'
        . '<td class="num">' . $amount . '</td><td><span class="badge ' . $badge[1] . '">' . $badge[0] . '</span></td><td>' . cp_h($r['staff_note']) . '</td><td>'
        . ($r['status'] === 'pending' ? '<form method="post" class="inline">' . cp_csrf_field() . '<input type="hidden" name="action" value="withdraw"><input type="hidden" name="collection_id" value="' . (int) $r['collection_id'] . '"><button class="btn small light" data-confirm="Withdraw ' . cp_h($r['collection_no']) . '?">Withdraw</button></form>' : '') . '</td></tr>';
}
echo '</tbody></table></div>';
sr_page_end();
