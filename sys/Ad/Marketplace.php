<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 05.08.15
 * Time: 16:03
 */

/**
 * Class Ad_Marketplace
 * @deprecated
 */
class Ad_Marketplace {
    
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
    
    public static function enableAd($adId, $adTable) {
        return Rest_MarketplaceAds::enableAd($adId, $adTable);
    }

    public static function enableAds($adIds) {
        return Rest_MarketplaceAds::enableAds($adIds);
    }

    public static function enableAdsEx($arAds) {
        return Rest_MarketplaceAds::enableAdsEx($arAds);
    }
    
    public static function disableAd($adId, $adTable) {
        return Rest_MarketplaceAds::disableAd($adId, $adTable);
    }

    public static function disableAds($adIds) {
        return Rest_MarketplaceAds::disableAds($adIds);
    }

    public static function disableAdsEx($arAds) {
        return Rest_MarketplaceAds::disableAdsEx($arAds);
    }
    
    public static function deleteAd($adId, $adTable) {
        return Rest_MarketplaceAds::deleteAd($adId, $adTable);
    }

    public static function deleteAds($adIds) {
        return Rest_MarketplaceAds::deleteAds($adIds);
    }

    public static function deleteAdsEx($arAds) {
        return Rest_MarketplaceAds::deleteAdsEx($arAds);
    }

    public static function deleteAdUser($adId, $userId) {
        return Rest_MarketplaceAds::deleteAdUser($adId, $userId);
    }
    
    public static function extendAdDetailsList(&$arArticleList) {
        Rest_MarketplaceAds::extendAdDetailsList($arArticleList);
    }
    
    public static function extendAdDetailsSingle(&$arArticle) {
        Rest_MarketplaceAds::extendAdDetailsSingle($arArticle);
    }
    
    public static function getFieldLabelByName($tableDefId, $fieldName, ebiz_db $db = null, $langval = null) {
        return Rest_MarketplaceAds::getFieldLabelByName($tableDefId, $fieldName, $db, $langval);
    }
    
    public static function getFieldIdByName($tableDefId, $fieldName, ebiz_db $db = null) {
        return Rest_MarketplaceAds::getFieldIdByName($tableDefId, $fieldName, $db);
    }

    public static function getListLabelById($idListeValue, ebiz_db $db = null, $langval = null) {
        return Rest_MarketplaceAds::getListLabelById($idListeValue, $db, $langval);
    }
        
    /**
     * Get all search fields for the given table/category
     * @param int       $tableDefId
     * @param int       $categoryId
     * @param ebiz_db   $db
     * @return array
     */
    public static function getFields($tableDefId, $categoryId, ebiz_db $db = null, $langval = null) {
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
      			WHERE f.FK_TABLE_DEF=" . $tableDefId . " AND (f.IS_MASTER=1 OR kf.B_ENABLED=1)"
        );
    }
    
    /**
     * Get all search fields for the given table/category
     * @param int       $tableDefId
     * @param int       $categoryId
     * @param ebiz_db   $db
     * @return array
     */
    public static function getSearchFields($tableDefId, $categoryId, ebiz_db $db = null, $langval = null) {
        return Rest_MarketplaceAds::getSearchFields($tableDefId, $categoryId, $db, $langval);
    }
    
    public static function getVariantFields($tableDefId, $categoryId, ebiz_db $db = null, $langval = null) {
        return Rest_MarketplaceAds::getVariantFields($tableDefId, $categoryId, $db, $langval);
    }
    
    /**
     * Get all master search fields that behave like regular search fields
     * @return array
     */
    public static function getSearchFieldsMaster() {
        return Rest_MarketplaceAds::getSearchFieldsMaster();
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
        return Rest_MarketplaceAds::getAdCachePath($id, $createIfNotExist, $absoluteUrl);
    }
    
    /**
     * Get datatable object for the given category
     * @param int       $categoryId
     * @param ebiz_db   $db
     * @param int       $langval
     * @return Api_DataTable
     */
    public static function getDataTable($categoryId = null, ebiz_db $db = null, $langval = null) {
        return Rest_MarketplaceAds::getDataTable($categoryId, $db, $langval);
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
        return Rest_MarketplaceAds::getQueryByHash($searchHash, $searchParamsMore, $db, $langval);
    }

    /**
     * Get a DataTableQuery object for the given search parameters
     * @param array     $searchData
     * @param ebiz_db   $db
     * @param int       $langval
     * @return Api_DataTableQuery
     */
    public static function getQueryByParams($searchData, ebiz_db $db = null, $langval = null) {
        return Rest_MarketplaceAds::getQueryByParams($searchData, $db, $langval);
    }

    /**
     * Adds all fields to the query that are required to render the given template.
     * WARNING: TEMPLATES MUST BE DEFINED MANUALLY! NO AUTOMATIC DETECTION!!
     * @param Api_DataTableQuery $searchQuery
     * @param $templateName
     */
    public static function addQueryFieldsByTemplate(Api_DataTableQuery $searchQuery, $templateName) {
        return Rest_MarketplaceAds::addQueryFieldsByTemplate($searchQuery, $templateName);
    }

}