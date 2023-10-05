<?php 

namespace CreateParsedDataBlob {

/**
 * Strips off "item_" from strings, and nullifies dota_unknown.
 * Does not mutate the original string.
 * */
function translate($input) {
  if ($input === 'dota_unknown') {
    return null;
  }
  if (!empty($input) && \strpos($input, 'item_') === 0) {
    return \substr($input, 5);
  }
  return $input;
}

/**
 * Prepends illusion_ to string if illusion
 * */
function computeIllusionString($input, $isIllusion) {
  return ($isIllusion ? 'illusion_' : '').$input;
}

/**
 * Produces a new output array with expanded entries from the original input array
 * */
function processExpand(&$entries, &$meta) {
  $output = [];
  /**
   * Place a copy of the entry in the output
   * */
  $expand = function($e) use (&$output, &$meta) {
    // set slot and player_slot
    $slot = $e['slot'] ?? (isset($e['unit']) && isset($meta['hero_to_slot'][ $e['unit'] ]) ? $meta['hero_to_slot'][ $e['unit'] ] : null);
    $output[] = array_replace($e, [
      'slot' => $slot,
      'player_slot' => $meta['slot_to_playerslot'][$slot] ?? $slot,
    ]);
  };

  // Tracks current aegis holder so we can ignore kills that pop aegis
  $aegisHolder = null;

  // Used to ignore meepo clones killing themselves
  $aegisDeathTime = null;

  $types = [
    'DOTA_COMBATLOG_DAMAGE' => function($e) use ($expand, &$meta) {
      // damage
      $unit = $e['sourcename'];
      $key = computeIllusionString($e['targetname'], $e['targetillusion']);
      $inflictor = translate($e['inflictor']);
      $expand( \array_replace($e, [
        'unit' => $unit,
        'key'  => $key,
        'type' => 'damage',
      ]) );
      // check if this damage happened to a real hero
      if ($e['targethero'] && !$e['targetillusion']) {
        // reverse
        $expand([
          'time' => $e['time'],
          'value'=> $e['value'],
          'unit' => $key,
          'key'  => $unit,
          'type' => 'damage_taken',
        ]);
        $expand([
          'value'=> $e['value'],
          'unit' => $unit,
          'key'  => [$inflictor, translate($e['targetname'])],
          'type' => 'damage_targets',
        ]);
        // count a hit on a real hero with this inflictor
        $expand([
          'time' => $e['time'],
          'value'=> 1,
          'unit' => $unit,
          'key'  => $inflictor,
          'type' => 'hero_hits',
        ]);
        // don't count self-damage for the following
        if ($key !== $unit) {
          // count damage dealt to a real hero with this inflictor
          $expand([
            'time' => $e['time'],
            'value'=> $e['value'],
            'unit' => $unit,
            'key'  => $inflictor,
            'type' => 'damage_inflictor',
          ]);
          // biggest hit on a hero
          $expand([
            'type' => 'max_hero_hit',
            'time' => $e['time'],
            'max'  => true,
            'inflictor' => $inflictor,
            'unit' => $unit,
            'key'  => $key,
            'value'=> $e['value'],
          ]);
          if (!empty($e['sourcename']) && strpos($e['sourcename'], 'npc_dota_hero_') !== false) {
            $expand([
              'time' => $e['time'],
              'value'=> $e['value'],
              'type' => 'damage_inflictor_received',
              'unit' => $key,
              'key'  => $inflictor,
            ]);
          }
        }
      }
    },
    'DOTA_COMBATLOG_HEAL' => function($e) use ($expand, &$meta) {
      // healing
      $expand(\array_replace($e, [
        'unit' => $e['sourcename'],
        'key'  => computeIllusionString( $e['targetname'], $e['targetillusion'] ),
        'type' => 'healing',
      ]));
    },
    'DOTA_COMBATLOG_MODIFIER_ADD' => function($e) use ($expand, &$aegisHolder, &$meta) {
      // gain buff/debuff
      // e.attackername // unit that buffed (use source to get the hero? chen/enchantress)
      // e.inflictor // the buff
      // e.targetname // target of buff (possibly illusion)

      // Aegis expired
      if ($e['inflictor'] === 'modifier_aegis_regen') {
        $aegisHolder = null;
      }
    },
    'DOTA_COMBATLOG_MODIFIER_REMOVE' => function($e = null) use ($expand, &$meta) {
      // modifier_lost
      // lose buff/debuff
      // this is really only useful if we want to try to "time" modifiers
      // e.targetname is unit losing buff (possibly illusion)
      // e.inflictor is name of buff
    },
    'DOTA_COMBATLOG_DEATH' => function($e) use ($expand, &$aegisHolder, &$aegisDeathTime, &$meta) {
      $unit = $e['sourcename'];
      $key = computeIllusionString($e['targetname'], $e['targetillusion']);

      // If it is a building kill
      if (\strpos($e['targetname'], '_tower') !== false
          || \strpos($e['targetname'], '_rax_') !== false
          || \strpos($e['targetname'], '_healers') !== false
          || \strpos($e['targetname'], '_fort') !== false ) {
        $expand([
          'time' => $e['time'],
          'type' => 'building_kill',
          'unit' => $unit,
          'key'  => $key,
        ]);
      }

      if (isset($meta['hero_to_slot'][$key]) && $meta['hero_to_slot'][$key] === $aegisHolder) {
        // The aegis holder was killed
        if ($aegisDeathTime === null) {
          // It is the first time they have been killed this tick
          // If the hero is meepo than the clones will also get killed
          $aegisDeathTime = $e['time'];
          return;
        } if ($aegisDeathTime !== $e['time']) {
          // We are after the aegis death tick, so clear everything
          $aegisDeathTime = null;
          $aegisHolder = null;
        } else {
          // We are on the same tick, so it is a clone dying
          return;
        }
      }

      // Ignore suicides
      if ($e['attackername'] === $key) {
        return;
      }

      // If a hero was killed log extra information
      if ($e['targethero'] && !$e['targetillusion']) {
        $expand([
          'time' => $e['time'],
          'unit' => $unit,
          'key'  => $key,
          'tracked_death' => $e['tracked_death'] ?? null,
          'tracked_sourcename' => $e['tracked_sourcename'] ?? null,
          'type' => 'kills_log',
        ]);
        // reverse
        $expand([
          'time' => $e['time'],
          'unit' => $key,
          'key'  => $unit,
          'type' => 'killed_by',
        ]);
      }

      $expand(\array_replace($e, [
        'unit' => $unit,
        'key'  => $key,
        'type' => 'killed',
        // Dota Plus patch added a value field to this event type, but we want to always treat it as 1
        'value'=> 1,
      ]));
    },
    'DOTA_COMBATLOG_ABILITY' => function($e) use ($expand, &$meta) {
      // Value field is 1 or 2 for toggles
      // ability use
      $expand([
        'time' => $e['time'],
        'unit' => $e['attackername'],
        'key'  => translate($e['inflictor']),
        'type' => 'ability_uses',
      ]);
      // target of ability
      if ($e['targethero'] && !$e['targetillusion']) {
        $expand([
          'time' => $e['time'],
          'unit' => $e['attackername'],
          'key'  => [ translate($e['inflictor']), translate($e['targetname']) ],
          'type' => 'ability_targets',
        ]);
      }
    },
    'DOTA_ABILITY_LEVEL' => function($e) use ($expand, &$meta) {
      $expand([
        'time' => $e['time'],
        'unit' => $e['targetname'],
        'level' => $e['abilitylevel'],
        'key'  => translate($e['valuename']),
        'type' => 'ability_levels',
      ]);
    },
    'DOTA_COMBATLOG_ITEM' => function($e) use ($expand, &$meta) {
      // item use
      $expand([
        'time' => $e['time'],
        'unit' => $e['attackername'],
        'key'  => translate($e['inflictor']),
        'type' => 'item_uses',
      ]);
    },
    'DOTA_COMBATLOG_LOCATION' => function($e = null) use ($expand, &$meta) {
      // not in replay?
    },
    'DOTA_COMBATLOG_GOLD' => function($e) use ($expand, &$meta) {
      // gold gain/loss
      $expand([
        'time' => $e['time'],
        'value' => $e['value'],
        'unit' => $e['targetname'],
        'key' => $e['gold_reason'],
        'type' => 'gold_reasons',
      ]);
    },
    'DOTA_COMBATLOG_GAME_STATE' => function($e = null) use ($expand, &$meta) {
      // state
    },
    'DOTA_COMBATLOG_XP' => function ($e) use ($expand, &$meta) {
      // xp gain
      $expand([
        'time' => $e['time'],
        'value' => $e['value'],
        'unit' => $e['targetname'],
        'key' => $e['xp_reason'],
        'type' => 'xp_reasons',
      ]);
    },
    'DOTA_COMBATLOG_PURCHASE' => function($e) use ($expand, &$meta) {
      // purchase
      $unit = $e['targetname'];
      $key = translate($e['valuename']);
      $expand([
        'time' => $e['time'],
        'value' => 1,
        'unit' => $unit,
        'key' => $key,
        'charges' => $e['charges'] ?? 0,
        'type' => 'purchase',
      ]);
      // don't include recipes in purchase logs
      if (strpos($key, 'recipe_') !== 0) {
        $expand([
          'time' => $e['time'],
          'value' => 1,
          'unit' => $unit,
          'key' => $key,
          'charges' => $e['charges'] ?? 0,
          'type' => 'purchase_log',
        ]);
      }
    },
    'DOTA_COMBATLOG_BUYBACK' => function($e) use ($expand, &$meta) {
      // buyback
      $expand([
        'time' => $e['time'],
        'slot' => $e['value'],
        'type' => 'buyback_log',
      ]);
    },
    'DOTA_COMBATLOG_ABILITY_TRIGGER' => function($e = null) use ($expand, &$meta) {
      // ability_trigger
      // only seems to happen for axe spins
      // e.attackername //unit triggered on?
      // e.inflictor; //ability triggered?
      // e.targetname //unit that triggered the skill
    },
    'DOTA_COMBATLOG_PLAYERSTATS' => function($e = null) use ($expand, &$meta) {
      // player_stats
      // Don't really know what this does, following fields seem to be populated
      // attackername
      // targetname
      // targetsourcename
      // value (1-15)
    },
    'DOTA_COMBATLOG_MULTIKILL' => function($e) use ($expand, &$meta) {
      // multikill
      // add the "minimum value", as of 2016-02-06
      // remove the "minimum value", as of 2016-06-23
      $expand([
        'time' => $e['time'],
        'value' => 1,
        'unit' => $e['attackername'],
        'key' => $e['value'],
        'type' => 'multi_kills',
      ]);
    },
    'DOTA_COMBATLOG_KILLSTREAK' => function($e) use ($expand, &$meta) {
      // killstreak
      // add the "minimum value", as of 2016-02-06
      // remove the "minimum value", as of 2016-06-23
      $expand([
        'time' => $e['time'],
        'value' => 1,
        'unit' => $e['attackername'],
        'key' => $e['value'],
        'type' => 'kill_streaks',
      ]);
    },
    'DOTA_COMBATLOG_TEAM_BUILDING_KILL' => function($e = null) use ($expand, &$meta) {
      // team_building_kill
      // System.err.println(cle);
      // e.attackername,  unit that killed the building
      // e.value, this is only really useful if we can get WHICH tower/rax was killed
      // 0 is other?
      // 1 is tower?
      // 2 is rax?
      // 3 is ancient?
    },
    'DOTA_COMBATLOG_FIRST_BLOOD' => function($e = null) use ($expand, &$meta) {
      // first_blood
      // time, involved players?
    },
    'DOTA_COMBATLOG_MODIFIER_REFRESH' => function($e = null) use ($expand, &$meta) {
      // modifier_refresh
      // no idea what this means
    },
    'pings' => function($e) use ($expand, &$meta) {
      // we're not breaking pings into subtypes atm so just set key to 0 for now
      $expand([
        'time' => $e['time'],
        'type' => 'pings',
        'slot' => $e['slot'],
        'key' => 0,
      ]);
    },
    'actions' => function($e) use ($expand, &$meta) {
      // expand the actions
      $expand(\array_replace($e, [
        'value' => 1
      ]));
    },
    'CHAT_MESSAGE_RUNE_PICKUP' => function($e) use ($expand, &$meta) {
      $expand([
        'time' => $e['time'],
        'value' => 1,
        'type' => 'runes',
        'slot' => $e['player1'],
        'key' => (string)$e['value'],
      ]);
      $expand([
        'time' => $e['time'],
        'key' => $e['value'],
        'slot' => $e['player1'],
        'type' => 'runes_log',
      ]);
    },
    'CHAT_MESSAGE_RUNE_BOTTLE' => function($e = null) use ($expand, &$meta) {
      // not tracking rune bottling atm
    },
    'CHAT_MESSAGE_HERO_KILL' => function($e = null) use ($expand, &$meta) {
      // player, assisting players
      // player2 killed player 1
      // subsequent players assisted
      // still not perfect as dota can award kills to players when they're killed by towers/creeps
      // chat event does not reflect this
      // e.slot = e.player2;
      // e.key = String(e.player1);
      // currently disabled in favor of combat log kills
    },
    'CHAT_MESSAGE_GLYPH_USED' => function($e = null) use ($expand, &$meta) {
      // team glyph
      // player1 = team that used glyph (2/3, or 0/1?)
      // e.team = e.player1;
    },
    'CHAT_MESSAGE_PAUSED' => function($e = null) use ($expand, &$meta) {
      // e.slot = e.player1;
      // player1 paused
    },
    'CHAT_MESSAGE_TOWER_KILL' => function($e = null) use ($expand, &$meta) {
    },
    'CHAT_MESSAGE_TOWER_DENY' => function($e = null) use ($expand, &$meta) {
      // tower (player/team)
      // player1 = slot of player who killed tower (-1 if nonplayer)
      // value (2/3 radiant/dire killed tower, recently 0/1?)
    },
    'CHAT_MESSAGE_BARRACKS_KILL' => function($e = null) use ($expand, &$meta) {
      // barracks (player)
      // value id of barracks based on power of 2?
      // Barracks can always be deduced
      // They go in incremental powers of 2
      // starting by the Dire side to the Dire Side, Bottom to Top, Melee to Ranged
      // so Bottom Melee Dire Rax = 1 and Top Ranged Radiant Rax = 2048.
    },
    'CHAT_MESSAGE_RECONNECT' => function($e) use ($expand, &$meta) {
      $expand([
        'time' => $e['time'],
        'type' => 'connection_log',
        'slot' => $e['player1'],
        'event' => 'reconnect',
      ]);
    },
    'CHAT_MESSAGE_DISCONNECT_WAIT_FOR_RECONNECT' => function($e) use ($expand, &$meta) {
      $expand([
        'time' => $e['time'],
        'type' => 'connection_log',
        'slot' => $e['player1'],
        'event' => 'disconnect',
      ]);
    },
    'CHAT_MESSAGE_FIRSTBLOOD' => function($e) use ($expand, &$meta) {
      $expand([
        'time' => $e['time'],
        'type' => $e['type'],
        'slot' => $e['player1'],
        'key' => $e['player2'],
      ]);
    },
    'CHAT_MESSAGE_AEGIS' => function($e) use ($expand, &$aegisHolder, &$meta) {
      $aegisHolder = $e['player1'];

      $expand([
        'time' => $e['time'],
        'type' => $e['type'],
        'slot' => $e['player1'],
      ]);
    },
    'CHAT_MESSAGE_AEGIS_STOLEN' => function($e) use ($expand, &$aegisHolder, &$meta) {
      $aegisHolder = $e['player1'];

      $expand([
        'time' => $e['time'],
        'type' => $e['type'],
        'slot' => $e['player1'],
      ]);
    },
    'CHAT_MESSAGE_DENIED_AEGIS' => function($e) use ($expand, &$meta) {
      // aegis (player)
      // player1 = slot who picked up/denied/stole aegis
      $expand([
        'time' => $e['time'],
        'type' => $e['type'],
        'slot' => $e['player1'],
      ]);
    },
    'CHAT_MESSAGE_ROSHAN_KILL' => function($e) use ($expand, &$meta) {
      // player1 = team that killed roshan? (2/3)
      $expand([
        'time' => $e['time'],
        'type' => $e['type'],
        'team' => $e['player1'],
      ]);
    },
    'CHAT_MESSAGE_COURIER_LOST' => function($e) use ($expand, &$meta) {
      // player1 = team that lost courier? (2/3)
      $expand([
        'time' => $e['time'],
        'type' => $e['type'],
        'team' => $e['player1'],
      ]);
    },
    'CHAT_MESSAGE_HERO_NOMINATED_BAN' => function($e = null) use ($expand) {
      // TODO
    },
    'CHAT_MESSAGE_HERO_BANNED' => function($e = null) use ($expand) {
      // TODO
    },
    'chat' => function($e) use ($expand) {
      // e.slot = name_to_slot[e.unit];
      // push a copy to chat
      $expand($e);
    },
    'chatwheel' => function($e) use ($expand) {
      $expand($e);
    },
    'interval' => function($e) use ($expand) {
      if ($e['time'] >= 0) {
        $expand($e);
        $types = [
          'stuns',
          'life_state',
          'obs_placed',
          'sen_placed',
          'creeps_stacked',
          'camps_stacked',
          'rune_pickups',
          'randomed',
          'repicked',
          'pred_vict',
          'firstblood_claimed',
          'teamfight_participation',
          'towers_killed',
          'roshans_killed',
          'observers_placed',
        ];

        foreach ($types as $field) {
          // $key;
          // $value;
          if ($field === 'life_state') {
            $key = $e[$field] ?? null;
            $value = 1;
          } else {
            $key = $field;
            $value = $e[$field] ?? null;
          }
          $expand([
            'time' => $e['time'],
            'slot' => $e['slot'],
            'type' => $field,
            'key'  => $key,
            'value'=> $value,
          ]);
        }
        // if on minute, add to interval arrays
        if ($e['time'] % 60 === 0) {
          $expand([
            'time' => $e['time'],
            'slot' => $e['slot'],
            'interval' => true,
            'type' => 'times',
            'value' => $e['time'],
          ]);
          $expand([
            'time' => $e['time'],
            'slot' => $e['slot'],
            'interval' => true,
            'type' => 'gold_t',
            'value' => $e['gold'],
          ]);
          $expand([
            'time' => $e['time'],
            'slot' => $e['slot'],
            'interval' => true,
            'type' => 'xp_t',
            'value' => $e['xp'],
          ]);
          $expand([
            'time' => $e['time'],
            'slot' => $e['slot'],
            'interval' => true,
            'type' => 'lh_t',
            'value' => $e['lh'],
          ]);
          $expand([
            'time' => $e['time'],
            'slot' => $e['slot'],
            'interval' => true,
            'type' => 'dn_t',
            'value' => $e['denies'],
          ]);
        }
      }
      // store player position for the first 10 minutes
      if ($e['time'] <= 600 && isset($e['x']) && isset($e['y'])) {
        $expand([
          'time' => $e['time'],
          'slot' => $e['slot'],
          'type' => 'lane_pos',
          'key' => \json_encode([ $e['x'], $e['y'] ]),
          'posData' => true,
        ]);
      }
    },
    'obs' => function($e) use ($expand) {
      $expand(\array_replace($e, [
        'type' => 'obs',
        'posData' => true,
      ]));
      $expand(\array_replace($e, [
        'type' => 'obs_log',
      ]));
    },
    'sen' => function($e) use ($expand) {
      $expand(\array_replace($e, [
        'type' => 'sen',
        'posData' => true,
      ]));
      $expand(\array_replace($e, [
        'type' => 'sen_log',
      ]));
    },
    'obs_left' => function($e) use ($expand) {
      $expand(\array_replace($e, [
        'type' => 'obs_left_log',
      ]));
    },
    'sen_left' => function($e) use ($expand) {
      $expand(\array_replace($e, [
        'type' => 'sen_left_log',
      ]));
    },
    'epilogue' => function($e) use ($expand) {
      $expand($e);
    },
    'player_slot' => function($e) use ($expand) {
      $expand(array_replace($e, [
        'slot' => $e['key'],
      ]));
    },
    'cosmetics' => function($e) use ($expand) {
      $expand($e);
    },
  ];

  foreach($entries as &$e) {
    if (isset($types[ $e['type'] ])) {
      $types[ $e['type'] ]($e);
    } else {
      // console.log('parser emitted unhandled type: %s', e.type);
    }
  }

  return $output;
}

}
