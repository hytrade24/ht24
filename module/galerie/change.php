<?php
/* ###VERSIONSBLOCKINLCUDE### */



 ## Wir gebraucht, wenn die Modulzuordnung zu einer Seite ge�ndert oder gel�scht wird. 

 function change($ar)
 {
   global $db;
   $db->querynow("delete from modul2nav where FK_NAV=".$ar['ID_NAV']." and S_MODUL='galerie'");
   #die(ht(dump($GLOBALS['lastresult'])));
 }

?>
