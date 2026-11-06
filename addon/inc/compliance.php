<?php
/**
 * Tax and government compliance per country (Settings > Country, Language & Tax). Works with addon/inc/country.php.
 *
 *  - Tax rates: bs_tax_rates, seeded with each country's rates the first time it is chosen, editable afterwards.
 *  - Tax split: how one tax amount is printed: India CGST + SGST (same state) or IGST (other state), Pakistan sales tax
 *    plus further tax for unregistered buyers, and the plain VAT / SST / GTGT elsewhere.
 *  - Tax IDs: format and check-digit tests for BIN, GSTIN, NTN/STRN, PAN, TIN/SST, Thai Tax ID and MST.
 *  - Compliance checklist: what the chosen country requires of this install, and whether it is done.
 * Rates change by law: the seeded figures are a starting point. Check them with your tax adviser.
 */

/** Business models: what the install mainly does. It sets defaults (price incl. tax, credit sales), never figures. */
const BS_BUSINESS_MODELS = [
    'retail' => ['Retail shop (POS, walk-in customers)', 'prices_include_tax' => '1'],
    'wholesale' => ['Wholesale / B2B (registered buyers, credit)', 'prices_include_tax' => '0'],
    'distribution' => ['Distribution (dealers, sales representatives, routes)', 'prices_include_tax' => '0'],
    'service' => ['Service and repair centre (RMA, warranty)', 'prices_include_tax' => '1'],
    'mixed' => ['Retail and wholesale together', 'prices_include_tax' => '1'],
];

/** Tax registration: registered sellers charge tax on a tax invoice; others issue a plain bill. */
const BS_TAX_REGISTRATION = ['registered' => 'Registered: charges tax and issues tax invoices', 'small' => 'Not registered / small business scheme: no tax charged'];

/** Seed rates per country: [name, rate %, kind]. kind: standard, reduced, zero, exempt, additional. */
const BS_TAX_SEED = [
    'BD' => [['VAT standard', 15, 'standard'], ['VAT reduced', 10, 'reduced'], ['VAT reduced', 7.5, 'reduced'], ['VAT reduced', 5, 'reduced'], ['VAT zero-rated (export)', 0, 'zero'], ['VAT exempt', 0, 'exempt']],
    'IN' => [['GST 18%', 18, 'standard'], ['GST 5%', 5, 'reduced'], ['GST 40% (demerit goods)', 40, 'additional'], ['GST nil-rated', 0, 'zero'], ['GST exempt', 0, 'exempt']],
    'PK' => [['Sales tax standard', 18, 'standard'], ['Sales tax reduced', 10, 'reduced'], ['Further tax (unregistered buyer)', 4, 'additional'], ['Zero-rated', 0, 'zero'], ['Exempt', 0, 'exempt']],
    'NP' => [['VAT', 13, 'standard'], ['VAT zero-rated', 0, 'zero'], ['VAT exempt', 0, 'exempt']],
    'MY' => [['Sales tax 10%', 10, 'standard'], ['Sales tax 5%', 5, 'reduced'], ['Service tax 8%', 8, 'reduced'], ['Service tax 6%', 6, 'reduced'], ['Exempt', 0, 'exempt']],
    'TH' => [['VAT', 7, 'standard'], ['VAT zero-rated (export)', 0, 'zero'], ['VAT exempt', 0, 'exempt']],
    'VN' => [['VAT 10%', 10, 'standard'], ['VAT 8% (reduced)', 8, 'reduced'], ['VAT 5%', 5, 'reduced'], ['VAT 0% (export)', 0, 'zero'], ['Not subject to VAT', 0, 'exempt']],
];

/** Bill title for a seller that is not tax-registered. */
const BS_UNREGISTERED_TITLE = ['BD' => 'Bill / Cash Memo', 'IN' => 'Bill of Supply', 'PK' => 'Invoice', 'NP' => 'Invoice', 'MY' => 'Invoice', 'TH' => 'Receipt', 'VN' => 'Sales Invoice (Hóa đơn bán hàng)'];

function bs_setting($key, $default = '')
{
    return function_exists('docno_setting_raw') ? docno_setting_raw('acc_settings', $key, $default) : $default;
}

