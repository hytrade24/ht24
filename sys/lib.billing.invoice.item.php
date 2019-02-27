<?php
/* ###VERSIONSBLOCKINLCUDE### */



class BillingInvoiceItemManagement {
	private static $db;
	private static $instance = null;

	const REF_TYPE_DEFAULT = 1;
  const REF_TYPE_PACKET = 2;
	const REF_TYPE_MEMBERSHIP = 3;
	const REF_TYPE_PROVISION = 4;
  const REF_TYPE_ADVERTISEMENT = 5;
  const REF_TYPE_COUPON = 6;



	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return BillingInvoiceItemManagement
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
        $sqlOrder = " it.ID_BILLING_INVOICE_ITEM ";
        $sqlSelect = '';

        if(isset($param['FK_BILLING_INVOICE']) && $param['FK_BILLING_INVOICE'] != null) { $sqlWhere .= " AND it.FK_BILLING_INVOICE = '".mysql_real_escape_string($param['FK_BILLING_INVOICE'])."' "; }
        if (isset($param['BILLING_CANCEL_CHECK'])) {
        	$sqlJoin .= ' LEFT JOIN billing_cancel bc ON bc.FK_BILLING_INVOICE_ITEM = it.ID_BILLING_INVOICE_ITEM ';
        	$sqlSelect .= ' bc.ID_BILLING_CANCEL, ';
        }


        $query = "
            SELECT
            	".$sqlSelect."
                it.*,
                tax.TAX_VALUE,
                tax.TXT AS TAX_NAME
            FROM
                billing_invoice_item it
            LEFT JOIN tax ON tax.ID_TAX = it.FK_TAX
            ".$sqlJoin."
            WHERE
                true
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY it.ID_BILLING_INVOICE_ITEM
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."
        ";


        $result = $db->fetch_table($query);

        foreach($result as $key => $invoice) {
            $result[$key]['TOTAL_PRICE'] = $this->getInvoiceItemTotalPrice($invoice['ID_BILLING_INVOICE_ITEM']);
            $result[$key]['TOTAL_PRICE_NET'] = $this->getInvoiceItemTotalPrice($invoice['ID_BILLING_INVOICE_ITEM'], true);
        }

        return $result;
    }

    public function fetchById($invoiceItemId) {
        return $this->getDb()->fetch1("
            SELECT
                it.*,
                tax.TAX_VALUE,
                tax.TXT AS TAX_NAME
            FROM
                billing_invoice_item it
            LEFT JOIN tax ON tax.ID_TAX = it.FK_TAX
            WHERE
                ID_BILLING_INVOICE_ITEM = '" . (int)$invoiceItemId . "'");
    }

    public function createInvoiceItem($rawData) {
        // validation
        $validationError = false;
        if(!$this->validate($rawData, true)) { $validationError = true; }

        if(!$validationError) {
            $rawData['ID_BILLING_INVOICE_ITEM'] = null;

            $this->update(null, $rawData);
            return true;
        } else {
            return false;
        }
    }

    public function deleteInvoiceItem($invoiceItemId, $keepService = false, $idBillingCancel = null) {
        $arInvoiceItem = $this->fetchById($invoiceItemId);
        $idInvoiceUser = $this->getDb()->fetch_atom("SELECT FK_USER FROM `billing_invoice` WHERE ID_BILLING_INVOICE=".(int)$arInvoiceItem["FK_BILLING_INVOICE"]);
        $arData = $arInvoiceItem;
        $arData["KEEP_PERFORMANCES"] = ($keepService ? 1 : 0);
        $billingNotificationManagement = BillingNotificationManagement::getInstance($this->getDb());
        $billingNotificationManagement->notify(BillingNotificationManagement::EVENT_INVOICE_ITEM_CANCEL, $arInvoiceItem["FK_BILLING_INVOICE"], $arData);
        $result = $this->getDb()->querynow("
            DELETE FROM `billing_invoice_item`
            WHERE ID_BILLING_INVOICE_ITEM=".(int)$invoiceItemId);
        if ($result["rsrc"]) {
          $arInvoiceItemCancel = $arInvoiceItem;
			    $arInvoiceItemCancel["CANCEL_TIME"] = date("Y-m-d H:i:s");
					$arInvoiceItemCancel["FK_USER"] = $idInvoiceUser;
					$arInvoiceItemCancel["FK_BILLING_CANCEL"] = $idBillingCancel;
					$arInvoiceItemCancel["FK_BILLING_INVOICE_ITEM"] = $arInvoiceItem["ID_BILLING_INVOICE_ITEM"];
					$idInvoiceItemCancel = $this->getDb()->update("billing_cancel_item", $arInvoiceItemCancel);
          return true;
        } else {
          return false;
        }
    }

    public function update($invoiceItemId, $rawData) {
        if($this->validate($rawData, false)) {
            $invoiceItem = array(
                'ID_BILLING_INVOICE_ITEM' => $invoiceItemId,
                'FK_BILLING_INVOICE' => $rawData['FK_BILLING_INVOICE'],
                'FK_TAX' => $rawData['FK_TAX'],
                'DESCRIPTION' => $rawData['DESCRIPTION'],
                'QUANTITY' => isset($rawData['QUANTITY'])?$rawData['QUANTITY']:1,
                'PRICE' => isset($rawData['PRICE'])?$rawData['PRICE']:0,
				'REF_TYPE' => isset($rawData['REF_TYPE'])?$rawData['REF_TYPE']:0,
				'REF_FK' => isset($rawData['REF_FK'])?$rawData['REF_FK']:NULL
            );

            return $this->getDb()->update('billing_invoice_item', $invoiceItem);
        } else {
            return false;
        }
    }

    public function updateRef($invoiceItemId, $refType, $refId) {
        $result = $this->getDb()->querynow("
            UPDATE `billing_invoice_item`
            SET REF_TYPE=".(int)$refType.", REF_FK=".(int)$refId."
            WHERE ID_BILLING_INVOICE_ITEM=".(int)$invoiceItemId);
        return $result["rsrc"];
    }

    public function updateRefBillableItem($invoiceBillableItemId, $refType, $refId) {
        $result = $this->getDb()->querynow("
            UPDATE `billing_billableitem`
            SET REF_TYPE=".(int)$refType.", REF_FK=".(int)$refId."
            WHERE ID_BILLING_BILLABLEITEM=".(int)$invoiceBillableItemId);
        return $result["rsrc"];
    }

    public function validate($invoiceItem, $isNewObject = true) {
        return true;
    }

    public function getInvoiceItemTotalPrice($invoiceItemId, $netPrice = false) {
        $invoiceItem = $this->fetchById($invoiceItemId);

        $taxModifier = (($invoiceItem['TAX_VALUE'] == null) || ($netPrice == true))?0:$invoiceItem['TAX_VALUE'];
        return ($invoiceItem['QUANTITY']*$invoiceItem['PRICE']*(1+$taxModifier/100));
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