<?php

interface Ad_Import_PresetEditor_Helper_FieldMappingSaveHelper_SaveHelperInterface {

	/**
	 * @param Ad_Import_Preset_Mapping_Value_AbstractMappingValue $mappingValue
	 * @param $data
	 * @param Ad_Import_Preset_AbstractPreset					  $preset
	 *
	 * @return mixed
	 */
	public function save($mappingValue, $data, $preset);
}
