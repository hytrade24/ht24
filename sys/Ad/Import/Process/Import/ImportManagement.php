<?php

require_once $ab_path."sys/packet_management.php";
require_once $ab_path."sys/lib.ads.php";
require_once $ab_path."sys/lib.payment.adapter.user.php";



class Ad_Import_Process_Import_ImportManagement {

	/** @var  Ad_Import_Process_Process */
	protected $importProcess;

	/** @var  Ad_Import_Preset_AbstractPreset */
	protected $preset;

	protected $autoIncrementPointer = null;
	protected $buffer = array();

	protected $statsNewArticles = 0;
	protected $statsUpdatedArticles = 0;
	protected $statsDeletedArticles = 0;
	protected $statsPausedArticles = 0;
	protected $statsStartedArticles = 0;
	
	protected $statsNewProducts = 0;

	protected $preloadUser;
	protected $preloadUserPaymentAdapter;
	protected $preloadTableStructure = array();
	protected $preloadPackets = array();
	protected $preloadFlatratePacket = null;
	protected $preloadUserAccessCategories;
	protected $preloadContent = array();
	protected $preloadManufacturers = array();
	protected $preloadManufacturersById = array();
	protected $preloadProductsByTable = array();
	protected $preloadImportIdentifiers = array();
	protected $preloadImportIdentifiersQueryResource;
	protected $blockedImportIdentfiers = array();

	/**
	 * @param $importProcess
	 */
	function __construct($importProcess) {
		global $db;

		$this->importProcess = $importProcess;
		$this->preset = $this->importProcess->getPreset();

		$this->tableFields = $this->preset->getTableFieldsByTableDef();
	}


	/**
	 * Precheck before import
	 *
	 * @return bool
	 */
	public function preCheckAll() {
		global $db, $nar_systemsettings;

		$userId = $this->importProcess->getUserId();
		$packets = PacketManagement::getInstance($db);

		// All Ads are free
		if($nar_systemsettings['MARKTPLATZ']['FREE_ADS'] == 1) {
			return true;
		}

		$numberOfAvailableAds = 0;
		$ar_required = array(PacketManagement::getType("ad_once") => 1);
		$ar_required_abo = array(PacketManagement::getType("ad_abo") => 1);

		$ar_packets = array_merge($packets->order_find_collections($userId, $ar_required), $packets->order_find_collections($userId, $ar_required_abo));
		foreach($ar_packets as $key => $packet) {
			if($packet['FREE0'] == -1) {
				// Flatrate Package
				return true;
			}

			$numberOfAvailableAds += (int)$packet['FREE0'];
		}


		// estimate new ads
		$allData = $this->importProcess->getInfrastructure()->countAllArticleData(array('D', 'U'));
		// TODO better estimation

		return true;

	}

	public function import($dataset, $tabledef) {
		// Allow to add further fields by plugin
		$importDataset = new Ad_Import_Process_Import_ImportDataset($dataset, $this->importProcess);
		Api_TraderApiHandler::getInstance($GLOBALS['db'])->triggerEvent(Api_TraderApiEvents::IMPORT_PROCESS_DATASET, $importDataset);
		// Update possible changes
		$dataset = $importDataset->getContent();
		
		switch($dataset['IMPORT_TASK']) {
			case 'D':
			case 'DELETE':
				// delete
				$this->processImportDeleteDataset($dataset, $tabledef);
				break;
			case 'N':
			case 'NEW':
				// new
				$this->processImportNewDataset($dataset, $tabledef);
				break;
			case 'PAUSE':
				$this->processImportPauseDataset($dataset, $tabledef);
				break;
			case 'START':
				$this->processImportStartDataset($dataset, $tabledef);
				break;
			case 'U':
			case 'UPDATE':
			default:
				// update
				$this->processImportUpdateDataset($dataset, $tabledef);
				break;
			case 'P':
			case 'PRODUCT':
			case 'PRODUCT_DB':
				// new
				$this->processImportProductDb($dataset, $tabledef);
				break;
		}

	}

