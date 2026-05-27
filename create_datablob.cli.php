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
  $options['do-log-parse'] ?? $options['l'] ?? false,
  $options['verbose'] ?? $options['v'] ?? false
);

echo \json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

}

