<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.ads.php";
//////////////// IMENSO //////////////////////
include_once $GLOBALS["ab_path"]."sys/MicrosoftTranslator.php";
/**
 * @var string $return
 *
 * Rückgabe ans Template
 */

$return = "Dev hat return vergessen :-)";
$return_json = array();

session_write_close();

$searchData = array_merge($_GET,$_POST);
if (array_key_exists("PRODUKTNAME", $searchData)) {
    $searchData["PRODUKTNAME"] = trim($searchData["PRODUKTNAME"]);
}

$fallbackMarketplaceView = '';
$viewType = '';
$sortBy = '';
$sortDir = '';

if ( isset($_POST["SEARCH_TYPE"]) && $_POST["SEARCH_TYPE"] == "1" ) {

	$fallbackMarketplaceView = (in_array($nar_systemsettings['MARKTPLATZ']['LISTING_STD_VIEW'], array('BOX', 'LIST')))?$nar_systemsettings['MARKTPLATZ']['LISTING_STD_VIEW']:'BOX';
	$viewType = ($_SESSION['defaultMarketplaceView'][$return_json["ID_KAT"]]['viewType'])?($_SESSION['defaultMarketplaceView'][$return_json["ID_KAT"]]['viewType']):$fallbackMarketplaceView;
	$sortBy = ($_SESSION['defaultMarketplaceView'][$return_json["ID_KAT"]]['sortBy'])?($_SESSION['defaultMarketplaceView'][$return_json["ID_KAT"]]['sortBy']):'RUNTIME';
	$sortDir = ($_SESSION['defaultMarketplaceView'][$return_json["ID_KAT"]]['sortDir'])?($_SESSION['defaultMarketplaceView'][$return_json["ID_KAT"]]['sortDir']):'DESC';
	$per_page_count = 10;

	if ( $viewType == "LIST" ) {
		$per_page_count = 10;
	}
	else if ( $viewType == "LIST" ) {
		$per_page_count = 24;
	}
	$search = AdManagment::generateSearchString($searchData,$per_page_count);
}
else {
	$search = AdManagment::generateSearchString($searchData);
}


$urlAgentObject = null;
$urlAgent = $tpl_content->tpl_uri_action("ad_agent,add,".$search['HASH'], false, null, $urlAgentObject);

$urlResultObject = null;
$urlResult = $tpl_content->tpl_uri_action("marktplatz,".$search['ID_KAT'].",Suchergebniss,".$search['HASH'], false, null, $urlResultObject);

