<?php
/* ###VERSIONSBLOCKINLCUDE### */

 
require_once 'sys/lib.db.mysql.php';  // DB Wrapper class db
 include "inc.server.php";
 $db = new ebiz_db($db_name, $db_host, $db_user, $db_pass);
 unset($db_user); unset($db_pass);
$db->querynow("update partnerlinks set HITSOUT = HITSOUT +1 where ID_PARTNERLINKS=".$_GET['id']);
Header(  "Location: ".$_GET['url']);
?>