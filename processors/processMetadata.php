<?php 

namespace \CreateParsedDataBlob;

include __DIR__ . "../from_camel_case.php";

/**
 * Given an event stream, extracts metadata such as game zero time and hero to slot/ID mappings.
 * */
function processMetadata(&$entries) {
  $heroToSlot = [];
  $slotToPlayerslot = [];
  $heroIdToSlot = [];
  $metaTypes = [
    'interval' => function ($e) {
      // check if hero has been assigned to entity
      if ($e['hero_id']) {
        // grab the end of the name, lowercase it
        $ending = \str_replace('CDOTA_Unit_Hero_', '', $e['unit']);
        // the combat log name could involve replacing camelCase with _ or not!
        // double map it so we can look up both cases
        $combatLogName = "npc_dota_hero_".\strtolower($ending);
        // don't include final underscore here
        // the first letter is always capitalized and will be converted to underscore
        $combatLogName2 = "npc_dota_hero_".\from_camel_case($ending);
        // populate hero_to_slot for combat log mapping
        $heroToSlot[$combatLogName] = $e['slot'];
        $heroToSlot[$combatLogName2] = $e['slot'];
        // populate hero_to_id for multikills
        $heroIdToSlot[$e['hero_id']] = $e['slot'];
      }
    },
    'player_slot' => function ($e) {
      // map slot number (0-9) to playerslot (0-4, 128-132)
      $slotToPlayerslot[$e['key']] = $e['value'];
    },
  ];

  foreach ($entries as $e) {
    if ($metaTypes[ $e['type'] ])
      $metaTypes[ $e['type'] ]($e);
  }

  return [
    'hero_to_slot' => $heroToSlot,
    'slot_to_playerslot' => $slotToPlayerslot,
    'hero_id_to_slot' => $heroIdToSlot,
  ];
}

?>