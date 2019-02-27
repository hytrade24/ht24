<?php
/* ###VERSIONSBLOCKINLCUDE### */


// Parameters
$userId = ((int)$ar_params[2] ? (int)$ar_params[2] : null);

$userData = $db->fetch1("
		select
			u.*
		from user u
		where u.ID_USER=". $userId);

if(($userId != null) && ($userData != null)) {
	$userContentData = $db->fetch1("SELECT * FROM usercontent WHERE FK_USER = '".$userId."'");

	$tpl_content->addvars($userData, 'USER_');
	$tpl_content->addvars($userContentData, 'USERCONTENT_');

} else {
	$tpl_content->addvar("user_not_found", 1);
}

$tpl_content->addvar("active_impressum", 1);