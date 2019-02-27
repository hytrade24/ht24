<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $GLOBALS["ab_path"]."admin/sys/tabledef.php";

class AdTable {

    private static $tableDef = null;
    private static $tableCache = array();
    private static $tableNames = array();

    /**
     * @return tabledef
     */
    public static function getTableDefObject() {
        if (self::$tableDef === null) {
            self::$tableDef = new tabledef();
        }
        return self::$tableDef;
    }

    /**
     * @param $idTable      Id of the table to get
     * @param $allowCached  Whether to allow reusing the table object
     * @return AdTable  An AdTable-Object of the given table
     */
    public static function getTableById($idTable, $allowCached = true) {
        if ($allowCached) {
            $adTable = false;
            if (array_key_exists($idTable, self::$tableCache)) {
                // Use cached table
                $adTable = self::$tableCache[$idTable];
            } else {
                // No cached table found, create object and write to cache
                $adTable = new AdTable($idTable);
                self::$tableCache[$idTable] = $adTable;
            }
            return $adTable;
        } else {
            // Neither read from, nor write to table cache 
            return new AdTable($idTable);
        }
    }

    /**
     * @param $tableName    Name of the table to get (mysql name)
     * @param $allowCached  Whether to allow reusing the table object
     * @return AdTable  An AdTable-Object of the given table
     */
    public static function getTableByName($tableName, $allowCached = true) {
        global $db;
        $idTable = false;
        if ($allowCached) {
            if (array_key_exists($tableName, self::$tableNames)) {
                // Use cached table id
                $idTable = self::$tableNames[$tableName];
            } else {
                // No cached table found, create object and write to cache
                $idTable = $db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_real_escape_string($tableName)."'");
                self::$tableNames[$tableName] = $idTable;
            }
        } else {
            // Neither read from, nor write to table cache 
            $idTable = $db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_real_escape_string($tableName)."'");
        }
        return self::getTableById($idTable, $allowCached);
    }

    /**
     * Get a list of all available article tables
     * @param bool $includeFields   Whether to include the fields or not
     * @return array    Array containing all available article tables
     */
    public static function getTableList($includeFields = false) {
        $tableDef = self::getTableDefObject();
        $tableDef->getTables(0, $includeFields);
        return $tableDef->tables;
    }

    private $tableId;
    private $tableName;
    private $arFields;

    public function __construct($idTable) {
        $this->tableId = $idTable;
        $tableDef = self::getTableDefObject();
        // Get table basics (name)
        $tableDef->getTableById($idTable, true);
        $this->tableName = $tableDef->table;
        // Get table fields
        $tableDef->getFields();
        $this->arFields = $tableDef->tables[$tableDef->table]['FIELDS_ORDERED'];
    }

    public function getName() {
        return $this->tableName;
    }

    public function getFields() {
        return $this->arFields;
    }

    /**
     * Gets a list of categories that use the article table
     * @param $language     Bitvalue of the language to be used for multilingual strings
     * @return array
     */
    public function getCategories($language = null) {
        global $db, $langval;
        if ($language === null) $language = $langval;
        $arCategories = $db->fetch_table("SELECT el.*, s.T1, s.V1, s.V2 FROM `kat` el
            LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=el.ID_KAT
                AND s.BF_LANG=if(el.BF_LANG_KAT & ".$language.", ".$language.", 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
            WHERE ROOT=1 AND B_VIS=1 AND KAT_TABLE='".$this->tableName."'
            ORDER BY el.ORDER_FIELD");
        return $arCategories;
    }

    public function getFieldByName($fieldName) {
        $arFieldsByName = array();
        foreach ($this->arFields as $arField) {
            if ($arField["F_NAME"] == $fieldName) {
                return $arField;
            }
        }
        return null;
    }

    public function getFieldsByName() {
        $arFieldsByName = array();
        foreach ($this->arFields as $arField) {
            $arFieldsByName[ $arField["F_NAME"] ] = $arField;
        }
        return $arFieldsByName;
    }

    public function getFieldStrings($idField, $langval) {
        global $db;
        $ar_strings = $db->fetch1($q="
			select s.V1, s.V2, s.T1
			FROM `field_def` t
			LEFT JOIN `string_field_def` s on s.S_TABLE='field_def' and s.FK=t.ID_FIELD_DEF
					and s.BF_LANG=if(t.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_DEF+0.5)/log(2)))
			WHERE
				t.ID_FIELD_DEF=".$idField);
        list($ar_strings["T1"], $ar_strings["T2"], $ar_strings["T3"]) = explode("§§§", $ar_strings["T1"]);
        $ar_strings_t1 = explode("||", $ar_strings["T1"]);
        $ar_strings_t2 = explode("||", $ar_strings["T2"]);
        $ar_strings_t3 = explode("||", $ar_strings["T3"]);
        unset($ar_strings["T1"]);
        $ar_strings["T1_DESC"] = $ar_strings_t1[0];
        $ar_strings["T1_HELP"] = $ar_strings_t1[1];
        unset($ar_strings["T2"]);
        $ar_strings["T2_DESC"] = $ar_strings_t2[0];
        $ar_strings["T2_HELP"] = $ar_strings_t2[1];
        unset($ar_strings["T3"]);
        $ar_strings["T3_DESC"] = $ar_strings_t3[0];
        $ar_strings["T3_HELP"] = $ar_strings_t3[1];
        return $ar_strings;
    }

    public function getFieldCategories($idField) {
        global $db;
        $arFields = $db->fetch_table("
            SELECT kf.*
            FROM `kat2field` kf
            JOIN `kat` k ON k.ID_KAT=kf.FK_KAT
            WHERE k.KAT_TABLE='".mysql_real_escape_string($this->tableName)."' AND kf.FK_FIELD=".(int)$idField);
        return $arFields;
    }

} 
