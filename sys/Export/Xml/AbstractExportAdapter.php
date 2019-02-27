<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 01.09.14
 * Time: 14:30
 */

abstract class Export_Xml_AbstractExportAdapter implements Export_Xml_ExportAdapterInterface {

    protected $db;
    protected $lang;
    protected $xmlWriter;

    protected $queryResult;
    protected $arObject;

    function __construct(ebiz_db $database, $xmlFilename = null, $language = null) {
        $this->db = $database;
        $this->lang = ($language !== null ? (int)$language : (int)$GLOBALS['langval']);
        $this->xmlWriter = new XMLWriter();
        if ($xmlFilename === null) {
            $this->xmlWriter->openMemory();
        } else {
            $this->xmlWriter->openURI($xmlFilename);
        }
    }

    function __destruct() {
        $this->xmlWriter->flush();
    }

    /**
     * Add a new xml element
     * @param string    $elementName
     * @param string    $elementText
     * @param array     $elementAttributes
     */
    public function addSimpleNode($elementName, $elementText = null, $elementAttributes = null) {
        // Open element
        $this->xmlWriter->startElement($elementName);
        // Add attributes (if set)
        if (is_array($elementAttributes)) {
            foreach ($elementAttributes as $attrName => $attrValue) {
                $this->xmlWriter->writeAttribute($attrName, $attrValue);
            }
        }
        // Add text (if set)
        if ($elementText !== null) {
            $this->xmlWriter->text($elementText);
        }
        // Close element
        $this->xmlWriter->endElement();
    }

    public function getXmlWriter() {
        return $this->xmlWriter;
    }

    public function getXml($flush = true) {
        return $this->xmlWriter->outputMemory($flush);
    }

    public function process($arFilter = array(), $limit = null, $offset = null) {
        if (!$this->queryData($arFilter, $limit, $offset)) {
            return false;
        }
        $this->documentInitialize();
        while ($this->readObject()) {
            if ($this->transformObject()) {
                $this->writeObject();
            }
        }
        $this->documentFinish();
        return true;
    }

    public function queryData($arFilter = array(), $limit = null, $offset = null) {
        $query = $this->getDatabaseQuery($arFilter, $limit, $offset);
        $this->queryResult = $this->db->querynow($query);
        if ($this->queryResult['rsrc'] === false) {
            // Query failed!
            eventlog("error", "Failed to query data for xml export!", $query);
            return false;
        }
        return true;
    }

    public function readObject() {
        $this->arObject = mysql_fetch_assoc($this->queryResult['rsrc']);
        return ($this->arObject !== false);
    }

    public function transformObject() {
        return true;
    }

    public function streamXml($filename = "orders.xml") {
        header("Content-Type: application/xml");
        header("Content-Disposition: attachment; filename=\"".$filename."\"");
        echo($this->getXml());
    }

}