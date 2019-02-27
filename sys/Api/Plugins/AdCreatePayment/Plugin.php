<?php

class Api_Plugins_AdCreatePayment_Plugin extends Api_TraderApiPlugin {

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
        $this->registerEvent(Api_TraderApiEvents::IMPORT_EBAY_EDIT, "importEbayEdit");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CONFIRM, "marketplaceAdConfirm");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE, "marketplaceAdCreate");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATED, "marketplaceAdCreated");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_UPDATE, "marketplaceAdUpdate");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_UPDATED, "marketplaceAdUpdated");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_IMPORT_LIVE, "marketplaceAdImportLive");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_IMPORT_TEST, "marketplaceAdImportTest");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_IMPORT_START, "marketplaceAdImportStart");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_IMPORT_FINISH, "marketplaceAdImportFinish");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_PAY, "invoicePay");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_UNPAY, "invoiceUnpay");
        $this->registerEvent(Api_TraderApiEvents::SYSTEM_CACHE_TRANSLATIONS, "systemCacheTranslations");
        return true;
    }

    /**
     * Returns the configuration form for this plugin
     * @return Template
     */
    public function getConfigurationForm() {
        global $db;
        $idTaxActive = (array_key_exists("TAX_ID", $this->pluginConfiguration) ? $this->pluginConfiguration["TAX_ID"] : $GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["TAX_DEFAULT"]);
        $taxPercent = 0;
        $arTaxList = $db->fetch_table("SELECT * FROM `tax` ORDER BY TAX_VALUE ASC");
        foreach ($arTaxList as $index => $arTax) {
            if ($arTax["ID_TAX"] == $idTaxActive) {
                $arTaxList[$index]["SELECTED"] = true;
                $taxPercent = $arTax["TAX_VALUE"];
            }
        }
        $arTaxListTpl = $this->utilGetTemplateList("config.row_tax.htm", $arTaxList);
        $arPriceList = (array_key_exists("STEPS", $this->pluginConfiguration) ? $this->pluginConfiguration["STEPS"] : array());
        foreach ($arPriceList as $index => $arPrice) {
            $arPriceList[$index]["PRICE_MIN_BRUTTO"] = (round($arPrice["PRICE_MIN"] * (100 + $taxPercent)) / 100);
        }
        $arPriceListTpl = $this->utilGetTemplateList("config.row_price.htm", $arPriceList);
        $tplConfig = parent::getConfigurationForm();
        $tplConfig->addvar("liste_prices", $arPriceListTpl);
        $tplConfig->addvar("liste_tax", $arTaxListTpl);
        if (array_key_exists("CAP_MIN", $this->pluginConfiguration)) {
            $tplConfig->addvar("CONFIG_CAP_MIN_BRUTTO", round($this->pluginConfiguration["CAP_MIN"] * (100 + $taxPercent)) / 100);
        }
        if (array_key_exists("CAP_MAX", $this->pluginConfiguration)) {
            $tplConfig->addvar("CONFIG_CAP_MAX_BRUTTO", round($this->pluginConfiguration["CAP_MAX"] * (100 + $taxPercent)) / 100);
        }
        return $tplConfig;
    }

    /**
     * Updates the configuration of the plugin (usually done by posting the plugin config form)
     * @param array $arConfig
     * @return bool
     */
    public function setConfiguration($arConfig) {
        $arPriceList = $arConfig["STEPS"];
        // Convert inputs to numbers
        foreach ($arPriceList["PRICE_MIN"] as $index => $value) {
            if ($value !== "") {
                $arPriceList["PRICE_MIN"][$index] = (float)str_replace(",", ".", $arPriceList["PRICE_MIN"][$index]);
            }
        }
        foreach ($arPriceList["PRICE_PERCENT"] as $index => $value) {
            if ($value !== "") {
                $arPriceList["PRICE_PERCENT"][$index] = (float)str_replace(",", ".", $arPriceList["PRICE_PERCENT"][$index]);
            }
        }
        // Sort by price
        array_multisort($arPriceList["PRICE_MIN"], $arPriceList["PRICE_PERCENT"]);
        // Convert into list of assoc arrays
        $arConfig["STEPS"] = array();
        $count = min(count($arPriceList["PRICE_MIN"]), count($arPriceList["PRICE_PERCENT"]));
        for ($index = 0; $index < $count; $index++) {
            if (($arPriceList["PRICE_MIN"][$index] !== "") && ($arPriceList["PRICE_PERCENT"][$index] !== "")) {
                $arConfig["STEPS"][] = array(
                    "PRICE_MIN"     => $arPriceList["PRICE_MIN"][$index],
                    "PRICE_PERCENT" => $arPriceList["PRICE_PERCENT"][$index]
                );
            }
        }
        // Save configuration
        return parent::setConfiguration($arConfig);
    }
    
    public function createInvoiceForAds($arAdIds, $userId, $priceMin = 2, $priceMax = 100, $pricePercent = 5) {
        global $db, $ab_path;
        $price = $this->getPriceForAds($arAdIds, $priceMin, $priceMax, $pricePercent);
        if ($price <= 0) {
            // Do not create invoice if free
            return true;
        }
        $label = Translation::readTranslation("marketplace", "ad.create.payment.description", null, array('COUNT' => '"'.count($arAdIds).'"' ), "Einstellgebühr für {COUNT} Anzeigen");
        // Rechnung stellen
        require_once $ab_path."sys/lib.billing.invoice.php";
        require_once $ab_path."sys/lib.billing.billableitem.php";
        $billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
        $billingBillableItemManagement = BillingBillableItemManagement::getInstance($db);
        // Rechnungsposition hinzufügen
        $ar_billing_items = array(array(
            "DESCRIPTION" 	=> $label,
            "QUANTITY"		=> 1,
            "PRICE"			=> $price,
            "FK_TAX"		=> (array_key_exists("TAX_ID", $this->pluginConfiguration) ? $this->pluginConfiguration["TAX_ID"] : $GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["TAX_DEFAULT"]),
            "REF_TYPE"		=> BillingInvoiceItemManagement::REF_TYPE_DEFAULT,
            "REF_FK"		=> NULL
        ));
        // Create invoice
        $ar_billingdata = array(
            "FK_USER" => $userId,
            "__items" => $ar_billing_items
        );
        $id_invoice = $billingInvoiceManagement->createInvoice($ar_billingdata);
        // Create link to invoice
        $arInserts = array();
        foreach ($arAdIds as $adIndex => $adId) {
            $arInserts[] = "(".(int)$adId.", ".(int)$id_invoice.")";
        }
        $query = "INSERT IGNORE INTO `ad_invoice` (ID_AD_MASTER, ID_INVOICE) VALUES ".implode(", ", $arInserts);
        $db->querynow($query);
        return true;
    }

    public function getPriceForAds($arAdIds) {
        global $db;
        $arAds = $db->fetch_table("SELECT ID_AD_MASTER, PREIS FROM `ad_master` WHERE ID_AD_MASTER IN (".implode(",", $arAdIds).")");
        return $this->getPriceForAdsRaw($arAds);
    }

    public function getPriceForAdsRaw($arAds) {
        $price = 0;
        foreach ($arAds as $adIndex => $arAd) {
            $priceAd = self::getPriceForAd($arAd);
            $price += $priceAd;
        }
        return $price;
    }
    
    public function getPriceForAd($arAdData) {
        $pricePercent = 0;
        if (array_key_exists("STEPS", $this->pluginConfiguration)) {
            foreach ($this->pluginConfiguration["STEPS"] as $stepIndex => $arStep) {
                if ($arAdData["PREIS"] >= $arStep["PRICE_MIN"]) {
                    $pricePercent = $arStep["PRICE_PERCENT"];
                } else {
                    break;
                }
            }
        }
        $priceAd = floor($arAdData["PREIS"] * $pricePercent) / 100;
        if (array_key_exists("CAP_MIN", $this->pluginConfiguration)) {
            $priceAd = max($priceAd, $this->pluginConfiguration["CAP_MIN"]);
        }
        if (array_key_exists("CAP_MAX", $this->pluginConfiguration)) {
            $priceAd = min($priceAd, $this->pluginConfiguration["CAP_MAX"]);
        }
        return $priceAd;
    }

    private function getAdPaymentRequired($arArticleData) {
        global $db;
        if (array_key_exists("ID_AD_MASTER", $arArticleData)) {
            $adId = (int)$arArticleData["ID_AD_MASTER"];
            $adInvoice = (int)$db->fetch_atom("SELECT ID_INVOICE FROM `ad_invoice` WHERE ID_AD_MASTER=".$adId);
            if ($adInvoice > 0) {
                return false;
            }
        }
        return true;
    }

    public function importEbayEdit(Api_Entities_EventParamContainer $params) {
        global $db;
        /**
         * @var Ad_Import_Preset_Type_eBayPreset $ebayPreset
         */
        $ebayPreset = $params->getParam("preset");
        $info = $params->getParam("pluginInfo");
        $arArticles = array();
        $arArticlesSelected = $params->getParam("selected");
        $arArticlesActive = $ebayPreset->ebayGetArticlesIds("ActiveList", array("ItemID", "SellingStatus"));
        $arArticlesPresent = $db->fetch_nar("SELECT IMPORT_IDENTIFIER FROM `ad_master` WHERE FK_USER=".$ebayPreset->getOwnerUser()." AND IMPORT_IDENTIFIER IS NOT NULL", 0, 1);
        foreach ($arArticlesActive as $articleIndex => $articleData) {
            if (in_array($articleData["ItemID"], $arArticlesSelected) && !in_array($articleData["ItemID"], $arArticlesPresent)) {
                $arArticles[] = array("ID_EBAY" => $articleData["ItemID"], "PREIS" => $articleData["SellingStatus_ConvertedCurrentPrice"]);
            }
        }
        $taxId = (array_key_exists("TAX_ID", $this->pluginConfiguration) ? $this->pluginConfiguration["TAX_ID"] : $GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["TAX_DEFAULT"]);
        $taxPercent = $db->fetch_atom("SELECT TAX_VALUE FROM `tax` WHERE ID_TAX=".$taxId);
        $price = $this->getPriceForAdsRaw($arArticles);
        $priceBrutto = round($price * (100 + $taxPercent)) / 100;
        if ($price > 0) {
            $tplPrice = $this->utilGetTemplate("importPriceInfo.htm");
            $tplPrice->addvar("PREIS", $price);
            $tplPrice->addvar("PREIS_BRUTTO", $priceBrutto);
            $tplPrice->addvar("COUNT", count($arArticles));
            if (array_key_exists("STEPS", $this->pluginConfiguration)) {
                $tplPrice->addvar("LISTE_PREIS", $this->utilGetTemplateList("importPriceInfo.price.htm", $this->pluginConfiguration["STEPS"]));
                $tplPrice->addvar("LISTE_PROZENT", $this->utilGetTemplateList("importPriceInfo.percent.htm", $this->pluginConfiguration["STEPS"]));
            }
            if (array_key_exists("CAP_MIN", $this->pluginConfiguration)) {
                $tplPrice->addvar("PREIS_MIN", $this->pluginConfiguration["CAP_MIN"]);
                $tplPrice->addvar("PREIS_MIN_BRUTTO", round($this->pluginConfiguration["CAP_MIN"] * (100 + $taxPercent)) / 100);
            }
            if (array_key_exists("CAP_MAX", $this->pluginConfiguration)) {
                $tplPrice->addvar("PREIS_MAX", $this->pluginConfiguration["CAP_MAX"]);
                $tplPrice->addvar("PREIS_MAX_BRUTTO", round($this->pluginConfiguration["CAP_MAX"] * (100 + $taxPercent)) / 100);
            }
            $tplPrice->addvar("CURRENCY_DEFAULT", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);
            $info .= $tplPrice->process(true);
        }
        $params->setParam("pluginInfo", $info);
    }

    public function marketplaceAdConfirm(Api_Entities_EventParamContainer $params) {
        $adNeedsPayment = $this->getAdPaymentRequired( $params->getParam("data") );
        if ($adNeedsPayment) {
            /**
             * @var Template $tpl
             */
            global $db;
            $tpl = $params->getParam("template");
            $taxId = (array_key_exists("TAX_ID", $this->pluginConfiguration) ? $this->pluginConfiguration["TAX_ID"] : $GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["TAX_DEFAULT"]);
            $taxPercent = $db->fetch_atom("SELECT TAX_VALUE FROM `tax` WHERE ID_TAX=".$taxId);
            $price = self::getPriceForAd( $params->getParam("data") );
            $priceBrutto = round($price * (100 + $taxPercent)) / 100;
            $contentBefore = $this->utilGetTemplate("createPaymentNotice.htm");
            $contentBefore->addvar("PREIS", $price);
            $contentBefore->addvar("PREIS_BRUTTO", $priceBrutto);
            $contentBefore->addvar("CURRENCY_DEFAULT", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);
            if (array_key_exists("CONFIRM_CONTENT_BEFORE", $tpl->vars)) {
                $tpl->vars["CONFIRM_CONTENT_BEFORE"] .= $contentBefore->process(false);
            } else {
                $tpl->vars["CONFIRM_CONTENT_BEFORE"] = $contentBefore->process(false);
            }
        }
    }
    
    public function marketplaceAdCreate(Api_Entities_EventParamContainer $params) {
        // Prevent cron from enabling this ad
        $params->setParamArrayValue("data", "CRON_DONE", 1);
        $params->setParamArrayValue("data", "ADMIN_STAT", 2);
        // Prevent enabling this ad after creation
        $params->setParam("enable", false);        
    }
    
    public function marketplaceAdCreated(Api_Entities_EventParamContainer $params) {
        if ($params->getParam("recreate") === true) {
            // Do not create invoice upon recreation, wait until confirming the ad.
            return;
        }
        $adNeedsPayment = $this->getAdPaymentRequired( $params->getParam("data") );
        if ($adNeedsPayment) {
            // Ensure new ads are initially disabled
            Ad_Marketplace::disableAd( $params->getParam("id"), $params->getParamArrayValue("data", "AD_TABLE") );
            // Create invoice
            $this->createInvoiceForAds(array($params->getParam("id")), $params->getParamArrayValue("data", "FK_USER"));
        }
    }
    
    public function marketplaceAdUpdate(Api_Entities_EventParamContainer $params) {
        // Prevent cron from enabling this ad
        $params->setParamArrayValue("data", "CRON_DONE", 1);
        // Prevent enabling this ad after creation
        $params->setParam("enable", false);        
    }
    
    public function marketplaceAdUpdated(Api_Entities_EventParamContainer $params) {
        $adNeedsPayment = $this->getAdPaymentRequired( $params->getParam("data") );
        if ($adNeedsPayment) {
            // Ensure new ads are initially disabled
            Ad_Marketplace::disableAd( $params->getParam("id"), $params->getParamArrayValue("data", "AD_TABLE") );
            // Create invoice
            $this->createInvoiceForAds(array($params->getParam("id")), $params->getParamArrayValue("data", "FK_USER"));
        }
    }
    
    public function marketplaceAdImportLive(Api_Entities_EventParamContainer $params) {
        /**
         * @var Ad_Import_Process_Import_ImportManagement $importManagement
         */
        $importManagement = $params->getParam("importManagement");
        $importProcess = $importManagement->getImportProcess();
        $userId = $params->getParam("userId");
        $arAdIds = $params->getParamArrayValue("ads", "INSERT");
        if (is_array($arAdIds) && !empty($arAdIds)) {
            $arAdIds = array_merge(
                $importProcess->getConfigurationOption("_adCreatePaymentIds"), $arAdIds
            );
            $importProcess->setConfigurationOption("_adCreatePaymentIds", $arAdIds);
            $importProcess->setConfigurationOption("_adCreatePaymentCount", count($arAdIds));
        }
    }
    
    public function marketplaceAdImportTest(Api_Entities_EventParamContainer $params) {
        /**
         * @var Ad_Import_Process_Import_ImportManagement $importManagement
         */
        $importManagement = $params->getParam("importManagement");
        $importProcess = $importManagement->getImportProcess();
        $arBufferInsert = $params->getParamArrayValue("buffer", "INSERT");
        if (is_array($arBufferInsert) && !empty($arBufferInsert) && array_key_exists("ad_master", $arBufferInsert)) {
            $priceImport = $importProcess->getConfigurationOption("_adCreatePaymentPrice");
            $countImport = $importProcess->getConfigurationOption("_adCreatePaymentCount") + count($arBufferInsert["ad_master"]);
            foreach ($arBufferInsert["ad_master"] as $adIndex => $adData) {
                $priceImport += $this->getPriceForAd($adData);
            }
            $importProcess->setConfigurationOption("_adCreatePaymentCount", $countImport);
            $importProcess->setConfigurationOption("_adCreatePaymentPrice", $priceImport);
        }
    }
    
    public function marketplaceAdImportStart(Api_Entities_EventParamContainer $params) {
        /**
         * @var Ad_Import_Process_Process $importProcess
         */
        $importProcess = $params->getParam("importProcess");
        $importProcess->setConfigurationOption("_adCreatePaymentIds", array());
        $importProcess->setConfigurationOption("_adCreatePaymentCount", 0);
        $importProcess->setConfigurationOption("_adCreatePaymentPrice", 0);
    }
    
    public function marketplaceAdImportFinish(Api_Entities_EventParamContainer $params) {
        /**
         * @var Ad_Import_Process_Process $importProcess
         */
        $importProcess = $params->getParam("importProcess");
        $priceImport = $importProcess->getConfigurationOption("_adCreatePaymentPrice");
        $countImport = $importProcess->getConfigurationOption("_adCreatePaymentCount");
        if ($countImport > 0) {
            global $db;
            $testMode = $importProcess->getConfigurationOption('testMode');
            if (!$testMode) {
                $arAdIds = $importProcess->getConfigurationOption("_adCreatePaymentIds");
                if (!empty($arAdIds)) {
                    $this->createInvoiceForAds( $arAdIds, $importProcess->getUserId() );
                    $priceImport = $this->getPriceForAds($arAdIds);
                }
            }
            $taxId = (array_key_exists("TAX_ID", $this->pluginConfiguration) ? $this->pluginConfiguration["TAX_ID"] : $GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["TAX_DEFAULT"]);
            $taxPercent = $db->fetch_atom("SELECT TAX_VALUE FROM `tax` WHERE ID_TAX=".$taxId);
            $priceImportBrutto = round($priceImport * (100 + $taxPercent)) / 100;
            $tplPrice = $this->utilGetTemplate("importPaymentNotice.htm");
            $tplPrice->addvar("COUNT", $countImport);
            $tplPrice->addvar("PREIS", $priceImport);
            $tplPrice->addvar("PREIS_BRUTTO", $priceImportBrutto);
            $tplPrice->addvar("TEST_MODE", $testMode);
            $tplPrice->addvar("CURRENCY_DEFAULT", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);
            $importProcess->log( $tplPrice->process() );
        }
    }
    
    public function invoicePay($arParams) {
        global $db;
        $id = (int)$arParams['id'];
        $arAds = $db->fetch_nar("
            SELECT a.ID_AD_MASTER, a.AD_TABLE FROM `ad_invoice` i, `ad_master` a
            WHERE a.ID_AD_MASTER=i.ID_AD_MASTER AND i.ID_INVOICE=".$id);
        if (!empty($arAds)) {
            // Unlock ads
            $db->querynow("UPDATE `ad_master` SET ADMIN_STAT=0 WHERE ID_AD_MASTER IN (".implode(",", array_keys($arAds)).")");
            Ad_Marketplace::enableAdsEx($arAds);
        }
    }
    
    public function invoiceUnpay($arParams) {
        global $db;
        $id = (int)$arParams['id'];
        $arAds = $db->fetch_nar("
            SELECT a.ID_AD_MASTER, a.AD_TABLE FROM `ad_invoice` i, `ad_master` a
            WHERE a.ID_AD_MASTER=i.ID_AD_MASTER AND i.ID_INVOICE=".$id);
        if (!empty($arAds)) {
            // Lock ads
            $db->querynow("UPDATE `ad_master` SET ADMIN_STAT=2 WHERE ID_AD_MASTER IN (" . implode(",", array_keys($arAds)) . ")");
            Ad_Marketplace::disableAdsEx($arAds);
        }
    }
    
    public function systemCacheTranslations(Api_Entities_EventParamContainer $params) {
        switch ($params->getParam("namespace")) {
            case "marketplace":
                if (!$params->getParamArrayKeyExists("translations", "ad.create.payment.description")) {
                    $params->setParamArrayValue("translations", "ad.create.payment.description", "Einstellgebühr für {COUNT} Anzeigen");
                }
                break;
        }
    }
}