<?php 

namespace CreateParsedDataBlob {

include "__includes/__matchdataDummy.php";

include "__includes/prepareMetadata.php";

include "processors/processAllPlayers.php";
include "processors/processTeamfights.php";
include "processors/processLogParse.php";
include "processors/processParsedData.php";
include "processors/processMetadata.php";
include "processors/processExpand.php";
include "processors/processDraftTimings.php";
include "processors/processProps.php";
// TODO smokes

function createParsedDataBlob($entries, $epilogue, $doLogParse, $verbose = false) {
  $time = [];

  $stream = \fopen("php://stderr", "w") or die("Unable to open stderr stream");
  
  $matchid = $epilogue['gameInfo_']['dota_']['matchId_'];

  //if ($verbose) \file_put_contents("php://stderr", "[ ] metadata: ");
  $time['metadata'] = [ 'start' => \microtime(true) ];
  $meta = processMetadata($entries);
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

  $time['processAllPlayers'] = [ 'start' => \microtime(true) ];
  $ap = processAllPlayers($entries, $meta);
  $time['processAllPlayers']['end'] = \microtime(true);

  $parsedData['radiant_gold_adv'] = $ap['radiant_gold_adv'];
  $parsedData['radiant_xp_adv'] = $ap['radiant_xp_adv'];

  $time['processProps'] = [ 'start' => \microtime(true) ];
  $parsedData = processProps($entries, $parsedData, $epilogue, $meta);
  $time['processProps']['end'] = \microtime(true);

  $time['doLogParse'] = [ 'start' => \microtime(true) ];
  if ($doLogParse)
    $parsedData['logs'] = LogParse\processReduce($entries, $meta);
  $time['doLogParse']['end'] = \microtime(true);

  \fclose($stream);

  return $parsedData;
}

function parseStream($stream, $doLogParse = true, $verbose = false) {
  if (!isset($GLOBALS['metadata'])) $GLOBALS['metadata'] = prepareMetadata();
  if (!isset($GLOBALS['steamapikey'])) $GLOBALS['steamapikey'] = file_get_contents("steamapikey");

  $entries = [];

  $stream = \fopen($stream, "r") or die("Unable to open stream");
  while(!\feof($stream)) {
    $e = \json_decode(\trim(\fgets($stream)), true);
    if ($e['type'] === 'epilogue') {
      $epilogue = \json_decode($e['key'], true, 512, JSON_BIGINT_AS_STRING);
      break;
    } else 
      $entries[] = $e;
  }
  \fclose($stream);
  
  $parsedData = createParsedDataBlob($entries, $epilogue, $doLogParse, $verbose);

  return $parsedData;
}

}

?>