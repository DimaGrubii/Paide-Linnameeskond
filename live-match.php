<?php
/**
 * Paide Linnameeskond — Live Match & Protocol Sync Endpoint (jalgpall.ee)
 *
 * Compatible with PHP 5.3+ through PHP 8.x (Zone.ee).
 * Automatically detects and parses live/matchday protocols for both:
 *  - Paide Linnameeskond (Men's First Team)
 *  - Paide Linnanaiskond (Women's First Team)
 * Caches responses for 20 seconds to ensure high speed and low upstream load.
 */

@ini_set('display_errors', '0');
error_reporting(0);
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Europe/Tallinn');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function fetchUrlContent($url, $timeout = 6) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PaideLinnameeskondLiveSync/1.0; +https://paidelinnameeskond.ee)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($html && $code >= 200 && $code < 400) {
            return $html;
        }
    }

    $ctx = stream_context_create(array(
        'http' => array(
            'method'  => 'GET',
            'timeout' => $timeout,
            'header'  => "User-Agent: Mozilla/5.0 (compatible; PaideLinnameeskondLiveSync/1.0)\r\n"
        ),
        'ssl' => array(
            'verify_peer'      => false,
            'verify_peer_name' => false
        )
    ));
    $html = @file_get_contents($url, false, $ctx);
    return $html ? $html : null;
}

function cleanText($str) {
    $str = html_entity_decode(strip_tags((string) $str), ENT_QUOTES, 'UTF-8');
    $str = preg_replace('/\s+/u', ' ', $str);
    return trim($str);
}

function formatShortPlayerName($fullName) {
    $fullName = cleanText($fullName);
    if ($fullName === '') return '';
    $parts = preg_split('/\s+/u', $fullName);
    if (count($parts) <= 1) return $fullName;
    $surname = array_pop($parts);
    $first = $parts[0];
    $initial = function_exists('mb_substr') ? mb_substr($first, 0, 1, 'UTF-8') : substr($first, 0, 1);
    return $initial . '. ' . $surname;
}

function cleanTeamDisplayName($rawName) {
    $name = cleanText($rawName);
    $name = preg_replace('/\s*\(N\)\s*$/i', '', $name);
    return trim($name);
}

/**
 * Returns true ONLY if the team name is Paide's male team (Paide Linnameeskond)
 * or female team (Paide Linnanaiskond).
 */
function isPaideFirstTeam($teamName) {
    $clean = cleanTeamDisplayName($teamName);
    if ($clean === '') return false;
    return (bool) preg_match('/^Paide\s+(Linnameeskond|Linnanaiskond|LM|Naiskond)$/iu', $clean);
}

function isPaideMatch($homeTeam, $awayTeam) {
    return isPaideFirstTeam($homeTeam) || isPaideFirstTeam($awayTeam);
}

