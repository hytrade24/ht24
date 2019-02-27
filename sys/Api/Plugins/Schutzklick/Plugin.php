<?php

class Api_Plugins_Schutzklick_Plugin extends Api_TraderApiPlugin {

    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 0;
    }

    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        $this->registerEvent(Api_TraderApiEvents::AJAX_PLUGIN, "ajaxCall");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_CART_CHECKOUT, "marketplaceCartCheckout");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_CART_CHECKOUT_SUCCESS, "marketplaceCartCheckoutSuccess");
        return true;
    }
    
    public function ajaxCall(Api_Entities_EventParamContainer $params) {
        // e.g.: /index.php?pluginAjax=Schutzklick&pluginAjaxAction=country.resolve
        $ajaxAction = $params->getParam("action");
        switch ($ajaxAction) {
            case "country.resolve":
                $idCountry = (int)$_POST["id"];
                $abbrCountry = $this->db->fetch_atom("SELECT CODE FROM `country` WHERE ID_COUNTRY=".$idCountry);
                header("Content-Type: application/json");
                die(json_encode(array("success" => (strlen($abbrCountry) == 2 ? true : false), "code" => $abbrCountry)));
        }
    }
    
    public function marketplaceCartCheckout(Api_Entities_EventParamContainer $params) {
        $arArticlesJson = array();
        foreach ($params->getParam("articleGroups") as $groupIndex => $arArticles) {
            foreach ($arArticles as $articleIndex => $articleDetails) {
                $arArticlesJson[] = array(
                    "id"            => $articleDetails["ID_AD_MASTER"],
                    "categories"    => array(
                        array($articleDetails["FK_KAT"] => $articleDetails["KAT"])
                    ),
                    "name"          => $articleDetails["PRODUKTNAME"],
                    "price"         => $articleDetails["PREIS"],
                    "currency"      => "EUR",
                    "sku"           => sprintf("%07u", $articleDetails["MENGE"]),
                    "qty"           => $articleDetails["CART_QUANTITY"]
                );
            }
        }

        $tplCartCheckout = $this->utilGetTemplate("cart_checkout.htm");
        $tplCartCheckout->addvar("JSON_ARTICLES", json_encode($arArticlesJson));
        $tplCartCheckout->addvar("CONFIG_PARTNER_ID", $this->pluginConfiguration["PARTNER_ID"]);
        $tplCartCheckout->addvar("CONFIG_SHOP_ID", $this->pluginConfiguration["SHOP_ID"]);
        $tplCartCheckout->addvar("CONFIG_COUNTRY", $this->pluginConfiguration["COUNTRY"]);
        
        $params->setParam("pluginHtml", $params->getParam("pluginHtml")."\n".$tplCartCheckout->process());
        #die(var_dump($params));
        #die(json_encode($arArticlesJson));
        #die($tplCartCheckout->process());
    }
    
    public function marketplaceCartCheckoutSuccess(Api_Entities_EventParamContainer $params) {
        $orderId = (int)$params->getParam("orderId");
        $arOrderUser = $this->db->fetch1("
          SELECT
            u.EMAIL as EMAIL,
            s.INVOICE_FIRMA as FIRMA,
            s.INVOICE_VORNAME as VORNAME,
            s.INVOICE_NACHNAME as NACHNAME,
            s.INVOICE_TEL as TEL,
            s.INVOICE_STRASSE as STRASSE,
            s.INVOICE_PLZ as PLZ,
            s.INVOICE_ORT as ORT,
            (SELECT LOWER(CODE) FROM `country` WHERE ID_COUNTRY=s.INVOICE_FK_COUNTRY) AS COUNTRY_CODE
          FROM `ad_sold` s
          JOIN `ad_order` o ON s.FK_AD_ORDER=o.ID_AD_ORDER
          JOIN `user` u ON u.ID_USER=o.FK_USER 
          WHERE o.ID_AD_ORDER=".$orderId);
        if (is_array($arOrderUser)) {
            if (preg_match("/^(.+) ([0-9]+.*)$/i", $arOrderUser["STRASSE"], $arStreetMatch)) {
                $arOrderUser["STRASSE_NAME"] = $arStreetMatch[1];
                $arOrderUser["STRASSE_NUMMER"] = $arStreetMatch[2];
            } else {
                $arOrderUser["STRASSE_NAME"] = $arOrderUser["STRASSE"];
                $arOrderUser["STRASSE_NUMMER"] = "";
            }
            $tplCartCheckout = $this->utilGetTemplate("cart_checkout_success.htm");
            $tplCartCheckout->addvar("ORDER_ID", $orderId);
            $tplCartCheckout->addvars($arOrderUser, "USER_");
            $tplCartCheckout->addvar("CONFIG_PARTNER_ID", $this->pluginConfiguration["PARTNER_ID"]);
            $tplCartCheckout->addvar("CONFIG_SHOP_ID", $this->pluginConfiguration["SHOP_ID"]);
            $tplCartCheckout->addvar("CONFIG_COUNTRY", $arOrderUser["COUNTRY_CODE"]);
            
            $params->setParam("pluginHtml", $params->getParam("pluginHtml")."\n".$tplCartCheckout->process());
        }
        #die(var_dump($params));
    }
}