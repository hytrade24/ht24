<?php
/* ###VERSIONSBLOCKINLCUDE### */


  $SILENCE = false;

  if ($_REQUEST['edit'] > 0) {
      $tpl_content->addvar("ID_EDIT", $_REQUEST['edit']);
  }

  $tpl_content->addvar("search_global", -1);
  
  $conditions = array();
  if (!empty($_POST["search"])) {
    $search = $_POST["search"];
    if (!isset($search["remove"])) {
      if (isset($search["global"])) {
        // Suche nach globalen listen
        if ($search["global"] != "") {
          $conditions["LIST_GLOBAL"] = "=".(int)$search["global"];
          $tpl_content->addvar("search_global", (int)$search["global"]);
        }
      }
      if (isset($search["name"])) {
        // Suche nach namen
        $conditions["NAME"] = " LIKE '%".mysql_escape_string($search["name"])."%'";
        $tpl_content->addvar("search_name", $search["name"]);
      }
    }
  }
  
  if (isset($_REQUEST["do"])) {
    if ($_REQUEST["do"] == "delete") {
      $id_liste = (int)$_REQUEST["ID_LISTE"];
      $values = $db->fetch_nar("SELECT t.ID_LISTE_VALUES FROM liste_values t WHERE t.FK_LISTE=".$id_liste);
      $id_values = "(".implode(",", array_keys($values)).")";
      $db->querynow("DELETE FROM string_liste_values WHERE S_TABLE='liste_values' AND FK IN ".$id_values);
      $db->querynow("DELETE FROM liste_values WHERE FK_LISTE=".$id_liste);
      $db->querynow("DELETE FROM liste WHERE ID_LISTE=".$id_liste);
      die(forward("index.php?page=auswahllisten&delete_ok=1"));
    }    
  }
  
  $where = "";
  if (!empty($conditions)) {
    $ar_where = array();
    foreach ($conditions as $field => $condition) {
      $ar_where[] = "`".mysql_escape_string($field)."`".$condition;
    }
    $where = " WHERE ".implode(" AND ", $ar_where);
  }

  $perpage = 25; // Elemente pro Seite
  $limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);
  $all = $db->fetch_atom("select count(*) from liste ".$where);
  
  $query = "select ID_LISTE, LIST_GLOBAL, NAME, STAMP_CREATE, STAMP_UPDATE,
              (SELECT count(*) FROM liste_values WHERE FK_LISTE=ID_LISTE) as LIST_VALUE_COUNT
                from liste ".$where."
              order by STAMP_CREATE DESC limit ".$limit.",".$perpage;;
  
  $liste = $db->fetch_table($query);                    
  $tpl_content->addlist("liste", $liste, "tpl/de/auswahllisten.row.htm");
  // 10.10.2011 - Seitenzähler gefixt
  $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=auswahllisten&npage=", $perpage));
?>
