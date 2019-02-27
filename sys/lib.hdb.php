<?php
require_once $ab_path. 'sys/lib.cache.adapter.php';
require_once $ab_path. 'sys/lib.hdb.databasestructure.php';
require_once $ab_path. 'sys/lib.pub_kategorien.php';
require_once $ab_path. 'sys/lib.shop_kategorien.php';

class ManufacturerDatabaseManagement {
	private static $db;
	private static $instance = null;


	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ManufacturerDatabaseManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function fetchIdsByParam($hdbTable, $param, $columns = array()) {
        $db = $this->getDb();
        $param["LIMIT"] = NULL;
        $param["NO_FIELDS"] = 1;
        $param["SORT_SKIP"] = 1;
        $query = $this->generateFetchQuery($hdbTable, $param, $columns);
        #die($query);

        $arResult = array_keys($db->fetch_nar($query));

        return $arResult;
    }

    public function fetchAllByParam($hdbTable, $param, $columns = array()) {
        $db = $this->getDb();
        $query = $this->generateFetchQuery($hdbTable, $param, $columns);
        $arResult = $db->fetch_table($query);

		$hdbTableData = $this->fetchProductTypeByTable($hdbTable);

		foreach($arResult as $key => $value) {
			$arResult[$key]['HDB_TABLE'] = $hdbTable;
			$arResult[$key]['PRODUCT_TYPE_DESCRIPTION'] = $hdbTableData['DESCRIPTION'];
		}

        return $arResult;
    }

    public function fetchQueryByParam($hdbTable, $param, $columns = array()) {
        $query = $this->generateFetchQuery($hdbTable, $param, $columns);
        return $query;
    }

	public function countByParam($hdbTable, $param, $columns = array()) {
		$db = $this->getDb();

		unset($param['LIMIT']);
		unset($param['OFFSET']);
		unset($param['SORT']);
		unset($param['SORT_DIR']);
		$param['NO_FIELDS'] = TRUE;

		$query = $this->generateFetchQuery($hdbTable, $param);

		$db->querynow($query);
		$rowCount = $db->fetch_atom("SELECT FOUND_ROWS()");

		return $rowCount;
	}

	public function generateFieldsQuery($hdbTable, $param, $columns = array())
	{
		global $langval;
		$db = $this->getDb();

		$sqlFields = "";

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			$sqlFields = "pt.ID_".strtoupper($hdbTable).",";
		} else {
			$sqlFields = "
				pt.ID_".strtoupper($hdbTable)." as ID_HDB_PRODUCT,
				pt.*,
				m.NAME as MANUFACTURER_NAME
			";
		}

