<?php

switch($_REQUEST['DO']) {
	case 'RUN':
		// Close session for not blocking further requests while running the import
		session_write_close();
		// Execute import tasks
		$result = array();

		if (!$importProcess->getCronProcess()) {
			$importProcess->run();
			$importProcessManagement->saveProcess($importProcess->getProcessId(), $importProcess);
		}


		$result['PROGRESS_PERCENTAGE'] = $importProcess->getEstimatedProgessState();
		$result['PROCESS_STATUS'] = $importProcess->getStatus();
		$result['PROCESS_STATUS_NAME'] = $importProcess->getStatusName();
		$result['PROCESS_COMPLETE'] = ($importProcess->getStatus() == Ad_Import_Process_Process::STATUS_COMPLETE);
		$result['PROCESS_CRON'] = ($importProcess->getCronProcess() ? true : false);

		die(json_encode($result));
		break;
	case 'GET_LOG':
		// Close session for not blocking further requests while executing the query
		session_write_close();
		// Query log messages
		$result = array();

		$logLevel = isset($_REQUEST['LOG_LEVEL'])?(int)$_REQUEST['LOG_LEVEL']:Ad_Import_Process_Process::LOG_INFO;
		$offset = isset($_REQUEST['start'])?(int)$_REQUEST['start']:0;

		$logs = $importProcess->getInfrastructure()->readLog($logLevel, $offset, $limit = 50);
		$allLogs = $importProcess->getInfrastructure()->countLogs($logLevel);

		$logRows = array();
		foreach($logs as $key => $log) {
			unset($log['LOG_DATA']);
			unset($log['rowid']);

			$logRows[] = array_values($log);
		}

		$result = array(
			'draw' => $_REQUEST['draw'],
			'recordsTotal' => $allLogs,
			'recordsFiltered' => $allLogs,
			'data' => $logRows
		);

		die(json_encode($result));
		break;
	case 'GET_MARKER_DATASETS':
		// Close session for not blocking further requests while executing the query
		session_write_close();
		// Query datasets
		$result = array();
		$tplRows = array();
		$offset = isset($_REQUEST['start'])?(int)$_REQUEST['start']:0;

		$markerData = $importProcess->getInfrastructure()->readMarkedBaseData($_REQUEST['MARKER'],$offset, 50);
		$allMarkerData = $importProcess->getInfrastructure()->countMarkedBaseDataset($_REQUEST['MARKER']);

		foreach($markerData as $key => $markerDataItem) {
			$markerDataItemId = $markerDataItem['ID'];
			$markerDetails = unserialize($markerDataItem['IMPORT_MARKER_DETAILS']);
			if (is_array($markerDetails)) {
				$markerDetailsGrouped = $markerDetails;
				$markerDetails = array();
				foreach ($markerDetailsGrouped as $fieldName => $messages) {
					$fieldDesc = (!empty($fieldName) ? $fieldName.": " : "");
					if (is_array($messages)) {
						$markerDetails[] = $fieldDesc.implode("<br />\n".$fieldDesc, $messages);
					} else {
						$markerDetails[] = $fieldDesc.$messages;
					}
				}
			}

			unset($markerDataItem['rowid']);
			unset($markerDataItem['IMPORT_MARKER']);
			unset($markerDataItem['ID']);
			unset($markerDataItem['IMPORT_MARKER_DETAILS']);

			array_unshift($markerDataItem, $markerDataItemId, is_array($markerDetails)?implode("<br>", $markerDetails):'');

            // HTML encode content
            foreach ($markerDataItem as $markerDataIndex => $markerDataItemValue) {
                $markerDataItem[$markerDataIndex] = htmlspecialchars($markerDataItemValue);
            }

            $tplRows[] = array_values($markerDataItem);
		}

		$result = array(
			'draw' => $_REQUEST['draw'],
			'recordsTotal' => $allMarkerData,
			'recordsFiltered' => $allMarkerData,
			'data' => $tplRows
		);


		die(json_encode($result));
		break;
	case 'DELETE':
		$targetAction = 'my-import-process';
		$resultMessage = "Unknown error!";
		// Delete import process?
		if ($importProcess !== null) {
			if ($importProcess->getImportSource() > 0) {
				$targetAction .= ','.$importProcess->getImportSource();
			}
			$importProcess->cleanUp();
			$importProcessManagement->deleteImportProcess($importProcess->getProcessId());
			$resultMessage = Translation::readTranslation('marketplace', 'import.process.delete.success', null, array(), 'Der Import wurde erfolgreich gelöscht');
		}
		// Delete import source?
		if ($importSource !== null) {
			if ( $_REQUEST["del_type"] == "del_import_and_data" ) {
				$query = "SELECT ID_AD_MASTER, AD_TABLE 
							FROM `ad_master` 
							WHERE IMPORT_SOURCE=".$_REQUEST["ID_IMPORT_SOURCE"];
				$targetAds = $db->fetch_nar( $query );
				Ad_Marketplace::deleteAdsEx($targetAds);
			}
			$importSource->deleteFromDatabase();
			$resultMessage = Translation::readTranslation('marketplace', 'import.source.delete.success', null, array(), 'Die Quelle wurde erfolgreich gelöscht');
		}

		die(forward($tpl_content->tpl_uri_action($targetAction).'?MESSAGE='.$resultMessage));
		break;
	case 'RECREATE':

		die(forward($tpl_content->tpl_uri_action('my-import-process,'.$importProcess->getImportSource().',1,1')));

		break;
	case 'CREATE':
		$importSource = Ad_Import_Source_Source::getByAssoc($db, $_POST);
		
		$arErrors = array();

		if($importSource->update($arErrors)) {
			
			die(forward($tpl_content->tpl_uri_action( "my-import-process,".$importSource->getId() )));
			//die(forward($tpl_content->tpl_uri_action('my-import-run').'?ID_IMPORT_SOURCE='.$importSource->getId()));

		} else {
			$tpl_content->addvar('ERR', implode('<br>', $arErrors));
			$tpl_content->addvars($_POST);
		}

		break;

}

