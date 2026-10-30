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

$jsonFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'poll-season-player-2026.json';
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

$menStandings   = isset($pollData['standings']['men']) && is_array($pollData['standings']['men']) ? $pollData['standings']['men'] : array();
$womenStandings = isset($pollData['standings']['women']) && is_array($pollData['standings']['women']) ? $pollData['standings']['women'] : array();
$menTotal       = isset($pollData['total_votes']['men']) ? (int) $pollData['total_votes']['men'] : 0;
$womenTotal     = isset($pollData['total_votes']['women']) ? (int) $pollData['total_votes']['women'] : 0;
$votesList      = isset($pollData['votes']) && is_array($pollData['votes']) ? array_reverse($pollData['votes']) : array();

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
  <title>Hooaja Mängija 2026 — Hääletuse Tulemused (Backend) | Paide Linnameeskond</title>
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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 mb-8 border-b border-slate-200">
      <div class="flex items-center gap-3">
        <img src="assets/img/jalgpall_logos/paide.png" alt="Paide LM" class="w-10 h-12 object-contain">
        <div>
          <span class="text-xs font-bold uppercase tracking-widest text-crimson">Backend Raport • Hooaeg 2026</span>
          <h1 class="font-display font-black text-2xl sm:text-3xl text-navy uppercase">Hooaja Mängija Hääletuse Tulemused</h1>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <a href="data/poll-season-player-2026.csv" download class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold uppercase tracking-wider hover:bg-crimson transition-colors">
          Laadi alla CSV (Excel)
        </a>
        <a href="data/poll-season-player-2026.json" target="_blank" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-navy text-xs font-bold uppercase tracking-wider hover:border-navy transition-colors">
          Ava JSON fail
        </a>
        <a href="index.html#player-of-season-poll" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold uppercase tracking-wider hover:bg-slate-200 transition-colors">
          ← Avalehele
        </a>
      </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
      <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="text-xs font-bold uppercase text-slate-400">Hääli kokku</div>
        <div class="font-display font-black text-3xl text-navy mt-1"><?php echo $menTotal + $womenTotal; ?></div>
      </div>
      <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="text-xs font-bold uppercase text-navy">Esindusmeeskond (Mehed)</div>
        <div class="font-display font-black text-3xl text-navy mt-1"><?php echo $menTotal; ?> <span class="text-xs font-sans font-semibold text-slate-400">häält</span></div>
      </div>
      <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="text-xs font-bold uppercase text-crimson">Linnanaiskond (Naised)</div>
        <div class="font-display font-black text-3xl text-crimson mt-1"><?php echo $womenTotal; ?> <span class="text-xs font-sans font-semibold text-slate-400">häält</span></div>
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
            <div class="space-y-3">
              <?php foreach ($menStandings as $player => $count): 
                $pct = $menTotal > 0 ? round(($count / $menTotal) * 100, 1) : 0;
              ?>
                <div>
                  <div class="flex justify-between text-xs font-bold mb-1">
                    <span class="text-navy uppercase"><?php echo h($player); ?></span>
                    <span class="text-slate-600"><?php echo (int) $count; ?> häält (<?php echo $pct; ?>%)</span>
                  </div>
                  <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-navy rounded-full" style="width: <?php echo $pct; ?>%"></div>
                  </div>
                </div>
              <?php endforeach; ?>
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
            <div class="space-y-3">
              <?php foreach ($womenStandings as $player => $count): 
                $pct = $womenTotal > 0 ? round(($count / $womenTotal) * 100, 1) : 0;
              ?>
                <div>
                  <div class="flex justify-between text-xs font-bold mb-1">
                    <span class="text-navy uppercase"><?php echo h($player); ?></span>
                    <span class="text-slate-600"><?php echo (int) $count; ?> häält (<?php echo $pct; ?>%)</span>
                  </div>
                  <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-crimson rounded-full" style="width: <?php echo $pct; ?>%"></div>
                  </div>
                </div>
              <?php endforeach; ?>
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
                <td colspan="5" class="py-6 px-4 text-center text-slate-400">Hääli pole veel lisatud.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($votesList as $v): ?>
                <tr class="hover:bg-slate-50">
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
