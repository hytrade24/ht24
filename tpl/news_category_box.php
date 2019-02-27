<?php
/**
 * Holzbranche.com
 *
 *
 * @version 6.5.1
 */

/**
 * Liest zufällig sortierte Anzeigen aus.
 * 
 * BEISPIEL:    {subtpl(tpl/{SYS_TPL_LANG}/news_category_box.htm,ID_KAT=42,COUNT=8,ROWS=4,HIDE_PARENT=1)}
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

$kat = new TreeCategories("kat", 2);
$id_kat_root = $kat->tree_get_parent();

// Einstellungen auslesen
$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "ads_random", "Marktplatz-Anzeigen (Zufällig sortiert)");
$id_kat = $subtplConfig->addOptionText("ID_KAT", "Kategorie-ID", false, "{ID_KAT}");
$id_kat = ($id_kat > 0 ? $id_kat : $id_kat_root);
$cacheLifetime = $subtplConfig->addOptionLookup("CACHE_LIFETIME", "Cache gültig für", "CACHE_LIFE", false);
if ($cacheLifetime === null) {
    //$cacheLifetime = 24 * 60;
}
$maxCategoriesPerRow = $subtplConfig->addOptionIntRange("COUNT_PER_ROW", "Anzahl pro Zeile", false, 3, 1, 12);
$maxCategoriesPerRowResponsive = $subtplConfig->addOptionText("COUNT_PER_ROW_RESPONSIVE", "Responsive", false, "xs-1 sm-2");
$template = $subtplConfig->addOptionSelectList("TEMPLATE", "Darstellung", 'row', array(
    'row'	    		=> "Listen-Darstellung"
));
$hideParent = $subtplConfig->addOptionHidden("HIDE_PARENT", "Eltern-Element ausblenden wenn leer", false, true);
$subtplConfig->finishOptions();

$arSettings = array(
    "LANG"                      => $s_lang,
    "ID_KAT" 		                => $id_kat,
    "COUNT_PER_ROW"	            => $maxCategoriesPerRow,
    "COUNT_PER_ROW_RESPONSIVE"	=> $maxCategoriesPerRowResponsive,
    "HIDE_PARENT"	              => $hideParent,
    "TEMPLATE"                  => $template
);
// Cache-Datei prüfen
$cacheHash = md5( serialize($arSettings) );
$cacheDir = $ab_path."cache/news/categories";
$cacheFile = $cacheDir."/".$cacheHash.".htm";
$cacheFileAge = (file_exists($cacheFile) && !$_SESSION["USER_IS_ADMIN"] ? (time() - filemtime($cacheFile)) / 60 : false );
$cacheContent = "";
if (($cacheLifetime <= 0) || ($cacheFileAge === false) || ($cacheFileAge > $cacheLifetime)) {
    /*
     * Cache (neu) erzeugen
     */
    $categoryParent = $kat->element_read($id_kat);
    $categoryList = $db->fetch_table("
        SELECT
            k.*, s.*,
            COUNT(n.ID_NEWS) as `COUNT`
        FROM `news` n
        JOIN `kat` k ON k.ID_KAT=n.FK_KAT
        JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=k.ID_KAT
          AND s.BF_LANG=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
        WHERE ROOT=2 AND LFT>".(int)$categoryParent["LFT"]." AND RGT<".(int)$categoryParent["RGT"]."
        GROUP BY n.FK_KAT");
    
    // Output result
    $tpl_content->isTemplateRecursiveParsable = TRUE;
    $tpl_content->isTemplateCached = TRUE;
    $tpl_content->addlist("liste", $categoryList, "tpl/".$s_lang."/news_category_box.".$template.".htm");
    $tpl_content->isTemplateCached = FALSE;
    // - Per row value
    $arCategoriesPerRow = array("xs" => $maxCategoriesPerRow, "sm" => $maxCategoriesPerRow, "md" => $maxCategoriesPerRow, "lg" => $maxCategoriesPerRow);
    $arCategoriesPerRowRaw = explode(" ", $maxCategoriesPerRowResponsive);
    foreach ($arCategoriesPerRowRaw as $adsPerRowIndex => $adsPerRowValue) {
        if (preg_match("/^(xs|sm|md|lg)\-?([0-9]+)$/i", $adsPerRowValue, $arMatch)) {
            $arCategoriesPerRow[$arMatch[1]] = (int)$arMatch[2];
        }
    }
    $tpl_content->addvars($arCategoriesPerRow, "COUNT_PER_ROW_");
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

?>
