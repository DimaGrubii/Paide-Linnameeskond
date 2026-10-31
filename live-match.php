<?php
/**
 * Paide Linnameeskond — Official EJL OpenData API Live Match Endpoint
 *
 * Replaces legacy HTML protocol scraping with official EJL OpenData REST API calls:
 *   - GET /api/v1/teams/5/games (Paide Linnameeskond)
 *   - GET /api/v1/teams/3521/games (Paide Linnanaiskond)
 *   - GET /api/v1/games/{calendarId} (Full live match protocol, scores, events, lineups)
 *
 * Security: Uses `api/ejl-client.php` so the EJL Bearer token remains strictly confidential on the server.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('Europe/Tallinn');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'ejl-client.php';

/**
 * Normalizes a `/api/v1/games/{id}` API response into the frontend live-match schema.
 */
function ejlBuildLiveMatchPayload($gameDetail) {
    if (!is_array($gameDetail) || empty($gameDetail['id'])) {
        return null;
    }

    $gameId = (int)$gameDetail['id'];
    $homeObj = $gameDetail['teams']['home'] ?? array();
    $awayObj = $gameDetail['teams']['away'] ?? array();
    $homeTeam = ejlCleanTeamName($homeObj['name'] ?? '');
    $awayTeam = ejlCleanTeamName($awayObj['name'] ?? '');

    if ($homeTeam === '' || $awayTeam === '') {
        return null;
    }

    // Ensure at least one team is Paide Linnameeskond or Paide Linnanaiskond
    $isPaide = (mb_stripos($homeTeam, 'paide', 0, 'UTF-8') !== false) ||
               (mb_stripos($awayTeam, 'paide', 0, 'UTF-8') !== false);
    if (!$isPaide) {
        return null;
    }

    $compObj = $gameDetail['competition'] ?? array();
    $compName = trim((string)($compObj['longName'] ?? $compObj['name'] ?? ''));
    $compSex = strtoupper((string)($compObj['sex'] ?? ''));
    $isWomen = ($compSex === 'N') ||
               (mb_stripos($compName, 'naiste', 0, 'UTF-8') !== false) ||
               (mb_stripos($homeObj['name'] ?? '', 'naiskond', 0, 'UTF-8') !== false) ||
               (mb_stripos($awayObj['name'] ?? '', 'naiskond', 0, 'UTF-8') !== false);

    $dtParsed = ejlParseDateTime($gameDetail['dateTime'] ?? '');
    $kickoffTs = (int)$dtParsed['timestamp'];
    $now = time();

    $statuses = $gameDetail['statuses'] ?? array();
    $gameStatusObj = $statuses['gameStatus'] ?? array();
    $gameStatusCode = strtoupper((string)($gameStatusObj['code'] ?? ''));
    $gameStatusName = (string)($gameStatusObj['name'] ?? '');
    $liveStatus = $statuses['liveStatus'] ?? null;
    $liveMinute = isset($statuses['liveMinute']) && $statuses['liveMinute'] !== null ? (int)$statuses['liveMinute'] : null;
    $liveExtraMinute = isset($statuses['liveExtraMinute']) && $statuses['liveExtraMinute'] !== null ? (int)$statuses['liveExtraMinute'] : null;

    $scores = $gameDetail['scores'] ?? array();
    $ft = $scores['fullTime'] ?? null;
    $ht = $scores['halftime'] ?? null;

    $homeScore = (is_array($ft) && isset($ft['home']) && $ft['home'] !== null) ? (int)$ft['home'] : null;
    $awayScore = (is_array($ft) && isset($ft['away']) && $ft['away'] !== null) ? (int)$ft['away'] : null;

    $htScoreStr = null;
    if (is_array($ht) && isset($ht['home']) && isset($ht['away']) && $ht['home'] !== null && $ht['away'] !== null) {
        $htScoreStr = ((int)$ht['home']) . ' : ' . ((int)$ht['away']);
    }

    $goals = ejlExtractGameGoals($gameDetail);
    if (($homeScore === null || $awayScore === null) && !empty($goals)) {
        $lastGoal = $goals[count($goals) - 1];
        if (preg_match('/(\d+)\s*:\s*(\d+)/', $lastGoal['runningScore'], $gm)) {
            $homeScore = (int)$gm[1];
            $awayScore = (int)$gm[2];
        }
    }

    $isPlayed = ($gameStatusCode === 'PLAYED' || mb_stripos($gameStatusName, 'mängitud', 0, 'UTF-8') !== false);
    $isExplicitLive = ($liveStatus !== null || $liveMinute !== null || in_array($gameStatusCode, array('LIVE', 'IN_PROGRESS', 'PLAYING', 'FIRST_HALF', 'SECOND_HALF', 'HALFTIME'), true));

    $matchState = 'upcoming';
    $statusLabel = 'Kalendris';

    if ($isPlayed) {
        $matchState = ($kickoffTs > 0 && $now <= ($kickoffTs + 10800)) ? 'finished_recent' : 'finished';
        $statusLabel = 'Lõppseis';
    } elseif ($isExplicitLive || ($kickoffTs > 0 && $now >= $kickoffTs && $now <= ($kickoffTs + 7500))) {
        $matchState = 'live';
        $statusLabel = 'Hetkeseis';
        if ($homeScore === null) {
            $homeScore = 0;
        }
        if ($awayScore === null) {
            $awayScore = 0;
        }
    } elseif ($kickoffTs > 0 && $now >= ($kickoffTs - 3600) && $now < $kickoffTs) {
        $matchState = 'pre_match';
        $statusLabel = 'Mängupäev';
        if ($homeScore === null) {
            $homeScore = 0;
        }
        if ($awayScore === null) {
            $awayScore = 0;
        }
    }

    $tour = trim((string)($gameDetail['tour'] ?? ''));
    $roundStr = $tour !== '' ? (preg_match('/voor/iu', $tour) ? $tour : "{$tour}. voor") : '';
    $venueName = trim((string)($gameDetail['field']['name'] ?? ''));
    $attendance = isset($gameDetail['attendance']) && $gameDetail['attendance'] !== null ? (int)$gameDetail['attendance'] : null;

    return array(
        'gameId'            => (string)$gameId,
        'protocolId'        => (string)$gameId,
        'protocolUrl'       => 'https://jalgpall.ee/voistlused/protocol/' . $gameId,
        'teamCategory'      => $isWomen ? 'women' : 'men',
        'competition'       => $compName !== '' ? $compName : ($isWomen ? 'Naiste Meistriliiga' : 'A. Le Coq Premium liiga'),
        'round'             => $roundStr,
        'dateStr'           => $dtParsed['dateStr'],
        'timeStr'           => $dtParsed['timeStr'],
        'dateTime'          => $dtParsed['dateTimeStr'],
        'kickoffTimestamp'  => $kickoffTs,
        'venue'             => $venueName,
        'attendance'        => $attendance,
        'homeTeam'          => $homeTeam,
        'awayTeam'          => $awayTeam,
        'homeLogo'          => ejlResolveLocalLogo($homeTeam),
        'awayLogo'          => ejlResolveLocalLogo($awayTeam),
        'scoreHome'         => $homeScore,
        'scoreAway'         => $awayScore,
        'homeScore'         => $homeScore,
        'awayScore'         => $awayScore,
        'htScore'           => $htScoreStr,
        'halftimeScore'     => $htScoreStr,
        'statusLabel'       => $statusLabel,
        'gameStatusCode'    => $gameStatusCode,
        'liveStatus'        => $liveStatus,
        'liveMinute'        => $liveMinute,
        'liveExtraMinute'   => $liveExtraMinute,
        'lineupConfirmed'   => (bool)(($homeObj['lineupConfirmed'] ?? false) || ($awayObj['lineupConfirmed'] ?? false)),
        'goals'             => $goals,
        'matchState'        => $matchState,
    );
}

