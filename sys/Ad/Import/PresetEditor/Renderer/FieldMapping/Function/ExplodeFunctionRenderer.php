<?php

class Ad_Import_PresetEditor_Renderer_FieldMapping_Function_ExplodeFunctionRenderer extends Ad_Import_PresetEditor_Renderer_FieldMapping_FunctionMappingValueRenderer {



	public function renderDisplayView($mappingValue, $fieldMap) {
		global $s_lang;

		$mappedFunction = $mappingValue->getFunction();
		if(!($mappedFunction instanceof Ad_Import_Preset_Mapping_Function_ExplodeFunction)) {
			throw new Exception("wrong mapping function renderer loaded");
		}

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.explode.displayview.htm');
		$tpl->addvar('FIELD_NAME', $fieldMap->getTableField()->getFieldName());
		$tpl->addvar('TABLE_DEF', $fieldMap->getTableField()->getTableDef());
		$tpl->addvar('FUNCTION_NAME', $mappedFunction->getFunctionName());
		$tpl->addvar('EXPLODE_DELIMITER', $mappedFunction->getDelimiter());;

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
		if(!($mappedFunction instanceof Ad_Import_Preset_Mapping_Function_ExplodeFunction)) {
			throw new Exception("wrong mapping function renderer loaded");
		}

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.explode.editview.htm');

		$this->setTemplateMappedFunctions($mappedFunctionClassName, $tpl);

		$tpl->addvar('FIELD_NAME', $fieldMap->getTableField()->getFieldName());
		$tpl->addvar('TABLE_DEF', $fieldMap->getTableField()->getTableDef());
		$tpl->addvar('POS', $fieldMap->getFieldValuePosition($mappingValue));
		$tpl->addvar('CURRENT_FUNCTION_CLASS', $mappedFunctionClassName);
		$tpl->addvar('EXPLODE_DELIMITER', $mappedFunction->getDelimiter());;

		return $tpl->process();

	}


}