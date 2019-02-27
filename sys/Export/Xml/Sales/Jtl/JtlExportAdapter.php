<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 01.09.14
 * Time: 16:10
 */

class Export_Xml_Sales_Jtl_JtlExportAdapter extends Export_Xml_Sales_GenericExportAdapter {

    protected static function readTranslation($name, $default = null) {
        return Translation::readTranslation("marketplace", "order.export.jtl.".$name, null, array(), $default);
    }

    /**
     * Add header / opening tags to the xml document
     * @return bool     true for success, false for failure.
     */
    function documentInitialize() {
        $this->xmlWriter->setIndent(true);
        $this->xmlWriter->startDocument('1.0', 'UTF-8');
        $this->xmlWriter->startElement("tBestellungen");
    }

    /**
     * Close tags opened on documentInitialize()
     * @return bool     true for success, false for failure.
     */
    function documentFinish() {
        $this->xmlWriter->endElement();
        $this->xmlWriter->endDocument();
    }

    private function transformCurrency($currency) {
        switch ($currency) {
            case '€':
            default:
                return "EUR";
            case 'CHF':
                return "CHF";
        }
    }

    public function transformObject() {
        if ($this->arObject['STAMP_CREATE'] !== null) {
            $this->arObject['STAMP_CREATE'] = date('Y-m-d', strtotime($this->arObject['STAMP_CREATE']));
        }
        if ($this->arObject['STAMP_PAID'] !== null) {
            $this->arObject['STAMP_PAID'] = date('Y-m-d', strtotime($this->arObject['STAMP_PAID']));
        }
        if ($this->arObject['STAMP_SHIPPED'] !== null) {
            $this->arObject['STAMP_SHIPPED'] = date('Y-m-d', strtotime($this->arObject['STAMP_SHIPPED']));
        }
        if ($this->arObject['SHIPPING_PRICE'] > 0) {
            $this->arObject['items'][] = array(
                "PRODUKTNAME"   => self::readTranslation("shipping", "Versandkosten"),
                "PREIS"         => $this->arObject['SHIPPING_PRICE'],
                "MENGE"         => 1
            );
        }
        return true;
    }

