<?php 

function from_camel_case($str) {
  $str[0] = strtolower($str[0]);
  $func = create_function('$c', 'return "_" . strtolower($c[1]);');
  return preg_replace_callback('/([A-Z])/', $func, $str);
}

?>