function parseProtocolHtml($html, $protocolId) {
    if (!$html || strpos($html, 'spacer') === false) {
        return null;
    }

    // 1. Extract Home & Away team names inside .head .teams
    $homeTeam = '';
    $awayTeam = '';
    if (preg_match('/<div class="teams">(.*?)<div class="info">/s', $html, $teamsBlockMatch)) {
        $teamsBlock = $teamsBlockMatch[1];
        if (preg_match_all('/<div class="team">.*?<p>\s*<a[^>]*>(.*?)<\/a>\s*<\/p>/s', $teamsBlock, $tMatches)) {
            if (isset($tMatches[1][0])) $homeTeam = cleanTeamDisplayName($tMatches[1][0]);
            if (isset($tMatches[1][1])) $awayTeam = cleanTeamDisplayName($tMatches[1][1]);
        }
    }

    if ($homeTeam === '' || $awayTeam === '') {
        return null;
    }

    // Strictly ensure at least one team is Paide Linnameeskond (Men) or Paide Linnanaiskond (Women)
    if (!isPaideMatch($homeTeam, $awayTeam)) {
        return null;
    }

    $isWomen = (
        stripos($teamsBlock, '(N)') !== false ||
        stripos($homeTeam, 'Linnanaiskond') !== false ||
        stripos($awayTeam, 'Linnanaiskond') !== false ||
        stripos($homeTeam, 'Naiskond') !== false ||
        stripos($awayTeam, 'Naiskond') !== false
    );

    // 2. Extract halftime & main score + status label (Hetkeseis vs Lõppseis)
    $htScore = '';
    if (preg_match('/<p class="p-sm">\s*([0-9]+\s*-\s*[0-9]+)\s*<small>/s', $html, $htMatch)) {
        $htScore = preg_replace('/\s+/', '', $htMatch[1]);
        $htScore = str_replace('-', ':', $htScore);
    }

    $rawMainScore = '-';
    $statusLabel = 'Hetkeseis';
    if (preg_match('/<p class="p-md">\s*(.*?)\s*<small>(.*?)<\/small>\s*<\/p>/s', $html, $mdMatch)) {
        $rawMainScore = cleanText($mdMatch[1]);
        $statusLabel = cleanText($mdMatch[2]);
    }

    $scoreHome = null;
    $scoreAway = null;
    if (preg_match('/([0-9]+)\s*-\s*([0-9]+)/', $rawMainScore, $sParts)) {
        $scoreHome = (int) $sParts[1];
        $scoreAway = (int) $sParts[2];
    }

    // 3. Extract metadata from .head .info ul
    $competition = $isWomen ? 'Naiste Meistriliiga' : 'A. Le Coq Premium liiga';
    $round = '';
    $dateStr = '';
    $timeStr = '';
    $venue = '';
    $attendance = null;

    if (preg_match('/<div class="info">\s*<ul>(.*?)<\/ul>\s*<\/div>/s', $html, $infoBlockMatch)) {
        $infoHtml = $infoBlockMatch[1];
        if (preg_match('/<li class="type">(.*?)<\/li>/s', $infoHtml, $m)) {
            $compText = cleanText($m[1]);
            if ($compText !== '') $competition = $compText;
        }
        if (stripos($competition, 'Naiste') !== false) {
            $isWomen = true;
        }
        if (preg_match_all('/<li(?:\s+class="([^"]*)")?>(.*?)<\/li>/s', $infoHtml, $liMatches, PREG_SET_ORDER)) {
            foreach ($liMatches as $li) {
                $cls = isset($li[1]) ? $li[1] : '';
                $txt = cleanText($li[2]);
                if ($cls === '' && stripos($txt, 'voor') !== false) {
                    $round = $txt;
                } elseif (strpos($cls, 'date') !== false) {
                    if (preg_match('/([0-9]{2}\.[0-9]{2}\.[0-9]{4})/', $txt, $dm)) {
                        $dateStr = $dm[1];
                    }
                    if (preg_match('/([0-9]{1,2}:[0-9]{2})/', $txt, $tm)) {
                        $timeStr = $tm[1];
                    }
                } elseif (strpos($cls, 'location') !== false) {
                    $venue = $txt;
                } elseif (strpos($cls, 'group') !== false) {
                    if (preg_match('/([0-9]+)/', $txt, $am)) {
                        $attendance = (int) $am[1];
                    }
                }
            }
        }
    }

    // 4. Parse Kickoff Timestamp (Europe/Tallinn)
    $kickoffTs = null;
    if ($dateStr !== '' && $timeStr !== '') {
        $dtParts = explode('.', $dateStr);
        if (count($dtParts) === 3) {
            $isoStr = $dtParts[2] . '-' . $dtParts[1] . '-' . $dtParts[0] . ' ' . $timeStr . ':00';
            $kickoffTs = strtotime($isoStr);
        }
    }

    // 5. Extract ALL Goals from .goals-list (bounded by the next <div class="block section so away goals never cut off later goals)
    $goals = array();
    $goalsPos = strpos($html, '<div class="goals-list">');
    if ($goalsPos !== false) {
        $afterGoals = substr($html, $goalsPos + strlen('<div class="goals-list">'));
        $blockParts = preg_split('/<div class="block\b/i', $afterGoals, 2);
        $goalsBlock = $blockParts[0];
        $chunks = explode('<div class="goal-single">', $goalsBlock);
        array_shift($chunks);

        foreach ($chunks as $chunk) {
            $runningScore = '';
            if (preg_match('/<div class="score">\s*([0-9]+\s*-\s*[0-9]+)\s*<\/div>/s', $chunk, $scM)) {
                $parts = explode('-', $scM[1]);
                $runningScore = trim($parts[0]) . ' : ' . trim($parts[1]);
                // Keep scoreHome / scoreAway in sync with latest goal if needed
                $gHome = (int) trim($parts[0]);
                $gAway = (int) trim($parts[1]);
                if ($scoreHome === null || $gHome > $scoreHome) $scoreHome = $gHome;
                if ($scoreAway === null || $gAway > $scoreAway) $scoreAway = $gAway;
            }

            // Split around <div class="score"> to distinguish Home (left) vs Away (right) goalscorer
            $halves = preg_split('/<div class="score">.*?<\/div>/s', $chunk);
            $leftHalf = isset($halves[0]) ? $halves[0] : '';
            $rightHalf = isset($halves[1]) ? $halves[1] : '';

            $side = (strpos($leftHalf, 'player empty') === false && strpos($leftHalf, 'class="time"') !== false) ? 'home' : 'away';
            $activeHalf = ($side === 'home') ? $leftHalf : $rightHalf;

            $minute = '';
            if (preg_match('/<span class="time">\s*(.*?)\s*<\/span>/s', $activeHalf, $minM)) {
                $minute = cleanText($minM[1]);
                $minute = str_replace(array('′', '´', '`'), "'", $minute);
                if ($minute !== '' && substr($minute, -1) !== "'") {
                    $minute .= "'";
                }
            }

            $playerName = '';
            if (preg_match('/<a[^>]*>(.*?)<\/a>/s', $activeHalf, $plM)) {
                $playerName = cleanText($plM[1]);
            }

            $goalStatus = '';
            if (preg_match('/<div class="status">(.*?)<\/div>/s', $activeHalf, $stM)) {
                $goalStatus = cleanText($stM[1]);
            }

            if ($playerName !== '' || $minute !== '') {
                $goals[] = array(
                    'minute'    => $minute,
                    'player'    => $playerName,
                    'shortName' => formatShortPlayerName($playerName),
                    'score'     => $runningScore,
                    'side'      => $side,
                    'status'    => $goalStatus
                );
            }
        }
    }

    $hasLineups = (strpos($html, 'Põhikoosseis') !== false);
    $now = time();

    // Determine match state: 'live', 'prematch', 'finished'
    $isFinished = (stripos($statusLabel, 'Lõppseis') !== false);
    $state = 'upcoming';

    if ($isFinished) {
        $state = 'finished';
    } elseif ($kickoffTs !== null) {
        if ($now >= $kickoffTs && ($now - $kickoffTs) <= 9600) {
            $state = 'live';
            if ($scoreHome === null) $scoreHome = 0;
            if ($scoreAway === null) $scoreAway = 0;
        } elseif ($now < $kickoffTs && ($kickoffTs - $now) <= 10800) {
            // Within 3 hours before kickoff on matchday
            $state = 'prematch';
            if ($scoreHome === null) $scoreHome = 0;
            if ($scoreAway === null) $scoreAway = 0;
        } elseif ($scoreHome !== null && stripos($statusLabel, 'Hetkeseis') !== false && ($now - $kickoffTs) <= 14400) {
            $state = 'live';
        }
    }

    return array(
        'protocolId'       => (string) $protocolId,
        'protocolUrl'      => 'https://jalgpall.ee/voistlused/protocol/' . $protocolId,
        'teamType'         => $isWomen ? 'women' : 'men',
        'state'            => $state,
        'statusLabel'      => $statusLabel,
        'hasLineups'       => $hasLineups,
        'competition'      => $competition,
        'round'            => $round,
        'dateStr'          => $dateStr,
        'timeStr'          => $timeStr,
        'kickoffTimestamp' => $kickoffTs,
        'venue'            => $venue,
        'attendance'       => $attendance,
        'homeTeam'         => $homeTeam,
        'awayTeam'         => $awayTeam,
        'scoreHome'        => $scoreHome,
        'scoreAway'        => $scoreAway,
        'htScore'          => $htScore,
        'goals'            => $goals
    );
}

