<?php
ob_start();
require_once __DIR__ . '/shell_common.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $h($main_title); ?></title>
<meta name="Author" content="MoonNetworks">
<meta name="Description" content="MoonBilling">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<meta name="ui-common-css" content="ui-common.css?v=<?php echo (int) @filemtime(__DIR__ . '/ui-common.css'); ?>">
<meta name="list-pager-js" content="../addon/portal/list-pager.js?v=<?php echo (int) @filemtime(__DIR__ . '/../addon/portal/list-pager.js'); ?>">
<link rel="stylesheet" href="topmenu.css?v=<?php echo (int) @filemtime(__DIR__ . '/topmenu.css'); ?>">
<script>
function calcHeight() {
  var frame = document.getElementById('frm_main');
  try {
    var doc = frame.contentWindow.document;
    var page = doc.querySelector('.ui-page, .rma-wrap'); // measured directly so the frame can shrink again after a rotate
    var h = page ? page.offsetHeight + 20 : doc.body.scrollHeight;
    frame.height = Math.max(h, window.innerHeight - frame.getBoundingClientRect().top - 40, 500);
  } catch (e) { frame.height = 800; }
}
function test(url) { window.open(url, 'frm_main'); }
</script>
</head>
<body>
<?php $licence = licence_status(); if ($licence['message'] !== '' || ($licence['data']['plan'] ?? '') === 'Demo'): ?>
<div class="licence-bar" role="status"><?php echo $h(trim((($licence['data']['plan'] ?? '') === 'Demo' ? 'Demo version. ' : '') . $licence['message'])); ?></div>
<?php endif; ?>
<header class="app-header"<?php echo !empty($index_bgcolor) ? ' style="--brand:' . $h($index_bgcolor) . '"' : ''; ?>>
  <button type="button" class="nav-toggle btn btn-light" aria-controls="navigation" aria-expanded="false" title="Menu"><i class="bi bi-list"></i></button>
  <a class="brand" href="<?php echo $h(perm_link($home)); ?>" target="frm_main" data-path="<?php echo $h($menu[0]['label'] ?? 'Home'); ?>">
    <span class="brand-mark"><?php if ($shellLogo !== ''): ?><img src="../<?php echo $h($shellLogo); ?>" alt=""><?php else: ?><i class="bi bi-receipt-cutoff"></i><?php endif; ?></span>
    <span class="brand-text"><span class="brand-name"><?php echo $top_menu_deading; ?></span><span class="brand-slogan"><?php echo $top_menu_slogan; ?></span></span>
  </a>
  <div class="menu-search">
    <i class="bi bi-search"></i>
    <input type="search" id="menu-search" class="form-control" placeholder="Find a page… (press /)" autocomplete="off" aria-label="Find a page">
    <ul id="menu-results" class="shadow" hidden></ul>
  </div>
  <div class="header-tools">
<?php if (count($branchNames) > 1): ?>
    <form method="post" action="user/switch_branch.php" class="branch-switch" title="Working branch">
      <i class="bi bi-diagram-3"></i>
      <select name="branch_id" class="form-select form-select-sm" aria-label="Working branch" onchange="this.form.submit()">
<?php foreach ($branchNames as $id => $name): ?>
        <option value="<?php echo (int) $id; ?>"<?php echo (int) $id === $currentBranch ? ' selected' : ''; ?>><?php echo $h($name); ?></option>
<?php endforeach; ?>
      </select>
    </form>
<?php elseif ($branchNames): ?>
    <span class="clock d-none d-lg-inline" title="Working branch"><i class="bi bi-diagram-3"></i> <?php echo $h(reset($branchNames)); ?></span>
<?php endif; ?>
    <span class="clock d-none d-lg-inline" title="Server time"><i class="bi bi-clock"></i> <?php echo $h($time); ?></span>
    <div class="bell-menu" id="bell" data-feed="user/notifications_feed.php">
      <button type="button" class="bell-btn" aria-expanded="false" aria-label="Notifications"><i class="bi bi-bell"></i><span class="bell-count" hidden></span></button>
      <div class="bell-drop shadow">
        <div class="bell-head"><b>Notifications</b><a href="user/notifications.php" target="frm_main" data-path="Notifications">See all</a></div>
        <div class="bell-list"></div>
      </div>
    </div>
    <div class="user-menu">
      <button type="button" class="user-chip" aria-expanded="false"><span class="avatar"><?php echo strtoupper(substr($loginName, 0, 1)); ?></span><span class="d-none d-md-inline"><?php echo $loginName; ?></span><i class="bi bi-chevron-down small"></i></button>
      <div class="user-drop shadow">
        <div class="user-drop-head"><span class="avatar lg"><?php echo strtoupper(substr($loginName, 0, 1)); ?></span><div><b><?php echo $loginName; ?></b><small><?php echo $h($userLevel); ?></small></div></div>
<?php if (perm_can('user/registration.php', 'view')): ?>
        <a href="<?php echo $h(perm_link('user/registration.php')); ?>" target="frm_main" data-path="Settings › Users, Roles & Permissions"><i class="bi bi-person-gear"></i> Users</a>
<?php endif; ?>
        <a href="logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </div>
  </div>
</header>

<nav class="app-nav" id="navigation" aria-label="Main menu">
  <ul class="nav-list">
<?php foreach ($menu as $i => $cat): ?>
<?php if (isset($cat['url'])): ?>
    <li class="nav-cat"><a class="nav-top" href="<?php echo $h(perm_link($cat['url'])); ?>" target="frm_main" data-path="<?php echo $h($cat['label']); ?>"><i class="bi bi-<?php echo $h($cat['icon']); ?>"></i><span><?php echo $h($cat['label']); ?></span></a></li>
<?php else: $cols = min(count($cat['groups']), 4); ?>
    <li class="nav-cat has-panel">
      <button type="button" class="nav-top" aria-expanded="false" aria-controls="panel-<?php echo $i; ?>"><i class="bi bi-<?php echo $h($cat['icon']); ?>"></i><span><?php echo $h($cat['label']); ?></span><i class="bi bi-chevron-down caret"></i></button>
      <div class="nav-panel cols-<?php echo $cols; ?>" id="panel-<?php echo $i; ?>" data-cat="<?php echo $h($cat['label']); ?>">
<?php foreach ($cat['groups'] as $g): ?>
        <section class="nav-group">
          <h6><?php echo $h($g['title']); ?></h6>
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
</nav>

<div class="crumb-bar"><i class="bi bi-house-door"></i><span id="breadcrumb"><b class="crumb-page"><?php echo $h($menu[0]['label'] ?? 'Home'); ?></b></span></div>
<main id="content">
  <iframe name="frm_main" id="frm_main" onload="calcHeight()" src="<?php echo $h(perm_link($home)); ?>" title="Page"></iframe>
</main>

<footer class="app-footer">
  <span><i class="bi bi-person-circle"></i> Signed in as <b><?php echo $loginName; ?></b></span>
  <span><?php if (!empty($finalversion)) { echo StatusBarMMSS(); } else { echo 'Demo Version and Multiple Login Allowed on Admin.'; } ?></span>
</footer>
<script src="topmenu.js?v=<?php echo (int) @filemtime(__DIR__ . '/topmenu.js'); ?>"></script>
</body>
</html>
<?php include "../addon/conf/connection_close.php";  ?>
