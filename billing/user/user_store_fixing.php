<?php
/**
 * User Wise Store Fixing: which stores a user may work with, by branch and transaction module. Each row of
 * admin_user_stores (billing/user/schema.sql) opens something up; a user with no rows is not limited. perm_allowed_stores()
 * in addon/inc/perm.php applies the rules: store lists show only the allowed stores and a save with another store is refused.
 * The boxes on the right widen a rule; only one can be ticked. With none, the rule is Access Branch + Module + Store.
 */
require_once dirname(__DIR__) . '/store_admin/sa_list.php';

/** Box => [label, all branches, all modules, all stores]. In the order of the form rows beside them. */
const USF_SCOPES = [
    'branch_store' => ['All Branch & All Store', true, false, true],
    'branch_store_module' => ['All Branch & All Store & All Module', true, true, true],
    'module_store' => ['All Module & All Store', false, true, true],
    'selected_module' => ['Selected Module & All Store', false, false, true],
    'selected_store' => ['All Module & Selected Store', false, true, false],
];

$self = 'user_store_fixing.php';
$me = perm_user();
$iAmSuper = perm_is_super();
$ready = sales_tables_ready($conn, ['admin_user_stores']);
$canAllBranch = perm_branch_ids() === null;
$branches = array_values(array_filter(sales_rows($conn, 'SELECT branch_id, branch_name FROM branches WHERE is_active=1 ORDER BY branch_name'), fn($b) => perm_branch_allowed($b['branch_id'])));
$branchIds = array_map('intval', array_column($branches, 'branch_id'));
$modules = perm_store_modules();
$forms = perm_forms();
$stores = sales_stores($conn);

