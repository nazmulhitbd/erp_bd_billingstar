<?php
PHP_SAPI === 'cli' || exit; // a developer tool: never from the web
// Rows of bs_sample that a fresh install (bs_fresh) does not have, or has with other values, per table, keyed by primary key.
// Used by export_sample.php; run directly it prints a summary.
function dd_db()
{
    static $db = null;
    return $db ??= new mysqli('127.0.0.1', 'bsapp', 'SampleBuild2026x', 'bs_sample', 3399);
}

function dd_pk($table)
{
    $cols = array_column(dd_db()->query("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema='bs_sample' AND table_name='$table' AND constraint_name='PRIMARY' ORDER BY ORDINAL_POSITION")->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
    return $cols;
}

/** [table => ['new' => rows, 'changed' => rows, 'removed' => n]] */
function dd_diff()
{
    $out = [];
    foreach (array_column(dd_db()->query("SELECT table_name AS t FROM information_schema.tables WHERE table_schema='bs_sample' AND table_type='BASE TABLE' ORDER BY table_name")->fetch_all(MYSQLI_ASSOC), 't') as $t) {
        $pk = dd_pk($t);
        $key = fn(array $r) => $pk ? implode("\x1f", array_map(fn($c) => (string) $r[$c], $pk)) : md5(serialize($r));
        $fresh = [];
        foreach (dd_db()->query("SELECT * FROM bs_fresh.`$t`")->fetch_all(MYSQLI_ASSOC) as $r) {
            $fresh[$key($r)] = $r;
        }
        $new = $changed = [];
        $seen = 0;
        foreach (dd_db()->query("SELECT * FROM bs_sample.`$t`")->fetch_all(MYSQLI_ASSOC) as $r) {
            $k = $key($r);
            if (!isset($fresh[$k])) {
                $new[] = $r;
                continue;
            }
            $seen++;
            $a = $r;
            $b = $fresh[$k];
            foreach (['created_at', 'updated_at'] as $c) { // set by the clock when each database was built
                unset($a[$c], $b[$c]);
            }
            if ($a !== $b) {
                $changed[] = $r;
            }
        }
        if ($new || $changed || $seen < count($fresh)) {
            $out[$t] = ['new' => $new, 'changed' => $changed, 'removed' => count($fresh) - $seen, 'pk' => $pk];
        }
    }
    return $out;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    foreach (dd_diff() as $t => $d) {
        printf("%-34s new %-4d changed %-3d removed %d%s\n", $t, count($d['new']), count($d['changed']), $d['removed'], $d['pk'] ? '' : '  (no primary key)');
    }
}
