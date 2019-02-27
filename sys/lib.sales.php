<?php
/* ###VERSIONSBLOCKINLCUDE### */

class SalesManagement {

    protected static $provisionDefault = 0.1;
    private static $instance = null;

    public function getInstance($db = null, $langval = null) {
        if (self::$instance !== null) {
            return self::$instance;
        }
        $db = ($db === null ? $GLOBALS['db'] : $db);
        $langval = ($langval === null ? $GLOBALS['langval'] : $langval);
        self::$instance = new self($db, $langval);
        return self::$instance;
    }

    private $db;
    private $langval;

    public function __construct(ebiz_db $db, $langval) {
        $this->db = $db;
        $this->langval = $langval;
    }

    private function buildQueryUser($selectFields, $joins = array(), $where = array(), $groupBy = "u.ID_USER", $having = array(), $orderBy = false, $limit = false) {
        $query = "SELECT SQL_CALC_FOUND_ROWS ".$selectFields." FROM `user` u";
        if (!empty($joins)) {
            $query .= "\n".implode("\n", $joins);
        }
        if (!empty($where)) {
            $query .= "\nWHERE ".implode(" AND ", $where);
        }
        if ($groupBy !== false) {
            $query .= "\nGROUP BY ".$groupBy;
        }
        if (!empty($having)) {
            $query .= "\nHAVING ".implode(" AND ", $having);
        }
        if ($orderBy !== false) {
            $query .= "\nORDER BY ".$orderBy;
        }
        if ($limit !== false) {
            $query .= "\nLIMIT ".$limit;
        }
        return $query;
    }

    private function buildQuerySalesUser($selectFields, $joins = array(), $where = array(), $groupBy = "u.ID_USER", $having = array(), $orderBy = false, $limit = false) {
        $query = "SELECT SQL_CALC_FOUND_ROWS ".$selectFields." FROM `user` u\n".
            "JOIN `usergroup` ug ON ug.ID_USERGROUP=u.FK_USERGROUP\n".
            "LEFT JOIN `user` u2 ON u2.FK_USER_SALES=u.ID_USER\n".
            "LEFT JOIN `billing_sales` bs ON bs.FK_USER_SALES=u.ID_USER";
        if (!empty($joins)) {
            $query .= "\n".implode("\n", $joins);
        }
        if (!empty($where)) {
            $query .= "\nWHERE ".implode(" AND ", $where);
        }
        if ($groupBy !== false) {
            $query .= "\nGROUP BY ".$groupBy;
        }
        if (!empty($having)) {
            $query .= "\nHAVING ".implode(" AND ", $having);
        }
        if ($orderBy !== false) {
            $query .= "\nORDER BY ".$orderBy;
        }
        if ($limit !== false) {
            $query .= "\nLIMIT ".$limit;
        }
        return $query;
    }

    private function buildQuerySalesUserTurnovers($selectFields, $joins = array(), $where = array(), $groupBy = "bs.ID_BILLING_SALES", $having = array(), $orderBy = false, $limit = false) {
        $query = "SELECT SQL_CALC_FOUND_ROWS ".$selectFields." FROM `billing_sales` bs\n".
            "JOIN `billing_invoice` bi ON bi.ID_BILLING_INVOICE=bs.FK_BILLING_INVOICE\n".
            "JOIN `user` u ON u.ID_USER=bi.FK_USER\n".
            "JOIN `usergroup` ug ON ug.ID_USERGROUP=u.FK_USERGROUP";
        if (!empty($joins)) {
            $query .= "\n".implode("\n", $joins);
        }
        if (!empty($where)) {
            $query .= "\nWHERE ".implode(" AND ", $where);
        }
        if ($groupBy !== false) {
            $query .= "\nGROUP BY ".$groupBy;
        }
        if (!empty($having)) {
            $query .= "\nHAVING ".implode(" AND ", $having);
        }
        if ($orderBy !== false) {
            $query .= "\nORDER BY ".$orderBy;
        }
        if ($limit !== false) {
            $query .= "\nLIMIT ".$limit;
        }
        return $query;
    }

