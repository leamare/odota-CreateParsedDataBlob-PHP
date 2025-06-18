<?php 

namespace CreateParsedDataBlob;

function processUploadProps(&$entries) {
  $container = [
    'player_map' => [],
  ];
  foreach ($entries as $e) {
    // $dota;
    switch ($e['type']) {
      case 'epilogue':
        $dota = json_decode($e['key'])['gameInfo_']['dota_'];
        $container['match_id'] = $dota['matchId_'];
        $container['game_mode'] = $dota['gameMode_'];
        $container['radiant_win'] = $dota['gameWinner_'] === 2;
        // TODO following needs some extraction/transformation
        // container.picks_bans = dota.picksBans_;
        // require('fs').writeFileSync('./outputEpilogue.json', JSON.stringify(JSON.parse(e.key)));
        break;
      case 'interval':
        if (!isset($container['player_map'][$e['player_slot']])) {
          $container['player_map'][$e['player_slot']] = [];
        }
        $container['player_map'][$e['player_slot']]['hero_id'] = $e['hero_id'];
        $container['player_map'][$e['player_slot']]['level'] = $e['level'];
        $container['player_map'][$e['player_slot']]['kills'] = $e['kills'];
        $container['player_map'][$e['player_slot']]['deaths'] = $e['deaths'];
        $container['player_map'][$e['player_slot']]['assists'] = $e['assists'];
        $container['player_map'][$e['player_slot']]['denies'] = $e['denies'];
        $container['player_map'][$e['player_slot']]['last_hits'] = $e['lh'];
        $container['player_map'][$e['player_slot']]['gold'] = $e['gold'];
        $container['player_map'][$e['player_slot']]['xp'] = $e['xp'];
        if (!isset($container['player_map'][$e['player_slot']]['variant'])) {
          $container['player_map'][$e['player_slot']]['variant'] = $e['variant'];
        }
        if (!isset($container['player_map'][$e['player_slot']]['facet_hero_id'])) {
          $container['player_map'][$e['player_slot']]['facet_hero_id'] = $e['facet_hero_id'];
        }
        break;
      default:
        break;
    }
  }
  return $container;
}