/**
 * Finds candidate protocol IDs from EJL league pages for Paide matches
 */
function discoverPaideProtocols() {
    $urls = array(
        'women' => 'https://jalgpall.ee/voistlused/naised/63/naiste-meistriliiga',
        'men'   => 'https://jalgpall.ee/voistlused/52/premium-liiga'
    );

    $candidates = array();
    $todayStr = date('d.m.Y');
    $yesterdayStr = date('d.m.Y', time() - 86400);

    foreach ($urls as $type => $url) {
        $html = fetchUrlContent($url, 5);
        if (!$html) continue;

        $items = explode('<div class="event-single-small">', $html);
        array_shift($items);

        foreach ($items as $rawItem) {
            // Bound strictly to the single match <li> item so the last item never bleeds into the league table
            $liParts = explode('</li>', $rawItem, 2);
            $item = $liParts[0];

            if (stripos($item, 'Paide') === false) continue;

            // Verify that at least one of the two teams in this match card is Paide's male or female team
            $hasPaideTeam = false;
            if (preg_match_all('/<p class="(?:left|right)">.*?<a[^>]*>(.*?)<\/a>/s', $item, $teamLinks)) {
                foreach ($teamLinks[1] as $tName) {
                    if (isPaideFirstTeam($tName)) {
                        $hasPaideTeam = true;
                        break;
                    }
                }
            }
            if (!$hasPaideTeam) continue;

            if (preg_match('/\/voistlused\/protocol\/([0-9]+)/', $item, $pm)) {
                $pid = $pm[1];
                $isToday = (strpos($item, $todayStr) !== false);
                $isYesterday = (strpos($item, $yesterdayStr) !== false);
                $candidates[$pid] = array(
                    'protocolId' => $pid,
                    'teamType'   => $type,
                    'priority'   => $isToday ? 100 : ($isYesterday ? 50 : 10)
                );
            }
        }
    }

    return $candidates;
}

