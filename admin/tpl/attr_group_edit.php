<?php
/* ###VERSIONSBLOCKINLCUDE### */


#die(ht(dump(array_keys($GLOBALS))));
// ROOT
require_once 'sys/lib.nestedsets.php'; // Nested Sets


$root = $_REQUEST['ROOT'] = $_SESSION['ROOT_ATTR_GROUP'] = 1;
$nest = new nestedsets('attr_group', $root, 1);
$b_neu = 'ins'==$_REQUEST['do'];

$flag = 2;
if (!($id = (int)$_REQUEST['ID_ATTR_GROUP']))
  $id = (int)$db->fetch_atom('select ID_ATTR_GROUP from attr_group where LFT=1 and ROOT='. $root);

if ($b_neu)
{
  $item = $db->fetch_blank('attr_group');
  $item['ID_ATTR_GROUP'] = $id;
}
else
  $item = $db->fetch1($db->lang_select('attr_group'). 'where '. ($id
    ? 'ID_ATTR_GROUP='. $id
    : 'ROOT='. $root. ' and LFT=1'
  ));

#echo ht(dump($item));
#echo ht(dump($nav_current));
#echo ht(dump(array_keys($GLOBALS)));

$do = $_REQUEST['do'];
if ('rm'==$do)
{
  if ($n = $db->fetch_atom("select count(*) from kat where FK_ATTR_GROUP=". $id))
    $err[] = 'Diese Attributgruppe ist noch mindestens einer Kategorie zugeordnet.';
  else
  {
    $db->querynow("delete from attr2group where FK_ATTR_GROUP=". $id);
    $nest->delete($id);
  }
}
function check_post_data()
{
  $err = array ();
  if (!$_POST['V1'])
    $err[] = 'Bitte geben Sie ein Label an.';
  return $err;
}
if (count($_POST))
{
  $err = check_post_data();
  if (!count($err))
  {
    switch($do)
    {
      case 'ins':
        // in Baum einfuegen
        $_POST['ID_ATTR_GROUP'] = $nest->nestInsert($id);
        // update
        $db->update('attr_group', $_POST);
        // Cache aktualisieren
        $b_cache_rewrite = true;
#        $id .= '&do=ins';
        break;
      case 'sv':
      default:
        // update
        $id = $db->update('attr_group', $_POST);
#        $b_cache_rewrite = $lastresult['int_result'];
        break;
    }
    require_once "sys/lib.perm_admin.php";
#    if ($b_cache_rewrite)
#      attr_group_cache_rewrite();
    // Attribute
    $db->query("delete from attr2group where FK_ATTR_GROUP=". $id);
    if (is_array ($ar_tmp = $_POST['attr']) && count($ar_tmp))
    {
      $ar = array (false=>array (), true=>array ());
      if (!$_POST['mand']) $_POST['mand'] = array ();
      foreach ($ar_tmp as $id_attr)
        $ar[in_array ($id_attr, $_POST['mand'])][] = $id_attr;
      if (count($ar[false]))
        $db->query("insert into attr2group select ID_ATTR, ". $id. ", 0, 0
          from attr where ID_ATTR in (". implode(', ', $ar[false]). ")");
      if (count($ar[true]))
        $db->query("insert into attr2group select ID_ATTR, ". $id. ", 1, 0
          from attr where ID_ATTR in (". implode(', ', $ar[true]). ")");
      if (is_array ($_POST['srch']) && count($_POST['srch']))
        $db->query("update attr2group set B_SEARCH=1
        where FK_ATTR_GROUP=". $id. " and FK_ATTR in (". implode(', ', $_POST['srch']). ")");
    }
#$ar_query_log = array ();
    $db->submit(true);
#die(listtab($ar_query_log));

    // Permissions
#    katperm2role_rewrite();
    // forward
    forward('index.php?page=attr_group_edit&ID_ATTR_GROUP='. $id
#      . '&tabno='. ($b_neu ? 2 : max(1, $_REQUEST['tabno']))
    );
  }
  else
    $tpl_content->addvar('err', implode('<br />', $err));
}

// Ahnen und Kinder ------------------------------------------------------------
$lastresult = $db->querynow("select LFT,RGT from attr_group where "
  . ($id ? 'ID_ATTR_GROUP='. $id : 'ROOT='. $root. ' and LFT=1'));
list($lft, $rgt) = mysql_fetch_row($lastresult['rsrc']);
#die(dump("$lft, $rgt"));

$res = $nest->nestSelect('and (
    (t.LFT between '. $lft. ' and '. $rgt. ')
    or ('. $lft. ' between t.LFT and t.RGT)
  )',
  '',
  ' 1 as no_move,',#t.LFT='. $lft. ' as is_current,t.LFT<'. $lft.' as is_ahne,t.LFT>'. $lft.' as is_kid,',
  true);
#echo ht(dump($lastresult));
$ar_baum = $db->fetch_table($res);
/**/ // Daten zwischen zwei Baeumen
#die(listtab($ar_baum));
$ar_ahnen = $ar_kinder = array ();
$maxlevel = 1;
foreach($ar_baum as $i=>$row)
{
  // Zuordnung zu Kategorien
  $nar = $db->fetch_nar($db->lang_select('kat', 'ID_KAT,LABEL'). ' where FK_ATTR_GROUP='. $row['ID_ATTR_GROUP']);
  $ar = array ();
  foreach ($nar as $fk=>$s_label)
    $ar[] = '<a onClick="return checkFlag();" title="zur Kategorie wechseln" href="index.php?page=
kat_edit&ID_KAT='. $fk. '">'. stdHtmlentities($s_label). '</a>';
  $row['assign'] = implode(', ', $ar);

  if ($row['LFT']<$lft || ($row['LFT']==$lft && $b_neu))
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
    $row['is_last'] = false;
    $row['haskids'] = $row['kidcount'];
    $row['kidcount'] = 0;
    $ar_kinder[] = $row;
  }
}
#if (count($ar_ahnen))
#  $ar_ahnen[count($ar_ahnen)-1]['kidcount'] = 0;
if (count($ar_kinder))
  $ar_kinder[count($ar_kinder)-1]['is_last'] = true;

#if (!$b_neu && $self['level']>0) $tpl_content->addvar('path0', str_repeat('
#    <td><img width="19" height="17" src="shim.gif" /></td>', $self['level']));
$tpl_content->addvar('ahnen', tree_show_nested($ar_ahnen, 'tpl/de/attr_group_edit.treerow.htm',NULL,false));
$tpl_content->addvar('self', $self);
$tpl_content->addvar('kinder', tree_show_nested($ar_kinder, 'tpl/de/attr_group_edit.treerow.htm',NULL,false));
/*/ // Daten neben einem Baum
foreach($ar_baum as $i=>$row)
{
  if ($row['LFT']<=$lft)
    $ar_baum[$i]['is_last'] = true;
}
$tpl_content->addvar('tree', tree_show_nested($ar_baum, 'tpl/de/attr_group_edit.row_neu.htm',NULL,false));
/**/

#$str_title = 'Seite '. ($id ? 'bearbeiten':'erstellen');
$tpl_content->addvars($item);


$tpl_content->addvar('flag', (int)$flag);

if (!$b_neu)
{
  // ATTRIBS
  $nar_attr = $db->fetch_table($db->lang_select('attr', '*, FK_ATTR_GROUP, B_MANDATORY, B_SEARCH'). '
    left join attr2group z on FK_ATTR=ID_ATTR and FK_ATTR_GROUP='. $id. '
    group by ID_ATTR order by ID_ATTR', 'ID_ATTR');
  $nar_inherit = $db->fetch_table("select FK_ATTR, FK_ATTR_GROUP, max(k.LFT) K_LFT, z.B_MANDATORY, z.B_SEARCH
    from attr2group z
      left join attr_group k on k.ID_ATTR_GROUP=z.FK_ATTR_GROUP
    where ". (int)$item['LFT']. " between k.LFT and k.RGT and k.ID_ATTR_GROUP<>". $id. "
    group by 1,2 order by 3,1", 'FK_ATTR');
  if (count($nar_inherit))
  {
    $ar_tmp = array ();
    foreach($nar_inherit as $row)
      $ar_tmp[] = $row['FK_ATTR_GROUP'];
    $nar_attr_group = $db->fetch_table($db->lang_select('attr_group'). ' where ID_ATTR_GROUP in ('
      . implode(', ', array_unique($ar_tmp)). ')', 'ID_ATTR_GROUP');
  }
#echo ht(dump($nar_attr_group));
  $i = 0;
  $ar_liste = array ();
  foreach($nar_attr as $id_attr=>$attr)
  {
    $tpl_tmp = new Template('tpl/de/attr_group_edit.attrrow.htm');
    $tpl_tmp->addvars($attr);
    if ($fk = $nar_inherit[$id_attr]['FK_ATTR_GROUP'])
    {
#echo "<b>$fk<br></b>";
      $tpl_tmp->addvars($nar_attr_group[$fk], 'GRP_');
      $tpl_tmp->addvar('GRP_B_MANDATORY', $nar_inherit[$id_attr]['B_MANDATORY']);
      $tpl_tmp->addvar('GRP_B_SEARCH', $nar_inherit[$id_attr]['B_SEARCH']);
    }
#    $tpl_tmp->addvar('curattr_group', $id);
    $tpl_tmp->addvar('even', 1-($i&1));
    $tpl_tmp->addvar('i', $i);
    $i++;
    $ar_liste[$c][] = $tpl_tmp;
  }
  $tpl_content->addvar('attr', $ar_liste);
}

$tpl_content->addvar('ROOT', $root);
$tpl_content->addvar('neu', $b_neu = 'ins'==$_REQUEST['do']);
if ($tmp = $_REQUEST['tabno'])
  $tpl_content->addvar('tabno', $tmp);
?>