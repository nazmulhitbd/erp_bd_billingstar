<?php
/**
 * Database Backup: download the database as SQL, compressed SQL or CSV, and the uploaded files as a ZIP. It uses the
 * application's own database connection, so no login is typed here and no password is shown. Every download is sent
 * straight to the browser and nothing is left on the server. A backup holds everything, password hashes included.
 */
require_once dirname(__DIR__) . '/trader/trader_common.php';

$self = 'dump_mysql.php';
const DB_UPLOADS = ['addon/uploads', 'billing/hr/uploads', 'billing/sales/uploads', 'billing/accounts_info/uploads', 'billing/purchase/uploads', 'billing/trader/uploads'];
const DB_ROWS_PER_INSERT = 200;

/** Tables and views as name => row from information_schema. */
function db_objects(mysqli $db)
{
    return array_column(sales_rows($db, 'SELECT table_name AS name, table_type AS kind, table_rows AS est_rows, COALESCE(data_length + index_length, 0) AS bytes FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name'), null, 'name');
}

/** Start a download: everything buffered so far is dropped, and the browser is told what is coming. */
function db_send_headers($type, $filename)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    session_write_close();
    set_time_limit(0);
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
}

/** Columns a dump can insert into: a calculated column cannot be given a value. A column with a default like now() can, so it stays. */
function db_columns(mysqli $db, $table)
{
    return array_column(sales_rows($db, "SELECT column_name AS c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND extra NOT REGEXP 'VIRTUAL GENERATED|STORED GENERATED' ORDER BY ordinal_position", 's', [$table]), 'c');
}

function db_quote_name($name)
{
    return '`' . str_replace('`', '``', $name) . '`';
}

/** Raw bytes rather than text. Dates and JSON report the binary character set too, but they are text. */
function db_is_binary($field)
{
    return $field->charsetnr === 63 && !in_array($field->type, [MYSQLI_TYPE_DATE, MYSQLI_TYPE_NEWDATE, MYSQLI_TYPE_DATETIME, MYSQLI_TYPE_TIMESTAMP, MYSQLI_TYPE_TIME, MYSQLI_TYPE_JSON, MYSQLI_TYPE_BIT], true);
}

/** A SQL literal for one value. Numbers stay bare and binary stays exact as hex. */
function db_sql_value(mysqli $db, $value, $field)
{
    if ($value === null) {
        return 'NULL';
    }
    if (in_array($field->type, [MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_INT24, MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_NEWDECIMAL, MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE, MYSQLI_TYPE_YEAR], true)) {
        return (string) $value;
    }
    if (db_is_binary($field)) {
        return $value === '' ? "''" : '0x' . bin2hex($value);
    }
    return "'" . $db->real_escape_string($value) . "'";
}

