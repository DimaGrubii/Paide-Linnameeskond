<?php
/**
 * Paide Linnameeskond — Youth Trial Training Registration Endpoint (Zone.ee PHP)
 *
 * Compatible with PHP 5.3+ through PHP 8.x.
 * Security & Privacy (GDPR):
 * - No database storage: Personal data is never written to a public database.
 * - Honeypot spam protection ('website_url').
 * - Per-IP rate limiting (max 5 submissions per 15 minutes).
 * - Strict input validation, sanitization, and email header-injection prevention.
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
// 1. CONFIGURATION & DYNAMIC COACH ROUTING
// ============================================================================
$BASE_RECIPIENTS = array(
    'margus.luts@paidelinnameeskond.ee',
    'marek@paidelinnameeskond.ee'
);

// Primary sender address for paidelinnameeskond.ee:
$SENDER_EMAIL    = 'info@paidelinnameeskond.ee';
$SENDER_NAME     = 'Paide Linnameeskond';

/**
 * Resolves the target training group, coach names, and coach emails
 * based on preferred location, gender, and birth year (2026 season).
 */
function resolveRegistrationRouting($locationKey, $genderKey, $birthYear) {
    $year = (int) $birthYear;

    // 1. Regional centers (Mixed-gender groups; Option A: all ages in town route to town coach)
    if ($locationKey === 'koeru') {
        return array(
            'group'   => ($year >= 2020) ? 'Koeru lasteaed' : 'Koeru 1.–3. klass',
            'coaches' => 'Rando Isakar',
            'emails'  => array('rando@paidelinnameeskond.ee')
        );
    }

    if ($locationKey === 'jarva-jaani') {
        return array(
            'group'   => 'Järva-Jaani 1.–3. klass',
            'coaches' => 'Viktor Mets',
            'emails'  => array('viktor@paidelinnameeskond.ee')
        );
    }

    if ($locationKey === 'aravete') {
        return array(
            'group'   => 'Aravete 1.–3. klass',
            'coaches' => 'Kevin Kärp',
            'emails'  => array('kevin.karp@paidelinnameeskond.ee')
        );
    }

    if ($locationKey === 'tyri') {
        if ($year >= 2020) {
            return array(
                'group'   => 'Türi lasteaed',
                'coaches' => 'Rauno Kald',
                'emails'  => array('rauno@paidelinnameeskond.ee')
            );
        }
        if ($year >= 2017 && $year <= 2019) {
            return array(
                'group'   => 'Türi 1.–2. klass',
                'coaches' => 'Rauno Kald',
                'emails'  => array('rauno@paidelinnameeskond.ee')
            );
        }
        return array(
            'group'   => 'Türi 3.–5. klass',
            'coaches' => 'Kristo Kiik',
            'emails'  => array('kristo@paidelinnameeskond.ee')
        );
    }

    // 2. Paide (and default fallback) — Girls ('tudruk')
    if ($genderKey === 'tudruk') {
        if ($year >= 2018) {
            return array(
                'group'   => 'Paide U9 Tüdrukud',
                'coaches' => 'Kristi Selter & Kätlin Kalamees',
                'emails'  => array('kristi@paidelinnameeskond.ee', 'katlin@paidelinnameeskond.ee')
            );
        }
        if ($year >= 2015 && $year <= 2017) {
            return array(
                'group'   => 'Paide U12 Tüdrukud',
                'coaches' => 'Katriin Rodi',
                'emails'  => array('katriin@paidelinnameeskond.ee')
            );
        }
        if ($year >= 2013 && $year <= 2014) {
            return array(
                'group'   => 'Paide U14 Tüdrukud',
                'coaches' => 'Kaivo Sang',
                'emails'  => array('kaivo@paidelinnameeskond.ee')
            );
        }
        return array(
            'group'   => 'Paide Linnanaiskond II / TU17',
            'coaches' => 'Joana Freitas & Sirje Kapper',
            'emails'  => array('joana@paidelinnameeskond.ee', 'sirje@paidelinnameeskond.ee')
        );
    }

    // 3. Paide (and default fallback) — Boys ('poiss')
    if ($year >= 2020) {
        return array(
            'group'   => 'Paide LM U7 (Mudilased)',
            'coaches' => 'Markus Piim & Kert Selter',
            'emails'  => array('markus@paidelinnameeskond.ee')
        );
    }
    if ($year === 2019) {
        return array(
            'group'   => 'Paide LM U-8',
            'coaches' => 'Markus Piim & Kert Selter',
            'emails'  => array('markus@paidelinnameeskond.ee')
        );
    }
    if ($year === 2018) {
        return array(
            'group'   => 'Paide LM U-9',
            'coaches' => 'Taavi Parts & Markus Piim',
            'emails'  => array('taavi@paidelinnameeskond.ee', 'markus@paidelinnameeskond.ee')
        );
    }
    if ($year === 2017) {
        return array(
            'group'   => 'Paide LM U10',
            'coaches' => 'Rauno Kald & Taavi Parts',
            'emails'  => array('rauno@paidelinnameeskond.ee', 'taavi@paidelinnameeskond.ee')
        );
    }
    if ($year === 2016) {
        return array(
            'group'   => 'Paide LM U-11',
            'coaches' => 'Steven Pleesi & Meelis Rooba',
            'emails'  => array('steven@paidelinnameeskond.ee', 'meelis@paidelinnameeskond.ee')
        );
    }
    if ($year === 2015) {
        return array(
            'group'   => 'Paide LM U-12',
            'coaches' => 'Steven Pleesi & Markus Piim',
            'emails'  => array('steven@paidelinnameeskond.ee', 'markus@paidelinnameeskond.ee')
        );
    }
    if ($year >= 2013 && $year <= 2014) {
        return array(
            'group'   => 'Paide LM U-13',
            'coaches' => 'Rauno Kööp & Kevin Kärp',
            'emails'  => array('rauno.koop@paidelinnameeskond.ee', 'kevin.karp@paidelinnameeskond.ee')
        );
    }
    if ($year >= 2011 && $year <= 2012) {
        return array(
            'group'   => 'Paide LM U-15',
            'coaches' => 'Kevin Kärp & Kristo Kiik',
            'emails'  => array('kevin.karp@paidelinnameeskond.ee', 'kristo@paidelinnameeskond.ee')
        );
    }
    if ($year === 2010) {
        return array(
            'group'   => 'Akadeemia U-17 / U19',
            'coaches' => 'Hardi Häelme & Rauno Kald',
            'emails'  => array('hardi@paidelinnameeskond.ee', 'rauno@paidelinnameeskond.ee')
        );
    }

    return array(
        'group'   => 'Paide LM U-19 / Akadeemia U-17/U19',
        'coaches' => 'Kevin Paavo, Meelis Rooba, Hardi Häelme & Rauno Kald',
        'emails'  => array(
            'kevin@paidelinnameeskond.ee',
            'meelis@paidelinnameeskond.ee',
            'hardi@paidelinnameeskond.ee',
            'rauno@paidelinnameeskond.ee'
        )
    );
}

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
// Real users never see or fill 'website_url'. Automated spam bots fill it.
if (!empty($data['website_url'])) {
    echo json_encode(array('ok' => true));
    exit;
}

