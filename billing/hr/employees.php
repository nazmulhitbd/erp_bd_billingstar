<?php
/**
 * Employees: register a person, keep their profile, walk them through onboarding and give them an SR / Staff Portal login.
 * Everyone is one row in hr_employees. A person who does not sell gets a sales_representatives row with staff_only=1, because
 * that is what a portal login hangs on; it keeps them out of the Sales Person lists. Existing SRs can be brought in with Import.
 */
require_once __DIR__ . '/hr_common.php';

$self = 'employees.php';
const HR_TASKS = ['documents' => 'Documents collected', 'contract' => 'Appointment letter signed', 'id_card' => 'ID card issued'];
const HR_TEXT = ['full_name' => 140, 'father_name' => 140, 'mother_name' => 140, 'spouse_name' => 140, 'blood_group' => 5, 'religion' => 40, 'nationality' => 60, 'nid' => 40, 'passport_no' => 40, 'driving_license' => 40, 'tin' => 40,
    'mobile' => 40, 'email' => 190, 'present_address' => 255, 'permanent_address' => 255, 'emergency_name' => 140, 'emergency_mobile' => 40, 'skills' => 255, 'languages' => 190, 'bank_name' => 120, 'bank_account' => 60];
$portalReady = sales_tables_ready($conn, ['sr_portal_accounts']);

/** A designation id for a name, added if it is new. */
function hr_designation_id(mysqli $db, $name)
{
    $name = sales_cut(trim((string) $name), 120);
    if ($name === '') {
        return null;
    }
    $row = sales_row($db, 'SELECT designation_id FROM hr_designations WHERE designation_name=?', 's', [$name]);
    return $row ? (int) $row['designation_id'] : sales_insert($db, 'INSERT INTO hr_designations (designation_name) VALUES (?)', 's', [$name]);
}

