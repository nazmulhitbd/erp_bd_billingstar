<?php
/** The audit trail: filter by date, person, address, country or level, open one record to see old and new values, or export. */
require_once __DIR__ . '/audit_common.php';
$tab = isset(AUDIT_TABS[$_GET['tab'] ?? '']) ? $_GET['tab'] : '';
if ($tab === '' || !audit_may($tab)) {
    foreach (array_keys(AUDIT_TABS) as $key) {
        if (audit_may($key)) {
            $tab = $key;
            break;
        }
    }
}
$categories = array_values(array_intersect(AUDIT_TABS[$tab][1], audit_my_categories()));
if (!$categories) {
    perm_deny('Audit records.');
}
$in = $_GET;
$in['from'] ??= date('Y-m-d', strtotime('-7 days'));
$in['to'] ??= date('Y-m-d');
[$where, $types, $params] = audit_filter($categories, $in);

if (isset($_GET['export'])) {
    if (!audit_may($tab, 'print')) {
        perm_deny('Print ' . AUDIT_TABS[$tab][0] . '.');
    }
    $rows = sales_rows($conn, "SELECT * FROM audit_log WHERE $where ORDER BY audit_id DESC LIMIT " . AUDIT_EXPORT_MAX, $types, $params);
    audit_event('activity', 'audit_export', 'warn', ['summary' => 'Exported ' . count($rows) . ' audit records', 'detail' => ['tab' => $tab, 'from' => $in['from'], 'to' => $in['to']]]);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_' . $tab . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['When', 'Category', 'Action', 'Level', 'Status', 'User Type', 'User', 'Address', 'Country', 'Browser', 'System', 'Device', 'Page', 'Table', 'Record', 'Summary'], ',', '"', '');
    foreach ($rows as $r) {
        $line = [$r['created_at'], $r['category'], $r['action'], $r['severity'], $r['status'], $r['actor_type'], $r['actor_name'], $r['ip'], $r['country'], $r['browser'], $r['os'], $r['device'], $r['page'], $r['target_table'], $r['target_id'], $r['summary']];
        // A cell starting with these runs as a formula in Excel, and these values can come straight from an attacker.
        fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v, $line), ',', '"', '');
    }
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id) {
    $r = sales_row($conn, 'SELECT * FROM audit_log WHERE audit_id=? AND category IN (' . implode(',', array_fill(0, count($categories), '?')) . ')', 'i' . str_repeat('s', count($categories)), [$id, ...$categories]);
    if (!$r) {
        perm_deny('that audit record.');
    }
    $detail = json_decode((string) $r['detail'], true) ?: [];
    $related = $r['request_id'] === '' ? [] : sales_rows($conn, 'SELECT audit_id, created_at, summary, severity FROM audit_log WHERE request_id=? AND audit_id<>? AND category IN (' . implode(',', array_fill(0, count($categories), '?')) . ') ORDER BY audit_id LIMIT 50', 'si' . str_repeat('s', count($categories)), [$r['request_id'], $id, ...$categories]);
    $show = fn($v) => sales_h(is_array($v) ? json_encode($v) : ($v === null ? '(empty)' : (string) $v));
    audit_page_start('Audit Record', $tab);
    ?>
<h1>Audit record #<?= $id ?></h1>
<p><a class="btn" href="audit_log.php?tab=<?= $tab ?>">Back</a></p>
<fieldset class="box"><legend><?= sales_h($r['summary']) ?></legend><dl class="a-facts">
<dt>When</dt><dd><?= sales_h($r['created_at']) ?></dd><dt>Level</dt><dd><?= audit_badge($r['severity']) ?> <?= sales_h($r['status']) ?></dd>
<dt>Who</dt><dd><?= sales_h($r['actor_name'] ?: 'Unknown') ?> (<?= sales_h(AUDIT_ACTORS[$r['actor_type']] ?? $r['actor_type']) ?><?= $r['actor_id'] ? ' #' . (int) $r['actor_id'] : '' ?>)</dd>
<dt>Address</dt><dd><?= sales_h($r['ip']) ?> &middot; <?= sales_h(audit_country_name($r['country'])) ?></dd>
<dt>Device</dt><dd><?= sales_h(trim($r['browser'] . ' on ' . $r['os'] . ', ' . $r['device'])) ?></dd><dt>Browser Text</dt><dd><?= sales_h($r['user_agent']) ?></dd>
<dt>Page</dt><dd><?= sales_h($r['method'] . ' ' . $r['page']) ?></dd><dt>Action</dt><dd><?= sales_h($r['category'] . ' / ' . $r['action']) ?></dd>
<?php if ($r['target_table'] !== ''): ?><dt>Record</dt><dd><?= sales_h($r['target_table'] . ' ' . $r['target_id']) ?></dd><?php endif; ?>
<dt>Session</dt><dd><?= sales_h($r['session_ref'] ?: '-') ?></dd></dl></fieldset>
<?php if (!empty($detail['changes'])): ?><fieldset class="box"><legend>What changed</legend><div class="table-scroll"><table><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>
<?php foreach ($detail['changes'] as $column => [$old, $new]): ?><tr><td><?= sales_h($column) ?></td><td class="a-old"><?= $show($old) ?></td><td class="a-new"><?= $show($new) ?></td></tr><?php endforeach; ?>
</tbody></table></div></fieldset><?php endif; ?>
<?php if ($detail): ?><fieldset class="box"><legend>Full detail</legend><div class="a-raw"><?= sales_h(json_encode($detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></div></fieldset><?php endif; ?>
<?php if ($related): ?><fieldset class="box"><legend>Same request</legend><div class="table-scroll"><table><tbody><?php foreach ($related as $x): ?><tr><td><a href="audit_log.php?tab=<?= $tab ?>&amp;id=<?= (int) $x['audit_id'] ?>"><?= sales_h($x['created_at']) ?></a></td><td><?= audit_badge($x['severity']) ?></td><td><?= sales_h($x['summary']) ?></td></tr><?php endforeach; ?></tbody></table></div></fieldset><?php endif; ?>
<?php
    audit_page_end();
    include "../../addon/conf/connection_close.php";
    exit;
}

$page = max(1, (int) ($_GET['pg'] ?? 1));
$total = (int) (sales_row($conn, "SELECT COUNT(*) AS n FROM audit_log WHERE $where", $types, $params)['n'] ?? 0);
$rows = sales_rows($conn, "SELECT * FROM audit_log WHERE $where ORDER BY audit_id DESC LIMIT " . AUDIT_PAGE_SIZE . ' OFFSET ' . (($page - 1) * AUDIT_PAGE_SIZE), $types, $params);
$keep = array_intersect_key($in, array_flip(['from', 'to', 'actor', 'severity', 'ip', 'country', 'table', 'q']));
$url = fn(array $extra) => 'audit_log.php?' . http_build_query(['tab' => $tab] + $extra + $keep);
audit_page_start(AUDIT_TABS[$tab][0], $tab);
?>
<h1><?= sales_h(AUDIT_TABS[$tab][0]) ?></h1>
<form method="get" class="list-search"><input type="hidden" name="tab" value="<?= sales_h($tab) ?>">
<input class="input" type="date" name="from" value="<?= sales_h($in['from']) ?>" aria-label="From" style="max-width:160px"><input class="input" type="date" name="to" value="<?= sales_h($in['to']) ?>" aria-label="To" style="max-width:160px">
<select class="input" name="actor" aria-label="User type" style="max-width:170px"><option value="">All users</option><?php foreach (AUDIT_ACTORS as $key => $label): ?><option value="<?= $key ?>"<?= ($in['actor'] ?? '') === $key ? ' selected' : '' ?>><?= sales_h($label) ?></option><?php endforeach; ?></select>
<select class="input" name="severity" aria-label="Level" style="max-width:130px"><option value="">Any level</option><?php foreach (['info' => 'Info', 'warn' => 'Warning', 'bad' => 'Attack'] as $key => $label): ?><option value="<?= $key ?>"<?= ($in['severity'] ?? '') === $key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
<input class="input" name="ip" value="<?= sales_h($in['ip'] ?? '') ?>" placeholder="Address" style="max-width:150px"><input class="input" name="country" value="<?= sales_h($in['country'] ?? '') ?>" placeholder="Country (BD)" maxlength="3" style="max-width:120px">
<input class="input" name="q" value="<?= sales_h($in['q'] ?? '') ?>" placeholder="User, text or record no." style="max-width:220px"><button class="btn primary">Filter</button>
<?php if (audit_may($tab, 'print')): ?><a class="btn" href="<?= sales_h($url(['export' => 1])) ?>">Export CSV</a><?php endif; ?></form>
<p class="footnote"><?= $total ?> record(s).</p>
<div class="table-scroll"><table><thead><tr><th>When</th><th>Level</th><th>Who</th><th>What</th><th>Address</th><th>Country</th><th>Device</th><th>Page</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="empty">Nothing found.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td><a href="<?= sales_h($url(['id' => (int) $r['audit_id']])) ?>"><?= sales_h($r['created_at']) ?></a></td><td><?= audit_badge($r['severity']) ?></td><td><?= audit_who($r) ?></td><td><?= sales_h($r['summary']) ?></td><td><?= sales_h($r['ip']) ?></td><td><?= sales_h(audit_country_name($r['country'])) ?></td><td><?= sales_h($r['browser']) ?><br><small><?= sales_h($r['os'] . ', ' . $r['device']) ?></small></td><td><small><?= sales_h($r['page']) ?></small></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="list-search"><?php if ($page > 1): ?><a class="btn" href="<?= sales_h($url(['pg' => $page - 1])) ?>">Previous</a><?php endif; ?><span class="footnote">Page <?= $page ?> of <?= max(1, (int) ceil($total / AUDIT_PAGE_SIZE)) ?></span><?php if ($page * AUDIT_PAGE_SIZE < $total): ?><a class="btn" href="<?= sales_h($url(['pg' => $page + 1])) ?>">Next</a><?php endif; ?></div>
<?php
audit_page_end();
?>
<?php include "../../addon/conf/connection_close.php";  ?>
