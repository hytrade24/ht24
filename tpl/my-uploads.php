<?php
 
 if($_REQUEST['DEL'])
 {
   #if($_REQUEST['what'] != "job") {
	   $ar_img = $db->fetch1("select * from user2img where ID_USER2IMG=".(int)$_REQUEST['DEL']);
     if($ar_img['FK_USER'] == $uid && $ar_img['FK'] == $_REQUEST['FK'])
     {
       $ar = $db->fetch_table("select * from string_c where FK=".(int)$_REQUEST['FK']." and s_table='".strtolower($_REQUEST['what'])."'");
	   #echo ht(dump($lastresult));
	   for($i=0; $i<count($ar); $i++)
	   {
	     $pattern = "/(\[img:)(\s?)(".$_REQUEST['DEL'].")(\])/si";
	     #echo $pattern."<hr>";
	     $ar[$i]['T1'] = preg_replace($pattern, "", $ar[$i]['T1']);
	     $db->querynow("update string_c set T1='".sqlString($ar[$i]['T1'])."' 
	      where FK=".(int)$_REQUEST['FK']." and s_table='".strtolower($_REQUEST['what'])."'
		   and BF_LANG=".$ar[$i]['BF_LANG']."
		  ");
	   } // for texte
	   unlink($ab_path.$ar_img['PATH'].$ar_img['IMG']);
	   if($ar_img['THUMB'])
	     unlink($ab_path.$ar_img['PATH'].$ar_img['THUMB']);
       $db->delete("user2img", $_REQUEST['DEL']);
     }
	/*
	 } // nicht job
	 else
	 {
	   $file = $db->fetch_atom($q="select `FILE` from job2pdf where ID_JOB2PDF=".$_REQUEST['DEL']." and FK_USER=".$uid);
		 //echo $file;
		 if($file)
		 {
		   @unlink($ab_path."uploads/user/pdf/".$file);
			 $res=$db->querynow($q="delete from job2pdf where ID_JOB2PDF=".$_REQUEST['DEL']." and FK_USER=".$uid);
			# echo ht(dump($res));
		 }
	 } // job
	 */
 } // delete
 

 
 $err = array();
 $_REQUEST['what']=strtolower($_REQUEST['what']);
 $what = $_REQUEST['what'];
 $_REQUEST['what_'.$_REQUEST['what']]=1;
 
 $tpl_content->addvar("what", $what);
 $tpl_content->addvars($_REQUEST);
 #echo ht(dump($tpl_content->vars));
 
 switch($what)
 {
   case "tutorial":
   case "news": 
   case "job":
   $ar_thumb = array
   (
     'width' => 80,
     'height' => 80,
     'name' => 'thumb_'   
   );
   $ar_file = false;
   break;
   case 'script':
   $ar_thumb = array
   (
     'width' => 150,
     'height' => 150,
     'name' => 'thumb_'   
   );
   $ar_file = array
   (
     'width' => 400,
     'height' => 400,
     'name' => ''    
   );
   break;
 } // switch $what
 
# echo ht(dump($file));
# die(ht(dump($ar_thumb)));
 #if($what != 'job')
 #{
   if ($_FILES['UP']['tmp_name']) // Bild speichern 
   {
     $filedir = $ab_path."uploads/users/img/";
   
     $upload = userImg($_FILES['UP'], $filedir, $ar_thumb, $ar_file); 
   
     if(!$upload)
       $tpl_content->addvar("err", 1);
     else
     {
       $ar = array(
	     'FK_USER' => $uid,
	     'FK' => $_REQUEST['FK'],
	     'WHAT' => strtoupper($_REQUEST['what']),
	     'IMG' => $upload['IMG']['file'],
	     'PATH' => $upload['IMG']['path'],
	     'THUMB' => $upload['THUMB']['file']
	   );
	   $db->update("user2img", $ar);
	 //die(ht(dump($lastresult)));
	   die(forward("index.php?page=my-uploads&what=".$_REQUEST['what']."&FK=".$_REQUEST['FK']."&frame=".$tpl_content->vars['curframe']));
     } // upload erfolgreich   
   } // count files  
 #} // kein job
 /*
 elseif($_FILES['UP']['tmp_name']) {
   $tmp = $_FILES['UP']['tmp_name'];
	 $name = strtolower($_FILES['UP']['name']);
	 $hack = explode(".", $name);
	 $n=(count($hack)-1);
	 $ext = $hack[$n];
	 if($ext != "pdf")
	 {
	 
	 } // kein pdf
	 else
	 {
	   $time = microtime();
		 $time = preg_replace("/[^0-9]{1,}/si", "", $time);
		 $new_name = $time . ".pdf";
		 //echo $new_name;
		 move_uploaded_file($_FILES['UP']['tmp_name'], $ab_path."uploads/users/pdf/".$new_name);
		 $ar = array(
		   'FK_USER' => $uid,
			 'FK_JOB' => $_REQUEST['FK'],
			 'DSC' => $name,
			 'FILE' => $time.".pdf"
		 );
		 $db->update("job2pdf", $ar);
	 }
	 #die("hier halten wir mal eben an ;-)");
 } // JOB 
  */
 ### bestehende auslesen
 //die($what);
 /*
 if($what == 'job')
 {
   $ar = $db->fetch_table($q="select *, 1 as 'what_".$_REQUEST['what']."'  from job2pdf
    where FK_USER=".$uid." and FK_JOB=".(int)$_REQUEST['FK']."
    order by ID_JOB2PDF DESC");
   $tpl_content->addlist("liste", $ar, "tpl/".$s_lang."/my-uploads.pdfrow.htm");
	 #echo ht(dump($ar));
 } 
 else
 {*/
   $ar = $db->fetch_table($q="select *, 1 as 'what_".$_REQUEST['what']."'  from user2img 
    where FK_USER=".$uid." and FK=".(int)$_REQUEST['FK']." and WHAT='".mysql_escape_string(strtoupper($_REQUEST['what']))."'
    order by ID_USER2IMG DESC");
   $tpl_content->addlist("liste", $ar, "tpl/".$s_lang."/my-uploads.row.htm");
 # } // no job 
 
?>
