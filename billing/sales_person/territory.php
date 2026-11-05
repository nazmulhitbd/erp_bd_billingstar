<?php
/** Territory: sales areas SRs are given (e.g. Dhaka North, inside Dhaka Region), with how many SRs and customers each has. */
require_once __DIR__ . '/sales_person_common.php';

$self = 'territory.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && sp_ready($conn)) {
    $id = (int) ($_POST['territory_id'] ?? 0);
    $back = $self . ($id ? '?edit=' . $id : '');
    sales_require_csrf($back);
    try {
        $old = $id ? sales_row($conn, 'SELECT * FROM sr_territories WHERE territory_id=?', 'i', [$id]) : null;
        if ($id && !$old) {
            throw new RuntimeException('Territory not found.');
        }
        if (($_POST['action'] ?? '') === 'delete') {
            mgmt_require(sp_can($conn, 'delete'), 'deleting territories');
            if (sales_row($conn, 'SELECT 1 FROM sales_representatives WHERE territory_id=? LIMIT 1', 'i', [$id]) || sales_row($conn, 'SELECT 1 FROM sr_territories WHERE parent_id=? LIMIT 1', 'i', [$id])) {
                throw new RuntimeException($old['territory_name'] . ' has SRs or smaller territories. Make it inactive instead.');
            }
            sales_exec($conn, 'DELETE FROM sr_territories WHERE territory_id=?', 'i', [$id])->close();
            sales_flash_redirect($self, 'success', 'Territory deleted.');
        }
        mgmt_require(sp_can($conn, $old ? 'edit' : 'add'), 'saving territories');
        $name = sales_post_string('territory_name', 120);
        if ($name === '') {
            throw new RuntimeException('Enter the territory name.');
        }
        if (sales_row($conn, 'SELECT 1 FROM sr_territories WHERE territory_name=? AND territory_id<>?', 'si', [$name, $id])) {
            throw new RuntimeException('Territory "' . $name . '" already exists.');
        }
        $parent = (int) ($_POST['parent_id'] ?? 0);
        // A territory cannot sit inside itself or inside one of its own sub-territories.
        for ($p = $parent; $p > 0; $p = (int) (sales_row($conn, 'SELECT parent_id FROM sr_territories WHERE territory_id=?', 'i', [$p])['parent_id'] ?? 0)) {
            if ($id && $p === $id) {
                throw new RuntimeException('A territory cannot be inside itself.');
            }
        }
        $cols = ['territory_code' => sales_post_string('territory_code', 20), 'territory_name' => $name, 'region' => sales_post_string('region', 120), 'parent_id' => $parent ?: null,
            'description' => sales_post_string('description', 255), 'is_active' => !empty($_POST['is_active']) ? 1 : 0];
        $types = implode('', array_map(fn($v) => is_int($v) ? 'i' : 's', $cols));
        if ($old) {
            sales_exec($conn, 'UPDATE sr_territories SET ' . implode(', ', array_map(fn($k) => "$k=?", array_keys($cols))) . ' WHERE territory_id=?', $types . 'i', array_merge(array_values($cols), [$id]))->close();
        } else {
            sales_insert($conn, 'INSERT INTO sr_territories (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', $types, array_values($cols));
        }
        sales_flash_redirect($self, 'success', $name . ' saved.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
sp_page_start($conn, 'Territory', 'territory');
echo sales_flash_html($flash);
$edit = isset($_GET['edit']) ? sales_row($conn, 'SELECT * FROM sr_territories WHERE territory_id=?', 'i', [(int) $_GET['edit']]) : null;
$rows = sales_rows($conn, 'SELECT t.*, COALESCE(p.territory_name, \'\') AS parent_name, (SELECT COUNT(*) FROM sales_representatives s WHERE s.territory_id=t.territory_id) AS srs, '
        . '(SELECT COUNT(*) FROM trader_sr_assignments a JOIN sales_representatives s ON s.sr_id=a.sr_id WHERE s.territory_id=t.territory_id AND a.effective_to IS NULL) AS customers '
        . 'FROM sr_territories t LEFT JOIN sr_territories p ON p.territory_id=t.parent_id ORDER BY t.region, t.territory_name');
$v = fn($k, $d = '') => sales_h($edit[$k] ?? $d);
?>
<form method="post" class="section product-form" style="margin-top:0"><?= sales_csrf_field() ?><input type="hidden" name="territory_id" value="<?= (int) ($edit['territory_id'] ?? 0) ?>"><div class="hgrid">
<div class="field s2"><label for="territory_code">Code</label><input id="territory_code" name="territory_code" maxlength="20" value="<?= $v('territory_code') ?>"></div>
<div class="field s4"><label for="territory_name">Territory Name *</label><input id="territory_name" name="territory_name" required maxlength="120" value="<?= $v('territory_name') ?>"></div>
<div class="field s2"><label for="region">Region</label><input id="region" name="region" maxlength="120" value="<?= $v('region') ?>"></div>
<div class="field s2"><label for="parent_id">Part Of</label><select id="parent_id" name="parent_id"><option value="0">—</option><?php foreach ($rows as $t): if ((int) $t['territory_id'] === (int) ($edit['territory_id'] ?? 0)) { continue; } ?><option value="<?= (int) $t['territory_id'] ?>"<?= (int) ($edit['parent_id'] ?? 0) === (int) $t['territory_id'] ? ' selected' : '' ?>><?= sales_h($t['territory_name']) ?></option><?php endforeach; ?></select></div>
<div class="field s2"><label>&nbsp;</label><label><input class="check" type="checkbox" name="is_active" value="1"<?= (int) ($edit['is_active'] ?? 1) ? ' checked' : '' ?>> Active</label></div>
<div class="field s6"><label for="description">Description</label><input id="description" name="description" maxlength="255" value="<?= $v('description') ?>"></div>
<div class="field s4"><label>&nbsp;</label><div class="inline"><button class="btn primary"<?= sp_can($conn, $edit ? 'edit' : 'add') ? '' : ' disabled' ?>><?= $edit ? 'Save' : 'Add' ?></button><?php if ($edit): ?><a class="btn" href="<?= $self ?>">New</a><?php endif; ?></div></div>
</div></form>
<div class="table-scroll" style="max-height:600px;margin-top:8px"><table><thead><tr><th>SL</th><th>Code</th><th>Territory</th><th>Region</th><th>Part Of</th><th class="num">SRs</th><th class="num">Customers</th><th>Status</th><th>Edit</th><th>Delete</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="10" class="empty">No territories yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $t): ?><tr><td><?= $i + 1 ?></td><td><?= sales_h($t['territory_code']) ?></td><td><?= sales_h($t['territory_name']) ?></td><td><?= sales_h($t['region']) ?></td><td><?= sales_h($t['parent_name']) ?></td>
<td class="num"><?= (int) $t['srs'] ?></td><td class="num"><?= (int) $t['customers'] ?></td><td><?= (int) $t['is_active'] ? 'Active' : 'Inactive' ?></td><td><a class="btn small" href="<?= $self ?>?edit=<?= (int) $t['territory_id'] ?>">Edit</a></td>
<td><form method="post" onsubmit="return confirm('Delete this territory?')"><?= sales_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="territory_id" value="<?= (int) $t['territory_id'] ?>"><button class="btn small danger">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
