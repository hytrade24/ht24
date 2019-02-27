<?php
/* ###VERSIONSBLOCKINLCUDE### */


class BillingCreditnoteManagement {
	private static $db;
	private static $instance = null;

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return BillingCreditnoteManagement
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
        $sqlOrder = " c.STAMP_CREATE DESC, c.ID_BILLING_CREDITNOTE DESC ";

        if(isset($param['ID_BILLING_CREDITNOTE']) && $param['ID_BILLING_CREDITNOTE'] != null && !is_array($param['ID_BILLING_CREDITNOTE'])) { $sqlWhere .= " AND c.ID_BILLING_CREDITNOTE = '".mysql_real_escape_string($param['ID_BILLING_CREDITNOTE'])."' "; }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null) { $sqlWhere .= " AND c.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(isset($param['STATUS']) && $param['STATUS'] !== null) { $sqlWhere .= " AND c.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }

        if(isset($param['LIMIT']) && $param['LIMIT'] != null) { if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; } }
        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }


        $query = "
            SELECT
                c.*,
                tax.TAX_VALUE,
                tax.TXT AS TAX_NAME,
                c.PRICE AS TOTAL_PRICE_NET,
                (1 + (IFNULL(tax.TAX_VALUE, 0)/100)) * c.PRICE AS TOTAL_PRICE,
                (c.PRICE - c.PRICE_APPLIED) AS REMAINING_PRICE_NET,
                (1 + (IFNULL(tax.TAX_VALUE, 0)/100)) * (c.PRICE - c.PRICE_APPLIED) AS REMAINING_PRICE
            FROM
                billing_creditnote c
            LEFT JOIN tax ON tax.ID_TAX = c.FK_TAX
            ".$sqlJoin."
            WHERE
                true
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY c.ID_BILLING_CREDITNOTE
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
        $sqlOrder = " c.STAMP_CREATE ";

        if(isset($param['ID_BILLING_CREDITNOTE']) && $param['ID_BILLING_CREDITNOTE'] != null && !is_array($param['ID_BILLING_CREDITNOTE'])) { $sqlWhere .= " AND c.ID_BILLING_CREDITNOTE = '".mysql_real_escape_string($param['ID_BILLING_CREDITNOTE'])."' "; }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null) { $sqlWhere .= " AND c.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(isset($param['STATUS']) && $param['STATUS'] !== null) { $sqlWhere .= " AND c.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }

        $query = ("
            SELECT
                SQL_CALC_FOUND_ROWS c.ID_BILLING_CREDITNOTE
            FROM
                billing_creditnote c
            ".$sqlJoin."
            WHERE
                1 = 1
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY c.ID_BILLING_CREDITNOTE
        ");

        $result = $db->querynow($query);
        $count = $db->fetch_atom("SELECT FOUND_ROWS()");

        return $count;
    }

    public function fetchById($creditNoteId) {
        $creditnote = $this->getDb()->fetch1("
            SELECT
                c.*,
                tax.TAX_VALUE,
                tax.TXT AS TAX_NAME,
                c.PRICE AS TOTAL_PRICE_NET,
                (1 + (IFNULL(tax.TAX_VALUE, 0)/100)) * c.PRICE AS TOTAL_PRICE,
                (c.PRICE - c.PRICE_APPLIED) AS REMAINING_PRICE_NET,
                (1 + (IFNULL(tax.TAX_VALUE, 0)/100)) * (c.PRICE - c.PRICE_APPLIED) AS REMAINING_PRICE
            FROM billing_creditnote c
            LEFT JOIN tax ON tax.ID_TAX = c.FK_TAX
            WHERE
                ID_BILLING_CREDITNOTE = '" . (int)$creditNoteId . "'");

        return $creditnote;
    }

    public function createCreditnote($rawData) {

        // validation
        $validationError = false;
        if(!$this->validate($rawData, true)) { $validationError = true; }

        if(!$validationError) {
            $rawData['ID_BILLING_CREDITNOTE'] = null;

            if(!isset($rawData['STATUS'])) { $rawData['STATUS'] = self::STATUS_ACTIVE; }
            if(!isset($rawData['STAMP_CREATE'])) { $rawData['STAMP_CREATE'] = date("Y-m-d"); }

            $creditnoteId = $this->update(null, $rawData);

            return $creditnoteId;
        } else {
            return null;
        }

    }

    public function update($creditnoteId, $rawData) {
        if($this->validate($rawData, false)) {
            $rawData['ID_BILLING_CREDITNOTE'] = $creditnoteId;

            return $this->getDb()->update('billing_creditnote', $rawData);
        } else {
            return null;
        }
    }

    public function setStatus($creditnoteId, $status) {
        $data = array('ID_BILLING_CREDITNOTE' => $creditnoteId);

        switch($status) {
            case self::STATUS_ACTIVE:
                $data['STATUS'] = self::STATUS_ACTIVE;
                break;
            case self::STATUS_INACTIVE:
                $data['STATUS'] = self::STATUS_INACTIVE;
                break;
        }

        $result = $this->update($creditnoteId, $data);

        return false;
    }

    public function reducePrice($creditnoteId, $priceReduction) {
        $creditnote = $this->fetchById($creditnoteId);
        if($creditnote) {
            $newPriceApplied = $creditnote['PRICE_APPLIED'] + $priceReduction;

            if($newPriceApplied >= $creditnote['PRICE']) {
                $newStatus = self::STATUS_INACTIVE;
            } else {
                $newStatus = $creditnote['STATUS'];
            }

            $this->update($creditnoteId, array(
                'PRICE_APPLIED' => $newPriceApplied,
                'STATUS' => $newStatus
            ));
        }
    }

    public function validate($creditnote, $isNewObject = true) {
        return true;
    }

    public function deleteById($id) {
        $db = $this->getDb();
        $db->querynow("DELETE FROM billing_creditnote WHERE ID_BILLING_CREDITNOTE = '" . mysql_real_escape_string($id) . "'");

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