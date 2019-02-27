<?php

class Api_Plugins_Hydromot_Plugin extends Api_TraderApiPlugin {

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
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_LIST_QUERY, "marketplaceListQuery");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_LIST_POST_PROCESSING, "marketplaceListPostProcessing");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_SEARCH_QUERY, "marketplaceAdSearchQuery");
        $this->registerEvent(Api_TraderApiEvents::PACKET_NEW_INVOICE, "packetNewInvoice");
        $this->registerEvent(Api_TraderApiEvents::PACKET_RENEW, "packetRenew");
        $this->registerEvent(Api_TraderApiEvents::TEMPLATE_PLUGIN_FUNCTION, "templatePluginFunction");
        return true;
    }
    
    public function marketplaceListQuery(Api_Entities_EventParamContainer $params) {
        // Query
        $table = $params->getParam("table");
        /** @var Api_DataTableQuery $query */
        $query = $params->getParam("query");
        if (($table == "artikel_master") || ($table == "ad_master")) {
            $query->addGroupField("ID_MAN_GROUP_MASTER");
            $query->addGroupField("ID_PRODUCT_GROUP_MASTER");
        }
        // Template
        $templateType = "row";
        if (preg_match("/tpl\/[a-z]+\/(.+)\.(.+)\.htm/", $params->getParam("templateRow"), $arMatchType)) {
            $templateType = $arMatchType[2];
        }
        switch ($templateType) {
            default:
            case "row":
                $templateNew = "marktplatz.product.htm";
                break;
            case "row_box":
                $templateNew = "marktplatz.product_box.htm";
                break;
        }
        $templateFileAbs = $this->utilGetTemplateCachedPath($templateNew);
        if (!file_exists($templateFileAbs)) {
            $this->utilGetTemplateRaw($templateNew);
        }
        $params->setParam("templateRow", $templateFileAbs);
        return;
        
        
        $table = $params->getParam("table");
        if (($table == "artikel_master") || ($table == "ad_master")) {
            if (!$params->getParam("groupByProduct")) {
                /** @var Api_DataTableQuery $query */
                $query = $params->getParam("query");
                $query->addGroupField("ID_AD_MASTER");
                return;
            }
            /** @var Api_DataTableQuery $query */
            $query = $params->getParam("query");
            $queryTable = $query->getDataTable();
            $queryTable->addField(null, null, "COUNT(`a`.ID_AD_MASTER)", "ARTICLE_COUNT", true, false, true);
            $queryTable->addField(null, null, "`a`.FK_PRODUCT", "ID_PRODUCT", true, false, true);
            $queryTable->addField(null, null, "IFNULL(".$queryTable->getTableIdent().".PRODUKTNAME,`a`.PRODUKTNAME)", "FULL_PRODUKTNAME", true, false, true);
            $query->addField("ARTICLE_COUNT");
            $query->addField("ID_PRODUCT");
            $query->addField("FULL_PRODUKTNAME");
            $query->addGroupField("ID_MAN_GROUP_MASTER");
            $query->addGroupField("ID_PRODUCT_GROUP_MASTER");
        }
        $templateType = "row";
        if (preg_match("/tpl\/[a-z]+\/(.+)\.(.+)\.htm/", $params->getParam("templateRow"), $arMatchType)) {
            $templateType = $arMatchType[2];
        }
        switch ($templateType) {
            default:
            case "row":
                $templateNew = "marktplatz.product.htm";
                break;
            case "row_box":
                $templateNew = "marktplatz.product_box.htm";
                break;
        }
        $templateFileAbs = $this->utilGetTemplateCachedPath($templateNew);
        if (!file_exists($templateFileAbs)) {
            $this->utilGetTemplateRaw($templateNew);
        }
        $params->setParam("templateRow", $templateFileAbs);
    }
    
    public function marketplaceListPostProcessing(Api_Entities_EventParamContainer $params) {
        $table = $params->getParam("table");
        if ((($table == "artikel_master") || ($table == "ad_master")) && $params->getParam("groupByProduct")) {
            $articleList = $params->getParam("list");
            $articleTables = [];
        }
    }
    
    public function marketplaceAdSearchQuery(Api_Entities_EventParamContainer $params) {        
        $idCategory = $params->getParam("idCategory");
        $table = $params->getParam("table");
        $searchData = $params->getParam("searchData");
        if (($table == "artikel_master") || ($table == "ad_master") || !empty($searchData["NO_GROUPING"])) {
            if (empty($searchData["MANUAL_GROUPING"])) {
                /** @var Api_DataTableQuery $query */
                $query = $params->getParam("query");
                $query->addGroupField("ID_AD_MASTER");
            }
            return;
        }
        /** @var Api_DataTableQuery $query */
        $query = $params->getParam("query");
        $query->addField("ID_PRODUCT");
        $query->addGroupField("ID_PRODUCT_GROUP");
        return;
        
        
        $idCategory = $params->getParam("idCategory");
        $table = $params->getParam("table");
        $searchData = $params->getParam("searchData");
        if (($table == "artikel_master") || ($table == "ad_master") || !empty($searchData["NO_GROUPING"])) {
            if (empty($searchData["MANUAL_GROUPING"])) {
                /** @var Api_DataTableQuery $query */
                $query = $params->getParam("query");
                $query->addGroupField("ID_AD_MASTER");
            }
            return;
        }
        
        /** @var Api_DataTableQuery $query */
        $query = $params->getParam("query");
        $query->addField("ID_PRODUCT");
        $query->addField("PREIS_MIN");
        $query->addGroupField("ID_PRODUCT_GROUP");
        $dataTableIntermediate = Rest_MarketplaceAds::getDataTable($idCategory, $this->db);
        $dataTableIntermediate->addField(null, null, "`intermediate_temp`.RESULT_COUNT_FAST", "ARTICLE_COUNT", true, false, true);
        $dataTableIntermediate->addField(null, null, "`intermediate_temp`.PREIS_MIN", "PREIS_MIN", false, false, true);
        $dataTableIntermediate->addField(null, null, "IFNULL(p.FULL_PRODUKTNAME,".$dataTableIntermediate->getTableIdent().".PRODUKTNAME)", "FULL_PRODUKTNAME", true, false, true);
        $queryIntermediate = new Api_DataTableQueryIntermediate($this->db, $query, array("ID_AD" => NULL, "ID_PRODUCT" => NULL, "RESULT_COUNT_FAST" => NULL, "PREIS_MIN" => NULL), $dataTableIntermediate);
        $queryIntermediate->addField("ID_PRODUCT");
        $queryIntermediate->addField("ARTICLE_COUNT");
        $queryIntermediate->addField("PREIS_MIN");
        $queryIntermediate->addField("IMG_DEFAULT_SRC");
        $queryIntermediate->addSortField("ARTICLE_COUNT", "DESC");
        $params->setParam("query", $queryIntermediate);
    }
    
    public function packetNewInvoice($arParams) {
        require_once $GLOBALS["ab_path"]."sys/packet_management.php";
        $packets = PacketManagement::getInstance($this->db);
        $packetId = $arParams["ID_PACKET_ORDER"];
        $packetObj = $packets->order_get($packetId);
        if ($packetObj instanceof PacketOrderBase) {
            $packetObj->itemRemContent("lead");
        }
    }
    
    public function packetRenew($arParams) {
        require_once $GLOBALS["ab_path"]."sys/packet_management.php";
        $packets = PacketManagement::getInstance($this->db);
        $packetId = $arParams["FK_PACKET_ORDER_OLD"];
        $packetObj = $packets->order_get($packetId);
        if ($packetObj instanceof PacketOrderBase) {
            $packetObj->itemRemContent("lead");
        }
    }
     
    /**
     * Called when a plugin function is invoked (within template: {plugin(MyPlugin,MyFunction,Param1,Param2)} )
     */
    public function templatePluginFunction(Api_Entities_EventParamContainer $params) {
        /** @var string $action */
        $action = $params->getParam("action"); 
        /** @var array $arParams */
        $arParams = $params->getParam("params");
        /** @var string $result */
        $result = $params->getParam("result");
        /** @var Template $template */
        $template = $params->getParam("template");
        
        switch ($action) {
            case "ProductLink":
                if (!array_key_exists("ARTICLE_COUNT", $template->vars)) {
                    if (array_key_exists("FK_PRODUCT", $template->vars) && ($template->vars["FK_PRODUCT"] > 0)) {
                        $template->vars["ARTICLE_COUNT"] = $this->db->fetch_atom("
                            SELECT COUNT(*)
                            FROM `".mysql_real_escape_string($template->vars["AD_TABLE"])."`
                            WHERE FK_PRODUCT=".(int)$template->vars["FK_PRODUCT"]);
                        if (!array_key_exists("ID_PRODUCT", $template->vars)) {
                            $template->vars["ID_PRODUCT"] = $template->vars["FK_PRODUCT"];
                        }
                    } else {
                        $template->vars["ARTICLE_COUNT"] = 1;
                    }
                }
                $articleCount = (int)$template->vars["ARTICLE_COUNT"];
                if ($articleCount <= 1) {
                    $result = $template->tpl_uri_action("marktplatz_anzeige,{ID_AD},{urllabel(PRODUKTNAME)}|KAT_PATH={market_kat_path_url({FK_KAT})}");
                } else {
                    $result = $template->tpl_uri_action("product_details,{FK_KAT},{ID_PRODUCT}|KAT_PATH={market_kat_path_url({FK_KAT})})}");
                }
                break;
            case "ProductImage":
                // {plugin(Hydromot,ProductImage,ImagesSerialized,Width,Height,Crop,Gravity)}
                $importImages = @unserialize( $template->vars[ $arParams[0] ] );
                $imageTpl = $this->utilGetTemplate("marktplatz.product.image.htm");
                $previewWidth = (array_key_exists(1, $arParams) ? (int)$template->parseTemplateString($arParams[1]) : 250);
                $previewHeight = (array_key_exists(2, $arParams) ? (int)$template->parseTemplateString($arParams[2]) : 150);
                $previewCrop = (array_key_exists(3, $arParams) ? $template->parseTemplateString($arParams[3]) : "crop");
                $previewGravity = (array_key_exists(4, $arParams) ? $template->parseTemplateString($arParams[4]) : "Center");
                if (is_array($importImages) && !empty($importImages)) {
                    $imageTpl->addvar("SRC", $importImages[0]);
                    $imageTpl->addvar("THUMBNAIL", $imageTpl->tpl_thumbnail("{SRC},".$previewWidth.",".$previewHeight.",".$previewCrop.",".$previewGravity));
                } else if (!empty($template->vars["IMG_DEFAULT_SRC"])) {
                    $imageTpl->addvar("ID_AD", $template->vars["ID_AD"]);
                    $imageTpl->addvar("IMG_DEFAULT_SRC", $template->vars["IMG_DEFAULT_SRC"]);
                    $imageTpl->addvar("THUMBNAIL", $template->tpl_thumbnail_article("{ID_AD},{IMG_DEFAULT_SRC},".$previewWidth.",".$previewHeight.",".$previewCrop));
                } else {
                    $imageTpl->addvar("SRC", $imageTpl->tpl_uri_resource("/images/marketplace/nopic.jpg"));
                    $imageTpl->addvar("THUMBNAIL", $imageTpl->tpl_thumbnail("{SRC},".$previewWidth.",".$previewHeight.",".$previewCrop.",".$previewGravity));
                }    
                    $result = $imageTpl->process();
                break;
                
        }
        $params->setParam("result", $result);
    }
}