<?php

require_once dirname(__FILE__).'/lib/IXR_Library.php';

class TaxExempt_Adapter_Evatr_EvatrAdapter extends  TaxExempt_Adapter_AbstractTaxExemptAdapter {

	/**
	 * @var IXR_Client
	 */
	protected $client;

	/**
	 * @var array
	 */
	private $errorCodes = array(
		200 => 'Die angefragte USt-IdNr. ist gültig.',
		201 => 'Die angefragte USt-IdNr. ist ungültig.',
		202 => 'Die angefragte USt-IdNr. ist ungültig. Sie ist nicht in der Unternehmerdatei des betreffenden EU-Mitgliedstaates registriert. Hinweis: Ihr Geschäftspartner kann seine gültige USt-IdNr. bei der für ihn zuständigen Finanzbehörde in Erfahrung bringen. Möglicherweise muss er einen Antrag stellen, damit seine USt-IdNr. in die Datenbank aufgenommen wird.',
		203 => 'Die angefragte USt-IdNr. ist ungültig. Sie ist erst ab dem ... gültig (siehe Feld \'Gueltig_ab\').',
		204 => 'Die angefragte USt-IdNr. ist ungültig. Sie war im Zeitraum von ... bis ... gültig (siehe Feld \'Gueltig_ab\' und \'Gueltig_bis\').',
		205 => 'Ihre Anfrage kann derzeit durch den angefragten EU-Mitgliedstaat oder aus anderen Gründen nicht beantwortet werden. Bitte versuchen Sie es später noch einmal. Bei wiederholten Problemen wenden Sie sich bitte an das Bundeszentralamt für Steuern - Dienstsitz Saarlouis.',
		206 => 'Ihre deutsche USt-IdNr. ist ungültig. Eine Bestätigungsanfrage ist daher nicht möglich. Den Grund hierfür können Sie beim Bundeszentralamt für Steuern - Dienstsitz Saarlouis - erfragen.',
		207 => 'Ihnen wurde die deutsche USt-IdNr. ausschliesslich zu Zwecken der Besteuerung des innergemeinschaftlichen Erwerbs erteilt. Sie sind somit nicht berechtigt, Bestätigungsanfragen zu stellen.',
		208 => 'Für die von Ihnen angefragte USt-IdNr. läuft gerade eine Anfrage von einem anderen Nutzer. Eine Bearbeitung ist daher nicht möglich. Bitte versuchen Sie es später noch einmal.',
		209 => 'Die angefragte USt-IdNr. ist ungültig. Sie entspricht nicht dem Aufbau der für diesen EU-Mitgliedstaat gilt. ( Aufbau der USt-IdNr. aller EU-Länder)',
		210 => 'Die angefragte USt-IdNr. ist ungültig. Sie entspricht nicht den Prüfziffernregeln die für diesen EU-Mitgliedstaat gelten.',
		211 => 'Die angefragte USt-IdNr. ist ungültig. Sie enthält unzulässige Zeichen (wie z.B. Leerzeichen oder Punkt oder Bindestrich usw.).',
		212 => 'Die angefragte USt-IdNr. ist ungültig. Sie enthält ein unzulässiges Länderkennzeichen.',
		213 => 'Die Abfrage einer deutschen USt-IdNr. ist nicht möglich.',
		214 => 'Ihre deutsche USt-IdNr. ist fehlerhaft. Sie beginnt mit \'DE\' gefolgt von 9 Ziffern.',
		215 => 'Ihre Anfrage enthält nicht alle notwendigen Angaben für eine einfache Bestätigungsanfrage (Ihre deutsche USt-IdNr. und die ausl. USt-IdNr.). Ihre Anfrage kann deshalb nicht bearbeitet werden.',
		216 => 'Ihre Anfrage enthält nicht alle notwendigen Angaben für eine qualifizierte Bestätigungsanfrage (Ihre deutsche USt-IdNr., die ausl. USt-IdNr., Firmenname einschl. Rechtsform und Ort). Ihre Anfrage kann deshalb nicht bearbeitet werden.',
		217 => 'Bei der Verarbeitung der Daten aus dem angefragten EU-Mitgliedstaat ist ein Fehler aufgetreten. Ihre Anfrage kann deshalb nicht bearbeitet werden.',
		218 => 'Eine qualifizierte Bestätigung ist zur Zeit nicht möglich. Es wurde eine einfache Bestätigungsanfrage mit folgendem Ergebnis durchgeführt: Die angefragte USt-IdNr. ist gültig.',
		219 => 'Bei der Durchführung der qualifizierten Bestätigungsanfrage ist ein Fehler aufgetreten. Es wurde eine einfache Bestätigungsanfrage mit folgendem Ergebnis durchgeführt: Die angefragte USt-IdNr. ist gültig.',
		220 => 'Bei der Anforderung der amtlichen Bestätigungsmitteilung ist ein Fehler aufgetreten. Sie werden kein Schreiben erhalten.',
		221 => 'Die Anfragedaten enthalten nicht alle notwendigen Parameter oder einen ungültigen Datentyp. Weitere Informationen erhalten Sie bei den Hinweisen zum Schnittstelle - Aufruf.',
		999 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
	);

