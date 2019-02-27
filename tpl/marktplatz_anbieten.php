<?php

$articleId = ($ar_params[1] > 0 ? (int)$ar_params[1] : ($_REQUEST["ID_AD_MASTER"] ? (int)$_REQUEST["ID_AD_MASTER"] : 0));
$article = Api_Entities_MarketplaceArticle::getById($articleId);
$npage = ($_REQUEST["npage"] > 0 ? (int)$_REQUEST["npage"] : 1);
$perpage = 5;

if ((!$article instanceof Api_Entities_MarketplaceArticle) || !$article->isOnline()) {
	header("HTTP/1.0 404 Not Found");
	$tpl_content->addvar("not_found", 1);
	return;
}

if (array_key_exists("ajax", $_REQUEST)) {
  $currency = $nar_systemsettings['MARKTPLATZ']['CURRENCY'];
  switch ($_REQUEST["ajax"]) {
    case "autocompleteArticle":
      if (!$uid) {
        // ERROR MESSAGE!
        die("Not logged in!");
      }
      $arResult = array();
      $phrase = (!empty($_REQUEST["phrase"]) ? $_REQUEST["phrase"] : null);
      $searchParams = [ "FK_USER" => $uid, "SALE_NO_REQUEST" => true ];
      if (!empty($_POST["EXCLUDE_ADS"])) {
        $excludeAds = [];
        foreach ($_POST["EXCLUDE_ADS"] as $adId) {
          $excludeAds[] = (int)$adId;
        }
        $searchParams["ID_AD_MASTER_NOT_IN"] = "(".implode(", ", $excludeAds).")";
      }
      if ($phrase !== null) {
        // Query by title / id
        if (preg_match("/^[0-9]+$/", $phrase)) {
          // Query by id
          $searchParams["SEARCH_TEXT_ID"] = $phrase;
        } else {
          // Query by title
          $searchParams["PRODUKTNAME"] = $phrase;
        }
      }
      $searchQuery = Rest_MarketplaceAds::getQueryByParams($searchParams);
      $searchQuery->addField("ID_AD_MASTER");
      $searchQuery->addField("PRODUKTNAME");
      $searchQuery->addField("MENGE");
      $searchQuery->addField("PREIS");
      $searchQuery->addField("IMG_DEFAULT_SRC");
      $searchQuery->setLimit($perpage, $perpage * ($npage-1));
      $count = $searchQuery->fetchCount();
      $arResult["pager"] = htm_browse_extended($count, $npage, "#", $perpage);
      $arResult["list"] = $searchQuery->fetchTable();
      $tplRow = new Template("tpl/" . $s_lang . "/marktplatz_anbieten.item.htm");
      foreach ($arResult["list"] as $itemIndex => $itemDetails) {
        if (!empty($itemDetails["IMG_DEFAULT_SRC"])) {
          $arResult["list"][$itemIndex]["IMG_DEFAULT_SRC"] = $tpl_content->tpl_uri_baseurl($itemDetails["IMG_DEFAULT_SRC"]);
        }
        $tplRow->vars = $arResult["list"][$itemIndex];
        $tplRow->addvar('CURRENCY_DEFAULT', $currency);
        $arResult["list"][$itemIndex]["TPL_ROW"] = $tplRow->process();
      }

      header("Content-Type: application/json");
      die(json_encode($arResult));
  }
}

$tpl_content->addvar("ID_AD_MASTER", $articleId);
$tpl_content->addvars($article->getData_ArticleFull(), "AD_");

return;
die(var_dump($article));