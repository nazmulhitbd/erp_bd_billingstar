<?php
/**
 * Shared layout for the Store Management lists (Store Logins, Customers by Store, Sales Persons by Store): the
 * reseller-list look (bootstrap panels, WUIrsheader table), top filter buttons, search bar, records-per-page and pager.
 */
require_once dirname(__DIR__) . '/trader/trader_common.php';

const SA_PAGE_SIZES = [25, 50, 100, 200, 500];
const SA_STORE_TYPES = ['head_office' => 'Head Office', 'warehouse' => 'Warehouse', 'retail_shop' => 'Retail Shop'];

/** Page size and page number from the query string. */
function sa_paging()
{
    $limit = (int) ($_GET['limit'] ?? 25);
    return [in_array($limit, SA_PAGE_SIZES, true) ? $limit : 25, max(1, (int) ($_GET['page'] ?? 1))];
}

/** URL builder keeping the current filters: $url(['page' => 2]). Empty values are dropped. */
function sa_url_builder($self, array $filters, $limit)
{
    return fn(array $extra = []) => $self . '?' . http_build_query(array_filter($extra + $filters + ['limit' => $limit], fn($v) => $v !== '' && $v !== 0 && $v !== null));
}

/** Stores for the store picker, Head Office first. */
function sa_stores(mysqli $db)
{
    return sales_rows($db, "SELECT store_id, store_name, store_type, branch_id FROM store_profiles ORDER BY store_type='head_office' DESC, store_name");
}

/** The tabs of Users, Roles & Permissions on its pages, showing only the ones this user holds. '' on any other page. */
function access_tabs()
{
    $script = 'user/' . basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach (menu_catalog() as $cat) {
        foreach ($cat['groups'] ?? [] as $g) {
            if (empty($g['merge']) || !in_array($script, array_column($g['items'], 1), true)) {
                continue;
            }
            $html = '';
            foreach ($g['items'] as [$label, $url]) {
                if (perm_can($url, 'view')) {
                    $on = $url === $script;
                    $html .= '<a href="' . sales_h(basename($url)) . '" style="padding:7px 14px;border:1px solid ' . ($on ? '#1d4ed8' : '#cfd8e3') . ';border-radius:8px;background:' . ($on ? '#1d4ed8' : '#fff') . ';color:' . ($on ? '#fff' : '#1e293b') . ';font:600 13px system-ui,sans-serif;text-decoration:none">' . sales_h($label) . '</a>';
                }
            }
            return '<nav aria-label="Users, Roles and Permissions" style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px">' . $html . '</nav>';
        }
    }
    return '';
}

function sa_page_head($title, $flash)
{
    echo '<!DOCTYPE html><html><head><title>' . sales_h($title) . '</title><meta charset="utf-8">'
        . '<link href="../../addon/App_Themes/DefaultTheme/default_color.css" type="text/css" rel="stylesheet" />'
        . '<link href="../../addon/App_Themes/DefaultTheme/default_font.css" type="text/css" rel="stylesheet" />'
        . '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css"/>'
        . '<style>body{padding:10px}.WUIfield{vertical-align:middle!important}.table>thead>tr>td{vertical-align:middle;text-align:center}.search-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center}'
        . '.search-bar .form-control{width:auto;display:inline-block}.rights-edit label{font-weight:normal;margin-right:14px;white-space:nowrap}.muted{color:#888}.nowrap{white-space:nowrap}'
        . '.flash{margin-bottom:10px}.bulk{margin-top:10px;display:flex;flex-wrap:wrap;gap:6px;align-items:center}.bulk .form-control{width:auto;display:inline-block}</style>'
        . '<script>function checkAll(box) { document.querySelectorAll(\'input[name="selected[]"]\').forEach(function (c) { c.checked = box.checked; }); }</script>'
        . '</head><body style="background: ' . sales_h($GLOBALS['body_bgcolor'] ?? '#fff') . '" class="Body">' . access_tabs();
    if ($flash) {
        echo '<div class="alert alert-' . ($flash['kind'] === 'success' ? 'success' : 'danger') . ' flash">' . sales_h($flash['message']) . '</div>';
    }
}

