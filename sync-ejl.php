<?php
/**
 * Paide Linnameeskond — Official EJL OpenData API Sync Endpoint
 *
 * Replaces all HTML regex scraping with official EJL OpenData API v1 requests:
 *   - GET /api/v1/competitions/52 & /api/v1/competitions/52/games (Premium Liiga table & season form)
 *   - GET /api/v1/competitions/63 & /api/v1/competitions/63/games (Naiste Meistriliiga table & season form)
 *   - GET /api/v1/teams/5/games & /api/v1/games/{id} (Men's upcoming & recent matches + scorers)
 *   - GET /api/v1/teams/3521/games & /api/v1/games/{id} (Women's upcoming & recent matches + scorers)
 *   - GET /api/v1/teams/5/statistics & /api/v1/players/{id}/statistics (Men's goals, assists, minutes)
 *   - GET /api/v1/teams/3521/statistics & /api/v1/players/{id}/statistics (Women's goals, assists, minutes)
 *
 * Security: Uses `api/ejl-client.php` so the EJL Bearer token remains strictly confidential on the server.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('Europe/Tallinn');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'ejl-client.php';

/**
 * Fetches all played games for a competition in a season (across pagination)
 * and builds per-team chronological seasonHistory arrays.
 */
function ejlFetchCompetitionSeasonHistory($competitionId, $season) {
    $teamMatches = array();
    $page = 1;
    $maxPages = 5;

    while ($page <= $maxPages) {
        $resp = ejlApiGet("/api/v1/competitions/{$competitionId}/games", array(
            'season'     => $season,
            'upcoming'   => 0,
            'pageSize'   => 100,
            'pageNumber' => $page,
        ));
        if (!is_array($resp) || empty($resp['games']) || !is_array($resp['games'])) {
            break;
        }

        foreach ($resp['games'] as $g) {
            $scores = $g['scores']['fullTime'] ?? null;
            if (!is_array($scores) || !isset($scores['home']) || !isset($scores['away']) || $scores['home'] === null || $scores['away'] === null) {
                continue;
            }
            $homeGoals = (int)$scores['home'];
            $awayGoals = (int)$scores['away'];
            $homeTeam = ejlCleanTeamName($g['teams']['home']['name'] ?? '');
            $awayTeam = ejlCleanTeamName($g['teams']['away']['name'] ?? '');
            if ($homeTeam === '' || $awayTeam === '') {
                continue;
            }

            $dtParsed = ejlParseDateTime($g['dateTime'] ?? '');
            $sortTs = (int)$dtParsed['timestamp'];
            $dateStr = $dtParsed['dateTimeStr'];
            $matchScore = "{$homeGoals}:{$awayGoals}";

            // Home team entry
            if ($homeGoals > $awayGoals) {
                $homeType = 'win';
                $homeRes = 'V';
            } elseif ($homeGoals < $awayGoals) {
                $homeType = 'loss';
                $homeRes = 'K';
            } else {
                $homeType = 'draw';
                $homeRes = 'X';
            }
            if (!isset($teamMatches[$homeTeam])) {
                $teamMatches[$homeTeam] = array();
            }
            $teamMatches[$homeTeam][] = array(
                '_sortTs'    => $sortTs,
                'type'       => $homeType,
                'result'     => $homeRes,
                'score'      => "{$matchScore} vs {$awayTeam}",
                'matchScore' => $matchScore,
                'homeTeam'   => $homeTeam,
                'awayTeam'   => $awayTeam,
                'opponent'   => $awayTeam,
                'date'       => $dateStr,
                'isHome'     => true,
            );

            // Away team entry
            if ($awayGoals > $homeGoals) {
                $awayType = 'win';
                $awayRes = 'V';
            } elseif ($awayGoals < $homeGoals) {
                $awayType = 'loss';
                $awayRes = 'K';
            } else {
                $awayType = 'draw';
                $awayRes = 'X';
            }
            if (!isset($teamMatches[$awayTeam])) {
                $teamMatches[$awayTeam] = array();
            }
            $teamMatches[$awayTeam][] = array(
                '_sortTs'    => $sortTs,
                'type'       => $awayType,
                'result'     => $awayRes,
                'score'      => "{$matchScore} vs {$homeTeam}",
                'matchScore' => $matchScore,
                'homeTeam'   => $homeTeam,
                'awayTeam'   => $awayTeam,
                'opponent'   => $homeTeam,
                'date'       => $dateStr,
                'isHome'     => false,
            );
        }

        $totalPages = isset($resp['totalPages']) ? (int)$resp['totalPages'] : 1;
        if ($page >= $totalPages) {
            break;
        }
        $page++;
    }

    foreach ($teamMatches as $tName => &$mList) {
        usort($mList, function ($a, $b) {
            return $a['_sortTs'] <=> $b['_sortTs'];
        });
        foreach ($mList as $idx => &$item) {
            $item['round'] = $idx + 1;
            unset($item['_sortTs']);
        }
        unset($item);
    }
    unset($mList);

    return $teamMatches;
}

