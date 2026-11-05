<?php
/**
 * Sending email and SMS, set up on Settings > Email & SMS. Settings live in acc_settings; the SMTP password and SMS key are
 * encrypted there, so a database dump does not carry them. The key comes from dbconfig.php, so a new database password
 * means entering them again. Each send returns an error for the person reading it, or '' when it was accepted.
 */
require_once __DIR__ . '/perm.php';

const MSG_SECRETS = ['smtp_pass', 'sms_key'];

function msg_get($key, $default = '')
{
    $row = perm_q('SELECT setting_value FROM acc_settings WHERE setting_key=?', 's', ['msg_' . $key])[0] ?? null;
    return $row ? (string) $row['setting_value'] : $default;
}

function msg_put($key, $value)
{
    perm_q('INSERT INTO acc_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', 'ss', ['msg_' . $key, (string) $value]);
}

function msg_secret_key()
{
    return hash('sha256', 'billing-star-messaging|' . (string) @file_get_contents(dirname(__DIR__) . '/display/dbconfig.php'), true);
}

function msg_put_secret($key, $value)
{
    $iv = random_bytes(12);
    $text = openssl_encrypt((string) $value, 'aes-256-gcm', msg_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    msg_put($key, base64_encode($iv . $tag . $text));
}

/** The secret as typed, or '' if none is saved or it was saved under another database password. */
function msg_get_secret($key)
{
    $raw = base64_decode(msg_get($key), true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    return (string) openssl_decrypt(substr($raw, 28), 'aes-256-gcm', msg_secret_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
}

/** Send a plain text email. $test sends even while email is switched off, so the settings can be proved first. */
function msg_mail($to, $subject, $body, $test = false)
{
    if (!$test && msg_get('mail_enabled') !== '1') {
        return 'Email is switched off in Settings > Email & SMS.';
    }
    $from = msg_get('mail_from');
    $host = msg_get('smtp_host');
    if ($host === '' || $from === '') {
        return 'Email is not set up yet.';
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $subject)) {
        return 'That email address is not valid.';
    }
    $name = trim(preg_replace('/[\r\n"]/', '', msg_get('mail_name', 'Billing Star')));
    $headers = ['From: "' . $name . '" <' . $from . '>', "To: <$to>", 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=', 'Date: ' . date('r'), 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=utf-8', 'Content-Transfer-Encoding: base64'];
    try {
        msg_smtp_send($host, $from, $to, implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body))));
        return '';
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
}

/** Read one SMTP reply and insist on the expected code. */
function msg_smtp_expect($socket, $code)
{
    $reply = '';
    do {
        $line = fgets($socket, 1024);
        if ($line === false) {
            throw new RuntimeException('The mail server stopped answering.');
        }
        $reply .= $line;
    } while (isset($line[3]) && $line[3] === '-');
    if ((int) substr($reply, 0, 3) !== $code) {
        throw new RuntimeException('The mail server said: ' . trim($reply));
    }
}

function msg_smtp_say($socket, $line, $code)
{
    fwrite($socket, $line . "\r\n");
    msg_smtp_expect($socket, $code);
}

function msg_smtp_send($host, $from, $to, $message)
{
    $secure = msg_get('smtp_secure', 'tls');
    $port = (int) msg_get('smtp_port', '587');
    $user = msg_get('smtp_user');
    $socket = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $error, 10);
    if (!$socket) {
        throw new RuntimeException("Could not reach $host on port $port. $error");
    }
    stream_set_timeout($socket, 15);
    msg_smtp_expect($socket, 220);
    $me = preg_replace('/[^A-Za-z0-9.-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
    msg_smtp_say($socket, "EHLO $me", 250);
    if ($secure === 'tls') {
        msg_smtp_say($socket, 'STARTTLS', 220);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new RuntimeException('The secure connection to the mail server failed.');
        }
        msg_smtp_say($socket, "EHLO $me", 250);
    }
    if ($user !== '') {
        msg_smtp_say($socket, 'AUTH LOGIN', 334);
        msg_smtp_say($socket, base64_encode($user), 334);
        msg_smtp_say($socket, base64_encode(msg_get_secret('smtp_pass')), 235);
    }
    msg_smtp_say($socket, "MAIL FROM:<$from>", 250);
    msg_smtp_say($socket, "RCPT TO:<$to>", 250);
    msg_smtp_say($socket, 'DATA', 354);
    msg_smtp_say($socket, $message . "\r\n.", 250);
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
}

/** Digits with an optional leading +, as a gateway expects. Returns '' if it cannot be a phone number. */
function msg_phone($number)
{
    $number = preg_replace('/[\s()-]/', '', (string) $number);
    return preg_match('/^\+?\d{8,15}$/', $number) ? $number : '';
}

/** Send an SMS through the gateway's address. The gateway's reply is the only proof it was accepted. */
function msg_sms($to, $text, $test = false)
{
    if (!$test && msg_get('sms_enabled') !== '1') {
        return 'SMS is switched off in Settings > Email & SMS.';
    }
    $url = msg_get('sms_url');
    $phone = msg_phone($to);
    if ($url === '') {
        return 'SMS is not set up yet.';
    }
    if ($phone === '') {
        return 'That mobile number is not valid.';
    }
    $key = msg_get_secret('sms_key');
    $fill = fn($template) => strtr($template, ['{to}' => urlencode($phone), '{message}' => urlencode($text), '{key}' => urlencode($key), '{sender}' => urlencode(msg_get('sms_sender'))]);
    $post = msg_get('sms_method') === 'POST';
    [$status, $reply, $error] = msg_http($fill($url), $post ? $fill(msg_get('sms_body')) : null);
    $hide = fn($message) => $key === '' ? $message : str_replace([$key, urlencode($key)], '***', $message);
    if ($error !== '') {
        return $hide('The SMS gateway could not be reached. ' . $error);
    }
    $expected = msg_get('sms_success');
    if ($status < 200 || $status >= 300 || ($expected !== '' && stripos($reply, $expected) === false)) {
        return $hide('The SMS gateway refused it (' . $status . '): ' . mb_substr(trim(strip_tags($reply)), 0, 160));
    }
    return '';
}

/** One web request. Returns [status, body, error]. Redirects are not followed, so a key in the address stays with the gateway. */
function msg_http($url, $body)
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
        if ($body !== null) {
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body]);
        }
        $reply = curl_exec($curl);
        return [(int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string) $reply, $reply === false ? curl_error($curl) : ''];
    }
    $options = ['method' => $body === null ? 'GET' : 'POST', 'timeout' => 20, 'ignore_errors' => true, 'follow_location' => 0];
    if ($body !== null) {
        $options += ['header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $body];
    }
    $reply = @file_get_contents($url, false, stream_context_create(['http' => $options]));
    if ($reply === false) {
        return [0, '', 'No answer.'];
    }
    return [preg_match('~^HTTP/\S+ (\d+)~', (string) ($http_response_header[0] ?? ''), $m) ? (int) $m[1] : 0, $reply, ''];
}
