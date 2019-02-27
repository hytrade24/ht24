<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ads.php';
require_once $ab_path.'sys/lib.user.php';
require_once $ab_path.'sys/lib.shopping.cart.php';
require_once $ab_path.'sys/lib.ad_constraint.php';
require_once $ab_path.'sys/lib.ad_variants.php';
require_once $ab_path.'sys/lib.ad_payment_adapter.php';
require_once $ab_path.'sys/lib.ad_order.php';

$shoppingCartManagement = ShoppingCartManagement::getInstance();
$userManagement = UserManagement::getInstance($db);
$variants = AdVariantsManagement::getInstance($db);
$adPaymentAdapterManagement = AdPaymentAdapterManagement::getInstance($db);
$adOrderManagement = AdOrderManagement::getInstance($db);

function killbb(&$row, $i) {
    //$row['DSC'] = strip_tags($row['DSC']);
    $row['DSC'] = substr(strip_tags(html_entity_decode($row['DSC'])), 0, 250);
    $row['DSC'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['DSC']);
}


$userdata = array();

if ($uid > 0) {
    $userdata = $userManagement->fetchById($uid);
    if (empty($userdata["VORNAME"]) || empty($userdata["NACHNAME"]) || empty($userdata["STRASSE"]) || empty($userdata["PLZ"]) || empty($userdata["ORT"])) {
        $tpl_content->addvar("error_noaddress", 1);

        if (empty($userdata["VORNAME"])) $tpl_content->addvar("error_addr_first", 1);
        if (empty($userdata["NACHNAME"])) $tpl_content->addvar("error_addr_last", 1);
        if (empty($userdata["STRASSE"])) $tpl_content->addvar("error_addr_street", 1);
        if (empty($userdata["PLZ"])) $tpl_content->addvar("error_addr_zip", 1);
        if (empty($userdata["ORT"])) $tpl_content->addvar("error_addr_city", 1);
        return;
    }
}


$articles = $shoppingCartManagement->getArticles();

$arAddrInvoice = $shoppingCartManagement->getCartInvoiceAddress();
$arAddrShipping = $shoppingCartManagement->getCartShippingAddress();
if (empty($arAddrInvoice) || empty($arAddrShipping)) {
    die(forward($tpl_content->tpl_uri_action("cart")));
}

$tpl_content->addvars($arAddrInvoice, "INVOICE_");
$tpl_content->addvars($arAddrShipping, "VERSAND_");

