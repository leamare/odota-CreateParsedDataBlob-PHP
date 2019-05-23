<?php 

namespace CreateParsedDataBlob {
  
  include_once __DIR__ . "/../util/php_utility.php";

  function processProps(&$entries, &$container, $epilogue, $meta) {
    $epilogue_props = \json_decode($epilogue['key'], true)['gameInfo']['dota_'];
    $container['match_id'] = $epilogue_props['matchId_'];
    
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
        $is_radiant = !($slot < 5);
        foreach ($pl['killed_by'] as $killer => $deaths) {
          if (strpos($is_radiant ? "_badguys" : "_goodguys") !== false || 
            ( isset($meta['hero_to_slot'][$killer]) && ($meta['hero_to_slot'][$killer] < 128 XOR $is_radiant) ) )
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


    foreach ($entries as &$e) {
      switch (e.type) {
        case 'interval':
          $player =& $container['players'][$e['player_slot']];
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
          break;
        default:
          break;
      }
    }

    foreach ($container['players'] as $slot => &$pl) {
      $pl['isRadiant'] = !($slot < 5);
      // accountid
      // xpm
      // gpm
      // hero/tower damage
      // gold spent
      // healing
      // 

      // copy
      // matchid
      //* radiant_win, start_time, duration, cluster, lobby_type, game_mode, patch, region
      // win, lose
    }
  
    return $container;
  }
  
}

/**
 * props to find:
 * 
 * barracks_status_side, tower_status_side (?)
 * replay_salt
 * series_id
 * series_type
 * patch
 * region
 * comeback, stomp, loss, throw - recalculate
 * 
 * players:
 * matchid
 * ability_upgrades_arr
 * account_id
 * additional_units
 * items: backpack_#, item_# (?) -- or skip
 * hero_damage
 * gold_spent -- purchases + lost gold?
 * hero_healing -- sum healing
 * permanent_buffs - ???
 * personaname - epilogue, name - null
 * tower_damage
 * xp_per_min
 * radiant_win, start_time, duration, cluster, lobby_type, game_mode, patch, region
 * isRadiant
 * win, lose
 * total_gold, total_xp
 * lane -- seek for utils
 * cosmetics - slot->[]->item_id
 * 
 * 
 * {"time":-860,"type":"cosmetics","key":"{\"9986\":3,\"5634\":128,\"9988\":3,\"6020\":130,\"6277\":3,\"6021\":130,\"9990\":3,\"647\":132,\"9991\":3,\"5639\":128,\"4361\":129,\"9482\":132,\"9483\":132,\"9741\":3,\"5776\":131,\"6291\":132,\"9749\":0,\"4764\":129,\"6432\":1,\"6433\":1,\"8865\":129,\"8994\":0,\"8866\":129,\"6435\":1,\"6948\":128,\"6054\":132,\"8871\":129,\"6952\":128,\"7595\":0,\"6830\":129,\"8627\":130,\"10424\":0,\"6714\":128,\"8130\":130,\"5957\":4,\"8919\":0,\"8920\":0,\"11998\":0,\"6504\":3,\"8176\":4,\"5105\":129,\"6004\":1,\"6136\":130,\"7033\":4,\"6137\":130,\"6138\":130,\"6139\":130,\"7036\":4,\"6140\":130,\"7935\":1,\"5631\":128}"}
 */
