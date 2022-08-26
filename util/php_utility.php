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

function bytes_to_string(array $bytes): string {
  $out = "";
  foreach ($bytes as $b)
    $out .= chr($b);
  return $out;
}

function get_tower_statuses(array $objectives): array {
  $statuses = [
    "barracks_status_dire" => "0000011111111111",
    "barracks_status_radiant" => "0000011111111111",
    "tower_status_dire" => "00111111",
    "tower_status_radiant" => "00111111",
  ];

  foreach ($objectives as $obj) {
    if ($obj['type'] !== 'building_kill') continue;

    $isRadiant = strpos($obj['key'], 'goodguys') !== false;

    if (strpos($obj['key'], 'tower4')) {
      if (isset($statuses["tower_status_".($isRadiant ? "radiant" : "dire")][ 5 ]))
        $statuses["tower_status_".($isRadiant ? "radiant" : "dire")][ 5 ] = '0';
      else
        $statuses["tower_status_".($isRadiant ? "radiant" : "dire")][ 6 ] = '0';
    } else if (strpos($obj['key'], 'tower')) {
      $params = explode(
        ",",
        preg_replace("/npc_dota_(goodguys|badguys)_tower(\d*)_(.*)/", "\\2,\\3", $obj['key'])
      );
      $lane = $params[1] === 'top' ? 0 : (
        $params[1] === 'mid' ? 1 : 2
      );
      $id = 3 * $lane + $params[0];
      $statuses["tower_status_".($isRadiant ? "radiant" : "dire")][ 16 - $id ] = '0';
    } else if (strpos($obj['key'], 'rax')) {
      $params = explode(
        ",",
        preg_replace("/npc_dota_(goodguys|badguys)_(.*)_rax_(.*)/", "\\2,\\3", $obj['key'])
      );
      $lane = $params[1] === 'top' ? 0 : (
        $params[1] === 'mid' ? 1 : 2
      );
      $isMelee = $params[0] === 'melee' ? 0 : 1;
      
      $id = 2 * $lane + $isMelee;
      $statuses["barracks_status_".($isRadiant ? "radiant" : "dire")][ 8 - $id ] = '0';
    }
  }

  return [
    "barracks_status_dire" => @base_convert($statuses['barracks_status_dire'], 2, 10),
    "barracks_status_radiant" => @base_convert($statuses['barracks_status_radiant'], 2, 10),
    "tower_status_dire" => @base_convert($statuses['tower_status_dire'], 2, 10),
    "tower_status_radiant" => @base_convert($statuses['tower_status_radiant'], 2, 10),
  ];
}

function get_team_data($id) {
  $d = @\file_get_contents(
      "http://api.steampowered.com/IDOTA2Match_570/GetTeamInfoByTeamID/v1?key=".$GLOBALS['steamapikey'].
      "&start_at_team_id=".$id."&teams_requested=1"
    );
  $r = \json_decode(
    $d, 
    true
  );
  if (!empty($r)) return $r['result']['teams'][0];
  return [  ];
}

function get_patch_id($start_time) {
  foreach ($GLOBALS['cpdb_config']['metadata']['patches'] as $i => $patch) {
    $p = $i;
    if (isset($patch['startDate'])) $patch['asOfDateTime'] = \strtotime($patch['startDate']);
    if ($patch['asOfDateTime'] >= $start_time) {
      break;
    }
  }

  //$d = $stratz_pid - $stratz_patches[$p-1]['id'];

  return ($p - 3);
}

}