    public function createRegisterCode($code, $description, $id_user = null) {
        if ($id_user === null) {
            $id_user = $GLOBALS['uid'];
        }
        $query = "INSERT INTO `sales_code` (FK_USER, CODE, DESCRIPTION) VALUES ('".(int)$id_user."', '".mysql_real_escape_string($code)."', '".mysql_real_escape_string($description)."')";
        $result = $this->db->querynow($query);
        return $result["rsrc"];
    }

    public function deleteRegisterCode($idCode, $id_user = null) {
        if ($id_user === null) {
            $id_user = $GLOBALS['uid'];
        }
        $query = "DELETE FROM `sales_code` WHERE ID_SALES_CODE=".(int)$idCode." AND FK_USER=".$id_user;
        $result = $this->db->querynow($query);
        if ($result["rsrc"]) {
            $this->db->querynow("UPDATE `user` SET FK_SALES_CODE=NULL WHERE FK_SALES_CODE=".(int)$idCode);
            return true;
        } else {
            return false;
        }
    }

    public function getUserByRegisterCode($code) {
        return $this->db->fetch_atom("SELECT FK_USER FROM `sales_code` WHERE CODE='".mysql_real_escape_string($code)."'");
    }

    public function getIdByRegisterCode($code) {
        return $this->db->fetch_atom("SELECT ID_SALES_CODE FROM `sales_code` WHERE CODE='".mysql_real_escape_string($code)."'");
    }

