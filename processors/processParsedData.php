<?php 

namespace \CreateParsedDataBlob;

include_once __DIR__ . "populate.php";

function processParsedData(&$entries, &$container, &$meta) {
  foreach ($entries as &$e) {
    populate($e, $container, $meta);
  }

  return $container;
}

?>