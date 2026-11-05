<?php
/**
 * Voucher Entry: Debit (payment), Credit (receipt), Journal and Contra vouchers.
 * Screen follows the Journal Voucher layout: Under (Debit/Credit) + Group Name + Particulars + Amount add rows to the grid.
 */
require_once __DIR__ . '/acc_common.php';

$type = (string) ($_REQUEST['type'] ?? 'journal');
if (!isset(ACC_VOUCHER_TYPES[$type])) {
    $type = 'journal';
}
$meta = ACC_VOUCHER_TYPES[$type];
acc_require_ready($conn, $meta['label'], 'v_' . $type);
// Inter Branch Journal Voucher (?ib=1): every line names its branch. An opened voucher keeps its own setting.
$ib = $type === 'journal' && !empty($_REQUEST['ib']);
$openId = (int) ($_POST['voucher_id'] ?? ($_GET['id'] ?? 0));
if ($openId > 0 && ($row = sales_row($conn, 'SELECT is_inter_branch FROM acc_vouchers WHERE voucher_id=?', 'i', [$openId]))) {
    $ib = (bool) $row['is_inter_branch'];
}
if ($ib) {
    $meta['label'] = 'Inter Branch Journal Voucher';
}
$branch = acc_current_branch($conn);
$self = 'acc_voucher.php?type=' . $type . ($ib ? '&ib=1' : '');
$userId = (int) ($GLOBALS['user_id'] ?? 0);

/* ---------- Small JSON and file endpoints ---------- */
if (isset($_GET['balance'])) {
    [$kind, $id] = array_pad(explode(':', (string) $_GET['balance'], 2), 2, '0');
    $m = $kind === 's'
        ? (acc_sub_ledger_movements($conn, '1900-01-01', '2100-12-31', 'voucher', (int) $id)[(int) $id] ?? null)
        : (acc_account_movements($conn, '1900-01-01', '2100-12-31', 'voucher', (int) $id)[(int) $id] ?? null);
    sales_json(['balance' => $m ? acc_drcr($m['open'] + $m['dr'] - $m['cr']) : '0.00']);
}
if (isset($_GET['download'])) {
    $file = sales_row($conn, 'SELECT * FROM acc_voucher_attachments WHERE attachment_id=?', 'i', [(int) $_GET['download']]);
    $path = $file ? ACC_UPLOAD_DIR . '/' . basename($file['stored_name']) : '';
    if (!$file || !is_file($path)) {
        http_response_code(404);
        exit('Attachment not found.');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    $inline = str_starts_with($file['mime_type'], 'image/') || $file['mime_type'] === 'application/pdf';
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $file['original_name']) . '"');
    readfile($path);
    exit;
}