/** Write a SQL dump through $out, a function taking a string. Tables come first because views read from them. */
function db_write_sql(mysqli $db, array $objects, array $names, array $opt, callable $out)
{
    $database = (string) $db->query('SELECT DATABASE()')->fetch_row()[0];
    $db->query("SET time_zone='+00:00'");
    $db->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT'); // one moment in time, even while people keep working
    $out("-- Billing Star database backup\n-- Database: " . $database . "\n-- Made: " . date('Y-m-d H:i:s') . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET time_zone='+00:00';\n\n");
    $views = [];
    foreach ($names as $name) {
        if ($objects[$name]['kind'] === 'VIEW') {
            $views[] = $name;
            continue;
        }
        $q = db_quote_name($name);
        $out("-- Table $name\n");
        if ($opt['drop']) {
            $out("DROP TABLE IF EXISTS $q;\n");
        }
        if ($opt['structure']) {
            $out($db->query("SHOW CREATE TABLE $q")->fetch_row()[1] . ";\n\n");
        }
        if (!$opt['data']) {
            continue;
        }
        $columns = db_columns($db, $name);
        $list = implode(', ', array_map('db_quote_name', $columns));
        $bitColumns = array_column(sales_rows($db, "SELECT column_name AS c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND data_type='bit'", 's', [$name]), 'c');
        $select = implode(', ', array_map(fn($c) => $bitColumns && in_array($c, $bitColumns, true) ? db_quote_name($c) . '+0 AS ' . db_quote_name($c) : db_quote_name($c), $columns)); // a bit value is read as a number
        $result = $db->query("SELECT $select FROM $q", MYSQLI_USE_RESULT); // row by row, so a large table does not fill memory
        if (!$result) {
            $out("-- BACKUP FAILED at table $name: this file is not complete\n");
            return;
        }
        $fields = $result->fetch_fields();
        $batch = [];
        while ($row = $result->fetch_row()) {
            $cells = [];
            foreach ($row as $i => $value) {
                $cells[] = db_sql_value($db, $value, $fields[$i]);
            }
            $batch[] = '(' . implode(',', $cells) . ')';
            if (count($batch) >= DB_ROWS_PER_INSERT) {
                $out("INSERT INTO $q ($list) VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        $result->free();
        if ($batch) {
            $out("INSERT INTO $q ($list) VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        $out("\n");
    }
    $pending = [];
    foreach ($views as $name) {
        $q = db_quote_name($name);
        $sql = preg_replace('/ DEFINER=`[^`]*`@`[^`]*` SQL SECURITY \w+/', '', $db->query("SHOW CREATE VIEW $q")->fetch_row()[1]); // restores under any user
        $pending[$name] = str_replace(db_quote_name($database) . '.', '', $sql); // and into any database
    }
    while ($pending) { // a view built on another view has to come after it
        $ready = array_filter($pending, function ($sql, $name) use ($pending) {
            foreach ($pending as $other => $unused) {
                if ($other !== $name && strpos($sql, db_quote_name($other)) !== false) {
                    return false;
                }
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH) ?: $pending;
        foreach ($ready as $name => $sql) {
            $q = db_quote_name($name);
            $out("-- View $name\nDROP VIEW IF EXISTS $q;\n$sql;\n\n");
            unset($pending[$name]);
        }
    }
    $db->query('COMMIT');
    $out("SET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n-- Backup complete\n");
}

/** One CSV cell list for a row. Binary is shown as hex. For Excel, a text cell starting with = + - @ is made plain text, so it cannot run as a formula. */
function db_csv_cells(array $row, array $fields, $safe)
{
    foreach ($row as $i => $v) {
        $v = (string) ($v ?? '');
        if ($v !== '' && db_is_binary($fields[$i])) {
            $v = '0x' . bin2hex($v);
        } elseif ($safe && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false && !is_numeric($v)) {
            $v = "'" . $v;
        }
        $row[$i] = $v;
    }
    return $row;
}

/** Write one table or view as CSV to an open file. */
function db_write_csv(mysqli $db, $name, $handle, $safe)
{
    fwrite($handle, "\xEF\xBB\xBF"); // so Excel reads Bangla and other Unicode correctly
    $result = $db->query('SELECT * FROM ' . db_quote_name($name), MYSQLI_USE_RESULT);
    if (!$result) {
        fwrite($handle, 'This table could not be read');
        return;
    }
    $fields = $result->fetch_fields();
    fputcsv($handle, array_map(fn($f) => $f->name, $fields), ',', '"', '');
    while ($row = $result->fetch_row()) {
        fputcsv($handle, db_csv_cells($row, $fields, $safe), ',', '"', '');
    }
    $result->free();
}

/** Send a ZIP built in a temporary file, which is removed afterwards. */
function db_send_zip($temp, $filename)
{
    db_send_headers('application/zip', $filename);
    header('Content-Length: ' . filesize($temp));
    readfile($temp);
    @unlink($temp);
    exit;
}

$objects = db_objects($conn);
$stamp = date('Ymd_Hi');
$dbName = (string) $conn->query('SELECT DATABASE()')->fetch_row()[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sales_require_csrf($self);
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'edit_sql' || $action === 'edit_sqlgz') {
            $picked = array_values(array_intersect(array_map('strval', (array) ($_POST['tables'] ?? [])), array_map('strval', array_keys($objects))));
            if (!$picked) {
                throw new RuntimeException('Tick at least one table.');
            }
            $opt = ['drop' => isset($_POST['drop']), 'structure' => isset($_POST['structure']), 'data' => isset($_POST['data'])];
            if (!$opt['structure'] && !$opt['data']) {
                throw new RuntimeException('Tick Table Structure, Table Data, or both.');
            }
            $file = $dbName . '_' . $stamp . '.sql';
            if ($action === 'edit_sql') {
                db_send_headers('application/sql; charset=utf-8', $file);
                db_write_sql($conn, $objects, $picked, $opt, function ($text) {
                    echo $text;
                    flush();
                });
                exit;
            }
            if (!function_exists('deflate_init')) {
                throw new RuntimeException('Compression is not available on this server. Use SQL.');
            }
            db_send_headers('application/gzip', $file . '.gz');
            $zip = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
            db_write_sql($conn, $objects, $picked, $opt, function ($text) use ($zip) {
                echo deflate_add($zip, $text, ZLIB_NO_FLUSH);
            });
            echo deflate_add($zip, '', ZLIB_FINISH);
            exit;
        }
        $safe = isset($_POST['excel_safe']);
        if ($action === 'edit_csv') {
            $table = (string) ($_POST['table'] ?? '');
            if (!isset($objects[$table])) {
                throw new RuntimeException('Pick a table.');
            }
            db_send_headers('text/csv; charset=utf-8', preg_replace('/[^A-Za-z0-9_]/', '_', $table) . '_' . $stamp . '.csv');
            $handle = fopen('php://output', 'w');
            db_write_csv($conn, $table, $handle, $safe);
            fclose($handle);
            exit;
        }
        if ($action === 'edit_csvzip' || $action === 'edit_files') {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('ZIP is not available on this server.');
            }
            set_time_limit(0);
            $temp = tempnam(sys_get_temp_dir(), 'bsbackup');
            $zip = new ZipArchive();
            if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not make the ZIP file.');
            }
            $added = 0;
            $parts = [];
            if ($action === 'edit_csvzip') {
                foreach ($objects as $name => $o) {
                    if ($o['kind'] === 'VIEW') {
                        continue;
                    }
                    $part = tempnam(sys_get_temp_dir(), 'bscsv');
                    $handle = fopen($part, 'w');
                    db_write_csv($conn, $name, $handle, $safe);
                    fclose($handle);
                    $zip->addFile($part, $name . '.csv');
                    $parts[] = $part;
                    $added++;
                }
            } else {
                $root = dirname(__DIR__, 2);
                foreach (DB_UPLOADS as $dir) {
                    if (!is_dir("$root/$dir")) {
                        continue;
                    }
                    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS)) as $file) {
                        if ($file->isFile() && $file->getFilename()[0] !== '.') {
                            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($root) + 1));
                            $added++;
                        }
                    }
                }
            }
            if (!$added) {
                $zip->close();
                @unlink($temp);
                throw new RuntimeException('There is nothing to download yet.');
            }
            $zip->close();
            array_map('unlink', $parts);
            db_send_zip($temp, ($action === 'edit_files' ? 'uploaded_files_' : $dbName . '_csv_') . $stamp . '.zip');
        }
        throw new RuntimeException('Unknown action.');
    } catch (RuntimeException $e) {
        sales_flash_redirect($self, 'error', $e->getMessage());
    }
}

