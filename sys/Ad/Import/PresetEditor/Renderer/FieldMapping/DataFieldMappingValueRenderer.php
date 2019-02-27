<?php

class Ad_Import_PresetEditor_Renderer_FieldMapping_DataFieldMappingValueRenderer implements Ad_Import_PresetEditor_Renderer_FieldMapping_FieldMappingValueRendererInterface {

	/**
	 * @var Ad_Import_Preset_AbstractPreset
	 */
	protected $preset;

	function __construct($preset) {
		$this->preset = $preset;
	}


	/**
	 * @param Ad_Import_Preset_Mapping_Value_DataFieldMappingValue $mappingValue
	 * @param Ad_Import_Preset_Mapping_FieldMap                   $fieldMap
	 *
	 * @return mixed
	 */
	public function renderDisplayView($mappingValue, $fieldMap) {
		global $s_lang;

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.datafield.displayview.htm');


		$dataFieldAsArray = ($mappingValue->getValue() instanceof Ad_Import_Preset_Mapping_DataField)?$mappingValue->getValue()->toArray():array();
		$tpl->addvars($dataFieldAsArray, 'DATAFIELD_');
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

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.datafield.editview.htm');

		$currentSelectedDataFieldIdentifier = ($mappingValue->getValue() != null)?$mappingValue->getValue()->getIdentifier():-1;

		$tplDataFieldList = array();
		/** @var Ad_Import_Preset_Mapping_DataField $dataField	 */
		foreach($this->preset->getDataFields() as $key => $dataField) {
			$tplDataFieldList[] = array(
				'IDENTIFIER' => $dataField->getIdentifier(),
				'DATAFIELD_NAME' => $dataField->getName(),
				'DATAFIELD_DESCRIPTION' => $dataField->getDescription(),
				'IS_SELECTED' => $dataField->getIdentifier() == $currentSelectedDataFieldIdentifier
			);
		}

		$tpl->addlist_fast('SELECT_DATAFIELD', $tplDataFieldList, 'tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.datafield.editview.selectdatafieldrow.htm');
		$tpl->addvar('FIELD_NAME', $fieldMap->getTableField()->getFieldName());
		$tpl->addvar('TABLE_DEF', $fieldMap->getTableField()->getTableDef());
		$tpl->addvar('POS', $fieldMap->getFieldValuePosition($mappingValue));
		$tpl->addvar('FIELD_MAPPING_VALUE_IDENTIFIER', $currentSelectedDataFieldIdentifier);

		return $tpl->process();
	}


}