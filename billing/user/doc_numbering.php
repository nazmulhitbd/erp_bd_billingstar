<?php
/**
 * Settings > Document Numbering: the country this install sells in, its financial year, and the automatic number of every
 * document. Each series shows the number the next document will get. SuperAdmin only. The engine is addon/inc/docno.php.
 */
require_once dirname(__DIR__) . '/trader/trader_common.php';

if (!perm_is_super()) {
    perm_deny('this page is for the SuperAdmin.');
}
$self = 'doc_numbering.php';
$db = docno_db();
docno_ready($db);

function dn_set_setting(mysqli $db, $key, $value)
{
    docno_exec($db, 'INSERT INTO acc_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', 'ss', [$key, (string) $value]);
}

/** '' or why an invoice pattern breaks the chosen country's rule. */
function dn_country_warning($type, $pattern)
{
    $c = bs_country();
    if ($type !== 'SI') {
        return '';
    }
    $sample = docno_format($pattern, 99999, date('Y-m-d'));
    if ($c['code'] === 'IN' && (strlen($sample) > 16 || !preg_match('~^[A-Za-z0-9/-]+$~', $sample))) {
        return 'India GST: an invoice number must be 16 characters or fewer, with letters, digits, - and / only.';
    }
    if ($c['code'] === 'VN' && strlen(preg_replace('/\D/', '', $sample)) > 8) {
        return 'Vietnam: the invoice number itself is at most 8 digits; put any prefix in the invoice symbol instead.';
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    $action = (string) ($_POST['action'] ?? '');
    $me = (int) (perm_user()['user_id'] ?? 0);
    if ($action === 'apply_country') { // the invoice format of this licence's country (PetaBill Cloud), from the next invoice on
        $c = bs_country();
        docno_exec($db, 'INSERT INTO doc_series (doc_type, pattern, reset_rule, start_no, updated_by) VALUES (?, ?, ?, 1, ?) ON DUPLICATE KEY UPDATE pattern=VALUES(pattern), reset_rule=VALUES(reset_rule), updated_by=VALUES(updated_by)', 'sssi', ['SI', $c['invoice_pattern'], $c['invoice_reset'], $me]);
        audit_event('settings', 'document_numbering', 'info', ['summary' => 'Invoice format of ' . $c['name'] . ' applied: ' . $c['invoice_pattern']]);
        sales_flash_redirect($self, 'success', 'New invoices now use ' . $c['invoice_pattern'] . ', for example ' . docno_format($c['invoice_pattern'], 1, date('Y-m-d')) . '. Invoices already issued keep their numbers.');
    }
    if ($action === 'series') {
        $defaults = docno_defaults();
        $changed = 0;
        foreach ((array) ($_POST['s'] ?? []) as $type => $row) {
            if (!isset($defaults[$type]) || !is_array($row)) {
                continue;
            }
            $pattern = trim((string) ($row['pattern'] ?? ''));
            $reset = isset(DOCNO_RESETS[$row['reset'] ?? '']) ? $row['reset'] : 'yearly';
            $start = max(1, min(999999999, (int) ($row['start'] ?? 1)));
            if (($error = docno_pattern_error($pattern)) !== '') {
                sales_flash_redirect($self, 'error', $defaults[$type][0] . ': ' . $error);
            }
            if (($warning = dn_country_warning($type, $pattern)) !== '') {
                sales_flash_redirect($self, 'error', $defaults[$type][0] . ': ' . $warning);
            }
            if (!str_contains($pattern, '{YYYY}') && !str_contains($pattern, '{YY}') && !str_contains($pattern, '{FY') && !str_contains($pattern, '{MM}') && $reset !== 'never') {
                sales_flash_redirect($self, 'error', $defaults[$type][0] . ': a number that starts again each period needs the period in it ({YYYY}, {FY2}, {MM}...), or it would repeat.');
            }
            $now = docno_series($type);
            if ($now['pattern'] === $pattern && $now['reset'] === $reset && $now['start'] === $start) {
                continue;
            }
            docno_exec($db, 'INSERT INTO doc_series (doc_type, pattern, reset_rule, start_no, updated_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE pattern=VALUES(pattern), reset_rule=VALUES(reset_rule), start_no=VALUES(start_no), updated_by=VALUES(updated_by)', 'sssii', [$type, $pattern, $reset, $start, $me]);
            $changed++;
        }
        audit_event('settings', 'document_numbering', 'info', ['summary' => "Document numbering: $changed series changed"]);
        sales_flash_redirect($self, 'success', $changed ? "$changed series saved. The next documents use the new format; numbers already issued never change." : 'Nothing changed.');
    }
    sales_flash_redirect($self, 'error', 'Unknown action.');
}

$flash = sales_take_flash();
$country = bs_country();
$months = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$fyOwn = (int) docno_setting_raw('acc_settings', 'fy_start_month', '0');
[$fy1, $fy2] = docno_fy(date('Y-m-d'));
mgmt_page_start('Settings', ['docno' => [$self, 'Document Numbering']], 'Document Numbering', 'docno');
echo sales_flash_html($flash);
?>
<style>
.dn-country{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:10px 0}
.dn-country label{display:flex;gap:10px;align-items:center;padding:12px;border:1px solid #dbe3ef;border-radius:10px;cursor:pointer;background:#fff}
.dn-country input:checked+span{font-weight:700;color:#0369a1}
.dn-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin:12px 0}
.dn-facts div{padding:10px 12px;border-radius:10px;background:#f0f9ff}
.dn-facts small{display:block;color:#64748b;font-size:12px}
.dn-table{width:100%;border-collapse:collapse;font-size:13.5px}
.dn-table th,.dn-table td{padding:8px 10px;border-bottom:1px solid #e5eaf2;text-align:left;vertical-align:middle}
.dn-table th{background:#f5f8fc;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.03em}
.dn-table tr.mod td{background:#eef6fd;color:#0369a1;font-weight:700}
.dn-table input,.dn-table select{width:100%;min-width:0;padding:6px 8px;border:1px solid #cbd5e1;border-radius:7px;font:inherit}
.dn-table input.pat{font-family:ui-monospace,Menlo,Consolas,monospace}
.dn-next{font-family:ui-monospace,Menlo,Consolas,monospace;font-weight:700;color:#0f766e;white-space:nowrap}
.dn-tokens code{display:inline-block;margin:2px 4px 2px 0;padding:1px 6px;border-radius:5px;background:#eef2f7}
</style>
<section class="section"><div class="section-title">Country: <?= sales_h($country['name']) ?></div>
<p class="footnote" style="margin-top:0">Invoice numbering rule: <?= sales_h($country['rule']) ?> The country is set in PetaBill Cloud (<a href="globalization.php">Country, Language &amp; Tax</a>). Its format: <code><?= sales_h($country['invoice_pattern']) ?></code>, for example <b><?= sales_h(docno_format($country['invoice_pattern'], 1, date('Y-m-d'))) ?></b>.</p>
<?php if (docno_series('SI')['pattern'] !== $country['invoice_pattern']): ?><form method="post"><?= sales_csrf_field() ?><input type="hidden" name="action" value="apply_country"><div class="toolbar"><button class="btn primary">Use <?= sales_h($country['name']) ?>'s invoice format</button></div></form><?php endif; ?></section>

<form method="post" class="section"><?= sales_csrf_field() ?><input type="hidden" name="action" value="series">
<div class="section-title">Automatic numbers</div>
<p class="footnote dn-tokens" style="margin-top:0">Every document gets its number by itself when it is saved, and the form shows it beforehand. Numbers are never repeated: two people saving at once get different numbers, and a number already in use is skipped. Tokens: <code>{YYYY}</code> 2026 <code>{YY}</code> 26 <code>{MM}</code> 11 <code>{DD}</code> 06 <code>{FY}</code> <?= sales_h(docno_format('{FY}', 1, date('Y-m-d'))) ?> <code>{FY2}</code> <?= sales_h(docno_format('{FY2}', 1, date('Y-m-d'))) ?> <code>{FYS}</code> <?= sales_h(docno_format('{FYS}', 1, date('Y-m-d'))) ?> <code>{BR}</code> branch <code>{SEQ:6}</code> 000001</p>
<div class="table-scroll"><table class="dn-table"><thead><tr><th>Document</th><th style="width:28%">Pattern</th><th style="width:18%">Starts again</th><th style="width:10%">First no.</th><th>Next number</th></tr></thead><tbody>
<?php $module = ''; foreach (docno_defaults() as $type => $def): $s = docno_series($type); $t = sales_h($type);
    if ($def[1] !== $module): $module = $def[1]; ?><tr class="mod"><td colspan="5"><?= sales_h($module) ?></td></tr><?php endif; ?>
<tr><td><?= sales_h($s['label']) ?><br><small class="muted"><?= $t ?></small></td>
<td><input class="pat" name="s[<?= $t ?>][pattern]" value="<?= sales_h($s['pattern']) ?>" maxlength="80" aria-label="<?= sales_h($s['label']) ?> pattern"></td>
<td><select name="s[<?= $t ?>][reset]" aria-label="<?= sales_h($s['label']) ?> reset"><?php foreach (DOCNO_RESETS as $k => $label): ?><option value="<?= $k ?>"<?= $k === $s['reset'] ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></td>
<td><input type="number" min="1" name="s[<?= $t ?>][start]" value="<?= (int) $s['start'] ?>" aria-label="<?= sales_h($s['label']) ?> first number"></td>
<td class="dn-next"><?= sales_h(docno_peek($db, $type)) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<p class="footnote">A changed format applies to the next document only; numbers already issued never change. "First no." matters when a series starts again, or for a brand-new series.</p>
<div class="toolbar"><button class="btn primary">Save Numbering</button></div></form>
<?php
mgmt_page_end();
