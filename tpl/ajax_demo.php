<?php
/* ###VERSIONSBLOCKINLCUDE### */



 ### basis include 
 // session, class etc.
 include $ab_path."sys/ajax/config.ajax.php";
 
 $ar_user = array();
 
 $err = $err_fields = $rewrite_fields = array();
 if(empty($ar_user))
 {
   $err[] = "USER_NOT_FOUND";
   $err_fields[] = 'USERNAME';
 } // gar nicht erst gefunden
 else
 {
   $exp = time()+(60*60*24*365*3);
   setcookie('USERNAME', $ar_user['USERNAME'], $exp, '/');
   if(!$_REQUEST['PASS'])
   {
     $err[] = 'NO_PASS';
	 $err_fields[] = 'PASS';
   } // kein Passwort
   else
   {

   } // Passwort gesetzt
 } // user wurde gefunden
 
 if(!empty($err))
 {
   #$err = $control->handleMsg($err);
   
   $GLOBALS['_RESULT']['msg']['err'] = $err;
   
   for($i=0; $i<count($err_fields); $i++)
     $GLOBALS['_RESULT']['formfields'][$err_fields[$i]]['err'] = 1;
 
   for($i=0; $i<count($rewrite_fields); $i++)
     $GLOBALS['_RESULT']['formfields'][$err_fields[$i]]['wert'] = 1;  
 } // wenn Fehler
 else
 {
   unset($ar_user['PASS']);
   unset($ar_user['KEY']);
   $GLOBALS['_RESULT']['msg']['ok'][] = 'BLA';    
 } // LOGIN ERfolgreich
 ### direkte ausgaben landen in debug, und können verarbeitet werden! 
 print_r($err);
?>
