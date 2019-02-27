<?php
/* ###VERSIONSBLOCKINLCUDE### */


  if (!empty($_POST)) {
    include "sys/lib.shop_kategorien.php";
    $kat = new TreeCategories("kat", 1);

	if(!$_POST['skipBackup']) {
    	$kat->tree_backup_create("Reperaturversuch Nested-Set");
	}

    if (isset($_POST["gosort"])) {
    	// Alphabetisch sortieren
    	$kat->tree_create_nestedset("title");
    } else {
    	// Sortierung beibehalten
    	$kat->tree_create_nestedset();
    }

    die(forward("index.php?page=m_kat_repair&ok=1"));
  } else {
    if ($_GET["ok"]) {
      $tpl_content->addvar("ok", 1);
    }
  }
?>