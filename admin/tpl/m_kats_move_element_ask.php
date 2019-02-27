<?php
/* ###VERSIONSBLOCKINLCUDE### */


  include "sys/lib.shop_kategorien.php";

  $id_cat = ($_REQUEST["ID_CAT"] ? $_REQUEST["ID_CAT"] : -1);
  $id_target = ($_REQUEST["ID_TARGET"] ? $_REQUEST["ID_TARGET"] : -1);

  $kat = new TreeCategories("kat", 1);

  $source = $kat->element_read($id_cat);
  $target = $kat->element_read($id_target);

  $tpl_content->addvar("move_".$_REQUEST["MOVE_TO"], 1);
  $tpl_content->addvar("KAT", $source["V1"]);
  $tpl_content->addvar("KAT_TARGET", $target["V1"]);
  $tpl_content->addvars($_REQUEST);
?>