if (isset($_GET['download']) && hr_ready($conn)) {
    hr_send_document((int) $_GET['download'], (int) ($_GET['id'] ?? 0));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hr_ready($conn)) {
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $tab = (string) ($_POST['tab'] ?? '');
    $back = $self . ($id ? '?id=' . $id . (isset(HR_RECORDS[$tab]) || $tab === 'documents' ? '&tab=' . $tab : '') : ($action === 'save' ? '?new=1' : ''));
    sales_require_csrf($back);
    try {
        if ($action === 'create_import') {
            $made = 0;
            foreach (sales_rows($conn, 'SELECT s.* FROM sales_representatives s LEFT JOIN hr_employees e ON e.sr_id=s.sr_id WHERE e.employee_id IS NULL AND s.staff_only=0 AND s.is_active=1') as $s) {
                sales_exec($conn, "INSERT INTO hr_employees (employee_code, full_name, mobile, email, present_address, designation_id, join_date, status, sr_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)", 'sssssisii',
                    [hr_next_code($conn), $s['sr_name'], $s['mobile'], $s['email'] ?? '', sales_cut((string) ($s['address'] ?? ''), 255), hr_designation_id($conn, $s['designation']), $s['joining_date'] ?? date('Y-m-d'), (int) $s['sr_id'], hr_user_id()])->close();
                $made++;
            }
            sales_flash_redirect($self, 'success', $made ? $made . ' sales person(s) added to HR. Fill in the rest of their profiles.' : 'Every active sales person is already in HR.');
        }
        $old = $id ? sales_row($conn, 'SELECT * FROM hr_employees WHERE employee_id=?', 'i', [$id]) : null;
        if ($id && !$old) {
            throw new RuntimeException('Employee not found.');
        }
        if ($action === 'edit_record' && $old && isset(HR_RECORDS[$tab])) {
            hr_save_record($tab, $id, (int) ($_POST['record_id'] ?? 0), hr_record_values($tab, $_POST));
            sales_flash_redirect($back, 'success', 'Saved.');
        }
        if ($action === 'delete_record' && $old && isset(HR_RECORDS[$tab])) {
            sales_exec($conn, 'DELETE FROM ' . HR_RECORDS[$tab][1] . ' WHERE record_id=? AND employee_id=?', 'ii', [(int) ($_POST['record_id'] ?? 0), $id])->close();
            sales_flash_redirect($back, 'success', 'Deleted.');
        }
        if ($action === 'edit_document' && $old) {
            hr_save_document($id, (string) ($_POST['document_type'] ?? ''), (string) ($_POST['title'] ?? ''), $_FILES['document'] ?? [], hr_user_id());
            sales_flash_redirect($back, 'success', ($_POST['document_type'] ?? '') === 'Photo' ? 'Photo saved.' : 'Document uploaded.');
        }
        if ($action === 'delete_document' && $old) {
            hr_delete_document((int) ($_POST['document_id'] ?? 0), $id);
            sales_flash_redirect($back, 'success', 'Document deleted.');
        }
        if ($action === 'edit_task') {
            $key = (string) ($_POST['task'] ?? '');
            if (!isset(HR_TASKS[$key])) {
                throw new RuntimeException('Unknown task.');
            }
            isset($_POST['done']) ? sales_exec($conn, 'INSERT IGNORE INTO hr_onboarding_tasks (employee_id, task_key, done_by) VALUES (?, ?, ?)', 'isi', [$id, $key, hr_user_id()])->close()
                : sales_exec($conn, 'DELETE FROM hr_onboarding_tasks WHERE employee_id=? AND task_key=?', 'is', [$id, $key])->close();
            sales_flash_redirect($back, 'success', 'Checklist saved.');
        }
        if ($action === 'edit_activate') {
            sales_exec($conn, "UPDATE hr_employees SET status='active' WHERE employee_id=? AND status='onboarding'", 'i', [$id])->close();
            sales_flash_redirect($back, 'success', $old['full_name'] . ' is now active.');
        }
        if ($action === 'edit_portal') {
            if (!$portalReady) {
                throw new RuntimeException('The SR portal is not installed.');
            }
            $srId = (int) $old['sr_id'];
            $conn->begin_transaction();
            if (!$srId) {
                $name = $old['full_name'];
                if (sales_row($conn, 'SELECT 1 AS x FROM sales_representatives WHERE sr_name=?', 's', [$name])) {
                    $name = sales_cut($old['full_name'], 120) . ' (' . $old['employee_code'] . ')'; // sales are matched by name, so names stay unique
                }
                $code = sales_row($conn, 'SELECT 1 AS x FROM sales_representatives WHERE sr_code=?', 's', [$old['employee_code']]) ? null : $old['employee_code'];
                $srId = sales_insert($conn, 'INSERT INTO sales_representatives (sr_code, sr_name, mobile, staff_only) VALUES (?, ?, ?, 1)', 'sss', [$code, $name, $old['mobile']]);
                sales_exec($conn, 'UPDATE hr_employees SET sr_id=? WHERE employee_id=?', 'ii', [$srId, $id])->close();
            }
            if (sales_row($conn, 'SELECT 1 AS x FROM sr_portal_accounts WHERE sr_id=?', 'i', [$srId])) {
                throw new RuntimeException($old['full_name'] . ' already has a portal login. Reset it on SR Portal Accounts.');
            }
            $login = sales_post_string('login', 60);
            $login = $login !== '' ? $login : $old['employee_code'];
            if (!preg_match('/^[A-Za-z0-9._@+-]{3,60}$/', $login)) {
                throw new RuntimeException('Enter a login of 3-60 letters, digits or . _ @ + -.');
            }
            if (sales_row($conn, 'SELECT 1 AS x FROM sr_portal_accounts WHERE login=?', 's', [$login])) {
                throw new RuntimeException('Login "' . $login . '" is already used.');
            }
            $sells = !sales_row($conn, 'SELECT 1 AS x FROM sales_representatives WHERE sr_id=? AND staff_only=1', 'i', [$srId]);
            $temp = portal_temp_password();
            sales_exec($conn, 'INSERT INTO sr_portal_accounts (sr_id, login, password_hash, must_change_password, can_book_orders, can_collect, created_by) VALUES (?, ?, ?, 1, ?, ?, ?)', 'issiii', [$srId, $login, password_hash($temp, PASSWORD_DEFAULT), $sells ? 1 : 0, $sells ? 1 : 0, hr_user_id()])->close();
            $conn->commit();
            sales_flash_redirect($back, 'success', 'Portal login for ' . $old['full_name'] . ': login ' . $login . ', temporary password ' . $temp . ' (shown once; it must be changed at first sign-in).');
        }

        $values = [];
        foreach (HR_TEXT as $name => $max) {
            $values[$name] = sales_post_string($name, $max);
        }
        if ($values['full_name'] === '') {
            throw new RuntimeException('Enter the name.');
        }
        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address or leave it blank.');
        }
        $dates = [];
        foreach (['date_of_birth', 'join_date', 'confirm_date', 'exit_date'] as $name) {
            $dates[$name] = sales_valid_date($_POST[$name] ?? '', $name !== 'join_date');
            if ($dates[$name] === null) {
                throw new RuntimeException($name === 'join_date' ? 'Enter the joining date.' : 'Enter valid dates or leave them blank.');
            }
        }
        $status = isset(HR_STATUS[$_POST['status'] ?? '']) ? $_POST['status'] : 'onboarding';
        $left = in_array($status, ['resigned', 'terminated'], true);
        if ($left && $dates['exit_date'] === '') {
            throw new RuntimeException('Enter the last working day of a person who has left.');
        }
        if ($dates['exit_date'] !== '' && $dates['exit_date'] < $dates['join_date']) {
            throw new RuntimeException('The last working day cannot be before the joining date.');
        }
        $manager = (int) ($_POST['reports_to'] ?? 0);
        if ($manager && $manager === $id) {
            throw new RuntimeException('A person cannot report to themselves.');
        }
        $code = sales_post_string('employee_code', 20);
        $code = $code !== '' ? $code : ($old['employee_code'] ?? hr_next_code($conn));
        if (sales_row($conn, 'SELECT 1 AS x FROM hr_employees WHERE employee_code=? AND employee_id<>?', 'si', [$code, $id])) {
            throw new RuntimeException('Employee code "' . $code . '" is already used.');
        }
        $srId = (int) ($_POST['sr_id'] ?? 0);
        if ($srId && sales_row($conn, 'SELECT 1 AS x FROM hr_employees WHERE sr_id=? AND employee_id<>?', 'ii', [$srId, $id])) {
            throw new RuntimeException('That sales person is already linked to someone else.');
        }
        $null = fn($v) => $v ?: null;
        $cols = $values + ['employee_code' => $code, 'date_of_birth' => $null($dates['date_of_birth']), 'gender' => in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : '',
            'department_id' => $null((int) ($_POST['department_id'] ?? 0)), 'designation_id' => $null((int) ($_POST['designation_id'] ?? 0)), 'branch_id' => $null((int) ($_POST['branch_id'] ?? 0)), 'reports_to' => $null($manager),
            'employment_type' => isset(HR_TYPES[$_POST['employment_type'] ?? '']) ? $_POST['employment_type'] : 'permanent', 'join_date' => $dates['join_date'], 'confirm_date' => $null($dates['confirm_date']),
            'exit_date' => $null($dates['exit_date']), 'status' => $status, 'sr_id' => $null($srId), 'marital_status' => in_array($_POST['marital_status'] ?? '', ['single', 'married', 'other'], true) ? $_POST['marital_status'] : ''];
        $types = implode('', array_map(fn($v) => is_int($v) ? 'i' : 's', $cols));
        if ($id) {
            sales_exec($conn, 'UPDATE hr_employees SET ' . implode(', ', array_map(fn($c) => "$c=?", array_keys($cols))) . ' WHERE employee_id=?', $types . 'i', array_merge(array_values($cols), [$id]))->close();
        } else {
            $cols['created_by'] = hr_user_id();
            $id = sales_insert($conn, 'INSERT INTO hr_employees (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', $types . 'i', array_values($cols));
        }
        $portalSr = $srId ?: (int) ($old['sr_id'] ?? 0);
        if ($left && $portalSr && $portalReady) {
            sales_exec($conn, "UPDATE sr_portal_accounts SET status='disabled' WHERE sr_id=?", 'i', [$portalSr])->close(); // no portal after leaving
        }
        sales_flash_redirect($self . '?id=' . $id, 'success', 'Employee saved.');
    } catch (RuntimeException $e) {
        $conn->rollback(); // undoes the portal login steps; harmless when nothing is open
        sales_flash_redirect($back, 'error', $e->getMessage());
    }
}

