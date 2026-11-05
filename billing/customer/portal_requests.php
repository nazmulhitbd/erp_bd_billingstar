<?php
/**
 * Portal Requests: quotation, support, payment, account and password requests sent from the Customer Portal.
 * Staff reply (the customer reads it on the portal), and close or reopen requests.
 */
require_once __DIR__ . '/portal_common.php';

$self = 'portal_requests.php';
const PORTAL_TYPES = ['quotation' => 'Price quotation', 'support' => 'Product support', 'payment' => 'Payment / statement', 'account' => 'Account details', 'password' => 'Password reset', 'other' => 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && portal_ready($conn)) {
    $id = (int) ($_POST['request_id'] ?? 0);
    $back = $self . '?id=' . $id;
    sales_require_csrf($back);
    mgmt_require(cm_can($conn, 'edit'), 'answering portal requests');
    try {
        $r = sales_row($conn, 'SELECT * FROM portal_requests WHERE request_id=?', 'i', [$id]);
        if (!$r) {
            throw new RuntimeException('Request not found.');
        }
        $body = sales_post_string('body', 4000);
        $status = in_array($_POST['status'] ?? '', ['open', 'answered', 'closed'], true) ? $_POST['status'] : ($body !== '' ? 'answered' : $r['status']);
        if ($body !== '') {
            sales_insert($conn, "INSERT INTO portal_request_messages (request_id, sender, staff_user_id, staff_name, body) VALUES (?, 'staff', ?, ?, ?)", 'iiss', [$id, (int) ($GLOBALS['user_id'] ?? 0), sales_cut((string) ($GLOBALS['user_name'] ?? ''), 120), $body]);
        }
        sales_exec($conn, 'UPDATE portal_requests SET status=?' . ($body !== '' ? ", last_reply_by='staff'" : '') . ' WHERE request_id=?', 'si', [$status, $id])->close();
        if ($r['customer_id'] && ($body !== '' || $status !== $r['status'])) {
            notify_customer($r['customer_id'], ($body !== '' ? 'Reply to your request ' : 'Request updated: ') . $r['request_no'], $body !== '' ? mb_substr($body, 0, 160) : 'Status: ' . ucfirst($status), 'requests.php?id=' . (int) $r['request_id']);
        }
        sales_flash_redirect($back, 'success', $body !== '' ? 'Reply sent.' : 'Status changed to ' . $status . '.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

$flash = sales_take_flash();
portal_page_start($conn, 'Portal Requests', 'portal_requests');
echo sales_flash_html($flash);

if (isset($_GET['id'])) {
    $r = sales_row($conn, 'SELECT r.*, c.customer_name, c.mobile FROM portal_requests r LEFT JOIN sales_customers c ON c.customer_id=r.customer_id WHERE r.request_id=?', 'i', [(int) $_GET['id']]);
    if ($r) {
        $msgs = sales_rows($conn, 'SELECT * FROM portal_request_messages WHERE request_id=? ORDER BY message_id', 'i', [(int) $r['request_id']]);
        echo '<div class="summary"><div>Request<b>' . sales_h($r['request_no']) . '</b></div><div>Type<b>' . sales_h(PORTAL_TYPES[$r['request_type']] ?? $r['request_type']) . '</b></div>'
            . '<div>Customer<b>' . ($r['customer_id'] ? '<a href="customer_profile.php?id=' . (int) $r['customer_id'] . '">' . sales_h($r['customer_name']) . '</a>' : '<span class="muted">Not identified</span>') . '</b></div>'
            . '<div>Mobile / Contact<b>' . sales_h($r['mobile'] ?: $r['contact']) . '</b></div><div>Status<b>' . sales_h(ucfirst($r['status'])) . '</b></div></div>';
        echo '<h3>' . sales_h($r['subject']) . '</h3>';
        if ($r['request_type'] === 'password') {
            echo '<div class="alert" style="background:#fff6df;color:#7a5b00">Check the caller is the customer, then reset the password in <a href="portal_accounts.php?q=' . urlencode((string) ($r['mobile'] ?: $r['contact'])) . '&amp;filter=with">Portal Accounts</a> and tell them the temporary password by phone.'
                . ($r['customer_id'] ? '' : ' No portal account matches this number.') . '</div>';
        }
        foreach ($msgs as $m) {
            echo '<div class="section" style="' . ($m['sender'] === 'staff' ? 'background:#f2f7fd' : '') . '"><div class="muted">' . ($m['sender'] === 'staff' ? sales_h($m['staff_name'] ?: 'Staff') : 'Customer') . ' · ' . sales_h(date('d/m/Y h:i A', strtotime($m['created_at']))) . '</div><div style="white-space:pre-wrap">' . sales_h($m['body']) . '</div></div>';
        }
        if (cm_can($conn, 'edit')) {
            echo '<form method="post" class="section">' . sales_csrf_field() . '<input type="hidden" name="request_id" value="' . (int) $r['request_id'] . '"><div class="field"><label for="body">Reply to the customer</label><textarea id="body" name="body" maxlength="4000" style="height:110px"></textarea></div>'
                . '<div class="toolbar" style="margin-top:8px"><button class="btn primary">Send reply</button><button class="btn" name="status" value="closed" formnovalidate>Close request</button>' . ($r['status'] === 'closed' ? '<button class="btn" name="status" value="open" formnovalidate>Reopen</button>' : '') . '</div></form>';
        }
        echo '<p><a href="' . $self . '">All requests</a></p>';
        mgmt_page_end();
        exit;
    }
}

$status = in_array($_GET['status'] ?? '', ['open', 'answered', 'closed', 'all'], true) ? (string) $_GET['status'] : 'open';
$type = array_key_exists($_GET['type'] ?? '', PORTAL_TYPES) ? (string) $_GET['type'] : '';
$where = [$status === 'all' ? '1=1' : 'r.status=?'];
$types = $status === 'all' ? '' : 's';
$params = $status === 'all' ? [] : [$status];
if ($type !== '') {
    $where[] = 'r.request_type=?';
    $types .= 's';
    $params[] = $type;
}
$rows = sales_list_rows($conn, 'SELECT r.*, c.customer_name, c.mobile, (SELECT COUNT(*) FROM portal_request_messages m WHERE m.request_id=r.request_id) AS messages FROM portal_requests r LEFT JOIN sales_customers c ON c.customer_id=r.customer_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.updated_at DESC', $types, $params);
$open = (int) sales_row($conn, "SELECT COUNT(*) AS n FROM portal_requests WHERE status='open'")['n'];
?>
<form method="get" class="list-search" style="align-items:center"><select class="input" name="status" style="max-width:200px"><?php foreach (['open' => 'Waiting for staff (' . $open . ')', 'answered' => 'Answered', 'closed' => 'Closed', 'all' => 'All'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
<select class="input" name="type" style="max-width:200px"><option value="">All types</option><?php foreach (PORTAL_TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $type === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><button class="btn primary">Show</button></form>
<div class="table-scroll" style="max-height:600px"><table><thead><tr><th>No</th><th>Received</th><th>Customer</th><th>Type</th><th>Subject</th><th class="num">Messages</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="9" class="empty">No requests.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td><?= sales_h($r['request_no']) ?></td><td><?= sales_h(inv_fmt_date($r['created_at'])) ?></td><td><?= sales_h($r['customer_name'] ?: $r['contact']) ?></td><td><?= sales_h(PORTAL_TYPES[$r['request_type']] ?? $r['request_type']) ?></td><td><?= sales_h($r['subject']) ?></td>
<td class="num"><?= (int) $r['messages'] ?></td><td><span class="badge <?= ['open' => 'open', 'answered' => 'converted', 'closed' => ''][$r['status']] ?>"><?= sales_h($r['status'] === 'open' ? ($r['last_reply_by'] === 'customer' ? 'Waiting for staff' : 'Open') : ucfirst($r['status'])) ?></span></td>
<td><?= sales_h(date('d/m/Y h:i A', strtotime($r['updated_at']))) ?></td><td><a class="btn small" href="<?= $self ?>?id=<?= (int) $r['request_id'] ?>">Open</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php
mgmt_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
