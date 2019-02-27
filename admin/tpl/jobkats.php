<?php
/* ###VERSIONSBLOCKINLCUDE### */



if($_GET['V1'])
  $tpl_content->addvar("V1", $_REQUEST['V1']);

$SILENCE = false;
	include "sys/lib.baum.php";
 
	if($_REQUEST['do'])
	{
		$baum = new baum("job");
		switch($_REQUEST['do'])
		{
			case "hide":
				$baum->hideNode($_REQUEST['ID']);
				break;
			case "show":
				$baum->showNode($_REQUEST['ID']);
				break;	 
			case "insert":
				$err=array();
				if(empty($_POST['V1']))
				{
					$tpl_content->addvar("err", "Bitte einen Namen angeben!");
					$tpl_content->addvars($_POST);
				}
				else
					$baum->insert($_POST);
				break;
				
			case 'cache':
				$baum->cache_tree();
				break;
		}
		$baum = false;
	}
 
	if($id = $_REQUEST['ID_TREE_JOB'])
	{
		$baum = new baum("job", $id);
		$baum->getAffected($id);
		$tpl_content->addvar("THIS", $baum->active_node['V1']);
		if ($parent = $baum->getParent())
			$tpl_content->addvars($parent, "P_");
		$tpl_content->addvar("show_root", 1);
		$tpl_content->addvar("ID_TREE_JOB", $id);
	}
	else
		$baum = new baum("job");
 
	$baum->read($id);
	$tpl_content->addvar("pfad", implode(" > ", $baum->ar_path));
	if(!empty($baum->ar_baum))	
		$tpl_content->addlist("liste", $baum->ar_baum, "tpl/de/jobkats.row.htm");

  # echo ht(dump($baum->ar_baum));
?>