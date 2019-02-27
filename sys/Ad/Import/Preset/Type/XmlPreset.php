<?php


class Ad_Import_Preset_Type_XmlPreset extends Ad_Import_Preset_AbstractPreset {

	protected $filename;
	protected $presetType = 'Xml';


	function __construct() {
		parent::__construct();

		$this->configuration = array(
			'fileCharset' 			=> 'UTF-8',
			'fileReadLimitPerOnce' => 10000
 		);
	}

	public function getImportProcessType() {
		return '';
	}


	public function loadFile($filename) {
		$this->filename = $filename;

		$this->readXmlStructure($filename);
	}

	public function loadDataCategories() {
		parent::loadDataCategories();

		$me = $this;
		$categoryField = $this->getTableFieldByName('FK_KAT');
		$categoryFieldMapping = $this->getFieldMappingByTableField('FK_KAT');

		if($categoryFieldMapping != null) {
			$this->iterate($this->filename, 0, -1, false, function($line) use ($me, $categoryFieldMapping) {
				$me->addCategoryDataValues($categoryFieldMapping->execute($line));
			}, false);
		}

	}


	/**
	 * @param $filename
	 */
	protected function readXmlStructure($filename) {
		$me = $this;
		$this->iterate($filename, 0, -1, false, null, function($nodeIdent, $nodeName, $nodeValue) use($me) {
			$me->addDataField($nodeIdent, $nodeName, $nodeValue);
		});
	}


	public function read($filename = null, $step = 0, $linesPerRun = null, $arOptions = null) {
		$data = $this->iterate($filename, $step, $linesPerRun);

		return $data;
	}

	public function iterate($filename = null, $step = 0, $linesPerRun = null, $optionCollectData = true, $rowCallback = null, $elementCallback = null) {
		$data = array();
		$rootElement = null;
		$itemElement = null;

		if($filename == null) {
			$filename = $this->filename;
		}
		$fileHandler = fopen($filename, "r");

		if($linesPerRun == null) {
			$linesPerRun = (int)$this->getConfigurationOption('fileReadLimitPerOnce');
		}

		$xmlReader = new XMLReader;
		$xmlReader->open($filename);

		// move to the first <product /> node
		while ($xmlReader->read()) {
			if($xmlReader->depth == 0) {
				$rootElement = $xmlReader->name;
				continue;
			} elseif($xmlReader->depth == 1) {
				$itemElement = $xmlReader->name;
				break;
			}
			throw new Exception("Could not read xml structure while detect root element");
		};

		$xmlPath = array($rootElement, $itemElement);

		// skip lines
		for($i = 0; $i < (($step)*$linesPerRun); $i++) {
			$xmlReader->next();
		}


		$i = 0;
		$line = array();

		while($i < $linesPerRun || ($linesPerRun == -1)) {

			if($xmlReader->nodeType == XMLReader::ELEMENT) {

				if(count($xmlPath) > $xmlReader->depth) {
					$xmlPath = array_slice($xmlPath, 0, $xmlReader->depth);
				} elseif(count($xmlPath) < $xmlReader->depth) {
					throw new Exception("Could not read xml structure while iterating throug elements. There is an gap in depth");
				}

				$xmlPath[] = $xmlReader->name;

				if($xmlReader->hasAttributes) {
					while($xmlReader->moveToNextAttribute()) {
						$attributeIdent = implode('/', $xmlPath).':'.$xmlReader->name;
						$attributeName = implode('/', $xmlPath).'['.$xmlReader->name.'] (Attribut)';

						$line[md5($attributeIdent)] = $xmlReader->value;

						if($elementCallback != null) {
							call_user_func($elementCallback, $attributeIdent, $attributeName, $xmlReader->value);
						}
					}
				}

			} elseif (($xmlReader->nodeType == XMLReader::TEXT) || ($xmlReader->nodeType == XMLReader::CDATA)) {
				if($xmlReader->hasValue) {
					$nodeIdent = implode('/', $xmlPath);
					$nodeName = implode('/', array_slice($xmlPath, 2));

					$line[md5($nodeIdent)] = $xmlReader->value;
					if($elementCallback != null && is_callable($elementCallback)) {
						$elementCallback($nodeIdent, $nodeName, $xmlReader->value);
					}
				}
			}

			$xmlReader->read();

			if($xmlReader->nodeType == XMLReader::ELEMENT && $xmlReader->depth == 1) {
				$i++;
				if($optionCollectData == true) {
					$data[] = $line;
				}

				if($rowCallback != null && is_callable($rowCallback)) {
					$rowCallback($line);
				}

				$line = array();
			}
			if($xmlReader->nodeType == XMLReader::NONE) {
				break;
			}

		}


		$xmlReader->close();

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

		$xmlReader = new XMLReader;
		$xmlReader->open($filename);

		while($xmlReader->read() && $xmlReader->depth < 1) { }

		while($xmlReader->nodeType == XMLReader::ELEMENT && $xmlReader->depth == 1) {
			$lines++;
			$xmlReader->next();
		}

		return $lines;
	}

	/**
	 * @param $nodeIdent
	 * @param $nodeName
	 * @param $value
	 */
	public function addDataField($nodeIdent, $nodeName, $value) {
		$nodeDescription = $nodeIdent;
		$nodeIdent = md5($nodeIdent);

		if (!array_key_exists($nodeIdent, $this->dataFieldsByIdent)) {
			$dataField = new Ad_Import_Preset_Mapping_DataField($nodeIdent, $nodeName, $nodeDescription);
			$this->dataFieldsByIdent[$nodeIdent] = $dataField;
			$this->dataFieldsByName[$nodeName] = $dataField;

			$dataField->addExampleData($value);
		} else {
			/** @var Ad_Import_Preset_Mapping_DataField $dataField */
			$dataField = $this->dataFieldsByIdent[$nodeIdent];
			if (count($dataField->getExampleData()) < 5) {
				$dataField->addExampleData($value);
			}
		}
	}

}