<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $id_kat;

require_once $ab_path.'sys/lib.ad_constraint.php';

//////////////// IMENSO //////////////////////
//include_once $GLOBALS["ab_path"]."sys/MicrosoftTranslator.php";
include_once $GLOBALS["ab_path"]."sys/GoogleTranslator.php";

// Einstellungen
$tpl_content->addvar("USE_HERSTELLER", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED']);

// Kategorie
include_once "sys/lib.shop_kategorien.php";
$kat = new TreeCategories("kat", 1);
$id_kat_root = $kat->tree_get_parent();
$id_kat = ($ar_params[1] ? $ar_params[1] : $id_kat_root);
$search_mode = $ar_params[2];
$search_hash = $ar_params[3];
if (empty($search_hash) && ($id_kat == $id_kat_root) && ($nar_systemsettings['MARKTPLATZ']['CATEGORY_ROOT'] > 0)) {
    $id_kat = $nar_systemsettings['MARKTPLATZ']['CATEGORY_ROOT'];
    $row_kat = $kat->element_read($id_kat);
    $tpl_content->addvars($row_kat);
    die(forward( $tpl_content->tpl_uri_action("marktplatz,{ID_KAT},{urllabel(V1)}") ));
}
$row_kat = $kat->element_read($id_kat);
if (empty($row_kat)) {
	header("HTTP/1.0 404 Not Found");
	$tpl_content->addvar("not_found", 1);
	return;
} else {
	$tpl_content->addvars($row_kat, "KAT_");
}
$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
$tpl_content->addvar("ID_KAT", $id_kat);
// Kategorie - Title/Meta
if (!empty($row_kat['V2'])) {
	$tpl_main->vars['pagetitle'] = $row_kat['V2']." - ".$tpl_main->vars['pagetitle'];
}
if (!empty($row_kat['META'])) {
	$tpl_main->vars['metatags'] = $row_kat['META'];
}

// Darstellung
$fallbackMarketplaceView = (in_array($nar_systemsettings['MARKTPLATZ']['LISTING_STD_VIEW'], array('BOX', 'LIST')))?$nar_systemsettings['MARKTPLATZ']['LISTING_STD_VIEW']:'BOX';
$defaultMarketplaceView['viewType'] = ($_SESSION['defaultMarketplaceView'][$id_kat]['viewType'])?($_SESSION['defaultMarketplaceView'][$id_kat]['viewType']):$fallbackMarketplaceView;
$defaultMarketplaceView['sortBy'] = ($_SESSION['defaultMarketplaceView'][$id_kat]['sortBy'])?($_SESSION['defaultMarketplaceView'][$id_kat]['sortBy']):'RUNTIME';
$defaultMarketplaceView['sortDir'] = ($_SESSION['defaultMarketplaceView'][$id_kat]['sortDir'])?($_SESSION['defaultMarketplaceView'][$id_kat]['sortDir']):'DESC';

// Parameter
$sort_by = strtoupper($ar_params[4] ? $ar_params[4] : $defaultMarketplaceView['sortBy']);
$sort_dir = strtoupper($ar_params[5] ? $ar_params[5] : $defaultMarketplaceView['sortDir']);
$disableSort = FALSE;
$curpage = ($ar_params[6] ? $ar_params[6] : $ar_params[6]=1);
$viewTypeList = array('LIST' => array('perpage' => 25, 'template' => 'marktplatz.row.htm'), 'BOX' => array('perpage' => 24, 'template' => 'marktplatz.row_box.htm'));
$viewTypeListJsonLD = array('LIST_JSON_LD' => array('perpage' => 10, 'template' => 'marktplatz.row.json_ld.htm'), 'BOX' => array('perpage' => 24, 'template' => 'marktplatz.row_box.json_ld.htm'));
$viewType = strtoupper(($ar_params[7] && array_key_exists($ar_params[7], $viewTypeList)) ? $ar_params[7] : $defaultMarketplaceView['viewType']);

// Darstellung in Session speichern
$_SESSION['defaultMarketplaceView'][$id_kat] = array(
    'viewType' => $viewType,
    'sortBy' => $sort_by,
    'sortDir' => $sort_dir
);

$searchQuery = null;
$searchData = array();
if ($search_mode == "Suchergebniss") {
	// Get search parameters
	$searchData = Rest_MarketplaceAds::getSearchData($search_hash);
	$GLOBALS['SEARCHED'] = $searchData;
	// Artikel-Ansicht erzwingen falls Suche ausgeführt wurde
	$row_kat["LU_KATART"] = Api_LookupManagement::getInstance($db)->readIdByValue("KATART", "STANDARD");
	// Set Category for Main Top Search
	if($row_kat['PARENT'] <= 1) {
		$GLOBALS['SEARCHED']['FK_KAT'] = $id_kat;
	} else {
		require_once("sys/lib.pub_kategorien.php");
		$kat_cache = new CategoriesCache();
		$mainCategory = array_shift($kat_cache->kats_read_path($id_kat));
		$GLOBALS['SEARCHED']['FK_KAT'] = $mainCategory['ID_KAT'];
	}
	// Suchparameter/Ariadne-Faden in das Template einfügen
	$tpl_main->addvars($searchData);
	$tpl_content->addvar("ariane", "<strong>Suchergebnisse</strong>");
	$tpl_content->addvar("SEARCH_HASH", $search_hash);
} else {
	$searchData["FK_KAT"] = $id_kat;
	// Kategorien/Ariadne-Faden in das Template einfügen
    $cachefile = "cache/marktplatz/ariane_".$s_lang.".".$id_kat.".htm";
    $cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
    $modifyTime = @filemtime($cacheFile);
    $diff = ((time()-$modifyTime)/60);
    if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile)) {
		require_once("sys/lib.pub_kategorien.php");
		$kat_cache = new CategoriesCache();
		$kat_cache->cacheKatAriane($id_kat);
	}
	$tpl_content->addvar("ariane", $ffile = file_get_contents($cachefile));
	$tpl_main->addvar("SKIN_KAT_PATH", $ffile);
}

