<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ads.php';
require_once $ab_path.'sys/lib.shopping.cart.php';
require_once $ab_path.'sys/lib.ad_constraint.php';
require_once $ab_path.'sys/lib.ad_variants.php';
require_once $ab_path.'sys/lib.ad_payment_adapter.php';

$shoppingCartManagement = ShoppingCartManagement::getInstance();
$variants = AdVariantsManagement::getInstance($db);
$adPaymentAdapterManagement = AdPaymentAdapterManagement::getInstance($db);

$arAddressInvoice = $shoppingCartManagement->getCartInvoiceAddress();
$arAddressShipping = $shoppingCartManagement->getCartShippingAddress();
$shippingCountry = 0;
if (is_array($arAddressInvoice)) {
	$arAddressInvoice["COUNTRY"] = $arAddressInvoice["FK_COUNTRY"];
	$tpl_content->addvars($arAddressInvoice, "INVOICE_");
	if (array_key_exists("ID", $arAddressInvoice)) {
		$tpl_content->addvar("FK_USER_INVOICE", $arAddressInvoice["ID"]);
	}
}
if (empty($arAddressShipping) || $shoppingCartManagement->isCartShippingAddressEqualToInvoice()) {
	// Rechnungsadresse standardmäßig auch für den Versand verwenden
	$tpl_content->addvar("VERSAND_USE_INVOICE", 1);	
	$shippingCountry = (int)$arAddressInvoice["FK_COUNTRY"];
} else if (is_array($arAddressShipping)) {
	$arAddressShipping["COUNTRY"] = $arAddressShipping["FK_COUNTRY"];
	$shippingCountry = $arAddressShipping["FK_COUNTRY"];
	$tpl_content->addvars($arAddressShipping, "VERSAND_");
	if (array_key_exists("ID", $arAddressShipping)) {
		$tpl_content->addvar("FK_USER_VERSAND", $arAddressShipping["ID"]);
	}
}
$tpl_content->addvar("SHIPPING_COUNTRY", $shippingCountry);

if ($ar_params[1] == "updated") {
	$tpl_content->addvar("updated", 1);
}
	
if (array_key_exists("ajax", $_POST)) {
		$doFinish = false;
		switch ($_POST["ajax"]) {
				case "list":
						$tpl_content->LoadText("tpl/".$s_lang."/cart.list.htm");
						break;
		}
}