/* ---------- Print ---------- */
if (isset($_GET['print'])) {
    $v = sales_row($conn, 'SELECT v.*, c.category_name FROM acc_vouchers v LEFT JOIN acc_money_receipt_categories c ON c.category_id=v.receipt_category_id WHERE v.voucher_id=?', 'i', [(int) $_GET['print']]);
    if (!$v) {
        http_response_code(404);
        exit('Voucher not found.');
    }
    $lines = sales_rows($conn, 'SELECT l.*, a.account_code, a.account_name, s.sub_ledger_name, s.sub_ledger_code FROM acc_voucher_lines l JOIN acc_accounts a ON a.account_id=l.account_id LEFT JOIN acc_sub_ledgers s ON s.sub_ledger_id=l.sub_ledger_id WHERE l.voucher_id=? ORDER BY l.line_no', 'i', [(int) $v['voucher_id']]);
    $title = $v['voucher_type'] === 'credit' ? 'Money Receipt' . ($v['category_name'] ? ' - ' . $v['category_name'] : '') : ACC_VOUCHER_TYPES[$v['voucher_type']]['label'];
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><title><?= sales_h($v['voucher_no']) ?></title>
    <style>body{font:13px Arial,sans-serif;color:#111;margin:24px}.head{display:flex;justify-content:space-between}.co b{font-size:20px}h2{text-align:center;margin:12px 0 4px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;margin:14px 0}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:6px;text-align:left}th{background:#eee}.num{text-align:right}.sign{display:flex;justify-content:space-between;margin-top:70px}.sign div{border-top:1px solid #000;width:22%;text-align:center;padding-top:4px}.draft{color:#a33;font-weight:bold;text-align:center}@media print{button{display:none}body{margin:10mm}}</style></head>
    <body<?= !empty($_GET['auto']) ? ' onload="window.print()"' : '' ?>><button onclick="window.print()">Print</button>
    <div class="head"><div class="co"><b><?= sales_h(acc_setting($conn, 'company_name')) ?></b><br><?= sales_h(acc_setting($conn, 'company_address')) ?></div><div>Phone : <?= sales_h(acc_setting($conn, 'company_phone')) ?><br>Email : <?= sales_h(acc_setting($conn, 'company_email')) ?></div></div>
    <h2><?= sales_h($title) ?></h2><?php if ($v['status'] !== 'posted'): ?><div class="draft"><?= strtoupper(sales_h($v['status'])) ?> - NOT POSTED</div><?php endif; ?>
    <div class="meta"><div><b>Voucher No:</b> <?= sales_h($v['voucher_no']) ?></div><div><b>Date:</b> <?= acc_fmt_date($v['voucher_date']) ?></div><div><b>Ref No:</b> <?= sales_h($v['ref_no']) ?></div><div><b>Mode:</b> <?= sales_h($v['transaction_mode']) ?><?= $v['cheque_no'] !== '' ? ', Cheque ' . sales_h($v['cheque_no']) . ' dated ' . acc_fmt_date($v['cheque_date']) : '' ?></div><div><b>Sales Person:</b> <?= sales_h($v['sales_person']) ?></div><div><b>Narration:</b> <?= sales_h($v['narration']) ?></div></div>
    <table><thead><tr><th>SL</th><th>Particulars</th><th>Narration</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
    <?php foreach ($lines as $i => $l): ?><tr><td><?= $i + 1 ?></td><td><?= sales_h($l['sub_ledger_name'] ? $l['sub_ledger_name'] . ' (' . $l['account_name'] . ')' : $l['account_code'] . ' - ' . $l['account_name']) ?></td><td><?= sales_h($l['line_narration']) ?></td><td class="num"><?= (float) $l['debit'] > 0 ? sales_money($l['debit']) : '' ?></td><td class="num"><?= (float) $l['credit'] > 0 ? sales_money($l['credit']) : '' ?></td></tr><?php endforeach; ?>
    <tr><th colspan="3" class="num">Total</th><th class="num"><?= sales_money($v['total_amount']) ?></th><th class="num"><?= sales_money($v['total_amount']) ?></th></tr></tbody></table>
    <p><b>In words:</b> <?= sales_h(acc_words((float) $v['total_amount'])) ?></p>
    <div class="sign"><div>Prepared By</div><div>Checked By</div><div>Approved By</div><div><?= $v['voucher_type'] === 'credit' ? 'Received From' : 'Received By' ?></div></div>
    </body></html><?php
    exit;
}

/* ---------- Save / post / attach ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voucherId = (int) ($_POST['voucher_id'] ?? 0);
    $back = $self . ($voucherId ? '&id=' . $voucherId : '');
    sales_require_csrf($back);
    $action = (string) ($_POST['action'] ?? 'save');
    try {
        if ($action === 'attach' || $action === 'detach') {
            if (!sales_row($conn, 'SELECT 1 FROM acc_vouchers WHERE voucher_id=?', 'i', [$voucherId])) {
                throw new RuntimeException('Save the voucher before attaching documents.');
            }
            if ($action === 'detach') {
                $file = sales_row($conn, 'SELECT * FROM acc_voucher_attachments WHERE attachment_id=? AND voucher_id=?', 'ii', [(int) ($_POST['attachment_id'] ?? 0), $voucherId]);
                if ($file) {
                    sales_exec($conn, 'DELETE FROM acc_voucher_attachments WHERE attachment_id=?', 'i', [(int) $file['attachment_id']])->close();
                    @unlink(ACC_UPLOAD_DIR . '/' . basename($file['stored_name']));
                }
                sales_flash_redirect($back, 'success', 'Attachment removed.');
            }
            $up = $_FILES['document'] ?? null;
            if (!$up || $up['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($up['tmp_name']) || $up['size'] > 5242880) {
                throw new RuntimeException('Choose a file of 5 MB or less.');
            }
            $types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $ext = strtolower(pathinfo((string) $up['name'], PATHINFO_EXTENSION));
            if (!isset($types[$ext])) {
                throw new RuntimeException('Allowed files: ' . implode(', ', array_keys($types)) . '.');
            }
            if (!is_dir(ACC_UPLOAD_DIR) && !mkdir(ACC_UPLOAD_DIR, 0750, true)) {
                throw new RuntimeException('The uploads folder could not be created.');
            }
            $stored = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!move_uploaded_file($up['tmp_name'], ACC_UPLOAD_DIR . '/' . $stored)) {
                throw new RuntimeException('Could not save the file. Check that billing/accounts_info/uploads is writable.');
            }
            sales_insert($conn, 'INSERT INTO acc_voucher_attachments (voucher_id, original_name, stored_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)', 'isssii', [$voucherId, sales_cut(basename((string) $up['name']), 190), $stored, $types[$ext], (int) $up['size'], $userId]);
            sales_flash_redirect($back, 'success', 'Document attached.');
        }

        $date = sales_valid_date($_POST['voucher_date'] ?? '');
        if ($date === null) {
            throw new RuntimeException('Enter a valid date.');
        }
        $mode = in_array(($_POST['transaction_mode'] ?? ''), ACC_MODES, true) ? (string) $_POST['transaction_mode'] : ($type === 'journal' ? 'Adjustment' : 'Cash');
        $chequeNo = sales_post_string('cheque_no', 40);
        $chequeDate = sales_valid_date($_POST['cheque_date'] ?? '', true);
        if ($chequeDate === null) {
            throw new RuntimeException('Enter a valid cheque date or leave it blank.');
        }
        if ($mode === 'Cheque' && $chequeNo === '') {
            throw new RuntimeException('Enter the cheque number.');
        }
        $category = (int) ($_POST['receipt_category_id'] ?? 0);
        $category = ($type === 'credit' && $category > 0 && sales_row($conn, 'SELECT 1 FROM acc_money_receipt_categories WHERE category_id=? AND is_active=1', 'i', [$category])) ? $category : null;
        $lines = json_decode((string) ($_POST['lines_json'] ?? '[]'), true);
        if (!is_array($lines) || count($lines) > 300) {
            throw new RuntimeException('The voucher lines could not be read.');
        }
        [$clean, $total] = acc_validate_lines($conn, $type, $lines);
        $branchIds = array_map('intval', array_column(acc_branches($conn), 'branch_id'));
        foreach ($clean as &$c) {
            if (!$ib) {
                $c['branch_id'] = $branch;
            } elseif (!in_array((int) $c['branch_id'], $branchIds, true)) {
                throw new RuntimeException('Pick the branch for every line of an inter branch voucher.');
            }
        }
        unset($c);
        if ($ib && count(array_unique(array_column($clean, 'branch_id'))) < 2) {
            throw new RuntimeException('An inter branch voucher needs lines in at least two branches.');
        }
        $post = $action === 'post' || acc_setting($conn, 'require_posting', '1') === '0';
        $header = [$date, sales_post_string('ref_no', 80), sales_post_string('narration', 500), sales_post_string('sales_person', 140), $mode, $chequeNo, $chequeDate === '' ? null : $chequeDate, $category, $total, $branch, $ib ? 1 : 0];

        $conn->begin_transaction();
        acc_assert_unlocked($conn, $date, $branch);
        if ($voucherId > 0) {
            $old = sales_row($conn, 'SELECT voucher_no, voucher_type, status, voucher_date, branch_id FROM acc_vouchers WHERE voucher_id=? FOR UPDATE', 'i', [$voucherId]);
            if (!$old || $old['voucher_type'] !== $type) {
                throw new RuntimeException('Voucher not found.');
            }
            if ($old['status'] !== 'draft') {
                throw new RuntimeException('Voucher ' . $old['voucher_no'] . ' is ' . $old['status'] . '. Unpost it from Voucher Posting before editing.');
            }
            acc_assert_unlocked($conn, $old['voucher_date'], (int) $old['branch_id']);
            sales_exec($conn, 'UPDATE acc_vouchers SET voucher_date=?, ref_no=?, narration=?, sales_person=?, transaction_mode=?, cheque_no=?, cheque_date=?, receipt_category_id=?, total_amount=?, branch_id=?, is_inter_branch=? WHERE voucher_id=?', 'sssssssidiii', array_merge($header, [$voucherId]))->close();
            $voucherNo = $old['voucher_no'];
        } else {
            $voucherNo = acc_next_voucher_no($conn, $type, $date, $ib);
            $voucherId = sales_insert($conn, 'INSERT INTO acc_vouchers (voucher_date, ref_no, narration, sales_person, transaction_mode, cheque_no, cheque_date, receipt_category_id, total_amount, branch_id, is_inter_branch, voucher_no, voucher_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'sssssssidiissi', array_merge($header, [$voucherNo, $type, $userId]));
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
        $_SESSION['acc_voucher_repost'] = $_POST;
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

/* ---------- Page data ---------- */
$flash = sales_take_flash();
$repost = ($flash['kind'] ?? '') === 'error' ? ($_SESSION['acc_voucher_repost'] ?? null) : null;
unset($_SESSION['acc_voucher_repost']);
$view = (string) ($_GET['view'] ?? 'form');
if (isset($_GET['search'])) {
    $found = sales_row($conn, 'SELECT voucher_id, voucher_type FROM acc_vouchers WHERE voucher_no=? OR ref_no=? ORDER BY voucher_id DESC LIMIT 1', 'ss', [trim((string) $_GET['search']), trim((string) $_GET['search'])]);
    if ($found) {
        header('Location: acc_voucher.php?type=' . $found['voucher_type'] . '&id=' . (int) $found['voucher_id'], true, 303);
        exit;
    }
    $flash = ['kind' => 'error', 'message' => 'No voucher matches "' . trim((string) $_GET['search']) . '".'];
}
$voucher = null;
$lines = [];
$attachments = [];
$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $voucher = sales_row($conn, 'SELECT * FROM acc_vouchers WHERE voucher_id=? AND voucher_type=?', 'is', [$id, $type]);
    if ($voucher) {
        $lines = sales_rows($conn, 'SELECT account_id, COALESCE(sub_ledger_id, 0) AS sub_ledger_id, debit, credit, line_narration, COALESCE(branch_id, 0) AS branch_id, transaction_mode, cheque_no, cheque_date, invoice_no FROM acc_voucher_lines WHERE voucher_id=? ORDER BY line_no', 'i', [$id]);
        $attachments = sales_rows($conn, 'SELECT * FROM acc_voucher_attachments WHERE voucher_id=? ORDER BY attachment_id', 'i', [$id]);
        $prev = sales_row($conn, 'SELECT voucher_id FROM acc_vouchers WHERE voucher_type=? AND is_inter_branch=? AND voucher_id<? ORDER BY voucher_id DESC LIMIT 1', 'sii', [$type, $ib ? 1 : 0, $id]);
        $next = sales_row($conn, 'SELECT voucher_id FROM acc_vouchers WHERE voucher_type=? AND is_inter_branch=? AND voucher_id>? ORDER BY voucher_id LIMIT 1', 'sii', [$type, $ib ? 1 : 0, $id]);
    }
} else {
    $prev = sales_row($conn, 'SELECT voucher_id FROM acc_vouchers WHERE voucher_type=? ORDER BY voucher_id DESC LIMIT 1', 's', [$type]);
    $next = null;
}
$h = $repost ?: ($voucher ?: []);
if ($repost) {
    $lines = json_decode((string) ($repost['lines_json'] ?? '[]'), true) ?: [];
}
$readOnly = $voucher && $voucher['status'] !== 'draft';

