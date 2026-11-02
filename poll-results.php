<?php
/**
 * Paide Linnameeskond — Hooaja Parim Mängija 2026 (Backend Poll Review Dashboard)
 * Reads data/poll-season-player-2026.json and displays live leaderboards + full voter log.
 */
@ini_set('display_errors', '0');
error_reporting(0);
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Europe/Tallinn');
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$dataDir  = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'data';
$jsonFile = $dataDir . DIRECTORY_SEPARATOR . 'poll-season-player-2026.json';
$csvFile  = $dataDir . DIRECTORY_SEPARATOR . 'poll-season-player-2026.csv';

$pollData = array(
    'updated_at'  => '-',
    'total_votes' => array('men' => 0, 'women' => 0),
    'standings'   => array('men' => array(), 'women' => array()),
    'votes'       => array()
);

if (is_file($jsonFile)) {
    $decoded = json_decode((string) @file_get_contents($jsonFile), true);
    if (is_array($decoded)) {
        $pollData = array_merge($pollData, $decoded);
    }
}

// Auto-merge if a Zone.ee backup JSON file is placed in data/ (e.g. poll-season-player-2026-backup.json)
$backupCandidates = array(
    $dataDir . DIRECTORY_SEPARATOR . 'poll-season-player-2026-backup.json',
    $dataDir . DIRECTORY_SEPARATOR . 'poll-season-player-2026.backup.json'
);
foreach ($backupCandidates as $bFile) {
    if (is_file($bFile)) {
        $bDecoded = json_decode((string) @file_get_contents($bFile), true);
        if (is_array($bDecoded) && isset($bDecoded['votes']) && is_array($bDecoded['votes']) && !empty($bDecoded['votes'])) {
            $seenKeys = array();
            $mergedVotes = array();
            // Load backup votes first (older), then current votes
            $combined = array_merge($bDecoded['votes'], isset($pollData['votes']) && is_array($pollData['votes']) ? $pollData['votes'] : array());
            $newStandings = array('men' => array(), 'women' => array());
            $newTotals = array('men' => 0, 'women' => 0);

            foreach ($combined as $v) {
                if (!is_array($v)) continue;
                $sq = isset($v['squad']) && $v['squad'] === 'women' ? 'women' : 'men';
                $em = isset($v['email']) ? strtolower(trim((string) $v['email'])) : '';
                $pn = isset($v['player_name']) ? trim((string) $v['player_name']) : '';
                if ($em === '' || $pn === '') continue;
                $dedupKey = $sq . '|' . $em;
                if (isset($seenKeys[$dedupKey])) continue;
                $seenKeys[$dedupKey] = true;
                $mergedVotes[] = $v;
                $newTotals[$sq]++;
                if (!isset($newStandings[$sq][$pn])) {
                    $newStandings[$sq][$pn] = 0;
                }
                $newStandings[$sq][$pn]++;
            }
            arsort($newStandings['men']);
            arsort($newStandings['women']);

            if (count($mergedVotes) > count($pollData['votes'])) {
                $pollData['votes'] = $mergedVotes;
                $pollData['total_votes'] = $newTotals;
                $pollData['standings'] = $newStandings;
                $pollData['updated_at'] = date('c');
                $jsonEncoded = defined('JSON_PRETTY_PRINT')
                    ? json_encode($pollData, JSON_PRETTY_PRINT | (defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0))
                    : json_encode($pollData);
                @file_put_contents($jsonFile, $jsonEncoded, LOCK_EX);
            }
        }
    }
}

$menStandings   = isset($pollData['standings']['men']) && is_array($pollData['standings']['men']) ? $pollData['standings']['men'] : array();
$womenStandings = isset($pollData['standings']['women']) && is_array($pollData['standings']['women']) ? $pollData['standings']['women'] : array();
arsort($menStandings);
arsort($womenStandings);

