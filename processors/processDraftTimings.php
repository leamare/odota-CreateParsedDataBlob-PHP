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
  $sumActiveTeam = 0;
  $draftStart = 0;

  // to override incorrect draft orders from odota parser
  // 0 is first pick/ban team, 1 is second
  // TODO: load it to out of main code to metadata
  $order_mask = [
    0, 1, 0, 1, // bans 1
    0, 1, 1, 0, // picks 1
    0, 1, 0, 1, 0, 1, // bans 2
    1, 0, 0, 1, // picks 2
    0, 1, 0, 1, // bans 3
    0, 1, // picks 3
  ];

  $order_team = [];

  foreach ($entries as $i => &$e) {
    $heroId = $e['hero_id'] ?? null;
    if (isset($e['type']) && $e['type'] === 'draft_timings') {
      if (empty($order_team) && $e['draft_order'] == 1) {
        $order_team[ 0 ] = $e['draft_active_team'];
        $order_team[ 1 ] = $e['draft_active_team'] == 3 ? 2 : 3;
      } else {
        $e['draft_active_team'] = $order_team[ $order_mask[ $e['draft_order']-1 ] ];
      }
  
      $currpickban = [
        'order' => $e['draft_order']-1,
        'pick' => $e['pick'],
        'active_team' => $e['draft_active_team'] == 3 ? 2 : 3,
        'hero_id' => $e['hero_id'],
        'player_slot' => $e['pick'] === true ? $heroIdToSlot[$heroId] : null,
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
  if (sizeof($draftTimings) !== 0) {
    foreach ($draftTimings as &$dt) {
      if ($dt['order'] === 1) {
        $dt['total_time_taken'] = ($dt['time'] - $draftStart);
      } else {
        $index2;
        // find the time of the end of the previous order
        foreach ($draftTimings as $i => &$currpick) {
          if ($currpick['order'] === ($dt['order'])) {
            $index2 = $i;
          }
        }
        // calculate the timings
        $dt['total_time_taken'] = ($dt['time'] - $draftTimings[$index2]['time']);
      }
    }
  }
  // remove the time, no need for it
  foreach ($draftTimings as &$dt) {
    unset($dt['time']);
  }

  return $draftTimings;
}

}
