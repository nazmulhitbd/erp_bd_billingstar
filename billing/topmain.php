<?php
ob_start();
?>
<!DOCTYPE html>
<?php
require_once "../addon/conf/connection.php";
require_once "../addon/conf/VersionControl.php";
$GLOBALS['StatusBar']="ta381";require_once "StatusBar.php";if($GLOBALS['StatusBar']!="411"){header("location:../index.php");}



if(!isset($_COOKIE['user_id_admin'])){header("location:../index.php");}



$today=date("Y-m-d-g");
$today_array=explode("-",$today);

if(isset($_GET['page'])) $page= $_GET['page'];
else $page="home.php";

$sql="select DATE_FORMAT(CURRENT_TIMESTAMP,'%W %M,%d,%Y %r')";
$res=mysql_query_moon($sql);
$row=mysql_fetch_row($res);
$time=$row[0];

if(isset($_COOKIE['user_id_admin']))
{
    $cinfo_sql="SELECT user_config, role
                FROM  admin_user,admin_role
                where admin_user.user_role_id=admin_role.user_role_id and user_id=".(int)$_COOKIE['user_id_admin'];
    $cinfo_res=mysql_query_moon($cinfo_sql);
    $cinfo_row=mysql_fetch_row($cinfo_res);
    // define value
    $user_config_=explode(',',(string)($cinfo_row[0] ?? ''));
    $role_=explode(',',(string)($cinfo_row[1] ?? ''));
    //end define value
}

?>
<?php
/*
 * Top menu, drawn from billing/menu_catalog.php. Each category opens a panel of groups (columns); each group lists pages as
 * [label, url, rule]. A category may instead be a single page (['label', 'icon', 'url']). Pages open in the frm_main iframe.
 * SuperAdmin menus still follow the role rules; everyone else sees the forms they hold in User Access Control. Customers
 * and employees also get their own portal pages. Empty groups and categories are not shown.
 */
// The permission arrays are loaded above; default them so a missing login cannot raise warnings here.
foreach (['user_config_', 'role_'] as $flagName) {
    if (!isset($$flagName)) {
        $$flagName = [];
    }
}
$can = function ($flags, $i = 0) {
    return is_array($flags) && isset($flags[$i]) && (string) $flags[$i] === '1';
};
// Customer Management / Sales Person Management permissions (view, add, edit, delete, credit|commission, ledger|targets).
// Columns come from customer/schema.sql and sales_person/schema.sql; until then everything is allowed.
$mgmtFlags = ['customer_mgmt' => [1, 1, 1, 1, 1, 1], 'sales_person_mgmt' => [1, 1, 1, 1, 1, 1]];
$mgmtRes = isset($_COOKIE['user_id_admin']) ? @mysql_query_moon_normal('SELECT r.customer_mgmt, r.sales_person_mgmt FROM admin_user u JOIN admin_role r ON r.user_role_id=u.user_role_id WHERE u.user_id=' . (int) $_COOKIE['user_id_admin']) : false;
if ($mgmtRes && ($mgmtRow = mysql_fetch_row($mgmtRes))) {
    $mgmtFlags = ['customer_mgmt' => explode(',', (string) $mgmtRow[0]), 'sales_person_mgmt' => explode(',', (string) $mgmtRow[1])];
}
$legacyRule = function ($rule) use ($can, $mgmtFlags, $user_config_, $role_) {
    [$kind, $i] = array_pad(explode(':', (string) $rule, 2), 2, 0);
    return match ($kind) {
        'cm' => $can($mgmtFlags['customer_mgmt'], (int) $i),
        'sp' => $can($mgmtFlags['sales_person_mgmt'], (int) $i),
        'uc' => $can($user_config_, (int) $i),
        'role' => $can($role_, (int) $i),
        default => true,
    };
};

$shellUser = perm_user();
$userType = $shellUser['user_type'] ?? 'staff';
$userLevel = $userType === 'staff' ? ($shellUser['access_level'] ?? 'Administrator') : ucfirst($userType);
$showItem = function ($item, array $group) use ($legacyRule) {
    if (perm_is_super()) {
        return $legacyRule($item[2] ?? '');
    }
    if (empty($group['registers'])) {
        return perm_can($item[1], 'view');
    }
    foreach (array_keys(menu_register_reports($item[1])) as $key) {
        if (perm_can($key, 'view')) {
            return true;
        }
    }
    return false;
};
$menu = [];
if ($userType !== 'staff') {
    $portal = menu_portal_pages($userType);
    foreach ($portal['pages'] as [$label, $icon, $file]) {
        if ($userType === 'employee' && !in_array($file, MENU_STAFF_PAGES, true) && !empty(perm_q('SELECT staff_only FROM sales_representatives WHERE sr_id=?', 'i', [(int) ($shellUser['linked_id'] ?? 0)])[0]['staff_only'])) {
            continue;
        }
        $menu[] = ['label' => $label, 'icon' => $icon, 'url' => $portal['dir'] . $file];
    }
}
if ($userType !== 'customer') {
    foreach (menu_catalog() as $cat) {
        if (!licence_module_on(licence_slug($cat['label']))) {
            continue;
        }
        if (isset($cat['url'])) {
            if (perm_can($cat['url'], 'view')) {
                $menu[] = $cat;
            }
            continue;
        }
        foreach ($cat['groups'] as &$g) {
            $g['items'] = array_map(fn($i) => [$i[0], $i[1], $showItem($i, $g)], $g['items']);
            if (!empty($g['merge'])) {
                $held = array_values(array_filter($g['items'], fn($i) => $i[2]));
                $g['items'] = $held ? [[$g['merge'], $held[0][1], true]] : [];
            }
        }
        unset($g);
        $menu[] = $cat;
    }
}

/** Drop pages the user may not open, then empty groups and empty categories. */
function topmenu_prune(array $menu)
{
    $out = [];
    foreach ($menu as $cat) {
        if (isset($cat['groups'])) {
            $groups = [];
            foreach (array_filter($cat['groups']) as $g) {
                $g['items'] = array_values(array_filter($g['items'], fn($i) => !array_key_exists(2, $i) || $i[2]));
                if ($g['items']) {
                    $groups[] = $g;
                }
            }
            if (!$groups) {
                continue;
            }
            $cat['groups'] = $groups;
        }
        $out[] = $cat;
    }
    return $out;
}

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$menu = topmenu_prune($menu);
$loginName = $h($_COOKIE['ID_my_site_admin'] ?? '');
// The first page the user may open replaces the dashboard when they do not have it.
require_once __DIR__ . '/../addon/inc/brand.php';
$shellLogo = brand_info()['logo'];
$home = 'user/no_access.php';
foreach ($menu as $cat) {
    $home = $cat['url'] ?? $cat['groups'][0]['items'][0][1];
    break;
}
$branchNames = $userType === 'customer' ? [] : array_filter(array_column(perm_q('SELECT branch_id, branch_name FROM branches WHERE is_active=1 ORDER BY branch_name'), 'branch_name', 'branch_id'), 'perm_branch_allowed', ARRAY_FILTER_USE_KEY);
$currentBranch = perm_current_branch() ?: (int) array_key_first($branchNames);
?>
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
