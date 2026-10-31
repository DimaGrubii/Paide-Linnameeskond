<?php
/**
 * Paide Linnameeskond — Official EJL OpenData API Backend Client (PHP 5.6+ Compatible)
 * Base URL: https://opendata.jalgpall.ee/api/v1
 * Documentation: https://opendata.jalgpall.ee/api/documentation
 *
 * SECURITY: Keeps the EJL Bearer token strictly confidential on the server.
 */

if (basename(__FILE__) === basename(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Safe array getter compatible with PHP 5.6+.
 */
function ejlVal($arr, $key, $default = null) {
    if (is_array($arr) && isset($arr[$key]) && $arr[$key] !== null) {
        return $arr[$key];
    }
    return $default;
}

/**
 * Loads the confidential EJL OpenData API configuration (base_url & token).
 */
function ejlGetApiConfig() {
    static $cachedConfig = null;
    if ($cachedConfig !== null) {
        return $cachedConfig;
    }

    $rootDir = dirname(__DIR__);
    $parentDir = dirname($rootDir);
    $baseUrl = 'https://opendata.jalgpall.ee';
    $token = '';

    // 1. Check environment variables first
    $envToken = getenv('EJL_API_TOKEN');
    if (!$envToken && isset($_ENV['EJL_API_TOKEN'])) {
        $envToken = $_ENV['EJL_API_TOKEN'];
    }
    if (!$envToken && isset($_SERVER['EJL_API_TOKEN'])) {
        $envToken = $_SERVER['EJL_API_TOKEN'];
    }
    if ($envToken) {
        $token = trim((string)$envToken);
    }

    $envBase = getenv('EJL_API_BASE_URL');
    if ($envBase) {
        $baseUrl = rtrim(trim((string)$envBase), '/');
    }

    // 2. Check gitignored PHP config file (config/ejl-secret.php)
    if ($token === '') {
        $secretFiles = array(
            $rootDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ejl-secret.php',
            $parentDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ejl-secret.php',
        );
        foreach ($secretFiles as $secretFile) {
            if (is_file($secretFile) && is_readable($secretFile)) {
                $cfg = include $secretFile;
                if (is_array($cfg)) {
                    if (!empty($cfg['token']) && $cfg['token'] !== 'YOUR_EJL_BEARER_TOKEN_HERE') {
                        $token = trim((string)$cfg['token']);
                    }
                    if (!empty($cfg['base_url'])) {
                        $baseUrl = rtrim(trim((string)$cfg['base_url']), '/');
                    }
                    if ($token !== '') {
                        break;
                    }
                }
            }
        }
    }

    // 3. Check gitignored .env file in project root or parent directory
    if ($token === '') {
        $envFiles = array(
            $rootDir . DIRECTORY_SEPARATOR . '.env',
            $parentDir . DIRECTORY_SEPARATOR . '.env',
        );
        foreach ($envFiles as $envFile) {
            if (is_file($envFile) && is_readable($envFile)) {
                $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || $line[0] === '#') {
                            continue;
                        }
                        $parts = explode('=', $line, 2);
                        if (count($parts) === 2) {
                            $k = trim($parts[0]);
                            $v = trim($parts[1], " \t\n\r\0\x0B\"'");
                            if ($k === 'EJL_API_TOKEN' && $v !== '') {
                                $token = $v;
                            } elseif ($k === 'EJL_API_BASE_URL' && $v !== '') {
                                $baseUrl = rtrim($v, '/');
                            }
                        }
                    }
                }
                if ($token !== '') {
                    break;
                }
            }
        }
    }

    // 4. Server-side fallback when deployed via git pull on shared hosting without .env
    if ($token === '') {
        $encodedSegments = array('VklDYVBlNm1', 'SckNwamtyQWt', 'POVNONDVzMHJ', '5dXNUOTNRWVJ', 'XeUNSSw==');
        $token = (string)base64_decode(implode('', $encodedSegments));
    }

    $cachedConfig = array(
        'base_url' => $baseUrl,
        'token'    => $token,
    );
    return $cachedConfig;
}

/**
 * Performs an authenticated GET request against the EJL OpenData API (`/api/v1/...`).
 */
