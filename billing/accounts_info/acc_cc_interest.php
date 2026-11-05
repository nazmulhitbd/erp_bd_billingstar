<?php
/**
 * CC Loan Interest: keep the annual rate history of each cash-credit loan account, work out interest on the
 * daily outstanding balance for a period, and post it as a journal voucher (Dr interest expense, Cr loan).
 */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'CC Loan Interest', 'cc');

$self = 'acc_cc_interest.php';
$loans = acc_accounts($conn, true, "a.account_type='loan'");
$loanIds = array_map('intval', array_column($loans, 'account_id'));

/**
 * Interest on the daily closing credit balance. Returns [rows, total] where each row is one stretch of days
 * with the same balance and rate.
 */
function acc_cc_calculate(mysqli $db, $accountId, $from, $to, $basis)
{
    $rates = sales_rows($db, 'SELECT annual_rate, effective_from FROM acc_cc_loan_rates WHERE account_id=? ORDER BY effective_from', 'i', [$accountId]);
    if (!$rates) {
        throw new RuntimeException('Add an interest rate for this loan account first.');
    }
    $acc = sales_row($db, 'SELECT opening_balance, opening_side FROM acc_accounts WHERE account_id=?', 'i', [$accountId]);
    $pre = sales_row($db, 'SELECT COALESCE(SUM(credit - debit), 0) AS b FROM acc_voucher_lines WHERE is_posted=1 AND account_id=? AND voucher_date < ?', 'is', [$accountId, $from]);
    $balance = -acc_signed_opening($acc['opening_balance'], $acc['opening_side']) + (float) $pre['b'];
    $changes = [];
    foreach (sales_rows($db, 'SELECT voucher_date AS d, SUM(credit - debit) AS c FROM acc_voucher_lines WHERE is_posted=1 AND account_id=? AND voucher_date BETWEEN ? AND ? GROUP BY voucher_date', 'iss', [$accountId, $from, $to]) as $c) {
        $changes[$c['d']] = (float) $c['c'];
    }
    $rows = [];
    $total = 0.0;
    $day = new DateTime($from);
    $end = new DateTime($to);
    while ($day <= $end) {
        $d = $day->format('Y-m-d');
        $balance += $changes[$d] ?? 0.0;
        $rate = null;
        foreach ($rates as $r) {
            if ($r['effective_from'] <= $d) {
                $rate = (float) $r['annual_rate'];
            }
        }
        $interest = ($rate !== null && $balance > 0) ? $balance * $rate / 100 / $basis : 0.0;
        $total += $interest;
        $last = count($rows) - 1;
        if ($last >= 0 && abs($rows[$last]['balance'] - $balance) < 0.005 && $rows[$last]['rate'] === $rate) {
            $rows[$last]['to'] = $d;
            $rows[$last]['days']++;
            $rows[$last]['interest'] += $interest;
        } else {
            $rows[] = ['from' => $d, 'to' => $d, 'days' => 1, 'balance' => $balance, 'rate' => $rate, 'interest' => $interest];
        }
        $day->modify('+1 day');
    }
    return [$rows, round($total, 2)];
}

