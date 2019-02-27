<?php

/** @var Ad_Import_Preset_PresetManagement $importPresetManagement */
$importPresetManagement = Ad_Import_Preset_PresetManagement::getInstance($db);
/** @var Ad_Import_Process_ProcessManagement $importProcessManagement */
$importProcessManagement = Ad_Import_Process_ProcessManagement::getInstance($db);


$importId = isset($_GET['ID_IMPORT_PROCESS']) ? (int)$_GET['ID_IMPORT_PROCESS'] : null;
$sourceId = isset($_GET['ID_IMPORT_SOURCE'])? (int)$_GET['ID_IMPORT_SOURCE'] : null;

if ($importId == null) {

	$globalImportPresets = $importPresetManagement->fetchGlobalImportPresets(Ad_Import_Preset_PresetManagement::STATUS_ENABLED);
	$ownImportPresets = $importPresetManagement->fetchImportPresetsByUser($uid, Ad_Import_Preset_PresetManagement::STATUS_ENABLED);

	$sourceConfig = new Ad_Import_Source_SourceConfiguration();
	// Allow to add further options for sources
	Api_TraderApiHandler::getInstance($db)->triggerEvent(
		Api_TraderApiEvents::IMPORT_SOURCE_CREATE, $sourceConfig
	);
	
	$tpl_content->addvar("IMPORT_SOURCE_CONFIG_TEMPLATES", $sourceConfig->getConfigurationTemplates());
	
	if ($sourceId !== null) {
		$importSource = Ad_Import_Source_Source::getById($db, $sourceId);
		if ($importSource == null || $importSource->getUserId() != $uid) {
			throw new Exception("Could not load import source");
		}
        $_REQUEST['IMPORT_PRESET'] = $importSource->getPreset()->getImportPresetId();
		$tpl_content->addvar('ID_IMPORT_SOURCE', $importSource->getId());
		$tpl_content->addvar('SOURCE_NAME', $importSource->getName());
		$tpl_content->addvar('DOWNLOAD_URL', $importSource->getDownloadUrl());
		$tpl_content->addvar('DOWNLOAD_INTERVAL', $importSource->getDownloadInterval());
		$tpl_content->addvar('DOWNLOAD_NEXT', $importSource->getDownloadNext());
		$tpl_content->addvars( array_flatten($importSource->getOptions(), true, "_", "OPTIONS_") );
        if ($importSource->getPreset() instanceof Ad_Import_Preset_AbstractPreset) {
            $tpl_content->addvar('FK_IMPORT_PRESET', $importSource->getPreset()->getImportPresetId());
        }
		$tpl_content->addvar('NEW_IMPORT', 1);
	}

    $tplPresetList = array();
    $tplPresetList += $ownImportPresets;

    if(count($ownImportPresets) > 0 && count($globalImportPresets) > 0) {
        $tplPresetList += array('DELIMITER' => 1);
    }

    $tplPresetList += $globalImportPresets;

    foreach($tplPresetList as $key => $value) {
        if ($value['ID_IMPORT_PRESET'] == $_REQUEST['IMPORT_PRESET']) {
            $tplPresetList[$key]['IS_SELECTED'] = true;
        }
        $presetObject = unserialize($value["PRESET_CONFIG"]);
        $tplPresetList[$key]['REQUIRES_FILE'] = $presetObject->doRequireFile();
    }

    $tpl_content->addvars($_REQUEST);
    $tpl_content->addlist('liste_presets', $tplPresetList, 'tpl/'.$s_lang.'/my-import-run.preset_row.htm');
    $tpl_content->addvar('NEW_IMPORT', 1);

} elseif ($importId !== null) {
	$importProcess = $importProcessManagement->loadImportProcessById($importId);
	if($importProcess == null || $importProcess->getUserId() != $uid) {
		throw new Exception("Could not load import process");
	}

	$tpl_content->addvar('ID_IMPORT_PROCESS', $importProcess->getProcessId());
	$tpl_content->addvar('ID_IMPORT_PRESET', $importProcess->getPreset()->getImportPresetId());
    $tpl_content->addvar('CRON_STAT', $importProcess->getCronProcess());

	$markerTableHeaderCols = array();
	$dataFields = $importProcess->getPreset()->getDataFields();
	foreach($dataFields as $key => $dataField) {
		$markerTableHeaderCols[] = array('KEY' => $key, 'TH_NAME' => $dataField->getName());
	}

	$tpl_content->addlist('MARKER_TABLE_TH', $markerTableHeaderCols, 'tpl/'.$s_lang.'/my-import-run.datasets_table.th.htm');
}

if(isset($_REQUEST['DO'])) {
	require_once "my-import-run-action.php";
}

