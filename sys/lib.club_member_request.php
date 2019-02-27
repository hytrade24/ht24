<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.club.php';
require_once $ab_path.'sys/lib.user.php';

class ClubMemberRequestManagement {
	private static $db;
	private static $instance = NULL;

	const STATUS_REQUEST_OPEN = 0;
	const STATUS_REQUEST_BLOCKED = 2;


	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ClubMemberRequestManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === NULL) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	public function fetchById($clubMemberRequestId) {
		global $langval;
		$clubMemberRequest = $this->getDb()->fetch1("
			SELECT
				cmr.*
			FROM
				club_member_request cmr
			WHERE cmr.ID_CLUB_MEMBER_REQUEST = '" . (int)$clubMemberRequestId . "'");

		return $clubMemberRequest;
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
		$sqlOrder = " cmr.STAMP_CREATE ";


		if(isset($param['FK_USER']) && $param['FK_USER'] != NULL) { $sqlWhere .= " AND cmr.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
		if(isset($param['FK_CLUB']) && $param['FK_CLUB'] != NULL) { $sqlWhere .= " AND cmr.FK_CLUB = '".mysql_real_escape_string($param['FK_CLUB'])."' "; }
		if(isset($param['STATUS']) && $param['STATUS'] !== NULL) { $sqlWhere .= " AND cmr.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }

		if(isset($param['SORT_BY']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT_BY']." ".$param['SORT_DIR']; }
		if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
			if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
		}

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			$sqlFields = "cmr.ID_CLUB_MEMBER_REQUEST";
		} else {
			$sqlFields = "
				cmr.*,
				u.NAME as USER_NAME,
				u.ID_USER as USER_ID_USER,
				c.NAME as CLUB_NAME
			";
		}

		$query = "
			SELECT
				SQL_CALC_FOUND_ROWS
				".$sqlFields."
			FROM `club_member_request` cmr
			JOIN user u ON u.ID_USER = cmr.FK_USER
			JOIN club c ON c.ID_CLUB = cmr.FK_CLUB
			".$sqlJoin."
			WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
			GROUP BY cmr.ID_CLUB_MEMBER_REQUEST
			ORDER BY ".$sqlOrder."
				".($sqlLimit?'LIMIT '.$sqlLimit:'')."
		";

		return $query;
	}


	public function existMembershipRequest($clubId, $userId) {
		return $this->getDb()->fetch_atom("SELECT COUNT(*) as a FROM club_member_request WHERE FK_USER = '".(int)$userId."' AND FK_CLUB = '".(int)$clubId."' AND STATUS = '".self::STATUS_REQUEST_OPEN."'");

	}

	public function isMemberRequestBlocked($clubId, $userId) {
		return $this->getDb()->fetch_atom("SELECT COUNT(*) as a FROM club_member_request WHERE FK_USER = '".(int)$userId."' AND FK_CLUB = '".(int)$clubId."' AND STATUS = '".self::STATUS_REQUEST_BLOCKED."'");
	}

	public function addMembershipRequestForUser($clubId, $userId, $reason) {
		$db = $this->getDb();
		$clubManagement = ClubManagement::getInstance($this->getDb());

		if(!$this->isMemberRequestBlocked($clubId, $userId) && !$this->existMembershipRequest($clubId, $userId)) {
			$clubMemberRequestId = $db->update("club_member_request", array(
				'FK_CLUB' => (int)$clubId,
				'FK_USER' => $userId,
				'STAMP_CREATE' => date("Y-m-d H:i:s"),
				'REASON' => $reason,
				'STATUS' => self::STATUS_REQUEST_OPEN
			));


			// Mail Senden

			$mailData = array(
				'FK_USER' => $userId,
				'FK_CLUB' => $clubId,
				'CLUB_MEMBER_REQUEST_USER_ID' => $userId,
				'ID_CLUB_MEMBER_REQUEST' => $clubMemberRequestId
			);

			// Admin
			$club = $clubManagement->getClubById($clubId);
			$this->sendMailToUser($club['FK_USER'], 'CLUB_MEMBER_REQUEST', $mailData);

			// Mods
			$mods = $clubManagement->getMembersByClubId($clubId, FALSE, 1, 100, array(
				'cu.MODERATOR = 1'
			));
			foreach($mods as $key => $mod) {
				$this->sendMailToUser($mod['FK_USER'], 'CLUB_MEMBER_REQUEST', $mailData);
			}
		}


	}

	public function acceptMemberRequest($clubMemberRequestId) {
		$clubManagement = ClubManagement::getInstance($this->getDb());
		$clubMemberRequest = $this->fetchById($clubMemberRequestId);

		if($clubMemberRequest === NULL) {
			return FALSE;
		}

		$result = $clubManagement->addMember($clubMemberRequest['FK_CLUB'], $clubMemberRequest['FK_USER'], TRUE);

		if($result == TRUE) {
			$this->sendMailToUser($clubMemberRequest['FK_USER'], 'CLUB_MEMBER_REQUEST_ACCEPTED', array(
				'FK_USER' => $clubMemberRequest['FK_USER'],
				'FK_CLUB' => $clubMemberRequest['FK_CLUB'],
				'CLUB_MEMBER_REQUEST_USER_ID' => $clubMemberRequest['FK_USER'],
				'ID_CLUB_MEMBER_REQUEST' => $clubMemberRequestId
			));
			$this->getDb()->querynow("DELETE FROM club_member_request WHERE ID_CLUB_MEMBER_REQUEST = '".(int)$clubMemberRequestId."'");
			return TRUE;
		}

		return FALSE;
	}

	public function declineMemberRequest($clubMemberRequestId) {
		$clubMemberRequest = $this->fetchById($clubMemberRequestId);

		if($clubMemberRequest === NULL) {
			return FALSE;
		}

		$this->sendMailToUser($clubMemberRequest['FK_USER'], 'CLUB_MEMBER_REQUEST_DECLINED', array(
			'FK_USER' => $clubMemberRequest['FK_USER'],
			'FK_CLUB' => $clubMemberRequest['FK_CLUB'],
			'CLUB_MEMBER_REQUEST_USER_ID' => $clubMemberRequest['FK_USER'],
			'ID_CLUB_MEMBER_REQUEST' => $clubMemberRequestId
		));
		$this->getDb()->querynow("DELETE FROM club_member_request WHERE ID_CLUB_MEMBER_REQUEST = '".(int)$clubMemberRequestId."'");

		return TRUE;
	}

	public function blockMemberRequest($clubMemberRequestId) {
		$clubMemberRequest = $this->fetchById($clubMemberRequestId);

		$this->sendMailToUser($clubMemberRequest['FK_USER'], 'CLUB_MEMBER_REQUEST_DECLINED', array(
			'FK_USER' => $clubMemberRequest['FK_USER'],
			'FK_CLUB' => $clubMemberRequest['FK_CLUB'],
			'CLUB_MEMBER_REQUEST_USER_ID' => $clubMemberRequest['FK_USER'],
			'ID_CLUB_MEMBER_REQUEST' => $clubMemberRequestId
		));
		$this->getDb()->querynow("UPDATE club_member_request SET STATUS = '".self::STATUS_REQUEST_BLOCKED."' WHERE ID_CLUB_MEMBER_REQUEST = '".(int)$clubMemberRequestId."'");

		return TRUE;
	}

	public function sendMailToUser($userId, $mailType, $additionalData = array()) {
		$emailData = $this->_getEmailData($userId, $additionalData);
		sendMailTemplateToUser(0, $userId, $mailType, $emailData);
	}

	protected function _getEmailData($userId, $additionalData = array()) {
		$clubManagement = ClubManagement::getInstance($this->getDb());
		$userManagement = UserManagement::getInstance($this->getDb());

		$mailData = array();

		// Club
		if(is_array($additionalData) && ((int)$additionalData['FK_CLUB'] > 0 || (int)$additionalData['ID_CLUB'] > 0)) {
			$clubId = $additionalData['ID_CLUB']?(int)$additionalData['ID_CLUB']:(int)$additionalData['FK_CLUB'];
			$club = $clubManagement->getClubById($clubId);

			foreach($club as $key => $value) {
				$mailData['CLUB_'.$key] = $value;
			}
		}

		// Request
		if(is_array($additionalData) && ((int)$additionalData['FK_CLUB_MEMBER_REQUEST'] > 0 || (int)$additionalData['ID_CLUB_MEMBER_REQUEST'] > 0)) {
			$clubMemberRequestId = $additionalData['ID_CLUB_MEMBER_REQUEST']?(int)$additionalData['ID_CLUB_MEMBER_REQUEST']:(int)$additionalData['FK_CLUB_MEMBER_REQUEST'];
			$clubMemberRequest = $this->fetchById($clubMemberRequestId);

			foreach($clubMemberRequest as $key => $value) {
				$mailData['REQUEST_'.$key] = $value;
			}
		}

		// Requestor
		if(is_array($additionalData) && ((int)$additionalData['CLUB_MEMBER_REQUEST_USER_ID'] > 0)) {
			$clubMemberRequestorId = (int)$additionalData['CLUB_MEMBER_REQUEST_USER_ID'];
			$clubMemberRequestor = $userManagement->fetchById($clubMemberRequestorId);

			foreach($clubMemberRequestor as $key => $value) {
				$mailData['REQUESTOR_'.$key] = $value;
			}
		}


		// User
		$userData = $userManagement->fetchById($userId);
		foreach($userData as $key => $value) {
			$mailData['USER_'.$key] = $value;
		}


		return array_merge($mailData, $additionalData);
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
