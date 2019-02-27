<?php

require_once $ab_path."sys/lib.job.php";
require_once $ab_path."sys/packet_management.php";
require_once $ab_path."sys/lib.billing.creditnote.php";
require_once $ab_path."sys/lib.user.php";

class PacketTargetMembership {

	/**
	 * Anzeigen/etc. übernehmen
	 *
	 * @param int     $id_packet_order
	 */
	public static function prepareUpgrade($db, $membership_from, $membership_to) {
        $userManagement = UserManagement::getInstance($db);
        $packets = PacketManagement::getInstance($db);
        $billingCreditnoteManagement = BillingCreditnoteManagement::getInstance($db);

		if ($membership_from->isRecurring()) {
			// Abo-Mitgliedschaft
			self::moveContent($db, $membership_from, $membership_to->getOrderId());
			if ($membership_from->cancelNow(true)) {
				// Gutschrift buchen
				$creditNoteFactor = (float)$db->fetch_atom(
					"SELECT DATEDIFF(NOW(), '".$membership_from->getPaymentDateNext()."')
						/ DATEDIFF(DATE_SUB('".$membership_from->getPaymentDateNext()."',
							INTERVAL ".$membership_from->getPaymentCycle()."),
							'".$membership_from->getPaymentDateNext()."')");

				$tax = $membership_from->getPaymentTax();

				$creditnoteId = $billingCreditnoteManagement->createCreditnote(array(
					'FK_USER' => $membership_to->getUserId(),
					'DESCRIPTION' => 'Upgrade '.$membership_to->getPacketName(),
					'PRICE' => $membership_from->getPaymentAmount() * $creditNoteFactor,
					'FK_TAX' => $tax['ID_TAX'],
					'STATUS' => BillingCreditnoteManagement::STATUS_ACTIVE
				));

                $maildata = array_merge(
                    array_prefix_key($userManagement->fetchById($membership_to->getUserId()), 'USER_'),
                    array_prefix_key($packets->get($membership_to->getPacketId()), 'MEMBERSHIP_')
                );
                sendMailTemplateToUser(0, $membership_to->getUserId(), "MEMBERSHIP_UPGRADE_SUCCESS", $maildata);

				return true;
			}
		} else {
			self::moveContent($db, $membership_from, $membership_to->getOrderId());
            $membership_from->cancelNow(true);
			// Nicht-Abo-Mitgliedschaft
			$db->querynow("DELETE FROM `packet_order` WHERE FK_COLLECTION=".$membership_from->getOrderId()." AND TYPE='GROUP'");
			$db->querynow("UPDATE `packet_order` SET TYPE='COLLECTION' WHERE ID_PACKET_ORDER=".$membership_from->getOrderId());

			return true;
		}
		return false;
	}

	public static function moveContent($db, $membership_from, $id_packet_order_to) {
        global $langval;

		$ar_failed = array();
		if (!$membership_from->moveContent($id_packet_order_to, $ar_failed)) {
			$tpl_mail = array();
			if (!empty($ar_failed["ad"])) {
				$ar_names = $db->fetch_nar("SELECT ID_AD_MASTER, PRODUKTNAME FROM `ad_master`
						WHERE ID_AD_MASTER IN (".implode(", ", $ar_failed["ad"]).")");
				if (!empty($ar_names)) {
					foreach ($ar_names as $id => $name) {
						$ar_names[$id] = $name." (#".$id.")";
					}
					$tpl_mail["list_ad"] = " - ".implode("\n - ", $ar_names);
				}
			}
			if (!empty($ar_failed["ad_top"])) {
				$ar_names = $db->fetch_nar("SELECT ID_AD_MASTER, PRODUKTNAME FROM `ad_master`
						WHERE ID_AD_MASTER IN (".implode(", ", $ar_failed["ad_top"]).")");
				if (!empty($ar_names)) {
					foreach ($ar_names as $id => $name) {
						$ar_names[$id] = $name." (#".$id.")";
					}
					$tpl_mail["list_ad_top"] = " - ".implode("\n - ", $ar_names);
				}
			}
			if (!empty($ar_failed["vendor_top"])) {
				$ar_names = $db->fetch_nar("SELECT ID_AD_MASTER, NAME FROM `vendor`
						WHERE ID_VENDOR IN (".implode(", ", $ar_failed["vendor_top"]).")");
				if (!empty($ar_names)) {
					foreach ($ar_names as $id => $name) {
						$ar_names[$id] = $name." (#".$id.")";
					}
					$tpl_mail["list_vendor_top"] = " - ".implode("\n - ", $ar_names);
				}
			}
			if (!empty($ar_failed["job"])) {
				$ar_names = $db->fetch_nar("SELECT j.ID_JOB, sj.V1 FROM `job` j
						LEFT JOIN `string_job` sj ON
						sj.FK=j.ID_JOB AND sj.S_TABLE='job' AND
						sj.BF_LANG=if(j.BF_LANG_JOB & ".$langval.", ".$langval.", 1 << floor(log(j.BF_LANG_JOB+0.5)/log(2)))
						WHERE j.ID_JOB IN (".implode(", ", $ar_failed["job"]).")");
				if (!empty($ar_names)) {
					foreach ($ar_names as $id => $name) {
						$ar_names[$id] = $name." (#".$id.")";
					}
					$tpl_mail["list_job"] = " - ".implode("\n - ", $ar_names);
				}
			}
			if (!empty($ar_failed["news"])) {
				$ar_names = $db->fetch_nar("SELECT n.ID_JOB, sn.V1 FROM `job` j
						LEFT JOIN `string_c` sn ON
						sn.FK=j.ID_NEWS AND sn.S_TABLE='news' AND
						sn.BF_LANG=if(n.BF_LANG_JOB & ".$langval.", ".$langval.", 1 << floor(log(n.BF_LANG_JOB+0.5)/log(2)))
						WHERE n.ID_NEWS IN (".implode(", ", $ar_failed["news"]).")");
				if (!empty($ar_names)) {
					foreach ($ar_names as $id => $name) {
						$ar_names[$id] = $name." (#".$id.")";
					}
					$tpl_mail["list_news"] = " - ".implode("\n - ", $ar_names);
				}
			}
			sendMailTemplateToUser(0, $membership_from->getUserId(), 'MEMBERSHIP_UPGRADE_WARNING', $tpl_mail);
		}
	}

}

?>