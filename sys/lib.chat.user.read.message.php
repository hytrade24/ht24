<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.chat.user.php';


class ChatUserReadMessageManagement {
	private static $db;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ChatUserReadMessageManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function markMessageAsReadByChatUserId($chatMessageId, $chatUserId) {
        $db = $this->getDb();

        $db->update("chat_user_read_message", array(
            'FK_CHAT_MESSAGE' => $chatMessageId,
            'FK_CHAT_USER' => $chatUserId
        ));
    }

	public function markMessageAsUnReadByChatUserId($chatId, $chatUserId) {
		$db = $this->getDb();

		$db->querynow("DELETE curm FROM chat_user_read_message curm, chat_message cm WHERE cm.FK_CHAT = '".$chatId."' AND cm.ID_CHAT_MESSAGE = curm.FK_CHAT_MESSAGE AND FK_CHAT_USER = '".$chatUserId."'");
	}


    /**
     * Markiert alle Nachrichten einer Konversation für einen Teilnehmer als gelesen
     *
     * @param $chatId
     * @param $chatUserId
     * @return assoc
     */
    public function markAllMessagesInChatAsReadByChatUserId($chatId, $chatUserId) {
        $db = $this->getDb();

        $query = '
            INSERT INTO chat_user_read_message (FK_CHAT_MESSAGE, FK_CHAT_USER) SELECT
                m.ID_CHAT_MESSAGE, cu.ID_CHAT_USER
            FROM chat_message m
            JOIN chat c ON c.ID_CHAT = m.FK_CHAT
            LEFT JOIN chat_user cu ON cu.FK_CHAT = c.ID_CHAT
            LEFT JOIN chat_user_read_message curm ON curm.FK_CHAT_MESSAGE = m.ID_CHAT_MESSAGE
            WHERE
                cu.ID_CHAT_USER = "'.mysql_real_escape_string($chatUserId).'"
                AND m.APPROVED = 1
                AND m.SENDER != cu.ID_CHAT_USER
                AND m.FK_CHAT = "'.mysql_real_escape_string($chatId).'"
                AND curm.ID_CHAT_USER_READ_MESSAGE IS NULL
            GROUP BY m.ID_CHAT_MESSAGE
        ';

        return $db->querynow($query);
    }

    public function countUnreadMessagesByUserId($userId) {
        $db = $this->getDb();

        $query = '
            SELECT
                SQL_CALC_FOUND_ROWS m.ID_CHAT_MESSAGE
            FROM chat_message m
            JOIN chat c ON c.ID_CHAT = m.FK_CHAT
            LEFT JOIN chat_user cu ON cu.FK_CHAT = c.ID_CHAT
            LEFT JOIN chat_user_read_message curm ON curm.FK_CHAT_MESSAGE = m.ID_CHAT_MESSAGE
            WHERE
                cu.FK_USER = "'.mysql_real_escape_string($userId).'" AND cu.TYPE = 0
                AND m.APPROVED = 1
                AND m.SENDER != cu.ID_CHAT_USER
                AND curm.ID_CHAT_USER_READ_MESSAGE IS NULL
				AND cu.STATUS = "'.ChatUserManagement::STATUS_ACTIVE.'"
            GROUP BY m.ID_CHAT_MESSAGE
        ';

        $db->querynow($query);
        return $db->fetch_atom("SELECT FOUND_ROWS()");
    }

    public function countAllMessagesByUserId($userId) {
        $db = $this->getDb();

        $query = '
                SELECT
                    SQL_CALC_FOUND_ROWS m.ID_CHAT_MESSAGE
                FROM chat_message m
                JOIN chat c ON c.ID_CHAT = m.FK_CHAT
                LEFT JOIN chat_user cu ON cu.FK_CHAT = c.ID_CHAT
                WHERE
                    cu.FK_USER = "' . mysql_real_escape_string($userId) . '" AND cu.TYPE = 0
                    AND m.APPROVED = 1
                    AND m.SENDER != cu.ID_CHAT_USER
                    AND cu.STATUS = "'.ChatUserManagement::STATUS_ACTIVE.'"
                GROUP BY m.ID_CHAT_MESSAGE
            ';

        $db->querynow($query);
        return $db->fetch_atom("SELECT FOUND_ROWS()");
    }

    public function countUnreadMessagesExByUserId($userId) {
        $db = $this->getDb();

        $query = '
            SELECT
               COUNT(cm.ID_CHAT_MESSAGE) as COUNT_UNREAD,
               COUNT(c.FK_AD) as COUNT_UNREAD_AD
            FROM chat_user cu
            JOIN chat_message cm ON cm.FK_CHAT=cu.FK_CHAT AND cu.ID_CHAT_USER!=cm.SENDER AND cm.APPROVED = 1
            JOIN chat c ON c.ID_CHAT=cm.FK_CHAT
            WHERE cu.FK_USER='.(int)$userId.' AND cu.STATUS='.ChatUserManagement::STATUS_ACTIVE.'
            	AND NOT EXISTS (SELECT 1 FROM chat_user_read_message curm WHERE curm.FK_CHAT_MESSAGE=cm.ID_CHAT_MESSAGE)
            GROUP BY cu.FK_USER';
        $arResult = $db->fetch1($query);
        if (!is_array($arResult)) {
            $arResult = array("COUNT_UNREAD" => 0, "COUNT_UNREAD_AD" => 0);
        }
        return $arResult;
    }


    public function existUnreadMessagesInChatForUser($chatId, $userId) {
        $db = $this->getDb();

        $query = '
            SELECT
                SQL_CALC_FOUND_ROWS m.ID_CHAT_MESSAGE
            FROM chat_message m
            JOIN chat c ON c.ID_CHAT = m.FK_CHAT
            LEFT JOIN chat_user cu ON cu.FK_CHAT = c.ID_CHAT
            LEFT JOIN chat_user_read_message curm ON curm.FK_CHAT_MESSAGE = m.ID_CHAT_MESSAGE
            WHERE
                cu.FK_USER = "'.mysql_real_escape_string($userId).'" AND cu.TYPE = 0
                AND m.APPROVED = 1
                AND m.SENDER != cu.ID_CHAT_USER
                AND c.ID_CHAT = "'.mysql_real_escape_string($chatId).'"
                AND curm.ID_CHAT_USER_READ_MESSAGE IS NULL
            GROUP BY m.ID_CHAT_MESSAGE
        ';

        $db->querynow($query);
        return ($db->fetch_atom("SELECT FOUND_ROWS()") > 0);
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