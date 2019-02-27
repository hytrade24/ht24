<?php

class Ad_Import_Preset_Mapping_Value_FunctionMappingValue extends Ad_Import_Preset_Mapping_Value_AbstractMappingValue {

	protected $function;

	function __construct(Ad_Import_Preset_Mapping_Function_MappingFunctionInterface $function = null) {
		$this->function = $function;
	}

	public function execute($input, $datarow, $implodeString = "") {
		return $this->function->execute($input);
	}

	/**
	 * @return mixed
	 */
	public function getFunction() {
		return $this->function;
	}

	/**
	 * @param mixed $function
	 *
	 * @return Ad_Import_Preset_Mapping_Value_FunctionMappingValue
	 */
	public function setFunction($function) {
		$this->function = $function;

		return $this;
	}




}