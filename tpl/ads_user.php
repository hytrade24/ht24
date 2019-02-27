<?php
/* ###VERSIONSBLOCKINLCUDE### */

/**
 * Liest die neusten Anzeigen eines Benutzers aus.
 * 
 * BEISPIEL:    {subtpl(tpl/{SYS_TPL_LANG}/ads_user.htm,ID_USER=1,COUNT=8,COUNT_PER_ROW=4,HIDE_PARENT=1)}
 * PARAMETER:
 *  ID_USER         - Gibt an wessen Anzeigen ausgelesen werden sollen. (user id)
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
$id_user = (int)$tpl_content->vars['ID_USER'];
$id_kat = (array_key_exists('ID_KAT', $tpl_content->vars) ? (int)$tpl_content->vars['ID_KAT'] : 0);
$cacheLifetime = (array_key_exists('CACHE_LIFETIME', $tpl_content->vars) ? (int)$tpl_content->vars['CACHE_LIFETIME'] : 60);
$maxAds = (array_key_exists('COUNT', $tpl_content->vars) ? (int)$tpl_content->vars['COUNT'] : 4);
$offset = (array_key_exists('OFFSET', $tpl_content->vars) ? (int)$tpl_content->vars['OFFSET'] : 0);
$maxAdsPerRow = (array_key_exists('COUNT_PER_ROW', $tpl_content->vars) ? (int)$tpl_content->vars['COUNT_PER_ROW'] : $maxAds);
$maxAdsPerRowResponsive = (array_key_exists('COUNT_PER_ROW_RESPONSIVE', $tpl_content->vars) ? $tpl_content->vars['COUNT_PER_ROW_RESPONSIVE'] : "");
$arExcluded = array();
$strExcludedAds = (array_key_exists('EXCLUDE_ADS', $tpl_content->vars) ? $tpl_content->vars['EXCLUDE_ADS'] : "");
if (!empty($strExcludedAds) && preg_match_all("/([0-9]+)/i", $strExcludedAds, $arMatches)) {
    $arExcludeAdsRaw = $arMatches[1];
    foreach ($arExcludeAdsRaw as $excludeIndex => $excludeAdId) {
        $arExcluded[] = (int)$excludeAdId;
    }
}
$template = (array_key_exists('TEMPLATE', $tpl_content->vars) ? (int)$tpl_content->vars['TEMPLATE'] : "row_box");
$arSettings = array(
    "ID_KAT" 		    => $id_kat,
    "ID_USER" 		    => $id_user,
    "COUNT"	            => $maxAds,
    "OFFSET"            => $offset,
    "COUNT_PER_ROW"	    => $maxAdsPerRow,
    "COUNT_PER_ROW_RES"	=> $maxAdsPerRowResponsive,
    "EXCLUDE_ADS"       => $arExcluded,
    "HIDE_PARENT"	    => (array_key_exists('HIDE_PARENT', $tpl_content->vars) ? $tpl_content->vars['HIDE_PARENT'] > 0 : false),
    "ONLY_TOP"	        => (array_key_exists('ONLY_TOP', $tpl_content->vars) ? (int)$tpl_content->vars['ONLY_TOP'] : 0),
    "SPAN_WIDTH"        => ($maxAdsPerRow > 12 ? 1 : round(12 / $maxAdsPerRow)),
    "TEMPLATE"          => $template
);
// Cache-Datei prüfen
$cacheHash = md5( serialize($arSettings) );
$cacheFile = $ab_path."cache/marktplatz/user/".$cacheHash.".htm";
$cacheFileAge = (file_exists($cacheFile) && !$_SESSION["USER_IS_ADMIN"] ? (time() - filemtime($cacheFile)) / 60 : false );
$cacheContent = "";
if (($cacheLifetime <= 0) || ($cacheFileAge === false) || ($cacheFileAge > $cacheLifetime)) {
    /*
     * Cache (neu) erzeugen
     */
    require_once $ab_path."sys/lib.ad_constraint.php";
    
    $arQueryOptions = array();
    $tpl_content->addvars($arSettings);
    
    // Category
    if ($id_kat > 0) {
        $arQueryOptions["FK_KAT"] = $id_kat;
    }
    // User
    if ($id_user > 0) {
        $arQueryOptions["FK_USER"] = $id_user;
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
    $queryAds->addSortFields(array(
   		"B_TOP_LIST"				=> "DESC",
   		$tableMaster.".STAMP_START"	=> "DESC",
   		"ID_AD_MASTER"				=> "DESC"
   	));
    $queryAds->setLimit($maxAds, $offset);
    
    Rest_MarketplaceAds::addQueryFieldsByTemplate($queryAds, "ads_user.".$template.".htm");
    #die($queryAds->getQueryString());
    $queryAds = new Api_DataTableQueryIntermediate($db, $queryAds, "ID_AD");
    $ads = $queryAds->fetchTable();
    foreach ( $ads as $index => $row ) {
        $sql = 'SELECT CONCAT(\'cache/vendor/logo/\',v.LOGO) as VENDOR_LOGO
                    FROM vendor v
                    WHERE v.FK_USER = ' . $row["FK_USER"];
        $ads[$index]["VENDOR_LOGO"] = $db->fetch_atom( $sql );
    }
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
    $tpl_content->addvar("liste_count", count($ads));
    $tpl_content->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
    $tpl_content->addvar("DAYS_ADS_NEW", $nar_systemsettings["MARKTPLATZ"]["DAYS_ADS_NEW"]);
    $tpl_content->isTemplateRecursiveParsable = FALSE;
    $tpl_content->isTemplateCached = TRUE;
    $tpl_content->addlist("liste", $ads, "tpl/".$s_lang."/ads_user.".$template.".htm");
    $tpl_content->isTemplateRecursiveParsable = TRUE;
    $tpl_content->isTemplateCached = FALSE;
    // Write cache
    $cacheContent = $tpl_content->process(true);
    if(!$_SESSION["USER_IS_ADMIN"]) {
        if ($cacheLifetime > 0) {
            if (!is_dir($ab_path."cache/marktplatz/user")) {
                // Cache Verzeichnis erstellen
                mkdir($ab_path."cache/marktplatz/user", 0777, true);
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
