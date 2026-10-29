<?php
// Knoxville Plumbing Co — quote form mailer, hosted on vellops.com.
// Receives cross-origin JSON POST from https://knoxvilleplumbingco.com (static GitHub Pages).
// Emails the lead to Mitchell. No dependencies, no storage.
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

$to      = 'mitchell@msecomllc.com';
$subject = 'New quote request — Knoxville Plumbing Co';
$body    = "Name: {$name}\nPhone: {$phone}\nService: {$service}\nDetails: {$details}\nPage: {$page}\nTime: " . date('c');
$headers = "From: Knoxville Plumbing Co <noreply@knoxvilleplumbingco.com>\r\n";

$sent = @mail($to, $subject, $body, $headers);
echo json_encode(['ok' => (bool) $sent]);
