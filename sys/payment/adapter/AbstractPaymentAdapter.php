<?php
/* ###VERSIONSBLOCKINLCUDE### */

abstract class Payment_Adapter_AbstractPaymentAdapter implements Payment_Adapter_PaymentAdapterInterface {
	const PAYMENT_RESULT_FAILED = 0;
	const PAYMENT_RESULT_PENDING = 1;
	const PAYMENT_RESULT_SUCCESS = 255;

    protected $paymentObject;
    protected $configuration;
    protected $db;
	protected $adapterName;
    protected $error;

	private $key = '1a9b545312cd4aa2cbd6e9f8cd5e7a9cfge34vrtf';
	private $iv = 'q12331adf2sds1z22Adsa2das23125c2dfew2f12d';
	private $encrypt_method = "AES-256-CBC";

    public function __construct($param) {
        global $db;

        if(isset($param['CONFIG']) && is_array($param['CONFIG'])) {
            $this->configuration = $param['CONFIG'];
        }

        $this->db = $db;
    }

	public function button() {
		global $ab_path, $s_lang;

		$filename = $this->getTemplateFilename('button.htm');

		$tpl = new Template($filename);

		$tpl->addvars($this->paymentObject);
		if (is_array($this->paymentObject["DATA"][$this->paymentObject["TYPE"]])) {
			$tpl->addvars($this->paymentObject["DATA"][$this->paymentObject["TYPE"]]);
		}

		return $tpl->process();
	}

	public function buttonOrder() {
		global $ab_path, $s_lang;

		$filename = $this->getTemplateFilename('buttonorder.htm');
		$tpl = new Template($filename);

		$tpl->addvars($this->paymentObject);
		if (is_array($this->paymentObject["DATA"][$this->paymentObject["TYPE"]])) {
			$ar_info = $this->paymentObject["DATA"][$this->paymentObject["TYPE"]];
			$tpl->addvars($ar_info);
		}

		return $tpl->process();
	}

    public function prepare() {
        global $ab_path, $s_lang;

        $filename = $this->getTemplateFilename('prepare.htm');
        $tpl = new Template($filename);

        $tpl->addvar('PAYURL', $this->paymentObject['PAYURL']);

        return $tpl->process();
    }

	public function prepareOrder() {
		global $ab_path, $s_lang;

		$filename = $this->getTemplateFilename('prepareorder.htm');
		$tpl = new Template($filename);

		$tpl->addvar('PAYURL', $this->paymentObject['PAYURL']);

		return $tpl->process();
	}

    public function init(array $paymentObject) {
        $this->paymentObject = $paymentObject;
    }

    public function successPayment() {
        global $ab_path, $s_lang;

        $filename = $this->getTemplateFilename('success.htm');
        $tpl = new Template($filename);

        $tpl->addvars($this->paymentObject);

        return $tpl->process();
    }

    public function pendingPayment() {
        global $ab_path, $s_lang;

        $filename = $this->getTemplateFilename('pending.htm');
        $tpl = new Template($filename);

        $tpl->addvars($this->paymentObject);

        return $tpl->process();
    }

	public function cancelPayment() {
		global $ab_path, $s_lang;

		$filename = $this->getTemplateFilename('cancel.htm');
		$tpl = new Template($filename);

		$tpl->addvars($this->paymentObject);

		return $tpl->process();
	}

    public function getErrorList() {
        return $this->error;
    }
    
    protected  function addError($ident) {
        if (!is_array($this->error)) {
            $this->error = array();
        }

        $this->error[] = $ident;
    }

	public function configurationEditUserConfiguration() { return ""; }
	public function configurationEditSellerConfiguration($sellerPaymentAdapterConfig = null, $template = null) {
		global $ab_path, $s_lang;

		if ($template === null) {
		    $template = 'sellerconfig.htm';
        }
		$filename = $this->getTemplateFilename($template);

		$tpl = new Template($filename);

		if ($sellerPaymentAdapterConfig === null) {
			$sellerPaymentAdapterConfig = $this->getSellerConfiguration($this->getAdapterName());
		}
		if($sellerPaymentAdapterConfig !== NULL) {
			$tpl->addvars($sellerPaymentAdapterConfig, 'PAYMENT_ADAPTER_SELLER_CONFIG_');
		}

		return $tpl->process();
	}

    public function configurationEditAdminConfiguration() { return "";  }
	public function configurationSaveUserConfiguration($config) { return TRUE;  }

	/**
	 * @param $config
	 *
	 * @return bool
	 */
	public function configurationSaveSellerConfiguration($config) {
		if($this->paymentObject['FK_SELLER'] != NULL) {
			$this->setSellerConfiguration($this->getAdapterName(), $config);

			return TRUE;
		}
		return FALSE;
	}

    public function configurationSaveAdminConfiguration($config) { return TRUE; }

    public function configurationIsUserConfigurationAllowed() {
        return TRUE;
    }


    public function eventBeforeInvoiceCreate($invoiceRawData) {
        return $invoiceRawData;
    }

	private function generate_iv( $id ) {
		$encrypt_method = "AES-256-CBC";
		$secret_key = $this->key;
		$secret_iv = $this->iv;

		// hash
		$key = hash('sha256', $id.$secret_key);

		// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
		$iv = substr(hash('sha256', $secret_iv), 0, 16);

		$data = new stdClass();
		$data->iv = $iv;
		$data->key = $key;

		return $data;
	}

