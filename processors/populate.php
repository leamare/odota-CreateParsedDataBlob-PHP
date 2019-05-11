<?php 

namespace \CreateParsedDataBlob;

include_once __DIR__ . "performanceOthers.php";
include_once __DIR__ . "../util/php_utility.php";

function populate($e, $container, $meta) {
  switch ($e['type']) {
    case 'interval':
      break;
    case 'player_slot':
      $container['players'][$e['key']]['player_slot'] = $e['value'];
      break;
    case 'chat':
    case 'chatwheel':
      $container['chat'][] = \json_decode( \json_encode($e), true );
      break;
    case 'cosmetics':
      $container['cosmetics'] =  \json_decode( $e['key'] );
      break;
    case 'CHAT_MESSAGE_FIRSTBLOOD':
    case 'CHAT_MESSAGE_COURIER_LOST':
    case 'CHAT_MESSAGE_AEGIS':
    case 'CHAT_MESSAGE_AEGIS_STOLEN':
    case 'CHAT_MESSAGE_DENIED_AEGIS':
    case 'CHAT_MESSAGE_ROSHAN_KILL':
    case 'building_kill':
      $container['objectives'][] = \json_decode( \json_encode($e), true );
      break;
    default:
      if (!$container['players'][$e['slot']]) {
      // couldn't associate with a player, probably attributed to a creep/tower/necro unit
      // console.log(e);
        return;
      }
      $t = $container['players'][$e['slot']][$e['type']];
      if (!isset($t)) {
      // container.players[0] doesn't have a type for this event
      // console.log("no field in parsed_data.players for %s", e.type);

      } else if ($e['posData']) {
      // fill 2d hash with x,y values
        $key = \json_decode( $e['key'] );
        $x = $key[0];
        $y = $key[1];
        if (!$t[$x]) {
          $t[$x] = [];
        }
        if (!$t[$x][$y]) {
          $t[$x][$y] = 0;
        }
        $t[$x][$y] += 1;
      } else if ($e['max']) {
      // check if value is greater than what was stored in value prop
        if ($e['value'] > $t['value']) {
          $container['players'][$e['slot']][$e['type']] = e;
        }
      } else if (!\utils\is_assoc_array($t)) {
      // determine whether we want the value only (interval) or everything (log)
      // either way this creates a new value so e can be mutated later
        $arrEntry;
        if ($e['interval']) {
          $arrEntry = $e['value'];
        } else if ($e['type'] === 'purchase_log' || $e['type'] === 'kills_log' || $e['type'] === 'runes_log') {
          $arrEntry = [
            'time' => $e['time'],
            'key' => $e['key'],
          ];
          if ($e['type'] === 'kills_log' && $e['tracked_death']) {
            $arrEntry = \array_replace([
              'tracked_death' => $e['tracked_death'],
              'tracked_sourcename' => $e['tracked_sourcename'],
            ], $arrEntry);
          }
        } else {
          $arrEntry = \json_decode( \json_encode($e), true );
        }
        $t[] = $arrEntry;
      } else if ($e['type'] === 'ability_targets') {
        // e.g. { Telekinesis: { Antimage: 1, Bristleback: 2 }, Fade Bolt: { Lion: 4, Timber: 5 }, ... }
        $ability = $e['key'][0];
        $target = $e['key'][1];
        if (isset($t[$ability][$target])) {
          $t[$ability][$target] += 1;
        } else if (isset($t[$ability])) {
          $t[$ability][$target] = 1;
        } else {
          $t[$ability] = [];
          $t[$ability][$target] = 1;
        }
      } else if ($e['type'] === 'damage_targets') {
        $ability = $e['key'][0];
        $target = $e['key'][1];
        $damage = $e['value'];
        if (!isset($t[$ability])) {
          $t[$ability] = [];
        }
        if (!isset($t[$ability][$target])) {
          $t[$ability][$target] = 0;
        }
        $t[$ability][$target] += $damage;
      } else if (is_array($t) && \utils\is_assoc_array($t)) {
      // add it to hash of counts
        $e['value'] = $e['value'] ?? 1;
        if ($t[$e['key']]) {
          $t[$e['key']] += $e['value'];
        } else {
          $t[$e['key']] = $e['value'];
        }

        // performanceOthers(e, container, meta);
      } else if (is_string($t)) {
      // string, used for steam id
        $container['players'][$e['slot']][$e['type']] = $e['key'];
      } else {
      // we must use the full reference since this is a primitive type
        $container['players'][$e['slot']][$e['type']] = $e['value'];
      }
      unset($t);
      break;
  }
}

?>