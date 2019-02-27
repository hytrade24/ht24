<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path. 'sys/lib.cache.adapter.php';
$cacheAdapter = new CacheAdapter();
  
  function man_active(&$row, $i) {
    global $fk_man;
    if ($fk_man == $row["ID_MAN"]) {
      $row["ACTIVE"] = 1;
    }
  }

  $fk_man = $_REQUEST["FK_MAN"];
  
  if (empty($_POST)) {
    if (isset($_REQUEST["ID_PRODUCT"])) {
      $tpl_content->addvar("ID_PRODUCT", $_REQUEST["ID_PRODUCT"]);
      $products = $db->fetch1("SELECT p.*, s.T1, s.V1, s.V2 FROM `product` p 
        LEFT JOIN `string_product` s ON s.S_TABLE='product' AND s.FK=p.ID_PRODUCT 
          AND s.BF_LANG=if(p.BF_LANG_PRODUCT & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_PRODUCT+0.5)/log(2)))
        WHERE p.ID_PRODUCT=".(int)$_REQUEST["ID_PRODUCT"]);
      $fk_man = $products["FK_MAN"];
      $tpl_content->addvars($products);
    }
  } else {
    $tpl_content->addvars($_POST);
    
    $errors = array();
  
    if (strlen($_POST["V1"]) < 1) {
      $errors[] = "Sie müssen einen Namen angeben!";
    }
    if (isset($_POST["PRICE"])) $_POST["PRICE"] = str_replace(",", ".", $_POST["PRICE"]);
    if (isset($_POST["PRICE_MAN"])) $_POST["PRICE_MAN"] = str_replace(",", ".", $_POST["PRICE_MAN"]);
    
    if (empty($errors)) {
      if (empty($_POST['ID_PRODUCT'])) {
          $_POST['CONFIRMED'] = 1;
      }
      // die(ht(dump($_POST)));
      $id = $db->update('product', $_POST);
		$cacheAdapter->_cacheManufacturesSearchbox();
      $id = ($id ? $id : $_POST["ID_PRODUCT"]);
      if ($id) {
        die(forward("index.php?page=article_db&id=".$id));
      }             
    } else {
      $tpl_content->addvar("errors", " - ".implode("<br> - ", $errors));
    }
  }
  
  $manufacturers = $db->fetch_table("SELECT * FROM manufacturers");
  $tpl_content->addlist("liste", $manufacturers, "tpl/de/article_db_edit.row.htm", "man_active");
?>