// ============================================================================
// 5. RATE LIMITING PER IP (Max 5 submissions per 15 minutes)
// ============================================================================
$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
$rateKey  = 'plm_reg_' . md5($clientIp);
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
// 6. SANITIZATION & VALIDATION HELPERS
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

function cleanMultiline($val, $maxLen = 1500) {
    $val = (string) $val;
    $val = trim(strip_tags($val));
    $val = str_replace(array("\r\n", "\r"), "\n", $val);
    return function_exists('mb_substr') ? mb_substr($val, 0, $maxLen, 'UTF-8') : substr($val, 0, $maxLen);
}

$childName      = cleanLine(getField($data, 'child_name'), 120);
$childIsikukood = cleanLine(getField($data, 'child_isikukood'), 30);
$childBirthyear = cleanLine(getField($data, 'child_birthyear'), 10);
$childGenderRaw = cleanLine(getField($data, 'child_gender'), 20);
$trainingLocRaw = cleanLine(getField($data, 'training_location'), 40);
$parentName     = cleanLine(getField($data, 'parent_name'), 120);
$parentPhone    = cleanLine(getField($data, 'parent_phone'), 40);
$parentEmailRaw = cleanLine(getField($data, 'parent_email'), 150);
$notes          = cleanMultiline(getField($data, 'notes'), 1500);
$sourcePage     = cleanLine(getField($data, 'source_page'), 120);

