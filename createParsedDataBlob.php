<?php 

namespace \CreateParsedDataBlob;

include "__includes/__matchdataDummy.php";

include "__includes/readline.php";

include "processors/processAllPlayers.php";
include "processors/processTeamfights.php";
include "processors/processLogParse.php";
include "processors/processParsedData.php";
include "processors/processMetadata.php";
include "processors/processExpand.php";
include "processors/processDraftTimings.php";
// TODO smokes
// TODO epilogue

function createParsedDataBlob($entries, $epilogue, $doLogParse) {
  $time = [];

  $stream = \fopen("php://stderr", "w") or die("Unable to open stderr stream");
  
  $matchid = $epilogue['gameInfo_']['dota_']['matchId_'];

  $time['metadata'] = [ 'start' => \microtime(true) ];
  $meta = processMetadata($entries);
  $meta['match_id'] = $matchid;
  $time['metadata']['end'] = \microtime(true);

  $time['expand'] = [ 'start' => \microtime(true) ];
  $expanded = processExpand($entries, $meta);
  $time['expand']['end'] = \microtime(true);

  /*

  logConsole.time('populate');
  const parsedData = processParsedData(expanded, getParseSchema(), meta);
  logConsole.timeEnd('populate');

  logConsole.time('teamfights');
  parsedData.teamfights = processTeamfights(expanded, meta);
  logConsole.timeEnd('teamfights');

  logConsole.time('draft');
  parsedData.draft_timings = processDraftTimings(entries, meta);
  logConsole.timeEnd('draft');

  logConsole.time('processAllPlayers');
  const ap = processAllPlayers(entries, meta);
  logConsole.timeEnd('processAllPlayers');

  parsedData.radiant_gold_adv = ap.radiant_gold_adv;
  parsedData.radiant_xp_adv = ap.radiant_xp_adv;

  logConsole.time('doLogParse');
  if (doLogParse) {
    parsedData.logs = processLogParse(entries, meta);
  }
  logConsole.timeEnd('doLogParse');

  return parsedData;
  */

  \fclose($stream);
}

function parseStream($stream, $doLogParse = true) {
  $entries = [];

  $stream = \fopen($stream, "r") or die("Unable to open stream");
  while(!\feof($stream)) {
    $e = \json_decode(\trim(\fgets($stream)), true);
    if ($e['type'] === 'epilogue') {
      $epilogue = \json_decode($e['key'], true);
      break;
    } else 
      $entries[] = $e;
  }
  \fclose($stream);
  
  $parsedData = createParsedDataBlob($entries, $epilogue, $doLogParse);

  return $parsedData;
}

?>