/**
 * Builds the standings array for a competition (`52` or `63`) from the EJL API.
 */
function ejlFetchStandingsFromApi($competitionId, $season, $existingRows = array()) {
    $compResp = ejlApiGet("/api/v1/competitions/{$competitionId}", array(
        'season' => $season,
    ));
    if (!is_array($compResp) || empty($compResp['table']) || !is_array($compResp['table'])) {
        return array();
    }

    $seasonHistoryMap = ejlFetchCompetitionSeasonHistory($competitionId, $season);

    $existingByTeam = array();
    if (is_array($existingRows)) {
        foreach ($existingRows as $er) {
            if (!empty($er['team'])) {
                $existingByTeam[mb_strtolower(ejlCleanTeamName($er['team']), 'UTF-8')] = $er;
            }
        }
    }

    $standings = array();
    foreach ($compResp['table'] as $row) {
        $teamObj = $row['team'] ?? array();
        $statsObj = $row['stats'] ?? array();
        $teamName = ejlCleanTeamName($teamObj['name'] ?? '');
        if ($teamName === '') {
            continue;
        }

        $rank = isset($row['rank']) ? (int)$row['rank'] : (count($standings) + 1);
        $played = isset($statsObj['gamesPlayed']) ? (int)$statsObj['gamesPlayed'] : 0;
        $won = isset($statsObj['wins']) ? (int)$statsObj['wins'] : 0;
        $drawn = isset($statsObj['draws']) ? (int)$statsObj['draws'] : 0;
        $lost = isset($statsObj['losses']) ? (int)$statsObj['losses'] : 0;
        $gf = isset($statsObj['goalsScored']) ? (int)$statsObj['goalsScored'] : 0;
        $ga = isset($statsObj['goalsConceded']) ? (int)$statsObj['goalsConceded'] : 0;
        $diff = isset($statsObj['goalsDiff']) ? (int)$statsObj['goalsDiff'] : ($gf - $ga);
        $points = isset($statsObj['points']) ? (int)$statsObj['points'] : ($won * 3 + $drawn);
        $gdStr = $diff > 0 ? '+' . $diff : (string)$diff;

        $teamKey = mb_strtolower($teamName, 'UTF-8');
        $prevRow = $existingByTeam[$teamKey] ?? array();

        $seasonHistory = $seasonHistoryMap[$teamName] ?? ($prevRow['seasonHistory'] ?? array());
        $form = array();
        if (!empty($seasonHistory)) {
            $last5 = array_slice($seasonHistory, -5);
            foreach ($last5 as $h) {
                $form[] = array(
                    'result'   => $h['result'],
                    'type'     => $h['type'],
                    'score'    => $h['score'],
                    'opponent' => $h['opponent'],
                );
            }
        } elseif (!empty($prevRow['form'])) {
            $form = $prevRow['form'];
        }

        $localLogo = ejlResolveLocalLogo($teamName);
        if ($localLogo === '' && !empty($prevRow['localLogo'])) {
            $localLogo = $prevRow['localLogo'];
        }

        $standings[] = array(
            'position'       => $rank,
            'team'           => $teamName,
            'teamId'         => isset($teamObj['id']) ? (int)$teamObj['id'] : null,
            'logo'           => $teamObj['logo'] ?? ($prevRow['logo'] ?? ''),
            'localLogo'      => $localLogo,
            'played'         => $played,
            'won'            => $won,
            'drawn'          => $drawn,
            'lost'           => $lost,
            'goalsFor'       => $gf,
            'goalsAgainst'   => $ga,
            'goalDifference' => $gdStr,
            'points'         => $points,
            'isPaide'        => (mb_stripos($teamName, 'paide', 0, 'UTF-8') !== false),
            'form'           => $form,
            'seasonHistory'  => $seasonHistory,
        );
    }

    return $standings;
}

