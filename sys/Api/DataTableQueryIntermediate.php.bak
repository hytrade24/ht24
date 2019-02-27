<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 14.07.15
 * Time: 11:28
 */

class Api_DataTableQueryIntermediate extends Api_DataTableQuery {
    
    protected $querySource;
    
    function __construct(ebiz_db $db, Api_DataTableQuery $query, $arIntermediateFields, Api_DataTable $intermediateTable = null) {
        if ($intermediateTable === null) {
            $intermediateTable = $query->getDataTable(); 
        }
        parent::__construct($db, $intermediateTable);
        // Create source query
        $querySource = $query->getDataTable()->createQuery();
        foreach ($query->getConfigWhere() as $whereIndex => $arWhere) {
            $querySource->addWhereCondition($arWhere["ident"], $arWhere["value"]);
        }
        foreach ($query->getConfigGroup() as $groupIndex => $arGroup) {
            $querySource->addGroupField($arGroup["field"] === null ? $arGroup["name"] : $arGroup["field"]);
        }
        foreach ($query->getConfigHaving() as $havingIndex => $arHaving) {
            $querySource->addHavingCondition($arHaving["ident"], $arHaving["value"]);
        }
        foreach ($query->getConfigSort() as $sortIndex => $arSortConfig) {
            if ($arSortConfig["field"] !== null) {
                $querySource->addSortField($arSortConfig["field"], $arSortConfig["direction"]);
            } else {
                $querySource->addSortField($arSortConfig["name"], $arSortConfig["direction"]);
            }
        }
        if (is_array($arIntermediateFields)) {
            $querySource->addFields($arIntermediateFields);
        } else {
            $querySource->addField($arIntermediateFields);
        }
        $querySource->setLimit( $query->getConfigLimitCount(), $query->getConfigLimitOffset() );
        $this->querySource = $querySource;
        // Add fields and sorting to new query
        $this->addFields( $query->getConfigFields() );
        foreach ($query->getConfigSort() as $sortIndex => $arSortConfig) {
	        if ($arSortConfig["field"] !== null) {
		        $this->addSortField($arSortConfig["field"], $arSortConfig["direction"]);
	        } else if ($arSortConfig["alias"] !== null) {
		        $this->addSortField($arSortConfig["alias"], $arSortConfig["direction"]);
	        } else {
                $this->addSortField($arSortConfig["name"], $arSortConfig["direction"]);
            }
        }
    }
    
    protected function compileQueryIntermediateJoin() {
        $arTables = array();
        $arConditions = array();
        $arJoins = $this->dataTable->getJoins();
        foreach ($this->querySource->getConfigFields() as $fieldName => $fieldLabel) {
            $arField = $this->dataTable->getField($fieldName);
            $fieldName = "";
            $fieldNameIntermediate = ($arField["alias"] !== NULL ? $arField["alias"] : $arField["name"]);
            $fieldSql = "";
            $fieldSqlIntermediate = "";
            if ($arField["name"] !== NULL) {
                $fieldName = $arField["table"].".".$arField["name"];
                $fieldSql = "`" . mysql_real_escape_string($arField["table"]) . "`.`" . mysql_real_escape_string($arField["name"]) . "`";
            } else if ($arField["expression"] !== NULL) {
                $fieldName = $arField["alias"];
                $fieldSql = $arField["expression"];
            }
            $fieldSqlIntermediate = "`intermediate_temp`.`" . mysql_real_escape_string($fieldNameIntermediate) . "`";
            
            if (array_key_exists($arField["table"], $arJoins)) {
                // Field is a join!
                $arJoin = $arJoins[ $arField["table"] ];
                $arTables[] = "`".mysql_real_escape_string($arJoin["table"])."`".($arField["table"] != $arJoin["table"] ? " `".mysql_real_escape_string($arField["table"])."`" : "");
                // Prevent joining it again
                if (array_key_exists($arField["table"], $this->configJoins)) {
                    unset($this->configJoins[ $arField["table"] ]);
                }
            } else {
                $fieldTableName = $this->dataTable->getTable( $arField["table"] );
                $arTables[] = "`".mysql_real_escape_string($fieldTableName)."`".($arField["table"] != $fieldTableName ? " `".mysql_real_escape_string($arField["table"])."`" : "");
            }
            $arConditions[] = $fieldSql."=".$fieldSqlIntermediate;
        }
        return "INNER JOIN ".implode(", ", $arTables)." ON ".implode(" AND ", $arConditions)."\n";
    }
    
    public function compileQuery($force = false) {
        if (!$force && ($this->queryCompiled !== false)) {
            // Query is already compiled, nothing to do.
            return true;
        }
        // Add fields
        $arQueryFields = $this->compileQueryFields();
        // Build intermediate join condition
        $strIntermediateJoin = $this->compileQueryIntermediateJoin();
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
        $sourceIndented = "    ".str_replace("\n", "\n    ", $this->querySource->getQueryString());
        $this->queryCompiled =
            "SELECT ".($this->configCalcFoundRows ? "SQL_CALC_FOUND_ROWS " : "").implode(", ", $arQueryFields)."\n".
            "FROM (\n".$sourceIndented."\n) AS `intermediate_temp`\n".
            $strIntermediateJoin.
            (!empty($arQueryJoins) ? implode("\n", $arQueryJoins)."\n" : "").
            (!empty($arQueryWhere) ? "WHERE ".implode(" AND ", $arQueryWhere)."\n" : "").
            (!empty($arQueryGroup) ? "GROUP BY ".implode(", ", $arQueryGroup)."\n" : "").
            (!empty($arQueryHaving) ? "HAVING ".implode(" AND ", $arQueryHaving)."\n" : "").
            (!empty($arQueryOrder) ? "ORDER BY ".implode(", ", $arQueryOrder)."\n" : "").
            ($this->configLimitCount !== NULL ? "LIMIT ".(int)$this->configLimitCount."\n" : "").
            ($this->configLimitOffset !== NULL ? "OFFSET ".(int)$this->configLimitOffset."\n" : "");
        return true;
    }
    
    public function fetchCount() {
        if (!$this->configCalcFoundRows) {
            $countField = null;
            $arSourceFields = array_keys($this->querySource->getConfigFields());
            if (count($arSourceFields) === 1) {
                #$arSourceField = $this->querySource->getDataTable()->getField($arSourceFields[0]);
                #"`".mysql_real_escape_string($arSourceField["table"])."`.`".mysql_real_escape_string($arSourceField["name"])."`";
                $countField = $arSourceFields[0];
            }
            $dtQueryCount = Api_DataTableQuery::createCountQuery($this->querySource, $countField);
            #die(var_dump($dtQueryCount->getQueryString()));
            return $dtQueryCount->fetchAtom();
        }
        return $this->db->fetch_atom("SELECT FOUND_ROWS();");
    }
    
}