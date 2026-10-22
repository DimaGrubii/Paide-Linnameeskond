<?php
/**
 * Paide Linnameeskond — Server-Side EJL Standings & Calendar Sync (Zone.ee)
 *
 * Runs directly on Zone.ee (Estonian IP) to fetch official standings and
 * full-season match calendars from jalgpall.ee for both:
 *  - A. Le Coq Premium Liiga (competition 52, 36 rounds)
 *  - Naiste Meistriliiga (competition 63, 25 rounds)
 *
 * Automatically updates data/jalgpall-data.json in place while preserving
 * curated squad, coaching staff, and match protocol details.
 */

@ini_set('display_errors', '0');
error_reporting(0);
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Europe/Tallinn');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function ejlFetchUrl($url, $timeout = 10) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: et,en;q=0.9'
        ));
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
            'header'  => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36\r\nAccept-Language: et,en;q=0.9\r\n"
        ),
        'ssl' => array(
            'verify_peer'      => false,
            'verify_peer_name' => false
        )
    ));
    $html = @file_get_contents($url, false, $ctx);
    return $html ? $html : null;
}

function ejlCleanText($str) {
    $str = html_entity_decode(strip_tags((string) $str), ENT_QUOTES, 'UTF-8');
    $str = preg_replace('/\s+/u', ' ', $str);
    return trim($str);
}

function ejlExtractSeasonHistory($html, $totalRounds, $cleanWomen = false) {
    if (!$html) return array();
    $blocks = preg_split('/<div class="block block-01">/i', $html);
    if (!$blocks || count($blocks) <= 1) return array();
    array_shift($blocks);

    $teamRounds = array();
    foreach ($blocks as $b) {
        if (!preg_match('/<span class="tag">\s*(\d+)\.\s*voor\s*<\/span>/i', $b, $tagM)) {
            continue;
        }
        $rNum = (int) $tagM[1];
        $events = preg_split('/<div class="event-single"/i', $b);
        if (!$events || count($events) <= 1) continue;
        array_shift($events);

        foreach ($events as $ev) {
            $dateStr = '';
            if (preg_match('/<p class="title">\s*([0-9]{2}\.[0-9]{2}\.[0-9]{4}[^<]*)<\/p>/i', $ev, $dateM)) {
                $dateStr = ejlCleanText($dateM[1]);
            }

            $teamsM = array();
            if (preg_match_all('/<div class="team">.*?<a[^>]*>(.*?)<\/a>/is', $ev, $m1) && count($m1[1]) >= 2) {
                $teamsM = $m1[1];
            } elseif (preg_match_all('/<div class="team">\s*<p>(.*?)<\/p>/is', $ev, $m2) && count($m2[1]) >= 2) {
                $teamsM = $m2[1];
            }

            if (count($teamsM) >= 2) {
                $home = ejlCleanText($teamsM[0]);
                $away = ejlCleanText($teamsM[1]);
                if ($cleanWomen) {
                    $home = trim(str_replace(' (N)', '', $home));
                    $away = trim(str_replace(' (N)', '', $away));
                }

                $scoreClean = '';
                if (preg_match('/<div class="result">.*?<p>(.*?)<\/p>/is', $ev, $resM)) {
                    $scoreClean = ejlCleanText($resM[1]);
                }

                if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $scoreClean, $mScore)) {
                    $hg = (int) $mScore[1];
                    $ag = (int) $mScore[2];
                    $hType = ($hg > $ag) ? 'win' : (($hg < $ag) ? 'loss' : 'draw');
                    $aType = ($ag > $hg) ? 'win' : (($ag < $hg) ? 'loss' : 'draw');
                    $hRes = ($hType === 'win' || $hType === 'draw') ? 'V' : 'K';
                    $aRes = ($aType === 'win' || $aType === 'draw') ? 'V' : 'K';
                    $hScoreStr = $hg . ':' . $ag . ' vs ' . $away;
                    $aScoreStr = $ag . ':' . $hg . ' vs ' . $home;
                    $matchScore = $hg . ':' . $ag;
                } else {
                    $hType = 'upcoming';
                    $aType = 'upcoming';
                    $hRes = '-';
                    $aRes = '-';
                    $hScoreStr = 'vs ' . $away;
                    $aScoreStr = 'vs ' . $home;
                    $matchScore = '-';
                }

                if (!isset($teamRounds[$home])) $teamRounds[$home] = array();
                if (!isset($teamRounds[$away])) $teamRounds[$away] = array();

                $teamRounds[$home][$rNum] = array(
                    'round'      => $rNum,
                    'type'       => $hType,
                    'result'     => $hRes,
                    'score'      => $hScoreStr,
                    'matchScore' => $matchScore,
                    'homeTeam'   => $home,
                    'awayTeam'   => $away,
                    'opponent'   => $away,
                    'date'       => $dateStr,
                    'isHome'     => true
                );

                $teamRounds[$away][$rNum] = array(
                    'round'      => $rNum,
                    'type'       => $aType,
                    'result'     => $aRes,
                    'score'      => $aScoreStr,
                    'matchScore' => $matchScore,
                    'homeTeam'   => $home,
                    'awayTeam'   => $away,
                    'opponent'   => $home,
                    'date'       => $dateStr,
                    'isHome'     => false
                );
            }
        }
    }

    $result = array();
    foreach ($teamRounds as $team => $rmap) {
        $arr = array();
        for ($r = 1; $r <= $totalRounds; $r++) {
            if (isset($rmap[$r])) {
                $arr[] = $rmap[$r];
            } else {
                $arr[] = array(
                    'round'      => $r,
                    'type'       => 'upcoming',
                    'result'     => '-',
                    'score'      => '',
                    'matchScore' => '-',
                    'homeTeam'   => '',
                    'awayTeam'   => '',
                    'opponent'   => '',
                    'date'       => '',
                    'isHome'     => true
                );
            }
        }
        $result[$team] = $arr;
    }

    return $result;
}

