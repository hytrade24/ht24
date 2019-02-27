<?php

require_once $ab_path.'sys/taxexempt/ValidationUtil.php';

abstract class TaxExempt_Adapter_AbstractTaxExemptAdapter implements TaxExempt_Adapter_TaxExemptAdapterInterface {

	const USTID_VALIDATION_INVALID = 2;
	const USTID_VALIDATION_VALID = 1;
	const USTID_VALIDATION_UNKNOWN = 0;

    protected $db;
	protected $adapterName;
	protected $taxExemptConfig;
	protected $adapterConfig;

    public function __construct() {
        global $db, $nar_systemsettings;

        $this->db = $db;
    }

	public function init() {
		global $db, $nar_systemsettings;

		$this->taxExemptConfig = array(
			'TAX_EXEMPT_ENABLE' => $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_ENABLE'],
			'TAX_EXEMPT_TAX_ID' => $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_TAX_ID'],
			'TAX_EXEMPT_ADAPTER' => $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_ADAPTER'],
			'TAX_EXEMPT_USTID' => $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_USTID']
		);

		$this->adapterConfig = $this->getAdapterConfiguration($this->getAdapterName());
	}


	protected  function validateClientData($clientData) {
		if($clientData['USTID'] == '' || !TaxExempt_ValidationUtil::checkUstId($clientData['USTID'])) {
			return false;
		}

		return true;
	}

	protected function getAdapterConfiguration($adapterName) {
		$db = $this->getDb();
		$adapter = $db->fetch1("SELECT * FROM billing_invoice_tax_exempt_adapter WHERE ADAPTER_NAME = '".$adapterName."'");

		if($adapter['ADAPTER_CONFIG'] != NULL) {
			$tmpFilename = tempnam(sys_get_temp_dir(), 'EBIZ');
			file_put_contents($tmpFilename, $adapter['ADAPTER_CONFIG']);
			$ini =  parse_ini_file($tmpFilename, true);
			unlink($tmpFilename);

			return $ini;
		} else {
			return array();
		}
	}

    /**
     * @return ebiz_db $db
     */
    public function getDb() {
        return $this->db;
    }

	public function setAdapterName($adapterName) {
		$this->adapterName = $adapterName;

		return $this;
	}

	public function getAdapterName() {
		return $this->adapterName;
	}


}