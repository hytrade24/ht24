<?php
/* ###VERSIONSBLOCKINLCUDE### */


	include_once "sys/lib.baum.php";
	$baum = new baum('tutorial', $_REQUEST['ID_TREE_TUTORIAL']);
	
	if(count($_POST))
	{
		if(empty($_POST['V1']))
			$tpl_content->addvar("err", "Bitte einen Namen angeben.");
		else
		{
    	$baum->updateNode($_POST);
			$tpl_content->addvar("rel", 1);
		}
	}

	$tpl_content->addvars($baum->active_node);
?>
