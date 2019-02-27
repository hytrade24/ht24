<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 10.09.14
 * Time: 11:56
 */

class Api_LookupManagement {

    private static $instance = array();

    /**
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return Api_LookupManagement
     */
    public static function getInstance(ebiz_db $db, $langval = null) {
        if ($langval === null) {
            $langval = $GLOBALS['langval'];
        }
        if (!array_key_exists($langval, self::$instance)) {
            self::$instance[$langval] = new Api_LookupManagement($db, $langval);
        }
        return self::$instance[$langval];
    }

    private $db;
    private $langval;
    private $arCached;
    private $arCachedArt;
    private $arCachedComplete;

    function __construct(ebiz_db $db, $langval) {
        $this->db = $db;
        $this->langval = (int)$langval;

        $this->arCached = array();
        $this->arCachedArt = array();
        $this->arCachedComplete = array();
    }

    public function readValueById($lookupId, $allowCached = true) {
        if ($allowCached && array_key_exists($lookupId, $this->arCachedArt)) {
            $lookupArt = $this->arCachedArt[$lookupId];
            return $this->arCached[$lookupArt][$lookupId]["VALUE"];
        }
        return $this->db->fetch_atom("SELECT l.VALUE FROM `lookup` l WHERE l.ID_LOOKUP=".$lookupId);
    }

    public function readById($lookupId, $allowCached = true) {
        if ($allowCached && array_key_exists($lookupId, $this->arCachedArt)) {
            $lookupArt = $this->arCachedArt[$lookupId];
            return $this->arCached[$lookupArt][$lookupId];
        }
        $query = "
            SELECT l.ID_LOOKUP, l.art, l.VALUE, s.V1, s.V2, s.T1
            FROM `lookup` l
            LEFT JOIN `string` s ON s.S_TABLE='lookup' AND s.FK=l.ID_LOOKUP
                AND s.BF_LANG=if(l.BF_LANG & ".$this->langval.", ".$this->langval.", 1 << floor(log(l.BF_LANG+0.5)/log(2)))
            WHERE l.ID_LOOKUP=".$lookupId;
        $arLookup = $this->db->fetch1($query);
        if ($arLookup === false) {
            // Query failed
            return false;
        }
        $lookupArt = $arLookup['art'];
        $this->arCachedArt[$lookupId] = $lookupArt;
        if (!array_key_exists($lookupArt, $this->arCached)) {
            $this->arCached[$lookupArt] = array();
        }
        $this->arCached[$lookupArt][$lookupId] = $arLookup;
        return $arLookup;
    }

    public function readByArt($lookupArt, $allowCached = true) {
        if ($allowCached && array_key_exists($lookupArt, $this->arCachedComplete)) {
            return $this->arCached[$lookupArt];
        }
        $query = "
            SELECT l.ID_LOOKUP, l.art, l.VALUE, s.V1, s.V2, s.T1
            FROM `lookup` l
            LEFT JOIN `string` s ON s.S_TABLE='lookup' AND s.FK=l.ID_LOOKUP
                AND s.BF_LANG=if(l.BF_LANG & ".$this->langval.", ".$this->langval.", 1 << floor(log(l.BF_LANG+0.5)/log(2)))
            WHERE l.art='".mysql_real_escape_string($lookupArt)."'
            ORDER BY l.F_ORDER";
        $arLookups = $this->db->fetch_table($query);
        if ($arLookups === false) {
            // Query failed
            return false;
        }
        // Completely replace the cache by the new query result
        $this->arCached[$lookupArt] = array();
        foreach ($arLookups as $lookupIndex => $arLookup) {
            $lookupId = $arLookup['ID_LOOKUP'];
            $this->arCachedArt[$lookupId] = $lookupArt;
            $this->arCached[$lookupArt][$lookupId] = $arLookup;
        }
        $this->arCachedComplete[$lookupArt] = true;
        return $this->arCached[$lookupArt];
    }

    public function readByValue($lookupArt, $lookupValue) {
        $arValues = $this->readByArt($lookupArt);
        foreach ($arValues as $lookupId => $lookupData) {
            if ($lookupData["VALUE"] == $lookupValue) {
                return $lookupData;
            }
        }
        return false;
    }

    public function readIdByValue($lookupArt, $lookupValue) {
        $arLookup = $this->readByValue($lookupArt, $lookupValue);
        if ($arLookup !== false) {
            return $arLookup['ID_LOOKUP'];
        }
        return false;
    }

} 