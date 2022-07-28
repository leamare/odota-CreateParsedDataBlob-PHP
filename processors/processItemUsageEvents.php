<?php 

namespace CreateParsedDataBlob {

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
          $event = [
            'slot' => $meta['hero_to_slot'][ $e['attackername'] ],
            'time' => $e['time'],
            'affected' => 0,
            'affected_players_slots' => [],
          ];
          $event['x_cell'] = ($positions[ $event['slot'] ] ?? [])['x'] ?? 0;
          $event['y_cell'] = ($positions[ $event['slot'] ] ?? [])['y'] ?? 0;
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
              $delta_x = abs( (($positions[$slot] ?? [])['x'] ?? 0) - $event['x_cell'] );
              $delta_y = abs( (($positions[$slot] ?? [])['y'] ?? 0) - $event['y_cell'] );
              if ($delta_x <= 10 && $delta_y <= 10) {
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

    foreach($entries as &$e) {
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