// Particulars: plain accounts, plus each party under control accounts.
$particulars = [];
foreach (acc_accounts($conn) as $a) {
    if ((int) $a['has_sub_ledger']) {
        continue;
    }
    $particulars[] = ['key' => 'a:' . $a['account_id'], 'account_id' => (int) $a['account_id'], 'sub_ledger_id' => 0, 'name' => $a['account_name'], 'code' => $a['account_code'], 'sub1' => $a['group_name'], 'sub2' => '', 'cash' => in_array($a['account_type'], ['cash', 'bank'], true)];
}
foreach (sales_rows($conn, 'SELECT s.sub_ledger_id, s.account_id, s.sub_ledger_code, s.sub_ledger_name, g.group_name, a.account_name FROM acc_sub_ledgers s JOIN acc_sub_ledger_groups g ON g.slg_id=s.slg_id JOIN acc_accounts a ON a.account_id=s.account_id WHERE s.is_active=1 AND a.is_active=1 ORDER BY s.sub_ledger_name') as $s) {
    $particulars[] = ['key' => 's:' . $s['sub_ledger_id'], 'account_id' => (int) $s['account_id'], 'sub_ledger_id' => (int) $s['sub_ledger_id'], 'name' => $s['sub_ledger_name'], 'code' => $s['sub_ledger_code'], 'sub1' => $s['group_name'], 'sub2' => $s['account_name'], 'cash' => false];
}
$particulars = perm_filter_ledgers($particulars);
$categories = sales_rows($conn, 'SELECT category_id, category_name, account_id FROM acc_money_receipt_categories WHERE is_active=1 ORDER BY category_name');
$list = [];
if ($view === 'list') {
    [$lf, $lt] = acc_period_from_request($_GET + ['from' => date('Y-m-01')]);
    $lq = '%' . trim((string) ($_GET['q'] ?? '')) . '%';
    $list = sales_list_rows($conn, 'SELECT voucher_id, voucher_no, voucher_date, ref_no, narration, transaction_mode, total_amount, status FROM acc_vouchers WHERE voucher_type=? AND voucher_date BETWEEN ? AND ? AND (voucher_no LIKE ? OR ref_no LIKE ? OR narration LIKE ?) ORDER BY voucher_date DESC, voucher_id DESC', 'ssssss', [$type, $lf, $lt, $lq, $lq, $lq]);
}
$hints = [
    'debit' => 'Payment: debit the expense or party, credit Cash or Bank.',
    'credit' => 'Receipt: debit Cash or Bank, credit the income or party.',
    'journal' => 'Adjustment between any accounts. Debits must equal credits.',
    'contra' => 'Transfer between cash and bank accounts only.',
];

