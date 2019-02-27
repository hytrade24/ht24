<?php

class Ad_Bulk_Create {
    
    public static function GetTables(ebiz_db $db, $parentId) {
        $arTablesUsed = $db->fetch_col("
            SELECT DISTINCT KAT_TABLE FROM `kat` WHERE ROOT=1 AND PARENT=".(int)$parentId);
        foreach ($arTablesUsed as $tableIndex => $tableName) {
            $arTablesUsed[$tableIndex] = "'".mysql_real_escape_string($tableName)."'";
        }
        return Api_StringManagement::getInstance($db)->readRaw("table_def", "T_NAME IN (".implode(", ", $arTablesUsed).")", "t.*, s.V1, s.V2, s.T1", 0, 0);
    }
    
    public static function GetManufacturers(ebiz_db $db, $tableId) {
        $tableName = $db->fetch_atom("
            SELECT T_NAME FROM `table_def` WHERE ID_TABLE_DEF=".(int)$tableId);
        if (empty($tableName)) {
            return array();
        }
        $arCategoryIds = $db->fetch_col("
            SELECT ID_KAT FROM `kat` WHERE ROOT=1 AND KAT_TABLE='".mysql_real_escape_string($tableName)."'");
        $arManufacturerGroupIds = $db->fetch_col("
            SELECT DISTINCT FK_MAN_GROUP FROM `man_group_category` WHERE FK_KAT IN (".implode(", ", $arCategoryIds).")");
        $arManufacturers = $db->fetch_table("
            SELECT m.* FROM `manufacturers` m
            LEFT JOIN `man_group_mapping` mgm ON m.ID_MAN=mgm.FK_MAN 
            WHERE mgm.FK_MAN_GROUP IN (".implode(", ", $arManufacturerGroupIds).")");
        return $arManufacturers;
    }
    
    public static function GetProducts(ebiz_db $db, $tableId, $manufacturerId, $userId = null) {
        $tableName = $db->fetch_atom("
            SELECT T_NAME FROM `table_def` WHERE ID_TABLE_DEF=".(int)$tableId);
        if (empty($tableName)) {
            return array();
        }
        $arCategoryIds = $db->fetch_col("
            SELECT ID_KAT FROM `kat` WHERE ROOT=1 AND KAT_TABLE='".mysql_real_escape_string($tableName)."'");
        require_once $GLOBALS["ab_path"]."sys/lib.hdb.php";
        $hdbManagement = ManufacturerDatabaseManagement::getInstance($db);
        $arProductTypes = array(
            $hdbManagement->fetchProductTypeByTable($tableName)
        );
        $arProducts = $hdbManagement->searchProductsByMan($manufacturerId, 1, -1, $arProductTypes, $arCategoryIds)->results;
        $arProductsOnline = array();
        if ($userId !== null) {
            $arProductsOnline = $db->fetch_col("
                SELECT DISTINCT FK_PRODUCT FROM `ad_master` 
                WHERE FK_USER=".(int)$userId." AND FK_MAN=".$manufacturerId." AND DELETED=0 AND (STATUS&3)=1");
        }
        foreach ($arProducts as $productIndex => $productData) {
            $arProducts[$productIndex]["IS_ONLINE"] = in_array($productData["ID_HDB_PRODUCT"], $arProductsOnline);
        }
        return $arProducts;
    }
    
    protected $db;
    protected $arTableLookup;
    protected $arTableFields;
    protected $arQueryBuffer;
    protected $arUserData;
    protected $arUserPayment;
    protected $defaultRuntime;
        
    public function __construct(ebiz_db $db) {
        $this->db = $db;
        $this->arTableLookup = array();
        $this->arTableFields = array();
        $this->arQueryBuffer = array();
        $this->arUserData = array();
        $this->arUserPayment = array();
        $this->defaultRuntime = null;
    }
    
    protected function addQuery($tableName, $arData, $type = "INSERT") {
        // Create query type within buffer if not existing
        if (!array_key_exists($type, $this->arQueryBuffer)) {
            $this->arQueryBuffer[$type] = array();
        }
        // Create table within buffer/fields lookup if not existing
        if (!array_key_exists($tableName, $this->arQueryBuffer[$type])) {
            $this->arQueryBuffer[$type][$tableName] = array();
        }
        $this->preloadTableFields($tableName);
        // Add query data to buffer
        $this->arQueryBuffer[$type][$tableName][] = array_intersect_key($arData, array_flip($this->arTableFields[$tableName]));
        return true;
    }
    
    protected function getDefaultRuntime() {
        if ($this->defaultRuntime === null) {
            $arRuntimes = array_values(Api_LookupManagement::getInstance($this->db)->readByArt("LAUFZEIT"));
            if (count($arRuntimes) > 0) {
                $this->defaultRuntime = $arRuntimes[ count($arRuntimes)-1 ];
            }
        }
        return $this->defaultRuntime;
    }
    
    protected function getTableName($tableId) {
        if (!array_key_exists($tableId, $this->arTableLookup)) {
            $this->arTableLookup[$tableId] = $this->db->fetch_atom("
                SELECT T_NAME FROM `table_def` WHERE ID_TABLE_DEF=".(int)$tableId);
        }
        return $this->arTableLookup[$tableId];
    }
    
    protected function getSearchDatabaseText($articleData, $langval) {
		require_once $GLOBALS["ab_path"]."sys/lib.ads.php";
		return AdManagment::getAdSearchTextRaw($articleData, $langval);
    }
    
    protected function getUserData($userId) {
        if (!array_key_exists($userId, $this->arUserData)) {    
            // Preload Userdata
            $this->arUserData[$userId] = $this->db->fetch1("
                SELECT
                    u.*, uc.*, us.*
                FROM user u
                JOIN usercontent uc ON uc.FK_USER = u.ID_USER
                LEFT JOIN usersettings us ON us.FK_USER = u.ID_USER
                WHERE u.ID_USER = '".$userId."'");
        }
        return $this->arUserData[$userId];
    }
    
    protected function getUserPayment($userId) {
        if (!array_key_exists($userId, $this->arUserPayment)) {
            // Preload Payment Adapter
            $paymentAdapterUserManagement = PaymentAdapterUserManagement::getInstance($this->db);
            $this->arUserPayment[$userId] = $paymentAdapterUserManagement->fetchAllAutoCheckedPaymentAdapterByUser($userId);
        }
        return $this->arUserPayment[$userId];
    }
    
    protected function getAutoIncrement($articleCount = 1) {        
		$tmp = $this->db->fetch1("SHOW TABLE STATUS  LIKE 'ad_master'");
		// Get the current auto increment id
		$autoIncrementPointer = (int)$tmp['Auto_increment'];
		// Move auto increment after the last reserved id
        $this->db->querynow("ALTER TABLE ad_master AUTO_INCREMENT = ".($autoIncrementPointer + $articleCount));
        // Return starting id
		return $autoIncrementPointer;
    }
    
    protected function preloadTableFields($tableName) {
        if (!array_key_exists($tableName, $this->arTableFields)) {
            $this->arTableFields[$tableName] = array();
			$structure = $this->db->fetch_table("SHOW COLUMNS FROM `".mysql_real_escape_string($tableName)."`");
			foreach($structure as $skey => $value) {
				$this->arTableFields[$tableName][] = $value['Field'];
			}
        }
        return true;
    }
    
    public function addArticle($tableName, $articleData, $userId, $articleId) {
        // Get base article data
        require_once $GLOBALS["ab_path"]."sys/lib.ads.php";
        $arUser = $this->getUserData($userId);
		$arArticle = AdManagment::createArticleAsArray($articleData['FK_KAT'], null, $arUser);
		$arArticle = array_merge($arArticle, $articleData);
        // Fill base fields
        $arArticle["ID_AD_MASTER"] = $articleId;
        $arArticle["ID_".strtoupper($tableName)] = $articleId;
        $arArticle["AD_TABLE"] = $tableName;
        $arArticle["FK_USER"] = $userId;
		// Moderate ads?
		if ($GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["MODERATE_ADS"]) {
			$userIsAutoConfirmed = $arUser['AUTOCONFIRM_ADS'];
			if ($userIsAutoConfirmed) {
				$arArticle["CONFIRMED"] = 1;
			} else {
				$arArticle["CONFIRMED"] = 0;
				$arArticle['CRON_DONE'] = 1;
			}
		} else {
			$arArticle["CONFIRMED"] = 1;
		}
		// Shipping
		if (!array_key_exists("VERSANDOPTIONEN", $arArticle) || ($arArticle["VERSANDOPTIONEN"] == "")) {
			if (array_key_exists("VERSANDKOSTEN", $arArticle) && ($arArticle["VERSANDKOSTEN"] > 0)) {
				$arArticle["VERSANDOPTIONEN"] = 3;
			}
		}
		// Comments
		if (!$arArticle["ALLOW_COMMENTS"]) {
			// Read default setting
			$userAllowComments = (($arUser['ALLOW_COMMENTS']&1 > 0) ? 1 : 0);
			$arArticle["ALLOW_COMMENTS"] = $userAllowComments;
		}
		// Enable article?
		$enable = ($arArticle['CONFIRMED'] == 1 ? true : false);
		if ($enable) {
		    $arRuntime = $this->getDefaultRuntime();
		    $runtimeDays = (int)$arRuntime["VALUE"];
			$tmpDate = new DateTime();
			$arArticle["LU_LAUFZEIT"] = $arRuntime["ID_LOOKUP"];
			$arArticle["STAMP_START"] = $tmpDate->format("Y-m-d H:i:s");
			$tmpDate->add(new DateInterval("P".$runtimeDays."D"));
			$arArticle["STAMP_END"] =  $tmpDate->format("Y-m-d H:i:s");
			$arArticle["STATUS"] =  1;
        }
        // Default settings
        if (empty($arArticle["MENGE"]) || ($arArticle["MENGE"] == 0)) {
            $arArticle["MENGE"] =  1;
        }
        
        // Add article table insert
        $this->addQuery($tableName, $arArticle);
        // Add master table insert
        $this->addQuery("ad_master", $arArticle);
        // Add payment adapters
        $arUserPayment = $this->getUserPayment($userId);
		if(($arUserPayment != null) && is_array($arUserPayment)) {
			foreach($arUserPayment as $key => $paymentAdapter) {
			    $this->addQuery("ad2payment_adapter", array(
			        'FK_AD' => $articleId, 'FK_PAYMENT_ADAPTER' => $paymentAdapter
                ));
			}
		}
        // Add fulltext search index
		foreach ($GLOBALS["lang_list"] as $langKey => $langData) {
            $searchText = $this->getSearchDatabaseText($arArticle, $langData["BITVAL"]);
            $this->addQuery("ad_search", array(
                'FK_AD' => $articleId, 'FK_USER' => $userId, 'LANG' => $langKey,
                'AD_TABLE' => $tableName, 'STEXT' => $searchText
            ));
        }
    }
    
    public function addProducts($tableId, $productIds, $userId) {
        $tableName = $this->getTableName($tableId);
        $tableNameHdb = "hdb_table_".$tableName;
        require_once $GLOBALS["ab_path"]."sys/lib.hdb.php";
        $hdbManagement = ManufacturerDatabaseManagement::getInstance($this->db);
        $arProducts = $hdbManagement->fetchAllByParam($tableNameHdb, array(
            "ID_".strtoupper($tableNameHdb) => $productIds
        ));
        $articleId = $this->getAutoIncrement( count($arProducts) );
        foreach ($arProducts as $productIndex => $productData) {
            $productData["FK_PRODUCT"] = $productData["ID_HDB_PRODUCT"];
            $this->addArticle($tableName, $productData, $userId, $articleId++);
        }
    }
    
    public function finish() {
        foreach ($this->arQueryBuffer as $type => $arTables) {
            foreach ($arTables as $tableName => $arEntries) {
                // Build query fields for table
                $arQueryFields = array();
                foreach ($this->arTableFields[$tableName] as $fieldIndex => $fieldName) {
                    $arQueryFields[] = "`".mysql_real_escape_string($fieldName)."`";
                }
                // Build query data
                $arQueryData = array();
                foreach ($arEntries as $entryIndex => $entryData) {
                    $arQueryDataRow = array();
                    foreach ($this->arTableFields[$tableName] as $fieldIndex => $fieldName) {
                        if (!array_key_exists($fieldName, $entryData) || ($entryData[$fieldName] === null)) {
                            $arQueryDataRow[] = "NULL";
                        } else {
                            $arQueryDataRow[] = "'".mysql_real_escape_string($entryData[$fieldName])."'";
                        }
                    }
                    $arQueryData[] = "(".implode(",", $arQueryDataRow).")";
                }
                // Build query string
                $query = "";
                switch ($type) {
                    case "INSERT":
                        $query = "
                            INSERT INTO `".mysql_real_escape_string($tableName)."`
                                (".implode(",", $arQueryFields).")
                            VALUES
                                ".implode(",\n                                ", $arQueryData).";";
                        break;
                    case "UPDATE":
                        $query = "
                            REPLACE INTO `".mysql_real_escape_string($tableName)."`
                                (".implode(",", $arQueryFields).")
                            VALUES
                                ".implode(",\n                                ", $arQueryData).";";
                        break;
                }
                $queryResult = $this->db->querynow($query, false, false, true);
				if ($queryResult['str_error'] != '') {
				    eventlog("error", "Ad_Bulk_Create::finish - Fehler beim einfügen der Artikel!", $queryResult['str_error']."\nQuery:\n\n".$query);
                }
            }
        }
    }

}