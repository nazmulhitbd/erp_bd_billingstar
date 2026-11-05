<?php
/**
 * Users tab of Users, Roles & Permissions: the user list beside the registration form. Every user here signs in on the normal login page and gets
 * the admin shell. Staff work with the forms given in User Access Control, in the branches ticked here. A customer or
 * employee user is linked to their customer / SR record and sees their own portal pages in the same shell, so their
 * password lives on the portal account. Only a SuperAdmin can create or change a SuperAdmin.
 */
require_once dirname(__DIR__) . '/store_admin/sa_list.php';

$self = 'registration.php';
$me = perm_user();
$iAmSuper = perm_is_super();
const REG_TYPES = ['staff' => 'Staff', 'employee' => 'Employee (SR)', 'customer' => 'Customer'];

function reg_time($key)
{
    $v = trim((string) ($_POST[$key] ?? ''));
    return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $v) ? $v : null;
}

/** Create or update the portal account that holds a customer / employee password. */
function reg_portal_account(mysqli $db, $type, $linked, $login, $password, $active, $by)
{
    [$table, $col] = perm_portal_table($type);
    $status = $active ? 'active' : 'disabled';
    $row = sales_row($db, "SELECT account_id FROM $table WHERE $col=?", 'i', [$linked]);
    if ($row) {
        sales_exec($db, "UPDATE $table SET login=?, status=? WHERE account_id=?", 'ssi', [$login, $status, (int) $row['account_id']])->close();
        if ($password !== '') {
            sales_exec($db, "UPDATE $table SET password_hash=?, must_change_password=0, failed_attempts=0, locked_until=NULL WHERE account_id=?", 'si', [password_hash($password, PASSWORD_DEFAULT), (int) $row['account_id']])->close();
        }
        return;
    }
    if ($password === '') {
        throw new RuntimeException('Enter a password: this ' . $type . ' has no login yet.');
    }
    sales_exec($db, "INSERT INTO $table ($col, login, password_hash, status, must_change_password, created_by) VALUES (?, ?, ?, ?, 0, ?)", 'isssi', [$linked, $login, password_hash($password, PASSWORD_DEFAULT), $status, $by])->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['user_id'] ?? 0);
    $back = $self . ($id ? '?edit=' . $id : '');
    sales_require_csrf($back);
    try {
        $old = $id ? sales_row($conn, 'SELECT * FROM admin_user WHERE user_id=?', 'i', [$id]) : null;
        if ($id && !$old) {
            throw new RuntimeException('User not found.');
        }
        if ($old && $old['access_level'] === 'SuperAdmin' && !$iAmSuper) {
            throw new RuntimeException('Only a SuperAdmin can change a SuperAdmin.');
        }
        $name = sales_post_string('user_name', 100);
        $fullName = sales_post_string('full_name', 120);
        $password = (string) ($_POST['password'] ?? '');
        $level = in_array($_POST['access_level'] ?? '', PERM_LEVELS, true) ? $_POST['access_level'] : 'Operator';
        $type = isset(REG_TYPES[$_POST['user_type'] ?? '']) ? $_POST['user_type'] : 'staff';
        $linked = $type === 'customer' ? (int) ($_POST['customer_id'] ?? 0) : ($type === 'employee' ? (int) ($_POST['sr_id'] ?? 0) : 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $allBranches = isset($_POST['all_branches']) ? 1 : 0;
        $branches = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['branches'] ?? [])))));
        $default = (int) ($_POST['default_branch_id'] ?? 0);
        $fullTime = isset($_POST['full_time']) ? 1 : 0;
        $start = reg_time('start_time');
        $end = reg_time('end_time');
        $birth = sales_valid_date($_POST['date_of_birth'] ?? '', true) ?: null;
        // SuperAdmin and customers do not use a role; anything else keeps to the assignable list.
        $roles = perm_assignable_roles();
        $roleId = (int) ($_POST['user_role_id'] ?? 0);
        if ($level === 'SuperAdmin' || $type === 'customer' || !isset($roles[$roleId])) {
            $roleId = $old ? (int) $old['user_role_id'] : perm_role_id();
        }
        if ($roleId !== (int) ($old['user_role_id'] ?? 0) && !perm_role_within_own($roleId)) {
            throw new RuntimeException('You can only give a role whose rights you hold yourself.');
        }

        if ($name === '' || !preg_match('/^[A-Za-z0-9._@-]+$/', $name)) {
            throw new RuntimeException('Enter a user name (letters, numbers, . _ @ - only).');
        }
        if ($fullName === '') {
            throw new RuntimeException('Enter the name.');
        }
        if ($password !== (string) ($_POST['confirm_password'] ?? '')) {
            throw new RuntimeException('The two passwords do not match.');
        }
        if (($password !== '' || !$id) && strlen($password) < 8) {
            throw new RuntimeException('Use a password of at least 8 characters.');
        }
        if ($level === 'SuperAdmin' && (!$iAmSuper || $type !== 'staff')) {
            throw new RuntimeException($iAmSuper ? 'A SuperAdmin must be a staff user.' : 'Only a SuperAdmin can create a SuperAdmin.');
        }
        if ($type !== 'staff' && $linked <= 0) {
            throw new RuntimeException('Pick the ' . ($type === 'customer' ? 'customer' : 'employee') . ' this login belongs to.');
        }
        if (!$fullTime && (!$start || !$end)) {
            throw new RuntimeException('Enter the start and end time, or tick Full Time.');
        }
        if ($type !== 'customer' && $level !== 'SuperAdmin' && !$allBranches && !$branches) {
            throw new RuntimeException('Tick at least one branch, or All Branches.');
        }
        if ($id === (int) $me['user_id'] && (!$active || $level !== $me['access_level'])) {
            throw new RuntimeException('You cannot change your own access level or deactivate yourself.');
        }
        // A limited user can only hand out the branches they hold themselves.
        $mine = perm_branch_ids();
        if ($mine !== null && ($allBranches || array_diff($branches, $mine))) {
            throw new RuntimeException('You can only give branches you have access to.');
        }
        if ($default && !$allBranches && $level !== 'SuperAdmin' && !in_array($default, perm_with_sub_branches($branches), true)) {
            throw new RuntimeException('The default branch must be one of the ticked branches.');
        }
        if (sales_row($conn, 'SELECT 1 AS x FROM admin_user WHERE user_name=? AND user_id<>?', 'si', [$name, $id])) {
            throw new RuntimeException('That user name is already taken.');
        }
        if ($type !== 'staff' && sales_row($conn, 'SELECT 1 AS x FROM admin_user WHERE user_type=? AND linked_id=? AND user_id<>?', 'sii', [$type, $linked, $id])) {
            throw new RuntimeException('This ' . $type . ' already has a user.');
        }

        $conn->begin_transaction();
        $stored = $type === 'staff' ? ($password !== '' ? password_hash($password, PASSWORD_DEFAULT) : (string) ($old['user_password'] ?? '')) : '!';
        $vals = [$name, $fullName, $stored, sales_post_string('email', 150), sales_post_string('mobile', 30), $level, $type, $linked ?: null, sales_post_string('department', 80), $birth, $fullTime, $start, $end, $active, $allBranches, $default ?: null, $roleId];
        $types = 'ssssssssss' . 'issiiii';
        if ($id) {
            sales_exec($conn, 'UPDATE admin_user SET user_name=?, full_name=?, user_password=?, email=?, mobile=?, access_level=?, user_type=?, linked_id=?, department=?, date_of_birth=?, full_time=?, start_time=?, end_time=?, is_active=?, all_branches=?, default_branch_id=?, user_role_id=? WHERE user_id=?', $types . 'i', [...$vals, $id])->close();
            if ($old['user_type'] !== 'staff' && ($type !== $old['user_type'] || $linked !== (int) $old['linked_id'])) {
                // The login moved to another record: the old portal account must not stay usable under it.
                [$oldTable, $oldCol] = perm_portal_table($old['user_type']);
                sales_exec($conn, "UPDATE $oldTable SET status='disabled' WHERE $oldCol=?", 'i', [(int) $old['linked_id']])->close();
            }
            if (!$active) {
                sales_exec($conn, "UPDATE admin_user SET sesid='', lastaccess=0 WHERE user_id=?", 'i', [$id])->close();
            }
        } else {
            $id = sales_insert($conn, 'INSERT INTO admin_user (user_name, full_name, user_password, email, mobile, access_level, user_type, linked_id, department, date_of_birth, full_time, start_time, end_time, is_active, all_branches, default_branch_id, user_role_id, level, show_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)', $types, $vals);
        }
        sales_exec($conn, 'DELETE FROM admin_user_branches WHERE user_id=?', 'i', [$id])->close();
        foreach ($allBranches ? [] : $branches as $b) {
            sales_exec($conn, 'INSERT INTO admin_user_branches (user_id, branch_id) VALUES (?, ?)', 'ii', [$id, $b])->close();
        }
        if ($type !== 'staff') {
            reg_portal_account($conn, $type, $linked, $name, $password, $active, (int) $me['user_id']);
        }
        $conn->commit();
        sales_flash_redirect($self . '?edit=' . $id, 'success', 'The record was saved. Set this user\'s forms in User Access Control.');
    } catch (RuntimeException $e) {
        $conn->rollback();
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$edit = isset($_GET['edit']) ? sales_row($conn, 'SELECT * FROM admin_user WHERE user_id=?', 'i', [(int) $_GET['edit']]) : null;
$users = sales_rows($conn, "SELECT u.user_id, u.user_name, u.full_name, u.user_type, u.access_level, u.is_active, u.default_branch_id, COALESCE(r.role_name, '') AS role_name FROM admin_user u LEFT JOIN admin_role r ON r.user_role_id=u.user_role_id WHERE u.user_name<>'administrator1' ORDER BY u.user_id");
$ruleCount = function ($table) use ($conn) {
    return sales_tables_ready($conn, [$table]) ? array_column(sales_rows($conn, "SELECT user_id, COUNT(*) AS n FROM $table GROUP BY user_id"), 'n', 'user_id') : [];
};
$storeRules = $ruleCount('admin_user_stores');
$ledgerRules = $ruleCount('admin_user_ledgers');
$allBranchRows = sales_rows($conn, 'SELECT branch_id, branch_name, parent_branch_id FROM branches WHERE is_active=1 ORDER BY branch_name');
$branchRows = array_filter($allBranchRows, fn($b) => perm_branch_allowed($b['branch_id']));
$picked = $edit ? array_map('intval', array_column(sales_rows($conn, 'SELECT branch_id FROM admin_user_branches WHERE user_id=?', 'i', [(int) $edit['user_id']]), 'branch_id')) : [];
$customers = sales_tables_ready($conn, ['sales_customers']) ? sales_rows($conn, 'SELECT customer_id, customer_name, mobile FROM sales_customers ORDER BY customer_name LIMIT 5000') : [];
$srs = sales_tables_ready($conn, ['sales_representatives']) ? sales_rows($conn, 'SELECT sr_id, sr_name, sr_code FROM sales_representatives WHERE is_active=1 ORDER BY sr_name') : [];
$departments = array_column(sales_rows($conn, "SELECT DISTINCT department FROM admin_user WHERE department<>'' ORDER BY department"), 'department');
$v = fn($k, $d = '') => sales_h($edit[$k] ?? $d);
$type = $edit['user_type'] ?? 'staff';
$levels = $iAmSuper ? PERM_LEVELS : array_values(array_diff(PERM_LEVELS, ['SuperAdmin']));

sa_page_head('Users', sales_take_flash());
?>
<style>
.reg{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,6fr);gap:16px;align-items:start}
.reg fieldset,.reg legend{all:revert}
.reg-row{display:grid;grid-template-columns:170px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px}
.reg-row label{font-weight:bold;margin:0}
.reg-row .form-control{max-width:340px}
.reg-checks{display:flex;flex-wrap:wrap;gap:4px 14px;font-weight:normal}
.reg-checks label{font-weight:normal;white-space:nowrap}
.reg-list{max-height:640px;overflow:auto}
@media (max-width:900px){.reg{grid-template-columns:minmax(0,1fr)}.reg-row{grid-template-columns:minmax(0,1fr);gap:2px}}
</style>
<h4 style="margin-top:0"><?= $edit ? 'Edit User' : 'Add User' ?></h4>
<div class="reg">
<div class="panel panel-default"><div class="panel-body reg-list">
<table class="table table-bordered table-hover">
<thead><tr class="WUIrsheader"><td class="WUIrsheaderel">SL</td><td class="WUIrsheaderel">User Name</td><td class="WUIrsheaderel">Type</td><td class="WUIrsheaderel">Active</td><td class="WUIrsheaderel">Access</td><td class="WUIrsheaderel">Edit</td></tr></thead>
<tbody>
<?php foreach ($users as $i => $u): ?>
<tr class="<?= $i % 2 ? 'WUIrsrecordrowodd' : 'WUIrsrecordroweven' ?>">
<td class="WUIfield"><?= $i + 1 ?></td>
<td class="WUIfield"><?= sales_h($u['user_name']) ?><?= $u['full_name'] !== '' ? ' <span class="muted">' . sales_h($u['full_name']) . '</span>' : '' ?></td>
<td class="WUIfield"><?= sales_h($u['user_type'] === 'staff' ? $u['access_level'] : REG_TYPES[$u['user_type']]) ?><?= $u['user_type'] === 'staff' && $u['access_level'] !== 'SuperAdmin' && $u['role_name'] !== '' ? '<br><span class="muted">' . sales_h($u['role_name']) . '</span>' : '' ?></td>
<td class="WUIfield" style="text-align:center"><input type="checkbox" disabled<?= (int) $u['is_active'] ? ' checked' : '' ?> aria-label="Active"></td>
<td class="WUIfield nowrap" style="text-align:center"><?php $limited = $u['user_type'] !== 'customer' && $u['access_level'] !== 'SuperAdmin'; $branch = (int) $u['default_branch_id']; ?><?php if ($limited && perm_can('user/user_store_fixing.php', 'view')): ?><a class="btn btn-default btn-xs" href="user_store_fixing.php?branch=<?= $branch ?>&user=<?= (int) $u['user_id'] ?>">Stores <?= (int) ($storeRules[$u['user_id']] ?? 0) ?></a> <?php endif; ?><?php if ($limited && perm_can('user/user_ledger_fixing.php', 'view')): ?><a class="btn btn-default btn-xs" href="user_ledger_fixing.php?branch=<?= $branch ?>&user=<?= (int) $u['user_id'] ?>">Ledgers <?= (int) ($ledgerRules[$u['user_id']] ?? 0) ?></a> <?php endif; ?><?php if (perm_can('user/user_access.php', 'view')): ?><a class="btn btn-default btn-xs" href="user_access.php?user=<?= (int) $u['user_id'] ?>" title="Open this user's rights">Select</a><?php endif; ?></td>
<td class="WUIfield" style="text-align:center"><a class="btn btn-default btn-xs" href="<?= $self ?>?edit=<?= (int) $u['user_id'] ?>">Edit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>

