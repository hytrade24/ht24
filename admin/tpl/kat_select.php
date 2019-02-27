<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.nestedsets.php'; // Nested Sets
$tpl_content->addvar('ROOT', $root = root('kat'));
$tpl_content->addvar("npage", ($_REQUEST['npage'] ? $_REQUEST['napage'] : NULL));

$nest = new nestedsets('kat', $root, 1);
$tpl_content->addvars($rootrow = $db->fetch1("select t.*, s.V1, s.V2, s.T1 from `kat` t left join string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2))) where LFT=1 and ROOT='".$root."' "), 'ROOT_');


//echo $db->lang_select('kat'). ' where LFT=1 and ROOT='. $root;

// left/right der aktuellen Zeile ermitteln
if (!($id = (int)$_REQUEST['ID_KAT']))
{
  $id = $rootrow['ID_KAT'];
  $lft = 1;
  $rgt = $rootrow['RGT'];
}
else
{
  $lastresult = $db->querynow('select LFT,RGT from kat where ID_KAT='. $id);
  list($lft, $rgt) = mysql_fetch_row($lastresult['rsrc']);
}
$tpl_content->addvar('ID_KAT', $id);

// Ahnenreihe lesen
if ($lft==1)
{
  $ar_path = array ();
  $n_level = 0;
}
else
{
  $ar_path = $db->fetch_table($nest->nestQuery(
    'and ('. $lft. ' between t.LFT and t.RGT)','',
    '1 as is_last,1 as kidcount,1 as is_first,t.LFT='. $lft. ' as is_current,', false), 'ID_KAT'
  );
  $n_level = $ar_path[$id]['level'];
  $ar_path = array_values($ar_path);
}

// Kinder lesen
$s_sql = $nest->nestQuery(' and (t.LFT between '. $lft. ' and '. $rgt. ')', '', 't.RGT-t.LFT>1 as haskids,', true);
$s_sql = str_replace(' order by ', ' having level='. (1+$n_level). ' order by ', $s_sql);
$res = $db->querynow($s_sql);
#echo ht(dump($res));

if (!(int)$res['int_result']) // keine Kinder da -> kidcount der aktuellen Zeile auf 0
{
  if ($n = count($ar_path))
    $ar_path[$n-1]['kidcount'] = 0;
}
else while ($row = mysql_fetch_assoc($res['rsrc'])) // sonst Kinder an Baum anhaengen
{
  $row['kidcount'] = 0;
  $ar_path[] = $row;
}

//echo ht(dump(tree_show_nested($ar_path, 'tpl/de/kat_select.row.htm',NULL,false)));
//echo tree_show_nested($ar_path, 'tpl/de/kat_select.row.htm',NULL,false);

// Baum ausgeben

$tpl_content->addvar('baum', tree_show_nested($ar_path, 'tpl/de/kat_select.row.htm',NULL,false));
?>