acc_page_start($meta['label'], $ib ? 'ibj' : 'v_' . $type);
echo sales_flash_html($flash);
?>
<fieldset class="box"><legend><?= sales_h($meta['label']) ?></legend>
<?php if ($view === 'list'): ?>
<form method="get" class="list-search"><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="view" value="list">
<input class="input" type="date" name="from" value="<?= sales_h($lf) ?>" style="max-width:160px"><input class="input" type="date" name="to" value="<?= sales_h($lt) ?>" style="max-width:160px"><input class="input" name="q" value="<?= sales_h($_GET['q'] ?? '') ?>" placeholder="Voucher no, ref or narration"><button class="btn primary">Search</button><a class="btn" href="<?= sales_h($self) ?>">New</a></form>
<div class="table-scroll" style="max-height:600px"><table><thead><tr><th>SL</th><th>Voucher No</th><th>Date</th><th>Ref No</th><th>Narration</th><th>Mode</th><th class="num">Amount</th><th>Status</th><th>Select</th><th>Print</th></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="10" class="empty">No vouchers in this period.</td></tr><?php endif; ?>
<?php foreach ($list as $i => $r): ?><tr><td><?= $i + 1 ?></td><td><?= sales_h($r['voucher_no']) ?></td><td><?= acc_fmt_date($r['voucher_date']) ?></td><td><?= sales_h($r['ref_no']) ?></td><td><?= sales_h($r['narration']) ?></td><td><?= sales_h($r['transaction_mode']) ?></td><td class="num"><?= sales_money($r['total_amount']) ?></td><td><span class="badge <?= ['draft' => 'open', 'posted' => 'converted', 'cancelled' => 'cancelled'][$r['status']] ?>"><?= ucfirst($r['status']) ?></span></td><td><a class="btn small" href="<?= sales_h($self) ?>&id=<?= (int) $r['voucher_id'] ?>">Select</a></td><td><a class="btn small" target="_blank" href="acc_voucher.php?print=<?= (int) $r['voucher_id'] ?>">Print</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php else: ?>
<form method="post" id="vform" autocomplete="off"><?= sales_csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="voucher_id" value="<?= (int) ($voucher['voucher_id'] ?? 0) ?>"><input type="hidden" name="lines_json" id="lines_json">
<div class="grid g3" style="margin-top:0">
<div class="field"><label for="ref_no">Ref No</label><input id="ref_no" name="ref_no" maxlength="80" value="<?= sales_h($h['ref_no'] ?? '') ?>"></div>
<div class="field"><label for="voucher_date">Date</label><input id="voucher_date" type="date" name="voucher_date" value="<?= sales_h($h['voucher_date'] ?? date('Y-m-d')) ?>" required></div>
<div class="field"><label for="search_no">Voucher No</label><div class="inline"><input id="search_no" value="<?= sales_h($voucher['voucher_no'] ?? acc_peek_voucher_no($conn, $type, $ib)) ?>"><button type="button" class="btn small" onclick="location.href='acc_voucher.php?type=<?= $type ?>&search='+encodeURIComponent(document.getElementById('search_no').value)">Search</button></div></div>
</div>
<div class="grid g4">
<div class="field"><label for="e_under">Under</label><select id="e_under"><option value="debit">Debit</option><option value="credit">Credit</option></select><?php if ($ib): ?><label for="e_branch">Branch</label><select id="e_branch"><?php foreach (acc_branches($conn) as $b): ?><option value="<?= (int) $b['branch_id'] ?>"><?= sales_h($b['branch_name']) ?></option><?php endforeach; ?></select><?php endif; ?></div>
<div class="field"><label for="e_group">Group Name</label><input id="e_group" list="group-list" placeholder="Filter by group"><datalist id="group-list"><?php foreach (array_unique(array_column($particulars, 'sub1')) as $g): ?><option value="<?= sales_h($g) ?>"><?php endforeach; ?></datalist></div>
<div class="field" style="grid-column:span 2"><label for="e_part">Particulars</label><div class="relative"><input id="e_part" placeholder="Account or party name / code"><div class="suggestions" id="part-suggestions" hidden></div></div></div>
</div>
<div class="grid g4">
<div class="field"><label for="sales_person">Sales Person</label><input id="sales_person" name="sales_person" maxlength="140" placeholder="Sales Person" value="<?= sales_h($h['sales_person'] ?? '') ?>"></div>
<div class="field" style="grid-column:span 2"><label for="narration">Narration</label><input id="narration" name="narration" maxlength="500" value="<?= sales_h($h['narration'] ?? '') ?>"></div>
<div class="field"><label for="e_amount">Amount</label><input id="e_amount" type="number" min="0.01" step="0.01"></div>
</div>
<div class="grid g4">
<div class="field"><label for="transaction_mode">Transaction Mode</label><select id="transaction_mode" name="transaction_mode"><?= acc_options(array_combine(ACC_MODES, ACC_MODES), $h['transaction_mode'] ?? ($type === 'journal' ? 'Adjustment' : 'Cash')) ?></select></div>
<div class="field"><label for="cheque_no">Cheque No</label><input id="cheque_no" name="cheque_no" maxlength="40" value="<?= sales_h($h['cheque_no'] ?? '') ?>"></div>
<div class="field"><label for="cheque_date">Cheque Date</label><input id="cheque_date" type="date" name="cheque_date" value="<?= sales_h($h['cheque_date'] ?? '') ?>"></div>
<?php if ($type === 'credit'): ?><div class="field"><label for="receipt_category_id">Money Receipt Category</label><select id="receipt_category_id" name="receipt_category_id"><option value="">None</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['category_id'] ?>" data-account="<?= (int) $c['account_id'] ?>" <?= (int) ($h['receipt_category_id'] ?? 0) === (int) $c['category_id'] ? 'selected' : '' ?>><?= sales_h($c['category_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
</div>
<div class="toolbar" style="margin-top:10px;align-items:center">
<a class="btn" href="<?= sales_h($self) ?>">New</a>
<a class="btn" href="<?= sales_h($self) ?>&view=list">List View</a>
<button type="button" class="btn" id="add-line" <?= $readOnly ? 'disabled' : '' ?>>Add</button>
<a class="btn" title="Previous voucher" <?= !empty($prev) ? 'href="' . sales_h($self) . '&id=' . (int) $prev['voucher_id'] . '"' : 'aria-disabled="true" style="opacity:.4"' ?>>&#9664;</a>
<a class="btn" title="Next voucher" <?= !empty($next) ? 'href="' . sales_h($self) . '&id=' . (int) $next['voucher_id'] . '"' : 'aria-disabled="true" style="opacity:.4"' ?>>&#9654;</a>
<button class="btn primary" name="action" value="save" <?= $readOnly ? 'disabled' : '' ?>><?= $voucher ? 'Edit' : 'Save' ?></button>
<button type="button" class="btn" id="balance-btn">Balance</button>
<?php if ($voucher): ?><a class="btn" target="_blank" href="acc_voucher.php?print=<?= (int) $voucher['voucher_id'] ?>">Print Preview</a><a class="btn" target="_blank" href="acc_voucher.php?print=<?= (int) $voucher['voucher_id'] ?>&auto=1">Print</a>
<button type="button" class="btn" onclick="document.getElementById('attach').scrollIntoView({behavior:'smooth'})">Document Attach</button><?php endif; ?>
<button class="btn" name="action" value="post" <?= $readOnly ? 'disabled' : '' ?> onclick="return confirm('Save and post this voucher? Posted vouchers count in the reports.')">Posting</button>
<a class="btn" href="acc_register.php">Close</a>
<b>Total Select Amount: <span id="sel-total">0</span></b>
<span id="balance-out" class="muted"></span>
</div>
<p class="footnote"><?= sales_h($hints[$type]) ?><?php if ($voucher): ?> Status: <b><?= ucfirst(sales_h($voucher['status'])) ?></b>.<?php endif; ?><?= $readOnly ? ' Posted vouchers are read-only. Unpost from Voucher Posting to change them.' : '' ?></p>
<div class="table-scroll"><table><thead><tr><th><input class="check" type="checkbox" id="sel-all"></th><th>SL</th><th>Under</th><th>Particulars Name</th><th>Subledger1</th><th>Subledger2</th><th>Narration</th><th class="num">DR Amount</th><th class="num">CR Amount</th><th>Select</th><th>Delete</th></tr></thead><tbody id="lines-body"></tbody></table></div>
<div style="display:flex;justify-content:flex-end;margin-top:8px"><table style="width:auto"><tr><th>Total Dr. Amount</th><td class="num" id="t_dr">0.00</td></tr><tr><th>Total Cr. Amount</th><td class="num" id="t_cr">0.00</td></tr><tr><th>Difference</th><td class="num" id="t_diff">0.00</td></tr></table></div>
</form>
<?php if ($voucher): ?>
<section class="section" id="attach"><div class="section-title">Documents</div>
<form method="post" enctype="multipart/form-data" class="list-search"><?= sales_csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="action" value="attach"><input type="hidden" name="voucher_id" value="<?= (int) $voucher['voucher_id'] ?>"><input class="input" type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx"><button class="btn primary">Attach</button></form>
<table><tbody><?php if (!$attachments): ?><tr><td class="empty">No documents.</td></tr><?php endif; ?>
<?php foreach ($attachments as $a): ?><tr><td><a target="_blank" href="acc_voucher.php?download=<?= (int) $a['attachment_id'] ?>"><?= sales_h($a['original_name']) ?></a></td><td class="num"><?= number_format($a['file_size'] / 1024, 1) ?> KB</td><td><form method="post" onsubmit="return confirm('Remove this document?')"><?= sales_csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="action" value="detach"><input type="hidden" name="voucher_id" value="<?= (int) $voucher['voucher_id'] ?>"><input type="hidden" name="attachment_id" value="<?= (int) $a['attachment_id'] ?>"><button class="btn small danger">Remove</button></form></td></tr><?php endforeach; ?></tbody></table></section>
<?php endif; ?>
<?php endif; ?>
</fieldset>
<?php
$initial = [];
$byKey = [];
foreach ($particulars as $p) {
    $byKey[$p['key']] = $p;
}
foreach ($lines as $l) {
    $key = ((int) ($l['sub_ledger_id'] ?? 0) > 0 ? 's:' . (int) $l['sub_ledger_id'] : 'a:' . (int) ($l['account_id'] ?? 0));
    $p = $byKey[$key] ?? ['key' => $key, 'account_id' => (int) ($l['account_id'] ?? 0), 'sub_ledger_id' => (int) ($l['sub_ledger_id'] ?? 0), 'name' => 'Inactive account', 'sub1' => '', 'sub2' => ''];
    $initial[] = $p + ['debit' => (float) ($l['debit'] ?? 0), 'credit' => (float) ($l['credit'] ?? 0), 'line_narration' => (string) ($l['line_narration'] ?? ''), 'branch_id' => (int) ($l['branch_id'] ?? 0),
        'transaction_mode' => (string) ($l['transaction_mode'] ?? ''), 'cheque_no' => (string) ($l['cheque_no'] ?? ''), 'cheque_date' => (string) ($l['cheque_date'] ?? ''), 'invoice_no' => (string) ($l['invoice_no'] ?? '')];
}
$script = $view === 'list' ? '' : 'const ibMode=' . ($ib ? 'true' : 'false') . ',branchNames=' . sales_js(array_column(acc_branches($conn), 'branch_name', 'branch_id')) . ';const parts=' . sales_js($particulars) . ',readOnly=' . ($readOnly ? 'true' : 'false') . ',vtype=' . sales_js($type) . ';const lines=' . sales_js($initial) . ';let picked=null;
const pIn=document.getElementById("e_part"),pBox=document.getElementById("part-suggestions"),gIn=document.getElementById("e_group");
function partsInGroup(){const g=gIn.value.trim().toLowerCase();return g?parts.filter(p=>String(p.sub1).toLowerCase()===g):parts}
/* attachSuggest only calls filter() and slice(), so this view re-reads the Group Name filter on every keystroke. */
const groupView={filter:f=>partsInGroup().filter(f),slice:(a,b)=>partsInGroup().slice(a,b)};
attachSuggest(pIn,pBox,groupView,["name","code","sub1","sub2"],p=>`${p.name} · ${p.code}${p.sub2?" ("+p.sub2+")":" ("+p.sub1+")"}`,p=>{picked=p;pIn.value=p.name;if(!gIn.value)gIn.value=p.sub1;document.getElementById("e_amount").focus()});
pIn.addEventListener("input",()=>{picked=null});
function render(){const b=document.getElementById("lines-body");b.innerHTML="";let dr=0,cr=0;
 if(!lines.length)b.innerHTML="<tr><td colspan=11 class=empty>No lines yet. Pick Under, Particulars and Amount, then Add.</td></tr>";
 lines.forEach((l,i)=>{dr+=l.debit;cr+=l.credit;b.insertAdjacentHTML("beforeend",`<tr><td><input class="check" type="checkbox" data-sel="${i}"></td><td>${i+1}</td><td>${l.debit>0?"Debit":"Credit"}</td><td>${safe(l.name)}</td><td>${safe(l.sub1)}</td><td>${safe(l.sub2)}${ibMode&&l.branch_id?" · "+safe(branchNames[l.branch_id]||""):""}</td><td>${safe(l.line_narration)}</td><td class=num>${money(l.debit)}</td><td class=num>${money(l.credit)}</td><td><button type=button class="btn small" data-pick="${i}" ${readOnly?"disabled":""}>Select</button></td><td><button type=button class="btn small danger" data-del="${i}" ${readOnly?"disabled":""}>Delete</button></td></tr>`)});
 document.getElementById("t_dr").textContent=money(dr);document.getElementById("t_cr").textContent=money(cr);document.getElementById("t_diff").textContent=money(Math.abs(dr-cr));document.getElementById("t_diff").style.color=Math.abs(dr-cr)>0.004?"#a33":"";
 document.getElementById("lines_json").value=JSON.stringify(lines.map(l=>({account_id:l.account_id,sub_ledger_id:l.sub_ledger_id,debit:l.debit,credit:l.credit,line_narration:l.line_narration,branch_id:l.branch_id||0,transaction_mode:l.transaction_mode||"",cheque_no:l.cheque_no||"",cheque_date:l.cheque_date||"",invoice_no:l.invoice_no||""})));selTotal()}
