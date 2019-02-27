<?php

abstract class Ad_Import_PresetEditor_Step_AbstractMappingStep extends Ad_Import_PresetEditor_Step_AbstractPresetEditorStep  {

	/**
	 * @param $fieldName
	 * @param $tableDef
	 * @param Ad_Import_Preset_AbstractPreset $preset
	 */
	public function renderTableFieldMapping($fieldName, $tableDef, $preset, $initialCall = false) {
		global $s_lang;

		$tableField = $preset->getTableFieldByName($fieldName, $tableDef);
		if($tableField == null) {
			throw new Exception("table field $fieldName / $tableDef not found");
		}


		$fieldMappingTableFieldTpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.step.basemapping.row_table_field.htm');
		$fieldMappingRenderer = new Ad_Import_PresetEditor_Renderer_FieldMappingRenderer($preset);

		if (array_key_exists('IMPORT_ERRORS_BY_FIELD', $_SESSION) && array_key_exists($fieldName, $_SESSION['IMPORT_ERRORS_BY_FIELD'])) {
			$messages = " - ".implode("<br />\n - ", $_SESSION['IMPORT_ERRORS_BY_FIELD'][$fieldName]);
			$fieldMappingTableFieldTpl->addvar('ERRORS', $messages);
			$fieldMappingTableFieldTpl->addvar('ERRORS_COUNT', count($_SESSION['IMPORT_ERRORS_BY_FIELD'][$fieldName]));
			$fieldMappingTableFieldTpl->addvar('ERRORS_PROCESS', $_SESSION['IMPORT_ERRORS_PROCESS']);
		}
		$tplTableFields = array();

		$fieldMapping = $preset->getFieldMappingByTableField($tableField->getFieldName(), $tableDef);

		$fieldMappingRenderedDisplayView = $fieldMappingRenderer->renderDisplayView($fieldMapping, $tableField->getFieldName(), $tableField->getTableDef());
		$fieldMappingRenderedEditView = $fieldMappingRenderer->renderEditView($fieldMapping, $tableField->getFieldName(), $tableField->getTableDef(), $initialCall);

		$tplTableFields = array_merge($tableField->asArray(), array(
			'FIELD_MAPPING_RENDERED_DISPLAY' => $fieldMappingRenderedDisplayView,
			'FIELD_MAPPING_RENDERED_EDIT' => $fieldMappingRenderedEditView
		));

		$fieldMappingTableFieldTpl->addvars($tplTableFields);

		return $fieldMappingTableFieldTpl->process();
	}

	/**
	 * @param $fieldName
	 * @param $tableDef
	 * @param $type
	 * @param $pos
	 * @param Ad_Import_Preset_AbstractPreset $preset
	 */
	public function addTableFieldMappingValue($fieldName, $tableDef, $type, $pos, $preset) {

		if(!class_exists($type) || !in_array('Ad_Import_Preset_Mapping_Value_MappingValueInterface', class_implements($type))) {
			throw new Exception("Could not add new mapping value of type $type , that does not implement Ad_Import_Preset_Mapping_Value_MappingValueInterface");
		}

		$tableField = $preset->getTableFieldByName($fieldName, $tableDef);
		$mappingValueToInsert = new $type();

		$currentMappingValue = ($preset->getFieldMappingByTableField($fieldName, $tableDef) == null)?array():$preset->getFieldMappingByTableField($fieldName, $tableDef)->getFieldValues();
		$newMappingValueList = array();

		if($pos < 0) { $newMappingValueList[] = $mappingValueToInsert; }

		for($i = 0; $i < count($currentMappingValue); $i++) {
			$newMappingValueList[] = $currentMappingValue[$i];

			if((int)$pos == $i) {
				$newMappingValueList[] = $mappingValueToInsert;
			}
		}
		if($pos > $i) { $newMappingValueList[] = $mappingValueToInsert; }


		$preset->mapField($tableField, $newMappingValueList);

		return true;
	}

	/**
	 * Löscht den zugeordneten MappingValue an Position $pos des angegebenen Feldes
	 *
	 * @param $fieldName
	 * @param $tableDef
	 * @param $pos
	 * @param $preset
	 *
	 * @return bool
	 */
	public function removeTableFieldMappingValue($fieldName, $tableDef, $pos, $preset) {

		$tableField = $preset->getTableFieldByName($fieldName, $tableDef);
		$currentMappingValue = ($preset->getFieldMappingByTableField($fieldName, $tableDef) == null)?array():$preset->getFieldMappingByTableField($fieldName, $tableDef)->getFieldValues();
		$newMappingValueList = array();

		for($i = 0; $i < count($currentMappingValue); $i++) {
			if((int)$pos != $i) {
				$newMappingValueList[] = $currentMappingValue[$i];
			}
		}

		$preset->mapField($tableField, $newMappingValueList);

		return true;
	}

	/**
	 * Speichert die zugeordneten MappingValue an Position $pos des angegebenen Feldes
	 *
	 * @param $fieldName
	 * @param $tableDef
	 * @param $pos
	 * @param $preset
	 *
	 * @return bool
	 */
	public function saveTableFieldMappingValue($fieldName, $tableDef, $pos, $value, $preset) {

		$tableField = $preset->getTableFieldByName($fieldName, $tableDef);
		$mappingValues = $preset->getFieldMappingByTableField($fieldName, $tableDef)->getFieldValues();
		$mappingValue = $mappingValues[$pos];

		if($mappingValue == null || !($mappingValue instanceof Ad_Import_Preset_Mapping_Value_MappingValueInterface)) {
			throw new Exception("Could not found Mapping Value at postion $pos");
		}

		$mappingValueClassName = get_class($mappingValue);
		$extractedClassIdent = preg_match("/\_([a-zA-Z0-9]+)$/", $mappingValueClassName, $tmp);

		if ($extractedClassIdent) {
			$saveHelperClassName = 'Ad_Import_PresetEditor_Helper_FieldMappingSaveHelper_' . $tmp['1'] . 'SaveHelper';
			if (!class_exists($saveHelperClassName) || !in_array('Ad_Import_PresetEditor_Helper_FieldMappingSaveHelper_SaveHelperInterface', class_implements($saveHelperClassName))) {
				throw new Exception("FieldMapping Save Helper $saveHelperClassName not found");
			}

			$saveHelper = new $saveHelperClassName();
			$saveHelper->save($mappingValue, $value['MAPPING'][$tableDef][$fieldName][$pos], $preset);
		}



		return true;
	}

	/**
	 * @param $fieldName
	 * @param $tableDef
	 * @param $value
	 * @param Ad_Import_Preset_AbstractPreset $preset
	 *
	 * @return bool
	 */
	public function saveDefaultTableFieldValue($fieldName, $tableDef, $value, $preset) {

		if(!is_array($value) && trim($value) == "") {
			$value = null;
		}
		if(is_array($value) && $value['0'] == "") {
			$value = null;
		}
		$tableField = $preset->getTableFieldByName($fieldName, $tableDef);
		$tableField->setDefaultValue($value);

		return true;
	}
}