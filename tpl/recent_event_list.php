<?php

require_once $ab_path."sys/lib.user_media.php";
global $nar_systemsettings;

// Einstellungen auslesen
$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "recent_event_list", "Aktuellste Veranstaltungen");
$eventCategoryId = $subtplConfig->addOptionInt("ID_KAT", "Kategorie-ID", false, "{ID_KAT}");
$eventCategoryId = ($eventCategoryId > 0 ? $eventCategoryId : null);
$eventStartAt = $subtplConfig->addOptionHidden("START_DATE", "Start-Datum", false, null);
$eventCount = $subtplConfig->addOptionIntRange("COUNT", "Anzahl", false, 2, 1, 20);
$eventOffset = $subtplConfig->addOptionIntRange("OFFSET", "Start", false, 0, 0, 100);
$eventCountPerRow = $subtplConfig->addOptionIntRange("COUNT_PER_ROW", "Anzahl pro Zeile", false, 1, 1, 10);
$template = $subtplConfig->addOptionSelectList("TEMPLATE", "Darstellung", 'row', array(
    'row'       => "Listen-Darstellung",
    'row_big'		=> "Große Vorschau",
    'row_img1'	=> "Bild-Darstellung #1",
    'row_img2'	=> "Bild-Darstellung #2"
));
$cacheLifetime = $subtplConfig->addOptionLookup("CACHE_LIFETIME", "Cache gültig für", "CACHE_LIFE", false);
$hideParent = $subtplConfig->addOptionHidden("HIDE_PARENT", "Eltern-Element ausblenden wenn leer", false);
$subtplConfig->finishOptions();

$arSettings = array(
	"LANG"          	=> $s_lang,
	"ID_KAT" 			    => $id_kat,
	"COUNT"				    => $eventCount,
  "OFFSET"          => $eventOffset,
	"COUNT_PER_ROW"		=> $eventCountPerRow,
	"START_DATE"		  => $eventStartAt,
	"CACHE_LIFETIME"  => $cacheLifetime,
  "TEMPLATE"        => $template,
	"HIDE_PARENT"			=> $hideParent
);

$eventStartAt = ($eventStartAt > 0 ? $eventStartAt : time());

// Cache-Datei prüfen
$cacheHash = md5( serialize($arSettings) );
$cacheDir = $ab_path."cache/event";
$cacheFile = $cacheDir."/recent_".$cacheHash.".htm";
$cacheFileAge = (file_exists($cacheFile) && !$_SESSION["USER_IS_ADMIN"] ? (time() - filemtime($cacheFile)) / 60 : false );
$cacheContent = "";
if (($cacheLifetime <= 0) || ($cacheFileAge === false) || ($cacheFileAge > $cacheLifetime)) {
    
    // Change base template?
    $tplNameBase = trim(str_replace("row", "", $template), "_.");
    if (!empty($tplNameBase)) {
        $tplFileBase = CacheTemplate::getHeadFile("tpl/".$s_lang."/recent_event_list.".$tplNameBase.".htm");
        if (file_exists($tplFileBase)) {
            $tpl_content->LoadText($tplFileBase);
        }
    }
        
    require_once $ab_path."sys/lib.calendar_event.php";
    require_once $ab_path."sys/lib.club.php";
    $calendarEventManagement = CalendarEventManagement::getInstance($db);
    $clubManagement = ClubManagement::getInstance($db);
    $tpl_content->addvars($arSettings);
    // Prepare search parameter
    $searchParameter = array("IS_CONFIRMED" => 1, "MODERATED" => 1);
    $searchParameter["PRIVACY"] = 1;
    $searchParameter['CATEGORY'] = $eventCategoryId;
    $searchParameter['TYPE'] = 'DEFAULT';
    $searchParameter['STAMP_END_GT'] = date("Y-m-d H:i:s", $eventStartAt);
    $searchParameter['SORT_BY'] = 'STAMP_START';
    $searchParameter['SORT_DIR'] = 'ASC';
    $searchParameter['LIMIT'] = $eventCount;
    $searchParameter['OFFSET'] = $eventOffset;
    // Get events
    $calendarEvents = $calendarEventManagement->fetchAllByParam($searchParameter, $all);
    foreach($calendarEvents as $key => $calendarEvent) {
        $arDefaultImage = UserMediaManagement::getDefaultImage($db, "calendar_event", $calendarEvent["ID_CALENDAR_EVENT"]);
        if ($arDefaultImage !== false) {
            $calendarEvents[$key] = array_merge($calendarEvent, array_flatten($arDefaultImage, true, "_", "IMAGE_"));
        }
        /*

            IMAGE_FK
            :
            "3"
            IMAGE_ID_MEDIA_IMAGE
            :
            "20"
            IMAGE_IS_DEFAULT
            :
            "1"
            IMAGE_META_SOURCE
            :
            "ebiz-consult"
            IMAGE_META_TITLE
            :
            "ebiz stats"
            IMAGE_SER_META
            :
            "a:2:{s:5:"TITLE";s:10:"ebiz stats";s:6:"SOURCE";s:12:"ebiz-consult";}"
            IMAGE_SRC
            :
            "/cache/media/calendar_event/eccbc87e/0.69191300_1482158740.jpg"
            IMAGE_SRC_THUMB
            :
            "/cache/media/calendar_event/eccbc87e/thumb_0.81709300_1482158740.jpg"
            IMAGE_TABLE
            :
            "calendar_event"

         */

		$calendarEvents[$key]["STAMP_START_TIMESTAMP"] = strtotime($calendarEvent["STAMP_START"]);
		$calendarEvents[$key]["STAMP_END_TIMESTAMP"] = strtotime($calendarEvent["STAMP_END"]);
	
		$calendarEvents[$key]["STAMP_START_DATE"] = date("Ymd", strtotime($calendarEvent["STAMP_START"]));
		$calendarEvents[$key]["STAMP_END_DATE"] =  date("Ymd", strtotime($calendarEvent["STAMP_END"]));
	
	}
	$tpl_content->addlist('liste', $calendarEvents, 'tpl/'.$s_lang.'/recent_event_list.'.$template.'.htm');
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
