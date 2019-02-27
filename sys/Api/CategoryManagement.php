<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 10.09.14
 * Time: 11:56
 */

class Api_CategoryManagement {

    private static $instance = array();

    /**
     * @param ebiz_db   $db
     * @param int       $root
     * @param int|null  $langval
     * @return Api_CategoryManagement
     */
    public static function getInstance(ebiz_db $db, $root = 1, $langval = null) {
        if ($langval === null) {
            $langval = $GLOBALS['langval'];
        }
        if (!array_key_exists($langval, self::$instance)) {
            self::$instance[$langval] = new Api_CategoryManagement($db, $root, $langval);
        }
        return self::$instance[$langval];
    }

    private $db;
    private $langval;
    private $root;
    private $arCached;

    function __construct(ebiz_db $db, $root = 1, $langval) {
        $this->db = $db;
        $this->root = (int)$root;
        $this->langval = (int)$langval;

        $this->arCached = array();
    }

    public function readById($categoryId, $allowCached = true) {
        if ($allowCached && array_key_exists($categoryId, $this->arCached)) {
            return $this->arCached[$categoryId];
        }

        $query = "
			SELECT
				el.*, s.T1, s.V1, s.V2
			FROM `kat` el
			LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=el.ID_KAT AND s.BF_LANG=if(el.BF_LANG_KAT & ".$this->langval.", ".$this->langval.", 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
		    WHERE el.ID_KAT=".(int)$categoryId." AND el.ROOT=".(int)$this->root."
            ORDER BY el.`ORDER_FIELD`";
        $arCategory = $this->db->fetch1($query);
        if ($arCategory === false) {
            // Query failed
            return false;
        }
        $this->arCached[$categoryId] = $arCategory;
        return $arCategory;
    }

} 