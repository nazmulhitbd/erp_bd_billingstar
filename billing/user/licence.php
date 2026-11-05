<?php
/** Settings > Service Licence: what Billing CRM allows this install, and connecting to it. SuperAdmin only. */
require_once dirname(__DIR__) . '/trader/trader_common.php';

if (!perm_is_super()) {
    perm_deny('this page is for the SuperAdmin.');
}
$self = 'licence.php';
licence_save_host(); // the SuperAdmin is here, so this request's host is the real one: it is what the panel is told from now on
const LICENCE_TABLE = "CREATE TABLE IF NOT EXISTS licence_state (
  id TINYINT NOT NULL PRIMARY KEY,
  crm_url VARCHAR(255) NOT NULL,
  service_code INT NOT NULL,
  service_key CHAR(64) NOT NULL,
  public_key TEXT NOT NULL,
  payload MEDIUMTEXT NOT NULL,
  signature TEXT NOT NULL,
  checked_at INT NOT NULL DEFAULT 0,
  attempted_at INT NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'edit_check') {
        licence_refresh();
        $error = (string) (licence_row()['last_error'] ?? '');
        sales_flash_redirect($self, $error === '' ? 'success' : 'error', $error === '' ? 'Checked with the licence panel.' : $error);
    }
    if ($action === 'edit_connect') {
        if (licence_row() && !licence_status()['locked']) {
            sales_flash_redirect($self, 'error', 'This install is already connected.');
        }
        $c = json_decode((string) base64_decode(trim((string) ($_POST['connection'] ?? '')), true), true);
        if (!is_array($c) || empty($c['url']) || empty($c['code']) || empty($c['key']) || empty($c['public_key']) || !preg_match('~^https?://~', (string) $c['url'])) {
            sales_flash_redirect($self, 'error', 'That is not a connection code.');
        }
        [$payload, $signature, $error] = licence_fetch($c['url'], (int) $c['code'], (string) $c['key'], (string) $c['public_key']);
        if ($error !== '') {
            sales_flash_redirect($self, 'error', $error);
        }
        $conn->query(LICENCE_TABLE);
        sales_exec($conn, 'REPLACE INTO licence_state (id, crm_url, service_code, service_key, public_key, payload, signature, checked_at, attempted_at) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)', 'sissssii', [$c['url'], (int) $c['code'], $c['key'], $c['public_key'], $payload, $signature, time(), time()]);
        sales_exec($conn, 'UPDATE client_theme SET service_code=? WHERE client_theme_id=1', 'i', [(int) $c['code']]);
        sales_flash_redirect($self, 'success', 'Connected as service ' . (int) $c['code'] . '.');
    }
    sales_flash_redirect($self, 'error', 'Unknown action.');
}

$row = licence_row();
$status = licence_status();
$data = $status['data'];
$flash = sales_take_flash();
mgmt_page_start('Settings', ['licence' => [$self, 'Service Licence']], 'Service Licence', 'licence');
echo sales_flash_html($flash);
if ($status['locked']) {
    echo '<img src="../../addon/licence/expired.png" alt="Service Expired" style="display:block;width:100%;max-width:520px;height:auto;margin:0 auto 12px">';
}
if ($status['message'] !== '') {
    echo '<div class="alert error">' . sales_h($status['message']) . '</div>';
}
if ($data):
    $names = [];
    foreach (menu_catalog() as $cat) {
        $names[licence_slug($cat['label'])] = $cat['label'];
    }
    $names += ['customer_portal' => 'Customer Portal', 'sr_portal' => 'SR Portal'];
?>
<div class="summary"><div>Service Code<b><?= (int) $data['service_code'] ?></b></div><div>Plan<b><?= sales_h($data['plan']) ?></b></div><div>Started<b><?= sales_h($data['start_date']) ?></b></div><div>Expires<b><?= sales_h($data['expiry_date']) ?></b></div><div>Days Left<b><?= (int) $status['days'] ?></b></div></div>
<section class="section"><div class="section-title">Modules</div>
<div class="grid g4"><?php foreach ($data['all_modules'] as $code): if (!isset($names[$code])) { continue; } ?><div><?= licence_module_on($code) ? '✓' : '✗' ?> <?= sales_h($names[$code]) ?></div><?php endforeach; ?></div></section>
<section class="section"><div class="section-title">Last check</div>
<p class="footnote" style="margin-top:0">This install checks by itself every minute while it is in use. Panel: <?= sales_h($row['crm_url']) ?>. Last answer: <?= (int) $row['checked_at'] ? date('Y-m-d H:i', (int) $row['checked_at']) : 'never' ?>.<?= (string) $row['last_error'] !== '' ? ' Last problem: ' . sales_h($row['last_error']) : '' ?></p>
<form method="post"><?= sales_csrf_field() ?><div class="toolbar"><button class="btn primary" name="action" value="edit_check">Check Now</button></div></form></section>
<?php endif;
if (!$row || $status['locked']): ?>
<section class="section"><div class="section-title">Connect to Billing CRM</div>
<?php if (!$row): ?><p class="footnote" style="margin-top:0">This install is not connected, so nothing is limited. Once connected, the panel controls its expiry date and modules.</p><?php endif; ?>
<form method="post"><?= sales_csrf_field() ?><div class="field"><label for="connection">Connection Code</label><textarea id="connection" name="connection" rows="5" required></textarea></div>
<div class="toolbar" style="margin-top:10px"><button class="btn primary" name="action" value="edit_connect">Connect</button></div></form></section>
<?php endif;
mgmt_page_end();
include "../../addon/conf/connection_close.php";
