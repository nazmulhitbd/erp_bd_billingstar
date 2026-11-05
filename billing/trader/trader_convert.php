<?php
/**
 * Trader Convert: move a trader to another category (for example Cash Customer to Credit Customer,
 * or Supplier to Dealer). The trader code gets the new category prefix and the change is logged.
 */
require_once __DIR__ . '/trader_common.php';

$self = 'trader_convert.php';
$ready = trader_schema_ready($conn);
$categories = $ready ? trader_categories($conn) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $traderId = (int) ($_POST['trader_id'] ?? 0);
    $back = $self . '?id=' . $traderId;
    sales_require_csrf($back);
    if (!$ready) {
        sales_flash_redirect($self, 'error', 'Trader tables are missing. Import billing/trader/schema.sql first.');
    }
    try {
        $newCat = (int) ($_POST['new_category_id'] ?? 0);
        if (!isset($categories[$newCat])) {
            throw new RuntimeException('Pick the new category.');
        }
        $reason = sales_post_string('reason', 255);
        if ($reason === '') {
            throw new RuntimeException('Enter the reason for the conversion.');
        }
        $conn->begin_transaction();
        $t = sales_row($conn, 'SELECT * FROM traders WHERE trader_id=? FOR UPDATE', 'i', [$traderId]);
        if (!$t) {
            throw new RuntimeException('Trader not found.');
        }
        if ((int) $t['category_id'] === $newCat) {
            throw new RuntimeException('The trader is already a ' . $categories[$newCat]['category_name'] . '.');
        }
        $newGroup = trader_group_id($conn, $newCat, $_POST['new_group_name'] ?? '', !empty($_POST['create_group']));
        $oldCode = (string) $t['trader_code'];
        sales_exec($conn, 'UPDATE traders SET category_id=?, group_id=? WHERE trader_id=?', 'iii', [$newCat, $newGroup, $traderId])->close();
        $newCode = trader_set_code($conn, $traderId);
        sales_insert(
            $conn,
            'INSERT INTO trader_conversions (trader_id, old_category_id, new_category_id, old_group_id, new_group_id, old_code, new_code, reason, converted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'iiiiisssi',
            [$traderId, (int) $t['category_id'], $newCat, $t['group_id'] !== null ? (int) $t['group_id'] : null, $newGroup, $oldCode, $newCode, $reason, (int) ($GLOBALS['user_id'] ?? 0)]
        );
        // Credit flags only make sense for credit trading; turn credit on when converting to Credit Customer.
        if ($categories[$newCat]['category_name'] === 'Credit Customer') {
            sales_exec($conn, 'INSERT INTO trader_flags (trader_id, allow_credit, updated_by) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE allow_credit=1, updated_by=VALUES(updated_by)', 'ii', [$traderId, (int) ($GLOBALS['user_id'] ?? 0)])->close();
        }
        trader_sync($conn, $traderId);
        $conn->commit();
        sales_flash_redirect($back, 'success', $t['trader_name'] . ' converted from ' . $categories[(int) $t['category_id']]['category_name'] . ' to ' . $categories[$newCat]['category_name'] . '. New code ' . $newCode . '.');
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$id = (int) ($_GET['id'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$trader = null;
$matches = $history = $recent = $groups = [];
if ($ready) {
    if ($id > 0) {
        $trader = sales_row($conn, 'SELECT t.*, c.category_name, COALESCE(g.group_name, \'\') AS group_name FROM traders t JOIN trader_categories c ON c.category_id=t.category_id LEFT JOIN trader_groups g ON g.group_id=t.group_id WHERE t.trader_id=?', 'i', [$id]);
        $history = sales_rows($conn, 'SELECT v.*, oc.category_name AS old_cat, nc.category_name AS new_cat, og.group_name AS old_group, ng.group_name AS new_group FROM trader_conversions v JOIN trader_categories oc ON oc.category_id=v.old_category_id JOIN trader_categories nc ON nc.category_id=v.new_category_id LEFT JOIN trader_groups og ON og.group_id=v.old_group_id LEFT JOIN trader_groups ng ON ng.group_id=v.new_group_id WHERE v.trader_id=? ORDER BY v.conversion_id DESC', 'i', [$id]);
    } elseif ($q !== '') {
        $like = '%' . $q . '%';
        $matches = sales_rows($conn, 'SELECT t.trader_id, t.trader_code, t.trader_name, t.mobile, c.category_name FROM traders t JOIN trader_categories c ON c.category_id=t.category_id WHERE t.trader_name LIKE ? OR t.trader_code LIKE ? OR t.mobile LIKE ? ORDER BY t.trader_name LIMIT 100', 'sss', [$like, $like, $like]);
    }
    if (!$trader) {
        $recent = sales_rows($conn, 'SELECT v.converted_at, v.old_code, v.new_code, v.reason, t.trader_id, t.trader_name, oc.category_name AS old_cat, nc.category_name AS new_cat FROM trader_conversions v JOIN traders t ON t.trader_id=v.trader_id JOIN trader_categories oc ON oc.category_id=v.old_category_id JOIN trader_categories nc ON nc.category_id=v.new_category_id ORDER BY v.conversion_id DESC LIMIT 50');
    }
    $groups = trader_groups($conn, 0, true);
}

trader_page_start('Trader Convert', 'convert');
echo sales_flash_html($flash);
if (!$ready) {
    echo trader_missing_html();
    trader_page_end();
    exit;
}
?>
<fieldset class="box"><legend>Trader Convert</legend>
<form method="get" class="list-search"><input class="input" name="q" value="<?= sales_h($q) ?>" placeholder="Trader name, code or mobile" autofocus><button class="btn primary">Find Trader</button><?php if ($trader): ?><a class="btn" href="<?= $self ?>">Pick another</a><?php endif; ?></form>
<?php if ($matches): ?>
<div class="table-scroll"><table><thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Mobile</th><th></th></tr></thead><tbody>
<?php foreach ($matches as $m): ?><tr><td><?= sales_h($m['trader_code']) ?></td><td><?= sales_h($m['trader_name']) ?></td><td><?= sales_h($m['category_name']) ?></td><td><?= sales_h($m['mobile']) ?></td><td><a class="btn small primary" href="<?= $self ?>?id=<?= (int) $m['trader_id'] ?>">Select</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif ($q !== '' && !$trader): ?><div class="alert error">No trader matches "<?= sales_h($q) ?>".</div><?php endif; ?>

<?php if ($trader): ?>
<div class="summary"><div>Code<b><?= sales_h($trader['trader_code']) ?></b></div><div>Name<b><?= sales_h($trader['trader_name']) ?></b></div><div>Current Category<b><?= sales_h($trader['category_name']) ?></b></div><div>Current Group<b><?= sales_h($trader['group_name'] ?: '-') ?></b></div><div>Mobile<b><?= sales_h($trader['mobile']) ?></b></div></div>
<form method="post" class="section" autocomplete="off"><?= sales_csrf_field() ?><input type="hidden" name="trader_id" value="<?= (int) $trader['trader_id'] ?>">
<div class="grid g4" style="margin-top:0">
<div class="field"><label for="new_category_id">Convert To</label><select id="new_category_id" name="new_category_id" required><option value="">Pick category</option><?php foreach ($categories as $cid => $c): if ($cid === (int) $trader['category_id']) { continue; } ?><option value="<?= $cid ?>"><?= sales_h($c['category_name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="new_group_name">New Group</label><input id="new_group_name" name="new_group_name" list="new-groups" maxlength="120" placeholder="Leave blank for no group"><datalist id="new-groups"></datalist></div>
<div class="field"><label>&nbsp;</label><label><input class="check" type="checkbox" name="create_group" value="1"> Create the group if it does not exist</label></div>
<div class="field"><label for="reason">Reason</label><input id="reason" name="reason" maxlength="255" required placeholder="Why the trader is converted"></div>
</div>
<div class="toolbar" style="margin-top:10px"><button class="btn primary" onclick="return confirm('Convert this trader? The trader code will change.')">Convert</button><a class="btn" href="trader_info.php?cat=<?= (int) $trader['category_id'] ?>&id=<?= (int) $trader['trader_id'] ?>">Open trader</a></div>
<p class="footnote">Sales and RMA records keep their history. A customer-type trader gets a sales customer copy, and a supplier-type trader gets an RMA supplier copy.</p>
</form>
<div class="section"><div class="section-title">Conversion history</div><table><thead><tr><th>Date</th><th>From</th><th>To</th><th>Old Code</th><th>New Code</th><th>Reason</th></tr></thead><tbody>
<?php if (!$history): ?><tr><td colspan="6" class="empty">Never converted.</td></tr><?php endif; ?>
<?php foreach ($history as $h): ?><tr><td><?= sales_h($h['converted_at']) ?></td><td><?= sales_h($h['old_cat'] . ($h['old_group'] ? ' / ' . $h['old_group'] : '')) ?></td><td><?= sales_h($h['new_cat'] . ($h['new_group'] ? ' / ' . $h['new_group'] : '')) ?></td><td><?= sales_h($h['old_code']) ?></td><td><?= sales_h($h['new_code']) ?></td><td><?= sales_h($h['reason']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif (!$matches): ?>
<div class="section-title" style="margin-top:12px">Recent conversions</div>
<div class="table-scroll"><table><thead><tr><th>Date</th><th>Trader</th><th>From</th><th>To</th><th>Old Code</th><th>New Code</th><th>Reason</th></tr></thead><tbody>
<?php if (!$recent): ?><tr><td colspan="7" class="empty">No conversions yet. Find a trader above.</td></tr><?php endif; ?>
<?php foreach ($recent as $r): ?><tr><td><?= sales_h($r['converted_at']) ?></td><td><a href="<?= $self ?>?id=<?= (int) $r['trader_id'] ?>"><?= sales_h($r['trader_name']) ?></a></td><td><?= sales_h($r['old_cat']) ?></td><td><?= sales_h($r['new_cat']) ?></td><td><?= sales_h($r['old_code']) ?></td><td><?= sales_h($r['new_code']) ?></td><td><?= sales_h($r['reason']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</fieldset>
<?php
$script = $trader ? 'const groups=' . sales_js($groups) . ';const sel=document.getElementById("new_category_id"),dl=document.getElementById("new-groups");
sel.addEventListener("change",()=>{dl.innerHTML="";groups.filter(g=>String(g.category_id)===sel.value).forEach(g=>{const o=document.createElement("option");o.value=g.group_name;dl.appendChild(o)});document.getElementById("new_group_name").value=""});' : '';
trader_page_end($script);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