// ---------------------------------------------------------------------------
// Main Execution
// ---------------------------------------------------------------------------
$requestedId = isset($_GET['game_id']) ? preg_replace('/\D+/', '', (string)$_GET['game_id']) : '';
if ($requestedId === '' && isset($_GET['protocol_id'])) {
    $requestedId = preg_replace('/\D+/', '', (string)$_GET['protocol_id']);
}
$forceRefresh = isset($_GET['force']) && $_GET['force'] === '1';

$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheKey = $requestedId !== '' ? "api_live_match_{$requestedId}.json" : 'api_live_match_auto.json';
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey;

if (!$forceRefresh && is_file($cacheFile) && (time() - filemtime($cacheFile)) < 12) {
    $cachedJson = @file_get_contents($cacheFile);
    if (is_string($cachedJson) && strlen($cachedJson) > 10) {
        echo $cachedJson;
        exit;
    }
}

$season = (int)date('Y');
$activeMatch = null;
$recentMatch = null;
$candidates = array();

if ($requestedId !== '') {
    $detail = ejlApiGet("/api/v1/games/{$requestedId}");
    $parsed = ejlBuildLiveMatchPayload($detail);
    if ($parsed !== null) {
        $candidates[] = $parsed;
    }
} else {
    // Check both Men's (5) and Women's (3521) teams for live, pre-match, or just-finished games
    $teamIds = array(5, 3521);
    $candidateGameIds = array();
    $now = time();

    foreach ($teamIds as $tid) {
        // 1. Games where kickoff has passed or are currently in progress (upcoming=0)
        $pastResp = ejlApiGet("/api/v1/teams/{$tid}/games", array(
            'season'   => $season,
            'upcoming' => 0,
            'pageSize' => 3,
        ));
        if (is_array($pastResp) && !empty($pastResp['games'])) {
            foreach ($pastResp['games'] as $idx => $g) {
                $gid = isset($g['id']) ? (int)$g['id'] : 0;
                if ($gid <= 0) {
                    continue;
                }
                $dt = ejlParseDateTime($g['dateTime'] ?? '');
                $ko = (int)$dt['timestamp'];
                $stCode = strtoupper((string)($g['statuses']['gameStatus']['code'] ?? ''));
                // Fetch full detail for the latest played game OR any game within 12 hours of now or not marked PLAYED
                if ($idx === 0 || $stCode !== 'PLAYED' || ($ko > 0 && abs($now - $ko) <= 43200)) {
                    $candidateGameIds[$gid] = $gid;
                }
            }
        }

        // 2. Games whose kickoff is still ahead (upcoming=1) — check if kickoff is today / within 2 hours
        $upResp = ejlApiGet("/api/v1/teams/{$tid}/games", array(
            'season'   => $season,
            'upcoming' => 1,
            'pageSize' => 2,
        ));
        if (is_array($upResp) && !empty($upResp['games'])) {
            foreach ($upResp['games'] as $g) {
                $gid = isset($g['id']) ? (int)$g['id'] : 0;
                if ($gid <= 0) {
                    continue;
                }
                $dt = ejlParseDateTime($g['dateTime'] ?? '');
                $ko = (int)$dt['timestamp'];
                if ($ko > 0 && ($ko - $now) <= 7200) {
                    $candidateGameIds[$gid] = $gid;
                }
            }
        }
    }

    foreach ($candidateGameIds as $gid) {
        $detail = ejlApiGet("/api/v1/games/{$gid}");
        $parsed = ejlBuildLiveMatchPayload($detail);
        if ($parsed !== null) {
            $candidates[] = $parsed;
        }
    }
}

