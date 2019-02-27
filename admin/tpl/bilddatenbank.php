<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(isset($_GET['del']))
 {
   $bild = $db->fetch1("select * from img where ID_IMG=".$_GET['del']);
   @unlink("../".$bild['SRC']);
   if(!empty($bild['SRC_T']))
     @unlink("../".$bild['SRC_T']);
   $db->querynow("delete from img where ID_IMG = ".$_GET['del']); 
   forward("index.php?page=bilddatenbank&npage=".$_REQUEST['npage']);
 }

 $c_bilder = $db->fetch_atom("select count(*) from img");
 $tpl_content->addvar("alle", $c_bilder);

 if(!isset($_REQUEST['npage']))
   $_REQUEST['npage']=1;   

 $limit=(($_REQUEST['npage']-1)*30);
 
 $ar_bilder = $db->fetch_table("select i.*,g.LABEL as GALLERY,u.NAME as OWNER from img i
     left join galerie g on i.FK_GALERIE=g.ID_GALERIE
	 left join user u on i.FK_USER=u.ID_USER
	 order by FK_GALERIE 
	 limit ".$limit.", 30");
	 
	 
 $qry_ar_generator = $db->querynow("select filename from bannergenerator group by `name`, filename");
 
 $ar_generator = array();
 while($tmp = mysql_fetch_assoc($qry_ar_generator['rsrc']))
 {
   foreach($tmp as $value)
     $ar_generator[] = $value;
 }

 for($i=0; $i<$all=count($ar_bilder); $i++)
 {
   if(in_array($ar_bilder[$i]['SRC'], $ar_generator))
     $ar_bilder[$i]['generator'] = 1;
 }

 $k=1;
 $tmp=array();
 for($i=0; $i<$all=count($ar_bilder); $i++)
 {
   if($k==(5))
     $k=1;
   $tpl_tmp = new Template("tpl/de/bilddatenbank.row.htm");
   $tpl_tmp->addvar("k", $k);
   $tpl_tmp->addvar("npage", $_REQUEST['npage']);
   $tpl_tmp->addvars($ar_bilder[$i]); 
   $ftmp=pathinfo($ar_bilder[$i]['SRC']);
   $tpl_tmp->addvar('pfilename',$ftmp['basename']);  
   if($i == $all-1)
     $tpl_tmp->addvar("end", 1);
   $tmp[] = $tpl_tmp;
   $k++;
   //echo ht(dump(pathinfo($ar_bilder[$i]['SRC'])));
 }
 
 $tpl_content->addvar("liste", $tmp);
 
 $tpl_content->addvar("pager", htm_browse($c_bilder, $_REQUEST['npage'], "index.php?page=bilddatenbank&npage=", 30));

?>