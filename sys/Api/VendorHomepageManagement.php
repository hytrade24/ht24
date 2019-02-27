<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 10.09.14
 * Time: 11:56
 */

class Api_VendorHomepageManagement {

    private static $instance = null;

    /**
     * @param ebiz_db   $db
     * @return Api_VendorHomepageManagement
     */
    public static function getInstance(ebiz_db $db) {
        if (self::$instance === null) {
            self::$instance = new Api_VendorHomepageManagement($db);
        }
        return self::$instance;
    }

    /**
     * @var ebiz_db $db
     */
    private $db;

    function __construct(ebiz_db $db) {
        $this->db = $db;
    }
    
    private function batchEscapeIds(&$arTargets) {
        foreach ($arTargets as $targetIndex => $targetId) {
            $arTargets[$targetIndex] = (int)$targetId;
        }
        return $arTargets;
    }

    public function batchSetStatus($arTargets, $statusNew) {
        $res = $this->db->querynow("UPDATE `vendor_homepage` SET ACTIVE=".(int)$statusNew." WHERE ID_VENDOR_HOMEPAGE IN (".implode(", ", $this->batchEscapeIds($arTargets)).")");;
        return ($res["rsrc"] === true);
    }

    public function batchDelete($arTargets) {
        $res = $this->db->querynow("DELETE FROM `vendor_homepage` WHERE ID_VENDOR_HOMEPAGE IN (".implode(", ", $this->batchEscapeIds($arTargets)).")");;
        return ($res["rsrc"] === true);
    }

    /**
     * Create new vendor homepage by the given data, returns object on success or null on failure. 
     * @param $arData
     * @return Api_Entities_VendorHomepage|null
     */
    function createNew($arData) {
        if (!array_key_exists("STAMP_START", $arData)) {
            $arData["STAMP_START"] = date("Y-m-d H:i:s");
        }
        if (!array_key_exists("DETAILS", $arData) && is_array($arData["DETAILS"])) {
            $arData["SER_DETAILS"] = $arData["DETAILS"];
            unset($arData["DETAILS"]);
        }
        $vendorHomepage = new Api_Entities_VendorHomepage($arData, $this->db);
        if ($vendorHomepage->updateDatabase()) {
            return $vendorHomepage;
        }
        return false;
    }

    /**
     * Fetch one vendor homepage by the given parameters.  (As assoc array)
     * @param $arParams
     * @return assoc
     */
    function fetchOne($arParams) {
        $arResult = $this->db->fetch1(
            $this->generateFetchQuery($arParams)
        );
        return $arResult;
    }

    /**
     * Fetch one vendor homepage by the given parameters.  (As vendor homepage object)
     * @param $arParams
     * @return Api_Entities_VendorHomepage
     */
    function fetchOneAsObject($arParams) {
        $arResult = $this->db->fetch1(
            $this->generateFetchQuery($arParams)
        );
        return (is_array($arResult) ? new Api_Entities_VendorHomepage($arResult, $this->db) : false);
    }

    /**
     * Fetch multiple vendor homepages by the given parameters. (Each as assoc array)
     * @param $arParams
     * @return array
     */
    function fetchAll($arParams) {
        $arResultList = $this->db->fetch_table(
            $this->generateFetchQuery($arParams)
        );
        return $arResultList;
    }

    /**
     * Fetch multiple vendor homepages by the given parameters. (Each as vendor homepage object)
     * @param $arParams
     * @return array
     */
    function fetchAllAsObject($arParams) {
        $arResultList = $this->db->fetch_table(
            $this->generateFetchQuery($arParams)
        );
        return Api_Entities_VendorHomepage::createMultipleFromArray($arResultList);
    }

    private function generateFetchWhere($arParams) {
        $where = array();
        
        if (array_key_exists("ID_VENDOR_HOMEPAGE", $arParams)) {
            if (!is_array($arParams["ID_VENDOR_HOMEPAGE"])) {
                $where[] = "vh.ID_VENDOR_HOMEPAGE=".(int)$arParams["ACTIVE"];
            } else {
                $idList = array();
                foreach ($arParams["ID_VENDOR_HOMEPAGE"] as $homepageIndex => $homepageId) {
                    $idList[] = (int)$homepageId;
                }
                $where[] = "vh.ID_VENDOR_HOMEPAGE IN (".implode(", ", $idList).")";
            }
        }
        if (array_key_exists("ACTIVE", $arParams)) {
            $where[] = "vh.ACTIVE=".(int)$arParams["ACTIVE"];
        }
        if (array_key_exists("FK_USER", $arParams)) {
            $where[] = "vh.FK_USER=".(int)$arParams["FK_USER"];
        }
        
        return (empty($where) ? "" : "WHERE ".implode(" AND ", $where));
    }

    private function generateFetchQuery($arParams) {
        $limit = (array_key_exists("LIMIT", $arParams) ? $arParams["LIMIT"] : 10);
        return "
          SELECT
            *
          FROM `vendor_homepage` vh
          ".$this->generateFetchWhere($arParams)."
          LIMIT ".$limit;
    }

} 