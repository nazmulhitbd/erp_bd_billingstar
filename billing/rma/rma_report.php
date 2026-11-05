<?php
require_once __DIR__ . '/rma_common.php';

/**
 * Print a report page.
 * $cols: [key => [label, 'num'|'qty'|'' ]]; $groupBy: row key used for group headings + subtotals; $sum: keys to total.
 */
function rma_report_render($title, $subtitle, array $cols, array $rows, $groupBy = null, array $sum = [])
{
    $auto = !empty($_GET['auto']);
    $fmt = function ($kind, $v) {
        if ($kind === 'num') {
            return number_format((float) $v, 2);
        }
        if ($kind === 'qty') {
            return rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
        }
        return rma_h($v);
    };
    $sumRow = function ($label, array $totals) use ($cols, $fmt) {
        $html = '<tr class="subtotal">';
        $first = true;
        foreach ($cols as $key => $c) {
            if (array_key_exists($key, $totals)) {
                $html .= '<td class="num">' . $fmt($c[1] ?? '', $totals[$key]) . '</td>';
            } else {
                $html .= '<td>' . ($first ? rma_h($label) : '') . '</td>';
            }
            $first = false;
        }
        return $html . '</tr>';
    };
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . rma_h($title) . '</title><link rel="stylesheet" href="rma.css?v=1"></head><body class="rma-print">';
    echo '<button class="rma-btn noprint" onclick="window.print()">Print</button>';
    echo '<h1>' . rma_h($title) . '</h1><h2>' . rma_h($subtitle) . ' &middot; Printed ' . rma_h(date('d/m/Y h:i A')) . ' by ' . rma_h(rma_user_name()) . '</h2>';
    echo '<table class="rma-table"><thead><tr>';
    foreach ($cols as $c) {
        echo '<th class="' . (in_array($c[1] ?? '', ['num', 'qty'], true) ? 'num' : '') . '">' . rma_h($c[0]) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="' . count($cols) . '" class="empty">No data for the selected filters.</td></tr>';
    }
    $grand = array_fill_keys($sum, 0.0);
    $groupTotals = array_fill_keys($sum, 0.0);
    $currentGroup = null;
    foreach ($rows as $row) {
        if ($groupBy !== null && $row[$groupBy] !== $currentGroup) {
            if ($currentGroup !== null && $sum) {
                echo $sumRow('Sub Total', $groupTotals);
            }
            $currentGroup = $row[$groupBy];
            $groupTotals = array_fill_keys($sum, 0.0);
            echo '<tr class="group"><td colspan="' . count($cols) . '">' . rma_h($currentGroup !== '' ? $currentGroup : '(blank)') . '</td></tr>';
        }
        echo '<tr>';
        foreach ($cols as $key => $c) {
            $kind = $c[1] ?? '';
            echo '<td class="' . (in_array($kind, ['num', 'qty'], true) ? 'num' : '') . '">' . $fmt($kind, $row[$key] ?? '') . '</td>';
        }
        echo '</tr>';
        foreach ($sum as $k) {
            $grand[$k] += (float) ($row[$k] ?? 0);
            $groupTotals[$k] += (float) ($row[$k] ?? 0);
        }
    }
    if ($rows && $groupBy !== null && $sum) {
        echo $sumRow('Sub Total', $groupTotals);
    }
    if ($rows && $sum) {
        echo $sumRow('Grand Total', $grand);
    }
    echo '</tbody></table>';
    if ($auto) {
        echo '<script>window.addEventListener("load",function(){window.print()})</script>';
    }
    echo '</body></html>';
}

/** Date range from the register filter form. Returns [from, to, label]. */
function rma_report_dates()
{
    $all = !empty($_GET['all_date']);
    $from = $all ? null : (rma_date_or_null($_GET['from'] ?? '') ?: null);
    $to = $all ? null : (rma_date_or_null($_GET['to'] ?? '') ?: null);
    $label = $all || (!$from && !$to)
        ? 'All Date'
        : 'Date: ' . ($from ? date('d/m/Y', strtotime($from)) : '…') . ' to ' . ($to ? date('d/m/Y', strtotime($to)) : '…');
    return [$from ?: '1000-01-01', $to ?: '9999-12-31', $label];
}

/** Report picker table shared by the Stock and Service registers. */
function rma_report_picker(array $reports)
{
    $reports = perm_filter_reports('rma/' . basename((string) $_SERVER['SCRIPT_NAME']), $reports);
    echo '<div class="rma-grid-wrap" style="min-height:0"><table class="rma-table"><thead><tr><th>SL</th><th>Caption</th><th></th><th>Report SL</th><th>Preview</th><th>Print</th></tr></thead><tbody>';
    $sl = 0;
    foreach ($reports as $key => $r) {
        $sl++;
        echo '<tr><td>' . $sl . '</td><td>' . rma_h($r['caption']) . '</td>'
            . '<td class="center"><input type="checkbox" class="reg-pick" value="' . rma_h($key) . '"></td><td>' . $sl . '</td>'
            . '<td><button type="button" class="rma-btn" data-report="' . rma_h($key) . '">Print Preview</button></td>'
            . '<td><button type="button" class="rma-btn" data-report="' . rma_h($key) . '" data-print="1">Print</button></td></tr>';
    }
    echo '</tbody></table></div>';
}