// ============================================================================
// MAIN EXECUTION WITH 20-SECOND CACHE
// ============================================================================
// Pin the 0-1 KUR vs PLM match (protocol 143893) by default unless ?auto=1 or another ?protocol_id= is specified
$defaultPinnedProtocol = (isset($_GET['auto']) && $_GET['auto'] === '1') ? '' : '143893';
$requestedProtocol = isset($_GET['protocol_id'])
    ? preg_replace('/[^0-9]/', '', $_GET['protocol_id'])
    : $defaultPinnedProtocol;
$forceRefresh = isset($_GET['force']) && $_GET['force'] === '1';

$cacheKey = $requestedProtocol !== '' ? 'paide_live_proto_v5_' . $requestedProtocol : 'paide_live_auto_v5';
$cacheFile = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . $cacheKey . '.json';
$cacheTtl = 15; // 15 seconds cache

if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    $cachedJson = @file_get_contents($cacheFile);
    if ($cachedJson) {
        $decoded = @json_decode($cachedJson, true);
        if (is_array($decoded)) {
            $decoded['serverTime'] = time();
            $decoded['cached'] = true;
            echo json_encode($decoded);
            exit;
        }
    }
}

$activeMatch = null;
$recentMatch = null;

if ($requestedProtocol !== '') {
    $pHtml = fetchUrlContent('https://jalgpall.ee/voistlused/protocol/' . $requestedProtocol, 6);
    if ($pHtml) {
        $activeMatch = parseProtocolHtml($pHtml, $requestedProtocol);
    }
} else {
    $candidates = discoverPaideProtocols();
    // Sort by priority descending, then protocolId descending
    uasort($candidates, function ($a, $b) {
        if ($a['priority'] !== $b['priority']) {
            return $b['priority'] - $a['priority'];
        }
        return (int) $b['protocolId'] - (int) $a['protocolId'];
    });

    // Check top candidates (up to 4 most relevant Paide protocols)
    $checked = 0;
    foreach ($candidates as $cand) {
        if ($checked >= 4) break;
        $checked++;
        $pHtml = fetchUrlContent('https://jalgpall.ee/voistlused/protocol/' . $cand['protocolId'], 5);
        if (!$pHtml) continue;

        $parsed = parseProtocolHtml($pHtml, $cand['protocolId']);
        if (!$parsed) continue;

        if ($parsed['state'] === 'live' || $parsed['state'] === 'prematch') {
            $activeMatch = $parsed;
            break;
        }

        if ($parsed['state'] === 'finished') {
            // If finished within the last 18 hours, keep as active post-match display
            if ($parsed['kickoffTimestamp'] && (time() - $parsed['kickoffTimestamp']) <= 64800) {
                if (!$activeMatch) {
                    $activeMatch = $parsed;
                }
            }
            if (!$recentMatch) {
                $recentMatch = $parsed;
            }
        }
    }
}

$response = array(
    'ok'          => true,
    'active'      => ($activeMatch !== null),
    'serverTime'  => time(),
    'cached'      => false,
    'match'       => $activeMatch ? $activeMatch : $recentMatch,
    'activeMatch' => $activeMatch,
    'recentMatch' => $recentMatch
);

$jsonOut = json_encode($response);
@file_put_contents($cacheFile, $jsonOut);
echo $jsonOut;
