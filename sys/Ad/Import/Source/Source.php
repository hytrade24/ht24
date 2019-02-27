<?php

class Ad_Import_Source_Source {

	protected $db;
	protected $sourceId;
	protected $userId;
	protected $name;
	protected $downloadUrl;
	protected $downloadInterval;
	protected $downloadNext;
	protected $options;

    /**
     * Get specific import source by id
     * @param ebiz_db 	$db
     * @param int		$sourceId
     * @return Ad_Import_Source_Source|null
     */
    public static function getById(ebiz_db $db, $sourceId) {
        $arSource = $db->fetch1("SELECT * FROM `import_source` WHERE ID_IMPORT_SOURCE=".(int)$sourceId);
        if (is_array($arSource)) {
            return self::getByAssoc($db, $arSource);
        } else {
            return null;
        }
    }

	/**
	 * Get import source by an assoc-array containing its configuration (all fields from database)
	 * @param ebiz_db 	$db
	 * @param int		$sourceAssoc
	 * @return Ad_Import_Source_Source
	 */
	public static function getByAssoc(ebiz_db $db, $sourceAssoc) {
		return new Ad_Import_Source_Source($db, $sourceAssoc);
	}

    /**
     * Get mulitple import sources by preset
     * @param ebiz_db 	$db
     * @param int		$presetId
     * @return array
     */
    public static function getByPreset(ebiz_db $db, $presetId) {
        $arSource = $db->fetch_table("SELECT * FROM `import_source` WHERE FK_IMPORT_PRESET=".(int)$presetId);
        if (is_array($arSource)) {
            $arResult = array();
            foreach ($arSource as $sourceIndex => $arSource) {
                $arResult[] = self::getByAssoc($db, $arSource);
            }
            return $arResult;
        } else {
            return null;
        }
    }
	
	/**
	 * Initialize import source by configuration
	 * @param ebiz_db $db
	 * @param array $configuration
	 */
	function __construct(ebiz_db $db, $configuration = array()) {
		$this->db = $db;
		$this->preset = Ad_Import_Preset_PresetManagement::getInstance($db)->loadPresetById($configuration["FK_IMPORT_PRESET"]);
		$this->sourceId = (array_key_exists("ID_IMPORT_SOURCE", $configuration) ? (int)$configuration["ID_IMPORT_SOURCE"] : null);
        if ($this->preset instanceof Ad_Import_Preset_AbstractPreset) {
            $this->userId = $this->preset->getOwnerUser();
        } else {
            $this->userId = (array_key_exists("FK_USER", $configuration) ? (int)$configuration["FK_USER"] : null);
        }
		$this->name = (array_key_exists("SOURCE_NAME", $configuration) ? $configuration["SOURCE_NAME"] : "");
		$this->downloadUrl = (array_key_exists("DOWNLOAD_URL", $configuration) ? $configuration["DOWNLOAD_URL"] : null);
		$this->downloadInterval = (array_key_exists("DOWNLOAD_INTERVAL", $configuration) ? (int)$configuration["DOWNLOAD_INTERVAL"] : null);
		$this->downloadNext = (array_key_exists("DOWNLOAD_NEXT", $configuration) ? (int)$configuration["DOWNLOAD_NEXT"] : null);
		$this->options = array();
		if (array_key_exists("OPTIONS", $configuration)) {
			if (is_array($configuration["OPTIONS"])) {
				$this->options = $configuration["OPTIONS"];
			} else if ($configuration["OPTIONS"] !== null) {
				$this->options = unserialize($configuration["OPTIONS"]);
			}
		}
	}

	/**
	 * Create a new import process for the given file upload
	 * @param array		$arUpload
	 * @param array 	$configuration
	 * @return Ad_Import_Process_Process
	 */
	public function createProcessFromFile($arUpload, $configuration = array(), $cronProcess = false) {
		if (!is_array($configuration)) {
			$configuration = array();
		}
		// Move file to temp
		$sourceFile = tempnam(sys_get_temp_dir(), 'EBIZ_IMPORT_PRESET_');
		move_uploaded_file($arUpload['tmp_name'], $sourceFile);
		// Create import process
		return $this->createProcessFromTempFile($sourceFile, $configuration, $cronProcess);
	}

    /**
     * Create an we import process for the given file url
     * @param string	$url
     * @param array 	$configuration
     * @return Ad_Import_Process_Process
     */
    public function createProcessFromUrl($url, $configuration = array(), $cronProcess = false) {
        if (!is_array($configuration)) {
            $configuration = array();
        }
        // Download file to temp
        $sourceFile = tempnam(sys_get_temp_dir(), 'EBIZ_IMPORT_PRESET_');
		// Store target file within session for progress tracking
		if (!array_key_exists("_importURLTarget", $_SESSION)) {
			$_SESSION["_importURLTarget"] = array();
		}
		$_SESSION["_importURLTarget"][$this->getId()] = array(
			"file" => $sourceFile, "time" => time()
		);
		session_write_close();
		ignore_user_abort(false);
        // Try to download the import file using fopen
		file_put_contents($sourceFile, fopen($url, 'r'));
		if (!file_exists($sourceFile) || (filesize($sourceFile) == 0)) {
			// Try fallback method for downloading the import file
			$cmdWget = "/usr/bin/wget -O ".$sourceFile." ".escapeshellarg($url);
			exec($cmdWget, $execOutput, $execResult);
		}
        /*
        $curlSession = curl_init();
        curl_setopt($curlSession, CURLOPT_URL, $url);
        curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        file_put_contents($sourceFile, curl_exec($curlSession));
        */
        // Create import process
        return $this->createProcessFromTempFile($sourceFile, $configuration, $cronProcess);
    }

