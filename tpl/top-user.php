<?php
/* ###VERSIONSBLOCKINLCUDE### */


  global $s_lang;
  
  function threads_date1(&$row, $i) {

    if ($row["STAMP_REG"] == date("Y-m-d", strtotime("today"))) {
      $row["datum_heute"] = 1;
    } else if ($row["STAMP_REG"] == date("Y-m-d", strtotime("yesterday"))) {
      $row["datum_gestern"] = 1;
    } else {
      $row["datum"] = $postdate;
    }
  }
  
  $liste_top = $db->fetch_table("SELECT NAME, STAMP_REG, ID_USER FROM user WHERE STAT = 1 ORDER BY STAMP_REG DESC LIMIT 0,10");
  $tpl_top->addlist("liste", $liste_top, "tpl/".$s_lang."/top-user.row.htm", "threads_date1");
?>