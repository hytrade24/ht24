<?php

class Ad_Import_PresetEditor_Renderer_FieldMapping_FunctionMappingValueRenderer implements Ad_Import_PresetEditor_Renderer_FieldMapping_FieldMappingValueRendererInterface {

	/**
	 * @var Ad_Import_Preset_AbstractPreset
	 */
	protected $preset;

	protected $mappingFunctions = array();

	function __construct($preset) {
		global $ab_path;

		$this->preset = $preset;

		// Loads declared Mapping Functions
		$path = $ab_path.'sys/Ad/Import';

		$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
		foreach($objects as $name => $object){
			if(preg_match("/\.php$/", $name)) {
				require_once $name;
			}

		}

		$functionMappingClasses = array_filter(
			get_declared_classes(),
			function ($className) {
				return in_array('Ad_Import_Preset_Mapping_Function_MappingFunctionInterface', class_implements($className));
			}
		);

		foreach($functionMappingClasses as $key => $classname) {
			$obj = new $classname();
			$this->mappingFunctions[$obj->getFunctionName()] = $obj;
			ksort($this->mappingFunctions);
		}


	}


	/**
	 * @param Ad_Import_Preset_Mapping_Value_AbstractMappingValue $mappingValue
	 * @param Ad_Import_Preset_Mapping_FieldMap                   $fieldMap
	 *
	 * @return mixed
	 */
	public function renderDisplayView($mappingValue, $fieldMap) {
		global $s_lang;

		$mappedFunction = $mappingValue->getFunction();
		if($mappedFunction != null) {
			$mappedFunctionClassName = get_class($mappedFunction);
			$extractedClassIdent = preg_match("/\_([a-zA-Z0-9]+)$/", $mappedFunctionClassName, $tmp);

			if($extractedClassIdent) {
				$rendererClassName = 'Ad_Import_PresetEditor_Renderer_FieldMapping_Function_' . $tmp['1'] . 'Renderer';
				if (class_exists($rendererClassName) && in_array('Ad_Import_PresetEditor_Renderer_FieldMapping_FieldMappingValueRendererInterface', class_implements($rendererClassName))) {
					/** @var Ad_Import_PresetEditor_Renderer_FieldMapping_FieldMappingValueRendererInterface $valueRenderer */
					$valueRenderer = new $rendererClassName($this->preset);
					return $valueRenderer->renderDisplayView($mappingValue, $fieldMap);
				}
			}
		}

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.displayview.htm');
		if($mappedFunction == null) {
			$tpl->addvar('NO_FUNCTION', 1);
		} else {
			$tpl->addvar('FUNCTION_NAME', $mappedFunction->getFunctionName());
		}
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

		$mappedFunction = $mappingValue->getFunction();
		if($mappedFunction != null) {
			$mappedFunctionClassName = get_class($mappedFunction);
			$extractedClassIdent = preg_match("/\_([a-zA-Z0-9]+)$/", $mappedFunctionClassName, $tmp);

			if($extractedClassIdent) {
				$rendererClassName = 'Ad_Import_PresetEditor_Renderer_FieldMapping_Function_' . $tmp['1'] . 'Renderer';
				if (class_exists($rendererClassName) && in_array('Ad_Import_PresetEditor_Renderer_FieldMapping_FieldMappingValueRendererInterface', class_implements($rendererClassName))) {
					/** @var Ad_Import_PresetEditor_Renderer_FieldMapping_FieldMappingValueRendererInterface $valueRenderer */
					$valueRenderer = new $rendererClassName($this->preset);
					return $valueRenderer->renderEditView($mappingValue, $fieldMap);
				}
			}
		}

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.function.editview.htm');
		$tpl->addvar('NO_FUNCTION', 1);

		$this->setTemplateMappedFunctions($mappedFunctionClassName, $tpl);

		$tpl->addvar('FIELD_NAME', $fieldMap->getTableField()->getFieldName());
		$tpl->addvar('TABLE_DEF', $fieldMap->getTableField()->getTableDef());
		$tpl->addvar('POS', $fieldMap->getFieldValuePosition($mappingValue));

		return $tpl->process();

	}


	/**
	 * @param $mappedFunctionClassName
	 * @param $tpl
	 */
	protected function setTemplateMappedFunctions($mappedFunctionClassName, $tpl) {
		global $s_lang;

		$tplMappedFunctions = array();
		foreach ($this->mappingFunctions as $key => $functionClass) {
			$tplMappedFunctions[] = array(
					'FUNCTION_CLASS' => get_class($functionClass), 'FUNCTION_NAME' => $functionClass->getFunctionName(),
					'IS_SELECTED' => (get_class($functionClass) == $mappedFunctionClassName)
			);
		}


		$tpl->addlist('SELECT_FUNCTION', $tplMappedFunctions, 'tpl/' . $s_lang . '/my-import-presets-edit.fieldmappingrenderer.function.editview.selectfunctionrow.htm');
	}


}