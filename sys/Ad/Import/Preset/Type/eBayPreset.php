<?php


class Ad_Import_Preset_Type_eBayPreset extends Ad_Import_Preset_AbstractPreset {

	protected $filename;
	protected $presetType = 'eBay';

	private static $ebayFieldMappingDefault = array(
		"FK_KAT" 						=> "PrimaryCategory_CategoryName",
		"FK_COUNTRY" 				=> "Country",
		"CITY" 							=> "Location",
		"MENGE" 						=> "Quantity",
		"ZUSTAND" 					=> "ConditionID",
		"PREIS"							=> "SellingStatus_ConvertedCurrentPrice",
		"VERSANDKOSTEN"			=> "ShippingDetails_ShippingServiceOptions_ShippingServiceCost",
		"PRODUKTNAME" 			=> "Title",
		"FK_MAN" 		    		=> "Manufacturer",
		"BESCHREIBUNG" 			=> "Description",
		"IMPORT_IMAGES" 		=> array("PictureDetails_GalleryURL", "PictureDetails_PictureURL"),
		"IMPORT_IDENTIFIER" => "ItemID",
		"IMPORT_TASK"       => "IMPORT_TASK"
	);
  private static $ebayOutputFilterDefault = array(
      "Item.ItemID", "Item.Title", "Item.Description", "Item.Country", "Item.Location", "Item.Quantity",
      "Item.PrimaryCategory", "Item.SellingStatus", "Item.ShippingDetails", "Item.ConditionID", "Item.PictureDetails",
      "Item.SKU", "Item.Site", "Item.ProductListingDetails.BrandMPN.Brand"
  );

	function __construct() {
		parent::__construct();

		$this->configuration = array(
			'ebaySession' 				=> Api_Ebay::getSessionId(),
			'ebayUserToken'				=> null,
			'itemsPerCall'				=> 200,
			'updateDisabledArticles'	=> false
 		);
	}
	
	public function getAutoCreateSource() {
		return true;
	}

    public function getStepMax() {
        return 2;
    }

	public function doResetOnSave() {
		return false;
	}

	public function doRequireFile() {
		return false;
	}

  public function doRequirePreperation() {
      return true;
  }

  public function finishPreperation() {
		$this->addUserImportCount( count($_SESSION["importSettings"]["ebayImportAds"]) );
  }
	
	public function isCategoryUsed($categoryValue) {
		return array_key_exists($categoryValue, $this->categoryUsage);
	}

	private function ebayFetchToken() {
		$ebayUserToken = Api_Ebay::fetchToken($this->configuration['ebaySession']);
		if ($ebayUserToken !== false) {
			$this->configuration['ebayUserToken'] = $ebayUserToken;
		} else {
			$error = Api_Ebay::getLastError();
			if ($error !== null) {
				if ($error["ErrorCode"] == 21916016) {
					// Session ID no longer valid!
					$this->configuration['ebaySession'] = Api_Ebay::getSessionId();
					return $this->ebayFetchToken();
				}
			}
			$ebayUserToken = null;
		}
		return $ebayUserToken;
	}
	
	private function ebayGetApiLimit() {
		$ebayUserToken = $this->getConfigurationOption("ebayUserToken");
		if ($ebayUserToken === null) {
			// Try to obtain user token
			$ebayUserToken = $this->ebayFetchToken();
		}
		if ($ebayUserToken !== null) {
			return Api_Ebay::getApiAccessRules($ebayUserToken);
		}
		return false;
	}

  public function ebayGetArticlesCount($listType = "ActiveList") {
		$ebayUserToken = $this->getConfigurationOption("ebayUserToken");
		if ($ebayUserToken === null) {
			// Try to obtain user token
			$ebayUserToken = $this->ebayFetchToken();
		}
		if ($ebayUserToken !== null) {
			$arArticlesBrief = Api_Ebay::getMyeBaySelling($ebayUserToken, array(
				$listType => array(
					"Include" => true, "Pagination" => array("EntriesPerPage" => 1)
				)
			));
			return (int)$arArticlesBrief[$listType]["PaginationResult"]["TotalNumberOfEntries"];
		}
		return 0;
	}
	
	public function ebayGetArticlesIds($listType = "ActiveList", $outputFields = array("ItemID")) {
		// Get ebay credentials
		$ebayUserToken = $this->getConfigurationOption("ebayUserToken");
		if ($ebayUserToken === null) {
			// Try to obtain user token
			$ebayUserToken = $this->ebayFetchToken();
		}
		if ($ebayUserToken !== null) {
			$arArticlesIds = Api_Ebay::getMyeBaySellingIds($ebayUserToken, $listType, $outputFields);
			if (Api_Ebay::getLastError() !== null) {
				$error = Api_Ebay::getLastError();
                if ($error["ErrorCode"] == 21916016) {
                    // Session ID no longer valid!
                    $this->configuration['ebaySession'] = Api_Ebay::getSessionId();
                    return $this->ebayGetArticlesIds($listType, $outputFields);
                }
				throw new Exception($error["LongMessage"], $error["ErrorCode"]);
			}
			return $arArticlesIds;
		}
		return null;
	}

    public function ebayGetArticlesBrief($perPage = null, $pageIndex = 0, $listType = "ActiveList") {
		// Get default settings
		if ($perPage === null) {
			$perPage = (int)$this->getConfigurationOption('itemsPerCall');
		}
		// Get ebay credentials
		$ebayUserToken = $this->getConfigurationOption("ebayUserToken");
		if ($ebayUserToken === null) {
			// Try to obtain user token
			$ebayUserToken = $this->ebayFetchToken();
		}
		if ($ebayUserToken !== null) {
			$arArticlesBrief = Api_Ebay::getMyeBaySelling($ebayUserToken, array($listType => array(
				"Include" => true, "Pagination" => array("EntriesPerPage" => $perPage, "PageNumber" => $pageIndex + 1)
			)));
			if (Api_Ebay::getLastError() !== null) {
				$error = Api_Ebay::getLastError();
                if ($error["ErrorCode"] == 21916016) {
                    // Session ID no longer valid!
                    $this->configuration['ebaySession'] = Api_Ebay::getSessionId();
                    return $this->ebayGetArticlesBrief($perPage, $pageIndex, $listType);
                }
				throw new Exception($error["LongMessage"], $error["ErrorCode"]);
			}
			return $arArticlesBrief;
		}
		return null;
	}
	
	public function autoMapFields() {
		foreach($this->tableFieldsByTableDef as $tableDefName => $tableFields) {
			foreach($tableFields as $tableFieldName => $tableField) {
				if(array_key_exists($tableFieldName, self::$ebayFieldMappingDefault)) {
					$ebayFieldName = self::$ebayFieldMappingDefault[$tableFieldName];
					$arMappingValues = array();
					switch ($ebayFieldName) {
						default:
							if (is_array($ebayFieldName)) {
								foreach ($ebayFieldName as $ebayFieldIndex => $ebayFieldNameCur) {
									$arMappingValues[] = new Ad_Import_Preset_Mapping_Value_DataFieldMappingValue($this->dataFieldsByName[$ebayFieldNameCur]);
								}
							} else {
								$arMappingValues[] = new Ad_Import_Preset_Mapping_Value_DataFieldMappingValue($this->dataFieldsByName[$ebayFieldName]);
							}
							break;
						case "ConditionID": 
							// Standardwerte: 1=Neu, 2=Gebraucht, 3=Defekt, 37=Keine Angabe, 38=Generalüberholt
							$fieldMappingFuncMap = new Ad_Import_Preset_Mapping_Function_ListTableFieldMapFunction(array(
								1000	=> 1,
								1500	=> 1,
								1750	=> 37,
								2000	=> 38,
								2500	=> 38,
								3000	=> 37,
								4000	=> 2,
								5000	=> 2,
								6000 	=> 2,
								7000 	=> 3							
							)); 
							$fieldMappingFunction = new Ad_Import_Preset_Mapping_Value_FunctionMappingValue($fieldMappingFuncMap); 
							$fieldMappingFunction->setFunction($fieldMappingFuncMap);
							$arMappingValues[] = new Ad_Import_Preset_Mapping_Value_DataFieldMappingValue($this->dataFieldsByName[$ebayFieldName]);
							$arMappingValues[] = $fieldMappingFunction;
							break;
					}
					$this->mapField($tableField, $arMappingValues);
				}
			}
		}
        // Add default runtime
        $tableField = $this->getTableFieldByName("LU_LAUFZEIT", "artikel_master");
        $tableField->setDefaultValue($this->configuration["ebayRuntime"]);
	}
	
