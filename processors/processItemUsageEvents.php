<?php 

namespace CreateParsedDataBlob {

  include_once __DIR__ . "/../util/php_utility.php";

  /**
   * Goes down through events data and records the most recent player position
   * and monitors item usage.
   * Effectively is smokes processor.
   * */
  function processItemUsageEvents(&$entries, &$container, &$meta) {
    $positions = [];
    $smokeEvents = [];

    $recentEvents = [];

    $types = [
      'interval' => function($e) use (&$positions) {
        if (!isset($e['x']) || !isset($e['y']))
          return;
        $positions[ $e['slot'] ] = [ 
          'x' => $e['x'], 
          'y' => $e['y'],
        ];
      },
      'DOTA_COMBATLOG_ITEM' => function($e) use (&$recentEvents, &$meta, &$positions) {
        if ($e['inflictor'] === 'item_smoke_of_deceit') {
          // Bear (and other non-hero units) are not in hero_to_slot.
          $slot = utils\hero_slot_for_unit($meta['hero_to_slot'], $e['attackername'] ?? null);
          if ($slot === null) {
            return;
          }
          $event = [
            'slot' => $slot,
            'time' => $e['time'],
            'affected' => 0,
            'affected_players_slots' => [],
          ];
          // The bear is not on the hero interval grid, so Lone Druid's last
          // position would reject allies who were actually next to the bear.
          $isBear = strpos((string)($e['attackername'] ?? ''), 'npc_dota_lone_druid_bear') === 0;
          if ($isBear) {
            $event['x_cell'] = null;
            $event['y_cell'] = null;
          } else {
            $event['x_cell'] = ($positions[ $event['slot'] ] ?? [])['x'] ?? 0;
            $event['y_cell'] = ($positions[ $event['slot'] ] ?? [])['y'] ?? 0;
          }
          $recentEvents[] =& $event;
        }
      },
      'DOTA_COMBATLOG_MODIFIER_ADD' => function($e) use (&$recentEvents, &$meta, &$positions) {
        if ($e['inflictor'] === 'modifier_smoke_of_deceit') {
          if (strpos($e['targetname'], "npc_dota_hero_") === FALSE) return;
          $slot = $meta['hero_to_slot'][ $e['targetname'] ];
          $isRadiant = $slot < 5;
          foreach($recentEvents as &$event) {
            if ($isRadiant == ($event['slot'] < 5)) {
              $unknownPos = $event['x_cell'] === null || $event['y_cell'] === null;
              $delta_x = abs( (($positions[$slot] ?? [])['x'] ?? 0) - ($event['x_cell'] ?? 0) );
              $delta_y = abs( (($positions[$slot] ?? [])['y'] ?? 0) - ($event['y_cell'] ?? 0) );
              if ($unknownPos || ($delta_x <= 10 && $delta_y <= 10)) {
                $event['affected']++;
                $event['affected_players_slots'][] = $slot;
              }
            }
          }
        }
      },
      'DOTA_COMBATLOG_MODIFIER_REMOVE' => function($e) use (&$recentEvents, &$meta, &$smokeEvents) {
        if ($e['inflictor'] === 'modifier_smoke_of_deceit') {
          if (strpos($e['targetname'], "npc_dota_hero_") === FALSE) return;
          $slot = $meta['hero_to_slot'][ $e['targetname'] ];
          $isRadiant = $slot < 5;
          foreach($recentEvents as $id => $event) {
            if (\in_array($slot, $event['affected_players_slots']))  {
              $smokeEvents[] = $event;
              unset($recentEvents[$id]);
            }
          }
        }
      },
      'DOTA_COMBATLOG_DEATH'  => function($e) use (&$meta, &$positions) {
        if (strpos($e['targetname'], "npc_dota_hero_") === FALSE) return;
        $slot = $meta['hero_to_slot'][ $e['targetname'] ];
        $positions[$slot] = [ 
          'x' => null, 
          'y' => null,
        ];
      }
    ];

    $gameEndTime = $meta['game_end_time'] ?? null;

    foreach($entries as &$e) {
      if (utils\should_skip_post_game_event($e, $gameEndTime)) {
        continue;
      }
      if (isset($types[ $e['type'] ])) {
        $types[ $e['type'] ]($e);
      } else {
        // console.log('parser emitted unhandled type: %s', e.type);
      }
    }

    $container['lrg_appends']['smokes'] = $smokeEvents;
    foreach($smokeEvents as $event) {
      $slot = $event['slot'];
      if (!isset($container['players'][$slot]['smokes']))
        $container['players'][$slot]['smokes'] = [];
      $container['players'][$slot]['smokes'][] = $event;
    }

    return $container;
  }
}