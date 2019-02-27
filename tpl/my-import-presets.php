<?php


$adImportPresetManagement = Ad_Import_Preset_PresetManagement::getInstance($db);
/** @var Ad_Import_PresetEditor_PresetEditorManagement $importPresetEditorManagement */
$importPresetEditorManagement = Ad_Import_PresetEditor_PresetEditorManagement::getInstance($db);


$globalImportPresets = $adImportPresetManagement->fetchGlobalImportPresets(Ad_Import_Preset_PresetManagement::STATUS_ENABLED);
$ownImportPresets = $adImportPresetManagement->fetchImportPresetsByUser($uid);


$tpl_content->addlist('liste_global', $globalImportPresets, 'tpl/'.$s_lang.'/my-import-presets.row_global.htm');
$tpl_content->addlist('liste_own', $ownImportPresets, 'tpl/'.$s_lang.'/my-import-presets.row_own.htm');

if($importPresetEditorManagement->isActiveSession()) {
	$tpl_content->addvar('ACTIVE_SESSION', 1);
	$tpl_content->addvar('ACTIVE_SESSION_NAME', ($importPresetEditorManagement->getPreset() != null)?$importPresetEditorManagement->getPreset()->getPresetName():'');
} else {
	$importPresetEditorManagement->destroy();
}


if(isset($_REQUEST['MESSAGE'])) {
	$tpl_content->addvar('MESSAGE', $_REQUEST['MESSAGE']);
}
if(isset($_REQUEST['MESSAGE_ERROR'])) {
	$tpl_content->addvar('MESSAGE_ERROR', $_REQUEST['MESSAGE_ERROR']);
}

