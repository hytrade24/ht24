<?php
/* ###VERSIONSBLOCKINLCUDE### */



$id_ad_user = ($_REQUEST["id_ad_user"] ? $_REQUEST["id_ad_user"] : $ar_params[1]);
$num_days = 30;



$tpl_content->addvar("ID_ADVERTISEMENT_USER", $id_ad_user);
/*

$ar_stats = $db->fetch_nar("
	SELECT
		DATEDIFF(CURDATE(), `STAMP`),
		`COUNT`
	FROM
		`advertisement_view`
	WHERE
		`STAMP` >= (CURDATE() - INTERVAL ".$num_days." DAY) AND
		FK_ADVERTISEMENT_USER=".$id_ad_user);
  */      
  
  $ar_stats = $db->fetch_nar("
	select DATEDIFF(CURDATE(), `STAMP`),sum(`COUNT`) as `COUNT` from `advertisement_stat`
		where `STAMP` >= (CURDATE() - INTERVAL ".$num_days." DAY) AND FK_ADVERTISEMENT_USER=".$id_ad_user." group by `STAMP`");
             

$stamp_today = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
$interval_day = (3600 * 24);

$value_max = 0;
$ar_dates = array();
$ar_values = array();
for ($day = $num_days; $day >= 0; $day--) {
	$stamp = $stamp_today - ($day * $interval_day);
	$ar_dates[] = date("d.m.Y", $stamp);
	if (!empty($ar_stats[$day])) {
		$ar_values[] = $ar_stats[$day];
		if ($ar_stats[$day] > $value_max)
			$value_max = $ar_stats[$day];
	} else {
		$ar_values[] = 0;		
	}
}

$tpl_content->addvar("DATES", implode(",", $ar_dates));
$tpl_content->addvar("VIEWS", implode(",", $ar_values));
$tpl_content->addvar("VIEWS_MAX", $value_max);

?>