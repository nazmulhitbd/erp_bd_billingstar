<?php
/**
 * Trader Info: add, edit, delete and search traders of one category, with attachments.
 * Menu links: Credit Customer (?cat=3), Supplier Info (?cat=1), Cash Supplier (?cat=5).
 */
require_once __DIR__ . '/trader_common.php';

$ready = trader_schema_ready($conn);
$categories = $ready ? trader_categories($conn) : [];
$cat = (int) ($_REQUEST['cat'] ?? 3);
if ($ready && !isset($categories[$cat])) {
    $cat = (int) array_key_first($categories);
}
$self = 'trader_info.php?cat=' . $cat;

/* ---------- Attachment download ---------- */
if (isset($_GET['download']) && $ready) {
    $file = sales_row($conn, 'SELECT * FROM trader_attachments WHERE attachment_id=?', 'i', [(int) $_GET['download']]);
    $path = $file ? TRADER_UPLOAD_DIR . '/' . basename($file['stored_name']) : '';
    if (!$file || !is_file($path)) {
        http_response_code(404);
        exit('Attachment not found.');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    $inline = str_starts_with($file['mime_type'], 'image/') || $file['mime_type'] === 'application/pdf';
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $file['original_name']) . '"');
    readfile($path);
    exit;
}

function trader_form_values()
{
    $balance = trim((string) ($_POST['opening_balance'] ?? '0'));
    if ($balance !== '' && !is_numeric($balance)) {
        throw new RuntimeException('Opening balance must be a number.');
    }
    $email = sales_post_string('email', 190);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address or leave it blank.');
    }
    $name = sales_post_string('trader_name', 190);
    if ($name === '') {
        throw new RuntimeException('Enter the name.');
    }
    return [
        'trader_name' => $name,
        'document' => sales_post_string('document', 255),
        'referrer_name' => sales_post_string('referrer_name', 190),
        'address' => sales_post_string('address', 2000),
        'mobile' => sales_post_string('mobile', 40),
        'secondary_mobile' => sales_post_string('secondary_mobile', 40),
        'phone' => sales_post_string('phone', 40),
        'fax' => sales_post_string('fax', 40),
        'email' => $email,
        'web' => sales_post_string('web', 190),
        'contact_person' => sales_post_string('contact_person', 190),
        'owner_name' => sales_post_string('owner_name', 190),
        'opening_balance' => round((float) $balance, 2),
        'security_type' => sales_post_string('security_type', 80),
        'bg_cheque_no' => sales_post_string('bg_cheque_no', 80),
        'bin' => sales_post_string('bin', 60),
        'nid' => sales_post_string('nid', 60),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
}

