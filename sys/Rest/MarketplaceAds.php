<?php

class Rest_MarketplaceAds extends Rest_Abstract {
    
    // Marketplace table field definitions
    public static $articleFieldsMaster = array(
        "ID_AD_MASTER", "FK_KAT", "FK_USER", "STATUS", "B_TOP", "B_TOP_LIST", "STAMP_START", "BF_CONSTRAINTS",
        "PRODUKTNAME", "BESCHREIBUNG", "STREET", "ZIP", "CITY", "FK_COUNTRY", "LATITUDE", "LONGITUDE", "IMPORT_IMAGES", 
        "EAN", "MENGE", "PREIS", "MWST", "TRADE", "PSEUDOPREIS", "B_PSEUDOPREIS_DISCOUNT", "VERKAUFSOPTIONEN", 
        "VERSANDKOSTEN", "VERSANDOPTIONEN"
    );
    public static $articleFieldsMasterAliases = array(
        "ID_AD_MASTER" => "ID_AD"
    );
    public static $articleFieldsMasterIndex = array("ID_AD_MASTER");
    public static $articleFieldsMasterSortable = array("ID_AD_MASTER", "B_TOP_LIST", "STAMP_START", "PRODUKTNAME", "PREIS");
    public static $articleFieldsMasterSearchable = array("PREIS");
    
    public static function createAd(&$arData, ebiz_db $db = null, $langval = null) {
        $marktplaceArticle = Api_Entities_MarketplaceArticle::createFromFullArray($arData, $db, $langval);
        $errors = $marktplaceArticle->validate();
        if ($errors !== true) {
            $errors_text = array();
            foreach ($errors as $errorField => $errorMessage) {
                $errors_text[] = $errorField.": ".$errorMessage;
            }
            self::setLastError(null, implode("\n", $errors_text));
            return false;
        }
        if (!$marktplaceArticle->writeToDatabase()) {
            return false;
        }
        return $marktplaceArticle->getId();
    }
    
    public static function createAdCheck(&$arData, ebiz_db $db = null, $langval = null) {
        $marktplaceArticle = Api_Entities_MarketplaceArticle::createFromFullArray($arData, $db, $langval);
        $errors = $marktplaceArticle->validate();
        if ($errors !== true) {
            $errors_text = array();
            foreach ($errors as $errorField => $errorMessage) {
                $errors_text[] = $errorField.": ".$errorMessage;
            }
            self::setLastError(null, implode("\n", $errors_text));
            return false;
        }
        return true;
    }    
    
    public static function updateAd($articleId, &$arData, ebiz_db $db = null, $langval = null) {
        $marktplaceArticle = Api_Entities_MarketplaceArticle::getById($articleId, true, $db, $langval);
        $marktplaceArticle->update($arData);
        $errors = $marktplaceArticle->validate();
        if ($errors !== true) {
            $errors_text = array();
            foreach ($errors as $errorField => $errorMessage) {
                $errors_text[] = $errorField.": ".$errorMessage;
            }
            self::setLastError(null, implode("\n", $errors_text));
            return false;
        }
        if (!$marktplaceArticle->writeToDatabase()) {
            return false;
        }
        return $marktplaceArticle->getId();
    }
    
    /**
     * Enable the given marketplace ad.
     * @param int       $adId
     * @param string    $adTable
     * @return bool
     */
    public static function enableAd($adId, $adTable) {
        // TODO: Move enable funtion to this class
        require_once $GLOBALS["ab_path"]."sys/lib.ads.php";
        return AdManagment::Enable($adId, $adTable);
    }

    /**
     * Enable the given marketplace ads
     * @param array     $adIds
     * @return bool
     */
    public static function enableAds($adIds) {
        global $db;
        // Escape ad ids
        $arIds = array();
        foreach ($adIds as $index => $id) {
            $arIds[] = (int)$id;
        }
        // Get ads and group them by table
        $arAds = $db->fetch_nar("SELECT ID_AD_MASTER, AD_TABLE FROM `ad_master` WHERE ID_AD_MASTER IN (".implode(",", $arIds).")");
        return self::enableAdsEx($arAds);
    }

    /**
     * Enable the given marketplace ads (by id => table array)
     * @param array     $arAds
     * @return bool
     */
    public static function enableAdsEx($arAds) {
        // TODO: Implement a more effective way of enabling multiple ads
        foreach ($arAds as $id => $table) {
            self::enableAd($id, $table);
        }
        return true;
    }

    /**
     * Disable the given marketplace ad
     * @param int       $adId
     * @param string    $adTable
     */
    public static function disableAd($adId, $adTable) {
        // TODO: Move disable funtion to this class
        require_once $GLOBALS["ab_path"]."sys/lib.ads.php";
        return AdManagment::Disable($adId, $adTable);
    }

    /**
     * Disable the given marketplace ads
     * @param array     $adIds
     * @return bool
     */
    public static function disableAds($adIds) {
        global $db;
        // Escape ad ids
        $arIds = array();
        foreach ($adIds as $index => $id) {
            $arIds[] = (int)$id;
        }
        // Get ads and group them by table
        $arAds = $db->fetch_nar("SELECT ID_AD_MASTER, AD_TABLE FROM `ad_master` WHERE ID_AD_MASTER IN (".implode(",", $arIds).")");
        return self::disableAdsEx($arAds);
    }

    /**
     * Disable the given marketplace ads (by id => table array)
     * @param array     $arAds
     * @return bool
     */
    public static function disableAdsEx($arAds) {
        // TODO: Implement a more effective way of disabling multiple ads
        foreach ($arAds as $id => $table) {
            self::disableAd($id, $table);
        }
        return true;
    }

    /**
     * Delete the given marketplace ad
     * @param int       $adId
     * @param string    $adTable
     */
    public static function deleteAd($adId, $adTable) {
        global $db, $ab_path;
        $directory = self::getAdCachePath($adId, false, true);
        // Kommentare löschen
        require_once $ab_path."sys/lib.comment.php";
        $cmNews = CommentManagement::getInstance($db, 'ad_master');
        $cmNews->deleteAllComments($adId);
        ### Artikel löschen!
        $db->querynow("DELETE FROM `ad_master` WHERE ID_AD_MASTER=".$adId);
        $db->querynow("DELETE FROM `ad_images` WHERE FK_AD=".$adId);
        $db->querynow("DELETE FROM `ad_upload` WHERE FK_AD=".$adId);
        $db->querynow("DELETE FROM `ad_search` WHERE FK_AD=".$adId);
        $db->querynow("DELETE FROM `ad_likes` WHERE FK_AD=".$adId);
        $db->querynow("DELETE FROM `ad_video` WHERE FK_AD=".$adId);
        $db->querynow("DELETE FROM `".$adTable."` WHERE ID_".strtoupper($adTable)."=".$adId);
        $db->querynow("DELETE FROM `ad_agent_temp` WHERE FK_ARTICLE=".$adId);
        $db->querynow("DELETE FROM `trade` WHERE FK_AD=".$adId);
        $db->querynow("DELETE FROM `trade_ad` WHERE FK_AD=".$adId);
        $db->querynow("DELETE FROM `verstoss` WHERE FK_AD=".$adId);
        $db->querynow("DELETE v, v2v FROM ad_variant v, ad_variant2liste_values v2v WHERE v.FK_AD_MASTER = '".(int)$adId."' AND v.ID_AD_VARIANT = v2v.FK_AD_VARIANT");
        $db->querynow("DELETE FROM `ad2payment_adapter` WHERE FK_AD=".$adId);
        if (is_dir($directory)) {
            system("rm -r ".$directory);
        }
    }

    /**
     * Delete the given marketplace ads
     * @param array     $adIds
     * @return bool
     */
    public static function deleteAds($adIds) {
        global $db;
        // Escape ad ids
        $arIds = array();
        foreach ($adIds as $index => $id) {
            $arIds[] = (int)$id;
        }
        // Get ads and group them by table
        $arAds = $db->fetch_nar("SELECT ID_AD_MASTER, AD_TABLE FROM `ad_master` WHERE ID_AD_MASTER IN (".implode(",", $arIds).")");
        return self::deleteAdsEx($arAds);
    }

    /**
     * Delete the given marketplace ads (by id => table array)
     * @param array     $arAds 
     * @return bool
     */
    public static function deleteAdsEx($arAds) {
        global $db, $ab_path;
        $arIds = array_keys($arAds);
        $arAdsByTable = array();
        foreach ($arAds as $id => $table) {
            if (!array_key_exists($table, $arAdsByTable)) {
                $arAdsByTable[$table] = array();
            }
            $arAdsByTable[$table][] = $id;
        }
        // Comments
        require_once $ab_path."sys/lib.comment.php";
        $cmNews = CommentManagement::getInstance($db, 'ad_master');
        // Delete dependend data
        $db->querynow("DELETE FROM `ad_master` WHERE ID_AD_MASTER IN (".implode(",", $arIds).")");
        foreach ($arAdsByTable as $table => $arTableAdIds) {
            $db->querynow("DELETE FROM `".$table."` WHERE ID_".strtoupper($table)." IN (".implode(",", $arTableAdIds).")");
            foreach ($arTableAdIds as $index => $id) {
                $directory = self::getAdCachePath($id, false, true);
                if (is_dir($directory)) {
                    system("rm -r ".$directory);
                }
                $cmNews->deleteAllComments($id);
            }
        }
        $db->querynow("DELETE FROM `ad_images` WHERE FK_AD IN (".implode(",", $arIds).")");
        $db->querynow("DELETE FROM `ad_upload` WHERE FK_AD IN (".implode(",", $arIds).")");
        $db->querynow("DELETE FROM `ad_search` WHERE FK_AD IN (".implode(",", $arIds).")");
        $db->querynow("DELETE FROM `ad_likes` WHERE FK_AD IN (".implode(",", $arIds).")");
        $db->querynow("DELETE FROM `ad_video` WHERE FK_AD IN (".implode(",", $arIds).")");
        $db->querynow("DELETE FROM `ad_agent_temp` WHERE FK_ARTICLE IN (".implode(",", $arIds).")");
        $db->querynow("DELETE FROM `trade` WHERE FK_AD IN (".implode(",", $arIds).")");
        $db->querynow("DELETE FROM `trade_ad` WHERE FK_AD IN (".implode(",", $arIds).")");
        $db->querynow("DELETE FROM `verstoss` WHERE FK_AD IN (".implode(",", $arIds).")");
        $db->querynow("DELETE v, v2v FROM ad_variant v, ad_variant2liste_values v2v WHERE IN (".implode(",", $arIds).") AND v.ID_AD_VARIANT = v2v.FK_AD_VARIANT");
        $db->querynow("DELETE FROM `ad2payment_adapter` WHERE FK_AD IN (".implode(",", $arIds));
        return true;
    }

