<?php
/* ###VERSIONSBLOCKINLCUDE### */



include "sys/lib.shop_kategorien.php";

header('Content-type: application/json');

$kat = new TreeCategories("kat", 1);
if (!$kat->tree_lock()) {
  $errors = get_messages("KATEGORIEN");
  die(json_encode(array("state" => "450", "error" => $errors[$kat->error], "reload" => $kat->reload)));
  //implode("",get_messages("KATEGORIEN", $kat->error))
}

$ID_CAT = ($_REQUEST["ID_CAT"] ? $_REQUEST["ID_CAT"] : 0);

$SILENCE = false;
$targets = $kat->element_get_targets($ID_CAT);
$targets_sort = $kat->element_get_targets_sort($ID_CAT);
$childs = $kat->element_has_childs($ID_CAT);

if(!empty($targets) || !empty($targets_sort)) {
  die(json_encode(array("state" => "200", "list" => $targets, "list_before" => $targets_sort, "childs" => $childs, "reload" => $kat->reload)));
}
else
  die(json_encode(array("state" => "404", "reload" => $kat->reload)));
?>
