<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 11.06.15
 * Time: 10:58
 */

class Api_Entities_User {
    
    protected static $userActive = null;
    protected static $userCache = [];

    /**
     * Create an array of user object by an array of assoc user datasets
     * @param $arUserList   array
     * @return array
     */
    public static function createMultipleFromArray($arUserList) {
        $arResult = array();
        foreach ($arUserList as $userIndex => $arUser) {
            $arResult[] = new Api_Entities_User($arUser);
        }
        return $arResult;
    }

    /**
     * Get the user object for the currently logged in user
     * @param ebiz_db|null $db
     * @return Api_Entities_User
     */
    public static function getActiveUser(ebiz_db $db = null) {
        if (self::$userActive === null) {
            self::$userActive = new static($GLOBALS["user"], ($db === null ? $GLOBALS['db'] : $db));
        }
        return self::$userActive;
    }
    
    public static function getById($userId, $db = null, $allowCached = true) {
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($allowCached && array_key_exists($userId, static::$userCache)) {
            return static::$userCache[$userId];
        }
        require_once $GLOBALS["ab_path"]."sys/lib.user.php";
        $arUserTarget = UserManagement::getInstance($db)->fetchById($userId);
        if (!is_array($arUserTarget)) {
            return null;
        }
        $result = new static($arUserTarget, $db);
        static::$userCache[$userId] = $result;
        return $result;
    }
    
    protected $userData;
    protected $userRoles;
    protected $userRights;
    protected $db;
    
    function __construct(&$arUser = array(), ebiz_db $db = null) {
        $this->userData = $arUser;
        $this->userRoles = null;
        $this->userRights = null;
        $this->db = ($db === null ? $GLOBALS['db'] : $db);
    }

    /**
     * Returns the users dataset as assoc array
     * @return array
     */
    public function asArray() {
        return $this->userData;
    }

    /**
     * Unlock the user (admin action)
     * @throws Exception
     */
    public function adminUnlock() {
        $this->unlock(false);
    }

    /**
     * Lock the user (admin action)
     * @throws Exception
     */
    public function adminLock() {
        $this->lock();
    }

    /**
     * Checks if current_password is matching the saved password
     * @param $current_pass
     * @return bool
     */
    public function checkPassword($current_pass) {
        $userLogin = $this->db->fetch1("SELECT PASS, SALT FROM user WHERE ID_USER = ".(int)$this->userData['ID_USER']);
        return pass_compare($current_pass, $userLogin['PASS'], $userLogin['SALT']);
    }
    
    public function confirmRegistration() {
        $this->unlock(true);
    }

    /**
     * Get a raw value from the users dataset
     * @param $fieldName
     * @return string|int|null
     */
    public function getFieldRaw($fieldName) {
        if (array_key_exists($fieldName, $this->userData)) {
            return $this->userData[$fieldName];
        } else {
            return null;
        }
    }
    
    /**
     * Get the id of the user
     * @return int|null
     */
    public function getId() {
        if (array_key_exists("ID_USER", $this->userData)) {
            return ($this->userData["ID_USER"] > 0 ? (int)$this->userData["ID_USER"] : null);
        } else {
            return null;
        }
    }

    /**
     * Get the cache directory of the user (e.g. "/cache/users/A/1/")
     * @return string
     */
    public function getCacheDir() {
        return "/cache/users/".$this->userData["CACHE"]."/".$this->userData["ID_USER"]."/";
    }

    /**
     * Get the icon file of the user (e.g. "/cache/users/A/1/1.jpg")
     * @return null|string
     */
    public function getIcon() {
        if (array_key_exists("ID_USER", $this->userData) && array_key_exists("CACHE", $this->userData)) {
            return $this->getCacheDir().$this->userData["ID_USER"].".jpg";
        } else {
            return null;
        }
    }

    /**
     * Get the name of the user
     * @return null
     */
    public function getName() {
        if (array_key_exists("NAME", $this->userData)) {
            return $this->userData["NAME"];
        } else {
            return null;
        }
    }
    
    protected function getMessagesParams($arParams = [], $limit = null, $offset = null) {
        require_once $GLOBALS["ab_path"].'sys/lib.chat.user.php';
        $arParams['CHAT_USER_ID'] = $this->getId();
        $arParams['READABLE_BY_USERID'] = $this->getId();
        $arParams['CHAT_USER_STATUS'] = ChatUserManagement::STATUS_ACTIVE;
        if ($limit !== null) {
            $arParams['LIMIT'] = $limit;
        }
        if ($offset !== null) {
            $arParams['OFFSET'] = $offset;
        }
        return $arParams;
    }

