<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $tpl_content->addvars($_REQUEST); 
 $tpl_content->addvars($db->fetch1($db->lang_select("nl")." where ID_NL=".$_REQUEST['ID_NL']));

?>