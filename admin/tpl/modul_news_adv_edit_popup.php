<?php
/* ###VERSIONSBLOCKINLCUDE### */



 // Usersuche
 if ($_REQUEST['NAME_']) $where[]=" NAME like '%".$_REQUEST['NAME_']."%'";
 if ($_REQUEST['NNAME_']) $where[]=" ( NACHNAME like '%".$_REQUEST['NNAME_']."%' or VORNAME like '%".$_REQUEST['NNAME_']."%' ) ";
 if (is_array ($where)) $where=' where '.implode(' and ',$where);

 // Gesuchte User einlesen
 $userdata = $db->fetch_table('select ID_USER, EMAIL, VORNAME, NACHNAME, NAME,STAT from user '.$where.' order by NAME');
  
 // Und Liste erstellen
 $tpl_content->addlist('liste', $userdata, 'tpl/de/modul_news_adv_edit.row.htm');

?>