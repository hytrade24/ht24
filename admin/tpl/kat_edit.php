<?php
/* ###VERSIONSBLOCKINLCUDE### */


$SILENCE = false;
#die(ht(dump(array_keys($GLOBALS))));
// ROOT
require_once 'sys/lib.nestedsets.php'; // Nested Sets
require_once 'sys/lib.cache.php';
include "sys/lib.shop_kategorien.php";

$tpl_content->addvar('ROOT', $root = root('kat'));
$do = $_REQUEST['do'];

if ( $root == 4 ) {
	$kat = new TreeCategories("kat", 4);
}

// Class
$nest = new nestedsets('kat', $root, 'ins'==$do);
if (!$nest->validate())
  die('<h2>nested set invalid</h2>'. $nest->errMsg);#ht(dump($lastresult)));

$tpl_content->addvar('lock_ok', !$frame && !$nest->tableLock);
if (!$frame && $nest->tableLock)
  $tpl_content->addvar('lock_user', $db->fetch_atom("select NAME from `user` where ID_USER=". $nest->tableLockData['FK_USER']));

if ('ins'!=$do || $n = $nest->tableLock)
{
}
else
  if ($s_expire = $nar_systemsettings['SITE']['lock_expire'])
    $tpl_content->addvar('timeout', (int)$db->fetch_atom("select unix_timestamp(date_add('1980-01-01', interval "
      .$s_expire . "))-unix_timestamp('1980-01-01')"));

$tpl_content->addvar('let_move', !$nest->tableLock);

$flag = 2;
if (!($id = (int)$_REQUEST['ID_KAT']))
  $id = (int)$db->fetch_atom('select ID_KAT from kat where LFT=1 and ROOT='. $root);

if ($b_neu)
{
  $item = $db->fetch_blank('kat');
  $item['ID_KAT'] = $id;
}
else
  $item = $db->fetch1($db->lang_select('kat'). 'where '. ($id
    ? 'ID_KAT='. $id
    : 'ROOT='. $root. ' and LFT=1'
  ));

#echo ht(dump($item));
#echo ht(dump($nav_current));
#echo ht(dump(array_keys($GLOBALS)));

function check_post_data()
{
  $err = array ();
  if (!$_POST['V1'])
    $err[] = 'Bitte geben Sie ein Label an.';
  return $err;
}

function check_field(&$row, $i)
{
	$row["B_USED"] = false;
	if ($row["B_ENABLED"] || $row["IS_SPECIAL"]) {
		$row["B_USED"] = true;
		if ($row["B_NEEDED"]) {
			$row["B_REQUIRED"] = true;
			$row["B_USED"] = true;
		}
		if ($row["B_SEARCH"] || $row['B_SEARCHABLE']) {
			$row["B_SEARCHABLE"] = 1;
		}
	}
}

