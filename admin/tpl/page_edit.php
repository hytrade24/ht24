<?php
/* ###VERSIONSBLOCKINLCUDE### */

// Layouts
$templateName = $nar_systemsettings['SITE']['TEMPLATE'];

### CHANGES

/*

 31.01.2008 [Schmalle] <meta> Tags aus Brauche Webseite als Defult entfernt
            [Schmalle] Bug behoben, dass man einen Ident nur einmal verwenden kann obwohl Root
			           unterschiedlich ist.

*/

$tpl_content->addvar("USE_SSL", $nar_systemsettings["SITE"]["USE_SSL"]);
$tpl_content->addvar("USE_SSL_GLOBAL", $nar_systemsettings["SITE"]["USE_SSL_GLOBAL"]);

// LOCKING
require_once 'sys/lib.nestedsets.php'; // Nested Sets
$tpl_content->addvar('ROOT', $root = root('nav'));
$nest = new nestedsets('nav', $root, true);

if ($n = $nest->tableLock)
{
  $meldung = (($nest->tableLockData && $tmp = $db->fetch_atom("select NAME from `user` where ID_USER=". $nest->tableLockData['FK_USER']))
      ? 'Der User "'. $tmp. '" bearbeitet zur Zeit diesen Baum.'
      : 'Das Locking ist fehlgeschlagen.'
    ). "<br />\nDaher sind Ihnen Verschieben und L&ouml;schen momentan nicht gestattet.";
}
else
  if ($s_expire = $nar_systemsettings['SITE']['lock_expire'])
    $tpl_content->addvar('timeout', (int)$db->fetch_atom("select unix_timestamp(date_add('1980-01-01', interval "
      .$s_expire . "))-unix_timestamp('1980-01-01')"));

$tpl_content->addvar("locked", $meldung);

#die(ht(dump(array_keys($GLOBALS))));
// ROOT

if ($_REQUEST['setroot']) {
	$root=1; // Public-Site
	$tpl_content->addvar('setroot', $root );
	require_once 'sys/lib.cache.php'; //berni 18.01.06
}
else {
	$root = root('nav'); // welche Root wurde verändert
}

$tpl_content->addvar('ROOT', $root );

### FK_MODUL ins Template schreiben, wenn eine Seite mit einem
### bestimmten Modul erzeugt werden soll
if(isset($_GET['FK_MODUL']))
  $tpl_content->addvar("FK_MODUL", (!empty($_GET['FK_MODUL']) ? $_GET['FK_MODUL'] : NULL));

$nest = new nestedsets('nav', $root, 1);

#echo ht(dump($nav_current));
#echo ht(dump(array_keys($GLOBALS)));

/*

berni 21.dez.05
kein plan was das soll!

$tpl_content->addvar('ref_file_edit',
  Template::tpl_pageref('file_edit', substr($nav_current['ALIAS'],9))
);
echo ht(dump($tpl_content));
die;

*/

// Roles prefix and list
$sRoleDir = ($root == 2 ? 'admin/' : '');
$arRoles = $db->fetch_table("select ID_ROLE, LABEL from role order by ID_ROLE");

