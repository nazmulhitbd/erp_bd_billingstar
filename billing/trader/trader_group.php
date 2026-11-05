<?php
/** Trader Group: groups inside each trader category (Supplier, Dealer, Credit Customer, ...). */
require_once __DIR__ . '/trader_common.php';

$self = 'trader_group.php';
$ready = trader_schema_ready($conn);
$categories = $ready ? trader_categories($conn) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    if (!$ready) {
        sales_flash_redirect($self, 'error', 'Trader tables are missing. Import billing/trader/schema.sql first.');
    }
    $action = (string) ($_POST['action'] ?? 'add');
    $groupId = (int) ($_POST['group_id'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $back = $self . '?cat=' . $categoryId;
    try {
        if ($action === 'delete') {
            $group = sales_row($conn, 'SELECT g.group_name, (SELECT COUNT(*) FROM traders t WHERE t.group_id=g.group_id) AS used FROM trader_groups g WHERE g.group_id=?', 'i', [$groupId]);
            if (!$group) {
                throw new RuntimeException('Group not found.');
            }
            if ((int) $group['used'] > 0) {
                throw new RuntimeException('Group "' . $group['group_name'] . '" has ' . (int) $group['used'] . ' trader(s). Move them or make the group inactive instead.');
            }
            sales_exec($conn, 'DELETE FROM trader_groups WHERE group_id=?', 'i', [$groupId])->close();
            sales_flash_redirect($back, 'success', 'Group "' . $group['group_name'] . '" deleted.');
        }
        if (!isset($categories[$categoryId])) {
            throw new RuntimeException('Pick a category.');
        }
        $name = sales_post_string('group_name', 120);
        if ($name === '') {
            throw new RuntimeException('Enter a group name.');
        }
        $active = !empty($_POST['is_active']) ? 1 : 0;
        $dupe = sales_row($conn, 'SELECT group_id FROM trader_groups WHERE category_id=? AND group_name=? AND group_id<>?', 'isi', [$categoryId, $name, $action === 'edit' ? $groupId : 0]);
        if ($dupe) {
            throw new RuntimeException('Group "' . $name . '" already exists in ' . $categories[$categoryId]['category_name'] . '.');
        }
        if ($action === 'edit') {
            $old = sales_row($conn, 'SELECT category_id FROM trader_groups WHERE group_id=?', 'i', [$groupId]);
            if (!$old) {
                throw new RuntimeException('Select a group to edit first.');
            }
            if ((int) $old['category_id'] !== $categoryId && sales_row($conn, 'SELECT 1 FROM traders WHERE group_id=? LIMIT 1', 'i', [$groupId])) {
                throw new RuntimeException('This group has traders, so its category cannot change. Use Trader Convert to move traders.');
            }
            $conn->begin_transaction();
            sales_exec($conn, 'UPDATE trader_groups SET category_id=?, group_name=?, is_active=? WHERE group_id=?', 'isii', [$categoryId, $name, $active, $groupId])->close();
            // Keep the group name on synced sales/RMA copies current.
            foreach (sales_rows($conn, 'SELECT trader_id FROM traders WHERE group_id=?', 'i', [$groupId]) as $t) {
                trader_sync($conn, (int) $t['trader_id']);
            }
            $conn->commit();
            sales_flash_redirect($back, 'success', 'Group "' . $name . '" updated.');
        }
        sales_insert($conn, 'INSERT INTO trader_groups (category_id, group_name, is_active, created_by) VALUES (?, ?, ?, ?)', 'isii', [$categoryId, $name, $active, (int) ($GLOBALS['user_id'] ?? 0)]);
        $return = (string) ($_POST['return_to'] ?? '');
        if (preg_match('/^trader_info\.php\?cat=\d+$/', $return)) {
            sales_flash_redirect($return . '&group=' . urlencode($name), 'success', 'Group "' . $name . '" added.');
        }
        sales_flash_redirect($back, 'success', 'Group "' . $name . '" added.');
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$filterCat = (int) ($_GET['cat'] ?? 0);
$editId = (int) ($_GET['id'] ?? 0);
$returnTo = (string) ($_GET['return'] ?? '');
$returnTo = preg_match('/^trader_info\.php\?cat=\d+$/', $returnTo) ? $returnTo : '';
$editing = null;
$groups = [];
if ($ready) {
    if ($editId > 0) {
        $editing = sales_row($conn, 'SELECT * FROM trader_groups WHERE group_id=?', 'i', [$editId]);
    }
    $sql = 'SELECT g.group_id, g.group_name, g.is_active, c.category_name, (SELECT COUNT(*) FROM traders t WHERE t.group_id=g.group_id) AS traders FROM trader_groups g JOIN trader_categories c ON c.category_id=g.category_id';
    $groups = $filterCat > 0
        ? sales_rows($conn, $sql . ' WHERE g.category_id=? ORDER BY g.group_name', 'i', [$filterCat])
        : sales_rows($conn, $sql . ' ORDER BY c.sort_order, g.group_name');
}
$formCat = $editing ? (int) $editing['category_id'] : $filterCat;

trader_page_start('Trader Group', 'group');
echo sales_flash_html($flash);
if (!$ready) {
    echo trader_missing_html();
    trader_page_end();
    exit;
}
?>
<fieldset class="box"><legend>Trader Group</legend>
<form method="post" autocomplete="off" class="section" style="margin-top:0">
<?= sales_csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) ($editing['group_id'] ?? 0) ?>"><input type="hidden" name="return_to" value="<?= sales_h($returnTo) ?>">
<div class="grid g2" style="margin-top:0">
<div class="field"><label for="category_id">Category Name</label><select id="category_id" name="category_id" required><option value="">Category Name</option><?= trader_category_options($categories, $formCat) ?></select></div>
<div class="field"><label for="group_name">Group Name</label><input id="group_name" name="group_name" maxlength="120" placeholder="Trader Group Name" value="<?= sales_h($editing['group_name'] ?? '') ?>" required autofocus></div>
<div class="field"><label><input class="check" type="checkbox" name="is_active" value="1" <?= !$editing || (int) $editing['is_active'] === 1 ? 'checked' : '' ?>> Active</label></div>
</div>
<div class="toolbar" style="margin-top:10px">
<button class="btn primary" name="action" value="add">Add</button>
<button class="btn" name="action" value="edit" <?= $editing ? '' : 'disabled title="Select a group first"' ?>>Edit</button>
<a class="btn" href="<?= $self ?>?cat=<?= $filterCat ?>">Clear</a>
<?php if ($returnTo): ?><a class="btn" href="<?= sales_h($returnTo) ?>">Back to trader</a><?php endif; ?>
</div>
</form>
<form method="get" class="list-search" style="margin-top:10px"><select class="input" name="cat" onchange="this.form.submit()"><?= trader_category_options($categories, $filterCat, true) ?></select></form>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Group ID</th><th>Category</th><th>Group Name</th><th>Active</th><th class="num">Traders</th><th>Select</th><th>Edit</th><th>Delete</th></tr></thead><tbody>
<?php if (!$groups): ?><tr><td colspan="9" class="empty">No groups yet.</td></tr><?php endif; ?>
<?php foreach ($groups as $i => $g): ?><tr class="<?= (int) $g['group_id'] === $editId ? 'selected-row' : '' ?>">
<td><?= $i + 1 ?></td><td><?= (int) $g['group_id'] ?></td><td><?= sales_h($g['category_name']) ?></td><td><?= sales_h($g['group_name']) ?></td><td><?= (int) $g['is_active'] ? 'Yes' : 'No' ?></td><td class="num"><?= (int) $g['traders'] ?></td>
<td><a class="btn small" href="trader_search.php?group=<?= (int) $g['group_id'] ?>">Select</a></td>
<td><a class="btn small" href="<?= $self ?>?cat=<?= $filterCat ?>&id=<?= (int) $g['group_id'] ?>">Edit</a></td>
<td><form method="post" onsubmit="return confirm('Delete group <?= sales_h(addslashes($g['group_name'])) ?>?')"><?= sales_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="group_id" value="<?= (int) $g['group_id'] ?>"><input type="hidden" name="category_id" value="<?= $filterCat ?>"><button class="btn small danger">Delete</button></form></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</fieldset>
<?php
trader_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
