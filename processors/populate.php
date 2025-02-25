<?php 

namespace CreateParsedDataBlob;

//include_once __DIR__ . "/performanceOthers.php";
include_once __DIR__ . "/../util/php_utility.php";

function populate(&$e, &$container, &$meta) {
  switch ($e['type']) {
    case 'interval':
      break;
    case 'player_slot':
      $container['players'][$e['key']]['player_slot'] = $e['value'];
      break;
    case 'chat':
    case 'chatwheel':
      $container['chat'][] = $e;
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
      $container['objectives'][] = $e;
      break;
    case 'ability_levels':
      $meta['ability_levels'][ $e['unit'] ] = \array_replace([
        $e['key'] => $e['level'],
      ], $meta['ability_levels'][ $e['unit'] ] ?? []);
      $meta['ability_levels'][ $e['unit'] ][ $e['key'] ] = $e['level'];
      break;
    default:
      if (!isset($container['players'][$e['slot']])) {
        // couldn't associate with a player, probably attributed to a creep/tower/necro unit
        // console.log(e);
        return;
      }

      if (isset($container['players'][$e['slot']][$e['type']])) {
        $t =& $container['players'][$e['slot']][$e['type']];
      } else {
        $t = null;
      }

      if ($t === null) {
        // container.players[0] doesn't have a type for this event
        // console.log("no field in parsed_data.players for %s", e.type);
      } else if (isset($e['posData'])) {
        // fill 2d hash with x,y values
        if (isset($e['key'])) {
          [$x, $y] = \json_decode( $e['key'] );
        } else {
          $x = $e['x'];
          $y = $e['y'];
        }
        if (!isset($t[$x])) {
          $t[$x] = [];
        }
        if (!isset($t[$x][$y])) {
          $t[$x][$y] = 0;
        }
        $t[$x][$y] += 1;
      } else if (isset($e['max'])) {
        // check if value is greater than what was stored in value prop
        if ($e['value'] > $t['value']) {
          $container['players'][$e['slot']][$e['type']] = $e;
        }
      } else if (is_array($t) && !utils\is_assoc_array_by_index($e['type'])) {
        // determine whether we want the value only (interval) or everything (log)
        // either way this creates a new value so e can be mutated later
        // $arrEntry;

        if ($e['type'] === 'neutral_item_history') {
          $existedEl = null;

          $itemName = preg_replace_callback('/([A-Z])/', function($matches) {
            return '_' . strtolower($matches[1]);
          }, $e['key']);
          $itemName = strtolower($itemName);
          $itemName = str_replace('__', '_', $itemName);

          if (strpos($itemName, '_') === 0) {
            $itemName = substr($itemName, 1);
          }
          $existedEl = array_filter($t, function($el) use ($e) {
            return $el['time'] === $e['time'];
          });
          $arrEntry = empty($existedEl) ? [
            'time' => $e['time']
          ] : $existedEl;
          $arrEntry['item_neutral'] = $e['isNeutralActiveDrop'] ? $itemName : ($arrEntry['item_neutral'] ?? null);
          $arrEntry['item_neutral_enhancement'] = $e['isNeutralPassiveDrop'] ? $itemName : ($arrEntry['item_neutral_enhancement'] ?? null);
          if ($existedEl) {
            $existedEl = $arrEntry;
          } else {
            $t[] = $arrEntry;
          }
        } else {
          if (isset($e['interval']) && $e['interval']) {
            $arrEntry = $e['value'];
          } else if ($e['type'] === 'purchase_log' || 
            $e['type'] === 'kills_log' || 
            $e['type'] === 'runes_log' ||
            $e['type'] === 'neutral_tokens_log'
          ) {
            $arrEntry = [
              'time' => $e['time'],
              'key' => $e['key'],
            ];
  
            $maxCharges = $e['key'] === 'tango' ? 3 : 1;
            if ($e['type'] === 'purchase_log' && $e['charges'] > $maxCharges) {
              $arrEntry = [
                'time' => $e['time'],
                'key' => $e['key'],
                'charges' => $e['charges']
              ];
            }
  
            if ($e['type'] === 'kills_log' && isset($e['tracked_death'])) {
              $arrEntry = \array_replace([
                'tracked_death' => $e['tracked_death'],
                'tracked_sourcename' => $e['tracked_sourcename'],
              ], $arrEntry);
            }
          } else {
            $arrEntry = $e;
          }
          $t[] = $arrEntry;
        }
      } else if ($e['type'] === 'ability_targets') {
        // e.g. { Telekinesis: { Antimage: 1, Bristleback: 2 }, Fade Bolt: { Lion: 4, Timber: 5 }, ... }
        [$ability, $target] = $e['key'];
        if (!empty($t[$ability]) && isset($t[$ability][$target])) {
          $t[$ability][$target] += 1;
        } else if (isset($t[$ability])) {
          $t[$ability][$target] = 1;
        } else {
          $t[$ability] = [];
          $t[$ability][$target] = 1;
        }
      } else if ($e['type'] === 'damage_targets') {
        [$ability, $target] = $e['key'];
        $damage = $e['value'];
        if (!isset($t[$ability])) {
          $t[$ability] = [];
        }
        if (!isset($t[$ability][$target])) {
          $t[$ability][$target] = 0;
        }
        $t[$ability][$target] += $damage;
      // } else if ($e['type'] === 'ability_levels') {
      //   // $container['players'][ $e['slot'] ][ $e['type'] ][ $e['key'] ] = $e['level'];
      //   $meta['ability_levels'][ $e['unit'] ] = \array_replace([
      //     $e['key'] => $e['level'],
      //   ], $meta['ability_levels'][ $e['unit'] ]);
      //   $meta['ability_levels'][ $e['unit'] ][ $e['key'] ] = $e['level'];
      } else if (is_array($t) && utils\is_assoc_array_by_index($e['type'])) {
      // add it to hash of counts
        $e['value'] = $e['value'] ?? 1;
        if (isset($t[$e['key']])) {
          $t[$e['key']] += $e['value'];
        } else {
          $t[$e['key']] = $e['value'];
        }

        performanceOthers($e, $container, $meta);
      } else if (is_string($t)) {
      // string, used for steam id
        $container['players'][$e['slot']][$e['type']] = $e['key'];
      } else {
      // we must use the full reference since this is a primitive type
        $container['players'][$e['slot']][$e['type']] = $e['value'];
      }
      // unset($t);
      break;
  }
}

