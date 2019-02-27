<?php
/* ###VERSIONSBLOCKINLCUDE### */


if ($ar_params[2] == "okay") {
    $tpl_content->addvar('allok', 1);
}
if ($ar_params[1] == "password") {
    $tpl_content->addvar('password_change', 1);
}

include $ab_path . "conf/inc.forum_vb.php";

//echo ht(dump($_POST));
$data = $db->fetch1("select * from user where ID_USER=" . $uid);
$data['CH_USERNAME'] = (strstr($data['NAME'], "@") ? 1 : 0);

if (count($_POST)) //Daten speichern
{
    $err = array();
    $doWhat = "unknown";
    /**
     * Passwort ändern
     */
    if ($_POST["do"] == "password") {
        $doWhat = "password";
        if ($_POST['pass1'] && $_POST['pass2'] && ($_POST['pass1'] != $_POST['pass2'])) {
            $err[] = 'PASS_REPEAT';
        } elseif ($_POST['pass1']) {
            //echo "PASS_ok";
            if (strlen($_POST['pass1']) < 6)
                $err[] = 'PASS_TOO_SHORT';
            else {
                $salt = pass_generate_salt();
                $_POST['SALT'] = $salt;
                $_POST['PASS'] = pass_encrypt($_POST['pass1'], $salt);


                $host = $_SERVER['HTTP_HOST'];
                $hack = explode(".", $host);
                $n = count($hack);
                array_shift($hack);
                $cookie_domain = ".".implode(".", $hack);
                $cookieContentHash = pass_encrypt($uid . $_POST['PASS']);

                setcookie('ebizuid_' . session_name() . '_uid', $uid, ($stay ? strtotime('+1 year') : NULL), '/', $cookie_domain);
                setcookie('ebizuid_' . session_name() . '_hash', $cookieContentHash, ($stay ? strtotime('+1 year') : NULL), '/', $cookie_domain);
                $db->querynow("UPDATE `user` SET PASS='".mysql_real_escape_string($_POST['PASS'])."',
                    SALT='".mysql_real_escape_string($_POST['SALT'])."' WHERE ID_USER=".(int)$uid);
            }
        }
    }
    /**
     * Profil ändern
     * AGB/... ändern
     */
    if ($_POST["do"] == "profile") {
        $doWhat = "profile";
        $_POST['ID_USER'] = $uid;

        $username = $data['NAME'];
        date_implode($_POST, 'GEBDAT');
        if (!$data['CH_USERNAME'])
            unset ($_POST['NAME']);
        if (isset($_POST['AGB']) || isset($_POST['WIDERRUF']) || isset($_POST['IMPRESSUM']) || isset($_POST['ZAHLUNG'])) {
            if (trim(strip_tags($_POST['IMPRESSUM'])) == '') {
                $_POST['IMPRESSUM'] = '';
            }

            $db->querynow($a = "INSERT INTO
  				`usercontent`
  					(`FK_USER`, `AGB`, `WIDERRUF`, `ZAHLUNG`, `IMPRESSUM`)
  				VALUES
  					(" . $uid . ",'" . mysql_real_escape_string($_POST['AGB']) . "','" . mysql_real_escape_string($_POST['WIDERRUF']) . "', '" . mysql_real_escape_string($_POST['ZAHLUNG']) . "', '" . mysql_real_escape_string($_POST['IMPRESSUM']) . "')
  			ON DUPLICATE KEY UPDATE
  				AGB='" . mysql_real_escape_string($_POST['AGB']) . "',
  				WIDERRUF='" . mysql_real_escape_string($_POST['WIDERRUF']) . "',
  				ZAHLUNG='" . mysql_real_escape_string($_POST['ZAHLUNG']) . "',
				IMPRESSUM='" . mysql_real_escape_string($_POST['IMPRESSUM']) . "'");
        }

        if (isset($_POST['upd_AGB'])) {
            // AGB Global ändern
            $ar_tables = $db->fetch_table("SELECT * FROM `table_def`");
            foreach ($ar_tables as $index => $ar_table) {
                $db->querynow("
    			UPDATE `" . mysql_escape_string($ar_table["T_NAME"]) . "`
    				SET AD_AGB='" . mysql_escape_string($_POST['AGB']) . "'
    				WHERE FK_USER=" . (int)$uid);
            }
        }
        if (isset($_POST['upd_WIDERRUF'])) {
            // AGB Global ändern
            $ar_tables = $db->fetch_table("SELECT * FROM `table_def`");
            foreach ($ar_tables as $index => $ar_table) {
                $db->querynow("
    			UPDATE `" . mysql_escape_string($ar_table["T_NAME"]) . "`
    				SET AD_WIDERRUF='" . mysql_escape_string($_POST['WIDERRUF']) . "'
    				WHERE FK_USER=" . (int)$uid);
            }
        }

        if (isset($_POST['NAME'])) {
            //die("test");
            if (0 !== validate_nick($_POST['NAME'])) {
                $err[] = "ERR_NAME_INVALID";
            } else {
                $check = $db->fetch_atom("
          select
            ID_USER
          from
            `user`
          where
            `NAME`='" . sqlString($_POST['NAME']) . "'
          ");
                if ($check && $check != $uid)
                    $err[] = "USERNAME_EXISTS";
            } // name is valid
            #echo ht(dump($err)); die();
        } // username given

        // Profilbild ändern
        if ($_POST['DEL_BILD']) {
            copy($ab_path . "uploads/users/no.jpg", $ab_path . "cache/users/" . $data['CACHE'] . "/" . $uid . "/" . $uid . ".jpg");
            copy($ab_path . "uploads/users/no_s.jpg", $ab_path . "cache/users/" . $data['CACHE'] . "/" . $uid . "/" . $uid . "_s.jpg");
            chmod($ab_path . "cache/users/" . $data['CACHE'] . "/" . $uid . "/" . $uid . ".jpg", 0777);
            chmod($ab_path . "cache/users/" . $data['CACHE'] . "/" . $uid . "/" . $uid . "_s.jpg", 0777);
        }
        if ($_FILES['BILD']['tmp_name']) {
            // Bild speichern
            $filedir = $GLOBALS['nar_systemsettings']['SITE']['USER_PATH'] . $data['CACHE'] . "/" . $uid;
            if (named_picupload($_FILES['BILD'], $uid, $filedir, true)) {
                $_POST['LOGO'] = $filedir . '/' . $new_filename['name'] . ".jpg";
                $_POST['LOGO_S'] = $filedir . '/' . $new_filename['name'] . "_s.jpg";
            }
        }

	    $mapsLanguage = $s_lang;
	    $street  = mysql_real_escape_string($_POST["STRASSE"]);
	    $zip  = mysql_real_escape_string($_POST["PLZ"]);
	    $city  = mysql_real_escape_string($_POST["ORT"]);

        if ( $street != "" && $zip != "" && $city != "" && $_POST["FK_COUNTRY"] != "" ) {
	        $q_country = 'SELECT s.V1
					FROM country c
					INNER JOIN string s
					ON c.ID_COUNTRY = '.mysql_real_escape_string($_POST["FK_COUNTRY"]).'
					AND s.S_TABLE = "COUNTRY"
					AND s.FK = c.ID_COUNTRY
					INNER JOIN lang l
					ON l.ABBR = "'.$mapsLanguage.'"
					AND s.BF_LANG = l.BITVAL';
	        $country  = $db->fetch_atom($q_country);

	        $geoCoordinates = Geolocation_Generic::getGeolocationCached($street, $zip, $city, $country, $mapsLanguage);
	        $_POST["LATITUDE"] = $geoCoordinates["LATITUDE"];
	        $_POST["LONGITUDE"] = $geoCoordinates["LONGITUDE"];
        }

        if (!$_POST['EMAIL'] || !$_POST['NACHNAME'] || !$_POST['VORNAME'])
            $err[] = 'ERR_REQUIRED_FELDS'; //multi-ling.msg
        if (!validate_email($_POST['EMAIL']))
            $err[] = 'INVALID_EMAIL';

        if (strlen($_POST['PLZ']) < 3)
            $err[] = 'PLZ';

        if (strlen($_POST['STRASSE']) < 3)
            $err[] = 'STRASSE';

        if (strlen($_POST['ORT']) < 3)
            $err[] = 'ORT';

        // Call API-Event
        $paramsProfileCheck = new Api_Entities_EventParamContainer(array("id" => $uid, "admin" => false, "data" => $_POST, "errors" => $err));
        Api_TraderApiHandler::getInstance($db)->triggerEvent(Api_TraderApiEvents::USER_PROFILE_CHECK, $paramsProfileCheck);
        if ($paramsProfileCheck->isDirty()) {
            $err = $paramsProfileCheck->getParam("errors");
        }
        
        if (empty($err)) {
            // Kein fehler
            if ($_POST['GEBDAT'] == date('Y-m-d'))
                $_POST['GEBDAT'] = 'NULL';


            if ($nar_systemsettings["SITE"]["FORUM_VB"]) {
                $id_vb_user = $db->fetch_atom("SELECT VB_USER FROM `user` WHERE ID_USER=" . (int)$uid);
                if ($id_vb_user > 0) {
                    // vBulletin-Forum wird integriert
                    require_once 'sys/lib.forum_vb.php';
                    $apiForum = new ForumVB();
                    if (!empty($_POST['pass1'])) {
                        // Update password
                        $apiForum->SetUserPassword($id_vb_user, $_POST['pass1']);
                    }
                    if (!empty($_POST['EMAIL'])) {
                        // Update email address
                        $apiForum->SetUserEmail($id_vb_user, $_POST['EMAIL']);
                    }
                }
            }

            $id = $db->update('user', $_POST);

            /*
             if ($_POST['PASS']) {
                 // Falls das Passwort geändert wurde das neue Passwort auch im Forum übernehmen.
                 $salt = $db->fetch_atom("SELECT salt FROM ".$ar_vboptions['table_pref']."user WHERE username='".$username."'");
                 if ($salt) {
                   $userpass = pwd($_POST['pass1'], $salt);
                   $query = "UPDATE ".$ar_vboptions['table_pref']."user SET
                         password='".$userpass."',
                         passworddate = NOW()
                         WHERE username='".$username."'";
                   $forum = $db->querynow($query);
                 }
             }

             */

			// Tax Exemption
			if($_POST['UST_ID'] != "" || $data['UST_ID'] != "") {
				require_once $ab_path.'sys/lib.billing.invoice.taxexempt.php';
				$billingInvoiceTaxExemptManagement = BillingInvoiceTaxExemptManagement::getInstance($db);
				$billingInvoiceTaxExemptManagement->updateVatNumberValidationForUser($uid);
			}

            // position für umkreissuche cachen
            /*
            if ($_POST['PLZ']) {
                include('umkreissuche.php');
                $Suche = new Umkreissuche();
                $Suche->CachePLZ($_POST['PLZ']);
            }
            */
        }
    }

    if (count($err)) { //display error msg
        #echo ht(dump($err));
        $err = implode(",", $err);
        $err = get_messages('null', $err);
        #die(ht(dump($err)));
        $tpl_content->addvar('err', implode('<br /> ', $err));
    } else {
        // Clear user cache
        $lang = $db->fetch_table("select ABBR from lang where B_PUBLIC = 1");

        for ($i = 0; $i < count($lang); $i++) {
            if (file_exists($ab_path . "cache/users/" . $data['CACHE'] . "/" . $uid . "/box." . $lang[$i]['ABBR'] . ".htm"))
                unlink($ab_path . "cache/users/" . $data['CACHE'] . "/" . $uid . "/box." . $lang[$i]['ABBR'] . ".htm");
            //die(ht(dump($lang[$i]['ABBR'])));
        }
        // Call API-Event
        Api_TraderApiHandler::getInstance($db)->triggerEvent(Api_TraderApiEvents::USER_PROFILE_CHANGE, array("id" => $uid, "data" => $_POST));
        // Forward to success page
        die(forward("/my-pages/my-profil,".$doWhat.",okay.htm"));
    }
}


### userbox
//$tpl_content->addvar("USERBOX", $u_box = getUserBox($uid,$data['CACHE']));
//$tpl_main->addvar("USERBOX", $u_box);

$usercontent = $db->fetch1("SELECT * FROM `usercontent` WHERE FK_USER=" . $uid);

#$data = $db->fetch1("select * from user where ID_USER=". $uid);
if (count($_POST))
    $data = array_merge($data, $_POST);
$tpl_content->addvars($data);
$tpl_content->addvar('rand', mt_rand());
if (!empty($usercontent))
    $tpl_content->addvars($usercontent);


require_once $ab_path.'sys/lib.user.authentication.php';
$userAuthenticationManagement = UserAuthenticationManagement::getInstance($db);
$tpl_content->addvar('SOCIAL_MEDIA_LOGIN_ENABLED', $userAuthenticationManagement->isSocialMediaLoginEnabled());

include_once $ab_path . 'sys/lib.map.php';
$googleMaps = GoogleMaps::getInstance();

?>
