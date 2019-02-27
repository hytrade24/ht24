<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 7.4.4
 */

require_once dirname(__FILE__).'/../../../lib.payment.adapter.php';

class Payment_Adapter_Directdebit_DirectDebitAdapter extends Payment_Adapter_AbstractPaymentAdapter {
	public $adapterName = 'directdebit';

	public function __construct($param) {
		parent::__construct($param);
	}

	public function prepare() {
		global $s_lang;

		$userPaymentAdapterConfig = $this->getUserConfiguration('directdebit');

		$tpl = new Template($this->getTemplateFilename('prepare.htm'));
		$tpl->addvars($this->configuration, 'CONFIG_');
		$tpl->addvar('INVOICE_TOTAL_PRICE', $this->paymentObject['TOTAL_PRICE']);
		$tpl->addvar('INVOICE_REMAINING_PRICE', $this->paymentObject['REMAINING_PRICE']);
		$tpl->addvar('CURRENCY', $this->paymentObject['CURRENCY']);
		if ($userPaymentAdapterConfig) {
		  $userPaymentAdapterConfig["iban_part"] = substr($userPaymentAdapterConfig["iban"], 0, 2).str_repeat("*", 16).substr($userPaymentAdapterConfig["iban"], 18);
			$tpl->addvars($userPaymentAdapterConfig, 'USER_CONFIG_');
		}

		return $tpl->process();
	}

	public function doPayment() {
		return false;
	}

	public function verifyPayment() {
		return self::PAYMENT_RESULT_SUCCESS;
	}

	private function getSettingsOptionLastschriftVerification() {
		$query = 'SELECT * 
					FROM `option` 
					WHERE `plugin` = "MARKTPLATZ"
					AND `typ` = "LASTSCHRIFT_VERIFICATION"';
		$result = $this->getDb()->fetch_table( $query );

		return $result[0]["value"];
	}


	public function configurationEditUserConfiguration() {
		global $s_lang;

		$tpl = new Template($this->getTemplateFilename('userconfig.htm'));

		$userPaymentAdapterConfig = $this->getUserConfiguration('directdebit');

		$settingsOptionLastschriftVerification = $this->getSettingsOptionLastschriftVerification();

		if ( $settingsOptionLastschriftVerification == "0" ) {
			$tpl->addvar('show_verification_part',0);
		}
		else {
			$tpl->addvar('show_verification_part',1);
		}

		if ( isset($userPaymentAdapterConfig["Block_Account"]) ) {
			if ( $userPaymentAdapterConfig["Block_Account"] == true ) {
				$tpl->addvar(
					'Block_Account',
					1
				);
			}
		}
		$query = 'SELECT *
					FROM payment_adapter p
					WHERE p.ADAPTER_NAME = "DirectDebit"';
		$result = $this->getDb()->fetch_table( $query );
		$payment_config = explode(PHP_EOL, $result[0]["CONFIG"]);
		$creditor_id =explode("=",$payment_config[4]);

		$tpl->addvar("SITENAME",$GLOBALS["nar_systemsettings"]["SITE"]["SITENAME"]);
		$tpl->addvar("CREDITORID",$creditor_id[1]);

		if($userPaymentAdapterConfig !== NULL) {
			$tpl->addvars($userPaymentAdapterConfig, 'PAYMENT_ADAPTER_CONFIG_');
			$tpl->addvar('PAYMENT_ADAPTER_CONFIG_IsRequestVerificationLocked', ($userPaymentAdapterConfig['RequestVerificationLocked'] > time()));
		}

		return $tpl->process();
	}

	public function configurationEditAdminConfiguration() {
		global $s_lang;

		$tpl = new Template($this->getTemplateFilename('adminconfig.htm'));

		$userPaymentAdapterConfig = $this->getUserConfiguration('directdebit');

		if($userPaymentAdapterConfig !== NULL) {
			$tpl->addvars($userPaymentAdapterConfig, 'PAYMENT_ADAPTER_CONFIG_');
		}

		return $tpl->process();
	}

