<?php
require_once $ab_path.'sys/lib.pub_kategorien.php';


abstract class Ad_Import_Preset_AbstractPreset {

	protected $importPresetId = null;
	protected $isStored = false;
	protected $ownerUser = null;
	protected $isGlobal = false;
	protected $status = Ad_Import_Preset_PresetManagement::STATUS_ENABLED;

	protected $presetName;

	/** @var  array|Ad_Import_Preset_Mapping_DataField */
	protected $categoryField;
	protected $categoryDataValues = array();
	protected $categoryMapping = array();
	protected $categoryUsage = array();

	protected $dataFieldsByName = array();
	protected $dataFieldsByIdent = array();
	protected $tableFieldsByFName = array();
	protected $tableFieldsByTableDef = array();
	protected $fieldMapping = array();

	protected $configuration = array();
	protected $configurationTemplates = array();

	/** @var Ad_Import_Preset_PresetManagement */
	protected $importPresetManagement = null;

	function __construct() {
		global $db;
		$this->importPresetManagement = Ad_Import_Preset_PresetManagement::getInstance($db);
		// Allow to alter default options / add input fields
		Api_TraderApiHandler::getInstance($GLOBALS['db'])->triggerEvent(
			Api_TraderApiEvents::IMPORT_PRESET_CREATE, $this
		);
	}


	abstract public function loadFile($filename);
	abstract public function getImportProcessType();
	abstract public function read($filename = null, $step = 0, $linesPerRun = null, $arOptions = null);
	abstract public function getEstimatedNumberOfDatasets($filename = null, $arProcessOptions = array());

	public function getAutoCreateSource() {
		return false;
	}
	
	public function getStepMax() {
			return 5;
	}

	public function doResetOnSave() {
		return ($this->importPresetId > 0 ? false : true);
	}

	public function doRequireFile() {
			return true;
	}

	public function doRequirePreperation() {
			return false;
	}

	public function finishPreperation() {
	
	}
	
	public function loadCustom() {
		
	}

	/**
	 * Allows to add custom templates before the actual step content
	 * @param $step
	 * @return bool
	 */
	public function getStepSpecial($step, $sourceId = null) {
		return false;
	}
	
	/**
	 * Loads a source File from a url
	 *
	 * @param $url
	 *
	 * @throws Exception
	 */
	public function loadFileUrl($url) {
		$urlResource = fopen($url, 'r');
		if($urlResource == false) {
			throw new Exception("Could not load Url $url ");
		}

		$tempFile = tempnam(sys_get_temp_dir(), 'EBIZ_IMPORT_PRESET_');
		file_put_contents($tempFile, $urlResource);

		$this->loadFile($tempFile);
	}


	/**
	 * Erzeugt TableField Objekte aus der Datenbank
	 */
	public function loadTableFields() {
		$this->tableFieldsByTableDef = array();
		$tableFields = $this->importPresetManagement->getTableFields();
		
		foreach($tableFields as $key => $tableFieldData) {
			// Split description fields
			list($tableFieldData["T1"], $tableFieldData["T2"], $tableFieldData["T3"]) = explode("§§§", $tableFieldData["T1"]);
			list($tableFieldData["T1_DESC"], $tableFieldData["T1_HELP"]) = explode("||", $tableFieldData["T1"]);
			list($tableFieldData["T2_DESC"], $tableFieldData["T2_HELP"]) = explode("||", $tableFieldData["T2"]);
			list($tableFieldData["T3_DESC"], $tableFieldData["T3_HELP"]) = explode("||", $tableFieldData["T3"]);
			
			$tableFieldClassName = 'Ad_Import_Preset_Mapping_TableField';
			$tableFieldClassNameSpecial = $tableFieldClassName.'_'.ucfirst(strtolower(str_replace('_', '', $tableFieldData['F_NAME'])));

			if(class_exists($tableFieldClassNameSpecial)) {
				$tableFieldClassName = $tableFieldClassNameSpecial;
			}
			/** @var Ad_Import_Preset_Mapping_TableField $tableField */
			$tableField = $tableFieldClassName::getInstance($tableFieldData['TABLE_DEF_TNAME'], $tableFieldData['F_NAME']);

			$tableField->setFieldName($tableFieldData['F_NAME']);
			$tableField->setDisplayName($tableFieldData['V1']);
			$tableField->setImportDescription($tableFieldData['T3_DESC']);
			$tableField->setType($tableFieldData['F_TYP']);
			$tableField->setIsMaster((bool)$tableFieldData['IS_MASTER']);
			$tableField->setIsSpecial($tableFieldData['IS_SPECIAL']);
			$tableField->setIsImport((bool)$tableFieldData['B_IMPORT']);
			$tableField->setIsRequired((bool)$tableFieldData['B_NEEDED']);
			$tableField->setTableDef($tableFieldData['TABLE_DEF_TNAME']);
			$tableField->setTableDefName($tableFieldData['TABLE_DEF_NAME']);
			$tableField->setTableDefId($tableFieldData['FK_TABLE_DEF']);

			if($tableField->isListTypeField() && $tableFieldData['FK_LISTE'] != null) {
				$tableField->setAcceptedValues(array());
				$listValues = CategoriesBase::getListValuesByListId($tableFieldData['FK_LISTE']);
				foreach($listValues as $listKey => $listValueData) {
					$tableField->addAcceptedValue($listValueData['ID_LISTE_VALUES'], $listValueData['V1']);
				}
			}


			// post hook

			$tableField->postHook();

			// save

			if($tableFieldData['TABLE_DEF_TNAME'] == 'artikel_master') {
				$this->tableFieldsByFName[$tableField->getFieldName()] = $tableField;
				$this->tableFieldsByTableDef['artikel_master'][$tableField->getFieldName()] = $tableField;
			} else {
				if($tableField->getIsMaster() == false) {
					$this->tableFieldsByTableDef[$tableField->getTableDef()][$tableField->getFieldName()] = $tableField;
				} elseif(!is_array($this->tableFieldsByTableDef[$tableField->getTableDef()])) {
					$this->tableFieldsByTableDef[$tableField->getTableDef()] = array();
				}
			}



		}

		// Special Fields
		$importTaskTableField = Ad_Import_Preset_Mapping_TableField_ImportTask::getInstance('artikel_master', 'IMPORT_TASK');
		$importTaskTableField->postHook();
		$this->addCustomTableField($importTaskTableField);
		
		// Allow to add further fields by plugin
		Api_TraderApiHandler::getInstance($GLOBALS['db'])->triggerEvent(
			Api_TraderApiEvents::IMPORT_GET_FIELDS, $this
		);

	}


