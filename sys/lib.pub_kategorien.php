<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once("lib.billing.php");
require_once $GLOBALS["ab_path"]."sys/lib.ad_variants.php";
require_once $GLOBALS["ab_path"]."sys/lib.payment.adapter.user.php";
require_once $GLOBALS["ab_path"]."sys/lib.ad_payment_adapter.php";


/**
 * Static callback function for filling list fields
 *
 * @param array $row
 */
function cb_field_input(&$row) {
	if (!in_array($row["field_type"], array(6,9,10,11))) {
		return;
	}
	global $db, $langval;
	$options = $db->querynow("
      select
        t.*, s.V1, s.V2, s.T1
      from
        `liste_values` t
      left join
        string_liste_values s
        on s.S_TABLE='liste_values'
        and s.FK=t.ID_LISTE_VALUES
        and s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
      where
        t.FK_LISTE=".$row['field_liste']."
      ORDER BY t.ORDER ASC, s.V1 ASC, t.ID_LISTE_VALUES ASC
      ");

	if($row["field_type"] == 6) {
		$htm_options = '<option value="0">...</option>'."\n";
		while($row_liste = mysql_fetch_assoc($options['rsrc'])) {
			$is_selected = '^if '.$row["field_field"].'=='.$row_liste['ID_LISTE_VALUES'].'° selected="selected"^endif°';
			$htm_options .= '<option value="'.$row_liste['ID_LISTE_VALUES'].'"'.$is_selected.'>'.stdHtmlentities($row_liste['V1']).'</option>';
		}
	} elseif($row["field_type"] == 9) {
		$htm_options = '';
		while($row_liste = mysql_fetch_assoc($options['rsrc'])) {
			$is_selected = '^if '.$row["field_field"].'_VARIANT_'.$row_liste['ID_LISTE_VALUES'].'° checked="checked"^endif°';
			$is_required = $row['field_needed']?'data-required="required"':'';
			$htm_options .= '
			    <div class="checkbox">
			        <label class="checkbox inline"><input type="checkbox" name="variants['.$row["field_field"].'][]" value="'.$row_liste['ID_LISTE_VALUES'].'" onchange="validateInput(this);" data-useattributes="data" data-fieldname="'.$row["field_field"].'" '.$is_required.' '.$is_selected.'>'.stdHtmlentities($row_liste['V1']).'</label>
			    </div>';
		}
	} elseif(($row["field_type"] == 10) || ($row["field_type"] == 11)) {
		$htm_options = '';
		while($row_liste = mysql_fetch_assoc($options['rsrc'])) {
			$is_selected = '^if '.$row["field_field"].'_CHECK_'.$row_liste['ID_LISTE_VALUES'].'° checked="checked"^endif°';
			$is_required = $row['field_needed']?'data-required="required"':'';
			$htm_options .= '
			    <div class="checkbox">
			        <label><input type="checkbox" name="check['.$row["field_field"].'][]" value="'.$row_liste['ID_LISTE_VALUES'].'" onchange="validateInput(this);" data-useattributes="data" data-fieldname="'.$row["field_field"].'" '.$is_required.' '.$is_selected.'>'.stdHtmlentities($row_liste['V1']).'</label>
                </div>';
		}
	}

	$row["field_options"] = $htm_options;
}

/**
 * Statick callback function for counting the number of articles within a category.
 *
 * @param array $row
 */
function cb_count_articles(&$row) {
	global $db;
	$ids_kats = $db->fetch_nar("
      SELECT ID_KAT
        FROM `kat`
      WHERE
        (LFT >= ".$row["LFT"].") AND
        (RGT <= ".$row["RGT"].") AND
        (ROOT = ".$row["ROOT"].")
      ");
	$ids_kats = "(".implode(",",array_keys($ids_kats)).")";
	$row["ARTICLE_COUNT"] = $db->fetch_atom("
      SELECT COUNT(*)
        FROM `ad_master`
      WHERE
        FK_KAT IN ".$ids_kats." AND
        (STATUS&3)=1 AND (DELETED=0)
    ");
}

/**
 * Base class for categories
 *
 * @package Categories
 * @subpackage Public
 */
class CategoriesBase {
	/**
	 * Last error that occoured within this instance.
	 *
	 * @var string  Contains the identifier of the last error message.
	 */
	public $error;

	/**
	 * The table used for the category-tree.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Number of the kategory-tree's root
	 *
	 * @var int
	 */
	private $root;

	/**
	 * Initialize object.
	 *
	 */
	function __construct($table = 'kat', $root = 1) {
		$this->table = $table;
		$this->root = $root;
	}

	/**
	 * Destroy object
	 *
	 */
	function __destruct() {

	}

	/**
	 * Deletes the cache for the specified category or the whole category-cache
	 * if no ID is given.
	 *
	 * @param   int     $id_kat   (optional) ID of the category to delete the cache of.
	 */
	static function deleteCache($id_kat = 0, $view_only = false) {
		global $db, $ab_path;
		if ($id_kat > 0) {
			// Cache zu einzelner Kategorie löschen
			$ar_langs = $db->fetch_table("SELECT * FROM `lang` WHERE B_PUBLIC=1");
			foreach ($ar_langs as $index => $ar_lang) {
				if (!$view_only) {
					// Eingabemaske ebenfalls löschen?
					if (file_exists($ab_path."cache/marktplatz/inputfields_".$ar_lang["ABBR"].".".$id_kat.".htm")) {
						unlink($ab_path."cache/marktplatz/inputfields_".$ar_lang["ABBR"].".".$id_kat.".htm");
					}
				}
				if (file_exists($ab_path."cache/marktplatz/kat_".$ar_lang["ABBR"].".".$id_kat.".htm")) {
					unlink($ab_path."cache/marktplatz/kat_".$ar_lang["ABBR"].".".$id_kat.".htm");
				}
				if (file_exists($ab_path."cache/marktplatz/liste_".$ar_lang["ABBR"].".".$id_kat.".htm")) {
					unlink($ab_path."cache/marktplatz/liste_".$ar_lang["ABBR"].".".$id_kat.".htm");
				}
				if (file_exists($ab_path."cache/marktplatz/ariane_".$ar_lang["ABBR"].".".$id_kat.".htm")) {
					unlink($ab_path."cache/marktplatz/ariane_".$ar_lang["ABBR"].".".$id_kat.".htm");
				}
				if (file_exists($ab_path."cache/marktplatz/sbox.".$id_kat.".".$ar_lang["ABBR"].".htm")) {
					unlink($ab_path."cache/marktplatz/sbox.".$id_kat.".".$ar_lang["ABBR"].".htm");
				}
                @system("rm -f ".$ab_path."cache/marktplatz/tree_".$ar_lang["ABBR"].".".$id_kat.".*.htm");

			}
            // Suchmasken löschen
            @system("rm -f ".$ab_path."cache/marktplatz/search/*.htm");
		} else {
			// Versuche über system alle cache-dateien zu löschen
			//echo("Loesche Cache-Files...\n");
			//echo("> rm -f ".$ab_path."cache/marktplatz/*.htm\n");

            @system("rm -f ".$ab_path."cache/marktplatz/*.htm");
            // Suchmasken löschen
            @system("rm -f ".$ab_path."cache/marktplatz/search/*.htm");
			// Übergebliebene Dateien einzeln versuchen zu löschen
			$dir_cache = dir($ab_path."cache/marktplatz");
			while (false !== ($entry = $dir_cache->read())) {
				if (preg_match('/^.+\.htm$/i', $entry)) {
					unlink($ab_path."cache/marktplatz/".$entry);
				}
			}
			$dir_cache->close();
		}
	}

	/**
	 * Deletes the cache for the specified category and its parents.
	 *
	 * @param   int     $id_kat   	ID of the category to delete the cache of.
	 * @param	bool	$view_only	Only delete "view cache"? (NO category dependend input fields)
	 */
	static function deleteCacheRecursive($id_kat, $view_only = false) {
		global $db, $ab_path;
		$kat = new CategoriesBase();
		$ar_kats = $kat->kats_read_path($id_kat);
		foreach ($ar_kats as $index => $ar_kat) {
			CategoriesBase::deleteCache($ar_kat["ID_KAT"], $view_only);
		}
		CategoriesBase::deleteCache(1, $view_only);
	}

	 public static function getListValuesByListId($listId) {
		global $db, $langval;

		return $db->fetch_table("
			select
				t.*, s.V1, s.V2, s.T1
			from
				`liste_values` t
			left join
				string_liste_values s
				on s.S_TABLE='liste_values'
				and s.FK=t.ID_LISTE_VALUES
				and s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & " . $langval . ", " . $langval . ", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
			where
				t.FK_LISTE=" . (int)$listId . "
			ORDER BY t.ORDER ASC
		");
	}

	/**
	 * Read a list of categories by parent-id.
	 *
	 * @param   int   $parent
	 * @param   int   $bf_lang
	 * @return  array|bool
	 */
	public function kats_read($parent, $bf_lang = false) {
		if (!isset($parent)) {
			$this->error = "ERR_MISSING_PARAMS";
			return false;
		}
		global $db, $langval;
		if ($bf_lang == false) $bf_lang = $langval;
		$node = $db->fetch_table("SELECT el.*, s.T1, s.V1, s.V2 FROM `".$this->table."` el
        LEFT JOIN `string_".$this->table."` s ON s.S_TABLE='".$this->table."' AND s.FK=el.ID_KAT
          AND s.BF_LANG=if(el.BF_LANG_KAT & ".$bf_lang.", ".$bf_lang.", 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
        WHERE ROOT=".$this->root." AND PARENT=".$parent." ORDER BY `ORDER_FIELD`");
		if ($node) {
			return $node;
		} else {
			$this->error = "ERR_PARENT_NOT_FOUND";
			return false;
		}
	}

    /**
     * Read a list of categories by parent-id.
     *
     * @param   int   $parent
     * @param   int   $bf_lang
     * @return  array|bool
     */
    public function kats_read_tree($parent, $bf_lang = false, $childLevel = 1) {
        if (!isset($parent)) {
            $this->error = "ERR_MISSING_PARAMS";
            return false;
        }
        global $db, $langval;
        if ($bf_lang == false) $bf_lang = $langval;
        $node = $db->fetch_table("SELECT el.*, s.T1, s.V1, s.V2 FROM `".$this->table."` el
        LEFT JOIN `string_".$this->table."` s ON s.S_TABLE='".$this->table."' AND s.FK=el.ID_KAT
          AND s.BF_LANG=if(el.BF_LANG_KAT & ".$bf_lang.", ".$bf_lang.", 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
        WHERE ROOT=".$this->root." AND PARENT=".$parent." ORDER BY `ORDER_FIELD`");
        if ($node && $childLevel >= 0) {

            foreach($node as $key => $value) {
                $node[$key]["OPTIONS"] = unserialize($value["SER_OPTIONS"]);
                $children = $this->kats_read_tree($value['ID_KAT'], $bf_lang, $childLevel - 1);
                if($children) {
                    $node[$key]['CHILDREN'] = $children;
                }
            }

            return $node;
        } else {
            $this->error = "ERR_PARENT_NOT_FOUND";
            return false;
        }
    }

	/**
	 * Read a list of categories by parent-id.
	 *
	 * @param   int   $parent
	 * @param   int   $bf_lang
	 * @return  array|bool
	 */
	public function kats_read_path($parent, $bf_lang = false) {
		if (!isset($parent)) {
			$this->error = "ERR_MISSING_PARAMS";
			return false;
		}
		global $db, $langval;
		$left = (int)$db->fetch_atom("SELECT LFT FROM `".$this->table."` WHERE ID_KAT=".$parent);
		if ($left <= 0) {
			$this->error = "ERR_PARENT_NOT_FOUND";
			return false;
		}
		if ($bf_lang == false) $bf_lang = $langval;
		$node = $db->fetch_table("SELECT el.*, s.T1, s.V1, s.V2 FROM `".$this->table."` el
        LEFT JOIN `string_".$this->table."` s ON s.S_TABLE='".$this->table."' AND s.FK=el.ID_KAT
          AND s.BF_LANG=if(el.BF_LANG_KAT & ".$bf_lang.", ".$bf_lang.", 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
        WHERE el.LFT<=".$left." AND el.RGT>".$left." AND el.PARENT<>0 AND el.ROOT=".$this->root." GROUP BY el.ID_KAT ORDER BY el.LFT");
		if ($node) {
			return $node;
		} else {
			$this->error = "ERR_PARENT_NOT_FOUND";
			return false;
		}
	}

	/**
	 * Read a list of categories by parent-id, including root entries.
	 *
	 * @param   int   $parent
	 * @param   int   $bf_lang
	 * @return  array|bool
	 */
	public function kats_read_path_with_root($parent, $bf_lang = false) {
		if (!isset($parent)) {
			$this->error = "ERR_MISSING_PARAMS";
			return false;
		}
		global $db, $langval;
		$left = $db->fetch_atom("SELECT LFT FROM `".$this->table."` WHERE ID_KAT=".$parent);
		if ($bf_lang == false) $bf_lang = $langval;
		$id_root = $db->fetch_atom("SELECT ID_KAT FROM `".$this->table."` WHERE PARENT=0 AND ROOT=".$this->root);
		$id_parent2 = $db->fetch_atom("SELECT PARENT FROM `".$this->table."` WHERE ID_KAT=".$parent." AND ROOT=".$this->root);
		$node = $db->fetch_table("
        SELECT (COUNT(*)-1) AS level, node2.*, s.T1, s.V1, s.V2,
              ((min(node.RGT) - node2.RGT - (node2.LFT>1)) / 2) <= 0 AS is_last,
              ((node2.LFT-max(node.LFT) <= 1 )) AS is_first
            FROM `".$this->table."` node, `".$this->table."` node2
            LEFT JOIN `string_".$this->table."` s ON s.S_TABLE='".$this->table."' AND s.FK=node2.ID_KAT
              AND s.BF_LANG=if(node2.BF_LANG_KAT & ".$bf_lang.", ".$bf_lang.", 1 << floor(log(node2.BF_LANG_KAT+0.5)/log(2)))
           WHERE node2.B_VIS=1 AND node.ROOT=".$this->root." AND node2.ROOT=".$this->root." AND node2.PARENT<>0 AND
            ((".$left." BETWEEN node2.lft AND node2.rgt) OR (node2.PARENT=".$parent. ($id_parent2 != false ? " OR node2.PARENT=".$id_parent2 : "") .")) AND
            ((node2.LFT BETWEEN node.LFT AND node.RGT) AND (node.LFT <> node2.LFT))
        GROUP BY node2.LFT ORDER BY node2.LFT ASC;");
		if ($node) {
			return $node;
		} else {
			$this->error = "ERR_PARENT_NOT_FOUND";
			return false;
		}
	}

	public function getCategoryPathHashMap() {
		global $s_lang, $ab_path, $nar_systemsettings;

		$cacheFile = $ab_path."cache/marktplatz/category_hashmap_path.".$s_lang.".php";
		$cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
		$modifyTime = @filemtime($cacheFile);
		$diff = ((time()-$modifyTime)/60);

		if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile)) {
			$cache = new CategoriesCache();
			$cache->cacheCategoryHashMap($cacheFile);
		}

		$hashmap = file_get_contents($cacheFile);
		$hashmap = unserialize($hashmap);

		return $hashmap;
	}

	/**
	 * Reads cached files for search boxes.
	 *
	 * @param   int $id_kat
	 * @param array $ar_params
	 * @return  string|html
	 */
	public function getBoxCache($id_kat, $ar_params = array())
	{
		global $s_lang, $ab_path, $nar_systemsettings;
		$id_kat = (int)$id_kat;

        $cacheFile = $ab_path."cache/marktplatz/sbox.".$id_kat.".".$s_lang.".htm";
        $cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
        $modifyTime = @filemtime($cacheFile);
        $diff = ((time()-$modifyTime)/60);

        if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile))
		{
			$cache = new CategoriesCache();
			$cache->cacheKatFields($id_kat);
		}
		$code = @file_get_contents($cacheFile);
		$tpl = new Template($ab_path."tpl/de/empty.htm");
		$tpl->tpl_text = $code;
		foreach ($ar_params as $index => $value) {
			if (is_array($value)) {
				foreach ($value as $index_sub => $value_sub) {
					$ar_params[$index."_".$index_sub] = $value_sub;
					$ar_params["ISSET_".$index."_".$value_sub] = 1;
				}
			}
		}
		$tpl->addvars($ar_params);
		return $tpl->process();
	} // getBoxCache()
	
    public function getCacheArticleCount($id_kat, &$arCategoryList = null, $left = null, $right = null, $categoryIndexOffset = 0, $forceUpdate = false) {
			global $db;
			$cacheFileLifeTime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
			// Get related categories
			if ($arCategoryList === null) {
				$arCategoryData = $db->fetch1("
					SELECT k.LFT, k.RGT, k.ROOT, ks.ADCOUNT, ks.STAMP
					FROM `kat` k
					LEFT JOIN `kat_statistic` ks ON ks.ID_KAT=k.ID_KAT
					WHERE k.ID_KAT=".(int)$id_kat);
				if (!$forceUpdate && ($arCategoryData["ADCOUNT"] !== null)) {
					// Check if cache is valid
					$cacheTime = strtotime($arCategoryData["STAMP"]);
					$diff = ((time()-$cacheTime)/60);
					if ($diff < $cacheFileLifeTime) {
						// Return cached value
						return $arCategoryData["ADCOUNT"];
					}
				}
				$arCategoryList = $db->fetch_table("
					SELECT k.ID_KAT, k.LFT, k.RGT, ks.ADCOUNT, ks.STAMP
					FROM `kat` k
					LEFT JOIN `kat_statistic` ks ON ks.ID_KAT=k.ID_KAT
					WHERE k.LFT>=".(int)$arCategoryData["LFT"]." AND k.RGT<=".(int)$arCategoryData["RGT"]." AND k.ROOT=".(int)$arCategoryData["ROOT"]."
					ORDER BY k.LFT ASC");
			}
			// Count articles
			$result = 0;
			$offset = (int)$left;
			$categoryCount = count($arCategoryList);
			for ($categoryIndex = $categoryIndexOffset; $categoryIndex < $categoryCount; $categoryIndex++) {
				$categoryDetail = $arCategoryList[$categoryIndex];
				if ($categoryDetail["LFT"] < $offset) {
					// Skip until next relevant category is reached
					continue;
				}
				if (($left !== null) && ($right !== null)) {
					if ($right < $categoryDetail["LFT"]) {
						// No relevant categories left
						break;
					}
					if (($left <= $categoryDetail["LFT"]) && ($right >= $categoryDetail["RGT"])) {
						// Check if cache is valid
						$diff = $cacheFileLifeTime;
						if ($categoryDetail["ADCOUNT"] !== null) {
							// Check if cache is valid
							$cacheTime = strtotime($categoryDetail["STAMP"]);
							$diff = ((time()-$cacheTime)/60);
						}
						if (!$forceUpdate && ($diff < $cacheFileLifeTime)) {
							// Return cached value
							$result += $categoryDetail["ADCOUNT"];
						} else {
							// Get article count for the current child category
							if (($categoryDetail["RGT"] - $categoryDetail["LFT"]) == 1) {
								$result += $this->getCacheArticleCountCached($categoryDetail["ID_KAT"], true, ($forceUpdate ? null : $categoryDetail["ADCOUNT"]), $categoryDetail["STAMP"]);
							} else {
								$result += $this->getCacheArticleCount($categoryDetail["ID_KAT"], $arCategoryList, $categoryDetail["LFT"], $categoryDetail["RGT"], $categoryIndex+1, $forceUpdate);
							}
						}
						$offset = $categoryDetail["RGT"];
					}
				} else if ($categoryDetail["ID_KAT"] == $id_kat) {
					$left = $categoryDetail["LFT"];
					$right = $categoryDetail["RGT"];
					if (($right - $left) == 1) {
						// Has no child categories
						return $this->getCacheArticleCountCached($id_kat, true, ($forceUpdate ? null : $categoryDetail["ADCOUNT"]), $categoryDetail["STAMP"]);
					}
					// TODO: Remove when articles are back in regular category structure
					$result += (int)$this->getCacheArticleCountCached($id_kat, true, ($forceUpdate ? null : $categoryDetail["ADCOUNT"]), $categoryDetail["STAMP"]);
				}
			}
			// Update cache file
			$this->setCacheArticleCountCached($id_kat, $result);
			return $result;
    }
	
    private function getCacheArticleCountCached($id_kat, $update = false, $cacheValue = null, $cacheStamp = null) {
			global $db;
			if ($cacheValue !== null) {
				$cacheTime = strtotime($cacheStamp);
				$cacheFileLifeTime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
				$diff = ((time()-$cacheTime)/60);
				if ($diff < $cacheFileLifeTime) {
					// Return cached value
					return $cacheValue;
				}
			}
			if ($update) {
				$result = $db->fetch_atom($q="
					SELECT COUNT(*) FROM (
						SELECT ID_AD_MASTER FROM `ad_master`
						WHERE FK_KAT=".(int)$id_kat." AND (STATUS&3)=1 AND (DELETED=0)
						GROUP BY IFNULL(FK_PRODUCT,ID_AD_MASTER)
					) as ADC");
				$this->setCacheArticleCountCached($id_kat, $result);
				return $result;
			} else {
				return null;
			}	
    }
	
    private function setCacheArticleCountCached($id_kat, $count) {
			global $db;
			$query = "
				INSERT INTO `kat_statistic`
					(ID_KAT, ADCOUNT, STAMP)
				VALUES
					(".(int)$id_kat.", ".$count.", NOW())
				ON DUPLICATE KEY UPDATE
					ADCOUNT=".(int)$count.", STAMP=NOW();";
			$db->querynow($query);
		}
	
		public function updateCacheArticleCount() {
			global $db;
			$arCategoryData = $db->fetch1("
				SELECT k.ID_KAT, k.LFT, k.RGT, k.ROOT, ks.ADCOUNT, ks.STAMP
				FROM `kat` k
				LEFT JOIN `kat_statistic` ks ON ks.ID_KAT=k.ID_KAT
				WHERE k.ROOT=1 AND k.PARENT=1
				ORDER BY ks.STAMP ASC
				LIMIT 1");
			$arCategoryList = null;
			$this->getCacheArticleCount($arCategoryData["ID_KAT"], $arCategoryList, $arCategoryData["LFT"], $arCategoryData["RGT"], 0, true);
		}

    /**
     * Reads cached files for input boxes.
     *
     * @param   int $id_kat
     * @param array $ar_params
     * @return  string|html
     */
    public function getInputFieldsCache($id_kat, $ar_params = array(), $template = false, $idFieldGroup = null, $is_vendor = false)
    {
        global $s_lang, $ab_path, $tpl_main, $tpl_content, $nar_systemsettings, $db, $uid;
        require_once $ab_path."sys/lib.ad_variants.php";
        $adVariantsManagement = AdVariantsManagement::getInstance($db);
        $paymentAdapterUserManagement = PaymentAdapterUserManagement::getInstance($db);
        $adPaymentAdapterManagement = AdPaymentAdapterManagement::getInstance($db);

        $cacheFile = null;

        $q = null;

        if ( !is_array($id_kat) ) {
	        $id_kat = (int)$id_kat;
	        if ( $is_vendor ) {
		        $cacheFile = $ab_path."cache/marktplatz/vendor/inputfields_".$s_lang.".".$id_kat.($idFieldGroup !== null ? ".".$idFieldGroup : "").".htm";
	        }
	        else {
		        $cacheFile = $ab_path."cache/marktplatz/inputfields_".$s_lang.".".$id_kat.($idFieldGroup !== null ? ".".$idFieldGroup : "").".htm";
	        }
	        $q="SELECT f.*
				FROM `kat` k
				LEFT JOIN `table_def` t ON t.T_NAME=k.KAT_TABLE
				LEFT JOIN `field_def` f ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
				WHERE k.ID_KAT=".(int)$id_kat." AND f.FK_FIELD_GROUP".($idFieldGroup === null ? " IS NULL" : "=".(int)$idFieldGroup);
        }
        else {
        	if ( $is_vendor ) {
		        $cacheFile = $ab_path."cache/marktplatz/vendor/inputfields_".$s_lang.".".implode("_",$id_kat).($idFieldGroup !== null ? ".".$idFieldGroup : "").".htm";
	        }
        	else {
		        $cacheFile = $ab_path."cache/marktplatz/inputfields_".$s_lang.".".implode("_",$id_kat).($idFieldGroup !== null ? ".".$idFieldGroup : "").".htm";
	        }
	        $q="SELECT f.*
				FROM `kat` k
				LEFT JOIN `table_def` t ON t.T_NAME=k.KAT_TABLE
				LEFT JOIN `field_def` f ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
				WHERE k.ID_KAT IN (".implode(",",$id_kat).") AND f.FK_FIELD_GROUP".($idFieldGroup === null ? " IS NULL" : "=".(int)$idFieldGroup)."
				GROUP BY f.ID_FIELD_DEF";
        }

        $cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
        $modifyTime = @filemtime($cacheFile);
        $diff = ((time()-$modifyTime)/60);

        if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile))
        {
            $cache = new CategoriesCache();
            $cache->cacheKatFieldsInput($id_kat, $idFieldGroup, $is_vendor);
        }
        $code = @file_get_contents($cacheFile);

        $ar_fieldTypes = $db->fetch_table( $q );
        foreach ($ar_fieldTypes as $index => $ar_field) {
            if (isset($ar_params[$ar_field["F_NAME"]])) {
                if (($ar_field["F_TYP"] == "MULTICHECKBOX") || ($ar_field["F_TYP"] == "MULTICHECKBOX_AND")) {
                    $ar_values = explode("x", trim($ar_params[$ar_field["F_NAME"]], "x"));
                    foreach ($ar_values as $index => $id_value) {
                        $ar_params[$ar_field["F_NAME"]."_CHECK_".$id_value] = 1;
                    }
                }
            }
        }

        if (!empty($ar_params["tmp_listen"])) {
            $input_lists = explode(",", $ar_params["tmp_listen"]);
            foreach($input_lists as $index => $liste) {
                $ar_params[$liste.'_sel_'.$ar_params[$liste]] = "selected";
                unset($ar_params[$liste]);
            }
        }
        // Varianten
        if($adVariantsManagement->isVariantCategory($id_kat)) {
            if (!empty($ar_params["_VARIANTS_FIELDS"])) {
                foreach($ar_params["_VARIANTS_FIELDS"] as $variantFieldName => $variantFieldValues) {
                    if (!empty($variantFieldValues)) {
                        foreach($variantFieldValues as $vkey => $arVariantValue) {
                            $ar_params[$variantFieldName.'_VARIANT_'.$arVariantValue] = 1;
                        }
                    }
                }
            }
        }

        // Payment Adapter
        $paymentAdapers = $paymentAdapterUserManagement->fetchAllAvailablePaymentAdapterByUser($uid);
        $paymentAdaperNames = $adPaymentAdapterManagement->fetchAllPaymentAdapterNamesForAd($ar_params['ID_AD_MASTER']);
        $userDefaultPaymentAdapters = $paymentAdapterUserManagement->fetchAllAutoCheckedPaymentAdapterByUser($uid);

        foreach($paymentAdapers as $key => $paymentAdaper) {
            if(array_key_exists($paymentAdaper['ID_PAYMENT_ADAPTER'], $paymentAdaperNames)) {
                $paymentAdapers[$key]['CHECKED'] = TRUE;
            }
            if($ar_params['ID_AD_MASTER'] == NULL && array_key_exists($paymentAdaper['ID_PAYMENT_ADAPTER'], $userDefaultPaymentAdapters)) {
                $paymentAdapers[$key]['CHECKED'] = TRUE;
            }
        }




        $tpl_content->addlist('AD_PAYMENT_ADAPTER', $paymentAdapers, "tpl/".$s_lang."/my-marktplatz-neu.payment-adapter.row.htm");

        if ($template === FALSE) {
            $tpl = new Template($ab_path."tpl/de/empty.htm");
            $tpl->tpl_text = $code;
            $tpl->addvars(array_merge($tpl_main->vars, $tpl_content->vars, $ar_params));

            return $tpl->process(FALSE);
        } else {
            $tpl = new Template($ab_path.$template);
            $tpl->isTemplateCached = TRUE;
            $tpl->tpl_text = str_replace("{input_fields}", $code, $tpl->tpl_text);
            $tpl->addvars(array_merge($tpl_main->vars, $tpl_content->vars, $ar_params));

            return $tpl->process(FALSE);
        }
    } // getBoxCache()

	/**
	 * Reads cached files for input form.
	 *
	 * @param   int $id_kat
	 * @return  string|html
	 */
	public function getInputFieldsOverviewCache($id_kat)
	{
		global $s_lang, $ab_path, $nar_systemsettings;
		$id_kat = (int)$id_kat;

        $cacheFile = $ab_path."cache/marktplatz/inputfields_overview_".$s_lang.".".$id_kat.".htm";

        $cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
        $modifyTime = @filemtime($cacheFile);
        $diff = ((time()-$modifyTime)/60);

        if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile))
		{
			$cache = new CategoriesCache();
			$cache->cacheKatFieldsInputOverview($id_kat);
		}
		$code = @file_get_contents($cacheFile);
		return $code;
	} // getBoxCache()

    /**
	 * Reads cached files for input boxes.
	 *
	 * @param   int $id_kat
	 * @param array $ar_params
	 * @return  string|html
	 */
	public function getCategoryInputFieldsCache($kat_table, $ar_params = array(), $template = false)
	{
		global $s_lang, $ab_path, $tpl_main, $nar_systemsettings;

        $cacheFile = $ab_path."cache/marktplatz/inputfields_k_".$s_lang.".".$kat_table.".htm";

        $cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
        $modifyTime = @filemtime($cacheFile);
        $diff = ((time()-$modifyTime)/60);

        if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile))
		{
			$cache = new CategoriesCache();
			$cache->cacheKatFieldsInputByKatTable($kat_table);
		}
		$code = @file_get_contents($cacheFile);

		if (!empty($ar_params["tmp_listen"])) {
			$input_lists = explode(",", $ar_params["tmp_listen"]);
			foreach($input_lists as $index => $liste) {
				$ar_params[$liste.'_sel_'.$ar_params[$liste]] = "selected";
				unset($ar_params[$liste]);
			}
		}

		if ($template === false) {
			$tpl = new Template($ab_path."tpl/de/empty.htm");
			$tpl->tpl_text = $code;
			$tpl->addvars($tpl_main->vars);
			$tpl->addvars($ar_params);
			return $tpl->process();
		} else {
			$tpl = new Template($ab_path.$template);
			$tpl->tpl_text = str_replace("{input_fields}", $code, $tpl->tpl_text);
			$tpl->addvars($tpl_main->vars);
			$tpl->addvars($ar_params);
			return $tpl->process();
		}
	}
}

/**
 * Class for caching necessary pages.
 *
 * @package Categories
 * @subpackage Public
 */
class CategoriesCache extends CategoriesBase {

    protected $ar_field_types = array(
        'TEXT' => array('tpl' => 'sbox.text', 'fkt' => NULL),
        'FLOAT' => array('tpl' => 'sbox.text', 'fkt' => NULL),
        'FLOAT_VB' => array('tpl' => 'sbox.text_vb', 'fkt' => NULL),
        'INT' => array('tpl' => 'sbox.text', 'fkt' => NULL),
        'INT_VB' => array('tpl' => 'sbox.text_vb', 'fkt' => NULL),
        'CHECKBOX' => array('tpl' => 'sbox.checkbox', 'fkt' => NULL),
        'LIST' => array('tpl' => 'sbox.liste', 'fkt' => 'listvalues'),
        'VARIANT' => array('tpl' => 'sbox.variant', 'fkt' => 'listvalues'),
        'MULTICHECKBOX' => array('tpl' => 'sbox.multicheckbox', 'fkt' => 'listvalues'),
        'MULTICHECKBOX_AND' => array('tpl' => 'sbox.multicheckbox_and', 'fkt' => 'listvalues'),
        'LONGTEXT' => array('tpl' => 'sbox.text', 'fkt' => NULL),
        'DATE' => array('tpl' => 'sbox.date', 'fkt' => NULL),
        'DATE_VB' => array('tpl' => 'sbox.date_vb', 'fkt' => NULL)
    );

	/**
	 * Create cache-file for the public category List of the given category
	 *
	 * @param   int   $id_kat     ID of the category to cache.
	 */
	public function cacheKatList($id_kat, $hoverChilds = true) {
		global $ab_path, $s_lang, $db, $nar_systemsettings;
		$tpl_cache = new Template('tpl/'.$s_lang.'/cache_marktplatz_list.htm');
		// $kats = $this->kats_read($id_kat);
		//$kats = $this->kats_read_path_with_root($id_kat);

        $childKats = $this->kats_read($id_kat);
        if($childKats == null) {
            $tmp = $db->fetch1("
            	SELECT
            		kp.PARENT as GRANDPARENT,
            		k.PARENT AS PARENT
            	FROM kat k
            	JOIN kat kp
            		ON kp.ID_KAT = k.PARENT
            	WHERE k.ID_KAT = '".(int)$id_kat."'");
			if ($tmp['GRANDPARENT'] > 0) {
				$inPathKatId = $tmp['PARENT'];
				$parentKat = $tmp['GRANDPARENT'];
				$childKats = $this->kats_read($inPathKatId);
			} else {
				$parentKat = $tmp['PARENT'];
				$childKats = array();
			}
        } else {
            $parentKat = $db->fetch_atom("SELECT k.PARENT FROM kat k WHERE k.ID_KAT = '".(int)$id_kat."'");
        }
        $siblingKats = $this->kats_read($parentKat);

        $tpl = '';
        foreach($siblingKats as $key => $siblingKat) {
            $tpl_row = new Template('tpl/'.$s_lang.'/cache_marktplatz_list.row.htm');

			if($siblingKat['ID_KAT'] == 1) {
				$siblingKat['V1'] = $nar_systemsettings['SITE']['SITENAME'];
			}

            if(in_array($siblingKat['ID_KAT'], array($id_kat, $inPathKatId))) {
                $tpl_row->addvar('in_path', 1);
                foreach($childKats as $cKey => $childKat) {
                    $childKats[$cKey]['ID_KAT_SEL'] = $id_kat;
                }

                $childTpl = $this->_processCacheKatTreeTemplate($childKats);
                $tpl_row->addvar('CHILDREN', $childTpl);
            } else if ($hoverChilds) {
            	$childKatsSibling = $this->kats_read($siblingKat['ID_KAT']);
            	if (is_array($childKatsSibling)) {
	                $childTpl = $this->_processCacheKatTreeTemplate($childKatsSibling);
	                $tpl_row->addvar('CHILDREN_HOVER', $childTpl);
            	}
            }

            $tpl_row->addvars($siblingKat);
            $tpl .= $tpl_row->process();
        }

		if (!empty($siblingKats)) {
			//die(ht(dump($kats)));
			$tpl_cache->addvar("ID_KAT_SEL", $id_kat);
			$tpl_cache->addvar('liste', $tpl);
			$filename = $ab_path."cache/marktplatz/liste_".$s_lang.".".$id_kat.".htm";
			file_put_contents($filename, $tpl_cache->process());
			@chmod($filename, 0777);
		}
	}

    /**
     * Create cache-file for the public category Tree of the given category
     *
     * @param   int   $id_kat     ID of the category to cache.
     */
    public function cacheKatTree($id_kat, $childLevels = 1, $hoverChilds = true, $options = [], $parent = []) {
        global $ab_path, $s_lang;
        $kats = $this->kats_read_tree($id_kat, false, $childLevels);
        $tpl_cache = new Template('tpl/'.$s_lang.'/cache_marktplatz_tree.htm');

        if (!empty($kats)) {
            $tpl = $this->_processCacheKatTreeTemplate($kats, $hoverChilds, 0, $options);

            //die(ht(dump($kats)));
            $tpl_cache->addvar("ID_KAT_SEL", $id_kat);
            $tpl_cache->addvar('liste', $tpl);
			$extra = "";
			if (!empty($options)) {
				$extra = ".".md5(json_encode($options));
                $tpl_cache->addvars(array_flatten($options, "both"));
			}
			$filename = $ab_path."cache/marktplatz/tree_".$s_lang.".".$id_kat.".".$childLevels.$extra.".htm";
            file_put_contents($filename, $tpl_cache->process());
            @chmod($filename, 0777);
        }
    }

    /**
     * Create cache-file for the public category Tree of the given category
     *
     * @param   int   $id_kat     ID of the category to cache.
     */
    public function cacheKatBoxes($id_kat, $childLevels = 1, $columnCount = 4, $childColumnCount = 3) {
        global $ab_path, $s_lang;
        $kats = $this->kats_read_tree($id_kat, false, $childLevels);
        $tpl_cache = new Template('tpl/'.$s_lang.'/cache_marktplatz_boxes.htm');

        if (!empty($kats)) {
            $tpl = $this->_processCacheKatBoxesTemplate($kats, true, $columnCount, $childColumnCount);
            $tpl_cache->addvar('liste', $tpl);
			if (is_array($columnCount)) {
				$tpl_cache->addvars($columnCount, "COLUMN_COUNT_");
			} else {
				$tpl_cache->addvar("COLUMN_COUNT_XS", $columnCount);
				$tpl_cache->addvar("COLUMN_COUNT_SM", $columnCount);
				$tpl_cache->addvar("COLUMN_COUNT_MD", $columnCount);
				$tpl_cache->addvar("COLUMN_COUNT_LG", $columnCount);
			}
            $filename = $ab_path."cache/marktplatz/boxes_".$s_lang.".".$id_kat.".".$childLevels.".htm";
            file_put_contents($filename, $tpl_cache->process());
            @chmod($filename, 0777);
        }
    }

	public function cacheCategoryHashMap($cacheFile) {
		global $db, $langval;

		$hashmap = array('ID' => array(), 'PATH' => array());

		$kats = $db->fetch_table("
			SELECT
				el.*, s.T1, s.V1, s.V2
			FROM `kat` el
			LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=el.ID_KAT AND s.BF_LANG=if(el.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
		    WHERE
		    	ROOT=1  ORDER BY `ORDER_FIELD`
		");

		foreach($kats as $key => $kat) {
			$hashmap['ID'][$kat['ID_KAT']] = $kat;

		}

		file_put_contents($cacheFile, serialize($hashmap));
	}

	private function _processCacheKatTreeTemplate($kats, $hoverChilds = true, $childLevel = 0, $options = [], $parent = []) {
		global $ab_path, $s_lang;

        $tpl = '';
		$limit = null;
        if (array_key_exists("LIMIT_CHILDS", $options)) {
			if (is_array($options["LIMIT_CHILDS"])) {
				$limit = $options["LIMIT_CHILDS"][$childLevel];
			} else {
                $limit = $options["LIMIT_CHILDS"];
			}
        }
        foreach($kats as $key => $kat) {
            if (($limit !== null) && ($limit-- <= 0)) {
                break;
            }
            $tpl_row = new Template('tpl/'.$s_lang.'/cache_marktplatz_tree.row.htm');
            $kat['level'] = $childLevel;
            if($kat['CHILDREN']) {
                $childrenTpl = $this->_processCacheKatTreeTemplate($kat['CHILDREN'], true, $childLevel+1, $options, $kat);
                if ($hoverChilds) {
                    $kat['CHILDREN_HOVER'] = $childrenTpl;
                    $kat['CHILDREN'] = false;
                } else {
                    $kat['CHILDREN_HOVER'] = false;
                    $kat['CHILDREN'] = $childrenTpl;
                }
            }
            $tpl_row->addvars($kat);
            $tpl_row->addvars($parent, "PARENT_");
            $tpl_row->addvars(array_flatten($options, "both"));
            if (($limit !== null) && ($limit <= 0) && array_key_exists($key+1, $kats)) {
                $tpl_row->addvar("LIMIT_CHILDS_LAST", 1);
            }
            $tpl .= $tpl_row->process();
        }
        return $tpl;
    }

    private function _processCacheKatBoxesTemplate($kats, $primary, $columnCount, $childColumnCount) {
        global $ab_path, $s_lang;

        $tpl = '';
        $index = 0;
        foreach($kats as $key => $kat) {
            $tpl_row = new Template('tpl/'.$s_lang.'/cache_marktplatz_boxes.row.htm');
            $kat["COLUMN_COUNT"] = $columnCount;
            $kat["CHILD_COLUMN_COUNT"] = $childColumnCount;
            $kat["KAT_INDEX"] = $index;
            $kat["KAT_PRIMARY"] = ($primary ? 1 : 0);
            if($kat['CHILDREN']) {
                $childrenTpl = $this->_processCacheKatBoxesTemplate($kat['CHILDREN'], false, $childColumnCount, $childColumnCount);
				$kat['CHILDREN_COUNT'] = count($kat['CHILDREN']);
                $kat['CHILDREN'] = $childrenTpl;
            } else {
				$kat['CHILDREN'] = "";
			}
            $tpl_row->addvars($kat);
			if (is_array($columnCount)) {
				$tpl_row->addvars($columnCount, "COLUMN_COUNT_");
			} else {
                $tpl_row->addvar("COLUMN_COUNT_XS", $columnCount);
                $tpl_row->addvar("COLUMN_COUNT_SM", $columnCount);
                $tpl_row->addvar("COLUMN_COUNT_MD", $columnCount);
                $tpl_row->addvar("COLUMN_COUNT_LG", $columnCount);
			}
			if (is_array($childColumnCount)) {
				$tpl_row->addvars($childColumnCount, "CHILD_COLUMN_COUNT_");
			} else {
                $tpl_row->addvar("CHILD_COLUMN_COUNT_XS", $childColumnCount);
                $tpl_row->addvar("CHILD_COLUMN_COUNT_SM", $childColumnCount);
                $tpl_row->addvar("CHILD_COLUMN_COUNT_MD", $childColumnCount);
                $tpl_row->addvar("CHILD_COLUMN_COUNT_LG", $childColumnCount);
			}
            $tpl .= $tpl_row->process(false);
            $index++;
        }
        return $tpl;
    }

    /**
     * Create cache-file for the category path of the given category
     *
     * @param   int   $id_kat     ID of the category to cache.
     */
    public function cacheKatAriane($id_kat) {
        global $ab_path, $s_lang, $db;
        $tpl_cache = new Template('tpl/'.$s_lang.'/cache_marktplatz_path.htm');
        $kats = $this->kats_read_path($id_kat);
        if (!empty($kats)) {
            $tpl_cache->addvar('KAT_COUNT', count($kats)-1);
            $tpl_cache->addlist('liste', $kats, 'tpl/'.$s_lang.'/cache_marktplatz_path.row.htm');
        } else {
            $tpl_cache->addvar('KAT_COUNT', -1);
        }
        $filename = $ab_path."cache/marktplatz/ariane_".$s_lang.".".$id_kat.".htm";
        file_put_contents($filename, $tpl_cache->process());
        @chmod($filename, 0777);
    }

    /**
     * Create cache-file for the category path of the given category
     *
     * @param   int   $id_kat     ID of the category to cache.
     */
    public function cacheKatArianeText($id_kat, $langId = null) {
        global $ab_path, $db;
        // Get language
        $s_lang = $GLOBALS['s_lang'];
        $langval = $GLOBALS['langval'];
        if ($langId !== null) {
            foreach ($GLOBALS['lang_list'] as $langIndex => $langDetails) {
                if ($langDetails['ID_LANG'] == $langId) {
                    $s_lang = $langDetails["ABBR"];
                    $langval = $langDetails["BITVAL"];
                    break;
                }
            }
        }
        // Get categories
        $kats = $this->kats_read_path($id_kat, $langval);
        $arKatNames = array();
        foreach ($kats as $katIndex => $arKat) {
            $arKatNames[] = $arKat["V1"];
        }
        $filename = $ab_path."cache/marktplatz/ariane_".$s_lang.".".$id_kat.".txt";
        file_put_contents($filename, implode("|", $arKatNames));
        @chmod($filename, 0777);
    }

	/**
	 * Create cache-file for public
	 *
	 * @param   int   $id_kat     ID of the category to cache.
	 */
	public function cacheKatFields($id_kat) {
		global $db, $langval, $s_lang, $ab_path;
		#echo $id_kat."<br />";
		$table = $db->fetch_atom("
        SELECT
          table_def.ID_TABLE_DEF
        FROM
          kat
        LEFT JOIN
          table_def on kat.KAT_TABLE=table_def.T_NAME
        WHERE
          kat.ID_KAT = ".$id_kat);
		if($table)
		{
			$res = $db->querynow("
          SELECT
            t.*, s.V1, s.V2, s.T1
          FROM
            kat2field kf
          LEFT JOIN
            `field_def` t  ON kf.FK_FIELD=t.ID_FIELD_DEF
          left join
            string_field_def s on s.S_TABLE='field_def' and s.FK=t.ID_FIELD_DEF
            and s.BF_LANG=if(t.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_DEF+0.5)/log(2)))
          WHERE
            kf.FK_KAT=".$id_kat."
            AND kf.B_SEARCHFIELD = 1 AND kf.B_ENABLED=1 AND t.B_ENABLED=1
          ORDER BY t.F_ORDER ASC");
			#echo ht(dump($res));
			$ar=array();
			while($row = mysql_fetch_assoc($res['rsrc']))
			{
				#echo ht(dump($row));
				// Spezialfälle
				if($row['IS_SPECIAL'])
				{

				}
				else
				{
					switch($row['F_TYP'])
					{
						case "LIST":
						case "VARIANT":
						case "MULTICHECKBOX":
						case "MULTICHECKBOX_AND":
							$res_liste = $db->querynow("
								select
									t.*, s.V1, s.V2, s.T1
								from
									`liste_values` t
								left join
									string_liste_values s
									on s.S_TABLE='liste_values'
									and s.FK=t.ID_LISTE_VALUES
									and s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & " . $langval . ", " . $langval . ", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
								where
									t.FK_LISTE=" . $row['FK_LISTE'] . "
								ORDER BY t.ORDER ASC
							");

							if($row['F_TYP'] == 'LIST') {
								$tmp = array();
								$tmp[] = '<select name="'.$row['F_NAME'].'" class="input-medium" onchange="presearch();">';
								$tmp[] = '<option value="0">...</option>';

								while($row_liste = mysql_fetch_assoc($res_liste['rsrc'])) {
									  $tmp[] = '<option value="'.$row_liste['ID_LISTE_VALUES'].'" '.
										'^if '.$row['F_NAME'].'=='.$row_liste['ID_LISTE_VALUES'].'°selected="selected" ^endif°>'.
										stdHtmlentities($row_liste['V1']).'</option>';
								}
								$tmp[] = "</select>";
								$special = implode("\n", $tmp);
							} elseif (($row['F_TYP'] == 'VARIANT') || ($row['F_TYP'] == 'MULTICHECKBOX') || ($row['F_TYP'] == 'MULTICHECKBOX_AND')) {
								$tmp = array();

								while($row_liste = mysql_fetch_assoc($res_liste['rsrc'])) {
									$is_selected = '^if ISSET_'.$row['F_NAME'].'_'.$row_liste['ID_LISTE_VALUES'].'° checked="checked" ^endif°';
									$tmp[] = '<label class="checkbox inline"><input type="checkbox" name="'.$row['F_NAME'].'[]" value="'.$row_liste['ID_LISTE_VALUES'].'" onchange="presearch();" '.$is_selected.'>'.stdHtmlentities($row_liste['V1']).'</label><br>';
								}
								$special = implode("\n", $tmp);
							}

							break;
						default: $special = NULL;
					}
					// Standardfall
					$hack_search = explode("§§§", $row['T1']);
					$hack = explode("||", $hack_search[1]);
					$row['help_text1'] = $hack[0];
					$row['help_text2'] = $hack[1];
					if($row['B_SEARCH'] == 2)
					{
						$feld = $this->ar_field_types[$row['F_TYP'].'_VB'];
					}
					else
					{
						$feld = $this->ar_field_types[$row['F_TYP']];
					}
					#die(ht(dump($row)));
					$file_template = CacheTemplate::getHeadFile("tpl/".$s_lang."/cache_".$feld['tpl'].".htm");
					if (file_exists($file_template)) {
						$tmp = new Template($file_template);
						$tmp->addvars($row);
						if($special) {
							$tmp->addvar("SPECIAL", $special);
						}
						$tmp = $tmp->process();
						$tmp = str_replace(array('^','°'), array('{','}'), $tmp);
						$ar[] = $tmp;
					} else {
						$details = 	"Typ: ".$row['F_TYP']."\n".
									"Template: ".$feld['tpl']."\n".
									"";
						eventlog("error", "Suchboxen - Template nicht gefunden! (".$file_template.")", $details);
					}
				} // no special
			}

			### create cache file
			$filename = $ab_path."cache/marktplatz/sbox.".$id_kat.".".$s_lang.".htm";
			file_put_contents($filename, implode("\n", $ar));
			@chmod($filename, 0777);
		}
		#echo ht(dump($GLOBALS['lastresult']));
	}

    /**
	 * Check if the given category(s) have fields defined
     * @param int|int[] $id_kat
     * @return bool
     */
	public function hasKatFields($id_kat) {
		$id_kat_sql = [];
        if ( is_array($id_kat) ) {
            foreach ($id_kat as $id_kat_cur) {
                $id_kat_sql[] = (int)$id_kat_cur;
        	}
		} else {
        	$id_kat_sql[] = (int)$id_kat;
        }
        if (empty($id_kat_sql)) {
        	return false;
		}
        $q = "
			SELECT COUNT(*) 
			FROM `kat2field` 
			WHERE FK_KAT IN (".implode(",",$id_kat_sql).")
				AND B_ENABLED=1";
        return ($GLOBALS["db"]->fetch_atom($q) > 0);
	}

	/**
	 * Create cache-file for input fields
	 *
	 * @param   int   $id_kat     ID of the category to cache.
	 */
	public function cacheKatFieldsInput($id_kat, $idFieldGroup = null, $is_vendor = false) {
		global $db, $langval, $s_lang, $ab_path;

		if (!$id_kat) return;
        require_once $ab_path."sys/lib.translation.php";
        $labelGeneral = Translation::readTranslation('marketplace', 'ad.fields.general', $s_lang, array(), "Allgemeine Informationen");
        $labelGeneralDesc = Translation::readTranslation('marketplace', 'ad.fields.general.desc', $s_lang, array(), "");
		// Article exists
		$q = null;
		if ( is_array($id_kat) ) {
			$q = "SELECT DISTINCT KAT_TABLE FROM `kat` WHERE ID_KAT IN (".implode(",",$id_kat).")";
		}
		else {
			$q = "SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat;
		}
		$kat_table = $db->fetch_atom( $q );
		$q = null;
		if ( is_array($id_kat) ) {
			$q = "
				SELECT
					f.F_TYP, f.FK_LISTE, f.F_NAME, sf.V1, sf.V2, sf.T1, f.IS_SPECIAL,
					IFNULL(kf.B_NEEDED,f.B_NEEDED) as B_NEEDED,
					f.FK_FIELD_GROUP,
					IFNULL(sg.V1, '".mysql_real_escape_string($labelGeneral)."') AS FIELD_GROUP,
					IFNULL(sg.V2, '".mysql_real_escape_string($labelGeneralDesc)."') AS FIELD_GROUP_DESC
				FROM `table_def` t
					LEFT JOIN `field_def` f  ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
					LEFT JOIN `kat2field` kf ON kf.FK_FIELD=f.ID_FIELD_DEF AND kf.FK_KAT IN (".implode(",",$id_kat).")
					LEFT JOIN `string_field_def` sf ON sf.S_TABLE='field_def' AND sf.FK=f.ID_FIELD_DEF
						AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
					LEFT JOIN `field_group` g ON f.FK_FIELD_GROUP=g.ID_FIELD_GROUP
					LEFT JOIN `string_app` sg on sg.S_TABLE='field_group' and sg.FK=g.ID_FIELD_GROUP
							and sg.BF_LANG=if(g.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_APP+0.5)/log(2)))
				WHERE t.T_NAME='".$kat_table."' AND kf.B_ENABLED=1 AND f.B_ENABLED=1 AND f.FK_FIELD_GROUP".($idFieldGroup === null ? " IS NULL" : "=".(int)$idFieldGroup)."
				GROUP BY f.ID_FIELD_DEF
				ORDER BY g.F_ORDER ASC, f.FK_FIELD_GROUP ASC, f.F_ORDER ASC";
		}
		else {
			$q = "
				SELECT
					f.F_TYP, f.FK_LISTE, f.F_NAME, sf.V1, sf.V2, sf.T1, f.IS_SPECIAL,
					IFNULL(kf.B_NEEDED,f.B_NEEDED) as B_NEEDED,
					f.FK_FIELD_GROUP,
					IFNULL(sg.V1, '".mysql_real_escape_string($labelGeneral)."') AS FIELD_GROUP,
					IFNULL(sg.V2, '".mysql_real_escape_string($labelGeneralDesc)."') AS FIELD_GROUP_DESC
				FROM `table_def` t
					LEFT JOIN `field_def` f  ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
					LEFT JOIN `kat2field` kf ON kf.FK_FIELD=f.ID_FIELD_DEF AND kf.FK_KAT=".$id_kat."
					LEFT JOIN `string_field_def` sf ON sf.S_TABLE='field_def' AND sf.FK=f.ID_FIELD_DEF
						AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
					LEFT JOIN `field_group` g ON f.FK_FIELD_GROUP=g.ID_FIELD_GROUP
					LEFT JOIN `string_app` sg on sg.S_TABLE='field_group' and sg.FK=g.ID_FIELD_GROUP
							and sg.BF_LANG=if(g.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_APP+0.5)/log(2)))
				WHERE t.T_NAME='".$kat_table."' AND kf.B_ENABLED=1 AND f.B_ENABLED=1 AND f.FK_FIELD_GROUP".($idFieldGroup === null ? " IS NULL" : "=".(int)$idFieldGroup)."
				ORDER BY g.F_ORDER ASC, f.FK_FIELD_GROUP ASC, f.F_ORDER ASC";
		}
		$field_data = $db->fetch_table( $q );
		/*
		 * Old query
		 */
		/*
		 $field_data = $db->fetch_table("SELECT
		 f.F_TYP, f.FK_LISTE, kf.B_NEEDED, f.F_NAME, sf.V1, sf.V2, sf.T1, f.IS_SPECIAL
		 FROM `kat2field` kf
		 LEFT JOIN `field_def` f ON f.ID_FIELD_DEF = kf.FK_FIELD
		 LEFT JOIN `string_field_def` sf ON sf.S_TABLE='field_def' AND sf.FK=kf.FK_FIELD
		 AND sf.BF_LANG=if(sf.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
		 WHERE kf.FK_KAT=".$id_kat." AND kf.B_ENABLED=1
		 ORDER BY f.F_ORDER ASC");
		 */
		$id_group = false;
		$fields_html = array();
		$fields_lists = array();
		$fields_needed = array();
		$fields_optional = array();
		$i = 0;

        $inputFieldsParams = new Api_Entities_EventParamContainer(array(
            "idCategory"    => $id_kat,
            "idFieldGroup"  => $idFieldGroup,
            "fields"        => $field_data
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent( Api_TraderApiEvents::MARKETPLACE_AD_CREATE_INPUT_FIELDS, $inputFieldsParams );
        if ($inputFieldsParams->isDirty()) {
            $field_data = $inputFieldsParams->getParam("fields");
        }

		for ($index = 0; $index < count($field_data); $index++) {
			if (!$field_data[$index]["IS_SPECIAL"]) {
				$text_split = explode("§§§", $field_data[$index]["T1"]);
				$text_split2 = explode("||", $text_split[0]);
				$fields_html[$i] = array(
		            "field_type" => 1,
		            "field_liste" => $field_data[$index]["FK_LISTE"],
		            "field_needed" => $field_data[$index]["B_NEEDED"],
		            "field_field" => $field_data[$index]["F_NAME"],
		            "field_name" => $field_data[$index]["V1"],
		            "field_unit" => $field_data[$index]["V2"],
		            "field_desc_p1" => $text_split2[0],
		            "field_desc_p2" => $text_split2[1],
		            "field_group" => $field_data[$index]["FIELD_GROUP"],
		            "field_group_desc" => $field_data[$index]["FIELD_GROUP_DESC"]
				);
				if ($field_data[$index]["FK_FIELD_GROUP"] !== $id_group) {
					$id_group = $field_data[$index]["FK_FIELD_GROUP"];
					$fields_html[$i]["group_new"] = 1;
					$fields_html[$i]["group_id"] = $id_group;
				}
				$fields_html[$i]["field_type_".$field_data[$index]["F_TYP"]] = 1;
				switch(strtoupper($field_data[$index]["F_TYP"])) {
                    default:
                        $fields_html[$i]["field_type"] = 0;
                        break;
					case "TEXT":
						$fields_html[$i]["field_type"] = 2;
						break;
					case "INT":
						$fields_html[$i]["field_type"] = 3;
						break;
					case "FLOAT":
						$fields_html[$i]["field_type"] = 4;
						break;
					case "DATE":
						$fields_html[$i]["field_type"] = 5;
						break;
					case "LIST":
						$fields_html[$i]["field_type"] = 6;
						$fields_lists[] = $field_data[$index]["F_NAME"];
						break;
					case "CHECKBOX":
						$fields_html[$i]["field_type"] = 7;
						$fields_lists[] = $field_data[$index]["F_NAME"];
						break;
					case "LONGTEXT":
						$fields_html[$i]["field_type"] = 8;
						break;
					case "VARIANT":
						$fields_html[$i]["field_type"] = 9;
						$fields_lists[] = $field_data[$index]["F_NAME"];
						break;
                    case "MULTICHECKBOX":
                        $fields_html[$i]["field_type"] = 10;
                        $fields_lists[] = $field_data[$index]["F_NAME"];
                        break;;
                    case "MULTICHECKBOX_AND":
                        $fields_html[$i]["field_type"] = 11;
                        $fields_lists[] = $field_data[$index]["F_NAME"];
                        break;
				}
				$i++;
			}
			if ($field_data[$index]["B_NEEDED"]) {
				$fields_needed[] = $field_data[$index]["F_NAME"];
			} else {
				$fields_optional[] = $field_data[$index]["F_NAME"];
			}
		}
		/*
		 * Eingabefelder cachen
		 */
		$tpl_cache = new Template($ab_path.'cache/design/tpl/'.$s_lang.'/my-marktplatz-neu.fields.htm');
		if (!empty($fields_html)) {
			$tpl_cache->addvar('FIELD_COUNT', count($fields_html)-1);
			$tpl_cache->addlist('liste', $fields_html, $ab_path.'cache/design/tpl/'.$s_lang.'/my-marktplatz-neu.fields.row.htm', 'cb_field_input');
			//echo ht(dump($tpl_cache->tpl_text));
			//die();
		} else {
			$tpl_cache->addvar('FIELD_COUNT', -1);
		}
		$tpl_cache->addvar('FIELD_LISTS', implode(",",$fields_lists));
		$tpl_cache->addvar('FIELDS_NEEDED', implode(",",$fields_needed));
		$tpl_cache->addvar('FIELDS_OPTIONAL', implode(",",$fields_optional));
		$tmp = $tpl_cache->process();
		$tmp = preg_replace("/(<date>)(.*?)(<\/date>)/si", "{datedrop($2,-100..-0)}", $tmp);
		$tmp = str_replace(array('^','°'), array('{','}'), $tmp);
		$filename = null;
		if ( is_array($id_kat) ) {
			if ( $is_vendor ) {
				$cachedir = $ab_path."cache/marktplatz/vendor";
				if (!is_dir($cachedir)) {
					mkdir($cachedir,0777,true);
				}
				$filename = $cachedir . "/inputfields_".$s_lang.".".implode("_",$id_kat).($idFieldGroup !== null ? ".".$idFieldGroup : "").".htm";
			}
			else {
				$filename = $ab_path."cache/marktplatz/inputfields_".$s_lang.".".implode("_",$id_kat).($idFieldGroup !== null ? ".".$idFieldGroup : "").".htm";
			}
		}
		else {
			if ( $is_vendor ) {
				$cachedir = $ab_path."cache/marktplatz/vendor";
				if (!is_dir($cachedir)) {
					mkdir($cachedir,0777,true);
				}
				$filename = $cachedir."/inputfields_".$s_lang.".".$id_kat.($idFieldGroup !== null ? ".".$idFieldGroup : "").".htm";
			}
			else {
				$filename = $ab_path."cache/marktplatz/inputfields_".$s_lang.".".$id_kat.($idFieldGroup !== null ? ".".$idFieldGroup : "").".htm";
			}
		}
		file_put_contents($filename, $tmp);
		@chmod($filename, 0777);
	}

	/**
	 * Create cache-file for category overview
	 *
	 * @param   int   $id_kat     ID of the category to cache.
	 */
	public function cacheKatFieldsInputOverview($id_kat) {
		global $db, $langval, $s_lang, $ab_path;

		if (!$id_kat) return;
		// Article exists
		$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
		$field_data = $db->fetch_table("
      	SELECT
      		f.F_TYP, f.FK_LISTE, f.F_NAME, sf.V1, sf.V2, sf.T1, f.IS_SPECIAL,
      		IFNULL(kf.B_NEEDED,f.B_NEEDED) as B_NEEDED,
			f.FK_FIELD_GROUP,
			sg.V1 AS FIELD_GROUP,
			sg.V2 AS FIELD_GROUP_DESC
      	FROM `table_def` t
      		LEFT JOIN `field_def` f  ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
      		LEFT JOIN `kat2field` kf ON kf.FK_FIELD=f.ID_FIELD_DEF AND kf.FK_KAT=".$id_kat."
      		LEFT JOIN `string_field_def` sf ON sf.S_TABLE='field_def' AND sf.FK=f.ID_FIELD_DEF
            	AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
			LEFT JOIN `field_group` g ON f.FK_FIELD_GROUP=g.ID_FIELD_GROUP
			LEFT JOIN `string_app` sg on sg.S_TABLE='field_group' and sg.FK=g.ID_FIELD_GROUP
					and sg.BF_LANG=if(g.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_APP+0.5)/log(2)))
		WHERE t.T_NAME='".$kat_table."' AND kf.B_ENABLED=1 AND f.B_ENABLED=1
        	ORDER BY
				g.F_ORDER ASC, f.FK_FIELD_GROUP ASC, f.F_ORDER ASC");
		$id_group = null;
		$fields_html = array();
		$i = 0;
		for ($index = 0; $index < count($field_data); $index++) {
			if (!$field_data[$index]["IS_SPECIAL"]) {
				$text_split = explode("§§§", $field_data[$index]["T1"]);
				$text_split2 = explode("||", $text_split[0]);
				$fields_html[$i] = array(
		            "field_type" => 1,
		            "field_liste" => $field_data[$index]["FK_LISTE"],
		            "field_needed" => $field_data[$index]["B_NEEDED"],
		            "field_field" => $field_data[$index]["F_NAME"],
		            "field_name" => $field_data[$index]["V1"],
		            "field_unit" => $field_data[$index]["V2"],
		            "field_desc_p1" => $text_split2[0],
		            "field_desc_p2" => $text_split2[1],
		            "field_group" => $field_data[$index]["FIELD_GROUP"],
		            "field_group_desc" => $field_data[$index]["FIELD_GROUP_DESC"]
				);
				if ($field_data[$index]["FK_FIELD_GROUP"] != $id_group) {
					$id_group = $field_data[$index]["FK_FIELD_GROUP"];
					$fields_html[$i]["group_new"] = 1;
				}
				switch(strtoupper($field_data[$index]["F_TYP"])) {
					case "TEXT":
						$fields_html[$i]["field_type"] = 2;
						break;
					case "INT":
						$fields_html[$i]["field_type"] = 3;
						break;
					case "FLOAT":
						$fields_html[$i]["field_type"] = 4;
						break;
					case "DATE":
						$fields_html[$i]["field_type"] = 5;
						break;
					case "LIST":
						$fields_html[$i]["field_type"] = 6;
						break;
					case "CHECKBOX":
						$fields_html[$i]["field_type"] = 7;
						break;
                    case "MULTICHECKBOX":
                        $fields_html[$i]["field_type"] = 10;
                        break;
                    case "MULTICHECKBOX_AND":
                        $fields_html[$i]["field_type"] = 11;
                        break;
					case "VARIANT":
						$fields_html[$i]["field_type"] = 9;
						break;
				}
				$i++;
			}
		}
		/*
		 * Übersicht der Felder cachen
		 */
		$tpl_cache = new Template($ab_path.'cache/design/tpl/'.$s_lang.'/cache_marktplatz_fields_overview.htm');
		if (!empty($fields_html)) {
			$tpl_cache->addvar('FIELD_COUNT', count($fields_html));
			$tpl_cache->addlist('liste', $fields_html, $ab_path.'cache/design/tpl/'.$s_lang.'/cache_marktplatz_fields_overview.row.htm');
		} else {
			$tpl_cache->addvar('FIELD_COUNT', -1);
		}
		$tmp = $tpl_cache->process();
		$filename = $ab_path."cache/marktplatz/inputfields_overview_".$s_lang.".".$id_kat.".htm";
		file_put_contents($filename, $tmp);
		@chmod($filename, 0777);
	}

	/**
	 * Create cache-file for category overview
	 *
	 * @param   int   $id_kat     ID of the category to cache.
	 */
	public function cacheKatOverview($id_kat) {
	}

    public function cacheKatFieldsInputByKatTable($kat_table) {
		global $db, $langval, $s_lang, $ab_path;

		$field_data = $db->fetch_table("
      	SELECT
      		f.F_TYP, f.FK_LISTE, f.F_NAME, sf.V1, sf.V2, sf.T1, f.IS_SPECIAL,
      		IFNULL(kf.B_NEEDED,f.B_NEEDED) as B_NEEDED
      	FROM `table_def` t
      		LEFT JOIN `field_def` f  ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
      		LEFT JOIN `kat2field` kf ON kf.FK_FIELD=f.ID_FIELD_DEF
      		LEFT JOIN `string_field_def` sf ON sf.S_TABLE='field_def' AND sf.FK=f.ID_FIELD_DEF
            	AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
		WHERE t.T_NAME='".$kat_table."' AND kf.B_ENABLED=1 AND f.B_ENABLED=1
		GROUP BY f.F_NAME
        	ORDER BY f.F_ORDER ASC");

		$fields_html = array();
		$fields_lists = array();
		$fields_needed = array();
		$fields_optional = array();
		$i = 0;
		for ($index = 0; $index < count($field_data); $index++) {
			if (!$field_data[$index]["IS_SPECIAL"]) {
				$text_split = explode("§§§", $field_data[$index]["T1"]);
				$text_split2 = explode("||", $text_split[0]);
				$fields_html[$i] = array(
					"field_type" => 1,
					"field_liste" => $field_data[$index]["FK_LISTE"],
					"field_needed" => $field_data[$index]["B_NEEDED"],
					"field_field" => $field_data[$index]["F_NAME"],
					"field_name" => $field_data[$index]["V1"],
					"field_unit" => $field_data[$index]["V2"],
					"field_desc_p1" => $text_split2[0],
					"field_desc_p2" => $text_split2[1]
				);
				switch(strtoupper($field_data[$index]["F_TYP"])) {
					case "TEXT":
						$fields_html[$i]["field_type"] = 2;
						break;
					case "INT":
						$fields_html[$i]["field_type"] = 3;
						break;
					case "FLOAT":
						$fields_html[$i]["field_type"] = 4;
						break;
					case "DATE":
						$fields_html[$i]["field_type"] = 5;
						break;
					case "LIST":
						$fields_html[$i]["field_type"] = 6;
						$fields_lists[] = $field_data[$index]["F_NAME"];
						break;
					case "CHECKBOX":
						$fields_html[$i]["field_type"] = 7;
						$fields_lists[] = $field_data[$index]["F_NAME"];
						break;
                    case "MULTICHECKBOX":
                        $fields_html[$i]["field_type"] = 10;
                        break;
                    case "MULTICHECKBOX_AND":
                        $fields_html[$i]["field_type"] = 10;
                        break;
					case "VARIANT":
						$fields_html[$i]["field_type"] = 9;
						break;
				}
				$i++;
			}
			if ($field_data[$index]["B_NEEDED"]) {
				$fields_needed[] = $field_data[$index]["F_NAME"];
			} else {
				$fields_optional[] = $field_data[$index]["F_NAME"];
			}
		}
		$tpl_cache = new Template($ab_path.'cache/design/tpl/'.$s_lang.'/my-marktplatz-neu.fields.htm');
		if (!empty($fields_html)) {
			$tpl_cache->addvar('FIELD_COUNT', count($fields_html)-1);
			$tpl_cache->addlist('liste', $fields_html, $ab_path.'cache/design/tpl/'.$s_lang.'/my-marktplatz-neu.fields.row.htm', 'cb_field_input');
			//echo ht(dump($tpl_cache->tpl_text));
			//die();
		} else {
			$tpl_cache->addvar('FIELD_COUNT', -1);
		}
		$tpl_cache->addvar('FIELD_LISTS', implode(",",$fields_lists));
		$tpl_cache->addvar('FIELDS_NEEDED', implode(",",$fields_needed));
		$tpl_cache->addvar('FIELDS_OPTIONAL', implode(",",$fields_optional));
		$tmp = $tpl_cache->process();
		$tmp = preg_replace("/(<date>)(.*?)(<\/date>)/si", "{datedrop($2,-100..-0)}", $tmp);
		$tmp = str_replace(array('^','°'), array('{','}'), $tmp);
		$filename = $ab_path."cache/marktplatz/inputfields_k_".$s_lang.".".$kat_table.".htm";
		file_put_contents($filename, $tmp);
		@chmod($filename, 0777);
	}

	public function cacheKatSelectOptions($id_kat = 1, $userFilter = false) {
		global $nar_systemsettings, $ab_path, $db, $s_lang, $langval;

		require_once $GLOBALS["ab_path"]."sys/lib.shop_kategorien.php";
        $cacheIdent = $id_kat.".".$s_lang.($userFilter !== false ? ".u".(int)$userFilter : "");
		$cacheFile = $ab_path."cache/marktplatz/kat_select_options.".$cacheIdent.".htm";

		$cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
		$modifyTime = @filemtime($cacheFile);
		$diff = ((time()-$modifyTime)/60);

		if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile)) {

            $categories = array();

            if ($userFilter !== false) {
                $arKats = array_keys($db->fetch_nar("SELECT FK_KAT FROM `ad_master` WHERE (STATUS&3)=1 AND (DELETED=0) AND FK_USER=".(int)$userFilter." GROUP BY FK_KAT"));
                if (!empty($arKats)) {
                    $categories = $db->fetch_table($query ="
                        SELECT
                            s.V1,
                            k.*
                        FROM `kat` k
                        JOIN `kat` k2 ON (k.LFT <= k2.LFT) AND (k.RGT >= k2.RGT) AND (k.ROOT = k2.ROOT)
                        LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=k.ID_KAT
                          AND s.BF_LANG=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
                        WHERE
                          k2.ID_KAT IN (".implode(", ", $arKats).") AND k.LEVEL>0
                        GROUP BY k.ID_KAT
                        ORDER BY k.LFT");
                }
            } else {
                $kat = new TreeCategories("kat", 1);
                $rootKat = $kat->element_read($id_kat);

                $categories = $db->fetch_table($query ="
				SELECT
					k.*,
					s.V1
				FROM `kat` k
				LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=k.ID_KAT
				  AND s.BF_LANG=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
				WHERE
				  (LFT >= ".$rootKat["LFT"].") AND
				  (RGT <= ".$rootKat["RGT"].") AND
				  (ROOT = ".$rootKat["ROOT"].") AND
				  ID_KAT != 1
				ORDER BY LFT");
            }

            foreach($categories as $key => $category) {
                $categories[$key]['PREFIX'] = str_repeat('-', ($category['LEVEL']-1)*2);
                $categories[$key]['SELECTED'] = ($category['ID_KAT'] == $_REQUEST['FK_KAT']);
            }
            $tmp = new Template("tpl/".$s_lang."/empty.htm");
            $tmp->tpl_text = '{marketplace_categories}';
			$tmp->addlist('marketplace_categories', $categories, 'tpl/'.$s_lang.'/my-marktplatz.row_kats.htm');
			$tmp->isTemplateRecursiveParsable = TRUE;
			file_put_contents($cacheFile, $tmp->process());
		}

		return file_get_contents($cacheFile);

	}

}

/**
 * Class for handling articles
 *
 * @package Categories
 * @subpackage Public
 */
class CategoriesArticle extends CategoriesBase implements Billing {
	/**
	 * Check $data for missing required inputs and such.
	 *
	 * @param   array   $data     An assoc. array with the input to validate.
	 *
	 * @return  bool    True if the given data is okay, false otherwise.
	 */
	public function validateArticle($data) {
		return true;
	}

	/**
	 * Updates or inserts an article. If $id_art is 0 a new article will be inserted.
	 *
	 * @param   array   $data     An assoc. array with the article data to submit.
	 * @param   int     $id_art   ID of the article to update.
	 */
	public function updateArticle($data, $id_art = 0) {
	}

	/**
	 * Deletes an article from database.
	 *
	 * @param   int     $id_art   ID of the article to delete.
	 */
	public function deleteArticle($id_art) {
	}

	/**
	 * Activates an inactive article.
	 *
	 * @param   int     $id_art   ID of the article to activate.
	 */
	public function activateArticle($id_art) {
	}

	/**
	 * Deactivates an inactive article.
	 *
	 * @param   int     $id_art   ID of the article to deactivate.
	 */
	public function deactivateArticle($id_art) {
	}

	/**
	 * Returns all relevant data about the article with $id_art.
	 *
	 * @param   int     $id_art   ID of the article to get.
	 */
	public function getArticle($id_art) {
	}

	/**
	 * Generates a bill for the given article.
	 *
	 * @param   int     $id       ID of the article to create a bill for.
	 *
	 * @return  array   An array with all data relavant to create a bill.
	 */
	public function getBill($id) {
	}
}
?>
