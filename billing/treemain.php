<?php
/*
 * Side menu layout: the same menu, permissions and pages as topmain.php (both come from shell_common.php), drawn as a dark
 * sidebar with collapsible categories, and a top bar with the page finder, notifications, the user and the company / branch.
 * Chosen in Settings > Company & Login Settings > Appearance. Behaviour is topmenu.js in its sidebar mode (body.shell-side).
 */
ob_start();
require_once __DIR__ . '/shell_common.php';
$companyRow = perm_q("SELECT setting_value FROM acc_settings WHERE setting_key='company_name'");
$companyName = trim((string) ($companyRow[0]['setting_value'] ?? '')) ?: strip_tags((string) $top_menu_deading);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $h($main_title); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<meta name="ui-common-css" content="ui-common.css?v=<?php echo (int) @filemtime(__DIR__ . '/ui-common.css'); ?>">
<meta name="list-pager-js" content="../addon/portal/list-pager.js?v=<?php echo (int) @filemtime(__DIR__ . '/../addon/portal/list-pager.js'); ?>">
<link rel="stylesheet" href="topmenu.css?v=<?php echo (int) @filemtime(__DIR__ . '/topmenu.css'); ?>">
<link rel="stylesheet" href="treemenu.css?v=<?php echo (int) @filemtime(__DIR__ . '/treemenu.css'); ?>">
<script>
try { if (localStorage.getItem('shell_side_collapsed') === '1') document.documentElement.classList.add('nav-collapsed'); } catch (e) {}
function calcHeight() {
  var frame = document.getElementById('frm_main');
  try {
    var doc = frame.contentWindow.document;
    var page = doc.querySelector('.ui-page, .rma-wrap, .dash'); // measured directly so the frame can shrink again after a rotate
    var h = page ? page.offsetHeight + 20 : doc.body.scrollHeight;
    frame.height = Math.max(h, window.innerHeight - frame.getBoundingClientRect().top - 50, 500);
  } catch (e) { frame.height = 800; }
}
function test(url) { window.open(url, 'frm_main'); }
</script>
</head>
<body class="shell-side"<?php echo !empty($index_bgcolor) ? ' style="--brand:' . $h($index_bgcolor) . '"' : ''; ?>>
<?php $licence = licence_status(); if ($licence['message'] !== '' || ($licence['data']['plan'] ?? '') === 'Demo'): ?>
<div class="licence-bar" role="status"><?php echo $h(trim((($licence['data']['plan'] ?? '') === 'Demo' ? 'Demo version. ' : '') . $licence['message'])); ?></div>
<?php endif; ?>
<div class="side-layout">
<aside class="app-nav side-nav" id="navigation" aria-label="Main menu">
  <a class="brand side-brand" href="<?php echo $h(perm_link($home)); ?>" target="frm_main" data-path="<?php echo $h($menu[0]['label'] ?? 'Home'); ?>">
    <span class="brand-mark"><?php if ($shellLogo !== ''): ?><img src="../<?php echo $h($shellLogo); ?>" alt=""><?php else: ?><i class="bi bi-receipt-cutoff"></i><?php endif; ?></span>
    <span class="brand-name"><?php echo $top_menu_deading; ?></span>
  </a>
  <ul class="nav-list">
<?php foreach ($menu as $i => $cat): ?>
<?php if (isset($cat['url'])): ?>
    <li class="nav-cat"><a class="nav-top" href="<?php echo $h(perm_link($cat['url'])); ?>" target="frm_main" data-path="<?php echo $h($cat['label']); ?>" title="<?php echo $h($cat['label']); ?>"><i class="bi bi-<?php echo $h($cat['icon']); ?>"></i><span><?php echo $h($cat['label']); ?></span></a></li>
<?php else: $single = count($cat['groups']) === 1; ?>
    <li class="nav-cat has-panel">
      <button type="button" class="nav-top" aria-expanded="false" aria-controls="panel-<?php echo $i; ?>" title="<?php echo $h($cat['label']); ?>"><i class="bi bi-<?php echo $h($cat['icon']); ?>"></i><span><?php echo $h($cat['label']); ?></span><i class="bi bi-chevron-down caret"></i></button>
      <div class="nav-panel" id="panel-<?php echo $i; ?>" data-cat="<?php echo $h($cat['label']); ?>">
<?php foreach ($cat['groups'] as $g): ?>
        <section class="nav-group">
<?php if (!$single): ?>          <h6><?php echo $h($g['title']); ?></h6>
<?php endif; ?>
          <ul>
<?php foreach ($g['items'] as $item): ?>
            <li><a href="<?php echo $h(perm_link($item[1])); ?>" target="frm_main" data-path="<?php echo $h($cat['label'] . ' › ' . $g['title'] . ' › ' . $item[0]); ?>"><?php echo $h($item[0]); ?></a></li>
