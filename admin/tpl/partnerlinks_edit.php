<?php
/* ###VERSIONSBLOCKINLCUDE### */

 

	if(count($_POST))
	{
		$err=array();
		if(empty($_POST['LINKTITEL']) or empty($_POST['LINK']))
	  		$err[] = "Bitte eine Linktitel und Link eingeben!";
		
		date_implode($_POST, 'PAIDTIL',true);
		
		if(empty($err))
		{
	  		$id = $_POST['ID_PARTNERLINKS'];
	  		$id_new = $db->update("partnerlinks", $_POST);
	  		if(!$id)
	    		$id = $id_new;	 
				
			require_once("sys/lib.cache.php");
			cache_partnerlinks();
			
	  		die(forward("index.php?page=partnerlinks&ok=1"));
		} else {
			$tpl_content->addvar("err", implode("<br>", $err));
			$tpl_content->addvars($_POST);
		} 
		
		//empty
		
	} else 	{
	 	if($_REQUEST['ID_PARTNERLINKS'])
		{
	  		$ar = $db->fetch1("select * from partnerlinks where ID_PARTNERLINKS=".$_REQUEST['ID_PARTNERLINKS']);
	  		$tpl_content->addvars($ar);
		} // ID_PARTNERLINKS 
	
	}// count
?>