    public function getUsersBySaleUser($id_user, &$arSearch = array()) {
        $arJoins = array();
        $fields = "u.*";
        $order = false;
        $limit = false;
        if (!empty($arSearch["SORT"])) {
            list($orderBy, $orderDir) = explode("+", $arSearch["SORT"]);
            $orderDir = (strtoupper($orderDir) == "ASC" ? "ASC" : "DESC");
            switch ($orderBy) {
                case 'STAMP_TURNOVER':
                    $order = "LAST_TURNOVER ".$orderDir;
                    break;
                case 'STAMP_REG':
                case 'TURNOVER':
                case 'PROVISION':
                    $order = $orderBy." ".$orderDir;
                    break;
            }
        }
        if (!empty($arSearch["LIMIT_COUNT"])) {
            $limit = ($arSearch["LIMIT_OFFSET"] > 0 ? (int)$arSearch["LIMIT_OFFSET"].", " : "").(int)$arSearch["LIMIT_COUNT"];
        }
        // Reg.-Code
        $fields .= ", sc.CODE as REG_CODE";
        $fields .= ", sc.DESCRIPTION as REG_CODE_DESC";
        $arJoins[] = "LEFT JOIN `sales_code` sc ON sc.ID_SALES_CODE=u.FK_SALES_CODE";
        // Umsatz & Provision
        $fields .= ", MAX(bi.STAMP_PAY) as LAST_TURNOVER";
        $fields .= ", (SELECT SUM(bs.AMOUNT) FROM `billing_sales` bs JOIN `billing_invoice` bsi ON bs.FK_BILLING_INVOICE=bsi.ID_BILLING_INVOICE WHERE bsi.FK_USER=u.ID_USER AND bs.FK_USER_SALES=u.FK_USER_SALES) as TURNOVER";
        $fields .= ", (SELECT SUM(bs.AMOUNT_PROV) FROM `billing_sales` bs JOIN `billing_invoice` bsi ON bs.FK_BILLING_INVOICE=bsi.ID_BILLING_INVOICE WHERE bsi.FK_USER=u.ID_USER AND bs.FK_USER_SALES=u.FK_USER_SALES) as PROVISION";
        $arJoins[] = "LEFT JOIN `billing_invoice` bi ON bi.FK_USER=u.ID_USER AND bi.STAMP_PAY IS NOT NULL";
        $arJoins[] = "LEFT JOIN `billing_sales` bs ON bs.FK_BILLING_INVOICE=bi.ID_BILLING_INVOICE AND bs.FK_USER_SALES=".(int)$id_user;
        // Mitgliedschaft
        $fields .= ", (SELECT s.V1 FROM `packet` p
		    JOIN `string_packet` s ON s.S_TABLE='packet' AND s.FK=p.ID_PACKET
			    AND BF_LANG=if(p.BF_LANG_PACKET & ".$this->langval.", ".$this->langval.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
            WHERE p.ID_PACKET=po.FK_PACKET) AS MEMBERSHIP_NAME";
        $fields .= ", (po.BILLING_CYCLE='ONCE' OR po.STATUS&1 = 1) as MEMBERSHIP_ACTIVE";
        $arJoins[] = "LEFT JOIN `packet_order` po ON po.`TYPE` = 'MEMBERSHIP' AND po.FK_COLLECTION IS NULL AND po.FK_USER=u.ID_USER AND po.STATUS&1 = 1";
        // Search options
        $arWhere = array("u.FK_USER_SALES=".(int)$id_user);
        $arHaving = array();
        if (!empty($arSearch["NAME"])) {
            $arWhere[] = "(u.NAME LIKE '%".mysql_real_escape_string($arSearch["NAME"])."%' OR ".
                "u.FIRMA LIKE '%".mysql_real_escape_string($arSearch["NAME"])."%' OR ".
                "u.NOTIZEN_SALES LIKE '%".mysql_real_escape_string($arSearch["NAME"])."%')";
        }
        if ($arSearch["MEMBERSHIP"] > 0) {
            $arWhere[] = "(po.FK_PACKET=".(int)$arSearch["MEMBERSHIP"].")";
        }
        if (is_numeric($arSearch["MEMBERSHIP_STATUS"])) {
            $arWhere[] = "(po.STATUS&1=".(int)$arSearch["MEMBERSHIP_STATUS"].")";
        }
        if (!empty($arSearch["TURNOVER_MIN"])) {
            $arHaving[] = "(TURNOVER>=".(float)str_replace(",", ".", $arSearch["TURNOVER_MIN"]).")";
        }
        if (!empty($arSearch["TURNOVER_MAX"])) {
            $arHaving[] = "(TURNOVER<=".(float)str_replace(",", ".", $arSearch["TURNOVER_MAX"]).")";
        }
        if (!empty($arSearch["PROVISION_MIN"])) {
            $arHaving[] = "(PROVISION>=".(float)str_replace(",", ".", $arSearch["PROVISION_MIN"]).")";
        }
        if (!empty($arSearch["PROVISION_MAX"])) {
            $arHaving[] = "(PROVISION<=".(float)str_replace(",", ".", $arSearch["PROVISION_MAX"]).")";
        }
        if (!empty($arSearch["LAST_CONTACT_MIN"])) {
            $arWhere[] = "(u.CONTACT_SALES>=".(float)str_replace(",", ".", $arSearch["LAST_CONTACT_MIN"]).")";
        }
        if (!empty($arSearch["LAST_CONTACT_MAX"])) {
            $arWhere[] = "(u.CONTACT_SALES<=".(float)str_replace(",", ".", $arSearch["LAST_CONTACT_MAX"]).")";
        }
        $query = $this->buildQueryUser($fields, $arJoins, $arWhere, "u.ID_USER", $arHaving, $order, $limit);
        $arResult = $this->db->fetch_table($query);
        $arSearch["RESULT_COUNT"] = $this->db->fetch_atom("SELECT FOUND_ROWS();");
        return $arResult;
    }


