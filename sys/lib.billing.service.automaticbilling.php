<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once dirname(__FILE__).'/lib.billing.invoice.php';
require_once dirname(__FILE__).'/lib.billing.billableitem.php';


class BillingServiceAutomaticBillingManagement {
	private static $db;
	private static $instance = null;



	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return BillingServiceAutomaticBillingManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function runAll() {
        $billableItemManagement = BillingBillableItemManagement::getInstance($this->getDb());

        $billableItemManagement->clearInvalidItems();
        $billableItems = $billableItemManagement->fetchAllByParam(array('STAMP_DONT_BILL_UNTIL_LT' => date('Y-m-d')));

        $billableItemIds = array();

        foreach ($billableItems as $key => $billableItem) {
            $billableItemIds[] = $billableItem['ID_BILLING_BILLABLEITEM'];
        }

        return $this->run($billableItemIds);

    }


    public function run($billableItemIds) {
        $billableItemManagement = BillingBillableItemManagement::getInstance($this->getDb());
        $invoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());
        $result = array();

        $billableItems = $billableItemManagement->fetchAllByParam(array(
            'ID_BILLING_BILLABLEITEM' => $billableItemIds,
        ));

        $billableItemsGroupedByUser = array();
        $salesUserByInvoiceUser = array();
        $result[] = 'Starte Rechnungslauf am '.date("Y-m-d H:i:s");

        foreach($billableItems as $key => $billableItem) {
            if(!array_key_exists($billableItem['FK_USER'], $billableItemsGroupedByUser)) {
                $billableItemsGroupedByUser[$billableItem['FK_USER']] = array();
            }
            $salesUserByInvoiceUser[$billableItem['FK_USER']] = $billableItem["FK_USER_SALES"];
            $billableItemsGroupedByUser[$billableItem['FK_USER']][] = $billableItem;
        }

        foreach($billableItemsGroupedByUser as $billableItemUser => $billableItems) {
            $invoiceItems = array();
            $touchedBillableItemIds = array();
            foreach($billableItems as $key => $billableItem) {
                $invoiceItems[] = array(
                    'FK_TAX' => $billableItem['FK_TAX'],
                    'DESCRIPTION' => $billableItem['DESCRIPTION'],
                    'QUANTITY' => $billableItem['QUANTITY'],
                    'PRICE' => $billableItem['PRICE'],
					'REF_TYPE' => $billableItem['REF_TYPE'],
					'REF_FK' => $billableItem['REF_FK']
                );
                $touchedBillableItemIds[] = $billableItem['ID_BILLING_BILLABLEITEM'];
            }

            if(count($invoiceItems) > 0) {
				$invoiceData = array(
                    'FK_USER' => $billableItemUser,
                    'FK_USER_SALES' => $salesUserByInvoiceUser[$billableItemUser],
					'STATUS' => BillingInvoiceManagement::STATUS_UNPAID,
					'STAMP_CREATE' => date('Y-m-d'),
					'STAMP_PAY' => null,
					'STAMP_CANCEL' => null,
					'__items' => $invoiceItems
				);

                $invoiceId = $invoiceManagement->createInvoice($invoiceData);

                if($invoiceId !== null) {
                    foreach($touchedBillableItemIds as $key => $billableItemId) {
                        $billableItemManagement->removeById($billableItemId);
                    }
                }

				$billingNotificationManagement = BillingNotificationManagement::getInstance($this->getDb());
				$billingNotificationManagement->notify(BillingNotificationManagement::EVENT_AUTOMATICBILLING_RUN_CREATE_INVOICE, $invoiceId, array('touchedBillableItemIds' => $touchedBillableItemIds, 'invoice' => $invoiceData));

           		$result[] = 'Erzeuge neue Rechnung #'.$invoiceId.' für User '.$billableItemUser.' aus abrechnungsfähigen Posten '.implode(', ', $touchedBillableItemIds);
			}
        }
        $result[] = 'Beende Rechnungslauf';

        return $result;
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