<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ads.php';
require_once $ab_path.'sys/lib.user.php';

class AdOrderManagement {
	private static $db;
	private static $instance = NULL;

	const STATUS_PAYMENT_OPEN = 0;
	const STATUS_PAYMENT_PAID = 1;
	const STATUS_PAYMENT_PARTIAL = 2;
	const STATUS_PAYMENT_PENDING = 3;

	const STATUS_SHIPPING_OPEN = 0;
	const STATUS_SHIPPING_INWORK = 1;
	const STATUS_SHIPPING_DONE = 2;

	const STATUS_CONFIRMATION_OPEN = 0;
	const STATUS_CONFIRMATION_CONFIRMED = 1;
	const STATUS_CONFIRMATION_DECLINED = 0;

	const STATUS_ARCHIVED_SELLER_DEFAULT = 0;
	const STATUS_ARCHIVED_SELLER_ARCHIVED = 1;


	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return AdOrderManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === NULL) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	public function getVendorCustomersOrders($param) {
		$db = $this->getDb();
		$arWhere = [];

		$query = 'SELECT c.NAME, c.EMAIL, CONCAT(c.VORNAME," ",c.NACHNAME) as FULL_NAME, c.FIRMA, c.STRASSE, c.PLZ, c.ORT,
		SUM(b.PREIS) as TOTAL_SUM, ROUND(SUM(b.PROV),2) as TOTAL_COMMISSION,
		COUNT(1) as NO_OF_ORDERS, c.ID_USER, DATE_FORMAT(MAX(a.STAMP_CREATE), "%e.%c.%Y") as LAST_ORDER_DATE
		FROM ad_order a
		INNER JOIN ad_sold b
		ON a.FK_USER_VK = '.$param["VENDOR_ID"].'
		AND b.FK_AD_ORDER = a.ID_AD_ORDER
		INNER JOIN user c
		ON c.ID_USER = b.FK_USER ';
		if (isset($param["STAMP_CREATE_FROM"]) && isset($param['STAMP_CREATE_TO'])) {
			if ( $param["STAMP_CREATE_FROM"] != "" && $param["STAMP_CREATE_TO"] != "" ) {
				$arWhere[] = ' date(b.STAMP_BOUGHT) BETWEEN "'.$param["STAMP_CREATE_FROM"].'" AND "'.$param["STAMP_CREATE_TO"].'"';
			}
		}
		else if (isset($param["STAMP_CREATE_FROM"])) {
			if ( $param["STAMP_CREATE_FROM"] != "" ) {
				$arWhere[] = ' date(b.STAMP_BOUGHT) >= '.$param["STAMP_CREATE_FROM"];
			}
		}
		else if (isset($param["STAMP_CREATE_TO"])) {
			if ( $param["STAMP_CREATE_TO"] != "" ) {
				$arWhere[] = ' date(b.STAMP_BOUGHT) <= '.$param["STAMP_CREATE_TO"];
			}
		}
		if (isset($param["SEARCH_ORDER_SHIPPING_STATUS"])) {
			$arWhere[] = 'a.STATUS_SHIPPING = '.$param["SEARCH_ORDER_SHIPPING_STATUS"];
		}
		if ( count($arWhere) != 0 ) {
			$query .= ' WHERE ' . implode(" AND ", $arWhere);
		}
		$query .= ' GROUP BY c.ID_USER ';
		if (isset($param['TOTAL_PAYMENT']) && $param['TOTAL_PAYMENT'] != "" ) {
			$query .= ' HAVING TOTAL_SUM >= '.$_REQUEST['TOTAL_PAYMENT'];
		}
		$query .= ' ORDER BY TOTAL_SUM DESC';

		$arResult = $db->fetch_table( $query );

		if ( is_array($arResult) ) {
			return $arResult;
		}
		return array();
	}


	public function createOrder($userId, $orderGrouped, &$orderId = null) {
		$db = $this->getDb();
		$orderSuccessful = TRUE;

		foreach($orderGrouped as $key => $groupedOrder) {
			$shippingProviderIdent = null;
			$shippingServiceId = null;
			// $order['article']['OPTIONS']['shippingProvider']
			if (array_key_exists("shippingProvider", $groupedOrder['0']['article']['OPTIONS'])) {
				$shippingProviderIdent = $groupedOrder['0']['article']['OPTIONS']["shippingProvider"];
				$shippingServiceIdResolved = Api_LookupManagement::getInstance($db)->readIdByValue("TRACK_URL", $shippingProviderIdent);
				if ($shippingServiceIdResolved > 0) {
					$shippingServiceId = (int)$shippingServiceIdResolved;
				}
			}
			$orderId = $db->update('ad_order', array(
				'FK_USER' => $userId,
				'FK_USER_VK' => $groupedOrder['0']['article']["ARTICLEDATA"]["FK_USER"],
				'FK_ARTICLE_EXT' => $groupedOrder['0']['article']["ARTICLEDATA"]["FK_ARTICLE_EXT"],
				'STAMP_CREATE' => date("Y-m-d H:i:s"),
				'FK_PAYMENT_ADAPTER' => $groupedOrder['0']['paymentAdapter'],
				'REMARKS' => $groupedOrder['0']['remarks'],
				'SHIPPING_PROVIDER' => $shippingProviderIdent,
				'SHIPPING_TRACKING_SERVICE' => $shippingServiceId
			));

			foreach($groupedOrder as $oKey => $order) {
				$arAdOverride = array();
				if ($order['article']['SHIPPING_PRICE'] !== null) {
					// Override shipping cost
					$arAdOverride["VERSANDKOSTEN"] = $order['article']['SHIPPING_PRICE'];
				}
				$idAdSold = AdManagment::Buy($order['article']['ID_AD'], $order['article']['ID_AD_VARIANT'], $order['article']['AVAILABILITY_ARRAY'], $userId, $order['userInvoice'], $order['userVersand'], $order['price'], $order['quantity'], $order['tradeId'], $orderId, $arAdOverride);
				if($idAdSold == NULL) {
					$orderSuccessful = FALSE;
				}
			}

			$newOrder = $this->fetchById($orderId);

			$this->sendStatusMailToSeller($orderId, 'MAIL_AD_VK');

			if($newOrder['ORDER_CONFIRMED']) {
				$this->getDb()->update('ad_order', array(
					'ID_AD_ORDER' => $orderId,
					'STATUS_CONFIRMATION' => 1
				));
				$this->sendStatusMailToUser($orderId, 'MAIL_AD_EK_CONFIRM');
			} else {
				$this->sendStatusMailToUser($orderId, 'MAIL_AD_EK');
			}
		}



		return $orderSuccessful;
	}

	public function markOrderAsConfirmed($orderId) {
		$order = $this->fetchById($orderId);
		$isConfirmed = FALSE;
		foreach($order['items'] as $key => $item) {
			if($item['CONFIRMED'] == 0) {
				AdManagment::BuyConfirm($item['FK_AD'], $item['ID_AD_SOLD']);
				$isConfirmed = TRUE;
			}
		}

		if($isConfirmed) {
			$this->getDb()->update('ad_order', array(
				'ID_AD_ORDER' => $orderId,
				'STATUS_CONFIRMATION' => 1
			));
			$this->sendStatusMailToUser($orderId, 'MAIL_AD_EK_CONFIRM');
		}

		return TRUE;
	}

	public function markOrderAsDesclined($orderId) {
		$order = $this->fetchById($orderId);

		$isDeclined = FALSE;
		foreach($order['items'] as $key => $item) {
			if($item['CONFIRMED'] == 0) {
				AdManagment::BuyDecline($item['FK_AD'], $item['ID_AD_SOLD'], '');
				$isDeclined = TRUE;
			}
		}

		if($isDeclined) {
			$this->getDb()->update('ad_order', array(
				'ID_AD_ORDER' => $orderId,
				'STATUS_CONFIRMATION' => 2
			));
			$this->sendStatusMailToUser($orderId, 'MAIL_AD_EK_DECLINE');
		}

		return TRUE;
	}

	public function markOrderAsPaid($orderId, $transactionId, $paymentDate = NULL) {
		$db = $this->getDb();

		if($paymentDate == NULL) { $paymentDate = date('Y-m-d H:i:d'); }

		$db->querynow("UPDATE ad_order
				SET STAMP_PAID = '".mysql_real_escape_string($paymentDate)."', TRANSACTION_ID='".mysql_real_escape_string($transactionId)."'
				WHERE ID_AD_ORDER = '".$orderId."'");

		$this->setPaymentStatus($orderId, self::STATUS_PAYMENT_PAID);

		// @TODO send Mail
	}

	/**
	 * @param      $orderId
	 * @param null $archiveDate
	 */
	public function markOrderAsArchived($orderId, $archiveDate = NULL, $archiveValue = 1) {
		$db = $this->getDb();

		if($archiveDate == NULL) { $archiveDate = date('Y-m-d H:i:d'); }


		if($archiveValue == self::STATUS_ARCHIVED_SELLER_ARCHIVED) {
			$db->querynow("UPDATE ad_order
							SET STATUS_ARCHIVED=1, STAMP_ARCHIVED = '".mysql_real_escape_string($archiveDate)."'
							WHERE ID_AD_ORDER = '".$orderId."'");

			$db->querynow("UPDATE ad_sold SET STATUS=(STATUS|1) WHERE FK_AD_ORDER = '".$orderId."'");
		} else {
			$db->querynow("UPDATE ad_order
					SET STATUS_ARCHIVED=0, STAMP_ARCHIVED = NULL
					WHERE ID_AD_ORDER = '".$orderId."'");

			$db->querynow("UPDATE ad_sold SET STATUS=(STATUS-(STATUS&1)) WHERE FK_AD_ORDER = '".$orderId."'");
		}

	}


	/**
	 * @param      $orderId
	 * @param null $archiveDate
	 */
	public function markOrderAsSellerArchived($orderId, $archiveDate = NULL, $archiveValue = 1) {
		$db = $this->getDb();

		if($archiveDate == NULL) { $archiveDate = date('Y-m-d H:i:d'); }

		if($archiveValue == self::STATUS_ARCHIVED_SELLER_ARCHIVED) {
			$db->querynow("UPDATE ad_order
					SET STATUS_ARCHIVED_SELLER=1, STAMP_ARCHIVED_SELLER = '".mysql_real_escape_string($archiveDate)."'
					WHERE ID_AD_ORDER = '".$orderId."'");

			$db->querynow("UPDATE ad_sold SET STATUS=(STATUS|2) WHERE FK_AD_ORDER = '".$orderId."'");
		} else {
			$db->querynow("UPDATE ad_order
					SET STATUS_ARCHIVED_SELLER=0, STAMP_ARCHIVED_SELLER = NULL
					WHERE ID_AD_ORDER = '".$orderId."'");

			$db->querynow("UPDATE ad_sold SET STATUS=(STATUS-(STATUS&2)) WHERE FK_AD_ORDER = '".$orderId."'");
		}
	}

	public function updateOrderStatus($orderId, $newOrderStatus) {
		global $db, $langval;

		$order = $this->fetchById($orderId);
		$orderPaymentChanged = false;
		$orderShippingChanged = false;
		$additionalData = array();

		if(isset($newOrderStatus['SHIPPING_TRACKING_SERVICE']) && isset($newOrderStatus['SHIPPING_TRACKING_CODE'])) {
			$this->getDb()->querynow("UPDATE ad_order SET SHIPPING_TRACKING_SERVICE='" . (int)$newOrderStatus['SHIPPING_TRACKING_SERVICE'] . "', SHIPPING_TRACKING_CODE = '".mysql_real_escape_string($newOrderStatus['SHIPPING_TRACKING_CODE'])."' WHERE ID_AD_ORDER = '" . $orderId . "'");
		}

		if($newOrderStatus['STATUS_PAYMENT'] != $order['STATUS_PAYMENT']) {
			$this->setPaymentStatus($orderId, $newOrderStatus['STATUS_PAYMENT']);
			$orderPaymentChanged = true;
		}
		if($newOrderStatus['STATUS_SHIPPING'] != $order['STATUS_SHIPPING']) {
			$this->setShippingStatus($orderId, $newOrderStatus['STATUS_SHIPPING']);
			$orderShippingChanged = true;
		}
		if (((int)$newOrderStatus['SHIPPING_TRACKING_SERVICE'] != (int)$order['SHIPPING_TRACKING_SERVICE'])
			|| ($newOrderStatus['SHIPPING_TRACKING_CODE'] != $order['SHIPPING_TRACKING_CODE'])) {
			$order = $this->fetchById($orderId);
			if(!empty($order['SHIPPING_TRACKING_SERVICE'])) {
				$additionalData['SHIPPING_TRACKING_SERVICE_URL'] = $this->getDb()->fetch_atom("
					SELECT s.V1 FROM lookup l
					LEFT JOIN string s ON s.S_TABLE='lookup' AND s.FK=l.ID_LOOKUP
						AND s.BF_LANG=if(l.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG+0.5)/log(2)))
					WHERE
						l.art = 'TRACK_URL' AND l.`ID_LOOKUP` = '".(int)$order['SHIPPING_TRACKING_SERVICE']."'");

				$additionalData['SHIPPING_TRACKING_SERVICE_URL'] = str_replace('{TRACKINGCODE}', $order['SHIPPING_TRACKING_CODE'], $additionalData['SHIPPING_TRACKING_SERVICE_URL']);
			}
			$db->querynow("UPDATE `ad_order` SET
				SHIPPING_TRACKING_SERVICE=".(int)$newOrderStatus['SHIPPING_TRACKING_SERVICE'].",
				SHIPPING_TRACKING_CODE='".mysql_real_escape_string($newOrderStatus['SHIPPING_TRACKING_CODE'])."',
				SHIPPING_TRACKING_SERVICE_URL='".mysql_real_escape_string($additionalData['SHIPPING_TRACKING_SERVICE_URL'])."'
				WHERE ID_AD_ORDER = '" . $orderId . "'");
			$orderShippingChanged = true;
		}
		if ($orderPaymentChanged) {
			$this->sendStatusMailToUser($orderId, 'AD_ORDER_STATUS_PAYMENT');
		}
		if ($orderShippingChanged) {
			$this->sendStatusMailToUser($orderId, 'AD_ORDER_STATUS_SHIPPING', $additionalData);
		}
	}

	public function setPaymentStatus($orderId, $paymentStatus) {
		if(!in_array($paymentStatus, array(0,1,2,3))) {
			return FALSE;
		}

		$this->getDb()->querynow("UPDATE ad_order SET STATUS_PAYMENT='" . (int)$paymentStatus . "' WHERE ID_AD_ORDER = '" . $orderId . "'");
		$this->getDb()->querynow("UPDATE ad_sold SET PAYED=".($paymentStatus == self::STATUS_PAYMENT_PAID?1:0)." WHERE FK_AD_ORDER = '".$orderId."'");
		$q = "UPDATE ad_sold SET STAMP_PAYED=\"".($paymentStatus == self::STATUS_PAYMENT_PAID?date("Y-m-d H:i:s"):"0000-00-00 00:00:00")."\" WHERE FK_AD_ORDER = '".$orderId."'";
		$this->getDb()->querynow( $q );

		return TRUE;
	}

	public function setShippingStatus($orderId, $shippingStatus) {
		if(!in_array($shippingStatus, array(0,1,2))) {
			return FALSE;
		}

		$this->getDb()->querynow("UPDATE ad_order SET STATUS_SHIPPING='".mysql_real_escape_string($shippingStatus)."' WHERE ID_AD_ORDER = '".$orderId."'");

		return TRUE;
	}



	public function setTransactionId($orderId, $transactionId) {
		$db = $this->getDb();

		$db->querynow("UPDATE ad_order
				SET TRANSACTION_ID='".mysql_real_escape_string($transactionId)."'
				WHERE ID_AD_ORDER = '".$orderId."'");
	}

	public function splitOrder($orderId) {
		$db = $this->getDb();
		$order = $this->fetchById($orderId);

		$newOrderItems = array();
		foreach($order['items'] as $key => $item) {
			if($item['CONFIRMED'] == 0) {
				$newOrderItems[] = $item['ID_AD_SOLD'];
			}
		}

		if(count($newOrderItems) > 0) {
			$newOrderId = $db->update('ad_order', array(
				'FK_USER' => $order['FK_USER'],
				'FK_USER_VK' => $order['FK_USER_VK'],
				'STAMP_CREATE' => $order['STAMP_CREATE'],
				'FK_PAYMENT_ADAPTER' => $order['FK_PAYMENT_ADAPTER']
			));

			$db->querynow("UPDATE ad_sold SET FK_AD_ORDER = '".$newOrderId."' WHERE ID_AD_SOLD IN (".implode(",", $newOrderItems).") ");
		}

		return $newOrderId;
	}

    public function addGeneratedFields(&$order) {
        $order['SHOW_MWST'] = 1;
        if(is_array($order['items']) && count($order['items']) > 0) {
            $tmpItem = $order['items']['0'];
            $invoiceSet = (($tmpItem['INVOICE_FIRMA'] !== null) || ($tmpItem['INVOICE_VORNAME'] !== null) || ($tmpItem['INVOICE_NACHNAME'] !== null) || ($tmpItem['INVOICE_STRASSE'] !== null)
            || ($tmpItem['INVOICE_PLZ'] !== null) || ($tmpItem['INVOICE_ORT'] !== null) || ($tmpItem['INVOICE_LAND'] !== null) ? true : false);
            $versandSet = (($tmpItem['VERSAND_FIRMA'] !== null) || ($tmpItem['VERSAND_VORNAME'] !== null) || ($tmpItem['VERSAND_NACHNAME'] !== null) || ($tmpItem['VERSAND_STRASSE'] !== null)
            || ($tmpItem['VERSAND_PLZ'] !== null) || ($tmpItem['VERSAND_ORT'] !== null) || ($tmpItem['VERSAND_LAND'] !== null) ? true : false);
            $order = array_merge($order, array(
                'ADDRESS_INVOICE_FIRMA' => ($invoiceSet ? $tmpItem['INVOICE_FIRMA'] : $order['USER_EK_FIRMA']),
                'ADDRESS_INVOICE_VORNAME' => ($invoiceSet ? $tmpItem['INVOICE_VORNAME'] : $order['USER_EK_VORNAME']),
                'ADDRESS_INVOICE_NACHNAME' => ($invoiceSet ? $tmpItem['INVOICE_NACHNAME'] : $order['USER_EK_NACHNAME']),
                'ADDRESS_INVOICE_STRASSE' => ($invoiceSet ? $tmpItem['INVOICE_STRASSE'] : $order['USER_EK_STRASSE']),
                'ADDRESS_INVOICE_PLZ' => ($invoiceSet ? $tmpItem['INVOICE_PLZ'] : $order['USER_EK_PLZ']),
                'ADDRESS_INVOICE_ORT' => ($invoiceSet ? $tmpItem['INVOICE_ORT'] : $order['USER_EK_ORT']),
                'ADDRESS_INVOICE_LAND' => ($invoiceSet ? $tmpItem['INVOICE_LAND'] : $order['USER_EK_LAND']),
                'ADDRESS_VERSAND_FIRMA' => ($versandSet ? $tmpItem['VERSAND_FIRMA'] : $order['USER_EK_FIRMA']),
                'ADDRESS_VERSAND_VORNAME' => ($versandSet ? $tmpItem['VERSAND_VORNAME'] : $order['USER_EK_VORNAME']),
                'ADDRESS_VERSAND_NACHNAME' => ($versandSet ? $tmpItem['VERSAND_NACHNAME'] : $order['USER_EK_NACHNAME']),
                'ADDRESS_VERSAND_STRASSE' => ($versandSet ? $tmpItem['VERSAND_STRASSE'] : $order['USER_EK_STRASSE']),
                'ADDRESS_VERSAND_PLZ' => ($versandSet ? $tmpItem['VERSAND_PLZ'] : $order['USER_EK_PLZ']),
                'ADDRESS_VERSAND_ORT' => ($versandSet ? $tmpItem['VERSAND_ORT'] : $order['USER_EK_ORT']),
                'ADDRESS_VERSAND_LAND' => ($versandSet ? $tmpItem['VERSAND_LAND'] : $order['USER_EK_LAND'])
            ));
            // Prüfen ob die MwSt bei allen items gleich eingestellt ist
            $order['MWST'] = $tmpItem["MWST"];
            foreach ($order['items'] as $itemIndex => $arItem) {
                if ($arItem['MWST'] != $order['MWST']) {
                    $order['SHOW_MWST'] = 0;
                }
            }
        } else {
            $order['items'] = array();
        }

        $confirmationData = $this->getOrderConfirmationArrayByItems($order['items']);
        $order = array_merge($order, $confirmationData);

        $order['ITEM_PRICE'] = $this->calculateItemPriceByItems($order['items']);
        $order['SHIPPING_PRICE'] = $this->calculateShippingCostByItems($order['items']);
        $order['TOTAL_PRICE'] = $order['ITEM_PRICE'] + $order['SHIPPING_PRICE'];
    }

    public function getQuery($param = array()) {
        global $langval;
        $language = ($param['BF_LANG'] > 0 ? $param['BF_LANG'] : $langval);
        $sqlFields = "";
        $sqlLimit = "";
        $sqlWhere = array();
        $sqlJoin = "";
        $having = "";
        $sqlOrder = " ao.STAMP_CREATE DESC ";

        if(isset($param['ID_AD_ORDER']) && $param['ID_AD_ORDER'] != NULL) {
            if (is_array($param['ID_AD_ORDER'])) {
                $sqlWhere[] = "ao.ID_AD_ORDER IN (".implode(", ", $param['ID_AD_ORDER']).")";
            } else {
                $sqlWhere[] = "ao.ID_AD_ORDER = '".(int)$param['ID_AD_ORDER']."' ";
            }
        }
        if(isset($param['USER_BUYER']) && $param['USER_BUYER'] != NULL) { $sqlWhere[] = "ads.FK_USER = '".(int)$param['USER_BUYER']."' AND ao.FK_USER = '".(int)$param['USER_BUYER']."' "; }
        if(isset($param['USER_SELLER']) && $param['USER_SELLER'] != NULL) { $sqlWhere[] = "ads.FK_USER_VK = '".(int)$param['USER_SELLER']."' AND ao.FK_USER_VK = '".(int)$param['USER_SELLER']."' "; }
        if(isset($param['STAMP_CREATE_FROM']) && $param['STAMP_CREATE_FROM'] != null) { $sqlWhere[] = "DATE(ao.STAMP_CREATE) >= STR_TO_DATE('".mysql_real_escape_string($param['STAMP_CREATE_FROM'])."', '%d.%m.%Y') "; }
        if(isset($param['STAMP_CREATE_TO']) && $param['STAMP_CREATE_TO'] != null) { $sqlWhere[] = "DATE(ao.STAMP_CREATE) <= STR_TO_DATE('".mysql_real_escape_string($param['STAMP_CREATE_TO'])."', '%d.%m.%Y') "; }
        if(isset($param['ARCHIVE']) && $param['ARCHIVE'] !== NULL) { $sqlWhere[] = "ao.STATUS_ARCHIVED = '".$param['ARCHIVE']."' "; }
        if(isset($param['ARCHIVE_SELLER']) && $param['ARCHIVE_SELLER'] !== NULL) { $sqlWhere[] = "ao.STATUS_ARCHIVED_SELLER = '".$param['ARCHIVE_SELLER']."' "; }
        if(isset($param['SHIPPING_PROVIDER']) && $param['SHIPPING_PROVIDER'] !== NULL) { $sqlWhere[] = "ao.SHIPPING_PROVIDER = '".mysql_real_escape_string($param['SHIPPING_PROVIDER'])."' "; }
        if(isset($param['STATUS_CONFIRMATION']) && $param['STATUS_CONFIRMATION'] !== NULL) { $sqlWhere[] = "ao.STATUS_CONFIRMATION = '".$param['STATUS_CONFIRMATION']."' "; }
        if(isset($param['STATUS_PAYMENT']) && $param['STATUS_PAYMENT'] !== NULL) { $sqlWhere[] = "ao.STATUS_PAYMENT = '".$param['STATUS_PAYMENT']."' "; }
        if(isset($param['STATUS_SHIPPING']) && $param['STATUS_SHIPPING'] !== NULL) { $sqlWhere[] = "ao.STATUS_SHIPPING = '".$param['STATUS_SHIPPING']."' "; }
        if(isset($param['BUYER_NAME']) && $param['BUYER_NAME'] != NULL) { $sqlWhere[] = "u2.NAME LIKE '%".mysql_real_escape_string($param['BUYER_NAME'])."%' "; }

        if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
            if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) { 
							$sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; 
						} else { 
							$sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; 
						}
        }
        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }

        if(isset($param['ONLY_READ_ID'])) {
            $sqlFields = "
                ao.ID_AD_ORDER";
        } else {
            $sqlFields = "
				ao.*,
				ads.PAYED,
				COUNT(ads.ID_AD_SOLD) as ARTICLE_COUNT,
				u1.ID_USER as USER_VK_ID,
				u1.NAME as USER_VK_NAME,
				u2.ID_USER as USER_EK_ID,
				u2.NAME as USER_EK_NAME,
				u2.FIRMA as USER_EK_FIRMA,
				u2.UST_ID as USER_EK_UST_ID,
				u2.VORNAME as USER_EK_VORNAME,
				u2.NACHNAME as USER_EK_NACHNAME,
				u2.EMAIL as USER_EK_EMAIL,
				u2.PLZ as USER_EK_PLZ,
				u2.ORT as USER_EK_ORT,
				u2sc.V1 as USER_EK_LAND,
				uc1.ZAHLUNG as USER_VK_ZAHLUNGSINFORMATIONEN,
				pas.V1 AS PAYMENT_ADAPTER_NAME";
            if (isset($param["PAID_DOWNLOADS"])){ if ( $param["PAID_DOWNLOADS"] == 1 ) { $sqlFields .= ',count(ID_AD_UPLOAD) as COUNT_PAID_DIGITAL_DOWNLOADS '; }}
            $sqlJoin .= "
			LEFT JOIN payment_adapter pa ON ao.FK_PAYMENT_ADAPTER = pa.ID_PAYMENT_ADAPTER
			LEFT JOIN string_payment_adapter pas ON pas.S_TABLE='payment_adapter' AND pas.FK=pa.ID_PAYMENT_ADAPTER
                AND pas.BF_LANG=if(pa.BF_LANG_PAYMENT_ADAPTER & " . $language . ", " . $language . ", 1 << floor(log(pa.BF_LANG_PAYMENT_ADAPTER+0.5)/log(2)))
			JOIN user u1 ON u1.ID_USER = ao.FK_USER_VK
			JOIN user u2 ON u2.ID_USER = ao.FK_USER
            LEFT JOIN country u2c ON u2c.ID_COUNTRY=u2.FK_COUNTRY
            LEFT JOIN string u2sc ON u2sc.S_TABLE='country' AND u2sc.FK=u2c.ID_COUNTRY AND u2sc.BF_LANG=if(u2c.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(u2c.BF_LANG+0.5)/log(2)))";
	        if (isset($param["PAID_DOWNLOADS"])){
	        	if ( $param["PAID_DOWNLOADS"] == 1 ) {
	        	    $sqlJoin .= ' LEFT JOIN ad_upload adu
									ON adu.FK_AD = ads.FK_AD
									AND adu.IS_PAID = 1 ';
	        	    $having .= ' HAVING COUNT_PAID_DIGITAL_DOWNLOADS > 0';
	        	}
	        }
            $sqlJoin .= " JOIN usercontent uc1 ON u1.ID_USER = uc1.FK_USER";
        }


			// Plugin event
			$eventOrderSalesSearchQuery = new Api_Entities_EventParamContainer(array(
					"fields"        => $sqlFields,
					"joins"        	=> $sqlJoin,
					"where"					=> $sqlWhere,
					"order"					=> $sqlOrder,
					"limit"					=> $sqlLimit
			));
			Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_ORDER_SALES_SEARCH_QUERY, $eventOrderSalesSearchQuery);
			if ($eventOrderSalesSearchQuery->isDirty()) {
					$sqlFields = $eventOrderSalesSearchQuery->getParam("fields");
					$sqlJoin = $eventOrderSalesSearchQuery->getParam("joins");
					$sqlWhere = $eventOrderSalesSearchQuery->getParam("where");
					$sqlOrder = $eventOrderSalesSearchQuery->getParam("order");
					$sqlLimit = $eventOrderSalesSearchQuery->getParam("limit");
			}

			$sql = "
				SELECT ".$sqlFields."
				FROM ad_order ao
				LEFT JOIN ad_sold ads ON ads.FK_AD_ORDER = ao.ID_AD_ORDER
				".$sqlJoin."
				".(!empty($sqlWhere) ? "WHERE ".implode(" AND ", $sqlWhere) : "")."
				GROUP BY ao.ID_AD_ORDER ";
			if ( !empty($having) ) {
				$sql .= $having;
			}
			$sql .= " ORDER BY ".$sqlOrder."
				".($sqlLimit?'LIMIT '.$sqlLimit:'');

			return $sql;
    }

    public function getQueryItems($param = array()) {
        global $langval;
        $language = ($param['BF_LANG'] > 0 ? $param['BF_LANG'] : $langval);
        $sqlLimit = "";
        $sqlWhere = array();
        $sqlJoin = "";
        $sqlOrder = " ao.STAMP_CREATE DESC ";

        if(isset($param['FK_AD_ORDER']) && $param['FK_AD_ORDER'] != NULL) {
            if (is_array($param['FK_AD_ORDER'])) {
                $sqlWhere[] = "ads.FK_AD_ORDER IN (".implode(", ", $param['FK_AD_ORDER']).")";
            } else {
                $sqlWhere[] = "ads.FK_AD_ORDER = '".(int)$param['FK_AD_ORDER']."' ";
            }
        }
        if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
            if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
        }
        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }
				
				// Plugin event
				$eventOrderSalesSearchQueryItems = new Api_Entities_EventParamContainer(array(
						"joins"        	=> $sqlJoin,
						"where"					=> $sqlWhere,
						"order"					=> $sqlOrder,
						"limit"					=> $sqlLimit
				));
				Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_ORDER_SALES_SEARCH_QUERY_ITEMS, $eventOrderSalesSearchQueryItems);
				if ($eventOrderSalesSearchQueryItems->isDirty()) {
						$sqlJoin = $eventOrderSalesSearchQueryItems->getParam("joins");
						$sqlWhere = $eventOrderSalesSearchQueryItems->getParam("where");
						$sqlOrder = $eventOrderSalesSearchQueryItems->getParam("order");
						$sqlLimit = $eventOrderSalesSearchQueryItems->getParam("limit");
				}

        return "
            SELECT
                ads.*,
                (ads.PREIS/ads.MENGE) AS PREIS_STUECK,
                adm.ID_AD_MASTER AS AD_ID,
                adm.FK_KAT,
                adm.BESCHREIBUNG AS AD_BESCHREIBUNG,
                adm.PRODUKTNAME AS AD_PRODUKTNAME,
                (adm.MENGE - ads.MENGE) as MENGE_LEFT,
                manufacturers.`NAME` AS AD_MANUFACTURER,
                i.SRC AS AD_SRC,
                i.SRC_THUMB AS AD_SRC_THUMB,
                rating_buyer.RATING AS RATING_BUYER,
                rating_seller.RATING AS RATING_SELLER,
                sci.V1 as VERSAND_LAND,
                scv.V1 as INVOICE_LAND
            FROM ad_sold ads
            LEFT JOIN ad_master adm ON adm.ID_AD_MASTER = ads.FK_AD
            LEFT JOIN ad_images i ON adm.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
            LEFT JOIN
                ad_sold_rating rating_buyer ON rating_buyer.FK_AD_SOLD=ads.ID_AD_SOLD
                AND rating_buyer.FK_USER_FROM=ads.FK_USER
            LEFT JOIN
                ad_sold_rating rating_seller ON rating_seller.FK_AD_SOLD=ads.ID_AD_SOLD
                AND rating_seller.FK_USER_FROM=ads.FK_USER_VK
            LEFT JOIN country ci ON ci.ID_COUNTRY=ads.VERSAND_FK_COUNTRY
            LEFT JOIN country cv ON cv.ID_COUNTRY=ads.INVOICE_FK_COUNTRY
            LEFT JOIN string sci ON sci.S_TABLE='country' AND sci.FK=ci.ID_COUNTRY AND sci.BF_LANG=if(ci.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(ci.BF_LANG+0.5)/log(2)))
            LEFT JOIN string scv ON scv.S_TABLE='country' AND scv.FK=cv.ID_COUNTRY AND scv.BF_LANG=if(cv.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(cv.BF_LANG+0.5)/log(2)))
            LEFT JOIN manufacturers on manufacturers.ID_MAN = adm.FK_MAN
			".$sqlJoin."
			".(!empty($sqlWhere) ? "WHERE ".implode(" AND ", $sqlWhere) : "")."
			GROUP BY ads.ID_AD_SOLD
			ORDER BY ".$sqlOrder."
			".($sqlLimit?'LIMIT '.$sqlLimit:'');
    }

	public function fetchAllByParam($param) {
        global $langval;
		$db = $this->getDb();
		$sqlQuery = $this->getQuery($param);
		
		$orders = $db->fetch_table($sqlQuery);

		foreach($orders as $key => $order) {
			if ( isset($param["PAID_DOWNLOADS"]) ) {
				if ($param["PAID_DOWNLOADS"] == 1) {
					$sql = "SELECT value
								FROM `option` 
								WHERE `plugin` = 'MARKTPLATZ' 
								AND `typ` = 'PAID_DOWNLOAD_LIFETIME'";
					$download_lifetime = $db->fetch_atom( $sql );

					$sql = 'SELECT count(1) as count, DATEDIFF(a.STAMP_PAYED,( CURDATE() - INTERVAL '.$download_lifetime.' DAY )) as remaining_time
								FROM ad_sold a
								WHERE a.FK_AD_ORDER = '.$order["ID_AD_ORDER"].'
								AND a.PAYED = 1
								AND a.STAMP_PAYED >= ( CURDATE() - INTERVAL '.$download_lifetime.' DAY )
								LIMIT 1';
					 $r1 = $db->fetch1( $sql );
					 $download_allowed = $r1["count"];
					 $remaning_time = $r1["remaining_time"];

					$param['DOWNLOAD_ALLOWED'] = $download_allowed;
					$orders[$key]['DOWNLOAD_ALLOWED'] = $download_allowed;
					$orders[$key]['REMAINING_TIME'] = $remaning_time;
				}
			}
			$orders[$key]['items'] = $this->fetchItemsForOrder($order['ID_AD_ORDER'],$param);

			$orders[$key]['ITEM_PRICE'] = $this->calculateItemPriceByItems($orders[$key]['items']);
			$orders[$key]['SHIPPING_PRICE'] = $this->calculateShippingCostByItems($orders[$key]['items']);
			$orders[$key]['TOTAL_PRICE'] = $orders[$key]['ITEM_PRICE'] + $orders[$key]['SHIPPING_PRICE'];
		}

		return $orders;
	}

	public function countByParam($param) {
		$db = $this->getDb();

		$sqlLimit = "";
		$sqlWhere = " ";
		$sqlJoin = "";
		$sqlOrder = " v.ID_VENDOR ";

		if(isset($param['USER_BUYER']) && $param['USER_BUYER'] != NULL) { $sqlWhere .= "AND ads.FK_USER = '".(int)$param['USER_BUYER']."' AND ao.FK_USER = '".(int)$param['USER_BUYER']."' "; }
		if(isset($param['USER_SELLER']) && $param['USER_SELLER'] != NULL) { $sqlWhere .= "AND ads.FK_USER_VK = '".(int)$param['USER_SELLER']."' AND ao.FK_USER_VK = '".(int)$param['USER_SELLER']."' "; }
		if(isset($param['ARCHIVE']) && $param['ARCHIVE'] !== NULL) { $sqlWhere .= "AND ao.STATUS_ARCHIVED = '".$param['ARCHIVE']."' "; }
		if(isset($param['ARCHIVE_SELLER']) && $param['ARCHIVE_SELLER'] !== NULL) { $sqlWhere .= "AND ao.STATUS_ARCHIVED_SELLER = '".$param['ARCHIVE_SELLER']."' "; }
		if(isset($param['STAMP_CREATE_FROM']) && $param['STAMP_CREATE_FROM'] != null) { $sqlWhere .= " AND DATE(ao.STAMP_CREATE) >= STR_TO_DATE('".mysql_real_escape_string($param['STAMP_CREATE_FROM'])."', '%d.%m.%Y') "; }
		if(isset($param['STAMP_CREATE_TO']) && $param['STAMP_CREATE_TO'] != null) { $sqlWhere .= " AND DATE(ao.STAMP_CREATE) <= STR_TO_DATE('".mysql_real_escape_string($param['STAMP_CREATE_TO'])."', '%d.%m.%Y') "; }
		if(isset($param['STATUS_CONFIRMATION']) && $param['STATUS_CONFIRMATION'] !== NULL) { $sqlWhere .= "AND ao.STATUS_CONFIRMATION = '".$param['STATUS_CONFIRMATION']."' "; }
		if(isset($param['STATUS_PAYMENT']) && $param['STATUS_PAYMENT'] !== NULL) { $sqlWhere .= "AND ao.STATUS_PAYMENT = '".$param['STATUS_PAYMENT']."' "; }
		if(isset($param['STATUS_SHIPPING']) && $param['STATUS_SHIPPING'] !== NULL) { $sqlWhere .= "AND ao.STATUS_SHIPPING = '".$param['STATUS_SHIPPING']."' "; }

		$q = "
			SELECT
				SQL_CALC_FOUND_ROWS
				ao.ID_AD_ORDER
			FROM ad_order ao
			LEFT JOIN ad_sold ads ON ads.FK_AD_ORDER = ao.ID_AD_ORDER
			JOIN user u1 ON u1.ID_USER = ao.FK_USER_VK
			JOIN usercontent uc1 ON u1.ID_USER = uc1.FK_USER
			".$sqlJoin."
			WHERE
				1=1
				".($sqlWhere?' '.$sqlWhere:'')."
			GROUP BY ao.ID_AD_ORDER
		";

		$x = $db->querynow($q);
		$y = $db->fetch_atom("SELECT FOUND_ROWS()");

		return $y;
	}

	public function fetchById($orderId) {
		$userManagement = UserManagement::getInstance($this->getDb());

		$order = $this->getDb()->fetch1("SELECT * FROM ad_order WHERE ID_AD_ORDER = '".(int)$orderId."'");
        if ($order == null) {
            return null;    // Order not found
        }
		$order['items'] = $this->fetchItemsForOrder($orderId);
        $order['SHOW_MWST'] = 1;
		
		if(is_array($order['items']) && count($order['items']) > 0) {
			$tmpItem = $order['items']['0'];
			$order = array_merge($order, array(
				'ADDRESS_INVOICE_FIRMA' => $tmpItem['INVOICE_FIRMA'],
				'ADDRESS_INVOICE_VORNAME' => $tmpItem['INVOICE_VORNAME'],
				'ADDRESS_INVOICE_NACHNAME' => $tmpItem['INVOICE_NACHNAME'],
				'ADDRESS_INVOICE_STRASSE' => $tmpItem['INVOICE_STRASSE'],
				'ADDRESS_INVOICE_PLZ' => $tmpItem['INVOICE_PLZ'],
				'ADDRESS_INVOICE_ORT' => $tmpItem['INVOICE_ORT'],
				'ADDRESS_INVOICE_LAND' => $tmpItem['INVOICE_LAND'],
				'ADDRESS_VERSAND_FIRMA' => $tmpItem['VERSAND_FIRMA'],
				'ADDRESS_VERSAND_VORNAME' => $tmpItem['VERSAND_VORNAME'],
				'ADDRESS_VERSAND_NACHNAME' => $tmpItem['VERSAND_NACHNAME'],
				'ADDRESS_VERSAND_STRASSE' => $tmpItem['VERSAND_STRASSE'],
				'ADDRESS_VERSAND_PLZ' => $tmpItem['VERSAND_PLZ'],
				'ADDRESS_VERSAND_ORT' => $tmpItem['VERSAND_ORT'],
				'ADDRESS_VERSAND_LAND' => $tmpItem['VERSAND_LAND']
			));
            // Prüfen ob die MwSt bei allen items gleich eingestellt ist
            $order['MWST'] = $tmpItem["MWST"];
            foreach ($order['items'] as $itemIndex => $arItem) {
                if ($arItem['MWST'] != $order['MWST']) {
                    $order['SHOW_MWST'] = 0;
                }
            }
        }

		$seller = $userManagement->fetchFullDatasetById($order['FK_USER_VK']);
		$buyer = $userManagement->fetchFullDatasetById($order['FK_USER']);

		foreach($seller as $key => $data) { $order['SELLER_DATA_'.$key] = $data; }
		foreach($buyer as $key => $data) { $order['BUYER_DATA_'.$key] = $data; }

		$confirmationData = $this->getOrderConfirmationArrayByItems($order['items']);
		$order = array_merge($order, $confirmationData);

		$order['ITEM_PRICE'] = $this->calculateItemPriceByItems($order['items']);
		$order['SHIPPING_PRICE'] = $this->calculateShippingCostByItems($order['items']);
		$order['TOTAL_PRICE'] = $order['ITEM_PRICE'] + $order['SHIPPING_PRICE'];

		return $order;
	}

    public function fetchItemById($soldId) {
        global $langval;
        
        return $this->getDb()->fetch1("
            SELECT
                ads.*,
                (ads.PREIS/ads.MENGE) AS PREIS_STUECK,
                adm.ID_AD_MASTER AS AD_ID,
                adm.BESCHREIBUNG AS AD_BESCHREIBUNG,
                adm.PRODUKTNAME AS AD_PRODUKTNAME,
                (adm.MENGE - ads.MENGE) as MENGE_LEFT,
                manufacturers.`NAME` AS AD_MANUFACTURER,
                i.SRC AS AD_SRC,
                i.SRC_THUMB AS AD_SRC_THUMB,
                rating_buyer.RATING AS RATING_BUYER,
                rating_seller.RATING AS RATING_SELLER,
                sci.V1 as VERSAND_LAND,
                scv.V1 as INVOICE_LAND
            FROM ad_sold ads
            LEFT JOIN ad_master adm ON adm.ID_AD_MASTER = ads.FK_AD
            LEFT JOIN ad_images i ON adm.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
            LEFT JOIN
                ad_sold_rating rating_buyer ON rating_buyer.FK_AD_SOLD=ads.ID_AD_SOLD
                AND rating_buyer.FK_USER_FROM=ads.FK_USER
            LEFT JOIN
                ad_sold_rating rating_seller ON rating_seller.FK_AD_SOLD=ads.ID_AD_SOLD
                AND rating_seller.FK_USER_FROM=ads.FK_USER_VK
            LEFT JOIN country ci ON ci.ID_COUNTRY=ads.VERSAND_FK_COUNTRY
            LEFT JOIN country cv ON cv.ID_COUNTRY=ads.INVOICE_FK_COUNTRY
            LEFT JOIN string sci ON sci.S_TABLE='country' AND sci.FK=ci.ID_COUNTRY AND sci.BF_LANG=if(ci.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(ci.BF_LANG+0.5)/log(2)))
            LEFT JOIN string scv ON scv.S_TABLE='country' AND scv.FK=cv.ID_COUNTRY AND scv.BF_LANG=if(cv.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(cv.BF_LANG+0.5)/log(2)))
            LEFT JOIN manufacturers on manufacturers.ID_MAN = adm.FK_MAN
            WHERE
                ID_AD_SOLD = '".(int)$soldId."'
        ");
    }

    public function fetchItemsForOrder($orderId, $param = null) {
        global $langval;

        $sql = "
            SELECT
                ads.*,
                (ads.PREIS/ads.MENGE) AS PREIS_STUECK,
                adm.ID_AD_MASTER AS AD_ID,
                adm.AD_TABLE AS AD_TABLE,
                adm.FK_KAT,
                adm.BESCHREIBUNG AS AD_BESCHREIBUNG,
                adm.PRODUKTNAME AS AD_PRODUKTNAME,
                adm.NOTIZ AS AD_NOTIZ,
                (adm.MENGE - ads.MENGE) as MENGE_LEFT,
                manufacturers.`NAME` AS AD_MANUFACTURER,
                i.SRC AS AD_SRC,
                i.SRC_THUMB AS AD_SRC_THUMB,
                rating_buyer.RATING AS RATING_BUYER,
                rating_seller.RATING AS RATING_SELLER,
                sci.V1 as VERSAND_LAND,
                scv.V1 as INVOICE_LAND";
        if ( isset($param["PAID_DOWNLOADS"]) ) {
	        if ( $param["PAID_DOWNLOADS"] == 1 ) {
		        $sql .= " , adu.* ";
	        }
        }
        $sql .= "
            FROM ad_sold ads
            LEFT JOIN ad_master adm ON adm.ID_AD_MASTER = ads.FK_AD
            LEFT JOIN ad_images i ON adm.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
            LEFT JOIN
                ad_sold_rating rating_buyer ON rating_buyer.FK_AD_SOLD=ads.ID_AD_SOLD
                AND rating_buyer.FK_USER_FROM=ads.FK_USER
            LEFT JOIN
                ad_sold_rating rating_seller ON rating_seller.FK_AD_SOLD=ads.ID_AD_SOLD
                AND rating_seller.FK_USER_FROM=ads.FK_USER_VK
            LEFT JOIN country ci ON ci.ID_COUNTRY=ads.VERSAND_FK_COUNTRY
            LEFT JOIN country cv ON cv.ID_COUNTRY=ads.INVOICE_FK_COUNTRY
            LEFT JOIN string sci ON sci.S_TABLE='country' AND sci.FK=ci.ID_COUNTRY AND sci.BF_LANG=if(ci.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(ci.BF_LANG+0.5)/log(2)))
            LEFT JOIN string scv ON scv.S_TABLE='country' AND scv.FK=cv.ID_COUNTRY AND scv.BF_LANG=if(cv.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(cv.BF_LANG+0.5)/log(2)))
            LEFT JOIN manufacturers on manufacturers.ID_MAN = adm.FK_MAN";
        if ( isset($param["PAID_DOWNLOADS"]) ) {
	        if ( $param['PAID_DOWNLOADS'] == 1 ) {
		        $sql .= ' LEFT JOIN ad_upload adu
        		            ON adu.FK_AD = ads.FK_AD ';
	        }
        }
        $sql .= "
            WHERE
                FK_AD_ORDER = '".(int)$orderId."'
        ";
        if ( isset($param['PAID_DOWNLOADS']) ) {
	        if ( $param['PAID_DOWNLOADS'] == 1 ) {
		        $sql .= ' AND adu.IS_PAID = 1 ';
	        }
        }

        $arItems = $this->getDb()->fetch_table( $sql );
        $previous_id_sold = 0;
        $previous_index = 0;
        $go = true;
        $add_items = false;
			foreach ($arItems as $itemIndex => $itemData) {
				//
				if ( isset($param['PAID_DOWNLOADS']) ) {
					if ( $param['PAID_DOWNLOADS'] == 1 ) {
						$add_items = true;
						if ( $previous_id_sold == 0 || $previous_id_sold != $itemData["ID_AD_SOLD"] )
						{
							$previous_id_sold = $itemData["ID_AD_SOLD"];
							$go = true;
							$previous_index = $itemIndex;
						}
						else {
							$go = false;
						}
					}
				}
				if ( $go ) {
					if ($itemData["AD_ID"] > 0) {
						$arItems[$itemIndex] = array_merge($itemData, $this->getDb()->fetch1("
						SELECT 
							FK_ARTICLE_EXT
						FROM `".mysql_real_escape_string($itemData["AD_TABLE"])."`
						WHERE ID_".mysql_real_escape_string(strtoupper($itemData["AD_TABLE"]))."=".(int)$itemData["AD_ID"]));
					}
				}
				if ( $add_items == true ) {
					if ( isset($param["PAID_DOWNLOADS"]) ) {
						if ( $param["PAID_DOWNLOADS"] == 1 ) {
							$upload_item = array(
								"ID_AD_UPLOAD"      =>  $itemData["ID_AD_UPLOAD"],
								"CUSTOM"            =>  $itemData["CUSTOM"],
								"SRC"               =>  $itemData["SRC"],
								"FILENAME"          =>  $itemData["FILENAME"],
								"EXT"               =>  $itemData["EXT"],
								"IS_PAID"           =>  $itemData["IS_PAID"],
								"FK_ARTICLE_EXT"    =>  $itemData["FK_ARTICLE_EXT"],
								"DOWNLOAD_ALLOWED"  =>  $param["DOWNLOAD_ALLOWED"]
							);
							$arItems[$previous_index]["PAID_DOWNLOAD_FILES"][] = $upload_item;
						}
					}
				}
				if ( $go == false ) {
					unset( $arItems[$itemIndex] );
				}
			}
			return $arItems;
    }

	public function sendStatusMailToUser($orderId, $mailType, $additionalData = array()) {
		$emailData = $this->_getEmailData($orderId, $additionalData);
		sendMailTemplateToUser(0, $emailData['EK_ID_USER'], $mailType, $emailData);
	}

	public function sendStatusMailToSeller($orderId, $mailType, $additionalData = array()) {
		$emailData = $this->_getEmailData($orderId, $additionalData);
		sendMailTemplateToUser(0, $emailData['VK_ID_USER'], $mailType, $emailData);
	}

	protected function _getEmailData($orderId, $additionalData = array()) {
		$order = $this->fetchById($orderId);
		$userData =  $this->getDb()->fetch1("
			SELECT
				vk.NAME as VK_NAME, vk.VORNAME as VK_VORNAME, vk.NACHNAME as VK_NACHNAME, vk.ID_USER as VK_ID_USER,
				ek.NAME as EK_NAME, ek.VORNAME as EK_VORNAME, ek.NACHNAME as EK_NACHNAME, ek.ID_USER as EK_ID_USER,
				ek.IS_VIRTUAL as EK_VIRTUAL, MD5(CONCAT(ek.NAME,ek.SALT,ek.EMAIL)) as EK_ACCESS_HASH
			FROM ad_order
			JOIN user vk ON vk.ID_USER = ad_order.FK_USER_VK
			JOIN user ek ON ek.ID_USER = ad_order.FK_USER
			WHERE ID_AD_ORDER = '".mysql_real_escape_string($orderId)."'
		");

		foreach($order['items'] as $key => $item) {
			$order['ORDER_ARTICLE_LIST'] .= "- ".$item['MENGE']."x: ".$item['AD_MANUFACTURER']." ".$item['PRODUKTNAME']."\n";
		}

		return array_merge($order, $userData, $additionalData);
	}

	public function existOrderForUserId($orderId, $userId) {
		return ($this->getDb()->fetch_atom("SELECT COUNT(*) AS c FROM ad_order WHERE ID_AD_ORDER = '".(int)$orderId."' AND FK_USER = '".(int)$userId."'") > 0);
	}

	public function existSellerOrderForUserId($orderId, $userId) {
		return ($this->getDb()->fetch_atom("SELECT COUNT(*) AS c FROM ad_order WHERE ID_AD_ORDER = '".(int)$orderId."' AND FK_USER_VK = '".(int)$userId."'") > 0);
	}

	public function calculateShippingCostByItems($orderItems) {
		$shipping = 0;
		foreach($orderItems as $key => $item) {
            if ($item['VERSANDOPTIONEN'] == 3) {
				$shipping = max($shipping, $item['VERSANDKOSTEN']);
			}
		}
		return $shipping;
	}

	protected function calculateItemPriceByItems($orderItems) {
		$sum = 0;
		foreach($orderItems as $key => $item) {
			$sum += $item['PREIS'];
		}
		return $sum;
	}

	protected function isOrderConfirmedByItems($orderItems) {
		$confirmed = TRUE;
		foreach($orderItems as $key => $item) {
			if ($item['CONFIRMED'] == 0) {
				$confirmed = FALSE;
			}
		}
		return $confirmed;
	}

	protected function isOrderConfirmationOpenByItems($orderItems) {
		$confirmationOpen = TRUE;
		foreach($orderItems as $key => $item) {
			if ($item['CONFIRMED'] == 0) {
				$confirmationOpen = FALSE;
			}
		}
		return $confirmationOpen;
	}

	protected function isOrderPartialConfirmedByItems($orderItems) {
		$partialConfirmed = FALSE;
		foreach($orderItems as $key => $item) {
			if ($item['CONFIRMED'] == 1) {
				$partialConfirmed = TRUE;
			}
		}

		return $partialConfirmed;
	}

	public function getOrderConfirmationArrayByItems($items) {
		$orderConfirmationPartialOpen = FALSE;
		$orderConfirmationOpen = TRUE;
		$orderPartialConfirmed = FALSE;
		$orderConfirmed = TRUE;
		$orderPartialDeclined = FALSE;
		$orderDeclined = TRUE;

		foreach($items as $key => $item) {
			if($item['CONFIRMED'] == 0) { $orderConfirmationPartialOpen = TRUE; $orderConfirmed = FALSE; $orderDeclined = FALSE; }
			if($item['CONFIRMED'] == 1) { $orderConfirmationOpen = FALSE; $orderPartialConfirmed = TRUE; $orderDeclined = FALSE; }
			if($item['CONFIRMED'] == 2) { $orderConfirmationOpen = FALSE; $orderConfirmed = FALSE; $orderPartialDeclined = TRUE; }
		}

		return array(
			'ORDER_CONFIRMATION_OPEN' => $orderConfirmationOpen,
			'ORDER_CONFIRMATION_PARTIAL_OPEN' => $orderConfirmationPartialOpen,
			'ORDER_CONFIRMED' => $orderConfirmed,
			'ORDER_PARTIAL_CONFIRMED' => $orderPartialConfirmed,
			'ORDER_DECLINED' => $orderDeclined,
			'ORDER_PARTIAL_DECLINED' => $orderPartialDeclined
		);
	}


	public function getTrackingUrlByOrder($order) {
		global $langval;

		if (($order['SHIPPING_TRACKING_SERVICE'] > 0) && !empty($order['SHIPPING_TRACKING_CODE'])) {
			$url = $this->getDb()->fetch_atom("
				SELECT s.V1 FROM lookup l
				LEFT JOIN string s ON s.S_TABLE='lookup' AND s.FK=l.ID_LOOKUP
					AND s.BF_LANG=if(l.BF_LANG & " . $langval . ", " . $langval . ", 1 << floor(log(l.BF_LANG+0.5)/log(2)))
				WHERE
					l.art = 'TRACK_URL' AND l.`ID_LOOKUP` = '" . (int)$order['SHIPPING_TRACKING_SERVICE'] . "'");

			$url = str_replace('{TRACKINGCODE}', $order['SHIPPING_TRACKING_CODE'], $url);

			return $url;
		} else {
			return null;
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


function callback_order_addOrderItemsBuyer(&$row) {
	global $s_lang, $db, $langval, $nar_systemsettings, $paymentAdapterManagement, $adOrderManagement;
    $userManagement = UserManagement::getInstance($db);

	$ar_items = array();

	$buyingEnabled = FALSE;
	$orderPaymentAdapter = $paymentAdapterManagement->fetchById($row['FK_PAYMENT_ADAPTER']);

	$orderConformationData = $adOrderManagement->getOrderConfirmationArrayByItems($row['items']);
	$row = array_merge($row, $orderConformationData);

	if(isset($row['items'])) {
		$orderItemTemplateConfirmed = '';
		$orderItemTemplateNotConfirmed = '';

		foreach($row['items'] as $key => $item) {
			$ar_items[] = array(
					'DESCRIPTION' => $item['PRODUKTNAME'],
					'QUANTITY' => $item['MENGE'],
					'PRICE' => $item['PREIS'],
			);
			$item["ID_AD_ORDER"] = $row["ID_AD_ORDER"];

			// Varianten
			$ar_variant = (isset($item["SER_VARIANT"]) ? unserialize($item["SER_VARIANT"]) : array());
			$ar_variant_list = array();
			foreach ($ar_variant as $index => $ar_current) {
				$value = $db->fetch_atom("SELECT sl.V1 FROM `liste_values` t
						LEFT JOIN `string_liste_values` sl
							ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
							AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
						WHERE t.ID_LISTE_VALUES=".$ar_current["ID_LISTE_VALUES"]);
				if ($value !== FALSE) {
					$ar_variant_list[] = $value;
				} else {
					$ar_variant_list[] = $ar_current["VALUE"];
				}
			}
			$item["VARIANT"] = (empty($ar_variant_list) ? "" : implode(", ", $ar_variant_list));

			$ar_availability = ($item["SER_AVAILABILITY"] == null ? false : unserialize($item["SER_AVAILABILITY"]));
			$item['AVAILABILITY'] = ($ar_availability !== false);
			$item['AVAILABILITY_DATE_FROM'] = (is_array($ar_availability) ? $ar_availability['DATE_FROM'] : false);
			$item['AVAILABILITY_TIME_FROM'] = (is_array($ar_availability) ? $ar_availability['TIME_FROM'] : false);
			$item['AVAILABILITY_DATE_TO'] = (is_array($ar_availability) ? $ar_availability['DATE_TO'] : false);

			$item['AD_BESCHREIBUNG'] = substr(strip_tags(html_entity_decode($item['AD_BESCHREIBUNG'])), 0, 250);
			$item['AD_BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $item['AD_BESCHREIBUNG']);

			$tpl = new Template("tpl/".$s_lang."/my-marktplatz-einkaeufe.item.row.htm");
			$tpl->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
            $tpl->addvar("USER_IS_VIRTUAL", $userManagement->isUserVirtual());
			$tpl->addvars($item);
			$tpl->addvars($row, 'ORDER_');//DOWNLOAD_ALLOWED

			if ( isset($item["PAID_DOWNLOAD_FILES"]) ) {

				$tpl_downloads = new Template("tpl/".$s_lang."/my-marktplatz-einkaeufe.item.paid.downloads.htm");
				$tpl_downloads->addvar("index_num",$key);
				$tpl_downloads->addlist(
					"list_paid_download_files",
					$item["PAID_DOWNLOAD_FILES"],
					"tpl/".$s_lang."/my-marktplatz-einkaeufe.item.paid.donwloads.row.htm"
				);
				$tpl->addvar("DOWNLOAD_PAID_FILES",$tpl_downloads->process());
				$tpl->addvar("DOWNLOAD_ALLOWED",$row["DOWNLOAD_ALLOWED"]);
				$tpl->addvar("REMAINING_TIME",$row["REMAINING_TIME"]);
				// nach PAID_DOWNLOAD_REFUNDTIME schon abgelaufen Auto-Bestätigung
                if( (time() - strtotime($item["STAMP_BOUGHT"])) > ($nar_systemsettings["MARKTPLATZ"]["PAID_DOWNLOAD_REFUNDTIME"]*86400) ) {
                    $item["RENOUNCE_REFUND_RIGHT"] = 1;
                }
				$tpl->addvar("RENOUNCE_REFUND_RIGHT",$item["RENOUNCE_REFUND_RIGHT"]);
			}

			if($item['CONFIRMED'] == 1) {
				$orderItemTemplateConfirmed .= $tpl->process();
			} else {
				$orderItemTemplateNotConfirmed .= $tpl->process();
			}

			if(!($item['STATUS'] & 1)) {
				$orderIsAbgeschlossen = FALSE;
			}
		}

		$row['tplItems'] = $orderItemTemplateConfirmed;
		$row['tplItemsNotConfirmed'] = $orderItemTemplateNotConfirmed;
	}


	$row['ORDER_ABGESCHLOSSEN'] = $orderIsAbgeschlossen;


	if ($orderPaymentAdapter !== false) {
		$paymentAdapterConfiguration = array(
				'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($orderPaymentAdapter['ID_PAYMENT_ADAPTER'])
		);
		/** @var Payment_Adapter_PaymentAdapterInterface $paymentAdapter  */
		$paymentAdapter = Payment_PaymentFactory::factory($orderPaymentAdapter['ADAPTER_NAME'], $paymentAdapterConfiguration);
		$paymentObject = array(
			'TYPE' => 'AD_ORDER',
			'FK_USER' => $row["FK_USER"],
			'FK_SELLER' => $row['FK_USER_VK'],
			'DATA' => array(
					'AD_ORDER' => $row
			),
			'DESCRIPTION' => ''.$nar_systemsettings['SITE']['SITENAME'].' #'.$row['ID_AD_ORDER'],
			'TOTAL_PRICE' => $row['TOTAL_PRICE'],
			//'CURRENCY' => 'EUR',
			'ITEMS' => $ar_items
		);
		$paymentAdapter->init($paymentObject);
		$row['BUTTON'] = $paymentAdapter->buttonOrder();
	}
}

function callback_order_addOrderItems(&$row) {
	global $s_lang, $db, $langval, $nar_systemsettings, $paymentAdapterManagement, $userManagement, $adOrderManagement;

	$ar_items = array();
	$row['ORDER_PROV'] = 0;

	if(isset($row['items'])) {
		$orderItemTemplate = '';

		$orderConfirmationData = $adOrderManagement->getOrderConfirmationArrayByItems($row['items']);
		$row = array_merge($row, $orderConfirmationData);

		foreach($row['items'] as $key => $item) {
			// Varianten
			$ar_variant = (isset($item["SER_VARIANT"]) ? unserialize($item["SER_VARIANT"]) : array());
			$ar_variant_list = array();
			foreach ($ar_variant as $index => $ar_current) {
				$value = $db->fetch_atom("SELECT sl.V1 FROM `liste_values` t
						LEFT JOIN `string_liste_values` sl
							ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
							AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
						WHERE t.ID_LISTE_VALUES=".$ar_current["ID_LISTE_VALUES"]);
				if ($value !== FALSE) {
					$ar_variant_list[] = $value;
				} else {
					$ar_variant_list[] = $ar_current["VALUE"];
				}
			}
			$item["VARIANT"] = (empty($ar_variant_list) ? "" : implode(", ", $ar_variant_list));

			$ar_availability = ($item["SER_AVAILABILITY"] == null ? false : unserialize($item["SER_AVAILABILITY"]));
            $item['AVAILABILITY'] = ($ar_availability !== false);
            $item['AVAILABILITY_DATE_FROM'] = (is_array($ar_availability) ? $ar_availability['DATE_FROM'] : false);
            $item['AVAILABILITY_TIME_FROM'] = (is_array($ar_availability) ? $ar_availability['TIME_FROM'] : false);
            $item['AVAILABILITY_DATE_TO'] = (is_array($ar_availability) ? $ar_availability['DATE_TO'] : false);


			$item['AD_BESCHREIBUNG'] = substr(strip_tags(html_entity_decode($item['AD_BESCHREIBUNG'])), 0, 250);
			$item['AD_BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $item['AD_BESCHREIBUNG']);

			$tpl = new Template("tpl/".$s_lang."/my-marktplatz-verkaeufe.item.row.htm");
			$tpl->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
			$tpl->addvars($item);
			$tpl->addvars($row, 'ORDER_');

			$orderItemTemplate .= $tpl->process();

			if(!($item['STATUS'] & 1)) {
				$orderIsAbgeschlossen = FALSE;
			}
			$row['ORDER_PROV'] += $item['PROV'] * $item['MENGE'];
		}

		$row['tplItems'] = $orderItemTemplate;
	}
	$row['ORDER_ABGESCHLOSSEN'] = $orderIsAbgeschlossen;
}