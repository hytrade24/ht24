<?php
/* ###VERSIONSBLOCKINLCUDE### */


	include "sys/lib.baum.php";
	$baum = new baum('tutorial', $_REQUEST['ID_TREE_TUTORIAL']);
	
	$tpl_content->addvars($baum->active_node);

 if(count($_POST))
 {
   if($_POST['DEL'] == 'THIS')
   {
	 	$baum->delNode($_REQUEST['ID_TREE_TUTORIAL']);
	 	$tpl_content->addvar("DELETED", 1);
   } // nur eine löschen
   else
   {
	 	$baum->delNode($_REQUEST['ID_TREE_TUTORIAL'], 1);
    $tpl_content->addvar("DELETED", 1);
   } // alle löschen
 }

?>
