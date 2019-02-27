<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(!empty($_REQUEST['popup']))
 { 
   $tpl_content->addvar("popup", 1);
   if(!empty($_REQUEST['FK_USER']))
     $tpl_content->addvar("NAME_", $db->fetch_atom("select NAME from user where ID_USER=".$_REQUEST['FK_USER']));
 }
 
 $tpl_content->addvars($_REQUEST);
 
 if(!empty($_REQUEST['ID_GALERIE']))
   $tpl_content->addvars($db->fetch1("select g.*,u.NAME as NAME_ from galerie g
      left join user u on g.FK_USER=u.ID_USER 
	 where ID_GALERIE=".$_REQUEST['ID_GALERIE']));
 
 if(count($_POST))
 {
   $err = array();
   if(empty($_POST['LABEL']))
     $err[] = "Kein Name angegeben";
   if((int)$_POST['IMG_PAGE'] <1)
     $err[] = "Ungültige Eingabe in Bilder pro Seite";
   if((int)$_POST['IMG_ROW'] < 1)
     $err[] = "Ungültige Eingabe in Bilder pro Zeile";
   if($_POST['NAME_']) {
     $idu = $db->fetch_atom("select ID_USER from user where NAME='".mysql_escape_string($_POST['NAME_'])."'");
	 if($idu <= 0)
	   $err[] = "Der angegebene Benutzer existiert nicht!";
	 else
	   $_POST['FK_USER'] = $idu;
   }
   $tpl_content->addvars($_POST);
   if(count($err))
     $tpl_content->addvar("err", implode("<br />", $err));
   else
   {
     $id = $db->update("galerie", $_POST);
	 #die(ht(dump($_POST,$lastresult)));
	 if(empty($_REQUEST['popup']))
	    forward("index.php?page=modul_galerie_details&id=".($_POST['ID_GALERIE'] ? $_POST['ID_GALERIE'] : $id));
   }
 }

?>