<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 11.06.15
 * Time: 10:58
 */

class Api_Entities_Vendor {
    
    protected static $vendorCacheByUser = [];

    /**
     * Create an array of user object by an array of assoc user datasets
     * @param $arUserList   array
     * @return array
     */
    public static function createMultipleFromArray($arVendorList) {
        $arResult = array();
        foreach ($arVendorList as $vendorIndex => $arVendor) {
            $arResult[] = new Api_Entities_Vendor($arVendor);
        }
        return $arResult;
    }
    
    public static function getByUserId($userId, $db = null, $allowCached = true) {
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($allowCached && array_key_exists($userId, static::$vendorCacheByUser)) {
            return static::$vendorCacheByUser[$userId];
        }
        require_once $GLOBALS["ab_path"]."sys/lib.vendor.php";
        $arVendorTarget = VendorManagement::getInstance($db)->fetchByUserId($userId);
        if (!is_array($arVendorTarget)) {
            return null;
        }
        $result = new static($arVendorTarget, $db);
        static::$vendorCacheByUser[$userId] = $result;
        return $result;
    }
    
    protected $vendorData;
    protected $db;
    
    function __construct(&$arVendor = array(), ebiz_db $db = null) {
        $this->vendorData = $arVendor;
        $this->db = ($db === null ? $GLOBALS['db'] : $db);
    }

    /**
     * Returns the users dataset as assoc array
     * @return array
     */
    public function asArray() {
        return array_merge($this->vendorData, [
            "LOGO" => $this->getLogo()
        ]);
    }

    /**
     * Get a raw value from the vendors dataset
     * @param $fieldName
     * @return string|int|null
     */
    public function getFieldRaw($fieldName) {
        if (array_key_exists($fieldName, $this->vendorData)) {
            return $this->vendorData[$fieldName];
        } else {
            return null;
        }
    }
    
    /**
     * Get the id of the vendor
     * @return int|null
     */
    public function getId() {
        if (array_key_exists("ID_VENDOR", $this->vendorData)) {
            return ($this->vendorData["ID_VENDOR"] > 0 ? (int)$this->vendorData["ID_VENDOR"] : null);
        } else {
            return null;
        }
    }
    
    public function getLogo() {
        return ($this->vendorData['LOGO'] != "" ? 'cache/vendor/logo/'.$this->vendorData['LOGO'] : null);
    }

    /**
     * Sets the id of the vendor
     * @param $id   int
     */
    public function setId($id) {
        $this->vendorData["ID_VENDOR"] = (int)$id;
    }

}