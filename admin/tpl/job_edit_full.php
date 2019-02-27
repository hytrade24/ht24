<?php
/* ###VERSIONSBLOCKINLCUDE### */


/*
 function findNode($id)
 {
   global $db, $nar_systemsettings;
	 $ar = $db->fetch1("select ID_TREE_JOB, `PARENT` from tree_job where ID_TREE_JOB=".(int)$id);
	 if($ar['PARENT'])
	   return findNode($ar['PARENT']);
	 else
	 {
		 if($ar['ID_TREE_JOB'] == $nar_systemsettings['jobs']['START_ANGEBOT'])
		   return "ANGEBOT";
		 else
		   return "GESUCH";
	 } // no parent
 } // findNode()*/

 $id = (int)$_REQUEST['ID_JOB'];
 
 ### pdf löschen
 if($_REQUEST['delpdf'])
 {
   $ar_pdf = $db->fetch1("select * from job2pdf where ID_JOB2PDF =".(int)$_REQUEST['delpdf']);
	 if($ar_pdf)
	 {
	   unlink($ab_path."uploads/users/pdf/".$ar_pdf['FILE']);
		 $db->querynow("delete from job2pdf where ID_JOB2PDF=".$_REQUEST['delpdf']);
		 $tpl_content->addvar("DELPDF", 1);
	 } // pdf gefunden
 } // pdf löschen
 
 if(count($_POST))
 {
   //$_POST['JOBTYP_'.$_POST['JOBTYP']]=1;
	/* $_POST['JOBTYP'] = findNode($_POST['FK_KAT']);*/
	 
	 $_POST['OK'] = array_sum($_POST['OK']);
   $tpl_content->addvar("SAVED", 1);
	 
	 if($_POST['LU_VERGUETUNG'] != 61) {
		  $_POST['PRICE_NUM'] = 0;
		  $_POST['PRICE_MUST'] = 0;
		} //vergütung
		
		if($_POST['LU_WORKWERE'] == 68) {
		  $_POST['ANMERKUNG'] = "";
		} //vorort
		elseif($_POST['LU_WORKWERE'] == 69) {
		  $_POST['PLZ'] = "";
		  $_POST['FK_COUNTRY'] = "";
		} //homeoffice
		
		if($_POST['FLAG_START'] != 1) {
		  $_POST['WANN'] = NULL;
		} //beliebig oder sofort
	 #die(ht(dump($_POST)));
	 $check = $db->fetch_atom("select OK from job_live where ID_JOB_LIVE=".$_POST['ID_JOB']);
	 if($_POST['OK'] == 3)
	 {
	   if($check != 3)
		 {
		   $_POST['STAMP'] = date('Y-m-d H:i:s');
		   $_POST['STAMP_END'] = date('Y-m-d H:i:s', strtotime('+'.$nar_systemsettings['jobs']['runtime']." days"));
		 } // noch nicht ONLINE
		 else
		 {
		   date_implode($_POST, "STAMP");
		   date_implode($_POST, "STAMP_END");			 
		 }
		 
		 /*$_POST['ID_JOB_LIVE'] = $_POST['ID_JOB'];
		 $_POST=array_merge($db->fetch1("select * from job where ID_JOB=".$id), $_POST);
		# die(ht(dump($_POST)));
		 $db->update("job_live", $_POST);
		 todo("Jobs neu cachen", "cron/job_cache.php", NULL, NULL, NULL, 'jobs');*/
		# die(ht(dump($lastresult)));
	 } // ok = 3
	 $db->update("job", $_POST);
	 if($_POST['OK'] == 3)
	 {
	     $db->querynow("delete from job_live where ID_JOB_LIVE = ".(int)$id);
		 $db->querynow("insert into job_live (select * from job where ID_JOB = ".(int)$id.")");
		 $db->querynow("UPDATE job2user SET FLAG_CHANGE=1 WHERE FK_JOB=".(int)$id);
   		 $db->querynow("UPDATE job2watch SET FLAG_CHANGE=1 WHERE FK_JOB=".(int)$id);
	     todo("Jobs neu cachen", "cron/job_cache.php", NULL, NULL, NULL, 'jobs');
	 }
	 $tpl_content->addvars($_POST);
 } // poste
 
  $ar = $db->fetch1("select j.*,o.OK as ONLINE, u.NAME as WINNER, ju.PREIS as GEBOT 
	   from job j 
		  left join job_live o on j.ID_JOB=o.ID_JOB_LIVE
			left join `user`u on j.FK_USER2=u.ID_USER
			left join job2user ju on u.ID_USER=ju.FK_USER and j.ID_JOB=ju.FK_JOB
		 where j.ID_JOB=".$id);
	 $ar['JOBTYP_'.$ar['JOBTYP']]=1;
	 $tpl_content->addvars($ar);
	 
	### PDF
	$pdf = $db->fetch_table("select * from job2pdf where FK_JOB=".$id);
	$tpl_content->addlist("liste", $pdf, "tpl/de/job_edit.pdf.htm");
 

?>