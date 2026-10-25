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
    $str = preg_replace('/<!--.*?-->/s', '', (string) $str);
    $str = html_entity_decode(strip_tags($str), ENT_QUOTES, 'UTF-8');
    $str = str_replace('*', '', $str);
    $str = preg_replace('/\s+/u', ' ', $str);
    return trim($str);
}

function ejlParseDateTimestamp($dStr) {
    if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{1,2}):(\d{2}))?/', (string) $dStr, $dm)) {
        $day = (int) $dm[1];
        $month = (int) $dm[2];
        $year = (int) $dm[3];
        $hour = isset($dm[4]) && $dm[4] !== '' ? (int) $dm[4] : 0;
        $min = isset($dm[5]) && $dm[5] !== '' ? (int) $dm[5] : 0;
        return mktime($hour, $min, 0, $month, $day, $year);
    }
    return 0;
}

function ejlIsMatchStillLive($evHtml, $dateStr) {
    $kickoffTs = ejlParseDateTimestamp($dateStr);
    if ($kickoffTs <= 0) return false;
    $elapsed = time() - $kickoffTs;
    // Only matches that kicked off within the last 3.5 hours can be currently live
    if ($elapsed < -1800 || $elapsed > 12600) {
        return false;
    }
    // Check official EJL protocol status (<small>Hetkeseis</small> vs <small>Lõppseis</small>)
    if (preg_match('/\/voistlused\/protocol\/(\d+)/', $evHtml, $pm)) {
        $protoHtml = ejlFetchUrl('https://jalgpall.ee/voistlused/protocol/' . $pm[1], 5);
        if ($protoHtml) {
            if (stripos($protoHtml, 'Lõppseis') !== false) {
                return false;
            }
            if (stripos($protoHtml, 'Hetkeseis') !== false) {
                return true;
            }
        }
    }
    // Fallback if protocol page could not be reached: a standard match takes ~108 minutes
    return ($elapsed >= 0 && $elapsed < 6600);
}

