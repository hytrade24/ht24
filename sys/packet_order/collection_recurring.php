<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/packet_management.php";
require_once $ab_path."sys/packet_order_recurring.php";

class PacketOrderCollectionRecurring extends PacketOrderRecurring {

	/**
	* Alle enthaltenen Elemente
	*
	* @var array
	*/
	private $ar_contents = array();

	/**
	 * Initialisieren eines neuen Objekts anhand der ID/des Primärschlüssels
	 *
	 * @param int $id_packet_order		ID des Datensatzes in der 'packet_order'-Tabelle
	 */
	function __construct(ebiz_db $db, $id_packet_order) {
		$this->database = $db;
		$this->id = (int)$id_packet_order;
		if ($this->id > 0) {
			$this->PacketOrder = $this->database->fetch1("SELECT * FROM `packet_order` WHERE ID_PACKET_ORDER=".$this->id);
			if (!$this->PacketOrder) {
				// Fehler beim lesen des Datensatzes
				throw new Exception("[PacketOrderCollectionOnce] PacketOrder nicht gefunden! (ID: ".$this->id.")");
			}
			$ar_childs = $this->database->fetch_table("SELECT o.* FROM `packet_order` c
					INNER JOIN `packet_order` o ON o.FK_COLLECTION=c.ID_PACKET_ORDER
					WHERE c.ID_PACKET_ORDER=".(int)$id_packet_order);
			foreach ($ar_childs as $index => $ar_order) {
				$ar_order["USAGE"] = array_keys(
					$this->database->fetch_nar("SELECT FK FROM `packet_order_usage` WHERE ID_PACKET_ORDER=".$ar_order["ID_PACKET_ORDER"])
				);
				$this->ar_contents[ $ar_order["FK_PACKET"] ] = $ar_order;
			}
		} else {
			throw new Exception("[PacketOrderCollectionOnce] Keine oder ungültige ID! (ID: ".$this->id.")");
		}
	}

	/**
	* Feature aktivieren
	*
	* @see PacketOrderInterface::activate()
	*/
	public function activate() {
		parent::activate();
		// Activate all subelements
		$ar_suborders = array_keys(
			$this->database->fetch_nar("SELECT ID_PACKET_ORDER FROM `packet_order` WHERE FK_COLLECTION=".$this->id)
		);
		if (!empty($ar_suborders)) {
			$packets = PacketManagement::getInstance($this->database);
			foreach ($ar_suborders as $index => $id_order) {
				$suborder = $packets->order_get($id_order);
				if (($suborder != NULL) && !$suborder->isActive()) {
					$suborder->activate();
				}
			}
		}
		return true;
	}

	/**
	 * Feature deaktivieren
	 *
	 * @see PacketOrderInterface::deactivate()
	 */
	public function deactivate() {
		parent::deactivate();
		// Deactivate all subelements
		$ar_suborders = array_keys(
			$this->database->fetch_nar("SELECT ID_PACKET_ORDER FROM `packet_order` WHERE FK_COLLECTION=".$this->id)
		);
		if (!empty($ar_suborders)) {
			$packets = PacketManagement::getInstance($this->database);
			foreach ($ar_suborders as $index => $id_order) {
				$suborder = $packets->order_get($id_order);
				if (($suborder != NULL) && $suborder->isActive()) {
					$suborder->deactivate();
				}
			}
		}
		return true;
	}

	/**
	 * Kündigt das Abo.
	 *
	 * @return Wahr wenn das abo gekündigt wurde
	 */
	public function cancel() {
		parent::cancel();
		// Cancel all subelements
		$ar_suborders = array_keys(
			$this->database->fetch_nar("SELECT ID_PACKET_ORDER FROM `packet_order` WHERE FK_COLLECTION=".$this->id)
		);
		if (!empty($ar_suborders)) {
			$packets = PacketManagement::getInstance($this->database);
			foreach ($ar_suborders as $index => $id_order) {
				$suborder = $packets->order_get($id_order);
				if (($suborder != NULL) && $suborder->isActive()) {
					$suborder->cancel();
				}
			}
		}
		return true;
	}

    public function cancelNow($isUpgrade = false) {
        $ar_suborders = array_keys(
            $this->database->fetch_nar("SELECT ID_PACKET_ORDER FROM `packet_order` WHERE FK_COLLECTION=".$this->id)
        );
        if (!empty($ar_suborders)) {
            $packets = PacketManagement::getInstance($this->database);
            foreach ($ar_suborders as $index => $id_order) {
                $suborder = $packets->order_get($id_order);
                if (($suborder != NULL) && $suborder->isActive()) {
                    $suborder->cancelNow();
                }
            }
        }

        parent::cancelNow();

        return true;
    }

	/**
	 * Erstellt die Verküpfung zu einem Bestandteil des Pakets
	 *
	 * @param string	$sType		z.B.: "ad", "image", "news"
	 * @param int		$fk_item
	 */
	public function itemAddContent($sType, $fk_item) {
		$id_packet = PacketManagement::getType( $sType . ($this->isRecurring() ? "_abo" : "_once") );
		$ar_content = $this->ar_contents[$id_packet];
		if (!empty($ar_content)) {
			$packets = PacketManagement::getInstance($this->database);
			$order_content = $packets->order_get($ar_content["ID_PACKET_ORDER"]);
			if ($order_content != NULL) {
				if ($order_content->itemAdd($fk_item)) {
					$this->update();
					return true;
				}
			}
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
		$ar_content = $this->ar_contents[$id_packet];
		if (!empty($ar_content)) {
			$packets = PacketManagement::getInstance($this->database);
			$order_content = $packets->order_get($ar_content["ID_PACKET_ORDER"]);
			if ($order_content != NULL) {
				if ($order_content->itemRemove($fk_item)) {
					$this->update();
					return true;
				}
			}
		}
		return false;
	}

	/**
	* Gibt den Inhalt einer "Collection" als Text aus. z.B.: "1 Anzeige, 2 Bilder"
	*
	* @param int $language		Die Sprache in der das Paket gelesen werden soll (false/auslassen für Standard-Sprache)
	*/
	public function getCollectionContent($language = false, $usage = false) {
		global $langval;
		$language = ($language > 0 ? (int)$language : $langval);
		$query = "SELECT SQL_CALC_FOUND_ROWS
						i.COUNT_USED, i.COUNT_MAX, (i.COUNT_MAX - i.COUNT_USED) as COUNT_FREE, s.*
					FROM `packet_order` i
					LEFT JOIN `packet` p ON  p.ID_PACKET=i.FK_PACKET
					LEFT JOIN `string_packet` s ON s.S_TABLE='packet' AND s.FK=p.ID_PACKET AND
						s.BF_LANG=if(p.BF_LANG_PACKET & ".$language.", ".$language.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
					WHERE i.FK_COLLECTION=".$this->id;
		$ar_packets = $this->database->fetch_table($query);
		$ar_result = array();
		foreach ($ar_packets as $index => $ar_packet) {
			if ($ar_packet["COUNT_MAX"] == 0) {
				continue;
			}
			$usageLeft = $ar_packet["COUNT_MAX"] - $ar_packet["COUNT_USED"];
			$usageText = ($usage ? ($usageLeft > 0 ? $usageLeft : 0).'/' : '');
			if ($ar_packet["COUNT_MAX"] != 1) {
				// Mehrzahl
				$ar_result[] = ($ar_packet["COUNT_MAX"] >= 0 ? $usageText.$ar_packet["COUNT_MAX"] : "Flatrate:")." ".$ar_packet["V2"];
			} else {
				// Einzahl
				$ar_result[] = $usageText.$ar_packet["COUNT_MAX"]." ".$ar_packet["V1"];
			}
		}
		return implode("\n", $ar_result);
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
		$ar_packet_ads = $this->getContentByType(PacketManagement::getType($ar_result["ads_type"]));
		$ar_packet_images = $this->getContentByType(PacketManagement::getType($ar_result["images_type"]));
		$ar_packet_videos = $this->getContentByType(PacketManagement::getType($ar_result["videos_type"]));
		$ar_packet_uploads = $this->getContentByType(PacketManagement::getType($ar_result["downloads_type"]));
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
				if ($forceNew || (!is_array($ar_packet_ads["USAGE"]) || !in_array($id_anzeige, $ar_packet_ads["USAGE"]))) {
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
				if ($forceNew || (!is_array($ar_packet_images["USAGE"]) || !in_array($id_image, $ar_packet_images["USAGE"]))) {
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
				if ($forceNew || (!is_array($ar_packet_videos["USAGE"]) || !in_array($id_video, $ar_packet_videos["USAGE"]))) {
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
				if ($forceNew || (!is_array($ar_packet_uploads["USAGE"]) || !in_array($id_upload, $ar_packet_uploads["USAGE"]))) {
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
			if (!empty($ar_packet_ads)) {
				if ($ar_packet_ads["COUNT_MAX"]  == -1) {
					$ar_result["ads_available"] = PHP_INT_MAX;
				} else {
					$ar_result["ads_available"] = ($ar_packet_ads["COUNT_MAX"] - $ar_packet_ads["COUNT_USED"]);
				}
			}
			$ar_result["ads_available"] += $ads_free - count($ar_result["ads_new"]);
			### Bilder
			if (!empty($ar_packet_images)) {
				if ($ar_packet_images["COUNT_MAX"]  == -1) {
					$ar_result["images_available"] = PHP_INT_MAX;
				} else {
					$ar_result["images_available"] = ($ar_packet_images["COUNT_MAX"] - $ar_packet_images["COUNT_USED"]);
				}
			}
			$ar_result["images_available"] += $images_free - count($ar_result["images_new"]);
			### Videos
			if (!empty($ar_packet_videos)) {
				if ($ar_packet_videos["COUNT_MAX"] == -1) {
					$ar_result["videos_available"] = PHP_INT_MAX;
				} else {
					$ar_result["videos_available"] = ($ar_packet_videos["COUNT_MAX"] - $ar_packet_videos["COUNT_USED"]);
				}
			}
			$ar_result["videos_available"] += $videos_free - count($ar_result["videos_new"]);
			### Dateien
			if (!empty($ar_packet_uploads)) {
				if ($ar_packet_uploads["COUNT_MAX"] == -1) {
					$ar_result["downloads_available"] = PHP_INT_MAX;
				} else {
					$ar_result["downloads_available"] = ($ar_packet_uploads["COUNT_MAX"] - $ar_packet_uploads["COUNT_USED"]);
				}
			}
			$ar_result["downloads_available"] += $uploads_free - count($ar_result["downloads_new"]);
		}
		return $ar_result;
	}

	/**
	 * Paket des angegebenen Typs aus dieser Collection ausgeben. (Unterelement)
	 *
	 * @param int $id_packet	Paket-Typ
	 */
	public function getContentByType($id_packet) {
		if (isset($this->ar_contents[$id_packet])) {
			return $this->ar_contents[$id_packet];
		} else {
			return array();
		}
	}

	/**
	 * Wird das Paket von der angegebenen Anzeige/News/... verwendet?
	 *
	 * @param string	$sType		z.B.: "ad", "image", "news"
	 * @param int		$id
	 * @return bool
	 */
	public function isUsed($sType, $id) {
		$type = PacketManagement::getType( $sType . ($this->isRecurring() ? "_abo" : "_once") );
		$ar_packet_contents = $this->ar_contents[$type];
		if (!empty($ar_packet_contents)) {
			if (in_array($id, $ar_packet_contents["USAGE"])) {
				return true;
			}
		}
		return false;
	}

	/**
	* Ist die angegebene Anzahl an Anzeige/News/... noch verfügbar?
	*
	* @param string		$sType		z.B.: "ad", "image", "news"
	* @param int 		$id
	* @return bool
	*/
	public function isAvailable($sType, $count) {
		$type = PacketManagement::getType( $sType . ($this->isRecurring() ? "_abo" : "_once") );
		$ar_packet_contents = $this->ar_contents[$type];
		if (!empty($ar_packet_contents)) {
			if ($ar_packet_contents["COUNT_MAX"] == -1) {
				// Flatrate
				return true;
			}
			if (($ar_packet_contents["COUNT_MAX"] - $ar_packet_contents["COUNT_USED"]) >= $count) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Inhalte in anderes Paket verschieben
	 * @see PacketOrderBase::moveContent()
	 */
	public function moveContent($id_packet_order, &$ar_failed = array()) {
		// Move all subelements
		$result = true;
		$ar_suborders = array_keys(
			$this->database->fetch_nar("SELECT ID_PACKET_ORDER FROM `packet_order` WHERE FK_COLLECTION=".$this->id)
		);
		if (!empty($ar_suborders)) {
			$packets = PacketManagement::getInstance($this->database);
			foreach ($ar_suborders as $index => $id_order) {
				$suborder = $packets->order_get($id_order);
				if (($suborder != NULL) && $suborder->isActive()) {
					if (!$suborder->moveContent($id_packet_order, $ar_failed)) {
						$result = false;
					}
				}
			}
			// Update remaining links to new membership/packet
			$this->database->querynow("UPDATE `ad_master` SET FK_PACKET_ORDER=".(int)$id_packet_order." WHERE FK_PACKET_ORDER=".(int)$this->id);
		}
		return $result;
	}

	public function setContentCount($id_packet, $count) {
		$ar_packet_contents = $this->ar_contents[$id_packet];
		if (!empty($ar_packet_contents)) {
			if (($ar_packet_contents["COUNT_USED"] <= $count) || ($count == -1)) {
				$this->database->querynow("UPDATE `packet_order` SET COUNT_MAX=".(int)$count."
						WHERE ID_PACKET_ORDER=".(int)$ar_packet_contents["ID_PACKET_ORDER"]);
				return true;
			}
		} else if ($count != 0) {
			$contentType = ($this->PacketOrder["BILLING_CYCLE"] == "ONCE" ? "BASE" : "BASE_ABO");
			$result = $this->database->querynow("
				INSERT INTO `packet_order` 
					(`TYPE`, `FK_PACKET`, `FK_COLLECTION`, `FK_USER`, `STATUS`, `STAMP_START`, `STAMP_NEXT`, `STAMP_END`, `STAMP_CANCEL_UNTIL`, 
						`BILLING_FACTOR`, `BILLING_CYCLE`, `BILLING_CANCEL_DAYS`, `RUNTIME_FACTOR`, `COUNT_USED`, `COUNT_MAX`, `PRICE`)
				VALUES ('".$contentType."', '".$id_packet."', '".$this->PacketOrder["ID_PACKET_ORDER"]."', '".$this->PacketOrder["FK_USER"]."', 
						'".$this->PacketOrder["STATUS"]."', 
						".(!empty($this->PacketOrder["STAMP_START"]) ? "'".$this->PacketOrder["STAMP_START"]."'" : "NULL").",
						".(!empty($this->PacketOrder["STAMP_NEXT"]) ? "'".$this->PacketOrder["STAMP_NEXT"]."'" : "NULL").",
						".(!empty($this->PacketOrder["STAMP_END"]) ? "'".$this->PacketOrder["STAMP_END"]."'" : "NULL").",
						".(!empty($this->PacketOrder["STAMP_CANCEL_UNTIL"]) ? "'".$this->PacketOrder["STAMP_CANCEL_UNTIL"]."'" : "NULL").",
						'".$this->PacketOrder["BILLING_FACTOR"]."', '".$this->PacketOrder["BILLING_CYCLE"]."', '".$this->PacketOrder["BILLING_CANCEL_DAYS"]."', 
						'".$this->PacketOrder["RUNTIME_FACTOR"]."', '0', ".(int)$count.", 0)");
			return $result["rsrc"];
		}
		return false;
	}

	/**
	 * Paket und Inhalte neu auslesen
	 * @throws Exception
	 */
	private function update() {
		$this->PacketOrder = $this->database->fetch1("SELECT * FROM `packet_order` WHERE ID_PACKET_ORDER=".$this->id);
		if (!$this->PacketOrder) {
			// Fehler beim lesen des Datensatzes
			throw new Exception("[PacketOrderCollectionOnce] PacketOrder nicht gefunden! (ID: ".$this->id.")");
		}
		$ar_childs = $this->database->fetch_table("SELECT o.* FROM `packet_order` c
				INNER JOIN `packet_order` o ON o.FK_COLLECTION=c.ID_PACKET_ORDER
				WHERE c.ID_PACKET_ORDER=".$this->id);
		foreach ($ar_childs as $index => $ar_order) {
			$ar_order["USAGE"] = array_keys(
				$this->database->fetch_nar("SELECT FK FROM `packet_order_usage` WHERE ID_PACKET_ORDER=".$ar_order["ID_PACKET_ORDER"])
			);
			$this->ar_contents[ $ar_order["FK_PACKET"] ] = $ar_order;
		}
	}

}

?>
