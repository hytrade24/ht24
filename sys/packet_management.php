
<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/packet_order/ad_once.php";
require_once $ab_path."sys/packet_order/ad_recurring.php";
require_once $ab_path."sys/packet_order/ad_top_recurring.php";
require_once $ab_path."sys/packet_order/ad_top_pin_recurring.php";
require_once $ab_path."sys/packet_order/ad_top_slider_recurring.php";
require_once $ab_path."sys/packet_order/ad_top_color_recurring.php";
require_once $ab_path."sys/packet_order/ad_top_custom_recurring.php";
require_once $ab_path."sys/packet_order/image_once.php";
require_once $ab_path."sys/packet_order/image_recurring.php";
require_once $ab_path."sys/packet_order/collection_once.php";
require_once $ab_path."sys/packet_order/collection_recurring.php";
require_once $ab_path."sys/packet_order/membership_once.php";
require_once $ab_path."sys/packet_order/membership_recurring.php";
require_once $ab_path."sys/packet_order/job_once.php";
require_once $ab_path."sys/packet_order/job_recurring.php";
require_once $ab_path."sys/packet_order/download_once.php";
require_once $ab_path."sys/packet_order/download_recurring.php";
require_once $ab_path."sys/packet_order/news_once.php";
require_once $ab_path."sys/packet_order/news_recurring.php";
require_once $ab_path."sys/packet_order/usergroup_once.php";
require_once $ab_path."sys/packet_order/usergroup_recurring.php";
require_once $ab_path."sys/packet_order/vendor_top_recurring.php";
require_once $ab_path."sys/packet_order/video_once.php";
require_once $ab_path."sys/packet_order/video_recurring.php";
require_once $ab_path."sys/packet_order/lead_once.php";
require_once $ab_path."sys/packet_order/lead_recurring.php";

class PacketManagement {
	/**
	 * Ids von festgelegten Basispaketen
	 * @var array
	 */
	public static $types = array(
		"ad_abo"				=> 1,
		"ad_once"				=> 2,
		"image_once"			=> 3,
		"image_abo"				=> 4,
		"video_once"			=> 5,
		"video_abo"				=> 6,
		"download_once"			=> 7,
		"download_abo"			=> 8,
		"job_once"				=> 9,
		"job_abo"				=> 10,
		"news_once"				=> 11,
		"news_abo"				=> 12,
		"usergroup_once"		=> 13,
		"usergroup_abo"			=> 14,
		"ad_top_abo"			=> 35,
		"vendor_top_abo"		=> 36,
		"ad_top_pin_abo"		=> 197,
		"ad_top_slider_abo"		=> 198,
		"ad_top_color_abo"		=> 199,
		"ad_top_custom_abo"		=> 200,
		"lead_once"				=> 211,
		"lead_abo"				=> 227
	);

	public static function getType($strType) {
		return self::$types[$strType];
	}

    /**
     * Returns the ids of all packet components
     * @return array
     */
    public static function getComponentTypes() {
        return array(35,36,197,198,199,200);
    }

	private static $instance = NULL;

	/**
	 * Array mit allen erlaubten Intervallen
	 *
	 * @var array
	 */
	private static $ar_recurring = array('ONCE','DAY','WEEK','MONTH','QUARTER','YEAR');	// Erlaubte intervalle

	/**
	* Singleton
	 *
	 * @param ebiz_db $db
	 * @return PacketManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === NULL) {
			self::$instance = new self($db);
		}
		return self::$instance;
	}

	/**
	 * Datenbankobjekt des ebiz-trader
	 *
	 * @var ebiz_db
	 */
	private $database = FALSE;

	/**
	* Array mit allen aufgetretenen Fehlern
	*
	* @var array
	*/
	private $ar_errors = array();

	/**
	 * Konstruktor
	 *
	 * @param ebiz_db $db
	 */
	function __construct(ebiz_db $db) {
		$this->database = $db;
	}

