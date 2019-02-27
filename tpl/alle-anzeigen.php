<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ad_constraint.php';


function killbb(&$row,$i)
{
	//$row['DSC'] = strip_tags($row['DSC']);
	$row['BESCHREIBUNG'] = substr(strip_tags($row['BESCHREIBUNG']), 0, 250);
	$row['BESCHREIBUNG'] = html_entity_decode(preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['BESCHREIBUNG']));
}

// Einstellungen
$tpl_content->addvar("USE_HERSTELLER", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED']);


$npage = ((int)$ar_params[1] ? (int)$ar_params[1] : 1);
$perpage = 20;
$limit = ($npage*$perpage)-$perpage;

// Limit abfragen
$limitCount = $perpage; // Elemente pro Seite
$limitOffset = (($npage-1)*$limitCount);
// Query erzeugen und parameter setzen
$searchQuery = Ad_Marketplace::getQueryByParams([]);
$searchQuery->addSortFields(array(
    "a.STAMP_START"	=> "DESC",
    "ID_AD"			=> "DESC"
));
// Benötigte Felder selektieren
Ad_Marketplace::addQueryFieldsByTemplate($searchQuery, "marktplatz.row.htm");
// Limit/Offset setzen
$searchQuery->setLimit($limitCount, $limitOffset);
// Plugin event
$eventMarketListParams = new Api_Entities_EventParamContainer(array(
    "language"						=> $s_lang,
    "idCategory"					=> $id_kat,
    "table"								=> $kat_table,
    "template"						=> $tpl_content,
    "searchActive"				=> ($search_mode == "Suchergebniss"),
    "searchHash"					=> $search_hash,
    "searchData"					=> $searchData,
    "query"								=> $searchQuery,
    "queryMasterPrefix"		=> ($kat_table == "ad_master" ? "a" : "adt")
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_LIST_QUERY, $eventMarketListParams);
// Ergebnis abfragen
#die($searchQuery->getQueryString());
$adsList = array();
$adsCount = $searchQuery->fetchCount();
if ($adsCount > 0) {
    $adsList = $searchQuery->fetchTable();
    Rest_MarketplaceAds::extendAdDetailsList($adsList);
}
    
$tpl_content->addvar("DAYS_ADS_NEW", $nar_systemsettings["MARKTPLATZ"]["DAYS_ADS_NEW"]);
$tpl_content->addlist("liste", $adsList, "tpl/".$s_lang."/marktplatz.row.htm", 'killbb');
$tpl_content->isTemplateRecursiveParsable = TRUE;
$tpl_content->isTemplateCached = TRUE;


$pager = htm_browse_extended($adsCount, $npage, "alle-anzeigen,{PAGE}", $perpage);
$tpl_content->addvar("pager", $pager);
$tpl_content->addvar("ALL_ADS", $adsCount);

?>