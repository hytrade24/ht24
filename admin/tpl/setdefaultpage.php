<?php
/* ###VERSIONSBLOCKINLCUDE### */


 
  $ID_USER = $_GET['ID_USER'];
  $CUR = $_GET['cure'];
  $ar = $db->querynow("update user set DEFAULTPAGE='$CUR' where ID_USER='$ID_USER'");
 
?>