if (!empty($_POST)) {
    $doFinish = true;
    if (array_key_exists("ajax", $_POST)) {
        $doFinish = false;
        switch ($_POST["ajax"]) {
            case "list":
                $tpl_content->LoadText("tpl/".$s_lang."/cart_checkout.list.htm");
                break;
        }
    }
    $allAgbChecked = true;
    foreach ($articles as $key => $article) {
        list($id_ad, $id_ad_variant) = explode(".", $key);
        if (empty($_POST['AGB'][$id_ad][$id_ad_variant])) {
            $allAgbChecked = false;
        }

        if (isset($_POST['payment_adapter'][$id_ad]) && !$adPaymentAdapterManagement->isPaymentAdapterAvailableForAd((int)$_POST['payment_adapter'][$id_ad], $id_ad)) {
            $tpl_content->addvar("err_payment_adapter", 1);
            $errors[] = "error_payment_adapter";
        }
    }
    if (!$allAgbChecked) {
        $tpl_content->addvar("err_agb", 1);
        $errors[] = "error_agb";
    }

    if ($doFinish) {
        if (empty($errors) && !$uid) {
            // Create virtual user
            $arVirtUser = $arAddrInvoice;
            $arVirtUser["STRASSE"] = $arVirtUser["STREET"];
            $arVirtUser["PLZ"] = $arVirtUser["ZIP"];
            $arVirtUser["ORT"] = $arVirtUser["CITY"];
            $uid = $userManagement->createVirtualUser($arVirtUser, true);
            if ($uid === false) {
                $tpl_content->addvar("err_user_create", 1);
                $errors[] = "error_user_create";
            }
        }
        if (empty($errors)) {
            $order = array();
    
            foreach ($articles as $key => $article) {
    
                if ($article['QUANTITY'] > 0 && ($article['QUANTITY'] <= $article['ARTICLEDATA']['MENGE']) && ($uid != $article['ARTICLEDATA']['FK_USER'])) {
                    $errorOnBuying = false;
    
                    $order[] = array(
                      'article' => $article,
                      'userInvoice' => (array_key_exists("ID", $arAddrInvoice) ? $arAddrInvoice["ID"] : $arAddrInvoice),
                      'userVersand' => (array_key_exists("ID", $arAddrShipping) ? $arAddrShipping["ID"] : $arAddrShipping),
                      'price' => $article['ARTICLEDATA']["PREIS"],
                      'quantity' => $article['QUANTITY'],
                      'paymentAdapter' => $article['ID_PAYMENT_ADAPTER']
                    );
    
                    /*$id_ad_sold = false;
                    $id_ad_sold = AdManagment::Buy($article['ID_AD'], $article['ID_AD_VARIANT'], $uid, $_POST["ID_USER_INVOICE"], $_POST["ID_USER_VERSAND"], $article['ARTICLEDATA']["PREIS"], $article['QUANTITY']);
    
                    if($id_ad_sold == false) {
                        $errorOnBuying = true;
                    }*/
                } else {
                    $errorOnBuying = true;
                }
            }
    
            if (count($articles) == 0) {
                $errorOnBuying = true;
            } else {
                // Group order
                $orderGrouped = array();
                $paramCartArticlesGroup = new Api_Entities_EventParamContainer(array(
                    "orderManagement"	=> $adOrderManagement,
                    "orders" 					=> $order,
                    "result"    			=> array()
                ));
                Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_ORDER_GROUP, $paramCartArticlesGroup);
                
                if ($paramCartArticlesGroup->isDirty()) {
                    // Plugin grouping
                    $orderGrouped = $paramCartArticlesGroup->getParam("result");
                } else {
                    // Default grouping
                    foreach($order as $key => $orderDetail) {
                      $tmpSellerId = $orderDetail['article']["ARTICLEDATA"]["FK_USER"];
                      $tmpPaymentAdapter = $orderDetail['paymentAdapter'];		
                      $orderGrouped[$tmpSellerId.';'.$tmpPaymentAdapter][] = $orderDetail;
                    }
                }
                // Add remarks
                foreach ($orderGrouped as $orderGroupIndex => $orderGroupArticles) {
                    foreach ($orderGroupArticles as $articleIndex => $articleDetails) {
                        $orderGrouped[$orderGroupIndex][$articleIndex]["remarks"] = $_POST["REMARKS"][$orderGroupIndex];
                    }
                }

                // Create order
                $orderResult = $adOrderManagement->createOrder($uid, $orderGrouped, $orderId);
    
                if ($orderResult == FALSE) {
                    $errorOnBuying = TRUE;
                }
            }
    
            if (!$errorOnBuying) {
                $shoppingCartManagement->flush();
    
                die(forward("/my-marktplatz/cart_checkout_done," . $orderId . ".htm"));
            } else {
                $tpl_content->addvar("err_buy", 1);
            }
        } else {
            $tpl_content->addvars($_POST);
        }
        $tpl_content->addvar("checkout_send", 1);
    }
} else {
    // Rechnungsadresse standardmäßig auch für den Versand verwenden
    $tpl_content->addvar("VERSAND_USE_INVOICE", 1);
}

$ownArticles = array();
$tplArticles = array();
$tplArticlesGrouped = array();