/** Stops deletion when sales documents point at this trader's customer copy. */
function trader_in_use(mysqli $db, array $trader)
{
    $customerId = (int) ($trader['sales_customer_id'] ?? 0);
    if ($customerId > 0) {
        foreach (['sales_invoices' => 'an invoice', 'sales_orders' => 'a sales order'] as $table => $label) {
            if (sales_tables_ready($db, [$table]) && sales_row($db, 'SELECT 1 FROM ' . $table . ' WHERE customer_id=? LIMIT 1', 'i', [$customerId])) {
                return 'This trader has ' . $label . '. Untick Active instead of deleting.';
            }
        }
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    if (!$ready) {
        sales_flash_redirect($self, 'error', 'Trader tables are missing. Import billing/trader/schema.sql first.');
    }
    $action = (string) ($_POST['action'] ?? 'add');
    $traderId = (int) ($_POST['trader_id'] ?? 0);
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    $back = $self . ($traderId > 0 ? '&id=' . $traderId : '');
    try {
        if ($action === 'upload') {
            if (!sales_row($conn, 'SELECT 1 FROM traders WHERE trader_id=?', 'i', [$traderId])) {
                throw new RuntimeException('Select a trader before adding an attachment.');
            }
            $upload = $_FILES['attachment'] ?? null;
            if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Choose a file to attach.');
            }
            if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
                throw new RuntimeException('The upload failed. Files must be 5 MB or smaller.');
            }
            if ($upload['size'] > TRADER_UPLOAD_MAX) {
                throw new RuntimeException('Files must be 5 MB or smaller.');
            }
            $ext = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
            if (!isset(TRADER_UPLOAD_TYPES[$ext])) {
                throw new RuntimeException('Allowed file types: ' . implode(', ', array_keys(TRADER_UPLOAD_TYPES)) . '.');
            }
            if (!is_dir(TRADER_UPLOAD_DIR) && !mkdir(TRADER_UPLOAD_DIR, 0750, true)) {
                throw new RuntimeException('The uploads folder could not be created.');
            }
            $stored = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!move_uploaded_file($upload['tmp_name'], TRADER_UPLOAD_DIR . '/' . $stored)) {
                throw new RuntimeException('The file could not be saved. Check that billing/trader/uploads is writable.');
            }
            $original = sales_cut(basename((string) $upload['name']), 190);
            sales_insert($conn, 'INSERT INTO trader_attachments (trader_id, original_name, stored_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)', 'isssii', [$traderId, $original, $stored, TRADER_UPLOAD_TYPES[$ext], (int) $upload['size'], $userId]);
            sales_flash_redirect($back, 'success', 'Attachment "' . $original . '" added.');
        }
        if ($action === 'delete_attachment') {
            $file = sales_row($conn, 'SELECT * FROM trader_attachments WHERE attachment_id=? AND trader_id=?', 'ii', [(int) ($_POST['attachment_id'] ?? 0), $traderId]);
            if (!$file) {
                throw new RuntimeException('Attachment not found.');
            }
            sales_exec($conn, 'DELETE FROM trader_attachments WHERE attachment_id=?', 'i', [(int) $file['attachment_id']])->close();
            @unlink(TRADER_UPLOAD_DIR . '/' . basename($file['stored_name']));
            sales_flash_redirect($back, 'success', 'Attachment removed.');
        }
        if ($action === 'delete') {
            $trader = sales_row($conn, 'SELECT * FROM traders WHERE trader_id=?', 'i', [$traderId]);
            if (!$trader) {
                throw new RuntimeException('Select a trader to delete.');
            }
            $reason = trader_in_use($conn, $trader);
            if ($reason !== '') {
                throw new RuntimeException($reason);
            }
            $files = sales_rows($conn, 'SELECT stored_name FROM trader_attachments WHERE trader_id=?', 'i', [$traderId]);
            sales_exec($conn, 'DELETE FROM traders WHERE trader_id=?', 'i', [$traderId])->close();
            foreach ($files as $f) {
                @unlink(TRADER_UPLOAD_DIR . '/' . basename($f['stored_name']));
            }
            sales_flash_redirect($self, 'success', $trader['trader_name'] . ' deleted.');
        }

        // add / edit
        $v = trader_form_values();
        $conn->begin_transaction();
        $groupId = trader_group_id($conn, $cat, $_POST['group_name'] ?? '');
        $dupe = sales_row(
            $conn,
            'SELECT trader_code FROM traders WHERE category_id=? AND trader_name=? AND mobile=? AND trader_id<>?',
            'issi',
            [$cat, $v['trader_name'], $v['mobile'], $action === 'edit' ? $traderId : 0]
        );
        if ($dupe) {
            throw new RuntimeException('"' . $v['trader_name'] . '" with this mobile already exists (' . $dupe['trader_code'] . ').');
        }
        $columns = array_keys($v);
        // $v order: 12 text fields, opening_balance, security_type, bg_cheque_no, bin, nid, is_active
        $types = str_repeat('s', 12) . 'd' . 'ssss' . 'i';
        if ($action === 'edit') {
            $old = sales_row($conn, 'SELECT category_id FROM traders WHERE trader_id=? FOR UPDATE', 'i', [$traderId]);
            if (!$old) {
                throw new RuntimeException('Select a trader to edit first.');
            }
            if ((int) $old['category_id'] !== $cat) {
                throw new RuntimeException('This trader belongs to another category. Use Trader Convert to change it.');
            }
            $set = implode(', ', array_map(fn($c) => $c . '=?', $columns));
            sales_exec($conn, 'UPDATE traders SET group_id=?, ' . $set . ' WHERE trader_id=?', 'i' . $types . 'i', array_merge([$groupId], array_values($v), [$traderId]))->close();
            $message = $v['trader_name'] . ' updated.';
        } else {
            $traderId = sales_insert(
                $conn,
                'INSERT INTO traders (category_id, group_id, ' . implode(', ', $columns) . ', created_by) VALUES (?, ?, ' . implode(', ', array_fill(0, count($columns), '?')) . ', ?)',
                'ii' . $types . 'i',
                array_merge([$cat, $groupId], array_values($v), [$userId])
            );
            $code = trader_set_code($conn, $traderId);
            $message = $v['trader_name'] . ' added as ' . $code . '.';
        }
        trader_sync($conn, $traderId);
        $conn->commit();
        sales_flash_redirect($self . '&id=' . $traderId, 'success', $message);
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('Trader save failed: ' . $e->getMessage());
        $_SESSION['trader_form'] = $_POST;
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