if(isset($_REQUEST['DO'])) {
	$tpl_content->addvar("updated", 1);
	
	
	if ($_REQUEST['DO'] == "CONTINUE") {
		
		$errors = array();
		$shoppingCartManagement->updateAddress($_REQUEST, $errors, $tpl_content);
		
		if (array_key_exists('DETAILS', $_POST)) {
			foreach($_POST['DETAILS'] as $id_ad => $ar_variant) {
				foreach ($ar_variant as $id_ad_variant => $ar_ad_details) {
					$shoppingCartManagement->setDetailsOfArticle($id_ad, $id_ad_variant, $ar_ad_details);
				}
			}
		}
		
		if (!empty($errors)) {
			$tpl_content->addvars($_POST);
		} else {
			die(forward("cart_checkout"));
		}
		
	}
	
	if(isset($_REQUEST['ID_AD'])) {
		$adId = (int)$_REQUEST['ID_AD'];
		$adVariantId = (int)$_REQUEST['ID_AD_VARIANT'];

		$ad = AdManagment::getAdById($adId);
		$adVariant = $variants->getAdVariantDetailsById($adVariantId);
		if($ad != NULL) {
			if ($_REQUEST['DO'] == 'ADD') {
				$newArticle = ($shoppingCartManagement->existArticleInCart($adId, $adVariantId) == FALSE);
				$availability = false;
				if (!empty($_REQUEST['AVAILABILITY'])) {
					if (!$newArticle) {
						// Trying to stack multiple items with availability
						echo json_encode(array('success' => false));
						die();
					}
					$availability = $_REQUEST['AVAILABILITY'];
					if (strtotime($availability['DATE_FROM']) === false) {
						// No vaild start date given
						echo json_encode(array('success' => false));
						die();
					}
				}

				if(isset($_REQUEST['QUANTITY'])) {
						$quantity = (int) $_REQUEST['QUANTITY'];
				} else {
						$quantity = ((int)$ad['MOQ']>0)?((int)$ad['MOQ']>0):1;
				}

				if($ad['MENGE'] < $quantity) {
					// No vaild start date given
					echo json_encode(array('success' => false, 'err' => 'mengelessthanquantity', 'maxQuantity' => $ad['MENGE']));
					die();
				}
		
								$result = $shoppingCartManagement->addArticleToCart($adId, $adVariantId, $quantity, $availability);
				$reponseStatus = array(
					'isNewItem' 		=> ($result && $newArticle),
					'cartItemCount' 	=> $shoppingCartManagement->getNumberOfArticles(),
					'cartTotalPrice' 	=> $shoppingCartManagement->getTotalPrice()
				);

			} elseif ($_REQUEST['DO'] == 'REMOVE') {
				$shoppingCartManagement->removeArticleFromCart($adId, $adVariantId);
			} elseif ($_REQUEST['DO'] == 'SETQUANTITY') {
				$shoppingCartManagement->setQuantityOfArticle($adId, $adVariantId, (int) $_REQUEST['QUANTITY']);
			} elseif ($_REQUEST['DO'] == 'SETPAYMENTADAPTER') {
				$shoppingCartManagement->setCartItemPaymentAdapter((int) $_REQUEST['ID_AD'], (int) $_REQUEST['ID_VARIANT'], (int) $_REQUEST['CART_PAYMENT_ADAPTER']);
			}

			echo json_encode(array('success' => true, 'status' => $reponseStatus));
			die();
		} else {
			echo json_encode(array('success' => false));
			die();
		}
	} elseif ($_REQUEST['DO'] == 'FLUSH') {
		$shoppingCartManagement->flush();

		echo json_encode(array('success' => true)); die();
	} elseif ($_REQUEST['DO'] == 'SETCARTPAYMENTADAPTER') {
		$shoppingCartManagement->setCartPaymentAdapter((int) $_REQUEST['CART_PAYMENT_ADAPTER']);

		echo json_encode(array('success' => true)); die();
	} elseif ($_REQUEST['DO'] == 'SET_ARTICLE_DETAILS') {
		$shoppingCartManagement->updateAddress($_REQUEST, $errors, $tpl_content);
		$arAddressInvoice = $shoppingCartManagement->getCartInvoiceAddress();
		$arAddressShipping = $shoppingCartManagement->getCartShippingAddress();
		
		foreach($_REQUEST['DETAILS'] as $id_ad => $ar_variant) {
			foreach ($ar_variant as $id_ad_variant => $ar_ad_details) {
				$shoppingCartManagement->setDetailsOfArticle($id_ad, $id_ad_variant, $ar_ad_details);
					//$shoppingCartManagement->setQuantityOfArticle($id_ad, $id_ad_variant, (int) $quantity);
			}
		}
		if (!array_key_exists("ajax", $_POST)) {
			$_SESSION["CART_INPUT"] = $_POST;
			die(forward('cart,updated.htm'));
		} else {
			if (!array_key_exists("FK_COUNTRY", $arAddressShipping)) {
				if ($_POST["VERSAND_USE_INVOICE"]) {
					$arAddressShipping["FK_COUNTRY"] = $_POST["INVOICE_FK_COUNTRY"];
				} else {
					$arAddressShipping["FK_COUNTRY"] = $_POST["VERSAND_FK_COUNTRY"];
				}
			}
			$tpl_content->addvar("SHIPPING_COUNTRY", $arAddressShipping["FK_COUNTRY"]);
		}
	}
}

function killbb(&$row, $i) {
    //$row['DSC'] = strip_tags($row['DSC']);
    $row['DSC'] = substr(strip_tags(html_entity_decode($row['DSC'])), 0, 250);
    $row['DSC'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['DSC']);
}

$articles = $shoppingCartManagement->getArticles();

$tplArticles = array();
$removeConstraint = FALSE;
$availablePaymentAdapter = array();

