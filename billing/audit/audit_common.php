<?php
/** Shared code of the Audit menu. The records are written by addon/inc/audit.php; these pages read them. */
require_once dirname(__DIR__) . '/sales/sales_common.php';
const AUDIT_PAGE_SIZE = 50;
const AUDIT_EXPORT_MAX = 20000;
const AUDIT_TABS = ['all' => ['All Activity', ['login', 'change', 'activity', 'security']], 'login' => ['Login History', ['login']], 'change' => ['Data Changes', ['change']], 'security' => ['Security Events', ['security']]];

function audit_may($tab, $action = 'view')
{
    return perm_is_super() || perm_can("audit/audit_log.php?tab=$tab", $action);
}

/** Categories this user may read, whatever address they typed. */
function audit_my_categories()
{
    $mine = [];
    foreach (AUDIT_TABS as $tab => [, $categories]) {
        if (audit_may($tab)) {
            $mine = array_merge($mine, $categories);
        }
    }
    return array_values(array_unique($mine));
}

function audit_page_start($title, $active = '')
{
    $links = [];
    if (perm_is_super() || perm_can('audit/audit_dashboard.php', 'view')) {
        $links['dashboard'] = ['audit_dashboard.php', 'Dashboard'];
    }
    foreach (AUDIT_TABS as $tab => [$label]) {
        if (audit_may($tab)) {
            $links[$tab] = ["audit_log.php?tab=$tab", $label];
        }
    }
    if (perm_is_super() || perm_can('audit/audit_settings.php', 'view')) {
        $links['settings'] = ['audit_settings.php', 'Settings'];
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . sales_h($title) . '</title><link rel="stylesheet" href="../sales/sales.css"><link rel="stylesheet" href="audit.css"></head><body><div class="wrap"><nav class="sales-nav">';
    foreach ($links as $key => [$href, $label]) {
        echo '<a href="' . sales_h($href) . '"' . ($key === $active ? ' class="active"' : '') . '>' . sales_h($label) . '</a>';
    }
    echo '</nav><main class="panel">';
}

function audit_page_end()
{
    echo '</main></div></body></html>';
}

function audit_country_name($code)
{
    if ($code === '') {
        return '-';
    }
    if ($code === 'LAN') {
        return 'Local network';
    }
    return class_exists('Locale') ? (string) Locale::getDisplayRegion('-' . $code, 'en') : $code;
}

function audit_badge($severity)
{
    return '<span class="ab ab-' . sales_h(in_array($severity, ['info', 'warn', 'bad'], true) ? $severity : 'info') . '">' . sales_h(['info' => 'Info', 'warn' => 'Warning', 'bad' => 'Attack'][$severity] ?? $severity) . '</span>';
}

function audit_who(array $r)
{
    $name = $r['actor_name'] !== '' ? $r['actor_name'] : 'Unknown';
    return sales_h($name) . '<br><small>' . sales_h(AUDIT_ACTORS[$r['actor_type']] ?? $r['actor_type']) . '</small>';
}

/** [SQL after WHERE, types, params] for what is on screen. Every value is a parameter and categories come from AUDIT_TABS. */
function audit_filter(array $categories, array $in)
{
    $where = ['category IN (' . implode(',', array_fill(0, count($categories), '?')) . ')'];
    $types = str_repeat('s', count($categories));
    $params = $categories;
    $add = function ($sql, $type, ...$values) use (&$where, &$types, &$params) {
        $where[] = $sql;
        $types .= $type;
        array_push($params, ...$values);
    };
    $from = sales_valid_date($in['from'] ?? '', true);
    $to = sales_valid_date($in['to'] ?? '', true);
    if ($from) {
        $add('created_at >= ?', 's', "$from 00:00:00");
    }
    if ($to) {
        $add('created_at <= ?', 's', "$to 23:59:59");
    }
    if (isset(AUDIT_ACTORS[$in['actor'] ?? ''])) {
        $add('actor_type = ?', 's', $in['actor']);
    }
    if (in_array($in['severity'] ?? '', ['info', 'warn', 'bad'], true)) {
        $add('severity = ?', 's', $in['severity']);
    }
    foreach (['ip' => 'ip', 'country' => 'country', 'table' => 'target_table'] as $key => $column) {
        if (trim((string) ($in[$key] ?? '')) !== '') {
            $add("$column = ?", 's', trim((string) $in[$key]));
        }
    }
    $q = trim((string) ($in['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $add('(actor_name LIKE ? OR summary LIKE ? OR action LIKE ? OR target_id = ?)', 'ssss', $like, $like, $like, $q);
    }
    return [implode(' AND ', $where), $types, $params];
}
