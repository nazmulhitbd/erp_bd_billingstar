<?php
/**
 * Opens a page from a link token, so links show no file paths: k = menu page (perm_link), t = link inside a page
 * (perm_page_token). Tokens only hide the path: page rights are checked by the page itself.
 */
require_once "../addon/conf/connection.php";
if (isset($_GET['t'])) {
    $target = perm_page_target((string) $_GET['t']);
    $path = strtok($target, '?');
    $target = $target !== '' && is_file(dirname(__DIR__) . '/' . $path) ? '../' . $target : '';
} else {
    $target = perm_link_target(preg_replace('/[^a-f0-9]/', '', (string) ($_GET['k'] ?? '')));
}
header('Location: ' . ($target !== '' ? $target : 'user/no_access.php'));
exit;
