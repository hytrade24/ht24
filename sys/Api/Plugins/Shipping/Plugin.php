<?php

class Api_Plugins_Shipping_Plugin extends Api_TraderApiPlugin {

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
        $this->registerEvent(Api_TraderApiEvents::AJAX_PLUGIN, "ajaxPlugin");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_SHIPPING, "marketplaceAdCreateShipping");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_SUBMIT_STEP, "marketplaceAdCreateSubmitStep");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_SHIPPING_DISPLAY, "marketplaceAdShippingDisplay");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_CART_ARTICLE_UPDATE, "marketplaceCartArticleUpdate");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_CART_ARTICLE_SHIPPING, "marketplaceCartArticleShipping");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_CART_ARTICLES_GROUP, "marketplaceCartArticlesGroup");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_CART_VIEW, "marketplaceCartView");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_CART_CHECKOUT, "marketplaceCartCheckout");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_ORDER_GROUP, "marketplaceOrderGroup");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_ORDER_SALES_SEARCH_FORM, "marketplaceOrderSalesSearchForm");
        $this->registerEvent(Api_TraderApiEvents::USER_SETTINGS, "userSettings");
        $this->registerEvent(Api_TraderApiEvents::USER_SETTINGS_SUBMIT, "userSettingsSubmit");
        return true;
    }
    
    private function getUserShippingGroups($userId = null) {
        // Default options
        if ($userId === null) {
            $userId = $GLOBALS["uid"];
        }
        // Error check
        if ((int)$userId <= 0) {
            // Invalid user id
            return false;
        }
        // Get user object
        $user = Api_UserManagement::getInstance($this->db)->fetchOneAsObject(array("ID_USER" => $userId));
        // Get configured shipping groups
        $arResult = array();
        $userShippingConfig = $GLOBALS["ab_path"].$user->getCacheDir()."shipping.json";
        if (file_exists($userShippingConfig)) {
            $arResult = ShippingGroup::fromJsonList( file_get_contents($userShippingConfig) );
        }
        return $arResult;
    }
    
    private function getUserShippingOptions($userId = null) {
        $arShippingGroups = $this->getUserShippingGroups();
        if ($_SESSION["pluginShipping_CustomGroup"] instanceof ShippingGroup) {
            $_SESSION["pluginShipping_CustomGroup"]->setCustom(true);
            array_unshift($arShippingGroups, $_SESSION["pluginShipping_CustomGroup"]);
        }
        return $this->utilGetTemplateList("shipping.row.option.htm", $arShippingGroups);
    }
    
    private function getShippingProviderPrice(ShippingGroup $configuration, $providerIdent, $countryId) {
        // Get providers and regions
        $providerIndex = $configuration->getProviderIndex($providerIdent);
        if ($providerIndex === null) {
            // Provider not found
            return null;
        }
        $arRegionsSelected = $configuration->getRegions();
        $arCountryGroups = (array_key_exists("country_group", $arRegionsSelected) ? $arRegionsSelected["country_group"] : array());
        $arCountries = (array_key_exists("country", $arRegionsSelected) ? $arRegionsSelected["country"] : array());
        $arRegions = $this->getRegionList($configuration, $arCountryGroups, $arCountries);
        // Prepare price fallback
        $arPriceFallback = array(null);
        // Add region rows
        foreach ($arRegions as $regionIndex => $regionData) {
            $arResult = array();
            // Remove obsolete fallbacks
            array_splice($arPriceFallback, $regionData["NS_LEVEL"]);
            // Get current price
            $priceCurrent = null;
            if (array_key_exists("ID_COUNTRY", $regionData)) {
                $priceCurrent = $configuration->getProviderPriceForCountry($providerIdent, $regionData["ID_COUNTRY"]);
            }
            if (array_key_exists("ID_COUNTRY_GROUP", $regionData)) {
                $priceCurrent = $configuration->getProviderPriceForCountryGroup($providerIdent, $regionData["ID_COUNTRY_GROUP"]);
            }
            $priceCurrentFinal = ($priceCurrent === null ? $arPriceFallback[ $regionData["NS_LEVEL"]-1 ] : $priceCurrent);
            // Store as fallback for child categories
            $arPriceFallback[ $regionData["NS_LEVEL"] ] = $priceCurrentFinal;
            // Check for target region
            if (array_key_exists("ID_COUNTRY", $regionData) && ($regionData["ID_COUNTRY"] == $countryId)) {
                // Return result
                return ($priceCurrentFinal >= 0 ? $priceCurrentFinal : null);
            }
        }
        return null;
    }
    
    private function getShippingProviderList(ShippingGroup $configuration, $countryId) {
        // Get providers and regions
        $arProviders = $configuration->getProviders();
        $arRegionsSelected = $configuration->getRegions();
        $arCountryGroups = (array_key_exists("country_group", $arRegionsSelected) ? $arRegionsSelected["country_group"] : array());
        $arCountries = (array_key_exists("country", $arRegionsSelected) ? $arRegionsSelected["country"] : array());
        $arRegions = $this->getRegionList($configuration, $arCountryGroups, $arCountries);
        // Prepare price fallback
        $arPriceFallback = array();
        foreach ($arProviders as $providerIndex => $providerData) {
            $arPriceFallback[$providerIndex] = array(null); 
        }
        // Add region rows
        foreach ($arRegions as $regionIndex => $regionData) {
            $arResult = array();
            // Add price input fields
            foreach ($arProviders as $providerIndex => $providerData) {
                // Remove obsolete fallbacks
                array_splice($arPriceFallback[$providerIndex], $regionData["NS_LEVEL"]);
                // Get current price
                $priceCurrent = null;
                if (array_key_exists("ID_COUNTRY", $regionData)) {
                    $priceCurrent = $configuration->getProviderPriceForCountry($providerData["IDENT"], $regionData["ID_COUNTRY"]);
                }
                if (array_key_exists("ID_COUNTRY_GROUP", $regionData)) {
                    $priceCurrent = $configuration->getProviderPriceForCountryGroup($providerData["IDENT"], $regionData["ID_COUNTRY_GROUP"]);
                }
                $priceCurrentFinal = ($priceCurrent === null ? $arPriceFallback[$providerIndex][ $regionData["NS_LEVEL"]-1 ] : $priceCurrent);
                // Store as fallback for child categories
                $arPriceFallback[$providerIndex][ $regionData["NS_LEVEL"] ] = $priceCurrentFinal;
                // Store as result
                if (($priceCurrentFinal !== null) && ($priceCurrentFinal >= 0)) {
                    $providerData["PRICE"] = $priceCurrentFinal;
                    $arResult[] = $providerData;
                }
            }
            // Check for target region
            if (array_key_exists("ID_COUNTRY", $regionData) && ($regionData["ID_COUNTRY"] == $countryId)) {
                // Return result
                return $arResult;
            }
        }
        return array();
    }

    private function getShippingConfigPriceTable(ShippingGroup $configuration, $baseTemplate = "shipping_config.prices") {
        // Prepare templates
        $tplTable = $this->utilGetTemplate($baseTemplate.".htm");
        $tplTableProvider = $this->utilGetTemplate($baseTemplate.".row.provider.htm");
        $tplTableRegion = $this->utilGetTemplate($baseTemplate.".row.region.htm");
        $tplTablePrice = $this->utilGetTemplate($baseTemplate.".row.price.htm");
        // Get providers and regions
        $arProviders = $configuration->getProviders();
        $arRegionsSelected = $configuration->getRegions();
        $arCountryGroups = (array_key_exists("country_group", $arRegionsSelected) ? $arRegionsSelected["country_group"] : array());
        $arCountries = (array_key_exists("country", $arRegionsSelected) ? $arRegionsSelected["country"] : array());
        $arRegions = $this->getRegionList($configuration, $arCountryGroups, $arCountries);
        // Prepare placeholders
        $arTplProviders = array();
        $arTplRegions = array();
        // Add provider header columns
        foreach ($arProviders as $providerIndex => $providerData) {
            $tplTableProvider->vars = $providerData;
            $arTplProviders[] = $tplTableProvider->process();
        }
        // Prepare price fallback
        $arPriceFallback = array();
        foreach ($arProviders as $providerIndex => $providerData) {
            $arPriceFallback[$providerIndex] = array(null); 
        }
        // Add region rows
        foreach ($arRegions as $regionIndex => $regionData) {
            // Open row tag
            $htmlRow = "<tr data-level='".$regionData["NS_LEVEL"]."'>\n";
            // Add region label
            $tplTableRegion->vars = $regionData;
            $htmlRow .= $tplTableRegion->process();
            // Add price input fields
            foreach ($arProviders as $providerIndex => $providerData) {
                // Remove obsolete fallbacks
                array_splice($arPriceFallback[$providerIndex], $regionData["NS_LEVEL"]);
                // Get current price
                $priceCurrent = null;
                if (array_key_exists("ID_COUNTRY", $regionData)) {
                    $priceCurrent = $configuration->getProviderPriceForCountry($providerData["IDENT"], $regionData["ID_COUNTRY"]);
                }
                if (array_key_exists("ID_COUNTRY_GROUP", $regionData)) {
                    $priceCurrent = $configuration->getProviderPriceForCountryGroup($providerData["IDENT"], $regionData["ID_COUNTRY_GROUP"]);
                }
                $priceCurrentFinal = ($priceCurrent === null ? $arPriceFallback[$providerIndex][ $regionData["NS_LEVEL"]-1 ] : $priceCurrent); 
                // Store as fallback for child categories
                $arPriceFallback[$providerIndex][ $regionData["NS_LEVEL"] ] = $priceCurrentFinal;
                // Render price template
                $tplTablePrice->vars = array_merge($regionData, $providerData);
                $tplTablePrice->vars["CURRENCY_DEFAULT"] = $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY'];
                $tplTablePrice->vars["INDEX"] = $providerIndex;
                $tplTablePrice->vars["DISABLED"] = ($priceCurrent < 0 ? true : false);
                $tplTablePrice->vars["PRICE"] = $priceCurrent;
                $tplTablePrice->vars["PRICE_FINAL"] = $priceCurrentFinal;
                $htmlRow .= $tplTablePrice->process();
            }
            // Close row tag
            $htmlRow .= "</tr>\n";
            // Add row to result
            $arTplRegions[] = $htmlRow;
        }
        // Finish template
        $tplTable->addvar("head_providers", implode("\n", $arTplProviders));
        $tplTable->addvar("body", implode("\n", $arTplRegions));
        return $tplTable->process();
    }
    
    private function getArticleShippingConfiguration($articleId) {
        $article = Api_Entities_MarketplaceArticle::getById($articleId);
        $articleJson = $article->getData_JsonAdditional();
        if (array_key_exists("SHIPPING_GROUP", $articleJson)) {
            return ShippingGroup::fromAssoc($articleJson["SHIPPING_GROUP"]);
        } else {
            return $this->getUserShippingConfiguration($article->getData_ArticleMaster("FK_USER"));
        }
    }
    
    private function getUserShippingConfiguration($userId = null) {
        if ($userId === null) {
            $arUser = $GLOBALS["user"];
        } else {
            $arUser = get_user($userId, true);
        }
        if (is_array($arUser) && is_array($arUser["JSON_ADDITIONAL"]) && array_key_exists("SHIPPING_GROUP", $arUser["JSON_ADDITIONAL"])) {
            $arConfigurations = $this->getUserShippingGroups($userId);
            return $this->getShippingConfigurationCurrent($arUser["JSON_ADDITIONAL"]["SHIPPING_GROUP"], $arConfigurations);
        }
        return null;
    }
    
    /**
     * @param int|null $index
     * @return ShippingGroup
     */
    private function getShippingConfigurationCurrent($ident = null, &$arConfigurations = null) {
        if ($arConfigurations === null) {
            $arConfigurations = $this->getUserShippingGroups();
        }
        /** @var ShippingGroup|null $configurationActive */
        $configurationActive = null;
        if ($ident == "custom") {
            $configurationActive = $_SESSION["pluginShipping_CustomGroup"];
            $configurationActive->setCustom(true);
        } else if ($ident !== null) {
            /**
             * Find existing configuration
             * @var int             $configIndex
             * @var ShippingGroup   $configObject
             */
            foreach ($arConfigurations as $configIndex => $configObject) {
                if ($configObject->getIdent() == $ident) {
                    $configurationActive = $configObject;
                    break;
                }
            }
        }
        if ($configurationActive !== null) {
            $_SESSION["pluginShipping_ActiveGroup"] = $configurationActive;
        } else if (($ident == "new") || !($_SESSION["pluginShipping_ActiveGroup"] instanceof ShippingGroup)) {
            // New configuration
            $_SESSION["pluginShipping_ActiveGroup"] = new ShippingGroup("");
        } else {
            // Default shipping configuration
            
        }
        return $_SESSION["pluginShipping_ActiveGroup"];
    }
    
    private function getRegionList(ShippingGroup $configuration, $arCountryGroupIds = null, $arCountryIds = null) {
        $countryGroups = Api_CountryGroupManagement::getInstance($this->db);
        $arRegions = array();
        $arCountryGroups = $countryGroups->fetchTree();
        $arCountryGroups[] = array(
          "ID_COUNTRY_GROUP"    => "OTHERS",
          "V1"                  => Translation::readTranslation("general", "country.group.others", null, array(), "Sonstige"),
          "NS_LEVEL"            => 1,
          "NS_LEFT"             => 1,
          "NS_RIGHT"            => 2
        );
        foreach ($arCountryGroups as $groupIndex => $groupData) {
            if (($arCountryGroupIds !== null) && !in_array($groupData["ID_COUNTRY_GROUP"], $arCountryGroupIds)) {
                // Skip groups that are not selected
                continue;
            }
            $groupData["SELECTED"] = $configuration->isCountryGroupSelected($groupData["ID_COUNTRY_GROUP"]);
            // Add country group to list
            $arRegions[] = $groupData;
            // Check for assigned countries
            if (($groupData["NS_RIGHT"] - $groupData["NS_LEFT"]) == 1) {
                // No child groups, get list of assigned countries.
                $arCountriesAssigned = $countryGroups->fetchCountryListAssigned($groupData["ID_COUNTRY_GROUP"]);
                foreach ($arCountriesAssigned as $countryIndex => $countryData) {
                    if (($arCountryIds !== null) && !in_array($countryData["ID_COUNTRY"], $arCountryIds)) {
                        // Skip countries that are not selected
                        continue;
                    }
                    $countryData["SELECTED"] = $configuration->isCountrySelected($countryData["ID_COUNTRY"]);
                    $countryData["NS_LEVEL"] = $groupData["NS_LEVEL"] + 1;
                    $arRegions[] = $countryData;
                }
            }
        }
        
        return $arRegions;
    }
    
    private function getProviderList($configOrProviderIdent) {
        $arProviders = Api_LookupManagement::getInstance($this->db)->readByArt("VERSAND_ANBIETER");
        foreach ($arProviders as $providerIndex => $providerDetails) {
            if ($configOrProviderIdent instanceof ShippingGroup) {
                $arProviders[$providerIndex]["SELECTED"] = $configOrProviderIdent->isProviderSelected($providerDetails["VALUE"]);
            } else {
                $arProviders[$providerIndex]["SELECTED"] = ($configOrProviderIdent == $providerDetails["VALUE"]);
            }
        }
        return $arProviders;
    }
    
    private function deleteShippingConfiguration($ident, $userId = null, &$error = null) {
        // Default options
        if ($userId === null) {
            $userId = $GLOBALS["uid"];
        }
        // Error check
        if ((int)$userId <= 0) {
            // Invalid user id
            return false;
        }
        $configFound = false;
        $arConfigurations = $this->getUserShippingGroups();
        foreach ($arConfigurations as $configIndex => $configObject) {
            if ($configObject->getIdent() == $ident) {
                // Configuration found, delete it!
                array_splice($arConfigurations, $configIndex, 1);
                $configFound = true;
            }
        }
        if (!$configFound) {
            $error = Translation::readTranslation("plugin", "shipping.error.config.not.found", null, array(), "Die zu löschende Konfiguration konnte nicht gefunden werden!");
            return false;
        }
        // Get user object
        $user = Api_UserManagement::getInstance($this->db)->fetchOneAsObject(array("ID_USER" => $userId));
        // Save to file!
        $userShippingConfig = $GLOBALS["ab_path"].$user->getCacheDir()."shipping.json";
        file_put_contents($userShippingConfig, json_encode($arConfigurations));
        return true;
    }

    private function saveShippingConfiguration(ShippingGroup $configuration, $userId = null, &$error = null) {
        // Default options
        if ($userId === null) {
            $userId = $GLOBALS["uid"];
        }
        // Error check
        if ((int)$userId <= 0) {
            // Invalid user id
            return false;
        }
        $arConfigurations = $this->getUserShippingGroups();
        /**
         * @var int             $configIndex
         * @var ShippingGroup   $configObject
         */
        $createNew = true;
        foreach ($arConfigurations as $configIndex => $configObject) {
            if ($configObject->getIdent() == $configuration->getIdent()) {
                $arConfigurations[$configIndex] = $configuration;
                $createNew = false;
            } else if ($configObject->getName() == $configuration->getName()) {
                // Duplicate name!
                $error = Translation::readTranslation("plugin", "shipping.error.config.name.duplicate", null, array(), "Eine Versandkonfiguration mit diesem Namen existiert bereits!");
                return false;
            }
        }
        if ($createNew) {
            $arConfigurations[] = $configuration;
        }
        // Get user object
        $user = Api_UserManagement::getInstance($this->db)->fetchOneAsObject(array("ID_USER" => $userId));
        // Save to file!
        $userShippingConfig = $GLOBALS["ab_path"].$user->getCacheDir()."shipping.json";
        file_put_contents($userShippingConfig, json_encode($arConfigurations));
        return true;
    }
    
    public function ajaxPlugin(Api_Entities_EventParamContainer $params) {
        $jsonResult = array("success" => false);
        switch ($params->getParam("action")) {
            case 'options':
                $jsonResult["options"] = implode("\n", $this->getUserShippingOptions());
                $jsonResult["success"] = true;
                break;
            case 'config':
                $configurationIdent = (array_key_exists("ident", $_POST) ? $_POST["ident"] : null);
                $articleEditMode = (array_key_exists("article", $_POST) ? true : false);
                $configuration = $this->getShippingConfigurationCurrent($configurationIdent, $arConfigurations);
                $arConfigurations = $this->getUserShippingGroups();
                $configurationIndex = array_search($configuration, $arConfigurations);                
                // Get available regions and countries
                $arRegions = $this->getRegionList($configuration);
                $arProviders = $this->getProviderList($configuration);
                // Render template
                $tplConfig = $this->utilGetTemplate("shipping_config.htm");
                $tplConfig->addvar("articleEdit", $articleEditMode);
                $tplConfig->addvar("CURRENCY_DEFAULT", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);
                $tplConfig->addvar("CURRENCY_DEFAULT", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);
                $tplConfig->addvar("configurations", $this->utilGetTemplateList("shipping_config.row.config.htm", $arConfigurations));
                $tplConfig->addvar("regions", $this->utilGetTemplateList("shipping_config.row.region.htm", $arRegions));
                $tplConfig->addvar("providers", $this->utilGetTemplateList("shipping_config.row.provider.htm", $arProviders));
                $tplConfig->addvar("formVisible", ($configurationIdent !== null));
                $tplConfig->addvar("CONFIG_NEW", $configuration->isNew());
                $tplConfig->addvar("CONFIG_INDEX", $configurationIndex);
                $tplConfig->addvars($configuration->jsonSerialize(), "CONFIG_");
                $tplConfig->addvar("prices", $this->getShippingConfigPriceTable($configuration));
                // Return result
                $jsonResult["body"] = $tplConfig->process();
                $jsonResult["success"] = true;
                break;
            case 'config_table':
                $configuration = $this->getShippingConfigurationCurrent(null);
                // Return result
                $jsonResult["table"] = $this->getShippingConfigPriceTable($configuration);
                $jsonResult["success"] = true;
                break;
            case 'modal_view_shipping':
                $articleId = (array_key_exists("id", $_REQUEST) ? $_REQUEST["id"] : null);
                $configuration = $this->getArticleShippingConfiguration($articleId);                
                // Get available regions and countries
                $arRegions = $this->getRegionList($configuration);
                // Render template
                $tplModal = $this->utilGetTemplate("view_shipping.modal.htm");
                $tplModal->addvar("CURRENCY_DEFAULT", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);
                $tplModal->addvars($configuration->jsonSerialize(), "CONFIG_");
                $tplModal->addvar("prices", $this->getShippingConfigPriceTable($configuration, "view_shipping.modal.prices"));
                die($tplModal->process());
                break;
            case 'provider_update':
                $configuration = $this->getShippingConfigurationCurrent(null);
                $arSelection = (array_key_exists("selected", $_POST) ? $_POST["selected"] : array());
                $jsonResult["success"] = $configuration->setProviders($arSelection);
                if (!$jsonResult["success"]) {
                    $jsonResult["error"] = $configuration->getLastError();
                }
                break;
            case 'provider_del':
                $configuration = $this->getShippingConfigurationCurrent(null);
                $jsonResult["success"] = $configuration->deleteProvider($_POST["ident"]);
                if (!$jsonResult["success"]) {
                    $jsonResult["error"] = $configuration->getLastError();
                }
                break;
            case 'provider_default':
                $configuration = $this->getShippingConfigurationCurrent(null);
                $jsonResult["success"] = $configuration->setDefaultProvider($_POST["ident"]);
                if (!$jsonResult["success"]) {
                    $jsonResult["error"] = $configuration->getLastError();
                }
                break;
            case 'regions_update':
                $configuration = $this->getShippingConfigurationCurrent(null);
                $arCountryGroups = (array_key_exists("country_group", $_POST) ? $_POST["country_group"] : array());
                $arCountries = (array_key_exists("country", $_POST) ? $_POST["country"] : array());
                // Get available regions and countries
                $arRegions = $this->getRegionList($configuration);
                // Filter by selected countries/region (and their parents)
                $selectedLevel = null;
                $arParentIndices = array();
                foreach ($arRegions as $regionIndex => $regionDetails) {
                    // Cleanup parent list
                    array_splice($arParentIndices, $regionDetails["NS_LEVEL"]-1);
                    if (($selectedLevel !== null) && ($selectedLevel >= $regionDetails["NS_LEVEL"])) {
                        $selectedLevel = null;
                    }
                    // Check if used
                    $arRegions[$regionIndex]["USED"] = false;
                    if ($selectedLevel !== null) {
                        $arRegions[$regionIndex]["USED"] = true;
                    } else if ((array_key_exists("ID_COUNTRY", $regionDetails) && in_array($regionDetails["ID_COUNTRY"], $arCountries))
                                || (array_key_exists("ID_COUNTRY_GROUP", $regionDetails) && in_array($regionDetails["ID_COUNTRY_GROUP"], $arCountryGroups))) {
                        $arRegions[$regionIndex]["USED"] = true;
                        // Ensure parents are selected as well
                        foreach ($arParentIndices as $parentLevel => $parentIndex) {
                            $arRegions[$parentIndex]["USED"] = true;
                        }
                        // Select child nodes as well
                        $selectedLevel = $regionDetails["NS_LEVEL"];
                    }
                    // Update parent list
                    $arParentIndices[ $regionDetails["NS_LEVEL"]-1 ] = $regionIndex;
                }
                // Regenerate the list containing only the selected items
                $arCountryGroups = array();
                $arCountries = array();
                $arRegionsSelected = array();
                foreach ($arRegions as $regionIndex => $regionDetails) {
                    if ($regionDetails["USED"]) {
                        $arRegionsSelected[] = $regionDetails;
                        if (array_key_exists("ID_COUNTRY_GROUP", $regionDetails)) {
                            $arCountryGroups[] = $regionDetails["ID_COUNTRY_GROUP"];
                        }
                        if (array_key_exists("ID_COUNTRY", $regionDetails)) {
                            $arCountries[] = $regionDetails["ID_COUNTRY"];
                        }
                    }
                }
                $jsonResult["success"] = $configuration->setRegions($arCountryGroups, $arCountries);
                break;
            case 'prices_save':
                $configuration = $this->getShippingConfigurationCurrent(null);
                $arPrices = (array_key_exists("PRICES", $_POST) ? $_POST["PRICES"] : array());
                $arEnabled = (array_key_exists("ENABLED", $_POST) ? $_POST["ENABLED"] : array());
                $jsonResult["success"] = $configuration->setPrices($arPrices, $arEnabled);
                break;
            case 'config_save':
                $saveMode = $_POST["mode"];
                $configuration = $this->getShippingConfigurationCurrent(null);
                if (!$configuration->setName($_POST["name"])) {
                    break;
                }
                $arPrices = (array_key_exists("PRICES", $_POST) ? $_POST["PRICES"] : array());
                $arEnabled = (array_key_exists("ENABLED", $_POST) ? $_POST["ENABLED"] : array());
                if (!$configuration->setPrices($arPrices, $arEnabled)) {
                    break;
                }
                if (count($configuration->getProviders()) == 0) {
                    $jsonResult["error"] = Translation::readTranslation("plugin", "shipping.error.no.providers", null, array(), "Bitte wählen Sie mindestens einen Versandanbieter aus.");
                    break;
                }
                $arRegions = $configuration->getRegions();
                if (empty($arRegions) || (empty($arRegions["country"]) && empty($arRegions["country_group"]))) {
                    $jsonResult["error"] = Translation::readTranslation("plugin", "shipping.error.no.regions", null, array(), "Bitte wählen Sie mindestens ein Land aus.");
                    break;
                }
                if ($saveMode == "new") {
                    $configuration->createNewIdent();
                }
                if ($saveMode == "custom") {
                    // Save as custom configuration (just for this article)
                    $configuration->setCustom(true);
                    $_SESSION["pluginShipping_CustomGroup"] = $configuration;
                    $jsonResult["ident"] = "custom"; 
                    $jsonResult["success"] = true;
                } else {
                    // Save as default configuration
                    $configuration->setCustom(false);
                    $configuration->setNew(false);
                    $jsonResult["error"] = null; 
                    $jsonResult["ident"] = $configuration->getIdent(); 
                    $jsonResult["success"] = $this->saveShippingConfiguration($configuration, null, $jsonResult["error"]);
                }
                break;
            case 'config_delete':
                $jsonResult["error"] = null; 
                $jsonResult["success"] = $this->deleteShippingConfiguration($_POST["ident"], null, $jsonResult["error"]);
                break;
        }
        header("Content-Type: application/json");
        die(json_encode($jsonResult));
    }
    
    public function marketplaceAdCreateShipping(Api_Entities_EventParamContainer $params) {
        // Get parameters
        $tplShipping = $params->getParam("template");
        $arUsergroupOptions = $params->getParam("usergroupOptions");
        /** @var AdCreate $adCreate */
        $adCreate = $params->getParam("adCreate");
        // Initialize session
        $configSelected = $adCreate->getJsonAdData("SHIPPING_GROUP");
        #die(var_dump($configSelected));
        if (!is_array($configSelected) && !($configSelected instanceof ShippingGroup)) {
            $configSelected = $this->getUserShippingConfiguration();
        }
        $_SESSION["pluginShipping_ActiveGroup"] = null;
        $_SESSION["pluginShipping_CustomGroup"] = null;
        // Generate template
        $tplShippingPlugin = $this->utilGetTemplate("shipping.htm");
        $tplShippingPlugin->vars = $tplShipping->vars;
        $tplShippingPlugin->addvar("SCRIPT", $this->utilGetTemplate("script_edit.htm"));
        if (is_array($configSelected)) {
            $configSelected = ShippingGroup::fromAssoc($configSelected);
        }
        if ($configSelected instanceof ShippingGroup) {
            if ($configSelected->isCustom()) {
                $_SESSION["pluginShipping_CustomGroup"] = $configSelected;
                $tplShippingPlugin->addvar("customActive", 1);
            } else {
                $tplShippingPlugin->addvar("shippingActive", $configSelected->getIdent());
            }
        }
        $tplShippingPlugin->addvar("articleEdit", 1);
        $tplShippingPlugin->addvar("shipping_groups", $this->getUserShippingOptions());
        // Output result
        $params->setParam("result", $tplShippingPlugin->process());
    }    
    
    public function marketplaceAdCreateSubmitStep(Api_Entities_EventParamContainer $params) {
        /*
        $stepSubmitParams = new Api_Entities_EventParamContainer(array(
            "adCreate"          => $this,
            "step"              => $tplVarIdent,
            "stepOptions"       => $tplVarOptions,
            "dataInput"         => $arData,
            "dataOutput"        => $arDataNew
        ));
        */
        // Get parameters
        $step = $params->getParam("step");
        $stepOptions = $params->getParam("stepOptions");
        $dataInput = $params->getParam("dataInput");
        /** @var AdCreate $adCreate */
        $adCreate = $params->getParam("adCreate");
        // Disable shipping?        
        if (array_key_exists('VERKAUFSOPTIONEN', $dataInput) && (($dataInput['VERKAUFSOPTIONEN'] == 4) || ($dataInput['VERKAUFSOPTIONEN'] == 5))) {
            // No shipping!
            $dataInput["VERSANDOPTIONEN"] = 2;
            $params->setParamArrayValue("dataInput", "VERSANDOPTIONEN", 2);
        }
        if (($step == "ARTICLE_FIELDS") && array_key_exists("GROUPS", $stepOptions) && in_array("SHIPPING", $stepOptions["GROUPS"])) {
            $shippingConfig = null;
            if (!empty($dataInput["SHIPPING_GROUP"])) {
                if ($dataInput["SHIPPING_GROUP"] == "custom") {
                    $shippingConfig = $_SESSION["pluginShipping_CustomGroup"];
                } else {
                    $shippingConfig = $this->getShippingConfigurationCurrent($dataInput["SHIPPING_GROUP"]);
                }
            }
            if ($shippingConfig !== null) {
                $adCreate->setJsonAdData("SHIPPING_GROUP", $shippingConfig);
                if (!$shippingConfig instanceof ShippingGroup) {
                    $shippingConfig = $this->getShippingConfigurationCurrent($shippingConfig);
                }
                $params->setParamArrayValue("dataInput", "VERSANDKOSTEN", $shippingConfig->getPriceMin());
            } else if ($dataInput["VERSANDOPTIONEN"] != 3) {
                // Shipping disabled / on request
                $adCreate->setJsonAdData("SHIPPING_GROUP", null);
                $params->setParamArrayValue("dataInput", "VERSANDKOSTEN", 0);
            } else {
                $params->setParamArrayValue("errors", "SHIPPING_GROUP",
                  Translation::readTranslation("plugin", "shipping.error.no.shipping", null, array(), "Bitte wählen Sie eine Versandkonfiguration aus!")
                );
            }
        }
    }
    
    public function marketplaceAdShippingDisplay(Api_Entities_EventParamContainer $params) {
        /*
          $eventArticleShipping = new Api_Entities_EventParamContainer(array(
              "template"          => $this,
              "articleId"         => $articleId,
              "defaultVar"        => $defaultVar,
              "defaultValue"      => $defaultValue,
              "output"            => $this->tpl_topreis($defaultVar)
          ));
        */
        $template = $params->getParam("template");
        $articleId = $params->getParam("articleId");
        $variantId = $params->getParam("variantId");
        $countryId = $params->getParam("countryId");
        $providerIdent = $params->getParam("providerId");
        $defaultValue = $params->getParam("defaultValue");
        $defaultCurrency = $params->getParam("defaultCurrency");
        $value = $defaultValue;
        $valueIsExact = false;
        $viewType = $params->getParam("viewType");
        $shippingConfig = $this->getArticleShippingConfiguration($articleId);
        if ($shippingConfig !== null) {
            if (!empty($countryId) && !empty($providerIdent)) {
                $value = $this->getShippingProviderPrice($shippingConfig, $providerIdent, $countryId);
                $valueIsExact = true;
            }
            // Generate shipping cost output
            $tplOutput = $this->utilGetTemplate("view_shipping.".$viewType.".htm");
            $tplOutput->addvar("id", uniqid());
            $tplOutput->addvar("articleId", $articleId);
            $tplOutput->addvar("countryId", $countryId);
            $tplOutput->addvar("variantSet", ($variantId !== null ? true : false));
            $tplOutput->addvar("variantId", $variantId);
            $tplOutput->addvar("value", $value);
            $tplOutput->addvar("valueIsExact", $valueIsExact);
            $tplOutput->addvar("currency", $defaultCurrency);
            if ($viewType == "input") {
                // Add shipping provider options
                $arProviders = $this->getShippingProviderList($shippingConfig, $countryId);
                $tplOutput->addvar("provider_selected", $providerIdent);
                $tplOutput->addvar("providers", $this->utilGetTemplateList("view_shipping.".$viewType.".row.htm", $arProviders));
            } else if ($viewType == "table") {
                $tplOutput->addvar("prices", $this->getShippingConfigPriceTable($shippingConfig, "view_shipping.modal.prices"));
            }
            $params->setParam("output", $tplOutput->process());
        }
    }
    
    public function marketplaceCartArticleUpdate(Api_Entities_EventParamContainer $params) {
        /** @var ShoppingCartManagement $cart */
        $cart = $params->getParam('cart');
        $articleId = $params->getParam("articleId");
        $variantId = $params->getParam("variantId");
        $arItem = $params->getParam('item');
        $arDetails = $params->getParam('details');
        $shippingConfig = $this->getArticleShippingConfiguration($articleId);
        if (array_key_exists("SHIPPING_PROVIDER", $arDetails)) {
            // Set the selected shipping provider
            $cart->setOptionOfArticle($articleId, $variantId, "shippingProvider", $arDetails["SHIPPING_PROVIDER"]);
        } else if ($shippingConfig !== null) {
            // Set default shipping provider
            $arDefaultProvider = $shippingConfig->getProviderDefault();
            $cart->setOptionOfArticle($articleId, $variantId, "shippingProvider", ($arDefaultProvider !== null ? $arDefaultProvider["NAME"] : null));
        }
    }
    
    public function marketplaceCartArticleShipping(Api_Entities_EventParamContainer $params) {
        /** @var ShoppingCartManagement $cart */
        $cart = $params->getParam('cart');
        $arItem = $params->getParam('item');
        if ($arItem["VERSANDOPTIONEN"] == 3) {
            $shippingItem = $params->getParam('shippingItem');
            $shippingTotal = $params->getParam('shippingTotal');
            $shippingAddress = $params->getParam('shippingAddress'); 
            $shippingConfig = $this->getArticleShippingConfiguration($arItem["ID_AD_MASTER"]);
            if ($shippingConfig !== null) {
                $shippingProvider = "";
                if (array_key_exists("shippingProvider", $arItem["OPTIONS"])) {
                    $shippingProvider = $arItem["OPTIONS"]["shippingProvider"];
                } else {
                    $shippingProvider = $shippingConfig->getProviderDefault()["IDENT"];
                }
                $shippingItem = $this->getShippingProviderPrice($shippingConfig, $shippingProvider, $shippingAddress["FK_COUNTRY"]);
                if ($shippingItem === null) {
                    // Fallback!
                    $arShippingProviders = $this->getShippingProviderList($shippingConfig, $shippingAddress["FK_COUNTRY"]);
                    if (!empty($arShippingProviders)) {
                        $shippingItem = $arShippingProviders[0]["PRICE"];
                    }
                }
                $params->setParam('shippingItem', $shippingItem);
            }
        }
    }
    
    public function marketplaceCartArticlesGroup(Api_Entities_EventParamContainer $params) {
        require_once $GLOBALS['ab_path'].'sys/lib.user.php';
        require_once $GLOBALS['ab_path'].'sys/lib.ad_payment_adapter.php';
        $userManagement = UserManagement::getInstance($this->db);
        /*
        require_once $GLOBALS['ab_path'].'sys/lib.shopping.cart.php';
        require_once $GLOBALS['ab_path'].'sys/lib.ads.php';
        require_once $GLOBALS['ab_path'].'sys/lib.ad_constraint.php';
        require_once $GLOBALS['ab_path'].'sys/lib.ad_variants.php';
        require_once $GLOBALS['ab_path'].'sys/lib.ad_order.php';
        */
        /** @var ShoppingCartManagement $cart */
        $cart = $params->getParam('cart');
        $arArticles = $params->getParam("articles");
        $arResultRaw = array();
        $arResult = array();
        foreach ($arArticles as $key => $article) {
            list($articleId, $variantId) = explode(".", $key);
            // Payment Adapter
            $paymentAdapterId = $cart->getCartItemPaymentAdapter($article['ARTICLEDATA']['ID_AD'], $article['ARTICLEDATA']['ID_AD_VARIANT']);
            // Versandkosten
            $shippingGroupIndex = $article['USERDATA']['ID_USER'].".".$paymentAdapterId.".".$article['OPTIONS']['shippingProvider'];
            if (!array_key_exists($shippingGroupIndex, $arResultRaw)) {
                $arResultRaw[$shippingGroupIndex] = array();
            }
            $arResultRaw[$shippingGroupIndex][] = array_merge($article['ARTICLEDATA'], array(
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
        foreach ($arResultRaw as $shippingGroupIndex => $articleList) {
            if (empty($articleList)) {
                continue;
            }
            // Add article group
            $arResult[] = array(
                'IDENT'     => $shippingGroupIndex,
                'ARTICLES'  => $articleList
            );
        }
        $params->setParam("result", $arResult);
    }
    
    public function marketplaceCartView(Api_Entities_EventParamContainer $params) {
        /** @var ShoppingCartManagement $cart */
        $cart = $params->getParam('cart');
        $htmlOutput = $params->getParam('pluginHtml');
        $tplCartScript = $this->utilGetTemplate("cart.script.htm");
        $params->setParam('pluginHtml', $htmlOutput."\n".$tplCartScript->process());
    }
    
    public function marketplaceCartCheckout(Api_Entities_EventParamContainer $params) {
        /** @var ShoppingCartManagement $cart */
        $cart = $params->getParam('cart');
        $arArticleGroups = $params->getParam("articleGroups");
        foreach ($arArticleGroups as $groupIndex => $groupData) {
            list($articleId, $variantId, $providerIdent) = explode(".", $groupData["IDENT"]);
            if (!empty($providerIdent)) {
                $arShippingProvider = Api_LookupManagement::getInstance($this->db)->readByValue("VERSAND_ANBIETER", $providerIdent);
                // Generate group title
                $tplTitle = $this->utilGetTemplate("cart.group.htm");
                $tplTitle->addvars($groupData);
                $tplTitle->addvar("SHIPPING_PROVIDER", $arShippingProvider["V1"]);
                $arArticleGroups[$groupIndex]["TITLE_HTML"] = $tplTitle->process();
            }
        }
        $params->setParam("articleGroups", $arArticleGroups);
    }
    
    public function marketplaceOrderGroup(Api_Entities_EventParamContainer $params) {
        /** @var AdOrderManagement $orderManagement */
        $orderManagement = $params->getParam('orderManagement');
        $arOrders = $params->getParam("orders");
        $arResult = array();
				foreach($arOrders as $key => $order) {
          $shippingGroupIndex = $order['article']['ARTICLEDATA']['FK_USER'].".".(int)$order['paymentAdapter'].".".$order['article']['OPTIONS']['shippingProvider'];
					$arResult[$shippingGroupIndex][] = $order;
				}
        $params->setParam("result", $arResult);   
    }
    
    public function marketplaceOrderSalesSearchForm(Api_Entities_EventParamContainer $params) {
        $arFields = $params->getParam("fields");
        $arParams = $params->getParam("params");
        $arProviders = $this->getProviderList($arParams["SHIPPING_PROVIDER"]);
        $tplSearchProvider = $this->utilGetTemplate("sales_form.shipping_provider.htm");
        $tplSearchProvider->addvar("liste", $this->utilGetTemplateList("sales_form.shipping_provider.row.htm", $arProviders));
        $arFields[] = $tplSearchProvider->process();
        $params->setParam("fields", $arFields);
    }
    
    public function userSettings(Api_Entities_EventParamContainer $params) {
        $_SESSION["pluginShipping_ActiveGroup"] = null;
        $_SESSION["pluginShipping_CustomGroup"] = null;
        $pluginHtml = $params->getParam("pluginHtml");
        $configSelected = $this->getUserShippingConfiguration();
        $tplSettings = $this->utilGetTemplate("settings.htm");
        $tplSettings->addvar("SCRIPT", $this->utilGetTemplate("script_edit.htm"));
        $tplSettings->addvar("shipping_groups", $this->getUserShippingOptions());
        if ($configSelected instanceof ShippingGroup) {
            if ($configSelected->isCustom()) {
                $tplSettings->addvar("customActive", 1);
            } else {
                $tplSettings->addvar("shippingActive", $configSelected->getIdent());
            }
        }
        $pluginHtml .= $tplSettings->process();
        $params->setParam("pluginHtml", $pluginHtml);
    }
    
    public function userSettingsSubmit(Api_Entities_EventParamContainer $params) {
        $result = $params->getParam("result");
        $userId = $params->getParam("userId");
        $userData = $params->getParam("userData");
        if (array_key_exists("SHIPPING_GROUP", $_POST) && !empty($_POST["SHIPPING_GROUP"])) {
            $userData["JSON_ADDITIONAL"]["SHIPPING_GROUP"] = $_POST["SHIPPING_GROUP"];
            $result = $this->db->querynow("
              UPDATE `user` 
              SET JSON_ADDITIONAL='".mysql_real_escape_string(json_encode($userData["JSON_ADDITIONAL"]))."'
              WHERE ID_USER=".(int)$userId);
            if ($result["rsrc"]) {
                $GLOBALS["user"]["JSON_ADDITIONAL"]["SHIPPING_GROUP"] = $_POST["SHIPPING_GROUP"];
            } else {
                $result = false;
            }
        }
        $params->setParam("result", $result);
    }
}