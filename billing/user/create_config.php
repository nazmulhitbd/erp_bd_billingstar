<?php
/**
 * Settings > User Config: how the application and its billing behave. Titles and session limits are in client_theme; the
 * sales and stock rules are in inv_settings, which sales, stock and the dashboard read. Company details, the voucher prefix
 * and the accounts rules stay on Company & Login Settings, so nothing here has a second editor. The VoIP settings this page
 * once showed are gone from the screen only: their columns stay, so nothing that still reads them changes.
 */
require_once dirname(__DIR__) . '/trader/trader_common.php';
require_once dirname(__DIR__) . '/inventory/inv_common.php';
require_once dirname(__DIR__, 2) . '/addon/inc/two_step.php';

const CONFIG_MIN_IDLE = 10; // addon/conf/connection.php treats anything lower as 10
const CONFIG_MAX_IDLE = 480;
const CONFIG_MIN_RECORDS = 5;
const CONFIG_MAX_RECORDS = 500;

$rights = perm_q('SELECT r.user_config FROM admin_user u JOIN admin_role r ON r.user_role_id=u.user_role_id WHERE u.user_id=?', 'i', [(int) ($_COOKIE['user_id_admin'] ?? 0)]);
if (!perm_is_super() && (explode(',', (string) ($rights[0]['user_config'] ?? ''))[2] ?? '') !== '1') {
    perm_deny('this page needs the User Config right.');
}
$self = 'create_config.php';
$stores = array_column(sales_rows($conn, 'SELECT store_name FROM sales_stores ORDER BY store_name'), 'store_name');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    $idle = (int) ($_POST['session_idle_time'] ?? 0);
    $records = (int) ($_POST['default_record'] ?? 0);
    $prefix = strtoupper(sales_post_string('doc_prefix', 20)); // longer than allowed, so a long one is refused and not cut short
    $store = sales_post_string('default_store', 140);
    $low = (string) ($_POST['low_stock_level'] ?? '');
    $per100 = (string) ($_POST['member_points_per_100'] ?? '');
    $value = (string) ($_POST['member_point_value'] ?? '');
    $titles = [];
    foreach (['index_title', 'index_heading', 'main_title', 'brand_name', 'slogan'] as $key) {
        $titles[$key] = sales_post_string($key, 50);
    }
    if ($titles['index_title'] === '' || $titles['brand_name'] === '') {
        sales_flash_redirect($self, 'error', 'Enter the Index Title and the Brand Name.');
    }
    if ($idle < CONFIG_MIN_IDLE || $idle > CONFIG_MAX_IDLE) {
        sales_flash_redirect($self, 'error', 'Session Idle Time must be ' . CONFIG_MIN_IDLE . ' to ' . CONFIG_MAX_IDLE . ' minutes.');
    }
    if ($records < CONFIG_MIN_RECORDS || $records > CONFIG_MAX_RECORDS) {
        sales_flash_redirect($self, 'error', 'Records Per Page must be ' . CONFIG_MIN_RECORDS . ' to ' . CONFIG_MAX_RECORDS . '.');
    }
    if (!preg_match('/^[A-Z0-9]{1,6}$/', $prefix)) {
        sales_flash_redirect($self, 'error', 'Document Prefix must be 1 to 6 letters or numbers.');
    }
    if ($store !== '' && !in_array($store, $stores, true)) {
        sales_flash_redirect($self, 'error', 'Choose a store from the list.');
    }
    $posVat = (string) ($_POST['pos_vat_percent'] ?? '');
    foreach ([[$low, 'Low Stock Level'], [$per100, 'Points For Every 100'], [$value, 'Value Of One Point'], [$posVat, 'POS VAT %']] as [$number, $label]) {
        if (!is_numeric($number) || (float) $number < 0) {
            sales_flash_redirect($self, 'error', "$label must be a number, 0 or more.");
        }
    }
    if ((float) $posVat > 100) {
        sales_flash_redirect($self, 'error', 'POS VAT % cannot be more than 100.');
    }
    // Two-step sign-in: the channel must be switched on, and whoever saves must still be able to sign in afterwards.
    $tfa = [];
    foreach (TFA_GROUPS as $group => $label) {
        $tfa[$group] = isset(TFA_METHODS[$_POST['tfa_' . $group] ?? '']) ? $_POST['tfa_' . $group] : 'off';
        foreach (tfa_channels($tfa[$group]) as $channel) {
            if (msg_get($channel === 'sms' ? 'sms_enabled' : 'mail_enabled') !== '1') {
                sales_flash_redirect($self, 'error', "Two-step for $label needs " . ($channel === 'sms' ? 'SMS' : 'email') . ' switched on in Email & SMS first.');
            }
        }
    }
    $me = perm_user();
    if ($me && isset($tfa[$me['user_type']]) && ($missing = tfa_missing($tfa[$me['user_type']], tfa_contacts($me))) !== '') {
        sales_flash_redirect($self, 'error', "Your own login has no $missing for the sign-in code. Add it on Settings > Users first, or you could not sign in again.");
    }
    $tfaMinutes = (int) ($_POST['tfa_minutes'] ?? 0);
    if ($tfaMinutes < 2 || $tfaMinutes > 30) {
        sales_flash_redirect($self, 'error', 'The sign-in code must work for 2 to 30 minutes.');
    }
    sales_exec($conn, 'UPDATE client_theme SET index_title=?, index_heading=?, main_title=?, brand_name=?, slogan=?, session_idle_time=?, default_record_no=? WHERE client_theme_id=1', 'sssssii', [...array_values($titles), $idle, $records]);
    $settings = ['doc_prefix' => $prefix, 'default_store' => $store, 'sales_stock_mode' => ($_POST['sales_stock_mode'] ?? '') === 'off' ? 'off' : 'deduct', 'low_stock_level' => $low, 'member_points_per_100' => $per100, 'member_point_value' => $value,
        'pos_receipt_heading' => sales_post_string('pos_receipt_heading', 60), 'pos_vat_percent' => $posVat, 'pos_vat_mode' => ($_POST['pos_vat_mode'] ?? '') === 'exclusive' ? 'exclusive' : 'inclusive', 'pos_receipt_footer' => sales_post_string('pos_receipt_footer', 255)];
    foreach ($settings as $key => $setting) {
        sales_exec($conn, 'INSERT INTO inv_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', 'ss', [$key, (string) $setting]);
    }
    foreach ($tfa + ['minutes' => $tfaMinutes] as $key => $setting) {
        tfa_put($key, $setting);
    }
    sales_flash_redirect($self, 'success', 'Configuration saved.');
}

