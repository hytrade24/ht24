<?php
/* ###VERSIONSBLOCKINLCUDE### */


#$tpl_content->table = 'user';

require_once 'sys/lib.nestedsets.php'; // Nested Sets
$root=1;
$nest = new nestedsets('nav', $root, 1);
$res = $nest->nestSelect('', '', ((int)!$nest->tableLock). ' as no_move,', true);
$ar = $db->fetch_table($res);
$top = $db->fetch_atom("select ID_NAV from nav where ROOT=". $root. " and LFT=1");
$tpl_content->addvar('ID_NAV_ROOT', $top); 
$tpl_content->addvar('baum', tree_show_nested($ar, 'tpl/de/nav_edit.row.htm',NULL,false));

$id_modul = $db->fetch_atom("select ID_MODUL from modul where IDENT='login'");	
$id_opt = $db->fetch_atom("select ID_MODULOPTION from moduloption where 
	  FK_MODUL=".$id_modul." and OPTION_VALUE='STARTPAGE'");	

#echo $db->lang_select("moduloption");	  
if (count($_POST))
{	
	$_POST['trg'] = $db->fetch_atom("select IDENT from nav where ID_NAV=".$_POST['trg']);
	$db->update("moduloption", array("ID_MODULOPTION" => $id_opt, "V1" => $_POST['trg']));
	$db->querynow ("update modul set B_VIS=".$_POST['B_VIS']." where IDENT='login'");
    forward('index.php?nav='. $nav, 2);	
}

$startseite = $db->fetch_atom("select n.ID_NAV from `moduloption` t 
 left join string_opt s on s.S_TABLE='moduloption' and s.FK=t.ID_MODULOPTION 
   and s.BF_LANG=if(t.BF_LANG_OPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2)))
 left join nav n on V1=n.IDENT
 where ID_MODULOPTION=".$id_opt); 
$tpl_content->addvar("trg", (int)$startseite);
#echo $startseite;
$B_VIS = $db->fetch_atom("select B_VIS from modul where IDENT='login'");
$tpl_content->addvar('B_VIS',$B_VIS);

?>