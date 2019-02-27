<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path. 'sys/lib.cache.adapter.php';
$cacheAdapter = new CacheAdapter();

  global $bf_lang;
  
  function man_active(&$row, $i) {
    global $fk_man;
    if ($fk_man == $row["ID_MAN"]) {
      $row["ACTIVE"] = 1;
    }
  }

  if (isset($_REQUEST["DELETE"])) {
    $id = (int)$_REQUEST["DELETE"];
    $db->querynow("DELETE FROM product WHERE ID_PRODUCT=".$id);
	  $cacheAdapter->_cacheManufacturesSearchbox();

    die(forward("index.php?page=article_db"));  
  }
  if (isset($_REQUEST["confirm"])) {
  	$SILENCE=false;
  	$id = (int)$_REQUEST["confirm"];
  	$db->querynow("UPDATE `product` SET CONFIRMED=IF(CONFIRMED,0,1) WHERE ID_PRODUCT=".$id);
  	$confirmed = $db->fetch_atom("SELECT CONFIRMED FROM `product` WHERE ID_PRODUCT=".$id);
  	$tpl_content->LoadText("tpl/de/article_db.ajax.htm");
  	$tpl_content->addvar("ID_PRODUCT", $id);
  	$tpl_content->addvar("CONFIRMED", $confirmed);

	  $cacheAdapter->_cacheManufacturesSearchbox();


  	die($tpl_content->process(true));
  }
  
  $where = array();
  $searchparams = array();
  
  if ((int)$_REQUEST["ID_ORDER"] > 0) {
    $where[] = "p.ID_ORDER LIKE '%".(int)$_REQUEST["ID_ORDER"]."%'";
    $searchparams[] = "ID_ORDER=".urlencode($_REQUEST["ID_ORDER"]);
  }
  if ((int)$_REQUEST["FK_MAN"] > 0) {
    $fk_man = (int)$_REQUEST["FK_MAN"];
    $where[] = "p.FK_MAN=".(int)$_REQUEST["FK_MAN"];
    $searchparams[] = "FK_MAN=".urlencode($_REQUEST["FK_MAN"]);
  }
  if (strlen($_REQUEST["V1"]) > 0) {
    $where[] = "s.V1 LIKE '%".mysql_escape_string($_REQUEST["V1"])."%'";
    $searchparams[] = "V1=".urlencode($_REQUEST["V1"]);
  }
  if ((int)$_REQUEST["unconfirmed"] > 0) {
    $where[] = "p.CONFIRMED=0";
    $searchparams[] = "CONFIRMED=0";
    $tpl_content->addvar("unconfirmed", 1);
  } else {
    $where[] = "p.CONFIRMED=1";
    $searchparams[] = "CONFIRMED=1";
  }
  $searchparams = implode("&", $searchparams);
  
  if (!empty($_POST)) {
    $tpl_content->addvars($_POST);
  }
  
  $perpage = 30; // Elemente pro Seite
  $limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);
  
  $all = $db->fetch_atom("SELECT count(*)
  	FROM product p
	    LEFT JOIN `manufacturers` m ON m.ID_MAN = p.FK_MAN 
	    LEFT JOIN `string_product` s ON s.S_TABLE='product' AND s.FK=p.ID_PRODUCT
  	".(empty($where) ? "" : "WHERE ".implode(" AND ", $where)));
  // Seitenzähler hinzufügen
  $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=article_db".($searchparams ? "&".$searchparams : "")."&npage=", $perpage));
  
  $products = $db->fetch_table("SELECT p.*, m.NAME as MANUFACTURER, s.T1, s.V1, s.V2 FROM `product` p
    LEFT JOIN `manufacturers` m ON m.ID_MAN = p.FK_MAN 
    LEFT JOIN `string_product` s ON s.S_TABLE='product' AND s.FK=p.ID_PRODUCT 
      AND s.BF_LANG=if(p.BF_LANG_PRODUCT & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_PRODUCT+0.5)/log(2)))".
    (empty($where) ? "" : "WHERE ".implode(" AND ", $where)).
    " ORDER BY s.V1 LIMIT ".$limit.",".$perpage);
  $tpl_content->addlist("liste", $products, "tpl/de/article_db.row.htm");
	  
  $manufacturers = $db->fetch_table("SELECT * FROM manufacturers");
  $tpl_content->addlist("liste_man", $manufacturers, "tpl/de/article_db_edit.row.htm", "man_active");
?>