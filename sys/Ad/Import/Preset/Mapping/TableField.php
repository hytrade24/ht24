<?php

class Ad_Import_Preset_Mapping_TableField {
	protected static $instance = null;

	protected $fieldName;
	protected $displayName;
	protected $importDescription;
	protected $type;
	protected $isMaster;
	protected $isSpecial;
	protected $isImport;
	protected $isRequired;
	protected $isRequiredInCategories = false;

	protected $tableDef;
	protected $tableDefId;
	protected $tableDefName;

	protected $acceptedValues = array();
	protected $checkAcceptedValues = false;

	protected $defaultValue = null;

	/**
	 * Singleton
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public static function getInstance($tableDef, $fieldName) {
		if (self::$instance[$tableDef][$fieldName] === null) {
			self::$instance[$tableDef][$fieldName] = new static($tableDef, $fieldName);
		}

		return self::$instance[$tableDef][$fieldName];
	}


	private function __construct($tableDef = null, $fieldName = null) {
		$this->tableDefName = $tableDef;
		$this->fieldName = $fieldName;
	}

	public function postHook() {
	}


	/**
	 * @return mixed
	 */
	public function getFieldName() {
		return $this->fieldName;
	}

	/**
	 * @param mixed $fieldName
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setFieldName($fieldName) {
		$this->fieldName = $fieldName;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getIsMaster() {
		return $this->isMaster;
	}

	/**
	 * @param mixed $isMaster
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setIsMaster($isMaster) {
		$this->isMaster = $isMaster;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getIsImport() {
		return $this->isImport;
	}

	/**
	 * @param mixed $isImport
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setIsImport($isImport) {
		$this->isImport = $isImport;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getIsRequired() {
		return $this->isRequired;
	}

	/**
	 * @param mixed $isRequired
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setIsRequired($isRequired) {
		$this->isRequired = $isRequired;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getIsRequiredInCategories() {
		return $this->isRequiredInCategories;
	}

	/**
	 * @param array $isRequiredInCategories
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setIsRequiredInCategories($isRequiredInCategories) {
		$this->isRequiredInCategories = $isRequiredInCategories;

		return $this;
	}

	/**
	 * @param array $isRequiredInCategories
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function addIsRequiredInCategories($requiredCategory, $isRequired = true) {
		if(is_array($requiredCategory)) {
			foreach($requiredCategory as $key => $value) {
				$this->addIsRequiredInCategories($value, $isRequired);
			}
		} else {
			$this->isRequiredInCategories[$requiredCategory] = $isRequired;
		}

		return $this;
	}

	/**
	 * @param $category
	 *
	 * @return bool
	 */
	public function getIsRequiredInCategory($category) {
		if(array_key_exists($category, $this->isRequiredInCategories)) {
			return $this->isRequiredInCategories[$category];
		} else {
			$this->isRequired();
		}
	}




	/**
	 * @return mixed
	 */
	public function getIsSpecial() {
		return $this->isSpecial;
	}

	/**
	 * @param mixed $isSpecial
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setIsSpecial($isSpecial) {
		$this->isSpecial = $isSpecial;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getTableDef() {
		return $this->tableDef;
	}

	/**
	 * @param mixed $tableDef
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setTableDef($tableDef) {
		$this->tableDef = $tableDef;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getTableDefId() {
		return $this->tableDefId;
	}

	/**
	 * @param mixed $tableDefId
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setTableDefId($tableDefId) {
		$this->tableDefId = $tableDefId;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param mixed $type
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setType($type) {
		$this->type = $type;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getTableDefName() {
		return $this->tableDefName;
	}

	/**
	 * @param mixed $tableDefName
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setTableDefName($tableDefName) {
		$this->tableDefName = $tableDefName;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getAcceptedValues() {
		return $this->acceptedValues;
	}

	/**
	 * @param array $acceptedValues
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setAcceptedValues($acceptedValues) {
		$this->acceptedValues = $acceptedValues;

		return $this;
	}


	/**
	 * @param array $acceptedValues
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function addAcceptedValue($key, $value) {
		$this->acceptedValues[$key] = $value;

		return $this;
	}


	public function isListTypeField() {
		return in_array($this->getType(), array('LIST', 'MULTICHECKBOX', 'MULTICHECKBOX_AND', 'VARIANT'));
	}

	/**
	 * @return boolean
	 */
	public function isCheckAcceptedValues() {
		return $this->checkAcceptedValues;
	}

	/**
	 * @param boolean $checkAcceptedValues
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setCheckAcceptedValues($checkAcceptedValues) {
		$this->checkAcceptedValues = $checkAcceptedValues;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getDefaultValue() {
		return $this->defaultValue;
	}

	/**
	 * @param mixed $defaultValue
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setDefaultValue($defaultValue) {
		$this->defaultValue = $defaultValue;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getDisplayName() {
		return $this->displayName;
	}

	/**
	 * @param mixed $displayName
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setDisplayName($displayName) {
		$this->displayName = $displayName;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getImportDescription() {
		return $this->importDescription;
	}

	/**
	 * @param mixed $displayName
	 *
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function setImportDescription($importDescription) {
		$this->importDescription = $importDescription;

		return $this;
	}


	public function asArray() {
		return array(
			'fieldName' => $this->fieldName,
			'displayName' => $this->displayName,
			'importDescription' => $this->importDescription,
			'type' => $this->type,
			'isMaster' => $this->isMaster,
			'isSpecial' => $this->isSpecial,
			'isImport' => $this->isImport,
			'isRequired' => $this->isRequired,
			'tableDef' => $this->tableDef,
			'tableDefId' => $this->tableDefId,
			'tableDefName' => $this->tableDefName,
			'acceptedValues' => $this->acceptedValues,
			'acceptedValuesAsString' => implode(', ', $this->acceptedValues),
			'acceptedValuesAsTableString' => implode("<br>", array_map(function($k, $v) {
				return ($k.' => '.$v);
			}, array_keys($this->acceptedValues), $this->acceptedValues)),
			'defaultValue' => $this->defaultValue
		);
	}


	public function __wakeup() {
		if(self::$instance[$this->getTableDef()][$this->getFieldName()] != null) {
			return self::$instance[$this->getTableDef()][$this->getFieldName()];
		} else {
			self::$instance[$this->getTableDef()][$this->getFieldName()] = $this;
		}
	}


}