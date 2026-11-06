<?php
/**
 * Document numbering for every module: invoices, orders, returns, challans, purchases, transfers, vouchers, RMA documents,
 * employees, products, portal orders, collections and requests. One engine, so every number is automatic, unique and
 * shown in the form before saving.
 *
 * A series has a pattern, a reset rule and a counter per period:
 *   tokens  {YYYY} {YY} {MM} {DD}   the document date
 *           {FY} 2026-27  {FY2} 2627  {FYS} 2026   the financial year (Settings > Document Numbering sets its first month)
 *           {BR} branch id, 2 digits   {SEQ} or {SEQ:6} the running number, padded
 *   reset   never, yearly, fiscal, monthly or daily: when the running number starts again at 1
 * Numbers are reserved inside the caller's transaction with one atomic statement, so two people saving at the same moment
 * never get the same number, and a rolled-back save gives its number back. A series whose table is known is also checked
 * against that table, so a number already used (typed by hand, imported, or from an older format) is skipped.
 *
 * Existing installs keep their numbers: each default pattern is the format the module used before, and the first number
 * taken in a period continues from the old counter tables (sales_*_sequences, inv_sequences, acc_sequences, rma_sequences)
 * or from the highest number already in the table.
 */

const DOCNO_RESETS = ['never' => 'Never', 'yearly' => 'Every calendar year', 'fiscal' => 'Every financial year', 'monthly' => 'Every month', 'daily' => 'Every day'];

/**
 * Every series the software uses: type => [label, module, default pattern, reset, check [table, column] or null, legacy].
 * legacy: [table, key column or null, key value, year column] for the counter the module used before this engine.
 */
function docno_defaults()
{
    static $d = null;
    if ($d !== null) {
        return $d;
    }
    $inv = docno_setting_raw('inv_settings', 'doc_prefix', 'DES');
    $acc = docno_setting_raw('acc_settings', 'voucher_prefix', 'DES');
    $d = [
        'SI' => ['Sales invoice', 'Sales', 'SI{YYYY}{SEQ:6}', 'yearly', ['sales_invoices', 'invoice_no'], ['sales_invoice_sequences', null, null, 'sequence_year']],
        'SO' => ['Sales order', 'Sales', 'SO{YYYY}{SEQ:6}', 'yearly', ['sales_orders', 'order_no'], ['sales_order_sequences', null, null, 'sequence_year']],
        'SA' => ['Sales order adjustment', 'Sales', 'SA{YYYY}{SEQ:6}', 'yearly', ['sales_order_adjustments', 'adjustment_no'], ['sales_doc_sequences', 'doc_type', 'SA', 'sequence_year']],
        'SR' => ['Sales return', 'Sales', 'SR{YYYY}{SEQ:6}', 'yearly', ['sales_returns', 'return_no'], ['sales_doc_sequences', 'doc_type', 'SR', 'sequence_year']],
        'EMP' => ['Employee code', 'HR', 'EMP{SEQ:5}', 'never', ['hr_employees', 'employee_code'], null],
        'PRODUCT' => ['Product code', 'Products', 'P{SEQ:6}', 'never', ['sales_products', 'product_code'], null],
        'PFS' => ['Sales price fixing', 'Products', 'PFS{YYYY}{MM}{DD}{SEQ:3}', 'daily', ['product_price_fixings', 'fixing_no'], null],
        'PFP' => ['Purchase price fixing', 'Products', 'PFP{YYYY}{MM}{DD}{SEQ:3}', 'daily', ['product_price_fixings', 'fixing_no'], null],
        'PR' => ['Customer portal request', 'Customer Portal', 'PR{YYYY}{MM}{DD}{SEQ:3}', 'daily', ['portal_requests', 'request_no'], null],
        'CL' => ['SR collection', 'SR Portal', 'CL{YYYY}{MM}{DD}{SEQ:3}', 'daily', ['sr_collections', 'collection_no'], null],
    ];
    // Stock documents (purchase, challan, transfer, amendments...) and vouchers share one engine each in their modules.
    foreach (docno_inventory_codes() as $code => $label) {
        $d['INV_' . $code] = [$label, 'Purchase & Stock', $inv . $code . '{YYYY}{SEQ:6}', 'yearly', ['inv_docs', 'doc_no'], ['inv_sequences', 'doc_code', $code, 'seq_year']];
    }
    foreach (docno_voucher_codes() as $code => $label) {
        $d['ACC_' . $code] = [$label, 'Accounts', $acc . $code . '{YYYY}{SEQ:6}', 'yearly', ['acc_vouchers', 'voucher_no'], ['acc_sequences', 'doc_code', $code, 'seq_year']];
    }
    foreach (docno_rma_codes() as $code => $label) {
        $d['RMA_' . $code] = [$label, 'Service', 'DES' . $code . '{YYYY}{SEQ:6}', 'yearly', null, ['rma_sequences', 'doc_type', $code, 'seq_year']];
    }
    return $d;
}

