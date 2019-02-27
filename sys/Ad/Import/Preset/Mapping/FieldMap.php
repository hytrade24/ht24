<?php

class Ad_Import_Preset_Mapping_FieldMap {

	protected $tableField;
	protected $fieldValues = array();

	public function execute($datarow, $implodeString = "") {
		$result = '';
		/**
		 * @var  $key
		 * @var Ad_Import_Preset_Mapping_Value_AbstractMappingValue $fieldValue
		 */
		foreach($this->fieldValues as $key => $fieldValue) {
			$result = $fieldValue->execute($result, $datarow, $implodeString);
		}
		return $result;
	}

	/**
	 * @return Ad_Import_Preset_Mapping_TableField
	 */
	public function getTableField() {
		return $this->tableField;
	}

	/**
	 * @param Ad_Import_Preset_Mapping_TableField $tableField
	 *
	 * @return Ad_Import_Preset_Mapping_FieldMap
	 */
	public function setTableField($tableField) {
		$this->tableField = $tableField;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getFieldValues() {
		return $this->fieldValues;
	}

	/**
	 * @param array $fieldValues
	 *
	 * @return Ad_Import_Preset_Mapping_FieldMap
	 */
	public function setFieldValues($fieldValues) {
		$this->fieldValues = $fieldValues;

		return $this;
	}

	/**
	 * @param mixed $fieldValue
	 *
	 * @return Ad_Import_Preset_Mapping_FieldMap
	 */
	public function addFieldValue($fieldValue) {
		$this->fieldValues[] = $fieldValue;

		return $this;
	}

	public function getFieldValuePosition($fieldValue) {
		return array_search($fieldValue, $this->fieldValues, true);
	}


	public function isValueMapped() {
		return ($this->fieldValues != null && !(is_array($this->fieldValues) && count($this->fieldValues) == 0));
	}


}