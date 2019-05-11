<?php 

namespace \CreateParsedDataBlob\utils;

function is_assoc_array(array $arr) {
  if (empty($arr)) return false;
  return array_keys($arr) !== range(0, count($arr) - 1);
}

?>