foreach ($candidates as $m) {
    if ($m['matchState'] === 'live' || $m['matchState'] === 'pre_match') {
        if ($activeMatch === null || ($m['matchState'] === 'live' && $activeMatch['matchState'] !== 'live')) {
            $activeMatch = $m;
        }
    } elseif ($m['matchState'] === 'finished_recent' && $activeMatch === null) {
        $activeMatch = $m;
    }

    if ($m['matchState'] === 'finished' || $m['matchState'] === 'finished_recent') {
        if ($recentMatch === null || $m['kickoffTimestamp'] > $recentMatch['kickoffTimestamp']) {
            $recentMatch = $m;
        }
    }
}

if ($recentMatch === null && !empty($candidates)) {
    $recentMatch = $candidates[0];
}

$output = array(
    'status'      => 'success',
    'source'      => 'EJL OpenData API v1',
    'timestamp'   => date('c'),
    'nowUnix'     => time(),
    'isLive'      => ($activeMatch !== null && ($activeMatch['matchState'] === 'live' || $activeMatch['matchState'] === 'pre_match')),
    'activeMatch' => $activeMatch,
    'match'       => $activeMatch !== null ? $activeMatch : $recentMatch,
);

$encoded = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encoded !== false) {
    @file_put_contents($cacheFile, $encoded);
    echo $encoded;
} else {
    echo '{"status":"error","message":"JSON encoding failed"}';
}