	public function configurationSaveUserConfiguration($config) {
		$userPaymentAdapterConfig = $this->getUserConfiguration('directdebit');

		$settingsOptionLastschriftVerification = $this->getSettingsOptionLastschriftVerification();

		if($config['Recipient'] == "" || $config['iban'] == "" || $config['bic'] == "" || $config['Bank'] == "") {
			return false;
		}

		if($this->paymentObject['FK_USER'] != NULL) {
			$hash = md5($config['Recipient'].';'.$config['iban'].';'.$config['bic'].';'.$config['Bank'].';');

			if($userPaymentAdapterConfig['accounts'][$hash] != null) {

				$allow_deduct = null;
				if ( isset($config["Allow_Deduct"]) ) {
					if ( $config["Allow_Deduct"] == "1" ) {
						$allow_deduct = true;
					}
					else {
						$allow_deduct = false;
					}
				}
				else {
					$allow_deduct = false;
				}
				if ( $settingsOptionLastschriftVerification == "0" && $allow_deduct == true ) {
					$userPaymentAdapterConfig['Verified'] = true;
					if ( $userPaymentAdapterConfig['VerifiedDate'] == "0000-00-00 00:00:00" ) {
						$userPaymentAdapterConfig['VerifiedDate'] = date("Y-m-d H:i:s");
					}
				}
				else {
					$userPaymentAdapterConfig['Verified'] = $userPaymentAdapterConfig['accounts'][$hash]['Verified'];
				}

				$userPaymentAdapterConfig['RequestVerification'] = $userPaymentAdapterConfig['accounts'][$hash]['RequestVerification'];
				$userPaymentAdapterConfig['RequestVerificationLocked'] = $userPaymentAdapterConfig['accounts'][$hash]['RequestVerificationLocked'];
				$userPaymentAdapterConfig['PinCodeRequestedValue'] = $userPaymentAdapterConfig['accounts'][$hash]['PinCodeRequestedValue'];
				$userPaymentAdapterConfig['VerifiedDate'] = $userPaymentAdapterConfig['accounts'][$hash]['VerifiedDate'];

				if ( isset($config['RequestVerification']) && $config['RequestVerification'] == "1" ) {
					$userPaymentAdapterConfig['RequestVerification'] = $userPaymentAdapterConfig['accounts'][$hash]['RequestVerification'] = 1;
					$userPaymentAdapterConfig['RequestVerificationLocked'] = $userPaymentAdapterConfig['accounts'][$hash]['RequestVerificationLocked'] = time() + 180;

					$payment_adapter_directdb_req_flag = 1;
					if ( $allow_deduct == false && $config['RequestVerification'] != "1" ) {
						$payment_adapter_directdb_req_flag = 3;
					}
					$data = array(
						'ID_USER'                               =>  $this->paymentObject['FK_USER'],
						'PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG'     =>  $payment_adapter_directdb_req_flag
					);
					$this->getDb()->update('user',$data);
				}
				if ( $allow_deduct == true ) {
					$name = $this->getDb()->fetch_atom(
						"SELECT u.NAME FROM user u 
							WHERE u.ID_USER = ".$this->paymentObject['FK_USER']
					);

					$mail_content = array(
						"NAME"      => $name,
						"Recipient" => $config['Recipient'],
						"IBAN"  =>  substr($config["iban"],0,3) . str_repeat("X",strlen($config["iban"])-6) . substr($config["iban"],-3,3),
						"BIC"  =>  substr($config["bic"],0,2) . str_repeat("X",strlen($config["bic"])-5) . substr($config["bic"],-3,3)
					);

					sendMailTemplateToUser(
						0,
						$this->paymentObject['FK_USER'],
						'LASTSCHRIFT_BANK_KONTO_CHANGED',
						$mail_content
					);
				}

			}
			else {
				$allow_deduct = null;
				if ( isset($config["Allow_Deduct"]) ) {
					if ( $config["Allow_Deduct"] == "1" ) {
						$allow_deduct = true;
					}
					else {
						$allow_deduct = false;
					}
				}
				else {
					$allow_deduct = false;
				}

				if ( $settingsOptionLastschriftVerification == "0" && $allow_deduct == true ) {
					$userPaymentAdapterConfig['Verified'] = true;
					$userPaymentAdapterConfig['VerifiedDate'] = date("Y-m-d H:i:s");
				}
				else {
					$userPaymentAdapterConfig['Verified'] = false;
				}
				$userPaymentAdapterConfig['BANK_DETIALS_CREATE_STAMP'] = date("Y-m-d H:i:s");
				if ( isset($config['RequestVerification']) && $config['RequestVerification'] == "1" ) {
					$userPaymentAdapterConfig['RequestVerification'] = true;
					$userPaymentAdapterConfig['RequestVerificationLocked'] = time() + 180;
					$data = array(
						'ID_USER'                               =>  $this->paymentObject['FK_USER'],
						'PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG'     =>  1
					);
					$this->getDb()->update('user',$data);

				}
				else {
					$data = array(
						'ID_USER'                               =>  $this->paymentObject['FK_USER'],
						'PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG'     =>  0
					);
					$this->getDb()->update('user',$data);

					$userPaymentAdapterConfig['RequestVerification'] = false;
					$userPaymentAdapterConfig['RequestVerificationLocked'] = 0;
				}
				$userPaymentAdapterConfig['VerifiedDate'] = "0000-00-00 00:00:00";
				$userPaymentAdapterConfig['PinCodeRequestedValue'] = 0;

				if ( $allow_deduct == true ) {

					$payment_adapter_directdb_req_flag = 1;
					if ( $allow_deduct == false ) {
						$payment_adapter_directdb_req_flag = 3;
					}
					$data = array(
						'ID_USER'                               =>  $this->paymentObject['FK_USER'],
						'PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG'     =>  $payment_adapter_directdb_req_flag
					);
					$this->getDb()->update('user',$data);

					$name = $this->getDb()->fetch_atom(
						"SELECT u.NAME FROM user u 
							WHERE u.ID_USER = ".$this->paymentObject['FK_USER']
					);

					$mail_content = array(
						"NAME"      => $name,
						"Recipient" => $config['Recipient'],
						"IBAN"  =>  substr($config["iban"],0,3) . str_repeat("X",strlen($config["iban"])-6) . substr($config["iban"],-3,3),
						"BIC"  =>  substr($config["bic"],0,2) . str_repeat("X",strlen($config["bic"])-5) . substr($config["bic"],-3,3)
					);

					sendMailTemplateToUser(
						0,
						$this->paymentObject['FK_USER'],
						'LASTSCHRIFT_BANK_KONTO_CHANGED',
						$mail_content
					);
				}

				$userPaymentAdapterConfig['accounts'][$hash] = array_merge($config, array(
					'Verified' => false,
					'RequestVerification'       => $userPaymentAdapterConfig['RequestVerification'],
					'RequestVerificationLocked' => $userPaymentAdapterConfig['RequestVerificationLocked'],
					"VerifiedDate"              => "0000-00-00 00:00:00",
					"PinCodeRequestedValue"     => 0
				));
			}

			if($userPaymentAdapterConfig['RequestVerification'] == true
			   && isset($config['PinCodeValue']) ) {

				if( mb_strtoupper($config['PinCodeValue']) == $userPaymentAdapterConfig['PinCodeRequestedValue'] ) {
					$userPaymentAdapterConfig['RequestVerification'] = false;
					$userPaymentAdapterConfig['Verified'] = true;
					$userPaymentAdapterConfig['VerifiedDate'] = date("Y-m-d H:i:s");
					$userPaymentAdapterConfig['RequestVerificationLocked'] = 0;
					$userPaymentAdapterConfig['accounts'][$hash]['RequestVerification'] = false;
					$userPaymentAdapterConfig['accounts'][$hash]['Verified'] = true;
					$userPaymentAdapterConfig['accounts'][$hash]['VerifiedDate'] = $userPaymentAdapterConfig['VerifiedDate'];
					$userPaymentAdapterConfig['accounts'][$hash]['RequestVerificationLocked'] = 0;

					$userPaymentAdapterConfig['PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG'] = 3;
				} else {
					$userPaymentAdapterConfig['RequestVerificationLocked'] = time() + 180;
					$userPaymentAdapterConfig['accounts'][$hash]['RequestVerificationLocked'] = time() + 180;
				}
			}

			$userPaymentAdapterConfig['Recipient'] = $config['Recipient'];
			$userPaymentAdapterConfig['iban'] = $config['iban'];
			$userPaymentAdapterConfig['bic'] = $config['bic'];
			$userPaymentAdapterConfig['Bank'] = $config['Bank'];

			$this->setUserConfiguration('directdebit', $userPaymentAdapterConfig);

			return true;
		}
	}

