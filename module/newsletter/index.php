<?php
/* ###VERSIONSBLOCKINLCUDE### */

$errorMessages = array(
    'NOTICE_SECURITY_CODE'      => Translation::readTranslation("general", "newsletter.notice.security.code", null, array(), 'Sollten Sie Ihren Security Code verloren haben, bestellen Sie den Newsletter ab und bestellen ihn erneut; sie erhalten dann einen neuen Security-Code per E-Mail.'),
    'ERROR_NOT_FOUND'           => Translation::readTranslation("general", "newsletter.notice.not.found", null, array(), 'Die Newsletter-Bestellung konnte nicht gefunden werden. M&ouml;glicherweise haben Sie mit der Best&auml;tigung zu lang gewartet.'),
    'ERROR_SECURITY_CODE'       => Translation::readTranslation("general", "newsletter.notice.security.code.invalid", null, array(), 'Sicherheitscode ung&uuml;tig.'),
    'ERROR_ALREADY_CONFIRMED'   => Translation::readTranslation("general", "newsletter.notice.already.confirmed", null, array(), 'Dies Newsletter-Abonnement ist bereits best&auml;tigt.'),
    'ERROR_DB_GENERAL'          => Translation::readTranslation("general", "newsletter.notice.database.error", null, array(), 'Datenbankfehler bei der Best&auml;tigung.'),
    'ERROR_DB_REMOVE'           => Translation::readTranslation("general", "newsletter.notice.security.code.invalid", null, array("CODE" => $lastresult['int_result']), 'Datenbankfehler #{CODE} beim L&ouml;schen der Adresse.'),
    'ERROR_DB_ADD'              => Translation::readTranslation("general", "newsletter.add.security.code.invalid", null, array("CODE" => $lastresult['int_result']), 'Datenbankfehler #{CODE} beim Eintragen der Adresse.'),
    'ERROR_CONFIRM_CODE'        => Translation::readTranslation("general", "newsletter.notice.security.code.missing", null, array(), 'Zur Best&auml;tigung des Newsletter-Abonnements ben&ouml;tigen Sie den Sicherheitscode.'),
    'ERROR_MISSING_EMAIL'       => Translation::readTranslation("general", "newsletter.notice.email.missing", null, array(), 'Bitte geben Sie Ihre E-Mail-Adresse an.'),
    'ERROR_INVALID_EMAIL'       => Translation::readTranslation("general", "newsletter.notice.email.invalid", null, array(), 'Bitte geben Sie eine g&uuml;ltige E-Mail-Adresse an.'),
    'ERROR_SIGNUP_NOT_FOUND'    => Translation::readTranslation("general", "newsletter.notice.not.signed.up", null, array(), 'Der Eintrag konnte nicht gefunden werden.'),
    'ERROR_SIGNUP_EXISTS'       => Translation::readTranslation("general", "newsletter.notice.already.signed.up", null, array(), 'Diese Adresse befindet sich bereits in der Datenbank.')
);

