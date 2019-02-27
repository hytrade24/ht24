<?php
/* ###VERSIONSBLOCKINLCUDE### */


# BUG auf Z. 62: # Variable wird in Template nicht erkannt und kein Fehler generiert... Egal was ich mache???


# IDs für moduloption holen:
 $ID_MODULOPTION_NLC = $db->fetch_atom("select ID_MODULOPTION from `moduloption` where OPTION_VALUE='NEWSLETTER_CONFIRM'");
 $ID_MODULOPTION_NLT = $db->fetch_atom("select ID_MODULOPTION from `moduloption` where OPTION_VALUE='NEWSLETTER_TYPE'");
 $ID_MODULOPTION_NLE = $db->fetch_atom("select ID_MODULOPTION from `moduloption` where OPTION_VALUE='NEWSLETTER_EMAIL'");


if (count($_POST)) {

	$db->querynow ("update modul set B_VIS=".$_POST['B_VIS']." where IDENT='newsletter'");
   
   // **VERSENDEN?** \\
   // Array für Datenbank erstellen
   // Als SYS_NAME wird für NEWSLETTER_CONFIRM der Wert "confirm" in V1 gesetzt, falls Admin überhaupt Mails versenden möchte.
   // Sollen keine bestätigungs E-Mails versendet werden, bleibt V1 für NEWSLETTER_CONFIRM leer.
	$ar_confirm = array(
				"ID_MODULOPTION" 	=> $ID_MODULOPTION_NLC,
				"OPTION_VALUE"		=> "NEWSLETTER_CONFIRM",
				"V1"				=> $_POST['CONFIRM'] // checkbox aus Formular, ob versenden oder nicht
				);
   // Checkbox übermitteln
   $db->update("moduloption", $ar_confirm);
   
   
   // **VERSANDART** \\
   // Array für Datenbank erstellen
   // Als SYS_NAME wird für NEWSLETTER_TYPE der Wert "html" in V1 gesetzt, falls Admin HTML-E-Mails versenden möchte.
   // Soll normaler Text versendet werden, bleibt V1 für NEWSLETTER_TYPE leer.
    $ar_type = array(
	"ID_MODULOPTION" => $ID_MODULOPTION_NLT, // id dieser Option
	"OPTION_VALUE" => "NEWSLETTER_TYPE", // Einstellungen für "NEWSLETTER_TYPE"
	"V1" => $_POST['TYPE'] // checkbox aus Formular, ob HTML
   );
   // Checkbox übermitteln
   $db->update("moduloption", $ar_type);
   
   
   // **EMAIL** \\
   if(validate_email($_POST['EMAIL'])) // Wenn angegebene E-Mail syntaktisch korrekt ist...
   {
     // Array für Datenbank erstellen
     $ar_email = array(
      "ID_MODULOPTION" => $ID_MODULOPTION_NLE, // id dieser Option
	  "OPTION_VALUE" => "NEWSLETTER_EMAIL", // Einstellungen für "NEWSLETTER_EMAIL"
	  "V1" => $_POST['EMAIL'], // in Formular eingegebene E-Mailadresse
     );
     // E-Mail eintragen
     $a = $db->update("moduloption", $ar_email);
   }
   else
     $tpl_content->addvar('emailerror', 1); // Wenn nicht korrekt, Fehler generieren
	 # Variable wird in Template nicht erkannt und kein Fehler generiert... Egal was ich mache???
 
   
   
 /* // **AN MELDUNG** \\
   // Array für Datenbank erstellen
   $ar_optin = array(
    "T1" => $_POST['OPTIN'], // in Formular eingegebener Text
    "SYS_NAME" => "NEWSLETTER_OPTIN", // Einstellungen für "NEWSLETTER_OPTIN"
    "ID_MAILVORLAGE" => $_POST['ID_MAILVORLAGE_OPTIN'] // id dieser Option
   );
   // Anmelde Text eintragen
   $db->update("mailvorlage", $ar_optin);
   
   
   // **AB MELDUNG** \\
   // Array für Datenbank erstellen
   $ar_optout = array(
    "T1" => $_POST['OPTOUT'], // in Formular eingegebener Text
    "SYS_NAME" => "NEWSLETTER_OPTOUT", // Einstellungen für "NEWSLETTER_OPTOUT"
    "ID_MAILVORLAGE" => $_POST['ID_MAILVORLAGE_OPTOUT'] // id dieser Option
   );
   // Abmelde Text eintragen
   $db->update("mailvorlage", $ar_optout); */

   forward('index.php?page=modul_newsletter_settings', 2);
   
}

 // Checkbox einlesen...
 $confirm = $db->fetch_atom("select s.V1 from `moduloption` t left join string_opt s on s.S_TABLE='moduloption' and s.FK=t.ID_MODULOPTION and s.BF_LANG=if(t.BF_LANG_OPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2))) where S_TABLE='moduloption' AND s.FK='".$ID_MODULOPTION_NLC."'");

 
 // Checkbox einlesen...
 $type = $db->fetch_atom("select s.V1 from `moduloption` t left join string_opt s on s.S_TABLE='moduloption' and s.FK=t.ID_MODULOPTION and s.BF_LANG=if(t.BF_LANG_OPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2))) where S_TABLE='moduloption' AND s.FK='".$ID_MODULOPTION_NLT."'");

 // Neue E-Mail einlesen...
 $email = $db->fetch_atom("select s.V1 from `moduloption` t left join string_opt s on s.S_TABLE='moduloption' and s.FK=t.ID_MODULOPTION and s.BF_LANG=if(t.BF_LANG_OPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2))) where S_TABLE='moduloption' AND s.FK='".$ID_MODULOPTION_NLE."'");

 // Und Inhalte auch wieder ausgeben
 if ($confirm == "confirm")
   $tpl_content->addvar('confirm_checked', "checked=\"checked\"");
 if ($type == "html")
   $tpl_content->addvar('type_checked', "checked=\"checked\"");

 $tpl_content->addvar('email', $email);

 $B_VIS = $db->fetch_atom("select B_VIS from modul where IDENT='newsletter'");
 $tpl_content->addvar('B_VIS',$B_VIS);

 ?>