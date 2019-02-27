<?php

require_once $ab_path."sys/packet_order_once.php";
require_once $ab_path."sys/packet_order/targets/ad.php";

class PacketOrderAdOnce extends PacketOrderOnce {

	/**
	 * Feature aktivieren
	 *
	 * @see PacketOrderInterface::activate()
	 */
	public function activate() {
		parent::activate();
		return PacketTargetAd::activate($this->database, $this->id);
	}

	/**
	 * Feature deaktivieren
	 *
	 * @see PacketOrderInterface::deactivate()
	 */
	public function deactivate() {
		parent::deactivate();
		return PacketTargetAd::deactivate($this->database, $this->id);
	}

	/**
	 * Inhalte in anderes Paket verschieben
	 * @see PacketOrderBase::moveContent()
	 */
	public function moveContent($id_packet_order, &$ar_failed = array()) {
		// Move all subelements
		$packets = PacketManagement::getInstance($this->database);
		$ar_ads = array_keys(
				$this->database->fetch_nar("SELECT FK, ID_PACKET_ORDER FROM `packet_order_usage` WHERE ID_PACKET_ORDER=".$this->id)
			);
		$adOrderOld = $this->getRoot();
		$adOrderNew = $packets->order_get($id_packet_order);
		// Move all ads
		$result = true;

		foreach ($ar_ads as $index => $id_ad) {
			$arOrderNewUsage = $adOrderNew->getPacketUsage($id_ad);

			if (($arOrderNewUsage["ads_available"] >= 0)
					&& ($arOrderNewUsage["images_available"] >= 0)
					&& ($arOrderNewUsage["videos_available"] >= 0)
					&& ($arOrderNewUsage["downloads_available"] >= 0)) {
				// Anzeigen
				$adOrderOld->itemRemContent("ad", $id_ad);
                $adOrderNew->itemAddContent("ad", $id_ad);
				$this->database->querynow("UPDATE `ad_master` SET FK_PACKET_ORDER=".(int)$id_packet_order." WHERE ID_AD_MASTER=".(int)$id_ad);

				// Bilder
				foreach ($arOrderNewUsage["images_new"] as $index => $id_image) {
					$adOrderOld->itemRemContent("image", $id_image);
					$adOrderNew->itemAddContent("image", $id_image);
				}
				// Videos
				foreach ($arOrderNewUsage["videos_new"] as $index => $id_video) {
					$adOrderOld->itemRemContent("video", $id_video);
					$adOrderNew->itemAddContent("video", $id_video);
				}
				// Downloads
				foreach ($arOrderNewUsage["downloads_new"] as $index => $id_download) {
					$adOrderOld->itemRemContent("download", $id_download);
					$adOrderNew->itemAddContent("download", $id_download);
				}
			} else {
				if (!is_array($ar_failed["ad"])) {
					$ar_failed["ad"] = array();
				}
				$ar_failed["ad"][] = $id_ad;
				$result = false;
			}
		}
		return $result;
	}

}

?>