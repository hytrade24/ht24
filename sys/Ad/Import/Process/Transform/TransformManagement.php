<?php

require_once $ab_path.'sys/lib.pub_kategorien.php';

class Ad_Import_Process_Transform_TransformManagement {

	/** @var  Ad_Import_Process_Process */
	protected $importProcess;

	/** @var  Ad_Import_Preset_AbstractPreset */
	protected $preset;
	
	/** @var  Api_Entities_User */
	protected $userOwner;

	/** @var  array */
	protected $fieldMapping;
	protected $tableFields;

	protected $cachedCategoryHashmapById;

	protected $specialProtectedMappingFields = array('FK_KAT');

	/**
	 * @param Ad_Import_Process_Process $importProcess
	 */
	function __construct($importProcess) {
		$this->importProcess = $importProcess;
		$this->preset = $this->importProcess->getPreset();
		$this->userOwner = Api_UserManagement::getInstance($GLOBALS["db"])->fetchOneAsObject( array("ID_USER" => $this->preset->getOwnerUser()) );
		if ($this->userOwner === false) {
		    throw new Exception("User #".$this->preset->getOwnerUser()." not found!");
        }

		$this->fieldMapping = $this->preset->getFieldMapping();
		$this->tableFields = $this->preset->getTableFieldsByTableDef();

		$this->preloadCategoryList();
	}


