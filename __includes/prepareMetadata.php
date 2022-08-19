<?php 

namespace CreateParsedDataBlob {

  CONST _DOTA_Dust_Cost = 180;
  CONST _DOTA_Obs_Ward_Cost = 50;
  CONST _DOTA_Sentry_Ward_Cost = 75;
  CONST _DOTA_Salve_Cost = 110;
  CONST _DOTA_Mango_Cost = 70;
  CONST _DOTA_FaerieFire_Cost = 70;
  CONST _DOTA_Smoke_Cost = 80;
  CONST _DOTA_Tango_Cost = 90;
  CONST _DOTA_Scroll_Cost = 50;
  CONST _DOTA_Clarity_Cost = 50;

  function prepareMetadata() {
    $metadata = [];

    if (\file_exists(__DIR__ ."/../stratzkey")) {
      $stratzkey = \trim(\file_get_contents(__DIR__ ."/../stratzkey"));
    }

    if (file_exists(__DIR__."/../.stratz_gameversion.json")) {
      $patches = \file_get_contents(__DIR__."/../.stratz_gameversion.json");
    } else {
      $patches = \file_get_contents("https://api.stratz.com/api/v1/GameVersion".
        (!empty($stratzkey) ? $stratzkey : "" )
      );

      if (!empty($patches)) {
        file_put_contents(__DIR__."/../.stratz_gameversion.json", $patches);
      }
    }
    
    $patches = \json_decode($patches, true);

    // Altho we are using Stratz API for patches list, we are using OpenDota format
    // which is using new IDs only for major (non-letter) patches
    $patches = \array_values(
      \array_filter($patches, function($v) {
        return (strlen($v['name']) < 5) || ($v['id'] < 137 && $v['name'][ 4 ] == 'a');
      })
    );
    usort($patches, function($a, $b) { return $a['id'] <=> $b['id']; });

    $metadata['patches'] = $patches;

    // FIXME: 
    $metadata['ancients'] = [
      "neutral_black_drake",
      "neutral_black_dragon",
      "neutral_granite_golem",
      "neutral_elder_jungle_stalker",
      "neutral_prowler_acolyte",
      "neutral_prowler_shaman",
      "neutral_rock_golem",
      "neutral_small_thunder_lizard",
      "neutral_jungle_stalker",
      "neutral_big_thunder_lizard"
    ];

    /**
     * Creates a 2D array of lane mappings (x,y) to lane constant IDs
     * */
    $laneMappings = [];
    for ($i = 0; $i < 128; $i += 1) {
      $laneMappings[] = [];
      for ($j = 0; $j < 128; $j += 1) {
        $lane;
        if (\abs($i - (127 - $j)) < 8) {
          $lane = 2; // mid
        } else if ($j < 27 || $i < 27) {
          $lane = 3; // top
        } else if ($j >= 100 || $i >= 100) {
          $lane = 1; // bot
        } else if ($i < 50) {
          $lane = 5; // djung
        } else if ($i >= 77) {
          $lane = 4; // rjung
        } else {
          $lane = 2; // mid
        }
        $laneMappings[$i][] = $lane;
      }
    }
    $metadata['laneMappings'] = $laneMappings;

    return $metadata;
  }
}