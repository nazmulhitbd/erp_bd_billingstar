<?php
/** Notifications: everything for the signed-in person, office or staff. ?open=ID marks one read and goes to what it is about. */
require_once dirname(__DIR__) . '/trader/trader_common.php';
require_once dirname(__DIR__, 2) . '/addon/inc/notify.php';

$reader = notify_shell_reader(perm_user());
if (isset($_GET['open'])) {
    $link = notify_open($reader, (int) $_GET['open']);
    if ($link) {
        header('Location: ../../' . $link);
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf('notifications.php');
    notify_read_all($reader);
    sales_flash_redirect('notifications.php', 'success', 'All marked as read.');
}
$flash = sales_take_flash();
$rows = notify_list($reader);
mgmt_page_start('Notifications', [], 'Notifications', '');
echo sales_flash_html($flash);
?>
<form method="post" class="toolbar" style="margin-bottom:8px"><?= sales_csrf_field() ?><button class="btn" name="action" value="read_all"<?= array_filter($rows, fn($n) => $n['read_at'] === null) ? '' : ' disabled' ?>>Mark All As Read</button></form>
<div class="table-scroll"><table><thead><tr><th>Notification</th><th>When</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $n): $new = $n['read_at'] === null; ?><tr><td><?= $new ? '<b>' . sales_h($n['title']) . '</b> <span class="badge open">New</span>' : sales_h($n['title']) ?><?= $n['body'] !== '' ? '<br><span class="muted">' . sales_h($n['body']) . '</span>' : '' ?></td><td class="nowrap"><?= sales_h(notify_age($n['created_at'])) ?></td><td><a class="btn small" href="notifications.php?open=<?= (int) $n['notification_id'] ?>"><?= $n['link'] !== '' ? 'Open' : 'Mark Read' ?></a></td></tr><?php endforeach; ?>
<?= $rows ? '' : '<tr><td colspan="3" class="empty">No notifications.</td></tr>' ?>
</tbody></table></div>
<?php mgmt_page_end(); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
