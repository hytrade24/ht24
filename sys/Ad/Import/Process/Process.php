<?php

class Ad_Import_Process_Process {

	const STATUS_PRE = 0;
	const STATUS_INFRASTRUCTURE = 1;
	const STATUS_LOAD = 2;
	const STATUS_LOAD_READY = 3;
	const STATUS_TRANSFORM = 4;
	const STATUS_TRANSFORM_READY = 5;
	const STATUS_VALIDATE = 6;
	const STATUS_VALIDATE_READY = 7;
	const STATUS_IMPORT = 8;
	const STATUS_IMPORT_READY = 9;

	const STATUS_FINISH = 100;
	const STATUS_ERROR= 500;
	const STATUS_COMPLETE = 1000;

	const LOG_DEBUG = 5;
	const LOG_INFO = 4;
	const LOG_WARNING = 3;
	const LOG_ERROR = 1;

	const DATASET_MARKER_SUCCESS = 1;
	const DATASET_MARKER_ERROR = 2;



	/** @var  Ad_Import_Preset_AbstractPreset */
	protected $preset;
	/** @var Ad_Import_Process_Infrastructure_InfrastructureManagement  */
	protected $infrastructure;
	/** @var Ad_Import_Process_Transform_TransformManagement  */
	protected $transform;
	/** @var Ad_Import_Process_Validation_ValidationManagement  */
	protected $validation;
	/** @var Ad_Import_Process_Import_ImportManagement  */
	protected $import;

	protected $userId;
	protected $processId = null;
	protected $processName = '';
	protected $sourceFile;
	protected $importSourceId = null;

	protected $currentProgressState;

	protected $estimatedProgessState = 0;
	protected $estimatedProgressEnd;
	protected $status = 0;
	protected $statusName = '';

	protected $statsNewArticles = 0;
	protected $statsUpdatedArticles = 0;
	protected $statsDeletedArticles = 0;
	protected $statsPausedArticles = 0;
	protected $statsStartedArticles = 0;

	protected $statsNewProducts = 0;
	
	protected $configuration = array();

	protected $storageContainer = array();


	/**
	 * Init Import Process
	 *
	 * @param $processId
	 * @param $source
	 * @param $userId
	 * @param $preset
	 */
	function __construct($source, $userId, $preset, $configuration = array()) {
		$this->preset = $preset;
		$this->userId = $userId;
		$this->sourceFile = $source;

		$this->configuration = array_merge(array(
			'limitPerRun' => 5000,				// Anzahl der zu verarbeitenen Datensätzen pro Durchlauf
			'testMode' => false,				// validiert alles, importiert aber nicht
			'importCompleteOrWait' => true 		// Wenn true => Importiert alle Datensätze vollständig oder (falls nicht ausreichen Anzeigen verfügbar) gar nicht
		), $configuration);
	}

	protected function initializeObject() {
		$this->infrastructure = new Ad_Import_Process_Infrastructure_InfrastructureManagement($this);
		$this->transform = new Ad_Import_Process_Transform_TransformManagement($this);
		$this->validation = new Ad_Import_Process_Validation_ValidationManagement($this);
		$this->import = new Ad_Import_Process_Import_ImportManagement($this);
	}