		return $sqlFields;
	}

	public function generateHavingQuery($param, $columns = array())
	{
		global $langval;
		$db = $this->getDb();

		$sqlHaving = "";

		return $sqlHaving;
	}

	public function generateWhereQuery($hdbTable, $param, $columns = array())
	{
		global $ab_path, $langval;
		$db = $this->getDb();

		$sqlWhere = "";

		if(isset($param['ID_'.strtoupper($hdbTable)]) && $param['ID_'.strtoupper($hdbTable)] != NULL) {
            if (is_array($param['ID_'.strtoupper($hdbTable)])) {
                // Multiple ids
                $arIds = array();
                foreach ($param['ID_' . strtoupper($hdbTable)] as $index => $id) {
                    $arIds[] = (int)$id;
                }
                $sqlWhere .= " AND pt.ID_".strtoupper($hdbTable)." IN (".implode(", ", $arIds).") ";
            } else {
                // Single id
                $sqlWhere .= " AND pt.ID_".strtoupper($hdbTable)." = '".mysql_real_escape_string($param['ID_'.strtoupper($hdbTable)])."' ";
            }
		}
		if(isset($param['HDB_PRODUCT_ID']) && $param['HDB_PRODUCT_ID'] != NULL) {
			$sqlWhere .= " AND pt.ID_".strtoupper($hdbTable)." = '".mysql_real_escape_string($param['HDB_PRODUCT_ID'])."' ";
		}
		if(isset($param['FK_MAN']) && $param['FK_MAN'] != NULL) {
			$sqlWhere .= " AND pt.FK_MAN = '".mysql_real_escape_string($param['FK_MAN'])."' ";
		}
		if(isset($param['MANUFACTURER']) && $param['MANUFACTURER'] != NULL) {
			$sqlWhere .= " AND m.NAME LIKE '%".mysql_real_escape_string($param['MANUFACTURER'])."%' ";
		}
		if(isset($param['PRODUKTNAME']) && $param['PRODUKTNAME'] != NULL) {
			$sqlWhere .= " AND pt.PRODUKTNAME LIKE '%".mysql_real_escape_string($param['PRODUKTNAME'])."%' ";
		}
		if(isset($param['CONFIRMED']) && $param['CONFIRMED'] !== "" && $param['CONFIRMED'] >= 0) {
			$sqlWhere .= " AND pt.CONFIRMED = '".(int)$param['CONFIRMED']."' ";
		}
		if(isset($param['HAS_USER_DATA']) && $param['HAS_USER_DATA'] !== "") {
			if($param['HAS_USER_DATA'] == 1) {
				$sqlWhere .= " AND pt.DATA_USER IS NOT NULL AND LENGTH(pt.DATA_USER) > 10 ";
			}elseif($param['HAS_USER_DATA'] == 0) {
				$sqlWhere .= " AND (pt.DATA_USER IS NULL || LENGTH(pt.DATA_USER) < 10) ";
			}
		}

		foreach($param as $key => $value) {
			if(!empty($columns) && array_key_exists($key,$columns)) {
				$columnInfo = $columns[$key];

				switch($columnInfo['TYPE']) {
					case 'FLOAT':
					case 'INT':
					case 'DATE':
						if((int)$param[$key]['VON'] != 0 && (int)$param[$key]['BIS'] == 0) {
							$sqlWhere .= " AND pt.`".$key."` > '".mysql_real_escape_string($param[$key]['VON'])."' ";
						} elseif((int)$param[$key]['VON'] == 0 && (int)$param[$key]['BIS'] != 0) {
							$sqlWhere .= " AND pt.`".$key."` < '".mysql_real_escape_string($param[$key]['BIS'])."' ";
						}  elseif((int)$param[$key]['VON'] != 0 && (int)$param[$key]['BIS'] != 0) {
							$sqlWhere .= " AND pt.`".$key."` BETWEEN '".mysql_real_escape_string($param[$key]['VON'])."' AND '".mysql_real_escape_string($param[$key]['BIS'])."' ";
						}

						break;
					case 'MULTICHECKBOX':
					case 'MULTICHECKBOX_AND':
						if(is_array($param[$key])) {
							$sqlWhere .= " AND pt.`".$key."` IN (".implode(',', $param[$key]).") ";
						}
						break;
					case 'FILE':
						if(trim($param[$key]) != "") {
							$sqlWhere .= " AND pt.`".$key."` LIKE '%".mysql_real_escape_string($param[$key])."%' ";
						}
						break;
					case 'TEXT':
					case 'LONGTEXT':
						if(trim($param[$key]) != "") {
							$sqlWhere .= " AND pt.`".$key."` LIKE '%".mysql_real_escape_string($param[$key])."%' ";
						}
					default:
						if(trim($param[$key]) != "") {
							$sqlWhere .= " AND pt.`".$key."` = '".mysql_real_escape_string($param[$key])."' ";
						}
				}
			}
		}

		return $sqlWhere;
	}

	public function generateJoinQuery($hdbTable, $param, $columns = array())
	{
		global $langval;
		$db = $this->getDb();

		$sqlJoin = "";

		$sqlJoin .= " LEFT JOIN manufacturers m ON m.ID_MAN = pt.FK_MAN ";

		return $sqlJoin;
	}

	protected function generateFetchQuery($hdbTable, $param, $columns = array()) {
		global $langval;
		$db = $this->getDb();

		$sqlLimit = "";
		$sqlWhere = "";
		$sqlJoin = "";
		$sqlFields = "";
		$sqlOrder = " pt.FULL_PRODUKTNAME ";
		$sqlHaving = array();

		$sqlWhere = $this->generateWhereQuery($hdbTable, $param, $columns);

		if(isset($param['SORT_BY']) && isset($param['SORT_DIR'])) {

			$sortBy = "pt.ID_".strtoupper($hdbTable);
			$sortDir = "DESC";
			if (isset($param["SORT_BY"])) {
				$sortBy = $param["SORT_BY"];
			}
			if (isset($param["SORT_DIR"])) {
				$sortDir = $param["SORT_DIR"];
			}
			$sqlOrder = $sortBy." ".$sortDir;
		}
        if(isset($param['SORT_SKIP'])) {
            $sqlOrder = false;
        }
		if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
			if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) {
				$sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' ';
			} else {
				$sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' ';
			}
		}

		$sqlFields = $this->generateFieldsQuery($hdbTable, $param, $columns);
		$sqlHaving = $this->generateHavingQuery($hdbTable, $param, $columns);
		$sqlJoin = $this->generateJoinQuery($hdbTable, $param, $columns);


		$query = "
			SELECT
				SQL_CALC_FOUND_ROWS
				".trim($sqlFields, " \t\r\n,")."
			FROM `".$hdbTable."` pt
			".$sqlJoin."
			WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
			".(empty($sqlHaving) ? "" : "HAVING ".implode(" AND ", $sqlHaving))."
			    ".($sqlOrder?'ORDER BY '.$sqlOrder:'')."
				".($sqlLimit?'LIMIT '.$sqlLimit:'')."
		";

		return $query;
	}

	public function searchProduct($column, $searchValue, $page = 1, $perpage = 10, $searchType = 'EXACT', $productTypes = null, $productCategory = null) {
		if($productTypes == null) {
			$productTypes = $this->fetchAllProductTypes();
		}

		$baseQuery = '
			(SELECT
				##ID_COL## as ID_HDB_PRODUCT, pt.FULL_PRODUKTNAME, \'##HDB_TABLE##\' as HDB_TABLE, pt.EAN, pt.FK_KAT, pt.IMPORT_IMAGES
			FROM ##HDB_TABLE## pt
			WHERE ##WHERE## AND pt.CONFIRMED = 1)
		';

        $where = [];
		switch($searchType) {
			case 'LIKE':
				$likeSearch = implode(' AND ', array_map(function($v) use ($column) {
					return "(`".$column."` LIKE '%".mysql_real_escape_string($v)."%')";
				}, explode(' ', $searchValue)));

				$where[] = " ".$likeSearch." ";

				break;
			default:
				$where[] = " `".$column."` = '".mysql_real_escape_string($searchValue)."' ";
		}
		if ($productCategory !== null) {
		    $where[] = " `FK_KAT` = '".(int)$productCategory."' ";
        }
        $baseQuery = str_replace('##WHERE##', implode(" AND ", $where), $baseQuery);

		$query = array();
		foreach($productTypes as $key => $productType) {
			$tmpQuery = $baseQuery;

			$tmpQuery = str_replace('##HDB_TABLE##', $productType['HDB_TABLE'], $tmpQuery);
			$tmpQuery = str_replace('##ID_COL##', 'ID_'.strtoupper($productType['HDB_TABLE']), $tmpQuery);

			$query[] = $tmpQuery;
		}

		$offset = (($page-1)*$perpage);
		$unionQuery = "SELECT SQL_CALC_FOUND_ROWS * FROM (". implode(' UNION ', $query).') as b LIMIT '.(int)$perpage.' OFFSET '.(int)$offset.' ';

		$resultObject = new stdClass();

		$resultObject->results  =  $this->getDb()->fetch_table($unionQuery);
		$resultObject->total = $this->getDb()->fetch_atom("SELECT FOUND_ROWS()");

		return $resultObject;
	}

	public function searchProductsByMan($manufacturerId, $page = 1, $perpage = -1, $productTypes = null, $categoryId = null, $searchText = null) {
        
	    $whereConditions = array("pt.FK_MAN=".(int)$manufacturerId);
        if ($categoryId !== null) {
            if (is_array($categoryId)) {
                $whereConditions[] = "pt.FK_KAT IN (".implode(", ", $categoryId).")";
            } else {
                $categoryDetail = $this->getDb()->fetch1("SELECT ID_KAT, LFT, RGT, ROOT, KAT_TABLE FROM `kat` WHERE ID_KAT=".(int)$categoryId);
                $categoryChildIds = $this->getDb()->fetch_col("
                    SELECT ID_KAT FROM `kat` 
                    WHERE LFT BETWEEN ".(int)$categoryDetail["LFT"]." AND ".(int)$categoryDetail["RGT"]."
                        AND ROOT=".(int)$categoryDetail["ROOT"]);
                $whereConditions[] = "pt.FK_KAT IN (".implode(", ", $categoryChildIds).")";
                if($productTypes == null) {
                    $productTypes = array(
                        $this->fetchProductTypeByTable($categoryDetail["KAT_TABLE"])
                    );
                }
            }
        } else {
		    if($productTypes == null) {
			    $productTypes = $this->fetchAllProductTypes();
            }
		}
		if ($searchText !== null) {
            $whereConditions[] = "pt.PRODUKTNAME LIKE '%".mysql_real_escape_string($searchText)."%'";
        }

		$baseQuery = '
			(SELECT
				##ID_COL## as ID_HDB_PRODUCT, pt.FULL_PRODUKTNAME, pt.PRODUKTNAME, \'##HDB_TABLE##\' as HDB_TABLE, pt.EAN, pt.FK_KAT, pt.IMPORT_IMAGES
			FROM ##HDB_TABLE## pt
			WHERE ##WHERE## AND pt.CONFIRMED = 1)
		';
		
		$baseQuery = str_replace('##WHERE##', implode(" AND ", $whereConditions), $baseQuery);

		$query = array();
		foreach($productTypes as $key => $productType) {
			$tmpQuery = $baseQuery;

			$tmpQuery = str_replace('##HDB_TABLE##', $productType['HDB_TABLE'], $tmpQuery);
			$tmpQuery = str_replace('##ID_COL##', 'ID_'.strtoupper($productType['HDB_TABLE']), $tmpQuery);

			$query[] = $tmpQuery;
		}

		$offset = (($page-1)*$perpage);
		$unionQuery = "
			SELECT SQL_CALC_FOUND_ROWS *
			FROM (". implode(' UNION ', $query).") as b 
			".($perpage > 0 ? "LIMIT ".(int)$perpage." OFFSET ".(int)$offset : "");

		$resultObject = new stdClass();

		$resultObject->results  =  $this->getDb()->fetch_table($unionQuery);
		$resultObject->total = $this->getDb()->fetch_atom("SELECT FOUND_ROWS()");

		return $resultObject;
	}

	public function fetchProductTypeColumnsByTable($table) {
		$hdbTable = $this->fetchProductTypeByTable($table);
		if(isset($hdbTable['CONFIG']) && isset($hdbTable['CONFIG']['COLUMNS'])) {
			return array_keys($hdbTable['CONFIG']['COLUMNS']);
		} else {
			return array();
		}
	}

	public function fetchProductTypeByTable($table) {
		global $langval;
		$tableDefTName = str_replace('hdb_table_', '', $table);

		$result = $this->getDb()->fetch1("
			SELECT
				t.*,
				s.V1 as DESCRIPTION,
				CONCAT('hdb_table_', t.T_NAME) AS HDB_TABLE
			FROM table_def t
			LEFT JOIN
				string_app s on s.S_TABLE='table_def'
				AND s.FK=t.ID_TABLE_DEF
				AND s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
		 	WHERE
				T_NAME = '".mysql_real_escape_string($tableDefTName)."'
		");

		if($result != null) {
			$result['CONFIG'] = $this->fetchProductTypeConfig($table);
		}

		return $result;
	}

	public function fetchProductTypeConfig($table) {
		global $langval, $ab_path, $nar_systemsettings;
		$config = array();

		$tableDefTName = str_replace('hdb_table_', '', $table);
		$cacheFile = $ab_path.'cache/marktplatz/hdb_producttype_config.'.$tableDefTName.'.php';

		$cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
		$modifyTime = @filemtime($cacheFile);
		$diff = ((time()-$modifyTime)/60);

		if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile)) {
			$tableDef = $this->getDb()->fetch1("SELECT * FROM table_def WHERE T_NAME = '".mysql_real_escape_string($tableDefTName)."'");
			$tableDefId  = $tableDef['ID_TABLE_DEF'];

			$sqlFieldNamesToIgnore = array();
			$fieldNamesToIgnore = ManufacturerDatabaseStructureManagement::$masterFieldsNotUsed;
			foreach($fieldNamesToIgnore as $key=>$value) {
				$sqlFieldNamesToIgnore[] = "'".mysql_real_escape_string($value)."'";
			}

			$fields = $this->getDb()->fetch_table($a = "
				select
					t.*, s.V1, s.V2
				FROM `field_def` t
				LEFT JOIN `string_field_def` s on s.S_TABLE='field_def' and s.FK=t.ID_FIELD_DEF
						and s.BF_LANG=if(t.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_DEF+0.5)/log(2)))
				WHERE
					t.FK_TABLE_DEF=".(int)$tableDefId." AND t.B_HDB_ENABLED = 1
				ORDER BY
					t.F_ORDER ASC
			");


			foreach($fields as $key => $field) {
				$col = array(
					'FIELD_NAME' => $field['V1'],
					'F_NAME' => $field['F_NAME'],
					'TYPE' => $field['F_TYP'],
					'UNIT' => $field['V2']
				);

				switch($field['F_TYP']) {
					case 'LIST':
					case 'VARIANT':
					case 'MULTICHECKBOX':
					case 'MULTICHECKBOX_AND':

						$col['LIST_VALUES'] = array();
						$listValues = CategoriesBase::getListValuesByListId($field['FK_LISTE']);
						foreach($listValues as $listKey => $listValueData) {
							$col['LIST_VALUES'][$listValueData['ID_LISTE_VALUES']] = $listValueData['V1'];
						}

						break;
				}


				switch($field['F_NAME']) {
					case 'FK_KAT':
						$col['DEFAULT_VISIBLE'] = true;
						$col['TYPE'] = 'LIST';
						$col['LIST_VALUES'] = array();

						$kat = new TreeCategories("kat", 1);
						$rootKat = $kat->element_read(1);
						$categories = $this->getDb()->fetch_table($query = "
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
							  ID_KAT != 1 AND
							  KAT_TABLE = '".$tableDefTName."'
							ORDER BY LFT
						");

						if(is_array($categories) && count($categories) > 0) {
							foreach ($categories as $categoryKey => $category) {
								$col['LIST_VALUES'][$category['ID_KAT']] = str_repeat('-', ($category['LEVEL'] - 1) * 2) . ' ' . $category['V1'];
							}
						}

						break;
					case 'IMPORT_IMAGES':
						$col['TYPE'] = 'IMAGE';
						$col['DEFAULT_VISIBLE'] = true;

						break;
					case 'EAN':
						$col['DEFAULT_VISIBLE'] = true;

						break;
					case 'PRODUKTNAME':
					case 'FK_MAN':
					case 'ID_'.strtoupper($tableDefTName):
						continue 2;
						break;
				}

				$config['COLUMNS'][$field['F_NAME']] = $col;
			}

			file_put_contents($cacheFile, serialize($config));
		}

		$config = unserialize(file_get_contents($cacheFile));

		return $config;
	}

	public function fetchAllProductTypes() {
		global $langval;
		return $this->getDb()->fetch_table("
			SELECT
				t.*,
				s.V1 as DESCRIPTION,
				CONCAT('hdb_table_', t.T_NAME) AS HDB_TABLE
			FROM table_def t
			LEFT JOIN
				string_app s on s.S_TABLE='table_def'
				AND s.FK=t.ID_TABLE_DEF
				AND s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
		    WHERE t.T_NAME <> 'vendor_master' 
		");
	}


	public function processDataColumnValue($product, $colValue) {
		global $tpl_main;

		$result = '';

		$result = $product[$colValue['COL_NAME']];

		switch($colValue['TYPE']) {
			case 'VARIANT':
			case 'LIST':
				if(array_key_exists($product[$colValue['COL_NAME']], $colValue['LIST_VALUES'])) {
					$result = $colValue['LIST_VALUES'][$product[$colValue['COL_NAME']]];
				}
				break;
			case 'MULTICHECKBOX':
			case 'MULTICHECKBOX_AND':
				$tmpValues = explode('x', trim($product[$colValue['COL_NAME']], 'x'));
				$tmpResult = array();
				if(is_array($tmpValues) && count($tmpValues) > 0) {
					foreach($tmpValues as $key => $explodedValue) {
						if(array_key_exists($explodedValue, $colValue['LIST_VALUES'])) {
							$tmpResult[] = $colValue['LIST_VALUES'][$explodedValue];
						} else {
							$tmpResult[] = $explodedValue;
						}
					}
					$result = implode(', ', $tmpResult);
				}

				break;
			case 'IMAGE':
				if(!empty($product[$colValue['COL_NAME']])) {
					$result = '<a href="'.$tpl_main->tpl_uri_baseurl_full($product[$colValue['COL_NAME']]).'" target="_blank"><img src="'.$tpl_main->tpl_thumbnail('"'.$product[$colValue['COL_NAME']].'",30,30,crop').'"></a>';
				}
				break;
		}


		if($colValue['UNIT']) {
			$result .= ' '.$colValue['UNIT'];
		}
		return $result;
	}

	public function processSearchFieldColumn($column, $searchData = array()) {
		global $ab_path;
		$filename = 'hdb_products.searchfieldlist.'.strtolower($column['TYPE']).'.htm';

		if(!is_file($ab_path.'admin/tpl/de/'.$filename)) {
			$filename = 'hdb_products.searchfieldlist.default.htm';
		}

		$tpl = new Template('tpl/de/'.$filename);
		$tpl->addvars($column);
		$tpl->addvar('SELECTED_VALUE', $searchData[$column['COL_NAME']]);


		switch($column['TYPE']) {
			case 'LIST':
			case 'MULTICHECKBOX':
			case 'MULTICHECKBOX_AND':
			case 'VARIANT':
				$selectOptions = '';
				foreach($column['LIST_VALUES'] as $key => $listValue) {
					$selectOptions .= '<option value="'.$key.'" '.((($searchData[$column['COL_NAME']] == $key) || (is_array($searchData[$column['COL_NAME']]) && in_array($key, $searchData[$column['COL_NAME']])))?'selected':'').'>'.$listValue.'</option>';
				}

				$tpl->addvar('SELECT_OPTIONS', $selectOptions);
				break;
			case 'INT':
			case 'FLOAT':
			case 'DATE':
				$tpl->addvar('SELECTED_VALUE_VON', $searchData[$column['COL_NAME']]['VON']);
				$tpl->addvar('SELECTED_VALUE_BIS', $searchData[$column['COL_NAME']]['BIS']);
				break;
		}



		return $tpl->process();
	}

	public function processEditFieldColumn($column, $productData = array()) {
		global $ab_path;
		
		switch ($column["COL_NAME"]) {
			default:
		    $filename = 'hdb_products.editfieldlist.'.strtolower($column['TYPE']).'.htm';
				break;
			case "BESCHREIBUNG":
				$filename = 'hdb_products.editfieldlist.specific_description.htm';
				$column['TYPE'] = "_BESCHREIBUNG";
				break;
		}

		if(!is_file($ab_path.'admin/tpl/de/'.$filename)) {
			$filename = 'hdb_products.editfieldlist.default.htm';
		}

		$tpl = new Template('tpl/de/'.$filename);
		$tpl->addvars($column);
		$tpl->addvar('SELECTED_VALUE', $productData[$column['COL_NAME']]);


		switch($column['TYPE']) {
			case '_BESCHREIBUNG':
				$tpl->addvar($column["COL_NAME"], $productData[$column['COL_NAME']]);
				break;
			case 'LIST':
			case 'MULTICHECKBOX':
			case 'MULTICHECKBOX_AND':
			case 'VARIANT':
				$tmpListValue = explode('x', trim($productData[$column['COL_NAME']], 'x'));

				$selectOptions = '';
				foreach($column['LIST_VALUES'] as $key => $listValue) {
					$selectOptions .= '<option value="'.$key.'" '.((($tmpListValue == $key) || (is_array($tmpListValue) && in_array($key, $tmpListValue)))?'selected':'').'>'.$listValue.'</option>';
				}

				$tpl->addvar('SELECT_OPTIONS', $selectOptions);
				break;
			case 'DATE':
				$tpl->addvar('SELECTED_VALUE', strstr($productData[$column['COL_NAME']],' ',true));

				break;
			case 'IMAGE':
			case 'FILE':
				$tpl->addvar('FILE_URL', $productData[$column['COL_NAME']]);

		}

		return $tpl->process();
	}

	public function processEditUserDataFieldColumn($column, $userdata = array()) {
		global $ab_path;
		$filename = 'hdb_products.edituserdatafieldlist.'.strtolower($column['TYPE']).'.htm';
		$result = '';
		if(count($userdata) == 0) {
			return '';
		}

		if(!is_file($ab_path.'admin/tpl/de/'.$filename)) {
			$filename = 'hdb_products.edituserdatafieldlist.default.htm';
		}

		foreach($userdata as $key => $data) {

			$tpl = new Template('tpl/de/'.$filename);
			$tpl->addvars($column);
			$tpl->addvar('DATA_USER_KEY', $key);

			$selectedValue = $data[$column['COL_NAME']];
			$printValue = $data[$column['COL_NAME']];

			switch($column['TYPE']) {
				case 'LIST':
				case 'MULTICHECKBOX':
				case 'MULTICHECKBOX_AND':
				case 'VARIANT':
					$tmpListValue = explode('x', trim($data[$column['COL_NAME']], 'x'));
					$output = array();

					foreach($tmpListValue as $key => $value) {
						if(array_key_exists($value, $column['LIST_VALUES'])) {
							$output[] = $column['LIST_VALUES'][$value];
						} else {
							$output[] = $value;
						}
					}

					$printValue = implode(', ', $output);
					break;

				case 'IMAGE':
				case 'FILE':
					$tpl->addvar('FILE_URL', $data[$column['COL_NAME']]);
				default:
					break;
			}

			$tpl->addvar('SELECTED_VALUE', $selectedValue);
			$tpl->addvar('PRINT_VALUE', $printValue);


			$result .= $tpl->process();
		}

		return $result;
	}

	public function fetchProductById($hdbProductId, $hdbTable) {
		$result = $this->fetchAllByParam($hdbTable, array('HDB_PRODUCT_ID' => $hdbProductId));

		return $result['0'];
	}

	public function saveProduct($hdbProductId, $hdbTable, $data) {
		
		if (array_key_exists("IMPORT_IMAGES", $data) && is_array($data["IMPORT_IMAGES"]) && !empty($data["IMPORT_IMAGES"])) {
			$arImages = array();
			foreach ($data["IMPORT_IMAGES"] as $imageIndex => $imagePath) {
				$arImages[] = $imagePath;
			}
			$data["IMPORT_IMAGES"] = serialize($arImages);
		} else {
			$data["IMPORT_IMAGES"] = null;
		}
		
		if($hdbProductId == null) {
			$hdbProductId = $this->getDb()->update($hdbTable, array(
				'ID_'.strtoupper($hdbTable) => null
			));
		}
		
		$result = $this->getDb()->update($hdbTable, array_merge($data, array('ID_'.strtoupper($hdbTable) => $hdbProductId)));
		if (!$hdbProductId) {
		    $hdbProductId = $result;
        }

		$this->updateProductFullName($hdbProductId, $hdbTable);
		return $hdbProductId;
	}

	public function updateProductFullName($hdbProductId, $hdbTable) {
		$this->getDb()->querynow("UPDATE `".$hdbTable."` p SET p.FULL_PRODUKTNAME = p.PRODUKTNAME WHERE p.`ID_".strtoupper($hdbTable)."` = '".$hdbProductId."'");
		$this->getDb()->querynow("UPDATE `".$hdbTable."` p, manufacturers m SET p.FULL_PRODUKTNAME = CONCAT(m.NAME, ' ', p.PRODUKTNAME) WHERE p.FK_MAN = m.ID_MAN AND p.`ID_".strtoupper($hdbTable)."` = '".$hdbProductId."'");
	}

	public function updateProductFullNameByManufacturer($hdbManufacturerId) {
		$productTypes = $this->fetchAllProductTypes();
		foreach($productTypes as $key => $productType) {
			$this->getDb()->querynow($a = "UPDATE `".$productType['HDB_TABLE']."` p, manufacturers m SET p.FULL_PRODUKTNAME = CONCAT(m.NAME, ' ', p.PRODUKTNAME) WHERE p.FK_MAN = m.ID_MAN AND m.ID_MAN = '".$hdbManufacturerId."'");
		}
	}


	public function deleteProduct($hdbProductId, $hdbTable) {
		$this->getDb()->delete($hdbTable, $hdbProductId);
	}

	public function deleteUserDataIndexesForProduct($hdbProductId, $hdbTable, $indexes = array()) {
		$hdbProduct = $this->fetchProductById($hdbProductId, $hdbTable);
		if($hdbProduct == null) {
			return false;
		}

		$dataUser = (unserialize($hdbProduct['DATA_USER']) == null)?array():unserialize($hdbProduct['DATA_USER']);
		if($dataUser && is_array($dataUser)) {
			foreach($dataUser as $key => $value) {
				if(in_array($key, $indexes)) {
					unset($dataUser[$key]);
				}
			}
		}

		$this->getDb()->update($hdbTable, array(
			'ID_'.strtoupper($hdbTable) => $hdbProductId,
			'DATA_USER' => (count($dataUser)>0)?serialize($dataUser):''
		));
	}

	public function deleteUserDataForProduct($hdbProductId, $hdbTable) {
		$this->getDb()->update($hdbTable, array(
			'ID_'.strtoupper($hdbTable) => $hdbProductId,
			'DATA_USER' => ""
		));
	}

	public function mergeProduct($originHdbProductId, $destinationHdbProductId, $hdbTable) {

		#$tableDefTName = str_replace('hdb_table_', '', $hdbTable);
		#$this->getDb()->querynow("UPDATE ad_master SET FK_PRODUCT = '".$destinationHdbProductId."' WHERE FK_PRODUCT = '".$originHdbProductId."'");

		return $destinationHdbProductId;
	}

	public function fetchManufacturerById($hdbManufacturerId) {
		return $this->getDb()->fetch1("SELECT * FROM manufacturers m WHERE m.ID_MAN = '".(int)$hdbManufacturerId."'");
	}

	public function updateManufacturerById($hdbManufacturerId, $data, $autoSync = true) {
		$result = null;
		$cacheAdapter = new CacheAdapter();

		if($hdbManufacturerId == null) {
			$values = array();
			if($data['NAME'] != "") { $values['NAME'] = '"'.$data['NAME'].'"'; }
			if($data['URL'] != "") { $values['URL'] = '"'.$data['URL'].'"'; }
			if($data['CONFIRMED'] != "") { $values['CONFIRMED'] = '"'.$data['CONFIRMED'].'"'; }

			$lastresult = $this->getDb()->querynow($q = "INSERT INTO manufacturers (".implode(',', array_keys($values)).") VALUES (".implode(',', $values).")");
			$result = $lastresult['int_result'];
			$hdbManufacturerId = $result;

		} else {
			$values = array();
			if(isset($data['NAME'])) { $values['NAME'] = 'NAME = "'.$data['NAME'].'"'; }
			if(isset($data['URL'])) { $values['URL'] = 'URL = "'.$data['URL'].'"'; }
			if(isset($data['CONFIRMED'])) { $values['CONFIRMED'] = 'CONFIRMED = "'.$data['CONFIRMED'].'"'; }

			$this->getDb()->querynow($q = "UPDATE manufacturers SET ".implode(',', $values)." WHERE ID_MAN = '".(int)$hdbManufacturerId."'");
		}

		$cacheAdapter->_cacheManufacturesSearchbox();
		$this->updateProductFullNameByManufacturer($hdbManufacturerId);


		return $hdbManufacturerId;
	}



	public function deleteManufacturerById($hdbManufacturerId) {
		$cacheAdapter = new CacheAdapter();
		// first delete all products
		$productTypes = $this->fetchAllProductTypes();
		foreach($productTypes as $key => $productType) {
			$products = $this->fetchAllByParam($productType['HDB_TABLE'], array('FK_MAN' => $hdbManufacturerId));
			foreach($products as $k => $product) {
				$this->deleteProduct($product['ID_HDB_PRODUCT'], $productType['HDB_TABLE']);
			}
		}

		$this->getDb()->querynow("DELETE FROM manufacturers WHERE ID_MAN = '".(int)$hdbManufacturerId."'");

		$cacheAdapter->_cacheManufacturesSearchbox();
	}

	public function suggestProductUserData($hdbProductId, $hdbTable, $suggestData) {
		global $ab_path;

		$hdbProduct = $this->fetchProductById($hdbProductId, $hdbTable);
		if($hdbProduct == null) {
			return false;
		}

		$hdbUploadDirectory = $this->getManufacturerDatabaseUploadDirectory();
		$hdbRelativeUploadDirectory = $this->getManufacturerDatabaseUploadDirectory(false);
		if(isset($suggestData['IMPORT_IMAGES']) && $suggestData['IMPORT_IMAGES'] != "") {
			if(strpos($suggestData['IMPORT_IMAGES'], '/') === 0) {
				$suggestImageFile = $suggestData['IMPORT_IMAGES'];
			} else {
				$suggestImageFile = $ab_path.$suggestData['IMPORT_IMAGES'];
			}

			$hdbUploadFile = $hdbProductId.'_IMAGE_'.time().'_'.pathinfo($suggestImageFile, PATHINFO_FILENAME).'.'.pathinfo($suggestImageFile, PATHINFO_EXTENSION);
			copy($suggestImageFile, $hdbUploadDirectory.$hdbUploadFile);
			$suggestData['IMPORT_IMAGES'] = $hdbRelativeUploadDirectory.$hdbUploadFile;
		}


		$suggestDataFingerprintIsIdentical = true;
		$suggestDataFingerprint = md5(json_encode($suggestData));

		$dataUser = (unserialize($hdbProduct['DATA_USER']) == null)?array():unserialize($hdbProduct['DATA_USER']);

		foreach($dataUser as $key => $dataUserItem) {
			$dataUserItemFingerPrint = 	md5(json_encode($dataUserItem));
			if($dataUserItemFingerPrint == $suggestDataFingerprint) {
				$suggestDataFingerprintIsIdentical = false;
				break;
			}
		}

		$dataUser[] = $suggestData;

		$this->saveProduct($hdbProductId, $hdbTable, array(
			'DATA_USER' => serialize($dataUser)
		));
	}

	public function getManufacturerDatabaseUploadDirectory($absoluteUrl = TRUE) {
		global $ab_path;
		return ($absoluteUrl?$ab_path:'').'cache/hdb/';
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

	private function __construct() {
	}
	private function __clone() {
	}



}