<?php

class Ad_Import_PresetEditor_Helper_FieldMappingSaveHelper_FunctionMappingValueSaveHelper implements Ad_Import_PresetEditor_Helper_FieldMappingSaveHelper_SaveHelperInterface {


	/**
	 * @param Ad_Import_Preset_Mapping_Value_AbstractMappingValue $mappingValue
	 * @param                                                     $data
	 * @param Ad_Import_Preset_AbstractPreset					  $preset
	 *
	 * @return mixed
	 */
	public function save($mappingValue, $data, $preset) {
		if($mappingValue instanceof Ad_Import_Preset_Mapping_Value_FunctionMappingValue) {

			$currentFunction = $mappingValue->getFunction();
			$currentFunctionClassName = get_class($currentFunction);

			if($data['FUNCTION'] == $currentFunctionClassName) {
				if(isset($data['CONFIG'])) {
					$currentFunction->setConfiguration($data['CONFIG']);
				}
			}


			if($data['FUNCTION'] != $currentFunctionClassName && class_exists($data['FUNCTION']) && (in_array('Ad_Import_Preset_Mapping_Function_MappingFunctionInterface', class_implements($data['FUNCTION'])))) {
				$currentFunction = new $data['FUNCTION']();

				if(isset($data['CONFIG'])) {
					$currentFunction->setConfiguration($data['CONFIG']);
				}

				$mappingValue->setFunction($currentFunction);
			}

		}
	}

}
