<?php
/* ###VERSIONSBLOCKINLCUDE### */

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

include_once $ab_path."sys/lib.shop_kategorien.php";

$tpl_content->addvar("USE_HERSTELLER", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED']);

$kat = new TreeCategories("kat", 1);
$id_kat_root = $kat->tree_get_parent();
// Einstellungen auslesen
$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "ads_search_special", "Marktplatz Suchmaske");
$id_kat = $subtplConfig->addOptionText("ID_KAT", "Kategorie-ID", false, "{ID_KAT}");
$id_kat = ($id_kat > 0 ? $id_kat : $id_kat_root);
$search_hash = $subtplConfig->addOptionHidden("SEARCH_HASH", "Such-Hash", false, "{SEARCH_HASH}");
$search_hash = $tpl_content->parseTemplateString($search_hash);
$cacheLifetime = $subtplConfig->addOptionLookup("CACHE_LIFETIME", "Cache gültig für", "CACHE_LIFE", false);
$designStyle = $subtplConfig->addOptionText("STYLE", "Design-Variante", false, "default");
$groupMinHeight = $subtplConfig->addOptionIntRange("GROUP_MIN_HEIGHT", "Mindesthöhe für Gruppen (pixel)", false, 100, 40, 800);
$rowCountGroups = $subtplConfig->addOptionIntRange("ROW_COUNT_GROUPS", "Anzahl Spalten", false, 4, 1, 12);
$rowCountVisible = $subtplConfig->addOptionIntRange("ROW_COUNT_VISIBLE", "Sichtbare Zeilen", false, 1, 1, 12);
$subtplConfig->finishOptions();

$arSettings = array(
	"LANG"              => $s_lang,
	"ID_KAT" 		    => $id_kat,
	"CACHE_LIFETIME"    => $cacheLifetime,
	"STYLE"             => $designStyle,
	"GROUP_MIN_HEIGHT"  => $groupMinHeight,
	"GROUP_WIDTH"       => (100 / $rowCountGroups)."%",
	"ROW_COUNT_GROUPS"	=> $rowCountGroups,
	"ROW_COUNT_VISIBLE"	=> $rowCountVisible,
);

if ($designStyle != "default") {
	$tpl_content->LoadText("tpl/".$s_lang."/ads_search.".$designStyle.".htm");
}

