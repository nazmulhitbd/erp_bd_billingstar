<?php
/**
 * Customer Portal bootstrap: customer sign-in, layout and helpers on top of addon/inc/portal_base.php.
 * The portal is public, so it has its own session (not the Billing admin login) and every query a customer can trigger
 * is limited to the customer_id of the signed-in account. Tables: customer_portal/schema.sql.
 */
const PORTAL_SESSION_NAME = 'CPSESSID';
require_once dirname(__DIR__) . '/addon/inc/portal_base.php';

/** Customer accounts for the shared sign-in rules. */
const CP_AUTH = ['accounts' => 'portal_accounts', 'log' => 'portal_login_log', 'session' => 'cp_account',
    'mobile_sql' => "SELECT a.* FROM portal_accounts a JOIN sales_customers c ON c.customer_id=a.customer_id WHERE RIGHT(REGEXP_REPLACE(c.mobile, '[^0-9]', ''), 10)=? LIMIT 1"];

function cp_log_attempt($accountId, $login, $success, $reason)
{
    cp_auth_log(CP_AUTH, $accountId, $login, $success, $reason);
}

function cp_sign_in($login, $password)
{
    return cp_auth_sign_in(CP_AUTH, $login, $password);
}

function cp_ready()
{
    static $ready = null;
    if ($ready === null) {
        $ready = true;
        foreach (['portal_accounts', 'portal_login_log', 'portal_requests', 'portal_request_messages', 'portal_notices', 'portal_settings', 'customer_accounts', 'customer_ledger_entries', 'sales_invoices'] as $t) {
            $ready = $ready && cp_table_exists($t);
        }
    }
    return $ready;
}

function cp_setting($key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = cp_table_exists('portal_settings') ? array_column(cp_rows('SELECT setting_key, setting_value FROM portal_settings'), 'setting_value', 'setting_key') : [];
    }
    return $cache[$key] ?? $default;
}

/** Company name and contact for headers and prints (Accounts Configuration when installed). */
function cp_company()
{
    $c = ['name' => cp_setting('portal_title', 'Customer Portal'), 'address' => '', 'phone' => cp_setting('contact_phone'), 'email' => cp_setting('contact_email')];
    if (cp_table_exists('acc_settings')) {
        foreach (cp_rows("SELECT setting_key, setting_value FROM acc_settings WHERE setting_key IN ('company_name','company_address','company_phone','company_email')") as $r) {
            if ($r['setting_value'] !== '') {
                $c[substr($r['setting_key'], 8)] = $r['setting_value'];
            }
        }
    }
    return $c;
}

/** The signed-in account with its customer, or null. Ends the session after CP_IDLE_MINUTES or when the account is disabled. */
function cp_account()
{
    static $acc = false;
    if ($acc !== false) {
        return $acc;
    }
    $acc = null;
    $id = (int) ($_SESSION['cp_account'] ?? 0);
    if ($id <= 0 || !cp_ready()) {
        return null;
    }
    if (time() - (int) ($_SESSION['cp_seen'] ?? 0) > CP_IDLE_MINUTES * 60) {
        cp_sign_out();
        cp_flash('err', 'You were signed out after ' . CP_IDLE_MINUTES . ' minutes without activity.');
        return null;
    }
    $row = cp_row("SELECT a.account_id, a.login, a.must_change_password, a.status, c.* FROM portal_accounts a JOIN customer_accounts c ON c.customer_id=a.customer_id WHERE a.account_id=? AND a.status='active'", 'i', [$id]);
    if (!$row) {
        cp_sign_out();
        return null;
    }
    $_SESSION['cp_seen'] = time();
    return $acc = $row;
}

/** Stop unless signed in (and, unless $passwordPage, unless the password has been changed from the one staff gave). */
function cp_require_login($passwordPage = false)
{
    if (!cp_ready()) {
        cp_page_start('Portal not installed', '');
        echo '<div class="alert err">The customer portal is not installed yet. Staff: import <code>customer_portal/schema.sql</code> after <code>billing/customer/schema.sql</code>.</div>';
        cp_page_end();
        exit;
    }
    $a = cp_account();
    if (!$a) {
        cp_redirect('login.php');
    }
    licence_portal_guard('customer_portal');
    if (!$passwordPage && (int) $a['must_change_password'] === 1) {
        cp_redirect('profile.php#password', 'err', 'Please choose your own password before continuing.');
    }
    return $a;
}

