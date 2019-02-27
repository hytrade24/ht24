<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $GLOBALS["ab_path"]."sys/lib.payment.adapter.user.php";


class AdPaymentAdapterManagement {
	private static $db;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return AdPaymentAdapterManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	public function fetchOneById($paymentAdapterId) {
		global $langval;

		$result = $this->getDb()->fetch1("
			SELECT
				pa.*,
				s.V1 as NAME,
				u.PAYMENT_ADAPTER_SELLER_CONFIG,
				u2pa.EXTRA_COST
			FROM
				payment_adapter pa
			JOIN user2payment_adapter u2pa ON pa.ID_PAYMENT_ADAPTER = u2pa.FK_PAYMENT_ADAPTER
			JOIN user u ON u.ID_USER = u2pa.FK_USER
			JOIN `string_payment_adapter` s ON s.S_TABLE='payment_adapter' AND s.FK=pa.ID_PAYMENT_ADAPTER
				AND s.BF_LANG=if(pa.BF_LANG_PAYMENT_ADAPTER & " . $langval . ", " . $langval . ", 1 << floor(log(pa.BF_LANG_PAYMENT_ADAPTER+0.5)/log(2)))
			WHERE
				pa.ID_PAYMENT_ADAPTER = '".(int)$paymentAdapterId."'
				AND u2pa.STATUS = 1 AND u2pa.CONFIG_VALID = 1
			GROUP BY pa.ID_PAYMENT_ADAPTER
			LIMIT 1
		");
		return $result;
	}


	public function fetchAllPaymentAdapterForAd($adId) {
		global $langval;

		 $result = $this->getDb()->fetch_table("
			SELECT
				pa.*,
				s.V1 as NAME,
				u.PAYMENT_ADAPTER_SELLER_CONFIG,
				u2pa.EXTRA_COST
			FROM
				ad2payment_adapter a2pa
			JOIN payment_adapter pa ON pa.ID_PAYMENT_ADAPTER = a2pa.FK_PAYMENT_ADAPTER
			JOIN user2payment_adapter u2pa ON pa.ID_PAYMENT_ADAPTER = u2pa.FK_PAYMENT_ADAPTER
			JOIN user u ON u.ID_USER = u2pa.FK_USER
			JOIN `string_payment_adapter` s ON s.S_TABLE='payment_adapter' AND s.FK=pa.ID_PAYMENT_ADAPTER
				AND s.BF_LANG=if(pa.BF_LANG_PAYMENT_ADAPTER & " . $langval . ", " . $langval . ", 1 << floor(log(pa.BF_LANG_PAYMENT_ADAPTER+0.5)/log(2)))
			WHERE
				a2pa.FK_AD = '".(int)$adId."'
				AND u2pa.STATUS = 1 AND u2pa.CONFIG_VALID = 1
			GROUP BY pa.ID_PAYMENT_ADAPTER

		");
		return $result;
	}

	public function fetchAllPaymentAdapterNamesForAd($adId) {
		$db = $this->getDb();
		$result = $db->fetch_nar("
			SELECT
				a2pa.FK_PAYMENT_ADAPTER,
				pa.ADAPTER_NAME
			FROM ad2payment_adapter a2pa
			JOIN payment_adapter pa ON pa.ID_PAYMENT_ADAPTER = a2pa.FK_PAYMENT_ADAPTER
			WHERE
				a2pa.FK_AD = '".(int)$adId."'
			");

		return $result;
	}

	public function initPaymentAdapterForAd($adId) {
		$this->getDb()->querynow($q = "
			INSERT INTO ad2payment_adapter (FK_AD, FK_PAYMENT_ADAPTER)
			SELECT
				adm.ID_AD_MASTER as FK_AD,
				u2pa.FK_PAYMENT_ADAPTER
			FROM user2payment_adapter u2pa
			JOIN ad_master adm ON adm.FK_USER = u2pa.FK_USER
			WHERE
				adm.ID_AD_MASTER = '".(int)$adId."'
				AND u2pa.AUTOCHECK = 1
		");


	}

	public function updatePaymentAdapterForAd($adId, $paymentAdapter) {
		global $uid;
		$paymentAdapterUserManagement = PaymentAdapterUserManagement::getInstance($this->getDb());

		$this->getDb()->querynow("DELETE FROM ad2payment_adapter WHERE FK_AD = '".(int)$adId."'");
		if(is_array($paymentAdapter)) {
			foreach($paymentAdapter as $paymentAdapterId => $value) {
				if($value && $paymentAdapterUserManagement->isPaymentAdapterAvailableForUser($paymentAdapterId, $uid)) {
					$this->getDb()->update("ad2payment_adapter", array(
						'FK_AD' => $adId,
						'FK_PAYMENT_ADAPTER' => $paymentAdapterId
					));
				}
			}
		}
	}

	public function isPaymentAdapterAvailableForAd($paymentAdapterId, $adId) {
		return ($this->getDb()->fetch_atom("
			SELECT
				COUNT(*) as c
			FROM
				ad2payment_adapter a2pa
			JOIN user2payment_adapter u2pa ON a2pa.FK_PAYMENT_ADAPTER = u2pa.FK_PAYMENT_ADAPTER
			WHERE
				a2pa.FK_AD = '".(int)$adId."'
				AND u2pa.STATUS = 1 AND u2pa.CONFIG_VALID = 1
				AND a2pa.FK_PAYMENT_ADAPTER = '".(int)$paymentAdapterId."'
			") > 0);
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