    /**
     * Delete a marketplace ad from the user perspective (fails for foreign ads)
     * @param int   $adId
     * @param int   $userId
     * @return bool
     */
    public static function deleteAdUser($adId, $userId) {
        global $db;
        $ar_article = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".(int)$adId);
        if ($ar_article["FK_USER"] != $userId) {
            return false;
        }
        $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".mysql_real_escape_string($ar_article["FK_KAT"]));
        self::disableAd($adId, $kat_table);
        $db->querynow("UPDATE `ad_master` SET DELETED=1 WHERE ID_AD_MASTER=".(int)$adId);

        $db->querynow("DELETE FROM `ad_images` WHERE FK_AD=".$adId);
        $db->querynow("DELETE FROM `ad_upload` WHERE FK_AD=".$adId);
        $db->querynow("DELETE FROM `ad_search` WHERE FK_AD=".$adId);
        $db->querynow("DELETE FROM `ad_video` WHERE FK_AD=".$adId);

        $directory = self::getAdCachePath($adId, false, true);
        if (is_dir($directory)) {
            system("rm -r ".$directory);
        }

        return true;
    }
    
    /**
     * Adds computed extra values to the given article list 
     * @param array $arArticleList
     */
    public static function extendAdDetailsList(&$arArticleList) {
        foreach ($arArticleList as $articleIndex => $arArticle) {
            self::extendAdDetailsSingle($arArticleList[$articleIndex]);
        }
    }

    /**
     * Adds computed extra values to the given article data 
     * @param array $arArticle
     */
    public static function extendAdDetailsSingle(&$arArticle) {
        require_once $GLOBALS["ab_path"]."sys/lib.ad_constraint.php";
        $arArticle = AdConstraintManagement::appendAdContraintMapping($arArticle);
    }

