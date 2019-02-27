<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 7.4.8.1
 */

$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "kat_left_2", "Kategorien Seitenleiste");
$showAdCountCategory = $subtplConfig->addOptionCheckbox("SHOW_AD_COUNT_KAT", "Anzahl Anzeigen darstellen (pro Kategorie)", false, true);
$showAdCount = $subtplConfig->addOptionCheckbox("SHOW_AD_COUNT", "Anzahl Anzeigen darstellen (Gesamt)", false, true);
$hideEmpty = $subtplConfig->addOptionCheckbox("HIDE_EMPTY", "Leere Kategorien ausblenden", false);
$childrenHover = $subtplConfig->addOptionCheckbox("CHILDREN_HOVER", "Unterkategorien als Menü", false);
/*
$template = $subtplConfig->addOptionSelectList("TEMPLATE", "Darstellung", 'default', array(
    'panel'	=> "Darstellung als Panel (Regulär)",
    'embed' => "Darstellung als aufklappbares Menü"
));
*/
$template = $subtplConfig->addOptionHidden("TEMPLATE", "Darstellung", false, "panel");
$embedOpen = 0;
if ($template == 'embed') {
	$embedOpen = $subtplConfig->addOptionHidden("EMBED_OPEN", "Menü immer aufgeklappt", false, false);
}
$tpl_content->addvar("EMBED_OPEN", (int)$embedOpen);
$subtplConfig->finishOptions();

$templateFilename = "tpl/".$s_lang."/kat_left_2.".$template.".htm";
if (file_exists($ab_path."cache/design/".$templateFilename)) {
	$tpl_content->LoadText($templateFilename);
}

$tpl_content->isTemplateRecursiveParsable = true;
$tpl_content->isTemplateCached = true;
$tpl_content->addvar("SHOW_AD_COUNT_KAT", $showAdCountCategory);
$tpl_content->addvar("SHOW_AD_COUNT", $showAdCount);
$tpl_content->addvar("CHILDREN_HOVER", $childrenHover);

include_once "sys/lib.nestedsets.php";
include_once "sys/lib.shop_kategorien.php";

$kat = new TreeCategories("kat", 1);
$id_kat = $kat->tree_get_parent();
if (($ar_params[1] > 0) && (in_array($tpl_content->vars['curpage'], array("marktplatz")))) {
	$id_kat = $ar_params[1];
}
$overrideKatUrl = NULL;

$settings_product_db = $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB'];

$tpl_content->addvar("USE_PRODUCT_DB", $settings_product_db);
$tpl_content->addvar("FK_KAT", $id_kat);
/*
if($ar_params[0] == "alle-anzeigen") {
    $id_kat = $kat->tree_get_parent();
    $ar_params[1] = $kat->tree_get_parent();
}
*/
if($ar_params[1]) {
	$tpl_content->addvar("IS_KAT", 1);
	$ar_kat = $db->fetch1("
  		select
  			t.*, s.V1, s.V2
  		from `kat` t
  			left join string_kat s on s.S_TABLE='kat'
  			and s.FK=t.ID_KAT
  			and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
  		where
  			ID_KAT=".(int)$ar_params[1]);
	$tpl_content->addvar("SEARCH_TYPE", $ar_kat["SEARCH_TYPE"]);
	$tpl_content->addvar("KATNAME", $ar_kat['V1']);

	$katOptions = unserialize($ar_kat['SER_OPTIONS']);
	if($katOptions != false) {
		$tpl_content->addvars(unserialize($ar_kat['SER_OPTIONS']), "OPTIONS_");
	}
}

if ($ar_params[2] == "Suchergebniss") {
	// Suche darstellen
	$search_hash = $ar_params[3];
	$overrideKatUrl = $ar_params[2].','.$search_hash;

	if (!empty($search_hash)) {
		$search_data = unserialize($db->fetch_atom("SELECT
	          S_STRING
	        FROM `searchstring`
	        WHERE `QUERY`='".mysql_escape_string($search_hash)."'"));
		if (is_array($search_data)) {
			$tpl_content->addvars($search_data);
			$tpl_content->addvar("SEARCH_HASH", $search_hash);
			$tpl_content->addvar("IS_SEARCH_RESULT", 1);
		} else {
			if (is_array($ar_kat)) {
				die(forward(
					$tpl_content->tpl_uri_action("marktplatz,".$ar_kat["ID_KAT"].",".urlencode($ar_kat["V1"]))
				));
			} else {
				die(forward(
					$tpl_content->tpl_uri_action("marktplatz")
				));
			}
		}
	}

} else {
	$tpl_content->addvar("FK_COUNTRY", '1');
}

include_once "sys/lib.pub_kategorien.php";
$kat_cache = new CategoriesCache();
$cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];

if($id_kat == 1) {
	$childDeepLevel = 1;
	$cachefile = "cache/marktplatz/tree_".$s_lang.".".$id_kat.".".$childDeepLevel.".htm";

	$modifyTime = @filemtime($cachefile);
	$diff = ((time()-$modifyTime)/60);

	if (($diff > $cacheFileLifeTime) || !file_exists($cachefile)) {
		$kat_cache->cacheKatTree($id_kat, $childDeepLevel, $childrenHover);
	}
	$tpl_content->addvar('SPECIAL_MENU', $childrenHover);
	$tpl_content->addvar('ARTICLE_COUNT', $kat_cache->getCacheArticleCount($id_kat));
} else {
	$cachefile = "cache/marktplatz/liste_".$s_lang.".".$id_kat.".htm";

	$modifyTime = @filemtime($cachefile);
	$diff = ((time()-$modifyTime)/60);

	$tpl_content->addvar('SPECIAL_MENU', 1);
	if (($diff > $cacheFileLifeTime) || !file_exists($cachefile)) {
		$kat_cache->cacheKatList($id_kat, $childrenHover);
	}
}

$tpl_kats = new Template("tpl/".$s_lang."/empty");
$tpl_kats->tpl_text = @file_get_contents($cachefile);
$tpl_kats->isTemplateRecursiveParsable = TRUE;
$tpl_kats->isTemplateCached = TRUE;
if($overrideKatUrl !== NULL) {
	$tpl_kats->addvar("KAT_LEFT_2_SET_FILTER_KAT", TRUE);
}
$tpl_kats->addvar("ID_KAT_SEL", $ar_params[1]);
$tpl_kats->addvar("SHOW_AD_COUNT_KAT", $showAdCountCategory);
$tpl_kats->addvar("HIDE_EMPTY", $hideEmpty);

$tpl_content->addvar("kats", $tpl_kats->process());
$tpl_content->addvar("ID_KAT", $ar_params[1]);
$tpl_content->addvar("ID_USER", $uid);

$tpl_content->addvar("SETTINGS_MARKTPLATZ_USE_ARTICLE_LOCATION", $nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_LOCATION']);


?>