<?php

class Ad_Import_PresetEditor_Renderer_FieldMapping_FixMappingValueRenderer implements Ad_Import_PresetEditor_Renderer_FieldMapping_FieldMappingValueRendererInterface {

	/**
	 * @var Ad_Import_Preset_AbstractPreset
	 */
	protected $preset;

	function __construct($preset) {
		$this->preset = $preset;
	}


	/**
	 * @param Ad_Import_Preset_Mapping_Value_AbstractMappingValue $mappingValue
	 * @param Ad_Import_Preset_Mapping_FieldMap                   $fieldMap
	 *
	 * @return mixed
	 */
	public function renderDisplayView($mappingValue, $fieldMap) {
		global $s_lang;

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.fix.displayview.htm');


		$value = $mappingValue->getValue();
		$tpl->addvar('DATA_VALUE', $value);
		$tpl->addvar('FIELD_NAME', $fieldMap->getTableField()->getFieldName());
		$tpl->addvar('TABLE_DEF', $fieldMap->getTableField()->getTableDef());
		return $tpl->process();
	}



	/**
	 * @param Ad_Import_Preset_Mapping_Value_AbstractMappingValue $mappingValue
	 * @param Ad_Import_Preset_Mapping_FieldMap                   $fieldMap
	 *
	 * @return mixed
	 */
	public function renderEditView($mappingValue, $fieldMap) {
		global $s_lang;

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.fix.editview.htm');

		$value = $mappingValue->getValue();
		$tpl->addvar('DATA_VALUE', $value);
		$tpl->addvar('FIELD_NAME', $fieldMap->getTableField()->getFieldName());
		$tpl->addvar('TABLE_DEF', $fieldMap->getTableField()->getTableDef());
		$tpl->addvar('POS', $fieldMap->getFieldValuePosition($mappingValue));

		return $tpl->process();
	}


}