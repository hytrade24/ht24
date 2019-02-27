<?php
/* ###VERSIONSBLOCKINLCUDE### */



class ChatUserVirtualManagement {
	private static $db;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ChatUserVirtualManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function get($email, $name) {
        $virtualUser = $this->fetchVirtualUserByEMailAndUsername($email, $name);
        if($virtualUser !== false) {
            return $virtualUser;
        } else {
            $this->createVirtualUser($name, $email);
            return $this->fetchVirtualUserByEMailAndUsername($email, $name);
        }
    }

    private function fetchVirtualUserByEMail($email) {
        $db = $this->getDb();

        $virtualUser = $db->fetch1("SELECT * FROM chat_user_virtual WHERE email = '".mysql_real_escape_string($email)."'");

        return $virtualUser;
    }

	private function fetchVirtualUserByEMailAndUsername($email, $username) {
		$db = $this->getDb();

		$virtualUser = $db->fetch1("SELECT * FROM chat_user_virtual WHERE EMAIL = '".mysql_real_escape_string($email)."' AND NAME = '".mysql_real_escape_string($username)."'");

		return $virtualUser;
	}

    private function createVirtualUser($name, $email) {
        $db = $this->getDb();
        return $db->update("chat_user_virtual", array(
            'NAME' => $name,
            'EMAIL' => $email
        ));
    }

    public function find($id) {
        $db = $this->getDb();

        $virtualUser = $db->fetch1("SELECT * FROM chat_user_virtual WHERE ID_CHAT_USER_VIRTUAL = '".mysql_real_escape_string($id)."'");

        return $virtualUser;
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