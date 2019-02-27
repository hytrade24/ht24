<?php
/* ###VERSIONSBLOCKINLCUDE### */



class UserContactManagement {
	private static $db;
	private static $instance = null;

	const EMAIL_TEMPLATE_REQUEST = "USER_CONTACT_REQUEST";
	const EMAIL_TEMPLATE_REQUEST_ACCEPT = "USER_CONTACT_REQUEST_ACCEPT";
	const EMAIL_TEMPLATE_REQUEST_DECLINE = "USER_CONTACT_REQUEST_DECLINE";

	const STATUS_ACCEPTED = 1;
	const STATUS_REQUESTED = 2;
	const STATUS_DECLINED = 0;
	

	private function __construct() {
	}
	private function __clone() {
	}

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return UserContactManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	/**
	 * Startet eine neue Kontaktanfrage zwischen dem User $requestorUserId und dem User $acceptorUserId
	 * Optional wird eine Nachricht $requestMessage mitgesendet
	 *
	 * @param int $requestorUserId
	 * @param int $acceptorUserId
	 * @param string $requestMessage
	 * @return boolean
	 * @throws Exception
	 */
	public function requestUserContact($requestorUserId, $acceptorUserId, $requestMessage = "") {
		$db = $this->getDb();

		if(($requestorUserId != $acceptorUserId) && !$this->existsUserContact($requestorUserId, $acceptorUserId)) {
			$db->querynow("INSERT INTO user_contact (FK_USER_A, FK_USER_B, STATUS, STAMP_REQUEST) VALUES('".mysql_real_escape_string($requestorUserId)."', '".mysql_real_escape_string($acceptorUserId)."', ".self::STATUS_REQUESTED.", '".date("Y-m-d H:i:s")."')");
			$userContactId = (int) $db->fetch_atom("SELECT LAST_INSERT_ID()");

			$this->sendEmailRequest($userContactId, $requestMessage);

			return true;
		} else {
			throw new Exception("UserContactRelation already exists");
		}
	}

	public function acceptRequest($userContactId) {
		$db = $this->getDb();
		$db->querynow("UPDATE user_contact SET STATUS = ".self::STATUS_ACCEPTED.", STAMP_RESPONSE =  '".date("Y-m-d H:i:s")."' WHERE ID_USER_CONTACT = '".mysql_real_escape_string($userContactId)."'");

		$this->sendEmailRequestAccept($userContactId);
	}

	public function declineRequest($userContactId) {
		$db = $this->getDb();
		$db->querynow("UPDATE user_contact SET STATUS = ".self::STATUS_DECLINED.", STAMP_RESPONSE =  '".date("Y-m-d H:i:s")."' WHERE ID_USER_CONTACT = '".mysql_real_escape_string($userContactId)."'");
	}

	public function removeUserContact($userContactId) {
		$db = $this->getDb();
		$db->querynow("DELETE FROM user_contact WHERE ID_USER_CONTACT = '".mysql_real_escape_string($userContactId)."'");
	}

	public function existsUserContact($userIdA, $userIdB, $requestStatus = null) {
		$db = $this->getDb();

		$whereRequestStatus = "";
		if($requestStatus !== null) {
			$whereRequestStatus = " AND STATUS = '".mysql_real_escape_string($requestStatus)."' ";
		}

		$is = $db->fetch_atom("SELECT COUNT(*) as a FROM user_contact WHERE ((FK_USER_A = '".$userIdA."' AND FK_USER_B = '".$userIdB."') OR (FK_USER_A = '".$userIdB."' AND FK_USER_B = '".$userIdA."')) ".$whereRequestStatus);
		return ($is > 0);
	}

	/**
	 * Prüft ob es eine Kontaktanfrage mit der Id $userContactId für den User $userId existiert
	 *
	 * @param int $userContactId
	 * @param int $userId
	 * @return boolean
	 */
	public function existsUserContactRequestByIdAndAcceptorUserId($userContactId, $acceptorUserId) {
		$db = $this->getDb();

		$is = $db->fetch_atom("SELECT COUNT(*) as a FROM user_contact WHERE ID_USER_CONTACT = '".$userContactId."' AND FK_USER_B = '".mysql_real_escape_string($acceptorUserId)."'");
		return ($is > 0);
	}

