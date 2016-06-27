<?php

$offset = 60 * 60 * 24 * -1;
$ExpStr = "Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
header($ExpStr);  
header("Cache-Control: max-age=120, must-revalidate");
?>
