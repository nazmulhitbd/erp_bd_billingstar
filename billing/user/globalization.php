<?php
/**
 * Settings > Country, Language & Tax: what PetaBill Cloud has set for this licence. Read only: the country, currency,
 * tax rates, language, wording and business setup are managed in PetaBill Cloud (Services > the licence, and
 * ERP Countries & Languages) and arrive with the licence check. A person can still switch between that language and English.
 */
require_once dirname(__DIR__) . '/trader/trader_common.php';

if (!perm_is_super()) {
    perm_deny('this page is for the SuperAdmin.');
}
$self = 'globalization.php';
$country = bs_country();
$pack = function_exists('licence_locale') ? licence_locale() : null;
$row = function_exists('licence_row') ? licence_row() : null;
$months = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$lang = bs_licence_lang();
$flash = sales_take_flash();
mgmt_page_start('Settings', ['glob' => [$self, 'Country, Language & Tax']], 'Country, Language & Tax', 'glob');
echo sales_flash_html($flash);
?>
<style>
.gl-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin:12px 0}
.gl-facts div{padding:10px 12px;border-radius:10px;background:#f0f9ff}.gl-facts small{display:block;color:#64748b;font-size:12px}
.gl-check{list-style:none;margin:0;padding:0;display:grid;gap:8px}
.gl-check li{display:flex;gap:10px;padding:10px 12px;border-radius:10px;border:1px solid #e5eaf2}
.gl-check li.ok{border-color:#bbf7d0;background:#f0fdf4}.gl-check li.warn{border-color:#fde68a;background:#fffbeb}.gl-check li.info{border-color:#bae6fd;background:#f0f9ff}
.gl-check b{display:block}.gl-check span{color:#475569;font-size:13px}
.gl-tbl{width:100%;border-collapse:collapse;font-size:13.5px}.gl-tbl th,.gl-tbl td{padding:7px 9px;border-bottom:1px solid #e5eaf2;text-align:left}
.gl-tbl th{background:#f5f8fc;color:#475569;font-size:12px;text-transform:uppercase}
.gl-langs{display:flex;flex-wrap:wrap;gap:8px}.gl-langs a{padding:6px 14px;border:1px solid #cbd5e1;border-radius:999px;text-decoration:none;color:#0f172a}
.gl-langs a.on{background:#0284c7;border-color:#0284c7;color:#fff}
.gl-managed{padding:12px 14px;border-radius:10px;background:#eef6fd;border:1px solid #bae6fd;color:#0c4a6e}
</style>
<section class="section"><div class="section-title">Managed by PetaBill Cloud</div>
<p class="gl-managed"><?php if ($pack): ?>This install's country, currency, tax, language and wording are set in PetaBill Cloud for licence <b><?= (int) ($row['service_code'] ?? 0) ?></b> and were last received <?= sales_h(date('d M Y, H:i', (int) (perm_q('SELECT fetched_at FROM licence_locale WHERE id=1')[0]['fetched_at'] ?? time()))) ?>. To change them, contact your PetaBill provider. They reach this install within minutes.
<?php elseif ($row): ?>Connected to PetaBill Cloud, but its country package has not arrived yet. It comes with the next licence check (<a href="licence.php">Service Licence &gt; Check Now</a>). Until then: Bangladesh, in English.
<?php else: ?>This install is not connected to PetaBill Cloud, so it works as Bangladesh, in English. Connect it on <a href="licence.php">Service Licence</a> to receive its country and language.<?php endif; ?></p>
<div class="gl-facts">
<div><small>Country</small><b><?= sales_h($country['name']) ?></b></div>
<div><small>Currency</small><b><?= sales_h($country['symbol'] . ' ' . $country['currency']) ?></b></div>
<div><small>Tax · tax ID</small><b><?= sales_h($country['tax'] . ' · ' . $country['tax_id']) ?></b></div>
<div><small>Invoice title</small><b><?= sales_h(bs_invoice_title()) ?></b></div>
<div><small>Financial year</small><b><?= sales_h($months[docno_fy_start()]) ?> start</b></div>
<div><small>Amounts</small><b><?= sales_h(bs_money(12345678.5)) ?></b></div>
<div><small>Business</small><b><?= sales_h(BS_BUSINESS_MODELS[bs_business_model()][0]) ?></b></div>
<div><small>Tax registration</small><b><?= bs_tax_registered() ? 'Registered' : 'Not registered' ?></b></div>
</div></section>

<section class="section"><div class="section-title">Compliance in <?= sales_h($country['name']) ?></div>
<ul class="gl-check"><?php foreach (bs_compliance_checks() as [$state, $title, $detail]): ?><li class="<?= $state ?>"><span><?= $state === 'ok' ? '✔' : ($state === 'warn' ? '!' : 'i') ?></span><div><b><?= sales_h($title) ?></b><span><?= sales_h($detail) ?></span></div></li><?php endforeach; ?></ul>
<p class="footnote">The company <?= sales_h($country['company_tax_id']) ?> is entered on <a href="company_settings.php">Company &amp; Login Settings</a> (format: <?= sales_h(BS_TAX_ID_HINT[$country['code']] ?? '') ?>). The country's invoice number format is applied on <a href="doc_numbering.php">Document Numbering</a>.</p></section>

<section class="section"><div class="section-title"><?= sales_h($country['tax']) ?> rates</div>
<div class="table-scroll"><table class="gl-tbl"><thead><tr><th>Name</th><th>Rate</th><th>Kind</th><th>In use</th></tr></thead><tbody>
<?php foreach (bs_tax_rates() as $r): ?><tr><td><?= sales_h($r['rate_name']) ?></td><td><?= sales_h(rtrim(rtrim(number_format((float) $r['rate'], 3), '0'), '.')) ?>%</td><td><?= sales_h($r['kind']) ?></td><td><?= $r['is_active'] ? 'Yes' : 'No' ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<section class="section"><div class="section-title">Language</div>
<p class="footnote" style="margin-top:0">This licence uses <b><?= sales_h(BS_LANGUAGES[$lang][1]) ?></b>. You can switch to English for yourself only:</p>
<div class="gl-langs"><?php if ($lang !== 'en'): ?><a href="<?= $self ?>?lang=auto"<?= bs_lang() === $lang ? ' class="on"' : '' ?>><?= sales_h(BS_LANGUAGES[$lang][1]) ?></a><?php endif; ?><a href="<?= $self ?>?lang=en"<?= bs_lang() === 'en' ? ' class="on"' : '' ?>>English</a></div></section>
<?php
mgmt_page_end();