/* ---------- Page ---------- */
$flash = sales_take_flash();
$repost = $flash && $flash['kind'] === 'error' ? ($_SESSION['trader_form'] ?? null) : null;
unset($_SESSION['trader_form']);
$selectedId = (int) ($_GET['id'] ?? 0);
$trader = null;
$attachments = [];
$traders = [];
$groups = [];
$qName = trim((string) ($_GET['q'] ?? ''));
$qMobile = trim((string) ($_GET['mobile'] ?? ''));
$qGroup = trim((string) ($_GET['group'] ?? ''));
if ($ready) {
    $groups = trader_groups($conn, $cat, true);
    if ($selectedId > 0) {
        $trader = sales_row($conn, 'SELECT t.*, COALESCE(g.group_name, \'\') AS group_name FROM traders t LEFT JOIN trader_groups g ON g.group_id=t.group_id WHERE t.trader_id=? AND t.category_id=?', 'ii', [$selectedId, $cat]);
        if ($trader) {
            $attachments = sales_rows($conn, 'SELECT * FROM trader_attachments WHERE trader_id=? ORDER BY attachment_id DESC', 'i', [$selectedId]);
        }
    }
    $where = ['t.category_id=?'];
    $types = 'i';
    $params = [$cat];
    if ($qName !== '') {
        $where[] = '(t.trader_name LIKE ? OR t.trader_code LIKE ?)';
        $types .= 'ss';
        array_push($params, '%' . $qName . '%', '%' . $qName . '%');
    }
    if ($qMobile !== '') {
        $where[] = '(t.mobile LIKE ? OR t.secondary_mobile LIKE ? OR t.phone LIKE ?)';
        $types .= 'sss';
        array_push($params, '%' . $qMobile . '%', '%' . $qMobile . '%', '%' . $qMobile . '%');
    }
    if ($qGroup !== '') {
        $where[] = 'g.group_name LIKE ?';
        $types .= 's';
        $params[] = '%' . $qGroup . '%';
    }
    $traders = sales_list_rows($conn, 'SELECT t.trader_id, t.trader_code, t.trader_name, t.phone, t.mobile, t.email, t.is_active, COALESCE(g.group_name, \'\') AS group_name FROM traders t LEFT JOIN trader_groups g ON g.group_id=t.group_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.trader_name', $types, $params);
}
$category = $categories[$cat] ?? ['category_name' => 'Trader', 'kind' => 'customer'];
$nameLabel = $category['kind'] === 'supplier' ? 'Supplier Name' : 'Customer Name';
$form = $repost ?: ($trader ?: []);
$val = fn($k, $d = '') => sales_h($form[$k] ?? $d);
$active = 'credit';
if ($cat === 1) { $active = 'supplier'; } elseif ($cat === 5) { $active = 'cashsup'; }

