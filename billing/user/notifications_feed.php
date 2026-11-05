<?php
/**
 * The bell's data, fetched every minute by topmenu.js. It checks the session but never touches lastaccess, unlike every other
 * page, so an open browser tab cannot keep an idle session alive past Session Idle Time.
 */
require_once dirname(__DIR__, 2) . '/addon/conf/dbconnect.php';
require_once dirname(__DIR__, 2) . '/addon/conf/configuration.php';
require_once dirname(__DIR__, 2) . '/addon/inc/notify.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$u = perm_user();
$idle = max(10, (int) ($session_idle_time ?? 30)) * 60;
if (!$u || (int) $u['lastaccess'] + $idle < time()) {
    http_response_code(401);
    exit('{"signed_in":false}');
}
licence_tick(); // an open screen keeps checking, even if nobody clicks anything
if (licence_status()['locked']) {
    exit('{"signed_in":true,"locked":true}');
}
$reader = notify_shell_reader($u);
$rows = $reader ? notify_list($reader) : [];
$unread = array_values(array_filter($rows, fn($n) => $n['read_at'] === null));
echo json_encode(['signed_in' => true, 'installed' => notify_installed(), 'unread' => count($unread), 'items' => array_map(fn($n) => ['id' => (int) $n['notification_id'], 'title' => $n['title'], 'body' => $n['body'], 'age' => notify_age($n['created_at']), 'unread' => $n['read_at'] === null], array_slice($rows, 0, 8))]);