/**
 * Fetches upcoming and recent matches for a team (`5` for Men, `3521` for Women) from the EJL API.
 */
function ejlFetchTeamMatchesFromApi($teamId, $season, $isWomen = false) {
    $defaultCode = $isWomen ? 'N.ML' : 'PL';
    $defaultTournament = $isWomen ? 'Naiste Meistriliiga' : 'A. Le Coq Premium Liiga';

    // 1. Upcoming games (upcoming=1)
    $upResp = ejlApiGet("/api/v1/teams/{$teamId}/games", array(
        'season'   => $season,
        'upcoming' => 1,
        'pageSize' => 20,
    ));
    $rawUpcoming = (is_array($upResp) && !empty($upResp['games'])) ? $upResp['games'] : array();

    $upcomingList = array();
    foreach ($rawUpcoming as $g) {
        $dtParsed = ejlParseDateTime($g['dateTime'] ?? '');
        $compObj = $g['competition'] ?? array();
        $compShort = trim((string)($compObj['name'] ?? $defaultCode));
        if ($compShort === 'ML') {
            $compShort = 'PL';
        }
        $compLong = trim((string)($compObj['longName'] ?? $defaultTournament));
        $tour = trim((string)($g['tour'] ?? ''));
        $roundDisplay = $compLong . ($tour !== '' ? " • {$tour}. Voor" : '');

        $homeTeam = ejlCleanTeamName($g['teams']['home']['name'] ?? '');
        $awayTeam = ejlCleanTeamName($g['teams']['away']['name'] ?? '');
        $venue = trim((string)($g['field']['name'] ?? ''));
        $broadcaster = trim((string)($g['coverage']['broadcaster']['name'] ?? ''));
        $broadcastStr = $broadcaster !== '' ? 'OTSE: ' . mb_strtoupper($broadcaster, 'UTF-8') : 'OTSE: SOCCERNET TV / JALGPALL.EE';
        $ticketUrl = $g['links']['tickets'] ?? null;
        $gid = isset($g['id']) ? (int)$g['id'] : 0;

        $upcomingList[] = array(
            '_ts'          => (int)$dtParsed['timestamp'],
            'id'           => $gid,
            'type'         => 'upcoming',
            'homeTeam'     => $homeTeam,
            'awayTeam'     => $awayTeam,
            'date'         => trim($compShort . ' ' . $dtParsed['dateTimeStr']),
            'dateStr'      => $dtParsed['dateStr'],
            'timeStr'      => $dtParsed['timeStr'],
            'dateDisplay'  => $dtParsed['dateDisplay'],
            'venue'        => $venue,
            'round'        => $roundDisplay,
            'broadcast'    => $broadcastStr,
            'ticketUrl'    => $ticketUrl,
            'matchInfoUrl' => $gid > 0 ? "https://jalgpall.ee/voistlused/protocol/{$gid}" : '',
        );
    }

    // Sort upcoming chronologically ascending (earliest next match first!)
    usort($upcomingList, function ($a, $b) {
        return $a['_ts'] <=> $b['_ts'];
    });
    foreach ($upcomingList as &$uItem) {
        unset($uItem['_ts']);
    }
    unset($uItem);

    $nextMatch = !empty($upcomingList) ? $upcomingList[0] : null;

    // 2. Recent played games (upcoming=0)
    $pastResp = ejlApiGet("/api/v1/teams/{$teamId}/games", array(
        'season'   => $season,
        'upcoming' => 0,
        'pageSize' => 15,
    ));
    $rawPast = (is_array($pastResp) && !empty($pastResp['games'])) ? $pastResp['games'] : array();

    $recentList = array();
    foreach ($rawPast as $idx => $g) {
        $scores = $g['scores']['fullTime'] ?? null;
        if (!is_array($scores) || !isset($scores['home']) || !isset($scores['away']) || $scores['home'] === null || $scores['away'] === null) {
            continue;
        }
        $gid = isset($g['id']) ? (int)$g['id'] : 0;
        $dtParsed = ejlParseDateTime($g['dateTime'] ?? '');
        $compObj = $g['competition'] ?? array();
        $compShort = trim((string)($compObj['name'] ?? $defaultCode));
        if ($compShort === 'ML') {
            $compShort = 'PL';
        }
        $compLong = trim((string)($compObj['longName'] ?? $defaultTournament));
        $tour = trim((string)($g['tour'] ?? ''));
        $roundStr = $tour !== '' ? "{$tour}. voor" : $compLong;

        $homeTeam = ejlCleanTeamName($g['teams']['home']['name'] ?? '');
        $awayTeam = ejlCleanTeamName($g['teams']['away']['name'] ?? '');
        $homeScore = (int)$scores['home'];
        $awayScore = (int)$scores['away'];
        $venue = trim((string)($g['field']['name'] ?? ''));
        $attendance = isset($g['attendance']) && $g['attendance'] !== null ? (int)$g['attendance'] : null;

        // Fetch full game protocol details (goal scorers) for the 5 most recent games
        $scorers = array();
        if ($gid > 0 && count($recentList) < 5) {
            $detail = ejlApiGet("/api/v1/games/{$gid}");
            if (is_array($detail)) {
                $scorers = ejlExtractGameGoals($detail);
            }
        }

        $recentList[] = array(
            '_ts'         => (int)$dtParsed['timestamp'],
            'id'          => $gid,
            'type'        => 'finished',
            'meta'        => trim("{$compShort} {$dtParsed['dateStr']} Kell {$dtParsed['timeStr']}"),
            'tournament'  => $compLong,
            'round'       => $roundStr,
            'dateDisplay' => $dtParsed['dateDisplay'],
            'timeDisplay' => $dtParsed['timeStr'],
            'homeTeam'    => $homeTeam,
            'score'       => "{$homeScore} - {$awayScore}",
            'homeScore'   => $homeScore,
            'awayScore'   => $awayScore,
            'awayTeam'    => $awayTeam,
            'venue'       => $venue,
            'attendance'  => $attendance,
            'protocolUrl' => $gid > 0 ? "https://jalgpall.ee/voistlused/protocol/{$gid}" : '',
            'scorers'     => $scorers,
        );
    }

    // Sort recent matches descending (newest played match first!)
    usort($recentList, function ($a, $b) {
        return $b['_ts'] <=> $a['_ts'];
    });
    foreach ($recentList as &$rItem) {
        unset($rItem['_ts']);
    }
    unset($rItem);

    return array(
        'next'     => $nextMatch,
        'upcoming' => $upcomingList,
        'recent'   => $recentList,
    );
}

