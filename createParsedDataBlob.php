<?php 

namespace CreateParsedDataBlob;

ini_set('memory_limit', '4000M');

include "__includes/__matchdataDummy.php";

include "__includes/prepareMetadata.php";

include "processors/processAllPlayers.php";
include "processors/processTeamfights.php";
include "processors/processLogParse.php";
include "processors/processParsedData.php";
include "processors/processMetadata.php";
include "processors/processExpand.php";
include "processors/processDraftTimings.php";
include "processors/processPauses.php";
include "processors/processProps.php";
include "processors/processItemUsageEvents.php";
//include "processors/processUploadProps.php";
include "processors/processPerformanceOthers.php";

function createParsedDataBlob($entries, $epilogue, $doLogParse, $verbose = false) {
  $time = [];

  $stream = \fopen("php://stderr", "w") or die("Unable to open stderr stream");
  
  $matchid = $epilogue['gameInfo_']['dota_']['matchId_'];

  //if ($verbose) \file_put_contents("php://stderr", "[ ] metadata: ");
  $time['metadata'] = [ 'start' => \microtime(true) ];
  $meta = processMetadata($entries, $epilogue);
  $meta['match_id'] = $matchid;
  $time['metadata']['end'] = \microtime(true);
  //if ($verbose) \file_put_contents("php://stderr", $time['metadata']['end']-$time['metadata']['start']);

  $time['expand'] = [ 'start' => \microtime(true) ];
  $expanded = processExpand($entries, $meta);
  $time['expand']['end'] = \microtime(true);

  $time['populate'] = [ 'start' => \microtime(true) ];
  $container = getParseSchema();
  $parsedData = processParsedData($expanded, $container, $meta);
  $time['populate']['end'] = \microtime(true);

  $time['teamfights'] = [ 'start' => \microtime(true) ];
  $parsedData['teamfights'] = processTeamfights($expanded, $meta);
  $time['teamfights']['end'] = \microtime(true);

  $time['draft'] = [ 'start' => \microtime(true) ];
  $parsedData['draft_timings'] = processDraftTimings($entries, $meta);
  $time['draft']['end'] = \microtime(true);

  $time['pauses'] = [ 'start' => \microtime(true) ];
  $parsedData['pauses'] = processPauses($entries);
  $time['pauses']['end'] = \microtime(true);

  $time['processAllPlayers'] = [ 'start' => \microtime(true) ];
  $ap = processAllPlayers($entries, $meta);
  $time['processAllPlayers']['end'] = \microtime(true);

  $parsedData['radiant_gold_adv'] = $ap['radiant_gold_adv'];
  $parsedData['radiant_xp_adv'] = $ap['radiant_xp_adv'];

  $time['processItemUsageEvents'] = [ 'start' => \microtime(true) ];
  $parsedData = processItemUsageEvents($entries, $parsedData, $meta);
  $time['processItemUsageEvents']['end'] = \microtime(true);

  $time['processProps'] = [ 'start' => \microtime(true) ];
  $parsedData = processProps($entries, $parsedData, $epilogue, $meta);
  $time['processProps']['end'] = \microtime(true);

  $time['doLogParse'] = [ 'start' => \microtime(true) ];
  if ($doLogParse)
    $parsedData['logs'] = LogParse\processReduce($entries, $meta);
  $time['doLogParse']['end'] = \microtime(true);

  \fclose($stream);

  if ($verbose) {
    foreach ($time as $stage => $span) {
      \file_put_contents(
        "php://stderr",
        sprintf("[time] %-24s %7.3fs\n", $stage, $span['end'] - $span['start'])
      );
    }
    \file_put_contents(
      "php://stderr",
      sprintf(
        "[time] entries=%d expanded=%d peak_mem=%sMB\n",
        count($entries),
        count($expanded),
        round(\memory_get_peak_usage(true) / 1048576)
      )
    );
  }

  return $parsedData;
}

function parseStream($stream, $doLogParse = true, $verbose = false) {
  $GLOBALS['cpdb_config'] = [];
  if (!isset($GLOBALS['cpdb_config']['stratzkey'])) 
    $GLOBALS['cpdb_config']['stratz'] = json_decode(@file_get_contents(__DIR__ . "/stratzkey.json"), true);
  if (!isset($GLOBALS['cpdb_config']['metadata'])) 
    $GLOBALS['cpdb_config']['metadata'] = prepareMetadata();
  if (!isset($GLOBALS['cpdb_config']['steamapikey'])) 
    $GLOBALS['cpdb_config']['steamapikey'] = trim((string)@file_get_contents(__DIR__ . "/steamapikey"));

  $entries = [];
  $loadStart = \microtime(true);

  $stream = \fopen($stream, "r") or die("Unable to open stream");
  while(!\feof($stream)) {
    $e = \json_decode(\trim(\fgets($stream)), true);
    if (!$e) continue;
    if ($e['type'] === 'epilogue') {
      $epilogue = \json_decode($e['key'], true, 512, JSON_BIGINT_AS_STRING);
      break;
    } else 
      $entries[] = $e;
  }
  \fclose($stream);
  if ($verbose) {
    \file_put_contents("php://stderr", sprintf("[time] %-24s %7.3fs\n", "load", \microtime(true) - $loadStart));
  }

  $parsedData = createParsedDataBlob($entries, $epilogue ?? [], $doLogParse, $verbose);

  return $parsedData;
}


