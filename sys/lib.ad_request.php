<?php
/* ###VERSIONSBLOCKINLCUDE### */



class AdRequestManagement {
    private static $db;
    private static $langval;
   	private static $instance = null;

    const CATEGORY_ROOT = 5;
    const MAX_CATEGORY_PER_USER = 3;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return AdRequestManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function find($adRequestId) {
        $db = $this->getDb();
        $langval = $this->getLangval();


        $q = $db->fetch1("
            SELECT
                ar.*,
                u.ID_USER AS USER_ID,
                u.NAME AS USER_NAME,
                u.CACHE AS USER_CACHE,
                u.VORNAME AS USER_VORNAME,
                u.NACHNAME AS USER_NACHNAME,
                u.PLZ AS USER_PLZ,
                u.ORT AS USER_ORT,
                (SELECT V1 FROM string WHERE S_TABLE = 'country' AND FK = u.FK_COUNTRY AND BF_LANG = '".$langval."') AS USER_LAND,
                (SELECT V1 FROM string_kat WHERE S_TABLE = 'kat' AND FK = ar.FK_KAT AND BF_LANG = '".$langval."') AS KAT_NAME
            FROM ad_request ar
            JOIN user u ON u.ID_USER = ar.FK_USER
            WHERE ar.ID_AD_REQUEST = '".mysql_real_escape_string($adRequestId)."'
        ");

        return $q;
    }


    public function fetchAllByParam($param) {
        $db = $this->getDb();

        $langval = $this->getLangval();
        /**
         * @todo schlecht gelöst, Refactor Bedarf
         */

        $t = get_language();
        $langvalAsCode = $t['0'];

        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " ar.STAMP_START DESC ";

        if(isset($param['ID_AD_REQUEST']) && $param['ID_AD_REQUEST'] != null ) { $sqlWhere .= " AND ar.ID_AD_REQUEST = '".mysql_real_escape_string($param['ID_AD_REQUEST'])."' "; }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null ) { $sqlWhere .= " AND ar.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(isset($param['SEARCH_AD_REQUEST']) && $param['SEARCH_AD_REQUEST'] != null) { $sqlWhere .= " AND ((ar.PRODUKTNAME LIKE '%".mysql_real_escape_string($param['SEARCH_AD_REQUEST'])."%') OR (ar.BESCHREIBUNG LIKE '%".mysql_real_escape_string($param['SEARCH_AD_REQUEST'])."%')) "; }
        //if(isset($param['ORT']) && $param['ORT'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND (p.ORT LIKE '".mysql_real_escape_string($param['ORT'])."%' OR p.PLZ LIKE '".mysql_real_escape_string($param['ORT'])."%' OR v.ORT LIKE '".mysql_real_escape_string($param['ORT'])."%' OR v.PLZ LIKE '".mysql_real_escape_string($param['ORT'])."%') "; }
        //if(isset($param['FK_COUNTRY']) && $param['FK_COUNTRY'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND v.FK_COUNTRY = '".mysql_real_escape_string($param['FK_COUNTRY'])."' "; }
        if(isset($param['STATUS']) && $param['STATUS'] != null ) { $sqlWhere .= " AND ar.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }
        if(isset($param['CATEGORY']) && $param['CATEGORY'] != null) {
            $row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=" . $param['CATEGORY']);
            $ids_kats = $db->fetch_nar("
              SELECT ID_KAT
                FROM `kat`
              WHERE
                (LFT >= " . $row_kat["LFT"] . ") AND
                (RGT <= " . $row_kat["RGT"] . ") AND
                (ROOT = " . $row_kat["ROOT"] . ")
            ");

            $sqlWhere .= " AND ar.FK_KAT IN (".mysql_real_escape_string(implode(',', array_keys($ids_kats))).") ";
        }

        /**
         * Umkreissuche
         */
        if(!empty($param['LONGITUDE']) && !empty($param['LATITUDE']))
        {
            $radius = 6368;

            $rad_b = $param['LATITUDE'];
            $rad_l = $param['LONGITUDE'];

            $rad_l = $rad_l / 180 * M_PI;
            $rad_b = $rad_b / 180 * M_PI;

            $sqlWhere .= " AND (
		 		 	".$radius." * SQRT(ABS(2*(1-cos(RADIANS(ar.LATITUDE)) *
					 cos(".$rad_b.") * (sin(RADIANS(ar.LONGITUDE)) *
					 sin(".$rad_l.") + cos(RADIANS(ar.LONGITUDE)) *
					 cos(".$rad_l.")) - sin(RADIANS(ar.LATITUDE)) * sin(".$rad_b."))))
				) <= ".$db->fetch_atom("select `value` from lookup where ID_LOOKUP =".$param['LU_UMKREIS']);;
        } // umkreissuche

        if(isset($param['LIMIT']) && $param['LIMIT'] != null) {
            if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
        }
        if(isset($param['BF_LANG']) && $param['BF_LANG'] != null) { $langval = $param['BF_LANG']; } else { $langval = $this->getLangval(); }


        $q = ($x = "
            SELECT
                ar.*,
                SUBSTRING(ar.BESCHREIBUNG, 1, 200) AS BESCHREIBUNG_KURZ,
                u.ID_USER AS USER_ID,
                u.NAME AS USER_NAME,
                u.VORNAME AS USER_VORNAME,
                u.NACHNAME AS USER_NACHNAME,
                u.PLZ AS USER_PLZ,
                u.ORT AS USER_ORT,
                (SELECT V1 FROM string WHERE S_TABLE = 'country' AND FK = u.FK_COUNTRY AND BF_LANG = '".$langval."') AS USER_LAND,
                (SELECT V1 FROM string_kat WHERE S_TABLE = 'kat' AND FK = ar.FK_KAT AND BF_LANG = '".$langval."') AS KAT_NAME
            FROM
                ad_request ar
            JOIN user u ON u.ID_USER = ar.FK_USER
            ".$sqlJoin."
            WHERE
                1 = 1
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY ar.ID_AD_REQUEST
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."

        ");
        //echo $q;
        $result =  $db->fetch_table($q);

        return $result;
    }

    public function countByParam($param) {
        $db = $this->getDb();

        $langval = $this->getLangval();
        /**
         * @todo schlecht gelöst, Refactor Bedarf
         */
        $t = get_language();
        $langvalAsCode = $t['0'];

        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " ar.STAMP_START DESC ";

        if(isset($param['SEARCH_AD_REQUEST']) && $param['SEARCH_AD_REQUEST'] != null) { $sqlWhere .= " AND ((ar.PRODUKTNAME LIKE '%".mysql_real_escape_string($param['SEARCH_AD_REQUEST'])."%') OR (ar.BESCHREIBUNG LIKE '%".mysql_real_escape_string($param['SEARCH_AD_REQUEST'])."%')) "; }
		if(isset($param['FK_USER']) && $param['FK_USER'] != null ) { $sqlWhere .= " AND ar.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        //if(isset($param['ORT']) && $param['ORT'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND (p.ORT LIKE '".mysql_real_escape_string($param['ORT'])."%' OR p.PLZ LIKE '".mysql_real_escape_string($param['ORT'])."%' OR v.ORT LIKE '".mysql_real_escape_string($param['ORT'])."%' OR v.PLZ LIKE '".mysql_real_escape_string($param['ORT'])."%') "; }
        //if(isset($param['FK_COUNTRY']) && $param['FK_COUNTRY'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND v.FK_COUNTRY = '".mysql_real_escape_string($param['FK_COUNTRY'])."' "; }
        if(isset($param['STATUS']) && $param['STATUS'] != null ) { $sqlWhere .= " AND ar.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }
        if(isset($param['CATEGORY']) && $param['CATEGORY'] != null) {
            $row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=" . $param['CATEGORY']);
            $ids_kats = $db->fetch_nar("
              SELECT ID_KAT
                FROM `kat`
              WHERE
                (LFT >= " . $row_kat["LFT"] . ") AND
                (RGT <= " . $row_kat["RGT"] . ") AND
                (ROOT = " . $row_kat["ROOT"] . ")
            ");

            $sqlWhere .= " AND ar.FK_KAT IN (".mysql_real_escape_string(implode(',', array_keys($ids_kats))).") ";
        }

        /**
         * Umkreissuche
         */
        if(!empty($param['LONGITUDE']) && !empty($param['LATITUDE']))
        {
            $radius = 6368;

            $rad_b = $param['LATITUDE'];
            $rad_l = $param['LONGITUDE'];

            $rad_l = $rad_l / 180 * M_PI;
            $rad_b = $rad_b / 180 * M_PI;

            $sqlWhere .= " AND (
		 		 	".$radius." * SQRT(ABS(2*(1-cos(RADIANS(j.LATITUDE)) *
					 cos(".$rad_b.") * (sin(RADIANS(j.LONGITUDE)) *
					 sin(".$rad_l.") + cos(RADIANS(j.LONGITUDE)) *
					 cos(".$rad_l.")) - sin(RADIANS(j.LATITUDE)) * sin(".$rad_b."))))
				) <= ".$db->fetch_atom("select `value` from lookup where ID_LOOKUP =".$param['LU_UMKREIS']);;
        } // umkreissuche

        if(isset($param['LIMIT']) && $param['LIMIT'] != null) {
            if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
        }
        if(isset($param['BF_LANG']) && $param['BF_LANG'] != null) { $langval = $param['BF_LANG']; } else { $langval = $this->getLangval(); }


        $q = ($x = "
            SELECT
                SQL_CALC_FOUND_ROWS ar.ID_AD_REQUEST
            FROM
                ad_request ar
            JOIN user u ON u.ID_USER = ar.FK_USER
            ".$sqlJoin."
            WHERE
                1 = 1
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY ar.ID_AD_REQUEST
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."

        ");

        $x = $db->querynow($q);
        $y = $db->fetch_atom("SELECT FOUND_ROWS()");

        return $y;
    }

    public function getAdRequestCategoryTree($preSelectedNodes = array()) {
        require_once 'sys/lib.nestedsets.php'; // Nested Sets

        $db = $this->getDb();

        $nest = new nestedsets('kat', AdRequestManagement::CATEGORY_ROOT, false, $db);

        return $this->getAdRequestCategoryArrayTreeRecursive(null, $nest, array(), $preSelectedNodes);
    }

    public function getAdRequestCategoryJSONTree($preSelectedNodes = array()) {
        return json_encode($this->getAdRequestCategoryTree($preSelectedNodes));
    }

    public function getAdRequestCategoryTreeFlat($categoryId = null, $preSelectedNodes = array(), $arTreeNested = null, &$arResult = array(), $level = 0) {
        if ( $arTreeNested === null ) {
            $arTreeNested = $this->getAdRequestCategoryTree($preSelectedNodes);
        }
        foreach ( $arTreeNested as $index => $item ) {
            $itemChilds = $item["children"];
            $itemActive = ($item["key"] == $categoryId);
            $itemInPath = in_array($categoryId, $item["childrenKeys"]);
            unset($item["children"]);
            $item["level"] = $level;
            $item["active"] = $itemActive;
            $item["in_path"] = $itemInPath;
            $arResult[] = $item;
            // If category (or child category) is active add children as well
            if (($categoryId !== null) && ($itemActive || $itemInPath)) {
                $this->getAdRequestCategoryTreeFlat($categoryId, $preSelectedNodes, $itemChilds, $arResult, $level + 1);
            }
        }
        return $arResult;
    }

    private function getAdRequestCategoryArrayTreeRecursive($id, nestedsets $nest, $visitedNodes = array(), $preSelectedNodes = array()) {
        require_once 'sys/lib.shop_kategorien.php';

        $langval = $this->getLangval();
        $db = $this->getDb();
        $root = AdRequestManagement::CATEGORY_ROOT;

        $rootrow = $db->fetch1("select t.*, s.V1, s.V2, s.T1 from `kat` t left join string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT and s.BF_LANG='".$langval."' where LFT=1 and ROOT='".$root."' ");

        if (!($id = (int)$id)) {
            $id = $rootrow['ID_KAT'];
            $lft = 1;
            $rgt = $rootrow['RGT'];
        } else {
            $lastresult = $db->querynow('select LFT,RGT from kat where ID_KAT=' . $id);
            list($lft, $rgt) = mysql_fetch_row($lastresult['rsrc']);
        }

        // Ahnenreihe lesen
        if ($lft == 1) {
            $ar_path = array();
            $n_level = 0;
        } else {
            $ar_path = $db->fetch_table($nest->nestQuery('and (' . $lft . ' between t.LFT and t.RGT)', '', '1 as is_last,1 as kidcount,1 as is_first,t.LFT=' . $lft . ' as is_current,', false), 'ID_KAT');
            $n_level = $ar_path[$id]['level'];
            $ar_path = array_values($ar_path);
        }

        // Kinder lesen
        $s_sql = $nest->nestQuery(' and (t.LFT between ' . $lft . ' and ' . $rgt . ') AND t.B_VIS = 1 ', '', 't.RGT-t.LFT>1 as haskids,', true);
        $s_sql = str_replace(' order by ', ' having level=' . (1 + $n_level) . ' order by ', $s_sql);
        $res = $db->querynow($s_sql);
        #echo ht(dump($res));

        if (!(int)$res['int_result']) // keine Kinder da -> kidcount der aktuellen Zeile auf 0
        {
            if ($n = count($ar_path)) $ar_path[$n - 1]['kidcount'] = 0;
        } else while ($row = mysql_fetch_assoc($res['rsrc'])) // sonst Kinder an Baum anhaengen
        {
            $row['kidcount'] = 0;
            $ar_path[] = $row;
        }

        if(is_array($ar_path) && count($ar_path) > 0) {
            $treeArray = array();
            $tplLink = new Template("tpl/".$GLOBALS['s_lang']."/empty.htm");

            foreach($ar_path as $key => $element) {
                if(!in_array($element['ID_KAT'], $visitedNodes)) {
                    $visitedNodes[] = $element['ID_KAT'];
                    $children = $this->getAdRequestCategoryArrayTreeRecursive($element['ID_KAT'], $nest, $visitedNodes, $preSelectedNodes);

                    $childrenKeys = array();
                    foreach($children as $cKey => $child) {
                        $childrenKeys = array_merge($childrenKeys, $child['childrenKeys'], array($child['key']));
                    }

                    $tplLink->vars['TITLE'] = $element['V1'];
                    $treeArray[] = array(
                        'key' => $element['ID_KAT'],
                        'title' => $element['V1'],
                        'link' => $tplLink->tpl_uri_action("ad_request,".$element['ID_KAT'].",".addnoparse(chtrans($element['V1']))."|KAT_NAME={TITLE}"),
                        'select' => in_array($element['ID_KAT'], $preSelectedNodes),
                        'children' => $children,
                        'childrenKeys' => $childrenKeys,
                        'expand' => true
                    );
                }
            }

            return $treeArray;
        } else {
            return null;
        }
    }

    public function deleteById($id) {
        $db = $this->getDb();

        $db->querynow("DELETE FROM ad_request WHERE ID_AD_REQUEST = '".mysql_real_escape_string($id)."'");

        return true;
    }

    public function unlockById($id) {
        $db = $this->getDb();

        $db->querynow("UPDATE ad_request SET STATUS = '1' WHERE ID_AD_REQUEST='".mysql_real_escape_string($id)."'");
    }

    public function lockById($id) {
        $db = $this->getDb();

        $db->querynow("UPDATE ad_request SET STATUS = '0' WHERE ID_AD_REQUEST='".mysql_real_escape_string($id)."'");
    }

    public function updateById($id, $ar_content) {
        $db = $this->getDb();

        $db->querynow("UPDATE ad_request
        	SET PRODUKTNAME='".mysql_real_escape_string($ar_content['PRODUKTNAME'])."',
        		BESCHREIBUNG='".mysql_real_escape_string($ar_content['BESCHREIBUNG'])."'
        	WHERE
        		ID_AD_REQUEST='".mysql_real_escape_string($id)."'");
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

    public function getLangval() {
        return self::$langval;
    }
    public function setLangval($langval) {
        self::$langval = $langval;
    }

	private function __construct() {
	}
	private function __clone() {
	}
}