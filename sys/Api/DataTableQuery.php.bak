<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 14.07.15
 * Time: 11:28
 */

class Api_DataTableQuery {

    protected     $db;
    protected     $dataTable;

    protected     $configFields;
    protected     $configJoins;
    protected     $configWhere;
    protected     $configGroup;
    protected     $configHaving;
    protected     $configSort;
    protected     $configLimitOffset;
    protected     $configLimitCount;

    protected     $configCalcFoundRows;

	protected     $queryCompiled;
	protected     $queryJoins;
    
    function __construct(ebiz_db $db, Api_DataTable $table) {
        $this->db = $db;
        $this->dataTable = $table;
        // Initialize variables
        $this->clear();
    }

    public static function createCountQuery(Api_DataTableQuery $query, $countField = null) {
        $dataTableQuery = $query->getDataTable()->createQuery();
        foreach ($query->getConfigWhere() as $whereIndex => $arWhere) {
            $dataTableQuery->addWhereCondition($arWhere["ident"], $arWhere["value"]);
        }
        /*
        foreach ($query->getConfigGroup() as $groupIndex => $arGroup) {
            $dataTableQuery->addGroupField($arGroup["field"] === null ? $arGroup["name"] : $arGroup["field"]);
        }
        */
        foreach ($query->getConfigHaving() as $havingIndex => $arHaving) {
            $dataTableQuery->addHavingCondition($arHaving["ident"], $arHaving["value"]);
        }
        if ($countField !== null) {
            $arCountField = $query->getDataTable()->getField($countField);
            $countFieldSql = "`".mysql_real_escape_string($arCountField["table"])."`.`".mysql_real_escape_string($arCountField["name"])."`";
            $dataTableQuery->getDataTable()->addField($arCountField["table"], null, "COUNT(".$countFieldSql.")", "RESULT_COUNT_CUSTOM");
            $dataTableQuery->addField("RESULT_COUNT_CUSTOM");
        } else {
            if (empty($dataTableQuery->getQueryJoins())) {
                if ($dataTableQuery->getDataTable()->getField("RESULT_COUNT_FAST") !== false) {
                    $dataTableQuery->addField("RESULT_COUNT_FAST");
                } else {
                    $dataTableQuery->addField("RESULT_COUNT");
                }
            } else {
                $dataTableQuery->addField("RESULT_COUNT");
            }
        }
        return $dataTableQuery;
    }

    protected function addFieldDependencies(&$arField) {
        if (array_key_exists("dependencies", $arField)) {
            // Special field!
            foreach ($arField["dependencies"] as $fieldName) {
                if (!$this->addField($fieldName)) {
                    trigger_error("DataTableQuery - Failed to add field dependencies: " . $fieldName . " (field not found)", E_USER_WARNING);
                    return false;
                }
            }
        }
        if ($arField["table"] === NULL) {
            // No dependencies (e.g. sub query)
            return true;
        }
        // Check if table is known
        if (array_key_exists($arField["table"], $this->dataTable->getTables())) {
            // Field already available by default
            return true;
        } else if (array_key_exists($arField["table"], $this->dataTable->getJoins())) {
            // Add join to query
            $arJoin = $this->dataTable->getJoin($arField["table"]);
            if ($arJoin !== false) {
                return $this->addJoin($arField["table"], $arJoin["table"], $arJoin["type"], $arJoin["conditions"], $arJoin["requires"], $arJoin["database"]);
            }
        }
        trigger_error("DataTableQuery - Failed to add table dependency for field: " . $fieldName . " (table alias '".$arField["table"]."' not found)", E_USER_WARNING);
        return false;
    }
    
    protected function setDirty() {
        $this->queryCompiled = false;
    }
    
    public function clear() {
        /*
        $this->fields = array();
        $this->tables = array();
        $this->joins = array();
        $this->order = array();
        $this->where = array();
        $this->group = array();
        $this->having = array();
         */
        $this->configFields = array();
        $this->configJoins = array();
        $this->configWhere = array();
        $this->configGroup = array();
        $this->configHaving = array();
        $this->configSort = array();
        $this->configLimitOffset = NULL;
        $this->configLimitCount = NULL;
        $this->configCalcFoundRows = false;
	    $this->queryCompiled = false;
	    $this->queryJoins = array();
    }
    
