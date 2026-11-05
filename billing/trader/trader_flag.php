<?php
/**
 * Trading wise Trader Flag Fixing: credit permission, credit limit and days, price level,
 * special discount, VAT exemption and sales/purchase blocks for each trader.
 */
require_once __DIR__ . '/trader_common.php';

$self = 'trader_flag.php';
$ready = trader_schema_ready($conn);
$categories = $ready ? trader_categories($conn) : [];
const TRADER_PRICE_LEVELS = ['retail' => 'Retail', 'wholesale' => 'Wholesale', 'dealer' => 'Dealer', 'special' => 'Special'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $self . '?' . http_build_query(array_intersect_key($_POST, array_flip(['cat', 'group', 'q'])));
    sales_require_csrf($back);
    if (!$ready) {
        sales_flash_redirect($self, 'error', 'Trader tables are missing. Import billing/trader/schema.sql first.');
    }
    $rows = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['rows'] ?? [])))));
    $flags = is_array($_POST['flag'] ?? null) ? $_POST['flag'] : [];
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    try {
        if (!$rows) {
            throw new RuntimeException('There is nothing to save.');
        }
        $conn->begin_transaction();
        $saved = 0;
        foreach ($rows as $traderId) {
            $f = is_array($flags[$traderId] ?? null) ? $flags[$traderId] : [];
            $name = sales_row($conn, 'SELECT trader_name FROM traders WHERE trader_id=?', 'i', [$traderId]);
            if (!$name) {
                continue;
            }
            $allowCredit = !empty($f['allow_credit']) ? 1 : 0;
            $limit = sales_amount($f['credit_limit'] ?? 0);
            $days = min(3650, max(0, (int) ($f['credit_days'] ?? 0)));
            $level = array_key_exists((string) ($f['price_level'] ?? ''), TRADER_PRICE_LEVELS) ? (string) $f['price_level'] : 'retail';
            $discount = sales_amount($f['special_discount_pct'] ?? 0);
            if ($discount > 100) {
                throw new RuntimeException('Special discount for ' . $name['trader_name'] . ' cannot be more than 100%.');
            }
            if (!$allowCredit && ($limit > 0 || $days > 0)) {
                throw new RuntimeException('Tick Credit for ' . $name['trader_name'] . ' before setting a credit limit or days.');
            }
            sales_exec(
                $conn,
                'INSERT INTO trader_flags (trader_id, allow_credit, credit_limit, credit_days, price_level, special_discount_pct, vat_exempt, block_sales, block_purchase, flag_note, updated_by) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE allow_credit=VALUES(allow_credit), credit_limit=VALUES(credit_limit), credit_days=VALUES(credit_days), '
                    . 'price_level=VALUES(price_level), special_discount_pct=VALUES(special_discount_pct), vat_exempt=VALUES(vat_exempt), block_sales=VALUES(block_sales), '
                    . 'block_purchase=VALUES(block_purchase), flag_note=VALUES(flag_note), updated_by=VALUES(updated_by)',
                'iidisdiiisi',
                [$traderId, $allowCredit, $limit, $days, $level, $discount, !empty($f['vat_exempt']) ? 1 : 0, !empty($f['block_sales']) ? 1 : 0, !empty($f['block_purchase']) ? 1 : 0, sales_str($f['flag_note'] ?? '', 255), $userId]
            )->close();
            $saved++;
        }
        $conn->commit();
        sales_flash_redirect($back, 'success', 'Flags saved for ' . $saved . ' trader(s).');
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
$cat = (int) ($_GET['cat'] ?? 0);
$groupId = (int) ($_GET['group'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$traders = $groups = [];
if ($ready) {
    $groups = trader_groups($conn, $cat);
    $where = ['1=1'];
    $types = '';
    $params = [];
    if ($cat > 0) { $where[] = 't.category_id=?'; $types .= 'i'; $params[] = $cat; }
    if ($groupId > 0) { $where[] = 't.group_id=?'; $types .= 'i'; $params[] = $groupId; }
    if ($q !== '') { $where[] = '(t.trader_name LIKE ? OR t.trader_code LIKE ? OR t.mobile LIKE ?)'; $types .= 'sss'; array_push($params, "%$q%", "%$q%", "%$q%"); }
    $traders = sales_list_rows(
        $conn,
        'SELECT t.trader_id, t.trader_code, t.trader_name, t.is_active, c.category_name, COALESCE(g.group_name, \'\') AS group_name, '
            . 'COALESCE(f.allow_credit,0) AS allow_credit, COALESCE(f.credit_limit,0) AS credit_limit, COALESCE(f.credit_days,0) AS credit_days, COALESCE(f.price_level,\'retail\') AS price_level, '
            . 'COALESCE(f.special_discount_pct,0) AS special_discount_pct, COALESCE(f.vat_exempt,0) AS vat_exempt, COALESCE(f.block_sales,0) AS block_sales, COALESCE(f.block_purchase,0) AS block_purchase, COALESCE(f.flag_note,\'\') AS flag_note '
            . 'FROM traders t JOIN trader_categories c ON c.category_id=t.category_id LEFT JOIN trader_groups g ON g.group_id=t.group_id LEFT JOIN trader_flags f ON f.trader_id=t.trader_id '
            . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY c.sort_order, t.trader_name',
        $types,
        $params
    );
}
$keep = ['cat' => $cat, 'group' => $groupId, 'q' => $q];

trader_page_start('Trader Flag Fixing', 'flag');
echo sales_flash_html($flash);
if (!$ready) {
    echo trader_missing_html();
    trader_page_end();
    exit;
}
?>
<fieldset class="box"><legend>Trading wise Trader Flag Fixing</legend>
<form method="get" class="list-search">
<select class="input" name="cat" style="max-width:190px" onchange="this.form.group.value='0';this.form.submit()"><?= trader_category_options($categories, $cat, true) ?></select>
<select class="input" name="group" style="max-width:190px"><option value="0">All groups</option><?php foreach ($groups as $g): ?><option value="<?= (int) $g['group_id'] ?>" <?= (int) $g['group_id'] === $groupId ? 'selected' : '' ?>><?= sales_h($g['group_name']) ?></option><?php endforeach; ?></select>
<input class="input" name="q" value="<?= sales_h($q) ?>" placeholder="Name, code or mobile"><button class="btn primary">Filter</button></form>
<form method="post"><?= sales_csrf_field() ?>
<?php foreach ($keep as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= sales_h($v) ?>"><?php endforeach; ?>
<div class="table-scroll" style="max-height:560px"><table><thead><tr><th>Code</th><th>Trader</th><th>Category / Group</th><th>Credit</th><th>Credit Limit</th><th>Credit Days</th><th>Price Level</th><th>Special Disc. %</th><th>VAT Exempt</th><th>Block Sales</th><th>Block Purchase</th><th>Note</th></tr></thead><tbody>
<?php if (!$traders): ?><tr><td colspan="12" class="empty">No traders match.</td></tr><?php endif; ?>
<?php foreach ($traders as $t): $id = (int) $t['trader_id']; $n = 'flag[' . $id . ']'; ?><tr<?= (int) $t['is_active'] ? '' : ' style="opacity:.6" title="Inactive trader"' ?>>
<td><input type="hidden" name="rows[]" value="<?= $id ?>"><?= sales_h($t['trader_code']) ?></td><td><?= sales_h($t['trader_name']) ?></td><td><?= sales_h($t['category_name']) ?><br><span class="muted"><?= sales_h($t['group_name']) ?></span></td>
<td><input class="check" type="checkbox" name="<?= $n ?>[allow_credit]" value="1" <?= (int) $t['allow_credit'] ? 'checked' : '' ?>></td>
<td><input type="number" name="<?= $n ?>[credit_limit]" min="0" step="0.01" value="<?= sales_h($t['credit_limit']) ?>" style="width:110px"></td>
<td><input type="number" name="<?= $n ?>[credit_days]" min="0" max="3650" step="1" value="<?= (int) $t['credit_days'] ?>" style="width:80px"></td>
<td><select name="<?= $n ?>[price_level]"><?php foreach (TRADER_PRICE_LEVELS as $k => $label): ?><option value="<?= $k ?>" <?= $t['price_level'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></td>
<td><input type="number" name="<?= $n ?>[special_discount_pct]" min="0" max="100" step="0.01" value="<?= sales_h($t['special_discount_pct']) ?>" style="width:80px"></td>
<td><input class="check" type="checkbox" name="<?= $n ?>[vat_exempt]" value="1" <?= (int) $t['vat_exempt'] ? 'checked' : '' ?>></td>
<td><input class="check" type="checkbox" name="<?= $n ?>[block_sales]" value="1" <?= (int) $t['block_sales'] ? 'checked' : '' ?>></td>
<td><input class="check" type="checkbox" name="<?= $n ?>[block_purchase]" value="1" <?= (int) $t['block_purchase'] ? 'checked' : '' ?>></td>
<td><input name="<?= $n ?>[flag_note]" maxlength="255" value="<?= sales_h($t['flag_note']) ?>"></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($traders): ?><div class="toolbar" style="margin-top:10px"><button class="btn primary">Save Flags</button><span class="footnote">Saves every row shown. Up to 300 rows per page, so filter to narrow the list.</span></div><?php endif; ?>
</form>
</fieldset>
<?php
trader_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
