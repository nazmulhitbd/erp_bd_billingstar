<?php
/**
 * Transaction Lock: stop new or changed documents dated on or before a date, for one branch or all branches.
 * Checked by every inventory document and by accounts vouchers.
 */
require_once dirname(__DIR__) . '/inventory/inv_common.php';
inv_require_ready($conn, 'Transaction Lock', 'lock');
$self = 'transaction_lock.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    try {
        if (($_POST['action'] ?? '') === 'remove') {
            sales_exec($conn, 'UPDATE inv_transaction_locks SET is_active=0 WHERE lock_id=?', 'i', [(int) ($_POST['lock_id'] ?? 0)])->close();
            sales_flash_redirect($self, 'success', 'Lock removed.');
        }
        $date = sales_valid_date($_POST['lock_date'] ?? '');
        if ($date === null) {
            throw new RuntimeException('Enter the lock date.');
        }
        $all = !empty($_POST['all']);
        $branch = $all ? null : (int) ($_POST['branch_id'] ?? 0);
        if (!$all && !sales_row($conn, 'SELECT 1 FROM branches WHERE branch_id=?', 'i', [$branch])) {
            throw new RuntimeException('Pick a branch or tick All.');
        }
        sales_insert($conn, 'INSERT INTO inv_transaction_locks (branch_id, lock_date, created_by) VALUES (?, ?, ?)', 'isi', [$branch, $date, (int) ($GLOBALS['user_id'] ?? 0)]);
        sales_flash_redirect($self, 'success', 'Transactions on or before ' . inv_fmt_date($date) . ' are now locked for ' . ($all ? 'all branches' : inv_branch_name($conn, $branch)) . '.');
    } catch (Throwable $e) {
        sales_flash_redirect($self, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$showAll = !empty($_GET['all_data']);
$locks = sales_list_rows($conn, 'SELECT * FROM inv_transaction_locks' . ($showAll ? '' : ' WHERE is_active=1') . ' ORDER BY lock_id DESC');
inv_page_start($conn, 'Transaction Lock', 'lock');
echo sales_flash_html($flash);
?>
<fieldset class="box" style="max-width:820px"><legend>Transaction Lock</legend>
<form method="post" class="list-search"><?= sales_csrf_field() ?>
<label style="align-self:center" for="branch_id">Branch Name</label><select class="input" id="branch_id" name="branch_id" style="max-width:240px"><?php foreach (inv_branches($conn) as $b): ?><option value="<?= (int) $b['branch_id'] ?>" <?= (int) $b['branch_id'] === inv_current_branch($conn) ? 'selected' : '' ?>><?= sales_h($b['branch_name']) ?></option><?php endforeach; ?></select>
<label style="align-self:center"><input class="check" type="checkbox" name="all" value="1"> All</label>
<label style="align-self:center" for="lock_date">Date</label><input class="input" type="date" id="lock_date" name="lock_date" required style="max-width:170px">
<button class="btn primary">Add</button><a class="btn" href="<?= $self ?>?all_data=<?= $showAll ? 0 : 1 ?>"><?= $showAll ? 'Active Only' : 'Show All Data' ?></a></form>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Branch</th><th>Locked On or Before</th><th>Added</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (!$locks): ?><tr><td colspan="6" class="empty">No locks.</td></tr><?php endif; ?>
<?php foreach ($locks as $i => $l): ?><tr><td><?= $i + 1 ?></td><td><?= $l['branch_id'] === null ? '<b>All branches</b>' : sales_h(inv_branch_name($conn, $l['branch_id'])) ?></td><td><?= inv_fmt_date($l['lock_date']) ?></td><td><?= sales_h($l['created_at']) ?></td><td><?= (int) $l['is_active'] ? 'Active' : 'Removed' ?></td>
<td><?php if ((int) $l['is_active']): ?><form method="post" onsubmit="return confirm('Remove this lock?')"><?= sales_csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="lock_id" value="<?= (int) $l['lock_id'] ?>"><button class="btn small danger">Remove</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<p class="footnote">Locked dates block saving, editing or cancelling inventory documents and accounts vouchers of that branch.</p>
</fieldset>
<?php
inv_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
