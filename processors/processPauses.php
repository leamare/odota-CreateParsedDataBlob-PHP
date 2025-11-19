<?php

/**
 * This processor grabs the game pause timings from the parsed replay.
 * The output is:
 * match_time: the game time when the pause started (in seconds)
 * paused_time_in_seconds: the duration of the pause (in seconds)
 */

function processPauses($entries) {
    $pauses = [];
    
    foreach ($entries as $e) {
        if (
            isset($e['type'], $e['key'], $e['value'], $e['time']) &&
            $e['type'] === 'game_paused' &&
            $e['key'] === 'pause_duration' &&
            $e['value'] > 0
        ) {
            $pauses[] = [
                'time' => $e['time'],
                'duration' => $e['value'],
            ];
        }
    }
    
    return $pauses;
}