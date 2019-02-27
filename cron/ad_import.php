<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $db, $s_lang, $langval, $ab_path;

$file_log = fopen($ab_path."cron/ad_import_detail.log", "w");

$limitGeoFix = 50;
$limitImages = 20;

// Cron import settings
$maxImportSourceCount = 1;	// Max count of imports to be processed in one call
$maxImportProcessCount = 1;	// Max count of imports to be processed in one call
$maxImportDuration = 40;	// Max duration used for importing
// Apply import settings
$maxImportTime = time() + $maxImportDuration;

// Geolocation lookup settings
$maxGeoDuration = 40;
$maxGeoTime = time() + $maxImportDuration + $maxGeoDuration;

echo("Cronjob 'ad import' gestartet.");
fwrite($file_log, date("Y-m-d H:i:s")." Cronjob 'ad import' gestartet.\n");

$importSourceManagement = Ad_Import_Source_SourceManagement::getInstance($db);
$importProcessManagement = Ad_Import_Process_ProcessManagement::getInstance($db);

/*
 * Import sources to be executed via cron
 */
$importSourcesPending = $importSourceManagement->fetchAllByParam(array(
	"DOWNLOAD_NEXT_LT"	=> date("Y-m-d H:i:s"),
	"LIMIT"				=> $maxImportSourceCount,
    "SORT_BY"           => "i.ID_IMPORT_SOURCE",
    "SORT_DIR"          => "ASC"
));

if (($importSourcesPending !== null) && !empty($importSourcesPending)) {
	// Start/resume imports
	$importProcessManagement = Ad_Import_Process_ProcessManagement::getInstance($db);
	foreach ($importSourcesPending as $importIndex => $arImportSource) {
        fwrite($file_log, date("Y-m-d H:i:s")." Processing ".$arImportSource["SOURCE_NAME"]." / #".$arImportSource["ID_IMPORT_SOURCE"]."\n");
		$importSource = Ad_Import_Source_Source::getByAssoc($db, $arImportSource);
		$importSource->setDownloadNext(null);
		$importSource->update();
		// Start process
		$importProcess = $importSource->createProcessFromUrl( $importSource->getDownloadUrl(), array(), true );
		$importProcess->run();
		$importProcessManagement->saveProcess($importProcess->getProcessId(), $importProcess);
        fwrite($file_log, date("Y-m-d H:i:s")." Finished ".$importSource->getName()." / #".$importSource->getId()."\n");
		if (time() > $maxImportTime) {
			// Maximum time exceeded, stop now.
			break;
		}
	}
}