/**
 * Fetches team statistics, top scorers (with league vs cup breakdown), and assists from the EJL API.
 */
function ejlFetchTeamStatsAndTopScorers($teamId, $season, $isWomen = false, $existingScorers = array()) {
    $statsResp = ejlApiGet("/api/v1/teams/{$teamId}/statistics", array(
        'season'            => $season,
        'includeTopPlayers' => 1,
        'limit'             => 30,
    ));
    if (!is_array($statsResp)) {
        return array(
            'topScorers' => $existingScorers,
            'teamStats'  => null,
        );
    }

    $existingByName = array();
    if (is_array($existingScorers)) {
        foreach ($existingScorers as $es) {
            if (!empty($es['name'])) {
                $existingByName[mb_strtolower(trim($es['name']), 'UTF-8')] = $es;
            }
        }
    }

    // Build assists lookup map from topPlayersByAssists
    $assistsMap = array();
    if (!empty($statsResp['topPlayersByAssists']) && is_array($statsResp['topPlayersByAssists'])) {
        foreach ($statsResp['topPlayersByAssists'] as $aItem) {
            $pid = isset($aItem['player']['id']) ? (int)$aItem['player']['id'] : 0;
            if ($pid > 0) {
                $assistsMap[$pid] = (int)($aItem['value'] ?? 0);
            }
        }
    }

    $leagueCode = $isWomen ? 'N.ML' : 'ML';
    $cupCode = $isWomen ? 'NKV' : 'KV';
    $topScorers = array();

    $rawGoalsList = isset($statsResp['topPlayersByGoals']) && is_array($statsResp['topPlayersByGoals'])
        ? array_slice($statsResp['topPlayersByGoals'], 0, 10)
        : array();

    foreach ($rawGoalsList as $idx => $gItem) {
        $pObj = $gItem['player'] ?? array();
        $pid = isset($pObj['id']) ? (int)$pObj['id'] : 0;
        $fullName = trim(($pObj['forename'] ?? '') . ' ' . ($pObj['surname'] ?? ''));
        if ($fullName === '') {
            continue;
        }

        $teamGoalsVal = isset($gItem['value']) ? (int)$gItem['value'] : 0;
        $leagueGoals = $teamGoalsVal;
        $cupGoals = 0;
        $apiAssists = $assistsMap[$pid] ?? 0;
        $gamesPlayed = 0;
        $minutesPlayed = 0;

        if ($pid > 0) {
            $pStat = ejlApiGet("/api/v1/players/{$pid}/statistics", array('season' => $season));
            if (is_array($pStat) && !empty($pStat['leagues']) && is_array($pStat['leagues'])) {
                $lgGoals = 0;
                $cpGoals = 0;
                $totAssists = 0;
                foreach ($pStat['leagues'] as $lg) {
                    $lName = strtoupper(trim((string)($lg['leagueName'] ?? '')));
                    if ($lName === $leagueCode) {
                        $lgGoals += (int)($lg['goals'] ?? 0);
                        $totAssists += (int)($lg['assists'] ?? 0);
                        $gamesPlayed += (int)($lg['gamesPlayed'] ?? 0);
                        $minutesPlayed += (int)($lg['minutesPlayed'] ?? 0);
                    } elseif ($lName === $cupCode) {
                        $cpGoals += (int)($lg['goals'] ?? 0);
                        $totAssists += (int)($lg['assists'] ?? 0);
                        $gamesPlayed += (int)($lg['gamesPlayed'] ?? 0);
                        $minutesPlayed += (int)($lg['minutesPlayed'] ?? 0);
                    }
                }
                if (($lgGoals + $cpGoals) > 0) {
                    $leagueGoals = $lgGoals;
                    $cupGoals = $cpGoals;
                    $teamGoalsVal = $lgGoals + $cpGoals;
                }
                if ($totAssists > $apiAssists) {
                    $apiAssists = $totAssists;
                }
            }
        }

        $prevScorer = $existingByName[mb_strtolower($fullName, 'UTF-8')] ?? array();
        $finalAssists = $apiAssists;
        if ($finalAssists === 0 && !empty($prevScorer['assists'])) {
            $finalAssists = (int)$prevScorer['assists'];
        }

        $topScorers[] = array(
            'rank'          => (string)($idx + 1),
            'id'            => $pid,
            'name'          => $fullName,
            'leagueGoals'   => (string)$leagueGoals,
            'cupGoals'      => (string)$cupGoals,
            'totalGoals'    => (string)$teamGoalsVal,
            'assists'       => (string)$finalAssists,
            'apiAssists'    => (string)$apiAssists,
            'gamesPlayed'   => $gamesPlayed,
            'minutesPlayed' => $minutesPlayed,
        );
    }

    $topAssistsList = array();
    if (!empty($statsResp['topPlayersByAssists']) && is_array($statsResp['topPlayersByAssists'])) {
        foreach (array_slice($statsResp['topPlayersByAssists'], 0, 10) as $idx => $aItem) {
            $pObj = $aItem['player'] ?? array();
            $topAssistsList[] = array(
                'rank'    => (int)($aItem['rank'] ?? ($idx + 1)),
                'id'      => isset($pObj['id']) ? (int)$pObj['id'] : 0,
                'name'    => trim(($pObj['forename'] ?? '') . ' ' . ($pObj['surname'] ?? '')),
                'assists' => (int)($aItem['value'] ?? 0),
            );
        }
    }

    $teamStatsSummary = array(
        'gamesPlayed' => $statsResp['gamesPlayed']['total'] ?? 0,
        'goals'       => $statsResp['goals']['total'] ?? 0,
        'assists'     => $statsResp['assists']['total'] ?? 0,
        'yellowCards' => $statsResp['yellowCards']['total'] ?? 0,
        'redCards'    => $statsResp['redCards']['total'] ?? 0,
        'viewers'     => $statsResp['viewers']['total'] ?? 0,
        'topAssists'  => $topAssistsList,
    );

    return array(
        'topScorers' => !empty($topScorers) ? $topScorers : $existingScorers,
        'teamStats'  => $teamStatsSummary,
    );
}

