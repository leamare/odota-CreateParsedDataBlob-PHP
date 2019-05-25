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
  $options['do-log-parse'] ?? $options['l'],
  $options['verbose'] ?? $options['v']
);

echo \json_encode($data);

}

?>