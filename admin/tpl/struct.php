<?php
/* ###VERSIONSBLOCKINLCUDE### */


// ROOT
require_once 'sys/lib.nestedsets.php'; // Nested Sets
$tpl_content->addvar('ROOT', $root = 1);
$nest = new nestedsets('nav', $root, true);

/*
if (!$nest->validate())
  die('<h2>nested set invalid</h2>'. $nest->errMsg);#ht(dump($lastresult)));
*/
  
// READ TREE -------------------------------------------------------------------
$res = $nest->nestSelect('', 'left join modul m on t.FK_MODUL=ID_MODUL left join infoseite inf on t.ID_NAV=inf.FK_NAV', ((int)!$nest->tableLock). ' B_VIS,ID_INFOSEITE,m.LABEL as MODUL,m.S_TABLE as MODTABLE,m.IDENT as modulgfx,', true);
$ar = $db->fetch_table($res);
$tpl_content->addvar('baum', tree_show_nested($ar, 'tpl/de/struct.row.htm', '', false, $id));
?>