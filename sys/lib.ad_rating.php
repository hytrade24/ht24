<?php
/* ###VERSIONSBLOCKINLCUDE### */



class AdRatingManagement {
	private static $db;
	private static $instance = null;
	
	/**
	 * Singleton 
	 * 
	 * @param ebiz_db $db
	 * @return AdRatingManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);
		
		return self::$instance;
	}	
	
	/**
	 * Fügt eine neue Bewertung hinzu
	 * 
	 * @param int $adSoldId 	Id der Anzeigen Transaktion
	 * @param int $userId 		User Id des zu bewertenden Users
	 * @param int $userFromId	User Id des bewertenden Users
	 * @param int $rating		Bewertung
	 * @param string $comment	Kommentar
	 */
	public function insertAdRating($adSoldId, $userId, $userFromId, $rating = 0, $comment = "", $adProductName = "") {
		$db = $this->getDb();
		
		$rating = array(
            "FK_AD_SOLD"    => $adSoldId,
            "FK_USER"       => $userId,
            "FK_USER_FROM"  => $userFromId,
			"STAMP_RATED"	=> date("Y-m-d H:i:s"),
            "RATING"        => $rating,
            "COMMENT"       => $comment,
			"AD_PRODUCTNAME"=> $adProductName
		);
		$db->update("ad_sold_rating", $rating);
		$this->updateAdRatingUserCache($userId);
	}

	public function cancelAdRating($idAdSoldRating, $userId) {
		$db = $this->getDb();
		$ratedUserId = $db->fetch_atom("SELECT FK_USER FROM `ad_sold_rating` WHERE ID_AD_SOLD_RATING='".(int)$idAdSoldRating."'");
		$db->querynow("UPDATE `ad_sold_rating` SET RATING=0 WHERE ID_AD_SOLD_RATING=".(int)$idAdSoldRating." AND FK_USER_FROM = '".(int)$userId."'");
		$this->updateAdRatingUserCache($ratedUserId);
	}
	
	/**
	 * Trägt die aktuelle Bewertung eines Users in dessen Datensatz ein (Tabelle "user")
	 * 
	 * @param int $userId		User Id für das Updaten
	 */
	public function updateAdRatingUserCache($userId) {
		$db = $this->getDb();

		$rating = $this->getAverageAdRatingValueByUserId($userId);
		if ($db->querynow("UPDATE `user` SET RATING=".(int)$rating." WHERE ID_USER=".(int)$userId)) {
			return true;
		}
		return false;
	}
	
	/**
	 * holt alle Bewertungen von dem User $userId
	 * 
	 * @param int $userId
	 * @param array
	 */
	public function fetchAllAdRatingsByUserId($userId, $conditionParam = array(), $limit = null) {
		$db = $this->getDb();
		
		$sqlWhere = array();
		
		$limitSql = "";
		if($limit !== null) {
			$limitSql = " LIMIT ".mysql_real_escape_string($limit)." ";
		}		
		
		if(isset($conditionParam['restrictSoldTypeAsSeller']) && $conditionParam['restrictSoldTypeAsSeller'] == true) { 
			$sqlWhere[] = " AND (ad_sold.FK_USER_VK = '".mysql_escape_string($userId)."') ";
		}
		if(isset($conditionParam['restrictSoldTypeAsBuyer']) && $conditionParam['restrictSoldTypeAsBuyer'] == true) { 
			$sqlWhere[] = " AND (ad_sold.FK_USER = '".mysql_escape_string($userId)."') ";
		}
		if(!isset($conditionParam['showCanceledRatings']) || $conditionParam['showCanceledRatings'] != true) {
			$sqlWhere[] = " AND (ad_sold_rating.RATING > 0) ";
		}
		
		$ratings = $db->fetch_table("
			SELECT ad_sold_rating.* FROM ad_sold_rating 
			JOIN ad_sold ON ad_sold.ID_AD_SOLD = ad_sold_rating.FK_AD_SOLD
			WHERE ad_sold_rating.FK_USER = '".mysql_escape_string($userId)."' ".implode("", $sqlWhere)."
			ORDER BY ad_sold_rating.STAMP_RATED DESC, ad_sold_rating.ID_AD_SOLD_RATING DESC ".$limitSql."
				
		");
		return $ratings;
	}
	
	/**
	 * Existiert eine Bewertung für einen Verkauf von dem User $userId
	 * 
	 * @param int $adSoldId
	 * @param int $userId
	 * @return boolean
	 */
	public function existsRatingByAdSoldIdAndUserId($adSoldId, $userId) {
		$db = $this->getDb();
		
		$ratingCount = $db->fetch_atom("SELECT COUNT(*) as c FROM ad_sold_rating WHERE FK_AD_SOLD = '".mysql_escape_string($adSoldId)."' AND FK_USER = '".mysql_escape_string($userId)."'");
		return ($ratingCount['c'] > 0);
	}

    /**
     * Gibt die Gesamtbewertung eines Nutzers zurück
     *
     * @param int $userId
     * @return int
     */
    public function getRatingByUserId($userId) {
        $db = $this->getDb();

        $rating = $db->fetch_atom("SELECT RATING as c FROM user WHERE ID_USER = '".mysql_escape_string($userId)."'");
        return round($rating);
    }

    /**
     * Gibt die Anzahl an Bewerungen zurück die über einen User gemacht wurden
     *
     * @param int $userId
     * @return int Anzahl der Bewertungen
     */
    public function countRatingsByUserId($userId, $conditionParam = array()) {
        $db = $this->getDb();

        $ratingCount = $db->fetch_atom("SELECT COUNT(*) as c FROM user WHERE ID_USER = '".mysql_escape_string($userId)."'");
        return $ratingCount;
    }

	/**
	 * Gibt die Gesamtbewertung eines Nutzers zurück
	 * 
	 * @param int $userId
	 * @return int 
	 */
	public function getAverageAdRatingValueByUserId($userId) {
		$db = $this->getDb();

		$rating = $db->fetch_atom("SELECT AVG(RATING) as c FROM ad_sold_rating WHERE FK_USER = '".mysql_escape_string($userId)."' AND RATING > 0");
		return round($rating);
	}
	
	/**
	 * Gibt die Anzahl an Bewerungen zurück die über einen User gemacht wurden
	 * 
	 * @param int $userId
	 * @return int Anzahl der Bewertungen
	 */
	public function countAdRatingsByUserId($userId, $conditionParam = array()) {
		$db = $this->getDb();
		$sqlWhere = array();
		
		if(isset($conditionParam['restrictSoldTypeAsSeller']) && $conditionParam['restrictSoldTypeAsSeller'] == true) { 
			$sqlWhere[] = " AND (ad_sold.FK_USER_VK = '".mysql_escape_string($userId)."') ";
		}
		if(isset($conditionParam['restrictSoldTypeAsBuyer']) && $conditionParam['restrictSoldTypeAsBuyer'] == true) { 
			$sqlWhere[] = " AND (ad_sold.FK_USER = '".mysql_escape_string($userId)."') ";
		}

		if(!isset($conditionParam['showCanceledRatings']) || $conditionParam['showCanceledRatings'] != true) {
			$sqlWhere[] = " AND (ad_sold_rating.RATING > 0) ";
		}
		
		$ratingCount = $db->fetch_atom("SELECT COUNT(*) as c FROM ad_sold_rating 
			JOIN ad_sold ON ad_sold.ID_AD_SOLD = ad_sold_rating.FK_AD_SOLD
			WHERE ad_sold_rating.FK_USER = '".mysql_escape_string($userId)."' ".implode("", $sqlWhere)."
		");
		
		return $ratingCount;
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