function ejlExtractSeasonHistory($html, $totalRounds, $cleanWomen = false, &$liveMatchesByTeam = null) {
    if (!is_array($liveMatchesByTeam)) {
        $liveMatchesByTeam = array();
    }
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

                    // Do not count in-progress (Hetkeseis) matches in standings/form/history until the match is finished (Lõppseis)
                    if (ejlIsMatchStillLive($ev, $dateStr)) {
                        if (!isset($liveMatchesByTeam[$home])) $liveMatchesByTeam[$home] = array();
                        if (!isset($liveMatchesByTeam[$away])) $liveMatchesByTeam[$away] = array();
                        $liveMatchesByTeam[$home][] = array('gf' => $hg, 'ga' => $ag);
                        $liveMatchesByTeam[$away][] = array('gf' => $ag, 'ga' => $hg);

                        $hType = 'upcoming';
                        $aType = 'upcoming';
                        $hRes = '-';
                        $aRes = '-';
                        $hScoreStr = 'vs ' . $away;
                        $aScoreStr = 'vs ' . $home;
                        $matchScore = '-';
                    } else {
                        $hType = ($hg > $ag) ? 'win' : (($hg < $ag) ? 'loss' : 'draw');
                        $aType = ($ag > $hg) ? 'win' : (($ag < $hg) ? 'loss' : 'draw');
                        $hRes = ($hType === 'win' || $hType === 'draw') ? 'V' : 'K';
                        $aRes = ($aType === 'win' || $aType === 'draw') ? 'V' : 'K';
                        $hScoreStr = $hg . ':' . $ag . ' vs ' . $away;
                        $aScoreStr = $ag . ':' . $hg . ' vs ' . $home;
                        $matchScore = $hg . ':' . $ag;
                    }
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

function ejlParseStandingsTable($html, $existingTeamsMap, $seasonHistoryMap, $isWomen = false, $liveMatchesByTeam = array()) {
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
    $adjustedAnyLive = false;

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

        $prevTeam = isset($existingTeamsMap[$teamName]) ? $existingTeamsMap[$teamName] : array();
        $fallbackHistory = isset($prevTeam['seasonHistory']) ? $prevTeam['seasonHistory'] : array();
        $teamHistory = !empty($seasonHistoryMap[$teamName]) ? $seasonHistoryMap[$teamName] : $fallbackHistory;

        // If EJL's live table prematurely included an in-progress (Hetkeseis) match, subtract it until Lõppseis
        $rowAdjusted = false;
        if (!empty($liveMatchesByTeam[$teamName]) && is_array($teamHistory)) {
            $finishedCount = 0;
            foreach ($teamHistory as $hm) {
                if (isset($hm['type']) && in_array($hm['type'], array('win', 'draw', 'loss'), true)) {
                    $finishedCount++;
                }
            }
            if ($played > $finishedCount) {
                foreach ($liveMatchesByTeam[$teamName] as $lm) {
                    if ($played <= $finishedCount) break;
                    $played = max(0, $played - 1);
                    $gf = max(0, $gf - (int) $lm['gf']);
                    $ga = max(0, $ga - (int) $lm['ga']);
                    if ($lm['gf'] > $lm['ga']) {
                        $won = max(0, $won - 1);
                        $points = max(0, $points - 3);
                    } elseif ($lm['gf'] < $lm['ga']) {
                        $lost = max(0, $lost - 1);
                    } else {
                        $drawn = max(0, $drawn - 1);
                        $points = max(0, $points - 1);
                    }
                    $adjustedAnyLive = true;
                    $rowAdjusted = true;
                }
            }
        }

        $gd = $gf - $ga;
        $gdStr = ($gd > 0) ? ('+' . $gd) : (string) $gd;

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
            'seasonHistory'  => $teamHistory,
            '_wasAdjusted'   => $rowAdjusted
        );
    }

    if ($adjustedAnyLive && count($standings) > 1) {
        usort($standings, function ($a, $b) {
            if ($a['points'] !== $b['points']) return $b['points'] - $a['points'];
            // Preserve official EJL table order when neither team had a live match subtracted
            if (empty($a['_wasAdjusted']) && empty($b['_wasAdjusted'])) {
                return $a['position'] - $b['position'];
            }
            // Otherwise evaluate head-to-head (omavahelised mängud) first per EJL regulations
            $ptsA = 0; $ptsB = 0; $gdA = 0; $gdB = 0;
            $oppName = isset($b['team']) ? $b['team'] : '';
            if (!empty($a['seasonHistory']) && is_array($a['seasonHistory'])) {
                foreach ($a['seasonHistory'] as $m) {
                    if (isset($m['opponent'], $m['type']) && $m['opponent'] === $oppName && in_array($m['type'], array('win', 'draw', 'loss'), true)) {
                        if ($m['type'] === 'win') {
                            $ptsA += 3;
                        } elseif ($m['type'] === 'draw') {
                            $ptsA += 1;
                            $ptsB += 1;
                        } else {
                            $ptsB += 3;
                        }
                        if (!empty($m['matchScore']) && strpos($m['matchScore'], ':') !== false) {
                            $parts = explode(':', $m['matchScore']);
                            $hg = (int) trim($parts[0]);
                            $ag = (int) trim($parts[1]);
                            if (!empty($m['isHome'])) {
                                $gdA += ($hg - $ag);
                                $gdB += ($ag - $hg);
                            } else {
                                $gdA += ($ag - $hg);
                                $gdB += ($hg - $ag);
                            }
                        }
                    }
                }
            }
            if ($ptsA !== $ptsB) return $ptsB - $ptsA;
            if ($gdA !== $gdB) return $gdB - $gdA;
            if ($a['won'] !== $b['won']) return $b['won'] - $a['won'];
            $totGdA = $a['goalsFor'] - $a['goalsAgainst'];
            $totGdB = $b['goalsFor'] - $b['goalsAgainst'];
            if ($totGdA !== $totGdB) return $totGdB - $totGdA;
            if ($a['goalsFor'] !== $b['goalsFor']) return $b['goalsFor'] - $a['goalsFor'];
            return $a['position'] - $b['position'];
        });
        foreach ($standings as $idx => &$row) {
            $row['position'] = $idx + 1;
            unset($row['_wasAdjusted']);
        }
        unset($row);
    } else {
        foreach ($standings as &$row) {
            unset($row['_wasAdjusted']);
        }
        unset($row);
    }

    return $standings;
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================
$dataFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'jalgpall-data.json';
$forceRefresh = (isset($_GET['force']) && $_GET['force'] === '1') || (PHP_SAPI === 'cli');
$syncTtlSeconds = 120; // 2 minutes cache on Zone.ee so finished matches update promptly

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

// Return existing JSON if fresh (< 2 min old) and not forced
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
$menLiveByTeam = array();
$menLeagueHtml = ejlFetchUrl('https://jalgpall.ee/voistlused/52/premium-liiga', 10);
$menCalHtml    = ejlFetchUrl('https://jalgpall.ee/voistlused/52/liigad/calendar?season=2026', 10);
$menHistoryMap = $menCalHtml ? ejlExtractSeasonHistory($menCalHtml, 36, false, $menLiveByTeam) : array();
$menStandings  = $menLeagueHtml ? ejlParseStandingsTable($menLeagueHtml, $existingMenMap, $menHistoryMap, false, $menLiveByTeam) : array();

// 2. Fetch Naiste Meistriliiga Standings & Calendar (25 rounds)
$womenLiveByTeam = array();
$womenLeagueHtml = ejlFetchUrl('https://jalgpall.ee/voistlused/naised/63/naiste-meistriliiga', 10);
$womenCalHtml    = ejlFetchUrl('https://jalgpall.ee/voistlused/63/liigad/calendar?season=2026', 10);
$womenHistoryMap = $womenCalHtml ? ejlExtractSeasonHistory($womenCalHtml, 25, true, $womenLiveByTeam) : array();
$womenStandings  = $womenLeagueHtml ? ejlParseStandingsTable($womenLeagueHtml, $existingWomenMap, $womenHistoryMap, true, $womenLiveByTeam) : array();

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
