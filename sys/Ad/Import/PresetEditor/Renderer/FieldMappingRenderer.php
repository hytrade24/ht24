<?php

class Ad_Import_PresetEditor_Renderer_FieldMappingRenderer {

	/** @var  Ad_Import_Preset_AbstractPreset */
	protected $preset;

	function __construct($preset) {
		$this->preset = $preset;
	}


	/**
	 * @param Ad_Import_Preset_Mapping_FieldMap $fieldMapping
	 */
	public function renderDisplayView($fieldMapping, $fieldName, $tableDef) {
		global $s_lang;

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.displayview.htm');
		$tpl->addvar('FIELD_NAME', $fieldName);
		$tpl->addvar('TABLE_DEF', $tableDef);

		if($fieldMapping == null || (($fieldMapping instanceof Ad_Import_Preset_Mapping_FieldMap) && count($fieldMapping->getFieldValues()) == 0)) {
			$tpl->addvar('NO_MAPPING', 1);
		} else {
			$valueOutput = $this->getValueOutput($fieldMapping, 'display');
			$tpl->addvar('VALUE_OUTPUT', implode('', $valueOutput));

		}

		$tableField = $this->preset->getTableFieldByName($fieldName, $tableDef);
		$tpl->addvar("FIELD_TYPE", $tableField->getType());
		$tpl->addvar("FIELD_TYPE_".strtoupper($tableField->getType()), 1);
		if($tableField->getDefaultValue() !== null) {
			$tpl->addvar('TABLE_FIELD_DEFAULTVALUE_ISSET', 1);
			if(is_array($tableField->getAcceptedValues()) && count($tableField->getAcceptedValues()) > 0) {
				if(!is_array($tableField->getDefaultValue())) {
					$intersectArray = array($tableField->getDefaultValue() => 1);
				} else {
					$intersectArray = array_flip($tableField->getDefaultValue());
				}

				$tpl->addvar('TABLE_FIELD_DEFAULTVALUE', implode(', ', array_intersect_key($tableField->getAcceptedValues(), $intersectArray)));
			} else {
				$tpl->addvar('TABLE_FIELD_DEFAULTVALUE', $tableField->getDefaultValue());
			}
		}

		return $tpl->process();
	}

	/**
	 * @param Ad_Import_Preset_Mapping_FieldMap $fieldMapping
	 */
	public function renderEditView($fieldMapping, $fieldName, $tableDef, $initialCall = false) {
		global $s_lang;

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.editview.htm');
		$tpl->addvar('FIELD_NAME', $fieldName);
		$tpl->addvar('TABLE_DEF', $tableDef);

		if( !$initialCall) {
			if ($fieldMapping != NULL) {
				$valueOutput = $this->getValueOutput($fieldMapping, 'edit');
				$tpl->addvar('VALUE_OUTPUT', implode('', $valueOutput));
			}

			// default Value
			$tpl->addvar('DEFAULT_VALUE_INPUT', $this->getDefaultValueInput($fieldMapping, $fieldName, $tableDef));
		}
		$tpl->addvar('INITIAL_CALL', $initialCall);

		return $tpl->process();

	}

	/**
	 * @param $fieldMapping
	 * @param $type
	 */
	protected function getValueOutput($fieldMapping, $type = 'display') {
		$valueOutput = array();

		$mappingFieldValues = $fieldMapping->getFieldValues();
		/** @var  Ad_Import_Preset_Mapping_Value_AbstractMappingValue $mappingFieldValue */
		foreach ($mappingFieldValues as $key => $mappingFieldValue) {

			$mappingClassName = get_class($mappingFieldValue);
			$extractedClassIdent = preg_match("/\_([a-zA-Z0-9]+)$/", $mappingClassName, $tmp);

			if ($extractedClassIdent) {
				$rendererClassName = 'Ad_Import_PresetEditor_Renderer_FieldMapping_' . $tmp['1'] . 'Renderer';
				if (!class_exists($rendererClassName) || !in_array('Ad_Import_PresetEditor_Renderer_FieldMapping_FieldMappingValueRendererInterface', class_implements($rendererClassName))) {
					$valueOutput[] = 'renderer for class ' . $rendererClassName . ' not found';
				}

				$valueRenderer = new $rendererClassName($this->preset);

				switch($type) {
					case 'edit':
						$valueOutput[] = $valueRenderer->renderEditView($mappingFieldValue, $fieldMapping); break;
					default:
						$valueOutput[] = $valueRenderer->renderDisplayView($mappingFieldValue, $fieldMapping);
				}
			}
		}

		return $valueOutput;
	}

