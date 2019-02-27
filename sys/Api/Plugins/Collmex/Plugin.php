<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 16:03
 */

class Api_Plugins_Collmex_Plugin extends Api_TraderApiPlugin {
    // Login credenticals
    const LOGIN_CUSTOMER_ID = "124450";
    const LOGIN_NAME = "1672596";
    const LOGIN_PASSWORD = "9056069";
    // Configuration variables
    const CONFIG_COMPANY = 1;
    // Name for this system
    const SYSTEM_NAME = "ebiz-trader";

    private $arSync;
    private $arSyncIds;

    public function __construct(Api_TraderApiHandler $apiHandler) {
        parent::__construct($apiHandler);
        $this->arSync = array();
        $this->arSyncIds = array();
    }

    public function __destruct() {
        if (!empty($this->arSync)) {
            $fileSync = __DIR__."/CollmexPlugin.sync.cfg";
            $arSyncSaved = array();
            if (file_exists($fileSync)) {
                $arSyncSaved = unserialize( file_get_contents($fileSync) );
            }
            file_put_contents($fileSync, serialize(array_merge($arSyncSaved, $this->arSync)));
        }
    }

    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 10;
    }

    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        if (!$GLOBALS['nar_systemsettings']['PLUGIN']['COLLMEX']) {
            // Do not load this plugin
            return false;
        }
        $this->registerEvent(Api_TraderApiEvents::CRONJOB_DONE, "apiUpdateAll");
        // User events
        $this->registerEvent(Api_TraderApiEvents::USER_NEW, "userChanged");
        $this->registerEvent(Api_TraderApiEvents::USER_PROFILE_CHANGE, "userChanged");
        $this->registerEvent(Api_TraderApiEvents::USER_DELETE, "userDelete");
        // Invoice events
        $this->registerEvent(Api_TraderApiEvents::INVOICE_CREATE, "invoiceCreate");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_CANCEL, "invoiceChanged");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_PAY, "invoiceChanged");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_UNPAY, "invoiceChanged");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_OVERDUE, "invoiceChanged");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_DUNNING_LEVEL_1, "invoiceChanged");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_DUNNING_LEVEL_2, "invoiceChanged");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_DUNNING_LEVEL_2, "invoiceChanged");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_TRANSACTION_CREATE, "invoiceChanged");
        $this->registerEvent(Api_TraderApiEvents::INVOICE_TRANSACTION_APPLY_CREDIT, "invoiceChanged");
        return true;
    }

    public function getSyncIds($target, $allowCached = true) {
        if (!array_key_exists($target, $this->arSyncIds)) {
            $this->arSyncIds[$target] = $this->db->fetch_nar("SELECT FK_TRADER, FK_COLLMEX FROM `plugin_collmex_link` WHERE S_TABLE='".$target."'");
        }
        return $this->arSyncIds[$target];
    }

    protected function apiCsvGetFields($type) {
        $arFields = array();
        switch (strtolower($type)) {
            case 'customer':
                $arFields = array(
                    "TYPE", "CUSTOMER_ID", "COMPANY_ID", "SALUTATION", "TITLE", "FIRST_NAME", "LAST_NAME", "COMPANY", "DEPARTMENT",
                    "STREET", "ZIP", "CITY", "NOTICE", "INACTIVE", "COUNTRY_ISO", "PHONE", "FAX", "EMAIL",
                    "BANK_ACCOUNT", "BANK_SORT", "BANK_IBAN", "BANK_BIC", "BANK_NAME", "BANK_TAX_NR", "BANK_USTID",
                    "PAYMENT_COND", "DISCOUNT_GROUP", "SHIPPING", "SHIPPING_MORE", "OUTPUT_MEDIA", "BANK_OWNER", "ADDRESS_GROUP",
                    "EBAY_USERNAME", "PRICE_GROUP", "CURRENCY", "SALES_USER", "COST_LOCATION", "RESUBMISSION", "SHIPPING_LOCK",
                    "BUILD_SERVICE", "SHIPPING_ID", "OUTPUT_LANGUAGE", "EMAIL_CC", "PHONE2", "BANK_SEPA_REF", "BANK_SEPA_SIGNED", "DUN_LOCK"
                );
                break;
            case 'invoice':
                $arFields = array(
                    "TYPE", "INVOICE_ID", "ITEM_POS", "INVOICE_TYPE", "COMPANY_ID", "ORDER_ID", "CUSTOMER_ID",
                    "SALUTATION", "TITLE", "FIRST_NAME", "LAST_NAME", "COMPANY", "DEPARTMENT",
                    "STREET", "ZIP", "CITY", "COUNTRY_ISO", "PHONE", "PHONE2", "FAX", "EMAIL",
                    "BANK_ACCOUNT", "BANK_SORT", "BANK_OWNER", "BANK_IBAN", "BANK_BIC", "BANK_NAME", "BANK_USTID",
                    "RESERVED", "INVOICE_DATE", "INVOICE_DATE_PRICE", "PAYMENT_COND", "CURRENCY_ISO",
                    "PRICE_GROUP", "DISCOUNT_GROUP", "DISCOUNT_PERCENT", "DISCOUNT_REASON",
                    "INVOICE_TEXT", "INVOICE_TEXT_FOOTER", "INVOICE_NOTES", "INVOICE_DELETED",
                    "LANGUAGE", "REVISER", "SALES_USER", "SYSTEM_NAME", "INVOICE_STATUS",
                    "DISCOUNT2_VALUE", "DISCOUNT2_REASON", "SHIPPING_TYPE", "SHIPPING_PRICE", "DELIVERY_CHARGE",
                    "SERVICE_DATE", "SHIPPING_COND", "SHIPPING_COND_MORE",
                    "SHIPPING_SALUTATION", "SHIPPING_TITLE", "SHIPPING_FIRST_NAME", "SHIPPING_LAST_NAME",
                    "SHIPPING_COMPANY", "SHIPPING_DEPARTMENT", "SHIPPING_STREET", "SHIPPING_ZIP", "SHIPPING_CITY",
                    "SHIPPING_COUNTRY_ISO", "SHIPPING_PHONE", "SHIPPING_PHONE2", "SHIPPING_FAX", "SHIPPING_EMAIL",
                    "ITEM_TYPE", "ITEM_PRODUCT_ID", "ITEM_PRODUCT_DESCRIPTION", "ITEM_AMOUNT_UNIT", "ITEM_AMOUNT",
                    "ITEM_PRICE", "ITEM_PRICE_AMOUNT", "ITEM_DISCOUNT_PERCENT", "ITEM_PRICE_FINAL", "ITEM_PRODUCT_TYPE",
                    "ITEM_TAX_CLASS", "ITEM_TAX_ABROAD", "ITEM_ORDER_POSITION", "ITEM_TAX_TYPE", "ITEM_SUM_OVER_POS",
                    "TURNOVER", "PRODUCT_COST", "GROSS_PROFIT", "PROFIT_MARGIN"
                );
                break;
        }
        return $arFields;
    }

    protected function apiCurlCreate($command) {
        $curl = curl_init("https://www.collmex.de/cgi-bin/cgi.exe?".self::LOGIN_CUSTOMER_ID.",0,data_exchange");
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, "LOGIN;".self::LOGIN_NAME.";".self::LOGIN_PASSWORD."\n".$command);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: text/csv"));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        return $curl;
    }

    protected function apiCurlSend($command) {
        $curl = $this->apiCurlCreate($command);
        $arCommand = explode("\n", $command);
        $arResult = explode("\n", curl_exec($curl));
        $error = curl_error($curl);
        curl_close($curl);
        if (!empty($error)) {
            eventlog("error", "CollmexPlugin: cURL Request fehlgeschlagen!", $error);
            return false;
        }
        $arResultFiltered = array();
        foreach ($arResult as $rowIndex => $csvRow) {
            if (empty($csvRow)) {
                continue;
            }
            $arEntry = str_getcsv(utf8_encode($csvRow), ";");
            switch ($arEntry[0]) {
                case 'MESSAGE':
                    $type = $arEntry[1];
                    $line = (array_key_exists(4, $arEntry) ? $arEntry[4]-2 : -1);
                    $messageCSV = ($line >= 0 ? $arCommand[ $line ] : "");
                    if ($type == 'E') {
                        eventlog("error", "CollmexPlugin: Collmex API Fehler #".$arEntry[2]."!", "CSV(".$arEntry[4]."): ".$messageCSV."\n".$arEntry[3]);
                    } elseif ($type == 'W') {
                        eventlog("warning", "CollmexPlugin: Collmex API Warnung #".$arEntry[2]."!", "CSV(".$arEntry[4]."): ".$messageCSV."\n".$arEntry[3]);
                    }
                    break;
            }
            $arResultFiltered[] = $arEntry;
        }
        return $arResultFiltered;
    }

    protected function apiCurlCustomerImport($arCustomerList) {
        $arFields = $this->apiCsvGetFields("customer");
        $csvCustomers = array();
        foreach ($arCustomerList as $customerIndex => $arCustomer) {
            $arCustomer["TYPE"] = "CMXKND";
            $csvCustomers[] = $this->utilTransformCsv($arCustomer, $arFields);
        }
    }

    protected function apiCurlCustomerGet($customerId = "", $companyId = "", $textSearch = "", $resubmission = "",
                          $zipCountry = "", $addressGroup = "", $priceGroup = "", $discountGroup = "",
                          $salesman = "", $onlyChanged = false, $showInactive = false) {
        $arFields = $this->apiCsvGetFields("customer");
        $command = "CUSTOMER_GET;".$customerId.";".$companyId.";".$this->utilEscapeCsv($textSearch).";".$resubmission.
            ";".$this->utilEscapeCsv($zipCountry).";".$addressGroup.";".$priceGroup.";".$discountGroup.";".$salesman.
            ";".($onlyChanged ? 1 : "").";".$this->utilEscapeCsv(self::SYSTEM_NAME).";".($showInactive ? 1 : 0);
        $arResult = $this->apiCurlSend($command);
        for ($i = count($arResult)-1; $i >= 0; $i--) {
            $arEntry = $arResult[$i];
            if ($arEntry[0] != "CMXKND") {
                // Skip rows that are not customer entries
                array_splice($arResult, $i, 1);
                continue;
            }
            $arResult[$i] = $this->utilTransformCsvReverse($arEntry, $arFields);
        }
        return $arResult;
    }

    protected function apiCurlInvoiceGet($invoiceId = "", $companyId = "", $customerId = "", $dateFrom = "", $dateTo = "",
                 $onlyOutput = false, $responseFormat = "", $onlyChanged = false, $onlySelfCreated = false, $noLetterPaper = false) {
        $arFields = $this->apiCsvGetFields("invoice");
        $command = "INVOICE_GET;".$invoiceId.";".$companyId.";".$customerId.";".$this->utilEscapeCsv($dateFrom).";".$this->utilEscapeCsv($dateTo)
            .";".($onlyOutput ? 1 : 0).";".$this->utilEscapeCsv($responseFormat).";".($onlyChanged ? 1 : 0).";".$this->utilEscapeCsv(self::SYSTEM_NAME)
            .";".($onlySelfCreated ? 1 : 0).";".($noLetterPaper ? 1 : 0);
        $arResult = $this->apiCurlSend($command);
        for ($i = count($arResult)-1; $i >= 0; $i--) {
            $arEntry = $arResult[$i];
            if ($arEntry[0] != "CMXINV") {
                // Skip rows that are not customer entries
                array_splice($arResult, $i, 1);
                continue;
            }
            $arResult[$i] = $this->utilTransformCsvReverse($arEntry, $arFields);
        }
        return $arResult;
    }

    public function apiUpdateAll() {
        $fileSync = __DIR__."/CollmexPlugin.sync.cfg";
        if (file_exists($fileSync)) {
            $this->arSync = array_merge( $this->arSync, unserialize( file_get_contents($fileSync) ) );
        }
        $this->apiUpdateUsers(is_array($this->arSync['user']) ? $this->arSync['user'] : array());
        $this->apiUpdateInvoices(is_array($this->arSync['invoice']) ? $this->arSync['invoice'] : array());
    }

    public function apiUpdateUsers($arUsersChanged) {
        $arUserIdMapping = $this->getSyncIds("user");
        $arFields = $this->apiCsvGetFields("customer");
        $arUpdateCSV = array();
        $arUpdateIds = array();
        if (!empty($arUsersChanged)) {
            foreach ($arUsersChanged as $userIndex => $userId) {
                $userIdCollmex = (array_key_exists($userId, $arUserIdMapping) ? $arUserIdMapping[$userId] : false);
                $arUserAssoc = $this->apiUpdateUsers_GetCollmexAssoc($userId, $userIdCollmex);
                if ($arUserAssoc === false) {
                    eventlog("error", "CollmexPlugin: Aktualisieren des Benutzers #".$userId." fehlgeschlagen!", "Fehler beim auslesen des Benutzer-Datensatz.");
                } else {
                    $arUpdateCSV[] = $this->utilTransformCsv($arUserAssoc, $arFields);
                    $arUpdateIds[] = $userId;
                }
            }
            if (!empty($arUpdateCSV)) {
                $success = false;
                $arInsertValues = array();
                $arResult = $this->apiCurlSend(implode("\n", $arUpdateCSV));
                foreach ($arResult as $messageIndex => $arMessage) {
                    if ($arMessage[0] == "NEW_OBJECT_ID") {
                        $userId = $arUpdateIds[ $arMessage[3]-2 ];
                        $userIdCollmex = $arMessage[1];
                        $arUserIdMapping[$userId] = $userIdCollmex;
                        $arInsertValues[] = "('user', ".(int)$userId.", ".(int)$userIdCollmex.")";
                    } elseif (($arMessage[0] == "MESSAGE") && ($arMessage[1] == "S")) {
                        $success = true;
                    }
                }
                if (!empty($arInsertValues)) {
                    $query = "INSERT IGNORE INTO `plugin_collmex_link` (S_TABLE, FK_TRADER, FK_COLLMEX) VALUES ".implode(", ", $arInsertValues);
                    $result = $this->db->querynow($query);
                    if ($result['rsrc'] === false) {
                        $success = false;
                    }
                }
                if ($success) {
                    foreach ($arUpdateIds as $updateIndex => $updateId) {
                        $this->syncDone("user", $updateId);
                    }

                }
            }
        }
        $arUsersChangedExt = $this->apiCurlCustomerGet("", "", "", "", "", "", "", "", "", true);
        foreach ($arUsersChangedExt as $userIndex => $arUser) {
            if (in_array($arUser["CUSTOMER_ID"], $arUserIdMapping)) {
                // Update!
                $userIdTrader = array_search($arUser["CUSTOMER_ID"], $arUserIdMapping);
                $arUserTrader = $this->apiUpdateUsers_GetTraderAssoc($arUser["CUSTOMER_ID"], $userIdTrader);
                $this->db->update("user", $arUserTrader);
            } else {
                // Create!
                // TODO: Sollen im Collmex angelegte Kunden beim ebiz-trader angelegt werden?
                //  - Welches Passwort wird für den neuen Kunden verwendet?
            }
        }
    }

    protected function apiUpdateUsers_GetCollmexAssoc($userId, $userIdCollmex = false) {
        // Read latest customer entry from collmex
        $arUserAssoc = array("TYPE" => "CMXKND");
        if ($userIdCollmex !== false) {
            // Update user
            $arUserCollmex = $this->apiCurlCustomerGet($userIdCollmex);
            if (!is_array($arUserCollmex) || empty($arUserCollmex)) {
                return false;
            }
            $arUserAssoc = $arUserCollmex[0];
        }
        // Update all fields available within the ebiz-trader
        $lookupManagement = Api_LookupManagement::getInstance($this->db);
        $stringManagement = Api_StringManagement::getInstance($this->db);
        require_once $GLOBALS['ab_path']."sys/lib.user.php";
        $userManagment = UserManagement::getInstance($this->db);
        $arUserTrader = $userManagment->fetchById($userId);
        $arUserTraderAnrede = $lookupManagement->readById($arUserTrader['LU_ANREDE']);
        $arUserTraderCountry = $stringManagement->readById('country', $arUserTrader['FK_COUNTRY']);
        if (!array_key_exists('COMPANY_ID', $arUserAssoc)) {
            $arUserAssoc['COMPANY_ID'] = self::CONFIG_COMPANY;
        }
        $arUserAssoc['SALUTATION'] = $arUserTraderAnrede['V1'];
        $arUserAssoc['FIRST_NAME'] = $arUserTrader['VORNAME'];
        $arUserAssoc['LAST_NAME'] = $arUserTrader['NACHNAME'];
        $arUserAssoc['COMPANY'] = $arUserTrader['FIRMA'];
        $arUserAssoc['STREET'] = $arUserTrader['STRASSE'];
        $arUserAssoc['ZIP'] = $arUserTrader['PLZ'];
        $arUserAssoc['CITY'] = $arUserTrader['ORT'];
        if (($arUserTrader['NOTIZEN_ADMIN'] === null) && (!array_key_exists('NOTICE', $arUserAssoc['NOTICE']))) {
            $arUserAssoc['NOTICE'] = "";
        } elseif ($arUserTrader['NOTIZEN_ADMIN'] !== null) {
            $arUserAssoc['NOTICE'] = $arUserTrader['NOTIZEN_ADMIN'];
        }
        $arUserAssoc['INACTIVE'] = ($arUserTrader['STAT'] == 1 ? 0 : 1);
        $arUserAssoc['COUNTRY_ISO'] = $arUserTraderCountry['CODE'];
        $arUserAssoc['PHONE'] = $arUserTrader['TEL'];
        $arUserAssoc['FAX'] = $arUserTrader['FAX'];
        $arUserAssoc['EMAIL'] = $arUserTrader['EMAIL'];
        $arUserAssoc['PHONE2'] = $arUserTrader['MOBIL'];
        $arUserAssoc['BANK_USTID'] = $arUserTrader['UST_ID'];
        return $arUserAssoc;
    }

    protected function apiUpdateUsers_GetTraderAssoc($userIdCollmex, $userId = false) {
        // Read latest customer entry from collmex
        $arUserCollmex = $this->apiCurlCustomerGet($userIdCollmex);
        if (!is_array($arUserCollmex) || empty($arUserCollmex)) {
            return false;
        }
        $arUserAssoc = $arUserCollmex[0];
        // Update all fields available within the ebiz-trader
        $lookupManagement = Api_LookupManagement::getInstance($this->db);
        $stringManagement = Api_StringManagement::getInstance($this->db);
        require_once $GLOBALS['ab_path']."sys/lib.user.php";
        $userManagment = UserManagement::getInstance($this->db);
        $arUserTrader = array();
        if ($userId !== false) {
            $arUserTrader = $userManagment->fetchById($userId);
            if (!is_array($arUserTrader)) {
                return false;
            }
        }
        $arTraderAnreden = $lookupManagement->readByArt("ANREDE");
        $arUserTrader['LU_ANREDE'] = 0;
        foreach ($arTraderAnreden as $anredeIndex => $arAnrede) {
            if ($arAnrede["V1"] == $arUserAssoc['SALUTATION']) {
                $arUserTrader['LU_ANREDE'] = $arAnrede['ID_LOOKUP'];
            }
        }
        $arUserTrader['VORNAME'] = $arUserAssoc['FIRST_NAME'];
        $arUserTrader['NACHNAME'] = $arUserAssoc['LAST_NAME'];
        $arUserTrader['FIRMA'] = $arUserAssoc['COMPANY'];
        $arUserTrader['STRASSE'] = $arUserAssoc['STREET'];
        $arUserTrader['PLZ'] = $arUserAssoc['ZIP'];
        $arUserTrader['ORT'] = $arUserAssoc['CITY'];
        $arUserTrader['NOTIZEN_ADMIN'] = $arUserAssoc['NOTICE'];
        if ($arUserTrader['STAT'] < 2) {
            $arUserTrader['STAT'] = ($arUserAssoc['INACTIVE'] ? 0 : 1);
        }
        $arUserTrader['FK_COUNTRY'] = $this->db->fetch_atom("SELECT ID_COUNTRY FROM `country` WHERE CODE='".mysql_real_escape_string($arUserAssoc['COUNTRY_ISO'])."'");
        $arUserTrader['TEL'] = $arUserAssoc['PHONE'];
        $arUserTrader['FAX'] = $arUserAssoc['FAX'];
        $arUserTrader['EMAIL'] = $arUserAssoc['EMAIL'];
        $arUserTrader['MOBIL'] = $arUserAssoc['PHONE2'];
        $arUserTrader['UST_ID'] = $arUserAssoc['BANK_USTID'];
        return $arUserTrader;
    }

    public function apiUpdateInvoices($arInvoicesChanged) {
        $arInvoiceIdMapping = $this->getSyncIds("invoice");
        $arFields = $this->apiCsvGetFields("invoice");
        $arUpdateCSV = array();
        $arUpdateIds = array();
        if (!empty($arInvoicesChanged)) {
            foreach ($arInvoicesChanged as $invoiceIndex => $invoiceId) {
                $invoiceIdCollmex = (array_key_exists($invoiceId, $arInvoiceIdMapping) ? $arInvoiceIdMapping[$invoiceId] : false);
                $arInvoiceList = $this->apiUpdateInvoices_GetCollmexAssoc($invoiceId, $invoiceIdCollmex);
                if ($arInvoiceList === false) {
                    eventlog("error", "CollmexPlugin: Aktualisieren des Benutzers #".$invoiceId." fehlgeschlagen!", "Fehler beim auslesen des Benutzer-Datensatz.");
                } else {
                    foreach ($arInvoiceList as $invoiceIndex => $arInvoiceAssoc) {
                        $arUpdateCSV[] = $this->utilTransformCsv($arInvoiceAssoc, $arFields);
                    }
                    $arUpdateIds[] = $invoiceId;
                }
            }
            if (!empty($arUpdateCSV)) {
                $success = false;
                $arInsertValues = array();
                $arResult = $this->apiCurlSend(implode("\n", $arUpdateCSV));
                foreach ($arResult as $messageIndex => $arMessage) {
                    if ($arMessage[0] == "NEW_OBJECT_ID") {
                        $invoiceId = $arUpdateIds[ $arMessage[3]-2 ];
                        $invoiceIdCollmex = $arMessage[1];
                        $arInvoiceIdMapping[$invoiceId] = $invoiceIdCollmex;
                        $arInsertValues[] = "('invoice', ".(int)$invoiceId.", ".(int)$invoiceIdCollmex.")";
                    } elseif (($arMessage[0] == "MESSAGE") && ($arMessage[1] == "S")) {
                        $success = true;
                    }
                }
                if (!empty($arInsertValues)) {
                    $query = "INSERT IGNORE INTO `plugin_collmex_link` (S_TABLE, FK_TRADER, FK_COLLMEX) VALUES ".implode(", ", $arInsertValues);
                    $result = $this->db->querynow($query);
                    if ($result['rsrc'] === false) {
                        $success = false;
                    }
                }
                if ($success) {
                    foreach ($arUpdateIds as $updateIndex => $updateId) {
                        $this->syncDone("invoice", $updateId);
                    }

                }
            }
        }
        $arInvoicesChangedExt = $this->apiCurlInvoiceGet("", "", "", "", "", false, "", true);
        $invoiceIndex = 0;
        $invoiceId = false;
        $arInvoiceListCollmex = array();
        foreach ($arInvoicesChangedExt as $invoiceIndex => $arInvoice) {
            $invoiceId = $arInvoice['INVOICE_ID'];
            if (!in_array($invoiceId, $arInvoiceListCollmex)) {
                $arInvoiceListCollmex[] = $invoiceId;
            }
        }
        foreach ($arInvoiceListCollmex as $invoiceIndex => $invoiceId) {
            if (in_array($invoiceId, $arInvoiceIdMapping)) {
                // Update!
                $invoiceIdTrader = array_search($invoiceId, $arInvoiceIdMapping);
                $arInvoiceTrader = $this->apiUpdateInvoices_GetTraderAssoc($invoiceId, $invoiceIdTrader, $arInvoices);
                die(var_dump("test", $arInvoiceListCollmex, $invoiceId, $invoiceIdTrader, $arInvoiceTrader));
                #$this->db->update("user", $arInvoiceTrader);
            } else {
                // Create!
                // TODO: Sollen im Collmex angelegte Rechnungen beim ebiz-trader angelegt werden?
            }
        }
    }

    protected function apiUpdateInvoices_GetCollmexAssoc($invoiceId, $invoiceIdCollmex = false) {
        require_once $GLOBALS['ab_path']."sys/lib.user.php";
        require_once $GLOBALS['ab_path']."sys/lib.billing.invoice.php";
        $lookupManagement = Api_LookupManagement::getInstance($this->db);
        $stringManagement = Api_StringManagement::getInstance($this->db);
        $userManagment = UserManagement::getInstance($this->db);
        $invoiceManagement = BillingInvoiceManagement::getInstance($this->db);
        $invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->db);
        // Read latest invoice entry from collmex
        $arUserIdMapping = $this->getSyncIds("user");
        $arInvoiceTrader = $invoiceManagement->fetchById($invoiceId);
        $arInvoiceItemsTrader = $invoiceItemManagement->fetchAllByParam(array('FK_BILLING_INVOICE' => $invoiceId));
        $arInvoiceAssoc = array("TYPE" => "CMXINV");
        $arInvoiceListCollmex = array();
        if ($invoiceIdCollmex !== false) {
            // Update invoice
            $arInvoiceListCollmex = $this->apiCurlInvoiceGet($invoiceIdCollmex);
            if (!is_array($arInvoiceListCollmex) || empty($arInvoiceListCollmex)) {
                return false;
            }
        } else {
            // Create invoice
            $arUserTrader = $userManagment->fetchById($arInvoiceTrader['FK_USER']);
            $arUserTraderAnrede = $lookupManagement->readById($arUserTrader['LU_ANREDE']);
            $arUserTraderCountry = $stringManagement->readById('country', $arInvoiceTrader['FK_COUNTRY']);
            $arInvoiceAssoc['CUSTOMER_ID'] = $arUserIdMapping[ $arInvoiceTrader['FK_USER'] ];
            $arInvoiceAssoc['SALUTATION'] = $arUserTraderAnrede['V1'];
            $arInvoiceAssoc['FIRST_NAME'] = $arUserTrader['VORNAME'];
            $arInvoiceAssoc['LAST_NAME'] = $arUserTrader['NACHNAME'];
            $arInvoiceAssoc['COMPANY'] = $arUserTrader['FIRMA'];
            $arInvoiceAssoc['STREET'] = $arUserTrader['STRASSE'];
            $arInvoiceAssoc['ZIP'] = $arUserTrader['PLZ'];
            $arInvoiceAssoc['CITY'] = $arUserTrader['ORT'];
            $arInvoiceAssoc['COUNTRY_ISO'] = $arUserTraderCountry['CODE'];
            $arInvoiceAssoc['PHONE'] = $arUserTrader['TEL'];
            $arInvoiceAssoc['FAX'] = $arUserTrader['FAX'];
            $arInvoiceAssoc['EMAIL'] = $arUserTrader['EMAIL'];
            $arInvoiceAssoc['PHONE2'] = $arUserTrader['MOBIL'];
            $arInvoiceAssoc['BANK_USTID'] = $arUserTrader['UST_ID'];
            $arInvoiceAssoc['INVOICE_STATUS'] = 0;  // 0 = Neu, 10 = Zu buchen, 20 = Offen, 30 = Gemahnt, 40 = Erledigt, 100 = Gelöscht
            if ($arInvoiceTrader['DUNNING_LEVEL'] !== NULL) {
                $arInvoiceAssoc['INVOICE_STATUS'] = 30;
            } elseif ($arInvoiceTrader['STAMP_PAY'] !== NULL) {
                $arInvoiceAssoc['INVOICE_STATUS'] = 40;
            }
        }
        // Update all fields available within the ebiz-trader
        if (!array_key_exists('COMPANY_ID', $arInvoiceAssoc)) {
            $arInvoiceAssoc['COMPANY_ID'] = self::CONFIG_COMPANY;
        }
        if (!array_key_exists('SYSTEM_NAME', $arInvoiceAssoc)) {
            $arInvoiceAssoc['SYSTEM_NAME'] = self::SYSTEM_NAME;
        }
        $arInvoiceList = array();
        foreach ($arInvoiceItemsTrader as $invoiceItemIndex => $arInvoiceItem) {
            $productType = 0;   // 0 = Ware, 1 = Dienstleistung, 2 = Mitgliedsbeitrag (nur Collmex Verein), 3 = Baudienstleistung
            if (($arInvoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_DEFAULT)
                || ($arInvoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_PACKET)
                || ($arInvoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_MEMBERSHIP)
                || ($arInvoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_PROVISION)
                || ($arInvoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_ADVERTISEMENT)) {
                $productType = 1;
            }
            $arInvoiceCollmex = array();
            if (array_key_exists($invoiceItemIndex, $arInvoiceListCollmex)) {
                $arInvoiceCollmex = $arInvoiceListCollmex[$invoiceItemIndex];
            }
            $arInvoiceList[] = array_merge($arInvoiceAssoc, $arInvoiceCollmex, array(
                'ITEM_POS'                  => $invoiceItemIndex,
                'INVOICE_TYPE'              => 0,
                'INVOICE_DATE'              => date("d.m.Y", strtotime($arInvoiceTrader['STAMP_CREATE'])),
                'INVOICE_TEXT'              => $arInvoiceItem['DESCRIPTION'],
                'ITEM_TYPE'                 => 0,  // 0 = Normalposition, 1 = Summenposition, 2 = Textposition, 3 = Kostenlos.
                'ITEM_PRODUCT_DESCRIPTION'  => $arInvoiceItem['DESCRIPTION'],
                'ITEM_AMOUNT_UNIT'          => 'PCE',
                'ITEM_AMOUNT'               => $arInvoiceItem['QUANTITY'],
                'ITEM_PRICE'                => str_replace(".", ",", $arInvoiceItem['PRICE']),
                'ITEM_PRICE_AMOUNT'         => $arInvoiceItem['QUANTITY'],
                'ITEM_PRODUCT_TYPE'         => $productType,
                'ITEM_TAX_CLASS'            => ($arInvoiceItem['TAX_VALUE'] > 0 ? 0 : 2),   // 0 = voller Steuersatz, 1 = halber Steuersatz, 2 = steuerfrei.
                'ITEM_TAX_ABROAD'           => $arInvoiceTrader['TAX_EXEMPT']
            ));
        }
        // TODO: Update address fields when updating
        #die(var_dump($arInvoiceList, $arInvoiceTrader, $arInvoiceItemsTrader));
        return $arInvoiceList;
    }

    protected function apiUpdateInvoices_GetTraderAssoc($invoiceIdCollmex, $invoiceId = false) {
        // Initialize required classes
        require_once $GLOBALS['ab_path']."sys/lib.billing.invoice.php";
        require_once $GLOBALS['ab_path']."sys/lib.user.php";
        $lookupManagement = Api_LookupManagement::getInstance($this->db);
        $stringManagement = Api_StringManagement::getInstance($this->db);
        $invoiceManagement = BillingInvoiceManagement::getInstance($this->db);
        $invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->db);
        // Get current invoice details
        $arInvoiceListCollmex = $this->apiCurlInvoiceGet($invoiceIdCollmex);
        if ($arInvoiceListCollmex === false) {
            // TODO: Fehlermeldung
            return false;
        }
        // Update all fields available within the ebiz-trader
        $arInvoiceTrader = array();
        $arInvoiceItemsTrader = array();
        if ($invoiceId !== false) {
            // Update invoice
            $arInvoiceTrader = $invoiceManagement->fetchById($invoiceId);
            if (!is_array($arInvoiceTrader)) {
                return false;
            }
            $arInvoiceItemsTrader = $invoiceItemManagement->fetchAllByParam(array('FK_BILLING_INVOICE' => $invoiceId));
            $arInvoiceItemsDeleted = $arInvoiceItemsTrader;
            $arInvoiceItemsNew = array();
            for ($i = count($arInvoiceItemsDeleted)-1; $i >= 0; $i--) {
                $arInvoiceItem = $arInvoiceItemsDeleted[$i];
                $isInvoiceItemNew = true;
                foreach ($arInvoiceListCollmex as $invoiceItemIndex => $arInvoiceItemCollmex) {
                    $arInvoiceItemCollmexCompare = $this->apiUpdateInvoices_GetCollmexAssocItem($arInvoiceItemCollmex['ITEM_POS'], $arInvoiceItem, $arInvoiceItemCollmex);
                    die(var_dump($arInvoiceItemCollmex, $arInvoiceItemCollmexCompare));
                    $invoiceItemPriceTrader = round($arInvoiceItem["PRICE"], 2);
                    $invoiceItemPriceCollmex = round(str_replace(",", ".", $arInvoiceItem["PRICE"]), 2);
                    $invoiceItemProductType = 0;   // 0 = Ware, 1 = Dienstleistung, 2 = Mitgliedsbeitrag (nur Collmex Verein), 3 = Baudienstleistung
                    if (($arInvoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_DEFAULT)
                        || ($arInvoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_PACKET)
                        || ($arInvoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_MEMBERSHIP)
                        || ($arInvoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_PROVISION)
                        || ($arInvoiceItem['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_ADVERTISEMENT)) {
                        $productType = 1;
                    }
                    if (($arInvoiceItemCollmex["ITEM_PRODUCT_DESCRIPTION"] == $arInvoiceItem["DESCRIPTION"])
                        && ($arInvoiceItemCollmex["ITEM_AMOUNT"] == $arInvoiceItem["QUANTITY"])
                        && ($arInvoiceItemCollmex["ITEM_TYPE"] == 0)
                        && ($arInvoiceItemCollmex["ITEM_PRODUCT_TYPE"] == $invoiceItemProductType)
                        && ($arInvoiceItemCollmex["ITEM_TAX_CLASS"] == ($arInvoiceItem['TAX_VALUE'] > 0 ? 0 : 2))
                        && ($arInvoiceItemCollmex["ITEM_PRODUCT_TYPE"] == $arInvoiceTrader['TAX_EXEMPT'])
                        && ($invoiceItemPriceCollmex == $invoiceItemPriceTrader)) {
                        unset($arInvoiceItemsDeleted[$invoiceItemIndex]);
                        $isInvoiceItemNew = false;
                        break;
                    }
                }
                if ($isInvoiceItemNew) {
                    $arInvoiceItemsNew[] = $arInvoiceItem;
                }
            }
            die(var_dump($arInvoiceItemsDeleted, $arInvoiceItemsNew));
        }
        // Update status
        $invoiceStatus = (int)$arInvoiceListCollmex[0]["INVOICE_STATUS"];  // 0 = Neu, 10 = Zu buchen, 20 = Offen, 30 = Gemahnt, 40 = Erledigt, 100 = Gelöscht
        if (($invoiceStatus == 40) && ($arInvoiceTrader['STAMP_PAY'] !== NULL)) {
            // Rechnung wurde bezahlt / abgeschlossen
            $result = $invoiceManagement->update($invoiceId, array(
                'STAMP_PAY' => date("Y-m-d"), 'STAMP_CANCEL' => null, 'STATUS' => BillingInvoiceManagement::STATUS_PAID
            ));
            if($result != NULL) {
                $billingNotificationManagement = BillingNotificationManagement::getInstance($this->db);
                $billingNotificationManagement->notify(BillingNotificationManagement::EVENT_INVOICE_PAY, $invoiceId);
            } else {
                // TODO: Fehlermeldung
                return false;
            }
        } else if (($invoiceStatus == 100) && ($arInvoiceTrader['STAMP_CANCEL'] !== NULL)) {
            // Rechnung wurde gelöscht / storniert
            $result = $invoiceManagement->update($invoiceId, array(
                'STAMP_CANCEL' => date("Y-m-d"), 'STATUS' => BillingInvoiceManagement::STATUS_CANCELED
            ));
            if($result != NULL) {
                $billingNotificationManagement = BillingNotificationManagement::getInstance($this->db);
                $billingNotificationManagement->notify(BillingNotificationManagement::EVENT_INVOICE_CANCEL, $invoiceId);
            } else {
                // TODO: Fehlermeldung
                return false;
            }
        }
        // Address
        $addressCountryId = $this->db->fetch_atom("SELECT ID_COUNTRY FROM `country` WHERE CODE='".mysql_real_escape_string($arInvoiceListCollmex[0]["COUNTRY_ISO"])."'");
        $addressCountry = $stringManagement->readById("country", $addressCountryId);
        $arInvoiceTrader['ADDRESS'] = $arInvoiceListCollmex[0]["COMPANY"]."\n".
            $arInvoiceListCollmex[0]["FIRST_NAME"]." ".$arInvoiceListCollmex[0]["LAST_NAME"]."\n".
            $arInvoiceListCollmex[0]["STREET"]."\n\n".
            $arInvoiceListCollmex[0]["ZIP"]." ".$arInvoiceListCollmex[0]["CITY"]."\n".
            $addressCountry["V1"];
        // Date values
        if (preg_match("/^([0-9]{4})([0-9]{2})([0-9]{2})$/", $arInvoiceListCollmex[0]["INVOICE_DATE"], $arInvoiceDate)) {
            $arInvoiceTrader['STAMP_CREATE'] = $arInvoiceDate[1]."-".$arInvoiceDate[2]."-".$arInvoiceDate[3];
        }
        return $arInvoiceTrader;
    }

    protected function apiUpdateInvoices_GetCollmexAssocItem($invoiceItemIndex, $arInvoiceTrader, $arInvoiceItemTrader) {
        $productType = 0;   // 0 = Ware, 1 = Dienstleistung, 2 = Mitgliedsbeitrag (nur Collmex Verein), 3 = Baudienstleistung
        if (($arInvoiceItemTrader['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_DEFAULT)
            || ($arInvoiceItemTrader['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_PACKET)
            || ($arInvoiceItemTrader['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_MEMBERSHIP)
            || ($arInvoiceItemTrader['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_PROVISION)
            || ($arInvoiceItemTrader['REF_TYPE'] == BillingInvoiceItemManagement::REF_TYPE_ADVERTISEMENT)) {
            $productType = 1;
        }
        return array(
            'ITEM_POS'                  => $invoiceItemIndex,
            'INVOICE_TYPE'              => 0,
            'INVOICE_DATE'              => date("d.m.Y", strtotime($arInvoiceItemTrader['STAMP_CREATE'])),
            'INVOICE_TEXT'              => $arInvoiceItemTrader['DESCRIPTION'],
            'ITEM_TYPE'                 => 0,  // 0 = Normalposition, 1 = Summenposition, 2 = Textposition, 3 = Kostenlos.
            'ITEM_PRODUCT_DESCRIPTION'  => $arInvoiceItemTrader['DESCRIPTION'],
            'ITEM_AMOUNT_UNIT'          => 'PCE',
            'ITEM_AMOUNT'               => $arInvoiceItemTrader['QUANTITY'],
            'ITEM_PRICE'                => str_replace(".", ",", $arInvoiceItemTrader['PRICE']),
            'ITEM_PRICE_AMOUNT'         => $arInvoiceItemTrader['QUANTITY'],
            'ITEM_PRODUCT_TYPE'         => $productType,
            'ITEM_TAX_CLASS'            => ($arInvoiceItemTrader['TAX_VALUE'] > 0 ? 0 : 2),   // 0 = voller Steuersatz, 1 = halber Steuersatz, 2 = steuerfrei.
            'ITEM_TAX_ABROAD'           => $arInvoiceTrader['TAX_EXEMPT']
        );
    }

    protected function apiUpdateInvoices_GetTraderAssocItem($invoiceIdTrader, $arInvoiceItemCollmex) {
        $taxId = $GLOBALS['nar_systemsettings']['MARKTPLATZ']['TAX_DEFAULT'];
        $arTax = $this->db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".$taxId);
        $price = str_replace(",", ".", $arInvoiceItemCollmex['ITEM_PRICE']);
        $quantity = $arInvoiceItemCollmex['ITEM_PRICE_AMOUNT'];
        return array(
            'FK_BILLING_INVOICE'        => $invoiceIdTrader,
            'DESCRIPTION'               => $arInvoiceItemCollmex['ITEM_PRODUCT_DESCRIPTION'],
            'PRICE'                     => $price,
            'QUANTITY'                  => $quantity,
            'FK_TAX'                    => $taxId,
            'REF_TYPE'                  => BillingInvoiceItemManagement::REF_TYPE_DEFAULT,
            'REF_FK'                    => NULL,
            'TAX_VALUE'                 => $arTax['TAX_VALUE'],
            'TAX_NAME'                  => $arTax['TXT'],
            'TOTAL_PRICE'               => ($price * $quantity) * (1 + $arTax['TAX_VALUE'] / 100),
            'TOTAL_PRICE_NET'           => ($price * $quantity)
        );
    }

    /**
     * Mark object for sync
     * @param string    $type
     * @param int       $id
     * @return bool
     */
    public function syncAdd($type, $id) {
        if (!array_key_exists($type, $this->arSync)) {
            $this->arSync[$type] = array();
        }
        if (!in_array($id, $this->arSync[$type])) {
            $this->arSync[$type][] = $id;
        }
        return true;
    }

    /**
     * Remove the given object from "Sync-List"
     * @param string    $type
     * @param int       $id
     * @return bool
     */
    public function syncDone($type, $id) {
        if (!array_key_exists($type, $this->arSync)) {
            return true;
        }
        $userIndex = array_search($id, $this->arSync[$type]);
        if ($userIndex !== false) {
            array_splice($this->arSync[$type], $userIndex, 1);
        }
        return true;
    }

    public function invoiceCreate($parameters) {
        $id = $parameters['id'];
        $this->syncAdd("invoice", $id);
    }

    public function invoiceChanged($parameters) {
        $id = $parameters['id'];
        $this->syncAdd("invoice", $id);
    }

    public function userChanged($parameters) {
        $id = $parameters['id'];
        $this->syncAdd("user", $id);
    }
    
    public function userDelete(Api_Entities_EventParamContainer $parameters) {
        $this->userChanged(array("id" => $parameters->getParam("ID_USER")));
    }

}