<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once dirname(__FILE__).'/lib.billing.invoice.php';
require_once dirname(__FILE__).'/lib.billing.creditnote.php';
require_once dirname(__FILE__).'/lib.billing.notification.php';

class BillingInvoiceTransactionManagement {
	private static $db;
	private static $instance = null;

    const TYPE_DEFAULT = 0;
    const TYPE_CREDITNOTE = 1;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return BillingInvoiceTransactionManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function fetchAllByParam($param) {
        $db = $this->getDb();

        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " t.STAMP_CREATE DESC, t.ID_BILLING_INVOICE_TRANSACTION DESC ";

        if(isset($param['ID_BILLING_INVOICE_TRANSACTION']) && $param['ID_BILLING_INVOICE_TRANSACTION'] != null && !is_array($param['ID_BILLING_INVOICE_TRANSACTION'])) { $sqlWhere .= " AND t.ID_BILLING_INVOICE_TRANSACTION = '".mysql_real_escape_string($param['ID_BILLING_INVOICE_TRANSACTION'])."' "; }
        if(isset($param['FK_BILLING_INVOICE']) && $param['FK_BILLING_INVOICE'] != null) { $sqlWhere .= " AND t.FK_BILLING_INVOICE = '".mysql_real_escape_string($param['FK_BILLING_INVOICE'])."' "; }

        if(isset($param['LIMIT']) && $param['LIMIT'] != null) { if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; } }
        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }


        $query = "
            SELECT
                t.*
            FROM
                billing_invoice_transaction t
            ".$sqlJoin."
            WHERE
                true
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY t.ID_BILLING_INVOICE_TRANSACTION
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."
        ";

        $result = $db->fetch_table($query);

        return $result;
    }

    public function countByParam($param) {
        $db = $this->getDb();


        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";

        if(isset($param['ID_BILLING_INVOICE_TRANSACTION']) && $param['ID_BILLING_INVOICE_TRANSACTION'] != null && !is_array($param['ID_BILLING_INVOICE_TRANSACTION'])) { $sqlWhere .= " AND t.ID_BILLING_INVOICE_TRANSACTION = '".mysql_real_escape_string($param['ID_BILLING_INVOICE_TRANSACTION'])."' "; }
        if(isset($param['FK_BILLING_INVOICE']) && $param['FK_BILLING_INVOICE'] != null) { $sqlWhere .= " AND t.FK_BILLING_INVOICE = '".mysql_real_escape_string($param['FK_BILLING_INVOICE'])."' "; }
        if(isset($param['TRANSACTION_ID']) && $param['TRANSACTION_ID'] != null) { $sqlWhere .= " AND t.TRANSACTION_ID = '".mysql_real_escape_string($param['TRANSACTION_ID'])."' "; }

        $query = ("
            SELECT
                SQL_CALC_FOUND_ROWS t.ID_BILLING_INVOICE_TRANSACTION
            FROM
                   billing_invoice_transaction t
               ".$sqlJoin."
               WHERE
                   true
                   ".($sqlWhere?' '.$sqlWhere:'')."
               GROUP BY t.ID_BILLING_INVOICE_TRANSACTION
        ");

        $result = $db->querynow($query);
        $count = $db->fetch_atom("SELECT FOUND_ROWS()");

        return $count;
    }

    public function fetchById($transactionId) {
        $transaction = $this->getDb()->fetch1("SELECT * FROM billing_invoice_transaction WHERE ID_BILLING_INVOICE_TRANSACTION = '" . (int)$transactionId . "'");
        return $transaction;
    }

    public function createInvoiceTransaction($rawData) {
        $billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());

        // validation
        $validationError = false;
        if(!$this->validate($rawData, true)) { $validationError = true; }

        if(!$validationError) {

            if(!isset($rawData['TYPE'])) { $rawData['TYPE'] = self::TYPE_DEFAULT; }
            if(!isset($rawData['STAMP_CREATE'])) { $rawData['STAMP_CREATE'] = date("Y-m-d"); }

            $invoice = $billingInvoiceManagement->fetchById($rawData['FK_BILLING_INVOICE']);

            if($invoice !== null) {
				$differenceAmount = $invoice['REMAINING_PRICE'] - $rawData['PRICE'];
				if(($differenceAmount > -0.01) && ($invoice['STATUS'] == BillingInvoiceManagement::STATUS_UNPAID)) {

                    $transactionId = $this->getDb()->update('billing_invoice_transaction', $rawData);
                    $transaction = $this->fetchById($transactionId);

					if($differenceAmount < 0.01) {
                        $billingInvoiceManagement->setStatus($invoice['ID_BILLING_INVOICE'], BillingInvoiceManagement::STATUS_PAID);
                    }

                    $billingNotificationManagement = BillingNotificationManagement::getInstance($this->getDb());
                    $billingNotificationManagement->notify(BillingNotificationManagement::EVENT_INVOICE_TRANSACTION_CREATE, $invoice['ID_BILLING_INVOICE'], array('TRANSACTION' => $transaction));

                    return $transactionId;
                }
            }
        }

        return null;
    }

    public function applyCreditnote($data) {
        $billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());
        $billingCreditnoteManagement = BillingCreditnoteManagement::getInstance($this->getDb());

        // validation
        $validationError = false;
        if(!isset($data['FK_BILLING_INVOICE'])) { $validationError = true; }
        if(!isset($data['FK_BILLING_CREDITNOTE'])) { $validationError = true; }

        if(!$validationError) {
            $invoice = $billingInvoiceManagement->fetchById($data['FK_BILLING_INVOICE']);
            $creditnote = $billingCreditnoteManagement->fetchById($data['FK_BILLING_CREDITNOTE']);

            if($invoice !== null && $creditnote !== null && ($creditnote['FK_USER'] == $invoice['FK_USER'])) {
                if(($invoice['STATUS'] == BillingInvoiceManagement::STATUS_UNPAID) && $creditnote['STATUS'] == BillingCreditnoteManagement::STATUS_ACTIVE) {
                    $transactionAmount = min($invoice['REMAINING_PRICE'], $creditnote['REMAINING_PRICE']);

                    $transactionId = $this->createInvoiceTransaction(array(
                        'FK_BILLING_INVOICE' => $invoice['ID_BILLING_INVOICE'],
                        'TYPE' => BillingInvoiceTransactionManagement::TYPE_CREDITNOTE,
                        'DESCRIPTION' => $creditnote['DESCRIPTION'],
                        'TRANSACTION_ID' => $creditnote['ID_BILLING_CREDITNOTE'],
                        'PRICE' => $transactionAmount
                    ));

                    if($transactionId != null) {
                        $transaction = $this->fetchById($transactionId);

                        $creditNotePriceReduction = $transactionAmount/(1+($creditnote['TAX_VALUE']/100));
                        $billingCreditnoteManagement->reducePrice($creditnote['ID_BILLING_CREDITNOTE'], $creditNotePriceReduction);

                        $billingNotificationManagement = BillingNotificationManagement::getInstance($this->getDb());
                        $billingNotificationManagement->notify(BillingNotificationManagement::EVENT_INVOICE_TRANSACTION_APPLY_CREDIT, $invoice['ID_BILLING_INVOICE'], array('TRANSACTION' => $transaction));

                    }
                }
            }
        }
    }

    public function getAmountPaidByInvoiceId($invoiceId) {
        $db = $this->getDb();
        return (float)$db->fetch_atom("SELECT SUM(PRICE) FROM billing_invoice_transaction WHERE FK_BILLING_INVOICE = '".(int)$invoiceId."' GROUP BY FK_BILLING_INVOICE");
    }

    public function deleteById($id) {
        $db = $this->getDb();
        $db->querynow("DELETE FROM billing_invoice_transaction WHERE ID_BILLING_INVOICE_TRANSACTION = '" . mysql_real_escape_string($id) . "'");

        return true;
    }

    public function validate($transaction, $isNewObject = true) {
        if(!isset($transaction['FK_BILLING_INVOICE']) || $transaction['FK_BILLING_INVOICE'] == null) { return false; }
        if(!isset($transaction['PRICE']) || $transaction['PRICE'] <= 0) { return false; }

        return true;
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