<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 14.07.15
 * Time: 11:28
 */

class Api_DataTable {

    private     $db;

    private     $fields;
    private     $fieldsCallback;
    private     $tablesFrom;
    private     $tablesJoin;
    private     $whereConditions;
    private     $havingConditions;
    
    function __construct(ebiz_db $db, $table, $tableIdent = NULL) {
        $this->db = $db;
        // Initialize variables
        $this->fields = true;
        $this->fieldsCallback = array();
        $this->tablesFrom = array();
        $this->tablesJoin = array();
        $this->whereConditions = array();
        $this->havingConditions = array();
        // Add initial table
        $this->addTable($table, $tableIdent);
    }

    /**
     * Check if the field matches the given plain string field name/alias
     * @param array     $arField
     * @param string    $fieldNameOrAlias
     * @return bool
     */
    protected function compareFieldWithNameOrAlias(&$arField, &$fieldNameOrAlias) {
        if ( (($arField["alias"] !== NULL) && ($arField["alias"] == $fieldNameOrAlias)) ||
            (($arField["name"] !== NULL) && ($arField["name"] == $fieldNameOrAlias)) ||
            (($arField["table"] !== NULL) && ($arField["name"] !== NULL) && ($arField["table"].".".$arField["name"] == $fieldNameOrAlias)) ) {
            return true;
        }
        return false;
    }

    /**
     * Create a query based on the current DataTable
     * @return Api_DataTableQuery
     */
    public function createQuery() {
        return new Api_DataTableQuery($this->db, $this);
    }

    public function addField($tableIdent, $fieldName = NULL, $fieldExpression = NULL, $fieldAlias = NULL, $fieldSortable = false, $fieldIsSelectKey = false, $fieldPrepend = false) {
        if (!is_array($this->fields)) {
            $this->fields = array();
        }
        $arField = array(
            "table"         => $tableIdent,
            "name"          => $fieldName,
            "expression"    => $fieldExpression,
            "alias"         => $fieldAlias,
            "resultKey"     => ($fieldAlias !== NULL ? $fieldAlias : $fieldName),
            "sortable"      => $fieldSortable,
            "selectKey"     => $fieldIsSelectKey
        );
        if ($fieldPrepend) {
            array_unshift($this->fields, $arField);
        } else {
            $this->fields[] = $arField;
        }
    }

    public function addFieldSpecial($fieldName = NULL, $fieldDependencies = array(), $fieldSortTarget = NULL, $columnTemplate = NULL, $columnCallback = NULL) {
        if (!is_array($this->fields)) {
            $this->fields = array();
        }
        $this->fields[] = array(
            "table"         => NULL,
            "name"          => $fieldName,
            "expression"    => NULL,
            "alias"         => NULL,
            "resultKey"     => $fieldName,
            "sortable"      => ($fieldSortTarget !== NULL ? true : false),
            "sortTarget"    => $fieldSortTarget,
            "dependencies"  => $fieldDependencies,
            "colTemplate"   => $columnTemplate
        );
        if ($columnCallback !== NULL) {
            $this->fieldsCallback[$fieldName] = $columnCallback;
        }
    }
    
    public function addFieldsFromDb($tableIdent) {
        $tableName = false;
        if (array_key_exists($tableIdent, $this->tablesFrom)) {
            $tableName = $this->tablesFrom[$tableIdent];
        } else if (array_key_exists($tableIdent, $this->tablesJoin)) {
            $tableName = $this->tablesJoin[$tableIdent]["table"];
        }
        if ($tableName !== false) {
            $arFields = $this->db->fetch_table("SHOW COLUMNS FROM `".mysql_real_escape_string($tableName)."`");
            foreach ($arFields as $fieldIndex => $fieldData) {
                $this->addField($tableIdent, $fieldData["Field"]);
            }
        }
    }
    
