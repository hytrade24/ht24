<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(isset($_REQUEST['delete']))
 {
  $db->delete("nl", $_REQUEST['delete']); 
  $db->querynow("delete from nl_log where FK_NL=".$_REQUEST['delete']); 
 }

 $tpl_content->addlist('liste', $db->fetch_table("select * from nl_log 
 left join nl on FK_NL=ID_NL
 left join string_mail on S_TABLE='nl' and FK=ID_NL
 order by DONE"), 
    'tpl/de/modul_newsletter_sendeberichte.row.htm'); 

?>
