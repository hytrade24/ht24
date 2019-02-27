<?php
/* ###VERSIONSBLOCKINLCUDE### */


	include "sys/lib.baum.php";
	$baum = new baum("job", $_REQUEST['ID_TREE_JOB']);
	
	$tpl_content->addvars($baum->active_node);

 if(count($_POST))
 {
   if($_POST['DEL'] == 'THIS')
   {
	 	$baum->delNode($_REQUEST['ID_TREE_JOB']);
	 	$tpl_content->addvar("DELETED", 1);
   } // nur eine löschen
   else
   {
	 	$baum->delNode($_REQUEST['ID_TREE_JOB'], 1);
    $tpl_content->addvar("DELETED", 1);
   } // alle löschen
 }

?>
