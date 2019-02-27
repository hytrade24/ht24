<?php
/* ###VERSIONSBLOCKINLCUDE### */



 /*

	erzeugt die 4 neuesten scripte und ändert die Sortierung.
    einmal die Nacht ausführen!

 */

 global $db;

 $res = $db->querynow("select * from lang where B_PUBLIC=1");
 while($row = mysql_fetch_assoc($res['rsrc']))
 {
   $s_lang = $row["ABBR"];
   $langval = $row["BITVAL"];

   $res_s = $db->querynow($str = "select ORG.*, ORG.VIEWS as GHITS, s.V1, s.V2, s.T1, u.`NAME` as UNAME,u.ID_USER, u2.*
    ,sk.T1 as ariane, (select count(*) from kommentar_script where FK = t.ID_SCRIPT_WORK) as GCOMMENTS
	from `script_work` t
     left join script ORG on t.ID_SCRIPT_WORK=ORG.ID_SCRIPT
     left join scriptview sv on ORG.ID_SCRIPT=sv.FK_SCRIPT
	 left join `user` u on t.FK_USER=u.ID_USER
     left join user2img u2 on u2.WHAT='SCRIPT' and u2.FK=ORG.ID_SCRIPT
	 left join string_script_work s
      on s.S_TABLE='script_work' and s.FK=t.ID_SCRIPT_WORK
	  and s.BF_LANG=if(t.BF_LANG_SCRIPT_WORK & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT_WORK+0.5)/log(2)))
     left join tree_script tt on t.FK_KAT=tt.ID_TREE_SCRIPT
	  left join string_tree_script sk on sk.S_TABLE='tree_script'
       and sk.FK=tt.ID_TREE_SCRIPT and sk.BF_LANG=if(tt.BF_LANG_TREE_SCRIPT & ".$langval.", ".$langval.", 1 << floor(log(tt.BF_LANG_TREE_SCRIPT+0.5)/log(2)))
	where ORG.OK=3
	group by ORG.ID_SCRIPT
	order by ORG.STAMP_UPDATE DESC
	LIMIT 6
	");
   #die(dump($ar));
   $tpl = new Template($ab_path."tpl/".$s_lang."/script_neu.htm");

   $tmp = $tmp2 = array(); $i=1;
	 while($rows = mysql_fetch_assoc($res_s['rsrc']))
	 {
	   if($i == 3)
		 {
		   $rows['tr_open'] = 1;
			 $i=1;
		 } // new row
		 $tpl_tmp = new Template($ab_path."tpl/".$s_lang."/script_neu.row.htm");
		 $tpl_tmp->addvars($rows);
		 $tmp[] = $tpl_tmp;
		 $i++;

		 $rows['PATH'] = "/uploads/users/img";
		 #*** zweite scriptneuzugänge (breite ansicht)
		 $tpl_tmp2 = new Template($ab_path."tpl/".$s_lang."/scripte.row.htm");
		 $tpl_tmp2->addvars($rows);
		 $tmp2[] = $tpl_tmp2->process();
		 $i++;
	 } // while jobs

	 $tpl->addvar("liste", $tmp);

   file_put_contents($ab_path."cache/script_neu.".$s_lang.".htm", $tpl->process());
   file_put_contents($ab_path."cache/script_neu2.".$s_lang.".htm", implode("\n", $tmp2));
   chmod($ab_path."cache/script_neu.".$s_lang.".htm", 0777);
   chmod($ab_path."cache/script_neu2.".$s_lang.".htm", 0777);
 } // while sprachen

 ### Sortierung ändern
 $db->querynow("update script set ORDERFIELD=RAND() where OK=3");

?>