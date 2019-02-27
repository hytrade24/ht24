<?php
/* ###VERSIONSBLOCKINLCUDE### */



 ### GeÃ¤ndert von Schmalle am 24.04.2006
 ### Neue Templates eingefÃ¼gt
 
 ### Basic File fÃ¼r Modul Login
 
 /* 
   Templates Array
   Jedes Template muss als eigenes Array bestehen aus
   -Name, Dateiname und Beschreibung
 */
 
 $ar_templates = array
 (
   array
   (
    "name" => "Loginformular", 
    "tpl"  => "login.htm",
	"dsc"  => "Formular für den Login. Inkl. Fehlerausgabe etc."
   ),
   array
   (
    "name" => "Login erfolgreich", 
    "tpl"  => "loggedin.htm",
	"dsc"  => "Willkommenseite, wenn der User bereits eingelogged ist."
   )     
 );

?>