function ejlMatchSortTimestamp($matchItem) {
    $dStr = isset($matchItem['date']) ? $matchItem['date'] : '';
    if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{1,2}):(\d{2}))?/', $dStr, $dm)) {
        $day = (int) $dm[1];
        $month = (int) $dm[2];
        $year = (int) $dm[3];
        $hour = isset($dm[4]) && $dm[4] !== '' ? (int) $dm[4] : 0;
        $min = isset($dm[5]) && $dm[5] !== '' ? (int) $dm[5] : 0;
        return mktime($hour, $min, 0, $month, $day, $year);
    }
    return 0;
}

function ejlComputeRecentForm($seasonHistory, $fallbackForm = array()) {
    if (empty($seasonHistory) || !is_array($seasonHistory)) {
        return $fallbackForm;
    }
    $played = array();
    foreach ($seasonHistory as $m) {
        if (isset($m['type']) && in_array($m['type'], array('win', 'draw', 'loss'), true)) {
            $played[] = $m;
        }
    }
    if (empty($played)) {
        return $fallbackForm;
    }

    usort($played, function ($a, $b) {
        $ta = ejlMatchSortTimestamp($a);
        $tb = ejlMatchSortTimestamp($b);
        if ($ta !== $tb) {
            return $ta - $tb;
        }
        $ra = isset($a['round']) ? (int) $a['round'] : 0;
        $rb = isset($b['round']) ? (int) $b['round'] : 0;
        return $ra - $rb;
    });

    $last3 = array_slice($played, -3);
    $form = array();
    foreach ($last3 as $m) {
        $form[] = array(
            'result'   => isset($m['result']) ? $m['result'] : 'V',
            'type'     => isset($m['type']) ? $m['type'] : 'win',
            'score'    => isset($m['score']) ? $m['score'] : '',
            'opponent' => isset($m['opponent']) ? $m['opponent'] : ''
        );
    }
    return $form;
}

