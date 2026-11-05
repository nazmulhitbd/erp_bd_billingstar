<?php
/** Trader wise SR Fixing: keep a list of sales representatives and assign one SR to each trader, with history. */
require_once __DIR__ . '/trader_common.php';

$self = 'trader_sr.php';
$ready = trader_schema_ready($conn);
$categories = $ready ? trader_categories($conn) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $self . '?' . http_build_query(array_intersect_key($_POST, array_flip(['cat', 'group', 'q', 'unassigned'])));
    sales_require_csrf($back);
    if (!$ready) {
        sales_flash_redirect($self, 'error', 'Trader tables are missing. Import billing/trader/schema.sql first.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    try {
        if ($action === 'sr_add' || $action === 'sr_edit') {
            $srId = (int) ($_POST['sr_id'] ?? 0);
            $name = sales_post_string('sr_name', 140);
            if ($name === '') {
                throw new RuntimeException('Enter the SR name.');
            }
            $code = sales_post_string('sr_code', 30);
            $code = $code === '' ? null : $code;
            if ($code !== null && sales_row($conn, 'SELECT 1 FROM sales_representatives WHERE sr_code=? AND sr_id<>?', 'si', [$code, $action === 'sr_edit' ? $srId : 0])) {
                throw new RuntimeException('SR code ' . $code . ' is already used.');
            }
            $values = [$code, $name, sales_post_string('mobile', 40), sales_post_string('designation', 80), !empty($_POST['is_active']) ? 1 : 0];
            if ($action === 'sr_edit') {
                if (!sales_row($conn, 'SELECT 1 FROM sales_representatives WHERE sr_id=?', 'i', [$srId])) {
                    throw new RuntimeException('Select an SR to edit.');
                }
                sales_exec($conn, 'UPDATE sales_representatives SET sr_code=?, sr_name=?, mobile=?, designation=?, is_active=? WHERE sr_id=?', 'ssssii', array_merge($values, [$srId]))->close();
                sales_flash_redirect($back, 'success', 'SR ' . $name . ' updated.');
            }
            sales_insert($conn, 'INSERT INTO sales_representatives (sr_code, sr_name, mobile, designation, is_active) VALUES (?, ?, ?, ?, ?)', 'ssssi', $values);
            sales_flash_redirect($back, 'success', 'SR ' . $name . ' added.');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['trader_ids'] ?? [])))));
        if (!$ids) {
            throw new RuntimeException('Tick at least one trader.');
        }
        $date = sales_valid_date($_POST['effective_from'] ?? '');
        if ($date === null) {
            throw new RuntimeException('Enter a valid effective date.');
        }
        $conn->begin_transaction();
        $current = trader_current_sr_map($conn, $ids);
        $changed = 0;
        if ($action === 'assign') {
            $srId = (int) ($_POST['assign_sr_id'] ?? 0);
            $sr = sales_row($conn, 'SELECT sr_name FROM sales_representatives WHERE sr_id=? AND is_active=1', 'i', [$srId]);
            if (!$sr) {
                throw new RuntimeException('Pick an active SR to assign.');
            }
            foreach ($ids as $traderId) {
                $now = $current[$traderId] ?? null;
                if ($now && (int) $now['sr_id'] === $srId) {
                    continue;
                }
                if ($now && $date < $now['effective_from']) {
                    throw new RuntimeException('The effective date is before the current SR started (' . $now['effective_from'] . ') for one of the traders.');
                }
                sales_exec($conn, 'UPDATE trader_sr_assignments SET effective_to=? WHERE trader_id=? AND effective_to IS NULL', 'si', [$date, $traderId])->close();
                sales_insert($conn, 'INSERT INTO trader_sr_assignments (trader_id, sr_id, effective_from, assigned_by) VALUES (?, ?, ?, ?)', 'iisi', [$traderId, $srId, $date, $userId]);
                $changed++;
            }
            $message = $sr['sr_name'] . ' assigned to ' . $changed . ' trader(s).';
        } elseif ($action === 'unassign') {
            foreach ($ids as $traderId) {
                if (!isset($current[$traderId])) {
                    continue;
                }
                sales_exec($conn, 'UPDATE trader_sr_assignments SET effective_to=? WHERE trader_id=? AND effective_to IS NULL', 'si', [max($date, $current[$traderId]['effective_from']), $traderId])->close();
                $changed++;
            }
            $message = 'SR removed from ' . $changed . ' trader(s).';
        } else {
            throw new RuntimeException('Unknown action.');
        }
        $conn->commit();
        sales_flash_redirect($back, 'success', $message);
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$cat = (int) ($_GET['cat'] ?? 0);
$groupId = (int) ($_GET['group'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$unassigned = !empty($_GET['unassigned']);
$historyId = (int) ($_GET['history'] ?? 0);
$editSr = null;
$srs = $traders = $groups = $history = [];
$srMap = [];
if ($ready) {
    if (!empty($_GET['sr'])) {
        $editSr = sales_row($conn, 'SELECT * FROM sales_representatives WHERE sr_id=?', 'i', [(int) $_GET['sr']]);
    }
    $srs = sales_rows($conn, 'SELECT s.*, (SELECT COUNT(*) FROM trader_sr_assignments a WHERE a.sr_id=s.sr_id AND a.effective_to IS NULL) AS traders FROM sales_representatives s ORDER BY s.is_active DESC, s.sr_name');
    $srs = array_values(array_filter($srs, fn($s) => empty($s['staff_only'])));
    $groups = trader_groups($conn, $cat);
    $where = ['1=1'];
    $types = '';
    $params = [];
    if ($cat > 0) { $where[] = 't.category_id=?'; $types .= 'i'; $params[] = $cat; }
    if ($groupId > 0) { $where[] = 't.group_id=?'; $types .= 'i'; $params[] = $groupId; }
    if ($q !== '') { $where[] = '(t.trader_name LIKE ? OR t.trader_code LIKE ? OR t.mobile LIKE ?)'; $types .= 'sss'; array_push($params, "%$q%", "%$q%", "%$q%"); }
    if ($unassigned) { $where[] = 'NOT EXISTS (SELECT 1 FROM trader_sr_assignments a WHERE a.trader_id=t.trader_id AND a.effective_to IS NULL)'; }
    $traders = sales_list_rows($conn, 'SELECT t.trader_id, t.trader_code, t.trader_name, t.mobile, c.category_name, COALESCE(g.group_name, \'\') AS group_name FROM traders t JOIN trader_categories c ON c.category_id=t.category_id LEFT JOIN trader_groups g ON g.group_id=t.group_id WHERE t.is_active=1 AND ' . implode(' AND ', $where) . ' ORDER BY c.sort_order, t.trader_name', $types, $params);
    $srMap = trader_current_sr_map($conn, array_column($traders, 'trader_id'));
    if ($historyId > 0) {
        $history = sales_rows($conn, 'SELECT a.*, s.sr_name, t.trader_name FROM trader_sr_assignments a JOIN sales_representatives s ON s.sr_id=a.sr_id JOIN traders t ON t.trader_id=a.trader_id WHERE a.trader_id=? ORDER BY a.effective_from DESC, a.assignment_id DESC', 'i', [$historyId]);
    }
}
$keep = ['cat' => $cat, 'group' => $groupId, 'q' => $q, 'unassigned' => $unassigned ? 1 : ''];

trader_page_start('Trader wise SR Fixing', 'sr');
echo sales_flash_html($flash);
if (!$ready) {
    echo trader_missing_html();
    trader_page_end();
    exit;
}
?>
<fieldset class="box"><legend>Sales Representative (SR)</legend>
<form method="post" autocomplete="off"><?= sales_csrf_field() ?><input type="hidden" name="sr_id" value="<?= (int) ($editSr['sr_id'] ?? 0) ?>">
<?php foreach ($keep as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= sales_h($v) ?>"><?php endforeach; ?>
<div class="grid g5" style="margin-top:0">
<div class="field"><label for="sr_code">SR Code</label><input id="sr_code" name="sr_code" maxlength="30" value="<?= sales_h($editSr['sr_code'] ?? '') ?>"></div>
<div class="field"><label for="sr_name">SR Name</label><input id="sr_name" name="sr_name" maxlength="140" required value="<?= sales_h($editSr['sr_name'] ?? '') ?>"></div>
<div class="field"><label for="sr_mobile">Mobile</label><input id="sr_mobile" name="mobile" maxlength="40" value="<?= sales_h($editSr['mobile'] ?? '') ?>"></div>
<div class="field"><label for="designation">Designation</label><input id="designation" name="designation" maxlength="80" value="<?= sales_h($editSr['designation'] ?? '') ?>"></div>
<div class="field"><label>&nbsp;</label><label><input class="check" type="checkbox" name="is_active" value="1" <?= !$editSr || (int) $editSr['is_active'] ? 'checked' : '' ?>> Active</label></div>
</div>
<div class="toolbar" style="margin-top:8px"><button class="btn primary" name="action" value="sr_add">Add SR</button><button class="btn" name="action" value="sr_edit" <?= $editSr ? '' : 'disabled' ?>>Edit SR</button><a class="btn" href="<?= $self ?>?<?= http_build_query($keep) ?>">Clear</a></div>
</form>
<div class="table-scroll" style="max-height:200px"><table><thead><tr><th>Code</th><th>SR Name</th><th>Mobile</th><th>Designation</th><th>Active</th><th class="num">Traders</th><th></th></tr></thead><tbody>
<?php if (!$srs): ?><tr><td colspan="7" class="empty">No SR yet. Add one above.</td></tr><?php endif; ?>
<?php foreach ($srs as $s): ?><tr><td><?= sales_h($s['sr_code']) ?></td><td><?= sales_h($s['sr_name']) ?></td><td><?= sales_h($s['mobile']) ?></td><td><?= sales_h($s['designation']) ?></td><td><?= (int) $s['is_active'] ? 'Yes' : 'No' ?></td><td class="num"><?= (int) $s['traders'] ?></td><td><a class="btn small" href="<?= $self ?>?<?= http_build_query($keep + ['sr' => (int) $s['sr_id']]) ?>">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div>
</fieldset>

<fieldset class="box" style="margin-top:14px"><legend>Trader wise SR Fixing</legend>
<form method="get" class="list-search">
<select class="input" name="cat" style="max-width:190px" onchange="this.form.group.value='';this.form.submit()"><?= trader_category_options($categories, $cat, true) ?></select>
<select class="input" name="group" style="max-width:190px"><option value="0">All groups</option><?php foreach ($groups as $g): ?><option value="<?= (int) $g['group_id'] ?>" <?= (int) $g['group_id'] === $groupId ? 'selected' : '' ?>><?= sales_h($g['group_name']) ?></option><?php endforeach; ?></select>
<input class="input" name="q" value="<?= sales_h($q) ?>" placeholder="Name, code or mobile">
<label style="align-self:center"><input class="check" type="checkbox" name="unassigned" value="1" <?= $unassigned ? 'checked' : '' ?>> Only without SR</label>
<button class="btn primary">Filter</button></form>
<form method="post" id="assign-form"><?= sales_csrf_field() ?>
<?php foreach ($keep as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= sales_h($v) ?>"><?php endforeach; ?>
<div class="list-search">
<select class="input" name="assign_sr_id" style="max-width:240px"><option value="">Pick SR</option><?php foreach ($srs as $s): if (!(int) $s['is_active']) { continue; } ?><option value="<?= (int) $s['sr_id'] ?>"><?= sales_h($s['sr_name'] . ($s['sr_code'] ? ' (' . $s['sr_code'] . ')' : '')) ?></option><?php endforeach; ?></select>
<input class="input" type="date" name="effective_from" value="<?= date('Y-m-d') ?>" style="max-width:170px" required>
<button class="btn primary" name="action" value="assign">Assign to ticked</button>
<button class="btn danger" name="action" value="unassign" onclick="return confirm('Remove the SR from the ticked traders?')">Remove SR</button>
</div>
<div class="table-scroll"><table><thead><tr><th><input class="check" type="checkbox" id="tick-all" title="Tick all"></th><th>Code</th><th>Trader</th><th>Category</th><th>Group</th><th>Mobile</th><th>Current SR</th><th>Since</th><th></th></tr></thead><tbody>
<?php if (!$traders): ?><tr><td colspan="9" class="empty">No active traders match.</td></tr><?php endif; ?>
<?php foreach ($traders as $t): $id = (int) $t['trader_id']; $now = $srMap[$id] ?? null; ?><tr>
<td><input class="check" type="checkbox" name="trader_ids[]" value="<?= $id ?>"></td><td><?= sales_h($t['trader_code']) ?></td><td><?= sales_h($t['trader_name']) ?></td><td><?= sales_h($t['category_name']) ?></td><td><?= sales_h($t['group_name']) ?></td><td><?= sales_h($t['mobile']) ?></td>
<td><?= $now ? sales_h($now['sr_name']) : '<span class="muted">None</span>' ?></td><td><?= sales_h($now['effective_from'] ?? '') ?></td>
<td><a class="btn small" href="<?= $self ?>?<?= http_build_query($keep + ['history' => $id]) ?>#history">History</a></td></tr><?php endforeach; ?>
</tbody></table></div>
</form>
<?php if ($historyId > 0): ?>
<div class="section" id="history"><div class="section-title">SR history<?= $history ? ' for ' . sales_h($history[0]['trader_name']) : '' ?></div>
<table><thead><tr><th>SR</th><th>From</th><th>To</th><th>Saved</th></tr></thead><tbody>
<?php if (!$history): ?><tr><td colspan="4" class="empty">No SR has been assigned.</td></tr><?php endif; ?>
<?php foreach ($history as $h): ?><tr><td><?= sales_h($h['sr_name']) ?></td><td><?= sales_h($h['effective_from']) ?></td><td><?= $h['effective_to'] ? sales_h($h['effective_to']) : '<b>Current</b>' ?></td><td><?= sales_h($h['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</fieldset>
<?php
trader_page_end('document.getElementById("tick-all")?.addEventListener("change",e=>document.querySelectorAll("input[name=\'trader_ids[]\']").forEach(c=>c.checked=e.target.checked));');
?>
<?php include "../../addon/conf/connection_close.php";  ?>
