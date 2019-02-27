<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/packet_management.php";
require_once $ab_path."sys/lib.user.php";
require_once $ab_path."sys/lib.billing.creditnote.php";


class PacketMembershipUpgradeManagement {
	private static $db;
	private static $instance = null;

    const STATUS_OPEN = 0;
    const STATUS_APPROVED = 1;
    const STATUS_UNAPPROVED = 2;
    const STATUS_DONE = 3;

    const INIT_RETURN_FAILED = 0;
    const INIT_RETURN_SUCCESS_APPROVED = 1;
    const INIT_RETURN_SUCCESS_UNLOCK_MANUAL = 2;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return PacketMembershipUpgradeManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function fetchAllByParam($param) {
        $db = $this->getDb();

        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " m.STAMP_CREATE DESC, m.ID_PACKET_MEMBERSHIP_UPGRADE DESC ";

        if(isset($param['ID_PACKET_MEMBERSHIP_UPGRADE']) && $param['ID_PACKET_MEMBERSHIP_UPGRADE'] != null && !is_array($param['ID_PACKET_MEMBERSHIP_UPGRADE'])) { $sqlWhere .= " AND m.ID_PACKET_MEMBERSHIP_UPGRADE = '".mysql_real_escape_string($param['ID_PACKET_MEMBERSHIP_UPGRADE'])."' "; }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null) { $sqlWhere .= " AND m.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(isset($param['STATUS']) && $param['STATUS'] !== null) { $sqlWhere .= " AND m.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }

