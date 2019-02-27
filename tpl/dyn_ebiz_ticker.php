<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $liste = $db->fetch_table("select *,ID_IMGTICKER*0+ rand() AS sort 
   from imgticker where OK=1 order by sort");
 $tmp=array(); 
 for($i=0; $i<count($liste); $i++)
 {
    $liste[$i]['TEXT'] = trim(str_replace("\r\n", "", $liste[$i]['TEXT']));
	$tpl_tmp=new Template("tpl/".$s_lang."/"."dyn_ebiz_ticker.row.htm");
	$tpl_tmp->addvars($liste[$i]); 
	$tpl_tmp->addvar('i', $i+1); 
	$tmp[] = $tpl_tmp; 
 }
 $mc = count($liste)+1;
 
 $tpl_main->addvar("CONF", file_get_contents("cache/ebizticker.js"));
 
 $tpl_main->addvar('mc', $mc);
 $tpl_main->addvar('liste', $tmp);

?>