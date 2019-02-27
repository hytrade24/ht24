<?php
/* ###VERSIONSBLOCKINLCUDE### */


	include_once "sys/lib.baum.php";
	$baum = new baum("job",$_REQUEST['ID_TREE_JOB']);
	
	
		
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

	$tpl_content->addvars($db->fetch1($db->lang_select('tree_job')." where ID_TREE_JOB=".$_REQUEST['ID_TREE_JOB']));
	#echo ht(dump($baum));
?>