// ---------------------------------------------------------------------------
// Main Execution
// ---------------------------------------------------------------------------
$dataFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'jalgpall-data.json';
if (!file_exists($dataFile)) {
    http_response_code(500);
    echo json_encode(array('status' => 'error', 'message' => 'Missing data/jalgpall-data.json'));
    exit;
}

$forceRefresh = isset($_GET['force']) && $_GET['force'] === '1';

// If data/jalgpall-data.json was updated within the last 90 seconds and not forced, return cached file
if (!$forceRefresh && (time() - filemtime($dataFile)) < 90) {
    $cached = @file_get_contents($dataFile);
    if (is_string($cached) && strlen($cached) > 100) {
        echo $cached;
        exit;
    }
}

$rawExisting = @file_get_contents($dataFile);
$data = json_decode($rawExisting, true);
if (!is_array($data)) {
    echo $rawExisting;
    exit;
}

$season = 2026;
$updatedAny = false;

// 1. Men's Premium Liiga Standings (Competition 52)
$existingMenStandings = $data['standings']['premiumLiiga'] ?? array();
$menStandings = ejlFetchStandingsFromApi(52, $season, $existingMenStandings);
if (count($menStandings) >= 8) {
    $data['standings']['premiumLiiga'] = $menStandings;
    $updatedAny = true;
}

