<?php
/* ###VERSIONSBLOCKINLCUDE### */


class WatchlistManagement {
	private static $db;
	private static $instance = NULL;

	protected $lastFetchCount = 0;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return WatchlistManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === NULL) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	public function addItem($userId, $watchlistUserId, $fkRefType, $fkRef, $itemName, $description, $url) {
		global $tpl_main;
		$db = $this->getDb();

		$isWatchlistOwnedByUser = ($db->fetch_atom("SELECT COUNT(*) as a FROM watchlist_user WHERE FK_USER = '".(int)$userId."' AND ID_WATCHLIST_USER = '".(int)$watchlistUserId."' ") > 0);

		if($isWatchlistOwnedByUser) {
			$previewImage = $this->getPreviewImageByUrl($tpl_main->tpl_uri_action_full($url));

			$db->update("watchlist", array(
				'FK_USER' => $userId,
				'FK_WATCHLIST_USER' => $watchlistUserId,
				'FK_REF' => $fkRef,
				'FK_REF_TYPE' => $fkRefType,
				'ITEMNAME' => $itemName,
				'STAMP_CREATE' => date("Y-m-d H:i:s"),
				'DESCRIPTION' => $description,
				'URL' => $url,
				'PREVIEW' => $previewImage
			));
		}

		return FALSE;
	}

	public function removeItemByFk($userId, $fkRef, $fkRefType) {
		$this->getDb()->querynow("DELETE FROM watchlist WHERE FK_USER = '".(int)$userId."' AND FK_REF = '".(int)$fkRef."' AND FK_REF_TYPE = '".mysql_real_escape_string($fkRefType)."'");
	}

	public function removeItemById($watchlistId, $userId) {
		$this->getDb()->querynow("DELETE FROM watchlist WHERE FK_USER = '".(int)$userId."' AND ID_WATCHLIST = '".(int)$watchlistId."'");
	}

	public function existItemForUser($userId, $fkRef, $fkRefType) {
		 return ($this->getDb()->fetch_atom("SELECT COUNT(*) FROM watchlist WHERE FK_USER = '".(int)$userId."' AND FK_REF = '".(int)$fkRef."' AND FK_REF_TYPE = '".mysql_real_escape_string($fkRefType)."' ") > 0);
	}

	public function existItemURLForUser($userId, $url, $fkRefType) {
		return ($this->getDb()->fetch_atom("SELECT COUNT(*) FROM watchlist WHERE FK_USER = '".(int)$userId."' AND URL = '".$url."' AND FK_REF_TYPE = '".mysql_real_escape_string($fkRefType)."' ") > 0);
	}

	public function removeItemURLByFk($userId, $url, $fkRefType) {
		$this->getDb()->querynow("DELETE FROM watchlist WHERE FK_USER = '".(int)$userId."' AND URL = '".$url."' AND FK_REF_TYPE = '".mysql_real_escape_string($fkRefType)."'");
	}

	public function getPreviewImageByUrl($url) {
		$html = @file_get_contents($url);
		$firstImage = NULL;

		if($html != null) {
			// Prepare the DOM document
			$dom = new DOMDocument();
			libxml_use_internal_errors(true);
			$dom->loadHTML($html);
			$dom->preserveWhiteSpace = false;

			// Get all images
			$imgs = $dom->getElementsByTagname('img');

			foreach ($imgs as $img) {
				if($firstImage == NULL) {
					$firstImage = $img->getAttribute("src");
				}
				if($img->getAttribute("data-watchlist") != "") {
					$firstImage = $img->getAttribute("src");
					break;
				}
			}
			libxml_clear_errors();

			return $firstImage;
		} else {
			return "";
		}
	}


	public function fetchAllByParam($param) {
		global $tpl_main;

		$db = $this->getDb();
		$query = $this->generateFetchQuery($param);
		$result = $db->fetch_table($query);
		$this->lastFetchCount = $db->fetch_atom("SELECT FOUND_ROWS()");

		foreach($result as $key => $item) {
			$result[$key]['LINK'] = $tpl_main->tpl_uri_action($item['URL']);
		}

		return $result;
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

	public function getLastFetchByParamCount() {
		return $this->lastFetchCount;
	}

	protected function generateFetchQuery($param) {
		global $langval;
		$db = $this->getDb();

		$sqlLimit = "";
		$sqlWhere = "";
		$sqlJoin = "";
		$sqlFields = "";
		$sqlOrder = " w.STAMP_CREATE ";


		if(isset($param['FK_USER']) && $param['FK_USER'] != NULL) { $sqlWhere .= " AND w.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
		if(isset($param['FK_WATCHLIST_USER']) && $param['FK_WATCHLIST_USER'] !== NULL) { $sqlWhere .= " AND w.FK_WATCHLIST_USER = '".mysql_real_escape_string($param['FK_WATCHLIST_USER'])."' "; }
		if(isset($param['SEARCHTEXT']) && $param['SEARCHTEXT'] != NULL) { $sqlWhere .= " AND (w.ITEMNAME LIKE '%".mysql_real_escape_string($param['SEARCHTEXT'])."%' OR w.DESCRIPTION LIKE '%".mysql_real_escape_string($param['SEARCHTEXT'])."%') "; }

		if(isset($param['SORT_BY']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT_BY']." ".$param['SORT_DIR']; }
		if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
			if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
		}

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			$sqlFields = "w.ID_WATCHLIST";
		} else {
			$sqlFields = "
				w.*,
				wu.LISTNAME as FK_WATCHLIST_USER_NAME
			";
		}

		$query = "
			SELECT
				SQL_CALC_FOUND_ROWS
				".$sqlFields."
			FROM `watchlist` w
			JOIN watchlist_user wu ON wu.ID_WATCHLIST_USER = w.FK_WATCHLIST_USER
			".$sqlJoin."
			WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
			GROUP BY w.ID_WATCHLIST
			ORDER BY ".$sqlOrder."
				".($sqlLimit?'LIMIT '.$sqlLimit:'')."
		";

		return $query;
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

?>
