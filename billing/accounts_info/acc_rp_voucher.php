<?php
/**
 * Receive Voucher (credit) and Payment Voucher (debit), plus their Inter Branch versions (?ib=1).
 * Layout follows "Credit Voucher(Receive)": the Under account is the cash/bank side; every row is one party
 * amount with its own mode, cheque and invoice number.
 */
require_once __DIR__ . '/acc_common.php';

$type = ($_REQUEST['type'] ?? 'credit') === 'debit' ? 'debit' : 'credit';
$ib = !empty($_REQUEST['ib']);
$title = ($ib ? 'Inter Branch ' : '') . ($type === 'credit' ? 'Credit Voucher(Receive)' : 'Debit Voucher(Payment)');
$menuKey = $ib ? ($type === 'credit' ? 'ibc' : 'ibd') : ($type === 'credit' ? 'rv' : 'pv');
acc_require_ready($conn, $title, $menuKey);
$self = 'acc_rp_voucher.php?type=' . $type . ($ib ? '&ib=1' : '');
$userId = (int) ($GLOBALS['user_id'] ?? 0);
$branch = acc_current_branch($conn);

/* ---------- JSON: open invoices of a party (Invoice List) ---------- */
if (isset($_GET['invoices'])) {
    $subId = (int) $_GET['invoices'];
    $out = [];
    if (sales_tables_ready($conn, ['traders', 'sales_invoices'])) {
        $out = sales_rows(
            $conn,
            "SELECT i.invoice_no, i.sales_date, i.net_amount, i.balance_amount FROM acc_sub_ledgers s JOIN traders t ON t.trader_id=s.trader_id JOIN sales_invoices i ON i.customer_id=t.sales_customer_id "
                . "WHERE s.sub_ledger_id=? AND i.status='posted' AND i.balance_amount > 0 ORDER BY i.sales_date, i.invoice_id LIMIT 200",
            'i',
            [$subId]
        );
    }
    sales_json($out);
}

