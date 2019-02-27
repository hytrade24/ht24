<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ads.php';
require_once $ab_path.'sys/lib.ad_variants.php';
require_once $ab_path.'sys/lib.ad_payment_adapter.php';

$variants = AdVariantsManagement::getInstance($db);
$adPaymentAdapterManagement = AdPaymentAdapterManagement::getInstance($db);

function addbidinfo(&$row, $i)
{
	$row['BID_STATUS_'.$row['BID_STATUS']] = 1;
}

$id_ad = (int)$ar_params[1];
$id_ad_variant = (int)$ar_params[2];

$maxBidCount = $nar_systemsettings['MARKTPLATZ']['TRADE_BID_COUNT'];			// max Anzahl der Gegenvorschlägen
$maxBidUserCount = $nar_systemsettings['MARKTPLATZ']['TRADE_BID_USER_COUNT'];	// max Anzahl der eigenen Preisvorschläge

if($_POST['ID_AD']) {
	$id_ad = $_POST['ID_AD'];
}
if($_POST['ID_AD_VARIANT']) {
	$id_ad_variant = $_POST['ID_AD_VARIANT'];
}

$article = Api_Entities_MarketplaceArticle::getById($id_ad);

$query = "
	SELECT
			a.*,
			a.ID_AD_MASTER as ID_AD,
			a.BESCHREIBUNG AS DSC,
			m.NAME AS MANUFACTURER,
			(SELECT slang.V1 FROM `string` slang WHERE slang.FK=a.FK_COUNTRY
    			AND slang.BF_LANG='".$langval."' LIMIT 1) as LAND
		FROM
			`ad_master` a
		LEFT JOIN
			manufacturers m on a.FK_MAN=m.ID_MAN
		WHERE
			a.ID_AD_MASTER = ".$id_ad."
			AND (a.STATUS&3=1 OR a.FK_USER=".(int)$uid.") AND (a.DELETED=0)";
$ad = $db->fetch1($query);
$adVariant = $variants->getAdVariantDetailsById($id_ad_variant);
$adVariantFields = $variants->getAdVariantTextById($id_ad_variant);

