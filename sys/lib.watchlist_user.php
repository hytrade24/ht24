<?php
/* ###VERSIONSBLOCKINLCUDE### */


class WatchlistUserManagement {
	private static $db;
	private static $instance = NULL;

	const MAX_LIST_PER_USER = 4;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return WatchlistUserManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === NULL) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	public function initWatchlistsForUser($userId) {
        $strListe = Translation::readTranslation("marketplace", "watchlist.list", null, array(), "Liste");
		$db = $this->getDb();
		$numberOfWatchlists = $db->fetch_atom("SELECT COUNT(*) FROM watchlist_user WHERE FK_USER = '".(int)$userId."'");
		$remainWatchlist = self::MAX_LIST_PER_USER - $numberOfWatchlists;

		for($i = 0; $i < $remainWatchlist; $i++) {
			$db->update("watchlist_user", array('FK_USER' => $userId, 'LISTNAME' => $strListe.' '.($i+1)));
		}
	}

	public function existListForUser($userId, $watchlistUserId) {
		return ($this->getDb()->fetch_atom("SELECT COUNT(*) FROM watchlist_user WHERE FK_USER = '".(int)$userId."' AND FK_USER = '".(int)$watchlistUserId."'") > 0);
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

	public function updateListForUser($userId, $lists) {
		foreach($lists as $key => $listname) {
			$this->getDb()->querynow("UPDATE watchlist_user SET LISTNAME = '".mysql_real_escape_string($listname)."' WHERE FK_USER = '".(int)$userId."' AND ID_WATCHLIST_USER = '".(int)$key."'");
		}
	}

	protected function generateFetchQuery($param) {
		global $langval;
		$db = $this->getDb();

		$sqlLimit = "";
		$sqlWhere = "";
		$sqlJoin = "";
		$sqlFields = "";
		$sqlOrder = " wu.ID_WATCHLIST_USER ";


		if(isset($param['FK_USER']) && $param['FK_USER'] != NULL) { $sqlWhere .= " AND wu.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }

		if(isset($param['SORT_BY']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT_BY']." ".$param['SORT_DIR']; }
		if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
			if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
		}

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			$sqlFields = "wu.ID_WATCHLIST_USER";
		} else {
			$sqlFields = "
				wu.*
			";
		}

		$query = "
			SELECT
				SQL_CALC_FOUND_ROWS
				".$sqlFields."
			FROM `watchlist_user` wu
			".$sqlJoin."
			WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
			GROUP BY wu.ID_WATCHLIST_USER
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
