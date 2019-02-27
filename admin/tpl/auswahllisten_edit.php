<?php
/* ###VERSIONSBLOCKINLCUDE### */



  function encodeJSparameter(&$str){
    $str = html_entity_decode(stdHtmlentities($str, ENT_COMPAT , "UTF-8"));
  }

  $SILENCE = false;
  $ID_LISTE = ($_REQUEST["ID_LISTE"] ? $_REQUEST["ID_LISTE"] : 0);

  if(is_numeric($_POST["delete_value"]) && $_POST["delete_value"] > 0){
    $db->querynow("DELETE FROM `liste_values` WHERE ID_LISTE_VALUES=".$_POST["delete_value"]);
    $db->querynow("DELETE FROM `string_liste_values` WHERE FK=".$_POST["delete_value"]);
  }

  if($_POST["save_options"]){
    if (!empty($_POST["V1"])) {
      //encodeJSparameter($_POST["V1"]);
      #die(dump($_POST));
      if($db->update("liste_values", $_POST))
        $tpl_content->addvar("save_ok", 1);
      else
        $tpl_content->addvar("save_error", 1);
    } else
      $tpl_content->addvar("save_error", 1);
  }

  if($_POST["save_auswahlliste"]){
    $_POST["NAME"] = $_POST["LIST_NAME"]; unset($_POST["LIST_NAME"]);
    //encodeJSparameter($_POST["NAME"]);
    if(!$_POST["LIST_GLOBAL"])
      $_POST["LIST_GLOBAL"] = 0;

    $_POST["STAMP_UPDATE"] = date("Y-m-d");

    if(!$_POST["ID_LISTE"])
      $_POST["STAMP_CREATE"] = date("Y-m-d");
    if($ID_LISTE = $db->update("liste", $_POST)){
      $hack = explode("\n", $_POST["LISTE_VALUES"]);
      for($i=0;$i<count($hack); $i++){
        if (!empty($hack[$i])) {
          //encodeJSparameter($hack[$i]);
          $db->update("liste_values", array("ID_LISTE_VALUES" => 0,
                                            "FK_LISTE" => $ID_LISTE,
                                            "V1" => $hack[$i]));
        }
      }
      $tpl_content->addvar("save_ok", 1);
    }
    else
      $tpl_content->addvar("save_error", 1);
  }

  if(is_numeric($_REQUEST["edit"]) && $_REQUEST["edit"] > 0){
    $tpl_content->addvar("EDIT", $_REQUEST["edit"]);
  }


  if($ID_LISTE){
    $query = "select lv.ID_LISTE_VALUES, slv.V1, lv.ORDER
                from liste_values lv
                  left join string_liste_values slv on slv.FK = lv.ID_LISTE_VALUES AND slv.S_TABLE = 'liste_values' AND slv.BF_LANG = if(lv.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1<<floor(log(lv.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                where lv.FK_LISTE = ".$ID_LISTE . " order by lv.ORDER ASC";

    $aliste_query = "select NAME, ID_LISTE, FK_FIELD_DEF, LIST_GLOBAL
                      from liste where ID_LISTE = ".$ID_LISTE;

    $liste = $db->fetch1($aliste_query);
    $liste["LIST_NAME"] = $liste["NAME"]; unset($liste["NAME"]);
    //echo ht(dump($liste));
    $list_values = $db->fetch_table($query);
    $tpl_content->addlist("liste", $list_values, "tpl/de/auswahllisten_edit.row.htm");
    $tpl_content->addvars($liste);
  } else {
    $tpl_content->addvar("LIST_GLOBAL", 1);
  }
?>