	public function loadCustom() {
		$arArticles = $this->read(null, 0, 5);
		foreach ($arArticles as $articleIndex => $articleDetails) {
			// Iterate all fields of the article
			$itemFieldIndex = 0;
			foreach (self::$ebayFieldMappingDefault as $fieldInternal => $fieldEbay) {
				if (is_array($fieldEbay)) {
					foreach ($fieldEbay as $fieldEbayIndex => $fieldEbayName) {
						if (!array_key_exists($fieldEbayName, $articleDetails)) {
							$articleDetails[$fieldEbayName] = "";
						}
					}
				} else {
					if (!array_key_exists($fieldEbay, $articleDetails)) {
						$articleDetails[$fieldEbay] = "";
					}
				}
			}
			foreach ($articleDetails as $itemFieldName => $itemFieldValue) {
				if ($articleIndex == 0) {
					// Add field definitions
					$dataField = new Ad_Import_Preset_Mapping_DataField($itemFieldName, $itemFieldName);
					$this->dataFieldsByIdent[$itemFieldName] = $dataField;
					$this->dataFieldsByName[$itemFieldName] = $dataField;
				}
				// Add Example Data
				$dataField = $this->dataFieldsByIdent[$itemFieldName];
				if($dataField instanceof Ad_Import_Preset_Mapping_DataField) {
					$dataField->addExampleData($itemFieldValue);
				}
			}
		}
		#die(var_dump($this->dataFieldsByIdent));
	}
	
	public function loadFile($filename) {
	}

	private function getUserImportCount() {
		if (!is_dir($GLOBALS["ab_path"]."/cache/import/ebay")) {
			mkdir($GLOBALS["ab_path"]."/cache/import/ebay", 0777);
		}
		$userStatusFile = $this->getUserImportCountFile();
		if (file_exists($userStatusFile)) {
			$userStatus = json_decode(file_get_contents($userStatusFile), true);
			if (is_array($userStatus) && ($userStatus["DATE"] == date("Y-m-d"))) {
				return $userStatus["COUNT"];
			}
		}
		return 0;
	}

	private function getUserImportCountFile() {
		return $GLOBALS["ab_path"]."/cache/import/ebay/userCount".$this->getOwnerUser().".json";
	}
	
	private function addUserImportCount($amount) {
		$userCurrentCount = $this->getUserImportCount();
		$userStatusFile = $this->getUserImportCountFile();
		file_put_contents($userStatusFile, json_encode(array(
			"DATE" => date("Y-m-d"), "COUNT" => ($userCurrentCount + $amount)
		)));
	}

	public function getImportProcessType() {
		return 'Ad_Import_Preset_Type_eBayPreset';
	}
	
	/**
	 * @return string
	 */
	public function getAjaxOptions() {
		$ebaySession = $this->getConfigurationOption("ebaySession");
		$ebayUserToken = $this->getConfigurationOption("ebayUserToken");
		if ($ebayUserToken === null) {
			// Try to obtain user token
			$ebayUserToken = $this->ebayFetchToken();
		}
		// Render options template
		$tplEbaySettings = new Template("tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.type.ebay.htm");
        $arRuntimes = Api_LookupManagement::getInstance($GLOBALS["db"])->readByArt("LAUFZEIT");
        foreach ($arRuntimes as $runtimeIndex => $arRuntime) {
            if ($arRuntime["ID_LOOKUP"] == $this->configuration["ebayRuntime"]) {
                $arRuntimes[$runtimeIndex]["SELECTED"] = true;
            }
        }
        $tplEbaySettings->addlist_fast("runtimes", $arRuntimes, "tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.type.ebay.runtime.htm");
		if ($ebayUserToken !== null) {
			$ebayUserDetails = Api_Ebay::getUser($ebayUserToken);
			$tplEbaySettings->addvars($ebayUserDetails, "EBAY_USER_");
		} else {
			$tplEbaySettings->addvar("EBAY_SIGNIN_URL", Api_Ebay::getConsentUrl($ebaySession));
		}
		return $tplEbaySettings->process();
	}
	
