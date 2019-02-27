<?php
/* ###VERSIONSBLOCKINLCUDE### */


include "sys/lib.nestedsets.php";
include "sys/lib.katnested.php";

include "sys/lib.shop_kategorien.php";

require_once "sys/tabledef.php";

$SILENCE = false;

function option_checked(&$row, $i)
{
    global $node_parent, $node_edit;
    if (!empty($_POST)) {
        if ($_POST["KAT_TABLE"] == $i) $row["SELECTED"] = true;
    } else if (($node_parent["KAT_TABLE"] == $i) || ($node_edit["KAT_TABLE"] == $i)) {
        $row["SELECTED"] = true;
    } else if (($node_parent["KAT_TABLE"] == "ad_master") && ($i == "artikel_master")) {
        $row["SELECTED"] = true;
    }
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

/**
 * Apply default category settings if undefined
 */
function options_apply_defaults(&$options)
{
    // Apply default options
    if (!$options['SALES']) $options['SALES'] = array(0, 1, 2);
    if (!array_key_exists('USE_ARTICLE_LOCATION', $options)) $options['USE_ARTICLE_LOCATION'] = 1;
    if (!array_key_exists('USE_ARTICLE_BASEPRICE', $options)) $options['USE_ARTICLE_BASEPRICE'] = 1;
    return $options;
}

$kat = new TreeCategories("kat", 1);
$deftable = new tabledef(NULL, true);
$deftable->getTables(0, 1, false);

$tpl_content->addvar("B_VIS", 1);

$id_kat = $_REQUEST["ID_KAT"];
$id_root = $kat->tree_get_parent();
$id_parent = ($_REQUEST["PARENT"] ? $_REQUEST["PARENT"] : $kat->tree_get_parent($id_kat));

global $node_edit, $node_parent;



$meta_def = @file_get_contents($ab_path . "cache/meta_def_" . $s_lang . ".txt");
$tpl_content->addvar("PARENT", $id_parent);
$tpl_content->addvar("ROOT", $id_root);
$tpl_content->addvar("META_DEF", $meta_def);
$tpl_content->addvar("ENABLE_RENT", $nar_systemsettings['MARKTPLATZ']['ENABLE_RENT']);

$arRolesIgnored = array(1);
$nar_roles = $db->fetch_nar(
    "select ID_ROLE, LABEL from role" .
    (!empty($arRolesIgnored) ? " WHERE ID_ROLE NOT IN (" . implode(", ", $arRolesIgnored) . ")" : "") .
    " order by ID_ROLE");
$arAccess = array();
$arAccessList = array();
$arSearchFieldsSpecial = array("FK_MAN", "PRODUKTNAME", "PREIS", "BF_CONSTRAINTS", "B_PSEUDOPREIS_DISCOUNT");
if ($nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_EAN']) {
    $arSearchFieldsSpecial[] = "EAN";
}

// Add parent values as default
if (($id_kat) && ($node_edit = $kat->element_read($id_kat))) {
    // Edit
    $node_edit_parent = $kat->element_read($node_edit["PARENT"]);
    if ((($node_edit_parent["KAT_TABLE"] != "ad_master") && ($node_edit_parent["KAT_TABLE"] != "artikel_master")) 
            || $kat->element_is_used($id_kat)) {
        $tpl_content->addvar("KAT_LOCKED", 1);
    }
    $tpl_content->addvars($node_edit);
    $tpl_content_links->addvar("ID_KAT", $id_kat);
    $tpl_content_links->addvar("V1", $node_edit['V1']);
    $ar_options = options_apply_defaults($node_edit['OPTIONS']);
    $tpl_content->addvars($ar_options, 'OPTIONS_');
    $tpl_content->addvars(array_flatten($ar_options), 'OPTIONS_');
    $tableFields = (isset($_POST["KAT_TABLE"]) ? $_POST["KAT_TABLE"] : $node_edit["KAT_TABLE"]);
    if ($tableFields == "ad_master") {
        $tableFields = "artikel_master";
    }
    tabledef::getFieldInfo($tableFields);
    $field_info = $db->fetch_table("
  	SELECT
  		kf.*
  	FROM
  		`kat2field` kf
  	JOIN
  		field_def df on kf.FK_FIELD=df.ID_FIELD_DEF
  	WHERE
  		FK_KAT=" . (int)$id_kat . "
  	ORDER BY
  		df.F_ORDER ASC");
    $field_settings = array();
    $field_list = array();
    // Auf Feld-ID als Index umschreiben
    foreach ($field_info as $index => $field_data) $field_settings[$field_data["FK_FIELD"]] = $field_data;
    // Und mit voreinstellungen mergen
    foreach (tabledef::$field_info as $index => $field_data) {
        if ((!$field_data['IS_SPECIAL'] || in_array($field_data["F_NAME"], $arSearchFieldsSpecial)) && $field_data['B_ENABLED']) {
            $field_current = $field_settings[(int)$field_data["ID_FIELD_DEF"]];
            $field_data['B_SEARCHFIELD'] = $field_data['B_SEARCHABLE'] = $field_data['B_SEARCH'];
            $field_current = ($field_current ? array_merge($field_data, $field_current) : $field_data);
            //echo($field_data["ID_FIELD_DEF"]." > ".ht(dump($field_current))."<hr />");
            $field_list[] = $field_current;
        }
    }

    $access = $db->fetch_table($q = "SELECT * FROM `role2kat` WHERE FK_KAT=" . (int)$id_kat);
    foreach ($access as $accessCur) {
        $arAccess[$accessCur['FK_ROLE']] = $accessCur;
    }
    if (is_array($_POST['ALLOW_NEW_AD'])) {
        foreach ($_POST['ALLOW_NEW_AD'] as $roleId => $allowAccess) {
            $arAccess[$roleId] = array("FK_ROLE" => $roleId, "FK_KAT" => $id_kat, "ALLOW_NEW_AD" => (int)$allowAccess);
        }
    }

    //die(ht(dump($field_settings)));
    //die(ht(dump($field_settings)));
    $tpl_content->addlist("liste_felder", $field_list, "tpl/de/m_kat_edit.fieldrow.htm", check_field);

    // Import filter
    $liste_filter = $db->fetch_table($query = "select
				t.*,
				s.V1,
				s.V2,
				s.T1
			from
				`import_filter` t
			left join
				string_app s on s.S_TABLE='import_filter'
				and s.FK=t.ID_IMPORT_FILTER
				and s.BF_LANG=if(t.BF_LANG_APP & " . $langval . ", " . $langval . ", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
			ORDER by
				IDENT ASC");;
    $tpl_content->addlist("liste_filter", $liste_filter, "tpl/de/m_kat_edit.importrow.htm");
} else if ($node_parent = $kat->element_read($id_parent)) {
    // Create
    if ($id_parent != $id_root)
        $tpl_content->addvar("KAT_LOCKED", 1);
    $tables = array_keys($deftable->tables);
    $tmp_table = (isset($_POST["KAT_TABLE"]) ? $_POST["KAT_TABLE"] : $node_parent["KAT_TABLE"]);
    $tmp_table = ($tmp_table ? $tmp_table : $tables[0]);
    if ($tmp_table == "ad_master") {
        $tmp_table = "artikel_master";
    }
    tabledef::getFieldInfo($tmp_table);
    // Access rights
    $access = $db->fetch_table($q = "SELECT * FROM `role2kat` WHERE FK_KAT=" . (int)$id_parent);
    foreach ($access as $accessCur) {
        $arAccess[$accessCur['FK_ROLE']] = $accessCur;
    }
    if (is_array($_POST['ALLOW_NEW_AD'])) {
        foreach ($_POST['ALLOW_NEW_AD'] as $roleId => $allowAccess) {
            $arAccess[$roleId] = array("FK_ROLE" => $roleId, "FK_KAT" => $id_kat, "ALLOW_NEW_AD" => (int)$allowAccess);
        }
    }
    // Field info
    $field_info = $db->fetch_table("
  	SELECT
  		kf.*
  	FROM
  		`kat2field` kf
  	JOIN
  		field_def df on kf.FK_FIELD=df.ID_FIELD_DEF
  	WHERE
  		FK_KAT='" . $id_parent . "'
  	ORDER BY
  		df.F_ORDER");
    $field_settings = array();
    $field_list = array();
    // Auf Feld-ID als Index umschreiben
    foreach ($field_info as $index => $field_data) {
        $field_settings[$field_data["FK_FIELD"]] = $field_data;
    }
    // Und mit voreinstellungen mergen
    foreach (tabledef::$field_info as $index => $field_data) {
        if ((!$field_data['IS_SPECIAL'] || in_array($field_data["F_NAME"], $arSearchFieldsSpecial)) && $field_data['B_ENABLED']) {
            $field_current = ($field_settings[$field_data["ID_FIELD_DEF"]] ? $field_settings[$field_data["ID_FIELD_DEF"]] : array());
            $field_data['B_SEARCHFIELD'] = $field_data['B_SEARCHABLE'] = $field_data['B_SEARCH'];
            $field_list[] = array_merge($field_current, $field_data);
        }
    }
    $tpl_content->addlist("liste_felder", $field_list, "tpl/de/m_kat_edit.fieldrow.htm", check_field);
    unset($node_parent["ID_KAT"]);
    unset($node_parent["PARENT"]);
    unset($node_parent["V1"]);
    unset($node_parent["V2"]);
    $tpl_content->addvar("META", $meta_def); // Add default meta tags
    $tpl_content->addvars($node_parent);
    $tpl_content->addvars(array_flatten(options_apply_defaults($node_parent['OPTIONS'])), 'OPTIONS_');
}

$tpl_content->addvar('FREE_ADS', $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]);
$tpl_content->addvar('GLOBAL_SETTINGS_USE_ARTICLE_LOCATION', $nar_systemsettings["MARKTPLATZ"]["USE_ARTICLE_LOCATION"]);
$tpl_content->addvar('GLOBAL_SETTINGS_USE_ARTICLE_BASEPRICE', $nar_systemsettings["MARKTPLATZ"]["USE_ARTICLE_BASEPRICE"]);
foreach ($nar_roles as $roleId => $roleName) {
    $arAccessEntry = array();
    if (array_key_exists($roleId, $arAccess)) {
        $arAccessEntry = $arAccess[$roleId];
    } else {
        $arAccessEntry = array("FK_ROLE" => $roleId, "FK_KAT" => $id_kat, "ALLOW_NEW_AD" => 0);
    }
    $arAccessEntry["ROLE_NAME"] = $roleName;
    $arAccessList[] = $arAccessEntry;
}
$tpl_content->addlist("access_list", $arAccessList, "tpl/de/m_kat_edit.access.htm");
$tpl_content->addlist("liste_def", $deftable->tables, "tpl/de/m_kat_edit.tablerow.htm", option_checked);

$errormsg = get_messages("KATEGORIEN");
$errors = array();

if (!$kat->tree_lock()) {
    $tpl_content->addvar("IS_LOCKED", 1);
    $errors[] = $errormsg[$kat->error];
}

if (!empty($_POST)) {
    if (!$_POST['B_SALES']) {
        $_POST['B_SALES'] = 0;
    }
    if (!$_POST['B_FREE']) {
        $_POST['B_FREE'] = 0;
    }
    // die(ht(dump($_POST)));
    $tpl_content->vars = array_merge($tpl_content->vars, $_POST);
}

if (isset($_POST["sent"])) {
    if ($_REQUEST["frame"] == "ajax")
        header('Content-type: application/json');

    if ($_POST['B_VIS'] == "") {
        unset($_POST['B_VIS']);
    }
    if (($_POST['KAT_TABLE'] === "") && empty($_POST["ID_KAT"])) {
        $errors[] = "MISSING_TABLE";
    }
    if (empty($_POST['V1']) && empty($_POST["ID_KAT"])) {
        $errors[] = "MISSING_NAME";
    }
    if (!empty($_POST['META'])) {
        // Append meta-tags to T1 field
        $_POST['T1'] .= '||||' . $_POST['META'];
    }
    if (!isset($_POST["MAP_SEARCH_VISIBLE"])) {
        $_POST["MAP_SEARCH_VISIBLE"] = 0;
    }
    if (!isset($_POST["MAP_REGIONS"])) {
        $_POST["MAP_REGIONS"] = 0;
    }
    if (!empty($_REQUEST['ICON_DEL'])) {
        $file_small = $ab_path . substr($node_edit["ICON"], 1);
        $file_large = $ab_path . substr($node_edit["ICON_BIG"], 1);
        @unlink($file_small);
        @unlink($file_large);
        $_POST["ICON"] = null;
        $_POST["ICON_BIG"] = null;
    }
    if (isset($_FILES["ICON"])) {
        // Bild upload
        $uploads_dir = $ab_path . 'uploads/kategorien';
        @mkdir($uploads_dir);
        chmod($uploads_dir, 0777);

        if ($_FILES["ICON"]["error"] == UPLOAD_ERR_OK) {
            $tmp_name = $_FILES["ICON"]["tmp_name"];
            $name = $_FILES["ICON"]["name"];
            $name_array = explode(".", $name);
            $name_ext = $name_array[count($name_array) - 1];

            require_once($ab_path . "sys/lib.image.php");
            $img_icon = new image(13, $uploads_dir, true);
            $img_icon->check_file(array("tmp_name" => $tmp_name, "name" => $name));
            $file_small = $uploads_dir . '/' . $_POST["ID_KAT"] . '.' . $name_ext;
            $file_large = $uploads_dir . '/' . $_POST["ID_KAT"] . '_big.' . $name_ext;
            rename($img_icon->img, $file_large);
            if ($img_icon->thumb !== false) {
                rename($img_icon->thumb, $file_small);
                $_POST["ICON"] = "/" . str_replace($ab_path, "", $file_small);
            } else {
                $_POST["ICON"] = "/" . str_replace($ab_path, "", $file_large);
            }
            $_POST["ICON_BIG"] = "/" . str_replace($ab_path, "", $file_large);
        }
    }
    // Serialized options
    if (is_array($_POST["OPTIONS"])) {
        $_POST["SER_OPTIONS"] = serialize($_POST["OPTIONS"]);
    } else {
        $_POST["SER_OPTIONS"] = options_apply_defaults($_POST["SER_OPTIONS"]);
        $_POST["SER_OPTIONS"] = serialize(array());
    }

    $_POST["MAP_SEARCH_VISIBLE"] = ($_POST["MAP_VISIBLE"] == 2 ? 1 : 0);
    $_POST["MAP_VISIBLE"] = ($_POST["MAP_VISIBLE"] > 0 ? 1 : 0);
    if ($_POST['MAP_REGIONS_RECURSIVE'] == 1) {
        $query = "UPDATE `kat` SET MAP_REGIONS='" . mysql_real_escape_string($_POST["MAP_REGIONS"]) . "' WHERE ROOT=1 AND LFT BETWEEN " . (int)$node_edit["LFT"] . " AND " . (int)$node_edit["RGT"];
        $db->querynow($query);
    }
    if ($_POST['MAP_VISIBLE_RECURSIVE'] == 1) {
        $query = "UPDATE `kat` SET MAP_VISIBLE='" . mysql_real_escape_string($_POST["MAP_VISIBLE"]) . "', MAP_SEARCH_VISIBLE='" . mysql_real_escape_string($_POST["MAP_SEARCH_VISIBLE"]) . "' WHERE ROOT=1 AND LFT BETWEEN " . (int)$node_edit["LFT"] . " AND " . (int)$node_edit["RGT"];
        $db->querynow($query);
    }

    if ($_POST['SEARCH_TYPE_RECURSIVE'] == 1) {
        $query = "UPDATE `kat` SET SEARCH_TYPE='" . (int)$_POST["SEARCH_TYPE"] . "', SEARCH_COLUMNS='" . (int)$_POST["SEARCH_COLUMNS"] . "' WHERE ROOT=1 AND LFT BETWEEN " . (int)$node_edit["LFT"] . " AND " . (int)$node_edit["RGT"];
        $db->querynow($query);
    }

    if (empty($errors)) {
        if ((int)$_POST["ID_KAT"] > 0) {
            $arKatIds = array((int)$_POST["ID_KAT"]);
            if ($_POST['ACCESS_RECURSIVE']) {
                $arKatIds = array_keys($db->fetch_nar("SELECT ID_KAT FROM `kat` WHERE ROOT=1 AND LFT BETWEEN " . (int)$node_edit["LFT"] . " AND " . (int)$node_edit["RGT"]));
            }
            foreach ($arKatIds as $katIndex => $katTargetId) {
                foreach ($nar_roles as $roleId => $roleName) {
                    $value = (int)$_POST['ALLOW_NEW_AD'][$roleId];
                    $query = "INSERT INTO `role2kat` (FK_ROLE, FK_KAT, ALLOW_NEW_AD)" .
                        " VALUES (" . (int)$roleId . ", " . (int)$katTargetId . ", " . $value . ")" .
                        " ON DUPLICATE KEY UPDATE ALLOW_NEW_AD=" . $value;
                    $db->querynow($query);
                }
            }

            if ($_POST["META_RECURSIVE"] == 1) {
                $arKatIds = array_keys($db->fetch_nar("SELECT ID_KAT FROM `kat` WHERE ROOT=1 AND LFT BETWEEN " . (int)$node_edit["LFT"] . " AND " . (int)$node_edit["RGT"]));
                $arQueryRows = array();
                foreach ($arKatIds as $childKatId) {
                    $arQueryRows[] = "\n('kat', " . $childKatId . ", " . $langval . ", '" . mysql_real_escape_string($_POST["T1"]) . "')";
                }
                $query = "INSERT INTO `string_kat` (S_TABLE, FK, BF_LANG, T1) VALUES " . implode(", ", $arQueryRows) . "\n" .
                    "ON DUPLICATE KEY UPDATE T1='" . mysql_real_escape_string($_POST["T1"]) . "'";
                $db->querynow($query);
            }
            if ($_POST["SALE_RECURSIVE"] == 1) {
                $query = "UPDATE `kat` SET B_FREE=" . (int)$_POST["B_FREE"] . ", B_FREE=" . (int)$_POST["B_FREE"] . "\n" .
                    "WHERE LFT BETWEEN " . (int)$node_edit["LFT"] . " AND " . (int)$node_edit["RGT"];
                $db->querynow($query);
            }
            if ($_POST["OPTIONS_RECURSIVE"] == 1) {
                // Apply options recursive to all child categories
                $query = "UPDATE `kat` SET SER_OPTIONS='" . mysql_real_escape_string($_POST["SER_OPTIONS"]) . "' WHERE ROOT=1 AND LFT BETWEEN " . (int)$node_edit["LFT"] . " AND " . (int)$node_edit["RGT"];
                $db->querynow($query);
            } else {
                // Apply options recursive to all child categories
                $query = "UPDATE `kat` SET SER_OPTIONS='" . mysql_real_escape_string($_POST["SER_OPTIONS"]) . "' WHERE ID_KAT=" . $id_kat;
                $db->querynow($query);
            }
            if ($_POST["ICON_RECURSIVE"]) {
                $_POST["ICON"] = (isset($_POST["ICON"]) ? $_POST["ICON"] : $node_edit["ICON"]);
            }
            $recursive = ($_POST["RECURSIVE"] ? true : false);
            $recursiveMeta = false;
            update_fields($_POST["ID_KAT"], $recursive);
            unset($_POST["RECURSIVE"]);
            if ($kat->element_update($id_kat, $_POST, $recursiveMeta)) {
                if ($kat->tree_create_nestedset() && $kat->tree_unlock()) {
                    // Erfolg - Cache neu schreiben
                    include "../sys/lib.pub_kategorien.php";
                    CategoriesBase::deleteCache();

                    // Trigger plugin event
                    $paramCategoryEditEvent = new Api_Entities_EventParamContainer(array(
                        "id"            => $_POST["ID_KAT"],
                        "data"          => array_merge($node_edit, $_POST),
                        "originalData"  => $node_edit
                    ), true);
                    Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_CATEGORY_UPDATED, $paramCategoryEditEvent);

                    // Clear caches
                    require_once $GLOBALS['ab_path']."sys/lib.cache.admin.php";
                    $cacheAdmin = new CacheAdmin();
                    $cacheAdmin->emptyCache("marketplace_categories");
                    $cacheAdmin->emptyCache("subtpl_ads_search");
                    
                    if ($_REQUEST["frame"] == "ajax")
                        die(json_encode(array("state" => "200", "reload" => $kat->reload)));
                    die(forward("index.php?page=m_kats&id=" . $id_kat . "#row" . $id_kat));
                } else {
                    $errors[] = $errormsg[$kat->error];
                    $kat->tree_unlock();
                    if ($_REQUEST["frame"] == "ajax")
                        die(json_encode(array("state" => "450", "error" => $errormsg[$kat->error], "reload" => $kat->reload)));
                }
            } else {
                if ($_REQUEST["frame"] == "ajax")
                    die(json_encode(array("state" => "450", "error" => $errormsg[$kat->error], "reload" => $kat->reload)));
                $kat->tree_unlock();
                $errors[] = $errormsg[$kat->error];
                $tpl_content->addvars($_POST);
                if ($_REQUEST["frame"] == "ajax")
                    die(json_encode(array("state" => "450", "error" => $errormsg[$kat->error], "reload" => $kat->reload)));
            }
        } else if ($kat->element_create($id_parent, $_POST)) {
            // Access rights
            foreach ($nar_roles as $roleId => $roleName) {
                $value = (int)$_POST['ALLOW_NEW_AD'][$roleId];
                $query = "INSERT INTO `role2kat` (FK_ROLE, FK_KAT, ALLOW_NEW_AD)" .
                    " VALUES (" . (int)$roleId . ", " . (int)$kat->updateid . ", " . $value . ")" .
                    " ON DUPLICATE KEY UPDATE ALLOW_NEW_AD=" . $value;
                $db->querynow($query);
            }
            // Fields
            update_fields($kat->updateid);
            if ($kat->tree_create_nestedset() && $kat->tree_unlock()) {
                // Erfolg - Cache neu schreiben
                include "../sys/lib.pub_kategorien.php";
                CategoriesBase::deleteCache();
                // Trigger plugin event
                $paramCategoryEditEvent = new Api_Entities_EventParamContainer(array(
                    "id"            => $kat->updateid,
                    "data"          => $kat->element_read($kat->updateid),
                    "originalData"  => array()
                ), true);
                Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_CATEGORY_UPDATED, $paramCategoryEditEvent);

                if ($_REQUEST["frame"] == "ajax")
                    die(json_encode(array("state" => "200", "reload" => $kat->reload)));
                die(forward("index.php?page=m_kats&id=" . $kat->updateid . "#row" . $kat->updateid));
            } else {
                $errors[] = $errormsg[$kat->error];
                $kat->tree_unlock();
                if ($_REQUEST["frame"] == "ajax")
                    die(json_encode(array("state" => "450", "error" => $errormsg[$kat->error], "reload" => $kat->reload)));
            }
        } else {
            $kat->tree_unlock();
            $errors[] = $errormsg[$kat->error];
            $tpl_content->addvars($_POST);
            if ($_REQUEST["frame"] == "ajax")
                die(json_encode(array("state" => "450", "error" => $errormsg[$kat->error], "reload" => $kat->reload)));
        }
    }
} // POST

if (!empty($errors)) {
    $tpl_content->addvar("errors", implode(",", $errors));
}

// Trigger plugin event
$paramCategoryEditEvent = new Api_Entities_EventParamContainer(array(
    "id" => $id_kat,
    "parent" => $id_parent,
    "root" => $id_root,
    "template" => $tpl_content,
    "pluginHtml" => array()
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_CATEGORY_EDIT_TEMPLATE, $paramCategoryEditEvent);
if ($paramCategoryEditEvent->isDirty()) {
    $tpl_content->addvar("pluginHtml", $paramCategoryEditEvent->getParam("pluginHtml"));
}
?>
