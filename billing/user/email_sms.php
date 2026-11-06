<?php
/** Settings > Email & SMS: the mail server and SMS gateway, each with a test send. Needs the same right as User Config. */
require_once dirname(__DIR__) . '/trader/trader_common.php';
require_once dirname(__DIR__, 2) . '/addon/inc/messaging.php';

$rights = perm_q('SELECT r.user_config FROM admin_user u JOIN admin_role r ON r.user_role_id=u.user_role_id WHERE u.user_id=?', 'i', [(int) ($_COOKIE['user_id_admin'] ?? 0)]);
if (!perm_is_super() && (explode(',', (string) ($rights[0]['user_config'] ?? ''))[2] ?? '') !== '1') {
    perm_deny('this page needs the User Config right.');
}
$self = 'email_sms.php';
$post = fn($key, $max = 190) => mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'edit_mail') {
        $from = $post('mail_from');
        $host = $post('smtp_host');
        $port = (int) ($_POST['smtp_port'] ?? 0);
        $secure = in_array($_POST['smtp_secure'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_secure'] : 'tls';
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            sales_flash_redirect($self, 'error', 'From Email is not valid.');
        }
        if ($host !== '' && (!preg_match('/^[A-Za-z0-9.-]+$/', $host) || $port < 1 || $port > 65535)) {
            sales_flash_redirect($self, 'error', 'Enter the SMTP server name and a port from 1 to 65535.');
        }
        if (isset($_POST['mail_enabled']) && ($host === '' || $from === '')) {
            sales_flash_redirect($self, 'error', 'Email needs an SMTP server and a From Email before it can be switched on.');
        }
        foreach (['mail_from' => $from, 'mail_name' => str_replace('"', '', $post('mail_name', 80)), 'smtp_host' => $host, 'smtp_port' => $port, 'smtp_secure' => $secure, 'smtp_user' => $post('smtp_user'), 'mail_enabled' => isset($_POST['mail_enabled']) ? 1 : 0] as $key => $value) {
            msg_put($key, $value);
        }
        if ((string) ($_POST['smtp_pass'] ?? '') !== '') { // never sent back to the browser, so blank means keep it
            msg_put_secret('smtp_pass', (string) $_POST['smtp_pass']);
        }
        sales_flash_redirect($self, 'success', 'Email settings saved.');
    }
    if ($action === 'edit_sms') {
        $url = $post('sms_url', 255);
        $method = ($_POST['sms_method'] ?? '') === 'POST' ? 'POST' : 'GET';
        if ($url !== '' && !preg_match('~^https?://[^\s<>"\']+$~', $url)) {
            sales_flash_redirect($self, 'error', 'The gateway address must start with http:// or https://.');
        }
        if ($url !== '' && !str_contains($url . $post('sms_body', 255), '{to}') || $url !== '' && !str_contains($url . $post('sms_body', 255), '{message}')) {
            sales_flash_redirect($self, 'error', 'The gateway address or POST body must include {to} and {message}.');
        }
        if (isset($_POST['sms_enabled']) && $url === '') {
            sales_flash_redirect($self, 'error', 'SMS needs a gateway address before it can be switched on.');
        }
        foreach (['sms_url' => $url, 'sms_method' => $method, 'sms_body' => $post('sms_body', 255), 'sms_sender' => $post('sms_sender', 40), 'sms_success' => $post('sms_success', 60), 'sms_enabled' => isset($_POST['sms_enabled']) ? 1 : 0] as $key => $value) {
            msg_put($key, $value);
        }
        if ((string) ($_POST['sms_key'] ?? '') !== '') {
            msg_put_secret('sms_key', (string) $_POST['sms_key']);
        }
        sales_flash_redirect($self, 'success', 'SMS settings saved.');
    }
    if ($action === 'edit_test_mail') {
        $error = msg_mail($post('test_to'), 'PetaBill test email', "Email from PetaBill is working.\n", true);
        sales_flash_redirect($self, $error === '' ? 'success' : 'error', $error === '' ? 'The mail server accepted the test email. Check the inbox and spam folder.' : $error);
    }
    if ($action === 'edit_test_sms') {
        $error = msg_sms($post('test_to', 30), 'PetaBill test SMS', true);
        sales_flash_redirect($self, $error === '' ? 'success' : 'error', $error === '' ? 'The gateway accepted the test SMS. Check the phone.' : $error);
    }
    sales_flash_redirect($self, 'error', 'Unknown action.');
}

