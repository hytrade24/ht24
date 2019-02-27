<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 10.09.14
 * Time: 11:56
 */

class Api_StringManagement {

    private static $instance = array();

    /**
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return Api_StringManagement
     */
    public static function getInstance(ebiz_db $db, $langval = null) {
        if ($langval === null) {
            $langval = $GLOBALS['langval'];
        }
        if (!array_key_exists($langval, self::$instance)) {
            self::$instance[$langval] = new Api_StringManagement($db, $langval);
        }
        return self::$instance[$langval];
    }

    private $db;
    private $langval;
    private $arCached;
    private $arCachedTable;

    function __construct(ebiz_db $db, $langval) {
        $this->db = $db;
        $this->langval = (int)$langval;

        $this->arCached = array();
        $this->arCachedTable = array();
    }

    public function readById($table, $id, $allowCached = true, $fields = "t.*, s.V1, s.V2, s.T1") {
        if ($allowCached && array_key_exists($table, $this->arCached) && array_key_exists($id, $this->arCached[$table])) {
            return $this->arCached[$table][$id];
        }
        $stringTable = "string";
        $stringTableField = "BF_LANG";
        if (array_key_exists($table, $this->arCachedTable)) {
            $stringTable = $this->arCachedTable[$table];
        } else {
            $arTableFields = $this->db->fetch_table("SHOW FIELDS FROM `".$table."`");
            foreach ($arTableFields as $fieldIndex => $arTableField) {
                if (preg_match("/^BF_LANG(.*)$/", $arTableField["Field"], $arMatches)) {
                    $stringTable = "string".strtolower($arMatches[1]);
                    $stringTableField = $arTableField["Field"];
                    break;
                }
            }
        }
        $query = "
            SELECT ".$fields."
            FROM `".$table."` t
            LEFT JOIN `".$stringTable."` s ON s.S_TABLE='".$table."' AND s.FK=t.ID_".strtoupper($table)."
                AND s.BF_LANG=if(t.".$stringTableField." & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.".$stringTableField."+0.5)/log(2)))
            WHERE t.ID_".strtoupper($table)."=".$id;
        $arString = $this->db->fetch1($query);
        if ($arString === false) {
            // Query failed
            return false;
        }
        $this->arCachedTable[$table] = $stringTable;
        if (!array_key_exists($table, $this->arCached)) {
            $this->arCached[$table] = array();
        }
        $this->arCached[$table][$id] = $arString;
        return $arString;
    }

    public function readRaw($table, $where, $fields = "t.*, s.V1, s.V2, s.T1", $limit = 1, $offset = 0) {
        $stringTable = "string";
        $stringTableField = "BF_LANG";
        if (array_key_exists($table, $this->arCachedTable)) {
            $stringTable = $this->arCachedTable[$table];
        } else {
            $arTableFields = $this->db->fetch_table("SHOW FIELDS FROM `".$table."`");
            foreach ($arTableFields as $fieldIndex => $arTableField) {
                if (preg_match("/^BF_LANG(.*)$/", $arTableField["Field"], $arMatches)) {
                    $stringTable = "string".strtolower($arMatches[1]);
                    $stringTableField = $arTableField["Field"];
                    break;
                }
            }
        }
        $this->arCachedTable[$table] = $stringTable;
        $query = "
            SELECT ".$fields."
            FROM `".$table."` t
            LEFT JOIN `".$stringTable."` s ON s.S_TABLE='".$table."' AND s.FK=t.ID_".strtoupper($table)."
                AND s.BF_LANG=if(t.".$stringTableField." & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.".$stringTableField."+0.5)/log(2)))
            WHERE ".$where."
            ".($limit > 0 ? "LIMIT ".(int)$limit : "").($offset > 0 ? "  OFFSET ".(int)$offset : "");
        
        if ($limit == 1) {
            // Select single entry
            $arString = $this->db->fetch1($query);
            if ($arString === false) {
                // Query failed
                return false;
            }
            return $arString;
        } else {
            // Select multiple entries
            $arResult = $this->db->fetch_table($query);
            if ($arResult === false) {
                // Query failed
                return false;
            }
            return $arResult;
        }
    }

} 