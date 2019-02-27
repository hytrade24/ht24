<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/packet_order_interface.php";

abstract class PacketOrderBase implements PacketOrderInterface {

	/**
	 * Datenbankobjekt des ebiz-trader
	 *
	 * @var ebiz_db
	 */
	protected $database = false;

	/**
	 * ID/Primärschlüssel das Datensatzes in der Datenbank
	 *
	 * @var unknown_type
	 */
	protected $id = 0;

	/**
	 * Assoziatives Array mit dem Datensatz dieses Produkts
	 *
	 * @var array
	 */
	protected $PacketOrder = array();

	/**
	 * Initialisieren eines neuen Objekts anhand der ID/des Primärschlüssels
	 *
	 * @param int $id_Packet_order		ID des Datensatzes in der 'packet_order'-Tabelle
	 */
	function __construct(ebiz_db $db, $id_packet_order) {
		$this->database = $db;
		$this->id = (int)$id_packet_order;
		if ($this->id > 0) {
			$this->PacketOrder = $this->database->fetch1("SELECT * FROM `packet_order` WHERE ID_PACKET_ORDER=".$this->id);
			if (!$this->PacketOrder) {
				// Fehler beim lesen des Datensatzes
				throw new Exception("[PacketOrderBase] PacketOrder nicht gefunden! (ID: ".$this->id.")");
			}
			$this->PacketOrder["USAGE"] = array_keys(
				$this->database->fetch_nar("SELECT FK FROM `packet_order_usage` WHERE ID_PACKET_ORDER=".$this->id)
			);
		} else {
			throw new Exception("[PacketOrderBase] Keine oder ungültige ID! (ID: ".$this->id.")");
		}
	}

