<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once dirname(__FILE__).'/lib.billing.creditnote.php';
require_once dirname(__FILE__).'/lib.billing.invoice.item.php';
require_once dirname(__FILE__).'/lib.billing.invoice.transaction.php';
require_once dirname(__FILE__).'/lib.billing.notification.php';
require_once dirname(__FILE__).'/lib.user.php';
require_once dirname(__FILE__).'/lib.payment.adapter.php';
require_once dirname(__FILE__).'/payment/PaymentFactory.php';
require_once dirname(__FILE__).'/lib.billing.invoice.taxexempt.php';

class BillingInvoiceManagement {
	private static $db;
	private static $instance = null;

    const STATUS_UNPAID = 0;
    const STATUS_PAID = 1;
    const STATUS_CANCELED = 2;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return BillingInvoiceManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function exportedSEPAXml() {
        $db = $this->getDb();

        $query = 'SELECT a.*, UNIX_TIMESTAMP(a.STAMP) as fileName
        FROM billing_invoice_export a
        ORDER BY a.STAMP DESC
        LIMIT 20';

        $resultSet = $db->fetch_table( $query );

        if ( is_array($resultSet) ) {
            return $resultSet;
        }
    }

    public function fetchAllByParam($param) {
        global $langval;
        $db = $this->getDb();

        $language = ($param['BF_LANG'] > 0 ? $param['BF_LANG'] : $langval);
        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " i.STAMP_CREATE DESC, i.ID_BILLING_INVOICE DESC ";
        $sqlSelect = '';

        if(isset($param['ID_BILLING_INVOICE']) && $param['ID_BILLING_INVOICE'] != null && !is_array($param['ID_BILLING_INVOICE'])) { $sqlWhere .= " AND i.ID_BILLING_INVOICE = '".mysql_real_escape_string($param['ID_BILLING_INVOICE'])."' "; }
        if(isset($param['ID_BILLING_INVOICE']) && $param['ID_BILLING_INVOICE'] !== null && is_array($param['ID_BILLING_INVOICE'])) { if(count($param['ID_BILLING_INVOICE']) > 0) { $sqlWhere .= " AND i.ID_BILLING_INVOICE IN (".implode(',', $param['ID_BILLING_INVOICE']).") "; } else { $sqlWhere .= "AND false"; } }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null) { $sqlWhere .= " AND i.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(isset($param['FK_PAYMENT_ADAPTER']) && $param['FK_PAYMENT_ADAPTER'] != null) { $sqlWhere .= " AND i.FK_PAYMENT_ADAPTER = '".mysql_real_escape_string($param['FK_PAYMENT_ADAPTER'])."' "; }
        if(isset($param['STATUS']) && $param['STATUS'] !== null) { $sqlWhere .= " AND i.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }
        if(isset($param['STAMP_CREATE_AFTER']) && $param['STAMP_CREATE_AFTER'] != null) { $sqlWhere .= " AND i.STAMP_CREATE >= DATE_SUB(NOW(), INTERVAL ".mysql_real_escape_string($param['STAMP_CREATE_AFTER']).") "; }
        if(isset($param['STAMP_CREATE_FROM']) && $param['STAMP_CREATE_FROM'] != null) { $sqlWhere .= " AND i.STAMP_CREATE >= STR_TO_DATE('".mysql_real_escape_string($param['STAMP_CREATE_FROM'])."', '%d.%m.%Y') "; }
        if(isset($param['STAMP_CREATE_TO']) && $param['STAMP_CREATE_TO'] != null) { $sqlWhere .= " AND i.STAMP_CREATE <= STR_TO_DATE('".mysql_real_escape_string($param['STAMP_CREATE_TO'])."', '%d.%m.%Y') "; }
        if(isset($param['IS_OVERDUE']) && $param['IS_OVERDUE'] !== null) { if($param['IS_OVERDUE'] === true) { $param['IS_OVERDUE'] = 1; } $sqlWhere .= " AND NOW() >= DATE_ADD(i.STAMP_DUE, INTERVAL ".mysql_real_escape_string($param['IS_OVERDUE'])." DAY) "; }
        if(isset($param['DUNNING_LEVEL']) && $param['DUNNING_LEVEL'] !== null) { $sqlWhere .= " AND i.DUNNING_LEVEL = '".mysql_real_escape_string($param['DUNNING_LEVEL'])."' "; }
        if(array_key_exists('DUNNING_LEVEL', $param) && $param['DUNNING_LEVEL'] === null) { $sqlWhere .= " AND i.DUNNING_LEVEL IS NULL "; }
		    if (array_key_exists('FK_BILLING_INVOICE_EXPORT', $param) && ($param['FK_BILLING_INVOICE_EXPORT'] !== "")) { $sqlWhere .= " AND i.FK_BILLING_INVOICE_EXPORT=".(int)$param['FK_BILLING_INVOICE_EXPORT']; }

        if(isset($param['LIMIT']) && $param['LIMIT'] != null) { if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; } }
        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }

        if (isset($param['BILLING_CANCEL_CHECK'])) {
        	$sqlSelect .= 'bc.ID_BILLING_CANCEL, ';
        	$sqlJoin .= ' LEFT JOIN billing_invoice_item bii
							ON bii.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
							LEFT JOIN billing_cancel bc
							ON bc.FK_BILLING_INVOICE_ITEM = bii.ID_BILLING_INVOICE_ITEM ';
        }

        $query = "
            SELECT
            	".$sqlSelect."
                i.*,
                IF(i.STAMP_DUE < CURDATE(), 1, 0) AS IS_OVERDUE,
                DATEDIFF(i.STAMP_DUE, NOW()) as DUE_DAYS,
                pas.V1 as PAYMENT_ADAPTER_NAME
            FROM
                billing_invoice i
            LEFT JOIN payment_adapter pa ON pa.ID_PAYMENT_ADAPTER = i.FK_PAYMENT_ADAPTER
			      LEFT JOIN string_payment_adapter pas ON pas.S_TABLE='payment_adapter' AND pas.FK=pa.ID_PAYMENT_ADAPTER
                AND pas.BF_LANG=if(pa.BF_LANG_PAYMENT_ADAPTER & " . $language . ", " . $language . ", 1 << floor(log(pa.BF_LANG_PAYMENT_ADAPTER+0.5)/log(2)))
            ".$sqlJoin."
            WHERE
                true
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY i.ID_BILLING_INVOICE
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."
        ";

