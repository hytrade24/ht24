<?php
/* ###VERSIONSBLOCKINLCUDE### */




$tpl_content->addvar("IDM", $id = $db->fetch_atom("select ID_MODUL from modul where IDENT='faq'")); 

$tpl_content->addlist("liste", $db->fetch_table($db->lang_select("nav")."
   where FK_MODUL=".$id), "tpl/de/modul_faq.row.htm");


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
?>