	/**
	 * Feature aktivieren
	 *
	 * @see PacketOrderInterface::activate()
	 */
	public function activate() {
		if ($this->getPaymentDateFirst() == null) {
			// Erste Freischaltung
			if ($this->isRecurring()) {
				// Abo
				$interval_payment = $this->getPaymentCycle();
				$interval_runtime = $this->getRuntimeCycle();
				if ($this->PacketOrder["BILLING_CANCEL_DAYS"] >= 0) {
					$this->database->querynow("UPDATE `packet_order` SET STATUS=(STATUS|1), STAMP_START=NOW(),
						STAMP_END=DATE_ADD(NOW(), INTERVAL ".$interval_runtime."),
						STAMP_NEXT=DATE_ADD(NOW(), INTERVAL ".$interval_payment."),
						STAMP_CANCEL_UNTIL=DATE_SUB(STAMP_END, INTERVAL ".$this->PacketOrder["BILLING_CANCEL_DAYS"]." DAY)
					WHERE ID_PACKET_ORDER=".$this->id);
				} else {
					$this->database->querynow("UPDATE `packet_order` SET STATUS=(STATUS|1), STAMP_START=NOW(),
						STAMP_END=DATE_ADD(NOW(), INTERVAL ".$interval_runtime."),
						STAMP_NEXT=NULL,
						STAMP_CANCEL_UNTIL=DATE_ADD(NOW(), INTERVAL ".$interval_payment.")
					WHERE ID_PACKET_ORDER=".$this->id);
				}
			} else {
				// Kein abo
				$this->database->querynow("UPDATE `packet_order` SET STATUS=(STATUS|1), STAMP_START=NOW()
				WHERE ID_PACKET_ORDER=".$this->id);
			}
		} else {
			// Update status
			$this->database->querynow("UPDATE `packet_order` SET STATUS=(STATUS|1) WHERE ID_PACKET_ORDER=".$this->id);
		}
		// Trigger event
		Api_TraderApiHandler::getInstance($this->database)->triggerEvent(Api_TraderApiEvents::PACKET_ENABLED, $this);
		return true;
	}

	/**
	 * Feature deaktivieren
	 *
	 * @see PacketOrderInterface::deactivate()
	 */
	public function deactivate() {
		// Update status
		$this->database->querynow("UPDATE `packet_order` SET STATUS=STATUS-(STATUS&1) WHERE ID_PACKET_ORDER=".$this->id);
		// Trigger event
		Api_TraderApiHandler::getInstance($this->database)->triggerEvent(Api_TraderApiEvents::PACKET_DISABLED, $this);
		return true;
	}

    public function cancelNow($isUpgrade = false) {
		$this->deactivate();
	}

    public function asArray() {
        return $this->PacketOrder;
    }
	/**
	* Ist das Feature aktiv?
	* @see PacketOrderInterface::isActive()
	*/
	public function isActive() {
		return (($this->PacketOrder["STATUS"] & 1) == 1);
	}

	public function isCollection() {
		return (($this->PacketOrder["TYPE"] == "COLLECTION") || ($this->PacketOrder["TYPE"] == "MEMBERSHIP"));
	}

	public function isRecurring() {
		return false;
	}
	
	public function isPaid() {
		if ($this->getPaymentAmount() > 0) {
			$invoiceStatus = $this->database->fetch_atom("
				SELECT i.STATUS 
				FROM `packet_order_invoice` p
				LEFT JOIN `billing_invoice` i ON i.ID_BILLING_INVOICE=p.FK_INVOICE
				WHERE FK_PACKET_ORDER=".(int)$this->id);
			/*
			require_once $GLOBALS["ab_path"].'sys/lib.billing.invoice.php';
			return ($invoiceStatus === BillingInvoiceManagement::STATUS_PAID);
			*/
			return ($invoiceStatus === 1);
		} else {
			return true;
		}
	}

	public function getCountMax() {
		return $this->PacketOrder["COUNT_MAX"];
	}

	public function getCountUsed() {
		return $this->PacketOrder["COUNT_USED"];
	}

	public function getInvoiceCount($count_paid = false) {
		$query = "SELECT count(i.ID_BILLING_INVOICE) FROM `packet_order_invoice` p
			LEFT JOIN `billing_invoice` i ON i.ID_BILLING_INVOICE=p.FK_INVOICE
			WHERE p.FK_PACKET_ORDER=".$this->id.($count_paid ? "" : " AND (i.STATUS)=0");
		return $this->database->fetch_atom($query);
	}

	public function getInvoiceIds() {
		$query = "SELECT FK_INVOICE FROM `packet_order_invoice` WHERE FK_PACKET_ORDER=".$this->id;
		return array_keys($this->database->fetch_nar($query));
	}

	public function getOrderId() {
		return $this->PacketOrder["ID_PACKET_ORDER"];
	}

	public function getPaymentAmount() {
		return $this->PacketOrder["PRICE"];
	}

	public function getPaymentCycle() {
		return $this->PacketOrder["BILLING_FACTOR"]." ".$this->PacketOrder["BILLING_CYCLE"];
	}

	public function getPaymentCycleFactor() {
		return $this->PacketOrder["BILLING_FACTOR"];
	}

	public function getPaymentCycleUnit() {
		return $this->PacketOrder["BILLING_CYCLE"];
	}

	public function getPaymentDateFirst() {
		return $this->PacketOrder["STAMP_START"];
	}

	public function getPaymentDateNext() {
		return $this->PacketOrder["STAMP_NEXT"];
	}

	public function getPaymentDateLast() {
		return $this->PacketOrder["STAMP_END"];
	}

	public function getPaymentDateCancel() {
		return $this->PacketOrder["STAMP_CANCEL_UNTIL"];
	}

	/**
	 * Steuersatz auslesen
	 * @return assoc
	 */
	public function getPaymentTax() {
		return $this->database->fetch1("
				SELECT * FROM `tax` t
				LEFT JOIN packet p ON p.FK_TAX = t.ID_TAX
				WHERE p.ID_PACKET=".$this->getPacketId());
	}

	public function getPaymentType() {
		return $this->PacketOrder["FK_PAYMENTTYPE"];
	}

	/**
	 * Liest das "Eltern-Element" des aktuellen Pakets aus.
	 * Wenn es kein  Eltern-Element gibt, dann wird das aktuelle Paket zurück gegeben.
	 * @return PacketOrderBase
	 */
	public function getRoot() {
		global $ab_path;
		if ($this->PacketOrder["FK_COLLECTION"] > 0) {
			require_once $ab_path."sys/packet_management.php";
			$packets = PacketManagement::getInstance($this->database);
			return $packets->order_get($this->PacketOrder["FK_COLLECTION"]);
		}
		return $this;
	}

	public function getRuntimeCycle() {
		return ($this->PacketOrder["BILLING_FACTOR"] * $this->PacketOrder["RUNTIME_FACTOR"])." ".$this->PacketOrder["BILLING_CYCLE"];
	}

	public function getRuntimeFactor() {
		return ($this->PacketOrder["BILLING_FACTOR"] * $this->PacketOrder["RUNTIME_FACTOR"])." ".$this->PacketOrder["BILLING_CYCLE"];
	}

	public function getPacketId() {
		return $this->PacketOrder["FK_PACKET"];
	}

	public function getPacketRuntimeId() {
		return $this->PacketOrder["FK_PACKET_RUNTIME"];
	}

	public function getPacketName() {
		global $langval;
		$name = $this->database->fetch_atom("SELECT s.V1 FROM `packet` p
		    JOIN `string_packet` s ON s.S_TABLE='packet' AND s.FK=p.ID_PACKET
			    AND BF_LANG=if(p.BF_LANG_PACKET & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
            WHERE p.ID_PACKET=".$this->PacketOrder["FK_PACKET"]);
		return $name;
	}
	
	public function getPacketOptions() {
		$options_ser = $this->database->fetch_atom("SELECT SERIALIZED FROM `packet_option` WHERE FK_PACKET=".(int)$this->PacketOrder["FK_PACKET"]);
		if ($options_ser !== false) {
			$options = unserialize($options_ser);
			return (is_array($options) ? $options : array());			
		}
		return array();
	}

	/**
	 * Prüft die benötigten/verfügbaren Features für eine Anzeige
	 *
	 * @param int $id_anzeige
	 * @return array
	 */
	public function getPacketUsage($id_anzeige, $forceNew = false) {
		return $this->getPacketUsageEx(array($id_anzeige), $forceNew);
	}

	/**
	 * Prüft die benötigten/verfügbaren Features für eine Anzeige
	 *
	 * @param int $id_anzeige
	 * @return array
	 */
	public function getPacketUsageEx($ar_id_anzeigen, $forceNew = false) {
		global $nar_systemsettings;
		$count = count($ar_id_anzeigen);
		// Typen festlegen, je nach dem ob das paket ein abo ist
		$ar_result = array(
			// Anzeigen
			"ads_required" 			=> 0,
			"ads_available" 		=> 0,
			"ads_type"		 		=> ($this->isRecurring() ? "ad_abo" : "ad_once"),
			"ads_free"				=> array(),
			"ads_used"				=> array(),
			"ads_new"				=> array(),
			// Bilder
			"images_required" 		=> 0,
			"images_available"		=> 0,
			"images_type"	 		=> ($this->isRecurring() ? "image_abo" : "image_once"),
			"images_free"			=> array(),
			"images_used"			=> array(),
			"images_new"			=> array(),
			// Videos
			"videos_required" 		=> 0,
			"videos_available"		=> 0,
			"videos_type"	 		=> ($this->isRecurring() ? "video_abo" : "video_once"),
			"videos_free"			=> array(),
			"videos_used"			=> array(),
			"videos_new"			=> array(),
			// Downloads
			"downloads_required"	=> 0,
			"downloads_available"	=> 0,
			"downloads_type"		=> ($this->isRecurring() ? "download_abo" : "download_once"),
			"downloads_free"		=> array(),
			"downloads_used"		=> array(),
			"downloads_new"			=> array()
		);
		### --------------------------------------------
		### Verwendete Anzeigen auslesen
		foreach ($ar_id_anzeigen as $index => $id_anzeige) {
			### Anzahl kostenloser Elemente auslesen
			$ads_free = $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"];
			$images_free = $nar_systemsettings["MARKTPLATZ"]["FREE_IMAGES"];
			$videos_free = $nar_systemsettings["MARKTPLATZ"]["FREE_VIDEOS"];
			$uploads_free = $nar_systemsettings["MARKTPLATZ"]["FREE_UPLOADS"];
			### Verwendete Bestandteile auslesen
			if (!$ads_free) {
				if ($forceNew || (!is_array($this->PacketOrder["USAGE"]) && !in_array($id_anzeige, $this->PacketOrder["USAGE"]))) {
					// New ad!
					$ar_result["ads_required"]++;
					$ar_result["ads_new"][] = $id_anzeige;
				} else {
					$ar_result["ads_used"][] = $id_anzeige;
				}
			} else {
				$ar_result["ads_free"][] = $id_anzeige;
			}
			### Verwendete Bilder auslesen
			$ar_images = array_keys($this->database->fetch_nar("SELECT ID_IMAGE FROM `ad_images` WHERE FK_AD=".(int)$id_anzeige));
			foreach ($ar_images as $index => $id_image) {
				if ($forceNew || (!is_array($this->PacketOrder["USAGE"]) && !in_array($id_image, $this->PacketOrder["USAGE"]))) {
					if ($images_free > 0) {
						$images_free--;
						$ar_result["images_free"][] = $id_image;
					} else {
						$ar_result["images_required"]++;
						$ar_result["images_new"][] = $id_image;
					}
				} else {
					$ar_result["images_used"][] = $id_image;
				}
			}
			### Verwendete Videos auslesen
			$ar_videos = array_keys($this->database->fetch_nar("SELECT ID_AD_VIDEO FROM `ad_video` WHERE FK_AD=".(int)$id_anzeige));
			foreach ($ar_videos as $index => $id_video) {
				if ($forceNew || (!is_array($this->PacketOrder["USAGE"]) && !in_array($id_video, $this->PacketOrder["USAGE"]))) {
					if ($videos_free > 0) {
						$videos_free--;
						$ar_result["videos_free"][] = $id_video;
					} else {
						$ar_result["videos_required"]++;
						$ar_result["videos_new"][] = $id_video;
					}
				} else {
					$ar_result["videos_used"][] = $id_video;
				}
			}
			### Verwendete Downloads auslesen
			$ar_uploads = array_keys($this->database->fetch_nar("SELECT ID_AD_UPLOAD FROM `ad_upload` WHERE FK_AD=".(int)$id_anzeige));
			foreach ($ar_uploads as $index => $id_upload) {
				if ($forceNew || (!is_array($this->PacketOrder["USAGE"]) && !in_array($id_upload, $this->PacketOrder["USAGE"]))) {
					if ($uploads_free > 0) {
						$uploads_free--;
						$ar_result["downloads_free"][] = $id_upload;
					} else {
						$ar_result["downloads_required"]++;
						$ar_result["downloads_new"][] = $id_upload;
					}
				} else {
					$ar_result["downloads_used"][] = $id_upload;
				}
			}
			### --------------------------------------------
			### Verfügbare Bestandteile hinzufügen
			### Anzeigen
			if ($this->getType() == $ar_result["ads_type"]) {
				if ($this->getCountMax() == -1) {
					$ar_result["ads_available"] = $ar_result["ads_required"] + 20;
				} else {
					$ar_result["ads_available"] = ($this->getCountMax() - $this->getCountUsed() - count($ar_result["ads_new"]));
				}
			}
			$ar_result["ads_available"] += $ads_free;
			### Bilder
			if ($this->getType() == $ar_result["images_type"]) {
				if ($this->getCountMax() == -1) {
					$ar_result["images_available"] = $ar_result["images_required"] + 20;
				} else {
					$ar_result["images_available"] = ($this->getCountMax() - $this->getCountUsed() - count($ar_result["images_new"]));
				}
			}
			$ar_result["images_available"] += $images_free;
			### Videos
			if ($this->getType() == $ar_result["images_type"]) {
				if ($this->getCountMax() == -1) {
					$ar_result["videos_available"] = $ar_result["videos_required"] + 20;
				} else {
					$ar_result["videos_available"] = ($this->getCountMax() - $this->getCountUsed() - count($ar_result["videos_new"]));
				}
			}
			$ar_result["videos_available"] += $videos_free;
			### Dateien
			if ($this->getType() == $ar_result["downloads_type"]) {
				if ($this->getCountMax() == -1) {
					$ar_result["downloads_available"] = $ar_result["downloads_required"] + 20;
				} else {
					$ar_result["downloads_available"] = ($this->getCountMax() - $this->getCountUsed() - count($ar_result["downloads_new"]));
				}
			}
			$ar_result["downloads_available"] += $uploads_free;
		}
		return $ar_result;
	}

	public function getType() {
		return $this->PacketOrder["TYPE"];
	}

	public function getUserId() {
		return $this->PacketOrder["FK_USER"];
	}

	private function updateUsage() {
		$this->PacketOrder["COUNT_USED"] = (int)$this->database->fetch_atom("SELECT count(*) FROM `packet_order_usage` WHERE ID_PACKET_ORDER=".$this->id);
		$ret = $this->database->querynow("UPDATE `packet_order` SET COUNT_USED=".$this->PacketOrder["COUNT_USED"]." WHERE ID_PACKET_ORDER=".$this->id);
		if (!$ret['rsrc']) {
			// Fehler!
			return false;
		} else {
			$this->PacketOrder["USAGE"] = array_keys(
				$this->database->fetch_nar("SELECT FK FROM `packet_order_usage` WHERE ID_PACKET_ORDER=".$this->id)
			);
			return true;
		}
	}

	/**
	* Ist die angegebene Anzahl an Anzeige/News/... noch verfügbar?
	*
	* @param string		$sType		z.B.: "ad", "image", "news"
	* @param int 		$id
	* @return bool
	*/
	public function isAvailable($sType, $count) {
		$id_packet = PacketManagement::getType( $sType . ($this->isRecurring() ? "_abo" : "_once") );
		if ($this->getPacketId() == $id_packet) {
			return ((getCountMax() - getCountUsed()) >= $count ? true : false);
		}
		return false;
	}

	/**
	* Verknüpfung zu einem "Objekt" (z.B. Anzeige/Werbung/Bild/...) hinzufügen
	*
	* @param int $fk_item		ID des Objekts
	*
	* @return Wahr wenn die Verknüpfung erfolgreich hinzugefügt wurde
	*/
	public function itemAdd($fk_item) {
		$ret = $this->database->querynow("INSERT INTO `packet_order_usage` (ID_PACKET_ORDER, FK) VALUES (".$this->id.", ".(int)$fk_item.")");

		if (!$ret['rsrc']) {
			// Fehler!
			$this->updateUsage();
			return false;
		} else {
			$this->updateUsage();
			return true;
		}
	}

	/**
	* Verknüpfung zu einem "Objekt" (z.B. Anzeige/Werbung/Bild/...) entfernen
	*
	* @param int $fk_item	ID des Objekts (alle löschen: $fk_item = null)
	* @param int $count		Anzahl der zu löschenden Verknüpfungen (alle löschen: $count <= 0)
	*
	* @return Wahr wenn die Verknüpfung erfolgreich entfernt wurde
	*/
	public function itemRemove($fk_item = null, $count = -1) {
		$query = "DELETE FROM `packet_order_usage` WHERE ID_PACKET_ORDER=".$this->id.($fk_item !== null ? " AND FK=".(int)$fk_item : "");
		if ($count > 0) {
			$query .= " LIMIT ".(int)$count;
		}
		$ret = $this->database->querynow($query);
		if (!$ret['rsrc']) {
			// Fehler!
			$this->updateUsage();
			return false;
		} else {
			$this->updateUsage();
			return true;
		}
	}

	/**
	 * Erstellt die Verküpfung zu einem Bestandteil des Pakets
	 *
	 * @param string	$sType		z.B.: "ad", "image", "news"
	 * @param int		$fk_item
	 */
	public function itemAddContent($sType, $fk_item) {
		$id_packet = PacketManagement::getType( $sType . ($this->isRecurring() ? "_abo" : "_once") );
		if ($this->getPacketId() == $id_packet) {
			return $this->itemAdd($fk_item);
		}
		return false;
	}

	/**
	 * Entfernt die Verküpfung zu einem Bestandteil des Pakets
	 *
	 * @param string	$sType		z.B.: "ad", "image", "news"
	 * @param int		$fk_item    Wenn weggelassen oder null angegeben wird, dann werden alle Verknüpfungen des Typs entfernt.
	 */
	public function itemRemContent($sType, $fk_item = null) {
		$id_packet = PacketManagement::getType( $sType . ($this->isRecurring() ? "_abo" : "_once") );
		if ($this->getPacketId() == $id_packet) {
			return $this->itemRemove($fk_item);
		}
		return false;
	}

	public function moveContent($id_packet_order) {
		return true;
	}

}

?>