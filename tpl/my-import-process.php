<?php

$sourceId = ($ar_params[1] > 0 ? (int)$ar_params[1] : null);

$perpage = 15;
$npage = ($ar_params[2] ? (int)$ar_params[2] : 1);
$showUploadTab = ($ar_params[3] !== null ? (int)$ar_params[3] : "auto");

if ($sourceId == null) {
	$tpl_content->LoadText("tpl/".$s_lang."/my-import-process.sources.htm");
	$importSourceManagement = Ad_Import_Source_SourceManagement::getInstance($db);
	
	$importSourceCount = 0;
	$importSourceList = $importSourceManagement->fetchAllByParam(array(
		'FIELD_PRESET_NAME' => true,
		'FK_USER' => $uid,
		'LIMIT' => $perpage,
		'OFFSET' => ($npage-1)*$perpage
	), $importSourceCount);

	foreach ( $importSourceList as $index => $row ) {
		$importSourceAdCount = $db->fetch_atom(
			"SELECT COUNT(*) FROM `ad_master` WHERE IMPORT_SOURCE=".$row["ID_IMPORT_SOURCE"]
		);
		$importSourceList[$index]["SOURCE_AD_COUNT"] = $importSourceAdCount;
	}
	
	
	$tpl_content->addlist("liste", $importSourceList, "tpl/".$s_lang."/my-import-process.sources.row.htm");
	$tpl_content->addvar("pager", htm_browse_extended($importSourceCount, $npage, "my-import-process,,{PAGE}", $perpage));
	$tpl_content->addvar("all", $importSourceCount);
} else {
	$importSource = Ad_Import_Source_Source::getById($db, $sourceId);
	$importProcessManagement = Ad_Import_Process_ProcessManagement::getInstance($db);
	
	if ($importSource->getUserId() !== $uid) {
		die(forward($tpl_content->tpl_uri_action("my-import-process")));
	}
	
	if (array_key_exists("do", $_REQUEST)) {
		switch ($_REQUEST["do"]) {
			case "deleteAds":
				$time_start = microtime(true);
				$targetAds = $db->fetch_nar("SELECT ID_AD_MASTER, AD_TABLE FROM `ad_master` WHERE IMPORT_SOURCE=".$sourceId);
				Ad_Marketplace::deleteAdsEx($targetAds);
				$messageOk = Translation::readTranslation("marketplace", "import.source.delete.ads.success", null, array(), "Alle Anzeigen aus dieser Import-Quelle wurden gelöscht.");
				die(forward($tpl_content->tpl_uri_action("my-import-process,".$sourceId)."?MESSAGE=".$messageOk));
			case "getUploadStatus":
				$arStatus = array(
					"bytesDownloaded"	=> 0,
					"bytesPerSecond"	=> 0,
					"downloadStarted"	=> time(),
					"downloadDuration"	=> 0
				);
				if (array_key_exists("_importURLTarget", $_SESSION) && array_key_exists($sourceId, $_SESSION["_importURLTarget"])) {
					$sourceFile = $_SESSION["_importURLTarget"][$sourceId];
					$arStatus["downloadStarted"] = $sourceFile["time"];
					$arStatus["downloadDuration"] = (time() - $arStatus["downloadStarted"]);
					$arStatus["bytesDownloaded"] = filesize($sourceFile["file"]);
					$arStatus["bytesPerSecond"] = round($arStatus["bytesDownloaded"] / ($arStatus["downloadDuration"] + 0.1)); 
				}
				header("Content-Type: application/json");
				die(json_encode($arStatus));
		}
	}
	
	$importSourceAdCount = $db->fetch_atom("SELECT COUNT(*) FROM `ad_master` WHERE IMPORT_SOURCE=".$sourceId);
	$tpl_content->addvar("SOURCE_AD_COUNT", $importSourceAdCount);
	
	if (!empty($_POST) || !empty($_FILES) || array_key_exists("STEP", $_REQUEST)) {

		if ($importSource->getPreset()->doRequirePreperation() || $_POST["PREPERATION_DONE"]) {
			$step = "init";
			if (array_key_exists("STEP", $_REQUEST)) {
				$step = $_REQUEST["STEP"];
			} else {
				$_SESSION["importSettings"] = (array_key_exists("SETTINGS", $_POST) ? $_POST["SETTINGS"] : array());
				die(forward($tpl_content->tpl_uri_action('my-import-process,'.$sourceId).'?STEP='.$step));
			}
			$tplSpecial = $importSource->getPreset()->getStepSpecial($step, $sourceId);
			if ($tplSpecial instanceof Template) {
				$tpl_content = $tplSpecial;
				$tpl_content->addvar("SOURCE_ID", $sourceId);
				return;
			} else if ($step == "finish") {
				$importSource->getPreset()->finishPreperation();
				$_POST["SETTINGS"] = (array_key_exists("importSettings", $_SESSION) ? $_SESSION["importSettings"] : array());
			}
		}

		$importProcess = null;
		if (array_key_exists("PRESET_FILE", $_FILES) && ($_FILES["PRESET_FILE"]["size"] > 0)) {
			// File upload
			$importProcess = $importSource->createProcessFromFile($_FILES["PRESET_FILE"], $_POST["SETTINGS"]);
		} elseif (array_key_exists("PRESET_FILE_URL", $_POST)) {
			// URL upload
			$importProcess = $importSource->createProcessFromUrl($_POST["PRESET_FILE_URL"], $_POST["SETTINGS"]);
			header("Content-Type: application/json");
			die(json_encode(array(
				"success" => true, "url" => $tpl_content->tpl_uri_action('my-import-run').'?ID_IMPORT_PROCESS='.$importProcess->getProcessId()
			)));
		} elseif (!$importSource->getPreset()->doRequireFile()) {
			$importProcess = $importSource->createProcessWithoutFile($_POST["SETTINGS"]);
		}
		if ($importProcess !== null) {
			die(forward($tpl_content->tpl_uri_action('my-import-run').'?ID_IMPORT_PROCESS='.$importProcess->getProcessId()));
		}
	}
	
	$searchParameters = array();
	
	$importProcesses = $importProcessManagement->fetchAllByParam(array_merge($searchParameters, array(
		'FK_USER' => $uid,
		'FK_IMPORT_SOURCE' => $sourceId,
		'LIMIT' => $perpage,
		'OFFSET' => ($npage-1)*$perpage
	)));
	$numberOfProcesses = $importProcessManagement->getLastFetchByParamCount();
	
	if ($showUploadTab === "auto") {
		if ($numberOfProcesses > 0) {
			$showUploadTab = 0;
		} else {
			$showUploadTab = 1;
		}
	}
	
	$tpl_content->addvar("SOURCE_ID", $sourceId);
	$tpl_content->addvar("SOURCE_NAME", $importSource->getName());
	$tpl_content->addvar('FILE_REQUIRED', $importSource->getPreset()->doRequireFile());
	$tpl_content->addvar("PRESET_FILE_URL", $importSource->getDownloadUrl());
	
	$tpl_content->addlist("liste", $importProcesses, "tpl/".$s_lang."/my-import-process.row.htm");
	$tpl_content->addvar("pager", htm_browse_extended($numberOfProcesses, $npage, "my-import-process,".$ar_params[1].",{PAGE}", $perpage));
	$tpl_content->addvar("all", $numberOfProcesses);
	
}