$importProcessesPending = $importProcessManagement->fetchAllByParam(array(
	"STATUS"	=> array(
		Ad_Import_Process_Process::STATUS_PRE, Ad_Import_Process_Process::STATUS_INFRASTRUCTURE,
		Ad_Import_Process_Process::STATUS_LOAD, Ad_Import_Process_Process::STATUS_LOAD_READY,
		Ad_Import_Process_Process::STATUS_TRANSFORM, Ad_Import_Process_Process::STATUS_TRANSFORM_READY,
		Ad_Import_Process_Process::STATUS_VALIDATE, Ad_Import_Process_Process::STATUS_VALIDATE_READY,
		Ad_Import_Process_Process::STATUS_IMPORT, Ad_Import_Process_Process::STATUS_IMPORT_READY
	),
	"CRON_STAT"	=> 1,
	"LIMIT"		=> $maxImportProcessCount,
    "SORT_BY"   => "i.ID_IMPORT_PROCESS",
    "SORT_DIR"  => "ASC"
));
if (($importProcessesPending !== null) && !empty($importProcessesPending)) {
	$arImportProcessList = array();
	foreach ($importProcessesPending as $importProcessIndex => $arImportProcess) {
        fwrite($file_log, date("Y-m-d H:i:s")." Loading ".$arImportProcess["PROCESS_NAME"]." / #".$arImportProcess["ID_IMPORT_PROCESS"]."\n");
	    try {
            $importProcess = $importProcessManagement->loadProcessByDataset($arImportProcess);
            if ($importProcess instanceof Ad_Import_Process_Process) {
                $arImportProcessList[] = $importProcess;
            } else {
                eventlog("error", "Fehler beim initialisieren eines Imports!", var_export($arImportProcess, true));
            }
        } catch (Exception $e) {
            eventlog("error", "Fehler beim initialisieren eines Imports!", $e->getMessage());
        }
	}
	$importActive = true;
	$importCount = 0;
	while ((time() <= $maxImportTime) && $importActive && ($importCount++ < 200)) {
		/**
		 * @var Ad_Import_Process_Process	$importProcess
		 */
		$importActive = false;
		foreach ($arImportProcessList as $importProcessIndex => $importProcess) {
		    if ($importProcess->getStatus() == Ad_Import_Process_Process::STATUS_COMPLETE) {
		        continue;
            }
            $importActive = true;
		    // Disable for further cron executions
            $db->querynow("UPDATE `import_process` SET CRON_STAT=2 WHERE ID_IMPORT_PROCESS=".$importProcess->getProcessId());
			// Resume import
            fwrite($file_log, date("Y-m-d H:i:s")." Resuming ".$importProcess->getProcessName()." / #".$importProcess->getProcessId()."\n");
			eventlog("info", "Import wird fortgesetzt, User-ID: ".$importProcess->getUserId().", Bezeichnung: ".$importProcess->getProcessName());
			#echo "resume import: ".$importProcess->getProcessId();
			$importProcess->run();
			$importProcessManagement->saveProcess($importProcess->getProcessId(), $importProcess);
            fwrite($file_log, date("Y-m-d H:i:s")." Finished ".$importProcess->getProcessName()." / #".$importProcess->getProcessId()."\n");
            // Re-Enable for further cron executions
            $db->querynow("UPDATE `import_process` SET CRON_STAT=1 WHERE ID_IMPORT_PROCESS=".$importProcess->getProcessId());
			if (time() > $maxImportTime) {
				// Maximum time exceeded, stop now.
				break;
			}
		}
	}
}
fwrite($file_log, date("Y-m-d H:i:s")." Geolocation fix.\n");

/*
 * Longitude / Latidude Fix
 */