function sa_page_foot($note = '')
{
    echo ($note !== '' ? '<p class="muted" style="margin-top:12px">' . sales_h($note) . '</p>' : '') . '</body></html>';
}

/** Top button group: [value => label] for one filter key, plus optional extra buttons (raw HTML). */
function sa_tabs(callable $url, $key, $current, array $tabs, $extraHtml = '')
{
    echo '<div class="panel panel-default"><div class="panel-body"><div class="btn-group" role="group">' . $extraHtml;
    foreach ($tabs as $value => $label) {
        echo '<a class="btn ' . ((string) $current === (string) $value ? 'btn-primary' : 'btn-default') . '" href="' . sales_h($url([$key => $value, 'page' => 1])) . '">' . sales_h($label) . '</a>';
    }
    echo '</div></div></div>';
}

/** Records-per-page select and Start / Prev / Page x of y / Next / End. */
function sa_pager(callable $url, $page, $pages, $total, $limit)
{
    echo '<table class="Navigator" cellspacing="3" cellpadding="0" width="100%" border="0"><tr><td width="140"><strong>Records:</strong> <select onchange="location.href=this.value" aria-label="Records per page">';
    foreach (SA_PAGE_SIZES as $n) {
        echo '<option value="' . sales_h($url(['limit' => $n, 'page' => 1])) . '"' . ($limit === $n ? ' selected' : '') . '>' . $n . '</option>';
    }
    echo '</select></td><td width="90">' . ($page > 1 ? '<a href="' . sales_h($url(['page' => 1])) . '">&#171;&#171; Start</a>' : '') . '</td>'
        . '<td width="110">' . ($page > 1 ? '<a href="' . sales_h($url(['page' => $page - 1])) . '">&#171; Prev Page</a>' : '') . '</td>'
        . '<td align="center"><strong>Page ' . $page . ' of ' . $pages . ' ( Total ' . $total . ' Records )</strong></td>'
        . '<td width="110" align="right">' . ($page < $pages ? '<a href="' . sales_h($url(['page' => $page + 1])) . '">Next Page &#187;</a>' : '') . '</td>'
        . '<td width="90" align="right">' . ($page < $pages ? '<a href="' . sales_h($url(['page' => $pages])) . '">End &#187;&#187;</a>' : '') . '</td></tr></table><br>';
}

/** Header row of a list table. */
function sa_table_head(array $columns)
{
    echo '<div class="box-body"><table class="table table-bordered table-hover"><thead><tr class="WUIrsheader"><td class="WUIrsheaderel" width="10"><input type="checkbox" onclick="checkAll(this)" aria-label="Select all"></td>';
    foreach ($columns as $c) {
        echo '<td class="WUIrsheaderel">' . sales_h($c) . '</td>';
    }
    echo '</tr></thead><tbody>';
}

/** Store picker options (value = store_id; 0 = all, -1 = Head Office / not in a store). */
function sa_store_options(array $stores, $selected, $allLabel = '- choose store -', $withUnassigned = true)
{
    $out = '<option value="0">' . sales_h($allLabel) . '</option>' . ($withUnassigned ? '<option value="-1"' . ((int) $selected === -1 ? ' selected' : '') . '>Not in a store (shared / Head Office)</option>' : '');
    foreach ($stores as $s) {
        $out .= '<option value="' . (int) $s['store_id'] . '"' . ((int) $selected === (int) $s['store_id'] ? ' selected' : '') . '>' . sales_h($s['store_name'] . ' (' . SA_STORE_TYPES[$s['store_type']] . ')') . '</option>';
    }
    return $out;
}

/** SQL condition on a branch_id column for a store filter value (0 = all, -1 = no store). */
function sa_store_filter(array $stores, $storeId, $column)
{
    if ((int) $storeId === -1) {
        return ["$column IS NULL", '', []];
    }
    foreach ($stores as $s) {
        if ((int) $s['store_id'] === (int) $storeId) {
            return ["$column = ?", 'i', [(int) $s['branch_id']]];
        }
    }
    return ['1=1', '', []];
}
