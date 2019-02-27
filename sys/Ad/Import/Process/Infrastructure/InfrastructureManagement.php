<?php

class Ad_Import_Process_Infrastructure_InfrastructureManagement {

	/** @var  SQLite3 */
	protected $sqllite;
	/** @var  Ad_Import_Process_Process */
	protected $importProcess;
	/** @var  Ad_Import_Preset_AbstractPreset */
	protected $preset;

	protected $logCache = array();
	protected $markerCache = array();

	/**
	 * @param $importProcess
	 */
	function __construct($importProcess) {
		$this->importProcess = $importProcess;
		$this->preset = $this->importProcess->getPreset();

		$this->openDatabase();
	}


	public function openDatabase() {
		$this->sqllite = new SQLite3($this->getDatabaseFilename());
		if(!$this->sqllite) {
			throw new Exception("Could not create SQLite Database");
		}
	}

	public function createLogTable() {
		$query = '';

		$query .= "DROP TABLE IF EXISTS log;";
		$query .= "
			CREATE TABLE log(
				LOG_DATE TEXT,
				LOG_LEVEL TEXT,
				LOG_TEXT TEXT,
				LOG_DATA TEXT
			);
		";
		$query .= "CREATE INDEX idx_log ON log (LOG_LEVEL, LOG_DATE);";

		$result = $this->sqllite->exec($query);

	}


	public function createBasedataTable() {
		$dataFields = $this->preset->getDataFields();
		$dataFieldKeys = array_keys($dataFields);

		foreach($dataFieldKeys as $key => $value) {
			$dataFieldKeys[$key] = 'COL'.$value.' TEXT';
		}

		$query = "DROP TABLE IF EXISTS basedata;";
		$query .= "
			CREATE TABLE basedata (
				ID INT PRIMARY KEY NOT NULL,
				IMPORT_MARKER INT,
				IMPORT_MARKER_DETAILS TEXT
				".(!empty($dataFieldKeys) ? ",".implode(', ', $dataFieldKeys) : "")."
			);
		";

		$query .= "CREATE INDEX idx_base_marker ON basedata (IMPORT_MARKER);";


		$result = $this->sqllite->exec($query);
		if ($result !== true) {
			echo($query."\n");
			die($this->sqllite->lastErrorMsg());
		}
	}

	public function createArticleTables() {
		$tableFieldsByTableDef = $this->preset->getTableFieldsByTableDef();
		$masterTableFields = $tableFieldsByTableDef['artikel_master'];
		$query = '';

		foreach($tableFieldsByTableDef as $tableDef => $tableFields) {
			$tableFieldKeys = array_keys($tableFields);
			$tableFieldKeys = array_unique(array_merge(array_keys($masterTableFields), $tableFieldKeys), SORT_STRING);

			foreach($tableFieldKeys as $key => $value) {
				$tableFieldKeys[$key] = $value.' TEXT';
			}


			$query .= "DROP TABLE IF EXISTS data_".$tableDef.";";
			$query .= "
				CREATE TABLE data_".$tableDef." (
					ID INT PRIMARY KEY NOT NULL,
					".implode(', ', $tableFieldKeys)."
				);
			";
		}

		$result = $this->sqllite->exec($query);
		if ($result !== true) {
			echo($query."\n");
			die($this->sqllite->lastErrorMsg());
		}

	}

	public function loadDataIntoBasedataTable($data) {
		$dataFields = $this->preset->getDataFields();
		$dataQueryStatement = array();

		$dataFieldKeys = array_keys($dataFields);

		$result = $this->sqllite->query("SELECT MAX(ID) FROM basedata");
		if ($result === false) {
			throw new Exception("SQLite: ".$this->sqllite->lastErrorMsg()."\nSELECT MAX(ID) FROM basedata");
		}
		$lastIdInDatabase = array_shift($result->fetchArray(SQLITE3_NUM));
		$nextId = $lastIdInDatabase + 1;

		foreach($dataFieldKeys as $key => $value) {
			$dataFieldKeys[$key] = '`COL'.$value.'`';
		}


		$this->sqllite->query("BEGIN TRANSACTION");

		foreach($data as $key => $value) {

			$insertValues = array();
			foreach($dataFields as $mDataFieldKey => $mDataField) {

				if(array_key_exists($mDataFieldKey, $value)) {
					$insertValues[] = $this->sqllite->escapeString($value[$mDataFieldKey]);
				} else {
					$insertValues[] = '';
				}
			}



			$dataQueryStatement = '('.$nextId.', \''.implode('\', \'', $insertValues).'\')';
			$result = $this->sqllite->query($q = "INSERT INTO basedata (ID, ".implode(', ', $dataFieldKeys).") VALUES ".$dataQueryStatement." ");
			if(!$result) {
				throw new Exception("SQLite: ".$this->sqllite->lastErrorMsg()."\n".$q);
			}

			$nextId++;
		}

		$result = $this->sqllite->query("COMMIT TRANSACTION");
		return true;
	}

