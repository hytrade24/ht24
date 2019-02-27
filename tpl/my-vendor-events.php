<?php
/* ###VERSIONSBLOCKINLCUDE### */

$npage =  (isset($_POST['PAGE']) ? $_POST['PAGE'] : (isset($ar_params[1]) ? $ar_params[1] : 1));
$doAction =  (isset($ar_params[2]) ? $ar_params[2] : null);
$calendarEventId =  (isset($ar_params[3]) ? (int)$ar_params[3] : null);
$viewType = (isset($ar_params[6]) ? $ar_params[6] : "LIST");

$vendorStatus = $db->fetch_atom("SELECT STATUS FROM `vendor` WHERE FK_USER=".(int)$uid);

$tpl_content->addvar("VENDOR_STATUS", $vendorStatus);

$tpl_content->addvar("PAGE_OFFSET", $npage);
$tpl_content->addvar("VIEW_TYPE", $viewType);
$tpl_content->addvar("STAMP_TODAY", date("Y-m-d H:i:s"));