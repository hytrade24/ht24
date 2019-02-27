<?php
/* ###VERSIONSBLOCKINLCUDE### */




class ChatMessageManagement {
	private static $db;
	private static $instance = null;

	const MODE_INBOX = 1;
	const MODE_OUTBOX = 2;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ChatMessageManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function countByChatId($chatId) {
        $db = $this->getDb();
        return $db->fetch_atom("SELECT COUNT(*) FROM chat_message WHERE FK_CHAT = ".(int)$chatId);
    }

    public function setApprove($chatMessageId, $approve = true) {
        $db = $this->getDb();
        $db->querynow("UPDATE chat_message SET APPROVED = '".mysql_real_escape_string(($approve?1:0))."' WHERE ID_CHAT_MESSAGE='".mysql_real_escape_string($chatMessageId)."'");
    }

    public function find($chatMessageId) {
        $db = $this->getDb();
        return $db->fetch1("SELECT * FROM chat_message WHERE ID_CHAT_MESSAGE = '".$chatMessageId."'");
    }

    public function fetchAllByParam($param) {
        $db = $this->getDb();


        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " m.STAMP_CREATE DESC ";

        if(isset($param['FK_CHAT']) && $param['FK_CHAT'] != null) { $sqlWhere .= ' AND c.ID_CHAT = "'.mysql_real_escape_string($param['FK_CHAT']).'" '; }
        if(isset($param['HASH']) && $param['HASH'] != null) { $sqlWhere .= ' AND c.HASH = "'.mysql_real_escape_string($param['HASH']).'" '; }
        if(isset($param['CHAT_USER_ID']) && $param['CHAT_USER_ID'] != null) {
            $sqlWhere .= ' AND cu.FK_USER = "'.mysql_real_escape_string($param['CHAT_USER_ID']).'" ';
        }

        if(isset($param['APPROVED_CHAT_USER']) && $param['APPROVED_CHAT_USER'] != null) { $sqlWhere .= ' AND (m.APPROVED = 1 OR m.SENDER = "'.mysql_real_escape_string($param['APPROVED_CHAT_USER']).'") '; }
        if(isset($param['APPROVED']) && $param['APPROVED'] !== null) { $sqlWhere .= ' AND m.APPROVED = '.($param['APPROVED']?1:0).' '; }

        $q = "SELECT
                m.*
            FROM
                chat_message m
            JOIN chat c ON m.FK_CHAT = c.ID_CHAT
            LEFT JOIN chat_user cu ON cu.FK_CHAT = c.ID_CHAT
            WHERE
                1=1
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY m.ID_CHAT_MESSAGE
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."

        ";


        $result =  $db->fetch_table($q);
        return $result;
    }

	public function fetchLastMessageByChatAndUser($chatId, $userId, $mode = NULL) {
		$db = $this->getDb();
		$sqlWhere = '';

		if($mode !== NULL && $mode === self::MODE_INBOX) {
			$sqlWhere .= " AND (u.FK_USER != '".$userId."'  OR u.FK_USER IS NULL) ";
		} else if($mode !== NULL && $mode === self::MODE_OUTBOX) {
			$sqlWhere .= " AND u.FK_USER = '".$userId."' ";
		}

		return $db->fetch1("
			SELECT
				m.*
			FROM chat_message m
			JOIN chat_user u ON u.ID_CHAT_USER = m.SENDER
			WHERE
				m.FK_CHAT = '".mysql_real_escape_string($chatId)."'
				AND (m.APPROVED = 1 || u.FK_USER = '".$userId."')
				".$sqlWhere."
			ORDER BY
				STAMP_CREATE DESC
			LIMIT 1");
	}

    public function deleteById($id) {
        $db = $this->getDb();

        $db->querynow("DELETE FROM chat_message WHERE ID_CHAT_MESSAGE = '".mysql_real_escape_string($id)."'");

        return true;
    }

    public function updateById($chatMessageId, $data) {
        $db = $this->getDb();

        $data['ID_CHAT_MESSAGE'] = $chatMessageId;

        return $db->update("chat_message", $data);
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