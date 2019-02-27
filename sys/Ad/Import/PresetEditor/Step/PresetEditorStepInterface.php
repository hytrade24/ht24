<?php

interface Ad_Import_PresetEditor_Step_PresetEditorStepInterface {

	/**
	 * @param $data
	 *
	 * @return Ad_Import_PresetEditor_FormResult
	 */
	public function save($data);
	public function load();
} 