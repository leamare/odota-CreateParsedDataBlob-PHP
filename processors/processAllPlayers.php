<?php 

namespace CreateParsedDataBlob;

include_once __DIR__ . "/../util/utility.php";
include_once __DIR__ . "/../util/php_utility.php";

/**
 * Compute data requiring all players in a match for storage in match table
 * */
function processAllPlayers($entries, $meta) {
  $goldAdvTime = [];
  $xpAdvTime = [];
  $res = [
    'radiant_gold_adv' => [],
    'radiant_xp_adv' => [],
  ];

  $gameEndTime = $meta['game_end_time'] ?? null;

  foreach ($entries as $e) {
    if (utils\should_skip_post_game_event($e, $gameEndTime)) {
      continue;
    }
    if ($e['time'] >= 0 && $e['time'] % 60 === 0 && $e['type'] === 'interval') {
      $g = \odota\core\utils\isRadiant([
        'player_slot' => $meta['slot_to_playerslot'][$e['slot']],
      ]) ? $e['gold'] : -$e['gold'];
      $x = \odota\core\utils\isRadiant([
        'player_slot' => $meta['slot_to_playerslot'][$e['slot']],
      ]) ? $e['xp'] : -$e['xp'];
      $goldAdvTime[$e['time']] = isset($goldAdvTime[$e['time']]) ? $goldAdvTime[$e['time']] + $g : $g;
      $xpAdvTime[$e['time']] = isset($xpAdvTime[$e['time']]) ? $xpAdvTime[$e['time']] + $x : $x;
    }
  }

  $order = array_keys($goldAdvTime);
  usort($order, function($a, $b) {
    return (int)$a - (int)$b;
  });
  foreach ($order as $k) {
    $res['radiant_gold_adv'][] = $goldAdvTime[$k];
    $res['radiant_xp_adv'][] = $xpAdvTime[$k];
  }

  return $res;
}