function ejlResolveLocalLogo($teamName, $isWomen = false) {
    if ($isWomen) {
        if (stripos($teamName, 'Flora') !== false) return 'assets/img/jalgpall_logos/flora_women.png';
        if (stripos($teamName, 'Viimsi') !== false) return 'assets/img/jalgpall_logos/viimsi.png';
        if (stripos($teamName, 'Sporting') !== false || stripos($teamName, 'Saku') !== false) return 'assets/img/jalgpall_logos/saku.png';
        if (stripos($teamName, 'Harju') !== false) return 'assets/img/jalgpall_logos/harju.png';
        if (stripos($teamName, 'Elva') !== false) return 'assets/img/jalgpall_logos/elva.png';
        return 'assets/img/jalgpall_logos/paide.png';
    }
    if (stripos($teamName, 'Levadia') !== false) return 'assets/img/jalgpall_logos/levadia.png';
    if (stripos($teamName, 'Flora') !== false) return 'assets/img/jalgpall_logos/flora.png';
    if (stripos($teamName, 'Kalju') !== false) return 'assets/img/jalgpall_logos/kalju.png';
    if (stripos($teamName, 'Tammeka') !== false) return 'assets/img/jalgpall_logos/tammeka.png';
    if (stripos($teamName, 'Harju') !== false) return 'assets/img/jalgpall_logos/harju.png';
    if (stripos($teamName, 'Vaprus') !== false) return 'assets/img/jalgpall_logos/vaprus.png';
    if (stripos($teamName, 'Kuressaare') !== false) return 'assets/img/jalgpall_logos/kuressaare.png';
    if (stripos($teamName, 'United') !== false) return 'assets/img/jalgpall_logos/nomme_united.png';
    if (stripos($teamName, 'Trans') !== false) return 'assets/img/jalgpall_logos/trans.png';
    return 'assets/img/jalgpall_logos/paide.png';
}

function ejlParseStandingsTable($html, $existingTeamsMap, $seasonHistoryMap, $isWomen = false) {
    if (!$html) return array();
    if (!preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $html, $tables) || empty($tables[1])) {
        return array();
    }

    $tableHtml = $tables[1][0];
    if (!preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableHtml, $rows) || count($rows[1]) <= 1) {
        return array();
    }

    $standings = array();
    $rowList = array_slice($rows[1], 1);

    foreach ($rowList as $r) {
        if (!preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $r, $cols) || count($cols[1]) < 9) {
            continue;
        }
        $logo = '';
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $r, $imgM)) {
            $logo = $imgM[1];
        }

        $cleanCols = array();
        foreach ($cols[1] as $c) {
            $cleanCols[] = ejlCleanText($c);
        }

        $posStr = $cleanCols[0];
        $teamName = $cleanCols[2];
        if ($isWomen) {
            $teamName = trim(str_replace(' (N)', '', $teamName));
        }
        if ($teamName === '') continue;

        $played = (int) $cleanCols[3];
        $won    = (int) $cleanCols[4];
        $drawn  = (int) $cleanCols[5];
        $lost   = (int) $cleanCols[6];
        $goals  = $cleanCols[7];
        $points = (int) $cleanCols[8];

        $gf = 0;
        $ga = 0;
        if (strpos($goals, ':') !== false) {
            $parts = explode(':', $goals);
            $gf = (int) trim($parts[0]);
            $ga = (int) trim($parts[1]);
        }
        $gd = $gf - $ga;
        $gdStr = ($gd > 0) ? ('+' . $gd) : (string) $gd;

        $prevTeam = isset($existingTeamsMap[$teamName]) ? $existingTeamsMap[$teamName] : array();
        $fallbackHistory = isset($prevTeam['seasonHistory']) ? $prevTeam['seasonHistory'] : array();
        $teamHistory = !empty($seasonHistoryMap[$teamName]) ? $seasonHistoryMap[$teamName] : $fallbackHistory;

        $fallbackForm = isset($prevTeam['form']) ? $prevTeam['form'] : array();
        $formData = ejlComputeRecentForm($teamHistory, $fallbackForm);

        $standings[] = array(
            'position'       => ctype_digit($posStr) ? (int) $posStr : (count($standings) + 1),
            'team'           => $teamName,
            'logo'           => $logo !== '' ? $logo : (isset($prevTeam['logo']) ? $prevTeam['logo'] : ''),
            'localLogo'      => ejlResolveLocalLogo($teamName, $isWomen),
            'played'         => $played,
            'won'            => $won,
            'drawn'          => $drawn,
            'lost'           => $lost,
            'goalsFor'       => $gf,
            'goalsAgainst'   => $ga,
            'goalDifference' => $gdStr,
            'points'         => $points,
            'isPaide'        => (stripos($teamName, 'Paide') !== false),
            'form'           => $formData,
            'seasonHistory'  => $teamHistory
        );
    }

    return $standings;
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================
$dataFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'jalgpall-data.json';
$forceRefresh = (isset($_GET['force']) && $_GET['force'] === '1') || (PHP_SAPI === 'cli');
$syncTtlSeconds = 900; // 15 minutes cache on Zone.ee

