<?php

require_once $ab_path."sys/lib.ad_availability.php";

if ($ar_params[1] == "create") {
	$id_ad = 10;
	for ($id_ad = 10; $id_ad < 1000; $id_ad++) {
		$digit = ($id_ad % 10);
		$ar_worktimes = array(
			array("WEEKDAY" => 1, "BEGIN" => "09:00:00", "END" => "12:00:00"),
			array("WEEKDAY" => 1, "BEGIN" => "13:00:00", "END" => "18:00:00"),
			array("WEEKDAY" => 2, "BEGIN" => "0".$digit.":00:00", "END" => "1".$digit.":00:00"),
			array("WEEKDAY" => 3, "BEGIN" => "0".$digit.":00:00", "END" => "1".$digit.":00:00"),
			array("WEEKDAY" => 4, "BEGIN" => "09:00:00", "END" => "12:00:00"),
			array("WEEKDAY" => 4, "BEGIN" => "13:00:00", "END" => "18:00:00"),
			array("WEEKDAY" => 5, "BEGIN" => "0".$digit.":00:00", "END" => "1".$digit.":00:00")
		);
		$mAvail = AdAvailabilityManagement::getInstance($id_ad, $db);
		$mAvail->createAdAvailability(365, $ar_worktimes);	
	}
}

$ar_ads = AdAvailabilityManagement::fetchAllByAvailability($db, '2013-07-15', '2013-11-24', '11:00:00', '16:00:00');

?>