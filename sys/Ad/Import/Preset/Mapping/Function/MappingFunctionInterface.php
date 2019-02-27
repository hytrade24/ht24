<?php


interface Ad_Import_Preset_Mapping_Function_MappingFunctionInterface {

	public function execute($input);
	public function getFunctionName();
	public function setConfiguration($config);
} 