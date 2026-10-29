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

/**
 * Replay time after which stat-affecting events should be ignored.
 * Matches odota-parser postGame: DOTA_COMBATLOG_GAME_STATE value 6 (POST_GAME).
 */
function get_game_end_time(array $entries): ?int {
  $postGame = null;
  $fortDeath = null;

  foreach ($entries as $e) {
    if (!isset($e['time'])) {
      continue;
    }
    $time = (int)$e['time'];
    $type = $e['type'] ?? '';

    if ($type === 'DOTA_COMBATLOG_GAME_STATE' && (int)($e['value'] ?? -1) === 6) {
      $postGame = $postGame === null ? $time : min($postGame, $time);
    }
    if ($type === 'DOTA_COMBATLOG_DEATH' && strpos($e['targetname'] ?? '', '_fort') !== false) {
      $fortDeath = $fortDeath === null ? $time : max($fortDeath, $time);
    }
  }

  return $postGame ?? $fortDeath;
}

/** Event types still processed after game end (chat etc.). */
function is_post_game_allowed_type(string $type): bool {
  return in_array($type, [
    'chat',
    'chatwheel',
    'cosmetics',
    'epilogue',
    'player_slot',
    'draft_start',
    'draft_timings',
    'DOTA_COMBATLOG_GAME_STATE',
  ], true);
}

function should_skip_post_game_event(array $e, ?int $gameEndTime): bool {
  if ($gameEndTime === null || !isset($e['time']) || (int)$e['time'] <= $gameEndTime) {
    return false;
  }
  return !is_post_game_allowed_type((string)($e['type'] ?? ''));
}

function str_check_multiple(string $haystack, array $needles) {
  foreach ($needles as $value) {
    if (strpos($haystack, $value) !== false) {
      return true;
    }
  }
  return false;
}

function bytes_to_string(array $bytes): string {
  $out = "";
  foreach ($bytes as $b)
    $out .= chr($b);
  return mb_scrub($out, 'UTF-8');
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

/**
 * Player slot for a combat-log unit. Spirit bear item uses are Lone Druid's.
 * */
function hero_slot_for_unit(array $heroToSlot, ?string $name): ?int {
  if ($name === null || $name === '') {
    return null;
  }
  if (isset($heroToSlot[$name])) {
    return $heroToSlot[$name];
  }
  if (strpos($name, 'npc_dota_lone_druid_bear') === 0) {
    return $heroToSlot['npc_dota_hero_lone_druid'] ?? null;
  }
  return null;
}

/**
 * GET Steam Web API paths in parallel. Port 80 does not answer, and PHP
 * streams fail TLS here, so this uses curl with a short timeout.
 * Values are decoded JSON, or null on transport / HTTP error.
 * */
function steam_get_many(array $pathsByKey): array {
  $out = [];
  foreach ($pathsByKey as $label => $_) {
    $out[$label] = null;
  }
  $apiKey = trim((string)($GLOBALS['cpdb_config']['steamapikey'] ?? ''));
  if ($apiKey === '' || !$pathsByKey || !function_exists('curl_multi_init')) {
    return $out;
  }

  $mh = curl_multi_init();
  $handles = [];
  foreach ($pathsByKey as $label => $path) {
    $ch = curl_init('https://api.steampowered.com/'.ltrim($path, '/'));
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 4,
      CURLOPT_CONNECTTIMEOUT => 2,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[$label] = $ch;
  }

  do {
    $status = curl_multi_exec($mh, $active);
    if ($active) {
      if (curl_multi_select($mh, 1.0) === -1) {
        usleep(10000);
      }
    }
  } while ($active && $status === CURLM_OK);

  foreach ($handles as $label => $ch) {
    $body = curl_multi_getcontent($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (is_string($body) && $code >= 200 && $code < 300) {
      $json = json_decode($body, true);
      if (is_array($json)) {
        $out[$label] = $json;
      }
    }
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
  }
  curl_multi_close($mh);
  return $out;
}

function steam_get(string $pathAndQuery): ?array {
  $fetched = steam_get_many(['_' => $pathAndQuery]);
  return $fetched['_'] ?? null;
}

function remember_team_data($id, ?array $payload): void {
  $row = $payload['result']['teams'][0] ?? [];
  $GLOBALS['cpdb_config']['team_cache'][(string)$id] = is_array($row) ? $row : [];
}

function get_team_data($id) {
  $sid = (string)$id;
  if (isset($GLOBALS['cpdb_config']['team_cache'][$sid])) {
    return $GLOBALS['cpdb_config']['team_cache'][$sid];
  }
  $key = rawurlencode(trim((string)($GLOBALS['cpdb_config']['steamapikey'] ?? '')));
  $r = steam_get(
    "IDOTA2Match_570/GetTeamInfoByTeamID/v1?key=".$key.
    "&start_at_team_id=".$id."&teams_requested=1"
  );
  $row = $r['result']['teams'][0] ?? [];
  $GLOBALS['cpdb_config']['team_cache'][$sid] = is_array($row) ? $row : [];
  return $GLOBALS['cpdb_config']['team_cache'][$sid];
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


