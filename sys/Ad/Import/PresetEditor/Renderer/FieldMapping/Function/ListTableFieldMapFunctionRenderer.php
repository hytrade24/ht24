<?php

class Ad_Import_PresetEditor_Renderer_FieldMapping_Function_ListTableFieldMapFunctionRenderer extends Ad_Import_PresetEditor_Renderer_FieldMapping_FunctionMappingValueRenderer {



	public function renderDisplayView($mappingValue, $fieldMap) {
		global $s_lang;

		$mappedFunction = $mappingValue->getFunction();
		if(!($mappedFunction instanceof Ad_Import_Preset_Mapping_Function_ListTableFieldMapFunction)) {
			throw new Exception("wrong mapping function renderer loaded");
		}

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.map.displayview.htm');
		$tpl->addvar('FIELD_NAME', $fieldMap->getTableField()->getFieldName());
		$tpl->addvar('TABLE_DEF', $fieldMap->getTableField()->getTableDef());
		$tpl->addvar('FUNCTION_NAME', $mappedFunction->getFunctionName());

		return $tpl->process();
	}


	/**
	 * @param Ad_Import_Preset_Mapping_Value_AbstractMappingValue $mappingValue
	 * @param Ad_Import_Preset_Mapping_FieldMap                   $fieldMap
	 *
	 * @return mixed|string
	 * @throws Exception
	 */
	public function renderEditView($mappingValue, $fieldMap) {
		global $s_lang;

		$mappedFunction = $mappingValue->getFunction();
		$mappedFunctionClassName = get_class($mappedFunction);
		if(!($mappedFunction instanceof Ad_Import_Preset_Mapping_Function_ListTableFieldMapFunction)) {
			throw new Exception("wrong mapping function renderer loaded");
		}

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.listmap.editview.htm');

		$this->setTemplateMappedFunctions($mappedFunctionClassName, $tpl);


		// tablefield
		$tableField = $fieldMap->getTableField();
		$tplAcceptedValues = array();
		foreach($tableField->getAcceptedValues() as $accpetedValuesKey => $accpetedValuesValue) {
			$tplAcceptedValues[$accpetedValuesKey] = array('KEY' => $accpetedValuesKey, 'VALUE' => $accpetedValuesValue);
		}

		// map
		$tplMap = array();
		foreach($mappedFunction->getMap() as $mapKey => $mapValue) {
			$tplAcceptedValuesCopy = array();
			$tplAcceptedValuesCopy = $tplAcceptedValues;
			$tplAcceptedValuesCopy[$mapValue]['IS_SELECTED'] = 1;

			$tmpTpl = new Template('tpl/de/empty.htm');
			$tmpTpl->tpl_text = '{liste}';
			$tmpTpl->addlist('liste', $tplAcceptedValuesCopy, 'tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.listmapmap.editview.acceptedvaluesrow.htm');

			$tplMap[] = array(
				'KEY' => $mapKey,
				'VALUE' => $mapValue,
				'TABLE_FIELD_ACCEPTED_VALUES' => $tmpTpl->process()
			);
		}

		$tpl->addlist('MAP', $tplMap, 'tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.listmap.editview.maprow.htm');
		$tpl->addlist('TABLE_FIELD_ACCEPTED_VALUES', $tplAcceptedValues, 'tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.listmapmap.editview.acceptedvaluesrow.htm');
		$tpl->addvar('FIELD_NAME', $fieldMap->getTableField()->getFieldName());
		$tpl->addvar('TABLE_DEF', $fieldMap->getTableField()->getTableDef());
		$tpl->addvar('POS', $fieldMap->getFieldValuePosition($mappingValue));
		$tpl->addvar('CURRENT_FUNCTION_CLASS', $mappedFunctionClassName);

		return $tpl->process();

	}


}