    public function existsUserContactRequestByIdAndRequestorUserId($userContactId, $requestorUserId) {
        $db = $this->getDb();

        $is = $db->fetch_atom("SELECT COUNT(*) as a FROM user_contact WHERE ID_USER_CONTACT = '".$userContactId."' AND FK_USER_A = '".mysql_real_escape_string($requestorUserId)."'");
        return ($is > 0);
    }

	public function countUserContactsByUserId($userId, $requestStatus = null) {
		$db = $this->getDb();

		$whereRequestStatus = "";
		if($requestStatus !== null) {
			$whereRequestStatus = " AND STATUS = '".mysql_real_escape_string($requestStatus)."' ";
		}

		$count = $db->fetch_atom("SELECT COUNT(*) as a FROM user_contact WHERE (FK_USER_A = '".$userId."' OR FK_USER_B = '".$userId."') ".$whereRequestStatus);
		return $count;
	}

    public function countUserContactsByAcceptorUserId($acceptorUserId, $requestStatus = null) {
        $db = $this->getDb();

        $whereRequestStatus = "";
        if($requestStatus !== null) {
            $whereRequestStatus = " AND STATUS = '".mysql_real_escape_string($requestStatus)."' ";
        }

        $count = $db->fetch_atom("SELECT COUNT(*) as a FROM user_contact WHERE FK_USER_B = '".mysql_real_escape_string($acceptorUserId)."' ".$whereRequestStatus);
        return $count;
    }

    public function countUserContactsBySenderUserId($acceptorUserId, $requestStatus = null) {
        $db = $this->getDb();

        $whereRequestStatus = "";
        if($requestStatus !== null) {
            $whereRequestStatus = " AND STATUS = '".mysql_real_escape_string($requestStatus)."' ";
        }

        $count = $db->fetch_atom("SELECT COUNT(*) as a FROM user_contact WHERE FK_USER_A = '".mysql_real_escape_string($acceptorUserId)."' ".$whereRequestStatus);
        return $count;
    }

