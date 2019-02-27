<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 14.03.14
 * Time: 16:40
 */

require_once $ab_path.'sys/facebook/facebook.php';

class FacebookManagement {

    const   FACEBOOK_MAX_ADS_PER_PAGE = 50;

    private static $instance = null;

    private $db;

    public function __construct(ebiz_db $db) {
        $this->db = $db;
    }

    /**
     * @param ebiz_db $db
     * @return FacebookManagement
     */
    public static function getInstance(ebiz_db $db) {
        if (self::$instance !== null) {
            return self::$instance;
        } else {
            self::$instance = new FacebookManagement($db);
            return self::$instance;
        }
    }

    public function getUserFacebookSite($userId) {
        return $this->db->fetch1("SELECT * FROM `facebook_app` WHERE FK_USER=".(int)$userId);
    }

    public function getUserFacebookSiteById($siteId) {
        return $this->db->fetch1("SELECT * FROM `facebook_app` WHERE FK_PAGE_ID='".mysql_real_escape_string($siteId)."'");
    }

    public function addUserFacebookSite($userId, $facebookPageId) {
        $dbResult = $this->db->querynow("INSERT INTO `facebook_app` (FK_PAGE_ID, FK_USER)
            VALUES ('".mysql_real_escape_string($facebookPageId)."', '".mysql_real_escape_string($userId)."')");
        return $dbResult["rsrc"];
    }

    public function removeUserFacebookSite($userId, $facebookPageId) {
        $dbResult = $this->db->querynow("DELETE FROM `facebook_app`
            WHERE FK_PAGE_ID='".mysql_real_escape_string($facebookPageId)."' AND FK_USER='".mysql_real_escape_string($userId)."'");
        return $dbResult["rsrc"];
    }

    public function configureUserFacebookSite($userId, $arSettings) {
        if ($arSettings["COUNT_PER_PAGE"] > self::FACEBOOK_MAX_ADS_PER_PAGE) {
            $arSettings["COUNT_PER_PAGE"] = self::FACEBOOK_MAX_ADS_PER_PAGE;
        }
        $query_updates = array();
        foreach ($arSettings as $field => $value) {
            $query_updates[] = mysql_real_escape_string($field)."='".mysql_real_escape_string($value)."'";
        }
        $dbResult = $this->db->querynow("UPDATE `facebook_app` SET ".implode(", ", $query_updates)." WHERE FK_USER=".(int)$userId);
        return $dbResult["rsrc"];
    }

} 