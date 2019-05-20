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

}

?>