    /**
     * Create an we import process for the given file url
     * @param array 	$configuration
     * @return Ad_Import_Process_Process
     */
    public function createProcessWithoutFile($configuration = array(), $cronProcess = false) {
        if (!is_array($configuration)) {
            $configuration = array();
        }
        // Create import process
        return $this->createProcessFromTempFile(null, $configuration, $cronProcess);
    }

	/**
	 * Create a new import process for the given CSV/XML-File within temp
	 * @param string	$tempFile
	 * @param array 	$configuration
	 * @param bool	 	$cronProcess
	 * @return Ad_Import_Process_Process
	 */
	protected function createProcessFromTempFile($tempFile, $configuration = array(), $cronProcess = false) {
		//$configuration = array_intersect_key($configuration, array('testMode' => 1));
		
		$importProcessManagement = Ad_Import_Process_ProcessManagement::getInstance($this->db);
		$importProcess = $importProcessManagement->createNewImportProcess($tempFile, $this->userId, $this->preset, $configuration);
		$importProcess->setImportSource($this->getId());
		$importProcess->setCronProcess($cronProcess);
		$importProcess->setProcessName($this->name);
		$importProcess->setStorageContainer(array_merge($importProcess->getStorageContainer(), array(
			'PROCESS_CREATE_SUBMIT_DATA' => $_POST
		)));

		$importProcessManagement->saveProcess(null, $importProcess);
		$importProcess->firstInitialize();
		return $importProcess;
	}
	
	public function deleteFromDatabase($deleteProcesses = true) {
		$importProcessManagement = Ad_Import_Process_ProcessManagement::getInstance($this->db);
		// Delete all processes from this source
		$importProcesses = $importProcessManagement->fetchAllByParam(array(
			'FK_USER' 			=> $this->userId,
			'FK_IMPORT_SOURCE' 	=> $this->sourceId
		));
		foreach ($importProcesses as $importProcessIndex => $arImportProcess) {
			$importProcess = $importProcessManagement->loadProcessByDataset($arImportProcess);
			if ($importProcess instanceof Ad_Import_Process_Process) {
				$importProcess->cleanUp();
				$importProcessManagement->deleteImportProcess($importProcess->getProcessId());
			}
		}
		return $this->db->delete("import_source", $this->sourceId);
	}
	
	public function getId() {
		return $this->sourceId;
	}
	
	public function getUserId() {
		return $this->userId;
	}
	
	public function getPreset() {
		return $this->preset;
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function getDownloadUrl() {
		return $this->downloadUrl;
	}
	
	public function getDownloadInterval() {
		return $this->downloadInterval;
	}
	
	public function getDownloadNext() {
		return $this->downloadNext;
	}
	
	public function getOption($optionName) {
		return (array_key_exists($optionName, $this->options) ? $this->options[$optionName] : null);
	}
	
	public function getOptions() {
		return $this->options;
	}
	
	public function setDownloadNext($downloadNext) {
		$this->downloadNext = $downloadNext;
	}
	
	public function setOption($optionName, $optionValue) {
		$this->options[$optionName] = $optionValue;
	}
	
	public function setOptions($options) {
		$this->options = $options;
	}
	
	public function update(&$errors = array()) {
		if (!$this->validate($errors)) {
			return false;
		}
		if (($this->downloadInterval > 0) && ($this->downloadNext === null)) {
			$this->downloadNext = date("Y-m-d H:i:s", time() + ((int)$this->downloadInterval * 3600));
		}
		$arFields = array("FK_IMPORT_PRESET", "FK_USER", "SOURCE_NAME", "DOWNLOAD_URL", "DOWNLOAD_INTERVAL", "DOWNLOAD_NEXT", "OPTIONS");
		$arValues = array(
			$this->preset->getImportPresetId(), $this->userId, "'".mysql_real_escape_string($this->name)."'",
			($this->downloadUrl == null ? "NULL" : "'".mysql_real_escape_string($this->downloadUrl)."'"), 
			((int)$this->downloadInterval <= 0 ? "NULL" : (int)$this->downloadInterval), 
			($this->downloadNext == null ? "NULL" : "'".mysql_real_escape_string($this->downloadNext)."'"), 
			(empty($this->options) ? "NULL" : "'".mysql_real_escape_string(serialize($this->options))."'")
		);
		$arUpdate = array(
			"FK_IMPORT_PRESET=".$this->preset->getImportPresetId(), "FK_USER=".$arValues[1], "SOURCE_NAME=".$arValues[2], 
			"DOWNLOAD_URL=".$arValues[3], "DOWNLOAD_INTERVAL=".$arValues[4], "DOWNLOAD_NEXT=".$arValues[5], "OPTIONS=".$arValues[6]
		);
		if ($this->sourceId > 0) {
			array_unshift($arFields, "ID_IMPORT_SOURCE");
			array_unshift($arValues, $this->sourceId);
		}
		$query = "
			INSERT INTO `import_source` (".implode(", ", $arFields).") VALUES (".implode(", ", $arValues).")
			ON DUPLICATE KEY UPDATE ".implode(", ", $arUpdate);
		$result = $this->db->querynow($query);
		if ($result["rsrc"]) {
			if ($this->sourceId === null) {
				$this->sourceId = $this->db->fetch_atom("SELECT LAST_INSERT_ID();");
			}
			return true;
		} else {
			return false;
		}
	}
	
	public function validate(&$errors = array()) {
		if (empty($this->name)) {
			$errors[] = "NAME_REQUIRED";
		}
		return empty($errors);
	}

}