<?php 

namespace CreateParsedDataBlob
{

include "createParsedDataBlob.php";

$options = getopt("lv", [
  "do-log-parse",
  "verbose",
]);

$data = parseStream(
  "php://stdin", 
  isset($options['do-log-parse']) || isset($options['l']),
  isset($options['verbose']) || isset($options['v'])
);

$encodeStart = microtime(true);
echo \json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if (isset($options['verbose']) || isset($options['v'])) {
  file_put_contents("php://stderr", sprintf("[time] %-24s %7.3fs\n", "json_encode", microtime(true) - $encodeStart));
}

}

