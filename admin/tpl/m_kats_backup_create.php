<?php
/* ###VERSIONSBLOCKINLCUDE### */


  include "sys/lib.shop_kategorien.php";
  
  // $kat_nested = new kat("kat", 1, false, $db);
  $kat = new TreeCategories("kat", 1);
  
  if (!empty($_POST)) {
    if ($kat->tree_backup_create($_POST["DESCRIPTION"], $user["NAME"])) {
      //$tpl_content->addvar("backup_ok", 1);
      die(forward("index.php?page=m_kats_backup_restore"));
    }
  }
?>