/** Stock document codes the inventory engine numbers (billing/inventory/inv_doc_engine.php), with their names. */
function docno_inventory_codes()
{
    $codes = [];
    if (!defined('INV_TYPES') && is_file(dirname(__DIR__, 2) . '/billing/inventory/inv_common.php')) {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/billing/inventory/inv_common.php'); // read only the list, without loading the module
        if (preg_match_all("/'code'\\s*=>\\s*'([A-Z]{2,6})',\\s*'title'\\s*=>\\s*'([^']+)'/", $src, $m, PREG_SET_ORDER)) {
            foreach ($m as [, $code, $title]) {
                $codes[$code] = $title;
            }
        }
    } elseif (defined('INV_TYPES')) {
        foreach (INV_TYPES as $meta) {
            $codes[$meta['code']] = $meta['title'];
        }
    }
    foreach (docno_q('SELECT DISTINCT doc_code FROM inv_sequences') as $row) {
        $codes[$row['doc_code']] ??= 'Stock document ' . $row['doc_code'];
    }
    return $codes;
}

function docno_voucher_codes()
{
    $codes = ['CV' => 'Credit voucher', 'DV' => 'Debit voucher', 'JV' => 'Journal voucher', 'CTV' => 'Contra voucher', 'IBC' => 'Inter-branch credit', 'IBD' => 'Inter-branch debit', 'IBJ' => 'Inter-branch journal'];
    if (defined('ACC_VOUCHER_TYPES')) {
        foreach (ACC_VOUCHER_TYPES as $type => $v) {
            $codes[$v['code']] ??= ucfirst($type) . ' voucher';
        }
    }
    foreach (docno_q('SELECT DISTINCT doc_code FROM acc_sequences') as $row) {
        $codes[$row['doc_code']] ??= 'Voucher ' . $row['doc_code'];
    }
    return $codes;
}

function docno_rma_codes()
{
    $codes = ['COM' => 'RMA complain receive', 'DIA' => 'RMA diagnosis', 'UTL' => 'RMA item utilize', 'SRB' => 'RMA service bill', 'RIN' => 'RMA replace in', 'RPD' => 'RMA replace delivery', 'REP' => 'RMA replace out'];
    foreach (docno_q('SELECT DISTINCT doc_type FROM rma_sequences') as $row) {
        $codes[$row['doc_type']] ??= 'RMA document ' . $row['doc_type'];
    }
    return $codes;
}

/* ---------------- Storage ---------------- */

function docno_db()
{
    foreach (['conn', 'link', 'cp_db', 'rma_conn'] as $name) {
        if (($GLOBALS[$name] ?? null) instanceof mysqli) {
            return $GLOBALS[$name];
        }
    }
    throw new RuntimeException('Document numbering has no database connection.');
}

