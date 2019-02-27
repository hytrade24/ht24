<?php
/* ###VERSIONSBLOCKINLCUDE### */

$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "marktplatz.categories", "Kategorie-Übersicht");
$showAdCountCategory = $subtplConfig->addOptionCheckbox("SHOW_AD_COUNT_KAT", "Anzahl Anzeigen darstellen (pro Kategorie)", false, true);
$hideEmpty = $subtplConfig->addOptionCheckbox("HIDE_EMPTY", "Leere Kategorien ausblenden", false);
$fkInfoseite = $subtplConfig->addOptionHidden("FK_INFOSEITE", "Infobereich (ID)", false, "{FK_INFOSEITE}");
$subtplConfig->finishOptions();

if (!function_exists("lists_add_kat")) {
	function lists_add_kat($kat_data, $kat_rows, &$ar_list, $kats_per_row, $list_cols = 3, $depth = 0, $max_level = 1, $list_target = -1)
	{
		global $kat;
		/*
		 if ($list_target == -1) {
		 $list_min_count = -1;
		 for ($list = 1; $list <= $list_cols; $list++) {
		 if (($list_min_count < 0) || (count($lists[$list]) < $list_min_count)) {
		 $list_min_count = count($lists[$list]);
		 $list_target = $list;
		 }
		 }
		 }
		 */
		$childs = $kat->element_get_childs($kat_data["ID_KAT"]);

		for ($list = 1; $list <= $list_cols; $list++) {
			if ((count($ar_list) + count($childs)) <= $kats_per_row) {
				$list_target = $list;
				break;
			}
		}

		$kat_data["CHILDS"] = count($childs);
		$kat_data["DEPTH"] = $depth;

		$ar_list[] = $kat_data;
		foreach ($childs as $index => $child) {
			if ($max_level > $depth) {
				lists_add_kat($child, $kat_rows, $ar_list, $kats_per_row, $list_cols, $depth + 1, $max_level, $list_target);
			}
		}
	}
}

global $id_kat;

require_once $ab_path.'sys/lib.ad_constraint.php';

// Einstellungen
$tpl_content->addvar("USE_HERSTELLER", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED']);

// Kategorie
include_once "sys/lib.shop_kategorien.php";
$kat = new TreeCategories("kat", 1);
$id_kat_root = $kat->tree_get_parent();
$id_kat = ($ar_params[1] ? $ar_params[1] : $id_kat_root);
$row_kat = $kat->element_read($id_kat);
if (empty($row_kat)) {
	header("HTTP/1.0 404 Not Found");
	$tpl_content->addvar("not_found", 1);
	return;
} else {
	$tpl_content->addvars($row_kat, "KAT_");
}
$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
$tpl_content->addvar("ID_KAT", $id_kat);
// Kategorie - Title/Meta
if (!empty($row_kat['V2'])) {
	$tpl_main->vars['pagetitle'] = $row_kat['V2']." - ".$tpl_main->vars['pagetitle'];
}
if (!empty($row_kat['META'])) {
	$tpl_main->vars['metatags'] = $row_kat['META'];
}

/*
 * KATEGORIE ÜBERSICHT
 */
$cacheFile = $ab_path . "cache/marktplatz/kat_" . $s_lang . "." . $id_kat . ".htm";
$cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
$modifyTime = @filemtime($cacheFile); 
$diff = ((time() - $modifyTime) / 60);
if (($diff > $cacheFileLifeTime) || !file_exists($cacheFile)) {
	$tplCategoriesContent = new Template("tpl/".$s_lang."/marktplatz.categories.content.htm");
	$tplCategoriesContent->isTemplateRecursiveParsable = false;
	$tplCategoriesContent->isTemplateCached = true;
	// Kategorie-Ansicht cachen
	$list_kats = $db->fetch_table("
		SELECT
			k.ID_KAT, k.PARENT, k.LFT, k.RGT, k.ROOT, k.KAT_TABLE, FLOOR((k.RGT - k.LFT) / 2) as COUNT, k.LEVEL
		FROM `kat` k
		WHERE (k.PARENT = " . (int)$id_kat . ") AND (k.ROOT = " . $row_kat["ROOT"] . ") AND (k.B_VIS = 1)
		ORDER BY k.LFT ASC");
	$kat_count = $db->fetch_atom("
		SELECT count(*) FROM `kat` k
		WHERE (k.LFT > " . $row_kat["LFT"] . ") AND (k.RGT < " . $row_kat["RGT"] . ")
			AND (k.ROOT = " . $row_kat["ROOT"] . ") AND (k.LEVEL <= " . ($row_kat["LEVEL"] + 2) . ") AND (k.B_VIS = 1)");
	$kat_lists = array();
	// Ab 15 Kategorien 2 Spalten, ab 30 Kats 3 Spalten.
	$kat_lists_count = ($kat_count > 30 ? 3 : ($kat_count > 15 ? 2 : 1));
	$kats_per_row = $kat_count / $kat_lists_count;
	for ($list_index = 1; $list_index <= $kat_lists_count; $list_index++) {
		$kat_lists[$list_index] = array();
	}
	$list_index = 1;
	foreach ($list_kats as $index => $kat_child) {
		if ($kat_child["PARENT"] == $id_kat) {
			lists_add_kat($kat->element_read($kat_child["ID_KAT"]), $kat_child["COUNT"], $kat_lists[$list_index], $kats_per_row, $kat_lists_count);
		}
		if (count($kat_lists[$list_index]) >= $kats_per_row) {
			$list_index++;
		}
	}
	for ($list_index = 1; $list_index <= $kat_lists_count; $list_index++) {
		$tplCategoriesContent->addlist("kat_liste_" . $list_index, $kat_lists[$list_index], "tpl/" . $s_lang . "/marktplatz.row_kat.htm");
	}
	$html_kat = $tplCategoriesContent->process(true);
	file_put_contents($cacheFile, $html_kat);
	chmod($cacheFile, 0777);
	$tpl_content->addvar("CATEGORIES_CONTENT", $html_kat);
} else {
	$tpl_content->addvar("CATEGORIES_CONTENT", file_get_contents($cacheFile));
}

$tpl_content->addvar("SHOW_AD_COUNT_KAT", $showAdCountCategory);
$tpl_content->addvar("HIDE_EMPTY", $hideEmpty);
$tpl_content->isTemplateRecursiveParsable = true;

?>
