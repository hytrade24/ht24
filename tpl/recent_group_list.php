<?php

// Einstellungen auslesen
$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "recent_group_list", "Aktuellste Gruppen-Beiträge");
$groupCategoryId = $subtplConfig->addOptionInt("ID_KAT", "Kategorie-ID", false, "{ID_KAT}");
$groupCategoryId = ($groupCategoryId > 0 ? $groupCategoryId : null);
$groupClubId = $subtplConfig->addOptionInt("ID_CLUB", "Gruppen-ID", false, "{ID_CLUB}");
$groupCount = $subtplConfig->addOptionIntRange("COUNT", "Anzahl", false, 2, 1, 20);
$eventCountPerRow = $subtplConfig->addOptionIntRange("COUNT_PER_ROW", "Anzahl pro Zeile", false, 1, 1, 10);
$template = $subtplConfig->addOptionSelectList("TEMPLATE", "Darstellung", 'row', array(
    'row'			=> "Listen-Darstellung"
));
$cacheLifetime = $subtplConfig->addOptionLookup("CACHE_LIFETIME", "Cache gültig für", "CACHE_LIFE", false);
$hideParent = $subtplConfig->addOptionHidden("HIDE_PARENT", "Eltern-Element ausblenden wenn leer", false);
$subtplConfig->finishOptions();

$arSettings = array(
	"LANG"          	    => $s_lang,
	"ID_KAT" 			        => $groupCategoryId,
	"ID_CLUB" 			      => $groupClubId,
	"COUNT"				        => $groupCount,
	"COUNT_PER_ROW"		    => $eventCountPerRow,
	"CACHE_LIFETIME"	    => $cacheLifetime,
	"HIDE_PARENT"			    => $hideParent
);

// Cache-Datei prüfen
$cacheHash = md5( serialize($arSettings) );
$cacheDir = $ab_path."cache/club";
$cacheFile = $cacheDir."/recent_".$cacheHash.".htm";
$cacheFileAge = (file_exists($cacheFile) && !$_SESSION["USER_IS_ADMIN"] ? (time() - filemtime($cacheFile)) / 60 : false );
$cacheContent = "";
if (($cacheLifetime <= 0) || ($cacheFileAge === false) || ($cacheFileAge > $cacheLifetime)) {
	
    // Change base template?
    $tplNameBase = trim(str_replace("row", "", $template), "_.");
    if (!empty($tplNameBase)) {
        $tplFileBase = CacheTemplate::getHeadFile("tpl/".$s_lang."/recent_group_list.".$tplNameBase.".htm");
        if (file_exists($tplFileBase)) {
            $tpl_content->LoadText($tplFileBase);
        }
    }
	
		include_once $ab_path.'sys/lib.groupforum.php';
		require_once $ab_path."sys/lib.club.php";
		$clubManagement = ClubManagement::getInstance($db);
    
    $strJoin = "";
    $strWhere = "";
    if ($groupCategoryId > 0) {
        $arSearchKat = $db->fetch1("SELECT LFT, RGT, ROOT FROM `kat` WHERE ID_KAT=".(int)$groupCategoryId);
        $strJoin .= "
    		LEFT JOIN club_category cc ON cc.FK_CLUB = c.ID_CLUB
            LEFT JOIN kat k ON k.ID_KAT=cc.FK_KAT";
        $strWhere .= " AND (k.LFT>=".$arSearchKat["LFT"]." AND k.RGT<=".$arSearchKat["RGT"]." AND k.ROOT=".$arSearchKat["ROOT"].")";
    }
    if ($groupClubId > 0) {
        $strWhere .= " AND (c.ID_CLUB=".(int)$groupClubId.")";
    }
    
	$recentClubActivities = $db->fetch_table($a = "
		SELECT
			cd.ID_CLUB_DISCUSSION as DISCUSSION_ID_CLUB_DISCUSSION,
			cd.NAME AS DISCUSSION_NAME,
			cd.STAMP_CREATE AS DISCUSSION_STAMP_CREATE,
			cd.STAMP_REPLY AS DISCUSSION_STAMP_REPLY,
			cd.BODY as DISCUSSION_BODY,
			c.NAME as CLUB_NAME,
			(SELECT c.BODY FROM club_discussion_comment c WHERE c.FK_CLUB_DISCUSSION = cd.ID_CLUB_DISCUSSION ORDER BY c.STAMP_CREATE DESC LIMIT 1) as DISCUSSION_COMMENT_BODY,
			u.CACHE as USER_CACHE, u.ID_USER AS USER_ID, u.NAME as USER_NAME
		FROM club_discussion cd
		JOIN club c ON c.ID_CLUB = cd.FK_CLUB
		JOIN user u ON u.ID_USER = cd.FK_USER
		LEFT JOIN club2user cu ON cu.FK_CLUB = c.ID_CLUB
        ".$strJoin."
		WHERE
			(c.STATUS=1 AND cd.PUBLIC = 1 AND c.FORUM_PUBLIC = 1 AND c.FORUM_ENABLED = 1)".$strWhere."
		GROUP BY cd.ID_CLUB_DISCUSSION
		ORDER BY cd.STAMP_REPLY DESC, cd.STAMP_CREATE DESC
		LIMIT ".(int)$groupCount."
	");
	$tpl_content->addlist('liste', $recentClubActivities, 'tpl/'.$s_lang.'/recent_group_list.'.$template.'.htm');
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