// 2. Women's Naiste Meistriliiga Standings (Competition 63)
$existingWomenStandings = $data['standings']['naisteMeistriliiga'] ?? array();
$womenStandings = ejlFetchStandingsFromApi(63, $season, $existingWomenStandings);
if (count($womenStandings) >= 4) {
    $data['standings']['naisteMeistriliiga'] = $womenStandings;
    $updatedAny = true;
}

// 3. Men's Matches (Team 5)
$menMatches = ejlFetchTeamMatchesFromApi(5, $season, false);
if (!empty($menMatches['upcoming']) || !empty($menMatches['recent'])) {
    $data['matches'] = $menMatches;
    $updatedAny = true;
}

// 4. Women's Matches (Team 3521)
$womenMatches = ejlFetchTeamMatchesFromApi(3521, $season, true);
if (!empty($womenMatches['upcoming']) || !empty($womenMatches['recent'])) {
    $data['womenMatches'] = $womenMatches;
    $updatedAny = true;
}

// 5. Men's Top Scorers & Team Statistics (Team 5)
$menStats = ejlFetchTeamStatsAndTopScorers(5, $season, false, $data['topScorers'] ?? array());
if (!empty($menStats['topScorers'])) {
    $data['topScorers'] = $menStats['topScorers'];
    $updatedAny = true;
}
if (!empty($menStats['teamStats'])) {
    $data['teamStats'] = $menStats['teamStats'];
}

// 6. Women's Top Scorers & Team Statistics (Team 3521)
$womenStats = ejlFetchTeamStatsAndTopScorers(3521, $season, true, $data['womenTopScorers'] ?? array());
if (!empty($womenStats['topScorers'])) {
    $data['womenTopScorers'] = $womenStats['topScorers'];
    $updatedAny = true;
}
if (!empty($womenStats['teamStats'])) {
    $data['womenTeamStats'] = $womenStats['teamStats'];
}

if ($updatedAny) {
    if (!isset($data['metadata']) || !is_array($data['metadata'])) {
        $data['metadata'] = array();
    }
    $data['metadata']['source']      = 'Eesti Jalgpalli Liit (EJL OpenData API v1)';
    $data['metadata']['sourceUrl']   = 'https://opendata.jalgpall.ee/api/v1';
    $data['metadata']['lastUpdated'] = date('c');
    $data['metadata']['status']      = 'success';

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($encoded) && strlen($encoded) > 100) {
        @file_put_contents($dataFile, $encoded);
        echo $encoded;
        exit;
    }
}

echo $rawExisting;