	/**
	 * Update Import Dataset
	 *
	 * @param $dataset
	 * @param $tabledef
	 */
	protected function processImportUpdateDataset($dataset, $tabledef) {
		global $lang_list;

		if(!$this->checkIfImportIdentifierExists($dataset['IMPORT_IDENTIFIER'], $tabledef)) {

			return $this->processImportNewDataset($dataset, $tabledef);
		}
		
		$originArticle = $this->getImportIdentfierDataset($dataset['IMPORT_IDENTIFIER'], $tabledef);
		$articleId = $originArticle['ID_'.strtoupper($tabledef)];

		$arArticle = $originArticle;
		$arArticle = array_merge($arArticle, $dataset);

		$arArticle = $this->transformArticleDataForManufacturer($arArticle);

		$arArticle['ID_AD_MASTER'] = $articleId;
		$arArticle['ID_'.strtoupper($tabledef)] = $articleId;
		$arArticle['FK_USER'] = $this->getImportTargetUser();
		$arArticle['IMPORT_SOURCE'] = $this->importProcess->getImportSource();

		if (empty($arArticle['ZIP'])) {
			$arArticle['ZIP'] = $this->preloadUser['PLZ'];
		}
		if (empty($arArticle['CITY'])) {
			$arArticle['CITY'] = $this->preloadUser['ORT'];
		}
		if ((int)$arArticle['FK_COUNTRY'] <= 0) {
			$arArticle['FK_COUNTRY'] = $this->preloadUser['FK_COUNTRY'];
		}

		$arArticle["STATUS"] = $originArticle["STATUS"];
		$arArticle['CRON_DONE'] = $originArticle["CRON_DONE"];
		$arArticle['CRON_STAT'] = $originArticle["CRON_STAT"];
		$arArticle["CONFIRMED"] = $originArticle["CONFIRMED"];
		$arArticle["STAMP_START"] = $originArticle["STAMP_START"];
		$arArticle["STAMP_END"] = $originArticle["STAMP_END"];
		$arArticle["STAMP_DEACTIVATE"] = $originArticle["STAMP_DEACTIVATE"];
		$arArticle["B_TOP"] = $originArticle["B_TOP"];

		if (!array_key_exists("VERSANDOPTIONEN", $dataset) || ($dataset["VERSANDOPTIONEN"] == "")) {
			if (array_key_exists("VERSANDKOSTEN", $dataset) && ($dataset["VERSANDKOSTEN"] > 0)) {
				$arArticle["VERSANDOPTIONEN"] = 3;
			}
		}

		// ad search
		$this->buffer['DELETE_MULTI']['ad_search']['FK_AD'][] = $articleId;
		$this->createAdSearchImportBuffer($tabledef, $arArticle, $articleId);

		$arArticleMaster = array_intersect_key($arArticle, array_flip($this->preloadTableStructure['ad_master']));
		$arArticleTable = array_intersect_key($arArticle, array_flip($this->preloadTableStructure[$tabledef]));

		// videos
		// docs
		// images

		$this->buffer['UPDATE']['ad_master'][$articleId] = $arArticleMaster;
		$this->buffer['UPDATE'][$tabledef][$articleId] = $arArticleTable;

		$this->statsUpdatedArticles++;
		$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_SUCCESS);


	}

	protected function processImportNewDataset($dataset, $tabledef) {
		global $nar_systemsettings, $db, $lang_list;

		if($this->checkIfImportIdentifierExists($dataset['IMPORT_IDENTIFIER'])) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.importidentifier.exists', null, array('IMPORT_IDENTIFIER' => "'".$dataset['IMPORT_IDENTIFIER']."'"), 'Es existiert bereits ein Datensatz mit dem Identifikator {IMPORT_IDENTIFIER}'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));

			return false;
		}

		if(isset($dataset['IMPORT_IDENTIFIER']) && !empty($dataset['IMPORT_IDENTIFIER']) && in_array($dataset['IMPORT_IDENTIFIER'], $this->blockedImportIdentfiers)) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.importidentifier.exists.blocked', null, array('IMPORT_IDENTIFIER' => "'".$dataset['IMPORT_IDENTIFIER']."'"), 'Es existiert bereits ein Datensatz mit dem Identifikator {IMPORT_IDENTIFIER} im Datenimport'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));

			return false;
		}

		$articleId = $this->autoIncrementPointer;
		$this->autoIncrementPointer++;

		$packet = $this->suggestPacketForImport();
		if($packet === false) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.nopacket.', null, array(), 'Es wurde kein Paket mit ausreichendem Anzeigenvolumen gefunden'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));

			return false;
		}

		if($dataset['FK_KAT'] == 0 || !array_key_exists($dataset['FK_KAT'], $this->preloadUserAccessCategories)) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.nocategoryaccess.', null, array('KAT_ID' => "'".$dataset['FK_KAT']."'"), 'Sie haben keinen Zugriff auf die Kategorie {KAT_ID}'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));

			return false;
		}
		
		$arArticle = AdManagment::createArticleAsArray($dataset['FK_KAT'], null, $this->preloadUser);
		$arArticle = array_merge($arArticle, $dataset);

		$arArticle = $this->transformArticleDataForManufacturer($arArticle);

		$arArticle['ID_AD_MASTER'] = $articleId;
		$arArticle['ID_'.strtoupper($tabledef)] = $articleId;
		$arArticle['AD_TABLE'] = $tabledef;
		$arArticle['FK_USER'] = $this->getImportTargetUser();
		$arArticle['FK_PACKET_ORDER'] = $packet;
		$arArticle['IMPORT_SOURCE'] = $this->importProcess->getImportSource();

		if (empty($arArticle['ZIP'])) {
			$arArticle['ZIP'] = $this->preloadUser['PLZ'];
		}
		if (empty($arArticle['CITY'])) {
			$arArticle['CITY'] = $this->preloadUser['ORT'];
		}
		if ((int)$arArticle['FK_COUNTRY'] <= 0) {
			$arArticle['FK_COUNTRY'] = $this->preloadUser['FK_COUNTRY'];
		}

		$arArticle["STATUS"] = 0;
		$arArticle['CRON_DONE'] = 1;
		$arArticle['CRON_STAT'] = NULL;
		// Moderate ads?
		if ($nar_systemsettings["MARKTPLATZ"]["MODERATE_ADS"]) {
			$userIsAutoConfirmed = $this->preloadUser['AUTOCONFIRM_ADS'];

			if ($userIsAutoConfirmed) {
				$arArticle["CONFIRMED"] = 1;
			} else {
				$arArticle["CONFIRMED"] = 0;
				$arArticle['CRON_DONE'] = 1;
			}
		} else {
			$arArticle["CONFIRMED"] = 1;
		}

		if (!array_key_exists("VERSANDOPTIONEN", $arArticle) || ($arArticle["VERSANDOPTIONEN"] == "")) {
			if (array_key_exists("VERSANDKOSTEN", $arArticle) && ($arArticle["VERSANDKOSTEN"] > 0)) {
				$arArticle["VERSANDOPTIONEN"] = 3;
			}
		}
		if (!$arArticle["ALLOW_COMMENTS"]) {
			// Read default setting
			$userAllowComments = ($this->preloadUser['ALLOW_COMMENTS']&1 > 0)?1:0;
			$arArticle["ALLOW_COMMENTS"] = $userAllowComments;
		}
		$enable = ($arArticle['CONFIRMED'] == 1 ? true : false);

		// ad search
		$this->createAdSearchImportBuffer($tabledef, $arArticle, $articleId);


		// Trigger plugin event
		$paramAdCreate = new Api_Entities_EventParamContainer(array(
			"data"		=> $arArticle,
			"enable"	=> $enable,
			"import"	=> true
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE, $paramAdCreate);
		if ($paramAdCreate->isDirty()) {
			$arArticle = $paramAdCreate->getParam("data");
			$enable = $paramAdCreate->getParam("enable");
		}
		
		
		if ($enable) {
			// enable it
			$runtimeDays = (int)$this->preloadContent['LAUFZEIT'][$arArticle['LU_LAUFZEIT']];
			if($runtimeDays <= 0) {
                $logText = Translation::readTranslation('marketplace', 'import.process.import.noruntime.', null, array('LU_LAUFZEIT' => "'".$arArticle['LU_LAUFZEIT']."'"), 'Die gewählte Laufzeit {LU_LAUFZEIT} ist nicht verfügbar');
                $this->importProcess->log($logText, Ad_Import_Process_Process::LOG_ERROR);
                $this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));
				return false;
			}
			$tmpDate = new DateTime();
			$arArticle["STAMP_START"] = $tmpDate->format("Y-m-d H:i:s");
			$tmpDate->add(new DateInterval("P".$runtimeDays."D"));
			$arArticle["STAMP_END"] =  $tmpDate->format("Y-m-d H:i:s");
			$arArticle["STATUS"] =  1;
		}

		
		$arArticleMaster = array_intersect_key($arArticle, array_flip($this->preloadTableStructure['ad_master']));
		$arArticleTable = array_intersect_key($arArticle, array_flip($this->preloadTableStructure[$tabledef]));

		$this->preloadProducts($tabledef);
		
		if (!array_key_exists($arArticle["PRODUKTNAME"], $this->preloadProductsByTable[$tabledef])) {
            // Generate full product name
            $manufacturerName = null;
            $productNameFull = $arArticle["PRODUKTNAME"];
            if ($arArticle["FK_MAN"] > 0) {
                $manufacturerName = $this->preloadManufacturersById[ $arArticle["FK_MAN"] ];
                $productNameFull = $manufacturerName." ".$productNameFull;
            }
            
            $tableHdb = "hdb_table_".$tabledef;
            $newProductId = (!empty($this->preloadProductsByTable[$tabledef]) ? max($this->preloadProductsByTable[$tabledef]) + 1 : 1);  // TODO: Besser lösen!
            $arProduct = array();
            foreach ($this->preloadTableStructure[$tableHdb] as $hdbIndex => $hdbField) {
                if (array_key_exists($hdbField, $arArticle)) {
                    $arProduct[$hdbField] = $arArticle[$hdbField];
                }
            }
            $arProduct["FULL_PRODUKTNAME"] = $productNameFull;
		    $this->buffer['INSERT'][$tableHdb][$newProductId] = $arProduct;
            $this->preloadProductsByTable[$tabledef][ $arArticle["PRODUKTNAME"] ] = $newProductId;
            $arArticleMaster["FK_PRODUCT"] = $newProductId;
            $arArticleTable["FK_PRODUCT"] = $newProductId;
        } else {
		    $productId = $this->preloadProductsByTable[$tabledef][ $arArticle["PRODUKTNAME"] ];
            $arArticleMaster["FK_PRODUCT"] = $productId;
            $arArticleTable["FK_PRODUCT"] = $productId;
        }
        
		if($this->preloadUserPaymentAdapter != null && is_array($this->preloadUserPaymentAdapter)) {
			foreach($this->preloadUserPaymentAdapter as $key => $paymentAdapter) {
				$this->buffer['INSERT']['ad2payment_adapter'][] = array('FK_AD' => $articleId, 'FK_PAYMENT_ADAPTER' => $paymentAdapter);
			}
		}

		// videos
		// docs
		// images

		$this->buffer['INSERT']['ad_master'][$articleId] = $arArticleMaster;
		$this->buffer['INSERT'][$tabledef][$articleId] = $arArticleTable;


		// Packet count
		if($packet > 0 && isset($this->preloadPackets[$packet])) {
			$this->preloadPackets[$packet]['ESTIMATED0']++;

			if(isset($this->preloadPackets[$packet]['AD_SUBPACKAGE_ID_PACKET_ORDER']) && $this->preloadPackets[$packet]['AD_SUBPACKAGE_ID_PACKET_ORDER'] > 0) {

				$this->buffer['INSERT']['packet_order_usage'][] = array(
					'ID_PACKET_ORDER' => $this->preloadPackets[$packet]['AD_SUBPACKAGE_ID_PACKET_ORDER'],
					'FK' => $articleId
				);
			}
		}

		// block import identifier
		if(isset($dataset['IMPORT_IDENTIFIER']) && !empty($dataset['IMPORT_IDENTIFIER'])) {
			$this->blockedImportIdentfiers[] = $dataset['IMPORT_IDENTIFIER'];
		}


		$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_SUCCESS);
		$this->statsNewArticles++;

	}

	protected function processImportProductDb($dataset, $tabledef) {
		if($dataset['FK_KAT'] == 0 || !array_key_exists($dataset['FK_KAT'], $this->preloadUserAccessCategories)) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.nocategoryaccess.', null, array('KAT_ID' => "'".$dataset['FK_KAT']."'"), 'Sie haben keinen Zugriff auf die Kategorie {KAT_ID}'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));

			return false;
		}
		
        $tableHdb = "hdb_table_".$tabledef;
		
		$arArticle = AdManagment::createArticleAsArray($dataset['FK_KAT'], null, $this->preloadUser);
		$arArticle = array_merge($arArticle, $dataset);

		$arArticle = $this->transformArticleDataForManufacturer($arArticle);

		$arArticle['AD_TABLE'] = $tabledef;
		$arArticle['FK_USER'] = $this->getImportTargetUser();
		$arArticle['IMPORT_SOURCE'] = $this->importProcess->getImportSource();
		$arArticle["STATUS"] = 0;
		$arArticle['CRON_DONE'] = 1;
		$arArticle['CRON_STAT'] = NULL;
		
		$arArticleMaster = array_intersect_key($arArticle, array_flip($this->preloadTableStructure['ad_master']));
		$arArticleTable = array_intersect_key($arArticle, array_flip($this->preloadTableStructure[$tabledef]));
		
		$this->preloadProducts($tabledef);
		
		if (!array_key_exists($arArticle["PRODUKTNAME"], $this->preloadProductsByTable[$tabledef])) {
            // Generate full product name
            $manufacturerName = null;
            $productNameFull = $arArticle["PRODUKTNAME"];
            if ($arArticle["FK_MAN"] > 0) {
                $manufacturerName = $this->preloadManufacturersById[ $arArticle["FK_MAN"] ];
                $productNameFull = $manufacturerName." ".$productNameFull;
            }
            
            $newProductId = (!empty($this->preloadProductsByTable[$tabledef]) ? max($this->preloadProductsByTable[$tabledef]) + 1 : 1);  // TODO: Besser lösen!
            $arProduct = array();
            foreach ($this->preloadTableStructure[$tableHdb] as $hdbIndex => $hdbField) {
                if (array_key_exists($hdbField, $arArticle)) {
                    $arProduct[$hdbField] = $arArticle[$hdbField];
                }
            }
            $arProduct["FULL_PRODUKTNAME"] = $productNameFull;
		    $this->buffer['INSERT']['hdb_table_'.$tabledef][$newProductId] = $arProduct;
            $this->preloadProductsByTable[$tabledef][ $arArticle["PRODUKTNAME"] ] = $newProductId;
		    $this->statsNewProducts++;
        }
		
		#die(var_dump($arArticle, $arArticleMaster, $arArticleTable, $this->buffer ));

		$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_SUCCESS);

	}


	/**
	 * Deletes a dataset in article tables
	 *
	 * @param $dataset
	 * @param $tabledef
	 *
	 * @return bool
	 */
	protected function processImportDeleteDataset($dataset, $tabledef) {

		if(!$this->checkIfImportIdentifierExists($dataset['IMPORT_IDENTIFIER'], $tabledef)) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.importidentifier.not.exists', null, array('IMPORT_IDENTIFIER' => '"'.$dataset['IMPORT_IDENTIFIER'].'"'), 'Es existiert kein Datensatz mit dem Identifikator {IMPORT_IDENTIFIER}'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));
			return false;
		}

		$originArticle = $this->getImportIdentfierDataset($dataset['IMPORT_IDENTIFIER'], $tabledef);
		$articleId = $originArticle['ID_'.strtoupper($tabledef)];

		$updateArticle = $originArticle;
		$updateArticle['DELETED'] = 1;
		$updateArticle['STATUS'] = 0;
		$updateArticle['STAMP_DEACTIVATE'] = date("Y-m-d H:i:s");


		$arArticleMaster = array_intersect_key($updateArticle, array_flip($this->preloadTableStructure['ad_master']));
		$arArticleTable = array_intersect_key($updateArticle, array_flip($this->preloadTableStructure[$tabledef]));


		$this->buffer['UPDATE']['ad_master'][$articleId] = $arArticleMaster;
		$this->buffer['UPDATE'][$tabledef][$articleId] = $arArticleTable;
        $this->buffer['DELETE_MULTI']['packet_order_usage']['FK'][] = $articleId;
        $this->buffer['DELETE_MULTI']['packet_order_usage']['FK'][] = $articleId;
        $this->buffer['DELETE_MULTI']['ad_upload']['FK_AD'][] = $articleId;
        $this->buffer['DELETE_MULTI']['ad_images']['FK_AD'][] = $articleId;
        $this->buffer['DELETE_MULTI']['ad_video']['FK_AD'][] = $articleId;
        $this->buffer['DELETE_MULTI']['packet_order_usage']['FK'][] = $articleId;
        $this->buffer['DELETE_MULTI']['ad2payment_adapter']['FK_AD'][] = $articleId;
        $this->buffer['DELETE_MULTI']['ad_search']['FK_AD'][] = $articleId;

		$this->statsDeletedArticles++;
		$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_SUCCESS);
	}

	/**
	 * Pauses a dataset in article tables
	 *
	 * @param $dataset
	 * @param $tabledef
	 *
	 * @return bool
	 */
	protected function processImportPauseDataset($dataset, $tabledef) {

		if(!$this->checkIfImportIdentifierExists($dataset['IMPORT_IDENTIFIER'])) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.importidentifier.exists', null, array('IMPORT_IDENTIFIER' => $dataset['IMPORT_IDENTIFIER']), 'Es existiert bereits ein Datensatz mit dem Identifikator {IMPORT_IDENTIFIER}'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));

			return false;
		}

		$originArticle = $this->getImportIdentfierDataset($dataset['IMPORT_IDENTIFIER'], $tabledef);
		$articleId = $originArticle['ID_'.strtoupper($tabledef)];

		$updateArticle = $originArticle;
		$updateArticle['STATUS'] = 0;
		$updateArticle['STAMP_DEACTIVATE'] = date("Y-m-d H:i:s");

		$arArticleMaster = array_intersect_key($updateArticle, array_flip($this->preloadTableStructure['ad_master']));
		$arArticleTable = array_intersect_key($updateArticle, array_flip($this->preloadTableStructure[$tabledef]));

		$this->buffer['UPDATE']['ad_master'][$articleId] = $arArticleMaster;
		$this->buffer['UPDATE'][$tabledef][$articleId] = $arArticleTable;
        $this->buffer['DELETE_MULTI']['packet_order_usage']['FK'][] = $articleId;
        $this->buffer['DELETE_MULTI']['ad_search']['FK_AD'][] = $articleId;

		$this->statsPausedArticles++;
		$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_SUCCESS);
	}


	/**
	 * Pauses a dataset in article tables
	 *
	 * @param $dataset
	 * @param $tabledef
	 *
	 * @return bool
	 */
	protected function processImportStartDataset($dataset, $tabledef) {

		if(!$this->checkIfImportIdentifierExists($dataset['IMPORT_IDENTIFIER'])) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.importidentifier.exists', null, array('IMPORT_IDENTIFIER' => "'".$dataset['IMPORT_IDENTIFIER']."'"), 'Es existiert bereits ein Datensatz mit dem Identifikator {IMPORT_IDENTIFIER}'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));

			return false;
		}

		$originArticle = $this->getImportIdentfierDataset($dataset['IMPORT_IDENTIFIER'], $tabledef);
		$articleId = $originArticle['ID_'.strtoupper($tabledef)];

		$dataset = $originArticle;

		if(($dataset["STATUS"]& 1) == 1) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.start.alreadyonline.', null, array(), 'Der Artikel ist bereits online'), Ad_Import_Process_Process::LOG_INFO);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));

			return false;
		}
		if($dataset["CONFIRMED"] != 1) {
			return false;
		}

		$packet = $this->suggestPacketForImport();
		if($packet === false) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.nopacket.', null, array(), 'Es wurde kein Paket mit ausreichendem Anzeigenvolumen gefunden'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));
			return false;
		}

		if($dataset['FK_KAT'] == 0 || !array_key_exists($dataset['FK_KAT'], $this->preloadUserAccessCategories)) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.nocategoryaccess.', null, array('KAT_ID' => "'".$dataset['FK_KAT']."'"), 'Sie haben keinen Zugriff auf die Kategorie {KAT_ID}'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));

			return false;
		}

		// enable it
		$runtimeDays = (int)$this->preloadContent['LAUFZEIT'][$dataset['LU_LAUFZEIT']];
		if($runtimeDays <= 0) {
			$logText = $this->importProcess->log(Translation::readTranslation('marketplace', 'import.process.import.noruntime.', null, array('LU_LAUFZEIT' => "'".$dataset['LU_LAUFZEIT']."'"), 'Die gewählte Laufzeit {LU_LAUFZEIT} ist nicht verfügbar'), Ad_Import_Process_Process::LOG_ERROR);
			$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_ERROR, array($logText));
			return false;
		}

		$tmpDate = new DateTime();
		$dataset["STAMP_START"] = $tmpDate->format("Y-m-d H:i:s");
		$tmpDate->add(new DateInterval("P".$runtimeDays."D"));
		$dataset["STAMP_END"] =  $tmpDate->format("Y-m-d H:i:s");
		$dataset['STAMP_DEACTIVATE'] = NULL;
		$dataset["STATUS"] =  1;

		$this->createAdSearchImportBuffer($tabledef, $dataset, $articleId);

		$arArticleMaster = array_intersect_key($dataset, array_flip($this->preloadTableStructure['ad_master']));
		$arArticleTable = array_intersect_key($dataset, array_flip($this->preloadTableStructure[$tabledef]));

		$this->buffer['UPDATE']['ad_master'][$articleId] = $arArticleMaster;
		$this->buffer['UPDATE'][$tabledef][$articleId] = $arArticleTable;


		$this->statsStartedArticles++;
		$this->importProcess->getInfrastructure()->markBaseDataset($dataset['ID'], Ad_Import_Process_Process::DATASET_MARKER_SUCCESS);
	}




	protected function checkIfImportIdentifierExists($importIdentifier, $tabledef = 'artikel_master') {
		if($importIdentifier == null) {
			return false;
		}

		if (array_key_exists($tabledef, $this->preloadImportIdentifiers) 
            && array_key_exists($importIdentifier, $this->preloadImportIdentifiers[$tabledef])) {
		    return true;
        } else {
		    return false;
        }
	}

	/**
	 * Sucht ein Passendes Anzeigenpaket für den Import einer Anzeige. Bevorzugt Flatrate Pakete
	 *
	 * @return bool
	 */
	protected function suggestPacketForImport() {
		global $nar_systemsettings;

		if($nar_systemsettings['MARKTPLATZ']['FREE_ADS'] == 1) {
			return 0;
		}

		if($this->preloadFlatratePacket != null) {
			return $this->preloadFlatratePacket['ID_PACKET_ORDER'];
		}

		foreach($this->preloadPackets as $key => $packet) {
			if($packet['FREE0'] - ((int)$packet['ESTIMATED0']) > 0) {
				return $packet['ID_PACKET_ORDER'];
			}
		}

		return false;
	}

	/**
	 * Preloads User data
	 */
	protected function preloadUser() {
		global $db, $nar_systemsettings;

		$packets = PacketManagement::getInstance($db);

		$userId = (int)$this->getImportTargetUser();
		// Preload Userdata
		$this->preloadUser = $db->fetch1("
			SELECT
				u.*, uc.*, us.*
			FROM user u
			JOIN usercontent uc ON uc.FK_USER = u.ID_USER
			LEFT JOIN usersettings us ON us.FK_USER = u.ID_USER
			WHERE u.ID_USER = '".$userId."'");

		// Preload Payment Adapter
		$paymentAdapterUserManagement = PaymentAdapterUserManagement::getInstance($db);
		$this->preloadUserPaymentAdapter = $paymentAdapterUserManagement->fetchAllAutoCheckedPaymentAdapterByUser($userId);

		$this->preloadUserAccessCategories = $db->fetch_nar("SELECT FK_KAT, FK_KAT FROM `role2kat`	WHERE FK_ROLE IN (SELECT FK_ROLE FROM `role2user` WHERE FK_USER='".$userId."') AND  ALLOW_NEW_AD=1");

		// Preload Packets
		if(!$nar_systemsettings['MARKTPLATZ']['FREE_ADS'] == 1) {
			$ar_required = array(PacketManagement::getType("ad_once") => 1);
			$ar_required_abo = array(PacketManagement::getType("ad_abo") => 1);
			$ar_ad_packet_keys = array(PacketManagement::getType("ad_once"), PacketManagement::getType("ad_abo"));

			$ar_packets = array_merge($packets->order_find_collections($userId, $ar_required), $packets->order_find_collections($userId, $ar_required_abo));
			foreach ($ar_packets as $key => $packet) {
				if ($packet['FREE0'] == -1 || $packet['MAX0'] == -1) {
					// Flatrate Package
					$this->preloadFlatratePacket = $packet;
				}

				$packet['AD_SUBPACKAGE_ID_PACKET_ORDER'] = $db->fetch_atom("SELECT ID_PACKET_ORDER FROM packet_order WHERE FK_COLLECTION = '".$packet['ID_PACKET_ORDER']."' AND FK_PACKET IN(".implode(',', $ar_ad_packet_keys).") LIMIT 1");

				$this->preloadPackets[$packet['ID_PACKET_ORDER']] = $packet;
			}


		}
	}

	/**
	 * Preload Table Structure
	 */
	protected function preloadTableStructure() {
		global $db;

		$tables = array_keys($this->tableFields);
		array_unshift($tables, 'ad_master');


		foreach($tables as $key => $table) {
			$structure = $db->fetch_table("SHOW COLUMNS FROM $table");
			foreach($structure as $skey => $value) {
				$this->preloadTableStructure[$table][] = $value['Field'];
			}
		}
	}

	protected function preloadContent() {
		global $db;

		$this->preloadContent['LAUFZEIT'] = $db->fetch_nar("SELECT ID_LOOKUP, VALUE FROM lookup WHERE art='LAUFZEIT'");

	}

	/**
	 * Läd die Import Identfiers des Users vor, sodass diese später direkt zugeriffen werden können
	 *
	 */
	public function preloadImportIdentifiers(&$identifiersEscaped = null, $table = null) {
		global $db;
		
		if (($identifiersEscaped === null) || ($table === null)) {
            foreach(array_keys($this->tableFields) as $key => $table) {
                $queryresult = $db->querynow("
                    SELECT ID_AD_MASTER, IMPORT_IDENTIFIER
                    FROM ad_master 
                    WHERE FK_USER = '".$this->getImportTargetUser()."' AND DELETED=0
                        AND IMPORT_IDENTIFIER IS NOT NULL AND IMPORT_IDENTIFIER <> '' ");
                if($queryresult['rsrc']) {
                    while($data = mysql_fetch_assoc($queryresult['rsrc'])) {
                        $this->preloadImportIdentifiers[$table][$data['IMPORT_IDENTIFIER']] = $data["ID_AD_MASTER"];
                    }
    
                }
            }
        } else {
            $queryresult = $db->querynow("
                SELECT ID_AD_MASTER, IMPORT_IDENTIFIER
                FROM ad_master 
                WHERE FK_USER = '".$this->getImportTargetUser()."' AND DELETED=0
                    AND IMPORT_IDENTIFIER IS NOT NULL AND IMPORT_IDENTIFIER IN (".implode(",", $identifiersEscaped).")");
            if($queryresult['rsrc']) {
                if (array_key_exists($table, $this->preloadImportIdentifiers)) {
                    $this->preloadImportIdentifiers[$table] = array();
                }
                while($data = mysql_fetch_assoc($queryresult['rsrc'])) {
                    $this->preloadImportIdentifiers[$table][$data['IMPORT_IDENTIFIER']] = $data["ID_AD_MASTER"];
                }

            }
        }
	}


	/**
	 * Läd die Import Identfiers des Users vor, sodass diese später direkt zugeriffen werden können
	 *
	 */
	protected function preloadManufacturers() {
		global $db, $nar_systemsettings;

		if($nar_systemsettings["MARKTPLATZ"]["USE_PRODUCT_DB"] ) {
			$this->preloadManufacturers = $db->fetch_nar("SELECT NAME, ID_MAN FROM manufacturers  ");
			$this->preloadManufacturersById = array_flip($this->preloadManufacturers);
		}
	}


	/**
	 * Läd die Import Identfiers des Users vor, sodass diese später direkt zugeriffen werden können
	 *
	 */
	protected function preloadProducts($table) {
		global $db, $nar_systemsettings;

		if($nar_systemsettings["MARKTPLATZ"]["USE_PRODUCT_DB"] ) {
		    $tableHdb = "hdb_table_".$table;
		    if (!array_key_exists($table, $this->preloadProductsByTable)) {
			    $this->preloadProductsByTable[$table] = $db->fetch_nar("SELECT PRODUKTNAME, ID_".mysql_real_escape_string(strtoupper($tableHdb))." FROM `".mysql_real_escape_string($tableHdb)."`");
            }
		    if (!array_key_exists($tableHdb, $this->preloadProductsByTable)) {
                $structure = $db->fetch_table("SHOW COLUMNS FROM `".mysql_real_escape_string($tableHdb)."`");
                foreach ($structure as $skey => $value) {
                    $this->preloadTableStructure[$tableHdb][] = $value['Field'];
                }
            }
		}
	}

	/**
	 * Gets an Dataset by Import Identifier. Need to be preloaded first with preloadImportIdentifiers() call
	 * The dataset includes all ad_master and artikel_... data
	 *
	 * @param $importIdentifier
	 * @param $tabledef
	 *
	 * @return array|null
	 */
	protected function getImportIdentfierDataset($importIdentifier, $tabledef) {
		$preloadedKey = $this->preloadImportIdentifiers[$tabledef][$importIdentifier];

		if ($preloadedKey > 0) {
            return $GLOBALS["db"]->fetch1("
              SELECT t.*, a.* 
              FROM `".$tabledef."` t 
              JOIN ad_master a ON a.ID_AD_MASTER = t.ID_".strtoupper($tabledef)." 
              WHERE t.FK_USER = '".$this->getImportTargetUser()."' AND a.ID_AD_MASTER=".(int)$preloadedKey);
        }

		return null;
	}
	
	protected function getImportTargetUser() {
		// Get target user
		$userId = (int)$this->importProcess->getConfigurationOption("targetUser");
		if ($userId <= 0) {
			$userId = $this->importProcess->getUserId();
		}
		return $userId;
	}

	/**
	 * @return Ad_Import_Process_Process
	 */
	public function getImportProcess() {
		return $this->importProcess;
	}

	/**
	 * Erzeugt den Text für die Suchdatenbank ad_search anhand der Artikeleigenschaften
	 *
	 * @param        $article
	 * @param string $langKey
	 *
	 * @return string
	 */
	protected function generateSearchDatabaseText($article, $langval = '') {
		global $ab_path;
		require_once $ab_path."sys/lib.ads.php";
		return AdManagment::getAdSearchTextRaw($article, $langval);
	}


	/**
	 * @param $tabledef
	 * @param $arArticle
	 * @param $articleId
	 */
	protected function createAdSearchImportBuffer($tabledef, $arArticle, $articleId) {
		global $lang_list;

		foreach ($lang_list as $langKey => $langData) {
			$searchText = $this->generateSearchDatabaseText($arArticle, $langData["BITVAL"]);
			$this->buffer['INSERT']['ad_search'][] = array(
					'FK_AD' => $articleId, 'FK_USER' => $this->getImportTargetUser(), 'LANG' => $langKey,
					'AD_TABLE' => $tabledef, 'STEXT' => $searchText
			);
		}
	}


	/**
	 * Bereitet Import vor
	 * Läd relevante Daten vor und setzt Sicherheitshalber den Auto Incr. Wert hoch
	 */
	public function prepareImport() {
		global $db;

		$readerLimit = (int)$this->importProcess->getConfigurationOption('limitPerRun');
		if ($readerLimit > 5000) {
		    $readerLimit = 5000;
        }
		$x =  $this->importProcess->getInfrastructure()->countAllArticleData(array('D'));
		$estimatedArticles = min($x, $readerLimit);


		$tmp = $db->fetch1("SHOW TABLE STATUS  LIKE 'ad_master'");
		$this->autoIncrementPointer = (int)$tmp['Auto_increment'];


		if($this->importProcess->getConfigurationOption('testMode') == false) {
			// sets autoincrement to end
			$db->query("ALTER TABLE ad_master AUTO_INCREMENT = " . ($this->autoIncrementPointer + $estimatedArticles));
		}

		$this->preloadUser();
		$this->preloadTableStructure();
		$this->preloadContent();
		$this->preloadManufacturers();



		$this->buffer = array();

	}

	/**
	 * Finishes Import after datasets are prepared
	 * This method executes database queries for insert, update or delete datasets
	 *
	 */
	public function finishImport() {
		global $db;

		$queries = array();
		$affectedTables = array();
		$bufferTasks = array('DELETE_MULTI', 'DELETE', 'UPDATE', 'INSERT');
		$insertLimitBytes = $db->fetch_atom("SELECT @@global.max_allowed_packet");

		if($this->importProcess->getConfigurationOption('testMode') == false) {
			$arAdsImported = array("INSERT" => array(), "UPDATE" => array(), "DELETE" => array());
			// RUN QUERY BUFFER
			foreach ($bufferTasks as $task) {
				if (isset($this->buffer[$task]) && is_array($this->buffer[$task])) {

					foreach ($this->buffer[$task] as $table => $dataset) {
						if ($task == 'INSERT' || $task == 'UPDATE') {
						    if (!empty($dataset)) {
                                $insertFields = array();
                                $insertRows = array();
                                $insertBytes = 8192;
							    foreach ($dataset as $key => $fields) {
                                    if (($table == "ad_master") && ($task == "UPDATE")) {
                                        $arAdsImported[$task][] = $fields["ID_AD_MASTER"];
                                    }
                                    $insertRow = array();
                                    foreach ($fields as $fkey => $value) {
                                        if ($value === NULL) {
                                            $insertRow[] = "NULL";
                                        } else {
                                            $insertRow[] = "'".mysql_real_escape_string($value)."'";
                                        }
                                    }
                                    if (empty($insertFields)) {
                                        foreach ($fields as $fieldName => $fieldValue) {
                                            $insertFields[] = "`".$fieldName."`";
                                        }
                                    }
                                    $insertRow = "(".implode(", ", $insertRow).")";
                                    $insertBytes += (int)mb_strlen($insertRow, "8bit") + 16;
                                    if ($insertBytes >= $insertLimitBytes) {
                                        switch ($task) {
                                            case 'INSERT':
                                                $queries[] = "INSERT INTO `" . $table . "`\n".
                                                    " (".implode(", ", $insertFields).")\n".
                                                    "VALUES\n".
                                                    " ".implode(",\n ", $insertRows).";";    
                                                break;
                                            case 'UPDATE':
                                                $queries[] = "REPLACE INTO `" . $table . "`\n".
                                                    " (".implode(", ", $insertFields).")\n".
                                                    "VALUES\n".
                                                    " ".implode(",\n ", $insertRows).";";
                                                break;
                                        }
                                        $insertBytes = 8192 + (int)mb_strlen($insertRow, "8bit") + 16;
                                        $insertRows = array();
                                    }
                                    $insertRows[] = $insertRow;
                                    
                                }
                                if (!empty($insertRows)) {
                                    switch ($task) {
                                        case 'INSERT':
                                            $queries[] = "INSERT INTO `" . $table . "`\n".
                                                " (".implode(", ", $insertFields).")\n".
                                                "VALUES\n".
                                                " ".implode(",\n ", $insertRows).";";    
                                            break;
                                        case 'UPDATE':
                                            $queries[] = "REPLACE INTO `" . $table . "`\n".
                                                " (".implode(", ", $insertFields).")\n".
                                                "VALUES\n".
                                                " ".implode(",\n ", $insertRows).";";
                                            break;
                                    }
                                }
                            }
						    

							$affectedTables[$table] = 1;

						} elseif ($task == 'DELETE') {
                            foreach ($dataset as $key => $fields) {
                                $tmpFieldSqlWhere = array();

								if ($table == "ad_master") {
									$arAdsImported["DELETE"][] = $fields["ID_AD_MASTER"];
								}
                                foreach ($fields as $fkey => $value) {
                                    $tmpFieldSqlWhere[$fkey] = " `" . $fkey . "` = '" . mysql_real_escape_string($value) . "' ";

                                    if ($value === NULL) {
                                        $tmpFieldSqlWhere[$fkey] = " `" . $fkey . "` IS NULL ";
                                    }
                                }

                                $queries[] = "DELETE FROM `" . $table . "` WHERE " . implode(' AND ', $tmpFieldSqlWhere) . " ";
                            }

                            $affectedTables[$table] = 1;

                        } elseif ($task == 'DELETE_MULTI') {
                            foreach ($dataset as $fieldName => $values) {
								if ($fieldName == "ID_AD_MASTER") {
									$arAdsImported["DELETE"] = array_merge($arAdsImported["DELETE"], $values);
								}
                                $queries[] = "DELETE FROM `".$table."` WHERE ".mysql_real_escape_string($fieldName)." IN (".implode(', ', $values) . ")";
                            }

                            $affectedTables[$table] = 1;
                        }

					}
				}
			}
			// Free buffer
            $this->buffer = array();
			/*
			for ($i = 0; $i < count($queries); $i++) {
                var_dump(round(strlen($queries[$i])/1024)."kb");
			}
			die(var_dump( count($queries), round($insertBytes/1024)."kb" ));
			*/

			// Get highest ad id from this import source to identify new ads
			$idAdLatest = (int)$db->fetch_atom("
				SELECT ID_AD_MASTER FROM `ad_master` 
				WHERE IMPORT_SOURCE=".$this->importProcess->getImportSource()."
				ORDER BY ID_AD_MASTER DESC
				LIMIT 1");
			
			$db->querynow("START TRANSACTION");
			
			// Disable Keys First
            /*
			foreach(array_keys($affectedTables) as $key => $table) {
			    $db->querynow("ALTER TABLE `".$table."` DISABLE KEYS");
			}
            */

			while (($query = array_shift($queries)) !== null) {
				$lastresult = $db->querynow($query, false, false, true);
				if ($lastresult['str_error'] != '') {

                    // Enable Keys Again
                    foreach(array_keys($affectedTables) as $key => $table) {
                        $db->querynow("ALTER TABLE `".$table."` ENABLE KEYS");
                    }
                    
					// TODO ERROR
					/*echo '<b>ERROR</b><br>';
					var_dump($lastresult);*/
					$this->importProcess->log('MySQL Query Failed ' . $lastresult['str_error'], Ad_Import_Process_Process::LOG_ERROR);
					eventlog("error", "Import: Fehler beim einfügen der Artikel!", $lastresult['str_error']."\nQuery:\n\n".$query);
					die();
				}
			}

			// Enable Keys Again
            /*
			foreach(array_keys($affectedTables) as $key => $table) {
				$db->querynow("ALTER TABLE `".$table."` ENABLE KEYS");
			}
            */

			// Get IDs of new ads
			$arAdsImported["INSERT"] = array_keys($db->fetch_nar("
				SELECT ID_AD_MASTER FROM `ad_master` 
				WHERE IMPORT_SOURCE=".$this->importProcess->getImportSource()." AND ID_AD_MASTER>".$idAdLatest
			));

			$db->querynow("COMMIT");


			// CREATE LOG, UPDATE PACKETS
			if ((int)$this->statsNewArticles > 0) {
				$db->querynow("INSERT INTO `ad_log` (`ID_DATE`, `FK_USER`,`CREATES`) VALUES ('" . date('Y-m-d') . "'," . $this->getImportTargetUser() . ", " . (int)$this->statsNewArticles . ") ON DUPLICATE KEY UPDATE CREATES=CREATES+" . (int)$this->statsNewArticles . " ");
				$db->querynow("INSERT INTO `usercontent` (`FK_USER`, `ADS_USED`) VALUES (" . $this->getImportTargetUser() . "," . (int)$this->statsNewArticles . ") ON DUPLICATE KEY UPDATE ADS_USED=ADS_USED+" . (int)$this->statsNewArticles . "");
			}

			if ((int)$this->statsUpdatedArticles > 0) {

			}

			$db->querynow("UPDATE `packet_order` po  SET COUNT_USED=(SELECT count(*) FROM `packet_order_usage` pou WHERE po.ID_PACKET_ORDER = pou.ID_PACKET_ORDER) WHERE po.FK_USER = '" . $this->getImportTargetUser() . "'");


			// Trigger plugin event
			$paramAdImport = new Api_Entities_EventParamContainer(array(
				"userId"			=> $this->getImportTargetUser(),
				"ads"				=> $arAdsImported,
				"importManagement"	=> $this
			));
			Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_IMPORT_LIVE, $paramAdImport);
		} else {
			// Trigger plugin event
			$paramAdImport = new Api_Entities_EventParamContainer(array(
				"userId"			=> $this->getImportTargetUser(),
				"buffer"			=> $this->buffer,
				"importManagement"	=> $this
			));
			Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_IMPORT_TEST, $paramAdImport);
		}

		// stats
		$this->importProcess->setStatsNewArticles($this->importProcess->getStatsNewArticles() + $this->statsNewArticles);
		$this->importProcess->setStatsUpdatedArticles($this->importProcess->getStatsUpdatedArticles() + $this->statsUpdatedArticles);
		$this->importProcess->setStatsDeletedArticles($this->importProcess->getStatsDeletedArticles() + $this->statsDeletedArticles);
		$this->importProcess->setStatsPausedArticles($this->importProcess->getStatsPausedArticles() + $this->statsPausedArticles);
		$this->importProcess->setStatsStartedArticles($this->importProcess->getStatsStartedArticles() + $this->statsStartedArticles);
		$this->importProcess->setStatsNewProducts($this->importProcess->getStatsNewProducts() + $this->statsNewProducts);
	}


	function __sleep() {
		return array();
	}

	/**
	 * @param $nar_systemsettings
	 * @param $arArticle
	 *
	 * @return mixed
	 */
	protected function transformArticleDataForManufacturer($arArticle) {
		global $nar_systemsettings;

		if ($nar_systemsettings["MARKTPLATZ"]["USE_PRODUCT_DB"]) {

			if (isset($arArticle['FK_MAN']) && !empty($arArticle['FK_MAN'])) {
				if (preg_match("/^[0-9]+$/", $arArticle['FK_MAN'])) {
					// numeric
					if (!array_key_exists($arArticle['FK_MAN'], $this->preloadManufacturersById)) {
						$arArticle['FK_MAN'] = NULL;

						return $arArticle;
					}

					return $arArticle;
				} else {
					// string
					if (array_key_exists($arArticle['FK_MAN'], $this->preloadManufacturers)) {
						$arArticle['FK_MAN'] = $this->preloadManufacturers[$arArticle['FK_MAN']];

						return $arArticle;
					} else {
						// new manufacturer
						$newManufacturerId = (!empty($this->preloadManufacturers) ? max($this->preloadManufacturers) + 1 : 1);  // TODO: Besser lösen!
						$newManufacturerName = $arArticle['FK_MAN'];

                        $this->importProcess->log('New manufacturer: '.$newManufacturerName.' (ID '.$newManufacturerId.')', Ad_Import_Process_Process::LOG_DEBUG);

						$this->preloadManufacturers[$newManufacturerName] = $newManufacturerId;
						$this->preloadManufacturersById[$newManufacturerId] = $newManufacturerName;

						$this->buffer['INSERT']['manufacturers'][] = array(
							'ID_MAN' => $newManufacturerId, 'NAME' => $newManufacturerName, 'CONFIRMED' => 0
						);

						$arArticle['FK_MAN'] = $newManufacturerId;

						return $arArticle;
					}
				}

			}

			return $arArticle;
		}

		return $arArticle;
	}


}