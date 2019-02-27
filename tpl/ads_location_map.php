<?php
/* ###VERSIONSBLOCKINLCUDE### */

/**
 * Gibt eine Google-Karte mit Anzeigen um einen bestimmten Standort aus.
 * 
 * BEISPIEL:    {subtpl(tpl/{SYS_TPL_LANG}/ads_location_map.htm,LATITUDE=53.25,LONGITUDE=8.92,LU_UMKREIS=75,ID_USER=1,COUNT=8,HIDE_PARENT=1)}
 * PARAMETER:
 *  ID_KAT          - (optional) Gibt an in aus welcher Kategorie die Anzeigen stammen sollen.
 *                      Standard: Die Anzeigen aus allen Kategorien ausgelesen.
 *  LATITUDE        - Breitengrad des Standorts um den gesucht werden soll
 *  LONGITUDE       - Längengrad des Standorts um den gesucht werden soll
 *  LU_UMKREIS      - Radius in dem um den Standort gesucht werden soll
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

$kat = new TreeCategories("kat", 1);
$id_kat_root = $kat->tree_get_parent();

// Einstellungen auslesen
$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "ads_location_map", "Marktplatz-Anzeigen (Umkreissuche)");
$id_kat = $subtplConfig->addOptionText("ID_KAT", "Kategorie-ID", false, "{ID_KAT}");
$id_kat = ($id_kat > 0 ? $id_kat : $id_kat_root);
$latitude = $subtplConfig->addOptionText("LATITUDE", "Breitengrad", true, 0);
$longitude = $subtplConfig->addOptionText("LONGITUDE", "Längengrad", true, 0);
$umkreis = $subtplConfig->addOptionLookup("LU_UMKREIS", "Umkreis", "UMKREIS", true);
$luUmkreis = $tpl_content->vars["LU_UMKREIS"];
$description = $subtplConfig->addOptionText("DESCRIPTION", "Beschreibung", true, "{DESCRIPTION}");
$cacheLifetime = $subtplConfig->addOptionLookup("CACHE_LIFETIME", "Cache gültig für", "CACHE_LIFE", false);
$maxAds = $subtplConfig->addOptionIntRange("COUNT", "Anzahl Anzeigen", false, 4, 1, 16);
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
$hideParent = $subtplConfig->addOptionHidden("HIDE_PARENT", "Eltern-Element ausblenden wenn leer", false);
$subtplConfig->finishOptions();

$arSettings = array(
    "LANG"                      => $s_lang,
    "ID_KAT" 		            => $id_kat,
    "LATITUDE"                  => $latitude,
    "LONGITUDE"                 => $longitude,
    "LU_UMKREIS"                => $luUmkreis,
    "COUNT"	                    => $maxAds,
    "EXCLUDE_ADS"               => $arExcluded,
    "HIDE_PARENT"	            => $hideParent,
    "ONLY_TOP"	                => $onlyTop,
    "SPAN_WIDTH"                => ($maxAdsPerRow > 12 ? 1 : round(12 / $maxAdsPerRow)),
    "TEMPLATE"                  => $template
);
$cacheLifetime = 0;
// Cache-Datei prüfen
$cacheDir = $ab_path."cache/marktplatz/location";
$cacheHash = md5( serialize($arSettings) );
$cacheFile = $cacheDir."/".$cacheHash.".htm";
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
    // Location search
    #die(var_dump($latitude, $longitude, $luUmkreis));
    // TODO
    $arQueryOptions["LU_UMKREIS"] = $luUmkreis;
    $arQueryOptions["LONGITUDE"] = $longitude;
    $arQueryOptions["LATITUDE"] = $latitude;
    //die(var_dump($arQueryOptions));

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
    //die($queryAds->getQueryString());

    $tableMaster = $queryAds->getDataTable()->getTableIdent();
    $queryAds->addSortField("GEO_DISTANCE", "ASC");
    $queryAds->setLimit($maxAds);
    
    Rest_MarketplaceAds::addQueryFieldsByTemplate($queryAds, "ads_location_map.".$template.".htm");
    $ads = $queryAds->fetchTable();
    Rest_MarketplaceAds::extendAdDetailsList($ads);

    foreach ($ads as $adIndex => $adDetails) {
        $ads[$adIndex]["URL"] = Api_Entities_MarketplaceArticle::getById($adDetails["ID_AD"])->getUrl();
    }
    //die(var_dump($ads));
    /*
    foreach ($ads as $adIndex => $adDetails) {
        // TODO Distance matix for $adDetails["LATITUDE"] / $adDetails["LONGITUDE"]
        // TODO Write result to $ads[$adIndex]
        $urlOrigins = $arQueryOptions["LONGITUDE"].",".$arQueryOptions["LATITUDE"];
        $destination = array($adDetails["LATITUDE"].",".$adDetails["LONGITUDE"]);
        $distanceResult = Geolocation_GoogleMaps::getDistanceMatrix(array("lat" => $arQueryOptions["LATITUDE"], "lng" => $arQueryOptions["LONGITUDE"]), $destination, "driving");
        if ($distanceResult[0]["status"] == "OK") {
            //var_dump($distanceResult[0]);
            $ads[$adIndex]["DistanceMatrix_DistanceText"] = $distanceResult[0]["distance"]["text"];
            $ads[$adIndex]["DistanceMatrix_DistanceValue"] = $distanceResult[0]["distance"]["value"];
            $ads[$adIndex]["DistanceMatrix_DurationText"] = $distanceResult[0]["duration"]["text"];
            $ads[$adIndex]["DistanceMatrix_DurationValue"] = $distanceResult[0]["duration"]["value"];
        }
    }
    */
    //die(var_dump($ads));
    // $ads = array( array("LATITUDE" => 47, "LONGITUDE" => 9, "distancematrix_drive" => 5.4), ... )

    // Output result
    $tpl_content->isTemplateRecursiveParsable = TRUE;
    $tpl_content->isTemplateCached = TRUE;
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

    $tpl_content->addvar("POI_HASH", $cacheHash);
    $tpl_content->addvar("POI_COUNT", count($ads));
    $tpl_content->addvar("POI_JSON", json_encode($ads));
    $tpl_content->isTemplateCached = FALSE;
    // Write cache
    $cacheContent = $tpl_content->process(true);
    if(!$_SESSION["USER_IS_ADMIN"]) {
        if ($cacheLifetime > 0) {
            if (!is_dir($cacheDir)) {
                // Cache Verzeichnis erstellen
                mkdir($cacheDir, 0777, true);
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

// Add marketplace settings to template
$tpl_content->addvar("USE_HERSTELLER", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED']);
$tpl_content->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
$tpl_content->addvar("DAYS_ADS_NEW", $nar_systemsettings["MARKTPLATZ"]["DAYS_ADS_NEW"]);

?>
