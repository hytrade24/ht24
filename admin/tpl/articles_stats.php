<?php
/* ###VERSIONSBLOCKINLCUDE### */


### anzeigen statistik
$c_file = $ab_path.'cache/marktplatz/admin_welcome_flash.tmp';
$time = @filemtime($c_file);
if(!$time || $time < strtotime('-60 minutes'))
{
	$tpl_tmp = new Template("tpl/de/welcome-flash-ads.htm");
	include "tpl/welcome-flash-ads.php";
	$tmp = $tpl_tmp->process();
	#touch($c_file);
	file_put_contents($c_file, $tmp);
	chmod($c_file, 0777);
} else
{
	$tmp = file_get_contents($c_file);
}
$tpl_content->addvar("FLASH_ADS", $tmp);
?>