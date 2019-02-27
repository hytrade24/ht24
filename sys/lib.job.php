<?php
/* ###VERSIONSBLOCKINLCUDE### */

class JobManagement {
	private static $db;
    private static $langval = 128;
	private static $instance = null;

    const CATEGORY_ROOT = 6;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return JobManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	/**
	 * Schaltet einen Job frei (Admin-Freigabe erteilen)
	 *
	 * @param int $jobId	ID des Jobs
	 */
	public function enable($jobId) {
        $db = $this->getDb();

        $ret = $db->querynow("UPDATE `job` SET OK=(OK|2) WHERE ID_JOB=".(int)$jobId);
        if (!$ret['rsrc']) {
        	// Fehler!
        	return false;
        } else {
        	return true;
        }
	}

	/**
	 * Sperrt einen Job (Admin-Freigabe entfernen)
	 *
	 * @param int $jobId	ID des Jobs
	 */
	public function disable($jobId) {
        $db = $this->getDb();

        $ret = $db->querynow("UPDATE `job` SET OK=(OK&1) WHERE ID_JOB=".(int)$jobId);
        if (!$ret['rsrc']) {
        	// Fehler!
        	return false;
        } else {
        	return true;
        }
	}

    /**
     * Holt einen Artikel anhand einer Artikel Id
     *
     * @throws 	Exception
     * @param 	$jobId
     * @return 	assoc
     */
    public function fetchByJobId($jobId) {
        $db = $this->getDb();

        $job = $db->fetch1($x = "
            SELECT
                n.*,
                s.*,
    			if(n.OK&1,1,0) as FREIGABE,
    			if(n.OK&2,1,0) as FREIGABE2
            FROM
                `job` n
			LEFT JOIN `string_job` s ON
				s.FK=n.ID_JOB AND s.S_TABLE='job' AND
				s.BF_LANG=if(n.BF_LANG_JOB & ".$this->getLangval().", ".$this->getLangval().", 1 << floor(log(n.BF_LANG_JOB+0.5)/log(2)))
			WHERE
				n.ID_JOB=".$jobId);

        return $job;
    }

    public function fetchAllJobsByUserId($userId, $param = array()) {
        $db = $this->getDb();

        $sqlLimit = "";
        if(isset($param['LIMIT']) && $param['LIMIT'] != null) {
            if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
        }

        return $db->fetch_table("
            SELECT
                n.*,
                s.*,
    			if(n.OK&1,1,0) as FREIGABE,
    			if(n.OK&2,1,0) as FREIGABE2
            FROM
                `job` n
			LEFT JOIN `string_job` s ON
				s.FK=n.ID_JOB AND s.S_TABLE='job' AND
				s.BF_LANG=if(n.BF_LANG_JOB & ".$this->getLangval().", ".$this->getLangval().", 1 << floor(log(n.BF_LANG_JOB+0.5)/log(2)))
			WHERE
				n.FK_AUTOR=".mysql_real_escape_string($userId)."
				AND n.OK = 3 and n.STAMPEND > now() 
		    ORDER BY STAMP DESC
		    ".($sqlLimit?'LIMIT '.$sqlLimit:'')."
        ");
    }

    public function fetchAllByParam($param) {
        $db = $this->getDb();

        $langval = $this->getLangval();
        $t = get_language();
        $langvalAsCode = $t['0'];

        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " j.STAMP DESC ";

        if (isset($param['JOIN_VENDOR'])) { $sqlJoin .= ' LEFT JOIN vendor v ON v.FK_USER = u.ID_USER '; }

        if(isset($param['ID_JOB']) && $param['ID_JOB'] != null ) { $sqlWhere .= " AND j.ID_JOB = '".mysql_real_escape_string($param['ID_JOB'])."' "; }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null ) { $sqlWhere .= " AND j.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(isset($param['FK_AUTOR']) && $param['FK_AUTOR'] != null ) { $sqlWhere .= " AND j.FK_AUTOR = '".mysql_real_escape_string($param['FK_AUTOR'])."' "; }
        if(isset($param['SEARCH_JOB']) && $param['SEARCH_JOB'] != null) { $sqlWhere .= " AND ((sj.V1 LIKE '%".mysql_real_escape_string($param['SEARCH_JOB'])."%') OR (sj.T1 LIKE '%".mysql_real_escape_string($param['SEARCH_JOB'])."%')) "; }
        if(isset($param['PUBLISHED']) && $param['PUBLISHED'] === true) { $sqlWhere .= " AND (j.OK = 3) "; }
        if(isset($param['EXCLUDE_ID']) && $param['EXCLUDE_ID'] != null) { $sqlWhere .= " AND j.ID_JOB != ".(int)$param['EXCLUDE_ID']; }

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

            $sqlWhere .= " AND j.FK_KAT IN (".mysql_real_escape_string(implode(',', array_keys($ids_kats))).") ";
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
        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }

        $query = "
            SELECT
                j.*,
                sj.*,
                u.NAME as USER_NAME,
                u.ID_USER as USER_ID,
                if(j.OK&1,1,0) as FREIGABE,
                if(j.OK&2,1,0) as FREIGABE2,
                ";
        if (isset($param['JOIN_VENDOR'])) {
        	$query .= "
        	    v.NAME as VENDOR_FIRM_NAME,
                u.FIRMA, v.LOGO, 
        	";
        }
        $query .= "
                (SELECT V1 FROM string_kat WHERE S_TABLE = 'kat' AND FK = j.FK_KAT AND BF_LANG = '".$langval."') AS KAT_NAME
            FROM
                job j
            LEFT JOIN `string_job` sj ON
                sj.FK=j.ID_JOB AND sj.S_TABLE='job' AND
                sj.BF_LANG=if(j.BF_LANG_JOB & ".$this->getLangval().", ".$this->getLangval().", 1 << floor(log(j.BF_LANG_JOB+0.5)/log(2)))
            LEFT JOIN user u ON
                u.ID_USER = j.FK_AUTOR
            ".$sqlJoin."
            WHERE
                1 = 1 and j.STAMPEND > now() 
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY j.ID_JOB
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."

        ";

        $result =  $db->fetch_table($query);
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
        $sqlOrder = " j.STAMP DESC ";

        if(isset($param['ID_JOB']) && $param['ID_JOB'] != null ) { $sqlWhere .= " AND j.ID_JOB = '".mysql_real_escape_string($param['ID_JOB'])."' "; }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null ) { $sqlWhere .= " AND j.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(isset($param['FK_AUTOR']) && $param['FK_AUTOR'] != null ) { $sqlWhere .= " AND j.FK_AUTOR = '".mysql_real_escape_string($param['FK_AUTOR'])."' "; }
        if(isset($param['SEARCH_JOB']) && $param['SEARCH_JOB'] != null) { $sqlWhere .= " AND ((sj.V1 LIKE '%".mysql_real_escape_string($param['SEARCH_JOB'])."%') OR (sj.T1 LIKE '%".mysql_real_escape_string($param['SEARCH_JOB'])."%')) "; }
        if(isset($param['PUBLISHED']) && $param['PUBLISHED'] === true) { $sqlWhere .= " AND (j.OK = 3) "; }

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

            $sqlWhere .= " AND j.FK_KAT IN (".mysql_real_escape_string(implode(',', array_keys($ids_kats))).") ";
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
                SQL_CALC_FOUND_ROWS j.ID_JOB
            FROM
                job j
            LEFT JOIN `string_job` sj ON
                sj.FK=j.ID_JOB AND sj.S_TABLE='job' AND
                sj.BF_LANG=if(j.BF_LANG_JOB & ".$this->getLangval().", ".$this->getLangval().", 1 << floor(log(j.BF_LANG_JOB+0.5)/log(2)))
            ".$sqlJoin."
            WHERE
                1 = 1 and j.STAMPEND > now() 
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY j.ID_JOB
        ");

        $x = $db->querynow($q);
        $y = $db->fetch_atom("SELECT FOUND_ROWS()");

        return $y;
    }


	/**
	 * Speichert einen Artikel in mehreren Sprachen
	 *
	 * @param $jobData	assoc	Assoziatives Array mit den Daten des Artikels.
	 * @param $jobId	int		ID des Artikels (optional)
	 */
    public function saveJobMultiLang($jobData, $jobId = null) {
    	if ($jobId == null) $jobId = $jobData["ID_JOB"];

    	$jobDataBase = $jobData;
        unset($jobDataBase["V1"]);
        unset($jobDataBase["V2"]);
        unset($jobDataBase["T1"]);

		if (!$jobDataBase['FREIGABE']) $jobDataBase['FREIGABE'] = 0;
		$jobDataBase['OK'] = ($jobDataBase['FREIGABE'] > 0 ? 1 : 0);
		$jobDataBase['FK_AUTOR'] = $jobDataBase['FK_USER'];
		if ($jobDataBase['OK']) $jobDataBase['STAMP'] = date("Y-m-d H:i");

        $db = $this->getDb();

        $languages = $jobDataBase["langs"];
        foreach ($languages as $langval) {
        	$ar_cur_lang = $jobDataBase;
        	$ar_cur_lang["V1"] = $jobData["V1"][$langval];
        	$ar_cur_lang["V2"] = $jobData["V2"][$langval];
        	$ar_cur_lang["T1"] = $this->saveJobParseText($jobData["T1"][$langval], $jobData["ID_JOB"], $jobData["FK_USER"]);

        	if (!empty($ar_cur_lang["V1"]) || !empty($ar_cur_lang["V2"]) || !empty($ar_cur_lang["T1"])) {
	        	$ar_cur_lang["BF_LANG_JOB"] = $langval;
	        	$id_insert = $db->update("job", $ar_cur_lang);
	        	if (($jobId == null) && ($id_insert > 0)) {
	        		$jobId = $jobDataBase["ID_JOB"] = $id_insert;
	        	}
        	}
        }
        return $jobId;
    }

    private function saveJobParseText($text, $id_job, $id_user) {
    	global $ab_path;
        $db = $this->getDb();

		### TAG FILTER
		$text = strip_tags($text, "<img><br><b><strong><i><u><ul><li><ol><p><em><a><div>");
		### KICK STYLES
		$text = preg_replace("/(style=)(\"|')([^\"']*)(\"|')/", "", $text);
		### externe Bilder finden und kicken
		$text = preg_replace("%(<img)([^>]*)(src=)('|\")([^/])([^>]*)(>)%", "", $text);
		### Bilder ersetzen
		preg_match_all("/(\[img:)(\s?)([0-9]*)(\])/si", $text, $find);
		if (count($find[3]) > 0) {
			$bilder = $db->querynow("
				SELECT * FROM `user2img` WHERE
					FK_USER=".(int)$id_user." AND FK=".(int)$id_job." AND
					WHAT='JOB' AND ID_USER2IMG IN (".implode(',', $find[3]).")");
			$img = array();
			while($row = mysql_fetch_assoc($bilder['rsrc'])) {
				$img[$row['ID_USER2IMG']] = $row;
			} // bilder aus DB
			for($i=0; $i<count($find[3]); $i++) {
				if($ar = $img[$find[3][$i]]) {
					$inf = getimagesize($ab_path.$ar['PATH'].$ar['IMG']);
					$rpl = '<img src="/'.$ar['PATH'].$ar['IMG'].'" '.$inf[3].' />';
					$text = str_replace($find[0][$i], $rpl, $text);
				} // bild gefunden
			} // for
		} // bildtags gefunden
		return $text;
    }

    public function getJobCategoryTreeFlat($categoryId = null, $preSelectedNodes = array(), $arTreeNested = null, &$arResult = array(), $level = 0) {
        if ($arTreeNested === null) {
            $arTreeNested = $this->getJobCategoryTree($preSelectedNodes = array());
        }
        foreach ($arTreeNested as $index => $item) {
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
                $this->getJobCategoryTreeFlat($categoryId, $preSelectedNodes, $itemChilds, $arResult, $level + 1);
            }
        }
        return $arResult;
    }

    public function getJobCategoryJSONTree($preSelectedNodes = array()) {
        return json_encode($this->getJobCategoryTree($preSelectedNodes));
    }

    public function getJobCategoryTree($preSelectedNodes = array()) {
        require_once 'sys/lib.nestedsets.php'; // Nested Sets

        $db = $this->getDb();

        $nest = new nestedsets('kat', JobManagement::CATEGORY_ROOT, false, $db);

        return $this->getJobCategoryArrayTreeRecursive(null, $nest, array(), $preSelectedNodes);
    }

    private function getJobCategoryArrayTreeRecursive($id, nestedsets $nest, $visitedNodes = array(), $preSelectedNodes = array()) {
        require_once 'sys/lib.shop_kategorien.php';

        $langval = $this->getLangval();
        $db = $this->getDb();
        $root = JobManagement::CATEGORY_ROOT;

        $rootrow = $db->fetch1("select t.*, s.V1, s.V2, s.T1 from `kat` t left join string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT and s.BF_LANG='" . $langval . "' where LFT=1 and ROOT='" . $root . "' ");

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
            $ar_path = $db->fetch_table($nest->nestQuery('and (' . $lft . ' between t.LFT and t.RGT) AND t.B_VIS = 1 ', '', '1 as is_last,1 as kidcount,1 as is_first,t.LFT=' . $lft . ' as is_current,', false), 'ID_KAT');
            $n_level = $ar_path[$id]['level'];
            $ar_path = array_values($ar_path);
        }

        // Kinder lesen
        $s_sql = $nest->nestQuery(' and (t.LFT between ' . $lft . ' and ' . $rgt . ')', '', 't.RGT-t.LFT>1 as haskids,', true);
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

        if (is_array($ar_path) && count($ar_path) > 0) {
            $treeArray = array();
            $tplLink = new Template("tpl/".$GLOBALS['s_lang']."/empty.htm");

			foreach($ar_path as $key => $element) {
				if(!in_array($element['ID_KAT'], $visitedNodes)) {
					$visitedNodes[] = $element['ID_KAT'];
					$children = $this->getJobCategoryArrayTreeRecursive($element['ID_KAT'], $nest, $visitedNodes, $preSelectedNodes);

					$childrenKeys = array();
					foreach($children as $cKey => $child) {
						$childrenKeys = array_merge($childrenKeys, $child['childrenKeys'], array($child['key']));
					}

          $tplLink->vars['TITLE'] = $element['V1'];
					$treeArray[] = array(
						'key' => $element['ID_KAT'],
						'parentKey' => $id,
						'title' => $element['V1'],
            'link' => $tplLink->tpl_uri_action("jobs,".$element['ID_KAT'].",".addnoparse(chtrans($element['V1']))."|KAT_NAME={TITLE}"),
						'select' => in_array($element['ID_KAT'], $preSelectedNodes),
						'hideCheckbox' => (is_array($children) && (count($children) > 0)),
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