$tables = array_filter($objects, fn($o) => $o['kind'] !== 'VIEW');
$bytes = array_sum(array_column($tables, 'bytes'));
$files = 0;
$fileBytes = 0;
foreach (DB_UPLOADS as $dir) {
    $path = dirname(__DIR__, 2) . '/' . $dir;
    if (is_dir($path)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getFilename()[0] !== '.') {
                $files++;
                $fileBytes += $file->getSize();
            }
        }
    }
}
$mb = fn($n) => number_format($n / 1048576, 1) . ' MB';
$options = '';
foreach ($objects as $name => $o) {
    $options .= '<option value="' . sales_h($name) . '">' . sales_h($name) . ($o['kind'] === 'VIEW' ? ' (view)' : '') . '</option>';
}
$flash = sales_take_flash();
mgmt_page_start('Settings', ['backup' => [$self, 'Database Backup']], 'Database Backup', 'backup');
echo sales_flash_html($flash);
?>
<style>fieldset.box{min-width:0}.summary,.radios,.toolbar{flex-wrap:wrap}.section{min-width:0}pre{max-width:100%}</style>
<div class="summary"><div>Database<b><?= sales_h($dbName) ?></b></div><div>Tables<b><?= count($tables) ?></b></div><div>Views<b><?= count($objects) - count($tables) ?></b></div><div>Database Size<b><?= $mb($bytes) ?></b></div><div>Uploaded Files<b><?= $files ?> (<?= $mb($fileBytes) ?>)</b></div></div>
<p class="footnote">A backup holds everything, including customer data and password hashes. Keep downloads somewhere safe. Nothing is stored on this server.</p>
<form method="post"><?= sales_csrf_field() ?>
<section class="section"><div class="section-title">Full backup (SQL)</div>
<p class="footnote" style="margin-top:0">Use this to restore the system. Compressed is much smaller.</p>
<div class="radios"><label><input class="check" type="checkbox" name="drop" checked> Drop Table Statement</label><label><input class="check" type="checkbox" name="structure" checked> Table Structure</label><label><input class="check" type="checkbox" name="data" checked> Table Data</label></div>
<details style="margin:8px 0"><summary>Choose tables (all ticked)</summary>
<div class="toolbar" style="margin:8px 0"><button type="button" class="btn small" id="tick-all">Tick All</button><button type="button" class="btn small" id="tick-none">Tick None</button></div>
<div class="grid g4"><?php foreach ($objects as $name => $o): ?><label><input class="check tbl" type="checkbox" name="tables[]" value="<?= sales_h($name) ?>" checked> <?= sales_h($name) ?></label><?php endforeach; ?></div></details>
<div class="toolbar"><button class="btn primary" name="action" value="edit_sqlgz">Download Compressed SQL (.gz)</button><button class="btn" name="action" value="edit_sql">Download SQL</button></div></section>
<section class="section"><div class="section-title">CSV (for Excel)</div>
<div class="radios"><label><input class="check" type="checkbox" name="excel_safe" checked> Safe for Excel</label></div>
<div class="grid g2"><div class="field"><label for="table">Table</label><select id="table" name="table"><?= $options ?></select></div></div>
<div class="toolbar" style="margin-top:10px"><button class="btn" name="action" value="edit_csv">Download This Table</button><button class="btn" name="action" value="edit_csvzip">Download All Tables (ZIP)</button></div>
<p class="footnote">Safe for Excel stops a cell like =SUM(...) running as a formula, by putting ' in front of it. Untick it for an exact copy of the data.</p></section>
<section class="section"><div class="section-title">Uploaded files</div>
<p class="footnote" style="margin-top:0">Photos, employee documents, logos and attachments are not in the database, so back them up too.</p>
<div class="toolbar"><button class="btn" name="action" value="edit_files">Download Uploaded Files (ZIP)</button></div></section>
</form>
<section class="section"><div class="section-title">Restore a backup</div>
<p class="footnote" style="margin-top:0">Restoring replaces data, so it is done in Terminal, into a database you choose:</p>
<pre style="background:#0f172a;color:#e2e8f0;padding:10px 12px;border-radius:8px;overflow:auto;font-size:12.5px">mysql -u root -p DATABASE_NAME &lt; <?= sales_h($dbName) ?>_backup.sql
gunzip &lt; <?= sales_h($dbName) ?>_backup.sql.gz | mysql -u root -p DATABASE_NAME</pre></section>
<?php mgmt_page_end('const t=v=>document.querySelectorAll("input.tbl").forEach(b=>{b.checked=v});document.getElementById("tick-all").addEventListener("click",()=>t(true));document.getElementById("tick-none").addEventListener("click",()=>t(false));'); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
