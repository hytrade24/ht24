<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ads.php';
require_once $ab_path.'sys/lib.user.php';
require_once $ab_path.'sys/lib.ad_variants.php';
require_once $ab_path.'sys/lib.ad_payment_adapter.php';

class ShoppingCartManagement {
    private static $db;
    private static $instance = null;

    private static $cart;
    private static $cartType = 1;
    private static $cartPaymentAdapter = 0;
    private static $cartUserData = array();
    private $dirty = true;
    private $articleData = array();

    /**
     * Singleton
     *
     * @return ShoppingCartManagement
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        self::_loadCart();

        return self::$instance;
    }

    public function addArticleToCart($adId, $adVariantId = 0, $quantity = 1, $availability = false)
    {
        if (!isset(self::$cart[self::$cartType][$adId . "." . $adVariantId])) {
            self::$cart[self::$cartType][$adId . "." . $adVariantId] = array(
              'adId' => $adId,
              'adVariantId' => $adVariantId,
              'options' => array(),
              'quantity' => 0,
              'shippingCost' => null,
              'availability' => $availability
            );
        }
        $this->setQuantityOfArticle($adId, $adVariantId, self::$cart[self::$cartType][$adId . "." . $adVariantId]['quantity'] + $quantity);
            
        $this->_saveCart();

        return true;
    }

    public function removeArticleFromCart($adId, $adVariantId = 0) {
        unset(self::$cart[self::$cartType][$adId.".".$adVariantId]);
        $this->_saveCart();
    }
    
    public function setDetailsOfArticle($adId, $adVariantId, $ar_ad_details)
    {
        // Trigger plugin event
        $paramCartUpdate = new Api_Entities_EventParamContainer(array(
            'cart'      => $this,
            'articleId' => $adId,
            'variantId' => $adVariantId,
            'item'      => self::$cart[self::$cartType][$adId.".".$adVariantId],
            'details'   => $ar_ad_details
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_CART_ARTICLE_UPDATE, $paramCartUpdate);
        if ($paramCartUpdate->isDirty()) {
            $ar_ad_details = $paramCartUpdate->getParam('details');
        }
        if (array_key_exists("QUANTITY", $ar_ad_details)) {
            $this->setQuantityOfArticle($adId, $adVariantId, $ar_ad_details["QUANTITY"]);
        }
    }
    
    public function setOptionOfArticle($adId, $adVariantId, $optionName, $optionValue) {
        self::$cart[self::$cartType][$adId . "." . $adVariantId]['options'][$optionName] = $optionValue;

        $this->_saveCart();
    }

    public function setShippingCostOfArticle($adId, $adVariantId, $price) {
        self::$cart[self::$cartType][$adId . "." . $adVariantId]['shippingCost'] = $price;
    }

    public function setQuantityOfArticle($adId, $adVariantId, $quantity)
    {
        $article = $this->getArticle($adId, $adVariantId);
        if ($article['ARTICLEDATA']['MENGE'] < $quantity) {
            $quantity = $article['ARTICLEDATA']['MENGE'];
        }
        if ($quantity < $article['ARTICLEDATA']['MOQ']) {
            // Mindestbestellmenge unterschritten, vorherige Anzahl beibehalten (bei neuem Artikel 0)
            $quantity = self::$cart[self::$cartType][$adId . "." . $adVariantId]['quantity'];
        }

        if ($quantity <= 0) {
            $this->removeArticleFromCart($adId, $adVariantId);
        } elseif (isset(self::$cart[self::$cartType][$adId . "." . $adVariantId])) {
            self::$cart[self::$cartType][$adId . "." . $adVariantId]['quantity'] = $quantity;
            
        }

        $this->_saveCart();
    }

    public function existArticleInCart($adId, $adVariantId) {
        return (isset(self::$cart[self::$cartType][$adId.".".$adVariantId]) &&
        			self::$cart[self::$cartType][$adId.".".$adVariantId]['quantity'] > 0);
    }

    public function getNumberOfArticles() {
        return count(self::$cart[self::$cartType]);
    }

    public function getArticles() {
        if($this->dirty === TRUE) {
            $this->cacheArticleData();
        }

        return $this->articleData['ARTICLES'];
    }

    public function getArticle($articleId, $adVariantId) {
        if($this->dirty === TRUE) {
            $this->cacheArticleData();
        }

        return $this->articleData['ARTICLES'][$articleId.".".$adVariantId];
    }

    public function calculateShippingCostByItems($arArticles)
    {
        $shipping = 0;
        foreach ($arArticles as $key => $item) {
            // Trigger plugin event
            $paramCartShipping = new Api_Entities_EventParamContainer(array(
                'cart'              => $this,
                'item'              => $item,
                'shippingItem'      => ($item['VERSANDOPTIONEN'] == 3 ? $item['VERSANDKOSTEN'] : 0),
                'shippingTotal'     => $shipping,
                'shippingAddress'   => $this->getCartShippingAddress()
            ));
            Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_CART_ARTICLE_SHIPPING, $paramCartShipping);
            if ($paramCartShipping->isDirty()) {
                if ($paramCartShipping->getParam('shippingTotal') != $shipping) {
                    // Shipping total adjusted by plugin, use that value
                    $shipping = $paramCartShipping->getParam('shippingTotal');
                } else if ($paramCartShipping->getParam('shippingItem') > 0) {
                    // Use the highest shipping cost as total for the cart
                    $shipping = max($shipping, $paramCartShipping->getParam('shippingItem'));
                }
                $this->setShippingCostOfArticle($item["ID_AD_MASTER"], (int)$item["FK_AD_VARIANT"], $paramCartShipping->getParam('shippingItem'));
            } else {
                // Use the highest shipping cost as total for the cart
                $shipping = max($shipping, $paramCartShipping->getParam('shippingItem'));
            }
        }
        return $shipping;
    }

    private function cacheArticleData() {
        global $db;

        $userManagement = UserManagement::getInstance($db);
		    $variantManagement = AdVariantsManagement::getInstance($db);

        $data = array();
        $totalArticlePrice = 0;
        $totalShippingPrice = 0;
        $totalPrice = 0;
		    $sumShipping = array();

        foreach(self::$cart[self::$cartType] as $key => $cartArticle) {
    		    list($id_ad, $id_ad_variant) = explode(".", $key);
            $ad = array_merge(
            		AdManagment::getAdById($cartArticle['adId']),
            		$variantManagement->getAdVariantDetailsById($cartArticle['adVariantId'])
            );
            $adUser = $userManagement->fetchById($ad['FK_USER']);

            $data[$key] = array(
              'ID_AD' => $id_ad,
              'ID_AD_VARIANT' => $id_ad_variant,
              'ID_PAYMENT_ADAPTER' => $cartArticle["paymentAdapter"],
              'ARTICLEDATA' => $ad,
              'USERDATA' => $adUser,
              'QUANTITY' => $cartArticle['quantity'],
              'AVAILABILITY_ARRAY' => $cartArticle['availability'],
              'AVAILABILITY' => ($cartArticle['availability'] !== false),
              'AVAILABILITY_DATE_FROM' => (is_array($cartArticle['availability']) ? $cartArticle['availability']['DATE_FROM'] : false),
              'AVAILABILITY_TIME_FROM' => (is_array($cartArticle['availability']) ? $cartArticle['availability']['TIME_FROM'] : false),
              'AVAILABILITY_DATE_TO' => (is_array($cartArticle['availability']) ? $cartArticle['availability']['DATE_TO'] : false),
              'MOQ' => $ad['MOQ'],
              'PRICE' => $ad['PREIS'],
              'SHIPPING_PRICE' => ($ad['VERSANDOPTIONEN'] == 3 ? ($cartArticle['shippingCost'] !== null ? $cartArticle['shippingCost'] : $ad['VERSANDKOSTEN']) : 0),
              'TOTAL_ARTICLE_PRICE' => $cartArticle['quantity'] * $ad['PREIS'],
              'TOTAL_PRICE' => $cartArticle['quantity'] * $ad['PREIS'],
              'OPTIONS' => $cartArticle["options"]
            );
            $ad["CART_ITEM"] = $cartArticle;
            $ad["OPTIONS"] = $cartArticle["options"];
            $totalArticlePrice += $data[$key]['TOTAL_ARTICLE_PRICE'];
            //$totalShippingPrice += $data[$key]['SHIPPING_PRICE'];
            $totalPrice += $data[$key]['TOTAL_PRICE'];
            // Versandkosten
            $shippingGroupIndex = $ad['FK_USER'].".".$cartArticle["paymentAdapter"];
            if (!isset($sumShipping[ $shippingGroupIndex ])) $sumShipping[ $shippingGroupIndex] = array();
            $sumShipping[ $shippingGroupIndex][] = $ad;
        }
        foreach ($sumShipping as $shippingGroupIndex => $arArticles) {
            $key = $ad["ID_AD_MASTER"] . "." . $ad["ID_AD_VARIANT"];
            $currentShippingPrice = $this->calculateShippingCostByItems($arArticles);
            // Add shipping cost to related prices
            $totalShippingPrice += $currentShippingPrice;
            $totalPrice += $currentShippingPrice;
        }

        $this->articleData = array(
            'TOTAL_ARTICLE_PRICE' => $totalArticlePrice,
            'TOTAL_SHIPPING_PRICE' => $totalShippingPrice,
            'TOTAL_PRICE' => $totalPrice,
            'ARTICLES' => $data
        );

        $this->dirty = false;
    }

	public function setCartPaymentAdapter($paymentAdapterId) {
		self::$cartPaymentAdapter = $paymentAdapterId;
		$this->_saveCart();
	}

	public function setCartItemPaymentAdapter($adId, $adVariantId, $paymentAdapterId) {
		self::$cart[self::$cartType][$adId.".".$adVariantId]['paymentAdapter'] = $paymentAdapterId;
		$this->_saveCart();
	}

	public function getCartPaymentAdapter() {
		return self::$cartPaymentAdapter;
	}

	public function getCartItemPaymentAdapter($adId, $adVariantId) {
		if (isset(self::$cart[self::$cartType][$adId.".".$adVariantId]['paymentAdapter'])) {
			return self::$cart[self::$cartType][$adId.".".$adVariantId]['paymentAdapter'];
		} else {
			return $this->getCartPaymentAdapter();
		}
	}
    
    public function getCartInvoiceAddress() {
        $arResult = (array_key_exists("addressInvoice", self::$cartUserData) ? self::$cartUserData["addressInvoice"] : array());
        if (empty($arResult) && ($GLOBALS["uid"] > 0)) {
            $arResult = $GLOBALS["user"]["FK_USER_INVOICE"];
        }
        if (!is_array($arResult)) {
            require_once $GLOBALS["ab_path"]."sys/lib.user_invoice.php";
            $userInvoice = UserInvoice::getInstance($GLOBALS["db"]);
            $idAddress = $arResult;
            $arResult = $userInvoice->getAddress($idAddress);
            $arResult["ID"] = $idAddress;
        }
        return $arResult;
    }
    
    public function getCartShippingAddress() {
        $arResult = (array_key_exists("addressShipping", self::$cartUserData) ? self::$cartUserData["addressShipping"] : array());
        if (empty($arResult) && ($GLOBALS["uid"] > 0)) {
            $arResult = $GLOBALS["user"]["FK_USER_VERSAND"];
        }
        if (!is_array($arResult)) {
            require_once $GLOBALS["ab_path"]."sys/lib.user_versand.php";
            $userVersand = UserVersand::getInstance($GLOBALS["db"]);
            $idAddress = $arResult;
            $arResult = $userVersand->getAddress($idAddress);
            $arResult["ID"] = $idAddress;
        }
        return $arResult;
    }
    
    public function isCartShippingAddressEqualToInvoice() {
        return (array_key_exists("addressShippingUseInvoice", self::$cartUserData) ? self::$cartUserData["addressShippingUseInvoice"] : false);
    }

    public function setCartInvoiceAddress($arInvoiceAddress, $useAsShipping = false) {
        self::$cartUserData["addressInvoice"] = $arInvoiceAddress;
        if ($useAsShipping) {
            self::$cartUserData["addressShipping"] = $arInvoiceAddress;
        }
        self::$cartUserData["addressShippingUseInvoice"] = $useAsShipping;
		    $this->_saveCart();
    }

    public function setCartShippingAddress($arShippingAddress) {
        self::$cartUserData["addressShipping"] = $arShippingAddress;
		    $this->_saveCart();
    }

    public function updateAddress($arData, &$errors, &$template) {
        $arAddressUser = array();
        $arAddressInvoice = array();
        $arAddressShipping = array();
        if (!$GLOBALS['uid']) {
            if ($GLOBALS['nar_systemsettings']['MARKTPLATZ']['BUYING_UNREGISTERED'] == 0) {
                $template->addvar("err_buying_unregistered_not_allowed", 1);
                $errors[] = "error_buying_unregistered_not_allowed";
            }
    
            $addressValid = true;
            $addressRequiredFields = array('INVOICE_EMAIL', 'INVOICE_STREET', 'INVOICE_ZIP', 'INVOICE_CITY', 'INVOICE_FK_COUNTRY');
            foreach ($addressRequiredFields as $fieldName) {
                if (empty($arData[$fieldName])) {
                    // Required field missing
                    $template->addvar("err_" . $fieldName, 1);
                    $template->addvar("err_address_required", 1);
                    $addressValid = false;
                }
            }
            if (empty($arData['INVOICE_FIRSTNAME']) || empty($arData['INVOICE_LASTNAME'])) {
                // No full name and no company name
                $addressValid = false;
                $template->addvar("err_INVOICE_FIRSTNAME", 1);
                $template->addvar("err_INVOICE_LASTNAME", 1);
                $template->addvar("err_INVOICE_COMPANY", 1);
                $template->addvar("err_address_name_or_company", 1);
            }
            if (!isset($arData['VERSAND_USE_INVOICE'])) {
                // Invoice address is not being used as shipping address, check seperately
                $addressRequiredFields = array('VERSAND_STREET', 'VERSAND_ZIP', 'VERSAND_CITY', 'VERSAND_FK_COUNTRY');
                foreach ($addressRequiredFields as $fieldName) {
                    if (empty($arData[$fieldName])) {
                        // Required field missing
                        $template->addvar("err_" . $fieldName, 1);
                        $template->addvar("err_address_required", 1);
                        $addressValid = false;
                    }
                }
                if (empty($arData['VERSAND_FIRSTNAME']) || empty($arData['VERSAND_LASTNAME'])) {
                    // No full name and no company name
                    $addressValid = false;
                    $template->addvar("err_VERSAND_FIRSTNAME", 1);
                    $template->addvar("err_VERSAND_LASTNAME", 1);
                    $template->addvar("err_VERSAND_COMPANY", 1);
                    $template->addvar("err_address_name_or_company", 1);
                }
            }
            if ($addressValid) {
                $arAddressInvoice = array(
                  "EMAIL" => $arData['INVOICE_EMAIL'],
                  "FIRSTNAME" => $arData['INVOICE_FIRSTNAME'],
                  "LASTNAME" => $arData['INVOICE_LASTNAME'],
                  "COMPANY" => $arData['INVOICE_COMPANY'],
                  "STREET" => $arData['INVOICE_STREET'],
                  "ZIP" => $arData['INVOICE_ZIP'],
                  "CITY" => $arData['INVOICE_CITY'],
                  "FK_COUNTRY" => $arData['INVOICE_FK_COUNTRY'],
                  "PHONE" => $arData['INVOICE_PHONE']
                );
                $arAddressUser = array(
                  "EMAIL" => $arAddressInvoice['EMAIL'],
                  "VORNAME" => $arAddressInvoice['FIRSTNAME'],
                  "NACHNAME" => $arAddressInvoice['LASTNAME'],
                  "FIRMA" => $arAddressInvoice['COMPANY'],
                  "STRASSE" => $arAddressInvoice['STREET'],
                  "PLZ" => $arAddressInvoice['ZIP'],
                  "ORT" => $arAddressInvoice['CITY'],
                  "FK_COUNTRY" => $arAddressInvoice['FK_COUNTRY'],
                  "TEL" => $arAddressInvoice['PHONE']
                );
                if ($arData['VERSAND_USE_INVOICE']) {
                    $arAddressShipping = $arAddressInvoice;
                } else {
                    $arAddressShipping = array(
                      "EMAIL" => $arData['INVOICE_EMAIL'],
                      "FIRSTNAME" => $arData['VERSAND_FIRSTNAME'],
                      "LASTNAME" => $arData['VERSAND_LASTNAME'],
                      "COMPANY" => $arData['VERSAND_COMPANY'],
                      "STREET" => $arData['VERSAND_STREET'],
                      "ZIP" => $arData['VERSAND_ZIP'],
                      "CITY" => $arData['VERSAND_CITY'],
                      "FK_COUNTRY" => $arData['VERSAND_FK_COUNTRY'],
                      "PHONE" => $arData['VERSAND_PHONE']
                    );
                }
            } else {
                $template->addvar("err_address", 1);
                $errors[] = "error_address";
            }
            $isVirtualUserBuy = true;
        } else {
            $isVirtualUserBuy = false;
        }
    
        $this->setCartInvoiceAddress( 
          ((array_key_exists("ID_USER_INVOICE", $arData) || !$isVirtualUserBuy) ? $arData["ID_USER_INVOICE"] : $arAddressInvoice),
          (array_key_exists("VERSAND_USE_INVOICE", $arData) ? $arData["VERSAND_USE_INVOICE"] : false)
        );
        if (!$this->isCartShippingAddressEqualToInvoice()) {
          $this->setCartShippingAddress( ((array_key_exists("ID_USER_VERSAND", $arData) || !$isVirtualUserBuy) ? $arData["ID_USER_VERSAND"] : $arAddressShipping) );
        }
    }

    public function flush() {
        self::$cart = array(self::$cartType => array());
		self::$cartPaymentAdapter = 0;
        $this->_saveCart();
    }

    /**
     * @return float
     */
    public function getTotalPrice() {
        if($this->dirty === TRUE) {
            $this->cacheArticleData();
        }

        return $this->articleData['TOTAL_PRICE'];
    }

    public function getTotalShippingPrice() {
        if($this->dirty === TRUE) {
            $this->cacheArticleData();
        }

        return $this->articleData['TOTAL_SHIPPING_PRICE'];
    }


    public function getTotalArticlePrice() {
        if($this->dirty === TRUE) {
            $this->cacheArticleData();
        }

        return $this->articleData['TOTAL_ARTICLE_PRICE'];
    }

    private function _loadCart()
    {
        if (isset($_SESSION['cart'])) {
            $tmp = unserialize($_SESSION['cart']);
            self::$cart = $tmp['cart'];
            self::$cartPaymentAdapter = $tmp['cartPaymentAdapter'];
            self::$cartUserData = (is_array($tmp['cartUserData']) ? $tmp['cartUserData'] : array());
        } else {
            self::$cart = array(self::$cartType => array());
            self::$cartPaymentAdapter = 0;
            self::$cartUserData = array();
        }
    }

    private function _saveCart()
    {
        $this->dirty = true;
        $_SESSION['cart'] = serialize(array(
          'cart' => self::$cart,
          'cartPaymentAdapter' => self::$cartPaymentAdapter,
          'cartUserData' => self::$cartUserData
        ));
    }

	private function __construct() {
	}

    private function __clone() {
	}


}