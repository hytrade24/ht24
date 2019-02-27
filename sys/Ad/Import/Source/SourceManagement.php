<?php

class Ad_Import_Source_SourceManagement {

	private static $instance = null;
	
	private $db;
	private $sourceCache;

	private function __construct(ebiz_db $db) {
		$this->db = $db;
		$this->sourceCache = array();
	}
	
	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return Ad_Import_Source_SourceManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self($db);
		}
		return self::$instance;
	}

	/**
	 * Get import source object by id (cached by id if requested multiple times)
	 * @param $sourceId
	 * @return Ad_Import_Source_Source|null
	 */
	public function fetchCachedById($sourceId) {
		if (!array_key_exists($sourceId, $this->sourceCache)) {
			$arSource = $this->fetchById($sourceId);
			if (is_array($arSource)) {
				$this->sourceCache[$sourceId] = Ad_Import_Source_Source::getByAssoc($this->db, $arSource);
			} else {
				return null;
			}
		}
		return $this->sourceCache[$sourceId];
	}

	public function fetchAllByParam($param, &$count = 0) {
		$query = $this->generateFetchQuery($param);
		$result = $this->db->fetch_table($query);
		$count = $this->db->fetch_atom("SELECT FOUND_ROWS()");

		return $result;
	}

	public function fetchById($sourceId) {
		$query = "SELECT i.* FROM `import_source` i WHERE ID_IMPORT_SOURCE=".(int)$sourceId;
		return $this->db->fetch1($query);
	}

	public function countByParam($param) {
		unset($param['LIMIT']);
		unset($param['OFFSET']);
		unset($param['SORT']);
		unset($param['SORT_DIR']);
		$param['NO_FIELDS'] = TRUE;

		$query = $this->generateFetchQuery($param);

		$this->db->querynow($query);
		$rowCount = $this->db->fetch_atom("SELECT FOUND_ROWS()");

		return $rowCount;
	}

	protected function generateFetchQuery($param) {
		$sqlLimit = "";
		$sqlWhere = "";
		$sqlJoin = "";
		$sqlFields = "";
		$sqlOrder = " i.ID_IMPORT_SOURCE DESC";

		if(isset($param['FK_USER']) && $param['FK_USER'] != NULL) { $sqlWhere .= " AND i.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
		if(isset($param['FK_IMPORT_PRESET']) && $param['FK_IMPORT_PRESET'] != NULL) { $sqlWhere .= " AND i.FK_IMPORT_PRESET = '".mysql_real_escape_string($param['FK_IMPORT_PRESET'])."' "; }
		
		if(isset($param['DOWNLOAD_NEXT_LT']) && $param['DOWNLOAD_NEXT_LT'] != NULL) {
			$sqlWhere .= " AND i.DOWNLOAD_NEXT IS NOT NULL AND i.DOWNLOAD_NEXT < '".mysql_real_escape_string($param['DOWNLOAD_NEXT_LT'])."' ";
		}
		
		if(isset($param['SORT_BY']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT_BY']." ".$param['SORT_DIR']; }
		if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
			if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
		}

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			$sqlFields = "i.ID_IMPORT_SOURCE";
		} else {
			$sqlFields = "i.*";
			if(isset($param['FIELD_PRESET_NAME']) && $param['FIELD_PRESET_NAME'] === TRUE) {
				$sqlFields .= ", (SELECT PRESET_NAME FROM `import_preset` WHERE ID_IMPORT_PRESET=i.FK_IMPORT_PRESET) as PRESET_NAME";
			}
		}

		$query = "
			SELECT
				SQL_CALC_FOUND_ROWS
				".$sqlFields."
			FROM `import_source` i
			".$sqlJoin."
			WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
			GROUP BY i.ID_IMPORT_SOURCE
			ORDER BY ".$sqlOrder."
				".($sqlLimit?'LIMIT '.$sqlLimit:'')."
		";

		return $query;
	}

	public function deleteImportSource($importSourceId) {
		$a = $this->db->delete('import_source', $importSourceId);
		return $a;
	}

}