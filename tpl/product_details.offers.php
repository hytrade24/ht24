<?php

$articleId = ($tpl_content->vars["ID_HDB_PRODUCT"] ? (int)$tpl_content->vars["ID_HDB_PRODUCT"] : 0);
$categoryId = ($tpl_content->vars["ID_KAT"] ? (int)$tpl_content->vars["ID_KAT"] : 0);

$queryOffers = Rest_MarketplaceAds::getQueryByParams(array(
    "FK_KAT" => $categoryId, "FK_PRODUCT" => $articleId, "NO_GROUPING" => 1
));
$queryOffers->addField("ID_AD");
//Rest_MarketplaceAds::addQueryFieldsByTemplate($queryOffers, "product_details.offers.row.htm");
$queryOffers->addSortField("B_TOP_LIST", "DESC");

$arOffers = $queryOffers->fetchTable();
$arOffers = Api_Entities_MarketplaceArticle::toAssocList(
    Api_Entities_MarketplaceArticle::createMultipleFromMinimalArray($arOffers)
);
foreach ($arOffers as $offerIndex => $offerDetails) {
    $arOffers[$offerIndex]["IS_OWN"] = ($GLOBALS["uid"] == $offerDetails["FK_USER"]);
}
$tpl_content->addvar("CURRENCY_DEFAULT", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);
$tpl_content->addvar("DAYS_ADS_NEW", $nar_systemsettings["MARKTPLATZ"]["DAYS_ADS_NEW"]);
$tpl_content->addlist("liste", $arOffers, "tpl/".$s_lang."/product_details.offers.row.htm");
