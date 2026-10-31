<?php
/**
 * Paide Linnameeskond — Official EJL OpenData API Backend Client
 * Base URL: https://opendata.jalgpall.ee/api/v1
 * Documentation: https://opendata.jalgpall.ee/api/documentation
 *
 * SECURITY: Keeps the EJL Bearer token strictly confidential on the server.
 * Loads credentials from environment variables, `config/ejl-secret.php`, or `.env`.
 */

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Forbidden');
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

    $cachedConfig = array(
        'base_url' => $baseUrl,
        'token'    => $token,
    );
    return $cachedConfig;
}

/**
 * Performs an authenticated GET request against the EJL OpenData API (`/api/v1/...`).
 *
 * @param string $path   API path, e.g. '/api/v1/competitions/52'
 * @param array  $params Optional query string parameters
 * @param int    $timeout Request timeout in seconds
 * @return array|null    Decoded JSON associative array or null on failure
 */
function ejlApiGet($path, $params = array(), $timeout = 12) {
    $cfg = ejlGetApiConfig();
    if (empty($cfg['token'])) {
        return null;
    }

    $url = $cfg['base_url'] . '/' . ltrim($path, '/');
    if (!empty($params)) {
        $qs = http_build_query($params);
        if ($qs !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . $qs;
        }
    }

    $headers = array(
        'Authorization: Bearer ' . $cfg['token'],
        'Accept: application/json',
        'User-Agent: PaideLinnameeskond-OfficialWebsite/2.0',
    );

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => $headers,
        ));
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
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
            'verify_peer'      => true,
            'verify_peer_name' => true,
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

        $dateDisplay = ($daysEt[$dow] ?? '') . ', ' . $dayNum . '. ' . ($monthsEt[$monthNum] ?? '');

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
    if (!is_array($gameDetail)) {
        return array();
    }

    $homeTeamObj = $gameDetail['teams']['home'] ?? array();
    $awayTeamObj = $gameDetail['teams']['away'] ?? array();
    $homeTeamId = isset($homeTeamObj['id']) ? (int)$homeTeamObj['id'] : 0;
    $awayTeamId = isset($awayTeamObj['id']) ? (int)$awayTeamObj['id'] : 0;
    $homeTeamName = ejlCleanTeamName($homeTeamObj['name'] ?? '');
    $awayTeamName = ejlCleanTeamName($awayTeamObj['name'] ?? '');

    // Build player lookup map from home & away lineups
    $playerMap = array();
    foreach (array($homeTeamObj, $awayTeamObj) as $tObj) {
        if (!empty($tObj['players']) && is_array($tObj['players'])) {
            foreach ($tObj['players'] as $p) {
                $pid = isset($p['id']) ? (int)$p['id'] : 0;
                if ($pid > 0) {
                    $fullName = trim(($p['forename'] ?? '') . ' ' . ($p['surname'] ?? ''));
                    $playerMap[$pid] = $fullName;
                }
            }
        }
    }

    $events = isset($gameDetail['events']) && is_array($gameDetail['events']) ? $gameDetail['events'] : array();
    $goals = array();
    $runningHome = 0;
    $runningAway = 0;

    foreach ($events as $ev) {
        $typeObj = $ev['type'] ?? array();
        $code = strtoupper((string)($typeObj['code'] ?? ''));
        $typeId = isset($typeObj['id']) ? (int)$typeObj['id'] : 0;

        $isGoal = ($code === 'GOAL' || $typeId === 1);
        $isPenalty = ($code === 'PENALTY' || $typeId === 3);
        $isOwnGoal = ($code === 'OWN_GOAL' || $typeId === 2);

        if (!$isGoal && !$isPenalty && !$isOwnGoal) {
            continue;
        }

        $evTeamId = isset($ev['teamId']) ? (int)$ev['teamId'] : 0;
        if ($isOwnGoal) {
            // Own goal: teamId is the player's own team, so the other team gets the goal
            $side = ($evTeamId === $homeTeamId) ? 'away' : 'home';
        } else {
            $side = ($evTeamId === $homeTeamId) ? 'home' : 'away';
        }

        if ($side === 'home') {
            $runningHome++;
        } else {
            $runningAway++;
        }

        $min = isset($ev['minute']) ? (int)$ev['minute'] : 0;
        $extra = isset($ev['extraMinute']) && $ev['extraMinute'] !== null ? (int)$ev['extraMinute'] : 0;
        $minuteStr = $min > 0 ? ($extra > 0 ? "{$min}+{$extra}'" : "{$min}'") : '';

        $pid = isset($ev['playerId']) ? (int)$ev['playerId'] : 0;
        $playerName = $playerMap[$pid] ?? '';
        if ($playerName === '' && $pid > 0) {
            // Optional fallback lookup if player wasn't in the lineup array
            $pInfo = ejlApiGet("/api/v1/players/{$pid}", array(), 5);
            if (is_array($pInfo)) {
                $playerName = trim(($pInfo['forename'] ?? '') . ' ' . ($pInfo['surname'] ?? ''));
                if ($playerName !== '') {
                    $playerMap[$pid] = $playerName;
                }
            }
        }

        $goalType = '';
        if ($isPenalty) {
            $goalType = 'pen';
        } elseif ($isOwnGoal) {
            $goalType = 'ov';
        }

        $scoringTeamName = ($side === 'home') ? $homeTeamName : $awayTeamName;
        $isPaide = (mb_stripos($scoringTeamName, 'paide', 0, 'UTF-8') !== false);

        $goals[] = array(
            'side'         => $side,
            'minute'       => $minuteStr,
            'player'       => $playerName,
            'shortPlayer'  => ejlFormatShortPlayerName($playerName),
            'team'         => $scoringTeamName,
            'runningScore' => "{$runningHome} : {$runningAway}",
            'goalType'     => $goalType,
            'isPaide'      => $isPaide,
        );
    }

    return $goals;
}
