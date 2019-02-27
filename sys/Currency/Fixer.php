<?php

require_once "Currency.php";

class Fixer extends Currency {

	private $key = null;

	function __construct() {
		parent::__construct();
	}

	function update_all_currencies_ratios( $force = false ) {

		$success = true;
		$marketplace_currencies = $this->get_currencires_in_db( $force );

		foreach ( $marketplace_currencies as $row ) {
			$ratio = $this->get_and_convert("EUR",$row["ISO_CURRENCY_FORMAT"]);

			if ( !is_null($ratio) ) {
				$success = $this->update_currency_ratio(
					$row["ID_CURRENCY"],
					$ratio
				);
			}
			else {
				$success = $this->update_currency_auto_status(
					$row["ID_CURRENCY"]
				);
			}
		}
		return $success;
	}

	function get_api_key() {
		global $db;

		if ( is_null($this->key) ) {
			$sql = "SELECT a.value
					FROM `option` a 
					WHERE `plugin` = 'MARKTPLATZ' 
					AND `typ` = 'FIXER_API_KEY'";

			$this->key = $db->fetch_atom( $sql );
		}
		return $this->key;
	}

	function get_and_convert($src = null, $target = null ) {

		$url = 'http://data.fixer.io/api/latest?';
		$url .= 'access_key='.$this->get_api_key();
		$url .= '&base='.$src;
		$url .= '&symbols='.$target;

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $url
		));

		$resp = curl_exec( $curl );

		$resultStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close( $curl );

		if (  $resultStatus == 200 ) {
			$resp_d = json_decode($resp);

			foreach ( $resp_d->rates as $currency_code => $row ) {
				return $row;
			}
		}
		return null;
	}

}