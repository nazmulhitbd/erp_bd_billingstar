<?php
function sales_h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sales_cut($value, $length)
{
    $value = (string) $value;
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $length, 'UTF-8')
        : substr($value, 0, $length);
}

/* ---------------- Lists: one page of records at a time ---------------- */

const LIST_PAGE_SIZES = [25, 50, 100, 250, 500];

/**
 * Turn a list query into a count: [sql, placeholders dropped from the start, from the end]. The select list goes (it may
 * repeat column names, which a derived table refuses) and so does ORDER BY. Null when that cannot be done safely.
 */
function list_count_sql($sql)
{
    $from = null;
    $order = null;
    $depth = 0;
    $quote = '';
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $c = $sql[$i];
        if ($quote !== '') {
            if ($c === '\\') {
                $i++;
            } elseif ($c === $quote) {
                $quote = '';
            }
        } elseif ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
        } elseif ($c === '(') {
            $depth++;
        } elseif ($c === ')') {
            $depth--;
        } elseif ($depth === 0 && ($i === 0 || !preg_match('/\w/', $sql[$i - 1]))) {
            $rest = substr($sql, $i, 9);
            if (stripos($rest, 'UNION') === 0) {
                return null;
            }
            if ($from === null && preg_match('/^FROM\b/i', $rest)) {
                $from = $i;
            }
            if ($from !== null && preg_match('/^ORDER\s+BY/i', $rest)) {
                $order = $i;
            }
        }
    }
    if ($from === null || preg_match('/^\s*SELECT\s+DISTINCT\b/i', $sql)) {
        return null;
    }
    $end = $order ?? $length;
    return ['SELECT COUNT(*) AS n FROM (SELECT 1 AS x ' . substr($sql, $from, $end - $from) . ') list_count',
        substr_count(substr($sql, 0, $from), '?'), substr_count(substr($sql, $end), '?')];
}

/**
 * One page of a list, from ?pg= and ?pg_size=. $rows runs a query: fn($sql, $types, $params). The page gets
 * window.LIST_PAGER, and addon/portal/list-pager.js draws the pager under the table. If the count fails the page still
 * loads and offers Prev and Next, because one row more than the page is always fetched.
 */
function list_page_rows(callable $rows, $sql, $types = '', array $params = [])
{
    $size = (int) ($_GET['pg_size'] ?? 25);
    $size = in_array($size, LIST_PAGE_SIZES, true) ? $size : 25;
    $page = max(1, (int) ($_GET['pg'] ?? 1));
    $total = null;
    if ($count = list_count_sql($sql)) {
        [$countSql, $skip, $drop] = $count;
        $keep = count($params) - $skip - $drop;
        try {
            $n = $rows($countSql, substr($types, $skip, $keep), array_slice($params, $skip, $keep))[0]['n'] ?? null;
            $total = $n === null ? null : (int) $n;
        } catch (Throwable $e) {
            error_log('list count failed: ' . $e->getMessage());
        }
    }
    if ($total !== null) {
        $page = min($page, max(1, (int) ceil($total / $size)));
    }
    $found = $rows($sql . ' LIMIT ' . ($size + 1) . ' OFFSET ' . (($page - 1) * $size), $types, $params);
    $more = count($found) > $size;
    if (!isset($GLOBALS['list_pager'])) {
        ob_start(fn($html) => str_contains($html, '</body>') ? str_replace('</body>', '<script>window.LIST_PAGER=' . json_encode($GLOBALS['list_pager']) . ';</script></body>', $html) : $html);
    }
    $GLOBALS['list_pager'] = ['total' => $total, 'page' => $page, 'size' => $size, 'sizes' => LIST_PAGE_SIZES, 'more' => $more];
    return array_slice($found, 0, $size);
}

