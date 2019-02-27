<?php

class Ad_Import_PresetEditor_Step_TypeStep extends Ad_Import_PresetEditor_Step_AbstractPresetEditorStep {



	public function save($data) {
		$dataPost = $data['POST'];
		$dataFiles = $data['FILES'];
		$resetPreset = false;

		$validationResult = $this->validate($data);
		if(!$validationResult->isSuccess()) {
			return $validationResult;
		}

		if($this->presetEditor->getPreset()->doResetOnSave() || !empty($dataFiles['PRESET_FILE']['tmp_name']) || !empty($data['POST']['PRESET_FILE_URL'])) {
			$resetPreset = true;

			$this->presetEditor->setPresetType($dataPost['PRESET_TYPE']);
			$this->presetEditor->reset();
			
		}
		switch ($dataPost['PRESET_TYPE_CONFIG']["fileDelimiter"]) {
			case "_comma":
				$dataPost['PRESET_TYPE_CONFIG']["fileDelimiter"] = ",";
				break;
			case "_semicolon":
				$dataPost['PRESET_TYPE_CONFIG']["fileDelimiter"] = ";";
				break;
			case "_tab":
				$dataPost['PRESET_TYPE_CONFIG']["fileDelimiter"] = "\t";
				break;
			default:
				break;
		}
		$this->presetEditor->getPreset()->setConfigurationOptions($dataPost['PRESET_TYPE_CONFIG']);

		if(isset($dataFiles['PRESET_FILE']) && !empty($dataFiles['PRESET_FILE']['tmp_name'])) {
			// File Upload
			$tempFile = tempnam(sys_get_temp_dir(), 'EBIZ_IMPORT_PRESET_');
			move_uploaded_file($dataFiles['PRESET_FILE']['tmp_name'], $tempFile);
			$this->presetEditor->getPreset()->loadFile($tempFile);

		} elseif(!empty($data['POST']['PRESET_FILE_URL'])) {
			// File Download
			$this->presetEditor->getPreset()->loadFileUrl($dataPost['PRESET_FILE_URL']);
		} else {
			$this->presetEditor->getPreset()->loadCustom();
		}


		$this->presetEditor->getPreset()->setPresetName($dataPost['PRESET_NAME']);
		$this->presetEditor->getPreset()->loadTableFields();

		if($resetPreset == true) {
			$this->presetEditor->getPreset()->autoMapFields();
		} else {
			$arMapping = $this->presetEditor->getPreset()->getFieldMapping();
			if (empty($arMapping)) {
				$this->presetEditor->getPreset()->autoMapFields();
			}
		}

		return $validationResult;
	}

	public function load() {
		global $s_lang;

		$flashMessages = $this->presetEditor->getFlashMessages();

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.step.type.htm');

		if($flashMessages) {
			$tpl->addvar("FLASHMESSAGES", implode('<br>', $flashMessages));
			$this->presetEditor->resetFlashMessages();
		}

		$data = array(
			'PRESET_NAME' => $this->presetEditor->getPreset()->getPresetName(),
			'PRESET_TYPE' => $this->presetEditor->getPresetType(),
			'ID_IMPORT_PRESET' => $this->presetEditor->getPreset()->getImportPresetId()
		);


		if(isset($_POST)) {
			$data = array_merge($data, array_flatten($data), $_POST, array_flatten($_POST));
		}

        $tpl->addvar("eBayConfigured", (Api_Ebay::getSessionId() !== false ? 1 : 0));
		$tpl->addvar('UPLOAD_MAX_FILESIZE', Tools_Utility::getUploadMaxFilsize());
		$tpl->addvars($data);

		if($this->presetEditor->getPreset() != null) {
			$presetConfig = $this->presetEditor->getPreset()->getConfiguration();
			$tplTypeConfigVars = array_flatten($presetConfig, true, '_', 'PRESET_TYPE_CONFIG_');
			$tpl->addvars($tplTypeConfigVars);
			switch ($presetConfig["fileDelimiter"]) {
				case ",":
					$tpl->addvar("PRESET_TYPE_CONFIG_fileDelimiter_comma", 1);
					break;
				case ";":
					$tpl->addvar("PRESET_TYPE_CONFIG_fileDelimiter_semicolon", 1);
					break;
				case "\t":
					$tpl->addvar("PRESET_TYPE_CONFIG_fileDelimiter_tab", 1);
					break;
				default:
					$tpl->addvar("PRESET_TYPE_CONFIG_fileDelimiter_other", 1);
					break;
			}
			/** @var Template $configurationTemplate */
            foreach ($this->presetEditor->getPreset()->getConfigurationTemplates() as $configurationTemplate) {
                $configurationTemplate->LoadText( $configurationTemplate->filename );
                $configurationTemplate->vars = $tpl->vars;
			}
			$tpl->addvar("PRESET_TYPE_CONFIG_TEMPLATES", $this->presetEditor->getPreset()->getConfigurationTemplates());
		}

		return $tpl->process();
	}


	/**
	 * @param $data
	 *
	 * @return Ad_Import_PresetEditor_FormResult
	 */
	protected function validate($data) {
		$formResult = new Ad_Import_PresetEditor_FormResult();

		if(empty($data['POST']['PRESET_TYPE'])) {
			$formResult->setFailed();
			$formResult->addError(Translation::readTranslation('marketplace', 'import.preset.editor.type.error.typeemtpy', null, array(), 'Es wurde kein Vorlagen Typ gewählt'));
		}

		if(empty($data['POST']['PRESET_NAME'])) {
			$formResult->setFailed();
			$formResult->addError(Translation::readTranslation('marketplace', 'import.preset.editor.type.error.nameempty', null, array(), 'Es wurde keine Vorlagen Bezeichnung angegeben'));
		}

        $this->presetEditor->getPreset()->validate($formResult);

		if($this->presetEditor->getPreset()->getImportPresetId() == null) {

			$fileBasedPresets = array("Csv", "Xml");
			if (in_array($data['POST']['PRESET_TYPE'], $fileBasedPresets)) {
				if (!(isset($data['FILES']['PRESET_FILE']) && !empty($data['FILES']['PRESET_FILE']['tmp_name'])) && !(isset($data['POST']['PRESET_FILE_URL']) && !empty($data['POST']['PRESET_FILE_URL']))) {
					$formResult->setFailed();
					$formResult->addError(Translation::readTranslation('marketplace', 'import.preset.editor.type.error.nofile', NULL, array(), 'Es wurde keine Vorlagen Datei ausgewählt'));
				}
	
				if (!(isset($dataFiles['PRESET_FILE']) && !empty($dataFiles['PRESET_FILE']['tmp_name'])) && (isset($data['POST']['PRESET_FILE_URL']) && !empty($data['POST']['PRESET_FILE_URL']))) {
					// Url but no file Upload
					$urlResource = @fopen($data['POST']['PRESET_FILE_URL'], 'r');
					if ($urlResource == FALSE) {
						$formResult->setFailed();
						$formResult->addError(Translation::readTranslation('marketplace', 'import.preset.editor.type.error.fileurlnotfound', NULL, array(), 'Die angegebende Vorlagen Url konnte nicht geladen werden'));
					}
				}
			}
		}

		return $formResult;
	}
}