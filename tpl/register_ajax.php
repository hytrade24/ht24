<?php
/* ###VERSIONSBLOCKINLCUDE### */


  $result = array("used" => false);

  if ($_REQUEST["check"] == "NAME") {
    $used = $db->fetch_atom("SELECT count(*) FROM `user` WHERE NAME='".mysql_real_escape_string($_REQUEST["value"])."' AND
                    (IS_VIRTUAL=0 OR EMAIL!='" . mysql_real_escape_string($_REQUEST['EMAIL']) . "')");
    if ($used > 0)
      $result["used"] = true;
  }
  if ($_REQUEST["check"] == "EMAIL") {
    $used = $db->fetch_atom("SELECT count(*) FROM `user` WHERE EMAIL='".mysql_real_escape_string($_REQUEST["value"])."' AND IS_VIRTUAL=0");
    if ($used > 0)
      $result["used"] = true;
  }
  
  header('Content-type: application/json');
  die(json_encode($result));
?>