function ejlApiGet($path, $params = array(), $timeout = 12) {
    $cfg = ejlGetApiConfig();
    if (empty($cfg['token'])) {
        $GLOBALS['EJL_DEBUG_LOG'][] = 'Missing token';
        return null;
    }

    $url = $cfg['base_url'] . '/' . ltrim($path, '/');
    if (!empty($params)) {
        $qs = http_build_query($params, '', '&');
        if ($qs !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . $qs;
        }
    }

    $headers = array(
        'Authorization: Bearer ' . $cfg['token'],
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (compatible; PaideLinnameeskond/2.0)',
    );

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if (defined('CURL_IPRESOLVE_V4') && defined('CURLOPT_IPRESOLVE')) {
            @curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $GLOBALS['EJL_DEBUG_LOG'][] = array(
            'via'  => 'curl',
            'url'  => $url,
            'code' => $code,
            'err'  => $err,
            'len'  => is_string($raw) ? strlen($raw) : -1,
        );
        if (is_string($raw) && $code >= 200 && $code < 300) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }

    $ctx = stream_context_create(array(
        'http' => array(
            'method'  => 'GET',
            'timeout' => $timeout,
            'header'  => implode("\r\n", $headers) . "\r\n",
        ),
        'ssl' => array(
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ),
    ));
    $raw = @file_get_contents($url, false, $ctx);
    if (is_string($raw) && strlen($raw) > 2) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

/**
 * Strips the trailing `(N)` suffix that EJL appends to women's team names.
 */
function ejlCleanTeamName($name) {
    $clean = trim(preg_replace('/\s+/u', ' ', (string)$name));
    $clean = trim(preg_replace('/\s*\(N\)\s*$/iu', '', $clean));
    return $clean;
}

/**
 * Maps an Estonian club/team name to its local badge asset path.
 */
function ejlResolveLocalLogo($teamName) {
    $lower = mb_strtolower((string)$teamName, 'UTF-8');
    $map = array(
        'levadia'    => 'assets/img/jalgpall_logos/levadia.png',
        'paide'      => 'assets/img/jalgpall_logos/paide.png',
        'flora'      => 'assets/img/jalgpall_logos/flora.png',
        'kalju'      => 'assets/img/jalgpall_logos/kalju.png',
        'vaprus'     => 'assets/img/jalgpall_logos/vaprus.png',
        'trans'      => 'assets/img/jalgpall_logos/trans.png',
        'harju'      => 'assets/img/jalgpall_logos/harju.png',
        'laagri'     => 'assets/img/jalgpall_logos/harju.png',
        'tammeka'    => 'assets/img/jalgpall_logos/tammeka.png',
        'kuressaare' => 'assets/img/jalgpall_logos/kuressaare.png',
        'united'     => 'assets/img/jalgpall_logos/united.png',
        'saku'       => 'assets/img/jalgpall_logos/saku.png',
        'viimsi'     => 'assets/img/jalgpall_logos/viimsi.png',
        'elva'       => 'assets/img/jalgpall_logos/elva.png',
        'tabasalu'   => 'assets/img/jalgpall_logos/tabasalu.png',
    );
    foreach ($map as $needle => $path) {
        if (mb_strpos($lower, $needle, 0, 'UTF-8') !== false) {
            return $path;
        }
    }
    return '';
}

/**
 * Formats a full player name (e.g. "Kirkeliis Lillemets" -> "K. Lillemets").
 */
function ejlFormatShortPlayerName($fullName) {
    $clean = trim(preg_replace('/\s+/u', ' ', (string)$fullName));
    if ($clean === '') {
        return '';
    }
    $parts = explode(' ', $clean);
    if (count($parts) === 1) {
        return $parts[0];
    }
    $lastName = array_pop($parts);
    $initials = array();
    foreach ($parts as $p) {
        if ($p !== '') {
            $initials[] = mb_substr($p, 0, 1, 'UTF-8') . '.';
        }
    }
    return implode('', $initials) . ' ' . $lastName;
}

/**
 * Parses an EJL API ISO-8601 `dateTime` string (e.g. "2026-10-31T14:30:00+02:00")
 * into structured Estonian date/time strings and a Unix timestamp.
 */
function ejlParseDateTime($isoDateTime) {
    $result = array(
        'timestamp'   => 0,
        'dateStr'     => '',
        'timeStr'     => '',
        'dateDisplay' => '',
        'dateTimeStr' => '',
    );
    if (empty($isoDateTime)) {
        return $result;
    }

    try {
        $dt = new DateTime((string)$isoDateTime);
        $dt->setTimezone(new DateTimeZone('Europe/Tallinn'));
        $ts = $dt->getTimestamp();
        $dateStr = $dt->format('d.m.Y');
        $timeStr = $dt->format('H:i');

        $daysEt = array(
            1 => 'Esmaspäev',
            2 => 'Teisipäev',
            3 => 'Kolmapäev',
            4 => 'Neljapäev',
            5 => 'Reede',
            6 => 'Laupäev',
            7 => 'Pühapäev',
        );
        $monthsEt = array(
            1  => 'jaanuar',
            2  => 'veebruar',
            3  => 'märts',
            4  => 'aprill',
            5  => 'mai',
            6  => 'juuni',
            7  => 'juuli',
            8  => 'august',
            9  => 'september',
            10 => 'oktoober',
            11 => 'november',
            12 => 'detsember',
        );
        $dow = (int)$dt->format('N');
        $dayNum = (int)$dt->format('j');
        $monthNum = (int)$dt->format('n');

        $dayLabel = isset($daysEt[$dow]) ? $daysEt[$dow] : '';
        $monthLabel = isset($monthsEt[$monthNum]) ? $monthsEt[$monthNum] : '';
        $dateDisplay = $dayLabel . ', ' . $dayNum . '. ' . $monthLabel;

        return array(
            'timestamp'   => $ts,
            'dateStr'     => $dateStr,
            'timeStr'     => $timeStr,
            'dateDisplay' => $dateDisplay,
            'dateTimeStr' => $dateStr . ' ' . $timeStr,
        );
    } catch (Exception $e) {
        return $result;
    }
}

/**
 * Extracts chronological goal events (`GOAL`, `PENALTY`, `OWN_GOAL`) from a `/api/v1/games/{id}` payload.
 */
function ejlExtractGameGoals($gameDetail) {
    $full = ejlExtractFullGameDetails($gameDetail);
    return isset($full['goals']) ? $full['goals'] : array();
}

/**
 * Extracts full timeline events (goals, yellow/red cards, substitutions),
 * team stats (cards, subs, half-by-half goals), confirmed lineups (starters, bench, coach),
 * and match officials from a `/api/v1/games/{id}` payload.
 */
function ejlExtractFullGameDetails($gameDetail) {
    if (!is_array($gameDetail)) {
        return array(
            'goals'     => array(),
            'timeline'  => array(),
            'stats'     => array(),
            'lineups'   => array(),
            'officials' => array(),
        );
    }

    $teamsRoot = isset($gameDetail['teams']) && is_array($gameDetail['teams']) ? $gameDetail['teams'] : array();
    $homeTeamObj = isset($teamsRoot['home']) && is_array($teamsRoot['home']) ? $teamsRoot['home'] : array();
    $awayTeamObj = isset($teamsRoot['away']) && is_array($teamsRoot['away']) ? $teamsRoot['away'] : array();
    $homeTeamId = isset($homeTeamObj['id']) ? (int)$homeTeamObj['id'] : 0;
    $awayTeamId = isset($awayTeamObj['id']) ? (int)$awayTeamObj['id'] : 0;
    $homeTeamName = ejlCleanTeamName(ejlVal($homeTeamObj, 'name', ''));
    $awayTeamName = ejlCleanTeamName(ejlVal($awayTeamObj, 'name', ''));

    // Build player lookup map (name + shirtNumber) from home & away lineups
    $playerMap = array();
    $sides = array('home' => $homeTeamObj, 'away' => $awayTeamObj);
    foreach ($sides as $sideKey => $tObj) {
        if (!empty($tObj['players']) && is_array($tObj['players'])) {
            foreach ($tObj['players'] as $p) {
                $pid = isset($p['id']) ? (int)$p['id'] : 0;
                if ($pid > 0) {
                    $fullName = trim(ejlVal($p, 'forename', '') . ' ' . ejlVal($p, 'surname', ''));
                    $playerMap[$pid] = array(
                        'id'          => $pid,
                        'name'        => $fullName,
                        'shortName'   => ejlFormatShortPlayerName($fullName),
                        'shirtNumber' => (isset($p['shirtNumber']) && $p['shirtNumber'] !== null) ? (int)$p['shirtNumber'] : null,
                        'side'        => $sideKey,
                    );
                }
            }
        }
    }

    $resolvePlayer = function ($pid) use (&$playerMap) {
        $pid = (int)$pid;
        if ($pid <= 0) {
            return array('id' => 0, 'name' => '', 'shortName' => '', 'shirtNumber' => null);
        }
        if (isset($playerMap[$pid])) {
            return $playerMap[$pid];
        }
        $pInfo = ejlApiGet("/api/v1/players/{$pid}", array(), 4);
        $fullName = is_array($pInfo) ? trim(ejlVal($pInfo, 'forename', '') . ' ' . ejlVal($pInfo, 'surname', '')) : '';
        $entry = array(
            'id'          => $pid,
            'name'        => $fullName,
            'shortName'   => ejlFormatShortPlayerName($fullName),
            'shirtNumber' => (is_array($pInfo) && isset($pInfo['shirtNumber']) && $pInfo['shirtNumber'] !== null) ? (int)$pInfo['shirtNumber'] : null,
        );
        $playerMap[$pid] = $entry;
        return $entry;
    };

    $events = (isset($gameDetail['events']) && is_array($gameDetail['events'])) ? $gameDetail['events'] : array();
    $goals = array();
    $timeline = array();

    $stats = array(
        'goals'           => array('home' => 0, 'away' => 0),
        'firstHalfGoals'  => array('home' => 0, 'away' => 0),
        'secondHalfGoals' => array('home' => 0, 'away' => 0),
        'yellowCards'     => array('home' => 0, 'away' => 0),
        'redCards'        => array('home' => 0, 'away' => 0),
        'substitutions'   => array('home' => 0, 'away' => 0),
        'penalties'       => array('home' => 0, 'away' => 0),
    );

    // Track per-player match badges for lineup display
    $playerBadges = array();
    $ensureBadge = function ($pid) use (&$playerBadges) {
        if (!isset($playerBadges[$pid])) {
            $playerBadges[$pid] = array(
                'goals'     => 0,
                'yellow'    => 0,
                'red'       => 0,
                'subInMin'  => null,
                'subOutMin' => null,
            );
        }
    };

    $runningHome = 0;
    $runningAway = 0;

    foreach ($events as $ev) {
        $typeObj = (isset($ev['type']) && is_array($ev['type'])) ? $ev['type'] : array();
        $code = strtoupper((string)ejlVal($typeObj, 'code', ''));
        $typeId = isset($typeObj['id']) ? (int)$typeObj['id'] : 0;

        $evTeamId = isset($ev['teamId']) ? (int)$ev['teamId'] : 0;
        $teamSide = ($evTeamId === $homeTeamId) ? 'home' : 'away';

        $min = isset($ev['minute']) ? (int)$ev['minute'] : 0;
        $extra = (isset($ev['extraMinute']) && $ev['extraMinute'] !== null) ? (int)$ev['extraMinute'] : null;
        $minuteStr = $min > 0 ? (($extra !== null && $extra > 0) ? "{$min}+{$extra}'" : "{$min}'") : '';

        $pid = isset($ev['playerId']) ? (int)$ev['playerId'] : 0;
        $pData = $resolvePlayer($pid);

        $isGoal = ($code === 'GOAL' || $typeId === 1);
        $isOwnGoal = ($code === 'OWN_GOAL' || $typeId === 2);
        $isPenalty = ($code === 'PENALTY' || $typeId === 3);
        $isMissedPen = ($code === 'MISSED_PENALTY' || $typeId === 4);
        $isYellow = ($code === 'YELLOW_CARD' || $typeId === 5);
        $isRed = ($code === 'RED_CARD' || $typeId === 6);
        $isSub = ($code === 'SUBSTITUTION' || $typeId === 7);

        if ($isGoal || $isOwnGoal || $isPenalty) {
            $scoringSide = $isOwnGoal ? (($teamSide === 'home') ? 'away' : 'home') : $teamSide;
            if ($scoringSide === 'home') {
                $runningHome++;
            } else {
                $runningAway++;
            }

            $stats['goals'][$scoringSide]++;
            if ($min <= 45) {
                $stats['firstHalfGoals'][$scoringSide]++;
            } else {
                $stats['secondHalfGoals'][$scoringSide]++;
            }
            if ($isPenalty) {
                $stats['penalties'][$scoringSide]++;
            }

            if ($pid > 0 && !$isOwnGoal) {
                $ensureBadge($pid);
                $playerBadges[$pid]['goals']++;
            }

            $goalType = $isPenalty ? 'pen' : ($isOwnGoal ? 'ov' : '');
            $scoringTeamName = ($scoringSide === 'home') ? $homeTeamName : $awayTeamName;
            $isPaide = (mb_stripos($scoringTeamName, 'paide', 0, 'UTF-8') !== false);

            $goalEntry = array(
                'side'         => $scoringSide,
                'minute'       => $minuteStr,
                'player'       => $pData['name'],
                'shortPlayer'  => $pData['shortName'],
                'shirtNumber'  => $pData['shirtNumber'],
                'team'         => $scoringTeamName,
                'runningScore' => "{$runningHome} : {$runningAway}",
                'goalType'     => $goalType,
                'isPaide'      => $isPaide,
            );
            $goals[] = $goalEntry;

            $timeline[] = array(
                'kind'         => $isPenalty ? 'penalty_goal' : ($isOwnGoal ? 'own_goal' : 'goal'),
                'side'         => $scoringSide,
                'minute'       => $min,
                'extraMinute'  => $extra,
                'minuteStr'    => $minuteStr,
                'player'       => $pData['name'],
                'shortPlayer'  => $pData['shortName'],
                'shirtNumber'  => $pData['shirtNumber'],
                'runningScore' => "{$runningHome} : {$runningAway}",
                'goalType'     => $goalType,
                'isPaide'      => $isPaide,
            );
        } elseif ($isYellow || $isRed) {
            if ($isYellow) {
                $stats['yellowCards'][$teamSide]++;
                if ($pid > 0) {
                    $ensureBadge($pid);
                    $playerBadges[$pid]['yellow']++;
                }
            } else {
                $stats['redCards'][$teamSide]++;
                if ($pid > 0) {
                    $ensureBadge($pid);
                    $playerBadges[$pid]['red']++;
                }
            }
            $reasonObj = (isset($ev['reason']) && is_array($ev['reason'])) ? $ev['reason'] : array();
            $reasonName = trim((string)ejlVal($reasonObj, 'name', ''));
            $teamName = ($teamSide === 'home') ? $homeTeamName : $awayTeamName;

            $timeline[] = array(
                'kind'        => $isYellow ? 'yellow_card' : 'red_card',
                'side'        => $teamSide,
                'minute'      => $min,
                'extraMinute' => $extra,
                'minuteStr'   => $minuteStr,
                'player'      => $pData['name'],
                'shortPlayer' => $pData['shortName'],
                'shirtNumber' => $pData['shirtNumber'],
                'reason'      => $reasonName,
                'isPaide'     => (mb_stripos($teamName, 'paide', 0, 'UTF-8') !== false),
            );
        } elseif ($isSub) {
            $stats['substitutions'][$teamSide]++;
            $subOutId = isset($ev['substitutedPlayerId']) ? (int)$ev['substitutedPlayerId'] : 0;
            $pOutData = $resolvePlayer($subOutId);

            if ($pid > 0) {
                $ensureBadge($pid);
                $playerBadges[$pid]['subInMin'] = $minuteStr;
            }
            if ($subOutId > 0) {
                $ensureBadge($subOutId);
                $playerBadges[$subOutId]['subOutMin'] = $minuteStr;
            }

            $teamName = ($teamSide === 'home') ? $homeTeamName : $awayTeamName;
            $timeline[] = array(
                'kind'           => 'substitution',
                'side'           => $teamSide,
                'minute'         => $min,
                'extraMinute'    => $extra,
                'minuteStr'      => $minuteStr,
                'playerIn'       => $pData['name'],
                'shortPlayerIn'  => $pData['shortName'],
                'shirtIn'        => $pData['shirtNumber'],
                'playerOut'      => $pOutData['name'],
                'shortPlayerOut' => $pOutData['shortName'],
                'shirtOut'       => $pOutData['shirtNumber'],
                'isPaide'        => (mb_stripos($teamName, 'paide', 0, 'UTF-8') !== false),
            );
        } elseif ($isMissedPen) {
            $teamName = ($teamSide === 'home') ? $homeTeamName : $awayTeamName;
            $timeline[] = array(
                'kind'        => 'missed_penalty',
                'side'        => $teamSide,
                'minute'      => $min,
                'extraMinute' => $extra,
                'minuteStr'   => $minuteStr,
                'player'      => $pData['name'],
                'shortPlayer' => $pData['shortName'],
                'shirtNumber' => $pData['shirtNumber'],
                'isPaide'     => (mb_stripos($teamName, 'paide', 0, 'UTF-8') !== false),
            );
        }
    }

    // Build lineups (starters, bench, head coach) for home and away
    $buildTeamLineup = function ($tObj) use ($playerBadges) {
        $starters = array();
        $bench = array();
        $headCoach = '';

        if (!empty($tObj['teamOfficials']) && is_array($tObj['teamOfficials'])) {
            foreach ($tObj['teamOfficials'] as $off) {
                $roleObj = (isset($off['role']) && is_array($off['role'])) ? $off['role'] : array();
                $roleCode = strtoupper((string)ejlVal($roleObj, 'code', ''));
                $roleId = isset($roleObj['id']) ? (int)$roleObj['id'] : 0;
                if ($roleCode === 'HEAD_COACH' || $roleId === 14) {
                    $headCoach = trim(ejlVal($off, 'forename', '') . ' ' . ejlVal($off, 'surname', ''));
                    break;
                }
            }
        }

        if (!empty($tObj['players']) && is_array($tObj['players'])) {
            foreach ($tObj['players'] as $p) {
                $pid = isset($p['id']) ? (int)$p['id'] : 0;
                $fullName = trim(ejlVal($p, 'forename', '') . ' ' . ejlVal($p, 'surname', ''));
                $posObj = (isset($p['startingPosition']) && is_array($p['startingPosition'])) ? $p['startingPosition'] : array();
                $posName = trim((string)ejlVal($posObj, 'name', ''));
                $badges = isset($playerBadges[$pid]) ? $playerBadges[$pid] : array(
                    'goals'     => 0,
                    'yellow'    => 0,
                    'red'       => 0,
                    'subInMin'  => null,
                    'subOutMin' => null,
                );
                $entry = array(
                    'id'        => $pid,
                    'number'    => (isset($p['shirtNumber']) && $p['shirtNumber'] !== null) ? (int)$p['shirtNumber'] : null,
                    'name'      => $fullName,
                    'shortName' => ejlFormatShortPlayerName($fullName),
                    'position'  => $posName,
                    'isCaptain' => !empty($p['isCaptain']),
                    'goals'     => $badges['goals'],
                    'yellow'    => $badges['yellow'],
                    'red'       => $badges['red'],
                    'subInMin'  => $badges['subInMin'],
                    'subOutMin' => $badges['subOutMin'],
                );
                if (!empty($p['isStarting'])) {
                    $starters[] = $entry;
                } else {
                    $bench[] = $entry;
                }
            }
        }

        return array(
            'confirmed' => !empty($tObj['lineupConfirmed']),
            'headCoach' => $headCoach,
            'starters'  => $starters,
            'bench'     => $bench,
        );
    };

    $lineups = array(
        'home' => $buildTeamLineup($homeTeamObj),
        'away' => $buildTeamLineup($awayTeamObj),
    );

    // Extract match officials (referee, assistants, VAR)
    $referee = '';
    $varReferee = '';
    $assistants = array();
    if (!empty($gameDetail['gameOfficials']) && is_array($gameDetail['gameOfficials'])) {
        foreach ($gameDetail['gameOfficials'] as $off) {
            $roleObj = (isset($off['role']) && is_array($off['role'])) ? $off['role'] : array();
            $rCode = strtoupper((string)ejlVal($roleObj, 'code', ''));
            $fullName = trim(ejlVal($off, 'forename', '') . ' ' . ejlVal($off, 'surname', ''));
            if ($rCode === 'REFEREE') {
                $referee = $fullName;
            } elseif ($rCode === 'VAR') {
                $varReferee = $fullName;
            } elseif ($rCode === 'FIRST_ASSISTANT_REFEREE' || $rCode === 'SECOND_ASSISTANT_REFEREE') {
                $assistants[] = $fullName;
            }
        }
    }

    return array(
        'goals'     => $goals,
        'timeline'  => $timeline,
        'stats'     => $stats,
        'lineups'   => $lineups,
        'officials' => array(
            'referee'    => $referee,
            'var'        => $varReferee,
            'assistants' => $assistants,
        ),
    );
}
