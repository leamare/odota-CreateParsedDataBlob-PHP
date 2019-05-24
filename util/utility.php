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
  $accountID = bcsub($id, '76561197960265728');
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
  $accountID = bcadd($id, '76561197960265728');
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
  /*
  $lanes = [];
  // iterate over the position hash and get the lane bucket for each data point
  Object.keys(lanePos).forEach((x) => {
    Object.keys(lanePos[x]).forEach((y) => {
      const val = lanePos[x][y];
      const adjX = Number(x) - 64;
      const adjY = 128 - (Number(y) - 64);
      // Add it N times to the array
      for (let i = 0; i < val; i += 1) {
        if (laneMappings[adjY] && laneMappings[adjY][adjX]) {
          lanes.push(laneMappings[adjY][adjX]);
        }
      }
    });
  });
  const { mode: lane, count } = modeWithCount(lanes);
  /**
  * Player presence on lane. Calculated by the count of the prominant
  * lane (`count` of mode) divided by the presence on all lanes (`lanes.length`).
  * Having low presence (<45%) probably means the player is roaming.
  * *//*
  const isRoaming = (count / lanes.length) < 0.45;

  // Roles, currently doesn't distinguish between carry/support in safelane
  // 1 safelane
  // 2 mid
  // 3 offlane
  // 4 jungle
  const laneRoles = {
    // bot
    1: isRadiant ? 1 : 3,
    // mid
    2: 2,
    // top
    3: isRadiant ? 3 : 1,
    // radiant jungle
    4: 4,
    // dire jungle
    5: 4,
  };
  return {
    lane,
    lane_role: laneRoles[lane],
    is_roaming: isRoaming,
  };
  */
}

}

?>