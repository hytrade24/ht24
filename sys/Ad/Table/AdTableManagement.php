<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 22.06.15
 * Time: 16:36
 */

class Ad_Table_AdTableManagement {

    private static $instance = array();

    /**
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return Ad_Table_AdTableManagement
     */
    public static function getInstance(ebiz_db $db, $langval = null) {
        if ($langval === null) {
            $langval = $GLOBALS['langval'];
        }
        if (!array_key_exists($langval, self::$instance)) {
            self::$instance[$langval] = new Ad_Table_AdTableManagement($db, $langval);
        }
        return self::$instance[$langval];
    }

    private $db;
    private $langval;
    private $arCachedTables;
    private $arCachedFields;

    function __construct(ebiz_db $db, $langval) {
        $this->db = $db;
        $this->langval = (int)$langval;
        $this->arCachedTables = array();
        $this->arCachedFields = array();
    }
    
    public function createFieldMapping($fieldId, $enableField = false) {
        $arField = $this->getFieldById($fieldId);
        $arTable = $this->getTableById($arField["FK_TABLE_DEF"]);
        $ar_katlist = array_keys(
            $this->db->fetch_nar("SELECT ID_KAT FROM `kat` WHERE KAT_TABLE='".mysql_real_escape_string($arTable['T_NAME'])."'")
        );
        $ar_kat2field = array();
        if ($enableField) {
            foreach ($ar_katlist as $index => $katId) {
                $ar_kat2field[] = "(".$katId.", ".$fieldId.", 1, ".$arField['B_NEEDED'].", ".($arField['B_SEARCH'] > 0 ? 1 : 0).")";
            }
        } else {
            foreach ($ar_katlist as $index => $katId) {
                $ar_kat2field[] = "(".$katId.", ".$fieldId.", 0, ".$arField['B_NEEDED'].", ".($arField['B_SEARCH'] > 0 ? 1 : 0).")";
            }
        }
        if (!empty($ar_kat2field)) {
            $query = "INSERT IGNORE INTO `kat2field` (FK_KAT, FK_FIELD, B_ENABLED, B_NEEDED, B_SEARCHFIELD) ".
                "VALUES \n  ".implode(",\n   ", $ar_kat2field);
            $this->db->querynow($query);
        }
        return true;
    }

    public function getFieldById($fieldId, $useCache = true) {
        if ($useCache && array_key_exists($fieldId, $this->arCachedFields)) {
            return $this->arCachedFields[$fieldId];
        }
        $query = "
			SELECT
			    t.*, s.V1, s.V2, s.T1,
				sg.V1 AS FIELD_GROUP, sg.V2 AS FIELD_GROUP_DESC
			FROM `field_def` t
			LEFT JOIN `string_field_def` s on s.S_TABLE='field_def' and s.FK=t.ID_FIELD_DEF
					and s.BF_LANG=if(t.BF_LANG_FIELD_DEF & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_FIELD_DEF+0.5)/log(2)))
			LEFT JOIN `field_group` g ON t.FK_FIELD_GROUP=g.ID_FIELD_GROUP
			LEFT JOIN `string_app` sg on sg.S_TABLE='field_group' and sg.FK=g.ID_FIELD_GROUP
					and sg.BF_LANG=if(g.BF_LANG_APP & ".$this->langval.", ".$this->langval.", 1 << floor(log(g.BF_LANG_APP+0.5)/log(2)))
			WHERE
				t.ID_FIELD_DEF=".(int)$fieldId;
        $arField = $this->db->fetch1($query);
        if ($useCache) {
            $this->arCachedFields[$fieldId] = $arField;
        }
        return $arField;
    }
    
    public function getTableById($tableId, $useCache = true) {
        if ($useCache && array_key_exists($tableId, $this->arCachedTables)) {
            return $this->arCachedTables[$tableId];
        }
        $query = "
          SELECT
            t.*, s.V1, s.V2, s.T1
          FROM `table_def` t
          LEFT JOIN string_app s 
            ON s.S_TABLE='table_def' AND s.FK=t.ID_TABLE_DEF
            AND s.BF_LANG=if(t.BF_LANG_APP & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
          WHERE t.ID_TABLE_DEF=".(int)$tableId;
        $arTable = $this->db->fetch1($query);
        if ($useCache) {
            $this->arCachedTables[$tableId] = $arTable;
        }
        return $arTable;
    }
    
}