<?php
/* ###VERSIONSBLOCKINLCUDE### */



function check_undo_step(&$row, $i) {
  if ($row["ACTION"] == "MOVE") {
    $row["IS_MOVE"] = 1;
  }
  if ($row["ACTION"] == "RESTORE") {
    $row["IS_RESTORE"] = 1;
  }
}

global $kat, $target_kat, $ar_add_fields, $nar_systemsettings;

require_once "sys/lib.nestedsets.php";
// include "sys/lib.katnested.php";
require_once "sys/lib.shop_kategorien.php";
require_once $ab_path."sys/lib.pub_kategorien.php";

// $kat_nested = new kat("kat", 1, false, $db);
$kat = new TreeCategories("kat", 1);
$kat_cache = new CategoriesCache();

$tpl_content->addvar("ID_KAT", 1);

$arRolesIgnored = array(1);
$nar_roles = $db->fetch_nar(
    "select ID_ROLE, LABEL from role".
    (!empty($arRolesIgnored) ? " WHERE ID_ROLE NOT IN (".implode(", ", $arRolesIgnored).")" : "").
    " order by ID_ROLE");

if (array_key_exists("ajax_kat_layer", $_REQUEST) && ($_REQUEST["ajax_kat_layer"] > 0)) {
    $id_kat = $_REQUEST["ajax_kat_layer"];
    $kat_paid = (int)$_REQUEST["paid"]; 
        
    $id_kat_top = $kat->tree_get_parent();
    $tpl_base = new Template("tpl/de/m_kats.ajax_layer.htm");
    $id_root_kat = $kat->tree_get_parent($id_kat);
    // Add category ids
    $tpl_base->addvar('ID_ROOT_KAT', $id_root_kat);
    $tpl_base->addvar('ID_KAT', $id_kat);
    // Add root category settings
    if (($id_root_kat > 0) && ($id_kat != $id_root_kat)) {
        $ar_root = $kat->element_read($id_root_kat);
        $tpl_base->addvars($ar_root, "ROOTKAT_");
    }
    // Get category tree
    $arTree = $kat->element_get_childs($id_kat);
    // Read children and remove paid ones (if not allowed)
    foreach ($arTree as $treeIndex => $treeNode) {
        $arTree[$treeIndex]["kids"] = $kat->element_has_childs($treeNode["ID_KAT"]);
        if ($treeNode["PARENT"] != $id_kat) {
            $arTree[$treeIndex]["HIDDEN"] = 1;
        }
        if (!$show_paid && !$treeNode["B_FREE"]) {
            $arTree[$treeIndex]["REMOVED"] = 1;
        }
        $arTree[$treeIndex]["KAT_ROOT"] = $id_root_kat;
        $arTree[$treeIndex]["ACTIVE"] = ($treeNode["ID_KAT"] == $arKatLayers[$layerIndex+1] ? 1 : 0);
    }
    // Remove unavailable
    for ($treeIndex = count($arTree) - 1; $treeIndex >= 0; $treeIndex--) {
        if ($arTree[$treeIndex]["REMOVED"]) {
            unset($arTree[$treeIndex]);
        }
    }
    if (!empty($arTree)) {
        #die(var_dump($arTree));
        $tpl_base->addlist("CATEGORIES", $arTree,"tpl/de/m_kats.ajax_layer.row.htm");
    }

    if($kat_id_root != null) {
        //$categoryHashMap = $categoriesBase->getCategoryPathHashMap();

        $tpl_base->addvar("FK_KAT", $kat_id_root);
        //$tpl_base->addvar("PRESELECTED_FK_KAT_NAME", $categoryHashMap['ID'][$kat_id_root]['V1']);
        /*if ($tplVarOptions["NO_WARNING"]) {
            $tpl_base->addvar("NO_WARNING", 1);
        }
        */
    }
    header("Content-Type: application/json");
    die(json_encode(array(
        "tree" => $tpl_base->process()
    )));
}

