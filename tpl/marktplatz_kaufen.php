<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path.'sys/lib.ad_constraint.php';
require_once $ab_path.'sys/lib.ad_variants.php';
require_once $ab_path.'sys/lib.ad_order.php';
require_once $ab_path.'sys/lib.ad_payment_adapter.php';

$variants = AdVariantsManagement::getInstance($db);
$adOrderManagement = AdOrderManagement::getInstance($db);
$adPaymentAdapterManagement = AdPaymentAdapterManagement::getInstance($db);

#$SILENCE = false;

$id_ad = (int)$ar_params[1];
$id_ad_variant = (int)$ar_params[2];
if($_POST['ID_AD'])
{
	$id_ad = (int)$_POST['ID_AD'];
	$id_ad_variant = (int)$_POST['ID_AD_VARIANT'];
} elseif($_REQUEST['tradeReq'] == 1)
{
	$id_ad = (int)$_REQUEST['FK_AD'];
	$id_ad_variant = (int)$_REQUEST['FK_AD_VARIANT'];
}
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
			AND a.STATUS&3=1 AND (a.DELETED=0)";
$ad = $db->fetch1($query);
$adVariant = $variants->getAdVariantDetailsById($id_ad_variant);
$adVariantFields = $variants->getAdVariantTextById($id_ad_variant);
$ad = array_merge($ad, $adVariant);

if(!empty($ad))
{
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
	$ad = array_merge($ad, $ar_info);
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
		  	"product_mwst"         	=> $ad["MWST"],
		  	"product_shipping"       => $ad["VERSANDKOSTEN"],
		    "product_sold"			=> (($ad["STATUS"]&4)==4 ? true : false),
		    "product_country"       => $ad['LAND'],
		    "product_zip"           => $ad["ZIP"],
		    "product_city"          => $ad["CITY"],
		    "vk_username"			=> $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$ad["FK_USER"]),
		    "vk_user"				=> $ad["FK_USER"],
			"agb"					=> $ad["AD_AGB"],
			"widerruf"				=> $ad["AD_WIDERRUF"],
			'FK_KAT' 				=> $ad["FK_KAT"],
			'_STATUS'				=> $ad['STATUS']&3,
			'BF_CONSTRAINTS'		=> $ad['BF_CONSTRAINTS'],
			'MOQ'					=> $ad['MOQ']
		);

	// Payment Adapter
	$paymentAdaptersForAd = $adPaymentAdapterManagement->fetchAllPaymentAdapterForAd($id_ad);
	foreach($paymentAdaptersForAd as $key => $value) {
		$paymentAdaptersForAd[$key]['CURRENT_CART_PAYMENT_ADAPTER'] = $_POST['payment_adapter'];
	}
	$tpl_content->addlist("PAYMENT_ADAPTER", $paymentAdaptersForAd, "tpl/".$s_lang."/cart.payment_adapter.row.htm");



	$tpl_content->addvars($ad);
	$tpl_content->addvars($article_tpl);
}
else
{
	$tpl_content->addvar('not_found', 1);
}


// Constraint Check
Rest_MarketplaceAds::extendAdDetailsSingle($article_tpl);