    public function writeObject() {
        $this->xmlWriter->startElement("tBestellung");
        // Attributes
        $this->xmlWriter->writeAttribute("kFirma", $this->arObject['USER_VK_ID']);
        $this->xmlWriter->writeAttribute("cFirma", $this->arObject['USER_VK_NAME']);
        #$this->xmlWriter->writeAttribute("cMandant", "");
        #$this->xmlWriter->writeAttribute("cRechnungsNr", "");
        // Add otherwise missing information as text
        $bemerkung = self::readTranslation("payment", "Zahlungsart").": ".
            ($this->arObject['PAYMENT_ADAPTER_NAME'] !== null ? $this->arObject['PAYMENT_ADAPTER_NAME'] : self::readTranslation("payment.default", "Keine Angabe"));
        // Child nodes
        $this->addSimpleNode("cSprache", "ger");
        $this->addSimpleNode("cWaehrung", $this->transformCurrency($GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']));
        $this->addSimpleNode("fGuthaben", 0);
        $this->addSimpleNode("fGesamtsumme", $this->arObject['TOTAL_PRICE']);
        $this->addSimpleNode("cBestellNr");
        $this->addSimpleNode("cExterneBestellNr", $this->arObject['ID_AD_ORDER']);
        $this->addSimpleNode("cVersandartName");
        $this->addSimpleNode("cVersandInfo");
        $this->addSimpleNode("dVersandDatum", $this->arObject['STAMP_SHIPPED']);
        $this->addSimpleNode("cTracking");
        $this->addSimpleNode("cLogistiker");
        $this->addSimpleNode("dLieferDatum");
        $this->addSimpleNode("cKommentar", $this->arObject['REMARKS']);
        $this->addSimpleNode("cBemerkung", $bemerkung);
        $this->addSimpleNode("dErstellt", $this->arObject['STAMP_CREATE']);
        $this->addSimpleNode("cZahlungsartName");
        $this->addSimpleNode("dBezahltDatum", $this->arObject['STAMP_PAID']);
        $this->addSimpleNode("fBezahlt", ($this->arObject['STAMP_PAID'] !== null ? $this->arObject['PRICE'] : 0));
        //
        // Order items
        if (is_array($this->arObject['items'])) {
            foreach ($this->arObject['items'] as $itemIndex => $arItem) {
                $this->xmlWriter->startElement("twarenkorbpos");
                $this->addSimpleNode("cName", $arItem['PRODUKTNAME']);
                if (!empty($arItem['FK_AD'])) {
                    $this->addSimpleNode("cArtNr", $arItem['FK_AD']);
                }
                $this->addSimpleNode("cBarcode");
                $this->addSimpleNode("cSeriennummer");
                $this->addSimpleNode("cEinheit");
                $this->addSimpleNode("fPreisEinzelNetto", $arItem['PREIS'] / $arItem['MENGE']);
                $this->addSimpleNode("fPreis", $arItem['PREIS']);
                $this->addSimpleNode("fMwSt");
                $this->addSimpleNode("fAnzahl", $arItem['MENGE']);
                $this->addSimpleNode("cPosTyp", "standard");
                $this->addSimpleNode("fRabatt");
                $this->xmlWriter->endElement();
            }
        }
        // User
        {
            $this->xmlWriter->startElement("tkunde");
            $this->addSimpleNode("cKundenNr", "et-".$this->arObject['USER_EK_ID']);
            $this->addSimpleNode("cAnrede");
            $this->addSimpleNode("cTitel");
            $this->addSimpleNode("cVorname", $this->arObject['ADDRESS_INVOICE_VORNAME']);
            $this->addSimpleNode("cNachname", $this->arObject['ADDRESS_INVOICE_NACHNAME']);
            $this->addSimpleNode("cFirma", $this->arObject['ADDRESS_INVOICE_FIRMA']);
            $this->addSimpleNode("cAnrede");
            $this->addSimpleNode("cStrasse", $this->arObject['ADDRESS_INVOICE_STRASSE']);
            $this->addSimpleNode("cAdressZusatz");
            $this->addSimpleNode("cPLZ", $this->arObject['ADDRESS_INVOICE_PLZ']);
            $this->addSimpleNode("cOrt", $this->arObject['ADDRESS_INVOICE_ORT']);
            $this->addSimpleNode("cBundesland");
            $this->addSimpleNode("cLand", $this->arObject['ADDRESS_INVOICE_LAND']);
            $this->addSimpleNode("cTel");
            $this->addSimpleNode("cMobil");
            $this->addSimpleNode("cFax");
            $this->addSimpleNode("cMail", $this->arObject['USER_EK_EMAIL']);
            $this->addSimpleNode("cUSTID", $this->arObject['USER_EK_UST_ID']);
            $this->addSimpleNode("cWWW");
            $this->addSimpleNode("cNewsletter");
            $this->addSimpleNode("dGeburtstag");
            $this->addSimpleNode("fRabatt", 0);
            $this->addSimpleNode("cHerkunft");
            $this->addSimpleNode("dErstellt");
            $this->xmlWriter->endElement();
        }
        // Shipping address
        {
            $this->xmlWriter->startElement("tlieferadresse");
            $this->addSimpleNode("cAnrede");
            $this->addSimpleNode("cVorname", $this->arObject['ADDRESS_VERSAND_VORNAME']);
            $this->addSimpleNode("cNachname", $this->arObject['ADDRESS_VERSAND_NACHNAME']);
            $this->addSimpleNode("cTitel");
            $this->addSimpleNode("cFirma", $this->arObject['ADDRESS_VERSAND_FIRMA']);
            $this->addSimpleNode("cStrasse", $this->arObject['ADDRESS_VERSAND_STRASSE']);
            $this->addSimpleNode("cAdressZusatz");
            $this->addSimpleNode("cPLZ", $this->arObject['ADDRESS_VERSAND_PLZ']);
            $this->addSimpleNode("cOrt", $this->arObject['ADDRESS_VERSAND_ORT']);
            $this->addSimpleNode("cBundesland");
            $this->addSimpleNode("cLand", $this->arObject['ADDRESS_VERSAND_LAND']);
            $this->addSimpleNode("cTel");
            $this->addSimpleNode("cMobil");
            $this->addSimpleNode("cFax");
            $this->addSimpleNode("cMail");
            $this->xmlWriter->endElement();
        }
        // Payment information
        {
            $this->xmlWriter->startElement("tzahlungsinfo");
            $this->addSimpleNode("cBankName");
            $this->addSimpleNode("cBLZ");
            $this->addSimpleNode("cKontoNr");
            $this->addSimpleNode("cKartenNr");
            $this->addSimpleNode("dGueltigkeit");
            $this->addSimpleNode("cCVV");
            $this->addSimpleNode("cKartenTyp");
            $this->addSimpleNode("cInhaber");
            $this->xmlWriter->endElement();
        }
        $this->xmlWriter->endElement();
    }
}