// Validate required fields
if ($childName === '' || $childIsikukood === '' || $childBirthyear === '' || $parentName === '' || $parentPhone === '' || $parentEmailRaw === '') {
    setStatus(400);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Palun täitke kõik tärniga (*) märgitud kohustuslikud väljad.'
    ));
    exit;
}

// Validate email address
$parentEmail = filter_var($parentEmailRaw, FILTER_VALIDATE_EMAIL);
if ($parentEmail === false) {
    setStatus(400);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Palun sisestage korrektne e-posti aadress.'
    ));
    exit;
}

// Validate birth year (4 digits, reasonable range)
if (!preg_match('/^(200[5-9]|201[0-9]|202[0-6])$/', $childBirthyear)) {
    setStatus(400);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Palun valige nimekirjast lapse sünniaasta.'
    ));
    exit;
}

// Map human-readable labels for gender & training center
$genderLabels = array(
    'poiss'  => 'Poiss',
    'tudruk' => 'Tüdruk'
);
$childGender = isset($genderLabels[$childGenderRaw]) ? $genderLabels[$childGenderRaw] : $childGenderRaw;

$locationLabels = array(
    'paide'       => 'Paide (jalgpallikeskus & hall)',
    'tyri'        => 'Türi (linnastaadion & spordihoone)',
    'koeru'       => 'Koeru (keskkooli staadion)',
    'jarva-jaani' => 'Järva-Jaani',
    'aravete'     => 'Aravete & Albu'
);
$trainingLocation = isset($locationLabels[$trainingLocRaw]) ? $locationLabels[$trainingLocRaw] : $trainingLocRaw;

// ============================================================================
// 7. RESOLVE TARGET GROUP, COACHES & RECIPIENTS
// ============================================================================
$routing = resolveRegistrationRouting($trainingLocRaw, $childGenderRaw, $childBirthyear);
$matchedGroup   = $routing['group'];
$matchedCoaches = $routing['coaches'];

$recipientList = $BASE_RECIPIENTS;
if (isset($routing['emails']) && is_array($routing['emails'])) {
    foreach ($routing['emails'] as $coachEmail) {
        if ($coachEmail !== '' && !in_array($coachEmail, $recipientList, true)) {
            $recipientList[] = $coachEmail;
        }
    }
}
$RECIPIENT_EMAIL = implode(', ', $recipientList);

// ============================================================================
// 8. BUILD & SEND EMAIL VIA MAIL SERVER
// ============================================================================
$subjectText = "Uus proovitrenni registreering: {$childName} ({$childBirthyear}) - {$matchedGroup}";
$encodedSubject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';

$dateStr = date('d.m.Y H:i:s');
$notesBlock = ($notes !== '') ? $notes : '— (lisainfo puudub)';

