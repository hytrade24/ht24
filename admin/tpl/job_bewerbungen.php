<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $id = (int)$_REQUEST['ID_JOB'];
 $ar = $db->fetch1("select JOBTITLE from job where ID_JOB=".$id);
 $tpl_content->addvars($ar);
 
 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $perpage = 10;
 $limit = ($perpage*$npage)-$perpage;
 
 $liste = $db->fetch_table("select ju.FK_USER,round(ju.PREIS, 2) as PREIS,ju.MSG,ju.STAMP,left(ju.MSG, 20) as `TEXT`,u.NAME as UNAME 
  from job2user ju
   left join `user` u on ju.FK_USER=u.ID_USER
  where FK_JOB=".$id."
	order by ju.STAMP DESC");
 $tpl_content->addlist("liste", $liste, "tpl/de/job_bewerbungen.row.htm");
 
 $all = $db->fetch_atom("select count(*) from job2user where FK_JOB=".$id);
 
 $tpl_content->addvar("pager", "<p class=\"error\">Achtung htm_browse() muss ajax f&auml;hig gemacht werden!</p>");

?>