if($article_tpl['BF_CONSTRAINTS_B2B'] && !$user['USER_CONSTRAINTS_ALLOWED_B2B'] && ($_REQUEST['tradeReq'] != 1)) {
	die(forward($tpl_content->tpl_uri_action("marktplatz_anzeige,".$id_ad.",".addnoparse(chtrans($article_tpl['product_articlename'])))));
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
/*
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
*/
if (!empty($_POST) || $_REQUEST['tradeReq'] == 1) {
	$errors = array();
	if (empty($_POST["AGB"]) && $_REQUEST['tradeReq'] != 1)
	{
		$tpl_content->addvar("err_agb", 1);
		$errors[] = "error_agb";
	}
	if ($_POST["ID_USER_VERSAND"] > 0) {
		$versand_check = $db->fetch_atom("SELECT FK_USER FROM `user_versand` WHERE ID_USER_VERSAND=".(int)$_POST["ID_USER_VERSAND"]);
		if ($versand_check != $uid) {
			$tpl_content->addvar("error_versand", 1);
			$errors[] = "error_versand";
		}
	}
	$id_payment_adapter = (int)$_POST['payment_adapter'];
	if(($id_payment_adapter > 0) && !$adPaymentAdapterManagement->isPaymentAdapterAvailableForAd($id_payment_adapter, $id_ad)) {
		$errors[] = 'err_payment_adapter';
		$tpl_content->addvar("err_payment_adapter", 1);
	}

	if($_REQUEST['tradeReq'] == 1)
	{
		$tpl_content->addvar('ID_TRADE', $_REQUEST['ID_TRADE']);
		/*
		 * Handeln
		 */
		$seller_id = $ad['FK_USER'];
		$_POST["ID_AD"] = $_REQUEST['FK_AD'];
		$ar_trade = $db->fetch1("
			SELECT
				*
			FROM
				trade
			WHERE
				ID_TRADE=".(int)$_REQUEST['ID_TRADE']);
		$_POST["MENGE"] = $ar_trade["AMOUNT"];
		if(empty($ar_trade))
		{
			$errors[] = 'err_trade';
			$tpl_content->addvar("err_trade", 1);
		}
		else
		{
            $ad['PREIS'] = $ar_trade['BID'];
            $_POST['REMARKS'] = $ar_trade['REMARKS'];
			if($seller_id == $uid)
			{
				// ist verkäufer
				$_IS_SELLER = true;
				if($ar_trade['BID_STATUS'] != 'ACTIVE')
				{
					$errors[] = "err_trade";
				}
				if($ar_trade['FK_USER_TO'] != $uid)
				{
					$errors[] = 'err_trade';
				}
				$uid_client = $ar_trade['FK_USER_FROM'];
			}
			else
			{
				// ist käufer
				if($ar_trade['BID_STATUS'] != 'ACTIVE')
				{
					$errors[] = "err_trade";
				}
				if($ar_trade['FK_USER_TO'] != $uid)
				{
					if($ar_trade['FK_USER_FROM'] == $uid)
					{
						if($ad['AUTOBUY'] > 0 && $ad['AUTOBUY'] <= $ar_trade['BID'])
						{
							//
						}
						else
						{
							$errors[] = 'err_trade';
						}
					}
				}
				$uid_client = $uid;
			}
		}
	} else if (!empty($_POST)) {
		/*
		 * Kaufen
		 */
		$uid_client = $uid;
	}
	if(in_array('err_trade', $errors))
	{
		$tpl_content->addvar("err_trade", 1);
	}
	if (empty($errors)) {
		if (($_POST["MENGE"] >= $ad['MOQ']) && ($_POST["MENGE"] <= $ad["MENGE"])) {
			require_once "sys/lib.ads.php";

			$id_ad_sold = false;

			$tmpArticle = array(
				'ID_AD' => $id_ad,
				'ID_AD_VARIANT' => $id_ad_variant,
				'ARTICLEDATA' => $ad
			);

			if ($ar_trade['ID_TRADE']) {
				$order[] = array(
					'article' => $tmpArticle,
					'userInvoice' => 0,
					'userVersand' => 0,
					'price' => $ad["PREIS"],
					'quantity' => $ar_trade["AMOUNT"],
					'paymentAdapter' => $ar_trade['FK_PAYMENT_ADAPTER'],
					'tradeId' => $ar_trade['ID_TRADE'],
                    'remarks' => $_POST["REMARKS"]
				);
			} else {
				$order[] = array(
					'article' => $tmpArticle,
					'userInvoice' => $_POST["ID_USER_INVOICE"],
					'userVersand' => $_POST["ID_USER_VERSAND"],
					'price' => $ad["PREIS"],
					'quantity' => $_POST["MENGE"],
					'paymentAdapter' => $id_payment_adapter,
					'remarks' => $_POST["REMARKS"]
				);
			}

			$orderResult = $adOrderManagement->createOrder($uid_client, array($order));

			if ($orderResult !== false) {
				if ($ad["FK_USER"] == $uid) {
					die(forward($tpl_content->tpl_uri_action("my-marktplatz-verkaeufe")));
				} else {
                    die(forward($tpl_content->tpl_uri_action("marktplatz_gekauft")));
				}
			} else {
				die("Fehler!!");
			}
		} else {
			$tpl_content->addvar("err_amount", 1);
		}
	}
}
?>
