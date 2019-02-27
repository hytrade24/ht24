<?php

require_once $ab_path.'sys/lib.ads.php';

abstract class Affiliate_Adapter_StandardAffilateAdapter extends Affiliate_Adapter_AbstractAffiliateAdapter {
	protected $tmpCsvFilename;
	protected $tmpCsvUpdateFilename;

	protected $csvDelimeter = "\t";
	protected $csvEnclosure = '"';
	protected $csvEscape = "\\";
	protected $csvNumberOfElements = 32;

	protected abstract function mapping($csvRow, $affiliate, $aliasCategoryMapping, $fallbackCategory);
	protected abstract function getTmpDirecory();


    public function __construct($param) {
        parent::__construct($param);
    }


    public function import() {
		$affiliate = $this->getAffiliate();
		$fallbackCategory = $this->getFallbackCategory();
		$aliasCategoryMapping = $this->getCategoryAliasTable();

		$currentAffiliateAds = $this->getCurrentAffiliateAds();
		$removeAffiliateAds = $currentAffiliateAds;

		$affiliateUrl = $affiliate['URL'];
		$csvRawData = file_get_contents($affiliateUrl);
		$tmpFile = $this->getTmpDirecory().date("YmdHis").'_'.md5(microtime(true)).'_download.csv';
		file_put_contents($tmpFile, $csvRawData);

		$csvRawDataHandler = fopen($tmpFile, "r");

		$this->tmpCsvFilename = date("YmdHis").'_'.md5(microtime(true)).'.csv';
		$databaseCsvImportFileHandler = fopen($this->getTmpDirecory().$this->tmpCsvFilename, "w");

		$this->tmpCsvUpdateFilename = date("YmdHis").'_'.md5(microtime(true)).'_u.csv';
		$databaseCsvUpdateImportFileHandler = fopen($this->getTmpDirecory().$this->tmpCsvUpdateFilename, "w");


		//$csvData = explode("\r\n", $csvRawData);
		//$csvHeader = array_shift($csvData);

		$listOfTouchedAdTables = array();
		$listOfTouchedAffiliateIds = array();
		$numberOfAds = 0;
		$numberOfNewAds = 0;
		$numberOfUpdateAds = 0;

        $logKatUnknown = array();

		echo $affiliate['ID_AFFILIATE']."--------<br><br>";

		$key = 0;
		while($csvRow = fgetcsv($csvRawDataHandler, 0, $this->csvDelimeter, $this->csvEnclosure, $this->csvEscape)) {
            if (($key == 2) && ($affiliate["STATUS"] == AffiliateManagement::STATUS_TESTING)) {
                // Only import 1 row in testing mode
                break;
            }
			if($key == 0) {
				$key++;
				continue;
			}

			$tmpAd = $this->mapping($csvRow, $affiliate, $aliasCategoryMapping, $fallbackCategory);

			$lastUpdate = $tmpAd['LAST_UPDATE'];
			if($lastUpdate == NULL || $affiliate['STAMP_LAST'] == NULL || $affiliate['STAMP_LAST'] == "" || ($lastUpdate > strtotime($affiliate['STAMP_LAST'])) || !array_key_exists($tmpAd['AFFILIATE_IDENTIFIER'], $currentAffiliateAds)) {

                if($tmpAd['FK_KAT'] === NULL) {
                    // Category not found. Output error later.
                    $csvKat = ($affiliate['CHARSET_SOURCE'] != 'UTF-8') ? iconv($affiliate['CHARSET_SOURCE'], 'UTF-8', $csvRow['12']) : $csvRow['12'];
                    if (!in_array($csvKat, $logKatUnknown)) {
                        $logKatUnknown[] = $csvKat;
                    }
                }

				if($tmpAd['FK_KAT'] && $tmpAd['AD_TABLE'] && $tmpAd['PRODUKTNAME']) {
					if(!in_array($tmpAd['AD_TABLE'], $listOfTouchedAdTables)) {
						$listOfTouchedAdTables[] = $tmpAd['AD_TABLE'];
					}

					if(!in_array($tmpAd['AFFILIATE_IDENTIFIER'], $listOfTouchedAffiliateIds)) {
						$listOfTouchedAffiliateIds[] = $tmpAd['AFFILIATE_IDENTIFIER'];
					} else {
						continue;
					}

					$writeData = array(
						$tmpAd['FK_KAT'],
						$tmpAd['AD_TABLE'],
						$tmpAd['PRODUKTNAME'],
						$tmpAd['BESCHREIBUNG'],
						$tmpAd['PREIS'],
						1,
						$tmpAd['MENGE'],
						$tmpAd['VERSANDKOSTEN'],
						$tmpAd['VERSANDOPTIONEN'],
						$tmpAd['CRON_STAT'],
						$tmpAd['CRON_DONE'],
						$tmpAd['STATUS'],
						$tmpAd['STAMP_START'],
						$tmpAd['STAMP_END'],
						$tmpAd['LU_LAUFZEIT'],
						$tmpAd['FK_USER'],
						$tmpAd['AFFILIATE'],
						$tmpAd['AFFILIATE_FK_AFFILIATE'],
						$tmpAd['AFFILIATE_LINK'],
						$tmpAd['AFFILIATE_LINK_CART'],
						$tmpAd['AFFILIATE_IDENTIFIER'],
						$tmpAd['AFFILIATE_URL_IMAGE']
					);

					if(array_key_exists($tmpAd['AFFILIATE_IDENTIFIER'], $currentAffiliateAds)) {
						array_unshift($writeData, $currentAffiliateAds[$tmpAd['AFFILIATE_IDENTIFIER']]);
						fputcsv($databaseCsvUpdateImportFileHandler, $writeData, ';', '"');

						unset($removeAffiliateAds[$tmpAd['AFFILIATE_IDENTIFIER']]);
						$numberOfUpdateAds++;
						echo "update $key ".$tmpAd['PRODUKTNAME']." - ".$tmpAd['AFFILIATE_IDENTIFIER']." - ".$currentAffiliateAds[$tmpAd['AFFILIATE_IDENTIFIER']]."<br>";
					} else {
						fputcsv($databaseCsvImportFileHandler, $writeData, ';', '"');
						$numberOfNewAds++;
						echo "new $key ".$tmpAd['PRODUKTNAME']." <br>";
					}



					$numberOfAds++;
				}

			} else {
				// Skip ads
				echo "skip $key ".$tmpAd['PRODUKTNAME']." <br>";
				$numberOfNewAds++;
				unset($removeAffiliateAds[$tmpAd['AFFILIATE_IDENTIFIER']]);
			}

			$key++;
		}

		if($numberOfAds > 0) {

			// Delete untouched
			if(count($removeAffiliateAds) > 0) {
				$this->cleanUp(array_values($removeAffiliateAds));
			}

			chmod($this->getTmpDirecory().$this->tmpCsvFilename, 0777);
			chmod($this->getTmpDirecory().$this->tmpCsvUpdateFilename, 0777);

			// Insert in ad_master

			if($numberOfNewAds > 0) {
				$this->processNewAds($this->getTmpDirecory() . $this->tmpCsvFilename );

			}

			if($numberOfUpdateAds > 0) {
				$this->processUpdateAds($affiliate['ID_AFFILIATE'], $this->getTmpDirecory() . $this->tmpCsvUpdateFilename);
			}


			// Insert in artikel_..

			foreach($listOfTouchedAdTables as $key => $adTable) {
				$this->getDb()->querynow($a = "
					REPLACE INTO `".$adTable."`
						(ID_".strtoupper($adTable).", STATUS, FK_USER, FK_KAT, STAMP_START, STAMP_END, PREIS, MWST, PRODUKTNAME, BESCHREIBUNG, VERSANDKOSTEN, VERSANDOPTIONEN, MENGE, LU_LAUFZEIT)
					SELECT
						ID_AD_MASTER, STATUS, FK_USER, FK_KAT, STAMP_START, STAMP_END, PREIS, MWST, PRODUKTNAME, BESCHREIBUNG, VERSANDKOSTEN, VERSANDOPTIONEN, MENGE, LU_LAUFZEIT
					FROM
						ad_master
					WHERE
						AFFILIATE_FK_AFFILIATE = '".(int)$affiliate['ID_AFFILIATE']."' AND AD_TABLE = '".$adTable."'


				");

			}

			// Ad Search
			$this->updateAdStampEnd($affiliate['ID_AFFILIATE'], $listOfTouchedAdTables);
			$this->cleanUpAdSearch($affiliate['ID_AFFILIATE']);
			$this->updateAdSearch($affiliate['ID_AFFILIATE']);

			$this->cleanUpAdImages($affiliate['ID_AFFILIATE']);

			// Anzahl der User Anzeigen aktualisieren
			$this->updateUserAdCount($affiliate['FK_USER'], $numberOfAds);

			$this->processPostHook();

		}

		unlink($this->getTmpDirecory().$this->tmpCsvFilename);
		unlink($this->getTmpDirecory().$this->tmpCsvUpdateFilename);
		unlink($tmpFile);

        // Output summary in eventlog
        eventlog('info', 'Affiliate-Artikel wurden importiert: '.$numberOfAds.' gesamt, '.$numberOfNewAds.' neu und '.$numberOfUpdateAds.' aktualisiert.',
            (!empty($logKatUnknown) ? 'Unbekannte Kategorien: "'.implode('", "', $logKatUnknown).'"' : NULL));

		$this->updateAffiliateAdCount($affiliate);
	}

	protected function updateAdStampEnd($affiliate, $listOfTouchedAdTables) {
		$this->getDb()->querynow("UPDATE ad_master SET STAMP_END = '".date("Y-m-d H:i:s", strtotime("+1 month"))."' WHERE AFFILIATE_FK_AFFILIATE = '" . (int)$affiliate['ID_AFFILIATE'] . "'");
		foreach($listOfTouchedAdTables as $key => $adTable) {
			$this->getDb()->querynow("UPDATE $adTable a, ad_master adt SET a.STAMP_END = adt.STAMP_END WHERE a.ID_".strtoupper($adTable)." = adt.ID_AD_MASTER AND adt.AFFILIATE_FK_AFFILIATE = '" . (int)$affiliate['ID_AFFILIATE'] . "'");
		}
	}

	/**
	 * @param $affiliate
	 */
	protected function updateAffiliateAdCount($affiliate) {
		$this->getDb()->update("affiliate", array(
				'ID_AFFILIATE' => $affiliate['ID_AFFILIATE'],
				'NUMBER_OF_ARTICLES' => $this->getDb()->fetch_atom("SELECT COUNT(*) FROM ad_master WHERE AFFILIATE_FK_AFFILIATE = '" . (int)$affiliate['ID_AFFILIATE'] . "'")
		));
	}

	/**
	 * @param $affiliate
	 * @param $numberOfAds
	 */
	protected function updateUserAdCount($userId, $numberOfAds) {
		$this->getDb()->querynow("
			INSERT INTO `usercontent`
				(`FK_USER`, `ADS_USED`)
			VALUES
				('" . ((int)$userId ? (int)$userId : 1) . "', " . (int)$numberOfAds . ")
			ON DUPLICATE KEY UPDATE
				ADS_USED=ADS_USED+" . (int)$numberOfAds . "
		");
	}

	/**
	 * @param $affiliate
	 */
	protected function cleanUpAdSearch($affiliateId) {
		$this->getDb()->querynow($q = "
			DELETE
				ads
			FROM ad_master am
			LEFT JOIN ad_search ads ON am.ID_AD_MASTER = ads.FK_AD
			WHERE
				am.AFFILIATE_FK_AFFILIATE = '" . (int)$affiliateId . "'
		");
	}

	/**
	 * @param $affiliate
	 */
	protected function updateAdSearch($affiliateId) {
		$this->getDb()->querynow("
			INSERT INTO `ad_search`
				(FK_AD, FK_USER, LANG, AD_TABLE, STEXT)
			SELECT
				ID_AD_MASTER, FK_USER, 'de', AD_TABLE, CONCAT_WS(' ', PRODUKTNAME, BESCHREIBUNG)
			FROM ad_master
			WHERE
				AFFILIATE_FK_AFFILIATE = '" . (int)$affiliateId . "'

		");
	}

	/**
	 * @param $affiliate
	 */
	protected function cleanUpAdImages($affiliateId) {
		$this->getDb()->querynow("
			UPDATE ad_master
			SET AFFILIATE_URL_IMAGE = NULL
			WHERE
				AFFILIATE_URL_IMAGE = AFFILIATE_URL_IMAGE_ORIGINAL
				AND AFFILIATE_FK_AFFILIATE = '" . (int)$affiliateId . "'
		");
	}

	/**
	 * @param $affiliate
	 */
	protected function processUpdateAds($affiliateId, $updateFilename) {
		$tmpTableName = "affilate_tmp_" . (int)$affiliateId;
		$this->getDb()->querynow("CREATE TABLE " . $tmpTableName . " LIKE ad_master");

		$this->getDb()->querynow($q = "
			LOAD DATA LOCAL INFILE '" . $updateFilename . "'
			REPLACE INTO TABLE `$tmpTableName`
			FIELDS
				TERMINATED BY ';'
				OPTIONALLY ENCLOSED BY '\"'
				ESCAPED BY '\\\\'
			(ID_AD_MASTER, FK_KAT, AD_TABLE, PRODUKTNAME, BESCHREIBUNG, PREIS, MWST, MENGE, VERSANDKOSTEN, VERSANDOPTIONEN, CRON_STAT, CRON_DONE, STATUS, STAMP_START, STAMP_END,
				LU_LAUFZEIT, FK_USER, AFFILIATE, AFFILIATE_FK_AFFILIATE, AFFILIATE_LINK, AFFILIATE_LINK_CART, AFFILIATE_IDENTIFIER, AFFILIATE_URL_IMAGE)
		");

		$this->getDb()->querynow("
			UPDATE ad_master a, `" . $tmpTableName . "` b
			SET
				a.FK_KAT = b.FK_KAT,
				a.AD_TABLE = b.AD_TABLE,
				a.PRODUKTNAME = b.PRODUKTNAME,
				a.BESCHREIBUNG = b.BESCHREIBUNG,
				a.PREIS = b.PREIS,
				a.MWST = b.MWST,
				a.MENGE = b.MENGE,
				a.VERSANDKOSTEN = b.VERSANDKOSTEN,
				a.VERSANDOPTIONEN = b.VERSANDOPTIONEN,
				a.CRON_STAT = b.CRON_STAT,
				a.CRON_DONE = b.CRON_DONE,
				a.STATUS = b.STATUS,
				a.STAMP_START = b.STAMP_START,
				a.STAMP_END = b.STAMP_END,
				a.LU_LAUFZEIT = b.LU_LAUFZEIT,
				a.FK_USER = b.FK_USER,
				a.AFFILIATE = b.AFFILIATE,
				a.AFFILIATE_FK_AFFILIATE = b.AFFILIATE_FK_AFFILIATE,
				a.AFFILIATE_LINK = b.AFFILIATE_LINK,
				a.AFFILIATE_LINK_CART = b.AFFILIATE_LINK_CART,
				a.AFFILIATE_IDENTIFIER = b.AFFILIATE_IDENTIFIER,
				a.AFFILIATE_URL_IMAGE = b.AFFILIATE_URL_IMAGE
			WHERE a.ID_AD_MASTER = b.ID_AD_MASTER

		");

		$this->getDb()->querynow("DROP TABLE " . $tmpTableName);
	}

	protected function processNewAds($filename) {
		$this->getDb()->querynow($q = "
			LOAD DATA LOCAL INFILE '" . $filename . "'
			INTO TABLE `ad_master`
			FIELDS
				TERMINATED BY ';'
				OPTIONALLY ENCLOSED BY '\"'
				ESCAPED BY '\\\\'
			(FK_KAT, AD_TABLE, PRODUKTNAME, BESCHREIBUNG, PREIS, MWST, MENGE, VERSANDKOSTEN, VERSANDOPTIONEN, CRON_STAT, CRON_DONE, STATUS, STAMP_START, STAMP_END,
				LU_LAUFZEIT, FK_USER, AFFILIATE, AFFILIATE_FK_AFFILIATE, AFFILIATE_LINK, AFFILIATE_LINK_CART, AFFILIATE_IDENTIFIER, AFFILIATE_URL_IMAGE)
		");
	}

	protected function processPostHook() {

	}

}