	/**
	 * Fügt ein Feld hinzu das beim Import zugeordnet werden kann
	 * @see	Api_TraderApiEvents::IMPORT_GET_FIELDS
	 */
	public function addCustomTableField(Ad_Import_Preset_Mapping_TableField &$field) {
		$fieldName = $field->getFieldName();
		$tableName = $field->getTableDefName();
		$this->tableFieldsByFName[$fieldName] = $field;
		$this->tableFieldsByTableDef[$tableName][$fieldName] = $field;
	}

	public function isTableFieldsUpToDate() {
		$databaseTableFields = $this->importPresetManagement->getTableFields();
		$tmpLoadedTableFields = array();
		$tmpLoadedDatabaseTableFields = array();
		$tmpFieldsNotToCompare = array();


		foreach($this->tableFieldsByTableDef as $tableDef => $tableFields) {
			/** @var  Ad_Import_Preset_Mapping_TableField $tableField	 */
			foreach($tableFields as $key => $tableField) {

				if(!in_array($tableField->getFieldName(), $tmpFieldsNotToCompare)) {
					$tableFieldKeyHash = md5(implode(',', array($tableDef, $tableField->getFieldName())));
					$tableFieldAttributeHash = (implode(',', array(
							$tableField->getFieldName(), $tableField->getType(), (int)$tableField->getIsRequired(),
							implode(',', $tableField->getAcceptedValues())
					)));

					if(get_class($tableField) != 'Ad_Import_Preset_Mapping_TableField') {
						$tmpFieldsNotToCompare[] = $tableField->getFieldName();
						continue;
					}

					$tmpLoadedTableFields[$tableFieldKeyHash] = $tableFieldAttributeHash;
				}
			}
		}

		foreach($databaseTableFields as $key => $databaseTableField) {
			if(!in_array($databaseTableField['F_NAME'], $tmpFieldsNotToCompare)) {

				if($databaseTableField['TABLE_DEF_TNAME'] != 'artikel_master' && $databaseTableField['IS_MASTER'] == 1) {
					continue;
				}

				$databaseTableFieldListValues = array();
				if ($databaseTableField['FK_LISTE'] > 0) {
					$listValues = CategoriesBase::getListValuesByListId($databaseTableField['FK_LISTE']);
					foreach ($listValues as $listKey => $listValueData) {
						$databaseTableFieldListValues[] = $listValueData['V1'];
					}
				}
				if ((bool)$databaseTableField['IS_MASTER']) {
					$databaseTableField['TABLE_DEF_TNAME'] = 'artikel_master';
				}

				$databaseTableFieldKeyHash = md5(implode(',', array($databaseTableField['TABLE_DEF_TNAME'], $databaseTableField['F_NAME'])));
				$databaseTableFieldAttributeHash = (implode(',', array(
						$databaseTableField['F_NAME'], $databaseTableField['F_TYP'], $databaseTableField['B_NEEDED'],
						implode(',', $databaseTableFieldListValues)
				)));
				$tmpLoadedDatabaseTableFields[$databaseTableFieldKeyHash] = $databaseTableFieldAttributeHash;
			}
		}

		$compareAToB = array_diff_assoc($tmpLoadedTableFields, $tmpLoadedDatabaseTableFields);
		$compareBToA = array_diff_assoc($tmpLoadedDatabaseTableFields, $tmpLoadedTableFields);

		return !(count($compareAToB) > 0 || count($compareBToA) > 0);
	}

