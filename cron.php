<?php
/* ###VERSIONSBLOCKINLCUDE### */


$shim_gif = array(71, 73, 70, 56, 57, 97, 1, 0, 1, 0, 128, 0, 0, 255, 255, 255, 0, 0, 0, 33, 249, 4, 1, 0, 0, 0, 0, 44, 0, 0, 0, 0, 1, 0, 1, 0, 0, 2, 2, 68, 1, 0, 59);
foreach($shim_gif as $byte) echo chr($byte);
include('sys/lib.crontab.php');
$s_cronpath = './admin/';
cron();
//header ('Content-Type: image/gif');
#$fp = fopen('shim.gif', 'rb');while ($c = fgetc($fp)) echo ord($c), ', ';fclose($fp);
?>