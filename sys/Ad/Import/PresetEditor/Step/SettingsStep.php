<?php

class Ad_Import_PresetEditor_Step_SettingsStep extends Ad_Import_PresetEditor_Step_AbstractPresetEditorStep {



	public function save($data) {
		global $tpl_main;

		$dataPost = $data['POST'];
		$dataFiles = $data['FILES'];

		$validationResult = $this->validate($data);
		if(!$validationResult->isSuccess()) {
			return $validationResult;
		}

		$result = $this->presetEditor->saveToDatabase();
		$this->presetEditor->destroy();
		if($result) {
			die(forward($tpl_main->tpl_uri_action("my-import-presets")));
		} else {
			$validationResult->setFailed();
			$validationResult->addError(Translation::readTranslation('marketplace', 'import.preset.editor.setting.error.savefailed', null, array(), 'Die Vorlage konnte nicht gespeichert werden'));
		}


		return $validationResult;
	}

	public function load() {
		global $s_lang;

		$flashMessages = $this->presetEditor->getFlashMessages();

		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.step.settings.htm');

		if($flashMessages) {
			$tpl->addvar("FLASHMESSAGES", implode('<br>', $flashMessages));
			$this->presetEditor->resetFlashMessages();
		}

		$data = array(
			'PRESET_NAME' => $this->presetEditor->getPreset()->getPresetName(),
			'PRESET_TYPE' => $this->presetEditor->getPresetType(),
		);


		if(isset($_POST)) {
			$data = array_merge($data, $_POST);
		}

		$tpl->addvars($data);

		return $tpl->process();
	}


	/**
	 * @param $data
	 *
	 * @return Ad_Import_PresetEditor_FormResult
	 */
	protected function validate($data) {
		$formResult = new Ad_Import_PresetEditor_FormResult();


		return $formResult;
	}
}