function selTotal(){let t=0;document.querySelectorAll("[data-sel]").forEach(c=>{if(c.checked){const l=lines[+c.dataset.sel];t+=l.debit+l.credit}});document.getElementById("sel-total").textContent=money(t)}
document.getElementById("add-line")?.addEventListener("click",()=>{const amt=Math.round(num("e_amount")*100)/100;if(!picked){alert("Pick the particulars from the list.");pIn.focus();return}if(amt<=0){alert("Enter an amount.");return}
 const under=document.getElementById("e_under").value;lines.push({...picked,branch_id:ibMode?Number(document.getElementById("e_branch").value):0,debit:under==="debit"?amt:0,credit:under==="credit"?amt:0,line_narration:document.getElementById("narration").value.trim()});
 picked=null;pIn.value="";document.getElementById("e_amount").value="";
 let dr=0,cr=0;lines.forEach(l=>{dr+=l.debit;cr+=l.credit});if(Math.abs(dr-cr)>0.004){document.getElementById("e_under").value=dr>cr?"credit":"debit";document.getElementById("e_amount").value=Math.abs(dr-cr).toFixed(2)}
 render();pIn.focus()});
document.getElementById("lines-body").addEventListener("click",e=>{const d=e.target.closest("[data-del]");if(d){lines.splice(+d.dataset.del,1);render();return}
 const s=e.target.closest("[data-pick]");if(s){const l=lines.splice(+s.dataset.pick,1)[0];picked=parts.find(p=>p.key===l.key)||l;pIn.value=l.name;gIn.value=l.sub1||"";document.getElementById("e_under").value=l.debit>0?"debit":"credit";document.getElementById("e_amount").value=(l.debit||l.credit).toFixed(2);render();pIn.focus()}});
document.getElementById("lines-body").addEventListener("change",e=>{if(e.target.matches("[data-sel]"))selTotal()});
document.getElementById("sel-all").addEventListener("change",e=>{document.querySelectorAll("[data-sel]").forEach(c=>c.checked=e.target.checked);selTotal()});
document.getElementById("balance-btn").addEventListener("click",async()=>{const out=document.getElementById("balance-out");if(!picked){out.textContent="Pick the particulars to see the balance.";return}
 try{const r=await fetch("acc_voucher.php?type="+vtype+"&balance="+encodeURIComponent(picked.key),{credentials:"same-origin"});const j=await r.json();out.textContent=picked.name+" balance: "+j.balance}catch(err){out.textContent="Balance could not be loaded."}});
if(vtype==="debit"){document.getElementById("e_under").value="debit"}if(vtype==="credit"){document.getElementById("e_under").value="credit"}
document.getElementById("vform").addEventListener("submit",e=>{render();if(lines.length<2){e.preventDefault();alert("Add at least one debit line and one credit line.")}});
render();';
acc_page_end($script);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
