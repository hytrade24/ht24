<?php
/* ###VERSIONSBLOCKINLCUDE### */


$inforow = array();
function check_modul_status (&$row,$i)
{ 
	global $db,$tpl_content;
	//return;
	$inforow =array();
	require_once 'module/'.$row['IDENT'].'.info.php';
	if (empty($inforow)) 
		return;
	foreach ($inforow as $key => $value)
		{
  			$tpl_tmp = new Template('tpl/de/module.rowinc.htm');
  			$tpl_tmp->addvar('frage',$value[0]);  
			$tpl_tmp->addvar('antwort',$value[1]);
			$tpl_tmp->addvar('link',$value[2]);  
  			$tmp[] = $tpl_tmp;
		}
	$row['modulzeile']=$tmp;
}


$tpl_content->addlist("module", $db->fetch_table("select IDENT,LABEL,infotext from modul where B_VIS order by LABEL"), "tpl/de/module.row.htm",'check_modul_status');


?>