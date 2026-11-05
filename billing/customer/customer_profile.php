<?php
/**
 * Customer Profile: add or edit a customer's details, SR and credit rules. Saving writes the customer-type trader and
 * syncs it to sales_customers, so the sales screens see the change at once. A customer first created on a sales
 * screen (no profile yet) gets its profile here, keeping the same customer record and its history.
 */
require_once __DIR__ . '/customer_common.php';

$self = 'customer_profile.php';
$id = (int) ($_REQUEST['id'] ?? 0);
$cats = cm_categories($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && cm_ready($conn)) {
    $back = $self . ($id ? '?id=' . $id : '');
    sales_require_csrf($back);
    $c = $id ? cm_customer($conn, $id) : null;
    try {
        if ($id && !$c) {
            throw new RuntimeException('Customer not found.');
        }
        $conn->begin_transaction();
        if (($_POST['action'] ?? '') === 'delete') {
            mgmt_require(cm_can($conn, 'delete'), 'deleting customers');
            $used = (int) sales_row($conn, 'SELECT COUNT(*) AS n FROM sales_invoices WHERE customer_id=?', 'i', [$id])['n']
                + (int) (sales_tables_ready($conn, ['sales_orders']) ? sales_row($conn, 'SELECT COUNT(*) AS n FROM sales_orders WHERE customer_id=?', 'i', [$id])['n'] : 0);
            if ($used > 0 || abs((float) $c['opening_balance']) > 0.004) {
                throw new RuntimeException($c['customer_name'] . ' has invoices, orders or an opening balance. Make the customer inactive instead.');
            }
            if ($c['trader_id']) {
                sales_exec($conn, 'DELETE FROM traders WHERE trader_id=?', 'i', [(int) $c['trader_id']])->close();
            }
            sales_exec($conn, 'DELETE FROM sales_customers WHERE customer_id=?', 'i', [$id])->close();
            $conn->commit();
            sales_flash_redirect('customer_list.php', 'success', $c['customer_name'] . ' deleted.');
        }
        mgmt_require(cm_can($conn, $c ? 'edit' : 'add'), $c ? 'editing customers' : 'adding customers');
        $catId = (int) ($_POST['category_id'] ?? 0);
        if (!isset($cats[$catId])) {
            throw new RuntimeException('Pick a customer category.');
        }
        $name = sales_post_string('trader_name', 190);
        if ($name === '') {
            throw new RuntimeException('Enter the customer name.');
        }
        $opening = $_POST['opening_balance'] ?? '0';
        if (!is_numeric($opening === '' ? '0' : $opening)) {
            throw new RuntimeException('Opening balance must be a number.');
        }
        $cols = [
            'category_id' => $catId, 'group_id' => trader_group_id($conn, $catId, sales_post_string('group_name', 120), true), 'trader_name' => $name,
            'mobile' => sales_post_string('mobile', 40), 'secondary_mobile' => sales_post_string('secondary_mobile', 40), 'phone' => sales_post_string('phone', 40),
            'email' => sales_post_string('email', 190), 'contact_person' => sales_post_string('contact_person', 190), 'owner_name' => sales_post_string('owner_name', 190),
            'address' => sales_post_string('address', 2000), 'bin' => sales_post_string('bin', 60), 'nid' => sales_post_string('nid', 60),
            'opening_balance' => round((float) $opening, 2), 'referrer_name' => sales_post_string('referrer_name', 190), 'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        ];
        if ($cols['email'] !== '' && !filter_var($cols['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address or leave it blank.');
        }
        if ($c && $c['trader_id']) {
            $traderId = (int) $c['trader_id'];
            $old = sales_row($conn, 'SELECT category_id FROM traders WHERE trader_id=?', 'i', [$traderId]);
            sales_exec($conn, 'UPDATE traders SET ' . implode(', ', array_map(fn($k) => "$k=?", array_keys($cols))) . ' WHERE trader_id=?',
                implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), $cols)) . 'i', array_merge(array_values($cols), [$traderId]))->close();
            if ((int) $old['category_id'] !== $catId) {
                sales_insert($conn, 'INSERT INTO trader_conversions (trader_id, old_category_id, new_category_id, converted_by) VALUES (?, ?, ?, ?)', 'iiii', [$traderId, (int) $old['category_id'], $catId, (int) ($GLOBALS['user_id'] ?? 0)]);
                trader_set_code($conn, $traderId);
            }
        } else {
            // New profile; for a customer made on a sales screen, link it so the same customer record is kept.
            $cols['sales_customer_id'] = $c ? $id : null;
            $cols['created_by'] = (int) ($GLOBALS['user_id'] ?? 0);
            $traderId = sales_insert($conn, 'INSERT INTO traders (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
                implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), $cols)), array_values($cols));
            trader_set_code($conn, $traderId);
        }
        trader_sync($conn, $traderId);
        $srId = (int) ($_POST['sr_id'] ?? 0);
        if ($srId > 0 && !sales_row($conn, 'SELECT 1 FROM sales_representatives WHERE sr_id=?', 'i', [$srId])) {
            throw new RuntimeException('Pick a valid SR.');
        }
        cm_assign_sr($conn, $traderId, $srId);
        if (cm_can($conn, 'credit') && isset($_POST['credit_form'])) {
            $limit = sales_amount($_POST['credit_limit'] ?? 0);
            $days = max(0, min(3650, (int) ($_POST['credit_days'] ?? 0)));
            $level = in_array($_POST['price_level'] ?? '', ['retail', 'wholesale', 'dealer', 'special'], true) ? $_POST['price_level'] : 'retail';
            $disc = min(100.0, sales_amount($_POST['special_discount_pct'] ?? 0));
            sales_exec($conn, 'INSERT INTO trader_flags (trader_id, allow_credit, credit_limit, credit_days, price_level, special_discount_pct, block_sales, flag_note, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) '
                    . 'ON DUPLICATE KEY UPDATE allow_credit=VALUES(allow_credit), credit_limit=VALUES(credit_limit), credit_days=VALUES(credit_days), price_level=VALUES(price_level), '
                    . 'special_discount_pct=VALUES(special_discount_pct), block_sales=VALUES(block_sales), flag_note=VALUES(flag_note), updated_by=VALUES(updated_by)',
                'iidisdisi', [$traderId, !empty($_POST['allow_credit']) ? 1 : 0, $limit, $days, $level, $disc, !empty($_POST['block_sales']) ? 1 : 0, sales_post_string('flag_note', 255), (int) ($GLOBALS['user_id'] ?? 0)])->close();
        }
        $customerId = (int) sales_row($conn, 'SELECT sales_customer_id FROM traders WHERE trader_id=?', 'i', [$traderId])['sales_customer_id'];
        $conn->commit();
        sales_flash_redirect($self . '?id=' . $customerId, 'success', $name . ' saved.');
    } catch (RuntimeException $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
cm_page_start($conn, 'Customer Profile', 'profile');
echo sales_flash_html($flash);
$c = $id ? cm_customer($conn, $id) : null;
if ($id && !$c) {
    echo '<div class="alert error">Customer not found.</div>';
    mgmt_page_end();
    exit;
}
$t = $c && $c['trader_id'] ? sales_row($conn, 'SELECT t.*, COALESCE(g.group_name, \'\') AS group_name FROM traders t LEFT JOIN trader_groups g ON g.group_id=t.group_id WHERE t.trader_id=?', 'i', [(int) $c['trader_id']]) : null;
$f = $t ? sales_row($conn, 'SELECT * FROM trader_flags WHERE trader_id=?', 'i', [(int) $t['trader_id']]) : null;
$srNow = $t ? sales_row($conn, 'SELECT sr_id FROM trader_sr_assignments WHERE trader_id=? AND effective_to IS NULL ORDER BY assignment_id DESC LIMIT 1', 'i', [(int) $t['trader_id']]) : null;
// Values: the profile, or for a customer with no profile yet, what the sales screens saved.
$v = [
    'category_id' => $t['category_id'] ?? 3, 'group_name' => $t['group_name'] ?? ($c['customer_group'] ?? ''), 'trader_name' => $t['trader_name'] ?? ($c['customer_name'] ?? ''),
    'mobile' => $t['mobile'] ?? ($c['mobile'] ?? ''), 'secondary_mobile' => $t['secondary_mobile'] ?? '', 'phone' => $t['phone'] ?? '', 'email' => $t['email'] ?? '',
    'contact_person' => $t['contact_person'] ?? ($c['attention'] ?? ''), 'owner_name' => $t['owner_name'] ?? '', 'address' => $t['address'] ?? ($c['address'] ?? ''),
    'bin' => $t['bin'] ?? ($c['bin'] ?? ''), 'nid' => $t['nid'] ?? ($c['nid'] ?? ''), 'opening_balance' => $t['opening_balance'] ?? 0, 'referrer_name' => $t['referrer_name'] ?? '',
    'is_active' => $t['is_active'] ?? 1,
];
$canSave = cm_can($conn, $c ? 'edit' : 'add');
$groups = array_column(sales_rows($conn, 'SELECT DISTINCT group_name FROM trader_groups g JOIN trader_categories c ON c.category_id=g.category_id WHERE c.kind=\'customer\' ORDER BY group_name'), 'group_name');
$in = fn($k, $label, $span = '', $type = 'text') => '<div class="field' . $span . '"><label for="' . $k . '">' . $label . '</label><input id="' . $k . '" name="' . $k . '" type="' . $type . '"' . ($type === 'number' ? ' step="0.01"' : '') . ' value="' . sales_h($v[$k]) . '"></div>';

if ($c) {
    [$label, $badge] = cm_status($c);
    $aging = cm_aging($conn, $id);
    echo '<div class="summary"><div>Customer<b>' . sales_h($c['customer_name']) . '</b></div><div>Code<b>' . sales_h($c['trader_code'] ?: $c['customer_code'] ?: '—') . '</b></div>'
        . '<div>Due<b class="qty-info">' . sales_money($c['balance']) . '</b></div><div>Credit Limit<b>' . ((int) $c['allow_credit'] && (float) $c['credit_limit'] > 0 ? sales_money($c['credit_limit']) : '—') . '</b></div>'
        . '<div>Total Sales<b>' . sales_money($c['total_sales']) . '</b></div><div>Invoices<b>' . (int) $c['invoice_count'] . '</b></div><div>Last Invoice<b>' . sales_h(inv_fmt_date($c['last_invoice_date'])) . '</b></div>'
        . '<div>Status<b><span class="badge ' . $badge . '">' . sales_h($label) . '</span></b></div></div>';
    if (array_sum($aging) > 0) {
        echo '<p class="footnote">Due by age: ' . implode(' · ', array_map(fn($k, $a) => sales_h($k) . ' ' . sales_money($a), array_keys($aging), $aging)) . '</p>';
    }
    if (!$t) {
        echo '<div class="alert error" style="background:#fff6df;color:#7a5b00">This customer was created on a sales screen and has no profile yet. Saving below creates it, keeping the same customer record.</div>';
    }
}
?>
<form method="post"><?= sales_csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<section class="section product-form" style="margin-top:8px"><div class="section-title">Profile</div><div class="hgrid">
<div class="field"><label for="category_id">Category *</label><select id="category_id" name="category_id"><?php foreach ($cats as $k => $n): ?><option value="<?= $k ?>"<?= (int) $v['category_id'] === $k ? ' selected' : '' ?>><?= sales_h($n) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="group_name">Customer Group</label><input id="group_name" name="group_name" list="dl-groups" value="<?= sales_h($v['group_name']) ?>"><datalist id="dl-groups"><?php foreach ($groups as $g): ?><option value="<?= sales_h($g) ?>"><?php endforeach; ?></datalist></div>
<div class="field s6"><label for="trader_name">Customer Name *</label><input id="trader_name" name="trader_name" required maxlength="190" value="<?= sales_h($v['trader_name']) ?>"></div>
<?= $in('mobile', 'Mobile') . $in('secondary_mobile', 'Secondary Mobile') . $in('phone', 'Phone') . $in('email', 'Email') . $in('contact_person', 'Contact Person / Attention') . $in('owner_name', 'Owner Name')
    . $in('address', 'Address', ' s6') . $in('bin', 'BIN') . $in('nid', 'NID') . $in('referrer_name', 'Referred By') . $in('opening_balance', 'Opening Balance (due)', '', 'number') ?>
<div class="field"><label for="sr_id">SR</label><select id="sr_id" name="sr_id"><option value="0">None</option><?php foreach (trader_srs($conn) as $s): ?><option value="<?= (int) $s['sr_id'] ?>"<?= (int) ($srNow['sr_id'] ?? 0) === (int) $s['sr_id'] ? ' selected' : '' ?>><?= sales_h($s['sr_name']) ?></option><?php endforeach; ?></select></div>
<div class="field s2"><label>&nbsp;</label><label><input class="check" type="checkbox" name="is_active" value="1"<?= (int) $v['is_active'] ? ' checked' : '' ?>> Active</label></div>
</div></section>
<?php if (cm_can($conn, 'credit')): ?>
<section class="section"><div class="section-title">Credit Limit</div><input type="hidden" name="credit_form" value="1"><div class="hgrid">
<div class="field s2"><label>&nbsp;</label><label><input class="check" type="checkbox" name="allow_credit" value="1"<?= (int) ($f['allow_credit'] ?? 0) ? ' checked' : '' ?>> Allow credit</label></div>
<div class="field s2"><label for="credit_limit">Credit Limit</label><input id="credit_limit" name="credit_limit" type="number" min="0" step="0.01" value="<?= sales_h($f['credit_limit'] ?? 0) ?>"></div>
<div class="field s2"><label for="credit_days">Credit Days</label><input id="credit_days" name="credit_days" type="number" min="0" max="3650" value="<?= sales_h($f['credit_days'] ?? 0) ?>"></div>
<div class="field s2"><label for="price_level">Price Level</label><select id="price_level" name="price_level"><?php foreach (['retail' => 'Retail', 'wholesale' => 'Wholesale', 'dealer' => 'Dealer', 'special' => 'Special'] as $k => $n): ?><option value="<?= $k ?>"<?= ($f['price_level'] ?? 'retail') === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
<div class="field s2"><label for="special_discount_pct">Special Discount %</label><input id="special_discount_pct" name="special_discount_pct" type="number" min="0" max="100" step="0.01" value="<?= sales_h($f['special_discount_pct'] ?? 0) ?>"></div>
<div class="field s2"><label>&nbsp;</label><label><input class="check" type="checkbox" name="block_sales" value="1"<?= (int) ($f['block_sales'] ?? 0) ? ' checked' : '' ?>> Block sales</label></div>
<div class="field s12"><label for="flag_note">Credit Note</label><input id="flag_note" name="flag_note" maxlength="255" value="<?= sales_h($f['flag_note'] ?? '') ?>"></div>
</div><p class="footnote">With credit rules saved, a Credit sales invoice is refused when the customer is blocked, has no credit allowed, would go over the limit, or has a due older than the credit days.</p></section>
<?php endif; ?>
<div class="toolbar" style="margin-top:10px">
<button class="btn primary"<?= $canSave ? '' : ' disabled title="Your role cannot save customers"' ?>>Save</button>
<a class="btn" href="customer_list.php">Back to list</a>
<?php if ($c && cm_can($conn, 'ledger')): ?><a class="btn" href="customer_ledger.php?id=<?= $id ?>">Ledger</a><?php endif; ?>
<?php if ($c): ?><a class="btn" href="../price_history/invoice_search.php?q=<?= urlencode($c['mobile']) ?>&amp;all_date=1">Invoices</a><?php endif; ?>
<?php if ($c && cm_can($conn, 'delete')): ?><button class="btn danger" name="action" value="delete" formnovalidate onclick="return confirm('Delete this customer?')">Delete</button><?php endif; ?>
</div></form>
<?php
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
