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
    // A menu page opened on one of its tabs, such as Company & Login Settings › Appearance.
    if ($target !== '' && preg_match('/^[a-z]{1,20}$/', (string) ($_GET['tab'] ?? ''))) {
        $target .= (str_contains($target, '?') ? '&' : '?') . 'tab=' . $_GET['tab'];
    }
}
header('Location: ' . ($target !== '' ? $target : 'user/no_access.php'));
exit;
