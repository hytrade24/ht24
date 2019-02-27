<?php

/* ###VERSIONSBLOCKINLCUDE### */
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$tpl_main->addvar("size_left", 240);

$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);

$SILENCE=false;
#$tpl_content->table = 'user';
$id = (int)$_REQUEST['ID_USER'];

$data = ($id
? $db->fetch1($db->lang_select('user'). " where ID_USER=". $id)
: $db->fetch_blank('user')
);

if (!empty($_REQUEST["do"])) {
	switch ($_REQUEST["do"]) {
		case "resendMail":
			$arConfirmMail = array( "NAME" => $data["NAME"], "CODE" => $data["CODE"], "LINK_ID" => $id );
			sendMailTemplateToUser(0, $id, 'REGISTER_CONFIRM', $arConfirmMail); // Bestätigungsmail versenden
			break;
	}
	die(forward("index.php?page=user_edit&ID_USER=".$id."&done=".$_REQUEST["do"]));
}
if (!empty($_REQUEST["done"])) {
	$tpl_content->addvar("DONE_".strtoupper($_REQUEST["done"]), 1);
}

if (count($_POST))
{
	$formData = $_POST;
	$_POST = array_merge($data, $_POST);

    $paymentAdapterConfigurationData = $_POST['PAYMENT_ADAPTER_CONFIG'];
    unset($_POST['PAYMENT_ADAPTER_CONFIG']);

	foreach($_POST as $k=>$v) if (strtoupper($k)==$k)
	$_POST[$k] = trim($v);
	$err = $msg = array ();
	if (!$_POST['EMAIL']  || (!$id && (!$_POST['pass1'] || !$_POST['NAME'])))
	$err[] = $err_allrequired;;
	if (!(int)$_POST['ID_USER'] && ($uname = $_POST['NAME']))
	{
		if ($_POST['STAT']==0)
		$_POST['STAT']=1;

		$_POST['STAMP_REG'] = date('Y-m-d');
		$anz = (int)$db->fetch_atom("select count(*) from `user`
      where NAME='". mysql_escape_string($uname). "'");
		if ($anz)
		$err[] = 'Der Username existiert bereits.';
	}

	if(isset($formData['TOP_USER']) && $formData['TOP_USER'] == '1') { $_POST['TOP_USER'] = 1; } else { $_POST['TOP_USER'] = 0; }
	if(isset($formData['TOP_SELLER']) && $formData['TOP_SELLER'] == '1') { $_POST['TOP_SELLER'] = 1; } else { $_POST['TOP_SELLER'] = 0; }
	if(isset($formData['PROOFED']) && $formData['PROOFED'] == '1') { $_POST['PROOFED'] = 1; } else { $_POST['PROOFED'] = 0; }
	if(isset($formData['TAX_EXEMPT']) && $formData['TAX_EXEMPT'] == '1') { $_POST['TAX_EXEMPT'] = 1; } else { $_POST['TAX_EXEMPT'] = 0; }

	if ($_POST['pass1'] && ($_POST['pass1']!=$_POST['pass2']))
	$err[] = 'Das Passwort muss zweimal identisch eingegeben werden.';
	elseif ($_POST['pass1']) {
	    if($data['SALT'] == "") {
            $_POST['SALT'] = pass_generate_salt();
            $data['SALT'] = $_POST['SALT'];
        }
        $_POST['PASS'] = pass_encrypt($_POST['pass1'], $data['SALT']);

        if ($id == $uid) {
		    $cookieContentHash = pass_encrypt($uid.$_POST['PASS']);

		    setcookie ('ebizuid_'.session_name().'_admin_uid', $uid);
		    setcookie ('ebizuid_'.session_name().'_admin_hash', $cookieContentHash);
        }
    }
	#die(ht(dump($_POST)).'<hr>'.ht(dump($msg)));
	if ($_POST['EMAIL'])
	{
		if (!validate_email($_POST['EMAIL']))
		$err[] = 'ung&uuml;ltige E-Mail-Syntax';
		/*
		 elseif ($db->fetch_atom("select count(*) from `user`
		 where "
		 . ($_POST['ID_USER'] ? "ID_USER<>$_POST[ID_USER] and " : '')
		 . "EMAIL='". mysql_escape_string($_POST['EMAIL']). "'"
		 )>0)
		 $msg[] = $err_uniquemail;
		 */
	}
	// Call API-Event
	$paramsProfileCheck = new Api_Entities_EventParamContainer(array("id" => $id, "admin" => true, "data" => $_POST, "errors" => $err));
	Api_TraderApiHandler::getInstance($db)->triggerEvent(Api_TraderApiEvents::USER_PROFILE_CHECK, $paramsProfileCheck);
	if ($paramsProfileCheck->isDirty()) {
		$err = $paramsProfileCheck->getParam("errors");
	}
	
	if (count($err) && !isset($_POST['ignoreErrors']))
	{
		$tpl_content->addvars($_POST);
		$tpl_content->addvar('err', implode('<br />', $err));
		$err = array ();
	}
	else
	{
		$prev_usergroup = $data["FK_USERGROUP"];
		if($_POST['ID_USER'])
		{
			$old_stat = $db->fetch_atom("select `STAT` from `user` where ID_USER=".$_POST['ID_USER']);
			if($old_stat != $_POST['STAT'])
			{
				if($_POST['STAT'] == 1) {
                    eventlog("info", 'User freigeschaltet "'.(isset($_POST["NAME"]) ? $_POST["NAME"] : $data['NAME']).'"');
					// Mail an den Benutzer
					$mail_to = $_POST["ID_USER"];
					$mail_data = $data;

					sendMailTemplateToUser(0, $mail_to, 'USER_REG_CONFIRM', $mail_data, false);

					if ($data["FK_PACKET_RUNTIME"] > 0) {
						// Gutscheincode
						$couponCodeUsageId = (int)$db->fetch_atom("SELECT FK_COUPON_CODE_USAGE FROM `user` WHERE ID_USER=".(int)$id);
						if($couponCodeUsageId > 0) {
							$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);
							try {
								$couponUsage = $couponUsageManagement->fetchActivatedCouponUsageByUserId($couponCodeUsageId, 0, 'PACKET', array($data["FK_PACKET_RUNTIME"]));
							} catch(Exception $e) {
								$db->querynow("DELETE FROM `coupon_code_usage` WHERE ID_COUPON_CODE_USAGE=".(int)$couponCodeUsageId);
								$db->querynow("UPDATE `user` SET FK_COUPON_CODE_USAGE=NULL WHERE ID_USER=".(int)$id);
							}
						}

						// Paket bestellen
						require_once $ab_path."sys/packet_management.php";
						$packets = PacketManagement::getInstance($db);
						$packets->order($data["FK_PACKET_RUNTIME"], $id, 1, null, null,null, $couponUsage);
						//$db->querynow("UPDATE `user` SET FK_PACKET_RUNTIME=NULL WHERE ID_USER=".(int)$id);
						$_POST["FK_USERGROUP"] = $db->fetch_atom("select FK_USERGROUP from user where ID_USER=".(int)$id);
						$_POST["FK_PACKET_RUNTIME"] = null;

						if($couponUsage != null) {
							$db->querynow("UPDATE `user` SET FK_COUPON_CODE_USAGE=NULL WHERE ID_USER=".(int)$id);
							$db->querynow("UPDATE `coupon_code_usage` SET FK_USER=".(int)$id." WHERE ID_COUPON_CODE_USAGE=".(int)$couponCodeUsageId);
						}
					}
                }
				else
				eventlog("warning", 'User "'.$_POST['NAME'].'" gesperrt!');
			} // status geändert
		}
        if (array_key_exists("PROVISION_SALES", $formData) && ($_POST["PROVISION_SALES"] == "")) {
            $_POST["PROVISION_SALES"] = null;
        }
		/*
        if (empty($formData['AUTOCONFIRM_ADS'])) {
            $_POST['AUTOCONFIRM_ADS'] = 0;
        }
        if (empty($formData['AUTOCONFIRM_EVENTS'])) {
            $_POST['AUTOCONFIRM_EVENTS'] = 0;
        }
        if (empty($formData['AUTOCONFIRM_VENDORS'])) {
            $_POST['AUTOCONFIRM_VENDORS'] = 0;
        }
		*/

		date_implode ($_POST,'GEBDAT');
		if ($_POST['GEBDAT'] == date('Y-m-d'))
		  $_POST['GEBDAT']='NULL';

		if ($id > 0) {
			if ($_POST["FK_USERGROUP"] != $prev_usergroup) {
				## Bisherige Benutzerrollen entfernen
				$ar_roles_old = $db->fetch_table("SELECT * FROM `usergroup_role` WHERE FK_USERGROUP=".$prev_usergroup);
				foreach ($ar_roles_old as $index => $ar_role) {
					DelRole2User($ar_role["FK_ROLE"], $id);
				}
			}
		}
        if ($_POST['FK_AUTOR'] > 0) {
            $_POST['FK_USER_SALES'] = (int)$_POST['FK_AUTOR'];
        } else if (isset($_POST['FK_AUTOR'])) {
            $_POST['FK_USER_SALES'] = null;
        }
		$id = $db->update('user', $_POST);

		// Tax Exemption
		if($_POST['UST_ID'] != "" || $data['UST_ID'] != "") {
			require_once $ab_path.'sys/lib.billing.invoice.taxexempt.php';
			$billingInvoiceTaxExemptManagement = BillingInvoiceTaxExemptManagement::getInstance($db);
			$billingInvoiceTaxExemptManagement->updateVatNumberValidationForUser($id);
		}


		if(!$_POST['ID_USER'])
		{
			include $ab_path."sys/lib.usercreate.php";
			createUser($id, $_POST);
		}

		/**
		 * Update Roles
		 */
		if ($_POST["FK_USERGROUP"] != $prev_usergroup) {
			## Neue Benutzerrollen hinzufügen
			$ar_roles_new = $db->fetch_table("SELECT * FROM `usergroup_role` WHERE FK_USERGROUP=".$_POST["FK_USERGROUP"]);
			foreach ($ar_roles_new as $index => $ar_role) {
				AddRole2User($ar_role["FK_ROLE"], $id);
			}
		}

		$db->querynow("UPDATE `usercontent` 
                        SET AGB='".$_POST["AGB"]."', WIDERRUF='".$_POST["WIDERRUF"]."', IMPRESSUM='".$_POST["IMPRESSUM"]."' 
                        
                        WHERE FK_USER=".($_POST["ID_USER"] ? $_POST["ID_USER"] : $id));
        
		if (array_key_exists("CHARGE_AT_ONCE", $_POST) ) {
        $db->querynow("UPDATE `usercontent` SET CHARGE_AT_ONCE=".(int)$_POST["CHARGE_AT_ONCE"]." WHERE FK_USER=".($_POST["ID_USER"] ? $_POST["ID_USER"] : $id));
		}
		if (array_key_exists("PROV_PREPAID", $_POST) ) {
        $db->querynow("UPDATE `usercontent` SET PROV_PREPAID=".(int)$_POST["PROV_PREPAID"]." WHERE FK_USER=".($_POST["ID_USER"] ? $_POST["ID_USER"] : $id));
		}
		#die(ht(dump($_POST)).'<hr>'.ht(dump($lastresult)));
		#die(ht(dump($_POST)));
		#    log_event('user', $id, dump($lastresult));
		$cachedir = $db->fetch_atom("select CACHE from user where ID_USER=". $uid);
		$lang = $db->fetch_table("select ABBR from lang where B_PUBLIC = 1");

		for($i=0; $i<count($lang); $i++) {
			if (file_exists($ab_path."cache/users/".$cachedir."/".$id."/box.".$lang[$i]['ABBR'].".htm"))
			unlink($ab_path."cache/users/".$cachedir."/".$id."/box.".$lang[$i]['ABBR'].".htm");
			//die(ht(dump($lang[$i]['ABBR'])));
		}

        # payment
        if($_POST['PAYMENT_ADAPTER'] == "") {
            $_POST['PAYMENT_ADAPTER'] = NULL;
        }

        $db->update('user', array(
            'ID_USER' => $id,
            'FK_PAYMENT_ADAPTER' => mysql_real_escape_string($_POST['PAYMENT_ADAPTER'])
        ));

        if($data['FK_PAYMENT_ADAPTER'] == $_POST['PAYMENT_ADAPTER'] && $_POST['PAYMENT_ADAPTER'] != NULL) {
            $newUserPaymentAdapter = $paymentAdapterManagement->fetchById($data['FK_PAYMENT_ADAPTER']);

            $newPaymentAdapterConfiguration = array(
                'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($newUserPaymentAdapter['ID_PAYMENT_ADAPTER'])
            );

            /** @var Payment_Adapter_PaymentAdapterInterface $paymentAdapter  */
            $newPaymentAdapter = Payment_PaymentFactory::factory($newUserPaymentAdapter['ADAPTER_NAME'], $newPaymentAdapterConfiguration);
            $newPaymentAdapter->init(array(
                'FK_USER' => $id
            ));
            $newPaymentAdapter->configurationSaveAdminConfiguration($paymentAdapterConfigurationData);
        }

        Api_TraderApiHandler::getInstance($db)->triggerEvent(Api_TraderApiEvents::USER_PROFILE_CHANGE, array("id" => $id, "data" => $_POST));
        #die();
		//forward('index.php?lang='.$s_lang.'&nav='. $id_nav. ($_REQUEST['frame'] ? '&frame='.$_REQUEST['frame'] : '').'&ID_USER='. $id, 2);
	}
}


$data["SIG"] = md5($data["PASS"]);
$data_content = ($id
? $db->fetch1("SELECT * FROM `usercontent` WHERE FK_USER=". $id)
: $db->fetch_blank('usercontent')
);
if(!$data_content)
{
	$data_content = array();
}
$data = array_merge($data, $data_content);
if (count($_POST))
$data = array_merge($data, $_POST);

if ($data["FK_USER_SALES"] > 0) {
    $data["NAME_USER_SALES"] = $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$data["FK_USER_SALES"]);
}
$tpl_content->addvars($data);