        $result = $db->fetch_table($query);

        foreach($result as $key => $invoice) {
            $result[$key]['TOTAL_PRICE'] = $this->getInvoiceTotalPrice($invoice['ID_BILLING_INVOICE']);
            $result[$key]['TOTAL_PRICE_NET'] = $this->getInvoiceTotalPrice($invoice['ID_BILLING_INVOICE'], true);
            $result[$key]['PAID_PRICE'] = $this->getInvoicePaidPrice($result[$key]['ID_BILLING_INVOICE']);
            $result[$key]['REMAINING_PRICE'] = $result[$key]['TOTAL_PRICE'] - $result[$key]['PAID_PRICE'];

        }

        return $result;
    }

    public function countByParam($param) {
        $db = $this->getDb();


        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " i.STAMP_CREATE ";

        if(isset($param['ID_BILLING_INVOICE']) && $param['ID_BILLING_INVOICE'] != null && !is_array($param['ID_BILLING_INVOICE'])) { $sqlWhere .= " AND i.ID_BILLING_INVOICE = '".mysql_real_escape_string($param['ID_BILLING_INVOICE'])."' "; }
        if(isset($param['ID_BILLING_INVOICE']) && $param['ID_BILLING_INVOICE'] !== null && is_array($param['ID_BILLING_INVOICE'])) { if(count($param['ID_BILLING_INVOICE']) > 0) { $sqlWhere .= " AND i.ID_BILLING_INVOICE IN (".implode(',', $param['ID_BILLING_INVOICE']).") "; } else { $sqlWhere .= "AND false"; } }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null) { $sqlWhere .= " AND i.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
		    if(isset($param['FK_PAYMENT_ADAPTER']) && $param['FK_PAYMENT_ADAPTER'] != null) { $sqlWhere .= " AND i.FK_PAYMENT_ADAPTER = '".mysql_real_escape_string($param['FK_PAYMENT_ADAPTER'])."' "; }
        if(isset($param['STATUS']) && $param['STATUS'] !== null) { $sqlWhere .= " AND i.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }
        if(isset($param['STAMP_CREATE_AFTER']) && $param['STAMP_CREATE_AFTER'] != null) { $sqlWhere .= " AND i.STAMP_CREATE >= DATE_SUB(NOW(), INTERVAL ".mysql_real_escape_string($param['STAMP_CREATE_AFTER']).") "; }
		    if(isset($param['STAMP_CREATE_FROM']) && $param['STAMP_CREATE_FROM'] != null) { $sqlWhere .= " AND i.STAMP_CREATE >= STR_TO_DATE('".mysql_real_escape_string($param['STAMP_CREATE_FROM'])."', '%d.%m.%Y') "; }
		    if(isset($param['STAMP_CREATE_TO']) && $param['STAMP_CREATE_TO'] != null) { $sqlWhere .= " AND i.STAMP_CREATE <= STR_TO_DATE('".mysql_real_escape_string($param['STAMP_CREATE_TO'])."', '%d.%m.%Y') "; }
        if(isset($param['IS_OVERDUE']) && $param['IS_OVERDUE'] !== null) { if($param['IS_OVERDUE'] === true) { $param['IS_OVERDUE'] = 1; } $sqlWhere .= " AND i.STAMP_DUE <= DATE_ADD(NOW(), INTERVAL ".mysql_real_escape_string($param['IS_OVERDUE']).") "; }
        if(isset($param['DUNNING_LEVEL']) && $param['DUNNING_LEVEL'] !== null) { $sqlWhere .= " AND i.DUNNING_LEVEL = '".mysql_real_escape_string($param['DUNNING_LEVEL'])."' "; }
        if(isset($param['DUNNING_LEVEL']) && $param['DUNNING_LEVEL'] === null) { $sqlWhere .= " AND i.DUNNING_LEVEL IS NULL "; }
		    if (array_key_exists('FK_BILLING_INVOICE_EXPORT', $param) && ($param['FK_BILLING_INVOICE_EXPORT'] !== "")) { $sqlWhere .= " AND i.FK_BILLING_INVOICE_EXPORT=".(int)$param['FK_BILLING_INVOICE_EXPORT']; }

        $query = ("
            SELECT
                SQL_CALC_FOUND_ROWS i.ID_BILLING_INVOICE
            FROM
                billing_invoice i
            LEFT JOIN payment_adapter pa ON pa.ID_PAYMENT_ADAPTER = i.FK_PAYMENT_ADAPTER
            ".$sqlJoin."
            WHERE
                1 = 1
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY i.ID_BILLING_INVOICE
        ");

        $result = $db->querynow($query);
        $count = $db->fetch_atom("SELECT FOUND_ROWS()");

        return $count;
    }

	public function fetchAllInvoiceIdsByParam($param) {
		$db = $this->getDb();


		$sqlLimit = "";
		$sqlWhere = " ";
		$sqlJoin = "";
		$sqlOrder = " i.STAMP_CREATE ";

		if(isset($param['ID_BILLING_INVOICE']) && $param['ID_BILLING_INVOICE'] != null && !is_array($param['ID_BILLING_INVOICE'])) { $sqlWhere .= " AND i.ID_BILLING_INVOICE = '".mysql_real_escape_string($param['ID_BILLING_INVOICE'])."' "; }
		if(isset($param['ID_BILLING_INVOICE']) && $param['ID_BILLING_INVOICE'] !== null && is_array($param['ID_BILLING_INVOICE'])) { if(count($param['ID_BILLING_INVOICE']) > 0) { $sqlWhere .= " AND i.ID_BILLING_INVOICE IN (".implode(',', $param['ID_BILLING_INVOICE']).") "; } else { $sqlWhere .= "AND false"; } }
		if(isset($param['FK_USER']) && $param['FK_USER'] != null) { $sqlWhere .= " AND i.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
		if(isset($param['STATUS']) && $param['STATUS'] !== null) { $sqlWhere .= " AND i.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }
		if(isset($param['STAMP_CREATE_AFTER']) && $param['STAMP_CREATE_AFTER'] != null) { $sqlWhere .= " AND i.STAMP_CREATE >= DATE_SUB(NOW(), INTERVAL ".mysql_real_escape_string($param['STAMP_CREATE_AFTER']).") "; }
		if(isset($param['STAMP_CREATE_FROM']) && $param['STAMP_CREATE_FROM'] != null) { $sqlWhere .= " AND i.STAMP_CREATE >= STR_TO_DATE('".mysql_real_escape_string($param['STAMP_CREATE_FROM'])."', '%d.%m.%Y') "; }
		if(isset($param['STAMP_CREATE_TO']) && $param['STAMP_CREATE_TO'] != null) { $sqlWhere .= " AND i.STAMP_CREATE <= STR_TO_DATE('".mysql_real_escape_string($param['STAMP_CREATE_TO'])."', '%d.%m.%Y') "; }
		if(isset($param['IS_OVERDUE']) && $param['IS_OVERDUE'] !== null) { if($param['IS_OVERDUE'] === true) { $param['IS_OVERDUE'] = 1; } $sqlWhere .= " AND i.STAMP_DUE <= DATE_ADD(NOW(), INTERVAL ".mysql_real_escape_string($param['IS_OVERDUE']).") "; }
		if(isset($param['DUNNING_LEVEL']) && $param['DUNNING_LEVEL'] !== null) { $sqlWhere .= " AND i.DUNNING_LEVEL = '".mysql_real_escape_string($param['DUNNING_LEVEL'])."' "; }
		if(isset($param['DUNNING_LEVEL']) && $param['DUNNING_LEVEL'] === null) { $sqlWhere .= " AND i.DUNNING_LEVEL IS NULL "; }
    if (array_key_exists('FK_BILLING_INVOICE_EXPORT', $param) && ($param['FK_BILLING_INVOICE_EXPORT'] !== "")) { $sqlWhere .= " AND i.FK_BILLING_INVOICE_EXPORT=".(int)$param['FK_BILLING_INVOICE_EXPORT']; }

		$query = ("
			SELECT
				i.ID_BILLING_INVOICE, i.ID_BILLING_INVOICE
			FROM
				billing_invoice i

			".$sqlJoin."
			WHERE
				1 = 1
				".($sqlWhere?' '.$sqlWhere:'')."
			GROUP BY i.ID_BILLING_INVOICE
		");

		$result = $db->fetch_nar($query);

		return array_values($result);
	}

    public function fetchById($invoiceId) {
        $invoice = $this->getDb()->fetch1("SELECT * FROM billing_invoice WHERE ID_BILLING_INVOICE = '" . (int)$invoiceId . "'");
        if ($invoice !== false) {
	        $invoice['TOTAL_PRICE'] = $this->getInvoiceTotalPrice($invoice['ID_BILLING_INVOICE']);
        	$invoice['TOTAL_PRICE_NET'] = $this->getInvoiceTotalPrice($invoice['ID_BILLING_INVOICE'], true);
        	$invoice['PAID_PRICE'] = $this->getInvoicePaidPrice($invoiceId);
        	$invoice['REMAINING_PRICE'] = $invoice['TOTAL_PRICE'] - $invoice['PAID_PRICE'];
        }

        return $invoice;
    }

    public function fetchByTransactionId($transactionId) {
        $invoice = $this->getDb()->fetch1("SELECT * FROM billing_invoice WHERE TRANSACTION_ID = '" . mysql_real_escape_string($transactionId) . "'");
        if ($invoice !== false) {
        	$invoice['TOTAL_PRICE'] = $this->getInvoiceTotalPrice($invoice['ID_BILLING_INVOICE']);
        	$invoice['TOTAL_PRICE_NET'] = $this->getInvoiceTotalPrice($invoice['ID_BILLING_INVOICE'], true);
        	$invoice['PAID_PRICE'] = $this->getInvoicePaidPrice($invoice['ID_BILLING_INVOICE']);
        	$invoice['REMAINING_PRICE'] = $invoice['TOTAL_PRICE'] - $invoice['PAID_PRICE'];
        }

        return $invoice;
    }

    public function createInvoice($rawData) {
        $invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->getDb());

        if(count($rawData['__items']) < 1) { return false; }

        // validation
        $validationError = false;
        if(count($rawData['__items']) < 1) { $validationError = true; }
        if(!$this->validate($rawData, true)) { $validationError = true; }
        foreach($rawData['__items'] as $key => $item) {
            if(!$invoiceItemManagement->validate($item)) {
                $validationError = true;
            }
        }

        if(!$validationError) {
            $rawData['ID_BILLING_INVOICE'] = null;
			      $rawData['TAX_EXEMPT'] = 0;
            $rawData['ADDRESS'] = $this->_getInvoiceAddressByUserId($rawData['FK_USER']);

            if(!isset($rawData['STATUS'])) { $rawData['STATUS'] = self::STATUS_UNPAID; }
            if(!isset($rawData['STAMP_CREATE'])) { $rawData['STAMP_CREATE'] = date("Y-m-d"); }
            if(!isset($rawData['FK_PAYMENT_ADAPTER']) || ($rawData['FK_PAYMENT_ADAPTER'] == null)) { $rawData['FK_PAYMENT_ADAPTER'] = $this->_getDefaultPaymentAdapter($rawData); }
            if(!isset($rawData['STAMP_DUE']) || ($rawData['STAMP_DUE'] == null)) { $rawData['STAMP_DUE'] = $this->_getDefaultDueDate($rawData); }

            if($this->isInvoiceTaxExempt($rawData['FK_USER'])) {
                $rawData = $this->applyTaxExemptRules($rawData);
            }
            $rawData = $this->_applyCreditnotesBeforeCreate($rawData);
            $rawData = $this->_eventBeforeCreate($rawData);

	        $invoiceId = $this->update(null, $rawData);

            if($invoiceId !== NULL) {
                foreach($rawData['__items'] as $key => $item) {
                    $item['FK_BILLING_INVOICE'] = $invoiceId;
                    $invoiceItemManagement->createInvoiceItem($item);
                }
            }

            $billingNotificationManagement = BillingNotificationManagement::getInstance($this->getDb());
            $billingNotificationManagement->notify(BillingNotificationManagement::EVENT_INVOICE_CREATE, $invoiceId);

            if ($rawData["STATUS"] == self::STATUS_PAID) {
                $billingNotificationManagement->notify(BillingNotificationManagement::EVENT_INVOICE_PAY, $invoiceId);
            }
            return $invoiceId;
        } else {
            return null;
        }

    }

    protected function getCachePath() {
        return $GLOBALS['ab_path']."filestorage/invoice";
    }

    public function getCachePdfFilename($invoiceId, $absolute = false, $correction = false, $storno = false) {
        $invoiceType = ($storno ? "_storno" : ($correction ? "_correction" : ""));
        return ($absolute ? $this->getCachePath()."/" : "")."invoice_".$invoiceId.$invoiceType.".pdf";
    }

    /**
     * Cache the given invoice to disk and return its path
     * @param $invoiceId
     * @param bool $recache
     */
    public function getCachePdfFile($invoiceId, $recache = false, $userId = NULL) {
        if ($userId == NULL) {
            $userId = $GLOBALS['uid'];
        }
        $arInvoice = $this->getDb()->fetch1("SELECT FK_USER, STATUS, STAMP_CORRECTION FROM `billing_invoice` WHERE ID_BILLING_INVOICE=".(int)$invoiceId);
        $userIdOwner = $arInvoice["FK_USER"];
        if (($userId > 0) && ($userId != $userIdOwner)) {
            die("invoice not found..");
        }
        $isCorrection = ($arInvoice["STAMP_CORRECTION"] !== null);
        $isStorno = ($arInvoice["STATUS"] == 2);
        $downloadFilename = $this->getCachePdfFilename($invoiceId, false, $isCorrection, $isStorno);
        $downloadFileFull = $this->getCachePath()."/".$downloadFilename;
        if ($recache || !file_exists($downloadFileFull)) {
            $this->saveAsPdf($invoiceId, $downloadFileFull, $recache, $userId);
        }
        return $downloadFileFull;
    }

    protected function renderHtmlContent($invoiceId, $userId = NULL, $s_lang = null) {
        if ($userId == NULL) {
            $userId = $GLOBALS['uid'];
        }
        // Initialize / get classes
        $billingInvoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->getDb());
        $paymentAdapterManagement = PaymentAdapterManagement::getInstance($this->getDb());
        $userManagement = UserManagement::getInstance($this->getDb());
        // Get invoice
        $invoice = $this->fetchById($invoiceId);
        $invoiceItems = $billingInvoiceItemManagement->fetchAllByParam(array('FK_BILLING_INVOICE' => $invoiceId));
        $invoicePaymentAdapter = $paymentAdapterManagement->fetchById($invoice['FK_PAYMENT_ADAPTER']);

        // use default adapter
        if ($invoicePaymentAdapter['STATUS'] == PaymentAdapterManagement::STATUS_DISABLED) {
            $invoicePaymentAdapter = $paymentAdapterManagement->fetchById($GLOBALS['nar_systemsettings']['MARKTPLATZ']['INVOICE_STD_PAYMENT_ADAPTER']);
        }

        if ($invoicePaymentAdapter['STATUS'] == PaymentAdapterManagement::STATUS_DISABLED) {
            $invalidPaymentAdapter = true;
        }

        if($invoice == null) {
            die("invoice not found..");
        }
        if (($userId > 0) && !$GLOBALS['nar_pageallow']['admin/'] && ($invoice['FK_USER'] != $userId)) {
            die("invoice not found..");
        }

        $taxes = array();
        foreach($invoiceItems as $key => $invoiceItem) {
            $invoiceItems[$key]['POS'] = ($key + 1);

            if(array_key_exists($invoiceItem['TAX_VALUE'], $taxes)) {
                $taxes[$invoiceItem['TAX_VALUE']]['TAX_AMOUNT'] += ($invoiceItem['TOTAL_PRICE'] - $invoiceItem['TOTAL_PRICE_NET']);
            } else {
                $taxes[$invoiceItem['TAX_VALUE']] = array();
                $taxes[$invoiceItem['TAX_VALUE']]['TAX_AMOUNT'] = ($invoiceItem['TOTAL_PRICE'] - $invoiceItem['TOTAL_PRICE_NET']);
                $taxes[$invoiceItem['TAX_VALUE']]['TAX_VALUE'] = $invoiceItem['TAX_VALUE'];
            }
        }

        $invoiceUser = $userManagement->fetchById($invoice['FK_USER']);
        // Get language
        if ($s_lang == null) {
            $s_lang = $GLOBALS["s_lang"];
            $userLang = $GLOBALS['db']->fetch_atom("SELECT FK_LANG FROM `user` WHERE ID_USER=".$invoice['FK_USER']);
            if ($userLang > 0) {
                foreach ($GLOBALS["lang_list"] as $langAbbr => $arLang) {
                    if ($arLang["ID_LANG"] == $userLang) {
                        $s_lang = $langAbbr;
                        break;
                    }
                }
            }
        }
        // Create templates
        $tpl_print = new Template($GLOBALS['ab_path'].'tpl/'.$s_lang.'/invoice.page.htm');

        $tpl_print->addvars($invoice, 'INVOICE_');
        if (is_array($invoiceUser)) {
            $tpl_print->addvars($invoiceUser, 'INVOICE_USER_');
        }
        $tpl_print->addlist('INVOICE_ITEMS', $invoiceItems, $GLOBALS['ab_path'].'tpl/'.$s_lang.'/invoice.page.item.row.htm');
        $tpl_print->addlist('INVOICE_TAXES', $taxes, $GLOBALS['ab_path'].'tpl/'.$s_lang.'/invoice.page.tax.row.htm');

        if (!$invalidPaymentAdapter) {
            $tpl_print->addvars($invoicePaymentAdapter, 'INVOICE_PAYMENT_ADAPTER_');
        }

        $tpl_print->addvar('INVALID_PAYMENT_ADAPTER', $invalidPaymentAdapter);
        $tpl_print->addvar("CURRENCY_DEFAULT", $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY']);

        return $tpl_print->process();
    }

    /**
     * Renders the given invoice(s) as html
     * @param int|array $invoiceId
     * @return string
     */
    public function renderHtml($invoiceId, $userId = NULL) {
        $ar_invoices = array();
        if (is_array($invoiceId)) {
            foreach ($invoiceId as $id_cur) {
                if (!empty($ar_invoices)) {
                    $ar_invoices[] = '<p style="page-break-before: always">&nbsp;</p>';
                }
                $ar_invoices[] = $this->renderHtmlContent( (int)$id_cur, $userId );
            }
        } else {
            $ar_invoices[] = $this->renderHtmlContent( (int)$invoiceId, $userId );
        }
        $tpl_html = new Template($GLOBALS['ab_path'].'tpl/'.$GLOBALS['s_lang'].'/invoice.page.skin.htm');
        $tpl_html->addvar("PAGES", implode('', $ar_invoices));
        $html = $tpl_html->process();
        $html = preg_replace('/src=[\"\']\/(.+)[\"\']/i', 'src="'.$GLOBALS['ab_path'].'$1"', $html);
        return $html;
    }

    protected function renderPdfObject($invoiceId, $userId = NULL) {
    	global $ab_path;
        include $ab_path . "sys/dompdf/dompdf_config.inc.php";

        $dompdf = new DOMPDF();
        $dompdf->load_html( $this->renderHtml( $invoiceId, $userId ) );
        $dompdf->render();
        return $dompdf;
    }

    public function renderPdf($invoiceId, $userId = NULL) {
        return $this->renderPdfObject($invoiceId, $userId)->output();
    }

    public function update($invoiceId, $rawData) {
        if($this->validate($rawData, false)) {
            $rawData['ID_BILLING_INVOICE'] = $invoiceId;

            return $this->getDb()->update('billing_invoice', $rawData);
        } else {
            return null;
        }
    }
    
    public function addItem($invoiceId, $description, $price, $quantity = 1, $taxId = null, $refType = 0, $refId = null) {
        if ($taxId === null) {
            $taxId = $GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["TAX_DEFAULT"];
        }
        $invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->getDb());
        return $invoiceItemManagement->createInvoiceItem(array(
            "FK_BILLING_INVOICE"    => $invoiceId,
            "FK_TAX"                => $taxId,
            "DESCRIPTION"           => $description,
            "QUANTITY"              => $quantity,
            "PRICE"                 => $price,
            "REF_TYPE"              => $refType,
            "REF_FK"                => $refId
        ));
    }
    
    public function deleteItem($invoiceItemId, $keepService = false, $idBillingCancel = null ) {
        $invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->getDb());
        return $invoiceItemManagement->deleteInvoiceItem($invoiceItemId, $keepService, $idBillingCancel);
    }

    public function updateItem($invoiceItemId, $itemData) {
        $itemData["ID_BILLING_INVOICE_ITEM"] = $invoiceItemId;
        return $this->getDb()->update('billing_invoice_item', $itemData);
    }

    /**
     * Save the given invoice(s) as html
     * @param int|array $invoiceId
     * @param string    $filename
     * @return string
     */
    public function saveAsHtml($invoiceId, $filename, $overwrite = true, $userId = NULL) {
        if (!$overwrite && file_exists($filename)) {
            return true;
        }
        return @file_put_contents($filename, $this->renderHtml( $invoiceId, $userId ));
    }

    /**
     * Save the given invoice(s) as pdf
     * @param int|array $invoiceId
     * @param string    $filename
     * @param bool      $overwrite
     * @param int       $userId     User-ID used for permission checking
     * @return string
     */
    public function saveAsPdf($invoiceId, $filename = null, $overwrite = true, $userId = NULL) {
        if ($filename === null) {
            if (is_array($invoiceId)) {
                // Multiple invoices
                return false;
            } else {
                // Single invoice, use cache file
                $arInvoice = $this->getDb()->fetch1("SELECT STATUS, STAMP_CORRECTION FROM `billing_invoice` WHERE ID_BILLING_INVOICE=".(int)$invoiceId);
	            echo '<pre>';
	            var_dump($invoiceId, $userId);
	            echo '</pre>';
                $isCorrection = ($arInvoice["STAMP_CORRECTION"] !== null);
                $isStorno = ($arInvoice["STATUS"] == 2);
                $filename = $this->getCachePdfFilename($invoiceId, true, $isCorrection, $isStorno);
            }
        }
        if (!$overwrite && file_exists($filename)) {
            return true;
        }
        return @file_put_contents($filename, $this->renderPdf( $invoiceId, $userId ));
    }

    /**
     * Stream the given invoice(s) to browser as pdf
     * @param int|array $invoiceId
     * @param string    $downloadFilename
     * @return string
     */
    public function streamAsPdf($invoiceId, $downloadFilename = null, $recache = false, $checkUser = true) {
        // Disable caching/gzipping
        while (ob_end_clean()) {  
            // do nothing   
        }
        header('Content-Encoding: identity');
        // Stream invoice pdf
        $userId = ($checkUser ? $GLOBALS['uid'] : -1);
        if ($downloadFilename === null) {
            if (is_array($invoiceId)) {
                $downloadFilename = "invoices.pdf";
            } else if ($GLOBALS['nar_systemsettings']['MARKTPLATZ']['INVOICE_SAVE_PDF']) {
                $downloadFileFull = $this->getCachePdfFile($invoiceId, $recache, $userId);
                $downloadFilename = pathinfo($downloadFileFull, PATHINFO_BASENAME);
                header("Content-Type: application/pdf");
                header("Content-Length: " . filesize($downloadFileFull));
                header("Content-Disposition: attachment; filename=\"".$downloadFilename."\"");
                readfile($downloadFileFull);
                return true;
            }
        }
        // Stream regular on demand
        return $this->renderPdfObject($invoiceId, $userId)->stream($downloadFilename);
    }

    /**
     * Stream the given invoice(s) to browser as csv file
     * @param int|array $invoiceId
     * @param string    $downloadFilename
     * @param bool      $recache            Force rendering the invoice again
     * @return string
     */
    public function streamAsXML($invoices, $downloadFilename = "invoices.csv", $recache = false, $checkUser = true) {

        header('Content-Type: text/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename='.$downloadFilename);

        // create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');

        fclose();
    }

    /**
     * Stream the given invoice(s) to browser as csv file
     * @param int|array $invoiceId
     * @param string    $downloadFilename
     * @param bool      $recache            Force rendering the invoice again
     * @return string
     */
    public function streamAsCSV($invoices, $downloadFilename = "invoices.csv", $recache = false, $checkUser = true) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename='.$downloadFilename);

        // create a file pointer connected to the output stream
        //if ( file_exists() )
        $output = fopen('php://output', 'w');

        // output the column headings
        fputcsv($output, array(
                'Id billing invoicce',
                'stamp due',
                'Due days',
                'Status',
                'Username',
                'Id user',
                'Rechnungsempfänger',
                'Payment method',
                'Netto',
                'Brutto',
                'Offen',
                'IBAN',
                'BIC'
            )
        );

        // loop over the rows, outputting them
        foreach ( $invoices as $invoice ) {
            $temp = array(
                $invoice["ID_BILLING_INVOICE"],
                $invoice["STAMP_DUE"],
                $invoice["DUE_DAYS"],
                $invoice["STATUS"] == "0" ? "unbezahlt": ($invoice["STATUS"] == "1" ? "bezahlt" : "storniert"),
                !empty($invoice["USER_NAME"]) ? $invoice["USER_NAME"] : "Benutzer gelöscht",
                $invoice["FK_USER"],
                $invoice["ADDRESS"],
                $invoice["PAYMENT_ADAPTER_NAME"],
                sprintf("%0.2f",(double)$invoice["TOTAL_PRICE_NET"]),
                sprintf("%0.2f",(double)$invoice["TOTAL_PRICE"]),
                $invoice["STATUS"] == "0" ? sprintf("%0.2f",(double)$invoice["REMAINING_PRICE"]) : "0.00",
                $invoice["IBAN"],
                $invoice["BIC"]
            );
            fputcsv($output, $temp);
        }
        fclose();
    }

    /**
     * Stream the given invoice(s) to browser as zipped pdf file(s)
     * @param int|array $invoiceId
     * @param string    $downloadFilename
     * @param bool      $recache            Force rendering the invoice again
     * @return string
     */
    public function streamAsZippedPdfs($invoiceId, $downloadFilename = "invoices.zip", $recache = false, $checkUser = true) {
        // Disable caching/gzipping
        while (ob_end_clean()) {  
            // do nothing   
        }
        header('Content-Encoding: identity');
        // Stream invoices
        $userId = ($checkUser ? $GLOBALS['uid'] : -1);
        $arInvoiceIds = (is_array($invoiceId) ? $invoiceId : array($invoiceId));
        if (count($arInvoiceIds) == 1) {
            // Because of problems opening zip archives containing only one file
            //  download single files without compressing them
            return $this->streamAsPdf($arInvoiceIds[0], null, $recache, $checkUser);
        }
        $zipFileTemp = tempnam("tmp", "zip");
        $zipInvoices = new ZipArchive();
        $zipInvoices->open($zipFileTemp, ZipArchive::OVERWRITE + ZipArchive::CREATE);
        foreach ($arInvoiceIds as $invoiceIndex => $invoiceId) {
            $arInvoice = $this->getDb()->fetch1("SELECT STATUS, STAMP_CORRECTION FROM `billing_invoice` WHERE ID_BILLING_INVOICE=".(int)$invoiceId);
            $isCorrection = ($arInvoice["STAMP_CORRECTION"] !== null);
            $isStorno = ($arInvoice["STATUS"] == 2);
            $invoiceFilename = $this->getCachePdfFilename($invoiceId, false, $isCorrection, $isStorno);
            if ($GLOBALS['nar_systemsettings']['MARKTPLATZ']['INVOICE_SAVE_PDF']) {
                // Use saved pdf file if possible
                $invoiceCacheFile = $this->getCachePdfFile($invoiceId, $recache, $userId);
                $zipInvoices->addFile($invoiceCacheFile, $invoiceFilename);
            } else {
                $zipInvoices->addFromString($invoiceFilename, $this->renderPdf($invoiceId, $userId));
            }

        }
        $zipInvoices->close();
        // Stream the file to the client
        header("Content-Type: application/zip");
        header("Content-Length: " . filesize($zipFileTemp));
        header("Content-Disposition: attachment; filename=\"".$downloadFilename."\"");
        flush();
        readfile($zipFileTemp);
        unlink($zipFileTemp);
        return true;
    }
    
    public function setCorrection($invoiceId) {
        $result = $this->getDb()->querynow("
            UPDATE `billing_invoice`
            SET STAMP_CORRECTION=CURRENT_DATE()
            WHERE ID_BILLING_INVOICE=".(int)$invoiceId);
        if ($result["rsrc"]) {
            // Success
            $billingNotificationManagement = BillingNotificationManagement::getInstance($this->getDb());
            $billingNotificationManagement->notify(BillingNotificationManagement::EVENT_INVOICE_CORRECTION, $invoiceId);
            return true;
        } else {
            // Failed
            return false;
        }
    }

    public function setStatus($invoiceId, $status, $data = array()) {
        $data['ID_BILLING_INVOICE'] = $invoiceId;

        switch($status) {
            case self::STATUS_UNPAID:
                $data['STAMP_PAY'] = null;
                $data['STAMP_CANCEL'] = null;
                $data['STATUS'] = self::STATUS_UNPAID;
                $event = BillingNotificationManagement::EVENT_INVOICE_UNPAY;
                break;
            case self::STATUS_PAID:
                $data['STAMP_PAY'] = date("Y-m-d");
                $data['STAMP_CANCEL'] = null;
                $data['STATUS'] = self::STATUS_PAID;
                $event = BillingNotificationManagement::EVENT_INVOICE_PAY;
                break;
            case self::STATUS_CANCELED:
                $data['STAMP_CANCEL'] = date("Y-m-d");
                $data['STATUS'] = self::STATUS_CANCELED;
                $event = BillingNotificationManagement::EVENT_INVOICE_CANCEL;
                break;
        }

        $result = $this->update($invoiceId, $data);
        if($result != NULL) {
            $billingNotificationManagement = BillingNotificationManagement::getInstance($this->getDb());
            $billingNotificationManagement->notify($event, $invoiceId, $data);
            
            if (array_key_exists("CREATE_CREDITNOTE", $data) && $data["CREATE_CREDITNOTE"]) {
                // Gutschrift erstellen
                $billingCreditnoteManagement = BillingCreditnoteManagement::getInstance($this->getDb());
                $billingInvoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->getDb());
                $invoiceItems = $billingInvoiceItemManagement->fetchAllByParam(array('FK_BILLING_INVOICE' => $invoiceId));
                $billingCreditnoteManagement->createCreditnote(array(
                  'FK_USER'     => $data["FK_USER"],
                  'DESCRIPTION' => Translation::readTranslation("marktplatz", "invoice.creditnote.description", null, array("ID_BILLING_INVOICE" => "'".$invoiceId."'"), "Storno Rechnung #{ID_BILLING_INVOICE}"),
                  'PRICE'       => $this->getInvoiceTotalPrice($invoiceId, true),
                  'FK_TAX'      => $invoiceItems[0]['FK_TAX'],
                  'STATUS'      => BillingCreditnoteManagement::STATUS_ACTIVE
                ));
            }
            return true;
        }
        return false;
    }

    public function setDunningLevel($invoiceId, $dunningLevel = 1) {
        $data = array('ID_BILLING_INVOICE' => $invoiceId);

        switch($dunningLevel) {
            case 0:
                $data['DUNNING_LEVEL'] = 0;
                $event = BillingNotificationManagement::EVENT_INVOICE_OVERDUE;
                break;
            case 1:
                $data['DUNNING_LEVEL'] = 1;
                $event = BillingNotificationManagement::EVENT_INVOICE_DUNNING_LEVEL_1;
                break;
            case 2:
                $data['DUNNING_LEVEL'] = 2;
                $event = BillingNotificationManagement::EVENT_INVOICE_DUNNING_LEVEL_2;
                break;
            case 3:
                $data['DUNNING_LEVEL'] = 3;
                $event = BillingNotificationManagement::EVENT_INVOICE_DUNNING_LEVEL_3;
                break;
        }

        $result = $this->update($invoiceId, $data);
        if($result != NULL) {
            $billingNotificationManagement = BillingNotificationManagement::getInstance($this->getDb());
            $billingNotificationManagement->notify($event, $invoiceId);

            return true;
        }
        return false;
    }

    public function validate($invoice, $isNewObject = true) {
        return true;
    }

    public function getInvoiceTotalPrice($invoiceId, $netPrice = false) {
        $invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->getDb());
        $invoiceItems = $invoiceItemManagement->fetchAllByParam(array('FK_BILLING_INVOICE' => $invoiceId));

        $sum = 0;
        foreach($invoiceItems as $key => $invoiceItem) {
            $taxModifier = (($invoiceItem['TAX_VALUE'] == null) || ($netPrice == true))?0:$invoiceItem['TAX_VALUE'];
            $sum += ($invoiceItem['QUANTITY']*$invoiceItem['PRICE']*(1+$taxModifier/100));
        }

        return $sum;
    }

    public function getInvoicePaidPrice($invoiceId) {
        $invoiceTransactionManagement = BillingInvoiceTransactionManagement::getInstance($this->getDb());
        return $invoiceTransactionManagement->getAmountPaidByInvoiceId($invoiceId);
    }

	public function shouldChargeAtOnceByUserId($userId, $forProvision = false) {
    if ($forProvision) {
		  return $this->getDb()->fetch_atom("SELECT PROV_PREPAID FROM usercontent WHERE FK_USER = '".(int)$userId."'");
    } else {
		  return $this->getDb()->fetch_atom("SELECT CHARGE_AT_ONCE FROM usercontent WHERE FK_USER = '".(int)$userId."'");
    }
	}

	public function isInvoiceTaxExempt($userId) {
		global $nar_systemsettings;
		$billingInvoiceTaxExemptManagement = BillingInvoiceTaxExemptManagement::getInstance($this->getDb());

		if($nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_ENABLE'] == 0 || $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_USTID'] == "") {
			return false;
		}

		$invoiceUser = $this->getDb()->fetch1("SELECT * FROM user WHERE ID_USER = '".$userId."' LIMIT 1");
		if($invoiceUser['TAX_EXEMPT'] == 1) {
			return true;
		} else {
			$countryUstidOption = $billingInvoiceTaxExemptManagement->getCountryUstIdOption($invoiceUser['FK_COUNTRY']);

			if($countryUstidOption == BillingInvoiceTaxExemptManagement::COUNTRY_USTID_OPTION_FREE_TAX) {
				return true;
			} else if($countryUstidOption == BillingInvoiceTaxExemptManagement::COUNTRY_USTID_OPTION_INTRA_COMMUNITY) {
                
                include_once $GLOBALS['ab_path'] . 'sys/lib.billing.invoice.taxexempt.php';
                $billingInvoiceTaxExemptManagement = BillingInvoiceTaxExemptManagement::getInstance($this->getDb());
                
				$isUstIdValid = ($billingInvoiceTaxExemptManagement->updateVatNumberValidationForUser($userId) == BillingInvoiceTaxExemptManagement::USTID_VALIDATION_VALID);

				return $isUstIdValid;
			} else {
				return false;
			}
		}

		return false;
	}

	protected function applyTaxExemptRules($rawData) {
		global $nar_systemsettings;
		$taxIdForTaxExempt = $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_TAX_ID'];
		$invoiceUser = $this->getDb()->fetch1("SELECT * FROM user WHERE ID_USER = '".$rawData['FK_USER']."' LIMIT 1");

		$rawData['TAX_EXEMPT'] = 1;
		$rawData['TAX_EXEMPT_USTID'] = $invoiceUser['UST_ID'];
		$rawData['TAX_EXEMPT_USTID_CHECKDATE'] = $invoiceUser['UST_ID_CHECKDATE'];

		foreach($rawData['__items'] as $key => $item) {
			$rawData['__items'][$key]['FK_TAX'] = $taxIdForTaxExempt;
		}

		return $rawData;
	}

    private function _getInvoiceAddressByUserId($userId) {
        $userManagement = UserManagement::getInstance($this->getDb());
        $tmpUser = $userManagement->fetchById($userId);

        $address = (!empty($tmpUser['FIRMA']) ? $tmpUser['FIRMA']."\n" : "").
            $tmpUser['VORNAME'].' '.$tmpUser['NACHNAME']."\n".
            $tmpUser['STRASSE']."\n\n".
            $tmpUser['PLZ'].' '.$tmpUser['ORT']."\n".
            $tmpUser['LAND'];

        return $address;
    }

    private function _getDefaultPaymentAdapter($rawData) {
        global $nar_systemsettings;

        if($rawData['FK_USER']) {
            $userManagement = UserManagement::getInstance($this->getDb());
            $invoiceUser = $userManagement->fetchById($rawData['FK_USER']);

            $userDefaultPaymentAdapter = $invoiceUser['FK_PAYMENT_ADAPTER'];

            if(($userDefaultPaymentAdapter != NULL) && ($userDefaultPaymentAdapter > 0)) {
                return $userDefaultPaymentAdapter;
            }
        }

        $defaultAdapterId = $nar_systemsettings['MARKTPLATZ']['INVOICE_STD_PAYMENT_ADAPTER'];

        $paymentAdapterManagement = PaymentAdapterManagement::getInstance($this->getDb());
        $tmpAdapter = $paymentAdapterManagement->fetchById($defaultAdapterId);

        if($tmpAdapter == null) {
            $all = $paymentAdapterManagement->fetchAllByParam(array('STATUS' => 1));

            $tmpAdapter = $all['0'];

        }

        return $tmpAdapter['ID_PAYMENT_ADAPTER'];
    }

    private function _getDefaultDueDate($rawData) {
        global $nar_systemsettings;

        $daysDue = $nar_systemsettings['MARKTPLATZ']['INVOICE_DAYS_REMIND'];
        $tmpDate = new DateTime($rawData['STAMP_CREATE']);
        $tmpDate->modify("+".((int)$daysDue)." day");

        return $tmpDate->format("Y-m-d");
    }

    private function _applyCreditnotesBeforeCreate($rawData) {
        $billingCreditnoteManagement = BillingCreditnoteManagement::getInstance($this->getDb());
        $invoiceUser = $rawData['FK_USER'];

        $amountLeft = 0;
        foreach($rawData['__items'] as $key => $item) {
            $tmpQuantity = isset($item['QUANTITY'])?$item['QUANTITY']:1;
            $tmpNetPrice = isset($item['PRICE'])?$item['PRICE']:0;
            $tmpTaxValue = $this->getDb()->fetch_atom("SELECT TAX_VALUE FROM tax WHERE ID_TAX = '".(int)$item['FK_TAX']."'");

            $tmpAmount = $tmpQuantity*$tmpNetPrice*(1+($tmpTaxValue/100));
            $amountLeft += $tmpAmount;
        }


        $creditnotes = $billingCreditnoteManagement->fetchAllByParam(array(
            'FK_USER' => $invoiceUser,
            'STATUS' => BillingCreditnoteManagement::STATUS_ACTIVE,
            'SORT' => 'c.STAMP_CREATE',
            'SORT_DIR' => 'ASC'
        ));

        foreach($creditnotes as $key => $creditnote) {
            $tmpApplyAmount = min($amountLeft, $creditnote['REMAINING_PRICE']);

            if($tmpApplyAmount > 0) {
                $tmpApplyAmountNet = $tmpApplyAmount / (1 + ($creditnote['TAX_VALUE']/100));
                $rawData['__items'][] = array(
                    'DESCRIPTION' => $creditnote['DESCRIPTION'],
                    'FK_TAX' => $creditnote['FK_TAX'],
                    'QUANTITY' => 1,
                    'PRICE' => -1 * $tmpApplyAmountNet
                );

                $billingCreditnoteManagement->reducePrice($creditnote['ID_BILLING_CREDITNOTE'], $tmpApplyAmountNet);

                $amountLeft -= $tmpApplyAmount;
            } else {
                break;
            }
        }
        if ($amountLeft <= 0) {
            $rawData['STATUS'] = self::STATUS_PAID;
        }

        return $rawData;
    }

    private function _eventBeforeCreate($rawData) {
        $paymentAdapterManagement = PaymentAdapterManagement::getInstance($this->getDb());
        $invoicePaymentAdapter = $paymentAdapterManagement->fetchById($rawData['FK_PAYMENT_ADAPTER']);

        $paymentAdapterConfiguration = array(
            'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($invoicePaymentAdapter['ID_PAYMENT_ADAPTER'])
        );
        $paymentAdapter = Payment_PaymentFactory::factory($invoicePaymentAdapter['ADAPTER_NAME'], $paymentAdapterConfiguration);
        $paymentAdapter->init(array('FK_USER' => $rawData['FK_USER']));
        $eventResult = $paymentAdapter->eventBeforeInvoiceCreate($rawData);

        if($eventResult !== NULL) {
            $rawData = $eventResult;
        }

        return $rawData;
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