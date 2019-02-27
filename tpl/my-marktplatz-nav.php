<?php
/* ###VERSIONSBLOCKINLCUDE### */

$countActive = $db->fetch_atom("SELECT count(*) FROM `ad_master` WHERE ((STATUS&3)=1 OR ((STATUS&3)=0 AND CONFIRMED=0)) AND (DELETED=0) AND FK_USER=".$uid);
$countTimeoutSoon = $db->fetch_atom("SELECT count(*) FROM `ad_master` WHERE (STATUS&3)=1 AND (DELETED=0) AND FK_USER=".$uid." AND (DATEDIFF(STAMP_END, NOW())<14)");
$countDisabled = $db->fetch_atom("SELECT count(*) FROM `ad_master` WHERE (STATUS&1)=0 AND (DELETED=0) AND (STAMP_END IS NOT NULL OR CONFIRMED=0) AND FK_USER=".$uid);
$countDeclined = $db->fetch_atom("SELECT count(*) FROM `ad_master` WHERE CONFIRMED=2 AND (DELETED=0) AND FK_USER=".$uid);
$countComments = $db->fetch_atom("SELECT count(*) FROM `comment` WHERE `TABLE`='ad_master' AND FK_USER_OWNER=".$uid);

$tpl_content->addvar("COUNT_ACTIVE", $countActive);
$tpl_content->addvar("COUNT_TIMEOUT_SOON", $countTimeoutSoon);
$tpl_content->addvar("COUNT_DECLINED", $countDeclined);
$tpl_content->addvar("COUNT_DISABLED", $countDisabled);
$tpl_content->addvar("COUNT_COMMENTS", $countComments);