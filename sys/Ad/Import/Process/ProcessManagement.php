<?php

class Ad_Import_Process_ProcessManagement {

	private static $db;
	private static $instance = null;

	protected $lastFetchCount = 0;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return Ad_Import_Process_ProcessManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	/**
	 * @param                                 $source
	 * @param Ad_Import_Preset_AbstractPreset $preset
	 *
	 * @return Ad_Import_Process_Process
	 * @throws Exception
	 */
	public function createNewImportProcess($source, $userId, Ad_Import_Preset_AbstractPreset $preset, $configuration = array()) {

		$process = new Ad_Import_Process_Process($source, $userId, $preset, $configuration);

		return $process;
	}

	/**
	 * @param $importProcessId
	 *
	 * @return Ad_Import_Process_Process|null
	 */
	public function loadImportProcessById($importProcessId) {
		$process = $this->getDb()->fetch1("SELECT * FROM import_process WHERE ID_IMPORT_PROCESS = '".(int)$importProcessId."'");
		if($process != null) {
			return $this->loadProcessByDataset($process);
		}

		return null;
	}

	/**
	 * @param $importProcess
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function loadProcessByDataset($importProcess) {
	    $importProcessCache = Ad_Import_Process_Process::getCachePathStatic($importProcess);
	    if (!is_dir($importProcessCache)) {
	        mkdir($importProcessCache, 0777, true);
        }
        if (file_exists($importProcessCache."/load.lock")) {
            // TODO: Automatically release lock after some time?
            return false;
        }
        touch($importProcessCache."/load.lock");
		$importProcessObject = unserialize($importProcess['PROCESS_DATA']);
		unlink($importProcessCache."/load.lock");
		if ($importProcessObject instanceof Ad_Import_Process_Process) {
			Api_TraderApiHandler::getInstance($GLOBALS['db'])->triggerEvent(Api_TraderApiEvents::IMPORT_PROCESS_LOAD, $importProcessObject);
			return $importProcessObject;
		} else {
			return false;
		}
	}

	public function fetchAllByParam($param) {
		$db = $this->getDb();
		$query = $this->generateFetchQuery($param);
		$result = $db->fetch_table($query);
		$this->lastFetchCount = $db->fetch_atom("SELECT FOUND_ROWS()");

		return $result;
	}



	public function countByParam($param) {
		$db = $this->getDb();

		unset($param['LIMIT']);
		unset($param['OFFSET']);
		unset($param['SORT']);
		unset($param['SORT_DIR']);
		$param['NO_FIELDS'] = TRUE;

		$query = $this->generateFetchQuery($param);

		$db->querynow($query);
		$rowCount = $db->fetch_atom("SELECT FOUND_ROWS()");

		return $rowCount;
	}

	public function getLastFetchByParamCount() {
		return $this->lastFetchCount;
	}

	protected function generateFetchQuery($param) {
		global $langval;
		$db = $this->getDb();

		$sqlLimit = "";
		$sqlWhere = "";
		$sqlJoin = "";
		$sqlFields = "";
		$sqlOrder = " i.STAMP_CREATE DESC";


		if(isset($param['FK_USER']) && $param['FK_USER'] != NULL) {
			$sqlWhere .= " AND i.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' ";
		}
		if(isset($param['FK_IMPORT_SOURCE']) && $param['FK_IMPORT_SOURCE'] != NULL) {
			$sqlWhere .= " AND i.FK_IMPORT_SOURCE = '".mysql_real_escape_string($param['FK_IMPORT_SOURCE'])."' ";
		}
		if(isset($param['CRON_STAT']) && $param['CRON_STAT'] != NULL) {
			$sqlWhere .= " AND i.CRON_STAT = '".mysql_real_escape_string($param['CRON_STAT'])."' ";
		}
		if(isset($param['STATUS']) && $param['STATUS'] != NULL) {
			if (!is_array($param['STATUS'])) {
				// Check for single status
				$sqlWhere .= " AND i.STATUS = '".mysql_real_escape_string($param['STATUS'])."' ";
			} else {
				// Check for one of multiple status value
				$arStatusList = array();
				foreach ($param['STATUS'] as $statusIndex => $statusValue) {
					$arStatusList[] = (int)$statusValue;
				}
				$sqlWhere .= " AND i.STATUS IN (".implode(", ", $arStatusList).") ";
			}
		}
		if(isset($param['MIN_AGE_DAYS']) && $param['MIN_AGE_DAYS'] != NULL) { $sqlWhere .= " AND i.STAMP_UPDATE < DATE_SUB( NOW() , INTERVAL ".(int)$param['MIN_AGE_DAYS']." DAY ) "; }




		if(isset($param['SORT_BY']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT_BY']." ".$param['SORT_DIR']; }
		if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
			if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) {
				$sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' ';
			} else {
				$sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' ';
			}
		}

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			$sqlFields = "i.ID_IMPORT_PROCESS";
		} else {
			$sqlFields = "
				i.*
			";
		}

		$query = "
			SELECT
				SQL_CALC_FOUND_ROWS
				".$sqlFields."
			FROM `import_process` i
			".$sqlJoin."
			WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
			GROUP BY i.ID_IMPORT_PROCESS
			ORDER BY ".$sqlOrder."
				".($sqlLimit?'LIMIT '.$sqlLimit:'')."
		";

		return $query;
	}




	/**
	 * @param $processId
	 * @param Ad_Import_Process_Process $process
	 */
	public function saveProcess($processId, $process) {

		if($processId == null) {
			$processData['STAMP_CREATE'] = date("Y-m-d H:i:s");
			$processId = $this->getDb()->update('import_process', $processData);
			$process->setProcessId($processId);
		}

		$processData = array(
			'FK_USER' => $process->getUserId(),
			'FK_IMPORT_SOURCE' => $process->getImportSource(),
			'CRON_STAT' => $process->getCronProcess(),
			'STATUS' => $process->getStatus(),
			'PROCESS_NAME' => $process->getProcessName(),
			'STAMP_UPDATE' => date("Y-m-d H:i:s"),
			'PROCESS_DATA' => serialize($process),
			'STATS_ALL' => $process->getEstimatedProgressEnd(),
			'STATS_NEW' => $process->getStatsNewArticles(),
			'STATS_UPDATED' => $process->getStatsUpdatedArticles(),
			'STATS_DELETED' => $process->getStatsDeletedArticles(),
			'STATS_PAUSED' => $process->getStatsPausedArticles(),
			'STATS_STARTED' => $process->getStatsStartedArticles()
		);

		$processData['ID_IMPORT_PROCESS'] = $processId;
		$result = $this->getDb()->update('import_process', $processData);


		return $result;
	}

	public function deleteImportProcess($importProcessId) {
		$a = $this->getDb()->delete('import_process', $importProcessId);
		return $a;
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