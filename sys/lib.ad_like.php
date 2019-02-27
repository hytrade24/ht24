<?php
/* ###VERSIONSBLOCKINLCUDE### */



class AdLikeManagement {
	private static $db;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return AdLikeManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}
	/**
	 * User mit ID $userID gefällt die Anzeige $adId
	 *
	 * @param int $userId
	 * @param int $adId
	 */
	public function like($userId, $adId) {
		$db = $this->getDb();

		if(!$this->_existsAdId($adId)) { throw new Exception("Ad ID doesn't exist."); }
		if(!$this->_existsUserId($userId)) { throw new Exception("User ID doesn't exist."); }

		if (!$this->isLike( $userId, $adId )) {
			$db->querynow( "INSERT INTO ad_likes (FK_USER, FK_AD) VALUES ('" . mysql_escape_string( $userId ) . "', '" . mysql_escape_string( $adId ) . "')" );
			$this->updateAdLikeRedundance($adId);
		}
	}

	/**
	 * User $userId gefällt die Anzeige $adId nicht mehr
	 *
	 * @param int $userId
	 * @param int $adId
	 */
	public function unlike($userId, $adId) {
		$db = $this->getDb();

		if(!$this->_existsAdId($adId)) { throw new Exception("Ad ID doesn't exist."); }
		if(!$this->_existsUserId($userId)) { throw new Exception("User ID doesn't exist."); }

		if ($this->isLike( $userId, $adId )) {
			$db->querynow( "DELETE FROM ad_likes WHERE FK_USER = '" . mysql_escape_string( $userId ) . "' AND FK_AD = '" . mysql_escape_string( $adId ) . "'" );
			$this->updateAdLikeRedundance($adId);
		}
	}

	/**
	 * Wechselt zwischen Gefallen und Nichtgefallen von User $userId und Anzeige $adId
	 *
	 * @param int $userId
	 * @param int $adId
	 */
	public function toggleLike($userId, $adId) {
		if ($this->isLike( $userId, $adId )) {
			$this->unlike( $userId, $adId );
		} else {
			$this->like( $userId, $adId );
		}

        $this->_cache();  //Cache neu schriben
	}

	/**
	 * Überprüft ob dem User $userId die Anzeige $adId gefällt
	 *
	 * @param int $userId
	 * @param int $adId
	 *
	 * @return boolean
	 */
	public function isLike($userId, $adId) {
		$db = $this->getDb();

		$isLike = $db->fetch_atom( "SELECT (COUNT(*) > 0) as isLike FROM ad_likes WHERE FK_USER = '" . mysql_escape_string( $userId ) . "' AND FK_AD = '" . mysql_escape_string( $adId ) . "' " );
		return ($isLike == "1");
	}

	/**
	 * Zählt die Anzahl der Gefällt mir Klicks
	 *
	 * @param int $adId
	 * @return int Anzahl der Gefällt mir Klicks
	 */
	public function countLikesByAdId($adId) {
		$db = $this->getDb();
		$likeCount = $db->fetch_atom("SELECT COUNT(*) as likeCount FROM ad_likes WHERE FK_AD = '" . mysql_escape_string( $adId ) . "' " );

		return $likeCount;
	}

	private function updateAdLikeRedundance($adId) {
		$db = $this->getDb();
		$likeCount = $this->countLikesByAdId($adId);

		$db->querynow("UPDATE ad_master SET AD_LIKES = '".mysql_escape_string($likeCount)."' WHERE ID_AD_MASTER = '" . mysql_escape_string( $adId ) . "'");
		return true;
	}

	/**
	 * @param int $userId
	 * @TODO: Auslagern
	 */
	private function _existsUserId($userId) {
		$db = $this->getDb();

		$isUser = $db->fetch_atom( "SELECT (COUNT(*) > 0) as existsUser FROM user WHERE ID_USER = '" . mysql_escape_string( $userId ) . "' " );
		return ($isUser == "1");
	}

	/**
	 * @param int $adId
	 * @TODO: Auslagern
	 */
	private function _existsAdId($adId) {
		$db = $this->getDb();

		$isAd = $db->fetch_atom( "SELECT (COUNT(*) > 0) as existsAd FROM ad_master WHERE ID_AD_MASTER = '" . mysql_escape_string( $adId ) . "' " );
		return ($isAd == "1");
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

    public function flushCache() {
        return $this->_cache();
    }

    private function _cache() {
        global $ab_path,$s_lang;
        $db = $this->getDb();
        $file_name = $ab_path.'cache/marktplatz/adlike/adlikes.htm';
        $liste = $db->fetch_table("select ad_likes.FK_AD,ad_likes.FK_USER ,u.NAME,u.CACHE ,ai.SRC_THUMB,PRODUKTNAME
from ad_likes
left join user u on FK_USER = ID_USER
left join ad_images ai on  ad_likes.FK_AD=ai.FK_AD and ai.IS_DEFAULT=1
left join ad_master am on  ad_likes.FK_AD=ID_AD_MASTER
order by DATESTAMP DESC limit 4");
        $tmp = new Template($ab_path."tpl/".$s_lang."/cache_adlikes.htm");
        $tmp->addlist("wholikes", $liste, $ab_path."tpl/".$s_lang."/cache_adlikes.row.htm");
		@file_put_contents($file_name, $tmp->process());
		@chmod($file_name, 0777);
    }

}