	public function transformData($data) {
		$transformationResultDataset = array();

		$rawData = $this->preprocessData($data);
		$transformationResultDataset = array_merge($transformationResultDataset, $this->detectCategory($rawData));
		
		$categoryTable = $rawData['_INTERN']['KAT_TABLE'];
		if($rawData['_INTERN']['STATUS'] !== 0 && $categoryTable != null) {


			$transformationResultDataset = array_merge($transformationResultDataset, $this->mapFields('artikel_master', $rawData));
			$transformationResultDataset = array_merge($transformationResultDataset, $this->mapFields($categoryTable, $rawData));
			$transformationResultDataset = array_merge($transformationResultDataset, $this->postFieldMapping($categoryTable, $transformationResultDataset));
			$transformationResultDataset['ID'] = $rawData['ID'];
		}
		
		if($this->postValidation($transformationResultDataset, $data["COLIMPORT_TASK"])) {
			return $transformationResultDataset;
		} else {
			$links = '<a href="my-import-presets-edit.htm?DO=EDIT&ID_IMPORT_PRESET='.$this->preset->getImportPresetId().'&STEP=3&ID_IMPORT_PROCESS='.$this->importProcess->getProcessId().'">'.
				Translation::readTranslation("marketplace", "my.import.link.edit.category", null, array(), 'Kategorie Zuordnung bearbeiten').
			'</a>';
			$logText = $this->importProcess->log(Translation::readTranslation(
					'marketplace', 'import.process.transform.failed.postvalidation', null,
					array(
						'DATASET_NAME' 	=> "'".$transformationResultDataset['PRODUKTNAME']."'",
						'DATASET_IDENT' => (!empty($transformationResultDataset['IMPORT_IDENTIFIER'])?"'(".$transformationResultDataset['IMPORT_IDENTIFIER'].")'":'""')
					),
					'<span class="text-error">Transformation des Datensatzes {DATASET_NAME} {DATASET_IDENT} fehlgeschlagen</span><br>Es konnte keine Kategorie zugewiesen werden!'
				).' '.$links, Ad_Import_Process_Process::LOG_WARNING
			);

			$this->importProcess->markBaseDataset($data['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));


			return null;
		}

	}

	protected function preprocessData($datarow) {
		$result = array();
		foreach($datarow as $key => $value) {
			if(strpos($key, 'COL') === 0) {
				$result[substr($key, 3)] = $value;
			} else {
				$result[$key] = $value;
			}
		}

		$result['_INTERN'] = array(
			'STATUS' => 1
		);
		$result['_DATA'] = array();

		return $result;
	}

	protected function detectCategory(&$datarow) {
		/*$categoryDataField = $this->preset->getCategoryField();


		if($categoryDataField == null || !($categoryDataField instanceof Ad_Import_Preset_Mapping_DataField)) {
			$this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.transform.nocategorymapping.', null, array(), 'Es wurde kein Kategoriefeld zugeordnet'), Ad_Import_Process_Process::LOG_ERROR);

			throw new Exception("Category Field is not mapped");
		}*/

		/** @var Ad_Import_Preset_Mapping_FieldMap $categoryFieldMapping */
		$categoryFieldMapping = $this->fieldMapping['artikel_master']['FK_KAT'];
		$categoryTableField = $this->tableFields['artikel_master']['FK_KAT'];

		$transFormedCategoryValue = '';
		if($categoryFieldMapping != null) {
			$transFormedCategoryValue = $categoryFieldMapping->execute($datarow, " - ");
		}

		if(array_key_exists($transFormedCategoryValue, $this->preset->getCategoryMapping())) {
			$transFormedCategoryValue = $this->preset->getCategoryMappingByKey($transFormedCategoryValue);
		}

		if(!array_key_exists($transFormedCategoryValue, $this->cachedCategoryHashmapById) && $categoryTableField->getDefaultValue() != '') {
			$transFormedCategoryValue = $categoryTableField->getDefaultValue();
		}
		
		if (preg_match("/^et\#([0-9]+)#(.+)$/", $transFormedCategoryValue, $arMatch)) {
			$transFormedCategoryValue = $arMatch[1];
		}
		
		if(array_key_exists($transFormedCategoryValue, $this->cachedCategoryHashmapById) && !empty($this->cachedCategoryHashmapById[$transFormedCategoryValue])) {
			$category = $this->cachedCategoryHashmapById[$transFormedCategoryValue];

			if($category['KAT_TABLE'] == 'ad_master') {
				$datarow['_INTERN']['KAT_TABLE'] = 'artikel_master';
			} else {
				$datarow['_INTERN']['KAT_TABLE'] = $category['KAT_TABLE'];
			}



			return array(
				'FK_KAT' => $category['ID_KAT'],
				'KAT_TABLE' => $category['KAT_TABLE']
			);
		} else if (!empty($transFormedCategoryValue)) {
			$datarow['_INTERN']['STATUS'] = 0;
			$this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.transform.failed.categorynotfound', null, array('KAT_NAME' => "'".$transFormedCategoryValue."'"), 'Tranformation des Datensatzes fehlgeschlagen.<br>Kategorie "{KAT_NAME}" wurde nicht gefunden'), Ad_Import_Process_Process::LOG_WARNING);
			// Add unknown category to preset values
			$this->preset->addCategoryDataValues($transFormedCategoryValue);

		}

		return array();
	}

	protected function mapFields($categoryTable, $datarow) {
		$result = array();


		/** @var Ad_Import_Preset_Mapping_TableField $tableField */
		foreach($this->tableFields[$categoryTable] as $key => $tableField) {

            $fieldMapping = $this->preset->getFieldMappingByTableField($tableField->getFieldName(), $tableField->getTableDef());
            $fieldName = $tableField->getFieldName();
            $fieldValue = '';

            if (!in_array($fieldName, $this->specialProtectedMappingFields)) {

                if ($fieldMapping != null) {
                    $implodeString = "";
                    if ($fieldName == "IMPORT_IMAGES") {
                        $implodeString = "\n";
                    }
                    $fieldValue = $fieldMapping->execute($datarow, $implodeString);
                }
                $fieldValue = $this->transformDataBasedOnDefaultValue($fieldValue, $tableField, $datarow);
                $fieldValue = $this->transformDataBasedOnTableField($fieldValue, $tableField, $datarow);

                $result[$fieldName] = $fieldValue;
            }
        }
        foreach($result as $fieldName => $fieldValue) {
            $result[$fieldName] = $this->transformDataBasedOnUserValue($fieldValue, $fieldName, $result);
		}


		return $result;
	}

	/**
     * @param string                                $fieldValue
	 * @param Ad_Import_Preset_Mapping_TableField   $tableField
     * @param array                                 $datarow
	 */
	protected function transformDataBasedOnTableField($fieldValue, $tableField, $datarow) {
		$acceptedValues = $tableField->getAcceptedValues();

		if(is_array($acceptedValues) && count($acceptedValues) > 0) {
		    $fieldValue = trim($fieldValue);
			if(!is_array($fieldValue)) {
			    if (!array_key_exists($fieldValue, $acceptedValues)) {
                    if(in_array($fieldValue, $acceptedValues)) {
                        $fieldValue = array_search($fieldValue, $acceptedValues);
                    } else if (in_array($tableField->getType(), array('MULTICHECKBOX', 'MULTICHECKBOX_AND', 'VARIANT')) && !empty($fieldValue)) {
                        $fieldValue = "";
                    }
                }
			} else {
			    $fieldValueAccepted = array();
				foreach($fieldValue as $key => $tmpValue) {
			        if (!array_key_exists($tmpValue, $acceptedValues)) {
                        if (in_array($tmpValue, $acceptedValues)) {
                            $fieldValueAccepted[$key] = array_search($tmpValue, $acceptedValues);
                        } else if (!in_array($tableField->getType(), array('MULTICHECKBOX', 'MULTICHECKBOX_AND', 'VARIANT'))) {
                            $fieldValueAccepted[$key] = $tmpValue;
                        }
                    } else {
			            $fieldValueAccepted[$key] = $tmpValue;
                    }
				}
				$fieldValue = $fieldValueAccepted;
			}
		}

		if(($tableField->getType() == 'FLOAT') && strpos($fieldValue, ',') !== FALSE) {
			$fieldValue = str_replace(',', '.', $fieldValue);
		}

		if(in_array($tableField->getType(), array('MULTICHECKBOX', 'MULTICHECKBOX_AND', 'VARIANT')) && !empty($fieldValue)) {
			if ( is_array($fieldValue) ) {
				$fieldValue = "x".implode("x", $fieldValue)."x";
			}
			else {
				$fieldValue = "x".$fieldValue."x";
			}
		}

		if($tableField->getFieldName() == 'IMPORT_IMAGES' && $tableField->getTableDef() == 'artikel_master' && !empty($fieldValue)) {
			if(!is_array($fieldValue)) {
				$fieldValue = explode("\n", str_replace(array("\r\n", "\r", "\n"), "\n", $fieldValue));
			}
			$fieldValue = serialize($fieldValue);
		}

		return $fieldValue;
	}

	/**
     * @param string                                $fieldValue
	 * @param Ad_Import_Preset_Mapping_TableField   $tableField
     * @param array                                 $datarow
	 */
	protected function transformDataBasedOnDefaultValue($fieldValue, $tableField, $datarow) {

		if($fieldValue === '' && $tableField->getDefaultValue() !== null) {
			$fieldValue = $tableField->getDefaultValue();
		}

		return $fieldValue;
	}

	/**
	 * @param string    $fieldValue
	 * @param string    $fieldName
     * @param array     $result
	 */
	protected function transformDataBasedOnUserValue($fieldValue, $fieldName, &$result) {

		if ($fieldValue === '') {
            // Article location
            if (in_array($fieldName, array("ZIP", "CITY", "STREET", "FK_COUNTRY"))) {
                if (empty($result["ZIP"]) && empty($result["CITY"]) && empty($result["STREET"]) && empty($result["FK_COUNTRY"])) {
                    $result["ZIP"] = $this->userOwner->getFieldRaw("PLZ");
                    $result["CITY"] = $this->userOwner->getFieldRaw("ORT");
                    $result["STREET"] = $this->userOwner->getFieldRaw("STRASSE");
                    $result["FK_COUNTRY"] = $this->userOwner->getFieldRaw("FK_COUNTRY");
                    return $result[$fieldName];
                }
            }
		}

		return $fieldValue;
	}

	/**
	 * Special Mapping Values applied after default mapping
	 *
	 * @param $categoryTable
	 * @param $datarow
	 */
	protected function postFieldMapping($categoryTable, $datarow) {


		// PREIS -> Verkaufsoptionen
		if($datarow['PREIS'] == 0 && $datarow['VERKAUFSOPTION'] != 2) {
			$datarow['VERKAUFSOPTIONEN'] = 2;
		}


		return $datarow;
	}

	protected function postValidation($datarow, $task = 'UPDATE') {
		switch ($task) {
			case 'D':
			case 'DELETE':
			case 'PAUSE':
			case 'START':
				return true;
			case 'N':
			case 'NEW':
			case 'U':
			case 'UPDATE':
			default:
				return (isset($datarow['KAT_TABLE']) && isset($datarow['FK_KAT']) && !empty($datarow['KAT_TABLE']) && !empty($datarow['FK_KAT']));
		}
	}

	protected function preloadCategoryList() {
		global $db;

		$categoriesBase = new CategoriesBase();
		$categoryHashMap = $categoriesBase->getCategoryPathHashMap();

		$this->cachedCategoryHashmapById = $categoryHashMap['ID'];
	}

}