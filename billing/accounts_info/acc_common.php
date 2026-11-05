<?php
/**
 * Shared code for billing/accounts_info/. Reuses the sales helpers for login, CSRF,
 * prepared statements (sales_exec / sales_rows / sales_row / sales_insert) and flash messages.
 */
require_once dirname(__DIR__) . '/sales/sales_common.php';

const ACC_NATURES = ['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expense'];
const ACC_TYPES = ['general' => 'General', 'cash' => 'Cash', 'bank' => 'Bank', 'receivable' => 'Receivable (customers)', 'payable' => 'Payable (suppliers)', 'loan' => 'Loan / CC'];
const ACC_VOUCHER_TYPES = [
    'debit' => ['label' => 'Debit Voucher', 'code' => 'DRV', 'kind' => 'Payment'],
    'credit' => ['label' => 'Credit Voucher', 'code' => 'CRV', 'kind' => 'Receipt'],
    'journal' => ['label' => 'Journal Voucher', 'code' => 'JUR', 'kind' => 'Journal'],
    'contra' => ['label' => 'Contra Voucher', 'code' => 'CON', 'kind' => 'Contra'],
];
const ACC_MODES = ['Cash', 'Cheque', 'Bank Transfer', 'Mobile Banking', 'Adjustment'];
const ACC_UPLOAD_DIR = __DIR__ . '/uploads';

function acc_ready(mysqli $db)
{
    static $columns = null;
    if ($columns === null) {
        // Columns added by later versions of schema.sql; without them saving would fail, so ask for the upgrade.
        try {
            $columns = (bool) sales_row($db, "SELECT COUNT(*) AS n FROM information_schema.columns WHERE table_schema=DATABASE() AND ((table_name='acc_voucher_lines' AND column_name IN ('is_posted','invoice_no')) OR (table_name='acc_vouchers' AND column_name='is_inter_branch')) HAVING n=3");
        } catch (Throwable $e) {
            $columns = false;
        }
    }
    return $columns && sales_tables_ready($db, ['branches', 'acc_cheque_books', 'acc_cheque_leaves', 'acc_cheques', 'acc_cheque_events', 'acc_settings', 'acc_groups', 'acc_accounts', 'acc_sub_ledger_groups', 'acc_sub_ledgers', 'acc_vouchers', 'acc_voucher_lines', 'acc_voucher_attachments', 'acc_money_receipt_categories', 'acc_cc_loan_rates', 'acc_cc_interest_runs', 'acc_overdue_permissions', 'acc_sequences']);
}