    /**
     * Kündigt alle Pakete die nicht mehr zur Verfügung stehen
     * @param null $userId
     */
    public function cancelUnavaiablePackets($userId = null) {
        if ($userId === null) {
            $userId = $GLOBALS["uid"];
        }
        $arPackets = $this->database->fetch_nar("
            SELECT
              o.ID_PACKET_ORDER, o.FK_PACKET
            FROM `packet_order` o
            JOIN `user` u ON u.ID_USER=o.FK_USER
            WHERE u.ID_USER=".(int)$userId." AND o.TYPE='COLLECTION' AND STAMP_NEXT IS NOT NULL
              AND u.FK_USERGROUP NOT IN (SELECT FK_USERGROUP FROM `packet_group` WHERE ID_PACKET=o.FK_PACKET)");
        foreach ($arPackets as $orderId => $packetId) {
            $order = $this->order_get($orderId);
            if ($order->isRecurring()) {

                $order->cancel();
            }
        }
        return true;
    }

	/**
	 * Bestellt das angegebene Paket
	 *
	 * @param      $id_packet_runtime
	 * @param      $id_user
	 * @param      $count
	 * @param null $fk_invoice
	 * @param null $fk_billableitem
	 * @param null $price
	 *
	 * @internal param array $ar_packet
	 * @internal param int $fk_paymenttype
	 */
	public function order($id_packet_runtime, $id_user, $count, $fk_invoice = NULL, $fk_billableitem = NULL, $price = NULL, $couponUsagage = null) {
		global $ab_path;

		require_once $ab_path."sys/lib.billing.invoice.php";
		$billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->database);

		$ar_packet = $this->getFull($id_packet_runtime);
		$id_packet = (int)$ar_packet["ID_PACKET"];

		$ar_usergroup = $this->database->fetch1("
			SELECT u.FK_USER_SALES, g.* FROM `user` u
				LEFT JOIN `usergroup` g ON g.ID_USERGROUP=u.FK_USERGROUP
			WHERE u.ID_USER=".$id_user);
		$userPrepaid = $billingInvoiceManagement->shouldChargeAtOnceByUserId($id_user);
		$status = 1;

		if ($price !== NULL) {
			$ar_packet["BILLING_PRICE"] = $price;
		}

		if($ar_packet["BILLING_PRICE"] > 0) {
			if ($ar_usergroup != FALSE) {
				$status = ($userPrepaid ? 0 : 1);
			} else {
				$status = 0;
			}
		}
		if (($fk_invoice == NULL) && ($fk_billableitem == NULL) && ($ar_packet["BILLING_PRICE"] > 0)) {
			if($userPrepaid) {
				$fk_invoice = $this->new_invoice($id_packet_runtime, $id_user, $count, $ar_packet["BILLING_PRICE"], $ar_usergroup["FK_USER_SALES"], $couponUsagage);
				if ($fk_invoice > 0) {
					$arInvoice = BillingInvoiceManagement::getInstance($this->database)->fetchById($fk_invoice);
					if ($arInvoice["STATUS"] == BillingInvoiceManagement::STATUS_PAID) {
						$status = 1;
					} 
					// Trigger api event for new invoice
					Api_TraderApiHandler::getInstance($GLOBALS['db'])->triggerEvent(
						Api_TraderApiEvents::PACKET_NEW_INVOICE, array(
							"ID_PACKET" => $id_packet, "ID_PACKET_RUNTIME" => $id_packet_runtime, "FK_USER" => $id_user, "COUNT" => $count, 
							"FK_INVOICE" => $fk_invoice, "FK_BILLING_BILLABLEITEM" => null
						)
					);
				}
			} else {
				$fk_billableitem = $this->new_billableitem($id_packet_runtime, $id_user, $count, $ar_packet["BILLING_PRICE"], $ar_usergroup["FK_USER_SALES"], $couponUsagage);
				if ($fk_billableitem > 0) {
					// Trigger api event for new billable item
					Api_TraderApiHandler::getInstance($GLOBALS['db'])->triggerEvent(
						Api_TraderApiEvents::PACKET_NEW_INVOICE, array(
							"ID_PACKET" => $id_packet, "ID_PACKET_RUNTIME" => $id_packet_runtime, "FK_USER" => $id_user, "COUNT" => $count, 
							"FK_INVOICE" => null, "FK_BILLING_BILLABLEITEM" => $fk_billableitem
						)
					);
				}
			}
		}
		
		// Check if invoice is paid upon creation
		if ($fk_invoice > 0) {
			$invoiceStatus = $this->database->fetch_atom("SELECT STATUS FROM `billing_invoice` WHERE ID_BILLING_INVOICE=" . (int)$fk_invoice);
			if ($invoiceStatus == BillingInvoiceManagement::STATUS_PAID) {
				$status = 1;
			}
		}

		if (($ar_packet["TYPE"] == "COLLECTION") || ($ar_packet["TYPE"] == "MEMBERSHIP")) {
			$ar_packet_ids = array();
			for ($i = 0; $i < $count; $i++) {
				// Hauptpaket bestellen
				$fk_collection = $this->order_element($id_packet, $id_packet_runtime, $id_user, 0, 1, NULL, $fk_invoice, $fk_billableitem, $ar_packet["BILLING_FACTOR"], $ar_packet["BILLING_CYCLE"], $ar_packet["BILLING_CANCEL_DAYS"], $ar_packet["RUNTIME_FACTOR"], $ar_packet["BILLING_PRICE"], NULL);
				// Alle Paketbestandteile hinzufügen
				$ar_packets_contained = $this->database->fetch_table(
					"SELECT FK_PACKET, COUNT, PARAMS FROM `packet_collection` WHERE ID_PACKET=".(int)$id_packet
				);
				foreach ($ar_packets_contained as $index => $ar_sub) {
					$this->order_element($ar_sub["FK_PACKET"], NULL, $id_user, 0, $ar_sub["COUNT"], $fk_collection, $fk_invoice, $fk_billableitem, $ar_packet["BILLING_FACTOR"], $ar_packet["BILLING_CYCLE"], $ar_packet["BILLING_CANCEL_DAYS"], $ar_packet["RUNTIME_FACTOR"], 0, $ar_sub["PARAMS"]);
				}
				$orderCollection = $this->order_get($fk_collection);
				if ($status == 1) {
					$orderCollection->activate();
				}
				if ($ar_packet["IS_TRIAL"]) {
					$orderCollection->cancel();
				}
				$ar_packet_ids[] = $fk_collection;
			}
			$id_order_single = $ar_packet_ids[0];
			if ($fk_invoice > 0) {
				$arInvoiceItems = $this->database->fetch_col("SELECT ID_BILLING_INVOICE_ITEM FROM `billing_invoice_item` WHERE FK_BILLING_INVOICE=".(int)$fk_invoice);
				$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->database);
				$invoiceItemManagement->updateRef($arInvoiceItems[0], BillingInvoiceItemManagement::REF_TYPE_PACKET, $id_order_single);
			}
			if ($fk_billableitem > 0) {
				$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->database);
				$invoiceItemManagement->updateRefBillableItem($fk_billableitem, BillingInvoiceItemManagement::REF_TYPE_PACKET, $id_order_single);
			}
			return $ar_packet_ids;
		} else {
			$id_order_single = $this->order_element($id_packet, $id_packet_runtime, $id_user, $status, $count, NULL, $fk_invoice, $fk_billableitem, $ar_packet["BILLING_FACTOR"], $ar_packet["BILLING_CYCLE"], $ar_packet["BILLING_CANCEL_DAYS"], $ar_packet["RUNTIME_FACTOR"], $ar_packet["BILLING_PRICE"], NULL);
			if ($fk_invoice > 0) {
				$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->database);
				$invoiceItemManagement->updateRef($fk_invoice, BillingInvoiceItemManagement::REF_TYPE_PACKET, $id_order_single);
			}
			if ($fk_billableitem > 0) {
				$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->database);
				$invoiceItemManagement->updateRefBillableItem($fk_billableitem, BillingInvoiceItemManagement::REF_TYPE_PACKET, $id_order_single);
			}
			return $id_order_single;
		}
	}

	/**
	 * Bestellt alle im Array aufgelisteten Pakete.
	 *
	 * @param array $ar_packets                Array nach dem Schema ID => Count
	 * @param       $id_user
	 * @param null  $fk_invoice
	 * @param null  $fk_billableitem
	 *
	 * @internal param int $fk_payment_adapter Zahlungsart
	 */
	public function order_batch($ar_packets, $id_user, &$fk_invoice = NULL, &$fk_billableitem = NULL, $couponUsage = NULL) {
		global $ab_path;
		require_once $ab_path."sys/lib.billing.invoice.php";

		$ar_order_ids = array();
		$arInvoiceItems = null;
		$fk_billableitem = null;
		if ($fk_invoice == NULL && $fk_billableitem == NULL) {
			$billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->database);
            $fk_sales_user = $this->database->fetch_atom("SELECT FK_USER_SALES FROM `user` WHERE ID_USER=".(int)$id_user);
			if($billingInvoiceManagement->shouldChargeAtOnceByUserId($id_user)) {
				$fk_invoice = $this->new_invoice_batch($ar_packets, $id_user, $fk_sales_user, $couponUsage);
			} else {
				$fk_billableitem = $this->new_billableitem_batch($ar_packets, $id_user, $fk_sales_user, $couponUsage);
			}
		}
		$index = 0;
		if ($fk_invoice > 0) {
			$arInvoiceItems = $this->database->fetch_col("SELECT ID_BILLING_INVOICE_ITEM FROM `billing_invoice_item` WHERE FK_BILLING_INVOICE=".(int)$fk_invoice);
		}
		foreach ($ar_packets as $id_packet => $options) {
			$count = 1;
			if (is_array($options)) {
				$count = $options['COUNT'];
			} else {
				$count = (int)$options;
			}
			$id_order = $this->order($id_packet, $id_user, $count, $fk_invoice, $fk_billableitem);
			if (is_array($ar_order_ids)) {
				$ar_order_ids[] = $id_order;
			}
			$id_order_single = (is_array($id_order) ? (int)$id_order[0] : (int)$id_order);
			if (($arInvoiceItems !== null) && array_key_exists($index, $arInvoiceItems)) {
				$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->database);
				$invoiceItemManagement->updateRef($arInvoiceItems[$index], BillingInvoiceItemManagement::REF_TYPE_PACKET, $id_order_single);
			}
			if (($fk_billableitem !== null) && array_key_exists($index, $fk_billableitem)) {
				$invoiceItemManagement = BillingInvoiceItemManagement::getInstance($this->database);
				$invoiceItemManagement->updateRefBillableItem($fk_billableitem[$index], BillingInvoiceItemManagement::REF_TYPE_PACKET, $id_order_single);
			}
			$index++;
		}
		return $ar_order_ids;
	}

	/**
	 * Bestellt alle im Array aufgelisteten Pakete.
	 *
	 * @param array	$ar_packets				Array nach dem Schema ID => Count
	 * @param int	$amount					Anzahl der Artikel
	 * @param int	$fk_payment_adapter		Zahlungsart
	 */
	public function order_single($id_packet, $id_user, $label = "", $amount = 1, $fk_invoice = NULL, $fk_billableitem = NULL, $overrideStatus = NULL, $couponUsage = NULL) {
		global $ab_path, $user;
		$id_packet_price = $this->database->fetch_atom("SELECT ID_PACKET_PRICE FROM `packet_price`
			WHERE FK_PACKET=".$id_packet." AND FK_USERGROUP=".(int)$user["FK_USERGROUP"]);


		if ($id_packet_price > 0) {
			$status = 1;
			$ar_packet = $this->getSingle($id_packet_price);
			$price = $ar_packet["PRICE"] * $amount;
			$billing_item_index = null;


			if ($fk_invoice == NULL && $fk_billableitem == NULL) {
				global $ab_path;
				require_once $ab_path."sys/lib.billing.invoice.php";
				require_once $ab_path."sys/lib.billing.billableitem.php";

				$billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->database);
				$billingBillableItemManagement = BillingBillableItemManagement::getInstance($this->database);

				if ($price > 0) {
					// Neue Abrechnung erzeugen
					$ar_billing_items = array();
					$billing_item = array(
								"DESCRIPTION" 	=> (empty($label) ? $ar_packet["V1"] : $label),
								"QUANTITY"		=> 1,
								"PRICE"			=> $price,
								"FK_TAX"		=> $ar_packet["FK_TAX"],
								"REF_TYPE"	=> BillingInvoiceItemManagement::REF_TYPE_PACKET,
								"REF_FK"		=> NULL
					);
					$billing_item_index = count($ar_billing_items); 
					$ar_billing_items[] = $billing_item;

					if ($couponUsage != NULL) {
						$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($this->database);
						if ($couponUsageManagement->isCouponsUsageCompatible($couponUsage, 'SERVICE', $id_packet)) {
							$couponBillingItem = $couponUsageManagement->useCouponForTarget($couponUsage, $billing_item);

							if(!empty($couponBillingItem)) {
								$ar_billing_items[] = $couponBillingItem;
								$couponUsageManagement->setUsageStateToUsed($couponUsage['ID_COUPON_CODE_USAGE']);
							}
						}
					}

					// Get sales user
					$fk_sales_user = $this->database->fetch_atom("SELECT FK_USER_SALES FROM `user` WHERE ID_USER=" . (int)$id_user);
					// Create invoice
					$ar_billingdata = array(
						"FK_USER" => $id_user,
						"FK_USER_SALES" => $fk_sales_user,
						"__items" => $ar_billing_items
					);

					if($billingInvoiceManagement->shouldChargeAtOnceByUserId($id_user)) {
						$fk_invoice = $billingInvoiceManagement->createInvoice($ar_billingdata);
					} else {
						$fk_billableitem = $billingBillableItemManagement->createMultipleBillableItems($ar_billingdata);
					}

					// Check usergroup
					$ar_usergroup = $this->database->fetch1("
						SELECT g.* FROM `user` u
							LEFT JOIN `usergroup` g ON g.ID_USERGROUP=u.FK_USERGROUP
						WHERE u.ID_USER=".$id_user);
					if ($ar_usergroup != FALSE) {
						$status = ($ar_usergroup["PREPAID"] ? 0 : 1);
					} else {
						$status = 0;
					}
				}
			}

			if($overrideStatus !== NULL) {
				$status = $overrideStatus;
			} else if ($fk_invoice > 0) {
				$invoiceStatus = $this->database->fetch_atom("SELECT STATUS FROM `billing_invoice` WHERE ID_BILLING_INVOICE=".(int)$fk_invoice);
				if ($invoiceStatus == BillingInvoiceManagement::STATUS_PAID) {
					$status = 1;
				}
			}

			$result = $this->order_element($id_packet, NULL, $id_user, $status, $amount, NULL, $fk_invoice, $fk_billableitem, $amount, "DAY", 0, 1, $price, NULL);
			return $result;
		} else {
			return FALSE;
		}
	}

	/**
	 * Bestellt das angegebene Paketbestandteil
	 *
	 * @param $id_packet
	 * @param $id_packet_runtime
	 * @param $id_user
	 * @param $status
	 * @param $count
	 * @param $fk_collection
	 * @param $fk_invoice
	 * @param $fk_billableitem
	 * @param $recurring_factor
	 * @param $recurring_cycle
	 * @param $recurring_cancel_days
	 * @param $runtime_factor
	 * @param $price
	 * @param $params
	 *
	 * @return bool|int
	 * @internal param array $ar_packet
	 * @internal param int $fk_paymenttype
	 */
	private function order_element($id_packet, $id_packet_runtime, $id_user, $status, $count, $fk_collection, $fk_invoice, $fk_billableitem, $recurring_factor, $recurring_cycle, $recurring_cancel_days, $runtime_factor, $price, $params) {
		$ar_packet = $this->get($id_packet);
		if (!$ar_packet) {
			// Paket nicht gefunden!
			var_dump($id_packet);
			return FALSE;
		}
		// Paketbestandteil bestellen
		$query = "
		INSERT INTO `packet_order`
			(TYPE, STATUS, FK_PACKET, FK_PACKET_RUNTIME, FK_USER, FK_COLLECTION, PARAMS, BILLING_FACTOR, BILLING_CYCLE, BILLING_CANCEL_DAYS, RUNTIME_FACTOR, COUNT_MAX, PRICE)
			VALUES
			(\"".mysql_escape_string($ar_packet["TYPE"])."\", ".$status.", ".(int)$id_packet.", ".($id_packet_runtime > 0 ? (int)$id_packet_runtime : "NULL").", ".(int)$id_user.", ".
			($fk_collection > 0 ? (int)$fk_collection : "NULL").", ".
			($params != NULL ? "'".mysql_escape_string($params)."'" : "NULL").", ".$recurring_factor.", ".
			"\"".$recurring_cycle."\", \"".$recurring_cancel_days."\", ".(int)$runtime_factor.", ".(int)$count.", ".(float)$price.")";
		$ret = $this->database->querynow($query);
		if (!$ret['rsrc']) {
			// Fehler!
			die("DEBUG: ".$query);
			return FALSE;
		} else {
			$id_packet_order = $ret['int_result'];

			if (!$fk_collection && ($fk_invoice > 0)) {
				// Rechnung eintragen
				$this->database->querynow("INSERT INTO `packet_order_invoice` (FK_PACKET_ORDER, FK_INVOICE) VALUES
					(".(int)$id_packet_order.", ".$fk_invoice.")");
			}
			if (!$fk_collection && ((is_array($fk_billableitem) && count($fk_billableitem) > 0) || $fk_billableitem > 0)) {

				// Billableitem eintragen
				$tmpBillableItems = array();
				if(!is_array($fk_billableitem) && $fk_billableitem > 0) {
					$tmpBillableItems[] = $fk_billableitem;
				} else {
					$tmpBillableItems = $fk_billableitem;
				}
				foreach($tmpBillableItems as $key => $billableItemId) {
					$this->database->querynow($a="INSERT INTO `packet_order_billableitem` (FK_PACKET_ORDER, FK_BILLING_BILLABLEITEM) VALUES
						(".(int)$id_packet_order.", ".$billableItemId.")");
				}
			}

			return $id_packet_order;
		}
	}

	/**
	 * Findet das Paket das von der angegebenen Anzeige/News/... verwendet wird.
	 *
	 * @param string	$sType				z.B.: "ad", "image", "news"
	 * @param int		$fk_item
	 * @param bool		$resolveCollection	Versucht die dazugehörige collection anstelle des Paketbestandteils zu holen.
	 */
	public function order_find($sType, $fk_item, $resolveCollection = TRUE) {
		$types = array();
		if (self::getType($sType."_abo") > 0)
			$types[] = self::getType($sType."_abo");
		if (self::getType($sType."_once") > 0)
			$types[] = self::getType($sType."_once");
		if (!empty($types)) {
			$ar_order = $this->database->fetch1("
				SELECT o.ID_PACKET_ORDER, o.FK_COLLECTION FROM `packet_order` o
					LEFT JOIN `packet_order_usage` u ON u.ID_PACKET_ORDER=o.ID_PACKET_ORDER
				WHERE o.FK_PACKET IN (".implode(", ", $types).")
					AND u.FK=".(int)$fk_item."
				ORDER BY o.STATUS DESC");
			if ($ar_order["ID_PACKET_ORDER"] > 0) {
				if ($resolveCollection && ($ar_order["FK_COLLECTION"] > 0)) {
					return $this->order_get($ar_order["FK_COLLECTION"]);
				} else {
					return $this->order_get($ar_order["ID_PACKET_ORDER"]);
				}
			}
		}
		return NULL;
	}

	/**
	 * Findet alle "Collection"-Paket vom angegebenen Benutzer das die
	 * anforderungen erfüllt.
	 *
	 * @param unknown_type $fk_user
	 * @param unknown_type $ar_required		Array im Stil von: $id_packet => $benoetigte_anzahl
	 */
	public function order_find_collections($fk_user, $ar_required, $status = 1) {
		global $langval;
		$subindex = 0;
		$ar_select = array("s.*", "c.*");
		$ar_where = array();
		$query_tables = "FROM `packet_order` c\n";
		$query_tables .= "	LEFT JOIN `packet` p ON p.ID_PACKET=c.FK_PACKET\n";
		$query_tables .= "	LEFT JOIN `string_packet` s ON s.S_TABLE='packet' AND s.FK=p.ID_PACKET AND\n".
				"		s.BF_LANG=if(p.BF_LANG_PACKET & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))\n";
		foreach ($ar_required as $id_packet => $count_min) {
			if ($count_min > 0) {
				$ar_select[] = "(o".$subindex.".COUNT_MAX - o".$subindex.".COUNT_USED) AS FREE".$subindex;
				$ar_select[] = "o".$subindex.".COUNT_USED AS USED".$subindex;
				$ar_select[] = "o".$subindex.".COUNT_MAX AS MAX".$subindex;
				$ar_where[] = "(FREE".$subindex." >= ".(int)$count_min." OR o".$subindex.".COUNT_MAX = -1)";
				$query_tables .= "	INNER JOIN `packet_order` o".$subindex." ON\n".
						"		o".$subindex.".FK_COLLECTION=c.ID_PACKET_ORDER AND o".$subindex.".FK_PACKET=".(int)$id_packet."\n";
				$subindex++;
			}
		}
		$query = "SELECT ".implode(", ", $ar_select)." ".$query_tables;
		$query .= "WHERE c.FK_USER=".(int)$fk_user." AND c.STATUS=".(int)$status." AND c.TYPE IN ('COLLECTION','MEMBERSHIP')";
		if (!empty($ar_where)) {
			$query .= "HAVING ".implode(" AND ", $ar_where);
		}
		return $this->database->fetch_table($query);
	}

	/**
	 * Setzt ein Paket (und alle Bestandteile) bezahlt
	 *
	 * @param int $id_packet
	 */
	public function order_get($id_order) {
		$ar_packet = $this->database->fetch1("SELECT FK_PACKET, TYPE, BILLING_CYCLE FROM `packet_order` WHERE ID_PACKET_ORDER=".(int)$id_order);
		if ($ar_packet["FK_PACKET"] > 0) {
			$order = NULL;
			try {
				if ($ar_packet["TYPE"] == "COLLECTION") {
					if ($ar_packet["BILLING_CYCLE"] == "ONCE") {
						$order = new PacketOrderCollectionOnce($this->database, $id_order);
					} else {
						$order = new PacketOrderCollectionRecurring($this->database, $id_order);
					}
				} else if ($ar_packet["TYPE"] == "MEMBERSHIP") {
					if ($ar_packet["BILLING_CYCLE"] == "ONCE") {
						$order = new PacketOrderMembershipOnce($this->database, $id_order);
					} else {
						$order = new PacketOrderMembershipRecurring($this->database, $id_order);
					}
				} else {
					if ($ar_packet["FK_PACKET"] == self::$types["ad_abo"])
						$order = new PacketOrderAdRecurring($this->database, $id_order);		// Abo-Anzeige
					if ($ar_packet["FK_PACKET"] == self::$types["ad_once"])
						$order = new PacketOrderAdOnce($this->database, $id_order);				// Einzel-Anzeige

					if ($ar_packet["FK_PACKET"] == self::$types["image_abo"])
						$order = new PacketOrderImageRecurring($this->database, $id_order);		// Abo-Bild
					if ($ar_packet["FK_PACKET"] == self::$types["image_once"])
						$order = new PacketOrderImageOnce($this->database, $id_order);			// Einzel-Bild

					if ($ar_packet["FK_PACKET"] == self::$types["video_abo"])
						$order = new PacketOrderVideoRecurring($this->database, $id_order);		// Abo-Video
					if ($ar_packet["FK_PACKET"] == self::$types["video_once"])
						$order = new PacketOrderVideoOnce($this->database, $id_order);			// Einzel-Video

					if ($ar_packet["FK_PACKET"] == self::$types["download_abo"])
						$order = new PacketOrderDownloadRecurring($this->database, $id_order);	// Abo-Download
					if ($ar_packet["FK_PACKET"] == self::$types["download_once"])
						$order = new PacketOrderDownloadOnce($this->database, $id_order);		// Einzel-Download

					if ($ar_packet["FK_PACKET"] == self::$types["job_abo"])
						$order = new PacketOrderJobRecurring($this->database, $id_order);		// Abo-Job
					if ($ar_packet["FK_PACKET"] == self::$types["job_once"])
						$order = new PacketOrderJobOnce($this->database, $id_order);			// Einzel-Job

					if ($ar_packet["FK_PACKET"] == self::$types["news_abo"])
						$order = new PacketOrderNewsRecurring($this->database, $id_order);		// Abo-News
					if ($ar_packet["FK_PACKET"] == self::$types["news_once"])
						$order = new PacketOrderNewsOnce($this->database, $id_order);			// Einzel-News
					
					if ($ar_packet["FK_PACKET"] == self::$types["lead_abo"])
						$order = new PacketOrderLeadRecurring($this->database, $id_order);		// Abo-Lead
					if ($ar_packet["FK_PACKET"] == self::$types["lead_once"])
						$order = new PacketOrderLeadOnce($this->database, $id_order);			// Einzel-Lead

					if ($ar_packet["FK_PACKET"] == self::$types["usergroup_abo"])
						$order = new PacketOrderUserGroupRecurring($this->database, $id_order);	// Abo-Mitgliedschaft
					if ($ar_packet["FK_PACKET"] == self::$types["usergroup_once"])
						$order = new PacketOrderUserGroupOnce($this->database, $id_order);		// Einzel-Mitgliedschaft

					if ($ar_packet["FK_PACKET"] == self::$types["ad_top_abo"])
						$order = new PacketOrderAdTopRecurring($this->database, $id_order);			// Top-Anzeige (Abo)
					if ($ar_packet["FK_PACKET"] == self::$types["ad_top_pin_abo"])
						$order = new PacketOrderAdTopPinRecurring($this->database, $id_order);		// Top-Anzeige Oben fest (Abo)
					if ($ar_packet["FK_PACKET"] == self::$types["ad_top_slider_abo"])
						$order = new PacketOrderAdTopSliderRecurring($this->database, $id_order);	// Top-Anzeige Slider (Abo)
					if ($ar_packet["FK_PACKET"] == self::$types["ad_top_color_abo"])
						$order = new PacketOrderAdTopColorRecurring($this->database, $id_order);	// Top-Anzeige Farbe (Abo)
					if ($ar_packet["FK_PACKET"] == self::$types["ad_top_custom_abo"])
						$order = new PacketOrderAdTopCustomRecurring($this->database, $id_order);	// Top-Anzeige Eigen/Custom (Abo)
					if ($ar_packet["FK_PACKET"] == self::$types["vendor_top_abo"])
						$order = new PacketOrderVendorTopRecurring($this->database, $id_order);		// Top-Anbieter / Vorgestellt (Abo)
				}
				return $order;
			} catch (Exception $e) {
				echo "<b>Fehler: </b> Exception beim auslesen der Bestellung: ".$e->getMessage()."\n";
			}
		}
		return NULL;
	}

	/**
	 * Prüft ob das Paket mit den gegebenen Eingaben hinzugefügt werden kann
	 *
	 * @param $ar_packet	Assoziatives-Array mit allen Informationen zum Paket
	 * @return bool			True wenn alle erforderlichen vorhanden sind
	 */
	public function check($ar_packet) {
		$valid = TRUE;
		// Fehlerüberprüfung
		if (empty($ar_packet["V1"])) {
			$this->ar_errors[] = "Keinen Namen angegeben!";
			$valid = FALSE;
		}
		if (empty($ar_packet["T1"])) {
			$this->ar_errors[] = "Keine Beschreibung angegeben!";
			$valid = FALSE;
		}
		if (isset($ar_packet["RECURRING"]) && !in_array($ar_packet["RECURRING"], self::$ar_recurring)) {
			$this->ar_errors[] = "Ungültige Laufzeit angegeben!";
			$valid = FALSE;
		}
		return $valid;
	}

	/**
	 * Löscht ein Paket
	 *
	 * @param $id_packet	ID des zu löschenden Pakets.
	 * @return bool			True wenn das Paket erfolgreich gelöscht wurde
	 */
	public function delete($id_packet) {
		$id_packet = (int)$id_packet;
		$found = $this->database->fetch_atom("SELECT count(*) FROM `packet` WHERE ID_PACKET=".$id_packet);
		if ($found > 0) {
			$this->database->querynow("DELETE FROM `packet_option` WHERE FK_PACKET=".$id_packet);
			$this->database->querynow("DELETE FROM `packet_runtime` WHERE FK_PACKET=".$id_packet);
			$this->database->querynow("DELETE FROM `packet_collection` WHERE ID_PACKET=".$id_packet);
			$this->database->querynow("DELETE FROM `packet` WHERE ID_PACKET=".$id_packet);
			$this->database->querynow("DELETE FROM `string_packet` WHERE FK=".$id_packet);
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Gibt alle aufgetretenen Fehler zurück
	 *
	 * @return array		Liste aller aufgetretenen Fehler
	 */
	public function getErrors() {
		return $this->ar_errors;
	}

	/**
	 * Liest ein Paket aus der Datenbank. Die Zahlungsinformationen hierbei sind nicht enthalten!
	 *
	 * @param $id_packet	ID des zu auszulesenden Pakets.
	 * @param $language		Die Sprache in der das Paket gelesen werden soll (false/auslassen für Standard-Sprache)
	 */
	public function get($id_packet, $language = FALSE) {
		global $langval;
		$language = ($language > 0 ? (int)$language : $langval);
		$query = "
			SELECT
				p.*, s.* FROM `packet` p
			LEFT JOIN `string_packet` s ON s.S_TABLE='packet' AND s.FK=p.ID_PACKET AND
				s.BF_LANG=if(p.BF_LANG_PACKET & ".$language.", ".$language.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
			WHERE p.ID_PACKET=".(int)$id_packet."
			LIMIT 1";
		$ar_packet = $this->database->fetch1($query);
		if (is_array($ar_packet)) {
			$ar_packet["RUNTIMES"] = $this->database->fetch_table("SELECT * FROM `packet_runtime` WHERE FK_PACKET=".(int)$id_packet);
		}
		return $ar_packet;
	}

	/**
	 * Liest die Benutzergruppe eines Pakets aus.
	 * 
	 * @param $id_packet
	 * @return int
	 */
	public function getUsergroup($id_packet) {
		return (int)$this->database->fetch_atom("
			SELECT PARAMS FROM `packet_collection` WHERE ID_PACKET=".$id_packet." 
				AND FK_PACKET IN (".self::$types["usergroup_once"].", ".self::$types["usergroup_abo"].")");
	}

	/**
	 * Liest ein Paket aus der Datenbank, einschließlich der Zahlungsinformationen.
	 *
	 * @param $id_packet	ID des zu auszulesenden Pakets.
	 * @param $language		Die Sprache in der das Paket gelesen werden soll (false/auslassen für Standard-Sprache)
	 */
	public function getSingle($id_packet_price, $language = FALSE) {
		global $langval;
		$language = ($language > 0 ? (int)$language : $langval);
		$query = "
			SELECT
				p.*, r.*, s.* FROM `packet_price` r
			LEFT JOIN `packet` p ON p.ID_PACKET=r.FK_PACKET
			LEFT JOIN `string_packet` s ON s.S_TABLE='packet' AND s.FK=p.ID_PACKET AND
				s.BF_LANG=if(p.BF_LANG_PACKET & ".$language.", ".$language.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
			WHERE r.ID_PACKET_PRICE=".(int)$id_packet_price."
			LIMIT 1";
		$ar_packet = $this->database->fetch1($query);
		return $ar_packet;
	}

	/**
	 * Liest ein Paket aus der Datenbank, einschließlich der Zahlungsinformationen.
	 *
	 * @param $id_packet	ID des zu auszulesenden Pakets.
	 * @param $language		Die Sprache in der das Paket gelesen werden soll (false/auslassen für Standard-Sprache)
	 */
	public function getFull($id_packet_runtime, $language = FALSE) {
		global $langval;
		$language = ($language > 0 ? (int)$language : $langval);
		$query = "
			SELECT
				p.*, r.*, s.* , o.SERIALIZED as SER_OPTIONS
			FROM `packet_runtime` r
			LEFT JOIN `packet` p ON p.ID_PACKET=r.FK_PACKET
			LEFT JOIN `packet_option` o ON p.ID_PACKET=o.FK_PACKET
			LEFT JOIN `string_packet` s ON s.S_TABLE='packet' AND s.FK=p.ID_PACKET AND
				s.BF_LANG=if(p.BF_LANG_PACKET & ".$language.", ".$language.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
			WHERE r.ID_PACKET_RUNTIME=".(int)$id_packet_runtime."
			LIMIT 1";
		$ar_packet = $this->database->fetch1($query);
		if ($ar_packet["SER_OPTIONS"] !== NULL) {
			$ar_packet["OPTIONS"] = @unserialize($ar_packet["SER_OPTIONS"]);
		}
		return $ar_packet;
	}

	/**
	 * Gibt den Inhalt einer "Collection" als Text aus. z.B.: "1 Anzeige, 2 Bilder"
	 *
	 * @param $id_packet	ID des zu auszulesenden Pakets.
	 * @param $language		Die Sprache in der das Paket gelesen werden soll (false/auslassen für Standard-Sprache)
	 */
	public function getCollectionContent($id_packet, $language = FALSE) {
		global $langval;
		$language = ($language > 0 ? (int)$language : $langval);
		$query = "
			SELECT SQL_CALC_FOUND_ROWS
				pc.COUNT, s.* FROM `packet_collection` pc
			LEFT JOIN `packet` p ON  p.ID_PACKET=pc.FK_PACKET
			LEFT JOIN `string_packet` s ON s.S_TABLE='packet' AND s.FK=p.ID_PACKET AND
				s.BF_LANG=if(p.BF_LANG_PACKET & ".$language.", ".$language.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
			WHERE pc.ID_PACKET=".(int)$id_packet;
		$ar_packets = $this->database->fetch_table($query);
		$ar_result = array();
		foreach ($ar_packets as $index => $ar_packet) {
			if ($ar_packet["COUNT"] != 1) {
				// Mehrzahl
				$ar_result[] = ($ar_packet["COUNT"] >= 0 ? $ar_packet["COUNT"] : Translation::readTranslation('marketplace', 'packet.count.flatrate', null, array(), "Flatrate")).": ".$ar_packet["V2"];
			} else {
				// Einzahl
				$ar_result[] = $ar_packet["COUNT"]." ".$ar_packet["V1"];
			}
		}
		return implode("\n", $ar_result);
	}

	/**
	 * Liest aller Pakete aus der Datenbank. (Nur "Paketbestandteile")
	 *
	 * @param $language		Die Sprache in der die Pakete gelesen werden soll (false/auslassen für Standard-Sprache)
	 */
	public function getBaseList($page = 1, $perpage = 10, &$all = NULL, $ar_where = array(), $ar_order = array("TYPE ASC"), $language = FALSE) {
		$ar_where[] = "TYPE IN ('BASE','BASE_ABO')";
		return $this->getList($page, $perpage, $all, $ar_where, $ar_order, $language);
	}

	/**
	 * Liest aller Pakete aus der Datenbank. (Nur "Collections"/zusammengestellte Pakete)
	 *
	 * @param $language		Die Sprache in der die Pakete gelesen werden soll (false/auslassen für Standard-Sprache)
	 */
	public function getCollectionList($page = 1, $perpage = 10, &$all = NULL, $ar_where = array(), $ar_order = array("TYPE ASC"), $language = FALSE) {
		$ar_where[] = "TYPE IN ('COLLECTION')";
		return $this->getList($page, $perpage, $all, $ar_where, $ar_order, $language);
	}

	/**
	 * Liest aller Pakete aus der Datenbank.
	 *
	 * @param $language		Die Sprache in der die Pakete gelesen werden soll (false/auslassen für Standard-Sprache)
	 */
	public function getList($page = 1, $perpage = 10, &$all = NULL, $ar_where = array(), $ar_order = array("TYPE ASC"), $language = FALSE, $includeTrials = false) {
		global $langval;
		$language = ($language > 0 ? (int)$language : $langval);
		$offset = ($page-1) * $perpage;
		$query = "
			SELECT SQL_CALC_FOUND_ROWS
				p.*, s.*, g.FK_USERGROUP, o.SERIALIZED as SER_OPTIONS
			FROM `packet` p
			LEFT JOIN `packet_group` g ON p.ID_PACKET=g.ID_PACKET
			LEFT JOIN `packet_option` o ON p.ID_PACKET=o.FK_PACKET 
			LEFT JOIN `string_packet` s ON s.S_TABLE='packet' AND s.FK=p.ID_PACKET AND
				s.BF_LANG=if(p.BF_LANG_PACKET & ".$language.", ".$language.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
			".(!empty($ar_where) ? "WHERE ".implode(" AND ", $ar_where) : "")."
			GROUP BY p.ID_PACKET
			".(!empty($ar_order) ? "ORDER BY ".implode(", ", $ar_order) : "")."
			LIMIT ".(int)$offset.", ".(int)$perpage;
		$ar_packets = $this->database->fetch_table($query);
		if ($all !== NULL) {
			$all = $this->database->fetch_atom("SELECT FOUND_ROWS()");
		}
		foreach ($ar_packets as $index => $ar_packet) {
			$taxPercent = $this->database->fetch_atom("SELECT TAX_VALUE FROM `tax` WHERE ID_TAX=".$ar_packet["FK_TAX"]);
			$tax = (100 + $taxPercent) / 100;
			$ar_runtimes = $this->database->fetch_table("SELECT * FROM `packet_runtime` WHERE FK_PACKET=".$ar_packet["ID_PACKET"].($includeTrials ? "" : " AND IS_TRIAL=0 ORDER BY IS_TRIAL DESC"));
			foreach ($ar_runtimes as $runtime_index => $ar_current) {
				$ar_runtimes[$runtime_index]["BILLING_PRICE_BRUTTO"] = round($ar_current["BILLING_PRICE"] * 100 * $tax) / 100;
			}
			if ($ar_packets[$index]["SER_OPTIONS"] !== NULL) {
				$ar_packets[$index]["OPTIONS"] = @unserialize($ar_packets[$index]["SER_OPTIONS"]);
			}
			$ar_packets[$index]["RUNTIMES"] = $ar_runtimes;
			$ar_packets[$index]["TAX_PERCENT"] = $taxPercent;
		}
		return $ar_packets;
	}

    public function getActiveMembershipByUserId($userId) {
        $db = $this->database;

        $membership = $db->fetch1("
            SELECT
                ID_PACKET_ORDER, FK_PACKET
            FROM `packet_order`
            WHERE
                `TYPE` = 'MEMBERSHIP'
                AND FK_COLLECTION IS NULL
                AND FK_USER=".(int)$userId."
                AND STATUS&1 = 1
        ");

        if($membership) {
            $order = $this->order_get($membership['ID_PACKET_ORDER']);
            return $order;
        } else {
            return NULL;
        }
    }


	/**
	 * Liest aller Pakete aus der Datenbank. Nur "Collections"/zusammengestellte Pakete
	 * für die angegebene Benutzergruppe
	 *
	 * @param $language		Die Sprache in der die Pakete gelesen werden soll (false/auslassen für Standard-Sprache)
	 */
	public function getUserList($id_usergroup, $page = 1, $perpage = 10, $ar_where = array(), $ar_order = array("TYPE ASC"), $language = FALSE) {
		global $langval;
		$ar_where[] = "g.FK_USERGROUP=".(int)$id_usergroup;
		$ar_where[] = "TYPE='COLLECTION'";
		$ar_where[] = "(STATUS&1)=1";
		$language = ($language > 0 ? (int)$language : $langval);
		$offset = ($page-1) * $perpage;
		$query = "
			SELECT SQL_CALC_FOUND_ROWS
				p.*, s.*
			FROM `packet_group` g
			LEFT JOIN `packet` p ON p.ID_PACKET=g.ID_PACKET
			LEFT JOIN `string_packet` s ON s.S_TABLE='packet' AND s.FK=p.ID_PACKET AND
				s.BF_LANG=if(p.BF_LANG_PACKET & ".$language.", ".$language.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
			".(!empty($ar_where) ? "WHERE ".implode(" AND ", $ar_where) : "")."
			".(!empty($ar_order) ? "ORDER BY ".implode(", ", $ar_order) : "")."
			LIMIT ".(int)$offset.", ".(int)$perpage;
		$ar_packets = $this->database->fetch_table($query);
		foreach ($ar_packets as $index => $ar_packet) {
			$tax = (100 + $this->database->fetch_atom("SELECT TAX_VALUE FROM `tax` WHERE ID_TAX=".$ar_packet["FK_TAX"])) / 100;
			$ar_runtimes = $this->database->fetch_table("SELECT * FROM `packet_runtime` WHERE FK_PACKET=".$ar_packet["ID_PACKET"]);
			foreach ($ar_runtimes as $runtime_index => $ar_current) {
				$ar_runtimes[$runtime_index]["BILLING_PRICE_BRUTTO"] = round($ar_current["BILLING_PRICE"] * 100 * $tax) / 100;
			}
			$ar_packets[$index]["RUNTIMES"] = $ar_runtimes;
		}
		return $ar_packets;
	}

	public function invoiceActivate($id_invoice) {
		$query = "SELECT p.ID_PACKET_ORDER FROM `packet_order` p
			LEFT JOIN `packet_order_invoice` i ON p.ID_PACKET_ORDER=i.FK_PACKET_ORDER
			WHERE i.FK_INVOICE=".(int)$id_invoice;
		$ar_orders = array_keys($this->database->fetch_nar($query));
		foreach ($ar_orders as $index => $id_order) {
			$order = $this->order_get($id_order);
			if ($order != NULL) {
				$order->activate();
			}
		}
	}

	public function invoiceDeactivate($id_invoice) {
		$query = "SELECT p.ID_PACKET_ORDER FROM `packet_order` p
			LEFT JOIN `packet_order_invoice` i ON p.ID_PACKET_ORDER=i.FK_PACKET_ORDER
			WHERE i.FK_INVOICE=".(int)$id_invoice;
		$ar_orders = array_keys($this->database->fetch_nar($query));
		foreach ($ar_orders as $index => $id_order) {
			$order = $this->order_get($id_order);
			if ($order != NULL) {
				$order->deactivate();
			}
		}
	}

	public function invoiceItemDeactivate($id_order) {
		$order = $this->order_get($id_order);
		if ($order != NULL) {
			$order->deactivate();
		}
		$this->database->querynow("
			DELETE FROM `packet_order_invoice`
			WHERE FK_PACKET_ORDER IN
				(SELECT ID_PACKET_ORDER FROM `packet_order` WHERE ID_PACKET_ORDER=".(int)$id_order." OR FK_COLLECTION=".(int)$id_order.")");
	}

	public function billableItemDeactivate($id_order) {
		$order = $this->order_get($id_order);
		if ($order != NULL) {
			$order->deactivate();
		}
		$this->database->querynow("
			DELETE FROM `packet_order_invoice`
			WHERE FK_PACKET_ORDER IN
				(SELECT ID_PACKET_ORDER FROM `packet_order` WHERE ID_PACKET_ORDER=".(int)$id_order." OR FK_COLLECTION=".(int)$id_order.")");
	}

	/**
	 * Erstellt eine neue Rechnung mit den aufgelisteten Paketen
	 *
	 * @param array	$ar_packets				Array nach dem Schema ID => Count
	 * @param int	$id_user				User-ID
	 */
	public function new_invoice($id_packet_runtime, $id_user, $count, $price = NULL, $fk_sales_user = NULL, $couponUsage = null) {
		global $ab_path;
		require_once $ab_path."sys/lib.billing.invoice.php";
		$billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->database);

		$id_invoice = NULL;

		$ar_data = $this->_get_billing_data(array($id_packet_runtime => $count), $id_user, $price, $fk_sales_user, $couponUsage);
		if (!empty($ar_data)) {
			$id_invoice = $billingInvoiceManagement->createInvoice($ar_data);
		}
		return $id_invoice;
	}

	public function new_billableitem($id_packet_runtime, $id_user, $count, $price = NULL, $fk_sales_user = NULL, $couponUsage = null) {
		global $ab_path;
		require_once $ab_path."sys/lib.billing.billableitem.php";
		$billingBillableItemManagement = BillingBillableItemManagement::getInstance($this->database);

		$id_billableitem = NULL;

		$ar_data = $this->_get_billing_data(array($id_packet_runtime => $count), $id_user, $price, $fk_sales_user, $couponUsage);
		if (!empty($ar_data)) {
			$id_billableitem = $billingBillableItemManagement->createMultipleBillableItems($ar_data);
		}
		return $id_billableitem;
	}

	private function _get_billing_data($ar_packets, $id_user, $price = NULL, $fk_sales_user = NULL, $couponUsage = NULL) {
		$ar_billing_items = array();
		$ar_data = array();

		foreach ($ar_packets as $id_packet_runtime => $options) {
			$count = 1;
			$ar_packet = $this->getFull($id_packet_runtime);
			$label = $ar_packet["V1"];
			if (is_array($options)) {
				$count = $options['COUNT'];
				$label = (isset($options['LABEL']) ? $options['LABEL'] : $label);
			} else {
				$count = (int)$options;
			}
			if ($price != NULL) $ar_packet["BILLING_PRICE"] = $price;
			if ($ar_packet["BILLING_PRICE"] > 0) {
				$billing_item = array(
					"DESCRIPTION" => $label,
					"QUANTITY" => $count,
					"PRICE" => $ar_packet["BILLING_PRICE"],
					"FK_TAX" => $ar_packet["FK_TAX"],
					"REF_TYPE" => BillingInvoiceItemManagement::REF_TYPE_PACKET,
					"REF_FK" => NULL
				);

				$ar_billing_items[] = $billing_item;

				if ($couponUsage != NULL) {
					$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($this->database);

					if ($couponUsageManagement->isCouponsUsageCompatible($couponUsage, 'PACKET', $id_packet_runtime)) {
						$couponBillingItem = $couponUsageManagement->useCouponForTarget($couponUsage, $billing_item);

						if(!empty($couponBillingItem)) {
							$ar_billing_items[] = $couponBillingItem;
							$couponUsageManagement->setUsageStateToUsed($couponUsage['ID_COUPON_CODE_USAGE']);
						}
					}
				}
			}
		}


		if (!empty($ar_billing_items)) {
			$ar_data = array(
				"FK_USER"	    => $id_user,
                "FK_USER_SALES" => $fk_sales_user,
				"__items"	    => $ar_billing_items
			);
			return $ar_data;
		}
		return array();
	}

	/**
	 * Erstellt eine neue Rechnung mit den aufgelisteten Paketen
	 *
	 * @param array	$ar_packets				Array nach dem Schema ID => Count
	 * @param int	$id_user				User-ID
	 */
	public function new_invoice_batch($ar_packets, $id_user, $fk_sales_user, $couponUsage = null) {
		global $ab_path;
		require_once $ab_path."sys/lib.billing.invoice.php";
		$billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->database);
		// Neue Rechnung erzeugen
		$id_invoice = NULL;
		$ar_billing_data = $this->_get_billing_data($ar_packets, $id_user, NULL, $fk_sales_user, $couponUsage);
		if (!empty($ar_billing_data)) {
			$id_invoice = $billingInvoiceManagement->createInvoice($ar_billing_data);
		}
		return $id_invoice;
	}

	/**
	 * Erstellt neue BillableItems mit den aufgelisteten Paketen
	 *
	 * @param array	$ar_packets				Array nach dem Schema ID => Count
	 * @param int	$id_user				User-ID
	 */
	public function new_billableitem_batch($ar_packets, $id_user, $fk_sales_user, $couponUsage = null) {
		global $ab_path;
		require_once $ab_path."sys/lib.billing.billableitem.php";
		$billingBillableItemManagement = BillingBillableItemManagement::getInstance($this->database);

		// Neue Rechnung erzeugen
		$id_billableitems = array();
		$ar_billing_data = $this->_get_billing_data($ar_packets, $id_user, NULL, $fk_sales_user, $couponUsage);

		if (!empty($ar_billing_data)) {
			$billingInvoiceManagement = BillingInvoiceManagement::getInstance($this->database);
			$id_billableitems = $billingBillableItemManagement->createMultipleBillableItems($ar_billing_data);
		}
		return $id_billableitems;
	}

	/**
	 * Aktuallisiert/erstellt ein Paket.
	 *
	 * @param $ar_packet	Assoziatives-Array mit allen Informationen zum Paket
	 * @return bool|int		Die ID des Pakets oder false bei Fehler.
	 */
	public function update($ar_packet) {
		if ($this->check($ar_packet)) {
			$id_packet = $this->database->update("packet", $ar_packet);
			if ($ar_packet["ID_PACKET"] > 0) {
				$id_packet = $ar_packet["ID_PACKET"];
			}
			if (array_key_exists("OPTIONS", $ar_packet) && !empty($ar_packet["OPTIONS"])) {
				// Add options to database
				$this->database->querynow("
					INSERT INTO `packet_option` (FK_PACKET, SERIALIZED)
					VALUES (".(int)$id_packet.", '".mysql_real_escape_string(serialize($ar_packet["OPTIONS"]))."')
					ON DUPLICATE KEY UPDATE SERIALIZED=VALUES(SERIALIZED)");
			} else {
				// Remove options from database
				$this->database->querynow("DELETE FROM `packet_option` WHERE FK_PACKET=".(int)$id_packet);
			}
			return $id_packet;
		} else {
			return FALSE;
		}
	}

}

?>
