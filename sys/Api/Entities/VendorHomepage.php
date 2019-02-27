<?php
/**
 * Created by PhpStorm.
 * VendorHomepage: jens
 * Date: 11.06.15
 * Time: 10:58
 */

class Api_Entities_VendorHomepage {

    const STATUS_PENDING = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_DECLINED = 2;
    const STATUS_DISABLED = 3;
    const STATUS_DOMAIN_CONFIG = 4;
    
    /**
     * Create an array of vendorHomepage object by an array of assoc vendorHomepage datasets
     * @param $arVendorHomepageList   array
     * @return array
     */
    public static function createMultipleFromArray($arVendorHomepageList) {
        $arResult = array();
        foreach ($arVendorHomepageList as $vendorHomepageIndex => $arVendorHomepage) {
            $arResult[] = new Api_Entities_VendorHomepage($arVendorHomepage);
        }
        return $arResult;
    }
    
    protected $db;
    protected $vendorHomepageData;
    
    function __construct(&$arVendorHomepage = array(), ebiz_db $db = null) {
        $this->db = ($db === null ? $GLOBALS['db'] : $db);
        $this->vendorHomepageData = $arVendorHomepage;
    }

    /**
     * Returns the vendorHomepages dataset as assoc array
     * @return array
     */
    public function asArray($addDetails = false) {
        if ($addDetails) {
            $arResult = $this->vendorHomepageData;
            $arResultDetails = $this->getDetails();
            if (is_array($arResultDetails)) {
                foreach ($arResultDetails as $detailName => $detailValue) {
                    $arResult["DETAILS_".$detailName] = $detailValue;
                }
            }
            return $arResult;
        } else {
            return $this->vendorHomepageData;
        }
    }

    /**
     * Get a raw value from the vendorHomepages dataset
     * @param $fieldName
     * @return string|int|null
     */
    public function getFieldRaw($fieldName) {
        if (array_key_exists($fieldName, $this->vendorHomepageData)) {
            return $this->vendorHomepageData[$fieldName];
        } else {
            return null;
        }
    }
    
    /**
     * Get the id of the vendorHomepage
     * @return int|null
     */
    public function getId() {
        if (array_key_exists("ID_VENDOR_HOMEPAGE", $this->vendorHomepageData)) {
            return ($this->vendorHomepageData["ID_VENDOR_HOMEPAGE"] > 0 ? (int)$this->vendorHomepageData["ID_VENDOR_HOMEPAGE"] : null);
        } else {
            return null;
        }
    }

    /**
     * Get the status of the vendorHomepage
     * @return int
     */
    public function getActive() {
        return (int)$this->vendorHomepageData["ACTIVE"];
    }

    /**
     * Get the subdomain of the vendorHomepage
     * @return string|null
     */
    public function getDomainSub() {
        return $this->vendorHomepageData["DOMAIN_SUB"];
    }
    
    /**
     * Get the subdomain of the vendorHomepage
     * @return string|null
     */
    public function getDomainFull() {
        return $this->vendorHomepageData["DOMAIN_FULL"];
    }
    
    /**
     * Get the start date of the vendorHomepage
     * @return string|null
     */
    public function getStampStart() {
        return $this->vendorHomepageData["STAMP_START"];
    }
    
    /**
     * Get the start date of the vendorHomepage
     * @return string|null
     */
    public function getStampEnd() {
        return $this->vendorHomepageData["STAMP_END"];
    }
    
    /**
     * Get details from the serialized array ("SER_DETAILS")
     * @return array|mixed
     */
    public function getDetails() {
        if (array_key_exists("SER_DETAILS", $this->vendorHomepageData) && ($this->vendorHomepageData["SER_DETAILS"] !== NULL)) {
            return unserialize($this->vendorHomepageData["SER_DETAILS"]);
        }
        return array();
    }

    /**
     * Get the userid assigned to this homepage / domain
     * @return int
     */
    public function getUser() {
        return (int)$this->vendorHomepageData["FK_USER"];
    }

    /**
     * Sets the status of the vendorHomepage
     * @param $status
     */
    public function setActive($status) {
        $this->vendorHomepageData["ACTIVE"] = (int)$status;
    }
    
    /**
     * Sets the id of the vendorHomepage
     * @param $id   int
     */
    public function setId($id) {
        $this->vendorHomepageData["ID_VENDOR_HOMEPAGE"] = (int)$id;
    }

    /**
     * Write details to the serialized array ("SER_DETAILS")
     * @param $arDetails
     */
    public function setDetails($arDetails) {
        $this->vendorHomepageData["SER_DETAILS"] = serialize($arDetails);
    }

    /**
     * Sets the subdomain of the vendorHomepage
     * @param $domainSub
     */
    public function setDomainSub($domainSub) {
        $this->vendorHomepageData["DOMAIN_SUB"] = $domainSub;
    }

    /**
     * Sets the domain of the vendorHomepage
     * @param $domainFull
     */
    public function setDomainFull($domainFull) {
        $this->vendorHomepageData["DOMAIN_FULL"] = $domainFull;
    }

    /**
     * Sets the start date of the vendorHomepage
     * @param $stampStart
     */
    public function setStampStart($stampStart) {
        $this->vendorHomepageData["STAMP_START"] = $stampStart;
    }

    /**
     * Sets the end date of the vendorHomepage
     * @param $stampEnd
     */
    public function setStampEnd($stampEnd) {
        $this->vendorHomepageData["STAMP_END"] = $stampEnd;
    }
    
    /**
     * Write changes to current dataset to database
     */
    function updateDatabase() {
        $id = $this->db->update("vendor_homepage", $this->vendorHomepageData);
        if ($id > 0) {
            $this->setId($id);
            return true;
        }
        return false;
    }
}