	protected function encrypt( $string, $id ) {

		$data = $this->generate_iv( $id );

		$output = openssl_encrypt($string, $this->encrypt_method, $data->key, 0, $data->iv);
		$output = base64_encode($output);

		return $output;

	}

	protected function decrypt( $string, $id ) {

		$data = $this->generate_iv($id);

		$output = openssl_decrypt(base64_decode($string), $this->encrypt_method, $data->key, 0, $data->iv);

		return $output;
	}

    public function getUserConfiguration($adapterName) {
        global $s_lang, $ab_path;
        require_once $ab_path.'sys/lib.user.php';

        $userManagement = UserManagement::getInstance($this->getDb());
        $user = $userManagement->fetchById($this->paymentObject['FK_USER']);

        if ($this->paymentObject['FK_USER']) {
            $tmp = ($user['PAYMENT_ADAPTER_CONFIG'] == NULL) ? NULL : unserialize(
            	$this->decrypt(
            		$user['PAYMENT_ADAPTER_CONFIG'],
			        $this->paymentObject['FK_USER']
	            )
            );
            if($adapterName === NULL) {
                $userPaymentAdapterConfig = $tmp;
            } else {
                $userPaymentAdapterConfig = $tmp[$adapterName];
            }
            return $userPaymentAdapterConfig;
        }

        return NULL;
    }

    protected function getSellerConfiguration($adapterName) {
        global $s_lang, $ab_path;
        require_once $ab_path.'sys/lib.user.php';

        $userManagement = UserManagement::getInstance($this->getDb());
        $user = $userManagement->fetchById($this->paymentObject['FK_SELLER']);

        if ($this->paymentObject['FK_SELLER']) {
            $tmp = ($user['PAYMENT_ADAPTER_SELLER_CONFIG'] == NULL) ? NULL : unserialize(
	            $this->decrypt(
		            $user['PAYMENT_ADAPTER_SELLER_CONFIG'],
		            $this->paymentObject['FK_SELLER']
	            )
            );
            if($adapterName === NULL) {
                $sellerPaymentAdapterConfig = $tmp;
            } else {
                $sellerPaymentAdapterConfig = $tmp[$adapterName];
            }
            return $sellerPaymentAdapterConfig;
        }

        return NULL;
    }

    public function setUserConfiguration($adapterName, $config) {
        $userPaymentAdapterConfig = $this->getUserConfiguration($adapterName);

        if($userPaymentAdapterConfig == NULL) {
            $userPaymentAdapterConfig = array();
        }

        if($this->paymentObject['FK_USER'] != NULL) {
            $arr = array(
              'ID_USER' => $this->paymentObject['FK_USER']
            );
      
            if ( isset($config['PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG']) ) {
              $arr['PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG'] = $config['PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG'];
              unset( $config['PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG'] );
            }
      
            $userPaymentAdapterConfig[$adapterName] = $config;
            $arr['PAYMENT_ADAPTER_CONFIG'] = serialize($userPaymentAdapterConfig);

	        $arr['PAYMENT_ADAPTER_CONFIG'] = $this->encrypt(
            	$arr['PAYMENT_ADAPTER_CONFIG'],
	            $this->paymentObject['FK_USER']
	        );

            $this->getDb()->update('user', $arr);
        }
    }

	protected function setSellerConfiguration($adapterName, $config) {
		$sellerPaymentAdapterConfig = $this->getSellerConfiguration(NULL);


		if($sellerPaymentAdapterConfig == NULL) {
			$sellerPaymentAdapterConfig = array();
		}

		if($this->paymentObject['FK_SELLER'] != NULL) {
			$sellerPaymentAdapterConfig[$adapterName] = $config;

			$serialize_str = serialize($sellerPaymentAdapterConfig);

			$encrypted_serialize_str = $this->encrypt(
				$serialize_str,
				$this->paymentObject['FK_SELLER']
			);

			$this->getDb()->update('user', array(
				'ID_USER' => $this->paymentObject['FK_SELLER'],
				'PAYMENT_ADAPTER_SELLER_CONFIG' => $encrypted_serialize_str
			));
		}
	}

	protected function getTotalInvoicePrice() {
		$totalPrice = $this->paymentObject['TOTAL_PRICE'];
		if (isset($this->paymentObject['REMAINING_PRICE']) && ($this->paymentObject['REMAINING_PRICE'] < $this->paymentObject['TOTAL_PRICE'])) {
			$totalPrice = $this->paymentObject['REMAINING_PRICE'];
		}

		return $totalPrice;
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

	public function getPaymentObject() {
		return $this->paymentObject;
	}

	protected function getTemplateFilename($file) {
		global $ab_path, $s_lang;

		$filename = $ab_path.'sys/payment/adapter/'.strtolower($this->getAdapterName()).'/tpl/'.$s_lang.'/'.$file;
		if(file_exists($filename)) {
			return $filename;
		}

		$filename = $ab_path.'sys/payment/adapter/'.strtolower($this->getAdapterName()).'/tpl/de/'.$file;
		if(file_exists($filename)) {
			return $filename;
		}

		$filename = $ab_path.'sys/payment/adapter/_abstract/tpl/'.$s_lang.'/'.$file;
		if(file_exists($filename)) {
			return $filename;
		}

		$filename = $ab_path.'sys/payment/adapter/_abstract/tpl/de/'.$file;
		if(file_exists($filename)) {
			return $filename;
		}

	}

}