    public function addTable($table, $tableIdent = NULL) {
        if ($tableIdent === NULL) {
            $tableIdent = $table;
        }
        $this->tablesFrom[$tableIdent] = $table;
    }
    
    public function addTableJoin($table, $tableIdent = NULL, $joinType = "JOIN", $joinConditions = NULL, $arTableJoinsRequired = array(), $databaseName = NULL) {
        if ($tableIdent === NULL) {
            $tableIdent = $table;
        }
        $this->tablesJoin[$tableIdent] = array(
            "type"          => $joinType,
            "database"      => $databaseName,
            "table"         => $table,
            "conditions"    => $joinConditions,
            "requires"      => $arTableJoinsRequired
        );
    }
    
    public function addTableJoinString($baseTable, $baseTableIdent, $stringTable, $stringTableIdent = NULL, $joinType = "LEFT JOIN", $langval = NULL) {
        if ($langval === NULL) {
            $langval = $GLOBALS["langval"];
        }
        $stringTableSuffix = str_replace("string", "", $stringTable);
        if ($baseTableIdent === NULL) {
            $baseTableIdent = ltrim($stringTableSuffix, "_");
        }
        $identTableBase = "`".mysql_real_escape_string($baseTableIdent)."`";
        $identPrimaryBase = "`ID_".mysql_real_escape_string(strtoupper($baseTable))."`";
        $identTableString = "`".mysql_real_escape_string($stringTableIdent)."`";
        $joinCondition = $identTableString.".`FK`=".$identTableBase.".".$identPrimaryBase.
            " AND ".$identTableString.".S_TABLE='".mysql_real_escape_string($baseTable)."'".
        	" AND ".$identTableString.".BF_LANG=if(".$identTableBase.".BF_LANG".mysql_real_escape_string(strtoupper($stringTableSuffix))." & ".$langval.", ".$langval.", 1 << floor(log(".$identTableBase.".`BF_LANG".mysql_real_escape_string(strtoupper($stringTableSuffix))."`+0.5)/log(2)))";
        $this->addTableJoin($stringTable, $stringTableIdent, $joinType, $joinCondition, array($baseTableIdent));
    }

    public function addWhereCondition($conditionIdent, $conditionTemplate, $arTableJoinsRequired = array(), $whereMultiLink = null) {
        $conditionParameters = 0;
        if (!is_array($conditionTemplate)) {
            while (strpos($conditionTemplate, "$".($conditionParameters+1)."$") !== false) {
                $conditionParameters++;
            }
        }
        $this->whereConditions[] = array(
            "ident"         => $conditionIdent,
            "parameters"    => $conditionParameters,
            "template"      => $conditionTemplate,
            "requires"      => $arTableJoinsRequired,
            "multiLink"     => $whereMultiLink
        );
    }

    public function addHavingCondition($conditionIdent, $conditionTemplate, $arTableJoinsRequired = array()) {
        $conditionParameters = 0;
        if (!is_array($conditionTemplate)) {
            while (strpos($conditionTemplate, "$".($conditionParameters+1)."$") !== false) {
                $conditionParameters++;
            }
        }
        $this->havingConditions[] = array(
            "ident"         => $conditionIdent,
            "parameters"    => $conditionParameters,
            "template"      => $conditionTemplate,
            "requires"      => $arTableJoinsRequired
        );
    }
    
    public function getHash() {
        return sha1(serialize($this));
    }

    public function getField($fieldNameOrAlias) {
        foreach ($this->fields as $fieldIndex => $arField) {
            // Check if field selected for current query
            if ($this->compareFieldWithNameOrAlias($arField, $fieldNameOrAlias)) {
                $arResult = $arField;
                if (($arField["name"] !== NULL) && array_key_exists($arField["name"], $this->fieldsCallback)) {
                    // Callbacks are stored in a seperate variable in order to enable serializing
                    $arResult["colCallback"] = $this->fieldsCallback[ $arField["name"] ];
                }
                return $arResult;
            }
        }
        return false;
    }