/** The business setup PetaBill Cloud gives this licence (Services > the licence), else retail and registered. */
function bs_locale_business($key, $default)
{
    $pack = function_exists('licence_locale') ? licence_locale() : null;
    return isset($pack['business'][$key]) ? (string) $pack['business'][$key] : $default;
}

function bs_business_model()
{
    $m = bs_locale_business('model', 'retail');
    return isset(BS_BUSINESS_MODELS[$m]) ? $m : 'retail';
}

function bs_tax_registered()
{
    return bs_locale_business('registration', 'registered') !== 'small';
}

/** The invoice title the country and the registration call for. */
function bs_invoice_title()
{
    $c = bs_country();
    return bs_tax_registered() ? $c['invoice_title'] : ($c['invoice_title_unregistered'] ?: (BS_UNREGISTERED_TITLE[$c['code']] ?? 'Invoice'));
}

/* ---------------- Tax rates ---------------- */

function bs_tax_table(mysqli $db)
{
    static $done = false;
    if (!$done) {
        $db->query("CREATE TABLE IF NOT EXISTS bs_tax_rates (
            rate_id INT AUTO_INCREMENT PRIMARY KEY,
            country_code CHAR(2) NOT NULL,
            rate_name VARCHAR(80) NOT NULL,
            rate DECIMAL(6,3) NOT NULL DEFAULT 0,
            kind ENUM('standard','reduced','zero','exempt','additional') NOT NULL DEFAULT 'standard',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 100,
            KEY idx_country (country_code, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done = true;
    }
}

/** A country's rates, seeded from BS_TAX_SEED the first time. */
function bs_tax_rates(?mysqli $db = null, $country = null)
{
    $pack = function_exists('licence_locale') ? licence_locale() : null;
    if ($country === null && isset($pack['rates']) && is_array($pack['rates'])) { // the rates PetaBill Cloud manages for this country
        return array_map(fn($r) => ['rate_name' => (string) $r['name'], 'rate' => (float) $r['rate'], 'kind' => (string) $r['kind'], 'is_active' => (int) $r['active']], $pack['rates']);
    }
    $db ??= docno_db();
    $country ??= bs_country_code();
    bs_tax_table($db);
    $rows = docno_q('SELECT * FROM bs_tax_rates WHERE country_code=? ORDER BY sort_order, rate_id', 's', [$country], $db);
    if (!$rows && isset(BS_TAX_SEED[$country])) {
        foreach (BS_TAX_SEED[$country] as $i => [$name, $rate, $kind]) {
            docno_exec($db, 'INSERT INTO bs_tax_rates (country_code, rate_name, rate, kind, sort_order) VALUES (?, ?, ?, ?, ?)', 'ssdsi', [$country, $name, $rate, $kind, ($i + 1) * 10]);
        }
        $rows = docno_q('SELECT * FROM bs_tax_rates WHERE country_code=? ORDER BY sort_order, rate_id', 's', [$country], $db);
    }
    return $rows;
}

/** The country's standard rate, the default for a new product. */
function bs_standard_rate()
{
    foreach (bs_tax_rates() as $r) {
        if ($r['kind'] === 'standard' && $r['is_active']) {
            return (float) $r['rate'];
        }
    }
    return 0.0;
}

/**
 * Tax on an amount: ['base', 'tax', 'lines' => [[label, rate, amount]]]. $o: inclusive (the amount includes tax),
 * buyer_tax_id (for India's place of supply and Pakistan's further tax), registered (seller; default: this install).
 */
function bs_tax_compute($amount, $rate, array $o = [])
{
    $c = bs_country();
    $dec = (int) $c['decimals'];
    $rate = (float) $rate;
    if (!($o['registered'] ?? bs_tax_registered()) || $rate <= 0) {
        return ['base' => round((float) $amount, $dec), 'tax' => 0.0, 'lines' => []];
    }
    $extra = 0.0;
    if ($c['code'] === 'PK' && !bs_tax_id_valid('PK', (string) ($o['buyer_tax_id'] ?? ''))) { // further tax on sales to unregistered buyers
        foreach (bs_tax_rates() as $r) {
            if ($r['kind'] === 'additional' && $r['is_active']) {
                $extra = (float) $r['rate'];
                break;
            }
        }
    }
    $total = $rate + $extra;
    $base = !empty($o['inclusive']) ? (float) $amount * 100 / (100 + $total) : (float) $amount;
    $tax = round($base * $rate / 100, $dec);
    $lines = bs_tax_split($tax, $rate, (string) ($o['buyer_tax_id'] ?? ''));
    if ($extra > 0) {
        $further = round($base * $extra / 100, $dec);
        $lines[] = ['Further tax', $extra, $further];
        $tax += $further;
    }
    return ['base' => round($base, $dec), 'tax' => $tax, 'lines' => $lines];
}

/** How one tax amount is printed: [[label, rate or null, amount]]. India splits by place of supply. */
function bs_tax_split($tax, $rate, $buyerTaxId = '')
{
    $c = bs_country();
    $tax = (float) $tax;
    if ($c['code'] === 'IN') {
        $seller = substr(strtoupper(bs_setting('company_bin', '')), 0, 2);
        $buyer = substr(strtoupper(trim($buyerTaxId)), 0, 2);
        if (ctype_digit($seller) && ctype_digit($buyer) && $seller !== $buyer) { // another state: integrated GST
            return [['IGST', $rate, $tax]];
        }
        $half = round($tax / 2, 2);
        return [['CGST', $rate !== null ? $rate / 2 : null, $half], ['SGST', $rate !== null ? $rate / 2 : null, round($tax - $half, 2)]];
    }
    return [[$c['tax'], $rate, $tax]];
}

/* ---------------- Tax IDs ---------------- */

/** What a valid tax ID looks like in each country, for the form hint. */
const BS_TAX_ID_HINT = ['SG' => '201912345K (UEN) or M2-1234567-8 (GST)', 'AE' => '100123456700003 (TRN, 15 digits)', 'SA' => '310123456700003 (15 digits, 3…3)', 'OM' => 'OM1234567890 (VATIN)', 'BH' => '200012345600002 (15 digits)', 'QA' => 'Commercial registration no.', 'KW' => 'Commercial registration no.', 'BD' => '000108157-0101 (BIN, 9 digits + branch)', 'IN' => '27AAPFU0939F1ZV (GSTIN, 15 characters)', 'PK' => '1234567-8 (NTN) or 13-digit STRN', 'NP' => '123456789 (PAN, 9 digits)',
    'MY' => 'C1234567890 (TIN) or W10-1808-31000047 (SST)', 'TH' => '0105536000315 (13 digits)', 'VN' => '0101243150 or 0101243150-001 (MST)'];

/** Whether a tax ID is right for a country, including check digits where the country has them. */
function bs_tax_id_valid($country, $id)
{
    $id = strtoupper(trim((string) $id));
    if ($id === '') {
        return false;
    }
    switch ($country) {
        case 'BD':
            return (bool) preg_match('/^\d{9}(-?\d{4})?$|^\d{13}$/', $id);
        case 'IN':
            if (!preg_match('/^(0[1-9]|[1-3]\d)[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $id)) {
                return false;
            }
            $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $sum = 0;
            for ($i = 0; $i < 14; $i++) { // GSTIN check character: base-36, weights 1,2,1,2...
                $p = strpos($chars, $id[$i]) * ($i % 2 ? 2 : 1);
                $sum += intdiv($p, 36) + $p % 36;
            }
            return $chars[(36 - $sum % 36) % 36] === $id[14];
        case 'PK':
            return (bool) preg_match('/^\d{7}-?\d$|^\d{13}$|^\d{2}-\d{2}-\d{4}-\d{3}-\d{2}$/', $id);
        case 'NP':
            return (bool) preg_match('/^\d{9}$/', $id);
        case 'MY':
            return (bool) preg_match('/^(C|CS|D|E|F|FA|PT|TA|TC|TN|TR|TP|J|LE|IG|SG|OG)\d{8,12}$|^[A-Z]\d{2}-\d{4}-\d{8}$/', $id);
        case 'TH':
            if (!preg_match('/^\d{13}$/', $id)) {
                return false;
            }
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int) $id[$i] * (13 - $i);
            }
            return (11 - $sum % 11) % 10 === (int) $id[12];
        case 'SG': // UEN (business registration) or GST registration number
            return (bool) preg_match('/^\d{8}[A-Z]$|^\d{9}[A-Z]$|^[TSR]\d{2}[A-Z]{2}\d{4}[A-Z]$|^M\d-?\d{7}-?[0-9A-Z]$/', $id);
        case 'AE': // TRN: 15 digits starting 100
            return (bool) preg_match('/^100\d{12}$/', str_replace([' ', '-'], '', $id));
        case 'SA': // VAT number: 15 digits, starting and ending with 3
            return (bool) preg_match('/^3\d{13}3$/', $id);
        case 'OM':
            return (bool) preg_match('/^OM\d{10}$/', $id);
        case 'BH':
            return (bool) preg_match('/^\d{15}$/', $id);
        case 'QA':
        case 'KW': // no VAT number yet: a commercial registration number
            return (bool) preg_match('/^[A-Z0-9-]{4,20}$/', $id);
        case 'VN':
            if (!preg_match('/^(\d{10})(-\d{3})?$/', $id, $m)) {
                return false;
            }
            $w = [31, 29, 23, 19, 17, 13, 7, 5, 3];
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += (int) $m[1][$i] * $w[$i];
            }
            return (10 - $sum % 11) === (int) $m[1][9];
    }
    return true;
}

