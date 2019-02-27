<?php
/* ###VERSIONSBLOCKINLCUDE### */



class ChatUserManagement {
	private static $db;
	private static $instance = null;

	const STATUS_ACTIVE = 1;
	const STATUS_DELETED = 2;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ChatUserManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function fetchAllChatUserByChatId($chatId) {
        $db = $this->getDb();


        return $db->fetch_table("
            SELECT
                *
            FROM chat_user
            WHERE
                FK_CHAT = '".mysql_real_escape_string($chatId)."'
        ");
    }

    public function fetchVirtualUserByChatId($chatId) {
        $db = $this->getDb();
        $user = $db->fetch1("SELECT cu.*, cuv.ID_CHAT_USER_VIRTUAL, cuv.NAME, cuv.EMAIL FROM chat_user cu JOIN chat_user_virtual cuv ON cuv.ID_CHAT_USER_VIRTUAL = cu.FK_CHAT_USER_VIRTUAL WHERE cu.FK_CHAT = '".mysql_real_escape_string($chatId)."' AND cu.TYPE = 1");

        return $user;
    }

    public function existUserForChatId($chatId, $userId) {
        $db = $this->getDb();

        $exist = $db->fetch_atom("SELECT COUNT(*) FROM chat_user WHERE FK_CHAT = '".mysql_real_escape_string($chatId)."' AND FK_USER = '".mysql_real_escape_string($userId)."' AND TYPE = 0 ");

        return ($exist > 0);
    }

    public function existVirtualUserForChatId($chatId, $email) {
        $db = $this->getDb();

        $exist = $db->fetch_atom("SELECT COUNT(*) FROM chat_user u LEFT JOIN chat_user_virtual v ON v.ID_CHAT_USER_VIRTUAL = u.FK_CHAT_USER_VIRTUAL WHERE u.FK_CHAT = '".mysql_real_escape_string($chatId)."' AND v.EMAIL = '".mysql_real_escape_string($email)."' AND u.TYPE = 1 GROUP BY u.ID_CHAT_USER");

        return ($exist > 0);
    }

    public function find($id) {
        $db = $this->getDb();

        $user = $db->fetch1("SELECT * FROM chat_user WHERE ID_CHAT_USER = '".mysql_real_escape_string($id)."'");

        return $user;
    }

    public function findUser($chatId, $userId) {
        $db = $this->getDb();

        $user = $db->fetch1("SELECT * FROM chat_user WHERE FK_CHAT = '".mysql_real_escape_string($chatId)."' AND FK_USER = '".mysql_real_escape_string($userId)."' AND TYPE = 0");

        return $user;
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