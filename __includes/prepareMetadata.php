<?php 

namespace CreateParsedDataBlob {

  function prepareMetadata() {
    $metadata = [];

    $patches = \file_get_contents("https://api.stratz.com/api/v1/GameVersion");
    $patches = \json_decode($patches, true);

    $patches = array_filter($patches, function($v) {
      return strlen($v['name']) < 4;
    });

    $metadata['patch'] = sizeof($patches);

    // FIXME
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

    return $metadata;
  }
}