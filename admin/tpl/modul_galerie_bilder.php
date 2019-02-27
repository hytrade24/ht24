<?php
/* ###VERSIONSBLOCKINLCUDE### */


 if(isset($_GET['del']))
 {
   $err=array();
   $img = $db->fetch1("select * from img where ID_IMG=".$_GET['del']);
   $del=1;
   if(!empty($img['SRC_T']) && file_exists("../".$img['SRC_T']))
   {
     $del = @unlink("../".$img['SRC_T']);
	 if(!$del)
	   $err[] = "Thumbnail konnte nicht gelöscht werden!";	   
   }   
   if(file_exists("../".$img['SRC']))
     $del = @unlink("../".$img['SRC']);
   if(!$del)
     $err[] = "Bild konnte nicht gelöscht werden!";
   if(empty($err))
   { 
     $del = $db->querynow("delete from img where ID_IMG=".$_REQUEST['del']);
	 if(!$del['rsrc'])
	   die(ht(dump($del)));
   }
   else
     $tpl_content->addvar("err", implode("<br />", $err));
 }
 
 if(count($_POST['IMG']))
 {
   foreach($_POST['IMG'] as $key => $value)
   {
     $up=$db->querynow("update img set OK=".(isset($_POST['OK'][$value]) ? 1 : 0)."
	    where ID_IMG=".$value);
     if(!$up['rsrc'])
  	   die(ht(dump($up)));
   }
 }
 
 if(!isset($_REQUEST['npage']))
 {
   $_REQUEST['npage'] = 1;
   $_REQUEST['orderby'] = 'date';
   $_REQUEST['FREE']=1;
 }
 
 $where = array();
 
 if(!empty($_REQUEST['FREE']))
 {
   $where[] = " OK = 0 ";
   $tpl_content->addvar("FREE", 1);   
 }
 
 switch($_REQUEST['orderby'])
 {
   case 'date': $order = " ORDER by DATUM DESC ";
   break;
   case 'user' : $order = " order by NAME ";
   break;
   case 'galerie' : $order = " order by g.LABEL ";
   break;   
 }
 
 $where[] = " FK_GALERIE > 0 ";
 
 $tpl_content->addvars($_REQUEST); 
 $tpl_content->addvar("order_".$_REQUEST['orderby'], 1);
 
 $perpage = 9; // Elemente pro Seite
 $limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

 $where = (count($where) ? "where ".implode("and", $where) : '');
 $all = $db->fetch_atom("select count(*) from img ".$where);
 
 $ar_img = $db->fetch_table("select i.*, u.NAME, g.LABEL as GAL from img i
     left join user u on i.FK_USER=u.ID_USER
  	 left join galerie g on i.FK_GALERIE=g.ID_GALERIE
    ".$where." ".$order." limit ".$limit.", ".$perpage);
 $k=1;
 $tmp=array();
 for($i=0; $i<$alle=count($ar_img); $i++)
 {
   if($k==4)
     $k=1;
   $tpl_tmp = new Template("tpl/de/modul_galerie_bilder.row.htm");
   $tpl_tmp->addvar("k", $k);
   $tpl_tmp->addvars($ar_img[$i]);      
   if($i == $all-1)
     $tpl_tmp->addvar("end", 1);
   $tmp[] = $tpl_tmp;
   $k++;
 }
 
 $tpl_content->addvar("bilder", $tmp); 
 $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$page."&orderby=".$_REQUEST['orderby']."&FREE=".($_REQUEST['FREE'] ? 1 : 0)."&npage=", $perpage)); 
?>