<?php

require_once $ab_path.'sys/lib.ad_create.php';

class Ad_Import_Process_Validation_ValidationManagement {

	/** @var  Ad_Import_Process_Process */
	protected $importProcess;

	/** @var  Ad_Import_Preset_AbstractPreset */
	protected $preset;

	/** @var  array */
	protected $fieldMapping;

	protected $fieldErrorMessagesCache = array();

	protected $requiredFieldsinCategory = array();


	/**
	 * @param $importProcess
	 */
	function __construct($importProcess) {
		global $db;

		$presetManagement = Ad_Import_Preset_PresetManagement::getInstance($db);

		$this->importProcess = $importProcess;
		$this->preset = $this->importProcess->getPreset();
		$this->requiredFieldsinCategory = $presetManagement->getRequiredCategoryFields(1);
		$this->notRequiredFieldsinCategory = $presetManagement->getRequiredCategoryFields(0);

		$this->tableFields = $this->preset->getTableFieldsByTableDef();

		$_SESSION['EBIZ_TRADER_AD_CREATE'] = null;
		$this->adCreate = new AdCreate($db);
	}


	public function validate($dataset, $categoryTable) {
		$this->fieldErrorMessagesCache = array();

		$masterTableFields = $this->tableFields['artikel_master'];
		$articleTableFields = $this->tableFields[$categoryTable];
		$validationResult = true;
		$invalidFields = array();
		$invalidFieldsMaster = false;
		$invalidFieldsArticle = false;

		if ($this->importProcess->getConfigurationOption("affiliateImport") !== null) {
			// Validate Fields
			/** @var Ad_Import_Preset_Mapping_TableField $tableField */
			foreach($masterTableFields as $key => $tableField) {
	
				$fieldValidationResult = $this->validateByField($dataset, $tableField);
				$validationResult = $fieldValidationResult ? $validationResult : false;
				if(!$fieldValidationResult && !in_array($tableField->getFieldName(), $invalidFields)) {
					$invalidFieldsMaster = true;
					if (!in_array($tableField->getFieldName(), $invalidFields)) {
						$invalidFields[] = $tableField->getFieldName();
					}
				}
			}
	
			/** @var Ad_Import_Preset_Mapping_TableField $tableField */
			foreach($articleTableFields as $key => $tableField) {
				if (!$tableField->getIsMaster()) {
					$fieldValidationResult = $this->validateByField($dataset, $tableField);
					$validationResult = $fieldValidationResult ? $validationResult : false;
					if(!$fieldValidationResult) {
						$invalidFieldsArticle = true;
						if (!in_array($tableField->getFieldName(), $invalidFields)) {
							$invalidFields[] = $tableField->getFieldName();
						}
					}
				}
			}
		}

		if($validationResult == false) {
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, $this->fieldErrorMessagesCache);

			$links = array();
			if ($invalidFieldsMaster) {
				$links[] = '<a href="my-import-presets-edit.htm?DO=EDIT&ID_IMPORT_PRESET='.$this->preset->getImportPresetId().'&STEP=2&ID_IMPORT_PROCESS='.$this->importProcess->getProcessId().'">'.
					Translation::readTranslation("marketplace", "my.import.link.edit.base", null, array(), 'Basis Zuweisung bearbeiten').
				'</a>';
			}
			if ($invalidFieldsArticle) {
				$links[] = ' <a href="my-import-presets-edit.htm?DO=EDIT&ID_IMPORT_PRESET='.$this->preset->getImportPresetId().'&STEP=4&ID_IMPORT_PROCESS='.$this->importProcess->getProcessId().'">'.
					Translation::readTranslation("marketplace", "my.import.link.edit.details", null, array(), 'Feld Zuordnung bearbeiten').
				'</a>';
			}

			$this->importProcess->log(Translation::readTranslation(
					'marketplace', 'import.process.fieldvalidation.failed.', null,
					array(
						'FIELD_NAMES' => "'".implode(', ', $invalidFields)."'",
						'DATASET_NAME' => "'".$dataset['PRODUKTNAME']."'",
						'DATASET_IDENT' => (!empty($dataset['IMPORT_IDENTIFIER'])?"'(".$dataset['IMPORT_IDENTIFIER'].")'":'""'),
					),
					'<span class="text-error">Datensatz {DATASET_NAME} {DATASET_IDENT} ist ungültig</span><br>Die Felder {FIELD_NAMES} sind erforderlich oder besitzen einen ungültigen Wert!'
				).' '.implode(' / ', $links), Ad_Import_Process_Process::LOG_WARNING);
		}