if (count($_POST))
{
#die (ht(dump($_POST['ID_KAT_OPTION'])));
  $err = check_post_data();
  if (!count($err))
  {
    if(is_array ($_POST['ID_KAT_OPTION']))
       $_POST['SER_OPTIONS'] = serialize($_POST['ID_KAT_OPTION']);
	else
	   $_POST['SER_OPTIONS'] = serialize(array ());
	if(is_array ($_POST['VK']))
	   $_POST['SET_ANZART']=implode(",",$_POST['VK']);
	else
	  $_POST['SET_ANZART']=NULL;
	switch($do)
    {
      case 'ins':
        // in Baum einfuegen
	      if ( $root == 4 ) {
        	$_POST["KAT_TABLE"] = 'vendor_master';
	      }
        $_POST['ID_KAT'] = $nest->nestInsert($id);
        // update
        $db->update('kat', $_POST);
        // Cache aktualisieren
        $b_cache_rewrite = true;
#        $id .= '&do=ins';
        break;
      case 'sv':
        // update
        $id = $db->update('kat', $_POST);
        $b_cache_rewrite = true;#$lastresult['int_result'];
        // Rechte
        $db->query("delete from katperm2role where FK_KAT=". $id);
        $db->query("insert into katperm2role (FK_KAT, FK_ROLE)
          select ". $id. ", ID_ROLE from role". (is_array ($_POST['perm2role']) ? '
          where ID_ROLE not in ('. implode(',', $_POST['perm2role']). ')' : '')
        );
#echo ht(dump($db->q_queries));die(ht(dump($_POST['perm2role'])));
        $db->submit();
#echo listtab($ar_query_log);
#echo ht(dump($_POST['perm2role']));
#die();
         if(isset($_POST['VERERBUNG']))
		   $db->querynow("update kat set SER_OPTIONS='".mysql_escape_string($_POST['SER_OPTIONS'])."'
		      where LFT > ".$item['LFT']." and RGT < ".$item['RGT']);
         if(isset($_POST['VERERBUNG_VK']))
		   $db->querynow("update kat set SET_ANZART='".mysql_escape_string($_POST['SET_ANZART'])."'
		      where LFT > ".$item['LFT']." and RGT < ".$item['RGT']);
		break;
    }
	if(!empty($_FILES['PREV']['tmp_name']))
	{
	  $ar_format = $db->fetch1("select * from bildformat where `LABEL`='themenbilder'");
	  $w = $ar_format['MAX_W'];
	  $h = $ar_format['MAX_H'];
	  include_once "sys/lib.image.php";
	  $name = quickReduce($_FILES['PREV'], $w, $h, 'uploads/images/kat/', $_POST['ID_KAT'].".jpg");
	  $ar_size = getimagesize($name);
	  $ar_tmp = array(
	   'ID_KAT' => $_POST['ID_KAT'],
	   'IMG' => $name,
	   'IMG_W' => $ar_size[0],
	   'IMG_H' => $ar_size[1]
	  );
	  $db->update("kat", $ar_tmp);
	  #echo ht(dump($lastresult));
	  #die();
	} // image via post
    require_once "sys/lib.perm_admin.php";
#die(ht(dump($lastresult)));
    if ($b_cache_rewrite)
      kat_cache_rewrite();
    // Attribute
#die(ht(dump($_POST)));
/*
    if (is_array ($ar_tmp = $_POST['add']) && count($ar_tmp))
      $db->querynow("insert into attr2kat select ID_ATTR, $item[ID_KAT]
        from attr where ID_ATTR in (". implode(', ', $ar_tmp). ")");
#die(ht(dump($lastresult)));
    if (is_array ($ar_tmp = $_POST['rm']) && count($ar_tmp))
      $db->querynow("delete from attr2kat
        where FK_KAT=$item[ID_KAT] and FK_ATTR in (". implode(', ', $ar_tmp). ")");
*/
    // Permissions
    katperm2role_rewrite();
    // Rewrite cache
    switch($root) {
    	case 4:
    		cache_kat_vendor();
    		break;
    	case 5:
    		cache_kat_request();
    		break;
    	case 6:
    		cache_kat_job();
    		break;
		case 7:
			cache_kat_events();
			break;
		case 8:
			cache_kat_clubs();
			break;
    }

	  $recursive = ($_POST["RECURSIVE"] ? true : false);
	  $recursiveMeta = false;

	  $tpl_content->addvar("RECURSIVE",$recursive);

	  update_fields($_POST["ID_KAT"], $recursive);

    // forward
    forward('index.php?page='.$_REQUEST['page'].'&ID_KAT='. $id
      . '&tabno='. ($b_neu ? 2 : max(1, $_REQUEST['tabno']))
    );
  }
  else
    $tpl_content->addvar('err', implode('<br />', $err));
}

// Ahnen und Kinder ------------------------------------------------------------
$lastresult = $db->querynow("select LFT,RGT from kat where "
  . ($id ? 'ID_KAT='. $id : 'ROOT='. $root. ' and LFT=1'));
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
$tpl_content->addvar('ahnen', tree_show_nested($ar_ahnen, 'tpl/de/kat_edit.katrow.htm',NULL,false));
$tpl_content->addvar('self', $self);
$tpl_content->addvar('kinder', tree_show_nested($ar_kinder, 'tpl/de/kat_edit.katrow.htm',NULL,false));
/*/ // Daten neben einem Baum
foreach($ar_baum as $i=>$row)
{
  if ($row['LFT']<=$lft)
    $ar_baum[$i]['is_last'] = true;
}
$tpl_content->addvar('tree', tree_show_nested($ar_baum, 'tpl/de/kat_edit.row_neu.htm',NULL,false));
/**/

#$str_title = 'Seite '. ($id ? 'bearbeiten':'erstellen');
if(empty($item['LU_KATART']))
  $item['LU_KATART']=18;
$tpl_content->addvars($item);


$tpl_content->addvar('flag', (int)$flag);

if (!$b_neu)
{
  // ATTRIBS
/*
  $nar_attr = $db->fetch_table($db->lang_select('attr'), 'ID_ATTR');
  $ar_liste1 = $ar_liste0 = array ();

  if (count($nar_attr))
  {
    $nar_kat_attr = $db->fetch_nar("select FK_ATTR, FK_KAT, max(k.LFT) from attr2kat
        left join kat k on ID_KAT=FK_KAT
      where ". (int)$item['LFT']. " between LFT and RGT
      group by 1 order by 3,1");
    $nar_kat = (count($nar_kat_attr)
      ? $db->fetch_table($db->lang_select('kat'). ' where ID_KAT in ('
        . implode(', ', $nar_kat_attr). ')', 'ID_KAT')
      : array ()
    );

    $i0 = $i1 = 0;
    foreach($nar_attr as $id_attr=>$attr)
    {
      if ($lft_kat = $nar_kat_attr[$id_attr])
      {
        $tpl_tmp = new Template('tpl/de/kat_edit.attrrow1.htm');
        $tpl_tmp->addvars($nar_kat[$lft_kat], 'KAT_');
        $i = $i1++;
      }
      else
      {
        $tpl_tmp = new Template('tpl/de/kat_edit.attrrow0.htm');
        $i = $i0++;
      }
      $tpl_tmp->addvars($attr);
      $tpl_tmp->addvar('curkat', $id);
      $tpl_tmp->addvar('even', !($i&1));
      $tpl_tmp->addvar('i', $i);
      if ($lft_kat)
        $ar_liste1[] = $tpl_tmp;
      else
        $ar_liste0[] = $tpl_tmp;
    }
    $tpl_content->addvar('attr1', $ar_liste1);
    $tpl_content->addvar('attr0', $ar_liste0);
  }
*/
  // PERMISSIONS
  $ar_roles = $db->fetch_table($db->lang_select('role', '*, z.FK_KAT is null as PERM'). '
    left join katperm2role z on z.FK_ROLE=ID_ROLE and z.FK_KAT='. $id. '
    order by ID_ROLE');
  $tpl_content->addlist('roles', $ar_roles, 'tpl/de/kat_edit.rolerow.htm');
}
/*
else
{
  // TREE
  $res = $nest->nestSelect('', '', ((int)!$nest->tableLock). ' as no_move,', true);
  $top = $db->fetch_atom("select ID_KAT from kat where ROOT=".$root." and LFT=1");
  $tpl_content->addvar('ID_NAV_ROOT', $top);
  $tpl_content->addvar('baum', tree_show_nested($db->fetch_table($res), 'tpl/de/kat_edit.row.htm',NULL,false));
}
*/
$tpl_content->addvar('ROOT', $root);
$tpl_content->addvar('neu', $b_neu = 'ins'==$_REQUEST['do']);
if ($tmp = $_REQUEST['tabno'])
  $tpl_content->addvar('tabno', $tmp);

// Optionen
$ar_opts=$opt_list=array ();
if(!empty($item['SER_OPTIONS']))
{
  if(is_string($item['SER_OPTIONS']))
    $ar_opts = unserialize($item['SER_OPTIONS']);
  else
    $ar_opts = $item['SER_OPTIONS'];
}
#echo ht(dump($ar_opts));
$opts = $db->fetch_table($db->lang_select("kat_option")." where ROOT=".$root."
   order by V1");
for($i=0; $i<count($opts); $i++)
{
  $value=0;
  if(isset($ar_opts[$opts[$i]['ID_KAT_OPTION']]))
  {
    $opts[$i]['in_use']=1;
	$value = $ar_opts[$opts[$i]['ID_KAT_OPTION']];
  }
  $hack = explode ('_', $opts[$i]['IDENT']);
  $tpl_tmp = new Template('tpl/de/kat_edit.optrow.htm');
  $tpl_tmp->addvars($opts[$i]);
  $tpl_tmp->addvar('type_'.$hack[0], 1);
  $tpl_tmp->addvar('value', $value);
  $opt_list[] = $tpl_tmp;
}
$tpl_content->addvar('options', $opt_list);


// geerbte Attributgruppe
$lastresult = $db->querynow($db->lang_select('attr_group', 'ID_ATTR_GROUP,LABEL'). '
  left join kat k on k.ROOT=t.ROOT and k.FK_ATTR_GROUP=t.ID_ATTR_GROUP
  where '. $item['LFT']. ' between k.LFT and k.RGT
    and k.FK_ATTR_GROUP>0 and k.ID_KAT<>'. $item['ID_KAT']. '
    order by k.RGT asc limit 1'
);
$tmp = mysql_fetch_row($lastresult['rsrc']);
$tpl_content->addvar('ag_inherit', stdHtmlentities($tmp[1]));
$tpl_content->addvar('ag_inherit_fk', (int)$tmp[0]);


//....................................................................
require_once "sys/tabledef.php";

$arSearchFieldsSpecial = array("FK_MAN", "PRODUKTNAME", "PREIS", "BF_CONSTRAINTS", "B_PSEUDOPREIS_DISCOUNT");
$tableFields = "vendor_master";
tabledef::getFieldInfo($tableFields);
$query = "
  	SELECT
  		kf.*
  	FROM
  		`kat2field` kf
  	JOIN
  		field_def df on kf.FK_FIELD=df.ID_FIELD_DEF
  	WHERE
  		FK_KAT=" . (int)$id . "
  	ORDER BY
  		df.F_ORDER ASC";
$field_info = $db->fetch_table( $query );
$field_settings = array();
$field_list = array();
// Auf Feld-ID als Index umschreiben
foreach ($field_info as $index => $field_data) {
	$field_settings[$field_data["FK_FIELD"]] = $field_data;
}
//......
foreach (tabledef::$field_info as $index => $field_data) {
	if ((!$field_data['IS_SPECIAL'] || in_array($field_data["F_NAME"], $arSearchFieldsSpecial)) && $field_data['B_ENABLED']) {
		$field_current = $field_settings[(int)$field_data["ID_FIELD_DEF"]];
		$field_data['B_SEARCHFIELD'] = $field_data['B_SEARCHABLE'] = $field_data['B_SEARCH'];
		$field_current = ($field_current ? array_merge($field_data, $field_current) : $field_data);
		//echo($field_data["ID_FIELD_DEF"]." > ".ht(dump($field_current))."<hr />");
		$field_list[] = $field_current;
	}
}
$access = $db->fetch_table($q = "SELECT * FROM `role2kat` WHERE FK_KAT=" . (int)$id);
foreach ($access as $accessCur) {
	$arAccess[$accessCur['FK_ROLE']] = $accessCur;
}
if (is_array($_POST['ALLOW_NEW_AD'])) {
	foreach ($_POST['ALLOW_NEW_AD'] as $roleId => $allowAccess) {
		$arAccess[$roleId] = array("FK_ROLE" => $roleId, "FK_KAT" => $id, "ALLOW_NEW_AD" => (int)$allowAccess);
	}
}

$tpl_content->addlist("liste_felder", $field_list, "tpl/de/m_kat_edit.fieldrow.htm", "check_field");


function update_fields($id, $recursive = false)
{
	if (empty($_POST["fields"])) return;

	global $db, $kat;
	$db->querynow("DELETE FROM `kat2field` WHERE FK_KAT='" . $id . "'");
	$fields_keys = array("FK_KAT", "FK_FIELD", "B_ENABLED", "B_NEEDED", "B_SEARCHFIELD");
	$fields_values = array();
	foreach ($_POST["fields"] as $fk_field => $b_enabled) {
		// (FK_KAT, FK_FIELD, B_ENABLED, B_SEARCH, B_NEEDED)
		$fields_values[] = "('" . $id . "','" . $fk_field . "','" . $b_enabled . "'," .
		                   "'" . ($_POST["neededfields"][$fk_field] ? $_POST["neededfields"][$fk_field] : 0) . "'," .
		                   "'" . ($_POST["searchfields"][$fk_field] ? $_POST["searchfields"][$fk_field] : 0) . "')";
	}
	$query = "INSERT INTO `kat2field` (" . implode(",", $fields_keys) . ") VALUES " . implode(",", $fields_values);
	$db->querynow($query);
	if ($recursive) {
		$childs = $kat->element_get_childs($id);
		foreach ($childs as $index => $data) {
			update_fields($data["ID_KAT"], true);
		}
	}
}

?>