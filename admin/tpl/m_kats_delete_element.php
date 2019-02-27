<?php
/* ###VERSIONSBLOCKINLCUDE### */



function add_childs(&$ar_categories, $kat, $id_kat) {
	$ar_childs = $kat->element_get_childs($id_kat);
	foreach ($ar_childs as $index => $ar_kat) {
		$ar_categories[] = $ar_kat["ID_KAT"];
		add_childs($ar_categories, $kat, $ar_kat["ID_KAT"]);
	}
}

include $ab_path."sys/lib.ads.php";
include "sys/lib.shop_kategorien.php";

header('Content-type: application/json');

$SILENCE = false;

$id_cat = ($_REQUEST["ID_CAT"] ? $_REQUEST["ID_CAT"] : -1);
$inherit_childs = ($_REQUEST["LEAVE_CHILDS"] ? false : true);
$errors = get_messages("KATEGORIEN");

$kat = new TreeCategories("kat", 1);
if (!$kat->tree_lock_valid()) {
	if (!$kat->tree_lock())
	die(json_encode(array("state" => "450", "error" => $errors[$kat->error], "reload" => $kat->reload)));
}
if ($id_cat > -1) {
    // Merken, welche Kategorien gelöscht werden
    $ar_categories = array($id_cat);
    if ($inherit_childs) {
        add_childs($ar_categories, $kat, $id_cat);
    }
    switch ($_REQUEST['do']) {
        default:
        case 'confirm':
            $arCategory = $kat->element_read($id_cat);
            $countArticles = $db->fetch_atom("SELECT count(*) FROM `ad_master` WHERE FK_KAT IN (".implode(", ", $ar_categories).")");
            $tpl_content->addvar("ID_CAT", $id_cat);
            $tpl_content->addvars($arCategory, "CAT_");
            $tpl_content->addvar("ARTICLES", $countArticles);
            $tpl_content->addvar("HAS_CHILDS", $_REQUEST["childs"]);
            die($tpl_content->process());
            die("test: ".$countArticles);
            break;
        case 'delete':
            ### Löschen ###
            // Cache leeren
            require_once $ab_path."sys/lib.pub_kategorien.php";
            CategoriesBase::deleteCacheRecursive($id_cat);
            if (!$kat->element_delete($id_cat, $inherit_childs) || !$kat->tree_create_nestedset()) {
                die(json_encode(array("state" => "450", "error" => $errors[$kat->error], "reload" => $kat->reload)));
            } else {
                // Erfolg
                # Abhängigkeiten löschen
                include $ab_path."sys/event.category.php";
                foreach ($ar_categories as $id_kat_deleted) {
                    EventCategory::onDelete($id_kat_deleted);
                }
            }
            break;
    }
}
if (!$kat->tree_unlock()) {
	die(json_encode(array("state" => "450", "error" => $errors[$kat->error], "reload" => $kat->reload)));
}
die(json_encode(array("state" => "200", "reload" => $kat->reload)));

?>
