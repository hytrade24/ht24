<?php
/* ###VERSIONSBLOCKINLCUDE### */



 global $db;

 $res_lang = $db->querynow("select * from lang where B_PUBLIC=1");
 while($langrow = mysql_fetch_assoc($res_lang['rsrc']))
 {
   $s_lang = $langrow['ABBR'];
   $langval = $langrow['BITVAL'];

   $res = $db->querynow("select j.ID_JOB_LIVE,j.JOBTITLE,j.STAMP, j.DSC,  s.T1 as KAT
	  from job_live j
	   left join string_tree_job s on j.FK_KAT = s.FK and s.BF_LANG=".$langval."
		where j.OK=3 and (j.STATUS & 5) = 1
		order by STAMP DESC LIMIT 6
	 ");
   #echo dump($res);

	 $tpl = new Template($ab_path."tpl/".$s_lang."/job_neu.htm");
   $tpl_list = new Template($ab_path."tpl/".$s_lang."/job_neu_list.htm");

	 //$tpl->addlist("liste", $tuts, $ab_path."tpl/".$s_lang."/job_neu.row.htm");
   $tmp = $tmp_list = array(); $i=1;
	 while($row = mysql_fetch_assoc($res['rsrc']))
	 {
		 if($i == 3)
		 {
		   $row['tr_open'] = 1;
			 $i=1;
		 } // new row
		 $tpl_tmp = new Template($ab_path."tpl/".$s_lang."/job_neu.row.htm");
		 $tpl_tmp->addvars($row);
		 $tmp[] = $tpl_tmp;

		 $tpl_tmp_list = new Template($ab_path."tpl/".$s_lang."/job_neu_list.row.htm");
		 $tpl_tmp_list->addvars($row);
		 $tmp_list[] = $tpl_tmp_list;

		 $i++;

		 #echo dump($row);
	 } // while jobs

	 $tpl->addvar("liste", $tmp);
	 $tpl_list->addvar("liste", $tmp_list);

	 file_put_contents($ab_path."cache/job_neu.".$s_lang.".htm", $tpl->process());
	 file_put_contents($ab_path."cache/job_neu_list.".$s_lang.".htm", $tpl_list->process());
	 chmod($ab_path."cache/job_neu.".$s_lang.".htm", 0777);
	 chmod($ab_path."cache/job_neu_list.".$s_lang.".htm", 0777);
 } // while sprachen

?>