/* ---------------- Layout ---------------- */

const CP_MENU = ['index' => ['index.php', 'Dashboard'], 'invoices' => ['invoices.php', 'Invoices'], 'statement' => ['statement.php', 'Statement'], 'orders' => ['orders.php', 'Orders & Quotations'],
    'payments' => ['payments.php', 'Payments'], 'warranty' => ['warranty.php', 'Warranty & Service'], 'requests' => ['requests.php', 'Requests'], 'profile' => ['profile.php', 'Profile']];

function cp_page_start($title, $active, $public = false)
{
    $c = cp_company();
    $a = $public ? null : cp_account();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . cp_h($title . ' · ' . $c['name']) . '</title>'
        . '<link rel="stylesheet" href="../addon/portal/portal.css"></head><body>';
    if (empty($_SESSION['shell_embedded'])) {
        echo '<header class="top"><div class="brand"><b>' . cp_h($c['name']) . '</b><span>' . cp_h(cp_setting('portal_title', 'Customer Portal')) . '</span></div>';
        if ($a) {
            $unread = notify_unread(['type' => 'customer', 'id' => (int) $a['customer_id']]);
            echo '<div class="who"><a class="bell" href="notifications.php" aria-label="Notifications' . ($unread ? ', ' . $unread . ' unread' : '') . '">&#128276;' . ($unread ? '<span>' . $unread . '</span>' : '') . '</a><span>' . cp_h($a['customer_name']) . '</span><a class="btn light" href="logout.php">Sign out</a></div>';
        }
        echo '</header>';
        if ($a) {
            echo '<nav class="menu">';
            foreach (CP_MENU as $key => [$href, $label]) {
                echo '<a href="' . $href . '"' . ($key === $active ? ' class="active"' : '') . '>' . cp_h($label) . '</a>';
            }
            echo '</nav>';
        }
    }
    echo '<main class="page' . ($public ? ' narrow' : '') . '"><h1>' . cp_h($title) . '</h1>' . cp_take_flash();
}

function cp_page_end()
{
    $c = cp_company();
    echo '</main>';
    if (empty($_SESSION['shell_embedded'])) {
        echo '<footer class="foot">' . cp_h($c['name']) . ($c['phone'] ? ' · ' . cp_h($c['phone']) : '') . ($c['email'] ? ' · ' . cp_h($c['email']) : '') . '</footer>';
    }
    echo '<script src="../addon/portal/portal.js"></script><script src="../addon/portal/list-pager.js"></script></body></html>';
}

/** Print page start: company header and the document title. */
function cp_print_start($title)
{
    $c = cp_company();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . cp_h($title) . '</title><link rel="stylesheet" href="../addon/portal/portal.css"></head><body class="print">'
        . '<div class="noprint"><button type="button" class="btn" data-print>Print</button> <button type="button" class="btn light" data-back>Back</button></div>'
        . '<div class="ph"><div><b class="co">' . cp_h($c['name']) . '</b><br>' . cp_h($c['address']) . '</div><div>' . ($c['phone'] ? 'Phone: ' . cp_h($c['phone']) . '<br>' : '') . ($c['email'] ? 'Email: ' . cp_h($c['email']) : '') . '</div></div>'
        . '<h2 class="pt">' . cp_h($title) . '</h2>';
}

function cp_print_end()
{
    echo '<script src="../addon/portal/portal.js"></script></body></html>';
}

/** Next request number like PR20261030001. */
function cp_request_no()
{
    $n = (int) cp_row("SELECT COUNT(*) AS n FROM portal_requests WHERE request_no LIKE ?", 's', ['PR' . date('Ymd') . '%'])['n'];
    do {
        $no = 'PR' . date('Ymd') . str_pad((string) ++$n, 3, '0', STR_PAD_LEFT);
    } while (cp_row('SELECT 1 AS x FROM portal_requests WHERE request_no=?', 's', [$no]));
    return $no;
}

const CP_REQUEST_TYPES = ['quotation' => 'Price quotation', 'support' => 'Product support', 'payment' => 'Payment / statement', 'account' => 'Account details', 'other' => 'Other'];
