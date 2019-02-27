<?php

class TaxExempt_Adapter_EcMias_EcMiasAdapter extends  TaxExempt_Adapter_AbstractTaxExemptAdapter {

	/**
	 * @var SoapClient $client
	 */
	protected $client;

	public function init() {
		parent::init();

		$this->client = new SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl");
	}

	public function verify($clientData) {
		$clientData['USTID'] = preg_replace("/[^0-9A-Z]+/", "", strtoupper($clientData['USTID']));
		if(!$this->isUstIdCountryAvailable($clientData['USTID'])) {
			return self::USTID_VALIDATION_UNKNOWN;
		}

		try {
			$result = $this->validateClientData($clientData);
			if (is_object($result) && $result->valid) {
				if($this->adapterConfig['EXTENDED_VALIDATION'] === true) {
					if (($result->traderName == null) || ($result->traderAddress == null) ||
						(strtolower($result->traderName) != $clientData["COMPANY"])) {
						return self::USTID_VALIDATION_INVALID;
					}
				}
				return self::USTID_VALIDATION_VALID;
			} else {
				return self::USTID_VALIDATION_INVALID;
			}
		} catch (SoapFault $e) {
			return self::USTID_VALIDATION_UNKNOWN;
		}
	}

	protected function validateClientData($clientData) {
		if(!parent::validateClientData($clientData)) {
			return false;
		}
		
		if (false) {
			$result = $this->client->checkVat(array(
				"countryCode" => substr($clientData['USTID'], 0, 2), 
				"vatNumber" => substr($clientData['USTID'], 2)
			));
		} else {
			$ustidOwn = $GLOBALS['nar_systemsettings']['MARKTPLATZ']['INVOICE_TAX_EXEMPT_USTID'];
			if (!preg_match("/^\s*([A-Z]{2})\s*([0-9A-Za-z\+\*\.]+)\s*$/", $ustidOwn, $arUstidOwnSplit)) {
				return false;
			}
			if (!preg_match("/^\s*([A-Z]{2})\s*([0-9A-Za-z\+\*\.]+)\s*$/", $clientData['USTID'], $arUstidClientSplit)) {
				return false;
			}
			$result = $this->client->checkVatApprox(array(
				"countryCode" => $arUstidClientSplit[1], 
				"vatNumber" => $arUstidClientSplit[2],
				"traderName" => $clientData["COMPANY"],
				"traderStreet" => $clientData["STREET"],
				"traderPostcode" => $clientData["ZIP"],
				"traderCity" => $clientData["CITY"],
				"requesterCountryCode" => $arUstidOwnSplit[1],
				"requesterVatNumber" => $arUstidOwnSplit[2]
			));
		}

		return $result;
	}

	protected function isUstIdCountryAvailable($ustid) {
		if (in_array(substr($ustid, 0, 2), array(
			'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'GB', 'HR', 'HU', 'IE', 'IT', 'LT',
			'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'
		))
		) {
			return true;
		} else {
			return false;
		}
	}


}