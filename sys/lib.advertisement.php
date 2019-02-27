<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.advertisement.categoryprice.php';

class AdvertisementManagement {
	private static $db;
	private static $instance = null;
	
	/**
	 * Singleton 
	 * 
	 * @param ebiz_db $db
	 * @return AdvertisementManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);
		
		return self::$instance;
	}

    public function fetchAllByParam($param) {
        global $langval;
        $db = $this->getDb();

        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " a.ID_ADVERTISEMENT ASC ";

        if(isset($param['ID_ADVERTISEMENT']) && $param['ID_ADVERTISEMENT'] != null && !is_array($param['ID_ADVERTISEMENT'])) { $sqlWhere .= " AND a.ID_ADVERTISEMENT = '".mysql_real_escape_string($param['ID_ADVERTISEMENT'])."' "; }


        if(isset($param['LIMIT']) && $param['LIMIT'] != null) { if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; } }
        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }


        $query = "
            SELECT
                a.*,
                s.*
            FROM
                advertisement a
            LEFT JOIN
            		`string_advertisement` s ON s.S_TABLE='advertisement' AND s.FK=a.ID_ADVERTISEMENT AND s.BF_LANG=if(a.BF_LANG_ADVERTISEMENT & ".$langval.", ".$langval.", 1 << floor(log(a.BF_LANG_ADVERTISEMENT+0.5)/log(2)))
            ".$sqlJoin."
            WHERE
                true
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY a.ID_ADVERTISEMENT
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."
        ";

        $result = $db->fetch_table($query);

        return $result;
    }

    public function getPriceByCategory($categoryId, $advertisementId, $usergroupId) {
        $db = $this->getDb();

        $advertisemenCategoryPriceManagement = AdvertisementCategoryPriceManagement::getInstance($this->getDb());
        $price = $advertisemenCategoryPriceManagement->getPriceByCategory($advertisementId, $categoryId, $usergroupId);
        if ($price === NULL || $price === FALSE) {
            $katLevel =  $db->fetch_atom("
                SELECT
                    (SELECT count(*) FROM `kat` k2 WHERE k2.LFT<k1.LFT AND k2.RGT>k1.RGT AND k2.ROOT=k1.ROOT) as LEVEL
                FROM
                    `kat` k1
                WHERE
                    k1.ID_KAT=".$categoryId);

            return $this->getDefaultLevelPriceByAdvertisement($advertisementId, $katLevel);
        }

        return $price;
    }

    public function getDefaultLevelPriceByAdvertisement($advertisementId, $level) {
        $db = $this->getDb();

        $advertisement = $db->fetch1("SELECT * FROM advertisement WHERE ID_ADVERTISEMENT = '".(int)$advertisementId."'");
        $defaultPrices = explode("|", $advertisement["COSTS"]);

        return $defaultPrices[$level];
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