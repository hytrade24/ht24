<?php
/* ###VERSIONSBLOCKINLCUDE### */

/**
 * Liest die neusten Anzeigen aus.
 * 
 * BEISPIEL:    {subtpl(tpl/{SYS_TPL_LANG}/ads_new.htm,ID_KAT=42,COUNT=8,COUNT_PER_ROW=4,HIDE_PARENT=1)}
 * PARAMETER:
 *  ID_KAT          - (optional) Gibt an in aus welcher Kategorie die Anzeigen stammen sollen.
 *                      Standard: Die Anzeigen aus allen Kategorien ausgelesen.
 *  CACHE_LIFETIME  - (optional) Zeit in Minuten nach der die Anzeigen neu gecached werden. (0 zum deaktivieren des Cache)
 *                      Standard: Die Anzeigen werden alle 60 Minuten neu gecached.
 *  COUNT           - (optional) Anzahl der auszulesenden Anzeigen.
 *                      Standard: 4 Anzeigen
 *  COUNT_PER_ROW   - (optional) Gibt an wie viele Anzeigen pro Zeile angezeigt werden sollen.
 *                      Standard: Es werden alle Anzeigen in einer Zeile ausgegeben.
 *  EXCLUDE_ADS     - (optional) Eine Liste an Anzeigen-Ids die ausgeschlossen werden sollen.
 *                      Standard: Es werden keine Anzeigen ausgeschlossen.
 *  ONLY_TOP        - (optional) Wenn gesetzt werden nur Anzeigen mit dem entsprechenden Top-Flag dargestellt
 *                      Flags: 1 = Immer oben, 2 = Darstellung im Slider, 4 = Farbiges Highlight, 8 = Eigen
 *                      Standard: Es werden alle Anzeigen unabhnägig des Top-Status ausgelesen.
 *  HIDE_PARENT     - (optional) Wenn gesetzt wird das übergeordnete HTML-Element ausgeblendet falls keine Anzeigen gefunden wurden.
 *                      Standard: Das übergeordnete HTML-Element bleibt unverändert.
 *                      
 */

global $nar_systemsettings;

include_once $ab_path."sys/lib.shop_kategorien.php";

$tpl_content->addvar("USE_HERSTELLER", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED']);

$kat = new TreeCategories("kat", 1);
$id_kat_root = $kat->tree_get_parent();

// Einstellungen auslesen
$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "ads_new", "Marktplatz-Anzeigen (Neuste zuerst)");
$id_kat = $subtplConfig->addOptionText("ID_KAT", "Kategorie-ID", false, "{ID_KAT}");
$id_kat = ($id_kat > 0 ? $id_kat : $id_kat_root);
$id_geo_region = $subtplConfig->addOptionText("ID_GEO_REGION", "Region (ID)", false, "{ID_GEO_REGION}");
$id_man = $subtplConfig->addOptionText("ID_MAN", "Hersteller (ID)", false, false);
$cacheLifetime = $subtplConfig->addOptionLookup("CACHE_LIFETIME", "Cache gültig für", "CACHE_LIFE", false);
$maxAds = $subtplConfig->addOptionIntRange("COUNT", "Anzahl Anzeigen", false, 4, 1, 16);
$offset = $subtplConfig->addOptionIntRange("OFFSET", "Start", false, 0, 0, 100);
$maxAdsPerRow = $subtplConfig->addOptionIntRange("COUNT_PER_ROW", "Anzahl pro Zeile", false, 4, 1, 12);
$maxAdsPerRowResponsive = $subtplConfig->addOptionText("COUNT_PER_ROW_RESPONSIVE", "Responsive", false, "xs-1 sm-2");
$onlyTop = $subtplConfig->addOptionCheckboxList("ONLY_TOP", "Nur Top-Anzeigen", false, array(
    1 => "Immer oben",
    2 => "Darstellung im Slider",
    4 => "Farbiges Highlight",
    8 => "Eigen"
));
$arExcluded = array();
$strExcludedAds = $subtplConfig->addOptionHidden("EXCLUDE_ADS", "Anzeigen ignorieren (IDs)", "");
if (!empty($strExcludedAds) && preg_match_all("/([0-9]+)/i", $strExcludedAds, $arMatches)) {
    $arExcludeAdsRaw = $arMatches[1];
    foreach ($arExcludeAdsRaw as $excludeIndex => $excludeAdId) {
        $arExcluded[] = (int)$excludeAdId;
    }
}
$template = $subtplConfig->addOptionSelectList("TEMPLATE", "Darstellung", 'row_box', array(
    'row_box'			      => "Box-Darstellung",
    'row_box_noprice'   => "Box-Darstellung (Ohne Preis)",
    'row'	    		      => "Listen-Darstellung",
    'row_simple'	      => "Listen-Darstellung (Vereinfacht)"
));
$hideParent = $subtplConfig->addOptionHidden("HIDE_PARENT", "Eltern-Element ausblenden wenn leer", false);
$subtplConfig->finishOptions();

