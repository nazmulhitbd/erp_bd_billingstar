<?php
/** What needs attention: the last 24 hours at a glance, the addresses failing most, and where sign-ins come from. */
require_once __DIR__ . '/audit_common.php';
$count = fn($where, ...$args) => (int) (sales_row($conn, "SELECT COUNT(*) AS n FROM audit_log WHERE created_at > NOW() - INTERVAL 1 DAY AND $where", str_repeat('s', count($args)), $args)['n'] ?? 0);
$tiles = [
    ['Sign-ins, 24 hours', $count("action='login_ok'"), 'login', ''],
    ['Failed sign-ins', $count("action='login_failed'"), 'login', 'bad'],
    ['Data changes', $count("category='change'"), 'change', ''],
    ['Security events', $count("category='security'"), 'security', 'bad'],
    ['Attacks detected', $count("action='attack_signature'"), 'security', 'bad'],
];
$blocked = (int) (sales_row($conn, 'SELECT COUNT(*) AS n FROM audit_blocked_ip WHERE blocked_until IS NULL OR blocked_until > NOW()')['n'] ?? 0);
$failing = sales_rows($conn, "SELECT ip, MAX(country) AS country, COUNT(*) AS n, MAX(created_at) AS last_at FROM audit_log WHERE action='login_failed' AND created_at > NOW() - INTERVAL 7 DAY GROUP BY ip ORDER BY n DESC LIMIT 10");
$countries = sales_rows($conn, "SELECT country, COUNT(*) AS n FROM audit_log WHERE category='login' AND action='login_ok' AND created_at > NOW() - INTERVAL 30 DAY GROUP BY country ORDER BY n DESC LIMIT 10");
$who = sales_rows($conn, "SELECT actor_type, COUNT(*) AS n FROM audit_log WHERE action='login_ok' AND created_at > NOW() - INTERVAL 30 DAY GROUP BY actor_type ORDER BY n DESC");
$recent = sales_rows($conn, "SELECT * FROM audit_log WHERE category='security' ORDER BY audit_id DESC LIMIT 10");
audit_page_start('Audit Dashboard', 'dashboard');
?>
<h1>Audit Dashboard</h1>
<div class="a-tiles"><?php foreach ($tiles as [$label, $n, $tab, $tone]): ?><a class="a-tile <?= $tone !== '' && $n > 0 ? 'bad' : '' ?>" href="audit_log.php?tab=<?= $tab ?>"><b><?= $n ?></b><span><?= sales_h($label) ?></span></a><?php endforeach; ?><a class="a-tile <?= $blocked ? 'bad' : '' ?>" href="audit_settings.php"><b><?= $blocked ?></b><span>Blocked addresses</span></a></div>
<div class="a-cols">
<fieldset class="box"><legend>Most failed sign-ins, 7 days</legend><div class="table-scroll"><table><thead><tr><th>Address</th><th>Country</th><th>Failed</th><th>Last</th></tr></thead><tbody>
<?php if (!$failing): ?><tr><td colspan="4" class="empty">None.</td></tr><?php endif; ?>
<?php foreach ($failing as $r): ?><tr><td><a href="audit_log.php?tab=all&amp;ip=<?= urlencode($r['ip']) ?>"><?= sales_h($r['ip']) ?></a></td><td><?= sales_h(audit_country_name($r['country'])) ?></td><td><?= (int) $r['n'] ?></td><td><?= sales_h($r['last_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></fieldset>
<fieldset class="box"><legend>Sign-ins by country, 30 days</legend><div class="table-scroll"><table><thead><tr><th>Country</th><th>Sign-ins</th></tr></thead><tbody>
<?php if (!$countries): ?><tr><td colspan="2" class="empty">None.</td></tr><?php endif; ?>
<?php foreach ($countries as $r): ?><tr><td><?= sales_h(audit_country_name($r['country'])) ?></td><td><?= (int) $r['n'] ?></td></tr><?php endforeach; ?>
</tbody></table></div></fieldset>
<fieldset class="box"><legend>Sign-ins by user type, 30 days</legend><div class="table-scroll"><table><thead><tr><th>Type</th><th>Sign-ins</th></tr></thead><tbody>
<?php if (!$who): ?><tr><td colspan="2" class="empty">None.</td></tr><?php endif; ?>
<?php foreach ($who as $r): ?><tr><td><?= sales_h(AUDIT_ACTORS[$r['actor_type']] ?? $r['actor_type']) ?></td><td><?= (int) $r['n'] ?></td></tr><?php endforeach; ?>
</tbody></table></div></fieldset>
</div>
<fieldset class="box"><legend>Latest security events</legend><div class="table-scroll"><table><thead><tr><th>When</th><th>Level</th><th>What</th><th>Who</th><th>Address</th><th>Country</th></tr></thead><tbody>
<?php if (!$recent): ?><tr><td colspan="6" class="empty">No security events.</td></tr><?php endif; ?>
<?php foreach ($recent as $r): ?><tr><td><a href="audit_log.php?tab=security&amp;id=<?= (int) $r['audit_id'] ?>"><?= sales_h($r['created_at']) ?></a></td><td><?= audit_badge($r['severity']) ?></td><td><?= sales_h($r['summary']) ?></td><td><?= audit_who($r) ?></td><td><?= sales_h($r['ip']) ?></td><td><?= sales_h(audit_country_name($r['country'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div></fieldset>
<?php
audit_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