	/**
	 * @param Ad_Import_Preset_Mapping_FieldMap $fieldMapping
	 * @param string $fieldName
	 * @param string $tableDef
	 */
	protected function getDefaultValueInput($fieldMapping, $fieldName, $tableDef) {
		global $s_lang;

		$tableField = $this->preset->getTableFieldByName($fieldName, $tableDef);

		$templateUsedForType = 'text';
		switch($tableField->getType()) {
			case 'CHECKBOX':
				$templateUsedForType = 'checkbox'; break;
			case 'LIST':
			case 'MULTICHECKBOX':
			case 'MULTICHECKBOX_AND':
			case 'VARIANT':
				$templateUsedForType = 'list'; break;
		}

		if(is_array($tableField->getAcceptedValues()) && count($tableField->getAcceptedValues()) > 0) {
			$templateUsedForType = 'list';
		}

		if($tableField->getFieldName() == 'FK_KAT' ) {
			$templateUsedForType = 'category';
		} else if ($tableField->getFieldName() == 'MWST' ) {
			$templateUsedForType = 'list';
			$tableField->setAcceptedValues(array(
				0 => Translation::readTranslation("marketplace", "vat.difference.long", null, array(), "Differenzbesteuerung nach §25a UstG"),
				1 => Translation::readTranslation("marketplace", "vat.included.long", null, array(), "Preis enthält Mehrwertsteuer"),
				2 => Translation::readTranslation("marketplace", "vat.private.long", null, array(), "Privatverkauf")
			));
		}

		$tpl = new Template("tpl/".$s_lang."/my-import-presets-edit.fieldmappingrenderer.defaultinput.".$templateUsedForType.".htm");


		switch($templateUsedForType) {
			case 'list':
				$tplAcceptedValues = array();
				foreach($tableField->getAcceptedValues() as $accpetedValuesKey => $accpetedValuesValue) {
					$isSelected = ($tableField->getDefaultValue() !== null) &&
						(((int)$accpetedValuesKey === (int)$tableField->getDefaultValue()) || (is_array($tableField->getDefaultValue()) && in_array($accpetedValuesKey, $tableField->getDefaultValue())));
					$tplAcceptedValues[$accpetedValuesKey] = array('KEY' => $accpetedValuesKey, 'VALUE' => $accpetedValuesValue, 'IS_SELECTED' => $isSelected);
				}

				if($tableField->getType() == 'MULTICHECKBOX' || $tableField->getType() == 'MULTICHECKBOX_AND' || $tableField->getType() == 'VARIANT') {
					$tpl->addvar('MULTIPLE', 1);
				}

				$tpl->addlist('SELECT_ACCEPTED_VALUES', $tplAcceptedValues, 'tpl/'.$s_lang.'/my-import-presets-edit.fieldmappingrenderer.defaultinput.list.row.htm' );
				break;
			case 'checkbox':
				if ($tableField->getDefaultValue() !== null) {
					if((int)$tableField->getDefaultValue() === 1) {
						$tpl->addvar('DEFAULT_VALUE_1', 1);
					} elseif((int)$tableField->getDefaultValue() === 0) {
						$tpl->addvar('DEFAULT_VALUE_0', 1);
					}
				}
				break;
			case 'category':
			default:
				$tpl->addvar('DEFAULT_VALUE', $tableField->getDefaultValue());
		}


		$tpl->addvar('FIELD_NAME', $fieldName);
		$tpl->addvar('TABLE_DEF', $tableDef);

		return $tpl->process();

	}
}