$existingData = array();
if (file_exists($dataFile)) {
    $rawExisting = @file_get_contents($dataFile);
    if ($rawExisting) {
        $decoded = @json_decode($rawExisting, true);
        if (is_array($decoded)) {
            $existingData = $decoded;
        }
    }
}

// Return existing JSON if fresh (< 15 min old) and not forced
if (!$forceRefresh && !empty($existingData) && file_exists($dataFile) && (time() - filemtime($dataFile)) < $syncTtlSeconds) {
    echo json_encode($existingData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Build lookup maps for existing teams
$existingMenMap = array();
$existingWomenMap = array();
if (isset($existingData['standings']['premiumLiiga']) && is_array($existingData['standings']['premiumLiiga'])) {
    foreach ($existingData['standings']['premiumLiiga'] as $t) {
        if (isset($t['team'])) $existingMenMap[$t['team']] = $t;
    }
}
if (isset($existingData['standings']['naisteMeistriliiga']) && is_array($existingData['standings']['naisteMeistriliiga'])) {
    foreach ($existingData['standings']['naisteMeistriliiga'] as $t) {
        if (isset($t['team'])) $existingWomenMap[$t['team']] = $t;
    }
}

// 1. Fetch Premium Liiga Standings & Calendar (36 rounds)
$menLeagueHtml = ejlFetchUrl('https://jalgpall.ee/voistlused/52/premium-liiga', 10);
$menCalHtml    = ejlFetchUrl('https://jalgpall.ee/voistlused/52/liigad/calendar?season=2026', 10);
$menHistoryMap = $menCalHtml ? ejlExtractSeasonHistory($menCalHtml, 36, false) : array();
$menStandings  = $menLeagueHtml ? ejlParseStandingsTable($menLeagueHtml, $existingMenMap, $menHistoryMap, false) : array();

// 2. Fetch Naiste Meistriliiga Standings & Calendar (25 rounds)
$womenLeagueHtml = ejlFetchUrl('https://jalgpall.ee/voistlused/naised/63/naiste-meistriliiga', 10);
$womenCalHtml    = ejlFetchUrl('https://jalgpall.ee/voistlused/63/liigad/calendar?season=2026', 10);
$womenHistoryMap = $womenCalHtml ? ejlExtractSeasonHistory($womenCalHtml, 25, true) : array();
$womenStandings  = $womenLeagueHtml ? ejlParseStandingsTable($womenLeagueHtml, $existingWomenMap, $womenHistoryMap, true) : array();

$updatedAny = false;
if (count($menStandings) >= 10) {
    $existingData['standings']['premiumLiiga'] = $menStandings;
    $updatedAny = true;
}
if (count($womenStandings) >= 6) {
    $existingData['standings']['naisteMeistriliiga'] = $womenStandings;
    $updatedAny = true;
}

if ($updatedAny) {
    if (!isset($existingData['metadata']) || !is_array($existingData['metadata'])) {
        $existingData['metadata'] = array();
    }
    $existingData['metadata']['source'] = 'Eesti Jalgpalli Liit (EJL)';
    $existingData['metadata']['sourceUrl'] = 'https://jalgpall.ee/voistlused/52/team/5';
    $existingData['metadata']['lastUpdated'] = date('Y-m-d\TH:i:s');
    $existingData['metadata']['season'] = '2026';
    $existingData['metadata']['status'] = 'success';

    $encoded = json_encode($existingData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($encoded) {
        $tmpFile = $dataFile . '.tmp';
        if (@file_put_contents($tmpFile, $encoded) !== false) {
            @rename($tmpFile, $dataFile);
        } else {
            @file_put_contents($dataFile, $encoded);
        }
    }
}

echo json_encode($existingData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
