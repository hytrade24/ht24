<?php
/* ###VERSIONSBLOCKINLCUDE### */



if(count($_POST)) {
	if(empty($_POST['NAME'])) {
		$err[] = "NONAME";
	}
	if(!secure_question($_POST)) {
		$err[] = "secQuestion";
	}
	if(!validate_email($_POST['EMAIL'])) {
		$err[] = "WRONGMAIL";
	}
	if(empty($_POST['BETREFF'])) {
		$_POST['BETREFF'] = "Kein Betreff";
	}
	if(strlen($_POST['TEXT']) < 5) {
		$err[] = "Nachrichtentext";
	}

	if(count($err)) {
		$tpl_content->addvars($_POST);
		#echo ht(dump($err));
		$err = get_messages("kontakt", implode(",", $err));
	 	$tpl_content->addvar("err", implode("<br />", $err));
	 	$tpl_modul->addvar("err", " - ".implode ("<br /> - ", $err));
	} else {
        /*
		$message ="Name: ".$_POST['NAME']."
Telefon: ".$_POST['TELEFON']."
Email: ".$_POST['EMAIL']."
Nachricht:
".$_POST['TEXT'];
        */
		$empfaenger = GetModuleValue("formmailer","EMAIL");
        sendMailTemplateToUser($_POST['NAME']." <".$_POST['EMAIL'].">", $empfaenger, "CONTACT_SITE", $_POST);
		#die('empfaenger: '.$empfaenger);
	 /*kmail($_POST['NAME']." <".$_POST['EMAIL'].">", $empfaenger, $_POST['BETREFF'],
	 "Neuer Kontakt ueber ".$nar_systemsettings['SITE']['SITENAME']."\r\n
".$message,false);
	 */
	 $tpl_modul->addvar("send", 1);
	}
}
?>