$flash = sales_take_flash();
$field = function ($name, $label, $value, $attrs = '') {
    return '<div class="field"><label for="' . $name . '">' . $label . '</label><input id="' . $name . '" name="' . $name . '" value="' . sales_h($value) . '" ' . $attrs . '></div>';
};
$number = 'type="number" step="any" min="0" required';
$prefix = inv_setting($conn, 'doc_prefix', 'DES');
$defaultStore = inv_setting($conn, 'default_store', '');
$stockMode = inv_setting($conn, 'sales_stock_mode', 'deduct');
$posVatMode = inv_setting($conn, 'pos_vat_mode', 'inclusive');
mgmt_page_start('Settings', ['config' => [$self, 'Config']], 'Config', 'config');
echo sales_flash_html($flash);
?>
<form method="post"><?= sales_csrf_field() ?>
<section class="section"><div class="section-title">Application</div>
<div class="grid g2">
<?= $field('index_title', 'Index Title', $index_title, 'maxlength="50" required') ?>
<?= $field('index_heading', 'Index Caption', $index_heading, 'maxlength="50"') ?>
<?= $field('main_title', 'Main Title', $main_title, 'maxlength="50"') ?>
<?= $field('brand_name', 'Brand Name', $top_menu_deading, 'maxlength="50" required') ?>
<?= $field('slogan', 'Your Slogan', $top_menu_slogan, 'maxlength="50"') ?>
<?= $field('session_idle_time', 'Session Idle Time (Minutes)', (int) $session_idle_time, 'type="number" min="' . CONFIG_MIN_IDLE . '" max="' . CONFIG_MAX_IDLE . '" required') ?>
<?= $field('default_record', 'Records Per Page', (int) $default_record, 'type="number" min="' . CONFIG_MIN_RECORDS . '" max="' . CONFIG_MAX_RECORDS . '" required') ?>
</div>
<p class="footnote">Saving Company &amp; Login Settings sets the three titles to its Brand Name.</p></section>

