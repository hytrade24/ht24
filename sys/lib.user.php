<?php
/* ###VERSIONSBLOCKINLCUDE### */



class UserManagement {
	private static $db;
	private static $instance = null;

	/**
	 * Singleton 
	 * 
	 * @param ebiz_db $db
	 * @return UserManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);
		
		return self::$instance;
	}

    public function createVirtualUser($ar_user, $allowUpdate = false) {
        global $ab_path;
        // Prevent updating other users
        unset($ar_user["ID_USER"]);
        $ar_user["IS_VIRTUAL"] = 1;
        $ar_user["NAME"] = preg_replace("/([^A-Za-z0-9_])/", "", substr($ar_user['EMAIL'], 0, strpos($ar_user['EMAIL'], "@")));
        // Check if email address is already taken
        $id_user = false;
        $ar_user_db = $this->getDb()->fetch1("SELECT ID_USER, NAME, STAMP_REG, IS_VIRTUAL FROM `user` WHERE EMAIL='".mysql_real_escape_string($ar_user['EMAIL'])."'");
        if ($ar_user_db["ID_USER"] > 0) {
            $id_user = $ar_user_db["ID_USER"];
            $ar_user["NAME"] = $ar_user_db["NAME"];
            $ar_user["STAMP_REG"] = $ar_user_db["STAMP_REG"];
        } else {
            $ar_user['SALT'] = pass_generate_salt();
            $ar_user["STAMP_REG"] = date("Y-m-d");
            // Ensure username is not taken yet
            $ar_user_names = $this->getDb()->fetch_nar("SELECT ID_USER, NAME FROM `user` WHERE NAME LIKE '".mysql_real_escape_string($ar_user["NAME"])."%'");
            if (in_array($ar_user["NAME"], $ar_user_names)) {
                $suffix = 2;
                while (in_array($ar_user["NAME"].$suffix, $ar_user_names)) {
                    $suffix++;
                }
                $ar_user["NAME"] = $ar_user["NAME"].$suffix;
            }
        }
        if ($allowUpdate) {
            if ($id_user > 0) {
                if ($ar_user_db['IS_VIRTUAL'] == 1) {
                    // Update existing virtual user
                    $ar_user['ID_USER'] = $id_user;
                    $this->getDb()->update("user", $ar_user);
                } else {
                    // User exists, but is not virtual.
                    return false;
                }
            } else {
                // New user
                $id_user = $this->getDb()->update("user", $ar_user);
                $cacheDir = $this->getDb()->fetch_atom("SELECT CACHE FROM `user` WHERE ID_USER=".$id_user);
                // Create cache directory
                mkdir($ab_path."cache/users/".$cacheDir."/".$id_user, 0777);  //Users Cacheverzeichnis
                chmod($ab_path."cache/users/".$cacheDir."/".$id_user, 0777);  // rechte richig setzen
                // Userbild kopieren
                $image_default = $ab_path."uploads/users/no.jpg";
                $image_gender = $ab_path."uploads/users/no_".$ar_user["LU_ANREDE"].".jpg";
                if (file_exists($image_gender)) {
                    copy($image_gender, $ab_path."cache/users/".$cacheDir."/".$id_user."/".$id_user.".jpg");
                } else {
                    copy($image_default, $ab_path."cache/users/".$cacheDir."/".$id_user."/".$id_user.".jpg");
                }
                $imagePrev_default = $ab_path."uploads/users/no_s.jpg";
                $imagePrev_gender = $ab_path."uploads/users/no_".$ar_user["LU_ANREDE"]."_s.jpg";
                if (file_exists($imagePrev_gender)) {
                    copy($imagePrev_gender, $ab_path."cache/users/".$cacheDir."/".$id_user."/".$id_user."_s.jpg");
                } else {
                    copy($imagePrev_default, $ab_path."cache/users/".$cacheDir."/".$id_user."/".$id_user."_s.jpg");
                }
                copy($ab_path."uploads/users/no_s.jpg", $ab_path."cache/users/".$cacheDir."/".$id_user."/".$id_user."_s.jpg");
                chmod($ab_path."cache/users/".$cacheDir."/".$id_user."/".$id_user.".jpg", 0777);
                chmod($ab_path."cache/users/".$cacheDir."/".$id_user."/".$id_user."_s.jpg", 0777);
            }
        } else {
            if ($id_user > 0) {
                // User exists, but update forbidden.
                return false;
            } else {
                // Create new user
                $id_user = $this->getDb()->update("user", $ar_user);
            }
        }
        return ($id_user > 0 ? $id_user : false);
    }

    public function fetchById($userId) {
        global $langval;

        $result = $this->getDb()->fetch1("
            SELECT
                u.*,
                (SELECT V1 FROM string WHERE S_TABLE = 'country' AND FK = u.FK_COUNTRY AND BF_LANG = '".$langval."') AS LAND
            FROM user u
            WHERE ID_USER = '".(int) $userId."'
        ");

        return $result;
    }

	public function fetchFullDatasetById($userId) {
		global $langval;
		$db = $this->getDb();

		$result = $db->fetch1($x = "
			SELECT
				u.ID_USER,
				v.ID_VENDOR,
				u.NAME AS USER_NAME,
				u.VORNAME,
				u.NACHNAME,
				u.UST_ID,
				u.EMAIL,
				u.CACHE,
				v.CHANGED,
				v.LATITUDE,
				v.LONGITUDE,
				v.STATUS as VENDOR_STATUS,
				IF(v.LOGO != '', CONCAT('/cache/vendor/logo/', v.LOGO), CONCAT('/cache/users/', u.CACHE, '/', u.ID_USER, '/', u.ID_USER, '.jpg')) AS LOGO,
				IF(v.STRASSE != '', v.STRASSE, u.STRASSE) AS STRASSE,
				IF(v.PLZ != '', v.PLZ, u.PLZ) AS PLZ,
				IF(v.ORT != '', v.ORT, u.ORT) AS ORT,
				IF(v.PLZ != '', v.PLZ, u.PLZ) AS PLZ,
				IF(v.TEL != '', v.TEL, u.TEL) AS TEL,
				IF(v.FAX != '', v.FAX, u.FAX) AS FAX,
				IF(v.URL != '', v.URL, u.URL) AS URL,
				IF(v.NAME != '', v.NAME, u.FIRMA) AS FIRMA,
				sc.V1 AS COUNTRY,
				uc.AGB AS AGB,
				uc.WIDERRUF AS WIDERRUF,
				uc.ZAHLUNG AS ZAHLUNGSINFORMATION
			FROM
				user u
			LEFT JOIN vendor v ON u.ID_USER = v.FK_USER
			LEFT JOIN string sc ON sc.FK = IF(v.FK_COUNTRY != '', v.FK_COUNTRY, u.FK_COUNTRY) AND sc.S_TABLE = 'country' AND sc.BF_LANG = '".$langval."'
			LEFT JOIN usercontent uc ON u.ID_USER = uc.FK_USER
			WHERE
				u.ID_USER = '".(int)$userId."'
			");

		return $result;
	}

    /**
     * Prüft ob ein User mit der Id $userId im System online ist
     *
     * @param int $userId Id des Benutzers
     * @return boolean
     */
    public function isUserOnline($userId) {
        $db = $this->getDb();

        $isOnline = $db->fetch_atom("SELECT COUNT(*) as a FROM useronline WHERE ID_USER = '".mysql_real_escape_string($userId)."'");
        return ($isOnline > 0);
    }

