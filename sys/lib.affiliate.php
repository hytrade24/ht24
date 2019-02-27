<?php

class AffiliateManagement {
	private static $db;
	private static $instance = NULL;

	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;
	const STATUS_PAUSED = 2;
    const STATUS_TESTING = 3;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return AffiliateManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === NULL) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	public function find($affiliateId) {
		global $langval;

		$db = $this->getDb();

		return $db->fetch1("
			SELECT
				a.*
			FROM affiliate a
			WHERE
				a.ID_AFFILIATE = '".mysql_real_escape_string($affiliateId)."'
		");
	}

	public function fetchAllByParam($param) {
		$db = $this->getDb();
		$query = $this->generateFetchQuery($param);
		return $db->fetch_table($query);
	}



	public function countByParam($param) {
		$db = $this->getDb();

		unset($param['LIMIT']);
		unset($param['OFFSET']);
		unset($param['SORT']);
		unset($param['SORT_DIR']);
		$param['NO_FIELDS'] = TRUE;

		$query = $this->generateFetchQuery($param);

		$db->querynow($query);
		$rowCount = $db->fetch_atom("SELECT FOUND_ROWS()");

		return $rowCount;
	}

	protected function generateFetchQuery($param) {
		global $langval;
		$db = $this->getDb();

		$sqlLimit = "";
		$sqlWhere = "";
		$sqlJoin = "";
		$sqlFields = "";
		$sqlOrder = " a.ID_AFFILIATE ";


		if(isset($param['ID_AFFILIATE']) && $param['ID_AFFILIATE'] != NULL) { $sqlWhere .= " AND a.ID_AFFILIATE = '".mysql_real_escape_string($param['ID_AFFILIATE'])."' "; }
		if(isset($param['SEARCH_NAME']) && $param['SEARCH_NAME'] != NULL) { $sqlWhere .= " AND a.DESCRIPTION LIKE '%".mysql_real_escape_string($param['SEARCH_NAME'])."%' "; }
		if(isset($param['STATUS']) && $param['STATUS'] != NULL ) { $sqlWhere .= " AND a.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }


		if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }
		if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
			if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
		}

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			$sqlFields = "a.ID_THEMEWORLD";
		} else {
			$sqlFields = "a.*";
		}

		$query = "
			SELECT
				SQL_CALC_FOUND_ROWS
				$sqlFields
			FROM `affiliate` a
			".$sqlJoin."
			WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
			GROUP BY a.ID_AFFILIATE
			ORDER BY ".$sqlOrder."
				".($sqlLimit?'LIMIT '.$sqlLimit:'')."
		";

		return $query;
	}


	/**
	 * @param $affiliateId
	 */
	public function deleteById($affiliateId) {
		$db = $this->getDb();
		#$this->deleteAllCategoriesByThemeworldId($affiliateId);
		#$this->deleteAllPriceByThemeworldId($affiliateId);
		$db->delete("affiliate", $affiliateId);
	}

	public function getAffiliateAdapters() {
		global $ab_path;

		$affiliateAdapters = array();
		$affiliateAdapterDirectory = $ab_path.'sys/affiliate/adapter/';
		$tmpFiles = scandir($affiliateAdapterDirectory);
		foreach($tmpFiles as $key => $file) {
			if(is_dir($affiliateAdapterDirectory.$file) && preg_match("/^[a-zA-Z0-9]+$/", $file)) {
				$affiliateAdapters[] = array('ADAPTER' => $file);
			}
		}

		return $affiliateAdapters;
	}

	public function fetchNextCronjob($status = 1) {
		global $ab_path;
		$db = $this->getDb();

		$affiliate = $db->fetch1($q = "SELECT a.* FROM affiliate a WHERE STATUS = '".(int)$status."' AND (NOW() > DATE_ADD(STAMP_LAST, INTERVAL a.INTERVAL HOUR) OR STAMP_LAST IS NULL) ");
		if($affiliate != NULL) {
			$db->querynow("UPDATE affiliate SET STAMP_LAST = NOW() WHERE ID_AFFILIATE = '".(int)$affiliate['ID_AFFILIATE']."'");
		}

		return $affiliate;
	}

	public function updateCategoryAliases($categoryId, $priceTable, $passToChildren = false) {
		$db = $this->getDb();

		foreach($priceTable as $affiliateId => $alias) {
			$affiliateKatAliasId = $db->fetch_atom($q="SELECT ID_AFFILIATE_KAT_ALIAS FROM affiliate_kat_alias WHERE FK_KAT = '".(int)$categoryId."' AND FK_AFFILIATE = '".(int)$affiliateId."' ");
			$db->update("affiliate_kat_alias", array(
				'ID_AFFILIATE_KAT_ALIAS' => $affiliateKatAliasId,
				'FK_KAT' => $categoryId,
				'FK_AFFILIATE' => $affiliateId,
				'ALIAS' => $alias
			));
		}

		if($passToChildren) {
			$kat_cache = new CategoriesCache();
			$children = $kat_cache->kats_read($categoryId);
			if($children) {
				foreach($children as $key => $child) {
					$this->updateCategoryAliases($child['ID_KAT'], $priceTable, true);
				}
			}
		}
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


    public function getLangval() {
        return self::$langval;
    }
    public function setLangval($langval) {
        self::$langval = $langval;
    }

	private function __construct() {
	}



	private function __clone() {
	}
}
