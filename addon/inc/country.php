<?php
/**
 * Country editions of PetaBill ERP, managed in PetaBill Cloud and received with the licence (addon/inc/licence.php).
 * The profile gives the currency, the tax and tax-ID names printed on documents, the start of the financial year, the invoice title,
 * how big numbers are grouped, and the invoice number format that suits the country's rules.
 * The figures stay the user's: a profile only fills defaults, and Document Numbering applies its invoice format on request.
 */

const BS_COUNTRIES = [
    'BD' => ['money_word' => 'Taka', 'cent_word' => 'Paisa', 'wht' => 'AIT', 'name' => 'Bangladesh', 'currency' => 'BDT', 'symbol' => '৳', 'tax' => 'VAT', 'tax_id' => 'BIN', 'company_tax_id' => 'BIN', 'fy_start' => 7,
        'invoice_title' => 'VAT Invoice', 'grouping' => 'lakh', 'date' => 'd/m/Y',
        'invoice_pattern' => 'SI{YYYY}{SEQ:6}', 'invoice_reset' => 'yearly',
        'rule' => 'NBR: one serial per business, never repeated (Mushak 6.3 for VAT-registered sellers).'],
    'IN' => ['money_word' => 'Rupees', 'cent_word' => 'Paise', 'wht' => 'TDS', 'name' => 'India', 'currency' => 'INR', 'symbol' => '₹', 'tax' => 'GST', 'tax_id' => 'GSTIN', 'company_tax_id' => 'GSTIN', 'fy_start' => 4,
        'invoice_title' => 'Tax Invoice', 'grouping' => 'lakh', 'date' => 'd/m/Y',
        'invoice_pattern' => 'INV/{FY2}/{SEQ:5}', 'invoice_reset' => 'fiscal',
        'rule' => 'GST Rule 46: at most 16 characters, letters, digits, - and / only, unique within the financial year (April to March).'],
    'PK' => ['money_word' => 'Rupees', 'cent_word' => 'Paisa', 'wht' => 'WHT', 'name' => 'Pakistan', 'currency' => 'PKR', 'symbol' => 'Rs', 'tax' => 'Sales Tax', 'tax_id' => 'NTN / STRN', 'company_tax_id' => 'NTN', 'fy_start' => 7,
        'invoice_title' => 'Sales Tax Invoice', 'grouping' => 'lakh', 'date' => 'd/m/Y',
        'invoice_pattern' => 'INV-{FY2}-{SEQ:6}', 'invoice_reset' => 'fiscal',
        'rule' => 'FBR: a serial number on every sales tax invoice, unique for the seller (July to June year).'],
    'NP' => ['money_word' => 'Rupees', 'cent_word' => 'Paisa', 'wht' => 'TDS', 'name' => 'Nepal', 'currency' => 'NPR', 'symbol' => 'रु', 'tax' => 'VAT', 'tax_id' => 'PAN / VAT No.', 'company_tax_id' => 'PAN', 'fy_start' => 7,
        'invoice_title' => 'Tax Invoice', 'grouping' => 'lakh', 'date' => 'Y-m-d',
        'invoice_pattern' => 'INV-{FY2}-{SEQ:5}', 'invoice_reset' => 'fiscal',
        'rule' => 'IRD: invoices numbered in one series per fiscal year (which starts in Shrawan, mid-July; set the first month below).'],
    'MY' => ['money_word' => 'Ringgit', 'cent_word' => 'Sen', 'wht' => 'WHT', 'name' => 'Malaysia', 'currency' => 'MYR', 'symbol' => 'RM', 'tax' => 'SST', 'tax_id' => 'SST No. / TIN', 'company_tax_id' => 'SST No.', 'fy_start' => 1,
        'invoice_title' => 'Invoice', 'grouping' => 'thousands', 'date' => 'd/m/Y',
        'invoice_pattern' => 'INV-{YYYY}{MM}-{SEQ:5}', 'invoice_reset' => 'yearly',
        'rule' => 'LHDN e-Invoice: a unique internal invoice number of up to 50 characters per supplier.'],
    'TH' => ['money_word' => 'Baht', 'cent_word' => 'Satang', 'wht' => 'WHT', 'name' => 'Thailand', 'currency' => 'THB', 'symbol' => '฿', 'tax' => 'VAT', 'tax_id' => 'Tax ID', 'company_tax_id' => 'Tax ID', 'fy_start' => 1,
        'invoice_title' => 'Tax Invoice / Receipt', 'grouping' => 'thousands', 'date' => 'd/m/Y',
        'invoice_pattern' => 'IV{YYYY}{MM}-{SEQ:5}', 'invoice_reset' => 'monthly',
        'rule' => 'Revenue Department: tax invoices carry a running book and serial number, never repeated.'],
    'VN' => ['money_word' => 'Dong', 'cent_word' => '', 'wht' => 'PIT', 'name' => 'Vietnam', 'currency' => 'VND', 'symbol' => '₫', 'tax' => 'VAT (GTGT)', 'tax_id' => 'MST', 'company_tax_id' => 'MST', 'fy_start' => 1,
        'invoice_title' => 'VAT Invoice (Hóa đơn GTGT)', 'grouping' => 'thousands', 'date' => 'd/m/Y',
        'invoice_pattern' => '{SEQ:8}', 'invoice_reset' => 'yearly',
        'rule' => 'Decree 123/2020: e-invoice numbers of up to 8 digits, starting again at 1 each calendar year within one invoice symbol.'],
];

