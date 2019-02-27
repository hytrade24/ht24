<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.nestedsets.php'; // Nested Sets

// ROOT
$tpl_content->addvar('ROOT', $root = root('kat'));
$id_agroot = (int)$db->fetch_atom("select ID_ATTR_GROUP from attr_group where ROOT=". $root. " and LFT=1");

// Class
$nest = new nestedsets('kat', $root, !$frame);
if (!$nest->validate())
  die('<h2>nested set invalid</h2>'. $nest->errMsg);#ht(dump($lastresult)));

$tpl_content->addvar('lock_ok', !$frame && !$nest->tableLock);
if (!$frame && $nest->tableLock)
  $tpl_content->addvar('lock_user', $db->fetch_atom("select NAME from `user` where ID_USER=". $nest->tableLockData['FK_USER']));

$tpl_content->addvar('let_move', !$frame && !$nest->tableLock);

// Do Something
$id = (int)$_REQUEST['id'];

switch($_REQUEST['do'])
{
  case 'up':
    $nest->nestMoveUp($id);
    break;
  case 'dn':
    $nest->nestMoveDown($id);
    break;
  case 'lt':
    $nest->nestMoveLeft($id);
    break;
  case 'rt':
    $nest->nestMoveRight($id);
    break;
  case 'v0':
    $db->querynow("update kat set B_VIS=0 where ID_KAT=".$id);
    break;
  case 'v1':
    $db->querynow("update kat set B_VIS=1 where ID_KAT=".$id);
    break;
  case 'mod':
    require_once 'sys/lib.perm_admin.php';
    katperm2role_set(-1, $_POST['mod']);
#die();
    break;
  case 'rm':
    $db->query("delete from string_kat where FK=".$id." and S_TABLE='kat'");
    $db->query("delete from katperm2role where FK_KAT=".$id);
    $db->query("delete from attr2kat where FK_KAT=".$id);
#    $db->query("delete from katperm2user where FK_KAT=".$id);
    $db->submit();
    require_once 'sys/lib.perm_admin.php';
    $nest->nestDel($id);
    katperm2role_rewrite();
    if ($root == 4) {
      $db->querynow("DELETE FROM `vendor_category` WHERE FK_KAT NOT IN (SELECT ID_KAT FROM `kat` WHERE ROOT=4)");
    }
    break;
  case 'rmx':
    $tmp=$db->fetch1("select LFT,RGT from kat where ID_KAT=".$id);
    $s_cat = implode(', ', $db->fetch_nar("select ID_KAT,ID_KAT from kat
      where ROOT=". $root."
        and LFT >= ".$tmp['LFT']." and RGT <= ".$tmp['RGT']));
    $db->query("delete from string_kat where S_TABLE='kat' and FK in($s_cat)");
    $db->query("delete from katperm2role where FK_KAT in($s_cat)");
    $db->query("delete from attr2kat where FK_KAT in($s_cat)");
#    $db->query("delete from katperm2user where FK_KAT in($s_cat)");
    $db->submit();
    $nest->nestDel($id, 1);
    #require_once 'sys/lib.perm_admin.php';
    #katperm2role_rewrite();
    if ($root == 4) {
      $db->querynow("DELETE FROM `vendor_category` WHERE FK_KAT NOT IN (SELECT ID_KAT FROM `kat` WHERE ROOT=4)");
    }
    break;
  case 'new':
    require_once "sys/lib.perm_admin.php";
/**/
    #katperm2role_rewrite();
/*/
    $roles = $db->fetch_table("select ID_ROLE from role");
    $tmp = array ($id => 1);
    for ($i=0; $i<count($roles); $i++)
    {
echo "ROLE UPDATE: ".$$roles[$i]['ID_ROLE']." fuer: ".implode(' -- ', $tmp)."<br />";
      katperm2role_set($roles[$i]['ID_ROLE'], $tmp);
    }
/**/
    break;
  default:
    break;
}
if ($_REQUEST['do'] && empty($nest->errMsg))
{
  // Kategorie-Cache neu schreiben
  require_once "sys/lib.perm_admin.php";
  kat_cache_rewrite();
/*
  $langval_bak = $langval;
  $langval = $langval_bak;
*/
  forward('index.php?frame='. $s_frame. '&page='. $s_page_alias."&ROOT=".$root.
    ($id ? '&id='. $id : ''));
}
// Errors
if(isset($_REQUEST['do']) && !empty($nest->errMsg))
  $tpl_content->addvar('msg', "SQL Table is locked. Please wait some minutes");

// READ TREE -------------------------------------------------------------------
$res = $nest->nestSelect('', '', ((int)!$nest->tableLock). ' as let_move,
  if('. $id_agroot. '=t.FK_ATTR_GROUP,0,t.FK_ATTR_GROUP) as FK_ATTR_GROUP,', true);
$ar = $db->fetch_table($res);
#foreach($ar as $row) echo $row['actions'];echo'<br>';
$nar_perm_admin_katperm = $db->perm_check('admin_katperm');
function katperm_show(&$row)
{
  global $db, $nar_roles, $nar_deny, $nar_extra, $nar_perm_admin_katperm;
#echo $row['actions'];
  static $nar_cache = array (), $nar_level2attrg = array (), $blank=NULL;
  if (!$row['ID_KAT'])
  {
    if (!$blank)
      $blank = array ('rolemod'=>'<td align="center" class="borderleft">&radic;</td>'. str_repeat('<td align="center" class="narrow">&radic;</td>', count($nar_roles)-1));
    $tmp = $blank;
  }
  elseif ($tmp = $nar_cache[$row['ID_KAT']])
    ;
  else
  {
    if ($tmp2 = $nar_extra[$row['ID_KAT']])
    {
      $row['OVR_P'] = $tmp2[1];
      $row['OVR_M'] = $tmp2[0];
    }
    $ar_tmp = array (); $i=0;
#    $nar_perm_admin_katperm = $db->perm_check('admin_katperm');
    foreach($nar_roles as $id_roletmp=>$label)
    {
      $s_title = ' title="'. stdHtmlentities($label). '"';
      $s_class = ($i++ ? 'narrow' : 'borderleft');
/*
      if ($nar_perm_admin_katperm & PERM_EDIT)
      {
        $ar_tmp[] = '
    <input type="hidden" name="mod['
          . $id_roletmp. ']['. $row['ID_KAT']. ']" value="0" />';
        $ar_tmp[] = '
    <td'. $s_title. ' style="text-align:center;"><input class="nob" type="checkbox" name="mod['
          . $id_roletmp. ']['. $row['ID_KAT']. ']" style="width:16px;height:16px"
        onClick="checkkatperm('. $row['ID_KAT']. ', '. $id_roletmp. ', this, '
          . ($nar_children[$row['ID_KAT']] ? 'recurse);" id="kids'. $row['ID_KAT'] : '0);'). '" '
          . ($nar_deny[$id_roletmp][$row['ID_KAT']] ? '':'checked ')
          . 'value="1" lft="'. $row['LFT']. '" rgt="'. $row['RGT']. '"'. $s_title. '></td>'/** /
        ;
      }
      else*/if ($nar_perm_admin_katperm & PERM_READ)
        $ar_tmp[] = '
    <td'. $s_title. ' class="'. $s_class. '" style="text-align:center;">'. ($nar_deny[$id_roletmp][$row['ID_KAT']] ? '-' : '&radic;'). '</td>';
      else
        $ar_tmp[] = '
    <td'. $s_title. ' class="'. $s_class. '" style="text-align:center;">?</td>';
    }
    $tmp['rolemod'] = $ar_tmp;
  }

  if (!$row['actions'])
  {
    if ($row['FK_ATTR_GROUP'])
      $tmp['thisag'] = 1;
    else
      $row['FK_ATTR_GROUP'] = $nar_level2attrg[$row['level']-1];
    $nar_level2attrg[$row['level']] = $row['FK_ATTR_GROUP'];
  }
#echo ht(dump($tmp));
  $row = array_merge($row, $tmp);
/*
{rolemod}
  <td>{if OVR_P}{OVR_P}+{else}&nbsp;{endif}</td>
  <td>{if OVR_M}{OVR_M}-{else}&nbsp;{endif}</td>

  $nar_roles
*/
}
  $nar_roles = $db->fetch_nar("select ID_ROLE, LABEL from role order by ID_ROLE");
  $nar_deny = $ar_modhead = array (); $i=0;
  foreach($nar_roles as $id_role=>$s_label)
  {
    $nar_deny[$id_role] = array ();
    $ar_modhead[] = '
  <th class="'. ($i++ ? 'narrow':'borderleft'). '" title="'. stdHtmlentities($s_label). '"><img src="tpl/role'. $id_role. '.png" /></th>';
  }
  $lastresult = $db->querynow("select FK_ROLE, FK_KAT from katperm2role");
  while (list($id_tmp, $s_tmp) = mysql_fetch_row($lastresult['rsrc']))
    $nar_deny[$id_tmp][$s_tmp] = 1;
  

#  $db->querynow("select FK_KAT, B_OVR, count(FK_USER) from katperm2user group by 1,2");
#  $nar_extra = array ();
#  while (list($s_tmp, $b_val, $n_count) = mysql_fetch_row($lastresult['rsrc']))
#    $nar_extra[$s_tmp][$b_val] = $n_count;

$tpl_content->addvar('actions', !$frame);
$tpl_content->addvar('baum', tree_show_nested($ar, 'tpl/de/kategorien.row.htm',
  ($nar_perm_admin_katperm & PERM_READ ? 'katperm_show' : false),
  !$frame, $id, 'ID_KAT'));
$tpl_content->addvar('modhead', $ar_modhead);
$tpl_content->addvar('modcount', count($ar_modhead));
#echo ht(dump($ar));
/*/
$tpl_content->addlist('baum', $ar, 'tpl/de/nav_edit.row.htm');
/**/


?>