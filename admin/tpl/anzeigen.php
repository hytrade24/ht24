<?php
/* ###VERSIONSBLOCKINLCUDE### */


$root = 1;

$n_perpage = 25;
$id = (int)$_REQUEST['ID_KAT'];
if (!$id)
  $id = (int)$db->fetch_atom("select ID_KAT from kat where ROOT=". $root. " and LFT=1");

if ($id_ = $_REQUEST['ID_ANZEIGE'])
{
  switch($do = $_REQUEST['do'])
  {
    case 'rm':
      $db->query("delete from anzeige where ID_ANZEIGE=". $id_anzeige);
      $db->submit();
      break;
    case 'v0':
      $db->querynow("update anzeige set B_VIS=0 where ID_ANZEIGE=". $id_anzeige);
      break;
    case 'v1':
      $db->querynow("update anzeige set B_VIS=1 where ID_ANZEIGE=". $id_anzeige);
      break;
  }
  forward('index.php?page=anzeigen&ID_KAT='. $id);
}

require_once 'sys/lib.nestedsets.php'; // Nested Sets
$root = 1;
$nest = new nestedsets('kat', $root);

// Ahnen und Kinder ------------------------------------------------------------
$lastresult = $db->querynow("select LFT,RGT from kat where "
  . ($id ? 'ID_KAT='. $id : 'ROOT='. $root. ' and LFT=1'));
list($lft, $rgt) = mysql_fetch_row($lastresult['rsrc']);
$res = $nest->nestSelect('and (
    (t.LFT between '. $lft. ' and '. $rgt. ')
    or ('. $lft. ' between t.LFT and t.RGT)
  )',
  '',
  ' 1 as no_move,',#t.LFT='. $lft. ' as is_current,t.LFT<'. $lft.' as is_ahne,t.LFT>'. $lft.' as is_kid,',
  true);
$ar_baum = $db->fetch_table($res);
array_unshift($ar_baum, $db->fetch1($db->lang_select('kat'). ' where ROOT='. $root. ' and LFT=1'));
$ar_ahnen = $ar_kinder = array ();
$maxlevel = 1;
foreach($ar_baum as $i=>$row)
{
  if ($row['LFT']<$lft)
  {
    $row['is_last'] = true;
    $ar_ahnen[] = $row;
    $maxlevel = $row['level']+1;
  }
  elseif ($row['LFT']==$lft)
  {
    $maxlevel = $row['level']+1;
    $row['is_last'] = true;
    $self = $row;
    $tpl_content->addvar('level', $row['level']);
  }
  elseif ($row['level']==$maxlevel)
  {
    $row['is_kid'] = true;
    $row['is_last'] = false;
    $row['kidcount'] = 0;
    $ar_kinder[] = $row;
  }
}
if (count($ar_kinder))
  $ar_kinder[count($ar_kinder)-1]['is_last'] = true;
#$tpl_content->addvar('ahnen', tree_show_nested($ar_ahnen, 'tpl/de/anzeigen.katrow.htm',NULL,false));
$tpl_content->addlist('ahnen', $ar_ahnen, 'tpl/de/anzeigen.katrow.htm');
$tpl_content->addvars($self);
#$tpl_content->addvar('kinder', tree_show_nested($ar_kinder, 'tpl/de/anzeigen.katrow.htm',NULL,false));
$tpl_content->addlist('kinder', $ar_kinder, 'tpl/de/anzeigen.katrow.htm');

// Inhalte ---------------------------------------------------------------------
/**/
if ($n_count = (int)$db->fetch_atom("select count(*) from anzeige where FK_KAT=". $id))
{
  $n_ofs = (int)$_REQUEST['ofs'];
  $ar_data = $db->fetch_table($db->lang_select('anzeige'). '
    where FK_KAT='. $id. '
    group by ID_ANZEIGE
    order by B_TOP desc, DATUM desc
    limit '. $n_ofs. ', '. $n_perpage);
#echo ht(dump($ar_browse));

  $tpl_content->addlist('inhalt', $ar_data, 'tpl/de/anzeigen.itemrow.htm');
  if ($n_count>$n_perpage)
  {
    $ar_browse = browse($n_count, $n_ofs, $n_perpage);
    list($n_ofs, $n_perpage) = array_shift($ar_browse);
    $tpl_content->addlist('browse', $ar_browse, 'skin/browse.item.htm');
    $tpl_content->addvar('ofs', $n_ofs);
  }
}
?>