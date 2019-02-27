<?php
/* ###VERSIONSBLOCKINLCUDE### */


  	$ar_data = $db->fetch1('select banner from ads where ID_ADS='.$_REQUEST['ID_ADS']);
	 $tpl_content->addvars($ar_data);
//die(ht(dump($options)));
?>