<?php 

namespace CreateParsedDataBlob\LogParse {

include_once __DIR__ . "/../util/php_utility.php";

$significantModifiers = [
  'modifier_stunned' => 1,
  'modifier_smoke_of_deceit' => 1,
  'modifier_silence' => 1,
];

$insignificantDeaths = [
  'npc_dota_creep',
  'npc_dota_neutral',
];

function translate($s) {
  return $s === 'dota_unknown' ? null : $s;
}

/**
 * A processor to reduce the event stream to only logs we want to persist
 * */
function processReduce(&$entries, &$meta) {
  $result = array_filter($entries, function($e) {
    if ($e['type'] === 'DOTA_COMBATLOG_PURCHASE'
      // || $e['type'] === 'DOTA_COMBATLOG_BUYBACK'
      || ($e['type'] === 'DOTA_COMBATLOG_DEATH' && \utils\array_every($insignificantDeaths, function($prefix) { return strpos($e['targetname'], $prefix) !== 0; } ) ) 
      // || ($e['type'] === 'DOTA_COMBATLOG_MODIFIER_ADD' && isset($significantModifiers[$e['inflictor']]) && $e['targethero'])
      // ($e['type'] === 'DOTA_COMBATLOG_DAMAGE' && $e['targethero']) ||
      // || ($e['type'] === 'DOTA_COMBATLOG_HEAL' && $e['targethero'])
      // || $e['type'] === 'CHAT_MESSAGE_AEGIS'
      // || $e['type'] === 'CHAT_MESSAGE_AEGIS_STOLEN'
      // || $e['type'] === 'CHAT_MESSAGE_DENIED_AEGIS'
      // || $e['type'] === 'CHAT_MESSAGE_ROSHAN_KILL'
      // || $e['type'] === 'CHAT_MESSAGE_BARRACKS_KILL'
      // || $e['type'] === 'CHAT_MESSAGE_TOWER_KILL'
      // || $e['type'] === 'CHAT_MESSAGE_SCAN_USED'
      // || $e['type'] === 'CHAT_MESSAGE_GLYPH_USED'
      // || $e['type'] === 'CHAT_MESSAGE_PAUSED'
      // || $e['type'] === 'CHAT_MESSAGE_UNPAUSED'
      // || $e['type'] === 'CHAT_MESSAGE_RUNE_PICKUP'
      // || $e['type'] === 'CHAT_MESSAGE_FIRSTBLOOD'
      // || $e['type'] === 'CHAT_MESSAGE_DISCONNECT_WAIT_FOR_RECONNECT'
      // || $e['type'] === 'CHAT_MESSAGE_RECONNECT'
      // || $e['type'] === 'obs'
      // || $e['type'] === 'sen'
      // || $e['type'] === 'obs_left'
      // || $e['type'] === 'sen_left'
      // || $e['type'] === 'chat') {
      ) {
        return (bool)$e['time'];
    }
    return false;
  });
  $result = array_map(function($e) {
    $e2 = \array_replace($e, [
      'match_id' => $meta['match_id'],
      'attackername_slot' => $meta['slot_to_playerslot'][$meta['hero_to_slot'][$e['attackername']]],
      'targetname_slot' => $meta['slot_to_playerslot'][$meta['hero_to_slot'][$e['targetname']]],
      'sourcename_slot' => $meta['slot_to_playerslot'][$meta['hero_to_slot'][$e['sourcename']]],
      'targetsourcename_slot' => $meta['slot_to_playerslot'][$meta['hero_to_slot'][$e['targetname']]],
      'player1_slot' => $meta['slot_to_playerslot'][$e['player1']],
      'player_slot' => $e['player_slot'] || $meta['slot_to_playerslot'][$e['slot']],
      'inflictor' => translate($e['inflictor']),
    ]);
    return $e2;
  }, $result);

  /*
  $count = [];
  foreach ($result as $r) {
    $count[$r['type']] = ($count[$r['type']] ?? 0) + 1;
  }
  
  var_dump(count);
  */
  return $result;
}

}

?>
