<?php
/* ###VERSIONSBLOCKINLCUDE### */



 ### MODUL Seite

 ### Einlesen aller relevanten Daten zu der aktuellen Seite
 $ar = $db->fetch1("select m.*
     from nav n
     left join modul m on n.FK_MODUL=m.ID_MODUL
	 where n.ID_NAV=".$id_nav);
 
 if(empty($ar['IDENT']) || $s_page == 'login' || ($s_page == '403' && !$uid))
   $ar['IDENT'] = 'login';
 ### Das eigentliche Modul holen

 $tpl_modul = new Template("module/tpl/".$s_lang."/".$ar['IDENT'].".htm");
 include "module/".$ar['IDENT']."/index.php";
 
 $tpl_content->addvar("MODUL", $tpl_modul); 
 
?>