    public function getSalesUsers(&$arSearch = array()) {
        $arJoins = array();
        $fields = "u.*";
        $order = false;
        $limit = false;
        if (!empty($arSearch["SORT"])) {
            list($orderBy, $orderDir) = explode("+", $arSearch["SORT"]);
            $orderDir = (strtoupper($orderDir) == "ASC" ? "ASC" : "DESC");
            switch ($orderBy) {
                case 'STAMP_TURNOVER':
                case 'USER_COUNT':
                case 'USER_COUNT_ACTIVE':
                case 'TURNOVER':
                case 'PROVISION':
                case 'PROVISION_OPEN':
                    $order = $orderBy." ".$orderDir;
                    break;
            }
        }
        if (!empty($arSearch["LIMIT_COUNT"])) {
            $limit = ($arSearch["LIMIT_OFFSET"] > 0 ? (int)$arSearch["LIMIT_OFFSET"].", " : "").(int)$arSearch["LIMIT_COUNT"];
        }
        // Geworbene Kunden
        $fields .= ", COUNT(DISTINCT u2.ID_USER) as USER_COUNT";
        $fields .= ", COUNT(DISTINCT po.FK_USER) as USER_COUNT_ACTIVE";
        $arJoins[] = "LEFT JOIN `packet_order` po ON po.`TYPE` = 'MEMBERSHIP' AND po.FK_COLLECTION IS NULL AND po.FK_USER=u2.ID_USER AND po.STATUS&1 = 1";
        // Umsatz & Provision
        $fields .= ", (SELECT MAX(bi.STAMP_PAY) FROM `billing_sales` bs JOIN `billing_invoice` bi ON bi.ID_BILLING_INVOICE=bs.FK_BILLING_INVOICE WHERE bs.FK_USER_SALES=u.ID_USER) as STAMP_TURNOVER";
        $fields .= ", (SELECT SUM(bs.AMOUNT) FROM `billing_sales` bs WHERE bs.FK_USER_SALES=u.ID_USER) as TURNOVER";
        $fields .= ", (SELECT SUM(bs.AMOUNT_PROV) FROM `billing_sales` bs WHERE bs.FK_USER_SALES=u.ID_USER) as PROVISION";
        $fields .= ", (SELECT SUM(bs.AMOUNT_PROV) FROM `billing_sales` bs WHERE bs.FK_USER_SALES=u.ID_USER AND bs.BILLED=0) as PROVISION_OPEN";
        // Search options
        $arWhere = array();
        $arHaving = array();
        if (!empty($arSearch["NAME"])) {
            $arWhere[] = "(u.NAME LIKE '%".mysql_real_escape_string($arSearch["NAME"])."%' OR ".
                "u.FIRMA LIKE '%".mysql_real_escape_string($arSearch["NAME"])."%' OR ".
                "u.NOTIZEN_ADMIN LIKE '%".mysql_real_escape_string($arSearch["NAME"])."%')";
        }
        if (!empty($arSearch["USER_COUNT_MIN"])) {
            $arHaving[] = "(USER_COUNT>=".(int)$arSearch["USER_COUNT_MIN"].")";
        }
        if (!empty($arSearch["USER_COUNT_MAX"])) {
            $arHaving[] = "(USER_COUNT<=".(int)$arSearch["USER_COUNT_MAX"].")";
        }
        if (!empty($arSearch["USER_COUNT_ACTIVE_MIN"])) {
            $arHaving[] = "(USER_COUNT_ACTIVE>=".(int)$arSearch["USER_COUNT_ACTIVE_MIN"].")";
        }
        if (!empty($arSearch["USER_COUNT_ACTIVE_MAX"])) {
            $arHaving[] = "(USER_COUNT_ACTIVE<=".(int)$arSearch["USER_COUNT_ACTIVE_MAX"].")";
        }
        if (!empty($arSearch["TURNOVER_MIN"])) {
            $arHaving[] = "(TURNOVER>=".(float)str_replace(",", ".", $arSearch["TURNOVER_MIN"]).")";
        }
        if (!empty($arSearch["TURNOVER_MAX"])) {
            $arHaving[] = "(TURNOVER<=".(float)str_replace(",", ".", $arSearch["TURNOVER_MAX"]).")";
        }
        if (!empty($arSearch["PROVISION_MIN"])) {
            $arHaving[] = "(PROVISION>=".(float)str_replace(",", ".", $arSearch["PROVISION_MIN"]).")";
        }
        if (!empty($arSearch["PROVISION_MAX"])) {
            $arHaving[] = "(PROVISION<=".(float)str_replace(",", ".", $arSearch["PROVISION_MAX"]).")";
        }
        if (!empty($arSearch["PROVISION_OPEN_MIN"])) {
            $arHaving[] = "(PROVISION_OPEN>=".(float)str_replace(",", ".", $arSearch["PROVISION_OPEN_MIN"]).")";
        }
        if (!empty($arSearch["PROVISION_OPEN_MAX"])) {
            $arHaving[] = "(PROVISION_OPEN<=".(float)str_replace(",", ".", $arSearch["PROVISION_OPEN_MAX"]).")";
        }
        $arHaving[] = "(COUNT(u2.ID_USER) > 0) OR (COUNT(bs.ID_BILLING_SALES) > 0)";
        $query = $this->buildQuerySalesUser($fields, $arJoins, $arWhere, "u.ID_USER", $arHaving, $order, $limit);
        $arResult = $this->db->fetch_table($query);
        $arSearch["RESULT_COUNT"] = $this->db->fetch_atom("SELECT FOUND_ROWS();");
        return $arResult;
    }