$emailBody = "UUS PROOVITRENNI REGISTREERING — PAIDE LINNAMEESKOND\n"
    . "====================================================\n\n"
    . "1. LAPSE ANDMED & TREENINGGRUPP\n"
    . "----------------------------------------------------\n"
    . "Lapse nimi:              {$childName}\n"
    . "Isikukood:               {$childIsikukood}\n"
    . "Sünniaasta:              {$childBirthyear}\n"
    . "Sugu:                    {$childGender}\n"
    . "Eelistatud treeningpaik: {$trainingLocation}\n"
    . "Suunatud treeninggrupp:  {$matchedGroup}\n"
    . "Grupi treener(id):       {$matchedCoaches}\n\n"
    . "2. LAPSEVANEMA KONTAKT\n"
    . "----------------------------------------------------\n"
    . "Lapsevanema nimi:        {$parentName}\n"
    . "Telefon:                 {$parentPhone}\n"
    . "E-posti aadress:         {$parentEmail}\n\n"
    . "3. LISAINFO VÕI KÜSIMUSED\n"
    . "----------------------------------------------------\n"
    . "{$notesBlock}\n\n"
    . "----------------------------------------------------\n"
    . "Saajad:   {$RECIPIENT_EMAIL}\n"
    . "Esitatud: {$dateStr}\n"
    . "Lehekülg: {$sourcePage}\n"
    . "(Vastamiseks võite vajutada otse \"Reply / Vasta\" — vastus läheb lapsevanema e-postile: {$parentEmail})\n";

$encodedSenderName = '=?UTF-8?B?' . base64_encode($SENDER_NAME) . '?=';
$encodedParentName = '=?UTF-8?B?' . base64_encode($parentName) . '?=';

// Detect current server host so SPF/MTA checks pass even when testing on a staging domain
$serverHost = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', strtolower(trim($_SERVER['HTTP_HOST']))) : '';
$serverHost = preg_replace('/^www\./', '', $serverHost);
$hostSenderEmail = ($serverHost !== '' && strpos($serverHost, '.') !== false)
    ? "noreply@{$serverHost}"
    : $SENDER_EMAIL;

function trySendRegistrationMail($to, $subject, $body, $fromName, $fromEmail, $replyName, $replyEmail) {
    $headersStr = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n"
        . "From: {$fromName} <{$fromEmail}>\r\n"
        . "Reply-To: {$replyName} <{$replyEmail}>\r\n"
        . "X-Mailer: PaideLinnameeskond-Registration/1.2";

    // Attempt 1: With envelope sender (-f)
    if (@mail($to, $subject, $body, $headersStr, "-f{$fromEmail}")) {
        return true;
    }
    // Attempt 2: Without envelope sender (-f)
    if (@mail($to, $subject, $body, $headersStr)) {
        return true;
    }
    return false;
}

$sent = trySendRegistrationMail(
    $RECIPIENT_EMAIL,
    $encodedSubject,
    $emailBody,
    $encodedSenderName,
    $SENDER_EMAIL,
    $encodedParentName,
    $parentEmail
);

// Fallback: If hosted on a domain other than paidelinnameeskond.ee (e.g., staging domain),
// use the active server domain as From address so the host MTA allows sending.
if (!$sent && $hostSenderEmail !== $SENDER_EMAIL) {
    $sent = trySendRegistrationMail(
        $RECIPIENT_EMAIL,
        $encodedSubject,
        $emailBody,
        $encodedSenderName,
        $hostSenderEmail,
        $encodedParentName,
        $parentEmail
    );
}

// Final fallback: Minimal headers using server default From address
if (!$sent) {
    $minimalHeaders = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Reply-To: {$parentEmail}";
    $sent = @mail($RECIPIENT_EMAIL, $encodedSubject, $emailBody, $minimalHeaders);
}

if (!$sent) {
    setStatus(500);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Registreeringu saatmisel tekkis tehniline tõrge. Palun kirjutage otse aadressile info@paidelinnameeskond.ee.'
    ));
    exit;
}

// Record timestamp for rate limiting after successful send
$timestamps[] = $now;
@file_put_contents($rateFile, json_encode($timestamps), LOCK_EX);

echo json_encode(array(
    'ok'      => true,
    'message' => 'Registreering on edukalt edastatud!'
));