	/**
	 * Loads all categories from Source File into the attribute categoryDataValues
	 * This is the abstract parent method that does nothing and should be overwritten
	 *
	 * @throws Exception
	 */
	public function loadDataCategories() {
		if($this->getCategoryField() == NULL) {
			throw new Exception("Category Field is not set");
		}
	}

	public function autoMapFields() {
		foreach($this->tableFieldsByTableDef as $tableDefName => $tableFields) {
			foreach($tableFields as $tableFieldName => $tableField) {

				if(array_key_exists($tableFieldName, $this->dataFieldsByName)) {
					$this->mapField($tableField, new Ad_Import_Preset_Mapping_Value_DataFieldMappingValue($this->dataFieldsByName[$tableFieldName]));
				}

			}
		}
	}

	/**
	 *
	 */
	public function autoMapCategories() {
	}


	/**
	 * Maps a tablefield to one or more Mapping Values (eg. Datafields, functions)
	 *
	 * @param Ad_Import_Preset_Mapping_TableField $tableField
	 * @param                                     $mappingValue
	 *
	 * @return $this
	 */
	public function mapField(Ad_Import_Preset_Mapping_TableField $tableField, $mappingValue) {
		$fieldMap = new Ad_Import_Preset_Mapping_FieldMap();
		$fieldMap->setTableField($tableField);

		if(is_array($mappingValue)) {
			$fieldMap->setFieldValues($mappingValue);
		} elseif(is_string($mappingValue)) {
			$fieldMap->addFieldValue(new Ad_Import_Preset_Mapping_Value_FixMappingValue($mappingValue));
		} elseif($mappingValue instanceof Ad_Import_Preset_Mapping_Value_AbstractMappingValue) {
			$fieldMap->addFieldValue($mappingValue);
		}


		$this->fieldMapping[$tableField->getTableDef()][$tableField->getFieldName()] = $fieldMap;

		return $this;
	}

	public function debug() {
		echo '<pre>';

		var_dump($this->fieldMapping);
	}

	/**
	 * Returns the value of the configuration $optionName
	 *
	 * @param $optionName
	 *
	 * @return string|null
	 */
	public function getConfigurationOption($optionName) {
		return (array_key_exists($optionName, $this->configuration)?$this->configuration[$optionName]:null);
	}

	public  function setConfigurationOption($optionName, $value) {
		$this->configuration[$optionName] = $value;
	}

	public function setConfigurationOptions($options) {
		if(!is_array($options)) {
			return false;
		}

		foreach($options as $optionName => $value) {
			$this->setConfigurationOption($optionName, $value);
		}
	}
	
	public function addConfigurationTemplate(Template $tplConfig) {
		$this->configurationTemplates[] = $tplConfig;
	}
	
	public function clearConfigurationTemplates() {
		$this->configurationTemplates = array();
	}
	
	public function getConfigurationTemplates() {
		return $this->configurationTemplates;
	}

	/**
	 * @return array
	 */
	public function getConfiguration() {
		return $this->configuration;
	}




	/**
	 * Getter for presetName
	 *
	 * @return mixed
	 */
	public function getPresetName() {
		return $this->presetName;
	}

	/**
	 * Setter for presetName
	 *
	 * @param string $presetName
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function setPresetName($presetName) {
		$this->presetName = $presetName;

		return $this;
	}

	/**
	 * Getter for importPresetId
	 *
	 * @return null
	 */
	public function getImportPresetId() {
		return $this->importPresetId;
	}

	/**
	 * @param null $importPresetId
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function setImportPresetId($importPresetId) {
		$this->importPresetId = $importPresetId;

		return $this;
	}


	/**
	 * @return mixed
	 */
	public function getCategoryField() {
		return $this->categoryField;
	}

