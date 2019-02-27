<?php

class Ad_Import_PresetEditor_Helper_FieldMappingSaveHelper_DataFieldMappingValueSaveHelper implements Ad_Import_PresetEditor_Helper_FieldMappingSaveHelper_SaveHelperInterface {


	/**
	 * @param Ad_Import_Preset_Mapping_Value_AbstractMappingValue $mappingValue
	 * @param                                                     $data
	 * @param Ad_Import_Preset_AbstractPreset					  $preset
	 *
	 * @return mixed
	 */
	public function save($mappingValue, $data, $preset) {
		if($mappingValue instanceof Ad_Import_Preset_Mapping_Value_DataFieldMappingValue) {

			$dataField = $preset->getDataFieldByIdentifier($data);
			if($dataField != null) {
				$mappingValue->setValue($dataField);
			}
		}
	}

}
