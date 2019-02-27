<?php
/* ###VERSIONSBLOCKINLCUDE### */


	include_once "sys/lib.baum.php";
	$id = ($_REQUEST['ID'] ? $_REQUEST['ID'] : 0);
 
	$baum = new baum("tutorial", $id);
	$a_node = new baum("tutorial", $_REQUEST['ID_TREE_TUTORIAL']);
	
	if ($_REQUEST['d'] == 'moved')
		$tpl_content->addvar('rel', 1);
 
	if(count($_POST))
	{
  	$baum->moveNode($_REQUEST['ID_TREE_TUTORIAL'], $_REQUEST['ID']);
		forward('index.php?page=modul_tutorial_katbaum_move&ID_TREE_TUTORIAL='.$_REQUEST['ID_TREE_TUTORIAL'].'&ID='.$_REQUEST['ID']."&d=moved&frame=popup");
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
	$tpl_content->addlist("liste", $baum->ar_baum, "tpl/de/modul_tutorial_katbaum_move.row.htm");
 
	$moveable = false;
	if($a_node->active_node['PARENT'] != 0 && !$_REQUEST['ID'])
		$moveable = true;
	if($_REQUEST['ID'] && $_REQUEST['ID'] != $_REQUEST['ID_COUNTRYTREE'])
		$moveable = true;

	$tpl_content->addvar("moveable", $moveable);
?>