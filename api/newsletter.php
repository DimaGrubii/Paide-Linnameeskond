<?php
/**
 * Paide Linnameeskond — Newsletter Subscription Endpoint (Smaily Manual List Notification)
 *
 * Compatible with PHP 5.3+ through PHP 8.x (Zone.ee).
 * Sends new subscriber notifications to dimagrubii@gmail.com and karl@paidelinnameeskond.ee
 * so they can be added to the club's Smaily mailing list.
 */

@ini_set('display_errors', '0');
error_reporting(0);
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Europe/Tallinn');
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function setStatus($code) {
    if (function_exists('http_response_code')) {
        http_response_code($code);
    } else {
        header('X-PHP-Response-Code: ' . (int) $code, true, (int) $code);
    }
}

// Handle CORS / preflight requests gracefully if triggered
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    setStatus(200);
    echo json_encode(array('ok' => true));
    exit;
}

// ============================================================================
// 1. CONFIGURATION (Newsletter Notification Recipients for Smaily)
// ============================================================================
$RECIPIENT_EMAIL = 'dimagrubii@gmail.com, karl@paidelinnameeskond.ee';

// Primary sender address for paidelinnameeskond.ee:
$SENDER_EMAIL    = 'info@paidelinnameeskond.ee';
$SENDER_NAME     = 'Paide Linnameeskond';

// ============================================================================
// 2. HTTP METHOD CHECK
// ============================================================================
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    setStatus(405);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Lubatud on ainult POST päringud.'
    ));
    exit;
}

// ============================================================================
// 3. PARSE INPUT (Supports both form-urlencoded $_POST and JSON body)
// ============================================================================
$data = is_array($_POST) ? $_POST : array();
if (empty($data)) {
    $rawBody = @file_get_contents('php://input');
    if (is_string($rawBody) && $rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $data = $decoded;
        } else {
            $parsed = array();
            parse_str($rawBody, $parsed);
            if (is_array($parsed) && !empty($parsed)) {
                $data = $parsed;
            }
        }
    }
}

// ============================================================================
// 4. HONEYPOT ANTI-BOT CHECK
// ============================================================================
if (!empty($data['website_url'])) {
    echo json_encode(array('ok' => true));
    exit;
}

// ============================================================================
// 5. RATE LIMITING PER IP (Max 5 submissions per 15 minutes)
// ============================================================================
$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
$rateKey  = 'plm_news_' . md5($clientIp);
$rateFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $rateKey . '.json';
$now      = time();
$window   = 900; // 15 minutes
$maxReqs  = 5;

$timestamps = array();
if (is_file($rateFile)) {
    $existing = json_decode((string) @file_get_contents($rateFile), true);
    if (is_array($existing)) {
        foreach ($existing as $ts) {
            if (is_int($ts) && ($now - $ts) < $window) {
                $timestamps[] = $ts;
            }
        }
    }
}

if (count($timestamps) >= $maxReqs) {
    setStatus(429);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Liiga palju päringuid lühikese aja jooksul. Palun proovige mõne minuti pärast uuesti.'
    ));
    exit;
}

// ============================================================================
// 6. SANITIZATION & VALIDATION
// ============================================================================
function getField($arr, $key) {
    return (is_array($arr) && isset($arr[$key])) ? $arr[$key] : '';
}

function cleanLine($val, $maxLen = 150) {
    $val = (string) $val;
    $val = trim(strip_tags($val));
    $clean = @preg_replace('/[\r\n\t\x00-\x1F\x7F]+/u', ' ', $val);
    if ($clean === null) {
        $clean = preg_replace('/[\r\n\t]+/', ' ', $val);
    }
    return function_exists('mb_substr') ? mb_substr($clean, 0, $maxLen, 'UTF-8') : substr($clean, 0, $maxLen);
}

$rawEmail = cleanLine(getField($data, 'subscriber_email'), 150);
if ($rawEmail === '') {
    $rawEmail = cleanLine(getField($data, 'email'), 150);
}
$sourcePage = cleanLine(getField($data, 'source_page'), 120);
if ($sourcePage === '') {
    $sourcePage = '/';
}

if ($rawEmail === '') {
    setStatus(400);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Palun sisestage oma e-posti aadress.'
    ));
    exit;
}

$subscriberEmail = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
if ($subscriberEmail === false) {
    setStatus(400);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Palun sisestage korrektne e-posti aadress.'
    ));
    exit;
}

// ============================================================================
// 7. BUILD & SEND EMAIL VIA MAIL SERVER
// ============================================================================
$subjectText = "Uus uudiskirja liituja (Smaily): {$subscriberEmail}";
$encodedSubject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';

$dateStr = date('d.m.Y H:i:s');

$emailBody = "UUS UUDISKIRJA LIITUJA — PAIDE LINNAMEESKOND\n"
    . "====================================================\n\n"
    . "E-posti aadress (Smaily):  {$subscriberEmail}\n"
    . "Esitatud:                  {$dateStr}\n"
    . "Lehekülg:                  {$sourcePage}\n\n"
    . "----------------------------------------------------\n"
    . "See külastaja liitus Paide Linnameeskonna kodulehe jaluses oleva uudiskirja vormi kaudu.\n"
    . "Palun lisage e-posti aadress ({$subscriberEmail}) Smaily uudiskirja nimekirja.\n";

$encodedSenderName = '=?UTF-8?B?' . base64_encode($SENDER_NAME) . '?=';

$serverHost = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', strtolower(trim($_SERVER['HTTP_HOST']))) : '';
$serverHost = preg_replace('/^www\./', '', $serverHost);
$hostSenderEmail = ($serverHost !== '' && strpos($serverHost, '.') !== false)
    ? "noreply@{$serverHost}"
    : $SENDER_EMAIL;

function trySendNewsletterMail($to, $subject, $body, $fromName, $fromEmail, $replyEmail) {
    $headersStr = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n"
        . "From: {$fromName} <{$fromEmail}>\r\n"
        . "Reply-To: {$replyEmail}\r\n"
        . "X-Mailer: PaideLinnameeskond-Newsletter/1.0";

    if (@mail($to, $subject, $body, $headersStr, "-f{$fromEmail}")) {
        return true;
    }
    if (@mail($to, $subject, $body, $headersStr)) {
        return true;
    }
    return false;
}

$sent = trySendNewsletterMail(
    $RECIPIENT_EMAIL,
    $encodedSubject,
    $emailBody,
    $encodedSenderName,
    $SENDER_EMAIL,
    $subscriberEmail
);

if (!$sent && $hostSenderEmail !== $SENDER_EMAIL) {
    $sent = trySendNewsletterMail(
        $RECIPIENT_EMAIL,
        $encodedSubject,
        $emailBody,
        $encodedSenderName,
        $hostSenderEmail,
        $subscriberEmail
    );
}

if (!$sent) {
    $minimalHeaders = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Reply-To: {$subscriberEmail}";
    $sent = @mail($RECIPIENT_EMAIL, $encodedSubject, $emailBody, $minimalHeaders);
}

if (!$sent) {
    setStatus(500);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Liitumise saatmisel tekkis tehniline tõrge. Palun proovige hetke pärast uuesti.'
    ));
    exit;
}

$timestamps[] = $now;
@file_put_contents($rateFile, json_encode($timestamps), LOCK_EX);

echo json_encode(array(
    'ok'      => true,
    'message' => 'Aitäh! Oled edukalt uudiskirjaga liitunud.'
));
