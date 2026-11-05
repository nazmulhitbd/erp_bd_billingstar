<?php
/** Cheque Book: register the leaf range of a bank account's cheque book; approve it before its leaves can be issued. */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'Cheque Book', 'cheque_book');
$self = 'acc_cheque_book.php';
$banks = acc_accounts($conn, true, "a.account_type='bank'");
$bankIds = array_map('intval', array_column($banks, 'account_id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    $action = (string) ($_POST['action'] ?? 'add');
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    try {
        $conn->begin_transaction();
        if ($action === 'approve') {
            $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
            if (!$ids) {
                throw new RuntimeException('Tick at least one cheque book.');
            }
            $stmt = sales_exec($conn, "UPDATE acc_cheque_books SET approval_status='approved', approved_by=?, approved_at=NOW() WHERE approval_status='pending' AND book_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')', 'i' . str_repeat('i', count($ids)), array_merge([$userId], $ids));
            $n = $stmt->affected_rows;
            $stmt->close();
            $conn->commit();
            sales_flash_redirect($self, 'success', $n . ' cheque book(s) approved.');
        }
        if ($action === 'cancel_leaf') {
            $leaf = sales_row($conn, 'SELECT leaf_id, leaf_no, status, book_id FROM acc_cheque_leaves WHERE leaf_id=? FOR UPDATE', 'i', [(int) ($_POST['leaf_id'] ?? 0)]);
            if (!$leaf || $leaf['status'] !== 'unused') {
                throw new RuntimeException('Only unused leaves can be cancelled.');
            }
            sales_exec($conn, "UPDATE acc_cheque_leaves SET status='cancelled' WHERE leaf_id=?", 'i', [(int) $leaf['leaf_id']])->close();
            $conn->commit();
            sales_flash_redirect($self . '?book=' . (int) $leaf['book_id'], 'success', 'Leaf ' . $leaf['leaf_no'] . ' cancelled.');
        }
        if ($action === 'delete') {
            $book = sales_row($conn, 'SELECT * FROM acc_cheque_books WHERE book_id=? FOR UPDATE', 'i', [(int) ($_POST['book_id'] ?? 0)]);
            if (!$book || $book['approval_status'] !== 'pending' || sales_row($conn, "SELECT 1 FROM acc_cheque_leaves WHERE book_id=? AND status<>'unused' LIMIT 1", 'i', [(int) $book['book_id']])) {
                throw new RuntimeException('Only pending books with no used leaves can be deleted.');
            }
            sales_exec($conn, 'DELETE FROM acc_cheque_books WHERE book_id=?', 'i', [(int) $book['book_id']])->close();
            $conn->commit();
            sales_flash_redirect($self, 'success', 'Cheque book deleted.');
        }
        $account = (int) ($_POST['account_id'] ?? 0);
        if (!in_array($account, $bankIds, true)) {
            throw new RuntimeException('Pick the bank account.');
        }
        $bookNo = sales_post_string('book_no', 40);
        $prefix = sales_post_string('leaf_prefix', 20);
        $fromRaw = trim((string) ($_POST['leaf_from'] ?? ''));
        $toRaw = trim((string) ($_POST['leaf_to'] ?? ''));
        $issue = sales_valid_date($_POST['issue_date'] ?? '');
        if ($bookNo === '' || !ctype_digit($fromRaw) || !ctype_digit($toRaw) || $issue === null) {
            throw new RuntimeException('Enter the book number, a numeric first and last leaf, and the issue date.');
        }
        $from = (int) $fromRaw;
        $to = (int) $toRaw;
        if ($to < $from || $to - $from >= 500) {
            throw new RuntimeException('The last leaf must follow the first, with at most 500 leaves in a book.');
        }
        $digits = strlen($fromRaw);
        $bookId = sales_insert($conn, 'INSERT INTO acc_cheque_books (account_id, book_no, leaf_prefix, leaf_from, leaf_to, leaf_digits, issue_date, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', 'issiiissi', [$account, $bookNo, $prefix, $from, $to, $digits, $issue, sales_post_string('note', 255), $userId]);
        for ($n = $from; $n <= $to; $n++) {
            $leaf = $prefix . str_pad((string) $n, $digits, '0', STR_PAD_LEFT);
            if (sales_row($conn, 'SELECT 1 FROM acc_cheque_leaves WHERE account_id=? AND leaf_no=?', 'is', [$account, $leaf])) {
                throw new RuntimeException('Leaf ' . $leaf . ' already belongs to another cheque book of this account.');
            }
            sales_insert($conn, 'INSERT INTO acc_cheque_leaves (book_id, account_id, leaf_no) VALUES (?, ?, ?)', 'iis', [$bookId, $account, $leaf]);
        }
        $conn->commit();
        sales_flash_redirect($self, 'success', 'Cheque book ' . $bookNo . ' added with ' . ($to - $from + 1) . ' leaves. Approve it before use.');
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        $message = str_contains($e->getMessage(), 'Duplicate entry') ? 'That book number already exists for this bank account.' : $e->getMessage();
        sales_flash_redirect($self, 'error', $message);
    }
}

$flash = sales_take_flash();
$books = sales_rows($conn, "SELECT b.*, a.account_name, SUM(l.status='unused') AS unused, SUM(l.status='used') AS used, SUM(l.status='cancelled') AS cancelled FROM acc_cheque_books b JOIN acc_accounts a ON a.account_id=b.account_id LEFT JOIN acc_cheque_leaves l ON l.book_id=b.book_id GROUP BY b.book_id ORDER BY b.approval_status='pending' DESC, b.book_id DESC");
$bookId = (int) ($_GET['book'] ?? 0);
$leaves = $bookId ? sales_rows($conn, 'SELECT l.*, v.voucher_no FROM acc_cheque_leaves l LEFT JOIN acc_vouchers v ON v.voucher_id=l.voucher_id WHERE l.book_id=? ORDER BY l.leaf_id', 'i', [$bookId]) : [];

acc_page_start('Cheque Book', 'cheque_book');
echo sales_flash_html($flash);
if (!$banks): ?><div class="errorbox">No bank account yet. In General Accounts, set an account's type to Bank.</div><?php acc_page_end(); exit; endif; ?>
<fieldset class="box"><legend>Cheque Book</legend>
<form method="post" class="grid g4" style="margin-top:0" autocomplete="off"><?= sales_csrf_field() ?><input type="hidden" name="action" value="add">
<div class="field"><label for="account_id">Bank Account</label><select id="account_id" name="account_id"><?= acc_account_options($banks, 0, null) ?></select></div>
<div class="field"><label for="book_no">Book No</label><input id="book_no" name="book_no" maxlength="40" required></div>
<div class="field"><label for="leaf_prefix">Leaf Prefix</label><input id="leaf_prefix" name="leaf_prefix" maxlength="20" placeholder="Optional, e.g. SEB"></div>
<div class="field"><label for="issue_date">Issue Date</label><input id="issue_date" type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required></div>
<div class="field"><label for="leaf_from">First Leaf No</label><input id="leaf_from" name="leaf_from" inputmode="numeric" pattern="\d+" required></div>
<div class="field"><label for="leaf_to">Last Leaf No</label><input id="leaf_to" name="leaf_to" inputmode="numeric" pattern="\d+" required></div>
<div class="field"><label for="note">Note</label><input id="note" name="note" maxlength="255"></div>
<div class="field"><label>&nbsp;</label><button class="btn primary">Add Cheque Book</button></div>
</form>
<form method="post" style="margin-top:12px"><?= sales_csrf_field() ?><input type="hidden" name="action" value="approve">
<div class="table-scroll"><table><thead><tr><th></th><th>Bank Account</th><th>Book No</th><th>Leaves</th><th class="num">Unused</th><th class="num">Used</th><th class="num">Cancelled</th><th>Issue Date</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$books): ?><tr><td colspan="10" class="empty">No cheque books yet.</td></tr><?php endif; ?>
<?php foreach ($books as $b): $first = $b['leaf_prefix'] . str_pad((string) $b['leaf_from'], (int) $b['leaf_digits'], '0', STR_PAD_LEFT); $last = $b['leaf_prefix'] . str_pad((string) $b['leaf_to'], (int) $b['leaf_digits'], '0', STR_PAD_LEFT); ?>
<tr class="<?= (int) $b['book_id'] === $bookId ? 'selected-row' : '' ?>"><td><?php if ($b['approval_status'] === 'pending'): ?><input class="check" type="checkbox" name="ids[]" value="<?= (int) $b['book_id'] ?>"><?php endif; ?></td><td><?= sales_h($b['account_name']) ?></td><td><?= sales_h($b['book_no']) ?></td><td><?= sales_h($first . ' - ' . $last) ?></td><td class="num"><?= (int) $b['unused'] ?></td><td class="num"><?= (int) $b['used'] ?></td><td class="num"><?= (int) $b['cancelled'] ?></td><td><?= acc_fmt_date($b['issue_date']) ?></td>
<td><span class="badge <?= $b['approval_status'] === 'approved' ? 'converted' : 'open' ?>"><?= ucfirst($b['approval_status']) ?></span></td><td class="nowrap"><a class="btn small" href="<?= $self ?>?book=<?= (int) $b['book_id'] ?>#leaves">Leaves</a>
<?php if ($b['approval_status'] === 'pending' && !(int) $b['used']): ?><button class="btn small danger" form="del-<?= (int) $b['book_id'] ?>" onclick="return confirm('Delete this cheque book?')">Delete</button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="toolbar" style="margin-top:8px"><button class="btn primary">Approve ticked books</button></div>
</form>
<?php foreach ($books as $b): if ($b['approval_status'] === 'pending'): ?><form method="post" id="del-<?= (int) $b['book_id'] ?>"><?= sales_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="book_id" value="<?= (int) $b['book_id'] ?>"></form><?php endif; endforeach; ?>
<?php if ($bookId): ?>
<div class="section" id="leaves"><div class="section-title">Leaves</div><div class="table-scroll" style="max-height:360px"><table><thead><tr><th>Leaf No</th><th>Status</th><th>Voucher</th><th></th></tr></thead><tbody>
<?php foreach ($leaves as $l): ?><tr><td><?= sales_h($l['leaf_no']) ?></td><td><?= ucfirst(sales_h($l['status'])) ?></td><td><?= sales_h($l['voucher_no'] ?? '') ?></td><td><?php if ($l['status'] === 'unused'): ?><form method="post" onsubmit="return confirm('Cancel leaf <?= sales_h($l['leaf_no']) ?>?')"><?= sales_csrf_field() ?><input type="hidden" name="action" value="cancel_leaf"><input type="hidden" name="leaf_id" value="<?= (int) $l['leaf_id'] ?>"><button class="btn small danger">Cancel Leaf</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
<p class="footnote">Once a bank account has a cheque book, Payment Vouchers by cheque from that account must use an unused leaf of an approved book.</p>
</fieldset>
<?php
acc_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