trader_page_start($category['category_name'], $active);
echo sales_flash_html($flash);
if (!$ready) {
    echo trader_missing_html();
    trader_page_end();
    exit;
}
?>
<fieldset class="box"><legend><?= sales_h($category['category_name']) ?></legend>
<form method="get" class="list-search" style="justify-content:flex-end"><label class="muted" for="catpick">Category</label><select id="catpick" class="input" name="cat" style="max-width:220px" onchange="this.form.submit()"><?= trader_category_options($categories, $cat) ?></select></form>
<form method="post" id="trader-form" autocomplete="off">
<?= sales_csrf_field() ?><input type="hidden" name="cat" value="<?= $cat ?>"><input type="hidden" name="trader_id" value="<?= (int) ($trader['trader_id'] ?? 0) ?>">
<div class="grid g2" style="margin-top:0">
<div class="field"><label for="group_name">Group Name</label><div class="inline"><input id="group_name" name="group_name" list="group-list" maxlength="120" value="<?= $val('group_name', $_GET['group'] ?? '') ?>"><datalist id="group-list"><?php foreach ($groups as $g): ?><option value="<?= sales_h($g['group_name']) ?>"><?php endforeach; ?></datalist>
<a class="btn small" href="trader_group.php?cat=<?= $cat ?>&return=<?= urlencode('trader_info.php?cat=' . $cat) ?>">Add</a><button type="button" class="btn small" data-search="group">Search</button></div></div>
<div class="field"><label for="trader_name"><?= $nameLabel ?></label><div class="inline"><input id="trader_name" name="trader_name" maxlength="190" placeholder="<?= $nameLabel ?>" value="<?= $val('trader_name') ?>" required><button type="button" class="btn small" data-search="q">Search</button></div></div>
</div>
<div class="grid g2">
<div class="field"><label for="document">Document</label><input id="document" name="document" maxlength="255" value="<?= $val('document') ?>"></div>
<div class="field"><label for="referrer_name">Referrer Name</label><input id="referrer_name" name="referrer_name" maxlength="190" value="<?= $val('referrer_name') ?>"></div>
</div>
<div class="grid" style="grid-template-columns:1fr"><div class="field"><label for="address">Address</label><input id="address" name="address" maxlength="2000" value="<?= $val('address') ?>"></div></div>
<div class="grid g5">
<div class="field"><label for="mobile">Mobile</label><div class="inline"><input id="mobile" name="mobile" maxlength="40" placeholder="Mobile Number" value="<?= $val('mobile') ?>"><button type="button" class="btn small" data-search="mobile">Search</button></div></div>
<div class="field"><label for="secondary_mobile">Secondary Mobile</label><input id="secondary_mobile" name="secondary_mobile" maxlength="40" placeholder="Mobile Number" value="<?= $val('secondary_mobile') ?>"></div>
<div class="field"><label for="phone">Phone</label><input id="phone" name="phone" maxlength="40" value="<?= $val('phone') ?>"></div>
<div class="field"><label for="fax">Fax</label><input id="fax" name="fax" maxlength="40" value="<?= $val('fax') ?>"></div>
<div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="190" value="<?= $val('email') ?>"></div>
</div>
<div class="grid g4">
<div class="field"><label for="web">Web</label><input id="web" name="web" maxlength="190" value="<?= $val('web') ?>"></div>
<div class="field"><label for="contact_person">Contact Person</label><input id="contact_person" name="contact_person" maxlength="190" value="<?= $val('contact_person') ?>"></div>
<div class="field"><label for="owner_name">Owner/Proprietor</label><input id="owner_name" name="owner_name" maxlength="190" value="<?= $val('owner_name') ?>"></div>
<div class="field"><label for="opening_balance">Opening Balance</label><input id="opening_balance" name="opening_balance" type="number" step="0.01" value="<?= $val('opening_balance', '0') ?>"></div>
</div>
<div class="grid g5">
<div class="field"><label for="security_type">Security Type</label><input id="security_type" name="security_type" maxlength="80" list="security-list" value="<?= $val('security_type') ?>"><datalist id="security-list"><option value="Bank Guarantee"><option value="Cheque"><option value="Cash Security"><option value="Trade License"></datalist></div>
<div class="field"><label for="bg_cheque_no">BG/Cheque No</label><input id="bg_cheque_no" name="bg_cheque_no" maxlength="80" value="<?= $val('bg_cheque_no') ?>"></div>
<div class="field"><label for="bin">BIN</label><input id="bin" name="bin" maxlength="60" value="<?= $val('bin') ?>"></div>
<div class="field"><label for="nid">NID</label><input id="nid" name="nid" maxlength="60" value="<?= $val('nid') ?>"></div>
<div class="field"><label>&nbsp;</label><label><input class="check" type="checkbox" name="is_active" value="1" <?= !$form || !empty($form['is_active']) ? 'checked' : '' ?>> Active</label></div>
</div>
<div class="toolbar" style="margin-top:14px">
<button class="btn primary" name="action" value="add" title="Save as a new <?= sales_h($category['category_name']) ?>">Add</button>
<button class="btn" name="action" value="edit" <?= $trader ? '' : 'disabled title="Select a trader first"' ?>>Edit</button>
<button class="btn danger" name="action" value="delete" formnovalidate <?= $trader ? '' : 'disabled' ?> onclick="return confirm('Delete this trader?')">Delete</button>
<button type="button" class="btn" <?= $trader ? '' : 'disabled title="Select a trader first"' ?> onclick="document.getElementById('attach').scrollIntoView({behavior:'smooth'})">Attachment</button>
<a class="btn" href="<?= sales_h($self) ?>">Close</a>
<?php if ($trader): ?><span class="muted" style="align-self:center">Selected: <b><?= sales_h($trader['trader_code']) ?></b></span><?php endif; ?>
</div>
</form>

