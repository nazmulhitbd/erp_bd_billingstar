<?php
require_once __DIR__ . '/rma_common.php';

$page = 'problem.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rma_require_csrf($page);
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['problem_id'] ?? 0);
    $name = rma_post('problem_name', 190);
    $active = !empty($_POST['is_active']) ? 1 : 0;
    try {
        if ($action === 'add' || $action === 'edit') {
            if ($name === '') {
                throw new RuntimeException('Enter a problem name.');
            }
            $dup = rma_row('SELECT problem_id FROM rma_problems WHERE problem_name=? AND problem_id<>?', 'si', [$name, $id]);
            if ($dup) {
                throw new RuntimeException('"' . $name . '" already exists.');
            }
        }
        if ($action === 'add') {
            rma_exec('INSERT INTO rma_problems (problem_name, is_active, created_by) VALUES (?, ?, ?)', 'sii', [$name, $active, rma_user_id()])->close();
            rma_flash('success', 'Problem added.');
        } elseif ($action === 'edit' && $id > 0) {
            rma_exec('UPDATE rma_problems SET problem_name=?, is_active=? WHERE problem_id=?', 'sii', [$name, $active, $id])->close();
            rma_flash('success', 'Problem updated.');
        } elseif ($action === 'delete' && $id > 0) {
            // Complain lines keep the problem text, so deleting the master record is safe.
            rma_exec('DELETE FROM rma_problems WHERE problem_id=?', 'i', [$id])->close();
            rma_flash('success', 'Problem deleted.');
        } else {
            throw new RuntimeException('Select a problem first.');
        }
    } catch (Throwable $error) {
        rma_flash('error', $error->getMessage());
    }
    rma_redirect($page);
}

$rows = rma_schema_ready() ? rma_rows('SELECT problem_id, problem_name, is_active FROM rma_problems ORDER BY problem_id') : [];
rma_page_header('Problem');
?>
<form method="post" id="problem-form" autocomplete="off">
<?= rma_csrf_field() ?>
<input type="hidden" name="action" id="p-action">
<div class="rma-grid">
  <div class="rma-field w5"><label for="p-id">Problem ID</label><input id="p-id" name="problem_id" class="ro" readonly tabindex="-1"></div>
  <div class="rma-field w7"><label for="p-name">Problem Name</label><input id="p-name" name="problem_name" maxlength="190"></div>
  <div class="rma-field w12 rma-radios"><label for="p-active">Active <input type="checkbox" id="p-active" name="is_active" value="1" checked></label></div>
</div>
<div class="rma-toolbar">
  <button type="button" class="rma-btn" data-p="add">Add</button>
  <button type="button" class="rma-btn" data-p="edit">Edit</button>
  <button type="button" class="rma-btn" data-p="delete">Delete</button>
  <a class="rma-btn" href="<?= rma_h(rma_home_url()) ?>">Close</a>
</div>
</form>
<div class="rma-grid-wrap"><table class="rma-table">
<thead><tr><th>SL</th><th>Problem ID</th><th>Problem Name</th><th class="center">Active</th><th>Select</th><th>Edit</th><th>Delete</th></tr></thead>
<tbody>
<?php if (!$rows): ?><tr><td colspan="7" class="empty">No problems added yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): ?>
<tr data-row='<?= rma_h(json_encode($r)) ?>'>
  <td><?= $i + 1 ?></td><td><?= (int) $r['problem_id'] ?></td><td><?= rma_h($r['problem_name']) ?></td>
  <td class="center"><input type="checkbox" disabled <?= $r['is_active'] ? 'checked' : '' ?>></td>
  <td><button type="button" class="rma-btn" data-row-act="select">Select</button></td>
  <td><button type="button" class="rma-btn" data-row-act="edit">Edit</button></td>
  <td><button type="button" class="rma-btn" data-row-act="delete">Delete</button></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php
rma_page_footer(<<<'JS'
const pf = document.getElementById('problem-form');
const pid = document.getElementById('p-id'), pname = document.getElementById('p-name'), pactive = document.getElementById('p-active');
function pSubmit(action) { document.getElementById('p-action').value = action; pf.submit(); }
function pLoad(row) { pid.value = row.problem_id; pname.value = row.problem_name; pactive.checked = row.is_active == 1; pname.focus(); }
document.addEventListener('click', e => {
  const b = e.target.closest('[data-p]');
  if (b) {
    const a = b.dataset.p;
    if (a === 'add') { if (!pname.value.trim()) { RMA.alert('Enter a problem name.'); return; } pid.value = ''; pSubmit('add'); }
    if (a === 'edit') { if (!pid.value) { RMA.alert('Select a problem to edit.'); return; } pSubmit('edit'); }
    if (a === 'delete') { if (!pid.value) { RMA.alert('Select a problem to delete.'); return; } RMA.confirm('Are you sure want to delete this record?', () => pSubmit('delete')); }
    return;
  }
  const r = e.target.closest('[data-row-act]');
  if (!r) return;
  const row = JSON.parse(r.closest('tr').dataset.row);
  pLoad(row);
  if (r.dataset.rowAct === 'delete') RMA.confirm('Are you sure want to delete this record?', () => pSubmit('delete'));
});
JS);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
