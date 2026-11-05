<?php
/**
 * Shared by the two admin layouts, topmain.php (top menu) and treemain.php (side menu): sign-in check, the menu the user may
 * open (billing/menu_catalog.php, filtered by role, User Access Control and the licence), the first page, the branch list and
 * the brand. The layout in use is chosen in Settings > Company & Login Settings > Appearance; opening the other one moves there.
 */
require_once "../addon/conf/connection.php";
require_once "../addon/conf/VersionControl.php";
$GLOBALS['StatusBar']="ta381";require_once "StatusBar.php";if($GLOBALS['StatusBar']!="411"){header("location:../index.php");}



if(!isset($_COOKIE['user_id_admin'])){header("location:../index.php");exit;}

// One layout for everyone: the one in Settings. A bookmark to the other layout opens this one.
if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== perm_shell()) {
    header('Location: ' . perm_shell());
    exit;
}





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
