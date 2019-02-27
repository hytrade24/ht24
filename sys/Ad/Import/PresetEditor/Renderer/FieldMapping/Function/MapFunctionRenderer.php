<?php

class Ad_Import_PresetEditor_Renderer_FieldMapping_Function_MapFunctionRenderer extends Ad_Import_PresetEditor_Renderer_FieldMapping_FunctionMappingValueRenderer {



	public function renderDisplayView($mappingValue, $fieldMap) {
		global $s_lang;

		$mappedFunction = $mappingValue->getFunction();
		if(!($mappedFunction instanceof Ad_Import_Preset_Mapping_Function_MapFunction)) {
			throw new Exception("wrong mapping function renderer loaded");
		}

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.map.displayview.htm');
		$tpl->addvar('FIELD_NAME', $fieldMap->getTableField()->getFieldName());
		$tpl->addvar('TABLE_DEF', $fieldMap->getTableField()->getTableDef());
		$tpl->addvar('FUNCTION_NAME', $mappedFunction->getFunctionName());

		if(is_array($mappedFunction->getMap()) && count($mappedFunction->getMap()) > 0) {
			$tpl->addvar('SHORT_LIST_MAP', implode("<br>", array_slice(array_map(function ($v, $k) { return $k . ' = ' . $v; }, $mappedFunction->getMap(), array_keys($mappedFunction->getMap())),0,3)));
		}

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
		if(!($mappedFunction instanceof Ad_Import_Preset_Mapping_Function_MapFunction)) {
			throw new Exception("wrong mapping function renderer loaded");
		}

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.map.editview.htm');

		$this->setTemplateMappedFunctions($mappedFunctionClassName, $tpl);


		// map
		$tplMap = array();
		foreach($mappedFunction->getMap() as $mapKey => $mapValue) {
			$tplMap[] = array('KEY' => $mapKey, 'VALUE' => $mapValue);
		}

		$tpl->addlist('MAP', $tplMap, 'tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.map.editview.maprow.htm');
		$tpl->addvar('FIELD_NAME', $fieldMap->getTableField()->getFieldName());
		$tpl->addvar('TABLE_DEF', $fieldMap->getTableField()->getTableDef());
		$tpl->addvar('POS', $fieldMap->getFieldValuePosition($mappingValue));
		$tpl->addvar('CURRENT_FUNCTION_CLASS', $mappedFunctionClassName);

		return $tpl->process();

	}


}