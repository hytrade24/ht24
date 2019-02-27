<?php

$pageCur = (!empty($ar_params[1]) ? (int)$ar_params[1] : 1);
$pageItems = 10;
$show = (!empty($ar_params[2]) ? $ar_params[2] : "received");
$ajax = (!empty($ar_params[3]) ? $ar_params[3] : "list");

$vendorStatus = $db->fetch_atom("SELECT STATUS FROM `vendor` WHERE FK_USER=".(int)$uid);

$tpl_content->addvar("VENDOR_STATUS", $vendorStatus);

$tpl_content->addvar("SHOW", $show);
$tpl_content->addvar("show_".$show, 1);
$tpl_content->addvar("PAGE", $pageCur);
$tpl_content->addvar("PERPAGE", $pageItems);

if ($show == "received") {
	$tpl_content->addvar("FK_USER_OWNER", $uid);
} else {
	$tpl_content->addvar("FK_USER", $uid);
}

if ($ajax == 'AJAX_LIST') {
	$tpl_content->addvar("ACTION", "list");
	$tpl_content->addvar("HIDE_SCRIPTS", 1);
	die($tpl_content->tpl_subtpl("tpl/".$s_lang."/my-comments-list.htm,*"));
} else {
	$tpl_content->addvar("ACTION", $ajax);
}

?>