	public function fetchBasedataQuery($limit = 100, $offset = 0) {
		return $this->sqllite->query("SELECT * FROM basedata ORDER BY ID ASC LIMIT ".(int)$limit." OFFSET ".(int)$offset." ");

	}

	public function fetchArticleDataQuery($categoryTable, $limit = 100, $offset = 0) {
		return $this->sqllite->query("SELECT * FROM data_".$categoryTable." ORDER BY ID ASC LIMIT ".(int)$limit." OFFSET ".(int)$offset." ");
	}

	public function fetchImportIdentifiers($categoryTable, $limit = 100, $offset = 0) {
		return $this->sqllite->query("SELECT IMPORT_IDENTIFIER FROM data_".$categoryTable." ORDER BY ID ASC LIMIT ".(int)$limit." OFFSET ".(int)$offset." ");
	}

	public function insertArticleData($categoryTable, $data = array()) {
		$sqlTable = "data_".$categoryTable;
		$dataQueryStatement = array();

		$tableFieldsByTableDef =  $this->preset->getTableFieldsByTableDef();
		$masterTableFields = $tableFieldsByTableDef['artikel_master'];
		$categoryTableFields = $tableFieldsByTableDef[$categoryTable];

		$tableFieldKeys = array_unique(array_merge(array_keys($masterTableFields), array_keys($categoryTableFields)), SORT_STRING);
		$baseDataQueryStatement = array();

		foreach($tableFieldKeys as $key => $value) {
			$baseDataQueryStatement[$value] = '';
		}

        $result = $this->sqllite->query("SELECT MAX(ID) FROM $sqlTable");
        if ($result === false) {
            throw new Exception("SQLite: " . $this->sqllite->lastErrorMsg() . "\nSELECT MAX(ID) FROM $sqlTable");
        }
        $lastIdInDatabase = array_shift($result->fetchArray(SQLITE3_NUM));
		$nextId = $lastIdInDatabase + 1;

		$this->sqllite->query("BEGIN TRANSACTION");

		foreach($data as $key => $value) {
			$dataQueryStatement = array_intersect_key(array_merge($baseDataQueryStatement, $value), $baseDataQueryStatement);

			foreach($dataQueryStatement as $vkey => $vvalue) {
				$dataQueryStatement[$vkey] = $this->sqllite->escapeString($vvalue);
			}
			$dataQueryStatement = '(\''.$value['ID'].'\', \''.implode('\', \'', $dataQueryStatement).'\')';
			$this->sqllite->query($q = "INSERT INTO $sqlTable (ID, ".implode(', ', $tableFieldKeys).") VALUES ".$dataQueryStatement." ");

			$nextId++;
		}

		$result = $this->sqllite->query("COMMIT TRANSACTION");
	}

	public function massDeleteArticleData($categoryTable, $dataIds) {
		$sqlTable = "data_".$categoryTable;

		if(count($dataIds) == 0) {
			return true;
		}

		$this->sqllite->query("BEGIN TRANSACTION");

		foreach($dataIds as $key => $id) {
			$query = "DELETE FROM $sqlTable WHERE ID = $id ";
			$this->sqllite->query($query);
		}
		$result = $this->sqllite->query("COMMIT TRANSACTION");

		return $result;
	}

	public function countAllArticleData($excludeImportStatus = array()) {
		$countArticles = 0;
		$tableFieldsByTableDef =  $this->preset->getTableFieldsByTableDef();

		$queryExcludeImportStatus = '';
		if(count($excludeImportStatus) > 0) {
			foreach($excludeImportStatus as $vkey => $vvalue) {
				$excludeImportStatus[$vkey] = $this->sqllite->escapeString($vvalue);
			}

			$queryExcludeImportStatus = " AND IMPORT_TASK NOT IN ('".implode('\', \'', $excludeImportStatus)."') ";
		}

		foreach($tableFieldsByTableDef as $tableDef => $tableFields) {
			#$countArticles += (int)$t

			$query = "SELECT COUNT(ID) FROM data_".$tableDef." WHERE 1 = 1 ".$queryExcludeImportStatus." ";
			$result = $this->sqllite->query($query);
			if ($result === false) {
				throw new Exception("SQLite: ".$this->sqllite->lastErrorMsg()."\nSELECT MAX(ID) FROM basedata");
			}
			$c = array_shift($result->fetchArray());
			$countArticles += $c;
		}

		return $countArticles;
	}

	public function countArticleData($tableDef) {
		$query = "SELECT COUNT(ID) FROM data_".$tableDef." ";
		$result = $this->sqllite->query($query);
		if ($result === false) {
			throw new Exception("SQLite: ".$this->sqllite->lastErrorMsg()."\nSELECT MAX(ID) FROM basedata");
		}
		$c = array_shift($result->fetchArray());


		return (int)$c;
	}


