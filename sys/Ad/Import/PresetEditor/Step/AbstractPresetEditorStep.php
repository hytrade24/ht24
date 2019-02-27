<?php

abstract class Ad_Import_PresetEditor_Step_AbstractPresetEditorStep implements Ad_Import_PresetEditor_Step_PresetEditorStepInterface {

	/** @var  Ad_Import_PresetEditor_PresetEditorManagement */
	protected $presetEditor;

	function __construct($presetEditor) {
		$this->presetEditor = $presetEditor;
	}
}