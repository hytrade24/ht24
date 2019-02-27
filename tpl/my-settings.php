<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
#$userManagement = UserManagement::getInstance($db);

#$user = $userManagement->fetchById($uid);


function get_values_from_lookup ($art,$selected='') {
	global $db, $langval;

	$options = $db->fetch_table("select t.*, s.V1, s.V2, s.T1 from `lookup` t left join string s on s.S_TABLE='lookup' and s.FK=t.ID_LOOKUP and s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2))) where art='".$art."' order by VALUE");
	$option = array();

	for($i=0; $i<count($options); $i++)
			$option[] = '<option value="'.$options[$i]['VALUE'].'"'.($options[$i]['VALUE'] == $selected ? ' selected' : '').'>'.stdHtmlentities($options[$i]['V1']).'</option>';

	return $option;
}

if (count($_POST))  //Daten speichern
{

	if (is_array($_POST['ALLOW_COMMENTS'])) {
		$arDefaults = $_POST['ALLOW_COMMENTS'];
		$_POST['ALLOW_COMMENTS'] = 0;
		foreach ($arDefaults as $index => $bitValue) {
			$_POST['ALLOW_COMMENTS'] += $bitValue;
		}
	}

	$vendorCommentsAllowed = (($_POST['ALLOW_COMMENTS'] & 8) == 8 ? 1 : 0);
	$db->querynow("UPDATE `vendor` SET ALLOW_COMMENTS=".$vendorCommentsAllowed." WHERE FK_USER=".(int)$uid);

    $db->querynow("UPDATE `usersettings` SET
        LU_SHOWCONTAC='" . mysql_real_escape_string($_POST['LU_SHOWCONTAC']) . "',
        GET_MAIL_MSG=" . mysql_real_escape_string(($_POST['GET_MAIL_MSG'] ? $_POST['GET_MAIL_MSG'] : 0)) . ",
        GET_MAIL_AD_TIMEOUT=" . mysql_real_escape_string(($_POST['GET_MAIL_AD_TIMEOUT'] ? $_POST['GET_MAIL_AD_TIMEOUT'] : 0)) . ",
        ALLOW_CONTACS=" . mysql_real_escape_string(($_POST['ALLOW_CONTACS'] ? $_POST['ALLOW_CONTACS'] : 0)) . ",
        ALLOW_ADD_USER_CONTACT=" . mysql_real_escape_string(($_POST['ALLOW_ADD_USER_CONTACT'] ? $_POST['ALLOW_ADD_USER_CONTACT'] : 0)) . ",
        ALLOW_COMMENTS=" . mysql_real_escape_string(($_POST['ALLOW_COMMENTS'] ? $_POST['ALLOW_COMMENTS'] : 0)) . ",
        SHOW_STATUS_USER_ONLINE=" . mysql_real_escape_string(($_POST['SHOW_STATUS_USER_ONLINE'] ? $_POST['SHOW_STATUS_USER_ONLINE'] : 0)) . ",
        SET_AUTOCONFIRM=" . mysql_real_escape_string(($_POST['SET_AUTOCONFIRM'] ? $_POST['SET_AUTOCONFIRM'] : 0)) . ",
        SET_COMMENT_MANUAL=" . mysql_real_escape_string(($_POST['SET_COMMENT_MANUAL'] ? $_POST['SET_COMMENT_MANUAL'] : 0)) . "
        where FK_USER=" . mysql_real_escape_string($uid));


    $default_constraints = 0;
    if ($nar_systemsettings['MARKTPLATZ']['AD_CONSTRAINTS']) {
    	if (is_array($_POST["DEFAULT_CONSTRAINTS"])) {
    		foreach ($_POST["DEFAULT_CONSTRAINTS"] as $key => $value) {
    			$default_constraints += $value;
    		}
    	}
    }

    $db->update('user', array(
        'ID_USER' => $uid,
    	'FK_USER_INVOICE' => (int)$_POST["ID_USER_INVOICE"],
    	'FK_USER_VERSAND' => (int)$_POST["ID_USER_VERSAND"],
    	'DEFAULT_CONSTRAINTS' => $default_constraints,
        'ABO_FORUM' => (int)$_POST["ABO_FORUM"]
    ));
    // User-Array updaten
    $user["FK_USER_INVOICE"] = (int)$_POST["ID_USER_INVOICE"];
	$user["FK_USER_VERSAND"] = (int)$_POST["ID_USER_VERSAND"];

    $saveResult = true;

    
    // Plugin settings (submit)
    $eventUserSettingsSubmit = new Api_Entities_EventParamContainer(array(
        "userId"        => $uid,
        "userData"  		=> $user,
        "template"      => $tpl_content,          
        "result"        => $saveResult
    ));
    Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::USER_SETTINGS_SUBMIT, $eventUserSettingsSubmit);
    if ($eventUserSettingsSubmit->isDirty()) {
        $saveResult = $eventUserSettingsSubmit->getParam("result");
    }
    

    if($saveResult == TRUE) {
	    $tpl_content->addvar('ok',1);
    } else {
        $tpl_content->addvar('err',1);

    }

}


#$user = $userManagement->fetchById($uid);

$data = $db->fetch1("select uus.*,CACHE from usersettings uus left join user on FK_USER=ID_USER where FK_USER=". $uid);
if (is_array($data)) {
	$data = array_merge($data, $user);
	$data["ID_USER_INVOICE"] = $user["FK_USER_INVOICE"];
	$data["ID_USER_VERSAND"] = $user["FK_USER_VERSAND"];
	$data["ALLOW_COMMENTS"] = ($data["ALLOW_COMMENTS"] & 7);
	$data["ALLOW_COMMENTS"] += (8 * $db->fetch_atom("SELECT ALLOW_COMMENTS FROM `vendor` WHERE FK_USER=".(int)$uid));
	$data = AdConstraintManagement::appendAdContraintMapping($data, "DEFAULT_CONSTRAINTS");
	$tpl_content->addvars($data);
}
$tpl_content->addvar("s_kontakt", implode("\n", get_values_from_lookup('SHOWCONTAC',$data['LU_SHOWCONTAC'])));
$tpl_content->addvar("AD_CONSTRAINTS", $nar_systemsettings['MARKTPLATZ']['AD_CONSTRAINTS']);
$tpl_content->addvar("ALLOW_COMMENTS_AD", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_AD']);
$tpl_content->addvar("COMMENT_CONFIRM", $nar_systemsettings['SITE']['COMMENT_CONFIRM']);

if (count($_POST))  //cachen
{

/*
      foreach ($data as $row)
        $ar[$row['plugin']][$row['typ']] = $row['value'];
	*/
      $s_code = '<?'. 'php $useroptions = '. php_dump($data, 0). '; ?'. '>';
      $fp = fopen($GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$data['CACHE'].'/'.$uid."/useroptions.php", 'w');
      fputs($fp, $s_code);
      fclose ($fp);
}


require_once $ab_path.'sys/lib.user.authentication.php';
$userAuthenticationManagement = UserAuthenticationManagement::getInstance($db);
$tpl_content->addvar('SOCIAL_MEDIA_LOGIN_ENABLED', $userAuthenticationManagement->isSocialMediaLoginEnabled());

// Plugin settings
$eventUserSettings = new Api_Entities_EventParamContainer(array(
    "userId"        => $uid,
    "userData"  		=> $user,
    "template"      => $tpl_content, 
    "pluginHtml"    => ""
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::USER_SETTINGS, $eventUserSettings);
if ($eventUserSettings->isDirty()) {
    $tpl_content->addvar("PLUGIN_HTML", $eventUserSettings->getParam("pluginHtml"));
}

?>