	public function fetchUserContactById($userContactId) {
		$db = $this->getDb();

		$data = $db->fetch1("
			SELECT
				c.*,
				f.ID_USER as FROM_USER_ID, f.VORNAME as FROM_USER_VORNAME, f.NACHNAME as FROM_USER_NACHNAME, f.NAME as FROM_NAME,
				t.ID_USER as TO_USER_ID, t.VORNAME as TO_USER_VORNAME, t.NACHNAME as TO_USER_NACHNAME, t.NAME as TO_NAME
			FROM user_contact c
			LEFT JOIN user AS f ON f.ID_USER = c.FK_USER_A
			LEFT JOIN user AS t ON t.ID_USER = c.FK_USER_B
			WHERE c.ID_USER_CONTACT = '".mysql_real_escape_string($userContactId)."'");

		return $data;
	}

	public function fetchUserContactsByUserId($userId, $requestStatus = self::STATUS_ACCEPTED, $limit = null) {
		$db = $this->getDb();

		$limitSql = "";
		if($limit !== null) {
			$limitSql = " LIMIT ".mysql_real_escape_string($limit)." ";
		}

		$data = $db->fetch_table("
			SELECT
				c.*,
				t.ID_USER as USER_ID, t.VORNAME as USER_VORNAME, t.NACHNAME as USER_NACHNAME, t.NAME as USER_NAME
			FROM user_contact c
			LEFT JOIN user AS t ON t.ID_USER = c.FK_USER_B
			WHERE c.FK_USER_A = '".mysql_real_escape_string($userId)."' AND STATUS = ".$requestStatus."
			UNION SELECT
				c.*,
				t.ID_USER as USER_ID, t.VORNAME as USER_VORNAME, t.NACHNAME as USER_NACHNAME, t.NAME as USER_NAME
			FROM user_contact c
			LEFT JOIN user AS t ON t.ID_USER = c.FK_USER_A
			WHERE c.FK_USER_B = '".mysql_real_escape_string($userId)."' AND STATUS = ".$requestStatus."
			ORDER BY USER_NACHNAME ".$limitSql."
		");

		return $data;
	}

	public function fetchUserContactsByAcceptorUserId($userId, $requestStatus = self::STATUS_REQUESTED, $limit = null) {
		$db = $this->getDb();

		$limitSql = "";
		if($limit !== null) {
			$limitSql = " LIMIT ".mysql_real_escape_string($limit)." ";
		}

		$data = $db->fetch_table("
			SELECT
				c.*,
				t.ID_USER as USER_ID, t.VORNAME as USER_VORNAME, t.NACHNAME as USER_NACHNAME, t.NAME as USER_NAME
			FROM user_contact c
			LEFT JOIN user AS t ON t.ID_USER = c.FK_USER_A
			WHERE c.FK_USER_B = '".mysql_real_escape_string($userId)."' AND STATUS = ".$requestStatus."
			ORDER BY USER_NACHNAME ".$limitSql."
			");

		return $data;
	}

	public function fetchUserContactsBySenderUserId($userId, $requestStatus = self::STATUS_REQUESTED, $limit = null) {
		$db = $this->getDb();

		$limitSql = "";
		if($limit !== null) {
			$limitSql = " LIMIT ".mysql_real_escape_string($limit)." ";
		}

		$data = $db->fetch_table("
			SELECT
				c.*,
				t.ID_USER as USER_ID, t.VORNAME as USER_VORNAME, t.NACHNAME as USER_NACHNAME, t.NAME as USER_NAME
			FROM user_contact c
			LEFT JOIN user AS t ON t.ID_USER = c.FK_USER_B
			WHERE c.FK_USER_A = '".mysql_real_escape_string($userId)."' AND STATUS = ".$requestStatus."
			ORDER BY USER_NACHNAME ".$limitSql."
			");

		return $data;
	}

	/**
	 * Versendet die E-Mail der Kontaktanfrage
	 *
	 * @TODO global systemsettings auslagern
	 *
	 * @param int $userContactId Id der Kontaktanfrage
	 * @throws Exception
	 */
	private function sendEmailRequest($userContactId, $requestMessage = "") {
		global $nar_systemsettings;
		$db = $this->getDb();

		$emailData = $db->fetch1("
			SELECT
				c.ID_USER_CONTACT, c.FK_USER_B,
				f.ID_USER as FROM_USER_ID, f.VORNAME as FROM_USER_VORNAME, f.NACHNAME as FROM_USER_NACHNAME, f.NAME as FROM_NAME,
				t.ID_USER as TO_USER_ID, t.VORNAME as TO_USER_VORNAME, t.NACHNAME as TO_USER_NACHNAME, t.NAME as TO_NAME
			FROM user_contact c
			LEFT JOIN user AS f ON f.ID_USER = c.FK_USER_A
			LEFT JOIN user AS t ON t.ID_USER = c.FK_USER_B
			WHERE c.ID_USER_CONTACT = '".mysql_escape_string($userContactId)."'");

		if($emailData == null) {
			throw new Exception("User not found");
		}

		$emailData['MESSAGE'] = $requestMessage;

      	sendMailTemplateToUser(0, $emailData['TO_USER_ID'], self::EMAIL_TEMPLATE_REQUEST, $emailData);

	}

	/**
	 * Versendet eine E-Mail bei Annahme der Kontaktanfrage
	 *
	 * @TODO global systemsettings auslagern
	 *
	 * @param int $userContactId Id der Kontaktanfrage
	 * @throws Exception
	 */
	private function sendEmailRequestAccept($userContactId, $requestMessage = "") {
		global $nar_systemsettings;
		$db = $this->getDb();

		$emailData = $db->fetch1("
			SELECT
				c.ID_USER_CONTACT, c.FK_USER_B,
				f.ID_USER as FROM_USER_ID, f.VORNAME as FROM_USER_VORNAME, f.NACHNAME as FROM_USER_NACHNAME, f.NAME as FROM_NAME,
				t.ID_USER as TO_USER_ID, t.VORNAME as TO_USER_VORNAME, t.NACHNAME as TO_USER_NACHNAME, t.NAME as TO_NAME
			FROM user_contact c
			LEFT JOIN user AS f ON f.ID_USER = c.FK_USER_A
			LEFT JOIN user AS t ON t.ID_USER = c.FK_USER_B
			WHERE c.ID_USER_CONTACT = '".mysql_escape_string($userContactId)."'");

		if($emailData == null) {
			throw new Exception("User not found");
		}

		$emailData['MESSAGE'] = $requestMessage;

      	sendMailTemplateToUser(0, $emailData['TO_USER_ID'], self::EMAIL_TEMPLATE_REQUEST_ACCEPT, $emailData);

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
	public static function setDb(ebiz_db $db) {
		self::$db = $db;
	}
}
