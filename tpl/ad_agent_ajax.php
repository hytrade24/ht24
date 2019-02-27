<?php
/* ###VERSIONSBLOCKINLCUDE### */


  header('Content-type: application/json');
  
  $json_result = array("okay" => 0);
  
  if (isset($_REQUEST["suggest_man"])) {
    $list = $db->fetch_table("
    	SELECT
    		ID_MAN as ID, NAME
    	FROM
    		`manufacturers`
    	WHERE
    		NAME LIKE '".mysql_escape_string($_REQUEST["suggest_man"])."%'");
    $json_result["manufacturers"] = $list;
  }
  
  if (isset($_REQUEST["suggest_user"])) {
    $list = $db->fetch_table("
    	SELECT
    		ID_USER as ID, NAME
    	FROM
    		`user`
    	WHERE
    		NAME LIKE '".mysql_escape_string($_REQUEST["suggest_user"])."%'");
    $json_result["users"] = $list;
  }
  
  // ! !   W I C H T I G   ! !
  //   json_encode: "Diese Funktion arbeitet nur mit UTF-8-kodierten Daten."
  //   String(s) umwandeln in UTF-8!
  foreach ($json_result as $type => $content) {
    if (is_array($content))
      foreach ($content as $index => $entry) {
        if (!empty($content[$index]["NAME"]))
          $json_result[$type][$index]["NAME"] = $entry["NAME"];
        if (!empty($content[$index]["DESC_SHORT"]))
          $json_result[$type][$index]["DESC_SHORT"] = $entry["DESC_SHORT"];
      }
  }
  
  die(json_encode($json_result));
?>