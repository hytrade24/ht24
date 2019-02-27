<?php

$npage = ((int)$ar_params[4] ? $ar_params[4] : 1);
$perpage = 20;
$limit = ($npage*$perpage)-$perpage;

 ### delete
 if($ar_params[1] == "del")
 {



   if($db->fetch_atom("select FK_AUTOR from job where (OK < 3 ) and ID_JOB=".(int)$ar_params[2]) == $uid){
     $res = $db->delete("job", (int)$ar_params[2]);
	 } else
	 {
	 $err[]="NO_RIGHT";
	 }

   #echo ht(dump($lastresult)); die();
 } // löschen

 #$all = $db->fetch_atom("select count(*) from job where FK_AUTOR=".$uid);

 #die($db->lang_select("job"));
 $res = $db->querynow("select t.*, s.V1, s.V2, s.T1,(STAMPEND > NOW() or STAMPEND IS NULL) as is_active
  from `job` t
   left join string_job s
    on s.S_TABLE='job'
	 and s.FK=t.ID_JOB
	 and s.BF_LANG=if(t.BF_LANG_JOB & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_JOB+0.5)/log(2)))
  where t.FK_AUTOR=".$uid."
  order by t.STAMP DESC  LIMIT ".$limit.", ".$perpage);

  $ar_tmp = array();
  $i=0;
  while($row = mysql_fetch_assoc($res['rsrc']))
  {
    $ar = $db->fetch_table("select l.ABBR from string_job s
	 left join lang l on l.BITVAL=s.BF_LANG
	 where
	  S_TABLE='job' and FK=".$row['ID_JOB']);
	$row['langs']='';
	for($k=0; $k<count($ar); $k++) {
		$row['langs'] .= '<img src="'.$ab_baseurl.'gfx/lang.'.$ar[$k]['ABBR'].'.gif" /> ';
	}

	$tmp = new Template("tpl/".$s_lang."/my-jobs.row.htm");
	$tmp->addvars($row);
	$tmp->addvar("even", $i%2);
	$ar_tmp[] = $tmp;
	$i++;
  } // while artikel
  $tpl_content->addvar("jobruntime",$nar_systemsettings['jobs']['runtime']);
  $tpl_content->addvar("liste", $ar_tmp);

 if (count($err)){
  $err = implode(",", $err);
  $err = get_messages("ALLGEMEIN", $err);
  $tpl_content->addvar('err',implode('<br />- ', $err));
	}
  	    $all = $db->fetch_atom("select count(*) from `job` t WHERE FK_AUTOR = $uid");
		$pager = htm_browse_extended($all, $npage, 'my-jobs,,,,{PAGE}', $perpage);
		$tpl_content->addvar("npage", $npage);
		$tpl_content->addvar("pager", $pager);
		//$tpl_content->addvars($data0);

//	$tpl_modul->addvar("MODECODE", $tpl_mode);

?>