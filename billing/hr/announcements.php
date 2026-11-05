<?php
/** Staff Announcements: send a notice to everyone in the portal, to a department, or to one person. */
require_once __DIR__ . '/hr_common.php';
require_once dirname(__DIR__, 2) . '/addon/inc/notify.php';

$self = 'announcements.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hr_ready($conn)) {
    sales_require_csrf($self);
    try {
        $title = sales_post_string('title', 140);
        $body = sales_post_string('body', 500);
        if ($title === '') {
            throw new RuntimeException('Enter the title.');
        }
        $to = (string) ($_POST['to'] ?? 'all');
        if ($to === 'all') {
            notify_staff(null, $title, $body, '', hr_user_id());
            sales_flash_redirect($self, 'success', 'Sent to all staff.');
        }
        $where = $to === 'department' ? 'department_id=?' : 'employee_id=?';
        $id = (int) ($_POST[$to === 'department' ? 'department_id' : 'employee_id'] ?? 0);
        $people = sales_rows($conn, "SELECT sr_id FROM hr_employees WHERE $where AND sr_id IS NOT NULL AND status='active'", 'i', [$id]);
        if (!$people) {
            throw new RuntimeException('Nobody there has a portal login.');
        }
        foreach ($people as $p) {
            notify_staff((int) $p['sr_id'], $title, $body, '', hr_user_id());
        }
        sales_flash_redirect($self, 'success', 'Sent to ' . count($people) . ' person(s).');
    } catch (RuntimeException $e) {
        sales_flash_redirect($self, 'error', $e->getMessage());
    }
}

hr_page_start($conn, 'Staff Announcements', 'announce');
$departments = sales_rows($conn, 'SELECT department_id, department_name FROM hr_departments WHERE is_active=1 ORDER BY department_name');
$people = sales_rows($conn, "SELECT employee_id, full_name FROM hr_employees WHERE status='active' AND sr_id IS NOT NULL ORDER BY full_name");
$sent = sales_tables_ready($conn, ['app_notifications']) ? sales_list_rows($conn, "SELECT title, MAX(body) AS body, COUNT(*) AS people, MAX(sr_id IS NULL) AS everyone, MAX(created_at) AS sent_at FROM app_notifications WHERE audience='staff' AND sent_by IS NOT NULL GROUP BY title, created_at ORDER BY MAX(notification_id) DESC") : [];
?>
<form method="post" autocomplete="off"><?= sales_csrf_field() ?><div class="grid g2" style="margin-top:0">
<div class="field"><label for="title">Title</label><input id="title" name="title" maxlength="140" required></div>
<div class="field"><label for="body">Message</label><input id="body" name="body" maxlength="500"></div>
<div class="field"><label for="to">Send To</label><select id="to" name="to"><option value="all">All staff</option><option value="department">One department</option><option value="person">One person</option></select></div>
<div class="field"><label for="department_id">Department</label><select id="department_id" name="department_id"><?= hr_options($departments, 'department_id', 'department_name', 0, 'Select') ?></select></div>
<div class="field"><label for="employee_id">Person</label><select id="employee_id" name="employee_id"><?= hr_options($people, 'employee_id', 'full_name', 0, 'Select') ?></select></div>
</div><div class="toolbar" style="margin-top:10px"><button class="btn primary" name="action" value="add">Send</button></div></form>
<section class="section"><div class="section-title">Sent</div><div class="table-scroll"><table><thead><tr><th>Title</th><th>Message</th><th>Sent To</th><th>When</th></tr></thead><tbody>
<?php foreach ($sent as $s): ?><tr><td><?= sales_h($s['title']) ?></td><td><?= sales_h($s['body']) ?></td><td><?= (int) $s['everyone'] ? 'All staff' : (int) $s['people'] . ' person(s)' ?></td><td class="nowrap"><?= date('d/m/Y h:i A', strtotime($s['sent_at'])) ?></td></tr><?php endforeach; ?>
<?= $sent ? '' : '<tr><td colspan="4" class="empty">Nothing sent yet.</td></tr>' ?>
</tbody></table></div></section>
<?php mgmt_page_end(); ?>
<?php include "../../addon/conf/connection_close.php"; ?>
