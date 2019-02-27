<?php
/* ###VERSIONSBLOCKINLCUDE### */



class BillingBillableItemManagement {
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
	 * @return BillingBillableItemManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    /**
     * Remove invalid items from database (user deleted)
     */
    public function clearInvalidItems() {
        $db = $this->getDb();
        $result = $db->querynow("DELETE FROM `billing_billableitem` WHERE FK_USER NOT IN (SELECT ID_USER FROM `user`)");
        return $result['rsrc'];
    }

    public function fetchAllByParam($param) {
        $db = $this->getDb();

        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " b.ID_BILLING_BILLABLEITEM ";
        $sqlSelect = "";

        if(isset($param['ID_BILLING_BILLABLEITEM']) && $param['ID_BILLING_BILLABLEITEM'] != null && !is_array($param['ID_BILLING_BILLABLEITEM'])) { $sqlWhere .= " AND b.ID_BILLING_BILLABLEITEM = '".mysql_real_escape_string($param['ID_BILLING_BILLABLEITEM'])."' "; }
        if(isset($param['ID_BILLING_BILLABLEITEM']) && $param['ID_BILLING_BILLABLEITEM'] !== null && is_array($param['ID_BILLING_BILLABLEITEM'])) { if(count($param['ID_BILLING_BILLABLEITEM']) > 0) { $sqlWhere .= " AND b.ID_BILLING_BILLABLEITEM IN (".implode(',', $param['ID_BILLING_BILLABLEITEM']).") "; } else { $sqlWhere .= "AND false"; } }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null) { $sqlWhere .= " AND b.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(isset($param['STAMP_CREATE_AFTER']) && $param['STAMP_CREATE_AFTER'] != null) { $sqlWhere .= " AND b.STAMP_CREATE >= DATE_SUB(NOW(), INTERVAL ".mysql_real_escape_string($param['STAMP_CREATE_AFTER']).") "; }
        if(isset($param['STAMP_DONT_BILL_UNTIL_LT']) && $param['STAMP_DONT_BILL_UNTIL_LT'] != null) { $sqlWhere .= " AND (b.STAMP_DONT_BILL_UNTIL < '".mysql_real_escape_string($param['STAMP_DONT_BILL_UNTIL_LT'])."' OR b.STAMP_DONT_BILL_UNTIL IS NULL) "; }

        if(isset($param['LIMIT']) && $param['LIMIT'] != null) { if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; } }
        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }

        if(isset($param["JOIN"])) { $sqlJoin .= $param["JOIN"]; $sqlSelect .= $param["SELECT"].", "; }


        $query = "
            SELECT
            	".$sqlSelect."
                b.*,
                tax.TAX_VALUE,
                tax.TXT AS TAX_NAME,
                (1 + (IFNULL(tax.TAX_VALUE, 0)/100)) * b.PRICE AS TOTAL_PRICE
            FROM
                billing_billableitem b
            LEFT JOIN tax ON tax.ID_TAX = b.FK_TAX
            ".$sqlJoin."
            WHERE
                true
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY b.ID_BILLING_BILLABLEITEM
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."
        ";
        $result = $db->fetch_table($query);


        foreach ($result as $key => $billableItem) {
            $result[$key]['TOTAL_PRICE'] = $this->getBillableItemTotalPrice($billableItem['ID_BILLING_BILLABLEITEM']);
            $result[$key]['TOTAL_PRICE_NET'] = $this->getBillableItemTotalPrice($billableItem['ID_BILLING_BILLABLEITEM'], true);
        }

        return $result;
    }

    public function countByParam($param) {
        $db = $this->getDb();


        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " b.ID_BILLING_BILLABLEITEM ";

        if(isset($param['ID_BILLING_BILLABLEITEM']) && $param['ID_BILLING_BILLABLEITEM'] != null) { $sqlWhere .= " AND b.ID_BILLING_BILLABLEITEM = '".mysql_real_escape_string($param['ID_BILLING_BILLABLEITEM'])."' "; }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null) { $sqlWhere .= " AND b.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }
        if(isset($param['STAMP_CREATE_AFTER']) && $param['STAMP_CREATE_AFTER'] != null) { $sqlWhere .= " AND b.STAMP_CREATE >= DATE_SUB(NOW(), INTERVAL ".mysql_real_escape_string($param['STAMP_CREATE_AFTER']).") "; }

        $query = ("
            SELECT
                SQL_CALC_FOUND_ROWS b.ID_BILLING_BILLABLEITEM
            FROM
                billing_billableitem b

            ".$sqlJoin."
            WHERE
                1 = 1
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY b.ID_BILLING_BILLABLEITEM
        ");

        $result = $db->querynow($query);
        $count = $db->fetch_atom("SELECT FOUND_ROWS()");

        return $count;
    }

    public function fetchById($billableItemId) {
        return $this->getDb()->fetch1("
            SELECT
                it.*,
                tax.TAX_VALUE,
                tax.TXT AS TAX_NAME
            FROM
                billing_billableitem it
            LEFT JOIN tax ON tax.ID_TAX = it.FK_TAX
            WHERE
                ID_BILLING_BILLABLEITEM = '" . (int)$billableItemId . "'");
    }

    public function createBillableItem($rawData) {
        // validation
        $validationError = false;
        if(!$this->validate($rawData, true)) { $validationError = true; }

        if(!$validationError) {
            $rawData['ID_BILLING_BILLABLEITEM'] = null;

            $billableItemId = $this->update(null, $rawData);
            return $billableItemId;
        } else {
            return false;
        }
    }

	public function createMultipleBillableItems($rawData) {
		if(count($rawData['__items']) < 1) { return false; }
		$billableItems = $rawData['__items'];

		$validationError = false;
		foreach($billableItems as $key => $item) {
            $billableItems[$key]['FK_USER'] = $rawData['FK_USER'];
            $billableItems[$key]['FK_USER_SALES'] = $rawData['FK_USER_SALES'];
			if(!$this->validate($item, true)) { $validationError = true; }
		}

		if(!$validationError) {
			$resultIds = array();
			foreach($billableItems as $key => $item) {
				$resultIds[] = $this->createBillableItem($item);
			}

			return $resultIds;
		}

		return false;
	}

    public function update($billableItemId, $rawData) {
        if($this->validate($rawData, false)) {
            $billableItem = array(
                'ID_BILLING_BILLABLEITEM' => $billableItemId,
                'FK_USER' => $rawData['FK_USER'],
                'FK_USER_SALES' => $rawData['FK_USER_SALES'],
                'FK_TAX' => $rawData['FK_TAX'],
                'STAMP_CREATE' => isset($rawData['STAMP_CREATE'])?$rawData['STAMP_CREATE']:date("Y-m-d"),
                'STAMP_DONT_BILL_UNTIL' => isset($rawData['STAMP_DONT_BILL_UNTIL'])?$rawData['STAMP_DONT_BILL_UNTIL']:null,
                'DESCRIPTION' => $rawData['DESCRIPTION'],
                'QUANTITY' => isset($rawData['QUANTITY'])?$rawData['QUANTITY']:1,
				'PRICE' => isset($rawData['PRICE'])?$rawData['PRICE']:0,
				'REF_TYPE' => isset($rawData['REF_TYPE'])?$rawData['REF_TYPE']:0,
				'REF_FK' => isset($rawData['REF_FK'])?$rawData['REF_FK']:NULL
            );

            return $this->getDb()->update('billing_billableitem', $billableItem);
        } else {
            return false;
        }
    }

    public function deleteBillableItem($billableItemId, $keepService = false, $idBillingCancel = null) {
    	$arBillableItem = $this->fetchById( $billableItemId );
    	$arData = $arBillableItem;
    	$arData["KEEP_PERFORMANCES"] = ($keepService ? 1 : 0);
    	$billingNotificationManagement = BillingNotificationManagement::getInstance($this->getDb());
    	$billingNotificationManagement->notify(BillingNotificationManagement::EVENT_BILLABLEITEM_CANCEL, $arBillableItem["ID_BILLING_BILLABLEITEM"], $arData);
    	$sql = "DELETE FROM `billing_billableitem` 
    	    		WHERE ID_BILLING_BILLABLEITEM=".(int)$billableItemId;
    	$result = $this->getDb()->querynow( $sql );
      if ($result["rsrc"]) {
        $arBillableItemCancel = $arBillableItem;
        $arBillableItemCancel["CANCEL_TIME"] = date("Y-m-d H:i:s");
        $arBillableItemCancel["FK_BILLING_CANCEL"] = $idBillingCancel;
        $arBillableItemCancel["FK_BILLING_BILLABLEITEM"] = $arBillableItem["ID_BILLING_BILLABLEITEM"];
        $idBillableItemCancel = $this->getDb()->update("billing_cancel_item", $arBillableItemCancel);
        return true;
      } else {
        return false;
      }
    	return $result["rsrc"];
    }

    public function removeById($billableItemId) {
        $this->getDb()->delete('billing_billableitem', $billableItemId);
    }

    public function validate($billableItem, $isNewObject = true) {
        return true;
    }

    public function getBillableItemTotalPrice($billableItemId, $netPrice = false) {
        $billableItem = $this->fetchById($billableItemId);

        $taxModifier = (($billableItem['TAX_VALUE'] == null) || ($netPrice == true))?0:$billableItem['TAX_VALUE'];
        return ($billableItem['QUANTITY']*$billableItem['PRICE']*(1+$taxModifier/100));
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