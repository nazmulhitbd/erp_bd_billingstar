<?php
/**
 * User Access Control: pick a user, a branch (or All Branch) and an Access Category, optionally a Sub Menu, then tick
 * what the user may do on each form. Categories, sub menus and forms come from billing/menu_catalog.php, the same list the
 * top menu is drawn from. A row saved for a branch replaces the All Branch row there; saving All Branch clears those
 * branch rows. Only a user's own rights can be handed out, and only a SuperAdmin can copy rights between users.
 */
require_once dirname(__DIR__) . '/store_admin/sa_list.php';

$self = 'user_access.php';
$me = perm_user();
$iAmSuper = perm_is_super();
$allForms = perm_forms();
$categories = array_values(array_unique(array_column($allForms, 'category')));

$userId = (int) ($_REQUEST['user'] ?? 0);
// Category and Sub Menu open once a branch is chosen. One branch available is chosen for the user; with more, tick one, several or All Branch.
$branches = array_filter(sales_rows($conn, 'SELECT branch_id, branch_name FROM branches WHERE is_active=1 ORDER BY branch_name'), fn($b) => perm_branch_allowed($b['branch_id']));
$branchIds = array_map('intval', array_column($branches, 'branch_id'));
$canAllBranch = perm_branch_ids() === null && count($branchIds) > 1;
$allBranch = $canAllBranch && isset($_REQUEST['all']);
$picked = count($branchIds) === 1 ? $branchIds : array_values(array_intersect($branchIds, array_map('intval', (array) ($_REQUEST['branches'] ?? []))));
$selected = $allBranch ? [0] : $picked;
$branchReady = (bool) $selected;
$category =in_array($_REQUEST['category'] ?? '', $categories, true) ? $_REQUEST['category'] : $categories[0];
$subOn = isset($_REQUEST['use_sub']);
$subs = array_values(array_unique(array_column(array_filter($allForms, fn($f) => $f['category'] === $category), 'sub')));
$sub = in_array($_REQUEST['sub'] ?? '', $subs, true) ? $_REQUEST['sub'] : $subs[0];
$query = http_build_query(array_filter(['user' => $userId, 'branches' => $allBranch ? null : $picked, 'all' => $allBranch ? 1 : null, 'category' => $category, 'use_sub' => $subOn ? 1 : null, 'sub' => $subOn ? $sub : null], fn($v) => $v !== null));
$back = $self . '?' . $query;
$target = $userId ? sales_row($conn, 'SELECT user_id, user_name, access_level, user_type, user_role_id FROM admin_user WHERE user_id=?', 'i', [$userId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($back);
    try {
        if (!$target) {
            throw new RuntimeException('Pick a user.');
        }
        if ($target['user_type'] === 'customer' || $target['access_level'] === 'SuperAdmin') {
            throw new RuntimeException('This user\'s rights are fixed.');
        }
        if (!$iAmSuper && (int) $target['user_id'] === (int) $me['user_id']) {
            throw new RuntimeException('You cannot change your own rights.');
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action !== 'edit_copy' && !$branchReady) {
            throw new RuntimeException('Select a branch first.');
        }
        $columns = array_map(fn($a) => 'can_' . $a, array_keys(PERM_ACTIONS));

        if ($action === 'edit_copy') {
            $from = (int) ($_POST['copy_from'] ?? 0);
            if (!$iAmSuper || $from <= 0 || $from === $userId) {
                throw new RuntimeException('Pick another user to copy from.');
            }
            $list = implode(', ', $columns);
            $conn->begin_transaction();
            sales_exec($conn, 'DELETE FROM admin_user_permissions WHERE user_id=?', 'i', [$userId])->close();
            sales_exec($conn, "INSERT INTO admin_user_permissions (user_id, branch_id, form_key, $list, updated_by) SELECT ?, branch_id, form_key, $list, ? FROM admin_user_permissions WHERE user_id=?", 'iii', [$userId, (int) $me['user_id'], $from])->close();
            $conn->commit();
            sales_flash_redirect($back, 'success', 'Rights copied. Everything this user had before was replaced.');
        }

        $keys = (array) ($_POST['k'] ?? []);
        $posted = (array) ($_POST['p'] ?? []);
        $apply = (string) ($_POST['apply'] ?? 'all');
        $rows = $apply === 'all' ? $keys : array_intersect_key($keys, [(int) $apply => 1]);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $updates = implode(', ', array_map(fn($c) => "$c=VALUES($c)", $columns));
    $rows = array_filter($rows, fn($key) => isset($allForms[$key]));
    $roleRights = perm_role_rights((int) $target['user_role_id']);
    $conn->begin_transaction();
    foreach ($selected as $branchId) {
        $saved = perm_effective_rights($userId, (int) $target['user_role_id'], $branchId);
        foreach ($rows as $i => $key) {
            $allowed = PERM_PROFILES[$allForms[$key]['profile']];
            $vals = [];
            foreach (PERM_ACTIONS as $a => $label) {
                $want = in_array($a, $allowed, true) && isset($posted[$i][$a]);
                // Rights the editor does not hold stay as they were.
                $vals[] = (int) (perm_can($key, $a) ? $want : !empty($saved[$key][$a]));
            }
            // With nothing ticked the row goes, unless it has to stop the role giving this form.
            if ($branchId === 0 && !array_sum($vals) && !isset($roleRights[$key])) {
                sales_exec($conn, 'DELETE FROM admin_user_permissions WHERE user_id=? AND form_key=?', 'is', [$userId, $key])->close();
            } else {
                sales_exec($conn, "INSERT INTO admin_user_permissions (user_id, branch_id, form_key, " . implode(', ', $columns) . ", updated_by) VALUES (?, ?, ?, $placeholders, ?) ON DUPLICATE KEY UPDATE $updates, updated_by=VALUES(updated_by)",
                    'iis' . str_repeat('i', count($columns)) . 'i', [$userId, $branchId, $key, ...$vals, (int) $me['user_id']])->close();
                if ($branchId === 0) {
                    sales_exec($conn, 'DELETE FROM admin_user_permissions WHERE user_id=? AND form_key=? AND branch_id<>0', 'is', [$userId, $key])->close();
                }
            }
        }
    }
    $conn->commit();
    sales_flash_redirect($back, 'success', count($rows) . ' form(s) saved for ' . $target['user_name'] . ($allBranch ? ' in all branches.' : ' in ' . count($selected) . ' branch(es).'));
} catch (RuntimeException $e) {
    sales_flash_redirect($back, 'error', $e->getMessage());
}
}

$users = sales_rows($conn, "SELECT user_id, user_name, access_level, user_type FROM admin_user WHERE user_type<>'customer' AND user_name<>'administrator1' ORDER BY user_name");
$forms = array_filter($allForms, fn($f) => $f['category'] === $category && (!$subOn || $f['sub'] === $sub));
$rights = $target && $branchReady ? perm_effective_rights($userId, (int) $target['user_role_id'], $selected[0]) : [];
$roleName = $target ? (perm_assignable_roles()[(int) $target['user_role_id']] ?? '') : '';
$fixed = $target && ($target['access_level'] === 'SuperAdmin' || $target['user_type'] === 'customer');
$columns = array_filter(array_keys(PERM_ACTIONS), function ($a) use ($forms) {
    foreach ($forms as $f) {
        if (in_array($a, PERM_PROFILES[$f['profile']], true)) {
            return true;
        }
    }
    return false;
});

sa_page_head('User Access Control', sales_take_flash());
?>
<style>
.ua-bar{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;margin-bottom:8px}
.ua-bar label{margin:0;font-weight:bold}
.ua-bar .form-control{width:auto;display:inline-block;max-width:260px}
.ua-branches{display:flex;flex-wrap:wrap;gap:4px 14px;align-items:center}
.ua-branches label{font-weight:normal;white-space:nowrap}
.ua-branches b{margin-right:2px}
.ua-grid{max-height:640px;overflow:auto;border:1px solid #ddd}
.ua-grid table{margin:0}
.ua-grid thead td{position:sticky;top:0;background:#f7f7f7;z-index:1;text-align:center;font-weight:bold;min-width:58px;vertical-align:bottom!important}
.ua-grid thead td label{display:block;margin:0;font-weight:bold}
.ua-grid .name{white-space:nowrap;min-width:220px}
.ua-grid td.c{text-align:center}
.ua-grid .sub{color:#888;font-size:11px;display:block}
</style>
<h4 style="margin-top:0">User Access Control</h4>
<form method="get" class="panel panel-default"><div class="panel-body">
<div class="ua-bar">
<span><label for="user">UserName</label> <select class="form-control" id="user" name="user" required onchange="this.form.submit()"><option value="">- choose -</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['user_id'] ?>"<?= $userId === (int) $u['user_id'] ? ' selected' : '' ?>><?= sales_h($u['user_name'] . ' (' . ($u['user_type'] === 'staff' ? $u['access_level'] : 'Employee') . ')') ?></option><?php endforeach; ?></select></span>
</div>
<div class="ua-bar ua-branches" id="ua-branches">
<b>Access Branch Name</b>
<?php if (!$branchIds): ?><span class="muted">No branch is available. Add one in Branch &amp; Sub Branch.</span><?php endif; ?>
<?php if (count($branchIds) === 1): ?><label><input type="checkbox" checked disabled> <?= sales_h(reset($branches)['branch_name']) ?></label><?php else: ?>
<?php if ($canAllBranch): ?><label><input type="checkbox" name="all" value="1" id="ua-all"<?= $allBranch ? ' checked' : '' ?>> All Branch</label><?php endif; ?>
<?php foreach ($branches as $b): ?><label><input type="checkbox" name="branches[]" value="<?= (int) $b['branch_id'] ?>"<?= in_array((int) $b['branch_id'], $picked, true) ? ' checked' : '' ?>> <?= sales_h($b['branch_name']) ?></label><?php endforeach; ?>
<?php endif; ?>
</div>
<div class="ua-bar">
<span><label for="category">Access Category</label> <select class="form-control" id="category" name="category" data-needs-branch<?= $branchReady ? '' : ' disabled' ?> onchange="this.form.submit()"><?php foreach ($categories as $c): ?><option<?= $c === $category ? ' selected' : '' ?>><?= sales_h($c) ?></option><?php endforeach; ?></select></span>
<label><input type="checkbox" name="use_sub" value="1" data-needs-branch<?= $subOn ? ' checked' : '' ?><?= $branchReady ? '' : ' disabled' ?> onchange="this.form.submit()"> Sub Menu</label>
<?php if ($subOn): ?><select class="form-control" name="sub" aria-label="Sub Menu" data-needs-branch<?= $branchReady ? '' : ' disabled' ?> onchange="this.form.submit()"><?php foreach ($subs as $s): ?><option<?= $s === $sub ? ' selected' : '' ?>><?= sales_h($s) ?></option><?php endforeach; ?></select><?php endif; ?>
</div>
<button class="btn btn-default" data-needs-branch<?= $branchReady ? '' : ' disabled' ?>>Show</button>
</div></form>
<?php if ($branchIds): ?>
<script>
(function () {
  var box = document.getElementById('ua-branches');
  var all = document.getElementById('ua-all');
  var single = <?= count($branchIds) === 1 ? 'true' : 'false' ?>;
  function sync() {
    var ticks = box.querySelectorAll('input[name="branches[]"]');
    ticks.forEach(function (c) { c.disabled = !!(all && all.checked); if (c.disabled) c.checked = false; });
    var ready = single || (all && all.checked) || box.querySelector('input[name="branches[]"]:checked') !== null;
    document.querySelectorAll('[data-needs-branch]').forEach(function (el) { el.disabled = !ready; });
  }
  box.addEventListener('change', sync);
  sync();
})();
</script>
<?php endif; ?>

<?php if (!$target): ?>
<p class="muted">Pick a user to see their rights.</p>
<?php elseif ($fixed): ?>
<div class="alert alert-info"><?= sales_h($target['user_name']) ?> is a SuperAdmin and passes every check. There is nothing to set.</div>
<?php elseif (!$branchReady): ?>
<p class="muted">Select a branch to open Access Category and Sub Menu.</p>
<?php else: ?>
<?php if ($roleName !== '' && $roleName !== 'ERP User'): ?><p class="muted">Role: <?= sales_h($roleName) ?>. The ticks start from the role; Apply saves this user's own rights for those forms, and those replace the role for them.</p><?php endif; ?>
<?php if (count($selected) > 1): ?><div class="alert alert-warning">The ticks below show the rights in <?= sales_h(array_column($branches, 'branch_name', 'branch_id')[$selected[0]]) ?>. Apply writes them to all <?= count($selected) ?> selected branches.</div><?php endif; ?>
<?php if ($iAmSuper): ?>
<form method="post" class="ua-bar" onsubmit="return confirm('Replace all of <?= sales_h($target['user_name']) ?>\'s rights?')"><?= sales_csrf_field() ?>
<input type="hidden" name="user" value="<?= $userId ?>">
<label for="copy_from">Copy rights from</label> <select class="form-control" id="copy_from" name="copy_from"><option value="0">- choose -</option><?php foreach ($users as $u): if ((int) $u['user_id'] !== $userId && $u['access_level'] !== 'SuperAdmin'): ?><option value="<?= (int) $u['user_id'] ?>"><?= sales_h($u['user_name']) ?></option><?php endif; endforeach; ?></select>
<button class="btn btn-default btn-sm" name="action" value="edit_copy">Copy</button>
</form>
<?php endif; ?>
<form method="post" id="ua-form"><?= sales_csrf_field() ?>
<input type="hidden" name="action" value="edit_rights">
<div class="ua-grid"><table class="table table-bordered table-condensed">
<thead><tr><td>SL</td><td class="name" style="text-align:left">Form Name</td>
<?php foreach ($columns as $a): ?><td><label><input type="checkbox" data-col="<?= $a ?>" aria-label="All <?= PERM_ACTIONS[$a] ?>"><br><?= PERM_ACTIONS[$a] ?></label></td><?php endforeach; ?>
<td>All</td><td>Apply</td></tr></thead><tbody>
<?php $i = 0; foreach ($forms as $key => $f): $profile = PERM_PROFILES[$f['profile']]; ?>
<tr><td><?= $i + 1 ?></td>
<td class="name"><?= sales_h($f['name']) ?><?php if (!$subOn): ?><span class="sub"><?= sales_h($f['sub']) ?></span><?php endif; ?><input type="hidden" name="k[<?= $i ?>]" value="<?= sales_h($key) ?>"></td>
<?php foreach ($columns as $a): $on = in_array($a, $profile, true); ?>
<td class="c"><input type="checkbox" name="p[<?= $i ?>][<?= $a ?>]" data-c="<?= $a ?>" aria-label="<?= sales_h(PERM_ACTIONS[$a] . ' ' . $f['name']) ?>"<?= $on ? '' : ' disabled' ?><?= $on && !empty($rights[$key][$a]) ? ' checked' : '' ?>></td>
<?php endforeach; ?>
<td class="c"><input type="checkbox" data-row aria-label="All for <?= sales_h($f['name']) ?>"></td>
<td class="c"><button class="btn btn-default btn-xs" name="apply" value="<?= $i ?>">Apply</button></td>
</tr>
<?php $i++; endforeach; ?>
</tbody></table></div>
<button class="btn btn-primary" name="apply" value="all" style="margin-top:10px">Apply All</button>
</form>
<script>
(function () {
  var form = document.getElementById('ua-form');
  var action = form.action;
  form.action = action.split('?')[0] + '?<?= sales_h($query) ?>'.replace(/&amp;/g, '&');
  form.querySelectorAll('[data-col]').forEach(function (head) {
    head.addEventListener('change', function () {
      form.querySelectorAll('[data-c="' + head.dataset.col + '"]:not(:disabled)').forEach(function (c) { c.checked = head.checked; });
    });
  });
  form.querySelectorAll('[data-row]').forEach(function (all) {
    all.addEventListener('change', function () {
      all.closest('tr').querySelectorAll('[data-c]:not(:disabled)').forEach(function (c) { c.checked = all.checked; });
    });
  });
})();
</script>
<?php endif; ?>
<?php
sa_page_foot('Greyed boxes do not apply to that form. On Approvals, Edit means approve. Show Cost is the actual (purchase) price; Show Price is the sales price in reports.');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
