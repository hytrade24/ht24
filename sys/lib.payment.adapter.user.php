<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $GLOBALS["ab_path"]."sys/lib.payment.adapter.php";


class PaymentAdapterUserManagement {
	private static $db;
	private static $instance = null;

	const STATUS_ENABLED = 1;
 	const STATUS_DISABLED = 0;

	const AUTOCHECK_ENABLED = 1;
	const AUTOCHECK_DISABLED = 0;

	const CONFIG_VALID = 1;
 	const CONFIG_INVALID = 0;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return PaymentAdapterUserManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	public function fetchAllAvailablePaymentAdapterByUser($userId) {
        global $langval;
		return $this->getDb()->fetch_table("
			SELECT
				pa.*,
				s.V1 as NAME,
				u2pa.CONFIG_VALID AS SELLER_CONFIG_VALID,
				u2pa.STATUS AS SELLER_STATUS,
				(u2pa.CONFIG_VALID = 1 AND u2pa.STATUS = 1) AS IS_AVAILABLE
			FROM payment_adapter pa
			LEFT JOIN user2payment_adapter u2pa ON u2pa.FK_PAYMENT_ADAPTER = pa.ID_PAYMENT_ADAPTER
            JOIN `string_payment_adapter` s ON s.S_TABLE='payment_adapter' AND s.FK=pa.ID_PAYMENT_ADAPTER
                AND s.BF_LANG=if(pa.BF_LANG_PAYMENT_ADAPTER & " . $langval . ", " . $langval . ", 1 << floor(log(pa.BF_LANG_PAYMENT_ADAPTER+0.5)/log(2)))
			WHERE
				u2pa.FK_USER = '".(int)$userId."' OR u2pa.FK_USER IS NULL
				AND pa.STATUS_USER = '".PaymentAdapterManagement::USER_STATUS_ENABLED."'
		");
	}

	public function fetchAllAutoCheckedPaymentAdapterByUser($userId) {
		return $this->getDb()->fetch_nar("
			SELECT
				u2pa.FK_PAYMENT_ADAPTER,
				u2pa.FK_PAYMENT_ADAPTER
			FROM user2payment_adapter u2pa
			WHERE
				u2pa.FK_USER = '".(int)$userId."' AND u2pa.AUTOCHECK = 1
		");
	}

	public function isPaymentAdapterAvailableForUser($paymentAdapterId, $userId) {
		return ($this->getDb()->fetch_atom("SELECT COUNT(*) as c FROM user2payment_adapter WHERE FK_USER = '".(int)$userId."' AND FK_PAYMENT_ADAPTER = '".(int)$paymentAdapterId."' AND CONFIG_VALID = 1 AND STATUS = 1") > 0);
	}

	protected  function existPaymentAdapterForUser($paymentAdapterId, $userId) {
		return ($this->getDb()->fetch_atom("SELECT COUNT(*) as c FROM user2payment_adapter WHERE FK_USER = '".(int)$userId."' AND FK_PAYMENT_ADAPTER = '".(int)$paymentAdapterId."' ") > 0);
	}


    public function setPaymentAdapterConfigurationValidityForUser($paymentAdapterId, $userId, $validity) {
		if(!$this->existPaymentAdapterForUser($paymentAdapterId, $userId)) {
			$this->createPaymentAdapterConfigurationForUser($paymentAdapterId, $userId);
		}
		$this->getDb()->querynow("UPDATE user2payment_adapter SET CONFIG_VALID = '".($validity?1:0)."' WHERE FK_USER = '".(int)$userId."' AND FK_PAYMENT_ADAPTER = '".(int)$paymentAdapterId."' ");
	}

	public function setPaymentAdapterConfigurationStatusForUser($paymentAdapterId, $userId, $status) {
		if(!$this->existPaymentAdapterForUser($paymentAdapterId, $userId)) {
			$this->createPaymentAdapterConfigurationForUser($paymentAdapterId, $userId);
		}
		$this->getDb()->querynow("UPDATE user2payment_adapter SET STATUS = '".($status?1:0)."' WHERE FK_USER = '".(int)$userId."' AND FK_PAYMENT_ADAPTER = '".(int)$paymentAdapterId."' ");
	}

	public function setPaymentAdapterConfigurationAutocheckForUser($paymentAdapterId, $userId, $autocheck) {
		if(!$this->existPaymentAdapterForUser($paymentAdapterId, $userId)) {
			$this->createPaymentAdapterConfigurationForUser($paymentAdapterId, $userId);
		}
		$this->getDb()->querynow("UPDATE user2payment_adapter SET AUTOCHECK = '".($autocheck?1:0)."' WHERE FK_USER = '".(int)$userId."' AND FK_PAYMENT_ADAPTER = '".(int)$paymentAdapterId."' ");
	}

	public function getPaymentAdapterConfigurationForUser($paymentAdapterId, $userId) {
		return $this->getDb()->fetch1("SELECT * FROM user2payment_adapter WHERE FK_USER = '".(int)$userId."' AND FK_PAYMENT_ADAPTER = '".(int)$paymentAdapterId."' ");
	}

	protected function createPaymentAdapterConfigurationForUser($paymentAdapterId, $userId) {
		$this->getDb()->update("user2payment_adapter", array(
			'CONFIG_VALID' => 0,
			'STATUS' => 0,
			'FK_USER' => $userId,
			'FK_PAYMENT_ADAPTER' => $paymentAdapterId
		));
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