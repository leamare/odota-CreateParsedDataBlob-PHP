<?php 

namespace CreateParsedDataBlob
{

include "createParsedDataBlob.php";

$data = parseStream("php://stdin", (bool)($argv[1] ?? false));

echo \json_encode($data);

}

?>