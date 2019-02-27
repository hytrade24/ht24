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

require_once 'tpl/stat_'.$_REQUEST['show'].'.php';
                       
                       
?>