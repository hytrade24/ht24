<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.5.2
 */

class CurrencyManagement {

    private static $instance = null;

    public static function getInstance(ebiz_db $db) {
        if (self::$instance === null) {
            self::$instance = new CurrencyManagement($db);
        }
        return self::$instance;
    }

    private $db;

    public function __construct(ebiz_db $db) {
        $this->db = $db;
    }

    public function deleteById($id) {
        return $this->db->delete("currency", $id);
    }

    public function getById($id, $bf_lang = null) {
        $arWhere = array("c.ID_CURRENCY=".(int)$id);
        $arResult = $this->db->fetch1( $this->getQuery($arWhere) );
        return $arResult;
    }

    public function getListByParams($arParams = array(), $bf_lang = null, $offset = 0, $perpage = null, &$all = 0) {
        $arWhere = array();
        // Build query from parameters
        $arResults = $this->db->fetch_table( $this->getQuery($arWhere, $bf_lang, $offset, $perpage) );
        $all = 0; // TODO: Get number of result for pager
        return $arResults;
    }

    private function getQuery($arWhere, $bf_lang = null, $offset = 0, $perpage = null) {
        // Get default parameters
        if ($bf_lang === null) {
            $bf_lang = $GLOBALS['langval'];
        }
        // Build query
        $query = "
            SELECT
                c.ID_CURRENCY,
                c.RATIO_FROM_DEFAULT,
                c.ISO_CURRENCY_FORMAT,
                CONCAT(DAY(c.LAST_UPDATED),'.',MONTH(c.LAST_UPDATED),'.',YEAR(c.LAST_UPDATED),' ',HOUR(c.LAST_UPDATED),':',MINUTE(c.LAST_UPDATED)) as LAST_UPDATED,
                c.AUTOMATICALLY_UPDATED,
                (1 / c.RATIO_FROM_DEFAULT) as RATIO_TO_DEFAULT,
                s.V1, s.V2, s.T1
            FROM `currency` c
            LEFT JOIN `string_currency` s ON s.S_TABLE='currency' AND s.FK=c.ID_CURRENCY
                AND s.BF_LANG=if(c.BF_LANG_CURRENCY & ".$bf_lang.", ".$bf_lang.", 1 << floor(log(c.BF_LANG_CURRENCY+0.5)/log(2)))
            ".(!empty($arWhere) ? "WHERE ".implode(" AND ", $arWhere) : "")."
            ".($perpage !== null ? "LIMIT ".(int)$offset.", ".(int)$perpage : "");
        return $query;
    }

    public function update($arCurrency) {
    	$arCurrency["LAST_UPDATED"] = date('Y-m-d H:i:s');
        return $this->db->update("currency", $arCurrency);
    }

} 