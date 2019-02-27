<?php

class Ad_Import_Preset_Mapping_Value_FixMappingValue extends Ad_Import_Preset_Mapping_Value_AbstractMappingValue {

	protected $value;

	function __construct($value = '') {
		$this->value = $value;
	}

	public function execute($input, $datarow, $implodeString = "") {
		return (($input != "") && ($this->value != "") ? $input.$implodeString.$this->value : $input.$this->value);
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
	 * @return Ad_Import_Preset_Mapping_Value_DataFieldValue
	 */
	public function setValue($value) {
		$this->value = $value;

		return $this;
	}


}