    public function getFields() {
        return $this->fields;
    }

    public function getFieldsCallback() {
        return $this->fieldsCallback;
    }

    public function getTable($tableIdent = null) {
        if ($tableIdent === null) {
            $tableIdent = $this->getTableIdent();
        }
        if (array_key_exists($tableIdent, $this->tablesFrom)) {
            return $this->tablesFrom[$tableIdent];
        }
        return false;
    }

    public function getTableIdent($tableIndex = 0) {
        if ($tableIndex >= count($this->tablesFrom)) {
            return false;
        }
        $arTableIdents = array_keys($this->tablesFrom);
        return $arTableIdents[$tableIndex];
    }

    public function getTables() {
        return $this->tablesFrom;
    }
    
    public function getJoin($tableIdent) {
        return (array_key_exists($tableIdent, $this->tablesJoin) ? $this->tablesJoin[$tableIdent] : false);
    }
    
    public function getJoins() {
        return $this->tablesJoin;
    }
    
    public function getWhere($whereIdent = NULL) {
        if ($whereIdent === NULL) {
            return $this->whereConditions;
        } else {
            foreach ($this->whereConditions as $whereIndex => $arWhere) {
                if ($arWhere["ident"] == $whereIdent) {
                    return $arWhere;
                }
            }
            return false;
        }
    }

    public function getHaving($havingIdent = NULL) {
        if ($havingIdent === NULL) {
            return $this->havingConditions;
        } else {
            foreach ($this->havingConditions as $havingIndex => $arHaving) {
                if ($arHaving["ident"] == $havingIdent) {
                    return $arHaving;
                }
            }
            return false;
        }
        return $this->havingConditions;
    }

    public function getSelectKeys() {
        $arResult = array();
        foreach ($this->fields as $fieldIndex => $arField) {
            if ($arField["selectKey"] && ($arField["resultKey"] !== NULL)) {
                $arResult[] = $arField["resultKey"];
            }
        }
        return $arResult;
    }

    public function setFieldSortable($tableIdent, $fieldName, $isSortable = true) {
        foreach ($this->fields as $fieldIndex => $arField) {
            if (($arField["table"] == $tableIdent) && ($arField["name"] == $fieldName)) {
                $this->fields[$fieldIndex]["sortable"] = $isSortable;
                return true;
            }
        }
        return false;
    }

    public function setFieldSelectKey($tableIdent, $fieldName, $isSelectKey = true) {
        foreach ($this->fields as $fieldIndex => $arField) {
            if (($arField["table"] == $tableIdent) && ($arField["name"] == $fieldName)) {
                $this->fields[$fieldIndex]["selectKey"] = $isSelectKey;
                return true;
            }
        }
        return false;
    }

    public function hasFieldsDefined() {
        return is_array($this->fields);
    }

    public function isFieldEqual($fieldNameOrAliasA, $fieldNameOrAliasB) {
        if (is_array($this->fields)) {
            foreach ($this->fields as $fieldIndex => $arField) {
                // Check if field selected for current query
                if ($this->compareFieldWithNameOrAlias($arField, $fieldNameOrAliasA)) {
                    // Field name/alias A matches, if B matches too then both are equal.
                    return $this->compareFieldWithNameOrAlias($arField, $fieldNameOrAliasB);
                } else if ($this->compareFieldWithNameOrAlias($arField, $fieldNameOrAliasB)) {
                    // Field name/alias A does not match, but B does. So they resolve to different fields.
                    return false;
                }
            }
        }
        // Could not match A or B to any known field, do plain string compare
        return ($fieldNameOrAliasA == $fieldNameOrAliasB);
    }
    
    public function __sleep() {
        return array('fields', 'tablesFrom', 'tablesJoin', 'whereConditions', 'havingConditions');
    }
   
    public function __wakeup() {
        $this->db = $GLOBALS["db"];
        $this->fieldsCallback = array();
    }

}