hr_page_start($conn, 'Employees', 'employees');
$departments = sales_rows($conn, 'SELECT department_id, department_name FROM hr_departments WHERE is_active=1 ORDER BY department_name');
$designations = sales_rows($conn, 'SELECT designation_id, designation_name FROM hr_designations WHERE is_active=1 ORDER BY designation_name');

if (isset($_GET['id']) || isset($_GET['new'])) {
    $id = (int) ($_GET['id'] ?? 0);
    $e = $id ? sales_row($conn, 'SELECT * FROM hr_employees WHERE employee_id=?', 'i', [$id]) : null;
    if ($id && !$e) {
        echo '<div class="alert error">Employee not found.</div>';
        mgmt_page_end();
        exit;
    }
    if ($e) {
        $tab = (string) ($_GET['tab'] ?? '');
        $tabs = ['' => 'Profile'] + array_map(fn($r) => $r[0], HR_RECORDS) + ['documents' => 'Documents'];
        $photo = hr_photo_id($id);
        echo '<div style="display:flex;gap:12px;align-items:center;margin-bottom:10px">' . ($photo ? '<img src="' . $self . '?id=' . $id . '&download=' . $photo . '" alt="Photo of ' . sales_h($e['full_name']) . '" style="width:84px;height:84px;object-fit:cover;border-radius:8px;border:1px solid #ccc">' : '')
            . '<div><h3 style="margin:0 0 6px">' . sales_h($e['full_name']) . ' (' . sales_h($e['employee_code']) . ')</h3><form method="post" enctype="multipart/form-data" class="inline">' . sales_csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="tab" value="' . sales_h($tab) . '"><input type="hidden" name="document_type" value="Photo"><input type="file" name="document" accept=".jpg,.jpeg,.png" required aria-label="Photo"><button class="btn small" name="action" value="edit_document">' . ($photo ? 'Change Photo' : 'Add Photo') . '</button></form></div></div><div class="toolbar" style="margin-bottom:10px">';
        foreach ($tabs as $key => $label) {
            echo '<a class="btn' . ($key === $tab ? ' primary' : '') . '" href="' . $self . '?id=' . $id . ($key !== '' ? '&tab=' . $key : '') . '">' . sales_h($label) . '</a>';
        }
        echo '</div>';
        $hidden = sales_csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="tab" value="' . sales_h($tab) . '">';
        if (isset(HR_RECORDS[$tab])) {
            [, , , $fields] = HR_RECORDS[$tab];
            $rows = hr_records($tab, $id);
            $edit = null;
            foreach ($rows as $r) {
                if ((int) $r['record_id'] === (int) ($_GET['edit'] ?? 0)) {
                    $edit = $r;
                }
            }
            echo '<form method="post" autocomplete="off">' . $hidden . '<input type="hidden" name="record_id" value="' . (int) ($edit['record_id'] ?? 0) . '"><div class="grid g4" style="margin-top:0">';
            foreach ($fields as $col => [$label, $type, $size, $required]) {
                $value = $edit[$col] ?? '';
                $req = $required ? ' required' : '';
                if ($type === 'select') {
                    echo '<div class="field"><label for="' . $col . '">' . $label . '</label><select id="' . $col . '" name="' . $col . '"' . $req . '><option value="">Select</option>';
                    foreach ($size as $option) {
                        echo '<option' . ($value === $option ? ' selected' : '') . '>' . sales_h($option) . '</option>';
                    }
                    echo '</select></div>';
                } elseif ($type === 'check') {
                    echo '<div class="field"><label><input class="check" type="checkbox" name="' . $col . '"' . ((int) $value ? ' checked' : '') . '> ' . $label . '</label></div>';
                } else {
                    $html = ['date' => 'type="date"', 'email' => 'type="email"', 'year' => 'type="number" min="1950" max="' . (date('Y') + 1) . '"', 'amount' => 'type="number" min="0" step="0.01"', 'percent' => 'type="number" min="0" max="100" step="0.01"'][$type] ?? 'type="text"';
                    echo '<div class="field"><label for="' . $col . '">' . $label . '</label><input id="' . $col . '" name="' . $col . '" ' . $html . ($size ? ' maxlength="' . $size . '"' : '') . $req . ' value="' . sales_h($value) . '"></div>';
                }
            }
            echo '</div><div class="toolbar" style="margin:10px 0"><button class="btn primary" name="action" value="edit_record">' . ($edit ? 'Save' : 'Add') . '</button>' . ($edit ? '<a class="btn" href="' . $self . '?id=' . $id . '&tab=' . $tab . '">Close</a>' : '') . '</div></form>';
            if ($tab === 'experience' && $rows) {
                echo '<div class="summary"><div>Total Experience<b>' . sales_h(hr_experience_total($rows, $e['join_date'])) . '</b></div></div>';
            }
            $shown = array_filter($fields, fn($f) => $f[4]);
            echo '<div class="table-scroll"><table><thead><tr><th>SL</th>';
            foreach ($shown as $f) {
                echo '<th>' . $f[0] . '</th>';
            }
            echo '<th></th></tr></thead><tbody>';
            foreach ($rows as $i => $r) {
                echo '<tr><td>' . ($i + 1) . '</td>';
                foreach ($shown as $col => $f) {
                    echo '<td>' . hr_record_cell($f, $r[$col]) . '</td>';
                }
                echo '<td class="nowrap"><a class="btn small" href="' . $self . '?id=' . $id . '&tab=' . $tab . '&edit=' . (int) $r['record_id'] . '">Edit</a> <form method="post" style="display:inline" onsubmit="return confirm(\'Delete this record?\')">' . $hidden . '<input type="hidden" name="record_id" value="' . (int) $r['record_id'] . '"><button class="btn small danger" name="action" value="delete_record">Delete</button></form></td></tr>';
            }
            echo ($rows ? '' : '<tr><td colspan="' . (count($shown) + 2) . '" class="empty">Nothing added yet.</td></tr>') . '</tbody></table></div>';
            mgmt_page_end();
            exit;
        }
        if ($tab === 'documents') {
            $docs = sales_rows($conn, 'SELECT * FROM hr_documents WHERE employee_id=? ORDER BY document_type, document_id', 'i', [$id]);
            echo '<form method="post" enctype="multipart/form-data" class="grid g4" style="margin-top:0;align-items:end">' . $hidden . '<div class="field"><label for="document_type">Type</label><select id="document_type" name="document_type" required><option value="">Select</option>';
            foreach (HR_DOCUMENT_TYPES as $type) {
                echo '<option>' . sales_h($type) . '</option>';
            }
            echo '</select></div><div class="field"><label for="title">Title</label><input id="title" name="title" maxlength="190"></div><div class="field"><label for="document">File</label><input id="document" type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div><div class="toolbar"><button class="btn primary" name="action" value="edit_document">Upload</button></div></form>'
                . '<p class="footnote">PDF, JPG, PNG, DOC or DOCX, up to 5 MB.</p><div class="table-scroll"><table><thead><tr><th>SL</th><th>Type</th><th>Title</th><th>File</th><th class="num">Size</th><th>Uploaded</th><th></th></tr></thead><tbody>';
            foreach ($docs as $i => $d) {
                echo '<tr><td>' . ($i + 1) . '</td><td>' . sales_h($d['document_type']) . '</td><td>' . sales_h($d['title']) . '</td><td><a target="_blank" href="' . $self . '?id=' . $id . '&download=' . (int) $d['document_id'] . '">' . sales_h($d['original_name']) . '</a></td><td class="num">' . number_format($d['size_bytes'] / 1024) . ' KB</td><td>' . date('d/m/Y', strtotime($d['created_at'])) . '</td>'
                    . '<td><form method="post" onsubmit="return confirm(\'Delete this document?\')">' . $hidden . '<input type="hidden" name="document_id" value="' . (int) $d['document_id'] . '"><button class="btn small danger" name="action" value="delete_document">Delete</button></form></td></tr>';
            }
            echo ($docs ? '' : '<tr><td colspan="7" class="empty">No documents yet.</td></tr>') . '</tbody></table></div>';
            mgmt_page_end();
            exit;
        }
    }
    $v = fn($k, $d = '') => sales_h($e[$k] ?? $d);
    $text = fn($name, $label, $type = 'text', $req = false) => '<div class="field"><label for="' . $name . '">' . $label . '</label><input id="' . $name . '" type="' . $type . '" name="' . $name . '"' . (isset(HR_TEXT[$name]) ? ' maxlength="' . HR_TEXT[$name] . '"' : '') . ($req ? ' required' : '') . ' value="' . $v($name) . '"></div>';
    $select = fn($name, $label, $html) => '<div class="field"><label for="' . $name . '">' . $label . '</label><select id="' . $name . '" name="' . $name . '">' . $html . '</select></div>';
    $pick = function (array $map, $current) {
        $html = '';
        foreach ($map as $k => $label) {
            $html .= '<option value="' . sales_h($k) . '"' . ((string) $current === (string) $k ? ' selected' : '') . '>' . sales_h($label) . '</option>';
        }
        return $html;
    };
    $people = sales_rows($conn, "SELECT employee_id, full_name FROM hr_employees WHERE status IN ('active','onboarding') AND employee_id<>? ORDER BY full_name", 'i', [$id]);
    $linkable = sales_rows($conn, 'SELECT s.sr_id, s.sr_name FROM sales_representatives s LEFT JOIN hr_employees x ON x.sr_id=s.sr_id WHERE (x.employee_id IS NULL OR x.employee_id=?) ORDER BY s.sr_name', 'i', [$id]);
    $branches = sales_tables_ready($conn, ['branches']) ? sales_rows($conn, 'SELECT branch_id, branch_name FROM branches WHERE is_active=1 ORDER BY branch_name') : [];
    echo '<form method="post" autocomplete="off">' . sales_csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><section class="section" style="border-top:0;margin-top:0;padding-top:0"><div class="section-title">Job</div><div class="grid g4" style="margin-top:0">'
        . $text('employee_code', 'Employee Code') . $text('full_name', 'Full Name', 'text', true) . $select('department_id', 'Department', hr_options($departments, 'department_id', 'department_name', $e['department_id'] ?? 0, 'Select'))
        . $select('designation_id', 'Designation', hr_options($designations, 'designation_id', 'designation_name', $e['designation_id'] ?? 0, 'Select')) . $select('branch_id', 'Branch', hr_options($branches, 'branch_id', 'branch_name', $e['branch_id'] ?? 0, 'Select'))
        . $select('reports_to', 'Reports To', hr_options($people, 'employee_id', 'full_name', $e['reports_to'] ?? 0, 'Nobody')) . $select('employment_type', 'Type', $pick(HR_TYPES, $e['employment_type'] ?? 'permanent')) . $select('status', 'Status', $pick(HR_STATUS, $e['status'] ?? 'onboarding'))
        . $text('join_date', 'Joining Date', 'date', true) . $text('confirm_date', 'Confirmation Date', 'date') . $text('exit_date', 'Last Working Day', 'date') . $select('sr_id', 'Linked Sales Person', hr_options($linkable, 'sr_id', 'sr_name', $e['sr_id'] ?? 0, 'None'))
        . '</div></section><section class="section"><div class="section-title">Personal</div><div class="grid g4" style="margin-top:0">' . $text('mobile', 'Mobile') . $text('email', 'Email', 'email') . $text('date_of_birth', 'Date of Birth', 'date') . $select('gender', 'Gender', $pick(['' => 'Select', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'], $e['gender'] ?? ''))
        . $text('father_name', "Father's Name") . $text('mother_name', "Mother's Name") . $select('marital_status', 'Marital Status', $pick(['' => 'Select', 'single' => 'Single', 'married' => 'Married', 'other' => 'Other'], $e['marital_status'] ?? '')) . $text('spouse_name', 'Spouse Name')
        . $text('religion', 'Religion') . $text('nationality', 'Nationality') . $text('blood_group', 'Blood Group') . $text('nid', 'NID') . $text('passport_no', 'Passport No') . $text('driving_license', 'Driving License') . $text('tin', 'TIN')
        . $text('present_address', 'Present Address') . $text('permanent_address', 'Permanent Address') . $text('emergency_name', 'Emergency Contact') . $text('emergency_mobile', 'Emergency Mobile') . $text('skills', 'Skills') . $text('languages', 'Languages')
        . '</div></section><section class="section"><div class="section-title">Bank</div><div class="grid g4" style="margin-top:0">' . $text('bank_name', 'Bank Name') . $text('bank_account', 'Account No') . '</div></section>'
        . '<div class="toolbar" style="margin-top:10px"><button class="btn primary" name="action" value="save">Save</button><a class="btn" href="' . $self . '">Close</a></div></form>';
    if ($e) {
        $done = array_column(sales_rows($conn, 'SELECT task_key FROM hr_onboarding_tasks WHERE employee_id=?', 'i', [$id]), 'task_key');
        $hasSalary = (bool) sales_row($conn, 'SELECT 1 AS x FROM hr_salaries WHERE employee_id=? LIMIT 1', 'i', [$id]);
        $hasLeave = (bool) sales_row($conn, 'SELECT 1 AS x FROM hr_leave_balances WHERE employee_id=? LIMIT 1', 'i', [$id]);
        $account = $portalReady && $e['sr_id'] ? sales_row($conn, 'SELECT login, status FROM sr_portal_accounts WHERE sr_id=?', 'i', [(int) $e['sr_id']]) : null;
        $hasEducation = (bool) sales_row($conn, 'SELECT 1 AS x FROM hr_education WHERE employee_id=? LIMIT 1', 'i', [$id]);
        $hasReference = (bool) sales_row($conn, 'SELECT 1 AS x FROM hr_references WHERE employee_id=? LIMIT 1', 'i', [$id]);
        $tick = fn($on) => $on ? '<span class="badge converted">Done</span>' : '<span class="badge open">To do</span>';
        echo '<section class="section"><div class="section-title">Onboarding</div><div class="table-scroll"><table><tbody>';
        foreach (HR_TASKS as $key => $label) {
            $on = in_array($key, $done, true);
            echo '<tr><td>' . sales_h($label) . '</td><td>' . $tick($on) . '</td><td><form method="post">' . sales_csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="task" value="' . $key . '">' . ($on ? '' : '<input type="hidden" name="done" value="1">') . '<button class="btn small" name="action" value="edit_task">' . ($on ? 'Undo' : 'Mark done') . '</button></form></td></tr>';
        }
        echo '<tr><td>Education added</td><td>' . $tick($hasEducation) . '</td><td><a class="btn small" href="' . $self . '?id=' . $id . '&tab=education">Education</a></td></tr><tr><td>References added</td><td>' . $tick($hasReference) . '</td><td><a class="btn small" href="' . $self . '?id=' . $id . '&tab=references">References</a></td></tr>';
        echo '<tr><td>Salary set</td><td>' . $tick($hasSalary) . '</td><td><a class="btn small" href="salary.php?emp=' . $id . '">Salary Structure</a></td></tr><tr><td>Leave assigned</td><td>' . $tick($hasLeave) . '</td><td><a class="btn small" href="leave_assign.php">Leave Assign</a></td></tr>'
            . '<tr><td>Portal login' . ($account ? ': ' . sales_h($account['login']) . ' (' . sales_h($account['status']) . ')' : '') . '</td><td>' . $tick((bool) $account) . '</td><td>';
        if (!$account && $portalReady) {
            echo '<form method="post" class="inline">' . sales_csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input name="login" maxlength="60" placeholder="Login (blank = code)" style="max-width:190px" aria-label="Login"><button class="btn small" name="action" value="edit_portal">Create Login</button></form>';
        }
        echo '</td></tr></tbody></table></div>';
        if ($e['status'] === 'onboarding') {
            echo '<form method="post" style="margin-top:8px">' . sales_csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><button class="btn primary" name="action" value="edit_activate">Finish Onboarding</button></form>';
        }
        echo '</section>';
    }
    mgmt_page_end();
    exit;
}

$q = sales_cut(trim((string) ($_GET['q'] ?? '')), 100);
$status = isset(HR_STATUS[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$department = (int) ($_GET['department'] ?? 0);
$where = ['1=1'];
$types = '';
$params = [];
if ($q !== '') {
    $where[] = '(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.mobile LIKE ?)';
    $types .= 'sss';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($status !== '') {
    $where[] = 'e.status=?';
    $types .= 's';
    $params[] = $status;
}
if ($department) {
    $where[] = 'e.department_id=?';
    $types .= 'i';
    $params[] = $department;
}
$rows = sales_list_rows($conn, "SELECT e.*, (SELECT MAX(p.document_id) FROM hr_documents p WHERE p.employee_id=e.employee_id AND p.document_type='Photo') AS photo_id, COALESCE(d.department_name, '') AS department_name, COALESCE(g.designation_name, '') AS designation_name FROM hr_employees e
    LEFT JOIN hr_departments d ON d.department_id=e.department_id LEFT JOIN hr_designations g ON g.designation_id=e.designation_id WHERE " . implode(' AND ', $where) . ' ORDER BY e.full_name', $types, $params);
$statusOptions = '<option value="">All status</option>';
foreach (HR_STATUS as $k => $label) {
    $statusOptions .= '<option value="' . $k . '"' . ($status === $k ? ' selected' : '') . '>' . $label . '</option>';
}
?>
<form method="get" class="list-search"><input class="input" name="q" value="<?= sales_h($q) ?>" placeholder="Name, code or mobile" aria-label="Search"><select class="input" name="status" aria-label="Status"><?= $statusOptions ?></select><select class="input" name="department" aria-label="Department"><?= hr_options($departments, 'department_id', 'department_name', $department, 'All departments') ?></select><button class="btn">Search</button><a class="btn primary" href="<?= $self ?>?new=1">New Employee</a></form>
<form method="post" style="margin-bottom:8px"><?= sales_csrf_field() ?><button class="btn small" name="action" value="create_import">Import Sales Persons</button></form>
<div class="table-scroll"><table><thead><tr><th>SL</th><th>Code</th><th>Name</th><th>Designation</th><th>Department</th><th>Mobile</th><th>Joined</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $i => $r): ?><tr><td><?= $i + 1 ?></td><td><?= sales_h($r['employee_code']) ?></td><td class="nowrap"><?php if ($r['photo_id']): ?><img src="<?= $self ?>?id=<?= (int) $r['employee_id'] ?>&download=<?= (int) $r['photo_id'] ?>" alt="" style="width:28px;height:28px;object-fit:cover;border-radius:50%;vertical-align:middle;margin-right:6px"><?php endif; ?><?= sales_h($r['full_name']) ?></td><td><?= sales_h($r['designation_name']) ?></td><td><?= sales_h($r['department_name']) ?></td><td><?= sales_h($r['mobile']) ?></td><td><?= date('d/m/Y', strtotime($r['join_date'])) ?></td><td><?= sales_h(HR_STATUS[$r['status']]) ?></td><td><a class="btn small" href="<?= $self ?>?id=<?= (int) $r['employee_id'] ?>">Open</a></td></tr><?php endforeach; ?>
<?= $rows ? '' : '<tr><td colspan="9" class="empty">No employees found. Add one, or import your sales persons.</td></tr>' ?>
</tbody></table></div>
<?php mgmt_page_end(); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