<?php if ($trader): ?>
<section class="section" id="attach"><div class="section-title">Attachments for <?= sales_h($trader['trader_name']) ?></div>
<form method="post" enctype="multipart/form-data" class="list-search"><?= sales_csrf_field() ?><input type="hidden" name="cat" value="<?= $cat ?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="trader_id" value="<?= (int) $trader['trader_id'] ?>">
<input class="input" type="file" name="attachment" required accept=".<?= implode(',.', array_keys(TRADER_UPLOAD_TYPES)) ?>"><button class="btn primary">Upload</button><span class="muted">PDF, image, Word, Excel or text. Up to 5 MB.</span></form>
<table><tbody>
<?php if (!$attachments): ?><tr><td class="empty">No attachments.</td></tr><?php endif; ?>
<?php foreach ($attachments as $a): ?><tr><td><a target="_blank" href="<?= sales_h($self) ?>&download=<?= (int) $a['attachment_id'] ?>"><?= sales_h($a['original_name']) ?></a></td><td class="num"><?= number_format($a['file_size'] / 1024, 1) ?> KB</td><td><?= sales_h($a['created_at']) ?></td>
<td><form method="post" onsubmit="return confirm('Remove this attachment?')"><?= sales_csrf_field() ?><input type="hidden" name="cat" value="<?= $cat ?>"><input type="hidden" name="action" value="delete_attachment"><input type="hidden" name="trader_id" value="<?= (int) $trader['trader_id'] ?>"><input type="hidden" name="attachment_id" value="<?= (int) $a['attachment_id'] ?>"><button class="btn small danger">Remove</button></form></td></tr><?php endforeach; ?>
</tbody></table></section>
<?php endif; ?>

<?php if ($qName !== '' || $qMobile !== '' || $qGroup !== ''): ?><div class="footnote">Filtered list. <a href="<?= sales_h($self) ?>">Show all</a></div><?php endif; ?>
<div class="table-scroll"><table><thead><tr><th>SL</th><th><?= $category['kind'] === 'supplier' ? 'Supplier' : 'Customer' ?> ID</th><th><?= $nameLabel ?></th><th>Group</th><th>Phone</th><th>Mobile</th><th>Email</th><th>Active</th><th>Select</th><th>Edit</th><th>Delete</th></tr></thead><tbody>
<?php if (!$traders): ?><tr><td colspan="11" class="empty">No records.</td></tr><?php endif; ?>
<?php foreach ($traders as $i => $t): $tid = (int) $t['trader_id']; ?><tr class="<?= $tid === $selectedId ? 'selected-row' : '' ?>">
<td><?= $i + 1 ?></td><td><?= sales_h($t['trader_code']) ?></td><td><?= sales_h($t['trader_name']) ?></td><td><?= sales_h($t['group_name']) ?></td><td><?= sales_h($t['phone']) ?></td><td><?= sales_h($t['mobile']) ?></td><td><?= sales_h($t['email']) ?></td><td><?= (int) $t['is_active'] ? 'Yes' : 'No' ?></td>
<td><a class="btn small" href="<?= sales_h($self) ?>&id=<?= $tid ?>">Select</a></td>
<td><a class="btn small" href="<?= sales_h($self) ?>&id=<?= $tid ?>#trader-form" onclick="setTimeout(()=>document.getElementById('trader_name')?.focus(),50)">Edit</a></td>
<td><form method="post" onsubmit="return confirm('Delete <?= sales_h(addslashes($t['trader_name'])) ?>?')"><?= sales_csrf_field() ?><input type="hidden" name="cat" value="<?= $cat ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="trader_id" value="<?= $tid ?>"><button class="btn small danger">Delete</button></form></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</fieldset>
<?php
$script = 'document.querySelectorAll("[data-search]").forEach(b=>b.addEventListener("click",()=>{const k=b.dataset.search,src={q:"trader_name",mobile:"mobile",group:"group_name"}[k];const v=document.getElementById(src).value.trim();location.href=' . sales_js($self) . '+"&"+k+"="+encodeURIComponent(v)}));';
trader_page_end($script);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