function acc_setting(mysqli $db, $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (sales_rows($db, 'SELECT setting_key, setting_value FROM acc_settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

/** Group tree flattened in display order: each row gets depth and a path label. */
function acc_group_tree(mysqli $db, $activeOnly = false)
{
    $rows = sales_rows($db, 'SELECT * FROM acc_groups' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY sort_order, group_name');
    $children = [];
    foreach ($rows as $row) {
        $children[(int) ($row['parent_id'] ?? 0)][] = $row;
    }
    $out = [];
    $walk = function ($parent, $depth, $path) use (&$walk, &$out, $children) {
        foreach ($children[$parent] ?? [] as $row) {
            $row['depth'] = $depth;
            $row['path'] = $path === '' ? $row['group_name'] : $path . ' / ' . $row['group_name'];
            $out[(int) $row['group_id']] = $row;
            $walk((int) $row['group_id'], $depth + 1, $row['path']);
        }
    };
    $walk(0, 0, '');
    return $out;
}

function acc_accounts(mysqli $db, $activeOnly = true, $where = '')
{
    return sales_rows(
        $db,
        'SELECT a.*, g.nature, g.group_name FROM acc_accounts a JOIN acc_groups g ON g.group_id=a.group_id WHERE 1=1'
            . ($activeOnly ? ' AND a.is_active=1' : '') . ($where !== '' ? ' AND ' . $where : '') . ' ORDER BY a.account_code'
    );
}

function acc_account_options(array $accounts, $selected = 0, $blank = 'Pick account')
{
    $html = $blank !== null ? '<option value="">' . sales_h($blank) . '</option>' : '';
    foreach ($accounts as $a) {
        $html .= '<option value="' . (int) $a['account_id'] . '"' . ((int) $selected === (int) $a['account_id'] ? ' selected' : '') . '>'
            . sales_h($a['account_code'] . ' - ' . $a['account_name']) . '</option>';
    }
    return $html;
}

function acc_options(array $map, $selected)
{
    $html = '';
    foreach ($map as $value => $label) {
        $html .= '<option value="' . sales_h($value) . '"' . ((string) $selected === (string) $value ? ' selected' : '') . '>' . sales_h($label) . '</option>';
    }
    return $html;
}

/** Natural balance sign: assets and expenses grow with debits, the rest with credits. */
function acc_natural($nature, $drMinusCr)
{
    return in_array($nature, ['asset', 'expense'], true) ? $drMinusCr : -$drMinusCr;
}

function acc_signed_opening($amount, $side)
{
    return ($side === 'Cr' ? -1 : 1) * (float) $amount;
}

/** "Dr 1,000.00" style for a signed debit-minus-credit amount. */
function acc_drcr($drMinusCr)
{
    if (abs($drMinusCr) < 0.005) {
        return '0.00';
    }
    return sales_money(abs($drMinusCr)) . ($drMinusCr > 0 ? ' Dr' : ' Cr');
}

/** SQL expression for the reporting date on acc_vouchers v: voucher date, or cheque date when it exists. */
function acc_date_expr($basis)
{
    return $basis === 'cheque' ? 'COALESCE(v.cheque_date, v.voucher_date)' : 'v.voucher_date';
}

/** Same date on acc_voucher_lines l, using the copied (indexed) columns. */
function acc_line_date($basis)
{
    return $basis === 'cheque' ? 'l.basis_date' : 'l.voucher_date';
}

/**
 * Copy a voucher's status and dates onto its lines. Call after any change to a voucher's status, dates or lines.
 * Reports total the lines through covering indexes on these columns instead of joining every line to its voucher.
 */
function acc_sync_lines(mysqli $db, $voucherId)
{
    sales_exec(
        $db,
        "UPDATE acc_voucher_lines l JOIN acc_vouchers v ON v.voucher_id=l.voucher_id SET l.is_posted=(v.status='posted'), l.voucher_date=v.voucher_date, l.basis_date=COALESCE(l.cheque_date, v.cheque_date, v.voucher_date) WHERE l.voucher_id=?",
        'i',
        [(int) $voucherId]
    )->close();
}

/** Posted-line totals grouped by $col, optionally for one id. Returns rows with id, pre (dr-cr before $from), dr, cr, last_cr. */
function acc_line_totals(mysqli $db, $col, $from, $to, $basis, $onlyId = 0)
{
    $ld = acc_line_date($basis);
    $sql = "SELECT l.$col AS id, SUM(CASE WHEN $ld < ? THEN l.debit - l.credit ELSE 0 END) AS pre, SUM(CASE WHEN $ld >= ? THEN l.debit ELSE 0 END) AS dr, "
        . "SUM(CASE WHEN $ld >= ? THEN l.credit ELSE 0 END) AS cr, MAX(IF(l.credit > 0, $ld, NULL)) AS last_cr FROM acc_voucher_lines l WHERE l.is_posted=1 AND $ld <= ?";
    $types = 'ssss';
    $params = [$from, $from, $from, $to];
    if ($onlyId > 0) {
        $sql .= " AND l.$col=?";
        $types .= 'i';
        $params[] = (int) $onlyId;
    } else {
        $sql .= " AND l.$col IS NOT NULL";
    }
    return sales_rows($db, $sql . " GROUP BY l.$col", $types, $params);
}

/**
 * Per-account figures for a period from posted vouchers:
 * [account_id => ['open' => dr-cr before $from incl. openings, 'dr' => period debit, 'cr' => period credit]].
 * Pass $onlyId to work out a single account.
 */
function acc_account_movements(mysqli $db, $from, $to, $basis = 'voucher', $onlyId = 0)
{
    $out = [];
    $where = $onlyId > 0 ? ' WHERE account_id=' . (int) $onlyId : '';
    foreach (sales_rows($db, 'SELECT account_id, opening_balance, opening_side FROM acc_accounts' . $where) as $a) {
        $out[(int) $a['account_id']] = ['open' => acc_signed_opening($a['opening_balance'], $a['opening_side']), 'dr' => 0.0, 'cr' => 0.0];
    }
    foreach (sales_rows($db, "SELECT account_id, SUM(IF(opening_side='Cr', -opening_balance, opening_balance)) AS o FROM acc_sub_ledgers" . $where . ' GROUP BY account_id') as $s) {
        $out[(int) $s['account_id']]['open'] = ($out[(int) $s['account_id']]['open'] ?? 0) + (float) $s['o'];
    }
    foreach (acc_line_totals($db, 'account_id', $from, $to, $basis, $onlyId) as $r) {
        $id = (int) $r['id'];
        $out[$id] = $out[$id] ?? ['open' => 0.0, 'dr' => 0.0, 'cr' => 0.0];
        $out[$id]['open'] += (float) $r['pre'];
        $out[$id]['dr'] += (float) $r['dr'];
        $out[$id]['cr'] += (float) $r['cr'];
    }
    return $out;
}

/** Same as acc_account_movements but per sub ledger (party). Pass $onlyId for a single party. */
function acc_sub_ledger_movements(mysqli $db, $from, $to, $basis = 'voucher', $onlyId = 0)
{
    $out = [];
    $where = $onlyId > 0 ? ' WHERE sub_ledger_id=' . (int) $onlyId : '';
    foreach (sales_rows($db, 'SELECT sub_ledger_id, opening_balance, opening_side FROM acc_sub_ledgers' . $where) as $s) {
        $out[(int) $s['sub_ledger_id']] = ['open' => acc_signed_opening($s['opening_balance'], $s['opening_side']), 'dr' => 0.0, 'cr' => 0.0, 'last_cr' => null];
    }
    foreach (acc_line_totals($db, 'sub_ledger_id', $from, $to, $basis, $onlyId) as $r) {
        $id = (int) $r['id'];
        $out[$id] = $out[$id] ?? ['open' => 0.0, 'dr' => 0.0, 'cr' => 0.0, 'last_cr' => null];
        $out[$id]['open'] += (float) $r['pre'];
        $out[$id]['dr'] += (float) $r['dr'];
        $out[$id]['cr'] += (float) $r['cr'];
        $out[$id]['last_cr'] = $r['last_cr'];
    }
    return $out;
}

/** Reserve the next voucher number inside a transaction, e.g. DESJUR2024000002. */
function acc_voucher_code($type, $interBranch = false)
{
    return $interBranch ? ['debit' => 'IBD', 'credit' => 'IBC', 'journal' => 'IBJ'][$type] ?? ACC_VOUCHER_TYPES[$type]['code'] : ACC_VOUCHER_TYPES[$type]['code'];
}

function acc_next_voucher_no(mysqli $db, $type, $date, $interBranch = false)
{
    $code = acc_voucher_code($type, $interBranch);
    $year = (int) substr($date, 0, 4);
    sales_exec($db, 'INSERT INTO acc_sequences (doc_code, seq_year, last_number) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE last_number = last_number + 1', 'si', [$code, $year])->close();
    $row = sales_row($db, 'SELECT last_number FROM acc_sequences WHERE doc_code=? AND seq_year=? FOR UPDATE', 'si', [$code, $year]);
    return acc_setting($db, 'voucher_prefix', 'DES') . $code . $year . str_pad((string) $row['last_number'], 6, '0', STR_PAD_LEFT);
}

function acc_peek_voucher_no(mysqli $db, $type, $interBranch = false)
{
    $code = acc_voucher_code($type, $interBranch);
    $row = sales_row($db, 'SELECT last_number + 1 AS n FROM acc_sequences WHERE doc_code=? AND seq_year=?', 'si', [$code, (int) date('Y')]);
    return acc_setting($db, 'voucher_prefix', 'DES') . $code . date('Y') . str_pad((string) ($row['n'] ?? 1), 6, '0', STR_PAD_LEFT);
}

/** Read a posted date range: All Date, From, To, date basis. */
function acc_period_from_request(array $src)
{
    $all = !empty($src['all']);
    $from = sales_valid_date($src['from'] ?? '', true) ?: date('Y-m-01');
    $to = sales_valid_date($src['to'] ?? '', true) ?: date('Y-m-d');
    if ($all) {
        $from = '1900-01-01';
        $to = '2100-12-31';
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $basis = ($src['basis'] ?? '') === 'cheque' ? 'cheque' : 'voucher';
    return [$from, $to, $basis, $all];
}

function acc_fmt_date($date)
{
    if (!$date) {
        return '';
    }
    $d = DateTime::createFromFormat('Y-m-d', substr((string) $date, 0, 10));
    return $d ? $d->format('d/m/Y') : (string) $date;
}

/* ---------------- Layout ---------------- */

function acc_menu()
{
    return [
        'Accounts Information' => [
            'group' => ['acc_group.php', 'Accounts Group Distribution'],
            'account' => ['acc_account.php', 'General Accounts'],
            'slg' => ['acc_sub_ledger_group.php', 'Accounts Sub Ledger Group'],
            'sl' => ['acc_sub_ledger.php', 'Accounts Sub Ledger'],
            'mrc' => ['acc_receipt_category.php', 'MoneyReceiptCategory'],
            'config' => ['../user/company_settings.php?tab=accounts', 'Company & Login Settings'],
            'cc' => ['acc_cc_interest.php', 'CC Loan Interest'],
            'odp' => ['acc_overdue.php', 'Over Due Permission'],
        ],
        'Voucher Entry' => [
            'rv' => ['acc_rp_voucher.php?type=credit', 'Receive Voucher'],
            'pv' => ['acc_rp_voucher.php?type=debit', 'Payment Voucher'],
            'ibc' => ['acc_rp_voucher.php?type=credit&ib=1', 'Inter Branch Credit Voucher'],
            'ibd' => ['acc_rp_voucher.php?type=debit&ib=1', 'Inter Branch Debit Voucher'],
            'v_journal' => ['acc_voucher.php?type=journal', 'Journal Voucher'],
            'cheque_book' => ['acc_cheque_book.php', 'Cheque Book'],
            'cheque_treat' => ['acc_cheque_treatment.php', 'Cheque Treatment'],
            'ibj' => ['acc_voucher.php?type=journal&ib=1', 'Inter Branch Journal Voucher'],
            'v_contra' => ['acc_voucher.php?type=contra', 'Contra Voucher'],
            'cheque_history' => ['acc_cheque_history.php', 'Cheque History'],
        ],
        'Voucher Posting' => [
            'posting' => ['acc_posting.php', 'Voucher Posting'],
        ],
        'Reports' => [
            'register' => ['acc_register.php', 'Accounts Register'],
        ],
    ];
}

function acc_page_start($title, $active = '')
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . sales_h($title) . '</title><link rel="stylesheet" href="../sales/sales.css">';
    echo '<style>fieldset.box{border:1px solid #9aa6b5;border-radius:4px;padding:12px 14px;margin:0}fieldset.box>legend{font-size:20px;font-weight:bold;padding:0 6px}'
        . '.check{width:auto!important;height:auto!important}.selected-row td{background:#fff7d6}.acc-nav{display:flex;flex-wrap:wrap;gap:4px 14px;margin-bottom:8px;font-size:13px}'
        . '.acc-nav b{color:#55657a;margin-right:4px}.acc-nav a{color:#2563a6;text-decoration:none;margin-right:6px}.acc-nav a.active{font-weight:bold;text-decoration:underline}'
        . '.inline{display:flex;gap:6px;align-items:center}.inline input,.inline select{flex:1}.tree-1{padding-left:18px}.tree-2{padding-left:36px}.tree-3{padding-left:54px}.tree-4{padding-left:72px}</style></head><body><div class="wrap"><nav class="acc-nav">';
    foreach (acc_menu() as $section => $links) {
        echo '<span><b>' . sales_h($section) . ':</b>';
        foreach ($links as $key => [$href, $label]) {
            echo '<a href="' . sales_h($href) . '"' . ($key === $active ? ' class="active"' : '') . '>' . sales_h($label) . '</a>';
        }
        echo '</span>';
    }
    echo '</nav><main class="panel">';
}

function acc_page_end($script = '')
{
    echo '</main></div><script src="../sales/sales.js"></script>';
    if ($script !== '') {
        echo '<script>' . $script . '</script>';
    }
    echo '</body></html>';
}

function acc_missing_html()
{
    return '<div class="errorbox"><strong>Accounts tables are missing or out of date.</strong><br>Import <code>billing/accounts_info/schema.sql</code> into the MySQL database (it upgrades older installs safely), then reload this page.</div>';
}

/** Stop the page early when the tables are missing. */
function acc_require_ready(mysqli $db, $title, $active)
{
    if (acc_ready($db)) {
        return;
    }
    acc_page_start($title, $active);
    echo acc_missing_html();
    acc_page_end();
    exit;
}

/* ---------------- Generic master-data page (form + list) ---------------- */

/**
 * Renders and handles a simple master table page.
 * $cfg: self, title, menu, table, pk, fields[name => [label, type, opts]], list_sql (with {where}), search[], columns[key => label|[label, fn]],
 *       validate(fn(&$values, $id)), after_save(fn($id, $values)), delete_check(fn($id): string), extra_post(fn($action)), before_list(html), after_form(html)
 * Field types: text, textarea, decimal, int, date, select, check. Field opts: required, max, options, default, nullable, span, help.
 */
function acc_crud(mysqli $db, array $cfg)
{
    $self = $cfg['self'];
    $pk = $cfg['pk'];
    $fields = $cfg['fields'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        sales_require_csrf($self);
        $action = (string) ($_POST['action'] ?? 'add');
        $id = (int) ($_POST['id'] ?? 0);
        try {
            if (isset($cfg['extra_post']) && !in_array($action, ['add', 'edit', 'delete'], true)) {
                $message = ($cfg['extra_post'])($action);
                sales_flash_redirect($self, 'success', $message);
            }
            if ($action === 'delete') {
                if (!sales_row($db, "SELECT 1 FROM {$cfg['table']} WHERE $pk=?", 'i', [$id])) {
                    throw new RuntimeException('Record not found.');
                }
                $why = isset($cfg['delete_check']) ? ($cfg['delete_check'])($id) : '';
                if ($why !== '') {
                    throw new RuntimeException($why);
                }
                sales_exec($db, "DELETE FROM {$cfg['table']} WHERE $pk=?", 'i', [$id])->close();
                sales_flash_redirect($self, 'success', 'Deleted.');
            }
            $values = [];
            $types = '';
            foreach ($fields as $name => $f) {
                $type = $f['type'] ?? 'text';
                $raw = $_POST[$name] ?? null;
                if ($type === 'check') {
                    $values[$name] = !empty($raw) ? 1 : 0;
                    $types .= 'i';
                    continue;
                }
                $raw = trim((string) ($raw ?? ''));
                if ($raw === '' && $type === 'select' && empty($f['blank'])) {
                    // A dropdown with no blank choice always has a value; fall back to its default or first option.
                    $raw = (string) ($f['default'] ?? array_key_first($f['options']) ?? '');
                }
                if ($raw === '' && !empty($f['required'])) {
                    throw new RuntimeException('Enter ' . $f['label'] . '.');
                }
                if ($type === 'decimal') {
                    if ($raw !== '' && !is_numeric($raw)) {
                        throw new RuntimeException($f['label'] . ' must be a number.');
                    }
                    $values[$name] = round((float) $raw, 3);
                    if (isset($f['min']) && $values[$name] < $f['min']) {
                        throw new RuntimeException($f['label'] . ' cannot be below ' . $f['min'] . '.');
                    }
                    $types .= 'd';
                } elseif ($type === 'int' || $type === 'select') {
                    if ($type === 'select' && $raw !== '' && !array_key_exists($raw, $f['options'])) {
                        throw new RuntimeException('Pick a valid ' . $f['label'] . '.');
                    }
                    if (!empty($f['string'])) {
                        $values[$name] = $raw;
                        $types .= 's';
                    } else {
                        $values[$name] = $raw === '' ? (!empty($f['nullable']) ? null : 0) : (int) $raw;
                        $types .= 'i';
                    }
                } elseif ($type === 'date') {
                    $date = sales_valid_date($raw, empty($f['required']));
                    if ($date === null) {
                        throw new RuntimeException('Enter a valid ' . $f['label'] . '.');
                    }
                    $values[$name] = $date === '' ? null : $date;
                    $types .= 's';
                } else {
                    $values[$name] = sales_cut($raw, (int) ($f['max'] ?? 255));
                    $types .= 's';
                }
            }
            $db->begin_transaction();
            if (isset($cfg['validate'])) {
                ($cfg['validate'])($values, $action === 'edit' ? $id : 0);
            }
            // Bind types follow the final PHP value types, so validate() may add derived columns.
            $types = '';
            foreach ($values as $value) {
                $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
            }
            $cols = array_keys($values);
            if ($action === 'edit') {
                if (!sales_row($db, "SELECT 1 FROM {$cfg['table']} WHERE $pk=? FOR UPDATE", 'i', [$id])) {
                    throw new RuntimeException('Select a record to edit first.');
                }
                sales_exec($db, "UPDATE {$cfg['table']} SET " . implode(', ', array_map(fn($c) => "$c=?", $cols)) . " WHERE $pk=?", $types . 'i', array_merge(array_values($values), [$id]))->close();
            } else {
                $id = sales_insert($db, "INSERT INTO {$cfg['table']} (" . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', $types, array_values($values));
            }
            if (isset($cfg['after_save'])) {
                ($cfg['after_save'])($id, $values);
            }
            $db->commit();
            sales_flash_redirect($self . (str_contains($self, '?') ? '&' : '?') . 'id=' . $id, 'success', 'Saved.');
        } catch (Throwable $e) {
            try { $db->rollback(); } catch (Throwable $ignored) {}
            $message = $e->getMessage();
            if (str_contains($message, 'Duplicate entry')) {
                $message = 'That code or name is already used.';
            }
            sales_flash_redirect($self . ($id > 0 ? (str_contains($self, '?') ? '&' : '?') . 'id=' . $id : ''), 'error', $message);
        }
    }

    $flash = sales_take_flash();
    $id = (int) ($_GET['id'] ?? 0);
    $record = $id > 0 ? sales_row($db, "SELECT * FROM {$cfg['table']} WHERE $pk=?", 'i', [$id]) : null;
    $q = trim((string) ($_GET['q'] ?? ''));
    $where = 'WHERE 1=1';
    $types = '';
    $params = [];
    if ($q !== '' && !empty($cfg['search'])) {
        $where .= ' AND (' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $cfg['search'])) . ')';
        $types = str_repeat('s', count($cfg['search']));
        $params = array_fill(0, count($cfg['search']), '%' . $q . '%');
    }
    $rows = isset($cfg['rows']) ? ($cfg['rows'])($q) : sales_list_rows($db, str_replace('{where}', $where, $cfg['list_sql']) . '', $types, $params);

    acc_page_start($cfg['title'], $cfg['menu']);
    echo sales_flash_html($flash);
    echo '<fieldset class="box"><legend>' . sales_h($cfg['title']) . '</legend>';
    echo $cfg['before_form'] ?? '';
    echo '<form method="post" autocomplete="off">' . sales_csrf_field() . '<input type="hidden" name="id" value="' . ($record ? $id : 0) . '"><div class="grid g4" style="margin-top:0">';
    foreach ($fields as $name => $f) {
        $type = $f['type'] ?? 'text';
        $value = $record[$name] ?? ($f['default'] ?? '');
        $span = isset($f['span']) ? ' style="grid-column:span ' . (int) $f['span'] . '"' : '';
        $req = !empty($f['required']) ? ' required' : '';
        echo '<div class="field"' . $span . '>';
        if ($type === 'check') {
            echo '<label>&nbsp;</label><label><input class="check" type="checkbox" name="' . $name . '" value="1"' . (($record ? (int) $value : (int) ($f['default'] ?? 1)) ? ' checked' : '') . '> ' . sales_h($f['label']) . '</label>';
        } else {
            echo '<label for="f_' . $name . '">' . sales_h($f['label']) . '</label>';
            if ($type === 'select') {
                echo '<select id="f_' . $name . '" name="' . $name . '"' . $req . '>' . (!empty($f['blank']) ? '<option value="">' . sales_h($f['blank']) . '</option>' : '') . acc_options($f['options'], (string) ($value ?? '')) . '</select>';
            } elseif ($type === 'textarea') {
                echo '<textarea id="f_' . $name . '" name="' . $name . '" maxlength="' . (int) ($f['max'] ?? 255) . '"' . $req . '>' . sales_h($value) . '</textarea>';
            } else {
                $htmlType = ['decimal' => 'number', 'int' => 'number', 'date' => 'date'][$type] ?? 'text';
                $extra = $type === 'decimal' ? ' step="0.001"' : ($type === 'int' ? ' step="1"' : ' maxlength="' . (int) ($f['max'] ?? 255) . '"');
                echo '<input id="f_' . $name . '" type="' . $htmlType . '" name="' . $name . '" value="' . sales_h($value ?? '') . '"' . $extra . $req . '>';
            }
            if (!empty($f['help'])) {
                echo '<span class="muted" style="font-size:11px">' . sales_h($f['help']) . '</span>';
            }
        }
        echo '</div>';
    }
    echo '</div><div class="toolbar" style="margin-top:10px"><button class="btn primary" name="action" value="add">Add</button>'
        . '<button class="btn" name="action" value="edit"' . ($record ? '' : ' disabled title="Select a record first"') . '>Edit</button>'
        . '<a class="btn" href="' . sales_h($self) . '">Clear</a></div></form>';
    echo $cfg['after_form'] ?? '';
    if (!empty($cfg['search'])) {
        echo '<form method="get" class="list-search" style="margin-top:10px">';
        if (str_contains($self, '?')) {
            parse_str((string) parse_url($self, PHP_URL_QUERY), $keep);
            foreach ($keep as $k => $v) {
                echo '<input type="hidden" name="' . sales_h($k) . '" value="' . sales_h($v) . '">';
            }
        }
        echo '<input class="input" name="q" value="' . sales_h($q) . '" placeholder="Search"><button class="btn">Search</button></form>';
    }
    echo '<div class="table-scroll" style="max-height:520px"><table><thead><tr><th>SL</th>';
    foreach ($cfg['columns'] as $col) {
        $label = is_array($col) ? $col[0] : $col;
        echo '<th>' . sales_h($label) . '</th>';
    }
    echo '<th>Select</th><th>Delete</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="' . (count($cfg['columns']) + 3) . '" class="empty">No records.</td></tr>';
    }
    foreach ($rows as $i => $row) {
        $rid = (int) $row[$pk];
        echo '<tr class="' . ($rid === $id ? 'selected-row' : '') . '"><td>' . ($i + 1) . '</td>';
        foreach ($cfg['columns'] as $key => $col) {
            $cell = is_array($col) ? ($col[1])($row) : sales_h($row[$key] ?? '');
            echo '<td' . (is_array($col) && !empty($col[2]) ? ' class="num"' : '') . '>' . $cell . '</td>';
        }
        echo '<td><a class="btn small" href="' . sales_h($self) . (str_contains($self, '?') ? '&' : '?') . 'id=' . $rid . '">Select</a></td>';
        echo '<td><form method="post" onsubmit="return confirm(\'Delete this record?\')">' . sales_csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . $rid . '"><button class="btn small danger">Delete</button></form></td></tr>';
    }
    echo '</tbody></table></div></fieldset>';
    acc_page_end($cfg['script'] ?? '');
}

/**
 * Check and normalise voucher lines: [['account_id', 'sub_ledger_id', 'debit', 'credit', 'line_narration'], ...].
 * Enforces one side per line, sub ledgers on control accounts, balanced totals, and the voucher type rules:
 * debit (payment) vouchers pay out of cash/bank, credit (receipt) vouchers receive into cash/bank, contra moves only between cash/bank.
 */
function acc_validate_lines(mysqli $db, $type, array $lines)
{
    $accounts = [];
    foreach (acc_accounts($db, false) as $a) {
        $accounts[(int) $a['account_id']] = $a;
    }
    $clean = [];
    $dr = $cr = 0.0;
    foreach ($lines as $l) {
        $accountId = (int) ($l['account_id'] ?? 0);
        $debit = sales_amount($l['debit'] ?? 0);
        $credit = sales_amount($l['credit'] ?? 0);
        if ($accountId === 0 && $debit == 0 && $credit == 0) {
            continue;
        }
        $a = $accounts[$accountId] ?? null;
        if (!$a || !(int) $a['is_active']) {
            throw new RuntimeException('Each line needs an active account.');
        }
        if (($debit > 0) === ($credit > 0)) {
            throw new RuntimeException('Each line must have either a debit or a credit amount (' . $a['account_name'] . ').');
        }
        $subId = (int) ($l['sub_ledger_id'] ?? 0);
        if ((int) $a['has_sub_ledger']) {
            $sub = $subId ? sales_row($db, 'SELECT account_id, is_active FROM acc_sub_ledgers WHERE sub_ledger_id=?', 'i', [$subId]) : null;
            if (!$sub || (int) $sub['account_id'] !== $accountId || !(int) $sub['is_active']) {
                throw new RuntimeException('Pick the party (sub ledger) for ' . $a['account_name'] . '.');
            }
        } else {
            $subId = 0;
        }
        $cashBank = in_array($a['account_type'], ['cash', 'bank'], true);
        if ($type === 'contra' && !$cashBank) {
            throw new RuntimeException('A contra voucher can only use cash and bank accounts.');
        }
        if ($type === 'debit' && $credit > 0 && !$cashBank) {
            throw new RuntimeException('In a debit (payment) voucher the credit side must be a cash or bank account.');
        }
        if ($type === 'credit' && $debit > 0 && !$cashBank) {
            throw new RuntimeException('In a credit (receipt) voucher the debit side must be a cash or bank account.');
        }
        $dr += $debit;
        $cr += $credit;
        $chequeDate = sales_valid_date($l['cheque_date'] ?? '', true);
        if ($chequeDate === null) {
            throw new RuntimeException('Enter a valid cheque date for ' . $a['account_name'] . '.');
        }
        $mode = in_array(($l['transaction_mode'] ?? ''), ACC_MODES, true) ? (string) $l['transaction_mode'] : '';
        $chequeNo = sales_str($l['cheque_no'] ?? '', 40);
        if ($mode === 'Cheque' && $chequeNo === '') {
            throw new RuntimeException('Enter the cheque number for ' . $a['account_name'] . '.');
        }
        $clean[] = ['account_id' => $accountId, 'sub_ledger_id' => $subId ?: null, 'debit' => $debit, 'credit' => $credit, 'line_narration' => sales_str($l['line_narration'] ?? '', 255),
            'branch_id' => ((int) ($l['branch_id'] ?? 0)) ?: null, 'transaction_mode' => $mode, 'cheque_no' => $chequeNo, 'cheque_date' => $chequeDate ?: null, 'invoice_no' => sales_str($l['invoice_no'] ?? '', 40)];
    }
    if (count($clean) < 2) {
        throw new RuntimeException('A voucher needs at least one debit line and one credit line.');
    }
    if (abs($dr - $cr) > 0.004) {
        throw new RuntimeException('Debit total ' . sales_money($dr) . ' and credit total ' . sales_money($cr) . ' must be equal.');
    }
    if (in_array($type, ['debit', 'credit'], true) && !array_filter($clean, fn($l) => $type === 'debit' ? $l['credit'] > 0 : $l['debit'] > 0)) {
        throw new RuntimeException('Add the cash or bank line.');
    }
    return [$clean, round($dr, 2)];
}

function acc_write_lines(mysqli $db, $voucherId, array $clean)
{
    sales_exec($db, 'DELETE FROM acc_voucher_lines WHERE voucher_id=?', 'i', [$voucherId])->close();
    foreach ($clean as $n => $l) {
        sales_insert(
            $db,
            'INSERT INTO acc_voucher_lines (voucher_id, account_id, sub_ledger_id, debit, credit, line_narration, line_no, branch_id, transaction_mode, cheque_no, cheque_date, invoice_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'iiiddsiissss',
            [$voucherId, $l['account_id'], $l['sub_ledger_id'], $l['debit'], $l['credit'], $l['line_narration'], $n + 1, $l['branch_id'] ?? null, $l['transaction_mode'] ?? '', $l['cheque_no'] ?? '', $l['cheque_date'] ?? null, $l['invoice_no'] ?? '']
        );
    }
    acc_sync_lines($db, $voucherId);
}

/** Create a voucher from code (used by CC loan interest). Call inside a transaction. Returns [voucher_id, voucher_no]. */
function acc_create_voucher(mysqli $db, $type, $date, array $lines, array $header = [], $status = 'posted')
{
    [$clean, $total] = acc_validate_lines($db, $type, $lines);
    $no = acc_next_voucher_no($db, $type, $date);
    $userId = (int) ($GLOBALS['user_id'] ?? 0);
    $id = sales_insert(
        $db,
        'INSERT INTO acc_vouchers (voucher_no, voucher_type, voucher_date, ref_no, narration, transaction_mode, total_amount, status, source, created_by, posted_by, posted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'ssssssdssiis',
        [$no, $type, $date, $header['ref_no'] ?? '', $header['narration'] ?? '', $header['transaction_mode'] ?? 'Adjustment', $total, $status, $header['source'] ?? 'system', $userId, $status === 'posted' ? $userId : null, $status === 'posted' ? date('Y-m-d H:i:s') : null]
    );
    acc_write_lines($db, $id, $clean);
    return [$id, $no];
}

/** Accounts Register report list: number => [caption, needs]. needs: 'party' = account or sub ledger required. */
const ACC_REPORTS = [
    1 => ['Accounts Ledger', 'party'],
    2 => ['Due List', ''],
    3 => ['Accounts Ledger Details', 'party'],
    4 => ['Day Book', ''],
    5 => ['Bank Summary', ''],
    6 => ['Cash and Bank Summary', ''],
    7 => ['Date Wise Credit Voucher', ''],
    8 => ['Date Wise Debit Voucher', ''],
    9 => ['Date Wise Journal Voucher', ''],
    10 => ['Income Statement', ''],
    11 => ['Balance Sheet Tree Format', ''],
    12 => ['Trial Balance', ''],
    13 => ['Trial Balance Details', ''],
    14 => ['Branch Wise Due List', ''],
    15 => ['Date Wise Sales Received', ''],
    16 => ['Date Wise Sales Received Explicit', ''],
    17 => ['Chart Of Accounts', ''],
    18 => ['Supplier Information', ''],
    19 => ['Trader Information', ''],
    20 => ['Date Wise General Income', ''],
    21 => ['Date Wise General Expenses', ''],
    22 => ['Central Income Statement', ''],
];

/* ---------------- Branches and transaction locks ---------------- */

function acc_branches(mysqli $db)
{
    return sales_rows($db, 'SELECT branch_id, branch_name FROM branches WHERE is_active=1 ORDER BY branch_id');
}

/** Working branch, shared with the inventory pages through the session. */
function acc_current_branch(mysqli $db)
{
    return perm_pick_branch(array_map('intval', array_column(acc_branches($db), 'branch_id')));
}

/** Refuse vouchers dated inside a Transaction Lock (billing/amedment/transaction_lock.php) for the branch. */
function acc_assert_unlocked(mysqli $db, $date, $branchId)
{
    if (!sales_tables_ready($db, ['inv_transaction_locks'])) {
        return;
    }
    $lock = sales_row($db, 'SELECT MAX(lock_date) AS d FROM inv_transaction_locks WHERE is_active=1 AND (branch_id IS NULL OR branch_id=?) AND lock_date >= ?', 'is', [(int) $branchId, $date]);
    if (!empty($lock['d'])) {
        throw new RuntimeException('Transactions on or before ' . acc_fmt_date($lock['d']) . ' are locked for this branch.');
    }
}

/**
 * Keep the cheque register in step with a voucher's cheque lines. Received cheques come from credit (receive)
 * vouchers, issued cheques from debit (payment) vouchers; cheques already honoured or dishonoured are left alone.
 */
function acc_sync_cheques(mysqli $db, $voucherId)
{
    $v = sales_row($db, 'SELECT voucher_type, status FROM acc_vouchers WHERE voucher_id=?', 'i', [(int) $voucherId]);
    if (!$v || !in_array($v['voucher_type'], ['credit', 'debit'], true)) {
        return;
    }
    $treated = sales_row($db, "SELECT cheque_no FROM acc_cheques WHERE voucher_id=? AND status IN ('honoured','dishonoured') LIMIT 1", 'i', [(int) $voucherId]);
    if ($treated) {
        throw new RuntimeException('Cheque ' . $treated['cheque_no'] . ' on this voucher is already honoured or dishonoured, so the voucher cannot change.');
    }
    sales_exec($db, 'DELETE FROM acc_cheques WHERE voucher_id=?', 'i', [(int) $voucherId])->close();
    sales_exec($db, "UPDATE acc_cheque_leaves SET status='unused', voucher_id=NULL WHERE voucher_id=? AND status='used'", 'i', [(int) $voucherId])->close();
    if ($v['status'] === 'cancelled') {
        return;
    }
    $lines = sales_rows($db, "SELECT l.*, a.account_type, COALESCE(s.sub_ledger_name, a.account_name) AS name FROM acc_voucher_lines l JOIN acc_accounts a ON a.account_id=l.account_id LEFT JOIN acc_sub_ledgers s ON s.sub_ledger_id=l.sub_ledger_id WHERE l.voucher_id=? ORDER BY l.line_no", 'i', [(int) $voucherId]);
    $direction = $v['voucher_type'] === 'credit' ? 'received' : 'issued';
    foreach ($lines as $l) {
        // The party line carries the cheque; its bank line is the cash/bank line with the same cheque number.
        if ($l['transaction_mode'] !== 'Cheque' || $l['cheque_no'] === '' || in_array($l['account_type'], ['cash', 'bank'], true)) {
            continue;
        }
        $bank = null;
        foreach ($lines as $b) {
            if (in_array($b['account_type'], ['cash', 'bank'], true) && $b['cheque_no'] === $l['cheque_no']) {
                $bank = $b;
                break;
            }
        }
        if (!$bank) {
            continue;
        }
        if ($direction === 'issued') {
            $leaf = sales_row($db, 'SELECT l.leaf_id, l.status, b.approval_status FROM acc_cheque_leaves l JOIN acc_cheque_books b ON b.book_id=l.book_id WHERE l.account_id=? AND l.leaf_no=? FOR UPDATE', 'is', [(int) $bank['account_id'], $l['cheque_no']]);
            $hasBooks = sales_row($db, 'SELECT 1 FROM acc_cheque_books WHERE account_id=? LIMIT 1', 'i', [(int) $bank['account_id']]);
            if ($hasBooks && !$leaf) {
                throw new RuntimeException('Cheque ' . $l['cheque_no'] . ' is not a leaf of any cheque book of this bank account.');
            }
            if ($leaf && $leaf['approval_status'] !== 'approved') {
                throw new RuntimeException('The cheque book holding cheque ' . $l['cheque_no'] . ' is not approved yet.');
            }
            if ($leaf && $leaf['status'] !== 'unused') {
                throw new RuntimeException('Cheque leaf ' . $l['cheque_no'] . ' is already ' . $leaf['status'] . '.');
            }
            if ($leaf) {
                sales_exec($db, "UPDATE acc_cheque_leaves SET status='used', voucher_id=? WHERE leaf_id=?", 'ii', [(int) $voucherId, (int) $leaf['leaf_id']])->close();
            }
        }
        sales_insert(
            $db,
            'INSERT INTO acc_cheques (direction, voucher_id, line_id, bank_account_id, party_account_id, party_sub_ledger_id, party_name, cheque_no, cheque_date, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'siiiiisssd',
            [$direction, (int) $voucherId, (int) $l['line_id'], (int) $bank['account_id'], (int) $l['account_id'], $l['sub_ledger_id'] !== null ? (int) $l['sub_ledger_id'] : null, $l['name'], $l['cheque_no'], $l['cheque_date'], (float) ($l['debit'] + $l['credit'])]
        );
    }
}
