<?php

if (!empty($_POST)) {
  
  $errors = [];
  $arBillableItem = $_POST;
  unset($arBillableItem["lang"]);
  unset($arBillableItem["page"]);
  
  if (!array_key_exists("FK_USER", $arBillableItem) || !(int)$arBillableItem["FK_USER"]) {
    if (array_key_exists("NAME_", $arBillableItem) && !empty($arBillableItem["NAME_"])) {
      // Fallback to username
      $arBillableItem["FK_USER"] = (int)$db->fetch_atom("SELECT ID_USER FROM `user` WHERE NAME='".mysql_real_escape_string($arBillableItem["NAME_"])."'");
    }
  }
  if (!array_key_exists("FK_USER", $arBillableItem) || !(int)$arBillableItem["FK_USER"]) {
    // No user selected
    $errors[] = "Kein/Ungültiger Benutzer ausgewählt!";
  }
  if (!array_key_exists("STAMP_CREATE", $arBillableItem) || !preg_match("/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})$/", $arBillableItem["STAMP_CREATE"], $arMatchDate)) {
    // No user selected
    $errors[] = "Kein/Ungültiges Datum ausgewählt!";
  } else {
    $arBillableItem["STAMP_CREATE"] = $arMatchDate[3]."-".$arMatchDate[2]."-".$arMatchDate[1];
  }
  if (!array_key_exists("DESCRIPTION", $arBillableItem) || empty($arBillableItem["DESCRIPTION"])) {
    // No user selected
    $errors[] = "Kein Beschreibung vorhanden!";
  }
  if (!array_key_exists("QUANTITY", $arBillableItem) || ((int)$arBillableItem["QUANTITY"] <= 0)) {
    // No user selected
    $errors[] = "Die Menge muss mindestens 1 betragen!";
  }
  if (!array_key_exists("PRICE", $arBillableItem) || ((int)$arBillableItem["PRICE"] <= 0.01)) {
    // No user selected
    $errors[] = "Die Preis muss mindestens 0.01 betragen!";
  }
  if (!array_key_exists("FK_TAX", $arBillableItem) || ((int)$arBillableItem["FK_TAX"] <= 0)) {
    // No user selected
    $errors[] = "Bitte MwSt.-Satz auswählen!";
  }
  
  if (empty($errors)) {
    // Data okay!
    $idBillableItem = $db->update("billing_billableitem", $arBillableItem);
    if ($idBillableItem > 0) {
      die(forward("index.php?page=billing_billableitem&done=add"));
    } else {
      $errors[] = "Datenbankfehler beim hinzufügen der Rechnungsposition";
    }
  }
  
  $tpl_content->addvar("errors", implode("\n<br />\n", $errors));
  $tpl_content->addvars($arBillableItem);
}

$taxList = $db->fetch_table("SELECT * FROM `tax`");
$taxListJson = array();
foreach ($taxList as $taxItem) {
  $taxListJson[ $taxItem["ID_TAX"] ] = $taxItem["TAX_VALUE"];
}
$tpl_content->addlist("liste_mwst", $taxList, "tpl/".$s_lang."/billing_billableitem_add.row_mwst.htm");
$tpl_content->addvar("jsonTax", json_encode($taxListJson));