$arSettings = array(
    "LANG"                      => $s_lang,
    "ID_KAT" 		                => $id_kat,
    "ID_GEO_REGION"             => $id_geo_region,
    "ID_MAN"                    => $id_man,
    "COUNT"	                    => $maxAds,
    "OFFSET"                    => $offset,
    "COUNT_PER_ROW"	            => $maxAdsPerRow,
    "COUNT_PER_ROW_RESPONSIVE"	=> $maxAdsPerRowResponsive,
    "EXCLUDE_ADS"               => $arExcluded,
    "HIDE_PARENT"	              => $hideParent,
    "ONLY_TOP"	                => $onlyTop,
    "SPAN_WIDTH"                => ($maxAdsPerRow > 12 ? 1 : round(12 / $maxAdsPerRow)),
    "TEMPLATE"                  => $template
);
// Cache-Datei prüfen
$cacheHash = md5( serialize($arSettings) );
$cacheFile = $ab_path."cache/marktplatz/newest/".$cacheHash.".htm";
$cacheFileAge = (file_exists($cacheFile) && !$_SESSION["USER_IS_ADMIN"] ? (time() - filemtime($cacheFile)) / 60 : false );
$cacheContent = "";
if (($cacheLifetime <= 0) || ($cacheFileAge === false) || ($cacheFileAge > $cacheLifetime)) {
    /*
     * Cache (neu) erzeugen
     */
    require_once $ab_path."sys/lib.ad_constraint.php";
    
    // Change base template?
    $tplNameBase = trim(str_replace("row", "", $template), "_.");
    if (!empty($tplNameBase)) {
        $tplFileBase = CacheTemplate::getHeadFile("tpl/".$s_lang."/ads_new.".$tplNameBase.".htm");
        if (file_exists($tplFileBase)) {
            $tpl_content->LoadText($tplFileBase);
        }
    }
    
    $arQueryOptions = array("NO_GROUPING" => true);
    $tpl_content->addvars($arSettings);
    
    // Category
    if ($id_kat > 0) {
        $arQueryOptions["FK_KAT"] = $id_kat;
    }
    // Region
    if ($id_geo_region > 0) {
        /** @var Api_Plugins_GeoRegion_Plugin $pluginGeo */
        $pluginGeo = Api_TraderApiHandler::getInstance()->getPlugin("GeoRegion");
        if ($pluginGeo !== false) {
            // Get all child regions
            $idGeoRegions = $pluginGeo->getChildRegions((int)$id_geo_region);
            $idGeoRegions[] = $id_geo_region;
            $ids_regions = "(" . implode(",", $idGeoRegions) . ")";
            // Add where clause
            $arQueryOptions["FK_GEO_REGION"] = $ids_regions;
        }
    }
    // Hersteller
    if ($id_man > 0) {
        $arQueryOptions["FK_MAN"] = $id_man;
    }
    // Exclude ads
    if (!empty($arExcluded)) {
        $arExcudedEscaped = array();
        foreach ($arExcluded as $excludedIndex => $excludedId) {
            $arExcudedEscaped[] = (int)$excludedId;
        }
        $arQueryOptions["ID_AD_MASTER_NOT_IN"] = "(".implode(", ", $arExcudedEscaped).")";
    }
    // Only top articles?
    if ($arSettings["ONLY_TOP"] > 0) {
        // Optimierung zwecks besserer Nutzung des Datenbank-Index
        $arTopStates = array();
        for ($i = 0; $i < 16; $i++) {
            if (($i & $arSettings["ONLY_TOP"]) > 0) {
                $arTopStates[] = $i; 
            }
        }
        // Bedingung hinzufügen
        $arQueryOptions["TOP_IN"] = "(".implode(", ", $arTopStates).")";
    }
    
    // Query ads
    $queryAds = Rest_MarketplaceAds::getQueryByParams($arQueryOptions);
    $tableMaster = $queryAds->getDataTable()->getTableIdent();
	$queryAds->addField("VENDOR_NAME");
	$queryAds->addField("VENDOR_LOGO");
    $queryAds->addSortFields(array(
   		$tableMaster.".STAMP_START"	=> "DESC",
   		"ID_AD_MASTER"				=> "DESC"
   	));
    $queryAds->setLimit($maxAds, $offset);
    
    Rest_MarketplaceAds::addQueryFieldsByTemplate($queryAds, "ads_new.".$template.".htm");
	$templateRowFile = "tpl/".$s_lang."/ads_new.".$template.".htm";
    
	// Plugin event
	$eventMarketListParams = new Api_Entities_EventParamContainer(array(
		"language"						=> $s_lang,
		"idCategory"					=> $id_kat,
		"table"							=> $queryAds->getDataTable()->getTable(),
		"template"						=> $tpl_content,
		"templateRow"					=> $templateRowFile,
		"groupByProduct"                => true,
		"searchActive"					=> false,
		"searchHash"					=> null,
		"searchData"					=> $arQueryOptions,
		"query"							=> $queryAds,
		"queryMasterPrefix"				=> $tableMaster
	));
	Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_LIST_QUERY, $eventMarketListParams);
	if ($eventMarketListParams->isDirty()) {
		$templateRowFile = $eventMarketListParams->getParam("templateRow");
		$queryAds = $eventMarketListParams->getParam("query");
	}    
    
    #$queryAds = new Api_DataTableQueryIntermediate($db, $queryAds, "ID_AD");
    $ads = $queryAds->fetchTable();
    #die($queryAds->getQueryString());
    
    Rest_MarketplaceAds::extendAdDetailsList($ads);
    
    // Output result
    // - Per row value
    $arAdsPerRow = array("xs" => $maxAdsPerRow, "sm" => $maxAdsPerRow, "md" => $maxAdsPerRow, "lg" => $maxAdsPerRow);
    $arAdsPerRowRaw = explode(" ", $maxAdsPerRowResponsive);
    foreach ($arAdsPerRowRaw as $adsPerRowIndex => $adsPerRowValue) {
        if (preg_match("/^(xs|sm|md|lg)\-?([0-9]+)$/i", $adsPerRowValue, $arMatch)) {
            $arAdsPerRow[$arMatch[1]] = (int)$arMatch[2];
        }
    }
    $tpl_content->addvars($arAdsPerRow, "COUNT_PER_ROW_");
    // - More template variables
    $tpl_content->addvar("noads", 1);
    $tpl_content->addvar("liste_count", count($ads));
    $tpl_content->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
    $tpl_content->addvar("DAYS_ADS_NEW", $nar_systemsettings["MARKTPLATZ"]["DAYS_ADS_NEW"]);
    $tpl_content->isTemplateRecursiveParsable = FALSE;
    $tpl_content->isTemplateCached = TRUE;
    $tpl_content->addlist("liste", $ads, $templateRowFile);
    $tpl_content->isTemplateRecursiveParsable = TRUE;
    $tpl_content->isTemplateCached = FALSE;
    // Write cache
    $cacheContent = $tpl_content->process(true);
    if(!$_SESSION["USER_IS_ADMIN"]) {
        if ($cacheLifetime > 0) {
            if (!is_dir($ab_path."cache/marktplatz/newest")) {
                // Cache Verzeichnis erstellen
                mkdir($ab_path."cache/marktplatz/newest", 0777, true);
            }
            // Cache ist aktiviert, Cachedatei (neu) scheiben
            file_put_contents($cacheFile, $cacheContent);
        } else if (file_exists($cacheFile)) {
            // Cache ist deaktiviert, Cachedatei löschen
            unlink($cacheFile);
        }
    }
} else {
    /*
     * Cache noch gültig, aus dem Cache lesen
     */ 
    $cacheContent = file_get_contents($cacheFile);
}

$tpl_content->tpl_text = $cacheContent;
$tpl_content->addvars($user, "CURUSER_");

?>