// Plugin event
$eventAdSearchUrlParams = new Api_Entities_EventParamContainer(array(
    "language"			=> $s_lang,
    "categoryId"		=> $search['ID_KAT'],
    "resultCount"		=> $search['RESULT_COUNT'],
    "searchHash"		=> $search['HASH'],
    "searchData"		=> $searchData,
    "urlAgent"			=> $urlAgentObject,
    "urlResult"			=> $urlResultObject
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_SEARCH_URL, $eventAdSearchUrlParams);
if ($eventAdSearchUrlParams->isDirty()) {
    /** @var Api_Entities_URL $urlAgentObject */
    $urlAgentObject = $eventAdSearchUrlParams->getParam("urlAgent");
    $urlAgent = $tpl_content->tpl_uri_action($urlAgentObject);
    /** @var Api_Entities_URL $urlResultObject */
    $urlResultObject = $eventAdSearchUrlParams->getParam("urlResult");
    $urlResult = $tpl_content->tpl_uri_action($urlResultObject);
}

$return_json["ID_KAT"] = $search['ID_KAT'];
$return_json["COUNT"] = $search['RESULT_COUNT'];
$return_json["HASH"] = $search['HASH'];
$return_json["AGENTURL"] = $urlAgent;
$return_json["SEARCHURL"] = $urlResult;
$return_json['QUE'] = $search['SQLQUERY'];

if (!empty($search['RESULT_LIST'])) {
	$execute_template = true;
}
else if ( isset($_POST["SEARCH_TYPE"]) && $_POST["SEARCH_TYPE"] == "1" ) {
	$execute_template = true;
}

if ( $execute_template ) {
	$adsList = $search['RESULT_LIST'];

	foreach ($adsList as $adIndex => $adData) {
		/*$q = 'SELECT DATEDIFF(NOW(),a.STAMP_START) as RUMTIME_DAYS_GONE
		        FROM ad_master a
		        WHERE a.ID_AD_MASTER = ' . $adData["ID_AD"];
		$adsList[$adIndex]["RUNTIME_DAYS_GONE"] = $db->fetch_atom( $q );*/
		//$dataTable->addField(null, null, "DATEDIFF(NOW()," . $masterTableShortcut . ".STAMP_START)", "RUNTIME_DAYS_GONE");
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

		/*$q = 'SELECT c.CODE
					FROM vendor v
					INNER JOIN country c
					ON v.FK_USER = ' . $fkey_user .'
					AND c.ID_COUNTRY = v.FK_COUNTRY';

		$country_code = $db->fetch_atom( $q );
		$adsList[$adIndex]["COUNTRY_CODE"] = $country_code;*/

		$i++;
		#die(var_dump($adsList[$adIndex]));
		$adObject = Api_Entities_MarketplaceArticle::createFromMasterArray($adsList[$adIndex]);
		$adsList[$adIndex] = array_merge($adsList[$adIndex], $adObject->toAssoc());
		// Shorten description
		$description = strip_tags(html_entity_decode($adsList[$adIndex]['BESCHREIBUNG']));
		if (strlen($description) > 250) {
			$description = substr($description, 0, 250)." ...";
		}
		$adsList[$adIndex]['BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $description);
		// Add constraint mappings
		Rest_MarketplaceAds::extendAdDetailsSingle($adsList[$adIndex]);
	}
	
	$templateRowFile = "tpl/".$s_lang."/marktplatz.row.htm";
	if ( $viewType == "LIST" ) {
		$templateRowFile = "tpl/".$s_lang."/marktplatz.row.htm";
	} else if ( $viewType == "BOX" ) {
		$templateRowFile = "tpl/".$s_lang."/marktplatz.row_box.htm";
	}

	$tplList = new Template("tpl/".$s_lang."/presearch_ajax_articles.htm");
	$tplList->isTemplateRecursiveParsable = TRUE;
	$tplList->isTemplateCached = TRUE;
	
	
	
	// Plugin event
	$eventMarketListParams = new Api_Entities_EventParamContainer(array(
		"language"						=> $s_lang,
		"idCategory"					=> $id_kat,
		"table"							=> $kat_table,
		"template"						=> $tplList,
		"templateRow"					=> $templateRowFile,
		"searchActive"					=> true,
		"searchHash"					=> $search_hash,
		"searchData"					=> $searchData,
		"query"							=> null,
		"queryMasterPrefix"				=> ($kat_table == "ad_master" ? "a" : "adt")
	));
	Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_LIST_QUERY, $eventMarketListParams);
	if ($eventMarketListParams->isDirty()) {
		$templateRowFile = $eventMarketListParams->getParam("templateRow");
	}
	
	
	//.............
	$tplList->addvar("ALL_ADS",$return_json['COUNT']);
	$tplList->addvar("DAYS_ADS_NEW", $nar_systemsettings["MARKTPLATZ"]["DAYS_ADS_NEW"]);

	//////////////// IMENSO //////////////////////
	if($s_lang == 'en' )
	{
		for($i = 0 ; $i < count( $adsList ) ; $i++ )  
		{
			$PRODUKTNAME_EN = $adsList[$i]['PRODUKTNAME_EN'];
			if( $PRODUKTNAME_EN == '')
			{
				$PRODUKTNAME_EN = Translate( $adsList[$i]['PRODUKTNAME'] );
				$db->querynow("update ad_master set PRODUKTNAME_EN ='".$PRODUKTNAME_EN."'
			      	where ID_AD_MASTER=". $adsList[$i]['ID_AD_MASTER'] );
			}
			$BESCHREIBUNG_EN = $adsList[$i]['BESCHREIBUNG_EN'];
			if( $BESCHREIBUNG_EN == '')
			{
				$BESCHREIBUNG_EN = Translate( $adsList[$i]['BESCHREIBUNG'] );
				$db->querynow("update ad_master set BESCHREIBUNG_EN ='".$BESCHREIBUNG_EN."'
			      	where ID_AD_MASTER=". $adsList[$i]['ID_AD_MASTER'] );
			}
			$adsList[$i]['FULL_PRODUKTNAME'] = $PRODUKTNAME_EN;
			$adsList[$i]['PRODUKTNAME'] = $PRODUKTNAME_EN;
			$adsList[$i]['BESCHREIBUNG'] = $BESCHREIBUNG_EN;
		}
	}
	

	$tplList->addlist("liste", $adsList, $templateRowFile);
	$tplList->addvar("VIEW_TYPE_".$viewType,1);
	$tplList->addvar("ID_KAT",$search['ID_KAT']);
	$tplList->addvar("SEARCH_HASH",$return_json["HASH"]);
	$tplList->addvar("VIEW_TYPE",$viewType);
	$tplList->addvar("CUR_ORDER_".$sortBy."_".$sortDir,1);
	$curpage = 1;

	$tplList->addvar("pager", htm_browse_extended(
		$return_json['COUNT'],
		$curpage,
		"marktplatz,".$return_json["ID_KAT"].","."Suchergebniss".",".$return_json["HASH"].",".$ar_params[4].",".$ar_params[5].",{PAGE},".$viewType,
		10
	));
	$tplList->addvar("CURPAGE",$curpage);
	$return_json['LIST'] = $tplList->process();
}

header('Content-type: application/json');
die(json_encode($return_json));
//$tpl_content->addvar("NUM_ERGS", $return);

?>
