<?php
/** What the audit keeps and for how long, country lookups, and the addresses refused before any page runs. */
require_once __DIR__ . '/audit_common.php';
$self = 'audit_settings.php';
$me = (string) ($GLOBALS['user_name'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'block') {
            $ip = trim((string) ($_POST['ip'] ?? ''));
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new RuntimeException('Enter a valid address.');
            }
            if ($ip === audit_ip()) {
                throw new RuntimeException('That is your own address, so blocking it would lock you out.');
            }
            $hours = (int) ($_POST['hours'] ?? 0);
            audit_block_ip($ip, sales_post_string('reason', 190) ?: 'Blocked by hand', $hours > 0 ? date('Y-m-d H:i:s', time() + $hours * 3600) : null, $me);
            audit_event('security', 'ip_blocked', 'warn', ['summary' => "$ip blocked by $me", 'target_table' => 'audit_blocked_ip', 'target_id' => $ip]);
            sales_flash_redirect($self, 'success', "$ip is blocked.");
        }
        if ($action === 'unblock') {
            $ip = (string) ($_POST['ip'] ?? '');
            sales_exec($conn, 'DELETE FROM audit_blocked_ip WHERE ip=?', 's', [$ip])->close();
            audit_event('security', 'ip_unblocked', 'warn', ['summary' => "$ip unblocked by $me", 'target_table' => 'audit_blocked_ip', 'target_id' => $ip]);
            sales_flash_redirect($self, 'success', "$ip is unblocked.");
        }
        $url = trim((string) ($_POST['geo_url'] ?? ''));
        if (!preg_match('~^https://[^\s<>"\']+\{ip\}~', $url)) {
            throw new RuntimeException('The lookup address must start with https:// and contain {ip}.');
        }
        $values = ['geo_on' => empty($_POST['geo_on']) ? '0' : '1', 'geo_url' => $url, 'retention_days' => (string) max(0, min(3650, (int) ($_POST['retention_days'] ?? 730))), 'page_views' => empty($_POST['page_views']) ? '0' : '1', 'trust_proxy' => empty($_POST['trust_proxy']) ? '0' : '1', 'autoblock' => empty($_POST['autoblock']) ? '0' : '1', 'autoblock_hits' => (string) max(3, min(1000, (int) ($_POST['autoblock_hits'] ?? 10)))];
        foreach ($values as $key => $value) {
            sales_exec($conn, 'INSERT INTO audit_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', 'ss', [$key, $value])->close();
        }
        audit_event('change', 'audit_settings', 'warn', ['summary' => "Audit settings changed by $me", 'detail' => ['added' => $values], 'target_table' => 'audit_settings']);
        sales_flash_redirect($self, 'success', 'Settings saved.');
    } catch (Throwable $e) {
        sales_flash_redirect($self, 'error', $e->getMessage());
    }
}
$flash = sales_take_flash();
$blocked = sales_rows($conn, 'SELECT * FROM audit_blocked_ip WHERE blocked_until IS NULL OR blocked_until > NOW() ORDER BY created_at DESC');
$on = fn($key) => audit_setting($key) === '1' ? ' checked' : '';
$field = fn($id, $label, $html) => '<div class="field"><label for="' . $id . '">' . $label . '</label>' . $html . '</div>';
audit_page_start('Audit Settings', 'settings');
echo sales_flash_html($flash);
?>
<h1>Audit Settings</h1>
<form method="post"><?= sales_csrf_field() ?><fieldset class="box" style="max-width:760px"><legend>Recording</legend>
<?= $field('retention_days', 'Keep Records For (Days, 0 = Forever)', '<input class="input" type="number" id="retention_days" name="retention_days" min="0" max="3650" value="' . (int) audit_setting('retention_days') . '" style="max-width:160px">') ?>
<p><label><input class="check" type="checkbox" name="page_views" value="1"<?= $on('page_views') ?>> Record every page a signed-in person opens</label></p><p class="footnote">Forms, changes, sign-ins and attacks are always recorded. This adds many more rows.</p>
<p><label><input class="check" type="checkbox" name="geo_on" value="1"<?= $on('geo_on') ?>> Find the country of each address</label></p>
<?= $field('geo_url', 'Country Lookup Address', '<input class="input" id="geo_url" name="geo_url" value="' . sales_h(audit_setting('geo_url')) . '">') ?>
<p class="footnote">Each new public address is sent once to this outside service, then remembered. Switch it off to keep addresses on this server: the country then stays blank.</p>
<p><label><input class="check" type="checkbox" name="trust_proxy" value="1"<?= $on('trust_proxy') ?>> This site is behind Cloudflare or another proxy</label></p><p class="footnote">Tick only if it really is. Otherwise anyone can fake their address with a header.</p>
<p><label><input class="check" type="checkbox" name="autoblock" value="1"<?= $on('autoblock') ?>> Block an address for 24 hours after repeated attacks</label></p>
<?= $field('autoblock_hits', 'Attacks In One Hour Before Blocking', '<input class="input" type="number" id="autoblock_hits" name="autoblock_hits" min="3" max="1000" value="' . (int) audit_setting('autoblock_hits') . '" style="max-width:160px">') ?>
<p class="footnote">Only people who are not signed in are blocked automatically.</p>
<button class="btn primary">Save</button></fieldset></form>
<fieldset class="box"><legend>Blocked addresses</legend>
<form method="post" class="list-search"><?= sales_csrf_field() ?><input type="hidden" name="action" value="block"><input class="input" name="ip" placeholder="Address" required style="max-width:190px"><input class="input" name="reason" maxlength="190" placeholder="Reason" style="max-width:260px"><select class="input" name="hours" aria-label="How long" style="max-width:150px"><option value="24">24 hours</option><option value="168">7 days</option><option value="0">Until unblocked</option></select><button class="btn primary">Block</button></form>
<div class="table-scroll"><table><thead><tr><th>Address</th><th>Reason</th><th>Until</th><th>By</th><th></th></tr></thead><tbody>
<?php if (!$blocked): ?><tr><td colspan="5" class="empty">Nothing is blocked.</td></tr><?php endif; ?>
<?php foreach ($blocked as $b): ?><tr><td><a href="audit_log.php?tab=security&amp;ip=<?= urlencode($b['ip']) ?>"><?= sales_h($b['ip']) ?></a></td><td><?= sales_h($b['reason']) ?></td><td><?= sales_h($b['blocked_until'] ?? 'Until unblocked') ?></td><td><?= sales_h($b['created_by']) ?></td><td><form method="post" onsubmit="return confirm('Unblock this address?')"><?= sales_csrf_field() ?><input type="hidden" name="action" value="unblock"><input type="hidden" name="ip" value="<?= sales_h($b['ip']) ?>"><button class="btn">Unblock</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></fieldset>
<?php
audit_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
