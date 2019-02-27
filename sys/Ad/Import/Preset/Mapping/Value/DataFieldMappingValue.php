<?php

class Ad_Import_Preset_Mapping_Value_DataFieldMappingValue extends Ad_Import_Preset_Mapping_Value_AbstractMappingValue {

	protected $value;

	function __construct($value = null) {
		$this->value = $value;
	}

	public function execute($input, $datarow, $implodeString = "") {
		if($this->value instanceof Ad_Import_Preset_Mapping_DataField) {
			$inputRow = $datarow[$this->value->getIdentifier()];
			return (($input != "") && ($inputRow != "") ? $input.$implodeString.$inputRow : $input.$inputRow);
		}

		return $input;
	}


	/**
	 * @return mixed
	 */
	public function getValue() {
		return $this->value;
	}

	/**
	 * @param mixed $value
	 *
	 * @return Ad_Import_Preset_Mapping_Value_DataFieldMappingValue
	 */
	public function setValue($value) {
		$this->value = $value;

		return $this;
	}


}