foreach($articles as $key => $article) {
	Rest_MarketplaceAds::extendAdDetailsSingle($article['ARTICLEDATA']);
	if($article['ARTICLEDATA']['BF_CONSTRAINTS_B2B'] && !$user['USER_CONSTRAINTS_ALLOWED_B2B']) {
		$shoppingCartManagement->removeArticleFromCart($article['ARTICLEDATA']['ID_AD'], $article['ARTICLEDATA']['ID_AD_VARIANT']);
		$removeConstraint = TRUE;
	}

	// Varianten text
	$ar_liste = array();
	$ar_variant = $variants->getAdVariantTextById($article['ARTICLEDATA']['ID_AD_VARIANT']);
	foreach($ar_variant as $i => $row) {
        $tpl_tmp = new Template("tpl/".$s_lang."/cart.row_variant.htm");
        $tpl_tmp->addvars($row);
        $tpl_tmp->addvar('i', $i);
        $tpl_tmp->addvar('even', 1-($i&1));
        $ar_liste[] = $tpl_tmp;
	}

	// Payment Adapter
	$tplCartPaymentAdapter = new Template($ab_path."tpl/de/empty.htm");
	$tplCartPaymentAdapter->tpl_text = '{liste}';
	$paymentAdaptersForAd = $adPaymentAdapterManagement->fetchAllPaymentAdapterForAd($article['ARTICLEDATA']['ID_AD']);
	foreach($paymentAdaptersForAd as $key => $value) {
		$paymentAdapterId = $shoppingCartManagement->getCartItemPaymentAdapter( $article['ARTICLEDATA']['ID_AD'], $article['ARTICLEDATA']['ID_AD_VARIANT'] );
		$paymentAdapterId = ($paymentAdapterId > 0 ? $paymentAdapterId : $shoppingCartManagement->getCartPaymentAdapter());
		if (!$adPaymentAdapterManagement->isPaymentAdapterAvailableForAd($paymentAdapterId, $article['ARTICLEDATA']['ID_AD'])) {
			if (empty($paymentAdaptersForAd)) {
				$paymentAdapterId = 0;
			} else {
				$paymentAdapterId = $paymentAdaptersForAd[0]["ID_PAYMENT_ADAPTER"];
			}
			$shoppingCartManagement->setCartItemPaymentAdapter( $article['ARTICLEDATA']['ID_AD'], $article['ARTICLEDATA']['ID_AD_VARIANT'], $paymentAdapterId);
		}
		$paymentAdaptersForAd[$key]['CURRENT_CART_PAYMENT_ADAPTER'] = $paymentAdapterId;
	}
	$tplCartPaymentAdapter->addlist("liste", $paymentAdaptersForAd, "tpl/".$s_lang."/cart.payment_adapter.row.htm");

    $tplArticles[] = array_merge($article['ARTICLEDATA'], array(
    		'USER_ID_USER' => $article['USERDATA']['ID_USER'],
    		'USER_NAME' => $article['USERDATA']['NAME'],
    		'CART_QUANTITY' => $article['QUANTITY'],
    		'CART_TOTAL_SHIPPING_PRICE' => $article['TOTAL_SHIPPING_PRICE'],
    		'CART_TOTAL_ARTICLE_PRICE' => $article['TOTAL_ARTICLE_PRICE'],
    		'CART_TOTAL_PRICE' => $article['TOTAL_PRICE'],
    		'AVAILABILITY' => $article['AVAILABILITY'],
    		'AVAILABILITY_DATE_FROM' => $article['AVAILABILITY_DATE_FROM'],
    		'AVAILABILITY_TIME_FROM' => $article['AVAILABILITY_TIME_FROM'],
    		'AVAILABILITY_DATE_TO' => $article['AVAILABILITY_DATE_TO'],
    		'VARIANT_TEXT' => $ar_liste,
			'CART_PAYMENT_ADAPTER' => $tplCartPaymentAdapter->process()
    ), array_flatten($article["OPTIONS"], true, "_", "options_"));
}

if($removeConstraint === TRUE) {
	die(forward($tpl_content->tpl_uri_action("cart")));
}
// Plugin event
$eventMarketCartView = new Api_Entities_EventParamContainer(array(
    "cart"          => $shoppingCartManagement,
    "articleList"		=> $tplArticles,
    "pluginHtml"    => ""
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_CART_VIEW, $eventMarketCartView);
if ($eventMarketCartView->isDirty()) {
	  $tpl_content->addvar("PLUGIN_HTML", $eventMarketCartView->getParam("pluginHtml"));
}

$tpl_content->addlist("liste", $tplArticles, "tpl/".$s_lang."/cart.row.htm", 'killbb');
$tpl_content->addvar("TOTAL_PRICE", $shoppingCartManagement->getTotalPrice());
$tpl_content->addvar("TOTAL_SHIPPING_PRICE", $shoppingCartManagement->getTotalShippingPrice());
$tpl_content->addvar("TOTAL_ARTICLE_PRICE", $shoppingCartManagement->getTotalArticlePrice());

$tpl_content->addvar("SETTINGS_BUYING_UNREGISTERED", $nar_systemsettings['MARKTPLATZ']['BUYING_UNREGISTERED']);

// Payment Adapter
$tpl_content->addlist("cart_payment_adapter", $availablePaymentAdapter, "tpl/".$s_lang."/cart.payment_adapter.row.htm");

if (array_key_exists("ajax", $_POST)) {
    switch ($_POST["ajax"]) {
        case "list":
            die($tpl_content->process());
            break;
    }
}