### buchhaltung allgemein
$open = $db->fetch_atom("
	SELECT
        SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))
    FROM
      billing_invoice i
    LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
        LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
    WHERE
        i.FK_USER=".$id."
		AND i.STAMP_DUE < CURDATE()
		AND i.STATUS = 0");
$tpl_content->addvar("OPEN", $open);

$umsatz = $db->fetch_atom("
	SELECT
        SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100)))
    FROM
      billing_invoice i
    LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
        LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
    WHERE
		FK_USER=".$id."
		AND STATUS != 2");
$tpl_content->addvar("UMSATZ", $umsatz);

$ads = $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		ad_master
	WHERE
		FK_USER=".$id."
		AND STATUS&3 = 1");
$tpl_content->addvar("ACTIVE_ADS", $ads);

$ar_sale = $db->fetch1("
	SELECT
		COUNT(*) AS sales,
		(
			SELECT
				SUM(PREIS)
			FROM
				ad_sold
			WHERE
				FK_USER_VK=".$id."
		) AS sales_sum
	FROM
		ad_sold
	WHERE
		FK_USER_VK=".$id);
$tpl_content->addvars($ar_sale);

$ar_shop = $db->fetch1("
	SELECT
		COUNT(*) AS shopping,
		(
			SELECT
				SUM(PREIS)
			FROM
				ad_sold
			WHERE
				FK_USER=".$id."
		) AS shopping_sum
	FROM
		ad_sold
	WHERE
		FK_USER=".$id);
$tpl_content->addvars($ar_shop);

## payment

$paymentAdapters = $paymentAdapterManagement->fetchAllByParam(array('STATUS' => PaymentAdapterManagement::STATUS_ENABLED));
foreach($paymentAdapters as $key => $tplPaymentAdapter) {
    $paymentAdapters[$key]['CURRENT_PAYMENT_ADAPTER'] = $data['FK_PAYMENT_ADAPTER'];
}

$tpl_content->addlist('PAYMENT_ADAPTER', $paymentAdapters, $ab_path."tpl/".$s_lang."/my-settings.payment_adapter_row.htm");

if($data['FK_PAYMENT_ADAPTER'] != NULL && $data['FK_PAYMENT_ADAPTER'] != 0) {
    $userPaymentAdapter = $paymentAdapterManagement->fetchById($data['FK_PAYMENT_ADAPTER']);

    $paymentAdapterConfiguration = array(
        'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($userPaymentAdapter['ID_PAYMENT_ADAPTER'])
    );

    /** @var Payment_Adapter_PaymentAdapterInterface $paymentAdapter  */
    $paymentAdapter = Payment_PaymentFactory::factory($userPaymentAdapter['ADAPTER_NAME'], $paymentAdapterConfiguration);
    $paymentAdapter->init(array(
        'FK_USER' => $id
    ));

    $adminConfigOutput = $paymentAdapter->configurationEditAdminConfiguration();
    $tpl_content->addvar('PAYMENT_ADAPTER_ADMIN_CONFIG', $adminConfigOutput);
}

// Clubs
require_once $ab_path."sys/lib.club.php";
$clubManagement = ClubManagement::getInstance($db);
$userClubs = $clubManagement->getClubsByUser($id);
foreach($userClubs as $key => $club) {
    $userClubs[$key]['LOGO'] = ($club['LOGO'] != "")?'cache/club/logo/'.$club['LOGO']:null;
}
$tpl_content->addlist("clubs", $userClubs, 'tpl/'.$s_lang.'/user_edit.club.htm');

// Membership
$membershipname = $db->fetch_atom("
	SELECT
		sp.V1 as MEMBERSHIP_NAME
	FROM user u
	left join packet_runtime pr ON pr.ID_PACKET_RUNTIME = u.FK_PACKET_RUNTIME
	left join packet p ON p.ID_PACKET = pr.FK_PACKET
	left join
		`string_packet` sp on sp.FK=p.ID_PACKET
		AND sp.S_TABLE='packet'
		AND sp.BF_LANG=if(p.BF_LANG_PACKET & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
	WHERE u.ID_USER = '".$id."'
");
$tpl_content->addvar('MEMBERSHIP_NAME', $membershipname);


// Tax exempt
$tpl_content->addvar('TAX_EXEMPT_ENABLE', $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_ENABLE']);

// Moderate ads
$tpl_content->addvar('MODERATE_ADS', $nar_systemsettings['MARKTPLATZ']['MODERATE_ADS']);
$tpl_content->addvar('MODERATE_EVENTS', $nar_systemsettings['MARKTPLATZ']['MODERATE_EVENTS']);
$tpl_content->addvar('MODERATE_VENDORS', $nar_systemsettings['MARKTPLATZ']['MODERATE_VENDORS']);

if ( !isset($_GET['ID_USER']) ) {
	$tpl_content->addvar('dont_show_graph_and_map',1);
}

$tpl_content_links->vars = $tpl_content->vars;

?>
