<?php
/* ###VERSIONSBLOCKINLCUDE### */



class VendorPlaceManagement {
	private static $db;
    private static $langval = 128;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return VendorPlaceManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function updateByIdAndUserId($vendorPlace, $vendorPlaceId, $userId) {
        $db = $this->getDb();

        if(!$this->existVendorPlaceByUserId($vendorPlaceId, $userId)) {
            throw new Exception("");
        }

        $vendorPlace['ID_VENDOR_PLACE'] = $vendorPlaceId;
        return $this->_updateVendorPlace($vendorPlace);
    }

    public function insertVendorPlace($vendorPlace = array(), $vendorId, &$vendorPlaceId = null) {
        $db = $this->getDb();

        $vendorPlace['FK_VENDOR'] = $vendorId;
        $vendorPlace['_rescueId'] = true;
        return $this->_updateVendorPlace($vendorPlace, $vendorPlaceId);
    }

    /**
     * Aktualisiert / Erzeugt einen Anbieter Standort. Validiert die Eingaben
     *
     * @param array $vendorPlace
     * @return bool
     */
    private function _updateVendorPlace($vendorPlace, &$vendorPlaceId = null) {
        $db = $this->getDb();

        $validation = true;
        if(!isset($vendorPlace['STRASSE']) || $vendorPlace['STRASSE'] == '') { $validation = false; }
        if(!isset($vendorPlace['PLZ']) || $vendorPlace['PLZ'] == '') { $validation = false; }
        if(!isset($vendorPlace['ORT']) || $vendorPlace['ORT'] == '') { $validation = false; }
        
		$land = $db->fetch_atom("SELECT V1 FROM `string` WHERE S_TABLE='country' AND FK=".(int)$vendorPlace["FK_COUNTRY"]." AND BF_LANG=128");
        $geoCoordinates = Geolocation_Generic::getGeolocationCached($vendorPlace["STRASSE"], $vendorPlace["PLZ"], $vendorPlace["ORT"], $land);

        if (($geoCoordinates !== null) && ($geoCoordinates !== false)) {
        	// Erfolg! Geo-Koordinaten übernehmen
        	$vendorPlace["LATITUDE"] = $geoCoordinates["LATITUDE"];
        	$vendorPlace["LONGITUDE"] = $geoCoordinates["LONGITUDE"];
        } else {
        	eventlog("error", "Anbieter: Fehler beim Auflösen einer Adresse!", $vendorPlace["STRASSE"]." ".$vendorPlace["PLZ"]." ".$vendorPlace["ORT"].", ".$land);
        }
        
        if(!isset($vendorPlace['LATITUDE']) || $vendorPlace['LATITUDE'] == '') { $validation = false; }
        if(!isset($vendorPlace['LONGITUDE']) || $vendorPlace['LONGITUDE'] == '') { $validation = false; }

        $vendorPlaceLanguages = array();
        if(is_array($vendorPlace['T1'])) {
             foreach($vendorPlace['T1'] as $lang => $value) {
                 $vendorPlaceLanguages[$lang] = $vendorPlace;
                 $vendorPlaceLanguages[$lang]['T1'] = $value;
                 $vendorPlaceLanguages[$lang]['BF_LANG_VENDOR_PLACE'] = $lang;
             }
        } else {
            $vendorPlaceLanguages[$this->getLangval()] = $vendorPlace;
        }

        if($validation === true) {

            $rescueId = null;
            foreach($vendorPlaceLanguages as $lang => $vendorPlaceLanguage) {

                if($vendorPlaceLanguage['_rescueId'] == true && $rescueId != null) {
                    $vendorPlaceLanguage['ID_VENDOR_PLACE'] = $rescueId;
                }

                $insertId = $db->update("vendor_place", $vendorPlaceLanguage);

                if($vendorPlaceLanguage['_rescueId'] == true && $rescueId == null) {
                    $rescueId = $insertId;
                }


            }
            $vendorPlaceId = $rescueId;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Löscht einen Anbieter Standort
     * 
     * @param int $vendorPlaceId
     * @param int $userId
     * @return void
     */
    public function deleteVendorPlaceById($vendorPlaceId, $userId) {
        $db = $this->getDb();

        if($this->existVendorPlaceByUserId($vendorPlaceId, $userId)) {
            $db->querynow("
                DELETE
                    p
                FROM
                    vendor_place p, vendor v
                WHERE
                    v.ID_VENDOR = p.FK_VENDOR
                    AND v.FK_USER = '".mysql_real_escape_string($userId)."'
                    AND ID_VENDOR_PLACE = '".mysql_real_escape_string($vendorPlaceId)."'
            ");

            return true;
        }
    }

    /**
     * Löscht einen Anbieter Standort
     * 
     * @param int $vendorPlaceId
     * @param int $userId
     * @return void
     */
    public function deleteVendorPlaceWhereIdNotIn($vendorPlaceIds, $vendorId) {
        $db = $this->getDb();

        foreach ($vendorPlaceIds as $vendorPlaceIndex => $vendorPlaceId) {
            $vendorPlaceIds[$vendorPlaceIndex] = (int)$vendorPlaceId;
        }
        $db->querynow("
            DELETE FROM vendor_place
            WHERE
                FK_VENDOR = '".mysql_real_escape_string($vendorId)."'
                ".(!empty($vendorPlaceIds) ? "AND ID_VENDOR_PLACE NOT IN (".implode(", ", $vendorPlaceIds).")" : ""));

        return true;
    }

    /**
     * Holt einen Anbieter Standort anhand ID under User ID
     *
     * @param $vendorPlaceId
     * @param $userId
     * @return array
     */
    public function fetchById($vendorPlaceId, $userId) {
        $db = $this->getDb();

        $result = $db->fetch1("
            SELECT p.* FROM vendor_place p
            JOIN vendor v ON v.ID_VENDOR = p.FK_VENDOR
            WHERE
                FK_USER = '".mysql_real_escape_string($userId)."'
                AND ID_VENDOR_PLACE = '".mysql_real_escape_string($vendorPlaceId)."'
        ");

        return $result;
    }

    /**
     * Holt alle Anbieter Standorte eines Benutzers
     *
     * @param $userId
     * @return array
     */
    public function fetchAllByUserId($userId) {
        $db = $this->getDb();

        $langval = $this->getLangval();

        $result = $db->fetch_table("
            SELECT
                p.*,
                (SELECT V1 FROM string WHERE S_TABLE = 'country' AND FK = p.FK_COUNTRY AND BF_LANG = '".$langval."') AS COUNTRY,
                (SELECT T1 FROM string_vendor_place WHERE S_TABLE = 'vendor_place' AND FK = p.ID_VENDOR_PLACE AND BF_LANG = if(p.BF_LANG_VENDOR_PLACE & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_VENDOR_PLACE+0.5)/log(2)))) AS DESCRIPTION
            FROM
                vendor_place p
            JOIN vendor v ON v.ID_VENDOR = p.FK_VENDOR

            WHERE
                FK_USER = '".mysql_real_escape_string($userId)."'
        ");

        return $result;
    }

    public function fetchVendorPlaceDescriptionByLanguage($vendorPlaceId, $langval = null) {
        $db = $this->getDb();
        if($langval == null) { $langval = $this->getLangval(); }

        return $db->fetch_atom("
            SELECT
                T1
            FROM
                string_vendor_place s
            JOIN
                vendor_place p ON p.ID_VENDOR_PLACE = s.FK
            WHERE
                p.ID_VENDOR_PLACE = '".$vendorPlaceId."'
                AND s.BF_LANG = if(p.BF_LANG_VENDOR_PLACE & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_VENDOR_PLACE+0.5)/log(2)))
            ");

    }

    /**
     * Prüft ob ein Anbieter Standort mit der Id $vendorPlaceId existiert, der dem
     * Benutzer mit der Id $userId gehört
     * 
     * @param $vendorPlaceId
     * @param $userId
     * @return bool
     */
    private function existVendorPlaceByUserId($vendorPlaceId, $userId) {
        $db = $this->getDb();

        $result = $db->fetch_atom("
            SELECT COUNT(*) FROM vendor_place p
            JOIN vendor v ON v.ID_VENDOR = p.FK_VENDOR
            WHERE
                p.ID_VENDOR_PLACE = '".mysql_real_escape_string($vendorPlaceId)."'
                AND v.FK_USER = '".mysql_real_escape_string($userId)."'
        ");

        return ($result > 0);
    }

    public function getLangval() {
        return self::$langval;
    }
    public function setLangval($langval) {
        self::$langval = $langval;
    }

	/**
	 * @return ebiz_db $db
	 */
	public function getDb() {
		return self::$db;
	}

	/**
	 * @param ebiz_db $db
	 */
	public function setDb(ebiz_db $db) {
		self::$db = $db;
	}

	private function __construct() {
	}
	private function __clone() {
	}
}