$branchId = (int) ($_REQUEST['branch'] ?? 0);
$branchId = in_array($branchId, $branchIds, true) ? $branchId : (in_array(perm_current_branch(), $branchIds, true) ? perm_current_branch() : ($branchIds[0] ?? 0));
$users = perm_fixing_users($branchId);
$userId = (int) ($_REQUEST['user'] ?? 0);
$userId = in_array($userId, array_map('intval', array_column($users, 'user_id')), true) ? $userId : 0;
$back = $self . '?' . http_build_query(['branch' => $branchId, 'user' => $userId]);

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
            $scope = (string) ($_POST['scope'] ?? '');
            [, $allBranch, $allModule, $allStore] = USF_SCOPES[$scope] ?? ['', false, false, false];
            $access = (int) ($_POST['access_branch'] ?? 0);
            $form = (string) ($_POST['form_key'] ?? '');
            $store = sales_post_string('store_name', 140);
            if ($allBranch && !$canAllBranch) {
                throw new RuntimeException('Only a user with every branch can add an All Branch rule.');
            }
            if (!$allBranch && !in_array($access, $branchIds, true)) {
                throw new RuntimeException('Select the Access Branch.');
            }
            if (!$allModule && !isset($modules[$form])) {
                throw new RuntimeException('Select the Transaction Module.');
            }
            if (!$allStore && !in_array($store, $stores, true)) {
                throw new RuntimeException('Select the Store.');
            }
            $added = sales_exec($conn, 'INSERT IGNORE INTO admin_user_stores (user_id, branch_id, form_key, store_name, created_by) VALUES (?, ?, ?, ?, ?)', 'iissi',
                [$userId, $allBranch ? 0 : $access, $allModule ? '' : $form, $allStore ? '' : $store, (int) $me['user_id']]);
            $count = $added->affected_rows;
            $added->close();
            sales_flash_redirect($back, $count > 0 ? 'success' : 'error', $count > 0 ? 'Added.' : 'This user already has that rule.');
        }
        if ($action === 'delete_all') {
            sales_exec($conn, 'DELETE FROM admin_user_stores WHERE user_id=?', 'i', [$userId])->close();
            sales_flash_redirect($back, 'success', 'All rules deleted. This user is no longer limited by store.');
        }
        if (preg_match('/^delete:(\d+)$/', $action, $m)) {
            sales_exec($conn, 'DELETE FROM admin_user_stores WHERE rule_id=? AND user_id=?', 'ii', [(int) $m[1], $userId])->close();
            sales_flash_redirect($back, 'success', 'Deleted.');
        }
        throw new RuntimeException('Unknown action.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$rows = $ready && $userId ? sales_rows($conn, 'SELECT r.rule_id, r.branch_id, r.form_key, r.store_name, b.branch_name FROM admin_user_stores r LEFT JOIN branches b ON b.branch_id=r.branch_id WHERE r.user_id=? ORDER BY b.branch_name, r.form_key, r.store_name', 'i', [$userId]) : [];
$groups = [];
foreach ($modules as $key => $name) {
    $groups[$forms[$key]['category']][$key] = $name;
}
$off = $ready && $userId ? '' : ' disabled';
sa_page_head('User Wise Store Fixing', sales_take_flash());
?>
<link rel="stylesheet" href="user_store_fixing.css">
<?php if (!$ready): ?><div class="alert alert-warning"><b>The store rules table is not installed.</b> Import <code>billing/user/schema.sql</code>, then reload this page.</div><?php endif; ?>
<form method="get" id="usf-pick"></form>
<form method="post" id="usf-del"><?= sales_csrf_field() ?><input type="hidden" name="branch" value="<?= $branchId ?>"><input type="hidden" name="user" value="<?= $userId ?>"></form>
<form method="post" class="usf-form" id="usf-form" autocomplete="off"><?= sales_csrf_field() ?>
<input type="hidden" name="branch" value="<?= $branchId ?>"><input type="hidden" name="user" value="<?= $userId ?>">
<?php $scopes = array_keys(USF_SCOPES); $box = function ($i) use ($scopes, $canAllBranch) {
    $key = $scopes[$i];
    $locked = USF_SCOPES[$key][1] && !$canAllBranch;
    return '<label class="usf-scope"><input type="checkbox" name="scope" value="' . $key . '"' . ($locked ? ' disabled' : '') . '> ' . sales_h(USF_SCOPES[$key][0]) . '</label>';
}; ?>
  <label for="usf-access">Access Branch Name</label>
  <select id="usf-access" name="access_branch"><?php foreach ($branches as $b): ?><option value="<?= (int) $b['branch_id'] ?>"<?= (int) $b['branch_id'] === $branchId ? ' selected' : '' ?>><?= sales_h($b['branch_name']) ?></option><?php endforeach; ?></select>
  <?= $box(0) ?>
  <label for="usf-branch">Branch Name</label>
  <select id="usf-branch" name="branch" form="usf-pick" onchange="this.form.submit()"><?php foreach ($branches as $b): ?><option value="<?= (int) $b['branch_id'] ?>"<?= (int) $b['branch_id'] === $branchId ? ' selected' : '' ?>><?= sales_h($b['branch_name']) ?></option><?php endforeach; ?></select>
  <?= $box(1) ?>
  <label for="usf-user">User Name</label>
  <select id="usf-user" name="user" form="usf-pick" onchange="this.form.submit()"><option value="0">Select user</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['user_id'] ?>"<?= (int) $u['user_id'] === $userId ? ' selected' : '' ?>><?= sales_h($u['user_name'] . ($u['full_name'] !== '' ? ' (' . $u['full_name'] . ')' : '')) ?></option><?php endforeach; ?></select>
  <?= $box(2) ?>
  <label for="usf-module">Transaction Module</label>
  <select id="usf-module" name="form_key"><?php foreach ($groups as $category => $items): ?><optgroup label="<?= sales_h($category) ?>"><?php foreach ($items as $key => $name): ?><option value="<?= sales_h($key) ?>"><?= sales_h($name) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select>
  <?= $box(3) ?>
  <label for="usf-store">Store Name</label>
  <select id="usf-store" name="store_name"><?php foreach ($stores as $s): ?><option><?= sales_h($s) ?></option><?php endforeach; ?></select>
  <?= $box(4) ?>
</form>
<div class="usf-actions">
  <button class="btn btn-default" form="usf-form" name="action" value="add"<?= $off ?>>Add</button>
  <button class="btn btn-default" form="usf-del" name="action" value="delete_all" onclick="return confirm('Delete every store rule of this user? They will no longer be limited by store.')"<?= $off || !$rows ? ' disabled' : '' ?>>All Delete</button>
</div>
<p class="muted usf-note"><?= $userId && !$rows ? 'This user has no rules, so every store is open. The first rule limits them to what the rules allow.' : 'A user with no rules can use every store. SuperAdmin users are never limited.' ?></p>
<div class="table-scroll grid-box"><table class="table table-bordered">
<thead><tr><th>SL</th><th>Access Branch</th><th>Transaction Module</th><th>Store Name</th><th>Delete</th></tr></thead>
<tbody>
<?php foreach ($rows as $i => $r): ?>
<tr>
<td><?= $i + 1 ?></td>
<td><?= (int) $r['branch_id'] === 0 ? 'All Branch' : sales_h($r['branch_name'] ?? 'Branch ' . $r['branch_id']) ?></td>
<td><?= $r['form_key'] === '' ? 'All Module' : sales_h($forms[$r['form_key']]['name'] ?? $r['form_key']) ?></td>
<td><?= $r['store_name'] === '' ? 'All Store' : sales_h($r['store_name']) ?></td>
<td><button class="btn btn-default btn-xs" form="usf-del" name="action" value="delete:<?= (int) $r['rule_id'] ?>">Delete</button></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty"><?= $userId ? 'No rules for this user.' : 'Pick a user to see their rules.' ?></td></tr><?php endif; ?>
</tbody></table></div>
<script>
(function () {
  var boxes = document.querySelectorAll('.usf-scope input');
  var scopes = <?= json_encode(array_map(fn($s) => array_slice($s, 1), USF_SCOPES)) ?>;
  var fields = [document.getElementById('usf-access'), document.getElementById('usf-module'), document.getElementById('usf-store')];
  function sync() {
    var on = null;
    boxes.forEach(function (b) { if (b.checked) on = scopes[b.value]; });
    fields.forEach(function (field, i) { field.disabled = !!(on && on[i]); });
  }
  boxes.forEach(function (box) {
    box.addEventListener('change', function () {
      boxes.forEach(function (other) { if (other !== box) other.checked = false; });
      sync();
    });
  });
  sync();
})();
</script>
</body></html>
<?php include "../../addon/conf/connection_close.php"; ?>
