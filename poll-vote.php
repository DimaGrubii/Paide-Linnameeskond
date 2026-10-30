<?php
/**
 * Paide Linnameeskond — Hooaja Parim Mängija 2026 Poll Endpoint
 *
 * Compatible with PHP 5.3+ through PHP 8.x (Zone.ee).
 * - Prevents duplicate votes per email address (1 vote per email for Men, 1 vote per email for Women).
 * - Stores structured data in data/poll-season-player-2026.json
 * - Appends spreadsheet-ready rows to data/poll-season-player-2026.csv
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

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    setStatus(200);
    echo json_encode(array('ok' => true));
    exit;
}

// Resolve data directory (works from both root /poll-vote.php and /api/poll-vote.php)
$baseDir = dirname(__FILE__);
if (basename($baseDir) === 'api') {
    $baseDir = dirname($baseDir);
}
$dataDir  = $baseDir . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}
$jsonFile = $dataDir . DIRECTORY_SEPARATOR . 'poll-season-player-2026.json';
$csvFile  = $dataDir . DIRECTORY_SEPARATOR . 'poll-season-player-2026.csv';

function loadPollData($jsonFile) {
    $default = array(
        'updated_at' => date('c'),
        'total_votes' => array(
            'men'   => 0,
            'women' => 0
        ),
        'standings' => array(
            'men'   => array(),
            'women' => array()
        ),
        'votes' => array()
    );
    if (!is_file($jsonFile)) {
        return $default;
    }
    $raw = @file_get_contents($jsonFile);
    if (!is_string($raw) || $raw === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $default;
    }
    if (!isset($decoded['total_votes']) || !is_array($decoded['total_votes'])) {
        $decoded['total_votes'] = array('men' => 0, 'women' => 0);
    }
    if (!isset($decoded['standings']) || !is_array($decoded['standings'])) {
        $decoded['standings'] = array('men' => array(), 'women' => array());
    }
    if (!isset($decoded['votes']) || !is_array($decoded['votes'])) {
        $decoded['votes'] = array();
    }
    return $decoded;
}

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

// ============================================================================
// GET: Return public poll summary (without exposing voter emails)
// ============================================================================
if ($method === 'GET') {
    $pollData = loadPollData($jsonFile);
    echo json_encode(array(
        'ok'          => true,
        'updated_at'  => isset($pollData['updated_at']) ? $pollData['updated_at'] : date('c'),
        'total_votes' => $pollData['total_votes'],
        'standings'   => $pollData['standings']
    ));
    exit;
}

if ($method !== 'POST') {
    setStatus(405);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Lubatud on ainult GET ja POST päringud.'
    ));
    exit;
}

// ============================================================================
// POST: Parse & Validate Vote
// ============================================================================
$input = is_array($_POST) ? $_POST : array();
if (empty($input)) {
    $rawBody = @file_get_contents('php://input');
    if (is_string($rawBody) && $rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $input = $decoded;
        } else {
            $parsed = array();
            parse_str($rawBody, $parsed);
            if (is_array($parsed) && !empty($parsed)) {
                $input = $parsed;
            }
        }
    }
}

// Honeypot check
if (!empty($input['website_url'])) {
    echo json_encode(array('ok' => true));
    exit;
}

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

$rawEmail     = strtolower(cleanLine(getField($input, 'email'), 150));
$squad        = cleanLine(getField($input, 'squad'), 20);
$playerName   = cleanLine(getField($input, 'player_name') !== '' ? getField($input, 'player_name') : getField($input, 'playerName'), 100);
$playerNumber = cleanLine(getField($input, 'player_number') !== '' ? getField($input, 'player_number') : getField($input, 'playerNumber'), 10);
$playerRole   = cleanLine(getField($input, 'player_role') !== '' ? getField($input, 'player_role') : getField($input, 'role'), 40);

if ($squad !== 'men' && $squad !== 'women') {
    setStatus(400);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Palun valige meeskond või naiskond.'
    ));
    exit;
}

if ($playerName === '') {
    setStatus(400);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Palun valige mängija, kelle poolt soovite hääletada.'
    ));
    exit;
}

if ($rawEmail === '') {
    setStatus(400);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Palun sisestage oma e-posti aadress hääle kinnitamiseks.'
    ));
    exit;
}

$validEmail = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
if ($validEmail === false) {
    setStatus(400);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Palun sisestage korrektne e-posti aadress.'
    ));
    exit;
}
$validEmail = strtolower($validEmail);

// Rate limiting per IP (max 10 attempts per 15 min)
$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
$rateKey  = 'plm_poll_' . md5($clientIp);
$rateFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $rateKey . '.json';
$now      = time();
$window   = 900;
$maxReqs  = 10;

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

// Load existing poll data and check for duplicate email in the same squad category.
// Rule: 1 email address can vote ONCE for 'men' (Esindusmeeskond) and ONCE for 'women' (Linnanaiskond).
$pollData = loadPollData($jsonFile);
$otherSquad = ($squad === 'women') ? 'men' : 'women';
$hasVotedCurrentSquad = false;
$hasVotedOtherSquad   = false;
$existingCurrentPlayer = '';

foreach ($pollData['votes'] as $existingVote) {
    if (isset($existingVote['email']) && strtolower($existingVote['email']) === $validEmail) {
        if (isset($existingVote['squad']) && $existingVote['squad'] === $squad) {
            $hasVotedCurrentSquad = true;
            $existingCurrentPlayer = isset($existingVote['player_name']) ? $existingVote['player_name'] : '';
        } elseif (isset($existingVote['squad']) && $existingVote['squad'] === $otherSquad) {
            $hasVotedOtherSquad = true;
        }
    }
}

if ($hasVotedCurrentSquad) {
    setStatus(409);
    $squadTitle = ($squad === 'women') ? 'Paide Linnanaiskonna' : 'Esindusmeeskonna';
    $otherTitle = ($otherSquad === 'women') ? 'Paide Linnanaiskonna' : 'Esindusmeeskonna';
    $errMsg = $hasVotedOtherSquad
        ? "Selle e-posti aadressiga on juba mõlemas kategoorias (nii Esindusmeeskonna kui ka Paide Linnanaiskonna) hääletatud!"
        : "Selle e-posti aadressiga on {$squadTitle} kategoorias juba hääletatud! Küll aga saad sama e-posti aadressiga veel hääletada {$otherTitle} kategoorias.";

    echo json_encode(array(
        'ok'                    => false,
        'already_voted'         => true,
        'has_voted_other_squad' => $hasVotedOtherSquad,
        'other_squad'           => $otherSquad,
        'voted_player'          => $existingCurrentPlayer,
        'error'                 => $errMsg
    ));
    exit;
}

// Record vote
$dateFormatted = date('d.m.Y H:i:s');
$voteEntry = array(
    'timestamp'     => date('c'),
    'date_readable' => $dateFormatted,
    'squad'         => $squad,
    'squad_label'   => ($squad === 'women') ? 'Paide Linnanaiskond' : 'Esindusmeeskond',
    'player_name'   => $playerName,
    'player_number' => $playerNumber,
    'player_role'   => $playerRole,
    'email'         => $validEmail,
    'ip_hash'       => substr(md5($clientIp . '_plm2026'), 0, 12)
);

$pollData['votes'][] = $voteEntry;
$pollData['updated_at'] = date('c');

if (!isset($pollData['total_votes'][$squad])) {
    $pollData['total_votes'][$squad] = 0;
}
$pollData['total_votes'][$squad]++;

if (!isset($pollData['standings'][$squad][$playerName])) {
    $pollData['standings'][$squad][$playerName] = 0;
}
$pollData['standings'][$squad][$playerName]++;
arsort($pollData['standings'][$squad]);

// Save JSON document
$jsonEncoded = defined('JSON_PRETTY_PRINT')
    ? json_encode($pollData, JSON_PRETTY_PRINT | (defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0))
    : json_encode($pollData);

@file_put_contents($jsonFile, $jsonEncoded, LOCK_EX);

// Append to CSV document for easy Excel/Sheets review
$isNewCsv = !is_file($csvFile) || filesize($csvFile) === 0;
$fp = @fopen($csvFile, 'a');
if ($fp) {
    if (@flock($fp, LOCK_EX)) {
        if ($isNewCsv) {
            // Write UTF-8 BOM for Excel compatibility
            @fwrite($fp, "\xEF\xBB\xBF");
            @fputcsv($fp, array('Aeg', 'Kategooria', 'Mängija', 'Särginumber', 'Positsioon', 'E-post'));
        }
        @fputcsv($fp, array(
            $dateFormatted,
            $voteEntry['squad_label'],
            $playerName,
            $playerNumber,
            $playerRole,
            $validEmail
        ));
        @flock($fp, LOCK_UN);
    }
    @fclose($fp);
}

$timestamps[] = $now;
@file_put_contents($rateFile, json_encode($timestamps), LOCK_EX);

echo json_encode(array(
    'ok'                    => true,
    'message'               => 'Aitäh! Sinu hääl on edukalt salvestatud.',
    'squad'                 => $squad,
    'other_squad'           => $otherSquad,
    'has_voted_other_squad' => $hasVotedOtherSquad,
    'player_name'           => $playerName,
    'total_votes'           => $pollData['total_votes'],
    'standings'             => $pollData['standings']
));
