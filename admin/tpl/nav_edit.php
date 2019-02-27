<?php
/* ###VERSIONSBLOCKINLCUDE### */

#$SILENCE=false;
/*
$hack = explode (" ", microtime());
$start = $hack[0]+$hack[1];
*/
// ROOT
require_once 'sys/lib.nestedsets.php'; // Nested Sets
require_once 'sys/lib.cache.php';

// von Benny
require_once './sys/lib.nestedset_backup.php';
$nestedset_backup = new nestedset_backup($db);
// Benny ende

$tpl_content->addvar('ROOT', $root = root('nav'));

$nest = new nestedsets('nav', $root, true);

if (!$nest->validate())
  die ('<h2>nested set invalid</h2>'. ht(dump($lastresult)));

$wrn = array ();
if ($n = $nest->tableLock)
{
#echo "$n : ", dump($nest->tableLockData);
  $wrn[] = (($nest->tableLockData && $tmp = $db->fetch_atom("select NAME from `user` where ID_USER=". $nest->tableLockData['FK_USER']))
      ? 'Der User "'. $tmp. '" bearbeitet zur Zeit diesen Baum.'
      : 'Das Locking ist fehlgeschlagen.'
    ). "<br />\nDaher sind Ihnen Verschieben und L&ouml;schen momentan nicht gestattet.";
}
else
  if ($s_expire = $nar_systemsettings['SITE']['lock_expire'])
    $tpl_content->addvar('timeout', (int)$db->fetch_atom("select unix_timestamp(date_add('1980-01-01', interval "
      .$s_expire . "))-unix_timestamp('1980-01-01')"));

$tpl_content->addvar('let_move', !$nest->tableLock);
$tpl_content->addvar("USE_SSL", $nar_systemsettings["SITE"]["USE_SSL"]);
$tpl_content->addvar("USE_SSL_GLOBAL", $nar_systemsettings["SITE"]["USE_SSL_GLOBAL"]);

// Do Something
$id = (int)$_REQUEST['id'];

