<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ad_order.php';

if($_REQUEST['scope'] == 'order') {
	$adOrderManagement = AdOrderManagement::getInstance($db);

	$orderId = (int)$_REQUEST['ID_AD_ORDER'];
	$order = $adOrderManagement->fetchById($orderId);


	if($adOrderManagement->existSellerOrderForUserId($orderId, $uid)) {

		switch($_REQUEST['do']) {
			case 'accept':
				$result = $adOrderManagement->markOrderAsConfirmed($orderId);
				break;

			case 'decline':
				$result = $adOrderManagement->markOrderAsDesclined($orderId);
				break;

			case 'setOrderStatus':
				$result = $adOrderManagement->updateOrderStatus($orderId, array(
					'STATUS_SHIPPING' => $_REQUEST['STATUS_SHIPPING'],
					'STATUS_PAYMENT' => $_REQUEST['STATUS_PAYMENT'],
					'SHIPPING_TRACKING_SERVICE' => $_REQUEST['SHIPPING_TRACKING_SERVICE'],
					'SHIPPING_TRACKING_CODE' => $_REQUEST['SHIPPING_TRACKING_CODE'],
				));
				break;
			case 'archive':
				$adOrderManagement->markOrderAsSellerArchived($_REQUEST['ID_AD_ORDER']);

				forward($tpl_content->tpl_uri_action("my-marktplatz-verkaeufe,,,,show_done"));
				die();
				break;
			case 'unarchive':
				$adOrderManagement->markOrderAsSellerArchived($_REQUEST['ID_AD_ORDER'], NULL, AdOrderManagement::STATUS_ARCHIVED_SELLER_DEFAULT);

				forward($tpl_content->tpl_uri_action("my-marktplatz-verkaeufe"));
				die();
				break;
		}
		echo json_encode(array('success' => $result, 'orderId' => $orderId));
		die();
	}

	echo json_encode(array('success' => false));
	die();

} else {
	/***** LEGACY altes Verkaufssystem *****/

	include "sys/lib.ads.php";

	global $db, $uid;

	$id_ad = (int)$ar_params[1];
	$id_ad_sold = (int)$ar_params[2];
	$action = $ar_params[3];
	$confirm = $ar_params[4];

	/**
	 * Einzelne Anzeigen bestätigen oder ablehnen
	 * $action: "accept_batch" oder "decline_batch"
	 */

	$batch = false;
	$query_sales = "SELECT * FROM `ad_sold` WHERE FK_USER_VK=".$uid." AND FK_AD=".$id_ad." AND CONFIRMED=0";
	$query_sales_count = "SELECT count(*) FROM `ad_sold` WHERE FK_USER_VK=".$uid." AND FK_AD=".$id_ad." AND CONFIRMED=0";

	switch ($action) {
		case 'accept_batch':
			$batch = true;
			$tpl_content->addvar("accept", 1);
			if ($confirm == 1) {
				// Aktion bestätigt
				$ar_sales = $db->fetch_table($query_sales);
				if (!empty($ar_sales)) {
					// Offene verkäufe vorhanden
					$error = false;
					foreach ($ar_sales as $index => $ar) {
						if (!AdManagment::BuyConfirm($id_ad, $ar["ID_AD_SOLD"])) {
							$error = true;
							break;
						}
					}
					if ($error) {
						$tpl_content->addvar("error", 1);
					} else {
						$tpl_content->addvar("done", 1);
					}
				}
			}
			break;
		case 'decline_batch':
			$batch = true;
			$tpl_content->addvar("decline", 1);
			if ($confirm == 1) {
				// Aktion bestätigt
				$ar_sales = $db->fetch_table($query_sales);
				if (!empty($ar_sales)) {
					// Offene verkäufe vorhanden
					$error = false;
					foreach ($ar_sales as $index => $ar) {
						if (!AdManagment::BuyDecline($id_ad, $ar["ID_AD_SOLD"], $_POST["reason"], $_POST["disable"])) {
							$error = true;
							break;
						}
					}
					if ($error) {
						$tpl_content->addvar("error", 1);
					} else {
						$tpl_content->addvar("done", 1);
					}
				}
			}
			break;
	}

	/**
	 * Einzelne Anzeigen bestätigen oder ablehnen
	 * $action: "accept" oder "decline"
	 */

	if ($batch) {
		$ar = $db->fetch1("
			SELECT
				ad_master.*,
				`user`.*,
				`user`.NAME AS `USER`,
				(".$query_sales_count.") AS BUYER_COUNT
			FROM
				ad_master
			LEFT JOIN
				`user` ON `user`.ID_USER=ad_master.FK_USER
			WHERE
				ad_master.ID_AD_MASTER = ".$id_ad."
				AND ad_master.FK_USER=".$uid);
		$tpl_content->addvar("batch", 1);
	} else {
		$ar = $db->fetch1("
			SELECT
				ad_master.*,
				ad_sold.*,
				ad_sold.MENGE as MENGE_SOLD,
				ad_sold.FK_USER AS ID_BUYER,
				`user`.*,
				`user`.NAME AS `USER`
			FROM
				ad_sold
			LEFT JOIN
				ad_master ON ad_sold.FK_AD=ad_master.ID_AD_MASTER
			LEFT JOIN
				`user` ON `user`.ID_USER=ad_sold.FK_USER
			WHERE
				ad_sold.ID_AD_SOLD = ".$id_ad_sold."
				AND ad_sold.FK_USER_VK=".$uid);
	}

	if (!empty($ar)) {
		$tpl_content->addvars($ar);
	} else {
		$tpl_content->addvar("not_found", 1);
	}

	switch ($action) {
		case 'accept':
			$tpl_content->addvar("accept", 1);
			if ($confirm == 1) {
				// Aktion bestätigt
				if (($uid == $ar["FK_USER_VK"]) && AdManagment::BuyConfirm($id_ad, $id_ad_sold)) {
					$tpl_content->addvar("done", 1);
				} else {
					$tpl_content->addvar("error", 1);
				}
			}
			break;
		case 'decline':
			$tpl_content->addvar("decline", 1);
			if ($confirm == 1) {
				// Aktion bestätigt
				if (($uid == $ar["FK_USER_VK"]) && AdManagment::BuyDecline($id_ad, $id_ad_sold, $_POST["reason"], $_POST["disable"]) ) {
					$tpl_content->addvar("done", 1);
				} else {
					$tpl_content->addvar("error", 1);
				}
			}
			break;
	}
}

?>