    /**
     * Get the messages available matching the given parameters
     * @param array $arParams
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function getMessages($arParams = [], $limit = null, $offset = null) {
        require_once $GLOBALS["ab_path"].'sys/lib.chat.php';        
        return ChatManagement::getInstance($this->db)->fetchAllByParam( 
            $this->getMessagesParams($arParams, $limit, $offset) 
        );
    }

    /**
     * Get number of messages available matching the given parameters
     * @param array $arParams
     * @return int
     */
    public function getMessagesCount($arParams = []) {
        require_once $GLOBALS["ab_path"].'sys/lib.chat.php';        
        return ChatManagement::getInstance($this->db)->countByParam( 
            $this->getMessagesParams($arParams) 
        );
    }

    public function getOrdersSoldParams($arParams = [], $limit = null, $offset = null) {
        $arParams["USER_SELLER"] = $this->getId();
        if (!array_key_exists('ARCHIVE_SELLER', $arParams)) {
            $arParams['ARCHIVE_SELLER'] = 0;
        }
        if ($limit !== null) {
            $arParams['LIMIT'] = $limit;
        }
        if ($offset !== null) {
            $arParams['OFFSET'] = $offset;
        }
        return $arParams;
    }

    public function getOrdersBoughtParams($arParams = [], $limit = null, $offset = null) {
        $arParams["USER_BUYER"] = $this->getId();
        if (!array_key_exists('ARCHIVE', $arParams)) {
            $arParams['ARCHIVE'] = 0;
        }
        if ($limit !== null) {
            $arParams['LIMIT'] = $limit;
        }
        if ($offset !== null) {
            $arParams['OFFSET'] = $offset;
        }
        return $arParams;
    }

    /**
     * Get the orders that this user has sold matching the given parameters
     * @param array $arParams
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function getOrdersSold($arParams = [], $limit = null, $offset = null) {
        require_once $GLOBALS['ab_path'].'sys/lib.ad_order.php';
        return AdOrderManagement::getInstance($this->db)->fetchAllByParam( 
            $this->getOrdersSoldParams($arParams, $limit, $offset) 
        );
    }

    /**
     * Get the number of orders that this user has sold matching the given parameters
     * @param array $arParams
     * @return int
     */
    public function getOrdersSoldCount($arParams = []) {
        return AdOrderManagement::getInstance($this->db)->countByParam( 
            $this->getOrdersSoldParams($arParams) 
        );
    }
    
    /**
     * Get the orders that this user has bought matching the given parameters
     * @param array $arParams
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function getOrdersBought($arParams = [], $limit = null, $offset = null) {
        require_once $GLOBALS['ab_path'].'sys/lib.ad_order.php';
        return AdOrderManagement::getInstance($this->db)->fetchAllByParam( 
            $this->getOrdersBoughtParams($arParams, $limit, $offset) 
        );
    }
    
    /**
     * Get the number of orders that this user has bought matching the given parameters
     * @param array $arParams
     * @return int
     */
    public function getOrdersBoughtCount($arParams = []) {
        return AdOrderManagement::getInstance($this->db)->countByParam( 
            $this->getOrdersBoughtParams($arParams) 
        );
    }
    
    /**
     * Get the usergroup id of the user
     * @return int|null
     */
    public function getUsergroupId() {
        if (array_key_exists("FK_USERGROUP", $this->userData)) {
            return $this->userData["FK_USERGROUP"];
        } else {
            return null;
        }
    }

    /**
     * Get the vendor entry of the user
     * @return Api_Entities_Vendor|null
     */
    public function getVendorEntry() {
        return Api_Entities_Vendor::getByUserId( $this->getId() );
    }

    /**
     * Get the users assigned roles
     * @return array|null
     */
    public function getRoles() {
        $this->loadRoles();
        return $this->userRoles;
    }

