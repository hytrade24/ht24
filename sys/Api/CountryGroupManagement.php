<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 10.09.14
 * Time: 11:56
 */

class Api_CountryGroupManagement {

    private static $instance = array();

    /**
     * @param ebiz_db   $db
     * @param int       $root
     * @param int|null  $langval
     * @return Api_CountryGroupManagement
     */
    public static function getInstance(ebiz_db $db, $langval = null) {
        if ($langval === null) {
            $langval = $GLOBALS['langval'];
        }
        if (!array_key_exists($langval, self::$instance)) {
            self::$instance[$langval] = new Api_CountryGroupManagement($db, $langval);
        }
        return self::$instance[$langval];
    }

    private $db;
    private $langval;
    
    private $tree;

    function __construct(ebiz_db $db, $langval, $root = 1) {
        $this->db = $db;
        $this->langval = (int)$langval;
        $this->tree = new Api_TreeMixed($db, "country_group");
    }
    
    public function addCountryMapping($groupId, $arCountryIds, $force = false) {
        if (empty($arCountryIds) && $force != true) {
            return true;
        }
        $arCountryIdsEscaped = array();
        $arInsertValues = array();
        foreach ($arCountryIds as $countryIndex => $countryId) {
            $arCountryIdsEscaped[] = (int)$countryId;
            $arInsertValues[] = "(".(int)$countryId.", ".(int)$groupId.")";
        }
        // Remove countries from other groups
	    $result = null;
	    if ( $force ) {
		    $result = $this->db->querynow("
            DELETE FROM `country_group_mapping`
            WHERE FK_COUNTRY_GROUP=".(int)$groupId);
	    }
	    else {
		    $result = $this->db->querynow("
            DELETE FROM `country_group_mapping`
            WHERE FK_COUNTRY IN (".implode(", ", $arCountryIdsEscaped).")
                AND NOT FK_COUNTRY_GROUP=".(int)$groupId);
	    }
        if (!$result["rsrc"]) {
            return false;
        }
        // Remove obsolete countries from target group
        $result = $this->db->querynow("
            DELETE FROM `country_group_mapping`
            WHERE FK_COUNTRY NOT IN (".implode(", ", $arCountryIdsEscaped).")
                AND FK_COUNTRY_GROUP=".(int)$groupId);
        if (!$result["rsrc"]) {
            return false;
        }
        // Add countries to target group
        $result = $this->db->querynow($q="
            INSERT IGNORE INTO `country_group_mapping`
              (FK_COUNTRY, FK_COUNTRY_GROUP)
            VALUES
              ".implode(",\n            ", $arInsertValues));
        if (!$result["rsrc"]) {
            return false;
        }
        return true;
    }
    
    public function create($name, $parent, $arCountries = array()) {
        $idCountryGroup = $this->tree->addChild($parent, array(
            "V1"            => $name
        ));
        if ($idCountryGroup > 0) {
            $this->addCountryMapping($idCountryGroup, $arCountries);
            return $idCountryGroup;
        }
        return false;
    }
    
    public function cleanupMapping() {
        // Remove obsolete country mappings
        $result = $this->db->querynow("
            DELETE FROM `country_group_mapping`
            WHERE FK_COUNTRY_GROUP NOT IN (SELECT ID_COUNTRY_GROUP FROM `country_group`)");
        if (!$result["rsrc"]) {
            return false;
        }
        return true;
    }
    
    public function delete($groupId, $includeChilds = true) {
        if (!$this->tree->delete($groupId, $includeChilds)) {
            return false;
        }
        $this->cleanupMapping();
        return true;
    }
    
    public function fetchCountryList($groupId, $fieldName = "sc.V1", $fieldOrder = "sc.V1", $arWhere = array()) {
        $query = "
            SELECT 
              c.ID_COUNTRY, ".$fieldName." AS COUNTRY_NAME,
              IF(g.ID_COUNTRY_GROUP=".(int)$groupId.",1,0) AS COUNTRY_SELECTED,
              g.ID_COUNTRY_GROUP, sg.V1 AS GROUP_NAME
            FROM `country` c
            LEFT JOIN `string` sc ON sc.S_TABLE='country' AND sc.FK=c.ID_COUNTRY
                AND sc.BF_LANG=if(c.BF_LANG & ".$this->langval.", ".$this->langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
            LEFT JOIN `country_group_mapping` cm ON cm.FK_COUNTRY=c.ID_COUNTRY
            LEFT JOIN `country_group` g ON g.ID_COUNTRY_GROUP=cm.FK_COUNTRY_GROUP
            LEFT JOIN `string_country_group` sg ON sg.S_TABLE='country_group' AND sg.FK=g.ID_COUNTRY_GROUP
                AND sg.BF_LANG=if(g.BF_LANG_COUNTRY_GROUP & ".$this->langval.", ".$this->langval.", 1 << floor(log(g.BF_LANG_COUNTRY_GROUP+0.5)/log(2)))
            ".(!empty($arWhere) ? "WHERE ".implode(" AND ", $arWhere) : "")."
            ORDER BY g.NS_LEFT ASC, ".$fieldOrder." ASC";
        return $this->db->fetch_table($query);
    }
    
    public function fetchCountryListAssigned($groupId) {
        $query = "
            SELECT 
              c.*, sc.*
            FROM `country` c
            LEFT JOIN `country_group_mapping` cm ON cm.FK_COUNTRY=c.ID_COUNTRY
            LEFT JOIN `string` sc ON sc.S_TABLE='country' AND sc.FK=c.ID_COUNTRY
                AND sc.BF_LANG=if(c.BF_LANG & ".$this->langval.", ".$this->langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
            WHERE cm.FK_COUNTRY_GROUP".($groupId > 0 ? "=".(int)$groupId : " IS NULL")."
            ORDER BY sc.V1 ASC";
        return $this->db->fetch_table($query);
    }
    
    public function fetchCountryListAssignedAsText($groupId, $limit = 5) {
        $query = "
            SELECT 
              sc.V1 AS COUNTRY_NAME
            FROM `country_group_mapping` cm
            JOIN `country` c ON cm.FK_COUNTRY=c.ID_COUNTRY
            LEFT JOIN `string` sc ON sc.S_TABLE='country' AND sc.FK=c.ID_COUNTRY
                AND sc.BF_LANG=if(c.BF_LANG & ".$this->langval.", ".$this->langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
            WHERE cm.FK_COUNTRY_GROUP=".(int)$groupId."
            ORDER BY sc.V1 ASC
            LIMIT ".((int)$limit+1);
        $arCountryNames = $this->db->fetch_col($query);
        if (count($arCountryNames) > $limit) {
            $arCountryNames[$limit] = "...";
        }
        return implode(", ", $arCountryNames);
    }
    
    public function fetchCountryGroupPathText($groupId, $glue = " > ") {
        $arGroup = $this->db->fetch1("SELECT NS_LEFT, NS_RIGHT FROM `country_group` WHERE ID_COUNTRY_GROUP=".(int)$groupId);
        $query = "
            SELECT
              s.V1
            FROM `country_group` t
            LEFT JOIN `string_country_group` s ON s.S_TABLE='country_group' AND s.FK=t.ID_COUNTRY_GROUP
                AND s.BF_LANG=if(t.BF_LANG_COUNTRY_GROUP & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_COUNTRY_GROUP+0.5)/log(2)))
            WHERE t.NS_LEFT<=".(int)$arGroup["NS_LEFT"]." AND t.NS_RIGHT>=".(int)$arGroup["NS_RIGHT"]."
            ORDER BY t.NS_LEFT ASC";
        $arGroupNames = $this->db->fetch_col($query);
        return implode($glue, $arGroupNames);
    }
    
    public function fetchById($id) {
        $query = "
            SELECT t.*, s.*
            FROM `country_group` t
            LEFT JOIN `string_country_group` s ON s.S_TABLE='country_group' AND s.FK=t.ID_COUNTRY_GROUP
                AND s.BF_LANG=if(t.BF_LANG_COUNTRY_GROUP & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_COUNTRY_GROUP+0.5)/log(2)))
            WHERE t.ID_COUNTRY_GROUP=".(int)$id;
        return $this->db->fetch1($query);
    }
    
    public function fetchTree() {
        $this->tree->getChildsQuery(null, $arJoins, $arWhere, $arOrder, $arHaving, true, "t");
        $arJoins[] = "
            LEFT JOIN `string_country_group` s ON s.S_TABLE='country_group' AND s.FK=t.ID_COUNTRY_GROUP
                AND s.BF_LANG=if(t.BF_LANG_COUNTRY_GROUP & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_COUNTRY_GROUP+0.5)/log(2)))";
        $query = "
            SELECT 
              t.*, s.*,
              (SELECT COUNT(*) FROM `country_group_mapping` WHERE FK_COUNTRY_GROUP=t.ID_COUNTRY_GROUP) AS COUNTRY_COUNT
            FROM `country_group` t
            " . (!empty($arJoins) ? implode("\n     ", $arJoins) : "") . "
            " . (!empty($arWhere) ? "WHERE " . implode(" AND ", $arWhere) : "") . "
            " . (!empty($arGroup) ? "GROUP BY " . implode(", ", $arGroup) : "") . "
            " . (!empty($arHaving) ? "HAVING " . implode(" AND ", $arHaving) : "") . "
            " . (!empty($arOrder) ? "ORDER BY " . implode(", ", $arOrder) : "");
        return $this->db->fetch_table($query);
    }
    
    public function moveInto($groupId, $targetId, $includeChilds = true) {
        return $this->tree->moveInto($groupId, $targetId, $includeChilds);
    }
    
    public function moveBefore($groupId, $targetId, $includeChilds = true) {
        return $this->tree->moveBefore($groupId, $targetId, $includeChilds);
    }
    
    public function moveAfter($groupId, $targetId, $includeChilds = true) {
        return $this->tree->moveAfter($groupId, $targetId, $includeChilds);
    }
    
    public function update($id, $name, $arCountries = array(), $force = false) {
        $result = $this->db->update("country_group", array(
            "ID_COUNTRY_GROUP"  => $id,
            "V1"                => $name
        ));
        if ($result) {
            return $this->addCountryMapping($id, $arCountries, $force);
        }
        return false;
    }

    public function debug()
    {
        $this->tree->debug();
    }

} 