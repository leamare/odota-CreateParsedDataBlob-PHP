<?php 

namespace odota\core\utils {

/**
 * Provides some utility functions from odota/core utils.
 * */

function isRadiant($player) {
  return $player['player_slot'] < 128;
}

/*
 * Converts a steamid 64 to a steamid 32
 *
 * Takes and returns a string
 */
function convert64to32($id) {
  $accountID = \bcsub((string)$id, '76561197960265728');
  //$w = bcdiv($accountID, 2)*2 + bcmod($accountID, '2');
  //return 'STEAM_0:'.bcmod($accountID, '2').':'.bcdiv($accountID, 2);
  return $accountID;
}

/*
 * Converts a steamid 32 to a steamid 64
 *
 * Takes and returns a string
 */
function convert32to64($id) {
  $accountID = \bcadd($id, '76561197960265728');
  return $accountID;
}

/**
 * The anonymous account ID used as a placeholder for player with match privacy settings on
 * */
function getAnonymousAccountId() {
  return 4294967295;
}

/**
 * Computes the lane a hero is in based on an input hash of positions
 * */
function getLaneFromPosData($lanePos, $isRadiant) {
  // compute lanes
  $lanes = [];
  // iterate over the position hash and get the lane bucket for each data point
  foreach ($lanePos as $x => $xv) {
    foreach($xv as $y => $val) {
      $adjX = (int)$x - 64;
      $adjY = 128 - ((int)$y - 64);
      // Add it N times to the array
      for ($i = 0; $i < $val; $i++) {
        if (isset($GLOBALS['cpdb_config']['metadata']['laneMappings'][$adjY]) && isset($GLOBALS['cpdb_config']['metadata']['laneMappings'][$adjY][$adjX])) {
          $lanes[] = $GLOBALS['cpdb_config']['metadata']['laneMappings'][$adjY][$adjX];
        }
      }
    }
  }
  
  [ 'mode' => $lane, 'count' => $count ] = modeWithCount($lanes);
  /**
  * Player presence on lane. Calculated by the count of the prominant
  * lane (`count` of mode) divided by the presence on all lanes (`lanes.length`).
  * Having low presence (<45%) probably means the player is roaming.
  * */
  $isRoaming = ($count / sizeof($lanes)) < 0.45;

  // Roles, currently doesn't distinguish between carry/support in safelane
  // 1 safelane
  // 2 mid
  // 3 offlane
  // 4 jungle
  $laneRoles = [
    // bot
    1 => $isRadiant ? 1 : 3,
    // mid
    2 => 2,
    // top
    3 => $isRadiant ? 3 : 1,
    // radiant jungle
    4 => 4,
    // dire jungle
    5 => 4,
  ];
  
  return [
    'lane' => $lane,
    'lane_role' => $laneRoles[$lane],
    'is_roaming' => $isRoaming,
  ];
}

/**
 * Finds the mode and its occurrence count in the input array
 * */
function modeWithCount($array) {
  if (!sizeof($array)) {
    return [];
  }
  $modeMap = [];
  $maxEl = $array[0];
  $maxCount = 1;
  foreach($array as $i => $el) {
    if (empty($modeMap[$el])) $modeMap[$el] = 1;
    else $modeMap[$el] += 1;
    if ($modeMap[$el] > $maxCount) {
      $maxEl = $el;
      $maxCount = $modeMap[$el];
    }
  }
  return [ 'mode' => $maxEl, 'count' => $maxCount ];
}

function mode($array) {
  return modeWithCount($array)['mode'];
}

}