<div class="panel panel-default"><div class="panel-body">
<form method="post" autocomplete="off"><?= sales_csrf_field() ?>
<input type="hidden" name="user_id" value="<?= (int) ($edit['user_id'] ?? 0) ?>">
<div class="reg-row"><label for="full_name">Name</label><input class="form-control" id="full_name" name="full_name" maxlength="120" required value="<?= $v('full_name') ?>"></div>
<div class="reg-row"><label for="user_name">User Name</label><input class="form-control" id="user_name" name="user_name" maxlength="100" required value="<?= $v('user_name') ?>"></div>
<div class="reg-row"><label for="password">Password</label><input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password"<?= $edit ? ' placeholder="Leave empty to keep"' : ' required' ?>></div>
<div class="reg-row"><label for="confirm_password">Confirm Password</label><input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"<?= $edit ? '' : ' required' ?>></div>
<div class="reg-row"><label for="email">Email ID</label><input class="form-control" type="email" id="email" name="email" maxlength="150" value="<?= $v('email') ?>"></div>
<div class="reg-row"><label for="mobile">Mobile No</label><input class="form-control" id="mobile" name="mobile" maxlength="30" value="<?= $v('mobile') ?>"></div>
<div class="reg-row"><label for="user_type">User Type</label><select class="form-control" id="user_type" name="user_type"><?php foreach (REG_TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $type === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
<div class="reg-row" data-for="employee"><label for="sr_id">Employee</label><select class="form-control" id="sr_id" name="sr_id"><option value="0">- choose -</option><?php foreach ($srs as $s): ?><option value="<?= (int) $s['sr_id'] ?>"<?= $type === 'employee' && (int) $edit['linked_id'] === (int) $s['sr_id'] ? ' selected' : '' ?>><?= sales_h($s['sr_name'] . ($s['sr_code'] ? ' (' . $s['sr_code'] . ')' : '')) ?></option><?php endforeach; ?></select></div>
<div class="reg-row" data-for="customer"><label for="customer_id">Customer</label><select class="form-control" id="customer_id" name="customer_id"><option value="0">- choose -</option><?php foreach ($customers as $c): ?><option value="<?= (int) $c['customer_id'] ?>"<?= $type === 'customer' && (int) $edit['linked_id'] === (int) $c['customer_id'] ? ' selected' : '' ?>><?= sales_h($c['customer_name'] . ($c['mobile'] ? ' - ' . $c['mobile'] : '')) ?></option><?php endforeach; ?></select></div>
<div class="reg-row" data-for="staff employee"><label for="default_branch_id">Default Branch</label><select class="form-control" id="default_branch_id" name="default_branch_id"><option value="0">- none -</option><?php foreach ($branchRows as $b): ?><option value="<?= (int) $b['branch_id'] ?>"<?= (int) ($edit['default_branch_id'] ?? 0) === (int) $b['branch_id'] ? ' selected' : '' ?>><?= sales_h($b['branch_name']) ?></option><?php endforeach; ?></select></div>
<div class="reg-row" data-for="staff employee"><label>Branches</label><div class="reg-checks">
<?php if (perm_branch_ids() === null): ?><label><input type="checkbox" name="all_branches" id="all_branches"<?= (int) ($edit['all_branches'] ?? 0) ? ' checked' : '' ?>> All Branches</label><?php endif; ?>
<?php foreach ($branchRows as $b): ?><label><input type="checkbox" name="branches[]" value="<?= (int) $b['branch_id'] ?>"<?= in_array((int) $b['branch_id'], $picked, true) ? ' checked' : '' ?>> <?= sales_h($b['branch_name']) ?><?= $b['parent_branch_id'] ? ' <span class="muted">(sub)</span>' : '' ?></label><?php endforeach; ?>
</div></div>
<div class="reg-row" data-for="staff employee"><label for="access_level">Access Level</label><select class="form-control" id="access_level" name="access_level"><?php foreach ($levels as $l): ?><option<?= ($edit['access_level'] ?? 'Operator') === $l ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
<div class="reg-row" data-for="staff employee"><label for="user_role_id">Role</label><select class="form-control" id="user_role_id" name="user_role_id"><?php foreach (perm_assignable_roles() as $rid => $rname): ?><option value="<?= $rid ?>"<?= (int) ($edit['user_role_id'] ?? perm_role_id()) === $rid ? ' selected' : '' ?>><?= sales_h($rname === 'ERP User' ? '- No role -' : $rname) ?></option><?php endforeach; ?></select></div>
<div class="reg-row"><label for="department">Department</label><input class="form-control" id="department" name="department" maxlength="80" list="dept-list" value="<?= $v('department') ?>"><datalist id="dept-list"><?php foreach ($departments as $d): ?><option value="<?= sales_h($d) ?>"><?php endforeach; ?></datalist></div>
<div class="reg-row"><label for="date_of_birth">Date Of Birth</label><input class="form-control" type="date" id="date_of_birth" name="date_of_birth" value="<?= $v('date_of_birth') ?>"></div>
<div class="reg-row"><label for="start_time">Start Time</label><div class="reg-checks"><input class="form-control" style="width:auto" type="time" id="start_time" name="start_time" value="<?= $v('start_time') ?>"> <label><input type="checkbox" name="full_time" id="full_time"<?= (int) ($edit['full_time'] ?? 1) ? ' checked' : '' ?>> Full Time</label></div></div>
<div class="reg-row"><label for="end_time">End Time</label><input class="form-control" style="width:auto" type="time" id="end_time" name="end_time" value="<?= $v('end_time') ?>"></div>
<div class="reg-row"><label for="is_active">Active</label><div><input type="checkbox" id="is_active" name="is_active"<?= (int) ($edit['is_active'] ?? 1) ? ' checked' : '' ?>></div></div>
<button class="btn btn-primary" name="action" value="add"<?= $edit ? ' disabled' : '' ?>>Add</button>
<button class="btn btn-default" name="action" value="edit"<?= $edit ? '' : ' disabled' ?>>Edit</button>
<?php if ($edit): ?><a class="btn btn-link" href="<?= $self ?>">New user</a><?php endif; ?>
</form>
</div></div>
</div>
<script>
(function () {
  var type = document.getElementById('user_type');
  function sync() {
    document.querySelectorAll('[data-for]').forEach(function (row) {
      row.style.display = row.dataset.for.split(' ').indexOf(type.value) >= 0 ? '' : 'none';
    });
  }
  type.addEventListener('change', sync);
  sync();
})();
</script>
<?php
sa_page_foot('SuperAdmin passes every permission. Everyone else gets the rights of their role, changed for that person in User Access Control. Customers always see only their own records.');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
