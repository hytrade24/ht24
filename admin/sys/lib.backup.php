<?php
/**
 * Created by JetBrains PhpStorm.
 * User: jens
 * Date: 04.10.13
 * Time: 11:29
 */

class Backup {
    private $version = "0.0.1";
    private $database;
    private $configDefaults = array(
        'tables'    => true,
        'fields'    => true
    );

    function __construct($db) {
        $this->database = $db;
    }

    /**
     * @param array $config
     */
    public function xmlBackup($config = array()) {
        $config = array_merge($this->configDefaults, $config);
        $xmlBackup = new SimpleXMLElement("<backup></backup>");
        $xmlBackup->addAttribute("version", $this->version);
        if ($config['tables']) {
            $this->xmlGetMarketTables($xmlBackup, $config['fields']);
        }
        return $xmlBackup->asXML();
    }

    /**
     * Creates a xml-object containing a backup of the article tables
     * @return SimpleXMLElement
     */
    private function xmlGetMarketTables($xmlBackup, $includeFields = true, $includeData = false) {
        $xmlTables = $xmlBackup->addChild("articleTables");
        $arTables = $this->database->fetch_table("SELECT * FROM `table_def`");
        foreach ($arTables as $index => $arTable) {
            $xmlTable = $xmlTables->addChild("articleTable");
            // Basic table information
            $xmlTableName = $xmlTable->addChild("id", $arTable["ID_TABLE_DEF"]);
            $xmlTableName = $xmlTable->addChild("name", $arTable["T_NAME"]);
            $xmlTableNameShort = $xmlTable->addChild("nameShort", $arTable["T_NAME_SHORT"]);
            $xmlTableStampCreate = $xmlTable->addChild("stampCreate", $arTable["STAMP_CREATE"]);
            $xmlTableStampUpdate = $xmlTable->addChild("stampUpdate", $arTable["STAMP_UPDATE"]);
            // Multilingual description
            $arTableDescription = $this->database->fetch_table("SELECT * FROM `string_app` WHERE S_TABLE='table_def' AND FK=".$arTable["ID_TABLE_DEF"]);
            $xmlTableDescription = $xmlTable->addChild("description");
            foreach ($arTableDescription as $indexLang => $arDescription) {
                $xmlTranslation = $xmlTableDescription->addChild("translation");
                $xmlTranslation->addAttribute("language", $arDescription["BF_LANG"]);
                $xmlTranslationV1 = $xmlTranslation->addChild("textShort1", $arDescription["V1"]);
                $xmlTranslationV2 = $xmlTranslation->addChild("textShort2", $arDescription["V2"]);
                $xmlTranslationT1 = $xmlTranslation->addChild("textLong", $arDescription["T1"]);
            }
            if ($includeFields) {
                // Additional fields
                $xmlTableFields = $this->xmlGetMarketTableFields($xmlTable, $arTable["ID_TABLE_DEF"]);
            }
        }
        return $xmlTables;
    }

    /**
     * Creates a xml-object containing a backup of the article tables fields
     * @return SimpleXMLElement
     */
    private function xmlGetMarketTableFields($xmlParent, $idTable, $includeList = false) {
        // Additional fields
        $xmlTableFields = $xmlParent->addChild("fields");
        $arTableFields = $this->database->fetch_table("SELECT * FROM `field_def` WHERE FK_TABLE_DEF=".$idTable." ORDER BY F_ORDER ASC");
        foreach ($arTableFields as $indexField => $arField) {
            $xmlField = $xmlTableFields->addChild("field");
            $xmlTableId = $xmlField->addChild("id", $arField["ID_FIELD_DEF"]);
            $xmlTableName = $xmlField->addChild("name", $arField["F_NAME"]);
            if ($arField["SER_CONF"] !== NULL) {
                $xmlTableConfig = $xmlField->addChild("config", $arField["SER_CONF"]);
            }
            if ($arField["F_DEC_INTERN"] !== NULL) {
                $xmlTableDescription = $xmlField->addChild("description", $arField["F_DEC_INTERN"]);
            }
            $xmlTableType = $xmlField->addChild("type", $arField["F_TYP"]);
            if ($arField["FK_LISTE"] > 0) {
                $xmlTableList = $xmlField->addChild("list", $arField["FK_LISTE"]);
            }
            $xmlTableIsMaster = $xmlField->addChild("isMaster", $arField["IS_MASTER"]);
            $xmlTableIsSpecial = $xmlField->addChild("isSpecial", $arField["IS_MASTER"]);
            $xmlTableIsSerachable = $xmlField->addChild("isSearchable", $arField["B_SEARCH"]);
            $xmlTableIsEnabled = $xmlField->addChild("isEnabled", $arField["B_ENABLED"]);
            $xmlTableIsRequired = $xmlField->addChild("isRequired", $arField["B_NEEDED"]);
        }
        return $xmlTableFields;
    }
}