/**
 * The country this install runs in: the package PetaBill Cloud sends with the licence (Services > the licence, and
 * ERP Countries & Languages). The ERP never sets it itself; an install that is not connected yet works as Bangladesh.
 */
function bs_country_code()
{
    return bs_country()['code'];
}

function bs_country()
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $pack = function_exists('licence_locale') ? licence_locale() : null;
    $p = $pack['country'] ?? null;
    if (is_array($p) && !empty($p['code'])) {
        return $c = [
            'code' => strtoupper((string) $p['code']), 'name' => (string) $p['name'], 'currency' => (string) $p['currency'], 'symbol' => (string) $p['symbol'],
            'decimals' => (int) ($p['decimals'] ?? 2), 'grouping' => ($p['grouping'] ?? '') === 'lakh' ? 'lakh' : 'thousands', 'tax' => (string) $p['tax'],
            'tax_id' => (string) $p['tax_id'], 'company_tax_id' => (string) ($p['company_tax_id'] ?? $p['tax_id']), 'fy_start' => max(1, min(12, (int) ($p['fy_start'] ?? 1))),
            'invoice_title' => (string) $p['invoice_title'], 'invoice_title_unregistered' => (string) ($p['invoice_title_unregistered'] ?? 'Invoice'),
            'invoice_pattern' => (string) $p['invoice_pattern'], 'invoice_reset' => (string) ($p['invoice_reset'] ?? 'yearly'), 'money_word' => (string) $p['money_word'],
            'cent_word' => (string) ($p['cent_word'] ?? ''), 'wht' => (string) ($p['wht'] ?? 'WHT'), 'date' => (string) ($p['date'] ?? 'd/m/Y'),
            'rule' => (string) ($p['rule'] ?? ''), 'einvoice' => (string) ($p['einvoice'] ?? ''), 'managed' => true,
        ];
    }
    return $c = BS_COUNTRIES['BD'] + ['code' => 'BD', 'decimals' => 2, 'invoice_title_unregistered' => 'Bill / Cash Memo', 'einvoice' => '', 'managed' => false];
}

function bs_currency_symbol()
{
    return bs_country()['symbol'];
}

function bs_tax_label()
{
    return bs_country()['tax'];
}

/** An amount grouped the way the country writes it: 1,23,45,678.00 (lakh) or 12,345,678.00 (thousands). */
function bs_number($value, $decimals = null)
{
    $decimals ??= bs_country()['decimals'];
    $value = (float) $value;
    $text = number_format(abs($value), $decimals, '.', '');
    [$int, $frac] = array_pad(explode('.', $text), 2, '');
    if (bs_country()['grouping'] === 'lakh' && strlen($int) > 3) {
        $last = substr($int, -3);
        $int = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3)) . ',' . $last;
    } else {
        $int = number_format((float) $int, 0, '.', ',');
    }
    return ($value < 0 ? '-' : '') . $int . ($frac !== '' ? '.' . $frac : '');
}

/** An amount with the country's currency symbol. */
function bs_money($value, $decimals = null)
{
    return bs_currency_symbol() . ' ' . bs_number($value, $decimals);
}

/**
 * The visible wording of a page in the country's terms: VAT becomes GST, SST, Sales Tax..., BIN becomes GSTIN, NTN...,
 * Taka and Paisa become the country's money, AIT its withholding tax. Field names are lower case (vat_amount, bin), so only
 * what people read changes. Scripts and styles are left alone. Bangladesh, the original wording, is returned untouched.
 * Called on every page by the output step in addon/inc/security.php.
 */
function bs_country_text($html)
{
    $c = bs_country();
    if ($c['code'] === 'BD' || $html === '') {
        return $html;
    }
    $map = [ // the invoice title goes through a marker, so the VAT rule below cannot change it again
        '/\bVAT Invoice\b/' => "\x01TITLE\x01",
        '/\bMushak[\s-]*6\.3\b/i' => "\x01TITLE\x01",
        '/\bVAT\b/' => $c['tax'],
        '/\bBIN\b/' => $c['tax_id'],
        '/\bAIT\b/' => $c['wht'],
        '/\bTaka\b/' => $c['money_word'],
        '/\bPaisa\b/' => $c['cent_word'] !== '' ? $c['cent_word'] : 'cents',
    ];
    $parts = preg_split('~(<script\b.*?</script>|<style\b.*?</style>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $part) {
        if ($i % 2 === 0) { // text and markup, not a script or style block
            $parts[$i] = str_replace("\x01TITLE\x01", $c['invoice_title'], preg_replace(array_keys($map), array_values($map), $part));
        }
    }
    return implode('', $parts);
}
