<?php
/* ###VERSIONSBLOCKINLCUDE### */



if(empty($_GET['jahr']))
	$_GET['jahr'] = date('Y');
else
	$_GET['monat'] = 12;



include "sys/lib.stats.php";

$tpl_content->addvars(news_getdata($_GET['monat'], $_GET['jahr']));
$tpl_content->addvars(newsdozen_getdata($_GET['jahr']));

$tpl_content->addvars(comment_getdata($_GET['monat'], $_GET['jahr']));
$tpl_content->addvars(commentdozen_getdata($_GET['jahr']));

if($_GET['jahr'])
	$tpl_content->addvar("jahr", $_GET['jahr']);
?>