    public function addField($fieldName, $fieldLabel = NULL, $errorIfMissing = true) {
        $arField = $this->dataTable->getField($fieldName);
        if (($arField !== false) && $this->addFieldDependencies($arField)) {
            if (($arField["name"] !== NULL) && ($arField["expression"] !== NULL)) {
                trigger_error("DataTableQuery - Failed to add field! (field has both a name and a sql expression)", E_USER_WARNING);
                return false;
            }
            if (!array_key_exists($fieldName, $this->configFields) || ($this->configFields[$fieldName] === NULL)) {
                // Only add if it does not already exist
                $this->configFields[$fieldName] = $fieldLabel;
                $this->setDirty();

            }
            return true;
        }
        if ($errorIfMissing) {
            trigger_error("DataTableQuery - Failed to add field: ".$fieldName." (field not found)", E_USER_WARNING);
        }
        return false;
    }
    
    public function addFieldIfKnown($fieldName, $fieldLabel = NULL) {
        return $this->addField($fieldName, $fieldLabel, false);
    }

    public function addFields($arFields, $errorIfMissing = true) {
        if (is_array($arFields)) {
            foreach ($arFields as $fieldName => $fieldLabel) {
                if (!$this->addField($fieldName, $fieldLabel, $errorIfMissing)) {
                    return false;
                }
            }
        } else if (is_string($arFields)) {
            if (preg_match("/^([a-z0-9]+)\.\*$/i", $arFields, $arMatch)) {
                $tableIdent = $arMatch[1];
                foreach ($this->dataTable->getFields() as $fieldIndex => $fieldData) {
                    if ($fieldData["table"] == $tableIdent) {
                        if (!$this->addField($fieldData["table"].".".$fieldData["name"], NULL, $errorIfMissing)) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }
    
    public function addFieldsIfKnown($arFields) {
        return $this->addFields($arFields, false);
    }

    public function addJoin($tableIdent, $joinTable, $joinType = NULL, $joinConditions = NULL, $joinTablesRequired = array(), $joinDatabase = NULL, $recursionLimit = 5) {
        if ($recursionLimit < 0) {
            trigger_error("DataTableQuery - Failed to resolve join dependencies! (recursion limit reached, circular condition?)", E_USER_WARNING);
            return false;
        }
        if (!array_key_exists($tableIdent, $this->configJoins)) {
            foreach ($joinTablesRequired as $requiredTableIdent) {
                if (($this->dataTable->getTableIdent() !== $requiredTableIdent) && !array_key_exists($requiredTableIdent, $this->configJoins)) {
                    $arJoin = $this->dataTable->getJoin($requiredTableIdent);
                    if (($arJoin === false) || !$this->addJoin($requiredTableIdent, $arJoin["table"], $arJoin["type"], $arJoin["conditions"], $arJoin["requires"], $arJoin["database"], $recursionLimit--)) {
                        trigger_error("DataTableQuery - Failed to add join required for table '".$joinTable."' / ".$requiredTableIdent.": ".$arJoin["table"], E_USER_WARNING);
                        continue;
                    } 
                }
            }
            // Add join to config
            $this->configJoins[$tableIdent] = array(
                "type"          => $joinType,
                "database"      => $joinDatabase,
                "table"         => $joinTable,
                "conditions"    => $joinConditions,
                "requires"      => $joinTablesRequired
            );
            $this->setDirty();
            return true;
        } else {
            // Join already present in query!
            return true;
        }
    }

    public function addWhereCondition($whereIdent, $whereValue = array(), $whereMultiLink = null) {
        $arWhere = $this->dataTable->getWhere($whereIdent);
        if ($arWhere !== false) {
            foreach ($arWhere["requires"] as $joinIndex => $joinTableIdent) {
                $arJoin = $this->dataTable->getJoin($joinTableIdent);
                if ($arJoin !== false) {
                    if (!$this->addJoin($joinTableIdent, $arJoin["table"], $arJoin["type"], $arJoin["conditions"], $arJoin["requires"], $arJoin["database"])) {
                        return false;
                    }
                }
            }
            $this->configWhere[] = array(
                "ident"     => $whereIdent,
                "value"     => (is_array($whereValue) ? $whereValue : array($whereValue)),
                "multiLink" => ($whereMultiLink === null ? $arWhere["multiLink"] : $whereMultiLink)
            );
            $this->setDirty();
            return true;
        }
        return false;
    }

    public function addWhereConditions($arWhere) {
        foreach ($arWhere as $whereIdent => $whereValue) {
            if (!$this->addWhereCondition($whereIdent, $whereValue)) {
                return false;
            }
        }
        return true;
    }

    public function addHavingCondition($havingIdent, $havingValue) {
        $arHaving = $this->dataTable->getHaving($havingIdent);
        if ($arHaving !== false) {
            foreach ($arHaving["requires"] as $joinIndex => $joinTableIdent) {
                $arJoin = $this->dataTable->getJoin($joinTableIdent);
                if ($arJoin !== false) {
                    if (!$this->addJoin($joinTableIdent, $arJoin["table"], $arJoin["type"], $arJoin["conditions"], $arJoin["requires"], $arJoin["database"])) {
                        return false;
                    }
                }
            }
            $this->configWhere[] = array(
                "ident"     => $havingIdent,
                "value"     => (is_array($havingValue) ? $havingValue : array($havingValue))
            );
            $this->setDirty();
            return true;
        }
        return false;
    }

    public function addHavingConditions($arHaving) {
        foreach ($arHaving as $havingIdent => $havingValue) {
            if (!$this->addHavingCondition($havingIdent, $havingValue)) {
                return false;
            }
        }
        return true;
    }

    public function addSortField($orderField, $orderDirection) {
        // Add fields (+joins)
        if (!$this->dataTable->hasFieldsDefined()) {
            // Fields not defined, simply add order fields to query without dependency checks
            $this->configSort[] = array(
                "field"     => NULL,
                "name"      => $orderField,
                "direction" => $orderDirection
            );
            $this->setDirty();
        } else {
            $arField = $this->dataTable->getField($orderField);
            if (($arField !== false) && $arField["sortable"] && $this->addFieldDependencies($arField)) {
                $fieldSql = "";
                if ($arField["name"] !== NULL) {
                    $fieldSql = "`".mysql_real_escape_string($arField["table"])."`.`".mysql_real_escape_string($arField["name"])."`";
                } else if ($arField["expression"] !== NULL) {
                    $fieldSql = $arField["expression"];
                } else {
                    trigger_error("DataTableQuery - Failed to add order field! (field has neither a name nor a sql expression)", E_USER_WARNING);
                    return false;
                }
                $this->configSort[] = array(
                    "field"     => ($arField["name"] !== NULL ? $arField["table"].".".$arField["name"] : null),
                    "alias"     => $arField["alias"],
                    "name"      => $fieldSql,
                    "direction" => $orderDirection
                );
                $this->setDirty();
            }
        }
        return true;

    }

    public function addSortFields($arSortFields) {
        foreach ($arSortFields as $orderField => $orderDirection) {
            if (!$this->addSortField($orderField, $orderDirection)) {
                return false;
            }
        }
        return true;
    }

    public function addGroupField($groupField) {
        // Add fields (+joins)
        if (!$this->dataTable->hasFieldsDefined()) {
            // Fields not defined, simply add order fields to query without dependency checks
            $this->configGroup[] = array(
                "field"     => NULL,
                "name"      => $groupField
            );
            $this->setDirty();
        } else {
            $arField = $this->dataTable->getField($groupField);
            if (($arField !== false) && $this->addFieldDependencies($arField)) {
                $fieldSql = "";
                if ($arField["name"] !== NULL) {
                    $fieldSql = "`".mysql_real_escape_string($arField["table"])."`.`".mysql_real_escape_string($arField["name"])."`";
                } else if ($arField["expression"] !== NULL) {
                    $fieldSql = $arField["expression"];
                } else {
                    trigger_error("DataTableQuery - Failed to add group field! (field has neither a name nor a sql expression)", E_USER_WARNING);
                    return false;
                }
                $this->configGroup[] = array(
                    "field"     => ($arField["name"] !== null ? $arField["table"].".".$arField["name"] : $groupField),
                    "name"      => $fieldSql
                );
                $this->setDirty();
            }
        }
        return true;

    }

    public function addGroupFields($arGroupFields) {
        foreach ($arGroupFields as $groupIndex => $groupName) {
            if (!$this->addGroupField($groupName)) {
                return false;
            }
        }
        return true;
    }

    public function setLimit($limit = NULL, $offset = NULL) {
        $this->configLimitCount = $limit;
        $this->configLimitOffset = $offset;
        return true;
    }
    
    public function compileQuery($force = false) {
        if (!$force && ($this->queryCompiled !== false)) {
            // Query is already compiled, nothing to do.
            return true;
        }
        // Add fields
        $arQueryFields = $this->compileQueryFields();
        // Add tables
        $arQueryTables = $this->compileQueryTables();
        // Add joins
        $arQueryJoins = $this->compileQueryJoins();
	    $this->queryJoins = array_keys($arQueryJoins);
        // Add where conditions
        $arQueryWhere = $this->compileQueryWhere();
        // Add group by
        $arQueryGroup = $this->compileQueryGroup();
        // Add having conditions
        $arQueryHaving = $this->compileQueryHaving();
        // Add having conditions
        $arQueryOrder = $this->compileQueryOrder();
        // Build select query
        $this->queryCompiled =
            "SELECT ".($this->configCalcFoundRows ? "SQL_CALC_FOUND_ROWS " : "").implode(", ", $arQueryFields)."\n".
            "FROM ".implode(", ", $arQueryTables)."\n".
            (!empty($arQueryJoins) ? implode("\n", $arQueryJoins)."\n" : "").
            (!empty($arQueryWhere) ? "WHERE ".implode(" AND ", $arQueryWhere)."\n" : "").
            (!empty($arQueryGroup) ? "GROUP BY ".implode(", ", $arQueryGroup)."\n" : "").
            (!empty($arQueryHaving) ? "HAVING ".implode(" AND ", $arQueryHaving)."\n" : "").
            (!empty($arQueryOrder) ? "ORDER BY ".implode(", ", $arQueryOrder)."\n" : "").
            ($this->configLimitCount !== NULL ? "LIMIT ".(int)$this->configLimitCount."\n" : "").
            ($this->configLimitOffset !== NULL ? "OFFSET ".(int)$this->configLimitOffset."\n" : "");
        return true;
    }
    
    protected function compileQueryFields() {
        $arQueryFields = array();
        if (!empty($this->configFields)) {
            foreach ($this->configFields as $fieldName => $fieldLabel) {
                $arField = $this->dataTable->getField($fieldName);
                if (!$this->addFieldDependencies($arField, $arQueryFields)) {
                    trigger_error("DataTableQuery - Failed to add field: " . $fieldName . " (failed to resolve dependencies)", E_USER_WARNING);
                    return false;
                }
                $fieldName = "";
                $fieldSql = "";
                if (array_key_exists("dependencies", $arField)) {
                    // Special field! No own sql code.
                } else {
                    if ($arField["name"] !== NULL) {
                        $fieldName = $arField["table"].".".$arField["name"];
                        $fieldSql = "`" . mysql_real_escape_string($arField["table"]) . "`.`" . mysql_real_escape_string($arField["name"]) . "`";
                    } else if ($arField["expression"] !== NULL) {
                        $fieldName = $arField["alias"];
                        $fieldSql = $arField["expression"];
                    }
                    $arQueryFields[$fieldName] = $fieldSql . ($arField["alias"] !== NULL ? " AS `" . mysql_real_escape_string($arField["alias"]) . "`" : "");
                }
            }
        } else {
            // No fields selected, add wildcard field selector.
            $arQueryFields[] = "*";
        }
        return $arQueryFields;
    }
    
    protected function compileQueryTables() {
        $arQueryTables = array();
        foreach ($this->dataTable->getTables() as $tableIdent => $tableName) {
            $arQueryTables[] = "`".mysql_real_escape_string($tableName)."`".
                ($tableIdent != $tableName ? " `".mysql_real_escape_string($tableIdent)."`" : "");
        }
        return $arQueryTables;
    }
    
    protected function compileQueryJoins() {
        $arQueryJoins = array();
        foreach ($this->configJoins as $joinTableIdent => $arJoinConfig) {
            // Add join to query
            $arQueryJoins[$joinTableIdent] = $arJoinConfig["type"] . " ".
                ($arJoinConfig["database"] !== NULL ? "`".mysql_real_escape_string($arJoinConfig["database"])."`." : "") .
                "`" . mysql_real_escape_string($arJoinConfig["table"]) . "` " .
                "`" . mysql_real_escape_string($joinTableIdent) . "`" .
                ($arJoinConfig["conditions"] !== NULL ? " ON " . $arJoinConfig["conditions"] : "");
        }
        return $arQueryJoins;
    }
    
    protected function compileQueryWhere() {
        $arQueryWhere = array();
        foreach ($this->configWhere as $whereIndex => $arWhereConfig) {
            $arWhere = $this->dataTable->getWhere($arWhereConfig["ident"]);
            if (($arWhere["ident"] === NULL) && ($arWhere["parameters"] == 0) && !is_array($arWhere["template"])) {
                $arQueryWhere[] = $arWhere["template"];
            } else {
                if ($arWhereConfig["multiLink"] === null) {
                    $whereSql = $this->compileConditionTemplate($arWhere["template"], $arWhereConfig["value"], $arWhere["parameters"], $arWhere["ident"]);
                    if ($whereSql === false) {
                        return false;
                    }
                    $arQueryWhere[] = $whereSql;
                } else {
                    $arWhereParts = array();
                    foreach ($arWhereConfig["value"] as $whereValueIndex => $whereValue) {
                        $whereSql = $this->compileConditionTemplate($arWhere["template"], $whereValue, $arWhere["parameters"], $arWhere["ident"]);
                        if ($whereSql === false) {
                            return false;
                        }
                        $arWhereParts[] = $whereSql;
                    }
                    $arQueryWhere[] = "(".implode(" ".$arWhereConfig["multiLink"]." ", $arWhereParts).")";
                }
            }
        }
        return $arQueryWhere;
    }
    
    protected function compileQueryGroup() {
        $arQueryGroup = array();
        foreach ($this->configGroup as $groupIndex => $arGroupConfig) {
            $arQueryGroup[] = $arGroupConfig["name"];
        }
        return $arQueryGroup;
    }
    
    protected function compileQueryHaving() {
        $arQueryHaving = array();
        foreach ($this->configHaving as $havingIndex => $arHavingConfig) {
            $arHaving = $this->dataTable->getHaving($arHavingConfig["ident"]);
            if (($arHaving["ident"] === NULL) && ($arHaving["parameters"] == 0) && !is_array($arHaving["template"])) {
                $arQueryHaving[] = $arHaving["template"];
            } else {
                $havingSql = $this->compileConditionTemplate($arHaving["template"], $arHavingConfig["value"], $arHaving["parameters"], $arHaving["ident"]);
                if ($havingSql === false) {
                    return false;
                }
                $arQueryHaving[] = $havingSql;
            }
        }
        return $arQueryHaving;
    }
    
    protected function compileQueryOrder() {
        $arQueryOrder = array();
        foreach ($this->configSort as $sortIndex => $arSortConfig) {
            $arQueryOrder[] = $arSortConfig["name"]." ".$arSortConfig["direction"];
        }
        return $arQueryOrder;
    }

    protected function compileConditionTemplate($conditionTemplate, $conditionValue, $conditionParamCount, $conditionIdent) {
        if (is_array($conditionTemplate) && (count($conditionValue) == 1) && array_key_exists($conditionValue[0], $conditionTemplate)) {
            return $conditionTemplate[ $conditionValue[0] ];
        } else if (is_array($conditionTemplate)) {
            trigger_error("DataTableQuery - Invalid value for condition '".$conditionIdent."'! (template is an array, but no (valid) key was specified as value)", E_USER_WARNING);
            return false;
        }
        if (($conditionParamCount === 0) && (count($conditionValue) == 1) && ($conditionValue[0] === true)) {
            // Allow to define conditions without parameters as array("condition" => true)
            $conditionValue = array();
        }
        if (count($conditionValue) != $conditionParamCount) {
            trigger_error("DataTableQuery - Failed to compile condition '".$conditionIdent."'! (wrong number of parameters, expected ".$conditionParamCount." got ".count($conditionValue).")", E_USER_WARNING);
            return false;
        }
        $result = $conditionTemplate;
        foreach ($conditionValue as $paramIndex => $paramValue) {
            $escapedValue = mysql_real_escape_string($paramValue);
            if (((substr($paramValue, 0, 1) == '"') && (substr($paramValue, -1, 1) == '"'))
                || ((substr($paramValue, 0, 1) == "'") && (substr($paramValue, -1, 1) == "'"))) {
                $escapedValue = $paramValue;
            }
            $result = str_replace("$".($paramIndex+1)."$", $escapedValue, $result);
        }
        return $result;
    }

    public function fetchCount() {
        if (!$this->configCalcFoundRows) {
            $dtQueryCount = Api_DataTableQuery::createCountQuery($this);
            return $dtQueryCount->fetchAtom();
        }
        return $this->db->fetch_atom("SELECT FOUND_ROWS();");
    }

    public function fetchAtom() {
        if (!$this->compileQuery()) {
            return false;
        }
        return $this->db->fetch_atom($this->queryCompiled);
    }

    public function fetchCol() {
        if (!$this->compileQuery()) {
            return false;
        }
        return $this->db->fetch_col($this->queryCompiled);
    }

    public function fetchNar() {
        if (!$this->compileQuery()) {
            return false;
        }
        return $this->db->fetch_nar($this->queryCompiled);
    }

    public function fetchOne() {
        if (!$this->compileQuery()) {
            return false;
        }
        return $this->db->fetch1($this->queryCompiled);
    }

    public function fetchTable($resultType = MYSQL_ASSOC) {
        if (!$this->compileQuery()) {
            return false;
        }
        return $this->db->fetch_table($this->queryCompiled, NULL, false, false, $resultType);
    }

    /*
    public function fetchSortKeys() {
        $querySortKeys = $this->dataTable->createQuery();
        $querySortKeys->addFields($this->dataTable->getSelectKeys());
        foreach ($this->configWhere as $indexWhere => $arWhere) {
            $querySortKeys->addWhereCondition($arWhere['ident'], $arWhere['value']);
        }
        foreach ($this->configGroup as $indexGroup => $arGroup) {
            $querySortKeys->addGroupField($arGroup['field'] === NULL ? $arGroup['name'] : $arGroup['field']);
        }
        foreach ($this->configHaving as $indexHaving => $arHaving) {
            $querySortKeys->addHavingField($arHaving['ident'], $arHaving['value']);
        }
        foreach ($this->configSort as $indexSort => $arSort) {
            $querySortKeys->addSortField(($arSort['field'] === NULL ? $arSort['name'] : $arGroup['field']), $arSort['direction']);
        }
        return $querySortKeys->fetchTable();
    }
    */
    
    public function getDataTable() {
        return $this->dataTable;
    }

    public function getConfigFields() {
        return $this->configFields;
    }

    public function getConfigJoins() {
        return $this->configJoins;
    }

    public function getConfigWhere() {
        return $this->configWhere;
    }

    public function getConfigGroup() {
        return $this->configGroup;
    }

    public function getConfigHaving() {
        return $this->configHaving;
    }

    public function getConfigSort() {
        return $this->configSort;
    }

    public function getConfigSortDirection($fieldName) {
        foreach ($this->configSort as $sortIndex => $arSortConfig) {
            if ($this->dataTable->isFieldEqual($arSortConfig["field"], $fieldName)) {
                return $arSortConfig["direction"];
            }
        }
        return false;
    }

    public function getConfigLimitCount() {
        return $this->configLimitCount;
    }

    public function getConfigLimitOffset() {
        return $this->configLimitOffset;
    }

    public function setConfigCalcFoundRows($calcFoundRows = true) {
        $this->configCalcFoundRows = $calcFoundRows;
        $this->setDirty();
    }

    public function getConfigCalcFoundRows() {
        return $this->configCalcFoundRows;
    }

    public function getConfigQuery() {
        $arWhere = array();
        $arGroup = array();
        $arHaving = array();
        $arSort = array();
        foreach ($this->configWhere as $whereIndex => $arWhereConfig) {
            $arWhere[ $arWhereConfig["ident"] ] = $arWhereConfig["value"];
        }
        foreach ($this->configGroup as $groupIndex => $arGroupConfig) {
            $arGroup[] = $arGroupConfig["field"];
        }
        foreach ($this->configHaving as $havingIndex => $arHavingConfig) {
            $arHaving[ $arHavingConfig["ident"] ] = $arHavingConfig["value"];
        }
        foreach ($this->configSort as $sortIndex => $arSortConfig) {
            $arSort[ $arSortConfig["name"] ] = $arSortConfig["direction"];
        }
        return array(
            "fields"    => $this->configFields,
            "where"     => $arWhere,
            "group"     => $arGroup,
            "having"    => $arHaving,
            "sorting"   => $arSort,
            "limit"     => $this->configLimitCount,
            "offset"    => $this->configLimitOffset
        );
    }

    public function getQueryString() {
        if (!$this->compileQuery()) {
            return false;
        }
        return $this->queryCompiled;
    }

	/**
	 * Gets a list of all joined tables
	 * @return array
	 */
    public function getQueryJoins() {
	    if (!$this->compileQuery()) {
		    return false;
	    }
	    return $this->queryJoins;
    }
    
}