<?php 

namespace CreateParsedDataBlob {
  
  include_once __DIR__ . "/../util/php_utility.php";

  function processProps(&$entries, &$container, $epilogue, $meta) {
    /**
     * Missing stuff that we have no way to get anyhow:
     * 
     * PLAYERS: 
       * ability_upgrades_arr
       * additional_units
       * items: backpack_#, item_#
       * permanent_buffs
       * cosmetics - we can use cosmetics array tho, but I don't think it's necessary slot->[]->item_id
     */

    $epilogue_props = $epilogue['gameInfo_']['dota_'];
    $container['match_id'] = $epilogue_props['matchId_'];
    $container['parse_time'] = time();
    $container['parser_type'] = "clarity-odota;CreateParsedDataBlobPHP";
    
    if (isset($GLOBALS['steamapikey'])) {
      $match_details = file_get_contents("http://api.steampowered.com/IDOTA2Match_570/GetMatchDetails/v1?key=".$GLOBALS['steamapikey']."&match_id=".$container['match_id']);
      $match_details = \json_decode($match_details, true);
    }

    if (!empty($match_details) && !isset($match_details['result']['error'])) {
      $container['match_seq_num'] = $match_details['result']['match_seq_num'];
      $container['negative_votes'] = $match_details['result']['negative_votes'];
      $container['positive_votes'] = $match_details['result']['positive_votes'];
      $container['lobby_type'] = $match_details['result']['match_seq_num'];
      $container['engine'] = $match_details['result']['engine'];
      $container['cluster'] = $match_details['result']['cluster'];

      $container['game_mode'] = $match_details['result']['game_mode'];
      $container['radiant_win'] = $match_details['result']['radiant_win'];
      $container['human_players'] = $match_details['result']['human_players'];
      $container['radiant_score'] = $match_details['result']['radiant_score'];
      $container['dire_score'] = $match_details['result']['dire_score'];

      $container['duration'] = $match_details['result']['duration'];
      $container['start_time'] = $match_details['result']['start_time'];
      $container['end_time'] = $container['start_time'] + $container['duration'];
    } else {
      // null values for those keys where we can't get any kind of value by ourselves
      $container['match_seq_num'] = null;
      $container['negative_votes'] = null;
      $container['positive_votes'] = null;
      $container['lobby_type'] = 1;
      $container['engine'] = 1; // we won't see any other value anyway
      $container['cluster'] = null;

      $container['game_mode'] = $epilogue_props['gameMode_'];
      $container['radiant_win'] = ( $epilogue_props['gameWinner_'] === 2);

      $container['human_players'] = 0;
      foreach ($epilogue_props['playerInfo_'] as $pr) {
        if (isset($pr['steamid_']))
        $container['human_players']++;
      }

      // radiant/dire score by container->killed_by
      // reason: player can be killed by neutrals or deny himself
      $container['radiant_score'] = 0;
      $container['dire_score'] = 0;
      foreach ($container['players'] as $slot => $pl) {
        $is_radiant = ($slot < 5);
        foreach ($pl['killed_by'] as $killer => $deaths) {
          if (strpos($killer, $is_radiant ? "_badguys" : "_goodguys") === false && 
            ( isset($meta['hero_to_slot'][$killer]) && ($meta['hero_to_slot'][$killer] < 5 XOR $is_radiant) ) )
            $container[($is_radiant ? 'dire' : 'radiant').'_score'] += $deaths;
        }
      }

      $container['duration'] = end($container['objectives'])['time'];
      $container['end_time'] = $epilogue_props['endTime_'];
      $container['start_time'] = $container['end_time'] - $container['duration'];
    }

    if (isset($match_details['result']['radiant_team_id'])) {
      $container['radiant_team_id'] = $match_details['result']['radiant_team_id'];
      $container['radiant_team'] = [
        'team_id' => $container['radiant_team_id'],
        'name' => $match_details['result']['radiant_name'],
        'tag' => null,
      ];
    } else if (isset($epilogue_props['radiantTeamId_'])) {
      $container['radiant_team_id'] = $epilogue_props['radiantTeamId_'];
      $container['radiant_team'] = [
        'team_id' => $container['radiant_team_id'],
        'name' => null,
        'tag' => utils\bytes_to_string($epilogue_props['radiantTeamTag_']['bytes']),
      ];
    } else $container['radiant_team_id'] = null;

    if (isset($match_details['result']['dire_team_id'])) {
      $container['dire_team_id'] = $match_details['result']['dire_team_id'];
      $container['dire_team'] = [
        'team_id' => $container['dire_team_id'],
        'name' => $match_details['result']['dire_name'],
        'tag' => null,
      ];
    } else if (isset($epilogue_props['direTeamId_'])) {
      $container['dire_team_id'] = $epilogue_props['direTeamId_'];
      $container['dire_team'] = [
        'team_id' => $container['dire_team_id'],
        'name' => null,
        'tag' => utils\bytes_to_string($epilogue_props['direTeamTag_']['bytes']),
      ];
    } else $container['dire_team_id'] = null;

    if (isset($match_details['result']['leagueid'])) {
      $container['leagueid'] = $epilogue_props['leagueid_'];
    } else if (isset($epilogue_props['leagueid_'])) {
      $container['leagueid'] = $epilogue_props['leagueid_'];
    } else $container['leagueid'] = 0;

    foreach ($container['objectives'] as $obj) {
      if ($obj['type'] == "CHAT_MESSAGE_FIRSTBLOOD") {
        $container['first_blood_time'] = $obj['time'];
      }
    }

    //* barracks_status_side, tower_status_side
    foreach (utils\get_tower_statuses($container['objectives']) as $k => $v)
      $container[$k] = $v;

    // compute throw/comeback levels
    $radiantGoldAdvantage = $container['radiant_gold_adv'];
    $throwVal = $container['radiant_win'] ? max($radiantGoldAdvantage) : min($radiantGoldAdvantage) * -1;
    $comebackVal = $container['radiant_win'] ? min($radiantGoldAdvantage) * -1 : max($radiantGoldAdvantage);
    $lossVal = $container['radiant_win'] ? min($radiantGoldAdvantage) * -1 : max($radiantGoldAdvantage);
    $stompVal = $container['radiant_win'] ? max($radiantGoldAdvantage) : min($radiantGoldAdvantage) * -1;

    if (!$container['radiant_win'])
      $container['throw'] = $throwVal;
    if ($container['radiant_win'])
      $container['comeback'] = $comebackVal;
    if (!$container['radiant_win'])
      $container['loss'] = $lossVal;
    if ($container['radiant_win'])
      $container['stomp'] = $stompVal;

    // some data we have no clue about
    $container['replay_salt'] = null; // we need it to download replays, but there's no easy
      // way to get this parameter, and this tool is made to generate stats for replays
      // that couldn't been accessed otherwise
    $container['series_id'] = null;   // Getting series data requires monitoring of liveLeagueGamesUpdate
    $container['series_type'] = null; // which is not optimal and doesn't really work for us
      // we could request opendota for that info, but it's not optimal
    // $container['region'] = null; // regions are purely odota stuff, so we are skipping it
    // Same goes for patch number, but we are heavily relying on that, so we need it
    $container['patch'] = $GLOBALS['metadata']['patch'];

    foreach ($entries as &$e) {
      switch ($e['type']) {
        case 'interval':
          if (isset($player['hero_id'])) {
            $player =& $container['players'][$e['slot']];
            $player['hero_id'] = $e['hero_id'];
            $player['level'] = $e['level'];
            $player['gold'] = $e['gold'];
            $player['last_hits'] = $e['lh'];
            $player['xp'] = $e['xp'];
            $player['stuns'] = $e['stuns'];
            $player['kills'] = $e['kills'];
            $player['deaths'] = $e['deaths'];
            $player['assists'] = $e['assists'];
            $player['denies'] = $e['denies'];
          }
          break;
        default:
          break;
      }
    }

    foreach ($container['players'] as $slot => &$pl) {
      $pl['isRadiant'] = \odota\core\utils\isRadiant($pl);
      $pl['account_id'] = $match_details['result']['players'][$slot]['account_id'] ?? 
        \odota\core\utils\convert64to32($epilogue_props['playerInfo_'][$slot]['steamid_']);
      $pl['pings'] = $pl['pings'][0];
      $pl['personaname'] = utils\bytes_to_string($epilogue_props['playerInfo_'][$slot]['playerName_']['bytes']);
      $pl['name'] = null;

      $pl['win'] = $pl['isRadiant'] == $container['radiant_win'] ? 1 : 0;
      $pl['lose'] = $pl['win'] ? 0 : 1;

      $pl['kills'] = sizeof($pl['kills_log']);
      $pl['assists'] = \round(($container[($pl['isRadiant'] ? 'radiant' : 'dire').'_score'] * $pl['teamfight_participation']) - $pl['kills']);
      $pl['deaths'] = \array_sum($pl['killed_by']);
      $pl['kda'] = \floor(($pl['kills']+$pl['assists']) / ($pl['deaths']+1));
      $pl['buyback_count'] = sizeof($pl['buyback_log']);
      $pl['total_gold'] = end($pl['gold_t']);
      $pl['gpm'] = \round($pl['total_gold'] / floor($container['duration']/60));
      $pl['total_xp'] = end($pl['xp_t']);
      $pl['xpm'] = \round($pl['total_xp'] / floor($container['duration']/60));

      // hero/tower damage
      $pl['hero_damage'] = 0;
      $pl['tower_damage'] = 0;
      foreach ($pl['damage'] as $k => $dmg) {
        if (strpos($k, "npc_dota_hero") === 0)
          $pl['hero_damage'] += $dmg;
        if (strpos($k, "tower") !== false || strpos($k, "fort") !== false || strpos($k, "rax") !== false)
          $pl['tower_damage'] += $dmg;
      }

      $pl['hero_healing'] = 0;
      foreach ($pl['healing'] as $k => $heal) {
        if (strpos($k, "npc_dota_hero") === false)
          $pl['hero_healing'] += $heal;
      }
      
      // hero/tower damage
      $pl['hero_damage_taken'] = 0;
      foreach ($pl['damage_taken'] as $k => $dmg) {
        if (strpos($k, "npc_dota_hero") === 0)
          $pl['hero_damage_taken'] += $dmg;
      }

      // it's not as accurate, but it's something
      $pl['gold_spent'] = \array_sum($pl['gold_reasons']);
      $pl['gold_spent'] += \floor(($pl['item_uses']['dust'] ?? 0)/2) * _DOTA_Dust_Cost;
      $pl['gold_spent'] += ($pl['item_uses']['ward_observer'] ?? 0) * _DOTA_Obs_Ward_Cost;
      $pl['gold_spent'] += ($pl['item_uses']['ward_sentry'] ?? 0) * _DOTA_Sentry_Ward_Cost;
      $pl['gold_spent'] += ($pl['item_uses']['flask'] ?? 0) * _DOTA_Salve_Cost;
      $pl['gold_spent'] += ($pl['item_uses']['clarity'] ?? 0) * _DOTA_Salve_Cost;
      $pl['gold_spent'] += ($pl['item_uses']['enchanted_mango'] ?? 0) * _DOTA_Mango_Cost;
      $pl['gold_spent'] += ($pl['item_uses']['faerie_fire'] ?? 0) * _DOTA_FaerieFire_Cost;
      $pl['gold_spent'] += ($pl['item_uses']['smoke_of_deceit'] ?? 0) * _DOTA_Smoke_Cost;
      $pl['gold_spent'] += \ceil(($pl['item_uses']['tango'] ?? 0)/3) * _DOTA_Tango_Cost;
      $pl['gold_spent'] += ($pl['item_uses']['tpscroll'] ?? 0) * _DOTA_Scroll_Cost;

      // copy
      $pl['match_id'] = $container['match_id'];
      $pl['radiant_win'] = $container['radiant_win'];
      $pl['start_time'] = $container['start_time'];
      $pl['duration'] = $container['duration'];
      $pl['cluster'] = $container['cluster'];
      $pl['lobby_type'] = $container['lobby_type'];
      $pl['game_mode'] = $container['game_mode'];
      $pl['patch'] = $container['patch'];
      //$pl['region'] = $container['region'];

      $pl['neutral_kills'] = 0;
      $pl['tower_kills'] = 0;
      $pl['courier_kills'] = 0;
      $pl['lane_kills'] = 0;
      $pl['hero_kills'] = 0;
      $pl['observer_kills'] = 0;
      $pl['sentry_kills'] = 0;
      $pl['roshan_kills'] = 0;
      $pl['necronomicon_kills'] = 0;
      $pl['ancient_kills'] = 0;

      foreach ($pl['killed'] as $key => $v) {
        if (strpos($key, 'creep_goodguys') !== false || strpos($key, 'creep_badguys') !== false)
          $pl['lane_kills'] += $v;
        else if (strpos($key, 'observer') !== false)
          $pl['observer_kills'] += $v;
        else if (strpos($key, 'sentry') !== false)
          $pl['sentry_kills'] += $v;
        else if (strpos($key, 'npc_dota_hero') !== false) {
          if ( $meta['hero_to_slot'][$key] != $slot )
            $pl['hero_kills'] += $v;
        } else if (strpos($key, 'npc_dota_neutral') !== false)
          $pl['neutral_kills'] += $v;
        else if (in_array($key, $GLOBALS['metadata']['ancients']))
          $pl['ancient_kills'] += $v;
        else if (strpos($key, '_tower') !== false)
          $pl['tower_kills'] += $v;
        else if (strpos($key, 'courier') !== false)
          $pl['courier_kills'] += $v;
        else if (strpos($key, 'roshan') !== false)
          $pl['roshan_kills'] += $v;
        else if (strpos($key, 'necronomicon') !== false)
          $pl['necronomicon_kills'] += $v;
      }

      if (isset($pl['gold_t']) && isset($pl['gold_t'][10])) {
        // lane efficiency: divide 10 minute gold by static amount based on standard creep spawn
        // var tenMinute = (43 * 60 + 48 * 20 + 74 * 2);
        // 6.84 change
        $melee = (40 * 60);
        $ranged = (45 * 20);
        $siege = (74 * 2);
        $passive = (600 * 1.5);
        $starting = 625;
        $tenMinute = $melee + $ranged + $siege + $passive + $starting;
        $pl['lane_efficiency'] = $pl['gold_t'][10] / $tenMinute;
        $pl['lane_efficiency_pct'] = floor($pl['lane_efficiency'] * 100);
      }

      if ($pl['lane_pos']) {
        $laneData = \odota\core\utils\getLaneFromPosData($pl['lane_pos'], \odota\core\utils\isRadiant($pl));
        $pl['lane'] = $laneData['lane'];
        $pl['lane_role'] = $laneData['lane_role'];
        $pl['is_roaming'] = $laneData['is_roaming'];
      }

      // compute hashes of purchase time sums and counts from logs
      if ($pl['purchase_log']) {
        // remove ward dispenser and recipes
        $pl['purchase_log'] = \array_filter($pl['purchase_log'], function($purchase) {
          !(strpos($purchase['key'], 'recipe_') === 0 || $purchase['key'] === 'ward_dispenser');
        });
        $pl['purchase_time'] = [];
        $pl['first_purchase_time'] = [];
        $pl['item_win'] = [];
        $pl['item_usage'] = [];
        foreach($pl['purchase_log'] as $v) {
          $k = $v['key'];
          $time = $v['time'];
          if (!isset($pl['purchase_time'][$k])) {
            $pl['purchase_time'][$k] = 0;
          }
          // Store first purchase time for every item
          if (!isset($pl['first_purchase_time'][$k])) {
            $pl['first_purchase_time'][$k] = $time;
          }
          $pl['purchase_time'][$k] += $time;
          $pl['item_usage'][$k] = 1;
          $pl['item_win'][$k] = \odota\core\utils\isRadiant(pm) === $pl['radiant_win'] ? 1 : 0;
        }
      }

      if (isset($pl['purchase'])) {
        // account for stacks
        $pl['purchase']['dust'] = ($pl['purchase']['dust'] ?? 0) * 2;
        $pl['purchase_ward_observer'] = $pl['purchase']['ward_observer'] ?? 0;
        $pl['purchase_ward_sentry'] = $pl['purchase']['ward_sentry'] ?? 0;
        $pl['purchase_tpscroll'] = $pl['purchase']['tpscroll'] ?? 0;
        $pl['purchase_rapier'] = $pl['purchase']['rapier'] ?? 0;
        $pl['purchase_gem'] = $pl['purchase']['gem'] ?? 0;
      }

      if (isset($pl['actions']) && isset($pl['duration'])) {
        $actionsSum = 0;
        foreach ($pl['actions'] as $v) {
          $actionsSum += $v;
        }
        $pl['actions_per_min'] = floor(($actionsSum / $pl['duration']) * 60);
      }

      if (isset($pl['life_state'])) {
        $pl['life_state_dead'] = ($pl['life_state'][1] ?? 0) + ($pl['life_state'][2] ?? 0);
      }
    }
  
    return $container;
  }
  
}