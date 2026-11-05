<?php
/**
 * SR Portal Accounts: give sales representatives a login to the SR / Staff Portal and choose what each may do
 * (book orders, record collections, see every customer). Creating an account or resetting a password makes a
 * temporary password, shown once here, that the SR must change at first sign-in.
 */
require_once __DIR__ . '/sales_person_common.php';

$self = 'sr_portal_accounts.php';
const SR_RIGHTS = ['can_book_orders' => 'Book orders', 'can_collect' => 'Record collections', 'see_all_customers' => 'See all customers'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && sp_portal_ready($conn)) {
    $back = $self . '?' . http_build_query(array_intersect_key($_POST, array_flip(['q', 'filter'])));
    sales_require_csrf($back);
    $action = (string) ($_POST['action'] ?? '');
    $srId = (int) ($_POST['sr_id'] ?? 0);
    $acc = sales_row($conn, 'SELECT a.*, s.sr_name FROM sr_portal_accounts a JOIN sales_representatives s ON s.sr_id=a.sr_id WHERE a.sr_id=?', 'i', [$srId]);
    try {
        if ($action === 'create') {
            mgmt_require(sp_can($conn, 'add'), 'creating SR portal accounts');
            $sr = sales_row($conn, 'SELECT * FROM sales_representatives WHERE sr_id=?', 'i', [$srId]);
            if (!$sr) {
                throw new RuntimeException('Sales person not found.');
            }
            if ($acc) {
                throw new RuntimeException($sr['sr_name'] . ' already has a portal account.');
            }
            $login = sales_post_string('login', 60);
            $login = $login !== '' ? $login : ($sr['sr_code'] !== '' ? $sr['sr_code'] : substr(preg_replace('/\D/', '', (string) $sr['mobile']), -10));
            if (!preg_match('/^[A-Za-z0-9._@+-]{3,60}$/', $login)) {
                throw new RuntimeException('Enter a login of 3-60 letters, digits or . _ @ + -.');
            }
            if (sales_row($conn, 'SELECT 1 FROM sr_portal_accounts WHERE login=?', 's', [$login])) {
                throw new RuntimeException('Login "' . $login . '" is already used.');
            }
            $temp = portal_temp_password();
            sales_insert($conn, 'INSERT INTO sr_portal_accounts (sr_id, login, password_hash, must_change_password, can_book_orders, can_collect, see_all_customers, created_by) VALUES (?, ?, ?, 1, ?, ?, ?, ?)',
                'issiiii', [$srId, $login, password_hash($temp, PASSWORD_DEFAULT), !empty($_POST['can_book_orders']) ? 1 : 0, !empty($_POST['can_collect']) ? 1 : 0, !empty($_POST['see_all_customers']) ? 1 : 0, (int) ($GLOBALS['user_id'] ?? 0)]);
            sales_flash_redirect($back, 'success', 'Portal account for ' . $sr['sr_name'] . ': login ' . $login . ', temporary password ' . $temp . ' (shown once; it must be changed at first sign-in).');
        }
        if (!$acc) {
            throw new RuntimeException('This sales person has no portal account.');
        }
        switch ($action) {
            case 'reset':
                mgmt_require(sp_can($conn, 'edit'), 'resetting SR portal passwords');
                $temp = portal_temp_password();
                sales_exec($conn, 'UPDATE sr_portal_accounts SET password_hash=?, must_change_password=1, failed_attempts=0, locked_until=NULL WHERE account_id=?', 'si', [password_hash($temp, PASSWORD_DEFAULT), (int) $acc['account_id']])->close();
                sales_flash_redirect($back, 'success', 'New temporary password for ' . $acc['sr_name'] . ' (login ' . $acc['login'] . '): ' . $temp . ' (shown once).');
            case 'rights':
                mgmt_require(sp_can($conn, 'edit'), 'changing SR portal accounts');
                $vals = array_map(fn($k) => !empty($_POST[$k]) ? 1 : 0, array_keys(SR_RIGHTS));
                sales_exec($conn, 'UPDATE sr_portal_accounts SET can_book_orders=?, can_collect=?, see_all_customers=? WHERE account_id=?', 'iiii', array_merge($vals, [(int) $acc['account_id']]))->close();
                sales_flash_redirect($back, 'success', $acc['sr_name'] . "'s portal rights saved.");
            case 'toggle':
                mgmt_require(sp_can($conn, 'edit'), 'changing SR portal accounts');
                $status = $acc['status'] === 'active' ? 'disabled' : 'active';
                sales_exec($conn, 'UPDATE sr_portal_accounts SET status=? WHERE account_id=?', 'si', [$status, (int) $acc['account_id']])->close();
                sales_flash_redirect($back, 'success', $acc['sr_name'] . "'s portal account is now " . $status . '.');
            case 'unlock':
                mgmt_require(sp_can($conn, 'edit'), 'changing SR portal accounts');
                sales_exec($conn, 'UPDATE sr_portal_accounts SET failed_attempts=0, locked_until=NULL WHERE account_id=?', 'i', [(int) $acc['account_id']])->close();
                sales_flash_redirect($back, 'success', $acc['sr_name'] . "'s portal account is unlocked.");
            case 'delete':
                mgmt_require(sp_can($conn, 'delete'), 'removing SR portal accounts');
                sales_exec($conn, 'DELETE FROM sr_portal_accounts WHERE account_id=?', 'i', [(int) $acc['account_id']])->close();
                sales_flash_redirect($back, 'success', 'Portal account removed. The sales person, their orders and collections are kept.');
        }
        throw new RuntimeException('Unknown action.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
sp_portal_page_start($conn, 'SR Portal Accounts', 'sr_portal_accounts');
echo sales_flash_html($flash);
$q = sales_cut(trim((string) ($_GET['q'] ?? '')), 120);
$filter = in_array($_GET['filter'] ?? '', ['all', 'with', 'without', 'disabled', 'locked'], true) ? (string) $_GET['filter'] : 'all';

if (isset($_GET['log'])) {
    $acc = sales_row($conn, 'SELECT a.*, s.sr_name FROM sr_portal_accounts a JOIN sales_representatives s ON s.sr_id=a.sr_id WHERE a.account_id=?', 'i', [(int) $_GET['log']]);
    $log = $acc ? sales_rows($conn, 'SELECT * FROM sr_portal_login_log WHERE account_id=? ORDER BY log_id DESC LIMIT 100', 'i', [(int) $acc['account_id']]) : [];
    echo '<section class="section"><div class="section-title">Sign-in history' . ($acc ? ': ' . sales_h($acc['sr_name']) . ' (' . sales_h($acc['login']) . ')' : '') . '</div><table><thead><tr><th>When</th><th>Result</th><th>Address</th><th>Browser</th></tr></thead><tbody>';
    echo $log ? '' : '<tr><td colspan="4" class="empty">No sign-ins yet.</td></tr>';
    $reasons = ['ok' => 'Signed in', 'bad_password' => 'Wrong password', 'locked' => 'Refused: locked', 'disabled' => 'Refused: disabled', 'ip_throttled' => 'Refused: too many tries'];
    foreach ($log as $l) {
        echo '<tr><td>' . sales_h(date('d/m/Y h:i A', strtotime($l['created_at']))) . '</td><td>' . ((int) $l['success'] ? '<span class="badge converted">' : '<span class="badge cancelled">') . sales_h($reasons[$l['reason']] ?? $l['reason']) . '</span></td><td>' . sales_h($l['ip_address']) . '</td><td>' . sales_h(substr($l['user_agent'], 0, 80)) . '</td></tr>';
    }
    echo '</tbody></table></section>';
}

$where = ['1=1'];
$types = '';
$params = [];
if ($q !== '') {
    $where[] = '(s.sr_name LIKE ? OR s.sr_code LIKE ? OR s.mobile LIKE ? OR a.login LIKE ?)';
    $types = 'ssss';
    $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
}
$where[] = match ($filter) {
    'with' => 'a.account_id IS NOT NULL',
    'without' => 'a.account_id IS NULL',
    'disabled' => "a.status='disabled'",
    'locked' => 'a.locked_until > NOW()',
    default => '1=1',
};
$rows = sales_list_rows($conn, 'SELECT s.sr_id, s.sr_code, s.sr_name, s.mobile, s.designation, s.is_active, a.account_id, a.login, a.status, a.must_change_password, a.can_book_orders, a.can_collect, a.see_all_customers, a.locked_until, a.last_login_at, '
        . "(SELECT COUNT(*) FROM sr_collections k WHERE k.sr_id=s.sr_id AND k.status='pending') AS pending_collections "
        . 'FROM sales_representatives s LEFT JOIN sr_portal_accounts a ON a.sr_id=s.sr_id WHERE ' . implode(' AND ', $where) . ' ORDER BY s.is_active DESC, s.sr_name', $types, $params);
$counts = sales_row($conn, "SELECT COUNT(*) AS total, COALESCE(SUM(status='active'), 0) AS active, COALESCE(SUM(locked_until > NOW()), 0) AS locked, COALESCE(SUM(must_change_password=1), 0) AS pending FROM sr_portal_accounts");
$hidden = '<input type="hidden" name="q" value="' . sales_h($q) . '"><input type="hidden" name="filter" value="' . sales_h($filter) . '">';
$act = function (array $r, $action, $label, $class = '', $confirm = '') use ($hidden) {
    return '<form method="post" class="inline">' . sales_csrf_field() . $hidden . '<input type="hidden" name="sr_id" value="' . (int) $r['sr_id'] . '"><input type="hidden" name="action" value="' . $action . '">'
        . '<button class="btn small ' . $class . '"' . ($confirm ? ' onclick="return confirm(\'' . sales_h($confirm) . '\')"' : '') . '>' . $label . '</button></form>';
};
$checks = function (array $r) {
    $out = '';
    foreach (SR_RIGHTS as $k => $l) {
        $out .= '<label class="nowrap" style="margin-right:8px"><input type="checkbox" name="' . $k . '" value="1"' . ((int) ($r[$k] ?? ($k === 'see_all_customers' ? 0 : 1)) ? ' checked' : '') . '> ' . $l . '</label>';
    }
    return $out;
};
?>
<div class="summary"><div>Portal accounts<b><?= (int) $counts['total'] ?></b></div><div>Active<b><?= (int) $counts['active'] ?></b></div><div>Locked now<b><?= (int) $counts['locked'] ?></b></div><div>Not signed in yet<b><?= (int) $counts['pending'] ?></b></div>
<div>Portal address<b><a target="_blank" href="<?= SR_PORTAL_URL ?>">Open SR portal</a></b></div></div>
<form method="get" class="list-search" style="align-items:center;margin-top:8px"><input class="input" name="q" value="<?= sales_h($q) ?>" placeholder="Name, code, mobile or login" style="max-width:260px">
<select class="input" name="filter" style="max-width:220px"><?php foreach (['all' => 'All sales persons', 'with' => 'With an account', 'without' => 'Without an account', 'disabled' => 'Disabled accounts', 'locked' => 'Locked accounts'] as $k => $l): ?><option value="<?= $k ?>"<?= $filter === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
<button class="btn primary">Show</button></form>
<div class="table-scroll" style="max-height:600px;margin-top:8px"><table><thead><tr><th>SL</th><th>Sales Person</th><th>Mobile</th><th>Login</th><th>Status</th><th>Rights</th><th>Last Sign-in</th><th class="num">Pending coll.</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="9" class="empty">No sales persons match.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): $locked = $r['locked_until'] && strtotime($r['locked_until']) > time(); ?>
<tr><td><?= $i + 1 ?></td><td><?= sales_h($r['sr_name']) ?> <span class="muted"><?= sales_h($r['sr_code']) ?></span><?= (int) $r['is_active'] ? '' : ' <span class="badge cancelled">Inactive SR</span>' ?></td><td><?= sales_h($r['mobile']) ?></td>
<?php if (!$r['account_id']): ?>
<td colspan="5" class="muted">No portal account</td>
<td><?php if (sp_can($conn, 'add') && (int) $r['is_active']): ?><form method="post" class="inline"><?= sales_csrf_field() . $hidden ?><input type="hidden" name="sr_id" value="<?= (int) $r['sr_id'] ?>"><input type="hidden" name="action" value="create">
<input name="login" placeholder="Login (default: SR code)" style="width:160px;height:28px"> <?= $checks([]) ?><button class="btn small primary">Create account</button></form><?php endif; ?></td>
<?php else: ?>
<td><?= sales_h($r['login']) ?></td>
<td><?= $r['status'] === 'active' ? '<span class="badge converted">Active</span>' : '<span class="badge cancelled">Disabled</span>' ?><?= $locked ? ' <span class="badge cancelled">Locked</span>' : '' ?><?= (int) $r['must_change_password'] ? ' <span class="badge open">Must change password</span>' : '' ?></td>
<td><?php if (sp_can($conn, 'edit')): ?><form method="post" class="inline"><?= sales_csrf_field() . $hidden ?><input type="hidden" name="sr_id" value="<?= (int) $r['sr_id'] ?>"><input type="hidden" name="action" value="rights"><?= $checks($r) ?><button class="btn small">Save</button></form>
<?php else: ?><?= sales_h(implode(', ', array_keys(array_filter(array_combine(SR_RIGHTS, [$r['can_book_orders'], $r['can_collect'], $r['see_all_customers']]))))) ?><?php endif; ?></td>
<td><?= $r['last_login_at'] ? sales_h(date('d/m/Y h:i A', strtotime($r['last_login_at']))) : '<span class="muted">Never</span>' ?></td>
<td class="num"><?= (int) $r['pending_collections'] ? '<a href="sr_collections.php?sr=' . (int) $r['sr_id'] . '">' . (int) $r['pending_collections'] . '</a>' : '0' ?></td>
<td class="nowrap"><?php if (sp_can($conn, 'edit')): ?><?= $act($r, 'reset', 'Reset password', '', 'Make a new temporary password for ' . $r['sr_name'] . '?') ?> <?= $act($r, 'toggle', $r['status'] === 'active' ? 'Disable' : 'Enable') ?><?= $locked ? ' ' . $act($r, 'unlock', 'Unlock') : '' ?><?php endif; ?>
 <a class="btn small" href="<?= $self ?>?log=<?= (int) $r['account_id'] ?>&amp;filter=<?= sales_h($filter) ?>&amp;q=<?= urlencode($q) ?>">History</a>
<?php if (sp_can($conn, 'delete')): ?> <?= $act($r, 'delete', 'Remove', 'danger', 'Remove the portal account of ' . $r['sr_name'] . '? Their orders and collections stay.') ?><?php endif; ?></td>
<?php endif; ?></tr>
<?php endforeach; ?></tbody></table></div>
<p class="footnote">SRs sign in with their login or mobile number. An SR sees only the customers assigned to them in Trader wise SR Fixing unless "See all customers" is ticked (supervisors, office staff). Deactivating the SR in Sales Person (SR) also stops their portal sign-in.</p>
<?php
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
