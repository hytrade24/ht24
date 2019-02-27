<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 10.09.14
 * Time: 11:56
 */

class Api_UserManagement {

    private static $instance = array();

    /**
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return Api_UserManagement
     */
    public static function getInstance(ebiz_db $db, $langval = null) {
        if ($langval === null) {
            $langval = $GLOBALS['langval'];
        }
        if (!array_key_exists($langval, self::$instance)) {
            self::$instance[$langval] = new Api_UserManagement($db, $langval);
        }
        return self::$instance[$langval];
    }

    private $db;
    private $langval;

    function __construct(ebiz_db $db, $langval) {
        $this->db = $db;
        $this->langval = (int)$langval;
    }

    /**
     * Fetch one user by the given parameters.  (As assoc array)
     * @param $arParams
     * @return assoc
     */
    function fetchOne($arParams) {
        $arResult = $this->db->fetch1(
            $this->generateFetchQuery($arParams)
        );
        return $arResult;
    }

    /**
     * Fetch one user by the given parameters.  (As user object)
     * @param $arParams
     * @return Api_Entities_User
     */
    function fetchOneAsObject($arParams) {
        $arResult = $this->db->fetch1(
            $this->generateFetchQuery($arParams)
        );
        return (is_array($arResult) ? new Api_Entities_User($arResult, $this->db) : false);
    }

    /**
     * Fetch multiple users by the given parameters. (Each as assoc array)
     * @param $arParams
     * @return array
     */
    function fetchAll($arParams) {
        $arResultList = $this->db->fetch_table(
            $this->generateFetchQuery($arParams)
        );
        return $arResultList;
    }

    /**
     * Fetch multiple users by the given parameters. (Each as user object)
     * @param $arParams
     * @return array
     */
    function fetchAllAsObject($arParams) {
        $arResultList = $this->db->fetch_table(
            $this->generateFetchQuery($arParams)
        );
        return Api_Entities_User::createMultipleFromArray($arResultList);
    }

    private function generateFetchWhere($arParams) {
        $where = array();
        
        if (array_key_exists("ID_USER", $arParams)) {
            $where[] = "u.ID_USER=".(int)$arParams["ID_USER"];
        }
        
        return (empty($where) ? "" : "WHERE ".implode(" AND ", $where));
    }

    private function generateFetchQuery($arParams) {
        $limit = (array_key_exists("LIMIT", $arParams) ? $arParams["LIMIT"] : 10);
        return "
          SELECT
            *
          FROM `user` u
          ".$this->generateFetchWhere($arParams)."
          LIMIT ".$limit;
    }
    
} 