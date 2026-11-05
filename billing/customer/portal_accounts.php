<?php
/**
 * Portal Accounts: give customers access to the Customer Portal. Creating an account or resetting a password makes a
 * temporary password, shown once here, that the customer must change at first sign-in. Accounts can be disabled,
 * unlocked after failed sign-ins, or removed; the sign-in history shows every attempt.
 */
require_once __DIR__ . '/portal_common.php';

$self = 'portal_accounts.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && portal_ready($conn)) {
    $back = $self . '?' . http_build_query(array_intersect_key($_POST, array_flip(['q', 'filter'])));
    sales_require_csrf($back);
    $action = (string) ($_POST['action'] ?? '');
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $acc = sales_row($conn, 'SELECT a.*, c.customer_name FROM portal_accounts a JOIN sales_customers c ON c.customer_id=a.customer_id WHERE a.customer_id=?', 'i', [$customerId]);
    try {
        switch ($action) {
            case 'create':
                mgmt_require(cm_can($conn, 'add'), 'creating portal accounts');
                $c = cm_customer($conn, $customerId);
                if (!$c) {
                    throw new RuntimeException('Customer not found.');
                }
                if ($acc) {
                    throw new RuntimeException($c['customer_name'] . ' already has a portal account.');
                }
                $login = sales_post_string('login', 60);
                $login = $login !== '' ? $login : substr(preg_replace('/\D/', '', (string) $c['mobile']), -10);
                if ($login === '' || !preg_match('/^[A-Za-z0-9._@+-]{3,60}$/', $login)) {
                    throw new RuntimeException('Enter a login of 3-60 letters, digits or . _ @ + - (the customer has no mobile number to use).');
                }
                if (sales_row($conn, 'SELECT 1 FROM portal_accounts WHERE login=?', 's', [$login])) {
                    throw new RuntimeException('Login "' . $login . '" is already used.');
                }
                $temp = portal_temp_password();
                sales_insert($conn, "INSERT INTO portal_accounts (customer_id, login, password_hash, must_change_password, created_via, created_by) VALUES (?, ?, ?, 1, 'staff', ?)",
                    'issi', [$customerId, $login, password_hash($temp, PASSWORD_DEFAULT), (int) ($GLOBALS['user_id'] ?? 0)]);
                sales_flash_redirect($back, 'success', 'Portal account for ' . $c['customer_name'] . ': login ' . $login . ', temporary password ' . $temp . ' (shown once; the customer must change it at first sign-in).');
                // no break: sales_flash_redirect exits
            case 'reset':
                mgmt_require(cm_can($conn, 'edit'), 'resetting portal passwords');
                if (!$acc) {
                    throw new RuntimeException('This customer has no portal account.');
                }
                $temp = portal_temp_password();
                sales_exec($conn, 'UPDATE portal_accounts SET password_hash=?, must_change_password=1, failed_attempts=0, locked_until=NULL WHERE account_id=?', 'si', [password_hash($temp, PASSWORD_DEFAULT), (int) $acc['account_id']])->close();
                sales_flash_redirect($back, 'success', 'New temporary password for ' . $acc['customer_name'] . ' (login ' . $acc['login'] . '): ' . $temp . ' (shown once).');
            case 'toggle':
            case 'unlock':
                mgmt_require(cm_can($conn, 'edit'), 'changing portal accounts');
                if (!$acc) {
                    throw new RuntimeException('This customer has no portal account.');
                }
                if ($action === 'toggle') {
                    $status = $acc['status'] === 'active' ? 'disabled' : 'active';
                    sales_exec($conn, 'UPDATE portal_accounts SET status=? WHERE account_id=?', 'si', [$status, (int) $acc['account_id']])->close();
                    sales_flash_redirect($back, 'success', $acc['customer_name'] . "'s portal account is now " . $status . '.');
                }
                sales_exec($conn, 'UPDATE portal_accounts SET failed_attempts=0, locked_until=NULL WHERE account_id=?', 'i', [(int) $acc['account_id']])->close();
                sales_flash_redirect($back, 'success', $acc['customer_name'] . "'s portal account is unlocked.");
            case 'delete':
                mgmt_require(cm_can($conn, 'delete'), 'removing portal accounts');
                if ($acc) {
                    sales_exec($conn, 'DELETE FROM portal_accounts WHERE account_id=?', 'i', [(int) $acc['account_id']])->close();
                }
                sales_flash_redirect($back, 'success', 'Portal account removed. The customer and their history are kept.');
        }
        throw new RuntimeException('Unknown action.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
portal_page_start($conn, 'Portal Accounts', 'portal_accounts');
echo sales_flash_html($flash);
$q = sales_cut(trim((string) ($_GET['q'] ?? '')), 120);
$filter = in_array($_GET['filter'] ?? '', ['with', 'without', 'disabled', 'locked', 'pending'], true) ? (string) $_GET['filter'] : 'with';

if (isset($_GET['log'])) {
    $acc = sales_row($conn, 'SELECT a.*, c.customer_name FROM portal_accounts a JOIN sales_customers c ON c.customer_id=a.customer_id WHERE a.account_id=?', 'i', [(int) $_GET['log']]);
    $log = $acc ? sales_rows($conn, 'SELECT * FROM portal_login_log WHERE account_id=? ORDER BY log_id DESC LIMIT 100', 'i', [(int) $acc['account_id']]) : [];
    echo '<section class="section"><div class="section-title">Sign-in history' . ($acc ? ': ' . sales_h($acc['customer_name']) . ' (' . sales_h($acc['login']) . ')' : '') . '</div><table><thead><tr><th>When</th><th>Result</th><th>Address</th><th>Browser</th></tr></thead><tbody>';
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
    $where[] = '(c.customer_name LIKE ? OR c.mobile LIKE ? OR a.login LIKE ? OR c.trader_code LIKE ?)';
    $types = 'ssss';
    $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
}
$where[] = match ($filter) {
    'without' => 'a.account_id IS NULL',
    'disabled' => "a.status='disabled'",
    'locked' => 'a.locked_until > NOW()',
    'pending' => 'a.must_change_password=1',
    default => 'a.account_id IS NOT NULL',
};
$rows = sales_list_rows($conn, 'SELECT c.customer_id, c.customer_name, c.mobile, c.trader_code, c.customer_code, c.balance, a.account_id, a.login, a.status, a.must_change_password, a.failed_attempts, a.locked_until, a.last_login_at, a.created_via, a.created_at '
        . 'FROM customer_accounts c LEFT JOIN portal_accounts a ON a.customer_id=c.customer_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.customer_name', $types, $params);
$counts = sales_row($conn, "SELECT COUNT(*) AS total, SUM(status='active') AS active, SUM(locked_until > NOW()) AS locked, SUM(must_change_password=1) AS pending FROM portal_accounts");
$hidden = '<input type="hidden" name="q" value="' . sales_h($q) . '"><input type="hidden" name="filter" value="' . sales_h($filter) . '">';
$act = function (array $r, $action, $label, $class = '', $confirm = '') use ($hidden) {
    return '<form method="post" class="inline">' . sales_csrf_field() . $hidden . '<input type="hidden" name="customer_id" value="' . (int) $r['customer_id'] . '"><input type="hidden" name="action" value="' . $action . '">'
        . '<button class="btn small ' . $class . '"' . ($confirm ? ' onclick="return confirm(\'' . sales_h($confirm) . '\')"' : '') . '>' . $label . '</button></form>';
};
?>
<div class="summary"><div>Portal accounts<b><?= (int) $counts['total'] ?></b></div><div>Active<b><?= (int) $counts['active'] ?></b></div><div>Locked now<b><?= (int) $counts['locked'] ?></b></div><div>Not signed in yet<b><?= (int) $counts['pending'] ?></b></div>
<div>Portal address<b><a target="_blank" href="<?= PORTAL_URL ?>">Open portal</a></b></div></div>
<form method="get" class="list-search" style="align-items:center;margin-top:8px"><input class="input" name="q" value="<?= sales_h($q) ?>" placeholder="Customer, mobile, login or code" style="max-width:260px">
<select class="input" name="filter" style="max-width:220px"><?php foreach (['with' => 'Customers with an account', 'without' => 'Customers without an account', 'disabled' => 'Disabled accounts', 'locked' => 'Locked accounts', 'pending' => 'Must change password'] as $k => $l): ?><option value="<?= $k ?>"<?= $filter === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
<button class="btn primary">Show</button></form>
<div class="table-scroll" style="max-height:600px;margin-top:8px"><table><thead><tr><th>SL</th><th>Customer</th><th>Mobile</th><th class="num">Due</th><th>Login</th><th>Status</th><th>Last Sign-in</th><th>Created</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="9" class="empty">No customers match.</td></tr><?php endif; ?>
<?php foreach ($rows as $i => $r): $locked = $r['locked_until'] && strtotime($r['locked_until']) > time(); ?>
<tr><td><?= $i + 1 ?></td><td><a href="customer_profile.php?id=<?= (int) $r['customer_id'] ?>"><?= sales_h($r['customer_name']) ?></a> <span class="muted"><?= sales_h($r['trader_code'] ?: $r['customer_code']) ?></span></td><td><?= sales_h($r['mobile']) ?></td><td class="num"><?= sales_money($r['balance']) ?></td>
<?php if (!$r['account_id']): ?>
<td colspan="4" class="muted">No portal account</td>
<td><?php if (cm_can($conn, 'add')): ?><form method="post" class="inline"><?= sales_csrf_field() . $hidden ?><input type="hidden" name="customer_id" value="<?= (int) $r['customer_id'] ?>"><input type="hidden" name="action" value="create">
<input name="login" placeholder="Login (default: mobile)" style="width:160px;height:28px"><button class="btn small primary">Create account</button></form><?php endif; ?></td>
<?php else: ?>
<td><?= sales_h($r['login']) ?></td>
<td><?= $r['status'] === 'active' ? '<span class="badge converted">Active</span>' : '<span class="badge cancelled">Disabled</span>' ?><?= $locked ? ' <span class="badge cancelled">Locked</span>' : '' ?><?= (int) $r['must_change_password'] ? ' <span class="badge open">Must change password</span>' : '' ?></td>
<td><?= $r['last_login_at'] ? sales_h(date('d/m/Y h:i A', strtotime($r['last_login_at']))) : '<span class="muted">Never</span>' ?></td><td><?= sales_h(inv_fmt_date($r['created_at'])) ?> <span class="muted"><?= sales_h($r['created_via'] === 'signup' ? 'self sign-up' : 'by staff') ?></span></td>
<td class="nowrap"><?php if (cm_can($conn, 'edit')): ?><?= $act($r, 'reset', 'Reset password', '', 'Make a new temporary password for ' . $r['customer_name'] . '?') ?> <?= $act($r, 'toggle', $r['status'] === 'active' ? 'Disable' : 'Enable') ?><?= $locked ? ' ' . $act($r, 'unlock', 'Unlock') : '' ?><?php endif; ?>
 <a class="btn small" href="<?= $self ?>?log=<?= (int) $r['account_id'] ?>&amp;filter=<?= sales_h($filter) ?>&amp;q=<?= urlencode($q) ?>">History</a>
<?php if (cm_can($conn, 'delete')): ?> <?= $act($r, 'delete', 'Remove', 'danger', 'Remove the portal account of ' . $r['customer_name'] . '? The customer record stays.') ?><?php endif; ?></td>
<?php endif; ?></tr>
<?php endforeach; ?></tbody></table></div>
<p class="footnote">Customers can also create their own account on the portal with their mobile number and one of their invoice numbers, while self sign-up is on (Portal Notices &rsaquo; Settings).</p>
<?php
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
