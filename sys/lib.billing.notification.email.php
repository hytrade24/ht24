<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once dirname(__FILE__).'/lib.billing.invoice.php';
require_once dirname(__FILE__).'/lib.user.php';

class BillingNotificationEmailManagement {
	private static $db;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return BillingNotificationEmailManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function sendEmailToCustomerReasonNewInvoice($invoiceId) {
        global $nar_systemsettings;

        $billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());
        $userManangement = UserManagement::getInstance($this->getDb());

        $invoice = $billingInvoiceManagement->fetchById($invoiceId);
        if($invoice != null) {
            $invoiceUser = $userManangement->fetchById($invoice['FK_USER']);

            if($invoiceUser != null) {
                $mail_to = $invoice['FK_USER'];
                $mail_data = array_merge($invoiceUser, $invoice);
                $mail_data['SITEURL'] = $nar_systemsettings['SITE']['SITEURL'];
                $mail_data['ID_INVOICE'] = $invoice['ID_BILLING_INVOICE'];
                $mail_data['BRUTTO'] = $invoice['TOTAL_PRICE'];
                $mail_data['STAMP_PAY_UNTIL'] = $invoice['STAMP_DUE'];

                $arFiles = array();
                if ($GLOBALS['nar_systemsettings']['MARKTPLATZ']['INVOICE_MAIL_PDF']) {
                    require_once $GLOBALS['ab_path'] . 'sys/swiftmailer/swift_required.php';
                    if ($GLOBALS['nar_systemsettings']['MARKTPLATZ']['INVOICE_SAVE_PDF']) {
                        $invoiceFile = $billingInvoiceManagement->getCachePdfFile($invoiceId, FALSE, $invoice['FK_USER']);
                        $fileInfo = new finfo(FILEINFO_MIME);
                        $fileType = $fileInfo->file($invoiceFile);
                        $arFiles[] = Swift_Attachment::fromPath($invoiceFile, $fileType);
                    } else {
                        $isCorrection = ($invoice["STAMP_CORRECTION"] !== null);
                        $isStorno = ($invoice["STATUS"] == 2);
                        $invoiceFilename = $billingInvoiceManagement->getCachePdfFilename($invoiceId, false, $isCorrection, $isStorno);
                        $arFiles[] = new Swift_Attachment($billingInvoiceManagement->renderPdf($invoiceId, $invoice['FK_USER']), $invoiceFilename, "application/pdf");
                    }
                    $mail_data['INVOICE_MAIL_PDF'] = 1;
                }

                sendMailTemplateToUser(0, $mail_to, 'new_invoice', $mail_data, FALSE, FALSE, NULL, $arFiles);
            }
        }
    }

    public function sendEmailToCustomerReasonInvoiceCorrection($invoiceId) {
        global $nar_systemsettings;

        $billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());
        $userManangement = UserManagement::getInstance($this->getDb());

        $invoice = $billingInvoiceManagement->fetchById($invoiceId);
        if($invoice != null) {
            $invoiceUser = $userManangement->fetchById($invoice['FK_USER']);

            if($invoiceUser != null) {
                $mail_to = $invoice['FK_USER'];
                $mail_data = array_merge($invoiceUser, $invoice);
                $mail_data['SITEURL'] = $nar_systemsettings['SITE']['SITEURL'];
                $mail_data['ID_INVOICE'] = $invoice['ID_BILLING_INVOICE'];
                $mail_data['BRUTTO'] = $invoice['TOTAL_PRICE'];
                $mail_data['STAMP_PAY_UNTIL'] = $invoice['STAMP_DUE'];

                $arFiles = array();
                if ($GLOBALS['nar_systemsettings']['MARKTPLATZ']['INVOICE_MAIL_PDF']) {
                    require_once $GLOBALS['ab_path'] . 'sys/swiftmailer/swift_required.php';
                    if ($GLOBALS['nar_systemsettings']['MARKTPLATZ']['INVOICE_SAVE_PDF']) {
                        $invoiceFile = $billingInvoiceManagement->getCachePdfFile($invoiceId, FALSE, $invoice['FK_USER']);
                        $fileInfo = new finfo(FILEINFO_MIME);
                        $fileType = $fileInfo->file($invoiceFile);
                        $arFiles[] = Swift_Attachment::fromPath($invoiceFile, $fileType);
                    } else {
                        $isCorrection = ($invoice["STAMP_CORRECTION"] !== null);
                        $isStorno = ($invoice["STATUS"] == 2);
                        $invoiceFilename = $billingInvoiceManagement->getCachePdfFilename($invoiceId, false, $isCorrection, $isStorno);
                        $arFiles[] = new Swift_Attachment($billingInvoiceManagement->renderPdf($invoiceId, $invoice['FK_USER']), $invoiceFilename, "application/pdf");
                    }
                    $mail_data['INVOICE_MAIL_PDF'] = 1;
                }

                sendMailTemplateToUser(0, $mail_to, 'INVOICE_CORRECTION', $mail_data, FALSE, FALSE, NULL, $arFiles);
            }
        }
    }

    public function sendEmailToCustomerReasonInvoiceCancel($invoiceId) {
        global $nar_systemsettings;

        $billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());
        $userManangement = UserManagement::getInstance($this->getDb());

        $invoice = $billingInvoiceManagement->fetchById($invoiceId);
        if($invoice != null) {
            $invoiceUser = $userManangement->fetchById($invoice['FK_USER']);

            if($invoiceUser != null) {
                $mail_to = $invoice['FK_USER'];
                $mail_data = array_merge($invoiceUser, $invoice);
                $mail_data['SITEURL'] = $nar_systemsettings['SITE']['SITEURL'];
                $mail_data['ID_INVOICE'] = $invoice['ID_BILLING_INVOICE'];
                $mail_data['BRUTTO'] = $invoice['TOTAL_PRICE'];
                $mail_data['STAMP_PAY_UNTIL'] = $invoice['STAMP_DUE'];

                $arFiles = array();
                if ($GLOBALS['nar_systemsettings']['MARKTPLATZ']['INVOICE_MAIL_PDF']) {
                    require_once $GLOBALS['ab_path'] . 'sys/swiftmailer/swift_required.php';
                    if ($GLOBALS['nar_systemsettings']['MARKTPLATZ']['INVOICE_SAVE_PDF']) {
                        $invoiceFile = $billingInvoiceManagement->getCachePdfFile($invoiceId, FALSE, $invoice['FK_USER']);
                        $fileInfo = new finfo(FILEINFO_MIME);
                        $fileType = $fileInfo->file($invoiceFile);
                        $arFiles[] = Swift_Attachment::fromPath($invoiceFile, $fileType);
                    } else {
                        $isCorrection = ($invoice["STAMP_CORRECTION"] !== null);
                        $isStorno = ($invoice["STATUS"] == 2);
                        $invoiceFilename = $billingInvoiceManagement->getCachePdfFilename($invoiceId, false, $isCorrection, $isStorno);
                        $arFiles[] = new Swift_Attachment($billingInvoiceManagement->renderPdf($invoiceId, $invoice['FK_USER']), $invoiceFilename, "application/pdf");
                    }
                    $mail_data['INVOICE_MAIL_PDF'] = 1;
                }

                sendMailTemplateToUser(0, $mail_to, 'INVOICE_CANCEL', $mail_data, FALSE, FALSE, NULL, $arFiles);
            }
        }
    }

    public function sendEmailToCustomerReasonDunning($invoiceId, $level = 1) {
        global $nar_systemsettings;

        $mailTemplateNames = array(
            1 => 'INVOICE_REMINDER',
            2 => 'INVOICE_REMINDER_2',
            3 => 'INVOICE_REMINDER_LAST'
        );

        if($level < 1 || $level > 3) {
            return false;
        }

        $billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());
        $userManangement = UserManagement::getInstance($this->getDb());

        $invoice = $billingInvoiceManagement->fetchById($invoiceId);
        if($invoice != null) {
            $invoiceUser = $userManangement->fetchById($invoice['FK_USER']);

            if($invoiceUser != null) {
                $mail_to = $invoice['FK_USER'];
                $mail_data = array_merge($invoiceUser, $invoice);
                $mail_data['SITEURL'] = $nar_systemsettings['SITE']['SITEURL'];
                $mail_data['ID_INVOICE'] = $invoice['ID_BILLING_INVOICE'];
                $mail_data['BRUTTO'] = $invoice['TOTAL_PRICE'];
                $mail_data['STAMP_PAY_UNTIL'] = $invoice['STAMP_DUE'];
                $mail_data['STAMP_DELIVERY'] = $invoice['STAMP_CREATE'];

                sendMailTemplateToUser(0, $mail_to, $mailTemplateNames[$level], $mail_data, FALSE);
            }
        }
    }

    public function sendEmailToCustomerReasonNewTransaction($invoiceId, $data) {
        global $nar_systemsettings;

        $billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->getDb());
        $userManangement = UserManagement::getInstance($this->getDb());

        $invoice = $billingInvoiceManagement->fetchById($invoiceId);
        if($invoice != null) {
            $invoiceUser = $userManangement->fetchById($invoice['FK_USER']);

            if($invoiceUser != null) {
                $transactionData = array();
                foreach ($data['TRANSACTION'] as $key => $value) {
                    $transactionData['TRANSACTION_' . $key] = $value;
                }

                $mail_to = $invoice['FK_USER'];
                $mail_data = array_merge($invoiceUser, $invoice, $transactionData);
                $mail_data['SITEURL'] = $nar_systemsettings['SITE']['SITEURL'];
                $mail_data['ID_INVOICE'] = $invoice['ID_BILLING_INVOICE'];
                $mail_data['STAMP_PAY_UNTIL'] = $invoice['STAMP_DUE'];
                $mail_data['CURRENCY_DEFAULT'] = $nar_systemsettings['MARKTPLATZ']['CURRENCY'];

                sendMailTemplateToUser(0, $mail_to, 'INVOICE_TRANSACTION_CREATE', $mail_data, FALSE);
            }
        }
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