$adsWithoutLongitude = $db->fetch_table("
	SELECT
		 a.ID_AD_MASTER, a.FK_KAT, a.AD_TABLE, a.STREET, a.ZIP, a.CITY, a.FK_COUNTRY,
		s.V1 as COUNTRY_NAME
	FROM ad_master a
	LEFT JOIN string s ON s.FK = a.FK_COUNTRY AND s.S_TABLE = 'country' AND s.BF_LANG = 128
	WHERE
		a.LONGITUDE = 0 AND a.LATITUDE = 0
		AND (a.ZIP != '' OR a.CITY != '' OR a.FK_COUNTRY != 0)
	LIMIT ".(int)$limitGeoFix."
	");



foreach($adsWithoutLongitude as $key => $ad) {

	$geoCoordinates = Geolocation_Generic::getGeolocationCached($ad['STREET'], $ad['ZIP'], $ad['CITY'], $ad['COUNTRY_NAME']);

	if (($geoCoordinates !== null) && ($geoCoordinates !== false)) {
	 	$db->querynow($q="
			UPDATE
				ad_master
			SET
				FK_GEO_REGION=".$geoCoordinates['FK_GEO_REGION'].",
			    LATITUDE = '".mysql_real_escape_string($geoCoordinates['LATITUDE'])."', LONGITUDE = '".mysql_real_escape_string($geoCoordinates['LONGITUDE'])."'
			WHERE
				ZIP = '".mysql_real_escape_string($ad['ZIP'])."'
				AND CITY = '".mysql_real_escape_string($ad['CITY'])."'
				AND FK_COUNTRY = '".mysql_real_escape_string($ad['FK_COUNTRY'])."'
				AND STREET = '".mysql_real_escape_string($ad['STREET'])."'
				AND AD_TABLE = '".mysql_real_escape_string($ad['AD_TABLE'])."'
		");

		$db->querynow("
			UPDATE
				".mysql_real_escape_string($ad['AD_TABLE'])."
			SET
				LATITUDE = '".mysql_real_escape_string($geoCoordinates['LATITUDE'])."', LONGITUDE = '".mysql_real_escape_string($geoCoordinates['LONGITUDE'])."'
			WHERE
				ZIP = '".mysql_real_escape_string($ad['ZIP'])."'
				AND CITY = '".mysql_real_escape_string($ad['CITY'])."'
				AND FK_COUNTRY = '".mysql_real_escape_string($ad['FK_COUNTRY'])."'
				AND STREET = '".mysql_real_escape_string($ad['STREET'])."'
		");

		// Trigger event
		$eventParams = new Api_Entities_EventParamContainer(array(
			"idCategory" 	=> $ad["FK_KAT"], 
			"idGeoRegion" 	=> $geoCoordinates['FK_GEO_REGION'],
			"latitude"		=> $geoCoordinates['LATITUDE'],
			"longitude"		=> $geoCoordinates['LONGITUDE']
		), true);
		Api_TraderApiHandler::getInstance($db)->triggerEvent( Api_TraderApiEvents::MARKETPLACE_AD_LOCATION_UPDATE, $eventParams );

	} else if ($geoCoordinates !== null) {
        eventlog('error', 'GeoLocation lookup failed, skipping further requests for this address.');
		$db->querynow($q="
			UPDATE
				ad_master
			SET
				LATITUDE = '-1', LONGITUDE = '-1'
			WHERE
				ID_AD_MASTER = '".mysql_real_escape_string($ad['ID_AD_MASTER'])."'
		");
	}
    if (time() > $maxGeoTime) {
        // Maximum time exceeded, stop now.
        break;
    }
}

fwrite($file_log, date("Y-m-d H:i:s")." Cleanup old imports.\n");

/**
 * Delete Old Import Logs
 */




$perpage = 15;
$npage = ($ar_params[1])?(int)$ar_params[1]:1;


$searchParameters = array();

$importProcesses = $importProcessManagement->fetchAllByParam(array(
	'MIN_AGE_DAYS' => 30,
	'LIMIT' => 1,
	'OFFSET' => 0
));

fwrite($file_log, date("Y-m-d H:i:s")." Cleanup issued for ".count($importProcesses)." processes.\n");


foreach($importProcesses as $key => $importProcessData) {
    try {
        fwrite($file_log, date("Y-m-d H:i:s")." Cleanup ".$importProcessData['PROCESS_NAME']." / #".$importProcessData['ID_IMPORT_PROCESS']."\n");
        $importProcess = $importProcessManagement->loadImportProcessById($importProcessData['ID_IMPORT_PROCESS']);
        if ($importProcess instanceof Ad_Import_Process_Process) {
            fwrite($file_log, date("Y-m-d H:i:s")."  - Loaded!\n");
            $importProcess->cleanUp();
            fwrite($file_log, date("Y-m-d H:i:s")."  - Cleanup done!\n");
            $importProcessManagement->deleteImportProcess($importProcess->getProcessId());
            fwrite($file_log, date("Y-m-d H:i:s")."  - Deleted!\n");
        } else {
            fwrite($file_log, date("Y-m-d H:i:s")."  - Failed to load! Skipping for further runs.\n");
        }
    } catch (Exception $e) {
        eventlog("error", "Fehler beim initialisieren eines Imports!", $e->getMessage());
    }
}



echo("Cronjob 'ad import' beendet.");
fwrite($file_log, date("Y-m-d H:i:s")." Cronjob 'ad_import' beendet.\n");
fclose($file_log);
return;

/*
 * Images
 */
$adsWithoutImages = $db->fetch_table("SELECT ID_AD_MASTER, AD_TABLE, IMPORT_IMAGES FROM ad_master WHERE  IMPORT_IMAGES IS NOT NULL AND IMPORT_IMAGES != '' LIMIT ".(int)$limitImages." ");
if(count($adsWithoutImages) > 0) {
	foreach($adsWithoutImages as $key => $ad) {
		$images = unserialize($ad['IMPORT_IMAGES']);
		Template_Helper_ArticleImageLoader::loadImagesForArticle($ad['ID_AD_MASTER'], $ad['AD_TABLE'], $images);
	}
}