	public function configurationSaveAdminConfiguration($config) {
		if ( $config["VerifiedDate"] == "0000-00-00 00:00:00" && $config["Verified"] == "1" ) {
			$config["VerifiedDate"] = date("Y-m-d H:i:s");
		}
		$userPaymentAdapterConfig = $this->getUserConfiguration('directdebit');

		if($this->paymentObject['FK_USER'] != NULL) {
			$hash = md5($config['Recipient'].';'.$config['iban'].';'.$config['bic'].';'.$config['Bank'].';');

			$block_account = null;
			if ( isset($config["Block_Account"]) ) {
				if ( $config["Block_Account"] == "1" ) {
					$block_account = true;

					if ( !isset($userPaymentAdapterConfig["Block_Account"]) ) {

						$name = $this->getDb()->fetch_atom(
							"SELECT u.NAME FROM user u 
							WHERE u.ID_USER = ".$this->paymentObject['FK_USER']
						);

						$mail_content = array(
							"NAME"      => $name,
							"Recipient" => $config['Recipient'],
							"IBAN"  =>  substr($config["iban"],0,3) . str_repeat("X",strlen($config["iban"])-6) . substr($config["iban"],-3,3),
							"BIC"  =>  substr($config["bic"],0,2) . str_repeat("X",strlen($config["bic"])-5) . substr($config["bic"],-3,3)
						);

						sendMailTemplateToUser(
							0,
							$this->paymentObject['FK_USER'],
							'LASTSCHRIFT_BANK_KONTO_BLOCK',
							$mail_content
						);
					}
				}
				else {
					$block_account = false;
				}
			}
			else {
				$block_account = false;

				if ( isset($userPaymentAdapterConfig["Block_Account"]) ) {

					$name = $this->getDb()->fetch_atom(
						"SELECT u.NAME FROM user u 
							WHERE u.ID_USER = ".$this->paymentObject['FK_USER']
					);

					$mail_content = array(
						"NAME"      => $name,
						"Recipient" => $config['Recipient'],
						"IBAN"  =>  substr($config["iban"],0,3) . str_repeat("X",strlen($config["iban"])-6) . substr($config["iban"],-3,3),
						"BIC"  =>  substr($config["bic"],0,2) . str_repeat("X",strlen($config["bic"])-5) . substr($config["bic"],-3,3)
					);

					sendMailTemplateToUser(
						0,
						$this->paymentObject['FK_USER'],
						'LASTSCHRIFT_BANK_KONTO_UNBLOCK',
						$mail_content
					);
				}
			}

			$userPaymentAdapterConfig['accounts'][$hash] = $config;
			$userPaymentAdapterConfig = array_merge($userPaymentAdapterConfig, $config);

			if ( $config['Verified'] == "0" || $block_account ) {
				$rand = rand();
				//echo $rand .'<br />';echo 'in if';
				$userPaymentAdapterConfig["VerifiedDate"] = "0000-00-00 00:00:00";
				//$userPaymentAdapterConfig['accounts'][$hash]['VerifiedDate'] = "0000-00-00 00:00:00";
				$userPaymentAdapterConfig["RequestVerification"] = false;
				//$userPaymentAdapterConfig['accounts'][$hash]['RequestVerification'] = false;
				$userPaymentAdapterConfig["PinCodeRequestedValue"] = 0;
				//$userPaymentAdapterConfig['accounts'][$hash]['PinCodeRequestedValue'] = $rand + 238;

				unset( $userPaymentAdapterConfig['accounts'][$hash] );
				$data = array(
					'ID_USER'                           =>  $this->paymentObject['FK_USER'],
					'PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG' =>  0
				);
				$this->getDb()->update('user',$data);
			}
			else if ( $config['Verified'] == "1" ) {
				$userPaymentAdapterConfig["VerifiedDate"] = date('Y-m-d');
				$userPaymentAdapterConfig['accounts'][$hash]['VerifiedDate'] = $userPaymentAdapterConfig["VerifiedDate"];
				$data = array(
					'ID_USER'                           =>  $this->paymentObject['FK_USER'],
					'PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG' =>  3
				);
				$this->getDb()->update('user',$data);
			}
			if ( $block_account ) {

				$userPaymentAdapterConfig["Verified"] = "0";
				$userPaymentAdapterConfig["VerifiedDate"] = "0000-00-00 00:00:00";
				$userPaymentAdapterConfig["RequestVerification"] = false;
				$userPaymentAdapterConfig["PinCodeRequestedValue"] = 0;
				$userPaymentAdapterConfig["Block_Account"] = true;

				/*$userPaymentAdapterConfig["accounts"][$hash]["Verified"] = "0";
				$userPaymentAdapterConfig["accounts"][$hash]["VerifiedDate"] = "0000-00-00 00:00:00";
				$userPaymentAdapterConfig["accounts"][$hash]["RequestVerification"] = false;
				$userPaymentAdapterConfig["accounts"][$hash]["PinCodeRequestedValue"] = 0;
				$userPaymentAdapterConfig["accounts"][$hash]["Block_Account"] = true;*/


				/*$data = array(
					'ID_USER'                           =>  $this->paymentObject['FK_USER'],
					'PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG' =>  0
				);*/
			}
			else {
				if ( isset($userPaymentAdapterConfig["Block_Account"]) ) {
					unset($userPaymentAdapterConfig["Block_Account"]);
				}
			}
			$this->setUserConfiguration('directdebit', $userPaymentAdapterConfig);

			return true;
		}
	}

