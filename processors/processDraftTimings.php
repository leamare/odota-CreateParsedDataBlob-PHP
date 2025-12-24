<?php 

namespace CreateParsedDataBlob {

/**
 * This processor grabs the draft timings from the parsed replay.
 * This is ideally to be used only for captain modes formats.
 * The output is:
 * order: the order of the pick or ban (1-20) (10 bans and 10 picks)
 * pick: whether the draft stage was a pick or ban. pick == true, ban == false
 * active_team: the active team during the draft stage (2 or 3) if 0
 * then not captains mode. Added check to ignore no CM games.
 * hero_id: the id of the hero banned or picked in the draft stage
 * player_slot: null for bans, the player_slot assoicated with the hero_id
 * time: removed for total time taken
 * extra_time: how much of the extra time is left at the end of the draft stage
 * total_time_taken: the time taken for the draft stage
 */

function processDraftTimings(&$entries, &$meta) {
  $draftTimings = [];
  $heroIdToSlot = $meta['hero_id_to_slot'];
  $slotToPlayerSlot = $meta['slot_to_playerslot'];
  $sumActiveTeam = 0;
  $draftStart = 0;

  // to override incorrect draft orders from odota parser
  // 0 is first pick/ban team, 1 is second
  // TODO: load it to out of main code to metadata
  if ($meta['game_mode'] == 2 || $meta['game_mode'] == 8) {
    if ($meta['patch_id'] < 53) {
      $order_mask = [
        0, 1, 0, 1, // bans 1
        0, 1, 1, 0, // picks 1
        0, 1, 0, 1, 0, 1, // bans 2
        1, 0, 0, 1, // picks 2
        0, 1, 0, 1, // bans 3
        0, 1, // picks 3
      ];
      $order_mask_ispick = [
        0, 0, 0, 0, // bans 1
        1, 1, 1, 1, // picks 1
        0, 0, 0, 0, 0, 0, // bans 2
        1, 1, 1, 1, // picks 2
        0, 0, 0, 0, // bans 3
        1, 1, // picks 3
      ];
    } else if ($meta['patch_id'] < 59) {
      $order_mask = [
        0, 1, 0, 1, // bans 1
        0, 1, 1, 0, // picks 1
        0, 1, 0, 1, 0, 1, // bans 2
        1, 0, 0, 1, // picks 2
        0, 1, 0, 1, // bans 3
        0, 1, // picks 3
      ];
      $order_mask_ispick = [
        0, 0, 0, 0, // bans 1
        1, 1, 1, 1, // picks 1
        0, 0, 0, 0, 0, 0, // bans 2
        1, 1, 1, 1, // picks 2
        0, 0, 0, 0, // bans 3
        1, 1, // picks 3
      ];
    } else {
      $order_mask = [
        0, 0, 1, 1, 0, 1, 1, // bans 1
        0, 1, // picks 1
        0, 0, 1, // bans 2
        1, 0, 0, 1, 1, 0, // picks 2
        0, 1, 0, 1, // bans 3
        0, 1, // picks 3
      ];
      $order_mask_ispick = [
        0, 0, 0, 0, 0, 0, 0, // bans 1
        1, 1, // picks 1
        0, 0, 0, // bans 2
        1, 1, 1, 1, 1, 1, // picks 2
        0, 0, 0, 0, // bans 3
        1, 1, // picks 3
      ];
    }
  } else if($meta['game_mode'] == 16) {
    $order_mask = [
      0, 1, 0, 1, 0, 1, //bans
      // bans order has to be fixed later
      // after the teams are assigned
      // first three always go to radiant
      0, 1, 1, 0,
      0, 1, 1, 0, 
      0, 1,
    ];
    $order_mask_ispick = [
      0, 0, 0, 0, 0, 0,
      1, 1, 1, 1,
      1, 1, 1, 1, 
      1, 1,
    ];
  }

  $order_team = [];

  foreach ($entries as $i => &$e) {
    $heroId = $e['hero_id'] ?? null;
    if (isset($e['type']) && $e['type'] === 'draft_timings') {
      if (isset($order_mask)) {
        if (empty($order_team) && $e['draft_order'] == 1) {
          $order_team[ 0 ] = $e['draft_active_team'];
          $order_team[ 1 ] = $e['draft_active_team'] == 3 ? 2 : 3;
        } else {
          $e['draft_active_team'] = $order_team[ $order_mask[ $e['draft_order']-1 ] ];
        }
      }
  
      $currpickban = [
        'order' => $e['draft_order']-1,
        'pick' => $e['pick'],
        'active_team' => $e['draft_active_team'] == 3 ? 2 : 3,
        'hero_id' => $e['hero_id'],
        'player_slot' => $e['pick'] === true ? $slotToPlayerSlot[ $heroIdToSlot[$heroId] ] : null,
        'time' => $e['time'],
        'extra_time' => $e['draft_active_team'] === 2 ? $e['draft_extime0'] : $e['draft_extime1'],
        'total_time_taken' => 0,
      ];
      $draftTimings[] = $currpickban;
    } else if ($e['type'] === 'draft_start') {
      $draftStart = $e['time'];
    }
  }
  // ignore Source 1 games
  if (count($draftTimings) !== 0) {
    foreach ($draftTimings as $i => $dt) {
      if ($dt['order'] === 1) {
        $draftTimings[$i]['total_time_taken'] = ($dt['time'] - $draftStart);
      } else {
        $index2 = 0;
        // find the time of the end of the previous order
        foreach ($draftTimings as $i => $currpick) {
          if ($currpick['order'] === ($dt['order'])) {
            $index2 = $i;
          }
        }
        // calculate the timings
        $draftTimings[$i]['total_time_taken'] = ($dt['time'] - $draftTimings[$index2]['time']);
      }
    }
  }

  $teams = [
    2 => null, 3 => null
  ];
  // remove the time, no need for it
  // also find out which team is which
  foreach ($draftTimings as $dt) {
    if (isset($dt['player_slot']) && !isset($teams[ $dt['active_team'] ]) && $dt['time'] != $draftStart) {
      $heroId = $dt['hero_id'] ?? null;
      if (isset($heroId)) {
        $teams[ $dt['active_team'] ] = $dt['player_slot'] < 128;
        $teams[ $dt['active_team'] == 3 ? 2 : 3 ] = !$teams[ $dt['active_team'] ];
        break;
      }
    }
    // unset($dt['time']);
  }
  $fpRadiant = null;
  $reprocess = 0;
  $repbans = 0; $sidebans = [0,0];
  foreach ($draftTimings as $i => $dt) {
    $draftTimings[$i]['team'] = !$teams[ $dt['active_team'] ];
    if (!isset($fpRadiant) && $dt['pick'] && $dt['time'] != $draftStart) {
      $fpRadiant = $draftTimings[$i]['team'] == $order_mask[$i];
    }
    if ($dt['time'] == $draftStart) {
      $reprocess++;
      if (!$dt['pick']) {
        $repbans++;
        
      }
    }
  }
  for ($i=0; $i<$reprocess; $i++) {
    if ($order_mask_ispick[$i]) continue;
    $sidebans[ $fpRadiant ? $order_mask[$i] : !$order_mask[$i] ]++;
  }


  if ($reprocess > 1) {
    if($meta['game_mode'] == 16) {
      $unprocessed = $order_mask;

      for ($i=0; $i<$reprocess; $i++) {
        if ($i < 6) {
          $side = $sidebans[0] > 0 ? 1 : 0;
          $sidebans[!$side]--;
        } else {
          $side = $draftTimings[$i]['player_slot'] < 128;
        }

        foreach($unprocessed as $st => $dside) {
          if (!isset($unprocessed[$st])) {
            continue;
          }

          if ((($fpRadiant && ($order_mask[$st] == !$side)) || 
              (!$fpRadiant && ($order_mask[$st] == $side)) 
              && $order_mask_ispick[$st] == $draftTimings[$i]['pick']
            )
          ) {
            $draftTimings[$i]['team'] = !$side;
            $draftTimings[$i]['order'] = $st;
            unset($unprocessed[$st]);
            break;
          }
        }
      }
    }
    if($meta['game_mode'] == 2) {
      $unprocessed = $order_mask;

      for ($i=0; $i<$reprocess; $i++) {
        $j = ($i-$repbans) % floor($reprocess/2);
        
        if ($draftTimings[$i]['pick']) {
          $side = $draftTimings[$i]['player_slot'] < 128;
        } else {
          $side = $sidebans[0] > 0 ? 1 : 0;
          $sidebans[!$side]--;
        }

        foreach($unprocessed as $st => $dside) {
          if (!isset($unprocessed[$st])) {
            continue;
          }

          if (
            (
              ($fpRadiant && ($order_mask[$st] == !$side)) || 
              (!$fpRadiant && ($order_mask[$st] == $side))
            ) && $order_mask_ispick[$st] == $draftTimings[$i]['pick']
          ) {
            $draftTimings[$i]['team'] = !$side;
            $draftTimings[$i]['order'] = $st;
            unset($unprocessed[$st]);
            break;
          }
        }
      }
    }
  }
  usort($draftTimings, function($a, $b) {
    return $a['order'] <=> $b['order'];
  });

  return $draftTimings;
}

}
