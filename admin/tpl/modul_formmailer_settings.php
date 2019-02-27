<?php
/* ###VERSIONSBLOCKINLCUDE### */



 // id des Moduls holen
 $id_formmailer = $db->fetch_atom("select ID_MODUL from modul where IDENT = 'formmailer'");

 // E-Mail einlesen
 $email = $db->fetch1($db->lang_select("moduloption") . " where FK_MODUL=".$id_formmailer."
 AND OPTION_VALUE='EMAIL'");

 // Derzeitige E-Mail Adresse ausgeben
 $tpl_content->addvar('email', $email['V1']);

 // Überprüfen ob eine E-Mail angegeben wurde und syntaktisch korrekt ist (falls ja: fortfahren)
 if( validate_email($_POST['email']) && $_POST['email'] != "" ) {

  // Array für Datenbank erstellen
  $ar_email = array(
   "FK_MODUL" => $id_formmailer, // Modul id
   "V1" => $_POST['email'], // in Formular eingegebene E-Mailadresse
   "OPTION" => "EMAIL", // Einstellungen für "EMAIL"
   "ID_MODULOPTION" => $email['ID_MODULOPTION'] // id dieser Option
  );
  // E-Mail eintragen
  $db->update("moduloption", $ar_email);

  $db->querynow ("update modul set B_VIS=".$_POST['B_VIS']." where IDENT='formmailer'");
    die(forward('index.php?page=modul_formmailer_settings'));
 }

 elseif( count($_POST) ) {

  // Falls syntaktisch falsche E-Mail: So nicht!
  $tpl_content->addvar('error', "E-Mail Adresse nicht korrekt!");
  // Und das Eingabefeld muss auch wieder her...
  $tpl_content->addvar('email', $email['V1']);
 }

$B_VIS = $db->fetch_atom("select B_VIS from modul where IDENT='formmailer'");
$tpl_content->addvar('B_VIS',$B_VIS);

 // Neue E-Mail einlesen...
 $email = $db->fetch1($db->lang_select("moduloption") . " where FK_MODUL=".$id_formmailer."
 AND OPTION_value='EMAIL'");

 // ...und auch wieder ausgeben (nach einer Änderung)
 $tpl_content->addvar('email', $email['V1']);

?>