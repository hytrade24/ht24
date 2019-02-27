<?php
/* ###VERSIONSBLOCKINLCUDE### */


  include "sys/lib.shop_kategorien.php";

  function get_error() {
    global $kat;
    $errors = get_messages("KATEGORIEN");
    return ($errors[$kat->error] ? $errors[$kat->error] : $kat->error);
  }

  header('Content-type: application/json');

  $id_cat = ($_REQUEST["ID_CAT"] ? $_REQUEST["ID_CAT"] : -1);
  $id_target = ($_REQUEST["ID_TARGET"] ? $_REQUEST["ID_TARGET"] : -1);
  $inherit_childs = ($_REQUEST["LEAVE_CHILDS"] ? false : true);
  $move_target = ($_REQUEST["MOVE_TO"] ? $_REQUEST["MOVE_TO"] : "into");

  $kat = new TreeCategories("kat", 1);
  if (!$kat->tree_lock_valid()) {
    die(json_encode(array("state" => "450", "error" => get_error(), "reload" => $kat->reload)));
  }
  if ($id_target > -1) {
  	if ($move_target == "into") {
	    // In Kategorie verschieben
	    if (!$kat->element_move_into($id_cat, $id_target, $inherit_childs) || !$kat->tree_create_nestedset()) {
	      die(json_encode(array("state" => "450", "error" => get_error(), "reload" => $kat->reload)));
	    } else {
	      // Erfolg - Cache neu schreiben
	      include "../sys/lib.pub_kategorien.php";
	      CategoriesBase::deleteCache();
	    }
  	} else {
	    // Vor Kategorie verschieben
	    if (!$kat->element_move_before($id_cat, $id_target, $inherit_childs) || !$kat->tree_create_nestedset()) {
	      die(json_encode(array("state" => "450", "error" => get_error(), "reload" => $kat->reload)));
	    } else {
	      // Erfolg - Cache neu schreiben
	      include "../sys/lib.pub_kategorien.php";
	      CategoriesBase::deleteCache();
	    }
  	}
  }
  if (!$kat->tree_unlock()) {
    die(json_encode(array("state" => "450", "error" => get_error(), "reload" => $kat->reload)));
  }
  die(json_encode(array("state" => "200", "reload" => $kat->reload)));
?>