    public function getSalesUserTurnovers($id_user, &$arSearch = array()) {
        $arJoins = array();
        $fields = "bs.*, u.*";
        $order = false;
        $limit = false;
        if (!empty($arSearch["SORT"])) {
            list($orderBy, $orderDir) = explode("+", $arSearch["SORT"]);
            $orderDir = (strtoupper($orderDir) == "ASC" ? "ASC" : "DESC");
            switch ($orderBy) {
                case 'STAMP_PAY':
                case 'FK_BILLING_INVOICE':
                    $order = $orderBy." ".$orderDir;
                    break;
            }
        }
        if (!empty($arSearch["LIMIT_COUNT"])) {
            $limit = ($arSearch["LIMIT_OFFSET"] > 0 ? (int)$arSearch["LIMIT_OFFSET"].", " : "").(int)$arSearch["LIMIT_COUNT"];
        }
        // Umsatz & Provision
        $fields .= ", bi.STAMP_PAY";
        $fields .= ", SUM(bs.AMOUNT) as TURNOVER";
        $fields .= ", SUM(bs.AMOUNT_PROV) as PROVISION";
        // Search options
        $arWhere = array("bs.FK_USER_SALES=".(int)$id_user);
        $arHaving = array();
        if (!empty($arSearch["NAME"])) {
            $arWhere[] = "(u.NAME LIKE '%".mysql_real_escape_string($arSearch["NAME"])."%' OR ".
                "u.FIRMA LIKE '%".mysql_real_escape_string($arSearch["NAME"])."%' OR ".
                "u.NOTIZEN_ADMIN LIKE '%".mysql_real_escape_string($arSearch["NAME"])."%')";
        }
        if (isset($arSearch["BILLED"])) {
            $arHaving[] = "(BILLED=".(int)$arSearch["BILLED"].")";
        }
        if (!empty($arSearch["STAMP_PAID_MIN"])) {
            $arHaving[] = "(STAMP_PAID>='".mysql_real_escape_string($arSearch["STAMP_PAID_MIN"])."')";
        }
        if (!empty($arSearch["STAMP_PAID_MAX"])) {
            $arHaving[] = "(STAMP_PAID<='".mysql_real_escape_string($arSearch["STAMP_PAID_MAX"])."')";
        }
        $query = $this->buildQuerySalesUserTurnovers($fields, $arJoins, $arWhere, "bs.ID_BILLING_SALES", $arHaving, $order, $limit);
        $arResult = $this->db->fetch_table($query);
        $arSearch["RESULT_COUNT"] = $this->db->fetch_atom("SELECT FOUND_ROWS();");
        return $arResult;
    }

