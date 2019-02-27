<?php
/* ###VERSIONSBLOCKINLCUDE### */


/*

### NewUser (CACHE)
$c_file = $ab_path.'cache/admin_welcome_stats_new_user.tmp';
$time = @filemtime($c_file);
if(!$time || $time < strtotime('-8 hours'))
{
       include_once( $ab_path.'lib/open-flash-chart.php' );
	    $tpl_tmp = new Template("tpl/de/welcome-flash-ads.htm");
		$tpl_tmp->addvar("FLASHDATA", stats_usercounter());
		$tmp = $tpl_tmp->process();
		file_put_contents($c_file, $tmp);
		chmod($c_file, 0777);
} // warenwert
else
{
	$tmp = file_get_contents($c_file);
}

$tpl_content->addvar('NewUser', $tmp);



### BdayUser (CACHE)
$c_file = $ab_path.'cache/admin_welcome_stats_age_user.tmp';
$time = @filemtime($c_file);
if(!$time || $time < strtotime('-8 hours'))
{
        include_once( $ab_path.'lib/open-flash-chart.php' );
	    $tpl_tmp = new Template("tpl/de/welcome-flash-ads.htm");
		$tpl_tmp->addvar("FLASHDATA", bdays_getdata2());
		$tmp = $tpl_tmp->process();
		file_put_contents($c_file, $tmp);
		chmod($c_file, 0777);

} // warenwert
else
{
	$tmp = file_get_contents($c_file);
}

$tpl_content->addvar('AgeUser', $tmp);

*/









?>