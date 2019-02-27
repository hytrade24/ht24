<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once dirname(__FILE__).'/lib.chat.user.php';
require_once dirname(__FILE__).'/lib.chat.user.virtual.php';
require_once dirname(__FILE__).'/lib.chat.messages.php';

class ChatManagement {
	private static $db;
	private static $instance = NULL;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ChatManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === NULL) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function addChat($subject = '') {
        $chat = array('SUBJECT' => $subject);
        return $this->_addChat($chat);
    }
    public function addChatForAd($adId, $subject = '') {
        $chat = array(
            'FK_AD' => $adId,
            'SUBJECT' => $subject
        );
        return $this->_addChat($chat);
    }

    public function addChatForTransaction($transactionId, $subject = '') {
        $chat = array(
            'FK_TRANS' => $transactionId,
            'SUBJECT' => $subject
        );
        return $this->_addChat($chat);
    }

	public function addChatForOrder($orderId, $subject = '') {
		$chat = array(
			'FK_AD_ORDER' => $orderId,
			'SUBJECT' => $subject
		);
		return $this->_addChat($chat);
	}

    public function addChatByParam($param) {
        return $this->_addChat($param);
    }

    private function _addChat($chat) {
        $db = $this->getDb();

        $chat['HASH'] = $this->_generateHash();
        $chat['STAMP_CREATE'] = date("Y-m-d H:i:s");

        return $db->update("chat", $chat);
    }
    
    public function extendRelation(&$chat) {
        $db = $this->getDb();

        if($chat['FK_AD'] != null) {
            $tmpAd = $db->fetch1("SELECT * FROM ad_master WHERE ID_AD_MASTER = '".$chat['FK_AD']."'");
            $chat['FK_KAT'] = $tmpAd['FK_KAT'];
            $chat['FK_AD_NAME'] = $tmpAd['PRODUKTNAME'];
        }
    
        if($chat['FK_AD_ORDER'] != null) {
            $tmpAdOrder = $db->fetch1("SELECT * FROM ad_order WHERE ID_AD_ORDER = '".$chat['FK_AD_ORDER']."'");
            $chat['AD_ORDER_FK_USER_VK'] = $tmpAdOrder['FK_USER_VK'];
        }
    }

    public function setChatAutoApprove($chatId, $approve = TRUE) {
        $db = $this->getDb();
        $chatMessageManagement = ChatMessageManagement::getInstance($db);

        $db->querynow("UPDATE chat SET AUTO_APPROVE = '".mysql_real_escape_string(($approve?1:0))."' WHERE ID_CHAT='".mysql_real_escape_string($chatId)."'");
        if($approve) {
            $chatMessages = $chatMessageManagement->fetchAllByParam(array(
                'APPROVED' => FALSE,
                'FK_CHAT' => $chatId
            ));

            foreach($chatMessages as $key => $chatMessage) {
                $this->approveMessage($chatId, $chatMessage['ID_CHAT_MESSAGE']);
            }
        }
    }

    public function approveMessage($chatId, $chatMessageId) {
        $db = $this->getDb();
        $chatMessageManagement = ChatMessageManagement::getInstance($db);

        $chatMessageManagement->setApprove($chatMessageId, TRUE);
        $this->publishMessage($chatId, $chatMessageId);
    }

    public function disApproveMessage($chatId, $chatMessageId) {
		$db = $this->getDb();
		$chatMessageManagement = ChatMessageManagement::getInstance($db);

		$chatMessageManagement->setApprove($chatMessageId, FALSE);
    }

    public function addUserToChat($chatId, $userId) {
        $db = $this->getDb();

        $db->update('chat_user', array(
            'FK_CHAT' => $chatId,
            'FK_USER' => $userId,
            'TYPE' => 0
        ));

        return TRUE;
    }

    public function addVirtualUserToChat($chatId, $virtualUserId) {
        $db = $this->getDb();

        $db->update('chat_user', array(
            'FK_CHAT' => $chatId,
            'FK_CHAT_USER_VIRTUAL' => $virtualUserId,
            'TYPE' => 1
        ));

        return TRUE;
    }

    public function postMessageByUser($chatId, $userId, $message) {
        $db = $this->getDb();

        $chatUser = $db->fetch1("
            SELECT
                *
            FROM chat_user
            WHERE
                FK_USER = '".mysql_real_escape_string($userId)."'
                AND FK_CHAT = '".mysql_real_escape_string($chatId)."'
                AND TYPE = 0
        ");

        if($chatUser == NULL) {
            throw new Exception("User not in Chat");
        }

        $this->postMessage($chatId, $chatUser['ID_CHAT_USER'], $message);
    }

    public function postMessageByVirtualUser($chatId, $virtualUserId, $message) {
        $db = $this->getDb();

        $chatUser = $db->fetch1("
            SELECT
                *
            FROM chat_user
            WHERE
                FK_CHAT_USER_VIRTUAL = '".mysql_real_escape_string($virtualUserId)."'
                AND FK_CHAT = '".mysql_real_escape_string($chatId)."'
                AND TYPE = 1
        ");

        if($chatUser == NULL) {
            throw new Exception("User not in Chat");
        }

        $this->postMessage($chatId, $chatUser['ID_CHAT_USER'], $message);
    }

    private function postMessage($chatId, $chatUserId, $message) {
      global $nar_systemsettings;

      $db = $this->getDb();
      $isMessageApproved = FALSE;

      $chat = $this->find($chatId);

      if ((int)$chat["FK_CHAT_USER"] <= 0) {
        $chat["FK_CHAT_USER"] = (int)$db->fetch_atom("SELECT SENDER FROM `chat_message` WHERE FK_CHAT=".(int)$chatId." ORDER BY ID_CHAT_MESSAGE ASC LIMIT 1");
        if ($chat["FK_CHAT_USER"] <= 0) {
          $chat["FK_CHAT_USER"] = $chatUserId;
        }
        $db->querynow("UPDATE `chat` SET FK_CHAT_USER=".(int)$chat["FK_CHAT_USER"]." WHERE ID_CHAT=" . (int)$chatId);
      }

      if ($chat['AUTO_APPROVE'] == TRUE) {
        $isMessageApproved = TRUE;
      }

      if (isset($nar_systemsettings['MARKTPLATZ']['CHAT_AUTO_APPROVE']) && ($nar_systemsettings['MARKTPLATZ']['CHAT_AUTO_APPROVE'] == 1)) {
        $isMessageApproved = TRUE;
      }

      // Approve By User
      /**
       * @TODO Approve By User
       */

      // Mehrere Teilnehmer in Konverstion notwendig
      $chatUserManagement = ChatUserManagement::getInstance($db);
      $chatUsers = $chatUserManagement->fetchAllChatUserByChatId($chatId);

      if ($chatUsers < 2) {
        throw new Exception("Conversation needs two participants");
      }

      $stampMessage = date("Y-m-d H:i:s");
      $stampSql = array("STAMP_CHANGED='" . mysql_real_escape_string($stampMessage) . "'");
      if ($chat["FK_CHAT_USER"] != $chatUserId) {
        $stampSql[] = "STAMP_REPLY='" . mysql_real_escape_string($stampMessage) . "'";
      }

      $db->querynow("
            UPDATE `chat`
            SET ".implode(",\n  ", $stampSql) ."
            WHERE ID_CHAT=" . (int)$chat['ID_CHAT']);

      $chatMessageId = $db->update("chat_message", array(
        'FK_CHAT' => $chat['ID_CHAT'],
        'STAMP_CREATE' => $stampMessage,
        'SENDER' => $chatUserId,
        'MESSAGE' => $message,
        'APPROVED' => $isMessageApproved
      ));

      if ($isMessageApproved) {
        $this->publishMessage($chatId, $chatMessageId);
      } else {
        $this->notifyAdmin($chatId, $chatMessageId);
      }


    }

    private function publishMessage($chatId, $chatMessageId) {
        global $nar_systemsettings;

        $db = $this->getDb();
        $chatMessagesManagement = ChatMessageManagement::getInstance($this->getDb());
        $chatManagement = ChatManagement::getInstance($this->getDb());
        $chatUserManagement = ChatUserManagement::getInstance($this->getDb());
        $chatUserVirtualManagement = ChatUserVirtualManagement::getInstance($this->getDb());

        $chatMessage = $chatMessagesManagement->find($chatMessageId);
        $chat = $this->find($chatId);

        $chatUsers = $chatUserManagement->fetchAllChatUserByChatId($chat['ID_CHAT']);

        foreach($chatUsers as $key => $chatUser) {
            if($chatUser['ID_CHAT_USER'] != $chatMessage['SENDER']) {
                
                $recipientUser = null;
                if ($chatUser['TYPE'] == 0) {
                    $recipientUser = $db->fetch1("SELECT * FROM user WHERE ID_USER=".$chatUser["FK_USER"]);
                }
                
                
                if ($recipientUser !== null) {
                    if (!$recipientUser["IS_VIRTUAL"]) {
                        // User
                        $mail_content['NAME'] = $recipientUser["NAME"];
                        $mail_content['SUBJECT'] = $chat["SUBJECT"];
                        $mail_content['USER_VIRTUAL'] = $recipientUser['IS_VIRTUAL'];
                        $mail_content['USER_VIRTUAL_HASH'] = $recipientUser['ID_USER']."!".md5($recipientUser["NAME"].$recipientUser["SALT"].$recipientUser["EMAIL"]);
                        $mail_content['FK_AD'] = $chat["FK_AD"];
                        $mail_content['IS_INITIATOR'] = ($chatUser['ID_CHAT_USER'] == $chat["FK_CHAT_USER"]);
    
                        sendMailTemplateToUser(0, $recipientUser["ID_USER"], 'NEW_MAIL', $mail_content);
                    } else {
                        // Virtual partially registered
                        $mail_content['NAME'] = $recipientUser["NAME"];
                        $mail_content['SUBJECT'] = $chat["SUBJECT"];
                        $mail_content['HASH'] = $chat['HASH'];
                        $mail_content['FK_AD'] = $chat["FK_AD"];
                        $mail_content['IS_INITIATOR'] = ($chatUser['ID_CHAT_USER'] == $chat["FK_CHAT_USER"]);
    
                        sendMailTemplateToUser(0, $recipientUser["EMAIL"], 'NEW_MAIL_VIRTUAL', $mail_content);
                    }
                } else {
                    // Virtual unregistered
                    $recipientVirtualUser = $chatUserVirtualManagement->find($chatUser["FK_CHAT_USER_VIRTUAL"]);
                    $mail_content['NAME'] = $recipientVirtualUser["NAME"];
                    $mail_content['SUBJECT'] = $chat["SUBJECT"];
                    $mail_content['HASH'] = $chat['HASH'];
                    $mail_content['FK_AD'] = $chat["FK_AD"];

                    sendMailTemplateToUser(0, $recipientVirtualUser["EMAIL"], 'NEW_MAIL_VIRTUAL', $mail_content);
                }

            }
        }
    }

    private function notifyAdmin($chatId, $chatMessageId) {
        global $nar_systemsettings;

        $db = $this->getDb();
        $chatMessagesManagement = ChatMessageManagement::getInstance($this->getDb());
        $chatManagement = ChatManagement::getInstance($this->getDb());

        $chatMessage = $chatMessagesManagement->find($chatMessageId);
        $chat = $this->find($chatId);

        $mail_content['SUBJECT'] = $chat["SUBJECT"];
        $mail_content['LINK'] = "/admin/index.php?lang=de&page=chat_messages&ID_CHAT=".$chat['ID_CHAT'];

        sendMailTemplateToUser(0, 0, 'NEW_MAIL_ADMIN_NOTIFY', $mail_content);
    }

    public function find($chatId) {
        $db = $this->getDb();
        return $db->fetch1("SELECT * FROM chat WHERE ID_CHAT = '".$chatId."'");
    }
    
    public function findPartnerUser($chatId, $userIdOwn = null) {
        if ($userIdOwn === null) {
            $userIdOwn = $GLOBALS["uid"];
        }
        if ($userIdOwn > 0) {
            $db = $this->getDb();
            $userPartner = $db->fetch1("SELECT * FROM `chat_user` WHERE FK_CHAT=".(int)$chatId." AND (FK_USER IS NULL OR FK_USER!=".(int)$userIdOwn.")");
            if (is_array($userPartner)) {
                if($userPartner['TYPE'] == 0) {
                    return $db->fetch1("SELECT * FROM user WHERE ID_USER = '".$userPartner['FK_USER']."'");
                } else {
                    $chatUserVirtualManagement = ChatUserVirtualManagement::getInstance($db);
                    return $chatUserVirtualManagement->find($userPartner['FK_CHAT_USER_VIRTUAL']);
                }
            }
        }
        return null;
    }

	public function fetchAllByParam($param, &$all = null) {
		$db = $this->getDb();
		$query = $this->generateFetchQuery($param);

		$arResult = $db->fetch_table($query);
		if ($all !== null) {
			$all = $db->fetch_atom("SELECT FOUND_ROWS()");
		}
		return $arResult;
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
		$sqlWhere = " ";
		$sqlHaving = " ";
		$sqlJoin = "";
		$sqlSelect = "";
		$sqlOrder = " STAMP_ANY DESC";

		if(isset($param['ID_CHAT']) && $param['ID_CHAT'] != NULL) { $sqlWhere .= ' AND c.ID_CHAT = "'.mysql_real_escape_string($param['ID_CHAT']).'" '; }
		if(isset($param['SUBJECT']) && $param['SUBJECT'] != NULL) { $sqlWhere .= ' AND c.SUBJECT LIKE "%'.mysql_real_escape_string($param['SUBJECT']).'%" '; }
		if(isset($param['FK_AD']) && $param['FK_AD'] != NULL) { $sqlWhere .= ' AND c.FK_AD = "'.mysql_real_escape_string($param['FK_AD']).'" '; }
		if(isset($param['FK_AD_REQUEST']) && $param['FK_AD_REQUEST'] != NULL) { $sqlWhere .= ' AND c.FK_AD_REQUEST = "'.mysql_real_escape_string($param['FK_AD_REQUEST']).'" '; }
		if(isset($param['FK_TRANS']) && $param['FK_TRANS'] != NULL) { $sqlWhere .= ' AND c.FK_TRANS = "'.mysql_real_escape_string($param['FK_TRANS']).'" '; }
        if(isset($param['IS_APPROVED']) && $param['IS_APPROVED'] !== NULL) { $sqlHaving .= ' AND IS_APPROVED = "'.($param['IS_APPROVED']?1:0).'" '; }
        if(isset($param['IS_UNREAD']) && $param['IS_UNREAD'] !== NULL) {
            $sqlWhere .= ' AND NOT EXISTS (SELECT 1 FROM chat_user_read_message curm WHERE curm.FK_CHAT_MESSAGE=m.ID_CHAT_MESSAGE) ';
        }
		if(isset($param['CHAT_TYPE']) && $param['CHAT_TYPE'] !== NULL) {
			switch((int)$param['CHAT_TYPE']) {
				case 1: $sqlWhere .= ' AND (c.FK_AD != "" OR c.FK_AD IS NOT NULL) '; break;
				case 2: $sqlWhere .= ' AND (c.FK_TRANS != "" OR c.FK_TRANS IS NOT NULL) '; break;
				case 3: $sqlWhere .= ' AND c.FK_AD IS NULL AND c.FK_TRANS IS NULL AND c.FK_AD_REQUEST IS NULL '; break;
				case 4: $sqlWhere .= ' AND (c.FK_AD_REQUEST != "" OR c.FK_AD_REQUEST IS NOT NULL) '; break;
			}
		}
    if (isset($param['CHAT_USER_ID']) && $param['CHAT_USER_ID'] != NULL) {
      if (isset($param['MODUS']) && $param['MODUS'] == 'OUTBOX') {
        // OUTBOX
        $sqlWhere .= ' AND cui.FK_USER=' . (int)$param['CHAT_USER_ID'];
      } else {
        // INBOX
        $sqlWhere .= ' AND cu.FK_USER=' . (int)$param['CHAT_USER_ID'];
      }
    }
		if(isset($param['CHAT_USER_STATUS']) && $param['CHAT_USER_STATUS'] !== NULL) { $sqlWhere .= ' AND cu.STATUS = "'.($param['CHAT_USER_STATUS']).'" '; }
		if(isset($param['READABLE_BY_USERID']) && $param['READABLE_BY_USERID'] != NULL) {
			$sqlSelect .= ' , IF((SELECT COUNT(*) FROM chat_message JOIN chat_user ON chat_user.FK_CHAT = chat_message.FK_CHAT WHERE chat_message.FK_CHAT = c.ID_CHAT AND ((chat_message.SENDER = chat_user.ID_CHAT_USER AND chat_user.FK_USER = '.$param['READABLE_BY_USERID'].' AND chat_user.TYPE = 0) OR chat_message.APPROVED = 1)) > 0, 1,0) AS READABLE_BY_USERID';
			$sqlHaving .= ' AND (READABLE_BY_USERID = 1) ';
		}

		if(isset($param['SEARCH_CONTENT']) && $param['SEARCH_CONTENT'] != NULL) { $sqlWhere .= ' AND (m.MESSAGE LIKE "%'.mysql_real_escape_string($param['SEARCH_CONTENT']).'%" OR c.SUBJECT LIKE "%'.mysql_real_escape_string($param['SEARCH_CONTENT']).'%") '; }
		if(isset($param['SEARCH_USER']) && $param['SEARCH_USER'] != NULL) {
			$sqlJoin .= ' LEFT JOIN chat_user cusearch ON cusearch.ID_CHAT_USER != cu.ID_CHAT_USER AND cusearch.FK_CHAT = c.ID_CHAT ';
			$sqlJoin .= ' LEFT JOIN user usearch ON usearch.ID_USER = cusearch.FK_USER AND cusearch.TYPE = 0 ';
			$sqlJoin .= ' LEFT JOIN chat_user_virtual uvsearch ON uvsearch.ID_CHAT_USER_VIRTUAL = cusearch.FK_CHAT_USER_VIRTUAL AND cusearch.TYPE = 1 ';
			$sqlWhere .= ' AND (
				uvsearch.NAME LIKE "%'.mysql_real_escape_string($param['SEARCH_USER']).'%" OR uvsearch.EMAIL LIKE "%'.mysql_real_escape_string($param['SEARCH_USER']).'%"
				OR usearch.NAME LIKE "%'.mysql_real_escape_string($param['SEARCH_USER']).'%" OR usearch.FIRMA LIKE "%'.mysql_real_escape_string($param['SEARCH_USER']).'%"
				OR usearch.VORNAME LIKE "%'.mysql_real_escape_string($param['SEARCH_USER']).'%" OR usearch.NACHNAME LIKE "%'.mysql_real_escape_string($param['SEARCH_USER']).'%"
			) ';
		}

		if(isset($param['SEARCH_FK_TYPE']) && $param['SEARCH_FK_TYPE'] == '1') {
			$sqlWhere .= ' AND c.FK_AD IS NOT NULL ';
			if(isset($param['SEARCH_FK_CONTENT']) && $param['SEARCH_FK_CONTENT'] != NULL) {
				$sqlWhere .= ' AND c.FK_AD = "'.(int)$param['SEARCH_FK_CONTENT'].'" ';
			}
		} else if(isset($param['SEARCH_FK_TYPE']) && $param['SEARCH_FK_TYPE'] == '2') {
			$sqlWhere .= ' AND c.FK_AD_ORDER IS NOT NULL ';
			if(isset($param['SEARCH_FK_CONTENT']) && $param['SEARCH_FK_CONTENT'] != NULL) {
				$sqlWhere .= ' AND c.FK_AD_ORDER = "'.(int)$param['SEARCH_FK_CONTENT'].'" ';
			}
		} else if (!isset($param['SEARCH_ANY'])) {
            $sqlWhere .= ' AND (c.FK_AD IS NULL) ';
		}

		/*if(isset($param['SEARCH_UNREAD']) && $param['SEARCH_UNREAD'] != NULL) {
			$sqlJoin .= ' LEFT JOIN chat_message curmm ON curmm.FK_CHAT = c.ID_CHAT AND curmm.APPROVED = 1 AND curmm.SENDER != cu.ID_CHAT_USER ';
			$sqlJoin .= ' LEFT JOIN chat_user_read_message curm ON curm.FK_CHAT_MESSAGE = curmm.ID_CHAT_MESSAGE	';
			$sqlWhere .= ' AND curm.ID_CHAT_USER_READ_MESSAGE IS NULL ';
		}*/


		if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
			if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
		}

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			$sqlFields = "c.ID_CHAT,";
		} else {
			$sqlFields = "
				c.*,
				c.STAMP_CHANGED AS STAMP_ANY,
				IF((SELECT COUNT(*) FROM `chat_message` ma1 WHERE ma1.FK_CHAT=c.ID_CHAT AND ma1.APPROVED=0) = 0,1,0) AS IS_APPROVED,
				IF((SELECT COUNT(*) FROM `chat_message` ma2 WHERE ma2.FK_CHAT=c.ID_CHAT AND ma2.APPROVED=0) > 0,1,0) AS NOT_APPROVED
				".($sqlSelect?' '.$sqlSelect:'')."
			";
		}

		$query = "SELECT
				SQL_CALC_FOUND_ROWS
				c.STAMP_CREATE AS STAMP_MIN, ".trim($sqlFields, " \t\r\n,")."
			FROM
				chat c
			LEFT JOIN chat_user cu ON cu.FK_CHAT = c.ID_CHAT AND cu.ID_CHAT_USER != c.FK_CHAT_USER
			LEFT JOIN chat_user cui ON cui.ID_CHAT_USER = c.FK_CHAT_USER
			LEFT JOIN chat_message m ON m.FK_CHAT = c.ID_CHAT
			    AND ".(array_key_exists('MODUS', $param) && ($param['MODUS'] == 'INBOX') ? "m.SENDER != cu.ID_CHAT_USER" : "m.SENDER = cu.ID_CHAT_USER")."
			".$sqlJoin."
			WHERE
				1=1
				".($sqlWhere?' '.$sqlWhere:'')."
			GROUP BY c.ID_CHAT
			HAVING 1=1 ".($sqlHaving?' '.$sqlHaving:'')."
			ORDER BY ".$sqlOrder."
			".($sqlLimit?'LIMIT '.$sqlLimit:'')."

		";
		#die($query);
		
		return $query;
	}



    public function deleteById($id) {
        $db = $this->getDb();

        $db->querynow("DELETE FROM chat WHERE ID_CHAT = '".mysql_real_escape_string($id)."'");
        $db->querynow("DELETE FROM chat_message WHERE FK_CHAT = '".mysql_real_escape_string($id)."'");
        $db->querynow("DELETE FROM chat_user WHERE FK_CHAT = '".mysql_real_escape_string($id)."'");

        return TRUE;
    }

	/**
	 * @param $id chat ID
	 * @param $userId user ID
	 */
	public function deleteForUserById($id, $userId) {
		$db = $this->getDb();

		$db->querynow("UPDATE chat_user SET STATUS = '".ChatUserManagement::STATUS_DELETED."' WHERE FK_CHAT = '".mysql_real_escape_string($id)."' AND FK_USER = '".mysql_real_escape_string($userId)."'");
		return TRUE;
	}

    public function getChatByHash($hash) {
        $db = $this->getDb();

        return  $db->fetch1("SELECT * FROM chat WHERE HASH = '".mysql_real_escape_string($hash)."'");
    }

    private function _generateHash() {
        return md5(microtime().rand().$_SERVER['REMOTE_IP']);
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