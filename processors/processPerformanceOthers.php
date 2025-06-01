<?php 

namespace CreateParsedDataBlob;

function greevilsGreed(&$e, &$container, $meta) {
  if ($e['type'] === 'killed' && isset($e['greevils_greed_stack'])) {
    $alchName = 'npc_dota_hero_alchemist';
    $alchSlot = $meta['hero_to_slot'][$alchName];
    $alchPlayer = $container['players'][$alchSlot];

    if (isset($meta['ability_levels'][$alchName]['alchemist_goblins_greed'])) {
      $ggLvl = $meta['ability_levels'][$alchName]['alchemist_goblins_greed'];
    } else {
      $ggLvl = 0;
      // $goldBase = 2;
      // $goldStack = $e['greevils_greed_stack'] * 2;
    }

    $goldBase = 3;
    $goldStack = $e['greevils_greed_stack'] * 3;

    switch ($ggLvl) {
      case 0: $goldStack = min($goldStack, 18); break;
      case 1: $goldStack = min($goldStack, 18); break;
      case 2: $goldStack = min($goldStack, 21); break;
      case 3: $goldStack = min($goldStack, 24); break;
      case 4: $goldStack = min($goldStack, 27); break;
      default: return;
    }

    $alchPlayer['performance_others'] = \array_replace([
      'greevils_greed_gold' => 0,
    ], $alchPlayer['performance_others'] ?? []);

    $alchPlayer['performance_others']['greevils_greed_gold'] += $goldBase + $goldStack;
  }
}

function track(&$e, &$container, $meta) {
  if (($e['tracked_death'] ?? false) && $e['type'] === "killed") {
    $bhName = 'npc_dota_hero_bountyhunter';
    $bhName_alt = 'npc_dota_hero_bounty_hunter';

    $trackerSlot = $meta['hero_to_slot'][$e['tracked_sourcename']];
    $trackerPlayer = $container['players'][$trackerSlot];

    $trackLvl = $meta['ability_levels'][$bhName]['bounty_hunter_track'] ?? 
      $meta['ability_levels'][$bhName_alt]['bounty_hunter_track'];
    $trackTalentLvl = $meta['ability_levels'][$bhName]['special_bonus_unique_bounty_hunter_3'] ?? 
      $meta['ability_levels'][$bhName_alt]['special_bonus_unique_bounty_hunter_3'];

    $gold = 0;

    switch ($trackLvl) {
      case 1: $gold = 130; break;
      case 2: $gold = 225; break;
      case 3: $gold = 320; break;
      default: return;
    }
    // If the talent is selected add the extra bonus
    if ($trackTalentLvl === 1) {
      // $gold += 250;
      $gold += 45;
    }

    $trackerPlayer['performance_others'] = \array_replace([
      'tracked_deaths' => 0,
      'track_gold' => 0,
    ], $trackerPlayer['performance_others'] ?? []);

    $trackerPlayer['performance_others']['tracked_deaths'] += 1;
    $trackerPlayer['performance_others']['track_gold'] += $gold;
  }
}

function performanceOthers(&$e, &$container, $meta) {
  if (empty($meta)) {
    return;
  }
  greevilsGreed($e, $container, $meta);
  track($e, $container, $meta);
}

