<?php

/**
 * Stellt eine Suchmaske für eine Kategorie dar.
 *
 * BEISPIEL:    {subtpl(tpl/{SYS_TPL_LANG}/ads_search.htm,ID_KAT=42,ROW_COUNT_GROUPS=3)}
 * PARAMETER:
 *  ID_KAT              - (optional) Gibt an in welcher Kategorie gesucht werden soll.
 *                          Standard: Es wird über alle Kategorien gesucht.
 *  CACHE_LIFETIME      - (optional) Zeit in Minuten nach der die Anzeigen neu gecached werden. (0 zum deaktivieren des Cache)
 *                          Standard: Die Anzeigen werden alle 60 Minuten neu gecached.
 *  HIDE_BASE           - (optional) Versteckt die Grund-Suchfelder. (Hersteller/Produktname/Kategorie/Verfügbarkeit)
 *                          Standard: Die Grund-Suchfelder werden angezeigt.
 *  HIDE_LOCATION       - (optional) Versteckt die Suchfelder für die Umkreissuche. (Hersteller/Produktname/Kategorie/Verfügbarkeit)
 *                          Standard: Die Grund-Suchfelder werden angezeigt.
 *  GROUP_MIN_HEIGHT    - (optional) Mindesthöhe für Feld-Gruppen in Pixel
 *                          Standard: 100
 *  ROW_COUNT_GROUPS    - (optional) Anzahl an Gruppen pro Zeile.
 *                          Standard: 4
 *  ROW_COUNT_VISIBLE   - (optional) Anzahl der von beginn an sichtbaren Zeilen. Zusätzliche können vom User eingeblendet werden.
 *                          Standard: 1
 *
 */

global $nar_systemsettings;

require_once 'sys/lib.vendor.php';

$cacheLifetime = 60*24;
$arSettings = array(
    "LANG"              => $s_lang,
    "CACHE_LIFETIME"    => $cacheLifetime,
);

// Cache-Datei prüfen
$cacheHash = md5( serialize($arSettings) );
$cacheDir = $ab_path."cache/vendor";
$cacheFile = $cacheDir."/vendor_statistics_".$cacheHash.".htm";
$cacheFileAge = (file_exists($cacheFile) ? (time() - filemtime($cacheFile)) / 60 : false );
$cacheContent = "";
if (($cacheLifetime <= 0) || ($cacheFileAge === false) || ($cacheFileAge > $cacheLifetime)) {

    $vendorManagement = VendorManagement::getInstance($db);
    $vendorManagement->setLangval($langval);
    $countVendorsActive = $vendorManagement->countByParam([]);
    $tpl_content->addvar('vendors_active', $countVendorsActive);

    /** @var Api_Plugins_Leads_Plugin $pluginLeads */
    $pluginLeads = Api_TraderApiHandler::getInstance()->getPlugin("Leads");
    $countProjects = $pluginLeads->countProjects();
    $tpl_content->addvar('projects', $countProjects);

    $countAdProducts = $db->fetch_atom("
      SELECT COUNT(DISTINCT PRODUKTNAME) FROM `ad_master`
      WHERE STATUS = 1 AND CONFIRMED = 1 AND DELETED = 0 AND FK_PRODUCT IS NOT NULL");
    $countAdCustoms = $db->fetch_atom("
      SELECT COUNT(*) FROM ad_master 
      WHERE STATUS = 1 AND CONFIRMED = 1 AND DELETED = 0 AND FK_PRODUCT IS NULL");
    $countAds = $countAdProducts + $countAdCustoms; 
    $tpl_content->addvar('ads', $countAds);

    // Write cache
    $cacheContent = $tpl_content->process(false);
    if ($cacheLifetime > 0) {
        if (!is_dir($ab_path."cache/vendor")) {
            // Cache Verzeichnis erstellen
            mkdir($ab_path."cache/vendor", 0777, true);
        }
        // Cache ist aktiviert, Cachedatei (neu) scheiben
        file_put_contents($cacheFile, $cacheContent);
    } else if (file_exists($cacheFile)) {
        // Cache ist deaktiviert, Cachedatei löschen
        unlink($cacheFile);
    }
} else {
    /*
     * Cache noch gültig, aus dem Cache lesen
     */
    $cacheContent = file_get_contents($cacheFile);
}

$tpl_content->tpl_text = $cacheContent;