$menTotal       = isset($pollData['total_votes']['men']) ? (int) $pollData['total_votes']['men'] : 0;
$womenTotal     = isset($pollData['total_votes']['women']) ? (int) $pollData['total_votes']['women'] : 0;
$votesList      = isset($pollData['votes']) && is_array($pollData['votes']) ? array_reverse($pollData['votes']) : array();

// Extract #1 and #2 for Men
$menLeaderName  = '-';
$menLeaderVotes = 0;
$menSecondName  = '-';
$menSecondVotes = 0;
$mIdx = 0;
foreach ($menStandings as $pName => $pCount) {
    if ($mIdx === 0) {
        $menLeaderName  = $pName;
        $menLeaderVotes = (int) $pCount;
    } elseif ($mIdx === 1) {
        $menSecondName  = $pName;
        $menSecondVotes = (int) $pCount;
    }
    $mIdx++;
}
$menLeaderPct  = $menTotal > 0 ? round(($menLeaderVotes / $menTotal) * 100, 1) : 0;
$menLeadMargin = $menLeaderVotes - $menSecondVotes;

// Extract #1 for Women
$womenLeaderName  = '-';
$womenLeaderVotes = 0;
foreach ($womenStandings as $pName => $pCount) {
    $womenLeaderName  = $pName;
    $womenLeaderVotes = (int) $pCount;
    break;
}
$womenLeaderPct = $womenTotal > 0 ? round(($womenLeaderVotes / $womenTotal) * 100, 1) : 0;