		return $validationResult;
	}

	/**
	 * @param $dataset
	 * @param Ad_Import_Preset_Mapping_TableField $tableField
	 */
	protected function validateByField($dataset, $tableField) {
		$validationResult = true;
		$valueExploded = false;
		$fieldValue = $dataset[$tableField->getFieldName()];

		$categoryId = $dataset['FK_KAT'];
		$reqSearchKey = $tableField->getTableDef().'-'.$tableField->getFieldName();

		$fieldIsRequired = null;
		if(array_key_exists($reqSearchKey,$this->requiredFieldsinCategory)) {
			if(in_array($categoryId, explode(',', $this->requiredFieldsinCategory[$reqSearchKey]))) {
				$fieldIsRequired = true;
			}
		}

		if(array_key_exists($reqSearchKey,$this->notRequiredFieldsinCategory)) {
			if(in_array($categoryId, explode(',', $this->notRequiredFieldsinCategory[$reqSearchKey]))) {
				$fieldIsRequired = false;
			}
		}

		if($fieldIsRequired === null) {
			$fieldIsRequired = $tableField->getIsRequired();
		}

		if(in_array($tableField->getType(), array('MULTICHECKBOX', 'MULTICHECKBOX_AND', 'VARIANT')) && strpos($fieldValue, 'x') === 0) {
			$valueExploded = true;
			$fieldValue = explode("x", trim($fieldValue, "x"));
		}


		switch($tableField->getFieldName()) {
			case 'FK_MAN':
				break;

			default:
				$adCreateValidationResult = $this->adCreate->validateField($tableField->getFieldName(), $fieldValue, $fieldIsRequired, $tableField->getType());
				if($adCreateValidationResult['valid'] == 0) {
					$validationResult = false;
					if (!array_key_exists($tableField->getFieldName(), $this->fieldErrorMessagesCache)) {
						$this->fieldErrorMessagesCache[ $tableField->getFieldName() ] = array();
					}
					$this->fieldErrorMessagesCache[ $tableField->getFieldName() ][] = $adCreateValidationResult['error_msg'];
				}
				break;
		}


		if($tableField->isListTypeField() || $tableField->isCheckAcceptedValues()) {
			if(!is_array($fieldValue)) {
				if(!array_key_exists($fieldValue, $tableField->getAcceptedValues()) && (!empty($fieldValue))) {
					$validationResult = false;

					if (!array_key_exists($tableField->getFieldName(), $this->fieldErrorMessagesCache)) {
						$this->fieldErrorMessagesCache[ $tableField->getFieldName() ] = array();
					}
					$this->fieldErrorMessagesCache[ $tableField->getFieldName() ][] = Translation::readTranslation('marketplace', 'import.validation.error.value.not.accepted', null, array('FIELD' => "'".$tableField->getFieldName()."'", 'FIELD_VALUE' => "'".$fieldValue."'"), 'Der Wert des Feldes "{FIELD_VALUE}" ist kein gültiger Wert');
				}
			} else {
				foreach($fieldValue as $key => $tmpValue) {
					if(!array_key_exists($tmpValue, $tableField->getAcceptedValues()) && (!empty($tmpValue))) {
						$validationResult = false;

						if (!array_key_exists($tableField->getFieldName(), $this->fieldErrorMessagesCache)) {
							$this->fieldErrorMessagesCache[ $tableField->getFieldName() ] = array();
						}
						$this->fieldErrorMessagesCache[ $tableField->getFieldName() ][] = Translation::readTranslation('marketplace', 'import.validation.error.value.not.accepted', null, array('FIELD' => "'".$tableField->getFieldName()."'", 'FIELD_VALUE' => "'".$tmpValue."'"), 'Der Wert des Feldes "{FIELD_VALUE}" ist kein gültiger Wert');
					}
				}
			}
		}


		if($validationResult == false) {
			if ($tableField->getIsMaster()) {
				$this->fieldErrorMessagesCache[] = '<a href="my-import-presets-edit.htm?DO=EDIT&ID_IMPORT_PRESET='.$this->preset->getImportPresetId().'&STEP=2&ID_IMPORT_PROCESS='.$this->importProcess->getProcessId().'">'.
					Translation::readTranslation("marketplace", "my.import.link.edit.base", null, array(), 'Basis Zuweisung bearbeiten').
				'</a>';
			} else {
				$this->fieldErrorMessagesCache[] = '<a href="my-import-presets-edit.htm?DO=EDIT&ID_IMPORT_PRESET='.$this->preset->getImportPresetId().'&STEP=4&ID_IMPORT_PROCESS='.$this->importProcess->getProcessId().'">'.
					Translation::readTranslation("marketplace", "my.import.link.edit.details", null, array(), 'Feld Zuordnung bearbeiten').
				'</a>';
			}
			$invalidFields[] = $tableField->getFieldName();
		}



		return $validationResult;
	}


}