<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(isset($_GET['do']))
 {
  switch($_GET['do'])
  {
   case "frei": $db->update("anzeige", array ("ID_ANZEIGE" => $_GET['id'], "BF_VIS" => 3)); 
   break;
  }
 }

 $tpl_content->addlist("liste", 
   $db->fetch_table($db->lang_select("anzeige","*,NAME as USERNAME")." 
    left join user u on FK_USER=ID_USER 
	where BF_VIS=2"),"tpl/de/anzeigen_freischalten.row.htm");

?>