<?php endforeach; ?>
          </ul>
        </section>
<?php endforeach; ?>
      </div>
    </li>
<?php endif; ?>
<?php endforeach; ?>
  </ul>
  <button type="button" class="side-collapse" aria-label="Collapse the menu"><i class="bi bi-chevron-left"></i><span>Collapse</span></button>
</aside>
<div class="side-backdrop" hidden></div>

<div class="side-main">
<header class="app-header side-top">
  <button type="button" class="nav-toggle" aria-controls="navigation" aria-expanded="false" title="Menu"><i class="bi bi-list"></i></button>
  <div class="menu-search">
    <i class="bi bi-search"></i>
    <input type="search" id="menu-search" class="form-control" placeholder="Find a page, report or setting…" autocomplete="off" aria-label="Find a page">
    <kbd>/</kbd>
    <ul id="menu-results" class="shadow" hidden></ul>
  </div>
  <div class="header-tools">
    <div class="bell-menu" id="bell" data-feed="user/notifications_feed.php">
      <button type="button" class="bell-btn" aria-expanded="false" aria-label="Notifications"><i class="bi bi-bell"></i><span class="bell-count" hidden></span></button>
      <div class="bell-drop shadow">
        <div class="bell-head"><b>Notifications</b><a href="user/notifications.php" target="frm_main" data-path="Notifications">See all</a></div>
        <div class="bell-list"></div>
      </div>
    </div>
    <div class="user-menu">
      <button type="button" class="user-chip" aria-expanded="false"><span class="avatar"><?php echo strtoupper(substr($loginName, 0, 2)); ?></span><span class="user-who d-none d-md-flex"><b><?php echo $loginName; ?></b><small><?php echo $h($userLevel); ?></small></span><i class="bi bi-chevron-down small"></i></button>
      <div class="user-drop shadow">
        <div class="user-drop-head"><span class="avatar lg"><?php echo strtoupper(substr($loginName, 0, 2)); ?></span><div><b><?php echo $loginName; ?></b><small><?php echo $h($userLevel); ?></small></div></div>
<?php if (perm_can('user/registration.php', 'view')): ?>
        <a href="<?php echo $h(perm_link('user/registration.php')); ?>" target="frm_main" data-path="Settings › Users, Roles & Permissions"><i class="bi bi-person-gear"></i> Users</a>
<?php endif; ?>
<?php if (perm_is_super()): ?>
        <a href="<?php echo $h(perm_link('user/company_settings.php')); ?>&amp;tab=appearance" target="frm_main" data-path="Settings › Company & Login Settings"><i class="bi bi-layout-sidebar"></i> Layout</a>
<?php endif; ?>
        <a href="logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </div>
    <div class="company-box d-none d-lg-flex">
      <b title="<?php echo $h($companyName); ?>"><?php echo $h($companyName); ?></b>
<?php if (count($branchNames) > 1): ?>
      <form method="post" action="user/switch_branch.php" class="branch-switch" title="Working branch">
        <select name="branch_id" aria-label="Working branch" onchange="this.form.submit()">
<?php foreach ($branchNames as $id => $name): ?>
          <option value="<?php echo (int) $id; ?>"<?php echo (int) $id === $currentBranch ? ' selected' : ''; ?>><?php echo $h($name); ?></option>
<?php endforeach; ?>
        </select>
      </form>
<?php elseif ($branchNames): ?>
      <small><i class="bi bi-diagram-3"></i> <?php echo $h(reset($branchNames)); ?></small>
<?php endif; ?>
    </div>
  </div>
</header>

<div class="crumb-bar"><i class="bi bi-house-door"></i><span id="breadcrumb"><b class="crumb-page"><?php echo $h($menu[0]['label'] ?? 'Home'); ?></b></span><span class="clock ms-auto d-none d-md-inline" title="Server time"><i class="bi bi-clock"></i> <?php echo $h($time); ?></span></div>
<main id="content">
  <iframe name="frm_main" id="frm_main" onload="calcHeight()" src="<?php echo $h(perm_link($home)); ?>" title="Page"></iframe>
</main>

<footer class="app-footer">
  <span><i class="bi bi-person-circle"></i> Signed in as <b><?php echo $loginName; ?></b></span>
  <span><?php if (!empty($finalversion)) { echo StatusBarMMSS(); } else { echo 'Demo Version and Multiple Login Allowed on Admin.'; } ?></span>
</footer>
</div>
</div>
<script src="topmenu.js?v=<?php echo (int) @filemtime(__DIR__ . '/topmenu.js'); ?>"></script>
</body>
</html>
<?php include "../addon/conf/connection_close.php";  ?>
