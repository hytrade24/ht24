<?php


$categoryBaseId = 64584;

$categoryId = (array_key_exists(2, $ar_params) && ($ar_params[2] > 0) ? (int)$ar_params[2] : $categoryBaseId);
$categoryDetails = Api_StringManagement::getInstance($db)->readById("kat", $categoryId);

$curpage = (array_key_exists(3, $ar_params) && ($ar_params[3] > 0) ? (int)$ar_params[3] : 1);

$limitCount = 20;
$limitOffset = ($curpage - 1) * $limitCount;

$tpl_content->addvar("ID_KAT", $categoryId);
$tpl_content->addvars($categoryDetails, "KAT_");

$manufacturerId = (int)$ar_params[1];
$manufacturerDetails = $db->fetch1("SELECT * FROM `manufacturers` WHERE ID_MAN=".(int)$manufacturerId);
if (!is_array($manufacturerDetails)) {
    die(forward(
        $tpl_content->tpl_uri_action("hersteller".($categoryId > 0 ? ",".$categoryId : ""))
    ));
}

$tpl_content->addvars($manufacturerDetails, "MAN_");

$manufacturersDetailsLang = Api_StringManagement::getInstance($db)->readRaw("man_detail", "t.FK_MAN=".$manufacturerId);
if (is_array($manufacturersDetailsLang)) {
    $tpl_content->addvars($manufacturersDetailsLang, "MAN_");
}

if ($categoryId != $categoryBaseId) {
    require_once $ab_path."sys/lib.ads.php";
    $searchData = array("FK_KAT" => $categoryId, "FK_MAN" => $manufacturerId);
    $search = AdManagment::generateSearchString($searchData);
    // Query products
    $searchQuery = Rest_MarketplaceAds::getProductQueryByParams($searchData);
    // Add fields
	Ad_Marketplace::addQueryFieldsByTemplate($searchQuery, "hersteller_details.product.htm");
	$searchQuery->addField("ARTICLE_COUNT");
	$searchQuery->addField("ID_PRODUCT");
	$searchQuery = new Api_DataTableQueryIntermediate($db, $searchQuery, array("ID_PRODUCT" => NULL));
	$searchQuery->addSortField("ARTICLE_COUNT", "DESC");
	$searchQuery->addGroupField("ID_PRODUCT");
	$searchQuery->setLimit($limitCount, $limitOffset);
    
	/*
	$searchQuery = Ad_Marketplace::getQueryByParams($searchData);
	$searchQuery->addSortFields(array(
		"B_TOP_LIST"	=> "DESC",
		"a.STAMP_START"	=> "DESC",
		"ID_AD"			=> "DESC"
	));
	// Benötigte Felder selektieren
	Ad_Marketplace::addQueryFieldsByTemplate($searchQuery, "hersteller_details.product.htm");
	$templateRowFile = "tpl/".$s_lang."/hersteller_details.product.htm";
	// Limit/Offset setzen
	$searchQuery->setLimit($limitCount, $limitOffset);
	// Plugin event
	$eventMarketListParams = new Api_Entities_EventParamContainer(array(
		"language"						=> $s_lang,
		"idCategory"					=> $categoryId,
		"table"							=> $categoryDetails["KAT_TABLE"],
		"template"						=> $tpl_content,
		"templateRow"					=> $templateRowFile,
		"searchActive"					=> true,
		"searchHash"					=> $search["HASH"],
		"searchData"					=> $searchData,
		"query"							=> $searchQuery,
		"queryMasterPrefix"				=> ($kat_table == "ad_master" ? "a" : "adt")
	));
	Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_LIST_QUERY, $eventMarketListParams);
	if ($eventMarketListParams->isDirty()) {
		$templateRowFile = $eventMarketListParams->getParam("templateRow");
		$searchQuery = $eventMarketListParams->getParam("query");
	}
	*/
	// Ergebnis abfragen
	#die($searchQuery->getQueryString());
	$arProducts = array();
	$productCount = $searchQuery->fetchCount();
	if ($productCount > 0) {
        $arProducts = $searchQuery->fetchTable();
    }
    $tpl_content->addlist("liste", $arProducts, "tpl/".$s_lang."/hersteller_details.product.htm");
	$tpl_content->addvar("pager", htm_browse_extended($productCount, $curpage, "hersteller_details,".$manufacturerId.",".$categoryId.",{PAGE}", $limitCount));
	$tpl_content->addvar("PRODUCT_COUNT", $productCount);
	$tpl_content->addvar("PRODUCT_LIMIT", $limitCount);
	$tpl_content->addvar("SEARCH_HASH", $search["HASH"]);
	$tpl_content->addvar("SEARCH_COUNT", $search["RESULT_COUNT"]);
} else {
	$arCategories = $db->fetch_table("
  		select
  			t.*, s.V1, s.V2
  		from `kat` t
  		left join `man_group_category` mgc on mgc.FK_KAT=t.ID_KAT
  		left join `man_group_mapping` mgm on mgm.FK_MAN_GROUP=mgc.FK_MAN_GROUP
        left join `string_kat` s on s.S_TABLE='kat'
  			and s.FK=t.ID_KAT
  			and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
  		where
  			t.PARENT=".(int)$categoryBaseId." AND mgm.FK_MAN=".(int)$manufacturerId);
	if (count($arCategories) == 1) {
	    die(forward( $tpl_content->tpl_uri_action("hersteller_details,".$manufacturerId.",".(int)$arCategories[0]["ID_KAT"]) ));
    }
    $tpl_content->addlist("categories", $arCategories, "tpl/".$s_lang."/hersteller_details.category.htm");
}