if (count($_POST))
{

	$NAVDATE_tmp = time(); // Cache der Struktur im Admin-Bereich l&ouml;schen
	$db->putinto_tmp('NAVDATE',$NAVDATE_tmp);



  #die(print_r($_POST));
  if (!($_POST['ALIAS'] = trim($_POST['ALIAS'])))
    $_POST['ALIAS'] = NULL;
  $msg = array ();
  if(!preg_match('/^[a-z0-9_-]*$/', $_POST['IDENT']))
    $msg[] = $err_identsyntax;
#echo ht('msg='.dump($msg));
  if(!$_POST['V1'])
    $msg[] = $err_require_label;
  if(!$_POST['V2'])
    $_POST['V2'] = NULL;
  if(!$_POST['trg'] && empty($_POST['ID_NAV']))
    $msg[] = $err_require_trg;

	//jan - 31.01.07
	if ($_POST['IDENT'] != "" && !$_POST['ID_NAV'] && $db->fetch_atom("select count(ID_NAV) from nav where IDENT = '".$_POST['IDENT']."' and ROOT=".$root))
		$msg[] = "Dieser Ident wird bereits benutzt. Bitte w&auml;hlen Sie einen anderen Ident.";
	//ende jan
	//***
	if(!$_POST['T1'] || $_POST['T1'] == "") {
		$_POST['T1'] = "";
	}
	//die ("post T1".$_POST['T1']);
#echo ht('msg='.dump($msg));
  if (count($msg))
  {
    $item = $_POST;
    $tpl_content->addvar('msg', implode('<br />', $msg));
  }
  else
  {
    if (!$_POST['ID_NAV'])
    {


#die('huhu');
      $_POST['B_VIS'] = ($_POST['B_VIS'] > 0 ? 1 : 0);
      unset($_POST['ID_NAV']);
      $new=true;
      $ins = $nest->nestInsert($_POST['trg']);
    //echo $ins;
#die(ht(dump($nest)));
      if(empty($nest->errMsg))
      {
        $_POST['ID_NAV'] = $ins;
/**/
        $id = $db->update('nav', $_POST);
		#die(ht(dump($GLOBALS['lastresult'])));
        if ($lastresult['str_error'])
          $tpl_content->addvar('msg', "SQL Fehler. Seite nicht erfolgreich eingetragen!");
        elseif ($_POST['IDENT'])
        {
          $create = @touch(($root == 1 ? '../' : '') . 'tpl/de/'.$_POST['IDENT'].'.htm');
		  if($create)
		    @chmod('../tpl/'.$s_lang.'/'.$_POST['IDENT'].'.htm',0777);
		  if($_POST['FK_MODUL'])
		  {
		    // Modulseite anlegen
			$modul = $db->fetch1("select * from modul where ID_MODUL =".$_POST['FK_MODUL']);
			if(empty($modul))
			  die("Error! Modul ".$_POST['ID_MODUL']." not found!");
			// File Aktionen
			$code = @file("../module/modul.php");
			if(!$code)
			  die("Fatal Error! Could not read the base-mod-file");
			$open = @fopen("../tpl/".$_POST['IDENT'].".php", "w");
			if(!$open)
			  die("Failed to open Mod-Script");
			fwrite($open, implode($code));
			fclose($open);
			chmod("../tpl/".$_POST['IDENT'].".php", 0777);
			// Templates erzeugen Multilingual
			$ar_langu = $db->fetch_table("select ABBR from lang where B_PUBLIC=1");
			  $copy = copy("../module/modul.htm", "../design/".$templateName."/default/tpl/".$_POST['IDENT'].".htm");
			  @chmod("../design/".$templateName."/default/tpl/".$_POST['IDENT'].".htm", 0777);

		  }
		  $sql_ident = mysql_escape_string($_POST['IDENT']);
          // Wenn ident neu in nav-Tabelle ...
          if (1==$db->fetch_atom("select count(*) from nav
            where ROOT=". $root. " and IDENT='". $sql_ident. "'"))
            // ... Rechte entziehen fuer alle Rollen ausser Admin
            $db->querynow("insert into pageperm2role
              select '". (2==$root ? 'admin/' : ''). $sql_ident. "', ID_ROLE
              from role where ID_ROLE<>2");
#echo ht(dump($lastresult)); die();
        }
/*/
        $up = $db->querynow("update nav set ID_NAV=".$ins.", IDENT='".mysql_escape_string($_POST['IDENT'])."',
          `ALIAS`= ". ($_POST['ALIAS'] ? "'".mysql_escape_string($_POST['ALIAS'])."'" : 'NULL'). ",
          BF_LANG=".$langval.",B_VIS=0
        where ID_NAV=".$ins);
        if(!$up)
          $tpl_content->addvar('msg', "SQL Fehler. Seite nicht erfolgreich eingetragen!");
        else
          $db->update("string", array ('S_TABLE' => 'nav', 'FK' => $ins, 'V1' => $_POST['V1']));
/**/
#die(ht(dump($lastresult)));
      }
      else
        $tpl_content->addvar('msg', 'Table Lock Error!');
    }
    else
    {
      // Module
	  $ar_tmp = $db->fetch1("select *,n.IDENT as nID, m.IDENT as mID from nav n
	     left join modul m on n.FK_MODUL=ID_MODUL where ID_NAV=".$_POST['ID_NAV']);
#die(ht(dump($ar_tmp)));
	  if($ar_tmp['FK_MODUL'])
	  {
	    if($_POST['FK_MODUL'] != $ar_tmp['FK_MODUL'])
		{
		  if(file_exists("../module/".$ar_tmp['mID']."/change.php"))
		  {
		    include "../module/".$ar_tmp['mID']."/change.php";
			change($ar_tmp);
			if(!empty($_POST['FK_MODUL']))
			  $mod_neu=1;
		  }
		  if(empty($_POST['FK_MODUL']))
		  {
		      //echo getcwd()."../tpl/".$ar_tmp['nID'].".php";
              if(file_exists("../tpl/".$ar_tmp['nID'].".php"))
		      {
		          $del = @unlink("../tpl/".$ar_tmp['nID'].".php");
                        if(!$del)
    			  die("Fatal error! Could not delete modul script!");
              }
		  }
		  else
		    $neu_mod=1;
		}
	  }
	  if($neu_mod || ($_POST['FK_MODUL'] && !$ar_tmp['FK_MODUL']))
	  {
			$modul = $db->fetch1("select * from modul where ID_MODUL =".$_POST['FK_MODUL']);
			if(empty($modul))
			  die("Error! Modul ".$_POST['ID_MODUL']." not found!");
			// File Aktionen
			$code = @file("../module/modul.php");
			if(!$code)
			  die("Fatal Error! Could not read the base-mod-file");
			$open = @fopen("../tpl/".$_POST['IDENT'].".php", "w");
			if(!$open)
			  die("Failed to open Mod-Script");
			fwrite($open, implode($code));
			fclose($open);
			chmod("../tpl/".$_POST['IDENT'].".php", 0777);
			// Templates erzeugen Multilingual
			$ar_langu = $db->fetch_table("select ABBR from lang where B_PUBLIC=1");
		  	$copy = copy("../module/modul.htm", "../design/".$templateName."/default/tpl/".$_POST['IDENT'].".htm");
			  @chmod("../design/".$templateName."/default/tpl/".$_POST['IDENT'].".htm", 0777);
	  }
      if (isset($_POST['B_SSL_RECURSIVE'])) {
          // SSL-Einstellung rekursiv übernehmen!
          $query = "UPDATE `nav` SET B_SSL=".(int)$_REQUEST['B_SSL']." WHERE ROOT=".$root."\n".
                    "   AND LFT BETWEEN ".$ar_tmp["LFT"]." AND ".$ar_tmp["RGT"];
          $db->querynow($query);
      }
      if (!isset($_POST['B_SEARCH'])) $_POST['B_SEARCH'] = 0;
      $_POST['B_VIS'] = ($_POST['B_VIS'] > 0 ? 1 : 0);
	  $id = $db->update("nav", $_POST);
	  require_once ("sys/lib.search.php");
	  $search = new do_search($s_lang,false);
	  $search->add_new_text($_POST['V2'].' '.$_POST['V1'],$id,'nav');
#      $db->update('string', $_POST);

#berni Prüft ob die Seite über ein POPUP aufgerufen wurde (wenn Setroot=1)
    }

    if(empty($msg) && empty($nest->errMsg))
    {
        // Apply new role permissions
        if (is_array($_POST['mod'])) {
            $s_ident = $sRoleDir.$_POST["IDENT"];
            $arRoleDeny = array_keys(
                $db->fetch_nar("SELECT FK_ROLE, IDENT FROM pageperm2role WHERE IDENT='".mysql_real_escape_string($s_ident)."'")
            );
            foreach ($arRoles as $index => $arRole) {
                $roleAllowed = ($_POST['mod'][ $arRole["ID_ROLE"] ] > 0 ? true : false);
                $roleAllowedSaved = !in_array($arRole["ID_ROLE"], $arRoleDeny);
                if ($roleAllowed != $roleAllowedSaved) {
                    if ($roleAllowed) {
                        $db->querynow($q = "DELETE FROM pageperm2role
                            WHERE FK_ROLE=".$arRole["ID_ROLE"]." AND IDENT='".mysql_real_escape_string($s_ident)."'");
                    } else {
                        $db->querynow($q = "INSERT INTO pageperm2role (FK_ROLE, IDENT)
                            VALUES (".$arRole["ID_ROLE"].", '".mysql_real_escape_string($s_ident)."')");
                    }
                }
                $arRoles[$index]["ALLOWED"] = (in_array($arRole["ID_ROLE"], $arRoleDeny) ? 0 : 1);
            }
        }
        // Rewrite role permissions
        include "sys/lib.perm_admin.php";
        include "sys/lib.cache.php";
        pageperm2role_rewrite();
        cache_nav_all($root);
      #die(listtab($GLOBALS['ar_query_log']));
	  	if ($_REQUEST['setroot']) {
			forward('index.php?frame=popup&page=page_edit&setroot=1&ID_NAV='. $id."#nav".$id);
		}
		else
		{
      		forward('index.php?page=nav_edit&do=new&id='.$id."#nav".$id);
	  	}
    }
  }

}
else
{
	$flag = 2;
	if ($id=(int)$_REQUEST['ID_NAV']) {
    	$item = $db->fetch1($db->lang_select('nav'). "where ID_NAV=$id");
		//echo ht(dump($item));
		//echo ht(dump($item['T1']));
		//echo ht(dump($item));
	} else {
		$item = $db->fetch_blank('nav');
		#$item['FK_GROUP'] = (int)$_GET['FK_GROUP'];
		$item['IDENT'] = trim($_GET['IDENT']);
		//prüfen, ob meta tags für die seite (schon) gesetzt sind
		//falls nicht mit default wert füllen
		if(!isset($item['T1'])){
			$item['T1'] = @file_get_contents($ab_path."cache/meta_def_".$s_lang.".txt");
		}
	}
	$item['META'] = preg_replace("/\n|\r/si", "", @file_get_contents($ab_path."cache/meta_def_".$s_lang.".txt"));
}

// List of role permissions
$s_ident = $sRoleDir.(isset($_POST["IDENT"]) ? $_POST["IDENT"] : $item["IDENT"]);
$arRoleDeny = array_keys(
    $db->fetch_nar("SELECT FK_ROLE, IDENT FROM pageperm2role WHERE IDENT='".mysql_real_escape_string($s_ident)."'")
);
if ($item["ID_NAV"] > 0) {
    // Existing page
    foreach ($arRoles as $index => $arRole) {
        $arRoles[$index]["ALLOWED"] = (in_array($arRole["ID_ROLE"], $arRoleDeny) ? 0 : 1);
    }
} else {
    // Default setting
    foreach ($arRoles as $index => $arRole) {
        if ($root == 2) {
            // Admin
            $arRoles[$index]["ALLOWED"] = ($arRole["ID_ROLE"] == 2 ? 1 : 0);
        } else {
            // Public
            $arRoles[$index]["ALLOWED"] = 1;
        }
    }
}
$tpl_content->addlist("listRoles", $arRoles, "tpl/de/page_edit.row_role.htm");

// raus berni 03.09.08
/*
if ($do = $_REQUEST['do'])
{
echo "prüfen";
  if ($id_file = (int)$_REQUEST['ID_TPLFILE'])
    $tplfile = $db->fetch1("select * from tplfile where ID_TPLFILE=". $id_file);
  switch ($do)
  {
    case 'get':
      if(!$id_file)
        $tplfile['B_WYSIWYG'] = preg_match('/\.htm/', $tplfile['FN'] = $_REQUEST['file']);
      $s_path = '../tpl/'. $tplfile['FN'];
      $tplfile['BODY'] = implode('', file($s_path));
      $tplfile['STAMP_MODIFY'] = $tplfile['STAMP_PUBLISH'] = date('Y-m-d H:i:s', filemtime($s_path));
      $id_file = $db->update('tplfile', $tplfile);
      break;
    case 'put':
      $fp = fopen($s_path = '../tpl/'. $item['FN'], 'w');
      fputs($fp, $item['BODY']);
      fclose($fp);
      break;
  }
  forward('index.php?page=page_edit&ID_NAV='. $id."#nav".$id);
}
*/
$str_title = 'Seite '. ($id ? 'bearbeiten':'erstellen');
$tpl_content->addvars($item);


$tpl_content->addvar('flag', (int)$flag);

// files raus berni 03.09.08
/*
$ar_tmp = $ar_files = array ();
#$dp_root = opendir('.');
$id=(int)$_REQUEST['ID_NAV'];

$ar_dirs = array ('../tpl');
while ($s_dir = array_shift($ar_dirs))
{
  $dp = opendir($s_dir);
#echo $s_dir,'<br>';
  while ($s_fn = readdir($dp)) if ('.'!=$s_fn && '..'!=$s_fn)
    if (is_dir($s_path = "$s_dir/$s_fn"))
      $ar_dirs[] = $s_path;
    else
      if (ereg('\/'. preg_quote($item['IDENT']). '((_|\.)[a-z_]*)?\.(htm|php)$', $s_path))
        $ar_tmp[] = substr($s_path, ('../tpl'==$s_dir ? 6 : 7)). date('?Y-m-d H:i:s', filemtime($s_path));
  closedir($dp);
}
sort($ar_tmp);
$sql_ident = mysql_escape_string($item['IDENT']);
//$dbfiles = $db->fetch_table("select * from tplfile  where FN like '%/$sql_ident%' or FN like '$sql_ident%'", 'FN');  // raus berni 03.09.08
foreach($ar_tmp as $i=>$tmp)
{
  list($s_path, $mtime) = explode('?', $tmp);
  $s_fn = preg_replace('%^/(.*)$%', '$1', $s_path);
  //$row = $dbfiles[$s_fn];  raus berni 03.09.08
#echo ht(dump($row)), dump($s_path), dump($mtime), '<hr>';
  $tpl_tmp = new Template('tpl/de/page_edit.row.htm');
  $tpl_tmp->addvar('path1', ($i ? 3 : 11));
  $tpl_tmp->addvar('path2', ($i ? 0 : 36));
  $tpl_tmp->addvar('file', $s_fn);
  $tpl_tmp->addvar('ID_NAV', $id);
  $tpl_tmp->addvar('ID_TPLFILE', $row['ID_TPLFILE']);
  //$tpl_tmp->addvar('b_get', !$id || $row['STAMP_MODIFY']<$mtime);  //raus berni 03.09.08
  //$tpl_tmp->addvar('b_put', $id && $row['STAMP_MODIFY']>$mtime);  // raus berni 03.09.08
  $ar_files[] = $tpl_tmp;
}
//$tpl_content->addvar('filelist', $ar_files);
*/

if (!empty($_REQUEST["special"])) {
    list($specialType, $specialId) = explode("-", $_REQUEST["special"]);
    switch ($specialType) {
        case "kat":
            include "sys/lib.shop_kategorien.php";
            $kat = new TreeCategories("kat", 1);
            $arKat = $kat->element_read($specialId);
            $tpl_content->addvar("DISABLE_IDENT", true);
            $tpl_content->addvar("DISABLE_ALIAS", true);
            $tpl_content->addvar("ALIAS", "marktplatz,".$specialId.",".addnoparse(chtrans($arKat["V1"])));
            $tpl_content->addvar("V1", $arKat["V1"]);
            $tpl_content->addvar("V2", $arKat["V2"]);
            break;
    }
}

// TREE
$res = $nest->nestSelect('', '', ((int)!$nest->tableLock). ' as no_move,', true);
$ar = $db->fetch_table($res);

$top = $db->fetch_atom("select ID_NAV from nav where ROOT=". $root. " and LFT=1");
$tpl_content->addvar('ID_NAV_ROOT', $top);
$tpl_content->addvar('baum', tree_show_nested($ar, 'tpl/de/nav_edit.row.htm',NULL,false));


$ar_layouts = array ();
$directories = array();

if(1==$root) {
    $directories[] = '../design/default/default/skin';
    $directories[] = '../design/default/'.$s_lang.'/skin';
    if ($templateName != '') {
        $directories[] = '../design/' . $templateName . '/default/skin';
        $directories[] = '../design/' . $templateName . '/' . $s_lang . '/skin';
    }
} else {
    $directories[] = 'skin';
}

foreach($directories as $key => $directory) {
    if (is_dir($directory)) {
        $dp = opendir($directory);

        while ($fn = readdir($dp)) if (preg_match('/^index-(\w+)\_?/', $fn, $match)) {
            $ar_layouts[$match[1]] = '<option ' . ($item['S_LAYOUT'] == '-' . $match[1] ? 'selected ' : '') . 'value="-' . $match[1] . '">' . $match[1] . '</option>';
        }
        closedir($dp);
    }
}


#echo ht(dump($ar_layouts));
$tpl_content->addvar('layoutopts', $ar_layouts);
#echo ht(dump($ar_layouts));
?>