	/**
	 * Method Run() can be executed as cron and do the import work based on the current status
	 *
	 * 1. Init
	 * 2. Set Up Infrastructure
	 * 3. Load Data
	 * 4. Transform Data
	 * 5. Validate
	 * 6. Import
	 *
	 */
	public function run() {
		try {
			//$this->log(Translation::readTranslation('marketplace', 'import.process.run.debug', NULL, array(), 'Starte Import Aufgabe Prozess'), self::LOG_DEBUG);

			switch ($this->getStatus()) {
				case self::STATUS_PRE:
					$this->initialize();
					$this->setStatus(self::STATUS_INFRASTRUCTURE);
					$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.ini', NULL, array(), 'Initialisieren'));
					$this->setEstimatedProgessState(1);
					break;

				case self::STATUS_INFRASTRUCTURE:
					$this->setStatus(self::STATUS_LOAD);
					$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.infrastructure', NULL, array(), 'Infrastruktur erzeugen'));
					$this->setEstimatedProgessState(10);
					$this->setCurrentProgressState(0);
					break;

				case self::STATUS_LOAD:
					$this->load();
					$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.load', NULL, array(), 'Daten einlesen'));
					break;
				case self::STATUS_LOAD_READY:
					$this->setCurrentProgressState(0);
					$this->setStatus(self::STATUS_TRANSFORM);
					$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.loadready', NULL, array(), 'Daten einlesen fertiggestellt'));
					$this->setEstimatedProgessState(30);
					break;

				case self::STATUS_TRANSFORM:
					$this->runTransform();
					$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.transform', NULL, array(), 'Daten transformieren'));
					break;

				case self::STATUS_TRANSFORM_READY:
					$this->setCurrentProgressState(0);
					$this->setStatus(self::STATUS_VALIDATE);
					$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.transformready', NULL, array(), 'Daten transformieren fertiggestellt'));
					$this->setEstimatedProgessState(50);
					break;

				case self::STATUS_VALIDATE:
					$this->runValidate();
					$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.validate', NULL, array(), 'Daten überprüfen'));
					break;

				case self::STATUS_VALIDATE_READY:
					$this->setCurrentProgressState(0);
					$this->setStatus(self::STATUS_IMPORT);
					$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.validateready', NULL, array(), 'Daten überprüfen fertiggestellt'));
					$this->setEstimatedProgessState(70);
					break;

				case self::STATUS_IMPORT:
					$this->runImport();
					$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.import', NULL, array(), 'Daten importieren'));
					break;

				case self::STATUS_IMPORT_READY:
					$this->setCurrentProgressState(0);
					$this->setStatus(self::STATUS_FINISH);
					$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.importready', NULL, array(), 'Daten importieren fertiggestellt'));
					$this->setEstimatedProgessState(90);
					break;

				case self::STATUS_FINISH:
					$this->setCurrentProgressState(0);
					$this->finish();
					$this->setStatus(self::STATUS_COMPLETE);
					$this->setStatusName(Translation::readTranslation('marketplace','import.process.statusname.finish',NULL,array(),'Import vollständig abgeschlossen'));

					$this->setEstimatedProgessState(100);

					break;


			}
		} catch(Exception $e) {
			$this->setStatus(self::STATUS_ERROR);
			$this->setStatusName(Translation::readTranslation('marketplace', 'import.process.statusname.error', NULL, array(), 'Fehler'));
			$this->log($e->getMessage(), self::LOG_ERROR);
			eventlog("error", "Fehler beim Import!", $e->getMessage());
		}
	}

	public function firstInitialize() {
		$this->initializeObject();
		$this->infrastructure->createLogTable();

		$this->createInfrastructure();
	}

