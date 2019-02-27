<?php
/* ###VERSIONSBLOCKINLCUDE### */



 ### Config File für die Kommunikation mit dem vBulletin
 
 $ar_vboptions = array
 (
   'cookie_pref' => 'bb',
   'cookie_salt' => 'VBF89220CA',
   'table_pref' => 'vb_',
   'user_title' => 'Benutzer',
   'salt' => '~p2',
   'group_locked' => 8,
   'group_confirm' => 3,
   'group_user' => 2
 ); 
 
 function pwd($str, $salt)
 {
   //$str = md5($str);
   $str = md5(md5($str).$salt);
   return $str;
 } // password()

?>