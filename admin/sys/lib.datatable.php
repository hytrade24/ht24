<?php
/**
 * Created by Forsaken
 * Date: 21.07.15
 * Time: 23:11
 */

class AdminDataTable {
    /**
     * @param Api_DataTableQuery    $dtQuery
     * @param string                $title
     * @param Template|string|bool  $actionTemplate
     * @param Template|string|bool  $filterTemplate
     * @param string|null           $ajaxUrl
     * @param callable              $callbackCol
     * @return string
     */
    public static function renderDataTable(Api_DataTableQuery $dtQuery, $title, $actionTemplate = false, $filterTemplate = false, $ajaxUrl = NULL, &$resultCount = 0, $callbackCol = NULL) {
        $dtTableName = $dtQuery->getDataTable()->getTable();
        $dtFieldsLabeled = $dtQuery->getConfigFields();
        $arFieldsSelectKey = $dtQuery->getDataTable()->getSelectKeys();
        $selectable = false;
        // Query result
        $arResult = $dtQuery->fetchTable();
        $resultCount =  $dtQuery->fetchCount();
        // Create field cache for body
        $arFieldsCached = array();
        // Create header cols
        $arHeader = array();
        $arDataSample = (is_array($arResult) && (count($arResult) > 0) ? $arResult[0] : false);
        $fieldIndex = 0;
        foreach ($dtFieldsLabeled as $fieldName => $fieldLabel) {
            if ($fieldLabel === NULL) {
                continue;
            }
            // Add field to cache
            $arField = $dtQuery->getDataTable()->getField($fieldName);
            $arFieldsCached[] = $arField;
            // Create header template
            $tplHeader = new Template("tpl/".$GLOBALS['s_lang']."/data_table.header.htm");
            $label = Translation::readTranslation("admin", "dataTable.".$dtTableName.".field.".$fieldName, null, array(), $fieldLabel);
            $tplHeader->addvar("index", $fieldIndex);
            $tplHeader->addvar("field", $fieldName);
            $tplHeader->addvar("label", $label);
            $tplHeader->addvar("sortable", $arField["sortable"]);
            if ($arField["sortable"]) {
                $fieldSortName = (array_key_exists("sortTarget", $arField) ? $arField["sortTarget"] : $fieldName);
                $fieldSortDir = $dtQuery->getConfigSortDirection($fieldSortName);
                $tplHeader->addvar("sortTarget", $fieldSortName);
                if ($fieldSortDir !== false) {
                    $tplHeader->addvar("sortDir", $fieldSortDir);
                    $tplHeader->addvar("sortDir_".$fieldSortDir, 1);
                }
            }
            if ($arField["selectKey"]) {
                $selectable = true;
            }
            if ($arDataSample !== false) {
                $tplHeader->addvar("numeric", preg_match("/^[0-9\.\:\,\-\+\s]+$/", $arDataSample[$fieldName]));
            }
            $arHeader[] = $tplHeader;
            $fieldIndex++;
        }
        // Create frame template
        $selectable = ($actionTemplate !== false ? $selectable : false);
        $tplFrame = new Template("tpl/".$GLOBALS['s_lang']."/data_table.frame.htm");
        $tplFrame->addvar("hash", $dtQuery->getDataTable()->getHash());
        $tplFrame->addvar("action", ($actionTemplate === false ? "" : $actionTemplate));
        $tplFrame->addvar("filter", ($filterTemplate === false ? "" : $filterTemplate));
        foreach ($dtQuery->getConfigWhere() as $whereIndex => $arWhere) {
            if (count($arWhere["value"]) > 1) continue;
            $tplFrame->addvar("filter_".$arWhere["ident"], $arWhere["value"][0]);
            $tplFrame->addvar("filter_".$arWhere["ident"]."_".$arWhere["value"][0], 1);
        }
        $tplFrame->addvar("jsonQueryOptions", json_encode($dtQuery->getConfigQuery()));
        $tplFrame->addvar("body", self::renderDataTableBodyInternal($arResult, $dtFieldsLabeled, $arFieldsCached, $arFieldsSelectKey, $callbackCol));
        $tplFrame->addvar("resultFirst", $dtQuery->getConfigLimitOffset() + 1);
        if (($resultCount - $dtQuery->getConfigLimitOffset()) < $dtQuery->getConfigLimitCount()) {
            $tplFrame->addvar("resultLast", $resultCount);
        } else {
            $tplFrame->addvar("resultLast", $dtQuery->getConfigLimitOffset() + $dtQuery->getConfigLimitCount());
        }
        $tplFrame->addvar("resultCount", $resultCount);
        $tplFrame->addvar("limit", $dtQuery->getConfigLimitCount());
        $tplFrame->addvar("colCount", count($dtFieldsLabeled) + ($selectable ? 1 : 0));
        $tplFrame->addvar("selectable", $selectable);
        $tplFrame->addvar("title", Translation::readTranslation("admin", "dataTable.".$dtTableName.".title", null, array(), $title));
        if ($ajaxUrl !== NULL) {
            $tplFrame->addvar("ajaxUrl", $ajaxUrl);
        }
        $tplFrame->addvar("header", $arHeader);
        // Render
        return $tplFrame->process(true);
    }

