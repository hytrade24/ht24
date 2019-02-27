<?php
/* ###VERSIONSBLOCKINLCUDE### */



### Get Modul ID
$tpl_content->addvar("IDM", $id = $db->fetch_atom("select ID_MODUL from modul where IDENT='formmailer'")); 

### Alle Seiten mit diesem Modul als Liste
$tpl_content->addlist("liste", $ar=$db->fetch_table($db->lang_select("nav")."
   where FK_MODUL=".$id), "tpl/de/modul_formmailer.row.htm");  

//zeigt baum an wenn keine zuweisung für den Formmailer getroffen wurde!
if (!$ar) 
{ 
	require_once 'sys/lib.nestedsets.php'; // Nested Sets
	$root=1;
	$nest = new nestedsets('nav', $root, 1);
	$res = $nest->nestSelect('', '', ((int)!$nest->tableLock). ' as no_move,', true);
	$ar = $db->fetch_table($res);
	$top = $db->fetch_atom("select ID_NAV from nav where ROOT=". $root. " and LFT=1");
	$tpl_content->addvar('ID_NAV_ROOT', $top); 
	$tpl_content->addvar('baum', tree_show_nested($ar, 'tpl/de/nav_edit.row.htm',NULL,false));
}

 // id des Moduls holen
 $id_formmailer = $db->fetch_atom("select ID_MODUL from modul where IDENT = 'formmailer'");

 // E-Mail aus den Settings einlesen
 $email = $db->fetch1($db->lang_select("moduloption") . " where FK_MODUL=".$id_formmailer." 
 AND OPTION_VALUE='EMAIL'");
 
 if($email['V1'] == "") {
   $tpl_content->addvar('emailerror', "Sie haben in den <a href=\"index.php?page=modul_formmailer_settings\">Einstellungen</a> noch keine E-Mail Adresse eingetragen.<br /> Abgesendete Formulare können nicht übermittelt werden!");
 }

?>