<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $nummer=1;
 function lcheck(&$ar, $i)
 {
    global $db, $nummer;
	  $ar['NUMMER']=$nummer;
	  $nummer++;
		
		$ar['JOBTYP_'.$ar['JOBTYP']]=1;
		$ar['ENDPREIS'] = round($ar['ENDPREIS'],2);
		
		$active = 0;
		if($ar['STAMP_END'] != '0000-00-00 00:00:00')
		{
		  if($ar['STAMP_END'] > date('Y-m-d H:i:s') && $ar['OK'] == 3);
			  $active = 1;
		}
		
		$ar['ACTIVE'] = $active;
 } // lcheck()

  if(count($_POST))
	{
    if($_POST['ALL'])
    {
      $_POST['RED_'] = 0;
	    $_POST['ADM_']=0;
    }
    if(!$_POST['RED_'] && !$_POST['ADM_'] && !$_POST['ALL'])
      $_POST['RED_']=0;
		if($_POST['_NAME'])
    {
      $user = $db->fetch_atom("select ID_USER from `user` where  `NAME`='".sqlString($_POST['_NAME'])."'");
	    if($user)
	     $_POST['FK_USER'] = $user;
    } // name
    $ser = array("SER_PARAMS" => serialize($_POST), "STAMP" => date('Y-m-d H:i:s', strtotime('+1 day')));
    $id_s = $db->update("search", $ser);
    die(forward("index.php?page=jobs&ID_S=".$id_s));		
	} // count $_POST[
	
	if($_REQUEST['ID_S'])
	{
    $db->querynow("delete from search where STAMP < now()");
    $tpl_content->addvar("S_ID", $_REQUEST['S_ID']);
    $ar_pre = $db->fetch_atom("select SER_PARAMS from search where ID_SEARCH=".$_REQUEST['ID_S']);
    $ar_pre = unserialize($ar_pre);
	} // suchparams
	else
	{
    $ar_pre = array
    (
      'orderby' => "j.STAMP",
	    'updown' => 'DESC',
	    'ACTIVE' => 1,
	    'FK_USER' => NULL, 
	    'JOBTITLE' => NULL,
	    'ALL' => 1
     );	
  } // suchparameter voreinstellung
	
 ### auslesen etc.
 $tpl_content->addvars($ar_pre);
 $tpl_content->addvar("updown_".$ar_pre['updown'], 1);
 
 $orders = array
 (
   'j.STAMP' => "Einstelldatum",
   'u.`NAME`' => 'Username',
   'sk.V1' => "Kategorie"
 );
 
 $tpl_order = array();
 foreach($orders as $key => $value)
 {
   $selected = ($key == $ar_pre['orderby'] ? ' selected' : '');
   $tpl_order[] = '<option value="'.$key.'" '.$selected.'>'.stdHtmlentities($value).'</option>';
 } // for $orders
 $tpl_content->addvar("orders", implode("\n", $tpl_order));
 
 $where = array();
 
 if($ar_pre['FK_KAT'])
   $where[] = "j.FK_KAT = ".$ar_pre['FK_KAT'];
 $ok=0;
 if($ar_pre['SHOW'] == 1)
   $where[] = "j.STAMP_ABSCHLUSS IS NOT NULL";
 if($ar_pre['SHOW'] == 2)
   $where[] = "j.STAMP_ABSCHLUSS IS  NULL";
 if($ar_pre['RED_'])
   $ok += 1;
 if($ar_pre['ADM_'])
   $ok += 2;
 if($ar_pre['ALL'])
   $ok = false;
 if($ok !== false)
   $where[] = "j.OK=".$ok; 
 else
   $where[] = "OK >= 0";
 if($ar_pre['FK_USER'])
   $where[] = "j.FK_USER=".$ar_pre['FK_USER'];
 if($ar_pre['JOBTITLE'])
   $where[] = "j.JOBTITLE LIKE '%".sqlString($ar_pre['JOBTITLE'])."%'";
 
 $perpage = 30;
 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $limit = (($npage*$perpage)-$perpage);
 
 $all = $db->fetch_atom("select count(*) from job j where ".implode(" and ", $where)); 
 #echo ht(dump($lastresult));
 $liste = $db->fetch_table("select j.*, u.NAME as UNAME, 
   win.NAME as WINNER,
	 ".$npage." as npage, ".(int)$_REQUEST['ID_S']." as ID_S,
   count(distinct ju.FK_JOB) as N_BEWERBUNG, 
	 round(max(ju.PREIS),2) as HIGH, 
	 round(min(ju.PREIS),2) as LOW 
  from job j
   left join `user` u on j.FK_USER=u.ID_USER
	 left join `user` win on j.FK_USER2=win.ID_USER
	 left join job2user ju on j.ID_JOB=ju.FK_JOB	 
	where ".implode(" and ", $where)."
	group by j.ID_JOB
	order by ".$ar_pre['orderby']." ".$ar_pre['updown']."
	limit ".$limit.", ".$perpage."
 ");
 
 $tpl_content->addlist("liste", $liste, "tpl/de/jobs.row.htm", "lcheck");
 $tpl_content->addvar("pager", htm_browse($all, $npage, "index.php?page=jobs&ID_S=".$_REQUEST['ID_S']."6npage=", $perpage));
 
 
?>