    /**
     * Check if the role with the given label is assigned to this user.
     * @param $roleLabel    string
     * @return bool
     */
    public function hasRoleByLabel($roleLabel) {
        $arRoles = $this->getRoles();
        foreach ($arRoles as $roleIndex => $roleData) {
            if ($roleData["LABEL"] == $roleLabel) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the role with the given id is assigned to this user
     * @param $roleId   int
     * @return bool
     */
    public function hasRoleById($roleId) {
        $arRoles = $this->getRoles();
        foreach ($arRoles as $roleIndex => $roleData) {
            if ($roleData["ID_ROLE"] == $roleId) {
                return true;
            }
        }
        return false;
    }

    public function hasRight($rightIdent, $rightAccess = "r") {
        $this->loadRights();    // Ensure rights are available within the user object
        $rightAccess = strtolower($rightAccess);
        $rightAccessInteger = 0;
        if (strpos($rightAccess, "r") !== false) {
            $rightAccessInteger += 1;
        }
        if (strpos($rightAccess, "c") !== false) {
            $rightAccessInteger += 2;
        }
        if (strpos($rightAccess, "e") !== false) {
            $rightAccessInteger += 4;
        }
        if (strpos($rightAccess, "d") !== false) {
            $rightAccessInteger += 8;
        }
        if ($rightAccessInteger == 0) {
            // No rights requested. Always true.
            return true;
        } else if (array_key_exists($rightIdent, $this->userRights)) {
            // Check if the user has the required rights
            return (($this->userRights[$rightIdent] & $rightAccessInteger) == $rightAccessInteger);
        } else {
            // Right is not available to the user!
            return false;
        }
    }
    
    public function hasRights($arRights) {
        foreach ($arRights as $rightIdent => $rightAccess) {
            if (!$this->hasRight($rightIdent, $rightAccess)) {
                return false;
            }
        }
        return true;
    }
    
    public function isContactVisible($uid = null) {
        if (!$GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["HIDE_CONTACT_INFO"]) {
            return true;
        }
        if ($uid === null) {
            $uid = $GLOBALS["uid"];
        }
        $countSales = $this->db->fetch_atom($q="
            SELECT COUNT(*)
            FROM `ad_sold`
            WHERE CONFIRMED=1 AND
              ((FK_USER_VK=".(int)$this->getId()." AND FK_USER=".(int)$uid.")
                OR (FK_USER_VK=".(int)$uid." AND FK_USER=".(int)$this->getId()."))");
        return ($countSales > 0);
    }
    
    private function loadRoles() {
        if ($this->userRoles === null) {
            $this->userRoles = array();
            $userId = $this->getId();
            if ($userId !== null) {
                $this->userRoles = $this->db->fetch_table("
                    SELECT
                        r.*
                    FROM `role` r
                    JOIN `role2user` ru ON ru.FK_ROLE=r.ID_ROLE
                    WHERE ru.FK_USER=".$userId);
            }
        }
    }
    
    private function loadRights() {
        if ($this->userRights === null) {
            $queryPerm = "
                SELECT 
                  p.IDENT,
                  (
                    ((IFNULL(BIT_OR(pr.BF_ALLOW), 0) & IFNULL(pu.BF_INHERIT, 15)) 
                    - (IFNULL(BIT_OR(pr.BF_ALLOW), 0) & IFNULL(pu.BF_INHERIT, 15) & IFNULL(pu.BF_REVOKE, 0)))
                    | pu.BF_GRANT
                  ) AS BF_ALLOW
                FROM `perm` p
                LEFT JOIN `role2user` ru ON ru.FK_USER=".(int)$this->getId()."
                LEFT JOIN `perm2role` pr ON pr.FK_ROLE=ru.FK_ROLE AND pr.FK_ROLE=ru.FK_ROLE
                LEFT JOIN `perm2user` pu ON pu.FK_PERM=p.ID_PERM AND pu.FK_USER=".(int)$this->getId()."
                GROUP BY p.ID_PERM";
            $arRights = $this->db->fetch_table($queryPerm);
            $this->userRights = array();
            foreach ($arRights as $rightIndex => $rightDetails) {
                if (array_key_exists($rightDetails["IDENT"], $this->userRights)) {
                    $this->userRights[ $rightDetails["IDENT"] ] |= $rightDetails["BF_ALLOW"]; 
                } else {
                    $this->userRights[ $rightDetails["IDENT"] ] = $rightDetails["BF_ALLOW"];
                }
            }
        }
    }

    /**
     * Sets the id of the user
     * @param $id   int
     */
    public function setId($id) {
        $this->userData["ID_USER"] = (int)$id;
    }

    /**
     * Sets the name of the user
     * @param $name
     */
    public function setName($name) {
        $this->userData["NAME"] = $name;
    }

    /**
     * @param bool $registration    True for registration, false when confirmed by admin. 
     * @throws Exception
     */
    protected function unlock($registration = true) {        
        if ($this->userData["FK_PACKET_RUNTIME"] > 0) {
            /*
             * Apply membership (Confirming registration)
             */
            // Gutscheincode
            $couponCodeUsageId = (int)$this->db->fetch_atom("
              SELECT FK_COUPON_CODE_USAGE 
              FROM `user` 
              WHERE ID_USER=".(int)$this->getId());
            if($couponCodeUsageId > 0) {
                $couponUsageManagement = Coupon_CouponUsageManagement::getInstance($this->db);
                try {
                    $couponUsage = $couponUsageManagement->fetchActivatedCouponUsageByUserId($couponCodeUsageId, 0, 'PACKET', array($this->userData["FK_PACKET_RUNTIME"]));
                } catch(Exception $e) {
                    $this->db->querynow("DELETE FROM `coupon_code_usage` WHERE ID_COUPON_CODE_USAGE=".(int)$couponCodeUsageId);
                    $this->db->querynow("UPDATE `user` SET FK_COUPON_CODE_USAGE=NULL WHERE ID_USER=".(int)$this->getId());
                }
            }

            // Paket bestellen
            require_once $GLOBALS["ab_path"]."sys/packet_management.php";
            $packets = PacketManagement::getInstance($this->db);
            $result = $packets->order($this->userData["FK_PACKET_RUNTIME"], $this->getId(), 1, null, null,null, $couponUsage);
            $packetOrderId = (is_array($result) ? $result[0] : $result);
            if ($packetOrderId > 0) {
                $packetOrder = $packets->order_get($packetOrderId);
                $packetOrderUsergroup = array_merge(
                    $packetOrder->getContentByType(PacketManagement::getType("usergroup_abo")),
                    $packetOrder->getContentByType(PacketManagement::getType("usergroup_once"))
                );
                if (array_key_exists("PARAMS", $packetOrderUsergroup) && ($packetOrderUsergroup["PARAMS"] > 0)) {
                    $usergroup = $this->db->fetch1("SELECT * FROM `usergroup` WHERE ID_USERGROUP=".(int)$packetOrderUsergroup["PARAMS"]);
                    if (!$usergroup["PREPAID_REGISTER"]) {
                        // Enable packet and disable prepaid option
                        $packetOrder->activate();
                        $this->db->querynow("UPDATE `usercontent` SET CHARGE_AT_ONCE=0 WHERE FK_USER".(int)$id);
                    }
                }
            }
            //$db->querynow("UPDATE `user` SET FK_PACKET_RUNTIME=NULL WHERE ID_USER=".(int)$id);
            $this->userData["FK_USERGROUP"] = $this->db->fetch_atom("select FK_USERGROUP from user where ID_USER=".(int)$this->getId());
            $this->userData["FK_PACKET_RUNTIME"] = null;
            if($couponUsage != null) {
                $this->db->querynow("UPDATE `user` SET FK_COUPON_CODE_USAGE=NULL WHERE ID_USER=".(int)$this->getId());
                $this->db->querynow("UPDATE `coupon_code_usage` SET FK_USER=".(int)$this->getId()." WHERE ID_COUPON_CODE_USAGE=".(int)$couponCodeUsageId);
            }
            $this->db->querynow("UPDATE `user` SET STAT=1 WHERE ID_USER=".(int)$this->getId());
            $this->userData["STAT"] = 1;
        } else {
            /*
             * Simply unlock the user
             */
            $this->db->querynow("UPDATE `user` SET STAT=1 WHERE ID_USER=".(int)$this->getId());
            $this->userData["STAT"] = 1;
        }
        
        if (!$registration) {
            // Mail an den Benutzer
            sendMailTemplateToUser(0, $this->getId(), 'USER_REG_CONFIRM', $this->userData, false);
            eventlog("info", 'User freigeschaltet "'.$this->getName().'"');
        }
    }

    /**
     * @throws Exception
     */
    protected function lock() {
        $this->db->querynow("UPDATE `user` SET STAT=0 WHERE ID_USER=".(int)$this->getId());
        $this->userData["STAT"] = 0;
        eventlog("warning", 'User "'.$this->getName().'" gesperrt!');
    }
}