/* ---------- Prints: RV (money receipt / payment voucher) and Adjust (invoice adjustment) ---------- */
if (isset($_GET['print'])) {
    $v = sales_row($conn, 'SELECT * FROM acc_vouchers WHERE voucher_id=?', 'i', [(int) $_GET['print']]);
    if (!$v) {
        http_response_code(404);
        exit('Voucher not found.');
    }
    $lines = sales_rows($conn, 'SELECT l.*, a.account_name, a.account_type, s.sub_ledger_name, s.sub_ledger_code FROM acc_voucher_lines l JOIN acc_accounts a ON a.account_id=l.account_id LEFT JOIN acc_sub_ledgers s ON s.sub_ledger_id=l.sub_ledger_id WHERE l.voucher_id=? ORDER BY l.line_no', 'i', [(int) $v['voucher_id']]);
    $party = array_values(array_filter($lines, fn($l) => !in_array($l['account_type'], ['cash', 'bank'], true)));
    $bank = array_values(array_filter($lines, fn($l) => in_array($l['account_type'], ['cash', 'bank'], true)));
    $kind = ($_GET['kind'] ?? 'rv') === 'adjust' ? 'adjust' : 'rv';
    $heading = $kind === 'adjust' ? 'Invoice Adjustment' : ($v['voucher_type'] === 'credit' ? 'Money Receipt' : 'Payment Voucher');
    $branches = array_column(acc_branches($conn), 'branch_name', 'branch_id');
    $words = function ($amount) {
        $f = new NumberFormatter('en', NumberFormatter::SPELLOUT);
        $taka = (int) floor($amount);
        $paisa = (int) round(($amount - $taka) * 100);
        return ucwords($f->format($taka)) . ' Taka' . ($paisa ? ' and ' . ucwords($f->format($paisa)) . ' Paisa' : '') . ' Only';
    };
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><title><?= sales_h($v['voucher_no']) ?></title>
    <style>body{font:13px Arial,sans-serif;margin:24px;color:#111}.head{display:flex;justify-content:space-between}.co b{font-size:20px}h2{text-align:center;margin:12px 0}table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border:1px solid #999;padding:6px;text-align:left}th{background:#eee}.num{text-align:right}.meta{display:grid;grid-template-columns:1fr 1fr;gap:4px 20px}.sign{display:flex;justify-content:space-between;margin-top:70px}.sign div{border-top:1px solid #000;width:22%;text-align:center;padding-top:4px}.draft{text-align:center;color:#a33;font-weight:bold}@media print{button{display:none}}</style></head>
    <body<?= !empty($_GET['auto']) ? ' onload="window.print()"' : '' ?>><button onclick="window.print()">Print</button>
    <div class="head"><div class="co"><b><?= sales_h(acc_setting($conn, 'company_name')) ?></b><br><?= sales_h(acc_setting($conn, 'company_address')) ?></div><div>Phone : <?= sales_h(acc_setting($conn, 'company_phone')) ?><br>Email : <?= sales_h(acc_setting($conn, 'company_email')) ?></div></div>
    <h2><?= sales_h($heading) ?></h2><?php if ($v['status'] !== 'posted'): ?><div class="draft"><?= strtoupper(sales_h($v['status'])) ?> - NOT POSTED</div><?php endif; ?>
    <div class="meta"><div><b>Voucher No:</b> <?= sales_h($v['voucher_no']) ?></div><div><b>Date:</b> <?= acc_fmt_date($v['voucher_date']) ?></div><div><b>Ref No:</b> <?= sales_h($v['ref_no']) ?></div><div><b><?= $v['voucher_type'] === 'credit' ? 'Received In' : 'Paid From' ?>:</b> <?= sales_h(implode(', ', array_unique(array_column($bank, 'account_name')))) ?></div>
    <?php if ((int) $v['is_inter_branch']): ?><div><b>From Branch:</b> <?= sales_h($branches[(int) $v['branch_id']] ?? '') ?></div><div><b>To Branch:</b> <?= sales_h($branches[(int) $v['to_branch_id']] ?? '') ?></div><?php endif; ?>
    <div><b>Sales Person:</b> <?= sales_h($v['sales_person']) ?></div><div><b>Narration:</b> <?= sales_h($v['narration']) ?></div></div>
    <?php if ($kind === 'adjust'):
    $invoices = [];
    if (sales_tables_ready($conn, ['sales_invoices'])) {
        foreach (array_filter(array_unique(array_column($party, 'invoice_no'))) as $no) {
            $invoices[$no] = sales_row($conn, 'SELECT sales_date, net_amount, balance_amount FROM sales_invoices WHERE invoice_no=?', 's', [$no]);
        }
    } ?>
    <table><thead><tr><th>SL</th><th>Particulars</th><th>Invoice No</th><th>Invoice Date</th><th class="num">Invoice Amount</th><th class="num">Due Before</th><th class="num">Adjusted</th></tr></thead><tbody>
    <?php foreach ($party as $i => $l): $inv = $invoices[$l['invoice_no']] ?? null; ?><tr><td><?= $i + 1 ?></td><td><?= sales_h($l['sub_ledger_name'] ?: $l['account_name']) ?></td><td><?= sales_h($l['invoice_no'] ?: 'On account') ?></td><td><?= $inv ? acc_fmt_date($inv['sales_date']) : '' ?></td><td class="num"><?= $inv ? sales_money($inv['net_amount']) : '' ?></td><td class="num"><?= $inv ? sales_money($inv['balance_amount']) : '' ?></td><td class="num"><?= sales_money($l['debit'] + $l['credit']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php else: ?>
    <table><thead><tr><th>SL</th><th>Particulars</th><th>Mode</th><th>Cheque No</th><th>Cheque Date</th><th>Invoice No</th><th>Narration</th><th class="num">Amount</th></tr></thead><tbody>
    <?php foreach ($party as $i => $l): ?><tr><td><?= $i + 1 ?></td><td><?= sales_h($l['sub_ledger_name'] ? $l['sub_ledger_name'] . ' (' . $l['account_name'] . ')' : $l['account_name']) ?></td><td><?= sales_h($l['transaction_mode']) ?></td><td><?= sales_h($l['cheque_no']) ?></td><td><?= acc_fmt_date($l['cheque_date']) ?></td><td><?= sales_h($l['invoice_no']) ?></td><td><?= sales_h($l['line_narration']) ?></td><td class="num"><?= sales_money($l['debit'] + $l['credit']) ?></td></tr><?php endforeach; ?>
    <tr><th colspan="7" class="num">Total</th><th class="num"><?= sales_money($v['total_amount']) ?></th></tr></tbody></table>
    <p><b>In words:</b> <?= sales_h(class_exists('NumberFormatter') ? $words((float) $v['total_amount']) : sales_money($v['total_amount'])) ?></p>
    <?php endif; ?>
    <div class="sign"><div>Prepared By</div><div>Checked By</div><div>Approved By</div><div><?= $v['voucher_type'] === 'credit' ? 'Received From' : 'Received By' ?></div></div></body></html><?php
    exit;
}

/* ---------- Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voucherId = (int) ($_POST['voucher_id'] ?? 0);
    $back = $self . ($voucherId ? '&id=' . $voucherId : '');
    sales_require_csrf($back);
    try {
        $date = sales_valid_date($_POST['voucher_date'] ?? '');
        if ($date === null) {
            throw new RuntimeException('Enter a valid date.');
        }
        $under = (int) ($_POST['under_account'] ?? 0);
        $underAcc = sales_row($conn, "SELECT account_id, account_type FROM acc_accounts WHERE account_id=? AND account_type IN ('cash','bank') AND is_active=1", 'i', [$under]);
        if (!$underAcc) {
            throw new RuntimeException('Pick the cash or bank account in Under.');
        }
        $toBranch = $ib ? (int) ($_POST['to_branch_id'] ?? 0) : $branch;
        if ($ib && (!in_array($toBranch, array_map('intval', array_column(acc_branches($conn), 'branch_id')), true) || $toBranch === $branch)) {
            throw new RuntimeException('Pick a To Branch different from the working branch.');
        }
        $entries = json_decode((string) ($_POST['entries_json'] ?? '[]'), true);
        if (!is_array($entries) || !$entries || count($entries) > 200) {
            throw new RuntimeException('Add at least one row.');
        }
        $lines = [];
        foreach ($entries as $e) {
            $amount = sales_amount($e['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $common = ['transaction_mode' => $e['transaction_mode'] ?? 'Cash', 'cheque_no' => $e['cheque_no'] ?? '', 'cheque_date' => $e['cheque_date'] ?? '', 'invoice_no' => $e['invoice_no'] ?? '', 'line_narration' => $e['line_narration'] ?? ''];
            $party = $common + ['account_id' => (int) ($e['account_id'] ?? 0), 'sub_ledger_id' => (int) ($e['sub_ledger_id'] ?? 0), 'branch_id' => $toBranch];
            $cash = $common + ['account_id' => $under, 'sub_ledger_id' => 0, 'branch_id' => $branch, 'invoice_no' => ''];
            if ($type === 'credit') {
                $lines[] = $cash + ['debit' => $amount, 'credit' => 0];
                $lines[] = $party + ['debit' => 0, 'credit' => $amount];
            } else {
                $lines[] = $party + ['debit' => $amount, 'credit' => 0];
                $lines[] = $cash + ['debit' => 0, 'credit' => $amount];
            }
        }
        [$clean, $total] = acc_validate_lines($conn, $type, $lines);
        $post = ($_POST['action'] ?? '') === 'post' || acc_setting($conn, 'require_posting', '1') === '0';
        $mode = $clean[0]['transaction_mode'] ?: 'Cash';
        $conn->begin_transaction();
        acc_assert_unlocked($conn, $date, $branch);
        $header = [$date, sales_post_string('ref_no', 80), sales_post_string('narration', 500), sales_post_string('sales_person', 140), $mode, $total, $branch, $ib ? $toBranch : null, $ib ? 1 : 0];
        if ($voucherId > 0) {
            $old = sales_row($conn, 'SELECT voucher_no, voucher_type, status, is_inter_branch, voucher_date, branch_id FROM acc_vouchers WHERE voucher_id=? FOR UPDATE', 'i', [$voucherId]);
            if (!$old || $old['voucher_type'] !== $type || (int) $old['is_inter_branch'] !== ($ib ? 1 : 0)) {
                throw new RuntimeException('Voucher not found.');
            }
            if ($old['status'] !== 'draft') {
                throw new RuntimeException('Voucher ' . $old['voucher_no'] . ' is ' . $old['status'] . '. Unpost it from Voucher Posting before editing.');
            }
            acc_assert_unlocked($conn, $old['voucher_date'], (int) $old['branch_id']);
            sales_exec($conn, 'UPDATE acc_vouchers SET voucher_date=?, ref_no=?, narration=?, sales_person=?, transaction_mode=?, total_amount=?, branch_id=?, to_branch_id=?, is_inter_branch=? WHERE voucher_id=?', 'sssssdiiii', array_merge($header, [$voucherId]))->close();
            $voucherNo = $old['voucher_no'];
        } else {
            $voucherNo = acc_next_voucher_no($conn, $type, $date, $ib);
            $voucherId = sales_insert($conn, 'INSERT INTO acc_vouchers (voucher_date, ref_no, narration, sales_person, transaction_mode, total_amount, branch_id, to_branch_id, is_inter_branch, voucher_no, voucher_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'sssssdiiissi', array_merge($header, [$voucherNo, $type, $userId]));
        }
        acc_write_lines($conn, $voucherId, $clean);
        if ($post) {
            sales_exec($conn, "UPDATE acc_vouchers SET status='posted', posted_by=?, posted_at=NOW() WHERE voucher_id=?", 'ii', [$userId, $voucherId])->close();
            acc_sync_lines($conn, $voucherId);
        }
        acc_sync_cheques($conn, $voucherId);
        if (!$post) {
            notify_admin('accounts_info/acc_posting.php', 'Voucher to post: ' . $voucherNo, 'Saved as draft.');
        }
        $conn->commit();
        sales_flash_redirect($self . '&id=' . $voucherId, 'success', $voucherNo . ($post ? ' saved and posted.' : ' saved as draft. Use Posting to post it.'));
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        $_SESSION['acc_rp_repost'] = $_POST;
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

/* ---------- Page ---------- */
$flash = sales_take_flash();
$repost = ($flash['kind'] ?? '') === 'error' ? ($_SESSION['acc_rp_repost'] ?? null) : null;
unset($_SESSION['acc_rp_repost']);
if (isset($_GET['search'])) {
    $q = trim((string) $_GET['search']);
    $found = sales_row($conn, 'SELECT voucher_id FROM acc_vouchers WHERE voucher_type=? AND is_inter_branch=? AND (voucher_no=? OR ref_no=?) ORDER BY voucher_id DESC LIMIT 1', 'siss', [$type, $ib ? 1 : 0, $q, $q]);
    if ($found) {
        header('Location: ' . $self . '&id=' . (int) $found['voucher_id'], true, 303);
        exit;
    }
    $flash = ['kind' => 'error', 'message' => 'No voucher matches "' . $q . '".'];
}
$view = (string) ($_GET['view'] ?? 'form');
$voucher = null;
$entries = [];
$under = (int) acc_setting($conn, 'cash_account');
$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $voucher = sales_row($conn, 'SELECT * FROM acc_vouchers WHERE voucher_id=? AND voucher_type=? AND is_inter_branch=?', 'isi', [$id, $type, $ib ? 1 : 0]);
}
$cashBank = acc_accounts($conn, true, "a.account_type IN ('cash','bank')");
$cashIds = array_map('intval', array_column($cashBank, 'account_id'));
if ($voucher) {
    foreach (sales_rows($conn, 'SELECT * FROM acc_voucher_lines WHERE voucher_id=? ORDER BY line_no', 'i', [$id]) as $l) {
        if (in_array((int) $l['account_id'], $cashIds, true)) {
            $under = (int) $l['account_id'];
            continue;
        }
        $entries[] = ['account_id' => (int) $l['account_id'], 'sub_ledger_id' => (int) $l['sub_ledger_id'], 'amount' => (float) $l['debit'] + (float) $l['credit'], 'transaction_mode' => $l['transaction_mode'], 'cheque_no' => $l['cheque_no'], 'cheque_date' => $l['cheque_date'], 'invoice_no' => $l['invoice_no'], 'line_narration' => $l['line_narration']];
    }
}
$cashBank = perm_filter_ledgers($cashBank); // after $cashIds: a saved voucher's cash line is found among all of them
$h = $repost ?: ($voucher ?: []);
if ($repost) {
    $entries = json_decode((string) ($repost['entries_json'] ?? '[]'), true) ?: [];
    $under = (int) ($repost['under_account'] ?? $under);
}
$readOnly = $voucher && $voucher['status'] !== 'draft';
$prev = sales_row($conn, 'SELECT voucher_id FROM acc_vouchers WHERE voucher_type=? AND is_inter_branch=? AND voucher_id<? ORDER BY voucher_id DESC LIMIT 1', 'sii', [$type, $ib ? 1 : 0, $voucher ? $id : PHP_INT_MAX]);
$next = $voucher ? sales_row($conn, 'SELECT voucher_id FROM acc_vouchers WHERE voucher_type=? AND is_inter_branch=? AND voucher_id>? ORDER BY voucher_id LIMIT 1', 'sii', [$type, $ib ? 1 : 0, $id]) : null;

// Particulars: plain accounts other than cash/bank, plus parties under control accounts.
$parts = [];
foreach (acc_accounts($conn) as $a) {
    if ((int) $a['has_sub_ledger'] || in_array($a['account_type'], ['cash', 'bank'], true)) {
        continue;
    }
    $parts[] = ['key' => 'a:' . $a['account_id'], 'account_id' => (int) $a['account_id'], 'sub_ledger_id' => 0, 'name' => $a['account_name'], 'code' => $a['account_code'], 'sub1' => $a['group_name'], 'sub2' => ''];
}
foreach (sales_rows($conn, 'SELECT s.sub_ledger_id, s.account_id, s.sub_ledger_code, s.sub_ledger_name, g.group_name, a.account_name FROM acc_sub_ledgers s JOIN acc_sub_ledger_groups g ON g.slg_id=s.slg_id JOIN acc_accounts a ON a.account_id=s.account_id WHERE s.is_active=1 AND a.is_active=1 ORDER BY s.sub_ledger_name') as $s) {
    $parts[] = ['key' => 's:' . $s['sub_ledger_id'], 'account_id' => (int) $s['account_id'], 'sub_ledger_id' => (int) $s['sub_ledger_id'], 'name' => $s['sub_ledger_name'], 'code' => $s['sub_ledger_code'], 'sub1' => $s['group_name'], 'sub2' => $s['account_name']];
}
$parts = perm_filter_ledgers($parts);

acc_page_start($title, $menuKey);
echo sales_flash_html($flash);
echo '<fieldset class="box"><legend>' . sales_h($title) . '</legend>';
if ($view === 'list') {
    [$lf, $lt] = acc_period_from_request($_GET + ['from' => date('Y-m-01')]);
    $lq = '%' . trim((string) ($_GET['q'] ?? '')) . '%';
    $list = sales_list_rows($conn, 'SELECT voucher_id, voucher_no, voucher_date, ref_no, narration, total_amount, status FROM acc_vouchers WHERE voucher_type=? AND is_inter_branch=? AND voucher_date BETWEEN ? AND ? AND (voucher_no LIKE ? OR ref_no LIKE ? OR narration LIKE ?) ORDER BY voucher_date DESC, voucher_id DESC', 'sisssss', [$type, $ib ? 1 : 0, $lf, $lt, $lq, $lq, $lq]);
    echo '<form method="get" class="list-search"><input type="hidden" name="type" value="' . $type . '"><input type="hidden" name="ib" value="' . ($ib ? 1 : '') . '"><input type="hidden" name="view" value="list"><input class="input" type="date" name="from" value="' . sales_h($lf) . '" style="max-width:160px"><input class="input" type="date" name="to" value="' . sales_h($lt) . '" style="max-width:160px"><input class="input" name="q" value="' . sales_h($_GET['q'] ?? '') . '" placeholder="Voucher no, ref or narration"><button class="btn primary">Search</button><a class="btn" href="' . sales_h($self) . '">New</a></form>';
    echo '<div class="table-scroll" style="max-height:600px"><table><thead><tr><th>SL</th><th>Voucher No</th><th>Date</th><th>Ref No</th><th>Narration</th><th class="num">Amount</th><th>Status</th><th>Select</th><th>RV Print</th></tr></thead><tbody>';
    foreach ($list as $i => $r) {
        echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h($r['voucher_no']) . '</td><td>' . acc_fmt_date($r['voucher_date']) . '</td><td>' . sales_h($r['ref_no']) . '</td><td>' . sales_h($r['narration']) . '</td><td class="num">' . sales_money($r['total_amount']) . '</td><td>' . ucfirst(sales_h($r['status'])) . '</td><td><a class="btn small" href="' . sales_h($self) . '&id=' . (int) $r['voucher_id'] . '">Select</a></td><td><a class="btn small" target="_blank" href="acc_rp_voucher.php?print=' . (int) $r['voucher_id'] . '">Print</a></td></tr>';
    }
    echo $list ? '' : '<tr><td colspan="9" class="empty">No vouchers in this period.</td></tr>';
    echo '</tbody></table></div></fieldset>';
    acc_page_end();
    exit;
}
$vid = (int) ($voucher['voucher_id'] ?? 0);
?>
<form method="post" id="rpform" autocomplete="off"><?= sales_csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="ib" value="<?= $ib ? 1 : '' ?>"><input type="hidden" name="voucher_id" value="<?= $vid ?>"><input type="hidden" name="entries_json" id="entries_json">
<div class="grid g3" style="margin-top:0">
<div class="field"><label for="ref_no">Ref No</label><div class="inline"><input id="ref_no" name="ref_no" maxlength="80" value="<?= sales_h($h['ref_no'] ?? '') ?>"><button type="button" class="btn small" onclick="location.href='<?= sales_h($self) ?>&search='+encodeURIComponent(document.getElementById('ref_no').value)">Search</button></div></div>
<div class="field"><label for="voucher_date">Date</label><input id="voucher_date" type="date" name="voucher_date" value="<?= sales_h($h['voucher_date'] ?? date('Y-m-d')) ?>" required></div>
<div class="field"><label for="search_no">Voucher No</label><div class="inline"><input id="search_no" value="<?= sales_h($voucher['voucher_no'] ?? acc_peek_voucher_no($conn, $type, $ib)) ?>"><button type="button" class="btn small" onclick="location.href='<?= sales_h($self) ?>&search='+encodeURIComponent(document.getElementById('search_no').value)">Search</button></div></div>
</div>
<div class="grid g4">
<div class="field"><label for="under_account">Under</label><select id="under_account" name="under_account"><?= acc_account_options($cashBank, $under, null) ?></select></div>
<div class="field"><label>Group Name</label><input id="under_group" readonly></div>
<div class="field"><label>Particulars</label><input id="under_name" readonly></div>
<?php if ($ib): ?><div class="field"><label for="to_branch_id">From Branch &rarr; To Branch</label><div class="inline"><input value="<?= sales_h(array_column(acc_branches($conn), 'branch_name', 'branch_id')[$voucher ? (int) $voucher['branch_id'] : $branch] ?? '') ?>" readonly><select id="to_branch_id" name="to_branch_id"><option value="">To branch</option><?php foreach (acc_branches($conn) as $b): ?><option value="<?= (int) $b['branch_id'] ?>" <?= (int) ($h['to_branch_id'] ?? 0) === (int) $b['branch_id'] ? 'selected' : '' ?>><?= sales_h($b['branch_name']) ?></option><?php endforeach; ?></select></div></div><?php endif; ?>
</div>
<div class="grid g4">
<div class="field"><label for="e_group">Group Name</label><input id="e_group" list="group-list" placeholder="Filter by group"><datalist id="group-list"><?php foreach (array_unique(array_column($parts, 'sub1')) as $g): ?><option value="<?= sales_h($g) ?>"><?php endforeach; ?></datalist></div>
<div class="field" style="grid-column:span 2"><label for="e_part">Particulars</label><div class="relative"><input id="e_part" placeholder="Party or account"><div class="suggestions" id="part-suggestions" hidden></div></div></div>
<div class="field"><label>&nbsp;</label><span><label><input class="check" type="checkbox" id="cheque_adjust"> Cheque Adjust</label> &nbsp; <label><input class="check" type="checkbox" id="invoice_list"> Invoice List</label></span></div>
</div>
<div class="grid g4">
<div class="field"><label for="sales_person">Sales Person</label><input id="sales_person" name="sales_person" maxlength="140" placeholder="Sales Person" value="<?= sales_h($h['sales_person'] ?? '') ?>"></div>
<div class="field" style="grid-column:span 2"><label for="narration">Narration</label><input id="narration" name="narration" maxlength="500" value="<?= sales_h($h['narration'] ?? '') ?>"></div>
<div class="field"><label for="e_amount">Amount</label><input id="e_amount" type="number" min="0.01" step="0.01"></div>
</div>
<div class="grid g4" id="cheque-row" hidden>
<div class="field"><label for="e_mode">Mode</label><select id="e_mode"><?= acc_options(array_combine(['Cheque', 'Bank Transfer', 'Mobile Banking', 'Cash'], ['Cheque', 'Bank Transfer', 'Mobile Banking', 'Cash']), 'Cheque') ?></select></div>
<div class="field"><label for="e_cheque_no">Cheque No</label><input id="e_cheque_no" maxlength="40"></div>
<div class="field"><label for="e_cheque_date">Cheque Date</label><input id="e_cheque_date" type="date"></div>
</div>
<div class="grid g4" id="invoice-row" hidden><div class="field" style="grid-column:span 2"><label for="e_invoice">Invoice No</label><select id="e_invoice"><option value="">On account (no invoice)</option></select></div></div>
<div class="toolbar" style="margin-top:10px;align-items:center">
<a class="btn" href="<?= sales_h($self) ?>">New</a><a class="btn" href="<?= sales_h($self) ?>&view=list">List View</a>
<button type="button" class="btn" id="add-row" <?= $readOnly ? 'disabled' : '' ?>>Add</button>
<a class="btn" title="Previous" <?= $prev ? 'href="' . sales_h($self) . '&id=' . (int) $prev['voucher_id'] . '"' : 'aria-disabled="true" style="opacity:.4"' ?>>&#9664;</a><a class="btn" title="Next" <?= $next ? 'href="' . sales_h($self) . '&id=' . (int) $next['voucher_id'] . '"' : 'aria-disabled="true" style="opacity:.4"' ?>>&#9654;</a>
<button class="btn primary" name="action" value="save" <?= $readOnly ? 'disabled' : '' ?>><?= $voucher ? 'Edit' : 'Save' ?></button>
<button type="button" class="btn" id="balance-btn">Balance</button>
<?php if ($vid): ?>
<a class="btn" target="_blank" href="acc_rp_voucher.php?print=<?= $vid ?>">RV Preview</a><a class="btn" target="_blank" href="acc_rp_voucher.php?print=<?= $vid ?>&auto=1">RV Print</a>
<a class="btn" target="_blank" href="acc_voucher.php?print=<?= $vid ?>">CV Preview</a><a class="btn" target="_blank" href="acc_voucher.php?print=<?= $vid ?>&auto=1">CV Print</a>
<a class="btn" target="_blank" href="acc_rp_voucher.php?print=<?= $vid ?>&kind=adjust">Adjust Preview</a><a class="btn" target="_blank" href="acc_rp_voucher.php?print=<?= $vid ?>&kind=adjust&auto=1">Adjust Print</a>
<a class="btn" href="acc_voucher.php?type=<?= $type ?>&id=<?= $vid ?>#attach">Document Attach</a>
<?php endif; ?>
<button class="btn" name="action" value="post" <?= $readOnly ? 'disabled' : '' ?> onclick="return confirm('Save and post this voucher?')">Posting</button>
<a class="btn" href="acc_register.php">Close</a><span id="balance-out" class="muted"></span>
</div>
<?php if ($voucher): ?><p class="footnote"><?= sales_h($voucher['voucher_no']) ?> · Status: <b><?= ucfirst(sales_h($voucher['status'])) ?></b><?= $readOnly ? ' · Posted vouchers are read-only. Unpost from Voucher Posting to change them.' : '' ?></p><?php endif; ?>
<div class="table-scroll"><table><thead><tr><th>SL</th><th><?= $type === 'credit' ? 'Debit' : 'Credit' ?></th><th>Mode</th><th>Cheque No</th><th>Cheque Date</th><th>Particulars Name</th><th>Subledger 1</th><th>Subledger 2</th><th>Narration</th><th>Invoice No</th><th class="num">Amount</th><th>Select</th><th>Delete</th></tr></thead><tbody id="rows"></tbody></table></div>
<div style="display:flex;justify-content:flex-end;margin-top:8px"><table style="width:auto"><tr><th>Total Amount</th><td class="num" id="total">0.00</td></tr></table></div>
</form>
</fieldset>
<?php
$settings = ['self' => $self, 'parts' => $parts, 'entries' => $entries, 'readOnly' => $readOnly, 'cashBank' => array_map(fn($a) => ['id' => (int) $a['account_id'], 'name' => $a['account_name'], 'group' => $a['group_name']], $cashBank)];
echo '</main></div><script src="../sales/sales.js"></script><script>window.RP=' . sales_js($settings) . ';</script><script src="acc_rp_voucher.js"></script></body></html>';
?>
<?php include "../../addon/conf/connection_close.php";  ?>
