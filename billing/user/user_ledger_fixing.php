<?php
/**
 * User Wise Ledger Fixing: which ledgers a user may work with, by branch. Each row of admin_user_ledgers
 * (billing/user/schema.sql) opens something up; a user with no rows is not limited. perm_ledger_limit() in addon/inc/perm.php
 * applies the rules on the voucher pages and in Accounts Register. View lists what can be added for the ticked kind.
 */
require_once dirname(__DIR__) . '/store_admin/sa_list.php';

const ULF_MODES = ['all' => 'All Ledger', 'trader_group' => 'Trader Group', 'account_group' => 'Accounts Group', 'ledger' => 'Explicit Ledger'];

$self = 'user_ledger_fixing.php';
$me = perm_user();
$ready = sales_tables_ready($conn, ['admin_user_ledgers', 'acc_accounts', 'acc_groups']);
$canAllBranch = perm_branch_ids() === null;
$branches = array_values(array_filter(sales_rows($conn, 'SELECT branch_id, branch_name FROM branches WHERE is_active=1 ORDER BY branch_name'), fn($b) => perm_branch_allowed($b['branch_id'])));
$branchIds = array_map('intval', array_column($branches, 'branch_id'));
$firstBranch = in_array(perm_current_branch(), $branchIds, true) ? perm_current_branch() : ($branchIds[0] ?? 0);

$branchId = (int) ($_REQUEST['branch'] ?? 0);
$branchId = in_array($branchId, $branchIds, true) ? $branchId : $firstBranch;
$access = (int) ($_REQUEST['access'] ?? 0);
$access = in_array($access, $branchIds, true) ? $access : $branchId;
$allBranch = $canAllBranch && isset($_REQUEST['all_branch']);
$users = perm_fixing_users($branchId);
$userId = (int) ($_REQUEST['user'] ?? 0);
$userId = in_array($userId, array_map('intval', array_column($users, 'user_id')), true) ? $userId : 0;
$mode = is_string($_REQUEST['mode'] ?? null) && isset(ULF_MODES[$_REQUEST['mode']]) ? $_REQUEST['mode'] : 'all';
$back = $self . '?' . http_build_query(array_filter(['access' => $access, 'all_branch' => $allBranch ? 1 : null, 'branch' => $branchId, 'user' => $userId, 'mode' => $mode]));