// AJAX Kategorie-Abfrage
if($_REQUEST["ajax_kat_path"] > 0) {
    $idKatPath = (int)$_REQUEST["ajax_kat_path"];

    $katPathIdArray = array();
    $katPath = $kat_cache->kats_read_path($idKatPath);
    foreach($katPath as $key => $value) {
        $katPathIdArray[] = $value['ID_KAT'];
    }

    echo json_encode(array('success' => true, 'data' => $katPathIdArray)); die();
} else {
    $target_kat = ($_REQUEST["ajax_kat"] ? $_REQUEST["ajax_kat"] : 0);
    if ($target_kat > 0) {
        $tpl_content->addvar("ajax_kat", $target_kat);
        if ($_REQUEST['frame'] == "ajax") {
            global $nar_roles;
            if (empty($nar_roles)) $nar_roles = array();
            ### Felder für Filter
            $res = $db->querynow("select
                        t.ID_IMPORT_FILTER,
                        t.IDENT,
                        s.V1
                    from
                        `import_filter` t
                    left join
                        string_app s on s.S_TABLE='import_filter'
                        and s.FK=t.ID_IMPORT_FILTER
                        and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
                    ORDER BY
                        s.V1 ASC");
            $id_root = ($target_kat ? $target_kat : $kat->tree_get_parent());
            $ar_tree = $kat->element_get_childs($id_root);
            $ar_add_fields = array();
            while($row = mysql_fetch_assoc($res['rsrc'])) {
                $ar_add_fields[$row['ID_IMPORT_FILTER']] = $row['IDENT'];
            }
            foreach ($nar_roles as $roleId => $roleName) {
                $ar_tmp[] = '<th class="rightsCol"><img width="13" src="tpl/role'.$roleId.'.png"></th>';
            }
            //$ar_tree = $kat->element_get_childs($_REQUEST['target']);
            //array_unshift($ar_tree, $kat->element_read($_REQUEST['target']));
            /*
            $ar_tree = $kat->tree_get(), 'tpl/de/m_kats.row.htm', 'katperm_show', !$frame, (int)$id, 'ID_KAT',$ar_add_fields);
            $ar_tree_part = array();
            foreach ($ar_tree as $index => $tpl_kat) {
                if (($tpl_kat->vars['ID_KAT'] == $_REQUEST['target']) ||
                        ($tpl_kat->vars['PARENT'] == $_REQUEST['target'])) {
                    $ar_tree_part[] = $tpl_kat;
                }
            }*/
            $tpl_content->addlist('liste', $ar_tree, 'tpl/de/m_kats.row.htm', 'katperm_show');
            die($tpl_content->process_text("{liste}"));
        }
    }
}

if (!empty($_POST) && $_POST['do'] == 'mod') {
    $arUpdateAccessKats = $_POST['UPDATE_ALLOW_NEW_AD'];
    foreach ($arUpdateAccessKats as $katId) {
        foreach ($nar_roles as $roleId => $roleName) {
            $value = (int)$_POST['ALLOW_NEW_AD'][$katId][$roleId];
            $query = "INSERT INTO `role2kat` (FK_ROLE, FK_KAT, ALLOW_NEW_AD)".
                " VALUES (".(int)$roleId.", ".(int)$katId.", ".$value.")".
                " ON DUPLICATE KEY UPDATE ALLOW_NEW_AD=".$value;
            $db->querynow($query);
        }
    }
    die(forward("index.php?page=m_kats"));
}
if(isset($_GET['do']) && $_GET['do'] == 'modall') {
	$roleId = (int)$_GET['roleId'];
	$db->querynow("DELETE FROM role2kat WHERE FK_ROLE = '".$roleId."'");

	$modValue = $_GET['modcheck']?1:0;
	$db->querynow("INSERT INTO role2kat (FK_ROLE, FK_KAT, ALLOW_NEW_AD) SELECT '".$roleId."' as FK_ROLE, ID_KAT as FK_KAT, ".(int)$modValue." as ALLOW_NEW_AD FROM kat WHERE ROOT = 1");

	die(forward("index.php?page=m_kats"));
}

if (!empty($_POST)) die(ht(dump($_POST["fields"])));

// predef
$nar_deny = $ar_modhead = array (); $i=0;
$id = $_REQUEST['id'];

$tpl_main->addvar("HIGHLIGHTED", $id);

// preset role & perm
foreach($nar_roles as $id_role => $s_label)
{
	$nar_deny[$id_role] = array ();
    $ar_modhead[] = '<th class="'. ($i++ ? 'narrow':'borderleft'). '" title="'. stdHtmlentities($s_label). '"><img src="tpl/role'. $id_role. '.png" /></th>';
}
$lastresult = $db->querynow("select FK_ROLE, FK_KAT from katperm2role".(!empty($arRolesIgnored) ? " WHERE FK_ROLE NOT IN (".implode(", ", $arRolesIgnored).")" : ""));
while (list($id_tmp, $s_tmp) = mysql_fetch_row($lastresult['rsrc']))
{
	$nar_deny[$id_tmp][$s_tmp] = 1;
}

// add role & action to template
$tpl_content->addvar('modhead', $ar_modhead);
$tpl_content->addvar('modcount', count($ar_modhead));


// baum holen
//$kat_nested->getTree();
//die(ht(dump($kat_nested->ar_tree)));

if ($kat->lock_user) {
  $tpl_content->addvar("LOCKID", $kat->lock_user);
  $tpl_content->addvar("LOCKEXPIRE", date("H:i",$kat->lock_expire));
  $tpl_content->addvar("LOCKUSER", $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$kat->lock_user));
  if ($kat->lock_user != $uid)
    $tpl_content->addvar("IS_LOCKED", $kat->lock_user);
}

