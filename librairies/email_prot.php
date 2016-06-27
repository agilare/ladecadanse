<?php

$myText = $_REQUEST['m'];
$myText = strrev($myText);
$myTextLen = (mb_strlen($myText) * 10);
$safeemail = imagecreate($myTextLen,28);
$backcolor = imagecolorallocate($safeemail,255,255,255);
$textcolor = imagecolorallocate($safeemail,25,25,25);
imagefill($safeemail,0,0,$backcolor);
Imagestring($safeemail,4,5,15,$myText,$textcolor);
header("Content-type: image/jpeg");
imagejpeg($safeemail);
?>
