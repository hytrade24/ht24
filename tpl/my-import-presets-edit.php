<?php
/** @var Ad_Import_PresetEditor_PresetEditorManagement $importPresetEditorManagement */
$importPresetEditorManagement = Ad_Import_PresetEditor_PresetEditorManagement::getInstance($db);
/** @var Ad_Import_Preset_PresetManagement $importPresetManagement */
$importPresetManagement = Ad_Import_Preset_PresetManagement::getInstance($db);


if(isset($_REQUEST['DO'])) {
	switch($_REQUEST['DO']) {
		case 'NEW_PRESET':
			$importPresetEditorManagement->reset();
			$importPresetEditorManagement->saveState();
			die(forward($tpl_content->tpl_uri_action('my-import-presets-edit')));

			break;
		case 'RESET':
			$importPresetEditorManagement->destroy();
			die(forward($tpl_content->tpl_uri_action('my-import-presets')));

			break;
		case 'EDIT':
			$importPreset = $importPresetManagement->loadPresetById($_GET['ID_IMPORT_PRESET']);
			if($importPreset == null || $importPreset->getOwnerUser() != $uid) {
				die(forward($tpl_content->tpl_uri_action('my-import-presets').'?MESSAGE_ERROR='.Translation::readTranslation('marketplace', 'import.preset.edit.error', null, array(), 'Es ist ein Fehler aufgetreten')));
			}

			$importPresetEditorManagement->reset();
			$importPresetEditorManagement->loadPreset($importPreset);
			// Load result log to support the user fixing problems with error messages
			$targetProcess = (int)$_GET['ID_IMPORT_PROCESS'];
			if ($targetProcess > 0) {
				$_SESSION['IMPORT_ERRORS_BY_FIELD'] = array();
				/** @var Ad_Import_Process_ProcessManagement $importProcessManagement */
				$importProcessManagement = Ad_Import_Process_ProcessManagement::getInstance($db);
				// Load import
				$importProcess = $importProcessManagement->loadImportProcessById($targetProcess);
				if($importProcess == null || $importProcess->getUserId() != $uid) {
					throw new Exception("Could not load import process");
				}
				$_SESSION['IMPORT_ERRORS_PROCESS'] = $importProcess->getProcessName();
				// Get failed datasets
				$markerData = $importProcess->getInfrastructure()->readMarkedBaseData(Ad_Import_Process_Process::DATASET_MARKER_ERROR, 0, 50);
				foreach($markerData as $key => $markerDataItem) {
					$markerDetails = unserialize($markerDataItem['IMPORT_MARKER_DETAILS']);
					if (is_array($markerDetails)) {
						foreach ($markerDetails as $fieldName => $message) {
							if (!preg_match("/^[0-9]*$/", $fieldName)) {
								// No pure numeric index
								if (!array_key_exists($fieldName, $_SESSION['IMPORT_ERRORS_BY_FIELD'])) {
									$_SESSION['IMPORT_ERRORS_BY_FIELD'][$fieldName] = array();
								}
								if (is_array($message)) {
									foreach ($message as $messageIndex => $messageText) {
										if (!in_array($messageText, $_SESSION['IMPORT_ERRORS_BY_FIELD'][$fieldName])) {
											$_SESSION['IMPORT_ERRORS_BY_FIELD'][$fieldName][] = $messageText;
										} 
									}
								} else {
									if (!in_array($message, $_SESSION['IMPORT_ERRORS_BY_FIELD'][$fieldName])) {
										$_SESSION['IMPORT_ERRORS_BY_FIELD'][$fieldName][] = $message;
									}
								}
							}
						}

					}
				}
			} else {
				// Clear errors
				$_SESSION['IMPORT_ERRORS_BY_FIELD'] = array();
				$_SESSION['IMPORT_ERRORS_PROCESS'] = "";
			}
			// Go to step
			$targetStep = (int)$_GET['STEP'];
			if (($targetStep > 0) && ($targetStep <= $importPresetEditorManagement->getMaxStep())) {
				$importPresetEditorManagement->setCurrentStep($targetStep);
			}
			$importPresetEditorManagement->saveState();

			die(forward($tpl_content->tpl_uri_action('my-import-presets-edit')));

			break;
		case 'COPY':
			/** @var Ad_Import_Preset_AbstractPreset $importPreset */
			$importPreset = $importPresetManagement->loadPresetById($_GET['ID_IMPORT_PRESET']);
			if($importPreset == null || $importPreset->getOwnerUser() != $uid) {
				die(forward($tpl_content->tpl_uri_action('my-import-presets').'?MESSAGE_ERROR='.Translation::readTranslation('marketplace', 'import.preset.edit.error', null, array(), 'Es ist ein Fehler aufgetreten')));
			}

			$newImportPreset = clone $importPreset;
			$newImportPreset->loadTableFields();
			$newImportPreset->setImportPresetId(null);
			$newImportPreset->setIsStored(false);
			$importPresetManagement->savePreset(null, $newImportPreset);


			die(forward($tpl_content->tpl_uri_action('my-import-presets-edit').'?DO=EDIT&ID_IMPORT_PRESET='.$newImportPreset->getImportPresetId()));


			break;
		case 'DELETE':
			$importPreset = $importPresetManagement->loadPresetById($_GET['ID_IMPORT_PRESET']);
			if($importPreset == null || $importPreset->getOwnerUser() != $uid) {
				die(forward($tpl_content->tpl_uri_action('my-import-presets').'?MESSAGE_ERROR='.Translation::readTranslation('marketplace', 'import.preset.delete.error', null, array(), 'Es ist ein Fehler beim Löschen aufgetreten')));
			}

			$importPresetManagement->deletePreset($_GET['ID_IMPORT_PRESET']);
			die(forward($tpl_content->tpl_uri_action('my-import-presets').'?MESSAGE='.Translation::readTranslation('marketplace', 'import.preset.delete.success', null, array(), 'Die Import Vorlage wurde erfolgreich gelöscht')));

			break;
		case 'SHOW_TABLE_FIELD_INFO':
			if(isset($_GET['ID_IMPORT_PRESET']) && !empty($_GET['ID_IMPORT_PRESET'])) {
				$importPreset = $importPresetManagement->loadPresetById($_GET['ID_IMPORT_PRESET']);
				if($importPreset == null || $importPreset->getOwnerUser() != $uid) {
					die();
				}
			} else {
				$importPreset = $importPresetEditorManagement->getPreset();
			}

			if($importPreset == null) {
				throw new Exception("preset could not be loaded");
			}

			$tpl = new Template("tpl/".$s_lang."/my-import-presets-edit.table_field_info.htm");

			$tplTableFields = '';
			foreach($importPreset->getTableFieldsByTableDef() as $tableDef => $tableFields) {
				/**
				 * @var  $key
				 * @var Ad_Import_Preset_Mapping_TableField $tableField
				 */
				foreach($tableFields as $key => $tableField) {
					$tmpTpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.table_field_info.row.htm');
					$tmpTpl->addvars($tableField->asArray());
					$tplTableFields .= $tmpTpl->process();
				}
			}


			$tpl->addvar('liste_table_fields', $tplTableFields);

			if(isset($_GET['JUMPTO_FIELD']) && !empty($_GET['JUMPTO_FIELD'])) {
				$tpl->addvar('JUMPTO_FIELD', $_GET['JUMPTO_FIELD']);
			}

			echo $tpl->process();


			die();
			break;
		/**
		 * Zeigt die DataField Tabelle an, die alle Spalten und Daten der ursprünglichen
		 * Quelldatei z.B. CSV Datei enthält und zur Informationen dargestellt werden kann
		 */
		case 'SHOW_DATAFIELD_TABLE':
			$preset = $importPresetEditorManagement->getPreset();
			if($preset == null) {
				throw new Exception("preset could not be loaded");
			}

			$tpl = new Template("tpl/".$s_lang."/my-import-presets-edit.datafieldtable.htm");
			$tpl->addvar('ID_IMPORT_PRESET', $preset->getImportPresetId());

			$markerTableHeaderCols = array();
			$dataFields = $preset->getDataFields();
			$colNum = 0;
			$highlightedCol = null;
			foreach($dataFields as $key => $dataField) {
				$markerTableHeaderCols[] = array('KEY' => $key, 'TH_NAME' => $dataField->getName());
				if($key == $_REQUEST['MARKER_COL']) {
					$highlightedCol = $colNum;
				}
				$colNum++;
			}

			if($highlightedCol !== null) {
				$tpl->addvar('MARKER_COL', $highlightedCol + 1);
			}
			$tpl->addlist('MARKER_TABLE_TH', $markerTableHeaderCols, 'tpl/'.$s_lang.'/my-import-run.datasets_table.th.htm');

			echo $tpl->process();
			die();
			break;
		/**
		 * Lädt als DataTables Ajax Aufruf die Daten der ursprünglichen Quelldatei
		 * und markiert optional eine Spalte
		 */
		case 'GET_DATAFIELD_TABLE_DATASETS':
			$preset = $importPresetEditorManagement->getPreset();
			if($preset == null) {
				throw new Exception("preset could not be loaded");
			}

			$result = array();
			$tplRows = array();
			$offset = isset($_REQUEST['start'])?(int)$_REQUEST['start']:0;
			$length =  isset($_REQUEST['length'])?(int)$_REQUEST['length']:50;
			$page = max(0,(int)$offset/$length);

			$data = $preset->read(null, $page, $length);
			$allData = $preset->getEstimatedNumberOfDatasets();


			foreach($data as $key => $dataElement) {
				$rowValues = array();
				$rowValues[] = $key + ($offset);

				$dataFields = $preset->getDataFields();
				foreach($dataFields as $dataFieldKey => $dataField) {

					if(array_key_exists($dataFieldKey, $dataElement)) {
						$rowValues[] = $dataElement[$dataFieldKey];
					} else {
						$rowValues[] = '';
					}
				}

				$tplRows[] = array_values($rowValues);
			}

			$result = array(
				'draw' => $_REQUEST['draw'],
				'recordsTotal' => $allData,
				'recordsFiltered' => $allData,
				'data' => $tplRows
			);

			die(json_encode($result));
			break;
		case 'remove-category-mapping':

			$category_mapping_key = $_POST['CATEGORY_MAPPING_KEY'];
			$category_mapping_value = $_POST['CATEGORY_MAPPING_VALUE'];

			$arr = null;
			if ( isset($_SESSION["mappings-data"]) ) {
				$arr = $_SESSION["mappings-data"];
			}
			else {
				$arr = array(
					'CATEGORYMAPPING'   =>  array(
						'KEY'       =>  array(),
						'FK_KAT'    =>  array()
					)
				);
			}
			if ( in_array($category_mapping_key, $arr['CATEGORYMAPPING']['KEY']) ) {
				$key = array_search(
					$_POST["key_value"],
					$arr['CATEGORYMAPPING']['KEY']
				);
				unset( $_SESSION["mappings-data"][""] );
			}

			$success = $importPresetEditorManagement->removeCategoryMapping( $category_mapping_key );
			$data = new stdClass();
			$data->success = $success;
			die(json_encode($data));
			break;
		case 'add-mappings-to-sesseion':
			$mapping_data = array();
			if ( $_SESSION['mappings-data'] ) {
				$mapping_data = $_SESSION['mappings-data'];
			}
			else {
				$mapping_data = array(
					'CATEGORYMAPPING'   =>  array(
						'KEY'       =>  array(),
						'FK_KAT'    =>  array()
					)
				);
			}
			$key = null;
			if ( in_array($_POST["id_value"], $mapping_data['CATEGORYMAPPING']['KEY']) ) {
				$key = array_search(
					$_POST["id_value"],
					$mapping_data['CATEGORYMAPPING']['KEY']
				);
				$mapping_data['CATEGORYMAPPING']['KEY'][$key] = $_POST["id_value"];
			}
			else {
				$key = null;
				$mapping_data['CATEGORYMAPPING']['KEY'][] = $_POST["id_value"];
			}

			if ( !is_null($key) ) {
				$mapping_data['CATEGORYMAPPING']['FK_KAT'][$key] = $_POST["key_value"];
			}
			else {
				$mapping_data['CATEGORYMAPPING']['FK_KAT'][] = $_POST["key_value"];
			}

			$_SESSION['mappings-data'] = $mapping_data;
			die();
			break;
		case 'LOAD_CURRENT_STEP':
			$result = $importPresetEditorManagement->loadStep($importPresetEditorManagement->getCurrentStep());
			if($result != false) {
				echo $result;
			} else {

			}

			die();
			break;
		case 'SET_STEP':
			$nextStep = (int)$_GET['step'];
			if($nextStep <= $importPresetEditorManagement->getMaxStep()) {
				$importPresetEditorManagement->setCurrentStep($nextStep);
				$importPresetEditorManagement->saveState();
			}
			die(forward($tpl_content->tpl_uri_action('my-import-presets-edit')));

			break;
		case 'MAPPING_FIELD_LOAD':
			$currentStep = $importPresetEditorManagement->getCurrentStepRenderer();
			if($currentStep instanceof Ad_Import_PresetEditor_Step_AbstractMappingStep) {
				echo $currentStep->renderTableFieldMapping($_REQUEST['FIELD_NAME'], $_REQUEST['TABLE_DEF'], $importPresetEditorManagement->getPreset());
			}

			die();
			break;
		case 'MAPPING_FIELD_ADD':
			$currentStep = $importPresetEditorManagement->getCurrentStepRenderer();
			if($currentStep instanceof Ad_Import_PresetEditor_Step_AbstractMappingStep) {
				$currentStep->addTableFieldMappingValue($_REQUEST['FIELD_NAME'], $_REQUEST['TABLE_DEF'], $_REQUEST['MAPPING_VALUE_TYPE'], $_REQUEST['POS'], $importPresetEditorManagement->getPreset());
			}
			die();
			break;
		case 'MAPPING_FIELD_REMOVE':
			$currentStep = $importPresetEditorManagement->getCurrentStepRenderer();
			if($currentStep instanceof Ad_Import_PresetEditor_Step_AbstractMappingStep) {
				$currentStep->removeTableFieldMappingValue($_REQUEST['FIELD_NAME'], $_REQUEST['TABLE_DEF'], $_REQUEST['POS'], $importPresetEditorManagement->getPreset());
			}
			die();
			break;
		case 'MAPPING_FIELD_SAVE':
			$currentStep = $importPresetEditorManagement->getCurrentStepRenderer();
			if($currentStep instanceof Ad_Import_PresetEditor_Step_AbstractMappingStep) {
				$currentStep->saveTableFieldMappingValue($_REQUEST['FIELD_NAME'], $_REQUEST['TABLE_DEF'], $_REQUEST['POS'], $_POST, $importPresetEditorManagement->getPreset());
			}
			die();
			break;
		case 'FIELD_DEFAULTVALUE_SAVE':
			$currentStep = $importPresetEditorManagement->getCurrentStepRenderer();
			if($currentStep instanceof Ad_Import_PresetEditor_Step_AbstractMappingStep) {
				$currentStep->saveDefaultTableFieldValue($_REQUEST['FIELD_NAME'], $_REQUEST['TABLE_DEF'], $_POST['DEFAULTVALUE'][$_REQUEST['TABLE_DEF']][$_REQUEST['FIELD_NAME'] ], $importPresetEditorManagement->getPreset());
			}
			die();
			break;
		case 'LOAD_CATEGORIES':
			switch ($_REQUEST["jqTreeAction"]) {
				case "readChilds":

					require_once "sys/lib.shop_kategorien.php";
					$show_paid = ($_REQUEST["paid"] ? 1 : 0);
					$kat = new TreeCategories("kat", 1);
					$katIdRoot = $kat->tree_get_parent();
					$arTree = callback_jqTreeTransformNodes($katIdRoot, $kat->tree_get());


					header('Content-type: application/json');
					die(json_encode(array(
						"success"   => true,
						"nodes"     => $arTree
					)));
			}
			die();
			break;
		case 'LOAD_MANUFACTURERS':
			$ebayId = (int)$_REQUEST["id"];
			$categoryId = (int)$_REQUEST["category"];
			$arManufacturers = Api_Entities_ManufacturerGroup::getManufacturersByCategory($categoryId);
			$tplManufacturers = new Template("tpl/".$s_lang."/my-import-presets-edit.step.ebay.edit.col_manufacturer.htm");
			$tplManufacturers->addvar("category", $categoryId);
			if (array_key_exists($ebayId, $_SESSION["importSettings"]["ebayImportAdsEdit"])) {
				$tplManufacturers->addvar("SELECTED", $_SESSION["importSettings"]["ebayImportAdsEdit"][$ebayId]["Manufacturer"]);
			}
			$tplManufacturers->addlist("options", $arManufacturers, "tpl/".$s_lang."/my-import-presets-edit.step.ebay.edit.col_manufacturer.option.htm");
			die($tplManufacturers->process());
			break;
		case 'LOAD_NAVIGATION':
			die($importPresetEditorManagement->loadEditorNavigation());
			break;
		case 'LOAD_AJAX_OPTIONS':
			$preset = $importPresetEditorManagement->getPreset();
			if (($preset === null) || ($preset->getPresetType() != $_REQUEST["type"])) {
				$preset = $importPresetManagement->createNewPreset($_REQUEST["type"]);
				$importPresetEditorManagement->loadPreset($preset);
			}
			if ($preset !== null) {
				die($preset->getAjaxOptions());
			} else {
				die("");
			}
			break;
		case 'SAVE_DATA':
			$arr = null;
			if ( $_GET['s'] == '3' ) {
				if ( isset($_SESSION["mappings-data"]) ) {
					$arr = $_SESSION["mappings-data"];
				}
				else {
					$arr = array(
						'CATEGORYMAPPING'   =>  array(
							'KEY'       =>  array(),
							'FK_KAT'    =>  array()
						)
					);
				}
				$saveResult = $importPresetEditorManagement->saveData(array(
					'POST' => $arr,
					'FILES' => $_FILES
				));
			}
			else {
				$saveResult = $importPresetEditorManagement->saveData(array(
					'POST' => $_POST,
					'FILES' => $_FILES
				));
			}
			if($saveResult == true) {
				if ( $_GET['s'] == '3' ) {
					unset( $_SESSION['mappings-data'] );
				}
				$preset = $importPresetEditorManagement->getPreset();
				$finishAfterwards = ($preset->getStepMax() < $importPresetEditorManagement->getCurrentStep());
				#die(var_dump($finishAfterwards, $importPresetEditorManagement->getCurrentStep()));
				if (($_GET['AFTER'] == 'CLOSE') || $finishAfterwards) {
					$autoCreateSource = ($preset->getAutoCreateSource() && !$preset->getImportPresetId());
					$result = $importPresetEditorManagement->saveToDatabase();
					$importPresetEditorManagement->destroy();
					if ($autoCreateSource) {
						$importSource = Ad_Import_Source_Source::getByAssoc($db, array(
							"FK_IMPORT_PRESET"	=> $preset->getImportPresetId(),
							"SOURCE_NAME"		=> $preset->getPresetName()
						));
						if($importSource->update($arErrors)) {
							die(forward($tpl_main->tpl_uri_action('my-import-process,'.$importSource->getId())));
						} else {
							die(forward($tpl_main->tpl_uri_action('my-import-presets')));
						}
					} else {
						die(forward($tpl_main->tpl_uri_action('my-import-presets')));
					}
				}
				die(forward($tpl_main->tpl_uri_action('my-import-presets-edit')));
			}
			break;
		case 'ADD_STEP_BY_STEP':


	}
}

if ($importPresetEditorManagement->getPreset() !== null) {
	$tpl_content->addvar('importPresetName', $importPresetEditorManagement->getPreset()->getPresetName());
	if ($importPresetEditorManagement->getPreset()->getStepMax() < $importPresetEditorManagement->getCurrentStep()) {
			$result = $importPresetEditorManagement->saveToDatabase();
			$importPresetEditorManagement->destroy();
			die(forward($tpl_main->tpl_uri_action('my-import-presets')));
	}
}

$tpl_content->addvar('importPresetEditorContent', $importPresetEditorManagement->loadStep($importPresetEditorManagement->getCurrentStep()));