<section class="section"><div class="section-title">Billing And Stock</div>
<div class="grid g2">
<?= $field('doc_prefix', 'Document Prefix', $prefix, 'maxlength="6" pattern="[A-Za-z0-9]{1,6}" required') ?>
<div class="field"><label for="default_store">Default Store</label><select id="default_store" name="default_store"><option value="">None</option><?php foreach ($stores as $store): ?><option<?= $store === $defaultStore ? ' selected' : '' ?>><?= sales_h($store) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="sales_stock_mode">Sales Take Goods Out Of Stock</label><select id="sales_stock_mode" name="sales_stock_mode"><option value="deduct"<?= $stockMode !== 'off' ? ' selected' : '' ?>>Yes</option><option value="off"<?= $stockMode === 'off' ? ' selected' : '' ?>>No</option></select></div>
<?= $field('low_stock_level', 'Low Stock Level', inv_setting($conn, 'low_stock_level', '2'), $number) ?>
</div>
<p class="footnote">New stock documents are numbered prefix + type + year + serial, for example <?= sales_h($prefix) ?>PUR<?= date('Y') ?>000001. Saved documents keep their numbers. The dashboard lists products at or below the low stock level.</p></section>

<section class="section"><div class="section-title">Member Points</div>
<div class="grid g2">
<?= $field('member_points_per_100', 'Points For Every 100 Spent', inv_setting($conn, 'member_points_per_100', '1'), $number) ?>
<?= $field('member_point_value', 'Value Of One Point', inv_setting($conn, 'member_point_value', '1'), $number) ?>
</div></section>

<section class="section"><div class="section-title">POS Receipt</div>
<div class="grid g2">
<?= $field('pos_receipt_heading', 'Receipt Heading', inv_setting($conn, 'pos_receipt_heading', 'MUSHAK-6.3'), 'maxlength="60"') ?>
<?= $field('pos_vat_percent', 'Default VAT %', inv_setting($conn, 'pos_vat_percent', '0'), 'type="number" step="any" min="0" max="100" required') ?>
<div class="field"><label for="pos_vat_mode">VAT On POS Prices</label><select id="pos_vat_mode" name="pos_vat_mode"><option value="inclusive"<?= $posVatMode !== 'exclusive' ? ' selected' : '' ?>>Included in the price</option><option value="exclusive"<?= $posVatMode === 'exclusive' ? ' selected' : '' ?>>Added on top of the price</option></select></div>
<?= $field('pos_receipt_footer', 'Receipt Footer', inv_setting($conn, 'pos_receipt_footer', ''), 'maxlength="255" placeholder="Thank you, come again!"') ?>
</div>
<p class="footnote">The receipt header (logo, name, address, phones, BIN) comes from <a href="company_settings.php">Company &amp; Login Settings</a>. Leave the heading empty to hide it.</p></section>

<section class="section"><div class="section-title">Two-Step Verification</div>
<div class="grid g2">
<?php foreach (TFA_GROUPS as $group => $label): $method = tfa_method($group); ?>
<div class="field"><label for="tfa_<?= $group ?>"><?= sales_h($label) ?></label><select id="tfa_<?= $group ?>" name="tfa_<?= $group ?>"><?php foreach (TFA_METHODS as $value => $caption): ?><option value="<?= $value ?>"<?= $value === $method ? ' selected' : '' ?>><?= $caption ?></option><?php endforeach; ?></select></div>
<?php endforeach; ?>
<?= $field('tfa_minutes', 'Code Works For (Minutes)', tfa_minutes(), 'type="number" min="2" max="30" required') ?>
</div>
<p class="footnote">After the password, a 6-digit code goes to the user's mobile and/or email; they type it to finish signing in. Admin and office users get it at the mobile and email on Settings &gt; Users; customers and sales staff at the mobile on their customer or sales person record (email only if their login has one). A login with nothing to send to cannot sign in while this is on. SMS and email are set up in <a href="email_sms.php">Email &amp; SMS</a> (SMS: <?= msg_get('sms_enabled') === '1' ? 'on' : 'off' ?>, email: <?= msg_get('mail_enabled') === '1' ? 'on' : 'off' ?>).</p></section>
<div class="toolbar" style="margin-top:10px"><button class="btn primary">Save Configuration</button><a class="btn" href="email_sms.php">Email &amp; SMS</a><a class="btn" href="company_settings.php">Company &amp; Login Settings</a></div>
</form>
<?php
mgmt_page_end();
include "../../addon/conf/connection_close.php";