/* ---------------- Compliance checklist ---------------- */

/** What the country asks of this install: [[ok|warn|info, title, detail]]. */
function bs_compliance_checks()
{
    $c = bs_country();
    $code = $c['code'];
    $out = [];
    $taxId = trim(bs_setting('company_bin', ''));
    if (bs_tax_registered()) {
        $out[] = $taxId === '' ? ['warn', 'Company ' . $c['company_tax_id'] . ' missing', 'Add it on Settings > Company & Login Settings; it must print on every tax invoice.']
            : (bs_tax_id_valid($code, $taxId) ? ['ok', 'Company ' . $c['company_tax_id'] . ' is valid', $taxId] : ['warn', 'Company ' . $c['company_tax_id'] . ' does not look right', $taxId . ' · expected ' . (BS_TAX_ID_HINT[$code] ?? '')]);
    }
    $series = docno_series('SI');
    $sample = docno_format($series['pattern'], 99999, date('Y-m-d'));
    $ok = match ($code) {
        'IN' => strlen($sample) <= 16 && preg_match('~^[A-Za-z0-9/-]+$~', $sample) && in_array($series['reset'], ['fiscal', 'never'], true),
        'VN' => strlen(preg_replace('/\D/', '', $sample)) <= 8,
        default => true,
    };
    $out[] = $ok ? ['ok', 'Invoice numbers follow the rule', $sample . ' · ' . $c['rule']] : ['warn', 'Invoice numbers do not follow the rule', $sample . ' · ' . $c['rule'] . ' Use "Apply the country\'s invoice format" below.'];
    $out[] = ['ok', 'Invoice title', bs_invoice_title()];
    $out[] = ['ok', 'Financial year', date('F', mktime(0, 0, 0, docno_fy_start(), 1)) . ' to ' . date('F', mktime(0, 0, 0, (docno_fy_start() + 10) % 12 + 1, 1))];
    $einvoice = [
        'BD' => 'VAT-registered sellers issue Mushak 6.3 and keep sales and purchase registers (Mushak 6.1, 6.2); large retailers use an NBR-approved EFD/SDC device.',
        'IN' => 'E-invoicing (IRN and QR code from the IRP) is required above ₹5 crore annual turnover; e-way bills for goods moved over ₹50,000.',
        'PK' => 'Tier-1 retailers must connect their POS to FBR (FBR invoice number and QR on each receipt).',
        'NP' => 'Large sellers issue invoices through IRD-approved billing software connected to CBMS.',
        'MY' => 'MyInvois e-invoicing is phased in by turnover; each invoice is validated by LHDN and carries a UUID and QR code.',
        'TH' => 'e-Tax Invoice & e-Receipt is optional but accepted by the Revenue Department; the full tax invoice needs the buyer\'s Tax ID and branch.',
        'VN' => 'All invoices are e-invoices (Decree 123/2020), issued with a tax authority code or sent to the GDT; each has a symbol and a number up to 8 digits.',
    ][$code] ?? '';
    $einvoice = $c['einvoice'] !== '' ? $c['einvoice'] : $einvoice; // PetaBill Cloud's wording wins
    if ($einvoice !== '') {
        $out[] = ['info', 'Government e-invoicing', $einvoice . ' Connecting to the government system is a separate step: ask Petacore Technology.'];
    }
    return $out;
}
