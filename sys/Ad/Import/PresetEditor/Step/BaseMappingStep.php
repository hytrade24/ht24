<?php

class Ad_Import_PresetEditor_Step_BaseMappingStep extends Ad_Import_PresetEditor_Step_AbstractMappingStep {


	public function save($data) {
		$dataPost = $data['POST'];
		$dataFiles = $data['FILES'];

		$validationResult = $this->validate($data);
		if(!$validationResult->isSuccess()) {
			return $validationResult;
		}


		$this->presetEditor->getPreset()->setCategoryField(null);

		$categoryTableFieldMapping = $this->presetEditor->getPreset()->getFieldMappingByTableField('FK_KAT');
		if(($categoryTableFieldMapping instanceof Ad_Import_Preset_Mapping_FieldMap) && is_array($categoryTableFieldMapping->getFieldValues())) {
			foreach ($categoryTableFieldMapping->getFieldValues() as $key => $value) {
				if ($value instanceof Ad_Import_Preset_Mapping_Value_DataFieldMappingValue) {
					$this->presetEditor->getPreset()->addCategoryField($value->getValue());
				}
			}
		}
		if($this->presetEditor->getPreset()->getCategoryField() != null) {
			$this->presetEditor->getPreset()->setCategoryDataValues(array());
			$this->presetEditor->getPreset()->loadDataCategories();
		} else {
			$this->presetEditor->getPreset()->setCategoryMapping(array());
			$this->presetEditor->getPreset()->setCategoryDataValues(array());
		}

		return $validationResult;
	}

	/**
	 * @param	Ad_Import_Preset_Mapping_TableField	$field
	 * @return bool
	 */
	public function isFieldVisible($field) {
		switch ($field->getFieldName()) {
			case 'FK_MAN':
			case 'FK_PRODUCT':
				// Only display if manufacturer database is enabled
				return ($GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["USE_PRODUCT_DB"] ? true : false);
		}
		return true;
	}

	public function load() {
		global $s_lang;


		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.step.basemapping.htm');

		$flashMessages = $this->presetEditor->getFlashMessages();
		if($flashMessages) {
			$tpl->addvar("FLASHMESSAGES", implode('<br>', $flashMessages));
			$this->presetEditor->resetFlashMessages();
		}


		$preset = $this->presetEditor->getPreset();
		if (!$preset->isTableFieldsUpToDate()) {
		    $preset->loadTableFields();
        }
		$tableFields = $preset->getTableFieldsByTableDef();
		$masterTableFields = $tableFields['artikel_master'];
		$tplMasterTableFieldsOutput = '';


		/** @var Ad_Import_Preset_Mapping_TableField $masterTableField	 */
		foreach($masterTableFields as $key => $masterTableField) {
			if ($masterTableField->getIsImport() && $this->isFieldVisible($masterTableField)) {
				$tplMasterTableFieldsOutput .= $this->renderTableFieldMapping($masterTableField->getFieldName(), 'artikel_master', $preset, true);
			}
		}
		
		$tpl->addvar('BASEMAPPING_SPECIAL', $preset->getStepSpecial("BaseMapping"));
		$tpl->addvar('STEP_MAX', $preset->getStepMax());
		$tpl->addvar('TABLE_FIELDS', $tplMasterTableFieldsOutput);
		$tpl->addvars($_POST);
		$tpl->addvar('ID_IMPORT_PRESET', $this->presetEditor->getPreset()->getImportPresetId());


		return $tpl->process();
	}


	/**
	 * @param $data
	 *
	 * @return Ad_Import_PresetEditor_FormResult
	 */
	protected function validate($data) {
		$formResult = new Ad_Import_PresetEditor_FormResult();
		$preset = $this->presetEditor->getPreset();


		// check if category Field has default value or is mapped
		if($preset->getTableFieldByName('FK_KAT')->getDefaultValue() == '' && ($preset->getFieldMappingByTableField('FK_KAT') == null || !$preset->getFieldMappingByTableField('FK_KAT')->isValueMapped())) {
			$formResult->setFailed();
			$formResult->addError(Translation::readTranslation('marketplace', 'import.preset.editor.map.error.fkkatempty', null, array(), 'Sie müssen dem Feld Zugeordnete Kategorie einen Wert zuweisen'));
		}

		// check if category Field has default value or is mapped
		if($preset->getTableFieldByName('PRODUKTNAME')->getDefaultValue() == '' && ($preset->getFieldMappingByTableField('PRODUKTNAME') == null || !$preset->getFieldMappingByTableField('PRODUKTNAME')->isValueMapped())) {
			$formResult->setFailed();
			$formResult->addError(Translation::readTranslation('marketplace', 'import.preset.editor.map.error.produktnameempty', null, array(), 'Sie müssen dem Feld Produktname einen Wert zuweisen'));
		}

		return $formResult;
	}
}