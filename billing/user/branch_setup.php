<?php
/**
 * Branch & Sub Branch: add a branch, or a sub-branch under another one. Access to a branch includes its sub-branches.
 * A branch is never deleted, because documents point to it; make it inactive instead.
 */
require_once dirname(__DIR__) . '/store_admin/sa_list.php';

$self = 'branch_setup.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['branch_id'] ?? 0);
    $back = $self . ($id ? '?edit=' . $id : '');
    sales_require_csrf($back);
    try {
        $name = sales_post_string('branch_name', 120);
        $parent = (int) ($_POST['parent_branch_id'] ?? 0);
        if ($name === '') {
            throw new RuntimeException('Enter the branch name.');
        }
        if ($id && in_array($parent, perm_with_sub_branches([$id]), true)) {
            throw new RuntimeException('A branch cannot sit under itself or under its own sub-branch.');
        }
        if (sales_row($conn, 'SELECT 1 AS x FROM branches WHERE branch_name=? AND branch_id<>?', 'si', [$name, $id])) {
            throw new RuntimeException('That branch name is already used.');
        }
        $active = isset($_POST['is_active']) ? 1 : 0;
        // Package limit: a new active branch, or one switched back on, counts as one more.
        $was = $id ? sales_row($conn, 'SELECT is_active FROM branches WHERE branch_id=?', 'i', [$id]) : null;
        if ($active && !($was && (int) $was['is_active'] === 1) && ($limit = licence_limit_error('branches')) !== '') {
            throw new RuntimeException($limit);
        }
        if ($id) {
            sales_exec($conn, 'UPDATE branches SET branch_name=?, parent_branch_id=?, address=?, is_active=? WHERE branch_id=?', 'sisii', [$name, $parent ?: null, sales_post_string('address', 255), $active, $id])->close();
        } else {
            sales_insert($conn, 'INSERT INTO branches (branch_name, parent_branch_id, address, is_active) VALUES (?, ?, ?, ?)', 'sisi', [$name, $parent ?: null, sales_post_string('address', 255), $active]);
        }
        sales_flash_redirect($self, 'success', 'Branch saved.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$rows = sales_rows($conn, 'SELECT b.*, p.branch_name AS parent_name FROM branches b LEFT JOIN branches p ON p.branch_id=b.parent_branch_id ORDER BY COALESCE(p.branch_name, b.branch_name), b.parent_branch_id IS NOT NULL, b.branch_name');
$edit = isset($_GET['edit']) ? sales_row($conn, 'SELECT * FROM branches WHERE branch_id=?', 'i', [(int) $_GET['edit']]) : null;
sa_page_head('Branch & Sub Branch', sales_take_flash());
?>
<div class="panel panel-default"><div class="panel-body">
<h4 style="margin-top:0"><?= $edit ? 'Edit Branch' : 'Add Branch' ?></h4>
<form method="post" class="search-bar"><?= sales_csrf_field() ?>
<input type="hidden" name="branch_id" value="<?= (int) ($edit['branch_id'] ?? 0) ?>">
<input class="form-control" name="branch_name" maxlength="120" required placeholder="Branch name" aria-label="Branch name" value="<?= sales_h($edit['branch_name'] ?? '') ?>">
<select class="form-control" name="parent_branch_id" aria-label="Sub-branch of"><option value="0">Main branch</option><?php foreach ($rows as $r): if ((int) $r['branch_id'] !== (int) ($edit['branch_id'] ?? 0)): ?><option value="<?= (int) $r['branch_id'] ?>"<?= (int) ($edit['parent_branch_id'] ?? 0) === (int) $r['branch_id'] ? ' selected' : '' ?>>Sub-branch of <?= sales_h($r['branch_name']) ?></option><?php endif; endforeach; ?></select>
<input class="form-control" name="address" maxlength="255" placeholder="Address" aria-label="Address" value="<?= sales_h($edit['address'] ?? '') ?>">
<label style="font-weight:normal"><input type="checkbox" name="is_active"<?= (int) ($edit['is_active'] ?? 1) ? ' checked' : '' ?>> Active</label>
<button class="btn btn-primary">&#187; Save</button>
<?php if ($edit): ?><a class="btn btn-link" href="<?= $self ?>">Close</a><?php endif; ?>
</form>
</div></div>
<div class="box-body"><table class="table table-bordered table-hover">
<thead><tr class="WUIrsheader"><td class="WUIrsheaderel">Nr</td><td class="WUIrsheaderel">Branch</td><td class="WUIrsheaderel">Under</td><td class="WUIrsheaderel">Address</td><td class="WUIrsheaderel">Status</td><td class="WUIrsheaderel">Edit</td></tr></thead>
<tbody>
<?php foreach ($rows as $i => $r): ?>
<tr class="<?= $i % 2 ? 'WUIrsrecordrowodd' : 'WUIrsrecordroweven' ?>">
<td class="WUIfield"><?= $i + 1 ?>.</td>
<td class="WUIfield"><?= $r['parent_branch_id'] ? '&nbsp;&nbsp;&#8627; ' : '' ?><?= sales_h($r['branch_name']) ?></td>
<td class="WUIfield"><?= sales_h($r['parent_name']) ?></td>
<td class="WUIfield"><?= sales_h($r['address']) ?></td>
<td class="WUIfield"><?= (int) $r['is_active'] ? '<span class="label label-success">Active</span>' : '<span class="label label-danger">Inactive</span>' ?></td>
<td class="WUIfield" style="text-align:center"><a class="btn btn-default btn-xs" href="<?= $self ?>?edit=<?= (int) $r['branch_id'] ?>">Edit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php
sa_page_foot();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
