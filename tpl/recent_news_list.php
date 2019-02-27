<?php

// Einstellungen auslesen
$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "recent_news_list", "Aktuellste News");
$newsCategoryId = $subtplConfig->addOptionInt("ID_KAT", "Kategorie-ID", false, "{ID_KAT}");
$newsCategoryId = ($newsCategoryId > 0 ? $newsCategoryId : null);
$newsCount = $subtplConfig->addOptionIntRange("COUNT", "Anzahl", false, 2, 1, 20);
$newsOffset = $subtplConfig->addOptionIntRange("OFFSET", "Start", false, 0, 0, 100);
$eventCountPerRow = $subtplConfig->addOptionIntRange("COUNT_PER_ROW", "Anzahl pro Zeile", false, 1, 1, 10);
$showPreviewImage = $subtplConfig->addOptionCheckbox("SHOW_PREVIEW_IMAGE", "Vorschau Bild/Video anzeigen", false);
$showTypes = $subtplConfig->addOptionCheckboxList("SHOW_TYPES", "News-Typen", false, array(
    1 => "Top-News",
    2 => "Reguläre News"
));
$template = $subtplConfig->addOptionSelectList("TEMPLATE", "Darstellung", 'row', array(
    'row'			=> "Listen-Darstellung",
    'row_big'			=> "Große Vorschau",
    'row_list'			=> "Einfache Liste"
));
$cacheLifetime = $subtplConfig->addOptionLookup("CACHE_LIFETIME", "Cache gültig für", "CACHE_LIFE", false);
$hideParent = $subtplConfig->addOptionHidden("HIDE_PARENT", "Eltern-Element ausblenden wenn leer", false);
$subtplConfig->finishOptions();

$arSettings = array(
  "LANG"                => $s_lang,
  "ID_KAT"              => $newsCategoryId,
  "COUNT"               => $newsCount,
  "OFFSET"              => $newsOffset,
  "COUNT_PER_ROW"       => $eventCountPerRow,
  "SHOW_PREVIEW_IMAGE"  => $showPreviewImage,
  "SHOW_TYPES"          => $showTypes,
  "TEMPLATE"            => $template,
  "CACHE_LIFETIME"      => $cacheLifetime,
	"HIDE_PARENT"			    => $hideParent
);

// Cache-Datei prüfen
$cacheHash = md5( serialize($arSettings) );
$cacheDir = $ab_path."cache/news";
$cacheFile = $cacheDir."/recent_".$cacheHash.".htm";
$cacheFileAge = (file_exists($cacheFile) && !$_SESSION["USER_IS_ADMIN"] ? (time() - filemtime($cacheFile)) / 60 : false );
$cacheContent = "";
if (($cacheLifetime <= 0) || ($cacheFileAge === false) || ($cacheFileAge > $cacheLifetime)) {
    
    // Change base template?
    $tplNameBase = trim(str_replace("row", "", $template), "_.");
    if (!empty($tplNameBase)) {
        $tplFileBase = CacheTemplate::getHeadFile("tpl/".$s_lang."/recent_news_list.".$tplNameBase.".htm");
        if (file_exists($tplFileBase)) {
            $tpl_content->LoadText($tplFileBase);
        }
    }
    
    $arQueryParams = array("LIMIT" => $newsCount, "OFFSET" => $newsOffset);
    if ($newsCategoryId > 0) {
        $arQueryParams["ID_KAT"] = $newsCategoryId;
    }
    if ($showTypes > 0) {
        $arQueryParams["TYPES"] = $showTypes;
    }
    $arNews = Api_NewsManagement::getInstance($db)->fetchAll($arQueryParams);
    #dd($arNews);
    $tpl_content->addvars($arSettings);
    $tpl_content->addlist('liste', $arNews, 'tpl/'.$s_lang.'/recent_news_list.'.$template.'.htm');
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

?>
