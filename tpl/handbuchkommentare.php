<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $id = (int)$ar_params[1];
 
 if($ar_params[3] == 'ok')
   $tpl_content->addvar("K_SAVED", 1);
 
 $npage = ((int)$ar_params[4] ? $ar_params[4] : 1);
 $tpl_content->addvar("npage", $npage);
 $perpage = 20;
 $limit = (($perpage*$npage)-$perpage);
 
 $ar_artikel = $db->fetch1("select t.*, s.V1, s.V2
  from `script` t 
   left join string_script s on s.S_TABLE='script' and s.FK=t.ID_SCRIPT 
    and s.BF_LANG=if(t.BF_LANG_SCRIPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT+0.5)/log(2)))
  where t.ID_SCRIPT=".(int)$id." and OK = 3");
 
# echo ht(dump($ar_artikel));
 
 if(!empty($ar_artikel))
 {
   $tpl_content->addvars($ar_artikel);
   $all = $db->fetch_atom("select count(*) from kommentar_script where FK=".$id." and PUBLISH = 1");
   $tpl_content->addvar("all", $all);
   $liste = $db->fetch_table("select * from kommentar_script
   left join user on ID_USER = FK_USER 
    where FK=".$id." and PUBLISH=1
	order by STAMP DESC
	limit ".$limit.", ".$perpage."
	");
   $tpl_content->addlist("liste", $liste, "tpl/".$s_lang."/scriptkommentare.row.htm");
   
   $pager = htm_browse($all, $npage, "/scripte/scriptkommentare,".$id.",,,", $perpage);
   $tpl_content->addvar("pager", $pager);
 } // artikel ist da
 
?>