<?php
/** My records: education, experience, references, training, family and documents as the office has them. To read only. */
require_once __DIR__ . '/sr_common.php';
require_once dirname(__DIR__) . '/addon/inc/hr.php';
$a = sr_require_login();
$e = sr_hr_employee($a);
if ($e && isset($_GET['doc'])) {
    hr_send_document((int) $_GET['doc'], $e['employee_id']); // only their own
}
$e = sr_hr_page_start($a, 'My Records', 'records');
$id = (int) $e['employee_id'];
foreach (HR_RECORDS as $kind => [$title, , , $fields]) {
    $rows = hr_records($kind, $id);
    $shown = array_filter($fields, fn($f) => $f[4]);
    echo '<h2>' . cp_h($title) . '</h2><div class="scroll"><table><tr>';
    foreach ($shown as $f) {
        echo '<th>' . cp_h($f[0]) . '</th>';
    }
    echo '</tr>';
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($shown as $col => $f) {
            echo '<td>' . hr_record_cell($f, $r[$col]) . '</td>';
        }
        echo '</tr>';
    }
    echo ($rows ? '' : '<tr><td colspan="' . count($shown) . '" class="empty">Nothing on file.</td></tr>') . '</table></div>';
}
$docs = cp_rows('SELECT document_id, document_type, title, original_name FROM hr_documents WHERE employee_id=? ORDER BY document_type, document_id', 'i', [$id]);
echo '<h2>Documents</h2><div class="scroll"><table><tr><th>Type</th><th>Title</th><th>File</th></tr>';
foreach ($docs as $d) {
    echo '<tr><td>' . cp_h($d['document_type']) . '</td><td>' . cp_h($d['title']) . '</td><td><a target="_blank" rel="noopener" href="hr_records.php?doc=' . (int) $d['document_id'] . '">' . cp_h($d['original_name']) . '</a></td></tr>';
}
echo ($docs ? '' : '<tr><td colspan="3" class="empty">Nothing on file.</td></tr>') . '</table></div><p class="muted">If anything here is wrong or missing, please tell the office.</p>';
sr_page_end();