/** What can be picked per kind: scope => id => [name, flag, active]. */
$picks = ['all' => [0 => ['All Ledger', '', 1]], 'trader_group' => [], 'account_group' => [], 'ledger' => []];
if ($ready) {
    if (sales_tables_ready($conn, ['trader_groups', 'trader_categories'])) {
        foreach (sales_rows($conn, 'SELECT g.group_id, g.group_name, g.is_active, c.category_name FROM trader_groups g JOIN trader_categories c ON c.category_id=g.category_id ORDER BY c.category_name, g.group_name') as $g) {
            $picks['trader_group'][(int) $g['group_id']] = [$g['group_name'], $g['category_name'], (int) $g['is_active']];
        }
    }
    foreach (sales_rows($conn, 'SELECT group_id, group_code, group_name, nature, is_active FROM acc_groups ORDER BY group_code, group_name') as $g) {
        $picks['account_group'][(int) $g['group_id']] = [$g['group_name'], ucfirst($g['nature']), (int) $g['is_active']];
    }
    foreach (sales_rows($conn, 'SELECT account_id, account_code, account_name, account_type, is_active FROM acc_accounts ORDER BY account_code') as $a) {
        $picks['ledger'][(int) $a['account_id']] = [$a['account_code'] . ' - ' . $a['account_name'], ucfirst($a['account_type']), (int) $a['is_active']];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($back);
    try {
        if (!$ready) {
            throw new RuntimeException('Import billing/user/schema.sql first.');
        }
        if (!$userId) {
            throw new RuntimeException('Pick a user.');
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'add') {
            if (isset($_POST['all_branch']) && !$canAllBranch) {
                throw new RuntimeException('Only a user with every branch can add an All Branch rule.');
            }
            $refs = isset($_POST['one']) ? [(int) $_POST['one']] : array_map('intval', (array) ($_POST['refs'] ?? []));
            $refs = array_values(array_filter(array_unique($refs), fn($ref) => !empty($picks[$mode][$ref][2])));
            if (!$refs) {
                throw new RuntimeException('Tick at least one row of the list.');
            }
            $added = 0;
            foreach ($refs as $ref) {
                $st = sales_exec($conn, 'INSERT IGNORE INTO admin_user_ledgers (user_id, branch_id, scope, ref_id, created_by) VALUES (?, ?, ?, ?, ?)', 'iisii', [$userId, $allBranch ? 0 : $access, $mode, $ref, (int) $me['user_id']]);
                $added += $st->affected_rows;
                $st->close();
            }
            sales_flash_redirect($back, $added ? 'success' : 'error', $added ? $added . ' added.' : 'This user already has that.');
        }
        if ($action === 'delete_all') {
            sales_exec($conn, 'DELETE FROM admin_user_ledgers WHERE user_id=?', 'i', [$userId])->close();
            sales_flash_redirect($back, 'success', 'All rules deleted. This user is no longer limited by ledger.');
        }
        if (preg_match('/^delete:(\d+)$/', $action, $m)) {
            sales_exec($conn, 'DELETE FROM admin_user_ledgers WHERE rule_id=? AND user_id=?', 'ii', [(int) $m[1], $userId])->close();
            sales_flash_redirect($back, 'success', 'Deleted.');
        }
        throw new RuntimeException('Unknown action.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$rows = $ready && $userId ? sales_rows($conn, 'SELECT r.rule_id, r.branch_id, r.scope, r.ref_id, b.branch_name FROM admin_user_ledgers r LEFT JOIN branches b ON b.branch_id=r.branch_id WHERE r.user_id=? ORDER BY b.branch_name, r.scope, r.ref_id', 'i', [$userId]) : [];
$held = [];
foreach ($rows as $r) {
    if ((int) $r['branch_id'] === ($allBranch ? 0 : $access) && $r['scope'] === $mode) {
        $held[(int) $r['ref_id']] = true;
    }
}
$list = array_filter($picks[$mode], fn($p) => $p[2]);
$off = $ready && $userId ? '' : ' disabled';
$option = fn($id, $name, $on) => '<option value="' . (int) $id . '"' . ($on ? ' selected' : '') . '>' . sales_h($name) . '</option>';
sa_page_head('User Wise Ledger Fixing', sales_take_flash());
?>
<link rel="stylesheet" href="user_ledger_fixing.css">
<?php if (!$ready): ?><div class="alert alert-warning"><b>The ledger rules table is not installed.</b> Import <code>billing/user/schema.sql</code> (Accounts must be installed too), then reload this page.</div><?php endif; ?>
<form method="get" class="ulf-pick" id="ulf-pick" autocomplete="off">
  <div class="ulf-row">
    <label for="ulf-access">Access Branch Name</label>
    <select id="ulf-access" name="access"<?= $allBranch ? ' disabled' : '' ?>><?php foreach ($branches as $b): ?><?= $option($b['branch_id'], $b['branch_name'], (int) $b['branch_id'] === $access) ?><?php endforeach; ?></select>
    <label class="ulf-box"><input type="checkbox" id="ulf-all-branch" name="all_branch" value="1"<?= $allBranch ? ' checked' : '' ?><?= $canAllBranch ? '' : ' disabled' ?>> All Branch</label>
  </div>
  <div class="ulf-row">
    <label for="ulf-branch">Branch Name</label>
    <select id="ulf-branch" name="branch" onchange="this.form.submit()"><?php foreach ($branches as $b): ?><?= $option($b['branch_id'], $b['branch_name'], (int) $b['branch_id'] === $branchId) ?><?php endforeach; ?></select>
    <label for="ulf-user">User Name</label>
    <select id="ulf-user" name="user" onchange="this.form.submit()"><option value="0">Select user</option><?php foreach ($users as $u): ?><?= $option($u['user_id'], $u['user_name'] . ($u['full_name'] !== '' ? ' (' . $u['full_name'] . ')' : ''), (int) $u['user_id'] === $userId) ?><?php endforeach; ?></select>
  </div>
  <div class="ulf-row ulf-modes">
    <?php foreach (ULF_MODES as $key => $label): ?><label class="ulf-box"><?= sales_h($label) ?> <input type="checkbox" name="mode" value="<?= $key ?>"<?= $key === $mode ? ' checked' : '' ?>></label><?php endforeach; ?>
  </div>
  <div class="ulf-actions"><button class="btn btn-default">View</button></div>
</form>
<form method="post" id="ulf-add"><?= sales_csrf_field() ?>
<input type="hidden" name="action" value="add"><input type="hidden" name="access" value="<?= $access ?>"><input type="hidden" name="branch" value="<?= $branchId ?>"><input type="hidden" name="user" value="<?= $userId ?>"><input type="hidden" name="mode" value="<?= sales_h($mode) ?>">
<?php if ($allBranch): ?><input type="hidden" name="all_branch" value="1"><?php endif; ?>
<div class="table-scroll ulf-list"><table class="table table-bordered">
<thead><tr><th>SL</th><th>Ledger Name</th><th>Accounts Flag</th><th class="ulf-c"><input type="checkbox" id="ulf-check-all" aria-label="Tick every row"<?= $off ?>></th><th class="ulf-c">Add</th></tr></thead>
<tbody>
<?php $n = 0; foreach ($list as $ref => [$name, $flag]): $n++; $has = isset($held[$ref]); ?>
<tr><td><?= $n ?></td><td><?= sales_h($name) ?></td><td><?= sales_h($flag) ?></td>
<td class="ulf-c"><input type="checkbox" name="refs[]" value="<?= (int) $ref ?>" aria-label="Tick <?= sales_h($name) ?>"<?= $has ? ' disabled' : $off ?>></td>
<td class="ulf-c"><?php if ($has): ?><span class="muted">Added</span><?php else: ?><button class="btn btn-default btn-xs" name="one" value="<?= (int) $ref ?>"<?= $off ?>>Add</button><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="5" class="empty">Nothing to list for <?= sales_h(ULF_MODES[$mode]) ?>.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="ulf-actions"><button class="btn btn-default"<?= $off ?>>Add</button></div>
</form>
<h4 class="ulf-title">Ledgers of this user</h4>
<p class="muted ulf-note">A user with no rules can use every ledger. The first rule limits them to what the rules allow, so include a cash or bank ledger if they enter vouchers. SuperAdmin users are never limited.</p>
<form method="post" id="ulf-del"><?= sales_csrf_field() ?><input type="hidden" name="access" value="<?= $access ?>"><input type="hidden" name="branch" value="<?= $branchId ?>"><input type="hidden" name="user" value="<?= $userId ?>"><input type="hidden" name="mode" value="<?= sales_h($mode) ?>">
<div class="table-scroll grid-box"><table class="table table-bordered">
<thead><tr><th>SL</th><th>Access Branch</th><th>Kind</th><th>Ledger Name</th><th class="ulf-c">Delete</th></tr></thead>
<tbody>
<?php foreach ($rows as $i => $r): ?>
<tr><td><?= $i + 1 ?></td>
<td><?= (int) $r['branch_id'] === 0 ? 'All Branch' : sales_h($r['branch_name'] ?? 'Branch ' . $r['branch_id']) ?></td>
<td><?= sales_h(ULF_MODES[$r['scope']] ?? $r['scope']) ?></td>
<td><?= sales_h($picks[$r['scope']][(int) $r['ref_id']][0] ?? 'Removed (' . $r['ref_id'] . ')') ?></td>
<td class="ulf-c"><button class="btn btn-default btn-xs" name="action" value="delete:<?= (int) $r['rule_id'] ?>">Delete</button></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty"><?= $userId ? 'No rules for this user.' : 'Pick a user to see their ledgers.' ?></td></tr><?php endif; ?>
</tbody></table></div>
<div class="ulf-actions"><button class="btn btn-default" name="action" value="delete_all" onclick="return confirm('Delete every ledger rule of this user? They will no longer be limited by ledger.')"<?= $off || !$rows ? ' disabled' : '' ?>>All Delete</button></div>
</form>
<script>
(function () {
  var modes = document.querySelectorAll('input[name="mode"]');
  modes.forEach(function (box) {
    box.addEventListener('change', function () {
      if (!box.checked) box.checked = true;
      modes.forEach(function (other) { if (other !== box) other.checked = false; });
    });
  });
  document.getElementById('ulf-all-branch').addEventListener('change', function () {
    document.getElementById('ulf-access').disabled = this.checked;
  });
  document.getElementById('ulf-check-all').addEventListener('change', function () {
    var on = this.checked;
    document.querySelectorAll('input[name="refs[]"]:not(:disabled)').forEach(function (box) { box.checked = on; });
  });
})();
</script>
</body></html>
<?php include "../../addon/conf/connection_close.php"; ?>
