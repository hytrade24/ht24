<?php
/* ###VERSIONSBLOCKINLCUDE### */


	include_once "sys/lib.baum.php";
	$id = ($_REQUEST['ID'] ? $_REQUEST['ID'] : 0);
 
	$baum = new baum("job", $id);
	$a_node = new baum("job", $_REQUEST['ID_TREE_JOB']);
	
	if ($_REQUEST['d'] == 'copied')
		$tpl_content->addvar('rel', 1);
 
	if(count($_POST))
	{
  	$baum->copyNode($_REQUEST['ID_TREE_JOB'], $_REQUEST['ID']);
		forward('index.php?page=jobkat_copy&ID_TREE_JOB='.$_REQUEST['ID_TREE_JOB'].'&ID='.$_REQUEST['ID']."&d=copied&frame=popup");
	}
 
 	$tpl_content->addvars($a_node->active_node);
 
	if($id)
	{
		$baum->getAffected($id);
		$tpl_content->addvar("THIS", $baum->active_node['V1']);
		if ($parent = $baum->getParent())
			$tpl_content->addvars($parent, "P_");
		$tpl_content->addvar("show_root", 1);
		$tpl_content->addvar("ID", $id);
 	}
 
	$baum->read($id,0,1);
	$tpl_content->addlist("liste", $baum->ar_baum, "tpl/de/jobkat_copy.row.htm");
?>