    /**
     * @param Api_DataTableQuery    $dtQuery
     * @param int|null              $resultCount
     * @param callable|null         $callbackCol
     * @return string
     */
    public static function renderDataTableBody(Api_DataTableQuery $dtQuery, &$resultCount = NULL, $callbackCol = NULL) {
        // Create field cache for body
        $dtFieldsLabeled = $dtQuery->getConfigFields();
        $arFieldsCached = array();
        $arFieldsSelectKey = $dtQuery->getDataTable()->getSelectKeys();
        $fieldIndex = 0;
        foreach ($dtFieldsLabeled as $fieldName => $fieldLabel) {
            $arField = $dtQuery->getDataTable()->getField($fieldName);
            if ($fieldLabel === NULL) {
                continue;
            }
            // Add field to cache
            $arFieldsCached[] = $arField;
            $fieldIndex++;
        }
        // Query result
        $arResult = $dtQuery->fetchTable();
        if ($resultCount !== NULL) {
            $resultCount = $dtQuery->fetchCount();
        }
        return self::renderDataTableBodyInternal($arResult, $dtFieldsLabeled, $arFieldsCached, $arFieldsSelectKey, $callbackCol);
    }

    /**
     * @param array         $arResult
     * @param callable|null $callbackCol
     * @return string
     */
    private static function renderDataTableBodyInternal($arResult, &$dtFieldsLabeled, &$arFieldsCached, &$arFieldsSelectKey = array(), $callbackCol = NULL) {
        // Create rows
        $arRows = array();
        foreach ($arResult as $rowIndex => $arRow) {
            $tplRow = new Template("tpl/".$GLOBALS['s_lang']."/data_table.row.htm");
            $selectKey = array();
            // Create cols
            $arCols = array();
            $fieldIndex = 0;
            foreach ($dtFieldsLabeled as $fieldName => $fieldLabel) {
                if ($fieldLabel === NULL) {
                    continue;
                }
                $arField = $arFieldsCached[$fieldIndex];
                $fieldValue = $arRow[$fieldName];
                $tplColSource = "data_table.col.htm";
                $arCol = array(
                    "field"     => $fieldName,
                    "numeric"   => preg_match("/^[0-9\.\:\,\-\+\s]+$/", $fieldValue),
                    "value"     => $fieldValue
                );
                if ($callbackCol !== NULL) {
                    $callbackCol($arCol);
                }
                if (($arField !== NULL) && array_key_exists("colTemplate", $arField) && ($arField["colTemplate"] !== NULL)) {
                    $tplColSource = $arField["colTemplate"];
                }
                $tplCol = new Template("tpl/".$GLOBALS['s_lang']."/".$tplColSource);
                if ($arField !== NULL) {
                    if ($arField["selectKey"]) {
                        $selectKey[] = $fieldValue;
                    }
                    if (array_key_exists("colCallback", $arField) && ($arField["colCallback"] !== NULL)) {
                        $arField["colCallback"]($tplCol, $arRow);
                    }
                }
                $tplCol->addvars($arCol);
                $tplCol->addvars($arRow, "row_");
                $arCols[] = $tplCol;
                $fieldIndex++;
            }
            if (!empty($arFieldsSelectKey)) {
                $tplRow->addvar("selectable", true);
                if (count($arFieldsSelectKey) == 1) {
                    // Single field select key
                    $tplRow->addvar("selectKey", $arRow[ $arFieldsSelectKey[0] ]);
                } else {
                    // Multiple field select key
                    $arSelectKeys = array();
                    foreach ($arFieldsSelectKey as $selectKeyField) {
                        $arSelectKeys[] = $arRow[ $selectKeyField ];
                    }
                    $tplRow->addvar("selectKeyJson", json_encode($arSelectKeys));
                }
            }
            $tplRow->addvar("cols", $arCols);
            $arRows[] = $tplRow;
        }
        // Create body template
        $tplDataTable = new Template("tpl/".$GLOBALS['s_lang']."/data_table.body.htm");
        $tplDataTable->addvar("rows", $arRows);
        // Render
        return $tplDataTable->process(true);
    }
}