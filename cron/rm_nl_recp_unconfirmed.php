<?php
/* ###VERSIONSBLOCKINLCUDE### */


  require_once '_prepare.php';
  $res = $db->querynow("delete from nl_recp where FK_USER<1 and (STAMP is not null and STAMP<now())");
?>