if (isset($_REQUEST["UNDO_APPLY"])) {
  if ($kat->undo_apply_step($_REQUEST["UNDO_APPLY"])) {
    // Erfolg - Cache neu schreiben
    require_once "../sys/lib.pub_kategorien.php";
    CategoriesBase::deleteCache();
    die(forward("index.php?page=m_kats&id=".$id));
  }
}
if (isset($_REQUEST["BACKUP_RESTORE"])) {
  if ($kat->tree_backup_restore($_REQUEST["BACKUP_RESTORE"], ($_REQUEST["UNDO_BACKUP"] == 1 ? false : true))) {
    // Erfolg - Cache neu schreiben
    require_once "../sys/lib.pub_kategorien.php";
    CategoriesBase::deleteCache();
    die(forward("index.php?page=m_kats&id=".$id));
  }
}

if (isset($_REQUEST["UNDO_PREVIEW"])) {
  $ar_tree = $kat->undo_preview_step($_REQUEST["UNDO_PREVIEW"]);
  $tpl_main->addvar("IS_PREVIEW", 1);
  $tpl_main->addvar("ID_UNDO_PARAM", $_REQUEST["UNDO_PREVIEW"]);

  $highlights_add = array();
  $highlights_del = array();

  // Highlight changes
  foreach ($ar_tree as $index => $element) {
    if ($element["HIGHLIGHT"] == "NEW")
      $highlights_add[] = "row".$element["ID_KAT"];
    if ($element["HIGHLIGHT"] == "DEL")
      $highlights_del[] = "row".$element["ID_KAT"];
  }
  $tpl_main->addvar("HIGHLIGHT_ADD", implode(",",$highlights_add));
  $tpl_main->addvar("HIGHLIGHT_DEL", implode(",",$highlights_del));
}

if (isset($_REQUEST["BACKUP_PREVIEW"])) {
  $ar_backup = $kat->tree_backup_preview($_REQUEST["BACKUP_PREVIEW"]);
  $ar_tree = $kat->tree_get($ar_backup);
  $tpl_main->addvar("IS_PREVIEW", 1);
  $tpl_main->addvar("ID_BACKUP", $_REQUEST["BACKUP_PREVIEW"]);
  $tpl_main->addvar("UNDO_BACKUP", $_REQUEST["UNDO_BACKUP"]);
}

if (empty($ar_tree)) {
	$id_root = ($target_kat ? $target_kat : $kat->tree_get_parent());
    if ($_REQUEST['all']) {
        // Gesamten Baum inklusive aller Unterkategorien auslesen
        $ar_tree = $kat->tree_get();
    } else {
        // Nur die oberste Ebene auslesen, Unterkategorien werden bei Bedarf per AJAX nachgeladen.
        $ar_tree = $kat->element_get_childs($id_root);
    }

	//$id_root = $kat->tree_get_parent();
	//$ar_tree = $kat->element_get_childs($id_root);
}

