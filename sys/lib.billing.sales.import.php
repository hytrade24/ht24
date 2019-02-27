<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.ad_order.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/lib.billing.invoice.php';
require_once $ab_path . 'sys/lib.billing.invoice.transaction.php';

class BillingSalesImport {

    private $configImport;
    private $configBankTransfer;
    private $dateToday;
    private $dateStart;
    private $dateStartStamp;
    private $db;
    private $errors;
    private $processed;

    function __construct(ebiz_db $db, $config = array()) {
        $this->db = $db;
        $this->dateToday = date("Y-m-d");
        $this->dateStart = $this->db->fetch_atom("SELECT MAX(DATE) FROM `billing_import`");
        $this->dateStartStamp = ($this->dateStart !== null ? strtotime($this->dateStart) : 0);
        $this->errors = array();
        $this->processed = array();
        $this->configImport = array(
            "CSV_HEADERS"   => 0,
            "CSV_DELIMITER" => ',',
            "CSV_ENCLOSURE" => '"',
            "CSV_ESCAPE"    => '\\'
        );
        $this->configImport = array_merge($this->configImport, $config);    // Merge default config with user config
        // Get payment adapter configuration
        $paymentAdapters = PaymentAdapterManagement::getInstance($db);
        $paymentAdapterBankTransfer = $paymentAdapters->fetchByAdapterName("BankTransfer");
        $this->configBankTransfer = $paymentAdapters->fetchConfigurationById($paymentAdapterBankTransfer["ID_PAYMENT_ADAPTER"]);
    }

    private function addProcess($type, $id_target, $name, $account, $bank, $date, $amount, $subject, $notice) {
        if (!array_key_exists($type, $this->processed)) {
            $this->processed[$type] = array();
        }
        $arEntry = array(
            "FK"                => $id_target,
            "IMPORTANT"         => 3,
            "TYPE"              => $type,
            "NAME"              => $name,
            "ACCOUNT"           => $account,
            "BANK"              => $bank,
            "DATE"              => $date,
            "AMOUNT"            => $amount,
            "SUBJECT"           => $subject,
            "NOTICE"            => $notice
        );
        $arEntry["ID_BILLING_IMPORT"] = $this->db->update("billing_import", $arEntry);
        $this->processed[$type][] = $arEntry;
    }

    private function createQuery($arParameters, $fields, $limitPerPage = null, $limitOffset = null) {
        $where = array();
        if (!empty($arParameters["TYPE"])) {
            $where[] = "TYPE='".mysql_real_escape_string($arParameters["TYPE"])."'";
        }
        if (!empty($arParameters["FK"])) {
            $where[] = "FK='".mysql_real_escape_string($arParameters["FK"])."'";
        }
        if (!empty($arParameters["IMPORTANT"]) && is_numeric($arParameters["IMPORTANT"])) {
            $where[] = "IMPORTANT='".mysql_real_escape_string($arParameters["IMPORTANT"])."'";
        }
        if (!empty($arParameters["DATE_FROM"])) {
            $where[] = "DATE>='".mysql_real_escape_string($arParameters["DATE_FROM"])."'";
        }
        if (!empty($arParameters["DATE_UNTIL"])) {
            $where[] = "DATE<='".mysql_real_escape_string($arParameters["DATE_UNTIL"])."'";
        }
        if (!empty($arParameters["NAME"])) {
            $where[] = "NAME LIKE '%".mysql_real_escape_string($arParameters["NAME"])."%'";
        }
        if (!empty($arParameters["ACCOUNT"])) {
            $where[] = "ACCOUNT LIKE '%".mysql_real_escape_string($arParameters["ACCOUNT"])."%'";
        }
        if (!empty($arParameters["BANK"])) {
            $where[] = "BANK LIKE '%".mysql_real_escape_string($arParameters["BANK"])."%'";
        }
        if (!empty($arParameters["SUBJECT"])) {
            $where[] = "SUBJECT LIKE '%".mysql_real_escape_string($arParameters["SUBJECT"])."%'";
        }
        $query = "SELECT ".$fields." FROM `billing_import` ".(!empty($where) ? "WHERE ".implode(" AND ", $where)." " : "").
            ($limitPerPage !== null ? "LIMIT ".($limitOffset !== null ? $limitOffset.", " : "").$limitPerPage : "");
        return $query;
    }

