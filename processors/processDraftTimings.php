<?php 

namespace \CreateParsedDataBlob;

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
  $previousActiveTeam = 0;
  foreach ($entries as $i => &$e) {
    $heroId = e.hero_id;
    if ($e['type'] === 'draft_timings') {
      // The active team needs to be downshifted by 1, so ignore the final observation.
      if ($i < (sizeof($entries) - 1)) {
        $sumActiveTeam += $e['draft_active_team'];
      }
      $currpickban = [
        'order' => $e['draft_order'],
        'pick' => $e['pick'],
        'active_team' => $previousActiveTeam,
        'hero_id' => $e['hero_id'],
        'player_slot' => $e['pick'] === true ? $heroIdToSlot[$heroId] : null,
        'time' => $e['time'],
        'extra_time' => $e['draft_active_team'] === 2 ? $e['draft_extime0'] : $e['draft_extime1'],
        'total_time_taken' => 0,
      ];
      $draftTimings[] = $currpickban;
      $previousActiveTeam = $e['draft_active_team'];
    }
  }
  // ignore Source 1 games
  if (sizeof($draftTimings) !== 0) {
    // update the team that had the first pick/ban
    $draftTimings[0]['active_team'] = (($sumActiveTeam % 2) + 2);
    foreach ($draftTimings as &$dt) {
      if ($dt['order'] === 1) {
        $dt['total_time_taken'] = ($dt['time']);
      } else {
        $index2;
        // find the time of the end of the previous order
        foreach ($draftTimings as $i => &$currpick) {
          if ($currpick['order'] === ($dt['order'] - 1)) {
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

?>