$tpl_content->addvar("SHOW_UPLOAD_TAB", $showUploadTab);
$tpl_content->addvar('UPLOAD_MAX_FILESIZE', Tools_Utility::getUploadMaxFilsize());
	
if(isset($_REQUEST['MESSAGE'])) {
	$tpl_content->addvar('MESSAGE', $_REQUEST['MESSAGE']);
}
if(isset($_REQUEST['MESSAGE_ERROR'])) {
	$tpl_content->addvar('MESSAGE_ERROR', $_REQUEST['MESSAGE_ERROR']);
}

/*
$importProcessManagement = Ad_Import_Process_ProcessManagement::getInstance($db);


$perpage = 15;
$npage = ($ar_params[1])?(int)$ar_params[1]:1;


$searchParameters = array();

$importProcesses = $importProcessManagement->fetchAllByParam(array_merge($searchParameters, array(
	'FK_USER' => $uid,
	'LIMIT' => $perpage,
	'OFFSET' => ($npage-1)*$perpage
)));
$numberOfProcesses = $importProcessManagement->getLastFetchByParamCount();


$tpl_content->addlist("liste", $importProcesses, "tpl/".$s_lang."/my-import-process.row.htm");
$tpl_content->addvar("pager", htm_browse_extended($numberOfProcesses, $npage, "my-import-process,{PAGE}", $perpage));
$tpl_content->addvar("all", $numberOfProcesses);



if(isset($_REQUEST['MESSAGE'])) {
	$tpl_content->addvar('MESSAGE', $_REQUEST['MESSAGE']);
}
if(isset($_REQUEST['MESSAGE_ERROR'])) {
	$tpl_content->addvar('MESSAGE_ERROR', $_REQUEST['MESSAGE_ERROR']);
}

*/