// Kategorie/Darstellung in das Template einfügen
$strKatart = Api_LookupManagement::getInstance($db)->readValueById($row_kat["LU_KATART"]);
// Plugin event
$eventMarketViewParams = new Api_Entities_EventParamContainer(array(
	"categoryId"		=> $id_kat,
	"categoryRow"		=> $row_kat,
	"template"			=> $tpl_content,
	"viewType"			=> $strKatart
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_VIEW, $eventMarketViewParams);
if ($eventMarketViewParams->isDirty()) {
	$strKatart = $eventMarketViewParams->getParam("viewType");
	$row_kat = $eventMarketViewParams->getParam("categoryRow");
}

$tpl_main->addvar("KAT_SEL_LFT", $row_kat["LFT"]);
$tpl_main->addvar("KAT_SEL_RGT", $row_kat["RGT"]);
$tpl_content->addvars($row_kat);
$tpl_content->addvar("LU_KATART_".$strKatart, 1);

if ($strKatart == "STANDARD") {
	/*
	 * REGULÄRE ARTIKEL-DARSTELLUNG
	 */
	// Limit abfragen
	$limitCount = $viewTypeList[$viewType]['perpage']; // Elemente pro Seite
	$limitOffset = min((($curpage-1)*$limitCount), 200000);
	// Query erzeugen und parameter setzen
	$searchData["MANUAL_GROUPING"] = true;
	$searchQuery = Ad_Marketplace::getQueryByParams($searchData);
	// Sort feld übersetzen
	$sort_by_sql = $sort_by;
	switch ($sort_by) {
		case "RUNTIME":
			$sort_by_sql = $searchQuery->getDataTable()->getTableIdent().".STAMP_START";
			break;
	}
	$arSortFields = [
		"B_TOP_LIST"	=> "DESC"		
	];
	if (!empty($searchData["SEARCH_TEXT_FULL"])) {
		$arSortFields["SEARCH_RELEVANCE"] = "DESC";
	}
	$arSortFields[$sort_by_sql] = $sort_dir;
	$arSortFields["a.STAMP_START"] = "DESC";
	$arSortFields["ID_AD"] = "DESC";
	$searchQuery->addSortFields($arSortFields);
	// Benötigte Felder selektieren
	$searchQuery->addField("ID_AD");
	//Ad_Marketplace::addQueryFieldsByTemplate($searchQuery, $viewTypeList[$viewType]["template"]);
	$templateRowFile = "tpl/".$s_lang."/".$viewTypeList[$viewType]["template"];
	// Limit/Offset setzen
	$searchQuery->setLimit($limitCount, $limitOffset);
	// Plugin event
	$eventMarketListParams = new Api_Entities_EventParamContainer(array(
		"language"						=> $s_lang,
		"idCategory"					=> $id_kat,
		"table"							=> $kat_table,
		"template"						=> $tpl_content,
		"templateRow"					=> $templateRowFile,
		"searchActive"					=> ($search_mode == "Suchergebniss"),
		"searchHash"					=> $search_hash,
		"searchData"					=> $searchData,
		"groupByProduct"				=> true,
		"query"							=> $searchQuery,
		"queryMasterPrefix"				=> ($kat_table == "ad_master" ? "a" : "adt")
	));
	Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_LIST_QUERY, $eventMarketListParams);
	if ($eventMarketListParams->isDirty()) {
		$templateRowFile = $eventMarketListParams->getParam("templateRow");
		$searchQuery = $eventMarketListParams->getParam("query");
	}
	$adsCount = 0;
	if ((count($searchData) == 2) && array_key_exists("FK_KAT", $searchData)) {
		include_once "sys/lib.pub_kategorien.php";
		$kat_cache = new CategoriesCache();
		$adsCount = $kat_cache->getCacheArticleCount($searchData["FK_KAT"]);
	}
	// Ergebnis abfragen
	#die($searchQuery->getQueryString());
	$adsList = array();
	if ($adsCount < 10000) {
		$adsCount = $searchQuery->fetchCount();
	}
	if ($adsCount > 0) {
		$adsList = $searchQuery->fetchTable();
		$adsList = Api_Entities_MarketplaceArticle::toAssocList(
			Api_Entities_MarketplaceArticle::createMultipleFromMinimalArray($adsList)
		);
		// Plugin event
		$eventMarketListParams = new Api_Entities_EventParamContainer(array(
			"language"						=> $s_lang,
			"idCategory"					=> $id_kat,
			"table"							=> $kat_table,
			"template"						=> $tpl_content,
			"templateRow"					=> $templateRowFile,
			"groupByProduct"				=> true,
			"list"							=> $adsList
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_LIST_POST_PROCESSING, $eventMarketListParams);
		if ($eventMarketListParams->isDirty()) {
			$templateRowFile = $eventMarketListParams->getParam("templateRow");
			$adsList = $eventMarketListParams->getParam("list");
		}
		$i = 0;
		foreach ($adsList as $adIndex => $adData) {
			$fkey_user = $adData["FK_USER"];
			if ($fkey_user > 0) {
				$q = 'SELECT v.LOGO
						FROM vendor v
						WHERE v.FK_USER = ' . $fkey_user;
				$vendor_logo = $db->fetch_atom( $q );
				if ( $vendor_logo ) {
					$vendor_logo2 = '/cache/vendor/logo/'.$vendor_logo;
				}
				$adsList[$adIndex]["VENDOR_LOGO"] = $vendor_logo2;
			}
			$i++;
			#die(var_dump($adsList[$adIndex]));
			$adsList[$adIndex]["ID_AD_MASTER"] = $adsList[$adIndex]["ID_AD"];
			$adObject = Api_Entities_MarketplaceArticle::createFromMasterArray($adsList[$adIndex]);
			// Shorten description
			$description = strip_tags(html_entity_decode($adsList[$adIndex]['BESCHREIBUNG']));
			if (strlen($description) > 250) {
				$description = substr($description, 0, 250)." ...";
			}
			$adsList[$adIndex]['BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $description);
			// Add constraint mappings
			Rest_MarketplaceAds::extendAdDetailsSingle($adsList[$adIndex]);
		}
		// Suchvorschlag eintragen/updaten
		if (($curpage == 1) && !empty($searchData["PRODUKTNAME"])) {
			$search_offer = $db->fetch1("SELECT * FROM `ad_search_offer` WHERE SEARCHTEXT='".mysql_real_escape_string($searchData["PRODUKTNAME"])."'");
			if (empty($search_offer)) {
				// Suchvorschlag speichern
				$db->querynow("INSERT INTO `ad_search_offer`
									(`SEARCHTEXT`, `BF_LANG`, `HITS`)
								VALUES
									('".mysql_real_escape_string($searchData["PRODUKTNAME"])."', ".$GLOBALS["langval"].", 1)");
			} else {
				$search_offer["HITS"] += 1;
				$db->update("ad_search_offer", $search_offer);
			}
		}
	}
	// Ergebnis ins Template einfügen
	$tpl_content->isTemplateRecursiveParsable = TRUE;
	$tpl_content->isTemplateCached = TRUE;






	//////////////// IMENSO //////////////////////
	if($s_lang == 'en' )
	{
		for($i = 0 ; $i < count( $adsList ) ; $i++ )  
		{
			$table = "hdb_table_".$adsList[$i]['AD_TABLE'] ;
			$column = strtoupper('ID_HDB_TABLE_'.$adsList[$i]['AD_TABLE']);
			$id = $adsList[$i][$column];
			$PRODUKTNAME_EN = $adsList[$i]['PRODUKTNAME_EN'];
			if( $PRODUKTNAME_EN == '')
			{	
			 	$PRODUKTNAME_EN = translateText( $adsList[$i]['PRODUKTNAME'] );
				$db->querynow("update $table set PRODUKTNAME_EN ='".$PRODUKTNAME_EN."'
			      	where $column =".$id );
			}
		
			$BESCHREIBUNG_EN = $adsList[$i]['BESCHREIBUNG_EN'];
			if( $BESCHREIBUNG_EN == '')
			{
				$BESCHREIBUNG_EN = translateText( $adsList[$i]['BESCHREIBUNG'] );
				$db->querynow("update $table set BESCHREIBUNG_EN ='".$BESCHREIBUNG_EN."'
			      	where $column =".$id );
			}

			$FULL_PRODUKTNAME_EN = $adsList[$i]['FULL_PRODUKTNAME_EN'];
			if( $FULL_PRODUKTNAME_EN == '')
			{
				$FULL_PRODUKTNAME_EN = translateText( $adsList[$i]['FULL_PRODUKTNAME'] );
				$db->querynow("update $table set FULL_PRODUKTNAME_EN ='".$FULL_PRODUKTNAME_EN."'
			      	where $column =".$id );
			}
			$adsList[$i]['FULL_PRODUKTNAME'] = $FULL_PRODUKTNAME_EN;
			$adsList[$i]['PRODUKTNAME'] = $PRODUKTNAME_EN;
			$adsList[$i]['BESCHREIBUNG'] = $BESCHREIBUNG_EN;
		}
	}

	$tpl_content->addlist("liste", $adsList, $templateRowFile);
	// Seitenzähler hinzufügen
	$tpl_content->addvar("ALL_ADS", $adsCount);
  	$tpl_content->addvar("CURPAGE", $curpage);
	$tpl_content->addvar("pager", htm_browse_extended($adsCount, $curpage, "marktplatz,".$ar_params[1].",".$ar_params[2].",".$ar_params[3].",".$sort_by.",".$sort_dir.",{PAGE},".$viewType, $limitCount));
	// Sortierung
    #$tpl_content->addvars($sort_vars);
	$tpl_content->addvar('CUR_ORDER_'.$sort_by.'_'.$sort_dir, 1);
    if ($curpage == 1) {
        // Regionen
        $tpl_content->addvar('SHOW_MAP_REGIONS', $row_kat["MAP_REGIONS"]);
        // Google-Map
        if($row_kat['MAP_VISIBLE']) {
            if (((int)$row_kat['MAP_SEARCH_VISIBLE'] === 0) || ($searchData["LU_UMKREIS"] > 0)) {
                $tpl_content->addvar('SHOW_MAP', true);
            }
        }
    }
	// Darstellung
	$tpl_content->addvar('VIEW_TYPE', $viewType);
	$tpl_content->addvar('VIEW_TYPE_LIST', $viewType == 'LIST');
	$tpl_content->addvar('VIEW_TYPE_BOX', $viewType == 'BOX');
	// Mehr Einstellungen
	$tpl_content->addvar('SYSTEM_ALLOW_COMMENTS', $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_AD']);
 	$tpl_content->addvar("DAYS_ADS_NEW", $nar_systemsettings["MARKTPLATZ"]["DAYS_ADS_NEW"]);
}

?>