$flash = sales_take_flash();
$secure = msg_get('smtp_secure', 'tls');
$method = msg_get('sms_method', 'GET');
$saved = fn($key) => msg_get($key) !== '' ? 'Saved. Blank keeps it.' : '';
$checked = fn($key) => msg_get($key) === '1' ? ' checked' : '';
mgmt_page_start('Settings', ['email_sms' => [$self, 'Email & SMS']], 'Email & SMS', 'email_sms');
echo sales_flash_html($flash);
?>
<section class="section"><div class="section-title">Email</div>
<form method="post" autocomplete="off"><?= sales_csrf_field() ?>
<div class="grid g2">
<div class="field"><label for="mail_from">From Email</label><input type="email" id="mail_from" name="mail_from" maxlength="190" value="<?= sales_h(msg_get('mail_from')) ?>"></div>
<div class="field"><label for="mail_name">From Name</label><input id="mail_name" name="mail_name" maxlength="80" value="<?= sales_h(msg_get('mail_name', 'PetaBill')) ?>"></div>
<div class="field"><label for="smtp_host">SMTP Server</label><input id="smtp_host" name="smtp_host" maxlength="190" placeholder="mail.example.com" value="<?= sales_h(msg_get('smtp_host')) ?>"></div>
<div class="field"><label for="smtp_port">SMTP Port</label><input type="number" id="smtp_port" name="smtp_port" min="1" max="65535" value="<?= (int) msg_get('smtp_port', '587') ?>"></div>
<div class="field"><label for="smtp_secure">Security</label><select id="smtp_secure" name="smtp_secure"><?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None (not safe)'] as $value => $label): ?><option value="<?= $value ?>"<?= $value === $secure ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="smtp_user">SMTP User</label><input id="smtp_user" name="smtp_user" maxlength="190" autocomplete="off" value="<?= sales_h(msg_get('smtp_user')) ?>"></div>
<div class="field"><label for="smtp_pass">SMTP Password</label><input type="password" id="smtp_pass" name="smtp_pass" autocomplete="new-password" placeholder="<?= sales_h($saved('smtp_pass')) ?>"></div>
</div>
<p class="footnote"><label><input type="checkbox" name="mail_enabled"<?= $checked('mail_enabled') ?>> Email is switched on</label></p>
<div class="toolbar"><button class="btn primary" name="action" value="edit_mail">Save Email Settings</button></div></form>
<form method="post" style="margin-top:12px"><?= sales_csrf_field() ?><div class="grid g2"><div class="field"><label for="test_mail">Send A Test To</label><input type="email" id="test_mail" name="test_to" required></div></div>
<div class="toolbar" style="margin-top:8px"><button class="btn" name="action" value="edit_test_mail">Send Test Email</button></div></form></section>

<section class="section"><div class="section-title">SMS</div>
<form method="post" autocomplete="off"><?= sales_csrf_field() ?>
<div class="field"><label for="sms_url">Gateway Address</label><input id="sms_url" name="sms_url" maxlength="255" placeholder="https://sms.example.com/send?api_key={key}&amp;to={to}&amp;from={sender}&amp;text={message}" value="<?= sales_h(msg_get('sms_url')) ?>"></div>
<div class="grid g2">
<div class="field"><label for="sms_method">Method</label><select id="sms_method" name="sms_method"><?php foreach (['GET', 'POST'] as $value): ?><option<?= $value === $method ? ' selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="sms_body">POST Body</label><input id="sms_body" name="sms_body" maxlength="255" placeholder="api_key={key}&amp;to={to}&amp;text={message}" value="<?= sales_h(msg_get('sms_body')) ?>"></div>
<div class="field"><label for="sms_key">API Key</label><input type="password" id="sms_key" name="sms_key" autocomplete="new-password" placeholder="<?= sales_h($saved('sms_key')) ?>"></div>
<div class="field"><label for="sms_sender">Sender ID</label><input id="sms_sender" name="sms_sender" maxlength="40" value="<?= sales_h(msg_get('sms_sender')) ?>"></div>
<div class="field"><label for="sms_success">Success Word In Reply</label><input id="sms_success" name="sms_success" maxlength="60" placeholder="Blank = any 2xx reply" value="<?= sales_h(msg_get('sms_success')) ?>"></div>
</div>
<p class="footnote">Copy the address from your SMS provider's API page, and put {to}, {message}, {key} and {sender} where its values go. A provider that answers 200 even on failure needs its success word here.</p>
<p class="footnote"><label><input type="checkbox" name="sms_enabled"<?= $checked('sms_enabled') ?>> SMS is switched on</label></p>
<div class="toolbar"><button class="btn primary" name="action" value="edit_sms">Save SMS Settings</button></div></form>
<form method="post" style="margin-top:12px"><?= sales_csrf_field() ?><div class="grid g2"><div class="field"><label for="test_sms">Send A Test To</label><input id="test_sms" name="test_to" required placeholder="8801XXXXXXXXX"></div></div>
<div class="toolbar" style="margin-top:8px"><button class="btn" name="action" value="edit_test_sms">Send Test SMS</button></div></form></section>
<?php
mgmt_page_end();
include "../../addon/conf/connection_close.php";