	/**
	 * @param mixed $categoryField
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function setCategoryField($categoryField) {
		$this->categoryField = $categoryField;

		return $this;
	}

	/**
	 * @param mixed $categoryField
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function addCategoryField($categoryField) {
		if(!is_array($this->categoryField)) {
			if($this->categoryField != null) {
				$tmp = $this->categoryField;
				$this->categoryField = array($tmp);
			} else {
				$this->categoryField = array();
			}

		}
		$this->categoryField[] = $categoryField;

		return $this;
	}

	public function addCategoryUsage($categoryValue) {
		if (array_key_exists($categoryValue, $this->categoryUsage)) {
			$this->categoryUsage[$categoryValue]++;
		} else {
			$this->categoryUsage[$categoryValue] = 1;
		}
	}
	
	public function clearCategoryUsage() {
		$this->categoryUsage = array();
	}
	
	public function isCategoryUsed($categoryValue) {
		return true;
	}

	/**
	 * @return mixed
	 */
	public function getCategoryDataValues() {
		return $this->categoryDataValues;
	}

	/**
	 * @param mixed $categoryDataValues
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function setCategoryDataValues($categoryDataValues) {
		$this->categoryDataValues = $categoryDataValues;

		return $this;
	}

	/**
	 * @param mixed $categoryDataValues
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function addCategoryDataValues($categoryDataValue) {
		$categoryDataValue = trim($categoryDataValue);
		if(!in_array($categoryDataValue, $this->categoryDataValues)) {
			$this->categoryDataValues[] = $categoryDataValue;
		}

		return $this;
	}

	/**
	 * @return array
	 */
	public function getCategoryMapping() {
		return $this->categoryMapping;
	}

	public function getCategoryMappingByKey($key) {
		return $this->categoryMapping[$key];
	}

	/**
	 * @return array
	 */
	public function getTableFieldsByTableDef() {
		return $this->tableFieldsByTableDef;
	}



	/**
	 * @param array $categoryMapping
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function setCategoryMapping($categoryMapping) {
		$this->categoryMapping = $categoryMapping;

		return $this;
	}

	/**
	 * @param array $categoryMapping
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function addCategoryMapping($categoryMappingKey, $categoryMappingValue) {
		$this->categoryMapping[$categoryMappingKey] = $categoryMappingValue;

		return $this;
	}

	public function removeCategoryMapping($categoryMappingKey) {
		$this->categoryMapping[$categoryMappingKey] = "";
		return true;
	}





	/**
	 * @param        $fieldname
	 * @param string $tabledef
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function getTableFieldByName($fieldname, $tabledef = 'artikel_master') {
		return $this->tableFieldsByTableDef[$tabledef][$fieldname];
	}

	/**
	 * @return array
	 */
	public function getDataFields() {
		return $this->dataFieldsByIdent;
	}


	/**
	 * @param $ident
	 *
	 * @return Ad_Import_Preset_Mapping_DataField
	 */
	public function getDataFieldByIdentifier($ident) {
		return $this->dataFieldsByIdent[$ident];
	}

	/**
	 * @param $name
	 *
	 * @return Ad_Import_Preset_Mapping_DataField
	 */
	public function getDataFieldByName($name) {
		return $this->dataFieldsByName[$name];
	}

	/**
	 * @return array
	 */
	public function getFieldMapping() {
		return $this->fieldMapping;
	}

	/**
	 * @param        $fieldname
	 * @param string $tabledef
	 *
	 * @return Ad_Import_Preset_Mapping_FieldMap|null
	 */
	public function getFieldMappingByTableField($fieldname, $tabledef = 'artikel_master') {
		return $this->fieldMapping[$tabledef][$fieldname];
	}

	/**
	 * @return null
	 */
	public function getOwnerUser() {
		return $this->ownerUser;
	}

	/**
	 * @param null $ownerUser
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function setOwnerUser($ownerUser) {
		$this->ownerUser = $ownerUser;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isIsStored() {
		return $this->isStored;
	}

	/**
	 * @param boolean $isStored
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function setIsStored($isStored) {
		$this->isStored = $isStored;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isIsGlobal() {
		return $this->isGlobal;
	}

	/**
	 * @param boolean $isGlobal
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function setIsGlobal($isGlobal) {
		$this->isGlobal = $isGlobal;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param int $status
	 *
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function setStatus($status) {
		$this->status = $status;

		return $this;
	}

	/**
	 * @return mixed
	 */
	abstract public function getPresetType();

	/**
	 * @return string
	 */
	public function getAjaxOptions() {
		return "";
	}

    /**
     * Called once before importing, passed to the read function while importing
     * @return array
     */
    public function getReadOptions($arProcessOptions = array()) {
        return array();
    }

    /**
     * Validates the presets settings to ensure it is possible to perform an import.
     * @param Ad_Import_PresetEditor_FormResult     $formResult
     */
    public function validate(Ad_Import_PresetEditor_FormResult $formResult) {

    }

}