    public function getRegisterCodesByUser($id_user = null) {
        if ($id_user === null) {
            $id_user = $GLOBALS['uid'];
        }
        return $this->db->fetch_table("SELECT * FROM `sales_code` WHERE FK_USER=".(int)$id_user);
    }

    protected function isInvoiceBilled($id_invoice) {
        return ($this->db->fetch_atom("SELECT count(*) FROM `billing_sales` WHERE FK_BILLING_INVOICE=".(int)$id_invoice." AND BILLED=1") > 0);
    }

    public function onInvoicePay($id_invoice, $fk_user_sales) {
        if ($this->isInvoiceBilled($id_invoice)) {
            eventlog("info", "Invoice #".$id_invoice." changed after sales provision was accounted!");
        }
        $provision = $this->db->fetch_atom("SELECT IFNULL(u.PROVISION_SALES,ug.SALES_PROV) FROM `usergroup` ug
            JOIN `user` u ON ug.ID_USERGROUP=u.FK_USERGROUP WHERE u.ID_USER=".(int)$fk_user_sales);
        $amount = $this->db->fetch_atom("SELECT SUM(PRICE) FROM `billing_invoice_item` WHERE FK_BILLING_INVOICE=".$id_invoice);
        $query = "INSERT INTO `billing_sales` (FK_BILLING_INVOICE, FK_USER_SALES, PAID, AMOUNT, AMOUNT_PROV) \n".
            "VALUES (".(int)$id_invoice.", ".(int)$fk_user_sales.", 1, ".(float)$amount.", ".(float)($amount * $provision / 100).") \n".
            "ON DUPLICATE KEY UPDATE PAID=1, AMOUNT=".(float)$amount.";";
        $this->db->querynow($query);
    }

    public function onInvoiceUnpay($id_invoice, $fk_user_sales) {
        if ($this->isInvoiceBilled($id_invoice)) {
            eventlog("info", "Invoice #".$id_invoice." changed after sales provision was accounted!");
        }
        $query = "UPDATE `billing_sales` SET PAID=0 WHERE FK_BILLING_INVOICE=".(int)$id_invoice;
        $this->db->querynow($query);
    }

    public function setSalesBilled($ar_sales_ids, $status, $id_user = null) {
        if ($id_user === null) {
            $id_user = $GLOBALS['uid'];
        }
        foreach ($ar_sales_ids as $index => $id) {
            $ar_sales_ids[$index] = (int)$id;
        }
        $query = "UPDATE `billing_sales` SET BILLED=".(int)$status." WHERE ID_BILLING_SALES IN (".implode(", ", $ar_sales_ids).") AND FK_USER_SALES=".$id_user;
        $result = $this->db->querynow($query);
        return $result["rsrc"];
    }

    public function updateLastContact($fk_user_sales, $fk_user) {
        $query = "UPDATE `user` SET CONTACT_SALES=NOW() WHERE ID_USER=".$fk_user." AND FK_USER_SALES=".(int)$fk_user_sales;
        $result = $this->db->querynow($query);
        return $result["rsrc"];
    }

    /**
     * @param $arUsers          List of user-ids to be assigned
     * @param $fk_user_sales    The id of the sales user to assign to
     */
    public function updateSalesUser($arUsers, $fk_user_sales) {
        foreach ($arUsers as $userIndex => $userId) {
            $arUsers[$userIndex] = (int)$userId;
        }
        $query = "UPDATE `user` SET FK_USER_SALES=".($fk_user_sales > 0 ? (int)$fk_user_sales : "NULL")." WHERE ID_USER IN (".implode(", ", $arUsers).")";
        $result = $this->db->querynow($query);
        return $result["rsrc"];
    }

} 