	public function getStepSpecial($step, $sourceId = null, $arOptions = array()) {
		switch ($step) {
			case 'limit_reached':
				$tplLimitReachedEbay = new Template("tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.ebay.limit_reached.htm");
				$tplLimitReachedEbay->addvars($arOptions);
				return $tplLimitReachedEbay;
			case 'init':			
				if (array_key_exists("ajax", $_REQUEST) && ($_REQUEST["ajax"] == "dataTable")) {
				    $jsonResult = array("success" => false, "error" => "Unknown ajax action!");
				    switch ($_POST["action"]) {
							case "getResults":
								$options = json_decode($_POST["queryOptions"], true);
								$calcFoundRows = (array_key_exists("calcFoundRows", $_POST) ? $_POST["calcFoundRows"] : false);
								if (is_array($options)) {
									try {
										$resultCount = ($calcFoundRows ? $this->ebayGetArticlesCount() : NULL);
										$resultKeys = ($calcFoundRows ? array() : NULL);
										$resultBody = $this->getStepSpecial_DataTableBody($options["offset"], $options["limit"], "init", $options["where"]);
										if (!empty($_SESSION["importSettings"]["ebayImportSearchResult"])) {
											$resultCount = count($_SESSION["importSettings"]["ebayImportSearchResult"]["ids"]);
											$resultKeys = $_SESSION["importSettings"]["ebayImportSearchResult"]["ids"];
										}
										$jsonResult = array("success" => true, "body" => $resultBody, "count" => $resultCount, "keys" => $resultKeys);
									} catch (Exception $e) {
										if ($e->getCode() == 518) {
											$jsonResult["error"] = "Sehr geehrter Kunde,\n".
													"die mögliche Anzahl der Imports aus ebay für den heutigen Tag wurde erreicht.\n".
													"Weitere imports sind leider erst Morgen wieder möglich. Bitte versuchen Sie es dann erneut.";
										} else {
											$jsonResult["error"] = $e->getMessage();
										}
									}
								} else {
									$jsonResult["error"] = "Invalid / missing query options!";
								}
								break;
							case "getSelectKeys":
								$options = json_decode($_POST["queryOptions"], true);
								if (is_array($options)) {
									if (!is_array($_SESSION["importSettings"]["ebayImportAdsIds"])) {
										// Get ids for all available articles
										$_SESSION["importSettings"]["ebayImportAdsIds"] = $this->ebayGetArticlesIds();
									}
									$jsonResult = array("success" => true, "keys" => $_SESSION["importSettings"]["ebayImportAdsIds"]);
								} else {
									$jsonResult["error"] = "Invalid / missing query options!";
								}
								break;
							case "executeAction":
								$arTargets = json_decode($_POST["targetKeys"]);
								if (is_array($arTargets)) {
									switch ($_POST["targetAction"]) {
										case "save":
											$_SESSION["importSettings"]["ebayImportAds"] = $arTargets;
											break;
										default:
											// Do not rewrite cache for unknown actions
											break 2;
									}
									Api_TraderApiHandler::getInstance()->triggerEvent("VENDOR_HOMEPAGE_PLUGIN_CACHE");
									$jsonResult = array("success" => true, "preventReload" => true);
								}
								break;
				    }
				    header("Content-Type: application/json");
				    die(json_encode($jsonResult));
				}
				// Settings
				$userLimitMax = 1000;
				// Reset selected articles
				if (!array_key_exists("importSettings", $_SESSION)) {
					$_SESSION["importSettings"] = array();
				}
        if (!array_key_exists("ebayImportSource", $_SESSION["importSettings"])
            || ($_SESSION["importSettings"]["ebayImportSource"] != $sourceId)) {
					// New import selected
					// Check api limits
					$callsLeft = 0;
					$arApiLimit = $this->ebayGetApiLimit();
					if (is_array($arApiLimit)) {
						if ($arApiLimit["RuleStatus"] == "RuleOff") {
							$callsLeft = $userLimitMax;
						} else {
							$callsLeft = $arApiLimit["DailyHardLimit"] - $arApiLimit["DailyUsage"];
						}
						header("X-Debug-Api-Calls-Left: ".$callsLeft);
						header("X-Debug-Api: ".json_encode($arApiLimit));
					}
					$importsAllowed = max(min($userLimitMax, ($callsLeft - 100) / 2), 0);
					if ($importsAllowed <= 0) {
						// Call limit nearly depleted! Display error message.
						return $this->getStepSpecial("limit_reached", $sourceId);
					}
					// User import limits
					$userImports = $this->getUserImportCount();
					if ($userImports > $userLimitMax) {
						// Maximum allowed imports for this user reached! Display error message.
						return $this->getStepSpecial("limit_reached", $sourceId, array("USER_LIMIT" => true, "USER_LIMIT_MAX" => $userLimitMax));
					}
					$importsAllowed = min($userLimitMax - $userImports, $importsAllowed);
					// Initialize session storage
					$_SESSION["importSettings"]["ebayImportSource"] = $sourceId;
					$_SESSION["importSettings"]["ebayImportSearchResult"] = array();
					$_SESSION["importSettings"]["ebayImportAds"] = array();
					$_SESSION["importSettings"]["ebayImportAdsIds"] = null;
					$_SESSION["importSettings"]["ebayImportAdsEdit"] = array();
					$_SESSION["importSettings"]["ebayImportAdsCache"] = array();
					$_SESSION["importSettings"]["ebayImportCountMax"] = $importsAllowed;
					$arArticlesManufacturers = $GLOBALS["db"]->fetch_table("
						SELECT IMPORT_IDENTIFIER, FK_MAN FROM `ad_master` 
						WHERE FK_USER=".$this->getOwnerUser()." AND IMPORT_SOURCE=".(int)$sourceId);
					if (!empty($arArticlesManufacturers)) {
						// There are articles already imported from this source! Get list of available article ids
						$_SESSION["importSettings"]["ebayImportAdsIds"] = $this->ebayGetArticlesIds();
						foreach ($arArticlesManufacturers as $articleIndex => $articleDetails) {
							$articleIdEbay = $articleDetails["IMPORT_IDENTIFIER"];
							if (!in_array($articleIdEbay, $_SESSION["importSettings"]["ebayImportAdsIds"])) {
								// Article no longer available on ebay! Skip!
								continue;
							}
							// Add to selection
							$_SESSION["importSettings"]["ebayImportAds"][] = $articleIdEbay;
							// Add the manufacturer mapping for this article
							if (!array_key_exists($articleIdEbay, $_SESSION["importSettings"]["ebayImportAdsEdit"])) {
								$_SESSION["importSettings"]["ebayImportAdsEdit"][$articleIdEbay] = array();
							}
							$_SESSION["importSettings"]["ebayImportAdsEdit"][$articleIdEbay]["Manufacturer"] = $articleDetails["FK_MAN"];
						}
					}

				}
				// Default configuration
				$arQueryConfig = array(
					"offset"	=> 0,
					"limit"		=> 20,
                    "where"     => array()
				);
				// Field labels
				$dtFieldsLabeled = $this->getStepSpecial_DataTableFields();
				// Field properties
				$selectable = false;
				$dtResultOffset = $arQueryConfig["offset"];
				$dtResultsPerPage = $arQueryConfig["limit"];
				$dtResultCount = $this->ebayGetArticlesCount();
				// Render fields
				$fieldIndex = 0;
				$arFieldsCached = array();
				$arFieldsSelectKey = array("ItemID");
				foreach ($dtFieldsLabeled as $fieldName => $fieldLabel) {
					if ($fieldLabel === NULL) {
						continue;
					}
					// Add field to cache
					$arField = $this->getStepSpecial_DataTableField($fieldName);
					$arFieldsCached[] = $arField;
					// Create header template
					$tplHeader = new Template("tpl/" . $GLOBALS['s_lang'] . "/my-import-presets-edit.step.ebay.init.header.htm");
					$label = Translation::readTranslation("marketplace", "dataTable.ebayImport.field." . $fieldName, null, array(), $fieldLabel);
					$tplHeader->addvar("index", $fieldIndex);
					$tplHeader->addvar("field", $fieldName);
					$tplHeader->addvar("label", $label);
					$tplHeader->addvar("sortable", $arField["sortable"]);
					$tplHeader->addvar("numeric", $arField["numeric"]);
					if ($arField["sortable"]) {
						$fieldSortName = (array_key_exists("sortTarget", $arField) ? $arField["sortTarget"] : $fieldName);
						$fieldSortDir = false;	// TODO
						$tplHeader->addvar("sortTarget", $fieldSortName);
						if ($fieldSortDir !== false) {
							$tplHeader->addvar("sortDir", $fieldSortDir);
							$tplHeader->addvar("sortDir_" . $fieldSortDir, 1);
						}
					}
					if ($arField["selectKey"]) {
						$selectable = true;
					}
					$arHeader[] = $tplHeader;
					$fieldIndex++;
				}
				// Create frame template
				$tplInitEbay = new Template("tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.ebay.init.htm");	
				$tplInitEbayActions = new Template("tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.ebay.init.actions.htm");	
				$tplInitEbayFilter = new Template("tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.ebay.init.filter.htm");	
				$tplInitEbay->addvar("hash", sha1("ebayImport"));
				$tplInitEbay->addvar("action", $tplInitEbayActions);
				$tplInitEbay->addvar("filter", $tplInitEbayFilter);
				$tplInitEbay->addvar("ajaxUrl", $_SERVER["REQUEST_URI"]);
				$tplInitEbay->addvar("jsonQueryOptions", json_encode($arQueryConfig));
				$tplInitEbay->addvar("body", $this->getStepSpecial_DataTableBodyInternal($dtResultOffset, $dtResultsPerPage, $dtFieldsLabeled, $arFieldsCached, $arFieldsSelectKey));
				$tplInitEbay->addvar("resultFirst", $dtResultOffset + 1);
				if ($dtResultCount < ($dtResultOffset + $dtResultsPerPage)) {
					$tplInitEbay->addvar("resultLast", $dtResultCount);
				} else {
					$tplInitEbay->addvar("resultLast", ($dtResultOffset + $dtResultsPerPage));
				}
				$tplInitEbay->addvar("resultCount", $dtResultCount);
				$tplInitEbay->addvar("limit", $dtResultsPerPage);
				$tplInitEbay->addvar("colCount", count($dtFieldsLabeled) + ($selectable ? 1 : 0));
				$tplInitEbay->addvar("selectable", $selectable);
				$tplInitEbay->addvar("selection", json_encode($_SESSION["importSettings"]["ebayImportAds"]));
				$tplInitEbay->addvar("selectionLimit", $_SESSION["importSettings"]["ebayImportCountMax"]);
				$tplInitEbay->addvar("selectionLimitMax", $userLimitMax);
				$tplInitEbay->addvar("header", $arHeader);
				// Trigger plugin event
				$pluginInfoParams = new Api_Entities_EventParamContainer(array("pluginInfo" => "", "preset" => $this));
				Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::IMPORT_EBAY_INIT, $pluginInfoParams);
				$tplInitEbay->addvar("PLUGIN_INFO", $pluginInfoParams->getParam("pluginInfo"));
				return $tplInitEbay;
			case 'categories':
				$tplCategoriesEbay = new Template("tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.ebay.categories.htm");
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
				/** @var Ad_Import_PresetEditor_PresetEditorManagement $importPresetEditorManagement */
				$importPresetEditorManagement = Ad_Import_PresetEditor_PresetEditorManagement::getInstance($GLOBALS["db"]);
				$importPresetEditorManagement->reset();
				$importPresetEditorManagement->loadPreset($this);
				// Ensure category field is properly mapped
				$this->setCategoryField(null);
				$categoryTableFieldMapping = $this->getFieldMappingByTableField('FK_KAT');
				if(($categoryTableFieldMapping instanceof Ad_Import_Preset_Mapping_FieldMap) && is_array($categoryTableFieldMapping->getFieldValues())) {
					foreach ($categoryTableFieldMapping->getFieldValues() as $key => $value) {
						if ($value instanceof Ad_Import_Preset_Mapping_Value_DataFieldMappingValue) {
							$this->addCategoryField($value->getValue());
						}
					}
				}
				// Load categories
				$this->loadDataCategories();
				// Show category mapping
				$importPresetEditorManagement->setCurrentStep(3);
				$tplCategoriesEbay->addvar('CATEGORY_MAPPING', $importPresetEditorManagement->loadStep(3));
				// Trigger plugin event
				$pluginInfoParams = new Api_Entities_EventParamContainer(array("pluginInfo" => "", "preset" => $this));
				Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::IMPORT_EBAY_CATEGORIES, $pluginInfoParams);
				$tplCategoriesEbay->addvar("PLUGIN_INFO", $pluginInfoParams->getParam("pluginInfo"));
				return $tplCategoriesEbay;
			case 'edit':
				if (array_key_exists("ajax", $_REQUEST) && ($_REQUEST["ajax"] == "dataTable")) {
					$jsonResult = array("success" => false, "error" => "Unknown ajax action!");
					switch ($_POST["action"]) {
						case "getResults":
							$options = json_decode($_POST["queryOptions"], true);
							$calcFoundRows = (array_key_exists("calcFoundRows", $_POST) ? $_POST["calcFoundRows"] : false);
							if (is_array($options)) {
									$resultCount = count($_SESSION["importSettings"]["ebayImportAds"]);
									$resultBody = $this->getStepSpecial_DataTableBody($options["offset"], $options["limit"], "edit");
									$jsonResult = array("success" => true, "body" => $resultBody, "count" => $resultCount);
							} else {
									$jsonResult["error"] = "Invalid / missing query options!";
							}
							break;
						case "getSelectKeys":
							$options = json_decode($_POST["queryOptions"], true);
							if (is_array($options)) {
									$jsonResult = array("success" => true, "keys" => $_SESSION["importSettings"]["ebayImportAds"]);
							} else {
									$jsonResult["error"] = "Invalid / missing query options!";
							}
							break;
						case "executeAction":
							$arTargets = json_decode($_POST["targetKeys"]);
							if (is_array($arTargets)) {
								switch ($_POST["targetAction"]) {
									case "save-title":
										foreach ($arTargets as $targetIndex => $targetId) {
											if (!array_key_exists($targetId, $_SESSION["importSettings"]["ebayImportAdsEdit"])) {
												$_SESSION["importSettings"]["ebayImportAdsEdit"][$targetId] = array();
											}
											$_SESSION["importSettings"]["ebayImportAdsEdit"][$targetId]["Title"] = json_decode($_POST["targetParameters"]);
										}
										break;
									case "save-manufacturer":
										foreach ($arTargets as $targetIndex => $targetId) {
											if (!array_key_exists($targetId, $_SESSION["importSettings"]["ebayImportAdsEdit"])) {
												$_SESSION["importSettings"]["ebayImportAdsEdit"][$targetId] = array();
											}
											$_SESSION["importSettings"]["ebayImportAdsEdit"][$targetId]["Manufacturer"] = json_decode($_POST["targetParameters"]);
										}
										break;
									case "save-category":
										list($paramId, $paramLabel) = json_decode($_POST["targetParameters"]);
										foreach ($arTargets as $targetIndex => $targetId) {
											if (!array_key_exists($targetId, $_SESSION["importSettings"]["ebayImportAdsEdit"])) {
												$_SESSION["importSettings"]["ebayImportAdsEdit"][$targetId] = array();
											}
											$_SESSION["importSettings"]["ebayImportAdsEdit"][$targetId]["PrimaryCategory_CategoryName"] = "et#".$paramId."#".$paramLabel;
										}
										break;
									default:
										// Do not rewrite cache for unknown actions
										break 2;
								}
								Api_TraderApiHandler::getInstance()->triggerEvent("VENDOR_HOMEPAGE_PLUGIN_CACHE");
								$jsonResult = array("success" => true, "preventReload" => true);
							}
							break;
					}
					header("Content-Type: application/json");
					die(json_encode($jsonResult));
				}
				// Default configuration
				$arQueryConfig = array(
					"offset"	=> 0,
					"limit"		=> 20
				);
				// Field labels
				$dtFieldsLabeled = $this->getStepSpecial_DataTableFields("edit");
				// Field properties
				$selectable = false;
				$dtResultOffset = $arQueryConfig["offset"];
				$dtResultsPerPage = $arQueryConfig["limit"];
				$dtResultCount = count($_SESSION["importSettings"]["ebayImportAds"]);
				// Render fields
				$fieldIndex = 0;
				$arFieldsCached = array();
				$arFieldsSelectKey = array("ItemID");
				foreach ($dtFieldsLabeled as $fieldName => $fieldLabel) {
					if ($fieldLabel === NULL) {
						continue;
					}
					// Add field to cache
					$arField = $this->getStepSpecial_DataTableField($fieldName, "edit");
					$arFieldsCached[] = $arField;
					// Create header template
					$tplHeader = new Template("tpl/" . $GLOBALS['s_lang'] . "/my-import-presets-edit.step.ebay.edit.header.htm");
					$label = Translation::readTranslation("marketplace", "dataTable.ebayImport.field." . $fieldName, null, array(), $fieldLabel);
					$tplHeader->addvar("index", $fieldIndex);
					$tplHeader->addvar("field", $fieldName);
					$tplHeader->addvar("label", $label);
					$tplHeader->addvar("sortable", $arField["sortable"]);
					$tplHeader->addvar("numeric", $arField["numeric"]);
					if ($arField["sortable"]) {
						$fieldSortName = (array_key_exists("sortTarget", $arField) ? $arField["sortTarget"] : $fieldName);
						$fieldSortDir = false;	// TODO
						$tplHeader->addvar("sortTarget", $fieldSortName);
						if ($fieldSortDir !== false) {
							$tplHeader->addvar("sortDir", $fieldSortDir);
							$tplHeader->addvar("sortDir_" . $fieldSortDir, 1);
						}
					}
					if ($arField["selectKey"]) {
						$selectable = true;
					}
					$arHeader[] = $tplHeader;
					$fieldIndex++;
				}
				// Create frame template
				$tplEditEbay = new Template("tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.ebay.edit.htm");
				$tplEditEbayFilter = new Template("tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.ebay.edit.filter.htm");	
				$tplEditEbay->addvar("hash", sha1("ebayImport"));
				$tplEditEbay->addvar("filter", $tplEditEbayFilter);
				$tplEditEbay->addvar("ajaxUrl", $_SERVER["REQUEST_URI"]);
				$tplEditEbay->addvar("jsonQueryOptions", json_encode($arQueryConfig));
				$tplEditEbay->addvar("body", $this->getStepSpecial_DataTableBodyInternal($dtResultOffset, $dtResultsPerPage, $dtFieldsLabeled, $arFieldsCached, $arFieldsSelectKey, "edit"));
				$tplEditEbay->addvar("resultFirst", $dtResultOffset + 1);
				if ($dtResultCount < ($dtResultOffset + $dtResultsPerPage)) {
					$tplEditEbay->addvar("resultLast", $dtResultCount);
				} else {
					$tplEditEbay->addvar("resultLast", ($dtResultOffset + $dtResultsPerPage));
				}
				$tplEditEbay->addvar("resultCount", $dtResultCount);
				$tplEditEbay->addvar("limit", $dtResultsPerPage);
				$tplEditEbay->addvar("colCount", count($dtFieldsLabeled) + ($selectable ? 1 : 0));
				$tplEditEbay->addvar("selectable", $selectable);
				$tplEditEbay->addvar("header", $arHeader);
				// Trigger plugin event
				$pluginInfoParams = new Api_Entities_EventParamContainer(array("pluginInfo" => "", "preset" => $this, "selected" => $_SESSION["importSettings"]["ebayImportAds"]));
				Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::IMPORT_EBAY_EDIT, $pluginInfoParams);
				$tplEditEbay->addvar("PLUGIN_INFO", $pluginInfoParams->getParam("pluginInfo"));
				return $tplEditEbay;
			case 'BaseMapping':
				$tplBaseMappingEbay = new Template("tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.basemapping.ebay.htm");
				$ebayUserToken = $this->getConfigurationOption("ebayUserToken");
				if ($ebayUserToken === null) {
					// Try to obtain user token
					$ebayUserToken = $this->ebayFetchToken();
				}
				if ($ebayUserToken !== null) {
					$ebayUserDetails = Api_Ebay::getUser($ebayUserToken);
					$ebayUserArticles = Api_Ebay::getMyeBaySelling($ebayUserToken, array(
						"ActiveList" => array(
							"Include" => true, "Pagination" => array("EntriesPerPage" => 1)
						)
					));
					$tplBaseMappingEbay->addvars($ebayUserDetails, "EBAY_USER_");
					$tplBaseMappingEbay->addvar("AD_COUNT", (int)$ebayUserArticles["ActiveList"]["PaginationResult"]["TotalNumberOfEntries"]);
				}
				return $tplBaseMappingEbay->process();
			default:
				return false;
		}
	}
	
	private function getStepSpecial_DataTableFields($rowType = "init") {
		if ($rowType == "init") {
			return array(
				"ItemID" 								                => Translation::readTranslation("marketplace", "import.preset.init.ebay.col.itemid", null, array(), "eBay Artikel-ID"),
				"PictureDetails_PictureURL" 			      => Translation::readTranslation("marketplace", "import.preset.init.ebay.col.picture", null, array(), "Bild"),
				"Title" 								                => Translation::readTranslation("marketplace", "import.preset.init.ebay.col.title", null, array(), "Titel"),
				"PrimaryCategory_CategoryName" 		      => Translation::readTranslation("marketplace", "import.preset.init.ebay.col.category", null, array(), "Kategorie"),
				"Location" 								              => Translation::readTranslation("marketplace", "import.preset.init.ebay.col.location", null, array(), "Ort"),
				"Quantity" 								              => Translation::readTranslation("marketplace", "import.preset.init.ebay.col.quantity", null, array(), "Menge"),
				"SellingStatus_ConvertedCurrentPrice" 	=> Translation::readTranslation("marketplace", "import.preset.init.ebay.col.price", null, array(), "Preis")
			);
		} else {
			$arFields = array();
			$arFields["ItemID"] = Translation::readTranslation("marketplace", "import.preset.init.ebay.col.itemid", null, array(), "eBay Artikel-ID");
			$arFields["PictureDetails_PictureURL"] = Translation::readTranslation("marketplace", "import.preset.init.ebay.col.picture", null, array(), "Bild");
			$arFields["Title"] = Translation::readTranslation("marketplace", "import.preset.init.ebay.col.title", null, array(), "Titel");
			$arFields["PrimaryCategory_CategoryName"] = Translation::readTranslation("marketplace", "import.preset.init.ebay.col.category", null, array(), "Kategorie");
			if ($GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["USE_PRODUCT_DB"]) {
				$arFields["Manufacturer"] = Translation::readTranslation("marketplace", "import.preset.init.ebay.col.manufacturer", null, array(), "Hersteller");
			}
			$arFields["Location"] = Translation::readTranslation("marketplace", "import.preset.init.ebay.col.location", null, array(), "Ort");
			$arFields["Quantity"] = Translation::readTranslation("marketplace", "import.preset.init.ebay.col.quantity", null, array(), "Menge");
			$arFields["SellingStatus_ConvertedCurrentPrice"] = Translation::readTranslation("marketplace", "import.preset.init.ebay.col.price", null, array(), "Preis");
			return $arFields;
		}
	}
	
	private function getStepSpecial_DataTableField($fieldName, $rowType = "init") {
		$dtFieldsSortable = array();
		$dtFieldsNumeric = array("ItemID", "Quantity", "SellingStatus_ConvertedCurrentPrice");
		$dtFieldsKey = array("ItemID");
		$dtCallback = null;
		if ($rowType == "init") {
			$dtFieldsTemplates = array(
				"PictureDetails_PictureURL"								=> "my-import-presets-edit.step.ebay.init.col_image.htm",
				"SellingStatus_ConvertedCurrentPrice"	    => "my-import-presets-edit.step.ebay.init.col_price.htm"
			);
		} else {
			$dtFieldsTemplates = array(
				"PictureDetails_PictureURL"				        => "my-import-presets-edit.step.ebay.init.col_image.htm",
        "SellingStatus_ConvertedCurrentPrice"	    => "my-import-presets-edit.step.ebay.init.col_price.htm",
				"Title"									                  => "my-import-presets-edit.step.ebay.edit.col_title.htm",
				"PrimaryCategory_CategoryName"			      => "my-import-presets-edit.step.ebay.edit.col_category.htm",
				"Manufacturer"							              => "my-import-presets-edit.step.ebay.edit.col_manufacturer.htm"
			);
		}
		if ($fieldName == "Manufacturer") {
			$dtCallback = function($tplCol, $arRow, Ad_Import_Preset_Type_eBayPreset $preset) {
				$categoryId = null;
				if (preg_match("/^et\#([0-9]+)#(.+)$/", $arRow["PrimaryCategory_CategoryName"], $arMatch)) {
					$categoryId = (int)$arMatch[1];
				} else {
					$categoryId = (int)$preset->getCategoryMappingByKey($arRow["PrimaryCategory_CategoryName"]);
				}
				if ($categoryId > 0) {
					$itemId = $arRow["ItemID"];
					$arManufacturers = Api_Entities_ManufacturerGroup::getManufacturersByCategory($categoryId);
					/*
					if ($_SERVER["REMOTE_ADDR"] == "176.94.235.220") {
						die(var_dump($arManufacturers, $arRow));
					}
					*/
					if (array_key_exists($itemId, $_SESSION["importSettings"]["ebayImportAdsEdit"]) && ($_SESSION["importSettings"]["ebayImportAdsEdit"][$itemId]["Manufacturer"] > 0)) {
						$tplCol->addvar("SELECTED", $_SESSION["importSettings"]["ebayImportAdsEdit"][$itemId]["Manufacturer"]);
					} else if (array_key_exists("ProductListingDetails_BrandMPN_Brand", $arRow)) {
						$idMan = $GLOBALS["db"]->fetch_atom("SELECT ID_MAN FROM `manufacturers` WHERE NAME LIKE '".mysql_real_escape_string($arRow["ProductListingDetails_BrandMPN_Brand"])."'");
						if ($idMan > 0) {
							$_SESSION["importSettings"]["ebayImportAdsEdit"][$itemId]["Manufacturer"] = $idMan;
							$tplCol->addvar("SELECTED", $idMan);
						}
					}
					$tplCol->addvar("category", (int)$categoryId);
					$tplCol->addlist("options", $arManufacturers, "tpl/".$GLOBALS["s_lang"]."/my-import-presets-edit.step.ebay.edit.col_manufacturer.option.htm");
				}
			};
		}
		if ($fieldName == "PrimaryCategory_CategoryName") {
			$dtCallback = function($tplCol, $arRow, Ad_Import_Preset_Type_eBayPreset $preset) {
				$categoryId = null;
				if (preg_match("/^et\#([0-9]+)#(.+)$/", $arRow["PrimaryCategory_CategoryName"], $arMatch)) {
					$categoryId = (int)$arMatch[1];
					$tplCol->addvar("valueId", $categoryId);
					$tplCol->addvar("valueLabel", $arMatch[2]);
				} else {
          $categoryId = (int)$preset->getCategoryMappingByKey($arRow["PrimaryCategory_CategoryName"]);
          if ($categoryId > 0) {
            $arCategory = Api_CategoryManagement::getInstance($GLOBALS["db"])->readById($categoryId);
            if ($arCategory !== false) {
              $tplCol->addvar("valueId", $categoryId);
              $tplCol->addvar("valueLabel", $arCategory["V1"]);
            }
          }
        }
			};
		}
		return array(
			"sortable"		=> in_array($fieldName, $dtFieldsSortable),
			"numeric"		=> in_array($fieldName, $dtFieldsNumeric),
			"selectKey"		=> ($rowType == "init" ? in_array($fieldName, $dtFieldsKey) : false),
			"colTemplate"	=> (array_key_exists($fieldName, $dtFieldsTemplates) ? $dtFieldsTemplates[$fieldName] : "my-import-presets-edit.step.ebay.".$rowType.".col.htm"),
			"colCallback"	=> $dtCallback
		);
	}
	
	private function getStepSpecial_DataTableBody($dtResultOffset, $dtResultsPerPage, $rowType = "init", $searchParameters = array()) {
        // Create field cache for body
        $dtFieldsLabeled = $this->getStepSpecial_DataTableFields($rowType);
        $arFieldsCached = array();
        $fieldIndex = 0;
        foreach ($dtFieldsLabeled as $fieldName => $fieldLabel) {
            $arField = $this->getStepSpecial_DataTableField($fieldName, $rowType);
            if ($fieldLabel === NULL) {
                continue;
            }
            // Add field to cache
            $arFieldsCached[] = $arField;
            $fieldIndex++;
        }
		$arFieldsSelectKey = array("ItemID");
        return $this->getStepSpecial_DataTableBodyInternal($dtResultOffset, $dtResultsPerPage, $dtFieldsLabeled, $arFieldsCached, $arFieldsSelectKey, $rowType, $searchParameters);
	}
	
	private function getStepSpecial_DataTableBodyInternal($dtResultOffset, $dtResultsPerPage, $dtFieldsLabeled, $arFieldsCached, $arFieldsSelectKey, $rowType = "init", $searchParameters = array()) {
		$dtResultPage = $dtResultOffset / $dtResultsPerPage;
		// Get ebay credentials
		$ebayUserToken = $this->getConfigurationOption("ebayUserToken");
		if ($ebayUserToken === null) {
			// Try to obtain user token
			$ebayUserToken = $this->ebayFetchToken();
		}
		// Get articles
		$arResult = array();
		$arArticlesIds = array();
		if ($rowType == "init") {
			if (array_key_exists("search", $searchParameters)) {
				$searchMode = (array_key_exists("searchMode", $searchParameters) ? $searchParameters["searchMode"] : "title");
				$searchText = strtolower($searchParameters["search"]);
				$searchHash = sha1($searchText."|".$searchMode);
				if (!array_key_exists("hash", $_SESSION["importSettings"]["ebayImportSearchResult"]) || ($searchHash != $_SESSION["importSettings"]["ebayImportSearchResult"]["hash"])) {
					// Run search now
					switch ($searchMode) {
						case 'title':
						default:
							$arSearchResult = $this->ebayGetArticlesIds("ActiveList", array("ItemID", "Title"));
							foreach ($arSearchResult as $resultIndex => $resultData) {
								if (strpos(strtolower($resultData["Title"]), $searchText) !== false) {
									$arArticlesIds[] = $resultData["ItemID"];
								}
							}
							break;
						case 'full':
							$arArticlesIdsActive = $this->ebayGetArticlesIds("ActiveList");
							$arSearchResult = Api_Ebay::getItemList($arArticlesIdsActive, $ebayUserToken, "ReturnAll", array("ItemID", "Title", "PrimaryCategory", "Description"));
							foreach ($arSearchResult as $resultIndex => $resultData) {
								if ((strpos(strtolower($resultData["Title"]), $searchText) !== false) || (strpos(strtolower($resultData["Description"]), $searchText) !== false) ||
										(strpos(strtolower($resultData["PrimaryCategory_CategoryName"]), $searchText) !== false)) {
									$arArticlesIds[] = $resultData["ItemID"];
								}
							}
							break;
					}
					// Save in session
					$_SESSION["importSettings"]["ebayImportSearchResult"] = array(
						"hash"  => $searchHash,
						"ids"   => $arArticlesIds
					);
				}
				$arArticlesIds = array_slice($_SESSION["importSettings"]["ebayImportSearchResult"]["ids"], $dtResultOffset, $dtResultsPerPage);
			} else {
				// Clear previous search result if exists
				$_SESSION["importSettings"]["ebayImportSearchResult"] = array();
				// Get articles
				$arArticlesBrief = $this->ebayGetArticlesBrief($dtResultsPerPage, $dtResultPage, "ActiveList");
				if (($arArticlesBrief !== null) && ($ebayUserToken !== null)) {
					// Iterate all active ads
					foreach ($arArticlesBrief["ActiveList"]["ItemArray"] as $itemIndex => $itemBrief) {
						#die(var_dump($itemBrief));
						$arArticlesIds[] = $itemBrief["ItemID"];
					}
				}
			}
		} else {
			if (count($_SESSION["importSettings"]["ebayImportAds"]) > $dtResultOffset) {
				$arArticlesIds = array_slice($_SESSION["importSettings"]["ebayImportAds"], $dtResultOffset, $dtResultsPerPage);
			}
		}
		$arArticlesActive = Api_Ebay::getItemList($arArticlesIds, $ebayUserToken, "ReturnAll", self::$ebayOutputFilterDefault);
		foreach ($arArticlesActive as $itemIndex => $itemDetailed) {
			$itemDetailed["IMPORT_TASK"] = "UPDATE";
			$itemId = $itemDetailed["ItemID"];
			if (array_key_exists($itemId, $_SESSION["importSettings"]["ebayImportAdsEdit"])) {
				$arResult[] = array_merge(array_flatten($itemDetailed, true), $_SESSION["importSettings"]["ebayImportAdsEdit"][$itemId]);
			} else {
				$arResult[] = array_flatten($itemDetailed, true);
			}
		}
    // Create rows
    $arRows = array();
    foreach ($arResult as $rowIndex => $arRow) {
      $tplRow = new Template("tpl/".$GLOBALS['s_lang']."/my-import-presets-edit.step.ebay.".$rowType.".row.htm");
      $selectKey = array();
      // Create cols
      $arCols = array();
      $fieldIndex = 0;
      foreach ($dtFieldsLabeled as $fieldName => $fieldLabel) {
        if ($fieldLabel === NULL) {
            continue;
        }
        $arField = $arFieldsCached[$fieldIndex];
        $fieldValue = $arRow[$fieldName];
        if ($fieldName == "SellingStatus_ConvertedCurrentPrice") {
            $fieldValue .= " ".$GLOBALS["nar_systemsettings"]['MARKTPLATZ']['CURRENCY'];
        }
        $tplColSource = "data_table.col.htm";
        $arCol = array(
            "field"     => $fieldName,
            "numeric"   => preg_match("/^[0-9\.\:\,\-\+\s]+$/", $fieldValue),
            "value"     => $fieldValue
        );
        if (($arField !== NULL) && array_key_exists("colTemplate", $arField) && ($arField["colTemplate"] !== NULL)) {
            $tplColSource = $arField["colTemplate"];
        }
        $tplCol = new Template("tpl/".$GLOBALS['s_lang']."/".$tplColSource);
        if ($arField !== NULL) {
            if ($arField["selectKey"]) {
                $selectKey[] = $fieldValue;
            }
            if (array_key_exists("colCallback", $arField) && ($arField["colCallback"] !== NULL)) {
                $arField["colCallback"]($tplCol, $arRow, $this);
            }
        }
        if ($fieldName == "PrimaryCategory_CategoryName") {
          //die(var_dump($arRow, $this->categoryMapping));
        }
        $tplCol->addvars($arCol);
        $tplCol->addvars($arRow, "row_");
        $arCols[] = $tplCol;
        $fieldIndex++;
      }
      if (!empty($arFieldsSelectKey)) {
        $tplRow->addvar("selectable", true);
        if (count($arFieldsSelectKey) == 1) {
          // Single field select key
          $selectKeyCur = $arRow[ $arFieldsSelectKey[0] ];
          $tplRow->addvar("selectKey", $selectKeyCur);
          $tplRow->addvar("selected", in_array($selectKeyCur, $_SESSION["importSettings"]["ebayImportAds"]));
        } else {
          // Multiple field select key
          $arSelectKeys = array();
          foreach ($arFieldsSelectKey as $selectKeyField) {
              $arSelectKeys[] = $arRow[ $selectKeyField ];
          }
          $tplRow->addvar("selectKeyJson", json_encode($arSelectKeys));
          $tplRow->addvar("selected", in_array($arSelectKeys, $_SESSION["importSettings"]["ebayImportAds"]));
        }
      }
      $tplRow->addvar("cols", $arCols);
      $arRows[] = $tplRow;
    }
    // Create body template
    $tplDataTable = new Template("tpl/".$GLOBALS['s_lang']."/my-import-presets-edit.step.ebay.init.body.htm");
    $tplDataTable->addvar("rows", $arRows);
    // Render
    return $tplDataTable->process(true);
	}

	public function loadDataCategories() {
		parent::loadDataCategories();
		
		// Reset used categories
		$this->clearCategoryUsage();
		
		// Get ebay credentials
		$ebayUserToken = $this->getConfigurationOption("ebayUserToken");
		if ($ebayUserToken === null) {
			// Try to obtain user token
			$ebayUserToken = $this->ebayFetchToken();
		}
		// Get articles
		$arArticlesIds = $_SESSION["importSettings"]["ebayImportAds"];
		if (($arArticlesIds !== null) && ($ebayUserToken !== null)) {
			$arArticles = Api_Ebay::getItemList($arArticlesIds, $ebayUserToken, "ReturnAll", array("ItemID", "Title", "PrimaryCategory", "Description"));
			foreach ($arArticles as $itemIndex => $itemDetailed) {
				$this->addCategoryDataValues($itemDetailed["PrimaryCategory"]["CategoryName"]);
				$this->addCategoryUsage($itemDetailed["PrimaryCategory"]["CategoryName"]);
			}
		}
	}

	public function read($filename = null, $step = 0, $linesPerRun = null, $arOptions = null) {
		// Get default settings
		if ($linesPerRun === null) {
			$linesPerRun = (int)$this->getConfigurationOption('itemsPerCall');
		}
		if ($arOptions === null) {
			$arOptions = array(
				"ProcessOptions"	=> (array_key_exists("importSettings", $_SESSION) ? $_SESSION["importSettings"] : array())
			);
		}
		// Get ebay credentials
		$ebayUserToken = $this->getConfigurationOption("ebayUserToken");
		if ($ebayUserToken === null) {
			// Try to obtain user token
			$ebayUserToken = $this->ebayFetchToken();
		}
		// Get articles
		$arArticles = array();
		// Active
		$pageLimit = $step + 1;
		if (array_key_exists("ActiveList", $arOptions)) {
			$pageLimit = ceil($arOptions["ActiveList"] / $linesPerRun);
		}
		if ($step < $pageLimit) {
			$arArticlesIds = array();
			if (array_key_exists("ProcessOptions", $arOptions) && array_key_exists("ebayImportAds", $arOptions["ProcessOptions"])) {
				$arArticlesIds = array_slice($arOptions["ProcessOptions"]["ebayImportAds"], $step * $linesPerRun, $linesPerRun);
			} else {
				$arArticlesBrief = $this->ebayGetArticlesBrief($linesPerRun, $step);
				if (($arArticlesBrief !== null) && ($ebayUserToken !== null)) {
					// Iterate all active ads
					foreach ($arArticlesBrief["ActiveList"]["ItemArray"] as $itemIndex => $itemBrief) {
						// Get item details as one-dimensional array
						$arArticlesIds[] = $itemBrief["ItemID"];
					}
				}
			}
			if (!empty($arArticlesIds)) {
				$arArticlesPage = Api_Ebay::getItemListLive($arArticlesIds, $ebayUserToken, "ReturnAll", self::$ebayOutputFilterDefault);
				foreach ($arArticlesPage as $itemIndex => $itemDetailed) {
					$itemDetailed["IMPORT_TASK"] = "UPDATE";
					$itemId = $itemDetailed["ItemID"];
					if (array_key_exists("ProcessOptions", $arOptions) && array_key_exists("ebayImportAdsEdit", $arOptions["ProcessOptions"]) && 
							array_key_exists($itemId, $arOptions["ProcessOptions"]["ebayImportAdsEdit"])) {
						$arArticles[] = array_merge(array_flatten($itemDetailed, true), $arOptions["ProcessOptions"]["ebayImportAdsEdit"][$itemId]);
					} else {
						$arArticles[] = array_flatten($itemDetailed, true);
					}
				}
				if (!empty($arArticles)) {
						return $arArticles;
				}
			}
		} else {
			$step -= $pageLimit;
		}
		if (!array_key_exists("ActiveList", $arOptions) || !$this->getConfigurationOption("updateDisabledArticles")) {
				return $arArticles;
		}
		// Deleted
		$pageLimit = ceil($arOptions["DeletedFromSoldList"] / $linesPerRun);
		if ($step < $pageLimit) {
				$arArticlesBrief = $this->ebayGetArticlesBrief($linesPerRun, $step, "DeletedFromSoldList");
				if (($arArticlesBrief !== null) && ($ebayUserToken !== null)) {
						// Iterate all active ads
						$arArticlesIds = array();
						foreach ($arArticlesBrief["DeletedFromSoldList"]["ItemArray"] as $itemIndex => $itemBrief) {
								// Get item details as one-dimensional array
								$arArticlesIds[] = $itemBrief["ItemID"];
						}
						$arArticlesPage = Api_Ebay::getItemListLive($arArticlesIds, $ebayUserToken, "ReturnAll", array("ItemID"));
						foreach ($arArticlesPage as $itemIndex => $itemDetailed) {
								$itemDetailed["IMPORT_TASK"] = "DELETE";
								$arArticles[] = array_flatten($itemDetailed, true);
						}
						if (!empty($arArticles)) {
								return $arArticles;
						}
				}
		} else {
				$step -= $pageLimit;
		}
		// Sold
		$pageLimit = ceil($arOptions["SoldList"] / $linesPerRun);
		if ($step < $pageLimit) {
				$arArticlesBrief = $this->ebayGetArticlesBrief($linesPerRun, $step, "SoldList");
				if (($arArticlesBrief !== null) && ($ebayUserToken !== null)) {
						// Iterate all active ads
						$arArticlesIds = array();
						foreach ($arArticlesBrief["SoldList"]["ItemArray"] as $itemIndex => $itemBrief) {
								// Get item details as one-dimensional array
								$arArticlesIds[] = $itemBrief["ItemID"];
						}
						$arArticlesPage = Api_Ebay::getItemListLive($arArticlesIds, $ebayUserToken, "ReturnAll", array("ItemID"));
						foreach ($arArticlesPage as $itemIndex => $itemDetailed) {
								$itemDetailed["IMPORT_TASK"] = "DELETE";
								$arArticles[] = array_flatten($itemDetailed, true);
						}
						if (!empty($arArticles)) {
								return $arArticles;
						}
				}
		} else {
				$step -= $pageLimit;
		}
		// Unsold
		$pageLimit = ceil($arOptions["UnsoldList"] / $linesPerRun);
		if ($step < $pageLimit) {
				$arArticlesBrief = $this->ebayGetArticlesBrief($linesPerRun, $step, "UnsoldList");
				if (($arArticlesBrief !== null) && ($ebayUserToken !== null)) {
						// Iterate all active ads
						$arArticlesIds = array();
						foreach ($arArticlesBrief["UnsoldList"]["ItemArray"] as $itemIndex => $itemBrief) {
								// Get item details as one-dimensional array
								$arArticlesIds[] = $itemBrief["ItemID"];
						}
						$arArticlesPage = Api_Ebay::getItemListLive($arArticlesIds, $ebayUserToken, "ReturnAll", array("ItemID"));
						foreach ($arArticlesPage as $itemIndex => $itemDetailed) {
								$itemDetailed["IMPORT_TASK"] = "DELETE";
								$arArticles[] = array_flatten($itemDetailed, true);
						}
						if (!empty($arArticles)) {
								return $arArticles;
						}
				}
		}
		return $arArticles;
	}

	public function getPresetType() {
		return $this->presetType;
	}


	public function getEstimatedNumberOfDatasets($filename = null, $arProcessOptions = array()) {
		$ebayUserToken = $this->getConfigurationOption("ebayUserToken");
		if ($ebayUserToken === null) {
			// Try to obtain user token
			$ebayUserToken = $this->ebayFetchToken();
		}
		if ($ebayUserToken !== null) {
			if (array_key_exists("ebayImportAds", $arProcessOptions)) {
				return count($arProcessOptions["ebayImportAds"]);
			}
			if ($this->getConfigurationOption("updateDisabledArticles")) {
				$arArticlesBrief = Api_Ebay::getMyeBaySelling($ebayUserToken, array(
					"ActiveList" => array(
						"Include" => true, "Pagination" => array("EntriesPerPage" => 1)
					),
					"DeletedFromSoldList" => array(
						"Include" => true, "Pagination" => array("EntriesPerPage" => 1)
					),
					"SoldList" => array(
						"Include" => true, "Pagination" => array("EntriesPerPage" => 1)
					),
					"UnsoldList" => array(
						"Include" => true, "Pagination" => array("EntriesPerPage" => 1)
					)
				));
				$adCount = (int)$arArticlesBrief["ActiveList"]["PaginationResult"]["TotalNumberOfEntries"];
				$adCount += (int)$arArticlesBrief["DeletedFromSoldList"]["PaginationResult"]["TotalNumberOfEntries"];
				$adCount += (int)$arArticlesBrief["SoldList"]["PaginationResult"]["TotalNumberOfEntries"];
				$adCount += (int)$arArticlesBrief["UnsoldList"]["PaginationResult"]["TotalNumberOfEntries"];
				return $adCount;
			} else {
				$arArticlesBrief = Api_Ebay::getMyeBaySelling($ebayUserToken, array(
					"ActiveList" => array(
						"Include" => true, "Pagination" => array("EntriesPerPage" => 1)
					)
				));
				return (int)$arArticlesBrief["ActiveList"]["PaginationResult"]["TotalNumberOfEntries"];
			}
		}
		return 0;
	}

    /**
     * Called once before importing, passed to the read function while importing
     * @return array
     */
    public function getReadOptions($arProcessOptions = array()) {
        $ebayUserToken = $this->getConfigurationOption("ebayUserToken");
        if ($ebayUserToken === null) {
            // Try to obtain user token
            $ebayUserToken = $this->ebayFetchToken();
        }
        if ($ebayUserToken !== null) {
            $arArticlesBrief = Api_Ebay::getMyeBaySelling($ebayUserToken, array(
                "ActiveList" => array(
                    "Include" => true, "Pagination" => array("EntriesPerPage" => 1)
                ),
                "DeletedFromSoldList" => array(
                    "Include" => true, "Pagination" => array("EntriesPerPage" => 1)
                ),
                "SoldList" => array(
                    "Include" => true, "Pagination" => array("EntriesPerPage" => 1)
                ),
                "UnsoldList" => array(
                    "Include" => true, "Pagination" => array("EntriesPerPage" => 1)
                )
            ));
            $arResult = array(
                "ActiveList"            => (int)$arArticlesBrief["ActiveList"]["PaginationResult"]["TotalNumberOfEntries"],
                "DeletedFromSoldList"   => (int)$arArticlesBrief["DeletedFromSoldList"]["PaginationResult"]["TotalNumberOfEntries"],
                "SoldList"              => (int)$arArticlesBrief["SoldList"]["PaginationResult"]["TotalNumberOfEntries"],
                "UnsoldList"            => (int)$arArticlesBrief["UnsoldList"]["PaginationResult"]["TotalNumberOfEntries"], 
				"ProcessOptions" 		=> $arProcessOptions
            );
			if (array_key_exists("ebayImportAds", $arProcessOptions)) {
				$arResult["ActiveList"] = count($arProcessOptions["ebayImportAds"]);
			}
			return $arResult;
        }
        return array("ActiveList" => 0, "SoldList" => 0, "UnsoldList" => 0, "ProcessOptions" => $arProcessOptions);
    }

    /**
     * Validates the presets settings to ensure it is possible to perform an import.
     * @param Ad_Import_PresetEditor_FormResult     $formResult
     */
    public function validate(Ad_Import_PresetEditor_FormResult $formResult) {
        $ebayUserToken = $this->getConfigurationOption("ebayUserToken");
        if ($ebayUserToken === null) {
            $formResult->setFailed();
            $formResult->addError(Translation::readTranslation('marketplace', 'import.preset.editor.type.eBay.error.no.account', null, array(), 'Es wurde noch kein eBay-Account zugeordnet!'));
        }
    }
}