	/**
	 * @var array
	 */
	private $errorCodesExtended = array(
		'A' => 'stimmt überein',
		'B' => 'stimmt nicht überein',
		'C' => 'nicht angefragt',
		'D' => 'vom EU-Mitgliedsstaat nicht mitgeteilt'
	);

	public function init() {
		parent::init();

		if(!isset($this->adapterConfig['SERVER'])) {
			$this->adapterConfig['SERVER'] = 'https://evatr.bff-online.de/';
		}
		if(!isset($this->adapterConfig['EXTENDED_VALIDATION'])) {
			$this->adapterConfig['EXTENDED_VALIDATION'] = false;
		}
		if(!isset($this->adapterConfig['PRINT'])) {
			$this->adapterConfig['PRINT'] = false;
		}
		if(!isset($this->adapterConfig['DEBUG'])) {
			$this->adapterConfig['DEBUG'] = false;
		}

		$this->client = new IXR_Client($this->adapterConfig['SERVER']);
	}

	public function verify($clientData) {
		if(!$this->isUstIdCountryAvailable($clientData['USTID'])) {
			return self::USTID_VALIDATION_UNKNOWN;
		}

		if($this->validateClientData($clientData)) {
			if($this->adapterConfig['EXTENDED_VALIDATION'] == true) {
				$this->client->query('evatrRPC',
					$this->taxExemptConfig['TAX_EXEMPT_USTID'],
					$clientData['USTID'],
					$clientData['COMPANY'],
					$clientData['CITY'],
					$clientData['ZIP'],
					$clientData['STREET'],
					($this->adapterConfig['PRINT'] ? 'ja' : 'nein')
				);

				$response = $this->translateResponse($this->client->getResponse());

				if ($response['ErrorCode'] == 200 && $response['Erg_Ort'] != 'B' && $response['Erg_Name'] != 'B') {
					return self::USTID_VALIDATION_VALID;
				}

			} else {
				$this->client->query('evatrRPC',
					$this->taxExemptConfig['TAX_EXEMPT_USTID'],
					$clientData['USTID']
				);
				$response = $this->translateResponse($this->client->getResponse());

				if ($response['ErrorCode'] == 200) {
					return self::USTID_VALIDATION_VALID;
				}

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

		if($this->adapterConfig['EXTENDED_VALIDATION'] == true) {
			if(trim($clientData['COMPANY']) == "") {
				return false;
			}
			if(trim($clientData['CITY']) == "") {
				return false;
			}
		}

		return true;
	}

	protected function isUstIdCountryAvailable($ustid) {
		if (in_array(substr($ustid, 0, 2), array(
			'BE', 'BG', 'DK', 'EE', 'FI', 'FR', 'EL', 'GB', 'IE', 'IT', 'HR', 'LV', 'LT', 'LU', 'MT', 'NL', 'AT', 'PL',
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
		$responseAsArray = array();

		$response = new SimpleXMLElement($response);

		foreach ($response as $data) {
			$data = $data->value->array->data;
			$key = (string)$data->value[0]->string;
			$value = (string)$data->value[1]->string;

			if ($key === 'ErrorCode') {
				$responseAsArray['Message'] = $this->errorCodes[(int)$value];
			}

			if (substr($key, 0, 4) === 'Erg_') {
				$responseAsArray[$key . '_Message'] = $this->errorCodesExtended[$value];
			}

			$responseAsArray[$key] = $value;
		}

		return $responseAsArray;
	}


}