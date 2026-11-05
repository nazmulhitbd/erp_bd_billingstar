<?php
/** Requests: ask for a quotation, product support or about a payment or account, and read and answer staff replies. */
require_once __DIR__ . '/portal_common.php';
$a = cp_require_login();
$cid = (int) $a['customer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cp_check_csrf('requests.php');
    $body = cp_post('body', 4000);
    if (($_POST['action'] ?? '') === 'reply') {
        $r = cp_row('SELECT * FROM portal_requests WHERE request_id=? AND customer_id=?', 'ii', [(int) ($_POST['request_id'] ?? 0), $cid]);
        if (!$r || $body === '') {
            cp_redirect('requests.php', 'err', 'Write your message first.');
        }
        cp_insert("INSERT INTO portal_request_messages (request_id, sender, body) VALUES (?, 'customer', ?)", 'is', [(int) $r['request_id'], $body]);
        cp_exec("UPDATE portal_requests SET status='open', last_reply_by='customer' WHERE request_id=?", 'i', [(int) $r['request_id']])->close();
        notify_admin('customer/portal_requests.php', 'Customer replied: ' . $r['request_no'], mb_substr($body, 0, 160));
        cp_redirect('requests.php?id=' . (int) $r['request_id'], 'ok', 'Message sent.');
    }
    $type = array_key_exists($_POST['request_type'] ?? '', CP_REQUEST_TYPES) ? $_POST['request_type'] : 'other';
    $subject = cp_post('subject', 190);
    if ($subject === '' || $body === '') {
        cp_redirect('requests.php?new=' . urlencode($type), 'err', 'Write a subject and your message.');
    }
    $today = (int) cp_row('SELECT COUNT(*) AS n FROM portal_requests WHERE customer_id=? AND created_at > NOW() - INTERVAL 1 DAY', 'i', [$cid])['n'];
    if ($today >= 20) {
        cp_redirect('requests.php', 'err', 'You have sent many requests today. Please wait for our reply.');
    }
    $no = cp_request_no();
    $id = cp_insert('INSERT INTO portal_requests (request_no, customer_id, account_id, request_type, subject) VALUES (?, ?, ?, ?, ?)', 'siiss', [$no, $cid, (int) $a['account_id'], $type, $subject]);
    cp_insert("INSERT INTO portal_request_messages (request_id, sender, body) VALUES (?, 'customer', ?)", 'is', [$id, $body]);
    notify_admin('customer/portal_requests.php', 'New customer request: ' . $no, mb_substr($body, 0, 160));
    cp_redirect('requests.php?id=' . $id, 'ok', 'Request ' . $no . ' sent. We will reply here.');
}

if (isset($_GET['id'])) {
    $r = cp_row('SELECT * FROM portal_requests WHERE request_id=? AND customer_id=?', 'ii', [(int) $_GET['id'], $cid]);
    if (!$r) {
        cp_redirect('requests.php', 'err', 'Request not found.');
    }
    $msgs = cp_rows('SELECT * FROM portal_request_messages WHERE request_id=? ORDER BY message_id', 'i', [(int) $r['request_id']]);
    cp_page_start($r['request_no'] . ': ' . $r['subject'], 'requests');
    echo '<p class="muted">' . cp_h(CP_REQUEST_TYPES[$r['request_type']] ?? 'Request') . ' · ' . cp_h(ucfirst($r['status'])) . '</p>';
    foreach ($msgs as $m) {
        echo '<div class="msg' . ($m['sender'] === 'staff' ? ' staff' : '') . '"><small>' . ($m['sender'] === 'staff' ? cp_h($m['staff_name'] ?: 'Staff') : 'You') . ' · ' . cp_h(date('d/m/Y h:i A', strtotime($m['created_at']))) . '</small><div class="pre">' . cp_h($m['body']) . '</div></div>';
    }
    if ($r['status'] !== 'closed') {
        echo '<form method="post" class="card grid">' . cp_csrf_field() . '<input type="hidden" name="action" value="reply"><input type="hidden" name="request_id" value="' . (int) $r['request_id'] . '">'
            . '<div><label for="body">Your message</label><textarea id="body" name="body" required maxlength="4000"></textarea></div><div><button class="btn">Send</button></div></form>';
    } else {
        echo '<p class="muted">This request is closed. Send a new request if you need more help.</p>';
    }
    echo '<p><a href="requests.php">All requests</a></p>';
    cp_page_end();
    exit;
}

$rows = cp_rows('SELECT * FROM portal_requests WHERE customer_id=? ORDER BY updated_at DESC, request_id DESC LIMIT 200', 'i', [$cid]);
$new = array_key_exists($_GET['new'] ?? '', CP_REQUEST_TYPES) ? $_GET['new'] : '';
$ref = substr(trim((string) ($_GET['ref'] ?? '')), 0, 60);
cp_page_start('Requests', 'requests');
echo '<form method="post" class="card grid">' . cp_csrf_field() . '<h2>New request</h2><div class="row"><div><label for="request_type">Type</label><select id="request_type" name="request_type">';
foreach (CP_REQUEST_TYPES as $k => $l) {
    echo '<option value="' . $k . '"' . ($k === $new ? ' selected' : '') . '>' . cp_h($l) . '</option>';
}
echo '</select></div><div><label for="subject">Subject</label><input id="subject" name="subject" required maxlength="190" value="' . cp_h($ref !== '' ? 'About ' . $ref : '') . '"></div></div>'
    . '<div><label for="body">Message</label><textarea id="body" name="body" required maxlength="4000" placeholder="' . cp_h($new === 'quotation' ? 'Which products and how many?' : 'How can we help?') . '"></textarea></div><div><button class="btn">Send request</button></div></form>';
echo '<h2>Your requests</h2><div class="scroll"><table><thead><tr><th>No</th><th>Type</th><th>Subject</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>';
echo $rows ? '' : '<tr><td colspan="6" class="empty">No requests yet.</td></tr>';
foreach ($rows as $r) {
    $badge = $r['status'] === 'answered' ? 'ok' : ($r['status'] === 'open' ? 'warn' : '');
    echo '<tr><td>' . cp_h($r['request_no']) . '</td><td>' . cp_h(CP_REQUEST_TYPES[$r['request_type']] ?? 'Password') . '</td><td>' . cp_h($r['subject']) . '</td><td><span class="badge ' . $badge . '">' . cp_h($r['status'] === 'answered' ? 'Replied' : ucfirst($r['status'])) . '</span></td>'
        . '<td>' . cp_h(date('d/m/Y', strtotime($r['updated_at']))) . '</td><td><a class="btn small light" href="requests.php?id=' . (int) $r['request_id'] . '">Open</a></td></tr>';
}
echo '</tbody></table></div>';
cp_page_end();
