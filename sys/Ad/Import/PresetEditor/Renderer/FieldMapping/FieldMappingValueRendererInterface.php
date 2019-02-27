<?php

interface Ad_Import_PresetEditor_Renderer_FieldMapping_FieldMappingValueRendererInterface {

	/**
	 * @param Ad_Import_Preset_Mapping_Value_AbstractMappingValue $mappingValue
	 * @param Ad_Import_Preset_Mapping_FieldMap                   $fieldMap
	 *
	 * @return mixed
	 */
	public function renderDisplayView($mappingValue, $fieldMap);

	/**
	 * @param Ad_Import_Preset_Mapping_Value_AbstractMappingValue $mappingValue
	 * @param Ad_Import_Preset_Mapping_FieldMap                   $fieldMap
	 *
	 * @return mixed
	 */
	public function renderEditView($mappingValue, $fieldMap);
}