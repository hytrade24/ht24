<?php

require_once $ab_path.'sys/lib.ads.php';

class Affiliate_Adapter_Affilinet_AffilinetAdapter extends Affiliate_Adapter_AbstractAffiliateAdapter {
	protected $tmpCsvFilename;
	protected $tmpCsvUpdateFilename;

	protected $csvDelimeter = ";";
	protected $csvEnclosure = '"';
	protected $csvEscape = "\\";
	protected $csvNumberOfElements = 17;

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

		echo $affiliate['ID_AFFILIATE']."--------<br><br>";

		$key = 0;
		while($csvRow = fgetcsv($csvRawDataHandler, 0, $this->csvDelimeter, $this->csvEnclosure, $this->csvEscape)) {
			$tmpAd = array();

			if($key == 0) {
				$key++;
				continue;
			}

			$lastUpdate = isset($csvRow['15'])?strtotime($csvRow['15']):NULL;
			//Data


			$produktname = ($csvRow['13']?($csvRow['13'].' '):'') . ($csvRow['14']?($csvRow['14'].' '):'') . $csvRow['1'];
			$beschreibung = $csvRow['9'];


			$tmpAd['PRODUKTNAME'] = ($affiliate['CHARSET_SOURCE']!='UTF-8')?iconv($affiliate['CHARSET_SOURCE'], 'UTF-8', $produktname):$produktname;
			$tmpAd['BESCHREIBUNG'] = ($affiliate['CHARSET_SOURCE']!='UTF-8')?iconv($affiliate['CHARSET_SOURCE'], 'UTF-8', $beschreibung):$beschreibung;
			$tmpAd['PREIS'] = $csvRow['8'];
			$tmpAd['VERSANDKOSTEN'] = str_replace(',', '.', $csvRow['16']);
			if($tmpAd['VERSANDKOSTEN'] > 0) {
				$tmpAd['VERSANDOPTIONEN'] = 3;
			} else if($tmpAd['VERSANDKOSTEN'] = 0) {
				$tmpAd['VERSANDOPTIONEN'] = 0;
			} else {
				$tmpAd['VERSANDOPTIONEN'] = 2;
			}
			// nicht verf�gbar
			$verfugbar = $csvRow['21'];
			if ($verfugbar == "wird nachgeliefert") {
			$tmpAd['MENGE'] = 9;
			}
			else {
			$tmpAd['MENGE'] = 1;
		}

			// Category Transform
			$csvKat = ($affiliate['CHARSET_SOURCE']!='UTF-8')?iconv($affiliate['CHARSET_SOURCE'], 'UTF-8', $csvRow['7']):$csvRow['7'];
			if(array_key_exists($csvKat, $aliasCategoryMapping)) {
				$tmpAd['FK_KAT'] = $aliasCategoryMapping[$csvKat]['ID_KAT'];
				$tmpAd['AD_TABLE'] = $aliasCategoryMapping[$csvKat]['KAT_TABLE'];
			}

			$csvAffilinetKat = ($affiliate['CHARSET_SOURCE']!='UTF-8')?iconv($affiliate['CHARSET_SOURCE'], 'UTF-8', $csvRow['10']):$csvRow['10'];
			if(!$tmpAd['FK_KAT'] && array_key_exists($csvAffilinetKat, $aliasCategoryMapping)) {
				$tmpAd['FK_KAT'] = $aliasCategoryMapping[$csvAffilinetKat]['ID_KAT'];
				$tmpAd['AD_TABLE'] = $aliasCategoryMapping[$csvAffilinetKat]['KAT_TABLE'];
			}

			// Fallback Category
			if(!$tmpAd['FK_KAT'] && $fallbackCategory != NULL) {
				$tmpAd['FK_KAT'] = $fallbackCategory['ID_KAT'];
				$tmpAd['AD_TABLE'] = $fallbackCategory['KAT_TABLE'];
			}

			$tmpAd['CRON_STAT'] = NULL;
			$tmpAd['CRON_DONE'] = 1;
			$tmpAd['STATUS'] = 1;
			$tmpAd['STAMP_START'] = date("Y-m-d H:i:s");
			$tmpAd['STAMP_END'] = date("Y-m-d H:i:s", strtotime("+1 month"));
			$tmpAd['LU_LAUFZEIT'] = 0;
			if((int)$affiliate['FK_USER']) {
				$tmpAd['FK_USER'] = (int)$affiliate['FK_USER'];
			} else {
				$tmpAd['FK_USER'] = 1;
			}


			// Affiliate
			$tmpAd['AFFILIATE'] = 1;
			$tmpAd['AFFILIATE_FK_AFFILIATE'] = $affiliate['ID_AFFILIATE'];
			$tmpAd['AFFILIATE_LINK'] = $csvRow['5'];
			$tmpAd['AFFILIATE_LINK_CART'] = $csvRow['11'];
			$tmpAd['AFFILIATE_IDENTIFIER'] = $csvRow['0'];
			if($csvRow['4'] != "") {
				$tmpAd['AFFILIATE_URL_IMAGE'] = $csvRow['4'];
			} else {
				$tmpAd['AFFILIATE_URL_IMAGE'] = $csvRow['4'];
			}


			if($lastUpdate == NULL || $affiliate['STAMP_LAST'] == NULL || $affiliate['STAMP_LAST'] == "" || ($lastUpdate > strtotime($affiliate['STAMP_LAST'])) || !array_key_exists($tmpAd['AFFILIATE_IDENTIFIER'], $currentAffiliateAds)) {


				if($tmpAd['FK_KAT'] && $tmpAd['AD_TABLE'] && $tmpAd['PRODUKTNAME']) {
					if(!in_array($tmpAd['AD_TABLE'], $listOfTouchedAdTables)) {
						$listOfTouchedAdTables[] = $tmpAd['AD_TABLE'];
					}

					if(!in_array($tmpAd['AFFILIATE_IDENTIFIER'], $listOfTouchedAffiliateIds)) {
						$listOfTouchedAffiliateIds[] = $tmpAd['AFFILIATE_IDENTIFIER'];
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
						echo "update $key ".$tmpAd['PRODUKTNAME']." <br>";
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
				$this->getDb()->querynow($q = "
					LOAD DATA LOCAL INFILE '".$this->getTmpDirecory().$this->tmpCsvFilename."'
					INTO TABLE `ad_master`
					FIELDS
						TERMINATED BY ';'
						OPTIONALLY ENCLOSED BY '\"'
						ESCAPED BY '\\\\'
					(FK_KAT, AD_TABLE, PRODUKTNAME, BESCHREIBUNG, PREIS, MWST, MENGE, VERSANDKOSTEN, VERSANDOPTIONEN, CRON_STAT, CRON_DONE, STATUS, STAMP_START, STAMP_END,
						LU_LAUFZEIT, FK_USER, AFFILIATE, AFFILIATE_FK_AFFILIATE, AFFILIATE_LINK, AFFILIATE_LINK_CART, AFFILIATE_IDENTIFIER, AFFILIATE_URL_IMAGE)
				");

			}

			if($numberOfUpdateAds > 0) {

				echo "DO UPDATE";


				$this->getDb()->querynow($q = "
					LOAD DATA LOCAL INFILE '".$this->getTmpDirecory().$this->tmpCsvUpdateFilename."'
					REPLACE INTO TABLE `ad_master`
					FIELDS
						TERMINATED BY ';'
						OPTIONALLY ENCLOSED BY '\"'
						ESCAPED BY '\\\\'
					(ID_AD_MASTER, FK_KAT, AD_TABLE, PRODUKTNAME, BESCHREIBUNG, PREIS, MWST, MENGE, VERSANDKOSTEN, VERSANDOPTIONEN, CRON_STAT, CRON_DONE, STATUS, STAMP_START, STAMP_END,
						LU_LAUFZEIT, FK_USER, AFFILIATE, AFFILIATE_FK_AFFILIATE, AFFILIATE_LINK, AFFILIATE_LINK_CART, AFFILIATE_IDENTIFIER, AFFILIATE_URL_IMAGE)
				");

				#var_dump($q);
				#var_dump($lastresult);

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
			$this->getDb()->querynow($q = "
				DELETE
					ads
				FROM ad_master am
				LEFT JOIN ad_search ads ON am.ID_AD_MASTER = ads.FK_AD
				WHERE
					am.AFFILIATE_FK_AFFILIATE = '".$affiliate['ID_AFFILIATE']."'
			");
			$this->getDb()->querynow("
				INSERT INTO `ad_search`
					(FK_AD, FK_USER, LANG, AD_TABLE, STEXT)
				SELECT
					ID_AD_MASTER, FK_USER, 'de', AD_TABLE, CONCAT_WS(' ', PRODUKTNAME, BESCHREIBUNG)
				FROM ad_master
				WHERE
					AFFILIATE_FK_AFFILIATE = '".(int)$affiliate['ID_AFFILIATE']."'

			");

			// Anzahl der User Anzeigen aktualisieren
			$this->getDb()->querynow("
				INSERT INTO `usercontent`
					(`FK_USER`, `ADS_USED`)
				VALUES
					('".((int)$affiliate['FK_USER']?(int)$affiliate['FK_USER']:1)."', ".(int)$numberOfAds.")
				ON DUPLICATE KEY UPDATE
					ADS_USED=ADS_USED+".(int)$numberOfAds."
			");

		}

		unlink($this->tmpCsvFilename);
		unlink($this->tmpCsvUpdateFilename);
		unlink($tmpFile);

		$this->getDb()->update("affiliate", array(
			'ID_AFFILIATE' => $affiliate['ID_AFFILIATE'],
			'NUMBER_OF_ARTICLES' => $this->getDb()->fetch_atom("SELECT COUNT(*) FROM ad_master WHERE AFFILIATE_FK_AFFILIATE = '".(int)$affiliate['ID_AFFILIATE']."'")
		));
	}



	public function getTmpDirecory() {
		global $ab_path;
		return $ab_path.'cache/affiliate/affilinet/';
	}

}