function h($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Hooaja Mängija 2026 — Hääletuse Tulemused (Live) | Paide Linnameeskond</title>
  <link rel="icon" type="image/png" href="assets/img/jalgpall_logos/paide.png">
  <link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            navy: { DEFAULT: '#0b203e', 950: '#07152b' },
            crimson: { DEFAULT: '#be2931' }
          },
          fontFamily: {
            sans: ['"Space Grotesk"', 'sans-serif'],
            display: ['"Marcellus"', 'serif']
          }
        }
      }
    }
  </script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen p-4 sm:p-8">
  <div class="max-w-6xl mx-auto">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 mb-6 border-b border-slate-200">
      <div class="flex items-center gap-3">
        <img src="assets/img/jalgpall_logos/paide.png" alt="Paide LM" class="w-10 h-12 object-contain">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-widest text-crimson">Backend Raport • Hooaeg 2026</span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold uppercase tracking-wider">
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              Hääletus on avatud
            </span>
          </div>
          <h1 class="font-display font-black text-2xl sm:text-3xl text-navy uppercase mt-0.5">Hooaja Mängija Hääletuse Tulemused</h1>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <a href="poll-results.php" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold uppercase tracking-wider hover:bg-emerald-700 transition-colors">
          ↻ Värskenda tulemusi
        </a>
        <a href="data/poll-season-player-2026.csv" download class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold uppercase tracking-wider hover:bg-crimson transition-colors">
          Laadi alla CSV (Excel)
        </a>
        <a href="data/poll-season-player-2026.json" target="_blank" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-navy text-xs font-bold uppercase tracking-wider hover:border-navy transition-colors">
          Ava JSON fail
        </a>
        <a href="index.html#player-of-season-poll" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold uppercase tracking-wider hover:bg-slate-200 transition-colors">
          ← Avalehele hääletama
        </a>
      </div>
    </div>

    <!-- Current Leader / Winner Spotlight Banner -->
    <div class="bg-gradient-to-r from-navy to-navy-950 text-white rounded-2xl p-6 mb-6 shadow-md border border-navy/20 flex flex-col md:flex-row md:items-center justify-between gap-6">
      <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-400/20 text-amber-300 text-xs font-bold uppercase tracking-widest mb-2">
          <span>🏆 Hetkel Juhtimas (Esindusmeeskond)</span>
        </div>
        <h2 class="font-display font-black text-3xl sm:text-4xl uppercase tracking-tight text-white">
          <?php echo h($menLeaderName); ?>
        </h2>
        <p class="text-xs sm:text-sm text-slate-300 mt-1">
          <?php if ($menLeaderVotes > 0): ?>
            Kogunud <strong class="text-white"><?php echo $menLeaderVotes; ?> häält</strong> (<?php echo $menLeaderPct; ?>% kõigist häältest)
            <?php if ($menSecondName !== '-'): ?>
              • Edu 2. koha (<?php echo h($menSecondName); ?>, <?php echo $menSecondVotes; ?> häält) ees on <strong class="text-amber-300">+<?php echo $menLeadMargin; ?> häält</strong>.
            <?php endif; ?>
          <?php else: ?>
            Ühtegi häält pole veel registreeritud.
          <?php endif; ?>
        </p>
      </div>
      <div class="flex items-center gap-4 border-t md:border-t-0 md:border-l border-white/15 pt-4 md:pt-0 md:pl-6">
        <div class="text-left md:text-right">
          <div class="text-[11px] font-bold uppercase tracking-wider text-slate-300">Liidri Häälte Arv</div>
          <div class="font-display font-black text-4xl text-amber-300"><?php echo $menLeaderVotes; ?> <span class="text-sm font-sans font-bold text-white/80">(<?php echo $menLeaderPct; ?>%)</span></div>
          <div class="text-[11px] text-slate-400 mt-0.5">Viimane hääl: <?php echo !empty($votesList) && isset($votesList[0]['date_readable']) ? h($votesList[0]['date_readable']) : '-'; ?></div>
        </div>
      </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
      <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="text-xs font-bold uppercase text-slate-400">Kehtivaid hääli failis</div>
        <div class="font-display font-black text-3xl text-navy mt-1"><?php echo $menTotal + $womenTotal; ?></div>
      </div>
      <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="text-xs font-bold uppercase text-navy">Esindusmeeskond (Mehed)</div>
        <div class="font-display font-black text-3xl text-navy mt-1"><?php echo $menTotal; ?> <span class="text-xs font-sans font-semibold text-slate-400">häält • <?php echo count($menStandings); ?> mängijat</span></div>
      </div>
      <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="text-xs font-bold uppercase text-crimson">Linnanaiskond (Naised)</div>
        <div class="font-display font-black text-3xl text-crimson mt-1"><?php echo $womenTotal; ?> <span class="text-xs font-sans font-semibold text-slate-400">häält • <?php echo count($womenStandings); ?> mängijat</span></div>
      </div>
    </div>

    <!-- Leaderboards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">
      <!-- Men Leaderboard -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-navy text-white flex items-center justify-between">
          <h2 class="font-display font-bold uppercase tracking-wider">Esindusmeeskonna Edetabel</h2>
          <span class="text-xs font-bold bg-white/15 px-2.5 py-1 rounded"><?php echo $menTotal; ?> häält</span>
        </div>
        <div class="p-6">
          <?php if (empty($menStandings)): ?>
            <p class="text-sm text-slate-400">Ühtegi häält pole veel antud.</p>
          <?php else: ?>
            <div class="space-y-3.5">
              <?php 
              $rank = 1;
              foreach ($menStandings as $player => $count): 
                $pct = $menTotal > 0 ? round(($count / $menTotal) * 100, 1) : 0;
                $badgeClass = 'bg-slate-100 text-slate-600';
                if ($rank === 1) $badgeClass = 'bg-amber-400 text-navy font-black';
                elseif ($rank === 2) $badgeClass = 'bg-slate-300 text-navy font-bold';
                elseif ($rank === 3) $badgeClass = 'bg-amber-700/20 text-amber-900 font-bold';
              ?>
                <div>
                  <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                    <div class="flex items-center gap-2">
                      <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[11px] <?php echo $badgeClass; ?>">
                        #<?php echo $rank; ?>
                      </span>
                      <span class="text-navy uppercase text-sm"><?php echo h($player); ?></span>
                      <?php if ($rank === 1): ?>
                        <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-bold uppercase">Liider</span>
                      <?php endif; ?>
                    </div>
                    <span class="text-slate-700 font-bold"><?php echo (int) $count; ?> häält (<?php echo $pct; ?>%)</span>
                  </div>
                  <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full <?php echo $rank === 1 ? 'bg-crimson' : 'bg-navy'; ?> rounded-full transition-all" style="width: <?php echo $pct; ?>%"></div>
                  </div>
                </div>
              <?php 
                $rank++;
              endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Women Leaderboard -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-crimson text-white flex items-center justify-between">
          <h2 class="font-display font-bold uppercase tracking-wider">Linnanaiskonna Edetabel</h2>
          <span class="text-xs font-bold bg-white/15 px-2.5 py-1 rounded"><?php echo $womenTotal; ?> häält</span>
        </div>
        <div class="p-6">
          <?php if (empty($womenStandings)): ?>
            <p class="text-sm text-slate-400">Ühtegi häält pole veel antud.</p>
          <?php else: ?>
            <div class="space-y-3.5">
              <?php 
              $wRank = 1;
              foreach ($womenStandings as $player => $count): 
                $pct = $womenTotal > 0 ? round(($count / $womenTotal) * 100, 1) : 0;
              ?>
                <div>
                  <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                    <div class="flex items-center gap-2">
                      <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[11px] bg-slate-100 text-slate-600">
                        #<?php echo $wRank; ?>
                      </span>
                      <span class="text-navy uppercase text-sm"><?php echo h($player); ?></span>
                    </div>
                    <span class="text-slate-700 font-bold"><?php echo (int) $count; ?> häält (<?php echo $pct; ?>%)</span>
                  </div>
                  <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-crimson rounded-full" style="width: <?php echo $pct; ?>%"></div>
                  </div>
                </div>
              <?php 
                $wRank++;
              endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Voter Log Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-6 py-4 bg-slate-100 border-b border-slate-200 flex items-center justify-between">
        <h2 class="font-display font-bold text-navy uppercase tracking-wider">Kõik Registreeritud Hääled (<?php echo count($votesList); ?>)</h2>
        <span class="text-xs text-slate-500">Viimane uuendus: <?php echo h($pollData['updated_at']); ?></span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm">
          <thead class="bg-slate-50 text-[11px] font-bold uppercase text-slate-500 border-b border-slate-200">
            <tr>
              <th class="py-3 px-4">#</th>
              <th class="py-3 px-4">Aeg</th>
              <th class="py-3 px-4">Kategooria</th>
              <th class="py-3 px-4">Mängija</th>
              <th class="py-3 px-4">Positsioon</th>
              <th class="py-3 px-4">Hääletaja E-post</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (empty($votesList)): ?>
              <tr>
                <td colspan="6" class="py-6 px-4 text-center text-slate-400">Hääli pole veel lisatud.</td>
              </tr>
            <?php else: ?>
              <?php 
              $rowNum = count($votesList);
              foreach ($votesList as $v): ?>
                <tr class="hover:bg-slate-50">
                  <td class="py-3 px-4 text-slate-400 font-mono text-xs"><?php echo $rowNum--; ?></td>
                  <td class="py-3 px-4 text-slate-500 whitespace-nowrap"><?php echo h(isset($v['date_readable']) ? $v['date_readable'] : $v['timestamp']); ?></td>
                  <td class="py-3 px-4 font-bold <?php echo (isset($v['squad']) && $v['squad'] === 'women') ? 'text-crimson' : 'text-navy'; ?>">
                    <?php echo h(isset($v['squad_label']) ? $v['squad_label'] : $v['squad']); ?>
                  </td>
                  <td class="py-3 px-4 font-bold text-navy">
                    #<?php echo h(isset($v['player_number']) ? $v['player_number'] : ''); ?> <?php echo h(isset($v['player_name']) ? $v['player_name'] : ''); ?>
                  </td>
                  <td class="py-3 px-4 text-slate-600"><?php echo h(isset($v['player_role']) ? $v['player_role'] : ''); ?></td>
                  <td class="py-3 px-4 font-mono text-xs text-slate-700"><?php echo h(isset($v['email']) ? $v['email'] : ''); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
