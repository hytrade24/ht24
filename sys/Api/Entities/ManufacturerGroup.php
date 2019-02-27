<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 11.04.16
 * Time: 12:38
 */

class Api_Entities_ManufacturerGroup {
    
    private static $cacheLocal = array();
    private static $manufacturerNamesLength = 100;
    
    protected static function extendSingle(&$arManufacturerGroup, ebiz_db $db = null) {
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        $idManufacturerGroup = (int)$arManufacturerGroup["ID_MAN_GROUP"];
        $arManufacturerMapping = $db->fetch_nar("
            SELECT gm.FK_MAN, m.NAME
            FROM `man_group_mapping` gm
            JOIN `manufacturers` m ON m.ID_MAN=gm.FK_MAN
            WHERE gm.FK_MAN_GROUP=".$idManufacturerGroup);
        $arManufacturerGroup["MANUFACTURERS"] = array_keys($arManufacturerMapping);
        $arManufacturerGroup["MANUFACTURERS_NAMES"] = array_values($arManufacturerMapping); 
        $arNamesTruncated = array();
        $truncatedNames = false;
        $truncatedNamesLength = 0;
        foreach ($arManufacturerGroup["MANUFACTURERS_NAMES"] as $manufacturerIndex => $manufacturerName) {
            $manufacturerNameLen = strlen($manufacturerName);
            if (($truncatedNamesLength + $manufacturerNameLen) < self::$manufacturerNamesLength) {
                $truncatedNamesLength += $manufacturerNameLen;
                $arNamesTruncated[] = $manufacturerName;
            } else {
                $truncatedNames = true;
            }
        }
        $arManufacturerGroup["SER_MANUFACTURERS"] = implode(",", $arManufacturerGroup["MANUFACTURERS"]);
        $arManufacturerGroup["TXT_MANUFACTURERS"] = implode(", ", $arNamesTruncated).($truncatedNames ? ", ..." : "");
        return $arManufacturerGroup;
    }
    
    protected static function extendList(&$arManufacturerGroups, ebiz_db $db = null) {
        foreach ($arManufacturerGroups as $groupIndex => $groupDetails) {
            self::extendSingle($arManufacturerGroups[$groupIndex]);
        }
        return $arManufacturerGroups;
    }
    
    public static function getById($idManufacturerGroup, ebiz_db $db = null, $langval = null) {
        $dataTable = self::getDataTable($db, $langval);
        $dataTableQuery = $dataTable->createQuery();
        $dataTableQuery->addFields("g.*");
        $dataTableQuery->addFields("sg.*");
        $dataTableQuery->addWhereCondition("ID_MAN_GROUP", $idManufacturerGroup);
        return self::extendSingle($dataTableQuery->fetchOne());
    }
    
    public static function getByParam($arParams = array(), ebiz_db $db = null, $langval = null) {
        $dataTable = self::getDataTable($db, $langval);
        $dataTableQuery = $dataTable->createQuery();
        $dataTableQuery->addFields("g.*");
        $dataTableQuery->addFields("sg.*");
        foreach ($arParams as $paramIndex => $paramValue) {
            if (($paramIndex == "id") || ($paramIndex == "ID_MAN_GROUP")) {
                $dataTableQuery->addWhereCondition("ID_MAN_GROUP", $paramValue);
            }
        }
        $dataTableQuery->addSortField("sg.V1", "ASC");
        return self::extendList($dataTableQuery->fetchTable());
    }
    
    public static function getManufacturersByCategory($idCategory, $useCache = true, ebiz_db $db = null, $langval = null) {
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($useCache && array_key_exists("manufacturersByCategory", self::$cacheLocal)) {
            if (array_key_exists($idCategory, self::$cacheLocal["manufacturersByCategory"])) {
                return self::$cacheLocal["manufacturersByCategory"][$idCategory];
            }
        }
        $dataTable = self::getDataTable($db, $langval);
        $dataTableQuery = $dataTable->createQuery();
        $dataTableQuery->addField("g.ID_MAN_GROUP");
        $dataTableQuery->addWhereCondition("FK_KAT", array($idCategory));
        $arManufacturerGroups = $dataTableQuery->fetchCol();
        $arManufacturers = array();
        if (!empty($arManufacturerGroups)) {
            $arManufacturers = $db->fetch_table("
              SELECT m.* FROM `manufacturers` m 
              JOIN `man_group_mapping` mg ON mg.FK_MAN=m.ID_MAN
              WHERE m.CONFIRMED=1 AND mg.FK_MAN_GROUP IN (".implode(", ", $arManufacturerGroups).")
              GROUP BY m.ID_MAN
              ORDER BY m.NAME ASC");
        }
        if ($useCache) {
            if (!array_key_exists("manufacturersByCategory", self::$cacheLocal)) {
                self::$cacheLocal["manufacturersByCategory"] = array();
            }
            self::$cacheLocal["manufacturersByCategory"][$idCategory] = $arManufacturers;
        }
        return $arManufacturers;
    }
    
    public static function getDataTable(ebiz_db $db = null, $langval = null) {
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        $dataTable = new Api_DataTable($db, "man_group", "g");
        // Add joins
        $dataTable->addTableJoin("man_group_category", "gc", "JOIN", "gc.FK_MAN_GROUP=g.ID_MAN_GROUP");
        $dataTable->addTableJoinString("man_group", "g", "string_man_group", "sg", "LEFT JOIN", $langval);
        // Add fields
        $dataTable->addFieldsFromDb("g");
        $dataTable->addFieldsFromDb("sg");
        $dataTable->setFieldSortable("sg", "V1");
        // Add conditions
        $dataTable->addWhereCondition("ID_MAN_GROUP", "g.ID_MAN_GROUP=$1$");
        $dataTable->addWhereCondition("FK_KAT", "gc.FK_KAT=$1$", array("gc"));
        return $dataTable;
    }

    public static function deleteById($idGroup, ebiz_db $db = null) {
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        $result = $db->delete("man_group", $idGroup);
        $db->querynow("DELETE FROM `man_group_category` WHERE FK_MAN_GROUP=".$idGroup);
        $db->querynow("DELETE FROM `man_group_mapping` WHERE FK_MAN_GROUP=".$idGroup);
        return ($result ? true : false);
    }

}