    public function countByParam($arParameters = array()) {
        $query = $this->createQuery($arParameters, "count(*)");
        return $this->db->fetch_atom($query);
    }

    public function fetchByParam($arParameters = array(), $limitPerPage = null, $limitOffset = null, &$all = 0) {
        $query = $this->createQuery($arParameters, "SQL_CALC_FOUND_ROWS *", $limitPerPage, $limitOffset);
        $arResult = $this->db->fetch_table($query);
        $all = $this->db->fetch_atom("SELECT FOUND_ROWS();");
        return $arResult;
    }

    private function getLink($type, $id) {
        $href = "#unknown";
        $label = "Unknown";
        switch ($type) {
            case 'invoice':
                $href = "index.php?page=buchhaltung&ID_BILLING_INVOICE=".$id;
                $label = "Rechnung #".$id;
                break;
        }
        return '<a href="'.$href.'" target="tab_'.$type.'">'.htmlentities($label).'</a>';
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return array
     */
    public function getProcessed() {
        return $this->processed;
    }

    public function processFile($file) {
        $arFile = file($file);
        if ($arFile === false) {
            $this->errors[] = "Failed to read file: ".$file;
            return false;
        }
        if ($this->configImport["CSV_HEADERS"]) {
            // Remove headers
            array_shift($arFile);
        }
        foreach ($arFile as $lineIndex => $lineRaw) {
            $lineCsv = str_getcsv($lineRaw, $this->configImport["CSV_DELIMITER"], $this->configImport["CSV_ENCLOSURE"], $this->configImport["CSV_ESCAPE"]);
            $arTransaction = array(
                "DATE"      => $this->readFields($lineCsv, "DATE"),
                "NAME"      => $this->readFields($lineCsv, "NAME", " "),
                "ACCOUNT"   => $this->readFields($lineCsv, "ACCOUNT", " "),
                "BANK"      => $this->readFields($lineCsv, "BANK", " "),
                "AMOUNT"    => $this->readFields($lineCsv, "AMOUNT"),
                "SUBJECT"   => $this->readFields($lineCsv, "SUBJECT")
            );
            if (!$this->processTransaction($arTransaction, $lineIndex+1)) {
                // Error while processing transaction
                return false;
            }
        }
        return true;
    }

    private function processTransaction($arTransaction, $lineNumber = "Unknown") {
        // Convert date to yyyy-mm-dd
        $date = false;
        if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $arTransaction["DATE"], $arMatches)) {
            // dd.mm.yyyy
            $date = $arMatches[3]."-".$arMatches[2]."-".$arMatches[1];
        } else if (preg_match("/([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{2,4})/", $arTransaction["DATE"], $arMatches)) {
            // mm/dd/yyyy
            $date = $arMatches[3]."-".$arMatches[1]."-".$arMatches[2];
        } else if (preg_match("/([0-9]{2,4})\.([0-9]{1,2})\.([0-9]{1,2})/", $arTransaction["DATE"], $arMatches)) {
            // yyyy-mm-dd
            $date = $arMatches[1]."-".$arMatches[2]."-".$arMatches[3];
        } else {
            $this->errors[] = "Invalid date in line ".$lineNumber.": ".$arTransaction["DATE"];
            return false;
        }
        $dateStamp = strtotime($date);
        if ($dateStamp <= $this->dateStartStamp) {
            // Already imported, skip.
            return true;
        }
        // Convert amount to float
        $amount = false;
        if (preg_match("/^[+-]?[0-9\.]+\,[0-9]{2}$/", $arTransaction["AMOUNT"])) {
            // e.g. 1.000,00 = 1000
            $amount = floatval( str_replace(",", ".", str_replace(".", "", $arTransaction["AMOUNT"])) );
        } else if (preg_match("/^[+-]?[0-9\,]+\.[0-9]{2}$/", $arTransaction["AMOUNT"])) {
            // e.g. 1,000.00 = 1000
            $amount = floatval( str_replace(".", ",", str_replace(",", "", $arTransaction["AMOUNT"])) );
        } else {
            $this->errors[] = "Invalid amount in line ".$lineNumber.": ".$arTransaction["AMOUNT"];
            return false;
        }
        // Get subject
        $subject = $arTransaction["SUBJECT"];
        // Process
        $id_target = null;
        $result = true;
        $action = "SKIP";
        $notice = "";
        if ($date == $this->dateToday) {
            $action = "PENDING";
            $notice = "Umsätze vom aktuellen Tag werden noch nicht verarbeitet.";
        } else if ($amount > 0) {
            // Received payment
            if (preg_match("/".preg_quote($this->configBankTransfer["PREFIX"])."\-([0-9]+)\-([0-9]+)/i", $subject, $arMatches)) {
                $action = "SUCCESS";
                $id_user = (int)$arMatches[1];
                $id_target = (int)$arMatches[2];
                $id_transaction = md5("I".$id_target."_D".$date."_A".$amount);
                $billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->db);
                $ar_invoice = $billingInvoiceManagement->fetchById($id_target);
                if ($ar_invoice === false) {
                    $notice = "Ungültige Rechnungs-Nr ".$id_target." (Zeile ".$lineNumber.")";
                    $action = "ERROR";
                } else if ($ar_invoice["FK_USER"] != $id_user) {
                    $notice = "Ungültiger Benutzer für ".$this->getLink("invoice", $id_target)." (Zeile ".$lineNumber.")";
                    $action = "ERROR";
                } else {
                    $billingInvoiceTransactionManagement = BillingInvoiceTransactionManagement::getInstance($this->db);
                    if ($billingInvoiceTransactionManagement->countByParam(array("TRANSACTION_ID" => $id_transaction)) == 0) {
                        // New payment
                        $paymentResult = $billingInvoiceTransactionManagement->createInvoiceTransaction(array(
                            'FK_BILLING_INVOICE' => $id_target,
                            'TYPE' => BillingInvoiceTransactionManagement::TYPE_DEFAULT,
                            'STAMP_CREATE' => $date,
                            'DESCRIPTION' => $subject,
                            'TRANSACTION_ID' => $id_transaction,
                            'PRICE' => $amount
                        ));
                        if ($paymentResult === null) {
                            $notice = "Fehler beim hinzufügen der Zahlung für ".$this->getLink("invoice", $id_target)."! (Zeile ".$lineNumber.")";
                            $action = "ERROR";
                        }
                    } else {
                        $notice = "Identische Zahlung für ".$this->getLink("invoice", $id_target)." bereits gebucht (Zeile ".$lineNumber.")";
                        $action = "NOTICE";
                    }
                }
            }
        }
        $this->addProcess($action, $id_target, $arTransaction["NAME"], $arTransaction["ACCOUNT"], $arTransaction["BANK"], $date, $amount, $subject, $notice);
        return $result;
    }

    private function readFields($lineCsv, $fieldName, $implodeWith = "") {
        $arResult = array();
        if (is_array($this->configImport["FIELDS_".$fieldName])) {
            foreach ($this->configImport["FIELDS_".$fieldName] as $fieldIndex => $fieldPosition) {
                if (count($lineCsv) > $fieldPosition) {
                    $arResult[] = $lineCsv[$fieldPosition];
                }
            }
        }
        return implode($implodeWith, $arResult);
    }

    public function setImportant($id, $value) {
        $result = $this->db->querynow("UPDATE `billing_import` SET IMPORTANT=".(int)$value." WHERE ID_BILLING_IMPORT=".(int)$id);
        return $result["rsrc"];
    }

    public function delete($id) {
        if (!is_array($id)) {
            return $this->delete(array($id));
        }
        $arImportIds = array();
        foreach ($id as $importIndex => $importId) {
            $arImportIds[] = (int)$importId;
        }
        $result = $this->db->querynow("DELETE FROM `billing_import` WHERE ID_BILLING_IMPORT IN (".implode(", ", $arImportIds).")");
        return $result["rsrc"];
    }

} 