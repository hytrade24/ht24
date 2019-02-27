<?php


class Ad_Import_Preset_Type_CsvPreset extends Ad_Import_Preset_AbstractPreset {

	protected $filename;
	protected $presetType = 'Csv';


	function __construct() {
		parent::__construct();

		$this->configuration = array(
			'fileDelimiter' 			=> ';',
			'fileCharset' 				=> 'UTF-8',
			'fileNewlineBreak' 			=> '\n',
			'fileEnclosureCharacter' 	=> '"',
			'fileEscapeCharacter' 		=> '\\',
			'fileHeadlineInFirstline' 	=> true,
			'fileReadLimitPerOnce' 		=> 10000
 		);
	}

	public function getImportProcessType() {
		return 'Ad_Import_Process_Type_CsvProcess';
	}


	public function loadFile($filename) {
		$this->filename = $filename;

		$this->readCsvHeader($filename);
	}

	public function loadDataCategories() {
		parent::loadDataCategories();

		$categoryFieldIdents = array();
		if(!is_array($this->getCategoryField()) && ($this->getCategoryField() instanceof Ad_Import_Preset_Mapping_DataField)) {
			$categoryFieldIdents[$this->getCategoryField()->getIdentifier()] = 1;
		} elseif(is_array($this->getCategoryField())) {
			foreach($this->getCategoryField() as $tmpCategoryField) {
				if($tmpCategoryField instanceof Ad_Import_Preset_Mapping_DataField) {
					$categoryFieldIdents[$tmpCategoryField->getIdentifier()] = 1;
				}
			}
		}

		$fileHandler = fopen($this->filename, "r");
		$fileCharset = $this->getConfigurationOption('fileCharset');
		$i = 0;
		while($line = fgetcsv($fileHandler, 0, $this->getConfigurationOption('fileDelimiter'), $this->getConfigurationOption('fileEnclosureCharacter'), $this->getConfigurationOption('fileEscapeCharacter'))) {
			if($i == 0 && ($this->getConfigurationOption('fileHeadlineInFirstline'))) {
				$i++;
				continue;
			}

			// UTF8 BOM Detection
			$bom = pack("CCC", 0xef, 0xbb, 0xbf);
			if (0 == strncmp($line['0'], $bom, 3)) {
				$line['0'] = substr($line['0'], 3);
			}

			$categoryName = implode(' - ', array_values(array_intersect_key($line, $categoryFieldIdents)));
			if ($fileCharset !== null) {
				// Convert encoding
				$categoryName = iconv($fileCharset, 'UTF-8', $categoryName);
			}
			$this->addCategoryDataValues($categoryName);

			$i++;
		}
	}


	/**
	 * @param $filename
	 */
	protected function readCsvHeader($filename) {
		$fileHandler = fopen($filename, "r");
		$fileCharset = $this->getConfigurationOption('fileCharset');
		for ($i = 0; $i < 5; $i++) {
			$line = fgetcsv($fileHandler, 0, $this->getConfigurationOption('fileDelimiter'), $this->getConfigurationOption('fileEnclosureCharacter'), $this->getConfigurationOption('fileEscapeCharacter'));


			for ($c = 0; $c < count($line); $c++) {
				if ($i == 0) {
					// Create Header
					$dataFieldName = ($this->getConfigurationOption('fileHeadlineInFirstline') ? $line[$c] : 'C'.$c);
					if ($fileCharset !== null) {
						// Convert encoding
						$dataFieldName = iconv($fileCharset, 'UTF-8', $dataFieldName);
					}
					$dataFieldIdentifier = $c;

					$dataField = new Ad_Import_Preset_Mapping_DataField($dataFieldIdentifier, $dataFieldName, $dataFieldIdentifier);
					$this->dataFieldsByIdent[$dataFieldIdentifier] = $dataField;
					$this->dataFieldsByName[$dataFieldName] = $dataField;

				} else {
					// Add Example Data
					$dataField = $this->dataFieldsByIdent[$c];
					if($dataField instanceof Ad_Import_Preset_Mapping_DataField) {
						$dataField->addExampleData($line[$c]);
					}

				}
			}
		}
		fclose($fileHandler);
	}


	public function read($filename = null, $step = 0, $linesPerRun = null, $arOptions = null) {
		$data = array();


		if($filename == null) {
			$filename = $this->filename;
		}
		$fileHandler = fopen($filename, "r");
		if ($fileHandler === false) {
            return $data;
        }
		$fileCharset = $this->getConfigurationOption('fileCharset');

		if($linesPerRun == null) {
			$linesPerRun = (int)$this->getConfigurationOption('fileReadLimitPerOnce');
		}
		// skip lines
		for($i = 0; $i < (($step)*$linesPerRun); $i++) {
			fgets($fileHandler);
		}


		$i = 0;
		while($i < $linesPerRun) {
			$line = fgetcsv($fileHandler, 0, $this->getConfigurationOption('fileDelimiter'), $this->getConfigurationOption('fileEnclosureCharacter'), $this->getConfigurationOption('fileEscapeCharacter'));

			if($line == false || (count($line) == 1 && trim($line['0']) == '')) { $i++; continue; }

			if($step == 0 && $i == 0 && ($this->getConfigurationOption('fileHeadlineInFirstline'))) {
				$i++;
				continue;
			}
			
			// Truncate empty columns at the end
			for ($lineColumn = count($line) - 1; $lineColumn > 0; $lineColumn--) {
				if ($line[$lineColumn] === "") {
					array_pop($line);
				} else {
					// Column not empty, go ahead.
					break;
				}
			}

			if(count($line) > count($this->dataFieldsByIdent)) {
				array_splice($line, count($this->dataFieldsByIdent));
				/*
				var_dump($line, $this->dataFieldsByIdent);
				throw new Exception(Translation::readTranslation('marketplace', 'import.preset.error.filestructurenotmatch', null, array(), 'Die Struktur der Import Datei stimmt nicht mit der Vorlagenstruktur überein'));
				*/
			}

			// UTF8 BOM Detection
			$bom = pack("CCC", 0xef, 0xbb, 0xbf);
			if (0 == strncmp($line['0'], $bom, 3)) {
				$line['0'] = substr($line['0'], 3);
			}
			
			if ($fileCharset !== null) {
				// Convert encoding
				foreach ($line as $colIndex => $colContent) {
					$line[$colIndex] = iconv($fileCharset, 'UTF-8', $colContent);					
				}
			}

			$data[] = $line;

			$i++;
		}

		fclose($fileHandler);

		return $data;
	}

	public function getPresetType() {
		return $this->presetType;
	}


	public function getEstimatedNumberOfDatasets($filename = null, $arProcessOptions = array()) {
		$lines = 0;

		if($filename == null) {
			$filename = $this->filename;
		}

		$fileHandler = fopen($filename, "r");
		if ($fileHandler !== false) {
            while (!feof($fileHandler)) {
                $txt = fgets($fileHandler);
                if (trim($txt, "\t\n\r\0\x0B" . $this->getConfigurationOption('fileDelimiter')) != '') {
                    $lines++;
                }
            }

            if ($this->getConfigurationOption('fileHeadlineInFirstline')) {
                $lines--;
            }
        }

		return $lines;
	}

}