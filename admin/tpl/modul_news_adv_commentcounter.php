<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if (count($_POST))
 {
//news
	$db->querynow ("update news set PCOUNT = (select count(*) from kommentar_news where  PUBLISH=1 and FK=ID_NEWS )");
	$db->querynow ("update news set hitcount = (select sum(VIEWS) from newsview where  FK_NEWS=ID_NEWS)");

//tutorials
/*
	$db->querynow ("update tutorial set PCOUNT = (select count(*) from kommentar_tutorial where  PUBLISH=1 and FK=ID_TUTORIAL )");
	$db->querynow ("update tutorial_live set rating = (select round(sum(RATING)/count(*)) from rating_tutorial where FK=ID_TUTORIAL_LIVE )");
	$db->querynow ("update tutorial set rating = (select round(sum(RATING)/count(*)) from rating_tutorial where FK=ID_TUTORIAL )");
	$db->querynow ("update tutorial set VIEWS = (select sum(VIEWS) from tutorialview where FK_TUTORIAL=ID_TUTORIAL )");
	$db->querynow ("update tutorial_live set VIEWS = (select sum(VIEWS) from tutorialview where FK_TUTORIAL=ID_TUTORIAL_LIVE )");
*/

//script
/*
	$db->querynow ("update script set PCOUNT = (select count(*) from kommentar_script where  PUBLISH=1 and FK=ID_SCRIPT )");
	$db->querynow ("update script_work set PCOUNT = (select count(*) from kommentar_script where  PUBLISH=1 and FK=ID_SCRIPT_WORK )");
	$db->querynow ("update script set rating = (select round(sum(RATING)/count(*)) from rating_script where FK=ID_SCRIPT )");
	$db->querynow ("update script set rating = (select round(sum(RATING)/count(*)) from scriptview where FK=ID_SCRIPT )");
	$db->querynow ("update script_work set rating = (select round(sum(RATING)/count(*)) from scriptview where FK=ID_SCRIPT_WORK )");
*/
//user
/*
	$db->querynow ("update user set RATING = (select round(sum(RATING)/count(*)) from rating_user where  FK=ID_USER)");
*/


	$tpl_content->addvar('ok',1);
 }

?>