switch($_REQUEST['do'])
{
  case 'up':
  	// 1 Zeiler von Benny
  	$nestedset_backup->make_backup();
    $nest->nestMoveUp($id);
    break;
  case 'dn':
  	// 1 Zeiler von Benny
  	$nestedset_backup->make_backup();
    $nest->nestMoveDown($id);
    break;
  case 'lt':
  	// 1 Zeiler von Benny
  	$nestedset_backup->make_backup();
    $nest->nestMoveLeft($id);
    break;
  case 'rt':
  	// 1 Zeiler von Benny
  	$nestedset_backup->make_backup();
    $nest->nestMoveRight($id);
    break;
  case 'v0':
  	// 1 Zeiler von Benny
  	$nestedset_backup->make_backup();
    $db->querynow("update nav set B_VIS=0 where ID_NAV=".$id);
    break;
  case 'v1':
  	// 1 Zeiler von Benny
  	$nestedset_backup->make_backup();
    $db->querynow("update nav set B_VIS=1 where ID_NAV=".$id);
    break;
  case 'mod':
    require_once 'sys/lib.perm_admin.php';
    pageperm2role_set(-1, $_POST['mod']);
#die();
    break;
  case 'rm':
    $ar_del = $db->fetch1("select * from nav where ID_NAV=".$id);
	//print_r($ar_del); die();
	require_once ("sys/lib.search.php");
	$search = new do_search('de',false);
	$search->delete_article_from_searchindex($_REQUEST['ID_NAV'],'nav');
	if(!empty($ar_del))
	{
	  @unlink("../tpl/".$ar_del['IDENT'].".php");
	  $ar_lang = $db->fetch_table("select * from lang where B_PUBLIC IS NOT NULL");

        require_once $ab_path. 'sys/lib.template.design.php';
      $designManagement = TemplateDesignManagement::getInstance($db);

        foreach($designManagement->fetchAllTemplates() as $key => $design) {
            @unlink("../design/" . $design['ident'] . "/default/tpl/" . $ar_del['IDENT'] . ".htm");

            for ($i = 0; $i < count($ar_lang); $i++) {
                @unlink("../design/" . $design['ident'] . "/" . $ar_lang[$i]['ABBR'] . "/tpl/" . $ar_del['IDENT'] . ".htm");
            }
        }

	  if($ar_del['FK_MODUL'] > 0)
	  {
#echo ht(dump($lastresult));
		$db->querynow("delete from modul2nav where FK_NAV=".$id);
#echo ht(dump($lastresult));
#die();
	  }
	}
	// 1 Zeiler von Benny
  	$nestedset_backup->make_backup();
	$db->querynow("delete from string where S_TABLE='nav' and FK=".$id);
    require_once 'sys/lib.perm_admin.php';
    $nest->nestDel($id);
    pageperm2role_rewrite();
    break;
  case 'rmx':
    $tmp=$db->fetch1("select LFT,RGT from nav where ID_NAV=".$id);
    $s_id = implode(', ', $db->fetch_nar("select ID_NAV,ID_NAV from nav
      where ROOT=".$root."
        and LFT >= ".$tmp['LFT']." and RGT <= ".$tmp['RGT']));
    $ar_lang = $db->fetch_table("select * from lang where B_PUBLIC IS NOT NULL");
	$ar_files = $db->fetch_table("select IDENT,ID_NAV from nav where ID_NAV in (".$s_id.")");
	require_once ("sys/lib.search.php");
	$search = new do_search('de',false);
	for($i=0; $i<count($ar_files); $i++)
	{
	  $search->delete_article_from_searchindex($ar_files[$i]['ID_NAV'],'nav');
	  @unlink("../tpl/".$ar_files[$i]['IDENT'].".php");
	  for($k=0; $k<count($ar_lang); $k++)
	    @unlink("../tpl/".$ar_lang[$k]['ABBR']."/".$ar_files[$i]['IDENT'].".htm");
	}
	// 1 Zeiler von Benny
  	$nestedset_backup->make_backup();
	$db->querynow("delete from string where S_TABLE='nav' and FK in ($s_id)");
	$db->querynow("delete from string_info where S_TABLE='infoseite' and FK in ($s_id)");
	$db->querynow("delete from modul2nav where  FK_NAV in ($s_id)");
#die();
  	// 1 Zeiler von Benny
  	$nestedset_backup->make_backup();
	$nest->nestDel($id, 1);
    require_once 'sys/lib.perm_admin.php';
    pageperm2role_rewrite();
    break;
  case 'new':
    include "sys/lib.perm_admin.php";
/**/
    pageperm2role_rewrite();
/*/
    $ident = $db->fetch_atom("select IDENT from nav where ID_NAV=". $id);
    $roles = $db->fetch_table("select ID_ROLE from role");
    $tmp = array ($ident => 1);
    for($i=0; $i<count($roles); $i++)
    {
echo "ROLE UPDATE: ".$$roles[$i]['ID_ROLE']." fuer: ".implode(' -- ', $tmp)."<br />";
      pageperm2role_set($roles[$i]['ID_ROLE'], $tmp);
    }
/**/
    break;
  default:
    break;
}


$NAVDATE_tmp=$db->getfrom_tmp ('NAVDATE');
if (($_REQUEST['do'] && empty($nest->errMsg)) or $NAVDATE_tmp < 10)
{
	cache_nav_all($root);
	$NAVDATE_tmp = time();
	$db->putinto_tmp('NAVDATE',$NAVDATE_tmp);
}

// Errors
if(isset($_REQUEST['do']) && !empty($nest->errMsg))
  $tpl_content->addvar('msg', "SQL Table is locked. Please wait some minutes");

if ($_SESSION['navedit'.$root.$s_lang]=='' or !empty($wrn) or $NAVDATE_tmp > $_SESSION['NAVDATE_tmp2'.$root])
{

// READ TREE -------------------------------------------------------------------
#echo $db->lang_select("infoseite");

#select t.*, s.V1, s.V2, s.T1 from `infoseite` t left join string_c s on s.S_TABLE='infoseite' and s.FK=t.ID_INFOSEITE and s.BF_LANG=if(t.BF_LANG_C & 128, 128, 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))

$res = $nest->nestSelect('', 'left join modul m on t.FK_MODUL=ID_MODUL
   left join infoseite `is` on t.FK_INFOSEITE=is.ID_INFOSEITE
   left join string_info iss on iss.S_TABLE=\'infoseite\' and iss.FK=is.ID_INFOSEITE and iss.BF_LANG=if(is.BF_LANG_INFO & '.$langval.', '.$langval.', 1 << floor(log(is.BF_LANG_INFO+0.5)/log(2)))
  ', ((int)!$nest->tableLock). ' as let_move,m.LABEL as MODUL,m.S_TABLE as MODTABLE,m.IDENT as modulgfx, iss.V1 as INFOBEREICH,', true);
/*
  "left join string s on s.S_TABLE='nav' and s.FK=eins.ID_NAV
    and s.BF_LANG=if(eins.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(eins.BF_LANG+0.5)/log(2))) ",
  's.V1, '. ((int)!$nest->tableLock). ' as let_move,', true);
*/

$ar = $db->fetch_table($res);
#echo ht(dump($lastresult));
function pageperm_show(&$row)
{
  global $db, $nar_roles, $nar_deny, $nar_extra, $root;
  static $nar_cache = array (), $blank=NULL, $nar_perm_admin_pageperm = NULL;
  $s_dir = (2 == $root ? 'admin/' : '');
#echo $s_dir, '<br />';
  if (is_null($nar_perm_admin_pageperm))
    $nar_perm_admin_pageperm = $db->perm_check('admin_pageperm');
  if (!$row['IDENT'])
  {
    if (!$blank)
      $blank = array ('rolemod'=>
        '<td align="center" align="center" class="borderleft">&radic;</td>'
        . str_repeat('<td align="center" class="narrow">&radic;</td>', count($nar_roles)-1));
    $tmp = $blank;
  }
  elseif ($tmp = $nar_cache[$s_dir. $row['IDENT']])
    ;
  else
  {
    if ($tmp2 = $nar_extra[$s_dir. $row['IDENT']])
    {
      $row['OVR_P'] = $tmp2[1];
      $row['OVR_M'] = $tmp2[0];
    }
    $ar_tmp = array ();
#    $nar_perm_admin_pageperm = $db->perm_check('admin_pageperm');
    foreach($nar_roles as $id_roletmp=>$label)
    {
      $s_title = ' title="'. stdHtmlentities($label). '" class="'. (1==$id_roletmp ? 'borderleft' : 'narrow') . '"';
      if ($nar_perm_admin_pageperm & PERM_EDIT)
      {
        $ar_tmp[] = '
    <input type="hidden" name="mod['
          . $id_roletmp. ']['. $s_dir. $row['IDENT']. ']" value="0" />';
        $ar_tmp[] = '
    <td'. $s_title. ' align="center"><input class="nob" type="checkbox" name="mod['
          . $id_roletmp. ']['. $s_dir. $row['IDENT']. ']" style="width:16px;height:16px"
        onClick="return checkpageperm(\''. $s_dir. $row['IDENT']. '\', '. $id_roletmp. ', this, '
          . ($nar_children[$row['ID_NAV']] ? 'recurse);" id="kids'. $row['ID_NAV'] : '0, event);'). '" '
          . ($nar_deny[$id_roletmp][$s_dir. $row['IDENT']] ? '':'checked ')
          . 'value="1" lft="'. $row['LFT']. '" rgt="'. $row['RGT']. '"'. $s_title. '></td>'/**/
        ;
      }
      elseif ($nar_perm_admin_pageperm & PERM_READ)
        $ar_tmp[] = '
    <td'. $s_title. ' style="text-align:center;">'. ($nar_deny[$id_roletmp][$row['IDENT']] ? '-' : '&radic;'). '</td>';
      else
        $ar_tmp[] = '
    <td'. $s_title. ' style="text-align:center;">?</td>';
    }
    $tmp['rolemod'] = $ar_tmp;
  }
  $row = array_merge($row, $tmp);
/*
{rolemod}
  <td>{if OVR_P}{OVR_P}+{else}&nbsp;{endif}</td>
  <td>{if OVR_M}{OVR_M}-{else}&nbsp;{endif}</td>

  $nar_roles
*/
  return true;
}
  $nar_roles = $db->fetch_nar("select ID_ROLE, LABEL from role order by ID_ROLE");
  $nar_deny = $ar_modhead = array ();
  foreach($nar_roles as $id_role=>$s_label)
  {
    $nar_deny[$id_role] = array ();
    $ar_modhead[] = '
  <th valign="bottom" title="'. stdHtmlentities($s_label). '" class="'
      . (1==$id_role ? 'borderleft' : 'narrow')
      . '"><img width="13" src="tpl/role'. $id_role. '.png"></th>';
  }
  $lastresult = $db->querynow("select FK_ROLE, IDENT from pageperm2role");
  while (list($id_tmp, $s_tmp) = mysql_fetch_row($lastresult['rsrc']))
    $nar_deny[$id_tmp][$s_tmp] = 1;

  $lastresult = $db->querynow("select IDENT, B_OVR, count(FK_USER) from pageperm2user group by 1,2");
  $nar_extra = array ();
  while (list($s_tmp, $b_val, $n_count) = mysql_fetch_row($lastresult['rsrc']))
    $nar_extra[$s_tmp][$b_val] = $n_count;

$tpl_content->addvar('baum', tree_show_nested($ar, 'tpl/de/nav_edit.row.htm',
  'pageperm_show', true, $id));
$tpl_content->addvar('modhead', $ar_modhead);
$tpl_content->addvar('modcount', count($ar_modhead));

/*/
$tpl_content->addlist('baum', $ar, 'tpl/de/nav_edit.row.htm');
/**/


#die(ht(dump($ar_unused)));
// Template

$tpl_content->addvar('wrn', implode('<br /><br />
', $wrn));


	if  (empty($wrn) and $NAVDATE_tmp > $_SESSION['NAVDATE_tmp2'.$root]) {
		$_SESSION['navedit'.$root.$s_lang]= $tpl_content->process();
		$tpl_content->tpl_text=$_SESSION['navedit'.$root.$s_lang];
		#echo $NAVDATE_tmp." nav cached ".$_SESSION['NAVDATE_tmp2'.$root];
		$_SESSION['NAVDATE_tmp2'.$root] =time();
	}	//echo '<br>berni<br>'.$_SESSION['berni'.$root];
	else
	 {
		#echo $NAVDATE_tmp." nix gemacht ".$_SESSION['NAVDATE_tmp2'.$root];
		$_SESSION['navedit'.$root.$s_lang]='';
		$_SESSION['NAVDATE_tmp2'.$root]='0';
	}
}

else {
	#echo $NAVDATE_tmp." von sess ".$_SESSION['NAVDATE_tmp2'.$root] ;
	$tpl_content->tpl_text=$_SESSION['navedit'.$root.$s_lang];
}
		//echo $_SESSION['berni'.$root];

//$tpl_content->tpl_text =
/*
$hack = explode(" ", microtime());
$end = $hack[0]+$hack[1];

echo " differenz = ".($end-$start);
*/
//echo listtab($db->fetch_table("select *, STAMP_EXPIRE<now() as is_expired from locks order by IDENT"));
?>