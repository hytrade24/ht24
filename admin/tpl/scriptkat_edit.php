<?php
/* ###VERSIONSBLOCKINLCUDE### */


	include_once "sys/lib.baum.php";
	$baum = new baum('script',$_REQUEST['ID_TREE_SCRIPT']);
	
	
		
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

	$tpl_content->addvars($db->fetch1($db->lang_select('tree_script')." where ID_TREE_SCRIPT=".$_REQUEST['ID_TREE_SCRIPT']));
	#echo ht(dump($baum));
?>
