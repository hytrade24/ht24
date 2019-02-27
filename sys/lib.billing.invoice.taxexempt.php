<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.user.php';
require_once $ab_path.'sys/taxexempt/ValidationUtil.php';
require_once $ab_path.'sys/taxexempt/TaxExemptFactory.php';

class BillingInvoiceTaxExemptManagement {
	private static $db;
	private static $instance = null;

	const COUNTRY_USTID_OPTION_NO_TAX_EXEMPT = 0;
	const COUNTRY_USTID_OPTION_INTRA_COMMUNITY = 1;
	const COUNTRY_USTID_OPTION_FREE_TAX = 2;

	const USTID_VALIDATION_INVALID = 2;
	const USTID_VALIDATION_VALID = 1;
	const USTID_VALIDATION_UNKNOWN = 0;



	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return BillingInvoiceTaxExemptManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	public function getCountryUstIdOption($countryId) {
		return $this->getDb()->fetch_atom("SELECT USTID_OPTION FROM country WHERE ID_COUNTRY = '".(int)$countryId."'");
	}

	public function updateVatNumberValidationForUser($userId) {
		$db = $this->getDb();

		if($this->isTaxExemptGloballyEnabled() == false) {
			return false;
		}

		if($this->isOwnUstIdProvided() == false) {
			return false;
		}

		$taxExemptOption = $this->isVatNumberValidForUser($userId);
		$db->update('user', array(
			'ID_USER' => $userId,
			'UST_ID_VALID' => $taxExemptOption,
			'UST_ID_CHECKDATE' => date("Y-m-d H:i:s")
		));

		return $taxExemptOption;
	}


	/**
	 * @param $userId
	 *
	 * @return int
	 */
	protected function isVatNumberValidForUser($userId) {
		global $nar_systemsettings;
		$userManagement = UserManagement::getInstance($this->getDb());

		$checkUser = $userManagement->fetchById($userId);
		if($checkUser == null) {
			return self::USTID_VALIDATION_UNKNOWN;
		}

		if($checkUser['UST_ID'] == "") {
			return self::USTID_VALIDATION_UNKNOWN;
		}

		if(!TaxExempt_ValidationUtil::checkUstId($checkUser['UST_ID'])) {
			return self::USTID_VALIDATION_INVALID;
		}

		$taxExemptAdapterName = $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_ADAPTER'];
		try {
			$taxExemptAdapter = TaxExempt_TaxExemptFactory::factory($taxExemptAdapterName);

			$verifyUstId = $taxExemptAdapter->verify(array(
				'USERID' => $userId,
				'USTID' => $checkUser['UST_ID'],
				'COMPANY' => $checkUser['FIRMA'],
				'STREET' => $checkUser['STRASSE'],
				'ZIP' => $checkUser['PLZ'],
				'CITY' => $checkUser['ORT']
			));

		} catch(Exception $e) {
			die(var_dump($e));
			return self::USTID_VALIDATION_INVALID;
		}


		return $verifyUstId;
	}

	protected function isTaxExemptGloballyEnabled() {
		global $nar_systemsettings;
		return ($nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_ENABLE'] == 1);
	}

	protected function isOwnUstIdProvided() {
		global $nar_systemsettings;
		return ($nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_TAX_ID'] != "");
	}



	/**
	 * @return ebiz_db $db
	 */
	public function getDb() {
		return self::$db;
	}

	/**
	 * @param ebiz_db $db
	 */
	public function setDb(ebiz_db $db) {
		self::$db = $db;
	}

	private function __construct() {
	}
	private function __clone() {
	}

}