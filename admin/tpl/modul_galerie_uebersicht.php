<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(isset($_GET['del']))
 {
   $lastresult = $db->querynow("update img set FK_GALERIE=NULL where FK_GALERIE=".$_GET['del']);
   if(empty($lastresult['str_error']))
     $db->querynow("delete from galerie where ID_GALERIE=".$_GET['del']); 
 }

 // Alle bestehenden galerien aus der DB holen
 $ar = $db->fetch_table("select g.*,count(i.ID_IMG) as images, u.NAME from galerie g
   left join img i on i.FK_GALERIE=g.ID_GALERIE
   left join user u on g.FK_USER=u.ID_USER
   GROUP BY ID_GALERIE
   order by LABEL");
 $tpl_content->addlist("liste", $ar, "tpl/de/modul_galerie_uebersicht.row.htm");

?>