// $tpl_content->addvar("my", $ad['FK_USER'] == $uid ? 1 : 0);
if(!empty($ad)) {
    $ad = array_merge($ad, $adVariant);
    if ($ad['FK_USER'] == $uid) {
        $tpl_content->addvar("my", 1);
        //die(forward("/my-pages/my-marktplatz-handeln.htm#ad".$id_ad));
    }
	// Variant
	$ar_variant_list = array();
	foreach ($adVariantFields as $index => $ar_current) {
		$value = $db->fetch_atom("SELECT sl.V1 FROM `liste_values` t
				LEFT JOIN `string_liste_values` sl
					ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
					AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
				WHERE t.ID_LISTE_VALUES=".$ar_current["ID_LISTE_VALUES"]);
		if ($value !== false) {
			$ar_variant_list[] = $value;
		} else {
			$ar_variant_list[] = $ar_current["VALUE"];
		}
	}
	$tpl_content->addvar("VARIANT", (empty($ar_variant_list) ? "" : implode(", ", $ar_variant_list)));

	$ar_userinfo = $db->fetch1("
		SELECT
			u.FIRMA AS ANBIETER_FIRMA,
			CONCAT(u.VORNAME, ' ', u.NACHNAME) AS ANBIETER_NAME,
			u.STRASSE AS ANBIETER_STRASSE,
			u.PLZ AS ANBIETER_PLZ,
			u.ORT AS ANBIETER_ORT,
			u.UST_ID AS ANBIETER_UMSTG,
			str.V1 AS ANBIETER_COUNTRY,
			DATE_FORMAT(u.STAMP_REG, '%Y') AS STAMP_REG,
			s.V1 AS UGROUP
		FROM
			`user` u
		LEFT JOIN
			string_usergroup s
			on s.S_TABLE='usergroup' and s.FK=u.FK_USERGROUP
			and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
		LEFT JOIN
			string str
			on str.S_TABLE='country' and str.FK=u.FK_COUNTRY
			and str.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
		WHERE
			u.ID_USER=".$ad["FK_USER"]);


    require_once 'sys/lib.ad_rating.php';
    $adRatingManagement = AdRatingManagement::getInstance($db);
    $ar_userinfo['SCHNITT'] = $adRatingManagement->getRatingByUserId($ad["FK_USER"]);

	$tpl_content->addvars($ar_userinfo);
	$ar_info = $db->fetch1("
		SELECT
			*,
			AD_AGB AS AGB,
			AD_WIDERRUF AS WIDERRUF
		FROM
			".$ad['AD_TABLE']."
		WHERE
			ID_".strtoupper($ad['AD_TABLE'])."=".$ad['ID_AD_MASTER']);
	$ar_info = array_merge($ar_info, $adVariant);
	if ($ad['VERKAUFSOPTIONEN'] == 5) {
		// Request / Gesuch
		$ar_info['TRADE'] = 1;
		$tpl_content->addvar('is_request', 1);
		$tpl_content->addvar('BID_AMOUNT', $ad["MENGE"]);
	}
	if (!$article->isTradeAllowed($uid)) {
		$tpl_content->addvar('not_found', 1);
	}
	else
	{
		### Vorschlag annehmen
		if($ar_params[3] == 'a' && (int)$ar_params[4]) {
			die(forward("/index.php?page=marktplatz_kaufen&ID_TRADE=".(int)$ar_params[4]."&FK_AD=".$ad['ID_AD']."&FK_AD_VARIANT=".$id_ad_variant."&tradeReq=1"));
		}
		### ggf canceln
		if($ar_params[3] == 'c' && (int)$ar_params[4]) {
			if (AdManagment::CancelTrade((int)$ar_params[4])) {
				$tpl_content->addvar('canceled', 1);
			}
		}
		$ad = array_merge($ad, $ar_info);
		### last bids
		if($uid != $ad['FK_USER'])
		{
			$liste_bid = $db->fetch_table("
				SELECT
					*,
					if(FK_USER_FROM = ".$uid.",1,0) AS OWN,
					(AMOUNT * BID) AS BID_FULL
				FROM
					trade
				WHERE
					FK_AD=".$ad['ID_AD']." AND FK_AD_VARIANT=".$ad['ID_AD_VARIANT']."
					AND ( FK_USER_TO = ".$uid." OR FK_USER_FROM = ".$uid." )
				ORDER BY
					STAMP_BID ASC,
					ID_TRADE ASC");
			$bid_count = count($liste_bid);
			$bid_active = false;
			$currentPaymentAdapter = 0;
			foreach ($liste_bid as $index => $ar_bid) {
				if ($ar_bid["BID_STATUS"] == 'ACTIVE') {
					$bid_active = true;
					$currentPaymentAdapter = $ar_bid['FK_PAYMENT_ADAPTER'];
				}
			}
			if ($bid_active) {
				$tpl_content->addvar("userInvoice_ReadOnly", 1);
				$tpl_content->addvar("userVersand_ReadOnly", 1);
				$tpl_content->addvar("userPaymentAdapter_ReadOnly", 1);
				$tpl_content->addvar("userPaymentAdapter_Value", $currentPaymentAdapter);

				$tpl_content->addvars($liste_bid[$bid_count-1]);
			}
			$tpl_content->addlist("liste_handeln", $liste_bid, "tpl/".$s_lang."/marktplatz_handeln.row.htm", 'addbidinfo');
		} else if ($ar_params[3] == 'liste' && (int)$ar_params[4]) {
			$id_negotiation = (int)$ar_params[4];
			$liste=array();
			$res = $db->querynow("
				SELECT
					*,
					(SELECT NAME FROM `user` WHERE ID_USER=FK_USER_FROM) AS `NAME`,
					(AMOUNT * BID) AS BID_FULL,
					IF(FK_USER_AD_OWNER=FK_USER_FROM,FK_USER_TO,FK_USER_FROM) AS FK_USER_BID
				FROM
					trade
				WHERE
					FK_AD=".$ad['ID_AD']." AND FK_NEGOTIATION=".$id_negotiation."
				ORDER BY
					FK_USER_BID ASC,
					STAMP_BID ASC,
					ID_TRADE ASC");
			$first = true;
			$i = 0;
			while($row = mysql_fetch_assoc($res['rsrc'])) {
				if ($first) {
					$row['new'] = 1;
					$first = false;
				}
				$liste[$i++] = $row;
			}
			$liste[$i-1]['last'] = 1;
			if (count($liste) > 0) {
				$tpl_content->addlist("liste_handeln", $liste, "tpl/".$s_lang."/marktplatz_handeln.row2.htm", 'addbidinfo');
			}
		}
		if($ad['VERSANDKOSTEN'])
		{
			$ad['PREIS_KOMPLETT'] = $ad['PREIS']+$ad['VERSANDKOSTEN'];
		}
		else
		{
			$ad['PREIS_KOMPLETT'] = $ad['PREIS'];
		}
		$article_tpl = array(
			    "product_manufacturer"  => $ad['MANUFACTURER'],
			    "product_articlename"   => $ad["PRODUKTNAME"],
                "product_price"         => $ad["PREIS"],
                "product_amount"        => $ad["MENGE"],
			  	"product_mwst"         	=> $ad["MWST"],
			  	"product_shipping"      => $ad["VERSANDKOSTEN"],
			    "product_sold"			=> (($ad["STATUS"]&4)==4 ? true : false),
			    "product_country"       => $ad['LAND'],
			    "product_zip"           => $ad["ZIP"],
			    "product_city"          => $ad["CITY"],
			    "vk_username"			=> $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$ad["FK_USER"]),
			    "vk_user"				=> $ad["FK_USER"],
				"agb"					=> $article_data["AD_AGB"],
				"widerruf"				=> $article_data["AD_WIDERRUF"],
				'FK_KAT' 				=> $ad["FK_KAT"],
				'_STATUS'				=> $ad['STATUS']&3,
				'BF_CONSTRAINTS'		=> $ad['BF_CONSTRAINTS']
			);
        if (!($ad["MOQ"] > 0)) {
            $ad["MOQ"] = 1;
        }
		$tpl_content->addvars($ad);
		$tpl_content->addvars($article_tpl);
		$tpl_content->addvar("IMG_DEFAULT_SRC", $article->getImagePath());
		$tpl_content->addvar("trade_max_hours", $nar_systemsettings['MARKTPLATZ']['TRADE_MAX_HOURS']);

		Rest_MarketplaceAds::extendAdDetailsSingle($article_tpl);

		if(($article_tpl['BF_CONSTRAINTS_B2B'] && !$user['USER_CONSTRAINTS_ALLOWED_B2B']) && (count($liste) == 0) && (count($liste_bid) == 0)) {
			die(forward($tpl_content->tpl_uri_action("marktplatz_anzeige,".$id_ad.",".addnoparse(chtrans($article_tpl['product_articlename'])))));
		}

		// Payment Adapter
		$paymentAdaptersForAd = $adPaymentAdapterManagement->fetchAllPaymentAdapterForAd($id_ad);
		foreach($paymentAdaptersForAd as $key => $value) {
			$paymentAdaptersForAd[$key]['CURRENT_CART_PAYMENT_ADAPTER'] = ($currentPaymentAdapter)?$currentPaymentAdapter:$_POST['payment_adapter'];
		}
		$tpl_content->addlist("PAYMENT_ADAPTER", $paymentAdaptersForAd, "tpl/".$s_lang."/cart.payment_adapter.row.htm");


	}
} else {
	$tpl_content->addvar('not_found', 1);
}

$userdata = $db->fetch1("
	SELECT
		ID_USER, VORNAME, NACHNAME, STRASSE, PLZ, ORT
	FROM
		`user`
	WHERE
		ID_USER=".$uid);
if (empty($userdata["VORNAME"]) || empty($userdata["NACHNAME"]) ||
empty($userdata["STRASSE"]) || empty($userdata["PLZ"]) || empty($userdata["ORT"])) {
	$tpl_content->addvar("error_noaddress", 1);
	if (empty($userdata["VORNAME"])) $tpl_content->addvar("error_addr_first", 1);
	if (empty($userdata["NACHNAME"])) $tpl_content->addvar("error_addr_last", 1);
	if (empty($userdata["STRASSE"])) $tpl_content->addvar("error_addr_street", 1);
	if (empty($userdata["PLZ"])) $tpl_content->addvar("error_addr_zip", 1);
	if (empty($userdata["ORT"])) $tpl_content->addvar("error_addr_city", 1);
	return;
}

$cachefile = "cache/marktplatz/ariane_de.".$ad['FK_KAT'].".htm";

$cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
$modifyTime = @filemtime($cacheFile);
$diff = ((time()-$modifyTime)/60);

if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile)) {
	require_once("sys/lib.pub_kategorien.php");
	$kat_cache = new CategoriesCache();
	$kat_cache->cacheKatAriane($ad['FK_KAT']);
}
$tpl_content->addvar("ariane", $ffile = file_get_contents($cachefile));
$tpl_main->addvar("SKIN_KAT_PATH", $ffile);

// Aktuelles Gebot auslesen
$ar_bid_active = $db->fetch1("SELECT * FROM `trade` WHERE FK_AD=".$id_ad." AND FK_AD_VARIANT=".$id_ad_variant." AND FK_USER_FROM=".$uid." AND (BID_STATUS='ACTIVE' OR BID_STATUS='REQUEST')");
if (!empty($ar_bid_active)) {
	$tpl_content->addvar("trade_negotiation", $ar_bid_active['FK_NEGOTIATION']);
	$tpl_content->addvar("trade_amount", $ar_bid_active['AMOUNT']);
	$tpl_content->addvar("FK_USER_INVOICE", $ar_bid_active['FK_USER_INVOICE']);
	$tpl_content->addvar("FK_USER_VERSAND", $ar_bid_active['FK_USER_VERSAND']);
	if ($ar_bid_active["FK_AD_REQUEST"] > 0) {
		$articleRequest = Api_Entities_MarketplaceArticle::getById($ar_bid_active["FK_AD_REQUEST"]);
		$tplRequest = new Template("tpl/".$s_lang."/marktplatz_handeln.request.htm");
		$tplRequest->isTemplateRecursiveParsable = true;
		$tplRequest->isTemplateCached = true;
		$tplRequest->addvar("noads", 1);
		$tplRequest->addvars($articleRequest->getData_Article());
		$tplRequest->addvar("IMG_DEFAULT_SRC", $articleRequest->getImagePath());
		$tpl_content->addvar("REQUEST_ARTICLE", $tplRequest->process(false));
		//$tpl_content->addvars($articleRequest->getData_Article(), "REQUEST_");
	}
}

if (!empty($_POST)) {
	// Error-Array initialisieren
	$errors = array();
	$arOffer = false;
	$bidAmount = (int)$_POST['BID_AMOUNT'];
	$userTo = (int)$_POST['FK_USER_TO'];
	if (empty($userTo)) {
	    $userTo = $article->getData_Article("FK_USER");
    }
	if (!empty($ar_bid_active)) {
		$userTo = ($uid == $ar_bid_active["FK_USER_FROM"] ? $ar_bid_active["FK_USER_TO"] : $ar_bid_active["FK_USER_FROM"]);
		$bidAmount = (int)$ar_bid_active['AMOUNT'];
	}
	if ($ad['VERKAUFSOPTIONEN'] != 5) {
		// Gebot abgeben (regulär)
		$arOffer = $article->tradeOffer(
			$bidAmount, (float)str_replace(',', '.', $_POST['BID_NEW']), $_POST["REMARKS"], 
			$uid, $userTo, (int)$_POST["ID_USER_INVOICE"], (int)$_POST["ID_USER_VERSAND"], (int)$_POST['payment_adapter'],
			(int)$_POST['ID_AD_VARIANT'], null, null, $errors
		);
	} else {
		// Gesuch! Artikel anbieten!
		$articleJson = $article->getData_JsonAdditional();
		$articleOffer = Api_Entities_MarketplaceArticle::getById($_POST["OFFER_ARTICLE_ID"]);
		$arOffer = $articleOffer->tradeOfferRequest(
			$bidAmount, $ad["PREIS"], (float)str_replace(',', '.', $_POST['BID_NEW']), $_POST["REMARKS"], 
			$uid, $ad["FK_USER"], (int)$articleJson["ID_USER_INVOICE"], (int)$articleJson["ID_USER_VERSAND"], (int)$_POST['payment_adapter'],
			null, (int)$_POST["ID_AD"], null, $errors
		);
	}
	if ($arOffer !== false) {
		if (($ad['VERKAUFSOPTIONEN'] != 5) && $arOffer["DO_BUY"]) {
			// Anzeige kaufen  - Weiterleiten
			die(forward("/index.php?page=marktplatz_kaufen&ID_TRADE=".$arOffer["ID_TRADE"]."&FK_AD=".$arOffer['FK_AD']."&FK_AD_VARIANT=".$arOffer['FK_AD_VARIANT']."&tradeReq=1"));
		} else {
			// Gebot abgegeben - Weiterleiten
			die(forward("/marktplatz/marktplatz_handeln,".$arOffer['FK_AD'].",".$arOffer['FK_AD_VARIANT'].',liste,'.$arOffer["FK_NEGOTIATION"].'.htm'));
		}
	} else {
		var_dump($errors);
		// Fehler!
		foreach ($errors as $errorIndex => $errorIdent) {
			$tpl_content->addvar($errorIdent, 1);
		}
		$_POST["FK_USER_INVOICE"] = $_POST["ID_USER_INVOICE"];
		$_POST["FK_USER_VERSAND"] = $_POST["ID_USER_VERSAND"];
		$tpl_content->addvars($_POST);
	}
} else {
    $arOffersActive = $article->traderOffersActive($uid, true);
    $arOffersAds = [];
    foreach ($arOffersActive as $arOffer) {
        $arOfferAds[] = $arOffer["ID_AD_MASTER"];
    }
    $tpl_content->addlist("liste_offers", $arOffersActive, "tpl/".$s_lang."/marktplatz_handeln.request_offer.htm");
    $tpl_content->addvar("JSON_EXCLUDE_ADS", json_encode($arOfferAds));
}


?>