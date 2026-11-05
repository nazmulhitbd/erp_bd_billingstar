<?php
/**
 * Notifications for the office, the SR / Staff Portal and the Customer Portal. Sending never stops the work that triggered it: perm_q swallows
 * errors, so nothing breaks before billing/user/schema.sql is imported. Inside a transaction a notification rolls back with the work.
 * A reader is ['type' => 'user'|'sr'|'customer', 'id' => admin user id, sr_id or customer_id]. Office notifications show to whoever may view their form.
 */
require_once __DIR__ . '/perm.php';

const NOTIFY_LIST_SIZE = 200;

/** False until billing/user/schema.sql is imported. Sending fails quietly until then, so the bell says so. */
function notify_installed()
{
    return (bool) perm_q("SELECT 1 AS x FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_notifications' AND column_name='customer_id'");
}

function notify_save($audience, $formKey, $srId, $title, $body, $link, $by = null, $userId = null, $customerId = null)
{
    perm_q('INSERT INTO app_notifications (audience, form_key, sr_id, user_id, customer_id, title, body, link, sent_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', 'ssiiisssi',
        [$audience, $formKey, $srId, $userId, $customerId, mb_substr((string) $title, 0, 140), mb_substr((string) $body, 0, 500), mb_substr((string) $link, 0, 190), $by]);
}

/** To the office: everyone who may view the form. $formKey is its menu URL; the link opens it. */
function notify_admin($formKey, $title, $body = '', $query = '')
{
    notify_save('admin', $formKey, null, $title, $body, 'billing/' . $formKey . $query);
}

/** To one office user, such as the person who made a document. $page is under billing/. */
function notify_user($userId, $title, $body = '', $page = '')
{
    if ((int) $userId > 0) {
        notify_save('admin', '', null, $title, $body, $page !== '' ? 'billing/' . $page : '', null, (int) $userId);
    }
}

/** To one customer in their portal, or to all customers when $customerId is null. $page is a customer_portal page. */
function notify_customer($customerId, $title, $body = '', $page = '')
{
    notify_save('customer', '', null, $title, $body, $page !== '' ? 'customer_portal/' . $page : '', null, null, $customerId === null ? null : (int) $customerId);
}

/** A saved document waits for approval: tell whoever may approve that kind (approve.php?doc=). */
function notify_approval_needed($docKey, $title, $docNo, $party = '')
{
    notify_admin('approve/approve.php?doc=' . $docKey, 'Approval needed: ' . $title . ' ' . $docNo, (string) $party);
}

/** To one person in the portal, or to all staff when $srId is null. $page is a sr_portal page. */
function notify_staff($srId, $title, $body = '', $page = '', $by = null)
{
    notify_save('staff', '', $srId === null ? null : (int) $srId, $title, $body, $page !== '' ? 'sr_portal/' . $page : '', $by);
}

/** To an HR employee, if they have a portal login. */
function notify_employee($employeeId, $title, $body = '', $page = '')
{
    $srId = (int) (perm_q('SELECT sr_id FROM hr_employees WHERE employee_id=?', 'i', [(int) $employeeId])[0]['sr_id'] ?? 0);
    if ($srId) {
        notify_staff($srId, $title, $body, $page);
    }
}

/** The reader for a shell user. */
function notify_shell_reader(array $u)
{
    $types = ['customer' => 'customer', 'employee' => 'sr'];
    return isset($types[$u['user_type']]) ? ['type' => $types[$u['user_type']], 'id' => (int) $u['linked_id']] : ['type' => 'user', 'id' => (int) $u['user_id']];
}

/** Newest first, each with read_at. Office rows are limited to forms the signed-in user may view, or rows sent to them by name. */
function notify_list(array $reader)
{
    $base = 'SELECT n.*, r.read_at FROM app_notifications n LEFT JOIN app_notification_reads r ON r.notification_id=n.notification_id AND r.reader_type=? AND r.reader_id=? WHERE ';
    $tail = ' ORDER BY n.notification_id DESC LIMIT ' . NOTIFY_LIST_SIZE;
    if ($reader['type'] === 'sr') {
        return perm_q($base . "n.audience='staff' AND (n.sr_id=? OR n.sr_id IS NULL)" . $tail, 'sii', ['sr', $reader['id'], $reader['id']]);
    }
    if ($reader['type'] === 'customer') {
        return perm_q($base . "n.audience='customer' AND (n.customer_id=? OR n.customer_id IS NULL)" . $tail, 'sii', ['customer', $reader['id'], $reader['id']]);
    }
    $rows = perm_q($base . "n.audience='admin' AND (n.user_id=? OR n.user_id IS NULL)" . $tail, 'sii', ['user', $reader['id'], $reader['id']]);
    return array_values(array_filter($rows, fn($n) => $n['user_id'] !== null || $n['form_key'] === '' || perm_can($n['form_key'], 'view')));
}

function notify_unread(array $reader)
{
    return count(array_filter(notify_list($reader), fn($n) => $n['read_at'] === null));
}

/** Mark one notification read and return its link, or null if this reader may not see it. */
function notify_open(array $reader, $id)
{
    foreach (notify_list($reader) as $n) {
        if ((int) $n['notification_id'] === (int) $id) {
            perm_q('INSERT IGNORE INTO app_notification_reads (notification_id, reader_type, reader_id) VALUES (?, ?, ?)', 'isi', [(int) $id, $reader['type'], $reader['id']]);
            return (string) $n['link'];
        }
    }
    return null;
}

function notify_read_all(array $reader)
{
    foreach (notify_list($reader) as $n) {
        if ($n['read_at'] === null) {
            perm_q('INSERT IGNORE INTO app_notification_reads (notification_id, reader_type, reader_id) VALUES (?, ?, ?)', 'isi', [(int) $n['notification_id'], $reader['type'], $reader['id']]);
        }
    }
}

/** "5 minutes ago" for lists. */
function notify_age($when)
{
    $seconds = max(0, time() - strtotime((string) $when));
    if ($seconds < 60) {
        return 'just now';
    }
    foreach ([86400 => 'day', 3600 => 'hour', 60 => 'minute'] as $size => $name) {
        if ($seconds >= $size) {
            $n = intdiv($seconds, $size);
            return $n . ' ' . $name . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return '';
}
