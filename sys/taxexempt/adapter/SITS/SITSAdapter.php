<?php

require_once dirname(__FILE__).'/lib/class.uid-validation-austria.php';
require_once dirname(__FILE__).'/lib/kunden.uid-validation-austria.default.php';

class TaxExempt_Adapter_SITS_SITSAdapter extends  TaxExempt_Adapter_AbstractTaxExemptAdapter {

	/**
	 * @var UIDvalidationAustria
	 */
	protected $client;

	/**
	 * @var array
	 */
	private $errorCodes = array(
		/* Result 00 bis 09: Resultat fuer die Validierung einer EU UID */
		0	=> 'Validierung konnte NICHT durchgefuehrt werden - UST aufschlagen!',
		1 	=> 'Validierung erfolgreich - EU Unternehmen mit gueltiger UID, UST nicht aufschlagen!',
		2 	=> 'Validierung erfolgreich - AT Unternehmen mit gueltiger UID, UST aufschlagen!',
		5 	=> 'Validierung erfolgreich - UID Nummer ungueltig - UST aufschlagen!',
		6 	=> 'Validierung erfolgreich - UID Nummer gueltig - Stufe-2 Validierung ungenuegend - UST aufschlagen!',
		8 	=> 'Validierung nicht durchgeführt - EU Privatperson o. AT Unternehmen - UST aufschlagen!',
		9 	=> 'Validierung nicht durchgefuehrt - Export - UST nicht aufschlagen!',
		/* Result 10 bis 19: Resultat fuer Validierung Drittstaaten UID (UST ist nicht aufzuschlagen, unabhaengig vom Ergebnis) */
		10 	=> 'Validierung der Drittstaaten Unternehmens UID konnte NICHT durchgefuehrt werden!',
		11 	=> 'Validierung erfolgreich - UID des Drittstaaten Unternehmens ist gueltig!',
		15 	=> 'Validierung erfolgreich - UID des Drittstaaten Unternehmens ist ungueltig!',
		16 	=> 'Validierung erfolgreich - UID des Drittstaaten Unternehmens ist gueltig - Stufe2 ungenuegend!'
	);

	public function init() {
		parent::init();

		if(!isset($this->adapterConfig['REFERENCE_ID'])) {
			$this->adapterConfig['REFERENCE_ID'] = '710-00000:AAAAAAAAAAAA';
		}
		
		if(!isset($this->adapterConfig['COMPANY_NAME'])) {
			$this->adapterConfig['COMPANY_NAME'] = '';
		}
		$GLOBALS["UIDvalidationATglobalvars"]["firmaName"] = $this->adapterConfig['COMPANY_NAME'];
		if(!isset($this->adapterConfig['COMPANY_ADDRESS'])) {
			$this->adapterConfig['COMPANY_ADDRESS'] = '';
		}
		$GLOBALS["UIDvalidationATglobalvars"]["firmaAdresse"] = $this->adapterConfig['COMPANY_ADDRESS'];
		if(!isset($this->adapterConfig['COMPANY_UID'])) {
			$this->adapterConfig['COMPANY_UID'] = 'AT??????????';
		}
		$GLOBALS["UIDvalidationATglobalvars"]["firmaUID"] = $this->adapterConfig['COMPANY_UID'];
		if(!isset($this->adapterConfig['FINANZ_ONLINE_PARTICIPANT_ID'])) {
			$this->adapterConfig['FINANZ_ONLINE_PARTICIPANT_ID'] = 0;
		}
		$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_TeilnehmerID"] = $this->adapterConfig['FINANZ_ONLINE_PARTICIPANT_ID'];
		if(!isset($this->adapterConfig['FINANZ_ONLINE_USER_ID'])) {
			$this->adapterConfig['FINANZ_ONLINE_USER_ID'] = 0;
		}
		$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerID"] = $this->adapterConfig['FINANZ_ONLINE_USER_ID'];
		if(!isset($this->adapterConfig['FINANZ_ONLINE_PIN'])) {
			$this->adapterConfig['FINANZ_ONLINE_PIN'] = 0;
		}
		$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerPIN"] = $this->adapterConfig['FINANZ_ONLINE_PIN'];
		if(!isset($this->adapterConfig['STAGE2_WARNING_PERCENT'])) {
			$this->adapterConfig['STAGE2_WARNING_PERCENT'] = 15;
		}
		$GLOBALS["UIDvalidationATglobalvars"]["Stufe2UntergrenzeProzent"] = $this->adapterConfig['STAGE2_WARNING_PERCENT'];
		if(!isset($this->adapterConfig['STAGE2_SUCCESS_PERCENT'])) {
			$this->adapterConfig['STAGE2_SUCCESS_PERCENT'] = 60;
		}
		$GLOBALS["UIDvalidationATglobalvars"]["Stufe2erfolgreichProzent"] = $this->adapterConfig['STAGE2_SUCCESS_PERCENT'];
		
		$this->client = new UIDvalidationAustria($this->adapterConfig['REFERENCE_ID']);
	}

	public function verify($clientData) {
		if(!$this->isUstIdCountryAvailable($clientData['USTID'])) {
			return self::USTID_VALIDATION_UNKNOWN;
		}

		if($this->validateClientData($clientData)) {
			$countryIso = substr($clientData['USTID'], 0, 2);
			$validationLevel = 1;
			$validateAtUid = 1;
			$clientName = $clientData['COMPANY'];
			$clientAddress = $clientData['STREET'].", ".$clientData['ZIP']." ".$clientData['CITY'];
			$validationResult = $this->client->validateUID(
				$this->adapterConfig['REFERENCE_ID'],
				$countryIso, $clientData['USTID'], $validationLevel, $validateAtUid, $clientName, $clientAddress
			);

			$response = $this->translateResponse($validationResult);

			if ($response['Valid']) {
				return self::USTID_VALIDATION_VALID;
			}

			if($this->adapterConfig['DEBUG']) {
				eventlog("info", "USt. Prüfung fehlgeschlagen - ".$response['Message'], var_export($clientData, true));
			}

			return self::USTID_VALIDATION_INVALID;

		} else {

			return self::USTID_VALIDATION_INVALID;
		}
	}

	protected function validateClientData($clientData) {
		if(!parent::validateClientData($clientData)) {
			return false;
		}

		/*
		if($this->adapterConfig['EXTENDED_VALIDATION'] == true) {
			if(trim($clientData['COMPANY']) == "") {
				return false;
			}
			if(trim($clientData['CITY']) == "") {
				return false;
			}
		}
		*/

		return true;
	}

	protected function isUstIdCountryAvailable($ustid) {
		if (in_array(substr($ustid, 0, 2), array(
			'BE', 'BG', 'DE', 'DK', 'EE', 'FI', 'FR', 'EL', 'GB', 'IE', 'IT', 'HR', 'LV', 'LT', 'LU', 'MT', 'NL', 'AT', 'PL',
			'PT', 'RO', 'SE', 'SK', 'SI', 'ES', 'CZ', 'HU', 'CY'
		))
		) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * @param $response
	 * @return array
	 */
	protected function translateResponse($response) {
		if (array_key_exists($response["OverallValidationResult"], $this->errorCodes)) {
			$response["Valid"] = in_array($response["OverallValidationResult"], array(1, 2, 9));
			$response["Message"] = $this->errorCodes[ $response["OverallValidationResult"] ];
		} else {
			$response["Valid"] = false;
			$response["Message"] = "Unknown validation result: ".$response["OverallValidationResult"];
		}
		return $response;
	}


}