$paramCartArticlesGroup = new Api_Entities_EventParamContainer(array(
    "cart"      => $shoppingCartManagement,
    "articles"  => $articles,
    "result"    => $tplArticles
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_CART_ARTICLES_GROUP, $paramCartArticlesGroup);

if ($paramCartArticlesGroup->isDirty()) {
    // Plugin grouping
    $tplArticlesGrouped = $paramCartArticlesGroup->getParam("result");
} else {
    // Default grouping
    foreach ($articles as $key => $article) {
        list($articleId, $variantId) = explode(".", $key);
        // Payment Adapter
        $paymentAdapterId = $shoppingCartManagement->getCartItemPaymentAdapter($article['ARTICLEDATA']['ID_AD'], $article['ARTICLEDATA']['ID_AD_VARIANT']);
        $paymentAdapter = ($paymentAdapterId > 0 ? $adPaymentAdapterManagement->fetchOneById($paymentAdapterId) : null);
        // Versandkosten
        $shippingGroupIndex = $article['USERDATA']['ID_USER'].".".$paymentAdapterId;
        if (!array_key_exists($shippingGroupIndex, $tplArticles)) {
            $tplArticles[$shippingGroupIndex] = array();
        }
        $tplArticles[$shippingGroupIndex][] = array_merge($article['ARTICLEDATA'], array(
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
          'IS_AGB_CHECKED' => $_POST['AGB'][$articleId][$variantId],
          'OPTIONS' => $article['OPTIONS']
        ), array_flatten($article["OPTIONS"], true, "_", "options_"));
    }
    foreach ($tplArticles as $shippingGroupIndex => $articleList) {
        if (empty($articleList)) {
            continue;
        }
        // Add article group
        $tplArticlesGrouped[] = array(
            'IDENT'     => $shippingGroupIndex,
            'ARTICLES'  => $articleList
        );
    }
}
// Remove invalid articles and add seller and variant information
foreach ($tplArticlesGrouped as $groupIndex => $groupDetails) {
    list($groupUserId, $paymentAdapterId) = explode(".", $groupDetails['IDENT']);
	  $priceArticles = 0;
    $priceShipping = $shoppingCartManagement->calculateShippingCostByItems($groupDetails['ARTICLES']);
    $paymentAdapter = ($paymentAdapterId > 0 ? $adPaymentAdapterManagement->fetchOneById($paymentAdapterId) : null);
    $tplArticles = array();
    // Get seller information
    $anbieter = $userManagement->fetchById($groupDetails['ARTICLES'][0]['FK_USER']);
    foreach ($anbieter as $akey => $anbieterValue) {
        $anbieter['ANBIETER_' . $akey] = $anbieterValue;
    }
    // Extend article information
    for ($articleIndex = count($groupDetails['ARTICLES'])-1; $articleIndex >= 0; $articleIndex--) {
        $articleData = $groupDetails['ARTICLES'][$articleIndex];
        // Varianten text
        $ar_liste = array();
        $ar_variant = $variants->getAdVariantTextById($articleData['ID_AD_VARIANT']);
        foreach ($ar_variant as $i => $row) {
            $tpl_tmp = new Template("tpl/" . $s_lang . "/cart.row_variant.htm");
            $tpl_tmp->addvars($row);
            $tpl_tmp->addvar('i', $i);
            $tpl_tmp->addvar('even', 1 - ($i & 1));
            $ar_liste[] = $tpl_tmp;
        }
        if ($articleData['FK_USER'] == $uid) {
            $shoppingCartManagement->removeArticleFromCart($articleData['ID_AD']);
            $ownArticles[] = array_merge($anbieter, $articleData, array(
              'USER_ID_USER' => $article['USERDATA']['ID_USER'], 
              'USER_NAME' => $article['USERDATA']['NAME']
            ), array(
              'CART_QUANTITY' => $article['QUANTITY'], 
              'CART_TOTAL_SHIPPING_PRICE' => $article['TOTAL_SHIPPING_PRICE'], 
              'CART_TOTAL_ARTICLE_PRICE' => $article['TOTAL_ARTICLE_PRICE'], 
              'CART_TOTAL_PRICE' => $article['TOTAL_PRICE'], 
              'IS_AGB_CHECKED' => $_POST['AGB'][ $groupDetails['IDENT'] ]
            ));
            array_splice($tplArticles[$shippingGroupIndex], $articleIndex, 1);
            continue;
        } elseif ($articleData['BF_CONSTRAINTS_B2B'] && !$user['USER_CONSTRAINTS_ALLOWED_B2B']) {
            $shoppingCartManagement->removeArticleFromCart($articleData['ID_AD']);
            $removeConstraint = TRUE;
            array_splice($tplArticles[$shippingGroupIndex], $articleIndex, 1);
            continue;
        } else {
            $articleData = array_merge($anbieter, $articleData, array(
              'VARIANT_TEXT' => $ar_liste
            ));
        }
        // Generate template
        killbb($articleData, $i);
        $tpl_tmp = new Template("tpl/".$s_lang."/cart_checkout.row.htm");
        $tpl_tmp->addvars($articleData);
        $tpl_tmp->addvar('VERSAND_FK_COUNTRY', $arAddrShipping["FK_COUNTRY"]);
        $tpl_tmp->addvar('i', $i);
        $tpl_tmp->addvar('even', 1-($i&1));
        $tplArticles[] = $tpl_tmp;
        // Count total price
		    $priceArticles += $articleData["CART_TOTAL_ARTICLE_PRICE"];
    }
    // Update group
    $tplArticlesGrouped[$groupIndex] = array_merge($anbieter, array(
        'GROUP_IDENT'                   => $groupDetails['IDENT'],
        'GROUP_FK_USER'					        => $groupUserId,
        'GROUP_FK_PAYMENT_ADAPTER'		  => $paymentAdapterId,
        'GROUP_TOTAL_ARTICLE_PRICE'		  => $priceArticles,
        'GROUP_TOTAL_SHIPPING_PRICE'	  => $priceShipping,
        'GROUP_TOTAL_PRICE'				      => $priceArticles + $priceShipping,
        'PAYMENT_ADAPTER'               => $paymentAdapter["NAME"],
        'ID_PAYMENT_ADAPTER'            => (int)$paymentAdapter["ID_PAYMENT_ADAPTER"],
        'liste'							            => $tplArticles,
        'REMARKS_VALUE'					        => $_POST['REMARKS'][ $groupDetails['IDENT'] ]
    ), $groupDetails);
}
// Plugin event
$eventMarketCartCheckout = new Api_Entities_EventParamContainer(array(
    "cart"          => $shoppingCartManagement,
    "articleGroups"	=> $tplArticlesGrouped,
    "pluginHtml"    => ""
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_CART_CHECKOUT, $eventMarketCartCheckout);
if ($eventMarketCartCheckout->isDirty()) {
    $tplArticlesGrouped = $eventMarketCartCheckout->getParam("articleGroups");
	  $tpl_content->addvar("PLUGIN_HTML", $eventMarketCartCheckout->getParam("pluginHtml"));
}

if($removeConstraint === TRUE) {
	die(forward($tpl_content->tpl_uri_action("cart")));
}

if (!empty($tplArticlesGrouped)) {
    $tpl_content->addlist("liste", $tplArticlesGrouped, "tpl/".$s_lang."/cart_checkout.group.htm", 'killbb');
}

$tpl_content->addlist("liste_own", $ownArticles, "tpl/".$s_lang."/cart_checkout.row_own.htm", 'killbb');
$tpl_content->addvar("TOTAL_PRICE", $shoppingCartManagement->getTotalPrice());
$tpl_content->addvar("TOTAL_SHIPPING_PRICE", $shoppingCartManagement->getTotalShippingPrice());
$tpl_content->addvar("TOTAL_ARTICLE_PRICE", $shoppingCartManagement->getTotalArticlePrice());

$tpl_content->addvar("SETTINGS_BUYING_UNREGISTERED", $nar_systemsettings['MARKTPLATZ']['BUYING_UNREGISTERED']);

$tpl_content->addvars($userdata, 'USERDATA_');

if (array_key_exists("ajax", $_POST)) {
    switch ($_POST["ajax"]) {
        case "list":
            die($tpl_content->process());
            break;
    }
}