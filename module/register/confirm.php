<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

 ### Confirm File for Register

if(!isset($ar_params[2]))
	$tpl_content->addvar("confirm_mail", 1);
#echo ht(dump($ar_params));
if(!empty($ar_params[3])) {
	$code = $ar_params[3];
	$id = $ar_params[4];

	// Standard-Benutzergruppe
	$id_usergroup = (int)$db->fetch_atom("SELECT FK_USERGROUP FROM `user` WHERE ID_USER=".(int)$id);
	// Zukünftige Benutzergruppe auslesen sofern vorhanden
	$id_packet = (int)$db->fetch_atom("SELECT FK_PACKET_RUNTIME FROM `user` WHERE ID_USER=".(int)$id);
	if ($id_packet > 0) {
		$query = "
		SELECT pc.PARAMS
			FROM `packet_collection` pc
			JOIN `packet_runtime` pr ON pr.FK_PACKET=pc.ID_PACKET AND
				(pc.FK_PACKET=".PacketManagement::getType("usergroup_once")."
					OR pc.FK_PACKET=".PacketManagement::getType("usergroup_abo").")
		WHERE pr.ID_PACKET_RUNTIME=".$id_packet;
		$id_usergroup = (int)$db->fetch_atom($query);
	}

	$check = $db->fetch_atom("select ID_USER from user where ID_USER=".(int)$id." and CODE='".mysql_real_escape_string($code)."'");
	$check2 = $db->fetch_atom("SELECT UNLOCK_MANUAL FROM `usergroup` WHERE ID_USERGROUP=".(int)$id_usergroup);
	if(!$check) {
		$exists = $db->fetch1("select ID_USER, CODE from user where ID_USER=".(int)$id);
	 	if(empty($exists)) {
			$tpl_content->addvar("err", "Der angegebene Benutzer existiert nicht!");
	 	} elseif(empty($exists['CODE'])) {
	 		$tpl_content->addvar("err",  "Dieser Benutzer ist bereits freigeschaltet!");
	 	}
	} else if ($check2) {
        $db->querynow("UPDATE `user` SET CODE=NULL,STAT=2,IS_VIRTUAL=0 WHERE ID_USER=".(int)$id." and CODE='".mysql_real_escape_string($code)."'");
		$tpl_content->addvar("check_admin",  1);
		if ($nar_systemsettings['USER']['SEND_REGADMINMAIL'] == '1') {
			$usrdata = $db->fetch1("select * from user where ID_USER=".(int)$id);
			sendMailTemplateToUser(0, 0, 'USER_REG_TO_ADMIN2', $usrdata);
		}
	} else {
		$up = $db->querynow("update user set CODE=NULL,STAT=1,IS_VIRTUAL=0 where ID_USER=".(int)$id);
		$usrdata = $db->fetch1("select * from user where ID_USER=".(int)$id);
        //eventlog("info", "Vendor debug: ", "Confirm: ".var_export($usrdata, true));

		if($usrdata['VB_USER'] == 1) {
            #echo "HALLLLLLO?????";
		  include_once "conf/inc.forum.php";
		  $res = $db->querynow("update ".$ar_vboptions['table_pref']."user set usergroupid=".$ar_vboptions['group_user']." where username='".sqlString($usrdata['NAME'])."'");
		  if(!$res['rsrc'])
		    eventlog("error", "VB User freischalten fehlgeschlagen!", dump($res));
		  #die(ht(dump($res)));
		} // ist VB User

		sendMailTemplateToUser(0, $id, 'REGISTER_CONFIRMED', $usrdata);

		if ($nar_systemsettings['USER']['SEND_REGADMINMAIL'] == '1') {
			sendMailTemplateToUser(0, 0, 'USER_REG_TO_ADMIN', $usrdata);
		}

		// --------- Trader ------------

	    ### Add initial packet (if exists)
		if ($usrdata["FK_PACKET_RUNTIME"] > 0) {

			// Gutscheincode
			$couponCodeUsageId = (int)$db->fetch_atom("SELECT FK_COUPON_CODE_USAGE FROM `user` WHERE ID_USER=".(int)$id);
			if($couponCodeUsageId > 0) {
				$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);
				try {
					$couponUsage = $couponUsageManagement->fetchActivatedCouponUsageByUserId($couponCodeUsageId, $id, 'PACKET', array($usrdata["FK_PACKET_RUNTIME"]));
					$db->querynow("UPDATE `user` SET FK_COUPON_CODE_USAGE=NULL WHERE ID_USER=".(int)$id);
				} catch(Exception $e) {
					eventlog("error", "Coupon-Code konnte nicht eingelöst werden!", $e->getMessage());
					//$db->querynow("DELETE FROM `coupon_code_usage` WHERE ID_COUPON_CODE_USAGE=".(int)$couponCodeUsageId);
					$db->querynow("UPDATE `user` SET FK_COUPON_CODE_USAGE=NULL WHERE ID_USER=".(int)$id);
				}
			}

			// Paket bestellen
			$packets->order($usrdata["FK_PACKET_RUNTIME"], $id, 1, NULL, NULL, NULL, $couponUsage);
			$db->querynow("UPDATE `user` SET FK_PACKET_RUNTIME=NULL WHERE ID_USER=".(int)$id);
			$usrdata = $db->fetch1("select * from user where ID_USER=".(int)$id);
		}

		forward("/register,welcome,".$id.".htm");
	}
}

?>