if ($ar_tree)
{
	### Felder für Filter
	$res = $db->querynow("select
				t.ID_IMPORT_FILTER,
				t.IDENT,
				s.V1
			from
				`import_filter` t
			left join
				string_app s on s.S_TABLE='import_filter'
				and s.FK=t.ID_IMPORT_FILTER
				and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
			ORDER BY
				s.V1 ASC");
	$ar_tmp = array(); $ar_add_fields = array();
	while($row = mysql_fetch_assoc($res['rsrc'])) {
		$ar_tmp[] = '<th class="importCol"><a href="index.php?page=importfilter_edit&ID_IMPORT_FILTER='.$row['ID_IMPORT_FILTER'].'"><img src="gfx/btn.edit.gif">'.stdHtmlentities($row['V1']).'</a></td>';
		$ar_add_fields[$row['ID_IMPORT_FILTER']] = $row['IDENT'];
	}
    foreach ($nar_roles as $roleId => $roleName) {
		$allRolesEnabled = ($db->fetch_atom("SELECT COUNT(k.ID_KAT) FROM kat k LEFT JOIN role2kat r ON r.FK_KAT = k.ID_KAT WHERE r.FK_ROLE = '".(int)$roleId."' AND k.ROOT=1 AND r.ALLOW_NEW_AD = 0 AND k.ID_KAT != 1 ") == 0);
        $ar_tmp[] = '<th class="rightsCol"><img width="13" src="tpl/role'.$roleId.'.png"><br><br><a href="#" onclick="return toggleKatRoles(\''.$roleId.'\', '.($allRolesEnabled?'false':'true').');"><img src="/gfx/'.($allRolesEnabled?'all_uncheck':'all_check').'.gif"></a></th>';
    }

    $tpl_content->addvar("add_header", implode("\n", $ar_tmp));
	
	unset($ar_tmp);
  #die(ht(dump($ar_tree)));
    
    $ar_liste = [];
    $i = 0;
    while (($arRow = array_shift($ar_tree)) !== null) {
        katperm_show($arRow);
        $tplRow = new Template('tpl/de/m_kats.row.htm');
        $tplRow->addvars($arRow);
        $tplRow->addvar("i", $i);
        $tplRow->addvar("even", (($i&1) == 0 ? 1 : 0));
        unset($arRow);
        $ar_liste[] = $tplRow->process();
        $i++;
    }
    $tpl_content->addvar("liste", $ar_liste);
    unset($ar_liste);

    #$tpl_content->addlist_fast('liste', $ar_tree, 'tpl/de/m_kats.row.htm'); #, 'katperm_show');
	//$tpl_content->addvar('liste', $baum = tree_show_nested($ar_tree, 'tpl/de/m_kats.row.htm', 'katperm_show', !$frame, (int)$id, 'ID_KAT',$ar_add_fields));
	#echo ht(dump($baum));
}	// tree ist nicht leer

// Rückgängig Schritte auflisten
$undo_steps = $kat->undo_get_steps();
if (!empty($undo_steps)) {
  $tpl_main->addlist('liste_undo', $undo_steps, "tpl/de/m_kats.row_undo.htm", check_undo_step);
    //$tpl_main->vars["liste_undo"] = $tpl_main->vars["liste_undo"]->parse();
  $liste_undo = "";
  foreach ($tpl_main->vars["liste_undo"] as $index => $tpl_row) {
    if (isset($_REQUEST["UNDO_PREVIEW"]))
      $tpl_row->addvar("ID_UNDO_PARAM", $_REQUEST["UNDO_PREVIEW"]);
    $liste_undo .= $tpl_row->process();
  }
  $tpl_main->vars["liste_undo"] = $liste_undo;
}

function katperm_show(&$row)
{
	global $db, $kat, $ar_add_fields, $nar_roles, $nar_deny, $nar_extra, $nar_perm_admin_katperm, $langval, $nar_systemsettings;
    $row["FREE_ADS"] = $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"];
	$row["add_columns"] = "";
    foreach ($ar_add_fields as $importId => $importField) {
        $row["add_columns"] .= "<td class='importCol'>".$row["FK_".$importField]."</td>";
    }
    $access = $db->fetch_table($q="SELECT * FROM `role2kat` WHERE FK_KAT=".(int)$row["ID_KAT"]);
    $row["ALLOW_NEW_AD"] = array();
    foreach ($access as $accessCur) {
        $row["ALLOW_NEW_AD"][ $accessCur['FK_ROLE'] ] = $accessCur['ALLOW_NEW_AD'];
        $row["ALLOW_NEW_AD_".$accessCur['FK_ROLE']] = $accessCur['ALLOW_NEW_AD'];
    }
    if ($row["FK_INFOSEITE"] > 0) {
        $row["INFOSEITE_NAME"] = $db->fetch_atom("
          SELECT s.V1 FROM `infoseite` i JOIN `string_info` s ON s.S_TABLE='infoseite' AND s.BF_LANG=if(i.BF_LANG_INFO & ".$langval.", ".$langval.", 1 << floor(log(i.BF_LANG_INFO+0.5)/log(2))) AND FK=".(int)$row["FK_INFOSEITE"]);
    }
    foreach ($nar_roles as $roleId => $roleName) {
        $checked = ($row["ALLOW_NEW_AD"][$roleId] ? "checked='checked' " : "");
        $row["add_columns"] .= "<td class='importCol'><input type='checkbox' name='ALLOW_NEW_AD[".$row['ID_KAT']."][".$roleId."]' value='1' title='".$roleName."' ".$checked."/></td>";
    }
	$row["kidcount"] = $db->fetch_atom("SELECT count(*) FROM `kat` WHERE PARENT=".$row["ID_KAT"]);
	$row["padding"] = 6 + (($row["LEVEL"]-1) * 18);
	$row["editable"] = true;
	$ar_kat = $kat->element_read($row["ID_KAT"]);
	$id_parents = $db->fetch_nar("SELECT ID_KAT, ID_KAT FROM `kat` WHERE LFT<".$ar_kat["LFT"]." AND RGT>".$ar_kat["RGT"]);
	$parent_styles = array();
	foreach ($id_parents as $id_parent) {
		$parent_styles[] = "child".$id_parent;
	}
	$row["css_childs"] = implode(" ", $parent_styles);
	
	#die(ht(dump($nar_deny)));
	static $nar_cache = array (), $nar_level2attrg = array (), $blank=NULL;
	
    /*
        $row['AD_TABLE_DESC'] = $db->fetch_atom("
		SELECT
			s.V1
		FROM `table_def` t
			LEFT JOIN `string_app` s ON
				s.S_TABLE='table_def' AND s.FK=t.ID_TABLE_DEF AND
				s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
		WHERE
			t.T_NAME='".mysql_escape_string($row['KAT_TABLE'])."'");
    
    */

    $rowdef = $db->fetch1("
		SELECT
			s.V1 as AD_TABLE_DESC,ID_TABLE_DEF
		FROM `table_def` t
			LEFT JOIN `string_app` s ON
				s.S_TABLE='table_def' AND s.FK=t.ID_TABLE_DEF AND
				s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
		WHERE
			t.T_NAME='".mysql_escape_string($row['KAT_TABLE'])."'");
        $row['AD_TABLE_DESC'] =  $rowdef['AD_TABLE_DESC'];
         $row['ID_TABLE_DEF'] =  $rowdef['ID_TABLE_DEF'];
            
  	if (!$row['ID_KAT'])
  	{
    	if (!$blank)
    	{
      		$blank = array ('rolemod'=>'<td align="center" class="borderleft">&radic;</td>'. str_repeat('<td align="center" class="narrow">&radic;</td>', count($nar_roles)-1));
    	}
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
    	foreach($nar_roles as $id_roletmp=>$label)
    	{
      		$s_title = ' title="'. stdHtmlentities($label). '"';
      		$s_class = ($i++ ? 'narrow' : 'borderleft');
      		$ar_tmp[] = '<input type="hidden" name="mod['. $id_roletmp. ']['.$row['ID_KAT']. ']" value="0" />';
        	$ar_tmp[] = '<td class="'. $s_class. '" style="text-align:center;"><input type="checkbox" '.$s_title.' '.($nar_deny[$id_roletmp][$row['ID_KAT']] ? '':'checked ').' name="mod['. $id_roletmp. ']['.$row['ID_KAT']. ']" value="1" '. $s_title. '></td>';
    	}
    	$tmp['rolemod'] = $ar_tmp;
  	}
  	$row = array_merge($row, $tmp);
}

$tpl_content->addvar('lock_user', $kat->lock_user);
$tpl_content->addvar('FREE_ADS', $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]);
if ($target_kat > 0) {
	die($tpl_content->process(true));
}
?>