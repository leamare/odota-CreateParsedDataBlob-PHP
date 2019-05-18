<?php 

namespace \CreateParsedDataBlob;

include_once __DIR__ . "populate.php";

/**
 * A processor to compute teamfights that occurred given an event stream
 * */
function processTeamfights($entries, $meta) {
  $currTeamfight;
  $teamfights;
  $intervalState = [];
  $teamfightCooldown = 15;
  $heroToSlot = $meta['hero_to_slot'];

  foreach ($entries as $e) {
    if ($e['type'] === 'killed' && $e['targethero'] && !$e['targetillusion']) {
      // check teamfight state
      $currTeamfight = $currTeamfight ?? [
        'start' => $e['time'] - $teamfightCooldown,
        'end' => null,
        'last_death' => $e['time'],
        'deaths' => 0,
        'players' => array_fill(0, 10, [
          'deaths_pos' => [],
          'ability_uses' => [],
          'ability_targets' => [],
          'item_uses' => [],
          'killed' => [],
          'deaths' => 0,
          'buybacks' => 0,
          'damage' => 0,
          'healing' => 0,
          'gold_delta' => 0,
          'xp_delta' => 0,
        ]),
      ];
      // update the last_death time of the current fight
      $currTeamfight['last_death'] = $e['time'];
      $currTeamfight['deaths'] += 1;
    } else if ($e['type'] === 'interval') {
      // store hero state at each interval for teamfight lookup
      if (!$intervalState[$e['time']]) {
        $intervalState[$e['time']] = [];
      }
      $intervalState[$e['time']][$e['slot']] = $e;
      // check curr_teamfight status
      if ($currTeamfight && $e['time'] - $currTeamfight['last_death'] >= $teamfightCooldown) {
        // close it
        $currTeamfight['end'] = $e['time'];
        // push a copy for post-processing
        $teamfights[] = $currTeamfight;
        // clear existing teamfight
        $currTeamfight = null;
      }
    }
  }

  // fights that didnt end wont be pushed to teamfights array (endgame case)
  // filter only fights where 3+ heroes died
  $teamfights = array_filter($teamfights, function($tf) {
    return $tf['deaths'] >= 3;
  });
  foreach ($teamfights as &$tf) {
    foreach ($tf['players'] as $ind => &$p) {
      // record player's start/end xp for level change computation
      if ($intervalState[$tf['start']] && $intervalState[$tf['end']]) {
        $p['xp_start'] = $intervalState[$tf['start']][$ind]['xp'];
        $p['xp_end'] = $intervalState[$tf['end']][$ind]['xp'];
      }
    }
  }
  
  foreach ($entries as &$e) {
    // check each teamfight to see if this event should be processed as part of that teamfight
    foreach ($teamfights as &$tf) {
      if ($e['time'] >= $tf['start'] && $e['time'] <= $tf['end']) {
        if ($e['type'] === 'killed' && $e['targethero'] && !$e['targetillusion']) {
          populate($e, $tf);
          // reverse the kill entry to find killed hero
          $r = [
            'time' => $e['time'],
            'slot' => $heroToSlot[$e['key']],
          ];
          if ($intervalState[$r['time']] && $intervalState[$r['time']][$r['slot']]) {
            // if a hero dies
            // add to deaths_pos
            // lookup slot of the killed hero by hero name (e.key)
            // get position from intervalstate
            $_values = array_values($intervalState[$r['time']][$r['slot']]);
            $x = $_values[0];
            $y = $_values[1];
            // fill in the copy
            $r['type'] = 'deaths_pos';
            $r['key'] = \json_encode([$x, $y]);
            $r['posData'] = true;
            populate($r, $tf);
            // increment death count for this hero
            $tf['players'][$r['slot']]['deaths'] += 1;
          }
        } else if ($e['type'] === 'buyback_log') {
          // bought back
          if ($tf['players'][$e['slot']]) {
            $tf['players'][$e['slot']]['buybacks'] += 1;
          }
        } else if ($e['type'] === 'damage') {
          // sum damage
          // check if damage dealt to hero and not illusion
          if ($e['targethero'] && !$e['targetillusion']) {
            // check if the damage dealer could be assigned to a slot
            if ($tf['players'][$e['slot']]) {
              $tf['players'][$e['slot']]['damage'] += $e['value'];
            }
          }
        } else if ($e['type'] === 'healing') {
          // sum healing
          // check if healing dealt to hero and not illusion
          if ($e['targethero'] && !$e['targetillusion']) {
            // check if the healing dealer could be assigned to a slot
            if ($tf['players'][$e['slot']]) {
              $tf['players'][$e['slot']]['healing'] += $e['value'];
            }
          }
        } else if ($e['type'] === 'gold_reasons' || $e['type'] === 'xp_reasons') {
          // add gold/xp to delta
          if ($tf['players'][$e['slot']]) {
            $types = [
              'gold_reasons' => 'gold_delta',
              'xp_reasons' => 'xp_delta',
            ];
            $tf['players'][$e['slot']][$types[$e['type']]] += $e['value'];
          }
        } else if ($e['type'] === 'ability_uses' || $e['type'] === 'item_uses') {
          // count skills, items
          populate($e, $tf);
        }
      }
    }
  }
  return $teamfights;
}

?>