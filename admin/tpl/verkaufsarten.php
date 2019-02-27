<?php
/* ###VERSIONSBLOCKINLCUDE### */



$err=array ();

if(isset($_REQUEST['del']))
  $db->delete("anzart", $_REQUEST['del']); 
  #$err[] = "Das geht nicht, weil nicht überprüft werden kann, ob diese VK-Art irgendwo verwendet wird!"; 

$liste = $db->fetch_table($db->lang_select("anzart")." order by V1");
if(empty($liste))
  $err[] =  "Keine Verkaufarten angelegt";
else
  $tpl_content->addlist("liste", $liste, "tpl/de/verkaufsarten.row.htm");

if(count($err))
  $tpl_content->addvar("err", implode("<br />", $err)); 

?>
