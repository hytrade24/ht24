<?php
/* ###VERSIONSBLOCKINLCUDE### */



function createUser($id, $userDataSubmitted = null)
{
  global $db, $ab_path;
  //User anlegen

    if ($userDataSubmitted === null) {
        $userDataSubmitted = $_POST;
    }

    $ar_data = $db->fetch1('select *
				from user where ID_USER = '.$id);

    // Club-Invite zuordnen (wenn vorhanden)
    $db->querynow("UPDATE `club_invite` SET FK_USER=".$id." WHERE EMAIL='".mysql_real_escape_string($ar_data["EMAIL"])."'");

  	### Generate usercontent
    $usercontent = array(
        "CHARGE_AT_ONCE" => $db->fetch_atom("SELECT PREPAID FROM `usergroup` WHERE ID_USERGROUP=".$ar_data["FK_USERGROUP"])
      );

    $db->querynow("INSERT INTO `usercontent` (FK_USER, CHARGE_AT_ONCE) VALUES (".$id.", ".$usercontent['CHARGE_AT_ONCE'].")");
		$res2 = $db->querynow('INSERT INTO `usersettings` (`FK_USER`) values ('.$id.')');

		mkdir ($ab_path."cache/users/".$ar_data['CACHE']."/".$id, 0777);  //Users Cacheverzeichnis
		chmod ($ab_path."cache/users/".$ar_data['CACHE']."/".$id, 0777);  // rechte richig setzen
		$anrede = $db->fetch_atom("SELECT VALUE FROM `lookup` WHERE ID_LOOKUP=".(int)$ar_data["LU_ANREDE"]);
		$image_default = $ab_path."uploads/users/no.jpg";
		$image_gender = $ab_path."uploads/users/no_".$anrede.".jpg";
		if (file_exists($image_gender)) {
			copy($image_gender, $ab_path."cache/users/".$ar_data['CACHE']."/".$id."/".$id.".jpg");
		} else {
			copy($image_default, $ab_path."cache/users/".$ar_data['CACHE']."/".$id."/".$id.".jpg");
		}
		$imagePrev_default = $ab_path."uploads/users/no_s.jpg";
		$imagePrev_gender = $ab_path."uploads/users/no_".$anrede."_s.jpg";
		if (file_exists($imagePrev_gender)) {
			copy($imagePrev_gender, $ab_path."cache/users/".$ar_data['CACHE']."/".$id."/".$id."_s.jpg");
		} else {
			copy($imagePrev_default, $ab_path."cache/users/".$ar_data['CACHE']."/".$id."/".$id."_s.jpg");
		}
		copy($ab_path."uploads/users/no_s.jpg", $ab_path."cache/users/".$ar_data['CACHE']."/".$id."/".$id."_s.jpg");
		chmod($ab_path."cache/users/".$ar_data['CACHE']."/".$id."/".$id.".jpg", 0777);
		chmod($ab_path."cache/users/".$ar_data['CACHE']."/".$id."/".$id."_s.jpg", 0777);

		//mysql_query("update `user` set `LOGO` = 'cache/users/".$ar_data['CACHE']."/".$id."/".$id.".jpg', `LOGO_S` = 'cache/users/".$ar_data['CACHE']."/".$id."/".$id."_s.jpg' where `ID_USER` = ".$id);

		$data = $db->fetch1('select * from usersettings where FK_USER='.$id);

			      $s_code = '<?'. 'php $useroptions = '. php_dump($data, 0). '; ?'. '>';
      			  $fp = fopen($ab_path.$GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$ar_data['CACHE']."/".$data['FK_USER']."/useroptions.php", 'w');
      			  fputs($fp, $s_code);
      			  fclose ($fp);

    // Usergroup Rollen
    $groupRoles = $db->fetch_table($a = "SELECT * FROM usergroup_role WHERE FK_USERGROUP = '".mysql_real_escape_string($ar_data["FK_USERGROUP"])."'");
    if($groupRoles) {
        foreach($groupRoles as $key => $role) {
            AddRole2User($role['FK_ROLE'], $id);
        }
    }

	// Tax Exemption
	if(trim($ar_data['UST_ID']) != "") {
		require_once $ab_path.'sys/lib.billing.invoice.taxexempt.php';
		$billingInvoiceTaxExemptManagement = BillingInvoiceTaxExemptManagement::getInstance($db);
		$billingInvoiceTaxExemptManagement->updateVatNumberValidationForUser($id);
	}

    Api_TraderApiHandler::getInstance($db)->triggerEvent(Api_TraderApiEvents::USER_NEW, array("id" => $id, "data" => $userDataSubmitted));
}

?>