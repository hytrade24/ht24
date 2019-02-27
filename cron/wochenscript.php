<?php
/* ###VERSIONSBLOCKINLCUDE### */



 /* dieses Script sollte Sonntags vor 0:00 Uhr ausgeführt werden !! */

 global $db;
 $kw = date("W")+1; // kw (die nächste)

 $fk_script = $db->fetch_atom($q= "select FK_SCRIPT from scripttop where JAHR=".date('Y')." and KW=".$kw);
 echo "\n".$q."\n";
 echo dump($lastresult);
 if(!$fk_script)
 {
    $fk_script = $db->fetch_atom("select `value` from `option` where plugin='SCRIPT' and `typ`='DEFAULT'");
	echo dump($lastresult);
 } // kein script eingetragen

 $res = $db->querynow("select * from lang where B_PUBLIC=1");
 while($row = mysql_fetch_assoc($res['rsrc']))
 {
   $s_lang = $row["ABBR"];
   $langval = $row["BITVAL"];

   $ar = $db->fetch1($str = "select t.*, (select sum(VIEWS) from scriptview where FK_SCRIPT = t.ID_SCRIPT group by FK_SCRIPT) as GVIEW, s.V1, s.V2, s.T1, u.`NAME` as UNAME,u.ID_USER, u2.*
    ,sk.T1 as ariane
	from `script` t
     left join scriptview sv on t.ID_SCRIPT=sv.FK_SCRIPT
	 left join `user` u on t.FK_USER=u.ID_USER
     left join user2img u2 on u2.WHAT='SCRIPT' and u2.FK=t.ID_SCRIPT
	 left join string_script s
      on s.S_TABLE='script' and s.FK=t.ID_SCRIPT
	  and s.BF_LANG=if(t.BF_LANG_SCRIPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT+0.5)/log(2)))
     left join tree_script tt on t.FK_KAT=tt.ID_TREE_SCRIPT
	  left join string_tree_script sk on sk.S_TABLE='tree_script'
       and sk.FK=tt.ID_TREE_SCRIPT and sk.BF_LANG=if(tt.BF_LANG_TREE_SCRIPT & ".$langval.", ".$langval.", 1 << floor(log(tt.BF_LANG_TREE_SCRIPT+0.5)/log(2)))
	where t.ID_SCRIPT=".(int)$fk_script."
	group by t.ID_SCRIPT");

   #die(dump($str));

   $tpl = new Template($ab_path."tpl/".$s_lang."/script_woche.htm");
   if(!empty($ar))
   		$tpl->addvars($ar);
   file_put_contents($ab_path."cache/script_woche.".$s_lang.".htm", $tpl->process());
   chmod($ab_path."cache/script_woche.".$s_lang.".htm", 0777);
 } // while lang

?>