	/**
	 * Initialize Process
	 */
	public function initialize() {
		$numberOfDatasets = $this->preset->getEstimatedNumberOfDatasets($this->sourceFile, $this->configuration);
        $this->configuration["_presetReadOptions"] = $this->preset->getReadOptions($this->configuration);
		$this->setEstimatedProgressEnd($numberOfDatasets);

        unset($this->configuration["_loadCountPerCall"]);

		$this->log(Translation::readTranslation('marketplace', 'import.process.run.debug.pre', null, array(), 'Import wurde gestartet'), self::LOG_INFO);
		
		// Trigger plugin event
		$paramAdImport = new Api_Entities_EventParamContainer(array("importProcess" => $this));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_IMPORT_START, $paramAdImport);
	}

	/**
	 * Finish Import and Clean up
	 */
	public function finish() {
		$tpl_temp = $GLOBALS["tpl_main"];
		if (!$tpl_temp instanceof Template) {
			$tpl_temp = new Template("tpl/de/empty.htm");
		}
		if($this->getConfigurationOption('testMode') == true) {
			global $tpl_main;
			if ($this->statsNewProducts > 0) {
                $this->log(
                    Translation::readTranslation(
                        'marketplace',
                        'import.process.log.finish.stastics.test.products',
                        NULL,
                        array(
                            'NEW' => "'".$this->statsNewProducts."'"
                        ),
                        'Produkt-Datenbank: {NEW} neue Produkte'.
                        '<br /><br /><b>Import wurde simuliert (Testmodus ist aktiviert).</b> Es wurden keine Daten importiert.'
                    ),
                    self::LOG_INFO
                );
            }
			$this->log(
				Translation::readTranslation(
					'marketplace',
					'import.process.log.finish.stastics.test',
					NULL,
					array(
						'NEW' => "'".$this->statsNewArticles."'",
						'UPDATED' => "'".$this->statsUpdatedArticles."'",
						'DELETED' => "'".$this->statsDeletedArticles."'",
						'PAUSED' => "'".$this->statsPausedArticles."'",
						'STARTED' => "'".$this->statsStartedArticles."'",
						'RECREATE_URL' => "'".$tpl_temp->tpl_uri_action("my-import-run")."?DO=RECREATE&ID_IMPORT_PROCESS=".$this->getProcessId()."'"
					),
					'<b>Import wurde erfolgreich abgeschlossen</b><br>Statistiken: {NEW} neue, {UPDATED} aktualisierte, {DELETED} gelöschte, {PAUSED} pausierte, {STARTED} erneut aktivierte Artikel'.
					'<br /><br /><b>Import wurde simuliert (Testmodus ist aktiviert).</b> Es wurden keine Daten importiert<br>Möchten Sie jetzt <a href="{RECREATE_URL}">die Daten in das Live System importieren</a>?'
				),
				self::LOG_INFO
			);
		} else {
			if ($this->statsNewProducts > 0) {
                $this->log(
                    Translation::readTranslation(
                        'marketplace',
                        'import.process.log.finish.stastics.products',
                        NULL,
                        array(
                            'NEW' => "'".$this->statsNewProducts."'"
                        ),
                        'Produkt-Datenbank: {NEW} neue Produkte.'
                    ),
                    self::LOG_INFO
                );
            }
			$this->log(
				Translation::readTranslation(
					'marketplace',
					'import.process.log.finish.stastics',
					NULL,
					array(
						'NEW' => "'".$this->statsNewArticles."'",
						'UPDATED' => "'".$this->statsUpdatedArticles."'",
						'DELETED' => "'".$this->statsDeletedArticles."'",
						'PAUSED' => "'".$this->statsPausedArticles."'",
						'STARTED' => "'".$this->statsStartedArticles."'",
						'URL' => "'".$tpl_temp->tpl_uri_action("my-marktplatz,active")."?FK_IMPORT_SOURCE=".(int)$this->importSourceId."'"
					),
					'<b>Import wurde erfolgreich abgeschlossen</b><br>Statistiken: {NEW} neue, {UPDATED} aktualisierte, {DELETED} gelöschte, {PAUSED} pausierte, {STARTED} erneut aktivierte Artikel'.
					'<br /><a href="{URL}" target="_blank">Importierte Anzeigen einsehen/bearbeiten</a>'),
				self::LOG_INFO
			);
		}

		// clean up
		//exec("rm -rf ".$this->getCachePath());

		// Trigger plugin event
		$paramAdImport = new Api_Entities_EventParamContainer(array("importProcess" => $this));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_IMPORT_FINISH, $paramAdImport);

	}

	/**
	 * Create Needed Infrastructure, Database Tables and so on
	 */
	public function createInfrastructure() {
		$this->log(Translation::readTranslation('marketplace', 'import.process.run.debug.infra', null, array(), 'Führe Import Aufgabe "Infrastruktur Erzeugung" aus'), self::LOG_DEBUG);

		$this->infrastructure->createArticleTables();
		$this->infrastructure->createBasedataTable();

	}

	/**
	 * Loads Data from sourcefile into Process Database
	 */
	public function load() {
		$this->log(Translation::readTranslation('marketplace', 'import.process.run.debug.load', null, array('OPTIONS' => '"'.json_encode($this->configuration["_presetReadOptions"]).'"'),
            'Führe Import Aufgabe "Daten Einlesen" aus. Optionen: {OPTIONS}'), self::LOG_DEBUG);

		if($this->getStatus() == self::STATUS_LOAD) {

			$data = $this->preset->read($this->sourceFile, $this->getCurrentProgressState(), null, $this->configuration["_presetReadOptions"]);
			if(count($data) > 0) {
                $countPerCall = count($data);
                if (!array_key_exists("_loadCountPerCall", $this->configuration)) {
                    $this->configuration["_loadCountPerCall"] = $countPerCall;
                } else {
                    $countPerCall = $this->configuration["_loadCountPerCall"];
                }
                $logMessage = Translation::readTranslation(
                    'marketplace', 'import.process.run.debug.loaded', null,
                    array(
                        'COUNT' => '"'.count($data).'"', 'COUNT_MAX' => '"'.$this->getEstimatedProgressEnd().'"',
                        'PRECENT' => '"'.floor(($this->getCurrentProgressState() + 1) * 100 / ($this->getEstimatedProgressEnd() / $countPerCall)).'"'
                    ),
                    '{COUNT} Datensätze eingelesen: {PRECENT}% ({COUNT_MAX} Datensätze insgesamt)'
                );
                $this->log($logMessage, self::LOG_DEBUG);

				$this->infrastructure->loadDataIntoBasedataTable($data);
				$this->setCurrentProgressState($this->getCurrentProgressState() + 1);

				// progess
				$p = 10 + (min(1, ($this->getCurrentProgressState() + 1) * ($countPerCall / $this->getEstimatedProgressEnd())) * 20);
				$this->setEstimatedProgessState($p);
			} else {
				// finish
				$this->setStatus(self::STATUS_LOAD_READY);
			}
		}
	}

	/**
	 * Transforms Data based on rules in preset and saves them into process database
	 */
	protected function runTransform() {
		$this->log(Translation::readTranslation('marketplace', 'import.process.run.debug.transform', null, array(), 'Führe Import Aufgabe "Daten transformieren" aus'), self::LOG_DEBUG);

		$readerLimit = (int)$this->getConfigurationOption('limitPerRun');
		if ($readerLimit > 5000) {
		    $readerLimit = 5000;
        }


		$c = 0;
		$transformedDataByArticleTable = array();
		$transformQuery =  $this->infrastructure->fetchBasedataQuery($readerLimit, $this->getCurrentProgressState());
        if ($transformQuery === false) {
            throw new Exception("Failed to read data from transform table!");
        }
		while($dataToTransform = $transformQuery->fetchArray(SQLITE3_ASSOC)) {
			$transformedData = $this->transform->transformData($dataToTransform);

			if($transformedData != null) {
				$transformedDataByArticleTable[$transformedData['KAT_TABLE']][] = $transformedData;
			}

			$c++;
		}

		// Insert in Article Data Tables
		foreach($transformedDataByArticleTable as $categoryTable => $data) {
			$this->infrastructure->insertArticleData($categoryTable, $data);
		}

		// progess
		$p = 30 + (min(1, (($this->getCurrentProgressState() + $c) / $this->getEstimatedProgressEnd())) * 20);
		$this->setEstimatedProgessState($p);

		if($c == 0) {
			$this->setStatus(self::STATUS_TRANSFORM_READY);
		}

		$this->setCurrentProgressState($this->getCurrentProgressState() + $c);
		// Save possible changes to the preset
		Ad_Import_Preset_PresetManagement::getInstance($GLOBALS["db"])->savePreset($this->preset->getImportPresetId(), $this->preset);
	}

	/**
	 * Validates data and deletes invalid datasets
	 */
	protected function runValidate() {


		$this->log(Translation::readTranslation('marketplace', 'import.process.run.debug.validate', null, array(), 'Führe Import Aufgabe "Daten validieren" aus'), self::LOG_DEBUG);

		$readerLimit = (int)$this->getConfigurationOption('limitPerRun');
		if ($readerLimit > 5000) {
		    $readerLimit = 5000;
        }

		$c = 0;
		$offset = $this->getCurrentProgressState();
		$totalSkipped = 0;
		$totalDeleted = 0;
		$totalArticles = 0;

		//pre count articles
		foreach($this->preset->getTableFieldsByTableDef() as $tableDef => $fields) {
			$countArticleTable = $this->infrastructure->countArticleData($tableDef);
			$totalArticles += $countArticleTable;
		}

	#	echo "current progress: ". $this->getCurrentProgressState()."<br>";
		foreach($this->preset->getTableFieldsByTableDef() as $tableDef => $fields) {

			if($c >= $readerLimit) { break;	}

			$countArticleTable = $this->infrastructure->countArticleData($tableDef);
			#echo "count article table: ".$countArticleTable."<br>";
			if($offset >= $countArticleTable) {
				$totalSkipped += $countArticleTable;
				$offset = $this->getCurrentProgressState() - $totalSkipped;
				continue;
			}


			#echo "read from ".$tableDef." " .($readerLimit - $c)." ds offset ".$offset."<br>";
			$readerQuery =  $this->infrastructure->fetchArticleDataQuery($tableDef, $readerLimit - $c, $offset);
			$invalidData = array();

			while($dataToValidate = $readerQuery->fetchArray(SQLITE3_ASSOC)) {
				$valid = $this->validation->validate($dataToValidate, $tableDef);
				if(!$valid) {
					$invalidData[] = $dataToValidate['ID'];
				}
				$c++;
			}

			if(count($invalidData)) {
				$this->log(Translation::readTranslation('marketplace', 'import.process.run.validate.deleteinvalid', null, array('COUNT' => "'".count($invalidData)."'"), 'Es wurden {COUNT} ungültige Datensätze entfernt'), self::LOG_INFO);
				$this->infrastructure->massDeleteArticleData($tableDef, $invalidData);
				$totalDeleted += count($invalidData);
			}
		}

		$p = 50 + (min(1, (($this->getCurrentProgressState() + $c) / max(1, $totalArticles))) * 20);
		$this->setEstimatedProgessState($p);


		#echo("c:".$c."<br>");
		if($c == 0) {
			$this->setStatus(self::STATUS_VALIDATE_READY);
		}

		$this->setCurrentProgressState($this->getCurrentProgressState() + $c - $totalDeleted);


	}


	/**
	 * Import data
	 */
	protected function runImport() {
		$this->log(Translation::readTranslation('marketplace', 'import.process.run.debug.import', null, array(), 'Führe Import Aufgabe "Import der Daten" aus'), self::LOG_DEBUG);

		$timeLimit = time() + 120;
		$memLimit = 50 * 1024 * 1024;
		if (preg_match('/^(\d+)(.)$/', ini_get('memory_limit'), $matches)) {
            $memLimit = Tools_Utility::configSizeToBytes($matches[0]);
		}
		$readerLimit = (int)$this->getConfigurationOption('limitPerRun');
		if ($readerLimit > 5000) {
		    $readerLimit = 5000;
        }

		// check before import
		if($this->getCurrentProgressState() == 0 && $this->getConfigurationOption('importCompleteOrWait') == true) {
			$this->import->preCheckAll();
		}

		$this->import->prepareImport();

		$c = 0;
		$offset = $this->getCurrentProgressState();
		$totalSkipped = 0;

		foreach($this->preset->getTableFieldsByTableDef() as $tableDef => $fields) {

			if($c >= $readerLimit) { break;	}

			$countArticleTable = $this->infrastructure->countArticleData($tableDef);
			if($offset >= $countArticleTable) {
				$totalSkipped += $countArticleTable;
				$offset = $this->getCurrentProgressState() - $totalSkipped;
				continue;
			}

			$identifiersQuery = $this->infrastructure->fetchImportIdentifiers($tableDef, $readerLimit - $c, $offset);
			$identifiers = array();
			while($dataToImport = $identifiersQuery->fetchArray(SQLITE3_ASSOC)) {
			    $identifiers[] = '"'.mysql_real_escape_string($dataToImport["IMPORT_IDENTIFIER"]).'"';
            }
			$this->import->preloadImportIdentifiers($identifiers, $tableDef);
			$readerQuery =  $this->infrastructure->fetchArticleDataQuery($tableDef, $readerLimit - $c, $offset);

			while($dataToImport = $readerQuery->fetchArray(SQLITE3_ASSOC)) {
				$import = $this->import->import($dataToImport, $tableDef);
				$c++;
				// Check memory usage
				if(($c % 100) == 0) {
					$memUsage = memory_get_usage() * 100 / $memLimit;
					if (time() > $timeLimit) {
						// Over 2 minutes runtime! Stop now!
						break 2;
					}
					if ($memUsage > 75) {
						// Over 75% memory usage! Stop now!
						break 2;
					}
				}
			}

			$offset = 0;
		}


		$this->import->finishImport();

		$p = 70 + (min(1, (($this->getCurrentProgressState() + $c) / $this->getEstimatedProgressEnd())) * 20);
		$this->setEstimatedProgessState($p);



		if($c == 0) {
			$this->setStatus(self::STATUS_IMPORT_READY);
		}

		$this->setCurrentProgressState($this->getCurrentProgressState() + $c);
		
	}


	public function log($logText, $logStage = Ad_Import_Process_Process::LOG_INFO, $additionalData = array()) {
		$this->infrastructure->writeLog(new DateTime(), $logText, $logStage, $additionalData);

		return $logText;
	}

	public function readLog($logStage = Ad_Import_Process_Process::LOG_INFO, $offset = 0, $limit = 100) {
		$logs = $this->infrastructure->readLog($logStage, $offset, $limit);
		return $logs;
	}


	public function markBaseDataset($datasetId, $marker = self::DATASET_MARKER_ERROR, $markerDetails = '') {
		return $this->infrastructure->markBaseDataset($datasetId, $marker, $markerDetails);
	}


	/**
	 * @return mixed
	 */
	public function getProcessId() {
		return $this->processId;
	}

	/**
	 * @param mixed $processId
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setProcessId($processId) {
		$this->processId = $processId;

		return $this;
	}



	/**
	 * @return mixed
	 */
	public function getUserId() {
		return $this->userId;
	}

	/**
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function getPreset() {
		return $this->preset;
	}

	/**
	 * @return mixed
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param mixed $status
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setStatus($status) {
		$this->status = $status;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getCurrentProgressState() {
		return $this->currentProgressState;
	}

	/**
	 * @param mixed $currentProgressState
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setCurrentProgressState($currentProgressState) {
		$this->currentProgressState = $currentProgressState;

		return $this;
	}

	public function getConfigurationOption($optionName) {
		return (array_key_exists($optionName, $this->configuration)?$this->configuration[$optionName]:null);
	}

	public  function setConfigurationOption($optionName, $value) {
		$this->configuration[$optionName] = $value;
	}

	/**
	 * @return Ad_Import_Process_Infrastructure_InfrastructureManagement
	 */
	public function getInfrastructure() {
		return $this->infrastructure;
	}

	public function getCachePath() {
		global $ab_path;

		$path = $ab_path.'cache/import/'.$this->getProcessId().'-'.md5($this->getProcessId().$this->getUserId());
		if(!is_dir($path)) {
			mkdir($path);
		}

		return $path;
	}

	public static function getCachePathStatic(&$arImportProcess) {
		global $ab_path;

		$path = $ab_path.'cache/import/'.$arImportProcess["ID_IMPORT_PROCESS"].'-'.md5($arImportProcess["ID_IMPORT_PROCESS"].$arImportProcess["FK_USER"]);
		if(!is_dir($path)) {
			mkdir($path);
		}

		return $path;
	}

	public function cleanUp() {
		system("rm -r ".$this->getCachePath());
	}
	
	public function getImportSource() {
		return $this->importSourceId;
	}
	
	public function setImportSource($importSourceId) {
		$this->importSourceId = (int)$importSourceId;
		return $this;
	}

	public function getCronProcess() {
		return $this->cronProcess;
	}
	
	public function setCronProcess($cronProcess) {
		$this->cronProcess = ($cronProcess ? 1 : 0);
		return $this;
	}
	
	/**
	 * @return string
	 */
	public function getProcessName() {
		return $this->processName;
	}

	/**
	 * @param string $processName
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setProcessName($processName) {
		$this->processName = $processName;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getEstimatedProgessState() {
		return $this->estimatedProgessState;
	}

	/**
	 * @param int $estimatedProgessState
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setEstimatedProgessState($estimatedProgessState) {
		$this->estimatedProgessState = round($estimatedProgessState,2);

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getEstimatedProgressEnd() {
		return $this->estimatedProgressEnd;
	}

	/**
	 * @param mixed $estimatedProgressEnd
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setEstimatedProgressEnd($estimatedProgressEnd) {
		$this->estimatedProgressEnd = $estimatedProgressEnd;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getStatusName() {
		return $this->statusName;
	}

	/**
	 * @return int
	 */
	public function getStatsDeletedArticles() {
		return $this->statsDeletedArticles;
	}

	/**
	 * @param int $statsDeletedArticles
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setStatsDeletedArticles($statsDeletedArticles) {
		$this->statsDeletedArticles = $statsDeletedArticles;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getStatsNewArticles() {
		return $this->statsNewArticles;
	}

	/**
	 * @param int $statsNewArticles
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setStatsNewArticles($statsNewArticles) {
		$this->statsNewArticles = $statsNewArticles;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getStatsPausedArticles() {
		return $this->statsPausedArticles;
	}

	/**
	 * @param int $statsPausedArticles
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setStatsPausedArticles($statsPausedArticles) {
		$this->statsPausedArticles = $statsPausedArticles;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getStatsStartedArticles() {
		return $this->statsStartedArticles;
	}

	/**
	 * @param int $statsStartedArticles
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setStatsStartedArticles($statsStartedArticles) {
		$this->statsStartedArticles = $statsStartedArticles;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getStatsUpdatedArticles() {
		return $this->statsUpdatedArticles;
	}

	/**
	 * @param int $statsUpdatedArticles
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setStatsUpdatedArticles($statsUpdatedArticles) {
		$this->statsUpdatedArticles = $statsUpdatedArticles;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getStatsNewProducts() {
		return $this->statsNewProducts;
	}

	/**
	 * @param int $statsNewProducts
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setStatsNewProducts($statsNewProducts) {
		$this->statsNewProducts = $statsNewProducts;

		return $this;
	}




	/**
	 * @param string $statusName
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setStatusName($statusName) {
		$this->statusName = $statusName;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getStorageContainer() {
		return $this->storageContainer;
	}

	/**
	 * @param array $storageContainer
	 *
	 * @return Ad_Import_Process_Process
	 */
	public function setStorageContainer($storageContainer) {
		$this->storageContainer = $storageContainer;

		return $this;
	}




	function __wakeup() {
		$this->initializeObject();
	}


	function __sleep() {

		return array('preset', 'userId', 'processId', 'sourceFile', 'currentProgressState', 'estimatedProgessState','estimatedProgressEnd', 'status', 'configuration', 'statusName', 'processName', 'statsNewArticles', 'statsUpdatedArticles', 'statsDeletedArticles', 'statsPausedArticles', 'statsStartedArticles', 'statsNewProducts', 'storageContainer', 'importSourceId', 'cronProcess');
	}


}