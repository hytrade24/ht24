<?php

class Api_Plugins_GeoRegion_Plugin extends Api_TraderApiPlugin {

    static $cacheLifetime = 30;  // Minimum lifetime of cache files in minutes
    
    private $marketplaceViewList = false;
    
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
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_ENABLE, "marketplaceAdEnable");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_DISABLE, "marketplaceAdDisable");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_LOCATION_UPDATE, "marketplaceAdLocationUpdate" );
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_GET_DATATABLE, "marketplaceAdGetDatatable");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_SEARCH_FORM, "marketplaceAdSearchForm");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_SEARCH_QUERY, "marketplaceAdSearchQuery");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_LIST_QUERY, "marketplaceListQuery");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_VIEW, "marketplaceView");
        $this->registerEvent(Api_TraderApiEvents::URL_GENERATE, "urlGenerate");
        $this->registerEvent(Api_TraderApiEvents::SYSTEM_CACHE_ALL, "systemCacheAll");
        $this->registerEvent(Api_TraderApiEvents::TEMPLATE_PLUGIN_FUNCTION, "templatePluginFunction");
        $this->registerEvent(Api_TraderApiEvents::TEMPLATE_SETUP_CONTENT, "templateSetupContent");
        return true; 
    }
    
    private function getRegionDefaultOptions()
    {
        return array("VIEW" => "default");
    }
    
    /**
     * Returns some specific information about the plugins configuration
     * @param $arParams (optional) Array with parameters for this request.
     * @return mixed
     */
    public function getConfigurationAjax($arParams = array()) {
        $action = (array_key_exists("action", $arParams) ? $arParams["action"] : "default");
        switch ($action) {
            case "listAdminRegions":
                $regionParent = (array_key_exists("region", $arParams) && ($arParams["region"] > 0) ? (int)$arParams["region"] : null);
                $regionInfoParent = array();
                $arTemplateList = array();
                $arRegions = $this->getChildRegionsRaw($regionParent);
                if ($regionParent !== null) {
                    $regionInfoParent = $this->getRegionRaw($regionParent);
                    $tplRegionBack = $this->utilGetTemplate("config.regionRowBack.htm");
                    $tplRegionBack->addvars($regionInfoParent);
                    $tplRegionBack->addvars(array_flatten($regionInfoParent["OPTIONS"], false, "_", "OPTIONS_"));
                    $arTemplateList[] = $tplRegionBack->process();
                }
                if (!empty($arRegions)) {
                    foreach ($arRegions as $regionIndex => $regionInfo) {
                        $tplRegion = $this->utilGetTemplate("config.regionRow.htm");
                        $tplRegion->addvars($regionInfo);
                        $tplRegion->addvars(array_flatten($regionInfo["OPTIONS"], false, "_", "OPTIONS_"));
                        $tplRegion->addvar("CHILD_COUNT", $this->getChildRegionsCount($regionInfo["ID_GEO_REGION"]));
                        $arTemplateList[] = $tplRegion->process();
                    }
                } else {
                    $tplRegion = $this->utilGetTemplate("config.regionRowEmpty.htm");
                    $tplRegion->addvars($regionInfoParent, "PARENT_");
                    $arTemplateList[] = $tplRegion->process();
                }
                die(implode("\n", $arTemplateList));
        }
        return null;
    }
    
    public function setConfiguration($arConfig) {
        // Update region configurations
        if (array_key_exists("REGIONS", $arConfig)) {
            foreach ($arConfig["REGIONS"] as $regionId => $regionConfig) {
                $this->db->querynow("UPDATE `geo_region` SET SER_OPTIONS='".mysql_real_escape_string(serialize($regionConfig))."' WHERE ID_GEO_REGION=".(int)$regionId);
            }
        }
        unset($arConfig["REGIONS"]);
        // Save base configuration
        return parent::setConfiguration($arConfig);
    }
    
    public function ajaxPlugin(Api_Entities_EventParamContainer $params) {
        $jsonResult = array("success" => false);
        switch ($params->getParam("action")) {
            case 'getMarkers':
                list($mapType, $mapCategory, $mapRegion, $mapSearchHash) = explode(",", $_POST["mapIdent"]);
                if (array_key_exists("latMin", $_POST) && array_key_exists("latMax", $_POST) && array_key_exists("lngMin", $_POST) && array_key_exists("lngMax", $_POST)) {
                    $latMin = min($_POST["latMin"], $_POST["latMax"]);
                    $latMax = max($_POST["latMin"], $_POST["latMax"]);
                    $lngMin = min($_POST["lngMin"], $_POST["lngMax"]);
                    $lngMax = max($_POST["lngMin"], $_POST["lngMax"]);
                    $jsonResult = $this->getMapMarkers($mapType, $mapCategory, $mapRegion, $mapSearchHash, (int)$_POST["limit"], (int)$_POST["offset"], (int)$_POST["resultCount"], $latMin, $latMax, $lngMin, $lngMax);
                } else {
                    $jsonResult = $this->getMapMarkers($mapType, $mapCategory, $mapRegion, $mapSearchHash, (int)$_POST["limit"], (int)$_POST["offset"], (int)$_POST["resultCount"]);
                }
                break;
            case 'getRegionsSearch':
                $idCategory = ($_POST["category"] > 0 ? (int)$_POST["category"] : null);
                $idGeoRegion = ($_POST["region"] > 0 ? (int)$_POST["region"] : null);
                $searchHash = $_POST["hash"];
                if (!array_key_exists("GeoRegionsSearchHash", $_SESSION) || ($_SESSION["GeoRegionsSearchHash"] != $searchHash)) {
                    $_SESSION["GeoRegionsSearchCache"] = $this->pluginFunction_regions_render($idCategory, $idGeoRegion, $searchHash);
                }
                die($_SESSION["GeoRegionsSearchCache"]);
            case 'typeaheadRegionName':
                session_write_close();
                ignore_user_abort(false);
                $arRegions = $this->db->fetch_table("SELECT ID_GEO_REGION, NAME FROM `geo_region` WHERE NAME LIKE '".$_POST["query"]."%' GROUP BY NAME");
                die(json_encode($arRegions));
        }
        header("Content-Type: application/json");
        die(json_encode($jsonResult));
    }
    
    public function marketplaceAdEnable(Api_Entities_EventParamContainer $params) {
        $adData = $params->getParam("data");
        $this->setDirtyRegion("all", ($adData["FK_GEO_REGION"] > 0 ? (int)$adData["FK_GEO_REGION"] : null), (int)$adData["FK_KAT"]);
    }
    
    public function marketplaceAdDisable(Api_Entities_EventParamContainer $params) {
        $adData = $params->getParam("data");
        $this->setDirtyRegion("all", ($adData["FK_GEO_REGION"] > 0 ? (int)$adData["FK_GEO_REGION"] : null), (int)$adData["FK_KAT"]);
    }
    
    public function marketplaceAdLocationUpdate(Api_Entities_EventParamContainer $params) {
        $idGeoRegion = $params->getParam("idGeoRegion");
        $idCategory = $params->getParam("idCategory");
        $this->setDirtyRegion("all", $idGeoRegion, $idCategory);
    }
    
    public function marketplaceAdGetDatatable(Api_Entities_EventParamContainer $params) {
        $dbMasterPrefix = $params->getParam("articleMasterShortcut");
        /**
         * @var Api_DataTable $dataTable
         */
        $dataTable = $params->getParam("dataTable");
        $dataTable->addWhereCondition("FK_GEO_REGION", $dbMasterPrefix.".FK_GEO_REGION IN $1$");
    }
    
    public function marketplaceAdSearchQuery(Api_Entities_EventParamContainer $params) {
        $idGeoRegion = $params->getParamArrayValue("searchData", "FK_GEO_REGION");
        if ($idGeoRegion > 0) {
            /**
             * @var Api_DataTableQuery $searchQuery
             */
            $searchQuery = $params->getParam("query");
            $dbMasterPrefix = $params->getParam("queryMasterPrefix");
            // Get all child regions
            $idGeoRegions = $this->getChildRegions($idGeoRegion);
            $idGeoRegions[] = $idGeoRegion;
            $ids_regions = "(" . implode(",", $idGeoRegions) . ")";
            // Add where clause
            $params->setParamArrayValue("searchData", "FK_GEO_REGION", $ids_regions);
            //$searchQuery->addWhereCondition("FK_GEO_REGION", $ids_regions);
        }
    }
    
    public function marketplaceAdSearchForm(Api_Entities_EventParamContainer $params) {
        if (array_key_exists("ID_GEO_REGION", $params->getParam("templateContent")->vars) && ($params->getParam("templateContent")->vars["ID_GEO_REGION"] > 0)) {
            $idGeoRegion = (int)$params->getParam("templateContent")->vars["ID_GEO_REGION"];
            $tplRegion = $this->utilGetTemplate("ad_search.hidden_region.htm");
            $tplRegion->addvar("FK_GEO_REGION", $idGeoRegion);
            $params->setParamArrayAppend("customFieldsHidden", $tplRegion);
        }
    }
    
    public function marketplaceListQuery(Api_Entities_EventParamContainer $params) {
        if (!$params->getParam("searchActive") && array_key_exists("ID_GEO_REGION", $params->getParam("template")->vars)) {
            $idGeoRegion = (int)$params->getParam("template")->vars["ID_GEO_REGION"];
            if ($idGeoRegion > 0) {
                $idGeoRegions = $this->getChildRegions($idGeoRegion);
                $idGeoRegions[] = $idGeoRegion;
                $ids_regions = "(" . implode(",", $idGeoRegions) . ")";
                /**
                 * @var Api_DataTableQuery $searchQuery
                 */
                $searchQuery = $params->getParam("query");
                $dbMasterPrefix = $params->getParam("queryMasterPrefix");
                $searchQuery->addWhereCondition("FK_GEO_REGION", $ids_regions);
            }
        }
    }
    
    public function marketplaceView(Api_Entities_EventParamContainer $params) {
        if ($this->marketplaceViewList && ($params->getParam("viewType") != "STANDARD")) {
            $params->setParam("viewType", "STANDARD");
            $params->setParamArrayValue("categoryRow", "FK_INFOSEITE", null);
        }
    }
    
    public function urlGenerate(Api_Entities_EventParamContainer $params) {
        /** @var Api_Entities_URL $url */
        $url = $params->getParam("url");
        /** @var Template $tpl */
        $tpl = $params->getParam("template");
        if ($url->getPageIdent() == "marktplatz") {
            if (preg_match("/^([0-9]+)\-([0-9]+)$/", $url->getPageParameter(0), $arMatchCategory)) {
                if ($arMatchCategory[2] == 0) {
                    $url->setPageParameter(0, $arMatchCategory[1]);
                    $url->setPageParameterOptional("REGION_NAME", null);
                }
            } else if (array_key_exists("ID_GEO_REGION", $tpl->vars)) {
                $url->setPageParameter(0, $url->getPageParameter(0)."-".(int)$tpl->vars["ID_GEO_REGION"]);
            }
        }
    }
    
    public function systemCacheAll(Api_Entities_EventParamContainer $params) {
        $cachePath = $this->utilGetCachePathAbsolute();
        system('rm -R ' . $cachePath);
    }
    
    public function templatePluginFunction(Api_Entities_EventParamContainer $params) {
        $arActionParams = $params->getParam("params");
        switch ($params->getParam("action")) {
            case "map":
                $mapType = "marktplatz";
                $idCategory = null;
                $idGeoRegion = null;
                $searchHash = null;
                $searchFormSelector = null;
                $mapHeight = 400;
                if ((count($arActionParams) >= 1) && !empty($arActionParams[0])) {
                    $mapType = $arActionParams[0];
                }
                if ((count($arActionParams) >= 2) && ($arActionParams[1] > 0)) {
                    $idCategory = (int)$arActionParams[1];
                }
                if ((count($arActionParams) >= 3) && ($arActionParams[2] > 0)) {
                    $idGeoRegion = (int)$arActionParams[2];
                }
                if ((count($arActionParams) >= 4) && !empty($arActionParams[3])) {
                    $searchHash = $arActionParams[3];
                }
                if ((count($arActionParams) >= 5) && !empty($arActionParams[4])) {
                    $searchFormSelector = $arActionParams[4];
                }
                if ((count($arActionParams) >= 6) && ($arActionParams[5] > 0)) {
                    $mapHeight = (int)$arActionParams[5];
                }
                $params->setParam("result", $this->pluginFunction_map($mapType, $idCategory, $idGeoRegion, $searchHash, $searchFormSelector, $mapHeight));
                break;
            case "countries":
                $idCategory = null;
                $searchHash = null;
                if ((count($arActionParams) >= 1) && ($arActionParams[0] > 0)) {
                    $idCategory = (int)$arActionParams[0];
                }
                if ((count($arActionParams) >= 2) && !empty($arActionParams[1])) {
                    $searchHash = $arActionParams[1];
                }
                $params->setParam("result", $this->pluginFunction_countries($idCategory, $searchHash));
                break;
            case "regions":
                $idCategory = null;
                $idGeoRegion = null;
                $searchHash = null;
                if ((count($arActionParams) >= 1) && ($arActionParams[0] > 0)) {
                    $idCategory = (int)$arActionParams[0];
                }
                if ((count($arActionParams) >= 2) && ($arActionParams[1] > 0)) {
                    $idGeoRegion = (int)$arActionParams[1];
                }
                if ((count($arActionParams) >= 3) && !empty($arActionParams[2])) {
                    $searchHash = $arActionParams[2];
                }
                $params->setParam("result", $this->pluginFunction_regions($idCategory, $idGeoRegion, $searchHash));
                break;
        }
    }
    
    public function templateSetupContent(Api_Entities_EventParamContainer $params) {
        if ($params->getParam("name") == "marktplatz") {
            $ar_params = $params->getParam("params");
            if ((count($ar_params) >= 2) && preg_match("/^([0-9]+)\-([0-9]+)$/", $ar_params[1], $ar_kat_geo)) {
                $params->setParamArrayValue("params", 1, $ar_kat_geo[1]);
                $params->setParamArrayValue("variables", "ID_GEO_REGION", $ar_kat_geo[2]);
                $this->marketplaceViewList = true;
            }
        }
    }
    
    protected function pluginFunction_map($mapType, $idCategory = null, $idGeoRegion = null, $searchHash = null, $searchFormSelector = null, $mapHeight = null) {
        $tplMap = $this->utilGetTemplate("googlemaps.htm");
        $tplMap->addvar("type", $mapType);
        if ($searchFormSelector !== null) {
            $tplMap->addvar("search_form", $searchFormSelector);
        }
        if ($idCategory !== null) {
            $tplMap->addvar("category", $idCategory);
        }
        if ($idGeoRegion !== null) {
            $tplMap->addvar("region", $idGeoRegion);
        }
        if ($searchHash !== null) {
            $tplMap->addvar("search", $searchHash);
        }
        if ($mapHeight !== null) {
            $tplMap->addvar("height", $mapHeight);
        }
        $tplMap->addvar("ZOOM_MAX", $GLOBALS["nar_systemsettings"]["SYS"]["MAP_ZOOM_MAX"]);
        return $tplMap->process(true);
    }
    
    protected function pluginFunction_countries($idCategory = null, $searchHash = null) {
        if ($idCategory === null) {
            // Get default category
            include_once "sys/lib.shop_kategorien.php";
            $kat = new TreeCategories("kat", 1);
            $idCategory = $kat->tree_get_parent();
        }
        if ($searchHash === null) {
            // Regular
            $cacheHash = md5("html_".$idCategory);
            $cacheFile = "countries/html/".$cacheHash.".htm";
            $cacheFileAbs = $this->utilGetCacheFileAbsolute($cacheFile);
            $cacheRewrite = !file_exists($cacheFileAbs) || true;
            if (!$cacheRewrite) {
                // Check if dirty and beyond lifetime
                $renewTime = time() - (self::$cacheLifetime * 60);
                if ($this->getDirtyCountry("html", $idCategory) && (filemtime($cacheFileAbs) < $renewTime)) {
                    $cacheRewrite = true;
                }
            }
            if ($cacheRewrite) {
                // Update cache
                $result = $this->pluginFunction_countries_render($idCategory);
                $this->utilWriteCacheFile($cacheFile, $result);
                $this->setDirtyCountry("html", $idCategory, false);
                return $result;
            } else {
                return $this->utilReadCacheFile($cacheFile);
            }
        } else {
            // Search
            $tplRegion = $this->utilGetTemplate("countries.ajax.htm");
            $tplRegion->addvar("ID_KAT", $idCategory);
            $tplRegion->addvar("SEARCH_HASH", $searchHash);
            return $tplRegion->process();
        }
    }
    
    protected function pluginFunction_countries_render($idCategory = null, $searchHash = null) {
        global $db;
        // Get category details
        $arKat = array();
        if ($idCategory > 0) {
            include_once "sys/lib.shop_kategorien.php";
            $kat = new TreeCategories("kat", 1);
            $arKat = $kat->element_read($idCategory);
        }
        $regionCount = 0;
        $regionIdLast = false;
        // Prepare marker list for map
        $arMarkerList = array(); 
        // Get child regions
        $arRegions = $db->fetch_table("SELECT * FROM `geo_region` WHERE FK_PARENT IS NULL");
        $arRegionsTpl = array();
        foreach ($arRegions as $regionIndex => $arRegion) {
            // Add articles from child regions
            $adCount = $this->getArticleCount($idCategory, $arRegion["ID_GEO_REGION"], $searchHash);
            if ($adCount > 0) {
                $arGeoLocation = Geolocation_Generic::getGeolocationCached(null, null, null, $arRegion["NAME"]);
                if (is_array($arGeoLocation)) {
                    $regionCount++;
                    $regionIdLast = $arRegion["ID_GEO_REGION"];
                    $regionUrl = "";
                    $tplRegion = $this->utilGetTemplate("countries.row.htm");
                    $tplRegion->inheritParentVariables = true;
                    $tplRegion->addvars($arRegion);
                    $tplRegion->addvar("AD_COUNT", $adCount);
                    if ($searchHash !== null) {
                        $regionUrl = $tplRegion->tpl_uri_action("marktplatz,".$idCategory."-".$arRegion["ID_GEO_REGION"].",Suchergebniss,".$searchHash."|REGION_NAME={urllabel(NAME)}");
                        $tplRegion->addvar("SEARCH_HASH", $searchHash);
                    } else {
                        $regionUrl = $tplRegion->tpl_uri_action("marktplatz,".$idCategory."-".$arRegion["ID_GEO_REGION"].",".addnoparse(chtrans($arKat["V1"]))."|REGION_NAME={urllabel(NAME)}");
                    }
                    $tplRegion->addvar("URL", $regionUrl);
                    $arRegionsTpl[] = $tplRegion;
                    $arMarker = array(
                        "ID_CATEGORY" => $idCategory, "ID_GEO_REGION" => $arRegion["ID_GEO_REGION"], "SEARCH_HASH" => $searchHash,
                        "TITLE" => $arRegion["NAME"], "URL" => $regionUrl, "AD_COUNT" => $adCount,
                        "LATITUDE" => $arGeoLocation["LATITUDE"], "LONGITUDE" => $arGeoLocation["LONGITUDE"]
                    );
                    $regionIdent = str_replace(array("Ä", "ä", "Ö", "ö", "Ü", "ü", "ß"), array("ae", "ae", "oe", "oe", "ue", "ue", "ss"), strtolower($arRegion["NAME"]));
                    $arLanguage = $db->fetch1("
                      SELECT c.* FROM `country` c JOIN `string` s ON s.S_TABLE='country' AND c.ID_COUNTRY=s.FK 
                      WHERE s.V1 LIKE '".mysql_real_escape_string($arRegion["NAME"])."'");
                    if (is_array($arLanguage)) {
                        $regionIdent = $arLanguage["CODE"];
                    }
                    if ($this->utilGetTemplateExists("mapIcons/".$regionIdent.".png")) {
                        $iconPath = str_replace($GLOBALS["ab_path"], "/", $this->utilGetTemplatePath("mapIcons/".$regionIdent.".png"));
                        $arMarker["ICON"] = $tplRegion->tpl_uri_baseurl_full($iconPath);
                    }
                    $arMarkerList[] = $arMarker;
                }
            }
        }
        if ($regionCount == 1) {
            return $this->pluginFunction_regions_render($idCategory, $regionIdLast, $searchHash);
        }
        if (empty($arRegionsTpl)) {
            // Do not display region block when there are no known regions.
            return "";
        }
        $tplRegions = $this->utilGetTemplate("countries.htm");
        $tplRegions->addvar("ID_KAT", $idCategory);
        $tplRegions->addvar("liste", $arRegionsTpl);
        $tplRegions->addvar("markers", json_encode($arMarkerList));
        $tplRegions->addvars($arKat, "KAT_");
        return $tplRegions->process(false);
    }
    
    protected function pluginFunction_regions($idCategory = null, $idGeoRegion = null, $searchHash = null) {
        if (!$GLOBALS['nar_systemsettings']['SYS']['MAP_REGIONS']) {
            // Don't show regions if not enabled in settings
            return "";
        }
        if ($idCategory === null) {
            // Get default category
            include_once "sys/lib.shop_kategorien.php";
            $kat = new TreeCategories("kat", 1);
            $idCategory = $kat->tree_get_parent();
        }
        if ($searchHash === null) {
            // Regular
            $cacheHash = md5("html_".$idCategory."_".$idGeoRegion);
            $cacheFile = "regions/html/".$cacheHash.".htm";
            $cacheFileAbs = $this->utilGetCacheFileAbsolute($cacheFile);
            $cacheRewrite = !file_exists($cacheFileAbs);
            if (!$cacheRewrite) {
                // Check if dirty and beyond lifetime
                $renewTime = time() - (self::$cacheLifetime * 60);
                if ($this->getDirtyRegion("html", $idGeoRegion, $idCategory) && (filemtime($cacheFileAbs) < $renewTime)) {
                    $cacheRewrite = true;
                }
            }
            if ($cacheRewrite) {
                // Update cache
                $result = $this->pluginFunction_regions_render($idCategory, $idGeoRegion);
                $this->utilWriteCacheFile($cacheFile, $result);
                $this->setDirtyRegion("html", $idGeoRegion, $idCategory, false);
                return $result;
            } else {
                return $this->utilReadCacheFile($cacheFile);
            }
        } else {
            // Search
            $tplRegion = $this->utilGetTemplate("regions.ajax.htm");
            $tplRegion->addvar("ID_KAT", $idCategory);
            $tplRegion->addvar("ID_GEO_REGION", $idGeoRegion);
            $tplRegion->addvar("SEARCH_HASH", $searchHash);
            return $tplRegion->process();
        }
    }
    
    protected function pluginFunction_regions_render($idCategory = null, $idGeoRegion = null, $searchHash = null) {
        // Get parent region
        $arRegionCurrent = array();
        $arRegionParent = array();
        if ($idGeoRegion !== NULL) {
            $arRegionCurrent = $this->db->fetch1("SELECT * FROM `geo_region` WHERE ID_GEO_REGION=".(int)$idGeoRegion);
            if (is_array($arRegionCurrent)) {
                $idRegionParent = (int)$arRegionCurrent["FK_PARENT"];
                if ($idRegionParent > 0) {
                    $arRegionParent = $this->db->fetch1("SELECT * FROM `geo_region` WHERE ID_GEO_REGION=".(int)$idRegionParent." ORDER BY NAME ASC");
                }
            } else {
                $arRegionCurrent = array();
            }
        }
        // Get category details
        $arKat = array();
        if ($idCategory > 0) {
            include_once "sys/lib.shop_kategorien.php";
            $kat = new TreeCategories("kat", 1);
            $arKat = $kat->element_read($idCategory);
        }
        $regionCount = 0;
        $regionIdLast = false;
        // Get child regions
        $arRegions = $this->getChildRegionsRaw($idGeoRegion);
        $arRegionsTpl = array();
        foreach ($arRegions as $regionIndex => $arRegion) {
            // Add articles from child regions
            $adCount = $this->getArticleCount($idCategory, $arRegion["ID_GEO_REGION"], $searchHash);
            if ($adCount > 0) {
                $regionCount++;
                $regionIdLast = $arRegion["ID_GEO_REGION"];
                $tplRegion = $this->utilGetTemplate("regions.row.htm");
                $tplRegion->inheritParentVariables = true;
                $tplRegion->addvars($arRegion);
                $tplRegion->addvar("AD_COUNT", $adCount);
                if ($searchHash !== null) {
                    $tplRegion->addvar("SEARCH_HASH", $searchHash);
                    $tplRegion->addvar("URL", $tplRegion->tpl_uri_action("marktplatz,".$idCategory."-".$arRegion["ID_GEO_REGION"].",Suchergebniss,".$searchHash."|REGION_NAME={urllabel(NAME)}"));
                } else {
                    $tplRegion->addvar("URL", $tplRegion->tpl_uri_action("marktplatz,".$idCategory."-".$arRegion["ID_GEO_REGION"].",".addnoparse(chtrans($arKat["V1"]))."|REGION_NAME={urllabel(NAME)}"));
                }
                $arRegionsTpl[] = $tplRegion;
            }
        }
        /*
        if ($regionCount == 1) {
            return $this->pluginFunction_regions_render($idCategory, $regionIdLast, $searchHash);
        }
        */
        if (empty($arRegionsTpl) && ($idGeoRegion === NULL)) {
            // Do not display region block when there are no known regions.
            return "";
        }
        $tplRegions = $this->utilGetTemplate("regions.htm");
        $tplRegions->addvar("ID_KAT", $idCategory);
        $tplRegions->addvar("ID_GEO_REGION", $idGeoRegion);
        $tplRegions->addvar("liste", $arRegionsTpl);
        $tplRegions->addvars($arKat, "KAT_");
        // Current region
        $tplRegions->addvars($arRegionCurrent, "CURRENT_");
        // Parent region
        $tplRegions->addvars($arRegionParent, "PARENT_");
        if ($idGeoRegion !== null) {
            $parentCategoryIdent = $idCategory.($arRegionParent["ID_GEO_REGION"] !== null ? "-".$arRegionParent["ID_GEO_REGION"] : "-0");
            if ($searchHash !== null) {
                $tplRegions->addvar("SEARCH_HASH", $searchHash);
                $tplRegions->addvar("URL_PARENT", $tplRegions->tpl_uri_action("marktplatz,".$parentCategoryIdent.",Suchergebniss,".$searchHash."|REGION_NAME={urllabel(PARENT_NAME)}"));
            } else {
                $tplRegions->addvar("URL_PARENT", $tplRegions->tpl_uri_action("marktplatz,".$parentCategoryIdent.",".addnoparse(chtrans($arKat["V1"]))."|REGION_NAME={urllabel(PARENT_NAME)}"));
            }
        }
        return $tplRegions->process(false);
    }
    
    public function getArticleCount($idCategory = null, $idGeoRegion = null, $searchHash = null) {
        if ($searchHash !== null) {
            // Getting article count for search result, do not cache this!
            $searchQuery = Rest_MarketplaceAds::getQueryByHash($searchHash);
            $dbMasterPrefix = $searchQuery->getDataTable()->getTableIdent();
            // Get all child regions
            $idGeoRegions = $this->getChildRegions($idGeoRegion);
            $idGeoRegions[] = $idGeoRegion;
            $ids_regions = "(" . implode(",", $idGeoRegions) . ")";
            // Filter result by region
            $searchQuery->addWhereCondition("FK_GEO_REGION", $ids_regions);
            return $searchQuery->fetchCount();
        }
        $cacheHash = md5("ad_count_".$idCategory."_".$idGeoRegion);
        $cacheFile = "regions/ad_count/".$cacheHash.".txt";
        $cacheFileAbs = $this->utilGetCacheFileAbsolute($cacheFile);
        $cacheRewrite = !file_exists($cacheFileAbs);
        if (!$cacheRewrite) {
            // Check if dirty and beyond lifetime
            $renewTime = time() - (self::$cacheLifetime * 60);
            if ($this->getDirtyRegion("ad_count", $idGeoRegion, $idCategory) && (filemtime($cacheFileAbs) < $renewTime)) {
                $cacheRewrite = true;
            }
        }
        if ($cacheRewrite) {
            // Update cache
            global $db;
            $idCategoryChildren = array($idCategory);
            if (!is_array($idCategory)) {
                // Get ids of subcategorys
                $row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=".$idCategory);
                $idCategoryChildren = array_keys($db->fetch_nar("
                  SELECT ID_KAT
                    FROM `kat`
                  WHERE
                    (LFT >= " . $row_kat["LFT"] . ") AND
                    (RGT <= " . $row_kat["RGT"] . ") AND
                    (ROOT = " . $row_kat["ROOT"] . ")"));
            }
            // Implode list of categorys
            $ids_kats = "(" . implode(",", $idCategoryChildren) . ")";
            // Get all child regions
            $idGeoRegions = $this->getChildRegions($idGeoRegion);
            $idGeoRegions[] = $idGeoRegion;
            $ids_regions = "(" . implode(",", $idGeoRegions) . ")";
            // Count articles
            $adCount = $db->fetch_atom($q="
                SELECT COUNT(*) FROM `ad_master`
                WHERE FK_KAT IN ".$ids_kats." AND FK_GEO_REGION IN ".$ids_regions."
                    AND STATUS IN (1,5,9,13) AND DELETED=0");
            // Write article count to cache
            $this->utilWriteCacheFile($cacheFile, $adCount);
            $this->setDirtyRegion("ad_count", $idGeoRegion, $idCategory, false);
            // Return result
            return $adCount;
        } else {
            // Read from cache
            return (int)$this->utilReadCacheFile($cacheFile);
        }
    }

    public function getRegionRaw($idGeoRegion) {
        $arRegion = $this->db->fetch1("SELECT * FROM `geo_region` WHERE ID_GEO_REGION=".(int)$idGeoRegion);
        $arRegion["OPTIONS"] = $this->getRegionDefaultOptions();
        if ($arRegion["SER_OPTIONS"] !== null) {
            $arRegionOptions = @unserialize($arRegion["SER_OPTIONS"]);
            if (is_array($arRegionOptions)) {
                $arRegion["OPTIONS"] = array_merge($arRegion["OPTIONS"], $arRegionOptions);
            }
        }
        return $arRegion;
    }

    public function getChildRegions($idGeoRegion = null) {
        $cacheFile = "regions/childs/".($idGeoRegion === null ? "all" : $idGeoRegion).".txt";
        $cacheFileAbs = $this->utilGetCacheFileAbsolute($cacheFile);
        $cacheRewrite = !file_exists($cacheFileAbs);
        if (!$cacheRewrite) {
            // Check if dirty and beyond lifetime
            $renewTime = time() - (self::$cacheLifetime * 60);
            if ($this->getDirtyRegion("childs", $idGeoRegion) && (filemtime($cacheFileAbs) < $renewTime)) {
                $cacheRewrite = true;
            }
        }
        if ($cacheRewrite) {
            global $db;
            // Get direct childs
            $arChildIds = array_keys($db->fetch_nar(
                "SELECT ID_GEO_REGION FROM `geo_region` WHERE FK_PARENT".($idGeoRegion === null ? " IS NULL" : "=".(int)$idGeoRegion)
            ));
            // Get nested childs
            foreach ($arChildIds as $childIndex => $childId) {
                $arChildIds = array_merge($arChildIds, $this->getChildRegions($childId));
            }
            $this->utilWriteCacheFile($cacheFile, implode(",", $arChildIds));
            $this->setDirtyRegion("childs", $idGeoRegion, null, false);
            return $arChildIds;
        } else {
            $result = $this->utilReadCacheFile($cacheFile);
            return (!empty($result) ? explode(",", $result) : array());
        }
    }
    
    public function getChildRegionsRaw($idGeoRegion = null) {
        $arRegionList = $this->db->fetch_table("SELECT * FROM `geo_region` WHERE ".($idGeoRegion === null ? "FK_PARENT IS NULL" : "FK_PARENT=".(int)$idGeoRegion));
        foreach ($arRegionList as $regionIndex => $regionInfo) {
            $arRegionList[$regionIndex]["OPTIONS"] = $this->getRegionDefaultOptions();
            if ($regionInfo["SER_OPTIONS"] !== null) {
                $arRegionOptions = @unserialize($regionInfo["SER_OPTIONS"]);
                if (is_array($arRegionOptions)) {
                    $arRegionList[$regionIndex]["OPTIONS"] = array_merge($arRegionList[$regionIndex]["OPTIONS"], $arRegionOptions);
                }
            }
        }
        return $arRegionList;
    }

    public function getChildRegionsCount($idGeoRegion = null) {
        return $this->db->fetch_atom("SELECT COUNT(*) FROM `geo_region` WHERE ".($idGeoRegion === null ? "FK_PARENT IS NULL" : "FK_PARENT=".(int)$idGeoRegion));
    }
    
    protected function getMapMarkers($mapType, $mapCategory, $mapRegion, $mapSearchHash, $limit, $offset, $resultCount = null, $latMin = null, $latMax = null, $lngMin = null, $lngMax = null) {
        switch ($mapType) {
            case 'marktplatz':
                return $this->getMapMarkers_Ads($mapCategory, $mapRegion, $mapSearchHash, $limit, $offset, $resultCount, $latMin, $latMax, $lngMin, $lngMax);
            default:
                return false;
        }
    }
    
    protected function getMapMarkers_Ads($mapCategory, $mapRegion, $mapSearchHash, $limit, $offset, $resultCount = null, $latMin = null, $latMax = null, $lngMin = null, $lngMax = null) {
        // Get query
        $searchQueryParams = array(
            "FK_KAT" => $mapCategory,
            "FK_GEO_REGION" => $mapRegion,
        );
        if (($latMin !== null) && ($latMax !== null) && ($lngMin !== null) && ($lngMax !== null)) {
            $searchQueryParams["GEO_RECT"] = array($latMin, $latMax, $lngMin, $lngMax);
        }
        if (!empty($mapSearchHash)) {
            $searchQuery = Ad_Marketplace::getQueryByHash($mapSearchHash, $searchQueryParams);
        } else {
            $searchQuery = Ad_Marketplace::getQueryByParams($searchQueryParams);
        }
        $searchArticleTableIdent = $searchQuery->getDataTable()->getTableIdent();
        $searchArticleTable = $searchQuery->getDataTable()->getTable($searchArticleTableIdent);
        // Trigger plugin event
		$eventMarketListParams = new Api_Entities_EventParamContainer(array(
			"language"			=> $GLOBALS['s_lang'],
			"idCategory"		=> $mapCategory,
			"table"				=> $searchArticleTable,
			"searchActive"		=> true,
			"searchData"		=> $searchQueryParams,
			"query"				=> $searchQuery,
			"queryMasterPrefix"	=> $searchArticleTableIdent
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_SEARCH_QUERY, $eventMarketListParams);
		if ($eventMarketListParams->isDirty()) {
            $searchQueryParams = $eventMarketListParams->getParam("searchData");
		}
        // Add required fields and query limit
        Ad_Marketplace::addQueryFieldsByTemplate($searchQuery, "marktplatz.row_box.htm");
        $searchQuery->addField("LATITUDE");
        $searchQuery->addField("LONGITUDE");
        $searchQuery->setLimit($limit, $offset);
        $searchQuery->addSortFields(array(
       		"B_TOP_LIST"	=> "DESC",
       		"STAMP_START"	=> "DESC",
       		"ID_AD"			=> "DESC"
       	));
        // Initialize template
        $tplAdRow = new Template("tpl/".$GLOBALS["s_lang"]."/marktplatz.row_box.htm");
        $tplAdRow->addvar("CURRENCY_DEFAULT", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);
        $tplAdRow->addvar("noads", 1);
        $tplAdRow->isTemplateCached  = true;
        $tplAdRow->isTemplateRecursiveParsable = true;
        $tplAdRowInitalVars = $tplAdRow->vars;
        $tplMarker = $this->utilGetTemplate("googlemaps.marker.ad.htm");
        $tplMarker->addvar("CURRENCY_DEFAULT", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);
        $tplMarkerInitalVars = $tplMarker->vars;
        // Get results
        #die($searchQuery->getQueryString());
        $jsonResult = array("list" => array());
        $resultCount = ($resultCount > 0 ? (int)$resultCount : $searchQuery->fetchCount());
        $arResult = $searchQuery->fetchTable();
        foreach ($arResult as $rowIndex => $arRow) {
            $tplAdRow->vars = $tplAdRowInitalVars;
            $tplAdRow->addvars($arRow);
            $tplMarker->vars = $tplMarkerInitalVars;
            $tplMarker->addvars($arRow);
            $jsonResult["list"][] = array(
                "title"     => (!empty($arRow["MANUFACTURER"]) ? $arRow["MANUFACTURER"]." " : "").$arRow["PRODUKTNAME"],
                "position"  => array("lat" => $arRow["LATITUDE"], "lng" => $arRow["LONGITUDE"]),
                "row"       => $tplAdRow->process(true),
                "marker"    => $tplMarker->process(true)
            );
        }
        // Get pager
        $tplPager = $this->utilGetTemplate("googlemaps.list.pager.htm");
        $tplPager->addvar("first", 1 + $offset);
        $tplPager->addvar("last", min(1 + $offset + $limit, $resultCount));
        $tplPager->addvar("offsetPrev", max(0, $offset - $limit));
        $tplPager->addvar("offsetNext", min($resultCount - 1, $offset + $limit));
        $tplPager->addvar("count", $resultCount);
        $jsonResult["count"] = $resultCount;
        $jsonResult["pager"] = $tplPager->process(true);
        return $jsonResult;
    }
    
    protected function getDirtyCountry($cacheType, $idCategory = null) {
        $cacheHash = md5($cacheType."_".$idCategory);
        $cacheFileAbs = $this->utilGetCacheFileAbsolute("countries/dirty/".$cacheHash.".dirty");
        return file_exists($cacheFileAbs);
    }
    
    protected function getDirtyRegion($cacheType, $idGeoRegion = null, $idCategory = null) {
        $cacheHash = md5($cacheType."_".$idCategory."_".$idGeoRegion);
        $cacheFileAbs = $this->utilGetCacheFileAbsolute("regions/dirty/".$cacheHash.".dirty");
        return file_exists($cacheFileAbs);
    }
    protected function setDirtyCountry($cacheType, $idCategory = null, $isDirty = true) {
        global $db;
        if ($isDirty) {
            // Set dirty
            $renewTime = time() - (self::$cacheLifetime * 60);
            $idGeoRegionParents = array();
            $idCategoryParents = array();
            if ($idCategory !== null) {            
                // Get ids of parent categorys
                $row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=".(int)$idCategory);
                $idCategoryParents = array_keys($db->fetch_nar("
                  SELECT ID_KAT
                    FROM `kat`
                  WHERE
                    (LFT <= " . $row_kat["LFT"] . ") AND
                    (RGT >= " . $row_kat["RGT"] . ") AND
                    (ROOT = " . $row_kat["ROOT"] . ")"));
            }
            if (($cacheType == "ad_count") || ($cacheType == "all")) {
                foreach ($idCategoryParents as $parentIndex => $parentCategoryId) {
                    foreach ($idGeoRegionParents as $parentIndex => $parentRegionId) {
                        $cacheHash = md5("ad_count_".$parentCategoryId."_all");
                        $cacheFile = $this->utilGetCacheFileAbsolute("countries/ad_count/".$cacheHash.".txt");
                        if (file_exists($cacheFile)) {
                            if (filemtime($cacheFile) < $renewTime) {
                                // File beyond minimum lifetime, just delete.
                                unlink($cacheFile);
                            } else {
                                // File below minimum lifetime, mark dirty for later recache.
                                $this->utilWriteCacheFile("countries/dirty/".$cacheHash.".dirty", 1);
                            }
                        }
                    }
                }
            }
            if (($cacheType == "html") || ($cacheType == "all")) {
                foreach ($idCategoryParents as $parentIndex => $parentCategoryId) {
                    foreach ($idGeoRegionParents as $parentIndex => $parentRegionId) {
                        $cacheHash = md5("html_".$parentCategoryId."_all");
                        $cacheFile = $this->utilGetCacheFileAbsolute("countries/html/".$cacheHash.".htm");
                        if (file_exists($cacheFile)) {
                            if (filemtime($cacheFile) < $renewTime) {
                                // File beyond minimum lifetime, just delete.
                                unlink($cacheFile);
                            } else {
                                // File below minimum lifetime, mark dirty for later recache.
                                $this->utilWriteCacheFile("countries/dirty/".$cacheHash.".dirty", 1);
                            }
                        }
                    }
                }
            }
            if (($cacheType == "childs") || ($cacheType == "all")) {
                foreach ($idGeoRegionParents as $parentIndex => $parentRegionId) {
                    $cacheFile = $this->utilGetCacheFileAbsolute("countries/childs/all.txt");
                    if (file_exists($cacheFile)) {
                        if (filemtime($cacheFile) < $renewTime) {
                            // File beyond minimum lifetime, just delete.
                            unlink($cacheFile);
                        } else {
                            // File below minimum lifetime, mark dirty for later recache.
                            $cacheHash = md5("childs_all");
                            $this->utilWriteCacheFile("countries/dirty/".$cacheHash.".dirty", 1);
                        }
                    }
                }
            }
        } else {
            // Set clean
            switch ($cacheType) {
                case 'ad_count':
                    $cacheHash = md5("ad_count_".$idCategory."_all");
                    $this->utilDeleteCacheFile("countries/dirty/".$cacheHash.".dirty");
                    break;
                case 'html':
                    $cacheHash = md5("childs_all");
                    $this->utilDeleteCacheFile("countries/dirty/".$cacheHash.".dirty");
                    break;
                case 'childs':
                    $cacheHash = md5("childs_all");
                    $this->utilDeleteCacheFile("countries/dirty/".$cacheHash.".dirty");
                    break;
            };
        }
        return true;
    }
    
    protected function setDirtyRegion($cacheType, $idGeoRegion = null, $idCategory = null, $isDirty = true) {
        global $db;
        if ($isDirty) {
            // Set dirty
            $renewTime = time() - (self::$cacheLifetime * 60);
            $idGeoRegionParents = array();
            $idCategoryParents = array();
            if ($idGeoRegion !== null) {
                $arGeoRegionPath = unserialize($db->fetch_atom("SELECT SER_PATH FROM `geo_region` WHERE ID_GEO_REGION=".(int)$idGeoRegion));
                if (is_array($arGeoRegionPath)) {
                    $idGeoRegionParents = $arGeoRegionPath;
                }
                $idGeoRegionParents[] = $idGeoRegion;
            }
            if ($idCategory !== null) {            
                // Get ids of parent categorys
                $row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=".(int)$idCategory);
                $idCategoryParents = array_keys($db->fetch_nar("
                  SELECT ID_KAT
                    FROM `kat`
                  WHERE
                    (LFT <= " . $row_kat["LFT"] . ") AND
                    (RGT >= " . $row_kat["RGT"] . ") AND
                    (ROOT = " . $row_kat["ROOT"] . ")"));
            }
            if (($cacheType == "ad_count") || ($cacheType == "all")) {
                foreach ($idCategoryParents as $parentIndex => $parentCategoryId) {
                    foreach ($idGeoRegionParents as $parentIndex => $parentRegionId) {
                        $cacheHash = md5("ad_count_".$parentCategoryId."_".$parentRegionId);
                        $cacheFile = $this->utilGetCacheFileAbsolute("regions/ad_count/".$cacheHash.".txt");
                        if (file_exists($cacheFile)) {
                            if (filemtime($cacheFile) < $renewTime) {
                                // File beyond minimum lifetime, just delete.
                                unlink($cacheFile);
                            } else {
                                // File below minimum lifetime, mark dirty for later recache.
                                $this->utilWriteCacheFile("regions/dirty/".$cacheHash.".dirty", 1);
                            }
                        }
                    }
                }
            }
            if (($cacheType == "html") || ($cacheType == "all")) {
                foreach ($idCategoryParents as $parentIndex => $parentCategoryId) {
                    foreach ($idGeoRegionParents as $parentIndex => $parentRegionId) {
                        $cacheHash = md5("html_".$parentCategoryId."_".$parentRegionId);
                        $cacheFile = $this->utilGetCacheFileAbsolute("regions/html/".$cacheHash.".htm");
                        if (file_exists($cacheFile)) {
                            if (filemtime($cacheFile) < $renewTime) {
                                // File beyond minimum lifetime, just delete.
                                unlink($cacheFile);
                            } else {
                                // File below minimum lifetime, mark dirty for later recache.
                                $this->utilWriteCacheFile("regions/dirty/".$cacheHash.".dirty", 1);
                            }
                        }
                    }
                }
            }
            if (($cacheType == "childs") || ($cacheType == "all")) {
                foreach ($idGeoRegionParents as $parentIndex => $parentRegionId) {
                    $cacheFile = $this->utilGetCacheFileAbsolute("regions/childs/".($parentRegionId === null ? "all" : (int)$parentRegionId).".txt");
                    if (file_exists($cacheFile)) {
                        if (filemtime($cacheFile) < $renewTime) {
                            // File beyond minimum lifetime, just delete.
                            unlink($cacheFile);
                        } else {
                            // File below minimum lifetime, mark dirty for later recache.
                            $cacheHash = md5("childs_".($parentRegionId === null ? "all" : $parentRegionId));
                            $this->utilWriteCacheFile("regions/dirty/".$cacheHash.".dirty", 1);
                        }
                    }
                }
            }
        } else {
            // Set clean
            switch ($cacheType) {
                case 'ad_count':
                    $cacheHash = md5("ad_count_".$idCategory."_".$idGeoRegion);
                    $this->utilDeleteCacheFile("regions/dirty/".$cacheHash.".dirty");
                    break;
                case 'html':
                    $cacheHash = md5("childs_".($idGeoRegion === null ? "all" : $idGeoRegion));
                    $this->utilDeleteCacheFile("regions/dirty/".$cacheHash.".dirty");
                    break;
                case 'childs':
                    $cacheHash = md5("childs_".($idGeoRegion === null ? "all" : $idGeoRegion));
                    $this->utilDeleteCacheFile("regions/dirty/".$cacheHash.".dirty");
                    break;
            };
        }
        return true;
    }
}