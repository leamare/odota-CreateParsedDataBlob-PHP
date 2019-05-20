<?php 

namespace CreateParsedDataBlob\utils {

function is_assoc_array(array $arr) {
  if (is_array($arr) && empty($arr)) return true;
  return array_keys($arr) !== range(0, count($arr) - 1);
}

function is_assoc_array_by_index(string $index) {
  $list = [
    'cosmetics',
    'obs',
    'sen',
    'pos',
    'actions',
    'pings',
    'purchase',
    'gold_reasons',
    'xp_reasons',
    'killed',
    'item_uses',
    'ability_uses',
    'ability_targets',
    'damage_targets',
    'hero_hits',
    'damage',
    'damage_taken',
    'damage_inflictor',
    'runes',
    'killed_by',
    'kill_streaks',
    'multi_kills',
    'life_state',
    'healing',
    'damage_inflictor_received',
  ];

  return \in_array($index, $list);
}

function array_every(array $values, $func) {
  foreach ($values as $value) {
    if (!$func($value)) {
      return false;
    }
  }
  return true;
}

}

?>