$tpl_modul->addvar("s_lang", $s_lang);
$s_forgotcode = '<br /><br />'.$errorMessages['NOTICE_SECURITY_CODE'];
$err = array();
if ((($id = $ar_params[1]) || ($id = $_REQUEST['ID_NL_RECP'])) && preg_match('/^[0-9]+$/', $id) && $id>0) {
#echo dump($id), dump(ereg('^[0-9]+$', $id)), '<br />';
if (($s_code = $ar_params[2]) || ($s_code = trim($_REQUEST['CODE']))) {
    $s_mode = 'confirm';
    if (!($data = $db->fetch1("select * from nl_recp where ID_NL_RECP=". $id))) {
        $err[] = $errorMessages['ERROR_NOT_FOUND'];
    } elseif (strcmp($data['CODE'], $s_code)) {
        $err[] = $errorMessages['ERROR_SECURITY_CODE'].$s_forgotcode;
    } elseif (is_null($data['STAMP'])) {
        $err[] = $errorMessages['ERROR_ALREADY_CONFIRMED'];
    } else {
        $result = $db->querynow("update nl_recp set CODE=NULL, STAMP=NULL where ID_NL_RECP=". $id);
        //echo ht(dump($lastresult));
        if ($result['rsrc']) {
            //'xxx todo body mit best�tigungskram');
        } else {
            $err[] = $errorMessages['ERROR_DB_GENERAL'];
        }
    }
  } else {
      $err[] = $errorMessages['ERROR_CONFIRM_CODE'].$s_forgotcode;
  }
  if ($data)
    $tpl_modul->addvars($data);
} else {
  if ('optout'==$_REQUEST['mode'] || 'opt-out'==$_REQUEST['mode'] || 'rm'==$_REQUEST['do']) {
    $s_mode = 'remove';
    if (!($s_email = trim($_REQUEST['EMAIL'])) && (!($s_email = validate_email(trim($ar_params[1]))))) {
        $err[] = $errorMessages['ERROR_MISSING_EMAIL'];
    } else {
      $result = $db->querynow("delete from nl_recp where EMAIL='". $s_email."'");
      if ($result['rsrc']) {
          if ($result['int_result'])
          {
              // Get info if confirm-mails should be sent
              $IDsendmail = $db->fetch_atom("select ID_MODULOPTION from `moduloption` where OPTION_VALUE='NEWSLETTER_CONFIRM'");
              $sendmail = $db->fetch_atom($db->lang_select("string_opt","V1")." where S_TABLE='moduloption' AND FK=".$IDsendmail);
              if($sendmail) // If confirm-mails should be sent, do so now
              {
                  $parsevalues['SITENAME']=$nar_systemsettings['SITE']['SITENAME'];
                  sendMailTemplateToUser(0, $s_email, "NL_OUT", $parsevalues);
              }
          } else {
              $err[] = $errorMessages['ERROR_SIGNUP_NOT_FOUND'];
          }
      } else {
          $err[] = $errorMessages['ERROR_DB_REMOVE'];
      }
    }
  } else {
    $s_mode = 'order';
    if (!($s_email = trim($_REQUEST['EMAIL'])) && (!($s_email = trim($ar_params[1])))) {
        $err[] = $errorMessages['ERROR_MISSING_EMAIL'];
    } elseif (!validate_email($s_email)) {
        $err[] = $errorMessages['ERROR_INVALID_EMAIL'];
    } else {
      $result = $db->querynow("insert into nl_recp (EMAIL, LANGVAL, STAMP, CODE)
        values ('". mysql_escape_string($s_email). "', ". $langval. ", date_add(now(), interval "
        . $nar_systemsettings['SITE']['NL_CONFIRM_TIMEOUT']. "), '"
        . mysql_escape_string($s_code = createpass($s_email)). "')");
 # echo ht(dump($result));
	  if ($result['rsrc']) {
        if ($result['int_result'])
		{
		  # Array mit in das Template zu parsenden Werten
		  $parsevalues['SITENAME']=$nar_systemsettings['SITE']['SITENAME'];
		  $parsevalues['SITEURL']=$nar_systemsettings['SITE']['SITEURL'];
		  $parsevalues['PAGE']=$GLOBALS['s_page'];
		  $parsevalues['INT_RESULT']=$result['int_result'];
		  $parsevalues['CODE']=$s_code;
		  # Array ende

          $s_email_name = "Unknown";
          if (preg_match("/^(.+)\@(.+)$/", $s_email, $arMatches)) {
            $s_email_name = $arMatches[1];
          }
          sendMailTemplateToUser(0, $s_email_name." <".$s_email.">", "NL_IN", $parsevalues);
		}
        else {
            $err[] = $errorMessages['ERROR_SIGNUP_NOT_FOUND'];
        }
      } elseif (SQL_ERR_DUP_ENTRY==$result['int_result']) {
          $notice = ($db->fetch_atom("select STAMP from nl_recp where EMAIL='". $s_email."'") ? $s_forgotcode : '');
          $err[] = $errorMessages['ERROR_SIGNUP_EXISTS'];
      } else {
          $err[] = $errorMessages[''];
      }
    }
  }
  $tpl_modul->addvar('EMAIL', $s_email);
}
$tpl_modul->addvar('mode_'. $s_mode, true);
$tpl_modul->addvars($_REQUEST);
if (count($err)) $tpl_modul->addvar('err', implode('<br />', $err));
#echo ht(dump($nar_systemsettings));
?>