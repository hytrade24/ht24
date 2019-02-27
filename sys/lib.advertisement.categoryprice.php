<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.pub_kategorien.php';

class AdvertisementCategoryPriceManagement {
	private static $db;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return AdvertisementCategoryPriceManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}


    public function getPriceByCategory($advertisementId, $categoryId, $usergroupId) {
        return $this->getDb()->fetch_atom("SELECT
                PRICE
            FROM
                advertisement_kat_price
            WHERE
                FK_ADVERTISEMENT = '".(int)$advertisementId."'
                AND FK_KAT = '".(int)$categoryId."'
                AND FK_USERGROUP = '".(int)$usergroupId."'
        ");
    }


    /**
     * @param $categoryId
     * @param array $priceTable in der Form array[advertisement_id][usergroup_id]
     */
    public function setPriceByCategory($categoryId, $priceTable, $passToChildren = false) {
        $db = $this->getDb();

        foreach($priceTable as $advertisementId => $table) {
            foreach($table as $usergroupId => $price) {
                $this->setPrice($categoryId, $advertisementId, $usergroupId, $price);
            }
        }

        if($passToChildren) {
            $kat_cache = new CategoriesCache();
            $children = $kat_cache->kats_read($categoryId);
            if($children) {
                foreach($children as $key => $child) {
                    $this->setPriceByCategory($child['ID_KAT'], $priceTable, true);
                }
            }
        }
    }

    public function setPrice($categoryId, $advertisementId, $usergroupId, $price) {
        $db = $this->getDb();
        $price = str_replace(",", ".", $price);
        if(trim($price) == "") {
            $price = NULL;
        } else {
            $price = (float)$price;
        }

        $advertisementKatPriceId = $db->fetch_atom("SELECT ID_ADVERTISEMENT_KAT_PRICE FROM advertisement_kat_price WHERE FK_KAT = '".(int)$categoryId."' AND FK_ADVERTISEMENT = '".(int)$advertisementId."' AND FK_USERGROUP = '".(int)$usergroupId."'");
        $db->update('advertisement_kat_price', array(
            'ID_ADVERTISEMENT_KAT_PRICE' => $advertisementKatPriceId,
            'FK_ADVERTISEMENT' => (int)$advertisementId,
            'FK_USERGROUP' => (int)$usergroupId,
            'FK_KAT' => (int)$categoryId,
            'PRICE' => $price
        ));
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