<?php
/* ###VERSIONSBLOCKINLCUDE### */



class ClubCategoryManagement {
	private static $db;
    private static $langval;
	private static $instance = null;

    const CATEGORY_ROOT = 8;
    const MAX_CATEGORY_PER_USER = 3;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ClubCategoryManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function addClubCategories($categories, $clubId) {
        $db = $this->getDb();
        $this->deleteAllClubCategoriesByClubId($clubId);

        $i = 0;
        foreach($categories as $key => $value) {
            if($i < self::MAX_CATEGORY_PER_USER) {
                $db->update("club_category", array(
                    'FK_CLUB' => $clubId,
                    'FK_KAT' => $value
                ));
            }

            $i++;
        }
    }

    public function deleteAllClubCategoriesByClubId($clubId) {
        $db = $this->getDb();

        $db->querynow("DELETE FROM club_category WHERE FK_CLUB = '".mysql_real_escape_string($clubId)."'");
    }

    public function fetchAllClubCategoriesByClubId($clubId) {
        $db = $this->getDb();
        $langval = $this->getLangval();

        $a = $db->fetch_table($x = "SELECT c.*, (SELECT V1 FROM string_kat s WHERE s.FK = c.FK_KAT AND BF_LANG = '".$langval."' and S_TABLE='kat') as V1 FROM club_category c WHERE c.FK_CLUB = '".mysql_real_escape_string($clubId)."'");
        return $a;
    }

    public function getClubCategoryJSONTree($preSelectedNodes = array()) {
        return json_encode($this->getClubCategoryTree($preSelectedNodes));
    }

    public function getClubCategoryTreeFlat($categoryId = null, $preSelectedNodes = array(), $arTreeNested = null, &$arResult = array(), $level = 0) {
        if ($arTreeNested === null) {
            $arTreeNested = $this->getClubCategoryTree($preSelectedNodes = array());
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
                $this->getClubCategoryTreeFlat($categoryId, $preSelectedNodes, $itemChilds, $arResult, $level + 1);
            }
        }
        return $arResult;
    }

    public function getClubCategoryTree($preSelectedNodes = array()) {
        require_once 'sys/lib.nestedsets.php'; // Nested Sets

        $db = $this->getDb();

        $nest = new nestedsets('kat', ClubCategoryManagement::CATEGORY_ROOT, false, $db);

        return $this->getClubCategoryArrayTreeRecursive(null, $nest, array(), $preSelectedNodes);
    }

    private function getClubCategoryArrayTreeRecursive($id, nestedsets $nest, $visitedNodes = array(), $preSelectedNodes = array()) {
        require_once 'sys/lib.shop_kategorien.php';

        $langval = $this->getLangval();
        $db = $this->getDb();
        $root = ClubCategoryManagement::CATEGORY_ROOT;

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
					$children = $this->getClubCategoryArrayTreeRecursive($element['ID_KAT'], $nest, $visitedNodes, $preSelectedNodes);

					$childrenKeys = array();
					foreach($children as $cKey => $child) {
						$childrenKeys = array_merge($childrenKeys, $child['childrenKeys'], array($child['key']));
					}
          $tplLink->vars['TITLE'] = $element['V1'];
					$treeArray[] = array(
						'key' => $element['ID_KAT'],
						'parentKey' => $id,
						'title' => $element['V1'],
            'link' => $tplLink->tpl_uri_action("clubs,".$element['ID_KAT'].",".addnoparse(chtrans($element['V1']))."|KAT_NAME={TITLE}"),
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