$accountId = (int) ($_REQUEST['account_id'] ?? ($loanIds[0] ?? 0));
$from = sales_valid_date($_REQUEST['from'] ?? '', true) ?: date('Y-m-01');
$to = sales_valid_date($_REQUEST['to'] ?? '', true) ?: date('Y-m-d');
$basis = (int) ($_REQUEST['basis'] ?? acc_setting($conn, 'cc_day_basis', '365')) === 360 ? 360 : 365;
$keep = 'account_id=' . $accountId . '&from=' . $from . '&to=' . $to . '&basis=' . $basis;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self . '?' . $keep);
    $action = (string) ($_POST['action'] ?? '');
    try {
        if (!in_array($accountId, $loanIds, true)) {
            throw new RuntimeException('Pick a loan account. Set an account type to "Loan / CC" in General Accounts.');
        }
        if ($action === 'add_rate') {
            $rate = (float) ($_POST['annual_rate'] ?? 0);
            $effective = sales_valid_date($_POST['effective_from'] ?? '');
            if ($rate <= 0 || $rate > 100 || $effective === null) {
                throw new RuntimeException('Enter a rate between 0 and 100 and a valid effective date.');
            }
            sales_exec($conn, 'INSERT INTO acc_cc_loan_rates (account_id, annual_rate, effective_from, note) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE annual_rate=VALUES(annual_rate), note=VALUES(note)', 'idss', [$accountId, round($rate, 3), $effective, sales_post_string('note', 255)])->close();
            sales_flash_redirect($self . '?' . $keep, 'success', 'Rate ' . $rate . '% from ' . acc_fmt_date($effective) . ' saved.');
        }
        if ($action === 'delete_rate') {
            sales_exec($conn, 'DELETE FROM acc_cc_loan_rates WHERE rate_id=? AND account_id=?', 'ii', [(int) ($_POST['rate_id'] ?? 0), $accountId])->close();
            sales_flash_redirect($self . '?' . $keep, 'success', 'Rate removed.');
        }
        if ($action === 'post') {
            if ($from > $to || $to > date('Y-m-d')) {
                throw new RuntimeException('Pick a period that has already ended, with From before To.');
            }
            $expense = (int) acc_setting($conn, 'cc_interest_account');
            if (!$expense) {
                throw new RuntimeException('Set the CC Loan Interest Expense Account in Accounts Configuration.');
            }
            $conn->begin_transaction();
            sales_row($conn, 'SELECT account_id FROM acc_accounts WHERE account_id=? FOR UPDATE', 'i', [$accountId]);
            $overlap = sales_row($conn, "SELECT r.period_from, r.period_to FROM acc_cc_interest_runs r JOIN acc_vouchers v ON v.voucher_id=r.voucher_id WHERE r.account_id=? AND v.status<>'cancelled' AND r.period_from<=? AND r.period_to>=? LIMIT 1", 'iss', [$accountId, $to, $from]);
            if ($overlap) {
                throw new RuntimeException('Interest for ' . acc_fmt_date($overlap['period_from']) . ' to ' . acc_fmt_date($overlap['period_to']) . ' is already posted. Pick days after that.');
            }
            [, $total] = acc_cc_calculate($conn, $accountId, $from, $to, $basis);
            if ($total <= 0) {
                throw new RuntimeException('No interest is due for this period.');
            }
            $narration = 'CC loan interest ' . acc_fmt_date($from) . ' to ' . acc_fmt_date($to);
            [$voucherId, $voucherNo] = acc_create_voucher($conn, 'journal', $to, [
                ['account_id' => $expense, 'debit' => $total, 'line_narration' => $narration],
                ['account_id' => $accountId, 'credit' => $total, 'line_narration' => $narration],
            ], ['narration' => $narration, 'source' => 'cc_interest']);
            sales_insert($conn, 'INSERT INTO acc_cc_interest_runs (account_id, period_from, period_to, day_basis, interest_amount, voucher_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)', 'issidii', [$accountId, $from, $to, $basis, $total, $voucherId, (int) ($GLOBALS['user_id'] ?? 0)]);
            $conn->commit();
            sales_flash_redirect($self . '?' . $keep, 'success', 'Interest ' . sales_money($total) . ' posted as ' . $voucherNo . '.');
        }
        throw new RuntimeException('Unknown action.');
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($self . '?' . $keep, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$rates = $accountId ? sales_rows($conn, 'SELECT * FROM acc_cc_loan_rates WHERE account_id=? ORDER BY effective_from DESC', 'i', [$accountId]) : [];
$runs = $accountId ? sales_rows($conn, 'SELECT r.*, v.voucher_no, v.status FROM acc_cc_interest_runs r JOIN acc_vouchers v ON v.voucher_id=r.voucher_id WHERE r.account_id=? ORDER BY r.period_from DESC', 'i', [$accountId]) : [];
$calc = null;
$calcError = '';
if ($accountId && isset($_GET['calc'])) {
    try {
        $calc = acc_cc_calculate($conn, $accountId, min($from, $to), max($from, $to), $basis);
    } catch (Throwable $e) {
        $calcError = $e->getMessage();
    }
}

acc_page_start('CC Loan Interest', 'cc');
echo sales_flash_html($flash);
if (!$loans): ?>
<div class="errorbox">No loan account yet. In General Accounts, set an account's type to "Loan / CC".</div>
<?php acc_page_end(); exit; endif; ?>
<fieldset class="box"><legend>CC Loan Interest</legend>
<form method="get" class="grid g5" style="margin-top:0">
<div class="field"><label for="account_id">Loan Account</label><select id="account_id" name="account_id" onchange="this.form.submit()"><?= acc_account_options($loans, $accountId, null) ?></select></div>
<div class="field"><label for="from">From</label><input id="from" type="date" name="from" value="<?= sales_h($from) ?>"></div>
<div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="<?= sales_h($to) ?>"></div>
<div class="field"><label for="basis">Day Basis</label><select id="basis" name="basis"><?= acc_options(['365' => '365 days', '360' => '360 days'], (string) $basis) ?></select></div>
<div class="field"><label>&nbsp;</label><button class="btn primary" name="calc" value="1">Calculate</button></div>
</form>
<?php if ($calcError): ?><div class="alert error" style="margin-top:10px"><?= sales_h($calcError) ?></div><?php endif; ?>
<?php if ($calc): [$rows, $total] = $calc; ?>
<div class="table-scroll"><table><thead><tr><th>From</th><th>To</th><th class="num">Days</th><th class="num">Outstanding Balance</th><th class="num">Rate %</th><th class="num">Interest</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= acc_fmt_date($r['from']) ?></td><td><?= acc_fmt_date($r['to']) ?></td><td class="num"><?= (int) $r['days'] ?></td><td class="num"><?= sales_money($r['balance']) ?></td><td class="num"><?= $r['rate'] === null ? '<span class="muted">no rate</span>' : sales_h(rtrim(rtrim(number_format($r['rate'], 3), '0'), '.')) ?></td><td class="num"><?= number_format($r['interest'], 4) ?></td></tr><?php endforeach; ?>
<tr><th colspan="5" class="num">Total interest</th><th class="num"><?= sales_money($total) ?></th></tr>
</tbody></table></div>
<form method="post" style="margin-top:8px"><?= sales_csrf_field() ?><input type="hidden" name="action" value="post"><input type="hidden" name="account_id" value="<?= $accountId ?>"><input type="hidden" name="from" value="<?= sales_h($from) ?>"><input type="hidden" name="to" value="<?= sales_h($to) ?>"><input type="hidden" name="basis" value="<?= $basis ?>">
<button class="btn primary" <?= $total > 0 ? '' : 'disabled' ?> onclick="return confirm('Post <?= sales_money($total) ?> interest as a journal voucher?')">Post Interest Voucher</button> <span class="muted">Debits the interest expense account and credits the loan.</span></form>
<?php endif; ?>

<div class="grid g2">
<div class="section"><div class="section-title">Interest rates</div>
<form method="post" class="list-search"><?= sales_csrf_field() ?><input type="hidden" name="action" value="add_rate"><input type="hidden" name="account_id" value="<?= $accountId ?>"><input type="hidden" name="from" value="<?= sales_h($from) ?>"><input type="hidden" name="to" value="<?= sales_h($to) ?>"><input type="hidden" name="basis" value="<?= $basis ?>">
<input class="input" type="number" name="annual_rate" min="0.001" max="100" step="0.001" placeholder="Annual rate %" required style="max-width:140px"><input class="input" type="date" name="effective_from" value="<?= date('Y-m-d') ?>" required style="max-width:160px"><input class="input" name="note" maxlength="255" placeholder="Note"><button class="btn primary">Add Rate</button></form>
<table><thead><tr><th>Effective From</th><th class="num">Annual Rate %</th><th>Note</th><th></th></tr></thead><tbody>
<?php if (!$rates): ?><tr><td colspan="4" class="empty">No rate yet.</td></tr><?php endif; ?>
<?php foreach ($rates as $r): ?><tr><td><?= acc_fmt_date($r['effective_from']) ?></td><td class="num"><?= sales_h($r['annual_rate']) ?></td><td><?= sales_h($r['note']) ?></td><td><form method="post" onsubmit="return confirm('Remove this rate?')"><?= sales_csrf_field() ?><input type="hidden" name="action" value="delete_rate"><input type="hidden" name="rate_id" value="<?= (int) $r['rate_id'] ?>"><input type="hidden" name="account_id" value="<?= $accountId ?>"><button class="btn small danger">Remove</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="section"><div class="section-title">Posted interest</div>
<table><thead><tr><th>Period</th><th class="num">Interest</th><th>Voucher</th><th>Status</th></tr></thead><tbody>
<?php if (!$runs): ?><tr><td colspan="4" class="empty">Nothing posted yet.</td></tr><?php endif; ?>
<?php foreach ($runs as $r): ?><tr><td><?= acc_fmt_date($r['period_from']) ?> to <?= acc_fmt_date($r['period_to']) ?></td><td class="num"><?= sales_money($r['interest_amount']) ?></td><td><a target="_blank" href="acc_voucher.php?print=<?= (int) $r['voucher_id'] ?>"><?= sales_h($r['voucher_no']) ?></a></td><td><?= sales_h(ucfirst($r['status'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</div>
</fieldset>
<?php
acc_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