/** Rows for a query, or [] if it fails (a table of a module that is not installed). */
function docno_q($sql, $types = '', array $params = [], ?mysqli $db = null)
{
    try {
        $db ??= docno_db();
        $st = $db->prepare($sql);
        if (!$st) {
            return [];
        }
        if ($types !== '') {
            $st->bind_param($types, ...$params);
        }
        if (!$st->execute()) {
            $st->close();
            return [];
        }
        $res = $st->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $st->close();
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function docno_exec(mysqli $db, $sql, $types = '', array $params = [])
{
    $st = $db->prepare($sql);
    if (!$st) {
        throw new RuntimeException('Document numbering: ' . $db->error);
    }
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    if (!$st->execute()) {
        $error = $st->error;
        $st->close();
        throw new RuntimeException('Document numbering: ' . $error);
    }
    $st->close();
}

function docno_setting_raw($table, $key, $default)
{
    $row = docno_q("SELECT setting_value FROM $table WHERE setting_key=? LIMIT 1", 's', [$key]);
    $value = trim((string) ($row[0]['setting_value'] ?? ''));
    return $value !== '' ? $value : $default;
}

/** The two tables, made the first time they are needed (an install from before this engine has neither). */
function docno_ready(mysqli $db)
{
    static $done = false;
    if ($done) {
        return;
    }
    $db->query("CREATE TABLE IF NOT EXISTS doc_series (
        doc_type VARCHAR(30) NOT NULL PRIMARY KEY,
        pattern VARCHAR(80) NOT NULL,
        reset_rule ENUM('never','yearly','fiscal','monthly','daily') NOT NULL DEFAULT 'yearly',
        start_no INT UNSIGNED NOT NULL DEFAULT 1,
        updated_by INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS doc_counters (
        doc_type VARCHAR(30) NOT NULL,
        period_key VARCHAR(12) NOT NULL,
        last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (doc_type, period_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

/** A series as used now: the saved row, else its default. [label, module, pattern, reset, start, check, legacy] */
function docno_series($type)
{
    $defaults = docno_defaults();
    $def = $defaults[$type] ?? [$type, 'Other', $type . '{YYYY}{SEQ:6}', 'yearly', null, null];
    $row = docno_q('SELECT pattern, reset_rule, start_no FROM doc_series WHERE doc_type=?', 's', [$type])[0] ?? null;
    return ['label' => $def[0], 'module' => $def[1], 'pattern' => $row['pattern'] ?? $def[2], 'reset' => $row['reset_rule'] ?? $def[3],
        'start' => max(1, (int) ($row['start_no'] ?? 1)), 'check' => $def[4], 'legacy' => $def[5], 'default_pattern' => $def[2], 'default_reset' => $def[3]];
}

/* ---------------- Periods and formatting ---------------- */

/** First month of the financial year (1-12), from Settings > Document Numbering; else the country's. */
function docno_fy_start()
{
    $m = function_exists('bs_country') && bs_country()['managed'] ? 0 : (int) docno_setting_raw('acc_settings', 'fy_start_month', '0'); // PetaBill Cloud decides when connected
    if ($m < 1 || $m > 12) {
        $m = function_exists('bs_country') ? (int) bs_country()['fy_start'] : 7;
    }
    return max(1, min(12, $m));
}

/** [first year, second year] of the financial year a date falls in. */
function docno_fy($date)
{
    $t = strtotime($date) ?: time();
    $y = (int) date('Y', $t);
    $start = docno_fy_start();
    if ($start === 1) {
        return [$y, $y];
    }
    return (int) date('n', $t) >= $start ? [$y, $y + 1] : [$y - 1, $y];
}

function docno_period($reset, $date)
{
    $t = strtotime($date) ?: time();
    return match ($reset) {
        'never' => 'all',
        'fiscal' => 'FY' . docno_fy($date)[0],
        'monthly' => date('Ym', $t),
        'daily' => date('Ymd', $t),
        default => date('Y', $t),
    };
}

/** A pattern with its tokens filled. */
function docno_format($pattern, $n, $date, array $ctx = [])
{
    $t = strtotime($date) ?: time();
    [$fy1, $fy2] = docno_fy($date);
    $out = strtr($pattern, [
        '{YYYY}' => date('Y', $t), '{YY}' => date('y', $t), '{MM}' => date('m', $t), '{DD}' => date('d', $t),
        '{FY}' => $fy1 === $fy2 ? (string) $fy1 : $fy1 . '-' . substr((string) $fy2, -2),
        '{FY2}' => substr((string) $fy1, -2) . substr((string) $fy2, -2), '{FYS}' => (string) $fy1,
        '{BR}' => str_pad((string) (int) ($ctx['branch_id'] ?? 0), 2, '0', STR_PAD_LEFT),
    ]);
    return preg_replace_callback('/\{SEQ(?::(\d{1,2}))?\}/', fn($m) => str_pad((string) $n, (int) ($m[1] ?? 1) ?: 1, '0', STR_PAD_LEFT), $out);
}

/** '' when a pattern can be used, else what is wrong with it. */
function docno_pattern_error($pattern)
{
    $pattern = trim((string) $pattern);
    if ($pattern === '' || strlen($pattern) > 80) {
        return 'Enter a pattern of up to 80 characters.';
    }
    if (!preg_match('/\{SEQ(?::\d{1,2})?\}/', $pattern)) {
        return 'The pattern needs {SEQ} (or {SEQ:6} for six digits), the running number.';
    }
    $rest = preg_replace('/\{(YYYY|YY|MM|DD|FY|FY2|FYS|BR|SEQ(?::\d{1,2})?)\}/', '', $pattern);
    if (str_contains($rest, '{') || str_contains($rest, '}')) {
        return 'Unknown token. Use {YYYY} {YY} {MM} {DD} {FY} {FY2} {FYS} {BR} {SEQ}.';
    }
    if (!preg_match('~^[A-Za-z0-9/\-_.]*$~', $rest)) {
        return 'Use letters, digits and - / _ . between the tokens.';
    }
    return '';
}

/* ---------------- Reserving and previewing ---------------- */

/** The number the series stood at before this engine, for a period with no counter yet. */
function docno_legacy_last($type, array $s, $period, $date, mysqli $db)
{
    $last = 0;
    if ($s['legacy'] && $s['reset'] === 'yearly' && $s['pattern'] === $s['default_pattern']) { // the old counters fit only the old format
        [$table, $keyCol, $keyVal, $yearCol] = $s['legacy'];
        $year = (int) date('Y', strtotime($date) ?: time());
        $row = $keyCol === null
            ? docno_q("SELECT last_number FROM $table WHERE $yearCol=?", 'i', [$year], $db)
            : docno_q("SELECT last_number FROM $table WHERE $keyCol=? AND $yearCol=?", 'si', [$keyVal, $year], $db);
        $last = (int) ($row[0]['last_number'] ?? 0);
    }
    if ($s['check']) { // the highest number of this exact shape already in the table
        [$table, $col] = $s['check'];
        $seqWidth = preg_match('/\{SEQ(?::(\d+))?\}/', $s['pattern'], $m) ? (int) ($m[1] ?? 0) : 0;
        $head = docno_format(preg_replace('/\{SEQ(?::\d+)?\}.*/', '', $s['pattern']), 0, $date);
        $tail = docno_format(preg_replace('/.*\{SEQ(?::\d+)?\}/', '', $s['pattern']), 0, $date);
        foreach (docno_q("SELECT $col AS v FROM $table WHERE $col LIKE ? ORDER BY LENGTH($col) DESC, $col DESC LIMIT 200", 's', [addcslashes($head, '%_') . '%'], $db) as $row) {
            $v = (string) $row['v'];
            if ($tail !== '' && !str_ends_with($v, $tail)) {
                continue;
            }
            $mid = substr($v, strlen($head), strlen($v) - strlen($head) - strlen($tail));
            if ($mid !== '' && ctype_digit($mid) && ($seqWidth === 0 || strlen($mid) >= $seqWidth)) {
                $last = max($last, (int) $mid);
            }
        }
    }
    return $last;
}

/**
 * Reserve the next number of a series for a document dated $date. Call inside the transaction that saves the document,
 * so a save that fails gives the number back. $ctx: branch_id for {BR}.
 */
function docno_next(mysqli $db, $type, $date = null, array $ctx = [])
{
    docno_ready($db);
    $date = $date ?: date('Y-m-d');
    $s = docno_series($type);
    $period = docno_period($s['reset'], $date);
    if (!docno_q('SELECT 1 AS x FROM doc_counters WHERE doc_type=? AND period_key=?', 'ss', [$type, $period], $db)) {
        $seed = max($s['start'] - 1, docno_legacy_last($type, $s, $period, $date, $db));
        docno_exec($db, 'INSERT IGNORE INTO doc_counters (doc_type, period_key, last_number) VALUES (?, ?, ?)', 'ssi', [$type, $period, $seed]);
    }
    for ($try = 0; $try < 100; $try++) {
        // One statement takes the number and holds the row until the caller commits: nobody else can get the same one.
        docno_exec($db, 'UPDATE doc_counters SET last_number = LAST_INSERT_ID(last_number + 1) WHERE doc_type=? AND period_key=?', 'ss', [$type, $period]);
        $n = (int) ($db->query('SELECT LAST_INSERT_ID() AS n')->fetch_assoc()['n'] ?? 0);
        $no = docno_format($s['pattern'], $n, $date, $ctx);
        if (!$s['check'] || !docno_q("SELECT 1 AS x FROM {$s['check'][0]} WHERE {$s['check'][1]}=? LIMIT 1", 's', [$no], $db)) {
            return $no;
        }
    }
    throw new RuntimeException('No free ' . $s['label'] . ' number. Check Settings > Document Numbering.');
}

/** The number the next document will most likely get, for showing in the form. Reserves nothing. */
function docno_peek(?mysqli $db, $type, $date = null, array $ctx = [])
{
    $db ??= docno_db();
    $date = $date ?: date('Y-m-d');
    try {
        docno_ready($db);
    } catch (Throwable $e) {
    }
    $s = docno_series($type);
    $period = docno_period($s['reset'], $date);
    $row = docno_q('SELECT last_number FROM doc_counters WHERE doc_type=? AND period_key=?', 'ss', [$type, $period], $db);
    $last = $row ? (int) $row[0]['last_number'] : max($s['start'] - 1, docno_legacy_last($type, $s, $period, $date, $db));
    for ($n = $last + 1; $n < $last + 50; $n++) { // skip numbers already used, as docno_next() will
        $no = docno_format($s['pattern'], $n, $date, $ctx);
        if (!$s['check'] || !docno_q("SELECT 1 AS x FROM {$s['check'][0]} WHERE {$s['check'][1]}=? LIMIT 1", 's', [$no], $db)) {
            return $no;
        }
    }
    return docno_format($s['pattern'], $last + 1, $date, $ctx);
}