        if(isset($param['LIMIT']) && $param['LIMIT'] != null) { if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; } }
        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }


        $query = "
            SELECT
                m.*
            FROM
                packet_membership_upgrade m
            ".$sqlJoin."
            WHERE
                true
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY m.ID_PACKET_MEMBERSHIP_UPGRADE
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."
        ";

        $result = $db->fetch_table($query);

        return $result;
    }

    public function countByParam($param) {
        $db = $this->getDb();


        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";

        if(isset($param['ID_PACKET_MEMBERSHIP_UPGRADE']) && $param['ID_PACKET_MEMBERSHIP_UPGRADE'] != null && !is_array($param['ID_PACKET_MEMBERSHIP_UPGRADE'])) { $sqlWhere .= " AND m.ID_PACKET_MEMBERSHIP_UPGRADE = '".mysql_real_escape_string($param['ID_PACKET_MEMBERSHIP_UPGRADE'])."' "; }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null) { $sqlWhere .= " AND m.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(isset($param['STATUS']) && $param['STATUS'] !== null) { $sqlWhere .= " AND m.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }

        $query = ("
            SELECT
                SQL_CALC_FOUND_ROWS m.ID_PACKET_MEMBERSHIP_UPGRADE
            FROM
                packet_membership_upgrade m
            ".$sqlJoin."
            WHERE
                true
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY m.ID_PACKET_MEMBERSHIP_UPGRADE
        ");

        $result = $db->querynow($query);
        $count = $db->fetch_atom("SELECT FOUND_ROWS()");

        return $count;
    }

    public function fetchById($membershipUpgradeId) {
        return $this->getDb()->fetch1("SELECT * FROM packet_membership_upgrade WHERE ID_PACKET_MEMBERSHIP_UPGRADE = '".(int)$membershipUpgradeId."'");
    }

    public function fetchAllUpgradeablePackets($fromPacketId, $hideNotUpgradeable = false) {
        $packets = PacketManagement::getInstance($this->getDb());

        $all = 0;
        $whereConditions = array("TYPE='MEMBERSHIP'", "(STATUS&1)=1");
        if ($fromPacketId > 0) {
          $whereConditions[] = "p.ID_PACKET != '".(int)$fromPacketId."'";
        }
        $upgradeablePackets = $packets->getList(1, 1000, $all, $whereConditions, array("F_ORDER ASC", "TYPE ASC", "V1 ASC"), false, false);
        $upgradeablePacketOptions = $this->getDb()->fetch_nar("SELECT FK_PACKET_TO, UNLOCK_MANUAL FROM packet_membership_upgrade_option WHERE FK_PACKET_FROM = '".(int)$fromPacketId."'");
        foreach($upgradeablePackets as $upgradeablePacketKey => $upgradeablePacket) {
            if($fromPacketId == 0) {
                $upgradeablePackets[$upgradeablePacketKey]['IS_ABLE'] = 1;
                $upgradeablePackets[$upgradeablePacketKey]['IS_UNLOCK_MANUAL'] = 1;
            } elseif (array_key_exists($upgradeablePacket['ID_PACKET'], $upgradeablePacketOptions)) {
                $upgradeablePackets[$upgradeablePacketKey]['IS_ABLE'] = 1;
                $upgradeablePackets[$upgradeablePacketKey]['IS_UNLOCK_MANUAL'] = $upgradeablePacketOptions[$upgradeablePacket['ID_PACKET']];
            } else {
                $upgradeablePackets[$upgradeablePacketKey]['IS_ABLE'] = 0;
                $upgradeablePackets[$upgradeablePacketKey]['IS_UNLOCK_MANUAL'] = 0;
            }

            if($hideNotUpgradeable === TRUE && !$upgradeablePackets[$upgradeablePacketKey]['IS_ABLE']) {
                unset($upgradeablePackets[$upgradeablePacketKey]);
            }
        }

        return $upgradeablePackets;
    }

    public function fetchOptionById($packetIdFrom, $packetIdTo) {
        return $this->getDb()->fetch1("SELECT * FROM packet_membership_upgrade_option WHERE FK_PACKET_FROM = '".(int)$packetIdFrom."' AND FK_PACKET_TO = '".(int)$packetIdTo."'");
    }

    public function isUpgradeAvailable($packetIdFrom) {
        $countUpgrade = $this->getDb()->fetch_atom("SELECT count(*) FROM packet_membership_upgrade_option WHERE FK_PACKET_FROM = '".(int)$packetIdFrom."'");
        return ($countUpgrade > 0 ? true : false);
    }

    public function isUpgradePossible($packetIdFrom, $packetIdTo) {
        return ($this->fetchOptionById($packetIdFrom, $packetIdTo) != null);
    }

    /**
     * PacketMembershipUpgradeManagement::requestUpdate()
     *
     * @param $userId
     * @param $packetRuntimeId
     */
    public function initUpgrade($userId, $packetRuntimeId, $forceConfirm = false) {
        $packets = PacketManagement::getInstance($this->getDb());
        $userManagement = UserManagement::getInstance($this->getDb());

        $requestedMembership = $packets->getFull($packetRuntimeId);
        $activeMembership = $packets->getActiveMembershipByUserId($userId);
        $user = $userManagement->fetchById($userId);

        if($activeMembership == NULL) {
            $membershipUpgradeId = $this->createMembershipUpgrade(array(
                'FK_USER' => $userId,
                'FK_PACKET_RUNTIME' => $requestedMembership['ID_PACKET_RUNTIME'],
                'STATUS' => self::STATUS_OPEN
            ));

            if ($forceConfirm) {
                $this->approveUpgrade($membershipUpgradeId);
                return self::INIT_RETURN_SUCCESS_APPROVED;
            } else {
                sendMailTemplateToUser(0, 0, "MEMBERSHIP_UPGRADE_ADMIN", array(
                    'USER_NAME' => $user['NAME'],
                    'ID_PACKET_MEMBERSHIP_UPGRADE' => $membershipUpgradeId
                ));
    
                return self::INIT_RETURN_SUCCESS_UNLOCK_MANUAL;
            }
        } elseif($forceConfirm || $this->isUpgradePossible($activeMembership->getPacketId(), $requestedMembership['FK_PACKET'])) {
            $upgradeOption = $this->fetchOptionById($activeMembership->getPacketId(), $requestedMembership['FK_PACKET']);

            if (!$forceConfirm && ($upgradeOption['UNLOCK_MANUAL'] == 1)) {
                // Manuelle Freischaltung durch Admin erforderlich
                $status = self::STATUS_OPEN;

                $membershipUpgradeId = $this->createMembershipUpgrade(array(
                    'FK_USER' => $userId,
                    'FK_PACKET_RUNTIME' => $requestedMembership['ID_PACKET_RUNTIME'],
                    'STATUS' => $status
                ));

                sendMailTemplateToUser(0, 0, "MEMBERSHIP_UPGRADE_ADMIN", array(
                    'USER_NAME' => $user['NAME'],
                    'ID_PACKET_MEMBERSHIP_UPGRADE' => $membershipUpgradeId
                ));

                return self::INIT_RETURN_SUCCESS_UNLOCK_MANUAL;
            } else {
                $membershipUpgradeId = $this->createMembershipUpgrade(array(
                    'FK_USER' => $userId,
                    'FK_PACKET_RUNTIME' => $requestedMembership['ID_PACKET_RUNTIME'],
                    'STATUS' => self::STATUS_OPEN
                ));

                $this->approveUpgrade($membershipUpgradeId);

                return self::INIT_RETURN_SUCCESS_APPROVED;
            }
        }
        return self::INIT_RETURN_FAILED;
    }

    public function approveUpgrade($membershipUpgradeId) {
        $packets = PacketManagement::getInstance($this->getDb());
        $userManagement = UserManagement::getInstance($this->getDb());

        $membershipUpgrade = $this->fetchById($membershipUpgradeId);

        if($membershipUpgrade && $membershipUpgrade['STATUS'] == self::STATUS_OPEN) {
            $packets->order($membershipUpgrade["FK_PACKET_RUNTIME"], $membershipUpgrade['FK_USER'], 1, NULL);
            $this->getDb()->delete('packet_membership_upgrade', $membershipUpgradeId);

            return true;
        } else {
            return false;
        }
    }

    public function declineUpgrade($membershipUpgradeId) {
        $packets = PacketManagement::getInstance($this->getDb());
        $userManagement = UserManagement::getInstance($this->getDb());

        $membershipUpgrade = $this->fetchById($membershipUpgradeId);

        if($membershipUpgrade && $membershipUpgrade['STATUS'] == self::STATUS_OPEN) {
            $tmpUser = $userManagement->fetchById($membershipUpgrade['FK_USER']);
            $tmpPacket = $packets->getFull($membershipUpgrade['FK_PACKET_RUNTIME']);

            $maildata = array_merge(
                array_prefix_key($tmpUser, 'USER_'),
                array_prefix_key($tmpPacket, 'MEMBERSHIP_')
            );
            sendMailTemplateToUser(0, $membershipUpgrade['FK_USER'], "MEMBERSHIP_UPGRADE_DECLINED", $maildata);

            $this->getDb()->delete('packet_membership_upgrade', $membershipUpgradeId);
            return true;
        } else {
            return false;
        }
    }


    public function createMembershipUpgrade($rawData) {
        // validation
        $validationError = false;
        if(!isset($rawData['FK_USER'])) { $validationError = true; }
        if(!isset($rawData['FK_PACKET_RUNTIME'])) { $validationError = true; }

        if(!$validationError) {
            $rawData['ID_BILLING_CREDITNOTE'] = null;

            if(!isset($rawData['STATUS'])) { $rawData['STATUS'] = self::STATUS_OPEN; }
            if(!isset($rawData['STAMP_CREATE'])) { $rawData['STAMP_CREATE'] = date("Y-m-d"); }
            if(!isset($rawData['STAMP_ACCEPT'])) { $rawData['STAMP_ACCEPT'] = null; }
            if(!isset($rawData['STAMP_DECLINE'])) { $rawData['STAMP_DECLINE'] = null; }

            $membershipUpgradeId = $this->update(null, $rawData);

            return $membershipUpgradeId;
        } else {
            return null;
        }
    }

    public function update($membershipUpgradeId, $rawData) {
        $rawData['ID_PACKET_MEMBERSHIP_UPGRADE'] = $membershipUpgradeId;

        return $this->getDb()->update('packet_membership_upgrade', $rawData);
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