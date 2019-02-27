<?php
/* ###VERSIONSBLOCKINLCUDE### */


	include "sys/lib.baum.php";
	$baum = new baum("tutorial");
	
	$tpl_content->addvar("tree", implode("\n", $baum->printTree()));
?>