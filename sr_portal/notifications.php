<?php
/** My notifications. ?open=ID marks one read and goes to what it is about. */
require_once __DIR__ . '/sr_common.php';
$a = sr_require_login();
$reader = ['type' => 'sr', 'id' => (int) $a['sr_id']];
if (isset($_GET['open'])) {
    $link = notify_open($reader, (int) $_GET['open']);
    if ($link !== null) {
        cp_redirect($link !== '' ? '../' . $link : 'notifications.php');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cp_check_csrf('notifications.php');
    notify_read_all($reader);
    cp_redirect('notifications.php', 'ok', 'All marked as read.');
}
sr_page_start('Notifications', 'notifications');
$rows = notify_list($reader);
if (array_filter($rows, fn($n) => $n['read_at'] === null)) {
    echo '<form method="post" class="tabs">' . cp_csrf_field() . '<button class="btn small light">Mark All As Read</button></form>';
}
echo '<div class="scroll"><table><tr><th>Notification</th><th>When</th><th></th></tr>';
foreach ($rows as $n) {
    $new = $n['read_at'] === null;
    echo '<tr><td>' . ($new ? '<b>' . cp_h($n['title']) . '</b> <span class="badge warn">New</span>' : cp_h($n['title'])) . ($n['body'] !== '' ? '<br><span class="muted">' . cp_h($n['body']) . '</span>' : '') . '</td><td class="num">' . cp_h(notify_age($n['created_at'])) . '</td><td><a class="btn small" href="notifications.php?open=' . (int) $n['notification_id'] . '">' . ($n['link'] !== '' ? 'Open' : 'Mark Read') . '</a></td></tr>';
}
echo ($rows ? '' : '<tr><td colspan="3" class="empty">No notifications.</td></tr>') . '</table></div>';
sr_page_end();
