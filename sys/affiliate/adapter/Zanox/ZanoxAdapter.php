<?php

require_once $ab_path.'sys/lib.ads.php';
require_once $ab_path.'sys/affiliate/adapter/StandardAffiliateAdapter.php';

class Affiliate_Adapter_Zanox_ZanoxAdapter extends Affiliate_Adapter_StandardAffilateAdapter {
	protected $tmpCsvFilename;
	protected $tmpCsvUpdateFilename;

	protected $csvDelimeter = ";";
	protected $csvEnclosure = '"';
	protected $csvEscape = "\\";
	protected $csvNumberOfElements = 32;


	public function getTmpDirecory() {
		global $ab_path;
		return $ab_path.'cache/affiliate/zanox/';
	}


	/**
	 * @param $csvRow
	 * @param $affiliate
	 * @param $aliasCategoryMapping
	 * @param $fallbackCategory
	 *
	 * @return array
	 */
	protected function mapping($csvRow, $affiliate, $aliasCategoryMapping, $fallbackCategory) {
		$tmpAd = array();

		$tmpAd['LAST_UPADTE'] = isset($csvRow['1']) ? strtotime($csvRow['1']) : NULL;
		//Data


		$produktname = ($csvRow['2'] ? ($csvRow['2'] . ' ') : '') . $csvRow['3'];
		$beschreibung = $csvRow['24'].'<br> <br>'.$csvRow['13']."<br> <br>".$csvRow['8'];


		$tmpAd['PRODUKTNAME'] = ($affiliate['CHARSET_SOURCE'] != 'UTF-8') ? iconv($affiliate['CHARSET_SOURCE'], 'UTF-8', $produktname) : $produktname;
		$tmpAd['BESCHREIBUNG'] = ($affiliate['CHARSET_SOURCE'] != 'UTF-8') ? iconv($affiliate['CHARSET_SOURCE'], 'UTF-8', $beschreibung) : $beschreibung;
		$tmpAd['PREIS'] = $csvRow['5'];
		$tmpAd['VERSANDKOSTEN'] = $csvRow['17'];
		$tmpAd['VERSANDOPTIONEN'] = ($tmpAd['VERSANDKOSTEN'] > 0)?3:2;
		$tmpAd['MENGE'] = 1;

		// Category Transform
		$csvKat = ($affiliate['CHARSET_SOURCE'] != 'UTF-8') ? iconv($affiliate['CHARSET_SOURCE'], 'UTF-8', $csvRow['8']) : $csvRow['8'];
        if (empty($csvKat)) {
            $csvKat = ($affiliate['CHARSET_SOURCE'] != 'UTF-8') ? iconv($affiliate['CHARSET_SOURCE'], 'UTF-8', $csvRow['12']) : $csvRow['12'];
        }
		if (array_key_exists($csvKat, $aliasCategoryMapping)) {
			$tmpAd['FK_KAT'] = $aliasCategoryMapping[$csvKat]['ID_KAT'];
			$tmpAd['AD_TABLE'] = $aliasCategoryMapping[$csvKat]['KAT_TABLE'];
		}
/*
		$csvAffiliateKat = ($affiliate['CHARSET_SOURCE'] != 'UTF-8') ? iconv($affiliate['CHARSET_SOURCE'], 'UTF-8', $csvRow['7']) : $csvRow['7'];
		if (!$tmpAd['FK_KAT'] && array_key_exists($csvAffiliateKat, $aliasCategoryMapping)) {
			$tmpAd['FK_KAT'] = $aliasCategoryMapping[$csvAffiliateKat]['ID_KAT'];
			$tmpAd['AD_TABLE'] = $aliasCategoryMapping[$csvAffiliateKat]['KAT_TABLE'];
		}
*/
		// Fallback Category
		if (!$tmpAd['FK_KAT'] && $fallbackCategory != NULL) {
			$tmpAd['FK_KAT'] = $fallbackCategory['ID_KAT'];
			$tmpAd['AD_TABLE'] = $fallbackCategory['KAT_TABLE'];
		}

		$tmpAd['CRON_STAT'] = NULL;
		$tmpAd['CRON_DONE'] = 1;
		$tmpAd['STATUS'] = 1;
		$tmpAd['STAMP_START'] = date("Y-m-d H:i:s");
		$tmpAd['STAMP_END'] = date("Y-m-d H:i:s", strtotime("+1 month"));
		$tmpAd['LU_LAUFZEIT'] = 0;
		if ((int)$affiliate['FK_USER']) {
			$tmpAd['FK_USER'] = (int)$affiliate['FK_USER'];
		} else {
			$tmpAd['FK_USER'] = 1;
		}


		// Affiliate
		$tmpAd['AFFILIATE'] = 1;
		$tmpAd['AFFILIATE_FK_AFFILIATE'] = $affiliate['ID_AFFILIATE'];
		$tmpAd['AFFILIATE_LINK'] = $csvRow['11'];
		$tmpAd['AFFILIATE_LINK_CART'] = $csvRow['11'];
		$tmpAd['AFFILIATE_IDENTIFIER'] = $csvRow['0'];
		if ($csvRow['10'] != "") {
			$tmpAd['AFFILIATE_URL_IMAGE'] = $csvRow['10'];
		} else {
			$tmpAd['AFFILIATE_URL_IMAGE'] = $csvRow['9'];
		}

		return  $tmpAd;

	}

}