<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $db, $langval, $nar_systemsettings;

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$abo_old = $db->fetch_nar("SELECT ID_PACKET_ORDER, FK_USER FROM `packet_order`
	WHERE (DATEDIFF(STAMP_END, NOW()) = 3) AND FK_COLLECTION IS NULL AND STAMP_NEXT IS NOT NULL AND PRICE>0");

if (count($abo_old) > 0) {
  	// Bald endende abos gefunden
  	foreach ($abo_old as $id_packet_order => $id_user) {
  		$order = $packets->order_get($id_packet_order);
  		if ($order != null) {
        	$ad_user = $db->fetch1("SELECT * FROM user WHERE ID_USER=".$order->getUserId());
	        //$ad_user_wants_mail = $db->fetch_atom("SELECT GET_MAIL_AD_TIMEOUT FROM usersettings WHERE FK_USER=".$ad["FK_USER"]);
	        //if ($ad_user_wants_mail) {

			// Erinnerungs E-Mail verschicken
			$mail_content = array_merge($ad_user, array(
				"STAMP_UNTIL"	=> $order->getPaymentDateLast(),
				"V1"			=> $order->getPacketName(),
				"SITEURL"		=> $nar_systemsettings['SITE']['SITEURL']
			));

			sendMailTemplateToUser(0, $ad_user["ID_USER"], 'ABO_TIMEOUT', $mail_content);

  			//}
  		}
  	}
}

?>