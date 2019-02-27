<?php
/* ###VERSIONSBLOCKINLCUDE### */


  include "sys/lib.shop_kategorien.php";
  
  // $kat_nested = new kat("kat", 1, false, $db);
  
  $kat = new TreeCategories("kat", 1);
  
  if (isset($_REQUEST["DEL"])) {
    if ($kat->tree_backup_delete($_REQUEST["DEL"]))
      die(forward("index.php?page=m_kats_backup_restore"));
    else
      $tpl_content->addvar("error", 1);
  }
  
  $backups = $kat->tree_backup_list();
  
  $tpl_content->addlist("liste", $backups, "tpl/de/m_kats_backup_restore.row.htm");
?>