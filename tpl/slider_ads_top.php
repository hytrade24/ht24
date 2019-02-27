<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $nar_systemsettings;

require_once $ab_path."sys/lib.ad_constraint.php";
include_once $ab_path."sys/lib.nestedsets.php";
include_once $ab_path."sys/lib.shop_kategorien.php";
include_once $ab_path."sys/lib.ads.php";
include_once $ab_path."sys/lib.ad_agent.php";

$tpl_content->addvar("USE_HERSTELLER", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED']);

$kat = new TreeCategories("kat", 1);
$id_kat_root = $kat->tree_get_parent();

$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "slider_ads_top", "Top-Anzeigen Slider");
$id_kat = $subtplConfig->addOptionInt("ID_KAT", "Kategorie-ID", false, "{ID_KAT}");
$id_kat = ($id_kat > 0 ? $id_kat : $id_kat_root);
$maxPerPage = $subtplConfig->addOptionIntRange("MAX_PER_PAGE", "Anzeigen pro Seite", false, 4, 1, 16);
$maxNumPages = $subtplConfig->addOptionIntRange("MAX_PAGES", "Anzahl Seiten", false, 4, 1, 12);
$maxOverall = $maxNumPages * $maxPerPage;
$interval = $subtplConfig->addOptionIntRange("INTERVAL", "Seite wechseln nach (ms)", false, 5000, 500, 20000);
$hideIndicator = $subtplConfig->addOptionCheckbox("HIDE_INDICATOR", "Slider-Navigation verstecken", false);
$hideButtons = $subtplConfig->addOptionHidden("HIDE_BUTTONS", "Slider-Buttons verstecken", false, true);
$subtplConfig->finishOptions();

$tpl_content->addvars(array(
	"LANG"          	=> $s_lang,
	"ID_KAT" 			=> $id_kat,
	"MAX_PER_PAGE"		=> $maxPerPage,
	"MAX_PAGES"			=> $maxNumPages,
    "SPAN_WIDTH"    	=> ($maxPerPage > 12 ? 12 : round(12 / $maxPerPage)),
	"MAX_OVERALL"		=> $maxOverall,
	"INTERVAL"			=> $interval,
	"HIDE_INDICATOR"	=> $hideIndicator,
	"HIDE_BUTTONS"		=> $hideButtons
));

$topCondition = ($nar_systemsettings["MARKTPLATZ"]["EXTENDED_TOP_ADS"] ? "(2, 3, 6, 7, 10, 11, 14, 15)" : true);

// Query erzeugen
$queryTopIds = Ad_Marketplace::getQueryByParams(array("FK_KAT" => $id_kat));
if ($nar_systemsettings["MARKTPLATZ"]["EXTENDED_TOP_ADS"]) {
	$queryTopIds->addWhereCondition("TOP_IN", "(2, 3, 6, 7, 10, 11, 14, 15)");
} else {
	$queryTopIds->addWhereCondition("TOP");
}
// Benötigte Felder selektieren
$queryTopIds->addField("ID_AD_MASTER");
// Anzeigen auslesen
$adsIds = $queryTopIds->fetchCol();

// Prepare result
$ads = array();
if (!empty($adsIds)) {
	$queryTopAds = Ad_Marketplace::getQueryByParams(array("ID_AD_MASTER_IN" => "(".implode(", ", $adsIds).")"));
	$queryTopAds->addSortField("RANDOM", "ASC");
	$queryTopAds->setLimit($maxOverall);
	Ad_Marketplace::addQueryFieldsByTemplate($queryTopAds, "slider_ads_top.row.htm");
	$ads = $queryTopAds->fetchTable();
	Rest_MarketplaceAds::extendAdDetailsList($ads);
	#die(var_dump($queryTopAds->getQueryString(), $ads));
}
$ads_pages = array( array() );

if (count($ads) >= $maxPerPage) {
	// Fill the last page (if more than one page)
	$indexRepeat = 0;
	while ((count($ads) % $maxPerPage) > 0) {
		$ads[] = $ads[$indexRepeat++];
	}
	for ($i = 1; $i < ceil( count($ads) / $maxPerPage ); $i++) {
		$ads_pages[] = array();
	}
}

// Output result
$tpl_content->isTemplateRecursiveParsable = TRUE;
$tpl_content->isTemplateCached = TRUE;
$tpl_content->addvar("liste_count", count($ads));
$tpl_content->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
$tpl_content->addlist("liste", $ads, "tpl/".$s_lang."/slider_ads_top.row.htm");
$tpl_content->addlist("liste_indicator", $ads_pages, "tpl/".$s_lang."/slider_ads_top.row_indicator.htm");

?>