	public function configurationRequestVerify() {
		$userPaymentAdapterConfig = $this->getUserConfiguration('directdebit');

		$hash = md5($userPaymentAdapterConfig['Recipient'].';'.$userPaymentAdapterConfig['AccountNumber'].';'.$userPaymentAdapterConfig['Bankcode'].';'.$userPaymentAdapterConfig['Bank'].';');

		$userPaymentAdapterConfig['RequestVerification'] = true;
		$userPaymentAdapterConfig['accounts'][$hash]['RequestVerification'] = true;

		$this->setUserConfiguration('directdebit', $userPaymentAdapterConfig);

		return true;
	}

	public function eventBeforeInvoiceCreate($invoiceRawData) {
		global $nar_systemsettings;

		$userPaymentAdapterConfig = $this->getUserConfiguration('directdebit');
		if($userPaymentAdapterConfig == NULL || $userPaymentAdapterConfig['Verified'] !== true) {
			$defaultAdapterId = $nar_systemsettings['MARKTPLATZ']['INVOICE_STD_PAYMENT_ADAPTER'];

			$paymentAdapterManagement = PaymentAdapterManagement::getInstance($this->getDb());
			$tmpAdapter = $paymentAdapterManagement->fetchById($defaultAdapterId);

			if($tmpAdapter == null) {
				$all = $paymentAdapterManagement->fetchAllByParam(array('STATUS' => 1));
				$tmpAdapter = $all['0'];
			}

			$invoiceRawData['FK_PAYMENT_ADAPTER'] = $tmpAdapter['ID_PAYMENT_ADAPTER'];
		}

		return $invoiceRawData;
	}
}