	/**
	 * @param DateTime      $logDate
	 * @param       $logText
	 * @param int   $logStage
	 * @param array $additionalData
	 */
	public function writeLog($logDate, $logText, $logStage = Ad_Import_Process_Process::LOG_INFO, $additionalData = array()) {
		$this->logCache[] = array('logDate' => $logDate, 'logText' => $logText, 'logStage' => $logStage, 'additionalData' => $additionalData);

	}

	public function writeLogToDatabase() {

		if(count($this->logCache) > 0) {
			$this->sqllite->query("BEGIN TRANSACTION");
			foreach ($this->logCache as $key => $value) {
				$this->sqllite->query("
						INSERT INTO log (LOG_DATE, LOG_LEVEL, LOG_TEXT, LOG_DATA)
						VALUES ('" . $value['logDate']->format("Y-m-d H:i:s") . "', '" . $this->sqllite->escapeString($value['logStage']) . "', '" . $this->sqllite->escapeString($value['logText']) . "', '" . $this->sqllite->escapeString(serialize($value['additionalData'])) . "')");
			}
			$result = $this->sqllite->query("COMMIT TRANSACTION");
			$this->logCache = array();
		}
	}

	public function readLog($logStage, $offset = 0, $limit = 100) {
		$query = @$this->sqllite->query("SELECT rowid,* FROM log WHERE LOG_LEVEL <= $logStage ORDER BY LOG_DATE DESC LIMIT $limit OFFSET $offset");

		$log = array();
        if (is_object($query)) {
            while($row = @$query->fetchArray(SQLITE3_ASSOC)) {
                if (!is_array($row)) {
                    break;
                }
                switch ($row["LOG_LEVEL"]) {
                    case Ad_Import_Process_Process::LOG_DEBUG:
                        $row["LOG_LEVEL"] = "Debug";
                        break;
                    case Ad_Import_Process_Process::LOG_INFO:
                        $row["LOG_LEVEL"] = "Info";
                        break;
                    case Ad_Import_Process_Process::LOG_WARNING:
                        $row["LOG_LEVEL"] = "Warning";
                        break;
                    case Ad_Import_Process_Process::LOG_ERROR:
                        $row["LOG_LEVEL"] = "Error";
                        break;
                }
                $log[$row['rowid']] = $row;
            }
        }

		return $log;
	}

	public function countLogs($logStage) {
		$query = @$this->sqllite->query("SELECT COUNT() FROM log WHERE   LOG_LEVEL <= $logStage  ");
        if (is_object($query)) {
            $arResult = @$query->fetchArray();
            if (is_array($arResult)) {
                return array_shift($arResult);
            }
        }
        return 0;
	}

	public function markBaseDataset($datasetId, $marker = Ad_Import_Process_Process::DATASET_MARKER_ERROR, $markerDetails = '') {
		$this->markerCache[] = array('ID' => $datasetId, 'MARKER' => $marker, 'MARKER_DETAILS' => $markerDetails);
	}

	public function readMarkedBaseData($marker = Ad_Import_Process_Process::DATASET_MARKER_ERROR, $offset = 0, $limit = 100) {
		$query = @$this->sqllite->query("SELECT rowid,* FROM basedata WHERE IMPORT_MARKER = $marker LIMIT $limit OFFSET $offset");

		$markerData = array();
        if (is_object($query)) {
            while($row = $query->fetchArray(SQLITE3_ASSOC)) {
                $markerData[$row['rowid']] = $row;
            }
        }

		return $markerData;
	}

	public function countMarkedBaseDataset($marker = Ad_Import_Process_Process::DATASET_MARKER_ERROR) {
		$query = @$this->sqllite->query("SELECT COUNT(ID) FROM basedata WHERE  IMPORT_MARKER = $marker  ");
        if (is_object($query)) {
            $arResult = @$query->fetchArray();
            if (is_array($arResult)) {
                return array_shift($arResult);
            }
        }
		return 0;
	}

	protected function writeMarkerToDatabase() {
		if(count($this->markerCache) > 0) {
			$this->sqllite->query("BEGIN TRANSACTION");
			foreach($this->markerCache as $key => $entry) {
				$this->sqllite->query("UPDATE basedata SET IMPORT_MARKER = '".(int)$entry['MARKER']."', IMPORT_MARKER_DETAILS = '".serialize($entry['MARKER_DETAILS'])."' WHERE ID = '".(int)$entry['ID']."'");
			}
			$result = $this->sqllite->query("COMMIT TRANSACTION");

			$this->markerCache = array();
		}

	}


	protected function getDatabaseFilename() {
		return $this->getCachePath().'/db_'.$this->importProcess->getProcessId().'.db';
	}

	protected function getCachePath() {
		return $this->importProcess->getCachePath();
	}

	function __destruct() {
		$this->writeLogToDatabase();
		$this->writeMarkerToDatabase();
	}


}