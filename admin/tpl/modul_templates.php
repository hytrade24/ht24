<?php
/* ###VERSIONSBLOCKINLCUDE### */



 ### Dieses File liest alle Templates zu dem aktuellen Modul in eine Liste
 ### In der datei, welche diese Datei inludiert muss die variable $modulname
 ### definiert sein. $modulname muss den "IDENT" des Moduls enthalten
 
 if(!$modulname)
   die("Fehlender Name des Moduls!");
 
 $tpl_content->addvar("modulname", $modulname);
 include "module/".$modulname.".php";
 
 $tpl_content->addlist("templates", $ar_templates, "tpl/de/modul_templates.row.htm");

 
$tpl_content->addvar("IDM", $id = $db->fetch_atom("select ID_MODUL from modul where IDENT='".$modulname."'")); 
$tpl_content->addlist("liste", $ar=$db->fetch_table($db->lang_select("nav")."
   where FK_MODUL=".$id), "tpl/de/modul_".$modulname.".row.htm");  
?>