// Cache-Datei prüfen
$cacheHash = md5( serialize($arSettings) );
$cacheDir = $ab_path."cache/marktplatz/search";
if (!is_dir($cacheDir)) {
	mkdir($cacheDir, 0777, true);
}
$cacheFile = $cacheDir."/".$cacheHash.".htm";
$cacheFileAge = (file_exists($cacheFile) ? (time() - filemtime($cacheFile)) / 60 : false );
$cacheContent = "";
if (($cacheLifetime <= 0) || ($cacheFileAge === false) || ($cacheFileAge > $cacheLifetime) ) {
	// Kategorie-Details auslesen
	$ar_kat = $db->fetch1("
         SELECT t.*, s.V1, s.V2 FROM `kat` t
         LEFT JOIN string_kat s on s.S_TABLE='kat' AND s.FK=t.ID_KAT
             AND s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
   		WHERE ID_KAT=".(int)$id_kat);
	$tpl_content->addvars($ar_kat);
	$tpl_content->addvar("KATNAME", $ar_kat['V1']);
	$katOptions = unserialize($ar_kat['SER_OPTIONS']);
	if($katOptions != false) {
		$tpl_content->addvars(unserialize($ar_kat['SER_OPTIONS']), "OPTIONS_");
	}
	// Prüfen ob in den Unterkategorien andere Artikel-Tabellen verwendet werden
	$articleTableId = false;
	$articleTablesChild = $db->fetch_atom("
      SELECT count(*) FROM `kat` WHERE KAT_TABLE<>'".mysql_real_escape_string($ar_kat["KAT_TABLE"])."'
        AND ROOT=".(int)$ar_kat["ROOT"]." AND LFT BETWEEN ".(int)$ar_kat["LFT"]." AND ".(int)$ar_kat["RGT"]);
	if ((int)$articleTablesChild === 0) {
		$articleTableId = $db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_real_escape_string($ar_kat["KAT_TABLE"])."'");
	}
	// Suchfelder/-gruppen auslesen
	$arRows = array();
	$arFieldsByGroup = array();
	$arGroupsOrdered = array();
	$arBaseGroups = array(
		"general"   => new Template("tpl/".$s_lang."/ads_search.group.base.htm"),
		"nogroup"   => array(),
		"location"  => new Template("tpl/".$s_lang."/ads_search.group.location.htm")
	);
	$arSpecialFields = array();
	$resGroups = $db->querynow("
       SELECT 
         f.ID_FIELD_DEF, f.FK_TABLE_DEF, f.F_TYP, f.FK_LISTE, f.F_NAME, f.IS_SPECIAL, f.B_SEARCH, f.FK_FIELD_GROUP,
         kf.B_NEEDED, sf.V1, sf.V2, sf.T1, g.F_ORDER_SEARCH
       FROM `kat2field` kf
       LEFT JOIN `field_def` f ON f.ID_FIELD_DEF = kf.FK_FIELD
       LEFT JOIN `field_group` g ON g.ID_FIELD_GROUP=f.FK_FIELD_GROUP
       LEFT JOIN `string_field_def` sf ON sf.S_TABLE='field_def' AND sf.FK=kf.FK_FIELD
         AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
       WHERE kf.FK_KAT=".$id_kat." AND kf.B_ENABLED=1 AND kf.B_SEARCHFIELD=1 AND f.B_ENABLED=1
       GROUP BY f.ID_FIELD_DEF ORDER BY (g.F_ORDER_SEARCH IS NULL) ASC, g.F_ORDER_SEARCH ASC, f.F_ORDER ASC");
	while ($arField = mysql_fetch_assoc($resGroups["rsrc"])) {
		if ($arField["IS_SPECIAL"]) {
			$arSpecialFields[ $arField["F_NAME"] ] = 1;
			continue;
		} else {
			if ($arField["FK_TABLE_DEF"] !== $articleTableId) {
				continue;
			}
		}
		// Extract description strings
		list($arField["T1"], $arField["T2"], $arField["T3"]) = explode("§§§", $arField["T1"]);
		list($arField["T1_DESC"], $arField["T1_HELP"]) = explode("||", $arField["T1"]);
		list($arField["T2_DESC"], $arField["T2_HELP"]) = explode("||", $arField["T2"]);
		list($arField["T3_DESC"], $arField["T3_HELP"]) = explode("||", $arField["T3"]);
		// Get order index
		$groupOrderSearch = $arField["F_ORDER_SEARCH"];
		// Flatten array
		$arField = array_merge($arField, array_flatten($arField));
		$idGroup = $arField["FK_FIELD_GROUP"];
		if ($idGroup === null) {
			$arBaseGroups["nogroup"][] = $arField;
		} else if ($groupOrderSearch !== null) {
			if (!array_key_exists($idGroup, $arFieldsByGroup)) {
				$arFieldsByGroup[$idGroup] = array();
				$arGroupsOrdered[] = array(
					"ID_FIELD_GROUP"    => $idGroup,
					"F_ORDER_SEARCH"    => $groupOrderSearch
				);
			}
			$arFieldsByGroup[$idGroup][] = $arField;
		}
	}
	// Add list of manufacturers (if required)
	if($nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']) {
		$manCacheDir = $ab_path."cache/marktplatz/manufacturer";
		if (!is_dir($manCacheDir)) {
			mkdir($manCacheDir, 0777, true);
		}
		$manCacheFile = $manCacheDir."/sbox_".$s_lang."_".($id_kat > 0 ? (int)$id_kat : 0).".htm";
		$manCacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
		$modifyTime = @filemtime($manCacheFile);
		$diff = ((time()-$modifyTime)/60);

		if(($diff > $manCacheFileLifeTime) || !file_exists($manCacheFile)) {
			$ar_liste_man = $db->fetch_table("
                 SELECT
                    m.ID_MAN as VALUE,
                    m.NAME as TEXT
                 FROM
                    `manufacturers` m
                JOIN `man_group_mapping` mm ON m.ID_MAN=mm.FK_MAN
                JOIN `man_group_category` c ON mm.FK_MAN_GROUP=c.FK_MAN_GROUP
                 WHERE
                    m.CONFIRMED=1".($id_kat > 0 ? " AND c.FK_KAT=".$id_kat : "")."
                 ORDER BY
                     NAME
             ");
			$tpl_tmp = new Template($ab_path."tpl/de/empty.htm");
			$tpl_tmp->tpl_text = '{liste_man}';

			$row_tmp = '';
			foreach($ar_liste_man as $key => $man) {
				$row_tpl_tmp = new Template('tpl/' . $s_lang . '/ads_search.group.base.manufacturer.htm');
				$row_tpl_tmp->addvars($man);
				$row_tmp .= $row_tpl_tmp->process();
			}

			$tpl_tmp->addvar('liste_man', $row_tmp);
			$tpl_tmp->isTemplateRecursiveParsable = TRUE;

			file_put_contents($manCacheFile, $tpl_tmp->process());
		}

		$tplListeMan = @file_get_contents($manCacheFile);
	}
	// Add configuration of special fields
	$arBaseGroups['general']->addvars($arSpecialFields, "SPECIAL_");
	$arBaseGroups['general']->addvar('liste_man', $tpl_content->process_text($tplListeMan));
	// Render the group templates
	foreach ($arGroupsOrdered as $groupIndex => $arGroupBase) {
		$groupId = (int)$arGroupBase["ID_FIELD_GROUP"];
		$arFields = $arFieldsByGroup[ $groupId ];
		$arGroup = array();
		if ($groupId > 0) {
			$arGroup = $db->fetch1("
               SELECT t.ID_FIELD_GROUP, s.* FROM `field_group` t
               LEFT JOIN string_app s on s.S_TABLE='field_group' AND s.FK=t.ID_FIELD_GROUP
                 AND s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
               WHERE ID_FIELD_GROUP=".$groupId." ORDER BY t.F_ORDER");
		}
		$tplGroup = new Template("tpl/".$s_lang."/ads_search.group.htm");
		$tplGroup->addvars($arGroup, "GROUP_");
		$tplGroup->addlist("fields", $arFields, "tpl/".$s_lang."/ads_search.field.htm");
		$arGroupsOrdered[$groupIndex]["TEMPLATE"] = $tplGroup;
		//$arGroupsOrdered[$groupIndex]["TEMPLATE"] = $tplGroup->process(false);
	}
	// Insert enabled base templates
	$arBaseList = explode(",", $nar_systemsettings["MARKTPLATZ"]["SEARCH_BASE_GROUPS"]);
	foreach ($arBaseList as $groupString) {
		list($groupOrder,$groupName) = explode(":", $groupString);
		if (array_key_exists($groupName, $arBaseGroups)) {
			$tplGroup = false;
			if (!is_array($arBaseGroups[$groupName])) {
				$tplGroup = $arBaseGroups[$groupName];
				if ($groupName == "general") {
					$tplGroup->addlist("fields", $arBaseGroups["nogroup"], "tpl/".$s_lang."/ads_search.field.htm");
				}
			}
			if ($tplGroup !== false) {
				$arGroup = array("ID_FIELD_GROUP" => $groupName, "F_ORDER_SEARCH" => $groupOrder, "TEMPLATE" => $tplGroup);
				$groupIndex = 0;
				while (($groupIndex < count($arGroupsOrdered)) && ($arGroupsOrdered[$groupIndex]["F_ORDER_SEARCH"] !== NULL)
				       && ($arGroupsOrdered[$groupIndex]["F_ORDER_SEARCH"] < $groupOrder)) {
					$groupIndex++;
				}
				array_splice($arGroupsOrdered, $groupIndex, 0, array($arGroup));
			}
		}
	}
	// Put the groups into rows
	$rowSpanIndex = 0;
	$arRowGroups = array();
	$tplRow = new Template("tpl/".$s_lang."/ads_search.row.htm");
	$tplRow->addvar("i", count($arRows));
	foreach ($arGroupsOrdered as $arGroup) {
		$arRowGroups[] = $arGroup["TEMPLATE"];
		if (count($arRowGroups) == $rowCountGroups) {
			$tplRow->addvar("groups", $arRowGroups);
			$arRows[] = $tplRow;
			// Create new template
			$arRowGroups = array();
			$tplRow = new Template("tpl/".$s_lang."/ads_search.row.htm");
			$tplRow->addvar("i", count($arRows));
			$rowSpanIndex = 0;
		}
	}
	if (!empty($arRowGroups)) {
		$tplRow->addvar("groups", $arRowGroups);
		$arRows[] = $tplRow;
	}
	// Output result
	$tpl_content->isTemplateCached = false;
	$tpl_content->isTemplateRecursiveParsable = TRUE;
	$tpl_content->addvars($arSettings);
	$tpl_content->addvar("HASH", $cacheHash);
	$tpl_content->addvar("rows", $arRows);
	$tpl_content->addvar("row_count", count($arRows));
	$tpl_content->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
	$tpl_content->addvar("DAYS_ADS_NEW", $nar_systemsettings["MARKTPLATZ"]["DAYS_ADS_NEW"]);
	$tpl_content->addvar("USE_PRODUCT_DB", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
	$tpl_content->addvar("USE_ARTICLE_EAN", $nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_EAN']);
	$tpl_content->addvar("USE_AD_CONSTRAINTS", $nar_systemsettings['MARKTPLATZ']['AD_CONSTRAINTS']);
	// Process plugins
	$searchFormParam = new Api_Entities_EventParamContainer(array(
		"templateSearch"  => $tpl_content, "templateContent" => $GLOBALS['tpl_content'],
		"customFieldsHidden" => array(), "customContentPrepend" => array(), "customContentAppend" => array(),
		"customScripts" => array(), "searchHash" => $search_hash, "categoryId" => $id_kat
	));
	Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_SEARCH_FORM, $searchFormParam);
	// Add custom hidden fields
	$tpl_content->addvar("SEARCH_PREPEND", $searchFormParam->getParam("customContentPrepend"));
	$tpl_content->addvar("SEARCH_HIDDEN", $searchFormParam->getParam("customFieldsHidden"));
	$tpl_content->addvar("SEARCH_APPEND", $searchFormParam->getParam("customContentAppend"));
	$tpl_content->addvar("SEARCH_SCRIPTS", $searchFormParam->getParam("customScripts"));
	// Write cache
	$cacheContent = $tpl_content->process(false);
	if ($cacheLifetime > 0 || true ) {
		if (!is_dir($ab_path."cache/marktplatz/search")) {
			// Cache Verzeichnis erstellen
			mkdir($ab_path."cache/marktplatz/search", 0777, true);
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
$tpl_content->isTemplateCached = TRUE;
// Default values
$arFieldValues = array(
	"FK_COUNTRY"    => null    // Keine vorauswahl
);
// Search values
if (!empty($search_hash) && ($search_hash != "{SEARCH_HASH}")) {
	$arSearchValues = unserialize(
		$db->fetch_atom("SELECT S_STRING FROM `searchstring` WHERE `QUERY`='" . mysql_escape_string($search_hash) . "'")
	);
	if (is_array($arSearchValues)) {
		$arFieldValues = array_merge($arFieldValues, $arSearchValues);
		// TODO: Optimieren
		$arFieldValues = array_merge($arFieldValues, array_flatten($arFieldValues));
		$arFieldValues = array_merge($arFieldValues, array_flatten($arFieldValues, true));
	}
} else if (!empty($_POST)) {
	$arFieldValues = array_merge($arFieldValues, $_POST);
	// TODO: Optimieren
	$arFieldValues = array_merge($arFieldValues, array_flatten($arFieldValues));
	$arFieldValues = array_merge($arFieldValues, array_flatten($arFieldValues, true));
}
// Add to template
$tpl_content->addvars($arFieldValues);
#die($tpl_content->process(true));