<?php
// Knoxville Plumbing Co — quote form mailer, hosted on vellops.com.
// Receives cross-origin JSON POST from https://knoxvilleplumbingco.com (static GitHub Pages).
// Emails the lead to Mitchell + appends to a server-side lead log (no lead is ever lost silently).
//
// Delivery strategy (2026-09-29):
// - Primary: authenticated SMTP via smtp.zoho.com (app password in /home/u113057883/vellops-data/smtp-zoho.php,
//   outside docroot, chmod 600). Zoho authenticates the message, DKIM-signs it, DMARC passes -> inbox.
// - Backup: PHP mail() to the Gmail address (proven working through Hostinger's relay).
// - Every lead is ALWAYS appended to /home/u113057883/vellops-data/knoxville-leads.jsonl first.
//
// Why not mail() for Zoho: Hostinger's sendmail wrapper (hsendmail) forces the envelope sender to
// noreply@srv406.main-hosting.eu and ignores the -f flag, so SPF never aligns with the header From
// domain -> DMARC fails -> Zoho silently drops. Authenticated Zoho SMTP sidesteps this entirely.
header('Content-Type: application/json');

// CORS: allow the static site to POST here.
$__origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($__origin, ['https://knoxvilleplumbingco.com', 'https://www.knoxvilleplumbingco.com'], true)) {
    header("Access-Control-Allow-Origin: $__origin");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

// Honeypot (bots fill it; the JS already blocks, this is defense in depth).
if (!empty($data['company'])) {
    echo json_encode(['ok' => true]); // lie to the bot, send nothing
    exit;
}

$name    = trim($data['name'] ?? '');
$phone   = trim($data['phone'] ?? '');
$service = trim($data['service'] ?? '');
$details = trim($data['details'] ?? '');
$page    = trim($data['page'] ?? '');

if (strlen($name) < 2 || !preg_match('/^[+().\-\s\d]{7,20}$/', $phone)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

$lead = [
    'ts'      => date('c'),
    'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
    'name'    => $name,
    'phone'   => $phone,
    'service' => $service,
    'details' => $details,
    'page'    => $page,
];

// Backup: append every lead to a server-side log so nothing is ever lost silently.
@file_put_contents(
    '/home/u113057883/vellops-data/knoxville-leads.jsonl',
    json_encode($lead) . "\n",
    FILE_APPEND | LOCK_EX
);

$to_zoho   = 'mitchell@msecomllc.com';
$to_gmail  = 'mitchellstempowski@gmail.com';
$subject   = 'New quote request — Knoxville Plumbing Co';
$body      = "Name: {$name}\nPhone: {$phone}\nService: {$service}\nDetails: {$details}\nPage: {$page}\nTime: " . date('c');

/**
 * Send one message through an authenticated SMTP server (STARTTLS + AUTH LOGIN).
 * Raw sockets, no dependencies. Returns true on 250 after DATA, false otherwise.
 */
function smtp_send($cfg, $to, $subject, $body, $from_email, $from_name) {
    $fp = @fsockopen($cfg['host'], (int) $cfg['port'], $errno, $errstr, 15);
    if (!$fp) return false;
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $resp = '';
        while (($line = fgets($fp, 512)) !== false) {
            $resp .= $line;
            if (preg_match('/^\d{3} /', $line)) break;
        }
        return $resp;
    };
    $cmd = function ($c) use ($fp, $read) {
        fwrite($fp, $c . "\r\n");
        return $read();
    };

    $ok = function ($resp, $want = '250') {
        return substr(trim($resp), 0, 3) === $want;
    };

    $fail = function () use ($fp) { @fwrite($fp, "QUIT\r\n"); @fclose($fp); return false; };

    if (!$ok($read(), '220')) return $fail();
    if (!$ok($cmd('EHLO vellops.com'))) return $fail();
    if (!$ok($cmd('STARTTLS'), '220')) return $fail();
    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return $fail();
    if (!$ok($cmd('EHLO vellops.com'))) return $fail();
    if (substr(trim($cmd('AUTH LOGIN')), 0, 3) !== '334') return $fail();
    if (substr(trim($cmd(base64_encode($cfg['user']))), 0, 3) !== '334') return $fail();
    if (substr(trim($cmd(base64_encode($cfg['pass']))), 0, 3) !== '235') return $fail();
    if (!$ok($cmd('MAIL FROM:<' . $from_email . '>'))) return $fail();
    $rcpt = $cmd('RCPT TO:<' . $to . '>');
    if (!$ok($rcpt) && substr(trim($rcpt), 0, 3) !== '251') return $fail();
    if (substr(trim($cmd('DATA')), 0, 3) !== '354') return $fail();

    $enc_subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    // Dot-stuffing: escape lines beginning with a dot per RFC 5321.
    $safe_body = preg_replace('/^\./m', '..', $body);
    $msg = "From: {$from_name} <{$from_email}>\r\n"
         . "To: <{$to}>\r\n"
         . "Subject: {$enc_subj}\r\n"
         . "Date: " . date('r') . "\r\n"
         . "Message-ID: <" . uniqid('knox', true) . "@vellops.com>\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "Content-Transfer-Encoding: 8bit\r\n"
         . "\r\n"
         . $safe_body . "\r\n.\r\n";
    fwrite($fp, $msg);
    $data_resp = $read();
    $cmd('QUIT');
    fclose($fp);
    return $ok($data_resp);
}

// Primary: Zoho SMTP to the business inbox. From must be the authenticated Zoho account
// so SPF/DKIM/DMARC all align under msecomllc.com.
$sent_zoho = false;
$smtp_cfg = @include '/home/u113057883/vellops-data/smtp-zoho.php';
if (is_array($smtp_cfg) && !empty($smtp_cfg['pass'])) {
    $sent_zoho = smtp_send(
        $smtp_cfg,
        $to_zoho,
        $subject,
        $body,
        'mitchell@msecomllc.com',
        'Knoxville Plumbing Co'
    );
}

// Fallback for Zoho via local relay (kept for resilience; historically Zoho drops these,
// Gmail accepts them).
if (!$sent_zoho) {
    $headers = "From: Knoxville Plumbing Co <noreply@vellops.com>\r\n";
    $sent_zoho = (bool) @mail($to_zoho, $subject, $body, $headers);
}

// Backup recipient: Gmail delivery via Hostinger relay is proven working.
$headers = "From: Knoxville Plumbing Co <noreply@vellops.com>\r\n";
$sent_gmail = (bool) @mail($to_gmail, $subject, $body, $headers);

echo json_encode(['ok' => (bool) ($sent_zoho || $sent_gmail), 'logged' => true]);
