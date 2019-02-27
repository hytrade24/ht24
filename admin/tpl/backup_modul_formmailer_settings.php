<?php
/* ###VERSIONSBLOCKINLCUDE### */



// Vorspiel
$datei = "../module/formmailer/ini.php";
$settings = array();


$fp = fopen($datei,"r");
$settings = fread($fp, filesize($datei)); // Datei öffnen und settings einlesen
$fp = fclose($fp);

$settings = explode(";",$settings); // Wenn mehrere Angaben vorhanden sind, anhand von ; trennen
$tpl_content->addvar('email', $settings[0]); // Derzeitige E-Mail Adresse ausgeben

$email = $_POST["email"]; // leichter zu merken/schreiben

if( $email != "") {             // Wenn $email nicht leer ist,

  if(validate_email($email)) {  // überprüfe ob $email falsch ist,
    $settings[0] = $email;      // und weise es $settings zu
  }
  else {
    $tpl_content->addvar('error', "E-Mail Adresse falsch oder nicht angegeben!"); // Wenn falsch||nicht angegeben: So nicht!
  }

}

$fp = fopen($datei,"w");
fwrite($fp,$settings[0] . ";"); // Datei öffnen und neue Settings schreiben, anhand von ; trennen
$fp = fclose($fp);

// Nachspiel
$tpl_content->addvar('email', $settings[0]); // Neu gespeicherte E-Mail anzeigen

?>