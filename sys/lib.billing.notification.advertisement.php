<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once dirname(__FILE__).'/lib.billing.invoice.php';
require_once dirname(__FILE__).'/lib.billing.billableitem.php';
require_once dirname(__FILE__).'/lib.user.php';

class BillingNotificationAdvertisementManagement {
	private static $db;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return BillingNotificationAdvertisementManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}


	public function _eventInvoicePay($invoiceId, $data) {
		$db = $this->getDb();
		$isAdvertisement = $db->fetch_atom("SELECT COUNT(*) as A FROM advertisement_user WHERE FK_INVOICE = '" . mysql_real_escape_string($invoiceId) . "'");
		if ($isAdvertisement > 0) {
			$db->querynow("UPDATE `advertisement_user` SET PAID=1, ENABLED=1 WHERE FK_INVOICE=" . (int)$invoiceId);
		}
	}

	public function _eventInvoiceCancel($invoiceId, $data) {
		if (!array_key_exists("KEEP_PERFORMANCES", $data) || ($data["KEEP_PERFORMANCES"] == 0)) {
			$db = $this->getDb();
			$isAdvertisement = $db->fetch_atom("SELECT COUNT(*) as A FROM advertisement_user WHERE FK_INVOICE = '" . mysql_real_escape_string($invoiceId) . "'");
			if ($isAdvertisement > 0) {
				$db->querynow("UPDATE `advertisement_user` SET PAID=0, ENABLED=0 WHERE FK_INVOICE=" . (int)$invoiceId);
			}
		}
	}

	public function _eventInvoiceItemCancel($invoiceItemId, $data) {
		$db = $this->getDb();
		$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($db);
		$arInvoiceItem = $invoiceItemManagement->fetchById($invoiceItemId);
		if (($arInvoiceItem["REF_TYPE"] == BillingInvoiceItemManagement::REF_TYPE_ADVERTISEMENT) && ($arInvoiceItem["REF_FK"] !== null)) {
			if (!array_key_exists("KEEP_PERFORMANCES", $data) || ($data["KEEP_PERFORMANCES"] == 0)) {
				$db->querynow("UPDATE `advertisement_user` SET PAID=0, ENABLED=0, FK_INVOICE=NULL WHERE ID_ADVERTISEMENT_USER=" . (int)$arInvoiceItem["REF_FK"]);
			} else {
				$db->querynow("UPDATE `advertisement_user` SET FK_INVOICE=NULL WHERE ID_ADVERTISEMENT_USER=" . (int)$arInvoiceItem["REF_FK"]);
			}
		}
	}

	public function _eventBillableItemCancel($billableItemId, $data) {
		$db = $this->getDb();
		$billableItemManagement = BillingBillableItemManagement::getInstance($db);
		$arBillableItem = $billableItemManagement->fetchById($billableItemId);
		if (($arBillableItem["REF_TYPE"] == BillingBillableItemManagement::REF_TYPE_ADVERTISEMENT) && ($arBillableItem["REF_FK"] !== null)) {
			if (!array_key_exists("KEEP_PERFORMANCES", $data) || ($data["KEEP_PERFORMANCES"] == 0)) {
				$db->querynow("UPDATE `advertisement_user` SET PAID=0, ENABLED=0, FK_INVOICE=NULL WHERE ID_ADVERTISEMENT_USER=" . (int)$arBillableItem["REF_FK"]);
			} else {
				$db->querynow("UPDATE `advertisement_user` SET FK_INVOICE=NULL WHERE ID_ADVERTISEMENT_USER=" . (int)$arBillableItem["REF_FK"]);
			}
		}
	}

	public function _eventAutomaticBillingRunCreateInvoice($invoiceId, $touchedBillableItemIds, $invoice) {
		$db = $this->getDb();

		foreach ($invoice['__items'] as $key => $invoiceItem) {

			if ($invoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_ADVERTISEMENT) {
				$db->querynow("UPDATE advertisement_user SET FK_INVOICE = '" . (int)$invoiceId . "' WHERE ID_ADVERTISEMENT_USER = '" . (int)$invoiceItem['REF_FK'] . "' ");
			}
		}
	}

	/**
	 * @return ebiz_db $db
	 */
	public function getDb()
	{
		return self::$db;
	}

	/**
	 * @param ebiz_db $db
	 */
	public function setDb(ebiz_db $db)
	{
		self::$db = $db;
	}

	private function __construct()
	{
	}

	public function _eventInvoiceUnpay($invoiceId, $data)
	{
	}

	private function __clone()
	{
	}
}