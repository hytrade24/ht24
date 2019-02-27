<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once dirname(__FILE__).'/lib.billing.invoice.php';


class BillingServiceAutomaticDunningManagement {
	private static $db;
	private static $instance = null;



	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return BillingServiceAutomaticDunningManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function runAll() {
        $billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());

        $dunningLevelDays = $this->getDunningLevels();

        $dunningInvoiceIds = array();
        foreach($dunningLevelDays as $dunningLevel => $days) {
            $dunningInvoices = $billingInvoiceManagement->fetchAllByParam(array(
                'STATUS' => 0,
                'IS_OVERDUE' => $days,
                'DUNNING_LEVEL' => ($dunningLevel - 1)
            ));

            foreach ($dunningInvoices as $key => $invoice) {
                $dunningInvoiceIds[] = $invoice['ID_BILLING_INVOICE'];
            }
        }

        return $this->run($dunningInvoiceIds);

    }

    public  function runAllOverdue() {
        $billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());

        $overdueInvoices = $billingInvoiceManagement->fetchAllByParam(array(
            'STATUS' => 0,
            'IS_OVERDUE' => 1,
            'DUNNING_LEVEL' => NULL
        ));

        foreach ($overdueInvoices as $key => $invoice) {
            $billingInvoiceManagement->setDunningLevel($invoice['ID_BILLING_INVOICE'], 0);
        }
    }

    public function run($dunningInvoiceIds) {
        $invoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());
        $result = array();
        $dunningLevelDays = $this->getDunningLevels();


        $invoices = $invoiceManagement->fetchAllByParam(array(
            'ID_BILLING_INVOICE' => $dunningInvoiceIds,
        ));


        $result[] = 'Starte Mahnlauf am '.date("Y-m-d H:i:s");

        foreach($invoices as $key => $invoice) {
            $daysAfterDueForNextDunningLevel = $dunningLevelDays[$invoice['DUNNING_LEVEL'] + 1];

            $invoiceDueDate = new DateTime($invoice['STAMP_DUE']);
            $nextDunningDate = clone $invoiceDueDate;
            $nextDunningDate->modify("+ ".$daysAfterDueForNextDunningLevel." day");

            $today = new DateTime("today");

            if($today >= $nextDunningDate) {
                $invoiceManagement->setDunningLevel($invoice['ID_BILLING_INVOICE'], $invoice['DUNNING_LEVEL'] + 1);
                $result[] = 'Mahne Rechnung Nr. '.$invoice['ID_BILLING_INVOICE'].' - Neue Mahnstufe:'.($invoice['DUNNING_LEVEL'] + 1);
            }
        }

        $result[] = 'Beende Mahnlauf';

        return $result;
    }

    private function getDunningLevels() {
        global $nar_systemsettings;

        return array(
            1 => (int)$nar_systemsettings['MARKTPLATZ']['INVOICE_DAYS_DUNNING_LEVEL_1'],
            2 => (int)$nar_systemsettings['MARKTPLATZ']['INVOICE_DAYS_DUNNING_LEVEL_2'],
            3 => (int)$nar_systemsettings['MARKTPLATZ']['INVOICE_DAYS_DUNNING_LEVEL_3']
        );
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