    /**
     * Prüft ob ein User mit der Id $userId im System online ist
     *
     * @param int $userId Id des Benutzers
     * @return boolean
     */
    public function isUserVirtual() {
        global $uid, $user;
        if (is_array($user)) {
            if ($user["ID_USER"] == 0) {
                list($accessUser, $accessHash) = explode("!", $_SESSION['TRADER_USER_ACCESS_HASH']);
                return ($uid == $accessUser ? true : false);
            } else {
                return ($user['IS_VIRTUAL'] > 0 ? true : false);
            }
        } else {
            return ($uid > 0);
        }
    }

	public function setPassword($userId, $password) {
		global $nar_systemsettings, $ab_path, $db;

		$salt = pass_generate_salt();
		$passwordCrypted = pass_encrypt($password, $salt);

		if ($nar_systemsettings["SITE"]["FORUM_VB"]) {
			$id_vb_user = $db->fetch_atom("SELECT VB_USER FROM `user` WHERE ID_USER=".(int)$userId);
			if ($id_vb_user > 0) {
				// vBulletin-Forum wird integriert
				require_once $ab_path.'sys/lib.forum_vb.php';
				$apiForum = new ForumVB();
				if (!empty($password)) {
					$apiForum->SetUserPassword($id_vb_user, $password);
				}
			}
		}

		$db->update("user", array(
			'ID_USER' => $userId,
			'PASS' => $passwordCrypted,
			'SALT' => $salt
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