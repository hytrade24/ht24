<?php
/* ###VERSIONSBLOCKINLCUDE### */

class UserVersand {

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return UserVersand
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self($db);
		}
		return self::$instance;
	}

	private static $instance = null;

	/**
	 * Datenbankobjekt des ebiz-trader
	 *
	 * @var ebiz_db
	 */
	private $database = false;

	/**
	 * Konstruktor
	 *
	 * @param ebiz_db $db
	 */
	function __construct(ebiz_db $db) {
		$this->database = $db;
	}

	public function addAddress($company, $firstname, $lastname, $street, $zip, $city, $fk_country, $phone) {
		global $uid;
		$result = $this->database->querynow("INSERT INTO `user_versand` (FK_USER, COMPANY, FIRSTNAME, LASTNAME, STREET, ZIP, CITY, FK_COUNTRY, PHONE)
			VALUES (".(int)$uid.", '".mysql_escape_string($company)."', '".mysql_escape_string($firstname)."',
				'".mysql_escape_string($lastname)."', '".mysql_escape_string($street)."',
				'".mysql_escape_string($zip)."', '".mysql_escape_string($city)."', ".(int)$fk_country.",
				'".mysql_escape_string($phone)."')");
		if ($result['rsrc']) {
			return $result['int_result'];
		} else {
			return false;
		}
	}

	public function getAddress($id_user_versand) {
		global $uid, $langval;
		if ($id_user_versand == 0) {
			$ar_address = array(
				"ID_USER_VERSAND"	=> 0,
				"COMPANY"				=> $GLOBALS["user"]["FIRMA"],
				"FIRSTNAME"			=> $GLOBALS["user"]["VORNAME"],
				"LASTNAME"			=> $GLOBALS["user"]["NACHNAME"],
				"STREET"				=> $GLOBALS["user"]["STRASSE"],
				"ZIP"						=> $GLOBALS["user"]["PLZ"],
				"CITY"					=> $GLOBALS["user"]["ORT"],
				"COUNTRY"				=> $this->database->fetch_atom("SELECT s.V1 FROM country c
						LEFT JOIN string s ON S_TABLE='country' AND s.FK=c.ID_COUNTRY AND
							s.BF_LANG=if(c.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
						WHERE c.ID_COUNTRY=".(int)$GLOBALS["user"]["FK_COUNTRY"]),
				"FK_COUNTRY"		=> $GLOBALS["user"]["FK_COUNTRY"],
				"PHONE"					=> $GLOBALS["user"]["TEL"]
			);
			return $ar_address;
		} else {
			return $this->database->fetch1("SELECT u.*, s.V1 as COUNTRY
					FROM `user_versand` u
					LEFT JOIN country c ON c.ID_COUNTRY=u.FK_COUNTRY
					LEFT JOIN string s ON s.S_TABLE='country' AND s.FK=c.ID_COUNTRY AND
						s.BF_LANG=if(c.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
					WHERE u.FK_USER=".$uid." AND u.ID_USER_VERSAND=".(int)$id_user_versand);
		}
	}

	public function getAddresses() {
		global $uid, $langval;
		return $this->database->fetch_table("SELECT u.*, s.V1 as COUNTRY
				FROM `user_versand` u
				LEFT JOIN country c ON c.ID_COUNTRY=u.FK_COUNTRY
				LEFT JOIN string s ON s.S_TABLE='country' AND s.FK=c.ID_COUNTRY AND
					s.BF_LANG=if(c.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
				WHERE u.FK_USER=".$uid);
	}

	public function removeAddress($id_user_versand) {
		global $uid;
		$this->database->querynow("DELETE FROM `user_versand` WHERE ID_USER_VERSAND=".(int)$id_user_versand." AND FK_USER=".(int)$uid);
		$this->database->querynow("UPDATE `user` SET FK_USER_VERSAND=0 WHERE FK_USER_VERSAND=".(int)$id_user_versand);
	}

	public function updateAddress($id_user_versand, $company, $firstname, $lastname, $street, $zip, $city, $fk_country, $phone) {
		global $uid;
		$this->database->querynow("UPDATE `user_versand` SET
			COMPANY='".mysql_escape_string($company)."', FIRSTNAME='".mysql_escape_string($firstname)."',
			LASTNAME='".mysql_escape_string($lastname)."', STREET='".mysql_escape_string($street)."',
			ZIP='".mysql_escape_string($zip)."', CITY='".mysql_escape_string($city)."', FK_COUNTRY=".(int)$fk_country.",
			PHONE='".mysql_escape_string($phone)."'
			WHERE ID_USER_VERSAND=".(int)$id_user_versand." AND FK_USER=".(int)$uid);
	}

}

?>