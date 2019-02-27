<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 01.09.14
 * Time: 16:10
 */

class Export_Xml_Sales_GenericExportAdapter extends Export_Xml_AbstractExportAdapter {

    protected $queryResultItems;
    protected $arItem;
    protected $adOrderManagement;

    function __construct(ebiz_db $database, $xmlFilename = null, $language = null) {
        parent::__construct($database, $xmlFilename, $language);
        require_once $GLOBALS['ab_path'].'sys/lib.ad_order.php';
        $this->adOrderManagement = AdOrderManagement::getInstance($this->db);
        $this->arItem = false;
    }

    /**
     * Add header / opening tags to the xml document
     * @return bool     true for success, false for failure.
     */
    function documentInitialize() {
        $this->xmlWriter->setIndent(true);
        $this->xmlWriter->startDocument('1.0', 'UTF-8');
        $this->xmlWriter->startElement("orders");
    }

    /**
     * Close tags opened on documentInitialize()
     * @return bool     true for success, false for failure.
     */
    function documentFinish() {
        $this->xmlWriter->endElement();
        $this->xmlWriter->endDocument();
    }

    public function getDatabaseQuery($arFilter = array(), $limit = null, $offset = null) {
        $arWhere = array();
        // TODO: Map $arFilter to $arWhere
        $query = $this->adOrderManagement->getQuery(
            array_merge($arFilter, array('ONLY_READ_ID' => 1, "SORT" => "ao.ID_AD_ORDER", "SORT_DIR" => "ASC"))
        );
        $arOrderIds = array_keys($this->db->fetch_nar($query));
        if (empty($arOrderIds)) {
            return array(false, false);
        }
        $arFilterIds = array("ID_AD_ORDER" => $arOrderIds, "SORT" => "ao.ID_AD_ORDER", "SORT_DIR" => "ASC");
        $arFilterItems = array("FK_AD_ORDER" => $arOrderIds, "SORT" => "ads.FK_AD_ORDER", "SORT_DIR" => "ASC");
        $queryOrders = $this->adOrderManagement->getQuery($arFilterIds);
        $queryItems = $this->adOrderManagement->getQueryItems($arFilterItems);
        return array($queryOrders, $queryItems);
    }

    public function queryData($arFilter = array(), $limit = null, $offset = null) {
        list($queryOrders, $queryItems) = $this->getDatabaseQuery($arFilter, $limit, $offset);
        if (($queryOrders == false) || ($queryItems == false)) {
            return false;
        }
        $this->queryResult = $this->db->querynow($queryOrders);
        $this->queryResultItems = $this->db->querynow($queryItems);
        if ($this->queryResult['rsrc'] === false) {
            // Query failed!
            eventlog("error", "Failed to query data for xml export! (Orders)", $queryOrders);
            return false;
        }
        eventlog("error", "Failed to query data for xml export!", $query);
        if ($this->queryResultItems['rsrc'] === false) {
            // Query failed!
            eventlog("error", "Failed to query data for xml export! (Order-Items)", $queryItems);
            return false;
        }
        return true;
    }

    public function readObject() {
        $this->arObject = mysql_fetch_assoc($this->queryResult['rsrc']);
        if ($this->arObject === false) {
            return false;
        }
        $this->arObject['ITEMS'] = array();
        if ($this->arItem === false) {
            $this->arItem = mysql_fetch_assoc($this->queryResultItems['rsrc']);
        }
        while (($this->arItem !== false) && ($this->arItem['FK_AD_ORDER'] == $this->arObject['ID_AD_ORDER'])) {
            $this->arObject['items'][] = $this->arItem;
            $this->arItem = mysql_fetch_assoc($this->queryResultItems['rsrc']);
        }
        $this->adOrderManagement->addGeneratedFields($this->arObject);
        return true;
    }

    public function writeObject() {
        $this->xmlWriter->startElement("order");
        // Attributes
        $this->xmlWriter->writeAttribute("id", $this->arObject['ID_AD_ORDER']);
        // Child nodes
        $this->addSimpleNode("idOrder", $this->arObject['ID_AD_ORDER']);
        $this->addSimpleNode("idTransaction", $this->arObject['TRANSACTION_ID']);
        $this->addSimpleNode("language", $this->lang);
        //
        $this->addSimpleNode("currency", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);
        $this->addSimpleNode("priceSum", $this->arObject['TOTAL_PRICE']);
        $this->addSimpleNode("paid", ($this->arObject['STAMP_PAID'] !== null ? $this->arObject['PRICE'] : 0));
        $this->addSimpleNode("remarks", $this->arObject['REMARKS']);
        $this->addSimpleNode("dateCreated", $this->arObject['STAMP_CREATE']);
        $this->addSimpleNode("dateShipped", ($this->arObject['STAMP_SHIPPED'] !== null ? $this->arObject['STAMP_SHIPPED'] : ""));
        $this->addSimpleNode("datePaid", ($this->arObject['STAMP_PAID'] !== null ? $this->arObject['STAMP_PAID'] : ""));
        // Order items
        foreach ($this->arObject['items'] as $itemIndex => $arItem) {
            $this->xmlWriter->startElement("item");
            $this->addSimpleNode("name", $arItem['PRODUKTNAME']);
            $this->addSimpleNode("artNr", $arItem['FK_AD']);
            $this->addSimpleNode("amount", $arItem['MENGE']);
            $this->addSimpleNode("priceSingle", $arItem['PREIS'] / $arItem['MENGE']);
            $this->addSimpleNode("priceAll", $arItem['PREIS']);
            $this->xmlWriter->endElement();
        }
        // User
        {
            $this->xmlWriter->startElement("customer");
            $this->addSimpleNode("id", $this->arObject['USER_EK_ID']);
            $this->addSimpleNode("company", $this->arObject['ADDRESS_INVOICE_FIRMA']);
            $this->addSimpleNode("firstname", $this->arObject['ADDRESS_INVOICE_VORNAME']);
            $this->addSimpleNode("lastname", $this->arObject['ADDRESS_INVOICE_NACHNAME']);
            $this->addSimpleNode("street", $this->arObject['ADDRESS_INVOICE_STRASSE']);
            $this->addSimpleNode("zip", $this->arObject['ADDRESS_INVOICE_PLZ']);
            $this->addSimpleNode("city", $this->arObject['ADDRESS_INVOICE_ORT']);
            $this->addSimpleNode("country", $this->arObject['ADDRESS_INVOICE_LAND']);
            $this->xmlWriter->endElement();
        }
        // Shipping address
        {
            $this->xmlWriter->startElement("shippingAddress");
            $this->addSimpleNode("company", $this->arObject['ADDRESS_VERSAND_FIRMA']);
            $this->addSimpleNode("firstname", $this->arObject['ADDRESS_VERSAND_VORNAME']);
            $this->addSimpleNode("lastname", $this->arObject['ADDRESS_VERSAND_NACHNAME']);
            $this->addSimpleNode("street", $this->arObject['ADDRESS_VERSAND_STRASSE']);
            $this->addSimpleNode("zip", $this->arObject['ADDRESS_VERSAND_PLZ']);
            $this->addSimpleNode("city", $this->arObject['ADDRESS_VERSAND_ORT']);
            $this->addSimpleNode("country", $this->arObject['ADDRESS_VERSAND_LAND']);
            $this->xmlWriter->endElement();
        }
        $this->xmlWriter->endElement();
    }
}