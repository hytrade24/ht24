<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(isset($_REQUEST['do']) && $_REQUEST['do']=="rm")
   $db->delete("nl_group", (int)$_REQUEST['id']);

 $tpl_content->addlist("liste", $db->fetch_table("select * from nl_group 
   order by LABEL"), "tpl/de/versand_listen.row.htm");

?>
