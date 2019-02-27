<?php

/* ###VERSIONSBLOCKINLCUDE### */

/* 
Aufruf:
onclick="popupfkt('stats&show=buchhaltung&h=240&w=550','600','250');"  
*/

$tpl_content->addvar("show", $_REQUEST['show']);
if (array_key_exists('range', $_REQUEST)) {
  $tpl_content->addvar("range", $_REQUEST['range']);
  $tpl_content->addvar("range_".$_REQUEST['range'], 1);
}
if (array_key_exists('bg', $_REQUEST)) {
  $tpl_main->addvar("bg", $_REQUEST['bg']);
  $tpl_content->addvar("bg", $_REQUEST['bg']);
}

if(preg_match("/^[a-zA-Z0-9_]+$/", $_REQUEST['show'])) {
	require_once 'tpl/stat_'.$_REQUEST['show'].'.php';

	if ( $_REQUEST["show"] == "kunden_rechnungen" ) {
		$tpl_content->addvar("kunden_id",$_REQUEST["kunden_id"]);
	}
}
                       
                       
?>