    /**
     * Returns a list of all fields with name, type and whether its required.
     * @param int           $idCategory
     * @param int|null      $idFieldGroup
     * @param array         $arSystemGroups
     * @return array|bool
     */
    public static function getFields($idCategory, $idFieldGroup, $arSystemGroups) {
        global $db;
        if (array_key_exists($idFieldGroup, $arSystemGroups)) {
            // System group, read from array
            return $arSystemGroups;
        } else {
            // Regular group, read from database
            global $langval;
            $arFields = $db->fetch_table("
                SELECT
                    f.F_NAME, f.F_TYP, f.FK_LISTE, IFNULL(kf.B_NEEDED,f.B_NEEDED) AS B_NEEDED, s.*
				FROM `kat` k
				LEFT JOIN `table_def` t ON t.T_NAME=k.KAT_TABLE
				LEFT JOIN `field_def` f ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
				LEFT JOIN `kat2field` kf ON kf.FK_KAT=k.ID_KAT AND kf.FK_FIELD=f.ID_FIELD_DEF
                LEFT JOIN `string_field_def` s ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF
					AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
				WHERE k.ID_KAT=".(int)$idCategory." AND kf.B_ENABLED=1 AND f.B_ENABLED=1
				    AND f.FK_FIELD_GROUP".($idFieldGroup === null ? " IS NULL" : "=".(int)$idFieldGroup));
            return $arFields;
        }
    }

    /**
     * Returns a field by name if found. Contains name, type and wheter its required.
     * @param   int     $idCategory
     * @param   string  $fieldName          Name of the field to be found
     * @param   array   $arSystemGroups
     * @return  array|bool                  The field with name, type and whether its required as array or false if not found.
     */
    public function getFieldByName($idCategory, $fieldName, $arSystemGroups) {
        // Look for matching field in system groups
        foreach ($arSystemGroups as $groupName => $arFields) {
            foreach ($arFields as $fieldIndex => $arField) {
                if ($arField["F_NAME"] == $fieldName) {
                    return $arField;
                }
            }
        }
        // No system field found, search in database
        global $db, $langval;
        $arField = $db->fetch1("
                SELECT
                    f.ID_FIELD_DEF, f.F_NAME, f.F_TYP, IFNULL(kf.B_NEEDED,f.B_NEEDED) AS B_NEEDED, s.*
				FROM `kat` k
				LEFT JOIN `table_def` t ON t.T_NAME=k.KAT_TABLE
				LEFT JOIN `field_def` f ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
				LEFT JOIN `kat2field` kf ON kf.FK_KAT=k.ID_KAT AND kf.FK_FIELD=f.ID_FIELD_DEF
                LEFT JOIN `string_field_def` s ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF
					AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
				WHERE k.ID_KAT=".(int)$idCategory." AND kf.B_ENABLED=1 AND f.B_ENABLED=1
				    AND f.F_NAME='".mysql_real_escape_string($fieldName)."'");
        return $arField;
    }

    /**
     * Get the label of the given marketplace field
     * @param int       $tableDefId
     * @param string    $fieldName
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return string
     */
    public static function getFieldLabelByName($tableDefId, $fieldName, ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Query result
        return $db->fetch_atom("
      			SELECT s.V1
      			FROM field_def f
      			LEFT JOIN `string_field_def` s on s.S_TABLE='field_def' and s.FK=f.ID_FIELD_DEF
      			  and s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
      			WHERE f.FK_TABLE_DEF=" . $tableDefId . " AND f.F_NAME='".mysql_real_escape_string($fieldName)."'");
    }

    /**
     * Get the field id of the given article table field
     * @param int       $tableDefId
     * @param string    $fieldName
     * @param ebiz_db   $db
     * @return string
     */
    public static function getFieldIdByName($tableDefId, $fieldName, ebiz_db $db = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        // Query result
        return $db->fetch_atom("
      			SELECT f.ID_FIELD_DEF
      			FROM field_def f
      			WHERE f.FK_TABLE_DEF=" . $tableDefId . " AND f.F_NAME='".mysql_real_escape_string($fieldName)."'");
    }

    /**
     * Get the label of the given list field
     * @param int       $idListeValue
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return string
     */
    public static function getListLabelById($idListeValue, ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Query result
        return $db->fetch_atom("
                SELECT s.V1
                FROM liste_values l
                LEFT JOIN string_liste_values s ON
                  s.FK=l.ID_LISTE_VALUES AND s.S_TABLE='liste_values' AND
                  s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                WHERE l.ID_LISTE_VALUES='".(int)$idListeValue."'");
    }
    
    /**
     * Get all search fields for the given table/category
     * @param int       $tableDefId
     * @param int       $categoryId
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return array
     */
    public static function getSearchFields($tableDefId, $categoryId, ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Query result
        return $db->fetch_table("
      			SELECT f.ID_FIELD_DEF, f.F_NAME, f.IS_SPECIAL, f.F_TYP, f.B_SEARCH, f.FK_LISTE, s.V1, s.V2, s.T1
      			FROM field_def f
      			LEFT JOIN `string_field_def` s on s.S_TABLE='field_def' and s.FK=f.ID_FIELD_DEF
      			  and s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
      			LEFT JOIN `kat2field` kf ON kf.FK_FIELD=f.ID_FIELD_DEF AND kf.FK_KAT=" . $categoryId . "
      			WHERE f.FK_TABLE_DEF=" . $tableDefId . " AND f.B_SEARCH IN(1,2) AND (f.IS_MASTER=1 OR kf.B_ENABLED=1)"
        );
    }

    public static function getSearchData($searchHash, ebiz_db $db = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        // Query result
        $arSearch = $db->fetch1("
       		SELECT S_STRING, S_WHERE, S_HAVING
       		FROM `searchstring`
       		WHERE `QUERY`='".mysql_real_escape_string($searchHash)."'");
       	return unserialize($arSearch["S_STRING"]);
    }

    /**
     * Get the plain top value for sorting (database field "B_TOP_LIST") as database update expression 
     * @param $topFlags
     */
    public static function getTopValueDatabaseUpdate($topFlagField) {
        return "(`".$topFlagField."` & 1)";
    }

    /**
     * Get the plain top value for sorting (database field "B_TOP_LIST") by the top flag bitfield 
     * @param $topFlags
     */
    public static function getTopValueByFlags($topFlags) {
        return ($topFlags & 1 ? 1 : 0);
    }
    
    /**
     * Get the variant fields for the given category
     * @param int       $tableDefId
     * @param int       $categoryId
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return array
     */
    public static function getVariantFields($tableDefId, $categoryId, ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Query result
        return $db->fetch_table("
      			SELECT f.ID_FIELD_DEF, f.F_NAME, f.IS_SPECIAL, f.F_TYP, f.B_SEARCH, f.FK_LISTE, s.V1, s.V2, s.T1
      			FROM field_def f
      			LEFT JOIN `string_field_def` s on s.S_TABLE='field_def' and s.FK=f.ID_FIELD_DEF
      			  and s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
      			LEFT JOIN `kat2field` kf ON kf.FK_FIELD=f.ID_FIELD_DEF AND kf.FK_KAT=" . $categoryId . "
      			WHERE f.FK_TABLE_DEF=" . $tableDefId . " AND F_TYP='VARIANT' AND (f.IS_MASTER=1 OR kf.B_ENABLED=1)"
        );
    }
    
    /**
     * Get all master search fields that behave like regular search fields
     * @return array
     */
    public static function getSearchFieldsMaster() {
        return array(
            array("F_NAME" => "PREIS", "F_TYP" => "FLOAT", "B_SEARCH" => 2, "IS_SPECIAL" => 0, "IS_MASTER" => 1)
        );
    }
    
    /**
     * Get the first ad matching the given conditions
     * @param array|string  $arFields
     * @param array         $arWhere
     * @param ebiz_db       $db
     * @param int|null      $langval
     */
    public static function getAd($categoryId = null, $arWhere = array(), $arFields = "*", ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Get table
        $table = self::getDataTable($categoryId, $db, $langval);
        $query = $table->createQuery();
        if (is_array($arFields)) {
            foreach ($arFields as $fieldIndex => $fieldName) {
                $query->addField($fieldName);
            }
        }
        // Add conditions
        $query->addWhereConditions($arWhere);
        // Get result
        return $query->fetchOne();
    }
    
    /**
     * Get the users matching the given conditions
     * @param array|string  $arFields
     * @param array         $arWhere
     * @param ebiz_db       $db
     * @param int|null      $langval
     */
    public static function getAdList($categoryId = null, $arWhere = array(), $arFields = "*", ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Get table
        $table = self::getDataTable($categoryId, $db, $langval);
        $query = $table->createQuery();
        if (is_array($arFields)) {
            foreach ($arFields as $fieldIndex => $fieldName) {
                $query->addField($fieldName);
            }
        }
        // Add conditions
        $query->addWhereConditions($arWhere);
        // Get result
        return $query->fetchTable();
    }

    /**
     * Get the cache path to the given ad
     *
     * The cache path is in the directory /cache/marktplatz/[YEAR]/[MONTH]/[HASH|0,3]/[HASH|3,4]/[ID]
     *
     * @static
     * @param int       $id                 Article ID
     * @param boolean   $createIfNotExist   Create if the path does not exist
     *
     * @return string|null Full absolute path without tailing slash
     */
    static function getAdCachePath($id, $createIfNotExist = true, $absoluteUrl = true) {
        global $db, $ab_path;

        $articleData = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER = ".mysql_real_escape_string($id));
        if($articleData != null) {


            $path = 'cache/marktplatz';

            $path .= '/anzeigen';
            if($createIfNotExist && !is_dir($ab_path.$path)) { self::createPath($ab_path.$path, true, $ab_path); }

            /*$path .= '/'.date("Y", $articleStampStart);
            if($createIfNotExist && !is_dir($path)) { self::createPath($path, true, $ab_path); }

            $path .= '/'.date("m", $articleStampStart);
            if($createIfNotExist && !is_dir($path)) { self::createPath($path, true, $ab_path); }*/

            $hash = md5($id);
            $hashElements = array(
                substr($hash, 0, 3),
                substr($hash, 3, 3),
                substr($hash, 6, 3)
            );

            foreach($hashElements as $key => $hashElement) {
                $path .= '/'.$hashElement;
                if($createIfNotExist && !is_dir($ab_path.$path)) { self::createPath($ab_path.$path, true, $ab_path); }
            }

            $path .= '/'.$id;
            if($createIfNotExist && !is_dir($ab_path.$path)) { self::createPath($ab_path.$path, true, $ab_path); }

            if($absoluteUrl) {
                return $ab_path.$path;
            } else {
                return $path;
            }
        } else {
            return null;
        }
    }
    
    /**
     * Get datatable object for the given category
     * @param int       $categoryId
     * @param ebiz_db   $db
     * @param int       $langval
     * @return Api_DataTable
     */
    public static function getDataTable($categoryId = null, ebiz_db $db = null, $langval = null) {        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        $s_lang = "de";
        foreach ($GLOBALS['lang_list'] as $langIndex => $langCurrent) {
            if ($langCurrent["BITVAL"] == $langval) {
                $s_lang = $langCurrent["ABBR"];
            }
        }
        /*
         * Get target article table by category
         */
        $articleTable = "ad_master";
        $articleTableId = null;
        if ($categoryId > 0) {
            $arTable = $db->fetch1($q = "
                    SELECT k.ID_KAT, k.KAT_TABLE, t.ID_TABLE_DEF
                    FROM kat k
                    LEFT JOIN table_def t ON k.KAT_TABLE=t.T_NAME
                    WHERE ID_KAT=" . (int)$categoryId);
            $articleTable = $arTable['KAT_TABLE'];
            $articleTableId = $arTable['ID_TABLE_DEF'];
        } else {
            $categoryId = $db->fetch_atom("SELECT ID_KAT FROM `kat` WHERE LFT=1 AND ROOT=1");
        }
        if ($articleTableId === null) {
            $articleTableId = $db->fetch_atom("SELECT ID_TABLE_DEF FROM table_def WHERE T_NAME = 'artikel_master'");
        }
        $masterTableShortcut = ($articleTable == "ad_master" ? "a" : "adt");
        $productTable = "hdb_table_artikel_master";
        $productTableId = "ID_HDB_TABLE_ARTIKEL_MASTER";

        /*
         * Create data table
         */
        $dataTable = new Api_DataTable($db, "ad_master", $masterTableShortcut);

        /*
         * Define joins
         */
        if ($articleTable != "ad_master") {
            $dataTable->addTableJoin($articleTable, "a", "LEFT JOIN", $masterTableShortcut . ".ID_AD_MASTER = a.`ID_" . strtoupper($articleTable) . "`");
            $productTable = "hdb_table_".$articleTable;
            $productTableId = "ID_HDB_TABLE_".strtoupper($articleTable);
        }
        $dataTable->addTableJoin($productTable, "p", "LEFT JOIN", $masterTableShortcut . ".FK_PRODUCT = p.`".mysql_real_escape_string($productTableId)."`");
        $dataTable->addTableJoin("country", "c", "LEFT JOIN", $masterTableShortcut . ".FK_COUNTRY = c.ID_COUNTRY");
        $dataTable->addTableJoinString("country", "c", "string", "sc", "LEFT JOIN", $langval);
        $dataTable->addTableJoin("kat", "k", "LEFT JOIN", $masterTableShortcut . ".FK_KAT = k.ID_KAT");
        $dataTable->addTableJoinString("kat", "k", "string_kat", "sk");
        $dataTable->addTableJoin("user", "u", "LEFT JOIN", $masterTableShortcut . ".FK_USER = u.ID_USER");
	    $dataTable->addTableJoin("vendor", "v", "LEFT JOIN", $masterTableShortcut . ".FK_USER = v.FK_USER");
        $dataTable->addTableJoin("manufacturers", "m", "LEFT JOIN", $masterTableShortcut . ".FK_MAN = m.ID_MAN");
        $dataTable->addTableJoin("ad_search", null, "JOIN", $masterTableShortcut . ".ID_AD_MASTER=ad_search.FK_AD AND ad_search.lang='" . mysql_real_escape_string($s_lang) . "'");
        $dataTable->addTableJoin("ad_variant", "av", "LEFT JOIN", $masterTableShortcut . ".ID_AD_MASTER = av.FK_AD_MASTER");
        $dataTable->addTableJoin("ad_images", "imgDef", "LEFT JOIN", $masterTableShortcut . ".ID_AD_MASTER = imgDef.FK_AD AND imgDef.IS_DEFAULT=1");
        /*
         * Define fields
         */
        // Field for count queries
        $dataTable->addField(null, null, "COUNT(DISTINCT " . $masterTableShortcut . ".ID_AD_MASTER)", "RESULT_COUNT");
        $dataTable->addField(null, null, "COUNT(" . $masterTableShortcut . ".ID_AD_MASTER)", "RESULT_COUNT_FAST");
        // Field for random sorting
        $dataTable->addField(null, null, "RAND()", "RANDOM", true);
        // Field for sorting by number of comments
        $dataTable->addField(null, null, "(SELECT COUNT(*) FROM `comment` WHERE `TABLE`='ad_master' AND FK=`".$masterTableShortcut."`.`ID_AD_MASTER` AND IS_CONFIRMED=1 AND IS_PUBLIC=1)", "COMMENTS", true);
        // Fulltext search
        $dataTable->addField(null, null, "(MATCH (ad_search.STEXT) AGAINST ('$1$'))", "SEARCH_RELEVANCE", true);
        // Master fields
        $dataTable->addFieldsFromDb($masterTableShortcut);
        $dataTable->setFieldSortable($masterTableShortcut, "B_TOP_LIST", true);
        $dataTable->setFieldSortable($masterTableShortcut, "STAMP_START", true);
        $dataTable->setFieldSortable($masterTableShortcut, "ID_AD_MASTER", true);
        $dataTable->setFieldSortable($masterTableShortcut, "PREIS", true);
        $dataTable->setFieldSortable($masterTableShortcut, "PRODUKTNAME", true);
        $dataTable->setFieldSortable($masterTableShortcut, "ZIP", true);
        $dataTable->setFieldSortable($masterTableShortcut, "AD_LIKES", true);
        $dataTable->setFieldSortable(null, "COMMENTS", true);
        //$dataTable->addField($masterTableShortcut, "AD_TABLE");
        //$dataTable->addField($masterTableShortcut, "SER_FIELDS");
        // Product DB fields
        $dataTable->addFieldsFromDb("p");
        $dataTable->addField("p", $productTableId, null, "ID_PRODUCT");
        // Special fields
        $dataTable->addField(null, null, "IFNULL(p.".$productTableId.",".$masterTableShortcut.".ID_AD_MASTER)", "ID_PRODUCT_GROUP", true);
        $dataTable->addField(null, null, "IFNULL(".$masterTableShortcut.".FK_MAN,".$masterTableShortcut.".ID_AD_MASTER)", "ID_MAN_GROUP_MASTER", true);
        $dataTable->addField(null, null, "IFNULL(".$masterTableShortcut.".FK_PRODUCT,".$masterTableShortcut.".ID_AD_MASTER)", "ID_PRODUCT_GROUP_MASTER", true);
        $dataTable->addField(null, null, "MIN(NULLIF(".$masterTableShortcut.".PREIS, 0))", "PREIS_MIN");
        // Joined fields
	    $dataTable->addField("u", "NAME", NULL, "USER_NAME");
	    $dataTable->addField("v", "NAME", NULL, "VENDOR_NAME");
	    $dataTable->addField(NULL, NULL, "CONCAT('cache/vendor/logo/',v.LOGO)", "VENDOR_LOGO");
        $dataTable->addField("m", "NAME", NULL, "MANUFACTURER");
        $dataTable->addField("imgDef", "SRC", NULL, "IMG_DEFAULT_SRC");
        $dataTable->addField("imgDef", "SRC_THUMB", NULL, "IMG_DEFAULT_SRC_THUMB");
        // Define multilingual fields
        $dataTable->addField("sk", "V1", NULL, "KAT");
        $dataTable->addField("c", "CODE", NULL, "COUNTRY_CODE");
        $dataTable->addField("sc", "V1", NULL, "COUNTRY");
        // Define special fields
        $dataTable->addField(null, null, "DATEDIFF(NOW()," . $masterTableShortcut . ".STAMP_START)", "RUNTIME_DAYS");
        $dataTable->addField(null, null, "DATEDIFF(NOW()," . $masterTableShortcut . ".STAMP_START)", "RUNTIME_DAYS_GONE");
        $dataTable->addField(null, null, "(SELECT AMOUNT FROM `comment_stats` WHERE `TABLE` = 'ad_master' AND FK=" . $masterTableShortcut . ".ID_AD_MASTER)", "COUNT_COMMENTS");

        // Define master fields
        foreach (self::$articleFieldsMaster as $masterFieldIndex => $masterFieldName) {
            $masterFieldAlias = (array_key_exists($masterFieldName, self::$articleFieldsMasterAliases) ? self::$articleFieldsMasterAliases[$masterFieldName] : $masterFieldName);
            $dataTable->addField(
                $masterTableShortcut, $masterFieldName, NULL, $masterFieldAlias,                // Basic field definition
                in_array($masterFieldName, self::$articleFieldsMasterSortable),                 // Use for sorting
                in_array($masterFieldName, self::$articleFieldsMasterIndex)                     // Use for unique index
            );
        }
        // Define article fields
        $variantIndex = 0;
        $arSearchFields = array_merge(
            self::getSearchFieldsMaster(),
            self::getSearchFields($articleTableId, $categoryId, $db, $langval)
        );
        foreach ($arSearchFields as $searchFieldIndex => $searchField) {
            $searchFieldName = $searchField["F_NAME"];
            $searchFieldRequire = ($articleTable == "ad_master" ? array() : array("a"));
            // Skip master fields
            if (in_array($searchFieldName, self::$articleFieldsMaster) && !$searchField["IS_MASTER"]) {
                continue;
            }
            // Add field
            //$dataTable->addField("a", $searchFieldName, NULL, $searchFieldName);
            // Do not add where clauses for special (search)fields
            if ($searchField["IS_SPECIAL"]) {
                continue;
            }
            // Add where clause(s)
            switch ($searchField["F_TYP"]) {
                case 'TEXT':
                    $dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, 'a.`' . mysql_real_escape_string($searchFieldName) . '` LIKE "%$1$%"', $searchFieldRequire);
                    break;
                case 'DATE':
                case 'DATE_MONTH':
                case 'DATE_YEAR':
                case 'INT':
                case 'FLOAT':
                    if ($searchField["B_SEARCH"] == 1) {
                        $dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, 'a.`' . mysql_real_escape_string($searchFieldName) . '`="$1$"', $searchFieldRequire);
                    } else if ($searchField["B_SEARCH"] == 2) {
                        $dataTable->addWhereCondition("_RANGE_" . $searchFieldName, 'a.`' . mysql_real_escape_string($searchFieldName) . '` BETWEEN "$1$" AND "$2$"', $searchFieldRequire);
                        $dataTable->addWhereCondition("_GT_EQ_" . $searchFieldName, 'a.`' . mysql_real_escape_string($searchFieldName) . '` >= "$1$"', $searchFieldRequire);
                        $dataTable->addWhereCondition("_LT_EQ_" . $searchFieldName, 'a.`' . mysql_real_escape_string($searchFieldName) . '` <= "$1$"', $searchFieldRequire);
                    }
                    break;
                case 'VARIANT':
                    require_once $GLOBALS['ab_path'] . 'sys/lib.ad_variants.php';
                    // Add variant joins
                    $variantIndex++;
                    $variantIdent = "av2lv" . $variantIndex;
                    $dataTable->addTableJoin("ad_variant2liste_values", $variantIdent, "LEFT JOIN", "av.ID_AD_VARIANT = " . $variantIdent . ".FK_AD_VARIANT");
                    $searchFieldRequire[] = "av";
                    $searchFieldRequire[] = $variantIdent;
                    // Add variant condition
                    $variantCondition =
                        $variantIdent . '.F_NAME = "' . mysql_real_escape_string($searchFieldName) . '" AND ' .
                        $variantIdent . '.FK_LISTE_VALUES = "$1$" AND ' .
                        'av.MENGE > 0 AND av.STATUS = "' . AdVariantsManagement::STATUS_ENABLED . '"';
                    $variantConditionMulti =
                        $variantIdent . '.F_NAME = "' . mysql_real_escape_string($searchFieldName) . '" AND ' .
                        $variantIdent . '.FK_LISTE_VALUES IN ($1$) AND ' .
                        'av.MENGE > 0 AND av.STATUS = "' . AdVariantsManagement::STATUS_ENABLED . '"';
                    $dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, $variantCondition, $searchFieldRequire);
                    $dataTable->addWhereCondition("_IN_" . $searchFieldName, $variantConditionMulti, $searchFieldRequire);
                    break;
                case 'LIST':
                    $dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, 'a.`' . mysql_real_escape_string($searchFieldName) . '`="$1$"', $searchFieldRequire);
                    $dataTable->addWhereCondition("_IN_" . $searchFieldName, 'a.`' . mysql_real_escape_string($searchFieldName) . '` IN ($1$)', $searchFieldRequire);
                    break;
                case 'MULTICHECKBOX':
                    $dataTable->addWhereCondition("_MULTI_" . $searchFieldName, 'a.`' . mysql_real_escape_string($searchFieldName) . '` LIKE "%x$1$x%"', $searchFieldRequire, "OR");
                    break;
                case 'MULTICHECKBOX_AND':
                    $dataTable->addWhereCondition("_MULTI_" . $searchFieldName, 'a.`' . mysql_real_escape_string($searchFieldName) . '` LIKE "%x$1$x%"', $searchFieldRequire, "AND");
                    break;
            }
        }
        /*
         * Master fields
         */
        $dataTable->addWhereCondition("_EQUAL_MENGE", $masterTableShortcut . ".MENGE='$1$'");
        $dataTable->addWhereCondition("_RANGE_MENGE", $masterTableShortcut . ".MENGE BETWEEN '$1$' AND '$2$'");
        $dataTable->addWhereCondition("_GT_EQ_MENGE", $masterTableShortcut . ".MENGE >= '$1$'");
        $dataTable->addWhereCondition("_LT_EQ_MENGE", $masterTableShortcut . ".MENGE <= '$1$'");
        $dataTable->addWhereCondition("_EQUAL_EAN", $masterTableShortcut . ".EAN='$1$'");
        /*
         * Define core conditions
         */
        $dataTable->addWhereCondition("ID_AD_MASTER", $masterTableShortcut.".ID_AD_MASTER='$1$'");
        $dataTable->addWhereCondition("ID_AD_MASTER_IN", $masterTableShortcut.".ID_AD_MASTER IN $1$");
        $dataTable->addWhereCondition("ID_AD_MASTER_NOT_IN", $masterTableShortcut.".ID_AD_MASTER NOT IN $1$");
        $dataTable->addWhereCondition("FK_KAT", $masterTableShortcut . ".FK_KAT IN $1$");
        $dataTable->addWhereCondition("FK_COUNTRY", $masterTableShortcut . ".FK_COUNTRY='$1$'");
        $dataTable->addWhereCondition("FK_MAN", $masterTableShortcut . ".FK_MAN='$1$'");
        $dataTable->addWhereCondition("FK_PRODUCT", $masterTableShortcut . ".FK_PRODUCT='$1$'");
        $dataTable->addWhereCondition("FK_USER", $masterTableShortcut . ".FK_USER='$1$'");
        $dataTable->addWhereCondition("BF_CONSTRAINTS", "(".$masterTableShortcut . ".BF_CONSTRAINTS & $1$)=$1$");
        $dataTable->addWhereCondition("B_PSEUDOPREIS_DISCOUNT", $masterTableShortcut . ".B_PSEUDOPREIS_DISCOUNT=$1$");
        $dataTable->addWhereCondition("SALE_NO_REQUEST", $masterTableShortcut.".VERKAUFSOPTIONEN!=5");
        $dataTable->addWhereCondition("TOP", $masterTableShortcut . ".B_TOP > 0");
        $dataTable->addWhereCondition("TOP_IN", $masterTableShortcut . ".B_TOP IN $1$");
        $dataTable->addWhereCondition("ONLINE", "(" . $masterTableShortcut . ".STATUS IN (1,5,9,13) AND " . $masterTableShortcut . ".DELETED=0)");
        $dataTable->addWhereCondition("SEARCH_TEXT_ID", "(".$masterTableShortcut.".ID_AD_MASTER='$1$' OR ".$masterTableShortcut.".NOTIZ='$1$')");
        $dataTable->addWhereCondition("SEARCH_TEXT_FULL", "(MATCH (ad_search.STEXT) AGAINST ('$1$'))", array("ad_search"));
        $dataTable->addWhereCondition("SEARCH_TEXT_SHORT", "((m.NAME LIKE '%$1$%') OR (" . $masterTableShortcut . ".PRODUKTNAME LIKE '%$1$%'))", array("m"));

        //////////////// IMENSO  /////////////////////////////
        $dataTable->addWhereCondition("SEARCH_TEXT_LIKE", "(" . $masterTableShortcut . ".PRODUKTNAME LIKE '%$1$%')", array("ad_search"));

        $dataTable->addWhereCondition("GEO_RECT", $masterTableShortcut . ".LATITUDE BETWEEN '$1$' AND '$2$' AND " . $masterTableShortcut . ".LONGITUDE BETWEEN '$3$' AND '$4$'");
        $dataTable->addWhereCondition("GEO_CIRCLE", "
                (
                    6368 * SQRT(ABS(2*(1-cos(RADIANS(" . $masterTableShortcut . ".LATITUDE)) *
              	    cos($1$) * (sin(RADIANS(" . $masterTableShortcut . ".LONGITUDE)) *
              	    sin($2$) + cos(RADIANS(" . $masterTableShortcut . ".LONGITUDE)) *
              	    cos($2$)) - sin(RADIANS(" . $masterTableShortcut . ".LATITUDE)) * sin($1$))))
              	) <= $3$");
        // Special conditions
        $dataTable->addWhereCondition("_ADS_LIKE_FULL", "(a.PRODUKTNAME!='' AND a.PRODUKTNAME LIKE '$1$')".
            " OR ((a.STREET!='' AND a.ZIP!='' AND a.CITY!='' AND a.FK_COUNTRY!=0)".
            "    AND (a.STREET LIKE '$2$' AND a.ZIP LIKE '$3$' AND a.CITY LIKE '$4$' AND a.FK_COUNTRY LIKE '$5$'))");
        
        // Plugin event
        $eventMarketViewParams = new Api_Entities_EventParamContainer(array(
            "articleTable"          => $articleTable,
            "articleMasterShortcut" => $masterTableShortcut,
            "categoryId"            => $categoryId,
            "dataTable"		        => $dataTable
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_GET_DATATABLE, $eventMarketViewParams);
        
        return $dataTable;
    }
    
    
    /**
     * Get all master search fields that behave like regular search fields
     * @return array
     */
    public static function getProductSearchFieldsMaster() {
        return array(
            // Nothing yet
        );
    }
    
    /**
     * Get all search fields for the given table/category
     * @param int       $tableDefId
     * @param int       $categoryId
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return array
     */
    public static function getProductSearchFields($tableDefId, $categoryId, ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Query result
        return $db->fetch_table("
      			SELECT f.ID_FIELD_DEF, f.F_NAME, f.IS_SPECIAL, f.F_TYP, f.B_SEARCH, f.FK_LISTE, s.V1, s.V2, s.T1
      			FROM field_def f
      			LEFT JOIN `string_field_def` s on s.S_TABLE='field_def' and s.FK=f.ID_FIELD_DEF
      			  and s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
      			LEFT JOIN `kat2field` kf ON kf.FK_FIELD=f.ID_FIELD_DEF AND kf.FK_KAT=" . $categoryId . "
      			WHERE f.FK_TABLE_DEF=" . $tableDefId . " AND f.B_SEARCH IN(1,2) AND (f.IS_MASTER=1 OR kf.B_ENABLED=1) AND B_HDB_ENABLED=1"
        );
    }
    
    /**
     * Get product datatable object for the given category
     * @param int       $categoryId
     * @param ebiz_db   $db
     * @param int       $langval
     * @return Api_DataTable
     */
    public static function getProductDataTable($categoryId = null, ebiz_db $db = null, $langval = null) {        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        $s_lang = "de";
        foreach ($GLOBALS['lang_list'] as $langIndex => $langCurrent) {
            if ($langCurrent["BITVAL"] == $langval) {
                $s_lang = $langCurrent["ABBR"];
            }
        }
        /*
         * Get target article table by category
         */
        $articleTable = "ad_master";
        $articleTableId = null;
        if ($categoryId > 0) {
            $arTable = $db->fetch1($q = "
                    SELECT k.ID_KAT, k.KAT_TABLE, t.ID_TABLE_DEF
                    FROM kat k
                    LEFT JOIN table_def t ON k.KAT_TABLE=t.T_NAME
                    WHERE ID_KAT=" . (int)$categoryId);
            $articleTable = $arTable['KAT_TABLE'];
            $articleTableId = $arTable['ID_TABLE_DEF'];
        } else {
            $categoryId = $db->fetch_atom("SELECT ID_KAT FROM `kat` WHERE LFT=1 AND ROOT=1");
        }
        if ($articleTableId === null) {
            $articleTableId = $db->fetch_atom("SELECT ID_TABLE_DEF FROM table_def WHERE T_NAME = 'artikel_master'");
        }
        $productTableShortcut = "p";
        $productTable = "hdb_table_artikel_master";
        $productTableId = "ID_HDB_TABLE_ARTIKEL_MASTER";
        $articleTableIdField = "ID_AD_MASTER";
        if ($articleTable != "ad_master") {
            $productTable = "hdb_table_".$articleTable;
            $productTableId = "ID_HDB_TABLE_".strtoupper($articleTable);
            $articleTableIdField = "ID_".strtoupper($articleTable);
        }

        /*
         * Create data table
         */
        $dataTable = new Api_DataTable($db, $productTable, $productTableShortcut);

        /*
         * Define joins
         */
        $dataTable->addTableJoin("kat", "k", "LEFT JOIN", $productTableShortcut . ".FK_KAT = k.ID_KAT");
        $dataTable->addTableJoinString("kat", "k", "string_kat", "sk");
        $dataTable->addTableJoin("manufacturers", "m", "LEFT JOIN", $productTableShortcut . ".FK_MAN = m.ID_MAN");
        $dataTable->addTableJoin($articleTable, "a", "LEFT JOIN", 
            $productTableShortcut . ".FK_MAN = a.FK_MAN AND ".$productTableShortcut . ".".$productTableId." = a.FK_PRODUCT AND a.DELETED=0 AND a.STATUS IN (1,5,9,13)");
        /*
         * Define fields
         */
        // Field for count queries
        $dataTable->addField(null, null, "COUNT(DISTINCT " . $productTableShortcut . ".".$productTableId.")", "RESULT_COUNT");
        $dataTable->addField(null, null, "COUNT(" . $productTableShortcut . ".".$productTableId.")", "RESULT_COUNT_FAST");
        $dataTable->addField("a", null, "COUNT(a.".$articleTableIdField.")", "ARTICLE_COUNT", true);
        // Field for random sorting
        $dataTable->addField(null, null, "RAND()", "RANDOM", true);
        // Article fields
        $dataTable->addField("a", $articleTableIdField, null, "ID_AD", true);
        $dataTable->addField(null, null, "MIN(NULLIF(PREIS, 0))", "PREIS_MIN");
        // Master fields
        $dataTable->addField($productTableShortcut, $productTableId, null, "ID_PRODUCT", true);
        $dataTable->addFieldsFromDb($productTableShortcut);
        $dataTable->setFieldSortable($productTableShortcut, "PRODUKTNAME", true);
        // Joined fields
        $dataTable->addField("m", "NAME", NULL, "MANUFACTURER");
        // Define multilingual fields
        $dataTable->addField("sk", "V1", NULL, "KAT");

        // Define article fields
        $variantIndex = 0;
        $arSearchFields = array_merge(
            self::getProductSearchFieldsMaster(),
            self::getProductSearchFields($articleTableId, $categoryId, $db, $langval)
        );
        foreach ($arSearchFields as $searchFieldIndex => $searchField) {
            $searchFieldName = $searchField["F_NAME"];
            $searchFieldRequire = ($articleTable == "ad_master" ? array() : array("a"));
            // Skip master fields
            if (in_array($searchFieldName, self::$articleFieldsMaster) && !$searchField["IS_MASTER"]) {
                continue;
            }
            // Add field
            //$dataTable->addField("a", $searchFieldName, NULL, $searchFieldName);
            // Do not add where clauses for special (search)fields
            if ($searchField["IS_SPECIAL"]) {
                continue;
            }
            // Add where clause(s)
            switch ($searchField["F_TYP"]) {
                case 'TEXT':
                    $dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, $productTableShortcut.'.`' . mysql_real_escape_string($searchFieldName) . '` LIKE "%$1$%"', $searchFieldRequire);
                    break;
                case 'DATE':
                case 'DATE_MONTH':
                case 'DATE_YEAR':
                case 'INT':
                case 'FLOAT':
                    if ($searchField["B_SEARCH"] == 1) {
                        $dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, $productTableShortcut.'.`' . mysql_real_escape_string($searchFieldName) . '`="$1$"', $searchFieldRequire);
                    } else if ($searchField["B_SEARCH"] == 2) {
                        $dataTable->addWhereCondition("_RANGE_" . $searchFieldName, $productTableShortcut.'.`' . mysql_real_escape_string($searchFieldName) . '` BETWEEN "$1$" AND "$2$"', $searchFieldRequire);
                        $dataTable->addWhereCondition("_GT_EQ_" . $searchFieldName, $productTableShortcut.'.`' . mysql_real_escape_string($searchFieldName) . '` >= "$1$"', $searchFieldRequire);
                        $dataTable->addWhereCondition("_LT_EQ_" . $searchFieldName, $productTableShortcut.'.`' . mysql_real_escape_string($searchFieldName) . '` <= "$1$"', $searchFieldRequire);
                    }
                    break;
                    /** TODO: Variants
                case 'VARIANT':
                    require_once $GLOBALS['ab_path'] . 'sys/lib.ad_variants.php';
                    // Add variant joins
                    $variantIndex++;
                    $variantIdent = "av2lv" . $variantIndex;
                    $dataTable->addTableJoin("ad_variant2liste_values", $variantIdent, "LEFT JOIN", "av.ID_AD_VARIANT = " . $variantIdent . ".FK_AD_VARIANT");
                    $searchFieldRequire[] = "av";
                    $searchFieldRequire[] = $variantIdent;
                    // Add variant condition
                    $variantCondition =
                        $variantIdent . '.F_NAME = "' . mysql_real_escape_string($searchFieldName) . '" AND ' .
                        $variantIdent . '.FK_LISTE_VALUES = "$1$" AND ' .
                        'av.MENGE > 0 AND av.STATUS = "' . AdVariantsManagement::STATUS_ENABLED . '"';
                    $variantConditionMulti =
                        $variantIdent . '.F_NAME = "' . mysql_real_escape_string($searchFieldName) . '" AND ' .
                        $variantIdent . '.FK_LISTE_VALUES IN ($1$) AND ' .
                        'av.MENGE > 0 AND av.STATUS = "' . AdVariantsManagement::STATUS_ENABLED . '"';
                    $dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, $variantCondition, $searchFieldRequire);
                    $dataTable->addWhereCondition("_IN_" . $searchFieldName, $variantConditionMulti, $searchFieldRequire);
                    break;
                     */
                case 'LIST':
                    $dataTable->addWhereCondition("_EQUAL_" . $searchFieldName, $productTableShortcut.'.`' . mysql_real_escape_string($searchFieldName) . '`="$1$"', $searchFieldRequire);
                    $dataTable->addWhereCondition("_IN_" . $searchFieldName, $productTableShortcut.'.`' . mysql_real_escape_string($searchFieldName) . '` IN ($1$)', $searchFieldRequire);
                    break;
                case 'MULTICHECKBOX':
                    $dataTable->addWhereCondition("_MULTI_" . $searchFieldName, $productTableShortcut.'.`' . mysql_real_escape_string($searchFieldName) . '` LIKE "%x$1$x%"', $searchFieldRequire, "OR");
                    break;
                case 'MULTICHECKBOX_AND':
                    $dataTable->addWhereCondition("_MULTI_" . $searchFieldName, $productTableShortcut.'.`' . mysql_real_escape_string($searchFieldName) . '` LIKE "%x$1$x%"', $searchFieldRequire, "AND");
                    break;
            }
        }
        /*
         * Master fields
         */
        $dataTable->addWhereCondition("_EQUAL_EAN", $productTableShortcut . ".EAN='$1$'");
        /*
         * Define core conditions
         */
        $dataTable->addWhereCondition("ID_PRODUCT", $productTableShortcut.".".$productTableId."='$1$'");
        $dataTable->addWhereCondition("FK_PRODUCT", $productTableShortcut.".".$productTableId."='$1$'");
        $dataTable->addWhereCondition("FK_KAT", $productTableShortcut . ".FK_KAT IN $1$");
        $dataTable->addWhereCondition("FK_MAN", $productTableShortcut . ".FK_MAN='$1$'");
        
        // Plugin event
        $eventMarketViewParams = new Api_Entities_EventParamContainer(array(
            "articleTable"          => $articleTable,
            "categoryId"            => $categoryId,
            "dataTable"		        => $dataTable
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_GET_DATATABLE_PRODUCT, $eventMarketViewParams);
        
        return $dataTable;
    }

    /**
     * Get a DataTableQuery object for the given search
     * @param string    $searchHash
     * @param array     $searchParamsMore
     * @param ebiz_db   $db
     * @param int       $langval
     * @return Api_DataTableQuery
     */
    public static function getQueryByHash($searchHash, $searchParamsMore = array(), ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Get search parameters
        $arSearch = $db->fetch1("
            SELECT S_STRING, S_WHERE, S_HAVING
            FROM `searchstring`
            WHERE `QUERY`='".mysql_real_escape_string($searchHash)."'");
        $searchData = @unserialize($arSearch["S_STRING"]);
        if (!is_array($searchData)) {
            $searchData = $searchParamsMore;
        } else {
            $searchData = array_merge($searchData, $searchParamsMore);
        }
        return self::getQueryByParams($searchData, $db, $langval);
    }

    /**
     * Get a DataTableQuery object for the given search parameters
     * @param array     $searchData
     * @param ebiz_db   $db
     * @param int       $langval
     * @return Api_DataTableQuery
     */
    public static function getQueryByParams($searchData, ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        $s_lang = $GLOBALS["s_lang"];
        foreach ($GLOBALS["lang_list"] as $langAbbr => $arLang) {
            if ($arLang["BITVAL"] == $langval) {
                $s_lang = $langAbbr;
                break;
            }
        }
        // Get category
        include_once "sys/lib.shop_kategorien.php";
        $katTree = new TreeCategories("kat", 1);
        $searchCategory = null;
        if($searchData['FK_KAT'] > 0) {
            $searchCategory = (int)$searchData['FK_KAT'];
        } else {
            $searchCategory = $katTree->tree_get_parent();
        }
        $row_kat = $katTree->element_read($searchCategory);
        $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$searchCategory);
        unset($searchData['FK_KAT']);
        // Create DataTable and Query object
        $dataTable = self::getDataTable($searchCategory, $db, $langval);
        $dataTableQuery = $dataTable->createQuery();
        
		// Plugin event
		$eventMarketListParams = new Api_Entities_EventParamContainer(array(
			"language"			=> $s_lang,
			"idCategory"		=> $searchCategory,
			"table"				=> $kat_table,
			"searchActive"		=> true,
			"searchData"		=> $searchData,
			"query"				=> $dataTableQuery,
			"queryMasterPrefix"	=> ($kat_table == "ad_master" ? "a" : "adt")
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_SEARCH_QUERY, $eventMarketListParams);
		if ($eventMarketListParams->isDirty()) {
		    $dataTableQuery = $eventMarketListParams->getParam("query");
			$searchData = $eventMarketListParams->getParam("searchData");
		}
        
        // Add where conditions (special ones)
        if (($searchCategory > 0) && ($row_kat["PARENT"] > 0)) {
            // Category condition
            $ids_kats = $db->fetch_nar("
                SELECT ID_KAT FROM `kat`
                WHERE (LFT >= ".$row_kat["LFT"].") AND (RGT <= ".$row_kat["RGT"].") AND (ROOT = ".$row_kat["ROOT"].")");
            $ids_kats = "(".implode(",",array_keys($ids_kats)).")";
            $dataTableQuery->addWhereCondition("FK_KAT", $ids_kats);
        }
        if (array_key_exists("FK_MAN", $searchData) && !empty($searchData["FK_MAN"])) {
            // Category condition
            $dataTableQuery->addWhereCondition("FK_MAN", $searchData["FK_MAN"]);
        }
        if (array_key_exists("PRODUKTNAME", $searchData) && !empty($searchData["PRODUKTNAME"])) {
            $ad_searchEngine = $db->fetch1("SHOW TABLE STATUS LIKE 'ad_search'")["Engine"];
            if($ad_searchEngine == "InnoDB") {
                // InnoDB
                $ft_min_word = (int)$db->fetch_atom("SHOW GLOBAL VARIABLES LIKE 'innodb_ft_min_token_size'", 2);
                if($ft_min_word < 1) {
                    // Fallback
                    $ft_min_word = 3;
                }
            } else {
                // MyISAM
                $ft_min_word = (int)$db->fetch_atom("SHOW GLOBAL VARIABLES LIKE 'ft_min_word_len'", 2);
                if($ft_min_word < 1) {
                    // Fallback
                    $ft_min_word = 4;
                }
            }
		    if (strlen($searchData["PRODUKTNAME"]) >= $ft_min_word) {
                $arLikeWords = [];
                $fulltextSearchQuery = generateFulltextSearchstring($searchData["PRODUKTNAME"], $arLikeWords, $ft_min_word);
                ///////////////  IMENSO  /////////////////////////////
                if (!empty($fulltextSearchQuery)) {
                    $dataTableQuery->addWhereCondition("SEARCH_TEXT_LIKE", $searchData["PRODUKTNAME"]);
                    //$dataTableQuery->addWhereCondition("SEARCH_TEXT_FULL", $fulltextSearchQuery);
                }
                if (!empty($arLikeWords)) {
                    foreach ($arLikeWords as $word) {
                        $dataTableQuery->addWhereCondition("SEARCH_TEXT_SHORT", $word);
                    }
                }
            } else {
                $dataTableQuery->addWhereCondition("SEARCH_TEXT_SHORT", $searchData["PRODUKTNAME"]);
            }
            unset($searchData["PRODUKTNAME"]);
        }
        if (!empty($searchData['LU_UMKREIS']) && !empty($searchData['LONGITUDE']) && !empty($searchData['LATITUDE'])) {
            if (is_array($searchData['LONGITUDE']) && is_array($searchData['LATITUDE'])) {
                // Umkreissuche (ausschnitt/rechteck)
                $latMin = min($searchData['LATITUDE'][0], $searchData['LATITUDE'][1]);
                $latMax = max($searchData['LATITUDE'][0], $searchData['LATITUDE'][1]);
                $lngMin = min($searchData['LONGITUDE'][0], $searchData['LONGITUDE'][1]);
                $lngMax = max($searchData['LONGITUDE'][0], $searchData['LONGITUDE'][1]);
                $dataTableQuery->addWhereCondition("GEO_RECT", array($latMin, $latMax, $lngMin, $lngMax));
            } else {
                // Umkreissuche (klassisch/kreis)
                $dataTableQuery->addWhereCondition("GEO_CIRCLE", array(
                    ($searchData['LATITUDE'] / 180 * M_PI), ($searchData['LONGITUDE'] / 180 * M_PI), 
                    $db->fetch_atom("select `value` from lookup where ID_LOOKUP =" . $searchData['LU_UMKREIS'])
                ));
            }
            unset($searchData["LATITUDE"]);
            unset($searchData["LONGITUDE"]);
            unset($searchData["LU_UMKREIS"]);
            unset($searchData["FK_COUNTRY"]);
        } else if (!empty($searchData['LU_UMKREIS']) && (!empty($searchData["ZIP"]) || !empty($searchData["CITY"]))) {
            // Umkreissuche (klassisch/kreis)
            $countryAsName = $db->fetch_atom("SELECT V1 FROM `string` WHERE S_TABLE='country' AND BF_LANG=".$langval." AND FK=".(int)$searchData["FK_COUNTRY"]);
            $geoResult = Geolocation_Generic::getGeolocationCached($searchData["STREET"], $searchData["ZIP"], $searchData["CITY"], $countryAsName);
            if (is_array($geoResult)) {
                $dataTableQuery->addWhereCondition("GEO_CIRCLE", array(
                    ($geoResult['LATITUDE'] / 180 * M_PI), ($geoResult['LONGITUDE'] / 180 * M_PI), 
                    $db->fetch_atom("select `value` from lookup where ID_LOOKUP =" . $searchData['LU_UMKREIS'])
                ));
            }
        } else if (!empty($searchData['FK_COUNTRY'])) {
            // Search for country?
            $dataTableQuery->addWhereCondition("FK_COUNTRY", (int)$searchData["FK_COUNTRY"]);
            unset($searchData["FK_COUNTRY"]);
        }
        if (!empty($searchData['BF_CONSTRAINTS'])) {
            $constraintsFilter = 0;
            foreach ($searchData['BF_CONSTRAINTS'] as $constraintsValue => $constraintsStatus) {
                $constraintsFilter += (int)$constraintsValue;
            }
            $dataTableQuery->addWhereCondition("BF_CONSTRAINTS", $constraintsFilter);
            unset($searchData["BF_CONSTRAINTS"]);
        }
        $dataTableQuery->addWhereCondition("ONLINE");
        // Add where conditions (default)
        foreach ($searchData as $searchDataKey => $searchDataValue) {
            if (($searchDataValue === null) || ($searchDataValue == "")) {
                // Skip empty search fields
                continue;
            }
            $whereAdded = $dataTableQuery->addWhereCondition($searchDataKey, (!is_array($searchDataValue) ? array($searchDataValue) : $searchDataValue));
            if (!$whereAdded) {
                // No matching condition found, try article field conditions
                if (is_array($searchDataValue)) {
                    // Search value is array, do range search
                    $searchFrom = "";
                    $searchTo = "";
                    if (array_key_exists("VON", $searchDataValue) && array_key_exists("BIS", $searchDataValue)) {
                        $searchFrom = $searchDataValue["VON"];
                        $searchTo = $searchDataValue["BIS"];
                    } else if (array_key_exists(0, $searchDataValue) && array_key_exists(1, $searchDataValue)) {
                        $searchFrom = $searchDataValue[0];
                        $searchTo = $searchDataValue[1];
                    } else if (array_key_exists("VON", $searchDataValue)) {
                        $searchFrom = $searchDataValue["VON"];
                    } else if (array_key_exists(0, $searchDataValue)) {
                        $searchFrom = $searchDataValue[0];
                    } else if (array_key_exists("BIS", $searchDataValue)) {
                        $searchTo = $searchDataValue["BIS"];
                    }
                    if (($searchFrom != "") && ($searchTo != "")) {
                        $whereAdded = $dataTableQuery->addWhereCondition("_RANGE_" . $searchDataKey, array($searchFrom, $searchTo));
                    } else if ($searchFrom != "") {
                        $whereAdded = $dataTableQuery->addWhereCondition("_GT_EQ_".$searchDataKey, array($searchFrom));
                    } else if ($searchTo != "") {
                        $whereAdded = $dataTableQuery->addWhereCondition("_LT_EQ_".$searchDataKey, array($searchTo));
                    } 
                    if (!$whereAdded) {
                        // Try to use an "in" condition
                        $valueListEscaped = array();
                        foreach ($searchDataValue as $searchDataValueIndex => $searchDataValueContent) {
                            $valueListEscaped[] = '"'.mysql_real_escape_string($searchDataValueContent).'"';
                        }
                        $whereAdded = $dataTableQuery->addWhereCondition("_IN_".$searchDataKey, array( implode(", ", $valueListEscaped) ));
                    }
                    if (!$whereAdded) {
                        // Try to add as multiple where conditions
                        $searchValueMulti = array();
                        foreach ($searchDataValue as $searchDataValueIdx => $searchDataValueCur) {
                            $searchValueMulti[] = array($searchDataValueCur);
                        }
                        $whereAdded = $dataTableQuery->addWhereCondition("_MULTI_".$searchDataKey, $searchValueMulti);
                    }
                } else {
                    // Search value is string/number
                    $whereAdded = $dataTableQuery->addWhereCondition("_EQUAL_".$searchDataKey, array($searchDataValue));
                }
            }
        }
        return $dataTableQuery;
    }

    /**
     * Get a DataTableQuery object for the given search parameters
     * @param array     $searchData
     * @param ebiz_db   $db
     * @param int       $langval
     * @return Api_DataTableQuery
     */
    public static function getProductQueryByParams($searchData, ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        $s_lang = $GLOBALS["s_lang"];
        foreach ($GLOBALS["lang_list"] as $langAbbr => $arLang) {
            if ($arLang["BITVAL"] == $langval) {
                $s_lang = $langAbbr;
                break;
            }
        }
        // Get category
        include_once "sys/lib.shop_kategorien.php";
        $katTree = new TreeCategories("kat", 1);
        $searchCategory = null;
        if($searchData['FK_KAT'] > 0) {
            $searchCategory = (int)$searchData['FK_KAT'];
        } else {
            $searchCategory = $katTree->tree_get_parent();
        }
        $row_kat = $katTree->element_read($searchCategory);
        $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$searchCategory);
        unset($searchData['FK_KAT']);
        // Create DataTable and Query object
        $dataTable = self::getProductDataTable($searchCategory, $db, $langval);
        $dataTableQuery = $dataTable->createQuery();
                
        // Add where conditions (special ones)
        if (($searchCategory > 0) && ($row_kat["PARENT"] > 0)) {
            // Category condition
            $ids_kats = $db->fetch_nar("
                SELECT ID_KAT FROM `kat`
                WHERE (LFT >= ".$row_kat["LFT"].") AND (RGT <= ".$row_kat["RGT"].") AND (ROOT = ".$row_kat["ROOT"].")");
            $ids_kats = "(".implode(",",array_keys($ids_kats)).")";
            $dataTableQuery->addWhereCondition("FK_KAT", $ids_kats);
        }
        // Add where conditions (default)
        foreach ($searchData as $searchDataKey => $searchDataValue) {
            if (($searchDataValue === null) || ($searchDataValue == "")) {
                // Skip empty search fields
                continue;
            }
            $whereAdded = $dataTableQuery->addWhereCondition($searchDataKey, (!is_array($searchDataValue) ? array($searchDataValue) : $searchDataValue));
            if (!$whereAdded) {
                // No matching condition found, try article field conditions
                if (is_array($searchDataValue)) {
                    // Search value is array, do range search
                    $searchFrom = "";
                    $searchTo = "";
                    if (array_key_exists("VON", $searchDataValue) && array_key_exists("BIS", $searchDataValue)) {
                        $searchFrom = $searchDataValue["VON"];
                        $searchTo = $searchDataValue["BIS"];
                    } else if (array_key_exists(0, $searchDataValue) && array_key_exists(1, $searchDataValue)) {
                        $searchFrom = $searchDataValue[0];
                        $searchTo = $searchDataValue[1];
                    } else if (array_key_exists("VON", $searchDataValue)) {
                        $searchFrom = $searchDataValue["VON"];
                    } else if (array_key_exists(0, $searchDataValue)) {
                        $searchFrom = $searchDataValue[0];
                    } else if (array_key_exists("BIS", $searchDataValue)) {
                        $searchTo = $searchDataValue["BIS"];
                    }
                    if (($searchFrom != "") && ($searchTo != "")) {
                        $whereAdded = $dataTableQuery->addWhereCondition("_RANGE_" . $searchDataKey, array($searchFrom, $searchTo));
                    } else if ($searchFrom != "") {
                        $whereAdded = $dataTableQuery->addWhereCondition("_GT_EQ_".$searchDataKey, array($searchFrom));
                    } else if ($searchTo != "") {
                        $whereAdded = $dataTableQuery->addWhereCondition("_LT_EQ_".$searchDataKey, array($searchTo));
                    } 
                    if (!$whereAdded) {
                        // Try to use an "in" condition
                        $valueListEscaped = array();
                        foreach ($searchDataValue as $searchDataValueIndex => $searchDataValueContent) {
                            $valueListEscaped[] = '"'.mysql_real_escape_string($searchDataValueContent).'"';
                        }
                        $whereAdded = $dataTableQuery->addWhereCondition("_IN_".$searchDataKey, array( implode(", ", $valueListEscaped) ));
                    }
                    if (!$whereAdded) {
                        // Try to add as multiple where conditions
                        $searchValueMulti = array();
                        foreach ($searchDataValue as $searchDataValueIdx => $searchDataValueCur) {
                            $searchValueMulti[] = array($searchDataValueCur);
                        }
                        $whereAdded = $dataTableQuery->addWhereCondition("_MULTI_".$searchDataKey, $searchValueMulti);
                    }
                } else {
                    // Search value is string/number
                    $whereAdded = $dataTableQuery->addWhereCondition("_EQUAL_".$searchDataKey, array($searchDataValue));
                }
            }
        }
        return $dataTableQuery;
    }

    /**
     * Adds all fields to the query that are required to render the given template.
     * WARNING: TEMPLATES MUST BE DEFINED MANUALLY! NO AUTOMATIC DETECTION!!
     * @param Api_DataTableQuery $searchQuery
     * @param $templateName
     */
    public static function addQueryFieldsByTemplate(Api_DataTableQuery $searchQuery, $templateName) {
        switch ($templateName) {
            default:
            case "ads_new.row.htm":
            case "ads_new.row_box.htm":
            case "ads_user.row.htm":
            case "ads_user.row_box.htm":
            case "ads_random.row.htm":
            case "ads_random.row_box.htm":
            case "slider_ads_top.row.htm":
            case "marktplatz.row.htm":
            case "marktplatz.row_box.htm":
            case "hersteller_details.product.htm":
                $searchQuery->addField("ID_AD");
                $searchQuery->addField("ID_AD_MASTER");
                $searchQuery->addField("AD_TABLE");
               	$searchQuery->addField("FK_USER");
                $searchQuery->addField("FK_PRODUCT");
               	$searchQuery->addField("RUNTIME_DAYS_GONE");
               	$searchQuery->addField("PRODUKTNAME");
                $searchQuery->addFieldIfKnown("FULL_PRODUKTNAME");
               	$searchQuery->addField("MANUFACTURER");
               	$searchQuery->addField("BESCHREIBUNG");
               	$searchQuery->addField("EAN");
               	$searchQuery->addField("FK_KAT");
               	$searchQuery->addField("KAT");
                $searchQuery->addField("IMPORT_IMAGES");
                $searchQuery->addField("IMG_DEFAULT_SRC");
               	$searchQuery->addField("B_TOP");
               	$searchQuery->addField("BF_CONSTRAINTS");
                $searchQuery->addField("PREIS");
                $searchQuery->addFieldIfKnown("PREIS_MIN");
                $searchQuery->addField("MENGE");
               	$searchQuery->addField("MWST");
               	$searchQuery->addField("TRADE");
               	$searchQuery->addField("PSEUDOPREIS");
               	$searchQuery->addField("B_PSEUDOPREIS_DISCOUNT");
               	$searchQuery->addField("VERKAUFSOPTIONEN");
               	$searchQuery->addField("VERSANDKOSTEN");
               	$searchQuery->addField("VERSANDOPTIONEN");
                break;
            case "product_details.offers.row.htm":
                $searchQuery->addField("ID_AD");
                $searchQuery->addField("ID_AD_MASTER");
                $searchQuery->addField("AD_TABLE");
               	$searchQuery->addField("FK_USER");
                $searchQuery->addField("FK_PRODUCT");
               	$searchQuery->addField("RUNTIME_DAYS_GONE");
               	$searchQuery->addField("PRODUKTNAME");
                $searchQuery->addFieldIfKnown("FULL_PRODUKTNAME");
               	$searchQuery->addField("MANUFACTURER");
               	$searchQuery->addField("BESCHREIBUNG");
               	$searchQuery->addField("EAN");
               	$searchQuery->addField("FK_KAT");
               	$searchQuery->addField("KAT");
                $searchQuery->addField("IMPORT_IMAGES");
                $searchQuery->addField("IMG_DEFAULT_SRC");
               	$searchQuery->addField("B_TOP");
               	$searchQuery->addField("BF_CONSTRAINTS");
                $searchQuery->addField("PREIS");
                $searchQuery->addFieldIfKnown("PREIS_MIN");
                $searchQuery->addField("MENGE");
                $searchQuery->addField("MOQ");
               	$searchQuery->addField("MWST");
               	$searchQuery->addField("TRADE");
               	$searchQuery->addField("PSEUDOPREIS");
               	$searchQuery->addField("B_PSEUDOPREIS_DISCOUNT");
               	$searchQuery->addField("VERKAUFSOPTIONEN");
               	$searchQuery->addField("VERSANDKOSTEN");
               	$searchQuery->addField("VERSANDOPTIONEN");
               	$searchQuery->addField("VENDOR_NAME");
               	$searchQuery->addField("VENDOR_LOGO");
               	$searchQuery->addField("USER_NAME");
               	$searchQuery->addField("ZIP");
               	$searchQuery->addField("CITY");
               	$searchQuery->addField("COUNTRY");
                $searchQuery->addField("COUNTRY_CODE");
                break;
        }
    }
    
    public static function validateField($idCategory, $name, $value, $needed = null, $type = null, $arSystemGroups) {
        if (($needed === null) || ($type === null)) {
            $arField = self::getFieldByName($idCategory, $name, $arSystemGroups);
            $needed = $arField["B_NEEDED"];
            $type = $arField["F_TYP"];
        } else {
            $type = strtoupper($type);
        }
        $arResult = array(
            'valid' => 1,
            'error' => "",
            'type' => $type,
            'required' => $needed
        );
        /**************************************
         * Standard-Felder kontrollieren
         **************************************/
        if (!is_numeric($value) && empty($value) && $needed) {
            $arResult["fname"] = $name;
            $arResult["valid"] = 0;
            $arResult["error"] = "FIELD_NEEDED";
            $arResult["error_msg"] = implode("", get_messages("AD_NEW", $arResult["error"]));
            return $arResult;
        }

        switch ($type) {
            /*************************
             * Zahlen
             *************************/
            case 'INT':
                if (!empty($value) || $needed) {
                    $value = str_replace(",", ".", $value);
                    if (round($value) != $value) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_INTEGER";
                    }
                    if (!is_numeric($value)) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_NUMERIC";
                    }
                }
                break;
            case 'FLOAT':
                $value = str_replace(",", ".", $value);
                if (!empty($value) || $needed) {
                    if (!is_numeric($value)) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_NUMERIC";
                    }
                }
                break;
            /*************************
             * Auswahllisten
             *************************/
            case 'LIST':
            case 'LISTE':
                if ((!is_numeric($value) || ($value <= 0)) && ($needed == 1)) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "INVALID_SELECTION";
                }
                break;
			case 'VARIANT':
            case 'MULTICHECKBOX':
            case 'MULTICHECKBOX_AND':
                if ((((int)$value == 0) || empty($value)) && ($needed == 1)) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "FIELD_NEEDED";
                }
                break;
        }
        switch ($name) {
            /*************************
             * Kurzer Text
             *************************/
            case 'PRODUKTNAME':
                // - Artikelbezeichnung
                if (strlen(trim($value)) < 2) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "TOO_SHORT";
                }
                break;
            case 'ZIP':
            case 'CITY':
                // - Postleitzahl
                // - Ort
                if (strlen(trim($value)) < 3) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "TOO_SHORT";
                }
                break;
            /*************************
             * Langer Text
             *************************/
            case 'BESCHREIBUNG':
                $value = strip_tags($value);	// Remove html tags
                break;
            /*************************
             * Auswahllisten (Zahl>0)
             *************************/
            case 'FK_COUNTRY':
            case 'ZUSTAND':
                // - Land
                // - Versandkosten
                // - Breite, Höhe, Tiefe
                // - Leistung
                if (!is_numeric($value) || ($value <= 0)) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "INVALID_SELECTION";
                }
                break;
            /*************************
             * Positive Zahl (größer Null)
             *************************/
            case 'MENGE':
            case 'PREIS':
                $value = str_replace(',', '.', $value);
                if ($value < 0) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "NEGATIVE_NUMBER";
                }
                if (!empty($value) || $needed) {
                    // Nur prüfen wenn nicht leer oder Pflichtfeld
                    if (!is_numeric($value)) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_NUMERIC";
                    }
                    if ($value < 0.01) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NULL_NUMBER";
                    }
                }
                break;
            case 'AUTOBUY':
                $value = str_replace(',', '.', $value);
                if (!empty($value) && ($value != 0)) {
                    if (!is_numeric($value)) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_NUMERIC";
                    }
                    if ($value < 0) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NEGATIVE_NUMBER";
                    }
                    if ($value < 0.01) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NULL_NUMBER";
                    }
                }
                break;
            /*************************
             * Positive Zahl
             *************************/
            case 'VERSANDKOSTEN':
            case 'BREITE': case 'HOEHE': case 'TIEFE':
            case 'LEISTUNG':
                // - Verkaufspreis
                // - Versandkosten
                // - Breite, Höhe, Tiefe
                // - Leistung
                $value = str_replace(',', '.', $value);
                if ($needed || !empty($value)) {
                    if (!is_numeric($value)) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_NUMERIC";
                    }
                }
                if ($value < 0) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "NEGATIVE_NUMBER";
                }
                break;
        }
        
		// Trigger plugin event
		$paramAdValidate = new Api_Entities_EventParamContainer(array(
            "fieldName"     => $name,
            "fieldValue"    => $value,
            "fieldNeeded"   => $needed,
            "fieldType"     => $type,
            "result"        => $arResult
        ));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_FIELD_VALIDATE, $paramAdValidate);
        if ($paramAdValidate->isDirty()) {
            $arResult = $paramAdValidate->getParam("result");
        }
        
        if (!$arResult["valid"] && !array_key_exists("error_msg", $arResult)) {
            $arResult["error_msg"] = implode("", get_messages("AD_NEW", $arResult["error"]));
        }
        return $arResult;
    }
}