<?php
/**
 * Role & Permission: roles with their rights on every top menu, sub menu and form. The rows come from billing/menu_catalog.php,
 * so a page added to the menu appears here. Give a role to a user on Add New User; User Access Control can still change
 * one person's rights. Administrator stays the SuperAdmin role and ERP User means "no role", so neither is edited here.
 * The ticks post as one JSON field, because a role can hold more boxes than PHP's max_input_vars lets through.
 * Layout and behaviour: role_access.css, role_access.js. The page has its own look, so it starts with class ui-common,
 * which tells topmain.php not to add ui-common.css to it.
 */
require_once dirname(__DIR__) . '/store_admin/sa_list.php';

$self = 'role_access.php';
$me = perm_user();
$allForms = perm_forms();
$actionKeys = array_keys(PERM_ACTIONS);
$canAct = fn($form, $a) => in_array($a, PERM_PROFILES[$form['profile']], true);
$roles = sales_rows($conn, "SELECT r.user_role_id, r.role_name, r.description, COUNT(u.user_id) AS users, (SELECT COUNT(*) FROM admin_role_permissions p WHERE p.user_role_id=r.user_role_id) AS forms
    FROM admin_role r LEFT JOIN admin_user u ON u.user_role_id=r.user_role_id WHERE r.id_reseller IS NULL AND r.role_name NOT IN ('ERP User', 'Default Role for VSR') GROUP BY r.user_role_id ORDER BY r.role_name");
$editable = fn($role) => $role && !in_array($role['role_name'], PERM_SYSTEM_ROLES, true);

/** Options a form shows without opening More, by kind of form. The rest sit under RA_MORE. */
const RA_MAIN = ['page' => ['view'], 'setup' => ['view', 'add', 'edit', 'delete'], 'approve' => ['view', 'edit'], 'report' => ['view', 'preview', 'print', 'show_cost', 'show_price'],
    'voucher' => ['view', 'add', 'edit', 'delete'], 'doc' => ['view', 'add', 'edit', 'delete']];
const RA_MORE = ['Amounts' => ['edit_price', 'discount', 'vat', 'ait', 'extra_charges'], 'Details' => ['additional', 'serial_list', 'show_cost', 'show_price'],
    'Print' => ['preview', 'print', 'print_before_app'], 'After approval and dates' => ['edit_after_app', 'edit_after_print', 'edit_back_date', 'advance_date']];

/** Copy of the ERP User role's older columns, so a new role opens the older pages' own checks in the same way. */
function role_create(mysqli $db, $name, $description, $by)
{
    $skip = ['user_role_id', 'role_name', 'description', 'addby', 'id_reseller', 'reseller_level'];
    $cols = array_values(array_diff(array_column(sales_rows($db, 'SHOW COLUMNS FROM admin_role'), 'Field'), $skip));
    $list = implode(', ', array_map(fn($c) => '`' . $c . '`', $cols));
    sales_exec($db, "INSERT INTO admin_role (role_name, description, addby, $list) SELECT ?, ?, ?, $list FROM admin_role WHERE role_name='ERP User'", 'sss', [$name, $description, $by])->close();
    return (int) $db->insert_id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['user_role_id'] ?? 0);
    $back = $self . ($id ? '?edit=' . $id : '?add=1');
    sales_require_csrf($back);
    $action = (string) ($_POST['action'] ?? '');
    try {
        $old = $id ? sales_row($conn, 'SELECT * FROM admin_role WHERE user_role_id=?', 'i', [$id]) : null;
        if ($id && (!$old || !$editable($old) || $old['role_name'] === 'ERP User')) {
            throw new RuntimeException('This role cannot be changed here.');
        }
        if ($action === 'delete') {
            if (sales_row($conn, 'SELECT 1 AS x FROM admin_user WHERE user_role_id=?', 'i', [$id])) {
                throw new RuntimeException('Move the users of this role to another role first.');
            }
            sales_exec($conn, 'DELETE FROM admin_role WHERE user_role_id=?', 'i', [$id])->close();
            sales_flash_redirect($self, 'success', 'Role deleted.');
        }
        $name = sales_post_string('role_name', 50);
        if ($name === '') {
            throw new RuntimeException('Enter the role name.');
        }
        if (sales_row($conn, 'SELECT 1 AS x FROM admin_role WHERE role_name=? AND user_role_id<>?', 'si', [$name, $id])) {
            throw new RuntimeException('That role name is already used.');
        }
        $posted = json_decode((string) ($_POST['rights_json'] ?? '{}'), true);
        if (!is_array($posted)) {
            throw new RuntimeException('The ticks did not arrive. Please try again.');
        }
        $saved = $id ? perm_role_rights($id) : [];
        $columns = array_map(fn($a) => 'can_' . $a, $actionKeys);
        $conn->begin_transaction();
        if ($id) {
            sales_exec($conn, 'UPDATE admin_role SET role_name=?, description=? WHERE user_role_id=?', 'ssi', [$name, sales_post_string('description', 150), $id])->close();
        } else {
            $id = role_create($conn, $name, sales_post_string('description', 150), (string) $me['user_name']);
        }
        sales_exec($conn, 'DELETE FROM admin_role_permissions WHERE user_role_id=?', 'i', [$id])->close();
        $sql = 'INSERT INTO admin_role_permissions (user_role_id, form_key, ' . implode(', ', $columns) . ', updated_by) VALUES (?, ?, ' . implode(', ', array_fill(0, count($columns), '?')) . ', ?)';
        $n = 0;
        foreach ($allForms as $key => $form) {
            $vals = [];
            foreach ($actionKeys as $a) {
                $want = $canAct($form, $a) && in_array($a, (array) ($posted[$key] ?? []), true);
                // Rights the editor does not hold stay as they were.
                $vals[] = (int) (perm_can($key, $a) ? $want : !empty($saved[$key][$a]));
            }
            if (array_sum($vals)) {
                sales_exec($conn, $sql, 'is' . str_repeat('i', count($columns)) . 'i', [$id, $key, ...$vals, (int) $me['user_id']])->close();
                $n++;
            }
        }
        $conn->commit();
        sales_flash_redirect($self . '?edit=' . $id, 'success', 'Role saved with ' . $n . ' form(s).');
    } catch (RuntimeException $e) {
        $conn->rollback();
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$edit = isset($_GET['edit']) ? sales_row($conn, 'SELECT * FROM admin_role WHERE user_role_id=?', 'i', [(int) $_GET['edit']]) : null;
$edit = $editable($edit) && $edit['role_name'] !== 'ERP User' ? $edit : null;
$showForm = $edit || isset($_GET['add']);
$rights = $edit ? perm_role_rights((int) $edit['user_role_id']) : [];
$tree = [];
foreach ($allForms as $key => $form) {
    $tree[$form['category']][$form['sub']][$key] = $form;
}

$flash = sales_take_flash();
$icons = array_column(menu_catalog(), 'icon', 'label');
$label = fn($a, $profile) => $a === 'edit' && $profile === 'approve' ? 'Approve' : PERM_ACTIONS[$a];
$copyFrom = [];
foreach ($roles as $r) {
    if ($editable($r) && (int) $r['forms'] > 0 && (int) $r['user_role_id'] !== (int) ($edit['user_role_id'] ?? 0)) {
        $copyFrom[$r['role_name']] = array_map(fn($actions) => array_keys(array_filter($actions)), perm_role_rights((int) $r['user_role_id']));
    }
}
?>
<!doctype html>
<html lang="en" class="ui-common">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Role &amp; Permission</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="role_access.css?v=<?= (int) @filemtime(__DIR__ . '/role_access.css') ?>">
</head>
<body>
<?= access_tabs() ?>
<?php if ($flash): ?><div class="ra-flash <?= $flash['kind'] === 'success' ? 'ok' : 'err' ?>" role="status"><i class="bi bi-<?= $flash['kind'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i> <?= sales_h($flash['message']) ?></div><?php endif; ?>

<?php if ($showForm): ?>
<form method="post" id="ra-form" class="ra-app" data-copy="<?= sales_h(json_encode($copyFrom)) ?>"><?= sales_csrf_field() ?>
<input type="hidden" name="user_role_id" value="<?= (int) ($edit['user_role_id'] ?? 0) ?>">
<input type="hidden" name="rights_json" id="rights_json" value="{}">

<header class="ra-top">
  <a class="ra-back" href="<?= $self ?>"><i class="bi bi-arrow-left"></i> Roles</a>
  <div class="ra-field"><label for="role_name">Role name</label><input id="role_name" name="role_name" maxlength="50" required placeholder="e.g. Sales Operator" value="<?= sales_h($edit['role_name'] ?? '') ?>"></div>
  <div class="ra-field grow"><label for="description">Description</label><input id="description" name="description" maxlength="150" placeholder="What this role is for" value="<?= sales_h($edit['description'] ?? '') ?>"></div>
<?php if ($copyFrom): ?>
  <div class="ra-field"><label for="ra-copy">Start from</label><select id="ra-copy"><option value="">Choose a role…</option><?php foreach ($copyFrom as $name => $x): ?><option><?= sales_h($name) ?></option><?php endforeach; ?></select></div>
<?php endif; ?>
</header>

<div class="ra-tools">
  <div class="ra-search"><i class="bi bi-search"></i><input type="search" id="ra-search" placeholder="Find a form…" aria-label="Find a form" autocomplete="off"></div>
  <label class="ra-switch"><input type="checkbox" id="ra-granted"> <span>Only forms in this role</span></label>
  <span class="ra-total" id="ra-total" aria-live="polite"></span>
</div>

<div class="ra-body">
<nav class="ra-nav" aria-label="Menus">
<?php $n = 0; foreach ($tree as $category => $subs): ?>
  <button type="button" class="ra-nav-item<?= $n === 0 ? ' active' : '' ?>" data-panel="p<?= $n ?>"<?= $n === 0 ? ' aria-current="true"' : '' ?>><i class="bi bi-<?= sales_h($icons[$category] ?? 'grid') ?>"></i><span class="name"><?= sales_h($category) ?></span><span class="count" data-count="p<?= $n ?>"></span></button>
<?php $n++; endforeach; ?>
</nav>

<div class="ra-main" id="ra-main">
<p class="ra-none" id="ra-none" hidden>No forms match.</p>
<?php $n = 0; foreach ($tree as $category => $subs): ?>
<section class="ra-panel" id="p<?= $n ?>"<?= $n === 0 ? '' : ' hidden' ?> aria-label="<?= sales_h($category) ?>">
  <div class="ra-panel-head">
    <h2><i class="bi bi-<?= sales_h($icons[$category] ?? 'grid') ?>"></i> <?= sales_h($category) ?></h2>
    <div class="ra-presets"><span>Set all:</span><button type="button" data-preset="view">View only</button><button type="button" data-preset="full">Full access</button><button type="button" data-preset="none">None</button></div>
  </div>
<?php foreach ($subs as $sub => $forms):
$profile = reset($forms)['profile'];
$main = RA_MAIN[$profile];
$more = array_diff(PERM_PROFILES[$profile], $main); ?>
  <div class="ra-section">
    <?php if ($sub !== $category): ?><h3><button type="button" class="ra-fold" aria-expanded="true"><i class="bi bi-chevron-down"></i> <?= sales_h($sub) ?></button><span class="ra-sub-count"></span></h3><?php endif; ?>
    <table class="ra-table">
    <thead><tr><th scope="col" class="form">Form</th>
<?php foreach ($main as $a): ?><th scope="col"><label><input type="checkbox" data-col="<?= $a ?>" aria-label="All forms: <?= sales_h($label($a, $profile)) ?>"><span><?= sales_h($label($a, $profile)) ?></span></label></th><?php endforeach; ?>
    <th scope="col" class="more"><?= $more ? 'Options' : '' ?></th></tr></thead>
    <tbody>
<?php foreach ($forms as $key => $form): $k = sales_h($key); $moreId = 'm' . substr(md5($key), 0, 10); ?>
    <tr class="ra-row" data-name="<?= sales_h(strtolower($form['name'] . ' ' . $sub . ' ' . $category)) ?>"><th scope="row" class="form"><?= sales_h($form['name']) ?></th>
<?php foreach ($main as $a): ?><td data-label="<?= sales_h($label($a, $profile)) ?>"><input type="checkbox" data-f="<?= $k ?>" data-a="<?= $a ?>" aria-label="<?= sales_h($label($a, $profile) . ': ' . $form['name']) ?>"<?= !empty($rights[$key][$a]) ? ' checked' : '' ?>></td><?php endforeach; ?>
    <td class="more"><?php if ($more): ?><button type="button" class="ra-more" aria-expanded="false" aria-controls="<?= $moreId ?>">More <span class="n"></span> <i class="bi bi-chevron-down"></i></button><?php endif; ?></td></tr>
<?php if ($more): ?>
    <tr class="ra-opts" id="<?= $moreId ?>" hidden><td colspan="<?= count($main) + 2 ?>"><div class="ra-groups">
<?php foreach (RA_MORE as $group => $actions): $actions = array_intersect($actions, $more); if ($actions): ?>
      <fieldset><legend><?= sales_h($group) ?></legend><?php foreach ($actions as $a): ?><label class="ra-chip"><input type="checkbox" data-f="<?= $k ?>" data-a="<?= $a ?>"<?= !empty($rights[$key][$a]) ? ' checked' : '' ?>><span><?= sales_h(PERM_ACTIONS[$a]) ?></span></label><?php endforeach; ?></fieldset>
<?php endif; endforeach; ?>
    </div></td></tr>
<?php endif; endforeach; ?>
    </tbody></table>
  </div>
<?php endforeach; ?>
</section>
<?php $n++; endforeach; ?>
</div>
</div>

<footer class="ra-bar">
  <span class="ra-state" id="ra-state">No changes</span>
  <button type="button" class="ra-btn" id="ra-discard" disabled>Discard</button>
  <button class="ra-btn primary" name="action" value="<?= $edit ? 'edit' : 'add' ?>"><?= $edit ? 'Save role' : 'Create role' ?></button>
</footer>
</form>

<?php else: ?>
<div class="ra-list">
  <header class="ra-list-head">
    <div><h1>Role &amp; Permission</h1><p>A role is a set of menus and rights. Give it to a user on Add New User.</p></div>
    <a class="ra-btn primary" href="<?= $self ?>?add=1"><i class="bi bi-plus-lg"></i> Add Role</a>
  </header>
<?php if (count($roles) > 6): ?><div class="ra-search wide"><i class="bi bi-search"></i><input type="search" id="ra-role-search" placeholder="Find a role…" aria-label="Find a role" autocomplete="off"></div><?php endif; ?>
  <div class="ra-cards" id="ra-cards">
<?php foreach ($roles as $r): $open = $editable($r); $users = (int) $r['users']; ?>
    <article class="ra-card" data-name="<?= sales_h(strtolower($r['role_name'])) ?>">
      <div class="ra-card-top"><span class="ra-mark<?= $open ? '' : ' full' ?>"><i class="bi bi-<?= $open ? 'person-badge' : 'shield-check' ?>"></i></span><div><h2><?= sales_h($r['role_name']) ?></h2><p><?= sales_h($r['description'] ?: 'No description') ?></p></div></div>
      <dl><div><dt>Forms</dt><dd><?= $open ? (int) $r['forms'] : 'All' ?></dd></div><div><dt>Users</dt><dd><?= $users ?></dd></div></dl>
      <div class="ra-card-foot">
<?php if ($open): ?>
        <a class="ra-btn" href="<?= $self ?>?edit=<?= (int) $r['user_role_id'] ?>"><i class="bi bi-pencil"></i> Edit</a>
<?php if ($users): ?><span class="ra-hint">Move its users to delete</span><?php else: ?>
        <form method="post" data-confirm="Delete the role <?= sales_h($r['role_name']) ?>?"><?= sales_csrf_field() ?><input type="hidden" name="user_role_id" value="<?= (int) $r['user_role_id'] ?>"><button class="ra-btn danger" name="action" value="delete"><i class="bi bi-trash"></i> Delete</button></form>
<?php endif; ?>
<?php else: ?><span class="ra-hint"><i class="bi bi-lock"></i> SuperAdmin role, always full access</span><?php endif; ?>
      </div>
    </article>
<?php endforeach; ?>
    <a class="ra-card add" href="<?= $self ?>?add=1"><i class="bi bi-plus-circle"></i><span>Create a role</span></a>
  </div>
  <p class="ra-none" id="ra-role-none" hidden>No roles match.</p>
</div>
<?php endif; ?>
<script src="role_access.js?v=<?= (int) @filemtime(__DIR__ . '/role_access.js') ?>"></script>
</body>
</html>
<?php include "../../addon/conf/connection_close.php";  ?>
